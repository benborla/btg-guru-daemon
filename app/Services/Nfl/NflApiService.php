<?php

namespace App\Services\Nfl;
// app/Services/NflApiService.php
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class NflApiService
{
    private const BASE_URL = 'goalserve.com/getfeed/9645f122eef946c1c7bd08dd5ac0e712/football/nfl-scores?json=1';
    private const CACHE_PREFIX = 'nfl_scores';

    public function __construct(
        private string $apiKey = '9645f122eef946c1c7bd08dd5ac0e712'
    ) {}

    /**
     * Get NFL scores with intelligent caching
     */
    public function getScores(?string $week = null, ?string $season = null): Collection
    {
        $cacheKey = $this->buildCacheKey($week, $season);
        $ttl = $this->determineCacheTtl();

        return Cache::remember($cacheKey, $ttl, function() use ($week, $season) {
            return $this->fetchFromApi($week, $season);
        });
    }

    /**
     * Force refresh scores (bypass cache)
     */
    public function refreshScores(?string $week = null, ?string $season = null): Collection
    {
        $cacheKey = $this->buildCacheKey($week, $season);

        // Clear existing cache
        Cache::forget($cacheKey);

        // Fetch fresh data
        $scores = $this->fetchFromApi($week, $season);

        // Cache the fresh data
        Cache::put($cacheKey, $scores, $this->determineCacheTtl());

        return $scores;
    }

    /**
     * Get live games only (ultra-short cache for active games)
     */
    public function getLiveScores(): Collection
    {
        $cacheKey = self::CACHE_PREFIX . '_live';

        return Cache::remember($cacheKey, 15, function() { // 15 seconds for live games
            return $this->fetchFromApi()
                ->filter(fn($game) => $this->isGameLive($game));
        });
    }

    /**
     * Get real-time scores (5-10 second cache)
     */
    public function getRealTimeScores(): Collection
    {
        $cacheKey = self::CACHE_PREFIX . '_realtime';

        return Cache::remember($cacheKey, 5, function() { // 5 seconds only
            return $this->fetchFromApi()
                ->filter(fn($game) => $this->isGameLive($game));
        });
    }

    /**
     * Get scores with dynamic TTL based on game situation
     */
    public function getScoresWithDynamicTtl(): Collection
    {
        $cacheKey = self::CACHE_PREFIX . '_dynamic';
        $ttl = $this->calculateDynamicTtl();

        return Cache::remember($cacheKey, $ttl, function() {
            return $this->fetchFromApi();
        });
    }

    /**
     * Cache with tags for selective invalidation
     */
    public function getScoresWithTags(?string $week = null): Collection
    {
        $cacheKey = $this->buildCacheKey($week);
        $tags = ['nfl', 'scores', "week_{$week}"];

        return Cache::tags($tags)->remember($cacheKey, 300, function() use ($week) {
            return $this->fetchFromApi($week);
        });
    }

    /**
     * Invalidate cache by tags
     */
    public function invalidateWeek(string $week): void
    {
        Cache::tags(["week_{$week}"])->flush();
    }

    /**
     * Fetch data from API with error handling
     */
    private function fetchFromApi(?string $week = null, ?string $season = null): Collection
    {
        try {
            $response = Http::timeout(10)
                ->retry(3, 1000) // Retry 3 times with 1 second delay
                ->get(self::BASE_URL, [
                    'json' => 1,
                    /* 'week' => $week, */
                    /* 'season' => $season ?? date('Y'), */
                ]);

            if ($response->successful()) {
                $data = $response->json();

                // Log successful API call
                Log::info('NFL API call successful', [
                    /* 'week' => $week, */
                    'games_count' => count($data['scores'] ?? [])
                ]);

                return collect($data['scores'] ?? []);
            }

            throw new \Exception("API returned status: {$response->status()}");

        } catch (\Exception $e) {
            Log::error('NFL API call failed', [
                'error' => $e->getMessage(),
                'week' => $week,
                'season' => $season
            ]);

            // Return cached data as fallback
            return $this->getFallbackData($week, $season);
        }
    }

    /**
     * Build cache key based on parameters
     */
    private function buildCacheKey(?string $week = null, ?string $season = null): string
    {
        $parts = [self::CACHE_PREFIX];

        if ($week) $parts[] = "week_{$week}";
        if ($season) $parts[] = "season_{$season}";

        return implode('_', $parts);
    }

    /**
     * Determine cache TTL based on game status and time
     */
    private function determineCacheTtl(): int
    {
        $now = Carbon::now();

        // During NFL season (Sep-Feb), shorter cache
        if ($now->month >= 9 || $now->month <= 2) {
            // Game days (Thursday, Sunday, Monday)
            if (in_array($now->dayOfWeek, [0, 1, 4])) { // Sun=0, Mon=1, Thu=4
                return 300; // 5 minutes on game days
            }
            return 1800; // 30 minutes during season
        }

        // Off-season: longer cache
        return 3600; // 1 hour
    }

    /**
     * Calculate dynamic TTL based on current game situations
     */
    private function calculateDynamicTtl(): int
    {
        // Check if any games are currently live
        $hasLiveGames = $this->hasLiveGames();

        if ($hasLiveGames) {
            $now = Carbon::now();
            $dayOfWeek = $now->dayOfWeek;
            $hour = $now->hour;

            // Prime time games (8-11 PM ET) - most viewers
            if ($hour >= 20 && $hour <= 23) {
                return 5; // 5 seconds during prime time
            }

            // Regular game hours (1-8 PM ET)
            if ($hour >= 13 && $hour <= 20) {
                return 10; // 10 seconds during regular hours
            }

            // Late night games or overtime
            return 15; // 15 seconds otherwise
        }

        // No live games - use standard caching
        return $this->determineCacheTtl();
    }

    /**
     * Check if there are currently live games
     */
    private function hasLiveGames(): bool
    {
        // Quick check from existing cache first to avoid API call
        $cachedScores = Cache::get(self::CACHE_PREFIX . '_live');

        if ($cachedScores) {
            return collect($cachedScores)->contains(fn($game) => $this->isGameLive($game));
        }

        // If no cache, assume potential live games during game hours
        $now = Carbon::now();
        $isGameDay = in_array($now->dayOfWeek, [0, 1, 4]); // Sun, Mon, Thu
        $isGameHours = $now->hour >= 13 && $now->hour <= 23;

        return $isGameDay && $isGameHours;
    }

    /**
     * Check if game is currently live with more granular statuses
     */
    private function isGameLive(array $game): bool
    {
        $liveStatuses = [
            '1st', '2nd', '3rd', '4th', 'OT', 'OT2', 'OT3', // Game periods
            'Half', 'Halftime', 'Break', // Intermissions
            '2 minute warning', 'Under review', 'Timeout', // Special situations
            'Red zone', 'Goal line', // Critical moments (if API provides)
        ];

        return in_array($game['status'] ?? '', $liveStatuses);
    }

    /**
     * Check if game is in critical moment (needs fastest updates)
     */
    private function isGameCritical(array $game): bool
    {
        $status = $game['status'] ?? '';
        $timeLeft = $game['time_left'] ?? '';

        $criticalStatuses = [
            '4th', 'OT', 'OT2', 'OT3', // Final quarter and overtime
            '2 minute warning', 'Under review', 'Red zone'
        ];

        // Critical if in final quarter with little time left
        if ($status === '4th' && $timeLeft) {
            preg_match('/(\d+):(\d+)/', $timeLeft, $matches);
            if ($matches && $matches[1] <= 2) { // 2 minutes or less
                return true;
            }
        }

        return in_array($status, $criticalStatuses);
    }

    /**
     * Get fallback data from cache or empty collection
     */
    private function getFallbackData(?string $week, ?string $season): Collection
    {
        // Try to get stale cache data
        $cacheKey = $this->buildCacheKey($week, $season) . '_fallback';
        $fallback = Cache::get($cacheKey);

        if ($fallback) {
            Log::info('Using fallback cache data');
            return collect($fallback);
        }

        return collect([]);
    }

    /**
     * Warm up cache for upcoming week
     */
    public function warmupCache(int $week): void
    {
        $this->getScores((string)$week);
        Log::info("Cache warmed up for week {$week}");
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        $keys = [
            'current_week' => $this->buildCacheKey((string)$this->getCurrentWeek()),
            'live' => self::CACHE_PREFIX . '_live',
        ];

        $stats = [];
        foreach ($keys as $name => $key) {
            $stats[$name] = [
                'exists' => Cache::has($key),
                'ttl' => Cache::store()->getRedis()->ttl($key) ?? null,
            ];
        }

        return $stats;
    }

    private function getCurrentWeek(): int
    {
        // Simple week calculation - you might want to make this more sophisticated
        return (int) Carbon::now()->weekOfYear;
    }
}
