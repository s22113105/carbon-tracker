<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\CarbonEmission;
use App\Models\Trip;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    public function index()
    {
        // 總體統計
        $totalStats = [
            'total_users' => User::count(),
            'total_trips' => Trip::count(),
            'total_emissions' => round(CarbonEmission::sum('co2_emission'), 2),
            'total_distance' => round(Trip::sum('distance'), 2),
            'active_users_today' => User::whereHas('trips', function($query) {
                $query->whereDate('start_time', today());
            })->count()
        ];

        // 本月統計對比
        $currentMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();
        
        $monthlyStats = [
            'current' => [
                'trips' => Trip::where('start_time', '>=', $currentMonth)->count(),
                'emissions' => round(CarbonEmission::where('emission_date', '>=', $currentMonth)->sum('co2_emission'), 2),
                'distance' => round(Trip::where('start_time', '>=', $currentMonth)->sum('distance'), 2),
                'users' => User::where('created_at', '>=', $currentMonth)->count()
            ],
            'last' => [
                'trips' => Trip::whereBetween('start_time', [$lastMonth, $currentMonth])->count(),
                'emissions' => round(CarbonEmission::whereBetween('emission_date', [$lastMonth, $currentMonth])->sum('co2_emission'), 2),
                'distance' => round(Trip::whereBetween('start_time', [$lastMonth, $currentMonth])->sum('distance'), 2),
                'users' => User::whereBetween('created_at', [$lastMonth, $currentMonth])->count()
            ]
        ];

        // 交通工具使用統計
        $transportStats = CarbonEmission::select('transport_mode', 
            DB::raw('COUNT(*) as usage_count'),
            DB::raw('SUM(co2_emission) as total_emission'),
            DB::raw('SUM(distance) as total_distance')
        )->groupBy('transport_mode')
        ->orderBy('usage_count', 'desc')
        ->get()
        ->map(function($item) {
            return [
                'mode' => $this->getTransportModeName($item->transport_mode),
                'mode_code' => $item->transport_mode,
                'usage_count' => $item->usage_count,
                'total_emission' => round($item->total_emission, 2),
                'total_distance' => round($item->total_distance, 2),
                'avg_emission' => round($item->total_emission / $item->usage_count, 2)
            ];
        });

        // 近30天每日統計
        $dailyStats = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dailyStats[] = [
                'date' => $date->format('Y-m-d'),
                'date_formatted' => $date->format('m/d'),
                'trips' => Trip::whereDate('start_time', $date)->count(),
                'emissions' => round(CarbonEmission::where('emission_date', $date)->sum('co2_emission'), 2),
                'users' => User::whereHas('trips', function($query) use ($date) {
                    $query->whereDate('start_time', $date);
                })->count()
            ];
        }

        // 最活躍使用者（前10名）
        $topUsers = User::with(['carbonEmissions', 'trips'])
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'total_trips' => $user->trips->count(),
                    'total_emissions' => round($user->carbonEmissions->sum('co2_emission'), 2),
                    'total_distance' => round($user->trips->sum('distance'), 2),
                    'avg_daily_emission' => $user->trips->count() > 0 ? 
                        round($user->carbonEmissions->sum('co2_emission') / max(1, $user->trips->groupBy(function($trip) {
                            return $trip->start_time->format('Y-m-d');
                        })->count()), 2) : 0
                ];
            })
            ->sortByDesc('total_trips')
            ->take(10);

        // 部門統計（假設有部門欄位，如果沒有可以移除）
        $departmentStats = collect([
            ['name' => '資訊部', 'users' => 15, 'emissions' => 120.5],
            ['name' => '行銷部', 'users' => 12, 'emissions' => 95.3],
            ['name' => '業務部', 'users' => 18, 'emissions' => 145.7],
            ['name' => '人事部', 'users' => 8, 'emissions' => 65.2],
            ['name' => '財務部', 'users' => 6, 'emissions' => 48.9]
        ]);

        return view('admin.statistics', compact(
            'totalStats',
            'monthlyStats',
            'transportStats',
            'dailyStats',
            'topUsers',
            'departmentStats'
        ));
    }

    private function getTransportModeName($code)
    {
        $names = [
            'walking' => '步行',
            'bus' => '公車',
            'mrt' => '捷運',
            'car' => '汽車',
            'motorcycle' => '機車',
            'unknown' => '未知'
        ];

        return $names[$code] ?? '未知';
    }

    public function exportData(Request $request)
    {
        $format = $request->get('format', 'csv');
        $type = $request->get('type', 'all');
        
        // 根據類型獲取資料
        switch($type) {
            case 'users':
                $data = User::with(['carbonEmissions', 'trips'])->get();
                break;
            case 'emissions':
                $data = CarbonEmission::with('user')->get();
                break;
            case 'trips':
                $data = Trip::with('user')->get();
                break;
            default:
                $data = collect();
        }

        // 匯出邏輯（這裡簡化，實際應該使用專門的匯出套件）
        if ($format === 'csv') {
            return $this->exportToCsv($data, $type);
        }

        return response()->json(['error' => 'Unsupported format'], 400);
    }

    private function exportToCsv($data, $type)
    {
        $filename = "carbon_tracking_{$type}_" . date('Y-m-d') . ".csv";
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function() use ($data, $type) {
            $file = fopen('php://output', 'w');
            
            // 根據類型設定標題行
            switch($type) {
                case 'users':
                    fputcsv($file, ['ID', '姓名', '信箱', '角色', '註冊時間', '總行程數', '總碳排放(kg)', '總距離(km)']);
                    foreach($data as $user) {
                        fputcsv($file, [
                            $user->id,
                            $user->name,
                            $user->email,
                            $user->role === 'admin' ? '管理員' : '一般使用者',
                            $user->created_at->format('Y-m-d H:i:s'),
                            $user->trips->count(),
                            round($user->carbonEmissions->sum('co2_emission'), 2),
                            round($user->trips->sum('distance'), 2)
                        ]);
                    }
                    break;
                case 'emissions':
                    fputcsv($file, ['ID', '使用者', '日期', '交通工具', '距離(km)', '碳排放(kg)', 'AI建議']);
                    foreach($data as $emission) {
                        fputcsv($file, [
                            $emission->id,
                            $emission->user->name,
                            $emission->emission_date->format('Y-m-d'),
                            $this->getTransportModeName($emission->transport_mode),
                            $emission->distance,
                            $emission->co2_emission,
                            $emission->ai_suggestion ?? ''
                        ]);
                    }
                    break;
                case 'trips':
                    fputcsv($file, ['ID', '使用者', '開始時間', '結束時間', '距離(km)', '交通工具', '行程類型']);
                    foreach($data as $trip) {
                        fputcsv($file, [
                            $trip->id,
                            $trip->user->name,
                            $trip->start_time->format('Y-m-d H:i:s'),
                            $trip->end_time ? $trip->end_time->format('Y-m-d H:i:s') : '',
                            $trip->distance,
                            $this->getTransportModeName($trip->transport_mode),
                            $trip->trip_type
                        ]);
                    }
                    break;
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}