<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Seat extends Model
{
    protected $fillable = [
        'traveler_id',
        'flight_id',
        'booking_id',
        'traveler_name',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function flight()
    {
        return $this->belongsTo(Flight::class);
    }

    public function traveler()
    {
        return $this->belongsTo(User::class, 'traveler_id');
    }
}
