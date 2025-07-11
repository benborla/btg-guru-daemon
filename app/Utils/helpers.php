<?php

use Carbon\Carbon;

if (!function_exists('get_schedules')) {
    function get_schedules(): array
    {
        // Automate this: source should be this endpoint:
        // https://www.goalserve.com/getfeed/9645f122eef946c1c7bd08dd5ac0e712/afl/schedule
        return [
            ['start' => '2025-03-07', 'end' => '2025-03-09', 'round' => 'OR'],
            ['start' => '2025-03-13', 'end' => '2025-03-16', 'round' => 1],
            ['start' => '2025-03-20', 'end' => '2025-03-23', 'round' => 2],
            ['start' => '2025-03-27', 'end' => '2025-03-30', 'round' => 3],
            ['start' => '2025-04-03', 'end' => '2025-04-06', 'round' => 4],
            ['start' => '2025-04-10', 'end' => '2025-04-13', 'round' => 5],
            ['start' => '2025-04-17', 'end' => '2025-04-21', 'round' => 6],
            ['start' => '2025-04-24', 'end' => '2025-04-27', 'round' => 7],
            ['start' => '2025-05-01', 'end' => '2025-05-04', 'round' => 8],
            ['start' => '2025-05-08', 'end' => '2025-05-11', 'round' => 9],
            ['start' => '2025-05-15', 'end' => '2025-05-18', 'round' => 10],
            ['start' => '2025-05-22', 'end' => '2025-05-25', 'round' => 11],
            ['start' => '2025-05-29', 'end' => '2025-06-01', 'round' => 12],
            ['start' => '2025-06-05', 'end' => '2025-06-09', 'round' => 13],
            ['start' => '2025-06-12', 'end' => '2025-06-15', 'round' => 14],
            ['start' => '2025-06-19', 'end' => '2025-06-22', 'round' => 15],
            ['start' => '2025-06-26', 'end' => '2025-06-29', 'round' => 16],
            ['start' => '2025-07-03', 'end' => '2025-07-06', 'round' => 17],
            ['start' => '2025-07-10', 'end' => '2025-07-13', 'round' => 18],
            ['start' => '2025-07-17', 'end' => '2025-07-20', 'round' => 19],
            ['start' => '2025-07-24', 'end' => '2025-07-27', 'round' => 20],
            ['start' => '2025-07-31', 'end' => '2025-08-03', 'round' => 21],
            ['start' => '2025-08-07', 'end' => '2025-08-10', 'round' => 22],
            ['start' => '2025-08-15', 'end' => '2025-08-17', 'round' => 23],
            ['start' => '2025-08-22', 'end' => '2025-08-22', 'round' => 24]
        ];
    }
}

if (!function_exists('get_round_date')) {
    function get_round_date(Carbon $date): array
    {
        $rounds = get_schedules();

        $nextRound = null;
        $nextRoundDiff = null;
        $previousRound = null;

        foreach ($rounds as $key => $round) {
            $roundStart = Carbon::parse($round['start']);
            $roundEnd = Carbon::parse($round['end']);

            // Check if today is within this round's date range
            if ($date->isBetween($roundStart, $roundEnd)) {
                return $round;
            }

            // If today is before the round start, this could be the next round
            if ($date->lt($roundStart)) {
                $diff = $date->diffInSeconds($roundStart);

                // If we haven't found a next round yet, or this one is sooner
                if ($nextRound === null || $diff < $nextRoundDiff) {
                    $nextRound = $round;
                    $nextRoundDiff = $diff;
                }
            }

            // Keep track of the previous round (where end date is before the current date)
            if ($roundEnd->lt($date)) {
                // Update previous round if we haven't set it yet or if this round ends later
                if ($previousRound === null || Carbon::parse($previousRound['end'])->lt($roundEnd)) {
                    $previousRound = $round;
                }
            }
        }

        // If we're between rounds (after a round has ended but before the next one starts),
        // return the previous round instead of the next one
        if ($previousRound !== null) {
            return $previousRound;
        }

        // Return the next upcoming round, or empty array if no future rounds
        return $nextRound !== null ? $nextRound : [];
    }
}

if (!function_exists('get_current_round')) {
    function get_current_round(): array
    {
        return get_round_date(Carbon::now());
    }
}

if (!function_exists('get_schedule_by_round')) {
    function get_schedule_by_round(string $round): array
    {
        $schedules = get_schedules();

        foreach ($schedules as $schedule) {
            if ($schedule['round'] == $round) {
                // Format dates from YYYY-MM-DD to DD.MM.YYYY
                $startDate = Carbon::parse($schedule['start']);
                $endDate = Carbon::parse($schedule['end']);

                return [
                    'start' => $startDate->format('d.m.Y'),
                    'end' => $endDate->format('d.m.Y'),
                    'round' => $schedule['round']
                ];
            }
        }

        return [];
    }
}

if (!function_exists('has_match_today')) {
    function has_match_today(): bool
    {
        $round = get_current_round();
        $today = Carbon::now();

        // If we don't have a round, there's definitely no match today
        if (empty($round)) {
            return false;
        }

        // Check if today's date is within the round's date range
        $roundStart = Carbon::parse($round['start']);
        $roundEnd = Carbon::parse($round['end']);

        return $today->isBetween($roundStart, $roundEnd);
    }
}

if (!function_exists('has_live_match_ongoing')) {
    function has_live_match_ongoing()
    {
        $currentRound = get_current_round();

        if (empty($currentRound)) {
            return false;
        }

        // Get current date and time
        $now = Carbon::now();
        $currentDate = $now->format('Y-m-d');

        // First check for matches in the current round from the database
        $todayMatches = \App\Models\AflSchedule::byRound($currentRound['round'])
            ->whereDate('date', $currentDate)
            ->get();

        if ($todayMatches->isNotEmpty()) {
            // Check if any match is currently in progress based on time
            foreach ($todayMatches as $match) {
                // Parse match time and add estimated duration (3 hours for AFL match)
                $matchTime = Carbon::parse($match->date . ' ' . $match->time);
                $matchEndTime = (clone $matchTime)->addHours(3); // Typical AFL match duration

                // Check if current time is between match start and end time
                if ($now->between($matchTime, $matchEndTime)) {
                    return true;
                }
            }
        }

        // If no matches found in database or none are live, check the live API response
        $liveResponse = \App\Models\AflApiResponse::getLatestData();
        if ($liveResponse && !empty($liveResponse->response)) {
            $liveData = $liveResponse->response;

            // Check if we have the expected structure
            if (isset($liveData['scores']['category']['match'])) {
                $matches = $liveData['scores']['category']['match'];
                if (!isset($matches[0])) {
                    $matches = [$matches]; // Wrap single match in array
                }

                // Check if any match has a status indicating it's live
                foreach ($matches as $match) {
                    $status = $match['@status'] ?? '';

                    // These statuses indicate a live match
                    $liveStatuses = ['In Progress', '1st Quarter', '2nd Quarter', '3rd Quarter', '4th Quarter', 'Half Time', 'Quarter Time', 'Three Quarter Time'];

                    if (in_array($status, $liveStatuses)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}

function iterate_through_current_round_until_start()
{
    $round = get_current_round();
    $result = [];

    // Add rounds from current round down to 1 in descending order
    $currentRound = is_numeric($round['round']) ? $round['round'] : 24; // If current round is not numeric, use max round

    for ($i = $currentRound; $i >= 1; $i--) {
        $result[] = $i;
    }

    // Finally add the Opening Round (OR) as the last element
    $result[] = 'OR';

    return $result;
}

function isDevMode(): bool
{
    return app()->isLocal() || app()->isStaging();
}
