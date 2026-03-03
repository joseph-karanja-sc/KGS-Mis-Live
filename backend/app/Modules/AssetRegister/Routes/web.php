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

Route::prefix('assetregister')->group(function () {
    Route::get('/', 'AssetRegisterController@index');
    Route::post('saveAssetRegisterCommonData', 'AssetRegisterController@saveAssetRegisterCommonData');
    Route::get('getAssetRegisterParamFromTable', 'AssetRegisterController@getAssetRegisterParamFromTable');
    Route::delete('deleteAssetRegisterRecord', 'AssetRegisterController@deleteAssetRegisterRecord');
    Route::post('saveAssetInventoryDetails', 'AssetRegisterController@saveAssetInventoryDetails');
    Route::get('getAssetInventoryDetails', 'AssetRegisterController@getAssetInventoryDetails');
    Route::post('saveAssetRegisterParamData', 'AssetRegisterController@saveAssetRegisterParamData');
    Route::get('getAssetDepreciation', 'AssetRegisterController@getAssetDepreciation');
    Route::get('getInsurancePoliciesForLinkage', 'AssetRegisterController@getInsurancePoliciesForLinkage');
    Route::post('saveAssetInsuranceLinkage', 'AssetRegisterController@saveAssetInsuranceLinkage');
    Route::get('getAssetInsuranceLinkageDetails', 'AssetRegisterController@getAssetInsuranceLinkageDetails');
    Route::get('getFundingForLinkage', 'AssetRegisterController@getFundingForLinkage');
    Route::get('getAssetFundingLinkageDetails', 'AssetRegisterController@getAssetFundingLinkageDetails');
    Route::get('calculateAssetDepreciation', 'AssetRegisterController@calculateAssetDepreciation');
    Route::post('saveAssetCheckOutDetails', 'AssetRegisterController@saveAssetCheckOutDetails');
    Route::get('getAssetCheckOutDetails', 'AssetRegisterController@getAssetCheckOutDetails');
    Route::post('saveAssetCheckOutInDetails', 'AssetRegisterController@saveAssetCheckOutInDetails');
    Route::post('deletebrand','AssetRegisterController@deletebrand');
    Route::post('updateTagFields','AssetRegisterController@updateTagFields');

    
    Route::get('getAssetInventoryDetailsForReservation', 'AssetRegisterController@getAssetInventoryDetailsForReservations');

    Route::post('saveAssetReservationDetails','AssetRegisterController@saveAssetReservationDetails');
    Route::get('getReservedAssets', 'AssetRegisterController@getReservedAssets');
    Route::get('getUser', 'AssetRegisterController@getUserDetails');
    Route::get('getExpiredAssetWarranties', 'AssetRegisterController@getExpiredAssetWarranties');
    Route::post('saveAssetLossDamageDetails','AssetRegisterController@saveAssetLossDamageDetails');
    Route::get('getAssetForLossDamageDetails', 'AssetRegisterController@getAssetForLossDamageDetails');
    Route::post('saveAssetWriteOffDetails','AssetRegisterController@saveAssetWriteOffDetails');
    Route::post('saveAssetSellDetails','AssetRegisterController@saveAssetSellDetails');
    Route::post('saveAssetRepairScheduleDetails','AssetRegisterController@saveAssetRepairScheduleDetails');
   
    Route::post('saveAssetDisposalDetails','AssetRegisterController@saveAssetDisposalDetails');
    Route::get('getActiveScheduledRepairs', 'AssetRegisterController@getActiveScheduledRepairs');
    Route::get('getDueScheduledRepairs', 'AssetRegisterController@getDueScheduledRepairs');
    Route::post('saveAssetRepairReportDetails', 'AssetRegisterController@saveAssetRepairReportDetails');
    Route::post('saveAssetMaintainanceScheduleDetails', 'AssetRegisterController@saveAssetMaintainanceScheduleDetails');
    Route::post('saveAssetTransferDetails', 'AssetRegisterController@saveAssetTransferDetails');
    Route::post('saveAssetRequisitionRequestDetails','AssetRegisterController@saveAssetRequisitionRequestDetails');

    Route::get('benmasterinfo','AssetRegisterController@benmasterinfo');
    Route::post('DeleteAsset','AssetRegisterController@DeleteAsset');
    Route::post('saveIndividualBulkCheckout','AssetRegisterController@saveIndividualBulkCheckout');
    Route::post('saveMultipleIndividualsBulkCheckoutDetails','AssetRegisterController@saveMultipleIndividualsBulkCheckoutDetails');
    Route::post('saveIndividualBulkCheckInDetails','AssetRegisterController@saveIndividualBulkCheckInDetails');
    Route::post('SaveSingleSiteAssetCheckOutDetails','AssetRegisterController@SaveSingleSiteAssetCheckOutDetails');
    Route::post('SaveSingleSiteAssetCheckInDetails','AssetRegisterController@SaveSingleSiteAssetCheckInDetails');
    Route::post('saveSiteBulkCheckoutDetails','AssetRegisterController@saveSiteBulkCheckoutDetails');
    Route::post('saveSiteBulkCheckInDetails','AssetRegisterController@saveSiteBulkCheckInDetails');
    Route::post('saveMultipleusersBulkCheckInDetails','AssetRegisterController@saveMultipleusersBulkCheckInDetails');
    Route::get('getAssetReport','AssetRegisterController@getAssetReport');
    Route::get('generateAssetsReport','AssetRegisterController@generateAssetsReport');
    Route::post('saveAssetAdditionalData','AssetRegisterController@saveAssetAdditionalData');
    Route::post('restoreAsset','AssetRegisterController@RestoreAsset');
    Route::post('RequisitionRevert','AssetRegisterController@RequisitionRevert');
    Route::post('uploadAssets','AssetRegisterController@uploadAssets');
    Route::post('saveAssetApprovalDetails','AssetRegisterController@saveAssetApprovalDetails');

    Route::get('getPreUploadedAssets','AssetRegisterController@getPreUploadedAssets');
    Route::get('getSystemUsers','AssetRegisterController@getSystemUsers');
    Route::get('getSites','AssetRegisterController@getSites');
    Route::post('saveAssetMappingDetails','AssetRegisterController@saveAssetMappingDetails');
    Route::post('UploadMappedAssets','AssetRegisterController@UploadMappedAssets');
    Route::get('getNextAssetBatchNo','AssetRegisterController@getNextAssetBatchNo');
    Route::get('getAssetUploadBatches','AssetRegisterController@getAssetUploadBatches');
    Route::post('saveAssetMappingDetailsForDisposal','AssetRegisterController@saveAssetMappingDetailsForDisposal');

    Route::post('saveAssetInsuranceClaimDetails','AssetRegisterController@saveAssetInsuranceClaimDetails');
    Route::get('getAssetTransferReport','AssetRegisterController@getAssetTransferReport');
    Route::get('generateAssetTransferReport','AssetRegisterController@generateAssetTransferReport');

    //stores
    Route::post('saveStoresAssetInventoryDetails','AssetRegisterController@saveStoresAssetInventoryDetails');
    Route::get('getStoresAssetInventoryDetails', 'AssetRegisterController@getStoresAssetInventoryDetails');
    Route::get('getStoresAssetDepreciation', 'AssetRegisterController@getStoresAssetDepreciation');
    Route::get('calculateStoresAssetDepreciation', 'AssetRegisterController@calculateStoresAssetDepreciation');
    Route::post('saveStoresAssetRegisterParamData', 'AssetRegisterController@saveStoresAssetRegisterParamData');
    Route::get('getStoresAssetInsuranceLinkageDetails', 'AssetRegisterController@getStoresAssetInsuranceLinkageDetails');
    Route::get('getStoresInsurancePoliciesForLinkage', 'AssetRegisterController@getStoresInsurancePoliciesForLinkage');
    Route::post('saveStoresAssetInsuranceLinkage', 'AssetRegisterController@saveStoresAssetInsuranceLinkage');
    Route::get('getStoresFundingForLinkage', 'AssetRegisterController@getStoresFundingForLinkage');
    Route::get('getStoresAssetFundingLinkageDetails', 'AssetRegisterController@getStoresAssetFundingLinkageDetails');
    Route::post('saveStoresAssetRequisitionRequestDetails', 'AssetRegisterController@saveStoresAssetRequisitionRequestDetails');
    Route::post('saveStoresAssetApprovalDetails', 'AssetRegisterController@saveStoresAssetApprovalDetails');
    Route::get('getStoresAssetCheckOutDetails', 'AssetRegisterController@getStoresAssetCheckOutDetails');
    Route::get('getStoresReservedAssets', 'AssetRegisterController@getStoresReservedAssets');
    Route::get('getStoresAssetInventoryDetailsForReservations', 'AssetRegisterController@getStoresAssetInventoryDetailsForReservations');
    Route::post('saveStoresAssetReservationDetails','AssetRegisterController@saveStoresAssetReservationDetails');
    Route::post('saveStoresAssetCheckOutInDetails','AssetRegisterController@saveStoresAssetCheckOutInDetails');
    Route::post('SaveStoresSingleSiteAssetCheckOutDetails','AssetRegisterController@SaveStoresSingleSiteAssetCheckOutDetails');
    Route::post('saveStoresMultipleIndividualsBulkCheckoutDetails','AssetRegisterController@saveStoresMultipleIndividualsBulkCheckoutDetails');
    Route::post('SaveStoresSingleSiteAssetCheckInDetails','AssetRegisterController@SaveStoresSingleSiteAssetCheckInDetails');
    Route::post('saveStoresIndividualBulkCheckInDetails','AssetRegisterController@saveStoresIndividualBulkCheckInDetails');
    Route::post('saveStoresSiteBulkCheckInDetails','AssetRegisterController@saveStoresSiteBulkCheckInDetails');
    Route::post('saveStoresMultipleusersBulkCheckInDetails','AssetRegisterController@saveStoresMultipleusersBulkCheckInDetails');
    Route::post('saveStoresAssetTransferDetails','AssetRegisterController@saveStoresAssetTransferDetails');
    Route::post('saveStoresAssetMaintainanceScheduleDetails','AssetRegisterController@saveStoresAssetMaintainanceScheduleDetails');
    Route::get('getStoresActiveScheduledRepairs','AssetRegisterController@getStoresActiveScheduledRepairs');
    Route::post('saveStoresAssetRepairScheduleDetails','AssetRegisterController@saveStoresAssetRepairScheduleDetails');
    Route::post('saveStoresAssetDisposalDetails','AssetRegisterController@saveStoresAssetDisposalDetails');
    Route::post('saveStoresAssetLossDamageDetails','AssetRegisterController@saveStoresAssetLossDamageDetails');  
    Route::post('saveStoresAssetWriteOffDetails','AssetRegisterController@saveStoresAssetWriteOffDetails'); 
    Route::post('StoresuploadAssets','AssetRegisterController@StoresuploadAssets');  
    Route::get('getStoresNextAssetBatchNo','AssetRegisterController@getStoresNextAssetBatchNo');  
    Route::post('saveStoresAssetInsuranceClaimDetails','AssetRegisterController@saveStoresAssetInsuranceClaimDetails');
    Route::get('getStoresAssetReport','AssetRegisterController@getStoresAssetReport');
    Route::get('generateStoresAssetsReport','AssetRegisterController@generateStoresAssetsReport');
    Route::get('getStoresAssetUploadBatches','AssetRegisterController@getStoresAssetUploadBatches');
    Route::post('UploadStoresMappedAssets','AssetRegisterController@UploadStoresMappedAssets');
    Route::get('getStoresPreUploadedAssets','AssetRegisterController@getStoresPreUploadedAssets');
    Route::get('getStoresSites','AssetRegisterController@getStoresSites');
    Route::post('saveStoresAssetMappingDetails','AssetRegisterController@saveStoresAssetMappingDetails');
    Route::post('saveStoresAssetAdditionalData','AssetRegisterController@saveStoresAssetAdditionalData');
    Route::post('saveStoresAssetMappingDetailsForDisposal','AssetRegisterController@saveStoresAssetMappingDetailsForDisposal');
    Route::post('saveStoresAssetMappingDetailsForLostAsset','AssetRegisterController@saveStoresAssetMappingDetailsForLostAsset');
});

