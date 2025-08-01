<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'latitude',
        'longitude',
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
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    /**
     * Get the routes that the user manages as a cobrador.
     */
    public function routes(): HasMany
    {
        return $this->hasMany(Route::class, 'cobrador_id');
    }

    /**
     * Get the credits that belong to the user as a client.
     */
    public function credits(): HasMany
    {
        return $this->hasMany(Credit::class, 'client_id');
    }

    /**
     * Get the payments where the user is the client.
     */
    public function paymentsAsClient(): HasMany
    {
        return $this->hasMany(Payment::class, 'client_id');
    }

    /**
     * Get the payments where the user is the cobrador.
     */
    public function paymentsAsCobrador(): HasMany
    {
        return $this->hasMany(Payment::class, 'cobrador_id');
    }

    /**
     * Get the cash balances for the user as a cobrador.
     */
    public function cashBalances(): HasMany
    {
        return $this->hasMany(CashBalance::class, 'cobrador_id');
    }

    /**
     * Get the notifications for the user.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Get the routes that the user belongs to as a client.
     */
    public function clientRoutes(): BelongsToMany
    {
        return $this->belongsToMany(Route::class, 'client_routes', 'client_id', 'route_id')
                    ->withTimestamps();
    }

    /**
     * Check if the user has a specific role.
     */
    public function hasRole($role): bool
    {
        return $this->hasRole($role);
    }

    /**
     * Check if the user has a specific permission.
     */
    public function hasPermission($permission): bool
    {
        return $this->hasPermissionTo($permission);
    }

    /**
     * Get the location as an array with latitude and longitude.
     */
    public function getLocationAttribute(): ?array
    {
        if ($this->latitude && $this->longitude) {
            return [
                'latitude' => (float) $this->latitude,
                'longitude' => (float) $this->longitude,
            ];
        }
        return null;
    }

    /**
     * Set the location from an array with latitude and longitude.
     */
    public function setLocationAttribute($value): void
    {
        if (is_array($value) && isset($value['latitude']) && isset($value['longitude'])) {
            $this->attributes['latitude'] = $value['latitude'];
            $this->attributes['longitude'] = $value['longitude'];
        }
    }

    /**
     * Check if the user has a location set.
     */
    public function hasLocation(): bool
    {
        return !is_null($this->latitude) && !is_null($this->longitude);
    }

    /**
     * Get the distance to another user in kilometers.
     */
    public function distanceTo(User $user): ?float
    {
        if (!$this->hasLocation() || !$user->hasLocation()) {
            return null;
        }

        $lat1 = deg2rad($this->latitude);
        $lon1 = deg2rad($this->longitude);
        $lat2 = deg2rad($user->latitude);
        $lon2 = deg2rad($user->longitude);

        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;

        $a = sin($dlat / 2) * sin($dlat / 2) + cos($lat1) * cos($lat2) * sin($dlon / 2) * sin($dlon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return 6371 * $c; // Earth's radius in kilometers
    }
}
