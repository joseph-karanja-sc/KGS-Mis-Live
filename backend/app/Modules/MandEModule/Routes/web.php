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

Route::prefix('mandemodule')->group(function () {
    Route::get('/', 'MandEModuleController@index');
    Route::group(['middleware' => ['web']], function () {
        Route::post('saveMandEModuleCommonData', 'MandEModuleController@saveMandEModuleCommonData');
        Route::post('deleteMandEModuleRecord', 'MandEModuleController@deleteMandEModuleRecord');
        Route::get('getMandEModuleParamFromTable', 'MandEModuleController@getMandEModuleParamFromTable');
        Route::get('getMandEKPIs', 'MandEModuleController@getMandEKPIs');
        Route::get('getDataCollectionToolSections', 'MandEModuleController@getDataCollectionToolSections');
        Route::get('getDataCollectionToolQuizes', 'MandEModuleController@getDataCollectionToolQuizes');
        Route::post('addDataCollectionToolQuiz', 'MandEModuleController@addDataCollectionToolQuiz');
        Route::get('getDataCollectionToolAnswerOptionsSetup', 'MandEModuleController@getDataCollectionToolAnswerOptionsSetup');
        Route::get('getConsolidatedSchLevelBackgroundInfo', 'MandEModuleController@getConsolidatedSchLevelBackgroundInfo');
        Route::get('getConsolidatedSchLevelProgressionInfo', 'MandEModuleController@getConsolidatedSchLevelProgressionInfo');
        Route::get('getToolQuizes', 'MandEModuleController@getToolQuizes');
        Route::get('getDataCollectionToolQuizMultipleAnswerOptions', 'MandEModuleController@getDataCollectionToolQuizMultipleAnswerOptions');
        Route::post('saveDataCollectionToolDataEntryBasicInfo', 'MandEModuleController@saveDataCollectionToolDataEntryBasicInfo');
        Route::get('getMandEEntriesInfo', 'MandEModuleController@getMandEEntriesInfo');
        Route::post('saveConsolidatedSchLevelBackgroundInfo', 'MandEModuleController@saveConsolidatedSchLevelBackgroundInfo');
        Route::post('savePupilsStatisticsInfo', 'MandEModuleController@savePupilsStatisticsInfo');
        Route::get('getPupilsStatisticsInfo', 'MandEModuleController@getPupilsStatisticsInfo');
        Route::post('savePupilsProgressionInfo', 'MandEModuleController@savePupilsProgressionInfo');
        Route::get('getBeneficiaryPerformanceAttendanceInfo', 'MandEModuleController@getBeneficiaryPerformanceAttendanceInfo');
        Route::post('savePupilsPerformanceAttendanceInfo', 'MandEModuleController@savePupilsPerformanceAttendanceInfo');
        Route::post('saveMandEUnstructuredQuizesInfo', 'MandEModuleController@saveMandEUnstructuredQuizesInfo');
        Route::get('getNextMandEWorkflowStageDetails', 'MandEModuleController@getNextMandEWorkflowStageDetails');
        Route::post('processMandERecordSubmission', 'MandEModuleController@processMandERecordSubmission');
        Route::get('prepareToolInstitutionalInfo', 'MandEModuleController@prepareToolInstitutionalInfo');
        Route::get('getKPIsForAnalysis', 'MandEModuleController@getKPIsForAnalysis');
        Route::get('calculateCoreIndicatorMatrix', 'MandEModuleController@calculateCoreIndicatorMatrix');
        Route::get('getConsolidatedSchLevelBackgroundInfoAnalysis', 'MandEModuleController@getConsolidatedSchLevelBackgroundInfoAnalysis');
        Route::get('getPupilsStatisticsInfoAnalysis', 'MandEModuleController@getPupilsStatisticsInfoAnalysis');
        Route::get('getToolQuizesAnalysis', 'MandEModuleController@getToolQuizesAnalysis');
        Route::get('getMnEDashboardKPIsGraphData', 'MandEModuleController@getMnEDashboardKPIsGraphData');
        Route::get('getKPIsGraphDetails', 'MandEModuleController@getKPIsGraphDetails');
        Route::get('getConsolidatedSchLevelBackgroundInfoSC', 'MandEModuleController@getConsolidatedSchLevelBackgroundInfoSC');
        //frank
        Route::get('getBeneficiaryData', 'MandEModuleController@getBeneficiaryData');
        Route::post('saveDataCollectionToolBeneficiaryBasicInfo', 'MandEModuleController@saveDataCollectionToolBeneficiaryBasicInfo');
        Route::get('prepareBeneficiaryLevelTool', 'MandEModuleController@prepareBeneficiaryLevelTool');
        Route::get('getMnEDashboardKPIs', 'MandEModuleController@getMnEDashboardKPIs');
        Route::get('validateMnERecords', 'MandEModuleController@validateMnERecords');
        Route::get('prepareSpotCheckTool', 'MandEModuleController@prepareSpotCheckTool');
        Route::post('saveRecordSubmissionReport', 'MandEModuleController@saveRecordSubmissionReport');
        Route::get('getRecordSubmissionReportDetails', 'MandEModuleController@getRecordSubmissionReportDetails');
        Route::get('getDqaEnrollmentInfo', 'MandEModuleController@getDqaEnrollmentInfo');
        Route::post('saveDqaEnrollmentInfo', 'MandEModuleController@saveDqaEnrollmentInfo');
        Route::post('saveDqaEnrollmentAgeSpecificInfo', 'MandEModuleController@saveDqaEnrollmentAgeSpecificInfo');
        Route::get('getDqaEnrollmentAgeSpecificInfo', 'MandEModuleController@getDqaEnrollmentAgeSpecificInfo');        
        Route::post('saveDqaBordingGrlsPaidFor', 'MandEModuleController@saveDqaBordingGrlsPaidFor');
        Route::get('getDqaBordingGrlsPaidFor', 'MandEModuleController@getDqaBordingGrlsPaidFor');
        Route::post('saveDqaGrlsInPrivBrding', 'MandEModuleController@saveDqaGrlsInPrivBrding');
        Route::get('getDqaGrlsInPrivBrding', 'MandEModuleController@getDqaGrlsInPrivBrding');
        Route::post('saveDqaAvgTrmlyAttendance', 'MandEModuleController@saveDqaAvgTrmlyAttendance');
        Route::get('getDqaAvgTrmlyAttendance', 'MandEModuleController@getDqaAvgTrmlyAttendance');
        Route::post('saveDqaKgsGrlsAvgTrmlyPrfrmnce', 'MandEModuleController@saveDqaKgsGrlsAvgTrmlyPrfrmnce');
        Route::get('getDqaKgsGrlsAvgTrmlyPrfrmnce', 'MandEModuleController@getDqaKgsGrlsAvgTrmlyPrfrmnce');
        Route::post('saveDqaNonKgsGrlsAvgTrmlyPrfrmance', 'MandEModuleController@saveDqaNonKgsGrlsAvgTrmlyPrfrmance');
        Route::get('getDqaNonKgsGrlsAvgTrmlyPrfrmance', 'MandEModuleController@getDqaNonKgsGrlsAvgTrmlyPrfrmance');
        Route::post('saveDqaDropOutsInfo', 'MandEModuleController@saveDqaDropOutsInfo');
        Route::get('getDqaDropOutsInfo', 'MandEModuleController@getDqaDropOutsInfo');
        Route::post('saveDqaPaymentsInfo', 'MandEModuleController@saveDqaPaymentsInfo');
        Route::get('getDqaPaymentsInfo', 'MandEModuleController@getDqaPaymentsInfo');
        Route::post('saveDqaRptingLrnersInfo', 'MandEModuleController@saveDqaRptingLrnersInfo');
        Route::get('getDqaRptingLrnersInfo', 'MandEModuleController@getDqaRptingLrnersInfo');
        Route::post('saveDqaGrmInfo', 'MandEModuleController@saveDqaGrmInfo');
        Route::get('getDqaGrmInfo', 'MandEModuleController@getDqaGrmInfo');
        Route::post('saveDqatNotes', 'MandEModuleController@saveDqatNotes');
        Route::get('getDqaDataSrcInfo', 'MandEModuleController@getDqaDataSrcInfo');
        Route::post('saveDqaDataSrcInfo', 'MandEModuleController@saveDqaDataSrcInfo');
        Route::get('getDqaKeyIndicatorInfo', 'MandEModuleController@getDqaKeyIndicatorInfo');
        Route::post('saveDqaKeyIndicatorInfo', 'MandEModuleController@saveDqaKeyIndicatorInfo');
        //Reports start Maureen
        Route::get('printDataCollectionTool', 'MandEReportController@printDataCollectionTool');
        Route::get('getkpitermlysummary', 'MandEModuleController@getkpitermlysummary');
        Route::get('getkpiGendertermlySummary', 'MandEModuleController@getkpigendertermlySummary');
        Route::get('getspotCheckKgsenrolment', 'MandEModuleController@getspotCheckKgsenrolment');
        Route::get('getspotCheckBoardingFacility', 'MandEModuleController@getspotCheckBoardingFacility');
        Route::get('getspotcheckdropoutInfo', 'MandEModuleController@getspotcheckdropoutInfo');
        Route::get('getspotCheckPerfomance', 'MandEModuleController@getspotCheckPerfomance');
        Route::get('getspotcheckProgressionInfo', 'MandEModuleController@getspotcheckProgressionInfo');
        Route::post('savespotcheckdropoutInfo', 'MandEModuleController@savespotcheckdropoutInfo');
        Route::post('savespotCheckKgsenrolment', 'MandEModuleController@savespotCheckKgsenrolment');
        Route::post('savespotCheckBoardingFacility', 'MandEModuleController@savespotCheckBoardingFacility');
        Route::post('savespotCheckPerfomance', 'MandEModuleController@savespotCheckPerfomance');
        Route::get('getgrmsummarygraph', 'MandEModuleController@getgrmsummarygraph');
        Route::get('getgrmsummarygrid', 'MandEModuleController@getgrmtotalsummarygrid');
         Route::get('getSupportedBensummary', 'MandEModuleController@getBenSupportedGraph');
        Route::get('getProgressionRates', 'MandEModuleController@getProgressionRatesGraph');
        Route::get('getgraduationrates', 'MandEModuleController@getGraduationRatesGraph');
        Route::get('getdropoutsummary','MandEModuleController@getdropoutsummaryGraph');
        Route::get('getdropoutlinkedsummary','MandEModuleController@getdropoutLinkedsummaryGraph');
        Route::get('getdropoutreasonsummary','MandEModuleController@getmnedropoutreasonsummaryGraph');
        Route::get('getmandetraininginfo','MandEModuleController@getMandETrainingInfo');
        Route::get('getMandETrainingattendance','MandEModuleController@getmandetrainingparticipantinfo');
        Route::get('getCommunicationGrid','MandEModuleController@getcommunicationcominfo');
        Route::get('getmandeweeklyboardinfogrid','MandEModuleController@getmneboardingdata');
         Route::get('getmandesurveyinfogrid','MandEModuleController@getmneSurveydata');
         Route::get('getcomsummarygraph', 'MandEModuleController@getcommunicationsummarygraph');
        Route::get('getcomsummarygrid', 'MandEModuleController@getcommunicationtotalsummarygrid');
        Route::post('uploaddocument','MandEModuleController@uploadMnEDocument');
        //end Maureen
        Route::post('exportMandERecords', 'exportMandERecords@exportEnrolmentRecords');
        //Job
        Route::post('saveKPItarget', 'MandEModuleController@saveKPItarget');
        Route::get('getKPITargets', 'MandEModuleController@getKPITargets');
       
    });
});
