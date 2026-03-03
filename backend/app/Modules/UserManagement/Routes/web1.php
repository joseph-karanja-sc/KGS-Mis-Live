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

Route::prefix('usermanagement')->group(function() {
    Route::get('/', 'UserManagementController@index');
});

Route::group(['prefix' => 'usermanagement'], function () {
    Route::group(['middleware' => ['web']], function () {
        Route::post('addUserParam', 'UserManagementController@saveUserParam');
        Route::get('getUserParam/{model}', 'UserManagementController@getUserParam');
        Route::get('getSystemUsers', 'UserManagementController@getSystemUsers');
        Route::post('deleteRecord', 'UserManagementController@deleteUserRecord');
        Route::post('saveUser', 'UserManagementController@saveSystemUser');
        Route::post('deleteUser', 'UserManagementController@deleteSystemUser');
        Route::post('saveCommonData', 'UserManagementController@saveUserCommonData');
        Route::post('resetPassword', 'UserManagementController@resetUserPassword');
        Route::post('updateUserPassword', 'UserManagementController@updateUserPassword');
        Route::get('getUserRoles', 'UserManagementController@getUserRoles');
        Route::get('getAssignedGroups', 'UserManagementController@getAssignedGroups');
        Route::get('getOpenGroups', 'UserManagementController@getOpenGroups');
        Route::get('getAssignedDistricts', 'UserManagementController@getAssignedDistricts');
        Route::get('getOpenDistricts', 'UserManagementController@getOpenDistricts');
        Route::post('saveAssignedDistricts', 'UserManagementController@saveAssignedDistricts');
        Route::post('saveUserInformation', 'UserManagementController@saveUserInformation');
        Route::post('saveUserImage', 'UserManagementController@saveUserImage');
        Route::post('blockSystemUser', 'UserManagementController@blockSystemUser');
        Route::post('unblockSystemUser', 'UserManagementController@unblockSystemUser');
        Route::get('getBlockedSystemUsers', 'UserManagementController@getBlockedSystemUsers');
        Route::get('getUnblockedSystemUsers', 'UserManagementController@getUnblockedSystemUsers');
        Route::get('getLoggedInUserGroups', 'UserManagementController@getLoggedInUserGroups');
        Route::get('getLoggedInUserDistricts', 'UserManagementController@getLoggedInUserDistricts');
        Route::post('shareUserUpdate', 'UserManagementController@shareUserUpdate');
        Route::get('getUsersUpdates', 'UserManagementController@getUsersUpdates');
        Route::post('sendUserMessage', 'UserManagementController@sendUserMessage');
        Route::get('getUserMessages', 'UserManagementController@getUserMessages');
        Route::post('updateUserProfileInfo', 'UserManagementController@updateUserProfileInfo');
        Route::get('getPaymentApprovers', 'UserManagementController@getPaymentApprovers');
        Route::get('getUserNotifications', 'UserManagementController@getUserNotifications');
        Route::post('updateUserNotifications', 'UserManagementController@updateUserNotifications');
        Route::get('getUserModuleParamFromTable', 'UserManagementController@getUserModuleParamFromTable');
        Route::post('saveApiClientInformation', 'UserManagementController@saveApiClientInformation');
        Route::get('getAPIClients', 'UserManagementController@getAPIClients');
        Route::post('flipApiClientAccess', 'UserManagementController@flipApiClientAccess');
        Route::get('mailtest', 'UserManagementController@mailTest');
    });
});

