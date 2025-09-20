<?php

namespace App\Http\Controllers;

use App\Services\OpenAIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CarbonFootprintController extends Controller
{
    private $openAIService;

    public function __construct(OpenAIService $openAIService)
    {
        $this->middleware('auth');
        $this->openAIService = $openAIService;
    }

    /**
     * 顯示碳足跡分析頁面
     */
    public function index()
    {
        return view('carbon.index');
    }

    /**
     * 獲取GPS資料並進行分析
     */
    public function analyze(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        try {
            $userId = Auth::id();
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();

            // 獲取GPS資料
            $gpsData = $this->getGpsData($userId, $startDate, $endDate);

            if (empty($gpsData)) {
                return response()->json([
                    'success' => false,
                    'message' => '在指定日期範圍內沒有找到GPS資料'
                ]);
            }

            // 使用OpenAI分析
            $analysis = $this->openAIService->analyzeCarbonFootprint($gpsData);

            // 儲存分析結果
            $analysisRecord = $this->saveAnalysis($userId, $startDate, $endDate, $analysis);

            return response()->json([
                'success' => true,
                'data' => $analysis,
                'analysis_id' => $analysisRecord->id
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '分析失敗: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 獲取歷史分析記錄
     */
    public function history()
    {
        $userId = Auth::id();
        
        $analyses = DB::table('carbon_analyses')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $analyses
        ]);
    }

    /**
     * 獲取特定分析詳情
     */
    public function show($id)
    {
        $userId = Auth::id();
        
        $analysis = DB::table('carbon_analyses')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$analysis) {
            return response()->json([
                'success' => false,
                'message' => '找不到該分析記錄'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $analysis->id,
                'start_date' => $analysis->start_date,
                'end_date' => $analysis->end_date,
                'analysis_result' => json_decode($analysis->analysis_result, true),
                'created_at' => $analysis->created_at
            ]
        ]);
    }

    /**
     * 獲取GPS資料
     */
    private function getGpsData($userId, $startDate, $endDate)
    {
        // 假設你有一個gps_tracks表
        $tracks = DB::table('gps_tracks')
            ->where('user_id', $userId)
            ->whereBetween('recorded_at', [$startDate, $endDate])
            ->orderBy('recorded_at')
            ->get();

        // 按日期分組
        $groupedData = [];
        foreach ($tracks as $track) {
            $date = Carbon::parse($track->recorded_at)->format('Y-m-d');
            $groupedData[$date][] = [
                'latitude' => $track->latitude,
                'longitude' => $track->longitude,
                'timestamp' => $track->recorded_at
            ];
        }

        // 如果沒有真實資料，使用測試資料
        if (empty($groupedData)) {
            $groupedData = $this->getTestGpsData($startDate, $endDate);
        }

        return $groupedData;
    }

    /**
     * 產生測試GPS資料
     */
    private function getTestGpsData($startDate, $endDate)
    {
        $testData = [];
        $current = $startDate->copy();
        
        while ($current->lte($endDate)) {
            $date = $current->format('Y-m-d');
            
            // 模擬一天的GPS軌跡（台北市內移動）
            $testData[$date] = [
                // 早上通勤 - 可能是搭捷運/公車
                [
                    'latitude' => 25.0330,
                    'longitude' => 121.5654,
                    'timestamp' => $date . ' 08:00:00'
                ],
                [
                    'latitude' => 25.0378,
                    'longitude' => 121.5645,
                    'timestamp' => $date . ' 08:15:00'
                ],
                [
                    'latitude' => 25.0425,
                    'longitude' => 121.5687,
                    'timestamp' => $date . ' 08:30:00'
                ],
                // 中午外出 - 可能是步行
                [
                    'latitude' => 25.0425,
                    'longitude' => 121.5687,
                    'timestamp' => $date . ' 12:00:00'
                ],
                [
                    'latitude' => 25.0435,
                    'longitude' => 121.5695,
                    'timestamp' => $date . ' 12:10:00'
                ],
                [
                    'latitude' => 25.0425,
                    'longitude' => 121.5687,
                    'timestamp' => $date . ' 12:45:00'
                ],
                // 晚上回家 - 可能是開車/騎車
                [
                    'latitude' => 25.0425,
                    'longitude' => 121.5687,
                    'timestamp' => $date . ' 18:30:00'
                ],
                [
                    'latitude' => 25.0330,
                    'longitude' => 121.5654,
                    'timestamp' => $date . ' 18:50:00'
                ]
            ];
            
            $current->addDay();
        }
        
        return $testData;
    }

    /**
     * 儲存分析結果
     */
    private function saveAnalysis($userId, $startDate, $endDate, $analysis)
    {
        return DB::table('carbon_analyses')->insertGetId([
            'user_id' => $userId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'analysis_result' => json_encode($analysis),
            'total_carbon_emission' => $analysis['analysis']['total_carbon_emission'] ?? 0,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}