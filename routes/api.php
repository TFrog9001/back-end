<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\FieldPriceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\FieldController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\SupplyController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\BookingConversationController;
use App\Http\Controllers\GeneralConversationController;

Route::post('/auth/register', [UserController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/refresh', [AuthController::class, 'refresh']);

Route::post('/zalopay/callback', [PaymentController::class, 'zalopayCallback']);
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);

Route::group([
    // 'middleware' => ['api','auth:api'], 
], function () {

    Route::post('/send-message', [ChatController::class, 'sendMessage']);
    Route::post('/chat/send', [ChatController::class, 'send']);
    Route::group(
        [
            'prefix' => 'auth',
        ],
        function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/check/time', [AuthController::class, 'checkRefreshTokenExpiration']);
            
        }
    );

    // Zalopay
    Route::post('/zalopay', [PaymentController::class, 'createZaloPayOrder']);
    

    Route::group([
        // 'middleware' => ['check.admin', 'check.staff'],
    ], function () {


        Route::group(
            [
                'prefix' => 'users',
            ],
            function () {
                Route::get('', [UserController::class, 'index']);
                Route::get('/customers', [UserController::class, 'getCustomers']);

                Route::get('/{id}', [UserController::class, 'show']);
                Route::post('', [UserController::class, 'addUser']);
                Route::post('/{id}', [UserController::class, 'editUser']);
                Route::delete('/{id}', [UserController::class, 'delete']);
            }
        );

        // Add filed
        Route::group([
            'prefix' => 'fields',
        ], function () {
            Route::get('', [FieldController::class, 'index']);
            Route::get('/{id}', [FieldController::class, 'show']);
            Route::post('', [FieldController::class, 'store']);
            Route::post('/{id}', [FieldController::class, 'update']);
            Route::delete('/{id}', [FieldController::class, 'delete']);
        });

        Route::group([
            'prefix' => 'prices',
        ], function () {
            Route::post('', [FieldPriceController::class, 'store']);
            Route::post('/{id}', [FieldPriceController::class, 'update']);
            Route::delete('/{id}', [FieldPriceController::class, 'delete']);
        });

        Route::group([
            'prefix' => 'bookings',
        ], function () {
            Route::get('', [BookingController::class, 'index']);
            Route::get('/{id}', [BookingController::class, 'show']);
            Route::post('', [BookingController::class, 'store']);
            Route::post('/{id}', [BookingController::class, 'update']);
            Route::delete('/{id}', [BookingController::class, 'delete']);
        });

        Route::group([
            'prefix' => 'equipments',
        ], function () {
            Route::get('', [EquipmentController::class, 'index']);
            Route::post('', [EquipmentController::class, 'store']);
            Route::post('/{id}', [EquipmentController::class, 'update']);
            Route::delete('/{id}', [EquipmentController::class, 'delete']);
            Route::post('equipment/{equipment}/allocate', [EquipmentController::class, 'allocateToField']);
            Route::post('equipment/{equipment}/deallocate', [EquipmentController::class, 'deallocateFromField']);
        });

        Route::group([
            'prefix' => 'supplies',
        ], function () {
            Route::get('', [SupplyController::class, 'index']);
            Route::post('', [SupplyController::class, 'store']);
            Route::post('/{id}', [SupplyController::class, 'update']);
            Route::delete('/{id}', [SupplyController::class, 'delete']);
        });


        Route::group([
            'middleware' => 'check.admin',
        ], function () {
            // Route::resource('/roles', [RoleController::class]);
        });
    });

    Route::group([

    ], function(){
        Route::get('/user-booking/{id}',[BookingController::class, 'getUserBooking']);
    });
});

Route::middleware('auth:api')->group(function () {
    Route::post('/general-conversations', [GeneralConversationController::class, 'store']);
    Route::post('/general-conversations/{conversation}/messages', [GeneralConversationController::class, 'storeMessage']);
    Route::get('/general-conversations/{conversation}/messages', [GeneralConversationController::class, 'getMessages']);
});

Route::middleware('auth:api')->group(function () {
    Route::get('/bookings/{bookingId}/messages', [BookingConversationController::class, 'getMessages']);
    Route::post('/bookings/{bookingId}/messages', [BookingConversationController::class, 'sendMessage']);
});
