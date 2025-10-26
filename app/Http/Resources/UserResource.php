<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $roles = $this->roles->pluck('name')->toArray();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone ?? 'N/A',
            'roles' => $roles,
            'roles_formatted' => implode(', ', $roles),
            'client_category' => $this->client_category ?? 'N/A',
            'credits_count' => $this->credits->count(),
            'is_cobrador' => $this->roles->contains('name', 'cobrador'),
            'is_manager' => $this->roles->contains('name', 'manager'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'created_at_formatted' => $this->created_at->format('d/m/Y'),
        ];
    }
}
