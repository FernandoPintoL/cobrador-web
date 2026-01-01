<?php

namespace App\Http\Controllers\Api;

use App\Models\Tenant;
use App\Models\TenantSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantSettingController extends BaseController
{
    /**
     * Display all settings for a tenant.
     */
    public function index($tenantId)
    {
        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);

        if (!$tenant) {
            return $this->sendError('Tenant no encontrado.', [], 404);
        }

        $settings = $tenant->settings()
            ->orderBy('key')
            ->get()
            ->map(function ($setting) {
                return [
                    'key' => $setting->key,
                    'value' => $this->parseValue($setting->value, $setting->type),
                    'type' => $setting->type,
                ];
            });

        return $this->sendResponse([
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'settings' => $settings,
        ], 'Configuraciones recuperadas exitosamente.');
    }

    /**
     * Get a specific setting for a tenant.
     */
    public function show($tenantId, $key)
    {
        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);

        if (!$tenant) {
            return $this->sendError('Tenant no encontrado.', [], 404);
        }

        $setting = $tenant->settings()->where('key', $key)->first();

        if (!$setting) {
            return $this->sendError("Configuración '{$key}' no encontrada.", [], 404);
        }

        return $this->sendResponse([
            'key' => $setting->key,
            'value' => $this->parseValue($setting->value, $setting->type),
            'type' => $setting->type,
        ], 'Configuración recuperada exitosamente.');
    }

    /**
     * Update or create a setting for a tenant.
     */
    public function store($tenantId, Request $request)
    {
        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);

        if (!$tenant) {
            return $this->sendError('Tenant no encontrado.', [], 404);
        }

        $validated = $request->validate([
            'key' => 'required|string|max:255',
            'value' => 'required',
            'type' => ['required', Rule::in(['string', 'integer', 'decimal', 'boolean', 'json'])],
        ]);

        // Validar el valor según el tipo
        $validation = $this->validateValueByType(
            $validated['value'],
            $validated['type']
        );

        if (!$validation['success']) {
            return $this->sendError(
                "El valor no coincide con el tipo '{$validated['type']}'.",
                [],
                400
            );
        }

        // Crear o actualizar setting
        $tenant->setSetting($validated['key'], $validation['value'], $validated['type']);

        // Recuperar el setting actualizado
        $setting = $tenant->settings()->where('key', $validated['key'])->first();

        return $this->sendResponse([
            'key' => $setting->key,
            'value' => $this->parseValue($setting->value, $setting->type),
            'type' => $setting->type,
        ], 'Configuración guardada exitosamente.');
    }

    /**
     * Update multiple settings at once.
     */
    public function bulkUpdate($tenantId, Request $request)
    {
        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);

        if (!$tenant) {
            return $this->sendError('Tenant no encontrado.', [], 404);
        }

        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string|max:255',
            'settings.*.value' => 'required',
            'settings.*.type' => ['required', Rule::in(['string', 'integer', 'decimal', 'boolean', 'json'])],
        ]);

        $updated = [];
        $errors = [];

        foreach ($validated['settings'] as $settingData) {
            // Validar el valor según el tipo
            $validation = $this->validateValueByType(
                $settingData['value'],
                $settingData['type']
            );

            if (!$validation['success']) {
                $errors[] = [
                    'key' => $settingData['key'],
                    'error' => "El valor no coincide con el tipo '{$settingData['type']}'.",
                ];
                continue;
            }

            // Crear o actualizar setting
            $tenant->setSetting($settingData['key'], $validation['value'], $settingData['type']);
            $updated[] = $settingData['key'];
        }

        $response = [
            'updated_count' => count($updated),
            'updated_keys' => $updated,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return $this->sendResponse(
            $response,
            count($updated) > 0
                ? 'Configuraciones actualizadas exitosamente.'
                : 'No se pudo actualizar ninguna configuración.'
        );
    }

    /**
     * Delete a specific setting.
     */
    public function destroy($tenantId, $key)
    {
        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);

        if (!$tenant) {
            return $this->sendError('Tenant no encontrado.', [], 404);
        }

        $setting = $tenant->settings()->where('key', $key)->first();

        if (!$setting) {
            return $this->sendError("Configuración '{$key}' no encontrada.", [], 404);
        }

        $setting->delete();

        return $this->sendResponse(
            ['key' => $key],
            'Configuración eliminada exitosamente.'
        );
    }

    /**
     * Get available setting keys with their descriptions.
     */
    public function availableSettings()
    {
        $settings = [
            [
                'key' => 'allow_custom_interest_per_credit',
                'description' => 'Permitir editar interés por crédito individual',
                'type' => 'boolean',
                'default' => true,
            ],
            [
                'key' => 'max_credits_per_client',
                'description' => 'Máximo de créditos activos por cliente',
                'type' => 'integer',
                'default' => 10,
            ],
            [
                'key' => 'default_interest_rate',
                'description' => 'Tasa de interés por defecto (%)',
                'type' => 'decimal',
                'default' => 10.0,
            ],
            [
                'key' => 'enable_notifications',
                'description' => 'Habilitar notificaciones del sistema',
                'type' => 'boolean',
                'default' => true,
            ],
            [
                'key' => 'enable_auto_logout_on_app_switch',
                'description' => 'Cerrar sesión automáticamente al cambiar de app',
                'type' => 'boolean',
                'default' => true,
            ],
            [
                'key' => 'require_photo_on_payment',
                'description' => 'Requerir foto al registrar pago',
                'type' => 'boolean',
                'default' => false,
            ],
            [
                'key' => 'allow_negative_cash_balance',
                'description' => 'Permitir balance de efectivo negativo',
                'type' => 'boolean',
                'default' => false,
            ],
            [
                'key' => 'auto_suspend_overdue_credits',
                'description' => 'Suspender automáticamente créditos vencidos',
                'type' => 'boolean',
                'default' => false,
            ],
            [
                'key' => 'grace_days_for_payment',
                'description' => 'Días de gracia para pagos vencidos',
                'type' => 'integer',
                'default' => 3,
            ],
            [
                'key' => 'company_email',
                'description' => 'Email de contacto de la empresa',
                'type' => 'string',
                'default' => '',
            ],
            [
                'key' => 'company_phone',
                'description' => 'Teléfono de contacto de la empresa',
                'type' => 'string',
                'default' => '',
            ],
            [
                'key' => 'company_address',
                'description' => 'Dirección física de la empresa',
                'type' => 'string',
                'default' => '',
            ],
            [
                'key' => 'allow_custom_payment_frequency',
                'description' => 'Permitir cambiar frecuencia de pago al crear crédito',
                'type' => 'boolean',
                'default' => false,
            ],
            [
                'key' => 'default_payment_frequency',
                'description' => 'Frecuencia de pago por defecto (semanal, quincenal, mensual)',
                'type' => 'string',
                'default' => 'mensual',
            ],
        ];

        return $this->sendResponse($settings, 'Configuraciones disponibles recuperadas exitosamente.');
    }

    /**
     * Parse value based on type.
     */
    private function parseValue($value, $type)
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'decimal' => (float) $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Validate value matches the specified type.
     * Returns array [success: bool, value: mixed]
     */
    private function validateValueByType($value, $type)
    {
        switch ($type) {
            case 'boolean':
                if (is_bool($value)) {
                    return ['success' => true, 'value' => $value];
                }
                if (in_array($value, [0, 1, '0', '1', 'true', 'false'], true)) {
                    return ['success' => true, 'value' => filter_var($value, FILTER_VALIDATE_BOOLEAN)];
                }
                return ['success' => false, 'value' => null];

            case 'integer':
                if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                    return ['success' => true, 'value' => (int) $value];
                }
                return ['success' => false, 'value' => null];

            case 'decimal':
                if (is_numeric($value)) {
                    return ['success' => true, 'value' => (float) $value];
                }
                return ['success' => false, 'value' => null];

            case 'json':
                if (is_array($value)) {
                    return ['success' => true, 'value' => $value];
                }
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    return json_last_error() === JSON_ERROR_NONE
                        ? ['success' => true, 'value' => $decoded]
                        : ['success' => false, 'value' => null];
                }
                return ['success' => false, 'value' => null];

            case 'string':
                return ['success' => true, 'value' => (string) $value];

            default:
                return ['success' => false, 'value' => null];
        }
    }
}
