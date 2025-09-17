<?php

namespace App\Livewire\User;

use Livewire\Component;
use App\Models\GpsRecord;
use App\Models\Trip;
use Carbon\Carbon;

class RouteMap extends Component
{
    public $selectedDate;
    public $selectedTrip = null;
    public $trips = [];
    public $gpsPoints = [];

    public function mount()
    {
        $this->selectedDate = Carbon::today()->format('Y-m-d');
        $this->loadTrips();
    }

    public function updatedSelectedDate()
    {
        $this->loadTrips();
        $this->selectedTrip = null;
        $this->gpsPoints = [];
        $this->dispatch('mapReset');
    }

    public function selectTrip($tripId)
    {
        $this->selectedTrip = $tripId;
        $this->loadGpsPoints($tripId);
        $this->dispatch('tripSelected');
    }

    public function resetMap()
    {
        $this->selectedTrip = null;
        $this->gpsPoints = [];
        $this->dispatch('mapReset');
        
        session()->flash('message', '地圖已重置');
    }

    public function clearTodayData()
    {
        $userId = auth()->id();
        
        // 刪除今日的 GPS 記錄
        GpsRecord::where('user_id', $userId)
            ->whereDate('recorded_at', $this->selectedDate)
            ->delete();
            
        // 刪除今日的行程記錄
        Trip::where('user_id', $userId)
            ->whereDate('start_time', $this->selectedDate)
            ->delete();
        
        // 重新載入資料
        $this->loadTrips();
        $this->resetMap();
        
        session()->flash('message', '今日資料已清除');
    }

    public function loadTrips()
    {
        $userId = auth()->id();
        
        $this->trips = Trip::where('user_id', $userId)
            ->whereDate('start_time', $this->selectedDate)
            ->whereNotNull('end_time')
            ->with('carbonEmission')
            ->orderBy('start_time', 'asc')
            ->get()
            ->map(function ($trip) {
                return [
                    'id' => $trip->id,
                    'start_time' => $trip->start_time->format('H:i'),
                    'end_time' => $trip->end_time ? $trip->end_time->format('H:i') : null,
                    'distance' => $trip->distance,
                    'transport_mode' => $trip->transport_mode,
                    'trip_type' => $trip->trip_type,
                    'start_lat' => $trip->start_latitude,
                    'start_lng' => $trip->start_longitude,
                    'end_lat' => $trip->end_latitude,
                    'end_lng' => $trip->end_longitude,
                    'co2_emission' => $trip->carbonEmission ? $trip->carbonEmission->co2_emission : 0,
                ];
            })
            ->toArray();
    }

    public function loadGpsPoints($tripId)
    {
        $trip = Trip::find($tripId);
        if (!$trip) return;

        $this->gpsPoints = GpsRecord::where('user_id', auth()->id())
            ->whereBetween('recorded_at', [
                $trip->start_time->subMinutes(5),
                $trip->end_time->addMinutes(5)
            ])
            ->orderBy('recorded_at', 'asc')
            ->get()
            ->map(function ($record) {
                return [
                    'lat' => $record->latitude,
                    'lng' => $record->longitude,
                    'time' => $record->recorded_at->format('H:i:s'),
                    'speed' => $record->speed ?? 0,
                ];
            })
            ->toArray();
    }

    public function render()
    {
        return view('livewire.user.route-map');
    }
}