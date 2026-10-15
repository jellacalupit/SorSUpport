<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A setting the SDS admin can change in System Settings, stored as a key and a value.
 */
class SystemSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::query()->whereKey($key)->value('value');

        return $value === null ? $default : $value;
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value === null ? null : (string) $value]);
    }
}
