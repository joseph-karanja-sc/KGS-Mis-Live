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

Route::prefix('dashboards')->group(function() {
    Route::get('/', 'DashboardsController@index');
});

Route::group(['prefix' => 'dashboards'], function () {

    Route::group(['middleware' => ['web']], function () {
        Route::get('getBeneficiaryEnrollmentsRpt', 'DashboardsController@getBeneficiaryEnrollmentsRpt');
        Route::get('getBeneficiaryAnnualEnrollmentsRpt', 'DashboardsController@getBeneficiaryAnnualEnrollmentsRpt');
        Route::get('getPayment_vericationsummaryStr', 'DashboardsController@getPayment_vericationsummaryStr');
        Route::get('getPayment_requestssubsummaryStr', 'DashboardsController@getPayment_requestssubsummaryStr');
        Route::get('logDelete', 'DashboardsController@logDelete');
    });
});

