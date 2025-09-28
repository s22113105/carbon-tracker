<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportationBreakdown extends Model
{
    use HasFactory;

    protected $fillable = [
        'carbon_analysis_id',
        'transportation_type',
        'distance',
        'time_minutes',
        'carbon_emission',
        'trip_count',
        'average_speed',
        'estimated_cost'
    ];

    protected $casts = [
        'distance' => 'decimal:2',
        'time_minutes' => 'integer',
        'carbon_emission' => 'decimal:3',
        'trip_count' => 'integer',
        'average_speed' => 'decimal:2',
        'estimated_cost' => 'decimal:2'
    ];

    /**
     * 關聯到碳分析記錄
     */
    public function carbonAnalysis(): BelongsTo
    {
        return $this->belongsTo(CarbonAnalysis::class);
    }

    /**
     * 獲取交通工具類型文字
     */
    public function getTransportationTypeTextAttribute(): string
    {
        $types = [
            'walking' => '步行',
            'bicycle' => '腳踏車',
            'motorcycle' => '機車',
            'car' => '汽車',
            'bus' => '公車',
            'mrt' => '捷運',
            'train' => '火車'
        ];
        
        return $types[$this->transportation_type] ?? '未知';
    }

    /**
     * 獲取碳排放因子 (kg CO2 per km)
     */
    public function getCarbonEmissionFactorAttribute(): float
    {
        $factors = [
            'walking' => 0,
            'bicycle' => 0,
            'motorcycle' => 0.095,
            'car' => 0.21,
            'bus' => 0.089,
            'mrt' => 0.033,
            'train' => 0.041
        ];
        
        return $factors[$this->transportation_type] ?? 0.15;
    }

    /**
     * 計算每公里成本
     */
    public function getCostPerKmAttribute(): float
    {
        if ($this->distance <= 0 || !$this->estimated_cost) {
            return 0;
        }
        
        return round($this->estimated_cost / $this->distance, 2);
    }

    /**
     * 計算時間效率 (km/h)
     */
    public function getTimeEfficiencyAttribute(): float
    {
        if ($this->time_minutes <= 0) {
            return 0;
        }
        
        return round(($this->distance / $this->time_minutes) * 60, 2);
    }

    /**
     * 獲取環保等級
     */
    public function getEcoFriendlinessLevelAttribute(): string
    {
        $emission = $this->carbon_emission;
        
        if ($emission == 0) {
            return 'excellent';
        } elseif ($emission <= 0.5) {
            return 'good';
        } elseif ($emission <= 2) {
            return 'moderate';
        } else {
            return 'poor';
        }
    }

    /**
     * 獲取環保等級文字
     */
    public function getEcoFriendlinessLevelTextAttribute(): string
    {
        $levels = [
            'excellent' => '極優',
            'good' => '良好',
            'moderate' => '普通',
            'poor' => '較差'
        ];
        
        return $levels[$this->eco_friendliness_level] ?? '未知';
    }

    /**
     * 範圍查詢：特定交通工具
     */
    public function scopeByTransportationType($query, $type)
    {
        return $query->where('transportation_type', $type);
    }

    /**
     * 範圍查詢：高碳排放
     */
    public function scopeHighEmission($query, $threshold = 1.0)
    {
        return $query->where('carbon_emission', '>', $threshold);
    }

    /**
     * 範圍查詢：低碳排放
     */
    public function scopeLowEmission($query, $threshold = 0.5)
    {
        return $query->where('carbon_emission', '<=', $threshold);
    }
}