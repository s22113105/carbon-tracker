<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function index()
    {
        return view('admin.users');
    }

    public function updateRole(Request $request, User $user)
    {
        // 防止修改自己的權限
        if ($user->id === Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => '無法修改自己的權限'
            ], 403);
        }

        $request->validate([
            'role' => 'required|in:admin,user'
        ]);

        $oldRole = $user->role;
        $user->update(['role' => $request->role]);

        $roleText = $request->role === 'admin' ? '管理員' : '一般使用者';

        // 記錄操作日誌
        Log::info("User role updated by admin", [
            'admin_id' => Auth::id(),
            'admin_email' => Auth::user()->email,
            'target_user_id' => $user->id,
            'target_user_email' => $user->email,
            'old_role' => $oldRole,
            'new_role' => $request->role
        ]);

        return response()->json([
            'success' => true,
            'message' => "已成功將 {$user->name} 的權限更新為：{$roleText}"
        ]);
    }

    public function destroy(User $user)
    {
        // 防止刪除自己
        if ($user->id === Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => '無法刪除自己的帳戶'
            ], 403);
        }

        // 防止刪除其他管理員（可選，根據需求調整）
        if ($user->role === 'admin') {
            $adminCount = User::where('role', 'admin')->count();
            if ($adminCount <= 1) {
                return response()->json([
                    'success' => false,
                    'message' => '無法刪除最後一個管理員帳戶'
                ], 403);
            }
        }

        try {
            $userName = $user->name;
            $userEmail = $user->email;

            // 刪除相關資料
            $user->carbonEmissions()->delete();
            $user->trips()->delete();
            
            // 如果有 GPS 記錄也一併刪除
            if (method_exists($user, 'gpsRecords')) {
                $user->gpsRecords()->delete();
            }

            $user->delete();

            // 記錄操作日誌
            Log::info("User deleted by admin", [
                'admin_id' => Auth::id(),
                'admin_email' => Auth::user()->email,
                'deleted_user_id' => $user->id,
                'deleted_user_name' => $userName,
                'deleted_user_email' => $userEmail
            ]);

            return response()->json([
                'success' => true,
                'message' => "已成功刪除使用者：{$userName}"
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to delete user", [
                'admin_id' => Auth::id(),
                'target_user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '刪除失敗：' . $e->getMessage()
            ], 500);
        }
    }

    public function show(User $user)
    {
        // 取得使用者詳細資訊
        $userStats = [
            'total_trips' => $user->trips()->count(),
            'total_emissions' => round($user->carbonEmissions()->sum('co2_emission'), 2),
            'total_distance' => round($user->trips()->sum('distance'), 2),
            'last_login' => $user->updated_at,
            'registration_date' => $user->created_at,
            'recent_trips' => $user->trips()
                ->latest('start_time')
                ->limit(5)
                ->with('carbonEmission')
                ->get(),
            'monthly_emissions' => $user->carbonEmissions()
                ->where('emission_date', '>=', now()->subDays(30))
                ->sum('co2_emission')
        ];

        return response()->json([
            'success' => true,
            'user' => $user,
            'stats' => $userStats
        ]);
    }

    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:delete,activate,deactivate',
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id'
        ]);

        $userIds = $request->user_ids;
        $currentUserId = Auth::id();

        // 防止對自己執行批次操作
        if (in_array($currentUserId, $userIds)) {
            return response()->json([
                'success' => false,
                'message' => '無法對自己執行批次操作'
            ], 403);
        }

        try {
            switch ($request->action) {
                case 'delete':
                    // 防止刪除所有管理員
                    $adminIds = User::whereIn('id', $userIds)->where('role', 'admin')->pluck('id')->toArray();
                    $remainingAdmins = User::where('role', 'admin')->whereNotIn('id', $adminIds)->count();
                    
                    if (count($adminIds) > 0 && $remainingAdmins < 1) {
                        return response()->json([
                            'success' => false,
                            'message' => '無法刪除所有管理員'
                        ], 403);
                    }

                    $deletedCount = User::whereIn('id', $userIds)->delete();
                    $message = "已刪除 {$deletedCount} 個使用者";
                    break;

                case 'activate':
                    $updatedCount = User::whereIn('id', $userIds)->update(['is_active' => true]);
                    $message = "已啟用 {$updatedCount} 個使用者";
                    break;

                case 'deactivate':
                    $updatedCount = User::whereIn('id', $userIds)->update(['is_active' => false]);
                    $message = "已停用 {$updatedCount} 個使用者";
                    break;
            }

            // 記錄批次操作
            Log::info("Bulk user action performed", [
                'admin_id' => $currentUserId,
                'action' => $request->action,
                'affected_users' => $userIds,
                'count' => count($userIds)
            ]);

            return response()->json([
                'success' => true,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '批次操作失敗：' . $e->getMessage()
            ], 500);
        }
    }
}