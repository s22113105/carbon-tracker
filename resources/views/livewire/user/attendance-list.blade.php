<div>
    <!-- 篩選器 -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">篩選條件</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label">日期</label>
                    <input type="date" class="form-control" wire:model.live="dateFilter">
                </div>
                <div class="col-md-3">
                    <label class="form-label">類型</label>
                    <select class="form-select" wire:model.live="typeFilter">
                        <option value="">全部</option>
                        <option value="to_work">上班</option>
                        <option value="from_work">下班</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">交通工具</label>
                    <select class="form-select" wire:model.live="transportFilter">
                        <option value="">全部</option>
                        <option value="walking">步行</option>
                        <option value="bus">公車</option>
                        <option value="mrt">捷運</option>
                        <option value="car">汽車</option>
                        <option value="motorcycle">機車</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-outline-secondary" wire:click="clearFilters">
                        <i class="fas fa-times me-1"></i>清除篩選
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 記錄列表 -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">打卡記錄</h5>
            <span class="badge bg-info">共 {{ $trips->total() }} 筆記錄</span>
        </div>
        <div class="card-body">
            @if($trips->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>日期</th>
                                <th>時間</th>
                                <th>類型</th>
                                <th>距離</th>
                                <th>時長</th>
                                <th>交通工具</th>
                                <th>平均速度</th>
                                <th>碳排放</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($trips as $trip)
                                @php
                                    $duration = $trip->start_time->diffInMinutes($trip->end_time);
                                    $speed = $duration > 0 ? round(($trip->distance / ($duration / 60)), 1) : 0;
                                    $carbonEmission = $trip->carbonEmission;
                                @endphp
                                <tr>
                                    <td>{{ $trip->start_time->format('Y-m-d') }}</td>
                                    <td>{{ $trip->start_time->format('H:i') }}</td>
                                    <td>
                                        <span class="badge bg-{{ $trip->trip_type === 'to_work' ? 'success' : 'warning' }}">
                                            {{ $trip->trip_type === 'to_work' ? '上班' : '下班' }}
                                        </span>
                                    </td>
                                    <td>{{ number_format($trip->distance, 1) }} km</td>
                                    <td>{{ $duration }} 分鐘</td>
                                    <td>
                                        <span class="badge bg-primary">
                                            {{ $transportService->getTransportModeLabel($trip->transport_mode) }}
                                        </span>
                                    </td>
                                    <td>{{ $speed }} km/h</td>
                                    <td>
                                        @if($carbonEmission)
                                            <span class="text-{{ $carbonEmission->co2_emission > 1 ? 'danger' : 'success' }}">
                                                {{ number_format($carbonEmission->co2_emission, 2) }} kg
                                            </span>
                                        @else
                                            <span class="text-muted">未計算</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- 分頁 -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div>
                        顯示第 {{ $trips->firstItem() }} 到 {{ $trips->lastItem() }} 筆，共 {{ $trips->total() }} 筆記錄
                    </div>
                    <div>
                        {{ $trips->links() }}
                    </div>
                </div>
            @else
                <div class="text-center py-5">
                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">沒有找到符合條件的記錄</h5>
                    <p class="text-muted">請調整篩選條件或檢查是否有打卡資料</p>
                </div>
            @endif
        </div>
    </div>
</div>