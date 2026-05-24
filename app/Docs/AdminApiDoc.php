<?php

namespace App\Docs;

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Admin 統計",
 *     description="管理員平台統計"
 * )
 * @OA\Tag(
 *     name="Admin 會員管理",
 *     description="管理員的會員帳號管理"
 * )
 * @OA\Tag(
 *     name="Admin 教練管理",
 *     description="管理員的服務提供者帳號管理"
 * )
 * @OA\Tag(
 *     name="Admin 課程管理",
 *     description="管理員的課程、預約、評價管理"
 * )
 */
class AdminApiDoc
{
    // -----------------------------------------------------------------------
    // Admin Stats
    // -----------------------------------------------------------------------

    /**
     * 取得平台統計數據
     *
     * @OA\Get(
     *     path="/admin/stats",
     *     summary="取得平台統計數據",
     *     description="回傳會員總數、服務提供者總數、課程總數；非 admin 角色回傳 403",
     *     operationId="getAdminStats",
     *     tags={"Admin 統計"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="取得成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_members", type="integer", example=128),
     *                 @OA\Property(property="total_providers", type="integer", example=34),
     *                 @OA\Property(property="total_offers", type="integer", example=87)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="非 admin 角色", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function getAdminStats()
    {
    }

    // -----------------------------------------------------------------------
    // Admin Member Management
    // -----------------------------------------------------------------------

    /**
     * 取得會員列表
     *
     * @OA\Get(
     *     path="/admin/members",
     *     summary="取得會員列表",
     *     description="分頁回傳所有會員帳號，含 member_profile",
     *     operationId="listAdminMembers",
     *     tags={"Admin 會員管理"},
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
     *                     @OA\Property(property="name", type="string", example="王小明"),
     *                     @OA\Property(property="email", type="string", example="member@example.com"),
     *                     @OA\Property(property="role", type="string", example="member"),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="created_at", type="string", example="2025-01-01T00:00:00.000000Z"),
     *                     @OA\Property(property="member_profile", type="object", nullable=true)
     *                 )
     *             ),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *         )
     *     ),
     *     @OA\Response(response=403, description="非 admin 角色", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function listAdminMembers()
    {
    }

    /**
     * 取得單一會員
     *
     * @OA\Get(
     *     path="/admin/members/{id}",
     *     summary="取得單一會員",
     *     operationId="getAdminMember",
     *     tags={"Admin 會員管理"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="使用者 ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="取得成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="非 admin 角色", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=404, description="會員不存在", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function getAdminMember()
    {
    }

    /**
     * 切換會員啟用狀態
     *
     * @OA\Put(
     *     path="/admin/members/{id}/toggle-active",
     *     summary="切換會員啟用狀態",
     *     description="啟用或停用指定會員帳號",
     *     operationId="toggleMemberActive",
     *     tags={"Admin 會員管理"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="使用者 ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="切換成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="帳號已停用"),
     *             @OA\Property(property="is_active", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(response=403, description="非 admin 角色", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=404, description="會員不存在", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function toggleMemberActive()
    {
    }

    /**
     * 確認會員存在
     *
     * @OA\Get(
     *     path="/admin/check-member/{id}",
     *     summary="確認會員存在",
     *     description="快速確認指定 ID 的會員是否存在（角色為 member）",
     *     operationId="checkMember",
     *     tags={"Admin 會員管理"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="使用者 ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="會員存在",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="exists", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=403, description="非 admin 角色", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function checkMember()
    {
    }

    // -----------------------------------------------------------------------
    // Admin Provider Management
    // -----------------------------------------------------------------------

    /**
     * 取得教練列表
     *
     * @OA\Get(
     *     path="/admin/providers",
     *     summary="取得教練列表",
     *     description="分頁回傳所有服務提供者，含 provider_profile",
     *     operationId="listAdminProviders",
     *     tags={"Admin 教練管理"},
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
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="name", type="string", example="林教練"),
     *                     @OA\Property(property="email", type="string", example="coach@example.com"),
     *                     @OA\Property(property="role", type="string", example="provider"),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_verified", type="boolean", example=false),
     *                     @OA\Property(property="provider_profile", type="object", nullable=true)
     *                 )
     *             ),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *         )
     *     ),
     *     @OA\Response(response=403, description="非 admin 角色", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function listAdminProviders()
    {
    }

    /**
     * 取得單一教練
     *
     * @OA\Get(
     *     path="/admin/providers/{id}",
     *     summary="取得單一教練",
     *     operationId="getAdminProvider",
     *     tags={"Admin 教練管理"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="使用者 ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="取得成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="非 admin 角色", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=404, description="教練不存在", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function getAdminProvider()
    {
    }

    /**
     * 切換教練啟用狀態
     *
     * @OA\Put(
     *     path="/admin/providers/{id}/toggle-active",
     *     summary="切換教練啟用狀態",
     *     operationId="toggleProviderActive",
     *     tags={"Admin 教練管理"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="使用者 ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="切換成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="帳號已停用"),
     *             @OA\Property(property="is_active", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(response=403, description="非 admin 角色", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=404, description="教練不存在", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function toggleProviderActive()
    {
    }

    /**
     * 切換教練審核狀態
     *
     * @OA\Put(
     *     path="/admin/providers/{id}/toggle-verified",
     *     summary="切換教練審核狀態",
     *     description="通過或撤銷教練審核，回傳新的 is_verified 狀態",
     *     operationId="toggleProviderVerified",
     *     tags={"Admin 教練管理"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="使用者 ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="切換成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="教練已通過審核"),
     *             @OA\Property(property="is_verified", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=403, description="非 admin 角色", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=404, description="教練不存在", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function toggleProviderVerified()
    {
    }

    /**
     * 確認教練存在
     *
     * @OA\Get(
     *     path="/admin/check-provider/{id}",
     *     summary="確認教練存在",
     *     operationId="checkProvider",
     *     tags={"Admin 教練管理"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="使用者 ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="查詢成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="exists", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=403, description="非 admin 角色", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function checkProvider()
    {
    }

    // -----------------------------------------------------------------------
    // Admin Offers / Bookings / Reviews
    // -----------------------------------------------------------------------

    /**
     * 取得所有課程（Admin）
     *
     * @OA\Get(
     *     path="/admin/offers",
     *     summary="取得所有課程（Admin）",
     *     description="分頁回傳全平台課程列表",
     *     operationId="listAdminOffers",
     *     tags={"Admin 課程管理"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="page", in="query", description="頁碼", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", description="每頁筆數", @OA\Schema(type="integer", default=15)),
     *     @OA\Response(
     *         response=200,
     *         description="取得成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/DivingOffer")),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *         )
     *     ),
     *     @OA\Response(response=403, description="非 admin 角色", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function listAdminOffers()
    {
    }

    /**
     * 刪除課程（Admin）
     *
     * @OA\Delete(
     *     path="/admin/offers/{id}",
     *     summary="刪除課程（Admin）",
     *     operationId="deleteAdminOffer",
     *     tags={"Admin 課程管理"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="課程 ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="刪除成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="課程已刪除")
     *         )
     *     ),
     *     @OA\Response(response=403, description="非 admin 角色", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=404, description="課程不存在", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function deleteAdminOffer()
    {
    }

    /**
     * 取得所有預約（Admin）
     *
     * @OA\Get(
     *     path="/admin/bookings",
     *     summary="取得所有預約（Admin）",
     *     description="分頁回傳全平台預約列表",
     *     operationId="listAdminBookings",
     *     tags={"Admin 課程管理"},
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
     *     ),
     *     @OA\Response(response=403, description="非 admin 角色", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function listAdminBookings()
    {
    }

    /**
     * 標記預約完成（Admin）
     *
     * @OA\Put(
     *     path="/admin/bookings/{id}/complete",
     *     summary="標記預約完成（Admin）",
     *     operationId="completeBookingByAdmin",
     *     tags={"Admin 課程管理"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="預約 ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="標記成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="預約已完成"),
     *             @OA\Property(property="data", ref="#/components/schemas/Booking")
     *         )
     *     ),
     *     @OA\Response(response=403, description="非 admin 角色", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=422, description="狀態不允許完成", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function completeBookingByAdmin()
    {
    }

    /**
     * 取得所有評價（Admin）
     *
     * @OA\Get(
     *     path="/admin/reviews",
     *     summary="取得所有評價（Admin）",
     *     description="分頁回傳全平台評價列表，per_page 最大 100",
     *     operationId="listAdminReviews",
     *     tags={"Admin 課程管理"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="page", in="query", description="頁碼", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", description="每頁筆數（最大 100）", @OA\Schema(type="integer", default=20, maximum=100)),
     *     @OA\Response(
     *         response=200,
     *         description="取得成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Review")),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *         )
     *     ),
     *     @OA\Response(response=403, description="非 admin 角色", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function listAdminReviews()
    {
    }

    /**
     * 刪除評價（Admin）
     *
     * @OA\Delete(
     *     path="/admin/reviews/{id}",
     *     summary="刪除評價（Admin）",
     *     operationId="deleteAdminReview",
     *     tags={"Admin 課程管理"},
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
     *     @OA\Response(response=403, description="非 admin 角色", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=404, description="評價不存在", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function deleteAdminReview()
    {
    }
}
