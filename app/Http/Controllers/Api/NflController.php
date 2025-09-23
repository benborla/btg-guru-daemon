<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\Nfl\NflScoresRepository;
use App\Repositories\Nfl\NflApiRepository;
use App\Repositories\Nfl\NflPlayByPlayRepository;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class NflController extends Controller
{

    public function __construct(
        private NflScoresRepository $repository,
        private NflApiRepository $apiRepository,
        private NflPlayByPlayRepository $playByPlayRepository
    ) {}

    public function liveScores()
    {
        return response()->json([
            'data' => $this->repository->getLiveScores(),
            'updated_at' => now()->toISOString(),
        ]);
    }

    public function realTimeScores()
    {
        return response()->json([
            'data' => $this->repository->getRealTimeScores(),
            'updated_at' => now()->toISOString(),
            'cache_ttl' => 5, // Let frontend know cache duration
        ]);
    }

    public function dynamicScores()
    {
        $scores = $this->repository->getScoresWithDynamicTtl();

        return response()->json([
            'data' => $scores,
            'updated_at' => now()->toISOString(),
            'has_live_games' => $scores->contains(fn($game) => $this->isGameLive($game)),
            'next_update' => now()->addSeconds($this->calculateClientRefreshInterval())->toISOString(),
        ]);
    }

    private function calculateClientRefreshInterval(): int
    {
        // Frontend should refresh based on game status
        $now = Carbon::now();
        $isGameTime = in_array($now->dayOfWeek, [0, 1, 4]) &&
                     $now->hour >= 13 && $now->hour <= 23;

        return $isGameTime ? 10 : 60; // 10 seconds during games, 1 minute otherwise
    }

    public function teamsInfo() : JsonResponse
    {
        $data = $this->repository->getTeamsInfo(date('Y'));

        return response()->json($data);
    }

    public function teamSchedule() : JsonResponse
    {
        $teamId = request()->input('teamId');
        $seasonTypeId = request()->input('seasonTypeId');
        $season = request()->input('season') ?? date('Y');
        $seasonTypes = $this->repository->getSeasonTypes();

        $weeksData = collect($seasonTypes)->map(function($item, $i) use($teamId, $seasonTypeId, $season){
            $item['weeks'] = $this->repository->getTeamSchedule($teamId, $season, $item['id']);
            $item['weeks_info'] = $this->repository->getWeeksInfo($item['id']);
            return $item;
        });

        $customStandings = $this->customTeamStandings($weeksData, $teamId);
        $allTeams = $this->repository->getTeamsInfo($season);
        $teamInfo = $allTeams['AFC']->firstWhere('id', $teamId) ?? $allTeams['NFC']->firstWhere('id', $teamId);
        $teamStandings = $this->repository->getTeamStandings($teamId);
        $teamLast5Form = $this->getTeamLast5Form($weeksData, $teamId);

        return response()->json([
            'teams' => $allTeams,
            'teamInfo' => $teamInfo ?? [],
            'seasonTypes' => $seasonTypes,
            'data' => $weeksData,
            'standings' => $teamStandings ?? [],
            'divisionStandings' => $this->repository->getTeamStandingsDivision($teamId),
            'last5Form' => $teamLast5Form ?? [],
            'customStandings' => $customStandings,
            'teamRank' => $this->repository->getCurrentTeamRank($season,$teamId)
        ]);
    }

    private function customTeamStandings($data, $teamId)
    {
        $allData = $data->flatMap(function($item) {
            return $item['weeks'];
        });


        $teamCustomStats = $allData->map(function($item) use($teamId) {
            if (isset($item['match_status'])) {
                return 0;
            }

            if ($item['season_type_id'] == 1) return 0;


            if ($item['team_stats']) {
                if ($item['isHome']) {
                    return [
                        'passingfor' => $item['team_stats']['hometeam']['passing']['total'] ?? 0,
                        'passingagt' => $item['team_stats']['awayteam']['passing']['total'] ?? 0,
                        'rushingfor' => $item['team_stats']['hometeam']['rushings']['total'] ?? 0,
                        'rushingagt' => $item['team_stats']['awayteam']['rushings']['total'] ?? 0,
                    ];
                } else {
                    return [
                        'passingfor' => $item['team_stats']['awayteam']['passing']['total'] ?? 0,
                        'passingagt' => $item['team_stats']['hometeam']['passing']['total'] ?? 0,
                        'rushingfor' => $item['team_stats']['awayteam']['rushings']['total'] ?? 0,
                        'rushingagt' => $item['team_stats']['hometeam']['rushings']['total'] ?? 0,
                    ];
                }

            }
        })->filter()->take(-5)->reverse();

        return $teamCustomStats;
    }

    private function getTeamLast5Form($teamData, $teamId)
    {
        if ($teamData->count() == 0) {
            return [];
        }

        $teamForms = $teamData->flatMap(function($item) use($teamId){
            return $item['weeks']->filter(function($item) use($teamId){
                if (isset($item['home_team_id']) || isset($item['away_team_id'])) {
                    return $item['home_team_id'] == $teamId || $item['away_team_id'] == $teamId;
                }
            });
        });

        $last5Form = $teamForms->take(5)->map(function($a) use($teamId) {
            return $a['home_team_id'] == $teamId ? $a['home_result'] : $a['away_result'];
        });

        return $last5Form;
    }

    public function teamStandings($season, $teamId) : JsonResponse
    {
        $allTeams = $this->repository->getTeamsInfo();
        $teamInfo = $allTeams->firstWhere('id', $teamId);
        $standings = $this->repository->getTeamStandings($season, $teamId);

        return response()->json([
            'message' => count($standings) == 0 ? 'No team found' : 'Team found',
            'data' => $standings
        ]);
    }

    public function scores()
    {
        $date = request()->input('date');
        $week = request()->input('date');

        return response()->json(
            $this->repository->getScores($date)
        );
    }

    public function schedules()
    {
        $week = request()->input('week');
        $season = request()->input('season');
        $seasonTypeId = request()->input('seasonTypeId');

        return response()->json(
            $this->repository->getSchedules($season, $seasonTypeId, $week)
        );
    }

    public function currentWeek()
    {
        return response()->json(
            $this->repository->getCurrentWeek()
        );
    }

    public function scoreboard()
    {
        $games = [];
        $chronological = request()->input('chronological', false);

        if ($this->repository->hasMatchToday()) {
            $games = $this->repository->getScoreBoardDataFromApi($chronological);
        } else {
            $games = $this->repository->getScoreBoardDataFromDb($chronological);
        }

        return response()->json($games);

    }

    public function hasMatchToday()
    {
        return response()->json(
            $this->repository->hasMatchToday()
        );
    }

    public function matchCastBox($matchId, $week)
    {
        return response()->json(
            $this->repository->getMatchCastBoxData($matchId, $week)
        );
    }

    public function getRoosters($teamIds)
    {
        $ids = explode('+', $teamIds);
        $roosters = [];

        foreach ($ids as $id) {
            $roosters[] = [
                'teamId' => $id,
                'data' => $this->repository->getTeamRoosters($id)
            ];
        }

        return response()->json($roosters);
    }

    public function getPlayByPlay($contestId)
    {
        return response()->json([
            'data' => $this->playByPlayRepository->getPlayByPlay($contestId)
        ]);
    }

    public function getRankings($season)
    {
        return response()->json(
            $this->repository->getRankings($season)
        );
    }
}
