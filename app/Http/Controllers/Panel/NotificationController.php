<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class NotificationController extends Controller
{
    public function unreadCount(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $count = Notification::query()
            ->where('user_id', $userId)
            ->where('status', Notification::STATUS_UNREAD)
            ->count();

        return response()->json([
            'unread_count' => (int) $count,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $perPage = (int) $request->query('per_page', 8);
        $perPage = min(max($perPage, 5), 20);

        $query = Notification::query()
            ->with(['type:id,name,slug,severity'])
            ->where('user_id', $userId)
            ->orderByDesc('created_at');

        $paginator = $query->paginate($perPage);

        $tz = 'America/Sao_Paulo';
        $rows = collect($paginator->items())->map(function (Notification $notification) use ($tz) {
            $createdAt = $notification->created_at
                ? Carbon::parse($notification->created_at)->setTimezone($tz)
                : null;

            return [
                'id' => (int) $notification->id,
                'title' => (string) $notification->title,
                'message' => (string) $notification->message,
                'status' => (string) $notification->status,
                'source_type' => $notification->source_type,
                'source_id' => $notification->source_id ? (int) $notification->source_id : null,
                'payload_json' => $notification->payload_json,
                'type' => $notification->type ? [
                    'id' => (int) $notification->type->id,
                    'name' => $notification->type->name,
                    'slug' => $notification->type->slug,
                    'severity' => $notification->type->severity,
                ] : null,
                'created_at' => optional($notification->created_at)?->toIso8601String(),
                'created_at_formatted' => $createdAt?->format('d/m/Y H:i'),
            ];
        })->values();

        $unreadCount = Notification::query()
            ->where('user_id', $userId)
            ->where('status', Notification::STATUS_UNREAD)
            ->count();

        return response()->json([
            'data' => $rows,
            'current_page' => (int) $paginator->currentPage(),
            'per_page' => (int) $paginator->perPage(),
            'last_page' => (int) $paginator->lastPage(),
            'total' => (int) $paginator->total(),
            'unread_count' => (int) $unreadCount,
        ]);
    }

    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        $this->guardOwnership($request, $notification);

        if ($notification->status !== Notification::STATUS_READ) {
            $notification->status = Notification::STATUS_READ;
            $notification->read_at = now();
            $notification->save();
        }

        return response()->json([
            'ok' => true,
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;

        Notification::query()
            ->where('user_id', $userId)
            ->where('status', Notification::STATUS_UNREAD)
            ->update([
                'status' => Notification::STATUS_READ,
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'ok' => true,
        ]);
    }

    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        $this->guardOwnership($request, $notification);
        $notification->delete();

        return response()->json([
            'ok' => true,
        ]);
    }

    protected function guardOwnership(Request $request, Notification $notification): void
    {
        abort_if((int) $notification->user_id !== (int) $request->user()->id, 403);
    }
}
