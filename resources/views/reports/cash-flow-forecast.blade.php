@extends('reports.layouts.base')

@section('content')
    @include('reports.components.header', [
        'title' => 'Proyección de Flujo de Caja',
        'generated_at' => $generated_at,
        'generated_by' => $generated_by,
    ])

    @include('reports.components.summary-section', [
        'title' => 'Resumen de Proyección',
        'columns' => 2,
        'items' => [
            'Período' => $summary['period']['start'] . ' - ' . $summary['period']['end'] . ' (' . $summary['period']['months'] . ' meses)',
            'Total transacciones' => $summary['total_projected_transactions'],
            'Pagos esperados' => $summary['total_projected_payments'] . ' pagos',
            'Entregas programadas' => $summary['total_projected_deliveries'] . ' entregas',
            'Total ENTRADAS proyectadas' => '<span style="color: var(--color-success); font-weight: bold;">Bs ' . number_format($summary['total_entries'], 2) . '</span>',
            'Total SALIDAS proyectadas' => '<span style="color: var(--color-danger); font-weight: bold;">Bs ' . number_format($summary['total_exits'], 2) . '</span>',
            'BALANCE NETO' => '<span style="color: ' . ($summary['net_balance'] >= 0 ? 'var(--color-success)' : 'var(--color-danger)') . '; font-weight: bold; font-size: 1.2em;">Bs ' . number_format($summary['net_balance'], 2) . '</span>',
            'Monto vencido' => 'Bs ' . number_format($summary['overdue_amount'], 2),
            'Monto pendiente' => 'Bs ' . number_format($summary['pending_amount'], 2),
        ],
    ])

    {{-- Leyenda de colores --}}
    <div style="margin: var(--spacing-lg) 0; padding: var(--spacing-md); background: var(--color-surface); border-radius: var(--border-radius); border-left: 4px solid var(--color-primary);">
        <h4 style="margin-top: 0; color: var(--color-primary);">Leyenda de Colores</h4>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: var(--spacing-sm);">
            <div style="display: flex; align-items: center; gap: var(--spacing-xs);">
                <div style="width: 20px; height: 20px; background: #E8F5E9; border: 1px solid #4CAF50;"></div>
                <span>Pago Esperado (Pendiente)</span>
            </div>
            <div style="display: flex; align-items: center; gap: var(--spacing-xs);">
                <div style="width: 20px; height: 20px; background: #FFE5E5; border: 1px solid #F44336;"></div>
                <span>Pago Esperado (Vencido)</span>
            </div>
            <div style="display: flex; align-items: center; gap: var(--spacing-xs);">
                <div style="width: 20px; height: 20px; background: #FFF9C4; border: 1px solid #FFC107;"></div>
                <span>Entrega Programada</span>
            </div>
            <div style="display: flex; align-items: center; gap: var(--spacing-xs);">
                <div style="width: 20px; height: 20px; background: #FFE0B2; border: 1px solid #FF9800;"></div>
                <span>Entrega Vencida</span>
            </div>
        </div>
    </div>

    {{-- Tabla de proyecciones --}}
    <table class="report-table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Crédito ID</th>
                <th>Cliente</th>
                <th>Cobrador</th>
                <th>Monto</th>
                <th>Frecuencia</th>
                <th>Nº Cuota</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($projections as $projection)
            @php
                // Determinar clase CSS según tipo y estado
                if ($projection['type'] === 'payment') {
                    $rowClass = $projection['is_overdue'] ? 'payment-overdue' : 'payment-pending';
                    $typeLabel = 'Pago Esperado';
                    $typeIcon = '💰';
                } else {
                    $rowClass = $projection['is_overdue'] ? 'delivery-overdue' : 'delivery-scheduled';
                    $typeLabel = 'Entrega Programada';
                    $typeIcon = '📦';
                }

                $statusLabel = match($projection['status']) {
                    'overdue' => '⚠️ Vencido',
                    'pending' => '⏳ Pendiente',
                    'scheduled' => '📅 Programado',
                    default => $projection['status'],
                };
            @endphp
            <tr class="{{ $rowClass }}">
                <td style="font-weight: bold;">{{ $projection['payment_date'] ?? $projection['date'] }}</td>
                <td>{{ $typeIcon }} {{ $typeLabel }}</td>
                <td style="text-align: center;">{{ $projection['credit_id'] }}</td>
                <td>{{ $projection['client_name'] }}</td>
                <td style="font-size: 0.9em;">{{ $projection['cobrador_name'] }}</td>
                <td style="font-weight: bold; text-align: right;">
                    Bs {{ number_format($projection['projected_amount'] ?? $projection['amount'], 2) }}
                </td>
                <td style="font-size: 0.85em; text-align: center;">{{ ucfirst($projection['frequency']) }}</td>
                <td style="text-align: center;">{{ $projection['installment_number'] ?? '-' }}</td>
                <td style="text-align: center;">{{ $statusLabel }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="9" style="text-align: center; padding: var(--spacing-lg); color: var(--color-text-secondary);">
                    No hay proyecciones disponibles para el período seleccionado
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Estilos personalizados para este reporte --}}
    <style>
        .payment-pending {
            background-color: #E8F5E9 !important;
        }
        .payment-overdue {
            background-color: #FFE5E5 !important;
        }
        .delivery-scheduled {
            background-color: #FFF9C4 !important;
        }
        .delivery-overdue {
            background-color: #FFE0B2 !important;
        }
    </style>

    @include('reports.components.footer', [
        'additional_info' => [
            'Balance Neto Positivo' => 'Indica que se espera recibir más dinero del que se prestará (liquidez positiva)',
            'Balance Neto Negativo' => 'Indica que se prestará más dinero del que se recibirá (requerirá capital adicional)',
        ],
    ])
@endsection
