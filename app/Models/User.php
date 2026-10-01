<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\UserObserver;
use Naluz\Database\Orm\Attributes\ObservedBy;
use Naluz\Database\Orm\HasFactory;
use Naluz\Database\Orm\Model;

#[ObservedBy(UserObserver::class)]
class User extends Model
{
    use HasFactory;

    protected array $fillable = ['name', 'email', 'password'];
    protected array $hidden = ['password'];
    protected array $casts = ['password' => 'hashed'];

    public function posts(): \Naluz\Database\Orm\Relations\HasMany
    {
        return $this->hasMany(Post::class, 'user_id');
    }
}
