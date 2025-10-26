<?php

namespace App\Services;

use App\DTOs\UserReportDTO;
use App\Models\User;
use Illuminate\Support\Collection;

class UserReportService
{
    public function generateReport(array $filters, object $currentUser): UserReportDTO
    {
        $query = $this->buildQuery($filters, $currentUser);
        $users = $query->orderBy('name')->get();

        $transformedUsers = $this->transformUsers($users);
        $summary = $this->calculateSummary($users);

        return new UserReportDTO(
            users: $transformedUsers,
            summary: $summary,
            generated_at: now()->format('Y-m-d H:i:s'),
            generated_by: $currentUser->name,
        );
    }

    private function buildQuery(array $filters, object $currentUser)
    {
        $query = User::with(['roles', 'credits']);

        if (!empty($filters['role'])) {
            $query->whereHas('roles', function ($q) use ($filters) {
                $q->where('name', $filters['role']);
            });
        }

        if (!empty($filters['client_category'])) {
            $query->where('client_category', $filters['client_category']);
        }

        return $query;
    }

    private function transformUsers(Collection $users): Collection
    {
        return $users->map(function ($user) {
            $roles = $user->roles->pluck('name')->toArray();
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? 'N/A',
                'roles' => $roles,
                'roles_formatted' => implode(', ', $roles),
                'client_category' => $user->client_category ?? 'N/A',
                'credits_count' => $user->credits->count(),
                'is_cobrador' => $user->roles->contains('name', 'cobrador'),
                'is_manager' => $user->roles->contains('name', 'manager'),
                'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                'created_at_formatted' => $user->created_at->format('d/m/Y'),
                '_model' => $user,
            ];
        });
    }

    private function calculateSummary(Collection $users): array
    {
        $byRole = $users->groupBy(function ($user) {
            return $user->roles->first()?->name ?? 'Sin rol';
        })->map->count();

        $byCategory = $users->where('client_category', '!=', null)
            ->groupBy('client_category')
            ->map->count();

        return [
            'total_users' => $users->count(),
            'by_role' => $byRole->toArray(),
            'by_category' => $byCategory->toArray(),
            'cobradores_count' => $users->filter(function ($u) {
                return $u->roles->contains('name', 'cobrador');
            })->count(),
            'managers_count' => $users->filter(function ($u) {
                return $u->roles->contains('name', 'manager');
            })->count(),
            'admins_count' => $users->filter(function ($u) {
                return $u->roles->contains('name', 'admin');
            })->count(),
        ];
    }
}
