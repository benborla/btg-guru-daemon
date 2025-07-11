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

        // If we are fetching the current round, get data from the live source
        if ($round == $currentRound) {
            $liveResponse = AflApiResponse::getLatestData();

            if ($liveResponse && !empty($liveResponse->response)) {
                // Format live matches directly from the response
                $formattedSchedules = $this->formatLiveMatches($liveResponse->response);

                // If we successfully formatted matches, return them
                if (!empty($formattedSchedules)) {
                    return response()->json([
                        'live_match_available' => has_match_today(),
                        'current_round' => $currentRound,
                        'round' => $round,
                        'data' => $formattedSchedules
                    ]);
                }
            }
        }

        // If we don't have live data or we're looking for a different round,
        // fetch from the database
        if (empty($formattedSchedules)) {
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
                        'quarters' => null,
                        'events' => null,
                        'lineups' => []
                    ];
                })
                ->values()
                ->all();
        }

        return response()->json([
            'live_match_available' => has_match_today(),
            'current_round' => $currentRound,
            'round' => $round,
            'data' => $formattedSchedules
        ]);
    }

    /**
     * Format live matches from the API response
     *
     * @param array $liveData
     * @return array
     */
    private function formatLiveMatches($liveData): array
    {
        // Check if we have the expected structure
        if (!isset($liveData['scores']['category']['match'])) {
            return [];
        }

        // Get matches array, ensuring it's always an array even if there's only one match
        $matches = $liveData['scores']['category']['match'];
        if (!isset($matches[0])) {
            $matches = [$matches]; // Wrap single match in array
        }

        // Format each match
        $formattedMatches = [];
        foreach ($matches as $match) {
            // Extract attributes with @ prefix
            $matchId = $match['@id'] ?? '';
            $status = $match['@status'] ?? 'NS';
            $date = $match['@date'] ?? '';
            $time = $match['@time'] ?? '';
            $venue = $match['@venue'] ?? '';
            $week = $liveData['scores']['category']['@week'] ?? '';

            // Extract team data
            $localTeam = [];
            if (isset($match['localteam'])) {
                $localTeam = [
                    'name' => $match['localteam']['@name'] ?? '',
                    'id' => $match['localteam']['@id'] ?? '',
                    'score' => (string)($match['localteam']['@score'] ?? '0'),
                    'goals' => (string)($match['localteam']['@goals'] ?? '0'),
                    'behinds' => (string)($match['localteam']['@behinds'] ?? '0')
                ];
            }

            $visitorTeam = [];
            if (isset($match['visitorteam'])) {
                $visitorTeam = [
                    'name' => $match['visitorteam']['@name'] ?? '',
                    'id' => $match['visitorteam']['@id'] ?? '',
                    'score' => (string)($match['visitorteam']['@score'] ?? '0'),
                    'goals' => (string)($match['visitorteam']['@goals'] ?? '0'),
                    'behinds' => (string)($match['visitorteam']['@behinds'] ?? '0')
                ];
            }

            // Extract quarters and events if available
            $quarters = isset($match['quarters']) ? $match['quarters'] : null;
            $events = isset($match['events']) ? $match['events'] : null;
            $lineups = isset($match['lineups']) ? $match['lineups'] : [];

            // Base match data
            $baseData = [
                'category' => 'AFL Premiership',
                'week' => (string)$week,
                'match_id' => $matchId,
                'status' => $status,
                'date' => $date,
                'time' => $time,
                'venue' => $venue,
            ];

            // Add to formatted matches
            $formattedMatches[] = [
                ...$baseData,
                'localteam' => $localTeam,
                'visitorteam' => $visitorTeam,
                'quarters' => $quarters,
                'events' => $events,
                'lineups' => $lineups
            ];
        }

        return $formattedMatches;
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
            'upcoming_match_schedule' => get_schedule_by_round(get_current_round()['round'])

        ]);
    }

    public function liveMatchDataFeed(): JsonResponse
    {
        return response()->json($this->aflService->getCurrentMatchData());
    }

    public function liveTest()
    {
        dd(iterate_through_current_round_until_start());
        dd('test');
    }
}
