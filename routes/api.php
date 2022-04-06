<?php

use App\Http\Controllers\TestController;
use App\Http\Controllers\WS_BPJS\BPJSController;
use App\Http\Controllers\WS_RS\SPHController;
use Illuminate\Http\Request;
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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/** WS BPJS Route
 *  Digunakan untuk mengirim data antrean SPH ke BPJS
 */
Route::post('v2/antrean_add', [BPJSController::class, 'postTambahAntrean']);

/**
 * WS RS
 * Digunakan untuk menerima data dari BPJS
 */
Route::post('v2/jadwal_operasi_rs', [SPHController::class, 'getJadwalOperasi']);
Route::post('v2/jadwal_operasi_pasien', [SPHController::class, 'getJadwalOperasiPasien']);

Route::post('v2/ambil_antrian', [SPHController::class, 'getAntrean']);


/** Section Test */
// Route::post('post_tambah', [TestController::class, 'postTambahAntrean']);
// Route::post('update_waktu', [TestController::class, 'updateWaktuAntrean']);
