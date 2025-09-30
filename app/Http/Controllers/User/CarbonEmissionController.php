<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CarbonEmissionController extends Controller
{
    private $openAIService;
    
    public function __construct(OpenAIService $openAIService)
    {
        $this->openAIService = $openAIService;
    }
    
    public function aiAnalyses()
    {
        return view('user.aianalyses');
    }
    
    public function analyze(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'data_source' => 'required|in:all,esp32,manual',
        ]);
        
        $userId = auth()->id();
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        
        // 獲取GPS資料
        $gpsData = $this->getGpsDataForAnalysis($userId, $startDate, $endDate, $request->data_source);
        
        if (empty($gpsData)) {
            return response()->json([
                'success' => false,
                'message' => '所選時間範圍內沒有GPS資料'
            ], 404);
        }
        
        // 使用OpenAI分析
        $analysisResults = [];
        foreach ($gpsData as $date => $dayData) {
            $analysis = $this->openAIService->analyzeCarbonEmission($dayData);
            $analysisResults[$date] = [
                'gps_count' => count($dayData),
                'analysis' => $analysis,
            ];
        }
        
        // 計算總結
        $summary = $this->calculateSummary($analysisResults);
        
        // 儲存分析結果
        $analysisId = DB::table('carbon_analyses')->insertGetId([
            'user_id' => $userId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'analysis_result' => json_encode([
                'daily_results' => $analysisResults,
                'summary' => $summary,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $analysisId,
                'daily_results' => $analysisResults,
                'summary' => $summary,
            ]
        ]);
    }
    
    private function getGpsDataForAnalysis($userId, $startDate, $endDate, $dataSource)
    {
        $query = DB::table('gps_tracks')
            ->where('user_id', $userId)
            ->whereBetween('recorded_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        
        if ($dataSource === 'esp32') {
            $query->where('device_type', 'ESP32');
        } elseif ($dataSource === 'manual') {
            $query->where('device_type', '!=', 'ESP32');
        }
        
        $tracks = $query->orderBy('recorded_at')->get();
        
        $gpsData = [];
        foreach ($tracks as $track) {
            $date = Carbon::parse($track->recorded_at)->format('Y-m-d');
            if (!isset($gpsData[$date])) {
                $gpsData[$date] = [];
            }
            
            $gpsData[$date][] = [
                'latitude' => $track->latitude,
                'longitude' => $track->longitude,
                'speed' => $track->speed ?? 0,
                'timestamp' => $track->recorded_at,
            ];
        }
        
        return $gpsData;
    }
    
    private function calculateSummary($analysisResults)
    {
        $totalEmission = 0;
        $totalDistance = 0;
        $transportModes = [];
        
        foreach ($analysisResults as $result) {
            if (isset($result['analysis'])) {
                $analysis = $result['analysis'];
                $totalEmission += $analysis['carbon_emission'] ?? 0;
                $totalDistance += $analysis['total_distance'] ?? 0;
                
                $mode = $analysis['transport_mode'] ?? 'unknown';
                if (!isset($transportModes[$mode])) {
                    $transportModes[$mode] = 0;
                }
                $transportModes[$mode]++;
            }
        }
        
        return [
            'total_emission' => round($totalEmission, 2),
            'total_distance' => round($totalDistance, 2),
            'avg_daily_emission' => round($totalEmission / max(count($analysisResults), 1), 2),
            'transport_modes' => $transportModes,
            'reduction_suggestions' => $this->generateReductionSuggestions($transportModes),
        ];
    }
    
    private function generateReductionSuggestions($transportModes)
    {
        $suggestions = [];
        
        if (isset($transportModes['car']) && $transportModes['car'] > 0) {
            $suggestions[] = '建議增加使用大眾運輸工具或共乘，可減少個人碳排放';
        }
        
        if (isset($transportModes['motorcycle']) && $transportModes['motorcycle'] > 0) {
            $suggestions[] = '考慮改用電動機車或腳踏車進行短程通勤';
        }
        
        $suggestions[] = '定期保養交通工具可提高燃油效率，減少碳排放';
        $suggestions[] = '規劃最佳路線避免塞車，可減少不必要的排放';
        
        return $suggestions;
    }
}