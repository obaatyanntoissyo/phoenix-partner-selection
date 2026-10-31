<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('partner:reset-admin-password {email}')]
#[Description('管理者のパスワードを再設定する（パスワードは対話入力。画面やログには出さない）')]
class ResetAdminPassword extends Command
{
    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('その管理者は見つかりません。');

            return self::FAILURE;
        }

        $password = $this->secret('新しいパスワード（12文字以上）');
        $confirm = $this->secret('新しいパスワード（確認）');
        $validator = Validator::make(
            ['password' => $password, 'password_confirmation' => $confirm],
            ['password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()]],
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user->forceFill(['password' => $password])->save();
        AuditLog::record('reset_admin_password', $user, null, [], null);
        $this->info('パスワードを再設定しました: '.$user->email);

        return self::SUCCESS;
    }
}
