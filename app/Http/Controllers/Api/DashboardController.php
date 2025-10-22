<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use App\Models\User;
use App\Models\Credit;
use App\Models\Payment;
use App\Models\CashBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends BaseController
{
    /**
     * Obtener estadísticas generales del dashboard
     */
    public function getStats(Request $request)
    {
        try {
            $stats = [
                'total_clients' => User::role('client')->count(),
                'total_cobradores' => User::role('cobrador')->count(),
                'total_credits' => Credit::where('status', 'active')->count(),
                'total_payments' => Payment::count(),
                'overdue_payments' => Payment::where('status', 'overdue')->count(),
                'pending_payments' => Payment::where('status', 'pending')->count(),
                'total_balance' => Credit::where('status', 'active')->sum('balance'),
                'today_collections' => Payment::whereDate('payment_date', today())
                    ->where('status', 'paid')
                    ->sum('amount'),
            ];

            return $this->sendResponse($stats, 'Estadísticas obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->sendError('Error al obtener estadísticas', $e->getMessage(), 500);
        }
    }

    /**
     * Obtener estadísticas por cobrador
     */
    public function getStatsByCobrador(Request $request)
    {
        try {
            $cobradorId = $request->cobrador_id;
            
            if (!$cobradorId) {
                return $this->sendError('ID de cobrador requerido', [], 400);
            }

            $stats = [
                'total_clients' => User::role('client')
                    ->whereHas('clientRoutes.route', function ($q) use ($cobradorId) {
                        $q->where('cobrador_id', $cobradorId);
                    })->count(),
                'total_credits' => Credit::where('created_by', $cobradorId)
                    ->where('status', 'active')->count(),
                'total_payments' => Payment::where('cobrador_id', $cobradorId)->count(),
                'overdue_payments' => Payment::where('cobrador_id', $cobradorId)
                    ->where('status', 'overdue')->count(),
                'pending_payments' => Payment::where('cobrador_id', $cobradorId)
                    ->where('status', 'pending')->count(),
                'total_balance' => Credit::where('created_by', $cobradorId)
                    ->where('status', 'active')->sum('balance'),
                'today_collections' => Payment::where('cobrador_id', $cobradorId)
                    ->whereDate('payment_date', today())
                    ->where('status', 'paid')
                    ->sum('amount'),
                'monthly_collections' => Payment::where('cobrador_id', $cobradorId)
                    ->whereMonth('payment_date', now()->month)
                    ->whereYear('payment_date', now()->year)
                    ->where('status', 'paid')
                    ->sum('amount'),
            ];

            return $this->sendResponse($stats, 'Estadísticas del cobrador obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->sendError('Error al obtener estadísticas del cobrador', $e->getMessage(), 500);
        }
    }

    /**
     * Obtener actividad reciente
     */
    public function getRecentActivity(Request $request)
    {
        try {
            $limit = $request->get('limit', 10);
            
            $recentPayments = Payment::with(['client', 'cobrador'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            $recentCredits = Credit::with(['client'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            $recentCashBalances = CashBalance::with(['cobrador'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            $activity = [
                'payments' => $recentPayments,
                'credits' => $recentCredits,
                'cash_balances' => $recentCashBalances,
            ];

            return $this->sendResponse($activity, 'Actividad reciente obtenida exitosamente');
        } catch (\Exception $e) {
            return $this->sendError('Error al obtener actividad reciente', $e->getMessage(), 500);
        }
    }

    /**
     * Obtener alertas y notificaciones importantes
     */
    public function getAlerts(Request $request)
    {
        try {
            $alerts = [];

            // Pagos atrasados
            $overduePayments = Payment::where('status', 'overdue')
                ->with(['client', 'cobrador'])
                ->get();

            if ($overduePayments->count() > 0) {
                $alerts[] = [
                    'type' => 'overdue_payments',
                    'title' => 'Pagos Atrasados',
                    'message' => "Tienes {$overduePayments->count()} pagos atrasados que requieren atención inmediata.",
                    'count' => $overduePayments->count(),
                    'data' => $overduePayments,
                ];
            }

            // Créditos próximos a vencer
            $upcomingCredits = Credit::where('status', 'active')
                ->where('end_date', '<=', now()->addDays(7))
                ->where('end_date', '>', now())
                ->with(['client'])
                ->get();

            if ($upcomingCredits->count() > 0) {
                $alerts[] = [
                    'type' => 'upcoming_credits',
                    'title' => 'Créditos Próximos a Vencer',
                    'message' => "Tienes {$upcomingCredits->count()} créditos que vencen en los próximos 7 días.",
                    'count' => $upcomingCredits->count(),
                    'data' => $upcomingCredits,
                ];
            }

            // Clientes sin ubicación
            $clientsWithoutLocation = User::role('client')
                ->whereNull('location')
                ->count();

            if ($clientsWithoutLocation > 0) {
                $alerts[] = [
                    'type' => 'clients_without_location',
                    'title' => 'Clientes Sin Ubicación',
                    'message' => "Tienes {$clientsWithoutLocation} clientes sin ubicación GPS registrada.",
                    'count' => $clientsWithoutLocation,
                ];
            }

            return $this->sendResponse($alerts, 'Alertas obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->sendError('Error al obtener alertas', $e->getMessage(), 500);
        }
    }

    /**
     * Obtener métricas de rendimiento
     */
    public function getPerformanceMetrics(Request $request)
    {
        try {
            $period = $request->get('period', 'month'); // week, month, year
            
            $startDate = match($period) {
                'week' => now()->startOfWeek(),
                'month' => now()->startOfMonth(),
                'year' => now()->startOfYear(),
                default => now()->startOfMonth(),
            };

            $endDate = match($period) {
                'week' => now()->endOfWeek(),
                'month' => now()->endOfMonth(),
                'year' => now()->endOfYear(),
                default => now()->endOfMonth(),
            };

            $metrics = [
                'total_collections' => Payment::whereBetween('payment_date', [$startDate, $endDate])
                    ->where('status', 'paid')
                    ->sum('amount'),
                'total_credits_given' => Credit::whereBetween('created_at', [$startDate, $endDate])
                    ->sum('amount'),
                'collection_rate' => $this->calculateCollectionRate($startDate, $endDate),
                'average_credit_amount' => Credit::whereBetween('created_at', [$startDate, $endDate])
                    ->avg('amount'),
                'active_clients' => User::role('client')
                    ->whereHas('credits', function ($q) use ($startDate, $endDate) {
                        $q->where('status', 'active');
                    })->count(),
            ];

            return $this->sendResponse($metrics, 'Métricas de rendimiento obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->sendError('Error al obtener métricas de rendimiento', $e->getMessage(), 500);
        }
    }

    /**
     * Calcular tasa de cobro
     */
    private function calculateCollectionRate($startDate, $endDate)
    {
        $expectedPayments = Payment::whereBetween('payment_date', [$startDate, $endDate])->count();
        $actualPayments = Payment::whereBetween('payment_date', [$startDate, $endDate])
            ->where('status', 'paid')
            ->count();

        if ($expectedPayments === 0) {
            return 0;
        }

        return round(($actualPayments / $expectedPayments) * 100, 2);
    }

    /**
     * Get statistics for a manager and their team
     */
    public function getManagerStats(Request $request)
    {
        try {
            $managerId = $request->manager_id ?? auth()->id();

            // Verificar que el usuario sea manager
            $manager = User::find($managerId);
            if (!$manager || !$manager->hasRole('manager')) {
                return $this->sendError('Usuario no es manager', [], 403);
            }

            // Obtener cobradores del manager
            $cobradores = User::role('cobrador')
                ->where('assigned_manager_id', $managerId)
                ->get();

            $cobradorIds = $cobradores->pluck('id');

            // Estadísticas generales del equipo
            $teamStats = [
                'total_cobradores' => $cobradores->count(),
                'total_clients_team' => User::role('client')
                    ->whereIn('assigned_cobrador_id', $cobradorIds)
                    ->count(),
                'total_credits_team' => Credit::whereIn('created_by', $cobradorIds)
                    ->where('status', 'active')
                    ->count(),
                'total_balance_team' => Credit::whereIn('created_by', $cobradorIds)
                    ->where('status', 'active')
                    ->sum('balance'),
                'today_collections_team' => Payment::whereIn('cobrador_id', $cobradorIds)
                    ->whereDate('payment_date', today())
                    ->sum('amount'),
                'month_collections_team' => Payment::whereIn('cobrador_id', $cobradorIds)
                    ->whereMonth('payment_date', now()->month)
                    ->whereYear('payment_date', now()->year)
                    ->sum('amount'),
            ];

            // Estadísticas por cobrador
            $cobradorStats = $cobradores->map(function ($cobrador) {
                $activeCredits = Credit::where('created_by', $cobrador->id)
                    ->where('status', 'active')
                    ->count();

                $todayCollections = Payment::where('cobrador_id', $cobrador->id)
                    ->whereDate('payment_date', today())
                    ->sum('amount');

                $monthCollections = Payment::where('cobrador_id', $cobrador->id)
                    ->whereMonth('payment_date', now()->month)
                    ->whereYear('payment_date', now()->year)
                    ->sum('amount');

                $clients = User::role('client')
                    ->where('assigned_cobrador_id', $cobrador->id)
                    ->count();

                return [
                    'cobrador_id' => $cobrador->id,
                    'cobrador_name' => $cobrador->name,
                    'clients' => $clients,
                    'active_credits' => $activeCredits,
                    'today_collections' => round($todayCollections, 2),
                    'month_collections' => round($monthCollections, 2),
                ];
            });

            $data = [
                'manager' => [
                    'id' => $manager->id,
                    'name' => $manager->name,
                ],
                'team_summary' => $teamStats,
                'cobradores' => $cobradorStats,
            ];

            return $this->sendResponse($data, 'Estadísticas del manager obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->sendError('Error al obtener estadísticas del manager', $e->getMessage(), 500);
        }
    }

    /**
     * Get financial summary for dashboard
     */
    public function getFinancialSummary(Request $request)
    {
        try {
            $currentUser = auth()->user();

            // Filtrar por roles
            $creditsQuery = Credit::query();
            $paymentsQuery = Payment::query();
            $balancesQuery = CashBalance::query();

            if ($currentUser->hasRole('cobrador')) {
                $creditsQuery->where('created_by', $currentUser->id);
                $paymentsQuery->where('cobrador_id', $currentUser->id);
                $balancesQuery->where('cobrador_id', $currentUser->id);
            } elseif ($currentUser->hasRole('manager')) {
                $cobradorIds = User::role('cobrador')
                    ->where('assigned_manager_id', $currentUser->id)
                    ->pluck('id');

                $creditsQuery->whereIn('created_by', $cobradorIds);
                $paymentsQuery->whereIn('cobrador_id', $cobradorIds);
                $balancesQuery->whereIn('cobrador_id', $cobradorIds);
            }

            // Resumen financiero general
            $summary = [
                'total_lent' => round($creditsQuery->clone()->sum('amount'), 2),
                'total_collected' => round($paymentsQuery->clone()->sum('amount'), 2),
                'active_balance' => round($creditsQuery->clone()->where('status', 'active')->sum('balance'), 2),
                'total_active_credits' => $creditsQuery->clone()->where('status', 'active')->count(),
                'total_completed_credits' => $creditsQuery->clone()->where('status', 'completed')->count(),
            ];

            // Resumen del mes actual
            $monthStart = now()->startOfMonth();
            $monthEnd = now()->endOfMonth();

            $monthSummary = [
                'month_lent' => round($creditsQuery->clone()->whereBetween('created_at', [$monthStart, $monthEnd])->sum('amount'), 2),
                'month_collected' => round($paymentsQuery->clone()->whereBetween('payment_date', [$monthStart, $monthEnd])->sum('amount'), 2),
                'month_credits_count' => $creditsQuery->clone()->whereBetween('created_at', [$monthStart, $monthEnd])->count(),
                'month_payments_count' => $paymentsQuery->clone()->whereBetween('payment_date', [$monthStart, $monthEnd])->count(),
            ];

            // Resumen del día actual
            $todaySummary = [
                'today_collected' => round($paymentsQuery->clone()->whereDate('payment_date', today())->sum('amount'), 2),
                'today_payments_count' => $paymentsQuery->clone()->whereDate('payment_date', today())->count(),
                'today_credits_delivered' => $creditsQuery->clone()->whereDate('delivered_at', today())->count(),
            ];

            // Cajas de efectivo
            $cashSummary = [
                'total_cash_on_hand' => round($balancesQuery->clone()->where('status', 'open')->sum('final_amount'), 2),
                'open_cash_balances' => $balancesQuery->clone()->where('status', 'open')->count(),
                'closed_cash_balances_today' => $balancesQuery->clone()
                    ->where('status', 'closed')
                    ->whereDate('date', today())
                    ->count(),
            ];

            $data = [
                'general' => $summary,
                'month' => $monthSummary,
                'today' => $todaySummary,
                'cash' => $cashSummary,
                'generated_at' => now(),
            ];

            return $this->sendResponse($data, 'Resumen financiero obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->sendError('Error al obtener resumen financiero', $e->getMessage(), 500);
        }
    }

    /**
     * Get map statistics (clients and routes)
     */
    public function getMapStats(Request $request)
    {
        try {
            $currentUser = auth()->user();

            // Obtener clientes con ubicación
            $clientsQuery = User::role('client')
                ->whereNotNull('latitude')
                ->whereNotNull('longitude');

            // Filtrar por roles
            if ($currentUser->hasRole('cobrador')) {
                $clientsQuery->where('assigned_cobrador_id', $currentUser->id);
            } elseif ($currentUser->hasRole('manager')) {
                $cobradorIds = User::role('cobrador')
                    ->where('assigned_manager_id', $currentUser->id)
                    ->pluck('id');

                $clientsQuery->where(function ($q) use ($currentUser, $cobradorIds) {
                    $q->where('assigned_manager_id', $currentUser->id)
                        ->orWhereIn('assigned_cobrador_id', $cobradorIds);
                });
            }

            $clientsWithLocation = $clientsQuery->count();
            $clientsWithoutLocation = User::role('client')->whereNull('latitude')->count();

            // Clientes por categoría
            $clientsByCategory = User::role('client')
                ->whereNotNull('client_category')
                ->select('client_category', DB::raw('count(*) as count'))
                ->groupBy('client_category')
                ->get()
                ->pluck('count', 'client_category');

            // Clientes por cobrador (solo con ubicación)
            $clientsByCobrador = User::role('client')
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->select('assigned_cobrador_id', DB::raw('count(*) as count'))
                ->groupBy('assigned_cobrador_id')
                ->with('assignedCobrador:id,name')
                ->get()
                ->map(function ($item) {
                    return [
                        'cobrador_id' => $item->assigned_cobrador_id,
                        'cobrador_name' => $item->assignedCobrador ? $item->assignedCobrador->name : 'Sin asignar',
                        'count' => $item->count,
                    ];
                });

            // Estadísticas de rutas (si existe el modelo Route)
            $routesStats = [];
            if (class_exists('App\Models\Route')) {
                $routesQuery = \App\Models\Route::query();

                if ($currentUser->hasRole('cobrador')) {
                    $routesQuery->where('cobrador_id', $currentUser->id);
                } elseif ($currentUser->hasRole('manager')) {
                    $cobradorIds = User::role('cobrador')
                        ->where('assigned_manager_id', $currentUser->id)
                        ->pluck('id');

                    $routesQuery->whereIn('cobrador_id', $cobradorIds);
                }

                $routesStats = [
                    'total_routes' => $routesQuery->count(),
                    'active_routes' => $routesQuery->clone()->where('is_active', true)->count(),
                ];
            }

            $data = [
                'clients_with_location' => $clientsWithLocation,
                'clients_without_location' => $clientsWithoutLocation,
                'location_coverage_percentage' => $clientsWithLocation + $clientsWithoutLocation > 0
                    ? round(($clientsWithLocation / ($clientsWithLocation + $clientsWithoutLocation)) * 100, 2)
                    : 0,
                'clients_by_category' => $clientsByCategory,
                'clients_by_cobrador' => $clientsByCobrador,
                'routes' => $routesStats,
                'generated_at' => now(),
            ];

            return $this->sendResponse($data, 'Estadísticas del mapa obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->sendError('Error al obtener estadísticas del mapa', $e->getMessage(), 500);
        }
    }
} 