<?php

namespace App\Http\Controllers\Api;

use App\Models\Credit;
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
        
        // Si el usuario es cobrador, solo mostrar créditos de sus clientes asignados
        $currentUser = Auth::user();
        if ($currentUser && $currentUser->hasRole('cobrador')) {
            $query->whereHas('client', function ($q) use ($currentUser) {
                $q->where('assigned_cobrador_id', $currentUser->id);
            });
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
            'amount' => 'required|numeric|min:0',
            'balance' => 'required|numeric|min:0',
            'frequency' => 'required|in:daily,weekly,biweekly,monthly',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'status' => 'in:active,completed,defaulted,cancelled',
        ]);

        $currentUser = Auth::user();
        
        // Verificar que el cliente exista y tenga rol de cliente
        $client = User::findOrFail($request->client_id);
        if (!$client->hasRole('client')) {
            return $this->sendError('Cliente no válido', 'El usuario especificado no es un cliente', 400);
        }
        
        // Si el usuario es cobrador, verificar que el cliente esté asignado a él
        if ($currentUser->hasRole('cobrador')) {
            if ($client->assigned_cobrador_id !== $currentUser->id) {
                return $this->sendError('No autorizado', 'No puedes crear créditos para clientes que no tienes asignados', 403);
            }
        }

        $credit = Credit::create([
            'client_id' => $request->client_id,
            'created_by' => $currentUser->id,
            'amount' => $request->amount,
            'balance' => $request->balance,
            'frequency' => $request->frequency,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'status' => $request->status ?? 'active',
        ]);

        $credit->load(['client', 'payments', 'createdBy']);

        return $this->sendResponse($credit, 'Crédito creado exitosamente');
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
} 