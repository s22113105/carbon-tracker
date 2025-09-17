<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\OpenAiSuggestionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AiSuggestionController extends Controller
{
    protected $aiService;

    public function __construct(OpenAiSuggestionService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * 顯示 AI 建議頁面
     */
    public function index()
    {
        return view('user.ai-suggestions');
    }

    /**
     * 獲取 AI 建議
     */
    public function getSuggestions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date_range' => 'integer|min:1|max:365'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '參數驗證失敗',
                'errors' => $validator->errors()
            ], 400);
        }

        $dateRange = $request->input('date_range', 30);
        $userId = Auth::id();

        try {
            $result = $this->aiService->generateSuggestionsForUser($userId, $dateRange);
            
            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '建議生成失敗，請稍後再試',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * 分析指定日期範圍的 GPS 資料
     */
    public function analyzeGpsData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'trip_id' => 'sometimes|exists:trips,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '參數驗證失敗',
                'errors' => $validator->errors()
            ], 400);
        }

        $userId = Auth::id();
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        try {
            // 如果有指定行程 ID，只分析該行程
            if ($request->has('trip_id')) {
                $result = $this->analyzeSingleTrip($request->input('trip_id'));
            } else {
                $result = $this->analyzeDateRangeGpsData($userId, $startDate, $endDate);
            }
            
            return response()->json([
                'success' => true,
                'analysis' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'GPS 資料分析失敗',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * 分析單一行程
     */
    private function analyzeSingleTrip($tripId)
    {
        $trip = \App\Models\Trip::with('gpsData')
            ->where('id', $tripId)
            ->where('user_id', Auth::id())
            ->first();

        if (!$trip) {
            throw new \Exception('行程不存在或無權限存取');
        }

        $gpsData = $trip->gpsData->map(function ($data) {
            return [
                'latitude' => $data->latitude,
                'longitude' => $data->longitude,
                'timestamp' => $data->timestamp,
                'speed' => $data->speed ?? 0
            ];
        })->toArray();

        if (empty($gpsData)) {
            throw new \Exception('該行程沒有 GPS 資料');
        }

        return $this->aiService->analyzeGpsDataForTransportMode($gpsData);
    }

    /**
     * 分析日期範圍內的 GPS 資料
     */
    private function analyzeDateRangeGpsData($userId, $startDate, $endDate)
    {
        $trips = \App\Models\Trip::with('gpsData')
            ->where('user_id', $userId)
            ->whereBetween('start_time', [$startDate, $endDate])
            ->get();

        $results = [];
        
        foreach ($trips as $trip) {
            $gpsData = $trip->gpsData->map(function ($data) {
                return [
                    'latitude' => $data->latitude,
                    'longitude' => $data->longitude,
                    'timestamp' => $data->timestamp,
                    'speed' => $data->speed ?? 0
                ];
            })->toArray();

            if (!empty($gpsData)) {
                $analysis = $this->aiService->analyzeGpsDataForTransportMode($gpsData);
                if ($analysis) {
                    $results[] = [
                        'trip_id' => $trip->id,
                        'start_time' => $trip->start_time,
                        'end_time' => $trip->end_time,
                        'original_mode' => $trip->transport_mode,
                        'ai_analysis' => $analysis
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * 手動重新分析所有行程的交通工具
     */
    public function reanalyzeAllTrips(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date_range' => 'integer|min:1|max:90'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '參數驗證失敗',
                'errors' => $validator->errors()
            ], 400);
        }

        $userId = Auth::id();
        $dateRange = $request->input('date_range', 30);
        $startDate = now()->subDays($dateRange);

        try {
            $trips = \App\Models\Trip::with(['gpsData', 'carbonEmission'])
                ->where('user_id', $userId)
                ->where('start_time', '>=', $startDate)
                ->get();

            $updated = 0;
            $errors = [];

            foreach ($trips as $trip) {
                try {
                    $gpsData = $trip->gpsData->map(function ($data) {
                        return [
                            'latitude' => $data->latitude,
                            'longitude' => $data->longitude,
                            'timestamp' => $data->timestamp,
                            'speed' => $data->speed ?? 0
                        ];
                    })->toArray();

                    if (!empty($gpsData)) {
                        $analysis = $this->aiService->analyzeGpsDataForTransportMode($gpsData);
                        
                        if ($analysis && $analysis['confidence'] > 0.7) {
                            // 更新行程的交通工具
                            $trip->update(['transport_mode' => $analysis['transport_mode']]);
                            
                            // 更新碳排放記錄
                            if ($trip->carbonEmission) {
                                $trip->carbonEmission->update([
                                    'transport_mode' => $analysis['transport_mode']
                                ]);
                            }
                            
                            $updated++;
                        }
                    }
                } catch (\Exception $e) {
                    $errors[] = "行程 {$trip->id}: " . $e->getMessage();
                }
            }

            return response()->json([
                'success' => true,
                'message' => "成功重新分析 {$updated} 個行程",
                'updated_count' => $updated,
                'total_trips' => $trips->count(),
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '重新分析失敗',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}