<?php

namespace App\Docs;

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="會員預約",
 *     description="會員的預約管理"
 * )
 * @OA\Tag(
 *     name="會員評價",
 *     description="會員的評價管理"
 * )
 * @OA\Tag(
 *     name="通知",
 *     description="站內通知管理（Member / Provider 共用）"
 * )
 * @OA\Tag(
 *     name="即時訊息",
 *     description="預約聊天室訊息（Member / Provider 共用，僅限 confirmed / completed 預約的參與方）"
 * )
 */
class MemberApiDoc
{
    // -----------------------------------------------------------------------
    // Member Bookings
    // -----------------------------------------------------------------------

    /**
     * 建立預約
     *
     * @OA\Post(
     *     path="/member/bookings",
     *     summary="建立預約",
     *     description="會員建立新的課程預約",
     *     operationId="createBooking",
     *     tags={"會員預約"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"offer_id","schedule_id","participants"},
     *             @OA\Property(property="offer_id", type="integer", example=1, description="課程 ID"),
     *             @OA\Property(property="schedule_id", type="integer", example=2, description="時段 ID"),
     *             @OA\Property(property="participants", type="integer", example=2, description="參加人數"),
     *             @OA\Property(property="note", type="string", nullable=true, example="需要器材租借")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="預約建立成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="預約成功"),
     *             @OA\Property(property="data", ref="#/components/schemas/Booking")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="驗證失敗或時段已滿",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="無權限（非 member 角色）",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function createBooking()
    {
    }

    /**
     * 取得我的預約列表
     *
     * @OA\Get(
     *     path="/member/bookings",
     *     summary="取得我的預約列表",
     *     description="分頁回傳當前會員的所有預約",
     *     operationId="listMemberBookings",
     *     tags={"會員預約"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="page", in="query", description="頁碼", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", description="每頁筆數", @OA\Schema(type="integer", default=15)),
     *     @OA\Response(
     *         response=200,
     *         description="取得成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Booking")),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *         )
     *     )
     * )
     */
    public function listMemberBookings()
    {
    }

    /**
     * 取得單一預約
     *
     * @OA\Get(
     *     path="/member/bookings/{id}",
     *     summary="取得單一預約",
     *     operationId="getMemberBooking",
     *     tags={"會員預約"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="預約 ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="取得成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/Booking")
     *         )
     *     ),
     *     @OA\Response(response=404, description="預約不存在", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=403, description="無權限存取", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function getMemberBooking()
    {
    }

    /**
     * 取消預約
     *
     * @OA\Delete(
     *     path="/member/bookings/{id}",
     *     summary="取消預約",
     *     description="會員取消自己的預約",
     *     operationId="cancelBooking",
     *     tags={"會員預約"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="預約 ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="取消成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="預約已取消")
     *         )
     *     ),
     *     @OA\Response(response=403, description="無權限或狀態不允許取消", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=404, description="預約不存在", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function cancelBooking()
    {
    }

    // -----------------------------------------------------------------------
    // Member Reviews
    // -----------------------------------------------------------------------

    /**
     * 建立評價
     *
     * @OA\Post(
     *     path="/member/reviews",
     *     summary="建立評價",
     *     description="會員對已完成的預約課程提交評價",
     *     operationId="createReview",
     *     tags={"會員評價"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"booking_id","rating"},
     *             @OA\Property(property="booking_id", type="integer", example=5),
     *             @OA\Property(property="rating", type="integer", minimum=1, maximum=5, example=4),
     *             @OA\Property(property="comment", type="string", nullable=true, example="課程非常棒！"),
     *             @OA\Property(property="is_anonymous", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="評價建立成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="評價已提交"),
     *             @OA\Property(property="data", ref="#/components/schemas/Review")
     *         )
     *     ),
     *     @OA\Response(response=403, description="預約未完成或非本人預約", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=422, description="驗證失敗", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function createReview()
    {
    }

    /**
     * 更新評價
     *
     * @OA\Put(
     *     path="/member/reviews/{id}",
     *     summary="更新評價",
     *     operationId="updateReview",
     *     tags={"會員評價"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="評價 ID", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="rating", type="integer", minimum=1, maximum=5, example=5),
     *             @OA\Property(property="comment", type="string", nullable=true, example="更新後的評語"),
     *             @OA\Property(property="is_anonymous", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="更新成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/Review")
     *         )
     *     ),
     *     @OA\Response(response=403, description="非本人評價", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=404, description="評價不存在", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function updateReview()
    {
    }

    /**
     * 刪除評價
     *
     * @OA\Delete(
     *     path="/member/reviews/{id}",
     *     summary="刪除評價",
     *     operationId="deleteReview",
     *     tags={"會員評價"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="評價 ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="刪除成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="評價已刪除")
     *         )
     *     ),
     *     @OA\Response(response=403, description="非本人評價", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=404, description="評價不存在", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function deleteReview()
    {
    }

    /**
     * 標記評價為有幫助
     *
     * @OA\Post(
     *     path="/reviews/{id}/helpful",
     *     summary="標記評價為有幫助",
     *     description="切換投票狀態（已投票則撤回）",
     *     operationId="markReviewHelpful",
     *     tags={"會員評價"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="評價 ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="操作成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="helpful_count", type="integer", example=4),
     *             @OA\Property(property="has_voted", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=404, description="評價不存在", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function markReviewHelpful()
    {
    }

    // -----------------------------------------------------------------------
    // Booking Messages（即時訊息，Member + Provider 共用）
    // -----------------------------------------------------------------------

    /**
     * 取得各預約未讀訊息數
     *
     * @OA\Get(
     *     path="/bookings/messages/unread-counts",
     *     summary="取得各預約未讀訊息數",
     *     description="回傳當前使用者每個預約中對方發送的未讀訊息數量（Key 為 booking_id）",
     *     operationId="getMessageUnreadCounts",
     *     tags={"即時訊息"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="取得成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 description="Key 為 booking_id（string），value 為未讀數（integer）",
     *                 example={"12": 3, "17": 1}
     *             )
     *         )
     *     )
     * )
     */
    public function getMessageUnreadCounts()
    {
    }

    /**
     * 取得預約訊息列表
     *
     * @OA\Get(
     *     path="/bookings/{booking}/messages",
     *     summary="取得預約訊息列表",
     *     description="回傳指定預約的所有訊息（依時間升冪排列），僅限預約參與方存取，且預約狀態需為 confirmed 或 completed",
     *     operationId="listBookingMessages",
     *     tags={"即時訊息"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="booking", in="path", required=true, description="預約 ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="取得成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="booking_id", type="integer", example=12),
     *                     @OA\Property(property="sender_type", type="string", enum={"member","provider"}, example="member"),
     *                     @OA\Property(property="message", type="string", nullable=true, example="請問需要自備潛水裝備嗎？"),
     *                     @OA\Property(property="image_url", type="string", nullable=true, example=null),
     *                     @OA\Property(property="read_at", type="string", nullable=true, example=null),
     *                     @OA\Property(property="created_at", type="string", example="2025-07-01T10:00:00.000000Z")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="非參與方或預約狀態不符", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=404, description="預約不存在", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function listBookingMessages()
    {
    }

    /**
     * 發送預約訊息
     *
     * @OA\Post(
     *     path="/bookings/{booking}/messages",
     *     summary="發送預約訊息",
     *     description="向指定預約的對方發送文字訊息或圖片（multipart/form-data）",
     *     operationId="sendBookingMessage",
     *     tags={"即時訊息"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="booking", in="path", required=true, description="預約 ID", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="message", type="string", nullable=true, example="請問需要自備潛水裝備嗎？"),
     *                 @OA\Property(property="image", type="string", format="binary", nullable=true, description="圖片（jpg/png，message 與 image 至少擇一）")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="訊息已送出",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=42),
     *                 @OA\Property(property="booking_id", type="integer", example=12),
     *                 @OA\Property(property="sender_type", type="string", example="member"),
     *                 @OA\Property(property="message", type="string", nullable=true, example="請問需要自備潛水裝備嗎？"),
     *                 @OA\Property(property="image_url", type="string", nullable=true, example=null),
     *                 @OA\Property(property="read_at", type="string", nullable=true, example=null),
     *                 @OA\Property(property="created_at", type="string", example="2025-07-01T10:05:00.000000Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="非參與方或預約狀態不符", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=422, description="message 與 image 皆為空", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function sendBookingMessage()
    {
    }

    /**
     * 標記訊息為已讀
     *
     * @OA\Post(
     *     path="/bookings/{booking}/messages/read",
     *     summary="標記訊息為已讀",
     *     description="將指定預約中對方發送的所有未讀訊息批次標記為已讀",
     *     operationId="markMessagesRead",
     *     tags={"即時訊息"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="booking", in="path", required=true, description="預約 ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="標記成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="已標記為已讀")
     *         )
     *     ),
     *     @OA\Response(response=403, description="非參與方", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function markMessagesRead()
    {
    }

    // -----------------------------------------------------------------------
    // Notifications (Member + Provider 共用)
    // -----------------------------------------------------------------------

    /**
     * 取得通知列表
     *
     * @OA\Get(
     *     path="/notifications",
     *     summary="取得通知列表",
     *     description="分頁回傳當前使用者的所有通知（Member / Provider 共用）",
     *     operationId="listNotifications",
     *     tags={"通知"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="page", in="query", description="頁碼", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", description="每頁筆數", @OA\Schema(type="integer", default=15)),
     *     @OA\Response(
     *         response=200,
     *         description="取得成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="type", type="string", example="booking_confirmed"),
     *                     @OA\Property(property="title", type="string", example="預約已確認"),
     *                     @OA\Property(property="message", type="string", example="您的預約已由教練確認"),
     *                     @OA\Property(property="read_at", type="string", nullable=true, example=null),
     *                     @OA\Property(property="created_at", type="string", example="2025-01-01T00:00:00.000000Z")
     *                 )
     *             ),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *         )
     *     )
     * )
     */
    public function listNotifications()
    {
    }

    /**
     * 取得未讀通知數量
     *
     * @OA\Get(
     *     path="/notifications/unread-count",
     *     summary="取得未讀通知數量",
     *     operationId="getUnreadNotificationCount",
     *     tags={"通知"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="取得成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="unread_count", type="integer", example=3)
     *         )
     *     )
     * )
     */
    public function getUnreadNotificationCount()
    {
    }

    /**
     * 標記單一通知為已讀
     *
     * @OA\Patch(
     *     path="/notifications/{id}/read",
     *     summary="標記單一通知為已讀",
     *     operationId="markNotificationRead",
     *     tags={"通知"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="通知 ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="標記成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="通知已標記為已讀")
     *         )
     *     ),
     *     @OA\Response(response=404, description="通知不存在", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function markNotificationRead()
    {
    }

    /**
     * 全部通知標記為已讀
     *
     * @OA\Patch(
     *     path="/notifications/read-all",
     *     summary="全部通知標記為已讀",
     *     operationId="markAllNotificationsRead",
     *     tags={"通知"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="標記成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="所有通知已標記為已讀")
     *         )
     *     )
     * )
     */
    public function markAllNotificationsRead()
    {
    }

    /**
     * 刪除通知
     *
     * @OA\Delete(
     *     path="/notifications/{id}",
     *     summary="刪除通知",
     *     operationId="deleteNotification",
     *     tags={"通知"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="通知 ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="刪除成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="通知已刪除")
     *         )
     *     ),
     *     @OA\Response(response=404, description="通知不存在", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function deleteNotification()
    {
    }
}
