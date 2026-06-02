<?php

namespace App\Http\Controllers\API;

use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Events\NotificationCreated;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingMessage;
use App\Models\User;
use App\Notifications\NewBookingMessageNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class BookingMessageController extends Controller
{
    private function authorizeParticipant(Request $request, Booking $booking): bool
    {
        $user = $request->user();
        $booking->loadMissing('schedule');

        if ($user->role === 'member') {
            return $booking->member_id === $user->id;
        }

        if ($user->role === 'provider') {
            return $booking->schedule->provider_id === $user->id;
        }

        return false;
    }

    public function unreadCounts(Request $request): JsonResponse
    {
        $user       = $request->user();
        $senderType = $user->role === 'member' ? 'member' : 'provider';
        $otherType  = $senderType === 'member' ? 'provider' : 'member';

        if ($senderType === 'member') {
            $bookingIds = Booking::where('member_id', $user->id)
                ->whereIn('status', ['confirmed', 'completed'])
                ->pluck('id');
        } else {
            $bookingIds = Booking::whereHas('schedule', fn($q) => $q->where('provider_id', $user->id))
                ->whereIn('status', ['confirmed', 'completed'])
                ->pluck('id');
        }

        // 一次 query 取得所有 booking 的未讀數（只計對方發的、且 read_at 為 NULL）
        $counts = BookingMessage::whereIn('booking_id', $bookingIds)
            ->where('sender_type', $otherType)
            ->whereNull('read_at')
            ->selectRaw('booking_id, COUNT(*) as count')
            ->groupBy('booking_id')
            ->pluck('count', 'booking_id');

        return response()->json(['status' => true, 'data' => $counts]);
    }

    public function index(Request $request, Booking $booking): JsonResponse
    {
        if (!$this->authorizeParticipant($request, $booking)) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        $status = $booking->status->value;
        if (!in_array($status, ['confirmed', 'completed'])) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        $messages = $booking->messages()->orderBy('created_at')->get();

        return response()->json(['status' => true, 'data' => $messages]);
    }

    public function store(Request $request, Booking $booking): JsonResponse
    {
        if (!$this->authorizeParticipant($request, $booking)) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        if ($booking->status->value !== 'confirmed') {
            return response()->json(['status' => false, 'message' => '訊息功能僅在預約確認期間開放'], 403);
        }

        $request->validate(['type' => 'required|in:text,image']);

        $user = $request->user();
        $senderType = $user->role === 'member' ? 'member' : 'provider';

        if ($request->input('type') === 'text') {
            $request->validate(['content' => 'required|string|max:5000']);

            $message = BookingMessage::create([
                'booking_id'  => $booking->id,
                'sender_id'   => $user->id,
                'sender_type' => $senderType,
                'type'        => 'text',
                'content'     => $request->input('content'),
            ]);
        } else {
            $request->validate([
                'file' => 'required|file|mimes:jpg,jpeg,png,gif,webp|max:10240',
            ]);

            $manager = new ImageManager(new Driver());
            $image = $manager->read($request->file('file'));

            if ($image->width() > 2048 || $image->height() > 2048) {
                $image->scaleDown(width: 2048, height: 2048);
            }

            $uuid     = Str::uuid();
            $filename = "booking-images/{$uuid}.jpg";
            $encoded  = $image->toJpeg(quality: 85);

            Storage::disk('public')->put($filename, $encoded);
            $url = Storage::disk('public')->url($filename);

            $message = BookingMessage::create([
                'booking_id'  => $booking->id,
                'sender_id'   => $user->id,
                'sender_type' => $senderType,
                'type'        => 'image',
                'content'     => $url,
            ]);
        }

        broadcast(new MessageSent($message));

        // 通知另一方（寫入 DB notification，讓 Bell 圖示更新）
        $receiverId = $senderType === 'member'
            ? $booking->schedule->provider_id
            : $booking->member_id;

        $receiver = User::find($receiverId);
        if ($receiver) {
            // 同步寫進 DB（非 queue），確保下一行 broadcast 時 unread count 已是最新
            $receiver->notify(new NewBookingMessageNotification($message, $booking, $user));
            // 通知前端：立刻更新 bell badge，不等 polling
            broadcast(new NotificationCreated($receiver->id));
        }

        return response()->json(['status' => true, 'data' => $message], 201);
    }

    public function markRead(Request $request, Booking $booking): JsonResponse
    {
        if (!$this->authorizeParticipant($request, $booking)) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        $status = $booking->status->value;
        if (!in_array($status, ['confirmed', 'completed'])) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        $request->validate(['last_read_message_id' => 'required|integer']);

        $user       = $request->user();
        $senderType = $user->role === 'member' ? 'member' : 'provider';
        $otherType  = $senderType === 'member' ? 'provider' : 'member';

        $updated = BookingMessage::where('booking_id', $booking->id)
            ->where('sender_type', $otherType)
            ->where('id', '<=', $request->input('last_read_message_id'))
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($updated > 0 && $status === 'confirmed') {
            broadcast(new MessageRead(
                $booking->id,
                $senderType,
                $request->input('last_read_message_id'),
            ));
        }

        return response()->json(['status' => true]);
    }
}
