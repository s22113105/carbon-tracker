<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarbonEmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'trip_id',
        'emission_date',
        'transport_mode',
        'distance',
        'co2_emission',
        'ai_suggestion',
    ];

    protected $casts = [
        'emission_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }
}