<?php

use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/test', function (\App\Services\Afl\AflService $service) {
    // Test July 22, 2025 case
    Carbon\Carbon::setTestNow(Carbon\Carbon::create(2025, 7, 22, 10, 0, 0, 'Australia/Sydney'));
    $result = get_current_round();
    echo "July 22, 2025 result: ";
    print_r($result);
    echo "\n\n";
    
    // Reset and test current time
    Carbon\Carbon::setTestNow(null);
    $result2 = get_current_round();
    echo "Current time result: ";
    print_r($result2);
    die;
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
