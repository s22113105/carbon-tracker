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

@section('styles')
<style>
    .analysis-card {
        background: white;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .stat-box {
        text-align: center;
        padding: 15px;
        border-radius: 8px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .stat-number {
        font-size: 2rem;
        font-weight: bold;
        margin-bottom: 5px;
    }
    
    .stat-label {
        font-size: 0.9rem;
        opacity: 0.9;
    }
    
    .transport-badge {
        display: inline-block;
        padding: 5px 15px;
        border-radius: 20px;
        margin: 5px;
        font-size: 0.9rem;
    }
    
    .transport-walking { background: #10b981; color: white; }
    .transport-bicycle { background: #3b82f6; color: white; }
    .transport-motorcycle { background: #f59e0b; color: white; }
    .transport-car { background: #ef4444; color: white; }
    .transport-bus { background: #8b5cf6; color: white; }
    
    .eco-score {
        font-size: 3rem;
        font-weight: bold;
    }
    
    .eco-score-high { color: #10b981; }
    .eco-score-medium { color: #f59e0b; }
    .eco-score-low { color: #ef4444; }
    
    .suggestion-item {
        padding: 10px;
        margin: 10px 0;
        background: #f3f4f6;
        border-left: 4px solid #3b82f6;
        border-radius: 4px;
    }
    
    .loading-spinner {
        display: none;
        text-align: center;
        padding: 50px;
    }
    
    .loading-spinner.active {
        display: block;
    }
    
    .date-picker-container {
        background: white;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 20px;
    }
    
    .btn-analyze {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 10px 30px;
        border-radius: 5px;
        font-size: 1rem;
        cursor: pointer;
        transition: transform 0.2s;
    }
    
    .btn-analyze:hover {
        transform: translateY(-2px);
    }
    
    .btn-analyze:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .result-section {
        display: none;
    }
    
    .result-section.active {
        display: block;
    }
    
    .timeline-item {
        position: relative;
        padding-left: 40px;
        margin-bottom: 20px;
    }
    
    .timeline-item::before {
        content: '';
        position: absolute;
        left: 10px;
        top: 5px;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #3b82f6;
    }
    
    .timeline-item::after {
        content: '';
        position: absolute;
        left: 14px;
        top: 15px;
        width: 2px;
        height: calc(100% + 10px);
        background: #e5e7eb;
    }
    
    .timeline-item:last-child::after {
        display: none;
    }
    
    /* 測試面板樣式 - 預設隱藏 */
    .test-panel {
        display: none;
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        z-index: 9999;
        color: white;
        max-width: 350px;
    }
    
    .test-panel.active {
        display: block;
        animation: slideInRight 0.5s ease;
    }
    
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    .test-panel h5 {
        margin-top: 0;
        color: white;
        border-bottom: 2px solid rgba(255,255,255,0.3);
        padding-bottom: 10px;
    }
    
    .test-btn {
        background: white;
        color: #667eea;
        border: none;
        padding: 8px 15px;
        border-radius: 5px;
        margin: 5px;
        cursor: pointer;
        font-size: 0.9rem;
        transition: all 0.3s;
    }
    
    .test-btn:hover {
        background: #f3f4f6;
        transform: scale(1.05);
    }
    
    .test-result {
        margin-top: 10px;
        padding: 10px;
        background: rgba(255,255,255,0.2);
        border-radius: 5px;
        font-size: 0.85rem;
        max-height: 200px;
        overflow-y: auto;
    }
    
    .close-test-panel {
        position: absolute;
        top: 10px;
        right: 10px;
        background: rgba(255,255,255,0.2);
        border: none;
        color: white;
        width: 25px;
        height: 25px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 1.2rem;
        line-height: 1;
    }
    
    .close-test-panel:hover {
        background: rgba(255,255,255,0.3);
    }
    
    /* Konami Code 提示 */
    .konami-hint {
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(0,0,0,0.8);
        color: white;
        padding: 20px;
        border-radius: 10px;
        z-index: 10000;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 0.9; }
        50% { opacity: 1; }
    }
</style>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
@endsection

@section('content')
<div class="row mb-4">
    <div class="col-md-12">
        <h1>🌱 AI 碳排放分析系統</h1>
        <p class="text-muted">使用 AI 分析您的行程資料，計算碳排放並提供減碳建議</p>
    </div>
</div>

<!-- 日期選擇器 -->
<div class="date-picker-container">
    <h3>選擇分析期間</h3>
    <div class="row mt-3">
        <div class="col-md-4">
            <label for="start_date">開始日期</label>
            <input type="text" id="start_date" class="form-control" placeholder="選擇開始日期">
        </div>
        <div class="col-md-4">
            <label for="end_date">結束日期</label>
            <input type="text" id="end_date" class="form-control" placeholder="選擇結束日期">
        </div>
        <div class="col-md-4">
            <label>&nbsp;</label>
            <div>
                <button id="analyzeBtn" class="btn-analyze">開始分析</button>
                <button id="quickWeek" class="btn btn-outline-secondary ml-2">本週</button>
                <button id="quickMonth" class="btn btn-outline-secondary ml-2">本月</button>
            </div>
        </div>
    </div>
</div>

<!-- 載入中動畫 -->
<div id="loadingSpinner" class="loading-spinner">
    <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
        <span class="sr-only">分析中...</span>
    </div>
    <p class="mt-3">AI 正在分析您的行程資料，請稍候...</p>
</div>

<!-- 分析結果區域 -->
<div id="resultSection" class="result-section">
    
    <!-- 統計摘要 -->
    <div class="analysis-card">
        <h3>📊 統計摘要</h3>
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="stat-box">
                    <div id="totalEmission" class="stat-number">0</div>
                    <div class="stat-label">總碳排放 (kg CO₂)</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box">
                    <div id="totalDistance" class="stat-number">0</div>
                    <div class="stat-label">總距離 (公里)</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box">
                    <div id="totalDuration" class="stat-number">0</div>
                    <div class="stat-label">總時間</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box">
                    <div id="ecoScore" class="stat-number">0</div>
                    <div class="stat-label">環保分數</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 每日分析詳情 -->
    <div class="analysis-card">
        <h3>📅 每日行程分析</h3>
        <div id="dailyAnalysis" class="mt-3">
            <!-- 動態填充 -->
        </div>
    </div>
    
    <!-- 交通工具分布圖表 -->
    <div class="analysis-card">
        <h3>🚗 交通工具使用分布</h3>
        <canvas id="transportChart" width="400" height="200"></canvas>
    </div>
    
    <!-- 碳排放趨勢圖 -->
    <div class="analysis-card">
        <h3>📈 碳排放趨勢</h3>
        <canvas id="emissionTrendChart" width="400" height="200"></canvas>
    </div>
    
    <!-- AI 建議 -->
    <div class="analysis-card" id="aiSuggestionsCard">
        <h3>💡 AI 減碳建議</h3>
        <div id="aiSuggestions">
            <!-- 動態填充 -->
        </div>
    </div>
</div>

<!-- 隱藏的測試面板 -->
<div id="testPanel" class="test-panel">
    <button class="close-test-panel" onclick="closeTestPanel()">×</button>
    <h5>🔧 開發測試面板</h5>
    <div class="test-buttons">
        <button class="test-btn" onclick="testOpenAIConnection()">測試 OpenAI 連接</button>
        <button class="test-btn" onclick="testMockAnalysis()">模擬資料分析</button>
        <button class="test-btn" onclick="testAllModes()">測試所有交通工具</button>
        <button class="test-btn" onclick="showApiConfig()">查看 API 設定</button>
        <button class="test-btn" onclick="clearCache()">清除快取</button>
    </div>
    <div id="testResult" class="test-result" style="display: none;">
        <!-- 測試結果顯示區 -->
    </div>
</div>

<!-- Konami Code 提示 -->
<div id="konamiHint" class="konami-hint">
    🎮 密技啟動！測試面板已解鎖
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/zh_tw.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Konami Code 實作 (上上下下左右左右BA)
const konamiCode = ['ArrowUp', 'ArrowUp', 'ArrowDown', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'ArrowLeft', 'ArrowRight', 'b', 'a'];
let konamiIndex = 0;
let konamiEnabled = false;

// 監聽鍵盤事件
document.addEventListener('keydown', (e) => {
    // 只在 AI 建議區域可見時才監聽
    const aiSuggestionsCard = document.getElementById('aiSuggestionsCard');
    if (!aiSuggestionsCard || aiSuggestionsCard.offsetParent === null) {
        return;
    }
    
    const key = e.key.toLowerCase();
    
    // 檢查按鍵是否符合密技序列
    if (key === konamiCode[konamiIndex].toLowerCase()) {
        konamiIndex++;
        
        // 完成密技
        if (konamiIndex === konamiCode.length) {
            activateTestPanel();
            konamiIndex = 0;
        }
    } else {
        // 重置
        konamiIndex = 0;
        // 檢查是否從頭開始
        if (key === konamiCode[0].toLowerCase()) {
            konamiIndex = 1;
        }
    }
});

// 啟動測試面板
function activateTestPanel() {
    if (!konamiEnabled) {
        konamiEnabled = true;
        
        // 顯示提示
        const hint = document.getElementById('konamiHint');
        hint.style.display = 'block';
        setTimeout(() => {
            hint.style.display = 'none';
        }, 2000);
        
        // 顯示測試面板
        const testPanel = document.getElementById('testPanel');
        testPanel.classList.add('active');
        
        // 播放音效（選擇性）
        playKonamiSound();
    }
}

// 播放 Konami 音效
function playKonamiSound() {
    // 建立簡單的音效
    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
    const oscillator = audioContext.createOscillator();
    const gainNode = audioContext.createGain();
    
    oscillator.connect(gainNode);
    gainNode.connect(audioContext.destination);
    
    oscillator.frequency.setValueAtTime(880, audioContext.currentTime); // A5
    oscillator.frequency.setValueAtTime(1760, audioContext.currentTime + 0.1); // A6
    
    gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
    
    oscillator.start(audioContext.currentTime);
    oscillator.stop(audioContext.currentTime + 0.5);
}

// 關閉測試面板
function closeTestPanel() {
    document.getElementById('testPanel').classList.remove('active');
}

// ========== 測試功能 ==========

// 測試 OpenAI 連接
async function testOpenAIConnection() {
    showTestResult('測試中...');
    
    try {
        const response = await fetch('/user/carbon/test/connection', {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            showTestResult(`
                ✅ OpenAI 連接成功！<br>
                Model: ${result.config.model}<br>
                API URL: ${result.config.api_url}<br>
                Has API Key: ${result.config.has_api_key ? 'Yes' : 'No'}
            `, 'success');
        } else {
            showTestResult('❌ ' + result.message, 'error');
        }
    } catch (error) {
        showTestResult('❌ 測試失敗: ' + error.message, 'error');
    }
}

// 測試模擬資料分析
async function testMockAnalysis() {
    showTestResult('生成模擬資料並分析中...');
    
    try {
        const response = await fetch('/user/carbon/test/analysis', {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            const analysis = result.analysis_result;
            showTestResult(`
                ✅ 分析成功！<br>
                <strong>模擬類型:</strong> ${result.mock_data_type}<br>
                <strong>偵測結果:</strong> ${analysis.transport_mode}<br>
                <strong>信心度:</strong> ${(analysis.confidence * 100).toFixed(0)}%<br>
                <strong>總距離:</strong> ${analysis.total_distance.toFixed(2)} km<br>
                <strong>碳排放:</strong> ${analysis.carbon_emission.toFixed(3)} kg CO₂<br>
                <strong>建議數量:</strong> ${analysis.suggestions.length}
            `, 'success');
        } else {
            showTestResult('❌ 分析失敗', 'error');
        }
    } catch (error) {
        showTestResult('❌ 測試失敗: ' + error.message, 'error');
    }
}

// 測試所有交通工具
async function testAllModes() {
    showTestResult('測試所有交通工具判斷...');
    
    try {
        const response = await fetch('/user/carbon/test/all-modes', {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            let html = '✅ 測試完成！<br><br>';
            
            for (const [mode, data] of Object.entries(result.test_results)) {
                const icon = data.is_correct ? '✓' : '✗';
                const color = data.is_correct ? 'green' : 'red';
                html += `<span style="color: ${color}">${icon}</span> ${mode}: ${data.detected_mode} (${(data.confidence * 100).toFixed(0)}%)<br>`;
            }
            
            html += `<br><strong>準確率:</strong> ${result.accuracy.percentage}`;
            
            showTestResult(html, 'success');
        } else {
            showTestResult('❌ 測試失敗', 'error');
        }
    } catch (error) {
        showTestResult('❌ 測試失敗: ' + error.message, 'error');
    }
}

// 顯示 API 設定
async function showApiConfig() {
    try {
        const response = await fetch('/user/carbon/test/config', {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const config = await response.json();
        
        showTestResult(`
            <strong>API 設定:</strong><br>
            Model: ${config.model}<br>
            Max Tokens: ${config.max_tokens}<br>
            Temperature: ${config.temperature}<br>
            Timeout: ${config.timeout}秒<br>
            API Key: ${config.has_key ? '已設定' : '未設定'}<br>
            環境: ${config.environment}
        `, 'info');
    } catch (error) {
        showTestResult('❌ 無法取得設定: ' + error.message, 'error');
    }
}

// 清除快取
async function clearCache() {
    if (!confirm('確定要清除所有 AI 分析快取嗎？')) {
        return;
    }
    
    showTestResult('清除快取中...');
    
    try {
        const response = await fetch('/user/carbon/test/clear-cache', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            showTestResult('✅ 快取已清除！', 'success');
        } else {
            showTestResult('❌ 清除失敗', 'error');
        }
    } catch (error) {
        showTestResult('❌ 清除失敗: ' + error.message, 'error');
    }
}

// 顯示測試結果
function showTestResult(message, type = 'info') {
    const resultDiv = document.getElementById('testResult');
    resultDiv.style.display = 'block';
    resultDiv.innerHTML = message;
    
    // 根據類型設定顏色
    const colors = {
        'success': '#10b981',
        'error': '#ef4444',
        'info': '#3b82f6'
    };
    
    resultDiv.style.borderLeft = `3px solid ${colors[type] || colors.info}`;
}

// ========== 原有的分析功能 ==========

// 初始化日期選擇器
flatpickr("#start_date", {
    locale: "zh_tw",
    dateFormat: "Y-m-d",
    maxDate: "today",
    defaultDate: new Date().setDate(new Date().getDate() - 7)
});

flatpickr("#end_date", {
    locale: "zh_tw",
    dateFormat: "Y-m-d",
    maxDate: "today",
    defaultDate: "today"
});

// 快速選擇按鈕
document.getElementById('quickWeek').addEventListener('click', function() {
    const today = new Date();
    const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
    document.getElementById('start_date').value = formatDate(weekAgo);
    document.getElementById('end_date').value = formatDate(today);
});

document.getElementById('quickMonth').addEventListener('click', function() {
    const today = new Date();
    const monthAgo = new Date(today.getFullYear(), today.getMonth() - 1, today.getDate());
    document.getElementById('start_date').value = formatDate(monthAgo);
    document.getElementById('end_date').value = formatDate(today);
});

// 格式化日期
function formatDate(date) {
    return date.getFullYear() + '-' + 
           String(date.getMonth() + 1).padStart(2, '0') + '-' + 
           String(date.getDate()).padStart(2, '0');
}

// 圖表變數
let transportChart = null;
let emissionTrendChart = null;

// 分析按鈕點擊事件
document.getElementById('analyzeBtn').addEventListener('click', async function() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    
    if (!startDate || !endDate) {
        Swal.fire('提示', '請選擇開始和結束日期', 'warning');
        return;
    }
    
    // 顯示載入動畫
    document.getElementById('loadingSpinner').classList.add('active');
    document.getElementById('resultSection').classList.remove('active');
    this.disabled = true;
    
    try {
        // 呼叫 API 進行分析
        const response = await fetch('/user/carbon/analyze', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                start_date: startDate,
                end_date: endDate,
                force_refresh: false
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            displayResults(result.data, result.summary);
        } else {
            Swal.fire('錯誤', result.message || '分析失敗', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire('錯誤', '系統發生錯誤，請稍後再試', 'error');
    } finally {
        document.getElementById('loadingSpinner').classList.remove('active');
        this.disabled = false;
    }
});

// 顯示分析結果
function displayResults(data, summary) {
    if (!data || data.length === 0) {
        Swal.fire('提示', '所選期間沒有GPS資料', 'info');
        return;
    }
    
    // 顯示結果區域
    document.getElementById('resultSection').classList.add('active');
    
    // 更新統計摘要
    if (summary) {
        document.getElementById('totalEmission').textContent = summary.total_emission.toFixed(2);
        document.getElementById('totalDistance').textContent = summary.total_distance.toFixed(1);
        document.getElementById('totalDuration').textContent = summary.total_duration_formatted;
        document.getElementById('ecoScore').textContent = summary.eco_score;
        
        // 設定環保分數顏色
        const scoreElement = document.getElementById('ecoScore');
        scoreElement.className = 'stat-number';
        if (summary.eco_score >= 70) {
            scoreElement.classList.add('eco-score-high');
        } else if (summary.eco_score >= 40) {
            scoreElement.classList.add('eco-score-medium');
        } else {
            scoreElement.classList.add('eco-score-low');
        }
    }
    
    // 顯示每日分析
    displayDailyAnalysis(data);
    
    // 繪製圖表
    drawTransportChart(summary);
    drawEmissionTrendChart(data);
    
    // 顯示 AI 建議
    displayAISuggestions(data);
}

// 顯示每日分析詳情
function displayDailyAnalysis(data) {
    const container = document.getElementById('dailyAnalysis');
    container.innerHTML = '';
    
    data.forEach(analysis => {
        const transportColors = {
            'walking': 'transport-walking',
            'bicycle': 'transport-bicycle',
            'motorcycle': 'transport-motorcycle',
            'car': 'transport-car',
            'bus': 'transport-bus'
        };
        
        const transportNames = {
            'walking': '步行',
            'bicycle': '腳踏車',
            'motorcycle': '機車',
            'car': '汽車',
            'bus': '公車'
        };
        
        const aiAnalysis = analysis.ai_analysis || {};
        const confidence = (aiAnalysis.confidence || 0) * 100;
        
        const html = `
            <div class="timeline-item">
                <h5>${analysis.analysis_date}</h5>
                <div class="row">
                    <div class="col-md-3">
                        <span class="transport-badge ${transportColors[analysis.transport_mode]}">
                            ${transportNames[analysis.transport_mode] || analysis.transport_mode}
                        </span>
                        <small class="text-muted d-block mt-1">信心度: ${confidence.toFixed(0)}%</small>
                    </div>
                    <div class="col-md-3">
                        <strong>距離:</strong> ${parseFloat(analysis.total_distance).toFixed(2)} 公里<br>
                        <strong>時間:</strong> ${Math.floor(analysis.total_duration / 60)} 分鐘
                    </div>
                    <div class="col-md-3">
                        <strong>平均速度:</strong> ${parseFloat(analysis.average_speed || 0).toFixed(1)} km/h<br>
                        <strong>碳排放:</strong> ${parseFloat(analysis.carbon_emission).toFixed(3)} kg CO₂
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted">${aiAnalysis.route_analysis || '無詳細分析'}</small>
                    </div>
                </div>
            </div>
        `;
        
        container.innerHTML += html;
    });
}

// 繪製交通工具分布圖
function drawTransportChart(summary) {
    if (!summary || !summary.transport_modes) return;
    
    const ctx = document.getElementById('transportChart').getContext('2d');
    
    // 銷毀舊圖表
    if (transportChart) {
        transportChart.destroy();
    }
    
    const transportNames = {
        'walking': '步行',
        'bicycle': '腳踏車',
        'motorcycle': '機車',
        'car': '汽車',
        'bus': '公車'
    };
    
    const labels = Object.keys(summary.transport_modes).map(mode => transportNames[mode] || mode);
    const data = Object.values(summary.transport_modes);
    
    transportChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: [
                    '#10b981',
                    '#3b82f6',
                    '#f59e0b',
                    '#ef4444',
                    '#8b5cf6'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = (context.parsed / total * 100).toFixed(1);
                            return context.label + ': ' + context.parsed + ' 次 (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
}

// 繪製碳排放趨勢圖
function drawEmissionTrendChart(data) {
    const ctx = document.getElementById('emissionTrendChart').getContext('2d');
    
    // 銷毀舊圖表
    if (emissionTrendChart) {
        emissionTrendChart.destroy();
    }
    
    const labels = data.map(d => d.analysis_date);
    const emissions = data.map(d => parseFloat(d.carbon_emission));
    const distances = data.map(d => parseFloat(d.total_distance));
    
    emissionTrendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: '碳排放 (kg CO₂)',
                data: emissions,
                borderColor: '#ef4444',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                yAxisID: 'y'
            }, {
                label: '距離 (公里)',
                data: distances,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: '碳排放 (kg CO₂)'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: '距離 (公里)'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });
}

// 顯示 AI 建議
function displayAISuggestions(data) {
    const container = document.getElementById('aiSuggestions');
    container.innerHTML = '';
    
    // 收集所有建議
    const allSuggestions = new Set();
    
    data.forEach(analysis => {
        if (analysis.suggestions) {
            const suggestions = analysis.suggestions.split('\n').filter(s => s.trim());
            suggestions.forEach(s => allSuggestions.add(s));
        }
        
        // 也從 AI 分析中提取建議
        if (analysis.ai_analysis && analysis.ai_analysis.suggestions) {
            analysis.ai_analysis.suggestions.forEach(s => allSuggestions.add(s));
        }
    });
    
    if (allSuggestions.size === 0) {
        container.innerHTML = '<p class="text-muted">暫無建議</p>';
        return;
    }
    
    // 顯示建議
    let html = '<div class="row">';
    let index = 0;
    
    allSuggestions.forEach(suggestion => {
        if (index % 2 === 0 && index > 0) {
            html += '</div><div class="row">';
        }
        
        html += `
            <div class="col-md-6">
                <div class="suggestion-item">
                    <i class="fas fa-lightbulb text-warning"></i> ${suggestion}
                </div>
            </div>
        `;
        
        index++;
    });
    
    html += '</div>';
    container.innerHTML = html;
    
    // 添加總結
    const totalEmission = data.reduce((sum, d) => sum + parseFloat(d.carbon_emission), 0);
    const avgEmission = totalEmission / data.length;
    
    const summaryHtml = `
        <div class="alert alert-info mt-4">
            <h5>💚 減碳潛力分析</h5>
            <p>您目前的平均每日碳排放為 <strong>${avgEmission.toFixed(2)} kg CO₂</strong>。</p>
            <p>如果改用大眾運輸工具，預計可減少約 <strong>${(avgEmission * 0.3).toFixed(2)} kg CO₂</strong> 的碳排放。</p>
            <p>相當於種植 <strong>${Math.ceil(avgEmission * 0.3 / 0.022)}</strong> 棵樹一天的吸碳量！</p>
        </div>
    `;
    
    container.innerHTML += summaryHtml;
}

// 開發者彩蛋提示
console.log('%c🎮 Konami Code 已啟用！', 'color: #667eea; font-size: 14px; font-weight: bold;');
console.log('%c在 AI 建議區域按下：↑↑↓↓←→←→BA', 'color: #764ba2; font-size: 12px;');
</script>
@endsection