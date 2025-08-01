<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use App\Models\User;
use App\Models\Credit;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class MapController extends BaseController
{
    /**
     * Obtener todos los clientes con sus ubicaciones y estados de pago
     */
    public function getClientsWithLocations(Request $request)
    {
        try {
            $query = User::role('client')
                ->with(['credits' => function ($query) {
                    $query->where('status', 'active');
                }, 'payments' => function ($query) {
                    $query->orderBy('payment_date', 'desc');
                }]);

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

            // Filtrar por cobrador si se especifica
            if ($request->has('cobrador_id')) {
                $cobradorId = $request->cobrador_id;
                $query->whereHas('clientRoutes.route', function ($q) use ($cobradorId) {
                    $q->where('cobrador_id', $cobradorId);
                });
            }

            $clients = $query->get()->map(function ($client) {
                // Calcular el estado general del cliente
                $hasOverduePayments = $client->payments->where('status', 'overdue')->count() > 0;
                $hasPendingPayments = $client->payments->where('status', 'pending')->count() > 0;
                $totalBalance = $client->credits->sum('balance');
                
                // Determinar el estado general
                $overallStatus = 'paid';
                if ($hasOverduePayments) {
                    $overallStatus = 'overdue';
                } elseif ($hasPendingPayments) {
                    $overallStatus = 'pending';
                }

                return [
                    'id' => $client->id,
                    'name' => $client->name,
                    'phone' => $client->phone,
                    'address' => $client->address,
                    'location' => $client->location,
                    'overall_status' => $overallStatus,
                    'total_balance' => $totalBalance,
                    'active_credits_count' => $client->credits->count(),
                    'overdue_payments_count' => $client->payments->where('status', 'overdue')->count(),
                    'pending_payments_count' => $client->payments->where('status', 'pending')->count(),
                    'paid_payments_count' => $client->payments->where('status', 'paid')->count(),
                    'credits' => $client->credits->map(function ($credit) {
                        return [
                            'id' => $credit->id,
                            'amount' => $credit->amount,
                            'balance' => $credit->balance,
                            'status' => $credit->status,
                            'start_date' => $credit->start_date,
                            'end_date' => $credit->end_date,
                        ];
                    }),
                    'recent_payments' => $client->payments->take(5)->map(function ($payment) {
                        return [
                            'id' => $payment->id,
                            'amount' => $payment->amount,
                            'payment_date' => $payment->payment_date,
                            'status' => $payment->status,
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
            $cobradorId = $request->cobrador_id;
            
            $query = User::role('client');
            
            if ($cobradorId) {
                $query->whereHas('clientRoutes.route', function ($q) use ($cobradorId) {
                    $q->where('cobrador_id', $cobradorId);
                });
            }

            $stats = [
                'total_clients' => $query->count(),
                'clients_with_location' => $query->whereNotNull('latitude')->whereNotNull('longitude')->count(),
                'clients_without_location' => $query->where(function($q) {
                    $q->whereNull('latitude')->orWhereNull('longitude');
                })->count(),
                'overdue_clients' => $query->whereHas('payments', function ($q) {
                    $q->where('status', 'overdue');
                })->count(),
                'pending_clients' => $query->whereHas('payments', function ($q) {
                    $q->where('status', 'pending');
                })->count(),
                'paid_clients' => $query->whereDoesntHave('payments', function ($q) {
                    $q->whereIn('status', ['pending', 'overdue']);
                })->count(),
                'total_balance' => $query->withSum('credits', 'balance')->get()->sum('credits_sum_balance'),
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
                'east' => 'required|numeric',
                'west' => 'required|numeric',
            ]);

            $north = $request->north;
            $south = $request->south;
            $east = $request->east;
            $west = $request->west;

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
                        'id' => $client->id,
                        'name' => $client->name,
                        'location' => $client->location,
                        'total_balance' => $client->credits->sum('balance'),
                        'has_overdue' => $client->payments->where('status', 'overdue')->count() > 0,
                        'has_pending' => $client->payments->where('status', 'pending')->count() > 0,
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
                        'id' => $cobrador->id,
                        'name' => $cobrador->name,
                        'routes' => $cobrador->routes->map(function ($route) {
                            return [
                                'id' => $route->id,
                                'name' => $route->name,
                                'description' => $route->description,
                                'clients' => $route->clients->map(function ($client) {
                                    return [
                                        'id' => $client->id,
                                        'name' => $client->name,
                                        'location' => $client->location,
                                        'total_balance' => $client->credits->sum('balance'),
                                        'has_overdue' => $client->payments->where('status', 'overdue')->count() > 0,
                                        'has_pending' => $client->payments->where('status', 'pending')->count() > 0,
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
} 