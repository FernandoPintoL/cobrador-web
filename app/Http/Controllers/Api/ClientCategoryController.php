<?php

namespace App\Http\Controllers\Api;

use App\Models\ClientCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientCategoryController extends BaseController
{
    /**
     * Lista todas las categorías de cliente.
     */
    public function index()
    {
        $categories = ClientCategory::orderBy('code')->get();

        return $this->sendResponse($categories, 'Categorías obtenidas correctamente');
    }

    /**
     * Obtiene una categoría por su código.
     */
    public function show(string $code)
    {
        $category = ClientCategory::where('code', $code)->first();

        if (!$category) {
            return $this->sendError('Categoría no encontrada', [], 404);
        }

        return $this->sendResponse($category);
    }

    /**
     * Actualiza los límites de una categoría.
     * Solo managers y admins pueden actualizar.
     */
    public function updateLimits(Request $request, string $code)
    {
        $user = Auth::user();

        // Verificar permisos
        if (!$user->hasRole('admin') && !$user->hasRole('manager')) {
            return $this->sendError('Sin permisos', 'Solo administradores y managers pueden modificar límites', 403);
        }

        $category = ClientCategory::where('code', $code)->first();

        if (!$category) {
            return $this->sendError('Categoría no encontrada', [], 404);
        }

        $request->validate([
            'max_amount' => 'nullable|numeric|min:0',
            'min_amount' => 'nullable|numeric|min:0',
            'max_credits' => 'nullable|integer|min:0',
        ]);

        // Validar que min_amount no sea mayor que max_amount
        if ($request->has('min_amount') && $request->has('max_amount')) {
            if ($request->min_amount > $request->max_amount) {
                return $this->sendError(
                    'Validación fallida',
                    'El monto mínimo no puede ser mayor al monto máximo',
                    422
                );
            }
        }

        $category->update($request->only(['max_amount', 'min_amount', 'max_credits']));

        return $this->sendResponse($category, 'Límites de categoría actualizados correctamente');
    }

    /**
     * Obtiene estadísticas de clientes por categoría.
     */
    public function stats()
    {
        $categories = ClientCategory::withCount('users')->get();

        $stats = $categories->map(function ($category) {
            return [
                'code' => $category->code,
                'name' => $category->name,
                'clients_count' => $category->users_count,
                'limits' => [
                    'max_amount' => $category->max_amount,
                    'min_amount' => $category->min_amount,
                    'max_credits' => $category->max_credits,
                ],
            ];
        });

        return $this->sendResponse($stats, 'Estadísticas por categoría');
    }
}
