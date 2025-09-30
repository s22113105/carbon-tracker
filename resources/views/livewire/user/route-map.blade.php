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
