<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'image',
        'phone',
        'backup_number',
        'bankak_name',
        'bankak_number',
        'password',
        'role',
        'is_suspended',
        'suspension_reason',
        'suspended_at',
        'parent_company_id',
        'state_id',
    ];


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_suspended' => 'boolean',
            'suspended_at' => 'datetime',
        ];
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(UserDeviceToken::class);
    }

    public function parentCompany(): BelongsTo
    {
        return $this->belongsTo(ParentCompany::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function location(): HasOne
    {
        return $this->hasOne(OfficeLocation::class, 'office_id');
    }

    public function assignedOffices(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'office_support_user',
            'support_user_id',
            'office_id',
        )->withTimestamps();
    }

    public function assignedSupports(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'office_support_user',
            'office_id',
            'support_user_id',
        )->withTimestamps();
    }
}
