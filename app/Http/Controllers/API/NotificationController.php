<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user          = $request->user();
            $unreadCount   = $user->unreadNotifications()->count();
            $notifications = $user->notifications()
                ->orderByDesc('created_at')
                ->paginate(20);

            $items = $notifications->map(fn($n) => array_merge($n->data, [
                'id'         => $n->id,
                'read_at'    => $n->read_at?->toISOString(),
                'created_at' => $n->created_at->toISOString(),
            ]));

            return response()->json([
                'status'       => true,
                'data'         => $items,
                'unread_count' => $unreadCount,
                'meta'         => [
                    'current_page' => $notifications->currentPage(),
                    'last_page'    => $notifications->lastPage(),
                    'total'        => $notifications->total(),
                ],
            ]);
        } catch (\Throwable) {
            return response()->json(['status' => true, 'data' => [], 'unread_count' => 0, 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0]]);
        }
    }

    public function unreadCount(Request $request)
    {
        try {
            return response()->json([
                'status' => true,
                'data'   => ['count' => $request->user()->unreadNotifications()->count()],
            ]);
        } catch (\Throwable) {
            return response()->json(['status' => true, 'data' => ['count' => 0]]);
        }
    }

    public function markRead(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['status' => true]);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['status' => true]);
    }

    public function destroy(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->delete();

        return response()->json(null, 204);
    }
}
