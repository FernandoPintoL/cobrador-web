<?php

namespace App\DTOs;

use Illuminate\Support\Collection;

class OverdueReportDTO
{
    public function __construct(
        public Collection $credits,
        public array $summary,
        public string $generated_at,
        public string $generated_by,
    ) {}

    public function toArray(): array
    {
        return [
            'credits' => $this->credits->toArray(),
            'summary' => $this->summary,
            'generated_at' => $this->generated_at,
            'generated_by' => $this->generated_by,
        ];
    }

    public function getCredits(): Collection
    {
        return $this->credits;
    }

    public function getSummary(): array
    {
        return $this->summary;
    }
}
