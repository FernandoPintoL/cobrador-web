<?php

namespace App\Services;

use App\Models\Credit;

/**
 * Servicio para formatear créditos en reportes (PDF/Excel)
 * Centraliza el mapeo de severidad a iconos, colores y labels
 */
class CreditReportFormatterService
{
    /**
     * Obtiene el emoji Unicode para la severidad
     * Compatible con PDF y Excel
     */
    public static function getSeverityEmoji(string $severity): string
    {
        return match($severity) {
            'none'     => '✅',  // Check verde
            'light'    => '⚠️',  // Triángulo amarillo
            'moderate' => '🟠',  // Círculo naranja
            'critical' => '🔴',  // Círculo rojo
            default    => '❔',  // Interrogación
        };
    }

    /**
     * Obtiene el símbolo de texto simple (sin emojis)
     * Útil si los emojis no se renderizan bien
     */
    public static function getSeveritySymbol(string $severity): string
    {
        return match($severity) {
            'none'     => '✓',   // Check
            'light'    => '⚠',   // Triángulo
            'moderate' => '!',   // Exclamación
            'critical' => '✗',   // X
            default    => '?',
        };
    }

    /**
     * Obtiene el label descriptivo
     */
    public static function getSeverityLabel(string $severity): string
    {
        return match($severity) {
            'none'     => 'Al día',
            'light'    => 'Alerta leve',
            'moderate' => 'Alerta moderada',
            'critical' => 'Crítico',
            default    => 'Desconocido',
        };
    }

    /**
     * Obtiene el color hexadecimal para la severidad
     * Formato: #RRGGBB
     */
    public static function getSeverityColorHex(string $severity): string
    {
        return match($severity) {
            'none'     => '#4CAF50',  // Verde
            'light'    => '#FFC107',  // Amarillo
            'moderate' => '#FF9800',  // Naranja
            'critical' => '#F44336',  // Rojo
            default    => '#9E9E9E',  // Gris
        };
    }

    /**
     * Obtiene el color ARGB para Excel (PhpSpreadsheet)
     * Formato: AARRGGBB (Alpha + RGB)
     */
    public static function getSeverityColorExcel(string $severity): string
    {
        return match($severity) {
            'none'     => 'FF4CAF50',  // Verde
            'light'    => 'FFFFC107',  // Amarillo
            'moderate' => 'FFFF9800',  // Naranja
            'critical' => 'FFF44336',  // Rojo
            default    => 'FF9E9E9E',  // Gris
        };
    }

    /**
     * Obtiene el color de fondo claro para Excel
     * Formato: AARRGGBB
     */
    public static function getSeverityBgColorExcel(string $severity): string
    {
        return match($severity) {
            'none'     => 'FFE8F5E9',  // Verde claro
            'light'    => 'FFFFF9C4',  // Amarillo claro
            'moderate' => 'FFFFE0B2',  // Naranja claro
            'critical' => 'FFFFCDD2',  // Rojo claro
            default    => 'FFF5F5F5',  // Gris claro
        };
    }

    /**
     * Formatea un crédito para Excel
     * Retorna: [emoji, label, bgColor, textColor]
     */
    public static function formatForExcel(Credit $credit): array
    {
        $severity = $credit->overdue_severity;

        return [
            'emoji'     => self::getSeverityEmoji($severity),
            'symbol'    => self::getSeveritySymbol($severity),
            'label'     => self::getSeverityLabel($severity),
            'text'      => self::getSeverityEmoji($severity) . ' ' . self::getSeverityLabel($severity),
            'bg_color'  => self::getSeverityBgColorExcel($severity),
            'text_color'=> self::getSeverityColorExcel($severity),
            'days'      => $credit->days_overdue,
        ];
    }

    /**
     * Formatea un crédito para PDF
     * Retorna: [emoji, label, color_hex]
     */
    public static function formatForPDF(Credit $credit): array
    {
        $severity = $credit->overdue_severity;

        return [
            'emoji'    => self::getSeverityEmoji($severity),
            'symbol'   => self::getSeveritySymbol($severity),
            'label'    => self::getSeverityLabel($severity),
            'text'     => self::getSeverityEmoji($severity) . ' ' . self::getSeverityLabel($severity),
            'color'    => self::getSeverityColorHex($severity),
            'days'     => $credit->days_overdue,
        ];
    }

    /**
     * Obtiene un array completo de información para reportes
     */
    public static function getReportData(Credit $credit): array
    {
        return [
            'id'                 => $credit->id,
            'client_name'        => $credit->client->name ?? 'N/A',
            'amount'             => number_format($credit->amount, 2),
            'balance'            => number_format($credit->balance, 2),
            'status'             => $credit->status,
            'frequency'          => $credit->frequency,

            // Datos de severidad
            'days_overdue'       => $credit->days_overdue,
            'overdue_severity'   => $credit->overdue_severity,
            'severity_emoji'     => self::getSeverityEmoji($credit->overdue_severity),
            'severity_label'     => self::getSeverityLabel($credit->overdue_severity),
            'severity_text'      => self::getSeverityEmoji($credit->overdue_severity) . ' ' .
                                   self::getSeverityLabel($credit->overdue_severity),

            // Datos de pago
            'payment_status'     => $credit->payment_status,
            'overdue_installments' => $credit->overdue_installments,
            'requires_attention' => $credit->requires_attention,

            // Colores
            'severity_color_hex' => self::getSeverityColorHex($credit->overdue_severity),
            'severity_bg_excel'  => self::getSeverityBgColorExcel($credit->overdue_severity),
        ];
    }
}
