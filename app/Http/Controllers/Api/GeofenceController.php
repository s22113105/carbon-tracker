<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Geofence;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class GeofenceController extends Controller
{
    /**
     * 取得地理圍欄列表 (需認證)
     */
    public function index(Request $request)
    {
        $query = Geofence::query();

        // 根據參數篩選
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // 排序
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // 分頁
        $perPage = $request->get('per_page', 15);
        $geofences = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $geofences->items(),
            'pagination' => [
                'current_page' => $geofences->currentPage(),
                'last_page' => $geofences->lastPage(),
                'per_page' => $geofences->perPage(),
                'total' => $geofences->total(),
            ]
        ]);
    }

    /**
     * 建立新的地理圍欄 (需認證)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'required|integer|min:10|max:5000',
            'type' => 'required|in:office,restricted,parking,custom',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '資料驗證失敗',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $geofence = Geofence::create([
                'name' => $request->name,
                'description' => $request->description,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'radius' => $request->radius,
                'type' => $request->type,
                'is_active' => $request->boolean('is_active', true),
                'created_by' => Auth::id(),
            ]);

            Log::info('Geofence created via API', [
                'geofence_id' => $geofence->id,
                'name' => $geofence->name,
                'created_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => '地理圍欄建立成功',
                'data' => $geofence
            ], 201);

        } catch (\Exception $e) {
            Log::error('API Geofence creation failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'request_data' => $request->except(['_token'])
            ]);

            return response()->json([
                'success' => false,
                'message' => '建立失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 取得單一地理圍欄 (需認證)
     */
    public function show($id)
    {
        $geofence = Geofence::with('creator')->find($id);

        if (!$geofence) {
            return response()->json([
                'success' => false,
                'message' => '地理圍欄不存在'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $geofence
        ]);
    }

    /**
     * 更新地理圍欄 (需認證)
     */
    public function update(Request $request, $id)
    {
        $geofence = Geofence::find($id);

        if (!$geofence) {
            return response()->json([
                'success' => false,
                'message' => '地理圍欄不存在'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'required|integer|min:10|max:5000',
            'type' => 'required|in:office,restricted,parking,custom',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '資料驗證失敗',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $oldData = $geofence->toArray();
            
            $geofence->update([
                'name' => $request->name,
                'description' => $request->description,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'radius' => $request->radius,
                'type' => $request->type,
                'is_active' => $request->boolean('is_active', true),
            ]);

            Log::info('Geofence updated via API', [
                'geofence_id' => $geofence->id,
                'updated_by' => Auth::id(),
                'old_data' => $oldData,
                'new_data' => $geofence->fresh()->toArray()
            ]);

            return response()->json([
                'success' => true,
                'message' => '地理圍欄更新成功',
                'data' => $geofence->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('API Geofence update failed', [
                'geofence_id' => $geofence->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => '更新失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 刪除地理圍欄 (需認證)
     */
    public function destroy($id)
    {
        $geofence = Geofence::find($id);

        if (!$geofence) {
            return response()->json([
                'success' => false,
                'message' => '地理圍欄不存在'
            ], 404);
        }

        try {
            $geofenceData = $geofence->toArray();
            $geofence->delete();

            Log::info('Geofence deleted via API', [
                'deleted_geofence' => $geofenceData,
                'deleted_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => '地理圍欄刪除成功'
            ]);

        } catch (\Exception $e) {
            Log::error('API Geofence deletion failed', [
                'geofence_id' => $geofence->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => '刪除失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 檢查位置是否在地理圍欄內 (需認證)
     */
    public function checkLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'geofence_id' => 'nullable|exists:geofences,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '參數驗證失敗',
                'errors' => $validator->errors()
            ], 422);
        }

        $latitude = $request->latitude;
        $longitude = $request->longitude;

        try {
            // 如果指定了特定圍欄，只檢查該圍欄
            if ($request->has('geofence_id')) {
                $geofence = Geofence::where('id', $request->geofence_id)
                    ->where('is_active', true)
                    ->first();

                if (!$geofence) {
                    return response()->json([
                        'success' => false,
                        'message' => '指定的地理圍欄不存在或未啟用'
                    ], 404);
                }

                $isInside = $this->isLocationInGeofence($latitude, $longitude, $geofence);

                return response()->json([
                    'success' => true,
                    'is_inside' => $isInside,
                    'geofence' => $geofence,
                    'distance_to_center' => $this->calculateDistance(
                        $latitude, $longitude, 
                        $geofence->latitude, $geofence->longitude
                    )
                ]);
            }

            // 檢查所有啟用的圍欄
            $activeGeofences = Geofence::where('is_active', true)->get();
            $results = [];

            foreach ($activeGeofences as $geofence) {
                $isInside = $this->isLocationInGeofence($latitude, $longitude, $geofence);
                $distance = $this->calculateDistance(
                    $latitude, $longitude, 
                    $geofence->latitude, $geofence->longitude
                );

                $results[] = [
                    'geofence_id' => $geofence->id,
                    'geofence_name' => $geofence->name,
                    'geofence_type' => $geofence->type,
                    'is_inside' => $isInside,
                    'distance_to_center' => $distance,
                    'radius' => $geofence->radius
                ];
            }

            // 找出在哪些圍欄內
            $insideGeofences = array_filter($results, function($result) {
                return $result['is_inside'];
            });

            return response()->json([
                'success' => true,
                'location' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude
                ],
                'inside_geofences' => array_values($insideGeofences),
                'all_results' => $results,
                'total_geofences_checked' => count($activeGeofences),
                'inside_count' => count($insideGeofences)
            ]);

        } catch (\Exception $e) {
            Log::error('Geofence location check failed', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => '位置檢查失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 公開的位置檢查API (無需認證，供ESP32使用)
     */
    public function checkPublic(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'device_id' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '參數驗證失敗',
                'errors' => $validator->errors()
            ], 422);
        }

        $latitude = $request->latitude;
        $longitude = $request->longitude;
        $deviceId = $request->device_id;

        try {
            // 記錄設備位置檢查
            Log::info('ESP32 geofence check', [
                'device_id' => $deviceId,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'timestamp' => now()
            ]);

            // 只檢查辦公室類型的圍欄
            $officeGeofences = Geofence::where('is_active', true)
                ->where('type', 'office')
                ->get();

            $results = [];
            $isInAnyOffice = false;

            foreach ($officeGeofences as $geofence) {
                $isInside = $this->isLocationInGeofence($latitude, $longitude, $geofence);
                $distance = $this->calculateDistance(
                    $latitude, $longitude, 
                    $geofence->latitude, $geofence->longitude
                );

                if ($isInside) {
                    $isInAnyOffice = true;
                }

                $results[] = [
                    'geofence_id' => $geofence->id,
                    'geofence_name' => $geofence->name,
                    'is_inside' => $isInside,
                    'distance_to_center' => round($distance, 2),
                    'radius' => $geofence->radius
                ];
            }

            return response()->json([
                'success' => true,
                'device_id' => $deviceId,
                'timestamp' => now()->toISOString(),
                'location' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude
                ],
                'is_in_office' => $isInAnyOffice,
                'office_geofences' => $results,
                'check_time' => now()->format('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            Log::error('ESP32 geofence check failed', [
                'device_id' => $deviceId,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '位置檢查失敗',
                'error_code' => 'GEOFENCE_CHECK_FAILED',
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * 取得公開的圍欄列表 (無需認證，供ESP32同步)
     */
    public function getPublicList(Request $request)
    {
        try {
            // 只返回啟用的辦公室圍欄
            $geofences = Geofence::where('is_active', true)
                ->where('type', 'office')
                ->select('id', 'name', 'latitude', 'longitude', 'radius', 'type')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'timestamp' => now()->toISOString(),
                'geofences' => $geofences,
                'count' => $geofences->count(),
                'server_time' => now()->format('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            Log::error('Public geofence list failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '取得圍欄列表失敗',
                'error_code' => 'GEOFENCE_LIST_FAILED',
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * 取得地理圍欄統計資料 (需認證)
     */
    public function statistics($id)
    {
        $geofence = Geofence::find($id);

        if (!$geofence) {
            return response()->json([
                'success' => false,
                'message' => '地理圍欄不存在'
            ], 404);
        }

        try {
            // 這裡可以加入更多統計邏輯
            // 例如：進出次數、停留時間、使用者分析等
            
            $stats = [
                'geofence_info' => [
                    'id' => $geofence->id,
                    'name' => $geofence->name,
                    'type' => $geofence->type,
                    'is_active' => $geofence->is_active,
                    'created_at' => $geofence->created_at
                ],
                'area_info' => [
                    'latitude' => $geofence->latitude,
                    'longitude' => $geofence->longitude,
                    'radius_meters' => $geofence->radius,
                    'area_square_meters' => round(pi() * pow($geofence->radius, 2), 2)
                ],
                'usage_stats' => [
                    'total_checks' => 0, // 可從日誌統計
                    'daily_average' => 0,
                    'last_check' => null
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Geofence statistics failed', [
                'geofence_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '統計資料取得失敗'
            ], 500);
        }
    }

    /**
     * 判斷位置是否在地理圍欄內
     */
    private function isLocationInGeofence($latitude, $longitude, $geofence)
    {
        $distance = $this->calculateDistance(
            $latitude, $longitude, 
            $geofence->latitude, $geofence->longitude
        );

        return $distance <= $geofence->radius;
    }

    /**
     * 計算兩點間距離 (公尺)
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // 地球半徑 (公尺)

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat/2) * sin($dLat/2) + 
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
             sin($dLon/2) * sin($dLon/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }
}