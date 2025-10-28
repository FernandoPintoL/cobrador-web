<?php

namespace App\Helpers;

/**
 * 🎨 BladeLocalizationHelper - Funciones Helper para Blade
 *
 * Proporciona funciones globales para usar en vistas blade.
 *
 * USO EN BLADE:
 * {{ statusBadge($credit->status) }}
 * {{ translate('frequency', $credit->frequency) }}
 * {{ label('payment_method') }}
 */
class BladeLocalizationHelper
{
    /**
     * Retorna un badge HTML con el estado coloreado
     *
     * @param string $status
     * @return string HTML badge
     */
    public static function statusBadge(?string $status): string
    {
        $displayStatus = ReportLocalizationHelper::creditStatus($status);
        $color = ReportLocalizationHelper::creditStatusColor($status);
        $icon = ReportLocalizationHelper::creditStatusIcon($status);

        return <<<HTML
        <span style="
            background-color: {$color};
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        ">
            {$icon} {$displayStatus}
        </span>
        HTML;
    }

    /**
     * Alias para ReportLocalizationHelper::translateField
     */
    public static function translate(string $field, mixed $value): string
    {
        return ReportLocalizationHelper::translateField($field, $value);
    }

    /**
     * Alias para ReportLocalizationHelper::term
     */
    public static function label(string $term): string
    {
        return ReportLocalizationHelper::term($term);
    }

    /**
     * Registra todas las funciones helper globales en Blade
     *
     * Llamar una sola vez desde el service provider:
     * BladeLocalizationHelper::registerGlobalHelpers();
     */
    public static function registerGlobalHelpers(): void
    {
        // Funciones de traducción
        if (!function_exists('creditStatus')) {
            function creditStatus(?string $status): string {
                return ReportLocalizationHelper::creditStatus($status);
            }
        }

        if (!function_exists('frequency')) {
            function frequency(?string $frequency): string {
                return ReportLocalizationHelper::frequency($frequency);
            }
        }

        if (!function_exists('paymentMethod')) {
            function paymentMethod(?string $method): string {
                return ReportLocalizationHelper::paymentMethod($method);
            }
        }

        if (!function_exists('paymentStatus')) {
            function paymentStatus(?string $status): string {
                return ReportLocalizationHelper::paymentStatus($status);
            }
        }

        if (!function_exists('balanceStatus')) {
            function balanceStatus(?string $status): string {
                return ReportLocalizationHelper::balanceStatus($status);
            }
        }

        if (!function_exists('statusBadge')) {
            function statusBadge(?string $status): string {
                return BladeLocalizationHelper::statusBadge($status);
            }
        }

        if (!function_exists('translate')) {
            function translate(string $field, mixed $value): string {
                return ReportLocalizationHelper::translateField($field, $value);
            }
        }

        if (!function_exists('label')) {
            function label(string $term): string {
                return ReportLocalizationHelper::term($term);
            }
        }

        if (!function_exists('term')) {
            function term(string $term): string {
                return ReportLocalizationHelper::term($term);
            }
        }
    }
}
