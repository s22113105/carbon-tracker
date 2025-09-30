<?php
// ====================================
// 3. 修復 AttendanceList Livewire 組件
// app/Livewire/User/AttendanceList.php
// ====================================

namespace App\Livewire\User;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceList extends Component
{
    public $attendanceRecords = [];
    public $selectedMonth;
    public $statistics = [];
    
    public function mount()
    {
        $this->selectedMonth = Carbon::now()->format('Y-m');
        $this->loadAttendanceRecords();
    }
    
    public function updatedSelectedMonth()
    {
        $this->loadAttendanceRecords();
    }
    
    public function loadAttendanceRecords()
    {
        $userId = auth()->id();
        $startDate = Carbon::parse($this->selectedMonth . '-01')->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        
        // 獲取行程記錄
        $trips = DB::table('trips')
            ->where('user_id', $userId)
            ->whereBetween('start_time', [$startDate, $endDate])
            ->whereIn('trip_type', ['to_work', 'from_work'])
            ->orderBy('start_time', 'desc')
            ->get();
        
        $this->attendanceRecords = [];
        $workDays = 0;
        $totalWorkHours = 0;
        $totalDistance = 0;
        $totalEmission = 0;
        
        foreach ($trips as $trip) {
            $startTime = Carbon::parse($trip->start_time);
            $endTime = Carbon::parse($trip->end_time);
            
            // 使用 duration 欄位或計算時間差
            $duration = $trip->duration ?? $startTime->diffInSeconds($endTime);
            $durationMinutes = round($duration / 60);
            
            // 獲取碳排放
            $emission = 0;
            if (DB::getSchemaBuilder()->hasTable('carbon_emissions')) {
                $emission = DB::table('carbon_emissions')
                    ->where('trip_id', $trip->id)
                    ->value('carbon_amount') ?? 0;
            }
            
            $record = [
                'id' => $trip->id,
                'date' => $startTime->format('Y-m-d'),
                'day_of_week' => $this->getDayOfWeekText($startTime->dayOfWeek),
                'type' => $trip->trip_type,
                'type_text' => $trip->trip_type === 'to_work' ? '上班' : '下班',
                'check_time' => $startTime->format('H:i:s'),
                'arrival_time' => $endTime->format('H:i:s'),
                'duration_minutes' => $durationMinutes,
                'distance' => round($trip->distance, 2),
                'transport_mode' => $trip->transport_mode,
                'transport_mode_text' => $this->getTransportModeText($trip->transport_mode),
                'carbon_emission' => round($emission, 2),
                'start_location' => $this->getLocationName($trip->start_latitude, $trip->start_longitude),
                'end_location' => $this->getLocationName($trip->end_latitude, $trip->end_longitude),
            ];
            
            $this->attendanceRecords[] = $record;
            
            // 統計
            if ($trip->trip_type === 'to_work') {
                $workDays++;
            }
            $totalWorkHours += $durationMinutes / 60;
            $totalDistance += $trip->distance;
            $totalEmission += $emission;
        }
        
        // 計算統計資料
        $this->statistics = [
            'work_days' => $workDays,
            'total_hours' => round($totalWorkHours, 1),
            'avg_hours_per_day' => $workDays > 0 ? round($totalWorkHours / $workDays, 1) : 0,
            'total_distance' => round($totalDistance, 2),
            'total_emission' => round($totalEmission, 2),
            'avg_emission_per_day' => $workDays > 0 ? round($totalEmission / $workDays, 2) : 0,
        ];
    }
    
    private function getDayOfWeekText($dayOfWeek)
    {
        $days = [
            0 => '週日',
            1 => '週一',
            2 => '週二',
            3 => '週三',
            4 => '週四',
            5 => '週五',
            6 => '週六',
        ];
        
        return $days[$dayOfWeek] ?? '';
    }
    
    private function getTransportModeText($mode)
    {
        $modes = [
            'walking' => '步行',
            'bicycle' => '腳踏車',
            'motorcycle' => '機車',
            'car' => '汽車',
            'bus' => '公車',
            'mrt' => '捷運',
            'train' => '火車',
            'unknown' => '未知'
        ];
        
        return $modes[$mode] ?? '未知';
    }
    
    private function getLocationName($lat, $lng)
    {
        // 定義關鍵位置
        $locations = [
            ['name' => '樹德科技大學', 'lat' => 22.7632, 'lng' => 120.3757, 'radius' => 0.002],
            ['name' => '麥當勞楠梓餐廳', 'lat' => 22.8047, 'lng' => 120.4343, 'radius' => 0.002],
            ['name' => '橫山168', 'lat' => 22.7932, 'lng' => 120.3657, 'radius' => 0.002],
        ];
        
        foreach ($locations as $location) {
            $distance = sqrt(pow($lat - $location['lat'], 2) + pow($lng - $location['lng'], 2));
            if ($distance <= $location['radius']) {
                return $location['name'];
            }
        }
        
        // 根據座標判斷區域
        if ($lat < 22.78) {
            return '燕巢區';
        } elseif ($lat > 22.80) {
            return '楠梓區';
        }
        
        return '路途中';
    }
    
    public function render()
    {
        return view('livewire.user.attendance-list');
    }
}