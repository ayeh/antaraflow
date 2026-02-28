<?php

use App\Domain\Account\Controllers\Auth\LoginController;
use App\Domain\Account\Controllers\Auth\LogoutController;
use App\Domain\Account\Controllers\Auth\RegisterController;
use App\Domain\Account\Controllers\MemberController;
use App\Domain\Account\Controllers\OrganizationController;
use App\Domain\Account\Controllers\OrganizationSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login']);
    Route::get('register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('register', [RegisterController::class, 'register']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LogoutController::class, 'logout'])->name('logout');
});

Route::middleware(['auth', 'org.context'])->group(function () {
    Route::resource('organizations', OrganizationController::class);
    Route::resource('organizations.members', MemberController::class)->only(['index', 'store', 'update', 'destroy'])->shallow();
    Route::get('organizations/{organization}/settings', [OrganizationSettingsController::class, 'edit'])->name('organizations.settings.edit');
    Route::put('organizations/{organization}/settings', [OrganizationSettingsController::class, 'update'])->name('organizations.settings.update');
});
