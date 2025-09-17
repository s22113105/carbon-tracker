<div>
    <!-- 圖表類型選擇 -->
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="btn-group" role="group">
                <input type="radio" class="btn-check" name="chartType" id="daily" value="daily" wire:model.live="chartType">
                <label class="btn btn-outline-primary" for="daily">每日趨勢</label>

                <input type="radio" class="btn-check" name="chartType" id="weekly" value="weekly" wire:model.live="chartType">
                <label class="btn btn-outline-primary" for="weekly">每週趨勢</label>
            </div>
        </div>
    </div>

    <!-- 趨勢圖表 -->
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">碳排放趨勢</h5>
                </div>
                <div class="card-body">
                    <canvas id="carbonTrendChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        
        <!-- 交通工具圓餅圖 -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">交通工具使用</h5>
                </div>
                <div class="card-body">
                    <canvas id="transportPieChart" width="300" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('livewire:navigated', function () {
    initCharts();
});

document.addEventListener('DOMContentLoaded', function () {
    initCharts();
});

Livewire.on('chartDataUpdated', function () {
    initCharts();
});

function initCharts() {
    // 趨勢圖表
    const trendCtx = document.getElementById('carbonTrendChart');
    if (trendCtx) {
        if (window.trendChart) {
            window.trendChart.destroy();
        }
        
        const chartData = @json($chartData);
        
        window.trendChart = new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: chartData.map(item => item.label),
                datasets: [{
                    label: '碳排放量 (kg)',
                    data: chartData.map(item => item.value),
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: '碳排放量 (kg)'
                        }
                    }
                }
            }
        });
    }
    
    // 圓餅圖
    const pieCtx = document.getElementById('transportPieChart');
    if (pieCtx) {
        if (window.pieChart) {
            window.pieChart.destroy();
        }
        
        const transportData = @json($transportData);
        
        window.pieChart = new Chart(pieCtx, {
            type: 'pie',
            data: {
                labels: transportData.map(item => item.label),
                datasets: [{
                    data: transportData.map(item => item.value),
                    backgroundColor: [
                        '#FF6384',
                        '#36A2EB',
                        '#FFCE56',
                        '#4BC0C0',
                        '#9966FF'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
}
</script>