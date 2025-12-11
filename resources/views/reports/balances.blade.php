@extends('reports.layouts.base')

@section('content')
    @include('reports.components.header', [
        'title' => 'Reporte de Balances de Efectivo',
        'generated_at' => $generated_at,
        'generated_by' => $generated_by,
    ])

    @include('reports.components.summary-section', [
        'title' => 'Resumen Consolidado',
        'columns' => 2,
        'items' => [
            'Total de registros' => $summary['total_records'],
            'Monto inicial total' => '$ ' . number_format($summary['total_initial'], 2),
            'Monto recolectado total' => '$ ' . number_format($summary['total_collected'], 2),
            'Monto prestado total' => '$ ' . number_format($summary['total_lent'], 2),
            'Monto final total' => '$ ' . number_format($summary['total_final'], 2),
            'Diferencia promedio' => '<span style="color: ' . ($summary['average_difference'] > 0 ? 'var(--color-success)' : ($summary['average_difference'] < 0 ? 'var(--color-danger)' : 'var(--color-text)')) . ';">$ ' . number_format($summary['average_difference'], 2) . '</span>',
        ],
    ])

    <table class="report-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Cobrador</th>
                <th>Fecha</th>
                <th>Monto Inicial</th>
                <th>Monto Recolectado</th>
                <th>Monto Prestado</th>
                <th>Monto Final</th>
                <th>Diferencia</th>
            </tr>
        </thead>
        <tbody>
            @forelse($balances as $balance)
            @php
            // Convertir a array si es objeto
            $balanceArray = is_array($balance) ? $balance : (array)$balance;

            // Obtener el modelo si existe
            $model = $balanceArray['_model'] ?? null;

            // Extraer valores
            $initialAmount = (float)($balanceArray['initial_amount'] ?? 0);
            $collectedAmount = (float)($balanceArray['collected_amount'] ?? 0);
            $lentAmount = (float)($balanceArray['lent_amount'] ?? 0);
            $finalAmount = (float)($balanceArray['final_amount'] ?? 0);
            $difference = $finalAmount - ($initialAmount + $collectedAmount - $lentAmount);

            // Obtener nombre del cobrador
            $cobradorName = $balanceArray['cobrador_name'] ?? ($model && $model->cobrador ? $model->cobrador->name : 'N/A');

            // Obtener fecha formateada
            $dateFormatted = $balanceArray['date_formatted'] ?? ($model && $model->date ? $model->date->format('d/m/Y') : 'N/A');
            @endphp
            <tr>
                <td>{{ $balanceArray['id'] ?? 'N/A' }}</td>
                <td>{{ $cobradorName }}</td>
                <td>{{ $dateFormatted }}</td>
                <td>$ {{ number_format($initialAmount, 2) }}</td>
                <td>$ {{ number_format($collectedAmount, 2) }}</td>
                <td>$ {{ number_format($lentAmount, 2) }}</td>
                <td>$ {{ number_format($finalAmount, 2) }}</td>
                <td style="color: {{ $difference > 0 ? 'var(--color-success)' : ($difference < 0 ? 'var(--color-danger)' : 'var(--color-text)') }};">
                    $ {{ number_format($difference, 2) }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" style="text-align: center; padding: var(--spacing-lg); color: var(--color-text-secondary);">
                    No hay datos disponibles
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    @include('reports.components.footer')
@endsection
