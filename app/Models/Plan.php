<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $price_cents
 * @property string $currency
 * @property string $interval
 * @property array<int, string>|null $features
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable(['name', 'slug', 'description', 'price_cents', 'currency', 'interval', 'features', 'is_active', 'sort_order'])]
class Plan extends Model
{
    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
            'price_cents' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
