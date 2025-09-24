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
use Illuminate\Support\Number;

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
	$teamFlat = $this->getTeamTypesFlat();

        return in_array($teamId, $this->getTeamTypesFlat()['AFC']->toArray()) ?? null;
    }

    private function isNfc($teamId)
    {
        return in_array($teamId, $this->getTeamTypesFlat()['NFC']->toArray()) ?? null;
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

		        if (!isset($c['hometeam']['id']) || !isset($c['awayteam']['id'])) {return []; }

                        $isAfcHome = $this->isAfc($c['hometeam']['id'] ?? null);
                        $isNfcHome = $this->isNfc($c['hometeam']['id'] ?? null);

                        $isAfcAway= $this->isAfc($c['awayteam']['id'] ?? null);
                        $isNfcAway = $this->isNfc($c['awayteam']['id'] ?? null);

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
        $currentMatch = $weekGames->firstWhere('current_match', true);
        $weekGames = $weekGames->map(function($a) {
            $awayTeamStandings = $this->getTeamStandings($a['away_team_id']);
            $homeTeamStandings = $this->getTeamStandings($a['home_team_id']);
            $a['away_standings'] = $awayTeamStandings;
            $a['home_standings'] = $homeTeamStandings;
            return $a;
        });

        return [
            'current_week' => $weekInfo,
            'all_weeks' => $withoutHOFW ,
            'hasMatchToday' => $this->apiRepository->hasMatchToday(),
            'data' => $weekGames,
            'currentMatch' => $currentMatch
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

        if (empty($schedules)) {
            return collect($this->apiRepository->getFullSchedule()['shedules']['tournament']);
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
                $item['avg_for'] = '0';
                $item['avg_agt'] = '0';
                $item['avg_to'] = '0';
            } else {

                if ($i == 0) {
                    $item['avg_for'] = $item['home_result_score'] ?? 0;
                    $item['avg_agt'] = $item['away_result_score'] ?? 0;
                    $item['avg_to'] =  $item['avg_for'] + $item['avg_agt'];
                } else {

                    $teamPoints = $weeks->take($i+1)->filter(function($a) {
                        return !isset($a['match_status']) && $a['status'] != 'Not Started';
                    });

                    if ($teamPoints->contains('contest_id', $item['contest_id'])) {
                        // $teamPoints = $teamPoints->filter(function($a) use($teamId) {
                        //     return $a['home_team_id'] == $teamId || $a['away_team_id'] == $teamId;
                        // });

                        $avgFor = $teamPoints->avg(function($a) {
                            if ($a['isHome']) {
                                return $a['home_result_score'];
                            } else {
                                return $a['away_result_score'];
                            }
                        });
                        
                        $avgAgt = $teamPoints->avg(function($a) {
                            if (!$a['isHome']) {
                                return $a['home_result_score'];
                            } else {
                                return $a['away_result_score'];
                            }
                        });


                        $avgFor =number_format(round($avgFor),0);
                        $avgAgt =number_format(round($avgAgt),0);

                        $item['avg_for'] = $avgFor;
                        $item['avg_agt'] = $avgAgt;
                        $item['avg_to'] =  $avgFor + $avgAgt;
                    } else {
                        $item['avg_for'] = '0';
                        $item['avg_agt'] = '0';
                        $item['avg_to'] = '0';
                    }

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

    public function getScoreBoardDataFromDb($chronological = false)
    {
        $games=  $this->apiRepository->getCurrentScheduledGames();

        return $games->map(function($game) {

            if ($game['status'] == 'After Over Time') {
                $game['status'] = '(OT)';
            }
            $gameDate = Carbon::parse($game['datetime_utc'], 'UTC')->setTimeZone('Australia/Sydney')->format('M j g:ia');
            $game['awayteam'] = $this->parseNflTeam($game->awayteam);
            $game['hometeam'] = $this->parseNflTeam($game->hometeam);
            $game['game_date'] = $gameDate;
            $game['game_status'] = $game['status'] == 'Not Started' ? $gameDate : $game->status;
            $game['current_game'] = false;

            return $game;
        });
    }

    public function getScoreBoardDataFromApi($chronological = null)
    {
        $games = $this->apiRepository->getScoreBoardDataFromApi();

        $sorted =  $games->map(function($game) {

            $gameDate = Carbon::parse($game['datetime_utc'], 'UTC')->setTimeZone('Australia/Sydney')->format('M j g:ia');
            $status = $game['status'] == 'Not Started' ? $gameDate : $game['status'];
            if ($status == 'After Over Time') {
                $status = '(OT)';
            }

            $storedData = NflGame::where('contest_id', $game['contestID'])->first();
            $game['awayteam'] = $this->parseNflTeam($game['awayteam']);
            $game['hometeam'] = $this->parseNflTeam($game['hometeam']);
            $game['game_date'] = $gameDate;
            $gameHasStarted = Carbon::parse($game['datetime_utc'], 'UTC')->setTimeZone('Australia/Sydney')->diffInMinutes(now()->setTimeZone('Australia/Sydney'));
            $game['current_game'] = $gameHasStarted > 1 && ($game['status'] != 'Not Started' && $game['status'] != 'Final' && $game['status'] != 'After Over Time');
            $game['game_status'] = $status;
            $game['contest_id'] = $game['contestID'];
            $game['week'] = $storedData['week'];
            // $game['current_game'] = $game['contestID'] == '204610';

            if ($this->apiRepository->needToStore) {
                $this->storeGame($game);
            }

            return $game;
        });

        if ($chronological == 'true') {
            return $sorted->sortBy('game_date')->values();
        }

        return $sorted->values();
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

    public function getTeamStandingsFlat()
    {
        $standings = $this->apiRepository->fetchApiStandings();
        $standings = collect($standings['standings']['category']['league'])->map(function($league) {
            return $league['division'];
        });

        return $standings->collapse()->map(function($division) {
            return $division['team'];
        })->collapse();
    }

    public function getTeamStandings($teamId)
    {
        $flatStandings = $this->getTeamStandingsFlat();

        $standings = $flatStandings->where('id', $teamId);

        if ($standings->count() == 0) {
            return [];
        }

        return $standings->first();
    }

    public function getMatchCastBoxData($contestId, $week)
    {
        $data = NflGame::where([
            'contest_id' => $contestId,
            'week' => $week
        ])->first();

        if (empty($data)) {
            return [];
        }

        return [
            'playbyplay' => $this->apiRepository->getPlayByPlayScores($contestId, $data),
            'data' => $data,
            'home_standings' => $this->getTeamStandings($data->home_team_id),
            'away_standings' => $this->getTeamStandings($data->away_team_id)
        ];
    }

    public function getTeamRoosters($teamId)
    {
        $roosters = $this->apiRepository->fetchApiRosters($teamId);

        $roosters = collect($roosters)->flatMap(function($rooster) {
            $players = isset($rooster['player']['name']) ? [$rooster['player']] : $rooster['player'];
            return collect($players)->map(function($player) use($rooster) {
                return [
                    ...$player,
                    'type' => $rooster['name'],
                ];
            });
        });
        return $roosters;
    }

    public function getTeamStandingsDivision($teamId)
    {
        $standings = $this->apiRepository->fetchApiStandings();

        if (empty($standings) || empty($standings['standings']['category']['league'])) {
            return [];
        }
        $standings = collect($standings['standings']['category']['league'])->map(function($league) use($teamId) {

            $division = collect($league['division'])->map(function($division) use($teamId) {
                $hasTeam = collect($division['team'] ?? [])->contains('id', $teamId);

                if ($hasTeam) {
                    return $division;
                }

                return null;
            })->filter()->first();

            return $division;

        })->filter()->first();


        if (empty($standings)) {
            return [];
        }

        // image team name
        $standings['team'] = collect($standings['team'])->map(function($team) use($teamId) {
            return [
                ...$team,
                'image_name' => str_replace(' ', '_', $team['name']),
                'isCurrentTeam' => $team['id'] == $teamId,
            ];
        });

        return $standings;
    }

    public function getRankings($season)
    {
        $allTeams = $this->getTeamsInfo($season);

        if (empty($allTeams)) {
            return [];
        }

        $allTeams = $allTeams->flatMap(function($team) {
            return $team;
        });


        $scores = NflGame::where('season', $season)
            ->where('season_type_id', '!=', 1)
            ->get();

        $teamScores = $allTeams->map(function($team) use($scores) {
            $teamHome = $scores->where('home_team_id', $team['id'])->map(function($score) {
                $score['isHome'] = true;
                return $score;
            });
            $teamAway = $scores->where('away_team_id', $team['id'])->map(function($score) {
                $score['isHome'] = false;
                return $score;
            });

            $teamHome = $teamHome->filter(function($score) {
                return $score->status == 'Final' || $score->status == 'After Over Time';
            });

            $teamAway = $teamAway->filter(function($score) {
                return $score->status == 'Final' || $score->status == 'After Over Time';
            });

            $data = $teamHome->merge($teamAway);
            $teamAvgFor = $this->teamAvgStats($data->take(-5));
            $allMatchesDataComputation = $this->teamAvgStats($data);

            return [
                ...$team,
                'avg_for' => $teamAvgFor['for'],
                'avg_agt' => $teamAvgFor['agt'],
                'avg_passing' => $allMatchesDataComputation['passing'],
                'avg_rushings' => $allMatchesDataComputation['rushings'],
                'penalties' => $allMatchesDataComputation['penalties'],
                'penalties_yrds' => $allMatchesDataComputation['penalties_yrds'],
                'redzone_percent' => $allMatchesDataComputation['redzone_percent'],
                'redzone_cttd' => $allMatchesDataComputation['redzone_cttd'],
                'redzone_attempts' => $allMatchesDataComputation['redzone_attempts'],
                'td_rec_total' => $allMatchesDataComputation['td_rec_total'],
                'td_rushings_total' => $allMatchesDataComputation['td_rushings_total'],
                'td_total' => $allMatchesDataComputation['td_total'],
                'td_avg_total' => $allMatchesDataComputation['td_avg_total'],
                'def_passing_allowed_agt' => $allMatchesDataComputation['def_passing_allowed_agt'],
                'def_rushing_allowed_agt' => $allMatchesDataComputation['def_rushing_allowed_agt'],
                'sacks_agt' => $allMatchesDataComputation['sacks_agt'],
                'passing_agt' => $allMatchesDataComputation['passing_agt'],
                'rushings_agt' => $allMatchesDataComputation['rushings_agt'],
            ];
        });


        $withRankings = $teamScores->map(function($team) use($teamScores) {
            $id = $team['id'];

            $avgRankFor = $teamScores->sortBy([
                ['avg_for', 'desc']
            ]);

            $avgRankAgt = $teamScores->sortBy([
                ['avg_agt', 'asc']
            ]);
            $avgRankPassing = $teamScores->sortBy([
                ['avg_passing', 'desc']
            ]);

            $avgRankRushings = $teamScores->sortBy([
                ['avg_rushings', 'desc']
            ]);

            $avgPenaltyRank = $teamScores->sortBy([
                ['penalties_yrds', 'desc']
            ]);

            $redZoneRank = $teamScores->sortBy([
                ['redzone_percent', 'desc']
            ]);

            $tdRecRank = $teamScores->sortBy([
                ['td_rec_total', 'desc']
            ]);
            $tdRushingsRank = $teamScores->sortBy([
                ['td_rushings_total', 'desc']
            ]);
            $tdRank = $teamScores->sortBy([
                ['td_total', 'desc']
            ]);
            $tdAvgRank = $teamScores->sortBy([
                ['td_avg_total', 'desc']
            ]);
            $defPassingAllowedRank = $teamScores->sortBy([
                ['def_passing_allowed_agt', 'desc']
            ]);
            $defRushingAllowedRank = $teamScores->sortBy([
                ['def_rushing_allowed_agt', 'desc']
            ]);
            $sacksAgtrank = $teamScores->sortBy([
                ['sacks_agt', 'desc']
            ]);
            $passingAgtrank = $teamScores->sortBy([
                ['passing_agt', 'asc']
            ]);
            $rushingsAgtrank = $teamScores->sortBy([
                ['rushings_agt', 'asc']
            ]);


            $avgForRankFor = $this->getTeamRank($avgRankFor, $id);
            $avgPassingRank = $this->getTeamRank($avgRankPassing, $id);
            $avgRushingsRank = $this->getTeamRank($avgRankRushings, $id);
            $avgPenaltyRank = $this->getTeamRank($avgPenaltyRank, $id);
            $redZoneRank = $this->getTeamRank($redZoneRank, $id);
            $tdRecRank = $this->getTeamRank($tdRecRank, $id);
            $tdRushingsRank = $this->getTeamRank($tdRushingsRank, $id);
            $tdRank = $this->getTeamRank($tdRank, $id);
            $tdAvgRank = $this->getTeamRank($tdAvgRank, $id);
            $avgAgtRank = $this->getTeamRank($avgRankAgt, $id);
            $defPassingAllowedRank = $this->getTeamRank($defPassingAllowedRank, $id);
            $defRushingAllowedRank = $this->getTeamRank($defRushingAllowedRank, $id);
            $sacksAgtrank = $this->getTeamRank($sacksAgtrank, $id);
            $passingAgtrank = $this->getTeamRank($passingAgtrank, $id);
            $rushingsAgtrank = $this->getTeamRank($rushingsAgtrank, $id);

            $team['avg_for_rank'] = Number::ordinal($avgForRankFor + 1);
            $team['avg_passing_rank'] = Number::ordinal($avgPassingRank + 1);
            $team['avg_rushings_rank'] = Number::ordinal($avgRushingsRank + 1);
            $team['penalty_rank'] = Number::ordinal($avgPenaltyRank + 1);
            $team['redzone_rank'] = Number::ordinal($redZoneRank + 1);
            $team['td_rec_rank'] = Number::ordinal($tdRecRank + 1);
            $team['td_rushings_rank'] = Number::ordinal($tdRushingsRank + 1);
            $team['td_rank'] = Number::ordinal($tdRank + 1);
            $team['td_avg_rank'] = Number::ordinal($tdAvgRank + 1);
            $team['avg_agt_rank'] = Number::ordinal($avgAgtRank + 1);
            $team['def_passing_allowed_agt_rank'] = Number::ordinal($defPassingAllowedRank + 1);
            $team['def_rushing_allowed_agt_rank'] = Number::ordinal($defRushingAllowedRank + 1);
            $team['sacks_agt_rank'] = Number::ordinal($sacksAgtrank + 1);
            $team['passing_agt_rank'] = Number::ordinal($passingAgtrank + 1);
            $team['rushings_agt_rank'] = Number::ordinal($rushingsAgtrank + 1);

            return $team;
        });

        return $withRankings;
    }

    public function getCurrentTeamRank($season, $teamId)
    {
        $rankings = $this->getRankings($season);

        if (empty($rankings)) {
            return [];
        }

        $rankings = $rankings->where('id', $teamId)->first();

        return $rankings;
    }

    private function getTeamRank($teamScores, $teamId)
    {
        $avgForRankFor = $teamScores->values()->search(function($team) use ($teamId) {
            return $team['id'] == $teamId;
        });

        return $avgForRankFor;
    }

    private function teamAvgStats($teamScores)
    {
        $avg = $teamScores->map(function($score) {
            $homeTeamStats = $score->team_stats['hometeam'] ?? [];
            $awayTeamStats = $score->team_stats['awayteam'] ?? [];

            $hPenalties = $homeTeamStats['penalties']['total'] ?? 0;
            $aPenalties = $awayTeamStats['penalties']['total'] ?? 0;
            $hRedZone = $homeTeamStats['red_zone']['made_att'] ?? 0;
            $aRedZone = $awayTeamStats['red_zone']['made_att'] ?? 0;
            $receiving = $score['receiving'] ?? [];
            $rushing = $score['rushing'] ?? [];
            
            $hPenalties = explode('-', $hPenalties);
            $hPenaltyValue = (int) $hPenalties[0] ?? 0;
            $hPenaltyYards = (int) $hPenalties[1] ?? 0;

            $aPenalties = explode('-', $aPenalties);
            $aPenaltyValue = (int) $aPenalties[0] ?? 0;
            $aPenaltyYards = (int) $aPenalties[1] ?? 0;

            $hRedZone = explode('-', $hRedZone);
            $hCttd = 0;
            $hAttempts = 0;

            if (count($hRedZone) == 2) {
                $hCttd = (int) $hRedZone[0] ?? 0;
                $hAttempts = (int) $hRedZone[1] ?? 0;
            }

            $aRedZone = explode('-', $aRedZone);
            $aCttd = 0;
            $aAttempts = 0;

            if (count($aRedZone) == 2) {
                $aCttd = (int) $aRedZone[0] ?? 0;
                $aAttempts = (int) $aRedZone[1] ?? 0;
            }

            $hRec = $receiving['hometeam'] ?? [];
            $aRec = $receiving['awayteam'] ?? [];
            $hRecPlayerTd = collect($hRec['player'] ?? []);
            $aRecPlayerTd = collect($aRec['player'] ?? []);
            $hRushing = collect($rushing['hometeam']['player'] ?? []);
            $aRushing = collect($rushing['awayteam']['player'] ?? []);
            $hTdTot = $hRecPlayerTd->sum('receiving_touch_downs') + $hRushing->sum('rushing_touch_downs');
            $aTdTot = $aRecPlayerTd->sum('receiving_touch_downs') + $aRushing->sum('rushing_touch_downs');
            
            // check for home if team is home
            if ($score['isHome']) {

                return [
                    'passing' => $homeTeamStats['passing']['total'] ?? 0,
                    'passing_agt' => $awayTeamStats['passing']['total'] ?? 0,
                    'rushings' => $homeTeamStats['rushings']['total'] ?? 0,
                    'rushings_agt' => $awayTeamStats['rushings']['total'] ?? 0,
                    'for' => $score['home_score'],
                    'agt' => $score['away_score'],
                    'penalties' => $hPenaltyValue,
                    'penalties_yrds' => $hPenaltyYards,
                    'redzone_cttd' => $hCttd,
                    'rec_player_td' => $hRecPlayerTd->sum('receiving_touch_downs'),
                    'rushings_player_td' => $hRushing->sum('rushing_touch_downs'),
                    'td_total' => $hTdTot,
                    'def_passing_allowed_agt' => $awayTeamStats['first_downs']['passing'] ?? 0,
                    'def_rushing_allowed_agt' => $awayTeamStats['first_downs']['rushing'] ?? 0,
                    'sacks_agt' => $awayTeamStats['sacks']['total'] ?? 0,
                ];
            }


            // by deafult get values from away team
            return [
                'passing' => $awayTeamStats['passing']['total'] ?? 0,
                'passing_agt' => $homeTeamStats['passing']['total'] ?? 0,
                'rushings' => $awayTeamStats['rushings']['total'] ?? 0,
                'rushings_agt' => $homeTeamStats['rushings']['total'] ?? 0,
                'for' => $score['away_score'],
                'agt' => $score['home_score'],
                'penalties' => $aPenaltyValue,
                'penalties_yrds' => $aPenaltyYards,
                'redzone_cttd' => $aCttd,
                'redzone_attempts' => $aAttempts,
                'rec_player_td' => $aRecPlayerTd->sum('receiving_touch_downs'),
                'rushings_player_td' => $aRushing->sum('rushing_touch_downs'),
                'td_total' => $aTdTot,
                'def_passing_allowed_agt' => $homeTeamStats['first_downs']['passing'] ?? 0,
                'def_rushing_allowed_agt' => $homeTeamStats['first_downs']['rushing'] ?? 0,
                'sacks_agt' => $homeTeamStats['sacks']['total'] ?? 0,
            ];
        });

        $redzoneCtdAvg = $avg->avg('redzone_cttd');
        $redzoneAttemptsAvg = $avg->avg('redzone_attempts');
        $redZonePercent = ($redzoneCtdAvg / $redzoneAttemptsAvg) * 100;
        $tdRec = number_format($avg->sum('rec_player_td'), 1);
        $tdRushings = number_format($avg->sum('rushings_player_td'), 1);
        

        return [
            'passing' => number_format($avg->avg('passing'), 1),
            'rushings' => number_format($avg->avg('rushings'), 1),
            'for' => number_format($avg->avg('for'), 1),
            'agt' => number_format($avg->avg('agt'), 1),
            'penalties' => number_format($avg->avg('penalties'), 1),
            'penalties_yrds' => number_format($avg->avg('penalties_yrds'), 1),
            'redzone_cttd' => number_format($avg->avg('redzone_cttd'), 1),
            'redzone_attempts' => number_format($avg->avg('redzone_attempts'), 1),
            'redzone_percent' => number_format($redZonePercent, 2),
            'td_rec_total' => $tdRec,
            'td_rushings_total' => $tdRushings,
            'td_total' => number_format($avg->sum('td_total'), 1),
            'td_avg_total' => number_format($avg->avg('td_total'), 1),
            'def_passing_allowed_agt' => number_format($avg->avg('def_passing_allowed_agt'), 1),
            'def_rushing_allowed_agt' => number_format($avg->avg('def_rushing_allowed_agt'), 1),
            'sacks_agt' => number_format($avg->avg('sacks_agt'), 1),
            'passing_agt' => number_format($avg->avg('passing_agt'), 1),
            'rushings_agt' => number_format($avg->avg('rushings_agt'), 1),
        ];
    }
}

