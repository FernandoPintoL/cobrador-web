<?php

namespace App\DTOs;

use Illuminate\Support\Collection;

class PerformanceReportDTO
{
    public function __construct(
        public Collection $performance,
        public array $summary,
        public string $generated_at,
        public string $generated_by,
    ) {}

    public function toArray(): array
    {
        return [
            'performance' => $this->performance->toArray(),
            'summary' => $this->summary,
            'generated_at' => $this->generated_at,
            'generated_by' => $this->generated_by,
        ];
    }

    public function getPerformance(): Collection
    {
        return $this->performance;
    }

    public function getSummary(): array
    {
        return $this->summary;
    }
}
