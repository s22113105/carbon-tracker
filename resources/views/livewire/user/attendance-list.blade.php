<div>
    <!-- 月份選擇器 -->
    <div class="card mb-4">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-0">打卡記錄</h5>
                </div>
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text">選擇月份</span>
                        <input type="month" class="form-control" wire:model.live="selectedMonth">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 統計卡片 -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h6 class="text-muted">工作天數</h6>
                    <h3>{{ $statistics['work_days'] ?? 0 }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h6 class="text-muted">總工時</h6>
                    <h3>{{ $statistics['total_hours'] ?? 0 }} <small>小時</small></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h6 class="text-muted">總里程</h6>
                    <h3>{{ $statistics['total_distance'] ?? 0 }} <small>km</small></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h6 class="text-muted">碳排放</h6>
                    <h3>{{ $statistics['total_emission'] ?? 0 }} <small>kg</small></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- 打卡記錄表格 -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">詳細記錄</h5>
        </div>
        <div class="card-body">
            @if(count($attendanceRecords) > 0)
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>日期</th>
                            <th>星期</th>
                            <th>類型</th>
                            <th>打卡時間</th>
                            <th>到達時間</th>
                            <th>行程時間</th>
                            <th>距離</th>
                            <th>交通工具</th>
                            <th>碳排放</th>
                            <th>起點</th>
                            <th>終點</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($attendanceRecords as $record)
                        <tr>
                            <td>{{ $record['date'] }}</td>
                            <td>{{ $record['day_of_week'] }}</td>
                            <td>
                                <span class="badge {{ $record['type'] === 'to_work' ? 'bg-success' : 'bg-info' }}">
                                    {{ $record['type_text'] }}
                                </span>
                            </td>
                            <td>{{ $record['check_time'] }}</td>
                            <td>{{ $record['arrival_time'] }}</td>
                            <td>{{ $record['duration_minutes'] }} 分鐘</td>
                            <td>{{ $record['distance'] }} km</td>
                            <td>
                                <span class="badge bg-secondary">
                                    {{ $record['transport_mode_text'] }}
                                </span>
                            </td>
                            <td>{{ $record['carbon_emission'] }} kg</td>
                            <td>{{ $record['start_location'] }}</td>
                            <td>{{ $record['end_location'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <p class="text-center text-muted">本月無打卡記錄</p>
            @endif
        </div>
    </div>
</div>