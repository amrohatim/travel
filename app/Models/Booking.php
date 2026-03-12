<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'flight_id',
        'office_id',
        'traveler_id',
        'seats_booked',
        'total',
        'status',
        'demanded',
        'image',
    ];

    public function flight()
    {
        return $this->belongsTo(Flight::class);
    }

    public function traveler()
    {
        return $this->belongsTo(User::class, 'traveler_id');
    }

    public function office()
    {
        return $this->belongsTo(User::class, 'office_id');
    }

    public function seats()
    {
        return $this->hasMany(Seat::class);
    }

}
