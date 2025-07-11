<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AflSchedule extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'afl_schedules';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'round',
        'match_id',
        'date',
        'time',
        'status',
        'venue',
        'local_team',
        'visitor_team',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'local_team' => 'array',
        'visitor_team' => 'array',
    ];

    /**
     * Scope a query to get schedules by round.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $round
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByRound($query, $round)
    {
        return $query->where('round', $round);
    }

    /**
     * Scope a query to get schedules by match ID.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $matchId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByMatchId($query, $matchId)
    {
        return $query->where('match_id', $matchId);
    }
    
    /**
     * Scope a query to order schedules by date and time.
     * 
     * Note: This is a local scope that should be applied to a collection
     * after retrieving data from the database, not a query scope.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $collection
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function sortByDateTime($collection)
    {
        return $collection->sortBy(function ($schedule) {
            // Parse the date format and create a sortable string
            $date = $schedule->date;
            $time = $schedule->time;
            
            // Handle different date formats
            if (strpos($date, '.') !== false) {
                // Format: dd.mm.YYYY
                $dateParts = explode('.', $date);
                if (count($dateParts) === 3) {
                    return $dateParts[2] . '-' . $dateParts[1] . '-' . $dateParts[0] . ' ' . $time;
                }
            }
            
            // Default fallback
            return $date . ' ' . $time;
        });
    }
}
