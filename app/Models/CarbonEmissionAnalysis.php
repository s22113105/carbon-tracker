<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CarbonEmissionAnalysis extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id',
        'analysis_date',
        'total_distance',
        'total_duration',
        'transport_mode',
        'carbon_emission',
        'route_details',
        'ai_analysis',
        'suggestions',
        'average_speed'
    ];
    
    protected $casts = [
        'analysis_date' => 'date',
        'route_details' => 'array',
        'ai_analysis' => 'array',
        'total_distance' => 'decimal:2',
        'carbon_emission' => 'decimal:3',
        'average_speed' => 'decimal:2'
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * 取得格式化的交通工具名稱
     */
    public function getTransportModeNameAttribute()
    {
        $modes = [
            'walking' => '步行',
            'bicycle' => '腳踏車',
            'motorcycle' => '機車',
            'car' => '汽車',
            'bus' => '公車',
            'mixed' => '混合'
        ];
        
        return $modes[$this->transport_mode] ?? $this->transport_mode;
    }
}
