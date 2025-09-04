<?php

namespace App\Dto;

class NflScoreData
{
    public function __construct(
        public readonly string $attendance,
        public readonly array $awayteam,
        public readonly array $hometeam,
        public readonly string $contest_id,
        public readonly string $date,
        public readonly string $datetime_utc,
        public readonly array $defensive,
        public readonly array $events,
        public readonly string $formatted_date,
        public readonly array $fumbles,
        public readonly array $interceptions,
        public readonly array $kick_returns,
        public readonly array $kicking,
        public readonly array $passing,
        public readonly array $punt_returns,
        public readonly array $punting,
        public readonly array $receiving,
        public readonly array $rushing,
        public readonly string $status,
        public readonly array $team_stats,
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
            awayteam: ($data['awayteam'] ?? null),
            hometeam: ($data['hometeam'] ?? null),
            contest_id: $data['contestID'] ?? null,
            date: $data['date'] ?? null,
            datetime_utc: $data['datetime_utc'] ?? null,
            defensive: ($data['defensive'] ?? []),
            events: ($data['events'] ?? ''),
            formatted_date: $data['formatted_date'] ?? '',
            fumbles: ($data['fumbles'] ?? []),
            interceptions: ($data['interceptions'] ?? []),
            kick_returns: ($data['kick_returns'] ?? []),
            kicking: ($data['kicking'] ?? []),
            passing: ($data['passing'] ?? []),
            punt_returns: ($data['punt_returns'] ?? []),
            punting: ($data['punting'] ?? []),
            receiving: ($data['receiving'] ?? []),
            rushing: ($data['rushing'] ?? []),
            status: $data['status'] ?? '',
            team_stats: ($data['team_stats'] ?? []),
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
