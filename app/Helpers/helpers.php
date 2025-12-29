<?php

use App\Models\Tenant;

if (!function_exists('tenant_setting')) {
    /**
     * Obtener una configuración del tenant actual
     *
     * @param string $key Clave de la configuración
     * @param mixed $default Valor por defecto si no existe
     * @return mixed
     */
    function tenant_setting(string $key, $default = null)
    {
        if (!auth()->check() || !auth()->user()->tenant_id) {
            return $default;
        }

        $tenant = auth()->user()->tenant;

        if (!$tenant) {
            return $default;
        }

        return $tenant->getSetting($key, $default);
    }
}

if (!function_exists('current_tenant')) {
    /**
     * Obtener el tenant actual del usuario autenticado
     *
     * @return Tenant|null
     */
    function current_tenant(): ?Tenant
    {
        if (!auth()->check() || !auth()->user()->tenant_id) {
            return null;
        }

        return auth()->user()->tenant;
    }
}

if (!function_exists('tenant_id')) {
    /**
     * Obtener el ID del tenant actual
     *
     * @return int|null
     */
    function tenant_id(): ?int
    {
        if (!auth()->check()) {
            return null;
        }

        return auth()->user()->tenant_id;
    }
}
