<?php

namespace App\DTOs;

use Illuminate\Support\Collection;

class BalanceReportDTO
{
    public function __construct(
        public Collection $balances,
        public array $summary,
        public string $generated_at,
        public string $generated_by,
    ) {}

    public function toArray(): array
    {
        return [
            'balances' => $this->balances->toArray(),
            'summary' => $this->summary,
            'generated_at' => $this->generated_at,
            'generated_by' => $this->generated_by,
        ];
    }

    public function getBalances(): Collection
    {
        return $this->balances;
    }

    public function getSummary(): array
    {
        return $this->summary;
    }
}
