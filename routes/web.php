<?php

use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/test', function (\App\Services\Afl\AflService $service) {
    dump([
        // 'get_current_round()' => get_current_round(),
        // 'has_match_today()' => has_match_today(),
        // 'standings' => $service->getTeamStandings(),
        'scoreboard' => $service->getScoreboard()->toArray(),
        // 'schedules' => $service->getUpcomingSchedules(),
        'previous_match' => $service->getPreviousMatchData(),
        'current_match' => $service->getCurrentMatchData(),
    ]);
});

Route::get('/test-schedule', function (\App\Services\Afl\Utils\Analyzer $analyzer) {
    // dd(get_current_round());
    // return $analyzer->getNextMatchSchedule();
    $analyzer->hydrate($analyzer->getPreviousMatchData());
    dd($analyzer->getTeamScores());
});

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', Profile::class)->name('settings.profile');
    Route::get('settings/password', Password::class)->name('settings.password');
    Route::get('settings/appearance', Appearance::class)->name('settings.appearance');
});

require __DIR__ . '/auth.php';
