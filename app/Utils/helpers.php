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
            $roundStart = Carbon::parse($round['start'], 'Australia/Sydney');
            $roundEnd = Carbon::parse($round['end'], 'Australia/Sydney');

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
        // Get the current round based on today's date
        $now = Carbon::now('Australia/Sydney');
        $rounds = get_schedules();
        
        // First check if today falls within any round's date range
        foreach ($rounds as $round) {
            $roundStart = Carbon::parse($round['start'], 'Australia/Sydney');
            $roundEnd = Carbon::parse($round['end'], 'Australia/Sydney');
            
            // If today is within this round's date range, this is the current round
            if ($now->between($roundStart, $roundEnd)) {
                return $round;
            }
        }
        
        // If we're not within any round's date range, find the next upcoming round
        $nextRound = null;
        $nextRoundDiff = null;
        
        foreach ($rounds as $round) {
            $roundStart = Carbon::parse($round['start'], 'Australia/Sydney');
            
            // If the round start is in the future, it's a candidate for next round
            if ($now->lt($roundStart)) {
                $diff = $now->diffInSeconds($roundStart);
                
                // If we haven't found a next round yet, or this one is sooner
                if ($nextRound === null || $diff < $nextRoundDiff) {
                    $nextRound = $round;
                    $nextRoundDiff = $diff;
                }
            }
        }
        
        // Return the next upcoming round, or empty array if no future rounds
        return $nextRound !== null ? $nextRound : [];
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
        $today = Carbon::now('Australia/Sydney');

        // If we don't have a round, there's definitely no match today
        if (empty($round)) {
            return false;
        }

        // Check if today's date is within the round's date range
        $roundStart = Carbon::parse($round['start'], 'Australia/Sydney');
        $roundEnd = Carbon::parse($round['end'], 'Australia/Sydney');

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
        $now = Carbon::now('Australia/Sydney');
        
        // Format current date in dd.mm.YYYY format to match database format
        $currentDate = $now->format('d.m.Y');

        // First check for matches in the current round from the database
        $todayMatches = \App\Models\AflSchedule::byRound($currentRound['round'])
            ->where('date', $currentDate)
            ->get();

        if ($todayMatches->isNotEmpty()) {
            // Check if any match is currently in progress based on time
            foreach ($todayMatches as $match) {
                // Parse match time and add estimated duration (3 hours for AFL match)
                $matchTime = Carbon::parse($match->date . ' ' . $match->time, 'Australia/Sydney');
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

if (!function_exists('get_time_until_next_match')) {
    /**
     * Get the time until the next AFL match
     * 
     * @return array|null Returns an array with 'hours' and 'minutes' until next match, or null if no upcoming match found
     */
    function get_time_until_next_match()
    {
        $currentRound = get_current_round();

        if (empty($currentRound)) {
            return null;
        }

        $now = Carbon::now('Australia/Sydney');
        $nextMatch = null;

        // First check today's matches in the current round
        // Format current date in dd.mm.YYYY format to match database format
        $currentDate = $now->format('d.m.Y');
        $todayMatches = \App\Models\AflSchedule::byRound($currentRound['round'])
            ->where('date', $currentDate)
            ->get();

        // Find the next match today that hasn't started yet
        foreach ($todayMatches as $match) {
            $matchTime = Carbon::parse($match->date . ' ' . $match->time, 'Australia/Sydney');

            if ($matchTime->gt($now)) {
                if ($nextMatch === null || $matchTime->lt(Carbon::parse($nextMatch->date . ' ' . $nextMatch->time, 'Australia/Sydney'))) {
                    $nextMatch = $match;
                }
            }
        }

        // If no match found today, look for future matches in the current round
        if ($nextMatch === null) {
            // We need a custom approach since the dates are in dd.mm.YYYY format
            // Get all matches for the current round
            $allMatches = \App\Models\AflSchedule::byRound($currentRound['round'])->get();
            
            // Filter for future matches by parsing the dates
            $futureMatches = $allMatches->filter(function($match) use ($now) {
                // Convert dd.mm.YYYY to Carbon date for comparison
                $dateParts = explode('.', $match->date);
                if (count($dateParts) === 3) {
                    $matchDate = Carbon::createFromFormat('d.m.Y H:i', 
                        $dateParts[0] . '.' . $dateParts[1] . '.' . $dateParts[2] . ' ' . $match->time, 'Australia/Sydney');
                    return $matchDate->gt($now);
                }
                return false;
            })->sortBy(function($match) {
                // Sort by parsed date and time
                $dateParts = explode('.', $match->date);
                return $dateParts[2] . '-' . $dateParts[1] . '-' . $dateParts[0] . ' ' . $match->time;
            })->first();

            if ($futureMatches) {
                $nextMatch = $futureMatches;
            }
        }

        // If still no match found, check the live API response for upcoming matches
        if ($nextMatch === null) {
            $liveResponse = \App\Models\AflApiResponse::getLatestData();

            if ($liveResponse && !empty($liveResponse->response)) {
                $liveData = $liveResponse->response;

                // Check if we have the expected structure
                if (isset($liveData['scores']['category']['match'])) {
                    $matches = $liveData['scores']['category']['match'];
                    if (!isset($matches[0])) {
                        $matches = [$matches]; // Wrap single match in array
                    }

                    $earliestUpcomingMatch = null;
                    $earliestMatchTime = null;

                    foreach ($matches as $match) {
                        $status = $match['@status'] ?? '';
                        $date = $match['@date'] ?? '';
                        $time = $match['@time'] ?? '';

                        // Only consider matches that haven't started yet
                        if ($status === 'NS' || $status === 'Not Started') {
                            // Convert dd.mm.YYYY format to YYYY-mm-dd
                            if (strpos($date, '.') !== false) {
                                $dateParts = explode('.', $date);
                                if (count($dateParts) === 3) {
                                    $formattedDate = $dateParts[2] . '-' . $dateParts[1] . '-' . $dateParts[0];

                                    try {
                                        $matchTime = Carbon::parse($formattedDate . ' ' . $time, 'Australia/Sydney');

                                        if ($matchTime->gt($now)) {
                                            if ($earliestMatchTime === null || $matchTime->lt($earliestMatchTime)) {
                                                $earliestUpcomingMatch = $match;
                                                $earliestMatchTime = $matchTime;
                                            }
                                        }
                                    } catch (\Exception $e) {
                                        // Skip this match if date parsing fails
                                        continue;
                                    }
                                }
                            }
                        }
                    }

                    // If we found an upcoming match in the live data
                    if ($earliestMatchTime !== null) {
                        $diffInMinutes = $now->diffInMinutes($earliestMatchTime, false);
                        $hours = floor($diffInMinutes / 60);
                        $minutes = $diffInMinutes % 60;

                        return [
                            'hours' => $hours,
                            'minutes' => $minutes,
                            'match_time' => $earliestMatchTime->format('Y-m-d H:i:s'),
                            'source' => 'live_api'
                        ];
                    }
                }
            }
        }

        // Calculate time difference if we found a next match
        if ($nextMatch !== null) {
            $matchTime = Carbon::parse($nextMatch->date . ' ' . $nextMatch->time, 'Australia/Sydney');
            $diffInMinutes = $now->diffInMinutes($matchTime, false);
            $hours = floor($diffInMinutes / 60);
            $minutes = $diffInMinutes % 60;

            return [
                'hours' => $hours,
                'minutes' => $minutes,
                'match_time' => $matchTime->format('Y-m-d H:i:s'),
                'source' => 'database'
            ];
        }

        return null;
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

if (!function_exists('process_schedule_data')) {
    /**
     * Process schedule data into formatted match data
     *
     * @param \Illuminate\Support\Collection $scheduleData
     * @param \App\Services\Afl\AflService $aflService
     * @return array
     */
    function process_schedule_data($scheduleData, $aflService): array
    {
        return $scheduleData
            ->map(function ($match) use ($aflService) {
                // Extract team data from the JSON structure
                $localTeam = $match->local_team;
                $visitorTeam = $match->visitor_team;
                $matchData = \App\Models\AflApiResponse::findByMatchData($match->match_id, $match->round)->first();
                $aflService->hydrate($matchData);
                $matchDetails = $aflService->getMatchDataById($match->match_id);

                // Get the base data directly from the model
                $baseData = [
                    'category' => 'AFL Premiership',
                    'week' => (string)$match->round,
                    'match_id' => $match->match_id,
                    'status' => $match->status,
                    'date' => $match->date,
                    'time' => $match->time,
                    'venue' => $match->venue,
                ];

                // Add team data
                return [
                    ...$baseData,
                    'localteam' => $localTeam,
                    'visitorteam' => $visitorTeam,
                    'quarters' => $matchDetails['quarters'] ?? [],
                    'events' => $matchDetails['events'] ?? [],
                    'lineups' => $matchDetails['lineups'] ?? []
                ];
            })
            ->values()
            ->all();
    }
}

if (!function_exists('process_match_data')) {
    /**
     * Process match data for a specific match ID
     *
     * @param \App\Models\AflApiResponse $data
     * @param string $matchId
     * @param \App\Services\Afl\AflService $aflService
     * @return array
     */
    function process_match_data($data, string $matchId, $aflService): array
    {
        $aflService->hydrate($data);
        // Use the getMatchDataById method to get the exact match by ID
        $structured = $aflService->getMatchDataById($matchId);

        // If the specific match wasn't found in the data, fall back to current match data
        if (!$structured) {
            $structured = $aflService->getCurrentMatchData();
        }

        return [
            'match_date' => $data->match_date,
            'source' => 'proxy_server',
            'round' => $data->round,
            ...$structured
        ];
    }
}

if (!function_exists('format_live_matches')) {
    /**
     * Format live matches from the AFL API response
     *
     * @param array $liveData
     * @return array
     */
    function format_live_matches($liveData): array
    {
        // Check if we have the expected structure
        if (!isset($liveData['scores']['category']['match'])) {
            return [];
        }

        // Get matches array, ensuring it's always an array even if there's only one match
        $matches = $liveData['scores']['category']['match'];
        if (!isset($matches[0])) {
            $matches = [$matches]; // Wrap single match in array
        }

        // Format each match
        $formattedMatches = [];
        foreach ($matches as $match) {
            // Extract attributes with @ prefix
            $matchId = $match['@id'] ?? '';
            $status = $match['@status'] ?? 'NS';
            $date = $match['@date'] ?? '';
            $time = $match['@time'] ?? '';
            $venue = $match['@venue'] ?? '';
            $week = $liveData['scores']['category']['@week'] ?? '';

            // Extract team data
            $localTeam = [];
            if (isset($match['localteam'])) {
                $localTeam = [
                    'name' => $match['localteam']['@name'] ?? '',
                    'score' => (string)($match['localteam']['@score'] ?? '0'),
                    'goals' => (string)($match['localteam']['@goals'] ?? '0'),
                    'behinds' => (string)($match['localteam']['@behinds'] ?? '0'),
                    'psgoals' => (string)($match['localteam']['@psgoals'] ?? ''),
                    'psbehinds' => (string)($match['localteam']['@psbehinds'] ?? ''),
                    'id' => $match['localteam']['@id'] ?? ''
                ];
            }

            $visitorTeam = [];
            if (isset($match['visitorteam'])) {
                $visitorTeam = [
                    'name' => $match['visitorteam']['@name'] ?? '',
                    'score' => (string)($match['visitorteam']['@score'] ?? '0'),
                    'goals' => (string)($match['visitorteam']['@goals'] ?? '0'),
                    'behinds' => (string)($match['visitorteam']['@behinds'] ?? '0'),
                    'psgoals' => (string)($match['visitorteam']['@psgoals'] ?? ''),
                    'psbehinds' => (string)($match['visitorteam']['@psbehinds'] ?? ''),
                    'id' => $match['visitorteam']['@id'] ?? ''
                ];
            }

            // Process quarters data
            $formattedQuarters = [];
            if (isset($match['quarters']['quarter'])) {
                $quarterData = $match['quarters']['quarter'];
                
                // Handle both single quarter and array of quarters
                if (!isset($quarterData[0])) {
                    $quarterData = [$quarterData];
                }
                
                // Format each quarter
                foreach ($quarterData as $quarter) {
                    $quarterName = $quarter['@name'] ?? '';
                    $formattedQuarters[$quarterName] = $quarter;
                }
            }
            
            // Extract events and lineups if available
            $events = isset($match['events']) ? $match['events'] : [];
            $lineups = isset($match['lineups']) ? $match['lineups'] : [];

            // Base match data
            $baseData = [
                'category' => 'AFL Premiership',
                'week' => (string)$week,
                'match_id' => $matchId,
                'status' => $status,
                'date' => $date,
                'time' => $time,
                'venue' => $venue,
            ];

            // Add to formatted matches
            $formattedMatches[] = [
                ...$baseData,
                'localteam' => $localTeam,
                'visitorteam' => $visitorTeam,
                'quarters' => $formattedQuarters,
                'events' => $events,
                'lineups' => $lineups
            ];
        }

        return $formattedMatches;
    }
}
