<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Solo aplicar el scope si el usuario está autenticado y tiene tenant_id
        // Protección contra recursión infinita
        if (auth()->check()) {
            try {
                $user = auth()->user();
                if ($user && isset($user->tenant_id) && $user->tenant_id) {
                    $builder->where($model->getTable() . '.tenant_id', $user->tenant_id);
                }
            } catch (\Exception $e) {
                // Prevenir loops infinitos durante autenticación
                return;
            }
        }
    }
}
