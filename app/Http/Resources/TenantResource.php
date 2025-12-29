<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo' => $this->logo,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'trial_ends_at' => $this->trial_ends_at?->format('Y-m-d'),
            'trial_ends_at_formatted' => $this->trial_ends_at?->format('d/m/Y'),
            'monthly_price' => $this->monthly_price,
            'monthly_price_formatted' => number_format($this->monthly_price, 2) . ' Bs',

            // Status booleans
            'is_active' => $this->isActive(),
            'is_on_trial' => $this->isOnTrial(),
            'is_suspended' => $this->isSuspended(),
            'trial_has_expired' => $this->trialHasExpired(),

            // Counts (if loaded)
            'users_count' => $this->whenCounted('users'),
            'subscriptions_count' => $this->whenCounted('subscriptions'),

            // Relationships
            'settings' => $this->when($this->relationLoaded('settings'), function () {
                return $this->settings->map(function ($setting) {
                    return [
                        'key' => $setting->key,
                        'value' => $this->parseSettingValue($setting->value, $setting->type),
                        'type' => $setting->type,
                    ];
                });
            }),
            'latest_subscription' => new SubscriptionResource($this->whenLoaded('latestSubscription')),

            // Timestamps
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'created_at_formatted' => $this->created_at->format('d/m/Y'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'updated_at_formatted' => $this->updated_at->format('d/m/Y'),
        ];
    }

    /**
     * Get human-readable status label.
     */
    private function getStatusLabel(): string
    {
        return match ($this->status) {
            'active' => 'Activo',
            'trial' => 'Período de Prueba',
            'suspended' => 'Suspendido',
            default => ucfirst($this->status),
        };
    }

    /**
     * Parse setting value based on type.
     */
    private function parseSettingValue($value, $type)
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'decimal' => (float) $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }
}
