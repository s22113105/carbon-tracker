@extends('layouts.dashboard')

@section('title', '系統設定')

@section('sidebar-title', '管理員功能')

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
        <a class="nav-link" href="{{ route('admin.geofence') }}">
            <i class="fas fa-map-marked-alt me-2"></i>地理圍欄設定
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link active" href="{{ route('admin.settings') }}">
            <i class="fas fa-cog me-2"></i>系統設定
        </a>
    </li>
@endsection

@section('content')
<div class="row mb-4">
    <div class="col-md-12">
        <h1><i class="fas fa-cog me-2"></i>系統設定</h1>
        <p class="text-muted">管理系統參數、快取、備份與安全設定</p>
    </div>
</div>

<!-- 成功/錯誤訊息 -->
<div id="alertContainer"></div>

<!-- 系統資訊卡片 -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-white-50">PHP 版本</h6>
                        <h4>{{ $systemInfo['php_version'] }}</h4>
                    </div>
                    <i class="fab fa-php fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-white-50">Laravel 版本</h6>
                        <h4>{{ $systemInfo['laravel_version'] }}</h4>
                    </div>
                    <i class="fab fa-laravel fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-warning text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-white-50">資料庫大小</h6>
                        <h4>{{ $systemInfo['database_size'] }} MB</h4>
                    </div>
                    <i class="fas fa-database fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-info text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-white-50">儲存使用量</h6>
                        <h4>{{ $systemInfo['storage_usage'] }} MB</h4>
                    </div>
                    <i class="fas fa-hdd fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- 系統設定表單 -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-sliders-h me-2"></i>系統參數設定</h5>
            </div>
            <div class="card-body">
                <form id="settingsForm">
                    @csrf
                    
                    <!-- 基本設定 -->
                    <div class="mb-4">
                        <h6 class="text-primary mb-3"><i class="fas fa-info-circle me-2"></i>基本設定</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">網站名稱</label>
                                <input type="text" class="form-control" name="settings[site_name]" 
                                       value="{{ $settings['site_name'] }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">聯絡信箱</label>
                                <input type="email" class="form-control" name="settings[contact_email]" 
                                       value="{{ $settings['contact_email'] }}">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">網站描述</label>
                            <textarea class="form-control" name="settings[site_description]" rows="2">{{ $settings['site_description'] }}</textarea>
                        </div>
                    </div>

                    <!-- GPS 和定位設定 -->
                    <div class="mb-4">
                        <h6 class="text-success mb-3"><i class="fas fa-map-marker-alt me-2"></i>GPS 和定位設定</h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">自動打卡半徑 (公尺)</label>
                                <input type="number" class="form-control" name="settings[auto_punch_radius]" 
                                       value="{{ $settings['auto_punch_radius'] }}" min="10" max="1000">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">GPS 精確度閾值 (公尺)</label>
                                <input type="number" class="form-control" name="settings[gps_accuracy_threshold]" 
                                       value="{{ $settings['gps_accuracy_threshold'] }}" min="5" max="100">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">行程合併閾值 (秒)</label>
                                <input type="number" class="form-control" name="settings[trip_merge_threshold]" 
                                       value="{{ $settings['trip_merge_threshold'] }}" min="60" max="3600">
                            </div>
                        </div>
                    </div>

                    <!-- 碳排放計算設定 -->
                    <div class="mb-4">
                        <h6 class="text-warning mb-3"><i class="fas fa-leaf me-2"></i>碳排放設定</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">計算方法</label>
                                <select class="form-select" name="settings[carbon_calculation_method]">
                                    <option value="standard" {{ $settings['carbon_calculation_method'] === 'standard' ? 'selected' : '' }}>標準方法</option>
                                    <option value="enhanced" {{ $settings['carbon_calculation_method'] === 'enhanced' ? 'selected' : '' }}>增強方法</option>
                                    <option value="custom" {{ $settings['carbon_calculation_method'] === 'custom' ? 'selected' : '' }}>自訂方法</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">每日最大行程數</label>
                                <input type="number" class="form-control" name="settings[max_daily_trips]" 
                                       value="{{ $settings['max_daily_trips'] }}" min="1" max="50">
                            </div>
                        </div>
                    </div>

                    <!-- 通知設定 -->
                    <div class="mb-4">
                        <h6 class="text-info mb-3"><i class="fas fa-bell me-2"></i>通知設定</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="settings[email_notifications]" 
                                           value="1" {{ $settings['email_notifications'] === '1' ? 'checked' : '' }}>
                                    <label class="form-check-label">啟用郵件通知</label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="settings[sms_notifications]" 
                                           value="1" {{ $settings['sms_notifications'] === '1' ? 'checked' : '' }}>
                                    <label class="form-check-label">啟用簡訊通知</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 安全設定 -->
                    <div class="mb-4">
                        <h6 class="text-danger mb-3"><i class="fas fa-shield-alt me-2"></i>安全設定</h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">會話過期時間 (分鐘)</label>
                                <input type="number" class="form-control" name="settings[session_timeout]" 
                                       value="{{ $settings['session_timeout'] }}" min="15" max="1440">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">密碼最小長度</label>
                                <input type="number" class="form-control" name="settings[password_min_length]" 
                                       value="{{ $settings['password_min_length'] }}" min="6" max="20">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">API 請求限制 (每分鐘)</label>
                                <input type="number" class="form-control" name="settings[api_rate_limit]" 
                                       value="{{ $settings['api_rate_limit'] }}" min="10" max="1000">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="settings[enable_2fa]" 
                                           value="1" {{ $settings['enable_2fa'] === '1' ? 'checked' : '' }}>
                                    <label class="form-check-label">啟用雙因子驗證</label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="settings[maintenance_mode]" 
                                           value="1" {{ $settings['maintenance_mode'] === '1' ? 'checked' : '' }}>
                                    <label class="form-check-label">維護模式</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 檔案上傳設定 -->
                    <div class="mb-4">
                        <h6 class="text-secondary mb-3"><i class="fas fa-upload me-2"></i>檔案上傳設定</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">最大上傳大小 (MB)</label>
                                <input type="number" class="form-control" name="settings[max_upload_size]" 
                                       value="{{ $settings['max_upload_size'] }}" min="1" max="100">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">允許的檔案類型</label>
                                <input type="text" class="form-control" name="settings[allowed_file_types]" 
                                       value="{{ $settings['allowed_file_types'] }}" 
                                       placeholder="例如: jpg,jpeg,png,pdf">
                            </div>
                        </div>
                    </div>

                    <!-- 系統維護設定 -->
                    <div class="mb-4">
                        <h6 class="text-dark mb-3"><i class="fas fa-tools me-2"></i>系統維護設定</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">備份頻率</label>
                                <select class="form-select" name="settings[backup_frequency]">
                                    <option value="daily" {{ $settings['backup_frequency'] === 'daily' ? 'selected' : '' }}>每日</option>
                                    <option value="weekly" {{ $settings['backup_frequency'] === 'weekly' ? 'selected' : '' }}>每週</option>
                                    <option value="monthly" {{ $settings['backup_frequency'] === 'monthly' ? 'selected' : '' }}>每月</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">日誌保留天數</label>
                                <input type="number" class="form-control" name="settings[log_retention_days]" 
                                       value="{{ $settings['log_retention_days'] }}" min="7" max="365">
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="reset" class="btn btn-secondary me-2">
                            <i class="fas fa-undo me-2"></i>重置
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>儲存設定
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 系統工具和資訊 -->
    <div class="col-lg-4">
        <!-- 系統工具 -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-wrench me-2"></i>系統工具</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-warning" onclick="clearCache()">
                        <i class="fas fa-trash me-2"></i>清除快取
                    </button>
                    <button type="button" class="btn btn-outline-success" onclick="optimizeSystem()">
                        <i class="fas fa-rocket me-2"></i>系統優化
                    </button>
                    <button type="button" class="btn btn-outline-info" onclick="backupDatabase()">
                        <i class="fas fa-database me-2"></i>資料庫備份
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#emailTestModal">
                        <i class="fas fa-envelope me-2"></i>測試郵件
                    </button>
                </div>
            </div>
        </div>

        <!-- 系統資訊 -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>系統資訊</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr>
                        <td><strong>伺服器作業系統：</strong></td>
                        <td>{{ $systemInfo['server_os'] }}</td>
                    </tr>
                    <tr>
                        <td><strong>伺服器時間：</strong></td>
                        <td>{{ $systemInfo['server_time'] }}</td>
                    </tr>
                    <tr>
                        <td><strong>時區：</strong></td>
                        <td>{{ $systemInfo['timezone'] }}</td>
                    </tr>
                    <tr>
                        <td><strong>快取驅動：</strong></td>
                        <td>{{ $systemInfo['cache_driver'] }}</td>
                    </tr>
                    <tr>
                        <td><strong>佇列驅動：</strong></td>
                        <td>{{ $systemInfo['queue_driver'] }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- 最近活動 -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-history me-2"></i>最近系統活動</h6>
            </div>
            <div class="card-body">
                @if(count($recentActivities) > 0)
                    <div class="timeline">
                        @foreach($recentActivities as $activity)
                        <div class="timeline-item mb-3">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <div class="timeline-icon bg-{{ 
                                        $activity['type'] === 'user_login' ? 'success' : 
                                        ($activity['type'] === 'settings_update' ? 'warning' : 
                                        ($activity['type'] === 'backup_complete' ? 'info' : 'secondary'))
                                    }} text-white rounded-circle d-flex align-items-center justify-content-center" 
                                         style="width: 32px; height: 32px;">
                                        <i class="fas fa-{{ 
                                            $activity['type'] === 'user_login' ? 'sign-in-alt' : 
                                            ($activity['type'] === 'settings_update' ? 'cog' : 
                                            ($activity['type'] === 'backup_complete' ? 'database' : 
                                            ($activity['type'] === 'gps_update' ? 'map-marker-alt' : 'calculator')))
                                        }} fa-sm"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1 fs-6">{{ $activity['description'] }}</h6>
                                    <p class="mb-0 text-muted small">
                                        <i class="fas fa-user me-1"></i>{{ $activity['user'] }}
                                        <br>
                                        <i class="fas fa-clock me-1"></i>{{ $activity['time']->diffForHumans() }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted text-center">暫無系統活動記錄</p>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- 郵件測試 Modal -->
<div class="modal fade" id="emailTestModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-envelope me-2"></i>測試郵件發送
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="emailTestForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">收件人信箱</label>
                        <input type="email" class="form-control" id="testEmail" required 
                               placeholder="請輸入測試用的信箱地址">
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        系統將發送一封測試郵件到指定信箱，用於驗證郵件設定是否正常運作。
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>取消
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>發送測試郵件
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 系統設定表單提交
    document.getElementById('settingsForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const settings = {};
        
        // 處理一般欄位
        formData.forEach((value, key) => {
            if (key.startsWith('settings[')) {
                const settingKey = key.replace('settings[', '').replace(']', '');
                settings[settingKey] = value;
            }
        });
        
        // 處理 checkbox（未勾選的不會在 FormData 中）
        document.querySelectorAll('input[type="checkbox"][name^="settings["]').forEach(checkbox => {
            const settingKey = checkbox.name.replace('settings[', '').replace(']', '');
            if (!settings.hasOwnProperty(settingKey)) {
                settings[settingKey] = '0';
            }
        });
        
        fetch('{{ route("admin.settings.update") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ settings: settings })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
            } else {
                let errorMsg = data.message || '設定更新失敗';
                if (data.errors) {
                    errorMsg = Object.values(data.errors).flat().join('<br>');
                }
                showAlert('error', errorMsg);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('error', '設定更新失敗，請稍後再試');
        });
    });
    
    // 郵件測試表單
    document.getElementById('emailTestForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const email = document.getElementById('testEmail').value;
        
        fetch('{{ route("admin.settings.test-email") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ email: email })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
                bootstrap.Modal.getInstance(document.getElementById('emailTestModal')).hide();
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('error', '郵件發送失敗，請檢查郵件設定');
        });
    });
});

// 清除快取
function clearCache() {
    if (confirm('確定要清除所有系統快取嗎？')) {
        fetch('{{ route("admin.settings.clear-cache") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('error', '快取清除失敗');
        });
    }
}

// 系統優化
function optimizeSystem() {
    if (confirm('確定要進行系統優化嗎？這可能需要幾分鐘時間。')) {
        fetch('{{ route("admin.settings.optimize") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('error', '系統優化失敗');
        });
    }
}

// 資料庫備份
function backupDatabase() {
    if (confirm('確定要執行資料庫備份嗎？')) {
        fetch('{{ route("admin.settings.backup") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('error', '資料庫備份失敗');
        });
    }
}

// 顯示警告訊息
function showAlert(type, message) {
    const alertContainer = document.getElementById('alertContainer');
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    
    const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            <i class="fas ${iconClass} me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    alertContainer.innerHTML = alertHtml;
    
    // 自動消失
    setTimeout(() => {
        const alert = alertContainer.querySelector('.alert');
        if (alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }
    }, 5000);
}
</script>
@endsection