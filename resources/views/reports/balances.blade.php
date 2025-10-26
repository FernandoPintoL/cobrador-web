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
            $difference = $balance->final_amount - ($balance->initial_amount + $balance->collected_amount - $balance->lent_amount);
            @endphp
            <tr>
                <td>{{ $balance->id }}</td>
                <td>{{ $balance->cobrador->name ?? 'N/A' }}</td>
                <td>{{ $balance->date->format('d/m/Y') }}</td>
                <td>$ {{ number_format($balance->initial_amount, 2) }}</td>
                <td>$ {{ number_format($balance->collected_amount, 2) }}</td>
                <td>$ {{ number_format($balance->lent_amount, 2) }}</td>
                <td>$ {{ number_format($balance->final_amount, 2) }}</td>
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
