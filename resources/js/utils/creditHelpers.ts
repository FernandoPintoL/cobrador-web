/**
 * Helpers para mapear estados de créditos a UI
 * Sistema estandarizado de colores, iconos y labels
 */

import { OverdueSeverity, PaymentStatus, CreditStatus } from '@/types/credit';

// ========================================
// MAPEO DE SEVERIDAD (OVERDUE_SEVERITY)
// ========================================

/**
 * Obtiene las clases de color Tailwind CSS para la severidad
 */
export const getSeverityColorClass = (severity: OverdueSeverity): string => {
  switch (severity) {
    case 'none':
      return 'text-green-600 dark:text-green-400';
    case 'light':
      return 'text-amber-600 dark:text-amber-400';
    case 'moderate':
      return 'text-orange-600 dark:text-orange-400';
    case 'critical':
      return 'text-red-600 dark:text-red-400';
    default:
      return 'text-gray-600 dark:text-gray-400';
  }
};

/**
 * Obtiene las clases de background Tailwind CSS para la severidad
 */
export const getSeverityBgClass = (severity: OverdueSeverity): string => {
  switch (severity) {
    case 'none':
      return 'bg-green-50 dark:bg-green-900/20';
    case 'light':
      return 'bg-amber-50 dark:bg-amber-900/20';
    case 'moderate':
      return 'bg-orange-50 dark:bg-orange-900/20';
    case 'critical':
      return 'bg-red-50 dark:bg-red-900/20';
    default:
      return 'bg-gray-50 dark:bg-gray-900/20';
  }
};

/**
 * Obtiene las clases de border Tailwind CSS para la severidad
 */
export const getSeverityBorderClass = (severity: OverdueSeverity): string => {
  switch (severity) {
    case 'none':
      return 'border-green-200 dark:border-green-800';
    case 'light':
      return 'border-amber-200 dark:border-amber-800';
    case 'moderate':
      return 'border-orange-200 dark:border-orange-800';
    case 'critical':
      return 'border-red-200 dark:border-red-800';
    default:
      return 'border-gray-200 dark:border-gray-800';
  }
};

/**
 * Obtiene el nombre del icono Lucide para la severidad
 * Usar con: import { CheckCircle, AlertTriangle, AlertCircle, XCircle } from 'lucide-react'
 */
export const getSeverityIconName = (severity: OverdueSeverity): string => {
  switch (severity) {
    case 'none':
      return 'CheckCircle'; // ✓ Al día
    case 'light':
      return 'AlertTriangle'; // ⚠ Alerta leve
    case 'moderate':
      return 'AlertCircle'; // ⚠ Moderado
    case 'critical':
      return 'XCircle'; // 🚨 Crítico
    default:
      return 'HelpCircle';
  }
};

/**
 * Obtiene el label descriptivo para la severidad
 */
export const getSeverityLabel = (severity: OverdueSeverity): string => {
  switch (severity) {
    case 'none':
      return 'Al día';
    case 'light':
      return 'Alerta leve';
    case 'moderate':
      return 'Alerta moderada';
    case 'critical':
      return 'Crítico';
    default:
      return 'Desconocido';
  }
};

// ========================================
// MAPEO DE ESTADO DE PAGO (PAYMENT_STATUS)
// ========================================

/**
 * Obtiene las clases de color para el estado de pago
 */
export const getPaymentStatusColorClass = (status: PaymentStatus): string => {
  switch (status) {
    case 'completed':
      return 'text-green-600 dark:text-green-400';
    case 'on_track':
      return 'text-blue-600 dark:text-blue-400';
    case 'at_risk':
      return 'text-amber-600 dark:text-amber-400';
    case 'critical':
      return 'text-red-600 dark:text-red-400';
    default:
      return 'text-gray-600 dark:text-gray-400';
  }
};

/**
 * Obtiene el label para el estado de pago
 */
export const getPaymentStatusLabel = (status: PaymentStatus): string => {
  switch (status) {
    case 'completed':
      return 'Completado';
    case 'on_track':
      return 'En buen camino';
    case 'at_risk':
      return 'En riesgo';
    case 'critical':
      return 'Crítico';
    default:
      return 'Desconocido';
  }
};

// ========================================
// MAPEO DE ESTADO DEL CRÉDITO (STATUS)
// ========================================

/**
 * Obtiene las clases de color para el estado del crédito
 */
export const getCreditStatusColorClass = (status: CreditStatus): string => {
  switch (status) {
    case 'pending_approval':
      return 'text-orange-600 dark:text-orange-400';
    case 'waiting_delivery':
      return 'text-blue-600 dark:text-blue-400';
    case 'active':
      return 'text-green-600 dark:text-green-400';
    case 'completed':
      return 'text-teal-600 dark:text-teal-400';
    case 'defaulted':
      return 'text-red-600 dark:text-red-400';
    case 'rejected':
      return 'text-red-600 dark:text-red-400';
    case 'cancelled':
      return 'text-gray-600 dark:text-gray-400';
    default:
      return 'text-gray-600 dark:text-gray-400';
  }
};

/**
 * Obtiene el label para el estado del crédito
 */
export const getCreditStatusLabel = (status: CreditStatus): string => {
  switch (status) {
    case 'pending_approval':
      return 'Pendiente de aprobación';
    case 'waiting_delivery':
      return 'Esperando entrega';
    case 'active':
      return 'Activo';
    case 'completed':
      return 'Completado';
    case 'defaulted':
      return 'En mora';
    case 'rejected':
      return 'Rechazado';
    case 'cancelled':
      return 'Cancelado';
    default:
      return status;
  }
};

// ========================================
// HELPERS DE FORMATO
// ========================================

/**
 * Formatea un monto a moneda boliviana
 */
export const formatCurrency = (amount: string | number): string => {
  const value = typeof amount === 'string' ? parseFloat(amount) : amount;
  return new Intl.NumberFormat('es-BO', {
    style: 'currency',
    currency: 'BOB',
    minimumFractionDigits: 2,
  }).format(value);
};

/**
 * Formatea una fecha
 */
export const formatDate = (date: string): string => {
  return new Intl.DateTimeFormat('es-BO', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  }).format(new Date(date));
};

/**
 * Obtiene el label para la frecuencia de pago
 */
export const getFrequencyLabel = (
  frequency: 'daily' | 'weekly' | 'biweekly' | 'monthly'
): string => {
  switch (frequency) {
    case 'daily':
      return 'Diario';
    case 'weekly':
      return 'Semanal';
    case 'biweekly':
      return 'Quincenal';
    case 'monthly':
      return 'Mensual';
    default:
      return frequency;
  }
};

// ========================================
// COMPONENTES HELPER (OPCIONAL)
// ========================================

/**
 * Props para el badge de severidad
 */
export interface SeverityBadgeProps {
  severity: OverdueSeverity;
  showIcon?: boolean;
  showLabel?: boolean;
  size?: 'sm' | 'md' | 'lg';
}

/**
 * Obtiene las clases completas para un badge de severidad
 */
export const getSeverityBadgeClasses = (severity: OverdueSeverity): string => {
  return `inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-sm font-medium ${getSeverityBgClass(
    severity
  )} ${getSeverityColorClass(severity)} ${getSeverityBorderClass(severity)} border`;
};
