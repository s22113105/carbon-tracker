<?php

namespace App\Livewire\User;

use Livewire\Component;
use App\Models\CarbonEmission;

class DashboardStats extends Component
{
    public $todayEmission = 0;
    public $weekEmission = 0;
    public $monthEmission = 0;

    public function mount()
    {
        $this->loadStats();
    }

    public function loadStats()
    {
        $userId = auth()->id();
        
        // 今日排放
        $this->todayEmission = CarbonEmission::where('user_id', $userId)
            ->whereDate('emission_date', today())
            ->sum('co2_emission');

        // 本週排放
        $this->weekEmission = CarbonEmission::where('user_id', $userId)
            ->whereBetween('emission_date', [now()->startOfWeek(), now()->endOfWeek()])
            ->sum('co2_emission');

        // 本月排放
        $this->monthEmission = CarbonEmission::where('user_id', $userId)
            ->whereMonth('emission_date', now()->month)
            ->sum('co2_emission');
    }

    public function render()
    {
        return view('livewire.user.dashboard-stats');
    }
}
