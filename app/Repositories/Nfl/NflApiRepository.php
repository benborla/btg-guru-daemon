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
    const API_NFL_ROOSTERS_URL = "https://www.goalserve.com/getfeed/9645f122eef946c1c7bd08dd5ac0e712/football/%s_rosters?json=1";

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

    public function fetchApiRosters($teamId)
    {
        $cacheKey = "nfl_api_rosters_" . $teamId;
        $emptyResponseCacheKey = "nfl_api_rosters_empty_" . $teamId;

        if (Cache::has($emptyResponseCacheKey)) {
            return [];
        }

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $response = Http::get(str_replace('%s', $teamId, self::API_NFL_ROOSTERS_URL));
        $defaultCache = now()->addYears(1);

        if ($response->json() == null) {
            Cache::put($emptyResponseCacheKey, 'team_' . $teamId . '_is_empty', $defaultCache);
            return [];
        }

        $players = $response->json()['team']['position'];
        Cache::put($cacheKey, $players, $defaultCache);

        return $players;
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
                'contestId' =>  $hasTodayMatch ? $match->contest_id : null,
                'gameIsOver' => $match['status'] == 'Final' || $match['status'] == 'After Over Time'
            ];
        })->filter();

        $hasMatchToday = $matchesStatus->contains(function ($item) {
            return $item['todayMatch'] == true && $item['gameIsOver'] == false;
        });


        return [
            'status' => $hasMatchToday, 
            'contestIds' => $matchesStatus->pluck('contestId')->filter()->toArray()
        ];
    }

    public function getCurrentWeek()
    {
        return  $this->getCurrentScheduledGames()->first()->week;
    }

    public function getPlayByPlayScores($contestId)
    {
        $cacheKey = "nfl_api_playbyplay_live";
        $playByPlay = [];

        if (Cache::has($cacheKey)) {
            $playByPlay =  Cache::get($cacheKey);
        }

        // for testing
        // $testData = NflGamePlaybyplayScores::where('contest_id', '204639')->first();
        // $testData = $testData->response;

        if (!empty($playByPlay)) {
            $data = collect($playByPlay)->where('contestID', $contestId)->first();
            // $data = $testData;
            $playByPlay = $data['playbyplay'] ?? [];

            if (empty($playByPlay)) {
                return [];
            }

            $drives = $playByPlay['drive'] ?? [];

            if (empty($drives)) {
                return [];
            }

	    if (isset($drives['id'])) {
	    	$drives = [$drives];
	    }

            $drives = collect($drives)->first();
            $drives['play'] = collect($drives['play'])->reverse()->first();
            // $drives['play']['description'] = "Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries,";
            $drives['image_name'] = str_replace(' ', '_', $drives['name']) ?? "";
            
            $drives['current_drive'] = $drives['team'] == 'hometeam' ? 'home' : 'away';
            // $drives['current_drive'] = "home";
            // $drives['play']['description'] = "Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries,";
            // $drives['image_name'] = 'Cleveland_Browns';

            $toHomeFirstHalf = 0;
            $toHomeSecondHalf = 0;
            $toAwayFirstHalf = 0;
            $toAwaySecondHalf = 0;
            $firstHalfQuarters = [
                "1st", "2nd"
            ];
            $secondHalfQuarters = [
                "3rd", "4th"
            ];

            foreach ($playByPlay['drive'] as $drive){
                $plays = $drive['play'] ?? [];

                foreach ($plays as $play){

			$possessionTeam = $play['possession_team'] ?? $play['possessionTeam'];

			if ($play['type'] == 'TO' && $possessionTeam == 'hometeam'){

                        $quarter = explode("-", $play['minute'])[1] ?? null;

                        if ($quarter) {
                            $quarter = str_replace(" ", "", $quarter);

                            if (in_array($quarter, $firstHalfQuarters)) {
                                $toHomeFirstHalf++;
                            }

                            if (in_array($quarter, $secondHalfQuarters)) {
                                $toHomeSecondHalf++;
                            }
                        }
                    }
                
                    if ($play['type'] == 'TO' && $possessionTeam =='awayTeam'){
                        $quarter = explode("-", $play['minute'])[1] ?? null;

                        if ($quarter) {
                            $quarter = str_replace(" ", "", $quarter);

                            if (in_array($quarter, $firstHalfQuarters)) {
                                $toAwayFirstHalf++;
                            }

                            if (in_array($quarter, $secondHalfQuarters)) {
                                $toAwaySecondHalf++;
                            }
                        }
                    }
                }
            }

            $to = [
                'home' => [
                    'firstHalf' => $toHomeFirstHalf,
                    'secondHalf' => $toHomeSecondHalf
                ],
                'away' => [
                    'firstHalf' => $toAwayFirstHalf,
                    'secondHalf' => $toAwaySecondHalf
                ]
            ];

            $drives['to'] = $to;

            $playByPlay = $drives;
        }

        return $playByPlay;
    }

    
}

