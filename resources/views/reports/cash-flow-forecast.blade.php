@extends('reports.layouts.base')

@section('content')
    @include('reports.components.header', [
        'title' => 'Proyección de Flujo de Caja',
        'generated_at' => $generated_at,
        'generated_by' => $generated_by,
    ])

    @include('reports.components.summary-section', [
        'title' => 'Resumen General',
        'columns' => 2,
        'items' => [
            'Período' => $summary['period']['start'] . ' - ' . $summary['period']['end'],
            'Total créditos activos' => $summary['total_active_credits'],
            'Total pagos proyectados' => $summary['total_projected_payments'],
            'Monto total proyectado' => 'Bs ' . number_format($summary['total_projected_amount'], 2),
            'Monto vencido' => 'Bs ' . number_format($summary['overdue_amount'], 2),
            'Monto pendiente' => 'Bs ' . number_format($summary['pending_amount'], 2),
        ],
    ])

    <table class="report-table">
        <thead>
            <tr>
                <th>Fecha Proyectada</th>
                <th>Crédito ID</th>
                <th>Cliente</th>
                <th>Cobrador</th>
                <th>Monto Proyectado</th>
                <th>Frecuencia</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($projections as $projection)
            <tr class="{{ $projection['is_overdue'] ? 'row-danger' : 'row-warning' }}">
                <td>{{ $projection['payment_date'] }}</td>
                <td>{{ $projection['credit_id'] }}</td>
                <td>{{ $projection['client_name'] }}</td>
                <td>{{ $projection['cobrador_name'] }}</td>
                <td>Bs {{ number_format($projection['projected_amount'], 2) }}</td>
                <td>{{ $projection['frequency'] }}</td>
                <td>{{ $projection['is_overdue'] ? 'Vencido' : 'Pendiente' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="text-align: center; padding: var(--spacing-lg); color: var(--color-text-secondary);">
                    No hay datos disponibles
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    @include('reports.components.footer')
@endsection
