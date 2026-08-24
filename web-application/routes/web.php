<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ScreeningController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdvisoryController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\FarmSettingsController;
use App\Http\Controllers\MonitoringAccountController;

Route::get('/', fn()=>auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'));
Route::middleware('guest')->group(function () {
    Route::get('/login',[AuthController::class,'showLogin'])->name('login');
    Route::post('/login',[AuthController::class,'login'])->middleware('throttle:5,1')->name('login.attempt');
});
Route::post('/logout',[AuthController::class,'logout'])->middleware('auth')->name('logout');
Route::get('/dashboard',DashboardController::class)->middleware('auth')->name('dashboard');

Route::middleware(['auth', 'role:monitoring_personnel'])->group(function () {
    Route::get('/screenings/new',[ScreeningController::class,'create'])->name('screenings.create');
    Route::get('/screenings/sample-result',[ScreeningController::class,'sampleResult'])->name('screenings.sample');
    Route::post('/screenings',[ScreeningController::class,'store'])->middleware('throttle:10,1')->name('screenings.store');
    Route::post('/cases/{case}/follow-ups',[FollowUpController::class,'store'])->name('cases.follow-ups.store');
});

Route::middleware(['auth', 'role:farm_owner,monitoring_personnel'])->group(function () {
    Route::get('/monitoring',[CaseController::class,'index'])->name('monitoring');
    Route::get('/cases/{case}',[CaseController::class,'show'])->name('cases.show');
    Route::get('/cases/{case}/images/{image}',[CaseController::class,'image'])->name('cases.images.show');
});

Route::post('/cases/{case}/review',[CaseController::class,'review'])
    ->middleware(['auth', 'role:farm_owner'])->name('cases.review');
Route::get('/analytics',AnalyticsController::class)
    ->middleware(['auth', 'role:farm_owner'])->name('analytics');
Route::middleware(['auth', 'role:farm_owner'])->prefix('farm-settings')->name('farm-settings.')->group(function () {
    Route::get('/', [FarmSettingsController::class, 'index'])->name('index');
    Route::patch('/', [FarmSettingsController::class, 'update'])->name('update');
    Route::post('/sections', [FarmSettingsController::class, 'storeSection'])->name('sections.store');
    Route::patch('/sections/{section}', [FarmSettingsController::class, 'updateSection'])->name('sections.update');
});
Route::middleware(['auth', 'role:farm_owner'])->prefix('accounts')->name('accounts.')->group(function () {
    Route::get('/', [MonitoringAccountController::class, 'index'])->name('index');
    Route::post('/', [MonitoringAccountController::class, 'store'])->middleware('throttle:10,1')->name('store');
    Route::get('/{account}', [MonitoringAccountController::class, 'show'])->name('show');
    Route::patch('/{account}', [MonitoringAccountController::class, 'update'])->middleware('throttle:20,1')->name('update');
    Route::delete('/{account}', [MonitoringAccountController::class, 'destroy'])->middleware('throttle:10,1')->name('destroy');
});
Route::get('/advisories',AdvisoryController::class)
    ->middleware(['auth', 'role:farm_owner,monitoring_personnel,system_administrator'])->name('advisories');

Route::middleware(['auth', 'role:system_administrator'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/',[AdminController::class,'index'])->name('index');
    Route::post('/users',[AdminController::class,'storeUser'])->name('users.store');
    Route::patch('/users/{user}',[AdminController::class,'updateUser'])->name('users.update');
    Route::post('/diseases',[AdminController::class,'storeDisease'])->name('diseases.store');
    Route::patch('/diseases/{disease}',[AdminController::class,'updateDisease'])->name('diseases.update');
    Route::post('/advisories',[AdminController::class,'storeAdvisory'])->name('advisories.store');
    Route::post('/models',[AdminController::class,'storeModel'])->name('models.store');
});
