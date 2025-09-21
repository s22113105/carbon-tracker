<?php

namespace App\Services;

use App\Models\GpsData;
use App\Models\CarbonEmissionAnalysis;
use App\Services\OpenAIService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CarbonEmissionService
{
    protected $openAIService;
    
    public function __construct(OpenAIService $openAIService)
    {
        $this->openAIService = $openAIService;
    }
    
    /**
     * 分析指定日期範圍的碳排放
     */
    public function analyzeEmissions($userId, $startDate, $endDate)
    {
        try {
            // 獲取 GPS 資料
            $gpsDataByDate = $this->getGpsDataGroupedByDate($userId, $startDate, $endDate);
            
            $results = [];
            
            foreach ($gpsDataByDate as $date => $dayGpsData) {
                // 檢查是否已有分析結果
                $existingAnalysis = CarbonEmissionAnalysis::where('user_id', $userId)
                    ->where('analysis_date', $date)
                    ->first();
                
                if ($existingAnalysis && !request()->get('force_refresh')) {
                    $results[] = $existingAnalysis;
                    continue;
                }
                
                // 呼叫 AI 分析
                $aiResult = $this->openAIService->analyzeTransportMode($dayGpsData);
                
                // 儲存分析結果
                $analysis = $this->saveAnalysis($userId, $date, $aiResult);
                $results[] = $analysis;
            }
            
            return [
                'success' => true,
                'data' => $results,
                'summary' => $this->generateSummary($results)
            ];
            
        } catch (\Exception $e) {
            Log::error('Carbon Emission Analysis Error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => '分析失敗：' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 按日期分組 GPS 資料
     */
    private function getGpsDataGroupedByDate($userId, $startDate, $endDate)
    {
        $gpsData = GpsData::where('user_id', $userId)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('timestamp')
            ->get();
        
        $grouped = [];
        
        foreach ($gpsData as $point) {
            $date = $point->date->format('Y-m-d');
            if (!isset($grouped[$date])) {
                $grouped[$date] = [];
            }
            
            $grouped[$date][] = [
                'latitude' => $point->latitude,
                'longitude' => $point->longitude,
                'speed' => $point->speed,
                'timestamp' => $point->timestamp,
                'altitude' => $point->altitude,
                'accuracy' => $point->accuracy
            ];
        }
        
        return $grouped;
    }
    
    /**
     * 儲存分析結果
     */
    private function saveAnalysis($userId, $date, $aiResult)
    {
        return CarbonEmissionAnalysis::updateOrCreate(
            [
                'user_id' => $userId,
                'analysis_date' => $date
            ],
            [
                'total_distance' => $aiResult['total_distance'],
                'total_duration' => $aiResult['total_duration'],
                'transport_mode' => $aiResult['transport_mode'],
                'carbon_emission' => $aiResult['carbon_emission'],
                'route_details' => $aiResult['journey_segments'] ?? null,
                'ai_analysis' => $aiResult,
                'suggestions' => implode("\n", $aiResult['suggestions']),
                'average_speed' => $aiResult['average_speed'] ?? null
            ]
        );
    }
    
    /**
     * 生成摘要統計
     */
    private function generateSummary($analyses)
    {
        if (empty($analyses)) {
            return null;
        }
        
        $totalEmission = 0;
        $totalDistance = 0;
        $totalDuration = 0;
        $transportModes = [];
        
        foreach ($analyses as $analysis) {
            $totalEmission += $analysis->carbon_emission;
            $totalDistance += $analysis->total_distance;
            $totalDuration += $analysis->total_duration;
            
            if (!isset($transportModes[$analysis->transport_mode])) {
                $transportModes[$analysis->transport_mode] = 0;
            }
            $transportModes[$analysis->transport_mode]++;
        }
        
        // 計算減碳潛力
        $potentialSaving = $this->calculatePotentialSaving($totalDistance, $transportModes);
        
        return [
            'total_emission' => round($totalEmission, 2),
            'total_distance' => round($totalDistance, 2),
            'total_duration' => $totalDuration,
            'total_duration_formatted' => $this->formatDuration($totalDuration),
            'average_daily_emission' => round($totalEmission / count($analyses), 2),
            'transport_modes' => $transportModes,
            'main_transport' => array_keys($transportModes, max($transportModes))[0],
            'potential_saving' => $potentialSaving,
            'eco_score' => $this->calculateEcoScore($totalEmission, $totalDistance)
        ];
    }
    
    /**
     * 計算潛在減碳量
     */
    private function calculatePotentialSaving($totalDistance, $transportModes)
    {
        $currentEmission = 0;
        $factors = [
            'car' => 0.21,
            'motorcycle' => 0.095,
            'bus' => 0.089,
            'bicycle' => 0,
            'walking' => 0
        ];
        
        foreach ($transportModes as $mode => $count) {
            if (isset($factors[$mode])) {
                $modeDistance = ($totalDistance / array_sum($transportModes)) * $count;
                $currentEmission += $modeDistance * $factors[$mode];
            }
        }
        
        // 假設全部改為大眾運輸
        $optimalEmission = $totalDistance * 0.089; // 公車係數
        
        return [
            'amount' => max(0, round($currentEmission - $optimalEmission, 2)),
            'percentage' => $currentEmission > 0 ? 
                round((($currentEmission - $optimalEmission) / $currentEmission) * 100, 1) : 0
        ];
    }
    
    /**
     * 計算環保分數
     */
    private function calculateEcoScore($emission, $distance)
    {
        if ($distance == 0) return 100;
        
        $emissionPerKm = $emission / $distance;
        
        // 基準值：汽車的碳排係數
        $baseline = 0.21;
        
        // 計算分數（0-100）
        $score = max(0, 100 - ($emissionPerKm / $baseline * 100));
        
        return round($score);
    }
    
    /**
     * 格式化時間
     */
    private function formatDuration($seconds)
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        if ($hours > 0) {
            return "{$hours}小時{$minutes}分鐘";
        }
        
        return "{$minutes}分鐘";
    }
}