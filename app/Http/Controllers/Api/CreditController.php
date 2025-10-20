<?php
namespace App\Http\Controllers\Api;

use App\Events\CreditCreated;
use App\Models\Credit;
use App\Models\InterestRate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CreditController extends BaseController
{
    /**
     * Display a listing of credits.
     */
    public function index(Request $request)
    {
        $query = Credit::with(['client', 'payments', 'createdBy']);

        // Ya no necesitamos la columna computada para total_paid, ahora usamos el campo de la tabla
        $query->select('credits.*');

        // Visibilidad x rol
        $currentUser = Auth::user();
        if ($currentUser) {
            if ($currentUser->hasRole('cobrador')) {
                // Créditos creados por el cobrador O de clientes asignados a él
                $query->where(function ($q) use ($currentUser) {
                    $q->where('created_by', $currentUser->id)
                        ->orWhereHas('client', function ($q2) use ($currentUser) {
                            $q2->where('assigned_cobrador_id', $currentUser->id);
                        });
                });
            } elseif ($currentUser->hasRole('manager')) {
                // Clientes directos del manager
                $directClientIds = User::whereHas('roles', function ($q) {
                    $q->where('name', 'client');
                })
                    ->where('assigned_manager_id', $currentUser->id)
                    ->pluck('id')->toArray();

                // Cobradores bajo el manager
                $cobradorIds = User::role('cobrador')
                    ->where('assigned_manager_id', $currentUser->id)
                    ->pluck('id')->toArray();

                // Clientes de esos cobradores
                $cobradorClientIds = User::whereHas('roles', function ($q) {
                    $q->where('name', 'client');
                })
                    ->whereIn('assigned_cobrador_id', $cobradorIds)
                    ->pluck('id')->toArray();

                $allClientIds = array_unique(array_merge($directClientIds, $cobradorClientIds));

                // Créditos creados por el manager, por sus cobradores, O de sus clientes (directos o vía cobradores)
                $query->where(function ($q) use ($currentUser, $allClientIds, $cobradorIds) {
                    $q->where('created_by', $currentUser->id)
                        ->orWhereIn('created_by', $cobradorIds)
                        ->orWhereIn('client_id', $allClientIds);
                });
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
                    $q->orWhere('ci', 'like', "%{$search}%");
                    $q->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($request->cobrador_id, function ($query, $cobradorId) {
                // Solo admins y managers pueden filtrar por cobrador específico
                if (Auth::user() && (Auth::user()->hasRole('admin') || Auth::user()->hasRole('manager'))) {
                    $query->whereHas('client', function ($q) use ($cobradorId) {
                        $q->where('assigned_cobrador_id', $cobradorId);
                    });
                }
            })
        // Frecuencia (uno o varios valores separados por coma)
            ->when($request->frequency, function ($query, $frequency) {
                $values = is_array($frequency) ? $frequency : explode(',', (string) $frequency);
                $values = array_filter(array_map('trim', $values));
                if (! empty($values)) {
                    $query->whereIn('frequency', $values);
                }
            })
        // Rango de fechas (inicio y fin)
            ->when($request->start_date_from, function ($query, $date) {
                $query->whereDate('start_date', '>=', $date);
            })
            ->when($request->start_date_to, function ($query, $date) {
                $query->whereDate('start_date', '<=', $date);
            })
            ->when($request->end_date_from, function ($query, $date) {
                $query->whereDate('end_date', '>=', $date);
            })
            ->when($request->end_date_to, function ($query, $date) {
                $query->whereDate('end_date', '<=', $date);
            })
        // Rango de montos (amount, total_amount, balance)
            ->when($request->amount_min, function ($query, $value) {
                $query->where('amount', '>=', (float) $value);
            })
            ->when($request->amount_max, function ($query, $value) {
                $query->where('amount', '<=', (float) $value);
            })
            ->when($request->total_amount_min, function ($query, $value) {
                $query->where('total_amount', '>=', (float) $value);
            })
            ->when($request->total_amount_max, function ($query, $value) {
                $query->where('total_amount', '<=', (float) $value);
            })
            ->when($request->balance_min, function ($query, $value) {
                $query->where('balance', '>=', (float) $value);
            })
            ->when($request->balance_max, function ($query, $value) {
                $query->where('balance', '<=', (float) $value);
            })
        // Filtros por total pagado (ahora usando el campo de la tabla)
            ->when($request->total_paid_min, function ($q, $v) {
                $q->where("total_paid", '>=', (float) $v);
            })
            ->when($request->total_paid_max, function ($q, $v) {
                $q->where("total_paid", '<=', (float) $v);
            });

        $perPage = (int) ($request->get('per_page', 15));
        $perPage = $perPage > 0 ? $perPage : 15;
        $credits = $query->paginate($perPage);

        // Enriquecer con métricas de cuotas: solo pagadas y pendientes
        $credits->getCollection()->transform(function ($credit) {
            $completedInstallments = (int) $credit->getCompletedInstallmentsCount();
            $credit->setAttribute('completed_installments_count', $completedInstallments);

            $totalInstallments = (int) ($credit->total_installments ?? $credit->calculateTotalInstallments());
            $pending           = max($totalInstallments - $completedInstallments, 0);
            $credit->setAttribute('pending_installments', $pending);

            return $credit;
        });

        return $this->sendResponse($credits);
    }

    /**
     * Store a newly created credit.
     */
    public function store(Request $request)
    {
        $request->validate([
            'client_id'                    => 'required|exists:users,id',
            'cobrador_id'                  => 'nullable|exists:users,id', // Para que managers puedan especificar el cobrador
            'amount'                       => 'required|numeric|min:0',
            'balance'                      => 'required|numeric|min:0',
            'frequency'                    => 'required|in:daily,weekly,biweekly,monthly',
            'start_date'                   => 'required|date',
            'end_date'                     => 'required|date|after:start_date',
            'status'                       => 'in:pending_approval,waiting_delivery,active,completed,defaulted,cancelled',
            'scheduled_delivery_date'      => 'nullable|date|after_or_equal:today', // Permitir misma día si el manager lo autoriza
            'immediate_delivery_requested' => 'sometimes|boolean',
            'interest_rate_id'             => 'nullable|exists:interest_rates,id',
            'total_installments'           => 'nullable|integer|min:1',
            'latitude'                     => 'nullable|numeric|between:-90,90',
            'longitude'                    => 'nullable|numeric|between:-180,180',
        ]);

        $currentUser = Auth::user();

        // Verificar que el cliente exista y tenga rol de cliente
        $client = User::findOrFail($request->client_id);
        if (! $client->hasRole('client')) {
            return $this->sendError('Cliente no válido', 'El usuario especificado no es un cliente', 400);
        }

        // Validar cobrador_id si se proporciona
        $targetCobrador = null;
        if ($request->has('cobrador_id')) {
            $targetCobrador = User::findOrFail($request->cobrador_id);
            if (! $targetCobrador->hasRole('cobrador')) {
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
            $isDirectlyAssigned        = $client->assigned_manager_id === $currentUser->id;
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
                if (! $isDirectlyAssigned && ! $isAssignedThroughCobrador) {
                    return $this->sendError('No autorizado', 'El cliente debe estar asignado directamente a ti o a un cobrador bajo tu supervisión', 403);
                }
            }
        }
        // Los admins pueden crear créditos para cualquier combinación cliente-cobrador

                                                    // 1) Reglas por ranking del cliente (A,B,C): y bloqueo total para categoría C
        $clientCategory = $client->client_category; // Puede ser null

        // Validación de categoría desde el modelo
        if (! $client->canReceiveNewCredit()) {
            return $this->sendError(
                'Cliente en categoría C',
                $client->creditCreationBlockedReason() ?? 'No se pueden asignar nuevos créditos según la categoría del cliente.',
                422
            );
        }

        // Límites generales por categoría (A/B)
        $categoryLimits = [
            'A' => ['min_amount' => 0, 'max_amount' => 10000, 'max_credits' => 5],
            'B' => ['min_amount' => 0, 'max_amount' => 5000, 'max_credits' => 3],
        ];
        if ($clientCategory && isset($categoryLimits[$clientCategory])) {
            $limits = $categoryLimits[$clientCategory];
            // Validar monto
            if ($request->amount < $limits['min_amount'] || $request->amount > $limits['max_amount']) {
                return $this->sendError(
                    'Monto no permitido por ranking',
                    "Para clientes categoría {$clientCategory}, el monto debe estar entre {$limits['min_amount']} y {$limits['max_amount']}",
                    422
                );
            }
            // Validar cantidad de créditos (pendientes/por entregar/activos)
            $engagedStatuses = ['pending_approval', 'waiting_delivery', 'active'];
            $currentEngaged  = Credit::where('client_id', $client->id)
                ->whereIn('status', $engagedStatuses)
                ->count();
            if ($currentEngaged >= $limits['max_credits']) {
                return $this->sendError(
                    'Límite de créditos alcanzado',
                    "El cliente ya tiene {$currentEngaged} créditos en proceso/activos; máximo permitido para categoría {$clientCategory} es {$limits['max_credits']}",
                    422
                );
            }
        }

        // 2) Determinar el estado inicial del crédito, con fast-track para manager con cliente directo
        $isDirectClientOfManager = $currentUser->hasRole('manager') && ($client->assigned_manager_id === $currentUser->id);
        $forceWaitingDelivery    = false;

        $initialStatus = 'active'; // Por defecto para compatibilidad
        if ($request->has('status')) {
            $initialStatus = $request->status;
        } elseif ($currentUser->hasRole('manager') || $currentUser->hasRole('cobrador')) {
            // Los managers y cobradores crean créditos en lista de espera por defecto
            $initialStatus = 'pending_approval';
        }
        // Fast-track: si manager crea a cliente directo, saltar aprobación
        if ($isDirectClientOfManager) {
            $initialStatus        = 'waiting_delivery';
            $forceWaitingDelivery = true;
        }

        // Determinar tasa de interés según rol y parámetros
        $interestRateId    = null;
        $interestRateValue = 0.0;
        if ($currentUser->hasRole('cobrador')) {
            $activeRate = InterestRate::getActive();
            if ($activeRate) {
                $interestRateId    = $activeRate->id;
                $interestRateValue = (float) $activeRate->rate;
            }
        } else {
            if ($request->filled('interest_rate_id')) {
                $rateModel = InterestRate::find($request->interest_rate_id);
                if ($rateModel) {
                    $interestRateId    = $rateModel->id;
                    $interestRateValue = (float) $rateModel->rate;
                }
            } elseif ($request->filled('interest_rate')) {
                $interestRateValue = (float) $request->interest_rate;
            } else {
                // Fallback a la activa si existe
                $activeRate = InterestRate::getActive();
                if ($activeRate) {
                    $interestRateId    = $activeRate->id;
                    $interestRateValue = (float) $activeRate->rate;
                }
            }
        }

        // Preparar fechas considerando fast-track
        $startDate             = $request->start_date;
        $endDate               = $request->end_date;
        $scheduledDeliveryDate = $request->scheduled_delivery_date;
        if ($forceWaitingDelivery) {
            $approvedAtNow = now();
            $startDate     = (clone $approvedAtNow)->addDay();
            // Ajustar end_date si no es posterior a start_date
            try {
                $endDt   = new \DateTime($endDate);
                $startDt = new \DateTime($startDate);
                if ($endDt <= $startDt) {
                    $endDt   = (clone $startDt)->modify('+30 days');
                    $endDate = $endDt->format('Y-m-d');
                }
            } catch (\Exception $e) {
                $endDate = (new \DateTime($startDate))->modify('+30 days')->format('Y-m-d');
            }
            $scheduledDeliveryDate = $scheduledDeliveryDate ?? (clone $approvedAtNow)->addDay();
        }

        $credit = Credit::create([
            'client_id'                    => $request->client_id,
            'created_by'                   => $currentUser->id,
            'amount'                       => $request->amount,
            'interest_rate_id'             => $interestRateId,
            'interest_rate'                => $interestRateValue,
            'total_amount'                 => $request->total_amount ?? $request->amount,
            'balance'                      => $request->balance,
            'installment_amount'           => $request->installment_amount, // si no se envía, el modelo lo calculará
            'total_installments'           => $request->total_installments ?? 24,
            'frequency'                    => $request->frequency,
            'start_date'                   => $startDate,
            'end_date'                     => $endDate,
            'status'                       => $initialStatus,
            'scheduled_delivery_date'      => $scheduledDeliveryDate, // Para managers
            'immediate_delivery_requested' => (bool) $request->boolean('immediate_delivery_requested'),
            'latitude'                     => $request->latitude,
            'longitude'                    => $request->longitude,
        ]);

        // Si se aplicó fast-track, setear aprobaciones automáticamente
        if ($forceWaitingDelivery) {
            $credit->update([
                'approved_by' => $currentUser->id,
                'approved_at' => now(),
            ]);
        }

        $credit->load(['client', 'payments', 'createdBy']);

        // Disparar evento si el crédito fue creado en lista de espera
        if ($initialStatus === 'pending_approval') {
            // Determinar quién es el manager y el cobrador para las notificaciones
            $cobrador = null;
            $manager  = null;

            if ($currentUser->hasRole('cobrador')) {
                $cobrador = $currentUser;
                $manager  = $currentUser->assignedManager; // El manager del cobrador
            } elseif ($currentUser->hasRole('manager')) {
                $manager  = $currentUser;
                $cobrador = $client->assignedCobrador; // El cobrador asignado al cliente
            }

            // Disparar evento solo si tenemos los datos necesarios
            if ($cobrador && $manager) {
                event(new CreditCreated($credit, $manager, $cobrador));
            }
        }

        $message = 'Crédito creado exitosamente';
        if ($initialStatus === 'pending_approval') {
            $message .= ' (en lista de espera para aprobación)';
        } elseif ($initialStatus === 'waiting_delivery' && $forceWaitingDelivery) {
            $message .= ' (bypass de aprobación por manager: listo para entrega)';
        }

        return $this->sendResponse($credit, $message);
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

        // Métricas: cuotas pagadas y pendientes
        $completedInstallments = (int) $credit->getCompletedInstallmentsCount();
        $credit->setAttribute('completed_installments_count', $completedInstallments);

        $totalInstallments = (int) ($credit->total_installments ?? $credit->calculateTotalInstallments());
        $pending           = max($totalInstallments - $completedInstallments, 0);
        $credit->setAttribute('pending_installments', $pending);

        return $this->sendResponse($credit);
    }

    /**
     * Update the specified credit.
     */
    public function update(Request $request, Credit $credit)
    {
        $request->validate([
            'client_id'  => 'required|exists:users,id',
            'amount'     => 'required|numeric|min:0',
            'balance'    => 'required|numeric|min:0',
            'frequency'  => 'required|in:daily,weekly,biweekly,monthly',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
            'status'     => 'in:active,completed,defaulted,cancelled',
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

        // Validación de categoría desde el modelo (update/reasignación)
        $targetClient = User::findOrFail($request->client_id);
        if (! $targetClient->canReceiveNewCredit()) {
            return $this->sendError(
                'Cliente en categoría C',
                $targetClient->creditCreationBlockedReason() ?? 'No se pueden asignar o actualizar créditos según la categoría del cliente.',
                422
            );
        }

        $credit->update([
            'client_id'  => $request->client_id,
            'amount'     => $request->amount,
            'balance'    => $request->balance,
            'frequency'  => $request->frequency,
            'start_date' => $request->start_date,
            'end_date'   => $request->end_date,
            'status'     => $request->status,
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
    public function getByClient(Request $request, User $client)
    {
        $currentUser = Auth::user();

        // Verificar que el usuario especificado sea un cliente
        if (! $client->hasRole('client')) {
            return $this->sendError('Usuario no válido', 'El usuario especificado no es un cliente', 400);
        }

        // Autorización por rol
        if ($currentUser->hasRole('cobrador')) {
            if ($client->assigned_cobrador_id !== $currentUser->id) {
                return $this->sendError('No autorizado', 'No tienes acceso a los créditos de este cliente', 403);
            }
        } elseif ($currentUser->hasRole('manager')) {
            // El manager debe ser el asignado directo del cliente o el manager del cobrador asignado al cliente
            $isDirect      = $client->assigned_manager_id === $currentUser->id;
            $isViaCobrador = $client->assigned_cobrador_id && User::where('id', $client->assigned_cobrador_id)
                ->where('assigned_manager_id', $currentUser->id)
                ->exists();
            if (! ($isDirect || $isViaCobrador)) {
                return $this->sendError('No autorizado', 'Cliente no pertenece a tu equipo', 403);
            }
        }

        $query = $client->credits()->with(['payments', 'createdBy']);

        // Selección básica; las métricas de cuotas se calculan abajo por cada crédito
        $query->select('credits.*');

        // Filtros
        $query->when($request->status, function ($q, $status) {
            $q->where('status', $status);
        })
            ->when($request->frequency, function ($q, $frequency) {
                $values = is_array($frequency) ? $frequency : explode(',', (string) $frequency);
                $values = array_filter(array_map('trim', $values));
                if (! empty($values)) {
                    $q->whereIn('frequency', $values);
                }
            })
            ->when($request->start_date_from, function ($q, $date) {
                $q->whereDate('start_date', '>=', $date);
            })
            ->when($request->start_date_to, function ($q, $date) {
                $q->whereDate('start_date', '<=', $date);
            })
            ->when($request->end_date_from, function ($q, $date) {
                $q->whereDate('end_date', '>=', $date);
            })
            ->when($request->end_date_to, function ($q, $date) {
                $q->whereDate('end_date', '<=', $date);
            })
            ->when($request->amount_min, function ($q, $value) {
                $q->where('amount', '>=', (float) $value);
            })
            ->when($request->amount_max, function ($q, $value) {
                $q->where('amount', '<=', (float) $value);
            })
            ->when($request->balance_min, function ($q, $value) {
                $q->where('balance', '>=', (float) $value);
            })
            ->when($request->balance_max, function ($q, $value) {
                $q->where('balance', '<=', (float) $value);
            });

        $perPage = (int) ($request->get('per_page', 50));
        $perPage = $perPage > 0 ? $perPage : 50;
        $credits = $query->paginate($perPage);

        // Enriquecer con métricas reales de cuotas: solo pagadas y pendientes
        $credits->getCollection()->transform(function ($credit) {
            $completedInstallments = (int) $credit->getCompletedInstallmentsCount();
            $credit->setAttribute('completed_installments_count', $completedInstallments);
            $totalInstallments = (int) ($credit->total_installments ?? $credit->calculateTotalInstallments());
            $pending           = max($totalInstallments - $completedInstallments, 0);
            $credit->setAttribute('pending_installments', $pending);

            return $credit;
        });

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

        // Usando getPendingInstallments en lugar de getRemainingInstallments que fue eliminado
        $remaining = $credit->getPendingInstallments();

        return $this->sendResponse(['remaining_installments' => $remaining]);
    }

    /**
     * Get credits by cobrador (for admins and managers).
     */
    public function getByCobrador(Request $request, User $cobrador)
    {
        $currentUser = Auth::user();

        // Solo admins y managers pueden usar este endpoint
        if (! ($currentUser->hasRole('admin') || $currentUser->hasRole('manager'))) {
            return $this->sendError('No autorizado', 'No tienes permisos para realizar esta acción', 403);
        }

        // Verificar que el usuario especificado sea un cobrador
        if (! $cobrador->hasRole('cobrador')) {
            return $this->sendError('Usuario no válido', 'El usuario especificado no es un cobrador', 400);
        }

        $query = Credit::with(['client', 'payments', 'createdBy'])
            ->whereHas('client', function ($q) use ($cobrador) {
                $q->where('assigned_cobrador_id', $cobrador->id);
            });

        // Selección básica; las métricas de cuotas se calculan abajo
        $query->select('credits.*');

        // Filtros adicionales
        $query->when($request->status, function ($query, $status) {
            $query->where('status', $status);
        })
            ->when($request->search, function ($query, $search) {
                $query->whereHas('client', function ($q) use ($search) {
                    $q->where(function ($qq) use ($search) {
                        $qq->where('name', 'like', "%{$search}%")
                            ->orWhere('ci', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
                });
            })
            ->when($request->frequency, function ($query, $frequency) {
                $values = is_array($frequency) ? $frequency : explode(',', (string) $frequency);
                $values = array_filter(array_map('trim', $values));
                if (! empty($values)) {
                    $query->whereIn('frequency', $values);
                }
            })
            ->when($request->start_date_from, function ($query, $date) {
                $query->whereDate('start_date', '>=', $date);
            })
            ->when($request->start_date_to, function ($query, $date) {
                $query->whereDate('start_date', '<=', $date);
            })
            ->when($request->end_date_from, function ($query, $date) {
                $query->whereDate('end_date', '>=', $date);
            })
            ->when($request->end_date_to, function ($query, $date) {
                $query->whereDate('end_date', '<=', $date);
            })
            ->when($request->amount_min, function ($query, $value) {
                $query->where('amount', '>=', (float) $value);
            })
            ->when($request->amount_max, function ($query, $value) {
                $query->where('amount', '<=', (float) $value);
            })
            ->when($request->balance_min, function ($query, $value) {
                $query->where('balance', '>=', (float) $value);
            })
            ->when($request->balance_max, function ($query, $value) {
                $query->where('balance', '<=', (float) $value);
            });

        $perPage = (int) ($request->get('per_page', 15));
        $perPage = $perPage > 0 ? $perPage : 15;
        $credits = $query->paginate($perPage);

        // Enriquecer con métricas reales de cuotas: solo pagadas y pendientes
        $credits->getCollection()->transform(function ($credit) {
            $completedInstallments = (int) $credit->getCompletedInstallmentsCount();
            $credit->setAttribute('completed_installments_count', $completedInstallments);
            $totalInstallments = (int) ($credit->total_installments ?? $credit->calculateTotalInstallments());
            $pending           = max($totalInstallments - $completedInstallments, 0);
            $credit->setAttribute('pending_installments', $pending);

            return $credit;
        });

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
        if (! ($currentUser->hasRole('admin') || $currentUser->hasRole('manager') || $currentUser->id === $cobrador->id)) {
            return $this->sendError('No autorizado', 'No tienes permisos para realizar esta acción', 403);
        }

        // Verificar que el usuario especificado sea un cobrador
        if (! $cobrador->hasRole('cobrador')) {
            return $this->sendError('Usuario no válido', 'El usuario especificado no es un cobrador', 400);
        }

        $stats = [
            'total_credits'     => Credit::whereHas('client', function ($q) use ($cobrador) {
                $q->where('assigned_cobrador_id', $cobrador->id);
            })->count(),

            'active_credits'    => Credit::whereHas('client', function ($q) use ($cobrador) {
                $q->where('assigned_cobrador_id', $cobrador->id);
            })->where('status', 'active')->count(),

            'completed_credits' => Credit::whereHas('client', function ($q) use ($cobrador) {
                $q->where('assigned_cobrador_id', $cobrador->id);
            })->where('status', 'completed')->count(),

            'defaulted_credits' => Credit::whereHas('client', function ($q) use ($cobrador) {
                $q->where('assigned_cobrador_id', $cobrador->id);
            })->where('status', 'defaulted')->count(),

            'total_amount'      => Credit::whereHas('client', function ($q) use ($cobrador) {
                $q->where('assigned_cobrador_id', $cobrador->id);
            })->sum('amount'),

            'total_balance'     => Credit::whereHas('client', function ($q) use ($cobrador) {
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
        if (! ($currentUser->hasRole('admin') || ($currentUser->hasRole('manager') && $currentUser->id === $manager->id))) {
            return $this->sendError('No autorizado', 'No tienes permisos para realizar esta acción', 403);
        }
        if (! $manager->hasRole('manager')) {
            return $this->sendError('Usuario no válido', 'El usuario especificado no es un manager', 400);
        }
        // Obtener IDs de clientes directos
        $directClientIds = User::whereHas('roles', function ($q) {
            $q->where('name', 'client');
        })
            ->where('assigned_manager_id', $manager->id)
            ->pluck('id')->toArray();
        // Cobradores asignados al manager
        $cobradorIds = User::role('cobrador')->where('assigned_manager_id', $manager->id)->pluck('id')->toArray();
        // Clientes de los cobradores
        $cobradorClientIds = User::whereHas('roles', function ($q) {
            $q->where('name', 'client');
        })
            ->whereIn('assigned_cobrador_id', $cobradorIds)
            ->pluck('id')->toArray();
        // Unir todos los clientes
        $allClientIds = array_unique(array_merge($directClientIds, $cobradorClientIds));
        // Estadísticas
        $stats = [
            'total_credits'     => Credit::whereIn('client_id', $allClientIds)->count(),
            'active_credits'    => Credit::whereIn('client_id', $allClientIds)->where('status', 'active')->count(),
            'completed_credits' => Credit::whereIn('client_id', $allClientIds)->where('status', 'completed')->count(),
            'defaulted_credits' => Credit::whereIn('client_id', $allClientIds)->where('status', 'defaulted')->count(),
            'total_amount'      => Credit::whereIn('client_id', $allClientIds)->sum('amount'),
            'total_balance'     => Credit::whereIn('client_id', $allClientIds)->sum('balance'),
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
     * DEBUG: Método temporal para debuggear problema de listado de cobrador
     */
    public function debugCobradorCredits(Request $request)
    {
        $currentUser = Auth::user();

        if (! $currentUser) {
            return response()->json([
                'error' => 'Usuario no autenticado',
                'debug' => [
                    'auth_user' => null,
                    'headers'   => $request->headers->all(),
                ],
            ], 401);
        }

        $debugInfo = [
            'authenticated_user' => [
                'id'    => $currentUser->id,
                'name'  => $currentUser->name,
                'email' => $currentUser->email,
                'roles' => $currentUser->roles->pluck('name')->toArray(),
            ],
            'request_params'     => $request->all(),
            'is_cobrador'        => $currentUser->hasRole('cobrador'),
        ];

        if (! $currentUser->hasRole('cobrador')) {
            return response()->json([
                'error' => 'Usuario no es cobrador',
                'debug' => $debugInfo,
            ], 403);
        }

        // Ejecutar la misma consulta que en index()
        $query = Credit::with(['client', 'payments', 'createdBy'])
            ->where(function ($q) use ($currentUser) {
                $q->where('created_by', $currentUser->id)
                    ->orWhereHas('client', function ($q2) use ($currentUser) {
                        $q2->where('assigned_cobrador_id', $currentUser->id);
                    });
            });

        // Aplicar filtros si vienen en la request
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $credits = $query->get();

        $debugInfo['query_results'] = [
            'total_found' => $credits->count(),
            'credits'     => $credits->map(function ($credit) use ($currentUser) {
                return [
                    'id'                          => $credit->id,
                    'client_name'                 => $credit->client->name,
                    'amount'                      => $credit->amount,
                    'status'                      => $credit->status,
                    'created_by'                  => $credit->created_by,
                    'created_by_name'             => $credit->createdBy->name ?? 'N/A',
                    'client_assigned_cobrador_id' => $credit->client->assigned_cobrador_id,
                    'matches_created_by'          => $credit->created_by == $currentUser->id,
                    'matches_assigned_client'     => $credit->client->assigned_cobrador_id == $currentUser->id,
                ];
            }),
        ];

        // También obtener info adicional del cobrador
        $debugInfo['cobrador_info'] = [
            'assigned_clients_count'   => $currentUser->assignedClients()->count(),
            'assigned_clients'         => $currentUser->assignedClients()->pluck('name', 'id')->toArray(),
            'credits_created_directly' => Credit::where('created_by', $currentUser->id)->count(),
        ];

        return response()->json([
            'success' => true,
            'debug'   => $debugInfo,
        ]);
    }
}
