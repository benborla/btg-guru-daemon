<?php

namespace App\Dto;

class NflScoreData
{
    public function __construct(
        public readonly string $attendance,
        public readonly string $awayTeam,
        public readonly string $homeTeam,
        public readonly string $contestId,
        public readonly string $date,
        public readonly string $datetimeUtc,
        public readonly string $defensive,
        public readonly string $events,
        public readonly string $formatted_date,
        public readonly string $interceptions,
        public readonly string $kick_returns,
        public readonly string $kicking,
        public readonly string $passing,
        public readonly string $receiving,
        public readonly string $ruhsing,
        public readonly string $status,
        public readonly string $team_stats,
        public readonly string $time,
        public readonly string $timer,
        public readonly string $timezone,
        public readonly string $venu_id,
        public readonly string $venu_name,
    ) {}

    public static function fromApiResponse(array $data): self
    {
        return new self(
            attendance: $data['attendance'],
            awayTeam: $data['awayTeam'],
            homeTeam: $data['homeTeam'],
            contestId: $data['contestId'] ?? null,
            date: $data['date'] ?? null,
            dateTimeUtc: $data['datetimeUtc'] ?? null,
            defensive: $data['defensive'] ?? null,
            events: $data['events'] ?? '',
            formattedDate: $data['formatted_date'] ?? '',
            interceptions: $data['interceptions'] ?? '',
            kick_returns: $data['kick_returns'] ?? '',
            kicking: $data['kicking'] ?? '',
            passing: $data['passing'] ?? '',
            receiving: $data['receiving'] ?? '',
            rushing: $data['rushing'] ?? '',
            status: $data['status'] ?? '',
            team_stats: $data['team_stats'] ?? '',
            time: $data['time'] ?? '',
            timer: $data['timer'] ?? '',
            timezone: $data['timezone'] ?? '',
            venu_id: $data['venu_id'] ?? '',
            venu_name: $data['venu_name'] ?? '',
        );
    }
}
