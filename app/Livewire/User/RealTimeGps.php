<?php

namespace App\Livewire\User;

use Livewire\Component;
use App\Models\GpsRecord;
use App\Models\Trip;

class RealTimeGps extends Component
{
    public $latestGps = null;
    public $recentTrips = [];
    public $isOnline = false;

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
        
        // 最新的 GPS 記錄
        $this->latestGps = GpsRecord::where('user_id', $userId)
            ->latest('recorded_at')
            ->first();

        // 檢查是否在線（最近 2 分鐘有資料，因為 ESP32 每 30 秒傳送一次）
        $this->isOnline = $this->latestGps && 
                        $this->latestGps->recorded_at->diffInMinutes(now()) <= 2;

        // 最近的行程
        $this->recentTrips = Trip::where('user_id', $userId)
            ->with('carbonEmission')
            ->latest('start_time')
            ->limit(5)
            ->get()
            ->toArray();
    }

    public function render()
    {
        return view('livewire.user.real-time-gps');
    }
}