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
                'geofence' => $geofence
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
                'geofence' => $geofence->fresh()
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
}