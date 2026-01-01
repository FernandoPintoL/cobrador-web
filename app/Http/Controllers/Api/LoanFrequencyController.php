<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoanFrequency;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Controlador para gestionar las frecuencias de pago disponibles.
 *
 * Permite al frontend obtener la configuración de frecuencias
 * habilitadas para el tenant actual.
 */
class LoanFrequencyController extends Controller
{
    /**
     * Obtener todas las frecuencias de pago habilitadas para el tenant actual.
     *
     * Endpoint: GET /api/loan-frequencies
     *
     * Retorna la configuración de frecuencias que el frontend usará para:
     * - Mostrar opciones de frecuencia en el formulario de crédito
     * - Auto-completar campos según la frecuencia seleccionada
     * - Validar rangos de cuotas permitidos
     * - Calcular estimaciones visuales
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();

        if (!$user || !$user->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no tiene tenant asignado',
            ], 403);
        }

        // Obtener frecuencias habilitadas para el tenant, ordenadas por período
        $frequencies = LoanFrequency::enabledForTenant($user->tenant_id)->get();

        // Formatear respuesta con información útil para el frontend
        $formattedFrequencies = $frequencies->map(function ($frequency) {
            return [
                'id' => $frequency->id,
                'code' => $frequency->code,
                'name' => $frequency->name,
                'description' => $frequency->description,
                'period_days' => $frequency->period_days,

                // Configuración de duración
                'is_fixed_duration' => $frequency->is_fixed_duration,
                'fixed_installments' => $frequency->fixed_installments,
                'fixed_duration_days' => $frequency->fixed_duration_days,

                // Configuración flexible
                'default_installments' => $frequency->default_installments,
                'min_installments' => $frequency->min_installments,
                'max_installments' => $frequency->max_installments,

                // Tasa de interés (si varía por frecuencia)
                'interest_rate' => $frequency->interest_rate,

                // Información adicional para UI
                'is_editable' => !$frequency->is_fixed_duration,
                'suggested_installments' => $frequency->is_fixed_duration
                    ? $frequency->fixed_installments
                    : $frequency->default_installments,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedFrequencies,
            'message' => 'Frecuencias de pago obtenidas exitosamente',
        ]);
    }

    /**
     * Obtener una frecuencia específica por código.
     *
     * Endpoint: GET /api/loan-frequencies/{code}
     *
     * @param string $code
     * @return JsonResponse
     */
    public function show(string $code): JsonResponse
    {
        $user = Auth::user();

        if (!$user || !$user->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no tiene tenant asignado',
            ], 403);
        }

        $frequency = LoanFrequency::findByCode($user->tenant_id, $code);

        if (!$frequency) {
            return response()->json([
                'success' => false,
                'message' => 'Frecuencia no encontrada',
            ], 404);
        }

        if (!$frequency->is_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Frecuencia no está habilitada',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $frequency,
            'message' => 'Frecuencia obtenida exitosamente',
        ]);
    }
}
