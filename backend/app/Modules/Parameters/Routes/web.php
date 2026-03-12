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

Route::prefix('parameters')->group(function() {
    Route::get('/', 'ParametersController@index');
});

Route::group(['prefix' => 'parameters'], function () {
    Route::group(['middleware' => ['web']], function () {
        Route::post('addParam', 'ParametersController@saveParamCommonData');
        Route::post('saveschoolBankdetails', 'ParametersController@saveschoolBankdetails');
        Route::get('getParam/{model}', 'ParametersController@getParam');
        Route::get('getParamDistricts', 'ParametersController@getDistricts');
        Route::get('getParamConstituencies', 'ParametersController@getConstituencies');
        Route::get('getParamWards', 'ParametersController@getWards');
        Route::get('getParamsAccCwac', 'ParametersController@getAccCwac');
        Route::get('getHouseholds', 'ParametersController@getHouseholds');
        Route::post('deleteRecord', 'ParametersController@deleteParamRecord');
        Route::get('getSchoolinfoParam', 'ParametersController@getSchoolinfoParam');
        Route::get('getFieldTeamProvinces', 'ParametersController@getFieldTeamProvinces');
        Route::get('getFieldTeamMembers', 'ParametersController@getFieldTeamMembers');
        Route::get('getKgsDistricts', 'ParametersController@getKgsDistricts');
        Route::post('addSchoolTerm', 'ParametersController@addSchoolTerm');
        Route::get('getUserAssignedDistricts', 'ParametersController@getUserAssignedDistricts');
        Route::get('getJustSchools', 'ParametersController@getJustSchools');
        Route::get('getSchoolsAndRelated', 'ParametersController@getSchoolsAndRelated');
        Route::get('getJustBeneficiarySchools', 'ParametersController@getJustBeneficiarySchools');
        Route::get('getSchoolsWithFeesDisbursement', 'ParametersController@getSchoolsWithFeesDisbursement');
        Route::get('getSchoolsWithPayVerBatches', 'ParametersController@getSchoolsWithPayVerBatches');
        Route::get('getSchoolBankInformation', 'ParametersController@getSchoolBankInformation');
        Route::post('saveSchoolBankInformation', 'ParametersController@saveSchoolBankInformation');
        Route::get('getCommonParamFromTable', 'ParametersController@getCommonParamFromTable');
        //hiram
        Route::get('getSchoolinfoParam', 'ParametersController@getSchoolinfoParam');
        Route::get('getpaymentschool_informationstr', 'ParametersController@getpaymentschool_informationstr');
        Route::get('getSchooltypesParam', 'ParametersController@getSchooltypesParam');
        Route::get('getSchool_termsParam', 'ParametersController@getSchool_termsParam');
        Route::get('getSchoolgradeParam', 'ParametersController@getSchoolgradeParam');
        Route::get('getBank_detailParams', 'ParametersController@getBank_detailParams');
        Route::get('getBankbranch_detailParams', 'ParametersController@getBankbranch_detailParams');
        Route::get('getSchool_contactpersonParams', 'ParametersController@getSchool_contactpersonParams');
        Route::get('getSchool_designation', 'ParametersController@getSchool_designation');
        Route::get('getSchool_feessetup', 'ParametersController@getSchool_feessetup');
        Route::get('getSchool_feesdata', 'ParametersController@getSchool_feesdata');
        Route::get('getParamCase_types', 'ParametersController@getSchool_feesdata');
        Route::post('saveSchool_feesdata', 'ParametersController@saveSchool_feesdata');
        Route::get('getpayment_verificationstatus', 'ParametersController@getpayment_verificationstatus');
        Route::get('getSchool_termDaysParam', 'ParametersController@getSchool_termDaysParam');
        Route::get('getSchool_typeenrollment_setup', 'ParametersController@getSchool_typeenrollment_setup');
        Route::get('getPayment_validation_rules', 'ParametersController@getPayment_validation_rules');
        Route::get('getbeneficiary_enrollementstatusstr', 'ParametersController@getbeneficiary_enrollementstatusstr');
        Route::get('getBeneficiarySearchstr', 'ParametersController@getBeneficiarySearchstr');
        //end hiram
        Route::post('exportParamsRecords', 'ParametersController@exportParamsRecords');
        //frank
        Route::get('getCwacDropdowns', 'ParametersController@getCwacDropdowns');
        Route::get('getProvinces', 'ParametersController@getProvinces');
        Route::post('saveSchoolBankApprovalInfo', 'ParametersController@saveSchoolBankApprovalInfo');
        // batch transfer routes
        Route::get('getBatchTransferRecords', 'ParametersController@getBatchTransferRecords');
        Route::post('processBatchTransfer', 'ParametersController@processBatchTransfer');
    });
});

Route::group(['prefix' => 'mobile_params'], function () {
    Route::get('getMobileParams', 'ParametersController@getMobileParams');
    Route::post('syncMobileInfo', 'ParametersController@syncMobileInfo');
    Route::get('getSyncedVerificationData', 'ParametersController@getSyncedVerificationData');
    Route::get('getSyncedUploadData', 'ParametersController@getSyncedUploadData');
    Route::post('getOfflineAbsentGirlsbatchinfo', 'ParametersController@getOfflineAbsentGirlsbatchinfo');
    Route::post('syncEnrollmentInfo', 'ParametersController@syncEnrollmentInfo');
    Route::post('SyncGrmFormsMobile', 'ParametersController@SyncGrmFormsMobile');
    Route::get('fetch-mis-users', 'ParametersController@getUsersForApp');
    Route::get('update-mis-users', 'ParametersController@updateUsersForApp');
});