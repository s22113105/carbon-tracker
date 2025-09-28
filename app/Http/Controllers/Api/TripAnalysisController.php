<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TripAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class TripAnalysisController extends Controller
{
    protected $tripAnalysisService;

    public function __construct(TripAnalysisService $tripAnalysisService)
    {
        $this->tripAnalysisService = $tripAnalysisService;
    }

    /**
     * 分析指定日期的GPS資料並生成行程
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function analyzeDate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date|before_or_equal:today',
            'force' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '參數驗證失敗',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = Auth::id();
            $date = $request->input('date');
            $force = $request->boolean('force', false);

            Log::info('手動分析行程請求', [
                'user_id' => $userId,
                'date' => $date,
                'force' => $force
            ]);

            if ($force) {
                $trips = $this->tripAnalysisService->reanalyzeTripsForDate($userId, $date);
                $message = '重新分析完成';
            } else {
                $trips = $this->tripAnalysisService->analyzeTripsForDate($userId, $date);
                $message = '分析完成';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'date' => $date,
                    'trips_created' => count($trips),
                    'trips' => $trips->map(function ($trip) {
                        return [
                            'id' => $trip->id,
                            'start_time' => $trip->start_time->format('H:i'),
                            'end_time' => $trip->end_time->format('H:i'),
                            'distance' => round($trip->distance, 2),
                            'transport_mode' => $trip->transport_mode,
                            'trip_type' => $trip->trip_type
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('分析行程失敗', [
                'user_id' => $userId,
                'date' => $date,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '分析失敗: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 批量分析多個日期的GPS資料
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function analyzeDateRange(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date|before_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date|before_or_equal:today',
            'force' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '參數驗證失敗',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = Auth::id();
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $force = $request->boolean('force', false);

            // 檢查日期範圍不超過30天
            $daysDiff = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));
            if ($daysDiff > 30) {
                return response()->json([
                    'success' => false,
                    'message' => '日期範圍不能超過30天'
                ], 422);
            }

            Log::info('批量分析行程請求', [
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days' => $daysDiff + 1,
                'force' => $force
            ]);

            $results = [];
            $totalTrips = 0;
            $currentDate = Carbon::parse($startDate);
            $endDateCarbon = Carbon::parse($endDate);

            while ($currentDate->lte($endDateCarbon)) {
                $dateString = $currentDate->format('Y-m-d');
                
                try {
                    if ($force) {
                        $trips = $this->tripAnalysisService->reanalyzeTripsForDate($userId, $dateString);
                    } else {
                        $trips = $this->tripAnalysisService->analyzeTripsForDate($userId, $dateString);
                    }

                    $results[$dateString] = [
                        'trips_created' => count($trips),
                        'success' => true
                    ];
                    
                    $totalTrips += count($trips);

                } catch (\Exception $e) {
                    $results[$dateString] = [
                        'trips_created' => 0,
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }

                $currentDate->addDay();
            }

            return response()->json([
                'success' => true,
                'message' => '批量分析完成',
                'data' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'total_days' => $daysDiff + 1,
                    'total_trips_created' => $totalTrips,
                    'daily_results' => $results
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('批量分析行程失敗', [
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '批量分析失敗: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 獲取行程的GPS軌跡資料
     * 
     * @param int $tripId
     * @return JsonResponse
     */
    public function getTripTrace($tripId): JsonResponse
    {
        try {
            $userId = Auth::id();
            
            // 驗證行程是否屬於當前用戶
            $trip = \App\Models\Trip::where('id', $tripId)
                ->where('user_id', $userId)
                ->firstOrFail();

            $gpsTrace = $this->tripAnalysisService->getTripGpsTrace($tripId);

            return response()->json([
                'success' => true,
                'data' => [
                    'trip_id' => $tripId,
                    'trip_info' => [
                        'start_time' => $trip->start_time,
                        'end_time' => $trip->end_time,
                        'distance' => $trip->distance,
                        'transport_mode' => $trip->transport_mode,
                        'trip_type' => $trip->trip_type
                    ],
                    'gps_points' => $gpsTrace,
                    'points_count' => count($gpsTrace)
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => '找不到指定的行程記錄'
            ], 404);

        } catch (\Exception $e) {
            Log::error('獲取行程軌跡失敗', [
                'trip_id' => $tripId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '獲取軌跡資料失敗: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 獲取分析統計資料
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getAnalysisStats(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '參數驗證失敗',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = Auth::id();
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            // 基本統計
            $tripsQuery = \App\Models\Trip::where('user_id', $userId)
                ->whereDate('start_time', '>=', $startDate)
                ->whereDate('start_time', '<=', $endDate);

            $totalTrips = $tripsQuery->count();
            $totalDistance = $tripsQuery->sum('distance');
            
            // 交通工具分布
            $transportModes = \App\Models\Trip::where('user_id', $userId)
                ->whereDate('start_time', '>=', $startDate)
                ->whereDate('start_time', '<=', $endDate)
                ->selectRaw('transport_mode, COUNT(*) as count, SUM(distance) as total_distance, AVG(distance) as avg_distance')
                ->groupBy('transport_mode')
                ->get();

            // 行程類型分布
            $tripTypes = \App\Models\Trip::where('user_id', $userId)
                ->whereDate('start_time', '>=', $startDate)
                ->whereDate('start_time', '<=', $endDate)
                ->selectRaw('trip_type, COUNT(*) as count')
                ->groupBy('trip_type')
                ->get();

            // 每日統計
            $dailyStats = \App\Models\Trip::where('user_id', $userId)
                ->whereDate('start_time', '>=', $startDate)
                ->whereDate('start_time', '<=', $endDate)
                ->selectRaw('DATE(start_time) as date, COUNT(*) as trips_count, SUM(distance) as daily_distance')
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'days' => Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1
                    ],
                    'summary' => [
                        'total_trips' => $totalTrips,
                        'total_distance' => round($totalDistance, 2),
                        'avg_distance_per_trip' => $totalTrips > 0 ? round($totalDistance / $totalTrips, 2) : 0
                    ],
                    'transport_modes' => $transportModes,
                    'trip_types' => $tripTypes,
                    'daily_stats' => $dailyStats
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('獲取分析統計失敗', [
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '獲取統計資料失敗: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 刪除指定日期的行程資料
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteTripsForDate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '參數驗證失敗',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = Auth::id();
            $date = $request->input('date');

            Log::info('刪除行程資料請求', [
                'user_id' => $userId,
                'date' => $date
            ]);

            // 刪除行程記錄
            $deletedTrips = \App\Models\Trip::where('user_id', $userId)
                ->whereDate('start_time', $date)
                ->delete();

            // 刪除GPS記錄
            $deletedGps = \App\Models\GpsRecord::where('user_id', $userId)
                ->whereDate('recorded_at', $date)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => '資料刪除完成',
                'data' => [
                    'date' => $date,
                    'deleted_trips' => $deletedTrips,
                    'deleted_gps_records' => $deletedGps
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('刪除行程資料失敗', [
                'user_id' => $userId,
                'date' => $date,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '刪除資料失敗: ' . $e->getMessage()
            ], 500);
        }
    }
}