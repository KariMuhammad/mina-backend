<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $table = 'app_settings';

    protected $fillable = ['key', 'value', 'label', 'type'];

    /**
     * Get a setting value by key.
     */
    public static function get(string $key): ?string
    {
        return static::where('key', $key)->value('value');
    }

    /**
     * Set a setting value by key.
     */
    public static function set(string $key, ?string $value): void
    {
        static::where('key', $key)->update(['value' => $value]);
    }
}
