<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\BotManController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/thank', function () {
    return view('thank');
});


use BotMan\BotMan\BotMan;
use BotMan\BotMan\Drivers\DriverManager;

Route::match(['get', 'post'], '/botman', [BotManController::class, 'handle']);

Route::get('/botman', function () {
    return view('chatbot');
});


