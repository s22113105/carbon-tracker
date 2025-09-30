<div class="container-fluid">
    <div class="row">
        <!-- 左側控制面板 -->
        <div class="col-md-3">
            <div class="card">
                <div class="card-header">
                    <h5>路線控制</h5>
                </div>
                <div class="card-body">
                    <!-- 日期選擇 -->
                    <div class="mb-3">
                        <label class="form-label">選擇日期</label>
                        <input type="date" class="form-control" wire:model.live="selectedDate">
                    </div>
                    
                    <!-- 行程列表 -->
                    <div class="mb-3">
                        <label class="form-label">行程列表</label>
                        <div class="list-group">
                            @foreach($trips as $trip)
                            <button wire:click="selectTrip({{ $trip['id'] }})" 
                                class="list-group-item list-group-item-action @if($selectedTrip == $trip['id']) active @endif">
                                <div class="d-flex justify-content-between">
                                    <span>
                                        <i class="fas fa-{{ $trip['trip_type'] === 'to_work' ? 'sign-in-alt' : 'sign-out-alt' }}"></i>
                                        {{ $trip['trip_type_text'] }}
                                    </span>
                                    <span>{{ $trip['start_time'] }} - {{ $trip['end_time'] }}</span>
                                </div>
                                <small class="d-block">
                                    {{ $trip['transport_mode_text'] }} | {{ $trip['distance'] }} km | {{ $trip['duration_minutes'] }} 分鐘
                                </small>
                            </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 右側地圖 -->
        <div class="col-md-9">
            <div class="card">
                <div class="card-header">
                    <h5>通勤路線地圖</h5>
                </div>
                <div class="card-body p-0">
                    <div id="map" style="height: 600px;" wire:ignore></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 初始化地圖（中心點在路線中間）
    const map = L.map('map').setView([22.8109, 120.3926], 13);
    
    // 添加圖層
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
    let routeLayer = null;
    let markersLayer = L.layerGroup().addTo(map);
    
    // 監聽行程選擇事件
    Livewire.on('tripSelected', (data) => {
        // 清除舊路線
        if (routeLayer) {
            map.removeLayer(routeLayer);
        }
        markersLayer.clearLayers();
        
        // 繪製新路線
        if (data[0].gpsPoints && data[0].gpsPoints.length > 0) {
            const points = data[0].gpsPoints.map(p => [p.lat, p.lng]);
            
            // 繪製路線
            routeLayer = L.polyline(points, {
                color: '#007bff',
                weight: 4,
                opacity: 0.8
            }).addTo(map);
            
            // 添加起點標記
            L.marker(points[0], {
                icon: L.divIcon({
                    html: '<div style="background: green; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">起</div>',
                    iconSize: [30, 30]
                })
            }).addTo(markersLayer);
            
            // 添加終點標記
            L.marker(points[points.length - 1], {
                icon: L.divIcon({
                    html: '<div style="background: red; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">終</div>',
                    iconSize: [30, 30]
                })
            }).addTo(markersLayer);
            
            // 調整地圖視野
            map.fitBounds(routeLayer.getBounds());
        }
    });
    
    // 監聽地圖重置事件
    Livewire.on('mapReset', () => {
        if (routeLayer) {
            map.removeLayer(routeLayer);
            routeLayer = null;
        }
        markersLayer.clearLayers();
        map.setView([22.8109, 120.3926], 13);
    });
});
</script>