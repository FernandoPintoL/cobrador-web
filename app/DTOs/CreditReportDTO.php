<?php

namespace App\DTOs;

use Illuminate\Support\Collection;

/**
 * CreditReportDTO - Data Transfer Object para Reportes de Créditos
 *
 * ✅ ARQUITECTURA CENTRALIZADA
 * Encapsula todos los datos del reporte de créditos de forma consistente.
 */
class CreditReportDTO
{
    public function __construct(
        public Collection $credits,
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
            'credits' => $this->credits->toArray(),
            'summary' => $this->summary,
            'generated_at' => $this->generated_at,
            'generated_by' => $this->generated_by,
        ];
    }

    /**
     * Retorna solo los datos de credits transformados
     */
    public function getCredits(): Collection
    {
        return $this->credits;
    }

    /**
     * Retorna el resumen agregado
     */
    public function getSummary(): array
    {
        return $this->summary;
    }
}
