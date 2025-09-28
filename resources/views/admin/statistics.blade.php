@extends('layouts.dashboard')

@section('title', '全公司統計')

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
        <a class="nav-link active" href="{{ route('admin.statistics') }}">
            <i class="fas fa-chart-bar me-2"></i>全公司統計
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('admin.devices') }}">
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
<div class="row mb-4">
    <div class="col-md-12">
        <h1><i class="fas fa-chart-bar me-2"></i>全公司統計</h1>
        <p class="text-muted">查看公司整體碳排放統計與使用情況分析</p>
    </div>
</div>

<!-- 警告訊息區域 -->
<div id="alertContainer"></div>

<!-- 總體統計卡片 -->
<div class="row mb-4">
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body text-center">
                <i class="fas fa-users fa-2x mb-2"></i>
                <h3>{{ $totalStats['total_users'] }}</h3>
                <p class="mb-0">總使用者</p>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card bg-success text-white h-100">
            <div class="card-body text-center">
                <i class="fas fa-route fa-2x mb-2"></i>
                <h3>{{ $totalStats['total_trips'] }}</h3>
                <p class="mb-0">總行程數</p>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card bg-danger text-white h-100">
            <div class="card-body text-center">
                <i class="fas fa-smog fa-2x mb-2"></i>
                <h3>{{ $totalStats['total_emissions'] }}</h3>
                <p class="mb-0">總碳排放(kg)</p>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card bg-warning text-white h-100">
            <div class="card-body text-center">
                <i class="fas fa-map-marked-alt fa-2x mb-2"></i>
                <h3>{{ $totalStats['total_distance'] }}</h3>
                <p class="mb-0">總距離(km)</p>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card bg-info text-white h-100">
            <div class="card-body text-center">
                <i class="fas fa-user-check fa-2x mb-2"></i>
                <h3>{{ $totalStats['active_users_today'] }}</h3>
                <p class="mb-0">今日活躍</p>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card bg-dark text-white h-100">
            <div class="card-body text-center">
                <i class="fas fa-calculator fa-2x mb-2"></i>
                <h3>{{ round($totalStats['total_emissions'] / max(1, $totalStats['total_users']), 1) }}</h3>
                <p class="mb-0">人均排放(kg)</p>
            </div>
        </div>
    </div>
</div>

<!-- 報表匯出區域 -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-file-export me-2"></i>報表匯出 (CSV 格式)</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- 快速匯出 -->
                    <div class="col-lg-6">
                        <h6 class="text-muted mb-3">快速匯出</h6>
                        <div class="row">
                            <div class="col-md-4 mb-2">
                                <a href="{{ route('admin.statistics.export', ['type' => 'users', 'format' => 'csv']) }}" 
                                   class="btn btn-outline-primary d-grid">
                                    <i class="fas fa-users me-2"></i>使用者資料
                                </a>
                            </div>
                            <div class="col-md-4 mb-2">
                                <a href="{{ route('admin.statistics.export', ['type' => 'emissions', 'format' => 'csv']) }}" 
                                   class="btn btn-outline-danger d-grid">
                                    <i class="fas fa-smog me-2"></i>碳排放資料
                                </a>
                            </div>
                            <div class="col-md-4 mb-2">
                                <a href="{{ route('admin.statistics.export', ['type' => 'trips', 'format' => 'csv']) }}" 
                                   class="btn btn-outline-success d-grid">
                                    <i class="fas fa-route me-2"></i>行程資料
                                </a>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-12">
                                <button class="btn btn-outline-info" onclick="openAdvancedExport()">
                                    <i class="fas fa-cog me-2"></i>進階匯出（自訂日期範圍）
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- 匯出說明 -->
                    <div class="col-lg-6">
                        <h6 class="text-muted mb-3">匯出說明</h6>
                        <div class="alert alert-info mb-0">
                            <small>
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>CSV 格式說明：</strong><br>
                                • 使用 UTF-8 編碼，已解決中文亂碼問題<br>
                                • 支援所有試算表軟體（Excel、Google Sheets、LibreOffice）<br>
                                • 檔案較小，下載速度快<br>
                                • 進階匯出可自訂日期範圍和統計內容<br><br>
                                <strong>使用建議：</strong><br>
                                用 Excel 開啟時請選擇「資料 → 從文字檔」來正確載入
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 月度對比 -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>本月與上月對比</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                            <div>
                                <h6 class="mb-0">行程數</h6>
                                <h4 class="mb-0 text-primary">{{ $monthlyStats['current']['trips'] }}</h4>
                                @php
                                    $tripChange = $monthlyStats['last']['trips'] > 0 ? 
                                        (($monthlyStats['current']['trips'] - $monthlyStats['last']['trips']) / $monthlyStats['last']['trips']) * 100 : 0;
                                @endphp
                                <small class="text-{{ $tripChange >= 0 ? 'success' : 'danger' }}">
                                    <i class="fas fa-{{ $tripChange >= 0 ? 'arrow-up' : 'arrow-down' }}"></i>
                                    {{ abs(round($tripChange, 1)) }}%
                                </small>
                            </div>
                            <i class="fas fa-route fa-2x text-muted"></i>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                            <div>
                                <h6 class="mb-0">碳排放</h6>
                                <h4 class="mb-0 text-danger">{{ $monthlyStats['current']['emissions'] }}kg</h4>
                                @php
                                    $emissionChange = $monthlyStats['last']['emissions'] > 0 ? 
                                        (($monthlyStats['current']['emissions'] - $monthlyStats['last']['emissions']) / $monthlyStats['last']['emissions']) * 100 : 0;
                                @endphp
                                <small class="text-{{ $emissionChange >= 0 ? 'danger' : 'success' }}">
                                    <i class="fas fa-{{ $emissionChange >= 0 ? 'arrow-up' : 'arrow-down' }}"></i>
                                    {{ abs(round($emissionChange, 1)) }}%
                                </small>
                            </div>
                            <i class="fas fa-smog fa-2x text-muted"></i>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                            <div>
                                <h6 class="mb-0">總距離</h6>
                                <h4 class="mb-0 text-warning">{{ $monthlyStats['current']['distance'] }}km</h4>
                                @php
                                    $distanceChange = $monthlyStats['last']['distance'] > 0 ? 
                                        (($monthlyStats['current']['distance'] - $monthlyStats['last']['distance']) / $monthlyStats['last']['distance']) * 100 : 0;
                                @endphp
                                <small class="text-{{ $distanceChange >= 0 ? 'success' : 'danger' }}">
                                    <i class="fas fa-{{ $distanceChange >= 0 ? 'arrow-up' : 'arrow-down' }}"></i>
                                    {{ abs(round($distanceChange, 1)) }}%
                                </small>
                            </div>
                            <i class="fas fa-map-marked-alt fa-2x text-muted"></i>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                            <div>
                                <h6 class="mb-0">新用戶</h6>
                                <h4 class="mb-0 text-info">{{ $monthlyStats['current']['users'] }}</h4>
                                @php
                                    $userChange = $monthlyStats['last']['users'] > 0 ? 
                                        (($monthlyStats['current']['users'] - $monthlyStats['last']['users']) / $monthlyStats['last']['users']) * 100 : 0;
                                @endphp
                                <small class="text-{{ $userChange >= 0 ? 'success' : 'danger' }}">
                                    <i class="fas fa-{{ $userChange >= 0 ? 'arrow-up' : 'arrow-down' }}"></i>
                                    {{ abs(round($userChange, 1)) }}%
                                </small>
                            </div>
                            <i class="fas fa-user-plus fa-2x text-muted"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- 近30天統計圖表 -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>近30天趨勢</h5>
            </div>
            <div class="card-body">
                <canvas id="dailyStatsChart" height="300"></canvas>
            </div>
        </div>
    </div>

    <!-- 交通工具使用統計 -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>交通工具使用</h5>
            </div>
            <div class="card-body">
                <canvas id="transportChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- 交通工具詳細統計 -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>交通工具詳細統計</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>交通工具</th>
                                <th>使用次數</th>
                                <th>總碳排放</th>
                                <th>平均排放</th>
                                <th>總距離</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($transportStats as $transport)
                            <tr>
                                <td>
                                    <i class="fas fa-{{ $transport['mode_code'] === 'walking' ? 'walking' : 
                                        ($transport['mode_code'] === 'bus' ? 'bus' : 
                                        ($transport['mode_code'] === 'metro' ? 'subway' : 
                                        ($transport['mode_code'] === 'car' ? 'car' : 
                                        ($transport['mode_code'] === 'motorcycle' ? 'motorcycle' : 'question')))) }} me-2"></i>
                                    {{ $transport['mode'] }}
                                </td>
                                <td><span class="badge bg-primary">{{ $transport['usage_count'] }}</span></td>
                                <td><span class="badge bg-danger">{{ $transport['total_emission'] }}kg</span></td>
                                <td><span class="badge bg-warning">{{ $transport['avg_emission'] }}kg</span></td>
                                <td><span class="badge bg-info">{{ $transport['total_distance'] }}km</span></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 最活躍使用者 -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>最活躍使用者 TOP 10</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>排名</th>
                                <th>使用者</th>
                                <th>行程數</th>
                                <th>總排放</th>
                                <th>日均排放</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($topUsers as $index => $user)
                            <tr>
                                <td>
                                    @if($index < 3)
                                        <i class="fas fa-medal text-{{ $index === 0 ? 'warning' : ($index === 1 ? 'secondary' : 'danger') }}"></i>
                                    @else
                                        {{ $index + 1 }}
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle bg-primary text-white me-2" 
                                             style="width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px;">
                                            {{ strtoupper(substr($user['name'], 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="fw-bold">{{ $user['name'] }}</div>
                                            <small class="text-muted">{{ $user['email'] }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-success">{{ $user['total_trips'] }}</span></td>
                                <td><span class="badge bg-danger">{{ $user['total_emissions'] }}kg</span></td>
                                <td><span class="badge bg-info">{{ $user['avg_daily_emission'] }}kg</span></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 部門統計 -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-building me-2"></i>各部門碳排放統計</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    @foreach($departmentStats as $dept)
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h6 class="card-title">{{ $dept['name'] }}</h6>
                                <div class="row">
                                    <div class="col-6">
                                        <h4 class="text-primary">{{ $dept['users'] }}</h4>
                                        <small class="text-muted">使用者</small>
                                    </div>
                                    <div class="col-6">
                                        <h4 class="text-danger">{{ $dept['emissions'] }}</h4>
                                        <small class="text-muted">碳排放(kg)</small>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        人均: {{ round($dept['emissions'] / max(1, $dept['users']), 1) }} kg
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 進階匯出 Modal -->
<div class="modal fade" id="advancedExportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-cog me-2"></i>進階匯出設定
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="advancedExportForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">開始日期</label>
                                <input type="date" class="form-control" id="exportDateFrom" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">結束日期</label>
                                <input type="date" class="form-control" id="exportDateTo" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        進階匯出將包含指定日期範圍內的綜合統計資料，格式為 CSV
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-download me-2"></i>匯出 CSV 報表
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// 設定預設日期
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date();
    const oneMonthAgo = new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000);
    
    document.getElementById('exportDateTo').value = today.toISOString().split('T')[0];
    document.getElementById('exportDateFrom').value = oneMonthAgo.toISOString().split('T')[0];
});

// 開啟進階匯出視窗
function openAdvancedExport() {
    const modal = new bootstrap.Modal(document.getElementById('advancedExportModal'));
    modal.show();
}

// 進階匯出表單提交
document.getElementById('advancedExportForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const dateFrom = document.getElementById('exportDateFrom').value;
    const dateTo = document.getElementById('exportDateTo').value;
    
    if (!dateFrom || !dateTo) {
        showAlert('error', '請選擇日期範圍');
        return;
    }
    
    if (new Date(dateFrom) > new Date(dateTo)) {
        showAlert('error', '開始日期不能晚於結束日期');
        return;
    }
    
    // 建立下載連結
    const url = `{{ route('admin.statistics.export-advanced') }}?date_from=${dateFrom}&date_to=${dateTo}&format=csv`;
    window.location.href = url;
    
    // 關閉 Modal
    bootstrap.Modal.getInstance(document.getElementById('advancedExportModal')).hide();
    
    showAlert('success', 'CSV 報表下載已開始');
});

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

// 近30天趨勢圖
const dailyCtx = document.getElementById('dailyStatsChart').getContext('2d');
new Chart(dailyCtx, {
    type: 'line',
    data: {
        labels: {!! json_encode(collect($dailyStats)->pluck('date_formatted')) !!},
        datasets: [{
            label: '每日行程',
            data: {!! json_encode(collect($dailyStats)->pluck('trips')) !!},
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1,
            yAxisID: 'y'
        }, {
            label: '每日碳排放(kg)',
            data: {!! json_encode(collect($dailyStats)->pluck('emissions')) !!},
            borderColor: 'rgb(255, 99, 132)',
            backgroundColor: 'rgba(255, 99, 132, 0.2)',
            tension: 0.1,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        scales: {
            x: {
                display: true,
                title: {
                    display: true,
                    text: '日期'
                }
            },
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: '行程數'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: '碳排放(kg)'
                },
                grid: {
                    drawOnChartArea: false,
                }
            }
        }
    }
});

// 交通工具使用圓餅圖
const transportCtx = document.getElementById('transportChart').getContext('2d');
new Chart(transportCtx, {
    type: 'doughnut',
    data: {
        labels: {!! json_encode($transportStats->pluck('mode')) !!},
        datasets: [{
            data: {!! json_encode($transportStats->pluck('usage_count')) !!},
            backgroundColor: [
                'rgba(255, 99, 132, 0.8)',
                'rgba(54, 162, 235, 0.8)',
                'rgba(255, 205, 86, 0.8)',
                'rgba(75, 192, 192, 0.8)',
                'rgba(153, 102, 255, 0.8)',
                'rgba(255, 159, 64, 0.8)'
            ],
            borderColor: [
                'rgba(255, 99, 132, 1)',
                'rgba(54, 162, 235, 1)',
                'rgba(255, 205, 86, 1)',
                'rgba(75, 192, 192, 1)',
                'rgba(153, 102, 255, 1)',
                'rgba(255, 159, 64, 1)'
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom',
            },
            title: {
                display: false
            }
        }
    }
});
</script>
@endsection