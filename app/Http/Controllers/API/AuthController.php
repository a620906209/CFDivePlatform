<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AdminProfile;
use App\Models\CoachProfile;
use App\Models\MemberProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    // 科定規範角色
    private const ROLE_MEMBER = 'member';
    private const ROLE_COACH = 'coach';
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
            $targetUser->load('coachProfile');
        }
        
        return response()->json([
            'status' => true,
            'data' => $userData,
            'profile' => $role === self::ROLE_MEMBER ? $targetUser->memberProfile : $targetUser->coachProfile,
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
        
        $roleText = $role === self::ROLE_MEMBER ? '會員' : ($role === self::ROLE_COACH ? '教練' : '管理員');

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
        } elseif ($user->isCoach()) {
            $user->load('coachProfile');
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
     * 教練註冊
     */
    public function registerCoach(Request $request)
    {
        // 驗證請求數據
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'phone' => 'nullable|string|max:20',
            'bio' => 'nullable|string',
            'expertise' => 'nullable|string|max:100',
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
            'role' => 'coach', // 強制為教練角色
        ]);

        // 創建教練資料
        CoachProfile::create([
            'user_id' => $user->id,
            'bio' => $request->bio,
            'expertise' => $request->expertise,
        ]);

        // 創建 API token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => '教練註冊成功',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ], 201);
    }

    /**
     * 教練登入
     */
    public function loginCoach(Request $request)
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
                   ->where('role', 'coach') // 只驗證教練
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

        // 加載教練資料
        $user->load('coachProfile');

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
     * 教練登出
     */
    public function logoutCoach(Request $request)
    {
        // 確保只有教練可以使用這個方法
        $user = $request->user();
        if ($user->role !== 'coach') {
            return response()->json([
                'status' => false,
                'message' => '無權限存取'
            ], 403);
        }

        // 撤銷目前的 token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'message' => '教練登出成功'
        ]);
    }

    /**
     * 取得教練個人資料
     */
    public function coachProfile(Request $request)
    {
        $user = auth()->user();
        // 確保只有教練可以使用這個方法
        if ($user->role !== 'coach') {
            return response()->json([
                'status' => false,
                'message' => '無權限存取'
            ], 403);
        }

        // 加載教練資料
        $user->load('coachProfile');
        
        return response()->json([
            'status' => true,
            'data' => $user,
        ]);
    }

    /**
     * 更新教練個人資料
     */
    public function updateCoachProfile(Request $request)
    {
        $user = auth()->user();
        // 確保只有教練可以使用這個方法
        if ($user->role !== 'coach') {
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
            'bio' => 'nullable|string',
            'expertise' => 'nullable|string|max:100',
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

        // 更新教練資料
        if ($request->has('bio') || $request->has('expertise')) {
            $coachProfile = $user->coachProfile;
            if ($request->has('bio')) {
                $coachProfile->bio = $request->bio;
            }
            if ($request->has('expertise')) {
                $coachProfile->expertise = $request->expertise;
            }
            $coachProfile->save();
        }

        // 加載教練資料
        $user->load('coachProfile');

        return response()->json([
            'status' => true,
            'message' => '教練資料已更新',
            'data' => $user,
        ]);
    }

    /**
     * 修改教練密碼
     */
    public function changeCoachPassword(Request $request)
    {
        $user = auth()->user();
        // 確保只有教練可以使用這個方法
        if ($user->role !== 'coach') {
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
     * 查詢教練資料
     * 只有管理員可以使用這個方法
     */
    public function checkCoach(int $id)
    {
        return $this->checkUser(self::ROLE_COACH, $id);
    }
}