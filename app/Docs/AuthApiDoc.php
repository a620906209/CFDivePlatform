<?php

namespace App\Docs;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="CFDive Platform API",
 *     version="1.0.0",
 *     description="CFDive Platform API 文檔"
 * )
 * 
 * @OA\Server(
 *     url="/api",
 *     description="API 伺服器"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 * 
 * @OA\Tag(
 *     name="會員",
 *     description="會員相關操作"
 * )
 * @OA\Tag(
 *     name="服務提供者",
 *     description="服務提供者相關操作"
 * )
 * @OA\Tag(
 *     name="管理員",
 *     description="管理員相關操作"
 * )
 */
class AuthApiDoc
{
    /**
     * 會員註冊
     * 
     * @OA\Post(
     *     path="/register/member",
     *     summary="會員註冊",
     *     description="建立新的會員帳號",
     *     operationId="registerMember",
     *     tags={"會員"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "password_confirmation"},
     *             @OA\Property(property="name", type="string", example="王小明", description="使用者姓名"),
     *             @OA\Property(property="email", type="string", format="email", example="member@example.com", description="電子郵件"),
     *             @OA\Property(property="password", type="string", format="password", example="password123", description="密碼"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123", description="確認密碼"),
     *             @OA\Property(property="phone", type="string", example="0912345678", description="電話號碼"),
     *             @OA\Property(property="birthday", type="string", format="date", example="1990-01-01", description="生日"),
     *             @OA\Property(property="gender", type="string", enum={"male", "female", "other"}, example="male", description="性別")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="會員註冊成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="會員註冊成功"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="user", 
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="王小明"),
     *                     @OA\Property(property="email", type="string", example="user@example.com"),
     *                     @OA\Property(property="phone", type="string", example="0912345678"),
     *                     @OA\Property(property="role", type="string", example="member"),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="email_verified_at", type="string", example="2023-01-01T00:00:00.000000Z"),
     *                     @OA\Property(property="created_at", type="string", example="2023-01-01T00:00:00.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", example="2023-01-01T00:00:00.000000Z"),
     *                     @OA\Property(
     *                         property="memberProfile", 
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="user_id", type="integer", example=1),
     *                         @OA\Property(property="birthday", type="string", example="1990-01-01"),
     *                         @OA\Property(property="gender", type="string", example="male"),
     *                         @OA\Property(property="address", type="string", example="台北市信義區某街123號"),
     *                         @OA\Property(property="emergency_contact", type="string", example="王大明"),
     *                         @OA\Property(property="emergency_phone", type="string", example="0987654321"),
     *                         @OA\Property(property="created_at", type="string", example="2023-01-01T00:00:00.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", example="2023-01-01T00:00:00.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(property="token", type="string", example="1|abcdef1234567890"),
     *                 @OA\Property(property="token_type", type="string", example="Bearer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="驗證失敗",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="驗證失敗"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function registerMember()
    {
    }

    /**
     * 會員登入
     * 
     * @OA\Post(
     *     path="/login/member",
     *     summary="會員登入",
     *     description="會員帳號登入系統",
     *     operationId="loginMember",
     *     tags={"會員"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email", example="member@example.com", description="電子郵件"),
     *             @OA\Property(property="password", type="string", format="password", example="password123", description="密碼")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="登入成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="登入成功"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="user", 
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=11),
     *                     @OA\Property(property="name", type="string", example="測試會員"),
     *                     @OA\Property(property="email", type="string", example="test_member@example.com"),
     *                     @OA\Property(property="email_verified_at", type="string", nullable=true, example=null),
     *                     @OA\Property(property="phone", type="string", example="0912345678"),
     *                     @OA\Property(property="role", type="string", example="member"),
     *                     @OA\Property(property="is_active", type="integer", example=1),
     *                     @OA\Property(property="created_at", type="string", example="2025-05-08T17:19:22.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", example="2025-05-08T17:19:22.000000Z"),
     *                     @OA\Property(
     *                         property="member_profile", 
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=5),
     *                         @OA\Property(property="user_id", type="integer", example=11),
     *                         @OA\Property(property="birthday", type="string", example="1990-01-01T00:00:00.000000Z"),
     *                         @OA\Property(property="gender", type="string", example="male"),
     *                         @OA\Property(property="address", type="string", nullable=true, example=null),
     *                         @OA\Property(property="emergency_contact", type="string", nullable=true, example=null),
     *                         @OA\Property(property="emergency_phone", type="string", nullable=true, example=null),
     *                         @OA\Property(property="created_at", type="string", example="2025-05-08T17:19:22.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", example="2025-05-08T17:19:22.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(property="token", type="string", example="23|ZuJ6m0Ls4FSJITsOoqWtFtacazXyXwUtZtkcTb960e5a08"),
     *                 @OA\Property(property="token_type", type="string", example="Bearer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="身份驗證失敗",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="電子郵件或密碼錯誤")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="帳號已被停用",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="帳號已被停用")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="驗證失敗",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="驗證失敗"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function loginMember()
    {
    }

    /**
     * 會員登出
     * 
     * @OA\Post(
     *     path="/logout/member",
     *     summary="會員登出",
     *     description="會員登出系統並撤銷當前令牌",
     *     operationId="logoutMember",
     *     tags={"會員"},
     *     security={
     *         {"bearerAuth": {}}
     *     },
     *     @OA\Response(
     *         response=200,
     *         description="登出成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="會員登出成功")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="無權限存取",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="無權限存取")
     *         )
     *     )
     * )
     */
    public function logoutMember()
    {
    }

    /**
     * 取得會員個人資料
     * 
     * @OA\Get(
     *     path="/profile/member",
     *     summary="取得會員個人資料",
     *     description="取得當前登入會員的個人資料",
     *     operationId="memberProfile",
     *     tags={"會員"},
     *     security={
     *         {"bearerAuth": {}}
     *     },
     *     @OA\Response(
     *         response=200,
     *         description="取得資料成功",
     *         @OA\JsonContent(
    *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="user", 
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="王小明"),
     *                     @OA\Property(property="email", type="string", example="user@example.com"),
     *                     @OA\Property(property="phone", type="string", example="0912345678"),
     *                     @OA\Property(property="role", type="string", example="member"),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="email_verified_at", type="string", example="2023-01-01T00:00:00.000000Z"),
     *                     @OA\Property(property="created_at", type="string", example="2023-01-01T00:00:00.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", example="2023-01-01T00:00:00.000000Z"),
     *                     @OA\Property(
     *                         property="memberProfile", 
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="user_id", type="integer", example=1),
     *                         @OA\Property(property="birthday", type="string", example="1990-01-01"),
     *                         @OA\Property(property="gender", type="string", example="male"),
     *                         @OA\Property(property="address", type="string", example="台北市信義區某街123號"),
     *                         @OA\Property(property="emergency_contact", type="string", example="王大明"),
     *                         @OA\Property(property="emergency_phone", type="string", example="0987654321"),
     *                         @OA\Property(property="created_at", type="string", example="2023-01-01T00:00:00.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", example="2023-01-01T00:00:00.000000Z")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="無權限存取",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="無權限存取")
     *         )
     *     )
     * )
     */
    public function memberProfile()
    {
    }

    /**
     * 更新會員個人資料
     * 
     * @OA\Put(
     *     path="/profile/member",
     *     summary="更新會員個人資料",
     *     description="更新當前登入會員的個人資料",
     *     operationId="updateMemberProfile",
     *     tags={"會員"},
     *     security={
     *         {"bearerAuth": {}}
     *     },
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="王大明", description="使用者姓名"),
     *             @OA\Property(property="email", type="string", format="email", example="newmail@example.com", description="電子郵件"),
     *             @OA\Property(property="phone", type="string", example="0987654321", description="電話號碼")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="更新成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="會員資料已更新"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="user", 
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="王大明"),
     *                     @OA\Property(property="email", type="string", example="user@example.com"),
     *                     @OA\Property(property="phone", type="string", example="0912345678"),
     *                     @OA\Property(property="role", type="string", example="member"),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="email_verified_at", type="string", example="2023-01-01T00:00:00.000000Z"),
     *                     @OA\Property(property="created_at", type="string", example="2023-01-01T00:00:00.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", example="2023-01-01T00:00:00.000000Z"),
     *                     @OA\Property(
     *                         property="memberProfile", 
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="user_id", type="integer", example=1),
     *                         @OA\Property(property="birthday", type="string", example="1990-01-01"),
     *                         @OA\Property(property="gender", type="string", example="male"),
     *                         @OA\Property(property="address", type="string", example="台北市信義區某街123號"),
     *                         @OA\Property(property="emergency_contact", type="string", example="王大明"),
     *                         @OA\Property(property="emergency_phone", type="string", example="0987654321"),
     *                         @OA\Property(property="created_at", type="string", example="2023-01-01T00:00:00.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", example="2023-01-01T00:00:00.000000Z")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="無權限存取",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="無權限存取")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="驗證失敗",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="驗證失敗"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function updateMemberProfile()
    {
    }

    /**
     * 修改會員密碼
     * 
     * @OA\Post(
     *     path="/password/member",
     *     summary="修改會員密碼",
     *     description="修改當前登入會員的密碼",
     *     operationId="changeMemberPassword",
     *     tags={"會員"},
     *     security={
     *         {"bearerAuth": {}}
     *     },
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"current_password", "password", "password_confirmation"},
     *             @OA\Property(property="current_password", type="string", format="password", example="oldpassword", description="目前密碼"),
     *             @OA\Property(property="password", type="string", format="password", example="newpassword", description="新密碼"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="newpassword", description="確認新密碼")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="修改成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="密碼修改成功")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="目前密碼錯誤",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="目前密碼錯誤")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="無權限存取",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="無權限存取")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="驗證失敗",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="驗證失敗"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function changeMemberPassword()
    {
    }

    /**
     * 服務提供者註冊
     * 
     * @OA\Post(
     *     path="/register/provider",
     *     summary="服務提供者註冊",
     *     description="建立新的服務提供者帳號",
     *     operationId="registerProvider",
     *     tags={"服務提供者"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "password_confirmation"},
     *             @OA\Property(property="name", type="string", example="林教練", description="使用者姓名"),
     *             @OA\Property(property="email", type="string", format="email", example="coach@example.com", description="電子郵件"),
     *             @OA\Property(property="password", type="string", format="password", example="password123", description="密碼"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123", description="確認密碼"),
     *             @OA\Property(property="phone", type="string", example="0912345678", description="電話號碼"),
     *             @OA\Property(property="business_name", type="string", example="藍海潛水中心", description="業者名稱"),
     *             @OA\Property(property="description", type="string", example="專業潛水中心，提供各種潛水服務", description="業者描述"),
     *             @OA\Property(property="contact_person", type="string", example="羅大師", description="聯絡人"),
     *             @OA\Property(property="contact_phone", type="string", example="0912345678", description="聯絡電話")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="服務提供者註冊成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="服務提供者註冊成功"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="user", type="object", ref="#/components/schemas/User"),
     *                 @OA\Property(property="token", type="string", example="1|abcdef1234567890"),
     *                 @OA\Property(property="token_type", type="string", example="Bearer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="驗證失敗",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="驗證失敗"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function registerProvider()
    {
    }

    /**
     * 服務提供者登入
     * 
     * @OA\Post(
     *     path="/login/provider",
     *     summary="服務提供者登入",
     *     description="服務提供者帳號登入系統",
     *     operationId="loginProvider",
     *     tags={"服務提供者"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email", example="coach@example.com", description="電子郵件"),
     *             @OA\Property(property="password", type="string", format="password", example="password123", description="密碼")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="登入成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=11),
     *                 @OA\Property(property="name", type="string", example="測試服務提供者"),
     *                 @OA\Property(property="email", type="string", example="test_provider@example.com"),
     *                 @OA\Property(property="email_verified_at", type="string", nullable=true, example=null),
     *                 @OA\Property(property="phone", type="string", example="0912345678"),
     *                 @OA\Property(property="role", type="string", example="provider"),
     *                 @OA\Property(property="is_active", type="integer", example=1),
     *                 @OA\Property(property="created_at", type="string", example="2025-05-08T17:19:22.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", example="2025-05-08T17:19:22.000000Z"),
     *                 @OA\Property(
     *                     property="providerProfile", 
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=5),
     *                     @OA\Property(property="user_id", type="integer", example=11),
     *                     @OA\Property(property="business_name", type="string", example="藍海潛水中心"),
     *                     @OA\Property(property="description", type="string", example="專業潛水中心，提供各種潛水服務"),
     *                     @OA\Property(property="contact_person", type="string", example="王大師"),
     *                     @OA\Property(property="contact_phone", type="string", example="0912345678"),
     *                     @OA\Property(property="contact_email", type="string", example="contact@example.com"),
     *                     @OA\Property(property="address", type="string", example="台灣屏東縣恆春鎮XXX路123號"),
     *                     @OA\Property(property="created_at", type="string", example="2025-05-08T17:19:22.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", example="2025-05-08T17:19:22.000000Z")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="身份驗證失敗",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="電子郵件或密碼錯誤")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="帳號已被停用",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="帳號已被停用")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="驗證失敗",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="驗證失敗"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function loginProvider()
    {
    }

    /**
     * 服務提供者登出
     * 
     * @OA\Post(
     *     path="/logout/provider",
     *     summary="服務提供者登出",
     *     description="服務提供者登出系統並撤銷當前令牌",
     *     operationId="logoutProvider",
     *     tags={"服務提供者"},
     *     security={
     *         {"bearerAuth": {}}
     *     },
     *     @OA\Response(
     *         response=200,
     *         description="登出成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="服務提供者登出成功")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="無權限存取",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="無權限存取")
     *         )
     *     )
     * )
     */
    public function logoutProvider()
    {
    }

    /**
     * 取得服務提供者資料
     * 
     * @OA\Get(
     *     path="/profile/provider",
     *     summary="取得服務提供者資料",
     *     description="取得當前登入服務提供者的資料",
     *     operationId="providerProfile",
     *     tags={"服務提供者"},
     *     security={
     *         {"bearerAuth": {}}
     *     },
     *     @OA\Response(
     *         response=200,
     *         description="取得資料成功",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="user", 
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="測試服務提供者"),
     *                     @OA\Property(property="email", type="string", example="provider@example.com"),
     *                     @OA\Property(property="phone", type="string", example="0912345678"),
     *                     @OA\Property(property="role", type="string", example="provider"),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="email_verified_at", type="string", example="2023-01-01T00:00:00.000000Z"),
     *                     @OA\Property(property="created_at", type="string", example="2023-01-01T00:00:00.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", example="2023-01-01T00:00:00.000000Z"),
     *                     @OA\Property(
     *                         property="providerProfile", 
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="user_id", type="integer", example=1),
     *                         @OA\Property(property="business_name", type="string", example="藍海潛水中心"),
     *                         @OA\Property(property="description", type="string", example="專業潛水中心，提供各種潛水服務"),
     *                         @OA\Property(property="contact_person", type="string", example="王大師"),
     *                         @OA\Property(property="contact_phone", type="string", example="0912345678"),
     *                         @OA\Property(property="contact_email", type="string", example="contact@example.com"),
     *                         @OA\Property(property="address", type="string", example="台灣屏東縣恆春鎮XXX路123號"),
     *                         @OA\Property(property="business_hours", type="string", example="週一至週五 09:00-18:00"),
     *                         @OA\Property(property="created_at", type="string", example="2023-01-01T00:00:00.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", example="2023-01-01T00:00:00.000000Z")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="無權限存取",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="無權限存取")
     *         )
     *     )
     * )
     */
    public function providerProfile()
    {
    }

    /**
     * 更新服務提供者資料
     * 
     * @OA\Put(
     *     path="/profile/provider",
     *     summary="更新服務提供者資料",
     *     description="更新當前登入服務提供者的資料",
     *     operationId="updateProviderProfile",
     *     tags={"服務提供者"},
     *     security={
     *         {"bearerAuth": {}}
     *     },
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="藍海潛水中心", description="使用者姓名"),
     *             @OA\Property(property="email", type="string", format="email", example="newprovider@example.com", description="電子郵件"),
     *             @OA\Property(property="phone", type="string", example="0987654321", description="電話號碼"),
     *             @OA\Property(property="business_name", type="string", example="藍海潛水中心新分院", description="業者名稱"),
     *             @OA\Property(property="description", type="string", example="專業潛水中心，提供各種高品質潛水課程與行程", description="業者描述"),
     *             @OA\Property(property="contact_person", type="string", example="王大師", description="聯絡人"),
     *             @OA\Property(property="address", type="string", example="台灣屏東縣恆春鎮XXX路456號", description="營業地址")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="更新成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="服務提供者資料已更新"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="user", 
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="藍海潛水中心"),
     *                     @OA\Property(property="email", type="string", example="provider@example.com"),
     *                     @OA\Property(property="phone", type="string", example="0912345678"),
     *                     @OA\Property(property="role", type="string", example="provider"),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="email_verified_at", type="string", example="2023-01-01T00:00:00.000000Z"),
     *                     @OA\Property(property="created_at", type="string", example="2023-01-01T00:00:00.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", example="2023-01-01T00:00:00.000000Z"),
     *                     @OA\Property(
     *                         property="providerProfile", 
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="user_id", type="integer", example=1),
     *                         @OA\Property(property="business_name", type="string", example="藍海潛水中心"),
     *                         @OA\Property(property="description", type="string", example="專業潛水中心，提供各種潛水服務"),
     *                         @OA\Property(property="contact_person", type="string", example="王大師"),
     *                         @OA\Property(property="contact_phone", type="string", example="0912345678"),
     *                         @OA\Property(property="contact_email", type="string", example="contact@example.com"),
     *                         @OA\Property(property="address", type="string", example="台灣屏東縣恆春鎮XXX路123號"),
     *                         @OA\Property(property="business_hours", type="string", example="週一至週五 09:00-18:00"),
     *                         @OA\Property(property="created_at", type="string", example="2023-01-01T00:00:00.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", example="2023-01-01T00:00:00.000000Z")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="無權限存取",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="無權限存取")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="驗證失敗",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="驗證失敗"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function updateCoachProfile()
    {
    }

    /**
     * 修改服務提供者密碼
     * 
     * @OA\Post(
     *     path="/password/provider",
     *     summary="修改服務提供者密碼",
     *     description="修改當前登入服務提供者的密碼",
     *     operationId="changeProviderPassword",
     *     tags={"服務提供者"},
     *     security={
     *         {"bearerAuth": {}}
     *     },
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"current_password", "password", "password_confirmation"},
     *             @OA\Property(property="current_password", type="string", format="password", example="oldpassword", description="目前密碼"),
     *             @OA\Property(property="password", type="string", format="password", example="newpassword", description="新密碼"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="newpassword", description="確認新密碼")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="修改成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="密碼修改成功")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="目前密碼錯誤",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="目前密碼錯誤")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="無權限存取",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="無權限存取")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="驗證失敗",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="驗證失敗"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function changeProviderPassword()
    {
    }

    /**
     * 管理員註冊
     * 
     * @OA\Post(
     *     path="/register/admin",
     *     summary="管理員註冊",
     *     description="建立新的管理員帳號",
     *     operationId="registerAdmin",
     *     tags={"管理員"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "password_confirmation"},
     *             @OA\Property(property="name", type="string", example="張管理", description="使用者姓名"),
     *             @OA\Property(property="email", type="string", format="email", example="admin@example.com", description="電子郵件"),
     *             @OA\Property(property="password", type="string", format="password", example="password123", description="密碼"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123", description="確認密碼"),
     *             @OA\Property(property="phone", type="string", example="0912345678", description="電話號碼"),
     *             @OA\Property(property="position", type="string", example="系統管理員", description="職位"),
     *             @OA\Property(property="department", type="string", example="IT部門", description="部門")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="管理員註冊成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="管理員註冊成功"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="user", type="object", ref="#/components/schemas/User"),
     *                 @OA\Property(property="token", type="string", example="1|abcdef1234567890"),
     *                 @OA\Property(property="token_type", type="string", example="Bearer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="驗證失敗",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="驗證失敗"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function registerAdmin()
    {
    }

    /**
     * 管理員登入
     * 
     * @OA\Post(
     *     path="/login/admin",
     *     summary="管理員登入",
     *     description="管理員帳號登入系統",
     *     operationId="loginAdmin",
     *     tags={"管理員"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email", example="admin@example.com", description="電子郵件"),
     *             @OA\Property(property="password", type="string", format="password", example="password123", description="密碼")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="登入成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=11),
     *                 @OA\Property(property="name", type="string", example="測試管理員"),
     *                 @OA\Property(property="email", type="string", example="test_admin@example.com"),
     *                 @OA\Property(property="email_verified_at", type="string", nullable=true, example=null),
     *                 @OA\Property(property="phone", type="string", example="0912345678"),
     *                 @OA\Property(property="role", type="string", example="admin"),
     *                 @OA\Property(property="is_active", type="integer", example=1),
     *                 @OA\Property(property="created_at", type="string", example="2025-05-08T17:19:22.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", example="2025-05-08T17:19:22.000000Z"),
     *                 @OA\Property(
     *                     property="adminProfile", 
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=5),
     *                     @OA\Property(property="user_id", type="integer", example=11),
     *                     @OA\Property(property="birthday", type="string", example="1990-01-01T00:00:00.000000Z"),
     *                     @OA\Property(property="gender", type="string", example="male"),
     *                     @OA\Property(property="address", type="string", nullable=true, example=null),
     *                     @OA\Property(property="emergency_contact", type="string", nullable=true, example=null),
     *                     @OA\Property(property="emergency_phone", type="string", nullable=true, example=null),
     *                     @OA\Property(property="created_at", type="string", example="2025-05-08T17:19:22.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", example="2025-05-08T17:19:22.000000Z")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="身份驗證失敗",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="電子郵件或密碼錯誤")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="帳號已被停用",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="帳號已被停用")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="驗證失敗",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="驗證失敗"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function loginAdmin()
    {
    }

    /**
     * 管理員登出
     * 
     * @OA\Post(
     *     path="/logout/admin",
     *     summary="管理員登出",
     *     description="管理員登出系統並撤銷當前令牌",
     *     operationId="logoutAdmin",
     *     tags={"管理員"},
     *     security={
     *         {"bearerAuth": {}}
     *     },
     *     @OA\Response(
     *         response=200,
     *         description="登出成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="管理員登出成功")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="無權限存取",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="無權限存取")
     *         )
     *     )
     * )
     */
    public function logoutAdmin()
    {
    }

    /**
     * 取得管理員個人資料
     * 
     * @OA\Get(
     *     path="/profile/admin",
     *     summary="取得管理員個人資料",
     *     description="取得當前登入管理員的個人資料",
     *     operationId="adminProfile",
     *     tags={"管理員"},
     *     security={
     *         {"bearerAuth": {}}
     *     },
     *     @OA\Response(
     *         response=200,
     *         description="取得資料成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 ref="#/components/schemas/User"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="無權限存取",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="無權限存取")
     *         )
     *     )
     * )
     */
    public function adminProfile()
    {
    }

    /**
     * 更新管理員個人資料
     * 
     * @OA\Put(
     *     path="/profile/admin",
     *     summary="更新管理員個人資料",
     *     description="更新當前登入管理員的個人資料",
     *     operationId="updateAdminProfile",
     *     tags={"管理員"},
     *     security={
     *         {"bearerAuth": {}}
     *     },
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="張總管", description="使用者姓名"),
     *             @OA\Property(property="email", type="string", format="email", example="newadmin@example.com", description="電子郵件"),
     *             @OA\Property(property="phone", type="string", example="0987654321", description="電話號碼"),
     *             @OA\Property(property="position", type="string", example="資深系統管理員", description="職位"),
     *             @OA\Property(property="department", type="string", example="系統維護部", description="部門")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="更新成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="管理員資料已更新"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 ref="#/components/schemas/User"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="無權限存取",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="無權限存取")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="驗證失敗",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="驗證失敗"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function updateAdminProfile()
    {
    }

    /**
     * 修改管理員密碼
     * 
     * @OA\Post(
     *     path="/password/admin",
     *     summary="修改管理員密碼",
     *     description="修改當前登入管理員的密碼",
     *     operationId="changeAdminPassword",
     *     tags={"管理員"},
     *     security={
     *         {"bearerAuth": {}}
     *     },
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"current_password", "password", "password_confirmation"},
     *             @OA\Property(property="current_password", type="string", format="password", example="oldpassword", description="目前密碼"),
     *             @OA\Property(property="password", type="string", format="password", example="newpassword", description="新密碼"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="newpassword", description="確認新密碼")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="修改成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="密碼修改成功")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="目前密碼錯誤",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="目前密碼錯誤")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="無權限存取",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="無權限存取")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="驗證失敗",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="驗證失敗"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function changeAdminPassword()
    {
    }
}
