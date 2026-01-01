<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuración de frecuencias de pago por tenant.
 *
 * Permite centralizar las reglas de negocio para cada tipo de frecuencia:
 * - Diaria: 24 cuotas / ~28 días (fijo)
 * - Semanal, Quincenal, Mensual: configuración flexible
 */
class LoanFrequency extends Model
{
    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'description',
        'is_enabled',
        'is_fixed_duration',
        'fixed_installments',
        'fixed_duration_days',
        'period_days',
        'default_installments',
        'min_installments',
        'max_installments',
        'interest_rate',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_fixed_duration' => 'boolean',
        'fixed_installments' => 'integer',
        'fixed_duration_days' => 'integer',
        'period_days' => 'integer',
        'default_installments' => 'integer',
        'min_installments' => 'integer',
        'max_installments' => 'integer',
        'interest_rate' => 'decimal:2',
    ];

    /**
     * Relación con el tenant (empresa)
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope para obtener solo frecuencias habilitadas
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * Scope para obtener frecuencias por tenant
     */
    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope para obtener frecuencias activas de un tenant
     */
    public function scopeEnabledForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId)
                    ->where('is_enabled', true)
                    ->orderBy('period_days', 'asc');
    }

    /**
     * Obtener frecuencia por código
     */
    public static function findByCode(int $tenantId, string $code): ?self
    {
        return self::where('tenant_id', $tenantId)
                   ->where('code', $code)
                   ->first();
    }

    /**
     * Validar si un número de cuotas está en el rango permitido
     */
    public function isValidInstallments(int $installments): bool
    {
        // Si es duración fija, solo acepta el valor fijo
        if ($this->is_fixed_duration) {
            return $installments === $this->fixed_installments;
        }

        // Si tiene rangos definidos, validar
        if ($this->min_installments !== null && $installments < $this->min_installments) {
            return false;
        }

        if ($this->max_installments !== null && $installments > $this->max_installments) {
            return false;
        }

        return true;
    }

    /**
     * Calcular duración estimada en días basado en el número de cuotas
     */
    public function calculateEstimatedDuration(int $installments): int
    {
        if ($this->is_fixed_duration) {
            return $this->fixed_duration_days;
        }

        return $installments * $this->period_days;
    }
}
