@extends('reports.layouts.base')

@section('content')
    @include('reports.components.header', [
        'title' => 'Lista de Espera - Créditos Pendientes',
        'generated_at' => $generated_at,
        'generated_by' => $generated_by,
    ])

    @include('reports.components.summary-section', [
        'title' => 'Resumen',
        'columns' => 2,
        'items' => [
            'Total en lista de espera' => $summary['total_waiting'],
            'Pendientes de aprobación' => $summary['pending_approval'],
            'Esperando entrega' => $summary['waiting_delivery'],
            'Vencidos para entrega' => $summary['overdue_for_delivery'],
            'Monto total pendiente' => 'Bs ' . number_format($summary['total_pending_amount'], 2),
            'Promedio días esperando' => number_format($summary['average_days_waiting'], 1) . ' días',
            'Máximo días esperando' => $summary['max_days_waiting'] . ' días',
        ],
    ])

    <h3 style="margin-top: var(--spacing-lg); color: var(--color-primary); border-bottom: 2px solid var(--color-primary); padding-bottom: var(--spacing-sm);">Pendientes de Aprobación</h3>
    <table class="report-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Cliente</th>
                <th>Cobrador</th>
                <th>Monto</th>
                <th>Fecha Creación</th>
                <th>Días Esperando</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pending_approval as $credit)
            <tr class="row-warning">
                <td>{{ $credit->id }}</td>
                <td>{{ $credit->client->name ?? 'N/A' }}</td>
                <td>{{ $credit->createdBy->name ?? 'N/A' }}</td>
                <td>Bs {{ number_format($credit->amount, 2) }}</td>
                <td>{{ $credit->created_at->format('d/m/Y') }}</td>
                <td>{{ $credit->days_waiting }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align: center; padding: var(--spacing-lg); color: var(--color-text-secondary);">
                    No hay datos disponibles
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <h3 style="margin-top: var(--spacing-lg); color: var(--color-primary); border-bottom: 2px solid var(--color-primary); padding-bottom: var(--spacing-sm);">Esperando Entrega</h3>
    <table class="report-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Cliente</th>
                <th>Cobrador</th>
                <th>Monto</th>
                <th>Fecha Programada</th>
                <th>Días Esperando</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($waiting_delivery as $credit)
            <tr class="{{ $credit->is_overdue_for_delivery ? 'row-danger' : 'row-clean' }}">
                <td>{{ $credit->id }}</td>
                <td>{{ $credit->client->name ?? 'N/A' }}</td>
                <td>{{ $credit->createdBy->name ?? 'N/A' }}</td>
                <td>Bs {{ number_format($credit->amount, 2) }}</td>
                <td>{{ $credit->scheduled_delivery_date ? $credit->scheduled_delivery_date->format('d/m/Y') : 'N/A' }}</td>
                <td>{{ $credit->days_waiting }}</td>
                <td>{{ $credit->is_overdue_for_delivery ? 'VENCIDO' : 'Pendiente' }}</td>
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
