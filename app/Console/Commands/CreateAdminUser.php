<?php

namespace App\Console\Commands;

use App\Models\AdminProfile;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    protected $signature = 'app:create-admin
                            {name : 管理員姓名}
                            {email : 登入用電子郵件}
                            {--password= : 密碼（至少 8 碼，未提供時互動式輸入）}
                            {--position= : 職位}
                            {--department= : 部門}';

    protected $description = '建立管理員帳號（公開 /api/admin/register 端點已移除，管理員一律由主機端建立）';

    public function handle(): int
    {
        $password = $this->option('password') ?: $this->secret('請輸入密碼（至少 8 碼）');

        $validator = Validator::make([
            'name'     => $this->argument('name'),
            'email'    => $this->argument('email'),
            'password' => $password,
        ], [
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            // 管理權限影響全平台，密碼門檻高於一般使用者的 min:6
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name'     => $this->argument('name'),
            'email'    => $this->argument('email'),
            'password' => Hash::make($password),
            'role'     => 'admin',
        ]);

        AdminProfile::create([
            'user_id'    => $user->id,
            'position'   => $this->option('position'),
            'department' => $this->option('department'),
        ]);

        $this->info("管理員帳號已建立：{$user->email}（id={$user->id}）");

        return self::SUCCESS;
    }
}
