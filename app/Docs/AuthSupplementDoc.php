<?php

namespace App\Docs;

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Google OAuth",
 *     description="Google OAuth 2.0 社群登入"
 * )
 */
class AuthSupplementDoc
{
    /**
     * 取得 Google OAuth 重定向 URL
     *
     * @OA\Get(
     *     path="/auth/google/redirect",
     *     summary="取得 Google OAuth 重定向 URL",
     *     description="回傳 Google OAuth 授權頁面的 redirect_url，前端應將使用者導向此 URL 以啟動 OAuth 流程",
     *     operationId="googleRedirect",
     *     tags={"Google OAuth"},
     *     @OA\Response(
     *         response=200,
     *         description="取得 redirect_url 成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="redirect_url", type="string", format="uri", example="https://accounts.google.com/o/oauth2/auth?client_id=...")
     *         )
     *     )
     * )
     */
    public function googleRedirect()
    {
    }

    /**
     * Google OAuth 回調
     *
     * @OA\Get(
     *     path="/auth/google/callback",
     *     summary="Google OAuth 回調",
     *     description="Google OAuth 授權完成後的回調端點，通常由瀏覽器自動呼叫。成功後回傳 Bearer token 與使用者資訊",
     *     operationId="googleCallback",
     *     tags={"Google OAuth"},
     *     @OA\Parameter(
     *         name="code",
     *         in="query",
     *         required=true,
     *         description="Google 授權碼",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="state",
     *         in="query",
     *         required=false,
     *         description="OAuth state 參數",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OAuth 登入成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="登入成功"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="token", type="string", example="1|abcdef1234567890"),
     *                 @OA\Property(property="token_type", type="string", example="Bearer"),
     *                 @OA\Property(
     *                     property="user",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="王小明"),
     *                     @OA\Property(property="email", type="string", example="user@gmail.com"),
     *                     @OA\Property(property="role", type="string", example="member"),
     *                     @OA\Property(property="is_active", type="boolean", example=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="OAuth 驗證失敗",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="OAuth 驗證失敗")
     *         )
     *     )
     * )
     */
    public function googleCallback()
    {
    }
}
