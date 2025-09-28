<div>
    <div wire:poll.3s="refreshData">
        <!-- 設備狀態卡片 -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">ESP32 設備狀態</h5>
                <div>
                    @if($isOnline)
                        <span class="badge bg-success">
                            <i class="fas fa-circle text-white me-1"></i> 在線
                        </span>
                    @else
                        <span class="badge bg-secondary">
                            <i class="fas fa-circle text-white me-1"></i> 離線
                        </span>
                    @endif
                </div>
            </div>
            <div class="card-body">
                @if($latestGps)
                    <div class="row">
                        <div class="col-md-3">
                            <h6><i class="fas fa-map-marker-alt me-1"></i> 最新位置</h6>
                            <p class="mb-0">緯度: {{ number_format($latestGps['latitude'], 6) }}</p>
                            <p class="mb-0">經度: {{ number_format($latestGps['longitude'], 6) }}</p>
                        </div>
                        <div class="col-md-3">
                            <h6><i class="fas fa-tachometer-alt me-1"></i> 移動速度</h6>
                            <p class="mb-0 fs-4">{{ number_format($latestGps['speed'], 1) }} <small>km/h</small></p>
                        </div>
                        <div class="col-md-3">
                            <h6><i class="fas fa-clock me-1"></i> 最後更新</h6>
                            <p class="mb-0">{{ \Carbon\Carbon::parse($latestGps['recorded_at'])->format('Y-m-d') }}</p>
                            <p class="mb-0 fw-bold">{{ \Carbon\Carbon::parse($latestGps['recorded_at'])->format('H:i:s') }}</p>
                            <small class="text-muted">{{ \Carbon\Carbon::parse($latestGps['recorded_at'])->diffForHumans() }}</small>
                        </div>
                        <div class="col-md-3">
                            <h6><i class="fas fa-battery-three-quarters me-1"></i> 電池狀態</h6>
                            @if($deviceStatus && $deviceStatus->battery_level !== null)
                                <div class="progress" style="height: 25px;">
                                    <div class="progress-bar 
                                        @if($deviceStatus->battery_level > 60) bg-success
                                        @elseif($deviceStatus->battery_level > 30) bg-warning
                                        @else bg-danger
                                        @endif" 
                                        role="progressbar" 
                                        style="width: {{ $deviceStatus->battery_level }}%">
                                        {{ $deviceStatus->battery_level }}%
                                    </div>
                                </div>
                            @else
                                <p class="text-muted">無電池資訊</p>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        尚未收到 ESP32 設備的 GPS 資料，請確認設備已連線並正在傳送資料。
                    </div>
                @endif
            </div>
        </div>

        <!-- 最近 GPS 記錄 -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>最近 GPS 記錄</h5>
            </div>
            <div class="card-body">
                @if(count($lastFiveGps) > 0)
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>時間</th>
                                    <th>緯度</th>
                                    <th>經度</th>
                                    <th>速度</th>
                                    <th>更新時間</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($lastFiveGps as $gps)
                                    <tr @if($loop->first) class="table-active" @endif>
                                        <td>
                                            <span class="fw-bold">{{ $gps['formatted_time'] }}</span>
                                        </td>
                                        <td>{{ number_format($gps['latitude'], 6) }}</td>
                                        <td>{{ number_format($gps['longitude'], 6) }}</td>
                                        <td>
                                            <span class="badge bg-info">
                                                {{ number_format($gps['speed'], 1) }} km/h
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ $gps['time_ago'] }}</small>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted text-center">暫無記錄</p>
                @endif
            </div>
        </div>

        <!-- 最近行程記錄 -->
        @if(count($recentTrips) > 0)
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-route me-2"></i>最近行程記錄</h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    @foreach($recentTrips as $trip)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="mb-1">
                                        @if($trip['trip_type'] === 'to_work')
                                            <i class="fas fa-building me-1"></i> 上班
                                        @elseif($trip['trip_type'] === 'from_work')
                                            <i class="fas fa-home me-1"></i> 下班
                                        @else
                                            <i class="fas fa-map me-1"></i> 其他
                                        @endif
                                    </h6>
                                    <small>{{ \Carbon\Carbon::parse($trip['start_time'])->format('Y-m-d H:i') }}</small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-primary">{{ $trip['transport_mode'] ?? '未知' }}</span>
                                    @if($trip['distance'])
                                        <p class="mb-0 mt-1">
                                            <small>{{ number_format($trip['distance'], 2) }} km</small>
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
    </div>

    <style>
@keyframes pulse {
    0% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(1.1);
        opacity: 0.7;
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

.badge .fa-circle {
    animation: pulse 2s infinite;
}
</style>
</div>

