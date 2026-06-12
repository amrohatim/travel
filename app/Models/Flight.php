<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Flight extends Model
{
    protected $fillable = [
        'from',
        'to',
        'travel_date',
        'departure_time',
        'price',
        'seats',
        'office_id',
        'office_name',
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function officeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'office_id');
    }
}
