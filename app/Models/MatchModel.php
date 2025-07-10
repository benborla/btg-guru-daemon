<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchModel extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'matches';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'rapidapi_id',
        'sport_type',
        'league_id',
        'home_team_id',
        'away_team_id',
        'status_short',
        'status_long',
        'home_score',
        'away_score',
        'match_date',
        'timestamp',
        'timezone',
        'venue_name',
        'venue_city',
        'league_details',
        'teams',
        'scores',
        'fixture',
        'additional_data',
        'has_updates',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'match_date' => 'datetime',
        'timestamp' => 'integer',
        'home_score' => 'integer',
        'away_score' => 'integer',
        'league_details' => 'array',
        'teams' => 'array',
        'scores' => 'array',
        'fixture' => 'array',
        'additional_data' => 'array',
        'has_updates' => 'boolean',
    ];

    /**
     * Get the league that the match belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function league()
    {
        return $this->belongsTo(League::class);
    }

    /**
     * Get the home team for the match.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function homeTeam()
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    /**
     * Get the away team for the match.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function awayTeam()
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }
}
