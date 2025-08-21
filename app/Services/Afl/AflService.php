<?php

namespace App\Services\Afl;

use App\Services\ApiDriverHandler;
use App\Services\Facade\ApiInterface;
use App\Services\ApiDrivers\GoalServeApiDriver;
use App\Services\Afl\Utils\Analyzer;
use App\Models\AflApiResponse;
use App\Models\AflSchedule;

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
        if (!has_match_today()) {
            return $this->analyzer->getNextMatchSchedule();
        }

        return $this->analyzer->getTeamScores();
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
        return $this->analyzer->getAllTeamNamesInfo()->sortBy('name');
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
            $roundData = $scheduleData->where('round', $round)->first();
            return [$round => [
                'round' => $roundData['round'] ?? null,
                'match_id' => $roundData['match_id'] ?? null
            ] ?: (object)[
                    'round' => $round,
                    'status' => 'BYE', // or whatever default you want,
                    'match_status' => 'BYE', // or whatever default you want
            ]];
        });

        // to flag BYE
        $completeSchedule = $completeRounds->mapWithKeys(function ($round) use ($scheduleData) {
            $roundData = $scheduleData->where('round', $round)->first();
            return [$round => $roundData ?: (object)[
                'round' => $round,
                'status' => 'BYE', // or whatever default you want
                'match_status' => 'BYE', // or whatever default you want
            ]];
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


            $rounds = $data->map(function($a) {
                return $a->round;
            })->values();

            return [
                'rounds' => "{$rounds[0]} - {$rounds[count($rounds) -1]}",
                'pointsFor' => $pointsFor,
                'pointsAgt' => $pointsAgt,
                'total' => round(($totalFor + $totalAgt) / $validRounds ,2 )
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
        $l5['pointsFor'] = round($lastFive->avg('pointsFor'), 2);
        $l5['pointsAgt'] = round($lastFive->avg('pointsAgt'), 2);
        $l5['total'] = $l5['pointsFor'] + $l5['pointsAgt'];


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
            ]
        ];
    }
}
