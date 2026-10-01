<?php

declare(strict_types=1);

namespace App\Models;

use Naluz\Database\Orm\HasFactory;
use Naluz\Database\Orm\Model;

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
