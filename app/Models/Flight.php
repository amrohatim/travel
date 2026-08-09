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
        'has_discount',
        'discount_percentage',
        'discount_value',
    ];

    protected $casts = [
        'has_discount' => 'boolean',
        'discount_percentage' => 'integer',
        'discount_value' => 'integer',
    ];

    public function finalPrice(): int
    {
        if (! $this->hasDiscount()) {
            return (int) $this->price;
        }

        return max(0, (int) $this->price - (int) $this->discount_value);
    }

    public function hasDiscount(): bool
    {
        return (bool) $this->has_discount
            && (int) ($this->discount_percentage ?? 0) > 0
            && (int) ($this->discount_value ?? 0) > 0;
    }

    public function normalizedDiscount(): array
    {
        if (! $this->hasDiscount()) {
            return [
                'has_discount' => false,
                'discount_percentage' => null,
                'discount_value' => null,
                'final_price' => (int) $this->price,
            ];
        }

        return [
            'has_discount' => true,
            'discount_percentage' => (int) $this->discount_percentage,
            'discount_value' => (int) $this->discount_value,
            'final_price' => $this->finalPrice(),
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function officeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'office_id');
    }
}
