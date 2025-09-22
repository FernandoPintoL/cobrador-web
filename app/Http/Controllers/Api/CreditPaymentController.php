<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Credit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CreditPaymentController extends Controller
{
    /**
     * Process a payment for a credit
     */
    public function processPayment(Request $request, Credit $credit): JsonResponse
    {
        try {
            $validated = $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'payment_type' => 'required|string|in:cash,transfer,check',
                'notes' => 'nullable|string|max:500',
            ]);

            // Verificar que el crédito esté activo
            if ($credit->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'El crédito no está activo',
                ], 400);
            }

            // Procesar el pago usando el método del modelo
            $paymentResult = $credit->processPayment($validated['amount']);

            // Crear el registro de pago
            $payment = $credit->payments()->create([
                'cobrador_id' => auth()->id(),
                'amount' => $validated['amount'],
                'status' => 'completed',
                'payment_date' => now(),
                'payment_type' => $validated['payment_type'],
                'notes' => $validated['notes'] ?? null,
            ]);

            // Actualizar el balance del crédito
            $newBalance = $credit->getCurrentBalance();
            $credit->update(['balance' => $newBalance]);

            // Si el crédito está completamente pagado, marcar como completado
            if ($newBalance <= 0) {
                $credit->update(['status' => 'completed']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Pago procesado exitosamente',
                'data' => [
                    'payment' => $payment,
                    'payment_analysis' => $paymentResult,
                    'credit_status' => [
                        'current_balance' => $newBalance,
                        'total_paid' => $credit->getTotalPaidAmount(),
                        'pending_installments' => $credit->getPendingInstallments(),
                        'is_overdue' => $credit->isOverdue(),
                        'overdue_amount' => $credit->getOverdueAmount(),
                    ],
                ],
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de entrada no válidos',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el pago: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get credit details with payment information
     */
    public function getCreditDetails(Credit $credit): JsonResponse
    {
        $credit->load(['createdBy', 'client']);

        return response()->json([
            'success' => true,
            'data' => [
                'payments_history' => $credit->payments()
                    ->with('cobrador')
                    ->orderByRaw('CASE WHEN installment_number IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('installment_number', 'asc')
                    ->orderBy('payment_date', 'asc')
                    ->get(),
                'credit' => $credit,
                'summary' => [
                    'original_amount' => $credit->amount,
                    'interest_rate' => $credit->interest_rate,
                    'installment_amount' => $credit->installment_amount,
                    'total_installments' => $credit->total_installments,
                    'current_balance' => $credit->balance,
                    'total_amount' => $credit->total_amount,
                    'total_paid' => $credit->getTotalPaidAmount(),
                    'completed_installments_count' => (int) $credit->getCompletedInstallmentsCount(),
                    'pending_installments' => max(((int) ($credit->total_installments ?? $credit->calculateTotalInstallments())) - (int) $credit->getCompletedInstallmentsCount(), 0),
                ],
                'payment_schedule' => $credit->getPaymentSchedule(),
            ],
        ]);
    }

    /**
     * Get payment schedule for a credit
     */
    public function getPaymentSchedule(Credit $credit): JsonResponse
    {
        $schedule = $credit->getPaymentSchedule();
        $payments = $credit->payments()->where('status', 'completed')->get();

        // Marcar cuotas como pagadas en el cronograma
        foreach ($schedule as $index => &$installment) {
            $installment['status'] = 'pending';
            $installment['paid_amount'] = 0;
            $installment['payment_date'] = null;

            // Buscar pagos que correspondan a esta cuota
            $installmentNumber = $index + 1;
            $correspondingPayments = $payments->slice($index, 1);

            if ($correspondingPayments->count() > 0) {
                $payment = $correspondingPayments->first();
                $installment['status'] = 'paid';
                $installment['paid_amount'] = $payment->amount;
                $installment['payment_date'] = $payment->payment_date;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'schedule' => $schedule,
                'summary' => [
                    'total_installments' => count($schedule),
                    'paid_installments' => $payments->count(),
                    'pending_installments' => count($schedule) - $payments->count(),
                ],
            ],
        ]);
    }

    /**
     * Get overdue credits
     */
    public function getOverdueCredits(): JsonResponse
    {
        $overdueCredits = Credit::where('status', 'active')
            ->with(['client', 'payments'])
            ->get()
            ->filter(function ($credit) {
                return $credit->isOverdue();
            })
            ->map(function ($credit) {
                return [
                    'credit' => $credit,
                    'client_name' => $credit->client->name,
                    'overdue_amount' => $credit->getOverdueAmount(),
                    'expected_installments' => $credit->getExpectedInstallments(),
                    'paid_installments' => $credit->payments()->where('status', 'completed')->count(),
                    'current_balance' => $credit->getCurrentBalance(),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $overdueCredits,
        ]);
    }
}
