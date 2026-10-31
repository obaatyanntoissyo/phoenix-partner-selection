<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResetAdminPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_reset_with_a_strong_password(): void
    {
        $user = User::create(['name' => '管理者', 'email' => 'admin@example.invalid', 'password' => 'Old-password-12345']);

        $this->artisan('partner:reset-admin-password', ['email' => 'admin@example.invalid'])
            ->expectsQuestion('新しいパスワード（12文字以上）', 'New-password-67890')
            ->expectsQuestion('新しいパスワード（確認）', 'New-password-67890')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('New-password-67890', $user->fresh()->password));
    }

    public function test_weak_password_and_unknown_admin_are_rejected(): void
    {
        $user = User::create(['name' => '管理者', 'email' => 'admin@example.invalid', 'password' => 'Old-password-12345']);

        $this->artisan('partner:reset-admin-password', ['email' => 'admin@example.invalid'])
            ->expectsQuestion('新しいパスワード（12文字以上）', 'short1')
            ->expectsQuestion('新しいパスワード（確認）', 'short1')
            ->assertFailed();
        $this->assertTrue(Hash::check('Old-password-12345', $user->fresh()->password));

        $this->artisan('partner:reset-admin-password', ['email' => 'nobody@example.invalid'])->assertFailed();
    }
}
