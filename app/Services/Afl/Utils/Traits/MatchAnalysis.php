<?php

namespace App\Services\Afl\Utils\Traits;

use Illuminate\Support\Collection;

trait MatchAnalysis
{
    public function getCurrentMatchData()
    {
        if (empty($this->matches)) {
            return null;
        }

        if ($this->matches->count() === 1) {
            return $this->restructureMatchData($this->matches->first());
        }

        // Get current time in AEST timezone
        $now = now()->setTimezone('Australia/Sydney');

        // First priority: Find any match that is currently in progress
        foreach ($this->matches as $match) {
            // Check for matches that are in progress (not 'Full Time' and not 'Not Started')
            if ($match['@status'] !== 'Full Time' && $match['@status'] !== 'Not Started') {
                // This is a match in progress (1st Qtr, 2nd Qtr, etc.)
                return $this->restructureMatchData($match);
            }
        }

        // Second priority: Find the next upcoming match
        foreach ($this->matches as $match) {
            // Parse match date and time in AEST
            $matchDateTime = \Carbon\Carbon::createFromFormat(
                'd.m.Y H:i',
                $match['@date'] . ' ' . $match['@time'],
                'Australia/Sydney'
            );

            // If match is not started and is in the future
            if ($match['@status'] === 'Not Started' && $matchDateTime->greaterThanOrEqualTo($now)) {
                return $this->restructureMatchData($match);
            }
        }

        // If no upcoming matches found, return the last match in the list
        $lastMatch = end($this->matches) ?: null;
        if ($lastMatch) {
            return $this->restructureMatchData($lastMatch);
        }
        return null;
    }

    /**
     * Helper method to restructure match data consistently
     *
     * @param array $match The match data to restructure
     * @return array The restructured match data
     */
    protected function restructureMatchData($match)
    {
        // Restructure events if they exist
        if (isset($match['events'])) {
            $match['events'] = $this->restructureEventsByPeriod($match['events']);
        }

        // Restructure lineups if they exist
        if (isset($match['lineups'])) {
            $match['lineups'] = $this->restructureLineups($match['lineups']);
        }

        // Restructure quarters if they exist
        if (isset($match['quarters'])) {
            $match['quarters'] = $this->restructureQuarters($match['quarters'], $match);
        }

        return $match;
    }

    /**
     * Get the most recent completed match data
     *
     * @return array|null The previous match data or null if none found
     */
    public function getPreviousMatchData()
    {
        if (empty($this->matches)) {
            return null;
        }

        // Get current time in AEST timezone
        $now = now()->setTimezone('Australia/Sydney');
        $previousMatch = null;

        // Find the most recent completed match
        foreach ($this->matches as $match) {
            // Parse match date and time in AEST
            $matchDateTime = \Carbon\Carbon::createFromFormat(
                'd.m.Y H:i',
                $match['@date'] . ' ' . $match['@time'],
                'Australia/Sydney'
            );

            // If match is completed (Full Time) and in the past
            if ($match['@status'] === 'Full Time' && $matchDateTime->lessThan($now)) {
                // Keep track of the most recent completed match
                if (!$previousMatch) {
                    $previousMatch = $match;
                } else {
                    $previousMatchDateTime = \Carbon\Carbon::createFromFormat(
                        'd.m.Y H:i',
                        $previousMatch['@date'] . ' ' . $previousMatch['@time'],
                        'Australia/Sydney'
                    );

                    // Update if this match is more recent than our current previous match
                    if ($matchDateTime->greaterThan($previousMatchDateTime)) {
                        $previousMatch = $match;
                    }
                }
            }
        }

        // Restructure data if we have a match
        if ($previousMatch) {
            $previousMatch = $this->restructureMatchData($previousMatch);
        }

        return $previousMatch;
    }

    /**
     * Restructure events by period to make them more accessible
     *
     * @param array $events The original events array
     * @return array The restructured events array with period as keys
     */
    protected function restructureEventsByPeriod($events)
    {
        // If events is empty or doesn't have the expected structure, return as is
        if (empty($events) || !isset($events['event']) || !is_array($events['event'])) {
            return $events;
        }

        $restructuredEvents = [];

        // Loop through all events and organize them by period
        foreach ($events['event'] as $event) {
            if (isset($event['@period'])) {
                $period = $event['@period'];
                $restructuredEvents[$period][] = $event;
            }
        }

        return $restructuredEvents;
    }

    /**
     * Restructure lineups to make them more accessible
     *
     * @param array $lineups The original lineups array
     * @return array The restructured lineups array with team types as keys
     */
    protected function restructureLineups($lineups)
    {
        // If lineups is empty or doesn't have the expected structure, return as is
        if (empty($lineups) || !isset($lineups['lineup']) || !is_array($lineups['lineup'])) {
            return $lineups;
        }

        $restructuredLineups = [];

        // Loop through lineup entries and organize by team type
        foreach ($lineups['lineup'] as $lineup) {
            if (isset($lineup['@team']) && isset($lineup['player'])) {
                $teamType = $lineup['@team'];
                $restructuredLineups[$teamType] = $lineup['player'];
            }
        }

        return $restructuredLineups;
    }

    /**
     * Restructure quarters data to make it more accessible and add additional information
     *
     * @param array $quarters The original quarters array
     * @param array $match The full match data containing team information
     * @return array The restructured quarters array
     */
    protected function restructureQuarters($quarters, $match)
    {
        // If quarters is empty or doesn't have the expected structure, return as is
        if (empty($quarters) || !isset($quarters['quarter'])) {
            return $quarters;
        }

        // Get team names
        $homeTeamName = $match['localteam']['@name'] ?? 'Home Team';
        $awayTeamName = $match['visitorteam']['@name'] ?? 'Away Team';

        $restructuredQuarters = [];

        // Handle both array and single item cases
        $quarterData = $quarters['quarter'];

        // If it's a single quarter (not in an array), convert it to an array
        if (!isset($quarterData[0]) && is_array($quarterData)) {
            $quarterData = [$quarterData];
        }

        // Make sure we have an array to iterate
        if (!is_array($quarterData)) {
            return $quarters;
        }

        // Loop through quarters and add additional information
        foreach ($quarterData as $quarter) {
            if (!isset($quarter['@name'])) {
                continue;
            }

            $quarterNumber = $quarter['@name'];

            // Add team names and formatted stats
            $quarter['@homeTeam'] = $homeTeamName;
            $quarter['@awayTeam'] = $awayTeamName;

            // Format stats as goals.behinds
            if (isset($quarter['@homeGoals']) && isset($quarter['@homeBehinds'])) {
                $quarter['@homeStats'] = $quarter['@homeGoals'] . '.' . $quarter['@homeBehinds'];
            }

            if (isset($quarter['@awayGoals']) && isset($quarter['@awayBehinds'])) {
                $quarter['@awayStats'] = $quarter['@awayGoals'] . '.' . $quarter['@awayBehinds'];
            }

            $restructuredQuarters[$quarterNumber] = $quarter;
        }

        return $restructuredQuarters;
    }
    /**
     * Get all team scores with match details, usually used in showing
     *rscoreboard
     */
    public function getTeamScores(): Collection
    {
        return $this->matches->map(function ($match) {
            $homeScore = (int) $match['localteam']['@score'];
            $awayScore = (int) $match['visitorteam']['@score'];

            $matchDate = \Carbon\Carbon::parse($match['@date']);
            $matchTime = \Carbon\Carbon::parse($match['@time']);

            return [
                'match_id' => $match['@id'],
                'venue' => $match['@venue'],
                'date' => $matchDate->format('d.m.Y'),
                'time' => $matchTime->format('H:i'),
                'status' => $match['@status'],
                'home_team' => $match['localteam']['@name'],
                'home_team_id' => $match['localteam']['@id'],
                'home_score' => $homeScore,
                'away_team' => $match['visitorteam']['@name'],
                'away_team_id' => $match['visitorteam']['@id'],
                'away_score' => $awayScore,
                'total_score' => $homeScore + $awayScore,
                'margin' => abs($homeScore - $awayScore),
                // 'round' => $this->weekRound,
                // @INFO: To immprove, this should be coming from the response data
                'round' => get_current_round()['round'],
                'winner' => $homeScore > $awayScore ? $match['localteam']['@name'] : $match['visitorteam']['@name'],
                'home_goals' => (int) $match['localteam']['@goals'],
                'home_behinds' => (int) $match['localteam']['@behinds'],
                'away_goals' => (int) $match['visitorteam']['@goals'],
                'away_behinds' => (int) $match['visitorteam']['@behinds'],
            ];
        });
    }

    /**
     * Get highest scoring matches
     */
    public function getHighestScoringMatches(): Collection
    {
        return $this->getTeamScores()->sortByDesc('total_score');
    }

    /**
     * Get matches with biggest winning margins
     */
    public function getBiggestWins(): Collection
    {
        return $this->getTeamScores()->sortByDesc('margin');
    }

    /**
     * Get closest matches (smallest margins)
     */
    public function getClosestMatches(): Collection
    {
        return $this->getTeamScores()->sortBy('margin');
    }

    /**
     * Get match data by specific match ID
     *
     * @param string $matchId The match ID to find
     * @return array|null The match data or null if not found
     */
    public function getMatchDataById(string $matchId): ?array
    {
        if (empty($this->matches)) {
            return null;
        }

        // Find the specific match with the given ID
        $match = $this->matches->first(function ($match) use ($matchId) {
            return $match['@id'] === $matchId;
        });

        if (!$match) {
            return null;
        }

        return $this->restructureMatchData($match);
    }

    /**
     * Get quarter-by-quarter breakdown for all matches
     */
    public function getQuarterBreakdown(): Collection
    {
        return $this->matches->map(function ($match) {
            return [
                'match' => $match['localteam']['@name'] . ' vs ' . $match['visitorteam']['@name'],
                'match_id' => $match['@id'],
                'quarters' => collect($match['quarters']['quarter'])->map(function ($quarter) {
                    return [
                        'quarter' => $quarter['@name'],
                        'home_goals' => (int) $quarter['@homeGoals'],
                        'home_behinds' => (int) $quarter['@homeBehinds'],
                        'home_points' => (int) $quarter['@homePoints'],
                        'away_goals' => (int) $quarter['@awayGoals'],
                        'away_behinds' => (int) $quarter['@awayBehinds'],
                        'away_points' => (int) $quarter['@awayPoints'],
                        'home_quarter_score' => (int) $quarter['@homePoints'] -
                            (isset($prevQuarter) ? (int) $prevQuarter['@homePoints'] : 0),
                        'away_quarter_score' => (int) $quarter['@awayPoints'] -
                            (isset($prevQuarter) ? (int) $prevQuarter['@awayPoints'] : 0),
                    ];
                })
            ];
        });
    }

    /**
     * Get matches by status
     */
    public function getFullTimeMatches(): Collection
    {
        return $this->filterByStatus('Full Time');
    }

    /**
     * Get live matches
     */
    public function getLiveMatches(): Collection
    {
        return $this->matches->filter(function ($match) {
            return $match['@status'] !== 'Full Time';
        });
    }

    /**
     * Get matches by venue with statistics
     */
    public function getMatchesByVenue(): Collection
    {
        return $this->matches->groupBy('@venue')->map(function ($venueMatches, $venue) {
            $scores = $venueMatches->map(function ($match) {
                return (int) $match['localteam']['@score'] + (int) $match['visitorteam']['@score'];
            });

            return [
                'venue' => $venue,
                'match_count' => $venueMatches->count(),
                'average_total_score' => round($scores->avg(), 2),
                'highest_score' => $scores->max(),
                'lowest_score' => $scores->min(),
                'matches' => $venueMatches->values()
            ];
        });
    }

    /**
     * Get match results summary
     */
    public function getMatchSummary(): array
    {
        $teamScores = $this->getTeamScores();

        return [
            'total_matches' => $this->matches->count(),
            'completed_matches' => $this->getFullTimeMatches()->count(),
            'live_matches' => $this->getLiveMatches()->count(),
            'average_total_score' => round($teamScores->avg('total_score'), 2),
            'highest_total_score' => $teamScores->max('total_score'),
            'average_margin' => round($teamScores->avg('margin'), 2),
            'biggest_margin' => $teamScores->max('margin'),
            'venues_count' => $this->matches->pluck('@venue')->unique()->count(),
        ];
    }
}
