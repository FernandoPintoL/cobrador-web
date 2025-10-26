<?php

namespace App\DTOs;

use Illuminate\Support\Collection;

class UserReportDTO
{
    public function __construct(
        public Collection $users,
        public array $summary,
        public string $generated_at,
        public string $generated_by,
    ) {}

    public function toArray(): array
    {
        return [
            'users' => $this->users->toArray(),
            'summary' => $this->summary,
            'generated_at' => $this->generated_at,
            'generated_by' => $this->generated_by,
        ];
    }

    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function getSummary(): array
    {
        return $this->summary;
    }
}
