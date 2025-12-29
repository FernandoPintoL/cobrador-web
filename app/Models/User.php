<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
// use App\Traits\BelongsToTenant; // REMOVIDO: User no debe usar este trait para evitar loops infinitos
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /**
     * Scope: case-insensitive name search (DB-agnostic)
     */
    /*public function scopeNameLikeInsensitive($query, string $term)
    {
        $like = '%'.strtolower($term).'%';

        return $query->whereRaw('LOWER(name) LIKE ?', [$like]);
    }*/

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    // ===========================
    // Normalizadores de atributos
    // ===========================

    /**
     * Set the user's name in uppercase (trimmed, UTF-8 safe).
     */
    public function setNameAttribute(?string $value): void
    {
        if ($value === null) {
            $this->attributes['name'] = null;

            return;
        }
        $trimmed = trim($value);
        $this->attributes['name'] = mb_strtoupper($trimmed, 'UTF-8');
    }

    /**
     * Normalize email to lowercase (trimmed).
     */
    public function setEmailAttribute(?string $value): void
    {
        if ($value === null) {
            $this->attributes['email'] = null;

            return;
        }
        $trimmed = trim($value);
        $this->attributes['email'] = mb_strtolower($trimmed, 'UTF-8');
    }

    /**
     * Set CI (identity) to uppercase (trimmed).
     */
    public function setCiAttribute(?string $value): void
    {
        if ($value === null) {
            $this->attributes['ci'] = null;

            return;
        }
        $trimmed = trim($value);
        $this->attributes['ci'] = mb_strtoupper($trimmed, 'UTF-8');
    }

    /**
     * Set address to uppercase (trimmed) when provided.
     */
    public function setAddressAttribute(?string $value): void
    {
        if ($value === null) {
            $this->attributes['address'] = null;

            return;
        }
        $trimmed = trim($value);
        $this->attributes['address'] = mb_strtoupper($trimmed, 'UTF-8');
    }

    // Constantes para las categorías de clientes
    const CLIENT_CATEGORY_VIP = 'A';

    const CLIENT_CATEGORY_NORMAL = 'B';

    const CLIENT_CATEGORY_BAD = 'C';

    // Array con las categorías disponibles
    const CLIENT_CATEGORIES = [
        self::CLIENT_CATEGORY_VIP => 'Cliente VIP',
        self::CLIENT_CATEGORY_NORMAL => 'Cliente Normal',
        self::CLIENT_CATEGORY_BAD => 'Mal Cliente',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id', // ID de la empresa (multi-tenancy)
        'name',
        'email',
        'password',
        'phone',
        'address',
        'profile_image',
        'latitude',
        'longitude',
        'assigned_cobrador_id',
        'assigned_manager_id',
        'ci', // Nuevo campo para el CI
        'client_category', // Nuevo campo para la categoría del cliente
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
     * Get the clients assigned to this cobrador.
     */
    public function assignedClients(): HasMany
    {
        return $this->hasMany(User::class, 'assigned_cobrador_id');
    }

    /**
     * Get the cobrador assigned to this client.
     */
    public function assignedCobrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_cobrador_id');
    }

    /**
     * Get the cobradores assigned to this manager.
     */
    public function assignedCobradores(): HasMany
    {
        return $this->hasMany(User::class, 'assigned_manager_id');
    }

    /**
     * Get the manager assigned to this cobrador.
     */
    public function assignedManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_manager_id');
    }

    /**
     * Get the clients assigned directly to this manager.
     */
    public function assignedClientsDirectly(): HasMany
    {
        return $this->hasMany(User::class, 'assigned_manager_id')
            ->whereHas('roles', function ($q) {
                $q->where('name', 'client');
            })
            ->whereDoesntHave('roles', function ($q) {
                $q->where('name', 'manager');
            });
    }

    /**
     * Get the manager assigned directly to this client.
     */
    public function assignedManagerDirectly(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_manager_id');
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
     * Backward-compatible alias: payments of this user as client.
     * Many modules (e.g., MapController) reference User::payments.
     */
    public function payments(): HasMany
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
        return ! is_null($this->latitude) && ! is_null($this->longitude);
    }

    /**
     * Get the distance to another user in kilometers.
     */
    public function distanceTo(User $user): ?float
    {
        if (! $this->hasLocation() || ! $user->hasLocation()) {
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

    /**
     * Get all available client categories.
     */
    public static function getClientCategories(): array
    {
        // Intentar obtener desde la tabla si existe y tiene datos activos
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('client_categories')) {
                $rows = \App\Models\ClientCategory::query()->active()->orderBy('code')->get(['code', 'name']);
                if ($rows->count() > 0) {
                    return $rows->pluck('name', 'code')->toArray();
                }
            }
        } catch (\Throwable $e) {
            // Fallback silencioso a constantes si aún no existe la tabla o hay error
        }

        return self::CLIENT_CATEGORIES;
    }

    /**
     * Get the client category name.
     */
    public function getClientCategoryNameAttribute(): ?string
    {
        if (! $this->client_category) {
            return null;
        }
        try {
            if (relationLoaded('clientCategory') && $this->clientCategory) {
                return $this->clientCategory->name;
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('client_categories')) {
                $row = \App\Models\ClientCategory::query()->where('code', $this->client_category)->first();
                if ($row) {
                    return $row->name;
                }
            }
        } catch (\Throwable $e) {
            // ignore and fallback
        }
        if (isset(self::CLIENT_CATEGORIES[$this->client_category])) {
            return self::CLIENT_CATEGORIES[$this->client_category];
        }

        return null;
    }

    /**
     * Check if the user is a VIP client.
     */
    public function isVipClient(): bool
    {
        return $this->hasRole('client') && $this->client_category === self::CLIENT_CATEGORY_VIP;
    }

    /**
     * Check if the user is a normal client.
     */
    public function isNormalClient(): bool
    {
        return $this->hasRole('client') && $this->client_category === self::CLIENT_CATEGORY_NORMAL;
    }

    /**
     * Check if the user is a bad client.
     */
    public function isBadClient(): bool
    {
        return $this->hasRole('client') && $this->client_category === self::CLIENT_CATEGORY_BAD;
    }

    /**
     * Scope to filter clients by category.
     */
    public function scopeWithClientCategory($query, string $category)
    {
        return $query->whereHas('roles', function ($q) {
            $q->where('name', 'client');
        })->where('client_category', $category);
    }

    /**
     * Scope to get only VIP clients.
     */
    public function scopeVipClients($query)
    {
        return $query->withClientCategory(self::CLIENT_CATEGORY_VIP);
    }

    /**
     * Scope to get only normal clients.
     */
    public function scopeNormalClients($query)
    {
        return $query->withClientCategory(self::CLIENT_CATEGORY_NORMAL);
    }

    /**
     * Scope to get only bad clients.
     */
    public function scopeBadClients($query)
    {
        return $query->withClientCategory(self::CLIENT_CATEGORY_BAD);
    }

    /**
     * Relationship: category by code.
     */
    public function clientCategory(): BelongsTo
    {
        return $this->belongsTo(ClientCategory::class, 'client_category', 'code');
    }

    /**
     * Domain rule: whether this client can receive new credits (delegates to category model).
     */
    public function canReceiveNewCredit(): bool
    {
        try {
            if ($this->clientCategory) {
                return $this->clientCategory->canCreateNewCredit();
            }
        } catch (\Throwable $e) {
            // If relation/table is missing, default to allow to avoid hard failure
        }

        return true;
    }

    /**
     * If blocked, returns the reason message.
     */
    public function creditCreationBlockedReason(): ?string
    {
        try {
            if ($this->clientCategory) {
                return $this->clientCategory->creditCreationBlockedReason();
            }
        } catch (\Throwable $e) {
        }

        return null;
    }

    /**
     * Compute total overdue installments across active credits.
     */
    public function getTotalOverdueInstallments(): int
    {
        $total = 0;
        $credits = $this->credits()->where('status', 'active')->get();
        foreach ($credits as $credit) {
            $expected = $credit->getExpectedInstallments();
            // Usar el método correcto que cuenta cuotas completadas (no pagos individuales)
            $completed = $credit->getCompletedInstallmentsCount();
            $overdue = max(0, $expected - $completed);
            $total += $overdue;
        }

        return $total;
    }

    /**
     * Recalculate and persist client category based on overdue installments using client_categories ranges.
     */
    public function recalculateCategoryFromOverdues(): ?string
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('client_categories')) {
                return $this->client_category; // keep current when table missing
            }
        } catch (\Throwable $e) {
            return $this->client_category;
        }

        $overdueCount = $this->getTotalOverdueInstallments();
        $matching = \App\Models\ClientCategory::findForOverdueCount($overdueCount);
        if ($matching && $this->client_category !== $matching->code) {
            $this->update(['client_category' => $matching->code]);
        }

        return $this->client_category;
    }

    /**
     * Get photos associated with the user.
     */
    public function photos(): HasMany
    {
        return $this->hasMany(UserPhoto::class);
    }

    /**
     * Get the tenant that owns the user.
     * NOTA: No usa BelongsToTenant trait para evitar loops infinitos durante auth.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
