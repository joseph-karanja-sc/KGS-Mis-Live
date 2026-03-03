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

Route::prefix('identificationenrollment')->group(function() {
    Route::get('/', 'IdentificationEnrollmentController@index');
});

Route::group(['prefix' => 'identificationEnrollment'], function () {
    Route::group(['middleware' => ['web']], function () {
        Route::post('uploadNewBatch', 'IdentificationEnrollmentController@uploadNewBatch');
        Route::get('getImports', 'IdentificationEnrollmentController@getCurrentImports');
        Route::get('getAllBatchAssessmentData', 'IdentificationEnrollmentController@getAllBatchAssessmentData');
        Route::post('getErrorCount', 'IdentificationEnrollmentController@getErrorCount');
        Route::post('recreateBatchNumber', 'IdentificationEnrollmentController@recreateBatchNumber');
        Route::Post('getTemplateFields', 'IdentificationEnrollmentController@getTemplateFields');
        Route::Post('getTemplateFieldsForMapping', 'IdentificationEnrollmentController@getTemplateFieldsForMapping');
        Route::get('getActiveTemplate', 'IdentificationEnrollmentController@getActiveTemplate');
        Route::post('getMainActiveTemplateFields', 'IdentificationEnrollmentController@getMainActiveTemplateFields');
        Route::post('setCurrentTemplate', 'IdentificationEnrollmentController@setCurrentTemplate');
        Route::post('clearBatchInfo', 'IdentificationEnrollmentController@clearBatchInfo');
        Route::post('moveToBatchAssessment', 'IdentificationEnrollmentController@moveToBatchAssessment');
        Route::get('getOutofSchool', 'IdentificationEnrollmentController@getOutofSchool');
        Route::get('getInSchool', 'IdentificationEnrollmentController@getInSchool');
        Route::get('getExamClasses', 'IdentificationEnrollmentController@getExamClasses');
        Route::post('updateBatchTemplate', 'IdentificationEnrollmentController@updateBatchTemplate');
        Route::get('isDuplicateRecord', 'IdentificationEnrollmentController@isDuplicateRecord');
        Route::get('getDuplicateRecords', 'IdentificationEnrollmentController@getDuplicateRecords');
        Route::get('getImportationBatches', 'IdentificationEnrollmentController@getImportationBatches');
        Route::get('getFilteredOutRecords', 'IdentificationEnrollmentController@getFilteredOutRecords');
        Route::get('getSummaryStatistics', 'IdentificationEnrollmentController@getSummaryStatistics');
        Route::post('saveInclusionCriteria', 'IdentificationEnrollmentController@saveInclusionCriteria');
        Route::get('getMappedData', 'IdentificationEnrollmentController@getMappedData');
        Route::get('getMappingSummary', 'IdentificationEnrollmentController@getMappingSummary');
        Route::post('mapOutOfSchoolBatchData', 'IdentificationEnrollmentController@mapOutOfSchoolBatchData');
        Route::post('mapInSchoolBatchData', 'IdentificationEnrollmentController@mapInSchoolBatchData');
        Route::get('getImportsInfo', 'IdentificationEnrollmentController@getImportsInfo');
        Route::post('saveImportInformation', 'IdentificationEnrollmentController@saveImportInformation');
        Route::post('deleteBatchInfo', 'IdentificationEnrollmentController@deleteBatchInfo');
        Route::post('returnBatchToPrevStage', 'IdentificationEnrollmentController@returnBatchToPrevStage');
        Route::post('mapOutOfSchool', 'IdentificationEnrollmentController@mapOutOfSchool');
        Route::get('getMappingErrorLogs', 'IdentificationEnrollmentController@getMappingErrorLogs');
        Route::post('addMissingErrorParam', 'IdentificationEnrollmentController@addMissingErrorParam');
        Route::post('submitBatchForVerification', 'IdentificationEnrollmentController@submitBatchForVerification');
        Route::get('getMultipleAnswerOptions', 'IdentificationEnrollmentController@getMultipleAnswerOptions');
        Route::post('getVerificationSchInfo', 'IdentificationEnrollmentController@getVerificationSchInfo');
        Route::post('getVerificationCwacInfo', 'IdentificationEnrollmentController@getVerificationCwacInfo');
        Route::post('getVerificationDistrictInfo', 'IdentificationEnrollmentController@getVerificationDistrictInfo');
        Route::post('updateSchoolInfo', 'IdentificationEnrollmentController@updateSchoolInfo');
        Route::post('updateCwacInfo', 'IdentificationEnrollmentController@updateCwacInfo');
        Route::get('getGirlsForVerification', 'IdentificationEnrollmentController@getGirlsForVerification');
        Route::get('getGirlsForAnalysis', 'IdentificationEnrollmentController@getGirlsForAnalysis');
        Route::get('getExamClassesGirlsForAnalysis', 'IdentificationEnrollmentController@getExamClassesGirlsForAnalysis');
        Route::get('getGirlsForSchoolMatching', 'IdentificationEnrollmentController@getGirlsForSchoolMatching');
        Route::get('getGirlsForResultsEntry', 'IdentificationEnrollmentController@getGirlsForResultsEntry');
        Route::get('getVerificationProgress', 'IdentificationEnrollmentController@getVerificationProgress');
        Route::get('getVerificationChecklistItems', 'IdentificationEnrollmentController@getVerificationChecklistItems');
        Route::post('saveInSchVerificationDetails', 'IdentificationEnrollmentController@saveInSchVerificationDetails');
        Route::post('saveOutSchVerificationDetails', 'IdentificationEnrollmentController@saveOutSchVerificationDetails');
        Route::post('saveCommunityBasedVerificationDetailsTwo', 'IdentificationEnrollmentController@saveCommunityBasedVerificationDetailsTwo');
        Route::post('saveExamClassesVerificationDetails', 'IdentificationEnrollmentController@saveExamClassesVerificationDetails');
        Route::get('getChecklistsGenSummary', 'IdentificationEnrollmentController@getChecklistsGenSummary');
        Route::get('getAnalysisSummary', 'IdentificationEnrollmentController@getAnalysisSummary');
        Route::get('getChecklistsGenGirls', 'IdentificationEnrollmentController@getChecklistsGenGirls');
        Route::get('getDistrictsOnProvinceMultiSelect', 'IdentificationEnrollmentController@getDistrictsOnProvinceMultiSelect');
        Route::get('getCwacsOnDistrictMultiSelect', 'IdentificationEnrollmentController@getCwacsOnDistrictMultiSelect');
        Route::get('getSchoolsOnDistrictMultiSelect', 'IdentificationEnrollmentController@getSchoolsOnDistrictMultiSelect');
        Route::post('updateGirlInfo', 'IdentificationEnrollmentController@updateGirlInfo');
        Route::post('submitForAnalysis', 'IdentificationEnrollmentController@submitForAnalysis');
        Route::post('submitSingleForNextStageAfterAnalysis', 'IdentificationEnrollmentController@submitSingleForNextStageAfterAnalysis');
        Route::post('submitForNextStageAfterAnalysis', 'IdentificationEnrollmentController@submitForNextStageAfterAnalysis');
        Route::post('submitForLettersGenAfterMatching', 'IdentificationEnrollmentController@submitForLettersGenAfterMatching');
        Route::post('onSubmitExamClassesIndividualForLettersGen', 'IdentificationEnrollmentController@onSubmitExamClassesIndividualForLettersGen');
        Route::post('onSubmitExamClassesBatchForLettersGen', 'IdentificationEnrollmentController@onSubmitExamClassesBatchForLettersGen');
        Route::post('submitForResultsEntry', 'IdentificationEnrollmentController@submitForResultsEntry');
        Route::post('submitForLettersGenAfterPlacement', 'IdentificationEnrollmentController@submitForLettersGenAfterPlacement');
        Route::post('saveRecommendationOverrule', 'IdentificationEnrollmentController@saveRecommendationOverrule');
        Route::post('saveRecommendationOverruleBatch', 'IdentificationEnrollmentController@saveRecommendationOverruleBatch');
        Route::get('getLettersGenSummary', 'IdentificationEnrollmentController@getLettersGenSummary');
        Route::get('getGirlsForLettersGeneration', 'IdentificationEnrollmentController@getGirlsForLettersGeneration');
        Route::post('saveCommonData', 'IdentificationEnrollmentController@saveCommonData');
        Route::post('saveSchoolMatchingInfo', 'IdentificationEnrollmentController@saveSchoolMatchingInfo');
        Route::post('saveSchoolPlacementInfo', 'IdentificationEnrollmentController@saveSchoolPlacementInfo');
        Route::post('getGirlMatchingInfo', 'IdentificationEnrollmentController@getGirlMatchingInfo');
        Route::post('getGirlSchPlacementInfo', 'IdentificationEnrollmentController@getGirlSchPlacementInfo');
        Route::post('getBatchInclusionCriteria', 'IdentificationEnrollmentController@getBatchInclusionCriteria');
        Route::get('getInclusionCriteria', 'IdentificationEnrollmentController@getInclusionCriteria');
        Route::get('getBeneficiary', 'IdentificationEnrollmentController@getBeneficiary');
        Route::get('getPossibleDuplicatedRecords', 'IdentificationEnrollmentController@getPossibleDuplicatedRecords');
        Route::get('getMapPossibleDuplicatedRecords', 'IdentificationEnrollmentController@getMapPossibleDuplicatedRecords');
        Route::get('getPlacementAnalysisSummary', 'IdentificationEnrollmentController@getPlacementAnalysisSummary');
        Route::get('getInSchDetailedSummary', 'IdentificationEnrollmentController@getInSchDetailedSummary');
        Route::get('getLetterGenDetailedSummary', 'IdentificationEnrollmentController@getLetterGenDetailedSummary');
        Route::get('getUnresponsiveGirlsSummary', 'IdentificationEnrollmentController@getUnresponsiveGirlsSummary');
        Route::get('getOutSchDetailedSummary', 'IdentificationEnrollmentController@getOutSchDetailedSummary');
        Route::get('getDistrictCapacityAssessments', 'IdentificationEnrollmentController@getDistrictCapacityAssessments');
        Route::get('getSchoolMatchingProgress', 'IdentificationEnrollmentController@getSchoolMatchingProgress');
        Route::get('getSchoolPlacementProgress', 'IdentificationEnrollmentController@getSchoolPlacementProgress');
        Route::post('updateDuplicatedGirlDetails', 'IdentificationEnrollmentController@updateDuplicatedGirlDetails');
        Route::post('updateIsDuplicate', 'IdentificationEnrollmentController@updateIsDuplicate');
        Route::post('processDuplicates', 'IdentificationEnrollmentController@processDuplicates');
        Route::get('getGirlAdditionalInfo', 'IdentificationEnrollmentController@getGirlAdditionalInfo');
        Route::post('saveGirlAdditionalInfo', 'IdentificationEnrollmentController@saveGirlAdditionalInfo');
        Route::post('findDuplicatesFromExisting', 'IdentificationEnrollmentController@findDuplicatesFromExisting');
        Route::get('getMappingDuplicateRecords', 'IdentificationEnrollmentController@getMappingDuplicateRecords');
        Route::post('updateMappingDuplicatedGirlDetails', 'IdentificationEnrollmentController@updateMappingDuplicatedGirlDetails');
        Route::post('updateIsMappingDuplicate', 'IdentificationEnrollmentController@updateIsMappingDuplicate');
        Route::post('processMappingDuplicates', 'IdentificationEnrollmentController@processMappingDuplicates');
        Route::post('getImportationBatchesSubModulesDMSFolderID', 'IdentificationEnrollmentController@getImportationBatchesSubModulesDMSFolderID');
        Route::post('updateMappingLogAnyway', 'IdentificationEnrollmentController@updateMappingLogAnyway');
        Route::post('checkAnySuccessfulMapping', 'IdentificationEnrollmentController@checkAnySuccessfulMapping');
        Route::get('getSchoolMatchingSummary', 'IdentificationEnrollmentController@getSchoolMatchingSummary');
        Route::get('getSchoolPlacementSummary', 'IdentificationEnrollmentController@getSchoolPlacementSummary');
        Route::get('getInSchDetailedAnalysisSummary', 'IdentificationEnrollmentController@getInSchDetailedAnalysisSummary');
        Route::get('getInSchUserAnalysisSummary', 'IdentificationEnrollmentController@getInSchUserAnalysisSummary');
        Route::get('getOutSchDetailedAnalysisSummary', 'IdentificationEnrollmentController@getOutSchDetailedAnalysisSummary');
        Route::get('getInSchUserAnalysisSummaryTotals', 'IdentificationEnrollmentController@getInSchUserAnalysisSummaryTotals');
        Route::get('getInSchEntryOutcomeAnalysisSummary', 'IdentificationEnrollmentController@getInSchEntryOutcomeAnalysisSummary');
        Route::get('getOutSchUserAnalysisSummary', 'IdentificationEnrollmentController@getOutSchUserAnalysisSummary');
        Route::get('getOutSchUserAnalysisSummaryTotals', 'IdentificationEnrollmentController@getOutSchUserAnalysisSummaryTotals');
        Route::get('getOutSchEntryOutcomeAnalysisSummary', 'IdentificationEnrollmentController@getOutSchEntryOutcomeAnalysisSummary');
        Route::get('getLetterGenerationHistory', 'IdentificationEnrollmentController@getLetterGenerationHistory');
        Route::post('updateDatasetInfo', 'IdentificationEnrollmentController@updateDatasetInfo');
        Route::get('getDuplicateSetupLog', 'IdentificationEnrollmentController@getDuplicateSetupLog');
        Route::get('getGirlMappingInfo', 'IdentificationEnrollmentController@getGirlMappingInfo');
        Route::post('mapSingleBeneficiaryInfo', 'IdentificationEnrollmentController@mapSingleBeneficiaryInfo');
        Route::get('getSchoolsWithBeneficiariesOnDistrictMultiSelect', 'IdentificationEnrollmentController@getSchoolsWithBeneficiariesOnDistrictMultiSelect');
        Route::get('getBatchVerificationChecklist', 'IdentificationEnrollmentController@getBatchVerificationChecklist');
        Route::get('getImportationErrorLog', 'IdentificationEnrollmentController@getImportationErrorLog');
        Route::get('validatedUploadedDataset', 'IdentificationEnrollmentController@validatedUploadedDataset');
        Route::post('processBatchMerging', 'IdentificationEnrollmentController@processBatchMerging');
        Route::get('getMergingBatches', 'IdentificationEnrollmentController@getMergingBatches');
        Route::get('getBatchChecklistItems', 'IdentificationEnrollmentController@getBatchChecklistItems');		
        Route::get('populateChecklistDetails', 'IdentificationEnrollmentController@populateChecklistDetails');
        Route::post('insertAndVerifyResponses', 'IdentificationEnrollmentController@insertAndVerifyResponses');
        Route::post('recheckSchoolInfo', 'IdentificationEnrollmentController@recheckSchoolInfo');
        Route::post('recheckExamGradesInfo', 'IdentificationEnrollmentController@recheckExamGradesInfo');
        Route::post('recheckGradeEightandNines', 'IdentificationEnrollmentController@recheckGradeEightandNines');
        Route::post('submitSpecialGradeTwelveToLetterGen', 'IdentificationEnrollmentController@submitSpecialGradeTwelveToLetterGen');
        Route::get('manualPromotionProcess', 'IdentificationEnrollmentController@manualPromotionProcess');
        Route::get('manualPromotionProcessRollBack', 'IdentificationEnrollmentController@manualPromotionProcessRollBack');
    });
});
