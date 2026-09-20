<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class HotspotObservation extends Model
{
    protected $fillable = [
        'latitude',
        'longitude',
        'confidence',
        'frp',
        'satellite',
        'instrument',
        'acquired_at',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'confidence' => 'integer',
            'frp' => 'float',
            'acquired_at' => 'immutable_datetime',
        ];
    }
}
