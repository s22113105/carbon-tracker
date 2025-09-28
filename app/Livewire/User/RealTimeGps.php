<?php

namespace App\Livewire\User;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Models\Trip;

class RealTimeGps extends Component
{
    public $latestGps = null;
    public $recentTrips = [];
    public $isOnline = false;
    public $lastFiveGps = [];

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
        
        // 從 gps_tracks 表讀取最新的 GPS 記錄
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
                'device_type' => $latestRecord->device_type ?? 'Unknown',
            ];
            
            // 檢查是否在線（最近 2 分鐘有資料）
            $recordedAt = \Carbon\Carbon::parse($latestRecord->recorded_at);
            $this->isOnline = $recordedAt->diffInMinutes(now()) <= 2;
        }
        
        // 取得最近 5 筆 GPS 記錄
        $this->lastFiveGps = DB::table('gps_tracks')
            ->where('user_id', $userId)
            ->orderBy('recorded_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($record) {
                return [
                    'latitude' => $record->latitude,
                    'longitude' => $record->longitude,
                    'speed' => $record->speed ?? 0,
                    'recorded_at' => $record->recorded_at,
                    'time_ago' => \Carbon\Carbon::parse($record->recorded_at)->diffForHumans(),
                ];
            })
            ->toArray();

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