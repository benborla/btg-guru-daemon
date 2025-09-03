<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\Nfl\NflScoresRepository;
use App\Repositories\Nfl\NflApiRepository;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class NflController extends Controller
{

    public function __construct(
        private NflScoresRepository $repository,
        private NflApiRepository $apiRepository
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


        $allTeams = $this->repository->getTeamsInfo($season);
        $teamInfo = $allTeams['AFC']->firstWhere('id', $teamId) ?? $allTeams['NFC']->firstWhere('id', $teamId);

        return response()->json([
            'teams' => $allTeams,
            'teamInfo' => $teamInfo ?? [],
            'seasonTypes' => $seasonTypes,
            'data' => $weeksData
        ]);
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

        return response()->json(
            $this->repository->getScores($date)
        );
    }

    public function schedules()
    {
        $week = request()->input('week');
        return response()->json(
            $this->repository->getSchedules($week)
        );
    }

    public function scoreboard()
    {
        $games = [];

        if ($this->repository->hasMatchToday()) {
            $games = $this->repository->getScoreBoardDataFromApi();
        } else {
            $games = $this->repository->getScoreBoardDataFromDb();
        }

        return response()->json($games);

    }

    public function hasMatchToday()
    {
        return response()->json(
            ['status' => $this->repository->hasMatchToday()]
        );
    }

}
