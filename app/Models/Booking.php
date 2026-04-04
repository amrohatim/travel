<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'serial_number',
        'flight_id',
        'office_id',
        'traveler_id',
        'seats_booked',
        'total',
        'status',
        'demanded',
        'image',
    ];

    protected static function booted(): void
    {
        static::creating(function (Booking $booking): void {
            if (! empty($booking->serial_number)) {
                return;
            }

            do {
                $serial = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
            } while (self::query()->where('serial_number', $serial)->exists());

            $booking->serial_number = $serial;
        });
    }

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
