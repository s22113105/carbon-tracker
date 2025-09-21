<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OpenAIService;

class TestOpenAI extends Command
{
    protected $signature = 'openai:test {mode=connection}';
    protected $description = '測試 OpenAI API 連接和功能';
    
    protected $openAIService;
    
    public function __construct(OpenAIService $openAIService)
    {
        parent::__construct();
        $this->openAIService = $openAIService;
    }
    
    public function handle()
    {
        $mode = $this->argument('mode');
        
        switch ($mode) {
            case 'connection':
                $this->testConnection();
                break;
                
            case 'analysis':
                $this->testAnalysis();
                break;
                
            default:
                $this->error("未知的測試模式: {$mode}");
        }
    }
    
    private function testConnection()
    {
        $this->info('測試 OpenAI API 連接...');
        
        if ($this->openAIService->testConnection()) {
            $this->info('✅ OpenAI API 連接成功！');
            $this->table(
                ['設定項目', '值'],
                [
                    ['API Key', substr(config('services.openai.api_key'), 0, 10) . '...'],
                    ['Model', config('services.openai.model')],
                    ['Max Tokens', config('services.openai.max_tokens')],
                    ['Temperature', config('services.openai.temperature')]
                ]
            );
        } else {
            $this->error('❌ OpenAI API 連接失敗！');
            $this->warn('請檢查以下設定：');
            $this->warn('1. API Key 是否正確');
            $this->warn('2. 網路連接是否正常');
            $this->warn('3. OpenAI 帳戶是否有餘額');
        }
    }
    
    private function testAnalysis()
    {
        $this->info('測試 AI 分析功能...');
        
        // 生成測試資料
        $testData = $this->generateTestData();
        
        $this->info('分析中...');
        $result = $this->openAIService->analyzeTransportMode($testData);
        
        if ($result) {
            $this->info('✅ 分析成功！');
            
            $this->table(
                ['分析項目', '結果'],
                [
                    ['交通工具', $result['transport_mode']],
                    ['信心度', ($result['confidence'] ?? 0) * 100 . '%'],
                    ['總距離', $result['total_distance'] . ' km'],
                    ['總時間', $result['total_duration'] . ' 秒'],
                    ['碳排放', $result['carbon_emission'] . ' kg CO₂']
                ]
            );
            
            $this->info("\n建議：");
            foreach ($result['suggestions'] as $suggestion) {
                $this->line("• {$suggestion}");
            }
        } else {
            $this->error('❌ 分析失敗！');
        }
    }
    
    private function generateTestData()
    {
        $data = [];
        $baseTime = time();
        $lat = 25.0330;
        $lng = 121.5654;
        
        for ($i = 0; $i < 20; $i++) {
            $data[] = [
                'latitude' => $lat + ($i * 0.001),
                'longitude' => $lng + ($i * 0.001),
                'speed' => rand(20, 40),
                'timestamp' => date('Y-m-d H:i:s', $baseTime + ($i * 30)),
                'altitude' => rand(10, 50),
                'accuracy' => rand(5, 15)
            ];
        }
        
        return $data;
    }
}