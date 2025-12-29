<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        // Si no hay usuario autenticado, dejar pasar (el middleware 'auth' lo manejará)
        if (!$user) {
            return $next($request);
        }

        // Validar que el usuario tenga un tenant asignado
        if (!$user->tenant_id) {
            return response()->json([
                'message' => 'Usuario sin empresa asignada. Contacte al administrador.',
                'error' => 'NO_TENANT_ASSIGNED'
            ], 403);
        }

        // Cargar el tenant del usuario
        $tenant = $user->tenant;

        // Validar que el tenant exista
        if (!$tenant) {
            return response()->json([
                'message' => 'Empresa no encontrada. Contacte al administrador.',
                'error' => 'TENANT_NOT_FOUND'
            ], 404);
        }

        // Validar si el tenant está suspendido
        if ($tenant->isSuspended()) {
            return response()->json([
                'message' => 'Cuenta suspendida. Contacte al administrador para reactivar su servicio.',
                'error' => 'TENANT_SUSPENDED'
            ], 403);
        }

        // Validar si el trial ha expirado
        if ($tenant->trialHasExpired()) {
            // Suspender automáticamente el tenant
            $tenant->update(['status' => 'suspended']);

            return response()->json([
                'message' => 'Período de prueba expirado. Contacte al administrador para activar su suscripción.',
                'error' => 'TRIAL_EXPIRED'
            ], 402); // 402 Payment Required
        }

        // Todo OK, continuar con la petición
        return $next($request);
    }
}
