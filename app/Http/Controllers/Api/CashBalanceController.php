<?php

namespace App\Http\Controllers\Api;

use App\Models\CashBalance;
use App\Models\User;
use Illuminate\Http\Request;

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
        ]);

        $cashBalance = CashBalance::create([
            'cobrador_id' => $request->cobrador_id,
            'date' => $request->date,
            'initial_amount' => $request->initial_amount,
            'collected_amount' => $request->collected_amount,
            'lent_amount' => $request->lent_amount,
            'final_amount' => $request->final_amount,
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
        ]);

        $cashBalance->update([
            'cobrador_id' => $request->cobrador_id,
            'date' => $request->date,
            'initial_amount' => $request->initial_amount,
            'collected_amount' => $request->collected_amount,
            'lent_amount' => $request->lent_amount,
            'final_amount' => $request->final_amount,
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
            ]
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

        // Calculate collected amount from payments
        $collectedAmount = \App\Models\Payment::where('cobrador_id', $request->cobrador_id)
            ->whereDate('payment_date', $request->date)
            ->where('status', 'paid')
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
} 