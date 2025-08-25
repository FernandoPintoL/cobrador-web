<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Credit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use App\Events\CreditWaitingListUpdate;

class CreditWaitingListController extends Controller
{
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
            'count' => $credits->count()
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
            'count' => $credits->count()
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
            'count' => $credits->count()
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
            'count' => $credits->count()
        ]);
    }

    /**
     * Approve a credit for delivery
     */
    public function approve(Request $request, Credit $credit): JsonResponse
    {
        // Verificar permisos
        if (!Credit::userCanApprove(Auth::user())) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para aprobar créditos'
            ], 403);
        }

        $request->validate([
            'scheduled_delivery_date' => 'sometimes|nullable|date|after_or_equal:now',
            'immediate_delivery' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:1000'
        ]);

        DB::beginTransaction();
        try {
            // Determinar fecha programada o inmediata
            $immediate = (bool) $request->boolean('immediate_delivery');
            $scheduledDate = null;
            if ($request->filled('scheduled_delivery_date')) {
                $scheduledDate = Carbon::parse($request->scheduled_delivery_date);
            } else {
                // Si no se envía fecha y se solicita inmediata, usar ahora
                if ($immediate) {
                    $scheduledDate = now();
                } else {
                    // Por compatibilidad, si no hay fecha, también permitir ahora
                    $scheduledDate = now();
                }
            }

            $approved = $credit->approveForDelivery(
                Auth::id(),
                $scheduledDate,
                $request->notes
            );

            if (!$approved) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede aprobar este crédito. Verifica su estado actual.'
                ], 422);
            }

            // Si la fecha es ahora o en el pasado (o immediate=true), entregar de inmediato
            $shouldDeliverNow = $immediate || ($scheduledDate && $scheduledDate <= now());
            $deliveredNow = false;
            if ($shouldDeliverNow) {
                $deliveredNow = $credit->deliverToClient(Auth::id(), $request->notes);
                if (!$deliveredNow) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'No se pudo entregar el crédito inmediatamente. Verifica su estado actual.'
                    ], 422);
                }
            }

            DB::commit();

            // Disparar eventos de actualización
            event(new CreditWaitingListUpdate($credit->fresh(), 'approved', Auth::user()));
            if ($shouldDeliverNow && $deliveredNow) {
                event(new CreditWaitingListUpdate($credit->fresh(), 'delivered', Auth::user()));
            }

            return response()->json([
                'success' => true,
                'message' => $shouldDeliverNow && $deliveredNow
                    ? 'Crédito aprobado y entregado al cliente exitosamente'
                    : 'Crédito aprobado para entrega exitosamente',
                'data' => [
                    'credit' => $credit->fresh(),
                    'delivery_status' => $credit->fresh()->getDeliveryStatusInfo()
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al aprobar el crédito: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a credit
     */
    public function reject(Request $request, Credit $credit): JsonResponse
    {
        // Verificar permisos
        if (!Credit::userCanApprove(Auth::user())) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para rechazar créditos'
            ], 403);
        }

        $request->validate([
            'reason' => 'required|string|max:1000'
        ]);

        DB::beginTransaction();
        try {
            $success = $credit->reject(Auth::id(), $request->reason);

            if (!$success) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede rechazar este crédito. Verifica su estado actual.'
                ], 422);
            }

            DB::commit();

            // Disparar evento de actualización
            event(new CreditWaitingListUpdate($credit->fresh(), 'rejected', Auth::user()));

            return response()->json([
                'success' => true,
                'message' => 'Crédito rechazado exitosamente',
                'data' => [
                    'credit' => $credit->fresh(),
                    'delivery_status' => $credit->fresh()->getDeliveryStatusInfo()
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al rechazar el crédito: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deliver credit to client (activate it)
     */
    public function deliver(Request $request, Credit $credit): JsonResponse
    {
        // Verificar permisos
        if (!Credit::userCanDeliver(Auth::user())) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para entregar créditos'
            ], 403);
        }

        $request->validate([
            'notes' => 'nullable|string|max:1000'
        ]);

        DB::beginTransaction();
        try {
            $success = $credit->deliverToClient(Auth::id(), $request->notes);

            if (!$success) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede entregar este crédito. Verifica su estado actual.'
                ], 422);
            }

            DB::commit();

            // Disparar evento de actualización
            event(new CreditWaitingListUpdate($credit->fresh(), 'delivered', Auth::user()));

            return response()->json([
                'success' => true,
                'message' => 'Crédito entregado al cliente exitosamente',
                'data' => [
                    'credit' => $credit->fresh(),
                    'delivery_status' => $credit->fresh()->getDeliveryStatusInfo()
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al entregar el crédito: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reschedule delivery date
     */
    public function reschedule(Request $request, Credit $credit): JsonResponse
    {
        // Verificar permisos
        if (!Credit::userCanApprove(Auth::user())) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para reprogramar entregas'
            ], 403);
        }

        $request->validate([
            'new_delivery_date' => 'required|date|after:now',
            'reason' => 'nullable|string|max:500'
        ]);

        DB::beginTransaction();
        try {
            $newDate = Carbon::parse($request->new_delivery_date);
            $success = $credit->rescheduleDelivery(
                $newDate,
                Auth::id(),
                $request->reason
            );

            if (!$success) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede reprogramar este crédito. Verifica su estado actual.'
                ], 422);
            }

            DB::commit();

            // Disparar evento de actualización
            event(new CreditWaitingListUpdate($credit->fresh(), 'rescheduled', Auth::user()));

            return response()->json([
                'success' => true,
                'message' => 'Fecha de entrega reprogramada exitosamente',
                'data' => [
                    'credit' => $credit->fresh(),
                    'delivery_status' => $credit->fresh()->getDeliveryStatusInfo()
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al reprogramar la entrega: ' . $e->getMessage()
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
                'delivery_status' => $credit->getDeliveryStatusInfo()
            ]
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
                'total_in_waiting_list' => $pendingApproval + $waitingDelivery
            ]
        ]);
    }
}
