<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class CarbonEmissionController extends Controller
{
    private $openAIService;
    
    public function __construct(OpenAIService $openAIService)
    {
        $this->openAIService = $openAIService;
    }
    
    /**
     * 顯示 AI 碳排放分析頁面
     */
    public function aiAnalyses()
    {
        return view('user.aiAnalyses');
    }
    
    /**
     * 獲取可分析的資料
     * 這是關鍵方法 - 用於載入月份的 GPS 資料
     */
    public function getAvailableData(Request $request)
{
    try {
        $month = $request->input('month', date('Y-m')); // 格式: 2025-09
        $userId = auth()->id();
        
        Log::info('獲取可分析資料', [
            'user_id' => $userId,
            'month' => $month
        ]);
        
        // 計算月份的起始和結束日期
        $startDate = Carbon::parse($month . '-01')->startOfMonth();
        $endDate = Carbon::parse($month . '-01')->endOfMonth();
        
        // 先檢查是否有任何 GPS 資料
        $totalGpsCount = DB::table('gps_tracks')
            ->where('user_id', $userId)
            ->whereBetween('recorded_at', [$startDate, $endDate])
            ->count();
        
        Log::info('GPS 資料總數', [
            'user_id' => $userId,
            'count' => $totalGpsCount,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
        
        if ($totalGpsCount == 0) {
            return response()->json([
                'success' => false,
                'message' => '所選月份沒有GPS資料。請確認 ESP32 設備已上傳資料或執行假資料生成器。',
                'data' => [],
                'debug' => [
                    'user_id' => $userId,
                    'month' => $month,
                    'start_date' => $startDate->format('Y-m-d H:i:s'),
                    'end_date' => $endDate->format('Y-m-d H:i:s'),
                    'total_gps_count' => $totalGpsCount
                ]
            ]);
        }
        
        // 查詢該月份的 GPS 資料,按日期分組
        $dailyData = DB::table('gps_tracks')
            ->select(
                DB::raw('DATE(recorded_at) as date'),
                DB::raw('COUNT(*) as total_gps_count'),
                DB::raw('SUM(CASE WHEN device_type = "ESP32" THEN 1 ELSE 0 END) as esp32_count'),
                DB::raw('SUM(CASE WHEN device_type != "ESP32" OR device_type IS NULL THEN 1 ELSE 0 END) as manual_count'),
                DB::raw('MIN(recorded_at) as first_record'),
                DB::raw('MAX(recorded_at) as last_record'),
                DB::raw('AVG(speed) as avg_speed')
            )
            ->where('user_id', $userId)
            ->whereBetween('recorded_at', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(recorded_at)'))
            ->orderBy('date', 'desc')
            ->get();
        
        // 為每一天添加額外資訊
        $enrichedData = $dailyData->map(function($day) use ($userId) {
            // 查詢當天的行程數
            $tripsCount = DB::table('trips')
                ->where('user_id', $userId)
                ->whereDate('start_time', $day->date)
                ->count();
            
            // 檢查是否已經有分析結果
            $hasAnalysis = DB::table('carbon_analyses')
                ->where('user_id', $userId)
                ->where(function($query) use ($day) {
                    $query->whereDate('start_date', '<=', $day->date)
                          ->whereDate('end_date', '>=', $day->date);
                })
                ->exists();
            
            // 計算時間範圍
            $firstTime = Carbon::parse($day->first_record)->format('H:i');
            $lastTime = Carbon::parse($day->last_record)->format('H:i');
            
            return [
                'date' => $day->date,
                'gps_count' => (int)$day->total_gps_count,
                'esp32_count' => (int)($day->esp32_count ?? 0),
                'manual_count' => (int)($day->manual_count ?? 0),
                'trips_count' => $tripsCount,
                'time_range' => $firstTime . ' - ' . $lastTime,
                'avg_speed' => round($day->avg_speed ?? 0, 1),
                'has_analysis' => $hasAnalysis,
                'status' => $hasAnalysis ? 'analyzed' : 'pending'
            ];
        });
        
        Log::info('處理後的每日資料', [
            'user_id' => $userId,
            'days_count' => $enrichedData->count(),
            'sample' => $enrichedData->first()
        ]);
        
        return response()->json([
            'success' => true,
            'message' => '資料載入成功',
            'data' => $enrichedData,
            'month' => $month,
            'total_days' => $enrichedData->count(),
            'total_gps_records' => $totalGpsCount
        ]);
        
    } catch (\Exception $e) {
        Log::error('獲取可分析資料失敗: ' . $e->getMessage(), [
            'user_id' => auth()->id(),
            'month' => $request->input('month'),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => '載入資料時發生錯誤: ' . $e->getMessage(),
            'data' => []
        ], 500);
    }
}
    
    /**
     * 執行碳排放分析
     */
    public function analyze(Request $request)
{
    $request->validate([
        'dates' => 'required|array',
        'dates.*' => 'required|date'
    ]);
    
    try {
        $userId = auth()->id();
        $dates = $request->input('dates');
        $analysisResults = [];
        
        foreach ($dates as $date) {
            // 獲取該日的 GPS 資料
            $gpsData = $this->getGpsDataForDate($userId, $date);
            
            if (empty($gpsData)) {
                $analysisResults[] = [
                    'date' => $date,
                    'success' => false,
                    'message' => '無GPS資料'
                ];
                continue;
            }
            
            // 使用 OpenAI 分析
            $analysis = $this->openAIService->analyzeCarbonFootprint($gpsData);
            
            // 先刪除該日期的舊分析記錄(如果存在)
            DB::table('carbon_analyses')
                ->where('user_id', $userId)
                ->where('start_date', $date)
                ->where('end_date', $date)
                ->delete();
            
            // 插入新的分析結果
            $analysisId = DB::table('carbon_analyses')->insertGetId([
                'user_id' => $userId,
                'start_date' => $date,
                'end_date' => $date,
                'analysis_result' => json_encode($analysis),
                'transport_mode' => $analysis['transport_mode'] ?? 'unknown',
                'total_carbon_emission' => $analysis['carbon_emission'] ?? 0,
                'total_distance' => $analysis['total_distance'] ?? 0,
                'total_duration' => $analysis['total_duration'] ?? 0,
                'average_speed' => $analysis['average_speed'] ?? 0,
                'confidence' => $analysis['confidence'] ?? 0,
                'route_analysis' => $analysis['route_analysis'] ?? null,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // 同時更新 carbon_emissions 表
            DB::table('carbon_emissions')
                ->where('user_id', $userId)
                ->where('date', $date)
                ->delete();
            
            if (isset($analysis['carbon_emission']) && $analysis['carbon_emission'] > 0) {
                DB::table('carbon_emissions')->insert([
                    'user_id' => $userId,
                    'date' => $date,
                    'transport_mode' => $analysis['transport_mode'] ?? 'unknown',
                    'distance' => $analysis['total_distance'] ?? 0,
                    'carbon_amount' => $analysis['carbon_emission'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            
            $analysisResults[] = [
                'date' => $date,
                'success' => true,
                'analysis_id' => $analysisId,
                'analysis' => $analysis
            ];
        }
        
        // 計算總結
        $summary = $this->calculateSummary($analysisResults);
        
        return response()->json([
            'success' => true,
            'message' => '分析完成',
            'data' => $analysisResults,
            'summary' => $summary
        ]);
        
    } catch (\Exception $e) {
        Log::error('碳排放分析失敗: ' . $e->getMessage(), [
            'user_id' => auth()->id(),
            'dates' => $request->input('dates'),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => '分析失敗: ' . $e->getMessage()
        ], 500);
    }
}
    
    /**
     * 獲取指定日期的 GPS 資料
     */
    private function getGpsDataForDate($userId, $date)
    {
        $tracks = DB::table('gps_tracks')
            ->where('user_id', $userId)
            ->whereDate('recorded_at', $date)
            ->orderBy('recorded_at')
            ->get();
        
        $gpsData = [];
        foreach ($tracks as $track) {
            $gpsData[] = [
                'latitude' => $track->latitude,
                'longitude' => $track->longitude,
                'speed' => $track->speed ?? 0,
                'timestamp' => $track->recorded_at,
                'altitude' => $track->altitude ?? 0,
                'accuracy' => $track->accuracy ?? 0
            ];
        }
        
        return $gpsData;
    }
    
    /**
     * 計算分析總結
     */
    private function calculateSummary($analysisResults)
    {
        $totalEmission = 0;
        $totalDistance = 0;
        $transportModes = [];
        $successCount = 0;
        
        foreach ($analysisResults as $result) {
            if ($result['success'] && isset($result['analysis'])) {
                $successCount++;
                $analysis = $result['analysis'];
                
                $totalEmission += $analysis['carbon_emission'] ?? 0;
                $totalDistance += $analysis['total_distance'] ?? 0;
                
                $mode = $analysis['transport_mode'] ?? 'unknown';
                if (!isset($transportModes[$mode])) {
                    $transportModes[$mode] = 0;
                }
                $transportModes[$mode]++;
            }
        }
        
        $avgEmission = $successCount > 0 ? $totalEmission / $successCount : 0;
        
        return [
            'total_days' => count($analysisResults),
            'analyzed_days' => $successCount,
            'total_emission' => round($totalEmission, 3),
            'total_distance' => round($totalDistance, 2),
            'avg_daily_emission' => round($avgEmission, 3),
            'transport_modes' => $transportModes,
            'suggestions' => $this->generateSuggestions($transportModes, $totalEmission)
        ];
    }
    
    /**
     * 生成減碳建議
     */
    private function generateSuggestions($transportModes, $totalEmission)
    {
        $suggestions = [];
        
        if (isset($transportModes['car']) && $transportModes['car'] > 0) {
            $suggestions[] = '建議改用大眾運輸工具,可減少約60%的碳排放';
        }
        
        if (isset($transportModes['motorcycle']) && $transportModes['motorcycle'] > 0) {
            $suggestions[] = '短程通勤可考慮使用腳踏車或電動機車';
        }
        
        if ($totalEmission > 10) {
            $suggestions[] = '您的每日平均碳排放較高,建議規劃更環保的通勤方式';
        }
        
        if (empty($suggestions)) {
            $suggestions[] = '繼續保持環保的交通方式!';
        }
        
        return $suggestions;
    }
    
    /**
     * 獲取歷史分析記錄
     */
    public function history(Request $request)
    {
        try {
            $userId = auth()->id();
            
            $analyses = DB::table('carbon_analyses')
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->paginate(10);
            
            return response()->json([
                'success' => true,
                'data' => $analyses
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '獲取歷史記錄失敗'
            ], 500);
        }
    }
    
    /**
     * 獲取統計資料
     */
    public function statistics(Request $request)
    {
        try {
            $userId = auth()->id();
            $days = $request->input('days', 30);
            
            $startDate = Carbon::now()->subDays($days);
            
            $stats = [
                'total_emissions' => DB::table('carbon_emissions')
                    ->where('user_id', $userId)
                    ->where('date', '>=', $startDate)
                    ->sum('carbon_amount'),
                    
                'total_distance' => DB::table('carbon_emissions')
                    ->where('user_id', $userId)
                    ->where('date', '>=', $startDate)
                    ->sum('distance'),
                    
                'transport_breakdown' => DB::table('carbon_emissions')
                    ->select('transport_mode', DB::raw('SUM(carbon_amount) as total'))
                    ->where('user_id', $userId)
                    ->where('date', '>=', $startDate)
                    ->groupBy('transport_mode')
                    ->get()
            ];
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '獲取統計資料失敗'
            ], 500);
        }
    }
    
    /**
     * 測試OpenAI連線
     */
    public function testConnection()
    {
        try {
            // 簡單的測試資料
            $testData = [
                [
                    'latitude' => 22.7632,
                    'longitude' => 120.3757,
                    'speed' => 35,
                    'timestamp' => now()->format('Y-m-d H:i:s')
                ]
            ];
            
            $result = $this->openAIService->analyzeCarbonFootprint($testData);
            
            return response()->json([
                'success' => true,
                'message' => 'OpenAI 連線正常',
                'data' => $result
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'OpenAI 連線失敗: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 清除快取
     */
    public function clearCache()
    {
        try {
            Cache::flush();
            
            return response()->json([
                'success' => true,
                'message' => '快取已清除'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '清除快取失敗'
            ], 500);
        }
    }
}