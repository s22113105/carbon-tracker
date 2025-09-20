<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

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
        'timestamp',
        'date',
        'time',
        'device_id'
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'speed' => 'decimal:2',
        'altitude' => 'decimal:2',
        'accuracy' => 'decimal:2',
        'timestamp' => 'datetime',
        'date' => 'date',
    ];

    /**
     * 關聯到使用者
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 計算兩點之間的距離（公里）
     */
    public function distanceTo($otherGpsData)
    {
        $earthRadius = 6371; // 地球半徑（公里）

        $lat1 = deg2rad($this->latitude);
        $lon1 = deg2rad($this->longitude);
        $lat2 = deg2rad($otherGpsData->latitude);
        $lon2 = deg2rad($otherGpsData->longitude);

        $deltaLat = $lat2 - $lat1;
        $deltaLon = $lon2 - $lon1;

        $a = sin($deltaLat/2) * sin($deltaLat/2) + cos($lat1) * cos($lat2) * sin($deltaLon/2) * sin($deltaLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }

    /**
     * 根據日期範圍獲取使用者的GPS資料
     */
    public static function getDataByDateRange($userId, $startDate, $endDate)
    {
        return self::where('user_id', $userId)
                   ->whereBetween('date', [$startDate, $endDate])
                   ->orderBy('timestamp')
                   ->get();
    }

    /**
     * 獲取使用者最近的GPS資料
     */
    public static function getRecentData($userId, $days = 7)
    {
        $startDate = Carbon::now()->subDays($days)->format('Y-m-d');
        $endDate = Carbon::now()->format('Y-m-d');
        
        return self::getDataByDateRange($userId, $startDate, $endDate);
    }
}