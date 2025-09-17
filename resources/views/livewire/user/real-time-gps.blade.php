<div>
    <div wire:poll.5s="refreshData">
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
                                <div class="status-indicator bg-{{ $isOnline ? 'success' : 'secondary' }} me-2"></div>
                                {{ $isOnline ? 'GPS 裝置在線' : 'GPS 裝置離線' }}
                            </div>
                        </div>
                    </div>
                @else
                    <div class="text-center py-4">
                        <i class="fas fa-satellite-dish fa-3x text-muted mb-3"></i>
                        <h6 class="text-muted">尚未收到 GPS 資料</h6>
                        <p class="text-muted">請確認 ESP32 設備已連接並正常運作</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- 即時行程監控 -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">最近行程記錄</h5>
            </div>
            <div class="card-body">
                @if(count($recentTrips) > 0)
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>開始時間</th>
                                    <th>結束時間</th>
                                    <th>距離</th>
                                    <th>交通工具</th>
                                    <th>碳排放</th>
                                    <th>狀態</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentTrips as $trip)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($trip['start_time'])->format('H:i') }}</td>
                                        <td>
                                            @if($trip['end_time'])
                                                {{ \Carbon\Carbon::parse($trip['end_time'])->format('H:i') }}
                                            @else
                                                <span class="badge bg-warning">進行中</span>
                                            @endif
                                        </td>
                                        <td>{{ number_format($trip['distance'] ?? 0, 1) }} km</td>
                                        <td>
                                            @php
                                                $transportLabels = [
                                                    'walking' => '步行',
                                                    'bus' => '公車',
                                                    'mrt' => '捷運',
                                                    'car' => '汽車',
                                                    'motorcycle' => '機車',
                                                    'unknown' => '未知'
                                                ];
                                            @endphp
                                            {{ $transportLabels[$trip['transport_mode']] ?? '未知' }}
                                        </td>
                                        <td>
                                            @if($trip['carbon_emission'])
                                                {{ number_format($trip['carbon_emission']['co2_emission'], 2) }} kg
                                            @else
                                                --
                                            @endif
                                        </td>
                                        <td>
                                            @if($trip['end_time'])
                                                <span class="badge bg-success">已完成</span>
                                            @else
                                                <span class="badge bg-primary">進行中</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4">
                        <i class="fas fa-route fa-3x text-muted mb-3"></i>
                        <h6 class="text-muted">尚無行程記錄</h6>
                    </div>
                @endif
            </div>
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
</div>