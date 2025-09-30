<div wire:poll.5s="refreshData">
    <div class="row">
        <!-- 狀態卡片 -->
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">連線狀態</h6>
                    <div class="d-flex align-items-center">
                        <span class="badge {{ $isOnline ? 'bg-success' : 'bg-danger' }} me-2">
                            <i class="fas fa-circle"></i> {{ $isOnline ? '在線' : '離線' }}
                        </span>
                        @if($latestGps)
                        <small>{{ \Carbon\Carbon::parse($latestGps['recorded_at'])->diffForHumans() }}</small>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 速度卡片 -->
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">當前速度</h6>
                    <h3 class="mb-0">{{ number_format($currentSpeed, 1) }} <small>km/h</small></h3>
                </div>
            </div>
        </div>
        
        <!-- 今日里程 -->
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">今日里程</h6>
                    <h3 class="mb-0">{{ number_format($totalDistanceToday, 2) }} <small>km</small></h3>
                </div>
            </div>
        </div>
        
        <!-- 今日碳排放 -->
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">今日碳排放</h6>
                    <h3 class="mb-0">{{ $carbonEmissionToday }} <small>kg CO₂</small></h3>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 當前位置資訊 -->
    <div class="row mt-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>當前位置資訊</h5>
                </div>
                <div class="card-body">
                    @if($latestGps)
                    <div class="row">
                        <div class="col-md-4">
                            <strong>位置：</strong> {{ $currentLocation }}
                        </div>
                        <div class="col-md-4">
                            <strong>經緯度：</strong> 
                            {{ number_format($latestGps['latitude'], 6) }}, 
                            {{ number_format($latestGps['longitude'], 6) }}
                        </div>
                        <div class="col-md-4">
                            <strong>更新時間：</strong> 
                            {{ \Carbon\Carbon::parse($latestGps['recorded_at'])->format('Y-m-d H:i:s') }}
                        </div>
                    </div>
                    @else
                    <p class="text-muted">暫無GPS資料</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
