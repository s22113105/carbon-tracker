<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CarbonAnalysis extends Model
{
    use HasFactory;

    protected $table = 'carbon_analyses';

    protected $fillable = [
        'user_id',
        'start_date',
        'end_date',
        'analysis_result',
        'total_carbon_emission',
        'total_distance',
        'total_time',
        'footprint_level',
        'improvement_potential',
        'openai_model',
        'tokens_used',
        'api_cost',
        'status',
        'error_message'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'analysis_result' => 'array',
        'total_carbon_emission' => 'decimal:3',
        'total_distance' => 'decimal:2',
        'total_time' => 'integer',
        'improvement_potential' => 'integer',
        'tokens_used' => 'integer',
        'api_cost' => 'decimal:4',
    ];

    /**
     * 關聯到用戶
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 關聯到交通工具明細（如果存在該表）
     */
    public function transportationBreakdowns(): HasMany
    {
        return $this->hasMany(TransportationBreakdown::class);
    }

    /**
     * 獲取碳足跡等級文字
     */
    public function getFootprintLevelTextAttribute(): string
    {
        $levels = [
            'low' => '低碳足跡',
            'medium' => '中等碳足跡',
            'high' => '高碳足跡'
        ];
        
        return $levels[$this->footprint_level] ?? '未評估';
    }

    /**
     * 獲取狀態文字
     */
    public function getStatusTextAttribute(): string
    {
        $statuses = [
            'pending' => '等待中',
            'processing' => '分析中',
            'completed' => '已完成',
            'failed' => '失敗'
        ];
        
        return $statuses[$this->status] ?? '未知';
    }

    /**
     * 計算分析期間天數
     */
    public function getAnalysisDaysAttribute(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    /**
     * 計算日均碳排放
     */
    public function getDailyCarbonEmissionAttribute(): float
    {
        $days = $this->analysis_days;
        if ($days <= 0) return 0;
        
        return round($this->total_carbon_emission / $days, 3);
    }

    /**
     * 計算平均速度 (如果有距離和時間資料)
     */
    public function getAverageSpeedAttribute(): float
    {
        if (!$this->total_time || $this->total_time <= 0 || !$this->total_distance) {
            return 0;
        }
        
        // 時間是分鐘，需要轉換為小時
        return round($this->total_distance / ($this->total_time / 60), 2);
    }

    /**
     * 獲取改善潛力等級
     */
    public function getImprovementLevelAttribute(): string
    {
        $potential = $this->improvement_potential;
        
        if ($potential === null) return 'unknown';
        if ($potential <= 10) return 'low';
        if ($potential <= 30) return 'medium';
        return 'high';
    }

    /**
     * 獲取改善潛力等級文字
     */
    public function getImprovementLevelTextAttribute(): string
    {
        $levels = [
            'low' => '低改善潛力',
            'medium' => '中等改善潛力',
            'high' => '高改善潛力',
            'unknown' => '未評估'
        ];
        
        return $levels[$this->improvement_level] ?? '未知';
    }

    /**
     * 檢查分析是否成功完成
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * 檢查分析是否失敗
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * 檢查分析是否正在進行
     */
    public function isProcessing(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    /**
     * 獲取分析結果中的建議
     */
    public function getRecommendationsAttribute(): array
    {
        if (!$this->analysis_result || !is_array($this->analysis_result)) {
            return [];
        }
        
        return $this->analysis_result['recommendations'] ?? 
               $this->analysis_result['建議'] ?? 
               [];
    }

    /**
     * 獲取分析結果中的總結
     */
    public function getSummaryAttribute(): string
    {
        if (!$this->analysis_result || !is_array($this->analysis_result)) {
            return '';
        }
        
        return $this->analysis_result['summary'] ?? 
               $this->analysis_result['總結'] ?? 
               $this->analysis_result['摘要'] ?? 
               '';
    }

    /**
     * 範圍查詢：特定用戶
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * 範圍查詢：已完成的分析
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * 範圍查詢：失敗的分析
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * 範圍查詢：正在處理的分析
     */
    public function scopeProcessing($query)
    {
        return $query->whereIn('status', ['pending', 'processing']);
    }

    /**
     * 範圍查詢：日期範圍
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->where(function($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate, $endDate])
              ->orWhereBetween('end_date', [$startDate, $endDate])
              ->orWhere(function($subQ) use ($startDate, $endDate) {
                  $subQ->where('start_date', '<=', $startDate)
                       ->where('end_date', '>=', $endDate);
              });
        });
    }

    /**
     * 範圍查詢：本月
     */
    public function scopeThisMonth($query)
    {
        return $query->whereMonth('start_date', now()->month)
                    ->whereYear('start_date', now()->year);
    }

    /**
     * 範圍查詢：特定碳足跡等級
     */
    public function scopeByFootprintLevel($query, $level)
    {
        return $query->where('footprint_level', $level);
    }

    /**
     * 範圍查詢：高改善潛力
     */
    public function scopeHighImprovementPotential($query, $threshold = 30)
    {
        return $query->where('improvement_potential', '>', $threshold);
    }
}