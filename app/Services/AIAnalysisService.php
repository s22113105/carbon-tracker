<?php

namespace App\Services;

use OpenAI;
use Illuminate\Support\Facades\Log;
use App\Models\GpsData;
use Carbon\Carbon;

class AIAnalysisService
{
    protected $client;
    
    public function __construct()
    {
        $this->client = OpenAI::client(config('services.openai.key'));
    }
    
    /**
     * 分析GPS資料並預測交通工具和碳排放
     */
    public function analyzeGpsData($userId, $startDate, $endDate)
    {
        // 獲取指定日期範圍的GPS資料
        $gpsData = GpsData::getDataByDateRange($userId, $startDate, $endDate);
        
        if ($gpsData->isEmpty()) {
            throw new \Exception('指定日期範圍內沒有找到GPS資料');
        }
        
        // 預處理GPS資料
        $processedData = $this->preprocessGpsData($gpsData);
        
        // 建立提示詞
        $prompt = $this->buildAnalysisPrompt($processedData, [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
        
        try {
            $response = $this->client->chat()->create([
                'model' => 'gpt-4',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => '你是一個專業的交通分析師和環保顧問。請根據GPS移動資料分析交通工具使用情況並計算碳排放。請特別注意速度模式、移動間隔和路徑特徵來判斷交通工具類型。'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.3,
                'max_tokens' => 3000,
            ]);
            
            $content = $response->choices[0]->message->content;
            
            // 解析AI回應
            return $this->parseAIResponse($content);
            
        } catch (\Exception $e) {
            Log::error('OpenAI API 錯誤: ' . $e->getMessage());
            throw new \Exception('AI分析失敗，請稍後再試');
        }
    }
    
    /**
     * 預處理GPS資料
     */
    private function preprocessGpsData($gpsData)
    {
        $processed = [];
        $previousPoint = null;
        
        foreach ($gpsData as $point) {
            $processedPoint = [
                'date' => $point->date->format('Y-m-d'),
                'time' => $point->time,
                'latitude' => $point->latitude,
                'longitude' => $point->longitude,
                'speed' => $point->speed,
                'distance_from_previous' => 0,
                'time_gap_minutes' => 0
            ];
            
            if ($previousPoint) {
                // 計算與前一點的距離
                $processedPoint['distance_from_previous'] = $this->calculateDistance(
                    $previousPoint->latitude, $previousPoint->longitude,
                    $point->latitude, $point->longitude
                );
                
                // 計算時間間隔
                $timeDiff = Carbon::parse($point->timestamp)->diffInMinutes(Carbon::parse($previousPoint->timestamp));
                $processedPoint['time_gap_minutes'] = $timeDiff;
            }
            
            $processed[] = $processedPoint;
            $previousPoint = $point;
        }
        
        return $processed;
    }
    
    /**
     * 計算兩點間距離（公里）
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // 地球半徑（公里）
        
        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);
        
        $deltaLat = $lat2 - $lat1;
        $deltaLon = $lon2 - $lon1;
        
        $a = sin($deltaLat/2) * sin($deltaLat/2) + cos($lat1) * cos($lat2) * sin($deltaLon/2) * sin($deltaLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earthRadius * $c;
    }
    
    /**
     * 建立分析提示詞
     */
    private function buildAnalysisPrompt($processedData, $dateRange)
    {
        $prompt = "請分析以下GPS移動資料，分析期間：{$dateRange['start_date']} 到 {$dateRange['end_date']}\n\n";
        
        $prompt .= "分析任務：\n";
        $prompt .= "1. 根據速度、距離、時間間隔判斷交通工具類型\n";
        $prompt .= "2. 計算各交通工具的使用距離和時間\n";
        $prompt .= "3. 估算碳排放量\n";
        $prompt .= "4. 提供個人化減碳建議\n\n";
        
        $prompt .= "交通工具判斷標準：\n";
        $prompt .= "- 步行：速度 0-8 km/h，碳排放：0 kg CO2/km\n";
        $prompt .= "- 腳踏車：速度 8-25 km/h，碳排放：0 kg CO2/km\n";
        $prompt .= "- 機車：速度 25-60 km/h，碳排放：0.08 kg CO2/km\n";
        $prompt .= "- 汽車：速度 30-120 km/h，碳排放：0.2 kg CO2/km\n";
        $prompt .= "- 公車：速度 20-80 km/h，停停走走模式，碳排放：0.05 kg CO2/km\n\n";
        
        $prompt .= "GPS資料（最多顯示前50筆）：\n";
        $dataCount = min(50, count($processedData));
        for ($i = 0; $i < $dataCount; $i++) {
            $data = $processedData[$i];
            $prompt .= sprintf(
                "日期：%s，時間：%s，位置：(%.6f, %.6f)，速度：%.1f km/h，與前點距離：%.3f km，時間間隔：%d 分鐘\n",
                $data['date'],
                $data['time'],
                $data['latitude'],
                $data['longitude'],
                $data['speed'],
                $data['distance_from_previous'],
                $data['time_gap_minutes']
            );
        }
        
        if (count($processedData) > 50) {
            $prompt .= sprintf("... 還有 %d 筆資料未顯示\n", count($processedData) - 50);
        }
        
        $prompt .= "\n總資料筆數：" . count($processedData) . " 筆\n";
        
        $prompt .= "\n請以以下JSON格式回應，不要包含任何其他文字：\n";
        $prompt .= $this->getResponseFormat();
        
        return $prompt;
    }
    
    /**
     * 取得回應格式
     */
    private function getResponseFormat()
    {
        return '{
  "analysis": {
    "total_distance": "總距離(公里)",
    "total_time": "總時間(分鐘)",
    "transportation_breakdown": {
      "walking": {"distance": 0, "time": 0, "percentage": 0},
      "bicycle": {"distance": 0, "time": 0, "percentage": 0},
      "motorcycle": {"distance": 0, "time": 0, "percentage": 0},
      "car": {"distance": 0, "time": 0, "percentage": 0},
      "bus": {"distance": 0, "time": 0, "percentage": 0}
    },
    "carbon_emission": {
      "total_kg_co2": "總碳排放(公斤CO2)",
      "breakdown": {
        "walking": 0,
        "bicycle": 0,
        "motorcycle": 0,
        "car": 0,
        "bus": 0
      }
    },
    "recommendations": [
      "具體的碳排放減少建議1",
      "具體的碳排放減少建議2",
      "具體的碳排放減少建議3"
    ],
    "alternative_routes": [
      {"route": "替代路線描述", "carbon_saving": "節省碳排放量", "time_difference": "時間差異"}
    ]
  }
}';
    }
    
    /**
     * 解析AI回應
     */
    private function parseAIResponse($content)
    {
        try {
            // 提取JSON部分
            $jsonStart = strpos($content, '{');
            $jsonEnd = strrpos($content, '}') + 1;
            
            if ($jsonStart === false || $jsonEnd === false) {
                throw new \Exception('無法找到有效的JSON回應');
            }
            
            $jsonString = substr($content, $jsonStart, $jsonEnd - $jsonStart);
            $decoded = json_decode($jsonString, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON解析錯誤: ' . json_last_error_msg());
            }
            
            return $decoded;
            
        } catch (\Exception $e) {
            Log::error('AI回應解析失敗: ' . $e->getMessage());
            Log::error('原始回應: ' . $content);
            
            // 回傳預設結構避免錯誤
            return [
                'analysis' => [
                    'total_distance' => '0',
                    'total_time' => '0',
                    'transportation_breakdown' => [
                        'walking' => ['distance' => 0, 'time' => 0, 'percentage' => 0],
                        'bicycle' => ['distance' => 0, 'time' => 0, 'percentage' => 0],
                        'motorcycle' => ['distance' => 0, 'time' => 0, 'percentage' => 0],
                        'car' => ['distance' => 0, 'time' => 0, 'percentage' => 0],
                        'bus' => ['distance' => 0, 'time' => 0, 'percentage' => 0]
                    ],
                    'carbon_emission' => [
                        'total_kg_co2' => '0',
                        'breakdown' => [
                            'walking' => 0,
                            'bicycle' => 0,
                            'motorcycle' => 0,
                            'car' => 0,
                            'bus' => 0
                        ]
                    ],
                    'recommendations' => ['AI分析暫時無法使用，請稍後再試'],
                    'alternative_routes' => []
                ]
            ];
        }
    }
}