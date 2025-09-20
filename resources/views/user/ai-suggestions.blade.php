@extends('layouts.dashboard')

@section('title', 'AI 減碳建議')

@section('sidebar-title', '個人功能')

@section('sidebar-menu')
    <li class="nav-item">
        <a class="nav-link" href="{{ route('user.dashboard') }}">
            <i class="fas fa-home me-2"></i>個人儀表板
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('user.charts') }}">
            <i class="fas fa-chart-bar me-2"></i>每月/每日通勤碳排統計圖表
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('user.map') }}">
            <i class="fas fa-map-marked-alt me-2"></i>地圖顯示通勤路線
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="#">
            <i class="fas fa-chart-pie me-2"></i>交通工具使用分布
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('user.attendance') }}">
            <i class="fas fa-clock me-2"></i>打卡紀錄
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('user.realtime') }}">
            <i class="fas fa-sync-alt me-2"></i>即時儀表板
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link active" href="{{ route('user.ai-suggestions') }}">
            <i class="fas fa-lightbulb me-2"></i>AI 減碳建議
        </a>
    </li>
@endsection

@section('content')
<div class="row mb-4">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1><i class="fas fa-brain text-primary me-2"></i>AI 減碳建議</h1>
                <p class="text-muted">基於您的通勤資料，AI 為您提供個人化的減碳建議</p>
            </div>
            <div>
                <button class="btn btn-outline-secondary me-2" onclick="reanalyzeTrips()">
                    <i class="fas fa-sync-alt"></i> 重新分析行程
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 日期選擇區域 -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">分析設定</h5>
                <div class="row">
                    <div class="col-md-4">
                        <label for="dateRange" class="form-label">分析期間</label>
                        <select class="form-select" id="dateRange">
                            <option value="7">最近 7 天</option>
                            <option value="14">最近 14 天</option>
                            <option value="30" selected>最近 30 天</option>
                            <option value="60">最近 60 天</option>
                            <option value="90">最近 90 天</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="customStartDate" class="form-label">自訂開始日期</label>
                        <input type="date" class="form-control" id="customStartDate">
                    </div>
                    <div class="col-md-4">
                        <label for="customEndDate" class="form-label">自訂結束日期</label>
                        <input type="date" class="form-control" id="customEndDate">
                    </div>
                </div>
                <div class="mt-3">
                    <button class="btn btn-primary" onclick="generateAISuggestions()">
                        <i class="fas fa-magic"></i> 生成 AI 建議
                    </button>
                    <button class="btn btn-info ms-2" onclick="analyzeCustomRange()">
                        <i class="fas fa-search"></i> 分析自訂範圍
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 載入狀態 -->
<div id="loadingState" class="row mb-4" style="display: none;">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body text-center py-5">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">分析中...</span>
                </div>
                <h5>AI 正在分析您的通勤資料</h5>
                <p class="text-muted">這可能需要幾秒鐘時間，請稍候...</p>
            </div>
        </div>
    </div>
</div>

<!-- AI 建議結果 -->
<div id="aiSuggestions" style="display: none;">
    <!-- 資料摘要 -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>通勤資料摘要</h5>
                </div>
                <div class="card-body">
                    <div class="row" id="dataSummary">
                        <!-- 動態載入摘要資料 -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 視覺化圖表 -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-pie me-2"></i>交通工具使用分布
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="transportationChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-bar me-2"></i>碳排放分布
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="carbonChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- AI 分析結果 -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-robot me-2"></i>AI 分析結果</h5>
                </div>
                <div class="card-body">
                    <div id="aiAnalysis">
                        <!-- 動態載入 AI 分析內容 -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- AI 建議列表 -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>個人化減碳建議</h5>
                </div>
                <div class="card-body">
                    <div id="suggestions">
                        <!-- 動態載入建議內容 -->
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>替代方案</h5>
                </div>
                <div class="card-body">
                    <div id="alternativeRoutes">
                        <!-- 動態載入替代方案 -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 詳細數據表格 -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-table me-2"></i>詳細分析數據
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="detailTable">
                            <thead>
                                <tr>
                                    <th>交通工具</th>
                                    <th>距離 (公里)</th>
                                    <th>時間 (分鐘)</th>
                                    <th>使用比例 (%)</th>
                                    <th>碳排放 (kg CO2)</th>
                                </tr>
                            </thead>
                            <tbody id="detailTableBody">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 錯誤處理 -->
<div id="errorAlert" class="alert alert-danger" style="display: none;" role="alert">
    <h4 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> 分析失敗</h4>
    <p id="errorMessage"></p>
    <hr>
    <p class="mb-0">請稍後再試，或聯繫技術支援。</p>
</div>

<!-- 基礎減碳建議 -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>基礎減碳建議</h5>
            </div>
            <div class="card-body">
                <div id="fallbackContent">
                    <!-- 後備建議內容 -->
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let currentAnalysisData = null;
let transportationChart = null;
let carbonChart = null;

$(document).ready(function() {
    // 設定預設日期
    setDefaultDates();
    
    // 載入基礎建議
    showFallbackSuggestions();
});

// 設定預設日期
function setDefaultDates() {
    const today = new Date();
    const lastWeek = new Date(today.getTime() - 6 * 24 * 60 * 60 * 1000);
    
    $('#customStartDate').val(formatDate(lastWeek));
    $('#customEndDate').val(formatDate(today));
    
    // 設定最大日期為今天
    $('#customStartDate, #customEndDate').attr('max', formatDate(today));
}

function formatDate(date) {
    return date.getFullYear() + '-' + 
           String(date.getMonth() + 1).padStart(2, '0') + '-' + 
           String(date.getDate()).padStart(2, '0');
}

// 生成 AI 建議
async function generateAISuggestions() {
    const dateRange = document.getElementById('dateRange').value;
    
    showLoading();
    hideError();
    hideResults();

    try {
        const response = await fetch('/user/ai-suggestions/generate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                date_range: parseInt(dateRange)
            })
        });

        const data = await response.json();
        
        if (data.success) {
            displayAISuggestions(data);
        } else {
            if (data.fallback) {
                showFallbackSuggestions(data.fallback);
            } else {
                showError(data.message || '無法生成 AI 建議');
            }
        }
    } catch (error) {
        console.error('Error:', error);
        showError('請求失敗，請檢查網路連線');
    } finally {
        hideLoading();
    }
}

// 分析自訂日期範圍
async function analyzeCustomRange() {
    const startDate = document.getElementById('customStartDate').value;
    const endDate = document.getElementById('customEndDate').value;

    if (!startDate || !endDate) {
        showError('請選擇開始和結束日期');
        return;
    }

    if (new Date(startDate) >= new Date(endDate)) {
        showError('結束日期必須晚於開始日期');
        return;
    }

    showLoading();
    hideError();

    try {
        const response = await fetch('/user/ai-suggestions/analyze-gps', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                start_date: startDate,
                end_date: endDate
            })
        });

        const data = await response.json();
        
        if (data.success) {
            displayAISuggestions(data);
        } else {
            showError(data.message || '分析失敗');
        }
    } catch (error) {
        console.error('Error:', error);
        showError('分析請求失敗');
    } finally {
        hideLoading();
    }
}

// 重新分析行程
async function reanalyzeTrips() {
    showLoading();
    hideError();

    try {
        const response = await fetch('/user/ai-suggestions/reanalyze', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });

        const data = await response.json();
        
        if (data.success) {
            displayAISuggestions(data);
            alert('重新分析完成！');
        } else {
            showError(data.message || '重新分析失敗');
        }
    } catch (error) {
        console.error('Error:', error);
        showError('重新分析請求失敗');
    } finally {
        hideLoading();
    }
}

function showLoading() {
    document.getElementById('loadingState').style.display = 'block';
}

function hideLoading() {
    document.getElementById('loadingState').style.display = 'none';
}

function hideResults() {
    document.getElementById('aiSuggestions').style.display = 'none';
}

function hideError() {
    document.getElementById('errorAlert').style.display = 'none';
}

function showError(message) {
    document.getElementById('errorMessage').textContent = message;
    document.getElementById('errorAlert').style.display = 'block';
    hideResults();
}

// 顯示 AI 建議結果
function displayAISuggestions(data) {
    if (!data.data || !data.data.analysis) {
        showError('收到的資料格式不正確');
        return;
    }

    const analysis = data.data.analysis;
    currentAnalysisData = analysis;

    // 顯示摘要
    displayDataSummary(analysis);
    
    // 顯示圖表
    updateTransportationChart(analysis.transportation_breakdown);
    updateCarbonChart(analysis.carbon_emission.breakdown);
    
    // 顯示 AI 分析
    displayAIAnalysis(analysis);
    
    // 顯示建議
    displaySuggestions(analysis.recommendations);
    
    // 顯示替代方案
    displayAlternativeRoutes(analysis.alternative_routes);
    
    // 顯示詳細表格
    updateDetailTable(analysis.transportation_breakdown, analysis.carbon_emission.breakdown);
    
    // 顯示結果區域
    document.getElementById('aiSuggestions').style.display = 'block';
}

// 顯示資料摘要
function displayDataSummary(analysis) {
    const summaryHtml = `
        <div class="col-md-3">
            <div class="text-center">
                <h4 class="text-primary">${analysis.total_distance} km</h4>
                <p class="mb-0">總移動距離</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="text-center">
                <h4 class="text-info">${analysis.total_time} 分鐘</h4>
                <p class="mb-0">總移動時間</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="text-center">
                <h4 class="text-warning">${analysis.carbon_emission.total_kg_co2} kg CO2</h4>
                <p class="mb-0">總碳排放量</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="text-center">
                <h4 class="text-success">${getDominantTransport(analysis.transportation_breakdown)}</h4>
                <p class="mb-0">主要交通工具</p>
            </div>
        </div>
    `;
    document.getElementById('dataSummary').innerHTML = summaryHtml;
}

// 獲取主要交通工具
function getDominantTransport(breakdown) {
    let maxPercentage = 0;
    let dominantTransport = '未知';
    
    const transportMap = {
        'walking': '步行',
        'bicycle': '腳踏車', 
        'motorcycle': '機車',
        'car': '汽車',
        'bus': '公車'
    };
    
    Object.keys(breakdown).forEach(transport => {
        if (breakdown[transport].percentage > maxPercentage) {
            maxPercentage = breakdown[transport].percentage;
            dominantTransport = transportMap[transport] || transport;
        }
    });
    
    return dominantTransport;
}

// 顯示 AI 分析
function displayAIAnalysis(analysis) {
    const totalCarbon = parseFloat(analysis.carbon_emission.total_kg_co2);
    const totalDistance = parseFloat(analysis.total_distance);
    
    let carbonEfficiency = '';
    if (totalDistance > 0) {
        const efficiency = (totalCarbon / totalDistance).toFixed(3);
        carbonEfficiency = `您的平均碳排放效率為 ${efficiency} kg CO2/km`;
    }
    
    let analysisText = '';
    if (totalCarbon === 0) {
        analysisText = '恭喜！您在分析期間完全使用零碳排放的交通方式（步行或腳踏車）。';
    } else if (totalCarbon < 5) {
        analysisText = '您的碳排放量相對較低，主要使用環保的交通方式。';
    } else if (totalCarbon < 20) {
        analysisText = '您的碳排放量處於中等水平，仍有改善空間。';
    } else {
        analysisText = '您的碳排放量偏高，建議多使用低碳交通方式。';
    }
    
    const analysisHtml = `
        <div class="mb-3">
            <h6><i class="fas fa-chart-line me-2"></i>通勤模式分析</h6>
            <p>${analysisText}</p>
        </div>
        <div class="mb-3">
            <h6><i class="fas fa-leaf me-2"></i>碳足跡評估</h6>
            <p>${carbonEfficiency}</p>
        </div>
    `;
    document.getElementById('aiAnalysis').innerHTML = analysisHtml;
}

// 顯示建議列表
function displaySuggestions(recommendations) {
    if (!recommendations || recommendations.length === 0) {
        document.getElementById('suggestions').innerHTML = '<p class="text-muted">暫無具體建議</p>';
        return;
    }

    const suggestionsHtml = recommendations.map((recommendation, index) => {
        const icons = ['💡', '🌱', '🚴', '🚶', '🚌'];
        const colors = ['primary', 'success', 'info', 'warning', 'secondary'];
        const icon = icons[index % icons.length];
        const color = colors[index % colors.length];
        
        return `
            <div class="alert alert-${color} border-${color} mb-3">
                <div class="d-flex">
                    <div class="me-3" style="font-size: 1.5rem;">${icon}</div>
                    <div class="flex-grow-1">
                        <h6 class="alert-heading">建議 ${index + 1}</h6>
                        <p class="mb-0">${recommendation}</p>
                    </div>
                </div>
            </div>
        `;
    }).join('');
    
    document.getElementById('suggestions').innerHTML = suggestionsHtml;
}

// 顯示替代方案
function displayAlternativeRoutes(routes) {
    if (!routes || routes.length === 0) {
        document.getElementById('alternativeRoutes').innerHTML = `
            <div class="text-center text-muted">
                <i class="fas fa-route fa-3x mb-3 opacity-50"></i>
                <p>目前沒有替代方案建議</p>
            </div>
        `;
        return;
    }

    const routesHtml = routes.map((route, index) => {
        return `
            <div class="card mb-3 border-success">
                <div class="card-body p-3">
                    <h6 class="card-title text-success">
                        <i class="fas fa-route me-2"></i>方案 ${index + 1}
                    </h6>
                    <p class="card-text small mb-2">${route.route}</p>
                    <div class="row">
                        <div class="col-12 mb-1">
                            <small class="text-success">
                                <strong><i class="fas fa-leaf me-1"></i>節省碳排放：</strong>
                                ${route.carbon_saving}
                            </small>
                        </div>
                        <div class="col-12">
                            <small class="text-info">
                                <strong><i class="fas fa-clock me-1"></i>時間差異：</strong>
                                ${route.time_difference}
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
    
    document.getElementById('alternativeRoutes').innerHTML = routesHtml;
}

// 更新交通工具圖表
function updateTransportationChart(breakdown) {
    const ctx = document.getElementById('transportationChart').getContext('2d');
    
    if (transportationChart) {
        transportationChart.destroy();
    }
    
    const labels = ['步行', '腳踏車', '機車', '汽車', '公車'];
    const data = [
        breakdown.walking.percentage,
        breakdown.bicycle.percentage,
        breakdown.motorcycle.percentage,
        breakdown.car.percentage,
        breakdown.bus.percentage
    ];
    
    const colors = ['#28a745', '#17a2b8', '#ffc107', '#dc3545', '#6c757d'];
    
    transportationChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        usePointStyle: true
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ' + context.parsed + '%';
                        }
                    }
                }
            }
        }
    });
}

// 更新碳排放圖表
function updateCarbonChart(breakdown) {
    const ctx = document.getElementById('carbonChart').getContext('2d');
    
    if (carbonChart) {
        carbonChart.destroy();
    }
    
    const labels = ['步行', '腳踏車', '機車', '汽車', '公車'];
    const data = [
        breakdown.walking,
        breakdown.bicycle,
        breakdown.motorcycle,
        breakdown.car,
        breakdown.bus
    ];
    
    const colors = ['#28a745', '#17a2b8', '#ffc107', '#dc3545', '#6c757d'];
    
    carbonChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: '碳排放 (kg CO2)',
                data: data,
                backgroundColor: colors,
                borderColor: colors,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: '碳排放量 (kg CO2)'
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return '碳排放: ' + context.parsed.y + ' kg CO2';
                        }
                    }
                }
            }
        }
    });
}

// 更新詳細表格
function updateDetailTable(transportation, carbon) {
    const tbody = $('#detailTableBody');
    tbody.empty();
    
    const transportTypes = [
        { key: 'walking', name: '🚶 步行', color: 'success' },
        { key: 'bicycle', name: '🚴 腳踏車', color: 'info' },
        { key: 'motorcycle', name: '🏍️ 機車', color: 'warning' },
        { key: 'car', name: '🚗 汽車', color: 'danger' },
        { key: 'bus', name: '🚌 公車', color: 'secondary' }
    ];
    
    transportTypes.forEach(type => {
        const transportData = transportation[type.key];
        const carbonData = carbon[type.key];
        
        const row = `
            <tr>
                <td><strong class="text-${type.color}">${type.name}</strong></td>
                <td>${transportData.distance}</td>
                <td>${transportData.time}</td>
                <td>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar bg-${type.color}" role="progressbar" 
                             style="width: ${Math.max(transportData.percentage, 2)}%" 
                             aria-valuenow="${transportData.percentage}" 
                             aria-valuemin="0" aria-valuemax="100">
                            ${transportData.percentage}%
                        </div>
                    </div>
                </td>
                <td>
                    <span class="badge ${carbonData > 0 ? 'bg-warning' : 'bg-success'} text-dark">
                        ${carbonData}
                    </span>
                </td>
            </tr>
        `;
        tbody.append(row);
    });
}

// 顯示後備建議
function showFallbackSuggestions(fallbackData = null) {
    const suggestions = fallbackData ? fallbackData.analysis.recommendations : [
        '考慮使用步行或腳踏車進行短距離移動，這是最環保的交通方式',
        '搭乘大眾運輸工具（公車、捷運）可以有效降低個人碳排放',
        '規劃行程時嘗試合併多個目的地，減少不必要的往返',
        '與同事或朋友共乘，分攤交通工具的碳排放成本',
        '選擇居住地點時考慮與工作地點的距離，減少通勤碳排放'
    ];
    
    const fallbackHtml = suggestions.map((suggestion, index) => {
        const icons = ['🚶', '🚌', '📋', '👥', '🏠'];
        const icon = icons[index % icons.length];
        
        return `
            <div class="alert alert-light border-left-primary mb-3">
                <div class="d-flex">
                    <div class="me-3" style="font-size: 1.5rem;">${icon}</div>
                    <div class="flex-grow-1">
                        <p class="mb-0">${suggestion}</p>
                    </div>
                </div>
            </div>
        `;
    }).join('');
    
    document.getElementById('fallbackContent').innerHTML = fallbackHtml;
}
</script>
@endpush

@push('styles')
<style>
.border-left-primary {
    border-left: 4px solid #007bff !important;
}

.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.progress {
    background-color: #e9ecef;
}

.spinner-border {
    width: 3rem;
    height: 3rem;
}

.alert {
    border-radius: 0.5rem;
}

canvas {
    max-height: 300px;
}

.opacity-50 {
    opacity: 0.5;
}
</style>
@endpush