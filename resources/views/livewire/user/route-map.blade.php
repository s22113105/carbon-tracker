<div>
    <!-- 成功訊息 -->
    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- 日期選擇和控制按鈕 -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">選擇日期</h6>
                </div>
                <div class="card-body">
                    <input type="date" class="form-control mb-3" wire:model.live="selectedDate" max="{{ date('Y-m-d') }}">
                    
                    <!-- 控制按鈕 -->
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-warning btn-sm" wire:click="resetMap">
                            <i class="fas fa-redo me-1"></i>重置地圖
                        </button>
                        
                        @if($selectedDate === date('Y-m-d'))
                            <button class="btn btn-outline-danger btn-sm" 
                                    wire:click="clearTodayData" 
                                    onclick="return confirm('確定要清除今日所有資料嗎？此操作無法復原。')">
                                <i class="fas fa-trash me-1"></i>清除今日資料
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">當日行程</h6>
                    @if(count($trips) > 0)
                        <span class="badge bg-info">{{ count($trips) }} 筆行程</span>
                    @endif
                </div>
                <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                    @if(count($trips) > 0)
                        @foreach($trips as $trip)
                            <div class="trip-item mb-2 p-2 border rounded {{ $selectedTrip == $trip['id'] ? 'bg-primary text-white' : 'bg-light' }}" 
                                 style="cursor: pointer;" wire:click="selectTrip({{ $trip['id'] }})">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>{{ $trip['start_time'] }} - {{ $trip['end_time'] }}</strong>
                                        <br>
                                        <small>
                                            @php
                                                $transportLabels = [
                                                    'walking' => '步行',
                                                    'bus' => '公車',
                                                    'mrt' => '捷運',
                                                    'car' => '汽車',
                                                    'motorcycle' => '機車',
                                                    'unknown' => '未知'
                                                ];
                                                $typeLabels = [
                                                    'to_work' => '上班',
                                                    'from_work' => '下班',
                                                    'other' => '其他'
                                                ];
                                            @endphp
                                            {{ $transportLabels[$trip['transport_mode']] ?? '未知' }} | 
                                            {{ $typeLabels[$trip['trip_type']] ?? '其他' }}
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <div>{{ number_format($trip['distance'], 1) }} km</div>
                                        <small>{{ number_format($trip['co2_emission'], 2) }} kg CO2</small>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-calendar-times fa-2x mb-2"></i>
                            <p>該日期沒有行程記錄</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- 地圖顯示 -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">通勤路線地圖</h6>
            @if($selectedTrip)
                <small class="text-muted">已選擇行程 ID: {{ $selectedTrip }}</small>
            @endif
        </div>
        <div class="card-body">
            <div id="map" style="height: 500px; width: 100%;"></div>
        </div>
    </div>
</div>