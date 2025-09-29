<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\CarbonEmissionService;
use App\Services\OpenAIService;
use App\Models\CarbonEmissionAnalysis;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CarbonEmissionController extends Controller
{
    protected $carbonService;
    protected $openAIService;
    
    public function __construct(CarbonEmissionService $carbonService, OpenAIService $openAIService)
    {
        $this->carbonService = $carbonService;
        $this->openAIService = $openAIService;
    }
    
    /**
     * 顯示 AI 分析頁面
     */
    public function aiAnalyses()
    {
        return view('user.aiAnalyses');
    }
    
    /**
     * 取得可供分析的資料
     * 這個方法會檢查各種來源的GPS資料
     */
    public function getAvailableData(Request $request)
    {
        $userId = Auth::id();
        $month = $request->input('month', Carbon::now()->format('Y-m'));
        
        try {
            $startOfMonth = Carbon::parse($month)->startOfMonth();
            $endOfMonth = Carbon::parse($month)->endOfMonth();
            
            // 1. 檢查 gps_tracks 表（ESP32上傳的資料）
            $gpsTracksData = DB::table('gps_tracks')
                ->selectRaw('DATE(recorded_at) as date')
                ->selectRaw('COUNT(*) as points_count')
                ->selectRaw('MIN(recorded_at) as first_point')
                ->selectRaw('MAX(recorded_at) as last_point')
                ->selectRaw('AVG(speed) as avg_speed')
                ->where('user_id', $userId)
                ->whereBetween('recorded_at', [$startOfMonth, $endOfMonth])
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->get();
            
            // 2. 檢查 gps_records 表（如果有的話）
            $gpsRecordsData = [];
            if (DB::getSchemaBuilder()->hasTable('gps_records')) {
                $gpsRecordsData = DB::table('gps_records')
                    ->selectRaw('DATE(recorded_at) as date')
                    ->selectRaw('COUNT(*) as points_count')
                    ->selectRaw('MIN(recorded_at) as first_point')
                    ->selectRaw('MAX(recorded_at) as last_point')
                    ->where('user_id', $userId)
                    ->whereBetween('recorded_at', [$startOfMonth, $endOfMonth])
                    ->groupBy('date')
                    ->orderBy('date', 'desc')
                    ->get();
            }
            
            // 3. 檢查已有的分析結果
            $existingAnalyses = CarbonEmissionAnalysis::where('user_id', $userId)
                ->whereBetween('analysis_date', [$startOfMonth, $endOfMonth])
                ->pluck('analysis_date')
                ->map(function($date) {
                    return Carbon::parse($date)->format('Y-m-d');
                })
                ->toArray();
            
            // 4. 檢查行程資料（trips）
            $tripsData = [];
            if (DB::getSchemaBuilder()->hasTable('trips')) {
                $tripsData = DB::table('trips')
                    ->selectRaw('DATE(start_time) as date')
                    ->selectRaw('COUNT(*) as trips_count')
                    ->selectRaw('SUM(distance) as total_distance')
                    ->selectRaw('SUM(duration) as total_duration')
                    ->where('user_id', $userId)
                    ->whereBetween('start_time', [$startOfMonth, $endOfMonth])
                    ->groupBy('date')
                    ->orderBy('date', 'desc')
                    ->get();
            }
            
            // 整合所有資料來源
            $availableData = [];
            $allDates = collect();
            
            // 處理 gps_tracks 資料
            foreach ($gpsTracksData as $data) {
                $date = $data->date;
                if (!isset($availableData[$date])) {
                    $availableData[$date] = [
                        'date' => $date,
                        'esp32_points' => 0,
                        'gps_points' => 0,
                        'trips_count' => 0,
                        'has_analysis' => false,
                        'total_distance' => 0,
                        'total_duration' => 0,
                        'time_range' => '',
                        'avg_speed' => 0,
                        'data_sources' => []
                    ];
                }
                
                $availableData[$date]['esp32_points'] = $data->points_count;
                $availableData[$date]['avg_speed'] = round($data->avg_speed ?? 0, 1);
                $availableData[$date]['time_range'] = sprintf(
                    '%s - %s',
                    Carbon::parse($data->first_point)->format('H:i'),
                    Carbon::parse($data->last_point)->format('H:i')
                );
                $availableData[$date]['data_sources'][] = 'ESP32';
            }
            
            // 處理 gps_records 資料
            foreach ($gpsRecordsData as $data) {
                $date = $data->date;
                if (!isset($availableData[$date])) {
                    $availableData[$date] = [
                        'date' => $date,
                        'esp32_points' => 0,
                        'gps_points' => 0,
                        'trips_count' => 0,
                        'has_analysis' => false,
                        'total_distance' => 0,
                        'total_duration' => 0,
                        'time_range' => '',
                        'avg_speed' => 0,
                        'data_sources' => []
                    ];
                }
                
                $availableData[$date]['gps_points'] = $data->points_count;
                if (empty($availableData[$date]['time_range'])) {
                    $availableData[$date]['time_range'] = sprintf(
                        '%s - %s',
                        Carbon::parse($data->first_point)->format('H:i'),
                        Carbon::parse($data->last_point)->format('H:i')
                    );
                }
                if (!in_array('GPS', $availableData[$date]['data_sources'])) {
                    $availableData[$date]['data_sources'][] = 'GPS';
                }
            }
            
            // 處理行程資料
            foreach ($tripsData as $data) {
                $date = $data->date;
                if (!isset($availableData[$date])) {
                    $availableData[$date] = [
                        'date' => $date,
                        'esp32_points' => 0,
                        'gps_points' => 0,
                        'trips_count' => 0,
                        'has_analysis' => false,
                        'total_distance' => 0,
                        'total_duration' => 0,
                        'time_range' => '',
                        'avg_speed' => 0,
                        'data_sources' => []
                    ];
                }
                
                $availableData[$date]['trips_count'] = $data->trips_count;
                $availableData[$date]['total_distance'] = round($data->total_distance / 1000, 2); // 轉換為公里
                $availableData[$date]['total_duration'] = round($data->total_duration / 60, 0); // 轉換為分鐘
                
                if (!in_array('Trips', $availableData[$date]['data_sources'])) {
                    $availableData[$date]['data_sources'][] = 'Trips';
                }
            }
            
            // 標記已有分析的日期
            foreach ($existingAnalyses as $date) {
                if (isset($availableData[$date])) {
                    $availableData[$date]['has_analysis'] = true;
                }
            }
            
            // 按日期排序並轉換為陣列
            $sortedData = collect($availableData)
                ->sortByDesc('date')
                ->values()
                ->map(function($item) {
                    // 格式化日期顯示
                    $carbonDate = Carbon::parse($item['date']);
                    $item['formatted_date'] = $carbonDate->format('m/d');
                    $item['weekday'] = $carbonDate->locale('zh_TW')->isoFormat('dddd');
                    $item['is_weekend'] = $carbonDate->isWeekend();
                    
                    // 計算資料品質分數（用於排序或篩選）
                    $item['quality_score'] = $this->calculateDataQualityScore($item);
                    
                    return $item;
                })
                ->toArray();
            
            return response()->json([
                'success' => true,
                'data' => $sortedData,
                'summary' => [
                    'total_days' => count($sortedData),
                    'days_with_esp32_data' => collect($sortedData)->where('esp32_points', '>', 0)->count(),
                    'days_with_gps_data' => collect($sortedData)->where('gps_points', '>', 0)->count(),
                    'days_with_trips' => collect($sortedData)->where('trips_count', '>', 0)->count(),
                    'days_analyzed' => collect($sortedData)->where('has_analysis', true)->count(),
                    'month' => $month
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('獲取可用資料失敗', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '獲取資料失敗：' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 計算資料品質分數
     */
    private function calculateDataQualityScore($data)
    {
        $score = 0;
        
        // ESP32資料點數量
        if ($data['esp32_points'] > 0) {
            $score += min(50, $data['esp32_points'] / 10);
        }
        
        // GPS資料點數量
        if ($data['gps_points'] > 0) {
            $score += min(30, $data['gps_points'] / 10);
        }
        
        // 行程資料
        if ($data['trips_count'] > 0) {
            $score += 20;
        }
        
        return min(100, $score);
    }
    
    /**
     * 執行碳排放分析
     */
    public function analyze(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'force_refresh' => 'sometimes|boolean',
            'data_source' => 'sometimes|string|in:all,esp32,gps,trips'
        ]);
        
        $userId = Auth::id();
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $dataSource = $request->input('data_source', 'all');
        
        // 檢查日期範圍（最多 31 天）
        $daysDiff = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));
        if ($daysDiff > 31) {
            return response()->json([
                'success' => false,
                'message' => '分析期間不能超過 31 天'
            ], 400);
        }
        
        try {
            // 根據資料來源獲取GPS資料
            $gpsData = $this->getGpsDataForAnalysis($userId, $startDate, $endDate, $dataSource);
            
            if (empty($gpsData)) {
                return response()->json([
                    'success' => false,
                    'message' => '所選期間沒有可分析的GPS資料'
                ]);
            }
            
            // 執行分析
            $result = $this->carbonService->analyzeEmissions($userId, $startDate, $endDate);
            
            // 如果服務層分析失敗，嘗試直接使用OpenAI
            if (!$result['success']) {
                Log::info('使用備用OpenAI分析', ['user_id' => $userId]);
                
                $analysisResults = [];
                foreach ($gpsData as $date => $dayData) {
                    $aiResult = $this->openAIService->analyzeTransportMode($dayData);
                    $analysisResults[] = [
                        'date' => $date,
                        'analysis' => $aiResult,
                        'points_count' => count($dayData)
                    ];
                }
                
                $result = [
                    'success' => true,
                    'data' => $analysisResults,
                    'summary' => $this->calculateSummary($analysisResults)
                ];
            }
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            Log::error('碳排放分析失敗', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '分析失敗：' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 根據資料來源獲取GPS資料
     */
    private function getGpsDataForAnalysis($userId, $startDate, $endDate, $dataSource = 'all')
    {
        $gpsData = [];
        
        // 從 gps_tracks 表獲取資料（ESP32上傳的）
        if ($dataSource === 'all' || $dataSource === 'esp32') {
            $tracks = DB::table('gps_tracks')
                ->where('user_id', $userId)
                ->whereBetween('recorded_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->orderBy('recorded_at')
                ->get();
            
            foreach ($tracks as $track) {
                $date = Carbon::parse($track->recorded_at)->format('Y-m-d');
                if (!isset($gpsData[$date])) {
                    $gpsData[$date] = [];
                }
                
                $gpsData[$date][] = [
                    'latitude' => $track->latitude,
                    'longitude' => $track->longitude,
                    'speed' => $track->speed ?? 0,
                    'timestamp' => $track->recorded_at,
                    'source' => 'ESP32'
                ];
            }
        }
        
        // 從 gps_records 表獲取資料（如果需要）
        if (($dataSource === 'all' || $dataSource === 'gps') && DB::getSchemaBuilder()->hasTable('gps_records')) {
            $records = DB::table('gps_records')
                ->where('user_id', $userId)
                ->whereBetween('recorded_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->orderBy('recorded_at')
                ->get();
            
            foreach ($records as $record) {
                $date = Carbon::parse($record->recorded_at)->format('Y-m-d');
                if (!isset($gpsData[$date])) {
                    $gpsData[$date] = [];
                }
                
                $gpsData[$date][] = [
                    'latitude' => $record->latitude,
                    'longitude' => $record->longitude,
                    'speed' => $record->speed ?? 0,
                    'timestamp' => $record->recorded_at,
                    'source' => 'GPS'
                ];
            }
        }
        
        return $gpsData;
    }
    
    /**
     * 計算分析摘要
     */
    private function calculateSummary($analysisResults)
    {
        $totalEmission = 0;
        $totalDistance = 0;
        $totalDuration = 0;
        $transportModes = [];
        
        foreach ($analysisResults as $result) {
            if (isset($result['analysis'])) {
                $analysis = $result['analysis'];
                $totalEmission += $analysis['carbon_emission'] ?? 0;
                $totalDistance += $analysis['total_distance'] ?? 0;
                $totalDuration += $analysis['total_duration'] ?? 0;
                
                $mode = $analysis['transport_mode'] ?? 'unknown';
                if (!isset($transportModes[$mode])) {
                    $transportModes[$mode] = 0;
                }
                $transportModes[$mode]++;
            }
        }
        
        return [
            'total_emission' => round($totalEmission, 3),
            'total_distance' => round($totalDistance, 2),
            'total_duration' => $totalDuration,
            'days_analyzed' => count($analysisResults),
            'transport_modes' => $transportModes,
            'avg_daily_emission' => count($analysisResults) > 0 ? round($totalEmission / count($analysisResults), 3) : 0
        ];
    }
    
    /**
     * 取得歷史分析資料
     */
    public function history(Request $request)
    {
        $userId = Auth::id();
        
        $query = CarbonEmissionAnalysis::where('user_id', $userId);
        
        if ($request->has('month')) {
            $month = Carbon::parse($request->month);
            $query->whereMonth('analysis_date', $month->month)
                  ->whereYear('analysis_date', $month->year);
        }
        
        $analyses = $query->orderBy('analysis_date', 'desc')
                         ->paginate(30);
        
        return response()->json([
            'success' => true,
            'data' => $analyses
        ]);
    }
    
    /**
     * 取得統計資料
     */
    public function statistics(Request $request)
    {
        $userId = Auth::id();
        $period = $request->input('period', 'month'); // month, week, year
        
        $stats = Cache::remember("user_{$userId}_stats_{$period}", 3600, function() use ($userId, $period) {
            $query = CarbonEmissionAnalysis::where('user_id', $userId);
            
            // 根據期間設定查詢範圍
            switch ($period) {
                case 'week':
                    $query->where('analysis_date', '>=', Carbon::now()->startOfWeek());
                    break;
                case 'month':
                    $query->where('analysis_date', '>=', Carbon::now()->startOfMonth());
                    break;
                case 'year':
                    $query->where('analysis_date', '>=', Carbon::now()->startOfYear());
                    break;
            }
            
            $analyses = $query->get();
            
            return [
                'total_emission' => $analyses->sum('carbon_emission'),
                'total_distance' => $analyses->sum('total_distance'),
                'total_duration' => $analyses->sum('total_duration'),
                'days_analyzed' => $analyses->count(),
                'transport_breakdown' => $analyses->groupBy('transport_mode')
                    ->map(function($group) {
                        return [
                            'count' => $group->count(),
                            'emission' => $group->sum('carbon_emission'),
                            'distance' => $group->sum('total_distance')
                        ];
                    })
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
    
    // ===== 測試方法（開發用）=====
    
    /**
     * 測試分析功能
     */
    public function testAnalysis()
    {
        $testData = $this->generateTestGpsData();
        
        try {
            $result = $this->openAIService->analyzeTransportMode($testData);
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 產生測試GPS資料
     */
    private function generateTestGpsData()
    {
        $data = [];
        $baseTime = Carbon::now()->subHours(2);
        $lat = 25.0330;
        $lng = 121.5654;
        
        for ($i = 0; $i < 20; $i++) {
            $data[] = [
                'latitude' => $lat + ($i * 0.001),
                'longitude' => $lng + ($i * 0.001),
                'speed' => rand(20, 40),
                'timestamp' => $baseTime->copy()->addMinutes($i * 2)->toDateTimeString(),
                'altitude' => rand(10, 50),
                'accuracy' => rand(5, 15)
            ];
        }
        
        return $data;
    }
    /**
 * 測試 OpenAI 連線
 */
public function testConnection()
{
    try {
        $result = $this->openAIService->testConnection();
        
        // 記錄測試結果
        Log::info('OpenAI 連線測試', $result);
        
        return response()->json($result);
    } catch (\Exception $e) {
        Log::error('測試連線失敗', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => '測試失敗：' . $e->getMessage(),
            'debug' => [
                'api_key_configured' => !empty(config('services.openai.api_key')),
                'api_url' => config('services.openai.api_url'),
                'model' => config('services.openai.model')
            ]
        ], 500);
    }
}

/**
 * 取得設定資訊（開發用）
 */
public function getConfig()
{
    // 只在開發環境顯示
    if (!app()->environment('local', 'development')) {
        return response()->json(['message' => 'Not available in production'], 403);
    }
    
    $apiKey = config('services.openai.api_key');
    
    return response()->json([
        'openai' => [
            'api_key_exists' => !empty($apiKey),
            'api_key_format' => !empty($apiKey) ? (str_starts_with($apiKey, 'sk-') ? 'valid' : 'invalid') : 'not set',
            'api_key_length' => strlen($apiKey ?? ''),
            'api_url' => config('services.openai.api_url'),
            'model' => config('services.openai.model'),
            'max_tokens' => config('services.openai.max_tokens'),
            'temperature' => config('services.openai.temperature')
        ],
        'environment' => [
            'app_env' => app()->environment(),
            'app_debug' => config('app.debug'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version()
        ]
    ]);
}

/**
 * 清除快取（開發用）
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
            'message' => '清除失敗：' . $e->getMessage()
        ], 500);
    }
}
}
