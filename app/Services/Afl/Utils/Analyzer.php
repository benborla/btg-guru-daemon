<?php

namespace App\Services\Afl\Utils;

use Illuminate\Support\Collection;
use App\Services\Afl\Utils\Traits\{
    MatchAnalysis,
    PlayerAnalysis,
    TeamAnalysis,
    EventAnalysis,
    ScheduleAnalysis
};

class Analyzer
{
    use MatchAnalysis, PlayerAnalysis, TeamAnalysis, EventAnalysis, ScheduleAnalysis;

    protected Collection $matches;
    protected array $rawData;
    private bool $hasHydrated;
    protected $weekRound;


    public function hydrate(array $apiResponse)
    {
        $this->rawData = $apiResponse;
        $this->matches = $this->extractMatches($apiResponse);
        $this->hasHydrated = true;
    }

    public function invalidateCall()
    {
        if (! $this->hasHydrated) {
            throw new \BadMethodCallException('Analyzer must be hydrated before calling this method');
        }
    }

    /**
     * Extract matches from the API response
     */
    protected function extractMatches(array $response): Collection
    {
        // Handle both single match and multiple matches
        if (isset($response['scores']['category']['match'])) {
            $matchData = $response['scores']['category']['match'];
            $this->weekRound = $response['scores']['category']['@week'];

            // If it's a single match, wrap it in an array
            if (isset($matchData['@id'])) {
                $matchData = [$matchData];
            }

            return collect($matchData);
        }

        return collect();
    }

    /**
     * Get all matches collection
     */
    public function getMatches(): Collection
    {
        return $this->matches;
    }

    /**
     * Get raw API data
     */
    public function getRawData(): array
    {
        return $this->rawData;
    }

    /**
     * Filter matches by status
     */
    public function filterByStatus(string $status): Collection
    {
        return $this->matches->where('@status', $status);
    }

    /**
     * Filter matches by venue
     */
    public function filterByVenue(string $venue): Collection
    {
        return $this->matches->where('@venue', $venue);
    }

    /**
     * Filter matches by date
     */
    public function filterByDate(string $date): Collection
    {
        return $this->matches->where('@date', $date);
    }

    /**
     * Get matches involving a specific team
     */
    public function getMatchesForTeam(string $teamName): Collection
    {
        return $this->matches->filter(function ($match) use ($teamName) {
            return $match['localteam']['@name'] === $teamName ||
                $match['visitorteam']['@name'] === $teamName;
        });
    }

    public function getHeadToHeadRecord(string $team1, string $team2): array
    {
        $h2hMatches = $this->matches->filter(function ($match) use ($team1, $team2) {
            $teams = [$match['localteam']['@name'], $match['visitorteam']['@name']];
            return in_array($team1, $teams) && in_array($team2, $teams);
        });

        if ($h2hMatches->isEmpty()) {
            return [
                'team1' => $team1,
                'team2' => $team2,
                'matches_played' => 0,
                'message' => 'No matches found between these teams'
            ];
        }

        $team1Wins = 0;
        $team2Wins = 0;
        $draws = 0;
        $team1TotalScore = 0;
        $team2TotalScore = 0;

        foreach ($h2hMatches as $match) {
            $homeTeam = $match['localteam']['@name'];
            $awayTeam = $match['visitorteam']['@name'];
            $homeScore = (int) $match['localteam']['@score'];
            $awayScore = (int) $match['visitorteam']['@score'];

            if ($homeTeam === $team1) {
                $team1TotalScore += $homeScore;
                $team2TotalScore += $awayScore;
                if ($homeScore > $awayScore) $team1Wins++;
                elseif ($awayScore > $homeScore) $team2Wins++;
                else $draws++;
            } else {
                $team1TotalScore += $awayScore;
                $team2TotalScore += $homeScore;
                if ($awayScore > $homeScore) $team1Wins++;
                elseif ($homeScore > $awayScore) $team2Wins++;
                else $draws++;
            }
        }

        return [
            'team1' => $team1,
            'team2' => $team2,
            'matches_played' => $h2hMatches->count(),
            'team1_wins' => $team1Wins,
            'team2_wins' => $team2Wins,
            'draws' => $draws,
            'team1_avg_score' => round($team1TotalScore / $h2hMatches->count(), 2),
            'team2_avg_score' => round($team2TotalScore / $h2hMatches->count(), 2),
            'matches' => $h2hMatches->values()
        ];
    }

    public function getallheadtoheadrecords(): collection
    {
        $teams = $this->getAllTeamNames();
        $h2hRecords = collect();
        $processedPairs = collect();

        foreach ($teams as $team1) {
            foreach ($teams as $team2) {
                if ($team1 === $team2) continue;

                // Create a sorted pair to avoid duplicates (A vs B same as B vs A)
                $pair = collect([$team1, $team2])->sort()->values()->implode('|');

                if ($processedPairs->contains($pair)) continue;

                $h2h = $this->getHeadToHeadRecord($team1, $team2);

                if ($h2h['matches_played'] > 0) {
                    $h2hRecords->push($h2h);
                }

                $processedPairs->push($pair);
            }
        }

        return $h2hRecords;
    }

    /**
     * Create a new instance with filtered data
     */
    public function where(string $key, $operator, $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $filtered = $this->matches->where($key, $operator, $value);

        $newInstance = clone $this;
        $newInstance->matches = $filtered;

        return $newInstance;
    }

    /**
     * Get match count
     */
    public function count(): int
    {
        return $this->matches->count();
    }

    /**
     * Check if analyzer has matches
     */
    public function hasMatches(): bool
    {
        return $this->matches->isNotEmpty();
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return $this->matches->toArray();
    }

    /**
     * Get first match
     */
    public function first()
    {
        return $this->matches->first();
    }

    /**
     * Apply custom filter
     */
    public function filter(callable $callback): self
    {
        $filtered = $this->matches->filter($callback);

        $newInstance = clone $this;
        $newInstance->matches = $filtered;

        return $newInstance;
    }
}
