<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ApostasFeitasController;
use App\Http\Controllers\LiveStreamController;
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
    Route::get('/aprove-prize', [PartnerController::class, 'aprovePrize']);
    Route::get('/get-result', [PartnerController::class, 'getResultInMultiplePartners']);
    Route::get('/get-result2', [PartnerController::class, 'distributePrizes']);
    Route::post('/get-result3', [PartnerController::class, 'distributePrizes3']);
    Route::get('/list-competitions', [PartnerController::class, 'listCompetitions']);
    Route::delete('/delete-competition', [PartnerController::class, 'deleteCompetition']);
    Route::get('/financeiro', [PartnerController::class, 'Financial']);
    Route::get('/bichao-results', [PartnerController::class, 'getResultsBichao']);
    Route::get('/bichao-results2', [PartnerController::class, 'getResultsBichao2']);
    Route::get('/get-result2-bichao', [PartnerController::class, 'distributePrizesBichao']);
    Route::put('/update-status', [PartnerController::class, 'updateStatus']);
    Route::post('/pdf', [PartnerController::class, 'gerarPDF']);
    Route::post('/winners-lists', [PartnerController::class, 'storeListWinners']);
    Route::get('/winners-lists', [PartnerController::class, 'getWinners']);  // Lista das bancas!!

    Route::put('/update-status-bichao', [PartnerController::class, 'updateStatusBichao']);
    Route::post('/update-draw-numbers', [PartnerController::class, 'updateDrawNumbers']);
    Route::get('/modalidades/{partnerId}', [PartnerController::class, 'type_games'])->name('modalidades');

    Route::get('/auto-aprovation', [PartnerController::class, 'getPartnersToPaytmentManagment']);
    Route::post('/auto-aprovation/save/{banca_id}', [PartnerController::class, 'saveValorToAutoAprovation']);
    Route::post('/auto-aprovation/pdf', [PartnerController::class, 'gerarPDFAutoAprovacoes']);
    Route::get('/game-informations', [PartnerController::class, 'gameInformations']);
});

Route::get('/auto-aprovation/corrigir-saldos', [PartnerController::class, 'corrigirSaldos']);

Route::post('/apostas-feitas', [ApostasFeitasController::class, 'store']);
Route::get('/percentes/{numberOfWinners}', [PartnerController::class, 'generatePercentages']);

Route::get('/channel-id-live', [LiveStreamController::class, 'channelIdLive']); // PEGA LIVE STREAM

Route::get('/winners-list', [PartnerController::class, 'getWinnersListByBancaAndDate']); // Traz a lista dos objetos!! Já tenho a função
Route::get('/winners-list2', [PartnerController::class, 'getWinnersListByBancaAndHours']); // Traz a lista dos objetos!! Já tenho a função

Route::get('/copia-e-cola', [PartnerController::class, 'formatTableContentFromRequest']); //Traz a lista para conpiar e colar

Route::get('/system', [PartnerController::class, 'processarParceiros']);
Route::get('/modalidades/{partnerId}', [PartnerController::class, 'type_games']);
