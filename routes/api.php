<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;

// 這裡可以定義 API 路由，例如：
Route::get('/ping', function () {
    return response()->json(['message' => 'pong']);
});

// 你可以在這裡繼續新增 API 路由
Route::post('/testpost', function () {
    $data = request()->all(); // 取得所有POST資料（array）
    return response()->json([
        'data' => $data,
    ]);
});

// 會員註冊／登入
Route::post('/member/register', [AuthController::class, 'registerMember']);
Route::post('/member/login', [AuthController::class, 'loginMember']);

// Google 第三方登入（僅會員）
Route::get('/auth/google/redirect', [\App\Http\Controllers\API\SocialAuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [\App\Http\Controllers\API\SocialAuthController::class, 'handleGoogleCallback']);

// 會員專屬 API（需登入）
Route::middleware(['auth:sanctum'])->prefix('member')->group(function () {
    // 會員登出
    Route::post('/logout', [AuthController::class, 'logoutMember']);
    // 取得會員個人資料
    Route::get('/profile', [AuthController::class, 'memberProfile']);
    // 更新會員個人資料
    Route::put('/profile', [AuthController::class, 'updateMemberProfile']);
    // 修改密碼
    Route::put('/change-password', [AuthController::class, 'changeMemberPassword']);
    // 你可以再加上訂單、收藏、通知等API
    // Route::get('/orders', [OrderController::class, 'memberOrders']);
    // Route::get('/favorites', [FavoriteController::class, 'memberFavorites']);
});

// 服務提供者註冊／登入
Route::post('/provider/register', [AuthController::class, 'registerProvider']);
Route::post('/provider/login', [AuthController::class, 'loginProvider']);

// 服務提供者專屬 API（需登入）
Route::middleware(['auth:sanctum'])->prefix('provider')->group(function () {
    // 服務提供者登出
    Route::post('/logout', [AuthController::class, 'logoutProvider']);
    // 取得服務提供者資料
    Route::get('/profile', [AuthController::class, 'providerProfile']);
    // 更新服務提供者資料
    Route::put('/profile', [AuthController::class, 'updateProviderProfile']);
    // 修改密碼
    Route::put('/change-password', [AuthController::class, 'changeProviderPassword']);
    // 其他服務提供者專屬 API
});

// 管理員註冊／登入
Route::post('/admin/register', [AuthController::class, 'registerAdmin']);
Route::post('/admin/login', [AuthController::class, 'loginAdmin']);

// 管理員專屬 API（需登入）
Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {
    // 管理員登出
    Route::post('/logout', [AuthController::class, 'logoutAdmin']);
    // 取得管理員個人資料
    Route::get('/profile', [AuthController::class, 'adminProfile']);
    // 更新管理員個人資料
    Route::put('/profile', [AuthController::class, 'updateAdminProfile']);
    // 修改密碼
    Route::put('/change-password', [AuthController::class, 'changeAdminPassword']);
    // 查詢會員資料
    Route::get('/check-member/{id}', [AuthController::class, 'checkMember']);
    // 查詢服務提供者資料
    Route::get('/check-provider/{id}', [AuthController::class, 'checkProvider']);
    // 其他管理員專屬 API
});

// 需要認證的通用路由
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
});