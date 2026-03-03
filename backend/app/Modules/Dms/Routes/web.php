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

Route::prefix('dms')->group(function() {
    Route::get('/', 'DmsController@index');
});

Route::group(['prefix' => 'dms'], function () {
    Route::group(['middleware' => ['web']], function () {
        Route::post('addDocument', 'DmsController@addDocument');
        Route::post('addDocumentNoFolderId', 'DmsController@addDocumentNoFolderId');
        Route::get('getSubModuleFolderID', 'DmsController@getSubModuleFolderID');
    });
});
