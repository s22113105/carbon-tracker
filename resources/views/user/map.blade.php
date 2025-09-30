@extends('layouts.dashboard')

@section('title', '通勤路線地圖')

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
        <a class="nav-link active" href="{{ route('user.map') }}">
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
        <a class="nav-link" href="{{ route('user.carbon.aiAnalyses') }}">
            <i class="fas fa-lightbulb me-2"></i>AI 碳排放分析
        </a>
    </li>
@endsection

@section('content')
<div class="row mb-4">
    <div class="col-md-12">
        <h1>通勤路線地圖</h1>
        <p class="text-muted">查看您的 GPS 軌跡和通勤路線，分析行程模式</p>
    </div>
</div>

@livewire('user.route-map')

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 初始化地圖
    const map = L.map('map').setView([22.7839, 120.4051], 13); // 中心點在路線中間
    
    // 使用 OpenStreetMap 圖層
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);
    
    // 定義標記圖標
    const startIcon = L.divIcon({
        html: '<div style="background: #28a745; color: white; border-radius: 50%; width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 3px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.3);">起</div>',
        iconSize: [35, 35],
        iconAnchor: [17, 17]
    });
    
    const endIcon = L.divIcon({
        html: '<div style="background: #dc3545; color: white; border-radius: 50%; width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 3px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.3);">終</div>',
        iconSize: [35, 35],
        iconAnchor: [17, 17]
    });
    
    // 樹德科技大學座標
    const stuStart = [22.7632, 120.3757];
    const mcdonaldEnd = [22.727122, 120.326630];
    
    let routeControl = null;
    let actualRouteLayer = null;
    let markersLayer = L.layerGroup().addTo(map);
    
    // 監聽 Livewire 事件
    Livewire.on('tripSelected', (data) => {
        // 清除舊的路線
        if (routeControl) {
            map.removeControl(routeControl);
            routeControl = null;
        }
        if (actualRouteLayer) {
            map.removeLayer(actualRouteLayer);
        }
        markersLayer.clearLayers();
        
        if (data[0].gpsPoints && data[0].gpsPoints.length > 0) {
            const points = data[0].gpsPoints.map(p => [p.lat, p.lng]);
            
            // 繪製實際GPS軌跡（藍色實線）
            actualRouteLayer = L.polyline(points, {
                color: '#007bff',
                weight: 5,
                opacity: 0.8,
                dashArray: null
            }).addTo(map);
            
            // 添加起點標記
            L.marker(points[0], {
                icon: startIcon
            }).bindPopup('<strong>起點</strong><br>樹德科技大學').addTo(markersLayer);
            
            // 添加終點標記
            L.marker(points[points.length - 1], {
                icon: endIcon
            }).bindPopup('<strong>終點</strong><br>麥當勞楠梓餐廳').addTo(markersLayer);
            
            // 添加導航路線（灰色虛線作為參考）
            routeControl = L.Routing.control({
                waypoints: [
                    L.latLng(points[0][0], points[0][1]),
                    L.latLng(points[points.length - 1][0], points[points.length - 1][1])
                ],
                routeWhileDragging: false,
                addWaypoints: false,
                createMarker: function() { return null; }, // 不顯示默認標記
                lineOptions: {
                    styles: [{ 
                        color: '#6c757d', 
                        opacity: 0.4, 
                        weight: 4,
                        dashArray: '10, 10'
                    }]
                },
                show: false // 不顯示路線說明面板
            }).addTo(map);
            
            // 調整視野以包含整條路線
            map.fitBounds(actualRouteLayer.getBounds(), {
                padding: [50, 50]
            });
            
            // 顯示路線資訊
            const tripInfo = data[0].tripInfo || {};
            if (tripInfo.distance || tripInfo.duration) {
                const infoHtml = `
                    <div style="position: absolute; top: 10px; right: 10px; background: white; padding: 10px; border-radius: 5px; box-shadow: 0 2px 6px rgba(0,0,0,0.2); z-index: 1000;">
                        <strong>行程資訊</strong><br>
                        距離: ${tripInfo.distance || '--'} km<br>
                        時間: ${tripInfo.duration || '--'} 分鐘<br>
                        交通工具: 機車
                    </div>
                `;
                document.getElementById('map').insertAdjacentHTML('beforeend', infoHtml);
            }
        }
    });
    
    // 監聽地圖重置事件
    Livewire.on('mapReset', () => {
        if (routeControl) {
            map.removeControl(routeControl);
            routeControl = null;
        }
        if (actualRouteLayer) {
            map.removeLayer(actualRouteLayer);
        }
        markersLayer.clearLayers();
        
        // 重置視野到預設位置
        map.setView([22.7839, 120.4051], 13);
        
        // 清除資訊面板
        const infoPanel = document.querySelector('#map > div[style*="position: absolute"]');
        if (infoPanel) {
            infoPanel.remove();
        }
    });
    
    // 初始顯示起點和終點
    L.marker(stuStart, {
        icon: startIcon
    }).bindPopup('<strong>樹德科技大學</strong><br>起點').addTo(markersLayer);
    
    L.marker(mcdonaldEnd, {
        icon: endIcon
    }).bindPopup('<strong>麥當勞楠梓餐廳</strong><br>終點').addTo(markersLayer);
});
</script>
@endsection