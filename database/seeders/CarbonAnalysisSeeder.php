<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CarbonAnalysisSeeder extends Seeder
{
    /**
     * 執行資料庫種子
     */
    public function run(): void
    {
        $userId = 1;
        $endDate = Carbon::now();
        
        // 生成幾個歷史分析記錄範例
        for ($i = 1; $i <= 5; $i++) {
            $startDate = $endDate->copy()->subDays(7 * $i + 7);
            $analysisEndDate = $endDate->copy()->subDays(7 * $i);
            
            $sampleAnalysis = [
                'analysis' => [
                    'total_distance' => rand(50, 200) / 10, // 5-20 km
                    'total_time' => rand(180, 600), // 3-10 小時
                    'transportation_breakdown' => [
                        [
                            'type' => 'walking',
                            'distance' => rand(10, 30) / 10,
                            'time' => rand(30, 90),
                            'carbon_emission' => 0
                        ],
                        [
                            'type' => 'mrt',
                            'distance' => rand(20, 80) / 10,
                            'time' => rand(60, 180),
                            'carbon_emission' => rand(5, 25) / 100
                        ],
                        [
                            'type' => 'bus',
                            'distance' => rand(10, 40) / 10,
                            'time' => rand(30, 120),
                            'carbon_emission' => rand(8, 32) / 100
                        ]
                    ],
                    'total_carbon_emission' => rand(50, 300) / 100
                ],
                'recommendations' => [
                    [
                        'title' => '增加步行距離',
                        'description' => '短距離移動時考慮步行，既環保又健康',
                        'potential_reduction' => rand(5, 20) / 100,
                        'difficulty' => 'easy'
                    ],
                    [
                        'title' => '多使用大眾運輸',
                        'description' => '捷運和公車的碳排放比私人車輛低很多',
                        'potential_reduction' => rand(20, 50) / 100,
                        'difficulty' => 'medium'
                    ]
                ],
                'summary' => [
                    'current_footprint' => ['low', 'medium', 'high'][rand(0, 2)],
                    'improvement_potential' => rand(10, 40) . '%',
                    'key_insight' => '您的通勤方式相對環保，繼續保持！'
                ]
            ];
            
            DB::table('carbon_analyses')->insert([
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $analysisEndDate,
                'analysis_result' => json_encode($sampleAnalysis),
                'total_carbon_emission' => $sampleAnalysis['analysis']['total_carbon_emission'],
                'total_distance' => $sampleAnalysis['analysis']['total_distance'],
                'total_time' => $sampleAnalysis['analysis']['total_time'],
                'footprint_level' => $sampleAnalysis['summary']['current_footprint'],
                'improvement_potential' => (int) str_replace('%', '', $sampleAnalysis['summary']['improvement_potential']),
                'openai_model' => 'gpt-4',
                'tokens_used' => rand(800, 1500),
                'api_cost' => rand(2, 8) / 100,
                'status' => 'completed',
                'created_at' => $analysisEndDate->addHours(rand(1, 6)),
                'updated_at' => $analysisEndDate->addHours(rand(1, 6)),
            ]);
        }
        
        echo "碳分析範例資料生成完成！\n";
    }
}
