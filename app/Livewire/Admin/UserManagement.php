<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\CarbonEmission;
use App\Models\Trip;

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

    protected $queryString = [
        'search' => ['except' => ''],
        'roleFilter' => ['except' => '']
    ];

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
        if ($user) {
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
        if ($user && $user->id !== auth()->id()) { // 防止自己修改自己的權限
            $user->update(['role' => $this->editingUserRole]);
            
            session()->flash('message', "已成功更新 {$this->editingUserName} 的權限為：" . 
                ($this->editingUserRole === 'admin' ? '管理員' : '一般使用者'));
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