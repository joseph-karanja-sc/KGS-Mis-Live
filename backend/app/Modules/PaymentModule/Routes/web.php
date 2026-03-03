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

Route::prefix('paymentmodule')->group(function () {
    Route::get('/', 'PaymentModuleController@index');
});

Route::group(['prefix' => 'payment_module'], function () {

    Route::group(['middleware' => ['web']], function () {
        Route::post('addParam', 'Parameters@saveParamCommonData');
        Route::get('getParamCase_types', 'Parameters@getParamCase_types');
        Route::get('getVerificationdistrictData', 'PaymentModuleController@getVerificationdistrictData');
        Route::get('getDistrict_SchoolsData', 'PaymentModuleController@getDistrict_SchoolsData');
        Route::get('getBenschool_Provinces', 'PaymentModuleController@getBenschool_Provinces');
        Route::get('getBenschool_Districts', 'PaymentModuleController@getBenschool_Districts');
        Route::get('getBeneficiaryschools', 'PaymentModuleController@getBeneficiaryschools');
        Route::get('getPayment_verificationDatentry', 'PaymentModuleController@getPayment_verificationDatentry');
        Route::get('getPaymentValidationDetails', 'PaymentModuleController@getPaymentValidationDetails');
        Route::get('getBeneficiariesPaymentinfo', 'PaymentModuleController@getBeneficiariesPaymentinfo');
        Route::get('getBeneficiaryEnrollmentbatchinfo', 'PaymentModuleController@getBeneficiaryEnrollmentbatchinfo');
        Route::get('getBeneficiaryEnrollmentBatchInfoArchive', 'PaymentModuleController@getBeneficiaryEnrollmentBatchInfoArchive');
        Route::get('getBeneficiaryValidationEnrollmentInfo', 'PaymentModuleController@getBeneficiaryValidationEnrollmentInfo');
        Route::get('getbeneficiaryEnrollmentsummarydta', 'PaymentModuleController@getbeneficiaryEnrollmentsummarydta');
        Route::get('getBeneficiariesDatalogs', 'PaymentModuleController@getBeneficiariesDatalogs');
        Route::get('getpaymentrequestConsolidations', 'PaymentModuleController@getpaymentrequestConsolidations');
        Route::get('getValidatedBeneficiariesPaymentinfo', 'PaymentModuleController@getValidatedBeneficiariesPaymentinfo');
        Route::get('getBeneficiary_requestpaymentInfo', 'PaymentModuleController@getBeneficiary_requestpaymentInfo');
        Route::get('getValidateBeneficiaryschsummary', 'PaymentModuleController@getValidateBeneficiaryschsummary');
        Route::get('getValidatedBenschoolsPaymentinfo', 'PaymentModuleController@getValidatedBenschoolsPaymentinfo');
        Route::get('getSchoolpaymentschoolSummary', 'PaymentModuleController@getSchoolpaymentschoolSummary');
        Route::get('getUploadPaymentdisbursementdetails', 'PaymentModuleController@getUploadPaymentdisbursementdetails');
        Route::post('SaveFeesForPrivateSchools', 'PaymentModuleController@SaveFeesForPrivateSchools');
        //reports calls
        Route::get('printGeneratpayVerificationchk', 'Reports@printGeneratpayVerificationchk');
        Route::get('printPaymentVerificationChecklist', 'Reports@printPaymentVerificationChecklist');
        Route::get('printPaymentrequestschedule', 'Reports@printPaymentrequestschedule');
        Route::get('paymentScheduleForNonDisbursed', 'Reports@paymentScheduleForNonDisbursed');
        Route::get('paymentScheduleForDisbursed', 'Reports@paymentScheduleForDisbursed');
        Route::get('getschool_feessetupDetails', 'Parameters@getschool_feessetupDetails');
        Route::get('getschool_feessetupDetails3', 'Parameters@getschool_feessetupDetails3');
        Route::get('printGeneratBatchVerificationchk', 'Reports@printGeneratBatchVerificationchk');
        Route::get('postPaymentsToPayFlexi', 'Reports@postPaymentsToPayFlexi');
        //disbursement report per school
        Route::get('printPaymentdisbusementReport', 'Reports@printPaymentdisbusementReport');
        //payment pst data xsd
        Route::post('savePaymentVerificationDetails', 'PaymentModuleController@savePaymentVerificationDetails');
        Route::post('addBeneficiaryenrollement', 'PaymentModuleController@addBeneficiaryenrollement');
        Route::post('saveBeneficiaryEnrollmentbatchinfo', 'PaymentModuleController@saveBeneficiaryEnrollmentbatchinfo');
        Route::post('removeBeneficiaryenrollement', 'PaymentModuleController@removeBeneficiaryenrollement');

        Route::get('getPayment_verificationenquiry', 'PaymentModuleController@getPayment_verificationenquiry');
        Route::get('getPaymentreceiptingDetails', 'PaymentModuleController@getPaymentreceiptingDetails');

        Route::post('checkPaymentVerificationBatchdetails', 'PaymentModuleController@checkPaymentVerificationBatchdetails');
        Route::post('submitpaymentBatchChecklist', 'PaymentModuleController@submitpaymentBatchChecklist');
        Route::post('savePaymentrequestdetails', 'PaymentModuleController@savePaymentrequestdetails');
        Route::post('returnforPaymentverificationquery', 'PaymentModuleController@returnforPaymentverificationquery');
        Route::post('savevalidateEnrollementrecord', 'PaymentModuleController@savevalidateEnrollementrecord');
        Route::post('savevalidateEnrollementBatchrecord', 'PaymentModuleController@savevalidateEnrollementBatchrecord');
        Route::post('addValidateBenpaymentrequest', 'PaymentModuleController@addValidateBenpaymentrequest');
        Route::post('func_deleteBenpaymentrecord', 'PaymentModuleController@func_deleteBenpaymentrecord');
        Route::post('func_deleteSchoolpaymentrecord', 'PaymentModuleController@func_deleteSchoolpaymentrecord');
        Route::post('func_submitforpaymentApproval', 'PaymentModuleController@func_submitforpaymentApproval');
        Route::post('func_submitforpaymentDisbursement', 'PaymentModuleController@func_submitforpaymentDisbursement');
        Route::post('savePaymentdisbursementdetails', 'PaymentModuleController@savePaymentdisbursementdetails');
        Route::post('deletePaymentDisbursementdetails', 'PaymentModuleController@deletePaymentDisbursementdetails');
        Route::post('addValidateBenschoolpaymentrequest', 'PaymentModuleController@addValidateBenschoolpaymentrequest');
        Route::post('getbankContactSchoolinfo', 'PaymentModuleController@getbankContactSchoolinfo');
        Route::post('saveschool_feessetupDetails', 'Parameters@saveschool_feessetupDetails');
        Route::post('submitbatchPayment4Validation', 'PaymentModuleController@submitbatchPayment4Validation');
        Route::post('submittoPaymentReconciliation', 'PaymentModuleController@submittoPaymentReconciliation');
        Route::post('submitReceipttoPaymentReconciliation', 'PaymentModuleController@submitReceipttoPaymentReconciliation');
        Route::post('submitsinglePaymentreconcolliation', 'PaymentModuleController@submitsinglePaymentreconcolliation');
        Route::post('savePaymentrequestreceipting', 'PaymentModuleController@savePaymentrequestreceipting');
        Route::post('addBeneficiaryreceiptingdetails', 'PaymentModuleController@addBeneficiaryreceiptingdetails');
        Route::get('getSchoolben_paymentDisbursementsStr', 'PaymentModuleController@getSchoolben_paymentDisbursementsStr');
        Route::get('getBeneficiary_receiptingInfoStr', 'PaymentModuleController@getBeneficiary_receiptingInfoStr');
        Route::post('saveBeneficiary_receiptingInfoStr', 'PaymentModuleController@saveBeneficiary_receiptingInfoStr');
        Route::post('saveReceiptdetails', 'PaymentModuleController@saveReceiptdetails');
        Route::post('deleteReceiptDetails', 'PaymentModuleController@deleteReceiptDetails');
        Route::post('func_syncuploadedPayments', 'PaymentModuleController@func_syncuploadedPayments');

        Route::post('func_paymentuploaddisbursement', 'PaymentModuleController@func_paymentuploaddisbursement');
        Route::get('getbeneficiaryReceiptsStr', 'PaymentModuleController@getbeneficiaryReceiptsStr');
        Route::get('getSpecificPaymentChecklistsGen', 'PaymentModuleController@getSpecificPaymentChecklistsGen');
        //KIP
        Route::get('getSchoolsForPaymentsLog', 'PaymentModuleController@getSchoolsForPaymentsLog');
        Route::get('printSpecificPaymentChecklists', 'Reports@printSpecificPaymentChecklists');
        Route::get('getChecklistGenerationsLog', 'PaymentModuleController@getChecklistGenerationsLog');
        Route::get('getBeneficiariesOnChecklist', 'PaymentModuleController@getBeneficiariesOnChecklist');
        Route::get('getPaymentsDataEntryLog', 'PaymentModuleController@getPaymentsDataEntryLog');
        Route::get('getValidatedBeneficiaries', 'PaymentModuleController@getValidatedBeneficiaries');
        Route::get('getUnvalidatedBeneficiaries', 'PaymentModuleController@getUnvalidatedBeneficiaries');
        Route::get('getPaymentsConsolidationLog', 'PaymentModuleController@getPaymentsConsolidationLog');
        Route::get('getBeneficiariesOnPaymentRequest', 'PaymentModuleController@getBeneficiariesOnPaymentRequest');
        Route::get('getPaymentsDisbursementLog', 'PaymentModuleController@getPaymentsDisbursementLog');
        Route::get('getBeneficiariesOnPaymentDisbursement', 'PaymentModuleController@getBeneficiariesOnPaymentDisbursement');
        Route::get('getActivebeneficiaries_details', 'PaymentModuleController@getActivebeneficiaries_details');
        Route::post('removeSelectedBeneficiaries', 'PaymentModuleController@removeSelectedBeneficiaries');
        Route::post('updateEnrollmentSchoolFee', 'PaymentModuleController@updateEnrollmentSchoolFee');
        Route::get('getEnrollmentErrorLog', 'PaymentModuleController@getEnrollmentErrorLog');
        Route::get('getPaymentVerificationTransitionalStages', 'PaymentModuleController@getPaymentVerificationTransitionalStages');
        Route::post('deletePaymentVerificationBatch', 'PaymentModuleController@deletePaymentVerificationBatch');
        Route::get('getBeneficiariesSchools', 'PaymentModuleController@getBeneficiariesSchools');
        Route::post('saveBeneficiaryEnrollmentTransfer', 'PaymentModuleController@saveBeneficiaryEnrollmentTransfer');
        Route::get('getFeeSetUpErrorLog', 'PaymentModuleController@getFeeSetUpErrorLog');
        Route::post('validateEnrollmentBatchRecord', 'PaymentModuleController@validateEnrollmentBatchRecord');
        Route::post('validateEnrollmentSelectedRecords', 'PaymentModuleController@validateEnrollmentSelectedRecords');
        Route::post('deleteSchoolPaymentRecord', 'PaymentModuleController@deleteSchoolPaymentRecord');
        Route::post('deleteSchoolPaymentRecordBatch', 'PaymentModuleController@deleteSchoolPaymentRecordBatch');
        Route::post('savePaymentRequestApprovalDetails', 'PaymentModuleController@savePaymentRequestApprovalDetails');
        Route::get('getSchoolActiveBankInfo', 'PaymentModuleController@getSchoolActiveBankInfo');
        Route::post('updateSchoolBankInfo', 'PaymentModuleController@updateSchoolBankInfo');
        Route::get('getPayChecklistsGenerationHistory', 'PaymentModuleController@getPayChecklistsGenerationHistory');
        Route::get('downloadPrintedPaymentChecklists', 'Reports@downloadPrintedPaymentChecklists');
        Route::post('submitValidatedBeneficiaryDetails', 'PaymentModuleController@submitValidatedBeneficiaryDetails');
        Route::get('getValidationSubmittedRecords', 'PaymentModuleController@getValidationSubmittedRecords');
        Route::get('getPaymentUnvalidatedBeneficiaries', 'PaymentModuleController@getPaymentUnvalidatedBeneficiaries');
        Route::get('printFollowupPaymentChecklists', 'Reports@printFollowupPaymentChecklists');
        Route::get('printPromotionPaymentChecklists', 'Reports@printPromotionPaymentChecklists');
        Route::get('printRevokedPromotionPaymentChecklists', 'Reports@printRevokedPromotionPaymentChecklists');
        Route::get('getPaymentFollowupBeneficiaries', 'PaymentModuleController@getPaymentFollowupBeneficiaries');
        Route::get('getPaymentDisbursementReport', 'Reports@getPaymentDisbursementReport');
        Route::get('getSchoolsDisbursementReport', 'Reports@getSchoolsDisbursementReport');
        Route::get('getDisbursementReportForSchools', 'Reports@getDisbursementReportForSchools');
        Route::get('getDisbursementReportForDistrict', 'Reports@getDisbursementReportForDistrict');
        Route::get('getPaymentDisbursementDashboardData', 'PaymentModuleController@getPaymentDisbursementDashboardData');
        Route::post('paymentBatchSubmissions', 'PaymentModuleController@paymentBatchSubmissions');
        Route::post('saveBeneficiaryReceiptDetails', 'PaymentModuleController@saveBeneficiaryReceiptDetails');
        Route::post('deletePaymentReceiptBatch', 'PaymentModuleController@deletePaymentReceiptBatch');
        Route::post('addSelectedBeneficiariesReceiptDetails', 'PaymentModuleController@addSelectedBeneficiariesReceiptDetails');
        Route::post('removeSelectedBeneficiariesReceiptDetails', 'PaymentModuleController@removeSelectedBeneficiariesReceiptDetails');
        Route::get('getBeneficiariesForReceiptAdditions', 'PaymentModuleController@getBeneficiariesForReceiptAdditions');
        Route::post('deleteRecord', 'PaymentModuleController@deletePaymentRecord');
        Route::post('receiptBatchSubmissions', 'PaymentModuleController@receiptBatchSubmissions');
        Route::get('getPaymentReceiptingValidationDetails', 'PaymentModuleController@getPaymentReceiptingValidationDetails');
        Route::post('removeSelectedSubmittedBeneficiaries', 'PaymentModuleController@removeSelectedSubmittedBeneficiaries');
        Route::post('archiveActiveReconciliationBatch', 'PaymentModuleController@archiveActiveReconciliationBatch');
        Route::get('getPaymentRequestsBatches', 'PaymentModuleController@getPaymentRequestsBatches');
        Route::post('initializeReconciliationSuspenseAccount', 'PaymentModuleController@initializeReconciliationSuspenseAccount');
        Route::post('initializePaymentRequestPrintOut', 'PaymentModuleController@initializePaymentRequestPrintOut');
        Route::get('getEnrollmentDuplicateRecords', 'PaymentModuleController@getEnrollmentDuplicateRecords');
        Route::post('removeValidationDuplicatedEnrollments', 'PaymentModuleController@removeValidationDuplicatedEnrollments');
        Route::get('getEnrollmentDuplicateCount', 'PaymentModuleController@getEnrollmentDuplicateCount');
        Route::get('getRevokedPromotionBeneficiariesPaymentChecklistStats', 'PaymentModuleController@getRevokedPromotionBeneficiariesPaymentChecklistStats');
        Route::get('getPromotionBeneficiariesPaymentChecklistStatsGeneric', 'PaymentModuleController@getPromotionBeneficiariesPaymentChecklistStatsGeneric');
        Route::post('doDeleteBenEnrollmentAnomaly', 'PaymentModuleController@doDeleteBenEnrollmentAnomaly');
        Route::post('uploadSignedConsentForms', 'PaymentModuleController@uploadSignedConsentForms');
        Route::get('getSignedConsentUploadForms', 'PaymentModuleController@getSignedConsentUploadForms');
        Route::post('deletePaymentModuleRecord', 'PaymentModuleController@deletePaymentModuleRecord');
        Route::get('getPaymentEnrolmentBatchInfo', 'PaymentModuleController@getPaymentEnrolmentBatchInfo');
        //reconciliation modules
        Route::get('getPaymentreconcilliationStr', 'PaymentModuleController@getPaymentreconcilliationStr');
        Route::get('getSchpaymentreconcilliationStr', 'PaymentModuleController@getSchpaymentreconcilliationStr');
        Route::get('getSchpaymentNonreconcilliationStr', 'PaymentModuleController@getSchpaymentNonreconcilliationStr');
        Route::get('getSchpaymentdisbursements', 'PaymentModuleController@getSchpaymentdisbursements');
        Route::get('getPaymentVerificationDetails', 'PaymentModuleController@getPaymentVerificationDetails');
        Route::get('getPaymentDisbursementDetails', 'PaymentModuleController@getPaymentDisbursementDetails');
        Route::get('getPaymentReceiptDetails', 'PaymentModuleController@getPaymentReceiptDetails');
        Route::post('saveReconciliationOversightDetails', 'PaymentModuleController@saveReconciliationOversightDetails');
        Route::get('getReconciliationOversightDetails', 'PaymentModuleController@getReconciliationOversightDetails');
        Route::get('getBeneficiariesForReconciliation', 'PaymentModuleController@getBeneficiariesForReconciliation');
        Route::post('addSelectedBeneficiariesReconciliationDetails', 'PaymentModuleController@addSelectedBeneficiariesReconciliationDetails');
        Route::get('getOversightBatchBeneficiaries', 'PaymentModuleController@getOversightBatchBeneficiaries');
        Route::post('removeSelectedBeneficiariesReconciliationDetails', 'PaymentModuleController@removeSelectedBeneficiariesReconciliationDetails');
        Route::post('updateReconciliationConfirmedFees', 'PaymentModuleController@updateReconciliationConfirmedFees');
        Route::post('confirmAndCloseReconciliationBatch', 'PaymentModuleController@confirmAndCloseReconciliationBatch');
        Route::post('deleteReconciliationOversightBatch', 'PaymentModuleController@deleteReconciliationOversightBatch');
        Route::get('getReconciliationSummaries', 'PaymentModuleController@getReconciliationSummaries');
        Route::post('saveReconciliationRectificationDetails', 'PaymentModuleController@saveReconciliationRectificationDetails');
        Route::get('getPaymentDuplicateRecords', 'PaymentModuleController@getPaymentDuplicateRecords');
        Route::post('removeSelectedDuplicatedEnrollments', 'PaymentModuleController@removeSelectedDuplicatedEnrollments');
        Route::post('removeIndividualDuplicatedEnrollment', 'PaymentModuleController@removeIndividualDuplicatedEnrollment');
        Route::get('searchBeneficiaries4Reconciliation', 'PaymentModuleController@searchBeneficiaries4Reconciliation');
        Route::post('addExternalBeneficiaryForReconciliation', 'PaymentModuleController@addExternalBeneficiaryForReconciliation');
        Route::get('getPromotionBeneficiariesPaymentChecklistStats', 'PaymentModuleController@getPromotionBeneficiariesPaymentChecklistStats');
        Route::get('getUnprintedBeneficiariesPaymentChecklistStats', 'PaymentModuleController@getUnprintedBeneficiariesPaymentChecklistStats');
        //reconciliation reports
        Route::get('printReconcilliationRpt', 'Reports@printReconcilliationRpt');
        Route::get('exportComprehesiveReconcilliationRpt', 'Reports@exportComprehesiveReconcilliationRpt');
        Route::get('getBeneficiariespayValidationrules', 'PaymentModuleController@getBeneficiariespayValidationrules');
        Route::get('createFolders', 'Reports@createFolders');
        Route::post('savePaymentbenTransferinfo', 'PaymentModuleController@savePaymentbenTransferinfo');
        Route::post('removeUploadedpaymentsdisbursement', 'PaymentModuleController@removeUploadedpaymentsdisbursement');
        Route::get('getReconciliationArchiveDetails', 'PaymentModuleController@getReconciliationArchiveDetails');
        Route::get('getPaymentdashboarddata', 'PaymentModuleController@getPaymentdashboarddata');
        Route::get('getBenpaymentdisbDashboardChart', 'PaymentModuleController@getBenpaymentdisbDashboardChart');
        Route::get('getenrolledbeneficiariesbDashboarddetails', 'PaymentModuleController@getenrolledbeneficiariesbDashboarddetails');
        Route::get('getPaymentdisbDashboardChartdetails', 'PaymentModuleController@getPaymentdisbDashboardChartdetails');
        Route::get('backupgirlstoknockout','PaymentModuleController@BackupGirlsToKnockOut');
        Route::get('downloadKnockedOutGirls', 'PaymentModuleController@downloadKnockedOutGirls');
        Route::get('KnockOutGirls','PaymentModuleController@KnockOutGirls');

        Route::get('printPaymentverificationproces', 'Reports@printPaymentverificationproces');
        Route::get('getActivebengroupRptStr', 'Reports@getActivebengroupRptStr');
        Route::get('getBeneficiaryGroupingstr', 'Reports@getBeneficiaryGroupingstr');
        Route::get('getBeneficairypaymentgroupRpt', 'Reports@getBeneficairypaymentgroupRpt');
        Route::get('getPaymentsubGroupingstr', 'Reports@getPaymentsubGroupingstr');
        Route::get('getviewPaymentsubmissionStr', 'Reports@getviewPaymentsubmissionStr');
        Route::get('printUnprintedPaymentChecklists', 'Reports@printUnprintedPaymentChecklists');

        Route::get('generatePaymentrequestUploadtemplate', 'Reports@generatePaymentrequestUploadtemplate');

        Route::post('func_syncuploadedPayments', 'PaymentModuleController@func_syncuploadedPayments');
        Route::post('func_paymentuploaddisbursement', 'PaymentModuleController@func_paymentuploaddisbursement');
        Route::post('removeUploadedpaymentsdisbursement', 'PaymentModuleController@removeUploadedpaymentsdisbursement');
        Route::get('generatePaymentrequestUploadtemplate', 'Reports@generatePaymentrequestUploadtemplate');

        Route::get('paymentVerificationProgressReport', 'Reports@paymentVerificationProgressReport');
        Route::get('detailedReport', 'Reports@detailedReport');

        Route::get('exportPaymentRecords', 'PaymentModuleController@exportPaymentRecords');
        Route::get('returnPaymentGrantDownloadUrl','PaymentModuleController@returnPaymentGrantDownloadUrl');
        Route::get('paymentGrantlist','PaymentModuleController@paymentGrantlist');
        Route::get('getPaymentsDataEntryDuplicates','PaymentModuleController@getPaymentsDataEntryDuplicates');
        Route::post('removeSelectedDuplicatedEnrollmentsForDataEntry','PaymentModuleController@removeSelectedDuplicatedEnrollmentsForDataEntry');
        Route::post('uploadPaymentGrantList','PaymentModuleController@uploadPaymentGrantList');
        //job on 4/25/2022
        Route::get('getFeesKnockoutDetails','PaymentModuleController@getFeesKnockoutDetails');
        Route::post('reverseFeeKnockOut','PaymentModuleController@reverseFeeKnockOut');
        Route::get('GetFeesknockOutReport','PaymentModuleController@GetFeesknockOutReport');
        Route::get('getfeesknockOutStatus','PaymentModuleController@getfeesknockOutStatus');
        Route::get('getFeesKnockoutBeneficiaryDetails','PaymentModuleController@getFeesKnockoutBeneficiaryDetails');
        Route::get('getCurrentFeesKnockoutBeneficiaryDetails','PaymentModuleController@getCurrentFeesKnockoutBeneficiaryDetails');
        Route::get('getpaymentvarinacesPaymentRequests','PaymentModuleController@getpaymentvarinacesPaymentRequests');
        Route::get('getBeneficiaryPaymentVariances','PaymentModuleController@getBeneficiaryPaymentVariances');
        Route::get('generatePaymentVariancesReport','PaymentModuleController@generatePaymentVariancesReport');
                Route::get('getNewEntrants','PaymentModuleController@generateNewEntrants');
                     Route::get('getpaymentStats','PaymentModuleController@getBenePaidForWithFilters');
                       Route::get('getSchoolPaymentVariances','PaymentModuleController@getSchoolPaymentVariances');
        Route::get('getPaymentGrantLists','PaymentModuleController@getPaymentGrantLists');
        Route::post('savePaymentGrantListLimit','PaymentModuleController@savePaymentGrantListLimit');
        Route::get('getPaymentGrantListLimit','PaymentModuleController@getPaymentGrantListLimit');
                Route::post('processFeesDeduction','PaymentModuleController@processFeesDeduction');
                 Route::post('deletePaymentRequest', 'PaymentModuleController@deletePaymentRequest');
                 //API jobs
    Route::post('runPaymentBatches', 'PaymentModuleController@runPaymentBatches');    
    });
    // Route::get('getOfflineAbsentGirlsbatchinfo', 'ParametersController@getOfflineAbsentGirlsbatchinfo');
    Route::get('getOfflineAbsentGirlsbatchinfo', 'PaymentModuleController@getOfflineAbsentGirlsbatchinfo');
    Route::get('getPpmAppSchoolpaymentschoolSummary', 'PaymentModuleController@getPpmAppSchoolpaymentschoolSummary');
        Route::get('getPpmAppBeneficiary_requestpaymentInfo', 'PaymentModuleController@getPpmAppBeneficiary_requestpaymentInfo');
        Route::get('getPpmApppaymentConsolidations', 'PaymentModuleController@getPpmApppaymentConsolidations');  
        Route::get('getImagesData', 'PaymentModuleController@getImagesData');        
    Route::get('getPGresponseData', 'Reports@getPGresponseData'); 
    Route::get('getOfflineSchoolFeesSetup', 'Parameters@getOfflineSchoolFeesSetup');
    Route::get('getOfflineBeneficiaryEnrollmentbatchinfo', 'Parameters@getOfflineBeneficiaryEnrollmentbatchinfo');
    Route::post('saveOfflinePaymentVerificationDetails', 'PaymentModuleController@saveOfflinePaymentVerificationDetails');
    Route::post('saveOfflineSchoolFeesSetup', 'Parameters@saveOfflineSchoolFeesSetup');
    Route::post('saveOfflineBeneficiaryEnrollmentbatchinfo', 'PaymentModuleController@saveOfflineBeneficiaryEnrollmentbatchinfo');
    
});
