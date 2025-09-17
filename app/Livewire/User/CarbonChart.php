<?php

namespace App\Livewire\User;

use Livewire\Component;
use App\Models\CarbonEmission;
use Carbon\Carbon;

class CarbonChart extends Component
{
    public $chartType = 'daily';
    public $chartData = [];
    public $transportData = [];

    public function mount()
    {
        $this->loadChartData();
        $this->loadTransportData();
    }

    public function updatedChartType()
    {
        $this->loadChartData();
    }

    public function loadChartData()
    {
        $userId = auth()->id();
        
        if ($this->chartType === 'daily') {
            // 過去7天的資料
            $this->chartData = collect(range(6, 0))->map(function ($days) use ($userId) {
                $date = Carbon::today()->subDays($days);
                $emission = CarbonEmission::where('user_id', $userId)
                    ->whereDate('emission_date', $date)
                    ->sum('co2_emission');
                
                return [
                    'label' => $date->format('m/d'),
                    'value' => round($emission, 2)
                ];
            })->toArray();
        } else {
            // 過去4週的資料
            $this->chartData = collect(range(3, 0))->map(function ($weeks) use ($userId) {
                $startOfWeek = Carbon::today()->subWeeks($weeks)->startOfWeek();
                $endOfWeek = Carbon::today()->subWeeks($weeks)->endOfWeek();
                
                $emission = CarbonEmission::where('user_id', $userId)
                    ->whereBetween('emission_date', [$startOfWeek, $endOfWeek])
                    ->sum('co2_emission');
                
                return [
                    'label' => $startOfWeek->format('m/d'),
                    'value' => round($emission, 2)
                ];
            })->toArray();
        }
    }

    public function loadTransportData()
    {
        $userId = auth()->id();
        
        $transportStats = CarbonEmission::where('user_id', $userId)
            ->selectRaw('transport_mode, SUM(co2_emission) as total_emission, COUNT(*) as count')
            ->groupBy('transport_mode')
            ->get();

        $this->transportData = $transportStats->map(function ($item) {
            $labels = [
                'walking' => '步行',
                'bus' => '公車',
                'mrt' => '捷運',
                'car' => '汽車',
                'motorcycle' => '機車'
            ];
            
            return [
                'label' => $labels[$item->transport_mode] ?? $item->transport_mode,
                'value' => round($item->total_emission, 2),
                'count' => $item->count
            ];
        })->toArray();
    }

    public function render()
    {
        return view('livewire.user.carbon-chart');
    }
}