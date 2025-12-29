<?php

namespace App\Http\Controllers\Api;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TenantController extends BaseController
{
    /**
     * Display a listing of tenants.
     */
    public function index(Request $request)
    {
        $query = Tenant::withoutGlobalScopes()
            ->withCount(['users', 'subscriptions'])
            ->with(['latestSubscription']);

        // Filtro por estado
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Búsqueda por nombre, slug o ID
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('slug', 'ilike', "%{$search}%")
                  ->orWhere('id', 'ilike', "%{$search}%");
            });
        }

        // Filtro por nombre
        if ($request->has('name')) {
            $query->where('name', 'ilike', "%{$request->name}%");
        }

        // Filtro: solo tenants en período de prueba
        if ($request->boolean('trial_only')) {
            $query->where('status', 'trial')
                  ->whereNotNull('trial_ends_at')
                  ->where('trial_ends_at', '>=', now());
        }

        // Filtro: solo tenants suspendidos
        if ($request->boolean('suspended_only')) {
            $query->where('status', 'suspended');
        }

        // Filtro: solo tenants activos
        if ($request->boolean('active_only')) {
            $query->where('status', 'active');
        }

        // Incluir tenants eliminados (soft deleted)
        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $tenants = $query->paginate($perPage);

        return $this->sendResponse($tenants, 'Tenants recuperados exitosamente.');
    }

    /**
     * Store a newly created tenant.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:tenants,slug',
            'logo' => 'nullable|string|max:500',
            'status' => ['nullable', Rule::in(['active', 'trial', 'suspended'])],
            'monthly_price' => 'nullable|numeric|min:0',
            'trial_ends_at' => 'nullable|date|after:today',
            'settings' => 'nullable|array',
        ]);

        // Generar slug automáticamente si no se proporciona
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);

            // Asegurar unicidad del slug
            $counter = 1;
            $originalSlug = $validated['slug'];
            while (Tenant::where('slug', $validated['slug'])->exists()) {
                $validated['slug'] = $originalSlug . '-' . $counter;
                $counter++;
            }
        }

        // Establecer valores por defecto
        $validated['status'] = $validated['status'] ?? 'trial';
        $validated['monthly_price'] = $validated['monthly_price'] ?? 0;

        // Si es trial, establecer fecha de fin si no se proporciona
        if ($validated['status'] === 'trial' && empty($validated['trial_ends_at'])) {
            $validated['trial_ends_at'] = now()->addMonth();
        }

        $tenant = Tenant::create($validated);

        // Configurar settings por defecto si se proporcionaron
        if (!empty($validated['settings'])) {
            foreach ($validated['settings'] as $key => $value) {
                $type = $this->inferType($value);
                $tenant->setSetting($key, $value, $type);
            }
        } else {
            // Settings por defecto para nuevos tenants
            $defaultSettings = [
                'allow_custom_interest_per_credit' => ['value' => true, 'type' => 'boolean'],
                'max_credits_per_client' => ['value' => 10, 'type' => 'integer'],
                'default_interest_rate' => ['value' => 10.0, 'type' => 'decimal'],
                'enable_notifications' => ['value' => true, 'type' => 'boolean'],
            ];

            foreach ($defaultSettings as $key => $config) {
                $tenant->setSetting($key, $config['value'], $config['type']);
            }
        }

        // Recargar con relaciones
        $tenant->load(['settings']);

        return $this->sendResponse(
            $tenant,
            'Tenant creado exitosamente.',
            201
        );
    }

    /**
     * Display the specified tenant.
     */
    public function show($id)
    {
        $tenant = Tenant::withoutGlobalScopes()
            ->withCount(['users', 'subscriptions'])
            ->with([
                'settings',
                'subscriptions' => function ($query) {
                    $query->orderBy('period_start', 'desc')->limit(10);
                },
                'users' => function ($query) {
                    $query->with('roles')->limit(20);
                }
            ])
            ->find($id);

        if (!$tenant) {
            return $this->sendError('Tenant no encontrado.', [], 404);
        }

        // Agregar información adicional
        $tenant->is_on_trial = $tenant->isOnTrial();
        $tenant->trial_has_expired = $tenant->trialHasExpired();
        $tenant->is_suspended = $tenant->isSuspended();
        $tenant->is_active = $tenant->isActive();

        return $this->sendResponse($tenant, 'Tenant recuperado exitosamente.');
    }

    /**
     * Update the specified tenant.
     */
    public function update(Request $request, $id)
    {
        $tenant = Tenant::withoutGlobalScopes()->find($id);

        if (!$tenant) {
            return $this->sendError('Tenant no encontrado.', [], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('tenants', 'slug')->ignore($tenant->id)
            ],
            'logo' => 'nullable|string|max:500',
            'status' => ['sometimes', 'required', Rule::in(['active', 'trial', 'suspended'])],
            'monthly_price' => 'nullable|numeric|min:0',
            'trial_ends_at' => 'nullable|date',
            'settings' => 'nullable|array',
        ]);

        // Actualizar datos básicos
        $tenant->update(collect($validated)->except('settings')->toArray());

        // Actualizar settings si se proporcionaron
        if (isset($validated['settings'])) {
            foreach ($validated['settings'] as $key => $value) {
                $type = $this->inferType($value);
                $tenant->setSetting($key, $value, $type);
            }
        }

        // Recargar con relaciones
        $tenant->load(['settings']);

        return $this->sendResponse(
            $tenant,
            'Tenant actualizado exitosamente.'
        );
    }

    /**
     * Suspend (soft delete) the specified tenant.
     */
    public function destroy($id)
    {
        $tenant = Tenant::withoutGlobalScopes()->find($id);

        if (!$tenant) {
            return $this->sendError('Tenant no encontrado.', [], 404);
        }

        // Cambiar estado a suspended en lugar de eliminar
        $tenant->update(['status' => 'suspended']);

        return $this->sendResponse(
            ['id' => $tenant->id, 'status' => 'suspended'],
            'Tenant suspendido exitosamente.'
        );
    }

    /**
     * Activate a suspended tenant.
     */
    public function activate($id)
    {
        $tenant = Tenant::withoutGlobalScopes()->find($id);

        if (!$tenant) {
            return $this->sendError('Tenant no encontrado.', [], 404);
        }

        if ($tenant->status !== 'suspended') {
            return $this->sendError(
                'Solo se pueden activar tenants suspendidos.',
                ['current_status' => $tenant->status],
                400
            );
        }

        $tenant->update(['status' => 'active']);

        return $this->sendResponse(
            ['id' => $tenant->id, 'status' => 'active'],
            'Tenant activado exitosamente.'
        );
    }

    /**
     * Get tenant statistics.
     */
    public function statistics($id)
    {
        $tenant = Tenant::withoutGlobalScopes()->find($id);

        if (!$tenant) {
            return $this->sendError('Tenant no encontrado.', [], 404);
        }

        $stats = [
            'users_count' => $tenant->users()->count(),
            'active_users_count' => $tenant->users()->whereHas('roles', function ($q) {
                $q->whereIn('name', ['manager', 'cobrador']);
            })->count(),
            'clients_count' => $tenant->users()->whereHas('roles', function ($q) {
                $q->where('name', 'client');
            })->count(),
            'credits_count' => \App\Models\Credit::withoutGlobalScopes()
                ->where('tenant_id', $id)
                ->count(),
            'active_credits_count' => \App\Models\Credit::withoutGlobalScopes()
                ->where('tenant_id', $id)
                ->where('status', 'active')
                ->count(),
            'total_credit_amount' => \App\Models\Credit::withoutGlobalScopes()
                ->where('tenant_id', $id)
                ->sum('amount'),
            'subscriptions_count' => $tenant->subscriptions()->count(),
            'paid_subscriptions_count' => $tenant->subscriptions()
                ->where('status', 'paid')
                ->count(),
            'pending_subscriptions_count' => $tenant->subscriptions()
                ->where('status', 'pending')
                ->count(),
            'total_revenue' => $tenant->subscriptions()
                ->where('status', 'paid')
                ->sum('amount'),
        ];

        return $this->sendResponse($stats, 'Estadísticas del tenant recuperadas exitosamente.');
    }

    /**
     * Infer the type of a value for settings storage.
     */
    private function inferType($value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_int($value)) {
            return 'integer';
        }
        if (is_float($value)) {
            return 'decimal';
        }
        if (is_array($value)) {
            return 'json';
        }
        return 'string';
    }
}
