<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AflApiResponse;
use App\Models\AflSchedule;
use App\Services\Afl\AflService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use App\Models\Types\AflRequestType;
use Carbon\Carbon;

class AflController extends Controller
{
    protected AflService $aflService;

    public function __construct(AflService $aflService)
    {
        $this->aflService = $aflService;
    }

    public function index()
    {
        $aflData = AflApiResponse::getLatestData();

        if (!$aflData) {
            return response()->json([
                'error' => 'AFL data not found',
            ], 404);
        }

        return response()->json($aflData->response);
    }

    public function scoreboard()
    {
        if (has_match_today()) {
            return $this->aflService->getScoreboard();
        }

        return $this->aflService->getScoreBoardFromSchedules();
    }

    public function standing()
    {
        return $this->aflService->getTeamStandings();
    }

    public function headToHead()
    {
        return $this->aflService->getHeadToHead();
    }

    public function teams()
    {
        return $this->aflService->getTeams();
    }

    public function summary()
    {
        return $this->aflService->getMatchSummary();
    }

    /**
     * Get formatted schedule data in the requested format
     * Handles both current round (from live data) and historical rounds
     *
     * @return JsonResponse
     */
    public function schedules(): JsonResponse
    {
        $currentRound = get_current_round()['round'];
        $roundQuery = request()->get('round');
        $round = !is_null($roundQuery) && (int) $roundQuery == 0 ? 'OR' : $roundQuery;
        $formattedSchedules = [];

        if ($roundQuery == has_live_match_ongoing() && $roundQuery == $currentRound) {
            $liveResponse = AflApiResponse::getLatestData();

            if ($liveResponse && !empty($liveResponse->response)) {
                // Format live matches directly from the response
                $formattedSchedules = format_live_matches($liveResponse->response);

                // If we successfully formatted matches, return them
                if (!empty($formattedSchedules)) {
                    return response()->json([
                        'live_match_available' => has_live_match_ongoing(),
                        'current_round' => $currentRound,
                        'next_match_countdown' => get_time_until_next_match(),
                        'round' => $round,
                        'data' => $formattedSchedules
                    ]);
                }
            }
        }

        // If we don't have live data or we're looking for a different round,
        // fetch from the database
        $scheduleData = AflSchedule::byRound($round)->get();

        if ($scheduleData->isEmpty()) {
            return response()->json([
                'round' => $round,
                'data' => []
            ]);
        }

        // Sort the schedule data by date and time using the model's static method
        $sortedScheduleData = AflSchedule::sortByDateTime($scheduleData);

        // Process the schedule data - with caching for historical rounds
        if ($round == $currentRound) {
            // For current round, always get fresh data without caching
            $formattedSchedules = process_schedule_data($sortedScheduleData, $this->aflService);
        } else {
            // For historical rounds, use cache with a 24-hour expiration
            $cacheKey = 'afl_schedules_round_' . $round;
            $aflService = $this->aflService;
            $formattedSchedules = Cache::remember($cacheKey, now()->addYear(), function () use ($sortedScheduleData, $aflService) {
                return process_schedule_data($sortedScheduleData, $aflService);
            });
        }

        return response()->json([
            'live_match_available' => has_live_match_ongoing(),
            'current_round' => $currentRound,
            'next_match_countdown' => get_time_until_next_match(),
            'round' => $round,
            'data' => $formattedSchedules
        ]);
    }

    public function historySchedules(): JsonResponse
    {

        $teamId = request()->get('teamId');

        if (!$teamId) {
            return response()->json([
                'error' => 'Team ID is required'
            ], 400);
        }

        $scheduleData = $this->aflService->getHistorySchedules($teamId ?? '-');


        return response()->json([
            'teams' => $this->aflService->getTeamsInfo(),
            ...$scheduleData
        ]);
    }

    public function teamsInfo(): JsonResponse
    {
        return response()->json([
            'data' => $this->aflService->getTeamsInfo()
        ]);
    }


    /**
     * Undocumented function
     *
     * @return JsonResponse<string, string>
     */
    public function hasMatchToday(): JsonResponse
    {
        return response()->json([
            'request_id' => uniqid(),
            'has_live_game' => has_match_today(),
            'is_live_match_ongoing' => has_live_match_ongoing(),
            'next_match_countdown' => get_time_until_next_match(),
            'upcoming_match_schedule' => get_schedule_by_round(get_current_round()['round'])

        ]);
    }

    public function liveMatchDataFeed(): JsonResponse
    {
        return response()->json($this->aflService->getCurrentMatchData());
    }



    public function getMatchData(string $round, string $matchId): JsonResponse
    {
        // get from schedule first 
        $data = AflSchedule::where('match_id', $matchId)->first();
        $matchDate = $data->date ?? '';

        $uri = '/afl/home?json=1&date=' . $matchDate;
        // get from the api response which has the most updated scores data
        $apiResponse = AflApiResponse::where('uri', $uri)->first();
        $response = $data;
        
        if (!empty($apiResponse)) {
            $response = process_match_data($apiResponse, $matchId, $this->aflService);
	        $response['round'] =  $this->aflService->aflPlayOffMappingNames($response['round'])['full_name'] ?? $response['round'];
        } else {
             return response()->json([
                    'match_date' => $data->date,
                    'source' => 'proxy_server',
                    'round' => $this->aflService->aflPlayOffMappingNames($data->round)['full_name'] ?? $data->round,
                    '@status' => $data->status,
                    '@date' => $data->date,
                    '@time' => $data->time,
                    '@timezone' => 'AEST',
                    '@timer' => '',
                    '@venue' => $data->venue,
                    '@id' => $data->match_id,
                    'localteam' => [
                        '@name' => $data->local_team['name'],
                        '@score' => $data->local_team['score'],
                        '@goals' => $data->local_team['goals'],
                        '@behinds' => $data->local_team['behinds'],
                        '@psgoals' => $data->local_team['psgoals'],
                        '@psbehinds' => $data->local_team['psbehinds'],
                        '@id' => $data->local_team['id'],
                    ],
                    'visitorteam' => [
                        '@name' => $data->visitor_team['name'],
                        '@score' => $data->visitor_team['score'],
                        '@goals' => $data->visitor_team['goals'],
                        '@behinds' => $data->visitor_team['behinds'],
                        '@psgoals' => $data->visitor_team['psgoals'],
                        '@psbehinds' => $data->visitor_team['psbehinds'],
                        '@id' => $data->visitor_team['id'],
                    ],
                    'quarters' => $data->quarters ?: [],
                    'events' => $data->events ?: [],
                    'lineups' => $data->lineups ?: [
                        'localteam' => [],
                        'visitorteam' => []
                    ]
                ]
            );
        }
        


        return response()->json($response);
    }



    public function scoreSummary(string $round)
    {
        $data = AflApiResponse::whereIn('request_type', ['Live', 'Record'])
            ->where('round', $round)
            ->latest()
            ->first();
        // return $this->aflService->getScoreSummary($round);
    }

    public function liveTest()
    {
        dd(iterate_through_current_round_until_start());
        dd('test');
    }

    public function currentLiveGames()
    {
        $aflGames = AflSchedule::where([
            ['status' , '!=', 'Full Time']
        ])->get();

        if ($aflGames->isEmpty()) {
            return response()->json([
                'data' => []
            ]);
        }

        $upcomingGameDates = $aflGames->map(function ($game) {
            $date = $game->date ?? '';
            $time = $game->time ?? '';

            $auDateTime = Carbon::parse($date . ' ' . $time);
            $now = Carbon::now();

            $diffInMinutes = $auDateTime->diffInMinutes($now);

            if ($diffInMinutes > 0 && $game->status != 'Full Time') {
                return [
                    'date' => $date,
                    'round' => $game->round
                ];
            }

            return null;
        })->filter();


        return response()->json([
            'data' => $upcomingGameDates
        ]);
    }

    public function roundAlias()
    {
        return response()->json([
            'data' => $this->aflService->getRoundAliases()
        ]);
    }
}
