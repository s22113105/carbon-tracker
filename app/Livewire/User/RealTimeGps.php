<?php

namespace App\Livewire\User;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RealTimeGps extends Component
{
    public $latestGps = null;
    public $isOnline = false;
    public $currentSpeed = 0;
    public $totalDistanceToday = 0;
    public $carbonEmissionToday = 0;
    public $currentLocation = '';
    
    public function mount()
    {
        $this->loadData();
    }
    
    public function refreshData()
    {
        $this->loadData();
    }
    
    public function loadData()
    {
        $userId = auth()->id();
        
        // 獲取最新GPS記錄
        $latestRecord = DB::table('gps_tracks')
            ->where('user_id', $userId)
            ->orderBy('recorded_at', 'desc')
            ->first();
        
        if ($latestRecord) {
            $this->latestGps = [
                'latitude' => $latestRecord->latitude,
                'longitude' => $latestRecord->longitude,
                'speed' => $latestRecord->speed ?? 0,
                'recorded_at' => $latestRecord->recorded_at,
            ];
            
            // 判斷是否在線（最後記錄在90秒內）
            $recordedAt = Carbon::parse($latestRecord->recorded_at);
            $this->isOnline = $recordedAt->diffInSeconds(now()) <= 90;
            
            $this->currentSpeed = $latestRecord->speed ?? 0;
            
            // 判斷當前位置
            $this->currentLocation = $this->getLocationName(
                $latestRecord->latitude, 
                $latestRecord->longitude
            );
        }
        
        // 計算今日統計
        $todayStats = DB::table('trips')
            ->where('user_id', $userId)
            ->whereDate('start_time', Carbon::today())
            ->selectRaw('SUM(distance) as total_distance')
            ->first();
        
        $this->totalDistanceToday = $todayStats->total_distance ?? 0;
        
        // 計算今日碳排放
        $todayEmissions = DB::table('carbon_emissions')
            ->where('user_id', $userId)
            ->where('date', Carbon::today())
            ->sum('carbon_amount');
        
        $this->carbonEmissionToday = round($todayEmissions, 2);
    }
    
    private function getLocationName($lat, $lng)
    {
        // 定義關鍵位置
        $locations = [
            ['name' => '橫山168', 'lat' => 22.7932, 'lng' => 120.3657, 'radius' => 0.001],
            ['name' => '麥當勞楠梓餐廳', 'lat' => 22.8285, 'lng' => 120.4195, 'radius' => 0.001],
            ['name' => '旗楠路', 'lat' => 22.8034, 'lng' => 120.3815, 'radius' => 0.005],
            ['name' => '建楠路', 'lat' => 22.8226, 'lng' => 120.4103, 'radius' => 0.005],
        ];
        
        foreach ($locations as $location) {
            $distance = $this->calculateDistance($lat, $lng, $location['lat'], $location['lng']);
            if ($distance <= $location['radius']) {
                return $location['name'];
            }
        }
        
        return '移動中';
    }
    
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        return sqrt(pow($lat2 - $lat1, 2) + pow($lon2 - $lon1, 2));
    }
    
    public function render()
    {
        return view('livewire.user.real-time-gps');
    }
}