<?php

namespace App\Services\Afl\Utils\Traits;

use Illuminate\Support\Collection;
use App\Models\AflApiResponse;
use Carbon\Carbon;

trait ScheduleAnalysis
{
    /**
     * Get the next match schedule when there is no match today
     * 
     * @return Collection
     */
    public function getNextMatchSchedule(): Collection
    {
        $scheduleData = AflApiResponse::query()->getLatestSchedule()->first();

        if (!$scheduleData || empty($scheduleData->response)) {
            return collect();
        }

        return $this->getUpcomingMatches($scheduleData->response);
    }

    public function getScheduleByRound(string $round)
    {
        $scheduleData = AflApiResponse::query()->getScheduleByRound($round);

        if (!$scheduleData || empty($scheduleData->response)) {
            return collect();
        }

        if ($round == get_current_round()['round']) {
            return $this->getUpcomingMatches($scheduleData->response);
        }

        // @INFO: Load the previous matches
        dd($scheduleData->response);
    }

    /**
     * Get upcoming matches from schedule data
     * 
     * @param array $scheduleData
     * @return Collection
     */
    public function getUpcomingMatches(array $scheduleData): Collection
    {
        if (empty($scheduleData['results']['tournament']['round'])) {
            return collect();
        }

        $currentRoundNumber = (string) get_current_round()['round'];

        // Get all rounds data from the response, ensuring it's a collection
        $rounds = $scheduleData['results']['tournament']['round'];
        $allRounds = isset($rounds['@id']) ? collect([$rounds][0]['week']) : collect($rounds[0]['week']);

        $matches = $allRounds->firstWhere('@number', $currentRoundNumber);
        $round = (int) $matches['@number'] ?? $currentRoundNumber;

        return collect($matches['match'])->map(function ($match) use ($round) {
            return $this->formatScheduleData($match, $round);
        });
    }

    /**
     * Check if a round has any upcoming matches
     * 
     * @param array $round
     * @param Carbon $today
     * @return bool
     */
    protected function roundHasUpcomingMatches(array $round, Carbon $today): bool
    {
        if (empty($round['week'])) {
            return false;
        }

        $weeks = $this->normalizeToCollection($round['week']);

        return $weeks->contains(function ($week) use ($today) {
            if (empty($week['match'])) {
                return false;
            }

            $matches = $this->normalizeToCollection($week['match']);

            return $matches->contains(function ($match) use ($today) {
                return Carbon::parse($match['@date'])->greaterThanOrEqualTo($today)
                    && $match['@status'] === 'Not Started';
            });
        });
    }

    /**
     * Normalize data to a collection, handling both single items and arrays
     * 
     * @param mixed $data
     * @return Collection
     */
    protected function normalizeToCollection($data): Collection
    {
        if (empty($data)) {
            return collect();
        }

        // If it's a single item (has @id), wrap it in an array
        return isset($data['@id']) ? collect([$data]) : collect($data);
    }

    /**
     * Format schedule data to match the same structure as getTeamScores()
     * 
     * @param array $match
     * @return array
     */
    protected function formatScheduleData(array $match, int $round): array
    {
        $matchDate = Carbon::parse($match['@date']);
        $matchTime = \Carbon\Carbon::parse($match['@time']);

        return [
            'round' => $round,
            'match_id' => $match['@id'],
            'venue' => $match['@venue'],
            'date' => $matchDate->format('d.m.Y'),
            'time' => $matchTime->format('H:i'),
            'status' => $match['@status'],
            'home_team' => $match['localteam']['@name'],
            'home_score' => $match['localteam']['@score'],
            'away_team' => $match['visitorteam']['@name'],
            'away_score' => $match['visitorteam']['@score'],
            'total_score' => null,
            'margin' => null,
            'is_upcoming' => $match['@status'] === 'Not Started',
        ];
    }
}
