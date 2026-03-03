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

Route::prefix('statusesmanagement')->group(function() {
    Route::get('/', 'StatusesManagementController@index');
});

Route::group(['prefix' => 'statusesmanagement'], function () {
    Route::group(['middleware' => ['web']], function () {
        Route::post('addStatusParam', 'StatusesManagementController@saveStatusParam');
        Route::get('getStatusParam/{model}', 'StatusesManagementController@getStatusParam');
        Route::post('saveCommonData', 'StatusesManagementController@saveStatusCommonData');
        Route::post('deleteRecord', 'StatusesManagementController@deleteStatusRecord');
    });
});
