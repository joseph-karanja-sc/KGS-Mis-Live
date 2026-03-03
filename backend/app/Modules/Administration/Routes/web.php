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

Route::prefix('administration')->group(function() {
    Route::get('/', 'AdministrationController@index');
});

Route::get('MenuAccessLevel_Md', 'AdministrationController@getMenuAccessLevelData');
Route::group(['prefix' => 'administration'], function () {
    Route::group(['middleware' => ['web']], function () {
        Route::get('navigationMenus', 'AdministrationController@getNavigationItems');
        Route::post('addAdminParam', 'AdministrationController@saveAdminParam');
        Route::get('getAdminParam/{model}', 'AdministrationController@getAdminParam');
        Route::post('deleteRecord', 'AdministrationController@deleteAdminRecord');
        Route::post('deleteAdminRecord', 'AdministrationController@deleteAdminRecord');
        Route::get('getSystemMenus', 'AdministrationController@getSystemMenus');
        Route::get('getSystemMenus2', 'AdministrationController@getSystemMenus2');
        Route::get('getParentMenus', 'AdministrationController@getParentMenus');
        Route::get('getChildMenus', 'AdministrationController@getChildMenus');
        Route::post('addMenuItem', 'AdministrationController@saveMenuItem');
        Route::get('getSystemRoles', 'AdministrationController@getSystemRoles');
        Route::post('changeGroupAccessLevel', 'AdministrationController@updateAccessRoles');
        Route::post('updateSystemNavigationAccessRoles', 'AdministrationController@updateSystemNavigationAccessRoles');
        Route::post('updateSystemComponentsAccessRoles', 'AdministrationController@updateSystemComponentsAccessRoles');
        Route::post('saveCommonData', 'AdministrationController@saveAdminCommonData');
        Route::get('getUserGroups', 'AdministrationController@getGroups');
        Route::post('addUserGroup', 'AdministrationController@addUserGroup');
        Route::post('deleteUserGroup', 'AdministrationController@deleteUserGroup');
        Route::get('getSystem_guidelinesStr', 'AdministrationController@getSystem_guidelines');
        Route::post('saveGuidelinesdetails', 'AdministrationController@saveGuidelinesdetails');
        Route::get('getAccessPointUsers', 'AdministrationController@getAccessPointUsers');
        Route::get('getAdminModuleParamFromTable', 'AdministrationController@getAdminModuleParamFromTable');
        Route::get('getWorkflowStages', 'AdministrationController@getWorkflowStages');
        Route::get('getWorkflowTransitions', 'AdministrationController@getWorkflowTransitions');
        Route::get('showWorkflowDiagram', 'AdministrationController@showWorkflowDiagram');
        Route::get('getInitialWorkflowDetails', 'AdministrationController@getInitialWorkflowDetails');
        Route::get('getAllWorkflowDetails', 'AdministrationController@getAllWorkflowDetails');
        Route::get('getBasicWorkflowDetails', 'AdministrationController@getBasicWorkflowDetails');
        Route::get('getWorkflowActions', 'AdministrationController@getWorkflowActions');
        Route::get('getSubmissionWorkflowStages', 'AdministrationController@getSubmissionWorkflowStages');
        Route::get('getSubmissionNextStageDetails', 'AdministrationController@getSubmissionNextStageDetails');
        Route::post('updateRecordNotificationReadingFlag', 'AdministrationController@updateRecordNotificationReadingFlag');
        Route::get('getMenuItemAssignedUsers', 'AdministrationController@getMenuItemAssignedUsers');
        Route::get('getProcesses', 'AdministrationController@getProcesses');
        Route::get('getProcessStageMaxDaysSetup', 'AdministrationController@getProcessStageMaxDaysSetup');
        Route::post('saveProcessStageMaxDaysSetup', 'AdministrationController@saveProcessStageMaxDaysSetup');
        Route::get('getSystemComponentsRoles', 'AdministrationController@getSystemComponentsRoles');
        Route::post('removeSelectedUsersFromGroup', 'AdministrationController@removeSelectedUsersFromGroup');
        Route::post('exportAdminRecords', 'AdministrationController@exportAdminRecords');
    });
});
