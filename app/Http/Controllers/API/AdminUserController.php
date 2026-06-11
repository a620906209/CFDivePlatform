<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    private function checkAdmin()
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['status' => false, 'message' => '無權限存取'], 403);
        }
        return null;
    }

    private function findUser(int $id, string $role)
    {
        return User::where('id', $id)->where('role', $role)->first();
    }

    public function members(Request $request)
    {
        if ($err = $this->checkAdmin()) return $err;

        $query = User::where('role', 'member')->with('memberProfile');

        if ($q = $request->query('q')) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        $paginated = $query->latest()->paginate(15);

        return response()->json([
            'status' => true,
            'data'   => $paginated->items(),
            'meta'   => [
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
            ],
        ]);
    }

    public function member(int $id)
    {
        if ($err = $this->checkAdmin()) return $err;

        $user = $this->findUser($id, 'member');
        if (!$user) {
            return response()->json(['status' => false, 'message' => '用戶不存在'], 404);
        }

        return response()->json(['status' => true, 'data' => $user->load('memberProfile')]);
    }

    public function toggleMemberActive(int $id)
    {
        if ($err = $this->checkAdmin()) return $err;

        $user = $this->findUser($id, 'member');
        if (!$user) {
            return response()->json(['status' => false, 'message' => '用戶不存在'], 404);
        }

        $user->is_active = !$user->is_active;
        $user->save();

        $msg = $user->is_active ? '帳號已啟用' : '帳號已停用';
        return response()->json(['status' => true, 'message' => $msg, 'data' => ['is_active' => $user->is_active]]);
    }

    public function providers(Request $request)
    {
        if ($err = $this->checkAdmin()) return $err;

        $query = User::where('role', 'provider')->with('providerProfile');

        if ($q = $request->query('q')) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        $paginated = $query->latest()->paginate(15);

        return response()->json([
            'status' => true,
            'data'   => $paginated->items(),
            'meta'   => [
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
            ],
        ]);
    }

    public function provider(int $id)
    {
        if ($err = $this->checkAdmin()) return $err;

        $user = $this->findUser($id, 'provider');
        if (!$user) {
            return response()->json(['status' => false, 'message' => '用戶不存在'], 404);
        }

        return response()->json(['status' => true, 'data' => $user->load('providerProfile')]);
    }

    public function toggleProviderActive(int $id)
    {
        if ($err = $this->checkAdmin()) return $err;

        $user = $this->findUser($id, 'provider');
        if (!$user) {
            return response()->json(['status' => false, 'message' => '用戶不存在'], 404);
        }

        $user->is_active = !$user->is_active;
        $user->save();

        $msg = $user->is_active ? '帳號已啟用' : '帳號已停用';
        return response()->json(['status' => true, 'message' => $msg, 'data' => ['is_active' => $user->is_active]]);
    }

    // toggleProviderVerified 已移除：驗證狀態變更一律走 AdminVerificationController 的審核狀態機
}
