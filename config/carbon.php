<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Carbon Footprint Analysis Configuration
    |--------------------------------------------------------------------------
    |
    | 碳足跡分析相關的配置設定
    |
    */

    // 碳排放係數設定 (單位: kg CO2/km)
    'emission_factors' => [
        'walking' => 0,
        'bicycle' => 0,
        'motorcycle' => 0.06,
        'car' => 0.15,
        'bus' => 0.08,
        'mrt' => 0.03,  // 捷運
        'train' => 0.04, // 火車
    ],

    // 交通工具速度範圍 (單位: km/h)
    'speed_ranges' => [
        'walking' => ['min' => 0, 'max' => 5],
        'bicycle' => ['min' => 5, 'max' => 20],
        'motorcycle' => ['min' => 20, 'max' => 60],
        'car' => ['min' => 30, 'max' => 100],
        'bus' => ['min' => 10, 'max' => 50],
    ],

    // 分析設定
    'analysis' => [
        'max_days' => 365,  // 最大分析天數
        'min_distance' => 0.1,  // 最小距離 (km)
        'cache_duration' => 3600,  // 快取時間 (秒)
    ],

    // GPS 軌跡過濾設定
    'gps_filter' => [
        'min_accuracy' => 50,  // 最小精確度 (公尺)
        'max_speed' => 200,    // 最大速度 (km/h)
        'min_time_gap' => 30,  // 最小時間間隔 (秒)
    ],

];