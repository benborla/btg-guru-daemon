<?php

namespace App\Repositories\Nfl;

use App\Dto\NflScoreData;
use App\Dto\NflStandingsDto;
use App\Models\NflApiResponse;
use Illuminate\Support\Facades\Cache;
use App\Models\NflGame;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use App\Models\NflGamePlaybyplayScores;

class NflApiRepository
{
    const API_NFL_SCORES_URL = "https://www.goalserve.com/getfeed/9645f122eef946c1c7bd08dd5ac0e712/football/nfl-scores?json=1";
    const API_NFL_SCHEDULES_URL = "https://www.goalserve.com/getfeed/9645f122eef946c1c7bd08dd5ac0e712/football/nfl-schedule?json=1";
    const API_NFL_STANDINGS_URL = "https://www.goalserve.com/getfeed/9645f122eef946c1c7bd08dd5ac0e712/football/nfl-standings?json=1";
    const API_NFL_PLAYBYPLAY_URL = "https://www.goalserve.com/getfeed/9645f122eef946c1c7bd08dd5ac0e712/football/nfl-playbyplay-scores?json=1";
    const CACHE_SECONDS = 10;

    public bool $needToStore = false;
    
    public function getDbApiResponse()
    {
        $dataToday = NflApiResponse::getFirstByField('date_fetched', date('Y-m-d'));

        if (empty($dataToday)) {
            $dataToday = NflApiResponse::getFirstByField('date_fetched', date('Y-m-d', strtotime('-1 day')));
        }

        return json_decode($dataToday->response, true);
    }
    
    public function fetchApiScores()
    {
        $cacheKey = "nfl_api_scores_" . date('Y-m-d');

        if (Cache::has($cacheKey)) {
            $this->needToStore = false;
            return Cache::get($cacheKey);
        }

        $response = Http::get(self::API_NFL_SCORES_URL);

        Cache::put($cacheKey, $response->json(), now()->addSeconds(self::CACHE_SECONDS));
        $this->needToStore = true;

        return $response->json();
    }

    public function fetchApiStandings()
    {
        $cacheKey = "nfl_api_standings_" . date('Y-m-d');

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $response = Http::get(self::API_NFL_STANDINGS_URL);
        $oneDayCache = now()->addDays(1);

        Cache::put($cacheKey, $response->json(), $oneDayCache);

        return $response->json();
    }

    public function fetchApiPlayByPlay($isLive = false)
    {
        $cacheKey = "nfl_api_playbyplay" . date('Y-m-d');

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $response = Http::get(self::API_NFL_PLAYBYPLAY_URL);
        $defaultCache = now()->addDays(1);

        if ($isLive) {
            $defaultCache = now()->addSeconds(10);
        }

        $this->storePlayByPlay($response->json());
        Cache::put($cacheKey, $response->json(), $defaultCache);

        return $response->json();
    }

    private function storePlayByPlay($response)
    {
        $match = collect($response['scores']['category']['match']);

        if ($match->count() >= 0) {

            $match->map(function($item){
                NflGamePlaybyplayScores::updateOrCreate(
                    [
                        'contest_id' => $item['contestID']
                    ],
                    [
                        'response' => $item
                    ]
                );
            });
        
        }
    }
    
    public function getScoreBoardDataFromApi()
    {
        $response = $this->fetchApiScores();

        return collect($response['scores']['category']['match']);
    }

    public function fetchApiSchedules()
    {
        $response = Http::get(self::API_NFL_SCHEDULES_URL);

        // add new row
        $schedules = NflApiResponse::updateOrCreate(
            [
                'date_fetched' => date('Y-m-d'),
                'season' => date('Y')
            ],
            [
                'response' => json_encode($response->json())
            ]
        );

        return $response;
    }   

    public function getFullSchedule() 
    {
       $data = NflApiResponse::getFirstByField('date_fetched', date('Y-m-d'));

       if (empty($data)) {
        return $this->fetchApiSchedules();
       }

       $data = json_decode($data->response, true);

       return $data;
    }

    public function findTheCurrentWeek()
    {
        $data = $this->getFullSchedule();

        $data = collect($data['shedules']['tournament'])->map(function($item){
            foreach ($item['week'] as $week){
                
                foreach($week['matches'] as $match){
                    
                    foreach ($match['match'] as $b){
                        $dateTimeUtc = Carbon::parse($b['datetime_utc'], 'UTC')->setTimeZone('Australia/Sydney')->format('Y-m-d');

                        $dateToday = Carbon::parse(date('Y-m-d'));

                        if ($dateToday->lte($dateTimeUtc)) {
                            return $week;
                            break;
                        }
                    }
                }
            }
        })->filter();

        if ($data->count() == 0) {
            return [];
        }

        $contestIds = collect($data->first())['matches'];
        $contestIds = collect($contestIds)->flatMap(function($item){
            return collect($item['match'])->map(function($a){
                return $a['contestID'];
            });
        });

        return $contestIds;
    }

    public function getCurrentScheduledGames()
    {
        $contestIds = $this->findTheCurrentWeek();
        $data = NFlGame::whereIn('contest_id', $contestIds)->get();

        $filtered = $data->map(function($match){

            $date = Carbon::parse($match->datetime_utc, 'UTC')->setTimeZone('Australia/Sydney')->format('Y-m-d');
            $match['actual_date'] = $date;

            return $match;
        });


        if ($filtered->count() > 0) return $filtered;

        return []; 
    }

    public function hasMatchToday()
    {
        $dataToday = $this->getCurrentScheduledGames();

        $matchesStatus = $dataToday->map(function($match){
            $gameDate = Carbon::parse($match->datetime_utc, "UTC")->setTimeZone('Australia/Sydney');
            $hasTodayMatch = $gameDate->diffInSeconds(now()) > 180;

            return [
                'todayMatch' => $hasTodayMatch,
                'gameIsOver' => $match['status'] == 'Final'
            ];
        })->filter();

        $hasMatchToday = $matchesStatus->contains(function ($item) {
            return $item['todayMatch'] == true && $item['gameIsOver'] == false;
        });


        return $hasMatchToday;
    }

    public function getCurrentWeek()
    {
        return  $this->getCurrentScheduledGames()->first()->week;
    }

    public function getPlayByPlayScores($contestId)
    {
        $playByPlay = NflGamePlaybyplayScores::where('contest_id', $contestId)->first();

        if (empty($playByPlay)) {
            $this->fetchApiPlayByPlay();
            // retrieve
            $playByPlay = NflGamePlaybyplayScores::where('contest_id', $contestId)->first();
        }

        return $playByPlay;
    }
    
}

