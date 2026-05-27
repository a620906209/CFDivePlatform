<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\DivingOfferController;
use App\Http\Controllers\API\ProviderOfferController;
use App\Http\Controllers\API\ScheduleController;
use App\Http\Controllers\API\ProviderBookingController;
use App\Http\Controllers\API\MemberBookingController;
use App\Http\Controllers\API\ReviewController;
use App\Http\Controllers\API\AdminReviewController;
use App\Http\Controllers\API\AdminBookingController;
use App\Http\Controllers\API\CourseImageController;
use App\Http\Controllers\API\AdminStatsController;
use App\Http\Controllers\API\AdminUserController;
use App\Http\Controllers\API\AdminOfferController;
use App\Http\Controllers\API\BookingMessageController;
use App\Http\Controllers\API\NotificationController;

Broadcast::routes(['middleware' => ['auth:sanctum']]);

// 潛水課程（公開）
Route::get('/diving-offers', [DivingOfferController::class, 'index']);
Route::get('/diving-offers/{id}', [DivingOfferController::class, 'show']);
Route::get('/diving-offers/{id}/schedules', [ScheduleController::class, 'publicList']);
Route::get('/diving-offers/{id}/reviews',  [ReviewController::class, 'publicList']);

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
    // 預約
    Route::get('/bookings',          [MemberBookingController::class, 'index']);
    Route::post('/bookings',         [MemberBookingController::class, 'store']);
    Route::get('/bookings/{id}',     [MemberBookingController::class, 'show']);
    Route::delete('/bookings/{id}',  [MemberBookingController::class, 'destroy']);
    // 評價
    Route::post('/reviews',       [ReviewController::class, 'store']);
    Route::put('/reviews/{id}',   [ReviewController::class, 'update']);
    Route::delete('/reviews/{id}',[ReviewController::class, 'destroy']);
});

// 有幫助投票（需登入，但不限 member prefix）
Route::middleware('auth:sanctum')->post('/reviews/{id}/helpful', [ReviewController::class, 'toggleHelpful']);

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
    // 教練課程管理
    Route::get('/offers',          [ProviderOfferController::class, 'index']);
    Route::post('/offers',         [ProviderOfferController::class, 'store']);
    Route::get('/offers/{id}',     [ProviderOfferController::class, 'show']);
    Route::put('/offers/{id}',     [ProviderOfferController::class, 'update']);
    Route::delete('/offers/{id}',  [ProviderOfferController::class, 'destroy']);
    // 課程圖片
    Route::post('/offers/{id}/cover',    [CourseImageController::class, 'uploadCover']);
    Route::delete('/offers/{id}/cover',  [CourseImageController::class, 'deleteCover']);
    Route::post('/offers/{id}/images',   [CourseImageController::class, 'uploadImage']);
    Route::delete('/images/{id}',        [CourseImageController::class, 'deleteImage']);
    // 時段管理
    Route::get('/schedules',            [ScheduleController::class, 'index']);
    Route::post('/schedules',           [ScheduleController::class, 'store']);
    Route::put('/schedules/{id}',       [ScheduleController::class, 'update']);
    Route::delete('/schedules/{id}',    [ScheduleController::class, 'destroy']);
    // 預約管理
    Route::get('/bookings',                      [ProviderBookingController::class, 'index']);
    Route::put('/bookings/{id}/confirm',         [ProviderBookingController::class, 'confirm']);
    Route::put('/bookings/{id}/reject',          [ProviderBookingController::class, 'reject']);
    Route::put('/bookings/{id}/cancel',          [ProviderBookingController::class, 'cancel']);
    Route::put('/bookings/{id}/complete',        [ProviderBookingController::class, 'complete']);
});

// 管理員註冊／登入
Route::post('/admin/register', [AuthController::class, 'registerAdmin']);
Route::post('/admin/login', [AuthController::class, 'loginAdmin']);

// 管理員專屬 API（需登入）
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
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
    // 統計數據
    Route::get('/stats', [AdminStatsController::class, 'index']);
    // 用戶管理
    Route::get('/members',                          [AdminUserController::class, 'members']);
    Route::get('/members/{id}',                     [AdminUserController::class, 'member']);
    Route::put('/members/{id}/toggle-active',       [AdminUserController::class, 'toggleMemberActive']);
    Route::get('/providers',                        [AdminUserController::class, 'providers']);
    Route::get('/providers/{id}',                   [AdminUserController::class, 'provider']);
    Route::put('/providers/{id}/toggle-active',     [AdminUserController::class, 'toggleProviderActive']);
    Route::put('/providers/{id}/toggle-verified',   [AdminUserController::class, 'toggleProviderVerified']);
    // 課程管理
    Route::get('/offers',           [AdminOfferController::class, 'index']);
    Route::delete('/offers/{id}',   [AdminOfferController::class, 'destroy']);
    // 預約管理
    Route::get('/bookings',                   [AdminBookingController::class, 'index']);
    Route::put('/bookings/{id}/complete',     [AdminBookingController::class, 'complete']);
    // 評價管理
    Route::get('/reviews',          [AdminReviewController::class, 'index']);
    Route::delete('/reviews/{id}',  [AdminReviewController::class, 'destroy']);
});

// 通知（Member + Provider 共用）
Route::middleware('auth:sanctum')->prefix('notifications')->group(function () {
    Route::get('/',          [NotificationController::class, 'index']);
    Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('/read-all',   [NotificationController::class, 'markAllRead']);
    Route::patch('/{id}/read',  [NotificationController::class, 'markRead']);
    Route::delete('/{id}',      [NotificationController::class, 'destroy']);
});

// 即時訊息（Member + Provider 共用，依 booking 參與方驗證）
Route::middleware('auth:sanctum')->group(function () {
    // unread-counts 必須在 {booking} 之前，否則會被 route model binding 吃掉
    Route::get('/bookings/messages/unread-counts',   [BookingMessageController::class, 'unreadCounts']);
    Route::get('/bookings/{booking}/messages',       [BookingMessageController::class, 'index']);
    Route::post('/bookings/{booking}/messages',      [BookingMessageController::class, 'store']);
    Route::post('/bookings/{booking}/messages/read', [BookingMessageController::class, 'markRead']);
});

// 需要認證的通用路由
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
});