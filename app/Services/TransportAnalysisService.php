<?php

namespace App\Services;

class TransportAnalysisService
{
    // 碳排放係數 (kg CO2/km)
    private $emissionFactors = [
        'walking' => 0,
        'bus' => 0.089,
        'mrt' => 0.033,
        'car' => 0.251,
        'motorcycle' => 0.113,
    ];
    
    // 平均速度 (km/h)
    private $averageSpeeds = [
        'walking' => 5,
        'bus' => 15,
        'mrt' => 30,
        'car' => 25,
        'motorcycle' => 30,
    ];

    public function analyzeTransport($distance, $duration)
    {
        // duration 單位：分鐘，distance 單位：公里
        if ($duration <= 0 || $distance <= 0) {
            return ['transport_mode' => 'unknown', 'co2_emission' => 0];
        }
        
        $actualSpeed = ($distance / ($duration / 60)); // km/h
        
        // 根據速度推測交通工具
        if ($actualSpeed <= 6) {
            $transportMode = 'walking';
        } elseif ($actualSpeed <= 20) {
            $transportMode = 'bus';
        } elseif ($actualSpeed <= 35) {
            // 速度在 20-35 km/h 之間，可能是捷運或汽車
            // 如果距離較短且在市區，傾向捷運
            if ($distance <= 15) {
                $transportMode = 'mrt';
            } else {
                $transportMode = 'car';
            }
        } else {
            // 速度超過 35 km/h，可能是汽車或機車
            if ($distance <= 10) {
                $transportMode = 'motorcycle';
            } else {
                $transportMode = 'car';
            }
        }
        
        // 計算碳排放
        $co2Emission = $distance * $this->emissionFactors[$transportMode];
        
        return [
            'transport_mode' => $transportMode,
            'co2_emission' => round($co2Emission, 3),
            'analyzed_speed' => round($actualSpeed, 2)
        ];
    }
    
    public function getTransportModeLabel($mode)
    {
        $labels = [
            'walking' => '步行',
            'bus' => '公車',
            'mrt' => '捷運',
            'car' => '汽車',
            'motorcycle' => '機車',
            'unknown' => '未知'
        ];
        
        return $labels[$mode] ?? '未知';
    }
}