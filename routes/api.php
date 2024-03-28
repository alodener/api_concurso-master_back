<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ApostasFeitasController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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



Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::middleware('auth')->prefix('apostas-feitas')->group(function () {
   Route::post('/show', [ApostasFeitasController::class, 'filter']);
});

Route::middleware('auth')->prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'index']);
    Route::get('/{id}', [UserController::class, 'show']);
    Route::put('/{id}', [UserController::class, 'update']);
    Route::post('/', [UserController::class, 'store']);
    Route::delete('/{id}', [UserController::class, 'destroy']);
});

Route::middleware('auth')->prefix('logs')->group(function () {
    Route::get('/', [LogController::class, 'index']);
});

Route::middleware('auth')->prefix('partners')->group(function () {
    Route::get('/', [PartnerController::class, 'index']);
    Route::post('/', [PartnerController::class, 'createGameInMultiplePartners']);
    Route::post('/send-result', [PartnerController::class, 'sendResultInMultiplePartners']);
    Route::get('/get-result', [PartnerController::class, 'getResultInMultiplePartners']);
    Route::get('/aprove-prize', [PartnerController::class, 'aprovePrize']);
    Route::get('/get-result2', [PartnerController::class, 'distributePrizes']);
    Route::get('/list-competitions', [PartnerController::class, 'listCompetitions']);
    Route::delete('/delete-competition', [PartnerController::class, 'deleteCompetition']);
    Route::get('/financeiro', [PartnerController::class, 'Financial']);
    Route::get('/bichao-results', [PartnerController::class, 'getResultsBichao']);
    Route::get('/get-result2-bichao', [PartnerController::class, 'distributePrizesBichao']);
    Route::put('/update-status', [PartnerController::class, 'updateStatus']);
    Route::post('/pdf', [PartnerController::class, 'gerarPDF']);
    Route::put('/update-status-bichao', [PartnerController::class, 'updateStatusBichao']);
    Route::post('/update-draw-numbers', [PartnerController::class, 'updateDrawNumbers']);

});

Route::post('/apostas-feitas', [ApostasFeitasController::class, 'store']);
Route::get('/percentes/{numberOfWinners}', [PartnerController::class, 'generatePercentages']);
Route::get('/pdf', [PartnerController::class, 'gerarPDF']);
