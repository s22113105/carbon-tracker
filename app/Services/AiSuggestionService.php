<?php

namespace App\Services;

use App\Models\CarbonEmission;
use App\Models\Trip;

class AiSuggestionService
{
    public function generateSuggestionsForUser($userId)
    {
        // 收集使用者資料
        $userData = $this->collectUserData($userId);
        
        if (empty($userData['trips'])) {
            return '目前資料不足，請累積更多通勤記錄後再查看建議。';
        }
        
        // 使用模擬的建議
        return $this->generateMockSuggestions($userData);
    }
    
    private function collectUserData($userId)
    {
        // 最近30天的資料
        $trips = Trip::where('user_id', $userId)
            ->where('start_time', '>=', now()->subDays(30))
            ->with('carbonEmission')
            ->get();
            
        $carbonEmissions = CarbonEmission::where('user_id', $userId)
            ->where('emission_date', '>=', now()->subDays(30))
            ->get();
            
        return [
            'trips' => $trips,
            'totalEmission' => $carbonEmissions->sum('co2_emission'),
            'avgDailyEmission' => $carbonEmissions->avg('co2_emission'),
            'transportStats' => $carbonEmissions->groupBy('transport_mode')
                ->map(function ($group) {
                    return [
                        'count' => $group->count(),
                        'total_emission' => $group->sum('co2_emission'),
                        'avg_distance' => $group->avg('distance')
                    ];
                }),
        ];
    }

    private function generateMockSuggestions($userData)
    {
        $totalEmission = round($userData['totalEmission'], 2);
        $dominantTransport = $userData['transportStats']->sortByDesc('count')->keys()->first();
        
        $suggestions = "根據您過去30天的通勤資料分析：\n\n";
        $suggestions .= "您的通勤概況：\n";
        $suggestions .= "• 總碳排放量：{$totalEmission} kg CO2\n";
        $suggestions .= "• 主要交通工具：" . $this->getTransportLabel($dominantTransport) . "\n\n";
        
        $suggestions .= "個人化減碳建議：\n\n";
        
        if ($dominantTransport === 'car') {
            $suggestions .= "1. 考慮改搭大眾運輸：捷運或公車可減少60-80%的碳排放\n";
            $suggestions .= "2. 若需開車，可考慮與同事共乘\n";
            $suggestions .= "3. 短距離路段可改騎自行車\n";
            $suggestions .= "4. 選擇離峰時段出行可提高效率\n";
        } elseif ($dominantTransport === 'motorcycle') {
            $suggestions .= "1. 改搭捷運可減少約70%的碳排放\n";
            $suggestions .= "2. 公車也是不錯的環保選擇\n";
            $suggestions .= "3. 近距離路段可考慮步行\n";
            $suggestions .= "4. 定期保養機車可提高燃油效率\n";
        } else {
            $suggestions .= "1. 您已經在使用環保的交通方式，值得鼓勵！\n";
            $suggestions .= "2. 可考慮在短距離時改為步行\n";
            $suggestions .= "3. 選擇離峰時段通勤可提高效率\n";
            $suggestions .= "4. 維持目前的綠色通勤習慣\n";
        }
        
        $suggestions .= "\n預估減碳效果：\n";
        $suggestions .= "若改用大眾運輸，每月可減少約 " . round($totalEmission * 0.6, 1) . " kg CO2 排放量\n";
        $suggestions .= "這相當於種植 " . round($totalEmission * 0.6 / 21.77, 1) . " 棵樹的年吸碳量";
        
        return $suggestions;
    }

    private function getTransportLabel($transport)
    {
        $labels = [
            'walking' => '步行',
            'bus' => '公車',
            'mrt' => '捷運',
            'car' => '汽車',
            'motorcycle' => '機車'
        ];
        
        return $labels[$transport] ?? '未知';
    }
}