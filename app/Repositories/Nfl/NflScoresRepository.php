<?php

namespace App\Repositories\Nfl;

use App\Dto\NflScoreData;
use App\Dto\NflStandingsDto;
use App\Models\NflApiResponse;
use Illuminate\Support\Facades\Cache;
use App\Models\NflGame;
use App\Repositories\Interfaces\NflScoresRepositoryInterface;
use App\Services\Nfl\NflApiService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class NflScoresRepository
{
    protected $cacheKey = 'nfl_scores_season_';
    protected $cacheKeyStanding = 'nfl-standings_season_';

    public function __construct(
        private NflApiService $apiService,
        private NflGame $model
    ) {}


    private function storeScores(Collection $scores): void
    {
        foreach ($scores as $score) {
            $this->model->updateOrCreate(
                ['game_id' => $score['id']],
                [
                    'home_team' => $score['home_team'],
                    'away_team' => $score['away_team'],
                    'home_score' => $score['home_score'] ?? 0,
                    'away_score' => $score['away_score'] ?? 0,
                    'status' => $score['status'],
                    'game_date' => Carbon::parse($score['date']),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function getScoresFromDatabase(?string $week): Collection
    {
        $query = $this->model->query();

        if ($week) {
            $query->where('week', $week);
        }

        return $query->orderBy('game_date')->get();
    }

    public function getTeamsInfo($season) :Collection
    {
        $teams =  $this->getTournament($season)->map(function($item){
            return collect($item['week'])->flatMap(function($a){

                return collect($a['matches'])->flatMap(function($b){
                    return collect($b['match'])->flatMap(function($c){
                        return [
                            [
                                'id' => $c['awayteam']['id'],
                                'name' => $c['awayteam']['name'],
                                'image_name' => str_replace(' ', '_', $c['awayteam']['name']),
                            ],
                            [
                                'id' => $c['hometeam']['id'],
                                'name' => $c['hometeam']['name'],
                                'image_name' => str_replace(' ', '_', $c['hometeam']['name']),
                            ],
                        ];
                    });
                });

            });
        })->first();

        if (empty($teams)) return collect([]);

        return $teams->unique('id');
    }

    public function getTeamInfo(string $teamId) : Collection
    {
        $tournament = $this->getTournament();
        $data = Cache::get($this->cacheKey . date('Y'));

        if (empty($data)) return collect([]);

    }

    public function getTeamStandings(string $season, string $teamId) :array
    {
        $data = Cache::get($this->cacheKeyStanding . $season);

        return (new NflStandingsDto($data))->getTeamStandings($season, $teamId);
    }

    public function getScores($date)
    {
        $schedules = NflApiResponse::getFirstByField('date_fetched', date('Y-m-d'));

        $data = [];

        if ($schedules) {
            $data['data'] = json_decode($schedules->response,true)['shedules']['tournament'];
        }

        return $data;

    }

    public function getSchedules()
    {
        $schedules = NflApiResponse::getFirstByField('date_fetched', date('Y-m-d'));

        $data = [];

        if ($schedules) {
            $data['data'] = json_decode($schedules->response,true)['shedules']['tournament'];
        }

        return $data;
    }

    public function getTournament($season = null)
    {
        $schedules = NflApiResponse::where(
            [
                'date_fetched' => date('Y-m-d'),
                'season' => $season ?? date('Y')
            ]
        )->first();


        if(empty($schedules)) return [];

        return collect(json_decode($schedules->response, true)['shedules']['tournament']);
    }

    public function getSeasonTypes()
    {

        $tournament = $this->getTournament();
        return $tournament->map(fn($item) => [
            'id' => $item['id'],
            'name' => $item['name'],
        ]);
    }

    public function getWeeks($seasonTypeId)
    {
        $weeks = $this->getTournament()->where('id', $seasonTypeId)->map(function($item){
            return collect($item['week'])->map(fn($i, $j) => $j+1);
        })->first();

        return $weeks;
    }

    public function getWeeksInfo($seasonTypeId)
    {
        $weeks = $this->getTournament()->where('id', $seasonTypeId)->map(function($item){
            return collect($item['week'])->map(fn($i, $j) => ['week' => $j+1,
                'week_name' => $i['name'],
                'week_initials' =>  implode('', array_map(function($word) {
                    return strtoupper($word[0]);
                }, explode(' ', $i['name'])))
            ]);
        })->first();

        return $weeks;
    }

    public function getTeamSchedule($teamId, $season, $seasonType)
    {
        if (empty($teamId) || empty($season) || empty($seasonType)) {

            return [];
        }

        $schedules = NflGame::getTeamSchedule($season, $seasonType);

        $weeks = $this->getWeeks($seasonType)->mapWithKeys(function($week) use($schedules, $teamId){

            $match = $schedules->where('week', $week);

            // find the team
            $match = $match->map(function($item) use($teamId){
                $homeTeam = json_decode($item->hometeam, true);
                $awayTeam = json_decode($item->awayteam,true);
                $item['home_image_name'] = str_replace(' ', '_', $homeTeam['name']);
                $item['away_image_name'] = str_replace(' ', '_', $awayTeam['name']);

                $isHome = false;

                if ($homeTeam['id'] == $teamId ){
                    $item['isHome'] = true;
                    $item['home_result'] = $homeTeam['totalscore'] > $awayTeam['totalscore'] ? 'W' : 'L';
                    return $item;

                } else if ($awayTeam['id'] == $teamId) {
                    $item['isHome'] = false;
                    $item['away_result'] = $awayTeam['totalscore'] > $homeTeam['totalscore'] ? 'W' : 'L';
                    return $item;
                }

            })->filter()->first();


            if ($match == null) {
                return [
                    $week => [
                        'match_status' => 'BYE'
                    ]
                ];
            }

            return [
                $week => [
                    ...$match->toArray(),
                    'fumbles' => json_decode($match->fumbles, true),
                    'punt_returns' => json_decode($match->punt_returns, true),
                    'punting' => json_decode($match->punting, true),
                    'awayteam' => json_decode($match->awayteam, true),
                    'hometeam' => json_decode($match->hometeam, true),
                    'defensive' => json_decode($match->defensive, true),
                    'events' => json_decode($match->events, true),
                    'team_stats' => json_decode($match->team_stats, true),
                    'interceptions' => json_decode($match->interceptions, true),
                    'kick_returns' => json_decode($match->kick_returns, true),
                    'kicking' => json_decode($match->kicking, true),
                    'passing' => json_decode($match->passing, true),
                    'receiving' => json_decode($match->receiving, true),
                    'rushing' => json_decode($match->rushing, true),
                ]
            ];

        });

        return $weeks;
    }
}

