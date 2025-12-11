/**
 * Tipos de datos para Créditos
 * Generado automáticamente desde el backend
 */

export type CreditStatus =
  | 'pending_approval'
  | 'waiting_delivery'
  | 'active'
  | 'completed'
  | 'defaulted'
  | 'rejected'
  | 'cancelled';

export type CreditFrequency = 'daily' | 'weekly' | 'biweekly' | 'monthly';

/**
 * Severidad del retraso (calculado por backend)
 */
export type OverdueSeverity = 'none' | 'light' | 'moderate' | 'critical';

/**
 * Estado de pago (calculado por backend)
 */
export type PaymentStatus = 'completed' | 'on_track' | 'at_risk' | 'critical';

/**
 * Interfaz principal de Crédito
 */
export interface Credit {
  // Campos básicos
  id: number;
  client_id: number;
  created_by: number;
  amount: string | number;
  balance: string | number;
  total_amount?: string | number;
  installment_amount?: string | number;
  frequency: CreditFrequency;
  status: CreditStatus;
  start_date: string;
  end_date: string;
  created_at: string;
  updated_at: string;

  // Campos de interés
  interest_rate?: number;
  interest_rate_id?: number;

  // Campos de aprobación y entrega
  scheduled_delivery_date?: string;
  approved_by?: number;
  approved_at?: string;
  delivered_by?: number;
  delivered_at?: string;
  delivery_notes?: string;
  rejection_reason?: string;
  immediate_delivery_requested?: boolean;

  // Campos de cuotas
  total_installments?: number;
  paid_installments?: number;
  total_paid?: string | number;
  completed_installments_count?: number;
  pending_installments?: number;

  // ========================================
  // NUEVOS CAMPOS CALCULADOS DESDE BACKEND
  // ========================================

  /**
   * Días de retraso calculados por el backend
   */
  days_overdue: number;

  /**
   * Severidad del retraso
   * - none: Sin retraso (0 días)
   * - light: 1-3 días de retraso
   * - moderate: 4-7 días de retraso
   * - critical: >7 días de retraso
   */
  overdue_severity: OverdueSeverity;

  /**
   * Estado de pago basado en cuotas pendientes
   * - completed: 0 cuotas pendientes
   * - on_track: En buen camino (no usado actualmente)
   * - at_risk: 1-3 cuotas pendientes
   * - critical: >3 cuotas pendientes
   */
  payment_status: PaymentStatus;

  /**
   * Cantidad de cuotas atrasadas
   */
  overdue_installments: number;

  /**
   * Flag que indica si requiere atención inmediata
   */
  requires_attention: boolean;

  // Relaciones
  client?: {
    id: number;
    name: string;
    email?: string;
    phone?: string;
    ci?: string;
  };

  created_by_user?: {
    id: number;
    name: string;
  };

  payments?: Array<{
    id: number;
    amount: number;
    payment_date: string;
    payment_method: string;
  }>;
}

/**
 * Interfaz para paginación de créditos
 */
export interface CreditPaginatedResponse {
  data: Credit[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
}

/**
 * Interfaz para filtros de créditos
 */
export interface CreditFilters {
  status?: CreditStatus;
  frequency?: CreditFrequency;
  search?: string;
  cobrador_id?: number;
  client_id?: number;
  start_date_from?: string;
  start_date_to?: string;
  end_date_from?: string;
  end_date_to?: string;
  amount_min?: number;
  amount_max?: number;
  balance_min?: number;
  balance_max?: number;
  per_page?: number;
}
