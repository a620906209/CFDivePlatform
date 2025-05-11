<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AdminProfile;
use App\Models\ProviderProfile;
use App\Models\MemberProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;


class AuthController extends Controller
{
    // 科定規範角色
    private const ROLE_MEMBER = 'member';
    private const ROLE_PROVIDER = 'provider';
    private const ROLE_ADMIN = 'admin';
    
    /**
     * 確認用戶角色權限
     */
    public function checkRole(Request $request, string $role): bool
    {
        $user = $request->user();
        return ($user && $user->role === $role);
    }
    
    /**
     * 查詢用戶資料 (通用方法)
     * 設計為內部使用，用於管理員查詢用戶資料
     */
    private function checkUser(string $role, int $userId): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        
        // 確保只有管理員可以使用這個方法
        if ($user->role !== self::ROLE_ADMIN) {
            return $this->getUnauthorizedResponse();
        }
        
        // 查詢指定角色的用戶
        $targetUser = User::where('id', $userId)
                          ->where('role', $role)
                          ->first();
        
        if (!$targetUser) {
            return response()->json([
                'status' => false,
                'message' => '指定的用戶不存在或不是' . ($role === self::ROLE_MEMBER ? '會員' : '教練'),
            ], 404);
        }
        
        // 取得用戶資料（包含已加密的密碼）
        $userData = [
            'id' => $targetUser->id,
            'name' => $targetUser->name,
            'email' => $targetUser->email,
            'password_hash' => $targetUser->password, // 已經加密的密碼
            'created_at' => $targetUser->created_at->format('Y-m-d H:i:s'),
        ];
        
        if ($role === self::ROLE_MEMBER) {
            $targetUser->load('memberProfile');
        } else {
            $targetUser->load('providerProfile');
        }
        
        return response()->json([
            'status' => true,
            'data' => $userData,
            'profile' => $role === self::ROLE_MEMBER ? $targetUser->memberProfile : $targetUser->providerProfile,
        ]);
    }
    
    /**
     * 從請求取得密碼驗證權限失敗的回應
     */
    private function getUnauthorizedResponse(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'status' => false,
            'message' => '無權限存取'
        ], 403);
    }
    
    /**
     * 符合規範的變更密碼模式
     */
    private function commonChangePassword(Request $request, string $role): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        
        // 確保只有指定角色可以使用這個方法
        if ($user->role !== $role) {
            return $this->getUnauthorizedResponse();
        }

        // 驗證請求數據
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => '驗證失敗',
                'errors' => $validator->errors()
            ], 422);
        }

        // 確認目前密碼是否正確
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => '目前密碼錯誤'
            ], 401);
        }

        // 更新密碼
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'status' => true,
            'message' => '密碼修改成功',
        ]);
    }
    
    /**
     * 標準化的登出方法
     */
    private function commonLogout(Request $request, string $role): \Illuminate\Http\JsonResponse
    {
        // 確保用戶角色符合
        $user = $request->user();
        if ($user->role !== $role) {
            return $this->getUnauthorizedResponse();
        }

        // 撤銷目前的 token
        $request->user()->currentAccessToken()->delete();
        
        $roleText = $role === self::ROLE_MEMBER ? '會員' : ($role === self::ROLE_PROVIDER ? '服務提供者' : '管理員');

        return response()->json([
            'status' => true,
            'message' => $roleText . '登出成功'
        ]);
    }

    /**
     * 標準化的取得個人資料方法
     */
    private function commonProfile(Request $request, string $role, string $relation): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        
        // 確保只有指定角色可以使用這個方法
        if ($user->role !== $role) {
            return $this->getUnauthorizedResponse();
        }

        // 加載角色對應資料
        $user->load($relation);
        
        return response()->json([
            'status' => true,
            'data' => $user,
        ]);
    }

    /**
     * 用戶登出
     */
    public function logout(Request $request)
    {
        // 撤銷目前的 token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'message' => '登出成功'
        ]);
    }

    /**
     * 獲取當前用戶資訊
     */
    public function user(Request $request)
    {
        $user = $request->user();
        
        // 根據角色加載對應的資料
        if ($user->isAdmin()) {
            $user->load('adminProfile');
        } elseif ($user->isProvider()) {
            $user->load('providerProfile');
        } elseif ($user->isMember()) {
            $user->load('memberProfile');
        }

        return response()->json([
            'status' => true,
            'data' => $user
        ]);
    }

/**
 * 會員註冊
 */
    public function registerMember(Request $request)
    {
        // 驗證請求數據
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'phone' => 'nullable|string|max:20',
            'birthday' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => '驗證失敗',
                'errors' => $validator->errors()
            ], 422);
        }

        // 創建用戶
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'role' => 'member', // 強制為會員角色
        ]);

        // 創建會員資料
        MemberProfile::create([
            'user_id' => $user->id,
            'birthday' => $request->birthday,
            'gender' => $request->gender,
        ]);

        // 創建 API token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => '會員註冊成功',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ], 201);
    }

/**
 * 會員登入
 */
    public function loginMember(Request $request)
    {
        // 驗證請求數據
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => '驗證失敗',
                'errors' => $validator->errors()
            ], 422);
        }

        // 檢查用戶是否存在
        $user = User::where('email', $request->email)
                   ->where('role', 'member') // 只驗證會員
                   ->first();

        // 檢查密碼
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => '電子郵件或密碼錯誤'
            ], 401);
        }

        // 檢查用戶是否啟用
        if (!$user->is_active) {
            return response()->json([
                'status' => false,
                'message' => '帳號已被停用'
            ], 403);
        }

        // 創建 API token
        $token = $user->createToken('auth_token')->plainTextToken;

        // 加載會員資料
        $user->load('memberProfile');

        return response()->json([
            'status' => true,
            'message' => '登入成功',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ]);
    }

/**
 * 會員登出
 */
    public function logoutMember(Request $request)
    {
        // 確保只有會員可以使用這個方法
        $user = $request->user();
        if ($user->role !== 'member') {
            return response()->json([
                'status' => false,
                'message' => '無權限存取'
            ], 403);
        }

        // 撤銷目前的 token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'message' => '會員登出成功'
        ]);
    }

/**
 * 取得會員個人資料
 */
    public function memberProfile(Request $request)
    {
        $user = auth()->user();
        // 確保只有會員可以使用這個方法
        if ($user->role !== 'member') {
            return response()->json([
                'status' => false,
                'message' => '無權限存取'
            ], 403);
        }

        // 加載會員資料
        $user->load('memberProfile');
        
        return response()->json([
            'status' => true,
            'data' => $user,
        ]);
    }

/**
 * 更新會員個人資料
 */
    public function updateMemberProfile(Request $request)
    {
        $user = auth()->user();
        // 確保只有會員可以使用這個方法
        if ($user->role !== 'member') {
            return response()->json([
                'status' => false,
                'message' => '無權限存取'
            ], 403);
        }

        // 驗證請求數據
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => '驗證失敗',
                'errors' => $validator->errors()
            ], 422);
        }

        // 更新用戶資料
        if ($request->has('name')) {
            $user->name = $request->name;
        }
        if ($request->has('email')) {
            $user->email = $request->email;
        }
        if ($request->has('phone')) {
            $user->phone = $request->phone;
        }
        $user->save();

        // 加載會員資料
        $user->load('memberProfile');

        return response()->json([
            'status' => true,
            'message' => '會員資料已更新',
            'data' => $user,
        ]);
    }

/**
 * 修改會員密碼
 */
    public function changeMemberPassword(Request $request)
    {
        $user = auth()->user();
        // 確保只有會員可以使用這個方法
        if ($user->role !== 'member') {
            return response()->json([
                'status' => false,
                'message' => '無權限存取'
            ], 403);
        }

        // 驗證請求數據
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => '驗證失敗',
                'errors' => $validator->errors()
            ], 422);
        }

        // 確認目前密碼是否正確
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => '目前密碼錯誤'
            ], 401);
        }

        // 更新密碼
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'status' => true,
            'message' => '密碼修改成功',
        ]);
    }

/**
 * 服務提供者註冊
 */
    public function registerProvider(Request $request)
    {
        // 驗證請求數據
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'phone' => 'nullable|string|max:20',
            'business_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'contact_person' => 'nullable|string|max:100',
            'contact_phone' => 'nullable|string|max:20',
            'contact_email' => 'nullable|string|email|max:255',
            'address' => 'nullable|string|max:255',
            'business_hours' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => '驗證失敗',
                'errors' => $validator->errors()
            ], 422);
        }

        // 創建用戶
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'role' => 'provider', // 強制為服務提供者角色
        ]);

        // 創建服務提供者資料
        ProviderProfile::create([
            'user_id' => $user->id,
            'business_name' => $request->business_name,
            'description' => $request->description ?? null,
            'contact_person' => $request->contact_person ?? null,
            'contact_phone' => $request->contact_phone ?? null,
            'contact_email' => $request->contact_email ?? null,
            'address' => $request->address ?? null,
            'business_hours' => $request->business_hours ?? null,
        ]);

        // 創建 API token
        $token = $user->createToken('auth_token')->plainTextToken;
        
        return response()->json([
            'status' => true,
            'message' => '服務提供者註冊成功',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ], 201);
    }

/**
 * 服務提供者登入
 */
    public function loginProvider(Request $request)
    {
        // 驗證請求數據
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => '驗證失敗',
                'errors' => $validator->errors()
            ], 422);
        }

        // 檢查用戶是否存在
        $user = User::where('email', $request->email)
                   ->where('role', 'provider') // 只驗證服務提供者
                   ->first();

        // 檢查密碼
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => '電子郵件或密碼錯誤'
            ], 401);
        }

        // 檢查用戶是否啟用
        if (!$user->is_active) {
            return response()->json([
                'status' => false,
                'message' => '帳號已被停用'
            ], 403);
        }

        // 創建 API token
        $token = $user->createToken('auth_token')->plainTextToken;

        // 加載服務提供者資料
        $user->load('providerProfile');

        return response()->json([
            'status' => true,
            'message' => '登入成功',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ]);
    }

/**
 * 服務提供者登出
 */
    public function logoutProvider(Request $request)
    {
        // 確保只有服務提供者可以使用這個方法
        $user = $request->user();
        if ($user->role !== 'provider') {
            return response()->json([
                'status' => false,
                'message' => '無權限存取'
            ], 403);
        }

        // 撤銷目前的 token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'message' => '服務提供者登出成功'
        ]);
    }

/**
 * 取得服務提供者資料
 */
    public function providerProfile(Request $request)
    {
        $user = auth()->user();
        // 確保只有服務提供者可以使用這個方法
        if ($user->role !== 'provider') {
            return response()->json([
                'status' => false,
                'message' => '無權限存取'
            ], 403);
        }

        // 加載服務提供者資料
        $user->load('providerProfile');
        
        return response()->json([
            'status' => true,
            'data' => $user,
        ]);
    }

/**
 * 更新服務提供者資料
 */
    public function updateProviderProfile(Request $request)
    {
        $user = auth()->user();
        // 確保只有服務提供者可以使用這個方法
        if ($user->role !== 'provider') {
            return response()->json([
                'status' => false,
                'message' => '無權限存取'
            ], 403);
        }

        // 驗證請求數據
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'business_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'contact_person' => 'nullable|string|max:100',
            'contact_phone' => 'nullable|string|max:20',
            'contact_email' => 'nullable|string|email|max:255',
            'address' => 'nullable|string|max:255',
            'business_hours' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => '驗證失敗',
                'errors' => $validator->errors()
            ], 422);
        }

        // 更新用戶資料
        if ($request->has('name')) {
            $user->name = $request->name;
        }
        if ($request->has('email')) {
            $user->email = $request->email;
        }
        if ($request->has('phone')) {
            $user->phone = $request->phone;
        }
        $user->save();

        // 更新服務提供者資料
        $providerProfile = $user->providerProfile;
        
        if ($request->has('business_name')) {
            $providerProfile->business_name = $request->business_name;
        }
        if ($request->has('description')) {
            $providerProfile->description = $request->description;
        }
        if ($request->has('contact_person')) {
            $providerProfile->contact_person = $request->contact_person;
        }
        if ($request->has('contact_phone')) {
            $providerProfile->contact_phone = $request->contact_phone;
        }
        if ($request->has('contact_email')) {
            $providerProfile->contact_email = $request->contact_email;
        }
        if ($request->has('address')) {
            $providerProfile->address = $request->address;
        }
        if ($request->has('business_hours')) {
            $providerProfile->business_hours = $request->business_hours;
        }
        
        $providerProfile->save();

        // 加載服務提供者資料
        $user->load('providerProfile');

        return response()->json([
            'status' => true,
            'message' => '服務提供者資料已更新',
            'data' => $user,
        ]);
    }

/**
 * 修改服務提供者密碼
 */
    public function changeProviderPassword(Request $request)
    {
        $user = auth()->user();
        // 確保只有服務提供者可以使用這個方法
        if ($user->role !== 'provider') {
            return response()->json([
                'status' => false,
                'message' => '無權限存取'
            ], 403);
        }

        // 驗證請求數據
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => '驗證失敗',
                'errors' => $validator->errors()
            ], 422);
        }

        // 確認目前密碼是否正確
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => '目前密碼錯誤'
            ], 401);
        }

        // 更新密碼
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'status' => true,
            'message' => '密碼修改成功',
        ]);
    }

    /**
     * 管理員註冊
     */
    public function registerAdmin(Request $request)
    {
        // 驗證請求數據
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => '驗證失敗',
                'errors' => $validator->errors()
            ], 422);
        }

        // 創建用戶
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'role' => 'admin', // 強制為管理員角色
        ]);

        // 創建管理員資料
        AdminProfile::create([
            'user_id' => $user->id,
            'position' => $request->position,
            'department' => $request->department,
        ]);

        // 創建 API token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => '管理員註冊成功',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ], 201);
    }

    /**
     * 管理員登入
     */
    public function loginAdmin(Request $request)
    {
        // 驗證請求數據
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => '驗證失敗',
                'errors' => $validator->errors()
            ], 422);
        }

        // 檢查用戶是否存在
        $user = User::where('email', $request->email)
                   ->where('role', 'admin') // 只驗證管理員
                   ->first();

        // 檢查密碼
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => '電子郵件或密碼錯誤'
            ], 401);
        }

        // 檢查用戶是否啟用
        if (!$user->is_active) {
            return response()->json([
                'status' => false,
                'message' => '帳號已被停用'
            ], 403);
        }

        // 創建 API token
        $token = $user->createToken('auth_token')->plainTextToken;

        // 加載管理員資料
        $user->load('adminProfile');

        return response()->json([
            'status' => true,
            'message' => '登入成功',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ]);
    }

    /**
     * 管理員登出
     */
    public function logoutAdmin(Request $request)
    {
        // 確保只有管理員可以使用這個方法
        $user = $request->user();
        if ($user->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => '無權限存取'
            ], 403);
        }

        // 撤銷目前的 token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'message' => '管理員登出成功'
        ]);
    }

    /**
     * 取得管理員個人資料
     */
    public function adminProfile(Request $request)
    {
        $user = auth()->user();
        // 確保只有管理員可以使用這個方法
        if ($user->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => '無權限存取'
            ], 403);
        }

        // 加載管理員資料
        $user->load('adminProfile');
        
        return response()->json([
            'status' => true,
            'data' => $user,
        ]);
    }

    /**
     * 更新管理員個人資料
     */
    public function updateAdminProfile(Request $request)
    {
        $user = auth()->user();
        // 確保只有管理員可以使用這個方法
        if ($user->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => '無權限存取'
            ], 403);
        }

        // 驗證請求數據
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => '驗證失敗',
                'errors' => $validator->errors()
            ], 422);
        }

        // 更新用戶資料
        if ($request->has('name')) {
            $user->name = $request->name;
        }
        if ($request->has('email')) {
            $user->email = $request->email;
        }
        if ($request->has('phone')) {
            $user->phone = $request->phone;
        }
        $user->save();

        // 更新管理員資料
        if ($request->has('position') || $request->has('department')) {
            $adminProfile = $user->adminProfile;
            if ($request->has('position')) {
                $adminProfile->position = $request->position;
            }
            if ($request->has('department')) {
                $adminProfile->department = $request->department;
            }
            $adminProfile->save();
        }

        // 加載管理員資料
        $user->load('adminProfile');

        return response()->json([
            'status' => true,
            'message' => '管理員資料已更新',
            'data' => $user,
        ]);
    }

    /**
     * 修改管理員密碼
     */
    public function changeAdminPassword(Request $request)
    {
        $user = auth()->user();
        // 確保只有管理員可以使用這個方法
        if ($user->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => '無權限存取'
            ], 403);
        }

        // 驗證請求數據
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => '驗證失敗',
                'errors' => $validator->errors()
            ], 422);
        }

        // 確認目前密碼是否正確
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => '目前密碼錯誤'
            ], 401);
        }

        // 更新密碼
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'status' => true,
            'message' => '密碼修改成功',
        ]);
    }

    /**
     * 查詢會員資料
     * 只有管理員可以使用這個方法
     */
    public function checkMember(int $id)
    {
        return $this->checkUser(self::ROLE_MEMBER, $id);
    }

    /**
     * 查詢服務提供者資料
     * 只有管理員可以使用這個方法
     */
    public function checkProvider(int $id)
    {
        return $this->checkUser(self::ROLE_PROVIDER, $id);
    }
}