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
use App\Modules\SchoolManagement\Http\Controllers\SchoolManagementController;

Route::prefix('schoolmanagement')->group(function() {
    Route::get('/', 'SchoolManagementController@index');
});

Route::group(['prefix' => 'schoolmanagement'], function () {
    Route::group(['middleware' => ['web']], function () {
        // School bank info
        Route::get('schools_bank_information', 'SchoolManagementController@schoolsBankInformation');
        Route::get('schools-bank-list',          [SchoolManagementController::class, 'schoolsBankList']);
        Route::get('school-bank-info/{schoolId}',[SchoolManagementController::class, 'getSchoolBankInfo']);
        Route::get('bank-branches/{bankId}',     [SchoolManagementController::class, 'getBankBranches']);
        Route::post('update-school-bank',        [SchoolManagementController::class, 'updateSchoolBank']);
        Route::delete('delete-school-bank/{school_id}', [SchoolManagementController::class, 'deleteSchoolBank']);
        Route::post('create-branch', [SchoolManagementController::class, 'createBranch']);
        Route::put('update-branch', [SchoolManagementController::class, 'updateBranch']);
        Route::get('branch-details/{branchId}', [SchoolManagementController::class, 'getBranchDetails']);

        // School Consolidation Routes
        Route::get('school-consolidation', [SchoolManagementController::class, 'schoolConsolidation']);
        Route::get('schools-for-consolidation', [SchoolManagementController::class, 'getSchoolsForConsolidation']);
        Route::get('school-details/{schoolId}', [SchoolManagementController::class, 'getSchoolDetails']);
        Route::post('update-school-details', [SchoolManagementController::class, 'updateSchoolDetails']);
        Route::post('consolidate-schools', [SchoolManagementController::class, 'consolidateSchools']);
        
        Route::post('saveCommonData', 'SchoolManagementController@saveCommonData');
        Route::get('getMainCapacityAssessments', 'SchoolManagementController@getMainCapacityAssessments');
        Route::get('getCapacityAssessmentDetails', 'SchoolManagementController@getCapacityAssessmentDetails');
        Route::get('getClassroomMaxDetails', 'SchoolManagementController@getClassroomMaxDetails');
        Route::post('updateSchoolCapacityInfo', 'SchoolManagementController@updateSchoolCapacityInfo');
        Route::get('getSchoolManagementParam', 'SchoolManagementController@getSchoolManagementParam');
        Route::post('saveMonitoringDetails', 'SchoolManagementController@saveMonitoringDetails');
        Route::post('saveSchoolDetails', 'SchoolManagementController@saveSchoolDetails');
        Route::post('saveEducationQualityDetails', 'SchoolManagementController@saveEducationQualityDetails');
        Route::get('getSchMonitoringInspectors', 'SchoolManagementController@getSchMonitoringInspectors');
        Route::get('getSchoolMonitoringReports', 'SchoolManagementController@getSchoolMonitoringReports');
        Route::get('getSchoolMonitoringBeneficiaries', 'SchoolManagementController@getSchoolMonitoringBeneficiaries');
        Route::get('getSchMonitoringMissingGirls', 'SchoolManagementController@getSchMonitoringMissingGirls');
        Route::post('addMissingBeneficiaries', 'SchoolManagementController@addMissingBeneficiaries');
        Route::post('removeGirlFromMissingBeneficiariesList', 'SchoolManagementController@removeGirlFromMissingBeneficiariesList');
        Route::post('submitMonitoringToDiffStage', 'SchoolManagementController@submitMonitoringToDiffStage');
        Route::post('saveMonitoringBeneficiaryDetails', 'SchoolManagementController@saveMonitoringBeneficiaryDetails');
        Route::get('getSchMonitoringSummary', 'SchoolManagementController@getSchMonitoringSummary');
        Route::get('getSchoolMonitoringVerifiedGirls', 'SchoolManagementController@getSchoolMonitoringVerifiedGirls');
        Route::post('saveVerifiedGirlsDetails', 'SchoolManagementController@saveVerifiedGirlsDetails');
        Route::get('getCurrentlyEnrolledGirls', 'SchoolManagementController@getCurrentlyEnrolledGirls');
        Route::get('getSchoolsForMonitoring', 'SchoolManagementController@getSchoolsForMonitoring');
        Route::get('getRecommendationMonitoringList', 'SchoolManagementController@getRecommendationMonitoringList');
        Route::post('removeMonitoringInspector', 'SchoolManagementController@removeMonitoringInspector');
        Route::get('getSchoolMonitoringRegister', 'SchoolManagementController@getSchoolMonitoringRegister');
        Route::post('updateMonitoringRegister', 'SchoolManagementController@updateMonitoringRegister');
        Route::post('sendSuspensionRequest', 'SchoolManagementController@sendSuspensionRequest');
        Route::get('getMonitoringTransitionalStages', 'SchoolManagementController@getMonitoringTransitionalStages');
        Route::get('getPlannerDistricts', 'SchoolManagementController@getPlannerDistricts');
        Route::get('getPlannerSchools', 'SchoolManagementController@getPlannerSchools');
        Route::get('getPlannerExternalBeneficiaries', 'SchoolManagementController@getPlannerExternalBeneficiaries');
        Route::get('getPlannerSchoolPayments', 'SchoolManagementController@getPlannerSchoolPayments');
        //hirams
        Route::get('getSchools_infomanagementStr', 'SchoolManagementController@getSchools_infomanagementStr');
        Route::get('getSchooldistrict_summaryStr', 'SchoolManagementController@getSchooldistrict_summaryStr');
        Route::get('getSchool_benficiariesinfoStr', 'SchoolManagementController@getSchool_benficiariesinfoStr');
        Route::get('getBeneficiarySchEnrollmentinfoStr', 'SchoolManagementController@getBeneficiarySchEnrollmentinfoStr');
        Route::get('getSchoolfeesdisbursementinfstr', 'SchoolManagementController@getSchoolfeesdisbursementinfstr');

    });
});

