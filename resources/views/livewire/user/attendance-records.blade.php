<div class="card">
    <div class="card-header">
        <h5 class="mb-0">近期打卡記錄</h5>
    </div>
    <div class="card-body">
        @if(count($recentAttendance) > 0)
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>日期</th>
                            <th>時間</th>
                            <th>類型</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentAttendance as $record)
                        <tr>
                            <td>{{ $record['date'] }}</td>
                            <td>{{ $record['time'] }}</td>
                            <td>
                                <span class="badge bg-{{ $record['type'] === '上班打卡' ? 'success' : 'warning' }}">
                                    {{ $record['type'] }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center text-muted py-3">
                <i class="fas fa-clock fa-2x mb-2"></i>
                <p>尚無打卡記錄</p>
            </div>
        @endif
    </div>
</div>