<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    protected $fillable = [
        'name',
        'image',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
