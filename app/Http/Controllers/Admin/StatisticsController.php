<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\CarbonEmission;
use App\Models\CarbonEmissionAnalysis;
use App\Models\Trip;
use App\Models\GpsRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Response;

class StatisticsController extends Controller
{
    public function index()
    {
        // 總體統計
        $totalStats = [
            'total_users' => User::count(),
            'total_trips' => Trip::count(),
            'total_emissions' => round(CarbonEmissionAnalysis::sum('carbon_emission'), 2),
            'total_distance' => round(Trip::sum('distance') / 1000, 1),
            'active_users_today' => User::whereHas('trips', function ($query) {
                $query->whereDate('created_at', today());
            })->count()
        ];

        // 月度對比統計
        $currentMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();
        
        $monthlyStats = [
            'current' => [
                'trips' => Trip::whereBetween('created_at', [$currentMonth, Carbon::now()])->count(),
                'emissions' => round(CarbonEmissionAnalysis::whereBetween('analysis_date', [$currentMonth, Carbon::now()])->sum('carbon_emission'), 2),
                'distance' => round(Trip::whereBetween('created_at', [$currentMonth, Carbon::now()])->sum('distance') / 1000, 1),
                'users' => User::whereBetween('created_at', [$currentMonth, Carbon::now()])->count()
            ],
            'last' => [
                'trips' => Trip::whereBetween('created_at', [$lastMonth, $currentMonth])->count(),
                'emissions' => round(CarbonEmissionAnalysis::whereBetween('analysis_date', [$lastMonth, $currentMonth])->sum('carbon_emission'), 2),
                'distance' => round(Trip::whereBetween('created_at', [$lastMonth, $currentMonth])->sum('distance') / 1000, 1),
                'users' => User::whereBetween('created_at', [$lastMonth, $currentMonth])->count()
            ]
        ];

        // 近30天統計
        $dailyStats = collect();
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dailyStats->push([
                'date' => $date->format('Y-m-d'),
                'date_formatted' => $date->format('m/d'),
                'trips' => Trip::whereDate('created_at', $date)->count(),
                'emissions' => round(CarbonEmissionAnalysis::whereDate('analysis_date', $date)->sum('carbon_emission'), 2)
            ]);
        }

        // 交通工具統計
        $transportStats = Trip::select('transport_mode')
            ->selectRaw('COUNT(*) as usage_count')
            ->selectRaw('SUM(distance) / 1000 as total_distance')
            ->with(['carbonEmission'])
            ->groupBy('transport_mode')
            ->get()
            ->map(function ($trip) {
                $totalEmission = CarbonEmissionAnalysis::whereHas('trip', function ($query) use ($trip) {
                    $query->where('transport_mode', $trip->transport_mode);
                })->sum('carbon_emission');

                return [
                    'mode' => $this->getTransportModeName($trip->transport_mode),
                    'mode_code' => $trip->transport_mode,
                    'usage_count' => $trip->usage_count,
                    'total_distance' => round($trip->total_distance, 1),
                    'total_emission' => round($totalEmission, 2),
                    'avg_emission' => $trip->usage_count > 0 ? round($totalEmission / $trip->usage_count, 2) : 0
                ];
            });

        // 最活躍使用者 TOP 10
        $topUsers = User::withCount('trips')
            ->with(['carbonEmissions'])
            ->orderBy('trips_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($user) {
                $totalEmissions = $user->carbonEmissions->sum('carbon_emission');
                $daysSinceRegistration = max(1, $user->created_at->diffInDays(now()));
                
                return [
                    'name' => $user->name,
                    'email' => $user->email,
                    'total_trips' => $user->trips_count,
                    'total_emissions' => round($totalEmissions, 2),
                    'avg_daily_emission' => round($totalEmissions / $daysSinceRegistration, 2)
                ];
            });

        // 部門統計（模擬資料）
        $departmentStats = collect([
            ['name' => '技術部', 'users' => 15, 'emissions' => 245.6],
            ['name' => '行政部', 'users' => 8, 'emissions' => 156.2],
            ['name' => '業務部', 'users' => 12, 'emissions' => 298.4],
            ['name' => '財務部', 'users' => 6, 'emissions' => 87.3]
        ]);

        return view('admin.statistics', compact(
            'totalStats', 'monthlyStats', 'dailyStats', 
            'transportStats', 'topUsers', 'departmentStats'
        ));
    }

    /**
     * 匯出資料 - 純 CSV 實作，無需 Laravel Excel
     */
    public function exportData(Request $request)
    {
        $type = $request->get('type', 'users');
        $format = $request->get('format', 'csv');

        try {
            switch ($type) {
                case 'users':
                    return $this->exportUsers();
                case 'emissions':
                    return $this->exportEmissions();
                case 'trips':
                    return $this->exportTrips();
                default:
                    return response()->json(['error' => '不支援的匯出類型'], 400);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => '匯出失敗：' . $e->getMessage()], 500);
        }
    }

    /**
     * 匯出使用者資料
     */
    private function exportUsers()
    {
        $users = User::with(['trips', 'carbonEmissions'])
            ->get()
            ->map(function ($user) {
                return [
                    'User ID' => $user->id,
                    'Name' => $user->name,
                    'Email' => $user->email,
                    'Role' => $user->role === 'admin' ? 'Admin' : 'User',
                    'Registration Date' => $user->created_at->format('Y-m-d'),
                    'Total Trips' => $user->trips->count(),
                    'Total Distance (km)' => number_format($user->trips->sum('distance') / 1000, 2),
                    'Total CO2 Emission (kg)' => number_format($user->carbonEmissions->sum('carbon_emission'), 3),
                    'Last Activity' => $user->updated_at->format('Y-m-d H:i:s'),
                ];
            });

        return $this->generateCSV($users, 'users_report_' . date('Ymd'));
    }

    /**
     * 匯出碳排放資料
     */
    private function exportEmissions()
    {
        $emissions = CarbonEmissionAnalysis::with(['user', 'trip'])
            ->orderBy('analysis_date', 'desc')
            ->get()
            ->map(function ($emission) {
                return [
                    'Date' => $emission->analysis_date->format('Y-m-d'),
                    'User Name' => $emission->user->name,
                    'Email' => $emission->user->email,
                    'Transport Mode' => $this->getTransportModeNameEn($emission->transport_mode),
                    'Distance (km)' => number_format($emission->distance / 1000, 2),
                    'CO2 Emission (kg)' => number_format($emission->carbon_emission, 3),
                    'AI Suggestion' => $emission->ai_suggestion ?? 'None',
                ];
            });

        return $this->generateCSV($emissions, 'emissions_report_' . date('Ymd'));
    }

    /**
     * 匯出行程資料
     */
    private function exportTrips()
    {
        $trips = Trip::with(['user', 'carbonEmission'])
            ->orderBy('start_time', 'desc')
            ->get()
            ->map(function ($trip) {
                return [
                    'User Name' => $trip->user->name,
                    'Start Time' => $trip->start_time->format('Y-m-d H:i:s'),
                    'End Time' => $trip->end_time ? $trip->end_time->format('Y-m-d H:i:s') : 'Ongoing',
                    'Start Latitude' => $trip->start_latitude,
                    'Start Longitude' => $trip->start_longitude,
                    'End Latitude' => $trip->end_latitude ?? 'Unknown',
                    'End Longitude' => $trip->end_longitude ?? 'Unknown',
                    'Distance (km)' => number_format($trip->distance / 1000, 2),
                    'Transport Mode' => $this->getTransportModeNameEn($trip->transport_mode),
                    'Trip Type' => $this->getTripTypeNameEn($trip->trip_type),
                    'CO2 Emission (kg)' => $trip->carbonEmission 
                        ? number_format($trip->carbonEmission->carbon_emission, 3) 
                        : 'Not calculated',
                ];
            });

        return $this->generateCSV($trips, 'trips_report_' . date('Ymd'));
    }

    /**
     * 生成 CSV 檔案 - 解決中文亂碼
     */
    private function generateCSV($data, $filename)
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.csv"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            
            // 加入 BOM 來解決 Excel 開啟 UTF-8 CSV 的亂碼問題
            fwrite($file, "\xEF\xBB\xBF");
            
            if ($data->count() > 0) {
                // 寫入標題行
                fputcsv($file, array_keys($data->first()));
                
                // 寫入資料行
                foreach ($data as $row) {
                    fputcsv($file, array_values($row));
                }
            }
            
            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    /**
     * 進階匯出功能 - 包含更多統計資料
     */
    public function exportAdvancedReport(Request $request)
    {
        $dateFrom = Carbon::parse($request->get('date_from', Carbon::now()->subMonth()));
        $dateTo = Carbon::parse($request->get('date_to', Carbon::now()));

        // 綜合統計報表
        $stats = collect([
            [
                'Metric' => 'Total Users',
                'Value' => User::count(),
                'Unit' => 'users',
                'Period' => $dateFrom->format('Y-m-d') . ' to ' . $dateTo->format('Y-m-d')
            ],
            [
                'Metric' => 'Total Trips',
                'Value' => Trip::whereBetween('start_time', [$dateFrom, $dateTo])->count(),
                'Unit' => 'trips',
                'Period' => $dateFrom->format('Y-m-d') . ' to ' . $dateTo->format('Y-m-d')
            ],
            [
                'Metric' => 'Total Distance',
                'Value' => number_format(Trip::whereBetween('start_time', [$dateFrom, $dateTo])->sum('distance') / 1000, 2),
                'Unit' => 'km',
                'Period' => $dateFrom->format('Y-m-d') . ' to ' . $dateTo->format('Y-m-d')
            ],
            [
                'Metric' => 'Total CO2 Emission',
                'Value' => number_format(CarbonEmissionAnalysis::whereBetween('analysis_date', [$dateFrom, $dateTo])->sum('carbon_emission'), 3),
                'Unit' => 'kg',
                'Period' => $dateFrom->format('Y-m-d') . ' to ' . $dateTo->format('Y-m-d')
            ],
            [
                'Metric' => 'Average Daily Trips',
                'Value' => number_format(Trip::whereBetween('start_time', [$dateFrom, $dateTo])->count() / max(1, $dateFrom->diffInDays($dateTo)), 1),
                'Unit' => 'trips/day',
                'Period' => $dateFrom->format('Y-m-d') . ' to ' . $dateTo->format('Y-m-d')
            ],
            [
                'Metric' => 'Average CO2 per Trip',
                'Value' => number_format(CarbonEmissionAnalysis::whereBetween('analysis_date', [$dateFrom, $dateTo])->avg('carbon_emission'), 3),
                'Unit' => 'kg/trip',
                'Period' => $dateFrom->format('Y-m-d') . ' to ' . $dateTo->format('Y-m-d')
            ],
        ]);

        $filename = 'advanced_report_' . $dateFrom->format('Ymd') . '_' . $dateTo->format('Ymd');
        
        return $this->generateCSV($stats, $filename);
    }

    /**
     * 交通工具名稱（中文）
     */
    private function getTransportModeName($mode)
    {
        $modes = [
            'walking' => '步行',
            'bicycle' => '腳踏車',
            'motorcycle' => '機車',
            'car' => '汽車',
            'bus' => '公車',
            'train' => '火車',
            'metro' => '捷運',
            'other' => '其他'
        ];

        return $modes[$mode] ?? '未知';
    }

    /**
     * 交通工具名稱（英文）
     */
    private function getTransportModeNameEn($mode)
    {
        $modes = [
            'walking' => 'Walking',
            'bicycle' => 'Bicycle',
            'motorcycle' => 'Motorcycle',
            'car' => 'Car',
            'bus' => 'Bus',
            'train' => 'Train',
            'metro' => 'Metro',
            'other' => 'Other'
        ];

        return $modes[$mode] ?? 'Unknown';
    }

    /**
     * 行程類型名稱（英文）
     */
    private function getTripTypeNameEn($type)
    {
        $types = [
            'commute' => 'Commute',
            'business' => 'Business',
            'personal' => 'Personal',
            'other' => 'Other'
        ];

        return $types[$type] ?? 'Unknown';
    }
}