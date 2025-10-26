<?php

namespace App\DTOs;

use Illuminate\Support\Collection;

/**
 * PaymentReportDTO - Data Transfer Object para Reportes de Pagos
 *
 * ✅ ARQUITECTURA CENTRALIZADA
 * Este DTO encapsula TODOS los datos del reporte de pagos:
 * - Payments transformados con cálculos
 * - Resumen agregado
 * - Metadatos del reporte
 *
 * Ventajas:
 * - Un único punto de transformación (Service)
 * - Reutilizable en Controller, Resource, Export, Blade
 * - Sin duplicación de lógica de cálculo
 * - Fácil de testear
 */
class PaymentReportDTO
{
    public function __construct(
        public Collection $payments,
        public array $summary,
        public string $generated_at,
        public string $generated_by,
    ) {}

    /**
     * Convierte el DTO a array para serialización JSON
     */
    public function toArray(): array
    {
        return [
            'payments' => $this->payments->toArray(),
            'summary' => $this->summary,
            'generated_at' => $this->generated_at,
            'generated_by' => $this->generated_by,
        ];
    }

    /**
     * Retorna solo los datos de payments transformados
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    /**
     * Retorna el resumen agregado
     */
    public function getSummary(): array
    {
        return $this->summary;
    }
}
