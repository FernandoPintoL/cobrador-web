<?php

namespace App\Traits;

use App\Models\Tenant;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    /**
     * Boot the trait
     */
    protected static function bootBelongsToTenant()
    {
        // Aplicar el scope global para filtrar automáticamente por tenant_id
        static::addGlobalScope(new TenantScope);

        // Asignar tenant_id automáticamente al crear un nuevo registro
        static::creating(function ($model) {
            // Solo asignar si no tiene tenant_id ya definido
            if (empty($model->tenant_id) && auth()->check() && auth()->user()->tenant_id) {
                $model->tenant_id = auth()->user()->tenant_id;
            }
        });
    }

    /**
     * Relación: tenant al que pertenece
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope para queries sin filtro de tenant (úsalo con cuidado)
     * Ejemplo: User::withoutTenantScope()->get()
     */
    public function scopeWithoutTenantScope($query)
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }

    /**
     * Scope para queries de un tenant específico
     * Ejemplo: User::forTenant(2)->get()
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->withoutGlobalScope(TenantScope::class)
                     ->where('tenant_id', $tenantId);
    }
}
