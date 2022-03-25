<?php

use App\Http\Controllers\TestController;
use Bpjs\Bridging\GenerateBpjs as BridgingGenerateBpjs;
use Illuminate\Support\Facades\Route;

use Symfony\Component\HttpFoundation\Request;
use Vclaim\Bridging\GenerateBpjs;
use Vclaim\Bridging\PesertaController;
use Bpjs\Bridging\ReferensiController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});



Route::get('sample', function() {
	$generate = new BridgingGenerateBpjs;
	return $generate->generateSignature(config("bpjs.api.consid"), config("bpjs.api.seckey"));
});

Route::get('referensi/diagnosa/{kode}', [TestController::class, 'getDiagnosa']);
Route::get('ref/poli', [TestController::class, 'getPoli']);
