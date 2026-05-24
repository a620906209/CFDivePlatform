<?php

namespace App\Docs;

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="公開課程",
 *     description="無需認證的公開潛水課程查詢端點"
 * )
 *
 * -----------------------------------------------------------------------
 * 共用 Schema 定義
 * -----------------------------------------------------------------------
 *
 * @OA\Schema(
 *     schema="PaginationMeta",
 *     type="object",
 *     @OA\Property(property="current_page", type="integer", example=1),
 *     @OA\Property(property="last_page", type="integer", example=5),
 *     @OA\Property(property="per_page", type="integer", example=15),
 *     @OA\Property(property="total", type="integer", example=72)
 * )
 *
 * @OA\Schema(
 *     schema="ApiErrorResponse",
 *     type="object",
 *     @OA\Property(property="status", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", example="操作失敗"),
 *     @OA\Property(property="errors", type="object", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="DivingOffer",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="provider_id", type="integer", example=3),
 *     @OA\Property(property="title", type="string", example="基礎開放水域課程"),
 *     @OA\Property(property="description", type="string", example="適合初學者的 OWD 課程"),
 *     @OA\Property(property="region", type="string", example="墾丁"),
 *     @OA\Property(property="price", type="number", format="float", example=8500),
 *     @OA\Property(property="max_participants", type="integer", example=6),
 *     @OA\Property(property="tags", type="array", @OA\Items(type="string"), example={"初學","OWD"}),
 *     @OA\Property(property="cover_image_url", type="string", nullable=true, example="https://example.com/image.jpg"),
 *     @OA\Property(property="images", type="array", @OA\Items(type="string"), example={}),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", example="2025-01-01T00:00:00.000000Z"),
 *     @OA\Property(property="updated_at", type="string", example="2025-01-01T00:00:00.000000Z")
 * )
 *
 * @OA\Schema(
 *     schema="Review",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="offer_id", type="integer", example=1),
 *     @OA\Property(property="rating", type="integer", minimum=1, maximum=5, example=4),
 *     @OA\Property(property="comment", type="string", nullable=true, example="課程非常棒！"),
 *     @OA\Property(property="is_anonymous", type="boolean", example=false),
 *     @OA\Property(property="helpful_count", type="integer", example=3),
 *     @OA\Property(property="has_voted", type="boolean", example=false),
 *     @OA\Property(property="created_at", type="string", example="2025-01-01T00:00:00.000000Z")
 * )
 *
 * @OA\Schema(
 *     schema="CourseSchedule",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="offer_id", type="integer", example=1),
 *     @OA\Property(property="start_date", type="string", format="date", example="2025-07-01"),
 *     @OA\Property(property="end_date", type="string", format="date", example="2025-07-03"),
 *     @OA\Property(property="available_slots", type="integer", example=4),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", example="2025-01-01T00:00:00.000000Z")
 * )
 *
 * @OA\Schema(
 *     schema="Booking",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="member_id", type="integer", example=5),
 *     @OA\Property(property="offer_id", type="integer", example=1),
 *     @OA\Property(property="schedule_id", type="integer", example=2),
 *     @OA\Property(property="status", type="string", enum={"pending","confirmed","rejected","completed","cancelled"}, example="pending"),
 *     @OA\Property(property="participants", type="integer", example=2),
 *     @OA\Property(property="note", type="string", nullable=true, example=""),
 *     @OA\Property(property="created_at", type="string", example="2025-01-01T00:00:00.000000Z"),
 *     @OA\Property(property="updated_at", type="string", example="2025-01-01T00:00:00.000000Z")
 * )
 */
class PublicApiDoc
{
    /**
     * 查詢潛水課程列表
     *
     * @OA\Get(
     *     path="/diving-offers",
     *     summary="查詢潛水課程列表",
     *     description="支援關鍵字、地區、標籤篩選，回傳分頁結果",
     *     operationId="listDivingOffers",
     *     tags={"公開課程"},
     *     @OA\Parameter(name="q", in="query", description="關鍵字搜尋", @OA\Schema(type="string")),
     *     @OA\Parameter(name="region", in="query", description="地區篩選", @OA\Schema(type="string", example="墾丁")),
     *     @OA\Parameter(name="tag", in="query", description="標籤篩選", @OA\Schema(type="string", example="OWD")),
     *     @OA\Parameter(name="per_page", in="query", description="每頁筆數（預設 15，最大 50）", @OA\Schema(type="integer", default=15, maximum=50)),
     *     @OA\Parameter(name="page", in="query", description="頁碼", @OA\Schema(type="integer", default=1)),
     *     @OA\Response(
     *         response=200,
     *         description="查詢成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/DivingOffer")
     *             ),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *         )
     *     )
     * )
     */
    public function listDivingOffers()
    {
    }

    /**
     * 取得單一潛水課程
     *
     * @OA\Get(
     *     path="/diving-offers/{id}",
     *     summary="取得單一潛水課程",
     *     description="回傳課程詳情，包含封面圖片與相簿",
     *     operationId="getDivingOffer",
     *     tags={"公開課程"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="課程 ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="取得成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/DivingOffer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="課程不存在",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function getDivingOffer()
    {
    }

    /**
     * 取得課程評價列表
     *
     * @OA\Get(
     *     path="/diving-offers/{id}/reviews",
     *     summary="取得課程評價列表",
     *     description="回傳評分摘要、分頁評價列表與分頁 meta",
     *     operationId="listOfferReviews",
     *     tags={"公開課程"},
     *     @OA\Parameter(name="id", in="path", required=true, description="課程 ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="sort", in="query", description="排序方式（newest / helpful）", @OA\Schema(type="string", enum={"newest","helpful"}, default="newest")),
     *     @OA\Parameter(name="page", in="query", description="頁碼", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", description="每頁筆數（預設 15，最大 50）", @OA\Schema(type="integer", default=15, maximum=50)),
     *     @OA\Response(
     *         response=200,
     *         description="取得成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="summary",
     *                     type="object",
     *                     @OA\Property(property="average_rating", type="number", format="float", example=4.2),
     *                     @OA\Property(property="total_reviews", type="integer", example=24),
     *                     @OA\Property(property="distribution", type="object",
     *                         @OA\Property(property="1", type="integer", example=1),
     *                         @OA\Property(property="2", type="integer", example=2),
     *                         @OA\Property(property="3", type="integer", example=3),
     *                         @OA\Property(property="4", type="integer", example=8),
     *                         @OA\Property(property="5", type="integer", example=10)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="reviews",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/Review")
     *                 ),
     *                 @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="課程不存在",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function listOfferReviews()
    {
    }

    /**
     * 取得課程時段列表
     *
     * @OA\Get(
     *     path="/diving-offers/{id}/schedules",
     *     summary="取得課程時段列表",
     *     description="回傳指定課程的所有可用時段",
     *     operationId="listOfferSchedules",
     *     tags={"公開課程"},
     *     @OA\Parameter(name="id", in="path", required=true, description="課程 ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="取得成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/CourseSchedule")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="課程不存在",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function listOfferSchedules()
    {
    }
}
