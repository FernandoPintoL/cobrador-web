<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Credit extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'created_by',
        'amount',
        'interest_rate',
        'total_amount',
        'balance',
        'installment_amount',
        'frequency',
        'start_date',
        'end_date',
        'status',
        'scheduled_delivery_date',
        'approved_by',
        'approved_at',
        'delivered_at',
        'delivered_by',
        'delivery_notes',
        'rejection_reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'scheduled_delivery_date' => 'datetime',
        'approved_at' => 'datetime',
        'delivered_at' => 'datetime',
        'amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'installment_amount' => 'decimal:2',
    ];

    /**
     * Get the client that owns this credit.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
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
     * Get the payments for this credit.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the remaining installments for this credit.
     */
    public function getRemainingInstallments(): int
    {
        $totalInstallments = $this->calculateTotalInstallments();
        $paidInstallments = $this->payments()->where('status', 'completed')->count();
        
        return max(0, $totalInstallments - $paidInstallments);
    }

    /**
     * Calculate the total number of installments based on frequency and dates.
     */
    private function calculateTotalInstallments(): int
    {
        $startDate = Carbon::parse($this->start_date);
        $endDate = Carbon::parse($this->end_date);
        
        switch ($this->frequency) {
            case 'daily':
                return $startDate->diffInDays($endDate);
            case 'weekly':
                return $startDate->diffInWeeks($endDate) + 1;
            case 'biweekly':
                return $startDate->diffInWeeks($endDate) / 2 + 1;
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
        $totalAmount = $this->total_amount ?? $this->calculateTotalAmount();
        
        return $totalInstallments > 0 ? $totalAmount / $totalInstallments : 0;
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
     * Get current balance (remaining amount to pay)
     */
    public function getCurrentBalance(): float
    {
        $totalAmount = $this->total_amount ?? $this->calculateTotalAmount();
        $paidAmount = $this->getTotalPaidAmount();
        
        return max(0, $totalAmount - $paidAmount);
    }

    /**
     * Get pending installments count
     */
    public function getPendingInstallments(): int
    {
        $totalInstallments = $this->calculateTotalInstallments();
        $completedPayments = $this->payments()->where('status', 'completed')->count();
        
        return max(0, $totalInstallments - $completedPayments);
    }

    /**
     * Get expected installments based on current date
     */
    public function getExpectedInstallments(): int
    {
        $startDate = Carbon::parse($this->start_date);
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
        $completedPayments = $this->payments()->where('status', 'completed')->count();
        
        return $completedPayments < $expectedInstallments;
    }

    /**
     * Get overdue amount
     */
    public function getOverdueAmount(): float
    {
        if (!$this->isOverdue()) {
            return 0;
        }
        
        $expectedInstallments = $this->getExpectedInstallments();
        $completedPayments = $this->payments()->where('status', 'completed')->count();
        $overdueInstallments = $expectedInstallments - $completedPayments;
        
        return $overdueInstallments * $this->installment_amount;
    }

    /**
     * Procesar un pago (puede ser parcial, exacto o en exceso)
     */
    public function processPayment(float $paymentAmount, string $paymentType = 'regular'): array
    {
        $currentBalance = $this->getCurrentBalance();
        $regularInstallment = $this->installment_amount;
        
        // Determinar tipo de pago
        $result = [
            'payment_amount' => $paymentAmount,
            'regular_installment' => $regularInstallment,
            'remaining_balance' => max(0, $currentBalance - $paymentAmount),
            'type' => 'regular',
            'message' => '',
            'installments_covered' => 0,
            'excess_amount' => 0,
        ];
        
        if ($paymentAmount > $currentBalance) {
            // Pago excesivo - paga todo el crédito
            $result['type'] = 'full_payment';
            $result['excess_amount'] = $paymentAmount - $currentBalance;
            $result['remaining_balance'] = 0;
            $result['message'] = "Pago completo del crédito. Exceso: {$result['excess_amount']} Bs.";
            $result['installments_covered'] = $this->getPendingInstallments();
            
        } elseif ($paymentAmount >= $regularInstallment) {
            // Pago que cubre una o más cuotas
            $installmentsCovered = floor($paymentAmount / $regularInstallment);
            $result['installments_covered'] = $installmentsCovered;
            $result['type'] = $installmentsCovered > 1 ? 'multiple_installments' : 'regular';
            $result['message'] = "Pago cubre {$installmentsCovered} cuota(s).";
            
        } else {
            // Pago parcial
            $result['type'] = 'partial';
            $result['message'] = "Pago parcial. Falta: " . ($regularInstallment - $paymentAmount) . " Bs para completar la cuota.";
        }
        
        return $result;
    }

    /**
     * Get payment schedule
     */
    public function getPaymentSchedule(): array
    {
        $schedule = [];
        $startDate = Carbon::parse($this->start_date);
        $totalInstallments = $this->calculateTotalInstallments();
        $installmentAmount = $this->installment_amount;
        
        for ($i = 0; $i < $totalInstallments; $i++) {
            $dueDate = clone $startDate;
            
            switch ($this->frequency) {
                case 'daily':
                    $dueDate->addDays($i);
                    break;
                case 'weekly':
                    $dueDate->addWeeks($i);
                    break;
                case 'biweekly':
                    $dueDate->addWeeks($i * 2);
                    break;
                case 'monthly':
                    $dueDate->addMonths($i);
                    break;
            }
            
            $schedule[] = [
                'installment_number' => $i + 1,
                'due_date' => $dueDate->format('Y-m-d'),
                'amount' => $installmentAmount,
                'status' => 'pending', // Se puede actualizar con pagos reales
            ];
        }
        
        return $schedule;
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
            if (!$credit->total_amount && $credit->amount && $credit->interest_rate) {
                $credit->total_amount = $credit->calculateTotalAmount();
            }
            
            // Auto-calcular installment_amount si no está definido
            if (!$credit->installment_amount && $credit->total_amount) {
                $credit->installment_amount = $credit->calculateInstallmentAmount();
            }
            
            // Inicializar balance como total_amount si no está definido
            if (!$credit->balance && $credit->total_amount) {
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
                        event(new \App\Events\CreditRequiresAttention($credit, $cobrador));
                    }
                } catch (\Exception $e) {
                    \Log::error('Error dispatching CreditRequiresAttention event', [
                        'credit_id' => $credit->id,
                        'error' => $e->getMessage()
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
    public function approveForDelivery(int $approvedById, \DateTime $scheduledDate, string $notes = null): bool
    {
        if ($this->status !== 'pending_approval') {
            return false;
        }

        $this->update([
            'status' => 'waiting_delivery',
            'approved_by' => $approvedById,
            'approved_at' => now(),
            'scheduled_delivery_date' => $scheduledDate,
            'delivery_notes' => $notes
        ]);

        return true;
    }

    /**
     * Reject the credit with a reason
     */
    public function reject(int $rejectedById, string $reason): bool
    {
        if (!in_array($this->status, ['pending_approval', 'waiting_delivery'])) {
            return false;
        }

        $this->update([
            'status' => 'rejected',
            'approved_by' => $rejectedById,
            'approved_at' => now(),
            'rejection_reason' => $reason
        ]);

        return true;
    }

    /**
     * Deliver the credit to the client (activate it)
     */
    public function deliverToClient(int $deliveredById, string $notes = null): bool
    {
        if ($this->status !== 'waiting_delivery') {
            return false;
        }

        $this->update([
            'status' => 'active',
            'delivered_by' => $deliveredById,
            'delivered_at' => now(),
            'delivery_notes' => $notes ? $this->delivery_notes . "\n\nEntrega: " . $notes : $this->delivery_notes
        ]);

        return true;
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
        if (!$this->scheduled_delivery_date || $this->status !== 'waiting_delivery') {
            return 0;
        }

        return max(0, now()->diffInDays($this->scheduled_delivery_date, false));
    }

    /**
     * Get days overdue for delivery
     */
    public function getDaysOverdueForDelivery(): int
    {
        if (!$this->scheduled_delivery_date || $this->status !== 'waiting_delivery') {
            return 0;
        }

        $daysPast = now()->diffInDays($this->scheduled_delivery_date, false);
        return $daysPast < 0 ? abs($daysPast) : 0;
    }

    /**
     * Reschedule delivery date
     */
    public function rescheduleDelivery(\DateTime $newDate, int $rescheduledById, string $reason = null): bool
    {
        if ($this->status !== 'waiting_delivery') {
            return false;
        }

        $oldDate = $this->scheduled_delivery_date;
        $notes = $this->delivery_notes ?? '';
        $notes .= "\n\nReprogramado por usuario {$rescheduledById}: " . $oldDate->format('Y-m-d H:i') . " -> " . $newDate->format('Y-m-d H:i');
        if ($reason) {
            $notes .= "\nMotivo: " . $reason;
        }

        $this->update([
            'scheduled_delivery_date' => $newDate,
            'delivery_notes' => $notes
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
            'status' => $this->status,
            'is_pending_approval' => $this->status === 'pending_approval',
            'is_waiting_delivery' => $this->status === 'waiting_delivery',
            'is_active' => $this->status === 'active',
            'is_ready_for_delivery' => $this->isReadyForDelivery(),
            'is_overdue_for_delivery' => $this->isOverdueForDelivery(),
            'days_until_delivery' => $this->getDaysUntilDelivery(),
            'days_overdue_for_delivery' => $this->getDaysOverdueForDelivery(),
            'scheduled_delivery_date' => $this->scheduled_delivery_date,
            'approved_by' => $this->approvedBy,
            'approved_at' => $this->approved_at,
            'delivered_by' => $this->deliveredBy,
            'delivered_at' => $this->delivered_at,
            'delivery_notes' => $this->delivery_notes,
            'rejection_reason' => $this->rejection_reason,
        ];
    }
} 