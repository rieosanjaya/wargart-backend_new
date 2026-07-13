<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\CitizenController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health/ready', fn () => ApiResponse::success(['status'=>'ready','database'=>\Illuminate\Support\Facades\DB::select('SELECT 1')[0] ? 'up':'down']));
    Route::get('/public/media/{id}',[MediaController::class,'publicNews'])->whereNumber('id');
    Route::get('/public/letters/{publicId}/file',[MediaController::class,'publicLetter']);

    Route::prefix('auth')->middleware('throttle:10,1')->group(function () {
        Route::post('/register', [AuthController::class,'register']);
        Route::post('/login', [AuthController::class,'login']);
        Route::post('/desktop-login', [AuthController::class,'desktopLogin']);
        Route::post('/desktop-register', [AuthController::class,'desktopRegister']);
        Route::post('/desktop-username-check', [AuthController::class,'desktopUsernameCheck']);
        Route::post('/desktop-link-resident', [AuthController::class,'desktopLinkResident']);
        Route::post('/desktop-residents', [AuthController::class,'desktopResidentCreate']);
        Route::get('/desktop-residents', [AuthController::class,'desktopResidents']);
        Route::post('/otp/resend', [AuthController::class,'resend']);
        Route::post('/otp/verify', [AuthController::class,'verify']);
        Route::post('/refresh', [AuthController::class,'refresh']);
    });

    Route::middleware('api.auth')->group(function () {
        Route::get('/auth/me',[AuthController::class,'me']);
        Route::post('/auth/logout',[AuthController::class,'logout']);
        Route::post('/auth/logout-all',[AuthController::class,'logoutAll']);
        Route::get('/residents/search',[CitizenController::class,'searchResident'])->middleware('throttle:30,1');
        Route::get('/news',[CitizenController::class,'news']);
        Route::get('/news/{id}',[CitizenController::class,'newsShow'])->whereNumber('id');
        Route::get('/media/{id}',[MediaController::class,'download'])->whereNumber('id');

        Route::middleware('role:CITIZEN')->group(function () {
            Route::get('/me/profile',[CitizenController::class,'profile']);
            Route::patch('/me/profile',[CitizenController::class,'updateProfile']);
            Route::post('/me/resident-profile',[CitizenController::class,'residentProfileCreate']);
            Route::post('/me/resident-link',[CitizenController::class,'linkResident']);
            Route::get('/me/household',[CitizenController::class,'household']);
            Route::post('/me/media',[CitizenController::class,'mediaUpload']);
            Route::get('/me/letter-requests',[CitizenController::class,'letters']);
            Route::post('/me/letter-requests',[CitizenController::class,'letterCreate']);
            Route::get('/me/letter-requests/{id}',[CitizenController::class,'letterShow']);
            Route::get('/me/dues-bills',[CitizenController::class,'bills']);
            Route::get('/me/notifications',[CitizenController::class,'notifications']);
            Route::post('/me/notifications/{id}/read',[CitizenController::class,'notificationRead']);
            Route::post('/me/chat-sessions',[ChatController::class,'start']);
            Route::get('/me/chat-sessions/current',[ChatController::class,'current']);
        });

        Route::get('/chat-sessions/{id}/messages',[ChatController::class,'messages']);
        Route::post('/chat-sessions/{id}/messages',[ChatController::class,'send'])->middleware('throttle:60,1');
        Route::post('/chat-sessions/{id}/close',[ChatController::class,'close']);

        Route::prefix('admin')->middleware('role:ADMIN')->group(function () {
            Route::get('/dashboard',[AdminController::class,'dashboard']);
            Route::get('/residents',[AdminController::class,'residents']);
            Route::post('/residents',[AdminController::class,'residentCreate']);
            Route::get('/residents/{id}',[AdminController::class,'residentShow'])->whereNumber('id');
            Route::patch('/residents/{id}',[AdminController::class,'residentUpdate'])->whereNumber('id');
            Route::delete('/residents/{id}',[AdminController::class,'residentDelete'])->whereNumber('id');
            Route::post('/users/link-resident',[AdminController::class,'userLinkResident']);
            Route::get('/households/{id}',[AdminController::class,'household'])->whereNumber('id');
            Route::post('/households',[AdminController::class,'householdCreate']);
            Route::patch('/households/{id}',[AdminController::class,'householdUpdate'])->whereNumber('id');
            Route::get('/news',[AdminController::class,'news']);
            Route::post('/news',[AdminController::class,'newsCreate']);
            Route::patch('/news/{id}',[AdminController::class,'newsUpdate'])->whereNumber('id');
            Route::delete('/news/{id}',[AdminController::class,'newsDelete'])->whereNumber('id');
            Route::post('/news/{id}/publish',[AdminController::class,'newsPublish'])->whereNumber('id');
            Route::get('/letter-requests',[AdminController::class,'letters']);
            Route::get('/letter-requests/{id}',[AdminController::class,'letterShow']);
            Route::post('/letter-requests/{id}/{action}',fn($id,$action,\Illuminate\Http\Request $r)=>app(AdminController::class)->letterTransition($r,$id,$action))->whereIn('action',['review','approve','reject','complete']);
            Route::get('/billing-periods',[AdminController::class,'periods']);
            Route::post('/billing-periods',[AdminController::class,'periodCreate']);
            Route::post('/billing-periods/{id}/publish',[AdminController::class,'periodPublish'])->whereNumber('id');
            Route::get('/dues-bills',[AdminController::class,'bills']);
            Route::post('/dues-bills/legacy-generate',[AdminController::class,'legacyGenerateBills']);
            Route::post('/dues-bills/{id}/{action}',fn($id,$action,\Illuminate\Http\Request $r)=>app(AdminController::class)->billStatus($r,$id,$action))->whereIn('action',['mark-paid','mark-unpaid']);
            Route::post('/dues-bills/notify',[AdminController::class,'notifyBills']);
            Route::post('/notifications/legacy',[AdminController::class,'legacyNotification']);
            Route::post('/media',[AdminController::class,'mediaUpload']);
            Route::get('/chat-sessions',[ChatController::class,'adminSessions']);
            Route::post('/chat-sessions/{id}/assign',[ChatController::class,'assign']);
            Route::patch('/presence',[ChatController::class,'presence']);
        });
    });
});
