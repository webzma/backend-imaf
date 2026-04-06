<?php

namespace App\Http\Controllers;

use App\Http\Resources\DatabaseNotificationResource;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(20);

        return DatabaseNotificationResource::collection($notifications);
    }

    public function unreadCount(Request $request)
    {
        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count()
        ]);
    }

    public function markAsRead(Request $request, string $id)
    {
        $request->user()
            ->notifications()
            ->whereKey($id)
            ->firstOrFail()
            ->markAsRead();

        return response()->json(['message' => 'Notificación marcada como leída.']);
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()
            ->unreadNotifications()
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Todas las notificaciones marcadas como leídas.']);
    }

    public function send(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|email|exists:users,email',
            'titulo' => 'required|string|max:255',
            'mensaje' => 'required|string',
            'url' => 'nullable|string'
        ]);

        $user = User::where('email', $validated['user_id'])->firstOrFail();

        $user->notify(new GenericNotification(
            $validated['titulo'],
            $validated['mensaje'],
            $validated['url'] ?? null
        ));

        return response()->json(['message' => 'Notificación enviada con éxito.']);
    }
}
