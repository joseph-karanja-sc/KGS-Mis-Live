<?php

namespace App\Modules\BenBatchManagement\Http\Controllers;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BenBatchManagementController extends BaseController
{

    public function saveBenBatchCommonData(Request $req)
    {
        $user_id = $this->user_id;
        $post_data = $req->all();
        $table_name = $post_data['table_name'];
        $id = $post_data['id'];
        $skip = $post_data['skip'];
        $skipArray = explode(",", $skip);
        //unset unnecessary values
        unset($post_data['_token']);
        unset($post_data['table_name']);
        unset($post_data['model']);
        unset($post_data['id']);
        unset($post_data['skip']);
        $table_data = $post_data;//encryptArray($post_data, $skipArray);
        //add extra params
        $table_data['created_at'] = Carbon::now();
        $table_data['created_by'] = $user_id;
        $where = array(
            'id' => $id
        );
        if (isset($id) && $id != "") {
            if (recordExists($table_name, $where)) {
                unset($table_data['created_at']);
                unset($table_data['created_by']);
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $previous_data = getPreviousRecords($table_name, $where);
                $success = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                if ($success) {
                    $res = array(
                        'success' => true,
                        'message' => 'Data updated Successfully!!'
                    );
                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'Problem encountered while updating data. Try again later!!'
                    );
                }
            }
        } else {
            $success = insertRecord($table_name, $table_data, $user_id);
            if ($success) {
                $res = array(
                    'success' => true,
                    'message' => 'Data Saved Successfully!!'
                );
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'Problem encountered while saving data. Try again later!!'
                );
            }
        }
        return response()->json($res);
    }

    public function getBenBatchParam($model_name)
    {
        $model = 'App\\Modules\\benbatchmanagement\\Entities\\' . $model_name;
        $results = $model::all()->toArray();
        $results = decryptArray($results);
        $res = array(
            'results' => $results
        );
        return response()->json($res);
    }

    public function deleteBenBatchRecord(Request $req)
    {
        $record_id = $req->id;
        $table_name = $req->table_name;
        $user_id = \Auth::user()->id;
        $where = array(
            'id' => $record_id
        );
        try {
            $previous_data = getPreviousRecords($table_name, $where);
            $res = deleteRecord($table_name, $previous_data, $where, $user_id);
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getBeneficiaries(Request $req)
    {
        try {
            $start = $req->input('start');
            $limit = $req->input('limit');
            $school_prov = $req->input('school_prov');
            $home_prov = $req->input('home_prov');
            $girl_id = $req->input('girl_id');
            $kgs_status = $req->input('kgs_status');
            $school_status = $req->input('school_status');
            $takeup_status = $req->input('takeup_status');
            $grades = $req->input('grades');
            $unresponsive = $req->input('unresponsive');
            $beneficiary_status = 4;
            $recommendation = 1;
            $filter = $req->input('filter');
            $gradesIn = array();
            if (isset($grades) && $grades != '') {
                $gradesIn = json_decode($grades);
            }
            $qry = DB::table('beneficiary_information as t1')
                ->join('beneficiary_statuses', 't1.beneficiary_status', '=', 'beneficiary_statuses.id')
                ->leftJoin('beneficiary_school_statuses', 't1.beneficiary_school_status', '=', 'beneficiary_school_statuses.id')
                ->leftJoin('beneficiary_enrollement_statuses', 't1.enrollment_status', '=', 'beneficiary_enrollement_statuses.id')
                ->leftJoin('beneficiary_images', 't1.id', '=', 'beneficiary_images.girl_id')
                ->leftJoin('households', 't1.household_id', '=', 'households.id')
                ->leftJoin('school_information', 't1.school_id', '=', 'school_information.id')
                ->leftJoin('districts as d1', 'school_information.district_id', '=', 'd1.id')
                ->join('districts', 't1.district_id', '=', 'districts.id')
                ->leftJoin('cwac', 't1.cwac_id', '=', 'cwac.id')
                ->join('batch_info', 't1.batch_id', '=', 'batch_info.id')
                ->leftJoin('school_enrollment_statuses as t12', 't1.school_enrollment_status', '=', 't12.id')
                // ->join('beneficiary_school_statuses as t13', 't1.beneficiary_school_status', '=', 't13.id')
                ->leftJoin('beneficiary_school_statuses as t13', 't1.beneficiary_school_status', '=', 't13.id')
                ->join('beneficiary_categories as t14', 't1.category', '=', 't14.id')
                ->leftJoin('school_types as t15', 'school_information.school_type_id', '=', 't15.id')
                ->select(DB::raw("t1.id,t1.id as girl_id,t1.beneficiary_id,t1.household_id,t1.exam_school_id,t1.school_id,t1.cwac_id,t1.acc_id,t1.ward_id,t1.constituency_id,t1.district_id,t1.province_id,
                              CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.last_name)) as beneficiary_name,decrypt(t1.first_name) as first_name, decrypt(t1.last_name) as last_name,t1.dob,t1.verified_dob,t1.highest_grade,t1.exam_grade,t1.current_school_grade,t1.category,t1.master_id,
                              t1.beneficiary_status,t1.enrollment_status,t1.beneficiary_school_status,t1.exam_number,t1.enrollment_date,t1.batch_id,t1.folder_id,t1.letter_printed as letterPrinted,t1.relation_to_hhh,t1.cwac_txt,
                              batch_info.batch_no,d1.name as school_district,cwac.name as cwac_name,beneficiary_school_statuses.name as ben_school_status,beneficiary_enrollement_statuses.name as enrollment_status_name,
                              beneficiary_images.saved_name,beneficiary_statuses.name as status_name,school_information.name as school,households.id as hh_id,CONCAT_WS(' ',households.hhh_fname,households.hhh_lname) as hhh_name,households.hhh_fname,households.hhh_lname,households.hhh_nrc_number,
                              households.number_in_cwac,districts.name as district_name,t1.kgs_takeup_status,t1.school_enrollment_status,
                              t12.name as schenrollment_status,t13.name as sch_status,t14.name as category_name,t15.name as school_type,school_information.code as sch_code"))
                ->where('t1.beneficiary_status', $beneficiary_status)
                ->where('t1.verification_recommendation', $recommendation);
            if (isset($unresponsive) && $unresponsive != '') {
                if ($unresponsive == 1) {
                    $qry->join('unresponsive_cohorts', 'unresponsive_cohorts.girl_id', '=', 't1.id')
                        ->where('unresponsive_cohorts.matched', '<>', 1);
                }
            }
            if (count($gradesIn) > 0 && $gradesIn != '') {
                $qry->whereIn('current_school_grade', $gradesIn);
            }
            if (isset($school_prov) && $school_prov != '') {
                $qry->where('school_information.province_id', $school_prov);
            }
            if (isset($home_prov) && $home_prov != '') {
                $qry->where('t1.province_id', $home_prov);
            }
            if (isset($kgs_status) && $kgs_status != '') {
                $qry->where('t1.enrollment_status', $kgs_status);
            }
            if (isset($school_status) && $school_status != '') {
                $qry->where('t1.school_enrollment_status', $school_status);
            }
            if (isset($takeup_status) && $takeup_status != '') {
                $qry->where('t1.kgs_takeup_status', $takeup_status);
            }
            if (validateisNumeric($girl_id)) {
                $qry->where('t1.id', $girl_id);
            }
            if (isset($filter)) {
                $filters = json_decode($filter);
                $filter_string = $this->buildBeneficiarySearchQuery($filters);
                if ($filter_string != '') {
                    $qry->whereRAW($filter_string);
                }
            }
            $total = $qry->count();
            // if (validateisNumeric($limit)) {
            //     $qry->offset($start)
            //         ->limit($limit);
            // }
            //$qry->offset($start)->limit($limit);
            $data = $qry->get();
            $res = array(
                'success' => true,
                'message' => 'Records fetched successfully!!',
                'total' => $total,
                'results' => $data
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage(),
                'results' => ''
            );
        }
        return response()->json($res);
    }

    public function buildBeneficiarySearchQuery($filters)
    {
        $return_string = '';
        $whereClauses = array();
        if ($filters != NULL) {
            foreach ($filters as $filter) {
                switch ($filter->property) {
                    case 'beneficiary_id' :
                        $whereClauses[] = "t1.beneficiary_id like '%" . ($filter->value) . "%'";
                        break;
                    case 'beneficiary_name' :
                        $whereClauses[] = "decrypt(t1.first_name) like '%" . ($filter->value) . "%' OR decrypt(t1.last_name) like '%" . ($filter->value) . "%'";
                        break;
                    case 'first_name' :
                        $whereClauses[] = "decrypt(t1.first_name) like '%" . ($filter->value) . "%'";
                        break;
                    case 'last_name' :
                        $whereClauses[] = "decrypt(t1.last_name) like '%" . ($filter->value) . "%'";
                        break;
                    case 'school' :
                        $whereClauses[] = "t1.school_id = '" . ($filter->value) . "'";
                        break;
                    case 'school_district' :
                        $whereClauses[] = "school_information.district_id = '" . ($filter->value) . "'";
                        break;
                    /*case 'district_name' :
                        $whereClauses[] = "t1.district_id = '" . ($filter->value) . "'";
                        break;*/
                    case 'district_name' :
                        $whereClauses[] = "districts.name like '%" . ($filter->value) . "%'";
                        break;
                    case 'cwac_name' :
                        $whereClauses[] = "t1.cwac_id = '" . ($filter->value) . "'";
                        break;
                    case 'cwac_txt' :
                        $whereClauses[] = "t1.cwac_txt like '%" . ($filter->value) . "%'";
                        break;
                    case 'hhh_name' :
                        $whereClauses[] = "households.hhh_fname like '%" . ($filter->value) . "%' OR households.hhh_lname like '%" . ($filter->value) . "%'";
                        break;
                    case 'hhh_fname' :
                        $whereClauses[] = "households.hhh_fname like '%" . ($filter->value) . "%'";
                        break;
                    case 'hhh_lname' :
                        $whereClauses[] = "households.hhh_lname like '%" . ($filter->value) . "%'";
                        break;
                    case 'hhh_nrc_number' :
                        $whereClauses[] = "households.hhh_nrc_number like '%" . ($filter->value) . "%'";
                        break;
                    case 'batch_no' :
                        $whereClauses[] = "t1.batch_id = '" . ($filter->value) . "'";
                        break;
                    case 'enrollment_status_name' :
                        $whereClauses[] = "t1.enrollment_status = '" . ($filter->value) . "'";
                        break;
                }
            }
            $whereClauses = array_filter($whereClauses);
        }
        if (!empty($whereClauses)) {
            $return_string = implode(' AND ', $whereClauses);
        }
        return $return_string;
    }

    public function getEnrolledBeneficiaries(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $beneficiary_status = $req->input('beneficiary_status');
            $category_id = $req->input('category_id');
            $cwac_id = $req->input('cwac_id');
            $district_id = $req->input('district_id');
            $school_id = $req->input('school_id');
            $whereCat = array($category_id);
            if ($category_id == 2) {
                $whereCat = array(2, 3);
            }
            $qry = DB::table('beneficiary_information as t1')
                ->leftJoin('households', 't1.household_id', '=', 'households.id')
                ->join('districts as d1', 't1.district_id', '=', 'd1.id')
                ->select(DB::raw('t1.id,t1.beneficiary_id,t1.current_school_grade,households.hhh_fname,households.hhh_lname,d1.name as district_name,
                                  t1.dob,decrypt(t1.first_name) as first_name,decrypt(t1.last_name) as last_name'))
                ->where('beneficiary_status', $beneficiary_status)
                ->whereIn('category', $whereCat)
                ->where('batch_id', $batch_id);
            if (isset($district_id) && $district_id != '') {
                $qry->where('t1.district_id', $district_id);
            }
            if (isset($cwac_id) && $cwac_id != '') {
                $qry->where('t1.cwac_id', $cwac_id);
            }
            if (isset($school_id) && $school_id != '') {
                $qry->where('t1.school_id', $school_id);
            }
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getBeneficiariesSuspensionDetails(Request $req)
    {
        try {
            $province_id = $req->input('province_id');
            $district_id = $req->input('district_id');
            $cwac_id = $req->input('cwac_id');
            $school_id = $req->input('school_id');
            $category_id = $req->input('category_id');
            $batch_id = $req->input('batch_id');
            $enrollment_status = $req->input('enrollment_status');
            $grades = $req->input('grades');
            $stage = $req->input('stage');
            $approval_status = $req->input('approval_status');
            $gradesIn = json_decode($grades);
            $beneficiary_status = 4;
            $recommendation = 1;
            if (isset($stage) && $stage != '') {
                $stage = $stage;
            } else {
                $stage = 1;
            }
            $count = '';
            if ($stage == 1) {
                $count = 'COUNT(t4.id) as suspension_count,';
            }
            $qry = DB::table('suspension_requests')
                ->join('beneficiary_information as t1', 't1.id', '=', 'suspension_requests.girl_id')
                ->leftJoin('beneficiary_enrollement_statuses as q', 't1.enrollment_status', '=', 'q.id')
                ->leftJoin('households', 't1.household_id', '=', 'households.id')
                ->leftJoin('school_information', 't1.school_id', '=', 'school_information.id')
                ->join('districts', 't1.district_id', '=', 'districts.id')
                ->join('districts as d2', 'school_information.district_id', '=', 'd2.id')
                ->leftJoin('users as t2', 'suspension_requests.request_by', '=', 't2.id')
                ->leftJoin('users as t3', 'suspension_requests.approval_by', '=', 't3.id')
                ->leftJoin('suspension_reasons', 'suspension_requests.reason_id', '=', 'suspension_reasons.id')
                ->leftJoin('beneficiaries_transitional_report as t4', function ($join) {
                    $join->on('t1.id', '=', 't4.girl_id')
                        ->on('t4.to_stage', '=', DB::raw(2));
                })
                ->select(DB::raw($count . "suspension_requests.id,suspension_requests.girl_id,t1.beneficiary_id, CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.last_name)) as beneficiary_name,t1.dob,t1.current_school_grade,t1.enrollment_status,t1.beneficiary_school_status,
                              decrypt(t2.first_name) as requester_fname, decrypt(t2.last_name) requester_lname, decrypt(t3.first_name) as approver_fname, decrypt(t3.last_name) as approver_lname,
                              q.name as enrol_status_name,suspension_reasons.name as system_reason,suspension_requests.user_reason,suspension_requests.approval_remark,suspension_requests.request_date,suspension_requests.approval_date,suspension_requests.approval_status,
                              households.hhh_fname,households.hhh_lname,households.hhh_nrc_number,households.number_in_cwac,districts.name as district_name,school_information.name as school,d2.name as sch_district"))
                ->where('t1.beneficiary_status', $beneficiary_status)
                ->where('t1.verification_recommendation', $recommendation)
                ->where('suspension_requests.stage', $stage);
            if (is_array($gradesIn)) {
                if (count($gradesIn) > 0 && $gradesIn != '') {
                    $qry->whereIn('current_school_grade', $gradesIn);
                }
            }
            if (isset($province_id) && $province_id != '') {
                $qry->where('t1.province_id', $province_id);
            }
            if (isset($district_id) && $district_id != '') {
                $qry->where('t1.district_id', $district_id);
            }
            if (isset($cwac_id) && $cwac_id != '') {
                $qry->where('t1.cwac_id', $cwac_id);
            }
            if (isset($school_id) && $school_id != '') {
                $qry->where('t1.school_id', $school_id);
            }
            if (isset($category_id) && $category_id != '') {
                $qry->where('t1.category', $category_id);
            }
            if (isset($batch_id) && $batch_id != '') {
                $qry->where('t1.batch_id', $batch_id);
            }
            if (isset($enrollment_status) && $enrollment_status != '') {
                $qry->where('t1.enrollment_status', $enrollment_status);
            }
            if (isset($approval_status) && $approval_status != '') {
                $qry->where('suspension_requests.approval_status', $approval_status);
            }
            if ($stage == 1) {
                $qry->groupBy('suspension_requests.girl_id');
            }
            $data = $qry->get();
            $res = array(
                'success' => true,
                'message' => 'Records fetched successfully!!',
                'results' => $data
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage(),
                'results' => ''
            );
        }
        return response()->json($res);
    }

    // public function getBeneficiariesForPromotion(Request $req)
    // {
    //     try {
    //         $province_id = $req->input('province_id');
    //         $district_id = $req->input('district_id');
    //         $cwac_id = $req->input('cwac_id');
    //         $school_id = $req->input('school_id');
    //         $category_id = $req->input('category_id');
    //         $batch_id = $req->input('batch_id');
    //         $enrollment_status = $req->input('enrollment_status');
    //         $filter = $req->input('filter');
    //         $where = array(
    //             'beneficiary_status' => 4
    //         );
    //         $qry = DB::table('grade_nines_for_promotion as t0')
    //             ->join('beneficiary_information as t1', 't0.girl_id', '=', 't1.id')
    //             ->leftJoin('beneficiary_enrollement_statuses', 't1.enrollment_status', '=', 'beneficiary_enrollement_statuses.id')
    //             ->leftJoin('households', 't1.household_id', '=', 'households.id')
    //             ->leftJoin('school_information', 't1.school_id', '=', 'school_information.id')
    //             ->leftJoin('districts', 'school_information.district_id', '=', 'districts.id')
    //             //->leftJoin('gradenine_promotions as gp', 't0.girl_id', '=', 'gp.girl_id')
    //             ->leftJoin('gradenine_promotions as gp', function ($join) {
    //                 $join->on('t0.girl_id', '=', 'gp.girl_id')
    //                     ->on('t0.promotion_year', '=', 'gp.promotion_year');
    //             })
    //             ->select(DB::raw('decrypt(t1.first_name) as first_name, decrypt(t1.last_name) as last_name,t1.grade9_exam_no,
    //                           t1.id,t1.gradenine_promotion_stage,t1.gradenine_promotion_status,t1.school_id,t1.beneficiary_id,t1.dob,
    //                           t1.current_school_grade, school_information.name as school, households.id as hh_id,
    //                           households.hhh_fname, households.hhh_lname, households.hhh_nrc_number, districts.name as district_name,
    //                           t0.id as girl_promotion_id,t0.promotion_year,t1.cwac_txt,t1.district_txt,gp.id as details_captured'))
    //             ->where($where)
    //             ->whereIn('current_school_grade', [7, 9])
    //             ->where('t0.stage', 1)
    //             ->whereNotIn('enrollment_status', array(2, 4));

    //         if (isset($province_id) && $province_id != '') {
    //             $qry->where('school_information.province_id', $province_id);
    //         }
    //         if (isset($district_id) && $district_id != '') {
    //             $qry->where('school_information.district_id', $district_id);
    //         }
    //         if (isset($cwac_id) && $cwac_id != '') {
    //             $qry->where('beneficiary_information.cwac_id', $cwac_id);
    //         }
    //         if (isset($school_id) && $school_id != '') {
    //             $qry->where('t1.school_id', $school_id);
    //         }
    //         if (isset($category_id) && $category_id != '') {
    //             $qry->where('t1.category', $category_id);
    //         }
    //         if (isset($batch_id) && $batch_id != '') {
    //             $qry->where('t1.batch_id', $batch_id);
    //         }
    //         if (isset($enrollment_status) && $enrollment_status != '') {
    //             $qry->where('t1.enrollment_status', $enrollment_status);
    //         }
    //         if (isset($filter) && $filter != '') {
    //             if ($filter == 1) {
    //                 $qry->whereNotNull('gp.id');
    //             } else if ($filter == 2) {
    //                 $qry->whereNull('gp.id');
    //             }
    //         }
    //         $data = $qry->get();
    //         $res = array(
    //             'success' => true,
    //             'message' => returnMessage($data),
    //             'results' => $data
    //         );
    //     } catch (\Exception $exception) {
    //         $res = array(
    //             'success' => false,
    //             'message' => $exception->getMessage()
    //         );
    //     } catch (\Throwable $throwable) {
    //         $res = array(
    //             'success' => false,
    //             'message' => $throwable->getMessage()
    //         );
    //     }
    //     return response()->json($res);
    // }

    public function getBeneficiariesForPromotion(Request $req)
    {
        try {
            // Input parameters
            $province_id = $req->input('province_id');
            $district_id = $req->input('district_id');
            $cwac_id = $req->input('cwac_id');
            $school_id = $req->input('school_id');
            $category_id = $req->input('category_id');
            $batch_id = $req->input('batch_id');
            $enrollment_status = $req->input('enrollment_status');
            $filter = $req->input('filter');
            $start = $req->input('start', 0);
            $limit = $req->input('limit', 500); // Default to 500 per page

            // Build query with optimizations
            $qry = DB::table('grade_nines_for_promotion as t0')
                ->join('beneficiary_information as t1', 't0.girl_id', '=', 't1.id')
                ->join('school_information', 't1.school_id', '=', 'school_information.id')
                ->select('t1.id','t1.beneficiary_id','t1.first_name','t1.last_name',
                    't1.grade9_exam_no','t1.gradenine_promotion_stage',
                    't1.gradenine_promotion_status','t1.school_id','t1.dob',
                    't1.current_school_grade','t1.cwac_txt','t1.district_txt',
                    'school_information.name as school','t0.id as girl_promotion_id',
                    't0.promotion_year')
                ->where('t1.beneficiary_status', 4)
                ->whereIn('t1.current_school_grade', [7, 9])
                ->where('t0.stage', 1)
                ->whereNotIn('t1.enrollment_status', [2, 4]);
            // Add optional filters
            if (isset($province_id) && $province_id != '') {
                $qry->where('school_information.province_id', $province_id);
            }
            if (isset($district_id) && $district_id != '') {
                $qry->where('school_information.district_id', $district_id);
            }
            if (isset($cwac_id) && $cwac_id != '') {
                $qry->where('t1.cwac_id', $cwac_id);
            }
            if (isset($school_id) && $school_id != '') {
                $qry->where('t1.school_id', $school_id);
            }
            if (isset($category_id) && $category_id != '') {
                $qry->where('t1.category', $category_id);
            }
            if (isset($batch_id) && $batch_id != '') {
                $qry->where('t1.batch_id', $batch_id);
            }
            if (isset($enrollment_status) && $enrollment_status != '') {
                $qry->where('t1.enrollment_status', $enrollment_status);
            }
            // Handle filter for promotion details
            if (isset($filter) && $filter != '') {
                if ($filter == 1) {
                    // Details captured
                    $qry->whereExists(function ($subQuery) {
                        $subQuery->select(DB::raw(1))
                            ->from('gradenine_promotions as gp')
                            ->whereRaw('gp.girl_id = t1.id')
                            ->whereRaw('gp.promotion_year = t0.promotion_year');
                    });
                } else if ($filter == 2) {
                    // Details not captured
                    $qry->whereNotExists(function ($subQuery) {
                        $subQuery->select(DB::raw(1))
                            ->from('gradenine_promotions as gp')
                            ->whereRaw('gp.girl_id = t1.id')
                            ->whereRaw('gp.promotion_year = t0.promotion_year');
                    });
                }
            } else {
                // Default: include details_captured info
                $qry->addSelect(
                    DB::raw('EXISTS(SELECT 1 FROM gradenine_promotions gp WHERE gp.girl_id = t1.id AND gp.promotion_year = t0.promotion_year) as details_captured')
                );
            }
            $total = $qry->count();
            if (is_numeric($limit) && $limit > 0) {
                $qry->offset($start)->limit($limit);
            }
            $init_data = $qry->get();
            // Decrypt names in application layer (faster than DB)
            $data = collect($init_data)->map(function ($item) {
                try {
                    if (!empty($item->first_name)) {
                        $item->first_name = aes_decrypt($item->first_name);
                    }
                } catch (\Exception $e) {
                    // If decryption fails, keep original value
                    $item->first_name = $item->first_name ?? '';
                }
                
                try {
                    if (!empty($item->last_name)) {
                        $item->last_name = aes_decrypt($item->last_name);
                    }
                } catch (\Exception $e) {
                    // If decryption fails, keep original value
                    $item->last_name = $item->last_name ?? '';
                }
                
                return $item;
            })->toArray();
            $res = array(
                'success' => true,
                'message' => returnMessage($data),
                'results' => $data,
                'total' => $total,
                'page' => intval($start / $limit) + 1,
                'pageSize' => intval($limit)
            );
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
        return response()->json($res);
    }


    public function getBeneficiariesForPromotionApprovals(Request $req)
    {
        try {
            $province_id = $req->input('province_id');
            $district_id = $req->input('district_id');
            $cwac_id = $req->input('cwac_id');
            $school_id = $req->input('school_id');
            $category_id = $req->input('category_id');
            $batch_id = $req->input('batch_id');
            $enrollment_status = $req->input('enrollment_status');
            $filter = $req->input('filter');

            $where = array(
                'beneficiary_status' => 4,
                'current_school_grade' => 9
            );
            $qry = DB::table('grade_nines_for_promotion as t0')
                //->join('gradenine_promotions', 't0.girl_id', '=', 'gradenine_promotions.girl_id')
                ->leftJoin('gradenine_promotions', function ($join) {
                    $join->on('t0.girl_id', '=', 'gradenine_promotions.girl_id')
                        ->on('t0.promotion_year', '=', 'gradenine_promotions.promotion_year');
                })
                ->join('beneficiary_information as t1', 't1.id', '=', 'gradenine_promotions.girl_id')
                //->join(DB::raw("(select max(id) as maxid from gradenine_promotions kip group by kip.girl_id) as b"), 'b.maxid', '=', 'gradenine_promotions.id')
                ->leftJoin('users', 'gradenine_promotions.created_by', '=', 'users.id')
                ->leftJoin('school_information', 't1.school_id', '=', 'school_information.id')
                ->leftJoin('districts', 't1.district_id', '=', 'districts.id')
                ->leftJoin('school_information as s2', 'gradenine_promotions.school_id', '=', 's2.id')
                ->leftJoin('post_exam_eligibility as p1', 'gradenine_promotions.qualified', '=', 'p1.id')
                ->select(DB::raw('decrypt(t1.first_name) as first_name, decrypt(t1.last_name) as last_name,
                              decrypt(users.first_name) as author_fname, decrypt(users.last_name) as author_lname,
                              t1.enrollment_status,gradenine_promotions.id,t1.school_id as exam_school_id, gradenine_promotions.school_id,gradenine_promotions.girl_id,
                              gradenine_promotions.qualified,gradenine_promotions.beneficiary_school_status,gradenine_promotions.to_grade as grade,
                              s2.province_id,s2.district_id,s2.code as emis_code,s2.name as assigned_school,t1.beneficiary_id,t1.dob,t1.current_school_grade as exam_grade,
                              school_information.name as school, districts.name as district_name,t1.district_txt,t1.cwac_txt,
                              p1.name as post_exam_eligibility,t1.grade9_exam_no as exam_no,t0.id as girl_promotion_id,t0.promotion_year'))
                ->where($where)
                ->where('t0.stage', 2)
                ->whereNotIn('enrollment_status', array(2, 4));

            if (isset($province_id) && $province_id != '') {
                $qry->where('school_information.province_id', $province_id);
            }
            if (isset($district_id) && $district_id != '') {
                $qry->where('school_information.district_id', $district_id);
            }
            if (isset($cwac_id) && $cwac_id != '') {
                $qry->where('beneficiary_information.cwac_id', $cwac_id);
            }
            if (isset($school_id) && $school_id != '') {
                $qry->where('t1.school_id', $school_id);
            }
            if (isset($category_id) && $category_id != '') {
                $qry->where('t1.category', $category_id);
            }
            if (isset($batch_id) && $batch_id != '') {
                $qry->where('t1.batch_id', $batch_id);
            }
            if (isset($enrollment_status) && $enrollment_status != '') {
                $qry->where('t1.enrollment_status', $enrollment_status);
            }
            if (isset($filter) && $filter != '') {
                if ($filter == 1) {
                    $qry->where('gradenine_promotions.qualified', 1);
                } else if ($filter == 2) {
                    $qry->where('gradenine_promotions.qualified', 0);
                }
            }
            $data = $qry->get();
            $res = array(
                'success' => true,
                'message' => returnMessage($data),
                'results' => $data
            );
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
        return response()->json($res);
    }

    public function getGradeNinesPromotionLogs(Request $req)
    {
        $province_id = $req->input('province_id');
        $district_id = $req->input('district_id');
        $cwac_id = $req->input('cwac_id');
        $school_id = $req->input('school_id');
        $category_id = $req->input('category_id');
        $batch_id = $req->input('batch_id');
        $enrollment_status = $req->input('enrollment_status');
        $filter = $req->input('filter');

        $qry = DB::table('grade_nines_for_promotion as t0')
            //->join('gradenine_promotions', 't0.girl_id', '=', 'gradenine_promotions.girl_id')
            ->join('gradenine_promotions', function ($join) {
                $join->on('t0.girl_id', '=', 'gradenine_promotions.girl_id')
                    ->on('t0.promotion_year', '=', 'gradenine_promotions.promotion_year');
            })
            ->join('beneficiary_information as t1', 't1.id', '=', 'gradenine_promotions.girl_id')
            ->leftJoin('users', 'gradenine_promotions.created_by', '=', 'users.id')
            ->leftJoin('school_information', 't1.school_id', '=', 'school_information.id')
            ->leftJoin('districts', 't1.district_id', '=', 'districts.id')
            ->leftJoin('school_information as s2', 'gradenine_promotions.school_id', '=', 's2.id')
            ->join('gradenine_promotions_prev as g1', 'gradenine_promotions.prev_record_id', '=', 'g1.id')
            ->leftJoin('post_exam_eligibility as p1', 'gradenine_promotions.qualified', '=', 'p1.id')
            ->select(DB::raw('decrypt(t1.first_name) as first_name, decrypt(t1.last_name) as last_name,
                              decrypt(users.first_name) as author_fname, decrypt(users.last_name) as author_lname,
                              gradenine_promotions.promotion_year as year,t1.enrollment_status,gradenine_promotions.id,t1.school_id as exam_school_id,
                              gradenine_promotions.school_id,gradenine_promotions.girl_id,gradenine_promotions.qualified,
                              gradenine_promotions.beneficiary_school_status,gradenine_promotions.to_grade as grade,s2.province_id,s2.district_id,s2.code as emis_code,
                              s2.name as assigned_school,t1.cwac_txt,t1.beneficiary_id,t1.dob, g1.current_school_grade as exam_grade,
                              p1.name as post_exam_eligibility,t1.grade9_exam_no as exam_no,t0.id as girl_promotion_id,t0.promotion_year,school_information.name as school, districts.name as district_name'))
            ->where('t0.stage', 3)
            ->whereIn('t0.status', array(1, 2));

        if (isset($province_id) && $province_id != '') {
            $qry->where('t1.province_id', $province_id);
        }
        if (isset($district_id) && $district_id != '') {
            $qry->where('t1.district_id', $district_id);
        }
        if (isset($cwac_id) && $cwac_id != '') {
            $qry->where('beneficiary_information.cwac_id', $cwac_id);
        }
        if (isset($school_id) && $school_id != '') {
            $qry->where('t1.school_id', $school_id);
        }
        if (isset($category_id) && $category_id != '') {
            $qry->where('t1.category', $category_id);
        }
        if (isset($batch_id) && $batch_id != '') {
            $qry->where('t1.batch_id', $batch_id);
        }
        if (isset($enrollment_status) && $enrollment_status != '') {
            $qry->where('t1.enrollment_status', $enrollment_status);
        }
        if (isset($filter) && $filter != '') {
            if ($filter == 1) {
                $qry->where('gradenine_promotions.qualified', 1);
            } else if ($filter == 2) {
                $qry->where('gradenine_promotions.qualified', 0);
            }
        }
        try {
            $data = $qry->get();
            $res = array(
                'success' => true,
                'message' => 'Records fetched successfully!!',
                'results' => $data
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage(),
                'results' => ''
            );
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage(),
                'results' => ''
            );
        }
        return response()->json($res);
    }

    public function getRevokedGradeNinePromotions(Request $req)
    {
        $province_id = $req->input('province_id');
        $district_id = $req->input('district_id');
        $cwac_id = $req->input('cwac_id');
        $school_id = $req->input('school_id');
        $category_id = $req->input('category_id');
        $batch_id = $req->input('batch_id');
        $enrollment_status = $req->input('enrollment_status');
        $filter = $req->input('filter');

        $qry = DB::table('revoked_gradenine_promotions as t00 as t0')
            ->join('grade_nines_for_promotion as t0', 't00.promotion_id', '=', 't0.id')
            ->join('beneficiary_information as t1', 't0.girl_id', '=', 't1.id')
            ->join('school_information', 't1.school_id', '=', 'school_information.id')
            ->join('districts', 'school_information.district_id', '=', 'districts.id')
            ->join('users as t4', 't00.action_by', '=', 't4.id')
            ->select(DB::raw("decrypt(t1.first_name) as first_name, decrypt(t1.last_name) as last_name,t1.grade9_exam_no,
                              t1.id,t1.gradenine_promotion_stage,t1.gradenine_promotion_status,t1.school_id,t1.beneficiary_id,t1.dob,
                              t1.current_school_grade, school_information.name as school,CONCAT_WS(' ',decrypt(t4.first_name),decrypt(t4.last_name)) as author,
                              districts.name as district_name,t00.assigned_grade,t00.created_on as revoked_on,t00.revoke_reason,
                              t0.id as girl_promotion_id,t0.promotion_year,t1.cwac_txt,t1.district_txt"))
            ->where('t0.stage', 3)
            ->where('t0.status', 3);

        if (isset($province_id) && $province_id != '') {
            $qry->where('school_information.province_id', $province_id);
        }
        if (isset($district_id) && $district_id != '') {
            $qry->where('school_information.district_id', $district_id);
        }
        if (isset($cwac_id) && $cwac_id != '') {
            $qry->where('beneficiary_information.cwac_id', $cwac_id);
        }
        if (isset($school_id) && $school_id != '') {
            $qry->where('t1.school_id', $school_id);
        }
        if (isset($category_id) && $category_id != '') {
            $qry->where('t1.category', $category_id);
        }
        if (isset($batch_id) && $batch_id != '') {
            $qry->where('t1.batch_id', $batch_id);
        }
        if (isset($enrollment_status) && $enrollment_status != '') {
            $qry->where('t1.enrollment_status', $enrollment_status);
        }
        if (isset($filter) && $filter != '') {
            if ($filter == 1) {
                $qry->whereNotNull('gp.id');
            } else if ($filter == 2) {
                $qry->whereNull('gp.id');
            }
        }
        try {
            $data = $qry->get();
            $res = array(
                'success' => true,
                'message' => 'Records fetched successfully!!',
                'results' => $data
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage(),
                'results' => ''
            );
        }
        return response()->json($res);
    }

    public function getBeneficiariesForSchTransfer(Request $req)
    {
        try {
            $enrollment_status = array(1, 3, 5);
            $district_id = $req->input('district_id');
            $school_id = $req->input('school_id');
            $filter = $req->input('filter');
            $start = $req->input('start');
            $limit = $req->input('limit');
            $whereClauses = array();
            $filter_string = '';
            if (isset($filter)) {
                $filters = json_decode($filter);
                if ($filters != NULL) {
                    foreach ($filters as $filter) {
                        switch ($filter->property) {
                            case 'beneficiary_id' :
                                $whereClauses[] = "t1.beneficiary_id like '%" . ($filter->value) . "%'";
                                break;
                            case 'first_name' :
                                $whereClauses[] = "decrypt(t1.first_name) like '%" . ($filter->value) . "%'";
                                break;
                            case 'last_name' :
                                $whereClauses[] = "decrypt(t1.last_name) like '%" . ($filter->value) . "%'";
                                break;
                        }
                    }
                    $whereClauses = array_filter($whereClauses);
                }
                if (!empty($whereClauses)) {
                    $filter_string = implode(' AND ', $whereClauses);
                }
            }
            $qry = DB::table('beneficiary_information as t1')
                ->leftJoin('school_information as t2', 't2.id', '=', 't1.school_id')
                ->select(DB::raw('CASE WHEN decrypt(t1.first_name) IS NULL THEN first_name ELSE decrypt(t1.first_name) END as first_name, CASE WHEN decrypt(t1.last_name) IS NULL THEN last_name ELSE decrypt(t1.last_name) END as last_name,
                              t2.name as school, t1.id as girl_id, t1.school_id, t1.current_school_grade, t1.beneficiary_id, t1.dob, t1.district_id, t1.beneficiary_school_status'))
                ->whereIn('t1.enrollment_status', $enrollment_status);
            if (isset($district_id) && $district_id != '') {
                $qry->where('t2.district_id', $district_id);
            }
            if (isset($school_id) && $school_id != '') {
                $qry->where('t1.school_id', $school_id);
            }
            if ($filter_string != '') {
                $qry->whereRAW($filter_string);
            }
            $total = $qry->count();
            $qry->offset($start)->limit($limit);
            $data = $qry->get();
            $res = array(
                'success' => true,
                'message' => 'Records fetched successfully!!',
                'total' => $total,
                'results' => $data
            );
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage(),
                'results' => array()
            );
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage(),
                'results' => array()
            );
        }
        return response()->json($res);
    }

    public function getAllImportedDataset(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $province = $req->input('province');
        $district = $req->input('district');
        $cwac = $req->input('cwac');
        $school = $req->input('school');
        try {
            $qry = DB::table('beneficiary_master_info')
                ->where('batch_id', $batch_id);
            if ($province != '') {
                $qry->where('');
            }
            if ($district != '') {
                $qry->where('sct_district', 'like', '%' . $district . '%');
            }
            if ($cwac != '') {
                $qry->where('cwac', 'like', '%' . $cwac . '%');
            }
            if ($school != '') {
                $qry->where('school_name', 'like', '%' . $school . '%');
            }
            $data = $qry->get();
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'All is well'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => true,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getAssessmentFilteredInDataSet(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $category = $req->input('category');
            $province = $req->input('province');
            $district = $req->input('district');
            $cwac = $req->input('cwac');
            $school = $req->input('school');
            $whereCat = array($category);
            if ($category == 2) {
                $whereCat = array(2, 3);
            }
            $qry = DB::table('beneficiary_master_info')
                ->where('batch_id', $batch_id)
                ->whereIn('category', $whereCat);
            if ($province != '') {
                $qry->where('');
            }
            if ($district != '') {
                $qry->where('sct_district', 'like', '%' . $district . '%');
            }
            if ($cwac != '') {
                $qry->where('cwac', 'like', '%' . $cwac . '%');
            }
            if ($school != '') {
                $qry->where('school_name', 'like', '%' . $school . '%');
            }
            $data = $qry->get();
            $res = array(
                'success' => true,
                'message' => returnMessage($data),
                'results' => $data
            );
        } catch (\Exception $exception) {
            $res = array(
                'success' => true,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => true,
                'message' => $throwable->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getBatchTransitionalStages(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $qry = DB::table('batch_statuses')
                ->leftJoin('batches_transitional_report', function ($join) use ($batch_id) {
                    $join->on('batches_transitional_report.stage_id', '=', 'batch_statuses.id')
                        ->on('batches_transitional_report.batch_id', '=', DB::raw($batch_id));
                })
                ->leftJoin('users', 'batches_transitional_report.author', '=', 'users.id')
                ->select('batches_transitional_report.*', 'users.first_name', 'users.last_name', 'batch_statuses.tabindex', 'batch_statuses.id as phase_id', 'batch_statuses.name')
                ->orderBy('tabindex', 'Asc')
                ->orderBy('from_date', 'Asc');
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $data = decryptArray($data);
            foreach ($data as $key => $datum) {
                $days = $this->getDateDiff($datum['from_date'], $datum['to_date']);
                if (is_numeric($days) && $days == 0) {
                    $str = '[Done same day]';
                } else if ($days == 1) {
                    $str = 'Day';
                } else if ($days > 1) {
                    $str = 'Days';
                } else {
                    $str = '';
                }
                $data[$key]['is_done'] = (strtotime($datum['to_date']) > 0) ? 1 : 0;
                $data[$key]['author_name'] = $datum['first_name'] . '&nbsp;' . $datum['last_name'];
                $data[$key]['num_days'] = $days . '&nbsp;' . $str;
            }
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'All is well'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    function getDateDiff($from_date, $to_date)
    {
        if ($from_date == '' || $from_date == '0000-00-00 00:00:00') {
            return false;
        }
        if ($to_date == '' || $to_date == '0000-00-00 00:00:00') {
            $to_date = Carbon::now();
        }
        $from = Carbon::createFromFormat('Y-m-d H:s:i', $from_date);
        $to = Carbon::createFromFormat('Y-m-d H:s:i', $to_date);
        $diff_in_days = $to->diffInDays($from);
        return $diff_in_days;
    }

    public function getAssessmentFilteredOutRecords(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $district = $req->input('district');
            $cwac = $req->input('cwac');
            $school = $req->input('school');
            $duplicates = $req->input('duplicates');
            $qry = DB::table('beneficiary_master_info')
                ->where('batch_id', $batch_id)
                ->whereNotIn('category', array(1, 2, 3));
            if ($district != '') {
                $qry->where('sct_district', 'like', '%' . $district . '%');
            }
            if ($cwac != '') {
                $qry->where('cwac', 'like', '%' . $cwac . '%');
            }
            if ($school != '') {
                $qry->where('school_name', 'like', '%' . $school . '%');
            }
            if (validateisNumeric($duplicates)) {
                if ($duplicates == 1) {
                    $qry->where(array('is_duplicate' => 1, 'is_active' => 0));
                } else {
                    $qry->where(array('is_active' => 1));
                }
            }
            $data = $qry->get();
            $res = array(
                'success' => true,
                'message' => returnMessage($data),
                'results' => $data
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getAssessmentDuplicateRecords(Request $req)
    {
        $batch_id = $req->batch_id;
        $where = array(
            'batch_id' => $batch_id,
            'is_duplicate' => 1
        );
        try {
            $data = DB::table('beneficiary_master_info')
                ->where($where)
                ->get();
            //$data = convertStdClassObjToArray($data);
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'All is well'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getAssessmentSummary(Request $req)
    {
        $batch_id = $req->input('batch_id');

        $outOfSchool = DB::table('beneficiary_master_info')
            ->where('batch_id', $batch_id)
            // ->where('is_duplicate_with_existing', '<>', 1)
            ->where('category', 1)
            ->where('is_active', 1)
            ->count();

        $inSchool = DB::table('beneficiary_master_info')
            ->where('batch_id', $batch_id)
            //->where('is_duplicate_with_existing', '<>', 1)
            ->whereIn('category', array(2, 3))
            ->where('is_active', 1)
            ->count();

        $examClasses = DB::table('beneficiary_master_info')
            ->where('batch_id', $batch_id)
            //->where('is_duplicate_with_existing', '<>', 1)
            ->where('category', 3)
            ->where('is_active', 1)
            ->count();

        $duplicates = DB::table('beneficiary_master_info')
            ->where('batch_id', $batch_id)
            ->where('is_duplicate', 1)
            ->where('is_active', 1)
            ->count();

        $duplicates_existing = DB::table('beneficiary_master_info')
            ->where('batch_id', $batch_id)
            ->where('is_duplicate_with_existing', 1)
            ->count();

        $total = DB::table('beneficiary_master_info')
            ->where('batch_id', $batch_id)
            // ->where('is_active', 1)
            ->count();
        $res = array(
            'out_of_school_count' => $outOfSchool,
            'in_school_count' => $inSchool,
            'exam_classes_count' => $examClasses,
            'duplicates_count' => $duplicates,
            'duplicates_count_existing' => $duplicates_existing,
            'total_records' => $total
        );
        return response()->json(array('results' => $res));
    }

    public function getMappingSummary(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $outOfSchoolPassed = DB::table('beneficiary_master_info')
                ->where('batch_id', $batch_id)
                ->where('category', 1)
                ->where('is_mapped', 1)
                ->count();

            $outOfSchoolFailed = DB::table('beneficiary_master_info')
                ->where('batch_id', $batch_id)
                ->where('category', 1)
                ->where('is_mapped', '<>', 1)
                ->count();

            $inSchoolPassed = DB::table('beneficiary_master_info')
                ->where('batch_id', $batch_id)
                ->whereIn('category', array(2, 3))
                ->where('is_mapped', 1)
                ->count();

            $inSchoolFailed = DB::table('beneficiary_master_info')
                ->where('batch_id', $batch_id)
                ->whereIn('category', array(2, 3))
                ->where('is_mapped', '<>', 1)
                ->count();

            $examClassesPassed = DB::table('beneficiary_master_info')
                ->where('batch_id', $batch_id)
                ->where('category', 3)
                ->where('is_mapped', 1)
                ->count();

            $examClassesFailed = DB::table('beneficiary_master_info')
                ->where('batch_id', $batch_id)
                ->where('category', 3)
                ->where('is_mapped', '<>', 1)
                ->count();

            $duplicates_existing = DB::table('beneficiary_master_info')
                ->where('batch_id', $batch_id)
                ->where('is_duplicate_with_existing', 1)
                ->count();

            $data = array(
                'out_of_school_passed' => $outOfSchoolPassed,
                'out_of_school_failed' => $outOfSchoolFailed,
                'in_school_passed' => $inSchoolPassed,
                'in_school_failed' => $inSchoolFailed,
                'exam_classes_passed' => $examClassesPassed,
                'exam_classes_failed' => $examClassesFailed,
                'duplicates_count_existing' => $duplicates_existing
            );
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'All is well'
            );
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
        return response()->json($res);
    }

    public function getMappingRecords(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $mapping_status = $req->input('mapping_status');
            $category = $req->input('category');
            $district = $req->input('district');
            $cwac = $req->input('cwac');
            $school = $req->input('school');
            $whereCat = array($category);
            if ($category == 2) {
                $whereCat = array(2, 3);
            }
            $qry = DB::table('beneficiary_master_info')
                ->where('batch_id', $batch_id)
                ->whereIn('category', $whereCat)
                ->where('is_mapped', $mapping_status);
            if ($district != '') {
                $qry->where('sct_district', 'like', '%' . $district . '%');
            }
            if ($cwac != '') {
                $qry->where('cwac', 'like', '%' . $cwac . '%');
            }
            if ($school != '') {
                $qry->where('school_name', 'like', '%' . $school . '%');
            }
            $data = $qry->get();
            $res = array(
                'success' => true,
                'message' => returnMessage($data),
                'results' => $data
            );
        } catch (\Exception $exception) {
            $res = array(
                'success' => true,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => true,
                'message' => $throwable->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getVerificationSummary(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $outOfSchoolEnrolled = DB::table('beneficiary_information')
                ->where('batch_id', $batch_id)
                ->where('category', 1)
                ->where('beneficiary_status', 4)
                ->where('verification_recommendation', 1)//just to be sure
                ->where('school_matching_status', 1)//just to be sure
                ->count();
            $outOfSchoolFollowups = DB::table('beneficiary_information')
                ->where('batch_id', $batch_id)
                ->where('category', 1)
                ->where('beneficiary_status', 6)
                ->count();

            $inSchoolEnrolled = DB::table('beneficiary_information')
                ->where('batch_id', $batch_id)
                ->whereIn('category', array(2, 3))
                ->where('beneficiary_status', 4)
                ->where('verification_recommendation', 1)//just to be sure
                ->count();
            $inSchoolFollowups = DB::table('beneficiary_information')
                ->where('batch_id', $batch_id)
                ->whereIn('category', array(2, 3))
                ->where('beneficiary_status', 6)
                ->count();

            $examClassesEnrolled = DB::table('beneficiary_information')
                ->where('batch_id', $batch_id)
                ->where('category', 3)
                ->where('beneficiary_status', 4)
                ->where('verification_recommendation', 1)//just to be sure
                ->where('school_placement_status', 1)//just to be sure
                ->count();
            $examClassesFollowups = DB::table('beneficiary_information')
                ->where('batch_id', $batch_id)
                ->where('category', 3)
                ->where('beneficiary_status', 6)
                ->count();
            $data = array(
                'out_of_school_enrolled' => $outOfSchoolEnrolled,
                'out_of_school_followups' => $outOfSchoolFollowups,
                'in_school_enrolled' => $inSchoolEnrolled,
                'in_school_followups' => $inSchoolFollowups,
                'exam_classes_enrolled' => $examClassesEnrolled,
                'exam_classes_followups' => $examClassesFollowups
            );
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'All is well'
            );
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
        return response()->json($res);
    }

    public function getVerificationBriefSummary(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $qry = DB::table('beneficiary_information as t1')
                ->select(DB::raw('SUM(IF(t1.category=1,1,0)) as out_of_school_count,
                                  SUM(IF(t1.category in (2,3),1,0)) as in_school_count,
                                  SUM(IF(t1.category=3,1,0)) as exam_classes_count'))
                ->where('t1.batch_id', $batch_id);
            $data = $qry->get();
            $res = array(
                'success' => true,
                'message' => returnMessage($data),
                'results' => $data
            );
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
        return response()->json($res);
    }

    public function getVerificationFollowupsRecords(Request $req)
    {
        try {
            $start = $req->input('start');
            $limit = $req->input('limit');
            $province_id = $req->input('province_id');
            $district_id = $req->input('district_id');
            $cwac_id = $req->input('cwac_id');
            $school_id = $req->input('school_id');
            $batch_id = $req->input('batch_id');
            $category = $req->input('category');
            $checklist_item_id = $req->input('checklist_item_id');
            $answer = $req->input('answer');
            $filter = $req->input('filter');
            $beneficiary_status = 6;

            $whereClauses = array();
            $filter_string = '';
            if (isset($filter)) {
                $filters = json_decode($filter);
                if ($filters != NULL) {
                    foreach ($filters as $filter) {
                        switch ($filter->property) {
                            case 'beneficiary_id' :
                                $whereClauses[] = "t1.beneficiary_id like '%" . ($filter->value) . "%'";
                                break;
                            case 'first_name' :
                                $whereClauses[] = "t1.first_name like '%" . ($filter->value) . "%'";
                                break;
                            case 'last_name' :
                                $whereClauses[] = "t1.last_name like '%" . ($filter->value) . "%'";
                                break;
                        }
                    }
                    $whereClauses = array_filter($whereClauses);
                }
                if (!empty($whereClauses)) {
                    $filter_string = implode(' AND ', $whereClauses);
                }
            }

            $qry = DB::table('beneficiary_information as t1')
                ->join('households as t2', 't1.household_id', '=', 't2.id')
                ->join('districts as t3', 't1.district_id', '=', 't3.id')
                ->join('beneficiary_categories as t4', 't1.category', '=', 't4.id')
                ->join('verification_recommendation as t5', 't1.verification_recommendation', '=', 't5.id')
                ->select(DB::raw('t4.name as category_name,t1.id,t1.beneficiary_id,t1.current_school_grade,t1.dob,decrypt(t1.first_name) as first_name, decrypt(t1.last_name) as last_name,
                              t2.id as hh_id, t2.hhh_fname, t2.hhh_lname, t2.hhh_nrc_number, t2.number_in_cwac,t1.batch_id,t1.category,t1.beneficiary_status,
                              t3.name as district_name,t1.category,t1.verification_recommendation, t1.skip_matching,t5.name as recommendation_nm,t1.verification_type'))
                ->where('t1.beneficiary_status', $beneficiary_status);

            if (isset($answer) && $answer != '') {
                $qry->join('beneficiary_verification_report as t6', function ($join) use ($checklist_item_id, $answer) {
                    $join->on('t1.id', '=', 't6.beneficiary_id')
                        ->where(array('t6.checklist_item_id' => $checklist_item_id))
                        ->where('t6.response', 'LIKE', $answer . '%');
                });
            }
            if (validateisNumeric($category)) {
                $qry->where('t1.category', $category);
            }
            if (isset($province_id) && $province_id != '') {
                $qry->where('t1.province_id', $province_id);
            }
            if (isset($district_id) && $district_id != '') {
                $qry->where('t1.district_id', $district_id);
            }
            if (isset($cwac_id) && $cwac_id != '') {
                $qry->where('t1.cwac_id', $cwac_id);
            }
            if (isset($school_id) && $school_id != '') {
                $qry->where('t1.school_id', $school_id);
            }
            if (isset($batch_id) && $batch_id != '') {
                $qry->where('t1.batch_id', $batch_id);
            }
            if ($filter_string != '') {
                $qry->whereRAW($filter_string);
            }
            $total = $qry->count();
            if (validateisNumeric($limit)) {
                $qry->offset($start)
                    ->limit($limit);
            }
            $results = $qry->get();
            $res = array(
                'success' => true,
                'totalCount' => $total,
                'results' => $results
            );
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
        return response()->json($res);
    }

    public function saveBeneficiaryImage(Request $req)
    {
        $girl_id = $req->input('id');
        $res = array();
        try {
            if ($req->hasFile('beneficiary_image')) {
                $ben_image = $req->file('beneficiary_image');
                $origImageName = $ben_image->getClientOriginalName();
                $extension = $ben_image->getClientOriginalExtension();
                $destination = getcwd() . '\resources\images\beneficiary_images';
                $savedName = str_random(5) . time() . '.' . $extension;
                $ben_image->move($destination, $savedName);
                $where = array(
                    'girl_id' => $girl_id
                );
                $recordExists = recordExists('beneficiary_images', $where);
                if ($recordExists) {
                    $update_params = array(
                        'initial_name' => $origImageName,
                        'saved_name' => $savedName,
                        'updated_by' => \Auth::user()->id
                    );
                    DB::table('beneficiary_images')
                        ->where($where)
                        ->update($update_params);
                } else {
                    $insert_params = array(
                        'girl_id' => $girl_id,
                        'initial_name' => $origImageName,
                        'saved_name' => $savedName,
                        'created_by' => \Auth::user()->id
                    );
                    DB::table('beneficiary_images')
                        ->insert($insert_params);
                }
                $res = array(
                    'success' => true,
                    'image_name' => $savedName,
                    'message' => 'Image uploaded successfully!!'
                );
            }
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getBeneficiaryAdditionalInfo(Request $req)
    {
        $master_id = $req->input('master_id');
        $batch_id = $req->input('batch_id');
        $stdTempID = getStdTemplateId();
        $batch_info = DB::table('batch_info')
            ->where('id', $batch_id)
            ->first();
        if (is_null($batch_info)) {
            return response()->json(array('success' => false, 'message' => 'Sorry, problem encountered fetching beneficiary additional info. Please try again!!'));
        }
        $template_id = $batch_info->template_id;
        if ($template_id == $stdTempID) {
            return response()->json(array());
        }
        $qry = DB::table('template_fields');
        if ($master_id != '') {
            $qry->leftJoin('temp_additional_fields_values', function ($join) use ($batch_id, $master_id) {
                $join->on('template_fields.id', '=', 'temp_additional_fields_values.field_id')
                    ->on('temp_additional_fields_values.batch_id', '=', DB::raw($batch_id))
                    ->on('temp_additional_fields_values.main_temp_id', '=', DB::raw($master_id));
            })
                ->select('template_fields.*', 'temp_additional_fields_values.value');
        }
        $qry->where('temp_id', $template_id);
        $data = $qry->get();
        $data = convertStdClassObjToArray($data);
        $data = decryptArray($data);
        $res = array(
            'results' => $data
        );
        return response()->json($res);
    }

    public function updateGirlInformation(Request $req)
    {
        $additionalValues = $req->input('values');
        $master_id = $req->input('master_id');
        $girl_id = $req->input('id');
        $batch_id = $req->input('batch_id');
        $hh_id = $req->input('household_id');
        $girl_fname = $req->input('first_name');
        $girl_lname = $req->input('last_name');
        $girl_dob = $req->input('dob');
        $verified_dob = $req->input('verified_dob');
        $hhh_nrc = $req->input('hhh_nrc_number');
        $additionalValues = json_decode($additionalValues);

        $benInfoParams = array(
            'cwac_id' => $req->input('cwac_id'),
            'acc_id' => $req->input('acc_id'),
            'ward_id' => $req->input('ward_id'),
            'constituency_id' => $req->input('constituency_id'),
            'district_id' => $req->input('district_id'),
            'province_id' => $req->input('province_id'),
            'first_name' => aes_encrypt($girl_fname),
            'last_name' => aes_encrypt($girl_lname),
            'dob' => $girl_dob,
            'verified_dob' => $verified_dob,
            //'current_school_grade' => $req->input('current_school_grade'),
            'beneficiary_school_status' => $req->input('beneficiary_school_status'),
            'updated_by' => \Auth::user()->id
        );
        $masterParams = array(
            'hhh_nrc' => $hhh_nrc,
            'girl_fname' => $girl_fname,
            'girl_lname' => $girl_lname,
            'girl_dob' => $girl_dob
        );
        $hhParams = array(
            'hhh_nrc_number' => $hhh_nrc,
            'hhh_fname' => $req->input('hhh_fname'),
            'hhh_lname' => $req->input('hhh_lname'),
            'updated_by' => \Auth::user()->id
        );
        $res = array();
        DB::transaction(function () use (&$res, $girl_id, $master_id, $hh_id, $hhParams, $additionalValues, $benInfoParams, $masterParams, $batch_id) {
            try {
                if (is_numeric($girl_id) && $girl_id != '') {
                    logUpdateBeneficiaryInfo($girl_id, $masterParams);
                    DB::table('beneficiary_information')
                        ->where('id', $girl_id)
                        ->update($benInfoParams);
                    DB::table('households')
                        ->where('id', $hh_id)
                        ->update($hhParams);

                    if (count($additionalValues) > 0) {
                        foreach ($additionalValues as $additionalValue) {
                            $where = array(
                                'main_temp_id' => $master_id,
                                'field_id' => $additionalValue->field_id
                            );
                            $insertValues = array(
                                'main_temp_id' => $master_id,
                                'field_id' => $additionalValue->field_id,
                                'batch_id' => $batch_id,
                                'value' => $additionalValue->value
                            );
                            if (recordExists('temp_additional_fields_values', $where)) {
                                DB::table('temp_additional_fields_values')
                                    ->where($where)
                                    ->update($insertValues);
                            } else {
                                DB::table('temp_additional_fields_values')
                                    ->insert($insertValues);
                            }
                        }
                    }
                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'Problem encontered while getting beneficiary info for update!!'
                    );
                    return response()->json($res);
                }
                $res = array(
                    'success' => true,
                    'message' => 'Information saved successfully!!'
                );
            } catch (\Exception $e) {
                $res = array(
                    'success' => false,
                    'message' => $e->getMessage()
                );
            }
        }, 5);
        return response()->json($res);
    }

    public function getBeneficiaryUpdateHistory(Request $req)
    {
        $girl_id = $req->input('girl_id');
        try {
            $qry = DB::table('beneficiary_information_logs')
                ->leftJoin('households', 'beneficiary_information_logs.household_id', '=', 'households.id')
                ->leftJoin('school_information', 'beneficiary_information_logs.school_id', '=', 'school_information.id')
                ->leftJoin('districts', 'beneficiary_information_logs.district_id', '=', 'districts.id')
                ->leftJoin('provinces', 'beneficiary_information_logs.province_id', '=', 'provinces.id')
                ->leftJoin('cwac', 'beneficiary_information_logs.cwac_id', '=', 'cwac.id')
                ->leftJoin('constituencies', 'beneficiary_information_logs.constituency_id', '=', 'districts.id')
                ->leftJoin('acc', 'beneficiary_information_logs.constituency_id', '=', 'constituencies.id')
                ->leftJoin('users', 'beneficiary_information_logs.altered_by', '=', 'users.id')
                ->select('beneficiary_information_logs.*', 'users.first_name as author_fname', 'users.last_name as author_lname', 'cwac.name as cwac_name', 'provinces.name as province_name', 'constituencies.name as constituency_name', 'acc.name as acc_name', 'school_information.name as school_name', 'households.hhh_fname', 'households.hhh_lname', 'households.hhh_nrc_number', 'districts.name as district_name')
                ->where('beneficiary_information_logs.girl_id', $girl_id);
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $data = decryptArray($data);
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'All is well'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function saveBeneficiaryPerformance(Request $req)
    {
        $girl_id = $req->input('girl_id');
        $school_id = $req->input('school_id');
        $year = $req->input('year');
        $grade = $req->input('grade');
        $average_score = $req->input('average_score');
        $attendance = $req->input('attendance');
        $term = $req->input('term');
        $values = $req->input('values');
        $values = json_decode($values);
        $eng_score = '';
        $maths_score = '';
        $scie_score = '';
        $eng_ave_score = '';
        $maths_ave_score = '';
        $scie_ave_score = '';
        $user_id = \Auth::user()->id;
        try {
            if (count($values) > 0) {
                foreach ($values as $value) {
                    if ($value->subject_id == 1) {
                        if (isset($value->score)) {
                            $eng_score = $value->score;
                        }
                        if (isset($value->class_ave)) {
                            $eng_ave_score = $value->class_ave;
                        }
                    } else if ($value->subject_id == 2) {
                        if (isset($value->score)) {
                            $maths_score = $value->score;
                        }
                        if (isset($value->class_ave)) {
                            $maths_ave_score = $value->class_ave;
                        }
                    } else if ($value->subject_id == 3) {
                        if (isset($value->score)) {
                            $scie_score = $value->score;
                        }
                        if (isset($value->class_ave)) {
                            $scie_ave_score = $value->class_ave;
                        }
                    }
                }
            }
            $where = array(
                'beneficiary_id' => $girl_id,
                'school_id' => $school_id,
                'term_id' => $term,
                'grade' => $grade,
                'year_of_enrollment' => $year
            );
            $recordExists = recordExists('beneficiary_attendanceperform_details', $where);
            if ($recordExists) {
                $prev_record = getPreviousRecords('beneficiary_attendanceperform_details', $where);
                $updateParams = array(
                    'science_score' => $scie_score,
                    'mathematics_score' => $maths_score,
                    'mathsclass_average' => $maths_ave_score,
                    'english_score' => $eng_score,
                    'engclass_average' => $eng_ave_score,
                    'scienceclass_average' => $scie_ave_score,
                    'aggregate_average_score' => $average_score,
                    'benficiary_attendance' => $attendance,
                    'updated_by' => \Auth::user()->id
                );
                $record_updated = updateRecord('beneficiary_attendanceperform_details', $prev_record, $where, $updateParams, $user_id);
            } else {
                $insertParams = array(
                    'school_id' => $school_id,
                    'term_id' => $term,
                    'grade' => $grade,
                    'year_of_enrollment' => $year,
                    'beneficiary_id' => $girl_id,
                    'science_score' => $scie_score,
                    'mathematics_score' => $maths_score,
                    'mathsclass_average' => $maths_ave_score,
                    'english_score' => $eng_score,
                    'engclass_average' => $eng_ave_score,
                    'scienceclass_average' => $scie_ave_score,
                    'aggregate_average_score' => $average_score,
                    'benficiary_attendance' => $attendance,
                    'created_by' => \Auth::user()->id
                );
                $record_inserted = insertRecord('beneficiary_attendanceperform_details', $insertParams, $user_id);
            }
            $res = array(
                'success' => true,
                'message' => 'Details saved successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getBenPerformance(Request $req)
    {
        $girl_id = $req->input('girl_id');
        try {
            $qry = DB::table('beneficiary_attendanceperform_details')
                ->join('school_information', 'beneficiary_attendanceperform_details.school_id', '=', 'school_information.id')
                ->select('beneficiary_attendanceperform_details.*', 'school_information.name as school_name')
                ->where('beneficiary_id', $girl_id);
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $data = decryptArray($data);
            foreach ($data as $key => $datum) {
                $data[$key]['total_learning_days'] = getTermTotalLearningDays($datum['year_of_enrollment'], $datum['term_id']);
            }
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'All is well'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getSpecificBeneficiaryPerformance(Request $req)
    {
        $girl_id = $req->input('girl_id');
        $school_id = $req->input('school_id');
        $year = $req->input('year');
        $grade = $req->input('grade');
        $term = $req->input('term');
        $where = array(
            'school_id' => $school_id,
            'term_id' => $term,
            'grade' => $grade,
            'year_of_enrollment' => $year,
            'beneficiary_id' => $girl_id
        );
        $results = array();
        try {
            $qry = DB::table('beneficiary_attendanceperform_details')
                ->where($where);
            $data = $qry->first();
            if (!is_null($data)) {
                $results = $data;
            }
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => 'All is well'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getBeneficiaryStatuses(Request $req)
    {
        $girl_id = $req->input('girl_id');
        try {
            $qry = DB::table('beneficiary_enrollement_statuses')
                ->leftJoin('beneficiary_information', function ($join) use ($girl_id) {
                    $join->on('beneficiary_enrollement_statuses.id', '=', 'beneficiary_information.enrollment_status')
                        ->on('beneficiary_information.id', '=', DB::raw($girl_id));
                })
                ->select('beneficiary_enrollement_statuses.*', 'beneficiary_information.enrollment_status')
                ->orderBy('tabindex', 'Asc');
            $data = $qry->get();
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'All is well'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function saveBenStatusChanges(Request $req)
    {
        try {
            $girl_id = $req->input('girl_id');
            $from_stage = $req->input('from_status');
            $to_stage = $req->input('to_status');
            $reason = $req->input('reason');
            $reason_id = $req->input('sus_reason');
            $user_id = \Auth::user()->id;
            if ($to_stage == 2) {
                $to_stage = 5;
                $sus_params = array(
                    'girl_id' => $girl_id,
                    'reason_id' => $reason_id,
                    'user_reason' => $reason,
                    'request_by' => $user_id,
                    'request_date' => Carbon::now(),
                    'stage' => 1,
                    'created_by' => $user_id,
                    'created_at' => Carbon::now()
                );
                DB::table('suspension_requests')
                    ->insert($sus_params);
            }
            $params = array(
                'girl_id' => $girl_id,
                'from_stage' => $from_stage,
                'to_stage' => $to_stage,
                'reason' => $reason,
                'author' => $user_id,
                'created_by' => $user_id,
                'created_at' => Carbon::now()
            );
            DB::table('beneficiary_information')
                ->where('id', $girl_id)
                ->update(array('enrollment_status' => $to_stage));
            DB::table('beneficiaries_transitional_report')
                ->insert($params);
            $res = array(
                'success' => true,
                'message' => 'Changes saved successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getBeneficiaryStatusesHistory(Request $req)
    {
        $girl_id = $req->input('girl_id');
        try {
            $qry = DB::table('beneficiaries_transitional_report')
                ->join('users', 'beneficiaries_transitional_report.author', '=', 'users.id')
                ->join('beneficiary_enrollement_statuses as t1', 'beneficiaries_transitional_report.from_stage', '=', 't1.id')
                ->join('beneficiary_enrollement_statuses as t2', 'beneficiaries_transitional_report.to_stage', '=', 't2.id')
                ->select('t1.name as from_stage_name', 't2.name as to_stage_name', 'beneficiaries_transitional_report.reason', 'beneficiaries_transitional_report.created_at as changes_date', 'users.first_name', 'users.last_name')
                ->where('beneficiaries_transitional_report.girl_id', $girl_id)
                ->orderBy('beneficiaries_transitional_report.id');
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $data = decryptArray($data);
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'All is well'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getBeneficiaryEnrollments(Request $req)
    {
        $girl_id = $req->input('girl_id');
        try {
            $qry = DB::table('beneficiary_enrollments')
                ->join('school_information', 'beneficiary_enrollments.school_id', '=', 'school_information.id')
                ->leftJoin('beneficiary_payment_records', 'beneficiary_enrollments.id', '=', 'beneficiary_payment_records.enrollment_id')
                //->leftJoin('payment_disbursement_details', 'beneficiary_payment_records.payment_request_id', '=', 'payment_disbursement_details.payment_request_id')
                ->leftJoin('payment_disbursement_details', function ($join) {
                    $join->on('beneficiary_payment_records.payment_request_id', '=', 'payment_disbursement_details.payment_request_id')
                        ->on('payment_disbursement_details.school_id', '=', 'beneficiary_enrollments.school_id');
                })
                ->select('beneficiary_enrollments.*', 'school_information.name as school_name', 'beneficiary_payment_records.id as payment_status', 'payment_disbursement_details.id as disbursement_status')
                ->where('beneficiary_id', $girl_id);
            $data = $qry->get();
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'All is well'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => true,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getBeneficiaryPaymentDetails(Request $req)
    {
        $enrollment_id = $req->input('enrollment_id');
        try {
            $qry = DB::table('beneficiary_enrollments')
                ->join('payment_verificationbatch', 'beneficiary_enrollments.batch_id', 'payment_verificationbatch.id')
                ->leftJoin('beneficiary_payment_records', 'beneficiary_enrollments.id', 'beneficiary_payment_records.enrollment_id')
                //->leftJoin('payment_disbursement_details', 'beneficiary_payment_records.payment_request_id', '=', 'payment_disbursement_details.payment_request_id')
                ->leftJoin('payment_disbursement_details', function ($join) {
                    $join->on('beneficiary_payment_records.payment_request_id', '=', 'payment_disbursement_details.payment_request_id')
                        ->on('beneficiary_enrollments.school_id', '=', 'payment_disbursement_details.school_id');
                })
                ->leftJoin('payment_request_details', 'beneficiary_payment_records.payment_request_id', 'payment_request_details.id')
                ->join('school_information', 'beneficiary_enrollments.school_id', '=', 'school_information.id')
                ->select(DB::raw('decrypt(payment_disbursement_details.amount_transfered) as amount_transfered,
                         decrypt(payment_disbursement_details.transaction_no) as transaction_no,
                         payment_disbursement_details.transaction_date,
                         beneficiary_enrollments.school_fees,
                         beneficiary_enrollments.school_grade,
                         beneficiary_enrollments.term_id,
                         beneficiary_enrollments.year_of_enrollment,
                         payment_verificationbatch.batch_no as payment_ver_batch_no,
                         payment_request_details.payment_ref_no,
                         school_information.name as school_name'))
                ->where('beneficiary_enrollments.id', $enrollment_id);
            $data = $qry->get();
            /*$data = convertStdClassObjToArray($data);
            $data = decryptArray($data);*/
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'All is well'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getPaymentReceipt(Request $req)
    {
        $enrollment_id = $req->input('enrollment_id');
        try {
            $qry = DB::table('payments_receipting_details as t1')
                ->rightJoin('beneficiary_receipting_details as t2', 't1.id', '=', 't2.payment_receipt_id')
                ->select('t2.receipt_no', 't2.receipt_date', 't2.receipt_amount', 't2.document_id')
                ->where('t1.enrollment_id', $enrollment_id);
            $data = $qry->get();
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'All is well'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getBeneficiaryCases(Request $req)
    {
        $girl_id = $req->input('girl_id');
        try {
            $qry = DB::table('case_information')
                ->leftJoin('case_statuses', 'case_information.case_status_id', '=', 'case_statuses.id')
                ->select('case_information.*', 'case_statuses.name as case_status')
                ->where('case_information.beneficiary_id', $girl_id);
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $data = decryptArray($data);
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'All is well'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function saveSchoolTransferImplementation(Request $req)
    {
        try {
            $user_id = \Auth::user()->id;
            $id = $req->input('id');
            $is_submit = $req->input('is_submit');
            $to_school_id = $req->input('to_school_id');
            $transfer_type = $req->input('transfer_type');
            $girl_id = $req->input('girl_id');
            $grade = $req->input('transfer_grade');
            $school_status = $req->input('transfer_school_status');
            if (!validateisNumeric($to_school_id)) {
                $res = array(
                    'success' => false,
                    'message' => "Please specify the school where the beneficiary is transferring to!!"
                );
                return response()->json($res);
                exit();//just to be sure
            }
            $params = array(
                'girl_id' => $girl_id,
                'from_school_id' => $req->input('from_school_id'),
                'to_school_id' => $req->input('to_school_id'),
                'transfer_grade' => $req->input('transfer_grade'),
                'transfer_school_status' => $req->input('transfer_school_status'),
                'reason_id' => $req->input('reason_id'),
                'effective_from_year' => $req->input('effective_from_year'),
                'effective_from_term' => $req->input('effective_from_term'),
                'transfer_type' => $req->input('transfer_type'),
                'request_by' => $user_id,
                'request_comment' => $req->input('request_comment'),
                'created_by' => $user_id,
                'created_at' => Carbon::now(),
                'requested_on' => Carbon::now(),
            );
            if ($is_submit == 1) {
                $params['stage'] = 2;
            }
            $where = array(
                'id' => $id
            );
            if (validateisNumeric($id)) {
                if (recordExists('beneficiary_school_transfers', $where)) {
                    unset($params['requested_on']);
                    unset($params['created_at']);
                    unset($params['created_by']);
                    unset($params['request_by']);
                    $params['updated_at'] = Carbon::now();
                    $params['updated_by'] = $user_id;
                    $prev_data = getPreviousRecords('beneficiary_school_transfers', $where);
                    $updated = updateRecord('beneficiary_school_transfers', $prev_data, $where, $params, $user_id);
                    if ($updated === true) {
                        $res = array(
                            'success' => true,
                            'message' => 'Details updated successfully!!'
                        );
                    } else {
                        $res = $updated;
                    }
                }
            } else {
                $success = insertRecord('beneficiary_school_transfers', $params, $user_id);
                if ($success == true) {
                    if ($transfer_type == 3 || $transfer_type === 3) {//for school assignment...it's a submission and archival
                        $this->updateSchoolAssignmentApproval($success['record_id'], $girl_id, $to_school_id, $grade, $school_status);
                    }
                    $res = array(
                        'success' => true,
                        'message' => 'Details saved successfully!!'
                    );
                } else {
                    $res = $success;
                }
            }
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
        return response()->json($res);
    }

    public function updateSchoolAssignmentApproval($id, $girl_id, $to_school_id, $grade, $school_status)
    {
        $user_id = $this->user_id;
        $where = array(
            'id' => $id
        );
        $params = array(
            'status' => 2,
            'approval_by' => $user_id,
            'approval_comment' => 'Auto approval from school assignment',
            'approval_date' => Carbon::now(),
            'stage' => 3//archive
        );
        $update = array(
            'school_id' => $to_school_id,
            'current_school_grade' => $grade,
            'beneficiary_school_status' => $school_status
        );
        DB::table('beneficiary_information')
            ->where('id', $girl_id)
            ->update($update);
        logBeneficiaryGradeTransitioning($girl_id, $grade, $to_school_id, $user_id);
        $prev_data = getPreviousRecords('beneficiary_school_transfers', $where);
        updateRecord('beneficiary_school_transfers', $prev_data, $where, $params, $user_id);
    }

    public function saveSchoolTransferApproval(Request $req)
    {
        try {
            $user_id = \Auth::user()->id;
            $id = $req->input('id');
            $is_submit = $req->input('is_submit');
            $girl_id = $req->input('girl_id');
            $to_school_id = $req->input('to_school_id');
            $grade = $req->input('transfer_grade');
            $school_status = $req->input('transfer_school_status');
            $approval_status = $req->input('status');
            if (!validateisNumeric($to_school_id)) {
                $res = array(
                    'success' => false,
                    'message' => "Please specify the school where the beneficiary is transferring to!!"
                );
                return response()->json($res);
                exit();//just to be sure
            }
            $params = array(
                'to_school_id' => $to_school_id,
                'reason_id' => $req->input('reason_id'),
                'effective_from_year' => $req->input('effective_from_year'),
                'effective_from_term' => $req->input('effective_from_term'),
                'status' => $approval_status,
                'approval_by' => $user_id,
                'approval_comment' => $req->input('approval_comment'),
                'approval_date' => Carbon::now()
            );
            if ($is_submit == 1) {
                $params['stage'] = 1;
                if ($approval_status == 2) {
                    $update = array(
                        'school_id' => $to_school_id,
                        'current_school_grade' => $grade,
                        'beneficiary_school_status' => $school_status
                    );
                    DB::table('beneficiary_information')
                        ->where('id', $girl_id)
                        ->update($update);
                    logBeneficiaryGradeTransitioning($girl_id, $grade, $to_school_id, $user_id);
                }
            }
            $where = array(
                'id' => $id
            );
            if (is_numeric($id)) {
                if (recordExists('beneficiary_school_transfers', $where)) {
                    $prev_data = getPreviousRecords('beneficiary_school_transfers', $where);
                    $updated = updateRecord('beneficiary_school_transfers', $prev_data, $where, $params, $user_id);
                    if ($updated === true) {
                        $res = array(
                            'success' => true,
                            'message' => 'Details updated successfully!!'
                        );
                    } else {
                        $res = $updated;
                    }
                }
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'Sorry, transfer details were not found. Please contact system Admin!!'
                );
            }
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
        return response()->json($res);
    }

    public function getBeneficiarySchoolTransfers(Request $req)
    {
        try {
            $girl_id = $req->input('girl_id');
            $stage = $req->input('stage');
            $transfer_type = $req->input('transfer_type');
            $transfer_status = $req->input('transfer_status');
            $start = $req->input('start');
            $limit = $req->input('limit');
            $user_id = $this->user_id;
            $groups = getUserGroups($user_id);
            $superUserID = getSuperUserGroupId();
            $qry = DB::table('beneficiary_school_transfers')
                ->leftJoin('users', 'beneficiary_school_transfers.request_by', '=', 'users.id')//requester
                ->leftJoin('users as u2', 'beneficiary_school_transfers.approval_by', '=', 'u2.id')//approver
                ->leftJoin('users as u3', 'beneficiary_school_transfers.archived_by', '=', 'u3.id')//archiver
                ->join('beneficiary_information', 'beneficiary_school_transfers.girl_id', '=', 'beneficiary_information.id')
                ->join('school_transfer_types', 'beneficiary_school_transfers.transfer_type', '=', 'school_transfer_types.id')
                ->join('school_transfer_stages', 'beneficiary_school_transfers.stage', '=', 'school_transfer_stages.id')
                ->join('school_transfer_statuses', 'beneficiary_school_transfers.status', '=', 'school_transfer_statuses.id')
                ->join('school_information as t1', 'beneficiary_school_transfers.from_school_id', '=', 't1.id')
                ->leftJoin('school_information as t2', 'beneficiary_school_transfers.to_school_id', '=', 't2.id')
                ->select(DB::raw('CASE WHEN decrypt(users.first_name) IS NULL THEN users.first_name ELSE decrypt(users.first_name) END as requester_fname, CASE WHEN decrypt(users.last_name) IS NULL THEN users.last_name ELSE decrypt(users.last_name) END as requester_lname,
                                  CASE WHEN decrypt(u2.first_name) IS NULL THEN u2.first_name ELSE decrypt(u2.first_name) END as approver_fname, CASE WHEN decrypt(u2.last_name) IS NULL THEN u2.last_name ELSE decrypt(u2.last_name) END as approver_lname,
                                  CASE WHEN decrypt(u3.first_name) IS NULL THEN u3.first_name ELSE decrypt(u3.first_name) END as archiver_fname, CASE WHEN decrypt(u3.last_name) IS NULL THEN u3.last_name ELSE decrypt(u3.last_name) END as archiver_lname,
                                  CASE WHEN decrypt(beneficiary_information.first_name) IS NULL THEN beneficiary_information.first_name ELSE decrypt(beneficiary_information.first_name) END as first_name, CASE WHEN decrypt(beneficiary_information.last_name) IS NULL THEN beneficiary_information.last_name ELSE decrypt(beneficiary_information.last_name) END as last_name,
                                  beneficiary_school_transfers.*,school_transfer_stages.name as transfer_stage,beneficiary_information.beneficiary_id,beneficiary_information.dob,beneficiary_information.school_id,beneficiary_information.current_school_grade,beneficiary_information.district_id,beneficiary_information.beneficiary_school_status,t1.name as school_from,t2.name as school_to,school_transfer_types.name as transfer_type_name,school_transfer_statuses.name as transfer_status'));
            $qry = in_array($superUserID, $groups) == 1 ? $qry->whereRaw("1=1") : $qry->where('beneficiary_school_transfers.request_by', $user_id);
            if (isset($stage) && $stage != '') {
                $qry->where('beneficiary_school_transfers.stage', $stage);
            }
            if ($transfer_status != '') {
                $qry->where('beneficiary_school_transfers.status', $transfer_status);
            }
            if ($transfer_type != '') {
                $qry->where('beneficiary_school_transfers.transfer_type', $transfer_type);
            }
            if (isset($girl_id) && $girl_id != '') {
                $qry->where('beneficiary_school_transfers.girl_id', $girl_id);
            }
            $total = $qry->count();
            $qry->offset($start)
                ->limit($limit);
            $data = $qry->get();
            $res = array(
                'success' => true,
                'total' => $total,
                'totalCount' => $total,
                'results' => $data,
                'message' => 'All is well'
            );
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
        return response()->json($res);
    }

    public function getTransferApprovalOptions()
    {
        try {
            $qry = DB::table('school_transfer_statuses')
                ->whereNotIn('id', array(1));
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $data = decryptArray($data);
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'All is well'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function archiveSchoolTransfer(Request $req)
    {
        $id = $req->input('id');
        $params = array(
            'stage' => 3,
            'archived_by' => \Auth::user()->id
        );
        try {
            DB::table('beneficiary_school_transfers')
                ->where('id', $id)
                ->update($params);
            $res = array(
                'success' => true,
                'message' => 'Record archived successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getBatchFolderID(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $folder_id = getParentFolderID('batch_info', $batch_id);
        if (validateisNumeric($folder_id)) {
            $res = array(
                'success' => true,
                'folder_id' => $folder_id,
                'message' => 'All is well'
            );
        } else {
            $res = array(
                'success' => false,
                'message' => 'Folder ID not valid. Please contact system admin!!'
            );
        }
        return response()->json($res);
    }

    public function getBeneficiaryFolderID(Request $req)
    {
        $girl_id = $req->input('girl_id');
        $folder_id = getParentFolderID('beneficiary_information', $girl_id);
        if (validateisNumeric($folder_id)) {
            $res = array(
                'success' => true,
                'folder_id' => $folder_id,
                'message' => 'All is well'
            );
        } else {
            $res = array(
                'success' => false,
                'message' => 'Folder ID not valid. Please contact system admin!!'
            );
        }
        return response()->json($res);
    }

    function getBeneficiariesSubModulesDMSFolderID(Request $req)
    {
        $girl_id = $req->input('parent_id');
        $sub_module_id = $req->input('sub_module_id');
        $parent_folder_id = DB::table('beneficiary_information')
            ->where('id', $girl_id)
            ->value('folder_id');
        if ($parent_folder_id == '' || $parent_folder_id == 0) {
            $res = array(
                'success' => false,
                'message' => 'Problem was encountered while fetching folder details relating to this batch number!! Please contact system admin.'
            );
            return response()->json($res);
        }
        try {
            $folder_id = getSubModuleFolderID($parent_folder_id, $sub_module_id);
            $res = array(
                'success' => true,
                'folder_id' => $folder_id,
                'message' => 'All is well'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getExaminationSchoolInfo(Request $req)
    {
        $school_id = $req->input('school_id');
        try {
            $school_info = DB::table('school_information')
                ->select('district_id', 'province_id', 'code')
                ->where('id', $school_id)
                ->first();
            if (!is_null($school_info)) {
                $results = array(
                    'district_id' => $school_info->district_id,
                    'province_id' => $school_info->province_id,
                    'school_code' => $school_info->code
                );
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'Problem encountered while fetching school info!!'
                );
                return response()->json($res);
            }
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => 'All is well'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function savePromotionDetails(Request $req)
    {
        $is_submit = $req->input('is_submit');
        $source = $req->input('source');//1=data entry, 2=approvals
        $girl_id = $req->input('girl_id');
        $girl_promotion_id = $req->input('girl_promotion_id');
        $promotion_year = $req->input('promotion_year');
        $exam_no = $req->input('exam_no');
        $qualified = $req->input('qualified');
        $school_id = $req->input('school_id');
        $exam_grade = $req->input('exam_grade');
        $grade = $req->input('grade');
        $school_status = $req->input('beneficiary_school_status');
        $user_id = \Auth::user()->id;
        $year = date('Y');
        $gradenine_promotion_status = 2;
        if ($source == 2) {//approvals
            $stage = 2;
        } else {
            $stage = 1;
            if ($is_submit == 1) {
                $stage = 2;
            }
        }
        if ($qualified == 1) {
            $gradenine_promotion_status = 1;
        }
        try {
            //validations
            if ($exam_no == '') {
                $res = array(
                    'success' => false,
                    'message' => 'Please enter exam number of the learner!!'
                );
                return response()->json($res);
            }
            if ($qualified == 1 || $qualified == 2) {
                $params = array(
                    'girl_id' => $girl_id,
                    'qualified' => $qualified,
                    'school_id' => $school_id,
                    'to_grade' => $grade,
                    'exam_no' => $exam_no,
                    'promotion_year' => $promotion_year,
                    'beneficiary_school_status' => $school_status,
                    'stage' => $stage
                );
                if ($school_id == '' || $grade == '' || $school_status == '') {
                    $res = array(
                        'success' => false,
                        'message' => 'Fill all the mandatory fields(*)!!'
                    );
                    return response()->json($res);
                }
                if ($exam_grade < 7) {
                    $res = array(
                        'success' => false,
                        'message' => 'Incorrect examination grade!!'
                    );
                    return response()->json($res);
                }
                if ($grade < 4) {
                    $res = array(
                        'success' => false,
                        'message' => 'Incorrect grade assigned to the learner!!'
                    );
                    return response()->json($res);
                }

                // if ($exam_grade == 7 && $grade != 8) {
                //     $res = array(
                //         'success' => false,
                //         'message' => 'Incorrect grade assigned to the learner!!'
                //     );
                //     return response()->json($res);
                // }

                if ($exam_grade != 9 && $exam_grade != 7) {
                    $res = array(
                        'success' => false,
                        'message' => 'Incorrect exam grade assigned to the learner!!'
                    );
                    return response()->json($res);
                }
                // if ($grade != 10 && $grade != 9 && $grade != 8) {//take care of repeaters
                //     $res = array(
                //         'success' => false,
                //         'message' => 'Incorrect grade assigned to the learner!!'
                //     );
                //     return response()->json($res);
                // }
            } else {
                $params = array(
                    'girl_id' => $girl_id,
                    'qualified' => $qualified,
                    'school_id' => '',
                    'to_grade' => '',
                    'exam_no' => $exam_no,
                    'promotion_year' => $promotion_year,
                    'beneficiary_school_status' => '',
                    'stage' => $stage
                );
            }
            $exists = DB::table('gradenine_promotions')
                ->where('girl_id', $girl_id)
                ->where('promotion_year', $promotion_year)
                ->max('id');
            if (isset($exists) && $exists != '' && is_numeric($exists)) {
                $params['updated_at'] = Carbon::now();
                $params['created_by'] = $user_id;
                DB::table('gradenine_promotions')
                    ->where('id', $exists)
                    ->update($params);
                DB::table('beneficiary_information')
                    ->where('id', $girl_id)
                    ->update(array('grade9_exam_no' => $exam_no));
            } else {
                $prev_data = DB::table('beneficiary_information')
                    ->select('id as girl_id', 'school_id', 'beneficiary_school_status', 'current_school_grade')
                    ->where('id', $girl_id)
                    ->get();
                $prev_data = convertStdClassObjToArray($prev_data);
                $prev_id = DB::table('gradenine_promotions_prev')
                    ->insertGetId($prev_data[0]);
                $params['prev_record_id'] = $prev_id;
                $params['created_at'] = Carbon::now();
                $params['created_by'] = $user_id;
                insertRecord('gradenine_promotions', $params, $user_id);
                DB::table('beneficiary_information')
                    ->where('id', $girl_id)
                    ->update(array('grade9_exam_no' => $exam_no));
            }
            DB::table('grade_nines_for_promotion')
                ->where('id', $girl_promotion_id)
                ->update(array('stage' => $stage));
            $res = array(
                'success' => true,
                'message' => 'Details saved successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return response()->json($res);
    }

    function getGirlPromotionInfo(Request $req)
    {
        $girl_id = $req->input('girl_id');
        $promotion_year = $req->input('promotion_year');
        $where = array(
            'gradenine_promotions.girl_id' => $girl_id,
            'gradenine_promotions.promotion_year' => $promotion_year
        );
        try {
            $qry = DB::table('gradenine_promotions')
                ->join(DB::raw("(select max(id) as maxid from gradenine_promotions kip group by kip.girl_id) as b"), 'b.maxid', '=', 'gradenine_promotions.id')
                ->leftJoin('school_information', 'school_information.id', '=', 'gradenine_promotions.school_id')
                ->select('gradenine_promotions.*', 'school_information.province_id', 'school_information.district_id', 'school_information.id as school_id', 'school_information.name', 'school_information.code')
                ->where($where);
            $data = $qry->first();
            if (!is_null($data)) {
                $results = array(
                    'girl_id' => $data->girl_id,
                    'qualified' => $data->qualified,
                    'province_id' => $data->province_id,
                    'district_id' => $data->district_id,
                    'school_id' => $data->school_id,
                    'emis_code' => $data->code,
                    'grade' => $data->to_grade,
                    'beneficiary_school_status' => $data->beneficiary_school_status
                );
                $res = array(
                    'success' => true,
                    'message' => 'Data fetched successfully',
                    'results' => $results
                );
            } else {
                $res = array(
                    'success' => true,
                    'message' => 'No matching info found!!',
                    'results' => array()
                );
            }
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

   public function gradeNinePromotionTransitioning(Request $req)
    {
        try {
            $stage = $req->input('stage');
            $user_id = $this->user_id;
            $where = array(
                'beneficiary_status' => 4,
                'current_school_grade' => 9
            );
            $res = array();
            DB::transaction(function () use ($stage, $user_id, $where, &$res) {
                if ($stage == 1) {
                    DB::table('beneficiary_information as t1')
                        ->join('grade_nines_for_promotion as t2', 't1.id', '=', 't2.girl_id')
                        ->join('gradenine_promotions', function ($join) {
                            $join->on('t2.girl_id', '=', 'gradenine_promotions.girl_id')
                                ->on('t2.promotion_year', '=', 'gradenine_promotions.promotion_year');
                        })
                        ->where($where)
                        ->where('t2.stage', 1)
                        ->whereNotIn('enrollment_status', array(2, 4))
                        ->update(array('t2.stage' => 2));
                } else if ($stage == 2) {
                    //todo: Log grade transitioning for Passed==promoted (proceeded and repeated) before making an update
                    $year = date('Y');
                    $reason = 'Grade nine promotion of ';
                    $log_qry = DB::table('gradenine_promotions as t1')
                        ->select(DB::raw("t2.id as girl_id,$year as year,t1.school_id,CONCAT_WS(' ','$reason',t1.promotion_year) as reason,
                                          0 as created_by, t1.to_grade as grade"))
                        ->join('beneficiary_information as t2', 't1.girl_id', '=', 't2.id')
                        ->join('grade_nines_for_promotion as t3', function ($join) {
                            $join->on('t1.girl_id', '=', 't3.girl_id')
                                ->on('t1.promotion_year', '=', 't3.promotion_year');
                        })
                        ->whereIn('t1.qualified', array(1, 2))
                        ->where('t3.stage', 2)
                        ->where($where);
                    $grade_transition_params = $log_qry->get();
                    $grade_transition_params = convertStdClassObjToArray($grade_transition_params);
                    DB::table('beneficiary_grade_logs')->insert($grade_transition_params);
                    //logBeneficiaryGradeTransitioning($girl_id, $to_grade, $school_id, $user_id);
                    //todo: Passed==promoted (proceeded and repeated)
                    DB::table('gradenine_promotions as t1')
                        //->join(DB::raw("(select max(id) as maxid from gradenine_promotions kip group by kip.girl_id) as b"), 'b.maxid', '=', 't1.id')
                        ->join('beneficiary_information as t2', 't1.girl_id', '=', 't2.id')
                        //->join('grade_nines_for_promotion as t3', 't1.girl_id', '=', 't3.girl_id')
                        ->join('grade_nines_for_promotion as t3', function ($join) {
                            $join->on('t1.girl_id', '=', 't3.girl_id')
                                ->on('t1.promotion_year', '=', 't3.promotion_year');
                        })
                        ->whereIn('t1.qualified', array(1, 2))
                        ->where('t3.stage', 2)
                        ->where($where)
                        ->update(array(
                            't3.stage' => 3,
                            't2.current_school_grade' => DB::raw('t1.to_grade'),
                            't2.school_id' => DB::raw('t1.school_id'),
                            't2.under_promotion' => 0,
                            't2.beneficiary_school_status' => DB::raw('t1.beneficiary_school_status'),));
                    //todo: Unknown
                    DB::table('gradenine_promotions as t1')
                        ->join('beneficiary_information as t2', 't1.girl_id', '=', 't2.id')
                        ->join('grade_nines_for_promotion as t3', function ($join) {
                            $join->on('t1.girl_id', '=', 't3.girl_id')
                                ->on('t1.promotion_year', '=', 't3.promotion_year');
                        })
                        ->whereIn('t1.qualified', array(4))
                        ->where('t3.stage', 2)
                        ->where($where)
                        ->update(array('t3.stage' => 3, 't2.under_promotion' => 0));
                    //todo: Failed==suspended/Stopped school
                    $transition_params = array();
                    $failed_girls_ids = DB::table('gradenine_promotions as t1')
                        //->join(DB::raw("(select max(id) as maxid from gradenine_promotions kip group by kip.girl_id) as b"), 'b.maxid', '=', 't1.id')
                        ->join('beneficiary_information as t2', 't1.girl_id', '=', 't2.id')
                        //->join('grade_nines_for_promotion as t3', 't1.girl_id', '=', 't3.girl_id')
                        ->join('grade_nines_for_promotion as t3', function ($join) {
                            $join->on('t1.girl_id', '=', 't3.girl_id')
                                ->on('t1.promotion_year', '=', 't3.promotion_year');
                        })
                        ->select('t2.id', 't2.enrollment_status', 't1.id as promotion_id', 't3.id as girl_promotion_id', 't3.promotion_year')
                        ->whereIn('t1.qualified', array(0, 3))//take care of prev implementation
                        ->where('t3.stage', 2)
                        ->where($where)
                        ->get();
                    $update_ids = array();
                    $promotions_ids = array();
                    foreach ($failed_girls_ids as $failed_girl_id) {
                        $update_ids[] = array(
                            'id' => $failed_girl_id->id
                        );
                        $promotions_ids[] = array(
                            'id' => $failed_girl_id->girl_promotion_id
                        );
                        $transition_params[] = array(
                            'girl_id' => $failed_girl_id->id,
                            'from_stage' => $failed_girl_id->enrollment_status,
                            'to_stage' => 2,
                            'reason' => 'Failed grade 9 examinations [promotion year ' . $failed_girl_id->promotion_year . ']',
                            'author' => $user_id,
                            'created_by' => $user_id,
                            'created_at' => Carbon::now()
                        );
                    }
                    $update_ids = convertAssArrayToSimpleArray($update_ids, 'id');
                    $promotions_ids = convertAssArrayToSimpleArray($promotions_ids, 'id');
                    DB::table('beneficiary_information')
                        ->whereIn('id', $update_ids)
                        ->update(array('under_promotion' => 0, 'enrollment_status' => 2));
                    DB::table('grade_nines_for_promotion')
                        ->whereIn('id', $promotions_ids)
                        ->update(array('stage' => 3));
                    DB::table('beneficiaries_transitional_report')
                        ->insert($transition_params);
                }
                $res = array(
                    'success' => true,
                    'message' => 'Request executed successfully!!'
                );
            }, 5);
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return response()->json($res);
    }
    public function gradeNinePromotionTransitioningServer(Request $req)
    {

        try {
            $stage = $req->input('stage');
            $user_id = $this->user_id;
            $where = array(
                'beneficiary_status' => 4,
                'current_school_grade' => 9
            );
            $res = array();

            DB::transaction(function () use ($stage, $user_id, $where, &$res) {
                if ($stage == 1) {
                    DB::table('beneficiary_information as t1')
                        ->join('grade_nines_for_promotion as t2', 't1.id', '=', 't2.girl_id')
                        ->join('gradenine_promotions', function ($join) {
                            $join->on('t2.girl_id', '=', 'gradenine_promotions.girl_id')
                                ->on('t2.promotion_year', '=', 'gradenine_promotions.promotion_year');
                        })
                        ->where($where)
                        ->where('t2.stage', 1)
                        ->whereNotIn('enrollment_status', array(2, 4))
                        ->update(array('t2.stage' => 2));
                } else if ($stage == 2) {
                    //todo: Log grade transitioning for Passed==promoted (proceeded and repeated) before making an update
                    $year = date('Y');
                    $reason = 'Grade nine promotion of ';
                    $log_qry = DB::table('gradenine_promotions as t1')
                        ->select(DB::raw("t2.id as girl_id,$year as year,t1.school_id,CONCAT_WS(' ','$reason',t1.promotion_year) as reason,
                                          0 as created_by, t1.to_grade as grade"))
                        ->join('beneficiary_information as t2', 't1.girl_id', '=', 't2.id')
                        ->join('grade_nines_for_promotion as t3', function ($join) {
                            $join->on('t1.girl_id', '=', 't3.girl_id')
                                ->on('t1.promotion_year', '=', 't3.promotion_year');
                        })
                        ->whereIn('t1.qualified', array(1, 2))
                        ->where('t3.stage', 2)
                        ->where($where);
                    $grade_transition_params = $log_qry->get();
                    $grade_transition_params = convertStdClassObjToArray($grade_transition_params);
                    //$res=DB::table('beneficiary_grade_logs')->insert($grade_transition_params);
                    //updated feb 2024 frank&maureen chunk
                    $chunkSize=1000;
                    $res = collect($grade_transition_params)->chunk($chunkSize)->each(function ($chunk) {
                        DB::table('beneficiary_grade_logs')->insert($chunk->toArray());
                    });
                    //logBeneficiaryGradeTransitioning($girl_id, $to_grade, $school_id, $user_id);
                    //todo: Passed==promoted (proceeded and repeated)
                    $updateData = [
                                't3.stage' => 3,
                                't2.current_school_grade' => DB::raw('t1.to_grade'),
                                't2.school_id' => DB::raw('t1.school_id'),
                                't2.under_promotion' => 0,
                                't2.beneficiary_school_status' => DB::raw('t1.beneficiary_school_status')
                        ];

                       
                    $mainQry= DB::table('gradenine_promotions as t1')
                                ->join('beneficiary_information as t2', 't1.girl_id', '=', 't2.id')                 
                                ->join('grade_nines_for_promotion as t3', function ($join) {
                                    $join->on('t1.girl_id', '=', 't3.girl_id')
                                        ->on('t1.promotion_year', '=', 't3.promotion_year');
                                     })
                                ->whereIn('t1.qualified', array(1, 2))
                                ->where('t3.stage', 2)
                                ->where($where);                               
                    $SubQuery = clone $mainQry;
                    $mainQry->orderBy('t2.beneficiary_id')->chunk($chunkSize, function ($records) use ($updateData, $SubQuery ) {
                                    foreach ($records as $record) {  
                                         $SubQuery->where('beneficiary_id', $record->beneficiary_id)
                                                  ->update($updateData);
                                    }
                                });


                    //todo: Unknown
                     $updateUnknown = [
                                't3.stage' => 3,
                                't2.under_promotion' => 0,
                        ];
                    $unknownQry=DB::table('gradenine_promotions as t1')
                                ->join('beneficiary_information as t2', 't1.girl_id', '=', 't2.id')
                                ->join('grade_nines_for_promotion as t3', function ($join) {
                                    $join->on('t1.girl_id', '=', 't3.girl_id')
                                        ->on('t1.promotion_year', '=', 't3.promotion_year');
                                })
                                ->whereIn('t1.qualified', array(4))
                                ->where('t3.stage', 2)
                                ->where($where);
                     $SubUnknownQuery = clone $unknownQry;
                     $unknownQry->orderBy('t2.beneficiary_id')->chunk($chunkSize, function ($recordsUnknown) use ($updateUnknown, $SubUnknownQuery )  {
                                    foreach ($recordsUnknown as $recordUnknown) {      
                                         $SubUnknownQuery->where('beneficiary_id', $recordUnknown->beneficiary_id)
                                                  ->update($updateUnknown);
                                    }
                                });

                    //todo: Failed==suspended/Stopped school
                    $transition_params = array();
                    $failed_girls_ids = DB::table('gradenine_promotions as t1')
                        ->join('beneficiary_information as t2', 't1.girl_id', '=', 't2.id')
                        ->join('grade_nines_for_promotion as t3', function ($join) {
                            $join->on('t1.girl_id', '=', 't3.girl_id')
                                ->on('t1.promotion_year', '=', 't3.promotion_year');
                        })
                        ->select('t2.id', 't2.enrollment_status', 't1.id as promotion_id', 't3.id as girl_promotion_id', 't3.promotion_year')
                        ->whereIn('t1.qualified', array(0, 3))//take care of prev implementation
                        ->where('t3.stage', 2)
                        ->where($where)
                        ->get();

                    $update_ids = array();
                    $promotions_ids = array();
                    foreach ($failed_girls_ids as $failed_girl_id) {
                        $update_ids[] = array(
                            'id' => $failed_girl_id->id
                        );
                        $promotions_ids[] = array(
                            'id' => $failed_girl_id->girl_promotion_id
                        );
                        $transition_params[] = array(
                            'girl_id' => $failed_girl_id->id,
                            'from_stage' => $failed_girl_id->enrollment_status,
                            'to_stage' => 2,
                            'reason' => 'Failed grade 9 examinations [promotion year ' . $failed_girl_id->promotion_year . ']',
                            'author' => $user_id,
                            'created_by' => $user_id,
                            'created_at' => Carbon::now()
                        );
                    }
                    $update_ids = convertAssArrayToSimpleArray($update_ids, 'id');
                    $promotions_ids = convertAssArrayToSimpleArray($promotions_ids, 'id');
                    $updateBenInfo = [
                          'under_promotion' => 0, 
                          'enrollment_status' => 2
                    ];
                    $BenInfoQry=DB::table('beneficiary_information')
                                ->whereIn('id', $update_ids);
                    $SubBenInfoQry = clone $BenInfoQry;
                    $BenInfoQry->orderBy('id')->chunk($chunkSize, function ($recordsBenInfo)use ($updateBenInfo, $SubBenInfoQry) {
                                    foreach ($recordsBenInfo as $recordBenInfo) {    
                                       $SubBenInfoQry->where('id', $recordBenInfo->id)
                                                  ->update($updateBenInfo);                                
                                      //  $recordBenInfo->update($updateBenInfo);
                                    }
                                });
                    $updateGrade = [
                            'stage' => 3
                    ];
                    $gradeQry=DB::table('grade_nines_for_promotion')
                            ->whereIn('id', $promotions_ids);                             
                    $SubgradeQry = clone $gradeQry;         
                    $gradeQry->orderBy('id')->chunk($chunkSize, function ($recordsGrade) use ($updateGrade, $SubgradeQry) {
                                foreach ($recordsGrade as $recordGrade) {  
                                   $SubgradeQry->where('id', $recordGrade->id)
                                                  ->update($updateGrade);                                     
                                  // $recordGrade->update($updateGrade);
                                }
                            });

                    collect($transition_params)->chunk($chunkSize)->each(function ($chunk) {
                        DB::table('beneficiaries_transitional_report')->insert($chunk->toArray());
                    });
                    // DB::table('beneficiaries_transitional_report')->orderBy('id')                     
                    //     ->chunk($chunkSize, function ($recordsTrans) use ($transition_params) {
                    //         foreach ($recordsTrans as $recordTrans) {                                    
                    //             $recordTrans->insert($transition_params);
                    //         }
                    //     });
                     }
                $res = array(
                    'success' => true,
                    'message' => 'Request executed successfully!!'
                );
            }, 5);
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return response()->json($res);
    }

    public function selectedGradeNinePromotionTransitioning(Request $req)
    {
        try {
            $selected2 = $req->input('selected2');
            $selected_ids2 = json_decode($selected2);
            DB::table('grade_nines_for_promotion')
                ->whereIn('id', $selected_ids2)
                ->update(array('stage' => 2));
            $res = array(
                'success' => true,
                'message' => 'Request executed successfully!!'
            );
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
        return response()->json($res);
    }

    public function selectedGradeNinePromotionApproval(Request $req)//hilla here
    {
        $res = array(
            'success' => false,
            'message' => 'Use the other option, this functionality has been disabled!!'
        );
        return response()->json($res);
        exit();
        $res = array();
        DB::transaction(function () use ($req, &$res) {
            try {
                $post_data = $req->input();
                unset($post_data['_token']);
                $user_id = $this->user_id;
                foreach ($post_data as $value) {
                    $qualified = $value['qualified'];
                    $girl_id = $value['girl_id'];
                    $id = $value['id'];
                    $promotion_year = $value['promotion_year'];
                    if ($qualified == 1 || $qualified == 2) {
                        $to_grade = $value['grade'];
                        $school_id = $value['school_id'];
                        DB::table('beneficiary_information')
                            ->where('id', $girl_id)
                            ->update(array('under_promotion' => 0, 'current_school_grade' => $to_grade));
                        logBeneficiaryGradeTransitioning($girl_id, $to_grade, $school_id, $user_id);
                        // DB::table('gradenine_promotions')->where('id', $id)->update(array('is_approved' => 1));
                    } else {
                        $current_status = $value['enrollment_status'];
                        $transition_params[] = array(
                            'girl_id' => $girl_id,
                            'from_stage' => $current_status,
                            'to_stage' => 2,
                            'reason' => 'Failed grade 9 examinations [promotion year ' . $promotion_year . ']',
                            'author' => $user_id,
                            'created_by' => $user_id,
                            'created_at' => Carbon::now()
                        );
                        DB::table('beneficiary_information')
                            ->where('id', $girl_id)
                            ->update(array('under_promotion' => 0, 'enrollment_status' => 2));
                        DB::table('beneficiaries_transitional_report')
                            ->insert($transition_params);
                        // DB::table('gradenine_promotions')->where('id', $id)->update(array('is_approved' => 1));
                    }
                    DB::table('gradenine_promotions')->where('id', $id)->update(array('is_approved' => 1));
                }
                $res = array(
                    'success' => true,
                    'message' => 'Request executed successfully!!'
                );
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
        }, 5);
        return response()->json($res);
    }

    public function approveGradeNineBeneficiary(Request $req)
    {
        try {
            $girl_id = $req->input('girl_id');
            $promotion_year = $req->input('promotion_year');
            $girl_promotion_id = $req->input('girl_promotion_id');
            $id = $req->input('id');
            $qualified = $req->input('qualified');
            $user_id = \Auth::user()->id;
            $current_status = DB::table('beneficiary_information')->where('id', $girl_id)->value('enrollment_status');
            if ($qualified == 1) {
                $info = DB::table('gradenine_promotions')
                    ->where('id', $id)
                    ->where('promotion_year', $promotion_year)
                    ->first();
                if (!is_null($info)) {
                    DB::table('beneficiary_information')
                        ->where('id', $girl_id)
                        ->update(array('under_promotion' => 0, 'current_school_grade' => $info->to_grade));
                    logBeneficiaryGradeTransitioning($girl_id, $info->to_grade, $info->school_id, $user_id);
                    DB::table('grade_nines_for_promotion')
                        ->where('id', $girl_promotion_id)
                        ->update(array('stage' => 3));
                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'Problem encountered while getting promotion details. Try again later!!'
                    );
                    return response()->json($res);
                }
            } else {
                DB::table('beneficiary_information')
                    ->where('id', $girl_id)
                    ->update(array('under_promotion' => 0, 'enrollment_status' => 2));
                $transition_params = array(
                    'girl_id' => $girl_id,
                    'from_stage' => $current_status,
                    'to_stage' => 2,
                    'reason' => 'Failed grade 9 examinations [promotion year ' . $promotion_year . ']',
                    'author' => $user_id,
                    'created_by' => $user_id,
                    'created_at' => Carbon::now()
                );
                DB::table('beneficiaries_transitional_report')
                    ->insert($transition_params);
                DB::table('grade_nines_for_promotion')
                    ->where('id', $girl_promotion_id)
                    ->update(array('stage' => 3));
            }
            $res = array(
                'success' => true,
                'message' => 'Request executed successfully!!'
            );
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
        return response()->json($res);
    }

    public function revokeGradeNinePromotion(Request $req)
    {
        $selected = $req->input('selected');
        $assigned_grade = $req->input('assigned_grade');
        $revoke_reason = $req->input('revoke_reason');
        $selected_records = json_decode($selected);
        $girl_ids = array();
        $promotion_ids = array();
        $insert_params = array();
        $user_id = \Auth::user()->id;
        DB::beginTransaction();
        try {
            foreach ($selected_records as $selected_record) {
                logBeneficiaryGradeTransitioning($selected_record->girl_id, $assigned_grade, $selected_record->school_id, $user_id, $revoke_reason);
                $girl_ids[] = array($selected_record->girl_id);
                $promotion_ids[] = array($selected_record->promotion_id);
                $insert_params[] = array(
                    'promotion_id' => $selected_record->promotion_id,
                    'assigned_grade' => $assigned_grade,
                    'revoke_reason' => $revoke_reason,
                    'action_by' => $user_id,
                    'created_by' => $user_id,
                    'created_on' => Carbon::now()
                );
            }
            DB::table('revoked_gradenine_promotions')
                ->insert($insert_params);
            DB::table('grade_nines_for_promotion')
                ->whereIn('id', $promotion_ids)
                ->update(array('status' => 3, 'stage' => 3, 'updated_by' => $user_id, 'updated_at' => Carbon::now()));
            DB::table('beneficiary_information')
                ->whereIn('id', $girl_ids)
                ->update(array('current_school_grade' => $assigned_grade, 'under_promotion' => 0, 'updated_by' => $user_id, 'updated_at' => Carbon::now()));
            DB::commit();
            $res = array(
                'success' => true,
                'message' => 'Request executed successfully!!'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        } catch (\Throwable $t) {
            DB::rollBack();
            $res = array(
                'success' => false,
                'message' => $t->getMessage()
            );
        }
        return response()->json($res);
    }

    function getSchoolMatchingGeneralSummary(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $data = DB::table('cwac')
                ->join('districts', 'cwac.district_id', '=', 'districts.id')
                ->join('beneficiary_information', function ($join) use ($batch_id) {
                    $join->on('beneficiary_information.cwac_id', '=', 'cwac.id')
                        ->where('beneficiary_information.category', 1)
                        ->where('beneficiary_information.verification_recommendation', 1)
                        ->where('batch_id', $batch_id);
                })
                ->leftJoin('school_matching_details', 'beneficiary_information.id', '=', 'school_matching_details.girl_id')
                ->select(DB::raw('cwac.district_id,cwac.name,districts.name as district_name, count(beneficiary_information.id) as total_count,
                                  SUM(IF(school_matching_details.id IS NOT NULL,1,0)) as passed_dataentry_count'))
                ->where(function ($query) {
                    $query->where('beneficiary_information.skip_matching', 0)
                        ->orWhereNull('beneficiary_information.skip_matching');
                })
                ->groupBy('beneficiary_information.cwac_id')
                ->get();
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'All is well'
            );
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
        return response()->json($res);
    }

    function getSchMatchingUserSummary(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $qry = DB::table('beneficiary_information')
                ->join('cwac', 'beneficiary_information.cwac_id', '=', 'cwac.id')
                ->join('districts', 'cwac.district_id', '=', 'districts.id')
                ->join('school_matching_details', 'beneficiary_information.id', '=', 'school_matching_details.girl_id')
                ->join('users', 'school_matching_details.created_by', '=', 'users.id')
                ->select(DB::raw('users.id as user_id,COUNT(DISTINCT(school_matching_details.girl_id)) as done_count,districts.name as district_name,users.first_name,users.last_name,users.email'))
                ->where('beneficiary_information.category', 1)
                ->where('beneficiary_information.batch_id', $batch_id)
                ->where(function ($query) {
                    $query->where('beneficiary_information.skip_matching', 0)
                        ->orWhereNull('beneficiary_information.skip_matching');
                })
                ->groupBy('school_matching_details.created_by')
                ->groupBy('districts.id');
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $results = decryptArray($data);
            $res = array(
                'success' => true,
                'message' => returnMessage($data),
                'results' => $results
            );
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
        return response()->json($res);
    }

    function getSchoolMatchingSummaryTotals(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $qry = DB::table('beneficiary_information')
                ->join('cwac', 'beneficiary_information.cwac_id', '=', 'cwac.id')
                ->join('districts', 'cwac.district_id', '=', 'districts.id')
                ->leftJoin('school_matching_details', 'beneficiary_information.id', '=', 'school_matching_details.girl_id')
                ->select(DB::raw('school_matching_details.beneficiary_school_status,COUNT(DISTINCT(beneficiary_information.beneficiary_id)) as total_count,SUM(IF(school_matching_details.id IS NOT NULL,1,0)) as passed_dataentry_count,districts.name as district_name'))
                ->where('beneficiary_information.category', 1)
                ->where(function ($query) {
                    $query->where('beneficiary_information.skip_matching', 0)
                        ->orWhereNull('beneficiary_information.skip_matching');
                })
                ->where('beneficiary_information.verification_recommendation', 1)
                ->where('beneficiary_information.batch_id', $batch_id)
                ->groupBy('cwac.district_id');
            $data = $qry->get();
            $res = array(
                'success' => true,
                'message' => returnMessage($data),
                'results' => $data
            );
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
        return response()->json($res);
    }

    function getSchoolMatchedGirls(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $grade = $req->input('grade');
            $qualification = $req->input('qualification');
            $district = $req->input('district');
            $qry = DB::table('beneficiary_information as t1')
                ->join('school_matching_details', 't1.id', '=', 'school_matching_details.girl_id')
                ->join('school_information', 'school_matching_details.school_id', '=', 'school_information.id')
                ->join('districts', 't1.district_id', '=', 'districts.id')
                ->join('districts as t4', 'school_information.district_id', '=', 't4.id')
                ->join('cwac', 't1.cwac_id', '=', 'cwac.id')
                ->join('users', 'school_matching_details.created_by', '=', 'users.id')
                ->join('beneficiary_statuses as t8', 't1.beneficiary_status', '=', 't8.id')
                ->select(DB::raw("t4.name,t1.id,t1.beneficiary_id,CASE WHEN decrypt(t1.first_name) IS NULL THEN t1.first_name ELSE decrypt(t1.first_name) END as first_name, CASE WHEN decrypt(t1.last_name) IS NULL THEN t1.last_name ELSE decrypt(t1.last_name) END as last_name,t1.dob,t1.highest_grade,t1.current_school_grade,
                                  CASE WHEN decrypt(users.first_name) IS NULL THEN users.first_name ELSE decrypt(users.first_name) END as user_first_name, CASE WHEN decrypt(users.last_name) IS NULL THEN users.last_name ELSE decrypt(users.last_name) END as user_last_name,school_information.name as school_name, districts.name as home_district, cwac.name as cwac_name,
                                  t8.name as ben_status"))
                ->where('t1.category', 1)
                ->where('t1.batch_id', $batch_id)
                ->where(function ($query) {
                    $query->where('t1.skip_matching', 0)
                        ->orWhereNull('t1.skip_matching');
                })
                ->groupBy('t1.id');
            if (isset($grade) && $grade != '') {
                $qry->where('current_school_grade', $grade);
            }
            if (isset($qualification) && $qualification != '') {
                $qry->where('t1.school_matching_status', $qualification);
            }
            if ($district != '') {
                $where_distr = explode('-', $district);
                if (isset($where_distr[1])) {
                    $qry->where('t4.name', $where_distr[1]);
                }
            }
            $data = $qry->get();
            $res = array(
                'success' => true,
                'message' => returnMessage($data),
                'results' => $data
            );
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
        return response()->json($res);
    }

    function getUnMatchedGirls(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $grade = $req->input('grade');
            $qry = DB::table('beneficiary_information as t1')
                ->join('districts', 't1.district_id', '=', 'districts.id')
                ->join('cwac', 't1.cwac_id', '=', 'cwac.id')
                ->join('beneficiary_statuses as t4', 't1.beneficiary_status', '=', 't4.id')
                ->whereNotIn('t1.id', function ($query) use ($batch_id) {
                    $query->select(DB::raw('school_matching_details.girl_id'))
                        ->from('school_matching_details')
                        ->join('beneficiary_information', 'school_matching_details.girl_id', '=', 'beneficiary_information.id')
                        ->groupBy('school_matching_details.girl_id')
                        ->where('beneficiary_information.batch_id', $batch_id)
                        ->where('beneficiary_information.category', 1);
                })
                ->select(DB::raw("t1.id,t1.beneficiary_id,CASE WHEN decrypt(t1.first_name) IS NULL THEN t1.first_name ELSE decrypt(t1.first_name) END as first_name, CASE WHEN decrypt(t1.last_name) IS NULL THEN t1.last_name ELSE decrypt(t1.last_name) END as last_name,t1.dob,t1.highest_grade,t1.current_school_grade,
                                  districts.name as home_district, cwac.name as cwac_name,
                                  t4.name as ben_status"))
                ->where('t1.category', 1)
                ->where('t1.verification_recommendation', 1)
                ->where('t1.batch_id', $batch_id)
                ->where(function ($query) {
                    $query->where('t1.skip_matching', 0)
                        ->orWhereNull('t1.skip_matching');
                });
            if (isset($grade) && $grade != '') {
                $qry->where('t1.highest_grade', $grade);
            }
            $data = $qry->get();
            $res = array(
                'success' => true,
                'message' => returnMessage($data),
                'results' => $data
            );
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
        return response()->json($res);
    }

    function getSchoolPlacementGeneralSummary(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $qry = DB::table('school_information')
                ->join('beneficiary_information', function ($join) use ($batch_id) {
                    $join->on('beneficiary_information.school_id', '=', 'school_information.id')
                        ->where('beneficiary_information.initial_category', 3)
                        ->where('beneficiary_information.verification_recommendation', 1)
                        ->where('batch_id', $batch_id);
                })
                ->leftJoin('school_placement_details', 'beneficiary_information.id', '=', 'school_placement_details.girl_id')
                ->join('districts', 'school_information.district_id', '=', 'districts.id')
                ->select(DB::raw('school_information.district_id,school_information.name,districts.name as district_name, count(beneficiary_information.id) as total_count,
                                  SUM(IF(school_placement_details.id IS NOT NULL,1,0)) as passed_dataentry_count'))
                ->groupBy('beneficiary_information.exam_school_id');
            $results = $qry->get();
            $res = array(
                'success' => true,
                'message' => returnMessage($results),
                'results' => $results
            );
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
        return response()->json($res);
    }

    function getSchPlacementUserSummary(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $qry = DB::table('beneficiary_information')
                ->join('school_information', 'beneficiary_information.exam_school_id', '=', 'school_information.id')
                ->join('districts', 'school_information.district_id', '=', 'districts.id')
                ->join('school_placement_details', 'beneficiary_information.id', '=', 'school_placement_details.girl_id')
                ->join('users', 'school_placement_details.created_by', '=', 'users.id')
                ->select(DB::raw('users.id as user_id,COUNT(DISTINCT(school_placement_details.girl_id)) as done_count,districts.name as district_name,users.first_name,users.last_name,users.email'))
                ->where('beneficiary_information.initial_category', 3)
                ->where('beneficiary_information.batch_id', $batch_id)
                ->groupBy('school_placement_details.created_by')
                ->groupBy('districts.id');
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $results = decryptArray($data);
            $res = array(
                'success' => true,
                'message' => returnMessage($results),
                'results' => $results
            );
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
        return response()->json($res);
    }

    function getSchoolPlacementSummaryTotals(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $qry = DB::table('beneficiary_information')
                ->join('school_information', 'beneficiary_information.exam_school_id', '=', 'school_information.id')
                ->join('districts', 'school_information.district_id', '=', 'districts.id')
                ->leftJoin('school_placement_details', 'beneficiary_information.id', '=', 'school_placement_details.girl_id')
                ->select(DB::raw('COUNT(DISTINCT(beneficiary_information.beneficiary_id)) as total_count,SUM(IF(school_placement_details.id IS NOT NULL,1,0)) as passed_dataentry_count,districts.name as district_name'))
                ->where('beneficiary_information.initial_category', 3)
                ->where('beneficiary_information.verification_recommendation', 1)
                ->where('beneficiary_information.batch_id', $batch_id)
                ->groupBy('school_information.district_id');
            $data = $qry->get();
            $res = array(
                'success' => true,
                'message' => returnMessage($data),
                'results' => $data
            );
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
        return response()->json($res);
    }

    function getSchPlacementEntryResultsSummary(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $qry = DB::table('school_information')
                ->join('districts', 'school_information.district_id', '=', 'districts.id')
                ->join('beneficiary_information', function ($join) use ($batch_id) {
                    $join->on('beneficiary_information.exam_school_id', '=', 'school_information.id')
                        ->where('beneficiary_information.initial_category', 3)
                        ->where('beneficiary_information.verification_recommendation', 1)
                        ->where('batch_id', $batch_id);
                })
                ->leftJoin('school_placement_details', 'beneficiary_information.id', '=', 'school_placement_details.girl_id')
                ->select(DB::raw('SUM(IF(beneficiary_information.category = 3 AND beneficiary_information.verification_recommendation = 1, 1, 0)) AS identified_girls_count,
                              SUM(IF(school_placement_status = 1 AND school_placement_details.id IS NOT NULL, 1, 0)) AS qualified_girls_count,
                              SUM(IF(school_placement_status = 2 AND school_placement_details.id IS NOT NULL, 1, 0)) AS unqualified_girls_count,
                              districts.name as district_name'))
                ->groupBy('districts.id');
            $results = $qry->get();
            $res = array(
                'success' => true,
                'message' => returnMessage($results),
                'results' => $results
            );
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
        return response()->json($res);
    }

    function getPlacedGirlsDetails(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $qualification = $req->input('qualification');
            $grade = $req->input('grade');
            $sch_district_id = $req->input('district');
            $qry = DB::table('beneficiary_information as t1')
                ->join('school_placement_details', 't1.id', '=', 'school_placement_details.girl_id')
                ->leftJoin('school_information', 'school_placement_details.school_id', '=', 'school_information.id')
                ->join('school_information as school', 't1.exam_school_id', '=', 'school.id')
                ->join('districts', 't1.district_id', '=', 'districts.id')
                ->join('cwac', 't1.cwac_id', '=', 'cwac.id')
                ->join('users', 'school_placement_details.created_by', '=', 'users.id')
                ->join('beneficiary_statuses as t8', 't1.beneficiary_status', '=', 't8.id')
                ->select(DB::raw("t1.id,t1.school_placement_status,t1.beneficiary_id,t1.household_id,t1.exam_school_id,t1.school_id,t1.cwac_id,t1.acc_id,t1.ward_id,t1.constituency_id,t1.district_id,t1.province_id,CASE WHEN decrypt(t1.first_name) IS NULL THEN t1.first_name ELSE decrypt(t1.first_name) END as first_name, CASE WHEN decrypt(t1.last_name) IS NULL THEN t1.last_name ELSE decrypt(t1.last_name) END as last_name,t1.dob,t1.highest_grade,t1.exam_grade,t1.current_school_grade,t1.category,t1.master_id,t1.beneficiary_status,t1.enrollment_status,t1.beneficiary_school_status,t1.exam_number,t1.enrollment_date,t1.batch_id,t1.folder_id,
                                  CASE WHEN decrypt(users.first_name) IS NULL THEN users.first_name ELSE decrypt(users.first_name) END as user_first_name, CASE WHEN decrypt(users.last_name) IS NULL THEN users.last_name ELSE decrypt(users.last_name) END as user_last_name,school_information.name as placed_school_name,school.name as exam_school_name,districts.name as home_district,cwac.name as cwac_name,
                                  t8.name as ben_status"))
                //->where('t1.category', 3)
                ->where('t1.batch_id', $batch_id);
            if (isset($qualification) && $qualification != '') {
                $qry->where('t1.school_placement_status', $qualification);
            }
            if (isset($grade) && $grade != '') {
                $qry->where('t1.exam_grade', $grade);
            }
            if (validateisNumeric($sch_district_id)) {
                $qry->where('school_information.district_id', $sch_district_id);
            }
            $qry->groupBy('t1.id');
            $data = $qry->get();
            $res = array(
                'success' => true,
                'message' => returnMessage($data),
                'results' => $data
            );
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
        return response()->json($res);
    }
    
    public function getUnPlacedGirlsDetails(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $grade = $req->input('grade');
            $qry = DB::table('beneficiary_information as t1')
                ->leftJoin('school_placement_details', 't1.id', '=', 'school_placement_details.girl_id')
                ->join('school_information', 't1.exam_school_id', '=', 'school_information.id')
                ->join('districts', 't1.district_id', '=', 'districts.id')
                ->join('cwac', 't1.cwac_id', '=', 'cwac.id')
                ->join('beneficiary_statuses as t6', 't1.beneficiary_status', '=', 't6.id')
                ->join('households', 't1.household_id', '=', 'households.id')
                ->join('districts as d1', 'school_information.district_id', '=', 'd1.id')
                ->join('batch_info', 't1.batch_id', '=', 'batch_info.id')
                ->LeftJoin('school_types', 'school_types.id', '=', 'school_information.school_type_id')
                ->leftJoin('beneficiary_school_statuses', 'beneficiary_school_statuses.id', '=', 't1.beneficiary_school_status')
                ->select(
                    DB::raw("t1.id,t1.school_placement_status,t1.beneficiary_id,t1.household_id,t1.exam_school_id,t1.school_id,
                        t1.cwac_id,t1.acc_id,t1.ward_id,t1.constituency_id,t1.district_id,t1.province_id,
                        CASE WHEN decrypt(t1.first_name) IS NULL THEN t1.first_name ELSE decrypt(t1.first_name) END as first_name, 
                        CASE WHEN decrypt(t1.last_name) IS NULL THEN t1.last_name ELSE decrypt(t1.last_name) END as last_name,
                        t1.dob,t1.highest_grade,t1.exam_grade,t1.current_school_grade,t1.category,t1.master_id,
                        t1.beneficiary_status,t1.enrollment_status,t1.beneficiary_school_status,t1.exam_number,t1.enrollment_date,
                        t1.batch_id,t1.folder_id,school_information.name as exam_school_name, districts.name as home_district, 
                        cwac.name as cwac_name,t6.name as ben_status,t1.relation_to_hhh,households.id as hh_id, households.hhh_fname,
                        households.hhh_lname,households.hhh_nrc_number,households.number_in_cwac,districts.name as district_name,
                        d1.name as school_district,batch_info.batch_no,school_information.name as school_name,
                        school_types.name as school_type,beneficiary_school_statuses.name as school_status"
                    )
                )
                //->where('t1.category', 3)
                ->where('t1.verification_recommendation', 1)
                //->whereNotIn('beneficiary_information.school_placement_status', array(1,2))
                ->whereNull('school_placement_details.id')
                ->where('t1.beneficiary_status', 8)
                ->where('t1.batch_id', $batch_id);
            if (isset($grade) && $grade != '') {
                $qry->where('t1.exam_grade', $grade);
            }
            $qry->groupBy('t1.id');
            $data = $qry->get();
            $res = array(
                'success' => true,
                'message' => returnMessage($data),
                'results' => $data
            );
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
        return response()->json($res);
    }

    public function getGirlsForAnalysis(Request $req)
    {
        $cwac_id = $req->input('cwac_id');
        $school_id = $req->input('school_id');
        $category_id = $req->input('category');
        $batch_id = $req->input('batch_id');
        $province_id = $req->input('province_id');
        $district_id = $req->input('district_id');
        $recommendation = $req->input('recommendation');

        $filter_category = $req->input('filter_category');
        $filter_grade = $req->input('filter_grade');

        $qry = DB::table('beneficiary_information as t1')
            ->join('beneficiary_categories', 't1.category', '=', 'beneficiary_categories.id')
            ->leftJoin('beneficiary_master_info', 't1.master_id', '=', 'beneficiary_master_info.id')
            ->leftJoin('households', 't1.household_id', '=', 'households.id')
            ->leftJoin('cwac', 't1.cwac_id', '=', 'cwac.id')
            ->leftJoin('school_information', 't1.school_id', '=', 'school_information.id')
            ->leftJoin('districts as d1', 'school_information.district_id', '=', 'd1.id')
            ->leftJoin('districts', 't1.district_id', '=', 'districts.id')
            ->select(DB::raw('t1.id,t1.beneficiary_id,t1.household_id,t1.exam_school_id,t1.school_id,t1.cwac_id,t1.acc_id,t1.ward_id,t1.constituency_id,t1.district_id,t1.province_id,CASE WHEN decrypt(t1.first_name) IS NULL THEN first_name ELSE decrypt(t1.first_name) END as first_name, CASE WHEN decrypt(t1.last_name) IS NULL THEN last_name ELSE decrypt(t1.last_name) END as last_name,t1.dob,t1.highest_grade,t1.exam_grade,t1.current_school_grade,t1.category,t1.master_id,t1.beneficiary_status,t1.enrollment_status,t1.beneficiary_school_status,t1.exam_number,t1.enrollment_date,t1.batch_id,t1.folder_id,
                              d1.name as school_district,school_information.name as school_name, cwac.name as cwac_name, beneficiary_categories.name as category_name, beneficiary_master_info.highest_grade as master_highest_grade, beneficiary_master_info.current_school_grade as master_current_school_grade, households.id as hh_id, households.hhh_fname, households.hhh_lname, households.hhh_nrc_number, households.number_in_cwac, districts.name as district_name'))
            ->where('t1.batch_id', $batch_id)
            ->where('t1.verification_recommendation', $recommendation);

        if ($category_id == 1) {
            $qry->where('t1.category', $category_id);
            if (isset($school_id) && $school_id != '') {
                $qry->where('t1.school_id', $school_id);
            }
            if (isset($cwac_id) && $cwac_id != '') {
                $qry->where('t1.cwac_id', $cwac_id);
            }
            if (isset($district_id) && $district_id != '') {
                $qry->where('t1.district_id', $district_id);
            }
            if (isset($province_id) && $province_id != '') {
                $qry->where('t1.province_id', $province_id);
            }
            if (isset($filter_grade) && $filter_grade != '') {
                $qry->where('t1.highest_grade', $filter_grade);
            }
        } else {
            $qry->whereIn('t1.category', array(2, 3));
            if (isset($school_id) && $school_id != '') {
                $qry->where('t1.school_id', $school_id);
            }
            if (isset($cwac_id) && $cwac_id != '') {
                $qry->where('t1.cwac_id', $cwac_id);
            }
            if (isset($district_id) && $district_id != '') {
                $qry->where('school_information.district_id', $district_id);
            }
            if (isset($province_id) && $province_id != '') {
                $qry->where('school_information.province_id', $province_id);
            }
            if (isset($filter_category) && $filter_category != '') {
                $qry->where('t1.category', $filter_category);
            }
            if (isset($filter_grade) && $filter_grade != '') {
                $qry->where('t1.exam_grade', $filter_grade);
            }
        }

        try {
            $data = $qry->get();
            $res = array(
                'success' => true,
                'message' => 'Records fetched successfully!!',
                'results' => $data
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage(),
                'results' => ''
            );
        }
        return response()->json($res);
    }

    public function getBeneficiaryAnnualPromotions()
    {
        try {
            $data = DB::table('ben_annual_promotions')->get();
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'All is well!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function saveBeneficiaryPromotionDetails(Request $req)
    {
        $year = $req->input('year');
        $description = $req->input('description');
        try {
            $params = array(
                'year' => $year,
                'description' => $description,
                'created_by' => \Auth::user()->id,
                'created_at' => Carbon::now()
            );
            $promotion_id = DB::table('ben_annual_promotions')->insertGetId($params);
            $res = array(
                'success' => true,
                'promotion_id' => $promotion_id,
                'message' => 'Details saved successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getBeneficiaryPromotionDetails(Request $req)
    {
        try {
            $promotion_id = $req->input('promotion_id');
            $filter = $req->input('filter');
            $start = $req->input('start');
            $limit = $req->input('limit');
            $whereClauses = array();
            $filter_string = '';
            if (isset($filter)) {
                $filters = json_decode($filter);
                if ($filters != NULL) {
                    foreach ($filters as $filter) {
                        switch ($filter->property) {
                            case 'beneficiary_id' :
                                $whereClauses[] = "beneficiary_information.beneficiary_id like '%" . ($filter->value) . "%'";
                                break;
                            case 'school' :
                                $whereClauses[] = "school_information.name like '%" . ($filter->value) . "%'";
                                break;
                        }
                    }
                    $whereClauses = array_filter($whereClauses);
                }
                if (!empty($whereClauses)) {
                    $filter_string = implode(' AND ', $whereClauses);
                }
            }
            $qry = DB::table('beneficiary_information')
                ->join('ben_annual_promotion_details', function ($join) use ($promotion_id) {
                    $join->on('beneficiary_information.id', '=', 'ben_annual_promotion_details.girl_id')
                        ->on('ben_annual_promotion_details.promotion_id', '=', DB::raw($promotion_id));
                })
                ->join('school_information', 'beneficiary_information.school_id', '=', 'school_information.id')
                //->join('users', 'ben_annual_promotion_details.created_by', '=', 'users.id')
                ->select(DB::raw('ben_annual_promotion_details.*,beneficiary_information.beneficiary_id,school_information.name as school'));
            //->select('ben_annual_promotion_details.*', 'beneficiary_information.beneficiary_id','school_information.name as school');
            if ($filter_string != '') {
                $qry->whereRAW($filter_string);
            }
            $total = $qry->count();
            $qry->offset($start)
                ->limit($limit);
            $data = $qry->get();
            $res = array(
                'success' => true,
                'totalCount' => $total,
                'results' => $data,
                'message' => 'All is well!!'
            );
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
        return response()->json($res);
    }

    public function processBeneficiaryPromotions(Request $req)
    {
        $promotion_id = $req->input('promotion_id');
        $year = $req->input('promotion_year');
        $month = date('m');
        $res = array();
        $promotion_month = DB::table('months')
            ->where('is_promotion', 1)
            ->value('id');
        $promotion_month = (int)$promotion_month;
        if (isset($promotion_month) && $promotion_month != '') {
            if ($promotion_month != $month) {
                $res = array(
                    'success' => false,
                    'message' => 'Promotion only happens in the specified month of the year!!'
                );
                return response()->json($res);
            }
        }
        DB::transaction(function () use ($promotion_id, $year, $month, &$res) {
            try {
                $promotion_data = DB::table('beneficiary_information')
                    ->select(DB::raw("id as girl_id,current_school_grade as from_grade,current_school_grade+1 as to_grade,school_id,$promotion_id as promotion_id,1 as status"))
                    ->where('enrollment_status', 1)
                    ->whereIn('current_school_grade', array(8, 9, 10, 11))
                    ->get();
                $promotion_data = convertStdClassObjToArray($promotion_data);
                $grade_log_data = DB::table('beneficiary_information')
                    ->select(DB::raw("id as girl_id,current_school_grade+1 as grade,school_id,$year as year"))
                    ->where('enrollment_status', 1)
                    ->whereIn('current_school_grade', array(8, 9, 10, 11))
                    ->get();
                $grade_log_data = convertStdClassObjToArray($grade_log_data);
                DB::table('beneficiary_information')
                    ->where('enrollment_status', 1)
                    ->where('current_school_grade', 12)
                    ->update(array('enrollment_status' => 4));
                DB::table('beneficiary_information')
                    ->where('enrollment_status', 1)
                    ->whereIn('current_school_grade', array(8, 9, 10, 11))
                    ->update(array('current_school_grade' => DB::raw('current_school_grade+1')));
                $size = 100;
                $Promo_chunks = array_chunk($promotion_data, $size);
                foreach ($Promo_chunks as $Promo_chunk) {
                    DB::table('ben_annual_promotion_details')->insert($Promo_chunk);
                }
                $log_chunks = array_chunk($grade_log_data, $size);
                foreach ($log_chunks as $log_chunk) {
                    DB::table('beneficiary_grade_logs')->insert($log_chunk);
                }
                DB::table('ben_annual_promotions')
                    ->where('id', $promotion_id)
                    ->update(array('status' => 1));
                $res = array(
                    'success' => true,
                    'message' => 'Promotions processed successfully!!'
                );
            } catch (\Exception $e) {
                $res = array(
                    'success' => false,
                    'message' => $e->getMessage()
                );
            } catch (\Throwable $throwable) {
                $res = array(
                    'success' => false,
                    'message' => $throwable->getMessage()
                );
            }
        }, 5);
        return response()->json($res);
    }

    public function undoBeneficiaryPromotions(Request $req)
    {
        $year = $req->input('year');
        $ben_info = $req->input('ben_info');
        $ben_info = json_decode($ben_info);
        $res = array();
        $user_id = \Auth::user()->id;
        DB::transaction(function () use ($ben_info, &$res, $user_id, $year) {
            try {
                foreach ($ben_info as $key => $value) {
                    $promotion_status = $ben_info[$key]->status;
                    if ($promotion_status != 2) {//if reversed already then skip
                        DB::table('ben_annual_promotion_details')
                            ->where('id', $ben_info[$key]->id)
                            ->update(array('status' => 2, 'updated_by' => $user_id));
                        DB::table('beneficiary_information')
                            ->where('id', $ben_info[$key]->girl_id)
                            ->update(array('current_school_grade' => $ben_info[$key]->from_grade, 'updated_at' => Carbon::now(), 'updated_by' => $user_id));
                        logBeneficiaryGradeTransitioning($ben_info[$key]->girl_id, $ben_info[$key]->to_grade, $ben_info[$key]->school_id, $user_id);
                    }
                }
                $res = array(
                    'success' => true,
                    'message' => 'Request executed successfully!!'
                );
            } catch (\Exception $e) {
                $res = array(
                    'success' => false,
                    'message' => $e->getMessage()
                );
            }
        }, 5);
        return response()->json($res);
    }

    public function redoBeneficiaryPromotions(Request $req)
    {
        $year = $req->input('year');
        $ben_info = $req->input('ben_info');
        $ben_info = json_decode($ben_info);
        $res = array();
        $user_id = \Auth::user()->id;
        DB::transaction(function () use ($ben_info, &$res, $user_id, $year) {
            try {
                foreach ($ben_info as $key => $value) {
                    $promotion_status = $ben_info[$key]->status;
                    if ($promotion_status == 2) {//if confirmed already then skip
                        DB::table('ben_annual_promotion_details')
                            ->where('id', $ben_info[$key]->id)
                            ->update(array('status' => 3, 'updated_by' => $user_id));
                        DB::table('beneficiary_information')
                            ->where('id', $ben_info[$key]->girl_id)
                            ->update(array('current_school_grade' => $ben_info[$key]->to_grade, 'updated_at' => Carbon::now(), 'updated_by' => $user_id));
                        logBeneficiaryGradeTransitioning($ben_info[$key]->girl_id, $ben_info[$key]->to_grade, $ben_info[$key]->school_id, $user_id);
                    }
                }
                $res = array(
                    'success' => true,
                    'message' => 'Request executed successfully!!'
                );
            } catch (\Exception $e) {
                $res = array(
                    'success' => false,
                    'message' => $e->getMessage()
                );
            }
        }, 5);
        return response()->json($res);
    }

    public function getGradeRepeaters(Request $req)
    {
        try {
            $qry = DB::table('beneficiary_grade_logs as t1')
                ->join('beneficiary_information as t2', 't1.girl_id', '=', 't2.id')
                ->join('beneficiary_enrollement_statuses as t3', 't2.enrollment_status', '=', 't3.id')
                ->leftJoin('beneficiaries_transitional_report as t4', function ($join) {
                    $join->on('t2.id', '=', 't4.girl_id')
                        ->on('t4.to_stage', '=', DB::raw(2));
                })
                ->select(DB::raw('t1.id,t2.enrollment_status,COUNT(DISTINCT(t4.id)) as suspension_count,t3.name as enrollment_status_name,t1.grade,t1.year,t1.girl_id,count(DISTINCT(t1.year)) as times,t2.beneficiary_id'))
                ->groupBy('t1.girl_id')
                ->groupBy('t1.grade')
                ->havingRaw('COUNT(DISTINCT(t1.year)) > 1');
            $data = $qry->get();
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'All is well!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getBeneficiaryGradeRepetitionDetails(Request $req)
    {
        $girl_id = $req->input('girl_id');
        $grade = $req->input('grade');
        try {
            $where = array(
                'girl_id' => $girl_id,
                'grade' => $grade
            );
            $qry = DB::table('beneficiary_grade_logs')
                ->join('school_information', 'beneficiary_grade_logs.school_id', '=', 'school_information.id')
                ->select('beneficiary_grade_logs.*', 'school_information.name as school')
                ->where($where);
            $data = $qry->get();
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'All is well!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function sendBeneficiarySuspensionRequest(Request $req)
    {
        $girl_id = $req->input('girl_id');
        $system_reason = $req->input('system_reason');
        $user_reason = $req->input('user_reason');
        $reason_id = $req->input('sus_reason');
        $user_id = \Auth::user()->id;
        $res = array();
        $params = array(
            'girl_id' => $girl_id,
            'system_reason' => $system_reason,
            'user_reason' => $user_reason,
            'request_by' => $user_id,
            'created_by' => $user_id,
            'reason_id' => $reason_id,
            'created_at' => Carbon::now(),
            'request_date' => Carbon::now(),
            'stage' => 1
        );
        $transitional_params = array(
            'girl_id' => $girl_id,
            'from_stage' => '',
            'to_stage' => 5,
            'reason' => $user_reason,
            'author' => $user_id,
            'created_at' => Carbon::now(),
            'created_by' => $user_id
        );
        DB::transaction(function () use (&$res, $girl_id, $params, $transitional_params) {
            try {
                DB::table('suspension_requests')->insert($params);
                DB::table('beneficiaries_transitional_report')->insert($transitional_params);
                DB::table('beneficiary_information')
                    ->where('id', $girl_id)
                    ->update(array('enrollment_status' => 5));
                $res = array(
                    'success' => true,
                    'message' => 'Request executed successfully!!'
                );
            } catch (\Exception $e) {
                $res = array(
                    'success' => false,
                    'message' => $e->getMessage()
                );
            }
        }, 5);
        return response()->json($res);
    }

    function getOutSchDetailedAnalysisSummary(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $data = DB::table('cwac')
            ->join('districts', 'cwac.district_id', '=', 'districts.id')
            ->join('beneficiary_information', function ($join) use ($batch_id) {
                $join->on('beneficiary_information.cwac_id', '=', 'cwac.id')
                    ->where('beneficiary_information.category', 1)
                    ->where('batch_id', $batch_id);
            })
            ->select(DB::raw('SUM(IF(beneficiary_information.category = 1, 1, 0)) AS identified_girls_count,
                              SUM(IF(beneficiary_status =3 AND verification_recommendation=1 , 1, 0)) AS recommended_girls_count,
                              SUM(IF(beneficiary_status =3 AND verification_recommendation=2 , 1, 0)) AS unrecommended_girls_count,
                              SUM(IF(beneficiary_status =3 AND verification_recommendation=3 , 1, 0)) AS notfound_girls_count,
                              SUM(IF(beneficiary_status =2 , 1, 0)) AS verified_notsubmitted_count,
                              SUM(IF(beneficiary_status =8 , 1, 0)) AS forwarded_sch_placement_count,
                              SUM(IF(beneficiary_status =4 , 1, 0)) AS forwarded_letters_count,
                              SUM(IF(beneficiary_status =6 , 1, 0)) AS forwarded_followups_count,
                              cwac.*,districts.name as district_name'))
            ->groupBy('cwac.id')
            ->get();
        $data = convertStdClassObjToArray($data);
        $data = decryptArray($data);
        $res = array(
            'results' => $data
        );
        return response()->json($res);
    }

    function getInSchDetailedAnalysisSummary(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $data = DB::table('school_information')
            ->join('districts', 'school_information.district_id', '=', 'districts.id')
            ->join('beneficiary_information', function ($join) use ($batch_id) {
                $join->on('beneficiary_information.school_id', '=', 'school_information.id')
                    ->whereIn('beneficiary_information.category', array(2, 3))
                    ->where('batch_id', $batch_id);
            })
            ->select(DB::raw('SUM(IF(beneficiary_information.category IN (2,3), 1, 0)) AS identified_girls_count,
                              SUM(IF(beneficiary_status =3 AND verification_recommendation=1 , 1, 0)) AS recommended_girls_count,
                              SUM(IF(beneficiary_status =3 AND verification_recommendation=2 , 1, 0)) AS unrecommended_girls_count,
                              SUM(IF(beneficiary_status =3 AND verification_recommendation=3 , 1, 0)) AS notfound_girls_count,
                              SUM(IF(beneficiary_status =2 , 1, 0)) AS verified_notsubmitted_count,
                              SUM(IF(beneficiary_status =8 , 1, 0)) AS forwarded_sch_placement_count,
                              SUM(IF(beneficiary_status =4 , 1, 0)) AS forwarded_letters_count,
                              SUM(IF(beneficiary_status =6 , 1, 0)) AS forwarded_followups_count,
                              school_information.*,districts.name as district_name'))
            ->groupBy('school_information.id')
            ->get();
        $res = array(
            'results' => $data
        );
        return response()->json($res);
    }

    public function updateBenSuspensionApproval(Request $req)
    {
        $postdata = $req->input();
        $user_id = \Auth::user()->id;
        unset($postdata['_token']);
        $res = array();
        DB::transaction(function () use (&$res, $postdata, $user_id) {
            foreach ($postdata as $value) {
                $id = $value['id'];
                $girl_id = $value['girl_id'];
                $approval_status = $value['approval_status'];
                $approval_remark = $value['approval_remark'];
                $from_stage = $value['enrollment_status'];
                $enrollment_status = 1;
                if ($approval_status == 1) {
                    $enrollment_status = 2;
                }
                $update_params = array(
                    'approval_by' => $user_id,
                    'approval_status' => $approval_status,
                    'approval_remark' => $approval_remark,
                    'approval_date' => Carbon::now(),
                    'stage' => 2
                );
                $log_params = array(
                    'girl_id' => $girl_id,
                    'from_stage' => $from_stage,
                    'to_stage' => $enrollment_status,
                    'reason' => $approval_remark,
                    'author' => $user_id,
                    'created_by' => $user_id,
                    'created_at' => Carbon::now()
                );
                try {
                    DB::table('suspension_requests')
                        ->where('id', $id)
                        ->update($update_params);
                    DB::table('beneficiary_information')
                        ->where('id', $girl_id)
                        ->update(array('enrollment_status' => $enrollment_status));
                    DB::table('beneficiaries_transitional_report')->insert($log_params);
                    $res = array(
                        'success' => true,
                        'message' => 'Details saved successfully!!'
                    );
                } catch (\Exception $e) {
                    $res = array(
                        'success' => false,
                        'message' => $e->getMessage()
                    );
                }
            }
        }, 5);
        return response()->json($res);
    }

    public function recallSuspendedBeneficiaries(Request $req)
    {
        $postdata = $req->input();
        $user_id = \Auth::user()->id;
        unset($postdata['_token']);
        $res = array();
        $postdata = returnUniqueArray($postdata, 'girl_id');
        DB::transaction(function () use (&$res, $postdata, $user_id) {
            foreach ($postdata as $value) {
                $girl_id = $value['girl_id'];
                $remark = $value['remark'];
                $from_stage = $value['enrollment_status'];
                $enrollment_status = 1;
                if ($from_stage != 2) {
                    $from_stage = 2;
                }
                $log_params = array(
                    'girl_id' => $girl_id,
                    'from_stage' => $from_stage,
                    'to_stage' => $enrollment_status,
                    'reason' => $remark,
                    'author' => $user_id,
                    'created_by' => $user_id,
                    'created_at' => Carbon::now()
                );
                try {
                    DB::table('beneficiary_information')
                        ->where('id', $girl_id)
                        ->update(array('enrollment_status' => $enrollment_status));
                    DB::table('beneficiaries_transitional_report')->insert($log_params);
                    $res = array(
                        'success' => true,
                        'message' => 'Details saved successfully!!'
                    );
                } catch (\Exception $e) {
                    $res = array(
                        'success' => false,
                        'message' => $e->getMessage()
                    );
                }
            }
        }, 5);
        return response()->json($res);
    }

    public function getBeneficiaryGradeTransitioning(Request $req)
    {
        $girl_id = $req->input('girl_id');
        try {
            $qry = DB::table('beneficiary_grade_logs')
                ->leftJoin('school_information', 'beneficiary_grade_logs.school_id', '=', 'school_information.id')
                ->leftJoin('users', 'beneficiary_grade_logs.created_by', '=', 'users.id')
                ->select(DB::raw('beneficiary_grade_logs.*,school_information.name as school,
                                  CASE WHEN decrypt(users.first_name) IS NULL THEN users.first_name ELSE decrypt(users.first_name) END as user_first_name,
                                  CASE WHEN decrypt(users.last_name) IS NULL THEN users.last_name ELSE decrypt(users.last_name) END as user_last_name'))
                ->where('girl_id', $girl_id)
                ->orderBy('beneficiary_grade_logs.id');
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => 'All is well'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'messages' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function saveBenGradeChanges(Request $req)
    {
        $girl_id = $req->input('girl_id');
        $grade = $req->input('new_grade');
        $reason = $req->input('reason');
        $user_id = \Auth::user()->id;
        try {
            $beneficiary_details = DB::table('beneficiary_information')
                ->where('id', $girl_id)
                ->first();
            $school_id = $beneficiary_details->school_id;
            $under_promotion = $beneficiary_details->under_promotion;
            if ($under_promotion == 1) {
                $res = array(
                    'success' => false,
                    'message' => 'Action not allowed, beneficiary under promotion!!'
                );
                return response()->json($res);
            }
            DB::table('beneficiary_information')
                ->where('id', $girl_id)
                ->update(array('current_school_grade' => $grade));
            logBeneficiaryGradeTransitioning($girl_id, $grade, $school_id, $user_id, $reason);
            $res = array(
                'success' => true,
                'message' => 'Changes saved successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function updateBeneficiarySchoolCorrection(Request $req)
    {
        $girl_id = $req->input('girl_id');
        $confirmed_school = $req->input('confirmed_school');
        $indicated_school = $req->input('prev_school_id');
        $remark = $req->input('remark');
        $user_id = \Auth::user()->id;
        try {
            $is_allowed = DB::table('unresponsive_cohorts')
                ->where('girl_id', $girl_id)
                ->first();
            if (is_null($is_allowed)) {
                $res = array(
                    'success' => false,
                    'message' => 'This action is not allowed for this beneficiary. This is exclusive for unresponsive cohorts!!'
                );
                return response()->json($res);
            }
            DB::table('beneficiary_information')
                ->where('id', $girl_id)
                ->update(array('school_id' => $confirmed_school));
            $log_params = array(
                'girl_id' => $girl_id,
                'indicated_school_id' => $indicated_school,
                'confirmed_school_id' => $confirmed_school,
                'remark' => $remark,
                'created_by' => $user_id,
                'created_at' => Carbon::now()
            );
            DB::table('ben_schcorrections_log')->insert($log_params);
            DB::table('unresponsive_cohorts')
                ->where('girl_id', $girl_id)
                ->update(array('matched' => 1));
            $res = array(
                'success' => true,
                'message' => 'Details updated successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getBeneficiarySchoolCorrections(Request $req)
    {
        $girl_id = $req->input('girl_id');
        try {
            $results = DB::table('ben_schcorrections_log as t1')
                ->join('school_information as t2', 't1.indicated_school_id', '=', 't2.id')
                ->join('school_information as t3', 't1.confirmed_school_id', '=', 't3.id')
                ->join('users as t4', 't1.created_by', '=', 't4.id')
                ->select(DB::raw('t1.*,t2.name as indicated_school_name,t3.name as confirmed_school_name,
                                  CASE WHEN decrypt(t4.first_name) IS NULL THEN first_name ELSE decrypt(t4.first_name) END as first_name,
                                  CASE WHEN decrypt(t4.last_name) IS NULL THEN last_name ELSE decrypt(t4.last_name) END as last_name'))
                ->where('girl_id', $girl_id)
                ->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => 'All is well'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getBeneficiaryManagementSummary(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $status_id = $req->input('status_id');
        $enrollment_type_id = $req->input('enrollment_type_id');
        $group_id = $req->input('group_id');
        $payment_year = $req->input('payment_year');
        try {
            $qry = DB::table('beneficiary_information as b1')
                ->join('school_information as t3', 'b1.school_id', '=', 't3.id')
                ->join('districts as t1', 't3.district_id', '=', 't1.id')
                ->join('districts as t2', 'b1.district_id', '=', 't2.id')
                ->join('cwac as t4', 'b1.cwac_id', '=', 't4.id');
            if (validateisNumeric($payment_year)) {
                $qry->join('beneficiary_enrollments as t5', function ($join) use ($payment_year) {
                    $join->on('b1.id', '=', 't5.beneficiary_id')
                        ->where('t5.year_of_enrollment', $payment_year);
                })
                    ->join('beneficiary_payment_records as t6', 't5.id', '=', 't6.enrollment_id');
            }
            $qry->select(DB::raw('t1.name as sch_district,
                        SUM(IF(b1.current_school_grade=4,1,0)) as grade_4,
                          SUM(IF(b1.current_school_grade=5,1,0)) as grade_5,
                          SUM(IF(b1.current_school_grade=6,1,0)) as grade_6,
                          SUM(IF(b1.current_school_grade=7,1,0)) as grade_7,
                          SUM(IF(b1.current_school_grade=8,1,0)) as grade_8,
                          SUM(IF(b1.current_school_grade=9,1,0)) as grade_9,
                          SUM(IF(b1.current_school_grade=10,1,0)) as grade_10,
                          SUM(IF(b1.current_school_grade=11,1,0)) as grade_11,
                          SUM(IF(b1.current_school_grade=12,1,0)) as grade_12,
                          t' . $group_id . '.name as group_field'));
            if (validateisNumeric($batch_id)) {
                $qry->where('b1.batch_id', $batch_id);
            }
            if (validateisNumeric($status_id)) {
                $qry->where('b1.enrollment_status', $status_id);
            }
            if (validateisNumeric($enrollment_type_id)) {
                $qry->where('b1.beneficiary_school_status', $enrollment_type_id);
            }
            if (validateisNumeric($group_id)) {
                $qry->groupBy('t' . $group_id . '.id');
            }
            if ($group_id == 5) {
                $qry->join('beneficiary_categories as t5', 'b1.category', '=', 't5.id');
            } else if ($group_id == 6) {
                $qry->leftjoin('beneficiary_school_statuses as t6', 'b1.beneficiary_school_status', '=', 't6.id');
            }
            //$qry->groupBy('t3.id')
            $qry->where('b1.beneficiary_status', 4);
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => 'All is well'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getGradeNineSummary(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $status_id = $req->input('status_id');
            $group_id = $req->input('group_id');

            $where = array(
                'b1.beneficiary_status' => 4,
                'b1.current_school_grade' => 9
            );

            $qry = DB::table('grade_nines_for_promotion as b0')
                ->join('beneficiary_information as b1', 'b0.girl_id', '=', 'b1.id')
                ->join('school_information as t3', 'b1.school_id', '=', 't3.id')
                ->join('districts as t1', 't3.district_id', '=', 't1.id')
                ->join('districts as t2', 'b1.district_id', '=', 't2.id')
                ->join('cwac as t4', 'b1.cwac_id', '=', 't4.id')
                ->select(DB::raw('COUNT(b0.id) as grade_9,
                          t' . $group_id . '.name as group_field'))
                ->where($where)
                ->where('b0.stage', 1)
                ->whereNotIn('enrollment_status', array(2, 4));
            if (isset($batch_id) && $batch_id != '') {
                $qry->where('b1.batch_id', $batch_id);
            }
            if (isset($status_id) && $status_id != '') {
                $qry->where('b1.enrollment_status', $status_id);
            }
            if ($group_id == 5) {
                $qry->join('beneficiary_categories as t5', 'b1.category', '=', 't5.id');
            } else if ($group_id == 6) {
                $qry->leftjoin('beneficiary_school_statuses as t6', 'b1.beneficiary_school_status', '=', 't6.id');
            }
            if (isset($group_id) && $group_id != '') {
                $qry->groupBy('t' . $group_id . '.id');
            }
            $results = $qry->get();
            $res = array(
                'success' => true,
                'message' => returnMessage($results),
                'results' => $results
            );
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
        return response()->json($res);
    }

    public function updateBeneficiaryStatus(Request $req)
    {
        try {
            $girl_id = $req->input('girl_id');
            $from_status = $req->input('from_status');
            $to_status = $req->input('to_status');
            $verification_type = $req->input('verification_type');
            $remark = $req->input('remark');
            $user_id = $this->user_id;
            $params = array(
                'girl_id' => $girl_id,
                'from_status' => $from_status,
                'to_status' => $to_status,
                'remark' => $remark,
                'created_by' => $user_id,
                'created_at' => Carbon::now()
            );
            insertRecord('followup_status_changes', $params, $user_id);
            DB::table('beneficiary_information')
                ->where('id', $girl_id)
                ->update(array('beneficiary_status' => $to_status, 'verification_type' => $verification_type));
            $res = array(
                'success' => true,
                'message' => 'Request executed successfully!!'
            );
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
        return response()->json($res);
    }

    public function updateSelectedBeneficiariesStatus(Request $req)
    {
        try {
            $to_status = $req->input('to_status');
            $verification_type = $req->input('verification_type');
            $remark = $req->input('remark');
            $selected = $req->input('selected');
            $selected_records = json_decode($selected);
            $user_id = $this->user_id;
            $recommendation_log = array();
            $status_update_ids = array();
            $status_log = array();
            //assumptions.....none has recommendation of 1(recommended)
            foreach ($selected_records as $selected_record) {
                $status_update_ids[] = array(
                    $selected_record->girl_id
                );
                $status_log[] = array(
                    'girl_id' => $selected_record->girl_id,
                    'from_status' => $selected_record->from_status,
                    'to_status' => $to_status,
                    'remark' => $remark,
                    'created_by' => $user_id,
                    'created_at' => Carbon::now()
                );
                $recommendation_log[] = array(
                    'girl_id' => $selected_record->girl_id,
                    'from_recomm' => $selected_record->recommendation,
                    'to_recomm' => 1,
                    'changes_by' => $user_id,
                    'changes_on' => Carbon::now(),
                    'reason' => $remark
                );
            }
            DB::table('followup_status_changes')->insert($status_log);
            DB::table('recomm_overrule_logs')->insert($recommendation_log);
            DB::table('beneficiary_information')
                ->whereIn('id', $status_update_ids)
                ->update(array('beneficiary_status' => $to_status, 'verification_recommendation' => 1, 'verification_type' => $verification_type));
            $res = array(
                'success' => true,
                'message' => 'Request executed successfully!!'
            );
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
        return response()->json($res);
    }

    public function getFollowupPossibleReason(Request $req)
    {
        $girl_id = $req->input('girl_id');
        try {
            $girl_details = DB::table('beneficiary_information as t1')
                ->join('beneficiary_master_info as t2', 't1.master_id', '=', 't2.id')
                ->LeftJoin('beneficiary_categories as t3', 't1.category', '=', 't3.id')
                ->LeftJoin('beneficiary_categories as t4', 't2.category', '=', 't4.id')
                ->LeftJoin('verification_recommendation as t5', 't1.verification_recommendation', '=', 't5.id')
                ->select(DB::raw('t1.id, t1.beneficiary_id,t1.category as ver_category,t2.category,t2.is_mapped,
                                  t2.is_dup_processed,t2.is_duplicate_with_existing,t2.is_duplicate,t1.beneficiary_status,t1.school_placement_status,t1.school_matching_status,t5.name as ver_results,t4.name as category_name,t3.name as ver_category_name'))
                ->where('t1.id', $girl_id)
                ->first();

            $array = array();

            $array[0] = array();
            $array[0]['stage'] = 'Batch Assessment';
            $array[0]['stage_id'] = 2;
            $array[0]['process'] = 'Passed Assessment';
            $array[0]['value'] = $girl_details->category;
            $array[0]['isIntVal'] = 1;
            $array[0]['is_dup_processed'] = 'N/A';

            $array[1] = array();
            $array[1]['stage'] = 'Batch Assessment';
            $array[1]['stage_id'] = 2;
            $array[1]['process'] = 'Category';
            $array[1]['value'] = $girl_details->category_name;
            $array[1]['isIntVal'] = '';
            $array[1]['is_dup_processed'] = 'N/A';

            $array[2] = array();
            $array[2]['stage'] = 'Batch Assessment';
            $array[2]['stage_id'] = 2;
            $array[2]['process'] = 'Duplicate Within the Batch?';
            $array[2]['value'] = $girl_details->is_duplicate;
            $array[2]['isIntVal'] = 1;
            $array[2]['is_dup_processed'] = $girl_details->is_dup_processed;

            $array[3] = array();
            $array[3]['stage'] = 'Data Mapping';
            $array[3]['stage_id'] = 3;
            $array[3]['process'] = 'Passed Mapping?';
            $array[3]['value'] = $girl_details->is_mapped;
            $array[3]['isIntVal'] = 1;
            $array[3]['is_dup_processed'] = 'N/A';

            $array[4] = array();
            $array[4]['stage'] = 'Data Mapping';
            $array[4]['stage_id'] = 3;
            $array[4]['process'] = 'Duplicate With Existing?';
            $array[4]['value'] = $girl_details->is_duplicate_with_existing;
            $array[4]['isIntVal'] = 1;
            $array[4]['is_dup_processed'] = $girl_details->is_dup_processed;

            $array[5] = array();
            $array[5]['stage'] = 'Verification';
            $array[5]['stage_id'] = 4;
            $array[5]['process'] = 'Passed Verification';
            $array[5]['value'] = $girl_details->beneficiary_status;
            $array[5]['isIntVal'] = 1;
            $array[5]['is_dup_processed'] = 'N/A';

            $array[6] = array();
            $array[6]['stage'] = 'Verification';
            $array[6]['stage_id'] = 4;
            $array[6]['process'] = 'Category';
            $array[6]['value'] = $girl_details->ver_category_name;
            $array[6]['isIntVal'] = '';
            $array[6]['is_dup_processed'] = 'N/A';

            $array[7] = array();
            $array[7]['stage'] = 'Verification';
            $array[7]['stage_id'] = 4;
            $array[7]['process'] = 'Verification Results';
            $array[7]['value'] = $girl_details->ver_results;
            $array[7]['isIntVal'] = '';
            $array[7]['is_dup_processed'] = 'N/A';

            $array[8] = array();
            $array[8]['stage'] = 'Verification';
            $array[8]['stage_id'] = 4;
            $array[8]['process'] = 'School Placement';
            $array[8]['value'] = $girl_details->school_placement_status;
            $array[8]['isIntVal'] = 2;
            $array[8]['is_dup_processed'] = 'N/A';

            $array[9] = array();
            $array[9]['stage'] = 'Verification';
            $array[9]['stage_id'] = 4;
            $array[9]['process'] = 'School Matching';
            $array[9]['value'] = $girl_details->school_matching_status;
            $array[9]['isIntVal'] = 2;
            $array[9]['is_dup_processed'] = 'N/A';

            $res = array(
                'success' => true,
                'results' => $array,
                'message' => 'All is well'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function updateLateAssessmentInfo(Request $req)
    {
        $master_id = $req->input('master_id');
        $category_id = $req->input('category');
        $grade = $req->input('grade');
        $of_duplicate = $req->input('of_duplicate');
        $user_id = \Auth::user()->id;
        $params = array(
            'category' => $category_id,
            'initial_category' => $category_id,
            'current_school_grade' => $grade,
            'highest_grade' => $grade
        );
        if ($of_duplicate == 1) {
            $params['is_duplicate'] = 0;
            $params['is_active'] = 1;
            $params['activated_by'] = $user_id;
            $params['activated_on'] = Carbon::now();
        }
        try {
            DB::table('beneficiary_master_info')
                ->where('id', $master_id)
                ->update($params);
            $res = array(
                'success' => true,
                'message' => 'Request executed successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getDuplicateProcessingHistory(Request $req)
    {
        $batch_id = $req->input('batch_id');
        try {
            $qry = DB::table('beneficiary_master_info as t1')
                ->join('duplicate_processing_log as t2', 't2.selected_girl_id', '=', 't1.id')
                ->where('t1.batch_id', $batch_id)
                ->groupBy('t1.id');
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => 'All is well'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getDuplicateLateProcessing(Request $req)
    {
        $selected_girl = $req->input('selected_girl_id');
        try {
            $arr1qry = DB::table('duplicate_processing_log')
                ->select('duplicate_girl_id', 'main_girl_id')
                ->where('selected_girl_id', $selected_girl)
                ->get();
            $arr1qry = convertStdClassObjToArray($arr1qry);
            $simpArray1 = convertAssArrayToSimpleArray($arr1qry, 'duplicate_girl_id');
            $simpArray2 = convertAssArrayToSimpleArray($arr1qry, 'main_girl_id');
            $combinedSimpArray = array_unique(array_merge($simpArray1, $simpArray2), SORT_REGULAR);
            $leftOutArr = array_diff($combinedSimpArray, [$selected_girl]);//less selected girl ID
            $qry = DB::table('beneficiary_master_info as t1')
                ->leftJoin('users as t2', 't1.activated_by', '=', 't2.id')
                ->select(DB::raw("t1.*, CONCAT_WS(' ',decrypt(t2.first_name),decrypt(t2.last_name)) as activator"))
                ->whereIn('t1.id', $leftOutArr);
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => 'All is well'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function markBeneficiaryEnrollments(Request $request)
    {
        try {
            $year_of_enrollment = $request->input('year_of_enrollment');
            $term = $request->input('term');
            $shared_qry = DB::table('beneficiary_information as t1')
                ->join('beneficiary_enrollments as t2', 't1.id', '=', 't2.beneficiary_id')
                ->where('t1.beneficiary_status', 4);
            $qry1 = clone $shared_qry;
            $qry1->update(array('t1.school_enrollment_status' => 2));
            $qry2 = clone $shared_qry;
            $qry2->where('t2.year_of_enrollment', $year_of_enrollment)
                ->where('t2.is_validated', 1);
            if (is_numeric($term) && $term > 0) {
                $qry2->where('t2.term_id', $term);
            }
            $qry2->update(array('t1.school_enrollment_status' => 1));

            $res = array(
                'success' => true,
                'message' => 'Process executed successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function updateFilteredOutGirlsCategories()
    {
        $postdata = file_get_contents("php://input");
        $data = json_decode($postdata);
        // the post data is array thus handle as array data
        if (is_array($data)) {
            $data = json_decode($postdata, true);
        } else {
            $data = array();
            $data[] = json_decode($postdata, true);
        }
        $table_name = 'beneficiary_master_info';
        try {
            foreach ($data as $key => $value) {
                $table_data = array(
                    'initial_category' => $value['category'],
                    'category' => $value['category']
                );
                //where data
                $where_data = array(
                    'id' => $value['id']
                );
                DB::table($table_name)
                    ->where($where_data)
                    ->update($table_data);
            }
            $res = array(
                'success' => true,
                'message' => 'Details Updated Successfully!!'
            );
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
        return response()->json($res);
    }

    public function updateInSchoolFailedMappingGrades()
    {
        try {
            $postdata = file_get_contents("php://input");
            $data = json_decode($postdata);
            // the post data is array thus handle as array data
            if (is_array($data)) {
                $data = json_decode($postdata, true);
            } else {
                $data = array();
                $data[] = json_decode($postdata, true);
            }
            $table_name = 'beneficiary_master_info';
            foreach ($data as $key => $value) {
                $table_data = array(
                    'current_school_grade' => $value['current_school_grade']
                );
                //where data
                $where_data = array(
                    'id' => $value['id']
                );
                DB::table($table_name)
                    ->where($where_data)
                    ->update($table_data);
            }
            $res = array(
                'success' => true,
                'message' => 'Details Updated Successfully!!'
            );
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
        return response()->json($res);
    }

    public function exportBenBatchRecords(Request $request)
    {
        try {
            $extraParams = urldecode($request->input('extraparams'));
            $extraParams = json_decode($extraParams, true);
            //Added on 11/24/2024 - Start
             $filter=urldecode($request->input('filters'));
           // print_r( $filter);
           //  exit();
            $gridfilter = array('filter'=>$filter);
            if(!empty($gridfilter)){
                $extraParams = array_merge($extraParams, $gridfilter);
            }
            //Added on 11/24/2024 - End
            if (empty($extraParams)) {
                $extraParams = array();
            }
            $route = urldecode($request->input('route'));
            $routeArray = explode('/', $route);
            $function = last($routeArray);

            $myRequest = new Request($extraParams);
            $results = json_decode($this->$function($myRequest)->content(), true);

            $data = $results['results'];
            return exportSystemRecords($request, $data);
        } catch (\Exception $exception) {
            return array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            return array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
    }

    public function getActiveBeneficiariesWithoutSchools(Request $request)
    {
        try {
            $district_id = $request->input('district_id');
            $qry = DB::table('beneficiary_information as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->leftJoin('school_information as t3', 't1.school_id', '=', 't3.id')
                ->join('beneficiary_enrollement_statuses as t4', 't1.enrollment_status', '=', 't4.id')
                ->selectRaw("t1.id as girl_id,t1.beneficiary_id,CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.last_name)) as ben_name,decrypt(t1.first_name) as first_name,decrypt(t1.last_name) as last_name,
                            t2.name as district_name,t1.current_school_grade,t4.name as enrollment_status_name,t1.dob,t1.school_id,t1.beneficiary_school_status,t1.district_id, 3 as transfer_type")
                ->where(array('t1.beneficiary_status' => 4, 't1.enrollment_status' => 1))
                ->whereNull('t3.id');
            if (validateisNumeric($district_id)) {
                $qry->where('t1.district_id', $district_id);
            }
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results
            );
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
        return response()->json($res);
    }


    // public function manualPromotionProcess()
    // {
    //     // $year = date('Y');
    //     $year = 2021;
    //     $description = 'Beneficiary Grade Promotions for the Year ' . $year;
    //     $meta_params = array(
    //         'year' => $year,
    //         'description' => $description,
    //         'created_at' => Carbon::now()
    //     );
    //     $log_data = array(
    //         'process_type' => 'Beneficiary Grade Annual Promotions',
    //         'process_description' => 'Annual Beneficiaries Grade Promotion',//$this->description,
    //         'created_at' => Carbon::now()
    //     );
    //     $checker = DB::table('ben_annual_promotions')
    //         ->where('year', $year)
    //         ->count();
    //     if ($checker > 0) {
    //         $log_data['status'] = 'Failed';
    //         $log_data['failure_reason'] = 'Found another promotion entry for ' . $year;
    //         DB::table('auto_processes_logs')
    //             ->insert($log_data);
    //         print_r('Status: Failed');
    //         print_r('');
    //         print_r('Message: Found another promotion entry for ' . $year);
    //         exit();
    //     }
    //      //check for a missed year
    //     $max_year = DB::table('ben_annual_promotions')->max('year');
    //     $next_year = ($max_year + 1);
    //     if ($next_year != $year) {
    //         print_r('failed');
    //         exit();
    //         $log_data['status'] = 'Failed';
    //         $log_data['failure_reason'] = 'Promotion should be for the year ' . 
    //         $next_year . ', but trying to do promotion for ' . $year;
    //         DB::table('auto_processes_logs')
    //             ->insert($log_data);
    //         print_r('Status: Failed');
    //         print_r('');
    //         print_r('Message: Promotion should be for the year ' . $next_year . ', 
    //         but trying to do promotion for ' . $year);
    //         exit();
    //     }
    //     DB::transaction(function () use ($meta_params, $log_data, $year) {
    //         try {
    //             $prev_year = $year - 1;
    //             $promotion_id = DB::table('ben_annual_promotions')->insertGetId($meta_params);
    //             //gradeNines for Promotion
    //             $where = array(
    //                 'current_school_grade' => 9,
    //                 'enrollment_status' => 1,
    //                 'under_promotion' => 0
    //             );
    //             $grade_nines_main_qry = DB::table('beneficiary_information')
    //                 ->where($where);

    //             $grade_nines_qry = clone $grade_nines_main_qry;
    //             $grade_nines_qry->select(DB::raw("id as girl_id,$prev_year as prev_year,
    //             $year as promotion_year,'MIS Auto' as created_by"));
    //             $grade_nines = $grade_nines_qry->get();
    //             $grade_nines = convertStdClassObjToArray($grade_nines);
    //             $size = 100;
    //             $grade_nines_chunks = array_chunk($grade_nines, $size);
    //             foreach ($grade_nines_chunks as $grade_nines_chunk) {
    //                 DB::table('grade_nines_for_promotion')->insert($grade_nines_chunk);
    //             }

    //             $update_params = array(
    //                 'under_promotion' => 1,
    //                 'promotion_year' => $year
    //             );
    //             $grade_nines_update_qry = clone $grade_nines_main_qry;
    //             $grade_nines_update_qry->update($update_params);

    //             $promotion_data = DB::table('beneficiary_information')
    //                 ->select(DB::raw("id as girl_id,current_school_grade as from_grade,current_school_grade+1 as to_grade,school_id,$promotion_id as promotion_id"))
    //                 ->where('enrollment_status', 1)
    //                 ->whereIn('current_school_grade', array(8, 10, 11))
    //                 ->get();
    //             $promotion_data = convertStdClassObjToArray($promotion_data);

    //             $grade_log_data = DB::table('beneficiary_information')
    //                 ->select(DB::raw("id as girl_id,current_school_grade+1 as grade,school_id,$year as year"))
    //                 ->where('enrollment_status', 1)
    //                 ->whereIn('current_school_grade', array(8, 10, 11))
    //                 ->get();
    //             $grade_log_data = convertStdClassObjToArray($grade_log_data);

    //             //log grade 12 transitioning
    //             $to_stage = 4;
    //             $reason = "'Completed grade 12. Transition of " . $year."'";
    //             $grade12_log = DB::table('beneficiary_information')
    //                 ->select(DB::raw("id as girl_id,enrollment_status as from_stage,$to_stage as to_stage,$reason as reason"))
    //                 ->where('enrollment_status', 1)
    //                 ->where('current_school_grade', 12)
    //                 ->get();
    //             $grade12_log = convertStdClassObjToArray($grade12_log);
    //             DB::table('beneficiaries_transitional_report')->insert($grade12_log);

    //             DB::table('beneficiary_information')
    //                 ->where('enrollment_status', 1)
    //                 ->where('current_school_grade', 12)
    //                 ->update(array('enrollment_status' => 4));

    //             DB::table('beneficiary_information')
    //                 ->where('enrollment_status', 1)
    //                 ->whereIn('current_school_grade', array(8, 10, 11))
    //                 ->update(array('current_school_grade' => 
    //                 DB::raw('current_school_grade+1'), 
    //                 'last_annual_promo_date' => DB::raw('NOW()')));

    //             $promotion_chunks = array_chunk($promotion_data, $size);
    //             foreach ($promotion_chunks as $promotion_chunk) {
    //                 DB::table('ben_annual_promotion_details')->insert($promotion_chunk);
    //             }
    //             $grade_log_chunks = array_chunk($grade_log_data, $size);
    //             foreach ($grade_log_chunks as $grade_log_chunk) {
    //                 DB::table('beneficiary_grade_logs')->insert($grade_log_chunk);
    //             }

    //             $log_data['status'] = 'Successful';
    //             DB::table('auto_processes_logs')
    //                 ->insert($log_data);
    //             print_r('Status: Successful');
    //             print_r('');
    //             print_r('Message: Promotion for ' . $year . ' executed successfully');
    //         } catch (\Exception $e) {
    //             $log_data['status'] = 'Failed';
    //             $log_data['failure_reason'] = $e->getMessage();
    //             DB::table('auto_processes_logs')
    //                 ->insert($log_data);
    //             print_r('Status: Failed');
    //             print_r('');
    //             print_r('Message: ' . $e->getMessage());
    //         } catch (\Throwable $throwable) {
    //             $log_data['status'] = 'Failed';
    //             $log_data['failure_reason'] = $throwable->getMessage();
    //             DB::table('auto_processes_logs')
    //                 ->insert($log_data);
    //             print_r('Status: Failed');
    //             print_r('');
    //             print_r('Message: ' . $throwable->getMessage());
    //         }
    //     }, 5);
    //     return;
    // }

}