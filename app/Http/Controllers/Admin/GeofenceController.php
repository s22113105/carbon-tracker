<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Geofence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class GeofenceController extends Controller
{
    public function index()
    {
        $geofences = Geofence::orderBy('created_at', 'desc')->get();
        return view('admin.geofence', compact('geofences'));
    }

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

            Log::info('Geofence created', [
                'geofence_id' => $geofence->id,
                'name' => $geofence->name,
                'created_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => '地理圍欄建立成功！',
                'geofence' => $geofence->load('creator')
            ]);
        } catch (\Exception $e) {
            Log::error('Geofence creation failed', [
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

    public function show(Geofence $geofence)
    {
        try {
            $geofence->load('creator');
            
            // 取得圍欄的統計資訊
            $stats = [
                'total_checks' => 0, // 可以從日誌或其他表取得實際數據
                'active_users_today' => 0, // 今日進出此圍欄的使用者數
                'last_activity' => $geofence->updated_at
            ];

            return response()->json([
                'success' => true,
                'geofence' => $geofence,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '無法取得圍欄資訊：' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, Geofence $geofence)
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

            Log::info('Geofence updated', [
                'geofence_id' => $geofence->id,
                'updated_by' => Auth::id(),
                'old_data' => $oldData,
                'new_data' => $geofence->fresh()->toArray()
            ]);

            return response()->json([
                'success' => true,
                'message' => '地理圍欄更新成功！',
                'geofence' => $geofence->fresh()->load('creator')
            ]);
        } catch (\Exception $e) {
            Log::error('Geofence update failed', [
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

    public function destroy(Geofence $geofence)
    {
        try {
            $geofenceData = $geofence->toArray();
            $geofence->delete();

            Log::info('Geofence deleted', [
                'deleted_geofence' => $geofenceData,
                'deleted_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => '地理圍欄刪除成功！'
            ]);
        } catch (\Exception $e) {
            Log::error('Geofence deletion failed', [
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

    public function toggle(Geofence $geofence)
    {
        try {
            $oldStatus = $geofence->is_active;
            $geofence->update(['is_active' => !$geofence->is_active]);

            Log::info('Geofence status toggled', [
                'geofence_id' => $geofence->id,
                'old_status' => $oldStatus,
                'new_status' => $geofence->is_active,
                'updated_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => $geofence->is_active ? '地理圍欄已啟用' : '地理圍欄已停用',
                'is_active' => $geofence->is_active
            ]);
        } catch (\Exception $e) {
            Log::error('Geofence toggle failed', [
                'geofence_id' => $geofence->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => '狀態切換失敗：' . $e->getMessage()
            ], 500);
        }
    }

    public function checkGeofence(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'user_id' => 'nullable|exists:users,id' // 可選的使用者ID
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $lat = $request->latitude;
            $lng = $request->longitude;
            $userId = $request->user_id ?? Auth::id();

            $geofences = Geofence::where('is_active', true)->get();
            $insideGeofences = [];

            foreach ($geofences as $geofence) {
                $distance = $this->calculateDistance(
                    $lat, $lng,
                    $geofence->latitude, $geofence->longitude
                );

                if ($distance <= $geofence->radius) {
                    $insideGeofences[] = [
                        'id' => $geofence->id,
                        'name' => $geofence->name,
                        'type' => $geofence->type,
                        'distance' => round($distance, 2),
                        'description' => $geofence->description
                    ];
                }
            }

            // 記錄檢查日誌
            Log::info('Geofence check performed', [
                'user_id' => $userId,
                'latitude' => $lat,
                'longitude' => $lng,
                'inside_geofences' => array_column($insideGeofences, 'id'),
                'timestamp' => now()
            ]);

            return response()->json([
                'success' => true,
                'inside_geofences' => $insideGeofences,
                'is_inside_any' => count($insideGeofences) > 0,
                'check_time' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            Log::error('Geofence check failed', [
                'error' => $e->getMessage(),
                'latitude' => $request->latitude ?? null,
                'longitude' => $request->longitude ?? null,
                'user_id' => $request->user_id ?? Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => '位置檢查失敗：' . $e->getMessage()
            ], 500);
        }
    }

    public function getGeofenceData()
    {
        try {
            $geofences = Geofence::where('is_active', true)
                ->select(['id', 'name', 'latitude', 'longitude', 'radius', 'type', 'description'])
                ->get()
                ->map(function ($geofence) {
                    return [
                        'id' => $geofence->id,
                        'name' => $geofence->name,
                        'latitude' => (float) $geofence->latitude,
                        'longitude' => (float) $geofence->longitude,
                        'radius' => (int) $geofence->radius,
                        'type' => $geofence->type,
                        'description' => $geofence->description,
                        'type_name' => $this->getTypeName($geofence->type)
                    ];
                });

            return response()->json([
                'success' => true,
                'geofences' => $geofences,
                'total' => $geofences->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get geofence data', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '無法取得圍欄資料：' . $e->getMessage()
            ], 500);
        }
    }

    public function bulkAction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:activate,deactivate,delete',
            'geofence_ids' => 'required|array|min:1',
            'geofence_ids.*' => 'exists:geofences,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $geofenceIds = $request->geofence_ids;
            $action = $request->action;
            $affectedCount = 0;

            switch ($action) {
                case 'activate':
                    $affectedCount = Geofence::whereIn('id', $geofenceIds)
                        ->update(['is_active' => true]);
                    $message = "已啟用 {$affectedCount} 個地理圍欄";
                    break;

                case 'deactivate':
                    $affectedCount = Geofence::whereIn('id', $geofenceIds)
                        ->update(['is_active' => false]);
                    $message = "已停用 {$affectedCount} 個地理圍欄";
                    break;

                case 'delete':
                    $affectedCount = Geofence::whereIn('id', $geofenceIds)->count();
                    Geofence::whereIn('id', $geofenceIds)->delete();
                    $message = "已刪除 {$affectedCount} 個地理圍欄";
                    break;
            }

            Log::info('Geofence bulk action performed', [
                'action' => $action,
                'geofence_ids' => $geofenceIds,
                'affected_count' => $affectedCount,
                'performed_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => $message,
                'affected_count' => $affectedCount
            ]);
        } catch (\Exception $e) {
            Log::error('Geofence bulk action failed', [
                'action' => $request->action ?? null,
                'geofence_ids' => $request->geofence_ids ?? [],
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => '批次操作失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 計算兩點間距離（使用 Haversine 公式）
     */
    private function calculateDistance($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6371000; // 地球半徑（公尺）

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat/2) * sin($dLat/2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng/2) * sin($dLng/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }

    /**
     * 取得圍欄類型的中文名稱
     */
    private function getTypeName($type)
    {
        $types = [
            'office' => '辦公室',
            'restricted' => '限制區域',
            'parking' => '停車場',
            'custom' => '自訂區域'
        ];

        return $types[$type] ?? '未知';
    }

    /**
     * 取得圍欄統計資訊
     */
    public function getStatistics()
    {
        try {
            $stats = [
                'total_geofences' => Geofence::count(),
                'active_geofences' => Geofence::where('is_active', true)->count(),
                'inactive_geofences' => Geofence::where('is_active', false)->count(),
                'by_type' => Geofence::select('type')
                    ->selectRaw('COUNT(*) as count')
                    ->groupBy('type')
                    ->get()
                    ->mapWithKeys(function ($item) {
                        return [$this->getTypeName($item->type) => $item->count];
                    }),
                'recent_activity' => Geofence::orderBy('updated_at', 'desc')
                    ->limit(5)
                    ->get(['id', 'name', 'updated_at'])
            ];

            return response()->json([
                'success' => true,
                'statistics' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '無法取得統計資訊：' . $e->getMessage()
            ], 500);
        }
    }
}