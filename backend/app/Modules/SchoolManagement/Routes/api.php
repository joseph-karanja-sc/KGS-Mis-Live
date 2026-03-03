<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SchoolManagementController;

/*
|--------------------------------------------------------------------------
| API Routes for Auth Module
|--------------------------------------------------------------------------
|
| These routes are loaded by the RouteServiceProvider. We add a prefix
| so all routes automatically use /api/zispis/v1 as the base.
|
*/

Route::prefix('school_management')->group(function () {
     //test
     Route::get('/test-live', function () {
        return 'pg api is up and running';
    });
    // Route::get('/schools/bank-details', [SchoolManagementController::class, 'getSchoolBankDetails']);
    Route::get('/schools/bank-details', 'SchoolManagementController@getSchoolBankDetails');

});