<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\OpenAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OpenAIServiceTest extends TestCase
{
    use RefreshDatabase;
    
    protected $openAIService;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->openAIService = app(OpenAIService::class);
    }
    
    /**
     * 測試 OpenAI 連接
     */
    public function test_openai_connection()
    {
        $isConnected = $this->openAIService->testConnection();
        $this->assertTrue($isConnected, 'OpenAI API 應該要能連接');
    }
    
    /**
     * 測試步行資料分析
     */
    public function test_walking_analysis()
    {
        $walkingData = $this->generateWalkingData();
        $result = $this->openAIService->analyzeTransportMode($walkingData);
        
        $this->assertEquals('walking', $result['transport_mode']);
        $this->assertEquals(0, $result['carbon_emission']);
    }
    
    /**
     * 測試汽車資料分析
     */
    public function test_car_analysis()
    {
        $carData = $this->generateCarData();
        $result = $this->openAIService->analyzeTransportMode($carData);
        
        $this->assertEquals('car', $result['transport_mode']);
        $this->assertGreaterThan(0, $result['carbon_emission']);
    }
    
    /**
     * 測試空資料處理
     */
    public function test_empty_data_handling()
    {
        $result = $this->openAIService->analyzeTransportMode([]);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('transport_mode', $result);
    }
    
    private function generateWalkingData()
    {
        $data = [];
        $baseTime = time();
        
        for ($i = 0; $i < 10; $i++) {
            $data[] = [
                'latitude' => 25.0330 + ($i * 0.00001),
                'longitude' => 121.5654 + ($i * 0.00001),
                'speed' => rand(2, 5),
                'timestamp' => date('Y-m-d H:i:s', $baseTime + ($i * 30)),
                'altitude' => 20,
                'accuracy' => 10
            ];
        }
        
        return $data;
    }
    
    private function generateCarData()
    {
        $data = [];
        $baseTime = time();
        
        for ($i = 0; $i < 20; $i++) {
            $data[] = [
                'latitude' => 25.0330 + ($i * 0.001),
                'longitude' => 121.5654 + ($i * 0.001),
                'speed' => rand(40, 60),
                'timestamp' => date('Y-m-d H:i:s', $baseTime + ($i * 30)),
                'altitude' => 20,
                'accuracy' => 10
            ];
        }
        
        return $data;
    }
}