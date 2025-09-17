<?php

namespace App\Livewire\User;

use Livewire\Component;
use App\Models\Trip;

class AttendanceRecords extends Component
{
    public $recentAttendance = [];

    public function mount()
    {
        $this->loadAttendance();
    }

    public function loadAttendance()
    {
        $this->recentAttendance = Trip::where('user_id', auth()->id())
            ->whereIn('trip_type', ['to_work', 'from_work'])
            ->orderBy('start_time', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($trip) {
                return [
                    'date' => $trip->start_time->format('Y-m-d'),
                    'time' => $trip->start_time->format('H:i'),
                    'type' => $trip->trip_type === 'to_work' ? '上班打卡' : '下班打卡',
                ];
            })->toArray();
    }

    public function render()
    {
        return view('livewire.user.attendance-records');
    }
}