<?php

use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/*Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});*/

// added by joseph
Route::post('api-login', [AuthController::class, 'handleLogin']);
Route::get('get-user-details-mobile', [AuthController::class, 'getDecryptedUserDataByUuidMobile']);


// Route::get('/test-live', function () {
//     return 'System is up and running';
// });
