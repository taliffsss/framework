<?php

declare(strict_types=1);

namespace App\Models;

use Naluz\Database\Orm\HasFactory;
use Naluz\Database\Orm\Model;
use Naluz\Database\Orm\SoftDeletes;

class Post extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected array $fillable = ['user_id', 'title', 'body', 'published'];
    protected array $casts = ['published' => 'bool'];

    public function author(): \Naluz\Database\Orm\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Usage: Post::published()->get() */
    public function scopePublished(\Naluz\Database\Orm\Builder $query): void
    {
        $query->where('published', true);
    }
}
