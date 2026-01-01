<?php
namespace App\Http\Controllers\Api;

use App\Models\CashBalance;
use App\Models\Credit;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends BaseController
{
    /**
     * Register a new user.
     *
     * Soporta validación con scope por tenant si se proporciona tenant_id
     * o si el usuario está autenticado.
     */
    public function register(Request $request)
    {
        // Determinar el tenant_id para scope de validación
        $tenantId = null;

        // Opción 1: tenant_id explícito en el request
        if ($request->filled('tenant_id')) {
            $tenantId = $request->tenant_id;
        }
        // Opción 2: usuario autenticado (admin creando usuario)
        elseif (Auth::check()) {
            $tenantId = Auth::user()->tenant_id;
        }

        // Construir reglas de validación con scope por tenant si aplica
        $validationRules = [
            'name'      => 'required|string|max:255',
            'password'  => 'required|string|min:8|confirmed',
            'address'   => 'nullable|string',
            'tenant_id' => 'nullable|integer|exists:tenants,id',
        ];

        // Aplicar scope por tenant si tenemos tenant_id
        if ($tenantId !== null) {
            $validationRules['email'] = [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId);
                })
            ];
            $validationRules['phone'] = [
                'nullable',
                'string',
                'max:20',
                Rule::unique('users', 'phone')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId);
                })
            ];
            $validationRules['ci'] = [
                'required',
                'string',
                'max:20',
                Rule::unique('users', 'ci')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId);
                })
            ];
        } else {
            // Sin tenant_id: usar validación global (backward compatible)
            $validationRules['email'] = 'required|string|email|max:255|unique:users';
            $validationRules['phone'] = 'nullable|string|max:20|unique:users';
            $validationRules['ci'] = 'required|string|max:20|unique:users,ci';
        }

        $request->validate($validationRules);

        $userData = [
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'phone'    => $request->phone,
            'address'  => $request->address,
            'ci'       => $request->ci,
        ];

        // Agregar tenant_id si está disponible
        if ($tenantId !== null) {
            $userData['tenant_id'] = $tenantId;
        }

        $user = User::create($userData);

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->sendResponse([
            'user'  => $user,
            'token' => $token,
        ], 'Usuario registrado exitosamente');
    }

    /**
     * Login user.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email_or_phone' => 'required|string',
            'password'       => 'required',
        ]);

        $emailOrPhone = $request->email_or_phone;

        // Determinar si es email o teléfono
        $isEmail = filter_var($emailOrPhone, FILTER_VALIDATE_EMAIL);

        if ($isEmail) {
            // Buscar por email
            $user = User::where('email', $emailOrPhone)->first();
        } else {
            // Buscar por teléfono
            $user = User::where('phone', $emailOrPhone)->first();
        }

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email_or_phone' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        // Crear token para API
        $token = $user->createToken('auth_token')->plainTextToken;
        $user->load('roles', 'permissions');

        // Obtener estadísticas optimizadas según el rol
        $statistics = $this->getLoginStatistics($user);

        // Obtener configuraciones de seguridad del tenant
        $tenant = $user->tenant;
        $securitySettings = [
            'auto_logout_enabled' => $tenant
                ? (bool) $tenant->getSetting('enable_auto_logout_on_app_switch', true)
                : true, // Por defecto habilitado si no tiene tenant
        ];

        return $this->sendResponse([
            'user'              => $user,
            'token'             => $token,
            'statistics'        => $statistics,
            'security_settings' => $securitySettings,
        ], 'Inicio de sesión exitoso');
    }

    /**
     * Obtener estadísticas optimizadas al hacer login según el rol del usuario
     */
    private function getLoginStatistics(User $user)
    {
        if ($user->hasRole('cobrador')) {
            return $this->getCobradorStatistics($user);
        }

        if ($user->hasRole('manager')) {
            return $this->getManagerStatistics($user);
        }

        if ($user->hasRole('admin')) {
            return $this->getAdminStatistics($user);
        }

        // Para clientes o usuarios sin roles específicos
        return $this->getClientStatistics($user);
    }

    /**
     * Estadísticas para Cobrador
     */
    private function getCobradorStatistics(User $user)
    {
        $cobradorId = $user->id;
        $today      = today();
        $monthStart = now()->startOfMonth();

        // Calcular metas
        $cobros = (float) Payment::where('cobrador_id', $cobradorId)
            ->whereBetween('payment_date', [$monthStart, now()])
            ->where('status', 'paid')
            ->sum('amount');
        $meta       = 25000.00; // Esto podría venir de una tabla de metas
        $porcentaje = $meta > 0 ? round(($cobros / $meta) * 100, 2) : 0;

        return [
            'summary' => [
                'total_clientes'      => $user->assignedClients()->count(),
                'creditos_activos'    => Credit::where('created_by', $cobradorId)
                    ->where('status', 'active')
                    ->count(),
                'saldo_total_cartera' => (float) Credit::where('created_by', $cobradorId)
                    ->where('status', 'active')
                    ->sum('balance'),
            ],
            'hoy'     => [
                'cobros_realizados' => Payment::where('cobrador_id', $cobradorId)
                    ->whereDate('payment_date', $today)
                    ->where('status', 'paid')
                    ->count(),
                'monto_cobrado'     => (float) Payment::where('cobrador_id', $cobradorId)
                    ->whereDate('payment_date', $today)
                    ->where('status', 'paid')
                    ->sum('amount'),
                'pendientes_hoy'    => Payment::where('cobrador_id', $cobradorId)
                    ->whereDate('payment_date', $today)
                    ->where('status', 'pending')
                    ->count(),
                'efectivo_en_caja'  => (float) CashBalance::where('cobrador_id', $cobradorId)
                    ->latest()
                    ->value('final_amount') ?? 0,
            ],
            'alertas' => [
                'pagos_atrasados'           => Payment::where('cobrador_id', $cobradorId)
                    ->where('status', 'overdue')
                    ->count(),
                'clientes_sin_ubicacion'    => $user->assignedClients()
                    ->whereNull('latitude')
                    ->orWhereNull('longitude')
                    ->count(),
                'creditos_por_vencer_7dias' => Credit::where('created_by', $cobradorId)
                    ->where('status', 'active')
                    ->where('end_date', '<=', now()->addDays(7))
                    ->where('end_date', '>', now())
                    ->count(),
            ],
            'metas'   => [
                'cobros_mes_actual'       => $cobros,
                'meta_mes'                => $meta,
                'porcentaje_cumplimiento' => $porcentaje,
            ],
        ];
    }

    /**
     * Estadísticas para Manager
     */
    private function getManagerStatistics(User $user)
    {
        $managerId  = $user->id;
        $today      = today();
        $monthStart = now()->startOfMonth();

        // Obtener IDs de cobradores asignados
        $cobradorIds = $user->assignedCobradores()->pluck('id');

        // Top 5 cobradores del mes
        $topCobradores = Payment::whereIn('cobrador_id', $cobradorIds)
            ->whereBetween('payment_date', [$monthStart, now()])
            ->where('status', 'paid')
            ->select('cobrador_id', DB::raw('SUM(amount) as total_cobrado'))
            ->groupBy('cobrador_id')
            ->orderByDesc('total_cobrado')
            ->limit(5)
            ->get()
            ->map(function ($item, $index) {
                $cobrador = User::find($item->cobrador_id);
                return [
                    'id'         => $item->cobrador_id,
                    'nombre'     => $cobrador->name ?? 'N/A',
                    'cobros_mes' => (float) $item->total_cobrado,
                    'ranking'    => $index + 1,
                ];
            });

        return [
            'resumen_equipo'   => [
                'total_cobradores'    => $user->assignedCobradores()->count(),
                'total_clientes'      => User::role('client')
                    ->whereHas('assignedCobrador', fn($q) => $q->whereIn('id', $cobradorIds))
                    ->count(),
                'creditos_activos'    => Credit::whereIn('created_by', $cobradorIds)
                    ->where('status', 'active')
                    ->count(),
                'saldo_total_cartera' => (float) Credit::whereIn('created_by', $cobradorIds)
                    ->where('status', 'active')
                    ->sum('balance'),
            ],
            'rendimiento_hoy'  => [
                'cobradores_activos' => Payment::whereIn('cobrador_id', $cobradorIds)
                    ->whereDate('payment_date', $today)
                    ->distinct('cobrador_id')
                    ->count('cobrador_id'),
                'total_cobrado_hoy'  => (float) Payment::whereIn('cobrador_id', $cobradorIds)
                    ->whereDate('payment_date', $today)
                    ->where('status', 'paid')
                    ->sum('amount'),
                'numero_cobros'      => Payment::whereIn('cobrador_id', $cobradorIds)
                    ->whereDate('payment_date', $today)
                    ->where('status', 'paid')
                    ->count(),
            ],
            'top_cobradores'   => $topCobradores,
            'alertas_criticas' => [
                'cobradores_con_mora_alta' => $this->getCobradoresConMoraAlta($cobradorIds),
                'total_pagos_atrasados'    => Payment::whereIn('cobrador_id', $cobradorIds)
                    ->where('status', 'overdue')
                    ->count(),
                'clientes_categoria_c'     => User::role('client')
                    ->whereHas('assignedCobrador', fn($q) => $q->whereIn('id', $cobradorIds))
                    ->where('client_category', 'C')
                    ->count(),
            ],
        ];
    }

    /**
     * Estadísticas para Admin
     */
    private function getAdminStatistics(User $user)
    {
        $today      = today();
        $monthStart = now()->startOfMonth();

        $totalCreditsExpected = Payment::whereBetween('payment_date', [$monthStart, now()])->sum('amount');
        $totalCreditsPaid     = Payment::whereBetween('payment_date', [$monthStart, now()])
            ->where('status', 'paid')
            ->sum('amount');

        $tasaCobro = $totalCreditsExpected > 0
            ? round(($totalCreditsPaid / $totalCreditsExpected) * 100, 2)
            : 0;

        return [
            'sistema'       => [
                'total_usuarios'   => User::count(),
                'total_cobradores' => User::role('cobrador')->count(),
                'total_managers'   => User::role('manager')->count(),
                'total_clientes'   => User::role('client')->count(),
            ],
            'financiero'    => [
                'cartera_total' => (float) Credit::where('status', 'active')->sum('balance'),
                'cobrado_mes'   => (float) $totalCreditsPaid,
                'tasa_cobro'    => $tasaCobro,
                'mora_total'    => (float) Payment::where('status', 'overdue')
                    ->sum('amount'),
            ],
            'actividad_hoy' => [
                'nuevos_creditos'   => Credit::whereDate('created_at', $today)->count(),
                'pagos_registrados' => Payment::whereDate('payment_date', $today)->count(),
                'monto_cobrado'     => (float) Payment::whereDate('payment_date', $today)
                    ->where('status', 'paid')
                    ->sum('amount'),
            ],
        ];
    }

    /**
     * Estadísticas para Cliente
     */
    private function getClientStatistics(User $user)
    {
        return [
            'mis_creditos' => [
                'activos'      => $user->credits()->where('status', 'active')->count(),
                'saldo_total'  => (float) $user->credits()->where('status', 'active')->sum('balance'),
                'proximo_pago' => ($credit = $user->credits()
                        ->where('status', 'active')
                        ->with(['payments' => fn($q) => $q->where('status', 'pending')->orderBy('payment_date', 'asc')])
                        ->first()) ? $credit->payments->first() : null,
            ],
            'historial'    => [
                'total_pagos_realizados' => $user->payments()->where('status', 'paid')->count(),
                'monto_total_pagado'     => (float) $user->payments()->where('status', 'paid')->sum('amount'),
                'pagos_atrasados'        => $user->payments()->where('status', 'overdue')->count(),
            ],
        ];
    }

    /**
     * Obtener cantidad de cobradores con mora alta (más del 30% de pagos atrasados)
     */
    private function getCobradoresConMoraAlta($cobradorIds)
    {
        $count = 0;
        foreach ($cobradorIds as $cobradorId) {
            $totalPayments   = Payment::where('cobrador_id', $cobradorId)->count();
            $overduePayments = Payment::where('cobrador_id', $cobradorId)
                ->where('status', 'overdue')
                ->count();

            if ($totalPayments > 0) {
                $moraPercentage = ($overduePayments / $totalPayments) * 100;
                if ($moraPercentage > 30) {
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * Logout user.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->sendResponse([], 'Sesión cerrada exitosamente');
    }

    /**
     * Get authenticated user.
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $user->load('roles', 'permissions');

        // Obtener estadísticas optimizadas según el rol
        $statistics = $this->getLoginStatistics($user);

        return $this->sendResponse([
            'user'       => $user,
            'statistics' => $statistics,
        ]);
    }

    /**
     * Check if email or phone exists.
     */
    public function checkExists(Request $request)
    {
        $request->validate([
            'email_or_phone' => 'required|string',
        ]);

        $emailOrPhone = $request->email_or_phone;
        $isEmail      = filter_var($emailOrPhone, FILTER_VALIDATE_EMAIL);

        if ($isEmail) {
            $exists = User::where('email', $emailOrPhone)->exists();
        } else {
            $exists = User::where('phone', $emailOrPhone)->exists();
        }

        return $this->sendResponse([
            'exists' => $exists,
            'type'   => $isEmail ? 'email' : 'phone',
        ]);
    }

    /**
     * Get tenant status (used by Flutter to check if tenant is active).
     */
    public function tenantStatus(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return $this->sendError('No autenticado', 'Usuario no autenticado.', 401);
        }

        $tenant = $user->tenant;

        if (!$tenant) {
            // Usuario sin tenant (por ejemplo, super-admin)
            return $this->sendResponse([
                'has_tenant' => false,
                'is_active' => true,
                'status' => 'no_tenant',
            ], 'Usuario no pertenece a ninguna empresa.');
        }

        $isActive = $tenant->status !== 'suspended';

        return $this->sendResponse([
            'has_tenant' => true,
            'is_active' => $isActive,
            'status' => $tenant->status,
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
        ], $isActive
            ? 'Empresa activa.'
            : 'Empresa suspendida. Contacta a soporte para más información.');
    }
}
