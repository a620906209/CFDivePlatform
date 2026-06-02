<?php

namespace App\Docs;

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="教練課程",
 *     description="服務提供者的課程管理"
 * )
 * @OA\Tag(
 *     name="教練圖片",
 *     description="服務提供者的課程圖片管理"
 * )
 * @OA\Tag(
 *     name="教練時段",
 *     description="服務提供者的課程時段管理"
 * )
 * @OA\Tag(
 *     name="教練預約",
 *     description="服務提供者的預約管理"
 * )
 */
class ProviderApiDoc
{
    // -----------------------------------------------------------------------
    // Provider Offers CRUD
    // -----------------------------------------------------------------------

    /**
     * 取得自己的課程列表
     *
     * @OA\Get(
     *     path="/provider/offers",
     *     summary="取得自己的課程列表",
     *     description="回傳當前服務提供者的所有課程（分頁）",
     *     operationId="listProviderOffers",
     *     tags={"教練課程"},
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
     *     )
     * )
     */
    public function listProviderOffers()
    {
    }

    /**
     * 建立課程
     *
     * @OA\Post(
     *     path="/provider/offers",
     *     summary="建立課程",
     *     description="服務提供者建立新的潛水課程",
     *     operationId="createProviderOffer",
     *     tags={"教練課程"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title","description","region","price","max_participants"},
     *             @OA\Property(property="title", type="string", example="基礎開放水域課程"),
     *             @OA\Property(property="description", type="string", example="適合初學者的 OWD 課程"),
     *             @OA\Property(property="region", type="string", example="墾丁"),
     *             @OA\Property(property="price", type="number", format="float", example=8500),
     *             @OA\Property(property="max_participants", type="integer", example=6),
     *             @OA\Property(property="tags", type="array", @OA\Items(type="string"), example={"初學","OWD"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="課程建立成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="課程建立成功"),
     *             @OA\Property(property="data", ref="#/components/schemas/DivingOffer")
     *         )
     *     ),
     *     @OA\Response(response=422, description="驗證失敗", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=403, description="無權限", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function createProviderOffer()
    {
    }

    /**
     * 取得單一課程（Provider 視角）
     *
     * @OA\Get(
     *     path="/provider/offers/{id}",
     *     summary="取得單一課程（Provider 視角）",
     *     description="取得自己的指定課程，非本人課程回傳 403",
     *     operationId="getProviderOffer",
     *     tags={"教練課程"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="課程 ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="取得成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/DivingOffer")
     *         )
     *     ),
     *     @OA\Response(response=403, description="非本人課程", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=404, description="課程不存在", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function getProviderOffer()
    {
    }

    /**
     * 更新課程
     *
     * @OA\Put(
     *     path="/provider/offers/{id}",
     *     summary="更新課程",
     *     operationId="updateProviderOffer",
     *     tags={"教練課程"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="課程 ID", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", example="進階開放水域課程"),
     *             @OA\Property(property="description", type="string", example="AOWD 課程"),
     *             @OA\Property(property="region", type="string", example="小琉球"),
     *             @OA\Property(property="price", type="number", format="float", example=12000),
     *             @OA\Property(property="max_participants", type="integer", example=4),
     *             @OA\Property(property="tags", type="array", @OA\Items(type="string"), example={"進階","AOWD"}),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="更新成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/DivingOffer")
     *         )
     *     ),
     *     @OA\Response(response=403, description="非本人課程", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=422, description="驗證失敗", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function updateProviderOffer()
    {
    }

    /**
     * 刪除課程
     *
     * @OA\Delete(
     *     path="/provider/offers/{id}",
     *     summary="刪除課程",
     *     operationId="deleteProviderOffer",
     *     tags={"教練課程"},
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
     *     @OA\Response(response=403, description="非本人課程", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=404, description="課程不存在", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function deleteProviderOffer()
    {
    }

    // -----------------------------------------------------------------------
    // Provider Offer Images
    // -----------------------------------------------------------------------

    /**
     * 上傳封面圖片
     *
     * @OA\Post(
     *     path="/provider/offers/{id}/cover",
     *     summary="上傳封面圖片",
     *     description="上傳課程封面圖片（multipart/form-data）",
     *     operationId="uploadOfferCover",
     *     tags={"教練圖片"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="課程 ID", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"cover_image"},
     *                 @OA\Property(property="cover_image", type="string", format="binary", description="封面圖片（jpg/png，最大 5MB）")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="上傳成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="cover_image_url", type="string", example="https://example.com/covers/1.jpg")
     *         )
     *     ),
     *     @OA\Response(response=422, description="圖片驗證失敗", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=403, description="非本人課程", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function uploadOfferCover()
    {
    }

    /**
     * 刪除封面圖片
     *
     * @OA\Delete(
     *     path="/provider/offers/{id}/cover",
     *     summary="刪除封面圖片",
     *     operationId="deleteOfferCover",
     *     tags={"教練圖片"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="課程 ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="刪除成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="封面圖片已刪除")
     *         )
     *     ),
     *     @OA\Response(response=403, description="非本人課程", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function deleteOfferCover()
    {
    }

    /**
     * 上傳相簿圖片
     *
     * @OA\Post(
     *     path="/provider/offers/{id}/images",
     *     summary="上傳相簿圖片",
     *     description="新增課程相簿圖片（multipart/form-data，可多張）",
     *     operationId="uploadOfferImages",
     *     tags={"教練圖片"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="課程 ID", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"images[]"},
     *                 @OA\Property(
     *                     property="images[]",
     *                     type="array",
     *                     @OA\Items(type="string", format="binary"),
     *                     description="相簿圖片（每張最大 5MB）"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="上傳成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="images", type="array", @OA\Items(type="string"), example={"https://example.com/img/1.jpg"})
     *         )
     *     ),
     *     @OA\Response(response=422, description="圖片驗證失敗", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=403, description="非本人課程", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function uploadOfferImages()
    {
    }

    /**
     * 刪除相簿圖片
     *
     * @OA\Delete(
     *     path="/provider/images/{id}",
     *     summary="刪除相簿圖片",
     *     operationId="deleteOfferImage",
     *     tags={"教練圖片"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="圖片 ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="刪除成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="圖片已刪除")
     *         )
     *     ),
     *     @OA\Response(response=403, description="非本人圖片", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=404, description="圖片不存在", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function deleteOfferImage()
    {
    }

    // -----------------------------------------------------------------------
    // Provider Schedules
    // -----------------------------------------------------------------------

    /**
     * 取得時段列表
     *
     * @OA\Get(
     *     path="/provider/schedules",
     *     summary="取得時段列表",
     *     description="回傳服務提供者的所有時段，可依 offer_id 篩選",
     *     operationId="listProviderSchedules",
     *     tags={"教練時段"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="offer_id", in="query", required=false, description="課程 ID 篩選", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="取得成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/CourseSchedule"))
     *         )
     *     )
     * )
     */
    public function listProviderSchedules()
    {
    }

    /**
     * 建立時段
     *
     * @OA\Post(
     *     path="/provider/schedules",
     *     summary="建立時段",
     *     operationId="createProviderSchedule",
     *     tags={"教練時段"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"offer_id","start_date","end_date","available_slots"},
     *             @OA\Property(property="offer_id", type="integer", example=1),
     *             @OA\Property(property="start_date", type="string", format="date", example="2025-07-01"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2025-07-03"),
     *             @OA\Property(property="available_slots", type="integer", example=4)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="時段建立成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/CourseSchedule")
     *         )
     *     ),
     *     @OA\Response(response=422, description="驗證失敗", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=403, description="非本人課程", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function createProviderSchedule()
    {
    }

    /**
     * 更新時段
     *
     * @OA\Put(
     *     path="/provider/schedules/{id}",
     *     summary="更新時段",
     *     operationId="updateProviderSchedule",
     *     tags={"教練時段"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="時段 ID", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="start_date", type="string", format="date", example="2025-07-05"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2025-07-07"),
     *             @OA\Property(property="available_slots", type="integer", example=6),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="更新成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/CourseSchedule")
     *         )
     *     ),
     *     @OA\Response(response=403, description="非本人時段", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=404, description="時段不存在", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function updateProviderSchedule()
    {
    }

    /**
     * 刪除時段
     *
     * @OA\Delete(
     *     path="/provider/schedules/{id}",
     *     summary="刪除時段",
     *     operationId="deleteProviderSchedule",
     *     tags={"教練時段"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="時段 ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="刪除成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="時段已刪除")
     *         )
     *     ),
     *     @OA\Response(response=403, description="非本人時段", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=404, description="時段不存在", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function deleteProviderSchedule()
    {
    }

    // -----------------------------------------------------------------------
    // Provider Bookings Management
    // -----------------------------------------------------------------------

    /**
     * 取得收到的預約列表
     *
     * @OA\Get(
     *     path="/provider/bookings",
     *     summary="取得收到的預約列表",
     *     description="分頁回傳服務提供者收到的所有預約",
     *     operationId="listProviderBookings",
     *     tags={"教練預約"},
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
    public function listProviderBookings()
    {
    }

    /**
     * 確認預約
     *
     * @OA\Put(
     *     path="/provider/bookings/{id}/confirm",
     *     summary="確認預約",
     *     operationId="confirmBooking",
     *     tags={"教練預約"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="預約 ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="確認成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="預約已確認"),
     *             @OA\Property(property="data", ref="#/components/schemas/Booking")
     *         )
     *     ),
     *     @OA\Response(response=403, description="非本人課程的預約", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=422, description="狀態不允許確認", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function confirmBooking()
    {
    }

    /**
     * 拒絕預約
     *
     * @OA\Put(
     *     path="/provider/bookings/{id}/reject",
     *     summary="拒絕預約",
     *     operationId="rejectBooking",
     *     tags={"教練預約"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="預約 ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="拒絕成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="預約已拒絕"),
     *             @OA\Property(property="data", ref="#/components/schemas/Booking")
     *         )
     *     ),
     *     @OA\Response(response=403, description="非本人課程的預約", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=422, description="狀態不允許拒絕", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function rejectBooking()
    {
    }

    /**
     * 標記預約完成
     *
     * @OA\Put(
     *     path="/provider/bookings/{id}/complete",
     *     summary="標記預約完成",
     *     operationId="completeBookingByProvider",
     *     tags={"教練預約"},
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
     *     @OA\Response(response=403, description="非本人課程的預約", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=422, description="狀態不允許完成", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function completeBookingByProvider()
    {
    }

    /**
     * 取消預約（Provider）
     *
     * @OA\Put(
     *     path="/provider/bookings/{id}/cancel",
     *     summary="取消預約（Provider）",
     *     operationId="cancelBookingByProvider",
     *     tags={"教練預約"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="預約 ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="取消成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="預約已取消"),
     *             @OA\Property(property="data", ref="#/components/schemas/Booking")
     *         )
     *     ),
     *     @OA\Response(response=403, description="非本人課程的預約", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=422, description="狀態不允許取消", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function cancelBookingByProvider()
    {
    }
}
