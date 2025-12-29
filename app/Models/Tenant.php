<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'logo',
        'email',
        'phone',
        'address',
        'status',
        'trial_ends_at',
        'monthly_price',
        'settings',
    ];

    protected $casts = [
        'trial_ends_at' => 'date',
        'monthly_price' => 'decimal:2',
        'settings' => 'array',
    ];

    /**
     * Boot method to auto-generate slug from name
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tenant) {
            if (empty($tenant->slug)) {
                $tenant->slug = Str::slug($tenant->name);
            }
        });
    }

    /**
     * Relación: usuarios del tenant
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Relación: configuraciones del tenant
     */
    public function settings(): HasMany
    {
        return $this->hasMany(TenantSetting::class);
    }

    /**
     * Relación: suscripciones/facturas del tenant
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class);
    }

    /**
     * Obtener una configuración específica del tenant
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getSetting(string $key, $default = null)
    {
        $setting = $this->settings()->where('key', $key)->first();

        if (!$setting) {
            return $default;
        }

        // Convertir el valor según el tipo
        return match ($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $setting->value,
            'decimal' => (float) $setting->value,
            'json' => json_decode($setting->value, true),
            default => $setting->value,
        };
    }

    /**
     * Establecer una configuración del tenant
     *
     * @param string $key
     * @param mixed $value
     * @param string $type
     * @return TenantSetting
     */
    public function setSetting(string $key, $value, string $type = 'string')
    {
        // Convertir el valor a string para almacenamiento
        $storedValue = match ($type) {
            'boolean' => $value ? 'true' : 'false',
            'json' => json_encode($value),
            default => (string) $value,
        };

        return $this->settings()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $storedValue,
                'type' => $type,
            ]
        );
    }

    /**
     * Verificar si el tenant está activo
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Verificar si el tenant está en período de prueba
     */
    public function isOnTrial(): bool
    {
        return $this->status === 'trial' && $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Verificar si el trial ha expirado
     */
    public function trialHasExpired(): bool
    {
        return $this->status === 'trial' && $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    /**
     * Verificar si el tenant está suspendido
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeTrial($query)
    {
        return $query->where('status', 'trial');
    }

    public function scopeSuspended($query)
    {
        return $query->where('status', 'suspended');
    }
}
