<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MobileController;

/*
|--------------------------------------------------------------------------
| API Routes for Mobile Module
|--------------------------------------------------------------------------
|
| These routes are loaded by the RouteServiceProvider. We add a prefix
| so all routes automatically use /api/zispis/v1 as the base.
|
*/

Route::prefix('zispis/v1')->group(function () {


    //test
    Route::get('/test-live', function () {
        return 'pg api is up and running';
    });

    Route::post('/test-me', 'MobileController@testing');
    Route::post('/login', 'MobileController@login');
    Route::get('/beneficiaries', 'MobileController@getBeneficiariesBySchool');
    Route::post('/beneficiary-transaction-status', 'MobileController@sendPaymentStatuses');
    Route::post('/submit-deposit-slip', 'MobileController@submitDepositSlip');
    Route::post('/beneficiary-images', 'MobileController@beneficiaryImages');
    Route::get('/test-pg-login', 'MobileController@testPgLogin');
    Route::get('/generate-transaction-ids', 'MobileController@generateTransactionIds');
    // Route::post('/add-school-to-list/{schoolId}', [MobileController::class, 'addSchoolToPaymentList'])
    // ->whereNumber('schoolId');
    Route::get('/assign-school-to-user', 'MobileController@assignSchoolListToUser');
    Route::get('/get-payment-schools', 'MobileController@getPaymentPhaseSchools');
    Route::get('/add-bens-to-school', 'MobileController@addPhase2BenToPaymentList');
    Route::get('/add-bens-to-school-2', 'MobileController@addPh2BenFromConsolidatedList');

    //payment module apis
    Route::get('/payments-summary', 'MobileController@getPaymentSummaries');
    Route::get('/payment-phases', 'MobileController@getPaymentPhases');
    Route::get('/payment-phase-schools', 'MobileController@getPhaseSchools');
    Route::get('/payment-beneficiaries', 'MobileController@getPaymentBeneficiaries');
    Route::post('/submit-panel-a', 'MobileController@submitToPanelA');
    Route::post('/submit-panel-b', 'MobileController@submitToPanelB');
    Route::post('/panelb-approval', 'MobileController@PanelBApproval');
    Route::get('/pg-coordinators', 'MobileController@getPGCoordinators');

    // pg
    Route::post('/pg/submit-payment-to-pg', 'MobileController@submitPaymentToPGDebug');
    Route::get('/kgs/payments/generate-payload', 'MobileController@buildPaymentPayload');
    Route::get('/kgs/payments/generate-sch-payload', 'MobileController@buildPaymentPayloadSchools');
    Route::post('/pg/submit-single-payment-to-pg', 'MobileController@submitSinglePaymentToPG');
    Route::get('/kgs/payments/generate-sch-payload1', 'MobileController@buildSingleSchoolPayload1');
    Route::get('/regen-tid', 'MobileController@regenerateTransactionIds');

    Route::get('/pg/transactions', 'MobileController@pgLogsList');
    Route::get('/pg/transactions/{transaction_id}', 'MobileController@pgLogsDetails');
    Route::get('/pg/failed-payments', 'MobileController@getFailedPayments');

    Route::post('/pg/retry-one-payment', 'MobileController@retrySingleSchoolPayment');

    Route::post('/processAllSchoolsForPG', 'MobileController@processAllSchoolsForPG');

    Route::get('/sa-trans-summary', 'MobileController@getSchoolTransactionSummary');
    Route::get('/trans-beneficiary-images', 'MobileController@getImages');

});