<?php

namespace App\Livewire\User;

use Livewire\Component;
use App\Models\CarbonEmission;
use App\Models\CarbonEmissionAnalysis;

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
        $this->todayEmission = CarbonEmissionAnalysis::where('user_id', $userId)
            ->whereDate('analysis_date', today())
            ->sum('carbon_emission');

        // 本週排放
        $this->weekEmission = CarbonEmissionAnalysis::where('user_id', $userId)
            ->whereBetween('analysis_date', [now()->startOfWeek(), now()->endOfWeek()])
            ->sum('carbon_emission');

        // 本月排放
        $this->monthEmission = CarbonEmissionAnalysis::where('user_id', $userId)
            ->whereMonth('analysis_date', now()->month)
            ->sum('carbon_emission');
    }

    public function render()
    {
        return view('livewire.user.dashboard-stats');
    }
}
