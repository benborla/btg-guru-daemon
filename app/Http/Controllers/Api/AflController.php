<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Afl\AflService;
use Illuminate\Http\JsonResponse;
use App\Models\AflApiResponse;

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
     *
     * @return JsonResponse
     */
    public function schedules(): JsonResponse
    {
        $currentRound = get_current_round()['round'];
        $scheduleData = $this->aflService->getUpcomingSchedules();
        $round = (int) request()->get('round') ?: $currentRound;

        if ($round !== $currentRound) {
            return response()->json([
                'round' => $round,
                'status' => 'OK'
            ]);
        }

        if ($scheduleData->isEmpty()) {
            return response()->json([]);
        }

        $formattedSchedules = $scheduleData
            ->map(function ($match) {
                return [
                    'category' => 'AFL Premiership',
                    'week' => (string)$match['round'],
                    'match_id' => $match['match_id'],
                    'status' => $match['status'],
                    'date' => $match['date'],
                    'time' => $match['time'],
                    'venue' => $match['venue'],
                    'localteam' => [
                        'id' => $this->getTeamId($match['home_team']),
                        'name' => $match['home_team'],
                        'score' => (string)($match['home_score'] ?? '0'),
                        'goals' => '0',
                        'behinds' => '0'
                    ],
                    'visitorteam' => [
                        'id' => $this->getTeamId($match['away_team']),
                        'name' => $match['away_team'],
                        'score' => (string)($match['away_score'] ?? '0'),
                        'goals' => '0',
                        'behinds' => '0'
                    ],
                    'quarters' => null,
                    'events' => null,
                    'lineups' => []
                ];
            })
            ->sortBy(function ($match) {
                // Convert dd.mm.YYYY to a sortable format
                $dateParts = explode('.', $match['date']);
                if (count($dateParts) === 3) {
                    return $dateParts[2] . '-' . $dateParts[1] . '-' . $dateParts[0] . ' ' . $match['time'];
                }
                return $match['date'];
            })
            ->values()
            ->all();

        return response()->json([
            'round' => $round,
            'data' => $formattedSchedules
        ]);
    }

    /**
     * Helper method to generate a consistent team ID
     * 
     * @param string $teamName
     * @return string
     */
    private function getTeamId(string $teamName): string
    {
        // This is a simple mapping function to generate consistent IDs
        // In a real implementation, you would fetch this from your database
        $teamMap = [
            'Gold Coast Suns' => '1033',
            'Collingwood Magpies' => '1043',
            'Western Bulldogs' => '1019',
            'Adelaide Crows' => '1038',
            'Greater Western Sydney Giants' => '1132',
            'Geelong Cats' => '1017',
            'Richmond Tigers' => '1041',
            'Essendon Bombers' => '1048',
            'Fremantle Dockers' => '1047',
            'Hawthorn Hawks' => '1045',
            'Melbourne Demons' => '1049',
            'North Melbourne Kangaroos' => '1050',
            'St Kilda Saints' => '1042',
            'Sydney Swans' => '1037',
            'Port Adelaide Power' => '1044',
            'West Coast Eagles' => '1031',
            'Brisbane Lions' => '1032',
            'Carlton Blues' => '1040'
        ];

        return $teamMap[$teamName] ?? '1000'; // Default ID if team not found
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
}
