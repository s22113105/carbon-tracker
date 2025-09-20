<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\AIAnalysisService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class AiSuggestionController extends Controller
{
    protected $aiService;
    
    public function __construct(AIAnalysisService $aiService)
    {
        $this->aiService = $aiService;
        $this->middleware('auth');
    }
    
    /**
     * 顯示AI建議頁面
     */
    public function index()
    {
        return view('user.ai-suggestions');
    }
    
    /**
     * 生成AI建議 (原有的方法)
     */
    public function getSuggestions(Request $request)
    {
        $request->validate([
            'date_range' => 'required|integer|min:1|max:90',
        ]);
        
        $dateRange = $request->date_range;
        $userId = Auth::id();
        
        $endDate = Carbon::today();
        $startDate = $endDate->copy()->subDays($dateRange - 1);
        
        try {
            // 調用AI分析服務
            $analysis = $this->aiService->analyzeGpsData(
                $userId, 
                $startDate->format('Y-m-d'), 
                $endDate->format('Y-m-d')
            );
            
            return response()->json([
                'success' => true,
                'data' => $analysis,
                'date_range' => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'days' => $dateRange
                ]
            ]);
            
        } catch (\Exception $e) {
            // 如果AI分析失敗，提供後備建議
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'fallback' => $this->getFallbackSuggestions()
            ]);
        }
    }
    
    /**
     * 分析GPS資料 (新增的方法)
     */
    public function analyzeGpsData(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);
        
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $userId = Auth::id();
        
        // 檢查日期範圍是否合理（不超過30天）
        $daysDiff = Carbon::parse($endDate)->diffInDays(Carbon::parse($startDate));
        if ($daysDiff > 30) {
            return response()->json([
                'success' => false,
                'message' => '分析日期範圍不能超過30天'
            ], 400);
        }
        
        try {
            // 調用AI分析服務
            $analysis = $this->aiService->analyzeGpsData($userId, $startDate, $endDate);
            
            return response()->json([
                'success' => true,
                'data' => $analysis,
                'date_range' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
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
     * 重新分析所有行程 (現有的方法)
     */
    public function reanalyzeAllTrips(Request $request)
    {
        $userId = Auth::id();
        
        try {
            // 分析最近30天的資料
            $endDate = Carbon::today();
            $startDate = $endDate->copy()->subDays(29);
            
            $analysis = $this->aiService->analyzeGpsData(
                $userId, 
                $startDate->format('Y-m-d'), 
                $endDate->format('Y-m-d')
            );
            
            return response()->json([
                'success' => true,
                'message' => '重新分析完成',
                'data' => $analysis,
                'analyzed_period' => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d')
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '重新分析失敗：' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 獲取預設的分析期間選項
     */
    public function getDateRangeOptions()
    {
        $today = Carbon::today();
        
        return response()->json([
            'options' => [
                [
                    'label' => '今天',
                    'value' => 1,
                    'start_date' => $today->format('Y-m-d'),
                    'end_date' => $today->format('Y-m-d')
                ],
                [
                    'label' => '最近3天',
                    'value' => 3,
                    'start_date' => $today->copy()->subDays(2)->format('Y-m-d'),
                    'end_date' => $today->format('Y-m-d')
                ],
                [
                    'label' => '最近一週',
                    'value' => 7,
                    'start_date' => $today->copy()->subDays(6)->format('Y-m-d'),
                    'end_date' => $today->format('Y-m-d')
                ],
                [
                    'label' => '最近兩週',
                    'value' => 14,
                    'start_date' => $today->copy()->subDays(13)->format('Y-m-d'),
                    'end_date' => $today->format('Y-m-d')
                ],
                [
                    'label' => '最近30天',
                    'value' => 30,
                    'start_date' => $today->copy()->subDays(29)->format('Y-m-d'),
                    'end_date' => $today->format('Y-m-d')
                ]
            ]
        ]);
    }
    
    /**
     * 獲取後備建議
     */
    private function getFallbackSuggestions()
    {
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
                'recommendations' => [
                    '考慮使用步行或腳踏車進行短距離移動，這是最環保的交通方式',
                    '搭乘大眾運輸工具（公車、捷運）可以有效降低個人碳排放',
                    '規劃行程時嘗試合併多個目的地，減少不必要的往返',
                    '與同事或朋友共乘，分攤交通工具的碳排放成本',
                    '選擇居住地點時考慮與工作地點的距離，減少通勤碳排放'
                ],
                'alternative_routes' => [
                    [
                        'route' => '步行 + 大眾運輸組合',
                        'carbon_saving' => '約50-70%碳排放減少',
                        'time_difference' => '可能增加10-20分鐘'
                    ],
                    [
                        'route' => '腳踏車通勤',
                        'carbon_saving' => '接近100%碳排放減少',
                        'time_difference' => '與開車時間相近'
                    ]
                ]
            ]
        ];
    }
}