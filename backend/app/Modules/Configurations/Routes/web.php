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

Route::prefix('configurations')->group(function() {
    Route::get('/', 'ConfigurationsController@index');
});

Route::group(['prefix' => 'configurations'], function () {
    Route::group(['middleware' => ['web']], function () {
        Route::post('addConfigParam', 'ConfigurationsController@saveConfigParam');
        Route::get('getConfigParam/{model}', 'ConfigurationsController@getConfigParam');
        Route::post('deleteRecord', 'ConfigurationsController@deleteConfigRecord');
        Route::post('deleteTemplateColRecord', 'ConfigurationsController@deleteTemplateColRecord');
        Route::post('saveCommonData', 'ConfigurationsController@saveConfigCommonData');
        Route::post('saveEditorData', 'ConfigurationsController@saveDataFromEditor');
        Route::get('getTemplateInfo', 'ConfigurationsController@getTemplateInformation');
        Route::post('addColumns', 'ConfigurationsController@addColumns');
        Route::get('getExistingCols', 'ConfigurationsController@getExistingColumns');
        Route::get('downloadTemplate/{template_id}', 'ConfigurationsController@downloadTemplate');
        Route::post('saveTemplateColumn', 'ConfigurationsController@saveTemplateColumn');
        Route::get('getDuplicateParams', 'ConfigurationsController@getDuplicateParams');
        Route::post('saveChecklistType', 'ConfigurationsController@saveChecklistType');
        Route::get('getChecklistItems', 'ConfigurationsController@getChecklistItems');
        Route::get('getAnswerTypes', 'ConfigurationsController@getAnswerTypes');
        Route::post('addChecklistQuestion', 'ConfigurationsController@addChecklistQuestion');
        Route::post('getAnswerOptionsSetup', 'ConfigurationsController@getAnswerOptionsSetup');
        Route::post('deleteChecklistQuiz', 'ConfigurationsController@deleteChecklistQuiz');
        Route::get('getChecklistOptions', 'ConfigurationsController@getChecklistOptions');
        Route::post('addFieldTeamMembers', 'ConfigurationsController@addFieldTeamMembers');
        Route::post('addFieldTeamProvinces', 'ConfigurationsController@addFieldTeamProvinces');
        Route::get('getFieldTeamMembers', 'ConfigurationsController@getFieldTeamMembers');
        Route::get('getFieldTeamProvinces', 'ConfigurationsController@getFieldTeamProvinces');
        Route::get('getUnselectedTeamMembers', 'ConfigurationsController@getUnselectedTeamMembers');
        Route::get('getUnselectedTeamProvinces', 'ConfigurationsController@getUnselectedTeamProvinces');
        Route::get('getChildModules', 'ConfigurationsController@getChildModules');
        Route::get('getParentModules', 'ConfigurationsController@getParentModules');
        Route::post('saveMISDMSModuleItem', 'ConfigurationsController@saveMISDMSModuleItem');
        Route::get('getMISDMSModules', 'ConfigurationsController@getMISDMSModules');
        Route::post('syncMISModulesToDMS', 'ConfigurationsController@syncMISModulesToDMS');
        Route::post('saveDmsConnectionConfigs', 'ConfigurationsController@saveDmsConnectionConfigs');
        Route::post('saveLetterDates', 'ConfigurationsController@saveLetterDates');
        Route::get('previewVerificationChecklistTemplate', 'ConfigurationsController@previewVerificationChecklistTemplate');
        Route::get('previewOtherChecklistTemplate', 'ConfigurationsController@previewOtherChecklistTemplate');
        Route::post('markPromotionMonth', 'ConfigurationsController@markPromotionMonth');
        Route::get('getPromotionMonth', 'ConfigurationsController@getPromotionMonth');
        Route::get('getTemplateLastTabIndex', 'ConfigurationsController@getTemplateLastTabIndex');
        Route::get('getSchoolFeesAmendmentsSetup', 'ConfigurationsController@getSchoolFeesAmendmentsSetup');
        Route::post('saveSchoolFeesAmendmentsSetup', 'ConfigurationsController@saveSchoolFeesAmendmentsSetup');
        Route::get('getWeeklyBordersTopUp', 'ConfigurationsController@getWeeklyBordersTopUp');
        Route::post('saveWeeklyBordersTopUp', 'ConfigurationsController@saveWeeklyBordersTopUp');
        Route::get('getChecklistTypes', 'ConfigurationsController@getChecklistTypes');
        Route::get('getBatchChecklistTypesLinkage', 'ConfigurationsController@getBatchChecklistTypesLinkage');
        Route::post('updateEBatchesPaymentSetup', 'ConfigurationsController@updateEBatchesPaymentSetup');
        Route::get('getEBatchesPaymentSetup', 'ConfigurationsController@getEBatchesPaymentSetup');
        Route::get('getProcessStatuses', 'ConfigurationsController@getProcessStatuses');
        //Added by Frank
        Route::get('saveConfigParamData', 'ConfigurationsController@saveConfigModuleCommonData');
        Route::get('getConfigParamData', 'ConfigurationsController@getConfigModuleParamFromTable');
        Route::post('exportConfigRecords', 'ConfigurationsController@exportConfigRecords');

        Route::post('saveParameterSetupInfo', 'ConfigurationsController@saveParameterSetupInfo');
        Route::get('getParameterSetUpInfo', 'ConfigurationsController@getParameterSetUpInfo');
        Route::get('getParameterGridCols', 'ConfigurationsController@getParameterGridCols');
        Route::get('getParameterGridResultSet', 'ConfigurationsController@getParameterGridResultSet');
        Route::get('getParameterComboResultSet', 'ConfigurationsController@getParameterComboResultSet');
        Route::delete('deleteGenericParameterProperty', 'ConfigurationsController@deleteGenericParameterProperty');
        Route::post('deleteRecordWithComments','ConfigurationsController@deleteRecordWithComments');

          Route::get('getGrantAidedGCEExternalTopUp', 'ConfigurationsController@getGrantAidedGCEExternalTopUp');
        Route::post('saveGrantAidedGCEExternalTopUp', 'ConfigurationsController@saveGrantAidedGCEExternalTopUp');
    });
});
