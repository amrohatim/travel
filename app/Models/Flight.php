<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Flight extends Model
{
    //
  protected $fillable = [
    'from',
    'to',
    'travel_date',
    'price',
    'seats',
    'office_id',
    'office_name', // أضف هنا
];

public function bookings()
{
    return $this->hasMany(Booking::class);
}


}
