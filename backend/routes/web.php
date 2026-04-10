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

//pg panel a
Route::get('/payment_disbursement_panelA', function () {
    return view('payment_module.payment_disbursement_panelA');
})->name('payment_module.payment_disbursement_panelA');

//pg panel b
Route::get('/payment_disbursement_panelB', function () {
    return view('payment_module.payment_disbursement_panelB');
})->name('payment_module.payment_disbursement_panelB');

//pg transaction statuses
Route::get('/pg_transactions_report', function () {
    return view('payment_module.pg_transactions_report');
})->name('payment_module.pg_transactions_report');

// pg re-trials
Route::get('/pg_retrials', function () {
    return view('payment_module.disbursement_retrials');
})->name('payment_module.disbursement_retrials');
Route::get('/pg_sch_retrials', function () {
    return view('payment_module.disbursement_sch_retrials');
})->name('payment_module.disbursement_sch_retrials');

//sa app trans statuses
Route::get('/sa_trans_statuses', function () {
    return view('payment_module.school_acc_trans_status');
})->name('payment_module.school_acc_trans_status');

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
