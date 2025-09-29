<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class OpenAIService
{
    protected $apiKey;
    protected $apiUrl;
    protected $model;
    protected $maxTokens;
    protected $temperature;
    protected $timeout;
    
    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->apiUrl = config('services.openai.api_url');
        $this->model = config('services.openai.model');
        $this->maxTokens = config('services.openai.max_tokens');
        $this->temperature = config('services.openai.temperature');
        $this->timeout = config('services.openai.timeout');

        // 檢查 API Key 是否存在
        if (empty($this->apiKey)) {
            Log::error('OpenAI API Key 未設定！請在 .env 檔案中設定 OPENAI_API_KEY');
        }
    }
    

    /**
     * 測試 OpenAI 連線
     */
    public function testConnection()
    {
        try {
            // 檢查 API Key
            if (empty($this->apiKey)) {
                return [
                    'success' => false,
                    'message' => 'API Key 未設定，請在 .env 檔案中設定 OPENAI_API_KEY',
                    'debug' => [
                        'api_key_exists' => false,
                        'api_key_length' => 0
                    ]
                ];
            }
            
            Log::info('測試 OpenAI 連線', [
                'api_url' => $this->apiUrl . '/v1/chat/completions',
                'model' => $this->model,
                'timeout' => $this->timeout
            ]);
            
            // 發送測試請求
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->timeout)
            ->post($this->apiUrl . '/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a helpful assistant.'
                    ],
                    [
                        'role' => 'user',
                        'content' => 'Hello, this is a connection test. Please respond with "Connection successful".'
                    ]
                ],
                'max_tokens' => 50,
                'temperature' => 0.5,
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'message' => '連線成功',
                    'response' => $data['choices'][0]['message']['content'] ?? 'No response',
                    'model' => $data['model'] ?? $this->model,
                    'usage' => $data['usage'] ?? null
                ];
            } else {
                $error = $response->json();
                $statusCode = $response->status();
                
                // 處理常見錯誤
                if ($statusCode === 401) {
                    return [
                        'success' => false,
                        'message' => 'API Key 無效或已過期，請檢查您的 OpenAI API Key',
                        'error' => $error['error']['message'] ?? 'Unauthorized',
                        'status_code' => $statusCode
                    ];
                } elseif ($statusCode === 429) {
                    return [
                        'success' => false,
                        'message' => 'API 請求超過限制，請稍後再試或檢查您的 OpenAI 帳戶配額',
                        'error' => $error['error']['message'] ?? 'Rate limit exceeded',
                        'status_code' => $statusCode
                    ];
                } elseif ($statusCode === 404) {
                    return [
                        'success' => false,
                        'message' => 'API 端點不存在，請檢查 API URL 設定',
                        'error' => $error['error']['message'] ?? 'Not found',
                        'status_code' => $statusCode
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => 'API 請求失敗',
                        'status_code' => $statusCode,
                        'error' => $error['error']['message'] ?? 'Unknown error',
                        'type' => $error['error']['type'] ?? null,
                        'code' => $error['error']['code'] ?? null
                    ];
                }
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('OpenAI 連線超時', [
                'error' => $e->getMessage(),
                'timeout' => $this->timeout
            ]);
            
            return [
                'success' => false,
                'message' => '連線超時，請檢查網路連線或增加超時時間',
                'error' => $e->getMessage(),
                'timeout' => $this->timeout
            ];
        } catch (\Exception $e) {
            Log::error('OpenAI 連線測試失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => '連線失敗：' . $e->getMessage(),
                'exception' => get_class($e)
            ];
        }
    }
    
    /**
     * 分析 GPS 資料並判斷交通工具和碳排放
     */
    public function analyzeTransportMode(array $gpsData)
    {
        try {
            // 建立快取鍵值
            $cacheKey = 'ai_analysis_' . md5(json_encode($gpsData));
            
            // 檢查快取
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }
            
            $prompt = $this->buildAnalysisPrompt($gpsData);
            
            // 使用 Laravel HTTP Client 呼叫 OpenAI API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->timeout)
            ->post($this->apiUrl, [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->getSystemPrompt()
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => $this->temperature,
                'max_tokens' => $this->maxTokens,
                'response_format' => ['type' => 'json_object']
            ]);
            
            if (!$response->successful()) {
                throw new \Exception('OpenAI API 請求失敗: ' . $response->body());
            }
            
            $responseData = $response->json();
            
            if (!isset($responseData['choices'][0]['message']['content'])) {
                throw new \Exception('OpenAI 回應格式錯誤');
            }
            
            $result = json_decode($responseData['choices'][0]['message']['content'], true);
            
            // 驗證回應格式
            if (!$this->validateResponse($result)) {
                throw new \Exception('AI 回應格式無效');
            }
            
            // 快取結果 (24小時)
            Cache::put($cacheKey, $result, 86400);
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('OpenAI 分析錯誤: ' . $e->getMessage());
            Log::error('錯誤詳情: ' . json_encode([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]));
            
            // 回傳預設分析結果
            return $this->getDefaultAnalysis($gpsData);
        }
    }
    
    /**
     * 建立系統提示詞
     */
    private function getSystemPrompt()
    {
        return <<<PROMPT
你是一個專業的交通模式和碳排放分析專家。根據提供的GPS資料（包含經緯度、時間戳記、速度），你需要：

1. 判斷使用的交通工具（walking/bicycle/motorcycle/car/bus）
2. 計算總行駛距離和時間
3. 根據台灣的碳排放係數計算碳排放量
4. 提供具體的減碳建議

碳排放係數（kg CO2/km）：
- walking（步行）: 0
- bicycle（腳踏車）: 0
- motorcycle（機車）: 0.095
- car（汽車）: 0.21
- bus（公車）: 0.089

判斷標準：
- 步行: 平均速度 < 6 km/h，最高速度 < 10 km/h
- 腳踏車: 平均速度 6-20 km/h，最高速度 < 30 km/h
- 機車: 平均速度 20-40 km/h，最高速度 40-80 km/h，路線多為市區
- 汽車: 平均速度 30-60 km/h，最高速度 > 60 km/h
- 公車: 平均速度 15-30 km/h，有頻繁停留（站點）

你必須以JSON格式回應，格式如下：
{
  "transport_mode": "判斷的交通工具",
  "confidence": 0.95,
  "total_distance": 12.5,
  "total_duration": 1800,
  "average_speed": 25.0,
  "max_speed": 45.0,
  "carbon_emission": 2.625,
  "route_analysis": "詳細路線分析說明",
  "suggestions": [
    "建議1：具體可行的減碳方案",
    "建議2：替代交通方式建議",
    "建議3：路線優化建議"
  ],
  "journey_segments": [
    {
      "start_time": "09:00",
      "end_time": "09:15",
      "distance": 5.2,
      "mode": "car",
      "emission": 1.092
    }
  ]
}
PROMPT;
    }
    
    /**
     * 建立分析提示詞
     */
    private function buildAnalysisPrompt(array $gpsData)
    {
        $stats = $this->calculateGpsStats($gpsData);
        
        // 準備軌跡資料摘要
        $trajectoryData = [];
        $sampleSize = min(20, count($gpsData)); // 最多取20個點
        $step = max(1, floor(count($gpsData) / $sampleSize));
        
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
        
        return sprintf(
            "請分析以下GPS資料並判斷交通工具和碳排放：\n\n" .
            "=== 統計資料 ===\n" .
            "日期：%s\n" .
            "資料點數量：%d\n" .
            "總時間：%d 秒 (%s)\n" .
            "計算距離：%.2f 公里\n" .
            "平均速度：%.2f km/h\n" .
            "最高速度：%.2f km/h\n" .
            "最低速度：%.2f km/h\n" .
            "速度標準差：%.2f\n" .
            "停留次數（速度<2km/h）：%d\n" .
            "速度變化頻率：%s\n\n" .
            "=== GPS軌跡資料（採樣）===\n%s\n\n" .
            "請根據以上資料判斷交通工具，計算碳排放，並提供減碳建議。",
            $stats['date'],
            $stats['point_count'],
            $stats['total_duration'],
            $this->formatDuration($stats['total_duration']),
            $stats['total_distance'],
            $stats['avg_speed'],
            $stats['max_speed'],
            $stats['min_speed'],
            $stats['speed_std'],
            $stats['stop_count'],
            $stats['speed_variation'],
            json_encode($trajectoryData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
    
    /**
     * 計算 GPS 統計資料
     */
    private function calculateGpsStats(array $gpsData)
    {
        if (empty($gpsData)) {
            return $this->getEmptyStats();
        }
        
        $totalDistance = 0;
        $speeds = [];
        $stopCount = 0;
        $speedChanges = 0;
        $lastSpeed = null;
        
        // 確保資料按時間排序
        usort($gpsData, function($a, $b) {
            return strtotime($a['timestamp']) - strtotime($b['timestamp']);
        });
        
        for ($i = 1; $i < count($gpsData); $i++) {
            // 計算距離
            $distance = $this->calculateDistance(
                $gpsData[$i-1]['latitude'],
                $gpsData[$i-1]['longitude'],
                $gpsData[$i]['latitude'],
                $gpsData[$i]['longitude']
            );
            
            $totalDistance += $distance;
            
            // 計算速度
            $speed = $gpsData[$i]['speed'] ?? 0;
            
            // 如果沒有速度資料，從距離和時間計算
            if ($speed == 0 && $distance > 0) {
                $timeDiff = strtotime($gpsData[$i]['timestamp']) - strtotime($gpsData[$i-1]['timestamp']);
                if ($timeDiff > 0) {
                    $speed = ($distance / $timeDiff) * 3600; // 轉換為 km/h
                }
            }
            
            $speeds[] = $speed;
            
            // 計算停留次數
            if ($speed < 2) {
                $stopCount++;
            }
            
            // 計算速度變化頻率
            if ($lastSpeed !== null && abs($speed - $lastSpeed) > 10) {
                $speedChanges++;
            }
            $lastSpeed = $speed;
        }
        
        $firstPoint = $gpsData[0];
        $lastPoint = end($gpsData);
        $totalDuration = strtotime($lastPoint['timestamp']) - strtotime($firstPoint['timestamp']);
        
        // 計算速度變化頻率描述
        $speedVariation = 'stable';
        if ($speedChanges > count($gpsData) * 0.3) {
            $speedVariation = 'high';
        } elseif ($speedChanges > count($gpsData) * 0.1) {
            $speedVariation = 'medium';
        }
        
        return [
            'date' => date('Y-m-d', strtotime($firstPoint['timestamp'])),
            'point_count' => count($gpsData),
            'total_duration' => max(1, $totalDuration), // 至少1秒
            'total_distance' => round($totalDistance, 2),
            'avg_speed' => $speeds ? round(array_sum($speeds) / count($speeds), 2) : 0,
            'max_speed' => $speeds ? round(max($speeds), 2) : 0,
            'min_speed' => $speeds ? round(min($speeds), 2) : 0,
            'speed_std' => round($this->calculateStandardDeviation($speeds), 2),
            'stop_count' => $stopCount,
            'speed_variation' => $speedVariation
        ];
    }
    
    /**
     * 取得空資料統計
     */
    private function getEmptyStats()
    {
        return [
            'date' => date('Y-m-d'),
            'point_count' => 0,
            'total_duration' => 0,
            'total_distance' => 0,
            'avg_speed' => 0,
            'max_speed' => 0,
            'min_speed' => 0,
            'speed_std' => 0,
            'stop_count' => 0,
            'speed_variation' => 'none'
        ];
    }
    
    /**
     * 計算兩點間距離（Haversine公式）
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // 地球半徑（公里）
        
        $lat1 = deg2rad($lat1);
        $lat2 = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);
        
        $a = sin($deltaLat/2) * sin($deltaLat/2) +
             cos($lat1) * cos($lat2) *
             sin($deltaLon/2) * sin($deltaLon/2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earthRadius * $c;
    }
    
    /**
     * 計算標準差
     */
    private function calculateStandardDeviation($values)
    {
        if (empty($values)) return 0;
        
        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $values)) / count($values);
        
        return sqrt($variance);
    }
    
    /**
     * 格式化時間長度
     */
    private function formatDuration($seconds)
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        if ($hours > 0) {
            return sprintf("%d小時%d分鐘", $hours, $minutes);
        } elseif ($minutes > 0) {
            return sprintf("%d分鐘%d秒", $minutes, $secs);
        } else {
            return sprintf("%d秒", $secs);
        }
    }
    
    /**
     * 驗證 AI 回應格式
     */
    private function validateResponse($response)
    {
        if (!is_array($response)) {
            return false;
        }
        
        $requiredFields = [
            'transport_mode', 
            'total_distance', 
            'total_duration',
            'carbon_emission', 
            'suggestions'
        ];
        
        foreach ($requiredFields as $field) {
            if (!isset($response[$field])) {
                Log::warning("AI 回應缺少必要欄位: $field");
                return false;
            }
        }
        
        // 驗證數值欄位
        if (!is_numeric($response['total_distance']) || 
            !is_numeric($response['total_duration']) ||
            !is_numeric($response['carbon_emission'])) {
            return false;
        }
        
        // 驗證建議是否為陣列
        if (!is_array($response['suggestions'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 預設分析結果（當 AI 服務失敗時）
     */
    private function getDefaultAnalysis($gpsData)
    {
        $stats = $this->calculateGpsStats($gpsData);
        
        // 基於速度的簡單判斷邏輯
        $transportMode = 'walking';
        $carbonFactor = 0;
        $confidence = 0.7;
        
        if ($stats['avg_speed'] > 50) {
            $transportMode = 'car';
            $carbonFactor = 0.21;
            $confidence = 0.8;
        } elseif ($stats['avg_speed'] > 25) {
            // 判斷是機車還是汽車
            if ($stats['max_speed'] < 80 && $stats['speed_variation'] === 'high') {
                $transportMode = 'motorcycle';
                $carbonFactor = 0.095;
            } else {
                $transportMode = 'car';
                $carbonFactor = 0.21;
            }
            $confidence = 0.75;
        } elseif ($stats['avg_speed'] > 12) {
            // 判斷是腳踏車還是公車
            if ($stats['stop_count'] > $stats['point_count'] * 0.2) {
                $transportMode = 'bus';
                $carbonFactor = 0.089;
                $confidence = 0.65;
            } else {
                $transportMode = 'bicycle';
                $carbonFactor = 0;
                $confidence = 0.8;
            }
        } elseif ($stats['avg_speed'] > 3) {
            $transportMode = 'bicycle';
            $carbonFactor = 0;
            $confidence = 0.7;
        } else {
            $transportMode = 'walking';
            $carbonFactor = 0;
            $confidence = 0.85;
        }
        
        $carbonEmission = $stats['total_distance'] * $carbonFactor;
        
        // 生成預設建議
        $suggestions = $this->generateDefaultSuggestions($transportMode, $carbonEmission, $stats);
        
        return [
            'transport_mode' => $transportMode,
            'confidence' => $confidence,
            'total_distance' => $stats['total_distance'],
            'total_duration' => $stats['total_duration'],
            'average_speed' => $stats['avg_speed'],
            'max_speed' => $stats['max_speed'],
            'carbon_emission' => round($carbonEmission, 3),
            'route_analysis' => $this->generateDefaultAnalysis($transportMode, $stats),
            'suggestions' => $suggestions,
            'journey_segments' => []
        ];
    }
    
    /**
     * 生成預設分析說明
     */
    private function generateDefaultAnalysis($mode, $stats)
    {
        $modeNames = [
            'walking' => '步行',
            'bicycle' => '騎腳踏車',
            'motorcycle' => '騎機車',
            'car' => '開車',
            'bus' => '搭公車'
        ];
        
        $modeName = $modeNames[$mode] ?? '移動';
        
        return sprintf(
            "根據速度分析，您這段行程主要是%s，平均速度為 %.1f km/h，總距離 %.2f 公里，耗時 %s。",
            $modeName,
            $stats['avg_speed'],
            $stats['total_distance'],
            $this->formatDuration($stats['total_duration'])
        );
    }
    
    /**
     * 生成預設建議
     */
    private function generateDefaultSuggestions($mode, $emission, $stats)
    {
        $suggestions = [];
        
        // 根據不同交通工具給予建議
        switch ($mode) {
            case 'car':
                $suggestions[] = "建議1：短程距離（5公里內）可考慮改騎腳踏車或電動機車，預計可減少 " . 
                               round($emission * 0.8, 2) . " kg CO₂排放";
                $suggestions[] = "建議2：尋找共乘夥伴，分攤碳排放量，單人碳排可減少50%以上";
                $suggestions[] = "建議3：規劃路線時避開尖峰時段，減少怠速時間可降低15%碳排放";
                break;
                
            case 'motorcycle':
                $suggestions[] = "建議1：考慮升級為電動機車，可達到零碳排放";
                $suggestions[] = "建議2：短程（2公里內）可改為步行或騎腳踏車";
                $suggestions[] = "建議3：定期保養機車，確保引擎效率，可減少10%油耗";
                break;
                
            case 'bus':
                $suggestions[] = "建議1：您已選擇大眾運輸，碳排放較低，請繼續保持！";
                $suggestions[] = "建議2：可搭配步行或腳踏車完成最後一哩路";
                $suggestions[] = "建議3：選擇電動公車路線，進一步降低碳排放";
                break;
                
            case 'bicycle':
            case 'walking':
                $suggestions[] = "建議1：太棒了！您選擇了零碳排放的交通方式";
                $suggestions[] = "建議2：繼續保持環保出行習慣，您已經是減碳達人";
                $suggestions[] = "建議3：可以分享您的環保經驗，影響更多人加入減碳行列";
                break;
                
            default:
                $suggestions[] = "建議1：評估是否能使用大眾運輸工具替代";
                $suggestions[] = "建議2：短程移動優先選擇步行或騎腳踏車";
                $suggestions[] = "建議3：規劃最佳路線，減少不必要的繞路";
        }
        
        return $suggestions;
    }
}