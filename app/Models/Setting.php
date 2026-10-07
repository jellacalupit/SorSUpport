<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public const DECLARATION = 'ticket_declaration';

    /**
     * Used until the SDS admin writes their own declaration in System Settings.
     */
    public const DEFAULT_DECLARATION = 'I declare that the information I am submitting is true and correct to the best of my knowledge, and I understand that a false or malicious complaint may be dealt with under the Student Handbook. I consent to the collection and use of this information by Sorsogon State University to act on my concern, in accordance with the Data Privacy Act of 2012.';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    public static function read(string $key, ?string $default = null): ?string
    {
        $value = static::query()->find($key)?->value;

        return filled($value) ? $value : $default;
    }

    public static function write(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * The statement a student must agree to before submitting a ticket.
     */
    public static function declaration(): string
    {
        return (string) static::read(self::DECLARATION, self::DEFAULT_DECLARATION);
    }
}
