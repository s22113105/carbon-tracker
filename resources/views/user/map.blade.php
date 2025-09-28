<div>
    <!-- 載入指示器 -->
    <div wire:loading class="d-flex justify-content-center mb-3">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">載入中...</span>
        </div>
    </div>

    <!-- 成功/錯誤訊息 -->
    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- ESP32即時狀態顯示 -->
    @if(!empty($realTimeStatus['device_status']))
        <div class="row mb-3">
            <div class="col-12">
                <div class="card border-{{ $realTimeStatus['device_status']['status'] === 'online' ? 'success' : 'warning' }}">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-microchip me-2 text-{{ $realTimeStatus['device_status']['status'] === 'online' ? 'success' : 'warning' }}"></i>
                                <strong>ESP32設備狀態：</strong>
                                <span class="badge bg-{{ $realTimeStatus['device_status']['status'] === 'online' ? 'success' : 'warning' }} ms-2">
                                    {{ $realTimeStatus['device_status']['last_seen'] }}
                                </span>
                            </div>
                            @if(!empty($realTimeStatus['latest_gps']))
                                <small class="text-muted">
                                    最後位置更新：{{ $realTimeStatus['latest_gps']['minutes_ago'] }} 分鐘前
                                </small>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- 控制面板 -->
    <div class="row mb-4">
        <!-- 日期選擇和控制 -->
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>日期控制</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="dateSelect" class="form-label">選擇日期</label>
                        <input type="date" 
                               id="dateSelect"
                               class="form-control" 
                               wire:model.live="selectedDate" 
                               max="{{ date('Y-m-d') }}">
                    </div>
                    
                    <!-- ESP32控制按鈕 -->
                    <div class="d-grid gap-2">
                        <button class="btn btn-success btn-sm" 
                                wire:click="syncEsp32Data" 
                                wire:loading.attr="disabled">
                            <i class="fas fa-sync me-1"></i>同步ESP32資料
                        </button>
                        
                        <button class="btn btn-info btn-sm" 
                                wire:click="processBatchData" 
                                wire:loading.attr="disabled">
                            <i class="fas fa-database me-1"></i>處理離線資料
                        </button>
                        
                        <button class="btn btn-outline-info btn-sm" 
                                wire:click="analyzeTrips" 
                                wire:loading.attr="disabled">
                            <i class="fas fa-chart-line me-1"></i>重新分析行程
                        </button>
                        
                        <button class="btn btn-outline-warning btn-sm" 
                                wire:click="resetMap">
                            <i class="fas fa-redo me-1"></i>重置地圖
                        </button>
                        
                        @if($selectedDate === date('Y-m-d'))
                            <button class="btn btn-outline-danger btn-sm" 
                                    wire:click="clearTodayData" 
                                    onclick="return confirm('確定要清除今日所有資料嗎？此操作無法復原。')"
                                    wire:loading.attr="disabled">
                                <i class="fas fa-trash me-1"></i>清除今日資料
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 統計資料 -->
        <div class="col-md-8">
            <div class="card h-100">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>當日統計</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-2">
                            <div class="border rounded p-2">
                                <h6 class="text-success mb-1">{{ $analyticsData['esp32_points'] ?? 0 }}</h6>
                                <small class="text-muted">ESP32原始</small>
                            </div>
                        </div>
                        <div class="col-2">
                            <div class="border rounded p-2">
                                <h6 class="text-primary mb-1">{{ $analyticsData['gps_points'] ?? 0 }}</h6>
                                <small class="text-muted">同步GPS點</small>
                            </div>
                        </div>
                        <div class="col-2">
                            <div class="border rounded p-2">
                                <h6 class="text-info mb-1">{{ $analyticsData['trips_count'] ?? 0 }}</h6>
                                <small class="text-muted">行程數</small>
                            </div>
                        </div>
                        <div class="col-2">
                            <div class="border rounded p-2">
                                <h6 class="text-warning mb-1">{{ $analyticsData['total_distance'] ?? 0 }}km</h6>
                                <small class="text-muted">總距離</small>
                            </div>
                        </div>
                        <div class="col-2">
                            <div class="border rounded p-2">
                                <h6 class="text-danger mb-1">{{ $analyticsData['total_co2'] ?? 0 }}kg</h6>
                                <small class="text-muted">CO₂排放</small>
                            </div>
                        </div>
                        <div class="col-2">
                            <div class="border rounded p-2">
                                <h6 class="text-secondary mb-1">{{ $analyticsData['data_sync_ratio'] ?? 0 }}%</h6>
                                <small class="text-muted">同步率</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 交通工具分布 -->
                    @if(!empty($analyticsData['transport_modes']))
                        <div class="mt-3">
                            <small class="text-muted">交通工具分布：</small>
                            <div class="d-flex flex-wrap gap-1 mt-1">
                                @foreach($analyticsData['transport_modes'] as $mode)
                                    <span class="badge" style="background-color: {{ $mode['color'] }}">
                                        {{ $mode['mode_text'] }} ({{ $mode['count'] }}次, {{ $mode['distance'] }}km)
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- 即時GPS狀態 -->
                    @if(!empty($realTimeStatus['today_stats']))
                        <div class="mt-3 pt-2 border-top">
                            <small class="text-muted d-block">即時狀態：</small>
                            <div class="row text-center mt-1">
                                <div class="col-3">
                                    <small class="text-muted">未處理點數</small>
                                    <div class="fw-bold text-warning">{{ $realTimeStatus['today_stats']['unprocessed_points'] }}</div>
                                </div>
                                <div class="col-3">
                                    <small class="text-muted">首次記錄</small>
                                    <div class="fw-bold">{{ $realTimeStatus['today_stats']['first_record_at'] ? \Carbon\Carbon::parse($realTimeStatus['today_stats']['first_record_at'])->format('H:i') : '--' }}</div>
                                </div>
                                <div class="col-3">
                                    <small class="text-muted">最後記錄</small>
                                    <div class="fw-bold">{{ $realTimeStatus['today_stats']['last_record_at'] ? \Carbon\Carbon::parse($realTimeStatus['today_stats']['last_record_at'])->format('H:i') : '--' }}</div>
                                </div>
                                <div class="col-3">
                                    <small class="text-muted">生成行程</small>
                                    <div class="fw-bold text-success">{{ $realTimeStatus['today_stats']['trips_generated'] }}</div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- 地圖和行程列表 -->
    <div class="row">
        <!-- 行程列表 -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-route me-2"></i>行程列表</h6>
                    @if(count($trips) > 0)
                        <span class="badge bg-info">{{ count($trips) }} 筆</span>
                    @endif
                </div>
                <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                    @if(count($trips) > 0)
                        @foreach($trips as $trip)
                            <div class="trip-item p-3 border-bottom cursor-pointer {{ $selectedTrip == $trip['id'] ? 'bg-primary text-white' : 'bg-light' }}" 
                                 wire:click="selectTrip({{ $trip['id'] }})"
                                 style="cursor: pointer; transition: all 0.2s;">
                                
                                <!-- 行程標題 -->
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-clock me-2"></i>
                                        <strong>{{ $trip['start_time'] }} - {{ $trip['end_time'] }}</strong>
                                    </div>
                                    <div class="d-flex gap-1">
                                        <span class="badge" style="background-color: {{ $trip['color'] }}">
                                            {{ $trip['transport_mode_text'] }}
                                        </span>
                                        @if(isset($trip['data_source']))
                                            <span class="badge bg-{{ $trip['data_source'] === 'ESP32' ? 'success' : 'secondary' }}">
                                                {{ $trip['data_source'] }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                
                                <!-- 行程詳情 -->
                                <div class="row small">
                                    <div class="col-6">
                                        <i class="fas fa-route me-1"></i>
                                        {{ $trip['distance'] }} km
                                    </div>
                                    <div class="col-6">
                                        <i class="fas fa-tachometer-alt me-1"></i>
                                        {{ $trip['avg_speed'] }} km/h
                                    </div>
                                </div>
                                
                                <div class="row small mt-1">
                                    <div class="col-6">
                                        <i class="fas fa-hourglass-half me-1"></i>
                                        {{ $trip['duration_minutes'] }} 分鐘
                                    </div>
                                    <div class="col-6">
                                        <i class="fas fa-leaf me-1"></i>
                                        {{ $trip['co2_emission'] }} kg CO₂
                                    </div>
                                </div>
                                
                                <!-- 行程類型 -->
                                <div class="mt-2">
                                    <span class="badge badge-outline">
                                        <i class="fas fa-tag me-1"></i>{{ $trip['trip_type_text'] }}
                                    </span>
                                </div>
                                
                                <!-- 座標信息（小字顯示） -->
                                <div class="mt-2 small text-muted">
                                    起點: {{ number_format($trip['start_lat'], 4) }}, {{ number_format($trip['start_lng'], 4) }}<br>
                                    終點: {{ number_format($trip['end_lat'], 4) }}, {{ number_format($trip['end_lng'], 4) }}
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="p-4 text-center text-muted">
                            <i class="fas fa-microchip fa-2x mb-3 text-info"></i>
                            <p class="mb-2">沒有找到行程資料</p>
                            <small>
                                @if(($analyticsData['esp32_points'] ?? 0) > 0)
                                    有 {{ $analyticsData['esp32_points'] }} 筆ESP32資料，點擊「同步ESP32資料」來生成行程記錄
                                @elseif(($analyticsData['gps_points'] ?? 0) > 0)
                                    有 {{ $analyticsData['gps_points'] }} 筆GPS資料，點擊「重新分析行程」來生成行程記錄
                                @else
                                    該日期沒有GPS資料，請確認ESP32設備是否正常運作
                                @endif
                            </small>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        
        <!-- 地圖區域 -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-map me-2"></i>通勤路線地圖</h6>
                    @if($selectedTrip)
                        <span class="badge bg-success">已選擇行程 #{{ $selectedTrip }}</span>
                    @else
                        <span class="badge bg-secondary">請選擇行程</span>
                    @endif
                </div>
                <div class="card-body p-0 position-relative">
                    <!-- 地圖容器 -->
                    <div id="map" style="height: 600px; width: 100%;">
                        <!-- 地圖載入提示 -->
                        <div class="map-loading text-center">
                            <div class="spinner-border text-primary mb-2" role="status"></div>
                            <p class="text-muted mb-0">地圖載入中...</p>
                        </div>
                    </div>
                    
                    <!-- 地圖圖例 -->
                    <div class="position-absolute top-0 end-0 m-3 bg-white rounded shadow-sm p-2" style="z-index: 1000;">
                        <small class="text-muted d-block mb-1">圖例</small>
                        <div class="d-flex flex-column gap-1">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle me-2" style="width: 12px; height: 12px; background-color: #28a745;"></div>
                                <small>起點</small>
                            </div>
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle me-2" style="width: 12px; height: 12px; background-color: #dc3545;"></div>
                                <small>終點</small>
                            </div>
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle me-2" style="width: 12px; height: 12px; background-color: #6f42c1;"></div>
                                <small>樹德科大</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 沒有選擇行程時的提示 -->
                    @if(!$selectedTrip && count($trips) > 0)
                        <div class="position-absolute top-50 start-50 translate-middle text-center bg-white rounded shadow p-3" style="z-index: 1000;">
                            <i class="fas fa-hand-pointer fa-2x text-primary mb-2"></i>
                            <p class="mb-0 text-muted">請從左側選擇一個行程<br>來查看詳細路線</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- GPS資料詳細信息（選擇行程時顯示） -->
    @if($selectedTrip && !empty($gpsPoints))
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-satellite me-2"></i>GPS軌跡詳細資訊</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-center border rounded p-2">
                                    <h5 class="text-primary mb-1">{{ count($gpsPoints) }}</h5>
                                    <small class="text-muted">GPS軌跡點</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center border rounded p-2">
                                    <h5 class="text-warning mb-1">
                                        {{ count($gpsPoints) > 0 ? \Carbon\Carbon::parse(end($gpsPoints)['time'])->format('H:i:s') : '--' }}
                                    </h5>
                                    <small class="text-muted">結束時間</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center border rounded p-2">
                                    <h5 class="text-info mb-1">
                                        {{ count($gpsPoints) > 1 ? number_format(collect($gpsPoints)->avg('speed') ?? 0, 1) : '0' }} km/h
                                    </h5>
                                    <small class="text-muted">平均速度</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- GPS點採樣間隔信息 -->
                        @if(count($gpsPoints) > 1)
                            <div class="mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    GPS採樣間隔約 {{ number_format(\Carbon\Carbon::parse($gpsPoints[0]['time'])->diffInSeconds(\Carbon\Carbon::parse($gpsPoints[1]['time'] ?? $gpsPoints[0]['time']))) }} 秒
                                    | 軌跡精度: {{ number_format(collect($gpsPoints)->avg('accuracy') ?? 0, 1) }} 公尺
                                    | 資料來源: ESP32實體設備
                                </small>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

<style>
/* 行程項目懸停效果 */
.trip-item:hover {
    background-color: #e9ecef !important;
    transform: translateX(2px);
}

.trip-item.bg-primary:hover {
    background-color: #0056b3 !important;
    transform: translateX(0px);
}

/* 地圖樣式 */
#map {
    border-radius: 0 0 8px 8px;
    border: 1px solid #dee2e6;
    border-top: none;
}

.custom-marker {
    border: none !important;
    background: transparent !important;
}

/* 地圖控制項樣式 */
.leaflet-control-zoom {
    border: none !important;
    box-shadow: 0 1px 5px rgba(0,0,0,0.15) !important;
}

.leaflet-control-zoom a {
    background-color: #fff !important;
    color: #333 !important;
    border: 1px solid #ccc !important;
}

.leaflet-control-zoom a:hover {
    background-color: #f5f5f5 !important;
}

/* 載入動畫 */
.map-loading {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 1000;
    background-color: rgba(255, 255, 255, 0.95);
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

/* Badge樣式 */
.badge-outline {
    background-color: transparent;
    border: 1px solid currentColor;
}

/* ESP32狀態指示器 */
.card.border-success {
    border-width: 2px !important;
}

.card.border-warning {
    border-width: 2px !important;
}

/* 統計卡片樣式 */
.border.rounded.p-2 {
    transition: all 0.2s;
}

.border.rounded.p-2:hover {
    background-color: #f8f9fa;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* 響應式設計 */
@media (max-width: 768px) {
    #map {
        height: 400px !important;
    }
    
    .trip-item {
        font-size: 0.9rem;
    }
    
    .col-2 {
        margin-bottom: 0.5rem;
    }
}

/* 游標指針 */
.cursor-pointer {
    cursor: pointer;
}

/* 選中的行程項目特殊樣式 */
.trip-item.bg-primary {
    border-left: 4px solid #fff;
}

/* ESP32徽章樣式 */
.badge.bg-success {
    background-color: #28a745 !important;
}

.badge.bg-secondary {
    background-color: #6c757d !important;
}

/* 即時狀態區域 */
.border-top {
    border-color: #dee2e6 !important;
}

/* 按鈕載入狀態 */
button[wire\:loading\.attr="disabled"] {
    position: relative;
}

button[wire\:loading\.attr="disabled"]:disabled {
    opacity: 0.7;
}

/* 動畫效果 */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.text-success {
    animation: pulse 2s infinite;
}

/* 數據同步率指示器 */
.text-secondary h6 {
    font-weight: bold;
}

/* 地圖圖例樣式 */
.position-absolute.top-0.end-0 {
    background-color: rgba(255, 255, 255, 0.95) !important;
    backdrop-filter: blur(5px);
}
</style>
</div>

