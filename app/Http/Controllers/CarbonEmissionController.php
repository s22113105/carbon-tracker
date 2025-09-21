<?php

namespace App\Http\Controllers;

use App\Services\CarbonEmissionService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class CarbonEmissionController extends Controller
{
    protected $carbonService;
    
    public function __construct(CarbonEmissionService $carbonService)
    {
        $this->carbonService = $carbonService;
    }
    
    /**
     * 顯示分析頁面
     */
    public function index()
    {
        return view('carbon.index');
    }
    
    /**
     * 執行碳排放分析
     */
    public function analyze(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'force_refresh' => 'sometimes|boolean'
        ]);
        
        $userId = Auth::id();
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        
        $result = $this->carbonService->analyzeEmissions($userId, $startDate, $endDate);
        
        return response()->json($result);
    }
    
    /**
     * 取得歷史分析資料
     */
    public function history(Request $request)
    {
        $userId = Auth::id();
        
        $query = CarbonEmissionAnalysis::where('user_id', $userId);
        
        if ($request->has('month')) {
            $month = Carbon::parse($request->month);
            $query->whereMonth('analysis_date', $month->month)
                  ->whereYear('analysis_date', $month->year);
        }
        
        $analyses = $query->orderBy('analysis_date', 'desc')
                         ->paginate(30);
        
        return response()->json([
            'success' => true,
            'data' => $analyses
        ]);
    }
    
    /**
     * 取得統計資料
     */
    public function statistics(Request $request)
    {
        $userId = Auth::id();
        $period = $request->get('period', 'month'); // month, week, year
        
        $startDate = match($period) {
            'week' => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            'year' => Carbon::now()->startOfYear(),
            default => Carbon::now()->startOfMonth()
        };
        
        $analyses = CarbonEmissionAnalysis::where('user_id', $userId)
            ->where('analysis_date', '>=', $startDate)
            ->get();
        
        // 按交通工具分組統計
        $byTransport = $analyses->groupBy('transport_mode')->map(function($group) {
            return [
                'count' => $group->count(),
                'total_emission' => $group->sum('carbon_emission'),
                'total_distance' => $group->sum('total_distance'),
                'percentage' => 0 // 後續計算
            ];
        });
        
        $totalEmission = $analyses->sum('carbon_emission');
        
        // 計算百分比
        foreach ($byTransport as $mode => &$data) {
            $data['percentage'] = $totalEmission > 0 ? 
                round(($data['total_emission'] / $totalEmission) * 100, 1) : 0;
        }
        
        // 每日趨勢
        $dailyTrend = $analyses->groupBy(function($item) {
            return $item->analysis_date->format('Y-m-d');
        })->map(function($group) {
            return [
                'date' => $group->first()->analysis_date->format('Y-m-d'),
                'emission' => $group->sum('carbon_emission'),
                'distance' => $group->sum('total_distance')
            ];
        })->values();
        
        return response()->json([
            'success' => true,
            'statistics' => [
                'period' => $period,
                'total_emission' => round($totalEmission, 2),
                'total_distance' => round($analyses->sum('total_distance'), 2),
                'total_duration' => $analyses->sum('total_duration'),
                'average_daily_emission' => $analyses->count() > 0 ? 
                    round($totalEmission / $analyses->count(), 2) : 0,
                'by_transport' => $byTransport,
                'daily_trend' => $dailyTrend,
                'eco_score' => $this->calculateOverallEcoScore($analyses)
            ]
        ]);
    }
    
    /**
     * 計算整體環保分數
     */
    private function calculateOverallEcoScore($analyses)
    {
        if ($analyses->isEmpty()) return 100;
        
        $totalEmission = $analyses->sum('carbon_emission');
        $totalDistance = $analyses->sum('total_distance');
        
        if ($totalDistance == 0) return 100;
        
        $emissionPerKm = $totalEmission / $totalDistance;
        $carBaseline = 0.21; // 汽車基準
        
        $score = max(0, 100 - ($emissionPerKm / $carBaseline * 100));
        
        return round($score);
    }
}