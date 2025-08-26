<?php

namespace App\Dto;

class NflScoreData
{
    public function __construct(
        public readonly string $attendance,
        public readonly string $awayteam,
        public readonly string $hometeam,
        public readonly string $contest_id,
        public readonly string $date,
        public readonly string $datetime_utc,
        public readonly string $defensive,
        public readonly string $events,
        public readonly string $formatted_date,
        public readonly string $fumbles,
        public readonly string $interceptions,
        public readonly string $kick_returns,
        public readonly string $kicking,
        public readonly string $passing,
        public readonly string $punt_returns,
        public readonly string $punting,
        public readonly string $receiving,
        public readonly string $rushing,
        public readonly string $status,
        public readonly string $team_stats,
        public readonly string $time,
        public readonly string $timer,
        public readonly string $timezone,
        public readonly string $venue_id,
        public readonly string $venue_name,
    ) {}

    public static function fromApiResponse(array $data): self
    {

        return new self(
            attendance: $data['attendance'] ?? null,
            awayteam: json_encode($data['awayteam'] ?? null),
            hometeam: json_encode($data['hometeam'] ?? null),
            contest_id: $data['contestID'] ?? null,
            date: $data['date'] ?? null,
            datetime_utc: $data['datetime_utc'] ?? null,
            defensive: json_encode($data['defensive'] ?? null),
            events: json_encode($data['events'] ?? ''),
            formatted_date: $data['formatted_date'] ?? '',
            fumbles: json_encode($data['fumbles'] ?? ''),
            interceptions: json_encode($data['interceptions'] ?? ''),
            kick_returns: json_encode($data['kick_returns'] ?? ''),
            kicking: json_encode($data['kicking'] ?? ''),
            passing: json_encode($data['passing'] ?? ''),
            punt_returns: json_encode($data['punt_returns'] ?? ''),
            punting: json_encode($data['punting'] ?? ''),
            receiving: json_encode($data['receiving'] ?? ''),
            rushing: json_encode($data['rushing'] ?? ''),
            status: $data['status'] ?? '',
            team_stats: json_encode($data['team_stats'] ?? ''),
            time: $data['time'] ?? '',
            timer: $data['timer'] ?? '',
            timezone: $data['timezone'] ?? '',
            venue_id: $data['venue_id'] ?? '',
            venue_name: $data['venue_name'] ?? '',
        );
    }

    /**
     * Convert to array using get_object_vars()
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
