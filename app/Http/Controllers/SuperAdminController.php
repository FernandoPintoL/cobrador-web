<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantSubscription;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminController extends Controller
{
    /**
     * Display the billing dashboard.
     */
    public function dashboard(): Response
    {
        // Estadísticas de tenants
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('status', 'active')->count();
        $trialTenants = Tenant::where('status', 'trial')->count();
        $suspendedTenants = Tenant::where('status', 'suspended')->count();

        // Estadísticas de facturación
        $totalInvoices = TenantSubscription::count();
        $paidInvoices = TenantSubscription::where('status', 'paid')->count();
        $pendingInvoices = TenantSubscription::where('status', 'pending')->count();
        $overdueInvoices = TenantSubscription::where('status', 'overdue')->count();

        // Ingresos
        $totalRevenue = TenantSubscription::where('status', 'paid')->sum('amount');
        $pendingRevenue = TenantSubscription::where('status', 'pending')->sum('amount');
        $overdueRevenue = TenantSubscription::where('status', 'overdue')->sum('amount');

        // Ingresos del mes actual
        $currentMonthRevenue = TenantSubscription::where('status', 'paid')
            ->whereYear('paid_at', Carbon::now()->year)
            ->whereMonth('paid_at', Carbon::now()->month)
            ->sum('amount');

        // Ingresos del mes anterior
        $previousMonthRevenue = TenantSubscription::where('status', 'paid')
            ->whereYear('paid_at', Carbon::now()->subMonth()->year)
            ->whereMonth('paid_at', Carbon::now()->subMonth()->month)
            ->sum('amount');

        // Calcular crecimiento
        $revenueGrowthPercentage = $previousMonthRevenue > 0
            ? (($currentMonthRevenue - $previousMonthRevenue) / $previousMonthRevenue) * 100
            : 0;

        // Trials que expiran pronto (próximos 7 días)
        $trialsExpiringSoon = Tenant::where('status', 'trial')
            ->where('trial_ends_at', '<=', Carbon::now()->addDays(7))
            ->where('trial_ends_at', '>=', Carbon::now())
            ->count();

        $stats = [
            'total_tenants' => $totalTenants,
            'active_tenants' => $activeTenants,
            'trial_tenants' => $trialTenants,
            'suspended_tenants' => $suspendedTenants,
            'total_invoices' => $totalInvoices,
            'paid_invoices' => $paidInvoices,
            'pending_invoices' => $pendingInvoices,
            'overdue_invoices' => $overdueInvoices,
            'total_revenue' => (float) $totalRevenue,
            'pending_revenue' => (float) $pendingRevenue,
            'overdue_revenue' => (float) $overdueRevenue,
            'current_month_revenue' => (float) $currentMonthRevenue,
            'previous_month_revenue' => (float) $previousMonthRevenue,
            'revenue_growth_percentage' => round($revenueGrowthPercentage, 2),
            'trials_expiring_soon' => $trialsExpiringSoon,
        ];

        return Inertia::render('super-admin/dashboard', [
            'stats' => $stats,
        ]);
    }

    /**
     * Display a listing of tenants.
     */
    public function tenantsIndex(): Response
    {
        $query = Tenant::query()->with(['users', 'subscriptions']);

        // Filtro de búsqueda
        if (request('search')) {
            $search = request('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Filtro por estado
        if (request('status') && request('status') !== 'all') {
            $query->where('status', request('status'));
        }

        // Paginación
        $tenants = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString()
            ->through(function ($tenant) {
                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'status' => $tenant->status,
                    'status_label' => ucfirst($tenant->status),
                    'monthly_price' => $tenant->monthly_price,
                    'monthly_price_formatted' => 'Bs. ' . number_format($tenant->monthly_price, 2),
                    'trial_ends_at' => $tenant->trial_ends_at?->format('Y-m-d'),
                    'trial_ends_at_formatted' => $tenant->trial_ends_at?->format('d/m/Y'),
                    'users_count' => $tenant->users->count(),
                    'subscriptions_count' => $tenant->subscriptions->count(),
                    'created_at_formatted' => $tenant->created_at->format('d/m/Y H:i'),
                ];
            });

        return Inertia::render('super-admin/tenants/index', [
            'tenants' => $tenants,
            'filters' => [
                'search' => request('search'),
                'status' => request('status', 'all'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new tenant.
     */
    public function tenantsCreate(): Response
    {
        // Default values for new tenant
        $defaultTrialEnd = Carbon::now()->addMonth()->format('Y-m-d');

        return Inertia::render('super-admin/tenants/form', [
            'tenant' => null,
            'isEdit' => false,
            'defaultTrialEnd' => $defaultTrialEnd,
        ]);
    }

    /**
     * Display the specified tenant.
     */
    public function tenantsShow(int $id): Response
    {
        $tenant = Tenant::with(['users', 'subscriptions'])->findOrFail($id);

        // Calcular estadísticas
        $stats = [
            'users_count' => $tenant->users->count(),
            'credits_count' => 0, // TODO: Implementar cuando exista la relación
            'active_credits_count' => 0,
            'total_credit_amount' => 0,
            'subscriptions_count' => $tenant->subscriptions->count(),
            'paid_subscriptions_count' => $tenant->subscriptions->where('status', 'paid')->count(),
            'pending_subscriptions_count' => $tenant->subscriptions->where('status', 'pending')->count(),
            'overdue_subscriptions_count' => $tenant->subscriptions->where('status', 'overdue')->count(),
            'total_revenue' => $tenant->subscriptions->where('status', 'paid')->sum('amount'),
            'pending_revenue' => $tenant->subscriptions->where('status', 'pending')->sum('amount'),
            'overdue_revenue' => $tenant->subscriptions->where('status', 'overdue')->sum('amount'),
        ];

        // Datos del tenant
        $tenantData = [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'status' => $tenant->status,
            'status_label' => ucfirst($tenant->status),
            'monthly_price' => $tenant->monthly_price,
            'monthly_price_formatted' => 'Bs. ' . number_format($tenant->monthly_price, 2),
            'trial_ends_at' => $tenant->trial_ends_at?->format('Y-m-d'),
            'trial_ends_at_formatted' => $tenant->trial_ends_at?->format('d/m/Y'),
            'created_at_formatted' => $tenant->created_at->format('d/m/Y H:i'),
            'updated_at_formatted' => $tenant->updated_at->format('d/m/Y H:i'),
        ];

        // Últimas subscripciones
        $recentSubscriptions = $tenant->subscriptions()
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($sub) {
                return [
                    'id' => $sub->id,
                    'amount_formatted' => 'Bs. ' . number_format($sub->amount, 2),
                    'status' => $sub->status,
                    'status_label' => ucfirst($sub->status),
                    'period_start_formatted' => $sub->period_start?->format('d/m/Y'),
                    'period_end_formatted' => $sub->period_end?->format('d/m/Y'),
                    'created_at_formatted' => $sub->created_at->format('d/m/Y H:i'),
                ];
            });

        return Inertia::render('super-admin/tenants/show', [
            'tenant' => $tenantData,
            'stats' => $stats,
            'recentSubscriptions' => $recentSubscriptions,
        ]);
    }

    /**
     * Show the form for editing the specified tenant.
     */
    public function tenantsEdit(int $id): Response
    {
        $tenant = Tenant::findOrFail($id);

        $tenantData = [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'status' => $tenant->status,
            'monthly_price' => $tenant->monthly_price,
            'trial_ends_at' => $tenant->trial_ends_at?->format('Y-m-d'),
        ];

        return Inertia::render('super-admin/tenants/form', [
            'tenant' => $tenantData,
            'isEdit' => true,
        ]);
    }

    /**
     * Display the settings page for a tenant.
     */
    public function tenantsSettings(int $id): Response
    {
        return Inertia::render('super-admin/tenants/settings', [
            'tenantId' => $id,
        ]);
    }

    /**
     * Display a listing of subscriptions.
     */
    public function subscriptionsIndex(): Response
    {
        $query = TenantSubscription::query()->with('tenant');

        // Filtro de búsqueda por nombre de tenant
        if (request('search')) {
            $search = request('search');
            $query->whereHas('tenant', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        // Filtro por estado
        if (request('status') && request('status') !== 'all') {
            $query->where('status', request('status'));
        }

        // Paginación
        $subscriptions = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString()
            ->through(function ($subscription) {
                $daysOverdue = null;
                if ($subscription->status === 'overdue' && $subscription->due_date) {
                    $daysOverdue = Carbon::now()->diffInDays($subscription->due_date, false);
                    $daysOverdue = abs($daysOverdue);
                }

                return [
                    'id' => $subscription->id,
                    'tenant_id' => $subscription->tenant_id,
                    'tenant_name' => $subscription->tenant?->name ?? 'N/A',
                    'amount' => $subscription->amount,
                    'amount_formatted' => 'Bs. ' . number_format($subscription->amount, 2),
                    'period_start' => $subscription->period_start?->format('Y-m-d'),
                    'period_start_formatted' => $subscription->period_start?->format('d/m/Y'),
                    'period_end' => $subscription->period_end?->format('Y-m-d'),
                    'period_end_formatted' => $subscription->period_end?->format('d/m/Y'),
                    'status' => $subscription->status,
                    'status_label' => ucfirst($subscription->status),
                    'days_overdue' => $daysOverdue,
                ];
            });

        return Inertia::render('super-admin/subscriptions/index', [
            'subscriptions' => $subscriptions,
            'filters' => [
                'search' => request('search'),
                'status' => request('status', 'all'),
            ],
        ]);
    }
}
