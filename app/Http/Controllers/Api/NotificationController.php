<?php

namespace App\Http\Controllers\Api;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;

class NotificationController extends BaseController
{
    /**
     * Display a listing of notifications.
     */
    public function index(Request $request)
    {
        $notifications = Notification::with(['user', 'payment'])
            ->when($request->user_id, function ($query, $userId) {
                $query->where('user_id', $userId);
            })
            ->when($request->type, function ($query, $type) {
                $query->where('type', $type);
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return $this->sendResponse($notifications);
    }

    /**
     * Store a newly created notification.
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'payment_id' => 'nullable|exists:payments,id',
            'type' => 'required|in:payment_received,payment_due,credit_approved,credit_rejected,system_alert,cobrador_payment_received',
            'message' => 'required|string',
            'status' => 'in:unread,read,archived',
        ]);

        $notification = Notification::create([
            'user_id' => $request->user_id,
            'payment_id' => $request->payment_id,
            'type' => $request->type,
            'message' => $request->message,
            'status' => $request->status ?? 'unread',
        ]);

        $notification->load(['user', 'payment']);

        return $this->sendResponse($notification, 'Notificación creada exitosamente');
    }

    /**
     * Display the specified notification.
     */
    public function show(Notification $notification)
    {
        $notification->load(['user', 'payment']);
        return $this->sendResponse($notification);
    }

    /**
     * Update the specified notification.
     */
    public function update(Request $request, Notification $notification)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'payment_id' => 'nullable|exists:payments,id',
            'type' => 'required|in:payment_received,payment_due,credit_approved,credit_rejected,system_alert,cobrador_payment_received',
            'message' => 'required|string',
            'status' => 'in:unread,read,archived',
        ]);

        $notification->update([
            'user_id' => $request->user_id,
            'payment_id' => $request->payment_id,
            'type' => $request->type,
            'message' => $request->message,
            'status' => $request->status ?? $notification->status,
        ]);

        $notification->load(['user', 'payment']);

        return $this->sendResponse($notification, 'Notificación actualizada exitosamente');
    }

    /**
     * Remove the specified notification.
     */
    public function destroy(Notification $notification)
    {
        $notification->delete();
        return $this->sendResponse([], 'Notificación eliminada exitosamente');
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(Notification $notification)
    {
        $notification->update(['status' => 'read']);
        return $this->sendResponse($notification, 'Notificación marcada como leída');
    }

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllAsRead(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        Notification::where('user_id', $request->user_id)
            ->where('status', 'unread')
            ->update(['status' => 'read']);

        return $this->sendResponse([], 'Todas las notificaciones marcadas como leídas');
    }

    /**
     * Get notifications by user.
     */
    public function getByUser(User $user)
    {
        $notifications = $user->notifications()->with(['payment'])->orderBy('created_at', 'desc')->get();
        return $this->sendResponse($notifications);
    }

    /**
     * Get unread notifications count for a user.
     */
    public function getUnreadCount(User $user)
    {
        $count = $user->notifications()->where('status', 'unread')->count();
        return $this->sendResponse(['unread_count' => $count]);
    }
}
