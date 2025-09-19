<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AIAnalysisService;
use App\Models\GpsData;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AIAnalysisController extends Controller
{
    protected $aiService;
    
    public function __construct(AIAnalysisService $aiService)
    {
        $this->aiService = $aiService;
    }
    
    /**
     * 顯示AI分析頁面
     */
    public function index()
    {
        // 取得可選擇的日期範圍
        $availableDates = GpsData::selectRaw('DATE(created_at) as date')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->take(30)
            ->get();
            
        return view('ai-analysis.index', compact('availableDates'));
    }
    
    /**
     * 執行AI分析
     */
    public function analyze(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ], [
            'start_date.required' => '請選擇開始日期',
            'end_date.required' => '請選擇結束日期',
            'end_date.after_or_equal' => '結束日期必須大於或等於開始日期',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);
            
            // 檢查日期範圍不超過30天
            if ($startDate->diffInDays($endDate) > 30) {
                return response()->json([
                    'success' => false,
                    'message' => '分析期間不能超過30天'
                ], 422);
            }
            
            // 取得GPS資料
            $gpsData = GpsData::whereBetween('created_at', [
                $startDate->startOfDay(),
                $endDate->endOfDay()
            ])->orderBy('created_at')->get();
            
            if ($gpsData->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => '所選日期範圍內沒有GPS資料'
                ], 404);
            }
            
            // 執行AI分析
            $dateRange = [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d')
            ];
            
            $analysis = $this->aiService->analyzeGpsData($gpsData, $dateRange);
            
            return response()->json([
                'success' => true,
                'data' => $analysis,
                'period' => [
                    'start_date' => $startDate->format('Y年m月d日'),
                    'end_date' => $endDate->format('Y年m月d日'),
                    'total_days' => $startDate->diffInDays($endDate) + 1
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 取得特定日期的詳細資料
     */
    public function getDateDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $date = Carbon::parse($request->date);
        $gpsData = GpsData::whereDate('created_at', $date)
            ->orderBy('created_at')
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => $gpsData,
            'date' => $date->format('Y年m月d日')
        ]);
    }
}