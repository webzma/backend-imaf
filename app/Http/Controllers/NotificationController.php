<?php

namespace App\Http\Controllers;

use App\Http\Resources\DatabaseNotificationResource;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Services\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    /**
     * @phpstan-return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(20);

        return DatabaseNotificationResource::collection($notifications);
    }

    /**
     * @phpstan-return JsonResponse
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * @phpstan-return JsonResponse
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $request->user()
            ->notifications()
            ->whereKey($id)
            ->firstOrFail()
            ->markAsRead();

        return response()->json(['message' => 'Notificación marcada como leída.']);
    }

    /**
     * @phpstan-return JsonResponse
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()
            ->unreadNotifications()
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Todas las notificaciones marcadas como leídas.']);
    }

    /**
     * @phpstan-return JsonResponse
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|email|exists:users,email',
            'titulo' => 'required|string|max:255',
            'mensaje' => 'required|string',
            'url' => 'nullable|string',
        ]);

        /** @var User $user */
        $user = User::query()->where('email', $validated['user_id'])->firstOrFail();

        $user->notify(new GenericNotification(
            $validated['titulo'],
            $validated['mensaje'],
            $validated['url'] ?? null
        ));

        Auditoria::registrar('notificacion.enviada', "Notificación manual enviada a {$user->email}: «{$validated['titulo']}»", [
            'entidad' => $user,
            'metadatos' => ['titulo' => $validated['titulo'], 'url' => $validated['url'] ?? null],
        ]);

        return response()->json(['message' => 'Notificación enviada con éxito.']);
    }
}
