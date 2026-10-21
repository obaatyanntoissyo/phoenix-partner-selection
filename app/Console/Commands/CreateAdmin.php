<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('partner:create-admin {email} {name}')]
#[Description('管理者アカウントを作成する（パスワードは対話入力。固定の初期パスワードは使わない）')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $password = $this->secret('パスワード（12文字以上）');
        $confirm = $this->secret('パスワード（確認）');
        $validator = Validator::make(
            ['email' => $this->argument('email'), 'name' => $this->argument('name'), 'password' => $password, 'password_confirmation' => $confirm],
            ['email' => ['required', 'email', 'unique:users,email'], 'name' => ['required', 'max:100'], 'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()]],
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        $user = User::create(['email' => $this->argument('email'), 'name' => $this->argument('name'), 'password' => $password]);
        AuditLog::record('create_admin', $user, null, [], null);
        $this->info('管理者を作成しました: '.$user->email);

        return self::SUCCESS;
    }
}
