<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GpsData extends Model
{
    use HasFactory;
    
    protected $table = 'gps_data';
    
    protected $fillable = [
        'user_id',
        'latitude',
        'longitude',
        'speed',
        'altitude',
        'accuracy',
        'date',
        'time',
        'created_at',
        'updated_at'
    ];
    
    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'speed' => 'decimal:2',
        'altitude' => 'decimal:2',
        'accuracy' => 'decimal:2',
        'date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    /**
     * 計算兩點之間的距離 (公里)
     */
    public function distanceTo($otherGpsData)
    {
        $earthRadius = 6371;
        
        $latFrom = deg2rad($this->latitude);
        $lonFrom = deg2rad($this->longitude);
        $latTo = deg2rad($otherGpsData->latitude);
        $lonTo = deg2rad($otherGpsData->longitude);
        
        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;
        
        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos($latFrom) * cos($latTo) *
             sin($lonDelta / 2) * sin($lonDelta / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }
}