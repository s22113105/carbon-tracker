<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * 取得所有使用者列表（分頁）
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');
            $role = $request->get('role');
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');

            $query = User::query();

            // 搜尋功能
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // 角色篩選
            if ($role && in_array($role, ['admin', 'user'])) {
                $query->where('role', $role);
            }

            // 排序
            $allowedSorts = ['id', 'name', 'email', 'role', 'created_at', 'updated_at'];
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
            }

            $users = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $users->items(),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get users list', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => '取得使用者列表失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 取得指定使用者詳細資訊
     */
    public function show($id)
    {
        try {
            $user = User::with(['trips', 'carbonEmissions', 'gpsRecords'])
                ->findOrFail($id);

            // 計算統計資料
            $stats = [
                'total_trips' => $user->trips()->count(),
                'total_distance' => $user->trips()->sum('distance'),
                'total_co2_emission' => $user->carbonEmissions()->sum('co2_emission'),
                'avg_trip_distance' => $user->trips()->avg('distance'),
                'last_trip_date' => $user->trips()->latest()->first()?->created_at?->format('Y-m-d H:i:s'),
                'registration_days' => $user->created_at->diffInDays(now()),
            ];

            return response()->json([
                'success' => true,
                'data' => $user,
                'stats' => $stats
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => '找不到指定的使用者'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to get user details', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '取得使用者詳細資訊失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 建立新使用者
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'sometimes|in:admin,user'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '資料驗證失敗',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->get('role', 'user'),
                'email_verified_at' => now(), // API 建立的使用者直接設為已驗證
            ]);

            Log::info('User created via API', [
                'user_id' => $user->id,
                'created_by' => Auth::id(),
                'user_data' => $user->only(['name', 'email', 'role'])
            ]);

            return response()->json([
                'success' => true,
                'message' => '使用者建立成功',
                'data' => $user->fresh()
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create user', [
                'error' => $e->getMessage(),
                'request_data' => $request->except(['password', 'password_confirmation'])
            ]);

            return response()->json([
                'success' => false,
                'message' => '建立使用者失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 更新使用者資訊
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'email' => [
                    'sometimes',
                    'required',
                    'string',
                    'email',
                    'max:255',
                    Rule::unique('users')->ignore($user->id),
                ],
                'password' => 'sometimes|string|min:8|confirmed',
                'role' => 'sometimes|in:admin,user'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '資料驗證失敗',
                    'errors' => $validator->errors()
                ], 422);
            }

            // 防止使用者修改自己的權限
            if ($user->id === Auth::id() && $request->has('role')) {
                return response()->json([
                    'success' => false,
                    'message' => '不能修改自己的權限'
                ], 403);
            }

            $oldData = $user->toArray();
            $updateData = [];

            if ($request->has('name')) {
                $updateData['name'] = $request->name;
            }

            if ($request->has('email')) {
                $updateData['email'] = $request->email;
            }

            if ($request->has('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            if ($request->has('role')) {
                $updateData['role'] = $request->role;
            }

            $user->update($updateData);

            Log::info('User updated via API', [
                'user_id' => $user->id,
                'updated_by' => Auth::id(),
                'old_data' => $oldData,
                'new_data' => $user->fresh()->toArray()
            ]);

            return response()->json([
                'success' => true,
                'message' => '使用者資訊更新成功',
                'data' => $user->fresh()
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => '找不到指定的使用者'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to update user', [
                'user_id' => $id,
                'error' => $e->getMessage(),
                'request_data' => $request->except(['password', 'password_confirmation'])
            ]);

            return response()->json([
                'success' => false,
                'message' => '更新使用者失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 刪除使用者
     */
    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);

            // 防止刪除自己
            if ($user->id === Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => '不能刪除自己的帳號'
                ], 403);
            }

            // 防止刪除最後一個管理員
            if ($user->role === 'admin') {
                $adminCount = User::where('role', 'admin')->count();
                if ($adminCount <= 1) {
                    return response()->json([
                        'success' => false,
                        'message' => '不能刪除最後一個管理員帳號'
                    ], 403);
                }
            }

            $userData = $user->toArray();
            $user->delete();

            Log::info('User deleted via API', [
                'deleted_user' => $userData,
                'deleted_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => '使用者刪除成功'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => '找不到指定的使用者'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to delete user', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '刪除使用者失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 批次操作使用者
     */
    public function bulkAction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:delete,update_role,activate,deactivate',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
            'role' => 'required_if:action,update_role|in:admin,user'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '資料驗證失敗',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $action = $request->action;
            $userIds = $request->user_ids;
            $currentUserId = Auth::id();

            // 防止對自己進行批次操作
            if (in_array($currentUserId, $userIds)) {
                return response()->json([
                    'success' => false,
                    'message' => '不能對自己進行批次操作'
                ], 403);
            }

            $users = User::whereIn('id', $userIds)->get();
            $successCount = 0;
            $errors = [];

            foreach ($users as $user) {
                try {
                    switch ($action) {
                        case 'delete':
                            // 防止刪除管理員
                            if ($user->role === 'admin') {
                                $errors[] = "無法刪除管理員：{$user->name}";
                                continue 2;
                            }
                            $user->delete();
                            break;

                        case 'update_role':
                            $user->update(['role' => $request->role]);
                            break;

                        case 'activate':
                            // 假設有 is_active 欄位
                            if (in_array('is_active', $user->getFillable())) {
                                $user->update(['is_active' => true]);
                            }
                            break;

                        case 'deactivate':
                            // 假設有 is_active 欄位
                            if (in_array('is_active', $user->getFillable())) {
                                $user->update(['is_active' => false]);
                            }
                            break;
                    }

                    $successCount++;

                } catch (\Exception $e) {
                    $errors[] = "處理使用者 {$user->name} 時發生錯誤：{$e->getMessage()}";
                }
            }

            Log::info('Bulk action performed on users', [
                'action' => $action,
                'user_ids' => $userIds,
                'success_count' => $successCount,
                'errors' => $errors,
                'performed_by' => $currentUserId
            ]);

            return response()->json([
                'success' => true,
                'message' => "批次操作完成，成功處理 {$successCount} 個使用者",
                'success_count' => $successCount,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk action failed', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => '批次操作失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 取得使用者統計資料
     */
    public function statistics($id)
    {
        try {
            $user = User::findOrFail($id);

            // 基本統計
            $stats = [
                'basic' => [
                    'total_trips' => $user->trips()->count(),
                    'total_distance' => $user->trips()->sum('distance'),
                    'total_co2_emission' => $user->carbonEmissions()->sum('co2_emission'),
                    'avg_trip_distance' => $user->trips()->avg('distance'),
                    'registration_days' => $user->created_at->diffInDays(now()),
                ],
                'monthly' => [],
                'transport_modes' => [],
                'recent_activity' => []
            ];

            // 月度統計（最近6個月）
            for ($i = 5; $i >= 0; $i--) {
                $month = now()->subMonths($i)->startOfMonth();
                $monthEnd = $month->copy()->endOfMonth();

                $monthlyTrips = $user->trips()
                    ->whereBetween('created_at', [$month, $monthEnd])
                    ->get();

                $monthlyEmissions = $user->carbonEmissions()
                    ->whereBetween('emission_date', [$month, $monthEnd])
                    ->sum('co2_emission');

                $stats['monthly'][] = [
                    'month' => $month->format('Y-m'),
                    'trips' => $monthlyTrips->count(),
                    'distance' => $monthlyTrips->sum('distance'),
                    'co2_emission' => $monthlyEmissions
                ];
            }

            // 交通工具使用統計
            $transportStats = $user->trips()
                ->selectRaw('transport_mode, COUNT(*) as count, SUM(distance) as total_distance')
                ->groupBy('transport_mode')
                ->get();

            foreach ($transportStats as $stat) {
                $stats['transport_modes'][] = [
                    'mode' => $stat->transport_mode,
                    'mode_name' => $this->getTransportModeName($stat->transport_mode),
                    'count' => $stat->count,
                    'total_distance' => $stat->total_distance
                ];
            }

            // 最近活動
            $stats['recent_activity'] = $user->trips()
                ->latest()
                ->limit(10)
                ->get(['id', 'start_time', 'distance', 'transport_mode'])
                ->map(function ($trip) {
                    return [
                        'id' => $trip->id,
                        'date' => $trip->start_time->format('Y-m-d H:i'),
                        'distance' => $trip->distance,
                        'transport_mode' => $this->getTransportModeName($trip->transport_mode)
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => '找不到指定的使用者'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to get user statistics', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '取得使用者統計失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 搜尋使用者
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:2',
            'limit' => 'sometimes|integer|min:1|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '搜尋參數無效',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = $request->q;
            $limit = $request->get('limit', 10);

            $users = User::where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%");
            })
            ->limit($limit)
            ->get(['id', 'name', 'email', 'role', 'created_at']);

            return response()->json([
                'success' => true,
                'data' => $users,
                'count' => $users->count()
            ]);

        } catch (\Exception $e) {
            Log::error('User search failed', [
                'query' => $request->q,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '搜尋失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 變更使用者密碼
     */
    public function changePassword(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8|confirmed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '密碼驗證失敗',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::findOrFail($id);

            $user->update([
                'password' => Hash::make($request->password)
            ]);

            Log::info('User password changed via API', [
                'user_id' => $user->id,
                'changed_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => '密碼變更成功'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => '找不到指定的使用者'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to change user password', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '密碼變更失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 取得交通工具名稱
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
}