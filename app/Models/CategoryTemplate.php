<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $type
 * @property string|null $group
 * @property string $name
 * @property string $slug
 * @property string|null $icon
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable(['type', 'group', 'name', 'slug', 'icon', 'is_active', 'sort_order'])]
class CategoryTemplate extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
