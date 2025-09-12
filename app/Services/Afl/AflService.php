<?php

namespace App\Services\Afl;

use App\Services\ApiDriverHandler;
use App\Services\Facade\ApiInterface;
use App\Services\ApiDrivers\GoalServeApiDriver;
use App\Services\Afl\Utils\Analyzer;
use App\Models\AflApiResponse;
use App\Models\AflSchedule;
use Carbon\Carbon;

class AflService
{
    private $api;

    public function __construct(
        GoalServeApiDriver $driver,
        private Analyzer $analyzer
    ) {
        $this->api = new ApiDriverHandler($driver);
        try {
            $this->hydrate();
        } catch (\Exception $e) {
            // usually this happens when running migration
            // just ignore for now
            // but i need to work on a different approach so we can avoid this
            // if you're still seeing this, it means that it's still not fixed :-)
        }
    }

    /**
     * Undocumented function
     *
     * @return array<string, string<json>>
     */
    public function getApiLiveData(?string $query): array
    {
        $uri = AflApiResponse::URI_LIVE;
        if (!$this->api instanceof ApiInterface) {
            return [];
        }

        $uri = $uri . ($query !== '' ? "&" . $query : '');
        $response = $this->api->get()->uri($uri)->send();

        return [
            'response_code' => $response->getResponse()->getStatusCode(),
            'response' => $response->getResponse()->json(),
            'uri' => $uri
        ];
    }


    public function getApiSchedules(): array
    {
        $uri = AflApiResponse::URI_SCHEDULE;

        if (!$this->api instanceof ApiInterface) {
            return [];
        }

        $response = $this->api->get()->uri($uri)->send();

        return [
            'response_code' => $response->getResponse()->getStatusCode(),
            'response' => $response->getResponse()->json(),
            'uri' => $uri
        ];
    }

    public function getApiStandings(): array
    {
        $uri = AflApiResponse::URI_STANDINGS;

        if (!$this->api instanceof ApiInterface) {
            return [];
        }

        $response = $this->api->get()->uri($uri)->send();

        return [
            'response_code' => $response->getResponse()->getStatusCode(),
            'response' => $response->getResponse()->json(),
            'uri' => $uri
        ];
    }

    public function hydrate($data = [])
    {
        $data = $data ?: AflApiResponse::getLatestData();
        $response = [];

        if ($data) {
            $response = $data->response;
        }

        $this->analyzer->hydrate($response);
    }


    public function getScoreboard()
    {
        // for testing purposes
        // \Carbon\Carbon::setTestNow(\Carbon\Carbon::create(2025, 7, 28, 23, 59, 0, 'Australia/Sydney'));
        //  if (!has_match_today()) {
        //      return $this->analyzer->getNextMatchSchedule();
        //  }

        $data =  $this->analyzer->getTeamScores();
        $data = $data->map(function($a){
            $a['round_name'] = $roundName = $this->aflPlayOffMappingNames($a['round'])['name'] ?? $a['round'];
            $carbonDate = \Carbon\Carbon::createFromFormat('d.m.Y H:i', $a['date'] . " " . $a['time']);
            $a['game_status'] = $carbonDate->format('M j, Y g:i A');

            return $a;
        });

        return $data;
    }

    public function getHeadToHead()
    {
        return $this->analyzer->getallheadtoheadrecords();
    }

    public function getMatchSummary()
    {
        return $this->analyzer->getMatchSummary();
    }

    public function getTeamStandings($teamId = null)
    {
        $allTeamStandings = $this->analyzer->getTeamStandings();

        if ($teamId)  {
            return array_values(
                array_filter($allTeamStandings, function($item) use ($teamId) { return $item['id'] == $teamId; }
                )
            )[0] ?? [];
        }

        return $allTeamStandings;
    }

    public function getUpcomingSchedules()
    {
        return $this->analyzer->getNextMatchSchedule();
    }

    public function getScheduleByRound(string $round)
    {
        return $this->analyzer->getScheduleByRound($round);
    }

    public function getCurrentMatchData(): array|null
    {
        return $this->analyzer->getCurrentMatchData() ?? [];
    }

    public function getPreviousMatchData(): array|null
    {
        return $this->analyzer->getPreviousMatchData() ?? [];
    }

    /**
     * Get match data by specific match ID
     *
     * @param string $matchId The match ID to find
     * @return array|null The match data or null if not found
     */
    public function getMatchDataById(string $matchId): ?array
    {
        return $this->analyzer->getMatchDataById($matchId);
    }

    public function getTeams()
    {
        return $this->analyzer->getAllTeamNames();
    }

    public function getTeamsInfo()
    {
        $tournament = $this->getAflSchedules();

        $teams = collect($tournament['round'])->map(function($a) {
            $week = collect($a['week'])->map(function($b) {

                if (!isset($b['match']['@date'])) {
                    $matches = collect($b['match'])->flatMap(function($c) {
                        return [
                            $c['localteam']['@name'] => [
                                'name' => $c['localteam']['@name'],
                                'id' => $c['localteam']['@id'],
                                'image_name' => str_replace(' ', '_', $c['localteam']['@name'])
                            ],
                            $c['visitorteam']['@name'] => [
                                'name' => $c['visitorteam']['@name'],
                                'id' => $c['visitorteam']['@id'],
                                'image_name' => str_replace(' ', '_', $c['visitorteam']['@name'])
                            ],
                        ];
                    });

                    return $matches->unique()->values();
                }
            });

            return $week;
        });// //  //

        $teams = $teams->flatMap(fn($a) => $a)->flatMap(fn($a) => $a)->unique()->values()->sortBy('name');

        $teams = $teams->filter(function($a) {
            return !in_array($a['name'], [
                'Winner EF1',
                'Winner EF2',
                'Winner QF1',
                'Winner QF2',
                'Winner SF1',
                'Winner SF2',
                'Winner GF1',
                'Winner GF2',
                'Loser QF2',
                'Loser QF1',
            ]);
        });

        return $teams;
    }

    public function aflPlayOffMappingNames($round)
    {
        $mapping =  [
            '25' => [
                'name' => 'FW',
                'full_name' => 'Finals Week 1',
                'bg_color' => '#0d6efd'
            ],
            '26' => [
                'name' => 'SF',
                'full_name' => 'Semi Finals',
                'bg_color' => '#0d6efd'
            ],
            '27' => [
                'name' => 'PF',
                'full_name' => ' Preliminary Finals',
                'bg_color' => '#0d6efd'
            ],
            '28' => [
                'name' => 'GF',
                'full_name' => 'Grand Finals',
                'bg_color' => '#0d6efd'
            ],
        ];

        return isset($mapping[$round]) ? $mapping[$round] : $round;
    }

    public function getHistorySchedules($teamId = null)
    {
        $scheduleData = AflSchedule::all();
        $standings = $this->getTeamStandings($teamId);

        // manual filtering since stored team data is in json format
        $scheduleData = $scheduleData->filter(function ($schedule) use ($teamId) {
            return $schedule->local_team['id'] == $teamId || $schedule->visitor_team['id'] == $teamId;
        })->map(function($item) use ($teamId){

            // add match status on very round
            $localTeamScore = $item['local_team']['score'] ?? 0;
            $visitorScore = $item['visitor_team']['score'] ?? 0;

            if ($item['local_team']['id'] == $teamId) {

                if ($localTeamScore > $visitorScore) {
                    $item['match_status'] = 'W';
                }else if ($localTeamScore < $visitorScore) {
                    $item['match_status'] = 'L';
                } else {
                    $item['match_status'] = '-';
                }
            }

            if ($item['visitor_team']['id'] == $teamId) {

                if ($visitorScore > $localTeamScore) {
                    $item['match_status'] = 'W';
                }else if ($visitorScore < $localTeamScore) {
                    $item['match_status'] = 'L';
                } else {
                    $item['match_status'] = '-';
                }
            }

            // total
            $item['total_scores'] = $localTeamScore + $visitorScore;

            return $item;
        });

        $allRounds = $scheduleData->unique('round')->pluck('round');

        // Separate numeric and non-numeric rounds
        $numericRounds = $allRounds->filter(function ($round) {
            return is_numeric($round);
        })->map(function ($round) {
            return (int) $round;
        })->sort();

        $nonNumericRounds = $allRounds->filter(function ($round) {
            return !is_numeric($round);
        });
        $completeRounds = [];
        // Fill missing numeric rounds
        if ($numericRounds->isNotEmpty()) {
            $minRound = $numericRounds->min();
            $maxRound = $numericRounds->max();
            $completeNumericRounds = collect(range($minRound, $maxRound));
            $missingNumericRounds = $completeNumericRounds->diff($numericRounds);

            // Combine with non-numeric rounds FIRST
            $completeRounds = $nonNumericRounds
                ->concat($completeNumericRounds->concat($missingNumericRounds)->sort())
                ->unique()
                ->values();
        } else {
            $completeRounds = $nonNumericRounds->unique();
        }
        $roundsInfo = $completeRounds->mapWithKeys(function ($round) use ($scheduleData) {
            $roundData = $scheduleData->where('round', $round);
            $roundName = $this->aflPlayOffMappingNames($round);
            if ($roundData->count() > 1) {
                return $roundData->values()->mapWithKeys(function($a, $i) use ($round, $roundName){
                    $newRound = $round . "(" . $i + 1 .")";

                    return [
                        $newRound => [
                            'round' => $round,
                            'match_id' => $a['match_id'],
                            'round_name' => $roundName['name'] ?? $round,
                            'bg_color' => $roundName['bg_color'] ?? ''
                        ]
                    ];
                });

            } else {

                $first = $roundData->first();

                return [$round => [
                    'round' => $first['round'] ?? $round,
                    'round_name' => $roundName['name'] ?? $round,
                    'bg_color' => $roundName['bg_color'] ?? '',
                    'match_id' => $first['match_id'] ?? null
                ] ?: (object)[
                        'round' => $round,
                        'round_name' => $roundName['name'] ?? $round,
                        'bg_color' => $roundName['bg_color'] ?? '',
                        'status' => 'BYE', // or whatever default you want,
                        'match_status' => 'BYE', // or whatever default you want
                    ]];
            }

        });

        /* $roundsInfo->put('26', ['round' => '26', 'round_name' => $this->aflPlayOffMappingNames(26)['name'], 'bg_color' => $this->aflPlayOffMappingNames(26)['bg_color'],  'match_id' => '1234']); */
        /* $roundsInfo->put('27', ['round' => '27', 'round_name' => $this->aflPlayOffMappingNames(27)['name'], 'bg_color' => $this->aflPlayOffMappingNames(27)['bg_color'],  'match_id' => '1234']); */
        /* $roundsInfo->put('28', [ */
        /*     'round' => '28', */
        /*     'round_name' => $this->aflPlayOffMappingNames(28)['name'], */
        /*     'bg_color' => $this->aflPlayOffMappingNames(28)['bg_color'], 'match_id' => '1234' */
        /* ] */
        /* ); */

        // to flag BYE
        $completeSchedule = $completeRounds->mapWithKeys(function ($round) use ($scheduleData) {
            $roundData = $scheduleData->where('round', $round);
            if ($roundData->count() > 1) {
                return $roundData->values()->mapWithKeys(function($a, $i) use ($round){
                    $newRound = $round . "(" . $i + 1 .")";
                    return [
                        $newRound => $a,

                    ];
                });
            }
            else {
                return [$round => $roundData->first() ?: (object)[
                    'round' => $round,
                    'status' => 'BYE', // or whatever default you want
                    'match_status' => 'BYE', // or whatever default you want
                ]];
            }
        });

        $chunked = $completeSchedule->chunk(5)->map(function ($data, $i) use ($teamId) {
            $validRounds = $data->whereIn('match_status', ['L', 'W'])->count();
            $avg = $data->map(function($a) use ($teamId){

                if (isset($a->local_team)) {
                    $id = $a->local_team['id'];
                    $score = $a->local_team['score'];

                    if ($id == $teamId) {
                        return $score;
                    }
                }
                return 0;
            });

            $visitorAvg = $data->map(function($a) use ($teamId){

                if (isset($a->visitor_team)) {
                    $id = $a->visitor_team['id'];
                    $score = $a->visitor_team['score'];

                    if ($id == $teamId) {
                        return $score;
                    }
                }
                return 0;
            });

            $pointsFor = round($avg->merge($visitorAvg)->reject(0)->avg(), 2);

            $agt = $data->map(function($a) use ($teamId){

                if (isset($a->local_team)) {
                    $id = $a->local_team['id'];
                    $score = $a->local_team['score'];

                    if ($id != $teamId) {
                        return $score;
                    }
                }
                return 0;
            });
            $visitorAgt = $data->map(function($a) use ($teamId){

                if (isset($a->visitor_team)) {
                    $id = $a->visitor_team['id'];
                    $score = $a->visitor_team['score'];

                    if ($id != $teamId) {
                        return $score;
                    }
                }
                return 0;
            });

            $pointsAgt = round($agt->merge($visitorAgt)->reject(0)->avg(), 2);

            $totalFor = $avg->merge($visitorAvg)->sum();
            $totalAgt = $visitorAgt->merge($agt)->sum();
            $total = 0;

            if ($totalFor > 0 && $totalAgt > 0) {
                $total = round(($totalFor + $totalAgt) / $validRounds ,0 );
            }

            $rounds = $data->map(function($a) {
                return $a->round;
            })->values();

            return [
                'rounds' => "{$rounds[0]} - {$rounds[count($rounds) -1]}",
                'pointsFor' => $pointsFor,
                'pointsAgt' => $pointsAgt,
                'total' =>  $total
            ];
        });
        // SEA computattion
        $sea = [
           'rounds' => 'SEA',
           'pointsFor' => '-',
           'pointsAgt' => '-',
            'total' => '-'
        ];

        if ($standings) {
            $sea['pointsFor'] = round($standings['points_for'] / $standings['games_played'] , 2);
            $sea['pointsAgt'] = round($standings['points_against'] / $standings['games_played'], 2);
            $sea['total'] = round(
                ($standings['points_for'] + $standings['points_against']) / $standings['games_played']
            , 2);
        }


        // L5 computation
        $l5 = [
           'rounds' => 'L5',
           'pointsFor' => '-',
           'pointsAgt' => '-',
           'total' => '-'
        ];

        $lastFive = $completeSchedule->whereIn('match_status', ['W', 'L', 'BYE'])->take(-5)->map(function ($a) use($teamId) {
            $pointsFor = "";
            $pointsAgt = "";

            if (!empty($a->local_team) && !empty($a->visitor_team)) {
                if ($a->local_team['id'] == $teamId) {
                    $pointsFor = $a->local_team['score'];
                } else {
                    $pointsAgt = $a->local_team['score'];
                }
            }

            if (!empty($a->visitor_team)) {
                if ($a->visitor_team['id'] != $teamId) {
                    $pointsAgt = $a->visitor_team['score'];
                } else {
                    $pointsFor = $a->visitor_team['score'];
                }
            }

            return [
                'pointsFor' => $pointsFor,
                'pointsAgt' => $pointsAgt,
            ];

        });

        $lastFive = $lastFive->filter(fn($item) => !empty($item['pointsFor']) && !empty($item['pointsAgt']));
        $l5['pointsFor'] = round($lastFive->avg('pointsFor'), 0);
        $l5['pointsAgt'] = round($lastFive->avg('pointsAgt'), 0);
        $l5['total'] = $l5['pointsFor'] + $l5['pointsAgt'];


        $teamPoints = $this->checkTeamInLocalOrVisitor($teamId, $completeSchedule);
        $cumulativeAvgs = $teamPoints->map(function ($value, $key) use ($teamPoints) {
            $hasOr = in_array('OR', $teamPoints->keys()->toArray());
            $subset = $teamPoints->take((int)$key + ($hasOr ? 1 : 0))->filter(fn($item) => $item['pointsFor'] != 0);

            if ($value['match_status'] == 'BYE') {
                return [
                    'avgFor' => 'BYE',
                    'avgAgt' => 'BYE',
                    'avgTotal' => 'BYE'
                ];
            }

            if ($value['match_status'] == '-') {
                return [
                    'avgFor' => '0',
                    'avgAgt' => '0',
                    'avgTotal' => '0'
                ];
            }

            $for = $value['pointsFor'];
            $agt = $value['pointsAgt'];

            if ($key == 'OR') {
                return [
                    'avgFor' => $for,
                    'avgAgt' => $agt,
                    'avgTotal' => $for + $agt
                ];
            }

            if ($key == '1') {
                if ($hasOr) {
                    $subset = $teamPoints->take($key+1);
                } else {

                    return [
                        'avgFor' => $for,
                        'avgAgt' => $agt,
                        'avgTotal' => $for + $agt,
                        /* 'avgTotal' => $for + $agt, */
                    ];
                }
            }


            $total = ($subset->sum('pointsFor') + $subset->sum('pointsAgt')) / $subset->count();
            $avg1 = number_format($subset->avg('pointsFor'), 0 );
            $avg2 = number_format($subset->avg('pointsAgt'), 0 );

            return [
                'for' => $for,
                'agt' => $agt,
                'avgFor' => $avg1,
                'avgAgt' => $avg2,
                'avgTotal' =>  $avg1 + $avg2
            ];
        });

        return [
            'data' => $completeSchedule,
            'rounds' => $completeRounds,
            'roundsInfo' => $roundsInfo,
            'summaries' => [
                'scores' => [
                    ...$chunked,
                    $sea,
                    $l5
                ],
                'avg' => $cumulativeAvgs->toArray()
            ]
        ];
    }

    private function checkTeamInLocalOrVisitor($teamId, $roundData, $withBye = true)
    {

        $defaultMatchStatus = ['W', 'L', '-'];
        return $roundData->whereIn('match_status', $withBye ? [...$defaultMatchStatus, 'BYE'] : $defaultMatchStatus)->map(function($a) use ($teamId) {
            $pointsFor = 0;
            $pointsAgt = 0;

            if (!empty($a->local_team) && !empty($a->visitor_team)) {
                if ($a->local_team['id'] == $teamId) {
                    $pointsFor = $a->local_team['score'];
                } else {
                    $pointsAgt = $a->local_team['score'];
                }
            }

            if (!empty($a->visitor_team)) {
                if ($a->visitor_team['id'] != $teamId) {
                    $pointsAgt = $a->visitor_team['score'];
                } else {
                    $pointsFor = $a->visitor_team['score'];
                }
            }

            return [
                'pointsFor' => $pointsFor,
                'pointsAgt' => $pointsAgt,
                'match_status' => $a->match_status
            ];
        });
    }

    public function getAflSchedules()
    {
        $schedules = AflApiResponse::where('uri', AflApiResponse::URI_SCHEDULE)->first();

        if (!$schedules) {
            return [];
        }

        $schedules = $schedules->response;
        return $schedules['results']['tournament'];
    }

    public function getScoreBoardFromSchedules()
    {
        // knkow what is the current season today
        $schedules = AflApiResponse::where('uri', AflApiResponse::URI_SCHEDULE)->first();

        if (!$schedules) {
            return [];
        }

        $schedules = $schedules->response;
        $tournament = $schedules['results']['tournament'];
        $seasons = collect($tournament['round']);

        // determine here the current season
        $data = $seasons->map(function($a) {
            if (!isset($a['week'])) {
                return [];
            }
            $rounds = collect($a['week'])->map(function($b) {

                $match = collect($b['match']);

                if (isset($b['match']['@date'])) {
                    $multiArray = [$b['match']];
                    $match = collect($multiArray);
                }


                $filtered = $match->map(function($c) {

                    $gameDate = Carbon::parse($c['@date']);
                    $currentDate = Carbon::now();

                    if ($gameDate->greaterThan($currentDate)) {
                        $c['actual_date'] = $gameDate->format('Y-m-d');
                        return $c;
                    }

                    return null;
                })->filter();

                if ($filtered->count() > 0) {
                    $b['match'] = $filtered;
                    return $b;
                }

            })->filter();


            if ($rounds->count() == 0) {
                return null;
            }

            $a['week'] = $rounds;
            return $a;

        })->filter();

        if ($data->count() == 0) {
            return [];
        }

        // flat map
        $data = $data->first()['week']->map(function($a) {
            $matches = $a['match']->map(function($b) use($a) {
                $b['round'] = $a['@number'];
                return $b;
            });

            return $matches;
        })->first()->map(function($a){
            $a['match_id'] = $a['@id'];
            $a['venue'] = $a['@venue'];
            $a['date'] = $a['@date'];
            $a['time'] = $a['@time'];
            $a['status'] = $a['@status'];
            $a['home_team'] = $a['localteam']['@name'];
            $a['home_team_id'] = $a['localteam']['@id'];
            $a['home_score'] = $a['localteam']['@score'];
            $a['away_team'] = $a['visitorteam']['@name'];
            $a['away_team_id'] = $a['visitorteam']['@id'];
            $a['away_score'] = $a['visitorteam']['@score'];
            $a['total_score'] = $a['localteam']['@score'] + $a['visitorteam']['@score'];
            $a['margin'] = $a['localteam']['@score'] - $a['visitorteam']['@score'];
            $a['winner'] = $a['localteam']['@name'];
            $a['home_goals'] = $a['localteam']['@goals'];
            $a['home_behinds'] = $a['localteam']['@behinds'];
            $a['away_goals'] = $a['visitorteam']['@goals'];
            $a['away_behinds'] = $a['visitorteam']['@behinds'];
            $a['round_name'] = $roundName = $this->aflPlayOffMappingNames($a['round'])['name'] ?? $a['round'];
            $carbonDate = \Carbon\Carbon::createFromFormat('d.m.Y H:i', $a['@date'] . " " . $a['time']);
            $a['game_status'] = $carbonDate->format('M j, Y g:i A');

            return $a;
        });


        return $data->values();
    }
}
