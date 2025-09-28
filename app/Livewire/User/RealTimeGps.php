<?php

namespace App\Livewire\User;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Models\Trip;
use Carbon\Carbon;

class RealTimeGps extends Component
{
    public $latestGps = null;
    public $recentTrips = [];
    public $isOnline = false;
    public $lastFiveGps = [];
    public $deviceStatus = null;

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
            
            // 改善在線判斷邏輯
            $recordedAt = Carbon::parse($latestRecord->recorded_at);
            $now = Carbon::now();
            $diffInSeconds = $now->diffInSeconds($recordedAt);
            
            // 如果最後記錄在 90 秒內（ESP32 每 30 秒傳送，給予 3 倍容錯）
            $this->isOnline = $diffInSeconds <= 90;
            
            // 除錯資訊
            \Log::info('在線狀態檢查', [
                'recorded_at' => $recordedAt->toDateTimeString(),
                'now' => $now->toDateTimeString(),
                'diff_seconds' => $diffInSeconds,
                'is_online' => $this->isOnline
            ]);
        } else {
            $this->isOnline = false;
        }
        
        // 檢查設備狀態表
        $this->deviceStatus = DB::table('device_status')
            ->join('device_users', 'device_status.device_id', '=', 'device_users.device_id')
            ->where('device_users.user_id', $userId)
            ->select('device_status.*')
            ->first();
        
        if ($this->deviceStatus) {
            // 使用設備狀態表的在線狀態作為輔助判斷
            $lastSeen = Carbon::parse($this->deviceStatus->last_seen);
            $deviceOnline = Carbon::now()->diffInSeconds($lastSeen) <= 90;
            
            // 綜合判斷
            $this->isOnline = $this->isOnline || $deviceOnline;
        }
        
        // 取得最近 5 筆 GPS 記錄
        $this->lastFiveGps = DB::table('gps_tracks')
            ->where('user_id', $userId)
            ->orderBy('recorded_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($record) {
                $recordedAt = Carbon::parse($record->recorded_at);
                return [
                    'latitude' => $record->latitude,
                    'longitude' => $record->longitude,
                    'speed' => $record->speed ?? 0,
                    'recorded_at' => $record->recorded_at,
                    'formatted_time' => $recordedAt->format('H:i:s'),
                    'time_ago' => $recordedAt->diffForHumans(),
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