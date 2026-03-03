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

/* Route::prefix('audittrail')->group(function() {
    Route::get('/', 'AuditTrailController@index');
}); */


use Illuminate\Support\Facades\Route;

Route::prefix('audittrail')->group(function () {
    Route::get('/', 'AuditTrailController@index');
    Route::group(['middleware' => ['web']], function () {
        Route::get('getMisAuditTrail', 'AuditTrailController@getMisAuditTrail');
        Route::get('getPortalAuditTrail', 'AuditTrailController@getPortalAuditTrail');
        Route::get('getPortalAuditTableData', 'AuditTrailController@getPortalAuditTableData');
        Route::get('getMISAuditTableData', 'AuditTrailController@getMISAuditTableData');
        Route::get('revertAuditRecord', 'AuditTrailController@revertAuditRecord');
        Route::get('getTableslist', 'AuditTrailController@getTableslist');
        Route::get('getAllAuditTrans', 'AuditTrailController@getAllAuditTrans');
        Route::get('exportAudit', 'AuditTrailController@exportAudit');
        Route::get('getAllUsers/{table}/{id?}', 'AuditTrailController@getAllUsers');
        Route::get('getloginLogs', 'AuditTrailController@getloginLogs');
        Route::get('getloginAttemptsLogs', 'AuditTrailController@getloginAttemptsLogs');
        Route::get('getSystemErrorLogs', 'AuditTrailController@getSystemErrorLogs');
        Route::post('markErrorLogAsResolved', 'AuditTrailController@markErrorLogAsResolved');   
        Route::get('getUserAccessLogs', 'AuditTrailController@getUserAccessLogs');
        Route::get('getUserLoginLogs', 'AuditTrailController@getUserLoginLogs');
        Route::get('getSystemAuditTrailLogs', 'AuditTrailController@getSystemAuditTrailLogs');
    });
});

// Route::group(['middleware' => 'web', 'prefix' => 'audittrail', 'namespace' => 'App\\Modules\AuditTrail\Http\Controllers'], function(){});
