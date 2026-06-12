<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficeLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'office_id',
        'lat',
        'lng',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(User::class, 'office_id');
    }
}
