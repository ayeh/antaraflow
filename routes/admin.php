<?php

declare(strict_types=1);

use App\Domain\Admin\Controllers\Auth\LoginController;
use App\Domain\Admin\Controllers\BrandingController;
use App\Domain\Admin\Controllers\DashboardController;
use App\Domain\Admin\Controllers\EmailTemplateController;
use App\Domain\Admin\Controllers\OrganizationController;
use App\Domain\Admin\Controllers\SmtpController;
use App\Domain\Admin\Controllers\SubscriptionPlanController;
use App\Domain\Admin\Controllers\SystemController;
use App\Domain\Admin\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->as('admin.')->group(function () {
    // Guest routes
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
        Route::post('login', [LoginController::class, 'login'])->name('login.attempt');
    });

    // Authenticated admin routes
    Route::middleware('admin.auth')->group(function () {
        Route::post('logout', [LoginController::class, 'logout'])->name('logout');
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Subscription Plans
        Route::resource('plans', SubscriptionPlanController::class);

        // Users
        Route::get('users/export/csv', [UserController::class, 'exportCsv'])->name('users.export');
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show')->withTrashed();
        Route::post('users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
        Route::post('users/{user}/unsuspend', [UserController::class, 'unsuspend'])->name('users.unsuspend')->withTrashed();
        Route::post('users/{user}/impersonate', [UserController::class, 'impersonate'])->name('users.impersonate');

        // Organizations
        Route::get('organizations', [OrganizationController::class, 'index'])->name('organizations.index');
        Route::get('organizations/{organization}', [OrganizationController::class, 'show'])->name('organizations.show');
        Route::post('organizations/{organization}/suspend', [OrganizationController::class, 'suspend'])->name('organizations.suspend');
        Route::post('organizations/{organization}/unsuspend', [OrganizationController::class, 'unsuspend'])->name('organizations.unsuspend');
        Route::put('organizations/{organization}/plan', [OrganizationController::class, 'changePlan'])->name('organizations.change-plan');

        // Branding
        Route::get('branding', [BrandingController::class, 'index'])->name('branding.index');
        Route::put('branding', [BrandingController::class, 'update'])->name('branding.update');
        Route::post('branding/presets', [BrandingController::class, 'storePreset'])->name('branding.presets.store');
        Route::delete('branding/presets/{name}', [BrandingController::class, 'destroyPreset'])
            ->name('branding.presets.destroy')
            ->where('name', '[^/]{1,100}');

        // SMTP
        Route::get('smtp', [SmtpController::class, 'index'])->name('smtp.index');
        Route::put('smtp', [SmtpController::class, 'updateGlobal'])->name('smtp.update-global');
        Route::post('smtp/test', [SmtpController::class, 'testGlobal'])->name('smtp.test-global');
        Route::get('smtp/org', [SmtpController::class, 'orgIndex'])->name('smtp.org-index');
        Route::put('smtp/org/{organization}', [SmtpController::class, 'updateOrg'])->name('smtp.update-org');
        Route::post('smtp/org/{organization}/test', [SmtpController::class, 'testOrg'])->name('smtp.test-org');

        // Email Templates
        Route::get('email-templates', [EmailTemplateController::class, 'index'])->name('email-templates.index');
        Route::get('email-templates/{emailTemplate}', [EmailTemplateController::class, 'edit'])->name('email-templates.edit');
        Route::put('email-templates/{emailTemplate}', [EmailTemplateController::class, 'update'])->name('email-templates.update');
        Route::post('email-templates/{emailTemplate}/preview', [EmailTemplateController::class, 'preview'])->name('email-templates.preview');

        // System
        Route::get('system', [SystemController::class, 'index'])->name('system.index');
        Route::post('system/retry-job/{id}', [SystemController::class, 'retryJob'])->name('system.retry-job');
        Route::delete('system/failed-job/{id}', [SystemController::class, 'deleteJob'])->name('system.delete-job');
    });
});
