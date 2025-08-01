<?php

namespace App\Http\Controllers\Api;

use App\Models\Route;
use App\Models\User;
use Illuminate\Http\Request;

class RouteController extends BaseController
{
    /**
     * Display a listing of routes.
     */
    public function index(Request $request)
    {
        $routes = Route::with(['cobrador', 'clients'])
            ->when($request->cobrador_id, function ($query, $cobradorId) {
                $query->where('cobrador_id', $cobradorId);
            })
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->paginate(15);

        return $this->sendResponse($routes);
    }

    /**
     * Store a newly created route.
     */
    public function store(Request $request)
    {
        $request->validate([
            'cobrador_id' => 'required|exists:users,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'client_ids' => 'array',
            'client_ids.*' => 'exists:users,id',
        ]);

        $route = Route::create([
            'cobrador_id' => $request->cobrador_id,
            'name' => $request->name,
            'description' => $request->description,
        ]);

        if ($request->has('client_ids')) {
            $route->clients()->attach($request->client_ids);
        }

        $route->load(['cobrador', 'clients']);

        return $this->sendResponse($route, 'Ruta creada exitosamente');
    }

    /**
     * Display the specified route.
     */
    public function show(Route $route)
    {
        $route->load(['cobrador', 'clients']);
        return $this->sendResponse($route);
    }

    /**
     * Update the specified route.
     */
    public function update(Request $request, Route $route)
    {
        $request->validate([
            'cobrador_id' => 'required|exists:users,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'client_ids' => 'array',
            'client_ids.*' => 'exists:users,id',
        ]);

        $route->update([
            'cobrador_id' => $request->cobrador_id,
            'name' => $request->name,
            'description' => $request->description,
        ]);

        if ($request->has('client_ids')) {
            $route->clients()->sync($request->client_ids);
        }

        $route->load(['cobrador', 'clients']);

        return $this->sendResponse($route, 'Ruta actualizada exitosamente');
    }

    /**
     * Remove the specified route.
     */
    public function destroy(Route $route)
    {
        $route->delete();
        return $this->sendResponse([], 'Ruta eliminada exitosamente');
    }

    /**
     * Get routes by cobrador.
     */
    public function getByCobrador(User $cobrador)
    {
        $routes = $cobrador->routes()->with(['clients'])->get();
        return $this->sendResponse($routes);
    }

    /**
     * Get clients for route assignment.
     */
    public function getAvailableClients()
    {
        $clients = User::role('client')->get(['id', 'name', 'email']);
        return $this->sendResponse($clients);
    }
} 