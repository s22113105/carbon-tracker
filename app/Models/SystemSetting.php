<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'description',
        'type',
        'group'
    ];

    protected $casts = [
        'value' => 'string',
    ];

    // 當設定更新時，清除快取
    protected static function booted()
    {
        static::saved(function ($setting) {
            Cache::tags(['system_settings'])->flush();
        });

        static::deleted(function ($setting) {
            Cache::tags(['system_settings'])->flush();
        });
    }

    /**
     * 取得設定值
     */
    public static function get($key, $default = null)
    {
        return Cache::tags(['system_settings'])->remember("setting.{$key}", 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    /**
     * 設定值
     */
    public static function set($key, $value, $description = null)
    {
        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'description' => $description
            ]
        );
    }

    /**
     * 取得布林值
     */
    public static function getBool($key, $default = false)
    {
        $value = static::get($key, $default);
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * 取得整數值
     */
    public static function getInt($key, $default = 0)
    {
        $value = static::get($key, $default);
        return (int) $value;
    }

    /**
     * 取得浮點數值
     */
    public static function getFloat($key, $default = 0.0)
    {
        $value = static::get($key, $default);
        return (float) $value;
    }

    /**
     * 取得陣列值（JSON）
     */
    public static function getArray($key, $default = [])
    {
        $value = static::get($key, $default);
        
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : $default;
        }
        
        return is_array($value) ? $value : $default;
    }

    /**
     * 設定陣列值（轉為JSON）
     */
    public static function setArray($key, $value, $description = null)
    {
        return static::set($key, json_encode($value), $description);
    }

    /**
     * 檢查設定是否存在
     */
    public static function has($key)
    {
        return static::where('key', $key)->exists();
    }

    /**
     * 刪除設定
     */
    public static function forget($key)
    {
        return static::where('key', $key)->delete();
    }

    /**
     * 取得所有設定（依群組分類）
     */
    public static function getByGroup($group = null)
    {
        $query = static::query();
        
        if ($group) {
            $query->where('group', $group);
        }
        
        return $query->orderBy('group')->orderBy('key')->get();
    }

    /**
     * 批次設定
     */
    public static function setMultiple(array $settings)
    {
        foreach ($settings as $key => $value) {
            static::set($key, $value);
        }
        
        return true;
    }
}