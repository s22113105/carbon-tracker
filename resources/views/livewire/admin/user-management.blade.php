<div>
    <!-- 成功訊息 -->
    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- 篩選器 -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">篩選條件</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label">搜尋使用者</label>
                    <input type="text" class="form-control" wire:model.live="search" 
                           placeholder="搜尋姓名或信箱...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">角色</label>
                    <select class="form-select" wire:model.live="roleFilter">
                        <option value="">全部</option>
                        <option value="admin">管理員</option>
                        <option value="user">一般使用者</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">每頁顯示</label>
                    <select class="form-select" wire:model.live="perPage">
                        <option value="10">10 筆</option>
                        <option value="25">25 筆</option>
                        <option value="50">50 筆</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" wire:click="clearFilters">
                        <i class="fas fa-times me-1"></i>清除
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
                    <h4>{{ \App\Models\User::count() }}</h4>
                    <p class="mb-0">總使用者數</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h4>{{ \App\Models\User::where('role', 'user')->count() }}</h4>
                    <p class="mb-0">一般使用者</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h4>{{ \App\Models\User::where('role', 'admin')->count() }}</h4>
                    <p class="mb-0">管理員</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h4>{{ \App\Models\User::where('created_at', '>=', now()->subDays(30))->count() }}</h4>
                    <p class="mb-0">本月新用戶</p>
                </div>
            </div>
        </div>
    </div>

    <!-- 使用者列表 -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">使用者列表</h5>
            <span class="badge bg-info">共 {{ $users->total() }} 筆記錄</span>
        </div>
        <div class="card-body">
            @if($users->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>姓名</th>
                                <th>信箱</th>
                                <th>角色</th>
                                <th>註冊時間</th>
                                <th>總碳排放</th>
                                <th>總行程</th>
                                <th>最後活動</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($users as $user)
                                @php
                                    $stats = $this->getUserStats($user->id);
                                @endphp
                                <tr>
                                    <td>{{ $user->id }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle bg-primary text-white me-2" 
                                                 style="width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                                                {{ strtoupper(substr($user->name, 0, 1)) }}
                                            </div>
                                            {{ $user->name }}
                                        </div>
                                    </td>
                                    <td>{{ $user->email }}</td>
                                    <td>
                                        <span class="badge bg-{{ $user->role === 'admin' ? 'danger' : 'primary' }}">
                                            {{ $user->role === 'admin' ? '管理員' : '一般使用者' }}
                                        </span>
                                    </td>
                                    <td>{{ $user->created_at->format('Y-m-d') }}</td>
                                    <td>
                                        <span class="text-{{ $stats['total_emission'] > 10 ? 'danger' : 'success' }}">
                                            {{ $stats['total_emission'] }} kg
                                        </span>
                                    </td>
                                    <td>{{ $stats['total_trips'] }} 次</td>
                                    <td>{{ $stats['last_activity'] }}</td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" title="查看詳情">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            @if($user->id !== auth()->id())
                                                <button class="btn btn-outline-warning" title="編輯權限" 
                                                        wire:click="editUserRole({{ $user->id }})">
                                                    <i class="fas fa-user-cog"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" title="刪除">
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
                        顯示第 {{ $users->firstItem() }} 到 {{ $users->lastItem() }} 筆，共 {{ $users->total() }} 筆記錄
                    </div>
                    <div>
                        {{ $users->links() }}
                    </div>
                </div>
            @else
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">沒有找到符合條件的使用者</h5>
                    <p class="text-muted">請調整搜尋條件</p>
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
                        <h5 class="modal-title">編輯使用者權限</h5>
                        <button type="button" class="btn-close" wire:click="closeEditModal"></button>
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
                            <small>
                                <strong>權限說明：</strong><br>
                                • 一般使用者：只能查看個人資料和碳排放記錄<br>
                                • 管理員：可以管理所有使用者和系統設定
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeEditModal">取消</button>
                        <button type="button" class="btn btn-primary" wire:click="saveUserRole">儲存變更</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>