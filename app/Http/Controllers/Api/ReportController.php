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
            'status' => 'nullable|in:active,completed,pending_approval,waiting_delivery',
            'cobrador_id' => 'nullable|exists:users,id',
            'client_id' => 'nullable|exists:users,id',
            'format' => 'nullable|in:pdf,html,json,excel',
        ]);

        $query = Credit::with(['client', 'createdBy', 'payments']);

        // Apply filters
        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->cobrador_id) {
            $query->where('created_by', $request->cobrador_id);
        }

        if ($request->client_id) {
            $query->where('client_id', $request->client_id);
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
            'format' => 'nullable|in:pdf,html,json,excel',
        ]);

        $query = CashBalance::with(['cobrador']);

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

        // Calculate summary
        $summary = [
            'total_records' => $balances->count(),
            'total_initial' => $balances->sum('initial_amount'),
            'total_collected' => $balances->sum('collected_amount'),
            'total_lent' => $balances->sum('lent_amount'),
            'total_final' => $balances->sum('final_amount'),
            'average_difference' => $balances->avg(function ($balance) {
                return $balance->final_amount - ($balance->initial_amount + $balance->collected_amount - $balance->lent_amount);
            }),
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
                    'filters' => ['status', 'cobrador_id', 'client_id'],
                    'formats' => ['pdf', 'html', 'json', 'excel'],
                ],
                /*'users' => [
                    'name' => 'Reporte de Usuarios',
                    'description' => 'Lista de usuarios con roles y categorías',
                    'filters' => ['role', 'client_category'],
                    'formats' => ['pdf', 'html', 'json', 'excel'],
                ],*/
                'balances' => [
                    'name' => 'Reporte de Balances',
                    'description' => 'Balances de efectivo por cobrador',
                    'filters' => ['start_date', 'end_date', 'cobrador_id'],
                    'formats' => ['pdf', 'html', 'json', 'excel'],
                ],
            ],
        ]);
    }
}
