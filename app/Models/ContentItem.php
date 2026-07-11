<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $type
 * @property string $title
 * @property string $slug
 * @property string|null $body
 * @property bool $is_published
 * @property int $sort_order
 */
#[Fillable(['type', 'title', 'slug', 'body', 'is_published', 'published_at', 'sort_order'])]
class ContentItem extends Model
{
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }
}
