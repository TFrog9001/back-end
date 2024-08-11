<?php

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


Route::group([
    'middleware' => 'api',
], function () {

    Route::post('/register', [UserController::class, 'register']);

    Route::group(
        [
            'prefix' => 'auth',
        ],
        function () {
            Route::post('/login', [AuthController::class, 'login']);
            Route::post('/refresh', [AuthController::class, 'refresh']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/check/time', [AuthController::class, 'checkRefreshTokenExpiration']);
            Route::get('google', [AuthController::class, 'redirectToGoogle']);
            Route::get('google/callback', [AuthController::class, 'handleGoogleCallback']);
        }
    );

    Route::group([
        // 'middleware' => 'check.admin.staff',
    ], function () {

        Route::group(
            [
                'prefix' => 'users',
            ],
            function () {
                Route::get('', [UserController::class, 'index']);
                Route::get('/all', [UserController::class, 'getAll']);
                Route::get('/{id}', [UserController::class, 'show']);
            }
        );

        // Add filed
        Route::group([
            'prefix' => 'fields',
        ], function () {
            Route::get('', [FieldController::class, 'index']);
            Route::post('', [FieldController::class, 'store']);
            Route::post('/{id}', [FieldController::class, 'update']);
            Route::delete('/{id}', [FieldController::class, 'delete']);
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
            'prefix'=> 'supplies',
        ], function(){
            Route::get('', [SupplyController::class, 'index']);
            Route::post('', [SupplyController::class, 'store']);
            Route::post('/{id}', [SupplyController::class, 'update']);
            Route::delete('/{id}', [SupplyController::class, 'delete']);
        });


        Route::group([
            'middleware' => 'isAdmin',
        ], function () {
            // Route::resource('/roles', [RoleController::class]);
        });
    });
});
