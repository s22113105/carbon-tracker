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

    <!-- AI 分析結果 -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-brain me-2"></i>AI 分析報告</h5>
                </div>
                <div class="card-body">
                    <div id="aiAnalysis">
                        <!-- AI 分析內容 -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 減碳建議 -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>個人化減碳建議</h5>
                </div>
                <div class="card-body">
                    <div id="suggestions">
                        <!-- 建議內容 -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 減碳潛力 -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-leaf me-2"></i>每月減碳潛力</h5>
                </div>
                <div class="card-body text-center">
                    <h2 id="monthlyImpact" class="text-primary">計算中...</h2>
                    <p class="text-muted">預估每月可減少的碳排放量</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-tree me-2"></i>環境等效</h5>
                </div>
                <div class="card-body text-center">
                    <div id="environmentalEquivalent">
                        <p class="text-muted">計算中...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 錯誤訊息 -->
<div id="errorMessage" class="row mb-4" style="display: none;">
    <div class="col-md-12">
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <span id="errorText"></span>
        </div>
    </div>
</div>

<!-- 後備建議 -->
<div id="fallbackSuggestions" style="display: none;">
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
</div>

@push('scripts')
<script>
let currentAnalysisData = null;

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
            displayGpsAnalysis(data.analysis);
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

// 重新分析所有行程
async function reanalyzeTrips() {
    if (!confirm('這將重新分析最近 30 天的所有行程，可能需要較長時間，確定要繼續嗎？')) {
        return;
    }

    showLoading();

    try {
        const response = await fetch('/user/ai-suggestions/reanalyze', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                date_range: 30
            })
        });

        const data = await response.json();
        
        if (data.success) {
            alert(data.message);
            if (data.errors && data.errors.length > 0) {
                console.warn('分析過程中的錯誤:', data.errors);
            }
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

// 顯示 AI 建議結果
function displayAISuggestions(data) {
    currentAnalysisData = data;
    
    // 顯示資料摘要
    displayDataSummary(data.data_summary);
    
    // 顯示 AI 分析
    displayAIAnalysis(data.suggestions);
    
    // 顯示建議
    displaySuggestions(data.suggestions.suggestions || []);
    
    // 顯示減碳潛力
    displayImpact(data.suggestions);
    
    // 顯示結果區域
    document.getElementById('aiSuggestions').style.display = 'block';
}

// 顯示資料摘要
function displayDataSummary(summary) {
    const summaryHtml = `
        <div class="col-md-3">
            <div class="text-center">
                <h4 class="text-primary">${summary.total_trips}</h4>
                <p class="mb-0">總行程數</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="text-center">
                <h4 class="text-danger">${summary.total_emission} kg</h4>
                <p class="mb-0">總碳排放量</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="text-center">
                <h4 class="text-info">${summary.total_distance} km</h4>
                <p class="mb-0">總行駛距離</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="text-center">
                <h4 class="text-success">${getTransportLabel(summary.dominant_transport)}</h4>
                <p class="mb-0">主要交通工具</p>
            </div>
        </div>
    `;
    document.getElementById('dataSummary').innerHTML = summaryHtml;
}

// 顯示 AI 分析
function displayAIAnalysis(suggestions) {
    const analysisHtml = `
        <div class="mb-3">
            <h6>通勤模式分析</h6>
            <p>${suggestions.analysis || '正在分析您的通勤模式...'}</p>
        </div>
        <div class="mb-3">
            <h6>碳足跡評估</h6>
            <p>${suggestions.carbon_footprint || '正在評估您的碳足跡...'}</p>
        </div>
    `;
    document.getElementById('aiAnalysis').innerHTML = analysisHtml;
}

// 顯示建議列表
function displaySuggestions(suggestions) {
    if (!suggestions || suggestions.length === 0) {
        document.getElementById('suggestions').innerHTML = '<p class="text-muted">暫無具體建議</p>';
        return;
    }

    const suggestionsHtml = suggestions.map((suggestion, index) => {
        const difficultyClass = getDifficultyClass(suggestion.difficulty);
        return `
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h6 class="card-title">
                                <span class="badge bg-secondary me-2">${suggestion.category || '通用建議'}</span>
                                ${suggestion.title}
                            </h6>
                            <p class="card-text">${suggestion.description}</p>
                        </div>
                        <div class="text-end">
                            <span class="badge ${difficultyClass}">${suggestion.difficulty || '中等'}</span>
                            <div class="mt-1">
                                <small class="text-success">
                                    <i class="fas fa-leaf"></i> 
                                    ${suggestion.potential_reduction || '待計算'} CO2
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');

    document.getElementById('suggestions').innerHTML = suggestionsHtml;
}

// 顯示減碳影響
function displayImpact(suggestions) {
    document.getElementById('monthlyImpact').textContent = 
        suggestions.monthly_impact || '計算中...';
    
    document.getElementById('environmentalEquivalent').innerHTML = 
        `<p class="text-success">${suggestions.environmental_equivalent || '計算中...'}</p>`;
}

// 顯示 GPS 分析結果
function displayGpsAnalysis(analysis) {
    const analysisHtml = `
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>GPS 軌跡分析結果</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>行程 ID</th>
                                        <th>開始時間</th>
                                        <th>結束時間</th>
                                        <th>原交通工具</th>
                                        <th>AI 分析結果</th>
                                        <th>信心度</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${analysis.map(trip => `
                                        <tr>
                                            <td>${trip.trip_id}</td>
                                            <td>${new Date(trip.start_time).toLocaleString()}</td>
                                            <td>${new Date(trip.end_time).toLocaleString()}</td>
                                            <td>${getTransportLabel(trip.original_mode)}</td>
                                            <td>${getTransportLabel(trip.ai_analysis.transport_mode)}</td>
                                            <td>
                                                <span class="badge ${getConfidenceClass(trip.ai_analysis.confidence)}">
                                                    ${Math.round(trip.ai_analysis.confidence * 100)}%
                                                </span>
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('aiSuggestions').innerHTML = analysisHtml;
    document.getElementById('aiSuggestions').style.display = 'block';
}

// 顯示後備建議
function showFallbackSuggestions(fallback) {
    document.getElementById('fallbackContent').innerHTML = 
        `<pre class="bg-light p-3">${fallback}</pre>`;
    document.getElementById('fallbackSuggestions').style.display = 'block';
}

// 輔助函數
function showLoading() {
    document.getElementById('loadingState').style.display = 'block';
}

function hideLoading() {
    document.getElementById('loadingState').style.display = 'none';
}

function showError(message) {
    document.getElementById('errorText').textContent = message;
    document.getElementById('errorMessage').style.display = 'block';
}

function hideError() {
    document.getElementById('errorMessage').style.display = 'none';
}

function hideResults() {
    document.getElementById('aiSuggestions').style.display = 'none';
    document.getElementById('fallbackSuggestions').style.display = 'none';
}

function getTransportLabel(transport) {
    const labels = {
        'walking': '步行',
        'bicycle': '腳踏車',
        'motorcycle': '機車',
        'car': '汽車',
        'bus': '公車'
    };
    return labels[transport] || transport || '未知';
}

function getDifficultyClass(difficulty) {
    const classes = {
        '簡單': 'bg-success',
        '中等': 'bg-warning',
        '困難': 'bg-danger'
    };
    return classes[difficulty] || 'bg-secondary';
}

function getConfidenceClass(confidence) {
    if (confidence >= 0.8) return 'bg-success';
    if (confidence >= 0.6) return 'bg-warning';
    return 'bg-danger';
}

// 頁面載入時設定預設日期
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date();
    const thirtyDaysAgo = new Date(today);
    thirtyDaysAgo.setDate(today.getDate() - 30);
    
    document.getElementById('customStartDate').value = 
        thirtyDaysAgo.toISOString().split('T')[0];
    document.getElementById('customEndDate').value = 
        today.toISOString().split('T')[0];
});
</script>
@endpush

@endsection