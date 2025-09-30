<?php

namespace App\Livewire\User;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Trip;

class RouteMap extends Component
{
    public $selectedDate = '';
    public $selectedTrip = null;
    public $trips = [];
    public $gpsPoints = [];
    public $loading = false;
    public $mapCenter = ['lat' => 22.8109, 'lng' => 120.3926]; // 路線中心點
    
    public function mount()
    {
        $this->selectedDate = Carbon::today()->format('Y-m-d');
        $this->loadTrips();
    }
    
    public function updatedSelectedDate()
    {
        $this->loading = true;
        $this->loadTrips();
        $this->selectedTrip = null;
        $this->gpsPoints = [];
        $this->dispatch('mapReset');
        $this->loading = false;
    }
    
    public function selectTrip($tripId)
    {
        $this->loading = true;
        $this->selectedTrip = $tripId;
        $this->loadGpsPoints($tripId);
        
        // 發送事件到前端更新地圖
        $this->dispatch('tripSelected', [
            'gpsPoints' => $this->gpsPoints,
            'tripId' => $tripId
        ]);
        
        $this->loading = false;
    }
    
    public function loadTrips()
    {
        $userId = auth()->id();
        
        $this->trips = DB::table('trips')
            ->where('user_id', $userId)
            ->whereDate('start_time', $this->selectedDate)
            ->orderBy('start_time', 'asc')
            ->get()
            ->map(function ($trip) {
                $duration = Carbon::parse($trip->end_time)
                    ->diffInMinutes(Carbon::parse($trip->start_time));
                
                return [
                    'id' => $trip->id,
                    'start_time' => Carbon::parse($trip->start_time)->format('H:i'),
                    'end_time' => Carbon::parse($trip->end_time)->format('H:i'),
                    'duration_minutes' => $duration,
                    'distance' => round($trip->distance, 2),
                    'transport_mode' => $trip->transport_mode,
                    'transport_mode_text' => $this->getTransportModeText($trip->transport_mode),
                    'trip_type' => $trip->trip_type,
                    'trip_type_text' => $this->getTripTypeText($trip->trip_type),
                    'start_lat' => $trip->start_latitude,
                    'start_lng' => $trip->start_longitude,
                    'end_lat' => $trip->end_latitude,
                    'end_lng' => $trip->end_longitude,
                ];
            })->toArray();
    }
    
    public function loadGpsPoints($tripId)
    {
        $trip = DB::table('trips')->find($tripId);
        
        if (!$trip) {
            $this->gpsPoints = [];
            return;
        }
        
        $this->gpsPoints = DB::table('gps_tracks')
            ->where('user_id', $trip->user_id)
            ->whereBetween('recorded_at', [$trip->start_time, $trip->end_time])
            ->orderBy('recorded_at', 'asc')
            ->get()
            ->map(function ($point) {
                return [
                    'lat' => (float) $point->latitude,
                    'lng' => (float) $point->longitude,
                    'speed' => $point->speed ?? 0,
                    'time' => $point->recorded_at,
                ];
            })->toArray();
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
        ];
        
        return $modes[$mode] ?? '未知';
    }
    
    private function getTripTypeText($type)
    {
        $types = [
            'to_work' => '上班',
            'from_work' => '下班',
            'other' => '其他',
        ];
        
        return $types[$type] ?? '其他';
    }
    
    public function render()
    {
        return view('livewire.user.route-map');
    }
}