<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Exception;

class GpsFakeDataSeeder extends Seeder
{
    public function run()
    {
        try {
            $this->checkRequiredTables();

            $userId = 2;
            $this->ensureUserExists($userId);
            $this->clearOldData($userId);

            // 去程：樹德科大 → 楠梓車站前麥當勞（建楠路160號）
            $out = $this->generateTrip(
                $userId,
                $this->keypointsOutbound(),
                Carbon::today()->setTime(23, 34, 0),
                18 * 60 + 36, // 18分36秒
                'from_work'
            );

            // 回程：麥當勞 → 樹德科大（反向）
            $ret = $this->generateTrip(
                $userId,
                array_reverse($this->keypointsOutbound()),
                Carbon::tomorrow()->setTime(0, 21, 0),
                19 * 60 + 43, // 19分43秒
                'to_work'
            );

            echo "✅ GPS假資料生成完成！\n";
            echo "🚴 路線：樹德科技大學 ↔ 麥當勞（楠梓車站前/建楠路160號）\n";
            echo "📊 行程筆數：2\n";
            echo "📍 去程距離：約 {$out['distance']} 公里；回程：約 {$ret['distance']} 公里\n";
            echo "📍 GPS點數：去程 {$out['points']}、回程 {$ret['points']}\n";
        } catch (Exception $e) {
            echo "❌ 錯誤：".$e->getMessage()."\n";
        }
    }

    private function checkRequiredTables()
    {
        $required = ['users','gps_tracks','trips','carbon_emissions'];
        $missing = array_filter($required, fn($t)=>!Schema::hasTable($t));
        if ($missing) {
            throw new Exception("缺少資料表：".implode(', ', $missing));
        }
    }

    private function ensureUserExists($userId)
    {
        if (!DB::table('users')->where('id',$userId)->exists()) {
            DB::table('users')->insert([
                'id' => $userId,
                'name' => '測試用戶',
                'email' => "test{$userId}@example.com",
                'password' => bcrypt('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            echo "📝 已建立測試用戶 (ID: {$userId})\n";
        }
    }

    private function clearOldData($userId)
    {
        DB::table('carbon_emissions')->where('user_id',$userId)->delete();
        DB::table('trips')->where('user_id',$userId)->delete();
        DB::table('gps_tracks')->where('user_id',$userId)->delete();
        echo "🗑️ 已清除舊資料\n";
    }

    /**
     * 更精準的去程關鍵點（樹德科大 → 楠梓車站前麥當勞）
     * 資料來源：
     *  - 樹德科大：22.7632038,120.3757461
     *  - 旗楠路參考：22.733473,120.331490
     *  - 麥當勞(建楠路160號)：22.727122,120.326630
     */
    private function keypointsOutbound(): array
    {
        return [
            // 樹德科大 正門
            ['lat'=>22.7632038, 'lng'=>120.3757461, 'name'=>'樹德科技大學'],

            // 往西南沿橫山路離校（幾個過渡點，讓軌跡更平滑）
            ['lat'=>22.7589000, 'lng'=>120.3709000, 'name'=>'橫山路段'],
            ['lat'=>22.7538000, 'lng'=>120.3653000, 'name'=>'往燕巢邊界'],

            // 逐步向西（經度下降），靠近楠梓的外圍
            ['lat'=>22.7488000, 'lng'=>120.3588000, 'name'=>'往楠梓方向'],
            ['lat'=>22.7428000, 'lng'=>120.3510000, 'name'=>'接近都會區'],

            // 旗楠路附近參考點（實地址座標）
            ['lat'=>22.7334730, 'lng'=>120.3314900, 'name'=>'旗楠路 160 附近'],

            // 轉入建楠路一帶，逐步靠近車站
            ['lat'=>22.7318000, 'lng'=>120.3298000, 'name'=>'建楠路口'],
            ['lat'=>22.7299000, 'lng'=>120.3286000, 'name'=>'建楠路段'],

            // 終點：麥當勞 高雄楠梓店（建楠路160號）
            ['lat'=>22.7271220, 'lng'=>120.3266300, 'name'=>'麥當勞-高雄楠梓店'],
        ];
    }

    /**
     * 生成一趟行程 + 寫入 DB
     */
    private function generateTrip(int $userId, array $keyPoints, Carbon $startTime, int $totalSeconds, string $tripType): array
    {
        // 1) 先算總距離（公里）
        $distance = $this->calculateTotalDistance($keyPoints);

        // 2) 建立 trips
        $tripId = DB::table('trips')->insertGetId([
            'user_id'         => $userId,
            'start_time'      => $startTime,
            'end_time'        => (clone $startTime)->addSeconds($totalSeconds),
            'start_latitude'  => $keyPoints[0]['lat'],
            'start_longitude' => $keyPoints[0]['lng'],
            'end_latitude'    => $keyPoints[count($keyPoints)-1]['lat'],
            'end_longitude'   => $keyPoints[count($keyPoints)-1]['lng'],
            'distance'        => $distance,
            'transport_mode'  => 'motorcycle',
            'trip_type'       => $tripType,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // 3) 依 5 秒間隔生成 GPS 點
        $points   = $this->generateGpsPoints($keyPoints, $startTime, $totalSeconds, 5);
        $rows     = [];
        foreach ($points as $p) {
            $rows[] = [
                'user_id'      => $userId,
                'latitude'     => $p['lat'],
                'longitude'    => $p['lng'],
                'altitude'     => rand(10, 50),
                'speed'        => $p['speed'],
                'accuracy'     => rand(5, 15),
                'bearing'      => $p['bearing'],
                'recorded_at'  => $p['timestamp'],
                'device_type'  => 'ESP32',
                'is_processed' => false,
                'created_at'   => now(),
                'updated_at'   => now(),
            ];
        }
        DB::table('gps_tracks')->insert($rows);

        // 4) 碳排（用你原先係數）
        DB::table('carbon_emissions')->insert([
            'user_id'       => $userId,
            'trip_id'       => $tripId,
            'date'          => $startTime->toDateString(),
            'transport_mode'=> 'motorcycle',
            'distance'      => $distance,
            'carbon_amount' => $distance * 0.095,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return ['distance'=>$distance, 'points'=>count($points)];
    }

    private function generateGpsPoints(array $keyPoints, Carbon $startTime, int $totalSeconds, int $intervalSec): array
    {
        $gpsData       = [];
        $currentTime   = $startTime->copy();
        $totalPoints   = (int)ceil($totalSeconds / $intervalSec);
        $segments      = count($keyPoints) - 1;
        $pointsPerSeg  = max(1, (int)floor($totalPoints / $segments));
        $pointIndex    = 0;

        for ($i = 0; $i < $segments; $i++) {
            $A = $keyPoints[$i];
            $B = $keyPoints[$i+1];

            $segPoints = ($i === $segments - 1) ? ($totalPoints - $pointIndex) : $pointsPerSeg;
            $segPoints = max(1, $segPoints);

            for ($j = 0; $j < $segPoints; $j++) {
                $t   = $segPoints <= 1 ? 1.0 : $j / ($segPoints - 1);
                $lat = $A['lat'] + ($B['lat'] - $A['lat']) * $t;
                $lng = $A['lng'] + ($B['lng'] - $A['lng']) * $t;

                // 速度與偶發停等
                $base = 35;
                $var  = sin(($pointIndex) * 0.12) * 15;
                $spd  = max(0, min(60, $base + $var));
                if (rand(1,100) <= 4) $spd = 0;

                $bearing = $this->bearing($A['lat'], $A['lng'], $B['lat'], $B['lng']);

                $gpsData[] = [
                    'lat'       => round($lat, 8),
                    'lng'       => round($lng, 8),
                    'speed'     => round($spd, 2),
                    'bearing'   => round($bearing, 2),
                    'timestamp' => $currentTime->toDateTimeString(),
                ];
                $currentTime->addSeconds($intervalSec);
                $pointIndex++;
            }
        }
        return $gpsData;
    }

    private function calculateTotalDistance(array $pts): float
    {
        $km = 0.0;
        for ($i=0; $i<count($pts)-1; $i++) {
            $km += $this->haversine($pts[$i]['lat'], $pts[$i]['lng'], $pts[$i+1]['lat'], $pts[$i+1]['lng']);
        }
        return round($km, 2);
    }

    private function haversine($lat1,$lon1,$lat2,$lon2): float
    {
        $R = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2)**2 + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)**2;
        return $R * 2 * atan2(sqrt($a), sqrt(1-$a));
    }

    private function bearing($lat1,$lon1,$lat2,$lon2): float
    {
        $y = sin(deg2rad($lon2-$lon1)) * cos(deg2rad($lat2));
        $x = cos(deg2rad($lat1))*sin(deg2rad($lat2)) - sin(deg2rad($lat1))*cos(deg2rad($lat2))*cos(deg2rad($lon2-$lon1));
        $br = rad2deg(atan2($y,$x));
        return fmod(($br+360), 360);
    }
}
