<?php

namespace App\Livewire\User;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Trip;
use App\Services\TransportAnalysisService;

class AttendanceList extends Component
{
    use WithPagination;

    public $dateFilter = '';
    public $typeFilter = '';
    public $transportFilter = '';
    public $perPage = 10;

    protected $queryString = [
        'dateFilter' => ['except' => ''],
        'typeFilter' => ['except' => ''],
        'transportFilter' => ['except' => '']
    ];

    public function updatingDateFilter()
    {
        $this->resetPage();
    }

    public function updatingTypeFilter()
    {
        $this->resetPage();
    }

    public function updatingTransportFilter()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->dateFilter = '';
        $this->typeFilter = '';
        $this->transportFilter = '';
        $this->resetPage();
    }

    public function render()
    {
        $query = Trip::where('user_id', auth()->id())
            ->whereIn('trip_type', ['to_work', 'from_work']);

        // 日期篩選
        if ($this->dateFilter) {
            $query->whereDate('start_time', $this->dateFilter);
        }

        // 類型篩選
        if ($this->typeFilter) {
            $query->where('trip_type', $this->typeFilter);
        }

        // 交通工具篩選
        if ($this->transportFilter) {
            $query->where('transport_mode', $this->transportFilter);
        }

        $trips = $query->orderBy('start_time', 'desc')
            ->paginate($this->perPage);

        $transportService = new TransportAnalysisService();

        return view('livewire.user.attendance-list', [
            'trips' => $trips,
            'transportService' => $transportService
        ]);
    }
}