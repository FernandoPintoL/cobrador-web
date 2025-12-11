/**
 * Badge de Severidad para Créditos
 * Componente reutilizable que muestra la severidad del retraso
 * con color, icono y label estandarizados
 */

import React from 'react';
import {
  CheckCircle,
  AlertTriangle,
  AlertCircle,
  XCircle,
  HelpCircle,
} from 'lucide-react';
import type { OverdueSeverity } from '@/types/credit';
import {
  getSeverityBadgeClasses,
  getSeverityLabel,
  getSeverityIconName,
} from '@/utils/creditHelpers';

interface CreditSeverityBadgeProps {
  severity: OverdueSeverity;
  showIcon?: boolean;
  showLabel?: boolean;
  daysOverdue?: number;
  className?: string;
}

/**
 * Badge que muestra la severidad del retraso de un crédito
 *
 * @example
 * // Uso básico
 * <CreditSeverityBadge severity="critical" />
 *
 * @example
 * // Con días de retraso
 * <CreditSeverityBadge severity="moderate" daysOverdue={5} />
 *
 * @example
 * // Solo icono
 * <CreditSeverityBadge severity="light" showLabel={false} />
 */
export function CreditSeverityBadge({
  severity,
  showIcon = true,
  showLabel = true,
  daysOverdue,
  className = '',
}: CreditSeverityBadgeProps) {
  // Obtener icono según severidad
  const IconComponent = React.useMemo(() => {
    const iconName = getSeverityIconName(severity);
    switch (iconName) {
      case 'CheckCircle':
        return CheckCircle;
      case 'AlertTriangle':
        return AlertTriangle;
      case 'AlertCircle':
        return AlertCircle;
      case 'XCircle':
        return XCircle;
      default:
        return HelpCircle;
    }
  }, [severity]);

  // Obtener label
  const label = React.useMemo(() => {
    if (daysOverdue !== undefined && daysOverdue > 0) {
      return `${daysOverdue} día${daysOverdue > 1 ? 's' : ''} de retraso`;
    }
    return getSeverityLabel(severity);
  }, [severity, daysOverdue]);

  return (
    <span className={`${getSeverityBadgeClasses(severity)} ${className}`}>
      {showIcon && <IconComponent className="h-4 w-4" />}
      {showLabel && <span>{label}</span>}
    </span>
  );
}

/**
 * Ejemplo de uso en una tabla de créditos
 */
export function CreditTableExample() {
  // Datos de ejemplo
  const credits = [
    {
      id: 1,
      client: 'Juan Pérez',
      amount: 5000,
      days_overdue: 0,
      overdue_severity: 'none' as OverdueSeverity,
    },
    {
      id: 2,
      client: 'María García',
      amount: 3000,
      days_overdue: 2,
      overdue_severity: 'light' as OverdueSeverity,
    },
    {
      id: 3,
      client: 'Pedro López',
      amount: 7000,
      days_overdue: 5,
      overdue_severity: 'moderate' as OverdueSeverity,
    },
    {
      id: 4,
      client: 'Ana Martínez',
      amount: 10000,
      days_overdue: 15,
      overdue_severity: 'critical' as OverdueSeverity,
    },
  ];

  return (
    <div className="rounded-lg border">
      <table className="min-w-full divide-y divide-gray-200">
        <thead className="bg-gray-50 dark:bg-gray-900">
          <tr>
            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Cliente
            </th>
            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Monto
            </th>
            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Estado
            </th>
          </tr>
        </thead>
        <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
          {credits.map((credit) => (
            <tr key={credit.id}>
              <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                {credit.client}
              </td>
              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                Bs. {credit.amount.toLocaleString()}
              </td>
              <td className="px-6 py-4 whitespace-nowrap">
                <CreditSeverityBadge
                  severity={credit.overdue_severity}
                  daysOverdue={credit.days_overdue}
                />
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export default CreditSeverityBadge;
