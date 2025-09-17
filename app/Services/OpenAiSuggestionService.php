<?php

namespace App\Services;

use App\Models\CarbonEmission;
use App\Models\Trip;
use App\Models\GpsData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OpenAiSuggestionService
{
    private $openaiApiKey;
    private $baseUrl = 'https://api.openai.com/v1/chat/completions';

    public function __construct()
    {
        $this->openaiApiKey = config('services.openai.api_key');
    }

    /**
     * 為使用者生成 AI 減碳建議
     */
    public function generateSuggestionsForUser($userId, $dateRange = 30)
    {
        try {
            // 收集使用者資料
            $userData = $this->collectUserData($userId, $dateRange);
            
            if (empty($userData['trips'])) {
                return [
                    'success' => false,
                    'message' => '目前資料不足，請累積更多通勤記錄後再查看建議。'
                ];
            }

            // 呼叫 OpenAI API
            $suggestions = $this->callOpenAiApi($userData);
            
            return [
                'success' => true,
                'suggestions' => $suggestions,
                'data_summary' => $userData['summary']
            ];

        } catch (\Exception $e) {
            Log::error('AI 建議生成失敗: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'AI 分析服務暫時無法使用，請稍後再試。',
                'fallback' => $this->generateBasicSuggestions($userData ?? [])
            ];
        }
    }

    /**
     * 分析 GPS 資料並判斷交通工具
     */
    public function analyzeGpsDataForTransportMode($gpsDataArray)
    {
        if (empty($gpsDataArray)) {
            return null;
        }

        // 準備 GPS 分析資料
        $analysisData = $this->prepareGpsAnalysisData($gpsDataArray);
        
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($this->baseUrl, [
                'model' => 'gpt-4-turbo-preview',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->getTransportAnalysisPrompt()
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode($analysisData)
                    ]
                ],
                'max_tokens' => 500,
                'temperature' => 0.3
            ]);

            if ($response->successful()) {
                $result = $response->json();
                return $this->parseTransportAnalysisResult($result['choices'][0]['message']['content']);
            }

            return null;

        } catch (\Exception $e) {
            Log::error('GPS 交通工具分析失敗: ' . $e->getMessage());
            return $this->fallbackTransportAnalysis($analysisData);
        }
    }

    /**
     * 收集使用者資料
     */
    private function collectUserData($userId, $dateRange)
    {
        $startDate = now()->subDays($dateRange);
        
        // 取得行程資料
        $trips = Trip::where('user_id', $userId)
            ->where('start_time', '>=', $startDate)
            ->with('carbonEmission')
            ->get();
            
        // 取得碳排放資料
        $carbonEmissions = CarbonEmission::where('user_id', $userId)
            ->where('emission_date', '>=', $startDate)
            ->get();

        // 統計資料
        $transportStats = $carbonEmissions->groupBy('transport_mode')
            ->map(function ($group, $mode) {
                return [
                    'mode' => $mode,
                    'count' => $group->count(),
                    'total_emission' => round($group->sum('co2_emission'), 2),
                    'total_distance' => round($group->sum('distance'), 2),
                    'avg_emission_per_km' => $group->sum('distance') > 0 
                        ? round($group->sum('co2_emission') / $group->sum('distance'), 3)
                        : 0
                ];
            })->values();

        // 每日統計
        $dailyStats = $carbonEmissions->groupBy('emission_date')
            ->map(function ($group, $date) {
                return [
                    'date' => $date,
                    'total_emission' => round($group->sum('co2_emission'), 2),
                    'total_distance' => round($group->sum('distance'), 2),
                    'trip_count' => $group->count(),
                    'transport_modes' => $group->pluck('transport_mode')->unique()->values()
                ];
            })->sortBy('date')->values();

        return [
            'trips' => $trips,
            'transport_stats' => $transportStats,
            'daily_stats' => $dailyStats,
            'summary' => [
                'date_range' => $dateRange,
                'total_trips' => $trips->count(),
                'total_emission' => round($carbonEmissions->sum('co2_emission'), 2),
                'total_distance' => round($carbonEmissions->sum('distance'), 2),
                'avg_daily_emission' => round($carbonEmissions->avg('co2_emission'), 2),
                'dominant_transport' => $transportStats->sortByDesc('count')->first()['mode'] ?? 'unknown',
                'active_days' => $dailyStats->count()
            ]
        ];
    }

    /**
     * 呼叫 OpenAI API
     */
    private function callOpenAiApi($userData)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->openaiApiKey,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post($this->baseUrl, [
            'model' => 'gpt-4-turbo-preview',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->getSuggestionPrompt()
                ],
                [
                    'role' => 'user',
                    'content' => $this->formatUserDataForAi($userData)
                ]
            ],
            'max_tokens' => 1000,
            'temperature' => 0.7
        ]);

        if (!$response->successful()) {
            throw new \Exception('OpenAI API 請求失敗: ' . $response->body());
        }

        $result = $response->json();
        return $this->parseSuggestionResult($result['choices'][0]['message']['content']);
    }

    /**
     * 取得建議生成的系統提示詞
     */
    private function getSuggestionPrompt()
    {
        return "你是一個專業的碳排放分析師和環保顧問。請根據使用者的通勤資料，提供個人化的減碳建議。

你的任務：
1. 分析使用者的交通工具使用模式
2. 計算潛在的減碳空間
3. 提供實用且可行的建議
4. 預估改善後的減碳效果

請以 JSON 格式回傳結果，包含以下欄位：
{
    \"analysis\": \"使用者通勤模式的分析摘要\",
    \"carbon_footprint\": \"碳足跡評估\",
    \"suggestions\": [
        {
            \"category\": \"建議分類\",
            \"title\": \"建議標題\",
            \"description\": \"詳細說明\",
            \"potential_reduction\": \"預估減碳量(kg CO2)\",
            \"difficulty\": \"實施難度 (簡單/中等/困難)\"
        }
    ],
    \"monthly_impact\": \"每月總減碳潛力\",
    \"environmental_equivalent\": \"環境等效說明 (如種樹棵數)\"
}

請用繁體中文回答，建議要實用且考慮台灣的交通環境。";
    }

    /**
     * 取得交通工具分析的系統提示詞
     */
    private function getTransportAnalysisPrompt()
    {
        return "你是 GPS 軌跡分析專家。根據提供的 GPS 資料分析使用者的交通工具。

分析要素：
1. 平均速度 (km/h)
2. 最大速度
3. 移動路徑的規律性
4. 停留時間模式
5. 路徑特徵

交通工具類型：
- walking: 步行 (2-6 km/h)
- bicycle: 腳踏車 (8-20 km/h)
- motorcycle: 機車 (20-60 km/h)
- car: 汽車 (10-100 km/h，市區較低)
- bus: 公車 (10-50 km/h，有固定停靠站)

請以 JSON 格式回傳：
{
    \"transport_mode\": \"交通工具類型\",
    \"confidence\": \"信心度 (0-1)\",
    \"avg_speed\": \"平均速度\",
    \"analysis\": \"分析說明\"
}";
    }

    /**
     * 格式化使用者資料供 AI 分析
     */
    private function formatUserDataForAi($userData)
    {
        return json_encode([
            '分析期間' => $userData['summary']['date_range'] . ' 天',
            '總行程次數' => $userData['summary']['total_trips'],
            '總碳排放量' => $userData['summary']['total_emission'] . ' kg CO2',
            '總行駛距離' => $userData['summary']['total_distance'] . ' km',
            '主要交通工具' => $userData['summary']['dominant_transport'],
            '活躍天數' => $userData['summary']['active_days'],
            '交通工具統計' => $userData['transport_stats'],
            '每日統計' => $userData['daily_stats']
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 解析 AI 回傳的建議結果
     */
    private function parseSuggestionResult($content)
    {
        try {
            // 嘗試解析 JSON
            $result = json_decode($content, true);
            if ($result) {
                return $result;
            }
        } catch (\Exception $e) {
            Log::warning('AI 回傳內容不是有效 JSON，嘗試文字解析');
        }

        // 如果不是 JSON，返回原始文字
        return [
            'analysis' => $content,
            'suggestions' => [],
            'monthly_impact' => '無法計算',
            'environmental_equivalent' => '無法計算'
        ];
    }

    /**
     * 準備 GPS 分析資料
     */
    private function prepareGpsAnalysisData($gpsDataArray)
    {
        $points = collect($gpsDataArray)->map(function ($point) {
            return [
                'lat' => $point['latitude'],
                'lng' => $point['longitude'],
                'timestamp' => $point['timestamp'],
                'speed' => $point['speed'] ?? 0
            ];
        })->sortBy('timestamp');

        // 計算速度和距離
        $speeds = [];
        $totalDistance = 0;
        $totalTime = 0;

        $prevPoint = null;
        foreach ($points as $point) {
            if ($prevPoint) {
                $distance = $this->calculateDistance(
                    $prevPoint['lat'], $prevPoint['lng'],
                    $point['lat'], $point['lng']
                );
                $timeDiff = (strtotime($point['timestamp']) - strtotime($prevPoint['timestamp'])) / 3600; // 小時
                
                if ($timeDiff > 0 && $distance > 0) {
                    $speed = $distance / $timeDiff; // km/h
                    $speeds[] = $speed;
                    $totalDistance += $distance;
                    $totalTime += $timeDiff;
                }
            }
            $prevPoint = $point;
        }

        return [
            'total_points' => $points->count(),
            'total_distance' => round($totalDistance, 3),
            'total_time_hours' => round($totalTime, 2),
            'avg_speed' => $totalTime > 0 ? round($totalDistance / $totalTime, 2) : 0,
            'max_speed' => !empty($speeds) ? round(max($speeds), 2) : 0,
            'min_speed' => !empty($speeds) ? round(min($speeds), 2) : 0,
            'speed_variance' => !empty($speeds) ? round($this->calculateVariance($speeds), 2) : 0,
            'start_time' => $points->first()['timestamp'],
            'end_time' => $points->last()['timestamp']
        ];
    }

    /**
     * 計算兩點間距離
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371;
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earthRadius * $c;
    }

    /**
     * 計算變異數
     */
    private function calculateVariance($values)
    {
        $mean = array_sum($values) / count($values);
        $squareDiffs = array_map(function($value) use ($mean) {
            return pow($value - $mean, 2);
        }, $values);
        
        return array_sum($squareDiffs) / count($squareDiffs);
    }

    /**
     * 解析交通工具分析結果
     */
    private function parseTransportAnalysisResult($content)
    {
        try {
            return json_decode($content, true);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 後備交通工具分析
     */
    private function fallbackTransportAnalysis($analysisData)
    {
        $avgSpeed = $analysisData['avg_speed'];
        
        if ($avgSpeed < 6) {
            $mode = 'walking';
        } elseif ($avgSpeed < 20) {
            $mode = 'bicycle';
        } elseif ($avgSpeed < 35) {
            $mode = 'motorcycle';
        } else {
            $mode = 'car';
        }

        return [
            'transport_mode' => $mode,
            'confidence' => 0.6,
            'avg_speed' => $avgSpeed,
            'analysis' => '基於平均速度的基礎分析'
        ];
    }

    /**
     * 基礎建議生成
     */
    private function generateBasicSuggestions($userData)
    {
        if (empty($userData)) {
            return '請累積更多通勤資料以獲得個人化建議。';
        }

        $totalEmission = $userData['summary']['total_emission'] ?? 0;
        $dominantTransport = $userData['summary']['dominant_transport'] ?? 'unknown';
        
        $suggestions = "根據您的通勤資料分析：\n\n";
        $suggestions .= "• 總碳排放量：{$totalEmission} kg CO2\n";
        $suggestions .= "• 主要交通工具：" . $this->getTransportLabel($dominantTransport) . "\n\n";
        $suggestions .= "基礎減碳建議：\n";
        $suggestions .= "1. 考慮使用大眾運輸工具\n";
        $suggestions .= "2. 短距離可改為步行或騎腳踏車\n";
        $suggestions .= "3. 規劃合併行程減少出行次數\n";
        $suggestions .= "4. 選擇離峰時段出行提高效率\n";
        
        return $suggestions;
    }

    /**
     * 取得交通工具中文標籤
     */
    private function getTransportLabel($transport)
    {
        $labels = [
            'walking' => '步行',
            'bicycle' => '腳踏車',
            'motorcycle' => '機車',
            'car' => '汽車',
            'bus' => '公車'
        ];
        
        return $labels[$transport] ?? '未知';
    }
}