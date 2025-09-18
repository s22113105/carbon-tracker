<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\CarbonEmission;
use App\Models\Trip;
use Illuminate\Support\Facades\Log;

class UserManagement extends Component
{
    use WithPagination;

    public $search = '';
    public $roleFilter = '';
    public $perPage = 10;
    
    // 權限編輯相關
    public $editingUserId = null;
    public $editingUserName = '';
    public $editingUserRole = '';
    public $showEditModal = false;
    
    // 刪除確認相關
    public $deletingUserId = null;
    public $deletingUserName = '';
    public $showDeleteModal = false;

    protected $queryString = [
        'search' => ['except' => ''],
        'roleFilter' => ['except' => '']
    ];

    protected $listeners = ['userUpdated' => '$refresh'];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingRoleFilter()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->roleFilter = '';
        $this->resetPage();
    }

    public function editUserRole($userId)
    {
        $user = User::find($userId);
        if ($user && $user->id !== auth()->id()) { // 防止編輯自己
            $this->editingUserId = $userId;
            $this->editingUserName = $user->name;
            $this->editingUserRole = $user->role;
            $this->showEditModal = true;
        }
    }

    public function saveUserRole()
    {
        $this->validate([
            'editingUserRole' => 'required|in:admin,user'
        ]);

        $user = User::find($this->editingUserId);
        if ($user && $user->id !== auth()->id()) {
            $oldRole = $user->role;
            $user->update(['role' => $this->editingUserRole]);
            
            $roleText = $this->editingUserRole === 'admin' ? '管理員' : '一般使用者';
            session()->flash('message', "已成功將 {$this->editingUserName} 的權限更新為：{$roleText}");
            
            // 記錄操作日誌
            Log::info("User role updated", [
                'admin_id' => auth()->id(),
                'user_id' => $user->id,
                'old_role' => $oldRole,
                'new_role' => $this->editingUserRole
            ]);
        }

        $this->closeEditModal();
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->editingUserId = null;
        $this->editingUserName = '';
        $this->editingUserRole = '';
    }

    public function confirmDelete($userId)
    {
        $user = User::find($userId);
        if ($user && $user->id !== auth()->id()) { // 防止刪除自己
            $this->deletingUserId = $userId;
            $this->deletingUserName = $user->name;
            $this->showDeleteModal = true;
        }
    }

    public function deleteUser()
    {
        $user = User::find($this->deletingUserId);
        if ($user && $user->id !== auth()->id()) {
            // 防止刪除最後一個管理員
            if ($user->role === 'admin') {
                $adminCount = User::where('role', 'admin')->count();
                if ($adminCount <= 1) {
                    session()->flash('error', '無法刪除最後一個管理員帳戶');
                    $this->closeDeleteModal();
                    return;
                }
            }
            
            // 刪除相關的碳排放和行程記錄
            $user->carbonEmissions()->delete();
            $user->trips()->delete();
            
            // 如果有 GPS 記錄也一併刪除
            if (method_exists($user, 'gpsRecords')) {
                $user->gpsRecords()->delete();
            }
            
            $userName = $user->name;
            $user->delete();
            
            session()->flash('message', "已成功刪除使用者：{$userName}");
            
            Log::info("User deleted", [
                'admin_id' => auth()->id(),
                'deleted_user_id' => $this->deletingUserId,
                'deleted_user_name' => $userName
            ]);
        }

        $this->closeDeleteModal();
        $this->resetPage();
    }

    public function closeDeleteModal()
    {
        $this->showDeleteModal = false;
        $this->deletingUserId = null;
        $this->deletingUserName = '';
    }

    public function getUserStats($userId)
    {
        $totalEmission = CarbonEmission::where('user_id', $userId)->sum('co2_emission');
        $totalTrips = Trip::where('user_id', $userId)->count();
        $lastActivity = Trip::where('user_id', $userId)->latest('start_time')->first();
        
        return [
            'total_emission' => round($totalEmission, 2),
            'total_trips' => $totalTrips,
            'last_activity' => $lastActivity ? $lastActivity->start_time->format('Y-m-d') : '無記錄'
        ];
    }

    public function render()
    {
        $query = User::query();

        // 搜尋功能
        if ($this->search) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }

        // 角色篩選
        if ($this->roleFilter) {
            $query->where('role', $this->roleFilter);
        }

        $users = $query->orderBy('created_at', 'desc')
            ->paginate($this->perPage);

        return view('livewire.admin.user-management', [
            'users' => $users
        ]);
    }
}