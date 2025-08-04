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
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
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
                return $startDate->diffInDays($endDate) + 1;
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
     * Process a payment (can be partial, exact, or excess)
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
    }
} 