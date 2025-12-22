<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationRead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Lista notificações para o usuário atual (navbar dropdown)
     */
    public function index(Request $request)
    {
        $userId = Auth::id();

        $notifications = Notification::active()
            ->with('creator:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($notification) use ($userId) {
                $isRead = $notification->isReadBy($userId);
                return [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'type' => $notification->type,
                    'icon' => $notification->icon ?? $this->getDefaultIcon($notification->type),
                    'link' => $notification->link,
                    'is_read' => $isRead,
                    'created_at' => $notification->created_at->toISOString(),
                    'time_ago' => $notification->created_at->diffForHumans(),
                    'creator_name' => $notification->creator?->name ?? 'Sistema',
                ];
            });

        $unreadCount = Notification::active()->unreadBy($userId)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    /**
     * Histórico completo de notificações (página de histórico)
     */
    public function history(Request $request)
    {
        $userId = Auth::id();
        $perPage = $request->input('per_page', 20);
        $filter = $request->input('filter', 'all'); // all, read, unread

        $query = Notification::with('creator:id,name')
            ->orderBy('created_at', 'desc');

        // Filtrar por status de leitura
        if ($filter === 'unread') {
            $query->unreadBy($userId)->active();
        } elseif ($filter === 'read') {
            $query->whereHas('reads', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            });
        }

        $paginated = $query->paginate($perPage);

        $notifications = $paginated->getCollection()->map(function ($notification) use ($userId) {
            $isRead = $notification->isReadBy($userId);
            $readAt = NotificationRead::where('notification_id', $notification->id)
                ->where('user_id', $userId)
                ->value('read_at');

            return [
                'id' => $notification->id,
                'title' => $notification->title,
                'message' => $notification->message,
                'type' => $notification->type,
                'icon' => $notification->icon ?? $this->getDefaultIcon($notification->type),
                'link' => $notification->link,
                'is_read' => $isRead,
                'read_at' => $readAt,
                'created_at' => $notification->created_at->toISOString(),
                'time_ago' => $notification->created_at->diffForHumans(),
                'creator_name' => $notification->creator?->name ?? 'Sistema',
                'is_active' => $notification->is_active,
                'is_expired' => $notification->expires_at && $notification->expires_at->isPast(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $notifications,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Marcar uma notificação como lida
     */
    public function markAsRead(Request $request, $id)
    {
        $userId = Auth::id();

        $notification = Notification::findOrFail($id);

        NotificationRead::firstOrCreate(
            [
                'notification_id' => $notification->id,
                'user_id' => $userId,
            ],
            [
                'read_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Notificação marcada como lida',
        ]);
    }

    /**
     * Marcar todas as notificações como lidas
     */
    public function markAllAsRead(Request $request)
    {
        $userId = Auth::id();

        $unreadNotifications = Notification::active()
            ->unreadBy($userId)
            ->pluck('id');

        foreach ($unreadNotifications as $notificationId) {
            NotificationRead::firstOrCreate(
                [
                    'notification_id' => $notificationId,
                    'user_id' => $userId,
                ],
                [
                    'read_at' => now(),
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Todas as notificações foram marcadas como lidas',
            'count' => $unreadNotifications->count(),
        ]);
    }

    /**
     * Marcar como não lida (desfazer leitura)
     */
    public function markAsUnread(Request $request, $id)
    {
        $userId = Auth::id();

        NotificationRead::where('notification_id', $id)
            ->where('user_id', $userId)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notificação marcada como não lida',
        ]);
    }

    // =====================================================
    // ADMIN ENDPOINTS
    // =====================================================

    /**
     * Lista todas as notificações (admin)
     */
    public function adminIndex(Request $request)
    {
        $perPage = $request->input('per_page', 20);
        $search = $request->input('search', '');

        $query = Notification::with('creator:id,name')
            ->withCount('reads')
            ->orderBy('created_at', 'desc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $paginated = $query->paginate($perPage);

        $notifications = $paginated->getCollection()->map(function ($notification) {
            return [
                'id' => $notification->id,
                'title' => $notification->title,
                'message' => $notification->message,
                'type' => $notification->type,
                'icon' => $notification->icon,
                'link' => $notification->link,
                'is_active' => $notification->is_active,
                'expires_at' => $notification->expires_at?->toISOString(),
                'created_at' => $notification->created_at->toISOString(),
                'creator_name' => $notification->creator?->name ?? 'Sistema',
                'reads_count' => $notification->reads_count,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $notifications,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Criar nova notificação (admin)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
            'type' => 'required|in:info,success,warning,error',
            'icon' => 'nullable|string|max:50',
            'link' => 'nullable|string|max:500',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $notification = Notification::create([
            ...$validated,
            'created_by' => Auth::id(),
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notificação criada com sucesso',
            'data' => [
                'id' => $notification->id,
                'title' => $notification->title,
            ],
        ], 201);
    }

    /**
     * Atualizar notificação (admin)
     */
    public function update(Request $request, $id)
    {
        $notification = Notification::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'message' => 'sometimes|required|string|max:2000',
            'type' => 'sometimes|required|in:info,success,warning,error',
            'icon' => 'nullable|string|max:50',
            'link' => 'nullable|string|max:500',
            'expires_at' => 'nullable|date',
            'is_active' => 'sometimes|boolean',
        ]);

        $notification->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Notificação atualizada com sucesso',
        ]);
    }

    /**
     * Deletar notificação (admin)
     */
    public function destroy($id)
    {
        $notification = Notification::findOrFail($id);
        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notificação excluída com sucesso',
        ]);
    }

    /**
     * Ícone padrão por tipo
     */
    private function getDefaultIcon(string $type): string
    {
        return match ($type) {
            'success' => 'tabler-circle-check',
            'warning' => 'tabler-alert-triangle',
            'error' => 'tabler-alert-circle',
            default => 'tabler-info-circle',
        };
    }
}
