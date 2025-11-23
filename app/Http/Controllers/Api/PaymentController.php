<?php

namespace App\Http\Controllers\Api;

use App\Events\PaymentCreated;
use App\Models\CashBalance;
use App\Models\Credit;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        // Si el usuario que registra es un cobrador, buscar o crear una caja virtual automáticamente
        $cashBalance = null;
        $cashBalanceWasAutoCreated = false;
        if ($currentUser->hasRole('cobrador')) {
            $cashBalance = CashBalance::where('cobrador_id', $currentUser->id)
                ->whereDate('date', $paymentDate)
                ->where('status', 'open')
                ->first();

            // Si no existe caja abierta, crear una caja virtual automáticamente
            if (! $cashBalance) {
                $cashBalance = CashBalance::create([
                    'cobrador_id' => $currentUser->id,
                    'date' => $paymentDate,
                    'initial_amount' => 0,
                    'collected_amount' => 0,
                    'lent_amount' => 0,
                    'final_amount' => 0,
                    'status' => 'open',
                    'requires_reconciliation' => true,
                    'closure_notes' => 'Caja creada automáticamente al registrar pago sin caja abierta',
                ]);

                $cashBalanceWasAutoCreated = true;

                // Disparar evento de caja auto-creada
                $manager = $currentUser->assignedManager;
                event(new \App\Events\CashBalanceAutoCreated($cashBalance, $currentUser, $manager, 'payment'));

                Log::info('Virtual cash balance auto-created for payment', [
                    'cash_balance_id' => $cashBalance->id,
                    'cobrador_id' => $currentUser->id,
                    'date' => $paymentDate,
                ]);
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

        // Obtener el monto total acumulado hasta el momento para este crédito
        $totalAccumulated = $credit->payments()->sum('amount');

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
            // Primero encontrar la primera cuota incompleta
            $firstIncompleteInstallment = null;
            for ($i = 1; $i <= $totalInstallments; $i++) {
                $sum = (float) ($paidByInstallment[$i] ?? 0);
                if ($sum < $dailyInstallment) {
                    $firstIncompleteInstallment = $i;
                    break;
                }
            }

            // Si no hay cuotas incompletas, retornar indicador de fin
            if ($firstIncompleteInstallment === null) {
                return $totalInstallments + 1;
            }

            // Si se especificó un installment_number, validar que no salte cuotas incompletas
            if ($request->filled('installment_number')) {
                $requested = (int) $request->installment_number;
                if ($requested < 1) {
                    $requested = 1;
                }
                if ($requested > $totalInstallments) {
                    return $totalInstallments + 1; // Forzar error luego
                }

                // VALIDACIÓN CRÍTICA: No permitir saltar cuotas incompletas
                if ($requested > $firstIncompleteInstallment) {
                    throw new \Exception(
                        "No se puede pagar la cuota #$requested porque hay cuotas anteriores incompletas. " .
                        "La primera cuota incompleta es la #$firstIncompleteInstallment. " .
                        "Por favor, paga las cuotas en orden secuencial."
                    );
                }

                return $requested;
            }

            // Pago normal sin installment_number específico: usar la primera incompleta
            return $firstIncompleteInstallment;
        };

        try {
            $currentInstallment = $determineStartingInstallment();
        } catch (\Exception $e) {
            return $this->sendError('Pago fuera de orden', $e->getMessage(), 422);
        }

        if ($currentInstallment > $totalInstallments) {
            return $this->sendError('Sin cuotas pendientes', 'No hay cuotas pendientes para este crédito', 400);
        }

        $remainingAmountToAllocate = (float) $request->amount;

        // Envolver la creación de pagos y actualización de balances en una transacción
        DB::transaction(function () use (
            &$payments,
            &$totalPaid,
            &$remainingAmountToAllocate,
            &$currentInstallment,
            &$totalInstallments,
            &$paidByInstallment,
            &$totalAccumulated,
            &$cashBalance,
            $request,
            $credit,
            $currentUser,
            $dailyInstallment
        ) {
            while ($remainingAmountToAllocate > 0 && $currentInstallment <= $totalInstallments) {
                $alreadyPaid = (float) ($paidByInstallment[$currentInstallment] ?? 0);
                $remainingForInstallment = max(0.0, (float) $dailyInstallment - $alreadyPaid);

                if ($remainingForInstallment <= 0) {
                    // Esta cuota ya está completa, pasar a la siguiente
                    $currentInstallment++;

                    continue;
                }

                $toPay = (float) min($remainingAmountToAllocate, $remainingForInstallment);

                // Actualizar el monto acumulado
                $totalAccumulated += $toPay;

                $newTotalForInstallment = $alreadyPaid + $toPay;
                $isCompletingInstallment = $newTotalForInstallment >= $dailyInstallment;

                // Buscar si ya existe un pago parcial para esta cuota
                $existingPartialPayment = null;
                if ($alreadyPaid > 0) {
                    $existingPartialPayment = Payment::where('credit_id', $credit->id)
                        ->where('installment_number', $currentInstallment)
                        ->where('status', 'partial')
                        ->first();
                }

                if ($existingPartialPayment) {
                    // ACTUALIZAR el pago parcial existente en lugar de crear uno nuevo
                    // Sumar el nuevo monto al existente
                    $oldAmount = (float) $existingPartialPayment->amount;
                    $existingPartialPayment->amount += $toPay; // Sumar, no reemplazar
                    $existingPartialPayment->accumulated_amount += $toPay; // Sumar al acumulado
                    $existingPartialPayment->payment_date = $request->payment_date ?: now()->toDateString();
                    $existingPartialPayment->received_by = $currentUser->id;

                    // Actualizar ubicación solo si viene en el request
                    if ($request->latitude && $request->longitude) {
                        $existingPartialPayment->latitude = $request->latitude;
                        $existingPartialPayment->longitude = $request->longitude;
                    }

                    $existingPartialPayment->status = $isCompletingInstallment ? 'completed' : 'partial';

                    // Actualizar cash_balance_id si existe
                    if ($cashBalance) {
                        $existingPartialPayment->cash_balance_id = $cashBalance->id;
                    }

                    $existingPartialPayment->save();

                    $payment = $existingPartialPayment;
                } else {
                    // CREAR nuevo pago (no existía uno parcial)
                    $payment = Payment::create([
                        'credit_id' => $request->credit_id,
                        'client_id' => $credit->client->id,
                        'cobrador_id' => $credit->createdBy->id,
                        'cash_balance_id' => $cashBalance ? $cashBalance->id : null,
                        'amount' => $toPay,
                        'accumulated_amount' => $totalAccumulated,
                        'payment_method' => $request->payment_method,
                        'payment_date' => $request->payment_date ?: now()->toDateString(),
                        'received_by' => $currentUser->id,
                        'installment_number' => $currentInstallment,
                        'latitude' => $request->latitude,
                        'longitude' => $request->longitude,
                        'status' => $isCompletingInstallment ? 'completed' : 'partial',
                    ]);
                }

                $payments[] = $payment;
                $totalPaid += $toPay;
                $paidByInstallment[$currentInstallment] = $newTotalForInstallment;
                $remainingAmountToAllocate -= $toPay;

                if ($remainingAmountToAllocate > 0) {
                    $currentInstallment++;
                }
            }

            // NOTE: la actualización del balance del crédito y recálculo de categoría
            // se realiza en el evento `created` del modelo `Payment`.
            // Para evitar descontar doblemente, no modificamos el balance aquí.

            // Actualizar el collected_amount de la caja si existe
            if ($cashBalance) {
                $cashBalance->collected_amount += $totalPaid;
                $cashBalance->final_amount += $totalPaid;
                $cashBalance->save();
            }
        });

        if ($remainingAmountToAllocate > 0) {
            // El monto excede el total de cuotas pendientes
            return $this->sendError('Monto excede cuotas', 'El monto supera el total pendiente de cuotas para este crédito', 400);
        }

        // Recalcular balance, total_paid y paid_installments basado en pagos reales
        // Esto asegura coherencia cuando se actualizan pagos parciales a completados
        $credit->recalculateBalance();
        $credit->save();

        // Cargar relaciones para todos los pagos creados
        foreach ($payments as $p) {
            $p->load(['credit.client', 'receivedBy']);
        }

        // Disparar evento de pago creado (para notificaciones WebSocket)
        if (count($payments) > 0) {
            $payment = $payments[0];
            $cobrador = $payment->receivedBy;       // Usuario que registró el pago
            $manager = $cobrador->assignedManager; // El manager del cobrador
            $client = $payment->credit->client;

            Log::info('🔔 Disparando evento PaymentCreated', [
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'cobrador_id' => $cobrador->id,
                'cobrador_name' => $cobrador->name,
                'manager_id' => $manager?->id,
                'manager_name' => $manager?->name,
                'client_id' => $client->id,
                'client_name' => $client->name,
            ]);

            event(new PaymentCreated($payment, $cobrador, $manager, $client));

            Log::info('✅ Evento PaymentCreated disparado correctamente');
        } else {
            Log::warning('⚠️ No se disparó evento PaymentCreated: no hay pagos creados');
        }

        $message = 'Pagos registrados exitosamente';
        if ($cashBalanceWasAutoCreated) {
            $message .= ' (Se creó una caja virtual automáticamente - requiere conciliación)';
        }

        return $this->sendResponse([
            'payments' => $payments,
            'total_paid' => $totalPaid,
            'cash_balance_auto_created' => $cashBalanceWasAutoCreated,
            'cash_balance_id' => $cashBalance?->id,
        ], $message);
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

        // Calcular la diferencia en el monto
        $difference = $request->amount - $oldAmount;

        // Envolver todas las operaciones en una transacción
        DB::transaction(function () use ($payment, $request, $credit, $difference, $oldAmount) {
            // Recalcular el monto acumulado para este pago y pagos posteriores
            if ($difference != 0) {
                // Obtener todos los pagos del crédito ordenados por fecha
                $allPayments = $credit->payments()->orderBy('payment_date')->orderBy('id')->get();

                // Encontrar el índice del pago actual
                $currentIndex = -1;
                foreach ($allPayments as $index => $p) {
                    if ($p->id === $payment->id) {
                        $currentIndex = $index;
                        break;
                    }
                }

                // Actualizar este pago y los pagos posteriores
                if ($currentIndex >= 0) {
                    $accumulatedAmount = $currentIndex > 0
                        ? $allPayments[$currentIndex - 1]->accumulated_amount
                        : 0;

                    // Actualizar este pago
                    $accumulatedAmount += $request->amount;
                    $payment->update([
                        'amount' => $request->amount,
                        'accumulated_amount' => $accumulatedAmount,
                        'payment_method' => $request->payment_method,
                        'payment_date' => $request->payment_date ?: now()->toDateString(),
                        'notes' => $request->notes,
                    ]);

                    // Actualizar pagos posteriores
                    for ($i = $currentIndex + 1; $i < count($allPayments); $i++) {
                        $accumulatedAmount += $allPayments[$i]->amount;
                        $allPayments[$i]->accumulated_amount = $accumulatedAmount;
                        $allPayments[$i]->save();
                    }
                } else {
                    // Si no encontramos el pago, actualizamos normalmente
                    $payment->update([
                        'amount' => $request->amount,
                        'payment_method' => $request->payment_method,
                        'payment_date' => $request->payment_date ?: now()->toDateString(),
                        'notes' => $request->notes,
                    ]);
                }
            } else {
                // Si no hay cambio en el monto, actualizamos normalmente
                $payment->update([
                    'amount' => $request->amount,
                    'payment_method' => $request->payment_method,
                    'payment_date' => $request->payment_date ?: now()->toDateString(),
                    'notes' => $request->notes,
                ]);
            }

            // ✅ El modelo Payment::updated() se encarga automáticamente de:
            //    - Actualizar credit.balance
            //    - Actualizar credit.total_paid
            //    - Actualizar credit.status (completed/active)

            // Actualizar el collected_amount de la caja si existe y hubo cambio en el monto
            if ($difference != 0 && $payment->cash_balance_id) {
                $cashBalance = CashBalance::find($payment->cash_balance_id);
                if ($cashBalance) {
                    $cashBalance->collected_amount += $difference;
                    $cashBalance->final_amount += $difference;
                    $cashBalance->save();
                }
            }
        });

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

        // Guardar referencias antes de eliminar
        $paymentAmount = $payment->amount;
        $cashBalanceId = $payment->cash_balance_id;

        // Envolver todas las operaciones en una transacción
        DB::transaction(function () use ($payment, $paymentAmount, $cashBalanceId) {
            // Obtener el crédito antes de eliminar
            $credit = $payment->credit;

            // Obtener todos los pagos del crédito ordenados por fecha
            $allPayments = $credit->payments()->orderBy('payment_date')->orderBy('id')->get();

            // Encontrar el índice del pago a eliminar
            $currentIndex = -1;
            foreach ($allPayments as $index => $p) {
                if ($p->id === $payment->id) {
                    $currentIndex = $index;
                    break;
                }
            }

            // Eliminar el pago PRIMERO (esto dispara el evento deleted() que actualiza el crédito)
            $payment->delete();

            // ✅ El modelo Payment::deleted() se encarga automáticamente de:
            //    - Restaurar credit.balance
            //    - Actualizar credit.total_paid
            //    - Actualizar credit.paid_installments
            //    - Actualizar credit.status (active si balance > 0)

            // Actualizar los pagos posteriores para recalcular el monto acumulado
            if ($currentIndex >= 0) {
                $baseAccumulated = $currentIndex > 0 ? $allPayments[$currentIndex - 1]->accumulated_amount : 0;

                for ($i = $currentIndex + 1; $i < count($allPayments); $i++) {
                    $baseAccumulated += $allPayments[$i]->amount;
                    $paymentToUpdate = Payment::find($allPayments[$i]->id);
                    if ($paymentToUpdate) {
                        $paymentToUpdate->accumulated_amount = $baseAccumulated;
                        $paymentToUpdate->save();
                    }
                }
            }

            // Actualizar el collected_amount de la caja si existe
            if ($cashBalanceId) {
                $cashBalance = CashBalance::find($cashBalanceId);
                if ($cashBalance) {
                    $cashBalance->collected_amount -= $paymentAmount;
                    $cashBalance->final_amount -= $paymentAmount;
                    $cashBalance->save();
                }
            }
        });

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
