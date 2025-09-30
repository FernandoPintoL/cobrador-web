<?php

namespace App\Http\Controllers\Api;

use App\Models\CashBalance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CashBalanceController extends BaseController
{
    /**
     * Display a listing of cash balances.
     */
    public function index(Request $request)
    {
        $cashBalances = CashBalance::with(['cobrador'])
            ->when($request->cobrador_id, function ($query, $cobradorId) {
                $query->where('cobrador_id', $cobradorId);
            })
            ->when($request->date_from, function ($query, $dateFrom) {
                $query->whereDate('date', '>=', $dateFrom);
            })
            ->when($request->date_to, function ($query, $dateTo) {
                $query->whereDate('date', '<=', $dateTo);
            })
            ->orderBy('date', 'desc')
            ->paginate(15);

        return $this->sendResponse($cashBalances);
    }

    /**
     * Store a newly created cash balance.
     */
    public function store(Request $request)
    {
        $request->validate([
            'cobrador_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'initial_amount' => 'required|numeric|min:0',
            'collected_amount' => 'required|numeric|min:0',
            'lent_amount' => 'required|numeric|min:0',
            'final_amount' => 'required|numeric|min:0',
            'status' => 'nullable|in:open,closed,reconciled',
        ]);

        // Evitar duplicados por cobrador + fecha
        $exists = CashBalance::where('cobrador_id', $request->cobrador_id)
            ->where('date', $request->date)
            ->first();

        if ($exists) {
            return $this->sendError('Duplicado', 'Ya existe un balance para este cobrador en la fecha indicada', 400);
        }

        $cashBalance = CashBalance::create([
            'cobrador_id' => $request->cobrador_id,
            'date' => $request->date,
            'initial_amount' => $request->initial_amount,
            'collected_amount' => $request->collected_amount,
            'lent_amount' => $request->lent_amount,
            'final_amount' => $request->final_amount,
            'status' => $request->status ?? 'open',
        ]);

        $cashBalance->load(['cobrador']);

        return $this->sendResponse($cashBalance, 'Balance de efectivo creado exitosamente');
    }

    /**
     * Display the specified cash balance.
     */
    public function show(CashBalance $cashBalance)
    {
        $cashBalance->load(['cobrador']);

        return $this->sendResponse($cashBalance);
    }

    /**
     * Update the specified cash balance.
     */
    public function update(Request $request, CashBalance $cashBalance)
    {
        $request->validate([
            'cobrador_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'initial_amount' => 'required|numeric|min:0',
            'collected_amount' => 'required|numeric|min:0',
            'lent_amount' => 'required|numeric|min:0',
            'final_amount' => 'required|numeric|min:0',
            'status' => 'nullable|in:open,closed,reconciled',
        ]);

        $cashBalance->update([
            'cobrador_id' => $request->cobrador_id,
            'date' => $request->date,
            'initial_amount' => $request->initial_amount,
            'collected_amount' => $request->collected_amount,
            'lent_amount' => $request->lent_amount,
            'final_amount' => $request->final_amount,
            'status' => $request->status ?? $cashBalance->status,
        ]);

        $cashBalance->load(['cobrador']);

        return $this->sendResponse($cashBalance, 'Balance de efectivo actualizado exitosamente');
    }

    /**
     * Remove the specified cash balance.
     */
    public function destroy(CashBalance $cashBalance)
    {
        $cashBalance->delete();

        return $this->sendResponse([], 'Balance de efectivo eliminado exitosamente');
    }

    /**
     * Get cash balances by cobrador.
     */
    public function getByCobrador(User $cobrador)
    {
        $cashBalances = $cobrador->cashBalances()->orderBy('date', 'desc')->get();

        return $this->sendResponse($cashBalances);
    }

    /**
     * Get cash balance summary for a cobrador.
     */
    public function getSummary(User $cobrador)
    {
        $summary = $cobrador->cashBalances()
            ->selectRaw('
                SUM(initial_amount) as total_initial,
                SUM(collected_amount) as total_collected,
                SUM(lent_amount) as total_lent,
                SUM(final_amount) as total_final
            ')
            ->first();

        return $this->sendResponse($summary);
    }

    /**
     * Get cash balance with related payments and credits for detailed reconciliation.
     */
    public function getDetailedBalance(CashBalance $cashBalance)
    {
        $cashBalance->load(['cobrador']);

        // Get payments for this balance date and cobrador
        $payments = \App\Models\Payment::where('cobrador_id', $cashBalance->cobrador_id)
            ->whereDate('payment_date', $cashBalance->date)
            ->with(['client', 'credit'])
            ->get();

        // Get credits created on this date by this cobrador
        $credits = \App\Models\Credit::where('created_by', $cashBalance->cobrador_id)
            ->whereDate('created_at', $cashBalance->date)
            ->with(['client'])
            ->get();

        $data = [
            'cash_balance' => $cashBalance,
            'payments' => $payments,
            'credits' => $credits,
            'reconciliation' => [
                'expected_final' => $cashBalance->initial_amount + $cashBalance->collected_amount - $cashBalance->lent_amount,
                'actual_final' => $cashBalance->final_amount,
                'difference' => $cashBalance->final_amount - ($cashBalance->initial_amount + $cashBalance->collected_amount - $cashBalance->lent_amount),
                'is_balanced' => abs($cashBalance->final_amount - ($cashBalance->initial_amount + $cashBalance->collected_amount - $cashBalance->lent_amount)) < 0.01,
            ],
        ];

        return $this->sendResponse($data, 'Balance detallado obtenido exitosamente');
    }

    /**
     * Create a new cash balance with automatic calculation of collected and lent amounts.
     */
    public function createWithAutoCalculation(Request $request)
    {
        $request->validate([
            'cobrador_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'initial_amount' => 'required|numeric|min:0',
            'final_amount' => 'required|numeric|min:0',
        ]);

        // Calculate collected amount from payments (use 'completed' for consistency)
        $collectedAmount = \App\Models\Payment::where('cobrador_id', $request->cobrador_id)
            ->whereDate('payment_date', $request->date)
            ->where('status', 'completed')
            ->sum('amount');

        // Calculate lent amount from credits created on this date
        $lentAmount = \App\Models\Credit::where('created_by', $request->cobrador_id)
            ->whereDate('created_at', $request->date)
            ->sum('amount');

        $cashBalance = CashBalance::create([
            'cobrador_id' => $request->cobrador_id,
            'date' => $request->date,
            'initial_amount' => $request->initial_amount,
            'collected_amount' => $collectedAmount,
            'lent_amount' => $lentAmount,
            'final_amount' => $request->final_amount,
        ]);

        $cashBalance->load(['cobrador']);

        return $this->sendResponse($cashBalance, 'Balance de efectivo creado con cálculo automático');
    }

    /**
     * Close or reconcile a cash balance explicitly.
     * Allows updating collected/lent/final amounts and setting status to closed or reconciled.
     */
    public function close(Request $request, CashBalance $cashBalance)
    {
        $request->validate([
            'final_amount' => 'required|numeric|min:0',
            'collected_amount' => 'nullable|numeric|min:0',
            'lent_amount' => 'nullable|numeric|min:0',
            'status' => 'required|in:closed,reconciled',
        ]);

        // Update fields (keep existing when not provided)
        $cashBalance->update([
            'collected_amount' => $request->has('collected_amount') ? (float) $request->collected_amount : $cashBalance->collected_amount,
            'lent_amount' => $request->has('lent_amount') ? (float) $request->lent_amount : $cashBalance->lent_amount,
            'final_amount' => (float) $request->final_amount,
            'status' => $request->status,
        ]);

        $cashBalance->load(['cobrador']);

        return $this->sendResponse($cashBalance, 'Caja actualizada y cerrada exitosamente');
    }

    /**
     * Idempotent endpoint to open a cash balance (caja) for a cobrador and date.
     * If an open cash balance already exists for the cobrador+date, it is returned.
     * If not, a new one is created with provided initial_amount (or 0).
     */
    public function open(Request $request)
    {
        $request->validate([
            'cobrador_id' => 'nullable|exists:users,id',
            'date' => 'nullable|date',
            'initial_amount' => 'nullable|numeric|min:0',
        ]);

        $currentUser = Auth::user();

        // Determine cobrador for whom to open the box
        if ($currentUser instanceof \App\Models\User && $currentUser->hasRole('cobrador')) {
            $cobradorId = $currentUser->id;
        } else {
            // admins or managers may open for any cobrador when providing cobrador_id
            $cobradorId = $request->cobrador_id ?? null;
            if (! $cobradorId) {
                return $this->sendError('cobrador_id requerido', 'Debes especificar cobrador_id cuando no eres cobrador', 400);
            }
        }

        $date = $request->date ?? now()->toDateString();

        // If an open cash balance exists, return it (idempotent)
        $existing = CashBalance::where('cobrador_id', $cobradorId)
            ->where('date', $date)
            ->where('status', 'open')
            ->first();

        if ($existing) {
            $existing->load(['cobrador']);

            return $this->sendResponse($existing, 'Caja ya abierta para esta fecha');
        }

        // Create new open cash balance
        $initial = $request->initial_amount ?? 0;

        $cashBalance = CashBalance::create([
            'cobrador_id' => $cobradorId,
            'date' => $date,
            'initial_amount' => $initial,
            'collected_amount' => 0,
            'lent_amount' => 0,
            'final_amount' => $initial,
            'status' => 'open',
        ]);

        $cashBalance->load(['cobrador']);

        return $this->sendResponse($cashBalance, 'Caja abierta exitosamente');
    }
}
