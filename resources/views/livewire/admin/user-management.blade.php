<div>
    <!-- 成功訊息 -->
    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- 篩選功能 -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-filter me-2"></i>篩選條件</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">搜尋使用者</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" placeholder="請輸入姓名或信箱" 
                               wire:model.debounce.300ms="search">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">角色</label>
                    <select class="form-select" wire:model="roleFilter">
                        <option value="">全部</option>
                        <option value="user">一般使用者</option>
                        <option value="admin">管理員</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">每頁顯示</label>
                    <select class="form-select" wire:model="perPage">
                        <option value="10">10 筆</option>
                        <option value="20">20 筆</option>
                        <option value="50">50 筆</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button class="btn btn-outline-secondary w-100" wire:click="clearFilters">
                        <i class="fas fa-times me-2"></i>清除
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 統計卡片 -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <div class="d-flex justify-content-center align-items-center mb-2">
                        <i class="fas fa-users fa-2x me-2"></i>
                        <h2>{{ \App\Models\User::count() }}</h2>
                    </div>
                    <p class="mb-0">總使用者數</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <div class="d-flex justify-content-center align-items-center mb-2">
                        <i class="fas fa-user fa-2x me-2"></i>
                        <h2>{{ \App\Models\User::where('role', 'user')->count() }}</h2>
                    </div>
                    <p class="mb-0">一般使用者</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <div class="d-flex justify-content-center align-items-center mb-2">
                        <i class="fas fa-user-shield fa-2x me-2"></i>
                        <h2>{{ \App\Models\User::where('role', 'admin')->count() }}</h2>
                    </div>
                    <p class="mb-0">管理員</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <div class="d-flex justify-content-center align-items-center mb-2">
                        <i class="fas fa-user-plus fa-2x me-2"></i>
                        <h2>{{ \App\Models\User::where('created_at', '>=', now()->subDays(30))->count() }}</h2>
                    </div>
                    <p class="mb-0">本月新用戶</p>
                </div>
            </div>
        </div>
    </div>

    <!-- 使用者列表 -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>使用者列表</h5>
            @if(isset($users))
                <span class="badge bg-info">共 {{ $users->total() }} 筆記錄</span>
            @endif
        </div>
        <div class="card-body">
            @if(isset($users) && $users->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>使用者</th>
                                <th>信箱</th>
                                <th>角色</th>
                                <th>註冊時間</th>
                                <th>總碳排放</th>
                                <th>總行程</th>
                                <th>最後活動</th>
                                <th width="150">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($users as $user)
                                @php
                                    $stats = $this->getUserStats($user->id);
                                @endphp
                                <tr>
                                    <td><strong>{{ $user->id }}</strong></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle bg-{{ $user->role === 'admin' ? 'danger' : 'primary' }} text-white me-2" 
                                                 style="width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: bold;">
                                                {{ strtoupper(substr($user->name, 0, 1)) }}
                                            </div>
                                            <div>
                                                <div class="fw-bold">{{ $user->name }}</div>
                                                @if($user->id === auth()->id())
                                                    <small class="text-muted">(您自己)</small>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $user->email }}</td>
                                    <td>
                                        <span class="badge bg-{{ $user->role === 'admin' ? 'danger' : 'primary' }} fs-6">
                                            {{ $user->role === 'admin' ? '管理員' : '一般使用者' }}
                                        </span>
                                    </td>
                                    <td>{{ $user->created_at->format('Y-m-d') }}</td>
                                    <td>
                                        <span class="badge bg-{{ $stats['total_emission'] > 10 ? 'danger' : 'success' }}">
                                            {{ $stats['total_emission'] }} kg
                                        </span>
                                    </td>
                                    <td><span class="badge bg-info">{{ $stats['total_trips'] }} 次</span></td>
                                    <td>
                                        <small class="text-muted">{{ $stats['last_activity'] }}</small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            @if($user->id !== auth()->id())
                                                <button class="btn btn-outline-warning" title="編輯權限" 
                                                        wire:click="editUserRole({{ $user->id }})">
                                                    <i class="fas fa-user-cog"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" title="刪除使用者" 
                                                        wire:click="confirmDelete({{ $user->id }})">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            @else
                                                <span class="badge bg-secondary">自己</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- 分頁 -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div>
                        <small class="text-muted">
                            顯示第 {{ $users->firstItem() ?? 0 }} 到 {{ $users->lastItem() ?? 0 }} 筆，共 {{ $users->total() }} 筆記錄
                        </small>
                    </div>
                    <div>
                        {{ $users->links() }}
                    </div>
                </div>
            @else
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">沒有找到符合條件的使用者</h5>
                    <p class="text-muted">請調整搜尋條件或新增使用者</p>
                </div>
            @endif
        </div>
    </div>

    <!-- 權限編輯 Modal -->
    @if($showEditModal)
        <div class="modal d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-user-cog me-2"></i>編輯使用者權限
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeEditModal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">使用者名稱</label>
                            <input type="text" class="form-control" value="{{ $editingUserName }}" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">選擇權限</label>
                            <select class="form-select" wire:model="editingUserRole">
                                <option value="user">一般使用者</option>
                                <option value="admin">管理員</option>
                            </select>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>權限說明：</strong><br>
                            <small>
                                • <strong>一般使用者</strong>：只能查看個人資料和碳排放記錄<br>
                                • <strong>管理員</strong>：可以管理所有使用者和系統設定
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeEditModal">
                            <i class="fas fa-times me-2"></i>取消
                        </button>
                        <button type="button" class="btn btn-primary" wire:click="saveUserRole">
                            <i class="fas fa-save me-2"></i>儲存變更
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- 刪除確認 Modal -->
    @if($showDeleteModal)
        <div class="modal d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>確認刪除
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeDeleteModal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-warning me-2"></i>
                            <strong>警告：</strong>此操作無法復原！
                        </div>
                        <p>您確定要刪除使用者 <strong>{{ $deletingUserName }}</strong> 嗎？</p>
                        <p class="text-muted">
                            <small>
                                刪除後將會：<br>
                                • 永久刪除該使用者的所有資料<br>
                                • 刪除所有相關的行程記錄<br>
                                • 刪除所有碳排放記錄
                            </small>
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeDeleteModal">
                            <i class="fas fa-times me-2"></i>取消
                        </button>
                        <button type="button" class="btn btn-danger" wire:click="deleteUser">
                            <i class="fas fa-trash me-2"></i>確認刪除
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>