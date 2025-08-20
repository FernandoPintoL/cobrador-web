<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use App\Models\InterestRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InterestRateController extends BaseController
{
    public function index()
    {
        $rates = InterestRate::orderByDesc('is_active')->orderByDesc('id')->get();
        return $this->sendResponse($rates);
    }

    public function show(InterestRate $interestRate)
    {
        return $this->sendResponse($interestRate);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user || !($user->hasRole('manager') || $user->hasRole('admin'))) {
            return $this->sendError('No autorizado', 'Solo managers o administradores pueden crear tasas de interés', 403);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'rate' => 'required|numeric|min:0|max:100',
            'is_active' => 'boolean',
        ]);

        // Si se marca activa, desactivar las otras activas
        $isActive = $validated['is_active'] ?? false;
        if ($isActive) {
            InterestRate::where('is_active', true)->update(['is_active' => false]);
        }

        $rate = InterestRate::create([
            'name' => $validated['name'] ?? null,
            'rate' => $validated['rate'],
            'is_active' => $isActive,
            'created_by' => $user->id,
        ]);

        return $this->sendResponse($rate, 'Tasa creada correctamente');
    }

    public function update(Request $request, InterestRate $interestRate)
    {
        $user = Auth::user();
        if (!$user || !($user->hasRole('manager') || $user->hasRole('admin'))) {
            return $this->sendError('No autorizado', 'Solo managers o administradores pueden editar tasas de interés', 403);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'rate' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        // Manejar activación exclusiva
        if (array_key_exists('is_active', $validated) && $validated['is_active']) {
            InterestRate::where('is_active', true)->update(['is_active' => false]);
        }

        $interestRate->fill($validated);
        $interestRate->updated_by = $user->id;
        $interestRate->save();

        return $this->sendResponse($interestRate, 'Tasa actualizada correctamente');
    }

    public function destroy(InterestRate $interestRate)
    {
        $user = Auth::user();
        if (!$user || !($user->hasRole('manager') || $user->hasRole('admin'))) {
            return $this->sendError('No autorizado', 'Solo managers o administradores pueden eliminar tasas de interés', 403);
        }

        $interestRate->delete();
        return $this->sendResponse(null, 'Tasa eliminada correctamente');
    }

    public function active()
    {
        $active = InterestRate::getActive();
        return $this->sendResponse($active);
    }
}
