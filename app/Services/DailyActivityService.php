<?php

namespace App\Services;

use App\DTOs\DailyActivityDTO;
use App\Models\Credit;
use App\Models\CashBalance;
use App\Models\Payment;
use App\Traits\AuthorizeReportAccessTrait;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * DailyActivityService - Servicio Centralizado de Reportes de Actividad Diaria
 *
 * ✅ SEGURIDAD:
 * - Usa AuthorizeReportAccessTrait para autorización centralizada
 * - Cobrador: Ve su propia actividad diaria
 * - Manager: Ve actividad diaria de sus cobradores asignados
 * - Admin: Ve actividad diaria de todos
 */
class DailyActivityService
{
    use AuthorizeReportAccessTrait;
    public function generateReport(array $filters, object $currentUser): DailyActivityDTO
    {
        $date = $filters['date'] ?? now()->format('Y-m-d');
        $dateCarbon = Carbon::createFromFormat('Y-m-d', $date);

        // Obtener todos los cobradores relevantes
        $cobradores = $this->getRelevantCobradores($currentUser, $date);

        // Obtener datos de actividades
        $activities = $this->buildActivitiesData($cobradores, $date, $currentUser);

        // Construir resumen general
        $summary = $this->buildSummary($activities, $dateCarbon, $cobradores);

        return new DailyActivityDTO(
            data: $activities,
            summary: $summary,
            generated_at: now()->format('Y-m-d H:i:s'),
            generated_by: $currentUser->name,
        );
    }

    private function getRelevantCobradores(object $currentUser, string $date)
    {
        if ($currentUser->hasRole('cobrador')) {
            return collect([$currentUser]);
        } elseif ($currentUser->hasRole('manager')) {
            return $currentUser->assignedCobradores()->get();
        }
        // Admin puede ver todos
        return \App\Models\User::whereHas('roles', function ($q) {
            $q->where('name', 'cobrador');
        })->get();
    }

    private function buildActivitiesData($cobradores, string $date, object $currentUser): Collection
    {
        return $cobradores->map(function ($cobrador) use ($date) {
            // Pagos cobrados por este cobrador
            $payments = Payment::with(['credit.client', 'cobrador'])
                ->whereDate('payment_date', $date)
                ->where('cobrador_id', $cobrador->id)
                ->get();

            // Créditos entregados por este cobrador
            $credits = Credit::with(['client', 'deliveredBy'])
                ->whereDate('delivered_at', $date)
                ->where('delivered_by', $cobrador->id)
                ->where('status', '!=', 'rejected')
                ->get();

            // Estado de caja
            $cashBalance = CashBalance::where('cobrador_id', $cobrador->id)
                ->whereDate('date', $date)
                ->first();

            // Créditos activos creados/asignados a este cobrador
            $createdCredits = Credit::where('created_by', $cobrador->id)
                ->where('status', 'active')
                ->get();

            $totalExpected = $createdCredits->count();
            $collected = $payments->count();
            $pending = $totalExpected - $collected;
            $efficiency = $totalExpected > 0 ? round(($collected / $totalExpected) * 100, 2) : 0;

            return [
                'cobrador_name' => $cobrador->name,
                'cobrador_id' => $cobrador->id,
                'cash_balance' => [
                    'status' => $cashBalance?->status ?? 'open',
                    'initial_amount' => (float)($cashBalance?->initial_amount ?? 0),
                    'collected_amount' => (float)$payments->sum('amount'),
                    'lent_amount' => (float)($cashBalance?->lent_amount ?? 0),
                    'final_amount' => (float)($cashBalance?->final_amount ?? 0),
                ],
                'credits_delivered' => [
                    'count' => $credits->count(),
                    'details' => $credits->map(fn($c) => [
                        'id' => $c->id,
                        'client' => $c->client?->name ?? 'N/A',
                        'amount' => (float)$c->amount,
                    ])->toArray(),
                ],
                'payments_collected' => [
                    'count' => $payments->count(),
                    'details' => $payments->map(fn($p) => [
                        'id' => $p->id,
                        'client' => $p->credit?->client?->name ?? 'N/A',
                        'amount' => (float)$p->amount,
                    ])->toArray(),
                ],
                'expected_payments' => [
                    'count' => $totalExpected,
                    'collected' => $collected,
                    'pending' => $pending,
                    'efficiency' => $efficiency,
                ],
                '_model' => $cobrador,
            ];
        });
    }

    private function buildSummary($activities, Carbon $dateCarbon, $cobradores): array
    {
        $totalCreditsDelivered = 0;
        $totalAmountLent = 0;
        $totalPaymentsCollected = 0;
        $totalAmountCollected = 0;
        $totalExpected = 0;
        $totalCollected = 0;
        $cashOpenedCount = 0;
        $cashClosedCount = 0;

        foreach ($activities as $activity) {
            $totalCreditsDelivered += $activity['credits_delivered']['count'];
            $totalAmountLent += array_sum(array_column($activity['credits_delivered']['details'], 'amount'));
            $totalPaymentsCollected += $activity['payments_collected']['count'];
            $totalAmountCollected += array_sum(array_column($activity['payments_collected']['details'], 'amount'));
            $totalExpected += $activity['expected_payments']['count'];
            $totalCollected += $activity['expected_payments']['collected'];

            if ($activity['cash_balance']['status'] === 'open') {
                $cashOpenedCount++;
            } else {
                $cashClosedCount++;
            }
        }

        $overallEfficiency = $totalExpected > 0 ? round(($totalCollected / $totalExpected) * 100, 2) : 0;

        return [
            'date' => $dateCarbon->format('Y-m-d'),
            'day_name' => $this->getDayName($dateCarbon),
            'total_cobradores' => $cobradores->count(),
            'totals' => [
                'credits_delivered' => $totalCreditsDelivered,
                'amount_lent' => round($totalAmountLent, 2),
                'payments_collected' => $totalPaymentsCollected,
                'amount_collected' => round($totalAmountCollected, 2),
                'expected_payments' => $totalExpected,
                'pending_deliveries' => $totalExpected - $totalCollected,
            ],
            'overall_efficiency' => $overallEfficiency,
            'cash_balances' => [
                'opened' => $cashOpenedCount,
                'closed' => $cashClosedCount,
            ],
        ];
    }

    private function getDayName(Carbon $date): string
    {
        $days = [
            'Monday' => 'Lunes',
            'Tuesday' => 'Martes',
            'Wednesday' => 'Miércoles',
            'Thursday' => 'Jueves',
            'Friday' => 'Viernes',
            'Saturday' => 'Sábado',
            'Sunday' => 'Domingo',
        ];

        return $days[$date->englishDayOfWeek] ?? $date->format('l');
    }
}
