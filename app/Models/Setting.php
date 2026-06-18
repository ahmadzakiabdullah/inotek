<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value', 'type'];

    /**
     * Retrieve a setting value from cache or DB.
     */
    public static function get(string $key, $default = null)
    {
        return Cache::rememberForever("settings.{$key}", function () use ($key, $default) {
            $setting = self::find($key);
            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Set/Update a setting value and clear the cache.
     */
    public static function set(string $key, $value, string $type = 'string')
    {
        $setting = self::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type]
        );
        Cache::forget("settings.{$key}");
        return $setting;
    }
}
