<?php

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
use Illuminate\Support\Facades\Route;

Route::prefix('frontoffice')->group(function() {
    Route::get('/', 'FrontOfficeController@index');
});

Route::group(['prefix' => 'frontoffice'], function () {
    Route::group(['middleware' => ['web']], function () {
        Route::get('getPaymentVerificationEnquiries', 'FrontOfficeController@getPaymentVerificationEnquiries');
        Route::get('getPaymentApprovalEnquiries', 'FrontOfficeController@getPaymentApprovalEnquiries');
        Route::get('getPaymentBatchesTransitionalStages', 'FrontOfficeController@getPaymentBatchesTransitionalStages');
        Route::get('getBeneficiaryEnrollmentEnquiries', 'FrontOfficeController@getBeneficiaryEnrollmentEnquiries');
        Route::get('getBeneficiaryDisabilitytEnquiries', 'FrontOfficeController@getBeneficiaryDisabilitytEnquiries');
        Route::post('exportFrontOfficeRecords', 'FrontOfficeController@exportFrontOfficeRecords');
        Route::get('getFrontOfficeParamFromTable', 'FrontOfficeController@getFrontOfficeParamFromTable');
    });
});
