{{--
    Componente Table Reutilizable

    Props:
    - $headers: array - Encabezados de la tabla
      Ejemplo: ['ID', 'Nombre', 'Monto', 'Estado']
    - $rows: collection o array - Filas de la tabla
    - $columns: array - Mapeo de columnas a mostrar
      Ejemplo: [
          ['key' => 'id', 'label' => 'ID'],
          ['key' => 'name', 'label' => 'Nombre'],
          ['key' => 'amount', 'label' => 'Monto', 'format' => 'currency'],
          ['key' => 'status', 'label' => 'Estado', 'format' => 'status'],
      ]
    - $rowClass: string or callable (opcional) - Clase CSS para filas (puede ser función)
    - $striped: boolean (opcional) - Habilitar filas alternas (default: false)
--}}

<table class="report-table">
    <thead>
        <tr>
            @foreach($headers as $header)
            <th>{{ $header }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $row)
        @php
            // Determinar clase de fila
            $rowCssClass = '';
            if (is_callable($rowClass ?? null)) {
                $rowCssClass = $rowClass($row);
            } elseif (!empty($rowClass)) {
                $rowCssClass = $rowClass;
            }
        @endphp
        <tr class="{{ $rowCssClass }}">
            @foreach($columns as $column)
            <td>
                @php
                    $value = $row;
                    // Navegar a través de atributos anidados (ej: 'client.name')
                    foreach (explode('.', $column['key']) as $key) {
                        if (is_object($value) && method_exists($value, '__get')) {
                            $value = $value->$key;
                        } elseif (is_array($value)) {
                            $value = $value[$key] ?? null;
                        } else {
                            $value = $value->$key ?? null;
                        }
                    }

                    // Aplicar formato si se especifica
                    if (isset($column['format'])) {
                        switch ($column['format']) {
                            case 'currency':
                                $value = 'Bs ' . number_format($value, 2);
                                break;
                            case 'percentage':
                                $value = number_format($value, 2) . '%';
                                break;
                            case 'date':
                                $value = \Carbon\Carbon::parse($value)->format('d/m/Y');
                                break;
                            case 'datetime':
                                $value = \Carbon\Carbon::parse($value)->format('d/m/Y H:i');
                                break;
                            case 'status':
                                $statusMap = [
                                    'active' => ['class' => 'status-active', 'label' => 'Activo'],
                                    'completed' => ['class' => 'status-completed', 'label' => 'Completado'],
                                    'pending' => ['class' => 'status-pending', 'label' => 'Pendiente'],
                                    'overdue' => ['class' => 'status-overdue', 'label' => 'Vencido'],
                                ];
                                $status = $statusMap[$value] ?? ['class' => '', 'label' => $value];
                                $value = '<span class="' . $status['class'] . '">' . $status['label'] . '</span>';
                                break;
                        }
                    }
                @endphp
                @if(isset($column['format']) && $column['format'] === 'status')
                    {!! $value !!}
                @else
                    {{ $value ?? 'N/A' }}
                @endif
            </td>
            @endforeach
        </tr>
        @empty
        <tr>
            <td colspan="{{ count($headers) }}" style="text-align: center; padding: var(--spacing-lg); color: var(--color-text-secondary);">
                No hay datos disponibles
            </td>
        </tr>
        @endforelse
    </tbody>
</table>
