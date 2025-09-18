<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Geofence extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'latitude',
        'longitude',
        'radius',
        'type',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_active' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTypeNameAttribute()
    {
        $types = [
            'office' => '辦公室',
            'restricted' => '限制區域',
            'parking' => '停車場',
            'custom' => '自訂區域'
        ];

        return $types[$this->type] ?? '未知';
    }

    public function getStatusTextAttribute()
    {
        return $this->is_active ? '啟用' : '停用';
    }

    public function getStatusColorAttribute()
    {
        return $this->is_active ? 'success' : 'secondary';
    }

    // 檢查指定座標是否在此地理圍欄內
    public function isInsideGeofence($latitude, $longitude)
    {
        if (!$this->is_active) {
            return false;
        }

        $distance = $this->calculateDistance($latitude, $longitude);
        return $distance <= $this->radius;
    }

    // 計算與指定座標的距離（公尺）
    public function calculateDistance($latitude, $longitude)
    {
        $earthRadius = 6371000; // 地球半徑（公尺）

        $dLat = deg2rad($latitude - $this->latitude);
        $dLng = deg2rad($longitude - $this->longitude);

        $a = sin($dLat/2) * sin($dLat/2) +
            cos(deg2rad($this->latitude)) * cos(deg2rad($latitude)) *
            sin($dLng/2) * sin($dLng/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }

    // 獲取圍欄範圍的邊界座標
    public function getBounds()
    {
        $latOffset = $this->radius / 111320; // 緯度偏移（約1度 = 111.32km）
        $lngOffset = $this->radius / (111320 * cos(deg2rad($this->latitude))); // 經度偏移

        return [
            'north' => $this->latitude + $latOffset,
            'south' => $this->latitude - $latOffset,
            'east' => $this->longitude + $lngOffset,
            'west' => $this->longitude - $lngOffset,
        ];
    }

    // Scope：只取得啟用的圍欄
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope：根據類型篩選
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
}