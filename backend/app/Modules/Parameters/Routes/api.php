<?php

use Illuminate\Http\Request;
use App\Modules\Parameters\Http\Controllers\ParametersController;

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


Route::post('mobile_params/syncEnrollmentInfo', [ParametersController::class, 'syncEnrollmentInfo']);
Route::get('mobile-params/fetch-mis-users', [ParametersController::class, 'getUsersForApp']);
Route::post('mobile-params/update-mis-users', [ParametersController::class, 'updateUsersForApp']);
Route::get('mobile-params/verify-all-girls', [ParametersController::class, 'verifyAllUnverifiedFromMobile']);
Route::get('mobile-params/get-cwacs-enrollment-mobile', [ParametersController::class, 'getCwacsForEnrollmentsMobile']);
Route::get('mobile-params/get-statistics-enrollment-mobile', [ParametersController::class, 'getEnrollmentStatisticsMobile']);
Route::post('mobile-params/process-transfers', [ParametersController::class, 'processTransfersEnterprise']);
Route::post('mobile-params/mark-verified', [ParametersController::class, 'markChecklistVerified']);
Route::post('mobile-params/reset-verified', [ParametersController::class, 'resetChecklistVerification']);
Route::get('mobile-params/convert-to-file', [ParametersController::class, 'convertExistingImages']);
Route::get('mobile-params/normalize-paths', [ParametersController::class, 'normalizeImagePaths']);
Route::get('mobile-params/convert-all', [ParametersController::class, 'convertAllExistingImages']);
Route::get('mobile-params/convert-beneficiaries', [ParametersController::class, 'convertBeneficiaryImages']);
