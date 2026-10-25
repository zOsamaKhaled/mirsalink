<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One team-wide setting, stored as JSON under its key.
 *
 * @property string $key
 * @property array<string, mixed> $value
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function read(string $key): ?array
    {
        return static::query()->find($key)?->value;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public static function write(string $key, array $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
