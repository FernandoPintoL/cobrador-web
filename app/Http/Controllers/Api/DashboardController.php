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
} 