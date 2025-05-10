<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\MemberProfile;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    /**
     * 重定向到 Google 登入頁面
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->with(['access_type' => 'offline', 'prompt' => 'consent']) // 這裡要求 prompt=consent 才能每次都獲取 refresh token
            ->stateless()
            ->redirect();
    }

    /**
     * 處理 Google 回調
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            // 獲取 Google 用戶資訊
            $googleUser = Socialite::driver('google')->stateless()->user();

            // 查找相關的社交帳號
            $socialAccount = SocialAccount::where('provider', 'google')
                                          ->where('provider_id', $googleUser->getId())
                                          ->first();

            if ($socialAccount) {
                // 已存在社交帳號，直接獲取用戶
                $user = $socialAccount->user;
                
                // 如果用戶不是會員，拒絕登入
                if ($user->role !== 'member') {
                    return response()->json([
                        'status' => false,
                        'message' => '只有會員可以使用 Google 登入'
                    ], 403);
                }
            } else {
                // 檢查是否有相同 email 的用戶
                $user = User::where('email', $googleUser->getEmail())->first();
                
                if ($user) {
                    // 已存在用戶，但沒有連結社交帳號
                    // 檢查是否為會員
                    if ($user->role !== 'member') {
                        return response()->json([
                            'status' => false,
                            'message' => '只有會員可以使用 Google 登入'
                        ], 403);
                    }
                } else {
                    // 建立新用戶
                    $user = User::create([
                        'name' => $googleUser->getName(),
                        'email' => $googleUser->getEmail(),
                        'password' => Hash::make(Str::random(24)),
                        'role' => 'member', // 強制為會員角色
                        'is_active' => true,
                    ]);
                    
                    // 建立會員資料
                    try {
                        MemberProfile::create([
                            'user_id' => $user->id,
                            // 可以選擇性地從 Google 獲取更多資訊
                        ]);
                    } catch (\Exception $e) {
                        // 記錄錯誤，但不中斷整個登入流程
                        \Log::error('Google 登入建立會員資料失敗: ' . $e->getMessage());
                    }
                }
                
                // 建立社交帳號連結
                $socialAccountData = [
                    'user_id' => $user->id,
                    'provider' => 'google',
                    'provider_id' => $googleUser->getId(),
                    'provider_email' => $googleUser->getEmail(),
                    'access_token' => $googleUser->token,
                    'expires_in' => $googleUser->expiresIn ?? null,
                ];
                
                // 確保如果有 refreshToken 就正確地儲存
                if (!empty($googleUser->refreshToken)) {
                    $socialAccountData['refresh_token'] = $googleUser->refreshToken;
                }
                
                $socialAccount = SocialAccount::create($socialAccountData);
            }
            
            // 生成 Sanctum token
            $token = $user->createToken('google-auth')->plainTextToken;
            
            // 載入會員資料
            $user->load('memberProfile');
            
            return response()->json([
                'status' => true,
                'message' => 'Google 登入成功',
                'data' => [
                    'user' => $user,
                    'token' => $token,
                    'token_type' => 'Bearer',
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Google 登入失敗：' . $e->getMessage()
            ], 500);
        }
    }
}
