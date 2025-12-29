<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar que el usuario esté autenticado
        if (!auth()->check()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autenticado',
                    'error' => 'UNAUTHENTICATED'
                ], 401);
            }
            return redirect()->route('login');
        }

        // Verificar que tenga el rol super_admin
        if (!auth()->user()->hasRole('super_admin')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acceso denegado. Se requiere rol de Super Administrador.',
                    'error' => 'FORBIDDEN_SUPER_ADMIN_REQUIRED'
                ], 403);
            }
            abort(403, 'Acceso denegado. Solo super administradores pueden acceder a esta sección.');
        }

        return $next($request);
    }
}
