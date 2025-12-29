<?php

namespace App\Http\Controllers\Api;

use App\Models\Tenant;
use App\Models\TenantSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingDashboardController extends BaseController
{
    /**
     * Get overview metrics for billing dashboard.
     */
    public function overview(Request $request)
    {
        $stats = [
            // Tenant statistics
            'total_tenants' => Tenant::withoutGlobalScopes()->count(),
            'active_tenants' => Tenant::withoutGlobalScopes()->where('status', 'active')->count(),
            'trial_tenants' => Tenant::withoutGlobalScopes()->where('status', 'trial')->count(),
            'suspended_tenants' => Tenant::withoutGlobalScopes()->where('status', 'suspended')->count(),

            // Subscription statistics
            'total_invoices' => TenantSubscription::count(),
            'paid_invoices' => TenantSubscription::where('status', 'paid')->count(),
            'pending_invoices' => TenantSubscription::where('status', 'pending')->count(),
            'overdue_invoices' => TenantSubscription::where('status', 'overdue')->count(),

            // Revenue statistics
            'total_revenue' => TenantSubscription::where('status', 'paid')->sum('amount'),
            'pending_revenue' => TenantSubscription::where('status', 'pending')->sum('amount'),
            'overdue_revenue' => TenantSubscription::where('status', 'overdue')->sum('amount'),

            // Current month statistics
            'current_month_revenue' => TenantSubscription::where('status', 'paid')
                ->whereYear('period_start', now()->year)
                ->whereMonth('period_start', now()->month)
                ->sum('amount'),
            'current_month_invoices' => TenantSubscription::whereYear('period_start', now()->year)
                ->whereMonth('period_start', now()->month)
                ->count(),

            // Previous month statistics for comparison
            'previous_month_revenue' => TenantSubscription::where('status', 'paid')
                ->whereYear('period_start', now()->subMonth()->year)
                ->whereMonth('period_start', now()->subMonth()->month)
                ->sum('amount'),

            // Average statistics
            'average_monthly_revenue_per_tenant' => Tenant::withoutGlobalScopes()
                ->where('status', 'active')
                ->avg('monthly_price'),
            'average_invoice_amount' => TenantSubscription::avg('amount'),

            // Trial expiring soon (next 7 days)
            'trials_expiring_soon' => Tenant::withoutGlobalScopes()
                ->where('status', 'trial')
                ->whereBetween('trial_ends_at', [now(), now()->addDays(7)])
                ->count(),
        ];

        // Calculate growth percentage
        if ($stats['previous_month_revenue'] > 0) {
            $stats['revenue_growth_percentage'] = round(
                (($stats['current_month_revenue'] - $stats['previous_month_revenue'])
                / $stats['previous_month_revenue']) * 100,
                2
            );
        } else {
            $stats['revenue_growth_percentage'] = 0;
        }

        return $this->sendResponse($stats, 'Métricas del dashboard recuperadas exitosamente.');
    }

    /**
     * Get monthly revenue breakdown for the last 12 months.
     */
    public function monthlyRevenue(Request $request)
    {
        $months = $request->get('months', 12);
        $monthlyData = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i);

            $revenue = TenantSubscription::where('status', 'paid')
                ->whereYear('period_start', $month->year)
                ->whereMonth('period_start', $month->month)
                ->sum('amount');

            $invoices = TenantSubscription::whereYear('period_start', $month->year)
                ->whereMonth('period_start', $month->month)
                ->count();

            $paidInvoices = TenantSubscription::where('status', 'paid')
                ->whereYear('period_start', $month->year)
                ->whereMonth('period_start', $month->month)
                ->count();

            $monthlyData[] = [
                'month' => $month->format('Y-m'),
                'month_name' => $month->translatedFormat('F Y'),
                'revenue' => $revenue,
                'invoices' => $invoices,
                'paid_invoices' => $paidInvoices,
                'pending_invoices' => $invoices - $paidInvoices,
            ];
        }

        return $this->sendResponse($monthlyData, 'Ingresos mensuales recuperados exitosamente.');
    }

    /**
     * Get tenant growth over time.
     */
    public function tenantGrowth(Request $request)
    {
        $months = $request->get('months', 12);
        $growthData = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i);

            $newTenants = Tenant::withoutGlobalScopes()
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();

            $totalTenants = Tenant::withoutGlobalScopes()
                ->where('created_at', '<=', $month->endOfMonth())
                ->count();

            $activeTenants = Tenant::withoutGlobalScopes()
                ->where('status', 'active')
                ->where('created_at', '<=', $month->endOfMonth())
                ->count();

            $growthData[] = [
                'month' => $month->format('Y-m'),
                'month_name' => $month->translatedFormat('F Y'),
                'new_tenants' => $newTenants,
                'total_tenants' => $totalTenants,
                'active_tenants' => $activeTenants,
            ];
        }

        return $this->sendResponse($growthData, 'Crecimiento de tenants recuperado exitosamente.');
    }

    /**
     * Get tenants with overdue invoices.
     */
    public function overdueReport(Request $request)
    {
        $overdueInvoices = TenantSubscription::with('tenant')
            ->where('status', 'overdue')
            ->orderBy('period_end', 'asc')
            ->get()
            ->map(function ($invoice) {
                return [
                    'invoice_id' => $invoice->id,
                    'tenant_id' => $invoice->tenant_id,
                    'tenant_name' => $invoice->tenant->name,
                    'tenant_status' => $invoice->tenant->status,
                    'amount' => $invoice->amount,
                    'period_start' => $invoice->period_start,
                    'period_end' => $invoice->period_end,
                    'days_overdue' => now()->diffInDays($invoice->period_end),
                ];
            });

        $summary = [
            'total_overdue_invoices' => $overdueInvoices->count(),
            'total_overdue_amount' => $overdueInvoices->sum('amount'),
            'tenants_affected' => $overdueInvoices->pluck('tenant_id')->unique()->count(),
        ];

        return $this->sendResponse([
            'summary' => $summary,
            'invoices' => $overdueInvoices,
        ], 'Reporte de facturas vencidas recuperado exitosamente.');
    }

    /**
     * Get trials expiring soon.
     */
    public function trialsExpiring(Request $request)
    {
        $days = $request->get('days', 7);

        $expiringTrials = Tenant::withoutGlobalScopes()
            ->where('status', 'trial')
            ->whereBetween('trial_ends_at', [now(), now()->addDays($days)])
            ->withCount('users')
            ->orderBy('trial_ends_at', 'asc')
            ->get()
            ->map(function ($tenant) {
                return [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'trial_ends_at' => $tenant->trial_ends_at,
                    'days_remaining' => now()->diffInDays($tenant->trial_ends_at),
                    'users_count' => $tenant->users_count,
                    'monthly_price' => $tenant->monthly_price,
                ];
            });

        return $this->sendResponse([
            'count' => $expiringTrials->count(),
            'potential_monthly_revenue' => $expiringTrials->sum('monthly_price'),
            'trials' => $expiringTrials,
        ], 'Trials próximos a expirar recuperados exitosamente.');
    }

    /**
     * Get revenue by tenant (top earners).
     */
    public function topTenants(Request $request)
    {
        $limit = $request->get('limit', 10);
        $period = $request->get('period', 'all'); // all, year, month

        $query = DB::table('tenant_subscriptions')
            ->join('tenants', 'tenants.id', '=', 'tenant_subscriptions.tenant_id')
            ->where('tenant_subscriptions.status', 'paid')
            ->select(
                'tenants.id',
                'tenants.name',
                'tenants.status',
                'tenants.monthly_price',
                DB::raw('COUNT(tenant_subscriptions.id) as total_invoices'),
                DB::raw('SUM(tenant_subscriptions.amount) as total_revenue')
            )
            ->groupBy('tenants.id', 'tenants.name', 'tenants.status', 'tenants.monthly_price');

        // Apply period filter
        if ($period === 'year') {
            $query->whereYear('tenant_subscriptions.period_start', now()->year);
        } elseif ($period === 'month') {
            $query->whereYear('tenant_subscriptions.period_start', now()->year)
                  ->whereMonth('tenant_subscriptions.period_start', now()->month);
        }

        $topTenants = $query->orderBy('total_revenue', 'desc')
            ->limit($limit)
            ->get();

        return $this->sendResponse($topTenants, 'Top tenants recuperados exitosamente.');
    }

    /**
     * Get payment compliance rate.
     */
    public function paymentCompliance(Request $request)
    {
        $months = $request->get('months', 12);
        $complianceData = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i);

            $totalInvoices = TenantSubscription::whereYear('period_start', $month->year)
                ->whereMonth('period_start', $month->month)
                ->count();

            $paidInvoices = TenantSubscription::where('status', 'paid')
                ->whereYear('period_start', $month->year)
                ->whereMonth('period_start', $month->month)
                ->count();

            $complianceRate = $totalInvoices > 0
                ? round(($paidInvoices / $totalInvoices) * 100, 2)
                : 0;

            $complianceData[] = [
                'month' => $month->format('Y-m'),
                'month_name' => $month->translatedFormat('F Y'),
                'total_invoices' => $totalInvoices,
                'paid_invoices' => $paidInvoices,
                'compliance_rate' => $complianceRate,
            ];
        }

        return $this->sendResponse($complianceData, 'Tasa de cumplimiento de pagos recuperada exitosamente.');
    }

    /**
     * Get churn rate (tenants that went from active to suspended).
     */
    public function churnRate(Request $request)
    {
        $suspendedTenants = Tenant::withoutGlobalScopes()
            ->where('status', 'suspended')
            ->count();

        $totalTenants = Tenant::withoutGlobalScopes()->count();

        $churnRate = $totalTenants > 0
            ? round(($suspendedTenants / $totalTenants) * 100, 2)
            : 0;

        return $this->sendResponse([
            'suspended_tenants' => $suspendedTenants,
            'total_tenants' => $totalTenants,
            'churn_rate' => $churnRate,
        ], 'Tasa de abandono recuperada exitosamente.');
    }

    /**
     * Export billing data (CSV format preparation).
     */
    public function exportData(Request $request)
    {
        $type = $request->get('type', 'invoices'); // invoices, tenants, revenue

        if ($type === 'invoices') {
            $data = TenantSubscription::with('tenant')
                ->orderBy('period_start', 'desc')
                ->get()
                ->map(function ($invoice) {
                    return [
                        'ID Factura' => $invoice->id,
                        'Tenant' => $invoice->tenant->name,
                        'Monto' => $invoice->amount,
                        'Estado' => $invoice->status,
                        'Período Inicio' => $invoice->period_start,
                        'Período Fin' => $invoice->period_end,
                        'Creado' => $invoice->created_at,
                    ];
                });
        } elseif ($type === 'tenants') {
            $data = Tenant::withoutGlobalScopes()
                ->withCount(['users', 'subscriptions'])
                ->get()
                ->map(function ($tenant) {
                    return [
                        'ID' => $tenant->id,
                        'Nombre' => $tenant->name,
                        'Estado' => $tenant->status,
                        'Precio Mensual' => $tenant->monthly_price,
                        'Usuarios' => $tenant->users_count,
                        'Facturas' => $tenant->subscriptions_count,
                        'Trial Expira' => $tenant->trial_ends_at,
                        'Creado' => $tenant->created_at,
                    ];
                });
        } else {
            return $this->sendError('Tipo de exportación inválido.', [], 400);
        }

        return $this->sendResponse([
            'data' => $data,
            'count' => $data->count(),
        ], 'Datos de exportación preparados exitosamente.');
    }
}
