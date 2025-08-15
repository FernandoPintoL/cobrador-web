<?php

namespace App\Http\Controllers\Api;

use App\Models\Credit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Events\CreditWaitingListUpdate;
use App\Events\CreditRequiresAttention;

class CreditController extends BaseController
{
    /**
     * Display a listing of credits.
     */
    public function index(Request $request)
    {
        $query = Credit::with(['client', 'payments', 'createdBy']);

        // Si el usuario es cobrador, solo mostrar créditos de sus clientes asignados
        $currentUser = Auth::user();
        if ($currentUser) {
            if ($currentUser->hasRole('cobrador')) {
                // Solo créditos de clientes asignados al cobrador
                $query->whereHas('client', function ($q) use ($currentUser) {
                    $q->where('assigned_cobrador_id', $currentUser->id);
                });
            } elseif ($currentUser->hasRole('manager')) {
                // Créditos de clientes asignados directamente al manager
                $directClientIds = User::whereHas('roles', function($q) {
                        $q->where('name', 'client');
                    })
                    ->where('assigned_manager_id', $currentUser->id)
                    ->pluck('id')->toArray();
                // Cobradores asignados al manager
                $cobradorIds = User::role('cobrador')->where('assigned_manager_id', $currentUser->id)->pluck('id')->toArray();
                // Clientes de los cobradores
                $cobradorClientIds = User::whereHas('roles', function($q) {
                        $q->where('name', 'client');
                    })
                    ->whereIn('assigned_cobrador_id', $cobradorIds)
                    ->pluck('id')->toArray();
                // Unir todos los clientes
                $allClientIds = array_unique(array_merge($directClientIds, $cobradorClientIds));
                $query->whereIn('client_id', $allClientIds);
            }
        }

        // Filtros adicionales
        $query->when($request->client_id, function ($query, $clientId) {
                $query->where('client_id', $clientId);
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->search, function ($query, $search) {
                $query->whereHas('client', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
            })
            ->when($request->cobrador_id, function ($query, $cobradorId) {
                // Solo admins y managers pueden filtrar por cobrador específico
                if (Auth::user() && (Auth::user()->hasRole('admin') || Auth::user()->hasRole('manager'))) {
                    $query->whereHas('client', function ($q) use ($cobradorId) {
                        $q->where('assigned_cobrador_id', $cobradorId);
                    });
                }
            });

        $credits = $query->paginate(15);

        return $this->sendResponse($credits);
    }

    /**
     * Store a newly created credit.
     */
    public function store(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:users,id',
            'cobrador_id' => 'nullable|exists:users,id', // Para que managers puedan especificar el cobrador
            'amount' => 'required|numeric|min:0',
            'balance' => 'required|numeric|min:0',
            'frequency' => 'required|in:daily,weekly,biweekly,monthly',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'status' => 'in:pending_approval,waiting_delivery,active,completed,defaulted,cancelled',
            'scheduled_delivery_date' => 'nullable|date|after:now', // Para managers que quieran programar entrega
        ]);

        $currentUser = Auth::user();

        // Verificar que el cliente exista y tenga rol de cliente
        $client = User::findOrFail($request->client_id);
        if (!$client->hasRole('client')) {
            return $this->sendError('Cliente no válido', 'El usuario especificado no es un cliente', 400);
        }

        // Validar cobrador_id si se proporciona
        $targetCobrador = null;
        if ($request->has('cobrador_id')) {
            $targetCobrador = User::findOrFail($request->cobrador_id);
            if (!$targetCobrador->hasRole('cobrador')) {
                return $this->sendError('Cobrador no válido', 'El usuario especificado no es un cobrador', 400);
            }
        }

        // Lógica de permisos según el rol del usuario autenticado
        if ($currentUser->hasRole('cobrador')) {
            // Los cobradores solo pueden crear créditos para sus clientes asignados
            if ($client->assigned_cobrador_id !== $currentUser->id) {
                return $this->sendError('No autorizado', 'No puedes crear créditos para clientes que no tienes asignados', 403);
            }
            // Los cobradores no pueden especificar otro cobrador
            if ($request->has('cobrador_id') && $request->cobrador_id !== $currentUser->id) {
                return $this->sendError('No autorizado', 'Los cobradores solo pueden crear créditos para sí mismos', 403);
            }
        } elseif ($currentUser->hasRole('manager')) {
            // Los managers pueden crear créditos para:
            // 1. Clientes asignados directamente a ellos
            // 2. Clientes de cobradores bajo su supervisión

            $isDirectlyAssigned = $client->assigned_manager_id === $currentUser->id;
            $isAssignedThroughCobrador = $client->assignedCobrador &&
                                       $client->assignedCobrador->assigned_manager_id === $currentUser->id;

            if ($targetCobrador) {
                // Si especifica cobrador, debe estar asignado al manager
                if ($targetCobrador->assigned_manager_id !== $currentUser->id) {
                    return $this->sendError('No autorizado', 'No puedes crear créditos para cobradores que no tienes asignados', 403);
                }
                // Y el cliente debe estar asignado al cobrador especificado
                if ($client->assigned_cobrador_id !== $targetCobrador->id) {
                    return $this->sendError('No autorizado', 'El cliente no está asignado al cobrador especificado', 403);
                }
            } else {
                // Si no especifica cobrador, verificar acceso directo o indirecto
                if (!$isDirectlyAssigned && !$isAssignedThroughCobrador) {
                    return $this->sendError('No autorizado', 'El cliente debe estar asignado directamente a ti o a un cobrador bajo tu supervisión', 403);
                }
            }
        }
        // Los admins pueden crear créditos para cualquier combinación cliente-cobrador

        // Determinar el estado inicial del crédito
        $initialStatus = 'active'; // Por defecto para compatibilidad
        if ($request->has('status')) {
            $initialStatus = $request->status;
        } elseif ($currentUser->hasRole('manager') || $currentUser->hasRole('cobrador')) {
            // Los managers y cobradores crean créditos en lista de espera por defecto
            $initialStatus = 'pending_approval';
        }

        $credit = Credit::create([
            'client_id' => $request->client_id,
            'created_by' => $currentUser->id,
            'amount' => $request->amount,
            'interest_rate' => $request->interest_rate ?? 0,
            'total_amount' => $request->total_amount ?? $request->amount,
            'balance' => $request->balance,
            'installment_amount' => $request->installment_amount ?? ($request->amount / 1),
            'frequency' => $request->frequency,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'status' => $initialStatus,
            'scheduled_delivery_date' => $request->scheduled_delivery_date, // Para managers
        ]);

        $credit->load(['client', 'payments', 'createdBy']);

        // Disparar evento si el crédito fue creado en lista de espera
        if ($initialStatus === 'pending_approval') {
            event(new CreditWaitingListUpdate($credit, 'created', $currentUser));
        }

        $message = 'Crédito creado exitosamente';
        if ($initialStatus === 'pending_approval') {
            $message .= ' (en lista de espera para aprobación)';
        }

        return $this->sendResponse($credit, $message);
    }

    /**
     * Store a credit directly in waiting list (for managers).
     */
    public function storeInWaitingList(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:users,id',
            'cobrador_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'frequency' => 'required|in:daily,weekly,biweekly,monthly',
            'installment_amount' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'scheduled_delivery_date' => 'nullable|date|after:now',
            'notes' => 'nullable|string|max:1000',
        ]);

        $currentUser = Auth::user();

        // Solo managers y admins pueden usar este endpoint
        if (!$currentUser->hasRole(['manager', 'admin'])) {
            return $this->sendError('No autorizado', 'Solo managers y administradores pueden crear créditos en lista de espera', 403);
        }

        // Verificar que el cliente y cobrador existan y tengan los roles correctos
        $client = User::findOrFail($request->client_id);
        $cobrador = User::findOrFail($request->cobrador_id);

        if (!$client->hasRole('client')) {
            return $this->sendError('Cliente no válido', 'El usuario especificado no es un cliente', 400);
        }

        if (!$cobrador->hasRole('cobrador')) {
            return $this->sendError('Cobrador no válido', 'El usuario especificado no es un cobrador', 400);
        }

        // Verificar permisos del manager
        if ($currentUser->hasRole('manager')) {
            // El cobrador debe estar asignado al manager
            if ($cobrador->assigned_manager_id !== $currentUser->id) {
                return $this->sendError('No autorizado', 'El cobrador no está asignado a tu gestión', 403);
            }

            // El cliente debe estar asignado al cobrador
            if ($client->assigned_cobrador_id !== $cobrador->id) {
                return $this->sendError('No autorizado', 'El cliente no está asignado al cobrador especificado', 403);
            }
        }

        // Calcular valores derivados
        $interestRate = $request->interest_rate ?? 0;
        $totalAmount = $request->amount * (1 + ($interestRate / 100));
        $installmentAmount = $request->installment_amount ?? $this->calculateInstallmentAmount(
            $totalAmount,
            $request->frequency,
            $request->start_date,
            $request->end_date
        );

        $credit = Credit::create([
            'client_id' => $request->client_id,
            'created_by' => $currentUser->id,
            'amount' => $request->amount,
            'interest_rate' => $interestRate,
            'total_amount' => $totalAmount,
            'balance' => $totalAmount,
            'installment_amount' => $installmentAmount,
            'frequency' => $request->frequency,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'status' => 'pending_approval',
            'scheduled_delivery_date' => $request->scheduled_delivery_date,
            'delivery_notes' => $request->notes,
        ]);

        $credit->load(['client', 'createdBy']);

        // Disparar evento para notificaciones
        event(new CreditWaitingListUpdate($credit, 'created', $currentUser));

        return $this->sendResponse([
            'credit' => $credit,
            'delivery_status' => $credit->getDeliveryStatusInfo(),
            'cobrador' => [
                'id' => $cobrador->id,
                'name' => $cobrador->name,
                'email' => $cobrador->email,
                'phone' => $cobrador->phone,
            ]
        ], 'Crédito creado en lista de espera exitosamente');
    }

    /**
     * Helper method to calculate installment amount based on frequency.
     */
    private function calculateInstallmentAmount($totalAmount, $frequency, $startDate, $endDate)
    {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $interval = $start->diff($end);

        switch ($frequency) {
            case 'daily':
                $totalPeriods = $interval->days;
                break;
            case 'weekly':
                $totalPeriods = floor($interval->days / 7);
                break;
            case 'biweekly':
                $totalPeriods = floor($interval->days / 14);
                break;
            case 'monthly':
                $totalPeriods = ($interval->y * 12) + $interval->m;
                break;
            default:
                $totalPeriods = 1;
        }

        return $totalPeriods > 0 ? round($totalAmount / $totalPeriods, 2) : $totalAmount;
    }

    /**
     * Display the specified credit.
     */
    public function show(Credit $credit)
    {
        $currentUser = Auth::user();

        // Si el usuario es cobrador, verificar que el cliente del crédito esté asignado a él
        if ($currentUser->hasRole('cobrador')) {
            if ($credit->client->assigned_cobrador_id !== $currentUser->id) {
                return $this->sendError('No autorizado', 'No tienes acceso a este crédito', 403);
            }
        }

        $credit->load(['client', 'payments', 'createdBy']);
        return $this->sendResponse($credit);
    }

    /**
     * Update the specified credit.
     */
    public function update(Request $request, Credit $credit)
    {
        $request->validate([
            'client_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0',
            'balance' => 'required|numeric|min:0',
            'frequency' => 'required|in:daily,weekly,biweekly,monthly',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'status' => 'in:active,completed,defaulted,cancelled',
        ]);

        $currentUser = Auth::user();

        // Verificar permisos para actualizar el crédito
        if ($currentUser->hasRole('cobrador')) {
            // El cobrador solo puede actualizar créditos de sus clientes asignados
            if ($credit->client->assigned_cobrador_id !== $currentUser->id) {
                return $this->sendError('No autorizado', 'No puedes actualizar créditos de clientes que no tienes asignados', 403);
            }

            // Si cambia el cliente, verificar que el nuevo cliente también esté asignado
            if ($request->client_id !== $credit->client_id) {
                $newClient = User::findOrFail($request->client_id);
                if ($newClient->assigned_cobrador_id !== $currentUser->id) {
                    return $this->sendError('No autorizado', 'No puedes asignar el crédito a un cliente que no tienes asignado', 403);
                }
            }
        }

        $credit->update([
            'client_id' => $request->client_id,
            'amount' => $request->amount,
            'balance' => $request->balance,
            'frequency' => $request->frequency,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'status' => $request->status,
        ]);

        $credit->load(['client', 'payments', 'createdBy']);

        return $this->sendResponse($credit, 'Crédito actualizado exitosamente');
    }

    /**
     * Remove the specified credit.
     */
    public function destroy(Credit $credit)
    {
        $currentUser = Auth::user();

        // Si el usuario es cobrador, verificar que el cliente del crédito esté asignado a él
        if ($currentUser->hasRole('cobrador')) {
            if ($credit->client->assigned_cobrador_id !== $currentUser->id) {
                return $this->sendError('No autorizado', 'No puedes eliminar créditos de clientes que no tienes asignados', 403);
            }
        }

        $credit->delete();
        return $this->sendResponse([], 'Crédito eliminado exitosamente');
    }

    /**
     * Get credits by client.
     */
    public function getByClient(User $client)
    {
        $currentUser = Auth::user();

        // Verificar que el usuario especificado sea un cliente
        if (!$client->hasRole('client')) {
            return $this->sendError('Usuario no válido', 'El usuario especificado no es un cliente', 400);
        }

        // Si el usuario es cobrador, verificar que el cliente esté asignado a él
        if ($currentUser->hasRole('cobrador')) {
            if ($client->assigned_cobrador_id !== $currentUser->id) {
                return $this->sendError('No autorizado', 'No tienes acceso a los créditos de este cliente', 403);
            }
        }

        $credits = $client->credits()->with(['payments', 'createdBy'])->get();
        return $this->sendResponse($credits, "Créditos del cliente {$client->name} obtenidos exitosamente");
    }

    /**
     * Get remaining installments for a credit.
     */
    public function getRemainingInstallments(Credit $credit)
    {
        $currentUser = Auth::user();

        // Si el usuario es cobrador, verificar que el cliente del crédito esté asignado a él
        if ($currentUser->hasRole('cobrador')) {
            if ($credit->client->assigned_cobrador_id !== $currentUser->id) {
                return $this->sendError('No autorizado', 'No tienes acceso a este crédito', 403);
            }
        }

        $remaining = $credit->getRemainingInstallments();
        return $this->sendResponse(['remaining_installments' => $remaining]);
    }

    /**
     * Get credits by cobrador (for admins and managers).
     */
    public function getByCobrador(Request $request, User $cobrador)
    {
        $currentUser = Auth::user();

        // Solo admins y managers pueden usar este endpoint
        if (!($currentUser->hasRole('admin') || $currentUser->hasRole('manager'))) {
            return $this->sendError('No autorizado', 'No tienes permisos para realizar esta acción', 403);
        }

        // Verificar que el usuario especificado sea un cobrador
        if (!$cobrador->hasRole('cobrador')) {
            return $this->sendError('Usuario no válido', 'El usuario especificado no es un cobrador', 400);
        }

        $query = Credit::with(['client', 'payments', 'createdBy'])
            ->whereHas('client', function ($q) use ($cobrador) {
                $q->where('assigned_cobrador_id', $cobrador->id);
            });

        // Filtros adicionales
        $query->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->search, function ($query, $search) {
                $query->whereHas('client', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
            });

        $credits = $query->paginate($request->get('per_page', 15));

        return $this->sendResponse($credits, "Créditos del cobrador {$cobrador->name} obtenidos exitosamente");
    }

    /**
     * Get credit statistics for a cobrador.
     */
    public function getCobradorStats(User $cobrador)
    {
        $currentUser = Auth::user();

        // Los cobradores solo pueden ver sus propias estadísticas
        if ($currentUser->hasRole('cobrador') && $currentUser->id !== $cobrador->id) {
            return $this->sendError('No autorizado', 'Solo puedes ver tus propias estadísticas', 403);
        }

        // Admins y managers pueden ver estadísticas de cualquier cobrador
        if (!($currentUser->hasRole('admin') || $currentUser->hasRole('manager') || $currentUser->id === $cobrador->id)) {
            return $this->sendError('No autorizado', 'No tienes permisos para realizar esta acción', 403);
        }

        // Verificar que el usuario especificado sea un cobrador
        if (!$cobrador->hasRole('cobrador')) {
            return $this->sendError('Usuario no válido', 'El usuario especificado no es un cobrador', 400);
        }

        $stats = [
            'total_credits' => Credit::whereHas('client', function ($q) use ($cobrador) {
                $q->where('assigned_cobrador_id', $cobrador->id);
            })->count(),

            'active_credits' => Credit::whereHas('client', function ($q) use ($cobrador) {
                $q->where('assigned_cobrador_id', $cobrador->id);
            })->where('status', 'active')->count(),

            'completed_credits' => Credit::whereHas('client', function ($q) use ($cobrador) {
                $q->where('assigned_cobrador_id', $cobrador->id);
            })->where('status', 'completed')->count(),

            'defaulted_credits' => Credit::whereHas('client', function ($q) use ($cobrador) {
                $q->where('assigned_cobrador_id', $cobrador->id);
            })->where('status', 'defaulted')->count(),

            'total_amount' => Credit::whereHas('client', function ($q) use ($cobrador) {
                $q->where('assigned_cobrador_id', $cobrador->id);
            })->sum('amount'),

            'total_balance' => Credit::whereHas('client', function ($q) use ($cobrador) {
                $q->where('assigned_cobrador_id', $cobrador->id);
            })->sum('balance'),
        ];

        return $this->sendResponse($stats, "Estadísticas de créditos del cobrador {$cobrador->name} obtenidas exitosamente");
    }

    /**
     * Get credit statistics for a manager.
     */
    public function getManagerStats(User $manager)
    {
        $currentUser = Auth::user();
        // Solo admins y el propio manager pueden ver estas estadísticas
        if (!($currentUser->hasRole('admin') || ($currentUser->hasRole('manager') && $currentUser->id === $manager->id))) {
            return $this->sendError('No autorizado', 'No tienes permisos para realizar esta acción', 403);
        }
        if (!$manager->hasRole('manager')) {
            return $this->sendError('Usuario no válido', 'El usuario especificado no es un manager', 400);
        }
        // Obtener IDs de clientes directos
        $directClientIds = User::whereHas('roles', function($q) {
                $q->where('name', 'client');
            })
            ->where('assigned_manager_id', $manager->id)
            ->pluck('id')->toArray();
        // Cobradores asignados al manager
        $cobradorIds = User::role('cobrador')->where('assigned_manager_id', $manager->id)->pluck('id')->toArray();
        // Clientes de los cobradores
        $cobradorClientIds = User::whereHas('roles', function($q) {
                $q->where('name', 'client');
            })
            ->whereIn('assigned_cobrador_id', $cobradorIds)
            ->pluck('id')->toArray();
        // Unir todos los clientes
        $allClientIds = array_unique(array_merge($directClientIds, $cobradorClientIds));
        // Estadísticas
        $stats = [
            'total_credits' => Credit::whereIn('client_id', $allClientIds)->count(),
            'active_credits' => Credit::whereIn('client_id', $allClientIds)->where('status', 'active')->count(),
            'completed_credits' => Credit::whereIn('client_id', $allClientIds)->where('status', 'completed')->count(),
            'defaulted_credits' => Credit::whereIn('client_id', $allClientIds)->where('status', 'defaulted')->count(),
            'total_amount' => Credit::whereIn('client_id', $allClientIds)->sum('amount'),
            'total_balance' => Credit::whereIn('client_id', $allClientIds)->sum('balance'),
        ];
        return $this->sendResponse($stats, "Estadísticas de créditos del manager {$manager->name} obtenidas exitosamente");
    }

    /**
     * Get credits that require attention (overdue or nearing due date).
     */
    public function getCreditsRequiringAttention(Request $request)
    {
        $currentUser = Auth::user();

        $query = Credit::with(['client', 'payments', 'createdBy'])
            ->where('status', 'active');

        // Si el usuario es cobrador, solo mostrar créditos de sus clientes
        if ($currentUser && $currentUser->hasRole('cobrador')) {
            $query->whereHas('client', function ($q) use ($currentUser) {
                $q->where('assigned_cobrador_id', $currentUser->id);
            });
        }

        // Filtrar créditos que requieren atención
        $query->where(function ($q) {
            // Créditos vencidos (fecha de fin pasada)
            $q->where('end_date', '<', now())
              // O créditos que vencen en los próximos 7 días
              ->orWhere('end_date', '<=', now()->addDays(7));
        });

        $credits = $query->paginate($request->get('per_page', 15));

        return $this->sendResponse($credits, 'Créditos que requieren atención obtenidos exitosamente');
    }

    /**
     * Approve a credit for delivery
     */
    public function approve(Request $request, Credit $credit)
    {
        $currentUser = Auth::user();

        // Solo managers y admins pueden aprobar créditos
        if (!$currentUser->hasRole(['manager', 'admin'])) {
            return $this->sendError('No autorizado', 'Solo managers y administradores pueden aprobar créditos', 403);
        }

        $request->validate([
            'scheduled_delivery_date' => 'nullable|date|after:now',
            'notes' => 'nullable|string|max:1000'
        ]);

        // Verificar que el crédito esté en estado pending_approval
        if ($credit->status !== 'pending_approval') {
            return $this->sendError('Estado inválido', 'El crédito no está pendiente de aprobación', 400);
        }

        $credit->update([
            'status' => 'waiting_delivery',
            'approved_by' => $currentUser->id,
            'approved_at' => now(),
            'scheduled_delivery_date' => $request->scheduled_delivery_date ?? now()->addDay(),
            'approval_notes' => $request->notes,
        ]);

        $credit->load(['client', 'createdBy', 'approvedBy']);

        // Disparar evento de aprobación
        event(new CreditWaitingListUpdate($credit, 'approved', $currentUser));

        return $this->sendResponse($credit, 'Crédito aprobado exitosamente');
    }

    /**
     * Reject a credit
     */
    public function reject(Request $request, Credit $credit)
    {
        $currentUser = Auth::user();

        // Solo managers y admins pueden rechazar créditos
        if (!$currentUser->hasRole(['manager', 'admin'])) {
            return $this->sendError('No autorizado', 'Solo managers y administradores pueden rechazar créditos', 403);
        }

        $request->validate([
            'reason' => 'required|string|max:1000'
        ]);

        // Verificar que el crédito esté en estado pending_approval
        if ($credit->status !== 'pending_approval') {
            return $this->sendError('Estado inválido', 'El crédito no está pendiente de aprobación', 400);
        }

        $credit->update([
            'status' => 'rejected',
            'rejected_by' => $currentUser->id,
            'rejected_at' => now(),
            'rejection_reason' => $request->reason,
        ]);

        $credit->load(['client', 'createdBy']);

        // Disparar evento de rechazo
        event(new CreditWaitingListUpdate($credit, 'rejected', $currentUser));

        return $this->sendResponse($credit, 'Crédito rechazado exitosamente');
    }

    /**
     * Deliver credit to client (activate it)
     */
    public function deliver(Request $request, Credit $credit)
    {
        $currentUser = Auth::user();

        // Solo cobradores, managers y admins pueden entregar créditos
        if (!$currentUser->hasRole(['cobrador', 'manager', 'admin'])) {
            return $this->sendError('No autorizado', 'No tienes permisos para entregar créditos', 403);
        }

        $request->validate([
            'delivery_notes' => 'nullable|string|max:1000'
        ]);

        // Verificar que el crédito esté en estado waiting_delivery
        if ($credit->status !== 'waiting_delivery') {
            return $this->sendError('Estado inválido', 'El crédito no está esperando entrega', 400);
        }

        // Si es cobrador, verificar que el cliente esté asignado a él
        if ($currentUser->hasRole('cobrador')) {
            if ($credit->client->assigned_cobrador_id !== $currentUser->id) {
                return $this->sendError('No autorizado', 'No puedes entregar créditos de clientes que no tienes asignados', 403);
            }
        }

        $credit->update([
            'status' => 'active',
            'delivered_by' => $currentUser->id,
            'delivered_at' => now(),
            'delivery_notes' => $request->delivery_notes,
        ]);

        $credit->load(['client', 'createdBy', 'deliveredBy']);

        // Disparar evento de entrega
        event(new CreditWaitingListUpdate($credit, 'delivered', $currentUser));

        return $this->sendResponse($credit, 'Crédito entregado al cliente exitosamente');
    }

    /**
     * Reschedule delivery date
     */
    public function reschedule(Request $request, Credit $credit)
    {
        $currentUser = Auth::user();

        // Solo managers y admins pueden reprogramar entregas
        if (!$currentUser->hasRole(['manager', 'admin'])) {
            return $this->sendError('No autorizado', 'Solo managers y administradores pueden reprogramar entregas', 403);
        }

        $request->validate([
            'new_delivery_date' => 'required|date|after:now',
            'reason' => 'nullable|string|max:500'
        ]);

        // Verificar que el crédito esté en estado waiting_delivery
        if ($credit->status !== 'waiting_delivery') {
            return $this->sendError('Estado inválido', 'El crédito no está esperando entrega', 400);
        }

        $credit->update([
            'scheduled_delivery_date' => $request->new_delivery_date,
            'rescheduled_by' => $currentUser->id,
            'rescheduled_at' => now(),
            'reschedule_reason' => $request->reason,
        ]);

        $credit->load(['client', 'createdBy']);

        // Disparar evento de reprogramación
        event(new CreditWaitingListUpdate($credit, 'rescheduled', $currentUser));

        return $this->sendResponse($credit, 'Fecha de entrega reprogramada exitosamente');
    }
}
