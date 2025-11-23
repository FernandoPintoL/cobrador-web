<?php

namespace App\Services;

use App\Models\CashBalance;
use App\Models\Credit;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Servicio para crear y gestionar notificaciones en la base de datos
 */
class NotificationService
{
    /**
     * Crear notificación de pago recibido
     */
    public function createPaymentReceivedNotification(
        Payment $payment,
        User $cobrador,
        ?User $manager = null,
        ?User $client = null
    ): ?Notification {
        try {
            $credit = $payment->credit;

            // Crear mensaje descriptivo
            $clientName = $client?->name ?? $credit->client->name ?? 'Cliente';
            $message = sprintf(
                'Pago de Bs. %.2f recibido por %s para el crédito #%d (%s)',
                $payment->amount,
                $cobrador->name,
                $credit->id,
                $clientName
            );

            // Notificar al cobrador
            $cobradorNotification = Notification::create([
                'user_id' => $cobrador->id,
                'payment_id' => $payment->id,
                'type' => 'cobrador_payment_received',
                'message' => sprintf('Has registrado un pago de Bs. %.2f para %s', $payment->amount, $clientName),
                'status' => 'unread',
            ]);

            // Notificar al manager si existe
            if ($manager) {
                Notification::create([
                    'user_id' => $manager->id,
                    'payment_id' => $payment->id,
                    'type' => 'payment_received',
                    'message' => $message,
                    'status' => 'unread',
                ]);
            }

            Log::info('✅ Notificaciones de pago guardadas en DB', [
                'payment_id' => $payment->id,
                'cobrador_id' => $cobrador->id,
                'manager_id' => $manager?->id,
            ]);

            return $cobradorNotification;
        } catch (\Exception $e) {
            Log::error('❌ Error guardando notificación de pago en DB', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Crear notificación de crédito creado
     */
    public function createCreditCreatedNotification(
        Credit $credit,
        User $manager,
        User $cobrador
    ): ?Notification {
        try {
            $clientName = $credit->client->name ?? 'Cliente';
            $message = sprintf(
                'Nuevo crédito #%d por Bs. %.2f creado por %s para %s (pendiente de aprobación)',
                $credit->id,
                $credit->total_amount,
                $cobrador->name,
                $clientName
            );

            $notification = Notification::create([
                'user_id' => $manager->id,
                'payment_id' => null,
                'type' => 'system_alert',
                'message' => $message,
                'status' => 'unread',
            ]);

            Log::info('✅ Notificación de crédito creado guardada en DB', [
                'credit_id' => $credit->id,
                'manager_id' => $manager->id,
            ]);

            return $notification;
        } catch (\Exception $e) {
            Log::error('❌ Error guardando notificación de crédito creado en DB', [
                'credit_id' => $credit->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Crear notificación de crédito aprobado
     */
    public function createCreditApprovedNotification(
        Credit $credit,
        User $manager,
        User $cobrador,
        bool $entregaInmediata = false
    ): ?Notification {
        try {
            $clientName = $credit->client->name ?? 'Cliente';
            $message = sprintf(
                'Crédito #%d aprobado para %s por Bs. %.2f%s',
                $credit->id,
                $clientName,
                $credit->total_amount,
                $entregaInmediata ? ' (entrega inmediata)' : ''
            );

            $notification = Notification::create([
                'user_id' => $cobrador->id,
                'payment_id' => null,
                'type' => 'credit_approved',
                'message' => $message,
                'status' => 'unread',
            ]);

            Log::info('✅ Notificación de crédito aprobado guardada en DB', [
                'credit_id' => $credit->id,
                'cobrador_id' => $cobrador->id,
            ]);

            return $notification;
        } catch (\Exception $e) {
            Log::error('❌ Error guardando notificación de crédito aprobado en DB', [
                'credit_id' => $credit->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Crear notificación de crédito rechazado
     */
    public function createCreditRejectedNotification(
        Credit $credit,
        User $manager,
        User $cobrador
    ): ?Notification {
        try {
            $clientName = $credit->client->name ?? 'Cliente';
            $message = sprintf(
                'Crédito #%d rechazado para %s. Motivo: %s',
                $credit->id,
                $clientName,
                $credit->rejection_reason ?? 'No especificado'
            );

            $notification = Notification::create([
                'user_id' => $cobrador->id,
                'payment_id' => null,
                'type' => 'credit_rejected',
                'message' => $message,
                'status' => 'unread',
            ]);

            Log::info('✅ Notificación de crédito rechazado guardada en DB', [
                'credit_id' => $credit->id,
                'cobrador_id' => $cobrador->id,
            ]);

            return $notification;
        } catch (\Exception $e) {
            Log::error('❌ Error guardando notificación de crédito rechazado en DB', [
                'credit_id' => $credit->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Crear notificación de crédito entregado
     */
    public function createCreditDeliveredNotification(
        Credit $credit,
        User $manager,
        User $cobrador
    ): ?Notification {
        try {
            $clientName = $credit->client->name ?? 'Cliente';
            $message = sprintf(
                'Crédito #%d entregado a %s por Bs. %.2f',
                $credit->id,
                $clientName,
                $credit->amount
            );

            // Notificar al manager
            $notification = Notification::create([
                'user_id' => $manager->id,
                'payment_id' => null,
                'type' => 'system_alert',
                'message' => $message,
                'status' => 'unread',
            ]);

            Log::info('✅ Notificación de crédito entregado guardada en DB', [
                'credit_id' => $credit->id,
                'manager_id' => $manager->id,
            ]);

            return $notification;
        } catch (\Exception $e) {
            Log::error('❌ Error guardando notificación de crédito entregado en DB', [
                'credit_id' => $credit->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Crear notificación de caja cerrada automáticamente
     */
    public function createCashBalanceAutoClosedNotification(
        CashBalance $cashBalance,
        User $cobrador,
        ?User $manager = null
    ): ?Notification {
        try {
            $message = sprintf(
                'Caja del %s cerrada automáticamente. Monto final: Bs. %.2f',
                $cashBalance->date->format('d/m/Y'),
                $cashBalance->final_amount
            );

            $notification = Notification::create([
                'user_id' => $cobrador->id,
                'payment_id' => null,
                'type' => 'system_alert',
                'message' => $message,
                'status' => 'unread',
            ]);

            // Notificar al manager también
            if ($manager) {
                Notification::create([
                    'user_id' => $manager->id,
                    'payment_id' => null,
                    'type' => 'system_alert',
                    'message' => sprintf('Caja de %s (%s) cerrada automáticamente', $cobrador->name, $cashBalance->date->format('d/m/Y')),
                    'status' => 'unread',
                ]);
            }

            Log::info('✅ Notificación de caja auto-cerrada guardada en DB', [
                'cash_balance_id' => $cashBalance->id,
                'cobrador_id' => $cobrador->id,
            ]);

            return $notification;
        } catch (\Exception $e) {
            Log::error('❌ Error guardando notificación de caja auto-cerrada en DB', [
                'cash_balance_id' => $cashBalance->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Crear notificación de caja auto-creada
     */
    public function createCashBalanceAutoCreatedNotification(
        CashBalance $cashBalance,
        User $cobrador,
        ?User $manager = null,
        string $reason = 'payment'
    ): ?Notification {
        try {
            $message = sprintf(
                'Caja del %s abierta automáticamente (%s)',
                $cashBalance->date->format('d/m/Y'),
                $reason === 'payment' ? 'registro de pago' : $reason
            );

            $notification = Notification::create([
                'user_id' => $cobrador->id,
                'payment_id' => null,
                'type' => 'system_alert',
                'message' => $message,
                'status' => 'unread',
            ]);

            Log::info('✅ Notificación de caja auto-creada guardada en DB', [
                'cash_balance_id' => $cashBalance->id,
                'cobrador_id' => $cobrador->id,
            ]);

            return $notification;
        } catch (\Exception $e) {
            Log::error('❌ Error guardando notificación de caja auto-creada en DB', [
                'cash_balance_id' => $cashBalance->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Crear notificación de caja que requiere reconciliación
     */
    public function createCashBalanceReconciliationAlert(
        CashBalance $cashBalance,
        User $cobrador,
        ?User $manager = null,
        string $reason = ''
    ): ?Notification {
        try {
            $message = sprintf(
                'La caja del %s requiere reconciliación. %s',
                $cashBalance->date->format('d/m/Y'),
                $reason
            );

            $notification = Notification::create([
                'user_id' => $cobrador->id,
                'payment_id' => null,
                'type' => 'system_alert',
                'message' => $message,
                'status' => 'unread',
            ]);

            // Notificar al manager también
            if ($manager) {
                Notification::create([
                    'user_id' => $manager->id,
                    'payment_id' => null,
                    'type' => 'system_alert',
                    'message' => sprintf('Caja de %s (%s) requiere reconciliación: %s', $cobrador->name, $cashBalance->date->format('d/m/Y'), $reason),
                    'status' => 'unread',
                ]);
            }

            Log::info('✅ Notificación de caja requiere reconciliación guardada en DB', [
                'cash_balance_id' => $cashBalance->id,
                'cobrador_id' => $cobrador->id,
            ]);

            return $notification;
        } catch (\Exception $e) {
            Log::error('❌ Error guardando notificación de caja requiere reconciliación en DB', [
                'cash_balance_id' => $cashBalance->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
