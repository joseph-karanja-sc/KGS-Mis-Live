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

Route::prefix('casemanagement')->group(function () {
    Route::get('/', 'CaseManagementController@index');
    //Start Maureen
    //Case Forms
    Route::get('getCase_forms', 'CaseManagementController@getAllCaseforms');
    Route::post('editcaseforms', 'CaseManagementController@editCaseforms');
    Route::get('getCaseKPIs', 'CaseManagementController@getAllCaseKPIs');
    //print forms
    Route::get('printcaseform', 'CaseReportController@printAllcaseform');
    //Case Assessment
    Route::get('getCaseChildren', 'CaseManagementController@getCaseChildren');
    Route::get('getsiblingSignificant', 'CaseManagementController@getsiblingSignificant');
    Route::get('getcareplandetails', 'CaseManagementController@getcareplandetails');
    Route::get('getcaseagreement', 'CaseManagementController@getcaseagreement');
    Route::get('getcaseParticipant', 'CaseManagementController@getcaseParticipant');
    Route::get('getcaseImplementation', 'CaseManagementController@getcaseImplementation');
    Route::get('prepareCaseAssessmentDetails', 'CaseManagementController@prepareCaseAssessmentDetails');
    Route::post('saveCaseAssessmentDetail', 'CaseManagementController@saveCaseAssessmentDetail');
    Route::post('saveCarePlanDetails', 'CaseManagementController@saveCarePlanDetails');
    Route::get('getcareplan', 'CaseManagementController@getcareplan');
    Route::get('getFamilyRefferalDetails', 'CaseManagementController@getFamilyRefferalDetails');
    //Case Reports
    Route::get('getcasesperarget', 'CaseManagementController@getCasesPerTarget');
    Route::get('getcasesperlocation', 'CaseManagementController@getCasesPerLocation');
    Route::get('getCasesPerTargetGraph', 'CaseManagementController@getCasesperTargetgraph');
    Route::get('getcasesperlocationgraph', 'CaseManagementController@getCasesperLocationgraph');
    //Params
    Route::get('getParamServices', 'CaseManagementController@getAllServicesParam');
    //End Maureen Start frank
    Route::post('deleteCaseModuleRecord', 'CaseManagementController@deleteCaseModuleRecord');
    Route::post('saveCaseParamData', 'CaseManagementController@saveCaseModuleCommonData');
    Route::get('getCaseEntriesInfo', 'CaseManagementController@getCaseEntriesInfo');
    Route::post('dismissCaseNotification', 'CaseManagementController@dismissCaseNotification');
    Route::get('getCaseModuleParamFromTable', 'CaseManagementController@getCaseModuleParamFromTable');
    Route::post('saveCaseBasicDataEntryInfo', 'CaseManagementController@saveCaseBasicDataEntryInfo');
    Route::get('getCaseNotificationInfo', 'CaseManagementController@getCaseNotificationInfo');
    Route::get('getSupervisorComments', 'CaseManagementController@getSupervisorComments');
    Route::get('prepareCaseRecordingForm', 'CaseManagementController@prepareCaseRecordingForm');
    Route::get('getRecordedWarningSignsDetails', 'CaseManagementController@getRecordedWarningSignsDetails');
    Route::post('processCaseRecordSubmission', 'CaseManagementController@processCaseRecordSubmission');
    Route::get('getCaseWarningSigns', 'CaseManagementController@getCaseWarningSigns');
    Route::post('saveSelectedWarningSigns', 'CaseManagementController@saveSelectedWarningSigns');
    Route::get('getNextCaseWorkflowStageDetails', 'CaseManagementController@getNextCaseWorkflowStageDetails');
    Route::post('saveSupervisorComments', 'CaseManagementController@saveSupervisorComments');
    Route::get('getcustomreportcases', 'CaseManagementController@getCustomReportCases');
    Route::post('processCaseRecordValidationSubmission', 'CaseManagementController@processCaseRecordValidationSubmission');
    Route::get('getCaseConferenceDetails', 'CaseManagementController@getCaseConferenceDetails');
    Route::get('getCaseServiceResourceDetails', 'CaseManagementController@getCaseServiceResourceDetails');
    Route::post('saveWarningSignsRemarkInfo', 'CaseManagementController@saveWarningSignsRemarkInfo');
    //kpis
    Route::get('getCaseKpiNumberOfGrmQueries', 'CaseManagementController@getCaseKpiNumberOfGrmQueries');
    Route::get('getBursaryInvitesAndGraduates', 'CaseManagementController@getBursaryInvitesAndGraduates');
    Route::get('getKgsBursaryInvitesInSchool', 'CaseManagementController@getKgsBursaryInvitesInSchool');
    Route::get('getBeneficiaryRefferalKpis', 'CaseManagementController@getBeneficiaryRefferalKpis');
    Route::get('getBeneficiariesShowingSchoolProgress', 'CaseManagementController@getBeneficiariesShowingSchoolProgress');
    Route::get('getKpiNumberAndPercentage', 'CaseManagementController@getKpiNumberAndPercentage');
    Route::get('getSupportKpis', 'CaseManagementController@getSupportKpis');
    Route::get('getCaseKpisForAnalysis', 'CaseManagementController@getCaseKpisForAnalysis');
    Route::get('getCaseKpiCategories', 'CaseManagementController@getCaseKpiCategories');

    Route::get('getSocioEmotionalInfo', 'CaseManagementController@getSocioEmotionalInfo');
    Route::post('saveSocioEmotionalInfo', 'CaseManagementController@saveSocioEmotionalInfo');
    Route::get('getAccessToResourcesInfo', 'CaseManagementController@getAccessToResourcesInfo');
    Route::post('saveAccessToResourcesInfo', 'CaseManagementController@saveAccessToResourcesInfo');
    Route::get('getFamilySurvivalInfo', 'CaseManagementController@getFamilySurvivalInfo');
    Route::post('saveFamilySurvivalInfo', 'CaseManagementController@saveFamilySurvivalInfo');
    Route::post('saveFamilySurvivalDetails', 'CaseManagementController@saveFamilySurvivalDetails');
    Route::get('getCaseRelationshipDetails', 'CaseManagementController@getCaseRelationshipDetails');
    Route::post('saveCaseAssessmentBasicDetailsFrm', 'CaseManagementController@saveCaseAssessmentBasicDetailsFrm');
    Route::post('saveCaseReferralBasicDetailsFrm', 'CaseManagementController@saveCaseReferralBasicDetailsFrm');
    Route::post('saveCaseCarePlanBasicDetailsFrm', 'CaseManagementController@saveCaseCarePlanBasicDetailsFrm');
    Route::post('saveCaseParentDetailsFrm', 'CaseManagementController@saveCaseParentDetailsFrm');
    Route::post('saveGeneralHealthAndHiegienFrm', 'CaseManagementController@saveGeneralHealthAndHiegienFrm');
    
    Route::get('getEducationalBckGrndInfo', 'CaseManagementController@getEducationalBckGrndInfo');
    Route::post('saveEducationalBckGrndInfo', 'CaseManagementController@saveEducationalBckGrndInfo');
    Route::post('saveEducationalBckGrndDetails', 'CaseManagementController@saveEducationalBckGrndDetails');
    Route::get('prepareCaseAssessParentDetails', 'CaseManagementController@prepareCaseAssessParentDetails');
    Route::get('prepareCaseAssessmentHealthDetails', 'CaseManagementController@prepareCaseAssessmentHealthDetails');
    Route::post('saveCarePlanGridDetails', 'CaseManagementController@saveCarePlanGridDetails');
    Route::get('getReviewNotes', 'CaseManagementController@getReviewNotes');
    Route::post('saveCaseProcessCompletedDetails', 'CaseManagementController@saveCaseProcessCompletedDetails');
    Route::post('processCaseRecordClosureSubmission', 'CaseManagementController@processCaseRecordClosureSubmission');
    Route::get('getCaseProcessCompletedDetails', 'CaseManagementController@getCaseProcessCompletedDetails');
    Route::get('getCaseSystemUsers', 'CaseManagementController@getCaseSystemUsers');
    Route::get('getCaseTotalLoadInfo', 'CaseManagementController@getCaseTotalLoadInfo');
    Route::get('getCaseEntriesInfoToDasboard', 'CaseManagementController@getCaseEntriesInfoToDasboard');
    Route::get('getCaseLogSheetInfo', 'CaseManagementController@getCaseLogSheetInfo');
    Route::get('tcpdfSamplePage', 'CaseManagementController@tcpdfSamplePage');
    //end frank
    Route::get('getCaseReviewChecklists', 'CaseManagementController@getCaseReviewChecklists');
    Route::post('saveCaseReviewChecklists', 'CaseManagementController@saveCaseReviewChecklists');
    Route::get('getCaseMonitoringCarePlanDetails', 'CaseManagementController@getCaseMonitoringCarePlanDetails');
    Route::post('saveCaseMonitoringCarePlanDetails', 'CaseManagementController@saveCaseMonitoringCarePlanDetails');
    Route::post('saveCaseMonitoringReviewBasicInfo', 'CaseManagementController@saveCaseMonitoringReviewBasicInfo');
    Route::get('getCaseClosureReasonsDetails', 'CaseManagementController@getCaseClosureReasonsDetails');
    Route::post('saveCaseClosureReasonsDetails', 'CaseManagementController@saveCaseClosureReasonsDetails');
    Route::get('getCaseClosureProcessesCompletedDetails', 'CaseManagementController@getCaseClosureProcessesCompletedDetails');
    Route::post('saveCaseClosureProcessesCompletedDetails', 'CaseManagementController@saveCaseClosureProcessesCompletedDetails');
    Route::post('processCaseClosure', 'CaseManagementController@processCaseClosure');
    Route::post('saveCaseAppealDetails', 'CaseManagementController@saveCaseAppealDetails');
    Route::get('getCaseAppealDetails', 'CaseManagementController@getCaseAppealDetails');
    Route::get('getCaseGirlsDetails', 'CaseManagementController@getCaseGirlsDetails');
    Route::get('getCaseDashboardData', 'CaseManagementController@getCaseDashboardData');
    Route::get('getDashboardGraphGridCasesCount', 'CaseManagementController@getDashboardGraphGridCasesCount');
    Route::get('getDropoutsCountPerCategory', 'CaseManagementController@getDropoutsCountPerCategory');
    Route::post('saveCaseTransferDetails', 'CaseManagementController@saveCaseTransferDetails');
    Route::get('getCaseEarlyWarningSignsInfo', 'CaseManagementController@getCaseEarlyWarningSignsInfo');
    Route::post('saveCaseEarlyWarningSignInfo', 'CaseManagementController@saveCaseEarlyWarningSignInfo');
    Route::get('getRevisedCarePlanDetails', 'CaseManagementController@getRevisedCarePlanDetails');
    Route::post('deleteCaseCarePlanDetails', 'CaseManagementController@deleteCaseCarePlanDetails');
    Route::post('saveRevisedCarePlanDetails', 'CaseManagementController@saveRevisedCarePlanDetails');
    Route::get('getCaseMonitoringReviewNextCounter', 'CaseManagementController@getCaseMonitoringReviewNextCounter');
    Route::get('getCaseReferralDetails', 'CaseManagementController@getCaseReferralDetails');
    Route::get('getCaseMonitoringDetails', 'CaseManagementController@getCaseMonitoringDetails');
    Route::get('getCasePersonnelInfo', 'CaseManagementController@getCasePersonnelInfo');
    Route::post('getCaseManagementSubModulesDMSFolderID', 'CaseManagementController@getCaseManagementSubModulesDMSFolderID');
    Route::post('saveInitialCarePlanDetails', 'CaseManagementController@saveInitialCarePlanDetails');
    Route::get('getCarePlanTrackingInfo', 'CaseManagementController@getCarePlanTrackingInfo');
    Route::post('validateCaseClosure', 'CaseManagementController@validateCaseClosure');
    Route::get('getCaseTransfersLogs', 'CaseManagementController@getCaseTransfersLogs');
    Route::get('getCaseReferralsTrackingInfo', 'CaseManagementController@getCaseReferralsTrackingInfo');
    Route::post('exportCMSRecords', 'CaseManagementController@exportCMSRecords');
    Route::get('getRecordSubmissionReportDetails', 'CaseManagementController@getRecordSubmissionReportDetails');
    Route::get('prepareMainBasicCaseAssessmentFrmDetails', 'CaseManagementController@prepareMainBasicCaseAssessmentFrmDetails');
});

