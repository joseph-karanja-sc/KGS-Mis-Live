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

Route::prefix('reportsmodule')->group(function() {
    //Route::get('/', 'ReportsModuleController@index');
});

Route::group(['prefix' => 'reports'], function () {
    Route::group(['middleware' => ['web']], function () {
        Route::get('printInSchoolVerificationChecklists/{batch_id}/{provinces}/{districts}/{schools}/{checker}/{beneficiary_status}', 'ReportsCurrent@printInSchoolVerificationChecklists');
        Route::get('printOutOfSchoolVerificationChecklists/{batch_id}/{provinces}/{districts}/{cwacs}/{checker}/{beneficiary_status}', 'ReportsCurrent@printOutOfSchoolVerificationChecklists');
        Route::get('printOutOfSchMatchingForms/{batch_id}/{provinces}/{districts}/{print_filter}', 'ReportsCurrent@printOutOfSchMatchingForms');
        Route::get('printExamClassesVerificationChecklists/{batch_id}/{provinces}/{districts}/{schools}/{checker}', 'ReportsCurrent@printExamClassesVerificationChecklists');
        Route::get('printSchPlacementForms/{batch_id}/{provinces}/{districts}/{schools}/{print_filter}', 'ReportsCurrent@printSchPlacementForms');
        //Route::get('printOfferLetters/{batch_id}/{category}/{provinces}/{districts}/{schools}/{print_filter}', 'ReportsCurrent@printOfferLetters');
        Route::get('printOfferLetters', 'ReportsCurrent@printOfferLetters');
        Route::get('printProvisionalOfferLetters', 'ReportsCurrent@printProvisionalOfferLetters');
        Route::get('printCapacityAssessmentsForms/{year}/{provinces}/{districts}/{schools}', 'ReportsCurrent@printCapacityAssessmentsForms');
        //Route::get('printInSchoolSpecificChecklists/{batch_id}/{school_id}/{print_type}', 'ReportsCurrent@printInSchoolSpecificChecklists');
        Route::get('printInSchoolSpecificChecklists', 'ReportsCurrent@printInSchoolSpecificChecklists');
        //Route::get('printOutofSchoolSpecificChecklists/{batch_id}/{cwac_id}/{print_type}', 'ReportsCurrent@printOutofSchoolSpecificChecklists');
        Route::get('printOutofSchoolSpecificChecklists', 'ReportsCurrent@printOutofSchoolSpecificChecklists');
        Route::get('printSpecificOfferLetters/{batch_id}/{school_id}/{category}', 'ReportsCurrent@printSpecificOfferLetters');
        Route::get('printSingleOfferLetter/{girl_id}/{category}', 'ReportsCurrent@printSingleOfferLetter');
        Route::get('newTestRoute/{girl_id}/{category}', 'ReportsCurrent@newTestRoute');
        Route::get('downloadOfferLetter/{girl_id}/{category}/{log_id}/{track_id}', 'ReportsCurrent@downloadOfferLetter');
        Route::get('printGradeNineSchPlacementForms/{provinces}/{districts}/{schools}', 'ReportsCurrent@printGradeNineSchPlacementForms');
        /************* Payment Module hiram************/
        Route::get('getBeneficiaryenrollmentRpt', 'Reports_module@getBeneficiaryenrollmentRpt');
        Route::get('getBenEnrollmentstatuses', 'Reports_module@getBenEnrollmentstatuses');
        Route::get('printBeneficiarysummaryDetails', 'Reports_module@printBeneficiarysummaryDetails');
        Route::get('exportBeneficiarysummaryDetails', 'Reports_module@exportBeneficiarysummaryDetails');
        Route::get('printBeneficiaryDetailedrpt', 'Reports_module@printBeneficiaryDetailedrpt');
        Route::get('exportBeneficiaryDetailedrpt', 'Reports_module@exportBeneficiaryDetailedrpt');
        Route::get('exportDataBeneficiaryDetailedrpt', 'Reports_module@exportDataBeneficiaryDetailedrpt');
        Route::get('getTermlyEnrollmentDetailsRpt', 'Reports_module@getTermlyEnrollmentDetailsRpt');
        Route::get('printTermlysummaryDetails', 'Reports_module@printTermlysummaryDetails');
        Route::get('exportTermlysummaryDetails', 'Reports_module@exportTermlysummaryDetails');
        Route::get('printTermlyenrollmentDetailedrpt', 'Reports_module@printTermlyenrollmentDetailedrpt');
        Route::get('exportTermlyenrollmentDetailedrpt', 'Reports_module@exportTermlyenrollmentDetailedrpt');
        Route::get('exportDataTermlyDetailedrpt', 'Reports_module@exportDataTermlyDetailedrpt');
        Route::get('getBenhome_Districts', 'Reports_module@getBenhome_Districts');
        Route::get('getPayment_disbursementrptStr', 'Reports_module@getPayment_disbursementrptStr');
        /************* End ***********************/
        /*====================*/
        //SCHOOL MANAGEMENT REPORTS
        /*====================*/
        Route::get('printSchoolMonitoringForm', 'SchManagement_module@printSchoolMonitoringForm');
        Route::get('printSchoolMonitoringReport', 'SchManagement_module@printSchoolMonitoringReport');
        Route::get('generateSchoolMonitoringForms', 'SchManagement_module@generateSchoolMonitoringForms');
        //the spreadhseet details
        Route::get('getBeneficiaryspreadsheetstr', 'Reports_module@getBeneficiaryspreadsheetstr');
        Route::get('exportBeneficiaryspreadsheet', 'Reports_module@exportBeneficiaryspreadsheet');
        Route::get('getBeneficiarypaymentsspreadsheetstr', 'Reports_module@getBeneficiarypaymentsspreadsheetstr');
        Route::get('exportcompleteBeneficiaryspreadsheet', 'Reports_module@exportcompleteBeneficiaryspreadsheet');

        Route::get('exportenrollmentBeneficiaryspreadsheet', 'Reports_module@exportenrollmentBeneficiaryspreadsheet');
        Route::get('exportenrollmentcompleteBeneficiaryspreadsheet', 'Reports_module@exportenrollmentcompleteBeneficiaryspreadsheet');
        //KIP
        Route::get('getImportationDataReports', 'Reports_module@getImportationDataReports');
        Route::get('getImportationDataReportsPerHomeDistrict', 'Reports_module@getImportationDataReportsPerHomeDistrict');
        Route::get('getImportationDataReportsPerSchoolDistrict', 'Reports_module@getImportationDataReportsPerSchoolDistrict');
        Route::get('getImportationBrief', 'Reports_module@getImportationBrief');
        Route::get('getAssessmentDataReports', 'Reports_module@getAssessmentDataReports');
        Route::get('getAssessmentDataReportsPerHomeDistrict', 'Reports_module@getAssessmentDataReportsPerHomeDistrict');
        Route::get('getAssessmentDataReportsPerSchoolDistrict', 'Reports_module@getAssessmentDataReportsPerSchoolDistrict');
        Route::get('getAssessmentBrief', 'Reports_module@getAssessmentBrief');
        Route::get('getBeneficiariesPerBenStatus', 'Reports_module@getBeneficiariesPerBenStatus');
        Route::get('getBenStatusesBrief', 'Reports_module@getBenStatusesBrief');
        Route::get('getBenStatusesDataReportsPerHomeDistrict', 'Reports_module@getBenStatusesDataReportsPerHomeDistrict');
        Route::get('getBenStatusesDataReportsPerSchoolDistrict', 'Reports_module@getBenStatusesDataReportsPerSchoolDistrict');
        Route::get('getMappingBrief', 'Reports_module@getMappingBrief');
        Route::get('getMappingDataReports', 'Reports_module@getMappingDataReports');
        Route::get('getMappingDataReportsPerHomeDistrict', 'Reports_module@getMappingDataReportsPerHomeDistrict');
        Route::get('getMappingDataReportsPerSchoolDistrict', 'Reports_module@getMappingDataReportsPerSchoolDistrict');
        Route::get('getVerificationBrief', 'Reports_module@getVerificationBrief');
        Route::get('getVerificationDetailed', 'Reports_module@getVerificationDetailed');
        Route::get('getVerInSchoolDataReportsPerHomeDistrict', 'Reports_module@getVerInSchoolDataReportsPerHomeDistrict');
        Route::get('getVerOutSchoolDataReportsPerHomeDistrict', 'Reports_module@getVerOutSchoolDataReportsPerHomeDistrict');
        Route::get('getVerExamClassesDataReportsPerHomeDistrict', 'Reports_module@getVerExamClassesDataReportsPerHomeDistrict');
        Route::get('getVerInSchoolPerHomeDistrict', 'Reports_module@getVerInSchoolPerHomeDistrict');
        Route::get('getVerOutSchoolPerHomeDistrict', 'Reports_module@getVerOutSchoolPerHomeDistrict');
        Route::get('getVerExamClassesPerHomeDistrict', 'Reports_module@getVerExamClassesPerHomeDistrict');
        Route::get('getVerExamClassesPerSchoolDistrict', 'Reports_module@getVerExamClassesPerSchoolDistrict');
        Route::get('getVerInSchoolPerSchoolDistrict', 'Reports_module@getVerInSchoolPerSchoolDistrict');
        Route::get('getSchoolMatchingBrief', 'Reports_module@getSchoolMatchingBrief');
        Route::get('getSchoolMatchingChartData', 'Reports_module@getSchoolMatchingChartData');
        Route::get('getSchoolMatchingDistrictDistribution', 'Reports_module@getSchoolMatchingDistrictDistribution');
        Route::get('getSchoolMatchingSchoolDistribution', 'Reports_module@getSchoolMatchingSchoolDistribution');
        Route::get('getSchoolPlacementBrief', 'Reports_module@getSchoolPlacementBrief');
        Route::get('getSchoolPlacementChartData', 'Reports_module@getSchoolPlacementChartData');
        Route::get('getSchoolPlacementDistrictDistribution', 'Reports_module@getSchoolPlacementDistrictDistribution');
        Route::get('getSchoolPlacementSchoolDistribution', 'Reports_module@getSchoolPlacementSchoolDistribution');
        Route::get('getSchPlacementDistributionBrief', 'Reports_module@getSchPlacementDistributionBrief');
        Route::get('getSchoolMatchingDistributionBrief', 'Reports_module@getSchoolMatchingDistributionBrief');
        Route::get('getBeneficiariesResponseRate', 'Reports_module@getBeneficiariesResponseRate');
        Route::get('getPerformanceAttendanceReport', 'Reports_module@getPerformanceAttendanceReport');
        Route::get('getEnrollmentResponse', 'Reports_module@getEnrollmentResponse');
        Route::get('getBenEnrollmentStatusesBrief', 'Reports_module@getBenEnrollmentStatusesBrief');
        Route::get('getEnrollmentStatusesDataReportsPerHomeDistrict', 'Reports_module@getEnrollmentStatusesDataReportsPerHomeDistrict');
        Route::get('getBenEnrollmentStatusesPerSchoolDistrict', 'Reports_module@getBenEnrollmentStatusesPerSchoolDistrict');
        Route::get('getEnrollmentsPerHomeDistricts', 'Reports_module@getEnrollmentsPerHomeDistricts');
        Route::get('getEnrollmentsPerHomeDistrictsCWAC', 'Reports_module@getEnrollmentsPerHomeDistrictsCWAC');
        Route::get('getEnrollmentsPerSchoolDistricts', 'Reports_module@getEnrollmentsPerSchoolDistricts');
        Route::get('getTermlySchoolsDisbursementsRpt', 'Reports_module@getTermlySchoolsDisbursementsRpt');
        Route::get('getBeneficiariesMoreResponseRate', 'Reports_module@getBeneficiariesMoreResponseRate');
        Route::get('getBeneficiariesMoreSchStatusesResponseRate', 'Reports_module@getBeneficiariesMoreSchStatusesResponseRate');
        Route::get('getUnresponsiveBeneficiariesMainReport', 'Reports_module@getUnresponsiveBeneficiariesMainReport');
        Route::get('getUnresponsiveBeneficiaries', 'Reports_module@getUnresponsiveBeneficiaries');
        Route::get('printVerificationData', 'Reports_module@printVerificationData');
        Route::get('getSchoolFacilityTypes', 'Reports_module@getSchoolFacilityTypes');
        Route::get('getInitialAnnualEnrollments', 'Reports_module@getInitialAnnualEnrollments');
        Route::get('getBeneficiaryAnnualEnrollmentsRptTabular', 'Reports_module@getBeneficiaryAnnualEnrollmentsRptTabular');
        Route::get('getAnnualTakeUpStatusReport', 'Reports_module@getAnnualTakeUpStatusReport');
        Route::get('getEnrollmentProgressionCriteria', 'Reports_module@getEnrollmentProgressionCriteria');
        Route::get('getBeneficiaryAnnualEnrollmentCompletion', 'Reports_module@getBeneficiaryAnnualEnrollmentCompletion');
        Route::get('getBeneficiaryEnrolments', 'Reports_module@getBeneficiaryEnrolments');
        Route::get('getExamFeesReport', 'Reports_module@getExamFeesReport');
        Route::get('getExamFeesBeneficiariesReport', 'Reports_module@getExamFeesBeneficiariesReport');
        Route::post('exportReportsModuleRecords', 'Reports_module@exportReportsModuleRecords');
        //front office
        Route::get('getAllBeneficiaryStatuses', 'Reports_module@getAllBeneficiaryStatuses');
        //Test
        Route::get('testJasper', 'ReportsCurrent@testJasper');
    });
});



//<?php

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
/*use Illuminate\Support\Facades\Route;

Route::prefix('reportsmodule')->group(function() {
    //Route::get('/', 'ReportsModuleController@index');
});

Route::group(['prefix' => 'reports'], function () {
    Route::group(['middleware' => ['web']], function () {
        Route::get('printInSchoolVerificationChecklists/{batch_id}/{provinces}/{districts}/{schools}/{checker}/{beneficiary_status}', 'ReportsCurrent@printInSchoolVerificationChecklists');
        Route::get('printOutOfSchoolVerificationChecklists/{batch_id}/{provinces}/{districts}/{cwacs}/{checker}/{beneficiary_status}', 'ReportsCurrent@printOutOfSchoolVerificationChecklists');
        Route::get('printOutOfSchMatchingForms/{batch_id}/{provinces}/{districts}/{print_filter}', 'ReportsCurrent@printOutOfSchMatchingForms');
        Route::get('printExamClassesVerificationChecklists/{batch_id}/{provinces}/{districts}/{schools}/{checker}', 'ReportsCurrent@printExamClassesVerificationChecklists');
        Route::get('printSchPlacementForms/{batch_id}/{provinces}/{districts}/{schools}/{print_filter}', 'ReportsCurrent@printSchPlacementForms');
        //Route::get('printOfferLetters/{batch_id}/{category}/{provinces}/{districts}/{schools}/{print_filter}', 'ReportsCurrent@printOfferLetters');
        Route::get('printOfferLetters', 'ReportsCurrent@printOfferLetters');
        Route::get('printProvisionalOfferLetters', 'ReportsCurrent@printProvisionalOfferLetters');
        Route::get('printCapacityAssessmentsForms/{year}/{provinces}/{districts}/{schools}', 'ReportsCurrent@printCapacityAssessmentsForms');
        //Route::get('printInSchoolSpecificChecklists/{batch_id}/{school_id}/{print_type}', 'ReportsCurrent@printInSchoolSpecificChecklists');
        Route::get('printInSchoolSpecificChecklists', 'ReportsCurrent@printInSchoolSpecificChecklists');
        //Route::get('printOutofSchoolSpecificChecklists/{batch_id}/{cwac_id}/{print_type}', 'ReportsCurrent@printOutofSchoolSpecificChecklists');
        Route::get('printOutofSchoolSpecificChecklists', 'ReportsCurrent@printOutofSchoolSpecificChecklists');
        Route::get('printSpecificOfferLetters/{batch_id}/{school_id}/{category}', 'ReportsCurrent@printSpecificOfferLetters');
        Route::get('printSingleOfferLetter/{girl_id}/{category}', 'ReportsCurrent@printSingleOfferLetter');
        Route::get('newTestRoute', 'ReportsCurrent@newTestRoute');
        Route::get('downloadOfferLetter/{girl_id}/{category}/{log_id}/{track_id}', 'ReportsCurrent@downloadOfferLetter');
        Route::get('printGradeNineSchPlacementForms/{provinces}/{districts}/{schools}', 'ReportsCurrent@printGradeNineSchPlacementForms');
        /************* Payment Module hiram************/
//         Route::get('getBeneficiaryenrollmentRpt', 'Reports_module@getBeneficiaryenrollmentRpt');
//         Route::get('getBenEnrollmentstatuses', 'Reports_module@getBenEnrollmentstatuses');
//         Route::get('printBeneficiarysummaryDetails', 'Reports_module@printBeneficiarysummaryDetails');
//         Route::get('exportBeneficiarysummaryDetails', 'Reports_module@exportBeneficiarysummaryDetails');
//         Route::get('printBeneficiaryDetailedrpt', 'Reports_module@printBeneficiaryDetailedrpt');
//         Route::get('exportBeneficiaryDetailedrpt', 'Reports_module@exportBeneficiaryDetailedrpt');
//         Route::get('exportDataBeneficiaryDetailedrpt', 'Reports_module@exportDataBeneficiaryDetailedrpt');
//         Route::get('getTermlyEnrollmentDetailsRpt', 'Reports_module@getTermlyEnrollmentDetailsRpt');
//         Route::get('printTermlysummaryDetails', 'Reports_module@printTermlysummaryDetails');
//         Route::get('exportTermlysummaryDetails', 'Reports_module@exportTermlysummaryDetails');
//         Route::get('printTermlyenrollmentDetailedrpt', 'Reports_module@printTermlyenrollmentDetailedrpt');
//         Route::get('exportTermlyenrollmentDetailedrpt', 'Reports_module@exportTermlyenrollmentDetailedrpt');
//         Route::get('exportDataTermlyDetailedrpt', 'Reports_module@exportDataTermlyDetailedrpt');
//         Route::get('getBenhome_Districts', 'Reports_module@getBenhome_Districts');
//         Route::get('getPayment_disbursementrptStr', 'Reports_module@getPayment_disbursementrptStr');
//         /************* End ***********************/
//         /*====================*/
//         //SCHOOL MANAGEMENT REPORTS
//         /*====================*/
//         Route::get('printSchoolMonitoringForm', 'SchManagement_module@printSchoolMonitoringForm');
//         Route::get('printSchoolMonitoringReport', 'SchManagement_module@printSchoolMonitoringReport');
//         Route::get('generateSchoolMonitoringForms', 'SchManagement_module@generateSchoolMonitoringForms');
//         //the spreadhseet details
//         Route::get('getBeneficiaryspreadsheetstr', 'Reports_module@getBeneficiaryspreadsheetstr');
//         Route::get('exportBeneficiaryspreadsheet', 'Reports_module@exportBeneficiaryspreadsheet');
//         Route::get('getBeneficiarypaymentsspreadsheetstr', 'Reports_module@getBeneficiarypaymentsspreadsheetstr');
//         Route::get('exportcompleteBeneficiaryspreadsheet', 'Reports_module@exportcompleteBeneficiaryspreadsheet');

//         Route::get('exportenrollmentBeneficiaryspreadsheet', 'Reports_module@exportenrollmentBeneficiaryspreadsheet');
//         Route::get('exportenrollmentcompleteBeneficiaryspreadsheet', 'Reports_module@exportenrollmentcompleteBeneficiaryspreadsheet');
//         //KIP
//         Route::get('getImportationDataReports', 'Reports_module@getImportationDataReports');
//         Route::get('getImportationDataReportsPerHomeDistrict', 'Reports_module@getImportationDataReportsPerHomeDistrict');
//         Route::get('getImportationDataReportsPerSchoolDistrict', 'Reports_module@getImportationDataReportsPerSchoolDistrict');
//         Route::get('getImportationBrief', 'Reports_module@getImportationBrief');
//         Route::get('getAssessmentDataReports', 'Reports_module@getAssessmentDataReports');
//         Route::get('getAssessmentDataReportsPerHomeDistrict', 'Reports_module@getAssessmentDataReportsPerHomeDistrict');
//         Route::get('getAssessmentDataReportsPerSchoolDistrict', 'Reports_module@getAssessmentDataReportsPerSchoolDistrict');
//         Route::get('getAssessmentBrief', 'Reports_module@getAssessmentBrief');
//         Route::get('getBeneficiariesPerBenStatus', 'Reports_module@getBeneficiariesPerBenStatus');
//         Route::get('getBenStatusesBrief', 'Reports_module@getBenStatusesBrief');
//         Route::get('getBenStatusesDataReportsPerHomeDistrict', 'Reports_module@getBenStatusesDataReportsPerHomeDistrict');
//         Route::get('getBenStatusesDataReportsPerSchoolDistrict', 'Reports_module@getBenStatusesDataReportsPerSchoolDistrict');
//         Route::get('getMappingBrief', 'Reports_module@getMappingBrief');
//         Route::get('getMappingDataReports', 'Reports_module@getMappingDataReports');
//         Route::get('getMappingDataReportsPerHomeDistrict', 'Reports_module@getMappingDataReportsPerHomeDistrict');
//         Route::get('getMappingDataReportsPerSchoolDistrict', 'Reports_module@getMappingDataReportsPerSchoolDistrict');
//         Route::get('getVerificationBrief', 'Reports_module@getVerificationBrief');
//         Route::get('getVerificationDetailed', 'Reports_module@getVerificationDetailed');
//         Route::get('getVerInSchoolDataReportsPerHomeDistrict', 'Reports_module@getVerInSchoolDataReportsPerHomeDistrict');
//         Route::get('getVerOutSchoolDataReportsPerHomeDistrict', 'Reports_module@getVerOutSchoolDataReportsPerHomeDistrict');
//         Route::get('getVerExamClassesDataReportsPerHomeDistrict', 'Reports_module@getVerExamClassesDataReportsPerHomeDistrict');
//         Route::get('getVerInSchoolPerHomeDistrict', 'Reports_module@getVerInSchoolPerHomeDistrict');
//         Route::get('getVerOutSchoolPerHomeDistrict', 'Reports_module@getVerOutSchoolPerHomeDistrict');
//         Route::get('getVerExamClassesPerHomeDistrict', 'Reports_module@getVerExamClassesPerHomeDistrict');
//         Route::get('getVerExamClassesPerSchoolDistrict', 'Reports_module@getVerExamClassesPerSchoolDistrict');
//         Route::get('getVerInSchoolPerSchoolDistrict', 'Reports_module@getVerInSchoolPerSchoolDistrict');
//         Route::get('getSchoolMatchingBrief', 'Reports_module@getSchoolMatchingBrief');
//         Route::get('getSchoolMatchingChartData', 'Reports_module@getSchoolMatchingChartData');
//         Route::get('getSchoolMatchingDistrictDistribution', 'Reports_module@getSchoolMatchingDistrictDistribution');
//         Route::get('getSchoolMatchingSchoolDistribution', 'Reports_module@getSchoolMatchingSchoolDistribution');
//         Route::get('getSchoolPlacementBrief', 'Reports_module@getSchoolPlacementBrief');
//         Route::get('getSchoolPlacementChartData', 'Reports_module@getSchoolPlacementChartData');
//         Route::get('getSchoolPlacementDistrictDistribution', 'Reports_module@getSchoolPlacementDistrictDistribution');
//         Route::get('getSchoolPlacementSchoolDistribution', 'Reports_module@getSchoolPlacementSchoolDistribution');
//         Route::get('getSchPlacementDistributionBrief', 'Reports_module@getSchPlacementDistributionBrief');
//         Route::get('getSchoolMatchingDistributionBrief', 'Reports_module@getSchoolMatchingDistributionBrief');
//         Route::get('getBeneficiariesResponseRate', 'Reports_module@getBeneficiariesResponseRate');
//         Route::get('getPerformanceAttendanceReport', 'Reports_module@getPerformanceAttendanceReport');
//         Route::get('getEnrollmentResponse', 'Reports_module@getEnrollmentResponse');
//         Route::get('getBenEnrollmentStatusesBrief', 'Reports_module@getBenEnrollmentStatusesBrief');
//         Route::get('getEnrollmentStatusesDataReportsPerHomeDistrict', 'Reports_module@getEnrollmentStatusesDataReportsPerHomeDistrict');
//         Route::get('getBenEnrollmentStatusesPerSchoolDistrict', 'Reports_module@getBenEnrollmentStatusesPerSchoolDistrict');
//         Route::get('getEnrollmentsPerHomeDistricts', 'Reports_module@getEnrollmentsPerHomeDistricts');
//         Route::get('getEnrollmentsPerHomeDistrictsCWAC', 'Reports_module@getEnrollmentsPerHomeDistrictsCWAC');
//         Route::get('getEnrollmentsPerSchoolDistricts', 'Reports_module@getEnrollmentsPerSchoolDistricts');
//         Route::get('getTermlySchoolsDisbursementsRpt', 'Reports_module@getTermlySchoolsDisbursementsRpt');
//         Route::get('getBeneficiariesMoreResponseRate', 'Reports_module@getBeneficiariesMoreResponseRate');
//         Route::get('getBeneficiariesMoreSchStatusesResponseRate', 'Reports_module@getBeneficiariesMoreSchStatusesResponseRate');
//         Route::get('getUnresponsiveBeneficiariesMainReport', 'Reports_module@getUnresponsiveBeneficiariesMainReport');
//         Route::get('getUnresponsiveBeneficiaries', 'Reports_module@getUnresponsiveBeneficiaries');
//         Route::get('printVerificationData', 'Reports_module@printVerificationData');
//         Route::get('getSchoolFacilityTypes', 'Reports_module@getSchoolFacilityTypes');
//         Route::get('getInitialAnnualEnrollments', 'Reports_module@getInitialAnnualEnrollments');
//         Route::get('getBeneficiaryAnnualEnrollmentsRptTabular', 'Reports_module@getBeneficiaryAnnualEnrollmentsRptTabular');
//         Route::get('getAnnualTakeUpStatusReport', 'Reports_module@getAnnualTakeUpStatusReport');
//         Route::get('getEnrollmentProgressionCriteria', 'Reports_module@getEnrollmentProgressionCriteria');
//         Route::get('getBeneficiaryAnnualEnrollmentCompletion', 'Reports_module@getBeneficiaryAnnualEnrollmentCompletion');
//         Route::get('getBeneficiaryEnrolments', 'Reports_module@getBeneficiaryEnrolments');
//         Route::get('getExamFeesReport', 'Reports_module@getExamFeesReport');
//         Route::get('getExamFeesBeneficiariesReport', 'Reports_module@getExamFeesBeneficiariesReport');
//         Route::post('exportReportsModuleRecords', 'Reports_module@exportReportsModuleRecords');
//         //front office
//         Route::get('getAllBeneficiaryStatuses', 'Reports_module@getAllBeneficiaryStatuses');
//         //Test
//         Route::get('testJasper', 'ReportsCurrent@testJasper');
//     });
// });
