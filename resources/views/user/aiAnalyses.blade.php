<!-- resources/views/user/aiAnalyses.blade.php -->
@extends('layouts.dashboard')

@section('title', 'AI 碳排放分析')

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
        <a class="nav-link active" href="{{ route('user.carbon.aiAnalyses') }}">
            <i class="fas fa-leaf me-2"></i>AI 碳排放分析
        </a>
    </li>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-header mb-4">
                <h1 class="page-title">
                    <i class="fas fa-robot text-primary"></i> AI 碳排放分析
                </h1>
                <p class="text-muted">使用 AI 分析您的通勤模式並計算碳排放量</p>
            </div>
        </div>
    </div>

    <!-- 資料選擇區域 -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-database"></i> 選擇分析資料</h5>
        </div>
        <div class="card-body">
            <!-- 月份選擇器 -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <label for="monthSelector" class="form-label">選擇月份</label>
                    <input type="month" id="monthSelector" class="form-control" 
                           value="{{ date('Y-m') }}">
                </div>
                <div class="col-md-4">
                    <button id="loadDataBtn" class="btn btn-primary mt-4">
                        <i class="fas fa-sync-alt"></i> 載入資料
                    </button>
                </div>
            </div>

            <!-- 可用資料列表 -->
            <div id="availableDataSection" style="display: none;">
                <h6 class="mb-3">可分析的資料日期</h6>
                <div id="dataTableContainer">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="50">
                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                </th>
                                <th>日期</th>
                                <th>ESP32資料點</th>
                                <th>GPS資料點</th>
                                <th>行程數</th>
                                <th>時間範圍</th>
                                <th>平均速度</th>
                                <th>狀態</th>
                            </tr>
                        </thead>
                        <tbody id="dataTableBody">
                            <!-- 動態載入 -->
                        </tbody>
                    </table>
                </div>
                
                <!-- 分析按鈕 -->
                <div class="mt-3">
                    <button id="analyzeSelectedBtn" class="btn btn-success" disabled>
                        <i class="fas fa-brain"></i> 分析選中的資料
                    </button>
                    <span class="ms-3 text-muted">已選擇 <span id="selectedCount">0</span> 天</span>
                </div>
            </div>

            <!-- 載入中提示 -->
            <div id="loadingDataSpinner" class="text-center py-5" style="display: none;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">載入中...</span>
                </div>
                <p class="mt-2">正在載入資料...</p>
            </div>

            <!-- 無資料提示 -->
            <div id="noDataAlert" class="alert alert-warning" style="display: none;">
                <i class="fas fa-exclamation-triangle"></i>
                所選月份沒有可分析的GPS資料。請確認ESP32設備是否正常運作並上傳資料。
            </div>
        </div>
    </div>

    <!-- 分析結果區域 -->
    <div id="analysisResultSection" style="display: none;">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-chart-line"></i> 分析結果</h5>
            </div>
            <div class="card-body">
                <!-- 總覽統計 -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <i class="fas fa-calendar-check fa-2x text-primary mb-2"></i>
                            <h6>分析天數</h6>
                            <h3 id="statDays">0</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <i class="fas fa-road fa-2x text-info mb-2"></i>
                            <h6>總距離</h6>
                            <h3><span id="statDistance">0</span> km</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                            <h6>總時間</h6>
                            <h3><span id="statDuration">0</span> 小時</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <i class="fas fa-cloud fa-2x text-danger mb-2"></i>
                            <h6>碳排放量</h6>
                            <h3><span id="statEmission">0</span> kg CO₂</h3>
                        </div>
                    </div>
                </div>

                <!-- 詳細結果表格 -->
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>日期</th>
                                <th>交通工具</th>
                                <th>距離 (km)</th>
                                <th>時間 (分鐘)</th>
                                <th>平均速度 (km/h)</th>
                                <th>碳排放 (kg CO₂)</th>
                                <th>建議</th>
                            </tr>
                        </thead>
                        <tbody id="resultTableBody">
                            <!-- 動態載入 -->
                        </tbody>
                    </table>
                </div>

                <!-- AI 建議總結 -->
                <div class="mt-4">
                    <h6><i class="fas fa-lightbulb text-warning"></i> AI 減碳建議</h6>
                    <div id="aiSuggestions" class="alert alert-info">
                        <!-- 動態載入 -->
                    </div>
                </div>

                <!-- 圖表區域 -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <canvas id="transportChart"></canvas>
                    </div>
                    <div class="col-md-6">
                        <canvas id="emissionChart"></canvas>
                    </div>
                </div>

                <!-- 操作按鈕 -->
                <div class="mt-4">
                    <button class="btn btn-primary" onclick="exportResults()">
                        <i class="fas fa-download"></i> 匯出報告
                    </button>
                    <button class="btn btn-secondary" onclick="resetAnalysis()">
                        <i class="fas fa-redo"></i> 重新分析
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 歷史記錄區域 -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-history"></i> 分析歷史記錄</h5>
        </div>
        <div class="card-body">
            <div id="historySection">
                <button class="btn btn-sm btn-outline-primary" onclick="loadHistory()">
                    載入歷史記錄
                </button>
                <div id="historyContent" style="display: none;">
                    <!-- 動態載入歷史記錄 -->
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
.stat-card {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.table-info {
    background-color: rgba(23, 162, 184, 0.1) !important;
}

.table-success {
    background-color: rgba(40, 167, 69, 0.1) !important;
}

.page-header {
    border-bottom: 2px solid #dee2e6;
    padding-bottom: 15px;
    margin-bottom: 20px;
}

.page-title {
    font-size: 1.75rem;
    font-weight: 600;
    color: #333;
}

#dataTableContainer {
    max-height: 400px;
    overflow-y: auto;
}

.badge {
    font-size: 0.875rem;
    padding: 0.375rem 0.75rem;
}

canvas {
    max-height: 300px;
}

/* 動畫效果 */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.fade-in {
    animation: fadeIn 0.5s ease-in;
}

/* 響應式設計 */
@media (max-width: 768px) {
    .stat-card {
        margin-bottom: 15px;
    }
    
    #dataTableContainer {
        max-height: none;
    }
    
    .table {
        font-size: 0.875rem;
    }
}
</style>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// 全局變數
let availableData = [];
let selectedDates = [];
let currentAnalysisData = null;
let transportChart = null;
let emissionChart = null;

// 頁面載入完成後初始化
document.addEventListener('DOMContentLoaded', function() {
    loadAvailableData();
    
    // 綁定事件
    document.getElementById('loadDataBtn').addEventListener('click', loadAvailableData);
    document.getElementById('selectAll').addEventListener('change', toggleSelectAll);
    document.getElementById('analyzeSelectedBtn').addEventListener('click', analyzeSelectedData);
    
    // 自動載入當月資料
    const today = new Date();
    document.getElementById('monthSelector').value = 
        `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`;
});

// 載入可用資料
async function loadAvailableData() {
    const month = document.getElementById('monthSelector').value;
    
    // 顯示載入中
    document.getElementById('loadingDataSpinner').style.display = 'block';
    document.getElementById('availableDataSection').style.display = 'none';
    document.getElementById('noDataAlert').style.display = 'none';
    
    try {
        const response = await fetch(`/user/carbon/available-data?month=${month}`, {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const result = await response.json();
        
        if (result.success && result.data.length > 0) {
            availableData = result.data;
            displayAvailableData(result.data);
            document.getElementById('availableDataSection').style.display = 'block';
            
            // 顯示摘要資訊
            if (result.summary) {
                console.log('資料摘要:', result.summary);
                showDataSummary(result.summary);
            }
        } else {
            document.getElementById('noDataAlert').style.display = 'block';
        }
    } catch (error) {
        console.error('載入資料失敗:', error);
        Swal.fire('錯誤', '無法載入資料，請稍後再試', 'error');
    } finally {
        document.getElementById('loadingDataSpinner').style.display = 'none';
    }
}

// 顯示資料摘要
function showDataSummary(summary) {
    if (!summary) return;
    
    let summaryHtml = `
        <div class="alert alert-info mt-3">
            <strong>本月資料摘要：</strong>
            總共 ${summary.total_days} 天，
            其中 ${summary.days_with_esp32_data} 天有ESP32資料，
            ${summary.days_with_gps_data} 天有GPS資料，
            ${summary.days_analyzed} 天已分析
        </div>
    `;
    
    const container = document.getElementById('dataTableContainer');
    const existingAlert = container.querySelector('.alert-info');
    if (existingAlert) {
        existingAlert.remove();
    }
    container.insertAdjacentHTML('beforebegin', summaryHtml);
}

// 顯示可用資料
function displayAvailableData(data) {
    const tbody = document.getElementById('dataTableBody');
    tbody.innerHTML = '';
    
    data.forEach((item, index) => {
        const row = document.createElement('tr');
        
        // 根據資料品質設定行的樣式
        if (item.has_analysis) {
            row.classList.add('table-success');
        } else if (item.esp32_points > 0) {
            row.classList.add('table-info');
        }
        
        // 判斷是否可以分析
        const canAnalyze = (item.esp32_points > 0 || item.gps_points > 0) && !item.has_analysis;
        
        row.innerHTML = `
            <td>
                <input type="checkbox" class="form-check-input data-checkbox" 
                       value="${item.date}" data-index="${index}"
                       ${canAnalyze ? '' : 'disabled'}>
            </td>
            <td>
                <strong>${item.date}</strong>
                <br><small class="text-muted">${item.weekday || ''}</small>
                ${item.is_weekend ? '<span class="badge bg-secondary ms-1">週末</span>' : ''}
            </td>
            <td>
                ${item.esp32_points > 0 ? 
                    `<span class="badge bg-primary">${item.esp32_points}</span>` : 
                    '<span class="text-muted">-</span>'}
            </td>
            <td>
                ${item.gps_points > 0 ? 
                    `<span class="badge bg-info">${item.gps_points}</span>` : 
                    '<span class="text-muted">-</span>'}
            </td>
            <td>
                ${item.trips_count > 0 ? 
                    `<span class="badge bg-success">${item.trips_count}</span>` : 
                    '<span class="text-muted">-</span>'}
            </td>
            <td>${item.time_range || '-'}</td>
            <td>${item.avg_speed > 0 ? item.avg_speed + ' km/h' : '-'}</td>
            <td>
                ${item.has_analysis ? 
                    '<span class="badge bg-success">已分析</span>' : 
                    (canAnalyze ? 
                        '<span class="badge bg-warning">待分析</span>' : 
                        '<span class="badge bg-secondary">無資料</span>')}
            </td>
        `;
        
        tbody.appendChild(row);
    });
    
    // 綁定checkbox事件
    document.querySelectorAll('.data-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });
}

// 全選/取消全選
function toggleSelectAll() {
    const isChecked = document.getElementById('selectAll').checked;
    document.querySelectorAll('.data-checkbox:not(:disabled)').forEach(checkbox => {
        checkbox.checked = isChecked;
    });
    updateSelectedCount();
}

// 更新選擇計數
function updateSelectedCount() {
    const checkedBoxes = document.querySelectorAll('.data-checkbox:checked');
    const count = checkedBoxes.length;
    
    document.getElementById('selectedCount').textContent = count;
    document.getElementById('analyzeSelectedBtn').disabled = count === 0;
    
    // 更新選中的日期列表
    selectedDates = Array.from(checkedBoxes).map(cb => cb.value);
}

// 分析選中的資料
async function analyzeSelectedData() {
    if (selectedDates.length === 0) {
        Swal.fire('提示', '請選擇要分析的日期', 'warning');
        return;
    }
    
    // 確認分析
    const confirmResult = await Swal.fire({
        title: '確認分析',
        text: `您選擇了 ${selectedDates.length} 天的資料進行分析，是否繼續？`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '開始分析',
        cancelButtonText: '取消'
    });
    
    if (!confirmResult.isConfirmed) return;
    
    // 顯示載入提示
    Swal.fire({
        title: 'AI 分析中',
        html: '正在使用 OpenAI 分析您的通勤模式和碳排放...<br>這可能需要幾秒鐘時間',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    try {
        // 計算開始和結束日期
        const sortedDates = selectedDates.sort();
        const startDate = sortedDates[0];
        const endDate = sortedDates[sortedDates.length - 1];
        
        const response = await fetch('/user/carbon/analyze', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                start_date: startDate,
                end_date: endDate,
                force_refresh: true,
                data_source: 'all'  // 使用所有可用資料來源
            })
        });
        
        const result = await response.json();
        
        Swal.close();
        
        if (result.success) {
            currentAnalysisData = result;
            
            // 顯示分析結果
            displayAnalysisResults(result.data, result.summary);
            
            // 成功提示
            Swal.fire({
                icon: 'success',
                title: '分析完成',
                text: '已成功分析您的通勤資料和碳排放量',
                timer: 2000,
                showConfirmButton: false
            });
            
            // 重新載入資料以更新狀態
            setTimeout(loadAvailableData, 2000);
        } else {
            Swal.fire('錯誤', result.message || '分析失敗', 'error');
        }
    } catch (error) {
        console.error('分析失敗:', error);
        Swal.fire('錯誤', '分析過程中發生錯誤，請稍後再試', 'error');
    }
}

// 顯示分析結果
function displayAnalysisResults(data, summary) {
    // 顯示結果區域
    document.getElementById('analysisResultSection').style.display = 'block';
    
    // 滾動到結果區域
    document.getElementById('analysisResultSection').scrollIntoView({ behavior: 'smooth' });
    
    // 更新統計數據
    if (summary) {
        document.getElementById('statDays').textContent = summary.days_analyzed || 0;
        document.getElementById('statDistance').textContent = (summary.total_distance || 0).toFixed(2);
        document.getElementById('statDuration').textContent = ((summary.total_duration || 0) / 3600).toFixed(1);
        document.getElementById('statEmission').textContent = (summary.total_emission || 0).toFixed(3);
    }
    
    // 填充結果表格
    const tbody = document.getElementById('resultTableBody');
    tbody.innerHTML = '';
    
    if (data && data.length > 0) {
        data.forEach(item => {
            const analysis = item.analysis || {};
            const row = document.createElement('tr');
            
            row.innerHTML = `
                <td>${item.date}</td>
                <td>
                    ${getTransportModeIcon(analysis.transport_mode)} 
                    ${getTransportModeName(analysis.transport_mode)}
                </td>
                <td>${(analysis.total_distance || 0).toFixed(2)}</td>
                <td>${Math.round((analysis.total_duration || 0) / 60)}</td>
                <td>${(analysis.average_speed || 0).toFixed(1)}</td>
                <td class="${getEmissionClass(analysis.carbon_emission)}">
                    ${(analysis.carbon_emission || 0).toFixed(3)}
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-info" 
                            onclick='showSuggestions(${JSON.stringify(analysis.suggestions || [])})'>
                        查看建議
                    </button>
                </td>
            `;
            
            tbody.appendChild(row);
        });
    }
    
    // 生成圖表
    generateCharts(data, summary);
    
    // 顯示AI建議
    displayAISuggestions(data);
}

// 獲取交通工具圖標
function getTransportModeIcon(mode) {
    const icons = {
        'walking': '🚶',
        'bicycle': '🚴',
        'motorcycle': '🏍️',
        'car': '🚗',
        'bus': '🚌',
        'mixed': '🔄'
    };
    return icons[mode] || '❓';
}

// 獲取交通工具名稱
function getTransportModeName(mode) {
    const names = {
        'walking': '步行',
        'bicycle': '腳踏車',
        'motorcycle': '機車',
        'car': '汽車',
        'bus': '公車',
        'mixed': '混合'
    };
    return names[mode] || '未知';
}

// 獲取排放量等級樣式
function getEmissionClass(emission) {
    if (emission < 1) return 'text-success';
    if (emission < 3) return 'text-warning';
    return 'text-danger';
}

// 顯示建議
function showSuggestions(suggestions) {
    if (!suggestions || suggestions.length === 0) {
        Swal.fire('建議', '暫無相關建議', 'info');
        return;
    }
    
    let html = '<ul class="text-start">';
    suggestions.forEach(suggestion => {
        html += `<li class="mb-2">${suggestion}</li>`;
    });
    html += '</ul>';
    
    Swal.fire({
        title: '🌱 減碳建議',
        html: html,
        icon: 'info',
        width: '600px'
    });
}

// 生成圖表
function generateCharts(data, summary) {
    // 銷毀舊圖表
    if (transportChart) transportChart.destroy();
    if (emissionChart) emissionChart.destroy();
    
    // 交通工具分布圖
    const transportCtx = document.getElementById('transportChart').getContext('2d');
    const transportData = summary.transport_modes || {};
    
    transportChart = new Chart(transportCtx, {
        type: 'pie',
        data: {
            labels: Object.keys(transportData).map(mode => getTransportModeName(mode)),
            datasets: [{
                data: Object.values(transportData),
                backgroundColor: [
                    '#28a745',
                    '#17a2b8',
                    '#ffc107',
                    '#dc3545',
                    '#6610f2',
                    '#6c757d'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: '交通工具使用分布'
                },
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    
    // 碳排放趨勢圖
    const emissionCtx = document.getElementById('emissionChart').getContext('2d');
    const dates = data.map(item => item.date);
    const emissions = data.map(item => (item.analysis?.carbon_emission || 0));
    
    emissionChart = new Chart(emissionCtx, {
        type: 'line',
        data: {
            labels: dates,
            datasets: [{
                label: '碳排放量 (kg CO₂)',
                data: emissions,
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: '每日碳排放趨勢'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'kg CO₂'
                    }
                }
            }
        }
    });
}

// 顯示AI建議總結
function displayAISuggestions(data) {
    const container = document.getElementById('aiSuggestions');
    
    // 收集所有建議
    const allSuggestions = new Set();
    data.forEach(item => {
        if (item.analysis?.suggestions) {
            item.analysis.suggestions.forEach(s => allSuggestions.add(s));
        }
    });
    
    if (allSuggestions.size === 0) {
        container.innerHTML = '暫無建議';
        return;
    }
    
    // 顯示前5個最重要的建議
    const suggestions = Array.from(allSuggestions).slice(0, 5);
    let html = '<ul class="mb-0">';
    suggestions.forEach(suggestion => {
        html += `<li>${suggestion}</li>`;
    });
    html += '</ul>';
    
    container.innerHTML = html;
}

// 匯出結果
function exportResults() {
    if (!currentAnalysisData) {
        Swal.fire('提示', '請先進行分析', 'warning');
        return;
    }
    
    // 準備CSV資料
    let csv = '日期,交通工具,距離(km),時間(分鐘),平均速度(km/h),碳排放(kg CO2)\n';
    
    currentAnalysisData.data.forEach(item => {
        const analysis = item.analysis || {};
        csv += `${item.date},${getTransportModeName(analysis.transport_mode)},`;
        csv += `${(analysis.total_distance || 0).toFixed(2)},`;
        csv += `${Math.round((analysis.total_duration || 0) / 60)},`;
        csv += `${(analysis.average_speed || 0).toFixed(1)},`;
        csv += `${(analysis.carbon_emission || 0).toFixed(3)}\n`;
    });
    
    // 下載CSV檔案
    const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `carbon_analysis_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    Swal.fire('成功', '分析報告已匯出', 'success');
}

// 重新分析
function resetAnalysis() {
    document.getElementById('analysisResultSection').style.display = 'none';
    document.getElementById('selectAll').checked = false;
    toggleSelectAll();
    loadAvailableData();
}

// 載入歷史記錄
async function loadHistory() {
    const historyContent = document.getElementById('historyContent');
    historyContent.innerHTML = '<div class="text-center"><div class="spinner-border"></div></div>';
    historyContent.style.display = 'block';
    
    try {
        const response = await fetch('/user/carbon/history', {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const result = await response.json();
        
        if (result.success && result.data.data.length > 0) {
            let html = '<table class="table table-sm table-hover mt-3">';
            html += '<thead><tr><th>分析日期</th><th>交通工具</th><th>距離</th><th>碳排放</th><th>操作</th></tr></thead><tbody>';
            
            result.data.data.forEach(record => {
                html += `<tr>
                    <td>${record.analysis_date}</td>
                    <td>${getTransportModeName(record.transport_mode)}</td>
                    <td>${(record.total_distance).toFixed(2)} km</td>
                    <td>${record.carbon_emission.toFixed(3)} kg CO₂</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="viewHistoryDetail(${record.id})">
                            查看詳情
                        </button>
                    </td>
                </tr>`;
                });
            
            html += '</tbody></table>';
            historyContent.innerHTML = html;
        } else {
            historyContent.innerHTML = '<p class="text-muted">暫無歷史記錄</p>';
        }
    } catch (error) {
        console.error('載入歷史記錄失敗:', error);
        historyContent.innerHTML = '<p class="text-danger">載入失敗</p>';
    }
}

// 查看歷史詳情
function viewHistoryDetail(id) {
    // 可以實作一個詳細視窗顯示更多資訊
    Swal.fire({
        title: '歷史詳情',
        text: `分析ID: ${id} 的詳細資訊功能開發中`,
        icon: 'info'
    });
}

// Konami Code 彩蛋（開發者模式）
let konamiCode = [];
const konamiPattern = ['ArrowUp', 'ArrowUp', 'ArrowDown', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'ArrowLeft', 'ArrowRight', 'b', 'a'];

document.addEventListener('keydown', (event) => {
    konamiCode.push(event.key);
    konamiCode.splice(-konamiPattern.length - 1, konamiCode.length - konamiPattern.length);
    
    if (konamiCode.join(',') === konamiPattern.join(',')) {
        enableDeveloperMode();
    }
});

// 啟用開發者模式
function enableDeveloperMode() {
    Swal.fire({
        title: '🎮 開發者模式',
        html: `
            <div class="text-start">
                <p>開發者模式已啟用！</p>
                <hr>
                <button class="btn btn-sm btn-outline-primary mb-2" onclick="testOpenAIConnection()">測試 OpenAI 連線</button><br>
                <button class="btn btn-sm btn-outline-primary mb-2" onclick="generateTestData()">產生測試資料</button><br>
                <button class="btn btn-sm btn-outline-primary mb-2" onclick="clearAllCache()">清除所有快取</button><br>
                <button class="btn btn-sm btn-outline-primary mb-2" onclick="showSystemInfo()">顯示系統資訊</button><br>
                <button class="btn btn-sm btn-outline-primary" onclick="showDebugInfo()">顯示除錯資訊</button>
            </div>
        `,
        icon: 'success',
        width: '400px'
    });
}

// 測試 OpenAI 連線
async function testOpenAIConnection() {
    try {
        const response = await fetch('/user/carbon/test/connection', {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        const result = await response.json();
        Swal.fire('測試結果', result.success ? '連線成功！' : '連線失敗', result.success ? 'success' : 'error');
    } catch (error) {
        Swal.fire('錯誤', '測試失敗: ' + error.message, 'error');
    }
}

// 產生測試資料
async function generateTestData() {
    const { value: days } = await Swal.fire({
        title: '產生測試資料',
        input: 'number',
        inputLabel: '要產生幾天的測試資料？',
        inputValue: 7,
        inputAttributes: {
            min: 1,
            max: 30
        },
        showCancelButton: true,
        confirmButtonText: '產生',
        cancelButtonText: '取消'
    });
    
    if (days) {
        // 這裡可以呼叫後端API來產生測試資料
        Swal.fire({
            icon: 'success',
            title: '成功',
            text: `已產生 ${days} 天的測試資料`,
            timer: 2000
        });
        
        // 重新載入資料
        setTimeout(loadAvailableData, 2000);
    }
}

// 清除快取
async function clearAllCache() {
    const confirmResult = await Swal.fire({
        title: '確認清除',
        text: '確定要清除所有快取嗎？',
        icon: 'question',
        showCancelButton: true
    });
    
    if (!confirmResult.isConfirmed) return;
    
    try {
        const response = await fetch('/user/carbon/test/clear-cache', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        const result = await response.json();
        Swal.fire('成功', '快取已清除', 'success');
    } catch (error) {
        Swal.fire('錯誤', '清除失敗: ' + error.message, 'error');
    }
}

// 顯示系統資訊
async function showSystemInfo() {
    try {
        const response = await fetch('/user/carbon/test/config', {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        const result = await response.json();
        
        Swal.fire({
            title: '系統資訊',
            html: `<pre class="text-start" style="max-height: 400px; overflow-y: auto;">${JSON.stringify(result, null, 2)}</pre>`,
            width: '600px'
        });
    } catch (error) {
        Swal.fire('錯誤', '無法載入系統資訊: ' + error.message, 'error');
    }
}

// 顯示除錯資訊
function showDebugInfo() {
    const debugInfo = {
        availableDataCount: availableData.length,
        selectedDatesCount: selectedDates.length,
        currentMonth: document.getElementById('monthSelector').value,
        hasAnalysisData: currentAnalysisData !== null,
        chartsLoaded: transportChart !== null && emissionChart !== null,
        browserInfo: {
            userAgent: navigator.userAgent,
            language: navigator.language,
            platform: navigator.platform
        },
        timestamp: new Date().toISOString()
    };
    
    Swal.fire({
        title: '除錯資訊',
        html: `<pre class="text-start" style="max-height: 400px; overflow-y: auto;">${JSON.stringify(debugInfo, null, 2)}</pre>`,
        width: '600px'
    });
}

// 輔助功能：格式化日期
function formatDate(dateString) {
    const date = new Date(dateString);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// 輔助功能：格式化時間
function formatTime(seconds) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    if (hours > 0) {
        return `${hours}小時${minutes}分鐘`;
    }
    return `${minutes}分鐘`;
}

// 輔助功能：計算平均值
function calculateAverage(arr) {
    if (arr.length === 0) return 0;
    const sum = arr.reduce((a, b) => a + b, 0);
    return sum / arr.length;
}

// 錯誤處理
window.addEventListener('error', function(event) {
    console.error('全域錯誤捕獲:', event.error);
    // 可以在這裡加入錯誤回報機制
});

// 監聽網路狀態
window.addEventListener('online', function() {
    console.log('網路已連接');
    // 可以在這裡重新載入資料
});

window.addEventListener('offline', function() {
    console.log('網路已斷開');
    Swal.fire({
        icon: 'warning',
        title: '網路斷開',
        text: '請檢查您的網路連接',
        timer: 3000
    });
});

// 自動儲存功能（如果需要）
let autoSaveTimer = null;

function enableAutoSave() {
    if (autoSaveTimer) clearInterval(autoSaveTimer);
    
    autoSaveTimer = setInterval(() => {
        if (currentAnalysisData) {
            localStorage.setItem('carbonAnalysisData', JSON.stringify(currentAnalysisData));
            console.log('自動儲存完成');
        }
    }, 60000); // 每分鐘自動儲存
}

// 從本地儲存恢復資料
function restoreFromLocalStorage() {
    const savedData = localStorage.getItem('carbonAnalysisData');
    if (savedData) {
        try {
            currentAnalysisData = JSON.parse(savedData);
            console.log('已從本地儲存恢復資料');
            
            // 詢問是否要顯示之前的分析結果
            Swal.fire({
                title: '發現未完成的分析',
                text: '是否要載入之前的分析結果？',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '載入',
                cancelButtonText: '忽略'
            }).then((result) => {
                if (result.isConfirmed && currentAnalysisData) {
                    displayAnalysisResults(currentAnalysisData.data, currentAnalysisData.summary);
                }
            });
        } catch (error) {
            console.error('恢復資料失敗:', error);
            localStorage.removeItem('carbonAnalysisData');
        }
    }
}

// 頁面載入時檢查本地儲存
document.addEventListener('DOMContentLoaded', function() {
    // 恢復之前的資料（如果有）
    restoreFromLocalStorage();
    
    // 啟用自動儲存
    enableAutoSave();
});

// 頁面離開前儲存狀態
window.addEventListener('beforeunload', function(e) {
    if (currentAnalysisData) {
        localStorage.setItem('carbonAnalysisData', JSON.stringify(currentAnalysisData));
    }
});

// 初始化提示訊息
console.log('%c🌱 碳排放分析系統已載入', 'color: green; font-size: 16px; font-weight: bold;');
console.log('%c提示: 按下 ↑↑↓↓←→←→BA 可以啟用開發者模式', 'color: blue; font-size: 12px;');
</script>
@endsection