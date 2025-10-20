<?php

namespace App\Http\Controllers\Api;

use App\Exports\BalancesExport;
use App\Exports\CreditsExport;
use App\Exports\PaymentsExport;
use App\Exports\UsersExport;
use App\Http\Controllers\Controller;
use App\Models\CashBalance;
use App\Models\Credit;
use App\Models\Payment;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    /**
     * Generate payments report
     */
    public function paymentsReport(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'cobrador_id' => 'nullable|exists:users,id',
            'format' => 'nullable|in:pdf,html,json,excel',
        ]);

        $query = Payment::with(['cobrador', 'credit.client']);

        // Apply filters
        if ($request->start_date) {
            $query->whereDate('payment_date', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('payment_date', '<=', $request->end_date);
        }

        if ($request->cobrador_id) {
            $query->where('cobrador_id', $request->cobrador_id);
        }

        // Apply role-based visibility
        $currentUser = Auth::user();
        if ($currentUser->hasRole('cobrador')) {
            $query->where('cobrador_id', $currentUser->id);
        }

        $payments = $query->orderBy('payment_date', 'desc')->get();

        // Calculate summary
        $summary = [
            'total_payments' => $payments->count(),
            'total_amount' => $payments->sum('amount'),
            'average_payment' => $payments->avg('amount'),
            'date_range' => [
                'start' => $request->start_date,
                'end' => $request->end_date,
            ],
        ];

        // Totales reutilizables: principal (sin interés), interés y total restante para terminar cuotas
        $totalWithoutInterest = $payments->sum(function ($p) {
            return $p->principal_portion ?? 0;
        });

        $totalInterest = $payments->sum(function ($p) {
            return $p->interest_portion ?? 0;
        });

        $totalRemainingToFinish = $payments->sum(function ($p) {
            // remaining_for_installment puede ser null si no hay número de cuota
            return $p->remaining_for_installment ?? 0;
        });

        $summary['total_without_interest'] = round($totalWithoutInterest, 2);
        $summary['total_interest'] = round($totalInterest, 2);
        $summary['total_remaining_to_finish_installments'] = round($totalRemainingToFinish, 2);

        $data = [
            'payments' => $payments,
            'summary' => $summary,
            'generated_at' => now(),
            'generated_by' => $currentUser->name,
        ];

        if ($request->input('format') === 'html') {
            return view('reports.payments', $data);
        }

        if ($request->input('format') === 'json') {
            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Datos del reporte de pagos obtenidos exitosamente',
            ]);
        }

        if ($request->input('format') === 'excel') {
            $filename = 'reporte-pagos-'.now()->format('Y-m-d-H-i-s').'.xlsx';

            return Excel::download(new PaymentsExport($query, $summary), $filename);
        }

        $pdf = Pdf::loadView('reports.payments', $data);
        $filename = 'reporte-pagos-'.now()->format('Y-m-d-H-i-s').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Generate credits report
     */
    public function creditsReport(Request $request)
    {
        $request->validate([
            'status' => 'nullable|in:active,completed,pending_approval,waiting_delivery,rejected',
            'cobrador_id' => 'nullable|exists:users,id',
            'client_id' => 'nullable|exists:users,id',
            'created_by' => 'nullable|exists:users,id',
            'delivered_by' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'format' => 'nullable|in:pdf,html,json,excel',
        ]);

        $query = Credit::with(['client', 'createdBy', 'deliveredBy', 'payments', 'cashBalance']);

        // Apply filters
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Filtro flexible: cobrador puede ser creador o quien entregó
        if ($request->cobrador_id) {
            $query->where(function ($q) use ($request) {
                $q->where('created_by', $request->cobrador_id)
                    ->orWhere('delivered_by', $request->cobrador_id);
            });
        }

        // Filtros específicos
        if ($request->created_by) {
            $query->where('created_by', $request->created_by);
        }

        if ($request->delivered_by) {
            $query->where('delivered_by', $request->delivered_by);
        }

        if ($request->client_id) {
            $query->where('client_id', $request->client_id);
        }

        // Filtro por fechas de creación
        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Apply role-based visibility
        $currentUser = Auth::user();
        if ($currentUser->hasRole('cobrador')) {
            $query->where('created_by', $currentUser->id);
        } elseif ($currentUser->hasRole('manager')) {
            $query->whereHas('createdBy', function ($q) use ($currentUser) {
                $q->where('assigned_manager_id', $currentUser->id);
            });
        }

        $credits = $query->orderBy('created_at', 'desc')->get();

        // Calculate summary
        $summary = [
            'total_credits' => $credits->count(),
            'total_amount' => $credits->sum('amount'),
            'active_credits' => $credits->where('status', 'active')->count(),
            'completed_credits' => $credits->where('status', 'completed')->count(),
            'total_balance' => $credits->sum('balance'),
            'pending_amount' => $credits->sum('balance'),
        ];

        $data = [
            'credits' => $credits,
            'summary' => $summary,
            'generated_at' => now(),
            'generated_by' => $currentUser->name,
        ];

        if ($request->input('format') === 'html') {
            return view('reports.credits', $data);
        }

        if ($request->input('format') === 'json') {
            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Datos del reporte de créditos obtenidos exitosamente',
            ]);
        }

        if ($request->input('format') === 'excel') {
            $filename = 'reporte-creditos-'.now()->format('Y-m-d-H-i-s').'.xlsx';

            return Excel::download(new CreditsExport($query, $summary), $filename);
        }

        $pdf = Pdf::loadView('reports.credits', $data);
        $filename = 'reporte-creditos-'.now()->format('Y-m-d-H-i-s').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Generate users report
     */
    public function usersReport(Request $request)
    {
        $request->validate([
            'role' => 'nullable|string',
            'client_category' => 'nullable|in:A,B,C',
            'format' => 'nullable|in:pdf,html,json,excel',
        ]);

        $query = User::with(['roles']);

        // Role-based visibility
        $currentUser = Auth::user();
        if ($currentUser->hasRole('cobrador')) {
            $query->where(function ($q) use ($currentUser) {
                $q->where('id', $currentUser->id)
                    ->orWhere('assigned_cobrador_id', $currentUser->id);
            });
        } elseif ($currentUser->hasRole('manager')) {
            $cobradorIds = User::role('cobrador')
                ->where('assigned_manager_id', $currentUser->id)
                ->pluck('id');

            $query->where(function ($q) use ($currentUser, $cobradorIds) {
                $q->where('id', $currentUser->id)
                    ->orWhere('assigned_manager_id', $currentUser->id)
                    ->orWhereIn('assigned_cobrador_id', $cobradorIds);
            });
        }

        // Apply filters
        if ($request->role) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        if ($request->client_category) {
            $query->where('client_category', $request->client_category);
        }

        $users = $query->orderBy('name')->get();

        // Calculate summary
        $summary = [
            'total_users' => $users->count(),
            'by_role' => $users->groupBy(function ($user) {
                return $user->roles->first()?->name ?? 'Sin rol';
            })->map->count(),
            'by_category' => $users->where('client_category', '!=', null)
                ->groupBy('client_category')->map->count(),
            'cobradores_count' => $users->filter(function ($u) {
                return $u->roles->contains('name', 'cobrador');
            })->count(),
            'managers_count' => $users->filter(function ($u) {
                return $u->roles->contains('name', 'manager');
            })->count(),
        ];

        $data = [
            'users' => $users,
            'summary' => $summary,
            'generated_at' => now(),
            'generated_by' => Auth::user()->name,
        ];

        if ($request->input('format') === 'html') {
            return view('reports.users', $data);
        }

        if ($request->input('format') === 'json') {
            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Datos del reporte de usuarios obtenidos exitosamente',
            ]);
        }

        if ($request->input('format') === 'excel') {
            $filename = 'reporte-usuarios-'.now()->format('Y-m-d-H-i-s').'.xlsx';

            return Excel::download(new UsersExport($query, $summary), $filename);
        }

        $pdf = Pdf::loadView('reports.users', $data);
        $filename = 'reporte-usuarios-'.now()->format('Y-m-d-H-i-s').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Generate cash balances report
     */
    public function balancesReport(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'cobrador_id' => 'nullable|exists:users,id',
            'status' => 'nullable|in:open,closed,reconciled',
            'with_discrepancies' => 'nullable|boolean',
            'format' => 'nullable|in:pdf,html,json,excel',
        ]);

        $query = CashBalance::with(['cobrador', 'credits.client']);

        // Apply filters
        if ($request->start_date) {
            $query->whereDate('date', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('date', '<=', $request->end_date);
        }

        if ($request->cobrador_id) {
            $query->where('cobrador_id', $request->cobrador_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Apply role-based visibility
        $currentUser = Auth::user();
        if ($currentUser->hasRole('cobrador')) {
            $query->where('cobrador_id', $currentUser->id);
        } elseif ($currentUser->hasRole('manager')) {
            $query->whereHas('cobrador', function ($q) use ($currentUser) {
                $q->where('assigned_manager_id', $currentUser->id);
            });
        }

        $balances = $query->orderBy('date', 'desc')->get();

        // Filtrar cajas con discrepancias si se solicita
        if ($request->with_discrepancies) {
            $balances = $balances->filter(function ($balance) {
                $expected = $balance->initial_amount + $balance->collected_amount - $balance->lent_amount;
                $difference = abs($balance->final_amount - $expected);

                return $difference > 0.01; // Tolerancia de 1 centavo
            });
        }

        // Calculate summary con desglose mejorado
        $totalDiscrepancies = 0;
        $balancesWithIssues = 0;

        foreach ($balances as $balance) {
            $expected = $balance->initial_amount + $balance->collected_amount - $balance->lent_amount;
            $difference = $balance->final_amount - $expected;

            if (abs($difference) > 0.01) {
                $totalDiscrepancies += abs($difference);
                $balancesWithIssues++;
            }
        }

        $summary = [
            'total_records' => $balances->count(),
            'total_initial' => round($balances->sum('initial_amount'), 2),
            'total_collected' => round($balances->sum('collected_amount'), 2),
            'total_lent' => round($balances->sum('lent_amount'), 2),
            'total_final' => round($balances->sum('final_amount'), 2),
            'total_credits_delivered' => $balances->sum(fn ($b) => $b->credits->count()),
            'total_discrepancies' => round($totalDiscrepancies, 2),
            'balances_with_issues' => $balancesWithIssues,
            'balances_ok' => $balances->count() - $balancesWithIssues,
            'open_balances' => $balances->where('status', 'open')->count(),
            'closed_balances' => $balances->where('status', 'closed')->count(),
            'reconciled_balances' => $balances->where('status', 'reconciled')->count(),
        ];

        $data = [
            'balances' => $balances,
            'summary' => $summary,
            'generated_at' => now(),
            'generated_by' => Auth::user()->name,
        ];

        if ($request->input('format') === 'html') {
            return view('reports.balances', $data);
        }

        if ($request->input('format') === 'json') {
            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Datos del reporte de balances obtenidos exitosamente',
            ]);
        }

        if ($request->input('format') === 'excel') {
            $filename = 'reporte-balances-'.now()->format('Y-m-d-H-i-s').'.xlsx';

            return Excel::download(new BalancesExport($balances, $summary), $filename);
        }

        $pdf = Pdf::loadView('reports.balances', $data);
        $filename = 'reporte-balances-'.now()->format('Y-m-d-H-i-s').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Generate overdue credits report (Reporte de Mora)
     */
    public function overdueReport(Request $request)
    {
        $request->validate([
            'cobrador_id' => 'nullable|exists:users,id',
            'client_id' => 'nullable|exists:users,id',
            'client_category' => 'nullable|in:A,B,C',
            'min_days_overdue' => 'nullable|integer|min:1',
            'max_days_overdue' => 'nullable|integer|min:1',
            'min_overdue_amount' => 'nullable|numeric|min:0',
            'format' => 'nullable|in:pdf,html,json,excel',
        ]);

        $query = Credit::with(['client', 'createdBy', 'deliveredBy', 'payments'])
            ->where('status', 'active')
            ->where('balance', '>', 0);

        // Apply filters
        if ($request->cobrador_id) {
            $query->where(function ($q) use ($request) {
                $q->where('created_by', $request->cobrador_id)
                    ->orWhere('delivered_by', $request->cobrador_id);
            });
        }

        if ($request->client_id) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->client_category) {
            $query->whereHas('client', function ($q) use ($request) {
                $q->where('client_category', $request->client_category);
            });
        }

        // Apply role-based visibility
        $currentUser = Auth::user();
        if ($currentUser->hasRole('cobrador')) {
            $query->where(function ($q) use ($currentUser) {
                $q->where('created_by', $currentUser->id)
                    ->orWhere('delivered_by', $currentUser->id);
            });
        } elseif ($currentUser->hasRole('manager')) {
            $query->whereHas('createdBy', function ($q) use ($currentUser) {
                $q->where('assigned_manager_id', $currentUser->id);
            });
        }

        $credits = $query->orderBy('start_date', 'asc')->get();

        // Filtrar créditos con mora y calcular métricas
        $overdueCredits = $credits->filter(function ($credit) use ($request) {
            $expectedInstallments = $credit->getExpectedInstallments();
            $completedInstallments = $credit->getCompletedInstallmentsCount();
            $isOverdue = $completedInstallments < $expectedInstallments;

            if (! $isOverdue) {
                return false;
            }

            // Calcular días de mora
            $daysOverdue = $this->calculateDaysOverdue($credit);
            $overdueAmount = $credit->getOverdueAmount();

            // Aplicar filtros adicionales
            if ($request->min_days_overdue && $daysOverdue < $request->min_days_overdue) {
                return false;
            }

            if ($request->max_days_overdue && $daysOverdue > $request->max_days_overdue) {
                return false;
            }

            if ($request->min_overdue_amount && $overdueAmount < $request->min_overdue_amount) {
                return false;
            }

            // Añadir métricas calculadas al objeto
            $credit->days_overdue = $daysOverdue;
            $credit->overdue_amount = $overdueAmount;
            $credit->overdue_installments = $expectedInstallments - $completedInstallments;
            $credit->completion_rate = $expectedInstallments > 0
                ? round(($completedInstallments / $expectedInstallments) * 100, 2)
                : 0;

            return true;
        })->values();

        // Ordenar por días de mora (descendente)
        $overdueCredits = $overdueCredits->sortByDesc('days_overdue')->values();

        // Calculate comprehensive summary
        $summary = [
            'total_overdue_credits' => $overdueCredits->count(),
            'total_overdue_amount' => round($overdueCredits->sum('overdue_amount'), 2),
            'total_balance_overdue' => round($overdueCredits->sum('balance'), 2),
            'average_days_overdue' => round($overdueCredits->avg('days_overdue'), 2),
            'max_days_overdue' => $overdueCredits->max('days_overdue') ?? 0,
            'min_days_overdue' => $overdueCredits->min('days_overdue') ?? 0,

            // Distribución por gravedad
            'by_severity' => [
                'light' => $overdueCredits->filter(fn ($c) => $c->days_overdue <= 7)->count(),    // 1-7 días
                'moderate' => $overdueCredits->filter(fn ($c) => $c->days_overdue > 7 && $c->days_overdue <= 30)->count(),  // 8-30 días
                'severe' => $overdueCredits->filter(fn ($c) => $c->days_overdue > 30)->count(),   // > 30 días
            ],

            // Por cobrador
            'by_cobrador' => $overdueCredits->groupBy(function ($credit) {
                $cobrador = $credit->deliveredBy ?? $credit->createdBy;

                return $cobrador ? $cobrador->name : 'Sin asignar';
            })->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total_amount' => round($group->sum('overdue_amount'), 2),
                    'avg_days' => round($group->avg('days_overdue'), 2),
                ];
            }),

            // Por categoría de cliente
            'by_client_category' => $overdueCredits->groupBy(function ($credit) {
                return $credit->client->client_category ?? 'Sin categoría';
            })->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total_amount' => round($group->sum('overdue_amount'), 2),
                ];
            }),

            // Top 10 clientes morosos
            'top_debtors' => $overdueCredits
                ->sortByDesc('overdue_amount')
                ->take(10)
                ->map(function ($credit) {
                    return [
                        'client_name' => $credit->client->name,
                        'credit_id' => $credit->id,
                        'days_overdue' => $credit->days_overdue,
                        'overdue_amount' => $credit->overdue_amount,
                        'total_balance' => $credit->balance,
                    ];
                })
                ->values(),
        ];

        $data = [
            'credits' => $overdueCredits,
            'summary' => $summary,
            'generated_at' => now(),
            'generated_by' => $currentUser->name,
        ];

        if ($request->input('format') === 'html') {
            return view('reports.overdue', $data);
        }

        if ($request->input('format') === 'json') {
            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Datos del reporte de mora obtenidos exitosamente',
            ]);
        }

        if ($request->input('format') === 'excel') {
            $filename = 'reporte-mora-'.now()->format('Y-m-d-H-i-s').'.xlsx';

            return Excel::download(new \App\Exports\OverdueExport($overdueCredits, $summary), $filename);
        }

        $pdf = Pdf::loadView('reports.overdue', $data);
        $filename = 'reporte-mora-'.now()->format('Y-m-d-H-i-s').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Calculate days overdue for a credit based on expected vs completed installments
     */
    private function calculateDaysOverdue(Credit $credit): int
    {
        $expectedInstallments = $credit->getExpectedInstallments();
        $completedInstallments = $credit->getCompletedInstallmentsCount();

        if ($completedInstallments >= $expectedInstallments) {
            return 0;
        }

        $overdueInstallments = $expectedInstallments - $completedInstallments;

        // Calcular días basado en frecuencia
        switch ($credit->frequency) {
            case 'daily':
                return $overdueInstallments;
            case 'weekly':
                return $overdueInstallments * 7;
            case 'biweekly':
                return $overdueInstallments * 14;
            case 'monthly':
                return $overdueInstallments * 30;
            default:
                return $overdueInstallments;
        }
    }

    /**
     * Generate performance report (Reporte de Rendimiento/Desempeño)
     */
    public function performanceReport(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'cobrador_id' => 'nullable|exists:users,id',
            'manager_id' => 'nullable|exists:users,id',
            'format' => 'nullable|in:pdf,html,json,excel',
        ]);

        $startDate = $request->start_date ?? now()->subMonth()->startOfDay();
        $endDate = $request->end_date ?? now()->endOfDay();

        // Obtener cobradores según filtros
        $cobradoresQuery = User::role('cobrador')
            ->with(['assignedClients', 'assignedManager']);

        if ($request->cobrador_id) {
            $cobradoresQuery->where('id', $request->cobrador_id);
        }

        if ($request->manager_id) {
            $cobradoresQuery->where('assigned_manager_id', $request->manager_id);
        }

        // Apply role-based visibility
        $currentUser = Auth::user();
        if ($currentUser->hasRole('cobrador')) {
            $cobradoresQuery->where('id', $currentUser->id);
        } elseif ($currentUser->hasRole('manager')) {
            $cobradoresQuery->where('assigned_manager_id', $currentUser->id);
        }

        $cobradores = $cobradoresQuery->get();

        $performanceData = [];

        foreach ($cobradores as $cobrador) {
            // Créditos entregados en el período
            $creditsDelivered = Credit::where('delivered_by', $cobrador->id)
                ->whereBetween('delivered_at', [$startDate, $endDate])
                ->get();

            // Pagos cobrados en el período
            $paymentsCollected = Payment::where('cobrador_id', $cobrador->id)
                ->whereBetween('payment_date', [$startDate, $endDate])
                ->get();

            // Créditos activos del cobrador
            $activeCredits = Credit::where(function ($q) use ($cobrador) {
                $q->where('created_by', $cobrador->id)
                    ->orWhere('delivered_by', $cobrador->id);
            })
                ->where('status', 'active')
                ->with('payments')
                ->get();

            // Créditos completados en el período
            $completedCredits = Credit::where(function ($q) use ($cobrador) {
                $q->where('created_by', $cobrador->id)
                    ->orWhere('delivered_by', $cobrador->id);
            })
                ->where('status', 'completed')
                ->whereBetween('updated_at', [$startDate, $endDate])
                ->get();

            // Calcular métricas de mora
            $overdueCredits = $activeCredits->filter(function ($credit) {
                $expectedInstallments = $credit->getExpectedInstallments();
                $completedInstallments = $credit->getCompletedInstallmentsCount();

                return $completedInstallments < $expectedInstallments;
            });

            // Calcular promedio de días para completar créditos
            $avgDaysToComplete = 0;
            if ($completedCredits->count() > 0) {
                $totalDays = $completedCredits->sum(function ($credit) {
                    if ($credit->start_date && $credit->updated_at) {
                        return $credit->start_date->diffInDays($credit->updated_at);
                    }

                    return 0;
                });
                $avgDaysToComplete = round($totalDays / $completedCredits->count(), 2);
            }

            // Tasa de recuperación
            $totalLent = $creditsDelivered->sum('amount');
            $totalCollected = $paymentsCollected->sum('amount');
            $collectionRate = $totalLent > 0 ? round(($totalCollected / $totalLent) * 100, 2) : 0;

            // Portfolio quality (% de créditos sin mora)
            $totalActiveCredits = $activeCredits->count();
            $creditsOnTime = $activeCredits->count() - $overdueCredits->count();
            $portfolioQuality = $totalActiveCredits > 0
                ? round(($creditsOnTime / $totalActiveCredits) * 100, 2)
                : 100;

            // Eficiencia (pagos vs créditos activos)
            $efficiency = $totalActiveCredits > 0
                ? round($paymentsCollected->count() / $totalActiveCredits, 2)
                : 0;

            $performanceData[] = [
                'cobrador_id' => $cobrador->id,
                'cobrador_name' => $cobrador->name,
                'manager_name' => $cobrador->assignedManager?->name ?? 'Sin asignar',
                'metrics' => [
                    'credits_delivered' => $creditsDelivered->count(),
                    'total_amount_lent' => round($totalLent, 2),
                    'payments_collected_count' => $paymentsCollected->count(),
                    'total_amount_collected' => round($totalCollected, 2),
                    'collection_rate' => $collectionRate,
                    'active_credits' => $totalActiveCredits,
                    'completed_credits' => $completedCredits->count(),
                    'overdue_credits' => $overdueCredits->count(),
                    'portfolio_quality' => $portfolioQuality,
                    'avg_days_to_complete' => $avgDaysToComplete,
                    'efficiency_score' => $efficiency,
                    'active_clients' => $cobrador->assignedClients->count(),
                ],
            ];
        }

        // Ordenar por tasa de cobranza (descendente)
        $performanceData = collect($performanceData)->sortByDesc('metrics.collection_rate')->values();

        // Calculate summary
        $summary = [
            'total_cobradores' => $cobradores->count(),
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'totals' => [
                'credits_delivered' => collect($performanceData)->sum('metrics.credits_delivered'),
                'amount_lent' => round(collect($performanceData)->sum('metrics.total_amount_lent'), 2),
                'payments_collected' => collect($performanceData)->sum('metrics.payments_collected_count'),
                'amount_collected' => round(collect($performanceData)->sum('metrics.total_amount_collected'), 2),
                'active_credits' => collect($performanceData)->sum('metrics.active_credits'),
                'overdue_credits' => collect($performanceData)->sum('metrics.overdue_credits'),
            ],
            'averages' => [
                'collection_rate' => round(collect($performanceData)->avg('metrics.collection_rate'), 2),
                'portfolio_quality' => round(collect($performanceData)->avg('metrics.portfolio_quality'), 2),
                'efficiency_score' => round(collect($performanceData)->avg('metrics.efficiency_score'), 2),
            ],
            'top_performers' => collect($performanceData)->take(5)->map(fn ($p) => [
                'name' => $p['cobrador_name'],
                'collection_rate' => $p['metrics']['collection_rate'],
                'portfolio_quality' => $p['metrics']['portfolio_quality'],
            ])->values(),
        ];

        $data = [
            'performance' => $performanceData,
            'summary' => $summary,
            'generated_at' => now(),
            'generated_by' => $currentUser->name,
        ];

        if ($request->input('format') === 'html') {
            return view('reports.performance', $data);
        }

        if ($request->input('format') === 'json') {
            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Datos del reporte de rendimiento obtenidos exitosamente',
            ]);
        }

        if ($request->input('format') === 'excel') {
            $filename = 'reporte-rendimiento-'.now()->format('Y-m-d-H-i-s').'.xlsx';

            return Excel::download(new \App\Exports\PerformanceExport($performanceData, $summary), $filename);
        }

        $pdf = Pdf::loadView('reports.performance', $data);
        $filename = 'reporte-rendimiento-'.now()->format('Y-m-d-H-i-s').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Generate cash flow forecast report (Reporte de Proyección de Flujo de Efectivo)
     */
    public function cashFlowForecastReport(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'cobrador_id' => 'nullable|exists:users,id',
            'group_by' => 'nullable|in:day,week,month',
            'format' => 'nullable|in:pdf,html,json,excel',
        ]);

        $startDate = $request->start_date ?? now()->startOfDay();
        $endDate = $request->end_date ?? now()->addMonth()->endOfDay();
        $groupBy = $request->group_by ?? 'day';

        // Obtener todos los créditos activos
        $query = Credit::where('status', 'active')
            ->where('balance', '>', 0)
            ->with(['client', 'deliveredBy', 'payments']);

        if ($request->cobrador_id) {
            $query->where(function ($q) use ($request) {
                $q->where('created_by', $request->cobrador_id)
                    ->orWhere('delivered_by', $request->cobrador_id);
            });
        }

        // Apply role-based visibility
        $currentUser = Auth::user();
        if ($currentUser->hasRole('cobrador')) {
            $query->where(function ($q) use ($currentUser) {
                $q->where('created_by', $currentUser->id)
                    ->orWhere('delivered_by', $currentUser->id);
            });
        } elseif ($currentUser->hasRole('manager')) {
            $query->whereHas('createdBy', function ($q) use ($currentUser) {
                $q->where('assigned_manager_id', $currentUser->id);
            });
        }

        $activeCredits = $query->get();

        // Proyectar pagos esperados
        $projectedPayments = [];
        $currentDate = now()->copy();

        foreach ($activeCredits as $credit) {
            if (! $credit->start_date) {
                continue;
            }

            $installmentAmount = $credit->installment_amount ?? 0;
            $remainingInstallments = $credit->getRemainingInstallments();
            $nextPaymentDate = $this->getNextPaymentDate($credit);

            // Proyectar cada cuota pendiente
            $paymentsToProject = min($remainingInstallments, 100); // Límite de 100 cuotas futuras

            for ($i = 0; $i < $paymentsToProject; $i++) {
                if (! $nextPaymentDate || $nextPaymentDate->gt($endDate)) {
                    break;
                }

                if ($nextPaymentDate->gte($startDate)) {
                    $projectedPayments[] = [
                        'date' => $nextPaymentDate->copy(),
                        'credit_id' => $credit->id,
                        'client_name' => $credit->client->name,
                        'cobrador_name' => $credit->deliveredBy?->name ?? $credit->createdBy?->name ?? 'Sin asignar',
                        'amount' => $installmentAmount,
                        'frequency' => $credit->frequency,
                        'status' => $nextPaymentDate->lt($currentDate) ? 'overdue' : 'pending',
                    ];
                }

                // Calcular siguiente fecha de pago
                $nextPaymentDate = $this->calculateNextPaymentDate($nextPaymentDate, $credit->frequency);
            }
        }

        // Agrupar proyecciones
        $groupedProjections = $this->groupProjectionsByPeriod(
            collect($projectedPayments),
            $groupBy,
            $startDate,
            $endDate
        );

        // Calculate summary
        $totalProjected = collect($projectedPayments)->sum('amount');
        $overdueAmount = collect($projectedPayments)
            ->where('status', 'overdue')
            ->sum('amount');

        $summary = [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'total_active_credits' => $activeCredits->count(),
            'total_projected_payments' => count($projectedPayments),
            'total_projected_amount' => round($totalProjected, 2),
            'overdue_amount' => round($overdueAmount, 2),
            'pending_amount' => round($totalProjected - $overdueAmount, 2),
            'by_frequency' => collect($projectedPayments)->groupBy('frequency')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'amount' => round($group->sum('amount'), 2),
                ];
            }),
            'by_cobrador' => collect($projectedPayments)->groupBy('cobrador_name')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'amount' => round($group->sum('amount'), 2),
                ];
            }),
        ];

        $data = [
            'projections' => $groupedProjections,
            'detailed_payments' => collect($projectedPayments)->sortBy('date')->values(),
            'summary' => $summary,
            'generated_at' => now(),
            'generated_by' => $currentUser->name,
        ];

        if ($request->input('format') === 'html') {
            return view('reports.cash-flow-forecast', $data);
        }

        if ($request->input('format') === 'json') {
            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Datos del reporte de proyección de flujo obtenidos exitosamente',
            ]);
        }

        if ($request->input('format') === 'excel') {
            $filename = 'reporte-proyeccion-flujo-'.now()->format('Y-m-d-H-i-s').'.xlsx';

            return Excel::download(new \App\Exports\CashFlowForecastExport($projectedPayments, $summary), $filename);
        }

        $pdf = Pdf::loadView('reports.cash-flow-forecast', $data);
        $filename = 'reporte-proyeccion-flujo-'.now()->format('Y-m-d-H-i-s').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Generate waiting list report (Reporte de Lista de Espera)
     */
    public function waitingListReport(Request $request)
    {
        $request->validate([
            'status' => 'nullable|in:pending_approval,waiting_delivery,all',
            'cobrador_id' => 'nullable|exists:users,id',
            'format' => 'nullable|in:pdf,html,json,excel',
        ]);

        $statusFilter = $request->status ?? 'all';

        // Créditos pendientes de aprobación
        $pendingApprovalQuery = Credit::where('status', 'pending_approval')
            ->with(['client', 'createdBy']);

        // Créditos esperando entrega
        $waitingDeliveryQuery = Credit::where('status', 'waiting_delivery')
            ->with(['client', 'createdBy']);

        if ($request->cobrador_id) {
            $pendingApprovalQuery->where('created_by', $request->cobrador_id);
            $waitingDeliveryQuery->where('created_by', $request->cobrador_id);
        }

        // Apply role-based visibility
        $currentUser = Auth::user();
        if ($currentUser->hasRole('cobrador')) {
            $pendingApprovalQuery->where('created_by', $currentUser->id);
            $waitingDeliveryQuery->where('created_by', $currentUser->id);
        } elseif ($currentUser->hasRole('manager')) {
            $pendingApprovalQuery->whereHas('createdBy', function ($q) use ($currentUser) {
                $q->where('assigned_manager_id', $currentUser->id);
            });
            $waitingDeliveryQuery->whereHas('createdBy', function ($q) use ($currentUser) {
                $q->where('assigned_manager_id', $currentUser->id);
            });
        }

        $pendingApproval = $pendingApprovalQuery->get();
        $waitingDelivery = $waitingDeliveryQuery->get();

        // Calcular métricas de tiempo
        $pendingApprovalWithMetrics = $pendingApproval->map(function ($credit) {
            $daysWaiting = $credit->created_at->diffInDays(now());
            $credit->days_waiting = $daysWaiting;
            $credit->status_label = 'Pendiente de Aprobación';

            return $credit;
        });

        $waitingDeliveryWithMetrics = $waitingDelivery->map(function ($credit) {
            $daysWaiting = $credit->approved_at
                ? $credit->approved_at->diffInDays(now())
                : $credit->created_at->diffInDays(now());

            $credit->days_waiting = $daysWaiting;
            $credit->status_label = 'Esperando Entrega';

            // Verificar si está vencido para entrega
            $isOverdue = false;
            if ($credit->scheduled_delivery_date && $credit->scheduled_delivery_date->lt(now())) {
                $isOverdue = true;
                $credit->days_overdue_delivery = $credit->scheduled_delivery_date->diffInDays(now());
            }
            $credit->is_overdue_delivery = $isOverdue;

            return $credit;
        });

        // Combinar según filtro
        if ($statusFilter === 'pending_approval') {
            $allCredits = $pendingApprovalWithMetrics;
        } elseif ($statusFilter === 'waiting_delivery') {
            $allCredits = $waitingDeliveryWithMetrics;
        } else {
            $allCredits = $pendingApprovalWithMetrics->concat($waitingDeliveryWithMetrics);
        }

        $allCredits = $allCredits->sortByDesc('days_waiting')->values();

        // Calculate summary
        $avgTimeToApprove = 0;
        $approvedCredits = Credit::where('status', '!=', 'pending_approval')
            ->whereNotNull('approved_at')
            ->whereDate('approved_at', '>=', now()->subMonth())
            ->get();

        if ($approvedCredits->count() > 0) {
            $totalDays = $approvedCredits->sum(function ($credit) {
                return $credit->created_at->diffInDays($credit->approved_at);
            });
            $avgTimeToApprove = round($totalDays / $approvedCredits->count(), 2);
        }

        $overdueForDelivery = $waitingDeliveryWithMetrics->filter(fn ($c) => $c->is_overdue_delivery ?? false);

        $summary = [
            'total_in_waiting_list' => $allCredits->count(),
            'pending_approval' => $pendingApproval->count(),
            'waiting_delivery' => $waitingDelivery->count(),
            'overdue_for_delivery' => $overdueForDelivery->count(),
            'total_amount_pending' => round($allCredits->sum('amount'), 2),
            'avg_days_waiting' => round($allCredits->avg('days_waiting'), 2),
            'max_days_waiting' => $allCredits->max('days_waiting') ?? 0,
            'avg_time_to_approve' => $avgTimeToApprove,
            'by_cobrador' => $allCredits->groupBy(function ($credit) {
                return $credit->createdBy?->name ?? 'Sin asignar';
            })->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total_amount' => round($group->sum('amount'), 2),
                ];
            }),
            'ready_for_delivery_today' => $waitingDelivery->filter(function ($credit) {
                return $credit->scheduled_delivery_date
                    && $credit->scheduled_delivery_date->isToday();
            })->count(),
        ];

        $data = [
            'credits' => $allCredits,
            'summary' => $summary,
            'generated_at' => now(),
            'generated_by' => $currentUser->name,
        ];

        if ($request->input('format') === 'html') {
            return view('reports.waiting-list', $data);
        }

        if ($request->input('format') === 'json') {
            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Datos del reporte de lista de espera obtenidos exitosamente',
            ]);
        }

        if ($request->input('format') === 'excel') {
            $filename = 'reporte-lista-espera-'.now()->format('Y-m-d-H-i-s').'.xlsx';

            return Excel::download(new \App\Exports\WaitingListExport($allCredits, $summary), $filename);
        }

        $pdf = Pdf::loadView('reports.waiting-list', $data);
        $filename = 'reporte-lista-espera-'.now()->format('Y-m-d-H-i-s').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Helper: Get next payment date for a credit
     */
    private function getNextPaymentDate(Credit $credit): ?\Carbon\Carbon
    {
        if (! $credit->start_date) {
            return null;
        }

        $lastPayment = $credit->payments()->orderBy('payment_date', 'desc')->first();

        if ($lastPayment) {
            return $this->calculateNextPaymentDate($lastPayment->payment_date, $credit->frequency);
        }

        return $credit->start_date->copy();
    }

    /**
     * Helper: Calculate next payment date based on frequency
     */
    private function calculateNextPaymentDate(\Carbon\Carbon $currentDate, string $frequency): \Carbon\Carbon
    {
        switch ($frequency) {
            case 'daily':
                return $currentDate->copy()->addDay();
            case 'weekly':
                return $currentDate->copy()->addWeek();
            case 'biweekly':
                return $currentDate->copy()->addWeeks(2);
            case 'monthly':
                return $currentDate->copy()->addMonth();
            default:
                return $currentDate->copy()->addDay();
        }
    }

    /**
     * Helper: Group projections by period
     */
    private function groupProjectionsByPeriod($projections, string $groupBy, $startDate, $endDate): array
    {
        $grouped = [];

        foreach ($projections as $projection) {
            $key = match ($groupBy) {
                'week' => $projection['date']->format('Y-W'),
                'month' => $projection['date']->format('Y-m'),
                default => $projection['date']->format('Y-m-d'),
            };

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'period' => $key,
                    'period_label' => $this->formatPeriodLabel($projection['date'], $groupBy),
                    'count' => 0,
                    'total_amount' => 0,
                    'overdue_count' => 0,
                    'pending_count' => 0,
                ];
            }

            $grouped[$key]['count']++;
            $grouped[$key]['total_amount'] += $projection['amount'];

            if ($projection['status'] === 'overdue') {
                $grouped[$key]['overdue_count']++;
            } else {
                $grouped[$key]['pending_count']++;
            }
        }

        return array_values($grouped);
    }

    /**
     * Helper: Format period label for display
     */
    private function formatPeriodLabel(\Carbon\Carbon $date, string $groupBy): string
    {
        return match ($groupBy) {
            'week' => 'Semana '.$date->weekOfYear.', '.$date->year,
            'month' => $date->translatedFormat('F Y'),
            default => $date->format('Y-m-d'),
        };
    }

    /**
     * Generate daily activity report (Reporte de Actividad Diaria)
     */
    public function dailyActivityReport(Request $request)
    {
        $request->validate([
            'date' => 'nullable|date',
            'cobrador_id' => 'nullable|exists:users,id',
            'format' => 'nullable|in:pdf,html,json,excel',
        ]);

        $date = $request->date ?? now()->format('Y-m-d');
        $targetDate = \Carbon\Carbon::parse($date);

        // Obtener cobradores según filtros
        $cobradoresQuery = User::role('cobrador');

        if ($request->cobrador_id) {
            $cobradoresQuery->where('id', $request->cobrador_id);
        }

        // Apply role-based visibility
        $currentUser = Auth::user();
        if ($currentUser->hasRole('cobrador')) {
            $cobradoresQuery->where('id', $currentUser->id);
        } elseif ($currentUser->hasRole('manager')) {
            $cobradoresQuery->where('assigned_manager_id', $currentUser->id);
        }

        $cobradores = $cobradoresQuery->get();

        $activityData = [];

        foreach ($cobradores as $cobrador) {
            // Caja del día
            $cashBalance = CashBalance::where('cobrador_id', $cobrador->id)
                ->whereDate('date', $targetDate)
                ->first();

            // Créditos entregados hoy
            $creditsDelivered = Credit::where('delivered_by', $cobrador->id)
                ->whereDate('delivered_at', $targetDate)
                ->with(['client'])
                ->get();

            // Pagos cobrados hoy
            $paymentsCollected = Payment::where('cobrador_id', $cobrador->id)
                ->whereDate('payment_date', $targetDate)
                ->with(['credit.client'])
                ->get();

            // Créditos pendientes de entregar hoy
            $creditsToDeliverToday = Credit::where('created_by', $cobrador->id)
                ->where('status', 'waiting_delivery')
                ->whereDate('scheduled_delivery_date', $targetDate)
                ->with(['client'])
                ->get();

            // Pagos esperados hoy (créditos activos con fecha de pago hoy)
            $activeCredits = Credit::where(function ($q) use ($cobrador) {
                $q->where('created_by', $cobrador->id)
                    ->orWhere('delivered_by', $cobrador->id);
            })
                ->where('status', 'active')
                ->with(['client', 'payments'])
                ->get();

            $expectedPaymentsToday = [];
            foreach ($activeCredits as $credit) {
                $nextPaymentDate = $this->getNextPaymentDate($credit);
                if ($nextPaymentDate && $nextPaymentDate->isSameDay($targetDate)) {
                    $expectedPaymentsToday[] = [
                        'credit_id' => $credit->id,
                        'client_name' => $credit->client->name,
                        'amount' => $credit->installment_amount ?? 0,
                        'frequency' => $credit->frequency,
                    ];
                }
            }

            // Cuántos de los pagos esperados se cobraron
            $expectedPaymentsCount = count($expectedPaymentsToday);
            $actualPaymentsCount = $paymentsCollected->count();
            $collectionEfficiency = $expectedPaymentsCount > 0
                ? round(($actualPaymentsCount / $expectedPaymentsCount) * 100, 2)
                : 0;

            $activityData[] = [
                'cobrador_id' => $cobrador->id,
                'cobrador_name' => $cobrador->name,
                'cash_balance' => [
                    'status' => $cashBalance?->status ?? 'not_opened',
                    'initial_amount' => $cashBalance?->initial_amount ?? 0,
                    'collected_amount' => $cashBalance?->collected_amount ?? 0,
                    'lent_amount' => $cashBalance?->lent_amount ?? 0,
                    'final_amount' => $cashBalance?->final_amount ?? 0,
                ],
                'credits_delivered' => [
                    'count' => $creditsDelivered->count(),
                    'total_amount' => round($creditsDelivered->sum('amount'), 2),
                    'details' => $creditsDelivered->map(fn ($c) => [
                        'id' => $c->id,
                        'client' => $c->client->name,
                        'amount' => $c->amount,
                    ])->values(),
                ],
                'payments_collected' => [
                    'count' => $paymentsCollected->count(),
                    'total_amount' => round($paymentsCollected->sum('amount'), 2),
                    'details' => $paymentsCollected->map(fn ($p) => [
                        'id' => $p->id,
                        'client' => $p->credit->client->name,
                        'amount' => $p->amount,
                    ])->values(),
                ],
                'credits_to_deliver_today' => [
                    'count' => $creditsToDeliverToday->count(),
                    'total_amount' => round($creditsToDeliverToday->sum('amount'), 2),
                    'details' => $creditsToDeliverToday->map(fn ($c) => [
                        'id' => $c->id,
                        'client' => $c->client->name,
                        'amount' => $c->amount,
                    ])->values(),
                ],
                'expected_payments' => [
                    'count' => $expectedPaymentsCount,
                    'collected' => $actualPaymentsCount,
                    'pending' => $expectedPaymentsCount - $actualPaymentsCount,
                    'efficiency' => $collectionEfficiency,
                    'details' => $expectedPaymentsToday,
                ],
            ];
        }

        // Calculate summary
        $summary = [
            'date' => $targetDate->format('Y-m-d'),
            'day_name' => $targetDate->translatedFormat('l'),
            'total_cobradores' => $cobradores->count(),
            'totals' => [
                'credits_delivered' => collect($activityData)->sum('credits_delivered.count'),
                'amount_lent' => round(collect($activityData)->sum('credits_delivered.total_amount'), 2),
                'payments_collected' => collect($activityData)->sum('payments_collected.count'),
                'amount_collected' => round(collect($activityData)->sum('payments_collected.total_amount'), 2),
                'expected_payments' => collect($activityData)->sum('expected_payments.count'),
                'pending_deliveries' => collect($activityData)->sum('credits_to_deliver_today.count'),
            ],
            'cash_balances' => [
                'opened' => collect($activityData)->filter(fn ($a) => $a['cash_balance']['status'] === 'open')->count(),
                'closed' => collect($activityData)->filter(fn ($a) => $a['cash_balance']['status'] === 'closed')->count(),
                'not_opened' => collect($activityData)->filter(fn ($a) => $a['cash_balance']['status'] === 'not_opened')->count(),
            ],
            'overall_efficiency' => count($activityData) > 0
                ? round(collect($activityData)->avg('expected_payments.efficiency'), 2)
                : 0,
        ];

        $data = [
            'activities' => $activityData,
            'summary' => $summary,
            'generated_at' => now(),
            'generated_by' => $currentUser->name,
        ];

        if ($request->input('format') === 'html') {
            return view('reports.daily-activity', $data);
        }

        if ($request->input('format') === 'json') {
            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Datos del reporte de actividad diaria obtenidos exitosamente',
            ]);
        }

        if ($request->input('format') === 'excel') {
            $filename = 'reporte-actividad-diaria-'.now()->format('Y-m-d-H-i-s').'.xlsx';

            return Excel::download(new \App\Exports\DailyActivityExport($activityData, $summary), $filename);
        }

        $pdf = Pdf::loadView('reports.daily-activity', $data);
        $filename = 'reporte-actividad-diaria-'.now()->format('Y-m-d-H-i-s').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Generate portfolio report (Reporte de Cartera)
     */
    public function portfolioReport(Request $request)
    {
        $request->validate([
            'cobrador_id' => 'nullable|exists:users,id',
            'include_completed' => 'nullable|boolean',
            'format' => 'nullable|in:pdf,html,json,excel',
        ]);

        $includeCompleted = $request->include_completed ?? false;

        // Obtener créditos activos (y completados si se solicita)
        $query = Credit::with(['client', 'deliveredBy', 'createdBy', 'payments']);

        if ($includeCompleted) {
            $query->whereIn('status', ['active', 'completed']);
        } else {
            $query->where('status', 'active');
        }

        if ($request->cobrador_id) {
            $query->where(function ($q) use ($request) {
                $q->where('created_by', $request->cobrador_id)
                    ->orWhere('delivered_by', $request->cobrador_id);
            });
        }

        // Apply role-based visibility
        $currentUser = Auth::user();
        if ($currentUser->hasRole('cobrador')) {
            $query->where(function ($q) use ($currentUser) {
                $q->where('created_by', $currentUser->id)
                    ->orWhere('delivered_by', $currentUser->id);
            });
        } elseif ($currentUser->hasRole('manager')) {
            $query->whereHas('createdBy', function ($q) use ($currentUser) {
                $q->where('assigned_manager_id', $currentUser->id);
            });
        }

        $credits = $query->get();

        // Clasificar créditos
        $currentCredits = $credits->filter(fn ($c) => $c->status === 'active' && ! $this->isCreditOverdue($c));
        $overdueCredits = $credits->filter(fn ($c) => $c->status === 'active' && $this->isCreditOverdue($c));
        $completedCredits = $credits->filter(fn ($c) => $c->status === 'completed');

        // Análisis por cobrador
        $portfolioByCobrador = $credits->groupBy(function ($credit) {
            $cobrador = $credit->deliveredBy ?? $credit->createdBy;

            return $cobrador ? $cobrador->name : 'Sin asignar';
        })->map(function ($group) {
            $active = $group->where('status', 'active');
            $overdue = $active->filter(fn ($c) => $this->isCreditOverdue($c));

            return [
                'total_credits' => $group->count(),
                'active_credits' => $active->count(),
                'completed_credits' => $group->where('status', 'completed')->count(),
                'total_balance' => round($active->sum('balance'), 2),
                'total_lent' => round($group->sum('amount'), 2),
                'overdue_credits' => $overdue->count(),
                'overdue_amount' => round($overdue->sum('balance'), 2),
                'portfolio_quality' => $active->count() > 0
                    ? round((($active->count() - $overdue->count()) / $active->count()) * 100, 2)
                    : 100,
            ];
        });

        // Análisis por categoría de cliente
        $portfolioByCategory = $credits->groupBy(function ($credit) {
            return $credit->client->client_category ?? 'Sin categoría';
        })->map(function ($group) {
            $active = $group->where('status', 'active');

            return [
                'total_credits' => $group->count(),
                'active_balance' => round($active->sum('balance'), 2),
                'total_lent' => round($group->sum('amount'), 2),
            ];
        });

        // Top 10 clientes por balance
        $topClientsByBalance = $credits->where('status', 'active')
            ->sortByDesc('balance')
            ->take(10)
            ->map(function ($credit) {
                return [
                    'client_name' => $credit->client->name,
                    'client_category' => $credit->client->client_category ?? 'N/A',
                    'credit_id' => $credit->id,
                    'balance' => $credit->balance,
                    'total_amount' => $credit->total_amount,
                    'completion_rate' => round(($credit->total_amount - $credit->balance) / $credit->total_amount * 100, 2),
                ];
            })
            ->values();

        // Distribución por antigüedad
        $portfolioByAge = [
            '0_30_days' => $currentCredits->filter(fn ($c) => $c->start_date && $c->start_date->diffInDays(now()) <= 30)->count(),
            '31_60_days' => $currentCredits->filter(fn ($c) => $c->start_date && $c->start_date->diffInDays(now()) > 30 && $c->start_date->diffInDays(now()) <= 60)->count(),
            '61_90_days' => $currentCredits->filter(fn ($c) => $c->start_date && $c->start_date->diffInDays(now()) > 60 && $c->start_date->diffInDays(now()) <= 90)->count(),
            'over_90_days' => $currentCredits->filter(fn ($c) => $c->start_date && $c->start_date->diffInDays(now()) > 90)->count(),
        ];

        // Calculate summary
        $totalLent = $credits->sum('amount');
        $totalCollected = $credits->sum(fn ($c) => $c->amount - $c->balance);
        $activeBalance = $currentCredits->sum('balance') + $overdueCredits->sum('balance');

        $summary = [
            'total_credits' => $credits->count(),
            'active_credits' => $currentCredits->count() + $overdueCredits->count(),
            'completed_credits' => $completedCredits->count(),
            'current_credits' => $currentCredits->count(),
            'overdue_credits' => $overdueCredits->count(),
            'total_lent' => round($totalLent, 2),
            'total_collected' => round($totalCollected, 2),
            'active_balance' => round($activeBalance, 2),
            'overdue_balance' => round($overdueCredits->sum('balance'), 2),
            'portfolio_quality' => ($currentCredits->count() + $overdueCredits->count()) > 0
                ? round(($currentCredits->count() / ($currentCredits->count() + $overdueCredits->count())) * 100, 2)
                : 100,
            'collection_rate' => $totalLent > 0
                ? round(($totalCollected / $totalLent) * 100, 2)
                : 0,
        ];

        $data = [
            'portfolio_by_cobrador' => $portfolioByCobrador,
            'portfolio_by_category' => $portfolioByCategory,
            'top_clients_by_balance' => $topClientsByBalance,
            'portfolio_by_age' => $portfolioByAge,
            'summary' => $summary,
            'generated_at' => now(),
            'generated_by' => $currentUser->name,
        ];

        if ($request->input('format') === 'html') {
            return view('reports.portfolio', $data);
        }

        if ($request->input('format') === 'json') {
            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Datos del reporte de cartera obtenidos exitosamente',
            ]);
        }

        if ($request->input('format') === 'excel') {
            $filename = 'reporte-cartera-'.now()->format('Y-m-d-H-i-s').'.xlsx';

            return Excel::download(new \App\Exports\PortfolioExport($portfolioByCobrador, $summary), $filename);
        }

        $pdf = Pdf::loadView('reports.portfolio', $data);
        $filename = 'reporte-cartera-'.now()->format('Y-m-d-H-i-s').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Generate commissions report (Reporte de Comisiones)
     */
    public function commissionsReport(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'cobrador_id' => 'nullable|exists:users,id',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'format' => 'nullable|in:pdf,html,json,excel',
        ]);

        $startDate = $request->start_date ?? now()->startOfMonth();
        $endDate = $request->end_date ?? now()->endOfMonth();
        $commissionRate = $request->commission_rate ?? 10; // 10% por defecto

        // Obtener cobradores según filtros
        $cobradoresQuery = User::role('cobrador');

        if ($request->cobrador_id) {
            $cobradoresQuery->where('id', $request->cobrador_id);
        }

        // Apply role-based visibility
        $currentUser = Auth::user();
        if ($currentUser->hasRole('cobrador')) {
            $cobradoresQuery->where('id', $currentUser->id);
        } elseif ($currentUser->hasRole('manager')) {
            $cobradoresQuery->where('assigned_manager_id', $currentUser->id);
        }

        $cobradores = $cobradoresQuery->get();

        $commissionsData = [];

        foreach ($cobradores as $cobrador) {
            // Pagos cobrados en el período
            $paymentsCollected = Payment::where('cobrador_id', $cobrador->id)
                ->whereBetween('payment_date', [$startDate, $endDate])
                ->get();

            // Créditos entregados en el período
            $creditsDelivered = Credit::where('delivered_by', $cobrador->id)
                ->whereBetween('delivered_at', [$startDate, $endDate])
                ->get();

            // Cálculo de comisiones
            $totalCollected = $paymentsCollected->sum('amount');
            $totalLent = $creditsDelivered->sum('amount');

            // Comisión por cobranza
            $commissionOnCollection = ($totalCollected * $commissionRate) / 100;

            // Bonus por cumplimiento (ejemplo: si cobró más del 80% de lo esperado)
            $bonus = 0;
            $activeCredits = Credit::where(function ($q) use ($cobrador) {
                $q->where('created_by', $cobrador->id)
                    ->orWhere('delivered_by', $cobrador->id);
            })
                ->where('status', 'active')
                ->get();

            $totalExpectedInPeriod = 0;
            foreach ($activeCredits as $credit) {
                // Calcular cuántos pagos deberían haberse hecho en el período
                if ($credit->start_date && $credit->start_date->lte($endDate)) {
                    $daysInPeriod = max(0, min(
                        $credit->start_date->diffInDays($endDate),
                        $startDate->diffInDays($endDate)
                    ));

                    $expectedInstallments = match ($credit->frequency) {
                        'daily' => $daysInPeriod,
                        'weekly' => floor($daysInPeriod / 7),
                        'biweekly' => floor($daysInPeriod / 14),
                        'monthly' => floor($daysInPeriod / 30),
                        default => 0,
                    };

                    $totalExpectedInPeriod += $expectedInstallments * ($credit->installment_amount ?? 0);
                }
            }

            $collectionPercentage = $totalExpectedInPeriod > 0
                ? ($totalCollected / $totalExpectedInPeriod) * 100
                : 0;

            if ($collectionPercentage >= 80) {
                $bonus = $commissionOnCollection * 0.2; // 20% de bonus
            }

            $totalCommission = $commissionOnCollection + $bonus;

            $commissionsData[] = [
                'cobrador_id' => $cobrador->id,
                'cobrador_name' => $cobrador->name,
                'payments_collected' => [
                    'count' => $paymentsCollected->count(),
                    'total_amount' => round($totalCollected, 2),
                ],
                'credits_delivered' => [
                    'count' => $creditsDelivered->count(),
                    'total_amount' => round($totalLent, 2),
                ],
                'commission' => [
                    'rate' => $commissionRate,
                    'on_collection' => round($commissionOnCollection, 2),
                    'bonus' => round($bonus, 2),
                    'total' => round($totalCommission, 2),
                ],
                'performance' => [
                    'expected_collection' => round($totalExpectedInPeriod, 2),
                    'actual_collection' => round($totalCollected, 2),
                    'collection_percentage' => round($collectionPercentage, 2),
                ],
            ];
        }

        // Ordenar por comisión total (descendente)
        $commissionsData = collect($commissionsData)->sortByDesc('commission.total')->values();

        // Calculate summary
        $summary = [
            'period' => [
                'start' => is_string($startDate) ? $startDate : $startDate->format('Y-m-d'),
                'end' => is_string($endDate) ? $endDate : $endDate->format('Y-m-d'),
            ],
            'commission_rate' => $commissionRate,
            'total_cobradores' => $cobradores->count(),
            'totals' => [
                'collected' => round(collect($commissionsData)->sum('payments_collected.total_amount'), 2),
                'lent' => round(collect($commissionsData)->sum('credits_delivered.total_amount'), 2),
                'commissions' => round(collect($commissionsData)->sum('commission.total'), 2),
                'bonuses' => round(collect($commissionsData)->sum('commission.bonus'), 2),
            ],
            'top_earners' => $commissionsData->take(5)->map(fn ($c) => [
                'name' => $c['cobrador_name'],
                'commission' => $c['commission']['total'],
                'collection_percentage' => $c['performance']['collection_percentage'],
            ])->values(),
        ];

        $data = [
            'commissions' => $commissionsData,
            'summary' => $summary,
            'generated_at' => now(),
            'generated_by' => $currentUser->name,
        ];

        if ($request->input('format') === 'html') {
            return view('reports.commissions', $data);
        }

        if ($request->input('format') === 'json') {
            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Datos del reporte de comisiones obtenidos exitosamente',
            ]);
        }

        if ($request->input('format') === 'excel') {
            $filename = 'reporte-comisiones-'.now()->format('Y-m-d-H-i-s').'.xlsx';

            return Excel::download(new \App\Exports\CommissionsExport($commissionsData, $summary), $filename);
        }

        $pdf = Pdf::loadView('reports.commissions', $data);
        $filename = 'reporte-comisiones-'.now()->format('Y-m-d-H-i-s').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Helper: Check if credit is overdue
     */
    private function isCreditOverdue(Credit $credit): bool
    {
        $expectedInstallments = $credit->getExpectedInstallments();
        $completedInstallments = $credit->getCompletedInstallmentsCount();

        return $completedInstallments < $expectedInstallments;
    }

    /**
     * Get available report types
     */
    public function getReportTypes()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'payments' => [
                    'name' => 'Reporte de Pagos',
                    'description' => 'Historial de pagos con filtros por fecha y cobrador',
                    'filters' => ['start_date', 'end_date', 'cobrador_id'],
                    'formats' => ['pdf', 'html', 'json', 'excel'],
                ],
                'credits' => [
                    'name' => 'Reporte de Créditos',
                    'description' => 'Lista de créditos con estado y asignaciones',
                    'filters' => ['status', 'cobrador_id', 'client_id', 'created_by', 'delivered_by', 'start_date', 'end_date'],
                    'formats' => ['pdf', 'html', 'json', 'excel'],
                ],
                'balances' => [
                    'name' => 'Reporte de Balances',
                    'description' => 'Balances de efectivo por cobrador con desglose de créditos',
                    'filters' => ['start_date', 'end_date', 'cobrador_id', 'status', 'with_discrepancies'],
                    'formats' => ['pdf', 'html', 'json', 'excel'],
                ],
                'overdue' => [
                    'name' => 'Reporte de Mora',
                    'description' => 'Créditos vencidos con análisis de mora por cobrador y categoría',
                    'filters' => ['cobrador_id', 'client_id', 'client_category', 'min_days_overdue', 'max_days_overdue', 'min_overdue_amount'],
                    'formats' => ['pdf', 'html', 'json', 'excel'],
                ],
                'performance' => [
                    'name' => 'Reporte de Rendimiento',
                    'description' => 'Métricas de desempeño y eficiencia por cobrador',
                    'filters' => ['start_date', 'end_date', 'cobrador_id', 'manager_id'],
                    'formats' => ['pdf', 'html', 'json', 'excel'],
                ],
                'cash_flow_forecast' => [
                    'name' => 'Reporte de Proyección de Flujo',
                    'description' => 'Proyección de pagos esperados y flujo de efectivo futuro',
                    'filters' => ['start_date', 'end_date', 'cobrador_id', 'group_by'],
                    'formats' => ['pdf', 'html', 'json', 'excel'],
                ],
                'waiting_list' => [
                    'name' => 'Reporte de Lista de Espera',
                    'description' => 'Créditos pendientes de aprobación y entrega',
                    'filters' => ['status', 'cobrador_id'],
                    'formats' => ['pdf', 'html', 'json', 'excel'],
                ],
                'daily_activity' => [
                    'name' => 'Reporte de Actividad Diaria',
                    'description' => 'Resumen de actividades diarias por cobrador',
                    'filters' => ['date', 'cobrador_id'],
                    'formats' => ['pdf', 'html', 'json', 'excel'],
                ],
                'portfolio' => [
                    'name' => 'Reporte de Cartera',
                    'description' => 'Análisis de cartera vigente, vencida y riesgo',
                    'filters' => ['cobrador_id', 'include_completed'],
                    'formats' => ['pdf', 'html', 'json', 'excel'],
                ],
                'commissions' => [
                    'name' => 'Reporte de Comisiones',
                    'description' => 'Cálculo de comisiones por cobrador basado en cobranza',
                    'filters' => ['start_date', 'end_date', 'cobrador_id', 'commission_rate'],
                    'formats' => ['pdf', 'html', 'json', 'excel'],
                ],
            ],
        ]);
    }
}
