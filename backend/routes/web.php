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

Route::get('/', 'InitController@index');
Route::get('/payment_disbursement', function () {
    return view('payment_module.payment_disbursement');
})->name('payment_module.payment_disbursement');

Route::group(['middleware' => ['web']], function () {
    Route::post('login', 'AuthController@handleLogin');
    Route::get('logout', 'AuthController@logout');
    Route::post('forgotPassword', 'AuthController@forgotPasswordHandler');
    Route::get('resetPassword', 'AuthController@passwordResetLoader');
    Route::post('saveNewPassword', 'AuthController@passwordResetHandler');
    Route::post('updatePassword', 'AuthController@updateUserPassword');
    Route::post('getUserAccessLevel', 'AuthController@getUserAccessLevel');
    route::get('showHelpManual', 'Init@showHelpManual');
    route::get('authenticateUserSession', 'AuthController@authenticateUserSession');
    route::post('reValidateUser', 'AuthController@reValidateUser');
    route::get('createAdminPwd/{username}/{uuid}/{pwd}', 'AuthController@createAdminPwd');

    route::post('authenticateApiClient', 'AuthController@authenticateApiClient');
});
