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

Route::prefix('grmmodule')->group(function () {
    Route::get('/', 'GrmModuleController@index');
    Route::get('getGrmModuleParamFromTable', 'GrmModuleController@getGrmModuleParamFromTable');
    Route::get('getAllGrievanceTypes', 'GrmModuleController@getAllGrievanceTypes');
    Route::post('saveGrmParamData', 'GrmModuleController@saveGrmModuleCommonData');
    Route::post('deleteGrmParamData', 'GrmModuleController@deleteGrmModuleRecord');
    Route::post('saveComplaintDetails', 'GrmModuleController@saveComplaintDetails');
    Route::get('getOfflineSubmittedGrievances', 'GrmModuleController@getOfflineSubmittedGrievances');
    Route::get('getGrievances', 'GrmModuleController@getGrievances');
    Route::get('getComplaintSubCategories', 'GrmModuleController@getComplaintSubCategories');
    Route::get('getComplaintCategorizationDetails', 'GrmModuleController@getComplaintCategorizationDetails');
    Route::post('saveComplaintCategorizationDetails', 'GrmModuleController@saveComplaintCategorizationDetails');
    Route::get('getComplaintNoteDetails', 'GrmModuleController@getComplaintNoteDetails');
    Route::get('getGrmSubmissionDetails', 'GrmModuleController@getGrmSubmissionDetails');
    Route::post('processGRMRecordSubmission', 'GrmModuleController@processGRMRecordSubmission');
    Route::get('getComplaintActionItems', 'GrmModuleController@getComplaintActionItems');
    Route::get('getComplaintResolutionDetails', 'GrmModuleController@getComplaintResolutionDetails');
    Route::get('getComplaintRecommendationDetails', 'GrmModuleController@getComplaintRecommendationDetails');
    Route::post('saveComplaintResolutionDetails', 'GrmModuleController@saveComplaintResolutionDetails');
    Route::get('getGRMDashboardData', 'GrmModuleController@getGRMDashboardData');
    Route::get('getDashboardChartDetailsGrpOne', 'GrmModuleController@getDashboardChartDetailsGrpOne');
    Route::get('getComplaintStatusesChartDetails2', 'GrmModuleController@getComplaintStatusesChartDetails2');
    Route::get('getSelectedTemplate', 'GrmModuleController@getSelectedTemplate');
    Route::post('uploadResponseLetterTemplate', 'GrmModuleController@uploadResponseLetterTemplate');
    Route::get('getResolvedComplaintsChartDetails', 'GrmModuleController@getResolvedComplaintsChartDetails');
    Route::get('getComplaintResponseLettersConfig', 'GrmModuleController@getComplaintResponseLettersConfig');
    Route::post('saveGrmMonitoringPlanDetails', 'GrmModuleController@saveGrmMonitoringPlanDetails');
    Route::get('getGrmMonitoringPlanDetails', 'GrmModuleController@getGrmMonitoringPlanDetails');
    Route::get('getGrmMonitoringStaff', 'GrmModuleController@getGrmMonitoringStaff');
    Route::post('saveGrmMonitoringLocationDetails', 'GrmModuleController@saveGrmMonitoringLocationDetails');
    Route::get('getGrmMonitoringLocationDetails', 'GrmModuleController@getGrmMonitoringLocationDetails');
    Route::get('getGrievancesForMonitoring', 'GrmModuleController@getGrievancesForMonitoring');
    Route::post('saveMonitoringComplaints', 'GrmModuleController@saveMonitoringComplaints');
    Route::get('getMonitoringComplaints', 'GrmModuleController@getMonitoringComplaints');
    Route::post('uploadGrmResponseDocument', 'GrmModuleController@uploadGrmResponseDocument');
    Route::post('saveMonitoringComplaintsDataEntry', 'GrmModuleController@saveMonitoringComplaintsDataEntry');
    Route::get('getComplaintLetterResponses', 'GrmModuleController@getComplaintLetterResponses');
    Route::post('syncComplaintLetterResponses', 'GrmModuleController@syncComplaintLetterResponses');
    Route::get('getLetterTemplateApplicableSections', 'GrmModuleController@getLetterTemplateApplicableSections');
    Route::post('saveLetterTemplateApplicableSections', 'GrmModuleController@saveLetterTemplateApplicableSections');
    Route::post('saveComplaintLetterTemplate', 'GrmModuleController@saveComplaintLetterTemplate');
    Route::get('getComplaintLetterTemplates', 'GrmModuleController@getComplaintLetterTemplates');
    Route::get('getGRMFocalPersons', 'GrmModuleController@getGRMFocalPersons');
    Route::get('getRecordedGrievancesChartView', 'GrmModuleController@getRecordedGrievancesChartView');
    Route::get('getGrmNotifications', 'GrmModuleController@getGrmNotifications');
    Route::post('updateComplaintNotificationFeedback', 'GrmModuleController@updateComplaintNotificationFeedback');
    Route::post('saveComplaintActionItem', 'GrmModuleController@saveComplaintActionItem');
    Route::get('getProgrammeNotificationEmails', 'GrmModuleController@getProgrammeNotificationEmails');
    Route::post('saveProgrammeNotificationEmails', 'GrmModuleController@saveProgrammeNotificationEmails');
    Route::post('saveGrievanceProcessingDetails', 'GrmModuleController@saveGrievanceProcessingDetails');
    Route::post('saveGrievanceInvestigationDetails', 'GrmModuleController@saveGrievanceInvestigationDetails');
    Route::get('getGrievanceInvestigationDetails', 'GrmModuleController@getGrievanceInvestigationDetails');
    Route::post('validateGrievanceResolution', 'GrmModuleController@validateGrievanceResolution');
    Route::post('saveGrievanceAppealDetails', 'GrmModuleController@saveGrievanceAppealDetails');
    Route::get('getGrievanceAppealDetails', 'GrmModuleController@getGrievanceAppealDetails');
    Route::get('getGrievanceFormsDetails', 'GrmModuleController@getGrievanceFormsDetails');
    Route::post('saveGrievanceFormsDetails', 'GrmModuleController@saveGrievanceFormsDetails');
    Route::get('validateFormSerialNumber', 'GrmModuleController@validateFormSerialNumber');
    Route::post('dismissGrmNotification', 'GrmModuleController@dismissGrmNotification');
    Route::get('getGrmEmailNotificationsSetup', 'GrmModuleController@getGrmEmailNotificationsSetup');
    Route::get('getGrmResponseLetterSnapShot', 'GrmModuleController@getGrmResponseLetterSnapShot');
    Route::get('getAllGrievanceResponseLetterSnapShots', 'GrmModuleController@getAllGrievanceResponseLetterSnapShots');
    Route::post('handleGrmMonitoringSubmission', 'GrmModuleController@handleGrmMonitoringSubmission');
    Route::get('getInitialMonitoringWorkflowDetails', 'GrmModuleController@getInitialMonitoringWorkflowDetails');
    //todo: Reports
    Route::get('getGrmReportsPerProgram', 'GrmReportsController@getGrmReportsPerProgram');
    Route::get('getGrmReportsPerCategory', 'GrmReportsController@getGrmReportsPerCategory');
    Route::get('getGrmReportsPerLocation', 'GrmReportsController@getGrmReportsPerLocation');
    Route::get('getGrmReportsPerStatus', 'GrmReportsController@getGrmReportsPerStatus');
    Route::get('getGrmReportsComplaintResolutionAvTime', 'GrmReportsController@getGrmReportsComplaintResolutionAvTime');
    Route::get('getGrmReportsComplaintResolutionLimitTime', 'GrmReportsController@getGrmReportsComplaintResolutionLimitTime');
    Route::get('generateComplaintResponseLetter', 'GrmReportsController@generateComplaintResponseLetter');
    Route::get('previewResponseLetterTemplate', 'GrmReportsController@previewResponseLetterTemplate');
    Route::get('generateComplaintForms', 'GrmReportsController@generateComplaintForms');
    Route::get('getComplaintNumbersLog', 'GrmReportsController@getComplaintNumbersLog');
    Route::get('printGrmMonitoringForm', 'GrmReportsController@printGrmMonitoringForm');
    Route::get('printComplaintDetails', 'GrmReportsController@printComplaintDetails');
    //Maureen
    Route::get('getComplaintResult', 'GrmModuleController@getComplaintResult');
    //Frank
    Route::post('resendFailedGrmEmails', 'GrmModuleController@resendFailedGrmEmails');
    Route::post('deleteFailedGrmEmails', 'GrmModuleController@deleteFailedGrmEmails');
    Route::post('exportGRMRecords', 'GrmModuleController@exportGRMRecords');
    Route::get('batchSubmitComplaintDetails', 'GrmModuleController@batchSubmitComplaintDetails');   
    Route::post('batchSubmitComplaintDetails', 'GrmModuleController@batchSubmitComplaintDetails');    
});
