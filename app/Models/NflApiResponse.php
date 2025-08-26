<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NflApiResponse extends Model
{
    protected $fillable = [
        'response',
        'date_fetched'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'response' => 'collection',           // Simple collection
    ];

    /**
     * Static method to get records by field
     *
     * @param string $field
     * @param mixed $value
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getByField($field, $value)
    {
        return static::where($field, $value)->get();
    }

    /**
     * Static method to get first record by field
     */
    public static function getFirstByField($field, $value)
    {
        return static::where($field, $value)->first();
    }

}

