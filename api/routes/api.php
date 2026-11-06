<?php

use App\Http\Controllers\QuoteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/health', fn () => response()->json(['status' => 'ok']));
Route::post('/quotes', [QuoteController::class, 'store']);
Route::post('/quotes/batch', [QuoteController::class, 'batch']);
Route::get('/quotes', [QuoteController::class, 'index']);
Route::get('/quotes/{id}', [QuoteController::class, 'show']);
