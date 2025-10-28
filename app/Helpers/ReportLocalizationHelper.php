<?php

namespace App\Helpers;

/**
 * 🌐 ReportLocalizationHelper - Traducción Centralizada para Reportes
 *
 * Proporciona traducción consistente de estados, frecuencias, métodos de pago
 * y otros campos en PDF, HTML y Excel.
 *
 * USO:
 * use App\Helpers\ReportLocalizationHelper as Translate;
 *
 * // En vistas blade
 * {{ Translate::creditStatus($credit->status) }}
 * {{ Translate::frequency($credit->frequency) }}
 *
 * // En clases Export
 * {{ ReportLocalizationHelper::paymentMethod($payment->method) }}
 */
class ReportLocalizationHelper
{
    /**
     * Estados de Crédito
     */
    public static function creditStatus(?string $status): string
    {
        return match ($status) {
            'pending_approval' => 'Pendiente de Aprobación',
            'waiting_delivery' => 'Esperando Entrega',
            'active' => 'Activo',
            'completed' => 'Completado',
            'defaulted' => 'Incumplido',
            'cancelled' => 'Cancelado',
            'rejected' => 'Rechazado',
            'on_hold' => 'En Espera',
            default => ucfirst($status ?? 'Desconocido'),
        };
    }

    /**
     * Icono para estado de crédito (emoji)
     */
    public static function creditStatusIcon(?string $status): string
    {
        return match ($status) {
            'pending_approval' => '⏳',
            'waiting_delivery' => '📦',
            'active' => '✅',
            'completed' => '🎉',
            'defaulted' => '❌',
            'cancelled' => '❌',
            'rejected' => '🚫',
            'on_hold' => '⏸️',
            default => '❓',
        };
    }

    /**
     * Color para estado de crédito (para HTML/PDF)
     */
    public static function creditStatusColor(?string $status): string
    {
        return match ($status) {
            'pending_approval' => '#FFA500',
            'waiting_delivery' => '#0099FF',
            'active' => '#00B050',
            'completed' => '#70AD47',
            'defaulted' => '#C00000',
            'cancelled' => '#808080',
            'rejected' => '#FF0000',
            'on_hold' => '#FFD966',
            default => '#999999',
        };
    }

    /**
     * Frecuencias de Pago
     */
    public static function frequency(?string $frequency): string
    {
        return match ($frequency) {
            'daily' => 'Diaria',
            'weekly' => 'Semanal',
            'biweekly' => 'Quincenal',
            'monthly' => 'Mensual',
            'yearly' => 'Anual',
            'custom' => 'Personalizada',
            default => ucfirst($frequency ?? 'Desconocida'),
        };
    }

    /**
     * Métodos de Pago
     */
    public static function paymentMethod(?string $method): string
    {
        return match ($method) {
            'cash' => 'Efectivo',
            'bank_transfer' => 'Transferencia Bancaria',
            'check' => 'Cheque',
            'credit_card' => 'Tarjeta de Crédito',
            'debit_card' => 'Tarjeta de Débito',
            'mobile_payment' => 'Pago Móvil',
            'other' => 'Otro',
            default => ucfirst($method ?? 'Desconocido'),
        };
    }

    /**
     * Estados de Pago (Completado, Al Día, Atrasado, etc.)
     */
    public static function paymentStatus(?string $status): string
    {
        return match ($status) {
            'completed' => 'Completado',
            'current' => 'Al Día',
            'ahead' => 'Adelantado',
            'warning' => 'Retraso Bajo',
            'danger' => 'Retraso Alto',
            'overdue' => 'Vencido',
            'pending' => 'Pendiente',
            'cancelled' => 'Cancelado',
            default => ucfirst($status ?? 'Desconocido'),
        };
    }

    /**
     * Estados de Balance/Caja
     */
    public static function balanceStatus(?string $status): string
    {
        return match ($status) {
            'open' => 'Abierta',
            'closed' => 'Cerrada',
            'pending_close' => 'Pendiente Cierre',
            'reconciled' => 'Conciliada',
            default => ucfirst($status ?? 'Desconocida'),
        };
    }

    /**
     * Categorías de Usuario/Cliente
     */
    public static function userCategory(?string $category): string
    {
        return match ($category) {
            'premium' => 'Premium',
            'standard' => 'Estándar',
            'economy' => 'Económica',
            'vip' => 'VIP',
            'bronze' => 'Bronce',
            'silver' => 'Plata',
            'gold' => 'Oro',
            'platinum' => 'Platino',
            default => ucfirst($category ?? 'Desconocida'),
        };
    }

    /**
     * Estados de Documentos/Transacciones
     */
    public static function documentStatus(?string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente',
            'submitted' => 'Presentado',
            'approved' => 'Aprobado',
            'rejected' => 'Rechazado',
            'archived' => 'Archivado',
            'expired' => 'Vencido',
            default => ucfirst($status ?? 'Desconocido'),
        };
    }

    /**
     * Tipos de Interés
     */
    public static function interestType(?string $type): string
    {
        return match ($type) {
            'simple' => 'Interés Simple',
            'compound' => 'Interés Compuesto',
            'variable' => 'Interés Variable',
            'fixed' => 'Interés Fijo',
            default => ucfirst($type ?? 'Desconocido'),
        };
    }

    /**
     * Géneros
     */
    public static function gender(?string $gender): string
    {
        return match ($gender) {
            'M' => 'Masculino',
            'F' => 'Femenino',
            'male' => 'Masculino',
            'female' => 'Femenino',
            'other' => 'Otro',
            default => ucfirst($gender ?? 'Desconocido'),
        };
    }

    /**
     * Provincias/Estados (Venezuela)
     */
    public static function state(?string $state): string
    {
        return match ($state) {
            'amazonas' => 'Amazonas',
            'anzoategui' => 'Anzoátegui',
            'apure' => 'Apure',
            'aragua' => 'Aragua',
            'barinas' => 'Barinas',
            'bolivar' => 'Bolívar',
            'carabobo' => 'Carabobo',
            'cojedes' => 'Cojedes',
            'delta_amacuro' => 'Delta Amacuro',
            'falcon' => 'Falcón',
            'guarico' => 'Guárico',
            'lara' => 'Lara',
            'merida' => 'Mérida',
            'miranda' => 'Miranda',
            'monagas' => 'Monagas',
            'nueva_esparta' => 'Nueva Esparta',
            'portuguesa' => 'Portuguesa',
            'sucre' => 'Sucre',
            'tachira' => 'Táchira',
            'trujillo' => 'Trujillo',
            'vargas' => 'Vargas',
            'yaracuy' => 'Yaracuy',
            'zulia' => 'Zulia',
            'capitol' => 'Distrito Capital',
            'isl_margarita' => 'Isla de Margarita',
            default => ucfirst($state ?? 'Desconocida'),
        };
    }

    /**
     * Traduce un campo según su tipo
     *
     * @param string $field Nombre del campo
     * @param mixed $value Valor a traducir
     * @return string Valor traducido
     */
    public static function translateField(string $field, mixed $value): string
    {
        return match ($field) {
            'status' => self::creditStatus($value),
            'frequency' => self::frequency($value),
            'payment_method' => self::paymentMethod($value),
            'payment_status' => self::paymentStatus($value),
            'balance_status' => self::balanceStatus($value),
            'category' => self::userCategory($value),
            'gender' => self::gender($value),
            'state' => self::state($value),
            default => $value,
        };
    }

    /**
     * Términos comunes en reportes
     */
    public static function term(string $term): string
    {
        return match ($term) {
            // Encabezados comunes
            'credit' => 'Crédito',
            'credits' => 'Créditos',
            'payment' => 'Pago',
            'payments' => 'Pagos',
            'client' => 'Cliente',
            'clients' => 'Clientes',
            'cobrador' => 'Cobrador',
            'cobradores' => 'Cobradores',
            'amount' => 'Monto',
            'balance' => 'Balance',
            'status' => 'Estado',
            'date' => 'Fecha',
            'frequency' => 'Frecuencia',
            'installments' => 'Cuotas',
            'paid_installments' => 'Cuotas Pagadas',
            'pending_installments' => 'Cuotas Pendientes',
            'efficiency' => 'Eficiencia',
            'total' => 'Total',
            'summary' => 'Resumen',
            'detail' => 'Detalle',
            'report' => 'Reporte',
            'generated' => 'Generado',
            'generated_by' => 'Generado por',
            'period' => 'Período',
            'from' => 'Desde',
            'to' => 'Hasta',

            // Acciones
            'approve' => 'Aprobar',
            'reject' => 'Rechazar',
            'deliver' => 'Entregar',
            'cancel' => 'Cancelar',
            'close' => 'Cerrar',

            // Secciones
            'portfolio' => 'Cartera',
            'portfolio_summary' => 'Resumen de Cartera',
            'performance' => 'Desempeño',
            'performance_summary' => 'Resumen de Desempeño',
            'daily_activity' => 'Actividad Diaria',
            'cash_flow' => 'Flujo de Caja',
            'commissions' => 'Comisiones',
            'waiting_list' => 'Créditos en Espera',

            // Estados generales
            'active' => 'Activo',
            'inactive' => 'Inactivo',
            'pending' => 'Pendiente',
            'approved' => 'Aprobado',
            'rejected' => 'Rechazado',
            'completed' => 'Completado',

            // Números
            'count' => 'Cantidad',
            'total_amount' => 'Monto Total',
            'average' => 'Promedio',
            'minimum' => 'Mínimo',
            'maximum' => 'Máximo',

            default => $term,
        };
    }
}
