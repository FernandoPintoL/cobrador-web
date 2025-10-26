{{--
    Componente Summary Section Reutilizable

    Props:
    - $title: string - Título de la sección (ej: "Resumen General")
    - $items: array - Array asociativo de items a mostrar
      Ejemplo: [
          'Total créditos' => '150',
          'Monto total' => 'Bs 50,000.00',
          'Créditos activos' => '120',
      ]
    - $columns: int (opcional) - Número de columnas en grid (default: 3)
--}}

<div class="summary-section">
    <h3>{{ $title }}</h3>

    <div class="summary-grid" style="grid-template-columns: repeat({{ $columns ?? 3 }}, 1fr);">
        @foreach($items as $label => $value)
        <div class="summary-item">
            <p>
                <strong>{{ $label }}:</strong>
            </p>
            <div class="value">{{ $value }}</div>
        </div>
        @endforeach
    </div>
</div>
