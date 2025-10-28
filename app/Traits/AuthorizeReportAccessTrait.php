<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * 🔐 AuthorizeReportAccessTrait
 *
 * Centraliza la lógica de autorización para reportes.
 * Garantiza consistencia en cómo diferentes roles acceden a datos.
 *
 * REGLAS DE AUTORIZACIÓN:
 * ✅ Cobrador: Ve SOLO sus propios datos
 * ✅ Manager: Ve datos de sus cobradores asignados
 * ✅ Admin: Ve TODO
 *
 * USO:
 * use AuthorizeReportAccessTrait;
 *
 * public function generateReport(array $filters, object $currentUser) {
 *     $query = Payment::query();
 *
 *     // Aplicar filtro de autorización
 *     $this->authorizeUserAccess($query, $currentUser, 'cobrador_id');
 *
 *     return $query->get();
 * }
 */
trait AuthorizeReportAccessTrait
{
    /**
     * Autoriza acceso a datos según rol del usuario.
     *
     * Patrones soportados:
     * - Simple: $query->where('cobrador_id', $user->id) para cobradores
     * - Manager: whereHas('cobrador') para managers
     *
     * @param  Builder  $query  Query a filtrar
     * @param  object  $currentUser  Usuario actual (Auth::user())
     * @param  string  $relationship  Campo o relación a filtrar (ej: 'cobrador_id', 'cobrador')
     * @param  string  $userIdField  Campo en la tabla relacionada que contiene el ID del user
     * @return Builder  Query filtrada
     */
    protected function authorizeUserAccess(
        Builder $query,
        object $currentUser,
        string $relationship = 'cobrador_id',
        string $userIdField = 'id',
    ): Builder {
        // Admin puede ver todo
        if ($currentUser->hasRole('admin')) {
            return $query;
        }

        // Cobrador ve SOLO sus propios datos
        if ($currentUser->hasRole('cobrador')) {
            // Si es relación (contiene puntos), usar whereHas
            if (str_contains($relationship, '.')) {
                return $query->whereHas(...explode('.', $relationship, 2));
            }
            // Si es simple field, usar where directo
            return $query->where($relationship, $currentUser->id);
        }

        // Manager ve datos de sus cobradores asignados
        if ($currentUser->hasRole('manager')) {
            // Si es relación tipo "cobrador_id", buscar el nombre de la relación
            $relationName = str_replace('_id', '', $relationship);

            return $query->whereHas($relationName, function ($subQuery) use ($currentUser) {
                $subQuery->where('assigned_manager_id', $currentUser->id);
            });
        }

        // Por defecto, no retornar nada (seguridad)
        return $query->whereRaw('1 = 0');
    }

    /**
     * Autoriza acceso a múltiples relaciones (ej: createdBy OR deliveredBy).
     *
     * Soporta tanto camelCase (relaciones) como snake_case (columnas).
     *
     * @param  Builder  $query  Query a filtrar
     * @param  object  $currentUser  Usuario actual
     * @param  array  $relationships  Array de relaciones a chequear
     *                                 (ej: ['createdBy', 'deliveredBy'] o ['created_by', 'delivered_by'])
     * @return Builder  Query filtrada
     */
    protected function authorizeUserAccessMultiple(
        Builder $query,
        object $currentUser,
        array $relationships = [],
    ): Builder {
        // Admin puede ver todo
        if ($currentUser->hasRole('admin')) {
            return $query;
        }

        // Cobrador ve datos donde está involucrado en cualquiera de las relaciones
        if ($currentUser->hasRole('cobrador')) {
            return $query->where(function ($q) use ($currentUser, $relationships) {
                foreach ($relationships as $relationship) {
                    // Convertir camelCase a snake_case para columnas
                    // ej: 'createdBy' -> 'created_by'
                    $columnName = $this->camelCaseToSnakeCase($relationship);
                    $q->orWhere($columnName, $currentUser->id);
                }
            });
        }

        // Manager ve datos donde sus cobradores están involucrados
        if ($currentUser->hasRole('manager')) {
            return $query->where(function ($q) use ($currentUser, $relationships) {
                foreach ($relationships as $relationship) {
                    // Convertir snake_case a camelCase para relaciones si es necesario
                    // ej: 'created_by' -> 'createdBy', o 'createdBy' -> 'createdBy' (sin cambio)
                    $relationshipName = $this->snakeCaseToCamelCase($relationship);

                    $q->orWhereHas($relationshipName, function ($subQ) use ($currentUser) {
                        $subQ->where('assigned_manager_id', $currentUser->id);
                    });
                }
            });
        }

        // Por defecto, no retornar nada (seguridad)
        return $query->whereRaw('1 = 0');
    }

    /**
     * Convierte camelCase a snake_case
     * ej: 'createdBy' -> 'created_by'
     */
    private function camelCaseToSnakeCase(string $str): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $str));
    }

    /**
     * Convierte snake_case a camelCase
     * ej: 'created_by' -> 'createdBy'
     */
    private function snakeCaseToCamelCase(string $str): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $str))));
    }

    /**
     * Obtiene lista de IDs de cobradores según autorización.
     *
     * Útil para filtros complejos que necesitan IDs directamente.
     *
     * @param  object  $currentUser  Usuario actual
     * @return array  IDs de cobradores que el usuario puede ver
     */
    protected function getAuthorizedCobradorIds(object $currentUser): array
    {
        if ($currentUser->hasRole('admin')) {
            // Admin ve todos los cobradores
            return User::role('cobrador')->pluck('id')->toArray();
        }

        if ($currentUser->hasRole('cobrador')) {
            // Cobrador solo ve a sí mismo
            return [$currentUser->id];
        }

        if ($currentUser->hasRole('manager')) {
            // Manager ve sus cobradores asignados
            return $currentUser->assignedCobradores()->pluck('id')->toArray();
        }

        return [];
    }

    /**
     * Obtiene lista de IDs de clientes según autorización y relaciones de cobradores.
     *
     * Usado por servicios complejos que filtran por clientes (ej: OverdueReportService).
     *
     * @param  object  $currentUser  Usuario actual
     * @return array  IDs de clientes que el usuario puede ver
     */
    protected function getAuthorizedClientIds(object $currentUser): array
    {
        if ($currentUser->hasRole('admin')) {
            // Admin ve todos los clientes
            return User::role('client')->pluck('id')->toArray();
        }

        if ($currentUser->hasRole('cobrador')) {
            // Cobrador solo ve sus clientes asignados
            return $currentUser->assignedClients()->pluck('id')->toArray();
        }

        if ($currentUser->hasRole('manager')) {
            // Manager ve:
            // 1. Clientes asignados directamente a él
            $directClientIds = User::whereHas('roles', fn($q) => $q->where('name', 'client'))
                ->where('assigned_manager_id', $currentUser->id)
                ->pluck('id')
                ->toArray();

            // 2. Clientes asignados a sus cobradores
            $cobradorIds = $this->getAuthorizedCobradorIds($currentUser);
            $cobradorClientIds = User::whereHas('roles', fn($q) => $q->where('name', 'client'))
                ->whereIn('assigned_cobrador_id', $cobradorIds)
                ->pluck('id')
                ->toArray();

            return array_unique(array_merge($directClientIds, $cobradorClientIds));
        }

        return [];
    }
}
