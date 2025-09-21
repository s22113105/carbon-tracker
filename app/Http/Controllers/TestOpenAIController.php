<?php

namespace App\Http\Controllers;

use App\Services\OpenAIService;
use App\Models\GpsData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TestOpenAIController extends Controller
{
    protected $openAIService;
    
    public function __construct(OpenAIService $openAIService)
    {
        $this->openAIService = $openAIService;
    }
    
    /**
     * 測試 OpenAI 連接
     */
    public function testConnection()
    {
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
     * 測試分析功能（使用模擬資料）
     */
    public function testAnalysis()
    {
        // 模擬 GPS 資料 - 機車行程
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
     * 測試不同交通工具的分析
     */
    public function testAllModes()
    {
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
        
        return response()->json([
            'success' => true,
            'test_results' => $results,
            'accuracy' => $this->calculateAccuracy($results)
        ]);
    }
    
    /**
     * 測試真實資料分析
     */
    public function testRealData(Request $request)
    {
        $request->validate([
            'date' => 'required|date'
        ]);
        
        $userId = Auth::id();
        $date = $request->date;
        
        // 獲取真實 GPS 資料
        $gpsData = GpsData::where('user_id', $userId)
            ->whereDate('date', $date)
            ->orderBy('timestamp')
            ->get();
        
        if ($gpsData->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => '該日期沒有 GPS 資料'
            ]);
        }
        
        // 轉換為分析格式
        $analysisData = $gpsData->map(function($point) {
            return [
                'latitude' => $point->latitude,
                'longitude' => $point->longitude,
                'speed' => $point->speed,
                'timestamp' => $point->timestamp,
                'altitude' => $point->altitude,
                'accuracy' => $point->accuracy
            ];
        })->toArray();
        
        // 執行分析
        $result = $this->openAIService->analyzeTransportMode($analysisData);
        
        return response()->json([
            'success' => true,
            'date' => $date,
            'gps_points' => count($analysisData),
            'analysis' => $result,
            'suggestions_count' => count($result['suggestions'] ?? [])
        ]);
    }
    
    /**
     * 生成模擬 GPS 資料
     */
    private function generateMockGpsData($mode)
    {
        $baseTime = strtotime('2025-01-21 09:00:00');
        $data = [];
        
        // 根據交通工具設定參數
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
        $lat = 25.0330;  // 台北緯度
        $lng = 121.5654; // 台北經度
        
        for ($i = 0; $i < $config['points']; $i++) {
            // 計算速度（加入隨機變化）
            $speed = $config['avg_speed'] + rand(-$config['speed_range'], $config['speed_range']);
            $speed = max(0, $speed); // 確保速度不為負
            
            // 公車模式加入停站
            if (isset($config['stops']) && $i % 10 === 5) {
                $speed = 0; // 停站
            }
            
            // 更新位置
            $lat += $config['distance_per_point'] / 111 * (rand(80, 120) / 100);
            $lng += $config['distance_per_point'] / 111 * (rand(80, 120) / 100);
            
            // 時間間隔（30秒）
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
     * 計算準確率
     */
    private function calculateAccuracy($results)
    {
        $correct = 0;
        $total = count($results);
        
        foreach ($results as $result) {
            if ($result['is_correct']) {
                $correct++;
            }
        }
        
        return [
            'correct' => $correct,
            'total' => $total,
            'percentage' => round(($correct / $total) * 100, 2) . '%'
        ];
    }
}