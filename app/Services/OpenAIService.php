<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    private $apiKey;
    private $apiUrl = 'https://api.openai.com/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
    }

    /**
     * 分析GPS資料並產生碳排放建議
     */
    public function analyzeCarbonFootprint($gpsData)
    {
        try {
            $prompt = $this->buildPrompt($gpsData);
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($this->apiUrl, [
                'model' => 'gpt-4',
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
                'temperature' => 0.3,
                'max_tokens' => 2000,
            ]);

            if ($response->successful()) {
                $result = $response->json();
                $content = $result['choices'][0]['message']['content'] ?? '';
                
                // 解析回應為結構化資料
                return $this->parseResponse($content);
            }

            throw new \Exception('OpenAI API 回應失敗: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('OpenAI 分析失敗: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 建立系統提示詞
     */
    private function getSystemPrompt()
    {
        return '你是一個專業的交通碳排放分析師。請根據提供的GPS軌跡資料，分析使用者的交通方式並計算碳排放量。

請嚴格按照以下JSON格式回答：

```json
{
    "analysis": {
        "total_distance": "總距離(公里)",
        "total_time": "總時間(分鐘)",
        "transportation_breakdown": [
            {
                "type": "交通工具類型(walking/bicycle/motorcycle/car/bus)",
                "distance": "距離(公里)",
                "time": "時間(分鐘)",
                "carbon_emission": "碳排放量(公斤CO2)"
            }
        ],
        "total_carbon_emission": "總碳排放量(公斤CO2)"
    },
    "recommendations": [
        {
            "title": "建議標題",
            "description": "詳細說明",
            "potential_reduction": "預期減碳量(公斤CO2)",
            "difficulty": "實施難度(easy/medium/hard)"
        }
    ],
    "summary": {
        "current_footprint": "目前碳足跡評級(low/medium/high)",
        "improvement_potential": "改善潛力百分比",
        "key_insight": "關鍵洞察"
    }
}
```

分析規則：
1. 根據速度判斷交通工具：步行(<5km/h)、腳踏車(5-20km/h)、機車(20-60km/h)、汽車(30-100km/h)、公車(停停走走模式)
2. 碳排放係數：步行(0)、腳踏車(0)、機車(0.06kg CO2/km)、汽車(0.15kg CO2/km)、公車(0.08kg CO2/km)
3. 提供實用的減碳建議
4. 所有回答都用繁體中文';
    }

    /**
     * 建立用戶提示詞
     */
    private function buildPrompt($gpsData)
    {
        $prompt = "請分析以下GPS軌跡資料：\n\n";
        
        foreach ($gpsData as $date => $tracks) {
            $prompt .= "日期: {$date}\n";
            $prompt .= "軌跡點數: " . count($tracks) . "\n";
            
            if (!empty($tracks)) {
                $prompt .= "軌跡資料:\n";
                foreach ($tracks as $index => $point) {
                    $prompt .= sprintf(
                        "點%d: 緯度%.6f, 經度%.6f, 時間%s\n",
                        $index + 1,
                        $point['latitude'],
                        $point['longitude'],
                        $point['timestamp']
                    );
                }
            }
            $prompt .= "\n";
        }

        $prompt .= "請分析這些軌跡資料，判斷交通方式並計算碳排放，提供改善建議。";
        
        return $prompt;
    }

    /**
     * 解析OpenAI回應
     */
    private function parseResponse($content)
    {
        // 提取JSON部分
        preg_match('/```json\s*(.*?)\s*```/s', $content, $matches);
        
        if (empty($matches[1])) {
            // 如果沒有找到JSON格式，嘗試直接解析
            $jsonData = json_decode($content, true);
        } else {
            $jsonData = json_decode($matches[1], true);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('無法解析OpenAI回應為JSON格式');
        }

        return $jsonData;
    }
}