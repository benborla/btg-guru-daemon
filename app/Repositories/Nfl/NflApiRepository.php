<?php

namespace App\Repositories\Nfl;

use App\Dto\NflScoreData;
use App\Dto\NflStandingsDto;
use App\Models\NflApiResponse;
use Illuminate\Support\Facades\Cache;
use App\Models\NflGame;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class NflApiRepository
{
    const API_NFL_SCORES_URL = "https://www.goalserve.com/getfeed/9645f122eef946c1c7bd08dd5ac0e712/football/nfl-scores?json=1";
    const CACHE_SECONDS = 10;
    
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
            return Cache::get($cacheKey);
        }

        $response = Http::get(self::API_NFL_SCORES_URL);

        Cache::put($cacheKey, $response->json(), now()->addSeconds(self::CACHE_SECONDS));

        return $response->json();
    }

    public function getScoreBoardDataFromApi()
    {
        $response = $this->fetchApiScores();

        return collect($response['scores']['category']['match']);
    }

    public function getCurrentScheduledGames()
    {
        $data = NFlGame::where('season',date('Y'))->get();

        $filtered =  $data->groupBy('season_type_id')->map(function($matches) {
            $matchesWithActualDate = $matches->map(function($match){

                $date = Carbon::createFromFormat('d.m.Y', $match->formatted_date)->format('Y-m-d');
                $match['actual_date'] = $date;

                return $match;
            });


            $minDate = $matchesWithActualDate->min('actual_date');

            $checkDate = Carbon::parse(date('Y-m-d'));

            if ($checkDate->lte($minDate)) {
                return $matchesWithActualDate;
            }
        })->filter();

        if ($filtered->count() > 0) return $filtered->first();

        return []; 
    }

    public function hasMatchToday()
    {
        $dataToday = $this->getCurrentScheduledGames();

        $matchesStatus = $dataToday->map(function($match){
            $gameDate = Carbon::parse($match->date);
            $hasTodayMatch = $gameDate->isToday();

            return ['todayMatch' => $hasTodayMatch];
        })->filter();

        $hasMatchToday = $matchesStatus->contains(function ($item) {
            return $item['todayMatch'] === true;
        });

        return $hasMatchToday;
    }
    
}

