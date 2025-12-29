<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantIsActive
{
    /**
     * Handle an incoming request.
     *
     * Verifica que el tenant del usuario autenticado esté activo.
     * Si el tenant está suspendido, bloquea el acceso.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Si no hay usuario autenticado, dejar pasar (otros middlewares se encargarán)
        if (!$user) {
            return $next($request);
        }

        // Obtener el tenant del usuario
        $tenant = $user->tenant;

        // Si el usuario no pertenece a ningún tenant, permitir acceso
        // (por ejemplo, super-admin sin tenant)
        if (!$tenant) {
            return $next($request);
        }

        // Verificar si el tenant está suspendido
        if ($tenant->status === 'suspended') {
            return response()->json([
                'success' => false,
                'message' => 'Tu empresa ha sido suspendida. Contacta a soporte para más información.',
                'error_code' => 'TENANT_SUSPENDED',
                'data' => [
                    'tenant_name' => $tenant->name,
                    'status' => $tenant->status,
                ],
            ], 403);
        }

        return $next($request);
    }
}
