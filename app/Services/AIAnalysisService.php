<?php

namespace App\Services;

use OpenAI;
use Illuminate\Support\Facades\Log;

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
    public function analyzeGpsData($gpsData, $dateRange)
    {
        // 準備提示詞
        $prompt = $this->buildAnalysisPrompt($gpsData, $dateRange);
        
        try {
            $response = $this->client->chat()->create([
                'model' => 'gpt-4',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => '你是一個專業的交通分析師和環保顧問，請分析GPS資料來判斷交通工具並計算碳排放。'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.3,
                'max_tokens' => 2000,
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
     * 建立分析提示詞
     */
    private function buildAnalysisPrompt($gpsData, $dateRange)
    {
        $prompt = "請分析以下GPS移動資料，時間範圍：{$dateRange['start_date']} 到 {$dateRange['end_date']}\n\n";
        
        $prompt .= "GPS資料格式說明：\n";
        $prompt .= "- 每筆資料包含：日期、時間、經度、緯度、速度(km/h)\n";
        $prompt .= "- 請根據速度、移動模式、時間間隔來判斷交通工具\n\n";
        
        $prompt .= "GPS資料：\n";
        foreach ($gpsData as $data) {
            $prompt .= "日期：{$data->date}，時間：{$data->time}，位置：({$data->latitude}, {$data->longitude})，速度：{$data->speed} km/h\n";
        }
        
        $prompt .= "\n請按照以下JSON格式回答，不要包含任何其他文字：\n";
        $prompt .= "{\n";
        $prompt .= '  "analysis": {' . "\n";
        $prompt .= '    "total_distance": "總距離(公里)",' . "\n";
        $prompt .= '    "total_time": "總時間(分鐘)",' . "\n";
        $prompt .= '    "transportation_breakdown": {' . "\n";
        $prompt .= '      "walking": {"distance": 0, "time": 0, "percentage": 0},' . "\n";
        $prompt .= '      "bicycle": {"distance": 0, "time": 0, "percentage": 0},' . "\n";
        $prompt .= '      "motorcycle": {"distance": 0, "time": 0, "percentage": 0},' . "\n";
        $prompt .= '      "car": {"distance": 0, "time": 0, "percentage": 0},' . "\n";
        $prompt .= '      "bus": {"distance": 0, "time": 0, "percentage": 0}' . "\n";
        $prompt .= '    },' . "\n";
        $prompt .= '    "carbon_emission": {' . "\n";
        $prompt .= '      "total_kg_co2": "總碳排放(公斤CO2)",' . "\n";
        $prompt .= '      "breakdown": {' . "\n";
        $prompt .= '        "walking": 0,' . "\n";
        $prompt .= '        "bicycle": 0,' . "\n";
        $prompt .= '        "motorcycle": 0,' . "\n";
        $prompt .= '        "car": 0,' . "\n";
        $prompt .= '        "bus": 0' . "\n";
        $prompt .= '      }' . "\n";
        $prompt .= '    },' . "\n";
        $prompt .= '    "recommendations": [' . "\n";
        $prompt .= '      "具體的碳排放減少建議1",' . "\n";
        $prompt .= '      "具體的碳排放減少建議2",' . "\n";
        $prompt .= '      "具體的碳排放減少建議3"' . "\n";
        $prompt .= '    ],' . "\n";
        $prompt .= '    "alternative_routes": [' . "\n";
        $prompt .= '      {"route": "替代路線描述", "carbon_saving": "節省碳排放量", "time_difference": "時間差異"}' . "\n";
        $prompt .= '    ]' . "\n";
        $prompt .= '  }' . "\n";
        $prompt .= "}\n\n";
        
        $prompt .= "計算標準：\n";
        $prompt .= "- 步行：0-8 km/h，碳排放：0 kg CO2/km\n";
        $prompt .= "- 腳踏車：8-25 km/h，碳排放：0 kg CO2/km\n";
        $prompt .= "- 機車：25-60 km/h，碳排放：0.08 kg CO2/km\n";
        $prompt .= "- 汽車：30-120 km/h，碳排放：0.2 kg CO2/km\n";
        $prompt .= "- 公車：20-80 km/h，碳排放：0.05 kg CO2/km\n";
        
        return $prompt;
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
            
            // 回傳預設結構
            return [
                'analysis' => [
                    'total_distance' => 0,
                    'total_time' => 0,
                    'transportation_breakdown' => [
                        'walking' => ['distance' => 0, 'time' => 0, 'percentage' => 0],
                        'bicycle' => ['distance' => 0, 'time' => 0, 'percentage' => 0],
                        'motorcycle' => ['distance' => 0, 'time' => 0, 'percentage' => 0],
                        'car' => ['distance' => 0, 'time' => 0, 'percentage' => 0],
                        'bus' => ['distance' => 0, 'time' => 0, 'percentage' => 0],
                    ],
                    'carbon_emission' => [
                        'total_kg_co2' => 0,
                        'breakdown' => [
                            'walking' => 0,
                            'bicycle' => 0,
                            'motorcycle' => 0,
                            'car' => 0,
                            'bus' => 0,
                        ]
                    ],
                    'recommendations' => ['分析失敗，請稍後再試'],
                    'alternative_routes' => []
                ]
            ];
        }
    }
}
