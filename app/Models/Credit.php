<?php
namespace App\Models;

use App\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

/**
 * Credit Model
 *
 * Flujo de estados del crédito:
 *
 * 1. PENDING_APPROVAL
 *    - Cobrador solicita crédito
 *    - Esperando aprobación del manager
 *
 * 2. WAITING_DELIVERY
 *    - Manager aprobó el crédito
 *    - Crédito esperando entrega física por el cobrador
 *    - El campo `immediate_delivery_requested` indica urgencia (true = hoy, false = fecha programada)
 *    - El campo `scheduled_delivery_date` indica cuándo debe entregarse
 *
 * 3. ACTIVE
 *    - Cobrador confirmó la entrega física del dinero al cliente
 *    - El cronograma de pagos se arma en este momento:
 *      * delivered_at = momento de la entrega física
 *      * start_date = día siguiente a delivered_at (primer día de pagos)
 *      * end_date = calculado según total_installments y frequency
 *    - Cliente comienza a pagar según el cronograma
 *
 * 4. COMPLETED / DEFAULTED / CANCELLED / REJECTED
 *    - Estados finales del crédito
 *
 * IMPORTANTE: El cronograma de pagos se calcula al momento de la entrega física (ACTIVE),
 * NO al momento de la aprobación (WAITING_DELIVERY).
 */
class Credit extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'client_id',
        'created_by',
        'description',           // NUEVO: Concepto/descripción del crédito
        'amount',
        'down_payment',          // NUEVO: Anticipo del cliente
        'balance',
        'total_paid',
        'frequency',
        'start_date',
        'end_date',
        'status',
        'interest_rate',
        'total_amount',
        'installment_amount',
        'scheduled_delivery_date',
        'approved_by',
        'approved_at',
        'delivered_at',
        'completed_at',
        'delivered_by',
        'delivery_notes',
        'rejection_reason',
        'interest_rate_id',
        'total_installments',
        'paid_installments',
        'latitude',
        'longitude',
        'immediate_delivery_requested',
        'first_payment_today',
        'is_custom_credit',      // NUEVO: Flag de modo personalizado
        'cash_balance_id',
    ];

    protected $casts = [
        'start_date'                   => 'date',
        'end_date'                     => 'date',
        'scheduled_delivery_date'      => 'datetime',
        'immediate_delivery_requested' => 'boolean',
        'first_payment_today'          => 'boolean',
        'is_custom_credit'             => 'boolean',  // NUEVO
        'approved_at'                  => 'datetime',
        'delivered_at'                 => 'datetime',
        'completed_at'                 => 'datetime',
        'amount'                       => 'decimal:2',
        'down_payment'                 => 'decimal:2', // NUEVO
        'total_paid'                   => 'decimal:2',
        'interest_rate'                => 'decimal:2',
        'total_amount'                 => 'decimal:2',
        'balance'                      => 'decimal:2',
        'installment_amount'           => 'decimal:2',
        'total_installments'           => 'integer',
        'paid_installments'            => 'integer',
        'latitude'                     => 'decimal:8',
        'longitude'                    => 'decimal:8',
    ];

    /**
     * Atributos calculados que se incluyen automáticamente en JSON
     * Estos campos se calculan dinámicamente usando los métodos get{Attribute}Attribute()
     */
    protected $appends = [
        'days_overdue',           // Días de retraso calculados
        'overdue_severity',       // Severidad: 'none', 'light', 'moderate', 'critical'
        'payment_status',         // Estado: 'completed', 'on_track', 'at_risk', 'critical'
        'overdue_installments',   // Cantidad de cuotas atrasadas
        'requires_attention',     // Flag booleano de alerta
        'financed_amount',        // NUEVO: Monto financiado (amount - down_payment)
    ];

    /**
     * Get the client that owns this credit.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * Get the cobrador (collector) assigned to this credit.
     */
    public function cobrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cobrador_id');
    }

    /**
     * Get the cobrador who created this credit.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who approved this credit for delivery.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the user who delivered this credit to the client.
     */
    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    /**
     * Get the cash balance associated with this credit delivery.
     */
    public function cashBalance(): BelongsTo
    {
        return $this->belongsTo(CashBalance::class, 'cash_balance_id');
    }

    /**
     * Get the payments for this credit.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Interest rate associated to this credit (if any).
     */
    public function interestRate(): BelongsTo
    {
        return $this->belongsTo(InterestRate::class, 'interest_rate_id');
    }

    /**
     * Calculate the total number of installments based on frequency and dates.
     */
    public function calculateTotalInstallments(): int
    {
        // Priorizar el valor almacenado si existe (> 0)
        if (! empty($this->total_installments) && (int) $this->total_installments > 0) {
            return (int) $this->total_installments;
        }

        // Fallback al cálculo por fechas/frecuencia para compatibilidad
        $startDate = Carbon::parse($this->start_date);
        $endDate   = Carbon::parse($this->end_date);

        switch ($this->frequency) {
            case 'daily':
                return $startDate->diffInDays($endDate);
            case 'weekly':
                return $startDate->diffInWeeks($endDate);
            case 'biweekly':
                return (int) floor($startDate->diffInWeeks($endDate) / 2) + 1;
            case 'monthly':
                return $startDate->diffInMonths($endDate) + 1;
            default:
                return 0;
        }
    }

    /**
     * Calculate total amount with interest
     *
     * Para créditos personalizados con anticipo:
     * total_amount = (amount - down_payment) × (1 + interest_rate/100)
     *
     * Para créditos normales (sin anticipo):
     * total_amount = amount × (1 + interest_rate/100)
     */
    public function calculateTotalAmount(): float
    {
        // Usar monto financiado (considera anticipo si existe)
        $financedAmount = $this->financed_amount;
        $interestAmount = $financedAmount * ($this->interest_rate / 100);

        return $financedAmount + $interestAmount;
    }

    /**
     * Calculate installment amount based on total amount and installments
     */
    public function calculateInstallmentAmount(): float
    {
        $totalInstallments = $this->calculateTotalInstallments();
        $totalAmount       = $this->total_amount ?? $this->calculateTotalAmount();

        return $totalInstallments > 0 ? $totalAmount / $totalInstallments : 0;
    }

    /**
     * Calculate installment amount based on frequency and date range (for controllers)
     */
    public static function calculateInstallmentAmountByFrequency(
        float $totalAmount,
        string $frequency,
        string $startDate,
        string $endDate
    ): float {
        $start    = new \DateTime($startDate);
        $end      = new \DateTime($endDate);
        $interval = $start->diff($end);

        switch ($frequency) {
            case 'daily':
                $totalPeriods = $interval->days;
                break;
            case 'weekly':
                $totalPeriods = (int) floor($interval->days / 7);
                break;
            case 'biweekly':
                $totalPeriods = (int) floor($interval->days / 14);
                break;
            case 'monthly':
                $totalPeriods = ($interval->y * 12) + $interval->m;
                break;
            default:
                $totalPeriods = 1;
        }

        return $totalPeriods > 0 ? (float) round($totalAmount / $totalPeriods, 2) : (float) $totalAmount;
    }

    /**
     * Get total paid amount
     */
    public function getTotalPaidAmount(): float
    {
        return $this->payments()
            ->where('status', 'completed')
            ->sum('amount');
    }

    /**
     * Get pending installments count
     */
    public function getPendingInstallments(): int
    {
        $totalInstallments = $this->calculateTotalInstallments();
        if ($totalInstallments <= 0) {
            return 0;
        }

        $completedInstallments = $this->getCompletedInstallmentsCount();

        return max(0, $totalInstallments - $completedInstallments);
    }

    /**
     * Count installments that are fully paid (sum of payments per installment >= installment_amount).
     *
     * Si existe el campo paid_installments, lo usa directamente para mejorar el rendimiento.
     * Si no, realiza el cálculo detallado en base a los pagos registrados.
     */
    public function getCompletedInstallmentsCount(): int
    {
        // Si tenemos el valor persistido en la DB, lo usamos directamente
        if ($this->paid_installments !== null) {
            return (int) $this->paid_installments;
        }

        // Método original para retrocompatibilidad
        $installmentAmount = $this->installment_amount ?? $this->calculateInstallmentAmount();
        if ($installmentAmount <= 0) {
            return 0;
        }

        // Sumar pagos por número de cuota y contar las que están completas
        $totalsByInstallment = $this->payments()
            ->whereNotNull('installment_number')
            ->whereNotIn('status', ['cancelled', 'failed'])
            ->selectRaw('installment_number, SUM(amount) as total')
            ->groupBy('installment_number')
            ->pluck('total', 'installment_number');

        $completed = 0;
        foreach ($totalsByInstallment as $sum) {
            if ((float) $sum + 1e-6 >= (float) $installmentAmount) {
                $completed++;
            }
        }

        return $completed;
    }

    /**
     * Get expected installments based on current date
     *
     * ✅ CORREGIDO: Ahora usa getPaymentSchedule() para calcular correctamente
     * considerando días hábiles (lunes-sábado, omite domingos) y el calendario real.
     */
    public function getExpectedInstallments(): int
    {
        $startDate = Carbon::parse($this->start_date);
        $currentDate = Carbon::now()->startOfDay();

        if ($currentDate->lt($startDate)) {
            return 0;
        }

        // ✅ Usar el calendario real que considera días hábiles
        $schedule = $this->getPaymentSchedule();

        $expectedCount = 0;
        foreach ($schedule as $installment) {
            $dueDate = Carbon::parse($installment['due_date'])->startOfDay();

            // Contar cuotas cuya fecha de vencimiento ya pasó o es hoy
            if ($dueDate->lte($currentDate)) {
                $expectedCount++;
            } else {
                // Las siguientes cuotas son futuras, no contarlas
                break;
            }
        }

        return $expectedCount;
    }

    /**
     * Check if payment is overdue
     */
    public function isOverdue(): bool
    {
        $expectedInstallments = $this->getExpectedInstallments();
        $completedInstallments = $this->getCompletedInstallmentsCount();

        return $completedInstallments < $expectedInstallments;
    }

    /**
     * Get overdue amount
     */
    public function getOverdueAmount(): float
    {
        if (! $this->isOverdue()) {
            return 0;
        }

        $expectedInstallments = $this->getExpectedInstallments();
        $completedInstallments = $this->getCompletedInstallmentsCount();
        $overdueInstallments  = $expectedInstallments - $completedInstallments;

        return $overdueInstallments * $this->installment_amount;
    }

    // ========================================
    // NUEVOS MÉTODOS PARA ESTANDARIZACIÓN UI
    // ========================================

    /**
     * Calcula los días de retraso del crédito
     * Retorna 0 si no está vencido o si ya está completado
     */
    public function getDaysOverdueAttribute(): int
    {
        if ($this->status === 'completed' || ! $this->isOverdue()) {
            return 0;
        }

        $expectedInstallments = $this->getExpectedInstallments();
        $completedInstallments = $this->getCompletedInstallmentsCount();
        $overdueInstallments = $expectedInstallments - $completedInstallments;

        // Calcular días basado en la frecuencia de pago
        $daysPerInstallment = match ($this->frequency) {
            'daily' => 1,
            'weekly' => 7,
            'biweekly' => 14,
            'monthly' => 30,
            default => 1,
        };

        return $overdueInstallments * $daysPerInstallment;
    }

    /**
     * Determina la severidad del retraso basado en días
     * Retorna: 'none', 'light', 'moderate', 'critical'
     */
    public function getOverdueSeverityAttribute(): string
    {
        $days = $this->days_overdue;

        if ($days === 0) {
            return 'none';
        }
        if ($days <= 3) {
            return 'light';
        }
        if ($days <= 7) {
            return 'moderate';
        }

        return 'critical';
    }

    /**
     * Estado del pago basado en cuotas pendientes
     * Retorna: 'completed', 'on_track', 'at_risk', 'critical'
     */
    public function getPaymentStatusAttribute(): string
    {
        $total = $this->total_installments ?? $this->calculateTotalInstallments();
        $completed = $this->getCompletedInstallmentsCount();
        $pending = max($total - $completed, 0);

        if ($pending === 0) {
            return 'completed';
        }
        if ($pending <= 3) {
            return 'at_risk';
        }

        return 'critical';
    }

    /**
     * Cantidad de cuotas atrasadas (esperadas pero no pagadas)
     */
    public function getOverdueInstallmentsAttribute(): int
    {
        if (! $this->isOverdue()) {
            return 0;
        }

        $expectedInstallments = $this->getExpectedInstallments();
        $completedInstallments = $this->getCompletedInstallmentsCount();

        return max($expectedInstallments - $completedInstallments, 0);
    }

    /**
     * Flag booleano si requiere atención inmediata
     */
    public function getRequiresAttentionAttribute(): bool
    {
        return in_array($this->overdue_severity, ['moderate', 'critical'])
            || in_array($this->payment_status, ['critical']);
    }

    /**
     * Monto financiado (amount - down_payment)
     * Este es el monto sobre el cual se calculan los intereses
     *
     * Ejemplo:
     * - Precio del producto: 2000 Bs (amount)
     * - Anticipo: 500 Bs (down_payment)
     * - Monto financiado: 1500 Bs (financed_amount)
     * - Total con interés (20%): 1500 × 1.20 = 1800 Bs (total_amount)
     */
    public function getFinancedAmountAttribute(): float
    {
        return (float) $this->amount - (float) ($this->down_payment ?? 0);
    }

    /**
     * Procesar un pago (puede ser parcial, exacto o en exceso)
     */
    public function processPayment(float $paymentAmount, string $paymentType = 'regular'): array
    {
        $currentBalance     = $this->balance;
        $regularInstallment = $this->installment_amount;

        // Determinar tipo de pago
        $result = [
            'payment_amount'       => $paymentAmount,
            'regular_installment'  => $regularInstallment,
            'remaining_balance'    => max(0, $currentBalance - $paymentAmount),
            'type'                 => 'regular',
            'message'              => '',
            'installments_covered' => 0,
            'excess_amount'        => 0,
        ];

        if ($paymentAmount > $currentBalance) {
            // Pago excesivo - paga todo el crédito
            $result['type']                 = 'full_payment';
            $result['excess_amount']        = $paymentAmount - $currentBalance;
            $result['remaining_balance']    = 0;
            $result['message']              = "Pago completo del crédito. Exceso: {$result['excess_amount']} Bs.";
            $result['installments_covered'] = $this->getPendingInstallments();

        } elseif ($paymentAmount >= $regularInstallment) {
            // Pago que cubre una o más cuotas
            $installmentsCovered            = floor($paymentAmount / $regularInstallment);
            $result['installments_covered'] = $installmentsCovered;
            $result['type']                 = $installmentsCovered > 1 ? 'multiple_installments' : 'regular';
            $result['message']              = "Pago cubre {$installmentsCovered} cuota(s).";

        } else {
            // Pago parcial
            $result['type']    = 'partial';
            $result['message'] = 'Pago parcial. Falta: ' . ($regularInstallment - $paymentAmount) . ' Bs para completar la cuota.';
        }

        return $result;
    }

    /**
     * Get payment schedule with real payment data
     */
    public function getPaymentSchedule(): array
    {
        $schedule          = [];
        $startDate         = Carbon::parse($this->start_date);
        $totalInstallments = $this->calculateTotalInstallments();
        $installmentAmount = $this->installment_amount;

        // ✅ OPTIMIZACIÓN: Una sola query para obtener todos los pagos agrupados por cuota
        // Incluye pagos completados Y parciales + información del cobrador
        $paymentsByInstallment = $this->payments()
            ->whereIn('status', ['completed', 'partial'])
            ->with(['receivedBy:id,name']) // Cargar solo id y nombre del cobrador
            ->select(
                'installment_number',
                \DB::raw('SUM(amount) as paid_amount'),
                \DB::raw('COUNT(*) as payment_count'),
                \DB::raw('MAX(payment_date) as last_payment_date'),
                \DB::raw('MAX(payment_method) as payment_method'),
                \DB::raw('MAX(received_by) as received_by_id'),
                \DB::raw('MAX(id) as payment_id') // ID del último pago para generar recibos
            )
            ->groupBy('installment_number')
            ->get()
            ->keyBy('installment_number');

        // Cargar nombres de cobradores en un mapa para referencia rápida
        $receivedByIds = $paymentsByInstallment->pluck('received_by_id')->filter()->unique()->values()->toArray();
        $cobradores = \App\Models\User::whereIn('id', $receivedByIds)
            ->select('id', 'name')
            ->get()
            ->keyBy('id');

        // start_date YA ES el primer día de pago (no necesita +1 día)
        // Esto cambió con la introducción de first_payment_today
        $currentDueDate = $startDate->copy();
        $today = Carbon::now();

        for ($i = 0; $i < $totalInstallments; $i++) {
            $installmentNumber = $i + 1;

            switch ($this->frequency) {
                case 'daily':
                    if ($i > 0) {
                        // A partir de la segunda cuota, avanzar al siguiente día hábil
                        $currentDueDate = $this->getNextBusinessDay($currentDueDate);
                    } else {
                        // Primera cuota: usar start_date ajustado si cae domingo
                        $currentDueDate = $this->adjustIfSunday($currentDueDate);
                    }
                    break;
                case 'weekly':
                    if ($i === 0) {
                        // Primera cuota: ajustar si cae domingo
                        $currentDueDate = $this->adjustIfSunday($currentDueDate);
                    } else {
                        // Siguientes cuotas: agregar una semana y ajustar si cae domingo
                        $currentDueDate = $currentDueDate->copy()->addWeek();
                        $currentDueDate = $this->adjustIfSunday($currentDueDate);
                    }
                    break;
                case 'biweekly':
                    if ($i === 0) {
                        // Primera cuota: siguiente día hábil después del start_date
                        $currentDueDate = $this->getNextBusinessDay($currentDueDate);
                    } else {
                        // Siguientes cuotas: agregar dos semanas y ajustar si cae domingo
                        $currentDueDate = $currentDueDate->copy()->addWeeks(2);
                        $currentDueDate = $this->adjustIfSunday($currentDueDate);
                    }
                    break;
                case 'monthly':
                    if ($i === 0) {
                        // Primera cuota: siguiente día hábil después del start_date
                        $currentDueDate = $this->getNextBusinessDay($currentDueDate);
                    } else {
                        // Siguientes cuotas: agregar un mes y ajustar si cae domingo
                        $currentDueDate = $currentDueDate->copy()->addMonth();
                        $currentDueDate = $this->adjustIfSunday($currentDueDate);
                    }
                    break;
            }

            // ✅ NUEVO: Obtener datos de pagos para esta cuota
            $paymentData = $paymentsByInstallment->get($installmentNumber);
            $paidAmount = $paymentData ? (float) $paymentData->paid_amount : 0.0;
            $paymentCount = $paymentData ? (int) $paymentData->payment_count : 0;
            $lastPaymentDate = $paymentData ? $paymentData->last_payment_date : null;
            $paymentMethod = $paymentData ? $paymentData->payment_method : null;
            $receivedById = $paymentData ? $paymentData->received_by_id : null;
            $paymentId = $paymentData ? $paymentData->payment_id : null; // ID del pago para recibos
            $receivedByName = $receivedById && isset($cobradores[$receivedById])
                ? $cobradores[$receivedById]->name
                : null;

            // ✅ NUEVO: Calcular estados
            $remainingAmount = $installmentAmount - $paidAmount;
            $isPaid = $paidAmount >= $installmentAmount;
            $isPartial = $paidAmount > 0 && $paidAmount < $installmentAmount;

            // ✅ NUEVO: Determinar status basado en datos reales
            $status = 'pending';
            if ($isPaid) {
                $status = 'paid';
            } elseif ($isPartial) {
                $status = 'partial';
            } elseif ($currentDueDate->startOfDay()->lt($today->startOfDay()) && !$isPaid) {
                // Solo marcar como overdue si la fecha es estrictamente anterior a hoy
                // (no incluye el día actual)
                $status = 'overdue';
            }

            $schedule[] = [
                'installment_number' => $installmentNumber,
                'due_date'           => $currentDueDate->format('Y-m-d'),
                'amount'             => $installmentAmount,

                // ✅ NUEVOS CAMPOS: Datos reales de pagos
                'paid_amount'        => $paidAmount,
                'remaining_amount'   => max(0, $remainingAmount),
                'is_paid'            => $isPaid,
                'is_partial'         => $isPartial,
                'status'             => $status,
                'payment_count'      => $paymentCount,
                'last_payment_date'  => $lastPaymentDate,
                'payment_method'     => $paymentMethod,
                'received_by_id'     => $receivedById,
                'received_by_name'   => $receivedByName,
                'payment_id'         => $paymentId, // ID del pago para generar recibos
            ];

            // Para pagos diarios, avanzar al siguiente día para la próxima iteración
            if ($this->frequency === 'daily') {
                $currentDueDate = $currentDueDate->copy()->addDay();
            }
        }

        return $schedule;
    }

    /**
     * Get the next business day (Monday to Saturday, excluding Sunday)
     */
    private function getNextBusinessDay(Carbon $date): Carbon
    {
        $businessDay = $date->copy();

        // Si es domingo (0), avanzar al lunes
        while ($businessDay->dayOfWeek === Carbon::SUNDAY) {
            $businessDay->addDay();
        }

        return $businessDay;
    }

    /**
     * Adjust date if it falls on Sunday (move to Monday)
     */
    private function adjustIfSunday(Carbon $date): Carbon
    {
        $adjustedDate = $date->copy();

        // Si es domingo (0), mover al lunes
        if ($adjustedDate->dayOfWeek === Carbon::SUNDAY) {
            $adjustedDate->addDay();
        }

        return $adjustedDate;
    }

    /**
     * Determine if the credit requires attention
     */
    public function requiresAttention(): bool
    {
        // Verificar si el crédito está vencido
        if ($this->end_date && Carbon::now()->isAfter($this->end_date)) {
            return true;
        }

        // Verificar si hay un alto balance pendiente (más del 80% del total)
        if ($this->balance && $this->total_amount) {
            $percentageRemaining = ($this->balance / $this->total_amount) * 100;
            if ($percentageRemaining > 80 && Carbon::now()->isAfter(Carbon::parse($this->start_date)->addDays(7))) {
                return true;
            }
        }

        // Verificar si está próximo a vencer (dentro de 3 días)
        if ($this->end_date && Carbon::now()->addDays(3)->isAfter($this->end_date)) {
            return true;
        }

        // Verificar si el status es 'overdue' o 'defaulted'
        if (in_array($this->status, ['overdue', 'defaulted'])) {
            return true;
        }

        return false;
    }

    /**
     * Auto-calculate fields before saving
     */
    protected static function booted()
    {
        static::saving(function ($credit) {
            // Auto-calcular total_amount si no está definido
            if (! $credit->total_amount && $credit->amount && $credit->interest_rate) {
                $credit->total_amount = $credit->calculateTotalAmount();
            }

            // Auto-calcular installment_amount si no está definido
            if (! $credit->installment_amount && $credit->total_amount) {
                $credit->installment_amount = $credit->calculateInstallmentAmount();
            }

            // Inicializar balance como total_amount si no está definido
            if (! $credit->balance && $credit->total_amount) {
                $credit->balance = $credit->total_amount;
            }
        });

        // Evento cuando un crédito se actualiza y puede requerir atención
        static::updated(function ($credit) {
            // Verificar si el crédito requiere atención (vencido, balance alto, etc.)
            if ($credit->requiresAttention()) {
                try {
                    $cobrador = $credit->client->assignedCobrador ?? $credit->createdBy;
                    if ($cobrador) {
                        // event(new \App\Events\CreditRequiresAttention($credit, $cobrador));
                    }
                } catch (\Exception $e) {
                    Log::error('Error dispatching CreditRequiresAttention event', [
                        'credit_id' => $credit->id,
                        'error'     => $e->getMessage(),
                    ]);
                }
            }
        });
    }

    // ===========================================
    // MÉTODOS PARA SISTEMA DE LISTA DE ESPERA
    // ===========================================

    /**
     * Approve credit for delivery and set scheduled delivery date
     */
    public function approveForDelivery(int $approvedById, \DateTime $scheduledDate, ?string $notes = null): bool
    {
        if ($this->status !== 'pending_approval') {
            return false;
        }

        $this->update([
            'status'                  => 'waiting_delivery',
            'approved_by'             => $approvedById,
            'approved_at'             => now(),
            'scheduled_delivery_date' => $scheduledDate,
            'delivery_notes'          => $notes,
        ]);

        return true;
    }

    /**
     * Reject the credit with a reason
     */
    public function reject(int $rejectedById, string $reason): bool
    {
        if (! in_array($this->status, ['pending_approval', 'waiting_delivery'])) {
            return false;
        }

        $this->update([
            'status'           => 'rejected',
            'approved_by'      => $rejectedById,
            'approved_at'      => now(),
            'rejection_reason' => $reason,
        ]);

        return true;
    }

    /**
     * Deliver the credit to the client (activate it)
     *
     * Este método se ejecuta cuando el cobrador confirma físicamente la entrega del dinero al cliente.
     * AQUÍ es donde se arma el cronograma de pagos:
     * - delivered_at = ahora (momento de la entrega física)
     * - start_date = día de entrega O día siguiente según $firstPaymentToday
     * - end_date = calculado según el plazo del crédito
     *
     * @param int $deliveredById ID del usuario que entrega el crédito
     * @param string|null $notes Notas adicionales sobre la entrega
     * @param bool $firstPaymentToday Si true, el primer pago es HOY (mismo día de entrega).
     *                                Si false, el primer pago es MAÑANA (día siguiente).
     * @return bool
     */
    public function deliverToClient(int $deliveredById, ?string $notes = null, bool $firstPaymentToday = false): bool
    {
        if ($this->status !== 'waiting_delivery') {
            return false;
        }

        $deliveredAt = now();

        // Decidir si el primer pago es hoy o mañana
        $startDate = $firstPaymentToday
            ? Carbon::parse($deliveredAt)      // Primer pago HOY
            : Carbon::parse($deliveredAt)->addDay(); // Primer pago MAÑANA (comportamiento anterior)

        // Calcular end_date basado en total_installments y frequency
        $endDate = $this->calculateEndDate($startDate);

        $this->update([
            'status'                => 'active',
            'delivered_by'          => $deliveredById,
            'delivered_at'          => $deliveredAt,
            'first_payment_today'   => $firstPaymentToday,
            'start_date'            => $startDate->toDateString(),
            'end_date'              => $endDate->toDateString(),
            'delivery_notes'        => $notes ? $this->delivery_notes . "\n\nEntrega: " . $notes : $this->delivery_notes,
        ]);

        return true;
    }

    /**
     * Calculate end_date based on start_date, total_installments and frequency
     *
     * IMPORTANTE: Usa la misma lógica que getPaymentSchedule() para calcular
     * la fecha de la última cuota, considerando días hábiles (lunes a sábado).
     */
    private function calculateEndDate(Carbon $startDate): Carbon
    {
        $totalInstallments = $this->total_installments ?? 0;

        if ($totalInstallments <= 0) {
            // Fallback: 30 días por defecto
            return $startDate->copy()->addDays(30);
        }

        // Simular el mismo algoritmo que getPaymentSchedule() para encontrar
        // la fecha de la última cuota
        $currentDueDate = $startDate->copy(); // start_date YA ES el primer día de pago

        for ($i = 0; $i < $totalInstallments; $i++) {
            switch ($this->frequency) {
                case 'daily':
                    if ($i > 0) {
                        // A partir de la segunda cuota, avanzar al siguiente día hábil
                        $currentDueDate = $this->getNextBusinessDay($currentDueDate);
                    } else {
                        // Primera cuota: ajustar si cae domingo
                        $currentDueDate = $this->adjustIfSunday($currentDueDate);
                    }
                    break;
                case 'weekly':
                    if ($i === 0) {
                        // Primera cuota: ajustar si cae domingo
                        $currentDueDate = $this->adjustIfSunday($currentDueDate);
                    } else {
                        // Siguientes cuotas: agregar una semana y ajustar si cae domingo
                        $currentDueDate = $currentDueDate->copy()->addWeek();
                        $currentDueDate = $this->adjustIfSunday($currentDueDate);
                    }
                    break;
                case 'biweekly':
                    if ($i === 0) {
                        // Primera cuota: ajustar si cae domingo
                        $currentDueDate = $this->adjustIfSunday($currentDueDate);
                    } else {
                        // Siguientes cuotas: agregar dos semanas y ajustar si cae domingo
                        $currentDueDate = $currentDueDate->copy()->addWeeks(2);
                        $currentDueDate = $this->adjustIfSunday($currentDueDate);
                    }
                    break;
                case 'monthly':
                    if ($i === 0) {
                        // Primera cuota: ajustar si cae domingo
                        $currentDueDate = $this->adjustIfSunday($currentDueDate);
                    } else {
                        // Siguientes cuotas: agregar un mes y ajustar si cae domingo
                        $currentDueDate = $currentDueDate->copy()->addMonth();
                        $currentDueDate = $this->adjustIfSunday($currentDueDate);
                    }
                    break;
                default:
                    // Fallback: días simples
                    $currentDueDate->addDay();
            }

            // Para pagos diarios, avanzar al siguiente día para la próxima iteración
            // (igual que en getPaymentSchedule para mantener consistencia)
            if ($this->frequency === 'daily' && $i < $totalInstallments - 1) {
                $currentDueDate = $currentDueDate->copy()->addDay();
            }
        }

        // La fecha de fin es la fecha de la última cuota
        return $currentDueDate;
    }

    /**
     * Check if the credit is ready for delivery (scheduled date reached)
     */
    public function isReadyForDelivery(): bool
    {
        return $this->status === 'waiting_delivery'
        && $this->scheduled_delivery_date
        && $this->scheduled_delivery_date <= now();
    }

    /**
     * Check if the credit is overdue for delivery
     */
    public function isOverdueForDelivery(): bool
    {
        return $this->status === 'waiting_delivery'
        && $this->scheduled_delivery_date
        && $this->scheduled_delivery_date < now()->subDays(1); // 1 día de gracia
    }

    /**
     * Get days until scheduled delivery
     */
    public function getDaysUntilDelivery(): int
    {
        if (! $this->scheduled_delivery_date || $this->status !== 'waiting_delivery') {
            return 0;
        }

        return max(0, now()->diffInDays($this->scheduled_delivery_date, false));
    }

    /**
     * Get days overdue for delivery
     */
    public function getDaysOverdueForDelivery(): int
    {
        if (! $this->scheduled_delivery_date || $this->status !== 'waiting_delivery') {
            return 0;
        }

        $daysPast = now()->diffInDays($this->scheduled_delivery_date, false);

        return $daysPast < 0 ? abs($daysPast) : 0;
    }

    /**
     * Reschedule delivery date
     */
    public function rescheduleDelivery(\DateTime $newDate, int $rescheduledById, ?string $reason = null): bool
    {
        if ($this->status !== 'waiting_delivery') {
            return false;
        }

        $oldDate = $this->scheduled_delivery_date;
        $notes   = $this->delivery_notes ?? '';
        $notes .= "\n\nReprogramado por usuario {$rescheduledById}: " . $oldDate->format('Y-m-d H:i') . ' -> ' . $newDate->format('Y-m-d H:i');
        if ($reason) {
            $notes .= "\nMotivo: " . $reason;
        }

        $this->update([
            'scheduled_delivery_date' => $newDate,
            'delivery_notes'          => $notes,
        ]);

        return true;
    }

    /**
     * Get credits waiting for delivery
     */
    public static function waitingForDelivery()
    {
        return self::where('status', 'waiting_delivery')
            ->with(['client', 'createdBy', 'approvedBy'])
            ->orderBy('scheduled_delivery_date', 'asc');
    }

    /**
     * Get credits ready for delivery today
     */
    public static function readyForDeliveryToday()
    {
        return self::waitingForDelivery()
            ->whereDate('scheduled_delivery_date', '<=', now())
            ->get();
    }

    /**
     * Get credits overdue for delivery
     */
    public static function overdueForDelivery()
    {
        return self::waitingForDelivery()
            ->where('scheduled_delivery_date', '<', now()->subDays(1))
            ->get();
    }

    /**
     * Get credits pending approval
     */
    public static function pendingApproval()
    {
        return self::where('status', 'pending_approval')
            ->with(['client', 'createdBy'])
            ->orderBy('created_at', 'asc');
    }

    /**
     * Check if user can approve credits (manager or admin)
     */
    public static function userCanApprove(User $user): bool
    {
        return $user->hasRole(['manager', 'admin']);
    }

    /**
     * Check if user can deliver credits (cobrador, manager, or admin)
     */
    public static function userCanDeliver(User $user): bool
    {
        return $user->hasRole(['cobrador', 'manager', 'admin']);
    }

    /**
     * Get delivery status information
     */
    public function getDeliveryStatusInfo(): array
    {
        return [
            'status'                    => $this->status,
            'is_pending_approval'       => $this->status === 'pending_approval',
            'is_waiting_delivery'       => $this->status === 'waiting_delivery',
            'is_active'                 => $this->status === 'active',
            'is_ready_for_delivery'     => $this->isReadyForDelivery(),
            'is_overdue_for_delivery'   => $this->isOverdueForDelivery(),
            'days_until_delivery'       => $this->getDaysUntilDelivery(),
            'days_overdue_for_delivery' => $this->getDaysOverdueForDelivery(),
            'scheduled_delivery_date'   => $this->scheduled_delivery_date,
            'approved_by'               => $this->approvedBy,
            'approved_at'               => $this->approved_at,
            'delivered_by'              => $this->deliveredBy,
            'delivered_at'              => $this->delivered_at,
            'delivery_notes'            => $this->delivery_notes,
            'rejection_reason'          => $this->rejection_reason,
        ];
    }

    /**
     * Recalcular balance, total_paid y paid_installments basado en pagos reales
     * Esto asegura coherencia entre los datos del crédito y sus pagos
     *
     * @return bool True si se realizaron cambios, False si ya estaba coherente
     */
    public function recalculateBalance(): bool
    {
        $hasChanges = false;

        // Calcular total pagado desde TODOS los pagos (completados y parciales)
        $calculatedTotalPaid = $this->payments()
            ->whereIn('status', ['completed', 'partial'])
            ->sum('amount');

        // Calcular número de cuotas COMPLETAMENTE pagadas (solo completed)
        $calculatedPaidInstallments = $this->payments()
            ->where('status', 'completed')
            ->count();

        // Calcular balance correcto
        $calculatedBalance = $this->total_amount - $calculatedTotalPaid;

        // Verificar y actualizar total_paid
        if (abs($this->total_paid - $calculatedTotalPaid) > 0.01) {
            $this->total_paid = $calculatedTotalPaid;
            $hasChanges = true;
        }

        // Verificar y actualizar balance
        if (abs($this->balance - $calculatedBalance) > 0.01) {
            $this->balance = $calculatedBalance;
            $hasChanges = true;
        }

        // Verificar y actualizar paid_installments
        if ($this->paid_installments != $calculatedPaidInstallments) {
            $this->paid_installments = $calculatedPaidInstallments;
            $hasChanges = true;
        }

        // Actualizar estado si es necesario
        if ($this->balance <= 0 && $this->status !== 'completed') {
            $this->status = 'completed';
            $this->completed_at = now(); // ⭐ Registrar fecha de completado
            $hasChanges = true;
        } elseif ($this->balance > 0 && $this->status === 'completed') {
            $this->status = 'active';
            $this->completed_at = null; // ⭐ Limpiar fecha si se revierte
            $hasChanges = true;
        }

        // Guardar si hubo cambios
        if ($hasChanges) {
            $this->save();
            Log::info("Credit #{$this->id} balance recalculated", [
                'total_paid' => $calculatedTotalPaid,
                'balance' => $calculatedBalance,
                'paid_installments' => $calculatedPaidInstallments,
                'status' => $this->status,
                'completed_at' => $this->completed_at?->toDateTimeString(),
            ]);
        }

        return $hasChanges;
    }

    /**
     * Verificar si el crédito tiene inconsistencias en su balance
     *
     * @return array Array de inconsistencias encontradas (vacío si está coherente)
     */
    public function validateBalance(): array
    {
        $issues = [];

        // Calcular valores correctos
        $realTotalPaid = $this->payments()->where('status', 'completed')->sum('amount');
        $realPaidCount = $this->payments()->where('status', 'completed')->count();
        $expectedBalance = $this->total_amount - $realTotalPaid;

        // Verificar total_paid
        if (abs($this->total_paid - $realTotalPaid) > 0.01) {
            $diff = $this->total_paid - $realTotalPaid;
            $issues[] = [
                'field' => 'total_paid',
                'current' => $this->total_paid,
                'expected' => $realTotalPaid,
                'difference' => $diff,
                'message' => "total_paid debería ser {$realTotalPaid} pero es {$this->total_paid}",
            ];
        }

        // Verificar balance
        if (abs($this->balance - $expectedBalance) > 0.01) {
            $diff = $this->balance - $expectedBalance;
            $issues[] = [
                'field' => 'balance',
                'current' => $this->balance,
                'expected' => $expectedBalance,
                'difference' => $diff,
                'message' => "balance debería ser {$expectedBalance} pero es {$this->balance}",
            ];
        }

        // Verificar paid_installments
        if ($this->paid_installments != $realPaidCount) {
            $issues[] = [
                'field' => 'paid_installments',
                'current' => $this->paid_installments,
                'expected' => $realPaidCount,
                'difference' => $this->paid_installments - $realPaidCount,
                'message' => "paid_installments debería ser {$realPaidCount} pero es {$this->paid_installments}",
            ];
        }

        // Verificar estado
        if ($this->balance <= 0 && $this->status === 'active') {
            $issues[] = [
                'field' => 'status',
                'current' => $this->status,
                'expected' => 'completed',
                'message' => "El crédito debería estar 'completed' (balance: {$this->balance})",
            ];
        }

        if ($this->balance > 0 && $this->status === 'completed') {
            $issues[] = [
                'field' => 'status',
                'current' => $this->status,
                'expected' => 'active',
                'message' => "El crédito no debería estar 'completed' (balance: {$this->balance})",
            ];
        }

        return $issues;
    }
}
