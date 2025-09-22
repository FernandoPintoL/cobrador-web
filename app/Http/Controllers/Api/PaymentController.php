<?php

namespace App\Http\Controllers\Api;

use App\Models\CashBalance;
use App\Models\Credit;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends BaseController
{
    /**
     * Display a listing of payments.
     */
    public function index(Request $request)
    {
        $query = Payment::with(['cobrador', 'credit.client', 'receivedBy']);

        $currentUser = Auth::user();

        // Si el usuario es cobrador, solo mostrar sus pagos
        if ($currentUser && $currentUser->hasRole('cobrador')) {
            $query->where('received_by', $currentUser->id);
        } elseif ($currentUser && $currentUser->hasRole('manager')) {
            // Si es manager, mostrar pagos de sus cobradores
            $cobradorIds = User::role('cobrador')->where('assigned_manager_id', $currentUser->id)->pluck('id');
            $query->whereIn('received_by', $cobradorIds);
        }

        // Filtros adicionales
        $query->when($request->credit_id, function ($query, $creditId) {
            $query->where('credit_id', $creditId);
        })
            ->when($request->received_by, function ($query, $receivedBy) {
                $query->where('received_by', $receivedBy);
            })
            ->when($request->payment_method, function ($query, $method) {
                $query->where('payment_method', $method);
            })
            ->when($request->date_from, function ($query, $dateFrom) {
                $query->whereDate('payment_date', '>=', $dateFrom);
            })
            ->when($request->date_to, function ($query, $dateTo) {
                $query->whereDate('payment_date', '<=', $dateTo);
            })
            ->when($request->amount_min, function ($query, $amount) {
                $query->where('amount', '>=', $amount);
            })
            ->when($request->amount_max, function ($query, $amount) {
                $query->where('amount', '<=', $amount);
            });

        $payments = $query->orderBy('payment_date', 'desc')->paginate(15);

        return $this->sendResponse($payments);
    }

    /**
     * Store a newly created payment.
     */
    public function store(Request $request)
    {
        $request->validate([
            'credit_id' => 'required|exists:credits,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,transfer,card,mobile_payment',
            'payment_date' => 'nullable|date',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'installment_number' => 'nullable|integer|min:1',
        ]);

        $currentUser = Auth::user();
        $credit = Credit::with(['client'])->findOrFail($request->credit_id);

        // Determinar la fecha del pago (por defecto hoy)
        $paymentDate = $request->payment_date ? $request->payment_date : now()->toDateString();

        // Si el usuario que registra es un cobrador, exigir que exista una caja abierta para ese cobrador en la fecha del pago
        $cashBalance = null;
        if ($currentUser->hasRole('cobrador')) {
            $cashBalance = CashBalance::where('cobrador_id', $currentUser->id)
                ->whereDate('date', $paymentDate)
                ->where('status', 'open')
                ->first();

            if (! $cashBalance) {
                return $this->sendError('Caja no abierta', 'No existe una caja abierta para la fecha del pago. Abre la caja antes de registrar pagos.', 400);
            }
        }

        // Verificar permisos
        if ($currentUser->hasRole('cobrador')) {
            // El cobrador solo puede registrar pagos para sus clientes asignados
            if ($credit->client->assigned_cobrador_id !== $currentUser->id) {
                return $this->sendError('No autorizado', 'No puedes registrar pagos para clientes que no tienes asignados', 403);
            }
        }

        // Verificar que el crédito esté activo
        if ($credit->status !== 'active') {
            return $this->sendError('Crédito inválido', 'Solo se pueden registrar pagos para créditos activos', 400);
        }

        // Verificar que el monto no exceda el balance pendiente
        if ($request->amount > $credit->balance) {
            return $this->sendError('Monto inválido', 'El monto del pago no puede exceder el balance pendiente del crédito', 400);
        }

        // Calcular número de cuotas a cubrir y el número de cuota correcto
        $dailyInstallment = $credit->installment_amount;
        if ($dailyInstallment <= 0) {
            return $this->sendError('Error de configuración', 'El monto de cuota diaria no está configurado correctamente', 400);
        }

        $payments = [];
        $totalPaid = 0.0;

        // Construir un mapa de montos pagados por número de cuota existente
        $paidByInstallment = $credit->payments()
            ->whereNotNull('installment_number')
            ->selectRaw('installment_number, SUM(amount) as total')
            ->groupBy('installment_number')
            ->pluck('total', 'installment_number');

        // Determinar la primera cuota a pagar
        $totalInstallments = (int) ($credit->total_installments ?? 0);
        if ($totalInstallments <= 0) {
            // Si no está definido, estimar por total_amount / installment_amount
            $totalInstallments = (int) max(1, floor(($credit->total_amount ?? 0) / $dailyInstallment));
        }

        $determineStartingInstallment = function () use ($request, $paidByInstallment, $dailyInstallment, $totalInstallments) {
            if ($request->filled('installment_number')) {
                $requested = (int) $request->installment_number;
                if ($requested < 1) {
                    $requested = 1;
                }
                if ($requested > $totalInstallments) {
                    return $totalInstallments + 1; // Forzar error luego
                }

                return $requested;
            }

            for ($i = 1; $i <= $totalInstallments; $i++) {
                $sum = (float) ($paidByInstallment[$i] ?? 0);
                if ($sum < $dailyInstallment) {
                    return $i;
                }
            }

            return $totalInstallments + 1; // No hay cuotas pendientes
        };

        $currentInstallment = $determineStartingInstallment();
        if ($currentInstallment > $totalInstallments) {
            return $this->sendError('Sin cuotas pendientes', 'No hay cuotas pendientes para este crédito', 400);
        }

        $remainingAmountToAllocate = (float) $request->amount;

        while ($remainingAmountToAllocate > 0 && $currentInstallment <= $totalInstallments) {
            $alreadyPaid = (float) ($paidByInstallment[$currentInstallment] ?? 0);
            $remainingForInstallment = max(0.0, (float) $dailyInstallment - $alreadyPaid);

            if ($remainingForInstallment <= 0) {
                // Esta cuota ya está completa, pasar a la siguiente
                $currentInstallment++;

                continue;
            }

            $toPay = (float) min($remainingAmountToAllocate, $remainingForInstallment);

            $payment = Payment::create([
                'credit_id' => $request->credit_id,
                'client_id' => $credit->client->id,
                'cobrador_id' => $credit->createdBy->id,
                'cash_balance_id' => $cashBalance ? $cashBalance->id : null,
                'amount' => $toPay,
                'payment_method' => $request->payment_method,
                'payment_date' => $request->payment_date ?: now()->toDateString(),
                'received_by' => $currentUser->id,
                'installment_number' => $currentInstallment,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'status' => $toPay < $remainingForInstallment ? 'partial' : 'completed',
            ]);

            $payments[] = $payment;
            $totalPaid += $toPay;
            $paidByInstallment[$currentInstallment] = $alreadyPaid + $toPay;
            $remainingAmountToAllocate -= $toPay;

            if ($remainingAmountToAllocate > 0) {
                $currentInstallment++;
            }
        }

        if ($remainingAmountToAllocate > 0) {
            // El monto excede el total de cuotas pendientes
            return $this->sendError('Monto excede cuotas', 'El monto supera el total pendiente de cuotas para este crédito', 400);
        }

        // NOTE: la actualización del balance del crédito y recálculo de categoría
        // se realiza en el evento `created` del modelo `Payment`.
        // Para evitar descontar doblemente, no modificamos el balance aquí.

        // Cargar relaciones para todos los pagos creados
        foreach ($payments as $p) {
            $p->load(['credit.client', 'receivedBy']);
        }

        // Disparar evento de pago recibido para el primer pago si es necesario
        //        event(new PaymentReceived($payments[0]));

        return $this->sendResponse([
            'payments' => $payments,
            'total_paid' => $totalPaid,
        ], 'Pagos registrados exitosamente');
    }

    /**
     * Display the specified payment.
     */
    public function show(Payment $payment)
    {
        $currentUser = Auth::user();

        // Verificar permisos para ver el pago
        if ($currentUser->hasRole('cobrador')) {
            if ($payment->received_by !== $currentUser->id) {
                return $this->sendError('No autorizado', 'No tienes acceso a este pago', 403);
            }
        }

        $payment->load(['credit.client', 'receivedBy']);

        return $this->sendResponse($payment);
    }

    /**
     * Update the specified payment.
     */
    public function update(Request $request, Payment $payment)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,transfer,card,mobile_payment',
            'payment_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        $currentUser = Auth::user();

        // Verificar permisos para actualizar el pago
        if ($currentUser->hasRole('cobrador')) {
            if ($payment->received_by !== $currentUser->id) {
                return $this->sendError('No autorizado', 'No puedes actualizar pagos que no registraste', 403);
            }
        }

        // Guardar el monto anterior para ajustar el balance del crédito
        $oldAmount = $payment->amount;
        $credit = $payment->credit;

        $payment->update([
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'payment_date' => $request->payment_date ?: now()->toDateString(),
            'notes' => $request->notes,
        ]);

        // Ajustar el balance del crédito
        $difference = $request->amount - $oldAmount;
        $credit->balance -= $difference;

        // Verificar el estado del crédito
        if ($credit->balance <= 0 && $credit->status !== 'completed') {
            $credit->status = 'completed';
            $credit->completed_at = now();
        } elseif ($credit->balance > 0 && $credit->status === 'completed') {
            $credit->status = 'active';
            $credit->completed_at = null;
        }

        $credit->save();

        $payment->load(['credit.client', 'receivedBy']);

        return $this->sendResponse($payment, 'Pago actualizado exitosamente');
    }

    /**
     * Remove the specified payment.
     */
    public function destroy(Payment $payment)
    {
        $currentUser = Auth::user();

        // Solo admins pueden eliminar pagos
        if (! $currentUser->hasRole('admin')) {
            return $this->sendError('No autorizado', 'Solo administradores pueden eliminar pagos', 403);
        }

        // Ajustar el balance del crédito
        $credit = $payment->credit;
        $credit->balance += $payment->amount;

        // Ajustar el estado del crédito si era completado
        if ($credit->status === 'completed') {
            $credit->status = 'active';
            $credit->completed_at = null;
        }

        $credit->save();

        $payment->delete();

        return $this->sendResponse([], 'Pago eliminado exitosamente');
    }

    /**
     * Get payments by credit.
     */
    public function getByCredit(Credit $credit)
    {
        $currentUser = Auth::user();

        // Verificar permisos para ver los pagos del crédito
        if ($currentUser->hasRole('cobrador')) {
            if ($credit->client->assigned_cobrador_id !== $currentUser->id) {
                return $this->sendError('No autorizado', 'No tienes acceso a los pagos de este crédito', 403);
            }
        }

        $payments = $credit->payments()->with(['receivedBy'])->orderBy('payment_date', 'desc')->get();

        return $this->sendResponse($payments, 'Pagos del crédito obtenidos exitosamente');
    }

    /**
     * Get payments by cobrador.
     */
    public function getByCobrador(Request $request, User $cobrador)
    {
        $currentUser = Auth::user();

        // Solo admins, managers y el propio cobrador pueden ver estos pagos
        if ($currentUser->hasRole('cobrador') && $currentUser->id !== $cobrador->id) {
            return $this->sendError('No autorizado', 'Solo puedes ver tus propios pagos', 403);
        }

        if ($currentUser->hasRole('manager')) {
            // Verificar que el cobrador esté asignado al manager
            if ($cobrador->assigned_manager_id !== $currentUser->id) {
                return $this->sendError('No autorizado', 'El cobrador no está asignado a tu gestión', 403);
            }
        }

        // Verificar que el usuario sea un cobrador
        if (! $cobrador->hasRole('cobrador')) {
            return $this->sendError('Usuario no válido', 'El usuario especificado no es un cobrador', 400);
        }

        $query = Payment::with(['credit.client', 'receivedBy'])
            ->where('received_by', $cobrador->id);

        // Filtros adicionales
        $query->when($request->date_from, function ($query, $dateFrom) {
            $query->whereDate('payment_date', '>=', $dateFrom);
        })
            ->when($request->date_to, function ($query, $dateTo) {
                $query->whereDate('payment_date', '<=', $dateTo);
            })
            ->when($request->payment_method, function ($query, $method) {
                $query->where('payment_method', $method);
            });

        $payments = $query->orderBy('payment_date', 'desc')->paginate($request->get('per_page', 15));

        return $this->sendResponse($payments, "Pagos del cobrador {$cobrador->name} obtenidos exitosamente");
    }

    /**
     * Get payment statistics for a cobrador.
     */
    public function getCobradorStats(Request $request, User $cobrador)
    {
        $currentUser = Auth::user();

        // Los cobradores solo pueden ver sus propias estadísticas
        if ($currentUser->hasRole('cobrador') && $currentUser->id !== $cobrador->id) {
            return $this->sendError('No autorizado', 'Solo puedes ver tus propias estadísticas', 403);
        }

        // Verificar que el usuario sea un cobrador
        if (! $cobrador->hasRole('cobrador')) {
            return $this->sendError('Usuario no válido', 'El usuario especificado no es un cobrador', 400);
        }

        $query = Payment::where('received_by', $cobrador->id);

        // Filtros de fecha
        if ($request->date_from) {
            $query->whereDate('payment_date', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('payment_date', '<=', $request->date_to);
        }

        $stats = [
            'total_payments' => $query->count(),
            'total_amount' => $query->sum('amount'),
            'average_payment' => $query->avg('amount'),
            'payments_by_method' => Payment::where('received_by', $cobrador->id)
                ->when($request->date_from, function ($q, $date) {
                    $q->whereDate('payment_date', '>=', $date);
                })
                ->when($request->date_to, function ($q, $date) {
                    $q->whereDate('payment_date', '<=', $date);
                })
                ->selectRaw('payment_method, COUNT(*) as count, SUM(amount) as total')
                ->groupBy('payment_method')
                ->get(),
        ];

        return $this->sendResponse($stats, "Estadísticas de pagos del cobrador {$cobrador->name} obtenidas exitosamente");
    }

    /**
     * Get recent payments for dashboard.
     */
    public function getRecent(Request $request)
    {
        $currentUser = Auth::user();
        $limit = $request->get('limit', 10);

        $query = Payment::with(['credit.client', 'receivedBy']);

        // Filtrar según el rol del usuario
        if ($currentUser->hasRole('cobrador')) {
            $query->where('received_by', $currentUser->id);
        } elseif ($currentUser->hasRole('manager')) {
            $cobradorIds = User::role('cobrador')->where('assigned_manager_id', $currentUser->id)->pluck('id');
            $query->whereIn('received_by', $cobradorIds);
        }

        $payments = $query->orderBy('payment_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $this->sendResponse($payments, 'Pagos recientes obtenidos exitosamente');
    }

    /**
     * Get payments summary for today.
     */
    public function getTodaySummary()
    {
        $currentUser = Auth::user();
        $today = now()->toDateString();

        $query = Payment::whereDate('payment_date', $today);

        // Filtrar según el rol del usuario
        if ($currentUser->hasRole('cobrador')) {
            $query->where('received_by', $currentUser->id);
        } elseif ($currentUser->hasRole('manager')) {
            $cobradorIds = User::role('cobrador')->where('assigned_manager_id', $currentUser->id)->pluck('id');
            $query->whereIn('received_by', $cobradorIds);
        }

        $summary = [
            'total_payments' => $query->count(),
            'total_amount' => $query->sum('amount'),
            'payments_by_method' => Payment::whereDate('payment_date', $today)
                ->when($currentUser->hasRole('cobrador'), function ($q) use ($currentUser) {
                    $q->where('received_by', $currentUser->id);
                })
                ->when($currentUser->hasRole('manager'), function ($q) use ($currentUser) {
                    $cobradorIds = User::role('cobrador')->where('assigned_manager_id', $currentUser->id)->pluck('id');
                    $q->whereIn('received_by', $cobradorIds);
                })
                ->selectRaw('payment_method, COUNT(*) as count, SUM(amount) as total')
                ->groupBy('payment_method')
                ->get(),
        ];

        return $this->sendResponse($summary, 'Resumen de pagos del día obtenido exitosamente');
    }
}
