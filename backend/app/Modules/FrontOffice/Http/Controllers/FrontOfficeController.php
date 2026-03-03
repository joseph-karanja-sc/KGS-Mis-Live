<?php

namespace App\Modules\FrontOffice\Http\Controllers;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FrontOfficeController extends BaseController
{
    public function getPaymentVerificationEnquiries(Request $req)
    {
        $start = $req->input('start');
        $limit = $req->input('limit');
        $filter = $req->input('filter');
        $term_array = $this->getEnquiryFilter($req->input('term_id'), 't1.term_id');
        $year_array = $this->getEnquiryFilter($req->input('year_of_enrollement'), 't1.year_of_enrollment');
        $district_array = $this->getEnquiryFilter($req->input('district_id'), 't2.district_id');
        $school_array = $this->getEnquiryFilter($req->input('school_id'), 't1.school_id');
        $status_array = $this->getEnquiryFilter($req->input('status_id'), 't1.status_id');
        $where_array = array_merge($term_array, $year_array, $district_array, $school_array, $status_array);

        $whereClauses = array();
        $filter_string = '';
        if (isset($filter)) {
            $filters = json_decode($filter);
            if ($filters != NULL) {
                foreach ($filters as $filter) {
                    switch ($filter->property) {
                        case 'batch_no' :
                            $whereClauses[] = "t1.batch_no like '%" . ($filter->value) . "%'";
                            break;
                    }
                }
                $whereClauses = array_filter($whereClauses);
            }
            if (!empty($whereClauses)) {
                $filter_string = implode(' AND ', $whereClauses);
            }
        }

        try {
            $qry = DB::table('payment_verificationbatch as t1')
                ->select(DB::raw('count(t9.beneficiary_id) as no_of_girls, t1.*,
                                  decrypt(t8.first_name) as submitted_by, t7.name as status_name,t1.id as batch_id,
                                  t2.name as school_name,t3.name as district_name,decrypt(t5.first_name) as added_by_name,
                                  SUM(IF(t9.is_validated=1,1,0)) AS validated_girls, SUM(IF(t9.passed_rules=1,1,0)) AS passed_rules_girls,
                                  SUM(CASE WHEN t9.is_validated = 1 THEN decrypt(t9.annual_fees) ELSE 0 END) AS total_fees,t6.name as province_name'))
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                ->leftJoin('districts as t3', 't2.district_id', '=', 't3.id')
                ->leftJoin('users as t5', 't1.added_by', '=', 't5.id')
                ->leftJoin('provinces as t6', 't3.province_id', '=', 't6.id')
                ->leftJoin('payment_verification_statuses as t7', 't1.status_id', '=', 't7.id')
                ->join('beneficiary_enrollments as t9', 't1.id', '=', 't9.batch_id')
                ->leftJoin('users as t8', 't1.submitted_by', '=', 't8.id')
                ->where($where_array)
                ->havingRaw('count(t9.id)  > 0')
                ->orderBy('t1.added_on', 'asc');
            if ($filter_string != '') {
                $qry->whereRAW($filter_string);
            }
            $qry->groupBy('t1.id');
            $total = count($qry->get());
            $qry->offset($start)
                ->limit($limit);
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'totalCount' => $total,
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

    public function getPaymentApprovalEnquiries(Request $req)
    {
        $start = $req->input('start');
        $limit = $req->input('limit');
        $filter = $req->input('filter');
        $term_array = $this->getEnquiryFilter($req->input('term_id'), 't1.term_id');
        $year_array = $this->getEnquiryFilter($req->input('year_of_enrollement'), 't1.payment_year');
        $status_array = $this->getEnquiryFilter($req->input('status_id'), 't1.status_id');
        $where_array = array_merge($term_array, $year_array, $status_array);

        $whereClauses = array();
        $filter_string = '';
        if (isset($filter)) {
            $filters = json_decode($filter);
            if ($filters != NULL) {
                foreach ($filters as $filter) {
                    switch ($filter->property) {
                        case 'payment_ref_no' :
                            $whereClauses[] = "t1.payment_ref_no like '%" . ($filter->value) . "%'";
                            break;
                    }
                }
                $whereClauses = array_filter($whereClauses);
            }
            if (!empty($whereClauses)) {
                $filter_string = implode(' AND ', $whereClauses);
            }
        }

        try {
            $qry = DB::table('payment_request_details as t1')
                ->select(DB::raw("count(t2.enrollment_id) as no_of_girls,COUNT(DISTINCT(t3.school_id)) as no_of_schools, t1.*,
                                  t6.name as approval_status_name,CONCAT_WS(' ',decrypt(t5.first_name),decrypt(t5.last_name)) as prepared_by_name,t4.name as status_name, SUM(t3.annual_fees) as total_fees"))
                ->join('beneficiary_payment_records as t2', 't1.id', '=', 't2.payment_request_id')
                ->join('beneficiary_enrollments as t3', 't2.enrollment_id', '=', 't3.id')
                ->leftJoin('payment_verification_statuses as t4', 't1.status_id', '=', 't4.id')
                ->leftJoin('users as t5', 't1.prepared_by', '=', 't5.id')
                ->leftJoin('school_transfer_statuses as t6', 't1.approval_status', '=', 't6.id')
                ->where($where_array)
                ->orderBy('t1.prepared_on', 'asc');
            if ($filter_string != '') {
                $qry->whereRAW($filter_string);
            }
            $qry->groupBy('t1.id');
            $total = count($qry->get());
            $qry->offset($start)
                ->limit($limit);
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'totalCount' => $total,
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

    function getEnquiryFilter($value, $key)
    {
        $data = array();
        if (validateisNumeric($value)) {
            $data[$key] = $value;
        }
        return $data;
    }

    public function getPaymentBatchesTransitionalStages(Request $req)
    {
        $payment_batch_id = $req->input('payment_batch_id');
        $where_column = $req->input('where_column');
        try {
            $qry = DB::table('payment_verificationtransitionsubmissions as t1')
                ->leftJoin('users as t2', 't1.released_by', '=', 't2.id')
                ->join('payment_verification_statuses as t3', 't1.previous_status_id', '=', 't3.id')
                ->join('payment_verification_statuses as t4', 't1.next_status_id', '=', 't4.id')
                ->select('t3.name as from_stage_name', 't4.name as to_stage_name', 't1.remarks', 't1.created_at as changes_date', 't2.first_name', 't2.last_name')
                ->where('t1.' . $where_column, $payment_batch_id)
                ->orderBy('t1.id');
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

    // public function getBeneficiaryEnrollmentEnquiries(Request $req)
    // {
    //     $start = $req->input('start');
    //     $limit = $req->input('limit');
    //     $filter = $req->input('filter');
    //     $term_array = $this->getEnquiryFilter($req->input('term_id'), 't1.term_id');
    //     $year_array = $this->getEnquiryFilter($req->input('year_of_enrollement'), 't1.year_of_enrollment');
    //     $district_array = $this->getEnquiryFilter($req->input('district_id'), 't7.district_id');
    //     $school_array = $this->getEnquiryFilter($req->input('school_id'), 't1.school_id');
    //     $enrollmentBatch= $this->getEnquiryFilter($req->input('enrollment_batch'), 't3.batch_id');
    //     $where_array = array_merge($term_array, $year_array, $district_array, $school_array,$enrollmentBatch);

    //     $whereClauses = array();
    //     $filter_string = '';
    //     if (isset($filter)) {
    //         $filters = json_decode($filter);
    //         if ($filters != NULL) {
    //             foreach ($filters as $filter) {
    //                 switch ($filter->property) {
    //                     case 'beneficiary_no' :
    //                         $whereClauses[] = "t3.beneficiary_id like '%" . ($filter->value) . "%'";
    //                         break;
    //                     case 'full_name' :
    //                         $whereClauses[] = "CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) like '%" . ($filter->value) . "%'";
    //                         break;
    //                     case 'batch_no' :
    //                         $whereClauses[] = "t2.batch_no like '%" . ($filter->value) . "%'";
    //                         break;
    //                     case 'payment_ref_no' :
    //                         $whereClauses[] = "t5.payment_ref_no like '%" . ($filter->value) . "%'";
    //                         break;
    //                     case 'is_validated' :
    //                     if($filter->value== 'yes' || $filter->value== 'YES' ){
    //                             $is_validated=1;
    //                         }else{
    //                             $is_validated=0;
    //                         }
    //                         $whereClauses[] = "t1.is_validated like '%" . ($is_validated) . "%'";
    //                         break;
    //                 }
    //             }
    //             $whereClauses = array_filter($whereClauses);
    //         }
    //         if (!empty($whereClauses)) {
    //             $filter_string = implode(' AND ', $whereClauses);
    //         }
    //     }

    //     try {
    //         $qry = DB::table('beneficiary_enrollments as t1')
    //             ->join('payment_verificationbatch as t2', 't1.batch_id', '=', 't2.id')
    //             ->join('beneficiary_information as t3', 't1.beneficiary_id', '=', 't3.id')
    //             ->leftJoin('beneficiary_payment_records as t4', 't4.enrollment_id', '=', 't1.id')
    //             ->leftJoin('payment_request_details as t5', 't5.id', '=', 't4.payment_request_id')
    //             ->leftJoin('beneficiary_school_statuses as t6', 't6.id', '=', 't1.beneficiary_schoolstatus_id')
    //             ->join('school_information as t7', 't1.school_id', '=', 't7.id') 
    //             ->join('batch_info as batch_info', 't3.batch_id', '=', 'batch_info.id')
    //             ->leftjoin('households', 'households.id', '=', 't3.household_id')
    //             ->leftjoin('districts as schooldistrict', 'schooldistrict.id', '=', 't7.district_id')
    //             ->select(DB::raw("t1.*,CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as full_name,
    //                               t6.name as school_status_name,CONCAT(t1.year_of_enrollment,t1.term_id) as yearterm,t3.beneficiary_id as beneficiary_no,
    //                               t7.name as school_name,t4.id as added_for_payments,t5.payment_ref_no,t2.batch_no,batch_info.batch_no AS enrollment_batch,t1.year_of_enrollment,t3.dob,t3.cwac_txt,t3.relation_to_hhh,
    //                                 CONCAT_WS(' ',(households.hhh_fname),decrypt(households.hhh_lname)) as hhh_name,households.hhh_nrc_number,schooldistrict.NAME AS school_district,t3.district_txt AS home_district,year(t1.created_at) as validated_on,t3.mobile_phone_parent_guardian,t3.mobile_phone_cwac_contact_person"))
    //             ->where($where_array);
    //         if ($filter_string != '') {
    //             $qry->whereRAW($filter_string);
    //         }
    //         $total = $qry->count();
    //         $qry->offset($start)->limit($limit);
    //         $results = $qry->get();
    //         $res = array(
    //             'success' => true,
    //             'results' => $results,
    //             'totalCount' => $total,
    //             'message' => 'All is well'
    //         );
    //     } catch (\Exception $e) {
    //         $res = array(
    //             'success' => false,
    //             'message' => $e->getMessage()
    //         );
    //     }
    //     return response()->json($res);
    // }

    public function getBeneficiaryEnrollmentEnquiries(Request $req)
    {
        $start = $req->input('start');
        $limit = $req->input('limit');
        $filter = $req->input('filter');
        $term_array = $this->getEnquiryFilter($req->input('term_id'), 't1.term_id');
        $year_array = $this->getEnquiryFilter($req->input('year_of_enrollement'), 't1.year_of_enrollment');
        $district_array = $this->getEnquiryFilter($req->input('district_id'), 't7.district_id');
        $school_array = $this->getEnquiryFilter($req->input('school_id'), 't1.school_id');
        $enrollmentBatch= $this->getEnquiryFilter($req->input('enrollment_batch'), 't3.batch_id');
        $where_array = array_merge($term_array, $year_array, $district_array, $school_array,$enrollmentBatch);

        $whereClauses = array();
        $filter_string = '';
        if (isset($filter)) {
            $filters = json_decode($filter);
            if ($filters != NULL) {
                foreach ($filters as $filter) {
                    switch ($filter->property) {
                        case 'beneficiary_no' :
                            $whereClauses[] = "t3.beneficiary_id like '%" . ($filter->value) . "%'";
                            break;
                        case 'full_name' :
                            $whereClauses[] = "CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) like '%" . ($filter->value) . "%'";
                            break;
                        case 'batch_no' :
                            $whereClauses[] = "t2.batch_no like '%" . ($filter->value) . "%'";
                            break;
                        case 'payment_ref_no' :
                            $whereClauses[] = "t5.payment_ref_no like '%" . ($filter->value) . "%'";
                            break;
                        case 'is_validated' :
                        if($filter->value== 'yes' || $filter->value== 'YES' ){
                                $is_validated=1;
                            }else{
                                $is_validated=0;
                            }
                            $whereClauses[] = "t1.is_validated like '%" . ($is_validated) . "%'";
                            break;
                    }
                }
                $whereClauses = array_filter($whereClauses);
            }
            if (!empty($whereClauses)) {
                $filter_string = implode(' AND ', $whereClauses);
            }
        }

        try {
            $qry = DB::table('beneficiary_enrollments as t1')
                ->join('payment_verificationbatch as t2', 't1.batch_id', '=', 't2.id')
                ->join('beneficiary_information as t3', 't1.beneficiary_id', '=', 't3.id')
                ->leftJoin('beneficiary_payment_records as t4', 't4.enrollment_id', '=', 't1.id')
                ->leftJoin('payment_request_details as t5', 't5.id', '=', 't4.payment_request_id')
                ->leftJoin('beneficiary_school_statuses as t6', 't6.id', '=', 't1.beneficiary_schoolstatus_id')
                ->join('school_information as t7', 't1.school_id', '=', 't7.id') 
                ->join('batch_info as batch_info', 't3.batch_id', '=', 'batch_info.id')
                ->leftjoin('households', 'households.id', '=', 't3.household_id')
                ->leftjoin('districts as schooldistrict', 'schooldistrict.id', '=', 't7.district_id')
                ->leftjoin('school_contactpersons as t10', 't7.id', '=', 't10.school_id')
                ->select(DB::raw("t1.*,CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as full_name,
                    MAX(CASE WHEN t10.designation_id = 1 THEN t10.full_names END) AS ht_name,
                    MAX(CASE WHEN t10.designation_id = 1 THEN t10.telephone_no END) AS ht_phone,
                    MAX(CASE WHEN t10.designation_id = 2 THEN t10.full_names END) AS gc_name,
                    MAX(CASE WHEN t10.designation_id = 2 THEN t10.telephone_no END) AS gc_phone,
                    t6.name as school_status_name,CONCAT(t1.year_of_enrollment,t1.term_id) as yearterm,
                    t3.beneficiary_id as beneficiary_no,t7.name as school_name,t4.id as added_for_payments,
                    t5.payment_ref_no,t2.batch_no,batch_info.batch_no AS enrollment_batch,t1.year_of_enrollment,
                    t3.dob,t3.cwac_txt,t3.relation_to_hhh,CONCAT_WS(' ',(households.hhh_fname),
                    decrypt(households.hhh_lname)) as hhh_name,households.hhh_nrc_number,
                    schooldistrict.NAME AS school_district,t3.district_txt AS home_district,
                    year(t1.created_at) as validated_on,t3.mobile_phone_parent_guardian,t3.mobile_phone_cwac_contact_person"))
                ->where($where_array);
            if ($filter_string != '') {
                $qry->whereRAW($filter_string);
            }
            $qry->groupBy('t1.beneficiary_id');
            // $total = $qry->count();
            // $qry->offset($start)->limit($limit);
            $results = $qry->get();
            $total= $results->count();

            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'totalCount' => $total,
                'total' => $total,
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

    public function getBeneficiaryDisabilitytEnquiries(Request $req)
    {
        $start = $req->input('start');
        $limit = $req->input('limit');
        $filter = $req->input('filter');
        $district_array = $this->getEnquiryFilter($req->input('district_id'), 't5.district_id');
        $school_array = $this->getEnquiryFilter($req->input('school_id'), 't1.school_id');
        $import_array = $this->getEnquiryFilter($req->input('import_batch'), 't1.batch_id');
        $where_array = array_merge($district_array, $school_array, $import_array);
        $whereClauses = array();
        $filter_string = '';        
        if (isset($filter)) {
            $filters = json_decode($filter);
            if ($filters != NULL) {
                foreach ($filters as $filter) {
                    switch ($filter->property) {
                        case 'beneficiary_no' :
                            $whereClauses[] = "t1.beneficiary_id like '%" . ($filter->value) . "%'";
                            break;
                        case 'full_name' :
                            $whereClauses[] = "CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.last_name)) like '%" . ($filter->value) . "%'";
                            break;
                    }
                }
                $whereClauses = array_filter($whereClauses);
            }
            if (!empty($whereClauses)) {
                $filter_string = implode(' AND ', $whereClauses);
            }
        }
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->join('beneficiary_categories as t2', 't1.category', '=', 't2.id')
                ->leftJoin('school_information as t4', 't1.school_id', '=', 't4.id')
                ->leftJoin('districts as t5', 't4.district_id', '=', 't5.id')
                ->leftJoin('districts as t6', 't1.district_id', '=', 't6.id')
                ->join('beneficiary_verification_report as t7', 't7.beneficiary_id', '=', 't1.id')
                ->join('checklist_items as t8', 't7.checklist_item_id', '=', 't8.id')
                ->leftjoin('households', 'households.id', '=', 't1.household_id')
                ->leftJoin('beneficiary_disability_types as t9', function($join) {
                    $join->whereRaw("FIND_IN_SET(t9.flag,t7.response)");
                })
                ->join('batch_info as t10', 't1.batch_id', '=', 't10.id')
                ->select(
                    DB::raw("t1.id,t10.batch_no,t1.current_school_grade,t1.beneficiary_id,decrypt(t1.first_name) AS first_name,decrypt(t1.last_name) AS last_name,
                        CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.last_name)) as full_name,t5.name AS home_district,t1.dob,
                        t6.name AS school_district,t4.name AS school_name,t2.name AS category_name,GROUP_CONCAT(t9.name) AS disability_detail,
                        t7.remark AS disability_remark,t7.response,CONCAT_WS(' ',(households.hhh_fname),decrypt(households.hhh_lname)) as 
                        hhh_name,households.hhh_nrc_number,t1.mobile_phone_parent_guardian,t1.mobile_phone_cwac_contact_person"
                    )
                )
                ->where('t7.response', '<>', '')
                ->whereIn('t7.checklist_item_id', [11,52,65,92,109])
                ->where($where_array);
            if ($filter_string != '') {
                $qry->whereRAW($filter_string);
            }
            $qry->groupBy('t1.id');
            $total = $qry->count();
            $qry->offset($start)->limit($limit);
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'totalCount' => $total,
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

    public function exportFrontOfficeRecords(Request $request)
    {
        try {
            $extraParams = urldecode($request->input('extraparams'));
            $extraParams = json_decode($extraParams, true);
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
    public function getFrontOfficeParamFromTable(Request $request){
        $table_name = $request->input('table_name');
        $filters = $request->input('filters');
        $filters = (array)json_decode($filters);
        try {
            $qry = DB::table($table_name);
            if (count((array)$filters) > 0) {
                $qry->where($filters);
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
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return response()->json($res);

    }

}
