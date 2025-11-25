<?php
namespace App\Http\Controllers\Api;

use App\DTOs\LocationClusterDTO;
use App\Http\Controllers\Api\BaseController;
use App\Models\User;
use App\Services\LocationClusteringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class MapController extends BaseController
{
    /**
     * Obtener todos los clientes con sus ubicaciones y estados de pago
     */
    public function getClientsWithLocations(Request $request)
    {
        try {
            $currentUser = Auth::user();

            $query = User::role('client')
                ->with(['credits' => function ($query) {
                    $query->where('status', 'active');
                }, 'payments' => function ($query) {
                    $query->orderBy('payment_date', 'desc');
                }]);

            // Aplicar filtros según el rol del usuario autenticado
            if ($currentUser->hasRole('cobrador')) {
                // Los cobradores solo ven sus clientes asignados
                $query->where('assigned_cobrador_id', $currentUser->id);
            } elseif ($currentUser->hasRole('manager')) {
                // Los managers ven clientes directos + clientes de sus cobradores
                $cobradorIds = User::role('cobrador')
                    ->where('assigned_manager_id', $currentUser->id)
                    ->pluck('id');

                $query->where(function ($q) use ($currentUser, $cobradorIds) {
                    $q->where('assigned_manager_id', $currentUser->id) // Clientes directos
                        ->orWhereIn('assigned_cobrador_id', $cobradorIds); // Clientes de cobradores
                });
            }
            // Los admins pueden ver todos los clientes (sin filtro adicional)

            // Filtrar por estado de pago si se especifica
            if ($request->has('status')) {
                $status = $request->status;

                if ($status === 'overdue') {
                    $query->whereHas('payments', function ($q) {
                        $q->where('status', 'overdue');
                    });
                } elseif ($status === 'pending') {
                    $query->whereHas('payments', function ($q) {
                        $q->where('status', 'pending');
                    });
                } elseif ($status === 'paid') {
                    $query->whereDoesntHave('payments', function ($q) {
                        $q->whereIn('status', ['pending', 'overdue']);
                    });
                }
            }

            // Filtro manual por cobrador (solo para admins/managers)
            if ($request->has('cobrador_id') && ($currentUser->hasRole('admin') || $currentUser->hasRole('manager'))) {
                $cobradorId = $request->cobrador_id;
                $query->where('assigned_cobrador_id', $cobradorId);
            }

            $clients = $query->get()->map(function ($client) {
                // Calcular el estado general del cliente
                $hasOverduePayments = $client->payments->where('status', 'overdue')->count() > 0;
                $hasPendingPayments = $client->payments->where('status', 'pending')->count() > 0;
                $totalBalance       = $client->credits->sum('balance');

                // Determinar el estado general
                $overallStatus = 'paid';
                if ($hasOverduePayments) {
                    $overallStatus = 'overdue';
                } elseif ($hasPendingPayments) {
                    $overallStatus = 'pending';
                }

                return [
                    'id'                     => $client->id,
                    'name'                   => $client->name,
                    'phone'                  => $client->phone,
                    'address'                => $client->address,
                    'location'               => $client->location,
                    'overall_status'         => $overallStatus,
                    'total_balance'          => $totalBalance,
                    'active_credits_count'   => $client->credits->count(),
                    'overdue_payments_count' => $client->payments->where('status', 'overdue')->count(),
                    'pending_payments_count' => $client->payments->where('status', 'pending')->count(),
                    'paid_payments_count'    => $client->payments->where('status', 'paid')->count(),
                    'credits'                => $client->credits->map(function ($credit) {
                        return [
                            'id'         => $credit->id,
                            'amount'     => $credit->amount,
                            'balance'    => $credit->balance,
                            'status'     => $credit->status,
                            'start_date' => $credit->start_date,
                            'end_date'   => $credit->end_date,
                        ];
                    }),
                    'recent_payments'        => $client->payments->take(5)->map(function ($payment) {
                        return [
                            'id'             => $payment->id,
                            'amount'         => $payment->amount,
                            'payment_date'   => $payment->payment_date,
                            'status'         => $payment->status,
                            'payment_method' => $payment->payment_method,
                        ];
                    }),
                ];
            });

            return $this->sendResponse($clients, 'Clientes obtenidos exitosamente');
        } catch (\Exception $e) {
            return $this->sendError('Error al obtener clientes', $e->getMessage(), 500);
        }
    }

    /**
     * Obtener estadísticas del mapa
     */
    public function getMapStats(Request $request)
    {
        try {
            $currentUser = Auth::user();
            $query       = User::role('client');

            // Aplicar filtros según el rol del usuario autenticado
            if ($currentUser->hasRole('cobrador')) {
                // Los cobradores solo ven estadísticas de sus clientes asignados
                $query->where('assigned_cobrador_id', $currentUser->id);
            } elseif ($currentUser->hasRole('manager')) {
                // Los managers ven estadísticas de clientes directos + clientes de sus cobradores
                $cobradorIds = User::role('cobrador')
                    ->where('assigned_manager_id', $currentUser->id)
                    ->pluck('id');

                $query->where(function ($q) use ($currentUser, $cobradorIds) {
                    $q->where('assigned_manager_id', $currentUser->id) // Clientes directos
                        ->orWhereIn('assigned_cobrador_id', $cobradorIds); // Clientes de cobradores
                });
            }
            // Los admins pueden ver estadísticas de todos los clientes

            // Filtro manual por cobrador (solo para admins/managers)
            if ($request->has('cobrador_id') && ($currentUser->hasRole('admin') || $currentUser->hasRole('manager'))) {
                $cobradorId = $request->cobrador_id;
                $query->where('assigned_cobrador_id', $cobradorId);
            }

            $stats = [
                'total_clients'            => $query->count(),
                'clients_with_location'    => $query->whereNotNull('latitude')->whereNotNull('longitude')->count(),
                'clients_without_location' => $query->where(function ($q) {
                    $q->whereNull('latitude')->orWhereNull('longitude');
                })->count(),
                'overdue_clients'          => $query->whereHas('payments', function ($q) {
                    $q->where('status', 'overdue');
                })->count(),
                'pending_clients'          => $query->whereHas('payments', function ($q) {
                    $q->where('status', 'pending');
                })->count(),
                'paid_clients'             => $query->whereDoesntHave('payments', function ($q) {
                    $q->whereIn('status', ['pending', 'overdue']);
                })->count(),
                'total_balance'            => $query->withSum('credits', 'balance')->get()->sum('credits_sum_balance'),
            ];

            return $this->sendResponse($stats, 'Estadísticas obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->sendError('Error al obtener estadísticas', $e->getMessage(), 500);
        }
    }

    /**
     * Obtener clientes por zona geográfica
     */
    public function getClientsByArea(Request $request)
    {
        try {
            $request->validate([
                'north' => 'required|numeric',
                'south' => 'required|numeric',
                'east'  => 'required|numeric',
                'west'  => 'required|numeric',
            ]);

            $north = $request->north;
            $south = $request->south;
            $east  = $request->east;
            $west  = $request->west;

            $clients = User::role('client')
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->whereBetween('longitude', [$west, $east])
                ->whereBetween('latitude', [$south, $north])
                ->with(['credits' => function ($query) {
                    $query->where('status', 'active');
                }, 'payments'])
                ->get()
                ->map(function ($client) {
                    return [
                        'id'            => $client->id,
                        'name'          => $client->name,
                        'location'      => $client->location,
                        'total_balance' => $client->credits->sum('balance'),
                        'has_overdue'   => $client->payments->where('status', 'overdue')->count() > 0,
                        'has_pending'   => $client->payments->where('status', 'pending')->count() > 0,
                    ];
                });

            return $this->sendResponse($clients, 'Clientes en el área obtenidos exitosamente');
        } catch (\Exception $e) {
            return $this->sendError('Error al obtener clientes por área', $e->getMessage(), 500);
        }
    }

    /**
     * Obtener rutas de cobradores con sus clientes
     */
    public function getCobradorRoutes(Request $request)
    {
        try {
            $cobradores = User::role('cobrador')
                ->with(['routes.clients' => function ($query) {
                    $query->with(['credits' => function ($q) {
                        $q->where('status', 'active');
                    }, 'payments']);
                }])
                ->get()
                ->map(function ($cobrador) {
                    return [
                        'id'     => $cobrador->id,
                        'name'   => $cobrador->name,
                        'routes' => $cobrador->routes->map(function ($route) {
                            return [
                                'id'          => $route->id,
                                'name'        => $route->name,
                                'description' => $route->description,
                                'clients'     => $route->clients->map(function ($client) {
                                    return [
                                        'id'            => $client->id,
                                        'name'          => $client->name,
                                        'location'      => $client->location,
                                        'total_balance' => $client->credits->sum('balance'),
                                        'has_overdue'   => $client->payments->where('status', 'overdue')->count() > 0,
                                        'has_pending'   => $client->payments->where('status', 'pending')->count() > 0,
                                    ];
                                }),
                            ];
                        }),
                    ];
                });

            return $this->sendResponse($cobradores, 'Rutas de cobradores obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->sendError('Error al obtener rutas de cobradores', $e->getMessage(), 500);
        }
    }

    /**
     * Obtener solo las coordenadas de los clientes respetando roles
     */
    public function getClientCoordinates(Request $request)
    {
        try {
            $currentUser = Auth::user();

            $query = User::role('client')
                ->whereNotNull('latitude')
                ->whereNotNull('longitude');

            // Aplicar filtros según el rol del usuario autenticado
            if ($currentUser->hasRole('cobrador')) {
                // Los cobradores solo ven coordenadas de sus clientes asignados
                $query->where('assigned_cobrador_id', $currentUser->id);
            } elseif ($currentUser->hasRole('manager')) {
                // Los managers ven coordenadas de clientes directos + clientes de sus cobradores
                $cobradorIds = User::role('cobrador')
                    ->where('assigned_manager_id', $currentUser->id)
                    ->pluck('id');

                $query->where(function ($q) use ($currentUser, $cobradorIds) {
                    $q->where('assigned_manager_id', $currentUser->id) // Clientes directos
                        ->orWhereIn('assigned_cobrador_id', $cobradorIds); // Clientes de cobradores
                });
            }
            // Los admins pueden ver coordenadas de todos los clientes

            // Filtro manual por cobrador (solo para admins/managers)
            if ($request->has('cobrador_id') && ($currentUser->hasRole('admin') || $currentUser->hasRole('manager'))) {
                $cobradorId = $request->cobrador_id;
                $query->where('assigned_cobrador_id', $cobradorId);
            }

            $clients = $query->get(['id', 'name', 'latitude', 'longitude', 'address', 'phone'])
                ->map(function ($client) {
                    return [
                        'id'          => $client->id,
                        'name'        => $client->name,
                        'coordinates' => [
                            'latitude'  => (float) $client->latitude,
                            'longitude' => (float) $client->longitude,
                        ],
                        'address'     => $client->address,
                        'phone'       => $client->phone,
                    ];
                });

            return $this->sendResponse([
                'total_clients'    => $clients->count(),
                'clients'          => $clients,
                'user_role'        => $currentUser->roles->first()->name ?? 'unknown',
                'filtered_by_role' => $currentUser->hasRole('admin') ? 'none' : ($currentUser->hasRole('manager') ? 'manager' : 'cobrador'),
            ], 'Coordenadas de clientes obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->sendError('Error al obtener coordenadas de clientes', $e->getMessage(), 500);
        }
    }

    /**
     * ✅ ENDPOINT: Obtener clientes agrupados por ubicación (Clustering)
     *
     * PROPÓSITO:
     * Evitar múltiples marcadores iguales en el mapa cuando hay varias personas
     * en la misma casa con créditos. Agrupa por ubicación y proporciona información
     * detallada de todas las personas y créditos en esa ubicación.
     *
     * RESPUESTA JSON PRECISA PARA FLUTTER:
     * {
     *   "success": true,
     *   "data": [
     *     {
     *       "cluster_id": "19.4326,-99.1332",
     *       "location": {
     *         "latitude": 19.4326,
     *         "longitude": -99.1332,
     *         "address": "Calle Principal 123"
     *       },
     *       "cluster_summary": {
     *         "total_people": 3,
     *         "total_credits": 5,
     *         "total_amount": 5500.00,
     *         "total_balance": 3500.00,
     *         "overdue_count": 2,
     *         "overdue_amount": 1500.00,
     *         "active_count": 3,
     *         "active_amount": 2000.00,
     *         "completed_count": 0,
     *         "completed_amount": 0.00
     *       },
     *       "cluster_status": "overdue",
     *       "people": [
     *         {
     *           "person_id": 1,
     *           "name": "Juan García",
     *           "phone": "555-1234",
     *           "client_category": "A",
     *           "total_credits": 2,
     *           "total_balance": 1400.00,
     *           "person_status": "overdue",
     *           "credits": [...]
     *         }
     *       ]
     *     }
     *   ],
     *   "message": "Clusters de ubicaciones obtenidos exitosamente"
     * }
     *
     * FILTROS SOPORTADOS (query parameters):
     * - status: "overdue|pending|paid"
     * - cobrador_id: <user_id> (solo admin/manager)
     * - nombre: Búsqueda parcial por nombre del cliente (ej: "Juan")
     * - telefono: Búsqueda parcial por teléfono (ej: "555")
     * - ci: Búsqueda parcial por cédula/identificación (ej: "1234567")
     * - categoria_cliente: Categoría exacta (ej: "A"=VIP, "B"=Normal, "C"=Mal Cliente)
     *
     * EJEMPLOS DE USO:
     * GET /api/map/location-clusters
     * GET /api/map/location-clusters?status=overdue
     * GET /api/map/location-clusters?nombre=Juan
     * GET /api/map/location-clusters?telefono=555&categoria_cliente=A
     * GET /api/map/location-clusters?ci=1234567&cobrador_id=5
     *
     * PERMISOS:
     * - admin: Ve todos los clusters
     * - manager: Ve clusters de sus cobradores asignados
     * - cobrador: Ve solo sus clusters asignados
     */
    public function getLocationClusters(Request $request)
    {
        try {
            $currentUser = Auth::user();
            $service = new LocationClusteringService();

            // Preparar filtros desde query parameters
            $filters = [];

            // Filtro de estado de pago
            if ($request->has('status')) {
                $filters['status'] = $request->status;
            }

            // Filtro por cobrador (solo admin/manager pueden usar)
            if ($request->has('cobrador_id') && ($currentUser->hasRole('admin') || $currentUser->hasRole('manager'))) {
                $filters['cobrador_id'] = $request->cobrador_id;
            }

            // Filtros por datos del cliente
            if ($request->has('nombre')) {
                $filters['nombre'] = $request->nombre;
            }

            if ($request->has('telefono')) {
                $filters['telefono'] = $request->telefono;
            }

            if ($request->has('ci')) {
                $filters['ci'] = $request->ci;
            }

            if ($request->has('categoria_cliente')) {
                $filters['categoria_cliente'] = $request->categoria_cliente;
            }

            // Generar clusters
            $clusters = $service->generateLocationClusters($filters, $currentUser);

            // Convertir a array para respuesta JSON
            $clustersArray = $clusters->map(function (LocationClusterDTO $cluster) {
                return $cluster->toArray();
            })->values()->all();

            return $this->sendResponse(
                $clustersArray,
                'Clusters de ubicaciones obtenidos exitosamente'
            );
        } catch (\Exception $e) {
            return $this->sendError('Error al obtener clusters de ubicaciones', $e->getMessage(), 500);
        }
    }

    /**
     * Obtener clientes que se deben visitar hoy (para optimización de rutas)
     * Criterios: pagos vencidos, pagos que vencen hoy, o próximos a vencer (próximos 3 días)
     */
    public function getClientsToVisitToday(Request $request)
    {
        try {
            $currentUser = Auth::user();
            $today = now()->startOfDay();
            $next3Days = now()->addDays(3)->endOfDay();

            $query = User::role('client')
                ->with(['credits' => function ($query) {
                    $query->where('status', 'active')
                        ->with('payments');
                }]);

            // Aplicar filtros según el rol del usuario autenticado
            if ($currentUser->hasRole('cobrador')) {
                // Los cobradores solo ven sus clientes asignados
                $query->where('assigned_cobrador_id', $currentUser->id);
            } elseif ($currentUser->hasRole('manager')) {
                // Los managers pueden filtrar por cobrador o ver todos sus cobradores
                if ($request->has('cobrador_id')) {
                    $query->where('assigned_cobrador_id', $request->cobrador_id);
                } else {
                    // Ver clientes de todos sus cobradores
                    $cobradorIds = User::role('cobrador')
                        ->where('assigned_manager_id', $currentUser->id)
                        ->pluck('id');

                    $query->where(function ($q) use ($currentUser, $cobradorIds) {
                        $q->where('assigned_manager_id', $currentUser->id)
                            ->orWhereIn('assigned_cobrador_id', $cobradorIds);
                    });
                }
            }

            // Obtener solo clientes con ubicación
            $query->whereNotNull('latitude')
                ->whereNotNull('longitude');

            $clients = $query->get()->map(function ($client) use ($today, $next3Days) {
                // Calcular información de pagos
                $nextPayment = null;
                $nextPaymentDate = null;
                $nextPaymentAmount = 0;
                $nextInstallment = null;
                $hasOverdue = false;
                $overdueAmount = 0;
                $priority = 3; // 1 = alta (vencido), 2 = media (hoy), 3 = baja (próximo)

                foreach ($client->credits as $credit) {
                    foreach ($credit->payments as $payment) {
                        if ($payment->status === 'overdue') {
                            $hasOverdue = true;
                            $overdueAmount += $payment->amount;
                            $priority = min($priority, 1); // Máxima prioridad
                        }

                        if ($payment->status === 'pending') {
                            $paymentDate = \Carbon\Carbon::parse($payment->payment_date);

                            // Pago vence hoy
                            if ($paymentDate->isToday()) {
                                $priority = min($priority, 2);
                            }

                            // Encontrar el próximo pago (más cercano)
                            if ($paymentDate->between($today, $next3Days)) {
                                if (!$nextPaymentDate || $paymentDate->lt($nextPaymentDate)) {
                                    $nextPaymentDate = $paymentDate;
                                    $nextPaymentAmount = $payment->amount;
                                    $nextInstallment = $payment->installment_number;
                                    $nextPayment = $payment;
                                }
                            }
                        }
                    }
                }

                // Solo incluir clientes que tengan pagos vencidos o próximos
                if ($hasOverdue || $nextPayment) {
                    return [
                        'person_id' => $client->id,
                        'name' => $client->name,
                        'phone' => $client->phone ?? '',
                        'address' => $client->address ?? '',
                        'latitude' => (float) $client->latitude,
                        'longitude' => (float) $client->longitude,
                        'client_category' => $client->client_category ?? 'A',
                        'priority' => $priority, // 1 = urgente, 2 = hoy, 3 = próximo
                        'has_overdue' => $hasOverdue,
                        'overdue_amount' => $overdueAmount,
                        'next_payment_date' => $nextPaymentDate?->format('Y-m-d'),
                        'next_payment_amount' => $nextPaymentAmount,
                        'next_installment' => $nextInstallment,
                        'total_balance' => $client->credits->sum('balance'),
                    ];
                }

                return null;
            })->filter()->values(); // Eliminar nulos y reindexar

            return $this->sendResponse($clients, 'Clientes para visitar hoy obtenidos exitosamente');
        } catch (\Exception $e) {
            return $this->sendError('Error al obtener clientes para visitar', $e->getMessage(), 500);
        }
    }
}
