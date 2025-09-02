<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
        'min_overdue_count',
        'max_overdue_count',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'min_overdue_count' => 'integer',
            'max_overdue_count' => 'integer',
        ];
    }

    public function users(): HasMany
    {
        // Relación por código, ya que users.client_category almacena el código (A/B/C)
        return $this->hasMany(User::class, 'client_category', 'code');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function findForOverdueCount(int $overdueCount): ?self
    {
        return self::query()
            ->active()
            ->where(function ($q) use ($overdueCount) {
                $q->whereNull('min_overdue_count')
                    ->orWhere('min_overdue_count', '<=', $overdueCount);
            })
            ->where(function ($q) use ($overdueCount) {
                $q->whereNull('max_overdue_count')
                    ->orWhere('max_overdue_count', '>=', $overdueCount);
            })
            ->orderByRaw('COALESCE(min_overdue_count, 0) ASC')
            ->first();
    }

    /**
     * Business rule: whether clients in this category can receive new credits.
     */
    public function canCreateNewCredit(): bool
    {
        // If the code is exactly 'C' (Mal Cliente), block creating new credits
        if ($this->code === 'C') {
            return false;
        }

        return true;
    }

    /**
     * Optional helper: reason when creation is blocked by category.
     */
    public function creditCreationBlockedReason(): ?string
    {
        if (! $this->canCreateNewCredit()) {
            return 'No se pueden asignar nuevos créditos a clientes de categoría C hasta finalizar sus cuotas o créditos vigentes.';
        }

        return null;
    }
}
