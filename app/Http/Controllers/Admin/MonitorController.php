<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MonitorController extends Controller
{
    public function deviceStatus()
    {
        $devices = DB::table('device_status')
            ->select('device_id', 'battery_level', 'last_seen', 'is_online')
            ->get();
        
        // 檢查離線設備（超過5分鐘沒有回報）
        foreach ($devices as $device) {
            $lastSeen = \Carbon\Carbon::parse($device->last_seen);
            $device->is_online = $lastSeen->diffInMinutes(now()) < 5;
        }
        
        return view('admin.monitor', compact('devices'));
    }
    
    public function recentGpsData($deviceId)
    {
        $data = DB::table('gps_tracks')
            ->join('device_users', 'gps_tracks.user_id', '=', 'device_users.user_id')
            ->where('device_users.device_id', $deviceId)
            ->orderBy('gps_tracks.recorded_at', 'desc')
            ->limit(100)
            ->get();
        
        return response()->json($data);
    }
}