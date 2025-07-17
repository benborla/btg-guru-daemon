<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Afl\AflService;
use Illuminate\Http\JsonResponse;
use App\Models\AflApiResponse;
use App\Models\AflSchedule;

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
        return $this->aflService->getScoreboard();
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
        $round = request()->get('round') == 0 ? 'OR' : request()->get('round');
        $formattedSchedules = [];

        if ($round == $currentRound) {
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

        $formattedSchedules = $sortedScheduleData
            ->map(function ($match) {
                // Extract team data from the JSON structure
                $localTeam = $match->local_team;
                $visitorTeam = $match->visitor_team;
                $matchData = AflApiResponse::findByMatchData($match->match_id, $match->round)->first();
                $this->aflService->hydrate($matchData);
                $matchDetails = $this->aflService->getMatchDataById($match->match_id);

                // Get the base data directly from the model
                $baseData = [
                    'category' => 'AFL Premiership',
                    'week' => (string)$match->round,
                    'match_id' => $match->match_id,
                    'status' => $match->status,
                    'date' => $match->date,
                    'time' => $match->time,
                    'venue' => $match->venue,
                ];

                // Add team data
                return [
                    ...$baseData,
                    'localteam' => $localTeam,
                    'visitorteam' => $visitorTeam,
                    'quarters' => $matchDetails['quarters'] ?? [],
                    'events' => $matchDetails['events'] ?? [],
                    'lineups' => $matchDetails['lineups'] ?? []
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'live_match_available' => has_live_match_ongoing(),
            'current_round' => $currentRound,
            'next_match_countdown' => get_time_until_next_match(),
            'round' => $round,
            'data' => $formattedSchedules
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
        $data = AflApiResponse::findByMatchData($matchId, $round)->first();
        abort_if(!$data, 404, 'Match not found');

        $this->aflService->hydrate($data);
        // Use the new getMatchDataById method to get the exact match by ID
        $structured = $this->aflService->getMatchDataById($matchId);

        // If the specific match wasn't found in the data, fall back to current match data
        if (!$structured) {
            $structured = $this->aflService->getCurrentMatchData();
        }

        return response()->json([
            'match_date' => $data->match_date,
            'source' => 'proxy_server',
            'round' => $data->round,
            ...$structured
        ]);
    }

    public function scoreSummary(string $round)
    {
        // return $this->aflService->getScoreSummary($round);
    }

    public function liveTest()
    {
        dd(iterate_through_current_round_until_start());
        dd('test');
    }
}
