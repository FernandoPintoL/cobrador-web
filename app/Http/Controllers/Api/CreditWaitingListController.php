<?php

namespace App\Http\Controllers\Api;

use App\Events\CreditApproved;
use App\Events\CreditDelivered;
use App\Events\CreditRejected;
use App\Http\Controllers\Controller;
use App\Models\CashBalance;
use App\Models\Credit;
use App\Services\WebSocketNotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreditWaitingListController extends Controller
{
    protected WebSocketNotificationService $wsService;

    public function __construct(WebSocketNotificationService $wsService)
    {
        $this->wsService = $wsService;
    }

    /**
     * Get credits pending approval
     */
    public function pendingApproval(): JsonResponse
    {
        $credits = Credit::pendingApproval()
            ->get()
            ->map(function ($credit) {
                return [
                    'id' => $credit->id,
                    'client' => [
                        'id' => $credit->client->id,
                        'name' => $credit->client->name,
                        'email' => $credit->client->email,
                        'phone' => $credit->client->phone,
                        'address' => $credit->client->address,
                        'latitude' => $credit->client->latitude,
                        'longitude' => $credit->client->longitude,
                        'client_category' => $credit->client->client_category,
                    ],
                    'created_by' => [
                        'id' => $credit->createdBy->id,
                        'name' => $credit->createdBy->name,
                    ],
                    'amount' => $credit->amount,
                    'total_amount' => $credit->total_amount,
                    'interest_rate' => $credit->interest_rate,
                    'frequency' => $credit->frequency,
                    'created_at' => $credit->created_at,
                    'delivery_status' => $credit->getDeliveryStatusInfo(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $credits,
            'count' => $credits->count(),
        ]);
    }

    /**
     * Get credits waiting for delivery
     */
    public function waitingForDelivery(): JsonResponse
    {
        $credits = Credit::waitingForDelivery()
            ->get()
            ->map(function ($credit) {
                return [
                    'id' => $credit->id,
                    'client' => [
                        'id' => $credit->client->id,
                        'name' => $credit->client->name,
                        'email' => $credit->client->email,
                        'phone' => $credit->client->phone,
                    ],
                    'created_by' => [
                        'id' => $credit->createdBy->id,
                        'name' => $credit->createdBy->name,
                    ],
                    'approved_by' => $credit->approvedBy ? [
                        'id' => $credit->approvedBy->id,
                        'name' => $credit->approvedBy->name,
                    ] : null,
                    'amount' => $credit->amount,
                    'total_amount' => $credit->total_amount,
                    'interest_rate' => $credit->interest_rate,
                    'frequency' => $credit->frequency,
                    'scheduled_delivery_date' => $credit->scheduled_delivery_date,
                    'delivery_status' => $credit->getDeliveryStatusInfo(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $credits,
            'count' => $credits->count(),
        ]);
    }

    /**
     * Get credits ready for delivery today
     */
    public function readyForDeliveryToday(): JsonResponse
    {
        $credits = Credit::readyForDeliveryToday()
            ->map(function ($credit) {
                return [
                    'id' => $credit->id,
                    'client' => [
                        'id' => $credit->client->id,
                        'name' => $credit->client->name,
                        'email' => $credit->client->email,
                        'phone' => $credit->client->phone,
                    ],
                    'amount' => $credit->amount,
                    'total_amount' => $credit->total_amount,
                    'scheduled_delivery_date' => $credit->scheduled_delivery_date,
                    'delivery_status' => $credit->getDeliveryStatusInfo(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $credits,
            'count' => $credits->count(),
        ]);
    }

    /**
     * Get credits overdue for delivery
     */
    public function overdueForDelivery(): JsonResponse
    {
        $credits = Credit::overdueForDelivery()
            ->map(function ($credit) {
                return [
                    'id' => $credit->id,
                    'client' => [
                        'id' => $credit->client->id,
                        'name' => $credit->client->name,
                        'email' => $credit->client->email,
                        'phone' => $credit->client->phone,
                    ],
                    'amount' => $credit->amount,
                    'total_amount' => $credit->total_amount,
                    'scheduled_delivery_date' => $credit->scheduled_delivery_date,
                    'days_overdue' => $credit->getDaysOverdueForDelivery(),
                    'delivery_status' => $credit->getDeliveryStatusInfo(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $credits,
            'count' => $credits->count(),
        ]);
    }

    /**
     * Approve a credit for delivery
     */
    public function approve(Request $request, Credit $credit): JsonResponse
    {
        // Verificar permisos
        if (! Credit::userCanApprove(Auth::user())) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para aprobar créditos',
            ], 403);
        }

        $request->validate([
            'scheduled_delivery_date' => 'sometimes|nullable|date|after_or_equal:now',
            'immediate_delivery' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            // Determinar fecha programada o inmediata
            $immediate = (bool) $request->boolean('immediate_delivery');
            $scheduledDate = null;
            if ($request->filled('scheduled_delivery_date')) {
                $scheduledDate = Carbon::parse($request->scheduled_delivery_date);
            } else {
                // Si se solicita inmediata, usar ahora; si no, por defecto mañana
                if ($immediate) {
                    $scheduledDate = now();
                } else {
                    $scheduledDate = now()->copy()->addDay();
                }
            }

            $approved = $credit->approveForDelivery(
                Auth::id(),
                $scheduledDate,
                $request->notes
            );

            if (! $approved) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'No se puede aprobar este crédito. Verifica su estado actual.',
                ], 422);
            }

            // Actualizar el campo immediate_delivery_requested para indicar urgencia
            // NOTA: Este campo solo indica si el manager marcó como "entrega inmediata"
            // NO significa que se entregue automáticamente - el cobrador debe confirmar la entrega física
            $credit->immediate_delivery_requested = $immediate;
            $credit->save();

            DB::commit();

            // Cargar relaciones necesarias
            $credit->load(['client', 'client.assignedCobrador', 'client.assignedManager']);

            // Obtener manager y cobrador
            $manager = Auth::user();
            $cobrador = $credit->client->assignedCobrador ?? $credit->createdBy;

            // Disparar evento de aprobación
            try {
                // El crédito siempre queda en waiting_delivery después de aprobar
                // El cobrador debe confirmar la entrega física para activarlo
                event(new CreditApproved($credit, $manager, $cobrador, false));

                // Enviar notificación WebSocket
                $this->wsService->notifyCreditApproved($credit, $manager, $cobrador, false);
            } catch (\Exception $e) {
                Log::error('Error sending credit approval notification', [
                    'credit_id' => $credit->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $message = $immediate
                ? 'Crédito aprobado para entrega inmediata. El cobrador debe confirmar la entrega física para activarlo.'
                : 'Crédito aprobado para entrega programada. El cobrador debe confirmar la entrega física en la fecha indicada.';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'credit' => $credit->fresh(),
                    'delivery_status' => $credit->fresh()->getDeliveryStatusInfo(),
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al aprobar el crédito: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject a credit
     */
    public function reject(Request $request, Credit $credit): JsonResponse
    {
        // Verificar permisos
        if (! Credit::userCanApprove(Auth::user())) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para rechazar créditos',
            ], 403);
        }

        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            $success = $credit->reject(Auth::id(), $request->reason);

            if (! $success) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'No se puede rechazar este crédito. Verifica su estado actual.',
                ], 422);
            }

            DB::commit();

            // Cargar relaciones necesarias
            $credit->load(['client', 'client.assignedCobrador', 'client.assignedManager']);

            // Obtener manager y cobrador
            $manager = Auth::user();
            $cobrador = $credit->client->assignedCobrador ?? $credit->createdBy;

            // Disparar evento de rechazo
            try {
                event(new CreditRejected($credit, $manager, $cobrador));

                // Enviar notificación WebSocket
                $this->wsService->notifyCreditRejected($credit, $manager, $cobrador);
            } catch (\Exception $e) {
                Log::error('Error sending credit rejection notification', [
                    'credit_id' => $credit->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Crédito rechazado exitosamente',
                'data' => [
                    'credit' => $credit->fresh(),
                    'delivery_status' => $credit->fresh()->getDeliveryStatusInfo(),
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al rechazar el crédito: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Deliver credit to client (activate it)
     */
    public function deliver(Request $request, Credit $credit): JsonResponse
    {
        // Verificar permisos
        if (! Credit::userCanDeliver(Auth::user())) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para entregar créditos',
            ], 403);
        }

        $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        // Validaciones adicionales para cobradores: cliente asignado y caja abierta o auto-creada
        $authUser = Auth::user();
        $cashBalanceWasAutoCreated = false;
        if ($authUser && $authUser->hasRole('cobrador')) {
            // El cobrador solo puede entregar créditos de sus clientes asignados
            $credit->loadMissing('client');
            if ($credit->client && $credit->client->assigned_cobrador_id !== $authUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado: el cliente no está asignado a ti',
                ], 403);
            }

            // Buscar caja abierta o crear una virtual automáticamente
            $today = now()->toDateString();
            $openCash = CashBalance::where('cobrador_id', $authUser->id)
                ->whereDate('date', $today)
                ->where('status', 'open')
                ->first();

            // Si no existe caja abierta, crear una caja virtual automáticamente
            if (! $openCash) {
                $openCash = CashBalance::create([
                    'cobrador_id' => $authUser->id,
                    'date' => $today,
                    'initial_amount' => 0,
                    'collected_amount' => 0,
                    'lent_amount' => 0,
                    'final_amount' => 0,
                    'status' => 'open',
                    'requires_reconciliation' => true,
                    'closure_notes' => 'Caja creada automáticamente al entregar crédito sin caja abierta',
                ]);

                $cashBalanceWasAutoCreated = true;

                // Disparar evento de caja auto-creada
                $manager = $authUser->assignedManager;
                event(new \App\Events\CashBalanceAutoCreated($openCash, $authUser, $manager, 'credit_delivery'));

                Log::info('Virtual cash balance auto-created for credit delivery', [
                    'cash_balance_id' => $openCash->id,
                    'cobrador_id' => $authUser->id,
                    'credit_id' => $credit->id,
                    'date' => $today,
                ]);
            }
        }

        DB::beginTransaction();
        try {
            $success = $credit->deliverToClient(Auth::id(), $request->notes);

            if (! $success) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'No se puede entregar este crédito. Verifica su estado actual.',
                ], 422);
            }

            // Auto-actualizar caja abierta: vincular crédito y actualizar montos
            if ($authUser && $authUser->hasRole('cobrador') && isset($openCash)) {
                $credit->update(['cash_balance_id' => $openCash->id]);

                $openCash->update([
                    'lent_amount' => $openCash->lent_amount + $credit->amount,
                    'final_amount' => $openCash->final_amount - $credit->amount,
                ]);
            }

            DB::commit();

            // Cargar relaciones necesarias
            $credit->load(['client', 'client.assignedCobrador', 'client.assignedManager']);

            // Obtener manager y cobrador
            $deliveryUser = Auth::user();
            $cobrador = $credit->client->assignedCobrador ?? $credit->createdBy;
            $manager = $cobrador->assignedManager ?? $credit->client->assignedManager;

            // Si no hay manager asignado y el usuario autenticado es manager, usarlo
            if (! $manager && $deliveryUser->hasRole('manager')) {
                $manager = $deliveryUser;
            }

            // Disparar evento de entrega solo si tenemos manager
            if ($manager && $cobrador) {
                try {
                    event(new CreditDelivered($credit, $manager, $cobrador));

                    // Enviar notificación WebSocket
                    $this->wsService->notifyCreditDelivered($credit, $manager, $cobrador);
                } catch (\Exception $e) {
                    Log::error('Error sending credit delivery notification', [
                        'credit_id' => $credit->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $message = 'Crédito entregado al cliente exitosamente';
            if ($cashBalanceWasAutoCreated) {
                $message .= ' (Se creó una caja virtual automáticamente - requiere conciliación)';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'credit' => $credit->fresh(),
                    'delivery_status' => $credit->fresh()->getDeliveryStatusInfo(),
                    'cash_balance_auto_created' => $cashBalanceWasAutoCreated,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al entregar el crédito: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reschedule delivery date
     */
    public function reschedule(Request $request, Credit $credit): JsonResponse
    {
        // Verificar permisos
        if (! Credit::userCanApprove(Auth::user())) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para reprogramar entregas',
            ], 403);
        }

        $request->validate([
            'new_delivery_date' => 'required|date|after:now',
            'reason' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $newDate = Carbon::parse($request->new_delivery_date);
            $success = $credit->rescheduleDelivery(
                $newDate,
                Auth::id(),
                $request->reason
            );

            if (! $success) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'No se puede reprogramar este crédito. Verifica su estado actual.',
                ], 422);
            }

            DB::commit();

            // Disparar evento de actualización
            //            event(new CreditWaitingListUpdate($credit->fresh(), 'rescheduled', Auth::user()));

            return response()->json([
                'success' => true,
                'message' => 'Fecha de entrega reprogramada exitosamente',
                'data' => [
                    'credit' => $credit->fresh(),
                    'delivery_status' => $credit->fresh()->getDeliveryStatusInfo(),
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al reprogramar la entrega: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get delivery status for a specific credit
     */
    public function getDeliveryStatus(Credit $credit): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'credit_id' => $credit->id,
                'delivery_status' => $credit->getDeliveryStatusInfo(),
            ],
        ]);
    }

    /**
     * Get waiting list summary statistics
     */
    public function getSummary(): JsonResponse
    {
        $pendingApproval = Credit::pendingApproval()->count();
        $waitingDelivery = Credit::waitingForDelivery()->count();
        $readyToday = Credit::readyForDeliveryToday()->count();
        $overdue = Credit::overdueForDelivery()->count();

        return response()->json([
            'success' => true,
            'data' => [
                'pending_approval' => $pendingApproval,
                'waiting_delivery' => $waitingDelivery,
                'ready_today' => $readyToday,
                'overdue_delivery' => $overdue,
                'total_in_waiting_list' => $pendingApproval + $waitingDelivery,
            ],
        ]);
    }
}
