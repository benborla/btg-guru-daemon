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
use Carbon\Carbon;
use App\Repositories\Nfl\NflApiRepository;

class NflScoresRepository
{
    protected $cacheKey = 'nfl_scores_season_';
    protected $cacheKeyStanding = 'nfl-standings_season_';

    public function __construct(
        private NflApiService $apiService,
        private NflGame $model,
        public NflApiRepository $apiRepository      
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

    private function getTeamTypes()
    {
        return [
            'AFC' => [
                'division' => [
                    'east' => [
                        'teams' => [
                            '1692',
                            '1689',
                            '1681',
                            '1709',
                        ],
                        'name' => 'AFC East Division'
                    ],
                    'west' => [
                        'teams' => [
                            '1708',
                            '1691',
                            '5566',
                            '1702'
                        ],
                        'name' => 'AFC West Division'
                    ],
                    'north' => [
                        'teams' => [
                            '1679',
                            '1699',
                            '1694',
                            '1683'
                        ],
                        'name' => 'AFC North Division'
                    ],
                    'south' => [
                        'teams' => [
                            '1705',
                            '1706',
                            '1687',
                            '1697'
                        ],
                        'name' => 'AFC South Division'
                    ]
                ],
            ],
            'NFC' => [
                'division' => [
                    'east' => [
                        'teams' => [
                            '1680',
                            '1710',
                            '1686',
                            '5753'
                        ],
                        'name' => 'NFC East Division'
                    ],
                    'west' => [
                        'teams' => [
                            '5117',
                            '1696',
                            '1707',
                            '1704'
                        ],
                        'name' => 'NFC West Division'
                    ],
                    'north' => [
                        'teams' => [
                            '1703',
                            '1695',
                            '1698',
                            '1701'
                        ],
                        'name' => 'NFC North Division'
                    ],
                    'south' => [
                        'teams' => [
                            '1690',
                            '1682',
                            '1693',
                            '1684'
                        ],
                        'name' => 'NFC South Division'
                    ]
                ],
            ]

        ];
    }

    private function getTeamTypesFlat()
    {
        return collect($this->getTeamTypes())->mapWithKeys(function($item, $key){

            return [
                $key => collect($item['division'])->flatMap(fn($a) => $a['teams'])
            ];
        });
    }

    private function isAfc($teamId)
    {
        return in_array($teamId, $this->getTeamTypesFlat()['AFC']->toArray());
    }

    private function isNfc($teamId)
    {
        return in_array($teamId, $this->getTeamTypesFlat()['NFC']->toArray());
    }

    public function getTeamsInfo($season) :Collection
    {
        $tournament = $this->getTournament($season);

        if (empty($tournament)) return collect([]);

        $teams =  $tournament->map(function($item){
            return collect($item['week'])->flatMap(function($a){

                return collect($a['matches'])->flatMap(function($b){
                    if (isset($b['match'])) {
                        return collect($b['match'])->flatMap(function($c){

                        $isAfcHome = $this->isAfc($c['hometeam']['id']);
                        $isNfcHome = $this->isNfc($c['hometeam']['id']);

                        $isAfcAway= $this->isAfc($c['awayteam']['id']);
                        $isNfcAway = $this->isNfc($c['awayteam']['id']);

                        return [
                            [
                                'id' => $c['awayteam']['id'],
                                'name' => $c['awayteam']['name'],
                                'image_name' => str_replace(' ', '_', $c['awayteam']['name']),
                                'team_division' => $isAfcAway ? 'AFC' : ($isNfcAway  ? 'NFC' : 'no_team')
                            ],
                            [
                                'id' => $c['hometeam']['id'],
                                'name' => $c['hometeam']['name'],
                                'image_name' => str_replace(' ', '_', $c['hometeam']['name']),
                                'team_division' => $isAfcHome ? 'AFC' : ($isNfcHome  ? 'NFC' : 'no_team')
                            ],
                        ];
                    });
                }
                });

            });
        })->first();

        if (empty($teams)) return collect([]);

        $teams = $teams->unique('id');

        return collect([
            'AFC' => $teams->where('team_division', 'AFC')->sortBy('name'),
            'NFC' => $teams->where('team_division', 'NFC')->sortBy('name')
        ]);
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

    public function getScores($season, $week)
    {
        $schedules = NflApiResponse::getFirstByField('date_fetched', date('Y-m-d'));

        $data = [];

        if ($schedules) {
            $data['data'] = json_decode($schedules->response,true)['shedules']['tournament'];
        }

        return $data;

    }

    public function getSchedules($season, $seasonTypeId, $week)
    {
        $schedules = NflApiResponse::getFirstByField('date_fetched', date('Y-m-d'));

        $data = [];

        if ($schedules) {
            $data['data'] = json_decode($schedules->response,true)['shedules']['tournament'];
        }

        $currentWeekSchedule = $this->apiRepository->getCurrentScheduledGames()->first();
        $currentWeek = $currentWeekSchedule->week;
        $weekInfo = $this->getWeeksInfo($currentWeekSchedule->season_type_id);
        $weekInfo = $weekInfo->where('week', $currentWeek)->first();

        $allWeeksInfo = $this->getSeasonTypes()->flatMap(function($a) {
            $allWeeks = $this->getWeeksInfo($a['id']);
            $allWeeks = $allWeeks->map(function($b) use($a) {
                $b['season_type_id'] = $a['id'];
                $b['season_type_name'] = $a['name'];
                $isPreSeason = $a['id'] == 1;
                $isPostSesason = $a['id'] == 3;

                $bgColor = $isPreSeason ? '#ba893f' : ($isPostSesason ? '#0d6efd' : '#cfcfcf');
                $color = $isPreSeason ? '#444' : ($isPostSesason ? '#fff' : '#000');

                $b['week_alias'] = $isPreSeason ? 'P' . ((int) $b['week'] - 1 ) : ($isPostSesason ? $b['week_initials'] : $b['week']);
                $b['bg_color'] = $bgColor;
                $b['color'] = $color;

                return $b;
            });

            return $allWeeks;
        });

        $weekGames = NflGame::where([
            'season' => $season,
            'season_type_id' => $seasonTypeId,
            'week' => $week
        ])->get();

        $withoutHOFW = $allWeeksInfo->filter(fn($a) => $a['week_initials'] != 'HOFW');
        $weekInfo['season_type_id'] = $seasonTypeId;

        return [
            'current_week' => $weekInfo,
            'all_weeks' => $withoutHOFW ,
            'hasMatchToday' => $this->apiRepository->hasMatchToday(),
            'data' => $weekGames
        ];
    }


    public function getTournament($season = null)
    {
        $schedules = NflApiResponse::where(
            [
                'date_fetched' => date('Y-m-d'),
                'season' => $season ?? date('Y')
            ]
        )->first();


        if(empty($schedules)) {

            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $schedules = NflApiResponse::where(
                [
                    'date_fetched' => $yesterday,
                    'season' => $season ?? date('Y')
                ]
            )->first();
        }

        return collect(json_decode($schedules->response, true)['shedules']['tournament']);
    }

    public function getSeasonTypes()
    {
        $tournament = $this->getTournament();

        if (empty($tournament)) return [];

        return $tournament->map(fn($item) => [
            'id' => $item['id'],
            'name' => $item['name'],
        ]);
    }

    public function getWeeks($seasonTypeId)
    {
        $weeks = $this->getTournament()->where('id', $seasonTypeId)->map(function($item) use($seasonTypeId){
            if ($seasonTypeId == 1) {
                return collect($item['week'])->map(function($i, $j) use($seasonTypeId) {
                    if ($i['name'] != 'Hall of Fame Weekend') {
                        return $j+1;
                    }
                });
            }

            return collect($item['week'])->map(fn($i, $j) => $j+1);
        })->first()->filter();

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

        $weeks = $this->getWeeks($seasonType)->mapWithKeys(function($week) use($schedules, $teamId, $seasonType){

            $match = $schedules->where('week', $week);
            $teamAVG = collect($this->computeTeamAvg($schedules, $teamId,$seasonType));

            // find the team
            $match = $match->map(function($item, $i) use($teamId, $match, $teamAVG, $seasonType){

                $homeTeam = $item->hometeam;
                $awayTeam = $item->awayteam;
                $item['home_image_name'] = str_replace(' ', '_', $homeTeam['name']);
                $item['away_image_name'] = str_replace(' ', '_', $awayTeam['name']);
                $item['home_team_id'] = $homeTeam['id'];
                $item['away_team_id'] = $awayTeam['id'];
                $item['home_result_score'] = (int) $homeTeam['totalscore'] ;
                $item['away_result_score'] = (int) $awayTeam['totalscore'] ;
                $item['total'] = (int) $homeTeam['totalscore'] + (int) $awayTeam['totalscore'];

                $isHome = false;

                $homeTotalScore = (int)$homeTeam['totalscore'];
                $awayTotalScore = (int) $awayTeam['totalscore'];
                $homeResult = '-';
                $awayResult = '-';

                if ($homeTotalScore > 0 && $awayTotalScore > 0) {
                    $homeResult = $homeTeam['totalscore'] > $awayTeam['totalscore'] ? 'W' : 'L';;
                    $awayResult = $awayTeam['totalscore'] > $homeTeam['totalscore'] ? 'W' : 'L';
                }

                if ($homeTeam['id'] == $teamId ){


                    $item['isHome'] = true;
                    $item['court'] = 'HM';
                    $q1 = (int) $homeTeam['q1'];
                    $q2 = (int) $homeTeam['q2'];
                    $q3 = (int) $homeTeam['q3'];
                    $q4 = (int) $homeTeam['q4'];
                    $item['home_q1'] = $q1 ?? 0;
                    $item['home_q2'] = $q2 ?? 0;
                    $item['home_q3'] = $q3 ?? 0;
                    $item['home_q4'] = $q4 ?? 0;
                    $item['home_1h'] = $q1 + $q2;
                    $item['home_2h'] = $q3 + $q4;
                    $item['home_to'] = $q1 + $q2 + $q3 + $q4;
                    $item['home_result'] =  $homeResult;
                    $item['home_result_class'] = strtolower($homeResult);
                    $item['home_avg_for'] = 0;
                    $item['home_avg_agt'] = 0;
                    $item['home_avg_to'] = 0;

                    return $item;

                } else if ($awayTeam['id'] == $teamId) {
                    $item['isHome'] = false;
                    $q1 = (int) $awayTeam['q1'];
                    $q2 = (int) $awayTeam['q2'];
                    $q3 = (int) $awayTeam['q3'];
                    $q4 = (int) $awayTeam['q4'];
                    $item['away_q1'] = $q1 ?? 0;
                    $item['away_q2'] = $q2 ?? 0;
                    $item['away_q3'] = $q3 ?? 0;
                    $item['away_q4'] = $q4 ?? 0;
                    $item['away_1h'] = $q1 + $q2;
                    $item['away_2h'] = $q3 + $q4;
                    $item['away_to'] = $q1 + $q2 + $q3 + $q4;
                    $item['court'] = 'AW';
                    $item['away_result'] = $awayResult;
                    $item['away_result_class'] = strtolower($awayResult);
                    $item['away_avg_for'] = 0;
                    $item['away_avg_agt'] = 0;
                    $item['away_avg_to'] = 0;

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
                ]
            ];
        });

        $withAvgs = $weeks->values()->map(function($item,$i) use($weeks, $teamId) {

            if (isset($item['match_status'])) {
                return $item;
            }



            if ($item['season_type_id'] == 1)
            {
                $item['avg_for'] = '-';
                $item['avg_agt'] = '-';
                $item['avg_to'] = '-';
            } else {

                if ($i == 0) {
                    $item['avg_for'] = $item['home_result_score'];
                    $item['avg_agt'] = $item['away_result_score'];
                    $item['avg_to'] =  $item['avg_for'] + $item['avg_agt'];
                } else {

                    $teamPoints = $weeks->take($i+1);
                    $avgFor =number_format(round($teamPoints->avg('home_result_score')),0);
                    $avgAgt =number_format(round($teamPoints->avg('away_result_score')),0);
                    $item['avg_for'] = $avgFor;
                    $item['avg_agt'] = $avgAgt;
                    $item['avg_to'] =  $avgFor + $avgAgt;
                }

            }

            return $item;
        });

        return $withAvgs;
    }

    private function computeTeamAvg($schedules, $teamId, $seasonTypeId)
    {
        $i = $seasonTypeId == 1 ? 2 : 1;
        $filtered = $schedules;

        if ($seasonTypeId == 1) {
            $filtered = $schedules->filter(fn($a) => $a['week'] != 1);
        }

        return $filtered->map(function($a) use($teamId){
            $homeTeam = $a->hometeam;
            $awayTeam = $a->awayteam;

            $a['home_for'] = $homeTeam['totalscore'];
            $a['away_for'] = $awayTeam['totalscore'];

            return $a;

        });

        /* $nextMatch = $match->take($iterator + $i)->map(function($item) use($teamId){ */
        /*     $homeTeam = json_decode($item->hometeam, true); */
        /*     $awayTeam = json_decode($item->awayteam,true); */
        /*     $item['avg_for'] = (int) $homeTeam['totalscore']; */
        /*     $item['avg_agt'] = (int) $awayTeam['totalscore']; */
        /*     $item['home_team_id'] = $homeTeam['id']; */
        /*     $item['away_team_id'] = $awayTeam['id']; */
        /**/
        /*     if ($homeTeam['id'] == $teamId) { */
        /*         $item['home'] = true; */
        /*         return $item; */
        /*     } */
        /**/
        /*     if ($awayTeam['id'] == $teamId) { */
        /*         $item['away'] = true; */
        /*         return $item; */
        /*     } */
        /**/
        /* }); */

        /* if ($nextMatch != null) { */
        /*     dd($nextMatch); */
        /*     return $nextMatch->filter(); */
        /* } */

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

    private function formatNflScores($data)
    {
        return $data->map(function($match) {
            $match['awayteam'] = $this->parseNflTeam($match->awayteam);
            $match['hometeam'] = $this->parseNflTeam($match->hometeam);
            return $match;
        });
    }

    public function NflTeamAbbrieviation($teamId)
    {
        $teams = '[
            {
              "ID": 1696,
              "Name": "Arizona Cardinals",
              "Abbreviation": "ARI",
              "Conference": "NFC",
              "Division": "West"
            },
            {
              "ID": 1690,
              "Name": "Atlanta Falcons",
              "Abbreviation": "ATL",
              "Conference": "NFC",
              "Division": "South"
            },
            {
              "ID": 1683,
              "Name": "Baltimore Ravens",
              "Abbreviation": "BAL",
              "Conference": "AFC",
              "Division": "North"
            },
            {
              "ID": 1689,
              "Name": "Buffalo Bills",
              "Abbreviation": "BUF",
              "Conference": "AFC",
              "Division": "East"
            },
            {
              "ID": 1684,
              "Name": "Carolina Panthers",
              "Abbreviation": "CAR",
              "Conference": "NFC",
              "Division": "South"
            },
            {
              "ID": 1703,
              "Name": "Chicago Bears",
              "Abbreviation": "CHI",
              "Conference": "NFC",
              "Division": "North"
            },
            {
              "ID": 1679,
              "Name": "Cincinnati Bengals",
              "Abbreviation": "CIN",
              "Conference": "AFC",
              "Division": "North"
            },
            {
              "ID": 1699,
              "Name": "Cleveland Browns",
              "Abbreviation": "CLE",
              "Conference": "AFC",
              "Division": "North"
            },
            {
              "ID": 1680,
              "Name": "Dallas Cowboys",
              "Abbreviation": "DAL",
              "Conference": "NFC",
              "Division": "East"
            },
            {
              "ID": 1708,
              "Name": "Denver Broncos",
              "Abbreviation": "DEN",
              "Conference": "AFC",
              "Division": "West"
            },
            {
              "ID": 1695,
              "Name": "Detroit Lions",
              "Abbreviation": "DET",
              "Conference": "NFC",
              "Division": "North"
            },
            {
              "ID": 1698,
              "Name": "Green Bay Packers",
              "Abbreviation": "GB",
              "Conference": "NFC",
              "Division": "North"
            },
            {
              "ID": 1697,
              "Name": "Houston Texans",
              "Abbreviation": "HOU",
              "Conference": "AFC",
              "Division": "South"
            },
            {
              "ID": 1706,
              "Name": "Indianapolis Colts",
              "Abbreviation": "IND",
              "Conference": "AFC",
              "Division": "South"
            },
            {
              "ID": 1687,
              "Name": "Jacksonville Jaguars",
              "Abbreviation": "JAX",
              "Conference": "AFC",
              "Division": "South"
            },
            {
              "ID": 1691,
              "Name": "Kansas City Chiefs",
              "Abbreviation": "KC",
              "Conference": "AFC",
              "Division": "West"
            },
            {
              "ID": 1692,
              "Name": "Miami Dolphins",
              "Abbreviation": "MIA",
              "Conference": "AFC",
              "Division": "East"
            },
            {
              "ID": 1701,
              "Name": "Minnesota Vikings",
              "Abbreviation": "MIN",
              "Conference": "NFC",
              "Division": "North"
            },
            {
              "ID": 1681,
              "Name": "New England Patriots",
              "Abbreviation": "NE",
              "Conference": "AFC",
              "Division": "East"
            },
            {
              "ID": 1682,
              "Name": "New Orleans Saints",
              "Abbreviation": "NO",
              "Conference": "NFC",
              "Division": "South"
            },
            {
              "ID": 1710,
              "Name": "New York Giants",
              "Abbreviation": "NYG",
              "Conference": "NFC",
              "Division": "East"
            },
            {
              "ID": 1709,
              "Name": "New York Jets",
              "Abbreviation": "NYJ",
              "Conference": "AFC",
              "Division": "East"
            },
            {
              "ID": 5566,
              "Name": "Las Vegas Raiders",
              "Abbreviation": "LV",
              "Conference": "AFC",
              "Division": "West"
            },
            {
              "ID": 1686,
              "Name": "Philadelphia Eagles",
              "Abbreviation": "PHI",
              "Conference": "NFC",
              "Division": "East"
            },
            {
              "ID": 1694,
              "Name": "Pittsburgh Steelers",
              "Abbreviation": "PIT",
              "Conference": "AFC",
              "Division": "North"
            },
            {
              "ID": 1702,
              "Name": "Los Angeles Chargers",
              "Abbreviation": "LAC",
              "Conference": "AFC",
              "Division": "West"
            },
            {
              "ID": 1707,
              "Name": "San Francisco 49ers",
              "Abbreviation": "SF",
              "Conference": "NFC",
              "Division": "West"
            },
            {
              "ID": 1704,
              "Name": "Seattle Seahawks",
              "Abbreviation": "SEA",
              "Conference": "NFC",
              "Division": "West"
            },
            {
              "ID": 5117,
              "Name": "Los Angeles Rams",
              "Abbreviation": "LAR",
              "Conference": "NFC",
              "Division": "West"
            },
            {
              "ID": 1693,
              "Name": "Tampa Bay Buccaneers",
              "Abbreviation": "TB",
              "Conference": "NFC",
              "Division": "South"
            },
            {
              "ID": 1705,
              "Name": "Tennessee Titans",
              "Abbreviation": "TEN",
              "Conference": "AFC",
              "Division": "South"
            },
            {
              "ID": 5753,
              "Name": "Washington Commanders",
              "Abbreviation": "WAS",
              "Conference": "NFC",
              "Division": "East"
            }
        ]'; 

        $teams = collect(json_decode($teams, true));
        
        return $teams->where('ID', $teamId)->first();
    }
    
    public function getScoreBoardDataFromDb()
    {
        $games=  $this->apiRepository->getCurrentScheduledGames();

        return $games->map(function($game) {

            $game['awayteam'] = $this->parseNflTeam($game->awayteam);
            $game['hometeam'] = $this->parseNflTeam($game->hometeam);
            $game['game_date'] = Carbon::parse($game['datetime_utc'], 'UTC')->setTimeZone('Australia/Sydney')->format('M j g:ia');
            $game['current_game'] = false;

            return $game;
        });
    }

    public function getScoreBoardDataFromApi()
    {
        $games = $this->apiRepository->getScoreBoardDataFromApi();

        return $games->map(function($game) {

            $game['awayteam'] = $this->parseNflTeam($game['awayteam']);
            $game['hometeam'] = $this->parseNflTeam($game['hometeam']);
            $game['game_date'] = Carbon::parse($game['datetime_utc'], 'UTC')->setTimeZone('Australia/Sydney')->format('M j g:ia');
            $game['current_game'] = Carbon::parse($game['datetime_utc'], 'UTC')->setTimeZone('Australia/Sydney')->isToday();
            // $game['current_game'] = $game['contestID'] == '204610';

            $this->storeGame($game);

            return $game;
        });
    }

    private function storeGame($game)
    {
        $this->model->updateOrCreate(
            ['contest_id' => $game['contestID']],
            $game
        );
    }

    public function hasMatchToday()
    {
        return $this->apiRepository->hasMatchToday();
    }

    private function parseNflTeam($team)
    {
        if (empty($team))
            return [];

        $formatted = !is_array($team) ? json_decode($team, true) : $team;

        return [
            ...$formatted,
            'q1' => (int) $formatted['q1'] ?? 0,
            'q2' => (int) $formatted['q2'] ?? 0,
            'q3' => (int) $formatted['q3'] ?? 0,
            'q4' => (int) $formatted['q4'] ?? 0,
            'long' => $formatted['name'] ?? '',
            'score' => (int) $formatted['totalscore'] ?? 0,
            'short' => $this->NflTeamAbbrieviation($formatted['id'])['Abbreviation'] ?? '',
            'image_name' => str_replace(' ', '_', $formatted['name']),
        ];
    }

    public function getCurrentWeek()
    {
        $currentWeekSchedule = $this->apiRepository->getCurrentScheduledGames()->first();
        $weekInfo = $this->getWeeksInfo($currentWeekSchedule->season_type_id);
        $weekInfo = $weekInfo->where('week', $currentWeekSchedule->week)->first();
        $weekInfo['season_type_id'] = $currentWeekSchedule->season_type_id;

        return $weekInfo;
    }
}

