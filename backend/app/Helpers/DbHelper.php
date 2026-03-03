<?php
/**
 * Created by PhpStorm.
 * User: Kip
 * Date: 8/2/2017
 * Time: 7:23 PM
 */

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Carbon\Carbon;

class DbHelper
{
    static function insertRecord($table_name, $table_data, $user_id)
    {
        $result = array();
        DB::transaction(function () use ($table_name, $table_data, $user_id, &$result) {
            try {
                $record_id = DB::table($table_name)->insertGetId($table_data);
                $data = serialize($table_data);
                $audit_detail = array(
                    'table_name' => $table_name,
                    'table_action' => 'insert',
                    'record_id' => $record_id,
                    'current_tabledata' => $data,
                    'ip_address' => self::getIPAddress(),
                    'created_by' => $user_id,
                    'created_at' => Carbon::now()
                );
                DB::table('audit_trail')->insertGetId($audit_detail);
                $result = array(
                    'success' => true,
                    'record_id' => $record_id,
                    'message' => 'Data saved successfully!!'
                );
            } catch (\Exception $exception) {
                $result = array(
                    'success' => false,
                    'message' => $exception->getMessage()
                );
            }
        }, 5);
        return $result;
    }

    static function getSchoolenrollments($school_type_id, $no_check)
    {
        $qry = DB::table('beneficiary_school_statuses as t1')
            ->select('t1.name', 't1.id as school_enrollment_id', 't2.school_type_id', 't1.code')
            ->leftJoin('school_typeenrollment_setup as t2', 't1.id', '=', 't2.school_enrollment_id');
        if ($no_check == true) {
            $qry->where('t1.id', '<', '4');
        } else {
            $qry->where('t1.id', '<', '3');
        }
        $qry->groupBy('t1.id');
        $result = $qry->get();

        return $result;
    }

    static function insertRecordNoAudit($table_name, $table_data)
    {
        $result = false;
        DB::transaction(function () use ($table_name, $table_data, &$result) {
            try {
                DB::table($table_name)->insert($table_data);
                $result = true;
            } catch (QueryException $exception) {
                $result = $exception->getMessage();
            }
        }, 5);
        return $result;
    }

    static function insertRecordReturnId($table_name, $table_data, $user_id)
    {
        $insert_id = '';
        DB::transaction(function () use ($table_name, $table_data, $user_id, &$insert_id) {
            try {
                $insert_id = DB::table($table_name)->insertGetId($table_data);
                try {
                    $data = serialize($table_data);
                    $audit_detail = array(
                        'table_name' => $table_name,
                        'table_action' => 'insert',
                        'record_id' => $insert_id,
                        'current_tabledata' => $data,
                        'ip_address' => self::getIPAddress(),
                        'created_by' => $user_id,
                        'created_at' => Carbon::now()
                    );
                    DB::table('audit_trail')->insert($audit_detail);
                } catch (QueryException $exception) {
                    echo $exception->getMessage();
                    exit();
                    $insert_id = '';
                }
            } catch (QueryException $exception) {
                echo $exception->getMessage();
                exit();
                $insert_id = '';
            }
        }, 5);
        return $insert_id;

    }

    static function updateRecord($table_name, $previous_data, $where_data, $current_data, $user_id)
    {
        $res = false;
        try {
            DB::transaction(function () use ($table_name, $previous_data, $where_data, $current_data, $user_id, &$res) {
                DB::table($table_name)
                    ->where($where_data)
                    ->update($current_data);
                $record_id = $previous_data[0]['id'];
                $data_previous = serialize($previous_data);
                $data_current = serialize($current_data);
                $audit_detail = array(
                    'table_name' => $table_name,
                    'table_action' => 'update',
                    'record_id' => $record_id,
                    'prev_tabledata' => $data_previous,
                    'current_tabledata' => $data_current,
                    'ip_address' => self::getIPAddress(),
                    'created_by' => $user_id,
                    'created_at' => Carbon::now()
                );

                DB::table('audit_trail')->insert($audit_detail);
                $res = array(
                    'success' => true,
                    'message' => 'Data updated successfully!!'
                );
            }, 5);
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return $res;
    }

    static function deleteRecordWithComments($table_name, $previous_data, $where_data, $user_id,$delete_reason)
    {
        $res = array();
        try {
            DB::transaction(function () use ($table_name, $previous_data, $where_data, $user_id, &$res,$delete_reason) {
                DB::table($table_name)->where($where_data)->delete();
                $record_id = $previous_data[0]['id'];
                $data_previous = serialize($previous_data);
                $audit_detail = array(
                    'table_name' => $table_name,
                    'table_action' => 'delete',
                    'record_id' => $record_id,
                    'prev_tabledata' => $data_previous,
                    'ip_address' => self::getIPAddress(),
                    'created_by' => $user_id,
                    'created_at' => date('Y-m-d H:i:s')
                );
                $audit_id=DB::table('audit_trail')->insertGetId($audit_detail);
                if (validateisNumeric($audit_id)) {
                    DB::table('audit_trail_comments')->insert(
                    [
                    "audit_trail_id"=>$audit_id,
                    "comments"=>$delete_reason,
                    "created_at"=>Carbon::now(),
                    "created_by"=>$user_id
                    ]);
                }
                $res = array(
                    'success' => true,
                    'message' => 'Record deleted successfully!!'
                );
            }, 5);
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return $res;
    }
    
    static function deleteRecord($table_name, $previous_data, $where_data, $user_id)
    {
        $res = array();
        try {
            DB::transaction(function () use ($table_name, $previous_data, $where_data, $user_id, &$res) {
                DB::table($table_name)->where($where_data)->delete();
                $record_id = $previous_data[0]['id'];
                $data_previous = serialize($previous_data);
                $audit_detail = array(
                    'table_name' => $table_name,
                    'table_action' => 'delete',
                    'record_id' => $record_id,
                    'prev_tabledata' => $data_previous,
                    'ip_address' => self::getIPAddress(),
                    'created_by' => $user_id,
                    'created_at' => date('Y-m-d H:i:s')
                );
                DB::table('audit_trail')->insert($audit_detail);
                $res = array(
                    'success' => true,
                    'message' => 'Record deleted successfully!!'
                );
            }, 5);
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return $res;
    }

    static function deleteRecordNoAudit($table_name, $where_data)
    {
        $result = false;
        DB::transaction(function () use ($table_name, $where_data, &$result) {
            try {
                $affectedRows = DB::table($table_name)->where($where_data)->delete();
                if ($affectedRows) {
                    $result = true;
                } else {
                    $result = false;
                }
            } catch (QueryException $exception) {
                echo $exception->getMessage();
                exit();
                $result = false;
            }

        }, 5);
        return $result;
    }

    static function recordExists($table_name, $where)
    {
        $recordExist = DB::table($table_name)->where($where)->get();
        if ($recordExist && count($recordExist) > 0) {
            return true;
        }
        return false;
    }

    static function getPreviousRecords($table_name, $where)
    {
        $prev_records = DB::table($table_name)->where($where)->get();
        if ($prev_records && count($prev_records) > 0) {
            $prev_records = self::convertStdClassObjToArray($prev_records);
            return $prev_records;
        }
        return array();
    }

    static function auditTrail($table_name, $table_action, $prev_tabledata, $table_data, $user_id)
    {
        $result = false;
        $ip_address = self::getIPAddress();
        switch ($table_action) {
            case "insert":
                try {
                    //get serialised data $row_array = $sql_query->result_array();
                    $data = $table_data;
                    $audit_detail = array(
                        'table_name' => $table_name,
                        'table_action' => $table_action,
                        'current_tabledata' => $data,
                        'ip_address' => $ip_address,
                        'created_by' => $user_id,
                        'created_at' => date('Y-m-d H:i:s')
                    );
                    DB::table('audit_trail')->insert($audit_detail);
                    $result = true;
                } catch (QueryException $exception) {
                    $result = false;
                } catch (\Exception $exception) {
                    $result = false;
                }
                break;
            case "update":
                try {
                    //get serialised data $row_array = $sql_query->result_array();
                    $data_previous = serialize($prev_tabledata);
                    $data_current = serialize($table_data);
                    $audit_detail = array(
                        'table_name' => $table_name,
                        'table_action' => 'update',
                        'prev_tabledata' => $data_previous,
                        'current_tabledata' => $data_current,
                        'ip_address' => $ip_address,
                        'created_by' => $user_id,
                        'created_at' => date('Y-m-d H:i:s')
                    );
                    DB::table('audit_trail')->insert($audit_detail);
                    $result = true;
                } catch (QueryException $exception) {
                    $result = false;
                } catch (\Exception $exception) {
                    $result = false;
                }
                break;
            case "delete":
                try {
                    //get serialised data $row_array = $sql_query->result_array();
                    $data_previous = serialize($prev_tabledata);
                    $audit_detail = array(
                        'table_name' => $table_name,
                        'table_action' => 'delete',
                        'prev_tabledata' => $data_previous,
                        'ip_address' => $ip_address,
                        'created_by' => $user_id,
                        'created_at' => date('Y-m-d H:i:s')
                    );
                    DB::table('audit_trail')->insert($audit_detail);
                    $result = true;
                } catch (QueryException $exception) {
                    $result = false;
                } catch (\Exception $exception) {
                    $result = false;
                }
                break;
            default:
                $result = false;
        }
        return $result;
    }

    static function getRecordValFromWhere($table_name, $where, $col)
    {
        try {
            $record = DB::table($table_name)
                ->select($col)
                ->where($where)->get();
            return self::convertStdClassObjToArray($record);
        } catch (QueryException $exception) {
            echo $exception->getMessage();
            return false;
        }
    }

    //without auditing
    static function insertReturnID($table_name, $table_data)
    {
        $insert_id = '';
        DB::transaction(function () use ($table_name, $table_data, &$insert_id) {
            try {
                $insert_id = DB::table($table_name)->insertGetId($table_data);
            } catch (QueryException $exception) {
                echo $exception->getMessage();
                $insert_id = '';
            }
        }, 5);
        return $insert_id;
    }

    static function convertStdClassObjToArray($stdObj)
    {
        $simpleArray = json_encode($stdObj);
        return json_decode($simpleArray, true);
        
        // return json_decode(json_encode($stdObj), true);
        // return (array) $stdObj;
    }

    static function convertAssArrayToSimpleArray($assArray, $targetField)
    {
        $simpleArray = array();
        foreach ($assArray as $key => $array) {
            $simpleArray[] = $array[$targetField];
        }
        return $simpleArray;
    }

    static function getIPAddress()
    {

        if (isset($_SERVER)) {
            if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
                $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
                if (strpos($ip, ",")) {
                    $exp_ip = explode(",", $ip);
                    $ip = $exp_ip[0];
                }
            } else if (isset($_SERVER["HTTP_CLIENT_IP"])) {
                $ip = $_SERVER["HTTP_CLIENT_IP"];
            } else {
                $ip = $_SERVER["REMOTE_ADDR"];
            }
        } else {
            if (getenv('HTTP_X_FORWARDED_FOR')) {
                $ip = getenv('HTTP_X_FORWARDED_FOR');
                if (strpos($ip, ",")) {
                    $exp_ip = explode(",", $ip);
                    $ip = $exp_ip[0];
                }
            } else if (getenv('HTTP_CLIENT_IP')) {
                $ip = getenv('HTTP_CLIENT_IP');
            } else {
                $ip = getenv('REMOTE_ADDR');
            }
        }
        return $ip;
    }

    static function getUserGroups($user_id)
    {
        $groupsSimpleArray = array();
        $groups = DB::table('user_group')->where('user_id', $user_id)->get()->toArray();
        foreach ($groups as $group) {
            $groupsSimpleArray[] = $group->group_id;
        }
        return $groupsSimpleArray;
    }

    static function getSuperUserGroupId()
    {
        $allGroups = DB::table('groups')->get();
        $allGroups = convertStdClassObjToArray($allGroups);
        $decryptedGroups = decryptArray($allGroups);
        foreach ($decryptedGroups as $decryptedGroup) {
            if (stripos($decryptedGroup['name'], 'super') === false) {
                //  return 0;
            } else {
                return $decryptedGroup['id'];
            }
        }
        return 0;
    }

    static function getUserDistricts($user_id)
    {
        $districtsSimpleArray = array();
        $districts = DB::table('user_district')->where('user_id', $user_id)->get()->toArray();
        foreach ($districts as $district) {
            $districtsSimpleArray[] = $district->district_id;
        }
        return $districtsSimpleArray;
    }

    static function getStdTemplateId()
    {
        $allTemplates = DB::table('templates')->get();
        $allTemplates = convertStdClassObjToArray($allTemplates);
        $decryptedTemplates = decryptArray($allTemplates);
        foreach ($decryptedTemplates as $decryptedTemplates) {
            if (stripos($decryptedTemplates['name'], 'standard') === false) {
                //  return 0;
            } else {
                return $decryptedTemplates['id'];
            }
        }
        return 0;
    }

    static function getActiveTemplateID()
    {
        $template_info = DB::table('active_template')->first();
        if (is_null($template_info)) {
            return false;
        } else {
            return $template_info->template_id;
        }
    }

    static function getActiveBatchID()
    {
        $batch_info = DB::table('batch_info')->where('is_active', 1)->first();
        if (is_null($batch_info)) {
            return false;
        } else {
            return $batch_info->id;
        }
    }

    static function getActiveBatchTemplateID()
    {
        $batch_info = DB::table('batch_info')->where('is_active', 1)->first();
        if (is_null($batch_info)) {
            return false;
        } else {
            return $batch_info->template_id;
        }
    }

    static function getConfirmationFlag($flag)
    {
        $flag_name = DB::table('confirmations')->where('flag', $flag)->value('name');
        if (!is_null($flag_name)) {
            return aes_decrypt($flag_name);
        }
        return 'Undefined';
    }

    static function getProcessTypeID($name_like)
    {
        $process_type_id = DB::table('serialized_processes')->where('name', 'like', '%' . $name_like . '%')->value('id');
        return $process_type_id;
    }

    static function getSingleRecord($table, $where)
    {
        $record = DB::table($table)->where($where)->first();
        return $record;
    }

    static function getSingleRecordColValue($table, $where, $col)
    {
        $val = DB::table($table)->where($where)->value($col);
        return $val;
    }

    static function getTeamID($id, $level)
    {
        //level 1=school
        //level 2=CWAC
        //level 3=district
        //level 4=province
        $levels_array = array(
            '1' => 'school_information',
            '2' => 'cwac',
            '3' => 'districts',
            '4' => 'provinces'//less likely
        );
        $province_id = DB::table($levels_array[$level])
            ->where('id', $id)
            ->value('province_id');
        $team_id = DB::table('fieldteam_provinces')
            ->join('field_teams', 'fieldteam_provinces.team_id', '=', 'field_teams.id')
            ->where('fieldteam_provinces.province_id', $province_id)
            ->value('field_teams.team_id');
        return $team_id;
    }

    //hiram code
    static function getParamsdata($qry)
    {
        $data = $qry->get();
        $results = convertStdClassObjToArray($data);
        $results = decryptArray($results);
        $res = array(
            'results' => $results
        );
        json_output($res);
    }

    static function getSchoolfees($school_id, $enrollment_type_id, $grade_id, $term_id, $year)
    {
        $where = array(
            'school_enrollment_id' => $enrollment_type_id,
            'year' => $year,
            'school_id' => $school_id,
            'grade_id' => $grade_id,
            'term_id' => $term_id
        );
        $fees_amount = DB::table('school_feessetup')
            ->where($where)
            ->value("fees_amount");
        return ($fees_amount);
    }

    static function getAnnualSchoolFees($school_id, $enrollment_type_id, $grade_id, $year)
    {
        $fees_amount = array(
            'term1_fees' => 0,
            'term2_fees' => 0,
            'term3_fees' => 0,
            'day_fee' => 0,
            'boarder_fee' => 0,
            'weekly_boarder_fee' => 0
        );
        $where = array(
            // 'school_enrollment_id' => $enrollment_type_id,
            'year' => $year,
            'school_id' => $school_id,
            'grade_id' => $grade_id,
            'term_id' => 3
        );
        $fees = DB::table('school_feessetup')
            ->where($where)
            ->first();
        $fees = self::convertStdClassObjToArray($fees);
        if (!is_null($fees)) {
            $fees_amount = $fees;
        }
        return $fees_amount;
    }

    static function getTermTotalLearningDays($year, $term)
    {
        $default_no_days = Config('constants.term_num_days');
        $where = array(
            'term_id' => $term,
            'year_of_enrollment' => $year
        );
        $qry = DB::table('school_term_days')
            ->where($where);
        $num_days = $qry->value('no_of_days');
        if ($num_days == '' || $num_days == 0) {
            $num_days = $default_no_days;
        }
        return $num_days;
    }

    static function logUpdateBeneficiaryInfo($girl_id, $masterParams)
    {
        //previous image
        $prev_image = DB::table('beneficiary_information')
            ->where('id', $girl_id)
            ->first();
        $prev_image_array = convertStdClassObjToArray($prev_image);
        $prev_image_array['altered_by'] = \Auth::user()->id;
        $prev_image_array['altered_on'] = Carbon::now();
        $prev_image_array['girl_id'] = $prev_image_array['id'];
        unset($prev_image_array['id']);
        DB::table('beneficiary_information_logs')
            ->insert($prev_image_array);
        //update master for the sake of duplicates finding
        $master_id = $prev_image->master_id;
        DB::table('beneficiary_master_info')
            ->where('id', $master_id)
            ->update($masterParams);
    }

    static function logBeneficiaryGradeTransitioning($girl_id, $grade, $school_id, $user_id, $reason)
    {
        $year = date('Y');
        $where = array(
            'girl_id' => $girl_id,
            'year' => $year,
            'grade' => $grade,
            'school_id' => $school_id
        );
        $exists = DB::table('beneficiary_grade_logs')
            ->where($where)
            ->first();
        if (is_null($exists)) {
            $params = array(
                'girl_id' => $girl_id,
                'year' => $year,
                'grade' => $grade,
                'school_id' => $school_id,
                'created_at' => Carbon::now(),
                'created_by' => $user_id,
                'reason' => $reason
            );
            DB::table('beneficiary_grade_logs')
                ->insert($params);
        }
    }

    static function checkFeeChangesLimit($school_id, $term, $grade, $school_status, $current_fees, $batch_id, $year)
    {
        if ($term == 1) {
            return true;
        }
        $where_checker = array(
            'batch_id' => $batch_id,
            'grade_id' => $grade,
            'school_status_id' => $school_status
        );
        //get last term fees
        $prev_term = ($term - 1);
        $where = array(
            'school_id' => $school_id,
            'grade_id' => $grade,
            'term_id' => $prev_term,
            'year' => $year,
            'school_enrollment_id' => $school_status
        );
        $prev_term_fees = DB::table('school_feessetup')
            ->where($where)
            ->value('fees_amount');
        if ($prev_term_fees < 1) {
            return true;
        }
        //get allowed percentage increase
        $where2 = array(
            'grade_id' => $grade,
            'school_status_id' => $school_status
        );
        $allowed_percentage = DB::table('fees_increment_range')
            ->where($where2)
            ->value('allowed_percentage');
        if (!isset($allowed_percentage) || $allowed_percentage < 1) {
            return true;
        }
        $allowed_amount = (($allowed_percentage / 100) * $prev_term_fees);
        //get fee difference
        $fee_diff = ($current_fees - $prev_term_fees);
        if ($fee_diff > 0) {
            if ($fee_diff > $allowed_amount) {
                $error_text = 'Fees exceeds the allowed percentage of ' . $allowed_percentage . '% compared to previous term amount';
                $count = DB::table('fees_error_log')
                    ->where($where_checker)
                    ->count();
                if ($count > 0) {
                    $update_params = array(
                        'prev_term_amount' => $prev_term_fees,
                        'current_amount' => $current_fees,
                        'error_text' => $error_text
                    );
                    DB::table('fees_error_log')
                        ->where($where_checker)
                        ->update($update_params);
                } else {
                    $insert_params = array(
                        'batch_id' => $batch_id,
                        'grade_id' => $grade,
                        'prev_term_amount' => $prev_term_fees,
                        'current_amount' => $current_fees,
                        'school_status_id' => $school_status,
                        'error_text' => $error_text
                    );
                    DB::table('fees_error_log')
                        ->insert($insert_params);
                }
            } else {
                DB::table('fees_error_log')
                    ->where($where_checker)
                    ->delete();
                return true;
            }
        } else {
            DB::table('fees_error_log')
                ->where($where_checker)
                ->delete();
            return true;
        }
    }

    static function getSchoolBankDetails($school_bank_id, $school_id)
    {
        $results = (object)array(
            'bank_name' => '',
            'branch_name' => '',
            'account_no' => '',
            'sort_code' => ''
        );
        $qry = DB::table('school_bankinformation as t1')
            ->select(DB::raw('decrypt(t1.account_no) as account_no,t1.sort_code,t2.name as bank_name,t3.name as branch_name'))
            ->join('bank_details as t2', 't1.bank_id', '=', 't2.id')
            ->join('bank_branches as t3', 't1.branch_name', '=', 't3.id');
        if (is_numeric($school_bank_id) && $school_bank_id != '' && $school_bank_id != 0) {
            $qry->where('t1.id', $school_bank_id);
        } else {
            $qry->where('t1.school_id', $school_id)
                ->where('t1.is_activeaccount', 1);
        }
        $data = $qry->first();
        if (!is_null($data)) {
            $results = $data;
        }
        return $results;
    }

    static function getPaymentApproverRoles()
    {
        $qry = DB::table('user_roles')
            ->select('id')
            ->where('approve_payments', 1);
        $results = $qry->get();
        $results = self::convertStdClassObjToArray($results);
        $approverRoles = self::convertAssArrayToSimpleArray($results, 'id');
        return $approverRoles;
    }

    static function getAssignedComponents($user_id)
    {
        $user_groups = self::getUserGroups($user_id);
        //get keys
        $qry = DB::table('components_permissions as t1')
            ->join('system_components as t2', 't1.component_id', '=', 't2.id')
            ->select(DB::raw('t2.identifier as component_identifier,MAX(t1.accesslevel_id) as accessibility'))
            ->whereIn('t1.group_id', $user_groups)
            ->groupBy('t2.identifier');
        $results = $qry->get();
        $results = self::convertStdClassObjToArray($results);
        $keys = self::convertAssArrayToSimpleArray($results, 'component_identifier');
        //get values
        $qry = DB::table('components_permissions as t1')
            ->join('system_components as t2', 't1.component_id', '=', 't2.id')
            ->select(DB::raw('t2.identifier as component_identifier,MAX(t1.accesslevel_id) as accessibility'))
            ->whereIn('t1.group_id', $user_groups)
            ->groupBy('t2.identifier');
        $results = $qry->get();
        $results = self::convertStdClassObjToArray($results);
        $values = self::convertAssArrayToSimpleArray($results, 'accessibility');
        $combined = array_combine($keys, $values);
        return $combined;
    }

    static function getRecordInitialStatus($process_id)
    {
        $statusDetails = (object)array(
            'status_id' => 0,
            'name' => ''
        );
        $results = DB::table('process_statuses as t1')
            ->join('system_statuses as t2', 't1.status_id', '=', 't2.id')
            ->select('t1.status_id', 't2.name')
            ->where('t1.process_id', $process_id)
            ->where('t1.is_initial', 1)
            ->first();

        if (!is_null($results)) {
            $statusDetails = $results;
        }
        return $statusDetails;
    }

    static function getMenuItemWorkflowStages($menu_id)
    {
        $stagesSimpleArr = array();
        if (validateisNumeric($menu_id)) {
            $qry = DB::table('wf_workflow_stages as t1')
                ->where('menu_id', $menu_id)
                ->select('t1.id');
            $stagesRs = $qry->get();
            $stagesAssArr = convertStdClassObjToArray($stagesRs);
            $stagesSimpleArr = convertAssArrayToSimpleArray($stagesAssArr, 'id');
        }
        return $stagesSimpleArr;
    }

    static function getRecordTransitionStatus($prev_stage, $action, $next_stage, $static_status)
    {
        if (isset($static_status) && $static_status != '') {
            return $static_status;
        }
        $where = array(
            'stage_id' => $prev_stage,
            'action_id' => $action,
            'nextstage_id' => $next_stage
        );
        $status = DB::table('wf_workflow_transitions')
            ->where($where)
            ->value('record_status_id');
        return $status;
    }

    static function getTableData($table_name, $where)
    {
        $qry = DB::table($table_name)
            ->where($where);
        $results = $qry->first();
        return $results;
    }

    static function getEmailTemplateInfo($template_id, $vars)
    {
        $template_info = DB::table('email_messages_templates')
            ->where('id', $template_id)
            ->first();
        if (is_null($template_info)) {
            $template_info = (object)array(
                'subject' => 'Error',
                'body' => 'Sorry this email was delivered wrongly, kindly ignore...'
            );
        }
        $template_info->subject = strtr($template_info->subject, $vars);
        $template_info->body = strtr($template_info->body, $vars);
        return $template_info;
    }

    static function getBeneficiarySchMatchingPlacementDetails($qry, $sub_category, $benTableAlias)
    {
        if ($sub_category == 5) {//school matching
            $qry->join('school_matching_details', $benTableAlias . '.id', '=', 'school_matching_details.girl_id');
        } else if ($sub_category == 8) {//school placement
            $qry->join('school_placement_details', $benTableAlias . '.id', '=', 'school_placement_details.girl_id');
        }
        return $qry;
    }

}
