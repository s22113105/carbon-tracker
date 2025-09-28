<?php

namespace App\Livewire\User;

use Livewire\Component;
use App\Models\GpsRecord;
use App\Models\Trip;
use App\Models\CarbonAnalysis;
use App\Services\TripAnalysisService;
use App\Services\GpsDataSyncService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class RouteMap extends Component
{
    // 確保所有公共屬性都有預設值
    public $selectedDate = '';
    public $selectedTrip = null;
    public $trips = [];
    public $gpsPoints = [];
    public $loading = false;
    public $analyticsData = [];
    public $realTimeStatus = [];

    protected $tripAnalysisService;
    protected $gpsDataSyncService;

    public function mount()
    {
        // 明確設定預設日期
        $this->selectedDate = Carbon::today()->format('Y-m-d');
        
        // 初始化陣列
        $this->trips = [];
        $this->gpsPoints = [];
        $this->analyticsData = [
            'gps_points' => 0,
            'esp32_points' => 0,
            'trips_count' => 0,
            'total_distance' => 0,
            'total_co2' => 0,
            'transport_modes' => [],
            'data_sync_ratio' => 0,
        ];
        $this->realTimeStatus = [];
        
        // 載入資料
        $this->loadTrips();
        $this->loadAnalyticsData();
        $this->loadRealTimeStatus();
    }

    public function boot()
    {
        // 使用Laravel服務容器注入依賴
        if (!$this->tripAnalysisService) {
            $this->tripAnalysisService = app(TripAnalysisService::class);
        }
        if (!$this->gpsDataSyncService) {
            $this->gpsDataSyncService = app(GpsDataSyncService::class);
        }
    }

    public function updatedSelectedDate()
    {
        $this->loading = true;
        $this->loadTrips();
        $this->loadAnalyticsData();
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
        $this->dispatch('tripSelected');
        $this->loading = false;
    }

    public function resetMap()
    {
        $this->selectedTrip = null;
        $this->gpsPoints = [];
        $this->dispatch('mapReset');
        
        session()->flash('message', '地圖已重置');
    }

    public function analyzeTrips()
    {
        $this->loading = true;
        
        try {
            $userId = auth()->id();
            
            // 如果服務可用，使用服務；否則使用基本分析
            if ($this->gpsDataSyncService) {
                $result = $this->gpsDataSyncService->analyzeAndCreateTrips($userId, $this->selectedDate);
                session()->flash('message', 
                    "行程分析完成！同步了 {$result['synced_gps_count']} 筆ESP32資料，共生成 {$result['trips_created']} 筆行程記錄"
                );
            } else {
                // 使用基本的TripAnalysisService
                if ($this->tripAnalysisService) {
                    $trips = $this->tripAnalysisService->reanalyzeTripsForDate($userId, $this->selectedDate);
                    session()->flash('message', '行程分析完成，共生成 ' . count($trips) . ' 筆行程記錄');
                } else {
                    session()->flash('error', '分析服務不可用');
                }
            }
            
            $this->loadTrips();
            $this->loadAnalyticsData();
            $this->loadRealTimeStatus();
            $this->resetMap();
            
        } catch (\Exception $e) {
            Log::error('行程分析失敗', ['error' => $e->getMessage()]);
            session()->flash('error', '行程分析時發生錯誤');
        }
        
        $this->loading = false;
    }

    public function clearTodayData()
    {
        $userId = auth()->id();
        
        try {
            // 刪除今日的 GPS 記錄
            $deletedGps = GpsRecord::where('user_id', $userId)
                ->whereDate('recorded_at', $this->selectedDate)
                ->count();
                
            GpsRecord::where('user_id', $userId)
                ->whereDate('recorded_at', $this->selectedDate)
                ->delete();
                
            // 刪除今日的行程記錄
            $deletedTrips = Trip::where('user_id', $userId)
                ->whereDate('start_time', $this->selectedDate)
                ->count();
                
            Trip::where('user_id', $userId)
                ->whereDate('start_time', $this->selectedDate)
                ->delete();
            
            // 重新載入資料
            $this->loadTrips();
            $this->loadAnalyticsData();
            $this->loadRealTimeStatus();
            $this->resetMap();
            
            session()->flash('message', 
                "已清除今日資料：{$deletedGps} 筆GPS記錄，{$deletedTrips} 筆行程記錄"
            );
            
        } catch (\Exception $e) {
            Log::error('清除今日資料失敗', ['error' => $e->getMessage()]);
            session()->flash('error', '清除資料時發生錯誤');
        }
    }

    public function loadTrips()
    {
        $userId = auth()->id();
        
        try {
            $trips = Trip::where('user_id', $userId)
                ->whereDate('start_time', $this->selectedDate)
                ->whereNotNull('end_time')
                ->orderBy('start_time', 'asc')
                ->get();
            
            $this->trips = $trips->map(function ($trip) {
                $duration = $trip->start_time->diffInMinutes($trip->end_time);
                $avgSpeed = $duration > 0 ? ($trip->distance / $duration) * 60 : 0;
                
                return [
                    'id' => $trip->id,
                    'start_time' => $trip->start_time->format('H:i'),
                    'end_time' => $trip->end_time ? $trip->end_time->format('H:i') : null,
                    'duration_minutes' => $duration,
                    'distance' => round($trip->distance, 2),
                    'avg_speed' => round($avgSpeed, 1),
                    'transport_mode' => $trip->transport_mode,
                    'transport_mode_text' => $this->getTransportModeText($trip->transport_mode),
                    'trip_type' => $trip->trip_type,
                    'trip_type_text' => $this->getTripTypeText($trip->trip_type),
                    'start_lat' => $trip->start_latitude,
                    'start_lng' => $trip->start_longitude,
                    'end_lat' => $trip->end_latitude,
                    'end_lng' => $trip->end_longitude,
                    'co2_emission' => $this->estimateCarbonEmission($trip),
                    'color' => $this->getTripColor($trip->transport_mode),
                    'data_source' => '系統資料',
                ];
            })->toArray();
            
        } catch (\Exception $e) {
            Log::error('載入行程失敗', ['error' => $e->getMessage()]);
            $this->trips = [];
        }
    }

    public function loadGpsPoints($tripId)
    {
        try {
            if ($this->tripAnalysisService) {
                $this->gpsPoints = $this->tripAnalysisService->getTripGpsTrace($tripId);
            } else {
                // 基本的GPS點載入
                $trip = Trip::findOrFail($tripId);
                $this->gpsPoints = GpsRecord::where('user_id', $trip->user_id)
                    ->whereBetween('recorded_at', [$trip->start_time, $trip->end_time])
                    ->orderBy('recorded_at', 'asc')
                    ->get()
                    ->map(function ($point) {
                        return [
                            'lat' => (float) $point->latitude,
                            'lng' => (float) $point->longitude,
                            'time' => $point->recorded_at,
                            'speed' => $point->speed ?? 0,
                            'accuracy' => $point->accuracy ?? 10
                        ];
                    })->toArray();
            }
            
        } catch (\Exception $e) {
            Log::error('載入GPS軌跡失敗', ['trip_id' => $tripId, 'error' => $e->getMessage()]);
            $this->gpsPoints = [];
        }
    }

    public function loadAnalyticsData()
    {
        $userId = auth()->id();
        
        try {
            // 獲取當日統計資料
            $gpsCount = GpsRecord::where('user_id', $userId)
                ->whereDate('recorded_at', $this->selectedDate)
                ->count();
                
            $tripsCount = Trip::where('user_id', $userId)
                ->whereDate('start_time', $this->selectedDate)
                ->count();
                
            $totalDistance = Trip::where('user_id', $userId)
                ->whereDate('start_time', $this->selectedDate)
                ->sum('distance');
                
            // 交通工具分布
            $transportModes = Trip::where('user_id', $userId)
                ->whereDate('start_time', $this->selectedDate)
                ->selectRaw('transport_mode, COUNT(*) as count, SUM(distance) as total_distance')
                ->groupBy('transport_mode')
                ->get()
                ->map(function ($item) {
                    return [
                        'mode' => $item->transport_mode,
                        'mode_text' => $this->getTransportModeText($item->transport_mode),
                        'count' => $item->count,
                        'distance' => round($item->total_distance, 2),
                        'color' => $this->getTripColor($item->transport_mode)
                    ];
                });

            $this->analyticsData = [
                'gps_points' => $gpsCount,
                'esp32_points' => 0, // 暫時設為0
                'trips_count' => $tripsCount,
                'total_distance' => round($totalDistance, 2),
                'total_co2' => round($totalDistance * 0.15, 3), // 簡單估算
                'transport_modes' => $transportModes,
                'data_sync_ratio' => 0,
            ];
            
        } catch (\Exception $e) {
            Log::error('載入分析資料失敗', ['error' => $e->getMessage()]);
            $this->analyticsData = [
                'gps_points' => 0,
                'esp32_points' => 0,
                'trips_count' => 0,
                'total_distance' => 0,
                'total_co2' => 0,
                'transport_modes' => [],
                'data_sync_ratio' => 0,
            ];
        }
    }

    public function loadRealTimeStatus()
    {
        try {
            $userId = auth()->id();
            
            // 基本的即時狀態
            $this->realTimeStatus = [
                'device_status' => [
                    'status' => 'offline',
                    'last_seen' => '離線'
                ],
                'today_stats' => [
                    'unprocessed_points' => 0,
                    'first_record_at' => null,
                    'last_record_at' => null,
                    'trips_generated' => $this->analyticsData['trips_count'] ?? 0
                ]
            ];
            
        } catch (\Exception $e) {
            Log::error('載入即時狀態失敗', ['error' => $e->getMessage()]);
            $this->realTimeStatus = [];
        }
    }

    private function estimateCarbonEmission($trip)
    {
        $emissionFactors = [
            'walking' => 0,
            'bicycle' => 0,
            'motorcycle' => 0.095,
            'car' => 0.21,
            'bus' => 0.089,
            'mrt' => 0.033,
            'train' => 0.041,
            'unknown' => 0.15
        ];
        
        $factor = $emissionFactors[$trip->transport_mode] ?? 0.15;
        return round($trip->distance * $factor, 3);
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
            'unknown' => '未知'
        ];
        
        return $modes[$mode] ?? '未知';
    }

    private function getTripTypeText($type)
    {
        $types = [
            'to_work' => '上班',
            'from_work' => '下班',
            'other' => '其他'
        ];
        
        return $types[$type] ?? '其他';
    }

    private function getTripColor($transportMode)
    {
        $colors = [
            'walking' => '#28a745',
            'bicycle' => '#17a2b8',
            'motorcycle' => '#ffc107',
            'car' => '#dc3545',
            'bus' => '#6f42c1',
            'mrt' => '#007bff',
            'train' => '#20c997',
            'unknown' => '#6c757d'
        ];
        
        return $colors[$transportMode] ?? '#6c757d';
    }

    public function render()
    {
        return view('livewire.user.route-map');
    }
}