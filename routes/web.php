<?php

use App\Http\Controllers\Admin\ApplicationActionController;
use App\Http\Controllers\Admin\ApplicationController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GoogleCalendarController;
use App\Http\Controllers\Admin\InterviewSlotController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Applicant\ApplyController;
use App\Http\Controllers\Applicant\BookingController;
use App\Http\Controllers\Applicant\InviteController;
use App\Http\Controllers\Applicant\MyController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/apply/partner');

// 応募入口。区分はルートで固定する（①一括制作 / ②8341）
Route::get('/apply/partner', [ApplyController::class, 'show'])->defaults('category', 'p1')->name('apply.p1');
Route::post('/apply/partner', [ApplyController::class, 'store'])->defaults('category', 'p1')->middleware('throttle:apply')->name('apply.p1.store');
Route::get('/apply/8341', [ApplyController::class, 'show'])->defaults('category', 'p2')->name('apply.p2');
Route::post('/apply/8341', [ApplyController::class, 'store'])->defaults('category', 'p2')->middleware('throttle:apply')->name('apply.p2.store');
Route::get('/apply/done', [ApplyController::class, 'done'])->name('apply.done');

// 招待リンク（GET は確認画面のみ。POST でセッションへ交換）
Route::get('/invite/{token}', [InviteController::class, 'show'])->middleware('throttle:invite')->name('invite.show');
Route::post('/invite', [InviteController::class, 'accept'])->middleware('throttle:invite')->name('invite.accept');

// 本人専用ページ
Route::middleware(['applicant', 'throttle:applicant'])->prefix('my')->name('my.')->group(function () {
    Route::get('/', [MyController::class, 'home'])->name('home');
    Route::get('/conditions', [MyController::class, 'conditions'])->name('conditions');
    Route::get('/estimate', [MyController::class, 'estimate'])->name('estimate');
    Route::post('/estimate', [MyController::class, 'saveEstimate'])->name('estimate.save');
    Route::get('/confirm', [MyController::class, 'confirm'])->name('confirm');
    Route::post('/submit', [MyController::class, 'submit'])->name('submit');
    Route::get('/result', [MyController::class, 'result'])->name('result');
    Route::get('/booking', [BookingController::class, 'show'])->name('booking');
    Route::post('/booking/go', [BookingController::class, 'go'])->name('booking.go');
    Route::post('/booking/reserve', [BookingController::class, 'reserve'])->name('booking.reserve');
    Route::get('/booking-url', [BookingController::class, 'url'])->name('booking.url');
});

// 開発用のテスト予約画面。PARTNER_DEV_TOOLS=true かつ本番以外でのみ有効
Route::get('/dev/booking', function () {
    abort_unless(config('partner.dev_tools') && ! app()->environment('production'), 404);

    return view('dev.booking');
})->name('dev.booking');

// 管理画面
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->middleware('guest')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware(['guest', 'throttle:admin-login'])->name('login.attempt');

    Route::middleware('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::post('/purge', [DashboardController::class, 'purge'])->name('purge');

        Route::get('/applications', [ApplicationController::class, 'index'])->name('applications.index');
        Route::get('/applications/export', [ApplicationController::class, 'export'])->name('applications.export');
        Route::get('/applications/create', [ApplicationController::class, 'create'])->name('applications.create');
        Route::post('/applications/preview', [ApplicationController::class, 'preview'])->name('applications.preview');
        Route::post('/applications', [ApplicationController::class, 'store'])->name('applications.store');
        Route::get('/applications/{application}', [ApplicationController::class, 'show'])->name('applications.show');

        Route::controller(ApplicationActionController::class)->prefix('/applications/{application}')->name('applications.')->group(function () {
            Route::post('/reinvite', 'reinvite')->name('reinvite');
            Route::post('/revoke-invites', 'revokeInvites')->name('revoke-invites');
            Route::post('/allow-resubmission', 'allowResubmission')->name('allow-resubmission');
            Route::post('/grant-booking', 'grantBooking')->name('grant-booking');
            Route::post('/revoke-booking', 'revokeBooking')->name('revoke-booking');
            Route::post('/interview', 'updateInterview')->name('interview');
            Route::post('/mail/{job}/resend', 'resendMail')->name('resend-mail');
            Route::post('/anonymize', 'anonymize')->name('anonymize');
        });

        Route::get('/slots', [InterviewSlotController::class, 'index'])->name('slots.index');
        Route::post('/slots', [InterviewSlotController::class, 'store'])->name('slots.store');
        Route::post('/slots/settings', [InterviewSlotController::class, 'updateSettings'])->name('slots.settings');
        Route::post('/slots/{slot}/delete', [InterviewSlotController::class, 'destroy'])->name('slots.destroy');
        Route::post('/bookings/{booking}/cancel', [InterviewSlotController::class, 'cancel'])->name('bookings.cancel');
        Route::post('/bookings/{booking}/retry', [InterviewSlotController::class, 'retry'])->name('bookings.retry');
        Route::get('/google/connect', [GoogleCalendarController::class, 'connect'])->name('google.connect');
        Route::get('/google/callback', [GoogleCalendarController::class, 'callback'])->name('google.callback');
        Route::post('/google/disconnect', [GoogleCalendarController::class, 'disconnect'])->name('google.disconnect');

        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings');
        Route::post('/settings/config', [SettingsController::class, 'storeConfig'])->name('settings.config');
        Route::post('/settings/app', [SettingsController::class, 'updateSettings'])->name('settings.app');

        Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
        Route::get('/templates/{type}', [TemplateController::class, 'edit'])->name('templates.edit');
        Route::post('/templates/{type}', [TemplateController::class, 'update'])->name('templates.update');
    });
});
