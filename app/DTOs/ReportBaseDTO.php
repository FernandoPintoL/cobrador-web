<?php

namespace App\DTOs;

use Illuminate\Support\Collection;

/**
 * Base DTO para reportes genéricos
 * Utilizado por: CashFlowForecast, WaitingList, DailyActivity, Portfolio, Commissions
 */
class ReportBaseDTO
{
    public function __construct(
        public Collection $data,
        public array $summary,
        public string $generated_at,
        public string $generated_by,
    ) {}

    public function toArray(): array
    {
        return [
            'data' => $this->data->toArray(),
            'summary' => $this->summary,
            'generated_at' => $this->generated_at,
            'generated_by' => $this->generated_by,
        ];
    }

    public function getData(): Collection
    {
        return $this->data;
    }

    public function getSummary(): array
    {
        return $this->summary;
    }
}

// Aliases para tipado específico
class CashFlowForecastDTO extends ReportBaseDTO {}
class WaitingListDTO extends ReportBaseDTO {}
class DailyActivityDTO extends ReportBaseDTO {}
class PortfolioDTO extends ReportBaseDTO {}
class CommissionsDTO extends ReportBaseDTO {}
