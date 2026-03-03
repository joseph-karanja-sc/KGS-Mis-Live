<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NewPaymentModuleController;

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
// Route::prefix('payments/v1')->group(function () {

//test
Route::get('/test-live', function () {return 'test is ok';});
Route::get('/test', [NewPaymentModuleController::class, 'testing']);
Route::get('/payments-summary', [NewPaymentModuleController::class, 'getPaymentSummaries']);
Route::get('/payment-phases', [NewPaymentModuleController::class, 'getPaymentPhases']);
Route::get('/payment-phase-schools', [NewPaymentModuleController::class, 'getPhaseSchools']);
Route::get('/payment-beneficiaries', [NewPaymentModuleController::class, 'getPaymentBeneficiaries']);
// });