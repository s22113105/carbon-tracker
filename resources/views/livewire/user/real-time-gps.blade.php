<div>
    <div wire:poll.3s="refreshData">
        <!-- 設備狀態卡片 -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">ESP32 設備狀態</h5>
                <span class="badge bg-{{ $isOnline ? 'success' : 'secondary' }}">
                    {{ $isOnline ? '在線' : '離線' }}
                </span>
            </div>
            <div class="card-body">
                @if($latestGps)
                    <div class="row">
                        <div class="col-md-3">
                            <h6>最新位置</h6>
                            <p class="mb-0">緯度: {{ number_format($latestGps['latitude'], 6) }}</p>
                            <p class="mb-0">經度: {{ number_format($latestGps['longitude'], 6) }}</p>
                        </div>
                        <div class="col-md-3">
                            <h6>移動速度</h6>
                            <p class="mb-0">{{ number_format($latestGps['speed'], 1) }} km/h</p>
                        </div>
                        <div class="col-md-3">
                            <h6>最後更新</h6>
                            <p class="mb-0">{{ \Carbon\Carbon::parse($latestGps['recorded_at'])->format('H:i:s') }}</p>
                            <small class="text-muted">{{ \Carbon\Carbon::parse($latestGps['recorded_at'])->diffForHumans() }}</small>
                        </div>
                        <div class="col-md-3">
                            <h6>連接狀態</h6>
                            <div class="d-flex align-items-center">
                                <div class="spinner-grow spinner-grow-sm text-{{ $isOnline ? 'success' : 'secondary' }} me-2" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                {{ $isOnline ? 'ESP32 連線中' : 'ESP32 離線' }}
                            </div>
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

        <!-- 最近行程記錄 -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">最近 GPS 記錄</h5>
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
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($gps['recorded_at'])->format('H:i:s') }}</td>
                                        <td>{{ number_format($gps['latitude'], 6) }}</td>
                                        <td>{{ number_format($gps['longitude'], 6) }}</td>
                                        <td>{{ number_format($gps['speed'], 1) }} km/h</td>
                                        <td><small class="text-muted">{{ $gps['time_ago'] }}</small></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted">暫無記錄</p>
                @endif
            </div>
        </div>

        <!-- 最近行程記錄 -->
        @if(count($recentTrips) > 0)
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">最近行程記錄</h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    @foreach($recentTrips as $trip)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6>{{ $trip['trip_type'] === 'to_work' ? '上班' : ($trip['trip_type'] === 'from_work' ? '下班' : '其他') }}</h6>
                                    <small>{{ \Carbon\Carbon::parse($trip['start_time'])->format('Y-m-d H:i') }}</small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-info">{{ $trip['transport_mode'] ?? '未知' }}</span>
                                    @if($trip['distance'])
                                        <p class="mb-0"><small>{{ number_format($trip['distance'], 2) }} km</small></p>
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
</div>

<style>
.status-indicator {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}
</style>