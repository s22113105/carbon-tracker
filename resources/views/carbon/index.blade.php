@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4><i class="fas fa-leaf"></i> AI 碳排放分析與建議</h4>
                </div>
                <div class="card-body">
                    <!-- 日期選擇區域 -->
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <form id="analysisForm">
                                <div class="row">
                                    <div class="col-md-4">
                                        <label for="start_date" class="form-label">開始日期</label>
                                        <input type="date" class="form-control" id="start_date" name="start_date" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="end_date" class="form-label">結束日期</label>
                                        <input type="date" class="form-control" id="end_date" name="end_date" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">&nbsp;</label>
                                        <div>
                                            <button type="submit" class="btn btn-primary" id="analyzeBtn">
                                                <i class="fas fa-search"></i> 開始分析
                                            </button>
                                            <button type="button" class="btn btn-secondary" id="historyBtn">
                                                <i class="fas fa-history"></i> 歷史記錄
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="card-title">快速選擇</h6>
                                    <button class="btn btn-outline-primary btn-sm me-1 quick-select" data-days="7">近7天</button>
                                    <button class="btn btn-outline-primary btn-sm me-1 quick-select" data-days="30">近30天</button>
                                    <button class="btn btn-outline-primary btn-sm quick-select" data-days="90">近90天</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 載入指示器 -->
                    <div id="loadingIndicator" class="text-center my-4" style="display: none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">分析中...</span>
                        </div>
                        <p class="mt-2">AI正在分析您的移動軌跡，請稍候...</p>
                    </div>

                    <!-- 分析結果區域 -->
                    <div id="analysisResults" style="display: none;">
                        <!-- 總覽卡片 -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card text-center bg-primary text-white">
                                    <div class="card-body">
                                        <h5 class="card-title">總距離</h5>
                                        <h3 id="totalDistance">-- km</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card text-center bg-info text-white">
                                    <div class="card-body">
                                        <h5 class="card-title">總時間</h5>
                                        <h3 id="totalTime">-- 分鐘</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card text-center bg-warning text-white">
                                    <div class="card-body">
                                        <h5 class="card-title">碳排放量</h5>
                                        <h3 id="totalEmission">-- kg CO₂</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card text-center" id="footprintCard">
                                    <div class="card-body">
                                        <h5 class="card-title">碳足跡等級</h5>
                                        <h3 id="footprintLevel">--</h3>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 交通工具分析 -->
                        <div class="row mb-4">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-chart-pie"></i> 交通工具使用分析</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="transportChart" width="400" height="200"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-list"></i> 詳細分解</h5>
                                    </div>
                                    <div class="card-body" id="transportBreakdown">
                                        <!-- 動態生成的交通工具詳情 -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 改善建議 -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-lightbulb"></i> AI 減碳建議</h5>
                                    </div>
                                    <div class="card-body" id="recommendations">
                                        <!-- 動態生成的建議內容 -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 關鍵洞察 -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="alert alert-info" id="keyInsight">
                                    <!-- 關鍵洞察內容 -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 歷史記錄模態框 -->
                    <div class="modal fade" id="historyModal" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">分析歷史記錄</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body" id="historyContent">
                                    <!-- 歷史記錄內容 -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function() {
    let transportChart = null;

    // 設定預設日期
    const today = new Date();
    const lastWeek = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
    
    $('#start_date').val(lastWeek.toISOString().split('T')[0]);
    $('#end_date').val(today.toISOString().split('T')[0]);

    // 快速選擇按鈕
    $('.quick-select').click(function() {
        const days = parseInt($(this).data('days'));
        const endDate = new Date();
        const startDate = new Date(endDate.getTime() - days * 24 * 60 * 60 * 1000);
        
        $('#start_date').val(startDate.toISOString().split('T')[0]);
        $('#end_date').val(endDate.toISOString().split('T')[0]);
    });

    // 分析表單提交
    $('#analysisForm').submit(function(e) {
        e.preventDefault();
        
        const formData = {
            start_date: $('#start_date').val(),
            end_date: $('#end_date').val(),
            _token: $('meta[name="csrf-token"]').attr('content')
        };

        showLoading();
        
        $.ajax({
            url: '{{ route("carbon.analyze") }}',
            method: 'POST',
            data: formData,
            success: function(response) {
                hideLoading();
                if (response.success) {
                    displayAnalysisResults(response.data);
                } else {
                    showError(response.message);
                }
            },
            error: function(xhr) {
                hideLoading();
                const message = xhr.responseJSON?.message || '分析失敗，請稍後再試';
                showError(message);
            }
        });
    });

    // 顯示歷史記錄
    $('#historyBtn').click(function() {
        $.ajax({
            url: '{{ route("carbon.history") }}',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    displayHistory(response.data);
                    $('#historyModal').modal('show');
                }
            }
        });
    });

    function showLoading() {
        $('#loadingIndicator').show();
        $('#analysisResults').hide();
        $('#analyzeBtn').prop('disabled', true);
    }

    function hideLoading() {
        $('#loadingIndicator').hide();
        $('#analyzeBtn').prop('disabled', false);
    }

    function showError(message) {
        toastr.error(message);
    }

    function displayAnalysisResults(data) {
        const analysis = data.analysis;
        const recommendations = data.recommendations;
        const summary = data.summary;

        // 更新總覽卡片
        $('#totalDistance').text(analysis.total_distance);
        $('#totalTime').text(analysis.total_time);
        $('#totalEmission').text(analysis.total_carbon_emission);
        $('#footprintLevel').text(getFootprintText(summary.current_footprint));

        // 設定碳足跡等級卡片顏色
        updateFootprintCard(summary.current_footprint);

        // 更新交通工具分解
        updateTransportBreakdown(analysis.transportation_breakdown);

        // 更新圓餅圖
        updateTransportChart(analysis.transportation_breakdown);

        // 更新建議
        updateRecommendations(recommendations);

        // 更新關鍵洞察
        $('#keyInsight').html(`<strong>關鍵洞察：</strong> ${summary.key_insight}<br>
                              <strong>改善潛力：</strong> ${summary.improvement_potential}% 的減碳空間`);

        $('#analysisResults').show();
    }

    function getFootprintText(level) {
        const levels = {
            'low': '低',
            'medium': '中等',
            'high': '高'
        };
        return levels[level] || level;
    }

    function updateFootprintCard(level) {
        const card = $('#footprintCard');
        card.removeClass('bg-success bg-warning bg-danger text-white');
        
        switch(level) {
            case 'low':
                card.addClass('bg-success text-white');
                break;
            case 'medium':
                card.addClass('bg-warning text-white');
                break;
            case 'high':
                card.addClass('bg-danger text-white');
                break;
        }
    }

    function updateTransportBreakdown(breakdown) {
        const container = $('#transportBreakdown');
        container.empty();

        const transportIcons = {
            'walking': 'fa-walking',
            'bicycle': 'fa-bicycle',
            'motorcycle': 'fa-motorcycle',
            'car': 'fa-car',
            'bus': 'fa-bus'
        };

        const transportNames = {
            'walking': '步行',
            'bicycle': '腳踏車',
            'motorcycle': '機車',
            'car': '汽車',
            'bus': '公車'
        };

        breakdown.forEach(item => {
            const icon = transportIcons[item.type] || 'fa-question';
            const name = transportNames[item.type] || item.type;
            
            container.append(`
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <i class="fas ${icon}"></i> ${name}
                    </div>
                    <div class="text-end">
                        <small class="text-muted">${item.distance} km</small><br>
                        <small class="text-danger">${item.carbon_emission} kg CO₂</small>
                    </div>
                </div>
            `);
        });
    }

    function updateTransportChart(breakdown) {
        const ctx = document.getElementById('transportChart').getContext('2d');
        
        if (transportChart) {
            transportChart.destroy();
        }

        const labels = breakdown.map(item => {
            const names = {
                'walking': '步行',
                'bicycle': '腳踏車',
                'motorcycle': '機車',
                'car': '汽車',
                'bus': '公車'
            };
            return names[item.type] || item.type;
        });

        const emissions = breakdown.map(item => parseFloat(item.carbon_emission));

        transportChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: emissions,
                    backgroundColor: [
                        '#28a745',
                        '#17a2b8',
                        '#ffc107',
                        '#dc3545',
                        '#6f42c1'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.parsed + ' kg CO₂';
                            }
                        }
                    }
                }
            }
        });
    }

    function updateRecommendations(recommendations) {
        const container = $('#recommendations');
        container.empty();

        if (!recommendations || recommendations.length === 0) {
            container.append('<p class="text-muted">目前沒有特別的建議</p>');
            return;
        }

        recommendations.forEach((rec, index) => {
            const difficultyBadge = getDifficultyBadge(rec.difficulty);
            
            container.append(`
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="card-title">${rec.title}</h6>
                                <p class="card-text">${rec.description}</p>
                            </div>
                            <div class="text-end">
                                ${difficultyBadge}
                                <div class="mt-2">
                                    <small class="text-success">
                                        <i class="fas fa-leaf"></i> 可減少 ${rec.potential_reduction} kg CO₂
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `);
        });
    }

    function getDifficultyBadge(difficulty) {
        const badges = {
            'easy': '<span class="badge bg-success">容易</span>',
            'medium': '<span class="badge bg-warning">中等</span>',
            'hard': '<span class="badge bg-danger">困難</span>'
        };
        return badges[difficulty] || '<span class="badge bg-secondary">未知</span>';
    }

    function displayHistory(data) {
        const container = $('#historyContent');
        container.empty();

        if (!data.data || data.data.length === 0) {
            container.append('<p class="text-muted">沒有歷史分析記錄</p>');
            return;
        }

        const table = $(`
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>分析日期</th>
                        <th>期間</th>
                        <th>碳排放量</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        `);

        data.data.forEach(item => {
            const row = $(`
                <tr>
                    <td>${new Date(item.created_at).toLocaleDateString('zh-TW')}</td>
                    <td>${item.start_date} ~ ${item.end_date}</td>
                    <td>${item.total_carbon_emission} kg CO₂</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary view-analysis" data-id="${item.id}">
                            <i class="fas fa-eye"></i> 查看
                        </button>
                    </td>
                </tr>
            `);
            table.find('tbody').append(row);
        });

        container.append(table);

        // 綁定查看按鈕事件
        $('.view-analysis').click(function() {
            const analysisId = $(this).data('id');
            viewAnalysis(analysisId);
        });
    }

    function viewAnalysis(analysisId) {
        $.ajax({
            url: `{{ route("carbon.show", ":id") }}`.replace(':id', analysisId),
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    $('#historyModal').modal('hide');
                    displayAnalysisResults(response.data.analysis_result);
                }
            }
        });
    }
});
</script>
@endpush
@endsection