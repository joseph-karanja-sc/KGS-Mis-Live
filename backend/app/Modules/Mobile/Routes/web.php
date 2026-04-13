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

/*Route::prefix('mobile')->group(function() {
    Route::get('/', 'MobileController@index');
});*/

//Route::group(['middleware' => 'web', 'prefix' => 'mobile', 'namespace' => 'App\\Modules\Mobile\Http\Controllers'], function()
//{
Route::group(['prefix' => 'mobile'], function () {
	//reusable routes
	Route::post('syncGrm', 'MobileController@syncGrm');
	// MNE
    Route::post('post-mne-data', 'MobileController@postMNEData');
	Route::get('get-loggedin-user', 'MobileController@getLoggedInUser');
});
