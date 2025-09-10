<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NflController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AflController;
use App\Events\AflDataUpdate;
use App\Models\AflApiResponse;
use Illuminate\Support\Facades\Route;

// WebSocket test endpoint
Route::post('/test-broadcast', function () {
    $latestData = AflApiResponse::query()->orderBy('updated_at', 'desc')->first();

    if (!$latestData) {
        return response()->json([
            'status' => 'error',
            'message' => 'No AFL data found to broadcast'
        ], 404);
    }

    event(new AflDataUpdate($latestData));

    return response()->json([
        'status' => 'success',
        'message' => 'Test broadcast sent',
        'data' => [
            'id' => $latestData->id,
            'timestamp' => now()->toIso8601String()
        ]
    ]);
});

Route::prefix('v1')->group(function () {
    // Public routes
    Route::get('/health', function () {
        return response()->json(['status' => 'ok']);
    });

    Route::prefix('afl')->group(function () {
        Route::get('/has-match-today', [AflController::class, 'hasMatchToday']);
        Route::get('/scoreboard', [AflController::class, 'scoreboard']);
        Route::get('/standing', [AflController::class, 'standing']);
        Route::get('/schedules', [AflController::class, 'schedules']);
        Route::get('/history-schedules', [AflController::class, 'historySchedules']);
        Route::get('/live-match-feed', [AflController::class, 'liveMatchDataFeed']);
        Route::get('/match-data/{round}/{matchId}', [AflController::class, 'getMatchData']);
        Route::get('/test', [AflController::class, 'liveTest']);
        Route::get('/teams', [AflController::class, 'teamsInfo']);
    });

    Route::prefix('nfl')->group(function () {
        Route::get('/scoreboard', [NflController::class, 'scoreboard']);
        /* Route::get('/standing', [AflController::class, 'standing']); */
        /* Route::get('/schedules', [AflController::class, 'schedules']); */
        /* Route::get('/live-match-feed', [AflController::class, 'liveMatchDataFeed']); */
        /* Route::get('/match-data/{round}/{matchId}', [AflController::class, 'getMatchData']); */
        /* Route::get('/test', [AflController::class, 'liveTest']); */
        Route::get('/teams', [NflController::class, 'teamsInfo']);
        Route::get('/team-schedule', [NflController::class, 'teamSchedule']);
        Route::get('/team-standings/season/{season}/team-id/{teamId}', [NflController::class, 'teamStandings']);
        Route::get('/scores', [NflController::class, 'scores']);
        Route::get('/schedules', [NflController::class, 'schedules']);
        Route::get('/has-match-today', [NflController::class, 'hasMatchToday']);
        Route::get('/current-week', [NflController::class, 'currentWeek']);
        Route::get('/match-cast-box/{matchId}/{week}', [NflController::class, 'matchCastBox']);
        Route::get('/roosters/{teamId}', [NflController::class, 'getRoosters']);
    });

    // Auth routes
    Route::post('/login', [AuthController::class, 'login']);

    // @TODO: Add a possibility to add a round here
    Route::get('/live/afl', [AflController::class, 'index']);
    Route::get('/live/afl/scoreboard/{round?}', [AflController::class, 'scoreboard']);
    Route::get('/live/afl/match/h2h', [AflController::class, 'headToHead']);
    Route::get('/live/afl/match/summary', [AflController::class, 'summary']);
    // Route::get('/live/afl/{id}', [AflController::class, 'show']);

    // Protected routes (require authentication)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);

        // User routes
        Route::get('/user/profile', [UserController::class, 'profile']);
        Route::get('/user/subscription', [UserController::class, 'subscription']);
    });
});
