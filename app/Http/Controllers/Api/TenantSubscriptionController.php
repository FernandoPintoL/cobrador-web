<?php

namespace App\Http\Controllers\Api;

use App\Models\Tenant;
use App\Models\TenantSubscription;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantSubscriptionController extends BaseController
{
    /**
     * Display a listing of subscriptions.
     */
    public function index(Request $request)
    {
        $query = TenantSubscription::with(['tenant']);

        // Filtro por tenant específico
        if ($request->has('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        // Filtro por estado
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filtro: solo facturas pendientes
        if ($request->boolean('pending_only')) {
            $query->where('status', 'pending');
        }

        // Filtro: solo facturas pagadas
        if ($request->boolean('paid_only')) {
            $query->where('status', 'paid');
        }

        // Filtro: solo facturas vencidas
        if ($request->boolean('overdue_only')) {
            $query->where('status', 'overdue');
        }

        // Filtro por rango de fechas (período)
        if ($request->has('period_start_from')) {
            $query->where('period_start', '>=', $request->period_start_from);
        }

        if ($request->has('period_start_to')) {
            $query->where('period_start', '<=', $request->period_start_to);
        }

        if ($request->has('period_end_from')) {
            $query->where('period_end', '>=', $request->period_end_from);
        }

        if ($request->has('period_end_to')) {
            $query->where('period_end', '<=', $request->period_end_to);
        }

        // Filtro: facturas del mes actual
        if ($request->boolean('current_month')) {
            $query->whereYear('period_start', now()->year)
                  ->whereMonth('period_start', now()->month);
        }

        // Búsqueda por nombre de tenant
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('tenant', function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('slug', 'ilike', "%{$search}%");
            });
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'period_start');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $subscriptions = $query->paginate($perPage);

        return $this->sendResponse($subscriptions, 'Facturas recuperadas exitosamente.');
    }

    /**
     * Display subscriptions for a specific tenant.
     */
    public function indexByTenant($tenantId, Request $request)
    {
        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);

        if (!$tenant) {
            return $this->sendError('Tenant no encontrado.', [], 404);
        }

        $query = $tenant->subscriptions();

        // Filtro por estado
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'period_start');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $subscriptions = $query->paginate($perPage);

        return $this->sendResponse($subscriptions, "Facturas del tenant '{$tenant->name}' recuperadas exitosamente.");
    }

    /**
     * Store a newly created subscription (manual invoice creation).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'amount' => 'required|numeric|min:0',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after:period_start',
            'status' => ['nullable', Rule::in(['pending', 'paid', 'overdue', 'cancelled'])],
        ]);

        $validated['status'] = $validated['status'] ?? 'pending';

        $subscription = TenantSubscription::create($validated);
        $subscription->load('tenant');

        return $this->sendResponse(
            $subscription,
            'Factura creada exitosamente.',
            201
        );
    }

    /**
     * Display the specified subscription.
     */
    public function show($id)
    {
        $subscription = TenantSubscription::with(['tenant'])->find($id);

        if (!$subscription) {
            return $this->sendError('Factura no encontrada.', [], 404);
        }

        // Agregar información adicional
        $subscription->is_overdue = $subscription->status === 'pending' &&
                                    $subscription->period_end < now();
        $subscription->days_overdue = $subscription->is_overdue ?
                                      now()->diffInDays($subscription->period_end) : 0;

        return $this->sendResponse($subscription, 'Factura recuperada exitosamente.');
    }

    /**
     * Mark subscription as paid.
     */
    public function markAsPaid($id, Request $request)
    {
        $subscription = TenantSubscription::find($id);

        if (!$subscription) {
            return $this->sendError('Factura no encontrada.', [], 404);
        }

        if ($subscription->status === 'paid') {
            return $this->sendError('La factura ya está marcada como pagada.', [], 400);
        }

        if ($subscription->status === 'cancelled') {
            return $this->sendError('No se puede marcar como pagada una factura cancelada.', [], 400);
        }

        $validated = $request->validate([
            'payment_date' => 'nullable|date',
            'payment_method' => 'nullable|string|max:255',
            'payment_reference' => 'nullable|string|max:255',
        ]);

        // Actualizar estado
        $subscription->update([
            'status' => 'paid',
        ]);

        // Si el tenant estaba suspendido por falta de pago, reactivarlo
        $tenant = $subscription->tenant;
        if ($tenant->status === 'suspended') {
            // Verificar si todas las facturas pendientes han sido pagadas
            $pendingInvoices = $tenant->subscriptions()
                ->whereIn('status', ['pending', 'overdue'])
                ->count();

            if ($pendingInvoices === 0) {
                $tenant->update(['status' => 'active']);
            }
        }

        $subscription->load('tenant');

        return $this->sendResponse(
            $subscription,
            'Factura marcada como pagada exitosamente.'
        );
    }

    /**
     * Cancel a subscription.
     */
    public function cancel($id)
    {
        $subscription = TenantSubscription::find($id);

        if (!$subscription) {
            return $this->sendError('Factura no encontrada.', [], 404);
        }

        if ($subscription->status === 'paid') {
            return $this->sendError('No se puede cancelar una factura pagada.', [], 400);
        }

        if ($subscription->status === 'cancelled') {
            return $this->sendError('La factura ya está cancelada.', [], 400);
        }

        $subscription->update(['status' => 'cancelled']);

        return $this->sendResponse(
            $subscription,
            'Factura cancelada exitosamente.'
        );
    }

    /**
     * Get subscription statistics.
     */
    public function statistics(Request $request)
    {
        $query = TenantSubscription::query();

        // Filtro por tenant específico
        if ($request->has('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        // Filtro por rango de fechas
        if ($request->has('from_date')) {
            $query->where('period_start', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('period_start', '<=', $request->to_date);
        }

        $stats = [
            'total_invoices' => (clone $query)->count(),
            'paid_invoices' => (clone $query)->where('status', 'paid')->count(),
            'pending_invoices' => (clone $query)->where('status', 'pending')->count(),
            'overdue_invoices' => (clone $query)->where('status', 'overdue')->count(),
            'cancelled_invoices' => (clone $query)->where('status', 'cancelled')->count(),
            'total_revenue' => (clone $query)->where('status', 'paid')->sum('amount'),
            'pending_revenue' => (clone $query)->where('status', 'pending')->sum('amount'),
            'overdue_revenue' => (clone $query)->where('status', 'overdue')->sum('amount'),
            'average_invoice_amount' => (clone $query)->avg('amount'),
        ];

        // Estadísticas por mes (últimos 12 meses)
        $monthlyStats = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthQuery = (clone $query)
                ->whereYear('period_start', $month->year)
                ->whereMonth('period_start', $month->month);

            $monthlyStats[] = [
                'month' => $month->format('Y-m'),
                'month_name' => $month->translatedFormat('F Y'),
                'total_invoices' => (clone $monthQuery)->count(),
                'paid_invoices' => (clone $monthQuery)->where('status', 'paid')->count(),
                'total_revenue' => (clone $monthQuery)->where('status', 'paid')->sum('amount'),
                'pending_revenue' => (clone $monthQuery)->where('status', 'pending')->sum('amount'),
            ];
        }

        $stats['monthly_breakdown'] = $monthlyStats;

        return $this->sendResponse($stats, 'Estadísticas de facturación recuperadas exitosamente.');
    }

    /**
     * Update the specified subscription.
     */
    public function update(Request $request, $id)
    {
        $subscription = TenantSubscription::find($id);

        if (!$subscription) {
            return $this->sendError('Factura no encontrada.', [], 404);
        }

        $validated = $request->validate([
            'amount' => 'sometimes|required|numeric|min:0',
            'period_start' => 'sometimes|required|date',
            'period_end' => 'sometimes|required|date|after:period_start',
            'status' => ['sometimes', 'required', Rule::in(['pending', 'paid', 'overdue', 'cancelled'])],
        ]);

        $subscription->update($validated);
        $subscription->load('tenant');

        return $this->sendResponse(
            $subscription,
            'Factura actualizada exitosamente.'
        );
    }

    /**
     * Remove the specified subscription.
     */
    public function destroy($id)
    {
        $subscription = TenantSubscription::find($id);

        if (!$subscription) {
            return $this->sendError('Factura no encontrada.', [], 404);
        }

        if ($subscription->status === 'paid') {
            return $this->sendError('No se puede eliminar una factura pagada.', [], 400);
        }

        $subscription->delete();

        return $this->sendResponse(
            ['id' => $id],
            'Factura eliminada exitosamente.'
        );
    }
}
