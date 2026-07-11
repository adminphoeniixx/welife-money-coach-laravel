<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property string|null $value
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    /**
     * Return all settings as a key => value map.
     *
     * @return array<string, string|null>
     */
    public static function map(): array
    {
        return static::query()->pluck('value', 'key')->all();
    }

    /**
     * Persist a single setting value.
     */
    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => (string) $value]);
    }
}
