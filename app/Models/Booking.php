<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    //
    protected $fillable = [
    'flight_id',
    'traveler_id',
    'seats_booked',
    'status',
];
public function flight()
{
    return $this->belongsTo(Flight::class);
}

public function traveler()
{
    return $this->belongsTo(User::class, 'traveler_id');
}


}
