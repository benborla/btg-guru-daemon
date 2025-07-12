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
        return $this->scopeGetDataBy($query, self::URI_STANDINGS, AflRequestType::Standings->name);
    }

    public function scopeFindByMatchData($query, string $matchId, string $round)
    {
        return $query
            ->whereRaw('EXISTS (SELECT 1 FROM json_tree(response) WHERE json_tree.value = ?)', [$matchId])
            ->whereIn('request_type', ['Live', 'Record'])
            ->where('round', $round);
    }
}
