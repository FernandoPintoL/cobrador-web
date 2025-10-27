<?php

namespace App\Services;

use App\DTOs\LocationClusterDTO;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Servicio para agrupar clientes por ubicación geográfica
 * Combina múltiples personas en la misma casa/ubicación
 *
 * FLUJO:
 * 1. Obtener todos los clientes (filtrados por rol)
 * 2. Agrupar por coordenadas (lat, lng)
 * 3. Para cada grupo: calcular totales y información de personas
 * 4. Retornar DTOs listos para JSON
 */
class LocationClusteringService
{
    /**
     * Generar clusters de ubicaciones basado en coordenadas
     *
     * @param array $filters Filtros opcionales (status, cobrador_id, etc.)
     * @param User $currentUser Usuario autenticado para aplicar permisos
     * @return Collection|LocationClusterDTO[]
     */
    public function generateLocationClusters(array $filters = [], User $currentUser = null): Collection
    {
        // Obtener query base de clientes
        $query = $this->buildClientQuery($filters, $currentUser);

        // Obtener todos los clientes con sus relaciones
        $clients = $query
            ->with([
                'credits' => function ($q) {
                    $q->where('status', '!=', 'rejected'); // Excluir créditos rechazados
                },
                'credits.payments' => function ($q) {
                    // Cargar todos los pagos de cada crédito
                    $q->orderBy('payment_date', 'desc');
                },
                'payments' => function ($q) {
                    // Cargar pagos generales del cliente
                    $q->orderBy('payment_date', 'desc');
                }
            ])
            ->get();

        // Agrupar por ubicación (lat, lng)
        $grouped = $clients->groupBy(function ($client) {
            // Crear clave única basada en coordenadas redondeadas a 6 decimales
            // (suficiente precisión para diferenciar casas)
            return round($client->latitude, 6) . ',' . round($client->longitude, 6);
        });

        // Convertir grupos a DTOs
        return $grouped->map(function ($clientsInLocation, $locationKey) {
            return $this->buildClusterDTO($clientsInLocation, $locationKey);
        })->values(); // Resetear índices
    }

    /**
     * Construir query base respetando permisos por rol
     */
    private function buildClientQuery(array $filters, User $currentUser): Builder
    {
        $query = User::role('client')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        // Aplicar filtros de rol
        if ($currentUser && !$currentUser->hasRole('admin')) {
            if ($currentUser->hasRole('cobrador')) {
                $query->where('assigned_cobrador_id', $currentUser->id);
            } elseif ($currentUser->hasRole('manager')) {
                $cobradorIds = User::role('cobrador')
                    ->where('assigned_manager_id', $currentUser->id)
                    ->pluck('id');

                $query->where(function ($q) use ($currentUser, $cobradorIds) {
                    $q->where('assigned_manager_id', $currentUser->id)
                        ->orWhereIn('assigned_cobrador_id', $cobradorIds);
                });
            }
        }

        // Aplicar filtros de estado de pago
        if (isset($filters['status'])) {
            $this->applyStatusFilter($query, $filters['status']);
        }

        // Aplicar filtro por cobrador
        if (isset($filters['cobrador_id'])) {
            $query->where('assigned_cobrador_id', $filters['cobrador_id']);
        }

        // Aplicar filtros de datos del cliente (case-insensitive)
        if (isset($filters['nombre'])) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($filters['nombre']) . '%']);
        }

        if (isset($filters['telefono'])) {
            $query->whereRaw('LOWER(phone) LIKE ?', ['%' . strtolower($filters['telefono']) . '%']);
        }

        if (isset($filters['ci'])) {
            $query->whereRaw('LOWER(ci) LIKE ?', ['%' . strtolower($filters['ci']) . '%']);
        }

        if (isset($filters['categoria_cliente'])) {
            // Validar que sea una categoría válida (normalizar a mayúsculas)
            $validCategories = [User::CLIENT_CATEGORY_VIP, User::CLIENT_CATEGORY_NORMAL, User::CLIENT_CATEGORY_BAD];
            $categoria = strtoupper($filters['categoria_cliente']);
            if (in_array($categoria, $validCategories)) {
                $query->where('client_category', $categoria);
            }
        }

        return $query;
    }

    /**
     * Aplicar filtro de estado de pago
     */
    private function applyStatusFilter(Builder $query, string $status): void
    {
        if ($status === 'overdue') {
            $query->whereHas('payments', function ($q) {
                $q->where('status', 'overdue');
            });
        } elseif ($status === 'pending') {
            $query->whereHas('payments', function ($q) {
                $q->where('status', 'pending');
            });
        } elseif ($status === 'paid') {
            $query->whereDoesntHave('payments', function ($q) {
                $q->whereIn('status', ['pending', 'overdue']);
            });
        }
    }

    /**
     * Construir DTO desde un grupo de clientes en la misma ubicación
     */
    private function buildClusterDTO(Collection $clientsInLocation, string $locationKey): LocationClusterDTO
    {
        // Obtener primer cliente para coordenadas y dirección
        $firstClient = $clientsInLocation->first();

        // Construir información de personas
        $people = $clientsInLocation->map(function ($person) {
            return $this->buildPersonData($person);
        })->values()->all();

        // Calcular resumen del cluster
        $clusterSummary = $this->calculateClusterSummary($clientsInLocation);

        // Determinar estado general del cluster (overdue > pending > paid)
        $clusterStatus = $this->determineClusterStatus($clientsInLocation);

        return new LocationClusterDTO(
            cluster_id: $locationKey,
            location: [
                'latitude'  => (float) $firstClient->latitude,
                'longitude' => (float) $firstClient->longitude,
                'address'   => $firstClient->address ?? 'Sin dirección',
            ],
            cluster_summary: $clusterSummary,
            cluster_status: $clusterStatus,
            people: $people,
        );
    }

    /**
     * Construir datos de una persona (con sus créditos y pagos detallados)
     */
    private function buildPersonData(User $person): array
    {
        // Cargar pagos si no están ya cargados
        if (!$person->relationLoaded('payments')) {
            $person->load(['payments']);
        }

        // Calcular estado de la persona
        $hasOverdue = $person->payments->where('status', 'overdue')->count() > 0;
        $hasPending = $person->payments->where('status', 'pending')->count() > 0;
        $personStatus = $hasOverdue ? 'overdue' : ($hasPending ? 'pending' : 'paid');

        return [
            // Información del cliente
            'person_id'           => $person->id,
            'name'                => $person->name,
            'phone'               => $person->phone,
            'email'               => $person->email,
            'address'             => $person->address,
            'client_category'     => $person->client_category,

            // Información de créditos
            'total_credits'       => $person->credits->count(),
            'total_amount'        => (float) $person->credits->sum('amount'),
            'total_paid'          => (float) $person->credits->sum(function ($c) {
                return $c->amount - $c->balance;
            }),
            'total_balance'       => (float) $person->credits->sum('balance'),

            // Estado general
            'person_status'       => $personStatus,

            // Estadísticas de pagos
            'payment_stats'       => $this->buildPaymentStats($person->payments),

            // Créditos detallados
            'credits'             => $person->credits->map(function ($credit) {
                return $this->buildCreditData($credit);
            })->values()->all(),
        ];
    }

    /**
     * Construir estadísticas de pagos de una persona
     */
    private function buildPaymentStats($payments): array
    {
        $totalPayments = $payments->count();
        $paidPayments = $payments->where('status', 'paid')->count();
        $pendingPayments = $payments->where('status', 'pending')->count();
        $overduePayments = $payments->where('status', 'overdue')->count();

        $totalPaidAmount = $payments->where('status', 'paid')->sum('amount');
        $totalPendingAmount = $payments->where('status', 'pending')->sum('amount');
        $totalOverdueAmount = $payments->where('status', 'overdue')->sum('amount');

        $lastPayment = $payments->sortByDesc('payment_date')->first();

        return [
            'total_payments'      => $totalPayments,
            'paid_payments'       => $paidPayments,
            'pending_payments'    => $pendingPayments,
            'overdue_payments'    => $overduePayments,
            'total_paid_amount'   => round($totalPaidAmount, 2),
            'total_pending_amount' => round($totalPendingAmount, 2),
            'total_overdue_amount' => round($totalOverdueAmount, 2),
            'last_payment'        => $lastPayment ? [
                'date'            => $lastPayment->payment_date?->format('Y-m-d'),
                'amount'          => (float) $lastPayment->amount,
                'method'          => $lastPayment->payment_method,
                'status'          => $lastPayment->status,
            ] : null,
        ];
    }

    /**
     * Construir datos de un crédito individual con información detallada
     */
    private function buildCreditData($credit): array
    {
        // Cargar pagos si no están cargados
        if (!$credit->relationLoaded('payments')) {
            $credit->load('payments');
        }

        // Calcular días vencidos y estado
        $overdueDays = 0;
        $daysUntilDue = 0;
        if ($credit->status === 'active') {
            $dueDate = $credit->end_date;
            if ($dueDate) {
                if (now()->isAfter($dueDate)) {
                    $overdueDays = now()->diffInDays($dueDate, false);
                } else {
                    $daysUntilDue = now()->diffInDays($dueDate);
                }
            }
        }

        // Información de pagos del crédito
        $payments = $credit->payments ?? collect();
        $totalPaidAmount = $payments->sum('amount');
        $lastPayment = $payments->sortByDesc('payment_date')->first();
        $nextPaymentDue = $this->calculateNextPaymentDue($credit, $payments);

        // Porcentaje de pago
        $paymentPercentage = $credit->amount > 0
            ? round(($totalPaidAmount / $credit->amount) * 100, 2)
            : 0;

        return [
            // Información básica del crédito
            'credit_id'            => $credit->id,
            'amount'               => (float) $credit->amount,
            'balance'              => (float) $credit->balance,
            'paid_amount'          => (float) $totalPaidAmount,
            'payment_percentage'   => (float) $paymentPercentage,
            'status'               => $credit->status,

            // Fechas
            'start_date'           => $credit->start_date?->format('Y-m-d'),
            'end_date'             => $credit->end_date?->format('Y-m-d'),
            'days_until_due'       => $daysUntilDue,
            'overdue_days'         => $overdueDays,

            // Próximo pago
            'next_payment_due'     => $nextPaymentDue ? [
                'date'             => $nextPaymentDue['date'],
                'amount'           => (float) $nextPaymentDue['amount'],
                'installment'      => $nextPaymentDue['installment'],
            ] : null,

            // Información del último pago
            'last_payment'         => $lastPayment ? [
                'date'             => $lastPayment->payment_date?->format('Y-m-d'),
                'amount'           => (float) $lastPayment->amount,
                'method'           => $lastPayment->payment_method,
                'status'           => $lastPayment->status,
            ] : null,

            // Estadísticas de pagos
            'payment_stats'        => [
                'total_payments'   => $payments->count(),
                'paid_payments'    => $payments->where('status', 'paid')->count(),
                'pending_payments' => $payments->where('status', 'pending')->count(),
                'overdue_payments' => $payments->where('status', 'overdue')->count(),
            ],

            // Pagos detallados (últimos 5)
            'recent_payments'      => $payments
                ->sortByDesc('payment_date')
                ->take(5)
                ->map(function ($payment) {
                    return [
                        'payment_id'      => $payment->id,
                        'amount'          => (float) $payment->amount,
                        'date'            => $payment->payment_date?->format('Y-m-d'),
                        'method'          => $payment->payment_method,
                        'status'          => $payment->status,
                        'installment_num' => $payment->installment_number,
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * Calcular próximo pago debido
     */
    private function calculateNextPaymentDue($credit, $payments): ?array
    {
        // Si el crédito no está activo, no hay próximo pago
        if ($credit->status !== 'active') {
            return null;
        }

        // Obtener el número total de cuotas/pagos
        // Basarse en los pagos ya realizados
        $lastPayment = $payments->sortByDesc('installment_number')->first();

        if (!$lastPayment) {
            // Si no hay pagos, asumir primer pago
            return [
                'date'        => $credit->start_date?->format('Y-m-d'),
                'amount'      => round($credit->amount / 12, 2), // Asumir 12 cuotas
                'installment' => 1,
            ];
        }

        // El siguiente pago sería el siguiente número de cuota
        $nextInstallment = $lastPayment->installment_number + 1;
        $nextPaymentDate = $lastPayment->payment_date?->addMonths(1);

        return [
            'date'        => $nextPaymentDate?->format('Y-m-d'),
            'amount'      => round($credit->amount / 12, 2), // Asumir 12 cuotas
            'installment' => $nextInstallment,
        ];
    }

    /**
     * Calcular resumen de estadísticas del cluster
     */
    private function calculateClusterSummary(Collection $clientsInLocation): array
    {
        $totalCredits = 0;
        $totalAmount = 0.0;
        $totalBalance = 0.0;
        $overdueCount = 0;
        $overdueAmount = 0.0;
        $activeCount = 0;
        $activeAmount = 0.0;
        $completedCount = 0;
        $completedAmount = 0.0;

        foreach ($clientsInLocation as $client) {
            foreach ($client->credits as $credit) {
                $totalCredits++;
                $totalAmount += (float) $credit->amount;
                $totalBalance += (float) $credit->balance;

                if ($credit->status === 'active') {
                    $activeCount++;
                    $activeAmount += (float) $credit->amount;

                    // Verificar si está vencido
                    if ($credit->end_date && now()->isAfter($credit->end_date)) {
                        $overdueCount++;
                        $overdueAmount += (float) $credit->balance;
                    }
                } elseif ($credit->status === 'completed') {
                    $completedCount++;
                    $completedAmount += (float) $credit->amount;
                }
            }
        }

        return [
            'total_people'       => $clientsInLocation->count(),
            'total_credits'      => $totalCredits,
            'total_amount'       => round($totalAmount, 2),
            'total_balance'      => round($totalBalance, 2),
            'overdue_count'      => $overdueCount,
            'overdue_amount'     => round($overdueAmount, 2),
            'active_count'       => $activeCount,
            'active_amount'      => round($activeAmount, 2),
            'completed_count'    => $completedCount,
            'completed_amount'   => round($completedAmount, 2),
        ];
    }

    /**
     * Determinar estado general del cluster
     * Prioridad: overdue > pending > paid
     */
    private function determineClusterStatus(Collection $clientsInLocation): string
    {
        foreach ($clientsInLocation as $client) {
            if ($client->payments->where('status', 'overdue')->count() > 0) {
                return 'overdue';
            }
        }

        foreach ($clientsInLocation as $client) {
            if ($client->payments->where('status', 'pending')->count() > 0) {
                return 'pending';
            }
        }

        return 'paid';
    }
}
