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
    console.log('頁面載入完成,初始化中...');
    
    // 自動載入當月資料
    const today = new Date();
    const monthInput = document.getElementById('monthSelector');
    if (monthInput) {
        monthInput.value = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`;
    }
    
    // 綁定事件
    const loadBtn = document.getElementById('loadDataBtn');
    if (loadBtn) {
        loadBtn.addEventListener('click', loadAvailableData);
        console.log('載入按鈕事件已綁定');
    }
    
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', toggleSelectAll);
        console.log('全選按鈕事件已綁定');
    }
    
    const analyzeBtn = document.getElementById('analyzeSelectedBtn');
    if (analyzeBtn) {
        analyzeBtn.addEventListener('click', analyzeSelectedData);
        console.log('分析按鈕事件已綁定');
    }
    
    // 自動載入當月資料
    loadAvailableData();
});

// 載入可用資料
async function loadAvailableData() {
    const month = document.getElementById('monthSelector').value;
    console.log('載入資料,月份:', month);
    
    // 顯示載入中
    document.getElementById('loadingDataSpinner').style.display = 'block';
    document.getElementById('availableDataSection').style.display = 'none';
    document.getElementById('noDataAlert').style.display = 'none';
    
    try {
        const response = await fetch(`/user/carbon/available-data?month=${month}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        console.log('API 回應狀態:', response.status);
        const result = await response.json();
        console.log('API 回應資料:', result);
        
        document.getElementById('loadingDataSpinner').style.display = 'none';
        
        if (result.success && result.data && result.data.length > 0) {
            availableData = result.data;
            displayAvailableData(result.data);
            document.getElementById('availableDataSection').style.display = 'block';
            
            Swal.fire({
                icon: 'success',
                title: '載入成功',
                text: `找到 ${result.data.length} 天的資料`,
                timer: 1500,
                showConfirmButton: false
            });
        } else {
            document.getElementById('noDataAlert').style.display = 'block';
            const alertDiv = document.getElementById('noDataAlert');
            alertDiv.innerHTML = `
                <i class="fas fa-exclamation-triangle"></i>
                ${result.message || '所選月份沒有可分析的GPS資料'}
            `;
        }
    } catch (error) {
        console.error('載入資料錯誤:', error);
        document.getElementById('loadingDataSpinner').style.display = 'none';
        
        Swal.fire({
            icon: 'error',
            title: '載入失敗',
            text: '無法載入資料,請稍後再試'
        });
    }
}

// 顯示可用資料
function displayAvailableData(data) {
    const tbody = document.getElementById('dataTableBody');
    tbody.innerHTML = '';
    
    if (!data || data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center">沒有可用的資料</td></tr>';
        return;
    }
    
    data.forEach(item => {
        const row = document.createElement('tr');
        const statusClass = item.status === 'analyzed' ? 'bg-success' : 'bg-secondary';
        const statusText = item.status === 'analyzed' ? '已分析' : '待分析';
        
        row.innerHTML = `
            <td>
                <input type="checkbox" class="form-check-input date-checkbox" 
                       value="${item.date}" data-date="${item.date}">
            </td>
            <td>${item.date}</td>
            <td class="text-center">${item.esp32_count || item.gps_count || 0}</td>
            <td class="text-center">${item.gps_count || 0}</td>
            <td class="text-center">${item.trips_count || 0}</td>
            <td>${item.time_range || '-'}</td>
            <td>${item.avg_speed || 0} km/h</td>
            <td>
                <span class="badge ${statusClass}">${statusText}</span>
            </td>
        `;
        
        tbody.appendChild(row);
    });
    
    // 綁定 checkbox 事件
    document.querySelectorAll('.date-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });
    
    console.log('資料表格已更新,共', data.length, '行');
}

// 更新選中數量
function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.date-checkbox:checked');
    selectedDates = Array.from(checkboxes).map(cb => cb.value);
    
    document.getElementById('selectedCount').textContent = selectedDates.length;
    
    const analyzeBtn = document.getElementById('analyzeSelectedBtn');
    analyzeBtn.disabled = selectedDates.length === 0;
    
    console.log('已選擇日期:', selectedDates);
}

// 全選/取消全選
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.date-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
    
    updateSelectedCount();
}

// 分析選中的資料
async function analyzeSelectedData() {
    if (selectedDates.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: '請選擇日期',
            text: '請至少選擇一個日期進行分析'
        });
        return;
    }
    
    console.log('開始分析,選中的日期:', selectedDates);
    
    // 顯示單一載入動畫
    Swal.fire({
        title: '分析中...',
        html: `正在分析 ${selectedDates.length} 天的資料`,
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    try {
        const response = await fetch('/user/carbon/analyze', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                dates: selectedDates
            })
        });
        
        console.log('分析 API 回應狀態:', response.status);
        const result = await response.json();
        console.log('分析 API 回應:', result);
        
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: '分析完成!',
                text: `成功分析了 ${selectedDates.length} 天的資料`,
                timer: 2000,
                showConfirmButton: false
            });
            
            // 儲存分析結果
            currentAnalysisData = result;
            
            // 顯示分析結果
            displayAnalysisResults(result.data, result.summary);
            
            // 重新載入資料以更新狀態
            setTimeout(() => {
                loadAvailableData();
            }, 2000);
            
        } else {
            Swal.fire({
                icon: 'error',
                title: '分析失敗',
                text: result.message || '分析過程中發生錯誤'
            });
        }
        
    } catch (error) {
        console.error('分析錯誤:', error);
        Swal.fire({
            icon: 'error',
            title: '分析失敗',
            text: '網路錯誤或伺服器無回應: ' + error.message
        });
    }
}

// 顯示分析結果
function displayAnalysisResults(data, summary) {
    console.log('顯示分析結果:', { data, summary });
    
    // 顯示結果區域
    const resultSection = document.getElementById('analysisResultSection');
    if (resultSection) {
        resultSection.style.display = 'block';
        resultSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    
    // 更新統計卡片 - 修正數據提取邏輯
    if (summary) {
        // 總距離
        const totalDistanceEl = document.getElementById('totalDistance');
        if (totalDistanceEl) {
            const distance = summary.total_distance || 0;
            totalDistanceEl.textContent = distance.toFixed(2) + ' km';
            console.log('總距離:', distance);
        }
        
        // 碳排放量
        const totalEmissionEl = document.getElementById('totalEmission');
        if (totalEmissionEl) {
            const emission = summary.total_emission || 0;
            totalEmissionEl.textContent = emission.toFixed(3) + ' kg CO₂';
            console.log('碳排放量:', emission);
        }
        
        // 計算總時間 (分鐘)
        const totalTimeEl = document.getElementById('totalTime');
        if (totalTimeEl) {
            let totalMinutes = 0;
            
            // 從 summary 獲取
            if (summary.total_duration) {
                totalMinutes = Math.round(summary.total_duration / 60);
            } else {
                // 從每日數據計算
                data.forEach(item => {
                    if (item.success && item.analysis) {
                        const duration = item.analysis.total_duration || 0;
                        totalMinutes += Math.round(duration / 60);
                    }
                });
            }
            
            totalTimeEl.textContent = totalMinutes + ' 分鐘';
            console.log('總時間:', totalMinutes, '分鐘');
        }
    }
    
    // 顯示每日詳細結果
    displayDailyResults(data);
    
    // 繪製圖表
    if (summary && summary.transport_modes) {
        drawCharts(data, summary);
    }
    
    // 顯示 AI 建議
    if (summary && summary.suggestions) {
        displaySuggestions(summary.suggestions);
    }
}

// 顯示每日結果
function displayDailyResults(data) {
    const container = document.getElementById('dailyResultsContainer');
    if (!container) {
        console.warn('找不到 dailyResultsContainer 元素');
        return;
    }
    
    container.innerHTML = '';
    
    console.log('顯示每日結果,數據筆數:', data.length);
    
    let displayedCount = 0;
    
    data.forEach((item, index) => {
        console.log(`處理第 ${index + 1} 筆資料:`, item);
        
        if (!item.success) {
            console.warn(`第 ${index + 1} 筆資料分析失敗:`, item.message);
            return;
        }
        
        if (!item.analysis) {
            console.warn(`第 ${index + 1} 筆資料沒有 analysis 物件`);
            return;
        }
        
        const analysis = item.analysis;
        displayedCount++;
        
        const distance = (analysis.total_distance || 0).toFixed(2);
        const duration = Math.round((analysis.total_duration || 0) / 60);
        const avgSpeed = (analysis.average_speed || 0).toFixed(1);
        const emission = (analysis.carbon_emission || 0).toFixed(3);
        const transportMode = getTransportModeName(analysis.transport_mode || 'unknown');
        
        const cardHtml = `
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0">📅 ${item.date}</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <p class="mb-2"><strong>🚗 交通工具:</strong><br>${transportMode}</p>
                                <p class="mb-2"><strong>📏 距離:</strong><br>${distance} km</p>
                                <p class="mb-0"><strong>⏱️ 時間:</strong><br>${duration} 分鐘</p>
                            </div>
                            <div class="col-6">
                                <p class="mb-2"><strong>⚡ 平均速度:</strong><br>${avgSpeed} km/h</p>
                                <p class="mb-2"><strong>🌱 碳排放:</strong><br>${emission} kg CO₂</p>
                                ${analysis.confidence ? `<p class="mb-0"><strong>📊 信心度:</strong><br>${(analysis.confidence * 100).toFixed(0)}%</p>` : ''}
                            </div>
                        </div>
                        ${analysis.route_analysis ? `
                            <hr>
                            <p class="mb-0 small text-muted"><strong>路線分析:</strong><br>${analysis.route_analysis}</p>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
        
        container.insertAdjacentHTML('beforeend', cardHtml);
    });
    
    console.log(`成功顯示 ${displayedCount} 筆每日結果`);
    
    if (displayedCount === 0) {
        container.innerHTML = '<div class="col-12"><div class="alert alert-warning">沒有可顯示的分析結果</div></div>';
    }
}

// 繪製圖表
function drawCharts(data, summary) {
    console.log('繪製圖表,數據:', { data, summary });
    
    // 交通工具分布圖
    if (summary.transport_modes && Object.keys(summary.transport_modes).length > 0) {
        drawTransportChart(summary.transport_modes);
    } else {
        console.warn('沒有交通工具分布數據');
    }
    
    // 碳排放趨勢圖
    drawEmissionTrendChart(data);
}

// 繪製交通工具分布圖
function drawTransportChart(transportModes) {
    const ctx = document.getElementById('transportChart');
    if (!ctx) {
        console.warn('找不到 transportChart 元素');
        return;
    }
    
    if (transportChart) {
        transportChart.destroy();
    }
    
    const labels = Object.keys(transportModes).map(mode => getTransportModeName(mode));
    const values = Object.values(transportModes);
    
    console.log('交通工具圖表數據:', { labels, values });
    
    transportChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: [
                    '#007bff',
                    '#28a745',
                    '#ffc107',
                    '#dc3545',
                    '#6c757d',
                    '#17a2b8'
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
}

// 繪製碳排放趨勢圖
function drawEmissionTrendChart(data) {
    const ctx = document.getElementById('emissionChart');
    if (!ctx) {
        console.warn('找不到 emissionChart 元素');
        return;
    }
    
    if (emissionChart) {
        emissionChart.destroy();
    }
    
    const sortedData = data
        .filter(item => item.success && item.analysis)
        .sort((a, b) => new Date(a.date) - new Date(b.date));
    
    const labels = sortedData.map(item => item.date);
    const emissions = sortedData.map(item => item.analysis.carbon_emission || 0);
    
    console.log('碳排放趨勢圖數據:', { labels, emissions });
    
    emissionChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: '碳排放量 (kg CO₂)',
                data: emissions,
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                tension: 0.1,
                fill: true
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

// 顯示建議
function displaySuggestions(suggestions) {
    const container = document.getElementById('aiSuggestions');
    if (!container) {
        console.warn('找不到 aiSuggestions 元素');
        return;
    }
    
    if (!suggestions || suggestions.length === 0) {
        container.innerHTML = '<p class="text-muted mb-0">暫無建議</p>';
        return;
    }
    
    console.log('顯示建議:', suggestions);
    
    let html = '<ul class="mb-0">';
    suggestions.forEach(suggestion => {
        html += `<li class="mb-2">${suggestion}</li>`;
    });
    html += '</ul>';
    
    container.innerHTML = html;
}

// 獲取交通工具中文名稱
function getTransportModeName(mode) {
    const names = {
        'walking': '步行 🚶',
        'bicycle': '腳踏車 🚴',
        'motorcycle': '機車 🏍️',
        'car': '汽車 🚗',
        'bus': '公車 🚌',
        'mrt': '捷運 🚇',
        'train': '火車 🚆',
        'unknown': '未知'
    };
    
    return names[mode] || mode;
}

// 匯出結果
function exportResults() {
    if (!currentAnalysisData) {
        Swal.fire('提示', '沒有可匯出的分析結果', 'warning');
        return;
    }
    
    // 準備 CSV 資料
    let csv = '\ufeff'; // UTF-8 BOM
    csv += '日期,交通工具,距離(km),時間(分鐘),平均速度(km/h),碳排放(kg CO₂)\n';
    
    currentAnalysisData.data.forEach(item => {
        if (item.success && item.analysis) {
            const analysis = item.analysis;
            csv += `${item.date},`;
            csv += `${getTransportModeName(analysis.transport_mode)},`;
            csv += `${(analysis.total_distance || 0).toFixed(2)},`;
            csv += `${Math.round((analysis.total_duration || 0) / 60)},`;
            csv += `${(analysis.average_speed || 0).toFixed(1)},`;
            csv += `${(analysis.carbon_emission || 0).toFixed(3)}\n`;
        }
    });
    
    // 下載 CSV
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `carbon_analysis_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    Swal.fire({
        icon: 'success',
        title: '匯出成功',
        text: 'CSV 檔案已下載',
        timer: 1500,
        showConfirmButton: false
    });
}

// 重置分析
function resetAnalysis() {
    document.getElementById('analysisResultSection').style.display = 'none';
    document.getElementById('selectAll').checked = false;
    selectedDates = [];
    updateSelectedCount();
    
    Swal.fire({
        icon: 'info',
        title: '已重置',
        text: '可以重新選擇日期進行分析',
        timer: 1500,
        showConfirmButton: false
    });
}

// 初始化提示
console.log('%c🌱 AI 碳排放分析系統已載入', 'color: green; font-size: 16px; font-weight: bold;');
</script>
@endsection