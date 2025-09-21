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
     * 執行碳排放分析
     */
    public function analyze(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'force_refresh' => 'sometimes|boolean'
        ]);
        
        $userId = Auth::id();
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        
        // 檢查日期範圍（最多 31 天）
        $daysDiff = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));
        if ($daysDiff > 31) {
            return response()->json([
                'success' => false,
                'message' => '分析期間不能超過 31 天'
            ], 400);
        }
        
        $result = $this->carbonService->analyzeEmissions($userId, $startDate, $endDate);
        
        return response()->json($result);
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
        $period = $request->get('period', 'month');
        
        $startDate = match($period) {
            'week' => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            'year' => Carbon::now()->startOfYear(),
            default => Carbon::now()->startOfMonth()
        };
        
        $analyses = CarbonEmissionAnalysis::where('user_id', $userId)
            ->where('analysis_date', '>=', $startDate)
            ->get();
        
        // 按交通工具分組統計
        $byTransport = $analyses->groupBy('transport_mode')->map(function($group) {
            return [
                'count' => $group->count(),
                'total_emission' => $group->sum('carbon_emission'),
                'total_distance' => $group->sum('total_distance'),
                'percentage' => 0
            ];
        });
        
        $totalEmission = $analyses->sum('carbon_emission');
        
        // 計算百分比
        foreach ($byTransport as $mode => &$data) {
            $data['percentage'] = $totalEmission > 0 ? 
                round(($data['total_emission'] / $totalEmission) * 100, 1) : 0;
        }
        
        // 每日趨勢
        $dailyTrend = $analyses->groupBy(function($item) {
            return $item->analysis_date->format('Y-m-d');
        })->map(function($group) {
            return [
                'date' => $group->first()->analysis_date->format('Y-m-d'),
                'emission' => $group->sum('carbon_emission'),
                'distance' => $group->sum('total_distance')
            ];
        })->values();
        
        return response()->json([
            'success' => true,
            'statistics' => [
                'period' => $period,
                'total_emission' => round($totalEmission, 2),
                'total_distance' => round($analyses->sum('total_distance'), 2),
                'total_duration' => $analyses->sum('total_duration'),
                'average_daily_emission' => $analyses->count() > 0 ? 
                    round($totalEmission / $analyses->count(), 2) : 0,
                'by_transport' => $byTransport,
                'daily_trend' => $dailyTrend,
                'eco_score' => $this->calculateOverallEcoScore($analyses)
            ]
        ]);
    }
    
    // ========== 測試功能（開發用）==========
    
    /**
     * 測試 OpenAI 連接
     */
    public function testConnection()
    {
        // 檢查是否為開發環境或特定使用者
        if (!$this->isTestingAllowed()) {
            abort(404);
        }
        
        $isConnected = $this->openAIService->testConnection();
        
        return response()->json([
            'success' => $isConnected,
            'message' => $isConnected ? 'OpenAI API 連接成功！' : 'OpenAI API 連接失敗，請檢查 API Key',
            'config' => [
                'model' => config('services.openai.model'),
                'api_url' => config('services.openai.api_url'),
                'has_api_key' => !empty(config('services.openai.api_key'))
            ]
        ]);
    }
    
    /**
     * 測試分析功能
     */
    public function testAnalysis()
    {
        if (!$this->isTestingAllowed()) {
            abort(404);
        }
        
        // 模擬機車 GPS 資料
        $mockGpsData = $this->generateMockGpsData('motorcycle');
        
        $result = $this->openAIService->analyzeTransportMode($mockGpsData);
        
        return response()->json([
            'success' => true,
            'message' => '分析完成',
            'mock_data_type' => 'motorcycle',
            'analysis_result' => $result,
            'input_stats' => [
                'points' => count($mockGpsData),
                'first_point' => $mockGpsData[0],
                'last_point' => end($mockGpsData)
            ]
        ]);
    }
    
    /**
     * 測試所有交通工具
     */
    public function testAllModes()
    {
        if (!$this->isTestingAllowed()) {
            abort(404);
        }
        
        $modes = ['walking', 'bicycle', 'motorcycle', 'car', 'bus'];
        $results = [];
        
        foreach ($modes as $mode) {
            $mockData = $this->generateMockGpsData($mode);
            $analysis = $this->openAIService->analyzeTransportMode($mockData);
            
            $results[$mode] = [
                'input_points' => count($mockData),
                'detected_mode' => $analysis['transport_mode'],
                'confidence' => $analysis['confidence'] ?? 0,
                'carbon_emission' => $analysis['carbon_emission'],
                'is_correct' => $analysis['transport_mode'] === $mode
            ];
        }
        
        // 計算準確率
        $correct = array_filter($results, fn($r) => $r['is_correct']);
        $accuracy = [
            'correct' => count($correct),
            'total' => count($results),
            'percentage' => round((count($correct) / count($results)) * 100, 2) . '%'
        ];
        
        return response()->json([
            'success' => true,
            'test_results' => $results,
            'accuracy' => $accuracy
        ]);
    }
    
    /**
     * 取得 API 設定
     */
    public function getConfig()
    {
        if (!$this->isTestingAllowed()) {
            abort(404);
        }
        
        return response()->json([
            'model' => config('services.openai.model'),
            'max_tokens' => config('services.openai.max_tokens'),
            'temperature' => config('services.openai.temperature'),
            'timeout' => config('services.openai.timeout'),
            'has_key' => !empty(config('services.openai.api_key')),
            'environment' => app()->environment()
        ]);
    }
    
    /**
     * 清除快取
     */
    public function clearCache()
    {
        if (!$this->isTestingAllowed()) {
            abort(404);
        }
        
        // 清除 AI 分析快取
        Cache::tags(['ai_analysis'])->flush();
        
        // 如果沒有使用 tags，清除特定前綴的快取
        $userId = Auth::id();
        for ($i = 0; $i < 31; $i++) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            Cache::forget("carbon_analysis_{$userId}_{$date}");
        }
        
        return response()->json([
            'success' => true,
            'message' => '快取已清除'
        ]);
    }
    
    /**
     * 檢查是否允許測試功能
     */
    private function isTestingAllowed()
    {
        // 開發環境允許
        if (app()->environment('local', 'development')) {
            return true;
        }
        
        // 特定使用者 ID 允許（請改成你的使用者 ID）
        $allowedUsers = [1]; // 管理員 ID
        if (in_array(Auth::id(), $allowedUsers)) {
            return true;
        }
        
        // 特定 IP 允許
        $allowedIPs = ['127.0.0.1', '::1'];
        if (in_array(request()->ip(), $allowedIPs)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * 生成模擬 GPS 資料
     */
    private function generateMockGpsData($mode)
    {
        $baseTime = strtotime('2025-01-21 09:00:00');
        $data = [];
        
        $params = [
            'walking' => [
                'points' => 30,
                'avg_speed' => 4,
                'speed_range' => 2,
                'distance_per_point' => 0.02
            ],
            'bicycle' => [
                'points' => 40,
                'avg_speed' => 15,
                'speed_range' => 5,
                'distance_per_point' => 0.1
            ],
            'motorcycle' => [
                'points' => 50,
                'avg_speed' => 30,
                'speed_range' => 15,
                'distance_per_point' => 0.2
            ],
            'car' => [
                'points' => 60,
                'avg_speed' => 45,
                'speed_range' => 20,
                'distance_per_point' => 0.3
            ],
            'bus' => [
                'points' => 45,
                'avg_speed' => 20,
                'speed_range' => 10,
                'distance_per_point' => 0.15,
                'stops' => true
            ]
        ];
        
        $config = $params[$mode];
        $lat = 24.1477;  // 台南緯度
        $lng = 120.6736; // 台南經度
        
        for ($i = 0; $i < $config['points']; $i++) {
            $speed = $config['avg_speed'] + rand(-$config['speed_range'], $config['speed_range']);
            $speed = max(0, $speed);
            
            if (isset($config['stops']) && $i % 10 === 5) {
                $speed = 0;
            }
            
            $lat += $config['distance_per_point'] / 111 * (rand(80, 120) / 100);
            $lng += $config['distance_per_point'] / 111 * (rand(80, 120) / 100);
            
            $timestamp = date('Y-m-d H:i:s', $baseTime + ($i * 30));
            
            $data[] = [
                'latitude' => round($lat, 8),
                'longitude' => round($lng, 8),
                'speed' => round($speed, 2),
                'timestamp' => $timestamp,
                'altitude' => rand(10, 50),
                'accuracy' => rand(5, 15)
            ];
        }
        
        return $data;
    }
    
    /**
     * 計算整體環保分數
     */
    private function calculateOverallEcoScore($analyses)
    {
        if ($analyses->isEmpty()) return 100;
        
        $totalEmission = $analyses->sum('carbon_emission');
        $totalDistance = $analyses->sum('total_distance');
        
        if ($totalDistance == 0) return 100;
        
        $emissionPerKm = $totalEmission / $totalDistance;
        $carBaseline = 0.21;
        
        $score = max(0, 100 - ($emissionPerKm / $carBaseline * 100));
        
        return round($score);
    }
}