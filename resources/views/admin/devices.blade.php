@extends('layouts.dashboard')

@section('title', '設備管理')

@section('sidebar-menu')
    <li class="nav-item">
        <a class="nav-link" href="{{ route('admin.dashboard') }}">
            <i class="fas fa-tachometer-alt me-2"></i>總覽儀表板
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('admin.users') }}">
            <i class="fas fa-users me-2"></i>使用者管理
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('admin.statistics') }}">
            <i class="fas fa-chart-bar me-2"></i>全公司統計
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link active" href="{{ route('admin.devices') }}">
            <i class="fas fa-cog me-2"></i>設備統計
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('admin.geofence') }}">
            <i class="fas fa-map-marked-alt me-2"></i>地理圍欄設定
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('admin.settings') }}">
            <i class="fas fa-cog me-2"></i>系統設定
        </a>
    </li>
@endsection

@section('content')

<div class="container">
    <h2>ESP32 設備管理</h2>
    
    <div class="card">
        <div class="card-header">
            <h5>已註冊設備</h5>
        </div>
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>設備ID</th>
                        <th>設備名稱</th>
                        <th>對應使用者</th>
                        <th>最後上線</th>
                        <th>電池電量</th>
                        <th>狀態</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($devices as $device)
                    <tr>
                        <td>{{ $device->device_id }}</td>
                        <td>{{ $device->device_name }}</td>
                        <td>{{ $device->user_name }} ({{ $device->user_email }})</td>
                        <td>{{ $device->last_seen ? \Carbon\Carbon::parse($device->last_seen)->diffForHumans() : '從未' }}</td>
                        <td>{{ $device->battery_level ?? 'N/A' }}%</td>
                        <td>
                            @if($device->is_online)
                                <span class="badge bg-success">在線</span>
                            @else
                                <span class="badge bg-secondary">離線</span>
                            @endif
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="editDevice('{{ $device->device_id }}')">編輯</button>
                            <button class="btn btn-sm btn-danger" onclick="deleteDevice('{{ $device->device_id }}')">刪除</button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- 新增設備按鈕 -->
    <div class="mt-3">
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
            <i class="fas fa-plus"></i> 新增設備
        </button>
    </div>
</div>

<!-- 新增設備 Modal -->
<div class="modal fade" id="addDeviceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">新增ESP32設備</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addDeviceForm">
                    <div class="mb-3">
                        <label class="form-label">設備ID</label>
                        <input type="text" class="form-control" name="device_id" placeholder="ESP32_CARBON_XXX" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">設備名稱</label>
                        <input type="text" class="form-control" name="device_name" placeholder="ESP32 碳排放追蹤器" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">對應使用者</label>
                        <select class="form-control" name="user_id" required>
                            <option value="">選擇使用者</option>
                            @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" onclick="saveDevice()">儲存</button>
            </div>
        </div>
    </div>
</div>

<script>
function saveDevice() {
    const form = document.getElementById('addDeviceForm');
    const formData = new FormData(form);
    
    fetch('/api/devices', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(Object.fromEntries(formData))
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('設備新增成功！');
            location.reload();
        } else {
            alert('新增失敗：' + data.message);
        }
    });
}

function editDevice(deviceId) {
    // 編輯設備邏輯
}

function deleteDevice(deviceId) {
    if (confirm('確定要刪除此設備嗎？')) {
        fetch(`/api/devices/${deviceId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('設備刪除成功！');
                location.reload();
            } else {
                alert('刪除失敗：' + data.message);
            }
        });
    }
}
</script>
@endsection