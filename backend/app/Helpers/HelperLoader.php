<?php

use App\Helpers\AuthHelper;
use App\Helpers\DbHelper;
use App\Helpers\DMSHelper;
use App\Helpers\ReportsHelper;
use App\Helpers\SecurityHelper;
use App\Helpers\UtilityHelper;
use App\Helpers\ValidationsHelper;
use Illuminate\Http\Request;

//todo: Security Helpers
if (!function_exists('aes_encrypt')) {
    function aes_encrypt($value)
    {
        return SecurityHelper::aes_encrypt($value);
    }
}

if (!function_exists('aes_decrypt')) {
    function aes_decrypt($value)
    {
        return SecurityHelper::aes_decrypt($value);
    }
}

if (!function_exists('encryptArray')) {
    function encryptArray($array, $skipArray)
    {
        return SecurityHelper::encryptArray($array, $skipArray);
    }
}

if (!function_exists('decryptArray')) {
    function decryptArray($array)
    {
        return SecurityHelper::decryptArray($array);
    }
}

//todo: Auth Helpers
if (!function_exists('generateUniqID')) {
    function generateUniqID()
    {
        return AuthHelper::generateUniqID();
    }
}

if (!function_exists('generatePwdSaltOnRegister')) {
    function generatePwdSaltOnRegister($username)
    {
        return AuthHelper::generatePwdSaltOnRegister($username);
    }
}

if (!function_exists('generatePwdSaltOnLogin')) {
    function generatePwdSaltOnLogin($username, $uuid)
    {
        return AuthHelper::generatePwdSaltOnLogin($username, $uuid);
    }
}

if (!function_exists('hashPwdOnRegister')) {
    function hashPwdOnRegister($username, $pwd)
    {
        return AuthHelper::hashPwdOnRegister($username, $pwd);
    }
}

if (!function_exists('hashPwd')) {
    function hashPwd($username, $uuid, $pwd)
    {
        return AuthHelper::hashPwd($username, $uuid, $pwd);
    }
}

if (!function_exists('hashPwdOnLogin')) {
    function hashPwdOnLogin($username, $uuid, $pwd)
    {
        return AuthHelper::hashPwdOnLogin($username, $uuid, $pwd);
    }
}

//todo: Db Helpers
if (!function_exists('convertStdClassObjToArray')) {
    function convertStdClassObjToArray($stdObjArray)
    {
        return DbHelper::convertStdClassObjToArray($stdObjArray);
    }
}
if (!function_exists('getSchoolenrollments')) {
    function getSchoolenrollments($school_type_id = 0, $no_check = false)
    {
        return DbHelper::getSchoolenrollments($school_type_id, $no_check);
    }
}


if (!function_exists('insertRecord')) {
    function insertRecord($table_name, $table_data, $user_id)
    {
        return DbHelper::insertRecord($table_name, $table_data, $user_id);
    }
}

if (!function_exists('insertRecordReturnId')) {
    function insertRecordReturnId($table_name, $table_data, $user_id)
    {
        return DbHelper::insertRecordReturnId($table_name, $table_data, $user_id);
    }
}

 


if (!function_exists('checkFeeChangesLimit')) {
    function checkFeeChangesLimit($school_id, $term, $grade, $school_status, $current_fees, $batch_id, $year)
    {
        return DbHelper::checkFeeChangesLimit($school_id, $term, $grade, $school_status, $current_fees, $batch_id, $year);
    }
}


if (!function_exists('updateRecord')) {
    function updateRecord($table_name, $previous_data, $where, $table_data, $user_id)
    {
        return DbHelper::updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
    }
}

if (!function_exists('deleteRecord')) {
    function deleteRecord($table_name, $previous_data, $where_data, $user_id)
    {
        return DbHelper::deleteRecord($table_name, $previous_data, $where_data, $user_id);
    }
}

if (!function_exists('deleteRecordWithComments')) {
    function deleteRecordWithComments($table_name, $previous_data, $where_data, $user_id,$comments)
    {
        return DbHelper::deleteRecordWithComments($table_name, $previous_data, $where_data, $user_id,$comments);
        
    }
}

if (!function_exists('deleteRecordNoAudit')) {
    function deleteRecordNoAudit($table_name, $where_data)
    {
        return DbHelper::deleteRecordNoAudit($table_name, $where_data);
    }
}

if (!function_exists('recordExists')) {
    function recordExists($table_name, $where)
    {
        return DbHelper::recordExists($table_name, $where);
    }
}

if (!function_exists('getPreviousRecords')) {
    function getPreviousRecords($table_name, $where)
    {
        return DbHelper::getPreviousRecords($table_name, $where);
    }
}

if (!function_exists('getRecordValFromWhere')) {
    function getRecordValFromWhere($table_name, $where, $col)
    {
        return DbHelper::getRecordValFromWhere($table_name, $where, $col);
    }
}

if (!function_exists('convertAssArrayToSimpleArray')) {
    function convertAssArrayToSimpleArray($assArray, $targetField)
    {
        return DbHelper::convertAssArrayToSimpleArray($assArray, $targetField);
    }
}

if (!function_exists('getUserGroups')) {
    function getUserGroups($user_id)
    {
        return DbHelper::getUserGroups($user_id);
    }
}

if (!function_exists('getSuperUserGroupId')) {
    function getSuperUserGroupId()
    {
        return DbHelper::getSuperUserGroupId();
    }
}

if (!function_exists('getStdTemplateId')) {
    function getStdTemplateId()
    {
        return DbHelper::getStdTemplateId();
    }
}

if (!function_exists('insertReturnID')) {
    function insertReturnID($table_name, $table_data)
    {
        return DbHelper::insertReturnID($table_name, $table_data);
    }
}

if (!function_exists('insertRecordNoAudit')) {
    function insertRecordNoAudit($table_name, $table_data)
    {
        return DbHelper::insertRecordNoAudit($table_name, $table_data);
    }
}

if (!function_exists('getConfirmationFlag')) {
    function getConfirmationFlag($flag)
    {
        return DbHelper::getConfirmationFlag($flag);
    }
}

if (!function_exists('getProcessTypeID')) {
    function getProcessTypeID($name_like)
    {
        return DbHelper::getProcessTypeID($name_like);
    }
}

if (!function_exists('getActiveTemplateID')) {
    function getActiveTemplateID()
    {
        return DbHelper::getActiveTemplateID();
    }
}

if (!function_exists('getActiveBatchID')) {
    function getActiveBatchID()
    {
        return DbHelper::getActiveBatchID();
    }
}

if (!function_exists('getActiveBatchTemplateID')) {
    function getActiveBatchTemplateID()
    {
        return DbHelper::getActiveBatchTemplateID();
    }
}

if (!function_exists('getSingleRecord')) {
    function getSingleRecord($table, $where)
    {
        return DbHelper::getSingleRecord($table, $where);
    }
}

if (!function_exists('getSingleRecordColValue')) {
    function getSingleRecordColValue($table, $where, $col)
    {
        return DbHelper::getSingleRecordColValue($table, $where, $col);
    }
}

if (!function_exists('getTermTotalLearningDays')) {
    function getTermTotalLearningDays($year, $term)
    {
        return DbHelper::getTermTotalLearningDays($year, $term);
    }
}

if (!function_exists('getSchoolfees')) {
    function getSchoolfees($school_id, $enrollment_type_id, $grade_id, $term_id, $year)
    {
        return DbHelper::getSchoolfees($school_id, $enrollment_type_id, $grade_id, $term_id, $year);

    }
}

if (!function_exists('getAnnualSchoolFees')) {
    function getAnnualSchoolFees($school_id, $enrollment_type_id, $grade_id, $year)
    {
        return DbHelper::getAnnualSchoolFees($school_id, $enrollment_type_id, $grade_id, $year);
    }
}

if (!function_exists('getTeamID')) {
    function getTeamID($id, $level)
    {
        return DbHelper::getTeamID($id, $level);
    }
}

if (!function_exists('logUpdateBeneficiaryInfo')) {
    function logUpdateBeneficiaryInfo($girl_id, $masterParams)
    {
        DbHelper::logUpdateBeneficiaryInfo($girl_id, $masterParams);
    }
}

if (!function_exists('logBeneficiaryGradeTransitioning')) {
    function logBeneficiaryGradeTransitioning($girl_id, $grade, $school_id, $user_id, $reason = '')
    {
        DbHelper::logBeneficiaryGradeTransitioning($girl_id, $grade, $school_id, $user_id, $reason);
    }
}

if (!function_exists('getSchoolBankDetails')) {
    function getSchoolBankDetails($school_bank_id, $school_id)
    {
        return DbHelper::getSchoolBankDetails($school_bank_id, $school_id);
    }
}

if (!function_exists('getUserDistricts')) {
    function getUserDistricts($user_id)
    {
        return DbHelper::getUserDistricts($user_id);
    }
}

if (!function_exists('getPaymentApproverRoles')) {
    function getPaymentApproverRoles()
    {
        return DbHelper::getPaymentApproverRoles();
    }
}

if (!function_exists('getAssignedComponents')) {
    function getAssignedComponents($user_id)
    {
        return DbHelper::getAssignedComponents($user_id);
    }
}

if (!function_exists('getParamsdata')) {
    function getParamsdata($qry)
    {
        DbHelper::getParamsdata($qry);
    }
}

if (!function_exists('getMenuItemWorkflowStages')) {
    function getMenuItemWorkflowStages($menu_id)
    {
        return DbHelper::getMenuItemWorkflowStages($menu_id);
    }
}

if (!function_exists('getRecordInitialStatus')) {
    function getRecordInitialStatus($process_id)
    {
        return DbHelper::getRecordInitialStatus($process_id);
    }
}

if (!function_exists('getRecordTransitionStatus')) {
    function getRecordTransitionStatus($prev_stage, $action, $next_stage, $static_status = '')
    {
        return DbHelper::getRecordTransitionStatus($prev_stage, $action, $next_stage, $static_status);
    }
}

if (!function_exists('getTableData')) {
    function getTableData($table_name, $where)
    {
        return DbHelper::getTableData($table_name, $where);
    }
}

if (!function_exists('getEmailTemplateInfo')) {
    function getEmailTemplateInfo($template_id, $vars)
    {
        return DbHelper::getEmailTemplateInfo($template_id, $vars);
    }
}

if (!function_exists('getBeneficiarySchMatchingPlacementDetails')) {
    function getBeneficiarySchMatchingPlacementDetails($qry, $sub_category, $benTableAlias)
    {
        DbHelper::getBeneficiarySchMatchingPlacementDetails($qry, $sub_category, $benTableAlias);
    }
}

//todo: Utility Helpers
if (!function_exists('getTimeDiffHrs')) {
    function getTimeDiffHrs($time1, $time2)
    {
        return UtilityHelper::getTimeDiffHrs($time1, $time2);
    }
}

if (!function_exists('is_connected')) {
    function is_connected()
    {
        return UtilityHelper::is_connected();
    }
}
if (!function_exists('landscapereport_header')) {
    function landscapereport_header($form_id = '')
    {
        UtilityHelper::landscapereport_header($form_id);
    }
}
if (!function_exists('defaultreport_header')) {
    function defaultreport_header($title)
    {
        UtilityHelper::defaultreport_header($title);
    }
}
if (!function_exists('defaultreport_headerLandscape')) {
    function defaultreport_headerLandscape($title)
    {
        UtilityHelper::defaultreport_headerLandscape($title);
    }
}

if (!function_exists('validateExcelUpload')) {
    function validateExcelUpload($filename)
    {
        return UtilityHelper::validateExcelUpload($filename);
    }
}

if (!function_exists('converter1')) {
    function converter1($date)
    {
        return UtilityHelper::converter1($date);
    }
}

if (!function_exists('converter2')) {
    function converter2($date)
    {
        return UtilityHelper::converter2($date);
    }
}

if (!function_exists('converter11')) {
    function converter11($date)
    {
        return UtilityHelper::converter11($date);
    }
}

if (!function_exists('converter22')) {
    function converter22($date)
    {
        return UtilityHelper::converter22($date);
    }
}

if (!function_exists('dateConverter')) {
    function dateConverter($date, $format)
    {
        return UtilityHelper::dateConverter($date, $format);
    }
}

if (!function_exists('formatMoney')) {
    function formatMoney($value)
    {
        return UtilityHelper::formatMoney($value);
    }
}

if (!function_exists('json_output')) {
    function json_output($data = array(), $content_type = 'json')
    {
        UtilityHelper::json_output($data);
    }

}
if (!function_exists('utf8ize')) {
    function utf8ize($d)
    {
        return UtilityHelper::utf8ize($d);
    }
}

if (!function_exists('generateCaserefNo')) {
    function generateCaserefNo($code)
    {
        return UtilityHelper::generateCaserefNo($code);
    }
}
if (!function_exists('generatePaymentverificationBatchNo')) {
    function generatePaymentverificationBatchNo($is_reconciliation = false)
    {
        return UtilityHelper::generatePaymentverificationBatchNo($is_reconciliation);
    }
}
if (!function_exists('generatePaymentRequestRefNo')) {
    function generatePaymentRequestRefNo($year)
    {
        return UtilityHelper::generatePaymentRequestRefNo($year);
    }
}
if (!function_exists('generateReceiptFileNo')) {
    function generateReceiptFileNo()
    {
        return UtilityHelper::generateReceiptFileNo();
    }
}
if (!function_exists('getReferenceserials')) {
    function getReferenceserials($table_name, $year)
    {
        return UtilityHelper::getReferenceserials($table_name, $year);
    }
}

if (!function_exists('formatDate')) {
    function formatDate($date)
    {
        return UtilityHelper::formatDate($date);
    }
}

if (!function_exists('formatDaterpt')) {
    function formatDaterpt($date)
    {
        return UtilityHelper::formatDaterpt($date);
    }
}

if (!function_exists('generateBatchNumber')) {
    function generateBatchNumber($year, $user_id)
    {
        return UtilityHelper::generateBatchNumber($year, $user_id);

    }
}

if (!function_exists('getEnquiryfilter')) {
    function getEnquiryfilter($value, $key)
    {
        return UtilityHelper::getEnquiryfilter($value, $key);
    }
}

if (!function_exists('returnUniqueArray')) {
    function returnUniqueArray($array, $key)
    {
        return UtilityHelper::returnUniqueArray($array, $key);
    }
}
if (!function_exists('getExportReportHeader')) {
    function getExportReportHeader($colspan, $title)
    {
        return UtilityHelper::getExportReportHeader($colspan, $title);
    }
}

if (!function_exists('getSchBenefiaryenrollmentcounter')) {
    function getSchBenefiaryenrollmentcounter($year_of_enrollment, $term_id, $school_id, $payment_request_id = '')
    {
        return UtilityHelper::getSchBenefiaryenrollmentcounter($year_of_enrollment, $term_id, $school_id, $payment_request_id);
    }
}
if (!function_exists('getBenefiaryenrollmentcounter')) {
    function getBenefiaryenrollmentcounter($year_of_enrollment, $term_id, $payment_request_id = '', $is_validated)
    {
        return UtilityHelper::getBenefiaryenrollmentcounter($year_of_enrollment, $term_id, $payment_request_id, $is_validated);
    }
}

if (!function_exists('getBeneficiaryPaymentRecords')) {
    function getBeneficiaryPaymentRecords($year_of_enrollment, $term_id, $payment_request_id = '')
    {
        return UtilityHelper::getBeneficiaryPaymentRecords($year_of_enrollment, $term_id, $payment_request_id);
    }
}

if (!function_exists('getPaymentdisbursements')) {
    function getPaymentdisbursements($year_of_enrollment, $term_id, $payment_request_id = '')
    {
        return UtilityHelper::getPaymentdisbursements($year_of_enrollment, $term_id, $payment_request_id);
    }
}

if (!function_exists('getSchoolPaymentdisbursements')) {
    function getSchoolPaymentdisbursements($year_of_enrollment, $term_id, $school_id, $payment_request_id = '')
    {
        return UtilityHelper::getSchoolPaymentdisbursements($year_of_enrollment, $term_id, $school_id, $payment_request_id);
    }
}

if (!function_exists('checkBeneficiaryRepetitionStatus')) {
    function checkBeneficiaryRepetitionStatus($counter, $girl_id, $grade)
    {
        return UtilityHelper::checkBeneficiaryRepetitionStatus($counter, $girl_id, $grade);
    }
}

if (!function_exists('checkMissingPayments')) {
    function checkMissingPayments($counter, $girl_id, $year, $term)
    {
        return UtilityHelper::checkMissingPayments($counter, $girl_id, $year, $term);
    }
}

if (!function_exists('calculateAttendanceRate')) {
    function calculateAttendanceRate($girl_id, $year, $term)
    {
        return UtilityHelper::calculateAttendanceRate($girl_id, $year, $term);
    }
}

if (!function_exists('checkPaymentValidationRuleStatus')) {
    function checkPaymentValidationRuleStatus($rule_id)
    {
        return UtilityHelper::checkPaymentValidationRuleStatus($rule_id);
    }
}

if (!function_exists('getBeneficiaryReceiptCounter')) {
    function getBeneficiaryReceiptCounter($year, $term, $payment_request_id = '')
    {
        return UtilityHelper::getBeneficiaryReceiptCounter($year, $term, $payment_request_id);
    }
}

if (!function_exists('getSchoolBeneficiaryReceiptCounter')) {
    function getSchoolBeneficiaryReceiptCounter($year, $term, $school_id, $payment_request_id = '')
    {
        return UtilityHelper::getSchoolBeneficiaryReceiptCounter($year, $term, $school_id, $payment_request_id);
    }
}

if (!function_exists('generateReconciliationOversightBatchNumber')) {
    function generateReconciliationOversightBatchNumber($year, $term)
    {
        return UtilityHelper::generateReconciliationOversightBatchNumber($year, $term);
    }
}

if (!function_exists('getPreviousTerm')) {
    function getPreviousTerm($year, $term)
    {
        return UtilityHelper::getPreviousTerm($year, $term);
    }
}

if (!function_exists('getCurrentTerm')) {
    function getCurrentTerm($year, $term)
    {
        return UtilityHelper::getCurrentTerm($year, $term);
    }
}

if (!function_exists('getReconciliationSuspenseAmount')) {
    function getReconciliationSuspenseAmount($request_id, $school_id)
    {
        return UtilityHelper::getReconciliationSuspenseAmount($request_id, $school_id);
    }
}

if (!function_exists('checkEnrollmentDuplicates')) {
    function checkEnrollmentDuplicates($request_id)
    {
        return UtilityHelper::checkEnrollmentDuplicates($request_id);
    }
}

if (!function_exists('unserializeDeletedData')) {
    function unserializeDeletedData($record_id)
    {
        UtilityHelper::unserializeDeletedData($record_id);
    }
}

if (!function_exists('getSuspenseAmounts')) {
    function getSuspenseAmounts($year_of_enrollment, $term_id, $payment_request_id = '')
    {
        return UtilityHelper::getSuspenseAmounts($year_of_enrollment, $term_id, $payment_request_id);
    }
}

if (!function_exists('getSchBeneficiaryPaymentRecords')) {
    function getSchBeneficiaryPaymentRecords($year_of_enrollment, $term_id, $school_id, $payment_request_id = '')
    {
        return UtilityHelper::getSchBeneficiaryPaymentRecords($year_of_enrollment, $term_id, $school_id, $payment_request_id);
    }
}

if (!function_exists('getSchoolSuspenseAmounts')) {
    function getSchoolSuspenseAmounts($year_of_enrollment, $term_id, $school_id, $payment_request_id = '')
    {
        return UtilityHelper::getSchoolSuspenseAmounts($year_of_enrollment, $term_id, $school_id, $payment_request_id);
    }
}

if (!function_exists('getSetCurrentTerm')) {
    function getSetCurrentTerm()
    {
        return UtilityHelper::getSetCurrentTerm();
    }
}

if (!function_exists('getWeeklyBordersTopUpAmount')) {
    function getWeeklyBordersTopUpAmount()
    {
        return UtilityHelper::getWeeklyBordersTopUpAmount();
    }
}

//job on 20/4/2022
if (!function_exists('getGrantAidedTopUpAmount')) {
    function getGrantAidedTopUpAmount()
    {
        return UtilityHelper::getGrantAidedTopUpAmount();
    }
}

if (!function_exists('returnMessage')) {
    function returnMessage($results)
    {
        return UtilityHelper::returnMessage($results);
    }
}

if (!function_exists('getBatchVerificationChecklist')) {
    function getBatchVerificationChecklist($batch_id, $category)
    {
        return UtilityHelper::getBatchVerificationChecklist($batch_id, $category);
    }
}

if (!function_exists('getBatchPaymentEligibility')) {
    function getBatchPaymentEligibility($batch_id)
    {
        return UtilityHelper::getBatchPaymentEligibility($batch_id);
    }
}

if (!function_exists('generateRecordViewID')) {
    function generateRecordViewID()
    {
        return UtilityHelper::generateRecordViewID();
    }
}

if (!function_exists('generateRecordRefNumber')) {
    function generateRecordRefNumber($ref_id, $process_id, $district_id, $codes_array, $table_name, $user_id)
    {
        return UtilityHelper::generateRecordRefNumber($ref_id, $process_id, $district_id, $codes_array, $table_name, $user_id);
    }
}

if (!function_exists('generateRefNumber')) {
    function generateRefNumber($codes_array, $ref_id)
    {
        return UtilityHelper::generateRefNumber($codes_array, $ref_id);
    }
}

if (!function_exists('getSystemBasePath')) {
    function getSystemBasePath()
    {
        return UtilityHelper::getSystemBasePath();
    }
}

//todo: DMS Helpers
if (!function_exists('dms_createFolder')) {
    function dms_createFolder($parent_folder, $name, $comment, $user_email)
    {
        return DMSHelper::dms_createFolder($parent_folder, $name, $comment, $user_email);

    }
}

if (!function_exists('createDMSParentFolder')) {
    function createDMSParentFolder($parent_folder, $module_id = 0, $name, $comment, $owner)
    {
        return DMSHelper::createDMSParentFolder($parent_folder, $module_id, $name, $comment, $owner);

    }
}

if (!function_exists('authDms')) {
    function authDms($usr_name)
    {
        return DMSHelper::authDms($usr_name);

    }
}
//get folder documents
if (!function_exists('dms_FolderDocuments')) {
    function dms_FolderDocuments($folder_id, $user_email)
    {
        return DMSHelper::dms_FolderDocuments($folder_id, $user_email);

    }
}
//check folder documents
if (!function_exists('check_DmsFolderDocuments')) {
    function check_DmsFolderDocuments($folder_id, $user_email)
    {
        return DMSHelper::check_DmsFolderDocuments($folder_id, $user_email);

    }
}

if (!function_exists('createDMSModuleFolders')) {
    function createDMSModuleFolders($parent_id, $module_id, $owner)
    {
        return DMSHelper::createDMSModuleFolders($parent_id, $module_id, $owner);

    }
}

if (!function_exists('getSubModuleFolderID')) {
    function getSubModuleFolderID($parent_folder_id, $sub_module_id)
    {
        return DMSHelper::getSubModuleFolderID($parent_folder_id, $sub_module_id);
    }
}

if (!function_exists('getSubModuleFolderIDWithCreate')) {
    function getSubModuleFolderIDWithCreate($parent_folder_id, $sub_module_id, $owner)
    {
        return DMSHelper::getSubModuleFolderIDWithCreate($parent_folder_id, $sub_module_id, $owner);
    }
}

if (!function_exists('updateDocumentSequence')) {
    function updateDocumentSequence($parent, $order_no)
    {
        $dmsHelper = new DMSHelper();
        return $dmsHelper->updateDocumentSequence($parent, $order_no);
    }
}

if (!function_exists('saveRecordReturnId')) {
    function saveRecordReturnId($data, $table)
    {
        $dmsHelper = new DMSHelper();
        return $dmsHelper->saveRecordReturnId($data, $table);
    }
}

if (!function_exists('saveRecord')) {
    function saveRecord($data, $table)
    {
        $dmsHelper = new DMSHelper();
        return $dmsHelper->saveRecord($data, $table);
    }
}

if (!function_exists('getfile_extension')) {
    function getfile_extension($fileName)
    {
        $dmsHelper = new DMSHelper();
        return $dmsHelper->getfile_extension($fileName);
    }
}

if (!function_exists('fileSize')) {
    function fileSize($file)
    {
        $dmsHelper = new DMSHelper();
        return $dmsHelper->fileSize($file);
    }
}

if (!function_exists('format_filesize')) {
    function format_filesize($size, $sizes = array('Bytes', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'))
    {
        $dmsHelper = new DMSHelper();
        return $dmsHelper->format_filesize($size, $sizes);
    }
}

if (!function_exists('parse_filesize')) {
    function parse_filesize($str)
    {
        $dmsHelper = new DMSHelper();
        return $dmsHelper->parse_filesize($str);
    }
}

if (!function_exists('getParent')) {
    function getParent($folderId)
    {
        $dmsHelper = new DMSHelper();
        return $dmsHelper->getParent($folderId);
    }
}

if (!function_exists('addContent')) {
    function addContent($documentid, $version, $versioncomment)
    {
        $dmsHelper = new DMSHelper();
        return $dmsHelper->addContent($documentid, $version, $versioncomment);
    }
}

if (!function_exists('getChecksum')) {
    function getChecksum($file)
    {
        $dmsHelper = new DMSHelper();
        return $dmsHelper->getChecksum($file);
    }
}

if (!function_exists('getPath')) {
    function getPath($folderId)
    {
        $dmsHelper = new DMSHelper();
        return $dmsHelper->getPath($folderId);
    }
}

if (!function_exists('addDocument')) {
    function addDocument($doc_name, $doc_comment, $file_name, $folder_id, $versioncomment = '', $is_array_return = false)
    {
        $dmsHelper = new DMSHelper();
        return $dmsHelper->addDocument($doc_name, $doc_comment, $file_name, $folder_id, $versioncomment, $is_array_return);
    }
}

if (!function_exists('getParentFolderID')) {
    function getParentFolderID($table, $parent_record_id)
    {
        return DMSHelper::getParentFolderID($table, $parent_record_id);
    }
}

if (!function_exists('getDMSPath')) {
    function getDMSPath()
    {
        $dmsHelper = new DMSHelper();
        return $dmsHelper->getDMSPath();
    }
}

//todo: Validation Helpers
if (!function_exists('validateisNumeric')) {
    function validateisNumeric($value)
    {
        return ValidationsHelper::validateisNumeric($value);
    }
}

if (!function_exists('validateType')) {
    function validateType($value, $type)
    {
        return ValidationsHelper::validateType($value, $type);
    }
}

if (!function_exists('validateIsMandatory')) {
    function validateIsMandatory($value)
    {
        return ValidationsHelper::validateIsMandatory($value);
    }
}

if (!function_exists('validateCodeNameHyphened')) {
    function validateCodeNameHyphened($value)
    {
        return ValidationsHelper::validateCodeNameHyphened($value);
    }
}

if (!function_exists('validateIsParameterized')) {
    function validateIsParameterized($table, $value, $flag)
    {
        return ValidationsHelper::validateIsParameterized($table, $value, $flag);
    }
}

//todo: Report Helpers
if (!function_exists('generateJasperReport')) {
    function generateJasperReport($input_file_name, $output_filename, $file_type, $params = array())
    {
        $reportsHelper = new ReportsHelper();
        return $reportsHelper->generateJasperReport($input_file_name, $output_filename, $file_type, $params);
    }
}

if (!function_exists('checkForLocalIPs')) {
    function checkForLocalIPs()
    {
        return UtilityHelper::checkForLocalIPs();
    }
}

if (!function_exists('excelNumberToAlpha')) {
    function excelNumberToAlpha($numberOfCols, $code)
    {
        return UtilityHelper::excelNumberToAlpha($numberOfCols, $code);
    }
}

if (!function_exists('exportSystemRecords')) {
    function exportSystemRecords(Request $request, $data)
    {
        return UtilityHelper::exportSystemRecords($request, $data);
    }
}

if (!function_exists('calculateStraightLineDepreciation')) {
    function calculateStraightLineDepreciation(Object $depreciation_details,string $test_date="",$only_end_date=false,$get_cumulative_depreciation=false,$get_total_depreciation=false)
    {
        return UtilityHelper::calculateStraightLineDepreciation($depreciation_details,$test_date,$only_end_date,$get_cumulative_depreciation,$get_total_depreciation);
    }
}

if (!function_exists('calculatePercentageDepreciation')) {
    function  calculatePercentageDepreciation(Object $depreciation_details,string $test_date="",$percentage_rate,$only_end_date=false,$only_salvage_value=false,$get_cumulative_depreciation=false,$get_total_depreciation=false)
    {
        return UtilityHelper::calculatePercentageDepreciation($depreciation_details,$test_date,$percentage_rate,$only_end_date,$only_salvage_value,$get_cumulative_depreciation,$get_total_depreciation);
    }
}

if (!function_exists('createAssetDMSParentFolder')) {
    function createAssetDMSParentFolder($parent_folder, $module_id = 0, $name, $comment, $owner)
    {
        return DMSHelper::createAssetDMSParentFolder($parent_folder, $module_id, $name, $comment, $owner);
    }
}

if (!function_exists('createAssetRegisterDMSModuleFolders')) {
    function createAssetRegisterDMSModuleFolders($parent_id, $module_id,$sub_module_id, $owner)
    {
        return DMSHelper::createAssetRegisterDMSModuleFolders($parent_id, $module_id,$sub_module_id,$owner);
    }
}
