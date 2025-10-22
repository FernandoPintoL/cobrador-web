<?php
namespace App\Models;

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
    use HasFactory;

    protected $fillable = [
        'client_id',
        'created_by',
        'amount',
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
        'delivered_by',
        'delivery_notes',
        'rejection_reason',
        'interest_rate_id',
        'total_installments',
        'paid_installments',
        'latitude',
        'longitude',
        'immediate_delivery_requested',
        'cash_balance_id',
    ];

    protected $casts = [
        'start_date'                   => 'date',
        'end_date'                     => 'date',
        'scheduled_delivery_date'      => 'datetime',
        'immediate_delivery_requested' => 'boolean',
        'approved_at'                  => 'datetime',
        'delivered_at'                 => 'datetime',
        'amount'                       => 'decimal:2',
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
     */
    public function calculateTotalAmount(): float
    {
        $interestAmount = $this->amount * ($this->interest_rate / 100);

        return $this->amount + $interestAmount;
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
     */
    public function getExpectedInstallments(): int
    {
        $startDate   = Carbon::parse($this->start_date);
        $currentDate = Carbon::now();

        if ($currentDate->lt($startDate)) {
            return 0;
        }

        switch ($this->frequency) {
            case 'daily':
                return $startDate->diffInDays($currentDate) + 1;
            case 'weekly':
                return $startDate->diffInWeeks($currentDate) + 1;
            case 'biweekly':
                return floor($startDate->diffInWeeks($currentDate) / 2) + 1;
            case 'monthly':
                return $startDate->diffInMonths($currentDate) + 1;
            default:
                return 0;
        }
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
     * Get payment schedule
     */
    public function getPaymentSchedule(): array
    {
        $schedule          = [];
        $startDate         = Carbon::parse($this->start_date);
        $totalInstallments = $this->calculateTotalInstallments();
        $installmentAmount = $this->installment_amount;

        // Comenzar desde el día siguiente al registro
        $currentDueDate = $startDate->copy()->addDay();

        for ($i = 0; $i < $totalInstallments; $i++) {
            switch ($this->frequency) {
                case 'daily':
                    // Para pagos diarios, encontrar el siguiente día hábil (lunes a sábado)
                    $currentDueDate = $this->getNextBusinessDay($currentDueDate);
                    break;
                case 'weekly':
                    if ($i === 0) {
                        // Primera cuota: siguiente día hábil después del start_date
                        $currentDueDate = $this->getNextBusinessDay($currentDueDate);
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

            $schedule[] = [
                'installment_number' => $i + 1,
                'due_date'           => $currentDueDate->format('Y-m-d'),
                'amount'             => $installmentAmount,
                'status'             => 'pending', // Se puede actualizar con pagos reales
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
     * - start_date = día siguiente a la entrega (primer día de pagos)
     * - end_date = calculado según el plazo del crédito
     */
    public function deliverToClient(int $deliveredById, ?string $notes = null): bool
    {
        if ($this->status !== 'waiting_delivery') {
            return false;
        }

        $deliveredAt = now();

        // El cronograma de pagos comienza el día siguiente a la entrega física
        $startDate = Carbon::parse($deliveredAt)->addDay();

        // Calcular end_date basado en total_installments y frequency
        $endDate = $this->calculateEndDate($startDate);

        $this->update([
            'status'         => 'active',
            'delivered_by'   => $deliveredById,
            'delivered_at'   => $deliveredAt,
            'start_date'     => $startDate->toDateString(),
            'end_date'       => $endDate->toDateString(),
            'delivery_notes' => $notes ? $this->delivery_notes . "\n\nEntrega: " . $notes : $this->delivery_notes,
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
        $currentDueDate = $startDate->copy()->addDay();

        for ($i = 0; $i < $totalInstallments; $i++) {
            switch ($this->frequency) {
                case 'daily':
                    // Para pagos diarios, encontrar el siguiente día hábil (lunes a sábado)
                    $currentDueDate = $this->getNextBusinessDay($currentDueDate);
                    break;
                case 'weekly':
                    if ($i === 0) {
                        // Primera cuota: siguiente día hábil después del start_date
                        $currentDueDate = $this->getNextBusinessDay($currentDueDate);
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
                default:
                    // Fallback: días simples
                    $currentDueDate->addDay();
            }

            // Para pagos diarios, avanzar al siguiente día para la próxima iteración
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
}
