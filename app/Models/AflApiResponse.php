<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Models\Types\AflRequestType;

class AflApiResponse extends Model
{
    use HasUuids;

    protected $table = 'afl_api_responses';

    public const URI_LIVE = '/afl/home?json=1';
    public const URI_SCHEDULE = '/afl/schedule?json=1';
    public const URI_STANDINGS = '/afl/standings?json=1';

    protected $fillable = ['uri', 'response', 'response_code', 'response_time', 'request_id', 'round', 'match_date', 'request_type'];

    protected $casts = [
        'response' => 'array',
        'response_code' => 'integer',
        'response_time' => 'integer',
        'round' => 'integer',
    ];

    public function scopeGetDataBy($query, string $uri, string $requestType)
    {
        return $query->where('uri', $uri)->where('request_type', $requestType)->orderBy('updated_at', 'desc')->first();
    }

    public function scopeGetRoundSchedule($query, string $round = null)
    {
        $round = $round ?? get_current_round()['round'];
        return $query->where('round', $round)->orderBy('updated_at', 'desc')->first();
    }

    public function scopeGetScheduleByRound($query, string $round)
    {
        return $this->scopeGetRoundSchedule($query, $round);
    }

    public function scopeGetLatestData($query)
    {
        return $this->scopeGetDataBy($query, self::URI_LIVE, AflRequestType::Live->name);
    }

    public function scopeGetLatestSchedule($query)
    {
        return $query->where('request_type', AflRequestType::Schedules->name)->orderBy('updated_at', 'desc');
    }

    public function scopeGetLatestStandings($query)
    {
        return $query->where('uri', self::URI_STANDINGS)
            ->where('request_type', AflRequestType::Standings->name)
            ->orderBy('updated_at', 'desc');
    }

    public function scopeFindByMatchData($query, string $matchId, string $round)
    {
        // This handles both cases:
        // 1. When match is a single object (not in an array)
        // 2. When match is one of many in an array
        return $query
            ->where(function ($q) use ($matchId) {
                // Case 1: Match is a single object (not in an array)
                $q->whereRaw('json_extract(response, "$.scores.category.match.@id") = ?', [$matchId])
                    // Case 2: Match is in an array (could be at any position)
                    ->orWhereRaw('json_extract(response, "$.scores.category.match") LIKE ?', ['%"@id":"' . $matchId . '"%']);
            })
            ->whereIn('request_type', ['Live', 'Record'])
            ->where('round', $round);
    }

    public function scopeGetScoreSummary($query, string $round)
    {
        return $query->where('round', $round)->orderBy('upated_at', 'desc');
    }
}
