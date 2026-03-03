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

Route::prefix('benbatchmanagement')->group(function () {
    Route::get('/', 'BenBatchManagementController@index');
});

Route::group(['prefix' => 'benbatchmanagement'], function () {
    Route::group(['middleware' => ['web']], function () {
        Route::get('getBenBatchParam/{model}', 'BenBatchManagementController@getBenBatchParam');
        Route::post('deleteRecord', 'BenBatchManagementController@deleteBenBatchRecord');
        Route::post('saveCommonData', 'BenBatchManagementController@saveBenBatchCommonData');
        Route::get('getBeneficiaries', 'BenBatchManagementController@getBeneficiaries');
        Route::get('getBeneficiariesForPromotion', 'BenBatchManagementController@getBeneficiariesForPromotion');
        Route::get('getBeneficiariesForPromotionApprovals', 'BenBatchManagementController@getBeneficiariesForPromotionApprovals');
        Route::get('getGradeNinesPromotionLogs', 'BenBatchManagementController@getGradeNinesPromotionLogs');
        Route::get('getAllImportedDataset', 'BenBatchManagementController@getAllImportedDataset');
        Route::get('getBatchTransitionalStages', 'BenBatchManagementController@getBatchTransitionalStages');
        Route::get('getAssessmentFilteredInDataSet', 'BenBatchManagementController@getAssessmentFilteredInDataSet');
        Route::get('getAssessmentFilteredOutRecords', 'BenBatchManagementController@getAssessmentFilteredOutRecords');
        Route::get('getAssessmentSummary', 'BenBatchManagementController@getAssessmentSummary');
        Route::get('getMappingSummary', 'BenBatchManagementController@getMappingSummary');
        Route::get('getMappingRecords', 'BenBatchManagementController@getMappingRecords');
        Route::get('getVerificationSummary', 'BenBatchManagementController@getVerificationSummary');
        Route::get('getVerificationRecords', 'BenBatchManagementController@getVerificationRecords');
        Route::get('getVerificationFollowupsRecords', 'BenBatchManagementController@getVerificationFollowupsRecords');
        Route::post('saveBeneficiaryImage', 'BenBatchManagementController@saveBeneficiaryImage');
        Route::get('getBeneficiaryAdditionalInfo', 'BenBatchManagementController@getBeneficiaryAdditionalInfo');
        Route::post('updateGirlInformation', 'BenBatchManagementController@updateGirlInformation');
        Route::get('getBeneficiaryUpdateHistory', 'BenBatchManagementController@getBeneficiaryUpdateHistory');
        Route::get('getBenPerformance', 'BenBatchManagementController@getBenPerformance');
        Route::post('saveBeneficiaryPerformance', 'BenBatchManagementController@saveBeneficiaryPerformance');
        Route::post('getSpecificBeneficiaryPerformance', 'BenBatchManagementController@getSpecificBeneficiaryPerformance');
        Route::get('getBeneficiaryStatuses', 'BenBatchManagementController@getBeneficiaryStatuses');
        Route::post('saveBenStatusChanges', 'BenBatchManagementController@saveBenStatusChanges');
        Route::get('getBeneficiaryStatusesHistory', 'BenBatchManagementController@getBeneficiaryStatusesHistory');
        Route::get('getBeneficiaryEnrollments', 'BenBatchManagementController@getBeneficiaryEnrollments');
        Route::get('getBeneficiaryPaymentDetails', 'BenBatchManagementController@getBeneficiaryPaymentDetails');
        Route::get('getPaymentReceipt', 'BenBatchManagementController@getPaymentReceipt');
        Route::get('getBeneficiaryCases', 'BenBatchManagementController@getBeneficiaryCases');
        Route::get('getBeneficiaryCases', 'BenBatchManagementController@getBeneficiaryCases');
        Route::post('getBatchFolderID', 'BenBatchManagementController@getBatchFolderID');
        Route::post('getBeneficiaryFolderID', 'BenBatchManagementController@getBeneficiaryFolderID');
        Route::post('getBeneficiariesSubModulesDMSFolderID', 'BenBatchManagementController@getBeneficiariesSubModulesDMSFolderID');
        Route::post('getExaminationSchoolInfo', 'BenBatchManagementController@getExaminationSchoolInfo');
        Route::post('getGirlPromotionInfo', 'BenBatchManagementController@getGirlPromotionInfo');
        Route::post('savePromotionDetails', 'BenBatchManagementController@savePromotionDetails');
        Route::post('gradeNinePromotionTransitioning', 'BenBatchManagementController@gradeNinePromotionTransitioning');
        Route::post('selectedGradeNinePromotionTransitioning', 'BenBatchManagementController@selectedGradeNinePromotionTransitioning');
        Route::post('approveGradeNineBeneficiary', 'BenBatchManagementController@approveGradeNineBeneficiary');
        Route::post('selectedGradeNinePromotionApproval', 'BenBatchManagementController@selectedGradeNinePromotionApproval');
        Route::get('getSchoolMatchingGeneralSummary', 'BenBatchManagementController@getSchoolMatchingGeneralSummary');
        Route::get('getSchMatchingUserSummary', 'BenBatchManagementController@getSchMatchingUserSummary');
        Route::get('getSchoolMatchingSummaryTotals', 'BenBatchManagementController@getSchoolMatchingSummaryTotals');
        Route::get('getSchoolMatchedGirls', 'BenBatchManagementController@getSchoolMatchedGirls');
        Route::get('getUnMatchedGirls', 'BenBatchManagementController@getUnMatchedGirls');
        Route::get('getSchoolPlacementGeneralSummary', 'BenBatchManagementController@getSchoolPlacementGeneralSummary');
        Route::get('getSchPlacementUserSummary', 'BenBatchManagementController@getSchPlacementUserSummary');
        Route::get('getSchoolPlacementSummaryTotals', 'BenBatchManagementController@getSchoolPlacementSummaryTotals');
        Route::get('getSchPlacementEntryResultsSummary', 'BenBatchManagementController@getSchPlacementEntryResultsSummary');
        Route::get('getPlacedGirlsDetails', 'BenBatchManagementController@getPlacedGirlsDetails');
        Route::get('getUnPlacedGirlsDetails', 'BenBatchManagementController@getUnPlacedGirlsDetails');
        Route::get('getGirlsForAnalysis', 'BenBatchManagementController@getGirlsForAnalysis');
        Route::get('getBeneficiaryAnnualPromotions', 'BenBatchManagementController@getBeneficiaryAnnualPromotions');
        Route::post('saveBeneficiaryPromotionDetails', 'BenBatchManagementController@saveBeneficiaryPromotionDetails');
        Route::get('getBeneficiaryPromotionDetails', 'BenBatchManagementController@getBeneficiaryPromotionDetails');
        Route::post('processBeneficiaryPromotions', 'BenBatchManagementController@processBeneficiaryPromotions');
        Route::post('undoBeneficiaryPromotions', 'BenBatchManagementController@undoBeneficiaryPromotions');
        Route::post('redoBeneficiaryPromotions', 'BenBatchManagementController@redoBeneficiaryPromotions');
        Route::get('getGradeRepeaters', 'BenBatchManagementController@getGradeRepeaters');
        Route::get('getBeneficiaryGradeRepetitionDetails', 'BenBatchManagementController@getBeneficiaryGradeRepetitionDetails');
        Route::post('sendBeneficiarySuspensionRequest', 'BenBatchManagementController@sendBeneficiarySuspensionRequest');
        Route::post('updateBenSuspensionApproval', 'BenBatchManagementController@updateBenSuspensionApproval');
        Route::get('getBeneficiariesSuspensionDetails', 'BenBatchManagementController@getBeneficiariesSuspensionDetails');
        Route::get('getVerificationBriefSummary', 'BenBatchManagementController@getVerificationBriefSummary');
        Route::post('recallSuspendedBeneficiaries', 'BenBatchManagementController@recallSuspendedBeneficiaries');
        Route::get('getBeneficiaryGradeTransitioning', 'BenBatchManagementController@getBeneficiaryGradeTransitioning');
        Route::post('saveBenGradeChanges', 'BenBatchManagementController@saveBenGradeChanges');
        Route::get('getBeneficiaryManagementSummary', 'BenBatchManagementController@getBeneficiaryManagementSummary');
        Route::get('getGradeNineSummary', 'BenBatchManagementController@getGradeNineSummary');
        Route::post('updateBeneficiaryStatus', 'BenBatchManagementController@updateBeneficiaryStatus');
        Route::post('updateSelectedBeneficiariesStatus', 'BenBatchManagementController@updateSelectedBeneficiariesStatus');
        Route::get('getFollowupPossibleReason', 'BenBatchManagementController@getFollowupPossibleReason');
        Route::post('updateLateAssessmentInfo', 'BenBatchManagementController@updateLateAssessmentInfo');
        Route::get('getDuplicateProcessingHistory', 'BenBatchManagementController@getDuplicateProcessingHistory');
        Route::get('getDuplicateLateProcessing', 'BenBatchManagementController@getDuplicateLateProcessing');
        Route::get('getEnrolledBeneficiaries', 'BenBatchManagementController@getEnrolledBeneficiaries');
        Route::post('revokeGradeNinePromotion', 'BenBatchManagementController@revokeGradeNinePromotion');
        Route::get('getRevokedGradeNinePromotions', 'BenBatchManagementController@getRevokedGradeNinePromotions');
        Route::post('markBeneficiaryEnrollments', 'BenBatchManagementController@markBeneficiaryEnrollments');
        Route::post('updateFilteredOutGirlsCategories', 'BenBatchManagement@updateFilteredOutGirlsCategories');
        Route::post('updateInSchoolFailedMappingGrades', 'BenBatchManagement@updateInSchoolFailedMappingGrades');
        Route::post('updateInSchoolFailedMappingGrades', 'BenBatchManagement@updateInSchoolFailedMappingGrades');
        Route::post('exportBenBatchRecords', 'BenBatchManagementController@exportBenBatchRecords');
        //school transfer
        Route::post('saveSchoolTransferImplementation', 'BenBatchManagementController@saveSchoolTransferImplementation');
        Route::post('saveSchoolTransferApproval', 'BenBatchManagementController@saveSchoolTransferApproval');
        Route::get('getBeneficiarySchoolTransfers', 'BenBatchManagementController@getBeneficiarySchoolTransfers');
        Route::get('getBeneficiariesForSchTransfer', 'BenBatchManagementController@getBeneficiariesForSchTransfer');
        Route::post('updateBeneficiarySchoolCorrection', 'BenBatchManagementController@updateBeneficiarySchoolCorrection');
        Route::get('getBeneficiarySchoolCorrections', 'BenBatchManagementController@getBeneficiarySchoolCorrections');
        //Route::get('getBeneficiarySchoolTransfersApprovals', 'BenBatchManagementController@getBeneficiarySchoolTransfersApprovals');
        Route::get('getTransferApprovalOptions', 'BenBatchManagementController@getTransferApprovalOptions');
        Route::post('archiveSchoolTransfer', 'BenBatchManagementController@archiveSchoolTransfer');
        Route::get('getActiveBeneficiariesWithoutSchools', 'BenBatchManagementController@getActiveBeneficiariesWithoutSchools');
        //Reports
        Route::get('printSchPlacementForms', 'Reports@printSchPlacementForms');
        //manual beneficiary promotion
        Route::get('manualPromotionProcess', 'BenBatchManagementController@manualPromotionProcess');
    
    });
});
