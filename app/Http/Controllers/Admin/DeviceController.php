<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class DeviceController extends Controller
{
    public function index()
    {
        $devices = DB::table('device_users')
            ->leftJoin('users', 'device_users.user_id', '=', 'users.id')
            ->leftJoin('device_status', 'device_users.device_id', '=', 'device_status.device_id')
            ->select(
                'device_users.*',
                'users.name as user_name',
                'users.email as user_email',
                'device_status.battery_level',
                'device_status.last_seen',
                'device_status.is_online'
            )
            ->get();
        
        $users = User::all();
        
        return view('admin.devices', compact('devices', 'users'));
    }
    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'device_id' => 'required|string|unique:device_users,device_id',
            'device_name' => 'required|string',
            'user_id' => 'required|exists:users,id'
        ]);
        
        DB::table('device_users')->insert([
            'device_id' => $validated['device_id'],
            'device_name' => $validated['device_name'],
            'user_id' => $validated['user_id'],
            'device_type' => 'ESP32',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        return response()->json(['success' => true]);
    }
    
    public function destroy($deviceId)
    {
        DB::table('device_users')->where('device_id', $deviceId)->delete();
        DB::table('device_status')->where('device_id', $deviceId)->delete();
        
        return response()->json(['success' => true]);
    }
}