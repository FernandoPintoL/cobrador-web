<?php

namespace App\Listeners;

use App\Events\CreditWaitingListUpdate;
use App\Models\Notification;
use App\Models\User;
use App\Events\TestNotification;
use Illuminate\Support\Facades\Log;

class SendCreditWaitingListNotification
{
    public function handle(CreditWaitingListUpdate $event): void
    {
        $credit = $event->credit;
        $action = $event->action;
        $user = $event->user;
        
        Log::info("Processing CreditWaitingListUpdate: {$action} for credit {$credit->id}");
        
        switch ($action) {
            case 'created':
                $this->handleCreditCreated($credit, $user);
                break;
                
            case 'approved':
                $this->handleCreditApproved($credit, $user);
                break;
                
            case 'rejected':
                $this->handleCreditRejected($credit, $user);
                break;
                
            case 'delivered':
                $this->handleCreditDelivered($credit, $user);
                break;
        }
    }
    
    private function handleCreditCreated($credit, $user)
    {
        $cobrador = User::find($credit->created_by);
        
        if ($cobrador && $cobrador->assignedManager) {
            $manager = $cobrador->assignedManager;
            
            $notification = Notification::create([
                'user_id' => $manager->id,
                'payment_id' => null,
                'type' => 'system_alert',
                'message' => "El cobrador {$cobrador->name} ha creado un crédito de \${$credit->amount} para el cliente {$credit->client->name} que requiere tu aprobación.",
                'status' => 'unread'
            ]);
            
            event(new TestNotification($notification, $manager));
            
            Log::info("Notification sent to manager {$manager->id} for credit {$credit->id}");
        }
    }
    
    private function handleCreditApproved($credit, $user)
    {
        $cobrador = User::find($credit->created_by);
        
        if ($cobrador) {
            $notification = Notification::create([
                'user_id' => $cobrador->id,
                'payment_id' => null,
                'type' => 'credit_approved',
                'message' => "Tu crédito de \${$credit->amount} para {$credit->client->name} ha sido aprobado por {$user->name}.",
                'status' => 'unread'
            ]);
            
            event(new TestNotification($notification, $cobrador));
            
            Log::info("Approval notification sent to cobrador {$cobrador->id} for credit {$credit->id}");
        }
    }
    
    private function handleCreditRejected($credit, $user)
    {
        $cobrador = User::find($credit->created_by);
        
        if ($cobrador) {
            $notification = Notification::create([
                'user_id' => $cobrador->id,
                'payment_id' => null,
                'type' => 'credit_rejected',
                'message' => "Tu crédito de \${$credit->amount} para {$credit->client->name} ha sido rechazado por {$user->name}.",
                'status' => 'unread'
            ]);
            
            event(new TestNotification($notification, $cobrador));
            
            Log::info("Rejection notification sent to cobrador {$cobrador->id} for credit {$credit->id}");
        }
    }
    
    private function handleCreditDelivered($credit, $user)
    {
        $cobrador = User::find($credit->created_by);
        
        if ($cobrador && $cobrador->assignedManager) {
            $manager = $cobrador->assignedManager;
            
            $notification = Notification::create([
                'user_id' => $manager->id,
                'payment_id' => null,
                'type' => 'system_alert',
                'message' => "El crédito de \${$credit->amount} para {$credit->client->name} ha sido entregado por {$cobrador->name}.",
                'status' => 'unread'
            ]);
            
            event(new TestNotification($notification, $manager));
            
            Log::info("Delivery notification sent to manager {$manager->id} for credit {$credit->id}");
        }
    }
}
