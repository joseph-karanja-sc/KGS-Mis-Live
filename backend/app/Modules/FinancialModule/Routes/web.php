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
Route::prefix('financialmodule')->group(function() {
    Route::get('/', 'FinancialModuleController@index');
    Route::get('getBudgets','FinancialModuleController@getallBudgets');
    Route::get('getFinancialManagementParamFromTable', 'FinancialModuleController@getFinancialManagementParamFromTable');
    Route::post('saveBudgetDetails', 'FinancialModuleController@savebudgetdetails');
    Route::get('getActivities', 'FinancialModuleController@getallActivities');
    Route::get('getWorkplans', 'FinancialModuleController@getallWorkplans');
    Route::get('getWorkplanUsers', 'FinancialModuleController@getalltaskUsers');
    Route::get('getWorkplanComments', 'FinancialModuleController@getallTaskComments');
    Route::post('saveWorkplantask', 'FinancialModuleController@saveWorkplantasks');
    Route::get('getWorkPlanCountPerThematicArea', 'FinancialModuleController@getWorkPlanCountPerThematicArea');
    Route::get('getWorkPlanCountPerCostCentre', 'FinancialModuleController@getWorkPlanCountPerCostCentre');
    Route::get('getWorkPlanCountPerProgramme', 'FinancialModuleController@getWorkPlanCountPerProgramme');
    Route::post('saveWorkplanusers', 'FinancialModuleController@saveWorkplanusers');
    Route::post('saveCommentsDetails', 'FinancialModuleController@saveWorkPlanCommentsDetails');
    Route::get('getImplementationcostingrec', 'FinancialModuleController@getAllImplementationcostingrec');
    Route::post('updateworkplanstatus', 'FinancialModuleController@updateworkplanstatus');
    Route::get('getcostinglist', 'FinancialModuleController@getAllcostinglist');
    Route::post('saveCost', 'FinancialModuleController@saveCost');
    Route::post('saveActivities', 'FinancialModuleController@saveActivities');
    Route::get('getCurrencyRates', 'FinancialModuleController@getCurrencyRates');
    Route::post('updateImplementationplan', 'FinancialModuleController@assignedtasks');
    Route::get('getActivitiesdue', 'FinancialModuleController@getActivitiesdue');
    Route::get('getFinaltaskAssignedList', 'FinancialModuleController@getFinaltaskAssignedList');
    Route::get('getSchedule', 'FinancialModuleController@getschedulerecords');
    Route::get('calenderevents', 'FinancialModuleController@calenderevents');
    Route::post('deleteRecord', 'FinancialModuleController@deleteWorkplanRecord');
    Route::post('archiveplan', 'FinancialModuleController@archiveplan');
    Route::post('uploadBudget', 'FinancialModuleController@uploadBudget');
    Route::get('getActivitiesOverBudget', 'FinancialModuleController@getActivitiesOverBudget');  
    Route::get('getRequisitionList', 'FinancialModuleController@getAllRequisitionList');
    Route::post('saveRequisitionDetails', 'FinancialModuleController@saveRequisitionDet');
    Route::get('printcommitmentReq','FinancialModuleController@printcommitmentReqForm');
    //frank Reports
    Route::post('saveBackingSheetDetails', 'FinancialReportsController@saveBackingSheetDetails');
    Route::get('getBackingSheetDetails', 'FinancialReportsController@getBackingSheetDetails');
    Route::get('getAprvdImplPlanDetails', 'FinancialReportsController@getAprvdImplPlanDetails');
    Route::get('getWorkplanDetailsForCashbk', 'FinancialReportsController@getWorkplanDetailsForCashbk');
    Route::post('uploadFinancialReceipts', 'FinancialReportsController@uploadFinancialReceipts');
    Route::get('getDollarBackingSheetDetails', 'FinancialReportsController@getDollarBackingSheetDetails');
    Route::get('getBozBackingSheetDetails', 'FinancialReportsController@getBozBackingSheetDetails');
    Route::post('saveBozBackingSheetDetails', 'FinancialReportsController@saveBozBackingSheetDetails');
    Route::post('saveDollarBackingSheetDetails', 'FinancialReportsController@saveDollarBackingSheetDetails');    
    Route::get('getFinanceDashboardData', 'FinancialReportsController@getFinanceDashboardData');    
    Route::post('setSelectedDate', 'FinancialReportsController@setSelectedDate');    
    Route::get('generateFinancialReport', 'FinancialReportsController@generateFinancialReport'); 
    Route::get('manualPromotionProcess', 'FinancialReportsController@manualPromotionProcess');
    Route::get('manualPromotionProcessRollBack', 'FinancialReportsController@manualPromotionProcessRollBack');    
    Route::post('saveDollarAccountDetails', 'FinancialReportsController@saveDollarAccountDetails');    
    Route::get('onBackingSheetSelect', 'FinancialReportsController@onBackingSheetSelect');
    Route::post('saveBozAccountDetails', 'FinancialReportsController@saveBozAccountDetails');        
    Route::post('saveZanacoCashbookDetails', 'FinancialReportsController@saveZanacoCashbookDetails');           
    Route::post('saveFinancialModuleCommonData', 'FinancialReportsController@saveFinancialModuleCommonData');    
    
    Route::get('getFiscalYears', 'FinancialReportsController@getFiscalYears');        
    Route::get('getAccountOpeningBalances', 'FinancialReportsController@getAccountOpeningBalances');        
});