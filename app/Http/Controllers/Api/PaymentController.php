<?php

namespace App\Http\Controllers\Api;

use App\Models\Payment;
use App\Models\Credit;
use App\Models\User;
use Illuminate\Http\Request;

class PaymentController extends BaseController
{
    /**
     * Display a listing of payments.
     */
    public function index(Request $request)
    {
        $payments = Payment::with(['client', 'cobrador', 'credit'])
            ->when($request->client_id, function ($query, $clientId) {
                $query->where('client_id', $clientId);
            })
            ->when($request->cobrador_id, function ($query, $cobradorId) {
                $query->where('cobrador_id', $cobradorId);
            })
            ->when($request->credit_id, function ($query, $creditId) {
                $query->where('credit_id', $creditId);
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->date_from, function ($query, $dateFrom) {
                $query->whereDate('payment_date', '>=', $dateFrom);
            })
            ->when($request->date_to, function ($query, $dateTo) {
                $query->whereDate('payment_date', '<=', $dateTo);
            })
            ->orderBy('payment_date', 'desc')
            ->paginate(15);

        return $this->sendResponse($payments);
    }

    /**
     * Store a newly created payment.
     */
    public function store(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:users,id',
            'cobrador_id' => 'required|exists:users,id',
            'credit_id' => 'required|exists:credits,id',
            'amount' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,transfer,card,mobile_payment',
            'location' => 'nullable|array',
            'status' => 'in:pending,completed,failed,cancelled',
            'transaction_id' => 'nullable|string',
            'installment_number' => 'required|integer|min:1',
        ]);

        $payment = Payment::create([
            'client_id' => $request->client_id,
            'cobrador_id' => $request->cobrador_id,
            'credit_id' => $request->credit_id,
            'amount' => $request->amount,
            'payment_date' => $request->payment_date,
            'payment_method' => $request->payment_method,
            'location' => $request->location,
            'status' => $request->status ?? 'pending',
            'transaction_id' => $request->transaction_id,
            'installment_number' => $request->installment_number,
        ]);

        // Update credit balance if payment is completed
        if ($payment->status === 'completed') {
            $credit = $payment->credit;
            $credit->balance = max(0, $credit->balance - $payment->amount);
            $credit->save();
        }

        $payment->load(['client', 'cobrador', 'credit']);

        return $this->sendResponse($payment, 'Pago registrado exitosamente');
    }

    /**
     * Display the specified payment.
     */
    public function show(Payment $payment)
    {
        $payment->load(['client', 'cobrador', 'credit', 'notifications']);
        return $this->sendResponse($payment);
    }

    /**
     * Update the specified payment.
     */
    public function update(Request $request, Payment $payment)
    {
        $request->validate([
            'client_id' => 'required|exists:users,id',
            'cobrador_id' => 'required|exists:users,id',
            'credit_id' => 'required|exists:credits,id',
            'amount' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,transfer,card,mobile_payment',
            'location' => 'nullable|array',
            'status' => 'in:pending,completed,failed,cancelled',
            'transaction_id' => 'nullable|string',
            'installment_number' => 'required|integer|min:1',
        ]);

        $oldAmount = $payment->amount;
        $oldStatus = $payment->status;

        $payment->update([
            'client_id' => $request->client_id,
            'cobrador_id' => $request->cobrador_id,
            'credit_id' => $request->credit_id,
            'amount' => $request->amount,
            'payment_date' => $request->payment_date,
            'payment_method' => $request->payment_method,
            'location' => $request->location,
            'status' => $request->status,
            'transaction_id' => $request->transaction_id,
            'installment_number' => $request->installment_number,
        ]);

        // Update credit balance if status changed
        if ($oldStatus !== $payment->status || $oldAmount !== $payment->amount) {
            $credit = $payment->credit;
            if ($payment->status === 'completed') {
                $credit->balance = max(0, $credit->balance - ($payment->amount - $oldAmount));
            } elseif ($oldStatus === 'completed' && $payment->status !== 'completed') {
                $credit->balance += $oldAmount;
            }
            $credit->save();
        }

        $payment->load(['client', 'cobrador', 'credit']);

        return $this->sendResponse($payment, 'Pago actualizado exitosamente');
    }

    /**
     * Remove the specified payment.
     */
    public function destroy(Payment $payment)
    {
        // Restore credit balance if payment was completed
        if ($payment->status === 'completed') {
            $credit = $payment->credit;
            $credit->balance += $payment->amount;
            $credit->save();
        }

        $payment->delete();
        return $this->sendResponse([], 'Pago eliminado exitosamente');
    }

    /**
     * Get payments by client.
     */
    public function getByClient(User $client)
    {
        $payments = $client->paymentsAsClient()->with(['cobrador', 'credit'])->get();
        return $this->sendResponse($payments);
    }

    /**
     * Get payments by cobrador.
     */
    public function getByCobrador(User $cobrador)
    {
        $payments = $cobrador->paymentsAsCobrador()->with(['client', 'credit'])->get();
        return $this->sendResponse($payments);
    }

    /**
     * Get payments by credit.
     */
    public function getByCredit(Credit $credit)
    {
        $payments = $credit->payments()->with(['client', 'cobrador'])->get();
        return $this->sendResponse($payments);
    }
} 