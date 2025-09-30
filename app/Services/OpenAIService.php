<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class OpenAIService
{
    private $apiKey;
    private $model;
    private $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->model = config('services.openai.model', 'gpt-4o-mini');
        $this->apiUrl = config('services.openai.api_url', 'https://api.openai.com/v1/chat/completions');
    }

    /**
     * 分析碳足跡 - 主要方法
     * 這是 CarbonEmissionController 會調用的方法
     */
    public function analyzeCarbonFootprint(array $gpsData)
    {
        try {
            // 生成快取鍵
            $cacheKey = 'carbon_analysis_' . md5(json_encode($gpsData));
            
            // 檢查快取
            if (Cache::has($cacheKey)) {
                Log::info('從快取返回分析結果');
                return Cache::get($cacheKey);
            }

            Log::info('開始 OpenAI 碳足跡分析', [
                'gps_points' => count($gpsData),
                'first_point' => $gpsData[0] ?? null
            ]);

            // 計算 GPS 統計資料
            $stats = $this->calculateGpsStats($gpsData);
            
            // 構建提示詞
            $systemPrompt = $this->getSystemPrompt();
            $userPrompt = $this->buildAnalysisPrompt($gpsData, $stats);

            // 調用 OpenAI API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($this->apiUrl, [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt
                    ],
                    [
                        'role' => 'user',
                        'content' => $userPrompt
                    ]
                ],
                'temperature' => 0.3,
                'max_tokens' => 2000
            ]);

            if (!$response->successful()) {
                throw new \Exception('OpenAI API 請求失敗: ' . $response->body());
            }

            $responseData = $response->json();
            
            if (!isset($responseData['choices'][0]['message']['content'])) {
                throw new \Exception('OpenAI 回應格式錯誤');
            }

            // 解析 JSON 回應
            $content = $responseData['choices'][0]['message']['content'];
            $result = $this->parseAnalysisResult($content);

            // 驗證結果
            if (!$this->validateAnalysisResult($result)) {
                throw new \Exception('AI 回應格式無效');
            }

            // 快取結果 (1小時)
            Cache::put($cacheKey, $result, 3600);

            Log::info('OpenAI 分析完成', [
                'transport_mode' => $result['transport_mode'] ?? 'unknown',
                'carbon_emission' => $result['carbon_emission'] ?? 0
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('OpenAI 分析錯誤: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            // 返回預設分析結果
            return $this->getDefaultAnalysis($gpsData);
        }
    }

    /**
     * 計算 GPS 統計資料
     */
    private function calculateGpsStats(array $gpsData)
    {
        if (empty($gpsData)) {
            return [
                'total_points' => 0,
                'total_distance' => 0,
                'total_duration' => 0,
                'average_speed' => 0,
                'max_speed' => 0
            ];
        }

        $totalDistance = 0;
        $speeds = [];
        
        for ($i = 0; $i < count($gpsData) - 1; $i++) {
            $distance = $this->calculateDistance(
                $gpsData[$i]['latitude'],
                $gpsData[$i]['longitude'],
                $gpsData[$i + 1]['latitude'],
                $gpsData[$i + 1]['longitude']
            );
            $totalDistance += $distance;
            
            if (isset($gpsData[$i]['speed'])) {
                $speeds[] = $gpsData[$i]['speed'];
            }
        }

        // 計算時間差
        $startTime = strtotime($gpsData[0]['timestamp']);
        $endTime = strtotime($gpsData[count($gpsData) - 1]['timestamp']);
        $totalDuration = $endTime - $startTime;

        return [
            'total_points' => count($gpsData),
            'total_distance' => round($totalDistance, 2),
            'total_duration' => $totalDuration,
            'average_speed' => !empty($speeds) ? round(array_sum($speeds) / count($speeds), 1) : 0,
            'max_speed' => !empty($speeds) ? round(max($speeds), 1) : 0
        ];
    }

    /**
     * 計算兩點之間的距離 (公里)
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // 地球半徑(公里)

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * 系統提示詞
     */
    private function getSystemPrompt()
    {
        return <<<PROMPT
你是一個專業的交通模式和碳排放分析專家。根據提供的GPS資料,你需要:

1. 判斷使用的交通工具(walking/bicycle/motorcycle/car/bus)
2. 計算總行駛距離和時間
3. 根據台灣的碳排放係數計算碳排放量
4. 提供具體的減碳建議

碳排放係數(kg CO2/km):
- walking(步行): 0
- bicycle(腳踏車): 0
- motorcycle(機車): 0.095
- car(汽車): 0.21
- bus(公車): 0.089

判斷標準:
- 步行: 平均速度 < 6 km/h
- 腳踏車: 平均速度 6-20 km/h
- 機車: 平均速度 20-50 km/h,最高速度 40-80 km/h
- 汽車: 平均速度 30-70 km/h,最高速度 > 60 km/h
- 公車: 平均速度 15-40 km/h,有頻繁停留

你必須以JSON格式回應,格式如下:
{
  "transport_mode": "判斷的交通工具",
  "confidence": 0.95,
  "total_distance": 12.5,
  "total_duration": 1800,
  "average_speed": 25.0,
  "max_speed": 45.0,
  "carbon_emission": 1.188,
  "route_analysis": "詳細路線分析說明",
  "suggestions": [
    "建議1:具體可行的減碳方案",
    "建議2:替代交通方式建議"
  ]
}
PROMPT;
    }

    /**
     * 構建分析提示詞
     */
    private function buildAnalysisPrompt(array $gpsData, array $stats)
    {
        // 取樣 GPS 資料(最多20個點)
        $sampleSize = min(20, count($gpsData));
        $step = max(1, floor(count($gpsData) / $sampleSize));
        
        $trajectoryData = [];
        for ($i = 0; $i < count($gpsData); $i += $step) {
            if (isset($gpsData[$i])) {
                $trajectoryData[] = [
                    'time' => date('H:i:s', strtotime($gpsData[$i]['timestamp'])),
                    'lat' => round($gpsData[$i]['latitude'], 6),
                    'lng' => round($gpsData[$i]['longitude'], 6),
                    'speed' => round($gpsData[$i]['speed'] ?? 0, 1)
                ];
            }
        }

        $prompt = "請分析以下GPS資料並判斷交通工具和碳排放:\n\n";
        $prompt .= "=== 統計資料 ===\n";
        $prompt .= "資料點數: {$stats['total_points']}\n";
        $prompt .= "總距離: {$stats['total_distance']} km\n";
        $prompt .= "總時間: " . round($stats['total_duration'] / 60) . " 分鐘\n";
        $prompt .= "平均速度: {$stats['average_speed']} km/h\n";
        $prompt .= "最高速度: {$stats['max_speed']} km/h\n\n";
        
        $prompt .= "=== 軌跡樣本 ===\n";
        $prompt .= json_encode($trajectoryData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        $prompt .= "\n\n請根據以上資料進行分析,並以JSON格式回應。";

        return $prompt;
    }

    /**
     * 解析分析結果
     */
    private function parseAnalysisResult($content)
    {
        // 嘗試從文本中提取 JSON
        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $jsonStr = $matches[0];
            $result = json_decode($jsonStr, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                return $result;
            }
        }

        // 如果無法解析,嘗試直接解析
        $result = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $result;
        }

        throw new \Exception('無法解析 AI 回應: ' . $content);
    }

    /**
     * 驗證分析結果
     */
    private function validateAnalysisResult($result)
    {
        if (!is_array($result)) {
            return false;
        }

        $requiredFields = [
            'transport_mode',
            'total_distance',
            'carbon_emission'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($result[$field])) {
                Log::warning('分析結果缺少必要欄位: ' . $field);
                return false;
            }
        }

        return true;
    }

    /**
     * 獲取預設分析結果(當 AI 失敗時)
     */
    private function getDefaultAnalysis(array $gpsData)
    {
        $stats = $this->calculateGpsStats($gpsData);
        
        // 根據速度簡單判斷交通工具
        $avgSpeed = $stats['average_speed'];
        
        if ($avgSpeed < 6) {
            $mode = 'walking';
            $emissionFactor = 0;
        } elseif ($avgSpeed < 20) {
            $mode = 'bicycle';
            $emissionFactor = 0;
        } elseif ($avgSpeed < 50) {
            $mode = 'motorcycle';
            $emissionFactor = 0.095;
        } else {
            $mode = 'car';
            $emissionFactor = 0.21;
        }

        $carbonEmission = $stats['total_distance'] * $emissionFactor;

        return [
            'transport_mode' => $mode,
            'confidence' => 0.7,
            'total_distance' => $stats['total_distance'],
            'total_duration' => $stats['total_duration'],
            'average_speed' => $stats['average_speed'],
            'max_speed' => $stats['max_speed'],
            'carbon_emission' => round($carbonEmission, 3),
            'route_analysis' => '基於速度分析的自動判斷結果',
            'suggestions' => [
                '建議使用更環保的交通方式',
                '考慮拼車或使用大眾運輸工具',
                '短程距離可考慮步行或騎自行車'
            ]
        ];
    }

    /**
     * 別名方法 - 為了兼容性
     */
    public function analyzeCarbonEmission(array $gpsData)
    {
        return $this->analyzeCarbonFootprint($gpsData);
    }

    /**
     * 分析交通模式
     */
    public function analyzeTransportMode(array $gpsData)
    {
        return $this->analyzeCarbonFootprint($gpsData);
    }
}