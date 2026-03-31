<?php

namespace App\Modules\PaymentModule\Http\Controllers;

use App\Http\Controllers\BaseController;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use App\Modules\PaymentModule\Exports\KnockedOutGirls;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Support\Facades\Auth;
use App\Modules\PaymentModule\Exports\PaymentGrantList;
use App\Modules\PaymentModule\Exports\NewEntrants;
use App\Modules\PaymentModule\Exports\PaymentVariancesReport;
use App\Modules\PaymentModule\Exports\KnockOutReport;
use App\Jobs\SavePaymentVerificationDetailsJob;
use App\Jobs\AddPaymentBeneficiariesJob;

Builder::macro('if', function ($condition, $column, $operator, $value) {
    if ($condition) {
        return $this->where($column, $operator, $value);
    }
    return $this;
});

class PaymentModuleController extends BaseController
{

    public function index()
    {
        return view('paymentmodule::index');
    }

    public function deletePaymentModuleRecord(Request $request)
    {
        try {
            $record_id = $request->input('id');
            $table_name = $request->input('table_name');
            $user_id = $this->user_id;
            $where = array(
                'id' => $record_id
            );
            $previous_data = getPreviousRecords($table_name, $where);
            $res = deleteRecord($table_name, $previous_data, $where, $user_id);
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

    public function getbankContactSchoolinfo(Request $req)
    {
        $school_id = $req->input('school_id');
        $qry = DB::table('school_information as t1')
            ->select(DB::raw('t1.district_id, t1.cwac_id,t1.running_agency_id,t1.school_type_id, t2.bank_id, t2.branch_name, t1.code as emis_code, decrypt(t2.account_no) as account_no, t11.sort_code, t3.full_names as school_headteacher, t3.mobile_no as headteacher_tel_no,t4.full_names as school_guidance_counselling_teacher,t4.mobile_no as guidance_counselling_teacher_phone_number'
            //->select(DB::raw('t1.district_id, t1.cwac_id,t1.running_agency_id,t1.school_type_id, t2.bank_id, t2.branch_name, t1.code as emis_code, decrypt(t2.account_no) as account_no, t11.sort_code, t4.full_names as school_guidance_counselling_teacher,t4.mobile_no as guidance_counselling_teacher_phone_number'      
            ))
            ->leftJoin('school_bankinformation as t2', function ($join) {
                $join->on('t1.id', '=', 't2.school_id')
                    ->where('t2.is_activeaccount', 1);
            })
            ->leftJoin('bank_branches as t11', 't2.branch_name', '=', 't11.id')
            ->leftJoin('school_contactpersons as t3', function ($join) {//get head teacher details
                $join->on('t1.id', '=', 't3.school_id')
                    ->where('t3.designation_id', '=', DB::raw(1));
            })
            ->leftJoin('school_contactpersons as t4', function ($join) {//get guidance couselling teacher details
                $join->on('t1.id', '=', 't4.school_id')
                    ->where('t4.designation_id', '=', DB::raw(2));
            })
            ->where(array('t1.id' => $school_id))
            ->get();
        $results = convertStdClassObjToArray($qry);
        $results = decryptArray($results);
        $data = array();
        if (count($results) > 0) {
            $data = $results[0];
        }
        $data1 = array();//temporary
        json_output($data1);
    }
    public function getBenePaidForWithFilters(Request $req)
   {
      $school_status = $req->input('school_status');
      $gce_external = $req->input('gce_external');
     if(isset($school_status)&& $school_status!=null)
    {
        $paid_4_beneficiaries_with_zero_fees_query=Db::select("SELECT COUNT(t1.beneficiary_id),t1.beneficiary_id as ben_id FROM beneficiary_enrollments AS t1 
        INNER JOIN beneficiary_payment_records AS t2 ON t1.id=t2.enrollment_id  WHERE t1.year_of_enrollment<2022 AND beneficiary_schoolstatus_id=$school_status GROUP BY t1.beneficiary_id");
        $counted_beneficiries=array();
        foreach($paid_4_beneficiaries_with_zero_fees_query as $ben_data)
        {
         $counted_beneficiries[]=$ben_data->ben_id;
        }
        $counted_beneficiries=implode(",",$counted_beneficiries);
        $paid_4_beneficiaries_query_with_no_zero_fees_query=Db::select("SELECT COUNT(t2.beneficiary_id) FROM   beneficiary_enrollments as t2 inner join beneficiary_payment_records AS t3 ON t2.id=t3.enrollment_id
        INNER JOIN payment_request_details as t4 on t4.id=t3.payment_request_id WHERE t2.year_of_enrollment>=2022 AND decrypt(t2.annual_fees)>0  AND t4.status_id>=4 AND beneficiary_schoolstatus_id=$school_status   GROUP by t2.beneficiary_id ");
         //dd($paid_4_beneficiaries_query_with_no_zero_fees_query);
         $paid_4_beneficiaries_with_zero_fees=count($paid_4_beneficiaries_with_zero_fees_query);
         $paid_4_beneficiaries_query_with_no_zero_fees=count($paid_4_beneficiaries_query_with_no_zero_fees_query);
         $paid_4_beneficiaries_with_zero_fees=0;
         $paid_4_beneficiaries=$paid_4_beneficiaries_with_zero_fees+$paid_4_beneficiaries_query_with_no_zero_fees;
         // dd($paid_4_beneficiaries);
      
    }

    if(isset($gce_external)&& $gce_external!=null)
    {

        $paid_4_beneficiaries_with_zero_fees_query=Db::select("SELECT COUNT(t1.beneficiary_id),t1.beneficiary_id as ben_id FROM beneficiary_enrollments AS t1 
        INNER JOIN beneficiary_payment_records AS t2 ON t1.id=t2.enrollment_id  WHERE t1.year_of_enrollment<2022 AND is_gce_external_candidate=$gce_external GROUP BY t1.beneficiary_id");
        $counted_beneficiries=array();
        foreach($paid_4_beneficiaries_with_zero_fees_query as $ben_data)
        {
         $counted_beneficiries[]=$ben_data->ben_id;
        }
        $counted_beneficiries=implode(",",$counted_beneficiries);
        $paid_4_beneficiaries_query_with_no_zero_fees_query=Db::select("SELECT COUNT(t2.beneficiary_id) FROM   beneficiary_enrollments as t2 inner join beneficiary_payment_records AS t3 ON t2.id=t3.enrollment_id
        INNER JOIN payment_request_details as t4 on t4.id=t3.payment_request_id WHERE t2.year_of_enrollment>=2022 AND decrypt(t2.annual_fees)>0  AND t4.status_id>=4 AND is_gce_external_candidate=$gce_external   GROUP by t2.beneficiary_id ");
         //dd($paid_4_beneficiaries_query_with_no_zero_fees_query);
         $paid_4_beneficiaries_with_zero_fees=count($paid_4_beneficiaries_with_zero_fees_query);
         $paid_4_beneficiaries_query_with_no_zero_fees=count($paid_4_beneficiaries_query_with_no_zero_fees_query);
         $paid_4_beneficiaries_with_zero_fees=0;

         $paid_4_beneficiaries=$paid_4_beneficiaries_with_zero_fees+$paid_4_beneficiaries_query_with_no_zero_fees;
         // dd($paid_4_beneficiaries);


    }
   }  
    public function getVerificationdistrictDataInitial(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $category_id = $req->input('category_id');
            $grades = $req->input('grades');
            $print_filter = $req->input('print_filter');
            $sub_category = $req->input('sub_category');
            $verification_type = $req->input('verification_type');
            $source = $req->input('source');
            $grades = json_decode($grades);
            $qry = DB::table('beneficiary_information as t1')
                ->select(DB::raw('t2.name as school_name,t2.id as school_id,count(DISTINCT(t1.id)) as beneficiary_count,t3.id as district_id, t3.name as district_name,t4.name as province_name, t4.id as province_id'))
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                ->join('districts as t3', 't2.district_id', '=', 't3.id')
                ->join('provinces as t4', 't3.province_id', '=', 't4.id')
                ->where('t1.beneficiary_status', 4)
                ->where('t1.enrollment_status', 1)
                ->where('t1.under_promotion', 0)
                ->where('t1.payment_eligible', 1);
            getBeneficiarySchMatchingPlacementDetails($qry, $sub_category, 't1');
            if (isset($batch_id) && $batch_id != '') {
                $qry->where('t1.batch_id', $batch_id);
            }
            if (isset($category_id) && $category_id != '') {
                $qry->where('t1.category', $category_id);
            }
            if (validateisNumeric($verification_type)) {
                $qry->where('t1.verification_type', $verification_type);
            }
            if (isset($grades)) {
                if (count($grades) > 0) {
                    $qry->whereIn('t1.current_school_grade', $grades);
                }
            }
            if (isset($print_filter) && $print_filter != '') {
                if ($print_filter == 1) {
                    $qry->where('t1.payment_printed', 1);
                } else if ($print_filter == 2) {
                    $qry->where('t1.payment_printed', '<>', 1)
                        ->orWhere(function ($query) {
                            $query->whereNull('t1.payment_printed');
                        });
                }
            }
            if ($source == 1 || $source == 2) {
                $qry->groupBy('t2.id');
            } else {
                $qry->groupBy('t3.id');
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

    public function getVerificationdistrictData(Request $req)
    {//frank
        try {
            $json_batch_id = $req->input('batch_id');
            $batch_id = json_decode($json_batch_id);
            $category_id = $req->input('category_id');
            $grades = $req->input('grades');
            $print_filter = $req->input('print_filter');
            $sub_category = $req->input('sub_category');
            $verification_type = $req->input('verification_type');
            $source = $req->input('source');
            $grades = json_decode($grades);
            $qry = DB::table('beneficiary_information as t1')
                ->select(DB::raw('t2.name as school_name,t2.id as school_id,count(DISTINCT(t1.id)) as beneficiary_count,t3.id as district_id, t3.name as district_name,t4.name as province_name, t4.id as province_id'))
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                ->join('districts as t3', 't2.district_id', '=', 't3.id')
                ->join('provinces as t4', 't3.province_id', '=', 't4.id')
                ->where('t1.beneficiary_status', 4)
                ->where('t1.enrollment_status', 1)     
                ->whereIn('t1.under_promotion', [0, 1])
                ->where('t1.payment_eligible', 1);
            getBeneficiarySchMatchingPlacementDetails($qry, $sub_category, 't1');
            if (isset($batch_id)) {
                if (count($batch_id) > 0) {
                    $qry->whereIn('t1.batch_id', $batch_id);
                }
            }
            if (isset($category_id) && $category_id != '') {
                $qry->where('t1.category', $category_id);
            }
            if (validateisNumeric($verification_type)) {
                $qry->where('t1.verification_type', $verification_type);
            }
            if (isset($grades)) {
                if (count($grades) > 0) {
                    $qry->whereIn('t1.current_school_grade', $grades);
                }
            }
            if (isset($print_filter) && $print_filter != '') {
                if ($print_filter == 1) {
                    $qry->where('t1.payment_printed', 1);
                } else if ($print_filter == 2) {
                    $qry->where('t1.payment_printed', '<>', 1)
                        ->orWhere(function ($query) {
                            $query->whereNull('t1.payment_printed');
                        });
                }
            }
            if ($source == 1 || $source == 2) {
                $qry->groupBy('t2.id');
            } else {
                $qry->groupBy('t3.id');
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

    public function generateNewEntrants()//job on 27/07/2022
   {
    return Excel::download(new NewEntrants,'NewEntrants.xlsx');
   }  
    public function getUnprintedBeneficiariesPaymentChecklistStats(Request $req)
    {
        try {
            $category_id = $req->input('category_id');
            $grades = $req->input('grades');
            $district = $req->input('district');
            $year_not_paid = $req->input('year');
            $batch_id = $req->input('batch_id');
            $verification_type = $req->input('verification_type');
            $grades = json_decode($grades);
            $qry = DB::table('beneficiary_information as t1')
                ->select(DB::raw('t2.name as school_name,t2.id as school_id,count(DISTINCT(t1.id)) as beneficiary_count,t3.id as district_id, t3.name as district_name,t4.name as province_name, t4.id as province_id'))
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                ->join('districts as t3', 't2.district_id', '=', 't3.id')
                ->join('provinces as t4', 't3.province_id', '=', 't4.id')
                ->where('t1.beneficiary_status', 4)
                ->where('t1.enrollment_status', 1)
                ->where('t1.under_promotion', 0)
                ->where('t1.payment_eligible', 1)
                ->whereNotIn('t1.id', function ($query) use ($year_not_paid) {
                    $query->select(DB::raw('girl_id'))
                        ->from('vw_paymentchecklist_generations')
                        ->where('year', $year_not_paid);
                });
            if (validateisNumeric($verification_type)) {
                $qry->where('t1.verification_type', $verification_type);
            }
            if (isset($district) && $district != '') {
                $qry->where('t2.district_id', $district);
            }
            if (isset($category_id) && $category_id != '') {
                $qry->where('t1.category', $category_id);
            }
            if (isset($grades)) {
                if (count($grades) > 0) {
                    $qry->whereIn('t1.current_school_grade', $grades);
                }
            }
            if (validateisNumeric($batch_id)) {
                $qry->where('t1.batch_id', $batch_id);
            }
            $qry->groupBy('t3.id');
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

    public function getPromotionBeneficiariesPaymentChecklistStatsGeneric(Request $req)
    {
        try {
            $promo_revoked = $req->input('promo_revoked');
            if (isset($promo_revoked) && is_numeric($promo_revoked) && $promo_revoked == 1) {
                $results = $this->getRevokedPromotionBeneficiariesPaymentChecklistStats($req);
            } else {
                $results = $this->getPromotionBeneficiariesPaymentChecklistStats($req);
            }
            $res = array(
                'success' => true,
                'results' => $results,
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

    public function getPromotionBeneficiariesPaymentChecklistStats(Request $req)
    {
        try {
            $is_promotion=1;//job on 18/3/2022
            $prom_year = $req->input('prom_year');
            $batch_id = $req->input('batch_id');
            $category_id = $req->input('category_id');
            $source = $req->input('source');
            $qry = DB::table('grade_nines_for_promotion as t0')
                ->join('beneficiary_information as t1', 't0.girl_id', '=', 't1.id')
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                ->join('districts as t3', 't2.district_id', '=', 't3.id')
                ->join('provinces as t4', 't3.province_id', '=', 't4.id')
                //->join('gradenine_promotions as t5', 't0.girl_id', '=', 't5.girl_id')
                ->join('gradenine_promotions as t5', function ($join) {
                    $join->on('t0.girl_id', '=', 't5.girl_id')
                        ->on('t0.promotion_year', '=', 't5.promotion_year');
                })
                ->select(DB::raw("$is_promotion as undergoing_promotion,t2.name as school_name,t2.id as school_id,count(DISTINCT(t1.id)) as beneficiary_count,t3.id as district_id, t3.name as district_name,t4.name as province_name, t4.id as province_id"))
                ->where('t1.beneficiary_status', 4)
                ->where('t1.enrollment_status', 1)
                ->where('t0.promotion_year', $prom_year)
                ->where('t0.stage', 3)
                ->whereIn('t5.qualified', array(1, 2))
                ->where('t1.payment_eligible', 1);
            if (isset($batch_id) && $batch_id != '') {
                $qry->where('t1.batch_id', $batch_id);
            }
            if (isset($category_id) && $category_id != '') {
                $qry->where('t1.category', $category_id);
            }
            if ($source == 2) {
                $qry->groupBy('t2.id');
            } else {
                $qry->groupBy('t3.id');
            }
            $results = $qry->get();
        } catch (\Exception $exception) {
            $results = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            $results = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return $results;
    }

    
    public function getRevokedPromotionBeneficiariesPaymentChecklistStats(Request $req)
    {
        $is_promotion=0;//job on 18/3/2022
        $prom_year = $req->input('prom_year');
        $batch_id = $req->input('batch_id');
        $category_id = $req->input('category_id');
        $source = $req->input('source');
        $qry = DB::table('revoked_gradenine_promotions as t00')
            ->join('grade_nines_for_promotion as t0', 't00.promotion_id', '=', 't0.id')
            ->join('beneficiary_information as t1', 't0.girl_id', '=', 't1.id')
            ->join('school_information as t2', 't1.school_id', '=', 't2.id')
            ->join('districts as t3', 't2.district_id', '=', 't3.id')
            ->join('provinces as t4', 't3.province_id', '=', 't4.id')
            ->select(DB::raw("$is_promotion as undergoing_promotion,t2.name as school_name,t2.id as school_id,count(DISTINCT(t1.id)) as beneficiary_count,t3.id as district_id, t3.name as district_name,t4.name as province_name, t4.id as province_id"))
            ->where('t1.beneficiary_status', 4)
            ->where('t1.enrollment_status', 1)
            ->where('t0.promotion_year', $prom_year)
            ->where('t1.payment_eligible', 1);
        if (isset($batch_id) && $batch_id != '') {
            $qry->where('t1.batch_id', $batch_id);
        }
        if (isset($category_id) && $category_id != '') {
            $qry->where('t1.category', $category_id);
        }
        if ($source == 2) {
            $qry->groupBy('t2.id');
        } else {
            $qry->groupBy('t3.id');
        }
        $results = $qry->get()->toArray();
        //job on 18/3/2022
        $results2 = $this->getPromotionBeneficiariesPaymentChecklistStats($req)->ToArray();
        $results=array_merge($results,$results2);
        //end mod
        return $results;
    }

    public function getPaymentUnvalidatedBeneficiaries(Request $req)
    {
        try {
            $year = $req->input('year');
            $term = $req->input('term');
            $district = $req->input('district');
            $qry = DB::table('beneficiary_information as t1')
                ->join('beneficiary_enrollments as t5', 't1.id', '=', 't5.beneficiary_id')
                ->join('school_information as t2', 't5.school_id', '=', 't2.id')
                ->join('districts as t3', 't2.district_id', '=', 't3.id')
                ->join('provinces as t4', 't3.province_id', '=', 't4.id')
                ->select(DB::raw('t2.name as school_name,t2.id as school_id,count(DISTINCT(t1.id)) as beneficiary_count,t3.id as district_id, t3.name as district_name,t4.name as province_name, t4.id as province_id'))
                ->where('t5.year_of_enrollment', $year)
                ->where('t1.payment_eligible', 1)
                //->where('t5.term_id', $term)
                ->where(function ($query) {
                    $query->where('t5.is_validated', 0)
                        ->orWhereNull('t5.is_validated');
                });
            if (isset($district) && $district != '') {
                $qry->where('t3.id', $district);
            }
            $qry->groupBy('t3.id');
            $results = $qry->get();
            $res = array(
                'success' => true,
                'message' => returnMessage($results),
                'results' => $results
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

    public function getPaymentFollowupBeneficiaries(Request $req)
    {
        try {
            $year = $req->input('year');
            $gradeNineYear = ($year - 1);
            $district = $req->input('district');
            $viewWithRemarks = $req->input('viewWithRemarks');
            $qry = DB::table('beneficiary_information as t1')
                ->join('beneficiary_enrollments as t5', 't1.id', '=', 't5.beneficiary_id')
                ->join('school_information as t2', 't5.school_id', '=', 't2.id')
                ->join('districts as t3', 't2.district_id', '=', 't3.id')
                ->leftJoin('beneficiary_enrollments as t4', function ($join) use ($year) {
                    $join->on('t5.beneficiary_id', '=', 't4.beneficiary_id')
                        ->where('t4.year_of_enrollment', '<', $year)
                        ->where('t4.is_validated', 1);
                })
                ->leftJoin('gradenine_promotions as t6', function ($join) use ($gradeNineYear) {
                    $join->on('t1.id', '=', 't6.girl_id')
                        ->where('t6.promotion_year', $gradeNineYear);
                })
                ->leftJoin('post_exam_eligibility as t7', 't6.qualified', '=', 't7.id')
                ->select(DB::raw('distinct(t1.id) as girl_id,t2.name as school_name,t2.id as school_id,t3.id as district_id, t3.name as district_name,t1.current_school_grade,
                              t1.beneficiary_id as beneficiary_no,t5.id,decrypt(t1.first_name) as first_name,decrypt(t1.last_name) as last_name,t5.remarks,t5.has_signed,
                              COUNT(t4.id) as no_of_validations,t7.name as post_exam_eligibility'))
                ->where('t5.year_of_enrollment', $year)
                ->where(function ($query) {
                    $query->where('t5.is_validated', 0)
                        ->orWhereNull('t5.is_validated');
                })
                ->where('t3.id', $district);
            if ($viewWithRemarks === true || $viewWithRemarks === 'true' || $viewWithRemarks == 1) {
                $qry->where(function ($query) {
                    $query->where('t5.remarks', '<>', ' ')
                        ->orWhereNotNull('t5.remarks');
                });
            }
            $qry->groupBy('t1.id');
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

    public function getSpecificPaymentChecklistsGenInitial(Request $req)
    {
        $school_id = $req->input('school_id');
        $batch_id = $req->input('batch_id');
        $category_id = $req->input('category_id');
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->select(DB::raw('t1.id,CASE WHEN decrypt(t1.first_name) IS NULL THEN t1.first_name ELSE decrypt(t1.first_name) END as first_name, CASE WHEN decrypt(t1.last_name) IS NULL THEN t1.last_name ELSE decrypt(t1.last_name) END as last_name,
                                  t1.beneficiary_id'))
                ->where('t1.beneficiary_status', 4)
                ->where('t1.enrollment_status', 1)
                ->where('t1.payment_eligible', 1)
                ->where('t1.school_id', $school_id);
            if (isset($batch_id) && $batch_id != '') {
                $qry->where('t1.batch_id', $batch_id);
            }
            if (isset($category_id) && $category_id != '') {
                $qry->where('t1.category', $category_id);
            }
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
    
    public function getSpecificPaymentChecklistsGen(Request $req)
    {//frank
        $school_id = $req->input('school_id');
        $json_batch_id = $req->input('batch_id');
        $batch_id = json_decode($json_batch_id);
        $category_id = $req->input('category_id');
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->select(DB::raw('t1.id,CASE WHEN decrypt(t1.first_name) IS NULL THEN t1.first_name ELSE decrypt(t1.first_name) END as first_name, CASE WHEN decrypt(t1.last_name) IS NULL THEN t1.last_name ELSE decrypt(t1.last_name) END as last_name,
                                  t1.beneficiary_id'))
                ->where('t1.beneficiary_status', 4)
                ->where('t1.enrollment_status', 1)
                ->where('t1.payment_eligible', 1)
                ->where('t1.school_id', $school_id);
            if (isset($batch_id)) {
                if (count($batch_id) > 0) {
                    $qry->whereIn('t1.batch_id', $batch_id);
                }
            }
            if (isset($category_id) && $category_id != '') {
                $qry->where('t1.category', $category_id);
            }
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

    public function getDistrict_SchoolsData(Request $req)
    {
        try {
            $district_id = $req->input('district_id');
            $batch_id = $req->input('batch_id');
            $category_id = $req->input('category_id');
            $qry = DB::table('beneficiary_information as t1')
                ->select(DB::raw('count(t1.id) as beneficiary_count,t2.physical_address, t2.email_address, t3.id as district_id, t3.name as district_name,t4.name as province_name, t2.name as school_name, t2.id as school_id'))
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                ->join('districts as t3', 't2.district_id', '=', 't3.id')
                ->join('provinces as t4', 't3.province_id', '=', 't4.id')
                ->groupBy('t2.id')
                ->where(array('t2.district_id' => $district_id, 'enrollment_status' => 1, 'beneficiary_status' => 4))
                ->where('t1.payment_eligible', 1)
                ->where('t1.under_promotion', 0);
            if (isset($batch_id) && $batch_id != '') {
                $qry->where('t1.batch_id', $batch_id);
            }
            if (isset($category_id) && $category_id != '') {
                $qry->where('t1.category', $category_id);
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

        json_output($res);

    }

    public function getBenschool_Provinces()
    {
        $qry = DB::table('beneficiary_information as t1')
            ->select('t4.name', 't4.id')
            ->join('school_information as t2', 't1.school_id', '=', 't2.id')
            ->join('districts as t3', 't2.district_id', '=', 't3.id')
            ->join('provinces as t4', 't3.province_id', '=', 't4.id')
            ->groupBy('t4.id')
            ->get();
        $results = convertStdClassObjToArray($qry);
        $results = decryptArray($results);
        $res = array(
            'results' => $results
        );
        //var_dump($res);
        json_output($res);

    }

    public function getBenschool_Districts(Request $req)
    {
        $get_data = $req->all();
        $where_data = array();
        if (isset($get_data['province_id'])) {

            $where_data['t3.province_id'] = $get_data['province_id'];
        }

        if (isset($get_data['school_id'])) {

            $where_data['t1.school_id'] = $get_data['school_id'];
        }
        $qry = DB::table('beneficiary_information as t1')
            ->select('t3.name', 't3.id')
            ->join('school_information as t2', 't1.school_id', '=', 't2.id')
            ->join('districts as t3', 't2.district_id', '=', 't3.id')
            ->groupBy('t3.id')
            ->where($where_data)
            ->get();
        $results = convertStdClassObjToArray($qry);
        $results = decryptArray($results);
        $res = array(
            'results' => $results
        );
        json_output($res);
    }

    public function getBeneficiaryschools(Request $req)
    {

        $district_id = $req->input('district_id');
        if ($district_id != '') {
            $district_id = json_decode($district_id);
            // $district_id = explode(',',$district_id);
        }
        $qry = DB::table('beneficiary_information as t1')
            ->select('t2.name', 't2.id', 't2.code', 't1.district_id', 't3.name as district_name')
            ->join('school_information as t2', 't1.school_id', '=', 't2.id')
            ->join('districts as t3', 't2.district_id', '=', 't3.id')
            ->groupBy('t2.id')
            ->where(array('enrollment_status' => 1, 'beneficiary_status' => 4));

        if (validateisNumeric($district_id)) {
            $qry = $qry->where(array('t3.id' => $district_id));
        } else if (!empty($district_id)) {
            $qry = $qry->whereIn('t3.id', $district_id);
        }
        $qry = $qry->get();

        $results = convertStdClassObjToArray($qry);
        $results = decryptArray($results);
        $res = array(
            'results' => $results
        );
        //var_dump($res);
        json_output($res);
    }

    public function getBeneficiariesSchools(Request $req)
    {
        // Check if user is authenticated first
        if (!\Auth::check()) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }
        $logged_in_user = \Auth::user()->id;
        $district_id = $req->input('district_id');
        $user_access_point = \Auth::user()->access_point_id;
        if ($district_id != '') {
            $district_id = json_decode($district_id);
        }
        $qry = DB::table('beneficiary_information as t1')
            ->select(DB::raw("t2.name,t2.id,t2.code,t3.name as district_name,CONCAT_WS(' ',t2.code,t2.name) as codename"))
            ->join('school_information as t2', 't1.school_id', '=', 't2.id')
            ->join('districts as t3', 't2.district_id', '=', 't3.id');
        if ($user_access_point == 3 || $user_access_point == 4) {//assigned districts
            $qry->whereIn('t2.district_id', function ($query) use ($logged_in_user) {
                $query->select(DB::raw('user_district.district_id'))
                    ->from('user_district')
                    ->whereRaw('user_district.user_id=' . $logged_in_user);
            });
        }
        $qry->groupBy('t2.id')
            ->where(array('enrollment_status' => 1, 'beneficiary_status' => 4));

        if (validateisNumeric($district_id)) {
            $qry = $qry->where(array('t3.id' => $district_id));
        } else if (!empty($district_id)) {
            $qry = $qry->whereIn('t3.id', $district_id);
        }
        $results = $qry->get();
        $res = array(
            'results' => $results
        );
        json_output($res);
    }

    function funSaveschoolotherdetails($table_name, $table_data, $where_data)
    {
        $sql_query = DB::table($table_name)
            ->where($where_data)
            ->count();
        if ($sql_query > 0) {
            $table_data['updated_at'] = Carbon::now();
            $table_data['updated_by'] = $this->user_id;
            $previous_data = getPreviousRecords($table_name, $where_data);
            updateRecord($table_name, $previous_data, $where_data, $table_data, $this->user_id);
        } else {
            $table_data['created_at'] = Carbon::now();
            $table_data['created_by'] = $this->user_id;
            insertRecord($table_name, $table_data, $this->user_id);
        }
    }

    function getActiveBeneficiarygrades($school_id)
    {
        $qry = DB::table('beneficiary_information as t1')
            ->select('current_school_grade')
            ->where(array('school_id' => $school_id, 'enrollment_status' => 1, 'beneficiary_status' => 4))
            ->groupBy('t1.current_school_grade')
            ->get();
        $result = convertStdClassObjToArray($qry);
        $result = decryptArray($result);
        //get the details
        return $result;
    }

    function funccheckSchoolfeesdata($year, $term_id, $school_id)
    {
        $school_grades = $this->getActiveBeneficiarygrades($school_id);
        $sql_query = DB::table('school_feessetup')
            ->where(array('year' => $year, 'school_id' => $school_id, 'term_id' => $term_id))
            ->whereIn('grade_id', $school_grades)
            ->count();

        if ($sql_query < count($school_grades)) {

            $resp = array('success' => false, 'message' => 'Enter School fees Details for all the grades and Enrolment Type to proceed!!');
            json_output($resp);

            exit();
        }

    }

    function funcUpdateschoolFees($table_name, $where_data, $table_data)
    {
        $qry = DB::table($table_name)
            ->where($where_data)
            ->get();
        if (count($qry) > 0) {
            $table_data['updated_at'] = Carbon::now();
            $table_data['updated_by'] = $this->user_id;
            $previous_data = getPreviousRecords($table_name, $where_data);
            $success = updateRecord($table_name, $previous_data, $where_data, $table_data, $this->user_id);

        } else {
            $table_data['created_at'] = Carbon::now();
            $table_data['created_by'] = $this->user_id;

            $success = insertRecord($table_name, $table_data, $this->user_id);

        }

    }
    public function ValidateFeeSetup($year,$term_id,$school_id,$school_type_id,$batch_id)
    {
        // ($school_id, $enrollment_type_id, $grade_id, $year

        // $where = array(
        //     'school_enrollment_id' => $enrollment_type_id,
        //     'year' => $year,
        //     'school_id' => $school_id,
        //     'grade_id' => $grade_id
        // );
        $running_agency_id=Db::table('school_information')->where('id',$school_id)->value('running_agency_id');
        $results=$this->getschool_feessetupDetails($year,$term_id,$school_id,$school_type_id,$running_agency_id);
        $school_fees_data=$results['results'];
        $grade_fees_key=[8=>0,9=>1,10=>2,11=>3,12=>4];
        $grades = [8,9,10,11,12];
        $weekly_boarders=['.1w','.2w','.3w'];
        $dayscholars = ['.1d','.2d','.3d'];
        $boarders=['.1b','.2b','.3b'];
        foreach($grades as $grade)
        {
            $fees_for_agency=Db::table('school_running_agencies')->where('id',$running_agency_id)->selectraw('varied_fees,grade_nine_twelve,d_fees,b_fees')->get();
            $fees_for_agency= $fees_for_agency[0];
            $fees_setup= $school_fees_data[$grade_fees_key[$grade]];
           
            foreach($fees_setup as $key=>$fee)
            {
              
               if($key!="school_grade")
                 {

                    if($grade!=9 || $grade!=12)
                    {

                       
                     if($fees_for_agency->varied_fees==2 )
                     {
                       
                        if(in_array($key,$dayscholars))
                        {
                           
                                if($fees_for_agency->d_fees!=$fee)
                                {
                                    
                                    return false;
                                    // return response()->json([
                                    //     "success"=>false,
                                    //     "message"=> "Invalid Fees Given in fee setup"                      
                                    // ]);

                                }
     
                        }

                        if(in_array($key,$boarders))
                        {
                            //dd($key."-".$grade."-".$fees_for_agency->b_fees."-".$fee);
                               
                           // dd(gettype($fees_for_agency->b_fees)."-".gettype($fee));
                                if($fees_for_agency->b_fees!=$fee)
                                {
                                   
                                   return false;
                                    return response()->json([
                                        "success"=>false,
                                        "message"=> "Invalid Fees Given in fee setup"                      
                                    ]);

                                }
     
                        }

                     }
                    }else{
                        if($fees_for_agency->grade_nine_twelve==2)
                        {
                            if($fees_for_agency->d_fees!=$fee)
                            {
                                
                                return false;
                                return response()->json([
                                    "success"=>false,
                                    "message"=> "Invalid Fees Given in fee setup"                      
                                ]);

                            }

                            if($fees_for_agency->b_fees!=$fee)
                            {
                              
                                return false;
                                return response()->json([
                                    "success"=>false,
                                    "message"=> "Invalid Fees Given in fee setup"                      
                                ]);

                            }
                            
                        }
                    }
                     //dd($fees_for_agency);
                 }

                  
                  
                   
        
            }

        }
        return true;
        

    }


    public function getschool_feessetupDetails($year,$term_id,$school_id,$school_type_id,$running_agency)
    {
        // $year = $req->input('year_of_enrollment');
        // $term_id = $req->input('term_id');
        // $school_id = $req->input('school_id');
        // $school_type_id = $req->input('school_type_id');
        // $running_agency= $req->input('running_agency');
        try {
            $term_id = 3;

            //job 17/3/2022
           $running_agency_details=Db::table('school_running_agencies')
          // ->where('id',$running_agency)->selectRaw('varied_fees,b_fees,wb_fees,d_fees')->get()->toArray();
           ->where('id',$running_agency)->selectRaw('varied_fees,b_fees,d_fees,grade_nine_twelve')->get()->toArray();
           //dd($running_agency_details);
            //end 
            $qry_grade = DB::table('school_grades')
                ->whereIn('id', array(4, 5, 6, 7, 8, 9, 10, 11, 12))
                ->get();
            $results_grade = convertStdClassObjToArray($qry_grade);
            $dataset = array();
            $data = array();
            $res=$this->ValidateFeeSetup($year,$term_id,$school_id,$school_type_id,$batch_id);
            if (count($results_grade) > 0) {
                $enrollments = getSchoolenrollments($school_type_id, true);
                foreach ($results_grade as $rec_grade) {
                    $grade_id = $rec_grade['id'];
                    $data['school_grade'] = $grade_id;
                    if(count($running_agency_details)>0)
                    {
                        $data['varied_fees']=$running_agency_details[0]->varied_fees;// 2 means should be constant
                        $data['d_fees']=$running_agency_details[0]->d_fees;
                        $data['b_fees']=$running_agency_details[0]->b_fees;
                        $data['grade_nine_twelve']=$running_agency_details[0]->grade_nine_twelve;
                        //$data['wb_fees']=$running_agency_details[0]->wb_fees;
                    }
                    
                    $enrolldata = array();
                    foreach ($enrollments as $enrols) {
                        $enrollment_type_id = $enrols->school_enrollment_id;
                        $enrollment_type_code = $enrols->code;
                        $fees = getAnnualSchoolFees($school_id, $enrollment_type_id, $grade_id, $year);
                        // $enrolldata['.' . 1 . $enrollment_type_code] = $fees['term1_fees'];
                        // $enrolldata['.' . 2 . $enrollment_type_code] = $fees['term2_fees'];
                        // $enrolldata['.' . 3 . $enrollment_type_code] = $fees['term3_fees'];
                        $enrolldata['.' . '3d'] = $fees['day_fee'];
                        $enrolldata['.' . '3b'] = $fees['boarder_fee'];
                        $enrolldata['.' . '3w'] = $fees['weekly_boarder_fee'];
                        $data = array_merge($data, $enrolldata);
                    }
                   
                    $dataset[] = $data;
                }
            }
            //get running agency

            $res = array(
                'success' => true,
                'results' => $dataset,
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
        return $res;
    }

    public function SaveFeesForPrivateSchools(Request $req)
    {
        $school_id=$req->input('school_id');
        $is_school_reset_fees=$req->input('is_school_reset_fees');
        $grades=[8,9,10,11,12];
        foreach($grades as $school_grade){

        $table_name = 'school_feessetup';
        //1. Day getBeneficiary_requestpaymentInfo
        $table_data = array(
            'year' => date('Y'),
            'school_enrollment_id' => 1,
            'school_id' => $school_id,
            'grade_id' => $school_grade,
            'term1_fees' => 0,
            'term2_fees' => 0,
            'term3_fees' => 0
        );
        $where_data = array(
            'year' => date('Y'),
            'school_enrollment_id' => 1,
            'school_id' => $school_id,
            'grade_id' => $school_grade
        );
        $this->funcUpdateschoolFees($table_name, $where_data, $table_data);

         //1. boarder
         $table_data = array(
            'year' => date('Y'),
            'school_enrollment_id' => 1,
            'school_id' => $school_id,
            'grade_id' => $school_grade,
            'term1_fees' => 0,
            'term2_fees' => 0,
            'term3_fees' => 0
        );
        $where_data = array(
            'year' => date('Y'),
            'school_enrollment_id' => 2,
            'school_id' => $school_id,
            'grade_id' => $school_grade
        );
        $this->funcUpdateschoolFees($table_name, $where_data, $table_data);
         //1. weekly 
         $table_data = array(
            'year' => date('Y'),
            'school_enrollment_id' => 3,
            'school_id' => $school_id,
            'grade_id' => $school_grade,
            'term1_fees' => 0,
            'term2_fees' => 0,
            'term3_fees' => 0
        );
        $where_data = array(
            'year' => date('Y'),
            'school_enrollment_id' => 3,
            'school_id' => $school_id,
            'grade_id' => $school_grade
        );
        $this->funcUpdateschoolFees($table_name, $where_data, $table_data);
    

    }
    return response()->json([
        "success"=>true,
        "message"=> "Details saved" 

    ]);

    }
    public function savePaymentVerificationDetails(Request $req) {        
        try {
            if (Auth::check()) {
                if($this->user_id !=0)
                {
                    if($this->dms_id==0)
                    {
                        $res = array(
                            'success' => false,
                            'message' => "Invalid DMS credentials, please contact system Admin"
                        );
                        return response()->json($res);

                    }
                }else{
                    $res = array(
                        'success' => false,
                        'message' => "user is not authenticated,Kindly Reload system"
                    );
                    return response()->json($res);
                }
            }else{
                $res = array(
                    'success' => false,
                    'message' => "User is not authenticated,Kindly Reload system"
                );
                return response()->json($res);                
            }
            $post_data = $req->all();            
            $batch_id = $post_data['batch_id'];
            // $term_id = 1;
            $term_id = $post_data['term_id'];
            $school_id = $post_data['school_id'];
            $batch_no = $post_data['batch_no'];
            $checklistissued_by = $post_data['checklistissued_by'];
            $checklistissued_on = $post_data['checklistissued_on'];
            $year_of_enrollment = $post_data['year_of_enrollment'];
            $checklist_form_id = $post_data['checklist_form_id'];
            $school_headteacher = $req->input('school_headteacher');
            $headteacher_tel_no = $req->input('headteacher_tel_no');
            $school_guidance_teacher_name = $req->input('guidance_counselling_teacher');
            $school_guidance_teacher_phone = $req->input('guidance_counselling_teacher_phone_no');
            

            // $ben_data = DB::table('payment_checklists_track_details as t0')
            // ->join('beneficiary_information as t1', 't0.girl_id', '=', 't1.id')
            // ->join('school_information as t2', 't1.school_id', '=', 't2.id')
            // ->join('districts as t3', 't2.district_id', '=', 't3.id')
            // ->join('provinces as t4', 't3.province_id', '=', 't4.id')
            // ->select('t1.*')
            // ->where(array('t1.school_id' => $school_id, 't1.beneficiary_status' => 4, 't1.enrollment_status' => 1))
            // ->where('t1.payment_eligible', 1)
            // // ->where('t0.track_id', $checklist_form_id)
            // ->where(function ($query) {
            //     $query->where('t1.under_promotion', 0)
            //         ->orWhereNull('t1.under_promotion');
            // })->groupBy('t1.id')->get();
            // dd($ben_data);

            $where_data = array(
                'designation_id' => 1,
                'school_id' => $school_id
            );
            $table_data = array(
                'designation_id' => 1,
                'school_id' => $school_id,
                'full_names' => $school_headteacher,
                'telephone_no' => $headteacher_tel_no,
                'mobile_no' => $headteacher_tel_no
            );
            $this->funSaveschoolotherdetails('school_contactpersons', $table_data, $where_data);
            //job  16/3/2022
            //school guidance teacher info
            $where_data = array(
                'designation_id' => 2,//school guidance designation
                'school_id' => $school_id
            );
            $table_data = array(
                'designation_id' => 2,
                'school_id' => $school_id,
                'full_names' =>   $school_guidance_teacher_name,
                'telephone_no' =>  $school_guidance_teacher_phone,
                'mobile_no' =>  $school_guidance_teacher_phone
            );
            $result=$this->funSaveschoolotherdetails('school_contactpersons', $table_data, $where_data);
            //end
            //school information
            $school_type_id = $req->input('school_type_id');
            $district_id = $req->input('district_id');
            $cwac_id = $req->input('cwac_id');
            $running_agency_id =$req->input('running_agency_id');
            $table_data = array(
                'district_id' => $district_id,
                'cwac_id' => $cwac_id,
                'school_type_id' => $school_type_id,
                "running_agency_id"=> $running_agency_id
            );
            $where_data = array('id' => $school_id);
            $this->funSaveschoolotherdetails('school_information', $table_data, $where_data);
            //job 0n 29/03/2022
            if($running_agency_id==3) {//private               
                $grades=[4,5,6,7,8,9,10,11,12];
                foreach($grades as $school_grade){

                    $table_name = 'school_feessetup';
                    //1. Day
                    $table_data = array(
                        'year' => date('Y'),
                        'school_enrollment_id' => 1,
                        'school_id' => $school_id,
                        'grade_id' => $school_grade,
                        'term_id' => $term_id,
                        'term1_fees' => 0,
                        'term2_fees' => 0,
                        'term3_fees' => 0
                    );
                    $where_data = array(
                        'year' => date('Y'),
                        'school_enrollment_id' => 1,
                        'school_id' => $school_id,
                        'grade_id' => $school_grade
                    );
                    $this->funcUpdateschoolFees($table_name, $where_data, $table_data);

                     //1. boarder
                     $table_data = array(
                        'year' => date('Y'),
                        'school_enrollment_id' => 1,
                        'school_id' => $school_id,
                        'grade_id' => $school_grade,
                        'term1_fees' => 0,
                        'term2_fees' => 0,
                        'term3_fees' => 0
                    );
                    $where_data = array(
                        'year' => date('Y'),
                        'school_enrollment_id' => 2,
                        'school_id' => $school_id,
                        'grade_id' => $school_grade
                    );
                    $this->funcUpdateschoolFees($table_name, $where_data, $table_data);
                     //1. weekly 
                     $table_data = array(
                        'year' => date('Y'),
                        'school_enrollment_id' => 3,
                        'school_id' => $school_id,
                        'grade_id' => $school_grade,
                        'term1_fees' => 0,
                        'term2_fees' => 0,
                        'term3_fees' => 0
                    );
                    $where_data = array(
                        'year' => date('Y'),
                        'school_enrollment_id' => 3,
                        'school_id' => $school_id,
                        'grade_id' => $school_grade
                    );
                    $this->funcUpdateschoolFees($table_name, $where_data, $table_data);
                }
            }
            //school bank details
            $branch_name = $req->input('branch_name');
            $sort_code = $req->input('sort_code');
            $account_no = $req->input('account_no');
            $bank_id = $req->input('bank_id');
            $where_data = array(
                'school_id' => $school_id
            );
            $table_data = array(
                'school_id' => $school_id,
                'bank_id' => $bank_id,
                'account_no' => aes_encrypt($account_no),
                'branch_name' => $branch_name,
                'is_activeaccount' => 1
            );
            $this->funSaveschoolotherdetails('school_bankinformation', $table_data, $where_data);
            //bank branch sort code
            $where_data = array('id' => $branch_name);
            $table_data = array(
                'sort_code' => $sort_code
            );
            $this->funSaveschoolotherdetails('bank_branches', $table_data, $where_data);
            $additional_remarks = $post_data['additional_remarks'];
            $folder_id = $post_data['folder_id'];
            $district_id = $post_data['district_id'];
            $table_name = 'payment_verificationbatch';
            $table_data = array(
                'school_id' => $school_id,
                'term_id' => $term_id,
                'submitted_on' => Carbon::now(),
                'submitted_by' => $this->user_id,
                'checklistissued_by' => $checklistissued_by,
                'checklistissued_on' => $checklistissued_on,
                'additional_remarks' => $additional_remarks,
                'district_id' => $district_id,
                'year_of_enrollment' => $year_of_enrollment,
                'checklist_form_id' => $checklist_form_id
            );
            if (validateisNumeric($batch_id)) {
                $where = array('id' => $batch_id);
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $this->user_id;
                $is_same_school = DB::table('payment_verificationbatch')
                    ->where(array('id' => $batch_id, 
                        'school_id' => $school_id, 
                        'year_of_enrollment' => $year_of_enrollment))
                    ->count();
                if ($is_same_school < 3) {
                    // //check if checklist form id has been captured b4
                    // $checkDetails = DB::table($table_name)->where('checklist_form_id', $checklist_form_id)->first();
                    // if (!is_null($checkDetails)) {
                    //     $res = array(
                    //         'success' => false,
                    //         'message' => 'This Checklist Form ID has been entered, Batch Number: ' . $checkDetails->batch_no
                    //     );
                    //     return response()->json($res);
                    //     exit();
                    // }
                    $where_prev = array(
                        'batch_id' => $batch_id
                    );
                    // $prev_data = getPreviousRecords('beneficiary_enrollments', $where_prev);
                    // deleteRecord('beneficiary_enrollments', $prev_data, $where_prev, $this->user_id);

                    $this->addDefaultPaymentBeneficiaries($batch_id, $school_id, $term_id, $year_of_enrollment, $checklist_form_id);
                }
                $previous_data = getPreviousRecords($table_name, $where);
                updateRecord($table_name, $previous_data, $where, $table_data, $this->user_id);
            } else {
                //check if checklist form id has been captured b4
                $checkDetails = DB::table($table_name)->where('checklist_form_id', $checklist_form_id)->first();
                // if (!is_null($checkDetails)) {
                //     $res = array(
                //         'success' => false,
                //         'message' => 'This Checklist Form Number/ID has been captured, Batch Number: ' . $checkDetails->batch_no
                //     );
                //     return response()->json($res);
                //     exit();
                // }
                $batch_no = generatePaymentverificationBatchNo();
                $parent_id = 4;
                $main_module_id = 1;
                $folder_id = createDMSParentFolder($parent_id, $main_module_id, $batch_no, '', $this->dms_id);
                $table_data['created_at'] = Carbon::now();
                $table_data['created_by'] = $this->user_id;
                $table_data['batch_no'] = $batch_no;
                $table_data['folder_id'] = $folder_id;
                $table_data['added_by'] = $this->user_id;
                $table_data['added_on'] = Carbon::now();
                $table_data['status_id'] = 1;
                $batch_id = insertRecordReturnId($table_name, $table_data, $this->user_id);
                if (validateisNumeric($batch_id)) {
                    $this->addDefaultPaymentBeneficiaries($batch_id, $school_id, $term_id, $year_of_enrollment, $checklist_form_id);
                    // exit();
                    // $this->validateBeneficiaryEnrollment2($batch_id);
                }
            }
            if (validateisNumeric($batch_id)) {
                $res = array(
                    'success' => true,
                    'message' => 'Payment Information saved successfully',
                    'batch_no' => $batch_no,
                    'folder_id' => $folder_id,
                    'batch_id' => $batch_id
                );
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'Problem encountered while saving data. Try again later!!'
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

    public function BackupGirlsToKnockOut(Request $req)
    {
        $qry= DB::table('beneficiary_enrollments as t1')
        ->join('payment_verificationbatch as t2','t2.id','=','t1.batch_id')
        ->join('beneficiary_information as t3','t1.beneficiary_id','=','t3.id')
        ->leftjoin('districts as t4', 't2.district_id', '=', 't4.id')
        ->join('beneficiary_payment_records as t5','t5.enrollment_id','=','t1.id')
        ->where('t5.payment_request_id',26)
        ->whereIn('t3.batch_id',[19,20,24,25,26,27])
       //->whereIn('t1.submission_status',[2])
      // ->where('status_id',3)
       ->selectRaw('t1.*');//payment validation
       $results = convertStdClassObjToArray($qry->get());
     
       if(count($results)>1000)
       {
        $total_loop=ceil(count($results)/1000);
        $start_index=0;
        $end_index=1000;
            for($i=1;$i<=$total_loop;$i++)
            {

                $results_to_insert=array();
                foreach($results as $key=>$result)
                {
                    if($key>=$start_index && $key<=$end_index)
                    {
                        $results_to_insert[]=$result;
                    }
                }
                DB::table('beneficiary_enrollments_knocked_out_girls_backup')->insert($results_to_insert);
                $results_to_insert=[];
                        if($i!=$total_loop-1){
                        $start_index=$end_index+1;
                        $end_index=$start_index+1000;
                        }else{
                            $start_index=$end_index+1;
                            $end_index=(count($results)-1);
                        }

            }
       }else{
          DB::table('beneficiary_enrollments_knocked_out_girls_backup')->insert($results);
       }
       $res = array(
        'success' => true,
        'message' => returnMessage($results),
        'results' => $results
        );
       return response()->json($res);
    }

    public function KnockOutGirls(Request $req) {
        return;       
        $qry= DB::table('beneficiary_enrollments as t1')
            ->join('beneficiary_payment_records as t2','t2.enrollment_id','=','t1.id')
            ->join('beneficiary_information as t3','t1.beneficiary_id','=','t3.id')
            ->where('t2.payment_request_id',26)
            ->whereIn('t3.batch_id',[19,20,24,25,26,27])
            ->selectraw('t1.id,decrypt(t1.term1_fees) as t1_fees,decrypt(t1.term2_fees) as t2_fees,
                decrypt(t1.annual_fees) as annual_fees,t3.batch_id as batch_identity,t1.batch_id')
            ->join('payment_verificationbatch as t4','t4.id','=','t1.batch_id');
        $results = $qry->get()->toArray();
        $result="";
        foreach($results as $result) {          
            switch($result->batch_identity) {
                case 19:               
                   $annual_fees=$result->annual_fees-$result->t1_fees;
                   $result=DB::table('beneficiary_enrollments as t1')
                        ->where('id',$result->id)
                        ->update(['t1.term1_fees'=>DB::raw('encryptVal(0)'),
                            't1.annual_fees'=>DB::raw('encryptVal('.$annual_fees.')')]);                
                    break;
                case 27:
                //default:        
                    $annual_fees=$result->annual_fees-($result->t1_fees+$result->t2_fees);  
                    $result=DB::table('beneficiary_enrollments as t1')
                        ->where('id',$result->id)
                        ->update(['t1.term1_fees'=>DB::raw('encryptVal(0)'),
                            't1.term2_fees'=>DB::raw('encryptVal(0)'),
                            't1.annual_fees'=>DB::raw('encryptVal('.$annual_fees.')')]);
                    break;
            }
        }
        $res = array(
            'success' => true,
            'message' => returnMessage($results),
            'results' => $results
        );
        return response()->json($res);
    }

    public function downloadKnockedOutGirls(Request $req)
    {
       
     return Excel::download(new KnockedOutGirls,'knockedOutGirls.xlsx');
        
       
    }
    // public function getPayment_verificationDatentry(Request $req)
    // {
    //     try {
    //         //filter by districts get from the user rights
    //         $user_id = $this->user_id;
    //         $district_id = $req->input('district_id');
    //         $year = $req->input('year');
    //         $term = $req->input('term');
    //         $status_id = $req->input('status_id');
    //         $groups = getUserGroups($user_id);
    //         $superUserID = getSuperUserGroupId();
    //         $districts = getUserDistricts($user_id);
    //         $qry = DB::table('payment_verificationbatch as t1')
    //             ->select(DB::raw('TOTAL_WEEKDAYS(t1.added_on,now()) as added_span,TOTAL_WEEKDAYS(t1.submitted_on,now()) as submission_span,t10.checklist_number,
    //                           SUM(CASE WHEN t9.is_validated = 1 THEN decrypt(t9.annual_fees) ELSE 0 END) AS confirmed_total_fees,
    //                           SUM(CASE WHEN t9.passed_rules = 1 THEN decrypt(t9.annual_fees) ELSE 0 END) AS rule_total_fees,t1.checklist_form_id,
    //                           count(t9.beneficiary_id) as no_of_girls,sum(decrypt(t9.annual_fees)) as total_fees, t1.*,decrypt(t8.first_name) as submitted_by,
    //                           t7.name as status_name,t1.id as batch_id, t2.name as school_name,t3.name as district_name,t4.name as term_name, t1.term_id as term_id,
    //                           SUM(IF(t9.passed_rules=1,1,0)) AS passed_rules_girls,decrypt(t5.first_name) as added_by_name,t6.name as province_name,t2.running_agency_id'))
    //             ->join('school_information as t2', 't1.school_id', '=', 't2.id')
    //             ->leftJoin('districts as t3', 't1.district_id', '=', 't3.id')
    //             ->leftJoin('school_terms as t4', 't1.term_id', '=', 't4.id')
    //             ->leftJoin('users as t5', 't1.added_by', '=', 't5.id')
    //             ->leftJoin('provinces as t6', 't3.province_id', '=', 't6.id')
    //             ->leftJoin('payment_verification_statuses as t7', 't1.status_id', '=', 't7.id')
    //             ->leftJoin('beneficiary_enrollments as t9', 't1.id', '=', 't9.batch_id')
    //             ->leftJoin('users as t8', 't1.submitted_by', '=', 't8.id')
    //             ->leftJoin('payment_checklists_track as t10', 't1.checklist_form_id', '=', 't10.id');
    //         // $qry->where('t1.added_on', '>=', '2025-06-03 00:00:00');
    //         $qry->where('t1.added_on', '>=', '2025-11-01');
    //         if ($status_id == 1) {
    //             $qry = in_array($superUserID, $groups) == 1 ? $qry->whereRaw("1=1") : $qry->where('added_by', $user_id);
    //         } 
    //         else if ($status_id == 2) {
    //             $qry = in_array($superUserID, $groups) == 1 ? $qry->whereRaw("1=1") : $qry->whereIn('t2.district_id', $districts);
    //         }

    //         if (isset($year) && $year != '') {
    //             $qry->where(array('t1.year_of_enrollment' => $year));
    //         }
    //         if (isset($term) && $term != '') {
    //             $qry->where(array('t1.term_id' => $term));
    //         }
    //         if (isset($district_id) && $district_id != '') {
    //             $qry->where(array('t2.district_id' => $district_id));
    //         }
    //         // $qry->where(array('t1.term_id' => 2));
    //         $qry->where(array('t1.status_id' => $status_id))
    //             ->havingRaw('COUNT(t1.id) > 0')
    //             ->orderBy('t1.added_on', 'asc')
    //             ->groupBy('t1.id');


    //         $results = $qry->get();
    //         $res = array(
    //             'success' => true,
    //             'results' => $results,
    //             'message' => 'All is well'
    //         );
    //     } catch (\Exception $e) {
    //         $res = array(
    //             'success' => false,
    //             'message' => $e->getMessage()
    //         );
    //     } catch (\Throwable $throwable) {
    //         $res = array(
    //             'success' => false,
    //             'message' => $throwable->getMessage()
    //         );
    //     }
    //     return response()->json($res);
    // }

    public function getPayment_verificationDatentry(Request $req)
    {
        try {
            $user_id = $this->user_id;
            $district_id = $req->input('district_id');
            $year = $req->input('year');
            $term = $req->input('term');
            $status_id = $req->input('status_id');
            $groups = getUserGroups($user_id);
            $superUserID = getSuperUserGroupId();
            $districts = getUserDistricts($user_id);
            
            $qry = DB::table('payment_verificationbatch as t1')
                ->select(DB::raw('TOTAL_WEEKDAYS(t1.added_on,now()) as added_span,TOTAL_WEEKDAYS(t1.submitted_on,now()) as submission_span,
                    t10.checklist_number,
                    SUM(CASE WHEN t9.is_validated = 1 THEN decrypt(t9.annual_fees) ELSE 0 END) AS confirmed_total_fees,
                    SUM(CASE WHEN t9.passed_rules = 1 THEN decrypt(t9.annual_fees) ELSE 0 END) AS rule_total_fees,
                    t1.checklist_form_id,
                    count(t9.beneficiary_id) as no_of_girls,sum(decrypt(t9.annual_fees)) as total_fees, 
                    t1.*,decrypt(t8.first_name) as submitted_by,
                    t7.name as status_name,t1.id as batch_id, t2.name as school_name,
                    t3.name as district_name,t4.name as term_name, t1.term_id as term_id,
                    SUM(IF(t9.passed_rules=1,1,0)) AS passed_rules_girls,
                    decrypt(t5.first_name) as added_by_name,t6.name as province_name,t2.running_agency_id,
                    t2.school_type_id, t2.cwac_id,
                    t2.latitude,
                    t2.longitude,
                    t2.facility_type_id as facility_type,
                    sbi.bank_id,
                    bd.name as bank_name,
                    sbi.branch_name,
                    bb.name as branch_name_text,
                    decrypt(sbi.account_no) as account_no,
                    sbi.sort_code,
                    st.name as school_type,
                    stc.name as facility_type_name,
                    sc_head.full_names as school_headteacher,
                    sc_head.telephone_no as head_telephone,
                    sc_head.mobile_no as head_mobile,
                    sc_head.email_address as head_email,
                    sc_head.mobile_no as headteacher_tel_no,
                    sc_guidance.full_names as guidance_counselling_teacher,
                    sc_guidance.mobile_no as guidance_counselling_teacher_phone_no,
                    ed_acc.bank_name as education_grant_bank,
                    ed_acc.branch_name as education_grant_branch,
                    ed_acc.account_number as education_grant_account,
                    ed_acc.sort_code as education_grant_sort_code,
                    adm_acc.bank_name as administration_fees_bank,
                    adm_acc.branch_name as administration_fees_branch,
                    adm_acc.account_number as administration_fees_account,
                    adm_acc.sort_code as administration_fees_sort_code'))
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                ->leftJoin('districts as t3', 't1.district_id', '=', 't3.id')
                ->leftJoin('school_terms as t4', 't1.term_id', '=', 't4.id')
                ->leftJoin('users as t5', 't1.added_by', '=', 't5.id')
                ->leftJoin('provinces as t6', 't3.province_id', '=', 't6.id')
                ->leftJoin('payment_verification_statuses as t7', 't1.status_id', '=', 't7.id')
                ->leftJoin('beneficiary_enrollments as t9', 't1.id', '=', 't9.batch_id')
                ->leftJoin('users as t8', 't1.submitted_by', '=', 't8.id')
                ->leftJoin('payment_checklists_track as t10', 't1.checklist_form_id', '=', 't10.id')
                ->leftJoin('school_bankinformation as sbi', 't2.id', '=', 'sbi.school_id')
                ->leftJoin('bank_details as bd', 'sbi.bank_id', '=', 'bd.id')
                ->leftJoin('bank_branches as bb', 'sbi.branch_name', '=', 'bb.id')
                ->leftJoin('school_types as st', 't2.school_type_id', '=', 'st.id')
                ->leftJoin('school_types_categories as stc', 't2.facility_type_id', '=', 'stc.id')
                ->leftJoin('school_contactpersons as sc_head', function($join) {
                    $join->on('t2.id', '=', 'sc_head.school_id')
                        ->where('sc_head.designation_id', '=', 1);
                })
                ->leftJoin('school_contactpersons as sc_guidance', function($join) {
                    $join->on('t2.id', '=', 'sc_guidance.school_id')
                        ->where('sc_guidance.designation_id', '=', 2);
                })
                ->leftJoin('district_bank_accounts as ed_acc', function($join) {
                    $join->on('t2.district_id', '=', 'ed_acc.district_id')
                        ->where('ed_acc.bank_account_type_id', '=', 2);
                })
                ->leftJoin('district_bank_accounts as adm_acc', function($join) {
                    $join->on('t2.district_id', '=', 'adm_acc.district_id')
                        ->where('adm_acc.bank_account_type_id', '=', 3);
                });
                
            $qry->where('t1.added_on', '>=', '2025-11-01');
            
            if ($status_id == 1) {
                $qry = in_array($superUserID, $groups) == 1 ? $qry->whereRaw("1=1") : $qry->where('added_by', $user_id);
            } 
            else if ($status_id == 2) {
                $qry = in_array($superUserID, $groups) == 1 ? $qry->whereRaw("1=1") : $qry->whereIn('t2.district_id', $districts);
            }

            if (isset($year) && $year != '') {
                $qry->where(array('t1.year_of_enrollment' => $year));
            }
            if (isset($term) && $term != '') {
                $qry->where(array('t1.term_id' => $term));
            }
            if (isset($district_id) && $district_id != '') {
                $qry->where(array('t2.district_id' => $district_id));
            }
            
            $qry->where(array('t1.status_id' => $status_id))
                ->havingRaw('COUNT(t1.id) > 0')
                ->orderBy('t1.added_on', 'asc')
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
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getPaymentValidationDetails(Request $req)
    {
        try {
            //filter by districts get from the user rights
            $user_id = \Auth::user()->id;
            $district_id = $req->input('district_id');
            $year = $req->input('year');
            $term = $req->input('term');
            $status_id = $req->input('status_id');
            $groups = getUserGroups($user_id);
            $superUserID = getSuperUserGroupId();
            $districts = getUserDistricts($user_id);
            $qry = DB::table('payment_verificationbatch as t1')
                ->select(DB::raw('TOTAL_WEEKDAYS(t1.added_on,now()) as 
                    added_span,TOTAL_WEEKDAYS(t1.submitted_on,now()) as submission_span,
                    SUM(CASE WHEN t9.is_validated = 1 THEN decrypt(t9.annual_fees) 
                        ELSE 0 END) AS confirmed_total_fees,
                    SUM(CASE WHEN t9.passed_rules = 1 THEN decrypt(t9.annual_fees) 
                        ELSE 0 END) AS rule_total_fees, t10.checklist_number,
                    SUM(IF(t9.is_validated=1,1,0)) AS validated_girls, 
                    SUM(IF(t9.passed_rules=1,1,0)) AS passed_rules_girls,
                    SUM(IF(t9.submission_status=2,1,0)) AS submitted_girls,
                    COUNT(t9.beneficiary_id) as no_of_girls,
                    SUM(decrypt(t9.annual_fees)) as total_fees, 
                    t1.*,decrypt(t8.first_name) as submitted_by,
                    t7.name as status_name,t1.id as batch_id, t2.name as school_name,
                    t3.name as district_name,t4.name as term_name,
                    decrypt(t5.first_name) as added_by_name,t6.name as province_name,
                    t2.running_agency_id'))
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                ->leftJoin('districts as t3', 't1.district_id', '=', 't3.id')
                ->leftJoin('school_terms as t4', 't1.term_id', '=', 't4.id')
                ->leftJoin('users as t5', 't1.added_by', '=', 't5.id')
                ->leftJoin('provinces as t6', 't3.province_id', '=', 't6.id')
                ->leftJoin('payment_verification_statuses as t7', 't1.status_id', '=', 't7.id')
                ->leftJoin('beneficiary_enrollments as t9', 't1.id', '=', 't9.batch_id')
                ->leftJoin('users as t8', 't1.submitted_by', '=', 't8.id')
                ->leftJoin('payment_checklists_track as t10', 't1.checklist_form_id', '=', 
                    't10.id');
            $qry = in_array($superUserID, $groups) == 1 ? $qry->whereRaw("1=1") : $qry->whereIn('t2.district_id', $districts);
            if (isset($year) && $year != '') {
                $qry->where(array('t1.year_of_enrollment' => $year));
            } else {
                $qry->where(array('t1.year_of_enrollment' => 2025));
            }
            if (isset($term) && $term != '') {
                $qry->where(array('t1.term_id' => $term));
            } else {
                $qry->where(array('t9.term_id' => 2));
                // $qry->where('t1.added_on', '>=', '2025-06-03 00:00:00');
            }
            if (isset($district_id) && $district_id != '') {
                $qry->where(array('t2.district_id' => $district_id));
                // $qry->where(array('t2.district_id' => 75));
            }
            $qry->where(array('t1.status_id' => $status_id))
                ->havingRaw('count(t2.id) > 0')
                ->orderBy('t1.added_on', 'asc')
                ->groupBy('t1.id');

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

    public function getBeneficiary_receiptingInfoStr(Request $req)
    {
        $batch_id = $req->payment_receipts_id;

        $qry = DB::table('beneficiary_information as t1')
            ->select('t1.*', 't2.first_name', 't2.last_name', 't3.school_grade', 't2.beneficiary_id as beneficiary_no')
            ->join('beneficiary_enrollments as t3', 't1.enrollment_id', '=', 't3.id')
            ->leftJoin('payments_receipting_details as t2', 't1.beneficiary_id', '=', 't2.id')
            ->where(array('t1.payment_receipts_id<>' => $batch_id))
            ->groupBy('t3.id')
            ->get();

        $results = convertStdClassObjToArray($qry);

        $results = decryptArray($results);

        $res = array(
            'results' => $results
        );
        //var_dump($res);
        json_output($res);
        //the query

    }

    public function getbeneficiaryReceiptsStr1(Request $req)
    {
        $batch_id = $req->payment_receipts_id;
        $beneficiary_id = $req->beneficiary_id;


        $qry = DB::table('beneficiary_information as t1')
            ->select('t2.*', 't1.first_name', 't1.last_name', 't3.school_grade', 't1.beneficiary_id as beneficiary_no')
            ->join('beneficiary_enrollments as t3', 't1.id', '=', 't3.beneficiary_id')
            ->join('payments_receipting_details as t2', 't3.id', '=', 't2.enrollment_id')
            ->where(array('t2.payment_receipts_id' => $batch_id, 't1.id' => $beneficiary_id))
            ->groupBy('t2.id')
            ->get();
        $results = convertStdClassObjToArray($qry);

        $results = decryptArray($results);

        $res = array(
            'results' => $results
        );
        //var_dump($res);
        json_output($res);
    }

    public function getbeneficiaryReceiptsStr(Request $req)
    {
        $payment_receipt_id = $req->input('payment_receipt_id');
        try {
            $qry = DB::table('beneficiary_receipting_details as t1')
                ->where('payment_receipt_id', $payment_receipt_id);
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

    public function getPaymentEnrolmentBatchInfo(Request $request)
    {
        try {
            $batch_id = $request->input('batch_id');
            $qry = DB::table('payment_verificationbatch as t1')
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                ->join('districts as t5', 't2.district_id', '=', 't5.id')
                ->join('payment_verification_statuses as t6', 't1.status_id', '=', 't6.id')
                ->leftJoin('users as t3', 't1.added_by', '=', 't3.id')
                ->leftJoin('payment_checklists_track as t4', 't1.checklist_form_id', '=', 't4.id')
                ->select(DB::raw("t1.*,CONCAT_WS('-',t2.code,t2.name) as school_name,
                                        CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as author,t4.checklist_number,
                                        t5.name as district_name,t6.name as batch_status"))
                ->where('t1.id', $batch_id);
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

      //job on 27/03/2022
    public function CheckDataEntryDuplicates()
    {
        $year_of_enrollment= date('Y');

        $res = DB::table('payment_verificationbatch as t1')
        ->join('beneficiary_enrollments as t2','t1.id','t2.batch_id')
        ->join('beneficiary_information as t3','t3.id','t2.beneficiary_id')
        ->join('school_information as t4', 't2.school_id', '=', 't4.id')
        ->selectraw('count(Distinct t2.batch_id) as count,
        t3.beneficiary_id,decrypt(t3.first_name) as first_name,decrypt(t3.last_name) as last_name,t4.name as school_name')
        ->where(['t2.year_of_enrollment'=>$year_of_enrollment])
        ->groupby('t2.batch_id')
        ->havingRaw('count(Distinct t2.batch_id)  > 1')//to change to 1
        ->get()
        ->toArray();
        if(count($res)>0)
        {
            return true;
        }else{
            return false;
        }   
    }

    public function getPaymentsDataEntryDuplicates(Request $req)
    {
        $res=[];
        try{
        $year_of_enrollment= date('Y');
        $beneficiary_id= $req->input('beneficiary_id');

        // $res=Db::table('beneficiary_enrollments as t1')
        // ->join('beneficiary_information as t2','t1.beneficiary_id','t2.id')
        // ->join(DB::raw("(SELECT t2.beneficiary_id,count(*) as ben_enrollments_records_count
        // FROM beneficiary_enrollments  as t2 group by t2.beneficiary_id having ben_enrollments_records_count>1) as recs"),'recs.beneficiary_id','=','t2.id')
        // ->join('school_information as t3', 't2.school_id', '=', 't3.id')
        // //->groupby('t1.beneficiary_id')
        // ->selectraw('t1.id,t2.beneficiary_id,count(t1.beneficiary_id) as count,decrypt(t2.first_name) as first_name,decrypt(t2.last_name) as last_name,t3.name as school_name')
        // ->where('year_of_enrollment',$year_of_enrollment)
        // //->havingraw('count>1')
        // ->get();
     
        //job on 29/03/2022
          if(isset($beneficiary_id) && $beneficiary_id!=="")
          {
            $res = DB::table('payment_verificationbatch as t1')
            ->join('beneficiary_enrollments as t2','t1.id','t2.batch_id')
            ->join('beneficiary_information as t3','t3.id','t2.beneficiary_id')
            ->join('school_information as t4', 't2.school_id', '=', 't4.id')
            ->selectraw('count(Distinct t2.batch_id) as count,t1.batch_no,
            t3.beneficiary_id,t2.id,decrypt(t3.first_name) as first_name,decrypt(t3.last_name) as last_name,t4.name as school_name')
            ->where(['t2.year_of_enrollment'=>$year_of_enrollment,"t3.beneficiary_id"=> $beneficiary_id])
            ->groupby('t2.batch_id')
            ->havingRaw('count(Distinct t2.batch_id)  > 1')//to change to 1
            ->get();
          }else{
            $res = DB::table('payment_verificationbatch as t1')
               ->join('beneficiary_enrollments as t2','t1.id','t2.batch_id')
               ->join('beneficiary_information as t3','t3.id','t2.beneficiary_id')
               ->join('school_information as t4', 't2.school_id', '=', 't4.id')
               ->selectraw('count(Distinct t2.batch_id) as count,
               t3.beneficiary_id,decrypt(t3.first_name) as first_name,decrypt(t3.last_name) as last_name,t4.name as school_name')
               ->where(['t2.year_of_enrollment'=>$year_of_enrollment])
               ->groupby('t2.batch_id')
               ->havingRaw('count(Distinct t2.batch_id)  > 1')//to change to 1
               ->get();
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

    // public function getBeneficiariesPaymentinfo(Request $req)
    // {
    //     try {//GEWEL 2 update on term id to enable term 2 payments
    //         $school_id = $req->input('school_id');
    //         $year_of_enrollment = $req->input('year_of_enrollment');
    //         $qry = DB::table('beneficiary_information as t1')
    //             // q2.batch_no,q1.batch_id,
    //             // CASE
    //             //     WHEN q1.term_id = 1 THEN NULL
    //             //     ELSE q1.term_id
    //             // END AS term_id,
    //             // CASE 
    //             //     WHEN q1.term_id = 1 THEN NULL
    //             //     ELSE q1.batch_id
    //             // END AS batch_id,
    //             // CASE 
    //             //     WHEN q1.term_id = 1 THEN NULL
    //             //     ELSE q2.batch_no
    //             // END AS batch_no,                    
    //             ->select(DB::raw("(select id from beneficiary_enrollments a 
    //                 where a.beneficiary_id = t1.id and 
    //                 a.year_of_enrollment = $year_of_enrollment 
    //                 group by a.beneficiary_id) as entry_status,
    //                 t2.name as school_name, t3.name as school_district,
    //                 t7.name as beneficiary_school_status,t5.hhh_fname,
    //                 t6.name as hhh_district,decrypt(t1.first_name) as first_name,
    //                 t1.id,t1.beneficiary_id,decrypt(t1.last_name) as last_name,
    //                 q2.batch_no,q1.batch_id,
    //                 decrypt(q1.annual_fees) as verification_school_fees,
    //                 t1.school_id as cur_school_id,q1.school_id as enrol_school_id"))
    //             ->join('school_information as t2', 't1.school_id', '=', 't2.id')
    //             ->leftJoin('beneficiary_enrollments as q1', 
    //                 function ($join) use ($year_of_enrollment) {
    //                     $join->on('t1.id', '=', 'q1.beneficiary_id')
    //                         ->where(array('q1.year_of_enrollment' => $year_of_enrollment));
    //                     })
    //             ->leftJoin('payment_verificationbatch as q2', 'q1.batch_id', '=', 'q2.id')
    //             ->join('districts as t3', 't2.district_id', '=', 't3.id')
    //             ->join('provinces as t4', 't3.province_id', '=', 't4.id')
    //             ->leftJoin('beneficiary_school_statuses as t7', 
    //                 't1.beneficiary_school_status', '=', 't7.id')
    //             ->leftJoin('households as t5', 't1.household_id', '=', 't5.id')
    //             ->leftJoin('cwac as t8', 't5.cwac_id', '=', 't8.id')
    //             ->leftJoin('districts as t6', 't1.district_id', '=', 't6.id')
    //             ->where(array('t2.id' => $school_id, 
    //                 't1.beneficiary_status' => 4, 
    //                 't1.enrollment_status' => 1, 
    //                 't1.payment_eligible' => 1,
    //                 'q1.year_of_enrollment' => 2025
    //                 ))
    //             ->whereRaw('q1.term_id <> 2')
    //             ->where(function($q) {
    //                 $q->where('t1.under_promotion', 0)
    //                   ->orWhereNull('t1.under_promotion')
    //                   ->orWhereNull('q1.term_id');
    //             })
    //             ->groupBy('t1.id');
    //         $results = $qry->get();
    //         $res = array(
    //             'success' => true,
    //             'results' => $results,
    //             'message' => 'Records fetched successfully'
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

    public function getBeneficiariesPaymentinfo(Request $req)
    {
        try {//GEWEL 2 update on term id to enable term 2 payments
            $school_id = $req->input('school_id');
            $year_of_enrollment = $req->input('year_of_enrollment');
            $qry = DB::table('beneficiary_information as t1')// q2.batch_no,q1.batch_id,
                // CASE WHEN q1.term_id = 1 THEN NULL ELSE q1.term_id END AS term_id,
                // CASE WHEN q1.term_id = 1 THEN NULL ELSE q1.batch_id END AS batch_id,
                // CASE WHEN q1.term_id = 1 THEN NULL ELSE q2.batch_no END AS batch_no,

                // (select id from beneficiary_enrollments a 
                // where a.beneficiary_id = t1.id and 
                // a.year_of_enrollment = $year_of_enrollment 
                // group by a.beneficiary_id) as entry_status,
                ->select(DB::raw("0 as entry_status,
                    t2.name as school_name, t3.name as school_district,
                    t7.name as beneficiary_school_status,t5.hhh_fname,
                    t6.name as hhh_district,decrypt(t1.first_name) as first_name,
                    t1.id,t1.beneficiary_id,decrypt(t1.last_name) as last_name,
                    null as batch_no,null as batch_id, 0 as verification_school_fees,
                    t1.school_id as cur_school_id,null as enrol_school_id"))
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                ->leftJoin('beneficiary_enrollments as q1', 'q1.beneficiary_id', '=', 't1.id')
                // ->leftJoin('beneficiary_enrollments as q1', 
                //     function ($join) use ($year_of_enrollment) {
                //         $join->on('t1.id', '=', 'q1.beneficiary_id')
                //             ->where(array('q1.year_of_enrollment' => $year_of_enrollment));
                //         })
                ->leftJoin('payment_verificationbatch as q2', 'q1.batch_id', '=', 'q2.id')
                ->join('districts as t3', 't2.district_id', '=', 't3.id')
                ->join('provinces as t4', 't3.province_id', '=', 't4.id')
                ->leftJoin('beneficiary_school_statuses as t7', 
                    't1.beneficiary_school_status', '=', 't7.id')
                ->leftJoin('households as t5', 't1.household_id', '=', 't5.id')
                ->leftJoin('cwac as t8', 't5.cwac_id', '=', 't8.id')
                ->leftJoin('districts as t6', 't1.district_id', '=', 't6.id')
                ->where(array('t2.id' => $school_id, 
                    't1.beneficiary_status' => 4, 
                    't1.enrollment_status' => 1, 
                    't1.payment_eligible' => 1,
                    'q1.year_of_enrollment' => 2025
                    ))
                // ->whereRaw('q1.term_id <> 2 AND q1.has_signed <> 1')
                ->whereRaw('q1.has_signed <> 1')
                // ->whereRaw('q1.term_id <> 2')
                ->where(function($q) {
                    $q->where('t1.under_promotion', 0)
                      ->orWhereNull('t1.under_promotion')
                      ->orWhereNull('q1.term_id');
                })
                ->groupBy('t1.id');
            // $results = $qry->toSql();
            // dd($results);
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => 'Records fetched successfully'
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
    // getBeneficiariesPaymentinfo
    public function getBeneficiaryEnrollmentbatchinfo(Request $req)
    {
        try {
            $post_data = $req->all();
            $batch_id = $post_data['batch_id'];
            $school_id = DB::table('payment_verificationbatch')
                ->where('id', $batch_id)
                ->value('school_id');
            $qry = DB::table('beneficiary_information as t1')
                ->select(DB::raw("t1.*, t1.id as girl_id,
                   t2.id as enrollement_id, t2.*, t4.name as home_district, 
                   t1.beneficiary_id as beneficiary_no,t2.school_grade-1 as performance_grade,t1.mobile_phone_parent_guardian as mobile_phone_number_for_parent_guardian,
                  t1.mobile_phone_cwac_contact_person as mobile_phone_number_for_cwac_contact_person,
                  decrypt(t1.first_name) as first_name,decrypt(t1.last_name) as last_name,
                  0 as term1_fees,0 as term2_fees,decrypt(t2.term3_fees) as term3_fees,
        decrypt(t2.exam_fees) as exam_fees,(decrypt(t2.term3_fees) + decrypt(t2.exam_fees)) as annual_fees,
                  CASE WHEN t2.school_grade=9 THEN t1.grade9_exam_no WHEN t2.school_grade=12 THEN t1.grade12_exam_no ELSE '' END as exam_number
                 "))
                ->leftjoin('beneficiary_enrollments as t2', 't1.id', '=', 't2.beneficiary_id')
                ->join('school_information as t3', 't2.school_id', '=', 't3.id')
                ->join('districts as t4', 't1.district_id', '=', 't4.id')
                // ->where(array('t2.batch_id' => $batch_id))
                ->where(array(
                    't1.school_id' => $school_id,
                    't1.beneficiary_status' => 4,
                    't1.verification_recommendation' => 1,
                    't2.term_id' => 3
                ))
                ->groupBy('t1.id');
            
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => 'Successful'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        } catch (\Throwable $t) {
            $res = array(
                'success' => false,
                'message' => $t->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getBeneficiaryEnrollmentBatchInfoArchive(Request $req)
    {
        $post_data = $req->all();
        $batch_id = $post_data['batch_id'];
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->select(DB::raw("t1.id as girl_id,
                                  t2.id as enrollement_id, t2.*, t4.name as home_district, t1.beneficiary_id as beneficiary_no,
                                  t2.school_grade-1 as performance_grade,
                                  t7.id as added_for_payments,decrypt(t1.first_name) as first_name,decrypt(t1.last_name) as last_name,
                                  decrypt(t2.term1_fees) as term1_fees,decrypt(t2.term2_fees) as term2_fees,decrypt(t2.term3_fees) as term3_fees,decrypt(t2.exam_fees) as exam_fees,decrypt(t2.annual_fees) as annual_fees,
                                  CASE WHEN t2.school_grade=9 THEN t1.grade9_exam_no WHEN t2.school_grade=12 THEN t1.grade12_exam_no ELSE '' END as exam_number"))
                ->join('beneficiary_enrollments as t2', 't1.id', '=', 't2.beneficiary_id')
                ->join('school_information as t3', 't2.school_id', '=', 't3.id')
                ->join('districts as t4', 't1.district_id', '=', 't4.id')
                ->leftJoin('beneficiary_payment_records as t7', 't2.id', '=', 't7.enrollment_id')
                ->where(array('t2.batch_id' => $batch_id));
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
        } catch (\Throwable $t) {
            $res = array(
                'success' => false,
                'message' => $t->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getActivebeneficiaries_details(Request $req)
    {
        $start = $req->input('start');
        $limit = $req->input('limit');
        $filter = $req->input('filter');
        $where_statement = "";
        if (isset($filter)) {// No filter passed in
            $whereClauses = array();
            $filters = json_decode($filter);
            if ($filters != NULL) {
                foreach ($filters as $filter) {
                    switch ($filter->property) {
                        case 'beneficiary_id' :
                            $whereClauses[] = "beneficiary_id like '%" . ($filter->value) . "%'";
                            break;
                        case 'first_name' :
                            $whereClauses[] = "decrypt(first_name) like '%" . ($filter->value) . "%'";
                            break;
                        case 'last_name' :
                            $whereClauses[] = "decrypt(last_name) like '%" . ($filter->value) . "%'";
                            break;
                        case 'hhh_fname' :
                            $whereClauses[] = "hhh_fname like '%" . ($filter->value) . "%'";
                            break;
                    }
                }
            }
            $testwhere_value = array_filter($whereClauses);
            if (!empty($testwhere_value)) {
                $where_statement = implode(' AND ', $whereClauses);
                $where_statement = 'and (' . $where_statement . ')';
            }
        }
        $qry = DB::select("select * from vw_paymentben_information where beneficiary_status=4 AND enrollment_status = 1 AND payment_eligible = 1 AND (under_promotion=0 OR under_promotion IS NULL) $where_statement limit $start,$limit");
        $totalCount = DB::select("select count(id) as counter from vw_paymentben_information where beneficiary_status=4 AND enrollment_status = 1 AND payment_eligible = 1 AND (under_promotion=0 OR under_promotion IS NULL) $where_statement");
        $results = convertStdClassObjToArray($qry);
        $results = decryptArray($results);
        $res = array(
            'results' => $results,
            'totalCount' => $totalCount[0]->counter
        );
        json_output($res);
    }

    public function searchBeneficiaries4Reconciliation(Request $req)
    {
        $start = $req->input('start');
        $limit = $req->input('limit');
        $filter = $req->input('filter');
        $whereClauses = array();
        $filter_string = '';
        if (isset($filter)) {
            $filters = json_decode($filter);
            if ($filters != NULL) {
                foreach ($filters as $filter) {
                    switch ($filter->property) {
                        case 'beneficiary_id' :
                            $whereClauses[] = "beneficiary_id like '%" . ($filter->value) . "%'";
                            break;
                        case 'first_name' :
                            $whereClauses[] = "decrypt(first_name) like '%" . ($filter->value) . "%'";
                            break;
                        case 'last_name' :
                            $whereClauses[] = "decrypt(last_name) like '%" . ($filter->value) . "%'";
                            break;
                        case 'school_name' :
                            $whereClauses[] = "school_name like '%" . ($filter->value) . "%'";
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
            $qry = DB::table('vw_paymentben_information')
                ->select(DB::raw('id,1 as do_transfer,beneficiary_id,enrollment_status_name,beneficiary_status_name,
                                  current_school_grade,beneficiary_school_status,school_id,
                                  decrypt(first_name) as first_name,decrypt(last_name) as last_name,school_name'));
            if ($filter_string != '') {
                $qry->whereRAW($filter_string);
            }
            $total = $qry->count();
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

    public function savePaymentbenTransferinfo(Request $req)
    {
        try {
            $beneficiary_id = $req->input('beneficiary_id');
            $payment_request_id = $req->input('batch_id');
            $term_id = $req->input('term_id');
            $year_of_enrollment = $req->input('year_of_enrollment');
            $from_school_id = $req->input('from_school_id');
            $school_id = $req->input('to_school_id');
            $reason_id = $req->input('reason_id');
            $request_comment = $req->input('remarks');
            $transfer_school_status = $req->input('transfer_school_status');
            $transfer_grade = $req->input('transfer_grade');
            //$result_check=DB::table('beneficiary_enrollments')->where('beneficiary_id',$beneficiary_id)->count;

            //job on 29/03/2022
            //$beneficiary_id=129;
            $duplicate_count = DB::table('payment_verificationbatch as t1')
               ->join('beneficiary_enrollments as t2','t1.id','t2.batch_id')
               ->join('beneficiary_information as t3','t3.id','t2.beneficiary_id')
               ->selectraw('count(Distinct t2.batch_id) as count')
               ->where(['t3.id'=>$beneficiary_id,'t2.year_of_enrollment'=>$year_of_enrollment])
               ->groupby('t2.batch_id')
               ->havingRaw('count(Distinct t2.batch_id)  > 0')
               ->get()
               ->toArray();
           
             
               if(count($duplicate_count)>0)
               {
                   
                $res = array(
                    'success' => false,
                    'message' => 'Girl you trying add has already been captured for enrollment year please contact ICT!!'
                );
                return response()->json($res);

               }
               //emd 
             
            if (!validateisNumeric($school_id)) {
                $res = array(
                    'success' => false,
                    'message' => 'Process aborted, please do it again!!'
                );
                return response()->json($res);
            }

            $qry = DB::table('beneficiary_enrollments')
                ->where(array('beneficiary_id' => $beneficiary_id, 'year_of_enrollment' => $year_of_enrollment, 'term_id' => $term_id))
                ->count();

            if ($qry == 0) {
                //save the transfers details 1st
                $transfer_data = array(
                    'girl_id' => $beneficiary_id,
                    'from_school_id' => $from_school_id,
                    'to_school_id' => $school_id,
                    'reason_id' => $reason_id,
                    'transfer_type' => 2,
                    'status' => 2,
                    'stage' => 3,
                    'request_by' => $this->user_id,
                    'requested_on' => Carbon::now(),
                    'request_comment' => $request_comment,
                    'approval_by' => $this->user_id,
                    'approval_comment' => 'Auto Approval',
                    'approval_date' => Carbon::now(),
                    'archived_by' => $this->user_id,
                    'archive_comment' => 'Auto Archive',
                    'archive_date' => Carbon::now(),
                    'effective_from_year' => $year_of_enrollment,
                    'effective_from_term' => $term_id,
                    'transfer_grade' => $transfer_grade,
                    'transfer_school_status' => $transfer_school_status,
                    'created_by' => $this->user_id,
                    'source_module' => 'Payments - Data Entry Transfers',
                );
                //the details
                $response = insertRecord('beneficiary_school_transfers', $transfer_data, $this->user_id);
                //then update the beneficiary information and save enrollments
                if ($response) {
                    $where_data = array(
                        'id' => $beneficiary_id
                    );
                    $table_data = array(
                        'school_id' => $school_id,
                        'updated_at' => Carbon::now(),
                        'updated_by' => $this->user_id
                    );
                    $rec = DB::table('beneficiary_information')->where($where_data)->first();
                    if ($rec) {
                        $prev_data = array('school_id' => $rec->school_id, 'id' => $rec->id);
                        updateRecord('beneficiary_information', array($prev_data), $where_data, $table_data, $this->user_id);
                        $school_fees = getAnnualSchoolFees($school_id, $rec->beneficiary_school_status, $rec->current_school_grade, $year_of_enrollment);
                        $annual_fees = ($school_fees['term1_fees'] + $school_fees['term2_fees'] + $school_fees['term3_fees']);
                        $data = array(
                            'beneficiary_id' => $rec->id,
                            'school_id' => $school_id,
                            'year_of_enrollment' => $year_of_enrollment,
                            'term_id' => $term_id,
                            'school_grade' => $rec->current_school_grade,
                            'beneficiary_schoolstatus_id' => $rec->beneficiary_school_status,
                            'enrollment_status_id' => 0,
                            'term1_fees' => aes_encrypt($school_fees['term1_fees']),
                            'term2_fees' => aes_encrypt($school_fees['term2_fees']),
                            'term3_fees' => aes_encrypt($school_fees['term3_fees']),
                            'annual_fees' => aes_encrypt($annual_fees),
                            'batch_id' => $payment_request_id,
                            'has_signed' => 0
                        );
                        //where data
                        $where_data = array(
                            'beneficiary_id' => $rec->id,
                            'school_id' => $school_id,
                            'year_of_enrollment' => $year_of_enrollment
                            //'term_id' => $term_id
                        );
                        $this->saveBeneficiaryEnrollmentdata($data, $where_data);
                    }
                }
                $res = array(
                    'success' => true,
                    'message' => 'Beneficiary Details Saved successfully'
                );
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'The Beneficiary Details have been captured for the selected Enrollment period, contact the ICT Support!!'
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

    public function removeBeneficiaryenrollement(Request $req)
    {
        try {
            $table_name = 'beneficiary_enrollments';
            $post_data = $req->all();
            $id = $post_data['id'];
            $where_data = array('id' => $id);
            $qry = DB::table('beneficiary_enrollments as t1')
                ->where($where_data)
                ->count();
            if ($qry > 0) {
                $previous_data = getPreviousRecords($table_name, $where_data);
                $res = deleteRecord($table_name, $previous_data, $where_data, $this->user_id);
                //delete any errors
                DB::table('enrollment_error_log')
                    ->where('enrollment_id', $id)
                    ->delete();
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'Record not found!!'
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

    public function removeSelectedBeneficiaries(Request $req)
    {
        $selected = $req->input('selected');
        $selected_ids = json_decode($selected);
        try {
            DB::table('beneficiary_enrollments')
                ->whereIn('id', $selected_ids)
                ->delete();
            //delete any errors
            DB::table('enrollment_error_log')
                ->whereIn('enrollment_id', $selected_ids)
                ->delete();
            $res = array(
                'success' => true,
                'message' => 'Enrollment details deleted successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function addSelectedBeneficiariesReceiptDetails(Request $req)
    {
        $year = $req->input('year');
        $term = $req->input('term');
        $receipts_batch_id = $req->input('receipts_batch_id');
        $selected = $req->input('selected');
        $selected_ids = json_decode($selected);
        try {
            $params = array();
            foreach ($selected_ids as $selected_id) {
                $params[] = array(
                    'term_id' => $term,
                    'payment_year' => $year,
                    'enrollment_id' => $selected_id,
                    'payment_receipts_id' => $receipts_batch_id,
                    'created_by' => $this->user_id
                );
            }
            DB::table('payments_receipting_details')
                ->insert($params);
            $res = array(
                'success' => true,
                'message' => 'Receipt details removed successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function removeSelectedBeneficiariesReceiptDetails(Request $req)
    {
        $selected = $req->input('selected');
        $selected_ids = json_decode($selected);
        try {
            DB::table('payments_receipting_details')
                ->whereIn('id', $selected_ids)
                ->delete();
            DB::table('beneficiary_receipting_details')
                ->whereIn('payment_receipt_id', $selected_ids)
                ->delete();
            $res = array(
                'success' => true,
                'message' => 'Receipt details removed successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function deletePaymentDisbursementdetails(Request $req)
    {
        $table_name = 'payment_disbursement_details';
        $payment_disbursement_id = $req->input('payment_disbursement_id');
        $payment_request_id = $req->input('payment_request_id');
        $where_data = array(
            'payment_request_id' => $payment_request_id,
            'id' => $payment_disbursement_id
        );
        try {
            $qry = DB::table($table_name)
                ->where($where_data)
                ->count();
            if ($qry > 0) {
                $previous_data = getPreviousRecords($table_name, $where_data);
                deleteRecord($table_name, $previous_data, $where_data, $this->user_id);
            }
            $res = array(
                'success' => true,
                'message' => 'Payment disbursement details removed successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function func_deleteBenpaymentrecord(Request $req)
    {
        try {
            $table_name = 'beneficiary_payment_records';
            $enrollment_id = $req->input('enrollment_id');
            $payment_request_id = $req->input('payment_request_id');
            $where_data = array('payment_request_id' => $payment_request_id, 'enrollment_id' => $enrollment_id);
            $qry = DB::table($table_name)
                ->where($where_data)
                ->count();
            $response = false;
            if ($qry > 0) {
                $previous_data = getPreviousRecords($table_name, $where_data);
                $response = deleteRecord($table_name, $previous_data, $where_data, $this->user_id);
            }
            if ($response['success'] == true) {
                $resp = array('success' => true, 'message' => 'Enrollment Details Removed Successfully');
            } else {
                $resp = array('success' => false, 'message' => 'Error occurred enrollment details not deleted');
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

    public function getBeneficiaryValidationEnrollmentInfo(Request $req)
    {
        $post_data = $req->all();
        $batch_id = $post_data['batch_id'];
        $flag_filter = $post_data['flag_filter'];
        $status_id = $post_data['status_id'];
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->select(DB::raw("t1.id as girl_id,t1.is_letter_received,
                    t1.verification_school_fees,t2.id as enrollement_id,
                    t2.*, t4.name as home_district,t1.beneficiary_id as beneficiary_no, t2.school_grade-1 as performance_grade,
                    decrypt(t2.term1_fees) as term1_fees,decrypt(t2.term2_fees) as term2_fees,
                    decrypt(t2.term3_fees) as term3_fees,decrypt(t2.exam_fees) as exam_fees,
                    decrypt(t2.annual_fees) as annual_fees,
                    decrypt(t1.first_name) as first_name,decrypt(t1.last_name) as last_name,
                    t1.mobile_phone_parent_guardian as mobile_phone_number_for_parent_guardian,
                    t1.mobile_phone_cwac_contact_person as mobile_phone_number_for_cwac_contact_person,
                    CASE WHEN t2.school_grade=9 THEN t1.grade9_exam_no 
                    WHEN t2.school_grade=12 THEN t1.grade12_exam_no ELSE '' END as exam_number"))
                ->join('beneficiary_enrollments as t2', 't1.id', '=', 't2.beneficiary_id')
                ->join('school_information as t3', 't2.school_id', '=', 't3.id')
                ->join('districts as t4', 't1.district_id', '=', 't4.id')
                ->where(array('t2.batch_id' => $batch_id,'t2.term_id' => 2));
            if (isset($status_id) && $status_id != '') {
                if ($status_id == 3) {
                    $qry->where(function ($query) {
                        $query->whereIn('t2.submission_status', array(0, 1))
                            ->orWhereNull('t2.submission_status');
                    });
                }
            }
            if (isset($flag_filter) && $flag_filter != '') {
                if ($flag_filter == 1) {
                    $qry->where('t2.passed_rules', 1);
                } else {
                    $qry->where(function ($query) {
                        $query->where('t2.passed_rules', 0)
                            ->orWhereNull('t2.passed_rules');
                    });
                }
            }
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => 'Records fetched successfully'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        } catch (\Throwable $t) {
            $res = array(
                'success' => false,
                'message' => $t->getMessage()
            );
        }
        return response()->json($res);
    }

    //add validate ben school payment request
    public function addValidateBenschoolpaymentrequestOld(Request $req)
    {
        $payment_request_id = $req->input('payment_request_id');
        $term_id = $req->input('term_id');
        $payment_year = $req->input('payment_year');
        $data = $req->input('data');
        $records = explode(',', $data);
    
        try {
            foreach ($records as $school_id) {
                $qry = DB::table('beneficiary_enrollments as t5')
                    ->select('t5.id as enrollment_id')
                    ->join('school_information as t2', 't5.school_id', '=', 't2.id')
                    ->leftJoin('beneficiary_payment_records as t7', 't5.id', '=', 't7.enrollment_id')
                    ->where(array('t2.id' => $school_id, 't5.is_validated' => 1, 't5.year_of_enrollment' => $payment_year))
                    ->whereNull('t7.id')
                    ->get();
                $data = array();
                //just to be sure...already we have checked for null of payment records id....
                foreach ($qry as $rec) {
                    $enrollment_id = $rec->enrollment_id;
                    $table_data = array(
                        'enrollment_id' => $enrollment_id
                    );
                    $qry = DB::table('beneficiary_payment_records')
                        ->where($table_data)
                        ->count();
                    if ($qry == 0) {
                        $table_data['created_at'] = Carbon::now();
                        $table_data['created_by'] = $this->user_id;
                        $data[] = array(
                            'created_at' => Carbon::now(),
                            'created_by' => $this->user_id,
                            'enrollment_id' => $enrollment_id,
                            'payment_request_id' => $payment_request_id);
                    }
                }
                //then loop two for the insertion details
                $size = 100;
                $chunks = array_chunk($data, $size);
                foreach ($chunks as $chunk) {
                    DB::table('beneficiary_payment_records')->insert($chunk);
                }
            }
            $res = array(
                'success' => true,
                'message' => 'Beneficiary Enrollment & Payment details Saved Successfully!!'
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
    public function addValidateBenschoolpaymentrequest(Request $req) // 31st March 2026
    {
        $payment_request_id = $req->input('payment_request_id');
        $term_id = $req->input('term_id');
        $payment_year = $req->input('payment_year');
        $data = $req->input('data');
        $records = explode(',', $data);
    
        try {
            foreach ($records as $school_id) {
                $qry = DB::table('beneficiary_payresponses_report as t5')
                    ->select('t5.id as enrollment_id')
                    ->join('school_information as t2', 't5.school_id', '=', 't2.id')
                    ->leftJoin('beneficiary_payment_records as t7', 't5.id', '=', 't7.enrollment_id')
                    ->where(array('t2.id' => $school_id, 't5.year_of_enrollment' => $payment_year))
                    ->whereNull('t7.id')
                    ->get();
                $data = array();
                //just to be sure...already we have checked for null of payment records id....
                foreach ($qry as $rec) {
                    $enrollment_id = $rec->enrollment_id;
                    $table_data = array(
                        'enrollment_id' => $enrollment_id
                    );
                    $qry = DB::table('beneficiary_payment_records')
                        ->where($table_data)
                        ->count();
                    if ($qry == 0) {
                        $table_data['created_at'] = Carbon::now();
                        $table_data['created_by'] = $this->user_id;
                        $data[] = array(
                            'created_at' => Carbon::now(),
                            'created_by' => $this->user_id,
                            'enrollment_id' => $enrollment_id,
                            'payment_request_id' => $payment_request_id);
                    }
                }
                //then loop two for the insertion details
                $size = 100;
                $chunks = array_chunk($data, $size);
                foreach ($chunks as $chunk) {
                    DB::table('beneficiary_payment_records')->insert($chunk);
                }
            }
            $res = array(
                'success' => true,
                'message' => 'Beneficiary Enrollment & Payment details Saved Successfully!!'
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

    public function addValidateBenpaymentrequest(Request $req)
    {
        $payment_request_id = $req->input('payment_request_id');
        $data = $req->input('data');
        $records = explode(',', $data);
        $data = array();
        foreach ($records as $enrollment_id) {
            if (validateisNumeric($enrollment_id)) {
                $table_data = array(
                    'enrollment_id' => $enrollment_id,
                    'payment_request_id' => $payment_request_id
                );
                $qry = DB::table('beneficiary_payment_records')
                    ->where($table_data)
                    ->count();
                if ($qry == 0) {
                    $table_data['created_at'] = Carbon::now();
                    $table_data['created_by'] = $this->user_id;
                    $data[] = array(
                        'created_at' => Carbon::now(),
                        'created_by' => $this->user_id,
                        'enrollment_id' => $enrollment_id,
                        'payment_request_id' => $payment_request_id);
                }
            }
        }
        $size = 500;
        $chunks = array_chunk($data, $size);
        foreach ($chunks as $chunk) {
            DB::table('beneficiary_payment_records')->insert($chunk);
        }
        $resp = array(
            'success' => true,
            'message' => 'Beneficiary Enrollment & Payment details Saved Successfully'
        );
        json_output($resp);
    }

    public function addBeneficiaryenrollement(Request $req)
    {//term 2 modified
        $post_data = $req->all();
        $data = $post_data['data'];
        $term_id = $post_data['term_id'];
        $school_id = $post_data['school_id'];
        $batch_id = $post_data['batch_id'];
        $year_of_enrollment = $post_data['year_of_enrollment'];
        $records = explode(',', $data);
        foreach ($records as $beneficiary_id) {
            $qry = DB::table('beneficiary_information as t1')
                ->where(array('id' => $beneficiary_id))
                ->get();
            foreach ($qry as $rec) {
                $school_fees = getAnnualSchoolFees($school_id, $rec->beneficiary_school_status, $rec->current_school_grade, $year_of_enrollment);
                $annual_fees = ($school_fees['term1_fees'] + $school_fees['term2_fees'] + $school_fees['term3_fees']);
                $table_data = array(
                    'beneficiary_id' => $rec->id,
                    'school_id' => $school_id,
                    'year_of_enrollment' => $year_of_enrollment,
                    'term_id' => $term_id,
                    'school_grade' => $rec->current_school_grade,
                    'beneficiary_schoolstatus_id' => $rec->beneficiary_school_status,
                    'enrollment_status_id' => 0,
                    'term1_fees' => aes_encrypt($school_fees['term1_fees']),
                    'term2_fees' => aes_encrypt($school_fees['term2_fees']),
                    'term3_fees' => aes_encrypt($school_fees['term3_fees']),
                    'annual_fees' => aes_encrypt($annual_fees),
                    'batch_id' => $batch_id,
                    'has_signed' => 0
                );
                //where data
                $where_data = $data = array(
                    'beneficiary_id' => $rec->id,
                    'school_id' => $school_id,
                    'year_of_enrollment' => $year_of_enrollment,
                    'term_id' => $term_id
                );
                $this->saveBeneficiaryEnrollmentdata($table_data, $where_data);
            }
        }
        $resp = array('success' => true, 'message' => 'Beneficiary Enrolment Saved Successfully');
        json_output($resp);
    }

    function saveBeneficiaryEnrollmentdata($table_data, $where_data)
    {
        $qry = DB::table('beneficiary_enrollments as t1')
            ->where($where_data)
            ->count();
        if ($qry == 0) {
            $table_data['created_at'] = Carbon::now();
            $table_data['created_by'] = $this->user_id;
            insertRecordReturnId('beneficiary_enrollments', $table_data, $this->user_id);
        }
    }

    function getPreviousTermdetails($term_id, $year_of_enrollment, $school_grade)
    {
        if ($term_id == 1) {
            $term_id = 3;
            $year_of_enrollment = ($year_of_enrollment - 1);
            $school_grade = ($school_grade - 1);
        } else if ($term_id == 2) {
            $term_id = 1;
        } else {
            $term_id = 2;
        }
        return array(
            'term_id' => $term_id,
            'year_of_enrollment' => $year_of_enrollment,
            'school_grade' => $school_grade
        );
    }

    function saveBeneficiaryPermanceattdetails($enrollement_id, $value)
    {
        $benficiary_id = $value['beneficiary_id'];
        $performance_grade = $value['performance_grade'];
        $prevtermDetails = $this->getPreviousTermdetails($value['term_id'], $value['year_of_enrollment'], $value['school_grade']);
        $table_data = array(
            'enrollment_id' => $enrollement_id,
            'beneficiary_id' => $benficiary_id,
            'school_id' => $value['school_id'],
            'term_id' => $prevtermDetails['term_id'],
            'year_of_enrollment' => $prevtermDetails['year_of_enrollment'],
            'grade' => $performance_grade,
            'science_score' => $value['science_score'],
            'mathematics_score' => $value['mathematics_score'],
            'mathsclass_average' => $value['mathsclass_average'],
            'english_score' => $value['english_score'],
            'engclass_average' => $value['engclass_average'],
            'scienceclass_average' => $value['scienceclass_average'],
            'aggregate_average_score' => $value['aggregate_average_score'],
            'benficiary_attendance' => $value['benficiary_attendance']
        );
        $where_data = array('beneficiary_id' => $benficiary_id,
            'term_id' => $prevtermDetails['term_id'],
            'year_of_enrollment' => $prevtermDetails['year_of_enrollment'],
            'grade' => $performance_grade
        );
        $table_name = 'beneficiary_attendanceperform_details';
        $qry = DB::table($table_name)
            ->where($where_data)
            ->count();
        if ($qry > 0) {
            //then save the attandance and perfoamcne details for the previous terms
            $table_data['updated_at'] = Carbon::now();
            $table_data['updated_by'] = $this->user_id;
            $previous_data = getPreviousRecords($table_name, $where_data);
            updateRecord($table_name, $previous_data, $where_data, $table_data, $this->user_id);

        } else {
            if ($value['performance_grade'] != '') {
                $table_data['created_at'] = Carbon::now();
                $table_data['created_by'] = $this->user_id;
                insertRecordReturnId($table_name, $table_data, $this->user_id);
            }
        }
    }

    private function validate_phone_number($number)
    {
        $pattern="/^260\d{9}$/";
        $is_match= preg_match($pattern,$number);
        return $is_match;
    }
    public function saveBeneficiaryEnrollmentbatchinfo(Request $req)
    {
        // $number =  240124345678;
        // return $this->validate_phone_number($number);
       
        try {
            $post_data = $req->all();
            $batch_id = $post_data['batch_id'];
            $school_id = $post_data['school_id'];
            $current_stage = $post_data['status'];
            $year =  $post_data['year_of_enrollment'];
            $term_id = 3;//$post_data['term_id'];
            $school_type_id=$post_data['school_type_id'];
            $postdata = file_get_contents("php://input");
            $data = json_decode($postdata);
            // the post data is array thus handle as array data
            if (is_array($data)) {
                $data = json_decode($postdata, true);
            } else {
                $data = array();
                $data[] = json_decode($postdata, true);
            }
            $table_name = 'beneficiary_enrollments';
            foreach ($data as $key => $value) {    
                //mobile_phone_number_for_cwac_contact_person
                //mobile_phone_number_for_parent_guardian
                //basic validation checks for weekly boarders
                if ($value['beneficiary_schoolstatus_id'] == 3 && ($value['wb_facility_manager_id'] == '' || !isset($value['wb_facility_manager_id']))) {
                    $test = array(
                        'success' => true,
                        'val_error' => 1,
                        'message' => '<u>Specify WB facility for: ' . $value['beneficiary_no'] . '</u><br> [When you see this message, note that some details are not saved, make the said change then save again]'
                    );
                    return response()->json($test);
                    exit();
                }
                $exam_fees = '';
                if ($value['school_grade'] == 9 || $value['school_grade'] == 12) {
                    $exam_fees = $value['exam_fees'];
                }
                $annual_fees = ((float)$value['term1_fees'] + (float)$value['term2_fees'] + (float)$value['term3_fees'] + (float)$exam_fees);

                $fin_exam_fees = validateisNumeric($exam_fees) ? aes_encrypt($exam_fees) : 0;  
                $table_data = array(
                    'school_grade' => $value['school_grade'],
                    'beneficiary_schoolstatus_id' => $value['beneficiary_schoolstatus_id'],
                    'term1_fees' => aes_encrypt($value['term1_fees']),
                    'term2_fees' => aes_encrypt($value['term2_fees']),
                    'term3_fees' => aes_encrypt($value['term3_fees']),
                    'term_id' => $term_id,
                    'exam_fees' => $fin_exam_fees,
                    'annual_fees' => aes_encrypt($annual_fees),
                    'enrollment_status_id' => $value['enrollment_status_id'],
                    'wb_facility_manager_id' => $value['wb_facility_manager_id'],
                    'has_signed_consent' => $value['has_signed_consent'],
                    'is_gce_external_candidate' => $value['is_gce_external_candidate'],
                    'has_signed' => $value['has_signed'],
                    'remarks' => $value['remarks']
                );
                $beneficiary_id = $value['beneficiary_id'];
                $school_grade = $value['school_grade'];
                $school_id = $value['school_id'];
                $exam_no = $value['exam_number'];
                //where data
                $where_data = array(
                    'id' => $value['id']
                );
                $qry = DB::table('beneficiary_enrollments as t1')
                    ->where($where_data)
                    ->count();
                $enrollement_id = $value['id'];
                $where = array(
                    'id' => $value['beneficiary_id']
                );
                if ($qry > 0) {
                    //save attendance
                    //$this->saveBeneficiaryPermanceattdetails($enrollement_id, $value);
                    $table_data['updated_at'] = Carbon::now();
                    $table_data['updated_by'] = $this->user_id;
                    $previous_data = getPreviousRecords($table_name, $where_data);
                    updateRecord($table_name, $previous_data, $where_data, $table_data, $this->user_id);
                    //validate individual if in verification or validation
                    if ($current_stage == 2 || $current_stage == 3) {
                        //prep enrollment ids for cron validation update
                        /*$ids_for_cron = array(
                            'batch_id' => $batch_id,
                            'enrollment_id' => $enrollement_id,
                            'execution_status' => 0,
                            'created_at' => Carbon::now(),
                            'created_by' => $this->user_id
                        );
                        DB::table('beneficiary_enrollments_execdelay_stgtwo')->insert($ids_for_cron);*/
                        $this->checkPaymentValidationRules($enrollement_id, $batch_id);
                    }
                }
                $benInfo = DB::table('beneficiary_information as t1')
                    ->where('id', $value['beneficiary_id'])
                    ->first();
                $data = array(
                    'is_letter_received' => $value['is_letter_received'],
                    'first_name' => $value['first_name'],
                    'id' => $value['beneficiary_id'],
                    'last_name' => $value['last_name']
                );
                if ($value['school_grade'] == 9) {
                    $data['grade9_exam_no'] = $exam_no;
                    $data['grade12_exam_no'] = '';
                } else if ($value['school_grade'] == 12) {
                    $data['grade12_exam_no'] = $exam_no;
                    $data['grade9_exam_no'] = '';
                } else {
                    $data['grade12_exam_no'] = '';
                    $data['grade9_exam_no'] = '';
                }
                if (!is_null($benInfo)) {
                    //update beneficiary info
                    if ($value['school_grade'] != $benInfo->current_school_grade) {
                        logBeneficiaryGradeTransitioning($beneficiary_id, $school_grade, $school_id, $this->user_id);
                        $data['current_school_grade'] = $value['school_grade'];
                    }
                    $data['beneficiary_school_status'] = $value['beneficiary_schoolstatus_id'];
                    $data['verification_school_fees'] = $value['verification_school_fees'];
                    //job 16/3/2022
                    if($value['mobile_phone_number_for_parent_guardian']!=null )
                    {
                        //mobile_phone_number_for_cwac_contact_person
                        $data['mobile_phone_parent_guardian']=$value['mobile_phone_number_for_parent_guardian'];
                    }
                    if($value['mobile_phone_number_for_cwac_contact_person']!=null )
                    {
                        $data['mobile_phone_cwac_contact_person']=$value['mobile_phone_number_for_cwac_contact_person'];
                    }
                    $data['updated_at'] = Carbon::now();
                    $data['updated_by'] = $this->user_id;
                    $previous_data = getPreviousRecords('beneficiary_information', $where);
                    updateRecord('beneficiary_information', $previous_data, $where, $data, $this->user_id);
                    $pre_first_name = $previous_data[0]['first_name'];
                    $pre_last_name = $previous_data[0]['last_name'];
                    $beneficiary_no = $value['beneficiary_no'];
                    $data['created_at'] = Carbon::now();
                    $data['created_by'] = $this->user_id;
                    $data['beneficiary_id'] = $beneficiary_no;
                    if ($pre_first_name != $value['first_name'] || $pre_last_name != $value['last_name']) {
                        //names have been changed
                        $data['girl_id'] = $data['id'];
                        unset($data['id']);
                        insertRecordReturnId('beneficiary_information_logs', $data, $this->user_id);
                    }
                }
            }

                //todo log errors
                $this->validateBeneficiaryEnrollment2($batch_id);
                //update school enrollment status
                $this->updateSchoolEnrollmentStatus($batch_id);
                //update school fees
                $this->AutoUpdateEnrollmentSchoolFee($batch_id);

            //prep batch ids for cron batch update
            // $batch_for_cron = array(
            //     'batch_id' => $batch_id,
            //     'execution_status' => 0,
            //     'created_at' => Carbon::now(),
            //     'created_by' => $this->user_id
            // );
            // DB::table('beneficiary_enrollments_execdelay')->insert($batch_for_cron);
            $res = array(
                'success' => true,
                'val_error' => 0,
                'message' => 'Beneficiary Enrollment Details Updated Successfully!!'
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

    public function updateSchoolEnrollmentStatus($batch_id)
    {
        $main_sql = DB::table('beneficiary_enrollments as t1')
            ->join('beneficiary_information as t2', 't1.beneficiary_id', '=', 't2.id')
            ->where('t1.batch_id', $batch_id);
        $signed_update = clone $main_sql;
        $signed_update->where('has_signed', 1)
            ->update(array('t2.school_enrollment_status' => 2));
        $unsigned_update = clone $main_sql;
        $unsigned_update->where(function ($query) {
            $query->where('has_signed', 0)
                ->orWhereNull('has_signed');
        })
            ->update(array('t2.school_enrollment_status' => 3,'t1.term_id' => 3));
    }

    public function saveBeneficiaryEnrollmentbatchinfo2(Request $req)
    {
        $post_data = $req->all();
        $batch_id = $post_data['batch_id'];
        $postdata = file_get_contents("php://input");
        $data = json_decode($postdata);
        // the post data is array thus handle as array data
        if (is_array($data)) {
            $data = json_decode($postdata, true);
        } else {
            $data = array();
            $data[] = json_decode($postdata, true);
        }
        $table_name = 'beneficiary_enrollments';
        foreach ($data as $key => $value) {
            $table_data = array(
                'school_grade' => $value['school_grade'],
                'beneficiary_schoolstatus_id' => $value['beneficiary_schoolstatus_id'],
                'school_fees' => $value['school_fees'],
                'enrollment_status_id' => $value['enrollment_status_id'],
                'has_signed' => $value['has_signed'],
                'remarks' => $value['remarks']
            );
            $beneficiary_id = $value['beneficiary_id'];
            //where data
            $where_data = array(
                'id' => $value['id']
            );
            $qry = DB::table('beneficiary_enrollments as t1')
                ->where($where_data)
                ->count();
            $enrollement_id = $value['id'];
            if ($qry > 0) {
                //then save the attendance and performance details for the previous terms
                $this->saveBeneficiaryPermanceattdetails($enrollement_id, $value);
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $this->user_id;
                $previous_data = getPreviousRecords($table_name, $where_data);
                updateRecord($table_name, $previous_data, $where_data, $table_data, $this->user_id);
                //validation rules
                $is_validated = $this->validateEnrollement($enrollement_id, $batch_id, $beneficiary_id);
            }
            $where_data = array('id' => $value['beneficiary_id']);
            $qry = DB::table('beneficiary_information as t1')
                ->where($where_data)
                ->count();
            $data = array(
                'is_letter_received' => $value['is_letter_received'],
                'first_name' => $value['first_name'],
                'id' => $value['beneficiary_id'],
                'last_name' => $value['last_name']);
            if ($qry > 0) {
                if ($is_validated == 1) {
                    //if validated update the grade and school fees and also the
                    $data['current_school_grade'] = $value['school_grade'];
                    //$data['beneficiary_school_status'] = $value['beneficiary_school_status'];
                    $data['beneficiary_school_status'] = $value['beneficiary_schoolstatus_id'];
                    $data['verification_school_fees'] = $value['verification_school_fees'];
                    //
                }
                $data['updated_at'] = Carbon::now();
                $data['updated_by'] = $this->user_id;
                $previous_data = getPreviousRecords('beneficiary_information', $where_data);
                $success = updateRecord('beneficiary_information', $previous_data, $where_data, $data, $this->user_id);
                $pre_first_name = $previous_data[0]['first_name'];
                $pre_last_name = $previous_data[0]['last_name'];
                $beneficiary_no = $value['beneficiary_no'];
                $data['created_at'] = Carbon::now();
                $data['created_by'] = $this->user_id;
                $data['beneficiary_id'] = $beneficiary_no;
                if ($pre_first_name != $value['first_name'] || $pre_last_name != $value['last_name']) {
                    //names have been changed
                    $data['girl_id'] = $data['id'];
                    unset($data['id']);
                    insertRecordReturnId('beneficiary_information_logs', $data, $this->user_id);
                }
            }
            //todo log errors
            //$this->validateBeneficiaryEnrollment2($batch_id);
        }
        $resp = array(
            'success' => true,
            'message' => 'Beneficiary Enrollment Details Updated Successfully'
        );
        json_output($resp);
    }

    public function getbeneficiaryEnrollmentsummarydta(Request $req)
    {
        $post_data = $req->all();
        $batch_id = $post_data['batch_id'];
        $qry = DB::table('payment_verificationbatch as t1')
            ->select(DB::raw('count(t2.school_grade) as beneficiary_counter,school_grade'))
            ->join('beneficiary_enrollments as t2', 't1.id', '=', 't2.batch_id')
            ->groupBy('t2.school_grade')
            ->where(array('t1.id' => $batch_id))
            ->get();
        $results = convertStdClassObjToArray($qry);
        $results = decryptArray($results);
        $res = array(
            'results' => $results
        );
        //var_dump($res);
        json_output($res);
    }

    //end Hiram's code
    public function returnforPaymentverificationquery(Request $req)
    {
        $batch_id = $req->input('batch_id');
        //check if there is any enrollment details
        $qry = DB::table('payment_verificationbatch as t1')
            ->select('status_id', 'folder_id')
            ->where(array('t1.id' => $batch_id))
            ->get();
        $results = convertStdClassObjToArray($qry);
        if (count($results)) {
            $folder_id = $results[0]['folder_id'];
            $status_id = $results[0]['status_id'];
            $prev_status_id = $status_id - 1;
            if ($status_id == 3) {
                $counter = DB::table('beneficiary_enrollments as t1')
                    ->where('t1.batch_id', $batch_id)
                    ->where('t1.submission_status', 2)
                    ->count();
                if ($counter > 0) {
                    $res = array(
                        'success' => false,
                        'message' => 'Submission not allowed, you have already submitted some of the beneficiaries to the next stage!!',
                        'status_id' => ''
                    );
                    return response()->json($res);
                }
            } else if ($status_id == 7) {
                $prev_status_id = 3;
                /*$counter = DB::table('beneficiary_enrollments as t1')
                    ->join('beneficiary_payment_records as t2', 't1.id', '=', 't2.enrollment_id')
                    ->where('t1.batch_id', $batch_id)
                    ->count();
                if ($counter > 0) {
                    $res = array(
                        'success' => false,
                        'message' => 'Reverse not allowed, some beneficiaries in this batch have been added to a payment request!!',
                        'status_id' => ''
                    );
                    return response()->json($res);
                }*/
            }
            $resp = array(
                'success' => true,
                'prev_status_id' => $status_id,
                'status_id' => $prev_status_id//$status_id - 1
            );
        } else {
            $resp = array(
                'success' => false,
                'message' => 'Enter beneficiary enrollment Details before submission to the next phase!!'
            );
        }
        return response()->json($resp);
    }

    function validateBeneficairyenrollment($batch_id)
    {
        $qry = DB::table('beneficiary_enrollments')
            ->where(array('batch_id' => $batch_id, 'enrollment_status_id' => 1, 'has_signed' => 0))
            ->count();
        if ($qry > 0) {
            $resp = array(
                'success' => false,
                'message' => 'Found Enrolled beneficairy information that has no signiture details!!'
            );
            json_output($resp);
            exit();
        }
    }

    function validateSingleBeneficiaryEnrollment($enrollment_id, $batch_id)
    {
        //todo: keep the simple rules
        //1. no signed beneficiary should have fees of zero/NULL
        //2. no signed beneficiary should have unspecified school status
        //3. no beneficiary should be in grade less than 7
        $qry = DB::table('beneficiary_enrollments')
            ->where(array('id' => $enrollment_id));//, 'has_signed' => 1));
        $enrollment_detail = $qry->first();
        $user_id = \Auth::user()->id;
        if (!is_null($enrollment_detail)) {
            $enrollment_id = $enrollment_detail->id;
            $school_fees = $enrollment_detail->school_fees;
            $has_signed = $enrollment_detail->has_signed;
            if ($has_signed == 1) {
                //rule 1
                if ($school_fees < 1 || $school_fees == '') {
                    $error = 'Signed beneficiary MUST have specified school fees';
                    $params = array(
                        'batch_id' => $batch_id,
                        'error_index' => 1,
                        'error_text' => $error,
                        'enrollment_id' => $enrollment_id,
                        'created_by' => $user_id
                    );
                    $checker = DB::table('enrollment_error_log')
                        ->where(array('batch_id' => $batch_id, 'error_index' => 1, 'enrollment_id' => $enrollment_id))
                        ->count();
                    if ($checker == 0 || $checker < 1) {
                        DB::table('enrollment_error_log')
                            ->insert($params);
                    }
                } else {
                    DB::table('enrollment_error_log')
                        ->where(array('batch_id' => $batch_id, 'enrollment_id' => $enrollment_id, 'error_index' => 1))
                        ->delete();
                }
            } else {//defer validations
                DB::table('enrollment_error_log')
                    ->where(array('batch_id' => $batch_id, 'enrollment_id' => $enrollment_id))
                    ->delete();
            }
        }
    }

    function validateBeneficiaryEnrollment2($batch_id)
    {
        //todo: keep the simple rules
        //1. no signed beneficiary should have fees of zero/NULL
        //2. no signed beneficiary should have unspecified school status
        //3. no beneficiary should be in grade less than 7
        $qry = DB::table('beneficiary_enrollments')
            ->where(array('batch_id' => $batch_id));
        $enrollment_details = $qry->get();
        $user_id = 4;//\Auth::user()->id;
        $batch_ben_ids = array();
        $with_errors_ids = array();
        $params = array();
        foreach ($enrollment_details as $enrollment_detail) {
            $error_counter = 0;
            $enrollment_id = $enrollment_detail->id;
            $term1_fees = aes_decrypt($enrollment_detail->term1_fees);
            $term2_fees = aes_decrypt($enrollment_detail->term2_fees);
            $term3_fees = aes_decrypt($enrollment_detail->term3_fees);
            $has_signed = $enrollment_detail->has_signed;
            $isGCE = $enrollment_detail->is_gce_external_candidate;
            $batch_ben_ids[] = array(
                'id' => $enrollment_id
            );
            if ($has_signed == 1) {
                //rule 1
                if ($isGCE == 1) {//GCE/External Students
                    if (($term1_fees < 0 || $term1_fees == '') || ($term2_fees < 0 || $term2_fees == '')) {//job on 3/04/2022 frm 1
                        $error = 'Signed GCE/External beneficiary MUST have specified school fees for term 1 & term 2';
                        $params[] = array(
                            'batch_id' => $batch_id,
                            'error_index' => 1,
                            'error_text' => $error,
                            'enrollment_id' => $enrollment_id,
                            'created_by' => $user_id,
                            'created_at' => Carbon::now()
                        );
                        $with_errors_ids[] = array(
                            'id' => $enrollment_id
                        );
                    }
                    if ($term3_fees > 1) {
                        $error = 'GCE/External beneficiary is not eligible for term 3 payments';
                        $params[] = array(
                            'batch_id' => $batch_id,
                            'error_index' => 1,
                            'error_text' => $error,
                            'enrollment_id' => $enrollment_id,
                            'created_by' => $user_id,
                            'created_at' => Carbon::now()
                        );
                        $with_errors_ids[] = array(
                            'id' => $enrollment_id
                        );
                    }
                } 
                // else {//Other Students
                //     if (($term1_fees < 0 || $term1_fees == '') || ($term2_fees < 0 || $term2_fees == '') || ($term3_fees < 0 || $term3_fees == '')) {
                //         $error = 'Signed beneficiary MUST have specified school fees for all terms';
                //         $params[] = array(
                //             'batch_id' => $batch_id,
                //             'error_index' => 1,
                //             'error_text' => $error,
                //             'enrollment_id' => $enrollment_id,
                //             'created_by' => $user_id,
                //             'created_at' => Carbon::now()
                //         );
                //         $with_errors_ids[] = array(
                //             'id' => $enrollment_id
                //         );
                //     }
                // }
                /* if ($school_fees < 1 || $school_fees == '') {
                     $error = 'Signed beneficiary MUST have specified school fees';
                     $params[] = array(
                         'batch_id' => $batch_id,
                         'error_index' => 1,
                         'error_text' => $error,
                         'enrollment_id' => $enrollment_id,
                         'created_by' => $user_id,
                         'created_at' => Carbon::now()
                     );
                     $with_errors_ids[] = array(
                         'id' => $enrollment_id
                     );
                 }*/
            }
        }
        $batch_ben_ids = convertAssArrayToSimpleArray($batch_ben_ids, 'id');
        DB::table('enrollment_error_log')
            ->where('batch_id', $batch_id)
            ->whereIn('enrollment_id', $batch_ben_ids)
            ->delete();
        DB::table('enrollment_error_log')
            ->insert($params);
    }

    public function checkBatchForValidationErrors($batch_id)
    {
        $enrollment_error_counter = DB::table('enrollment_error_log')
            ->where('batch_id', $batch_id)
            ->count();
        $fees_error_counter = DB::table('fees_error_log')
            ->where('batch_id', $batch_id)
            ->count();
        if ($enrollment_error_counter > 0 || $fees_error_counter > 0) {
            $resp = array(
                'success' => false,
                'message' => 'Some anomalies were found in your submission, check on the details by clicking on error log button under \'Fees Setup\' and \'Enrollment Details\' sections!!'
            );
            json_output($resp);
            exit();
        }
    }

    public function getEnrollmentErrorLog(Request $req)
    {
        $batch_id = $req->input('batch_id');
        try {
            $qry = DB::table('enrollment_error_log as t1')
                ->leftJoin('beneficiary_enrollments as t2', 't1.enrollment_id', '=', 't2.id')
                ->leftJoin('beneficiary_information as t3', 't2.beneficiary_id', '=', 't3.id')
                ->select(DB::raw('t1.id, t1.batch_id, t1.enrollment_id, t1.error_text, t3.beneficiary_id, decrypt(t3.first_name) as first_name, decrypt(t3.last_name) as last_name'))
                ->where('t1.batch_id', $batch_id);
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

    public function validateFeesBeforeSubmission($batch_id)
    {
       
        $qry = DB::table('beneficiary_enrollments as t1')
        ->join('school_information as t2','t1.school_id','t2.id')
        ->select('t1.beneficiary_id,t2.running_agency_id,t1.beneficiary_schoolstatus_id,
        enrollment_status_id,term1_fees,term2_fees,term3_fees,school_grade,is_gce_external_candidate')
        ->where(array('t1.batch_id' => $batch_id))
        ->get();
    $results = convertStdClassObjToArray($qry);

    foreach($results as $beneficiary)
    {
        $beneficiary_id = $beneficiary->beneficiary_id;
        $school_grade=$beneficiary->school_grade;
        $is_gce_external_candidate=$is_gce_external_candidate;
        $term1_fees=$beneficiary->term1_fees; 
        $term2_fees=$beneficiary->term2_fees; 
        $term3_fees=$beneficiary->term3_fees; 
        $school_running_agency=$beneficiary->running_agency_id;
        db::table('school_feessetup')
        ->where('id',$school_id)
        ->where('year',date('Y'));
        //->where('grade_id',)
        

        
    }
    
    
    }
    public function checkPaymentVerificationBatchdetails(Request $req)
    {
        
        
        try {
            
            if($this->CheckDataEntryDuplicates()==true)
            {
                $resp = array(
                    'success' => false,
                    'message' => 'Please resolve Duplicates before submitting to next stage!'
                );
                return response()->json($resp);
                
            }
            
            //check the details
            $batch_id = $req->input('batch_id');
            //$res = $this->AutoUpdateEnrollmentSchoolFee($batch_id);//job on 03/04/2022
            //$this->validateFeesBeforeSubmission($batch_id);
            //check if there is any enrollment details
            $qry = DB::table('beneficiary_enrollments as t1')
                ->select('status_id', 'folder_id', 't2.id as batch_id')
                ->join('payment_verificationbatch as t2', 't1.batch_id', '=', 't2.id')
                ->where(array('t1.batch_id' => $batch_id))
                ->groupBy('t2.id')
                ->get();
            $results = convertStdClassObjToArray($qry);
           
          
            if (count($results)) {
                //validate beneficiaries details
                $batch_id = $results[0]['batch_id'];
                $this->checkBatchForValidationErrors($batch_id);//check fees and enrollment logs for any errors job 19/3/2022
                $folder_id = $results[0]['folder_id'];
                $status_id = $results[0]['status_id'];
                $next_stage_id = $status_id + 1;
                if ($status_id == 7) {
                    $resp = array(
                        'success' => false,
                        'message' => 'The batch has been archived already, please click on \'Validation Dashboard\' button!!'
                    );
                    return response()->json($resp);
                }
                if ($status_id == 1) {
                    $this->checkPaymentValidationRulesBatch($batch_id);
                }
                if ($status_id == 3) {
                    $next_stage_id = 7;
                    $counter = DB::table('beneficiary_enrollments')
                        ->where('batch_id', $batch_id)
                        ->where(function ($query) {
                            $query->whereIn('submission_status', array(0, 1))
                                ->orWhereNull('submission_status');
                        })
                        ->count();
                    if ($counter > 0) {
                        $resp = array(
                            'success' => false,
                            'message' => 'Action not allowed, only batches with all validated records submitted can be archived!!'
                        );
                        return response()->json($resp);
                    }
                    /*   $resp = array(
                           'success' => false,
                           'message' => 'Action not allowed at this stage, just submit validated records!!'
                       );
                       return response()->json($resp);*/
                }
                $batch_documents = true;// check_DmsFolderDocuments($folder_id,$this->user_email);
                //check if dms has a documents to the same folder
                if ($batch_documents) {
                    $resp = array(
                        'success' => true,
                        'prev_status_id' => $status_id,
                        'status_id' => $next_stage_id//$status_id + 1
                    );
                } else {
                    $resp = array(
                        'success' => false,
                        'message' => 'Upload Payment Verification Checklist before submission to the next phase!!'
                    );
                }
            } else {
                $resp = array(
                    'success' => false,
                    'message' => 'Enter beneficiary enrollment Details before submission to the next phase!!'
                );
            }
        } catch (\Exception $exception) {
            $resp = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            $resp = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return response()->json($resp);
    }

    public function submitbatchPayment4Validation(Request $req)
    {
        $next_status_id = $req->input('nextstatus_id');
        $status_id = $req->input('status_id');
        $data = $req->input('data');
        $records = explode(',', $data);
        foreach ($records as $rec) {
            $batch_id = $rec;
            $table_name = 'payment_verificationbatch';
            if (validateisNumeric($batch_id)) {
                $where = array('id' => $batch_id);
                $table_data = array(
                    'remarks' => 'Batch Submission for validation',
                    'status_id' => $next_status_id,
                    'submitted_on' => Carbon::now(),
                    'submitted_by' => $this->user_id
                );
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $this->user_id;
                $previous_data = getPreviousRecords($table_name, $where);

                $previous_status_id = $previous_data[0]['status_id'];

                $success = updateRecord($table_name, $previous_data, $where, $table_data, $this->user_id);
                $table_name = 'payment_verificationtransitionsubmissions';
                //save the details in the transitional table
                $data = array(
                    'batch_id' => $batch_id,
                    'previous_status_id' => $status_id,
                    'next_status_id' => $next_status_id,
                    'released_on' => Carbon::now(),
                    'released_by' => $this->user_id,
                    'remarks' => 'Batch Submission for validation'

                );
                $data['created_at'] = Carbon::now();
                $data['created_by'] = $this->user_id;

                $insert_resp = insertRecord($table_name, $data, $this->user_id);
            }
        }
        if ($insert_resp) {
            $resp = array('success' => true, 'message' => 'Payment Verification Details submitted successfully to the next phase for processing');
        } else {

            $resp = array('success' => false, 'message' => 'Error occurred, Please contact the system administrator');
        }
        json_output($resp);
    }

    public function paymentBatchSubmissions(Request $req)
    {
        $next_status_id = $req->input('nextstatus_id');
        $status_id = $req->input('status_id');
        $remark = $req->input('remark');
        $selected = $req->input('selected');
        $selected_batch_ids = json_decode($selected);
        $trans_data = array();
        foreach ($selected_batch_ids as $selected_batch_id) {
            $trans_data[] = array(
                'batch_id' => $selected_batch_id,
                'previous_status_id' => $status_id,
                'next_status_id' => $next_status_id,
                'released_on' => Carbon::now(),
                'released_by' => $this->user_id,
                'remarks' => $remark,
                'created_at' => Carbon::now(),
                'created_by' => $this->user_id
            );
        }
        try {
            $update_data = array(
                'remarks' => $remark,
                'status_id' => $next_status_id,
                'submitted_on' => Carbon::now(),
                'submitted_by' => $this->user_id,
                'updated_at' => Carbon::now(),
                'updated_by' => $this->user_id
            );
            DB::table('payment_verificationbatch')
                ->whereIn('id', $selected_batch_ids)
                ->update($update_data);
            DB::table('payment_verificationtransitionsubmissions')
                ->insert($trans_data);
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

    public function receiptBatchSubmissions(Request $req)
    {
        $next_status_id = $req->input('nextstatus_id');
        $remark = $req->input('remark');
        $selected = $req->input('selected');
        $selected_batch_ids = json_decode($selected);
        $params = array(
            'status_id' => $next_status_id,
            'updated_by' => $this->user_id
        );
        if ($next_status_id == 10) {
            $params['reconciliation_remark'] = $remark;
            $params['reconciliation_author'] = $this->user_id;
        } else {
            $params['validation_remark'] = $remark;
            $params['validation_author'] = $this->user_id;
        }
        try {
            DB::table('payment_receiptingbatch')
                ->whereIn('id', $selected_batch_ids)
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

    //submit details
    public function submitpaymentBatchChecklist(Request $req)
    {
        //the details
        $batch_id = $req->batch_id;
        $remarks = $req->remarks;
        $nextstatus_id = $req->nextstatus_id;
        $table_data = array('remarks' => $remarks,
            'status_id' => $nextstatus_id,
            'submitted_on' => Carbon::now(),
            'submitted_by' => $this->user_id
        );

        
        $table_name = 'payment_verificationbatch';
        if (validateisNumeric($batch_id)) {
            $where = array('id' => $batch_id);

            $table_data['updated_at'] = Carbon::now();
            $table_data['updated_by'] = $this->user_id;
            $previous_data = getPreviousRecords($table_name, $where);

            $previous_status_id = $previous_data[0]['status_id'];

            updateRecord($table_name, $previous_data, $where, $table_data, $this->user_id);
            $table_name = 'payment_verificationtransitionsubmissions';
            //save the details in the transitional table
            $data = array(
                'batch_id' => $batch_id,
                'previous_status_id' => $previous_status_id,
                'next_status_id' => $nextstatus_id,
                'released_on' => Carbon::now(),
                'released_by' => $this->user_id,
                'remarks' => $remarks

            );
            $data['created_at'] = Carbon::now();
            $data['created_by'] = $this->user_id;

            $insert_resp = insertRecord($table_name, $data, $this->user_id);

        }
        if ($insert_resp) {
            $resp = array('success' => true, 'message' => 'Payment Verification Details submitted successfully to the next phase for processing');

        } else {

            $resp = array('success' => false, 'message' => 'Error occurred, Please contact the system administrator');
        }
        json_output($resp);
    }

    public function submitValidatedBeneficiaryDetails(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $user_id = $this->user_id;
            //$res = $this->AutoUpdateEnrollmentSchoolFee($batch_id);//job on 13/04/2022
            //uptake status update
            DB::table('beneficiary_enrollments as t1')
                ->join('beneficiary_information as t2', 't1.beneficiary_id', '=', 't2.id')
                ->where('t1.batch_id', $batch_id)
                ->where('t1.is_validated', 1)
                ->where('t1.submission_status', '<>', 2)
                ->where('t2.kgs_takeup_status', '<>', 1)
                ->update(array('kgs_takeup_status' => 1, 'kgs_takeup_date' => Carbon::now(), 'kgs_takeup_author' => $user_id, 'kgs_takeup_grade' => DB::raw("t1.school_grade")));
            //update submission status
            DB::table('beneficiary_enrollments as t1')
                ->where('t1.batch_id', $batch_id)
                ->where('t1.is_validated', 1)
                ->where('t1.submission_status', '<>', 2)
                ->update(array('submission_status' => 2));
            $counter = DB::table('beneficiary_enrollments')
                ->where('batch_id', $batch_id)
                ->where(function ($query) {
                    $query->whereIn('submission_status', array(0, 1))
                        ->orWhereNull('submission_status');
                })
                ->count();
            if ($counter == 0 || $counter < 1) {
                DB::table('payment_verificationbatch')
                    ->where('id', $batch_id)
                    ->update(array('status_id' => 7));
                $table_name = 'payment_verificationtransitionsubmissions';
                //save the details in the transitional table
                $data = array(
                    'batch_id' => $batch_id,
                    'previous_status_id' => 3,
                    'next_status_id' => 7,
                    'released_on' => Carbon::now(),
                    'released_by' => $this->user_id,
                    'remarks' => 'System auto archive',
                    'created_at' => Carbon::now(),
                    'created_by' => $this->user_id
                );
                insertRecord($table_name, $data, $this->user_id);
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
        return response()->json($res);
    }

    public function savePaymentrequestreceipting(Request $req)
    {
        $term_id = 2;//$req->input('term_id');
        $payment_receipting_id = $req->input('payment_receipts_id');
        $payment_year = $req->input('payment_year');
        $file_no = $req->input('file_no');
        $school_id = $req->input('school_id');
        $data = array(
            'term_id' => $term_id,
            'school_id' => $school_id,
            'payment_year' => $payment_year
        );
        if ($file_no == '') {
            $file_no = generateReceiptFileNo();
        }
        $data['file_no'] = $file_no;
        $data['status_id'] = 8;//for receipting
        $table_name = 'payment_receiptingbatch';
        if (validateisNumeric($payment_receipting_id)) {
            $where = array('id' => $payment_receipting_id);
            $data['updated_at'] = Carbon::now();
            $data['updated_by'] = $this->user_id;
            $previous_data = getPreviousRecords($table_name, $where);
            updateRecord($table_name, $previous_data, $where, $data, $this->user_id);
        } else {
            $data['created_at'] = Carbon::now();
            $data['created_by'] = $this->user_id;
            $payment_receipting_id = insertRecordReturnId($table_name, $data, $this->user_id);
            $this->addReceiptingBeneficiaryDetails($school_id, $payment_year, $term_id, $payment_receipting_id);
        }
        if (validateisNumeric($payment_receipting_id)) {
            $resp = array(
                'success' => true,
                'message' => 'Receipting Details Saved Successfully!!',
                'payment_receipts_id' => $payment_receipting_id,
                'file_no' => $file_no
            );

        } else {
            $resp = array(
                'success' => false,
                'message' => 'Check connection or contact the system Administrator'
            );
        }
        json_output($resp);
    }

    public function addReceiptingBeneficiaryDetails($school_id, $year, $term, $payment_receipts_id)
    {
        try {
            $qry = DB::table('payment_disbursement_details as t1')
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                ->join('payment_request_details as t3', 't1.payment_request_id', '=', 't3.id')
                ->join('districts as t4', 't4.id', '=', 't2.district_id')
                ->join('beneficiary_payment_records as t6', 't6.payment_request_id', '=', 't3.id')
                ->join('beneficiary_enrollments as t7', function ($join) use ($year, $term) {
                    $join->on('t7.id', '=', 't6.enrollment_id')
                        ->on('t7.school_id', '=', 't1.school_id');
                })
                ->join('beneficiary_information as t8', 't7.beneficiary_id', '=', 't8.id')
                ->leftJoin('payments_receipting_details as t9', 't7.id', '=', 't9.enrollment_id')
                ->select(DB::raw('distinct(t8.id), t8.beneficiary_id as beneficiary_no,t2.name as school_name, t4.name as district_name,
                                  t7.id as enrollment_id,t2.id as school_id,t4.id as district_id,
                                  decrypt(t8.first_name) as first_name,decrypt(t8.last_name) as last_name,t7.school_fees'))
                ->where('t3.payment_year', $year)
                ->where('t3.term_id', $term)
                ->where('t7.school_id', $school_id)
                ->whereNull('t9.id');
            $results = $qry->get();
            $params = array();
            foreach ($results as $result) {
                $params[] = array(
                    'term_id' => $term,
                    'payment_year' => $year,
                    'enrollment_id' => $result->enrollment_id,
                    'payment_receipts_id' => $payment_receipts_id
                );
            }
            DB::table('payments_receipting_details')
                ->insert($params);
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

    public function savePaymentrequestdetails(Request $req)
    {
        //the details
        $term_id = $req->input('term_id');
        $payment_request_id = $req->input('payment_request_id');
        $description = $req->input('description');
        $payment_year = $req->input('payment_year');
        $approved_by = $req->input('approved_by');
        $approved_on = $req->input('approved_on');
        $checked_by = $req->input('checked_by');
        $checked_on = $req->input('checked_on');

        $data = array(
            'term_id' => $term_id,
            'payment_year' => $payment_year,
            'description' => $description
        );
        $table_name = 'payment_request_details';
        try {
            if (validateisNumeric($payment_request_id)) {
                $where = array('id' => $payment_request_id);
                $data['updated_at'] = Carbon::now();
                $data['updated_by'] = $this->user_id;
                $previous_data = getPreviousRecords($table_name, $where);
                $payment_ref_no = $previous_data[0]['payment_ref_no'];//$previous_data

                $data['checked_on'] = $checked_on;
                $data['checked_by'] = $checked_by;
                $data['approved_by'] = $approved_by;
                $data['approved_on'] = $approved_on;
                updateRecord($table_name, $previous_data, $where, $data, $this->user_id);
            } else {
                $payment_ref_no = generatePaymentRequestRefNo($payment_year);
                $data['prepared_by'] = $this->user_id;
                $data['prepared_on'] = Carbon::now();
                $data['created_at'] = Carbon::now();
                $data['created_by'] = $this->user_id;
                $data['payment_ref_no'] = $payment_ref_no;
                $data['status_id'] = 4;
                $payment_request_id = insertRecordReturnId($table_name, $data, $this->user_id);
            }
            if (validateisNumeric($payment_request_id)) {
                $res = array(
                    'success' => true,
                    'message' => 'Payment request Details Saved Successfully!',
                    'payment_request_id' => $payment_request_id,
                    'payment_ref_no' => $payment_ref_no
                );
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'Check connection or contact the system Administrator'
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

    public function savePaymentRequestApprovalDetails(Request $req)
    {
        $request_id = $req->input('payment_request_id');
        $checked_by = $req->input('checked_by_id');
        $checked_date = $req->input('checked_on');
        $check_comment = $req->input('check_comment');
        $approval_status = $req->input('approval_status');
        $approval_by = $req->input('approved_by_id');
        $approval_date = $req->input('approved_on');
        $approval_comment = $req->input('approval_comment');
        $next_stage = $req->input('next_stage');
        try {
            $where = array(
                'id' => $request_id
            );
            $params = array(
                'checked_on' => $checked_date,
                'checked_by' => $checked_by,
                'check_comment' => $check_comment,
                'approved_by' => $approval_by,
                'approved_on' => $approval_date,
                'approval_status' => $approval_status,
                'approval_comment' => $approval_comment
            );
            $table_name = 'payment_request_details';
            if (isset($next_stage) && $next_stage != '' && $next_stage != 0) {
                $params['status_id'] = $next_stage;
            }
            $prev_record = getPreviousRecords($table_name, $where);
            updateRecord($table_name, $prev_record, $where, $params, $this->user_id);
            $res = array(
                'success' => true,
                'message' => 'Approval information saved successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }
    
    public function getBeneficiariespayValidationrules(Request $req)
    {
        $enrollment_id = $req->enrollment_id;
        $qry = DB::table('beneficiary_payvalidation_rulesdetails as t1')
            ->join('payment_validation_rules as t2', 't1.rule_id', '=', 't2.id')
            ->join('beneficiary_information as t3', 't1.beneficiary_id', '=', 't3.id')
            ->join('beneficiary_enrollments as t4', 't1.enrollment_id', '=', 't4.id')
            ->select('t4.enrollment_status_id', 't4.is_validated', 't4.has_signed', 't2.name as rule_name', 't2.description', 't1.remarks', 't3.first_name', 't3.last_name')
            ->where(array('t1.enrollment_id' => $enrollment_id))
            ->get();
        $results = convertStdClassObjToArray($qry);
        $results = decryptArray($results);
        $res = array(
            'results' => $results
        );
        json_output($res);
    }

    // public function getBeneficiariespayValidationrules(Request $req)
    public function runPaymentBatches(Request $req)
    {
        try {
            $saved_batches = DB::table('beneficiary_enrollments_execdelay as t1')
                ->select('t1.*')
                ->where('t1.execution_status', 0)
                ->groupBy('t1.batch_id')->limit(20)->get();

            /*$pending_ids = DB::table('beneficiary_enrollments_execdelay_stgtwo as t1')
                ->select('t1.*')
                ->where('t1.execution_status', 0)
                ->limit(20)->get();
            
            foreach ($pending_ids as $key_id => $value_id) {
                //todo log errors
                self::checkPaymentValidationRules($value_id->enrollement_id, $value_id->batch_id);
                DB::table('beneficiary_enrollments_execdelay_stgtwo as t1')
                    ->where('t1.batch_id', $value_id->batch_id)
                    ->where('t1.enrollment_id', $value_id->enrollement_id)
                    ->update(array('t1.execution_status' => 1));
            }
            */
            foreach ($saved_batches as $key => $value) {
                //todo log errors
                self::validateBeneficiaryEnrollment2($value->batch_id);
                //update school enrollment status
                self::updateSchoolEnrollmentStatus($value->batch_id);
                //update school fees
                // self::AutoUpdateEnrollmentSchoolFee($value->batch_id);
                // DB::table('beneficiary_enrollments_execdelay as t1')
                //     ->where('t1.batch_id', $value->batch_id)
                //     ->update(array('t1.execution_status' => 1));
            }
            $pending_count = DB::table('beneficiary_enrollments_execdelay as t1')
                    ->where('t1.execution_status', 0)->count();
            /*$pending_enrol_ids = DB::table('beneficiary_enrollments_execdelay_stgtwo as t1')
                    ->where('t1.execution_status', 0)->count();*/
            $res = array(
                'success' => true,
                'pending' => $pending_count,
                // 'pending_enrol_ids' => $pending_enrol_ids,
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

    public function getBeneficiariesDatalogs(Request $req)
    {
        $beneficiary_id = $req->beneficiary_id;
        //get the query details
        $qry = DB::table('beneficiary_information_logs as t1')
            ->select('t1.first_name', 't1.last_name', 't2.first_name as updated_by', 't1.created_at as updated_on', 't1.beneficiary_id')
            ->join('users as t2', 't1.created_by', '=', 't2.id')
            ->where(array('t1.girl_id' => $beneficiary_id))
            ->get();

        $results = convertStdClassObjToArray($qry);
        $results = decryptArray($results);
        $res = array(
            'results' => $results
        );
        //var_dump($res);
        json_output($res);

    }

    function checkforEnrollmentrepeatStatus($rule_id, $beneficiary_id, $batch_id, $max_counter, $year_of_enrollment, $school_grade)
    {
        $response = false;
        $max_counter = $this->getRulecountvalue($rule_id);
        $sql = DB::table('beneficiary_enrollments')
            ->select('id')
            ->where(array('is_validated' => 1, 'beneficiary_id' => $beneficiary_id, 'school_grade' => $school_grade))
            ->where('year_of_enrollment', '<', $year_of_enrollment)
            ->groupBy('year_of_enrollment')
            ->count();

        if ($sql > $max_counter) {
            $response = true;
        }
        return $response;
    }

    function saveverificationValidationrules($where, $table_name, $data)
    {
        $sql = DB::table('beneficiary_payvalidation_rulesdetails')
            ->where($where)
            ->count();
        if ($sql > 0) {
            $data['updated_at'] = Carbon::now();
            $data['updated_by'] = $this->user_id;
            $previous_data = getPreviousRecords($table_name, $where);
            updateRecord($table_name, $previous_data, $where, $data, $this->user_id);
        } else {
            $data['created_at'] = Carbon::now();
            $data['created_by'] = $this->user_id;
            insertRecordReturnId($table_name, $data, $this->user_id);
        }
    }

    function checkPaymentValidationRules($id, $batch_id)
    {
        $qry = DB::table('beneficiary_enrollments')
            ->select('has_signed', 'year_of_enrollment', 'term_id', 'enrollment_status_id', 'id', 'beneficiary_id', 'school_grade')
            ->where(array('batch_id' => $batch_id, 'id' => $id))
            ->first();
        $failed_rules_counter = 0;
        $passed_rules = 1;
        $table_data = array();
        if (!is_null($qry)) {
            $has_signed = $qry->has_signed;
            $enrollment_id = $qry->id;
            $beneficiary_id = $qry->beneficiary_id;
            $id = $qry->id;
            $year_of_enrollment = $qry->year_of_enrollment;
            $term_id = $qry->term_id;
            $school_grade = $qry->school_grade;
            $valtable_name = 'beneficiary_payvalidation_rulesdetails';
            $where = array(
                'beneficiary_id' => $beneficiary_id,
                'enrollment_id' => $enrollment_id
            );

            /*TODO: RULES*/
            //RULE 1: Signing of payment checklist
            //RULE 2: Repeat of a grade more than twice not allowed
            //RULE 3: Missing payments two consecutive terms
            //RULE 4: Failing to attend the required attendance rate
            //RULE 5: Having more than two kids not allowed

            //Rule 1
            if ($has_signed != 1) {
                $rule_id = 1;
                $where['rule_id'] = $rule_id;
                $data = array(
                    'beneficiary_id' => $beneficiary_id,
                    'enrollment_id' => $enrollment_id,
                    'rule_id' => $rule_id,
                    'remarks' => 'The beneficiary has not signed on the Payment verification checklist.'
                );
                $this->saveverificationValidationrules($where, $valtable_name, $data);
                $failed_rules_counter++;
            } else {
                $rule_id = 1;
                $where['rule_id'] = $rule_id;
                DB::table('beneficiary_payvalidation_rulesdetails')
                    ->where($where)
                    ->delete();
            }
            //Rule 2
            $is_rule_two_enabled = checkPaymentValidationRuleStatus(4);
            if ($is_rule_two_enabled) {
                $max_counter = $this->getRulecountvalue(4);
                $check_repeatstatus = checkBeneficiaryRepetitionStatus($max_counter, $beneficiary_id, $school_grade);
                if ($check_repeatstatus) {
                    $rule_id = 4;
                    $where['rule_id'] = $rule_id;
                    $data = array(
                        'beneficiary_id' => $beneficiary_id,
                        'enrollment_id' => $enrollment_id,
                        'rule_id' => $rule_id,
                        'remarks' => 'Beneficiary has Repeated more than the allowed no of times in the same grade'
                    );
                    $this->saveverificationValidationrules($where, $valtable_name, $data);
                    $failed_rules_counter++;
                } else {
                    $rule_id = 4;
                    $where['rule_id'] = $rule_id;
                    DB::table('beneficiary_payvalidation_rulesdetails')
                        ->where($where)
                        ->delete();
                }
            } else {
                $rule_id = 4;
                $where['rule_id'] = $rule_id;
                DB::table('beneficiary_payvalidation_rulesdetails')
                    ->where($where)
                    ->delete();
            }
            //Rule 3
            $is_rule_three_enabled = checkPaymentValidationRuleStatus(5);
            if ($is_rule_three_enabled) {
                $max_counter = $this->getRulecountvalue(5);
                $has_missed_payments = checkMissingPayments($max_counter, $beneficiary_id, $year_of_enrollment, $term_id);
                if ($has_missed_payments) {
                    $rule_id = 5;
                    $where['rule_id'] = $rule_id;
                    $data = array(
                        'beneficiary_id' => $beneficiary_id,
                        'enrollment_id' => $enrollment_id,
                        'rule_id' => $rule_id,
                        'remarks' => 'Beneficiary has missed payments more than the allowed no of times'
                    );
                    $this->saveverificationValidationrules($where, $valtable_name, $data);
                    $failed_rules_counter++;
                } else {
                    $rule_id = 5;
                    $where['rule_id'] = $rule_id;
                    DB::table('beneficiary_payvalidation_rulesdetails')
                        ->where($where)
                        ->delete();
                }
            } else {
                $rule_id = 5;
                $where['rule_id'] = $rule_id;
                DB::table('beneficiary_payvalidation_rulesdetails')
                    ->where($where)
                    ->delete();
            }
            //Rule 4
            $is_rule_four_enabled = checkPaymentValidationRuleStatus(3);
            if ($is_rule_four_enabled) {
                $less_attendance = calculateAttendanceRate($beneficiary_id, $year_of_enrollment, $term_id);
                if ($less_attendance) {
                    $rule_id = 3;
                    $where['rule_id'] = $rule_id;
                    $data = array(
                        'beneficiary_id' => $beneficiary_id,
                        'enrollment_id' => $enrollment_id,
                        'rule_id' => $rule_id,
                        'remarks' => 'Beneficiary has not reached threshold attendance rate last term'
                    );
                    $this->saveverificationValidationrules($where, $valtable_name, $data);
                    $failed_rules_counter++;
                } else {
                    $rule_id = 3;
                    $where['rule_id'] = $rule_id;
                    DB::table('beneficiary_payvalidation_rulesdetails')
                        ->where($where)
                        ->delete();
                }
            } else {
                $rule_id = 3;
                $where['rule_id'] = $rule_id;
                DB::table('beneficiary_payvalidation_rulesdetails')
                    ->where($where)
                    ->delete();
            }
            if ($failed_rules_counter > 0) {
                $passed_rules = 0;
            }
            $where_data = array(
                'batch_id' => $batch_id,
                'id' => $id
            );
            $table_name = 'beneficiary_enrollments';
            $table_data['passed_rules'] = $passed_rules;
            $table_data['updated_at'] = Carbon::now();
            $table_data['updated_by'] = $this->user_id;
            $previous_data = getPreviousRecords($table_name, $where_data);
            updateRecord($table_name, $previous_data, $where_data, $table_data, $this->user_id);
        }
    }

    function checkPaymentValidationRulesBatch($batch_id)
    {
        $ben_details = DB::table('beneficiary_enrollments')
            ->select('has_signed', 'year_of_enrollment', 'term_id', 'id', 'beneficiary_id', 'school_grade')
            ->where(array('batch_id' => $batch_id))
            ->get();
        $passed_ids = array();
        $failed_ids = array();
        foreach ($ben_details as $ben_detail) {
            $failed_rules_counter = 0;
            $has_signed = $ben_detail->has_signed;
            $enrollment_id = $ben_detail->id;
            $beneficiary_id = $ben_detail->beneficiary_id;
            $year_of_enrollment = $ben_detail->year_of_enrollment;
            $term_id = $ben_detail->term_id;
            $school_grade = $ben_detail->school_grade;
            $valtable_name = 'beneficiary_payvalidation_rulesdetails';
            $where = array(
                'beneficiary_id' => $beneficiary_id,
                'enrollment_id' => $enrollment_id
            );
            /*TODO: RULES*/
            //RULE 1: Signing of payment checklist
            //RULE 2: Repeat of a grade more than twice not allowed
            //RULE 3: Missing payments two consecutive terms
            //RULE 4: Failing to attend the required attendance rate
            //RULE 5: Having more than two kids not allowed

            //Rule 1
            if ($has_signed == 0) {
                $rule_id = 1;
                $where['rule_id'] = $rule_id;
                $data = array(
                    'beneficiary_id' => $beneficiary_id,
                    'enrollment_id' => $enrollment_id,
                    'rule_id' => $rule_id,
                    'remarks' => 'The beneficiary has not signed on the Payment verification checklist.'
                );
                $this->saveverificationValidationrules($where, $valtable_name, $data);
                $failed_rules_counter++;
            } else {
                $rule_id = 1;
                $where['rule_id'] = $rule_id;
                DB::table('beneficiary_payvalidation_rulesdetails')
                    ->where($where)
                    ->delete();
            }
            //Rule 2
            $is_rule_two_enabled = checkPaymentValidationRuleStatus(4);
            if ($is_rule_two_enabled) {
                $max_counter = $this->getRulecountvalue(4);
                $check_repeatstatus = checkBeneficiaryRepetitionStatus($max_counter, $beneficiary_id, $school_grade);
                if ($check_repeatstatus) {
                    $rule_id = 4;
                    $where['rule_id'] = $rule_id;
                    $data = array(
                        'beneficiary_id' => $beneficiary_id,
                        'enrollment_id' => $enrollment_id,
                        'rule_id' => $rule_id,
                        'remarks' => 'Beneficiary has Repeated more than the allowed no of times in the same grade'
                    );
                    $this->saveverificationValidationrules($where, $valtable_name, $data);
                    $failed_rules_counter++;
                } else {
                    $rule_id = 4;
                    $where['rule_id'] = $rule_id;
                    DB::table('beneficiary_payvalidation_rulesdetails')
                        ->where($where)
                        ->delete();
                }
            } else {
                $rule_id = 4;
                $where['rule_id'] = $rule_id;
                DB::table('beneficiary_payvalidation_rulesdetails')
                    ->where($where)
                    ->delete();
            }
            //Rule 3
            $is_rule_three_enabled = checkPaymentValidationRuleStatus(5);
            if ($is_rule_three_enabled) {
                $max_counter = $this->getRulecountvalue(5);
                $has_missed_payments = checkMissingPayments($max_counter, $beneficiary_id, $year_of_enrollment, $term_id);
                if ($has_missed_payments) {
                    $rule_id = 5;
                    $where['rule_id'] = $rule_id;
                    $data = array(
                        'beneficiary_id' => $beneficiary_id,
                        'enrollment_id' => $enrollment_id,
                        'rule_id' => $rule_id,
                        'remarks' => 'Beneficiary has missed payments more than the allowed no of times'
                    );
                    $this->saveverificationValidationrules($where, $valtable_name, $data);
                    $failed_rules_counter++;
                } else {
                    $rule_id = 5;
                    $where['rule_id'] = $rule_id;
                    DB::table('beneficiary_payvalidation_rulesdetails')
                        ->where($where)
                        ->delete();
                }
            } else {
                $rule_id = 5;
                $where['rule_id'] = $rule_id;
                DB::table('beneficiary_payvalidation_rulesdetails')
                    ->where($where)
                    ->delete();
            }
            //Rule 4
            $is_rule_four_enabled = checkPaymentValidationRuleStatus(3);
            if ($is_rule_four_enabled) {
                $less_attendance = calculateAttendanceRate($beneficiary_id, $year_of_enrollment, $term_id);
                if ($less_attendance) {
                    $rule_id = 3;
                    $where['rule_id'] = $rule_id;
                    $data = array(
                        'beneficiary_id' => $beneficiary_id,
                        'enrollment_id' => $enrollment_id,
                        'rule_id' => $rule_id,
                        'remarks' => 'Beneficiary has not reached threshold attendance rate last term'
                    );
                    $this->saveverificationValidationrules($where, $valtable_name, $data);
                    $failed_rules_counter++;
                } else {
                    $rule_id = 3;
                    $where['rule_id'] = $rule_id;
                    DB::table('beneficiary_payvalidation_rulesdetails')
                        ->where($where)
                        ->delete();
                }
            } else {
                $rule_id = 3;
                $where['rule_id'] = $rule_id;
                DB::table('beneficiary_payvalidation_rulesdetails')
                    ->where($where)
                    ->delete();
            }
            if ($failed_rules_counter > 0) {
                $failed_ids[] = array(
                    'id' => $enrollment_id
                );
            } else {
                $passed_ids[] = array(
                    'id' => $enrollment_id
                );
            }
        }
        
        $passed_ids = convertAssArrayToSimpleArray($passed_ids, 'id');
        $failed_ids = convertAssArrayToSimpleArray($failed_ids, 'id');
        DB::table('beneficiary_enrollments')
            ->whereIn('id', $passed_ids)
            ->update(array('passed_rules' => 1));
        DB::table('beneficiary_enrollments')
            ->whereIn('id', $failed_ids)
            ->update(array('passed_rules' => 0));
    }

    function validateEnrollement($id, $batch_id, $max_counter = NULL)
    {
        $qry = DB::table('beneficiary_enrollments')
            ->select('has_signed', 'year_of_enrollment', 'term_id', 'enrollment_status_id', 'id', 'beneficiary_id', 'school_grade')
            ->where(array('batch_id' => $batch_id, 'id' => $id))
            ->first();
        $is_validated = 0;
        $table_data = array();
        if ($qry) {
            $has_signed = $qry->has_signed;
            $enrollment_id = $qry->id;
            $beneficiary_id = $qry->beneficiary_id;
            $id = $qry->id;
            $is_validated = 1;
            $year_of_enrollment = $qry->year_of_enrollment;
            $school_grade = $qry->school_grade;
            $valtable_name = 'beneficiary_payvalidation_rulesdetails';
            $where = array('beneficiary_id' => $beneficiary_id, 'enrollment_id' => $enrollment_id);
            if ($has_signed == 0) {
                $is_validated = 0;
                $rule_id = 1;
                $where['rule_id'] = $rule_id;
                $data = array(
                    'beneficiary_id' => $beneficiary_id,
                    'enrollment_id' => $enrollment_id,
                    'rule_id' => $rule_id,
                    'remarks' => 'The beneficiary has not signed on the Payment verification checklist.'
                );
                $table_data['enrollment_status_id'] = 0;

                //save the verification rules
                $this->saveverificationValidationrules($where, $valtable_name, $data);
            } else {
                $table_data['enrollment_status_id'] = 1;

            }
            $check_repeatstatus = $this->checkforEnrollmentrepeatStatus(4, $beneficiary_id, $batch_id, $max_counter, $year_of_enrollment, $school_grade);
            if ($check_repeatstatus) {
                $is_validated = 0;
                $rule_id = 4;
                $where['rule_id'] = $rule_id;
                $data = array(
                    'beneficiary_id' => $beneficiary_id,
                    'enrollment_id' => $enrollment_id,
                    'rule_id' => $rule_id,
                    'remarks' => 'Beneficiary has Repeated more than the allowed no of times in the same grade'
                );
                $this->saveverificationValidationrules($where, $valtable_name, $data);
            }
            //update the beneficiaryinformation
            $where_data = array(
                'batch_id' => $batch_id,
                'id' => $id
            );
            $table_name = 'beneficiary_enrollments';

            $table_data['is_validated'] = $is_validated;

            $table_data['updated_at'] = Carbon::now();
            $table_data['updated_by'] = $this->user_id;
            $previous_data = getPreviousRecords($table_name, $where_data);
            updateRecord($table_name, $previous_data, $where_data, $table_data, $this->user_id);
        }
        return $is_validated;
    }

    function getRulecountvalue($rule_id)
    {
        $counter = 0;
        $qry = DB::table('payment_validation_rules')->where(array('id' => $rule_id))->value('counter');
        if ($qry) {
            $counter = $qry;
        }
        return $counter;
    }

    public function validateEnrollmentBatchRecord(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $where = array(
                'batch_id' => $batch_id,
                'passed_rules' => 1
            );
            DB::table('beneficiary_enrollments')
                ->where($where)
                ->where('is_gce_external_candidate', '=', 1)
                //->whereRaw('decrypt(term1_fees)>5 AND decrypt(term2_fees)>5')
                // ->whereRaw(
                // 'decrypt(term1_fees)>=0 
                // AND decrypt(term2_fees)>=0'
                // )
                ->whereRaw('decrypt(term2_fees)>=0')//job on 13/4/2022
                // ->whereRaw('decrypt(term3_fees)<5')
                ->update(array('is_validated' => 1));
                //initial
            // DB::table('beneficiary_enrollments')
            //     ->where($where)
            //     //->where('is_gce_external_candidate', '<>', 1)
            //     ->whereRaw('NOT is_gce_external_candidate <=> 1')//handles NULL values
            //     ->whereRaw('decrypt(term1_fees)>5 AND decrypt(term2_fees)>5 AND decrypt(term3_fees)>5')
            //     ->update(array('is_validated' => 1));

                //job on 21/3/2022
                DB::table('beneficiary_enrollments')
                ->where($where)
                //->where('is_gce_external_candidate', '<>', 1)
                ->whereRaw('NOT is_gce_external_candidate <=> 1')//handles NULL values
                // ->whereRaw('
                //     decrypt(term1_fees)>=0 
                //     AND decrypt(term2_fees)>=0 
                //     AND decrypt(term3_fees)>=0')
                ->whereRaw('decrypt(term2_fees)>=0')
                ->update(array('is_validated' => 1));
            $res = array(
                'success' => true,
                'message' => 'Request executed successfully!!'
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

    public function validateEnrollmentSelectedRecords(Request $req)
    {
        $selected = $req->input('selected');
        $selected_ids = json_decode($selected);
        try {
            DB::table('beneficiary_enrollments')
                ->whereRaw('decrypt(term1_fees)>5 AND decrypt(term2_fees)>5 AND decrypt(term3_fees)>5')
                ->whereIn('id', $selected_ids)
                ->update(array('is_validated' => 1));
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

    public function savevalidateEnrollementBatchrecord(Request $req)
    {
        //the details
        $batch_id = $req->input('batch_id');
        //geth the batch details and enrolmment details
        $qry = DB::table('payment_verificationbatch as t1')
            ->select('t2.beneficiary_id', 't2.id as enrollment_id')
            ->join('beneficiary_enrollments as t2', 't1.id', '=', 't2.batch_id')
            ->where(array('batch_id' => $batch_id))
            ->get();

        $results = convertStdClassObjToArray($qry);
        $results = decryptArray($results);
        $success = false;
        foreach ($results as $rec) {
            //save the details
            $enrollment_id = $rec['enrollment_id'];
            $success = $this->validateEnrollement($enrollment_id, $batch_id);
        }
        if ($success) {
            $resp = array('success' => true, 'message' => 'Beneficiary Enrollment validation updated successfully');

        } else {

            $resp = array('success' => false, 'message' => 'Error occurred, Please contact the system administrator');
        }
        json_output($resp);
    }

    public function savevalidateEnrollementrecord(Request $req)
    {
        $id = $req->input('id');
        $girl_id = $req->input('girl_id');
        $batch_id = $req->input('batch_id');
        $is_validated = $req->input('is_validated');
        $validation_reason = $req->input('validation_reason');
        if ($is_validated == 1) {
            $from_recomm = 0;
        } else {
            $from_recomm = 1;
        }
        $where_data = array(
            'batch_id' => $batch_id,
            'id' => $id
        );
        $table_name = 'beneficiary_enrollments';
        $table_data = array('is_validated' => $is_validated);
        $table_data['updated_at'] = Carbon::now();
        $table_data['updated_by'] = $this->user_id;
        $previous_data = getPreviousRecords($table_name, $where_data);
        $success = updateRecord($table_name, $previous_data, $where_data, $table_data, $this->user_id);
        $log_params = array(
            'girl_id' => $girl_id,
            'enrollment_id' => $id,
            'batch_id' => $batch_id,
            'from_recomm' => $from_recomm,
            'to_recomm' => $is_validated,
            'changes_by' => $this->user_id,
            'changes_on' => Carbon::now(),
            'reason' => $validation_reason
        );
        if ($success) {
            insertRecord('paymentvalidation_overrule_logs', $log_params, $this->user_id);
            $resp = array(
                'success' => true,
                'message' => 'Beneficiary Enrollment validation updated successfully'
            );
        } else {
            $resp = array(
                'success' => false,
                'message' => 'Error occurred, Please contact the system administrator
                ');
        }
        json_output($resp);
    }

    public function getpaymentrequestConsolidationsOld(Request $req)
    {
        try {
            $status_id = $req->input('status_id');
            $term = $req->input('term');
            $year = $req->input('year');
            $qry = DB::table('payment_request_details as t1')
                ->select(DB::raw("count(beneficiary_id) as no_of_beneficiaries, 
                    SUM(
                      IF(decrypt(t6.term1_fees) IS NULL OR decrypt(t6.term1_fees) < 0, 0, decrypt (t6.term1_fees)) +
                      IF(decrypt(t6.term2_fees) IS NULL OR decrypt(t6.term2_fees) < 0, 0, decrypt(t6.term2_fees)) +
                      IF(decrypt(t6.term3_fees) IS NULL OR decrypt(t6.term3_fees) < 0, 0, decrypt(t6.term3_fees))
                    ) as total_fees,
                 t1.*, t1.id as payment_request_id,
                              st.name as approval_status_name,t2.id as prepared_by_id, CONCAT_WS(' ',decrypt(t2.first_name),decrypt(t2.last_name)) as prepared_by,
                              t1.check_comment,t3.id as checked_by_id, CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as checked_byname,
                              COUNT(DISTINCT(t6.school_id)) as no_of_schools,t4.id as approved_by_id, CONCAT_WS(' ',decrypt(t4.first_name),decrypt(t4.last_name)) as approved_byname
                              "))
                ->leftJoin('school_transfer_statuses as st', 't1.approval_status', '=', 'st.id')
                ->leftJoin('users as t2', 't1.prepared_by', '=', 't2.id')
                ->leftJoin('users as t3', 't1.checked_by', '=', 't3.id')
                ->leftJoin('users as t4', 't1.approved_by', '=', 't4.id')
                ->leftJoin('beneficiary_payment_records as t5', 't1.id', '=', 't5.payment_request_id')
                ->leftJoin('beneficiary_enrollments as t6', 't5.enrollment_id', '=', 't6.id')
                //->leftJoin('reconciliation_suspense_account as t7', 't7.payment_request_id', '=', 't1.id')
                ->where(array('status_id' => $status_id));
            if (isset($year) && $year != '') {
                $qry->where('t1.payment_year', $year);
            }
            if (isset($term) && $term != '') {
                //$qry->where('t1.term_id', $term);
            }
            $qry->groupBy('t1.id')
                ->orderBy('t1.id', 'DESC');
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
    public function getpaymentrequestConsolidations(Request $req) // 31st March 2026
    {
        try {
            $status_id = $req->input('status_id');
            $term = $req->input('term');
            $year = $req->input('year');
            $qry = DB::table('payment_request_details as t1')
                ->select(DB::raw("count(beneficiary_id) as no_of_beneficiaries, 
                    t6.total_payable_fees as total_fees,
                 t1.*, t1.id as payment_request_id,
                              st.name as approval_status_name,t2.id as prepared_by_id, CONCAT_WS(' ',decrypt(t2.first_name),
                              decrypt(t2.last_name)) as prepared_by,
                              t1.check_comment,t3.id as checked_by_id, CONCAT_WS(' ',decrypt(t3.first_name),
                              decrypt(t3.last_name)) as checked_byname,
                              COUNT(DISTINCT(t6.school_id)) as no_of_schools,t4.id as approved_by_id, CONCAT_WS(' ',
                              decrypt(t4.first_name),decrypt(t4.last_name)) as approved_byname
                              "))
                ->leftJoin('school_transfer_statuses as st', 't1.approval_status', '=', 'st.id')
                ->leftJoin('users as t2', 't1.prepared_by', '=', 't2.id')
                ->leftJoin('users as t3', 't1.checked_by', '=', 't3.id')
                ->leftJoin('users as t4', 't1.approved_by', '=', 't4.id')
                ->leftJoin('beneficiary_payment_records as t5', 't1.id', '=', 't5.payment_request_id')
                ->leftJoin('beneficiary_payresponses_report as t6', 't5.enrollment_id', '=', 't6.id')
                //->leftJoin('reconciliation_suspense_account as t7', 't7.payment_request_id', '=', 't1.id')
                ->where(array('status_id' => $status_id));
            if (isset($year) && $year != '') {
                $qry->where('t1.payment_year', $year);
            }
            if (isset($term) && $term != '') {
                //$qry->where('t1.term_id', $term);
            }
            $qry->groupBy('t1.id')
                ->orderBy('t1.id', 'DESC');
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

    public function getValidateBeneficiaryschsummaryOld(Request $req)
    {
        $province_id = $req->input('province_id');
        $district_id = $req->input('district_id');
        $year = $req->input('year');
        $term = $req->input('term');
        $start = $req->input('start');
        $limit = $req->input('limit');
        try {
            $qry = DB::table('beneficiary_enrollments as t5')
                ->select(DB::raw('t7.name as term_name,t6.year_of_enrollment,t2.name as school_name,
                                  count(t5.beneficiary_id) as no_of_beneficiaries,
                                  sum(decrypt(t5.annual_fees)) as total_fees'))
                ->join('school_information as t2', 't5.school_id', '=', 't2.id')
                ->join('payment_verificationbatch as t6', 't5.batch_id', '=', 't6.id')
                ->leftJoin('school_terms as t7', 't6.term_id', '=', 't7.id')
                ->leftJoin('beneficiary_payment_records as t8', 't5.id', '=', 't8.enrollment_id')
                ->whereNull('t8.payment_request_id')
                ->where(array('is_validated' => 1, 't5.submission_status' => 2));
            if (isset($province_id) && $province_id != '') {
                $qry->where('t2.province_id', $province_id);
            }
            if (isset($district_id) && $district_id != '') {
                $qry->where('t2.district_id', $district_id);
            }
            if (isset($year) && $year != '') {
                $qry->where('t6.year_of_enrollment', $year);
            }
            if (isset($term) && $term != '') {
                $qry->where('t6.term_id', $term);
            }
            $qry->groupBy('t2.id', 't6.year_of_enrollment');//, 't6.term_id');
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
                'success' => true,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }
    public function getValidateBeneficiaryschsummary(Request $req) // 31st March 2026
    {
        $province_id = $req->input('province_id');
        $district_id = $req->input('district_id');
        $year = $req->input('year');
        $term = $req->input('term');
        $start = $req->input('start');
        $limit = $req->input('limit');
        try {
            $qry = DB::table('beneficiary_payresponses_report as t5')
                ->select(DB::raw('t7.name as term_name,t5.year_of_enrollment,t2.name as school_name,
                                  count(t5.beneficiary_id) as no_of_beneficiaries,
                                  sum(t5.total_payable_fees) as total_fees'))
                ->join('school_information as t2', 't5.school_id', '=', 't2.id')
                ->leftJoin('school_terms as t7', 't5.term_id', '=', 't7.id');

            if (isset($province_id) && $province_id != '') {
                $qry->where('t2.province_id', $province_id);
            }
            if (isset($district_id) && $district_id != '') {
                $qry->where('t2.district_id', $district_id);
            }
            if (isset($year) && $year != '') {
                $qry->where('t5.year_of_enrollment', $year);
            }
            if (isset($term) && $term != '') {
                $qry->where('t5.term_id', $term);
            }
            $qry->groupBy('t5.school_id', 't5.year_of_enrollment');//, 't6.term_id');
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
                'success' => true,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getBeneficiary_requestpaymentInfoOld(Request $req)
    {
        try {
            $payment_request_id = $req->input('payment_request_id');
            $payment_status_id = $req->input('payment_status_id');
            $province_id = $req->input('province_id');
            $district_id = $req->input('district_id');
            $school_status = $req->input('school_status');//job on 21/4/2022
            $school_fees_status = $req->input('school_fees_status');//job on 30/05/2022
            $new_entrants= $req->input('new_entrants');//job on 03/11/2022
         
            if(isset($school_status) && $school_status!=null)
            {
            $school_status=explode(',',$school_status);
            }
            if($school_status==null)//Job on 03/11/2022
            {
                $school_status = $req->input('school_status_id');
                if($school_status!=null)
                {
                    $school_status=explode(',',$school_status);
                }
                
            }
           
            // Get default limit
            $normalTimeLimit = ini_get('max_execution_time');//Job on 03/11/2022

            // Set new limit
            ini_set('max_execution_time', 30000); 
        
            $qry = DB::table('beneficiary_information as t1')
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                ->leftJoin('districts as t3', 't2.district_id', '=', 't3.id')
                ->leftJoin('provinces as t4', 't3.province_id', '=', 't4.id')
                ->join('beneficiary_enrollments as t5', 't1.id', '=', 't5.beneficiary_id')
                ->leftJoin('beneficiary_school_statuses as t7', 't5.beneficiary_schoolstatus_id', '=', 't7.id')
                ->join('beneficiary_payment_records as t8', 't5.id', '=', 't8.enrollment_id')
                //->join('school_terms as t9', 't5.term_id', '=', 't9.id')
                ->leftJoin('payment_disbursement_details as t11', function ($join) {
                    $join->on('t8.payment_request_id', '=', 't11.payment_request_id');
                    $join->on('t2.id', '=', 't11.school_id');
                })
                ->select(DB::raw("t2.id as school_id, t1.id as beneficiary_id, t5.id as enrollment_id,t1.beneficiary_id as beneficiary_no, t5.year_of_enrollment,
                (IFNULL(decrypt(t5.term1_fees),0) + IFNULL(decrypt(t5.term2_fees),0) + IFNULL(decrypt(t5.term3_fees),0) + IFNULL(decrypt(t5.exam_fees),0)) as school_fees,  decrypt(t5.term1_fees) as term1_fees,decrypt(t5.term2_fees) as term2_fees,
                decrypt(t5.term3_fees) as term3_fees,decrypt(t5.exam_fees) as exam_fees, t5.school_grade, t1.dob, decrypt(t1.first_name) as first_name, decrypt(t1.last_name) as last_name,
                t2.name as school_name,t2.name as school_name2, t3.name as school_district, t4.name as school_province,t7.name as school_status"))
                ->where(array('t8.payment_request_id' => $payment_request_id))
                ->whereRaw("IF(`t8`.`payment_request_id` = `t11`.`payment_request_id`, `payment_status_id` = 1,1)");
            
                if(is_array($school_status) &&  count($school_status)>0)//job on 21/04/2022
                {
                    $qry->whereIn('t5.beneficiary_schoolstatus_id',$school_status);//job on 19/4/2022;
                }
                if (isset($province_id) && $province_id != '') {
                $qry->where('t4.id', $province_id);
            }
            if (isset($district_id) && $district_id != '') {
                $qry->where('t3.id', $district_id);
            }

            if(isset($school_fees_status) && $school_fees_status!="")//Job on 30/05/2022
            {
                if($school_fees_status==1){
                $qry->whereRaw('decrypt(t5.annual_fees)>0');
                }else{
                    $qry->whereRaw('decrypt(t5.annual_fees)=0');
                }
            }
            $results = $qry->get()->toArray();
              $new_entrants_year = date('Y');
            if($new_entrants==1){
            $old_beneficiries=Db::select("SELECT t5.beneficiary_id as ben_id FROM beneficiary_enrollments AS t1 
            INNER JOIN beneficiary_payment_records AS t2 ON t1.id=t2.enrollment_id LEFT JOIN beneficiary_information as t5 on t5.id=t1.beneficiary_id  WHERE t1.year_of_enrollment<$new_entrants_year  GROUP BY t1.beneficiary_id");
              $old_beneficiries_data=array();
            
           foreach($old_beneficiries as $ben_data)
           {
            $old_beneficiries_data[]=$ben_data->ben_id;
           }
           $new_entrants_data=array();
    
          
                foreach($results as $key=>$result)
                {
                    if(!in_array($result->beneficiary_no,$old_beneficiries_data))
                    {
                        $new_entrants_data[]=$result;
                       
                    }
                }
               
                $results= $new_entrants_data;
        }
            $res = array(
                'success' => true,
                'results' => $results,
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
        ini_set('max_execution_time', $normalTimeLimit); //Job on 03/11/2022
        return response()->json($res);
    }

    public function getBeneficiary_requestpaymentInfo(Request $req) // 31st March 2026
    {
        try {
            $payment_request_id = $req->input('payment_request_id');
            $payment_status_id = $req->input('payment_status_id');
            $province_id = $req->input('province_id');
            $district_id = $req->input('district_id');
            $school_status = $req->input('school_status');//job on 21/4/2022
            $school_fees_status = $req->input('school_fees_status');//job on 30/05/2022
            $new_entrants= $req->input('new_entrants');//job on 03/11/2022
         
            if(isset($school_status) && $school_status!=null)
            {
            $school_status=explode(',',$school_status);
            }
            if($school_status==null)//Job on 03/11/2022
            {
                $school_status = $req->input('school_status_id');
                if($school_status!=null)
                {
                    $school_status=explode(',',$school_status);
                }
                
            }
           
            // Get default limit
            $normalTimeLimit = ini_get('max_execution_time');//Job on 03/11/2022

            // Set new limit
            ini_set('max_execution_time', 30000); 
        
            $qry = DB::table('beneficiary_payresponses_report as t5')
                ->join('school_information as t2', 't5.school_id', '=', 't2.id')
                ->leftJoin('districts as t3', 't2.district_id', '=', 't3.id')
                ->leftJoin('provinces as t4', 't3.province_id', '=', 't4.id')
                ->leftJoin('beneficiary_school_statuses as t7', 't5.beneficiary_schoolstatus_id', '=', 't7.id')
                ->join('beneficiary_payment_records as t8', 't5.id', '=', 't8.enrollment_id')
                //->join('school_terms as t9', 't5.term_id', '=', 't9.id')
                ->leftJoin('payment_disbursement_details as t11', function ($join) {
                    $join->on('t8.payment_request_id', '=', 't11.payment_request_id');
                    $join->on('t2.id', '=', 't11.school_id');
                })
                ->select(DB::raw("t2.id as school_id, t5.girl_id as beneficiary_id, t5.id as enrollment_id,
                t5.beneficiary_id as beneficiary_no, t5.year_of_enrollment,
                t5.term1_fee as school_fees,  
                t5.term1_fee as term1_fees,0 as term2_fees,
                0 as term3_fees,t5.exam_fees as exam_fees, t5.confirmed_grade as school_grade, 
                t5.verified_dob as dob, t5.first_name, t5.surname as last_name,
                t2.name as school_name,t2.name as school_name2, t3.name as school_district, 
                t4.name as school_province,t7.name as school_status"))
                ->where(array('t8.payment_request_id' => $payment_request_id))
                ->whereRaw("IF(`t8`.`payment_request_id` = `t11`.`payment_request_id`, `payment_status_id` = 1,1)");
            
                if(is_array($school_status) &&  count($school_status)>0)//job on 21/04/2022
                {
                    $qry->whereIn('t5.beneficiary_schoolstatus_id',$school_status);//job on 19/4/2022;
                }
                if (isset($province_id) && $province_id != '') {
                $qry->where('t4.id', $province_id);
            }
            if (isset($district_id) && $district_id != '') {
                $qry->where('t3.id', $district_id);
            }
            $results = $qry->get()->toArray();
            
            $res = array(
                'success' => true,
                'results' => $results,
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
        ini_set('max_execution_time', $normalTimeLimit); //Job on 03/11/2022
        return response()->json($res);
    }
    

    public function getValidatedBenschoolsPaymentinfoOld(Request $req)
    {
        $term_id = $req->input('term_id');
        $year_of_enrollment = $req->input('payment_year');
        $province_id = $req->input('province_id');
        $district_id = $req->input('district_id');
        $start = $req->input('start');
        $limit = $req->input('limit');

        $school_status = $req->input('school_status');//job on 20/4/2022
        $running_agency_details=$req->input('running_agency');//27/04/2022
        if(isset($school_status) && $school_status!=null && $school_status!="")
        {
        $school_status=explode(',',$school_status);
        }
       
        if(isset($running_agency_details) && $running_agency_details!=null)
        {
        $running_agency_details=explode(',',$running_agency_details);
        }
        //job on 23/04/2022
        $terms_to_include=[1,2];
        $fees_string=[];
        if(count($terms_to_include)==3)
        {
            $fees_string="sum(decrypt(t5.annual_fees)) as school_feessummary";
        }

        $terms_to_exclude=[];
        for($i=1;$i<4;$i++)
        {
            if(!in_array($i,$terms_to_include))
            {
                $terms_to_exclude[]=$i;
            }
        }
        if(count($terms_to_exclude)==1 || count($terms_to_exclude)==2)
        {
            foreach($terms_to_exclude as $term)
            {
                if($term==1)
                {
                    $fees_string[]="t5.term1_fees";
                }
                if($term==2)
                {
                    $fees_string[]="t5.term2_fees";
                }
                if($term==3)
                {
                    $fees_string[]="t5.term3_fees";
                }
            }
        }

        $final_fee_string="";
        if(is_array($fees_string))
        {
            if(count($fees_string)==2)
            {
                $final_fee_string="sum(decrypt(t5.annual_fees)-(decrypt($fees_string[0])+decrypt($fees_string[1]))) as school_feessummary";
                //$final_fee_string="decrypt($fees_string[0])+decrypt($fees_string[1])";
            }else{
                $final_fee_string="sum(decrypt(t5.annual_fees)-decrypt($fees_string[0])) as school_feessummary"; 
            }
        }else{
            $final_fee_string=$fees_string;
        }
        
    
        try {
            $qry = DB::table('beneficiary_enrollments as t5')
             ->select(DB::raw(' t2.id as school_id, t2.name as school_name, t5.year_of_enrollment,
                                  t4.name as  province_name, t3.name as district_name, count(t5.beneficiary_id) as no_of_beneficiary,
                                   sum(decrypt(t5.annual_fees)) as school_feessummary,t12.name as running_agency'))

            //->selectraw('decrypt(t5.annual_fees),t5.beneficiary_id,t5.id')
                        // ->select(DB::raw(' t2.id as school_id, t2.name as school_name, t5.year_of_enrollment,
                        //           t4.name as  province_name, t3.name as district_name, (t5.beneficiary_id) as no_of_beneficiary,
                        //           (decrypt(t5.annual_fees)) as school_feessummary,beneficiary_schoolstatus_id'))
               
                //  ->select(DB::raw(" t2.id as school_id, t2.name as school_name, t5.year_of_enrollment,
                //                   t4.name as  province_name, t3.name as district_name, count(t5.beneficiary_id) as no_of_beneficiary,
                //                   $final_fee_string"))
        //   ->selectraw(Db::raw("t5.id,t5.beneficiary_id,$final_fee_string,decrypt(term1_fees),decrypt(term2_fees),decrypt(term3_fees),decrypt(term2_fees)+decrypt(term3_fees),
        // decrypt(t5.annual_fees)-decrypt(t5.term3_fees),decrypt(t5.annual_fees)"))
                ->join('school_information as t2', 't5.school_id', '=', 't2.id')
                ->leftjoin('school_running_agencies as t12','t2.running_agency_id','t12.id')
                ->join('districts as t3', 't2.district_id', '=', 't3.id')
                ->join('provinces as t4', 't3.province_id', '=', 't4.id')
                ->join('payment_verificationbatch as t6', 't5.batch_id', '=', 't6.id')
                ->leftJoin('beneficiary_school_statuses as t7', 't5.beneficiary_schoolstatus_id', '=', 't7.id')
                ->leftJoin('beneficiary_payment_records as t8', 't5.id', '=', 't8.enrollment_id')
                //->join('school_terms as t9', 't5.term_id', '=', 't9.id')
                ->whereNull('t8.payment_request_id')
                ->where(array('t5.is_validated' => 1, 't5.submission_status' => 2, 't5.year_of_enrollment' => $year_of_enrollment));
            if (isset($province_id) && $province_id != '') {
                $qry->where('t2.province_id', $province_id);
            }
            if (isset($district_id) && $district_id != '') {
                $qry->where('t2.district_id', $district_id);
            }
            if(is_array($school_status) &&  count($school_status)>0)//job on 20/04/2022
            {
               $qry->whereIn('beneficiary_schoolstatus_id',$school_status);//job on 19/4/2022;
            }

             //27/04/2022
             if(is_array($running_agency_details) && count($running_agency_details)>0)
             {
                 
                 $qry->whereIn('running_agency_id',$running_agency_details);
             }
            $qry->groupBy('t2.id');
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
        } catch (\Throwable $t) {
            $res = array(
                'success' => false,
                'message' => $t->getMessage()
            );
        }
        return response()->json($res);
    }
    public function getValidatedBenschoolsPaymentinfo(Request $req) // 31st March 2026
    {
        $term_id = $req->input('term_id');
        $year_of_enrollment = $req->input('payment_year');
        $province_id = $req->input('province_id');
        $district_id = $req->input('district_id');
        $start = $req->input('start');
        $limit = $req->input('limit');

        $school_status = $req->input('school_status');//job on 20/4/2022
        $running_agency_details=$req->input('running_agency');//27/04/2022
        if(isset($school_status) && $school_status!=null && $school_status!="")
        {
        $school_status=explode(',',$school_status);
        }
       
        if(isset($running_agency_details) && $running_agency_details!=null)
        {
        $running_agency_details=explode(',',$running_agency_details);
        }
        //job on 23/04/2022
        $terms_to_include=[1,2];
        $fees_string=[];
        // if(count($terms_to_include)==3)
        // {
        //     $fees_string="sum(t5.annual_fees) as school_feessummary";
        // }

        $terms_to_exclude=[];
        // for($i=1;$i<4;$i++)
        // {
        //     if(!in_array($i,$terms_to_include))
        //     {
        //         $terms_to_exclude[]=$i;
        //     }
        // }
        // if(count($terms_to_exclude)==1 || count($terms_to_exclude)==2)
        // {
        //     foreach($terms_to_exclude as $term)
        //     {
        //         if($term==1)
        //         {
        //             $fees_string[]="t5.term1_fees";
        //         }
        //         if($term==2)
        //         {
        //             $fees_string[]="t5.term2_fees";
        //         }
        //         if($term==3)
        //         {
        //             $fees_string[]="t5.term3_fees";
        //         }
        //     }
        // }

        $final_fee_string="";
        // if(is_array($fees_string))
        // {
        //     if(count($fees_string)==2)
        //     {
        //         $final_fee_string="sum(decrypt(t5.annual_fees)-(decrypt($fees_string[0])+decrypt($fees_string[1]))) as school_feessummary";
        //         //$final_fee_string="decrypt($fees_string[0])+decrypt($fees_string[1])";
        //     }else{
        //         $final_fee_string="sum(decrypt(t5.annual_fees)-decrypt($fees_string[0])) as school_feessummary"; 
        //     }
        // }else{
        //     $final_fee_string=$fees_string;
        // }
        
    
        try {
            $qry = DB::table('beneficiary_payresponses_report as t5')
             ->select(DB::raw(' t2.id as school_id, t2.name as school_name, t5.year_of_enrollment,
                t4.name as  province_name, t3.name as district_name, count(t5.beneficiary_id) as no_of_beneficiary,
                sum(t5.total_payable_fees) as school_feessummary,t12.name as running_agency'))
                ->join('school_information as t2', 't5.school_id', '=', 't2.id')
                ->leftjoin('school_running_agencies as t12','t2.running_agency_id','t12.id')
                ->join('districts as t3', 't2.district_id', '=', 't3.id')
                ->join('provinces as t4', 't3.province_id', '=', 't4.id')
                // ->join('payment_verificationbatch as t6', 't5.batch_id', '=', 't6.id')
                ->leftJoin('beneficiary_school_statuses as t7', 't5.beneficiary_schoolstatus_id', '=', 't7.id')
                ->leftJoin('beneficiary_payment_records as t8', 't5.id', '=', 't8.enrollment_id')
                //->join('school_terms as t9', 't5.term_id', '=', 't9.id')
                ->whereNull('t8.payment_request_id')
                ->where(array('t5.year_of_enrollment' => $year_of_enrollment));
            if (isset($province_id) && $province_id != '') {
                $qry->where('t2.province_id', $province_id);
            }
            if (isset($district_id) && $district_id != '') {
                $qry->where('t2.district_id', $district_id);
            }
            if(is_array($school_status) &&  count($school_status)>0)//job on 20/04/2022
            {
               $qry->whereIn('beneficiary_schoolstatus_id',$school_status);//job on 19/4/2022;
            }

             //27/04/2022
             if(is_array($running_agency_details) && count($running_agency_details)>0)
             {
                 
                 $qry->whereIn('running_agency_id',$running_agency_details);
             }
            $qry->groupBy('t2.id');
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
        } catch (\Throwable $t) {
            $res = array(
                'success' => false,
                'message' => $t->getMessage()
            );
        }
        return response()->json($res);
    }
    public function getValidatedBeneficiariesPaymentinfo(Request $req)
    {
        $term_id = $req->input('term_id');
        $year_of_enrollment = $req->input('payment_year');
        $qry = DB::table('beneficiary_information as t1')
            ->select('t6.batch_no', 't9.name as school_term', 't1.id as beneficiary_id', 't5.id as enrollment_id', 't1.beneficiary_id as beneficiary_no', 't5.year_of_enrollment', 't5.school_fees', 't5.school_grade', 't1.dob', 't1.first_name', 't1.last_name', 't2.name as school_name', 't3.name as school_district', 't4.name as school_province', 't7.name as school_status', 't9.name as  school_term')
            ->join('school_information as t2', 't1.school_id', '=', 't2.id')
            ->join('districts as t3', 't2.district_id', '=', 't3.id')
            ->join('provinces as t4', 't3.province_id', '=', 't4.id')
            ->join('beneficiary_enrollments as t5', 't1.id', '=', 't5.beneficiary_id')
            ->join('payment_verificationbatch as t6', 't5.batch_id', '=', 't6.id')
            ->leftJoin('beneficiary_school_statuses as t7', 't5.beneficiary_schoolstatus_id', '=', 't7.id')
            ->leftJoin('beneficiary_payment_records as t8', 't5.id', '=', 't8.enrollment_id')
            ->join('school_terms as t9', 't5.term_id', '=', 't9.id')
            ->whereNull('t8.payment_request_id')
            ->where(array('t6.status_id' => 4, 'is_validated' => 1, 't5.term_id' => $term_id, 't5.year_of_enrollment' => $year_of_enrollment))
            ->get();
        //print_r(DB::getQueryLog());
        $results = convertStdClassObjToArray($qry);
        $results = decryptArray($results);

        $res = array(
            'results' => $results
        );
        //var_dump($res);
        json_output($res);

    }

    public function submitsinglePaymentreconcolliation(Request $req)
    {
        $payment_request_id = $req->payment_request_id;
        $school_id = $req->school_id;

        //check if all the payments hasve been sibursed
        $sql = DB::table('beneficiary_enrollments as t5')
            ->select('t12.id', 't12.status_id')
            ->join('school_information as t2', 't2.id', '=', 't5.school_id')
            ->join('beneficiary_payment_records as t8', 't5.id', '=', 't8.enrollment_id')
            ->join('payment_request_details as t12', 't8.payment_request_id', '=', 't12.id')
            ->leftJoin('payment_disbursement_details as t11', function ($join) {
                $join->on('t8.payment_request_id', '=', 't11.payment_request_id');
                $join->on('t2.id', '=', 't11.school_id');
            })
            ->where(array('t8.payment_request_id' => $payment_request_id, 't5.school_id' => $school_id))
            ->groupBy('t2.id')
            ->get();
        $resp = $resp = array('success' => true);
        $table_name = 'payment_request_details';
        if ($sql) {

            $where = array('school_id' => $school_id, 'payment_request_id' => $payment_request_id);
            DB::table('payment_disbursement_details')
                ->where($where)
                ->update(array('payment_status_id' => 2));
            $resp = array('success' => true, 'message' => 'Payment submitted for reconcilliation');

        } else {
            $resp = array('success' => false, 'message' => 'Payment Disbursement not complete for all schools, confirm or submit partially for reconcilliation');

        }
        json_output($resp);
    }

    public function submitReceipttoPaymentReconciliation(Request $req)
    {
        $status_id = $req->input('status_id');
        $remark = $req->input('remark');
        $payment_receipts_id = $req->input('payment_receipts_id');
        $table_name = 'payment_receiptingbatch';
        $qry = DB::table('payments_receipting_details as t1')
            ->join('beneficiary_receipting_details as t2', 't1.id', '=', 't2.payment_receipt_id')
            ->where('t1.payment_receipts_id', $payment_receipts_id)
            ->count();
        $where_data = array(
            'id' => $payment_receipts_id
        );
        if ($qry > 0) {
            $data = array(
                'status_id' => $status_id,
                'updated_at' => Carbon::now(),
                'updated_by' => $this->user_id
            );
            if ($status_id == 10) {
                $data['reconciliation_remark'] = $remark;
                $data['reconciliation_author'] = $this->user_id;
                $check = DB::table('payments_receipting_details as t1')
                    ->LeftJoin('beneficiary_receipting_details as t2', 't1.id', '=', 't2.payment_receipt_id')
                    ->where('t1.payment_receipts_id', $payment_receipts_id)
                    ->whereNull('t2.id')
                    ->count();
                if ($check > 0) {
                    $res = array(
                        'success' => false,
                        'message' => 'To \'Mark as Validated\', ensure that receipt details for all beneficiaries are captured!!'
                    );
                    return response()->json($res);
                }
            } else {
                $data['validation_remark'] = $remark;
                $data['validation_author'] = $this->user_id;
            }
            $previous_data = getPreviousRecords($table_name, $where_data);
            updateRecord($table_name, $previous_data, $where_data, $data, $this->user_id);
            $res = array(
                'success' => true,
                'message' => 'Receipts details submitted successfully!!'
            );
        } else {
            $res = array(
                'success' => false,
                'message' => 'Enter beneficiary receipt details before submission!!'
            );
        }
        return response()->json($res);
    }

    public function submittoPaymentReconciliation(Request $req)
    {
        $post_data = $req->all();
        $payment_request_id = $req->payment_request_id;
        $status_id = $req->status_id;
        $data = $post_data['data'];
        $records = explode(',', $data);

        foreach ($records as $payment_disbursement_id) {
            $table_name = 'payment_disbursement_details';
            $where_data = array('id' => $payment_disbursement_id);
            $sql = DB::table('payment_disbursement_details as t1')
                ->where($where_data)
                ->first();
            $table_data = array('payment_status_id' => 2);
            $table_data['updated_at'] = Carbon::now();
            $table_data['updated_by'] = $this->user_id;
            $previous_data = getPreviousRecords($table_name, $where_data);
            updateRecord($table_name, $previous_data, $where_data, $table_data, $this->user_id);

        }
        //check if theere is any without payments and also status 2

        //get any payments with status 1
        $sql = DB::table('beneficiary_enrollments as t5')
            ->select('t12.id', 't12.status_id')
            ->join('school_information as t2', 't2.id', '=', 't5.school_id')
            ->join('beneficiary_payment_records as t8', 't5.id', '=', 't8.enrollment_id')
            ->join('payment_request_details as t12', 't8.payment_request_id', '=', 't12.id')
            ->leftJoin('payment_disbursement_details as t11', function ($join) {
                $join->on('t8.payment_request_id', '=', 't11.payment_request_id');
                $join->on('t2.id', '=', 't11.school_id');
            })
            ->where(array('t8.payment_request_id' => $payment_request_id))
            ->where(function ($query) use ($payment_request_id) {
                $query->where('t11.payment_status_id', '=', 1)
                    ->whereNull('amount_transfered');
            })
            ->groupBy('t2.id')
            ->count();
        if ($sql == 0) {
            $data = array('status_id' => $status_id);

            $table_name = 'payment_request_details';
            $where = array('id' => $payment_request_id);
            $data['updated_at'] = Carbon::now();
            $data['updated_by'] = $this->user_id;
            $previous_data = getPreviousRecords($table_name, $where);

            $success = updateRecord($table_name, $previous_data, $where, $data, $this->user_id);
            //save the record in the trasntitional table
            $previous_status_id = '6';

            $table_name = 'payment_verificationtransitionsubmissions';
            //save the details in the transitional table
            $data = array(
                'payment_request_id' => $payment_request_id,
                'previous_status_id' => $previous_status_id,
                'next_status_id' => $status_id,
                'released_on' => Carbon::now(),
                'released_by' => $this->user_id
            );
            $data['created_at'] = Carbon::now();
            $data['created_by'] = $this->user_id;

            $insert_resp = insertRecord($table_name, $data, $this->user_id);
            $resp = array('success' => true);
        } else {
            $resp = array('success' => false, 'message' => 'Payment submission to payment disbursement reconcilliation, confirm payment disbursement details and select all payments for submission to reconcilliation stage.');

        }

        json_output($resp);

    }

    public function getSchoolpaymentschoolSummaryOld(Request $req)
    {
        try {

            $payment_request_id = $req->input('payment_request_id');
            $filter_id = $req->input('filter_id');
            $province_id = $req->input('province_id');
            $district_id = $req->input('district_id');
            $start = $req->input('start');
            $limit = $req->input('limit');
            $school_status = $req->input('school_status');//job on 21/4/2022
            $running_agency_details=$req->input('running_agency_id');
            $skip=$start-1;//job on 6/6/2022
         
            if(isset($school_status) && $school_status!=null)
            {
            $school_status=explode(',',$school_status);
            }
            if(isset($running_agency_details) && $running_agency_details!=null)
            {
            $running_agency_details=explode(',',$running_agency_details);
            }
            $qry = DB::table('beneficiary_information as t1')
                ->select(DB::raw('t12.name as payment_status, t11.id as 
                    payment_disbursement_id,decrypt(t11.amount_transfered) as 
                    amount_transfered,t11.imbalance_reason,t11.school_bank_id,
                    decrypt(t11.transaction_no) as transaction_no,
                    date(t11.transaction_date) as transaction_date,t11.remarks, 
                    t2.id as school_id,t8.payment_request_id,
                    b1.name as bank_name,b2.name as 
                    branch_name,t9.bank_id,decrypt(t9.account_no) as 
                    account_no,b2.sort_code,count(t1.id) as no_of_beneficiary,

                    SUM(
                      IF(decrypt(t5.term1_fees) IS NULL OR decrypt(t5.term1_fees) < 0, 0, decrypt(t5.term1_fees)) +
                      IF(decrypt(t5.term2_fees) IS NULL OR decrypt(t5.term2_fees) < 0, 0, decrypt(t5.term2_fees)) +
                      IF(decrypt(t5.term3_fees) IS NULL OR decrypt(t5.term3_fees) < 0, 0, decrypt(t5.term3_fees))
                    ) as school_feessummary,
                    SUM(
                      IF(decrypt(t5.term1_fees) IS NULL OR decrypt(t5.term1_fees) < 0, 0, decrypt(t5.term1_fees)) +
                      IF(decrypt(t5.term2_fees) IS NULL OR decrypt(t5.term2_fees) < 0, 0, decrypt(t5.term2_fees)) +
                      IF(decrypt(t5.term3_fees) IS NULL OR decrypt(t5.term3_fees) < 0, 0, decrypt(t5.term3_fees))
                    ) as payable_amount,
                    t2.name as school_name,t3.name as district_name,
                    t4.name as province_name,t13.name as running_agency'))
                ->join('beneficiary_enrollments as t5', 't1.id', '=', 
                    't5.beneficiary_id')
                ->join('school_information as t2', 't2.id', '=', 't5.school_id')
                ->leftjoin('school_running_agencies as t13','t2.running_agency_id',
                    't13.id')//job on 4/05/2022
                ->join('districts as t3', 't2.district_id', '=', 't3.id')
                ->join('provinces as t4', 't3.province_id', '=', 't4.id')
                ->join('beneficiary_payment_records as t8', 't5.id', '=', 't8.enrollment_id')
                ->leftJoin('payment_disbursement_details as t11', function ($join) {
                    $join->on('t8.payment_request_id', '=', 't11.payment_request_id');
                    $join->on('t2.id', '=', 't11.school_id');
                })
                ->leftJoin('school_bankinformation as t9', 't11.school_bank_id', '=', 't9.id')
                ->leftJoin('bank_details as b1', 't9.bank_id', '=', 'b1.id')
                ->leftJoin('bank_branches as b2', 't9.branch_name', '=', 'b2.id')
                ->leftJoin('payment_disbursement_status as t12', 't11.payment_status_id', '=', 't12.id')
                ->where(array('t8.payment_request_id' => $payment_request_id))
                ->whereRaw("IF(`t8`.`payment_request_id` = `t11`.`payment_request_id`, `payment_status_id` = 1,1)");
            if (isset($filter_id) && $filter_id != '') {
                if ($filter_id == 1) {
                    $qry->whereNotNull('t11.id');
                }
                if ($filter_id == 2) {
                    $qry->whereNull('t11.id');
                }
            }
             ///04/2022
             if(is_array($running_agency_details) && count($running_agency_details)>0) {                 
                $qry->whereIn('running_agency_id',$running_agency_details);
             }
           
            if(is_array($school_status) &&  count($school_status)>0)//job on 21/04/2022
            {
                $qry->whereIn('t5.beneficiary_schoolstatus_id',$school_status);//job on 21/4/2022;
            }
            if (isset($province_id) && $province_id != '') {
                $qry->where('t4.id', $province_id);
            }
            if (isset($district_id) && $district_id != '') {
                $qry->where('t3.id', $district_id);
            }
            $qry->groupBy('t2.id');
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
    public function getSchoolpaymentschoolSummary(Request $req) // 31st March 2026
    {
        try {

            $payment_request_id = $req->input('payment_request_id');
            $filter_id = $req->input('filter_id');
            $province_id = $req->input('province_id');
            $district_id = $req->input('district_id');
            $start = $req->input('start');
            $limit = $req->input('limit');
            $school_status = $req->input('school_status');//job on 21/4/2022
            $running_agency_details=$req->input('running_agency_id');
            $skip=$start-1;//job on 6/6/2022
         
            if(isset($school_status) && $school_status!=null)
            {
            $school_status=explode(',',$school_status);
            }
            if(isset($running_agency_details) && $running_agency_details!=null)
            {
            $running_agency_details=explode(',',$running_agency_details);
            }
            $qry = DB::table('beneficiary_payresponses_report as t5')
                ->select(DB::raw('t12.name as payment_status, t11.id as 
                    payment_disbursement_id,decrypt(t11.amount_transfered) as 
                    amount_transfered,t11.imbalance_reason,t11.school_bank_id,
                    decrypt(t11.transaction_no) as transaction_no,
                    date(t11.transaction_date) as transaction_date,t11.remarks, 
                    t2.id as school_id,t8.payment_request_id,
                    b1.name as bank_name,b2.name as 
                    branch_name,t9.bank_id,decrypt(t9.account_no) as 
                    account_no,b2.sort_code,count(t5.id) as no_of_beneficiary,

                    t5.total_payable_fees as school_feessummary,
                    t5.total_payable_fees as payable_amount,
                    t2.name as school_name,t3.name as district_name,
                    t4.name as province_name,t13.name as running_agency'))
                ->join('school_information as t2', 't2.id', '=', 't5.school_id')
                ->leftjoin('school_running_agencies as t13','t2.running_agency_id',
                    't13.id')//job on 4/05/2022
                ->join('districts as t3', 't2.district_id', '=', 't3.id')
                ->join('provinces as t4', 't3.province_id', '=', 't4.id')
                ->leftJoin('beneficiary_payment_records as t8', 't5.id', '=', 
                't8.enrollment_id')
                ->leftJoin('payment_disbursement_details as t11', function ($join) {
                    $join->on('t8.payment_request_id', '=', 't11.payment_request_id');
                    $join->on('t2.id', '=', 't11.school_id');
                })
                ->leftJoin('school_bankinformation as t9', 't11.school_bank_id', '=', 't9.id')
                ->leftJoin('bank_details as b1', 't9.bank_id', '=', 'b1.id')
                ->leftJoin('bank_branches as b2', 't9.branch_name', '=', 'b2.id')
                ->leftJoin('payment_disbursement_status as t12', 
                't11.payment_status_id', '=', 't12.id')
                ->where(array('t8.payment_request_id' => $payment_request_id))
                ->whereRaw("IF(`t8`.`payment_request_id` = `t11`.`payment_request_id`, 
                `payment_status_id` = 1,1)");
            if (isset($filter_id) && $filter_id != '') {
                if ($filter_id == 1) {
                    $qry->whereNotNull('t11.id');
                }
                if ($filter_id == 2) {
                    $qry->whereNull('t11.id');
                }
            }
             ///04/2022
             if(is_array($running_agency_details) && count($running_agency_details)>0) {                 
                $qry->whereIn('running_agency_id',$running_agency_details);
             }
           
            if(is_array($school_status) &&  count($school_status)>0)//job on 21/04/2022
            {
                $qry->whereIn('t5.beneficiary_schoolstatus_id',$school_status);//job on 21/4/2022;
            }
            if (isset($province_id) && $province_id != '') {
                $qry->where('t4.id', $province_id);
            }
            if (isset($district_id) && $district_id != '') {
                $qry->where('t3.id', $district_id);
            }
            $qry->groupBy('t2.id');
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

    public function getSchoolpaymentschoolSummary2Cp(Request $req)
    {
        try {

            $payment_request_id = $req->input('payment_request_id');
            $filter_id = $req->input('filter_id');
            $province_id = $req->input('province_id');
            $district_id = $req->input('district_id');
            $start = $req->input('start');
            $limit = $req->input('limit');
            $school_status = $req->input('school_status');//job on 21/4/2022
            $running_agency_details=$req->input('running_agency_id');
         
            if(isset($school_status) && $school_status!=null)
            {
            $school_status=explode(',',$school_status);
            }
            if(isset($running_agency_details) && $running_agency_details!=null)
            {
            $running_agency_details=explode(',',$running_agency_details);
            }
            $qry = DB::table('beneficiary_information as t1')
                ->select(DB::raw('t12.name as payment_status, t11.id as payment_disbursement_id,decrypt(t11.amount_transfered) as amount_transfered,
                              t11.imbalance_reason,t11.school_bank_id,decrypt(t11.transaction_no) as transaction_no,date(t11.transaction_date) as transaction_date,t11.remarks, t2.id as school_id,t8.payment_request_id,
                              b1.name as bank_name,b2.name as branch_name,t9.bank_id,decrypt(t9.account_no) as account_no,b2.sort_code,count(t1.id) as no_of_beneficiary,
                              sum(decrypt(annual_fees)) as school_feessummary,sum(decrypt(annual_fees)) as payable_amount,t2.name as school_name, t3.name as district_name,t4.name as province_name,
                              t13.name as running_agency'))
                ->join('beneficiary_enrollments as t5', 't1.id', '=', 't5.beneficiary_id')
                ->join('school_information as t2', 't2.id', '=', 't5.school_id')
                ->leftjoin('school_running_agencies as t13','t2.running_agency_id','t13.id')//job on 4/05/2022
                ->join('districts as t3', 't2.district_id', '=', 't3.id')
                ->join('provinces as t4', 't3.province_id', '=', 't4.id')
                ->join('beneficiary_payment_records as t8', 't5.id', '=', 't8.enrollment_id')
                ->leftJoin('payment_disbursement_details as t11', function ($join) {
                    $join->on('t8.payment_request_id', '=', 't11.payment_request_id');
                    $join->on('t2.id', '=', 't11.school_id');
                })
                ->leftJoin('school_bankinformation as t9', 't11.school_bank_id', '=', 't9.id')
                ->leftJoin('bank_details as b1', 't9.bank_id', '=', 'b1.id')
                ->leftJoin('bank_branches as b2', 't9.branch_name', '=', 'b2.id')
                ->leftJoin('payment_disbursement_status as t12', 't11.payment_status_id', '=', 't12.id')
                ->where(array('t8.payment_request_id' => $payment_request_id))
                ->whereRaw("IF(`t8`.`payment_request_id` = `t11`.`payment_request_id`, `payment_status_id` = 1,1)");
            if (isset($filter_id) && $filter_id != '') {
                if ($filter_id == 1) {
                    $qry->whereNotNull('t11.id');
                }
                if ($filter_id == 2) {
                    $qry->whereNull('t11.id');
                }
            }
             ///04/2022
             if(is_array($running_agency_details) && count($running_agency_details)>0)
             {
                 
                 $qry->whereIn('running_agency_id',$running_agency_details);
             }
           
            if(is_array($school_status) &&  count($school_status)>0)//job on 21/04/2022
            {
                $qry->whereIn('t5.beneficiary_schoolstatus_id',$school_status);//job on 21/4/2022;
            }
            if (isset($province_id) && $province_id != '') {
                $qry->where('t4.id', $province_id);
            }
            if (isset($district_id) && $district_id != '') {
                $qry->where('t3.id', $district_id);
            }
            $qry->groupBy('t2.id');
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

    public function getSchoolpaymentschoolSummary2(Request $req)
    {
        try {
            $payment_request_id = $req->input('payment_request_id');
            $filter_id = $req->input('filter_id');
            $province_id = $req->input('province_id');
            $district_id = $req->input('district_id');
            $start = $req->input('start');
            $limit = $req->input('limit');
            $qry = DB::table('beneficiary_information as t1')
                ->select(DB::raw('t12.name as payment_status, t11.id as payment_disbursement_id,decrypt(t11.amount_transfered) as amount_transfered,
                              t11.imbalance_reason,t11.school_bank_id,decrypt(t11.transaction_no) as transaction_no,date(t11.transaction_date) as transaction_date,t11.remarks, t2.id as school_id,t8.payment_request_id,
                              b1.name as bank_name,b2.name as branch_name,t9.bank_id,decrypt(t9.account_no) as account_no,b2.sort_code,count(t1.id) as no_of_beneficiary,
                              sum(decrypt(annual_fees)) as school_feessummary,sum(decrypt(annual_fees)) as payable_amount,t2.name as school_name, t3.name as district_name,t4.name as province_name'))
                ->join('beneficiary_enrollments as t5', 't1.id', '=', 't5.beneficiary_id')
                ->join('school_information as t2', 't2.id', '=', 't5.school_id')
                ->join('districts as t3', 't2.district_id', '=', 't3.id')
                ->join('provinces as t4', 't3.province_id', '=', 't4.id')
                ->join('beneficiary_payment_records as t8', 't5.id', '=', 't8.enrollment_id')
                ->leftJoin('payment_disbursement_details as t11', function ($join) {
                    $join->on('t8.payment_request_id', '=', 't11.payment_request_id');
                    $join->on('t2.id', '=', 't11.school_id');
                })
                ->leftJoin('school_bankinformation as t9', 't11.school_bank_id', '=', 't9.id')
                ->leftJoin('bank_details as b1', 't9.bank_id', '=', 'b1.id')
                ->leftJoin('bank_branches as b2', 't9.branch_name', '=', 'b2.id')
                ->leftJoin('payment_disbursement_status as t12', 't11.payment_status_id', '=', 't12.id')
                ->where(array('t8.payment_request_id' => $payment_request_id))
                ->whereRaw("IF(`t8`.`payment_request_id` = `t11`.`payment_request_id`, `payment_status_id` = 1,1)");
            if (isset($filter_id) && $filter_id != '') {
                if ($filter_id == 1) {
                    $qry->whereNotNull('t11.id');
                }
                if ($filter_id == 2) {
                    $qry->whereNull('t11.id');
                }
            }
            if (isset($province_id) && $province_id != '') {
                $qry->where('t4.id', $province_id);
            }
            if (isset($district_id) && $district_id != '') {
                $qry->where('t3.id', $district_id);
            }
            $qry->groupBy('t2.id');
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

    public function func_submitforpaymentDisbursement(Request $req)
    {
        $payment_request_id = $req->payment_request_id;
        $status_id = $req->status_id;
        $data = array('status_id' => $status_id);
        $table_name = 'payment_request_details';
        if (validateisNumeric($payment_request_id)) {
            $where = array('id' => $payment_request_id);
            $records = DB::table($table_name)
                ->where(array('id' => $payment_request_id))
                ->where('approved_by', '<>', '')
                ->whereNotNull('approved_by')
                ->count();
            if ($records > 0) {
                $data['updated_at'] = Carbon::now();
                $data['updated_by'] = $this->user_id;
                $previous_data = getPreviousRecords($table_name, $where);
                $success = updateRecord($table_name, $previous_data, $where, $data, $this->user_id);
                //save the record in the trasntitional table
                $previous_status_id = $previous_data[0]['status_id'];
                $table_name = 'payment_verificationtransitionsubmissions';
                //save the details in the transitional table
                $data = array(
                    'payment_request_id' => $payment_request_id,
                    'previous_status_id' => $previous_status_id,
                    'next_status_id' => $status_id,
                    'released_on' => Carbon::now(),
                    'released_by' => $this->user_id
                );
                $data['created_at'] = Carbon::now();
                $data['created_by'] = $this->user_id;

                $insert_resp = insertRecord($table_name, $data, $this->user_id);


            } else {
                $resp = array('success' => false, 'message' => 'Add the Payment enrollment details to payment request!!');
                json_output($resp);
                exit();

            }

        }
        if ($success) {
            $resp = array('success' => true, 'message' => 'Payment Request Details submitted successfully.');

        } else {
            $resp = array('success' => false, 'message' => 'Problem encountered while saving data. Try again later!!');

        }
        json_output($resp);

    }
     //Job on 23/10/2022
    public function savePaymentGrantListLimit(Request $req)
    {
        try {
            $user_id = \Auth::user()->id;
            $postdata = file_get_contents("php://input");
            $data = json_decode($postdata);
            if (is_array($data)) {
                $data = json_decode($postdata, true);
            } else {
                $data = array();
                $data[] = json_decode($postdata, true);
            }
            foreach ($data as $key => $value) {
                $id = $value['id'];
                $grant_list_limit = $value['grant_list_limit'];
                $where = array(
                    'id' => $id
                );
                if($grant_list_limit>15000)
                {
                    $res = array(
                        'success' => false,
                        'message' => 'Limit Can not exceed 15000!!'
                    );
                    return $res;
                }
                $data = array(
                    'grant_list_limit' => $grant_list_limit,
                   
                    'updated_by' => $user_id
                );
                $prev_data = getPreviousRecords('payments_grant_list_limit', $where);
                updateRecord('payments_grant_list_limit', $prev_data, $where, $data, $user_id);
            }
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
     //Job on 23/10/2022


     public function getPaymentGrantListLimit(Request $req)
     {
         try {
             $qry = DB::table('payments_grant_list_limit as t1')
                 ->leftJoin('users as t2', 't1.created_by', '=', 't2.id')
                 ->leftJoin('users as t3', 't1.updated_by', '=', 't3.id')
                 ->select(DB::raw("t1.*,CONCAT_WS(' ',decrypt(t2.first_name),decrypt(t2.last_name)) as creator_names,
                                   CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as updator_names"));
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
 
    public function getPaymentGrantLists(Request $req)
    {
        $payment_year=$req->input('payment_year');
        $refreshdata=$req->input('refreshdata');
        $payment_verification_status=$req->input('payment_verification_status');
        $payment_request_id=$req->input('selected_payment_requests');
      
        //$payment_verification_status=7;
        $results= DB::table('payments_grant_list_batches as t1')
            ->where('t1.payment_year',$payment_year)
            ->where('stage',$payment_verification_status)
            ->leftjoin('payment_request_details as t3','t1.payment_request_id','t3.id')
            ->selectraw('t1.id,t1.description,total_records,t1.status_id,payment_request_id,t3.payment_ref_no');
           

            if(isset($payment_request_id))
          
            {

              $results->where('payment_request_id',$payment_request_id);
            }
           
            $results=$results->get()->toArray();
            $res = array();
          
        try{

            $number_of_records_main=DB::table('beneficiary_information as t1')
            ->join('beneficiary_enrollments as t2', 't1.id', '=', 't2.beneficiary_id')
            ->join('beneficiary_payment_records as t8', 't2.id', '=', 't8.enrollment_id')
            ->join('payment_request_details as t12','t12.id','t8.payment_request_id')
            ->orderBy('t1.beneficiary_id','ASC')
            ->where('t12.status_id',$payment_verification_status);

            if(isset($payment_request_id))
            {
                $number_of_records_main->where('t12.id',$payment_request_id);
            }
            $number_of_records_main=$number_of_records_main->where('t12.payment_year', $payment_year)->count();

            $batches_total_records=Db::table('payments_grant_list_batches')->where('payment_year',$payment_year)->sum('total_records');
            if($refreshdata==1 || ($number_of_records_main>$batches_total_records))
            //if($refreshdata==1 )
            {
                $batches_ids=Db::table('payments_grant_list_batches')->selectraw('id');
                //->where(['payment_year'=>$payment_year,"stage"=>$payment_verification_status]);
              
                if(isset($payment_request_id))
          
                {
                  $batches_ids->where('payment_request_id',$payment_request_id);
                }
                $batches_ids= $batches_ids->get()->toArray();
                $ids_of_batches=array();
                foreach($batches_ids as $batch_data)
                {
                    $ids_of_batches[]=$batch_data->id;
                }
             
                //$resultset=Db::table('payment_grant_list_log')->selectraw('ben_id')->where(['payment_year'=>$payment_year,"stage"=>$payment_verification_status])->get()->toArray();
                $resultset=Db::table('payment_grant_list_log')->selectraw('ben_id')->whereIn('grantlist_id',$ids_of_batches)->get()->toArray();
                $to_delete=[];
                foreach($resultset as $set)
                {
                    $to_delete[]=$set->ben_id;
                }
                //dd($ids_of_batches);
                //$result=Db::table('payments_grant_list_batches')->where(['payment_year'=>$payment_year,"stage"=>$payment_verification_status])->delete();
                if(count($ids_of_batches)>0)
                {
                Db::table('payments_grant_list_batches')->whereIn("id", $ids_of_batches)->delete();
            
                }
               // DB::table("payment_grant_list_log")->where(['payment_year'=>$payment_year,"stage"=>$payment_verification_status])->delete();
                
                $number_of_records=count($to_delete);
                $limit=800;
                if($number_of_records>$limit && $number_of_records>0)
                {
                    $total_loop=ceil($number_of_records/$limit);
                    $start_index=0;
                    $end_index=$limit;
                    for($i=1;$i<=$total_loop;$i++)
                    {
                        $results_to_insert=array();
                        foreach($to_delete as $key=>$result)
                        {
                            if($key>=$start_index && $key<=$end_index)
                            {
                                $results_to_insert[]=$result;
                            }
                        } 
                   DB::table("payments_beneficiaries_grant")->where(['payment_year'=>$payment_year])->whereIn('beneficiary_id',$results_to_insert)->delete();
                   DB::table("payment_grant_list_log")->where(['payment_year'=>$payment_year,"stage"=>$payment_verification_status])
                   ->whereIn('ben_id',$results_to_insert)->delete();

                    
                    $results_to_insert=array();
                        if($i!=$total_loop-1){
                            $start_index=$end_index+1;
                            $end_index=$start_index+$limit;
                            }else{
                                $start_index=$end_index+1;
                                $end_index=($number_of_records-1);
                        }
                    }
                }else{
                    if(count($to_delete)>0){
                   $complete_del= DB::table("payments_beneficiaries_grant")
                   ->where(['payment_year'=>$payment_year])->whereIn('beneficiary_id',$to_delete);
                   DB::table("payment_grant_list_log")->where(['payment_year'=>$payment_year,"stage"=>$payment_verification_status])
                   ->whereIn('ben_id',$to_delete)->delete();
                    }
                }
                $results=array(); 
               }

      
            if(count($results)>0)
            {
               
                return  array(
                    'success' => true,
                    'message' => returnMessage($results),
                    'results' => $results
                );
            }else{
                //get num of records in grant list
               $payment_requests= Db::table('payment_request_details')->where('payment_year',$payment_year)
               ->where('status_id',$payment_verification_status);
               if(isset($payment_request_id))
               {
                $payment_requests->where('id',$payment_request_id);
               }
               $payment_requests= $payment_requests->selectraw('id')->get()->toArray();

               $grant_list_limit=Db::table('payments_grant_list_limit')->where('id',1)->value('grant_list_limit');
               $table_data=array();
                $limit=$grant_list_limit;
                
                  foreach($payment_requests as $pay_req_id)
                  {
                 

                  

                    $number_of_records_per_request=DB::table('beneficiary_information as t1')
                    ->join('beneficiary_enrollments as t2', 't1.id', '=', 't2.beneficiary_id')
                    ->join('beneficiary_payment_records as t8', 't2.id', '=', 't8.enrollment_id')
                    ->join('payment_request_details as t12','t12.id','t8.payment_request_id')
                    ->orderBy('t1.beneficiary_id','ASC')
                    ->where('t12.status_id',$payment_verification_status)
                    ->where('t12.id',$pay_req_id->id)
                    ->where('t12.payment_year', $payment_year)->count();
                    

                    if( $number_of_records_per_request>$limit &&  $number_of_records_per_request>0)
                    {
                        $total_loop=ceil($number_of_records_per_request/$limit);
                        $start_index=0;
                        $end_index=$limit;
                       
                        $table_data[]=array(
                            "description"=>"Grant List 1",
                            "start"=>$start_index,
                            "stop"=>$end_index,
                            "stage"=>$payment_verification_status,
                            "total_records"=>$limit,
                            "payment_year"=>$payment_year,
                            "payment_request_id"=>$pay_req_id->id,
                            "created_by"=>$this->user_id,
                            "created_at"=>carbon::now()
                        );
    
                      
                        for($i=1;$i<$total_loop;$i++)
                        {
                            if($i!=$total_loop-1){
                                $start_index=$end_index+1;
                                $end_index=$end_index+$limit;
                                }else{
                                    $start_index=$end_index+1;
                                    $end_index=($number_of_records_per_request);
                            }
                               $grant_list_number=$i+1;
                                $table_data[]=array(
                                    "description"=>"Grant List  $grant_list_number",
                                    "start"=>$start_index-1,
                                    "stop"=>$end_index,
                                    "stage"=>$payment_verification_status,
                                    "total_records"=>$end_index-($start_index-1),
                                    "payment_year"=>$payment_year,
                                    "payment_request_id"=>$pay_req_id->id,
                                    "created_by"=>$this->user_id,
                                    "created_at"=>carbon::now()
                                );
                               
                        }
                    }else{
                        if($number_of_records_per_request>0)
                        {
                            $table_data[]=array(
                                "description"=>"Grant List 1",
                                "start"=>0,
                                "stop"=>$number_of_records_main-1,
                                "total_records"=>$number_of_records_per_request,
                                "stage"=>$payment_verification_status,
                                "payment_year"=>$payment_year,
                                "payment_request_id"=>$pay_req_id->id,
                                "created_by"=>$this->user_id,
                                "created_at"=>carbon::now()
                            );
    
                        }
                    }
                    
                  }
                
              
                
              
               
              
                if($number_of_records_main<1)
                {
                    return  array(
                        'success' => false,
                        'message' => returnMessage($table_data),
                        'results' => $table_data
                    );

                }
               $insert=Db::table('payments_grant_list_batches')->insert($table_data);
              //dd($table_data);
                $results= DB::table('payments_grant_list_batches as t1')
                ->where('t1.payment_year',$payment_year)
                ->leftjoin('payment_request_details as t3','t1.payment_request_id','t3.id')
                ->selectraw('t1.id,t1.description,total_records,t1.status_id,payment_request_id,t3.payment_ref_no');

              
                if(isset($payment_request_id))
          
                {
                    $results->where('payment_request_id',$payment_request_id);
                   
                }
              
                $results=$results->get()->toArray();

                return  array(
                    'success' => true,
                    'message' => returnMessage($results),
                    'results' => $results
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
    public function paymentGrantlist(Request $request)
    {
       
            try{
            
            $payment_grant_list_id= $request->input('batch_id');
            $payment_verification_status=$request->input('payment_verification_status');
            $time= date('H:i:s d-M-Y');
            return Excel::download(new PaymentGrantList($payment_grant_list_id,$payment_verification_status,$this->user_id) ,"PaymentGrant$time.xlsx");
            
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

    
    public function func_submitforpaymentApproval(Request $req)
    {
        $payment_request_id = $req->input('payment_request_id');
        $status_id = $req->input('status_id');
        $remark = $req->input('request_comment');
        $data = array(
            'status_id' => $status_id,
            'request_comment' => $remark
        );
        $table_name = 'payment_request_details';
        $success = false;
        try {
            $duplicates_exist = checkEnrollmentDuplicates($payment_request_id);
            if ($duplicates_exist == true) {
                $res = array(
                    'success' => false,
                    'message' => 'Duplicates found, please process duplicates before attempting this action!!'
                );
                return response()->json($res);
            }
            if (validateisNumeric($payment_request_id)) {
                $where = array('id' => $payment_request_id);
                $records = DB::table('beneficiary_payment_records')
                    ->where(array('payment_request_id' => $payment_request_id))
                    ->count();
                if ($records > 0) {
                    $data['updated_at'] = Carbon::now();
                    $data['updated_by'] = $this->user_id;
                    $previous_data = getPreviousRecords($table_name, $where);
                    $success = updateRecord($table_name, $previous_data, $where, $data, $this->user_id);
                    //save the record in the transitional table
                    $previous_status_id = $previous_data[0]['status_id'];
                    $table_name = 'payment_verificationtransitionsubmissions';
                    //save the details in the transitional table
                    $data = array(
                        'payment_request_id' => $payment_request_id,
                        'previous_status_id' => $previous_status_id,
                        'next_status_id' => $status_id,
                        'released_on' => Carbon::now(),
                        'released_by' => $this->user_id
                    );
                    $data['created_at'] = Carbon::now();
                    $data['created_by'] = $this->user_id;
                    insertRecord($table_name, $data, $this->user_id);
                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'Add Payment enrollment details to payment request!!'
                    );
                    return response()->json($res);
                }
            }
            if ($success) {
                $res = array(
                    'success' => true,
                    'message' => 'Payment Request Details submitted successfully for Payment Approval Process!!'
                );
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'Problem encountered while saving data. Try again later!!'
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

     public function savePaymentdisbursementdetails(Request $req)
    {

        $transaction_no = $req->input('transaction_no');
        $transaction_date = $req->input('transaction_date');
        //$fees = $req->input('school_feessummary');
        $fees = $req->input('payable_amount');
        $amount_transfered = $req->input('amount_transfered');
        $imbalance_reason = $req->input('imbalance_reason');
        $school_id = $req->input('school_id');
        $payment_request_id = $req->input('payment_request_id');
        $payment_disbursement_id = $req->input('payment_disbursement_id');
        $school_bank_id = $req->input('school_bank_id');
        $table_data = array(
            //'transaction_no' => aes_encrypt($transaction_no),  //job on 5/5/2022
           // 'transaction_date' => formatDate($transaction_date),
            'amount_transfered' => aes_encrypt($amount_transfered),
            'school_id' => $school_id,
            'payment_request_id' => $payment_request_id,
            'school_bank_id' => $school_bank_id
        );
        //job on 5/5/2022
        if(isset($transaction_date) && $transaction_date!=null)
        {
            $table_data['transaction_date']=formatDate($transaction_date);
        }
        if(isset($transaction_no) && $transaction_no!=null)
        {
            $table_data['transaction_no']=aes_encrypt($transaction_no);
        }
        //end change
        $table_name = 'payment_disbursement_details';
        if ($fees != $amount_transfered) {
            if (trim($imbalance_reason) != '') {
                $table_data['imbalance_reason'] = $imbalance_reason;
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'Please enter the reason why the school fees and amount paid are not equal!!'
                );
                return response()->json($res);
            }
        } else {
            $table_data['imbalance_reason'] = '';
        }
        if (validateisNumeric($payment_disbursement_id)) {
            $where = array(
                'id' => $payment_disbursement_id
            );
            $table_data['updated_at'] = Carbon::now();
            $table_data['updated_by'] = $this->user_id;
            $table_data['payment_status_id'] = 1;
            $previous_data = getPreviousRecords($table_name, $where);
            updateRecord($table_name, $previous_data, $where, $table_data, $this->user_id);
        } else {
            $table_data['created_at'] = Carbon::now();
            $table_data['created_by'] = $this->user_id;
            $table_data['payment_status_id'] = 1;
            $payment_disbursement_id = insertRecordReturnId($table_name, $table_data, $this->user_id);
        }
        if (validateisNumeric($payment_disbursement_id)) {
            $this->checkForPaymentRequestArchival($payment_request_id);
            $resp = array(
                'success' => true,
                'message' => 'Payment Disbursement information saved successfully'
            );
        } else {
            $resp = array(
                'success' => false,
                'message' => 'Problem encountered while saving data. Try again later!!'
            );
        }
        json_output($resp);
    }

     private function returnArrayFromStringArray($string_array)
    {

        $string_array=substr(trim($string_array), 0, -1);
        $final_array=explode(',' ,substr($string_array,1));
        return $final_array;
    }

    //job fees knockout
     public function getFeesKnockoutDetails(Request $req)
    {
        $res=array();
        try
        {
            //prepared_by
            $qry=Db::table('payment_fees_knockout as t1')
            //->join('batch_info as t2','t1.batch_id','=','t2.id')
            ->join('users as t4','t4.id','t1.created_by')
            ->leftjoin('users as t5','t5.id','t1.updated_by')
            ->join('payment_request_details as t3','t3.id','=','t1.payment_request_id')
            ->selectraw("t1.id,t1.description,t1.payment_year,t1.batch_id as batchNos_str,payment_ref_no,t1.status,
            CONCAT_WS(' ',decrypt(t4.first_name),decrypt(t4.last_name)) as prepared_by,
            CONCAT_WS(' ',decrypt(t5.first_name),decrypt(t5.last_name)) as reversal_by");
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


     public function reverseFeeKnockOut(Request $req)
    {
        $res=array();
        try{
            $payment_knockout_id=$req->input('payment_knockout_id');
            $payment_request_id=Db::table('payment_fees_knockout')->where('id',$payment_knockout_id)->value('payment_request_id');
            $payment_request_status=Db::table('payment_request_details')->where('id',$payment_request_id)->value('status_id');
            $was_there_an_update=0;
            if($payment_request_status!=4)
            {
                $res = array(
                    'success' => false,
                    'message' => "Sorry,Fees KnockOut Reversal for  this Payment Request is Prohibited",
                );
                return response()->json($res);
            }
             \Artisan::call("command:feesknockoutreversal",[
                "user_id"=>$this->user_id, 
                "fees_knockout_id"=> $payment_knockout_id,
            ]); 
           
            $res = array(
                'success' => true,
                'message' => "Fees KnockOut Reversal successfull",
                //'results' => $results
            ); 
            return response()->json($res);
           $qry= db::table('payment_fees_knockout_logs as t1')
           ->join('payment_fees_knockout as t2','t2.id','t1.fees_knockout_request_id')
           ->join('beneficiary_enrollments as t3','t3.id','t1.enrollment_id')
            ->selectraw('enrollment_id,term_fees,term,decrypt(t3.annual_fees) as annual_fees')
            ->where('fees_knockout_request_id',$payment_knockout_id);
            $results = $qry->get()->toArray();
            foreach($results as $result)
            {
                $term_to_reverse=$result->term;
         
                $term_to_update="";
                if($term_to_reverse==1)
                {
                    $term_to_update="term1_fees";

                }else if($term_to_reverse==2)
                {
                    $term_to_update="term2_fees";
                }else{
                    $term_to_update="term3_fees"; 
                }
                $annual_fees=$result->annual_fees+$result->term_fees;
                $qry=db::table('beneficiary_enrollments as t1')
                ->selectraw('t1.term1_fees',)
                ->where('id',$result->enrollment_id);
                $result=DB::table('beneficiary_enrollments as t1')
                ->where('id',$result->enrollment_id)
            ->update(["t1.$term_to_update"=>DB::raw('encryptVal('. $result->term_fees.')'),
            't1.annual_fees'=>DB::raw('encryptVal('.$annual_fees.')'),
            "updated_at"=>Carbon::now(),"updated_by"=>$this->user_id,]);
            $was_there_an_update=1;
            }
            
            if($was_there_an_update==1){
            $result=DB::table('payment_fees_knockout as t1')
            ->where('id',$payment_knockout_id)
        ->update(["t1.status"=>0,
        "updated_at"=>Carbon::now(),"updated_by"=>$this->user_id]);
        }
            $res = array(
                'success' => true,
                'message' => "Fees KnockOut Reversal successfull",
                //'results' => $results
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

     public function GetFeesknockOutReport(Request $req)
    {
        $payment_knockout_id = $req->input('payment_knockout_id');
        return Excel::download(new KnockOutReport($payment_knockout_id),'knockedOutGirls.xlsx');
    }

    //job on 20/4/2022
    public function getfeesknockOutStatus(Request $req)
    {
        $fees_knockout_id=$req->input('fees_knockout_id');
        $status=Db::table('payment_fees_knockout')->where('id',$fees_knockout_id)->value('status');
        if($status==1)//active
        {
            $res = array(
                'success' => true,
                'message' => "Knock Out Successfull",
                "message3"=>1,
            );

        }
        if($status==5)//still processing
        {
            $res = array(
                'success' => true,
                'message' => "Knock Out Successfull",
                //"message3"=1,
            );

        }
        if($status==null)//no record to knockout
        {
            $res = array(
                'success' => true,
                'message' => "KnockOut Not Successfull",
                "message3"=>6,
            );

        }


        return response()->json($res);
        
    }

     //05/05/2022
    public function processFeesDeduction(Request $req)
    {
        try{
            
        $payment_year=$req->input('payment_year');
        $payment_request_id=$req->input('payment_request_id');
        $description=$req->input('description');
        $batch_id=$req->input('batch_id');
        $term = $req->input('term_id');
        $school_status=$req->input('school_status_id');
        $gce_external= $req->input('gce_external');
        $knockout_exam_fees= $req->input('knockout_exam_fees');
        $term=$this->returnArrayFromStringArray($term);
        $batch_id=$this->returnArrayFromStringArray($batch_id);
        $payment_request_status=Db::table('payment_request_details')->where('id',$payment_request_id)->value('status_id');
            if($payment_request_status!=4)
            {
                $res = array(
                    'success' => false,
                    'message' => "Sorry,Fees KnockOut for  this Payment Request is Prohibited",
                );
                return response()->json($res);
            }   
            $batches=Db::table('batch_info')->whereIn('id',$batch_id)->selectraw('batch_no')->get()->toArray();
            $batches = convertStdClassObjToArray($batches);
            $new_batches=[];
            foreach($batches as $the_batch)
            {
                $new_batches[]=$the_batch['batch_no'];
            }
            $new_batches=implode(",",$new_batches);
            $qry= DB::table('beneficiary_enrollments as t1')
                ->join('beneficiary_payment_records as t2','t2.enrollment_id','=','t1.id')
                ->join('beneficiary_information as t3','t1.beneficiary_id','=','t3.id')
                ->where('t2.payment_request_id',$payment_request_id)
                ->selectraw('t1.id,decrypt(t1.term1_fees) as t1_fees,decrypt(t1.term2_fees) as t2_fees,decrypt(t1.term3_fees) as  t3_fees,
                decrypt(t1.annual_fees) as annual_fees,t3.batch_id as batch_identity,t1.batch_id')
                ->join('payment_verificationbatch as t4','t4.id','=','t1.batch_id')
                ->where('t2.payment_request_id',$payment_request_id)
                ->whereIn('t3.batch_id',$batch_id);
            $total_result_count=$qry->count();
            $total_results=$total_result_count*count($term);
                if(isset($school_status) && $school_status!="")
                {
                    $school_status=$this->returnArrayFromStringArray($school_status);
                    $qry->whereIn('beneficiary_schoolstatus_id',$school_status);
                }
                if($gce_external==true)
                {
                    $qry->where('is_gce_external_candidate',1);
                }
                
                $results = $qry->get()->toArray();
                if(count($results)==0)
                {
                    $res = array(
                        'success' => false,
                        'message' => "No Beneficiaries meet criteria for knockout",
                    );
                    return response()->json($res);
                }         
                $table_data=array(
                    "payment_request_id"=>$payment_request_id,
                    "payment_year"=>$payment_year,
                    "description"=>$description,
                    "batch_id"=>$new_batches,
                    //"term"=>$term_id,
                    "created_at"=>Carbon::now(),
                    "created_by"=>$this->user_id,
                    'status'=>6,
                );
                $fees_knockout_id=insertRecordReturnId('payment_fees_knockout', $table_data, $this->user_id);
            //\ob_start();
            \Artisan::call("command:feesknockout",[
                "payment_request_id"=>$payment_request_id,
                "payment_year"=>$payment_year,
                "description"=>$description,
                "batch_id"=>$batch_id,
                "school_status"=>$school_status,
                "term"=> $term,
                "gce_external"=>$gce_external,
                "user_id"=>$this->user_id, 
                "fees_knockout_id"=>$fees_knockout_id,
                "knockout_exam_fees"=>$knockout_exam_fees
            ]);  
        
            $was_knock_out_done_count=DB::table('payment_fees_knockout_logs')->where('fees_knockout_request_id',$fees_knockout_id)->count();
            if($was_knock_out_done_count>0)
            {
                $result=DB::table('payment_fees_knockout')->where('id',$fees_knockout_id)->update(['status'=>1]);
                $res = array(
                            'success' => true,
                            'message' => "Knockout Successfully",
                        );
                return response()->json($res);
                 }else{
                DB::table('payment_fees_knockout')
                ->where('id', $fees_knockout_id)
                ->delete();
                $res = array(
                    'success' => false,
                    'message' => "There was a problem with the fee knockout",   
                );
                return response()->json($res);
                }
        
            // $output = \ob_get_clean();
            // if($output==6 )
            // {
            
            //     $res = array(
            //         'success' => false,
            //         'message' => "There was a problem with the fee knockout",
            //         //'results' => $results
            //     );
            //     return response()->json($res);
            // }
       
            return response()->json($res);
            $was_knock_out_done=0;
            foreach($results as $result)
            {
                foreach($term as $term_id){
                switch($term_id)
            {
                case 1:
                    
                    if($result->t1_fees>0){
                        
                    $annual_fees=$result->annual_fees-$result->t1_fees;
                    $table_data=array(
                        "enrollment_id"=>$result->id,
                        "fees_knockout_request_id"=>$fees_knockout_id,
                        "term"=>1,
                        "term_fees"=>$result->t1_fees,
                        "created_at"=>Carbon::now(),
                        "created_by"=>$this->user_id
                    );
                    insertRecord('payment_fees_knockout_logs', $table_data, $this->user_id);
                    $result_updated=DB::table('beneficiary_enrollments as t1')
                            ->where('id',$result->id)
                        ->update(['t1.term1_fees'=>DB::raw('encryptVal(0)'),
                        't1.annual_fees'=>DB::raw('encryptVal('.$annual_fees.')'),
                        "updated_at"=>Carbon::now(),"updated_by"=>$this->user_id
                    ]); 
                    $was_knock_out_done=1;
                }   
                    break;
                case 2:
                    if($result->t2_fees>0){
                    $annual_fees=$result->annual_fees-$result->t2_fees;
                    $table_data=array(
                        "enrollment_id"=>$result->id,
                        "fees_knockout_request_id"=>$fees_knockout_id,
                        "term"=>2,
                        "term_fees"=>$result->t2_fees,
                        "created_at"=>Carbon::now(),
                        "created_by"=>$this->user_id
                    );
                insertRecord('payment_fees_knockout_logs', $table_data, $this->user_id);
                $result_updated=DB::table('beneficiary_enrollments as t1')
                        ->where('id',$result->id)
                    ->update(['t1.term2_fees'=>DB::raw('encryptVal(0)'),
                    't1.annual_fees'=>DB::raw('encryptVal('.$annual_fees.')'),
                    "updated_at"=>Carbon::now(),"updated_by"=>$this->user_id]); 
                    $was_knock_out_done=1;
                }
                
                    break;
                case 3:
            
                    if($result->t3_fees>0){

                    $annual_fees=$result->annual_fees-$result->t3_fees;
                    $table_data=array(
                        "enrollment_id"=>$result->id,
                        "fees_knockout_request_id"=>$fees_knockout_id,
                        "term"=>3,
                        "term_fees"=>$result->t3_fees,
                        "created_at"=>Carbon::now(),
                        "created_by"=>$this->user_id
                    );
                insertRecord('payment_fees_knockout_logs', $table_data, $this->user_id);
                $result_updated=DB::table('beneficiary_enrollments as t1')
                        ->where('id',$result->id)
                    ->update(['t1.term3_fees'=>DB::raw('encryptVal(0)'),
                    't1.annual_fees'=>DB::raw('encryptVal('.$annual_fees.')'),
                    "updated_at"=>Carbon::now(),"updated_by"=>$this->user_id
                    ]);
                    $was_knock_out_done=1;
                }
                
                    break;
                default:
                    break;

            }
            }
            }
            if($was_knock_out_done==0)
            {
                DB::table('payment_fees_knockout')
                ->where('id', $fees_knockout_id)
                ->delete();
                $res = array(
                    'success' => false,
                    'message' => "There was a problem with the fee knockout",
                );
            }
            if($was_knock_out_done==1)
            {
                $res = array(
                    'success' => true,
                    'message' => "Knockout Successfully",
                );
            }
            
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



      //job on 09/05/2022
    public function getFeesKnockoutBeneficiaryDetails(Request $req)
    {
        $res=array();
        try{
            $payment_knockout_id=$req->input('record_id');
            $terms_of_request= db::table('payment_fees_knockout_logs as t1')
            ->selectraw('distinct(term)')
            ->where('fees_knockout_request_id',$payment_knockout_id)
            ->get();
            $was_exam_fees_knocked_out=Db::table('payment_fees_knockout_logs')
            ->where('fees_knockout_request_id',$payment_knockout_id)
            ->where('term',10)->count();
            if($was_exam_fees_knocked_out>0)
            {
                $was_exam_fees_knocked_out=true;
            }else{
                $was_exam_fees_knocked_out=false;
            }
            $terms=[1,2,3,10];
            $terms_query=['SELECT enrollment_id,t3.beneficiary_id as beneficiary_no,decrypt(t2.annual_fees) as deducted_annual_fees'];
            if($was_exam_fees_knocked_out==false)  
            {
                $terms_query[]="decrypt(t2.exam_fees) as exam_fees";
            }  
            $terms_of_request_array=[];
                foreach($terms_of_request as $termobject)
                {
                    if($termobject->term!=10){
                    $terms_of_request_array[]=$termobject->term;
                    }
                }
            
                foreach($terms as $real_term)
                {
                if(!in_array($real_term,$terms_of_request_array))
                {
                    if($real_term==1)
                    {
                        $terms_query[]="decrypt(t2.term1_fees) as deducted_term1_fees";
                    }
                    if($real_term==2)
                    {
                        $terms_query[]="decrypt(t2.term2_fees) as deducted_term2_fees";
                    }
                    if($real_term==3)
                    {
                        $terms_query[]="decrypt(t2.term3_fees) as deducted_term3_fees";
                    }
                   
                    
                }
                }
            foreach($terms_of_request as $termobject)
            {
                if(!in_array($termobject->term,$terms))
                {
                    
                    if($termobject->term==1)
                    {
                        $terms_query[]="decrypt(t2.term1_fees) as deducted_term1_fees";
                    }
                    if($termobject->term==2)
                    {
                        $terms_query[]="decrypt(t2.term2_fees) as deducted_term2_fees";
                    }
                    if($termobject->term==3)
                    {
                        $terms_query[]="decrypt(t2.term3_fees) as deducted_term3_fees";
                    }
                   

                }
                
                if(in_array($termobject->term,$terms))
                {
                    if($termobject->term==1)
                    {
                       //$terms_query[]="CASE when term=1 then term_fees end as term1_fees";
                       $terms_query[]="(SELECT sum(term_fees) FROM  payment_fees_knockout_logs WHERE term='1' AND enrollment_id=t1.enrollment_id AND fees_knockout_request_id=t1.fees_knockout_request_id ) AS deducted_term1_fees";
                    }
                    if($termobject->term==2)
                    {
                       
                        $terms_query[]="(SELECT sum(term_fees) FROM  payment_fees_knockout_logs WHERE term='2' AND enrollment_id=t1.enrollment_id AND fees_knockout_request_id=t1.fees_knockout_request_id ) AS deducted_term2_fees";
                    }
                    if($termobject->term==3)
                    {
                        $terms_query[]="(SELECT sum(term_fees) FROM  payment_fees_knockout_logs WHERE term='3' AND enrollment_id=t1.enrollment_id AND fees_knockout_request_id=t1.fees_knockout_request_id ) AS deducted_term3_fees";
                    }
                    //new for exam fees
                    if($termobject->term==10)
                    {
                        $terms_query[]="(SELECT sum(term_fees) FROM  payment_fees_knockout_logs WHERE term='10' AND enrollment_id=t1.enrollment_id AND fees_knockout_request_id=t1.fees_knockout_request_id ) AS exam_fees";
                    }


                }
            }
            
            $terms_query= implode(",",$terms_query);
            $terms_query.= " FROM payment_fees_knockout_logs as t1 INNER JOIN beneficiary_enrollments AS t2 ON t2.id=t1.enrollment_id 
            INNER JOIN beneficiary_information as t3 ON t3.id=t2.beneficiary_id
             where fees_knockout_request_id=$payment_knockout_id group by enrollment_id";
    
            $results=db::select($terms_query);
         
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



    //job on 09/05/2022
    public function getCurrentFeesKnockoutBeneficiaryDetails(Request $req)
    {
        $res=array();
        try{  
            $payment_knockout_id=$req->input('record_id');
            $qry=Db::table('payment_fees_knockout_logs as t1')
           ->join('beneficiary_enrollments as t2','t2.id','t1.enrollment_id')
           ->join('beneficiary_information as t3','t3.id','t2.beneficiary_id')
            ->selectraw('t3.beneficiary_id  as beneficiary_no,decrypt(term1_fees) as initial_term1_fees,decrypt(term2_fees) as initial_term2_fees,decrypt(term3_fees) as initial_term3_fees,decrypt(exam_fees) as exam_fees,decrypt(annual_fees)  as initial_annual_fees')
            ->where('fees_knockout_request_id',$payment_knockout_id)
            ->groupby('enrollment_id');
            $results=$qry->get();
          
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

    //variances in payment
    public function getpaymentvarinacesPaymentRequests(Request $req)
    {

        try {
            $status_id = [7];
            $term = $req->input('term');
            $year = $req->input('year');
            $qry = DB::table('payment_request_details as t1')
                ->select(DB::raw("count(beneficiary_id) as no_of_beneficiaries, sum(decrypt(annual_fees)) as total_fees, t1.*, t1.id as payment_request_id,
                              st.name as approval_status_name,t2.id as prepared_by_id, CONCAT_WS(' ',decrypt(t2.first_name),decrypt(t2.last_name)) as prepared_by,
                              t1.check_comment,t3.id as checked_by_id, CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as checked_byname,
                              COUNT(DISTINCT(t6.school_id)) as no_of_schools,t4.id as approved_by_id, CONCAT_WS(' ',decrypt(t4.first_name),decrypt(t4.last_name)) as approved_byname
                              "))
                ->leftJoin('school_transfer_statuses as st', 't1.approval_status', '=', 'st.id')
                ->leftJoin('users as t2', 't1.prepared_by', '=', 't2.id')
                ->leftJoin('users as t3', 't1.checked_by', '=', 't3.id')
                ->leftJoin('users as t4', 't1.approved_by', '=', 't4.id')
                ->leftJoin('beneficiary_payment_records as t5', 't1.id', '=', 't5.payment_request_id')
                ->leftJoin('beneficiary_enrollments as t6', 't5.enrollment_id', '=', 't6.id')
                //->leftJoin('reconciliation_suspense_account as t7', 't7.payment_request_id', '=', 't1.id')
                ->where(array('status_id' => $status_id));
            if (isset($year) && $year != '') {
                $qry->where('t1.payment_year', $year);
            }
            if (isset($term) && $term != '') {
                //$qry->where('t1.term_id', $term);
            }
            $qry->groupBy('t1.id')
                ->orderBy('t1.id', 'DESC');
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

     public function getBeneficiaryPaymentVariances(Request $req)
    {
        try{
            $payment_request_id= $req->input('record_id');
            $term =$req->input('term');
            $fee_disbursed_term="";
            if($term=="1")
            {
                $fee_disbursed_term = "decrypt(t2.term1_fees)";
            }
            if($term=="2")
            {
                $fee_disbursed_term = "decrypt(t2.term2_fees)";
            }
            if($term=="3")
            {
                $fee_disbursed_term = "decrypt(t2.term3_fees)";
            }
            

             $qry="SELECT (t1.beneficiary_id) AS beneficiary_no,SUM(t5.receipt_amount) AS receipted_fees,
            CONCAT_WS(' ',decrypt(first_name),decrypt(last_name)) as beneficiary_name ,
            $fee_disbursed_term AS disbursed_fees,
            (decrypt(t2.term1_fees)-SUM(t5.receipt_amount)) AS varied_amount

            FROM beneficiary_information AS t1 INNER JOIN  beneficiary_enrollments AS t2 ON t1.id=t2.beneficiary_id 
            INNER JOIN beneficiary_payment_records AS t3 ON t3.enrollment_id=t2.id 
            INNER JOIN  payments_receipting_details AS t4 ON t2.id=t4.enrollment_id 
            INNER JOIN beneficiary_receipting_details AS t5 ON t5.payment_receipt_id=t4.id
            WHERE payment_request_id=$payment_request_id AND t4.term_id=$term
            GROUP BY t1.beneficiary_id  
            HAVING   disbursed_fees!= SUM(t5.receipt_amount)";
            $results=db::select($qry);
            $res = array(
                'success' => true,
                'message' => returnMessage($results),
                'results' => $results
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


 public function generatePaymentVariancesReport(Request $req)
    {
        $payment_request_id= $req->input('record_id');
        $term =$req->input('term');
        return Excel::download(new PaymentVariancesReport($payment_request_id,$term),'paymentvarianvesreport.xlsx');
    }
    //end

    public function savePaymentdisbursementdetails2(Request $req)
    {

        $transaction_no = $req->input('transaction_no');
        $transaction_date = $req->input('transaction_date');
        //$fees = $req->input('school_feessummary');
        $fees = $req->input('payable_amount');
        $amount_transfered = $req->input('amount_transfered');
        $imbalance_reason = $req->input('imbalance_reason');
        $school_id = $req->input('school_id');
        $payment_request_id = $req->input('payment_request_id');
        $payment_disbursement_id = $req->input('payment_disbursement_id');
        $school_bank_id = $req->input('school_bank_id');

        $table_data = array(
            'transaction_no' => aes_encrypt($transaction_no),
            'transaction_date' => formatDate($transaction_date),
            'amount_transfered' => aes_encrypt($amount_transfered),
            'school_id' => $school_id,
            'payment_request_id' => $payment_request_id,
            'school_bank_id' => $school_bank_id
        );
        $table_name = 'payment_disbursement_details';
        if ($fees != $amount_transfered) {
            if (trim($imbalance_reason) != '') {
                $table_data['imbalance_reason'] = $imbalance_reason;
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'Please enter the reason why the school fees and amount paid are not equal!!'
                );
                return response()->json($res);
            }
        } else {
            $table_data['imbalance_reason'] = '';
        }
        if (validateisNumeric($payment_disbursement_id)) {
            $where = array(
                'id' => $payment_disbursement_id
            );
            $table_data['updated_at'] = Carbon::now();
            $table_data['updated_by'] = $this->user_id;
            $table_data['payment_status_id'] = 1;
            $previous_data = getPreviousRecords($table_name, $where);
            updateRecord($table_name, $previous_data, $where, $table_data, $this->user_id);
        } else {
            $table_data['created_at'] = Carbon::now();
            $table_data['created_by'] = $this->user_id;
            $table_data['payment_status_id'] = 1;
            $payment_disbursement_id = insertRecordReturnId($table_name, $table_data, $this->user_id);
        }
        if (validateisNumeric($payment_disbursement_id)) {
            $this->checkForPaymentRequestArchival($payment_request_id);
            $resp = array(
                'success' => true,
                'message' => 'Payment Disbursement information saved successfully'
            );
        } else {
            $resp = array(
                'success' => false,
                'message' => 'Problem encountered while saving data. Try again later!!'
            );
        }
        json_output($resp);
    }

    public function checkForPaymentRequestArchival($request_id)
    {
        $checker = DB::table('beneficiary_payment_records as t1')
            ->join('beneficiary_enrollments as t2', 't1.enrollment_id', '=', 't2.id')
            ->leftJoin('payment_disbursement_details as t3', function ($join) {
                $join->on('t1.payment_request_id', '=', 't3.payment_request_id')
                    ->on('t2.school_id', '=', 't3.school_id');
            })
            ->where('t1.payment_request_id', $request_id)
            ->whereNull('t3.id')
            ->count();
        if ($checker == 0) {
            //archive
            DB::table('payment_request_details')
                ->where('id', $request_id)
                ->update(array('status_id' => 7));
            $table_name = 'payment_verificationtransitionsubmissions';
            $data = array(
                'payment_request_id' => $request_id,
                'previous_status_id' => 6,
                'next_status_id' => 7,
                'released_on' => Carbon::now(),
                'released_by' => $this->user_id,
                'created_at' => Carbon::now(),
                'created_by' => $this->user_id
            );
            DB::table($table_name)->insert($data);
        }
    }

    public function getBenEnrollmentstatuses()
    {
        //get the details


    }

    public function getPaymentreceiptingDetails1(Request $req)
    {
        $status_id = $req->input('status_id');
        $qry = DB::table('payment_receiptingbatch as t1')
            ->select(DB::raw('t7.first_name as added_by,t8.first_name as updated_by, t1.*, t1.id as payment_receipts_id,
                              t2.name as school_name, t3.name as school_term,t4.name as status,t6.name as school_district,
                              count(t6.id) as no_of_records '))
            ->join('school_information as t2', 't1.school_id', '=', 't2.id')
            ->join('school_terms as t3', 't1.term_id', '=', 't3.id')
            ->join('payment_verification_statuses as t4', 't1.status_id', '=', 't4.id')
            ->join('districts as t6', 't2.district_id', '=', 't6.id')
            ->leftJoin('payments_receipting_details as t5', function ($join) {
                $join->on('t5.payment_receipts_id', '=', 't1.id');
            })
            ->join('users as t7', 't1.created_by', '=', 't7.id')
            ->leftJoin('users as t8', 't1.updated_by', '=', 't8.id')
            ->where(array('status_id' => $status_id))
            ->havingRaw('count(t1.id)  > 0')
            ->groupBy('t1.id')
            ->get();
        $results = convertStdClassObjToArray($qry);
        $results = decryptArray($results);
        $res = array(
            'results' => $results
        );
        json_output($res);
    }

    public function getPaymentreceiptingDetails(Request $req)
    {
        $status_id = $req->input('status_id');
        $year = $req->input('year');
        $term = $req->input('term');
        $district_id = $req->input('district_id');
        $user_id = \Auth::user()->id;
        try {
            $groups = getUserGroups($user_id);
            $superUserID = getSuperUserGroupId();
            $districts = getUserDistricts($user_id);
            $qry = DB::table('payment_receiptingbatch as t1')
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                ->join('school_terms as t3', 't1.term_id', '=', 't3.id')
                ->join('districts as t6', 't2.district_id', '=', 't6.id')
                ->join('users as t7', 't1.created_by', '=', 't7.id')
                ->leftJoin('payments_receipting_details as t8', 't1.id', '=', 't8.payment_receipts_id')
                ->leftJoin('beneficiary_receipting_details as t9', 't8.id', '=', 't9.payment_receipt_id')
                ->select(DB::raw('t6.name as school_district, t2.name as school_name, t3.name as school_term, decrypt(t7.first_name) as added_by,
                                  COUNT(DISTINCT(t9.payment_receipt_id)) as receipted_beneficiaries,COUNT(DISTINCT(t8.enrollment_id)) as no_of_beneficiaries, t1.id, t1.payment_year, t1.file_no, t1.school_id, t1.term_id, t1.id as payment_receipts_id'))
                ->where('t1.status_id', $status_id);
            $qry = in_array($superUserID, $groups) == 1 ? $qry->whereRaw("1=1") : $qry->whereIn('t6.id', $districts);
            if (isset($year) && $year != '') {
                $qry->where('t1.payment_year', $year);
            }
            if (isset($term) && $term != '') {
                $qry->where('t1.term_id', $term);
            }
            if (isset($district_id) && $district_id != '') {
                $qry->where('t2.district_id', $district_id);
            }
            $qry->groupBy('t1.id');
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

    public function getPaymentReceiptingValidationDetails(Request $req)
    {
        $status_id = $req->input('status_id');
        $year = $req->input('year');
        $term = $req->input('term');
        $district_id = $req->input('district_id');
        $user_id = \Auth::user()->id;
        try {
            $groups = getUserGroups($user_id);
            $superUserID = getSuperUserGroupId();
            $districts = getUserDistricts($user_id);
            $qry = DB::table('payment_receiptingbatch as t1')
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                ->join('school_terms as t3', 't1.term_id', '=', 't3.id')
                ->join('districts as t6', 't2.district_id', '=', 't6.id')
                ->leftJoin('payments_receipting_details as t8', 't1.id', '=', 't8.payment_receipts_id')
                ->leftJoin('beneficiary_receipting_details as t9', 't8.id', '=', 't9.payment_receipt_id')
                ->leftJoin('users as t10', 't1.validation_author', '=', 't10.id')
                ->select(DB::raw('t6.name as school_district, t2.name as school_name, t3.name as school_term,decrypt(t10.first_name) as submitted_by,
                                  COUNT(DISTINCT(t9.payment_receipt_id)) as receipted_beneficiaries,COUNT(DISTINCT(t8.enrollment_id)) as no_of_beneficiaries, t1.id, t1.payment_year, t1.file_no, t1.school_id, t1.term_id, t1.id as payment_receipts_id'))
                ->where('t1.status_id', $status_id);
            $qry = in_array($superUserID, $groups) == 1 ? $qry->whereRaw("1=1") : $qry->whereIn('t6.id', $districts);
            if (isset($year) && $year != '') {
                $qry->where('t1.payment_year', $year);
            }
            if (isset($term) && $term != '') {
                $qry->where('t1.term_id', $term);
            }
            if (isset($district_id) && $district_id != '') {
                $qry->where('t2.district_id', $district_id);
            }
            $qry->groupBy('t1.id');
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

    public function deletePaymentReceiptBatch(Request $req)
    {
        $batch_id = $req->input('id');
        $where = array(
            'id' => $batch_id
        );
        $res = array();
        DB::transaction(function () use (&$res, $batch_id, $where) {
            try {
                $del_ids = DB::table('payments_receipting_details')
                    ->select('id')
                    ->where('payment_receipts_id', $batch_id)
                    ->get();
                $del_ids = convertStdClassObjToArray($del_ids);
                $del_ids = convertAssArrayToSimpleArray($del_ids, 'id');
                DB::table('beneficiary_receipting_details')
                    ->whereIn('payment_receipt_id', $del_ids)
                    ->delete();
                DB::table('payments_receipting_details')
                    ->where('payment_receipts_id', $batch_id)
                    ->delete();
                $table_name = 'payment_receiptingbatch';
                $prev_data = getPreviousRecords($table_name, $where);
                $res = deleteRecord($table_name, $prev_data, $where, $this->user_id);
            } catch (\Exception $e) {
                $res = array(
                    'success' => false,
                    'message' => $e->getMessage()
                );
            }
        }, 5);
        return response()->json($res);
    }

    public function getSchoolben_paymentDisbursementsStr(Request $req)
    {
        $payment_receipts_id = $req->input('payment_receipts_id');
        try {
            $qry = DB::table('payments_receipting_details as t1')
                ->join('beneficiary_enrollments as t2', 't1.enrollment_id', '=', 't2.id')
                ->join('beneficiary_information as t3', 't2.beneficiary_id', '=', 't3.id')
                ->leftJoin('beneficiary_receipting_details as t4', 't1.id', '=', 't4.payment_receipt_id')
                ->select(DB::raw('t1.id,t3.folder_id,t3.beneficiary_id as beneficiary_no,decrypt(t3.first_name) as first_name,decrypt(t3.last_name) as last_name,
                                  t2.school_fees,t4.id as receipted,COUNT(t4.id) as no_of_receipts,SUM(t4.receipt_amount) as totalreceipt_amount'))
                ->where('t1.payment_receipts_id', $payment_receipts_id)
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

    public function saveBeneficiaryReceiptDetails(Request $req)
    {
        $id = $req->input('id');
        $payment_receipt_id = $req->input('payment_receipt_id');
        $receipt_no = $req->input('receipt_no');
        $receipt_date = $req->input('receipt_date');
        $receipt_amount = $req->input('receipt_amount');
        $remarks = $req->input('remarks');
        $parent_folder_id = $req->input('folder_id');
        $user_id = $this->user_id;
        $dms_id = $this->dms_id;
        $table_name = 'beneficiary_receipting_details';
        $table_data = array(
            'payment_receipt_id' => $payment_receipt_id,
            'receipt_no' => $receipt_no,
            'receipt_date' => converter1($receipt_date),
            'receipt_amount' => $receipt_amount,
            'remarks' => $remarks
        );
        try {
            if (isset($id) && $id != '') {
                $where = array(
                    'id' => $id
                );
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $prev_data = getPreviousRecords($table_name, $where);
                updateRecord($table_name, $prev_data, $where, $table_data, $user_id);
            } else {
                $where = array(
                    'receipt_no' => $receipt_no,
                    'receipt_date' => converter1($receipt_date)
                );
                $checker = DB::table($table_name)
                    ->where($where)
                    ->count();
                if ($checker > 0) {
                    $res = array(
                        'success' => false,
                        'message' => 'This receipt exists, Please edit the details!!'
                    );
                    return response()->json($res);
                }
                //+++++++
                $folder_id = getSubModuleFolderIDWithCreate($parent_folder_id, 27, $dms_id);
                $document_id = '';
                if ($_FILES['localfile']['size'] > 0) {
                    $document_id = addDocument('Receipt' . $receipt_no, $remarks, 'localfile', $folder_id, $versioncomment = '', $is_arrayreturn = 0);
                }
                //+++++++
                $table_data['created_at'] = Carbon::now();
                $table_data['created_by'] = $user_id;
                $table_data['document_id'] = $document_id;
                insertRecord($table_name, $table_data, $user_id);
            }
            $res = array(
                'success' => true,
                'message' => 'Receipt details saved successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getBeneficiariesForReceiptAdditions(Request $req)
    {
        $school_id = $req->input('school_id');
        $year = $req->input('year');
        $term = $req->input('term');
        $where = array(
            't1.school_id' => $school_id,
            't1.year_of_enrollment' => $year,
            't1.term_id' => $term
        );
        try {
            $qry = DB::table('beneficiary_enrollments as t1')
                ->join('beneficiary_information as t2', 't1.beneficiary_id', '=', 't2.id')
                ->join('beneficiary_payment_records as t3', 't1.id', '=', 't3.enrollment_id')
                ->leftJoin('payments_receipting_details as t4', 't1.id', '=', 't4.enrollment_id')
                ->leftJoin('payment_receiptingbatch as t5', 't4.payment_receipts_id', '=', 't5.id')
                ->select(DB::raw('t1.id as enrollment_id,t2.id,t4.id as receipted,t5.file_no,t2.beneficiary_id,decrypt(t2.first_name) as first_name,decrypt(t2.last_name) as last_name'))
                ->where($where)
                ->groupBy('t2.id');
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

    public function getBeneficiaryPaymentdetails($enrollment_id)
    {
        $qry = DB::table('payment_disbursement_details as t1')
            ->select('t1.id as payment_disbursement_id', 't1.school_id', 't4.id as enrollment_id', 't4.beneficiary_id', 't4.school_fees')
            ->join('payment_request_details as t2', 't1.payment_request_id', '=', 't2.id')
            ->join('beneficiary_payment_records as t3', 't2.id', '=', 't1.payment_request_id')
            ->join('beneficiary_enrollments as t4', 't3.enrollment_id', '=', 't4.id')
            ->where(array('t4.id' => $enrollment_id))
            ->first();
        return $qry;

    }

    public function addBeneficiaryreceiptingdetails(Request $req)
    {
        $post_data = $req->all();
        $data = $post_data['data'];
        $term_id = $req->term_id;
        $payment_receipts_id = $req->payment_receipts_id;
        $payment_year = $req->payment_year;

        $records = explode(',', $data);

        foreach ($records as $enrollment_id) {
            //
            //get the records
            $payment_details = $this->getBeneficiaryPaymentdetails($enrollment_id);
            if ($payment_details) {

                $table_data = array('payment_receipts_id' => $payment_receipts_id,
                    'term_id' => $term_id,
                    'payment_year' => $payment_year,
                    'payment_disbursement_id' => $payment_details->payment_disbursement_id,
                    'enrollment_id' => $enrollment_id,
                    'beneficiary_id' => $payment_details->beneficiary_id
                );
                $qry = DB::table('payments_receipting_details')
                    ->where($table_data)
                    ->count();
                if ($qry == 0) {
                    //inser the records
                    $table_data['receipt_amount'] = $payment_details->school_fees;
                    $table_data['created_at'] = Carbon::now();
                    $table_data['created_by'] = $this->user_id;
                    insertRecordReturnId('payments_receipting_details', $table_data, $this->user_id);

                }
            }

        }
        $resp = array('success' => true, 'message' => 'Beneficiary Details Added Successfully');

        json_output($resp);

    }

    public function saveReceiptdetails(Request $req)
    {
        //the details
        $receipt_no = $req->receipt_no;
        $receipt_date = formatDate($req->receipt_date);
        $receipt_amount = $req->receipt_amount;
        $remarks = $req->receipt_amount;

        $enrollment_id = $req->enrollment_id;
        $payment_disbursement_id = $req->payment_disbursement_id;
        $payment_receipts_id = $req->payment_receipts_id;
        $folder_id = $req->folder_id;
        $beneficiary_id = $req->beneficiary_id;

        $table_name = 'payments_receipting_details';
        $table_data = array('receipt_no' => $receipt_no,
            'remarks' => $remarks,
            'beneficiary_id' => $beneficiary_id,
            'payment_receipts_id' => $payment_receipts_id,
            'payment_disbursement_id' => $payment_disbursement_id,
            'enrollment_id' => $enrollment_id,
            'receipt_amount' => $receipt_amount,
            'receipt_date' => $receipt_date);
        //update the details
        $where_data = array('receipt_no' => $receipt_no, 'receipt_date' => $receipt_date);
        $sql_query = DB::table($table_name)
            ->where($where_data)
            ->count();
        if ($sql_query == 0) {
            //upload documents

            //
            $document_id = '';
            if ($_FILES['localfile']['size'] == 0) {
                $document_data = addDocument('Receipt' . $receipt_no, $remarks, 'localfile', $folder_id, $versioncomment = '', $is_arrayreturn = null);
                $document_id = $document_data['document_id'];

            }
            $table_data['created_at'] = Carbon::now();
            $table_data['created_by'] = $this->user_id;
            $table_data['document_id'] = $document_id;
            insertRecordReturnId('payments_receipting_details', $table_data, $this->user_id);
            $resp = array('success' => true, 'message' => 'Receipt details saved successfully!!');

        } else {

            $resp = array('success' => false, 'message' => 'Receipt Already Entered!!');

        }

        json_output($resp);

    }

    //save receipting details
    public function saveBeneficiary_receiptingInfoStr(Request $req)
    {
        //save the data
        $payment_receipts_id = $req->payment_receipts_id;

        $postdata = file_get_contents("php://input");
        //th response array
        $response = array();

        $data = json_decode($postdata);
        // the post data is array thus handle as array data
        if (is_array($data)) {

            $data = json_decode($postdata, true);


        } else {

            $data = array();
            $data[] = json_decode($postdata, true);

        }

        foreach ($data as $key => $value) {
            //values in place

            $id = $value['id'];

            $receipt_no = $value['receipt_no'];
            $remarks = $value['remarks'];
            $receipt_amount = $value['receipt_amount'];
            $receipt_date = $value['receipt_date'];
            //receipt_no  'remarks', 'receipt_amount', receipt_date
            $table_name = 'payments_receipting_details';
            $table_data = array('receipt_no' => $receipt_no, 'remarks' => $remarks, 'receipt_amount' => $receipt_amount, 'receipt_date' => $receipt_date);
            //update the details
            $where_data = array('id' => $id);
            $sql_query = DB::table($table_name)
                ->where($where_data)
                ->count();
            if ($sql_query > 0) {
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $this->user_id;
                //print_r($table_data);
                $previous_data = getPreviousRecords($table_name, $where_data);
                $success = updateRecord($table_name, $previous_data, $where_data, $table_data, $this->user_id);

            }

        }
        $resp = array('success' => true, 'message' => 'Receipting Details updated successfully');
    }

    public function deleteReceiptDetails(Request $req)
    {
        $receipt_id = $req->receipt_id;
        $table_name = 'payments_receipting_details';
        $where_data = array('id' => $receipt_id);
        $qry = DB::table($table_name)
            ->where($where_data)
            ->count();
        if ($qry > 0) {
            $previous_data = getPreviousRecords($table_name, $where_data);
            $res = deleteRecord($table_name, $previous_data, $where_data, $this->user_id);

        } else {
            $res = array(
                'success' => false,
                'message' => 'We hit a snag!!'
            );
        }
        json_output($res);
    }

    //KIP
    public function getSchoolsForPaymentsLog(Request $req)
    {
        $district_id = $req->input('district_id');
        $province_id = $req->input('province_id');
        $year = $req->input('year');
        $term = $req->input('term');
        $start = $req->input('start');
        $limit = $req->input('limit');
        $filter = $req->input('filter');
        $whereClauses = array();
        $filter_string = '';
        if (isset($filter)) {
            $filters = json_decode($filter);
            if ($filters != NULL) {
                foreach ($filters as $filter) {
                    switch ($filter->property) {
                        case 'name' :
                            $whereClauses[] = "t1.name like '%" . ($filter->value) . "%'";
                            break;
                        case 'code' :
                            $whereClauses[] = "t1.code like '%" . ($filter->value) . "%'";
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
            $qry = DB::table('school_information as t1')
                ->join('payment_verificationbatch as t2', 't1.id', '=', 't2.school_id')
                ->join('districts as t3', 't1.district_id', '=', 't3.id')
                ->join('provinces as t4', 't3.province_id', '=', 't4.id')
                ->leftJoin('school_types as t5', 't1.school_type_id', '=', 't5.id')
                ->select(DB::raw("t1.*,t3.name as district_name,t4.name as province_name,t5.name as school_type,
                                  CONCAT_WS(' ',t1.code,t1.name) as codename"));
            if (isset($district_id) && $district_id != '') {
                $qry->where('t1.district_id', $district_id);
            }
            if (isset($province_id) && $province_id != '') {
                $qry->where('t3.province_id', $province_id);
            }
            if (isset($year) && $year != '') {
                $qry->where('t2.year_of_enrollment', $year);
            }
            if (isset($term) && $term != '') {
                $qry->where('t2.term_id', $term);
            }
            $qry->groupBy('t1.id');
            if ($filter_string != '') {
                $qry->whereRAW($filter_string);
            }
            $total = count($qry->get());
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
                'success' => true,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getChecklistGenerationsLog(Request $req)
    {
        $school_id = $req->input('school_id');
        try {
            $qry = DB::table('payment_checklists_track as t1')
                ->join('users as t2', 't1.created_by', '=', 't2.id')
                ->select(DB::raw('t1.*,CASE WHEN decrypt(t2.first_name) IS NULL THEN first_name ELSE decrypt(t2.first_name) END as first_name, CASE WHEN decrypt(t2.last_name) IS NULL THEN last_name ELSE decrypt(t2.last_name) END as last_name'))
                ->where('t1.school_id', $school_id);
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

    public function getPayChecklistsGenerationHistory(Request $req)
    {
        try {
            $year = $req->input('year');
            $term = $req->input('term');
            $entered = $req->input('entered');
            $start = $req->input('start');
            $limit = $req->input('limit');
            $filter = $req->input('filter');
            $whereClauses = array();
            $filter_string = '';
            if (isset($filter)) {
                $filters = json_decode($filter);
                if ($filters != NULL) {
                    foreach ($filters as $filter) {
                        switch ($filter->property) {
                            case 'district_name' :
                                $whereClauses[] = "t3.district_id  ='" . ($filter->value) . "'";
                                break;
                            case 'school_name' :
                                $whereClauses[] = "t1.school_id ='" . ($filter->value) . "'";
                                break;
                            case 'checklist_number' :
                                $whereClauses[] = "t1.checklist_number like '%" . ($filter->value) . "%'";
                                break;
                            case 'created_at' :
                                $whereClauses[] = "date(t1.created_at) = '" . (converter1($filter->value)) . "'";
                                break;
                            case 'created_by' :
                                $whereClauses[] = "t1.created_by = '" . $filter->value . "'";
                                break;
                        }
                    }
                    $whereClauses = array_filter($whereClauses);
                }
                if (!empty($whereClauses)) {
                    $filter_string = implode(' AND ', $whereClauses);
                }
            }
            $qry = DB::table('payment_checklists_track as t1')
                ->join('users as t2', 't1.created_by', '=', 't2.id')
                ->join('school_information as t3', 't1.school_id', '=', 't3.id')
                ->join('districts as t4', 't3.district_id', '=', 't4.id')
                ->leftJoin('payment_verificationbatch as t5', 't5.checklist_form_id', '=', 't1.id')
                ->select(DB::raw('t3.name as school_name,t4.name as district_name,t1.*,decrypt(t2.first_name) as first_name,
                                  decrypt(t2.last_name) as last_name,t5.id as entered'));
            if (isset($year) && $year != '') {
                $qry->where('t1.year', $year);
            }
            if (isset($term) && $term != '') {
                $qry->where('t1.term', $term);
            }
            if (validateisNumeric($entered)) {
                if ($entered == 1) {
                    $qry->whereNotNull('t5.id');
                } else {
                    $qry->whereNull('t5.id');
                }
            }
            if ($filter_string != '') {
                $qry->whereRAW($filter_string);
            }
            $total = $qry->count();
            $qry->orderBy('t3.id')
                ->offset($start)
                ->limit($limit);
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'totalCount' => $total,
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

    public function getBeneficiariesOnChecklist(Request $req)
    {
        $track_id = $req->input('track_id');
        try {
            $qry = DB::table('payment_checklists_track_details as t1')
                ->join('beneficiary_information as t2', 't1.girl_id', '=', 't2.id')
                ->select(DB::raw('t1.*,t2.beneficiary_id,CASE WHEN decrypt(t2.first_name) IS NULL THEN first_name ELSE decrypt(t2.first_name) END as first_name, CASE WHEN decrypt(t2.last_name) IS NULL THEN last_name ELSE decrypt(t2.last_name) END as last_name'))
                ->where('t1.track_id', $track_id)
                ->groupBy('t1.girl_id');
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

    public function getPaymentsDataEntryLog(Request $req)
    {
        $school_id = $req->input('school_id');
        try {
            $qry = DB::table('payment_verificationbatch as t1')
                ->join('beneficiary_enrollments as t2', 't1.id', '=', 't2.batch_id')
                ->join('payment_verification_statuses as t3', 't1.status_id', '=', 't3.id')
                ->leftJoin('users as t4', 't1.created_by', '=', 't4.id')
                ->select(DB::raw('CONCAT_WS(" ", decrypt(t4.first_name), decrypt(t4.last_name)) as full_name,t3.name as status_name,t1.*,count(t2.beneficiary_id) as total_entered,SUM(IF(t2.is_validated = 1, 1, 0)) AS total_validated'))
                ->where('t1.school_id', $school_id)
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

    public function getValidatedBeneficiaries(Request $req)
    {
        $batch_id = $req->input('batch_id');
        try {
            $qry = DB::table('beneficiary_enrollments as t1')
                ->join('beneficiary_information as t2', 't1.beneficiary_id', '=', 't2.id')
                ->select(DB::raw('t1.school_fees,t2.beneficiary_id,decrypt(t2.first_name) as first_name, decrypt(t2.last_name) as last_name'))
                ->where('t1.is_validated', 1)
                ->where('t1.batch_id', $batch_id);
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

    public function getUnvalidatedBeneficiaries(Request $req)
    {
        $batch_id = $req->input('batch_id');
        try {
            $qry = DB::table('beneficiary_enrollments as t1')
                ->join('beneficiary_information as t2', 't1.beneficiary_id', '=', 't2.id')
                ->select(DB::raw('t1.id,t1.school_fees,t2.beneficiary_id,decrypt(t2.first_name) as first_name, decrypt(t2.last_name) as last_name'))
                ->where('t1.is_validated', '<>', 1)
                ->where('t1.batch_id', $batch_id);
            $results = $qry->get();
            foreach ($results as $key => $result) {
                $result->error_log = $this->getBeneficiaryEnrollmentErrors($result->id);
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

    public function getBeneficiaryEnrollmentErrors($enrollment_id)
    {
        $error_string = '';
        try {
            $qry = DB::table('beneficiary_payvalidation_rulesdetails as t1')
                ->join('payment_validation_rules as t2', 't1.rule_id', '=', 't2.id')
                ->select('t2.name')
                ->where('t1.enrollment_id', $enrollment_id);
            $results = $qry->get();
            foreach ($results as $result) {
                $error_string .= '<li style="color: #ff6666;">' . $result->name . '</li>';
            }
        } catch (\Exception $e) {
            $error_string = $e->getMessage();
        }
        return $error_string;
    }

    public function getPaymentsConsolidationLog(Request $req)
    {
        $school_id = $req->input('school_id');
        try {
            $qry = DB::table('beneficiary_enrollments as t1')
                ->join('beneficiary_payment_records as t2', 't1.id', '=', 't2.enrollment_id')
                ->join('payment_request_details as t3', 't3.id', '=', 't2.payment_request_id')
                ->join('payment_verification_statuses as t4', 't3.status_id', '=', 't4.id')
                ->leftJoin('users as t5', 't3.prepared_by', '=', 't5.id')
                ->leftJoin('payment_disbursement_details as t6', function ($join) use ($school_id) {
                    $join->on('t6.payment_request_id', '=', 't3.id')
                        ->on('t6.school_id', '=', DB::raw($school_id));
                })
                ->select(DB::raw('t3.id,t3.payment_ref_no,t3.payment_year,t3.term_id,t3.prepared_on,sum(t1.school_fees) as school_feessummary,
                                  date(t6.transaction_date) as transaction_date,t6.imbalance_reason,t6.school_bank_id,CONCAT_WS(" ", decrypt(t5.first_name), decrypt(t5.last_name)) as full_name,
                                  decrypt(t6.transaction_no) as transaction_no,decrypt(t6.amount_transfered) as amount_transfered,t4.name as status_name,count(t1.beneficiary_id) as total_entered'))
                ->where('t1.school_id', $school_id)
                ->groupBy('t3.id');
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

    public function getPaymentsDisbursementLog(Request $req)
    {
        $school_id = $req->input('school_id');
        try {
            $qry = DB::table('beneficiary_enrollments as t1')
                ->join('beneficiary_payment_records as t2', 't1.id', '=', 't2.enrollment_id')
                ->join('payment_request_details as t3', 't3.id', '=', 't2.payment_request_id')
                ->join('payment_disbursement_details as t6', function ($join) use ($school_id) {
                    $join->on('t3.id', '=', 't6.payment_request_id')
                        ->on('t6.school_id', '=', DB::raw($school_id));
                })
                ->leftJoin('payment_disbursement_status as t4', 't6.payment_status_id', '=', 't4.id')
                ->leftJoin('users as t5', 't3.prepared_by', '=', 't5.id')
                ->select(DB::raw('t6.id,t3.id as request_id,decrypt(t6.transaction_no) as transaction_no,t6.transaction_date,decrypt(t6.amount_transfered) as amount_transfered,
                                  t3.payment_ref_no,t3.payment_year,t3.term_id,t3.prepared_on,sum(t1.school_fees) as school_fees_total,
                                  CONCAT_WS(" ", decrypt(t5.first_name), decrypt(t5.last_name)) as full_name,
                                  t4.name as status_name,count(t1.beneficiary_id) as total_entered'))
                ->where('t1.school_id', $school_id)
                ->groupBy('t1.school_id', 't1.year_of_enrollment', 't1.term_id');
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

    public function getBeneficiariesOnPaymentRequest(Request $req)
    {
        $request_id = $req->input('request_id');
        $school_id = $req->input('school_id');
        $year = $req->input('year');
        $term = $req->input('term');
        try {
            $qry = DB::table('beneficiary_payment_records as t1')
                ->join('beneficiary_enrollments as t2', function ($join) use ($school_id, $year, $term) {
                    $join->on('t2.id', '=', 't1.enrollment_id')
                        ->on('t2.school_id', '=', DB::raw($school_id))
                        ->on('t2.year_of_enrollment', '=', DB::raw($year))
                        ->on('t2.term_id', '=', DB::raw($term));
                })
                ->join('beneficiary_information as t3', 't2.beneficiary_id', '=', 't3.id')
                ->leftJoin('payments_receipting_details as t4', 't2.id', '=', 't4.enrollment_id')
                ->select(DB::raw('t2.school_fees,t3.beneficiary_id,decrypt(t3.first_name) as first_name, decrypt(t3.last_name) as last_name,
                                  t4.id as receipt_id'))
                ->where('t1.payment_request_id', $request_id);
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

    public function getBeneficiariesOnPaymentDisbursement(Request $req)
    {
        $request_id = $req->input('request_id');
        $school_id = $req->input('school_id');
        $year = $req->input('year');
        $term = $req->input('term');
        try {
            $qry = DB::table('beneficiary_payment_records as t1')
                ->join('beneficiary_enrollments as t2', function ($join) use ($school_id, $year, $term) {
                    $join->on('t2.id', '=', 't1.enrollment_id')
                        ->on('t2.school_id', '=', DB::raw($school_id))
                        ->on('t2.year_of_enrollment', '=', DB::raw($year))
                        ->on('t2.term_id', '=', DB::raw($term));
                })
                ->join('beneficiary_information as t3', 't2.beneficiary_id', '=', 't3.id')
                ->select(DB::raw('t2.school_fees,
                                  t3.beneficiary_id,decrypt(t3.first_name) as first_name, decrypt(t3.last_name) as last_name'))
                ->where('t1.payment_request_id', $request_id);
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

    //reconciliation
    public function getPaymentreconcilliationStr(Request $req)
    {
        $year_of_enrollment = $req->input('year_of_enrollment');
        $term_id = $req->input('term_id');
        $request_id = $req->input('request_batch_id');
        $start_year = 2017;
        $end_year = date('Y');
        $data = array();
        $qry = DB::table('school_terms')->get();
        if (validateisNumeric($term_id)) {
            $qry = DB::table('school_terms')->where(array('id' => $term_id))->get();
        }
        if (validateisNumeric($year_of_enrollment)) {
            $start_year = $year_of_enrollment;
            $end_year = $year_of_enrollment;
        }
        for ($year_loop = $start_year; $year_loop <= $end_year; $year_loop++) {
            if ($qry->count() > 0) {
                foreach ($qry as $row) {
                    $term_id = $row->id;
                    $term_name = $row->name;
                    $year_of_enrollment = $year_loop;

                    $validated_ben = getBenefiaryenrollmentcounter($year_of_enrollment, $term_id, $request_id, 1);
                    $verified_ben = getBenefiaryenrollmentcounter($year_of_enrollment, $term_id, $request_id, 0);
                    $disbursement_rpt = getPaymentdisbursements($year_of_enrollment, $term_id, $request_id);
                    $receipt_details = getBeneficiaryReceiptCounter($year_of_enrollment, $term_id, $request_id);
                    $waiting_details = getBeneficiaryPaymentRecords($year_of_enrollment, $term_id, $request_id);
                    $suspense_amount = getSuspenseAmounts($year_of_enrollment, $term_id, $request_id);

                    $reconcilliation_status = 0;
                    if ($disbursement_rpt->total_fees >= $validated_ben->total_fees && $validated_ben->total_fees != 0) {
                        $reconcilliation_status = 1;
                    }

                    $data[] = array(
                        'year_of_enrollment' => $year_of_enrollment,
                        'reconcilliation_status' => $reconcilliation_status,
                        'term' => $term_name,
                        'term_id' => $term_id,
                        'verified_beneficiaries' => $verified_ben->beneficiary_counter,
                        'validated_beneficiaries' => $validated_ben->beneficiary_counter,
                        'confirmed_total_fees' => $validated_ben->total_fees,
                        'waiting_payments' => $waiting_details->beneficiary_counter,
                        'waiting_payments_total_fees' => $waiting_details->waiting_payments_total_fees,
                        'recon_suspense_amount' => $suspense_amount,
                        'paid_for_beneficiares' => $disbursement_rpt->beneficiary_counter,
                        'total_disbursement' => $disbursement_rpt->total_fees,
                        'receipted_beneficiaries' => $receipt_details->beneficiary_counter,
                        'receipted_amount' => $receipt_details->total_fees
                    );
                }

            }
        }
        $resp = array('results' => $data);
        json_output($resp);
    }

    function getSchoolpaymentreconcilliationdetails($start, $limit, $term_id, $year_of_enrollment, $is_nonreconcilled = NULL, $school_id, $district_id, $payment_status, $receipt_status, $request_id = '')
    {
        $data = array();
        $where_statement = array(
            'year_of_enrollment' => $year_of_enrollment,
            'term_id' => $term_id
        );
        $user_id = $this->user_id;
        $groups = getUserGroups($user_id);
        $superUserID = getSuperUserGroupId();
        $districts = getUserDistricts($user_id);
        if (validateisNumeric($school_id)) {
            $where_statement['t2.id'] = $school_id;
            $start = 0;
        }
        if (validateisNumeric($district_id)) {
            $where_statement['t2.district_id'] = $district_id;
            $start = 0;
        }
        $totals = DB::table('beneficiary_enrollments as t1')
            ->select(DB::raw('count(t1.id) as counter'))
            ->join('school_information as t2', 't1.school_id', '=', 't2.id')
            ->join('districts as t3', 't2.district_id', '=', 't3.id')
            ->where($where_statement);
        $totals = in_array($superUserID, $groups) == 1 ? $totals->whereRaw("1=1") : $totals->whereIn('t2.district_id', $districts);
        $totals = $totals->groupBy('t2.id')
            ->get();
        $totals = $totals->count();
        $qry = DB::table('beneficiary_enrollments as t1')
            ->select(DB::raw("t1.school_id,term_id,year_of_enrollment, t2.name as school_name,t2.code as school_code,
                              t3.name as district_name,count(t1.id) as verified_bencount"))
            ->join('school_information as t2', 't1.school_id', '=', 't2.id')
            ->join('districts as t3', 't2.district_id', '=', 't3.id')
            ->where($where_statement);
        $qry = in_array($superUserID, $groups) == 1 ? $qry->whereRaw("1=1") : $qry->whereIn('t2.district_id', $districts);
        $qry->groupBy('t2.id')
            ->offset($start)
            ->limit($limit);
        $results = $qry->get();
        if ($results->count() > 0) {
            foreach ($results as $row) {
                $school_name = $row->school_name;
                $school_id = $row->school_id;
                $school_code = $row->school_code;
                $district_name = $row->district_name;
                $verified_bencount = $row->verified_bencount;

                $validated_ben = getSchBenefiaryenrollmentcounter($year_of_enrollment, $term_id, $school_id, $request_id);

                $disbursement_rpt = getSchoolPaymentdisbursements($year_of_enrollment, $term_id, $school_id, $request_id);

                $payments_rpt = getSchBeneficiaryPaymentRecords($year_of_enrollment, $term_id, $school_id, $request_id);

                $receipting_rpt = getSchoolBeneficiaryReceiptCounter($year_of_enrollment, $term_id, $school_id, $request_id);

                $suspense_amount = getSchoolSuspenseAmounts($year_of_enrollment, $term_id, $school_id, $request_id);

                $payable_amount = ($payments_rpt->waiting_payments_total_fees + $suspense_amount);

                $validated_fees = $validated_ben->total_fees;
                $reconcilliation_status = 0;
                if ($disbursement_rpt->total_fees >= $validated_fees && $validated_fees != 0) {
                    $reconcilliation_status = 1;
                }
                if (isset($payment_status) && $payment_status != '') {
                    if ($payment_status == 1) {//full payments
                        if ($validated_ben->total_fees != $disbursement_rpt->total_fees || $disbursement_rpt->total_fees < 5)
                            continue;
                    } else if ($payment_status == 2) {//over payments
                        if (($disbursement_rpt->total_fees - $validated_ben->total_fees) < 5 || $validated_ben->total_fees == $disbursement_rpt->total_fees || $disbursement_rpt->total_fees < $validated_ben->total_fees)
                            continue;
                    } else if ($payment_status == 3) {//under payments
                        if (($validated_ben->total_fees - $disbursement_rpt->total_fees) < 5 || $validated_ben->total_fees == $disbursement_rpt->total_fees || $disbursement_rpt->total_fees > $validated_ben->total_fees)
                            continue;
                    } else if ($payment_status == 4) {//no payments
                        if ($disbursement_rpt->total_fees > 5)
                            continue;
                    }
                }
                if (isset($receipt_status) && $receipt_status != '') {
                    if ($receipt_status == 1) {//balanced
                        if ($receipting_rpt->total_fees != $disbursement_rpt->total_fees || $receipting_rpt->total_fees < 5)
                            continue;
                    } else if ($receipt_status == 2) {//not balanced
                        if (abs(($disbursement_rpt->total_fees - $receipting_rpt->total_fees)) < 5 || $receipting_rpt->total_fees < 5)
                            continue;
                    } else if ($receipt_status == 3) {//not receipted
                        if ($receipting_rpt->total_fees > 5)
                            continue;
                    }
                }
                if ($is_nonreconcilled == 1) {
                    if ($reconcilliation_status == 0) {
                        $data[] = array(
                            'school_name' => $school_name,
                            'reconcilliation_status' => $reconcilliation_status,
                            'school_id' => $school_id,
                            'school_code' => $school_code,
                            'district_name' => $district_name,
                            'verified_beneficiaries' => $verified_bencount,
                            'validated_beneficiaries' => $validated_ben->beneficiary_counter,
                            'confirmed_total_fees' => $validated_ben->total_fees,
                            'paid_for_beneficiares' => $disbursement_rpt->beneficiary_counter,
                            'total_disbursement' => $disbursement_rpt->total_fees,
                            'receipted_beneficiaries' => $receipting_rpt->beneficiary_counter,
                            'receipted_amount' => $receipting_rpt->total_fees,
                            'waiting_payments' => $payments_rpt->beneficiary_counter,
                            'waiting_payments_total_fees' => $payments_rpt->waiting_payments_total_fees,
                            'recon_suspense_amount' => $suspense_amount,
                            'sch_recon_payable_amount' => $payable_amount
                        );
                    }
                } else {
                    $data[] = array(
                        'school_name' => $school_name,
                        'reconcilliation_status' => $reconcilliation_status,
                        'school_id' => $school_id,
                        'school_code' => $school_code,
                        'district_name' => $district_name,
                        'verified_beneficiaries' => $verified_bencount,
                        'validated_beneficiaries' => $validated_ben->beneficiary_counter,
                        'confirmed_total_fees' => $validated_ben->total_fees,
                        'paid_for_beneficiares' => $disbursement_rpt->beneficiary_counter,
                        'total_disbursement' => $disbursement_rpt->total_fees,
                        'receipted_beneficiaries' => $receipting_rpt->beneficiary_counter,
                        'receipted_amount' => $receipting_rpt->total_fees,
                        'waiting_payments' => $payments_rpt->beneficiary_counter,
                        'waiting_payments_total_fees' => $payments_rpt->waiting_payments_total_fees,
                        'recon_suspense_amount' => $suspense_amount,
                        'sch_recon_payable_amount' => $payable_amount
                    );
                }
            }
        }
        if (count($data) < 1) {
            $data = array(
                'school_name' => 'null',
                'reconcilliation_status' => 'null',
                'school_id' => 0,
                'school_code' => 00,
                'district_name' => 'No Data',
                'verified_beneficiaries' => 0,
                'validated_beneficiaries' => 0,
                'confirmed_total_fees' => 0,
                'paid_for_beneficiares' => 0,
                'total_disbursement' => 0,
                'receipted_beneficiaries' => 0,
                'receipted_amount' => 0,
                'waiting_payments' => 0,
                'waiting_payments_total_fees' => 0,
                'recon_suspense_amount' => 0,
                'sch_recon_payable_amount' => 0
            );
        }
        $resp = array('results' => $data, 'totals' => $totals);
        json_output($resp);
    }

    public function getSchpaymentNonreconcilliationStr(Request $req)
    {
        $term_id = $req->term_id;
        $year_of_enrollment = $req->year_of_enrollment;
        $school_id = $req->school_id;
        $district_id = $req->district_id;
        $start = $req->start;
        $limit = $req->limit;
        $this->getSchoolpaymentreconcilliationdetails($start, $limit, $term_id, $year_of_enrollment, 1, $school_id, $district_id);
    }

    public function getSchpaymentreconcilliationStr(Request $req)
    {
        $term_id = $req->input('term_id');
        $year_of_enrollment = $req->input('year_of_enrollment');
        $school_id = $req->input('school_id');
        $district_id = $req->input('district_id');
        $payment_status = $req->input('payment_status');
        $receipt_status = $req->input('receipt_status');
        $payment_request_id = $req->input('request_batch_id');
        $start = $req->input('start');
        $limit = $req->input('limit');
        $this->getSchoolpaymentreconcilliationdetails($start, $limit, $term_id, $year_of_enrollment, 0, $school_id, $district_id, $payment_status, $receipt_status, $payment_request_id);
    }

    public function getPaymentdashboarddatacnaceledlocal()//Job on 1223/06/2022
   {
    // Get default limit
    $normalTimeLimit = ini_get('max_execution_time');//Job on 18/10/2022

    // Set new limit
      ini_set('max_execution_time', 0); //Job on 18/10/2022
                //disbursements
                $sql_query = DB::table('payment_disbursement_details as t1')
                    ->select(DB::raw('sum(decrypt(amount_transfered)) as total_disbursement'))
                    ->first();
                $total_disbursement = formatMoney($sql_query->total_disbursement);

                $sql_query2 = DB::table('beneficiary_information')
                    ->select(DB::raw('count(beneficiary_id) as active_beneficiaries'))
                    ->where(array('enrollment_status' => 1, 'beneficiary_status' => 4))
                    ->first();
            
                $active_beneficiaries = $sql_query2->active_beneficiaries;

                

                $sql_query3=db::table('beneficiary_information as t1')
                    ->join('beneficiary_enrollments as t2','t1.id','t2.beneficiary_id')
                    ->join('beneficiary_payment_records as t3','t3.enrollment_id','t3.id')
                    ->where(['beneficiary_status'=>4,'kgs_takeup_status'=>1])
                    ->selectraw('COUNT(DISTINCT t1.beneficiary_id) as paid_4_beneficiaries')->first();

                    //before 2022 count job on 27/05/2022
                $paid_4_beneficiaries_with_zero_fees_query=Db::select("SELECT COUNT(t1.beneficiary_id),t1.beneficiary_id as ben_id FROM beneficiary_enrollments AS t1 
                INNER JOIN beneficiary_payment_records AS t2 ON t1.id=t2.enrollment_id  WHERE t1.year_of_enrollment<2022  GROUP BY t1.beneficiary_id");
                
            
            
            
                    //from 2022 count job
                // $paid_4_beneficiaries_query_with_no_zero_fees_query=Db::select("SELECT COUNT(t1.beneficiary_id), FROM beneficiary_enrollments AS t1 
                // INNER JOIN beneficiary_payment_records AS t2 ON t1.id=t2.enrollment_id  INNER JOIN payment_request_details as t4 on t4.id=t2.payment_request_id WHERE t1.year_of_enrollment>=2022 AND decrypt(t1.annual_fees)>0 AND t4.status_id>6 GROUP BY t1.beneficiary_id");
            
            $counted_beneficiries=array();
            foreach( $paid_4_beneficiaries_with_zero_fees_query as $ben_data)
            {
                $counted_beneficiries[]=$ben_data->ben_id;
            }
            //$counted_beneficiries=implode(",",$counted_beneficiries);
            

            //    $paid_4_beneficiaries_query_with_no_zero_fees_query=Db::select("SELECT COUNT(t2.beneficiary_id),t2.beneficiary_id as ben_id FROM   beneficiary_enrollments as t2 inner join beneficiary_payment_records AS t3 ON t2.id=t3.enrollment_id
            //    INNER JOIN payment_request_details as t4 on t4.id=t3.payment_request_id WHERE t2.year_of_enrollment>=2022 AND decrypt(t2.annual_fees)>0  AND t4.status_id>=4 AND t2.beneficiary_id NOT In ($counted_beneficiries) GROUP by t2.beneficiary_id ");
            

            $paid_4_beneficiaries_query_with_no_zero_fees_query=Db::select("SELECT COUNT(t2.beneficiary_id),t2.beneficiary_id as ben_id FROM   beneficiary_enrollments as t2 inner join beneficiary_payment_records AS t3 ON t2.id=t3.enrollment_id
            INNER JOIN payment_request_details as t4 on t4.id=t3.payment_request_id WHERE t2.year_of_enrollment>=2022 AND decrypt(t2.annual_fees)>0  AND t4.status_id>=4  GROUP by t2.beneficiary_id ");
            

            $second_counted_beneficiries=array();
            foreach(  $paid_4_beneficiaries_query_with_no_zero_fees_query as $ben_data)
        {
            if(!in_array($ben_data->ben_id,$counted_beneficiries)){
                $second_counted_beneficiries[]=$ben_data->ben_id;
                $counted_beneficiries[]=$ben_data->ben_id;
            }
        
        }
        
            //$second_counted_beneficiries=implode(",",$second_counted_beneficiries);
            // $merged_count=count( array_merge($second_counted_beneficiries,$counted_beneficiries_array));
            // dd($merged_count);
            //$merged_count=$counted_beneficiries.",".$second_counted_beneficiries;
        // $merged_count=explode(",",$merged_count);
        $merged_count=$counted_beneficiries;
        
            //$grant_recieved_query=Db::select("SELECT COUNT(DISTINCT t1.beneficiary_id) FROM   payments_beneficiaries_grant as t1 WHERE t1.grant_recieved>=1  AND t1.beneficiary_id NOT In ($merged_count) GROUP by t1.beneficiary_id ");

            $grant_recieved_query=Db::select("SELECT DISTINCT t1.beneficiary_id  as ben_id FROM   payments_beneficiaries_grant as t1 WHERE t1.grant_recieved=1  ");
            foreach($grant_recieved_query as $ben)
            {
                if(!in_array($ben->ben_id,$merged_count))
                {
                    $merged_count[]=$ben->ben_id;
                }
            }

            $paid_4_beneficiaries_with_zero_fees=count($paid_4_beneficiaries_with_zero_fees_query);
            $paid_4_beneficiaries_query_with_no_zero_fees=count($paid_4_beneficiaries_query_with_no_zero_fees_query);
        
        // dd($paid_4_beneficiaries_with_zero_fees);
            //dd($paid_4_beneficiaries_query_with_no_zero_fees);
        // $paid_4_beneficiaries_query_with_no_zero_fees=0;
            
            $paid_4_beneficiaries=$paid_4_beneficiaries_with_zero_fees+$paid_4_beneficiaries_query_with_no_zero_fees;
            $paid_4_beneficiaries=count($counted_beneficiries);
            $supported_beneficiaries=count( $grant_recieved_query)+$paid_4_beneficiaries;
            
            //$paid_4_beneficiaries = $sql_query3->paid_4_beneficiaries;

        // $average_disbursement = formatMoney(($sql_query->total_disbursement / $sql_query3->paid_4_beneficiaries));
            $average_disbursement = formatMoney(($sql_query->total_disbursement / $paid_4_beneficiaries));


        
            $data = array(
                'total_disbursement' => $total_disbursement,
                'active_beneficiaries' => number_format($active_beneficiaries),
                'paid_4_beneficiaries' => number_format($paid_4_beneficiaries),
                'average_disbursement' => $average_disbursement,
                'supported_beneficiaries'=>count($merged_count)
            );
            ini_set('max_execution_time', $normalTimeLimit); //Job on 18/10/2022
            // $res = array(
            //     'success' => true,
            //     'results' => $data,
            //     'message' => 'All is well'
            // );
            // return response()->json($res);
            json_output($data);
    }

    public function getPaymentdashboarddata()//Job on 1223/06/2022
    {
            // Get default limit
            $normalTimeLimit = ini_get('max_execution_time');//Job on 18/10/2022

            // Set new limit
              ini_set('max_execution_time', 0); //Job on 18/10/2022
                //disbursements
                $sql_query = DB::table('payment_disbursement_details as t1')
                    ->select(DB::raw('sum(decrypt(amount_transfered)) as total_disbursement'))
                    ->first();
                $total_disbursement = formatMoney($sql_query->total_disbursement);

                $sql_query2 = DB::table('beneficiary_information')
                    ->select(DB::raw('count(beneficiary_id) as active_beneficiaries'))
                    ->where(array('enrollment_status' => 1, 'beneficiary_status' => 4))
                    ->first();
            
                $active_beneficiaries = $sql_query2->active_beneficiaries;

                $sql_query3=db::table('beneficiary_information as t1')
                    ->join('beneficiary_enrollments as t2','t1.id','t2.beneficiary_id')
                    ->join('beneficiary_payment_records as t3','t3.enrollment_id','t3.id')
                    ->where(['beneficiary_status'=>4,'kgs_takeup_status'=>1])
                    ->selectraw('COUNT(DISTINCT t1.beneficiary_id) as paid_4_beneficiaries')->first();

                    //before 2022 count job on 27/05/2022
                $paid_4_beneficiaries_with_zero_fees_query=Db::select("SELECT COUNT(t1.beneficiary_id),t1.beneficiary_id as ben_id,t5.beneficiary_id as ben2id FROM beneficiary_enrollments AS t1 
                INNER JOIN beneficiary_payment_records AS t2 ON t1.id=t2.enrollment_id LEFT JOIN beneficiary_information as t5 on t5.id=t1.beneficiary_id  WHERE t1.year_of_enrollment<2022  GROUP BY t1.beneficiary_id");
            //from 2022           
            $counted_beneficiries=array();
            foreach( $paid_4_beneficiaries_with_zero_fees_query as $ben_data)
            {
                $counted_beneficiries[]=$ben_data->ben2id;
            }

            $paid_4_beneficiaries_query_with_no_zero_fees_query=Db::select("SELECT COUNT(t2.beneficiary_id),t2.beneficiary_id as ben_id,t5.beneficiary_id as ben2id FROM   beneficiary_enrollments as t2 inner join beneficiary_payment_records AS t3 ON t2.id=t3.enrollment_id
            INNER JOIN payment_request_details as t4 on t4.id=t3.payment_request_id LEFT JOIN beneficiary_information as t5 on t5.id=t2.beneficiary_id  WHERE t2.year_of_enrollment>=2022 AND decrypt(t2.annual_fees)>0  AND t4.status_id>=4  GROUP by t2.beneficiary_id ");
           
            foreach(  $paid_4_beneficiaries_query_with_no_zero_fees_query as $ben_data)
            {
               
                if(!in_array($ben_data->ben2id,$counted_beneficiries)){
                   
                    $counted_beneficiries[]=$ben_data->ben2id;
                }
            
            }        
          
            $merged_count=$counted_beneficiries;
        

            $grant_recieved_query=Db::select("SELECT DISTINCT t1.beneficiary_id  as ben_id FROM   payments_beneficiaries_grant as t1 WHERE t1.grant_recieved=1");
            foreach($grant_recieved_query as $ben)
            {
                if(!in_array($ben->ben_id,$merged_count))
                {
                    $merged_count[]=$ben->ben_id;
                }
            }

            $paid_4_beneficiaries_with_zero_fees=count($paid_4_beneficiaries_with_zero_fees_query);
            $paid_4_beneficiaries_query_with_no_zero_fees=count($paid_4_beneficiaries_query_with_no_zero_fees_query);
            $paid_4_beneficiaries=count($counted_beneficiries);
            $supported_beneficiaries=count( $merged_count);
           $average_disbursement = formatMoney(($sql_query->total_disbursement / $paid_4_beneficiaries));
        
            $data = array(
                'total_disbursement' => $total_disbursement,
                'active_beneficiaries' => number_format($active_beneficiaries),
                'paid_4_beneficiaries' => number_format($paid_4_beneficiaries),
                'average_disbursement' =>$average_disbursement,
                'supported_beneficiaries'=>number_format(count($merged_count))
            );

            $data = array(
                'total_disbursement' => $total_disbursement,
                'active_beneficiaries' => number_format($active_beneficiaries),
                'paid_4_beneficiaries' => number_format($paid_4_beneficiaries),
                'average_disbursement' =>$average_disbursement,
                'supported_beneficiaries'=>number_format(count($merged_count))
            );
            ini_set('max_execution_time', $normalTimeLimit); //Job on 18/10/2022
            json_output($data);
    }

    public function getPaymentDisbursementDashboardData()
    {
        try {
            $qry = DB::table('payment_disbursement_details as t1')
                ->join('payment_request_details as t2', 't1.payment_request_id', '=', 't2.id')
                ->select(DB::raw('sum(decrypt(amount_transfered)) as amount_transfered,
                                  t2.payment_year,t2.term_id'))
                ->groupBy('payment_year')
                ->groupBy('term_id');
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

    public function getenrolledbeneficiariesbDashboarddetails()
    {
        try {
            $qry = DB::table('beneficiary_enrollments as t1')
                ->select(DB::raw('t1.year_of_enrollment,count(DISTINCT t1.beneficiary_id) as no_of_girls'));
            $qry->where('t1.is_validated', 1)
                ->groupBy('t1.year_of_enrollment');
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

    public function getBenpaymentdisbDashboardChart()
    {
        try {
            $qry = DB::table('beneficiary_payment_records as t1')
                ->join('beneficiary_enrollments as t2', 't2.id', '=', 't1.enrollment_id')
                ->select(DB::raw('t2.year_of_enrollment,count(DISTINCT t2.beneficiary_id) as no_of_girls'));
            $qry->where('t2.is_validated', 1)
                ->groupBy('t2.year_of_enrollment');
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

    public function getPaymentdisbDashboardChartdetails()
    {
        try {
            $qry = DB::table('payment_disbursement_details as t1')
                ->join('payment_request_details as t2', 't2.id', '=', 't1.payment_request_id')
                ->select(DB::raw('payment_year as year_of_enrollment, sum(decrypt(amount_transfered)) as amount_disbursed'))
                ->groupBy('payment_year');
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

    public function addDefaultPaymentBeneficiaries($batch_id, $school_id, $term_id, $year_of_enrollment, $checklist_form_id)
    {
        $term_id = 3;
        $ben_data = DB::table('payment_checklists_track_details as t0')
            ->join('beneficiary_information as t1', 't0.girl_id', '=', 't1.id')
            ->join('school_information as t2', 't1.school_id', '=', 't2.id')
            ->join('districts as t3', 't2.district_id', '=', 't3.id')
            ->join('provinces as t4', 't3.province_id', '=', 't4.id')
            ->select('t1.*')
            ->where(array('t1.school_id' => $school_id, 't1.beneficiary_status' => 4, 't1.enrollment_status' => 1))
            ->where('t1.payment_eligible', 1)
            // ->where('t0.track_id', $checklist_form_id)
            ->where(function ($query) {
                $query->where('t1.under_promotion', 0)
                    ->orWhereNull('t1.under_promotion');
            })->groupBy('t1.id')->get();
        foreach ($ben_data as $ben_datum) {
            $school_fees = getAnnualSchoolFees($school_id, $ben_datum->beneficiary_school_status, $ben_datum->current_school_grade, $year_of_enrollment);
            $annual_fees = ($school_fees['term1_fees'] + $school_fees['term2_fees'] + $school_fees['term3_fees']);
            $table_data = array(
                'beneficiary_id' => $ben_datum->id,
                'school_id' => $school_id,
                'year_of_enrollment' => $year_of_enrollment,
                'term_id' => $term_id,
                'school_grade' => $ben_datum->current_school_grade,
                'beneficiary_schoolstatus_id' => $ben_datum->beneficiary_school_status,
                'enrollment_status_id' => 0,
                'term1_fees' => aes_encrypt(0),
                'term2_fees' => aes_encrypt(0),
                'term3_fees' => aes_encrypt(0),
                'annual_fees' => aes_encrypt(0),
                'batch_id' => $batch_id,
                'has_signed' => 0
            );
            $where = array(
                'beneficiary_id' => $ben_datum->id,
                'school_id' => $school_id,
                'year_of_enrollment' => $year_of_enrollment,
                'term_id' => $term_id
            );

            $this->saveBeneficiaryEnrollmentdata($table_data, $where);
        }
    }

    public function updateEnrollmentSchoolFee(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $update_flag = $req->input('update_flag');
        // $res = $this->AutoUpdateEnrollmentSchoolFee($batch_id, $update_flag);
        $res = array(
            'success' => true,
            'message' => 'Update made successfully!!'
        );
        return response()->json($res);
    }

    public function AutoUpdateEnrollmentSchoolFee($batch_id, $update_flag = 2)
    {
        try {
            $qry = DB::table('beneficiary_enrollments')
                ->where('batch_id', $batch_id)
                ->where('submission_status', '<>', 2);
            if ($update_flag == 1) {
                $qry->where(function ($query) {
                    $query->where('school_fees', 0)
                        ->orWhereNull('school_fees');
                });
            }
            $enrollment_details = $qry->get();
            $counter=0;
            $weekly_border_plus = getWeeklyBordersTopUpAmount();//job on 30/1/2022
            $grant_aided_plus = getGrantAidedTopUpAmount();//job on 20/4/2022
            foreach ($enrollment_details as $enrollment_detail) {
                $school_id = $enrollment_detail->school_id;
                $running_agency_id=DB::table('school_information')
                    ->where('id',$school_id)->value('running_agency_id');
                $enrollment_type_id = $enrollment_detail->beneficiary_schoolstatus_id;
                $grade_id = $enrollment_detail->school_grade;
                $year = $enrollment_detail->year_of_enrollment;
                $isGCE = $enrollment_detail->is_gce_external_candidate;
                $wb_facility_manager_id=$enrollment_detail->wb_facility_manager_id;
                $fees = getAnnualSchoolFees($school_id, $enrollment_type_id, $grade_id, $year);
                $term3_fees = 0;
                // $term3_fees = $fees['term3_fees'];
                $exam_fees = '';
                $varied_grade_nine_fees= Db::table('school_running_agencies')
                    ->where('id',$running_agency_id)->value('grade_nine_twelve');
                $boarder_fess_for_agency = Db::table('school_running_agencies')
                    ->where('id',$running_agency_id)->value('b_fees');
                $varied_fees=Db::table('school_running_agencies')
                    ->where('id',$running_agency_id)->value('varied_fees');
                $counter++;
                if ($enrollment_detail->school_grade == 9 || $enrollment_detail->school_grade == 12) {
                    $exam_fees = aes_decrypt($enrollment_detail->exam_fees);
                }
                if ($isGCE == 1 || $isGCE === 1) {//term 3 fees not applicable
                    $term3_fees = '';
                    if ($enrollment_detail->school_grade == 9 || $enrollment_detail->school_grade == 12) {
                        if($varied_grade_nine_fees==1  &&  $varied_fees==1 && $running_agency_id==2) {   
                            if( $enrollment_type_id==3) {//weekly boarder
                                if($wb_facility_manager_id==1){
                                    // $initial_fees_t1= $fees['term1_fees'];
                                    $initial_fees_t2= $fees['term2_fees'];
                                    // $initial_fees_t2= 0;
                                    // $fees['term1_fees']=$grant_aided_plus+$initial_fees_t1;
                                    $fees['term1_fees']=0;
                                    $fees['term2_fees']=$grant_aided_plus+$initial_fees_t2;
                                    $term3_fees =0;
                                }else{
                                   //private facility
                                    $term3_fees =0;
                                    $fees['term1_fees']=0;
                                    $fees['term2_fees']=0;
                                }
                            }else{
                                // $initial_fees_t1= $fees['term1_fees'];
                                $initial_fees_t1= 0;
                                $initial_fees_t2= $fees['term2_fees'];
                                $fees['term1_fees']=0;
                                // $fees['term1_fees']=$grant_aided_plus+$initial_fees_t1;
                                $fees['term2_fees']=$grant_aided_plus+$initial_fees_t2;
                                $term3_fees =0;
                            }                            
                        }
                        if( $varied_grade_nine_fees==1 && $running_agency_id!=2) {
                            if( $enrollment_type_id==3) {//weekly boarder
                                if($wb_facility_manager_id==1) {
                                    $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
                                    $fees['term1_fees']=0;
                                    $fees['term2_fees']=$weekly_border_plus+$fees2['term2_fees'];
                                    $term3_fees =0;
                                }else{
                                    // $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
                                    $fees['term1_fees']=0;
                                    $fees['term2_fees']=0;
                                    $term3_fees =0;
                                    $exam_fees=0;
                                }
                            }
                        }                       
                    }else{                       
                        if( $enrollment_type_id==3) {//weekly boarder
                            if($wb_facility_manager_id==1){
                                $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
                                // $fees['term1_fees']=$weekly_border_plus+$fees2['term1_fees'];
                                $fees['term1_fees']=0;
                                $fees['term2_fees']=$weekly_border_plus+$fees2['term2_fees'];
                                $term3_fees =0;                                
                            }else{
                                // $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
                                $fees['term1_fees']=0;
                                $fees['term2_fees']=0;
                                $term3_fees =0;
                                $exam_fees=0;
                            }
                        }
                    }
                }else{//job on 29/03/2022  
                    $exam_fees=0;
                    if (($enrollment_detail->school_grade == 9 || $enrollment_detail->school_grade == 12)) {
                        if( $varied_grade_nine_fees==1) {
                            if( ($enrollment_type_id==1 || $enrollment_type_id==4) && $varied_fees==2) {//day schlaf
                                $fees['term1_fees']=0;
                                $fees['term2_fees']=0;
                                $term3_fees =0;
                            }
                            if( $enrollment_type_id==3 ) {//weekly boarder
                                if($wb_facility_manager_id==1) {
                                    if($varied_fees==2) {//to enure grant-aided students get fees
                                        // $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
                                        // $fees['term1_fees']=$weekly_border_plus;
                                        $fees['term1_fees']=0;
                                        $fees['term2_fees']=$weekly_border_plus;
                                        // $term3_fees =$weekly_border_plus;
                                        $term3_fees =0;
                                    }
                                }else{//private facility
                                    // $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
                                    $fees['term1_fees']=0;
                                    $fees['term2_fees']=0;
                                    $term3_fees =0;                            
                                }
                            }

                            if( $enrollment_type_id==2 && $varied_fees==2) {// boarder
                                // $fees2 = getAnnualSchoolFees($school_id, 2, $grade_id, $year);//boarders
                                $fees['term1_fees']=0;
                                $fees['term2_fees']=$boarder_fess_for_agency;
                                $term3_fees =0;
                            }
                        }
                    }else{
                        if( $enrollment_type_id==3) {//weekly boarder
                            if($wb_facility_manager_id==1) {
                                if($varied_fees==2) {//to enure grant-aided students get fees
                                    // $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
                                    $fees['term1_fees']=0;
                                    $fees['term2_fees']=$weekly_border_plus;
                                    $term3_fees =0;
                                }
                            }else{//private facility
                                // $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
                                $fees['term1_fees']=0;
                                $fees['term2_fees']=0;
                                $term3_fees =0;                        
                            }
                        }
                    }
                }               
                //private agencies
                if($running_agency_id==3) {
                    $fees['term1_fees']=0;
                    $fees['term2_fees']=0;
                    $term3_fees =0;
                    $exam_fees=0;
                }
                
                // $annual_fees = ((float)$fees['term1_fees'] + (float)$fees['term2_fees'] + (float)$term3_fees + (float)$exam_fees);
                // $annual_fees = ((float)$fees['term1_fees'] + (float)$exam_fees);
                $annual_fees = ((float)$fees['term2_fees'] + (float)$exam_fees);
                $update_params = array(
                    'term1_fees' => aes_encrypt(0),
                    'term2_fees' => aes_encrypt(0),
                    'term3_fees' => aes_encrypt($fees['term3_fees']),
                    'annual_fees' => aes_encrypt($annual_fees)
                );
                DB::table('beneficiary_enrollments')
                    ->where('id', $enrollment_detail->id)
                    ->update($update_params);
            }
            // $this->validateBeneficiaryEnrollment2($batch_id);
            $res = array(
                'success' => true,
                'message' => 'Update made successfully!!'
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
        return $res;
    }

    // public function AutoUpdateEnrollmentSchoolFee($batch_id, $update_flag = 2)
    // {
    //     try {
    //         $qry = DB::table('beneficiary_enrollments')
    //             ->where('batch_id', $batch_id)
    //             ->where('submission_status', '<>', 2);
    //         if ($update_flag == 1) {
    //             $qry->where(function ($query) {
    //                 $query->where('school_fees', 0)
    //                     ->orWhereNull('school_fees');
    //             });
    //         }
    //         $enrollment_details = $qry->get();
    //         $counter=0;
    //         $weekly_border_plus = getWeeklyBordersTopUpAmount();//job on 30/1/2022
    //         $grant_aided_plus = getGrantAidedTopUpAmount();//job on 20/4/2022
    //         foreach ($enrollment_details as $enrollment_detail) {
    //             $school_id = $enrollment_detail->school_id;
    //             $running_agency_id=DB::table('school_information')
    //                 ->where('id',$school_id)->value('running_agency_id');
    //             $enrollment_type_id = $enrollment_detail->beneficiary_schoolstatus_id;
    //             $grade_id = $enrollment_detail->school_grade;
    //             $year = $enrollment_detail->year_of_enrollment;
    //             $isGCE = $enrollment_detail->is_gce_external_candidate;
    //             $wb_facility_manager_id=$enrollment_detail->wb_facility_manager_id;
    //             $fees = getAnnualSchoolFees($school_id, $enrollment_type_id, $grade_id, $year);
    //             $term3_fees = 0;
    //             // $term3_fees = $fees['term3_fees'];
    //             $exam_fees = '';
    //             $varied_grade_nine_fees= Db::table('school_running_agencies')
    //                 ->where('id',$running_agency_id)->value('grade_nine_twelve');
    //             $boarder_fess_for_agency = Db::table('school_running_agencies')
    //                 ->where('id',$running_agency_id)->value('b_fees');
    //             $varied_fees=Db::table('school_running_agencies')
    //                 ->where('id',$running_agency_id)->value('varied_fees');
    //             $counter++;
    //             if ($enrollment_detail->school_grade == 9 || $enrollment_detail->school_grade == 12) {
    //                 $exam_fees = aes_decrypt($enrollment_detail->exam_fees);
    //             }
    //             if ($isGCE == 1 || $isGCE === 1) {//term 3 fees not applicable
    //                 $term3_fees = '';
    //                 if ($enrollment_detail->school_grade == 9 || $enrollment_detail->school_grade == 12) {
    //                     if($varied_grade_nine_fees==1  &&  $varied_fees==1 && $running_agency_id==2) {   
    //                         if( $enrollment_type_id==3) {//weekly boarder
    //                             if($wb_facility_manager_id==1){
    //                                 $initial_fees_t1= $fees['term1_fees'];
    //                                 $initial_fees_t2= 0;
    //                                 $term3_fees =0;
    //                                 $fees['term1_fees']=$grant_aided_plus+$initial_fees_t1;
    //                                 $fees['term2_fees']=0;
    //                             }else{
    //                                //private facility
    //                                 $term3_fees =0;
    //                                 $fees['term1_fees']=0;
    //                                 $fees['term2_fees']=0;
    //                             }
    //                         }else{
    //                             $initial_fees_t1= $fees['term1_fees'];
    //                             $initial_fees_t2=0;
    //                             $term3_fees =0;
    //                             $fees['term1_fees']=$grant_aided_plus+$initial_fees_t1;
    //                             $fees['term2_fees']=0;
    //                         }                            
    //                     }
    //                     if( $varied_grade_nine_fees==1 && $running_agency_id!=2) {
    //                         if( $enrollment_type_id==3) {//weekly boarder
    //                             if($wb_facility_manager_id==1) {
    //                                 $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
    //                                 $fees['term1_fees']=$weekly_border_plus+$fees2['term1_fees'];
    //                                 $fees['term2_fees']=0;
    //                                 $term3_fees =0;
    //                             }else{
    //                                 // $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
    //                                 $fees['term1_fees']=0;
    //                                 $fees['term2_fees']=0;
    //                                 $term3_fees =0;
    //                                 $exam_fees=0;
    //                             }
    //                         }
    //                     }                       
    //                 }else{                       
    //                     if( $enrollment_type_id==3) {//weekly boarder
    //                         if($wb_facility_manager_id==1){
    //                             $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
    //                             $fees['term1_fees']=$weekly_border_plus+$fees2['term1_fees'];
    //                             $fees['term2_fees']=0;
    //                             $term3_fees =0;                                
    //                         }else{
    //                             // $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
    //                             $fees['term1_fees']=0;
    //                             $fees['term2_fees']=0;
    //                             $term3_fees =0;
    //                             $exam_fees=0;
    //                         }
    //                     }
    //                 }
    //             }else{//job on 29/03/2022  
    //                 $exam_fees=0;
    //                 if (($enrollment_detail->school_grade == 9 || $enrollment_detail->school_grade == 12)) {
    //                     if( $varied_grade_nine_fees==1) {
    //                         if( ($enrollment_type_id==1 || $enrollment_type_id==4) && $varied_fees==2) {//day schlaf
    //                             $fees['term1_fees']=0;
    //                             $fees['term2_fees']=0;
    //                             $term3_fees =0;
    //                         }
    //                         if( $enrollment_type_id==3 ) {//weekly boarder
    //                             if($wb_facility_manager_id==1) {
    //                                 if($varied_fees==2) {//to enure grant-aided students get fees
    //                                     // $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
    //                                     $fees['term1_fees']=$weekly_border_plus;
    //                                     $fees['term2_fees']=0;
    //                                     $term3_fees =$weekly_border_plus;
    //                                 }
    //                             }else{//private facility
    //                                 // $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
    //                                 $fees['term1_fees']=0;
    //                                 $fees['term2_fees']=0;
    //                                 $term3_fees =0;                            
    //                             }
    //                         }

    //                         if( $enrollment_type_id==2 && $varied_fees==2) {// boarder
    //                             // $fees2 = getAnnualSchoolFees($school_id, 2, $grade_id, $year);//boarders
    //                             $fees['term1_fees']=$boarder_fess_for_agency;
    //                             $fees['term2_fees']=0;
    //                             $term3_fees =0;
    //                         }
    //                     }
    //                 }else{
    //                     if( $enrollment_type_id==3) {//weekly boarder
    //                         if($wb_facility_manager_id==1) {
    //                             if($varied_fees==2) {//to enure grant-aided students get fees
    //                                 // $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
    //                                 $fees['term1_fees']=$weekly_border_plus;
    //                                 $fees['term2_fees']=0;
    //                                 $term3_fees =0;
    //                             }
    //                         }else{//private facility
    //                             // $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
    //                             $fees['term1_fees']=0;
    //                             $fees['term2_fees']=0;
    //                             $term3_fees =0;                        
    //                         }
    //                     }
    //                 }
    //             }               
    //             //private agencies
    //             if($running_agency_id==3) {
    //                 $fees['term1_fees']=0;
    //                 $fees['term2_fees']=0;
    //                 $term3_fees =0;
    //                 $exam_fees=0;
    //             }
                
    //             // $annual_fees = ((float)$fees['term1_fees'] + (float)$fees['term2_fees'] + (float)$term3_fees + (float)$exam_fees);
    //             $annual_fees = ((float)$fees['term1_fees'] + (float)$exam_fees);
    //             $update_params = array(
    //                 'term1_fees' => aes_encrypt($fees['term1_fees']),
    //                 'term2_fees' => aes_encrypt(0),
    //                 'term3_fees' => aes_encrypt(0),
    //                 'annual_fees' => aes_encrypt($annual_fees)
    //             );
    //             DB::table('beneficiary_enrollments')
    //                 ->where('id', $enrollment_detail->id)
    //                 ->update($update_params);
    //         }
    //         // $this->validateBeneficiaryEnrollment2($batch_id);
    //         $res = array(
    //             'success' => true,
    //             'message' => 'Update made successfully!!'
    //         );
    //     } catch (\Exception $e) {
    //         $res = array(
    //             'success' => false,
    //             'message' => $e->getMessage()
    //         );
    //     } catch (\Throwable $throwable) {
    //         $res = array(
    //             'success' => false,
    //             'message' => $throwable->getMessage()
    //         );
    //     }
    //     return $res;
    // }

    // public function AutoUpdateEnrollmentSchoolFee1($batch_id, $update_flag = 2)
    // {
    //     try {
    //         $qry = DB::table('beneficiary_enrollments')
    //             ->where('batch_id', $batch_id)
    //             ->where('submission_status', '<>', 2);
    //         if ($update_flag == 1) {
    //             $qry->where(function ($query) {
    //                 $query->where('school_fees', 0)
    //                     ->orWhereNull('school_fees');
    //             });
    //         }
    //         $enrollment_details = $qry->get();
    //         $counter=0;
    //         $weekly_border_plus = getWeeklyBordersTopUpAmount();//job on 30/1/2022
    //         $grant_aided_plus = getGrantAidedTopUpAmount();//job on 20/4/2022
    //         foreach ($enrollment_details as $enrollment_detail) {
    //             $school_id = $enrollment_detail->school_id;
    //             $running_agency_id=DB::table('school_information')->where('id',$school_id)->value('running_agency_id');
    //             $enrollment_type_id = $enrollment_detail->beneficiary_schoolstatus_id;
    //             $grade_id = $enrollment_detail->school_grade;
    //             $year = $enrollment_detail->year_of_enrollment;
    //             $isGCE = $enrollment_detail->is_gce_external_candidate;
    //             $wb_facility_manager_id=$enrollment_detail->wb_facility_manager_id;
    //             $fees = getAnnualSchoolFees($school_id, $enrollment_type_id, $grade_id, $year);
    //             $term3_fees = $fees['term3_fees'];
    //             $exam_fees = '';
    //             $varied_grade_nine_fees= Db::table('school_running_agencies')->where('id',$running_agency_id)->value('grade_nine_twelve');
    //             $boarder_fess_for_agency = Db::table('school_running_agencies')->where('id',$running_agency_id)->value('b_fees');
    //             $varied_fees=Db::table('school_running_agencies')->where('id',$running_agency_id)->value('varied_fees');
    //             $counter++;
    //             if ($enrollment_detail->school_grade == 9 || $enrollment_detail->school_grade == 12) {
    //                 $exam_fees = aes_decrypt($enrollment_detail->exam_fees);
                 
    //             }
    //             if ($isGCE == 1 || $isGCE === 1) {//term 3 fees not applicable
    //                 $term3_fees = '';
    //                 if ($enrollment_detail->school_grade == 9 || $enrollment_detail->school_grade == 12) {
    //                     if($varied_grade_nine_fees==1  &&  $varied_fees==1 && $running_agency_id==2)
    //                     {   
    //                         if( $enrollment_type_id==3)//weekly boarder
    //                         {
    //                             if($wb_facility_manager_id==1){
    //                                 $initial_fees_t1= $fees['term1_fees'];
    //                                 $initial_fees_t2= $fees['term2_fees'];
    //                                 $term3_fees =0;
    //                                 $fees['term1_fees']=$grant_aided_plus+$initial_fees_t1;
    //                                 $fees['term2_fees']=$grant_aided_plus+$initial_fees_t2;

    //                             }else{
    //                                //private facility
    //                                 $term3_fees =0;
    //                                 $fees['term1_fees']=0;
    //                                 $fees['term2_fees']=0;
    //                             }

    //                         }else{

    //                             $initial_fees_t1= $fees['term1_fees'];
    //                             $initial_fees_t2= $fees['term2_fees'];
    //                             $term3_fees =0;
    //                             $fees['term1_fees']=$grant_aided_plus+$initial_fees_t1;
    //                             $fees['term2_fees']=$grant_aided_plus+$initial_fees_t2;
    //                         }
                           
                            
    //                     }
    //                     if( $varied_grade_nine_fees==1 && $running_agency_id!=2)
    //                     {
    //                         if( $enrollment_type_id==3)//weekly boarder
    //                         {
    //                             if($wb_facility_manager_id==1){
    //                             $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
    //                             $fees['term1_fees']=$weekly_border_plus+$fees2['term1_fees'];
    //                             $fees['term2_fees']=0;//$weekly_border_plus+$fees2['term2_fees'];
    //                             $term3_fees =0;
    //                             }else{
    //                                 // $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
    //                                 $fees['term1_fees']=0;
    //                                 $fees['term2_fees']=0;
    //                                 $term3_fees =0;
    //                                 $exam_fees=0;
    //                             }

    //                         }
    //                     }
                       
    //                 }else{                       
    //                     if( $enrollment_type_id==3) {//weekly boarder
    //                         if($wb_facility_manager_id==1) {
    //                             $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
    //                             $fees['term1_fees']=$weekly_border_plus+$fees2['term1_fees'];
    //                             $fees['term2_fees']=0;//$weekly_border_plus+$fees2['term2_fees'];
    //                             $term3_fees =0;                            
    //                         }else{
    //                             // $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
    //                             $fees['term1_fees']=0;
    //                             $fees['term2_fees']=0;
    //                             $term3_fees =0;
    //                             $exam_fees=0;
    //                         }
    //                     }
    //                 }
    //             }else{//job on 29/03/2022
    //                 $exam_fees=0;
    //                 if (($enrollment_detail->school_grade == 9 || $enrollment_detail->school_grade == 12)) {
    //                     if( $varied_grade_nine_fees==1) {
    //                         if( ($enrollment_type_id==1 || $enrollment_type_id==4) && $varied_fees==2)//day schlaf
    //                         {
    //                             $fees['term1_fees']=0;
    //                             $fees['term2_fees']=0;
    //                             $term3_fees =0;
    //                         }
                           
    //                         if( $enrollment_type_id==3 ) {//weekly boarder
    //                             if($wb_facility_manager_id==1) {
    //                                 if($varied_fees==2) {//to enure grant-aided students get fees
    //                                     // $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
    //                                     $fees['term1_fees']=$weekly_border_plus;
    //                                     $fees['term2_fees']=$weekly_border_plus;
    //                                     $term3_fees =$weekly_border_plus;
    //                                 }
    //                             }else{//private facility
    //                                 // $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
    //                                 $fees['term1_fees']=0;
    //                                 $fees['term2_fees']=0;
    //                                 $term3_fees =0;                            
    //                             }
    //                             // $fees['term1_fees']=0;
    //                             // $fees['term2_fees']=0;
    //                             // $term3_fees =0;
    //                         }
    //                         if( $enrollment_type_id==2 && $varied_fees==2)
    //                         {// boarder
    //                             // $fees2 = getAnnualSchoolFees($school_id, 2, $grade_id, $year);//boarders
    //                             $fees['term1_fees']=$boarder_fess_for_agency;
    //                             $fees['term2_fees']=$boarder_fess_for_agency;
    //                             $term3_fees =$boarder_fess_for_agency;
    //                         }                            
    //                     }
    //                 }else{
    //                     if( $enrollment_type_id==3) {//weekly boarder
    //                         if($wb_facility_manager_id==1){
    //                             if($varied_fees==2){//to enure grant-aided students get fees
    //                                 // $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
    //                                 $fees['term1_fees']=$weekly_border_plus;
    //                                 $fees['term2_fees']=$weekly_border_plus;
    //                                 $term3_fees =$weekly_border_plus;
    //                             }
    //                         }else{//private facility
    //                             // $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
    //                             $fees['term1_fees']=0;
    //                             $fees['term2_fees']=0;
    //                             $term3_fees =0;                        
    //                         }                         
    //                     }
    //                 }
    //             }
               
    //             //private agencies
    //             if($running_agency_id==3)
    //             {
    //                 $fees['term1_fees']=0;
    //                 $fees['term2_fees']=0;
    //                 $term3_fees =0;
    //                 $exam_fees=0;

    //             }
    //             $annual_fees = ((float)$fees['term1_fees'] + (float)$fees['term2_fees'] + (float)$term3_fees + (float)$exam_fees);
    //             $update_params = array(
    //                 'term1_fees' => aes_encrypt($fees['term1_fees']),
    //                 'term2_fees' => aes_encrypt($fees['term2_fees']),
    //                 'term3_fees' => aes_encrypt($term3_fees),
    //                 'annual_fees' => aes_encrypt($annual_fees)
    //             );
    //             DB::table('beneficiary_enrollments')
    //                 ->where('id', $enrollment_detail->id)
    //                 ->update($update_params);
    //         }
    //         $this->validateBeneficiaryEnrollment2($batch_id);
    //         $res = array(
    //             'success' => true,
    //             'message' => 'Update made successfully!!'
    //         );
    //     } catch (\Exception $e) {
    //         $res = array(
    //             'success' => false,
    //             'message' => $e->getMessage()
    //         );
    //     } catch (\Throwable $throwable) {
    //         $res = array(
    //             'success' => false,
    //             'message' => $throwable->getMessage()
    //         );
    //     }
    //     return $res;
    // }

    // public function AutoUpdateEnrollmentSchoolFeeOriginal($batch_id, $update_flag = 2)
    // {
    //     try {
    //         $qry = DB::table('beneficiary_enrollments')
    //             ->where('batch_id', $batch_id)
    //             ->where('submission_status', '<>', 2);
    //         if ($update_flag == 1) {
    //             $qry->where(function ($query) {
    //                 $query->where('school_fees', 0)
    //                     ->orWhereNull('school_fees');
    //             });
    //         }
    //         $enrollment_details = $qry->get();
    //         $counter=0;
    //         $weekly_border_plus = getWeeklyBordersTopUpAmount();//job on 30/1/2022
    //         foreach ($enrollment_details as $enrollment_detail) {
    //             $school_id = $enrollment_detail->school_id;
    //             $running_agency_id=DB::table('school_information')->where('id',$school_id)->value('running_agency_id');
    //             $enrollment_type_id = $enrollment_detail->beneficiary_schoolstatus_id;
    //             $grade_id = $enrollment_detail->school_grade;
    //             $year = $enrollment_detail->year_of_enrollment;
    //             $isGCE = $enrollment_detail->is_gce_external_candidate;
    //             $wb_facility_manager_id=$enrollment_detail->wb_facility_manager_id;
    //             $fees = getAnnualSchoolFees($school_id, $enrollment_type_id, $grade_id, $year);
    //             $term3_fees = $fees['term3_fees'];
    //             $exam_fees = '';
    //             $varied_grade_nine_fees= Db::table('school_running_agencies')->where('id',$running_agency_id)->value('grade_nine_twelve');
    //             $boarder_fess_for_agency = Db::table('school_running_agencies')->where('id',$running_agency_id)->value('b_fees');
    //             $varied_fees=Db::table('school_running_agencies')->where('id',$running_agency_id)->value('varied_fees');
    //             $counter++;
    //             if ($enrollment_detail->school_grade == 9 || $enrollment_detail->school_grade == 12) {
    //                 $exam_fees = aes_decrypt($enrollment_detail->exam_fees);
                   
    //             }
    //             if ($isGCE == 1 || $isGCE === 1) {//term 3 fees not applicable
    //                 $term3_fees = '';
    //                 if ($enrollment_detail->school_grade == 9 || $enrollment_detail->school_grade == 12) {
    //                     if( $varied_grade_nine_fees==1)
    //                     {
    //                         if( $enrollment_type_id==3)//weekly boarder
    //                         {
    //                             if($wb_facility_manager_id==1){
    //                             $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
    //                             $fees['term1_fees']=$weekly_border_plus+$fees2['term1_fees'];
    //                             $fees['term2_fees']=0;//$weekly_border_plus+$fees2['term2_fees'];
    //                             $term3_fees =0;
    //                             }else{
    //                                 // $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
    //                                 $fees['term1_fees']=0;
    //                                 $fees['term2_fees']=0;
    //                                 $term3_fees =0;
    //                                 $exam_fees=0;

    //                             }

    //                         }
    //                     }
    //                 }else{
                       
    //                         if( $enrollment_type_id==3)//weekly boarder
    //                         {
    //                             if($wb_facility_manager_id==1){
    //                             $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
    //                             $fees['term1_fees']=$weekly_border_plus+$fees2['term1_fees'];
    //                             $fees['term2_fees']=0;//$weekly_border_plus+$fees2['term2_fees'];
    //                             $term3_fees =0;
    //                             }else{
    //                                 // $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
    //                                 $fees['term1_fees']=0;
    //                                 $fees['term2_fees']=0;
    //                                 $term3_fees =0;
    //                                 $exam_fees=0;

    //                             }

    //                         }


                        
    //                 }
    //             }else{//job on 29/03/2022
    //                 $exam_fees=0;
    //                 if (($enrollment_detail->school_grade == 9 || $enrollment_detail->school_grade == 12)) {
    //                     if( $varied_grade_nine_fees==1)
    //                     {
    //                         if( ($enrollment_type_id==1 || $enrollment_type_id==4) && $varied_fees==2)//day schlaf
    //                         {
    //                             $fees['term1_fees']=0;
    //                             $fees['term2_fees']=0;
    //                             $term3_fees =0;
    //                         }

                           
    //                         if( $enrollment_type_id==3 )//weekly boarder
    //                         {
    //                             if($wb_facility_manager_id==1){

    //                                 if($varied_fees==2){//to enure grant-aided students get fees
    //                             // $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
    //                                     $fees['term1_fees']=$weekly_border_plus;
    //                                     $fees['term2_fees']=0;//$weekly_border_plus;
    //                                     $term3_fees=0;//$weekly_border_plus;
    //                                 }
    //                             }else{//private facility
    //                                 // $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
    //                                 $fees['term1_fees']=0;
    //                                 $fees['term2_fees']=0;
    //                                 $term3_fees =0;                            
    //                             }
    //                             // $fees['term1_fees']=0;
    //                             // $fees['term2_fees']=0;
    //                             // $term3_fees =0;
    //                         }

    //                         if( $enrollment_type_id==2 && $varied_fees==2)// boarder
    //                         {
    //                             // $fees2 = getAnnualSchoolFees($school_id, 2, $grade_id, $year);//boarders
    //                             $fees['term1_fees']=$boarder_fess_for_agency;
    //                             $fees['term2_fees']=0;//$boarder_fess_for_agency;
    //                             $term3_fees=0;//$boarder_fess_for_agency;
    //                         }  
    //                     }
    //                 }else{
    //                     if( $enrollment_type_id==3) {//weekly boarder
    //                         if($wb_facility_manager_id==1) {
    //                             if($varied_fees==2) {//to enure grant-aided students get fees
    //                                 // $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
    //                                 $fees['term1_fees']=$weekly_border_plus;
    //                                 $fees['term2_fees']=0;//$weekly_border_plus;
    //                                 $term3_fees=0;//$weekly_border_plus;
    //                             }
    //                         }else{//private facility
    //                             // $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
    //                             $fees['term1_fees']=0;
    //                             $fees['term2_fees']=0;
    //                             $term3_fees =0;                        
    //                         }                        
    //                     }
    //                 }
    //             }
    //             //private agencies
    //             if($running_agency_id==3) {
    //                 $fees['term1_fees']=0;
    //                 $fees['term2_fees']=0;
    //                 $term3_fees =0;
    //                 $exam_fees=0;
    //             }
    //             $annual_fees = ((float)$fees['term1_fees'] + (float)$exam_fees);
    //             $update_params = array(
    //                 'term1_fees' => aes_encrypt($fees['term1_fees']),
    //                 // 'term2_fees' => aes_encrypt($fees['term2_fees']),
    //                 // 'term3_fees' => aes_encrypt($term3_fees),
    //                 'term2_fees' => aes_encrypt(0),
    //                 'term3_fees' => aes_encrypt(0),
    //                 'annual_fees' => aes_encrypt($annual_fees)
    //             );
    //             DB::table('beneficiary_enrollments')
    //                 ->where('id', $enrollment_detail->id)
    //                 ->update($update_params);
    //         }
    //         $this->validateBeneficiaryEnrollment2($batch_id);
    //         $res = array(
    //             'success' => true,
    //             'message' => 'Update made successfully!!'
    //         );
    //     } catch (\Exception $e) {
    //         $res = array(
    //             'success' => false,
    //             'message' => $e->getMessage()
    //         );
    //     } catch (\Throwable $throwable) {
    //         $res = array(
    //             'success' => false,
    //             'message' => $throwable->getMessage()
    //         );
    //     }
    //     return $res;
    // }

    public function getPaymentVerificationTransitionalStages(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $qry = DB::table('payment_verificationtransitionsubmissions as t1')
                ->leftJoin('users as t2', 't1.released_by', '=', 't2.id')
                ->join('payment_verification_statuses as t3', 't1.previous_status_id', '=', 't3.id')
                ->join('payment_verification_statuses as t4', 't1.next_status_id', '=', 't4.id')
                ->select('t3.name as from_stage_name', 't4.name as to_stage_name', 't1.remarks', 't1.created_at as changes_date', 't2.first_name', 't2.last_name')
                ->where(function ($query) use ($batch_id) {
                    $query->where('t1.batch_id', $batch_id)
                        ->whereNotNull('t1.batch_id');
                })
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

    /**
     * @param Request $req
     * @return \Illuminate\Http\JsonResponse
     */
    public function deletePaymentVerificationBatch(Request $req)
    {
        $batch_id = $req->input('id');
        $user_id = \Auth::user()->id;
        $res = [];
        try {
            $batch_status_id = getSingleRecordColValue('payment_verificationbatch', array('id' => $batch_id), 'status_id');
            if ($batch_status_id > 1) {
                $res = array(
                    'success' => false,
                    'message' => 'Delete not allowed at this stage!!'
                );
                return \response()->json($res);
            }
            DB::transaction(function () use ($batch_id, $user_id, &$res) {
                $where1 = array(
                    'batch_id' => $batch_id
                );
                $table1 = 'beneficiary_enrollments';
                DB::table($table1)
                    ->where($where1)
                    ->delete();
                $where2 = array(
                    'id' => $batch_id
                );
                $table2 = 'payment_verificationbatch';
                $prev_data2 = getPreviousRecords($table2, $where2);
                $res = deleteRecord($table2, $prev_data2, $where2, $user_id);
            }, 5);
            $res = array(
                'success' => true,
                'message' => 'Record deleted successfully!!'
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

    public function saveBeneficiaryEnrollmentTransfer(Request $req)
    {
        $new_school_id = $req->input('to_school_id');
        $old_school_id = $req->input('from_school_id');
        $res = array();
        if ($new_school_id == $old_school_id) {
            $res = array(
                'success' => false,
                'message' => 'Transfer not allowed within the same school!!'
            );
            return response()->json($res);
        }
        DB::transaction(function () use ($req, $new_school_id, $old_school_id, &$res) {
            $year = $req->input('year_of_enrollment');
            $term = $req->input('term_id');
            $enrollment_id = $req->input('enrollment_id');
            $girl_id = $req->input('girl_id');
            $district_id = $req->input('district_id');
            $transfer_comment = $req->input('remarks');
            $reason_id = $req->input('reason_id');
            $transfer_grade = $req->input('school_grade');
            $transfer_school_status = $req->input('school_status');
            try {
                $where = array(
                    'school_id' => $new_school_id,
                    'term_id' => $term,
                    'year_of_enrollment' => $year
                );
                $new_batch_info = DB::table('payment_verificationbatch')
                    ->where($where)
                    ->whereIn('status_id', array(1, 2))
                    ->first();
                if (!is_null($new_batch_info)) {
                    //payment batch for the school already exists
                    DB::table('beneficiary_enrollments')
                        ->where('id', $enrollment_id)
                        ->update(array('batch_id' => $new_batch_info->id, 'school_id' => $new_school_id));
                } else {
                    //create new payment verification batch
                    $batch_no = generatePaymentverificationBatchNo();
                    $parent_id = 4;
                    $main_module_id = 1;
                    $folder_id = createDMSParentFolder($parent_id, $main_module_id, $batch_no, '', $this->dms_id);;
                    $table_data = array(
                        'school_id' => $new_school_id,
                        'term_id' => $term,
                        'submitted_on' => Carbon::now(),
                        'submitted_by' => $this->user_id,
                        'additional_remarks' => '',
                        'district_id' => $district_id,
                        'year_of_enrollment' => $year,
                        'created_at' => Carbon::now(),
                        'created_by' => $this->user_id,
                        'batch_no' => $batch_no,
                        'folder_id' => $folder_id,
                        'added_by' => $this->user_id,
                        'added_on' => Carbon::now(),
                        'status_id' => 1
                    );
                    $batch_id = insertRecordReturnId('payment_verificationbatch', $table_data, $this->user_id);
                    if (validateisNumeric($batch_id)) {
                        DB::table('beneficiary_enrollments')
                            ->where('id', $enrollment_id)
                            ->update(array('batch_id' => $batch_id, 'school_id' => $new_school_id));
                    }
                }
                //school transfer
                $transfer_params = array(
                    'girl_id' => $girl_id,
                    'from_school_id' => $old_school_id,
                    'to_school_id' => $new_school_id,
                    'transfer_grade' => $transfer_grade,
                    'transfer_school_status' => $transfer_school_status,
                    'reason_id' => $reason_id,
                    'effective_from_year' => $year,
                    'effective_from_term' => $term,
                    'transfer_type' => 2,
                    'stage' => 3,
                    'status' => 2,
                    'request_by' => $this->user_id,
                    'request_comment' => $transfer_comment,
                    'requested_on' => Carbon::now(),
                    'approval_by' => $this->user_id,
                    'approval_date' => Carbon::now(),
                    'approval_comment' => 'Auto Approval',
                    'archived_by' => $this->user_id,
                    'archive_comment' => 'Auto Archive',
                    'archive_date' => Carbon::now(),
                    'source_module' => 'Payments - Enrollment Transfers',
                    'created_by' => $this->user_id
                );
                DB::table('beneficiary_school_transfers')
                    ->insert($transfer_params);
                $update_params = array(
                    'school_id' => $new_school_id,
                    'current_school_grade' => $transfer_grade,
                    'beneficiary_school_status' => $transfer_school_status
                );
                DB::table('beneficiary_information')
                    ->where('id', $girl_id)
                    ->update($update_params);
                logBeneficiaryGradeTransitioning($girl_id, $transfer_grade, $new_school_id, $this->user_id);
                $res = array(
                    'success' => true,
                    'message' => 'Transfer details saved successfully!!'
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

    public function getSchpaymentdisbursements(Request $req)
    {
        $payment_request_id = $req->input('payment_request_id');
        $school_id = $req->input('school_id');
        $result = DB::table('payment_disbursement_details as t1')
            ->select(DB::raw('t1.created_at as entered_on,decrypt(t3.first_name) as entered_by,decrypt(amount_transfered) as amount_transfered,decrypt(transaction_no) as transaction_no,transaction_date,bank_id,branch_name,account_no,sort_code,t2.name as bank_name'))
            ->leftJoin('bank_details as t2', 't1.bank_id', '=', 't2.id')
            ->leftJoin('users as t3', 't1.created_by', '=', 't3.id')
            ->where(array('school_id' => $school_id, 'payment_request_id' => $payment_request_id))
            ->get();
        json_output(array('results' => $result));
    }

    public function getFeeSetUpErrorLog(Request $req)
    {
        $batch_id = $req->input('batch_id');
        try {
            $qry = DB::table('fees_error_log as t1')
                ->select('t1.*', 't2.name')
                ->join('beneficiary_school_statuses as t2', 't1.school_status_id', '=', 't2.id')
                ->where('batch_id', $batch_id);
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

    public function deleteSchoolPaymentRecord(Request $req)
    {
        $request_id = $req->input('request_id');
        $school_id = $req->input('school_id');
        $year = $req->input('year');
        $term = $req->input('term');
        $where = array(
            't1.school_id' => $school_id,
            't1.year_of_enrollment' => $year
            // 't1.term_id' => $term
        );
        try {
            $qry = DB::table('beneficiary_enrollments as t1')
                ->join('beneficiary_payment_records as t2', 't1.id', '=', 't2.enrollment_id')
                ->select('t1.id as enrollment_id')
                ->where($where)
                ->where('t2.payment_request_id', $request_id);
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $where_data = convertAssArrayToSimpleArray($data, 'enrollment_id');
            Db::table('beneficiary_payment_records')
                ->whereIn('enrollment_id', $where_data)
                ->delete();
            //update reconciliation if any
            /*$update_params = array(
                't1.payment_request_id' => 0,
                't2.payment_request_id' => 0,
                't2.status_id' => 2
            );
            DB::table('reconciliation_suspense_account as t1')
                ->join('reconciliation_oversight_batches as t2', 't1.oversight_batch_id', '=', 't2.id')
                ->where('t1.school_id', $school_id)
                ->where('t1.payment_request_id', $request_id)
                ->update($update_params);*/
            //end
            $res = array(
                'success' => true,
                'message' => 'Record removed successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function deleteSchoolPaymentRecordBatch(Request $req)
    {
        $request_id = $req->input('request_id');
        $year = $req->input('year');
        $term = $req->input('term');
        $selected = $req->input('selected');
        $selected_ids = json_decode($selected);
        try {
            foreach ($selected_ids as $school_id) {
                $where = array(
                    't1.school_id' => $school_id,
                    't1.year_of_enrollment' => $year
                    //'t1.term_id' => $term
                );
                $qry = DB::table('beneficiary_enrollments as t1')
                    ->join('beneficiary_payment_records as t2', 't1.id', '=', 't2.enrollment_id')
                    ->select('t1.id as enrollment_id')
                    ->where($where)
                    ->where('t2.payment_request_id', $request_id);
                $data = $qry->get();
                $data = convertStdClassObjToArray($data);
                $where_data = convertAssArrayToSimpleArray($data, 'enrollment_id');
                Db::table('beneficiary_payment_records')
                    ->whereIn('enrollment_id', $where_data)
                    ->delete();
                //update reconciliation if any
                /*$update_params = array(
                    't1.payment_request_id' => 0,
                    't2.payment_request_id' => 0,
                    't2.status_id' => 2
                );
                DB::table('reconciliation_suspense_account as t1')
                    ->join('reconciliation_oversight_batches as t2', 't1.oversight_batch_id', '=', 't2.id')
                    ->whereIn('t1.school_id', $selected_ids)
                    ->where('t1.payment_request_id', $request_id)
                    ->update($update_params);*/
                //end
            }
            $res = array(
                'success' => true,
                'message' => 'Record removed successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        } catch (\Throwable $t) {
            $res = array(
                'success' => false,
                'message' => $t->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getSchoolActiveBankInfo(Request $req)
    {
        $id = $req->input('id');
        $school_id = $req->input('school_id');
        $bank_info = array();
        try {
            if (isset($id) && $id != '' && $id != 0) {
                $qry = DB::table('school_bankinformation as t1')
                    ->leftJoin('bank_branches as t11', 't1.branch_name', '=', 't11.id')
                    ->select(DB::raw('t1.id,t1.bank_id,t1.branch_name,t11.sort_code,decrypt(t1.account_no) as account_no'))
                    ->where('t1.id', $id)
                    ->first();
            } else {
                $qry = DB::table('school_bankinformation as t1')
                    ->leftJoin('bank_branches as t11', 't1.branch_name', '=', 't11.id')
                    ->select(DB::raw('t1.id,t1.bank_id,t1.branch_name,t11.sort_code,decrypt(t1.account_no) as account_no'))
                    ->where('t1.school_id', $school_id)
                    ->where('t1.is_activeaccount', 1)
                    ->first();
            }
            if (!is_null($qry)) {
                $bank_info = array(
                    'school_id' => $school_id,
                    'school_bank_id' => $qry->id,
                    'bank_id' => $qry->bank_id,
                    'branch_id' => $qry->branch_name,
                    'account_no' => $qry->account_no,
                    'sort_code' => $qry->sort_code
                );
            }
            $res = array(
                'success' => true,
                'bank_info' => $bank_info,
                'message' => 'All is well'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'bank_info' => $bank_info,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function updateSchoolBankInfo(Request $req)
    {
        $school_bank_id = $req->input('school_bank_id');
        $school_id = $req->input('school_id');
        $bank_id = $req->input('bank_id');
        $branch_id = $req->input('branch_id');
        $account_no = $req->input('account_no');
        $sort_code = $req->input('sort_code');
        $user_id = $this->user_id;
        try {
            $table = 'school_bankinformation';
            //1. If school bank id is set
            if (isset($school_bank_id) && $school_bank_id != '') {
                $count = DB::table('payment_disbursement_details')
                    ->where('school_bank_id', $school_bank_id)
                    ->count();
                if ($count > 0) {
                    $where_check1 = array(
                        'school_id' => $school_id,
                        'bank_id' => $bank_id,
                        'branch_name' => $branch_id,
                        'account_no' => aes_encrypt($account_no)
                    );
                    $check1 = DB::table($table)
                        ->where($where_check1)
                        ->first();
                    if (!is_null($check1)) {
                        $update_data1 = array(
                            'is_activeaccount' => 1,
                            'updated_at' => Carbon::now(),
                            'updated_by' => $user_id
                        );
                        DB::table($table)
                            ->where($where_check1)
                            ->update($update_data1);
                        $school_bank_id = $check1->id;
                    } else {
                        $insert_data1 = array(
                            'school_id' => $school_id,
                            'bank_id' => $bank_id,
                            'branch_name' => $branch_id,
                            'account_no' => aes_encrypt($account_no),
                            'is_activeaccount' => 1,
                            'created_at' => Carbon::now(),
                            'created_by' => $user_id
                        );
                        $school_bank_id = insertRecordReturnId($table, $insert_data1, $user_id);
                    }
                } else {
                    $table_data = array(
                        'school_id' => $school_id,
                        'bank_id' => $bank_id,
                        'branch_name' => $branch_id,
                        'account_no' => aes_encrypt($account_no),
                        'is_activeaccount' => 1,
                        'updated_at' => Carbon::now(),
                        'updated_by' => $user_id
                    );
                    $where = array(
                        'id' => $school_bank_id
                    );
                    $prev_data = getPreviousRecords($table, $where);
                    updateRecord($table, $prev_data, $where, $table_data, $user_id);
                }
            } else {//school bank id not set
                $where_check = array(
                    'school_id' => $school_id,
                    'bank_id' => $bank_id,
                    'branch_name' => $branch_id,
                    'account_no' => aes_encrypt($account_no)
                );
                $check = DB::table($table)
                    ->where($where_check)
                    ->first();
                if (!is_null($check)) {
                    $update_data = array(
                        'is_activeaccount' => 1,
                        'updated_at' => Carbon::now(),
                        'updated_by' => $user_id
                    );
                    DB::table($table)
                        ->where($where_check)
                        ->update($update_data);
                    $school_bank_id = $check->id;
                } else {
                    $insert_data = array(
                        'school_id' => $school_id,
                        'bank_id' => $bank_id,
                        'branch_name' => $branch_id,
                        'account_no' => aes_encrypt($account_no),
                        'is_activeaccount' => 1,
                        'created_at' => Carbon::now(),
                        'created_by' => $user_id
                    );
                    $school_bank_id = insertRecordReturnId($table, $insert_data, $user_id);
                }
            }
            if ($school_bank_id != '') {
                DB::table($table)
                    ->where('school_id', $school_id)
                    ->where('id', '<>', $school_bank_id)
                    ->update(array('is_activeaccount' => 0));
            }
            DB::table('bank_branches')
                ->where('id', $branch_id)
                ->update(array('sort_code' => $sort_code));
            $res = array(
                'success' => true,
                'school_bank_id' => $school_bank_id,
                'message' => 'Bank details updated successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function updateRules()
    {
        $params = array();
        try {
            $data = DB::table('beneficiary_enrollments as t1')
                ->where('t1.has_signed', 0)
                ->where(array('year_of_enrollment' => 2018, 'term_id' => 1))
                ->get();
            foreach ($data as $datum) {
                $params[] = array(
                    'beneficiary_id' => $datum->beneficiary_id,
                    'enrollment_id' => $datum->id,
                    'rule_id' => 1,
                    'remarks' => 'The beneficiary has not signed on the Payment verification checklist.',
                    'created_at' => Carbon::now(),
                    'created_by' => $this->user_id
                );
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
            exit();
        }
        echo count($params);
    }

    public function getValidationSubmittedRecords(Request $req)
    {
        $batch_id = $req->input('batch_id');
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->join('beneficiary_enrollments as t2', function ($join) use ($batch_id) {
                    $join->on('t2.beneficiary_id', '=', 't1.id')
                        ->on('t2.batch_id', '=', DB::raw($batch_id));
                })
                ->leftJoin('beneficiary_school_statuses as t3', 't2.beneficiary_schoolstatus_id', '=', 't3.id')
                ->leftJoin('beneficiary_payment_records as t4', 't2.id', '=', 't4.enrollment_id')
                ->where('t2.submission_status', 2)
                ->select(DB::raw('t1.id,t1.beneficiary_id,decrypt(t1.first_name) as first_name,decrypt(t1.last_name) as last_name,
                                  t2.id as enrollment_id,t4.id as added_for_payment,decrypt(t2.annual_fees) as school_fees,t2.school_grade,t3.name as school_status'))
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

    public function deletePaymentRecord(Request $req)
    {
        $record_id = $req->input('id');
        $table_name = $req->input('table_name');
        $user_id = \Auth::user()->id;
        $where = array(
            'id' => $record_id
        );
        try {
            $previous_data = getPreviousRecords($table_name, $where);
            $res = deleteRecord($table_name, $previous_data, $where, $user_id);
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getPaymentVerificationDetails(Request $req)
    {
        $school_id = $req->input('school_id');
        $year = $req->input('year');
        $term = $req->input('term');
        $where = array(
            't1.school_id' => $school_id,
            't1.year_of_enrollment' => $year,
            't1.term_id' => $term
        );
        try {
            $qry = DB::table('beneficiary_enrollments as t1')
                ->join('beneficiary_information as t2', 't1.beneficiary_id', '=', 't2.id')
                ->select(DB::raw('t2.beneficiary_id as beneficiary_no,decrypt(t2.first_name) as first_name,decrypt(t2.last_name) as last_name,
                                  t1.*'))
                ->where($where);
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

    public function getPaymentDisbursementDetails(Request $req)
    {
        $school_id = $req->input('school_id');
        $year = $req->input('year');
        $term = $req->input('term');
        $where = array(
            't2.payment_year' => $year,
            't2.term_id' => $term
        );
        try {
            $qry = DB::table('payment_disbursement_details as t1')
                ->join('payment_request_details as t2', 't1.payment_request_id', '=', 't2.id')
                ->join('beneficiary_payment_records as t3', 't2.id', '=', 't3.payment_request_id')
                ->join('beneficiary_enrollments as t4', function ($join) use ($school_id, $year, $term) {
                    $join->on('t3.enrollment_id', '=', 't4.id')
                        ->on('t4.school_id', '=', DB::raw($school_id))
                        ->on('t4.year_of_enrollment', '=', DB::raw($year))
                        ->on('t4.term_id', '=', DB::raw($term));
                })
                ->join('beneficiary_information as t5', 't4.beneficiary_id', '=', 't5.id')
                ->select(DB::raw('t5.beneficiary_id as beneficiary_no,decrypt(t5.first_name) as first_name,decrypt(t5.last_name) as last_name,
                                  t4.*'))
                ->where('t1.school_id', $school_id)
                ->where($where);
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

    public function getPaymentReceiptDetails(Request $req)
    {
        $school_id = $req->input('school_id');
        $year = $req->input('year');
        $term = $req->input('term');
        $where = array(
            't1.payment_year' => $year,
            't1.term_id' => $term,
            't1.school_id' => $school_id
        );
        try {
            $qry = DB::table('payment_receiptingbatch as t1')
                ->join('payments_receipting_details as t2', 't1.id', '=', 't2.payment_receipts_id')
                ->join('beneficiary_receipting_details as t3', 't2.id', '=', 't3.payment_receipt_id')
                ->join('beneficiary_enrollments as t4', function ($join) use ($school_id, $year, $term) {
                    $join->on('t2.enrollment_id', '=', 't4.id')
                        ->on('t4.school_id', '=', DB::raw($school_id))
                        ->on('t4.year_of_enrollment', '=', DB::raw($year))
                        ->on('t4.term_id', '=', DB::raw($term));
                })
                ->join('beneficiary_information as t5', 't4.beneficiary_id', '=', 't5.id')
                ->select(DB::raw('t5.beneficiary_id as beneficiary_no,decrypt(t5.first_name) as first_name,decrypt(t5.last_name) as last_name,
                                  SUM(t3.receipt_amount) as receipted_amount'))
                ->where($where)
                ->groupBy('t2.id');
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

    public function saveReconciliationOversightDetails(Request $req)
    {
        $term_id = $req->input('payment_term');
        $oversight_batch_id = $req->input('oversight_batch_id');
        $payment_year = $req->input('payment_year');
        $school_id = $req->input('school_id');
        $ref_no = $req->input('ref_no');
        $data = array(
            'school_id' => $school_id,
            'payment_year' => $payment_year,
            'payment_term' => $term_id,
            'status_id' => 1
        );
        $table_name = 'reconciliation_oversight_batches';
        try {
            if (validateisNumeric($oversight_batch_id)) {
                $where = array(
                    'id' => $oversight_batch_id
                );
                $data['updated_at'] = Carbon::now();
                $data['updated_by'] = $this->user_id;
                $previous_data = getPreviousRecords($table_name, $where);
                updateRecord($table_name, $previous_data, $where, $data, $this->user_id);
            } else {
                $ref_no = generateReconciliationOversightBatchNumber($payment_year, $term_id);
                $data['created_at'] = Carbon::now();
                $data['created_by'] = $this->user_id;
                $data['ref_no'] = $ref_no;
                $oversight_batch_id = insertRecordReturnId($table_name, $data, $this->user_id);
            }
            if (validateisNumeric($oversight_batch_id)) {
                $res = array(
                    'success' => true,
                    'message' => 'Receipting Details Saved Successfully!!',
                    'oversight_batch_id' => $oversight_batch_id,
                    'ref_no' => $ref_no
                );

            } else {
                $res = array(
                    'success' => false,
                    'message' => 'Problem encountered saving information, contact the system Administrator!!'
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

    public function getReconciliationOversightDetails(Request $req)
    {
        $payment_year = $req->input('year');
        $payment_term = $req->input('term');
        $district_id = $req->input('district_id');
        $status_id = $req->input('status_id');
        try {
            $qry = DB::table('reconciliation_oversight_batches as t1')
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                ->join('districts as t3', 't2.district_id', '=', 't3.id')
                ->leftJoin('reconciliation_beneficiaries as t4', 't1.id', '=', 't4.oversight_batch_id')
                ->leftJoin('beneficiary_enrollments as t7', function ($join) {
                    $join->on('t7.id', '=', 't4.enrollment_id')
                        ->on('t7.school_id', '=', 't1.school_id');
                })
                ->leftJoin('users as t5', 't1.created_by', '=', 't5.id')
                ->select(DB::raw('t1.*, t1.id as oversight_batch_id, t2.code as emis_code, t2.name as school_name, t2.district_id, t3.name as school_district,
                                  COUNT(t4.id) as no_of_beneficiaries,SUM(t4.confirmed_fees) as confirmed_total_fees,
                                  SUM(t7.school_fees) as school_feessummary,decrypt(t5.first_name) as added_by'));

            if (isset($status_id) && $status_id != '') {
                $qry->where('t1.status_id', $status_id);
            }
            if (isset($payment_year) && $payment_year != '') {
                $qry->where('t1.payment_year', $payment_year);
            }
            if (isset($payment_term) && $payment_term != '') {
                $qry->where('t1.payment_term', $payment_term);
            }
            if (isset($district_id) && $district_id != '') {
                $qry->where('t2.district_id', $district_id);
            }
            $qry->groupBy('t1.id');
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

    public function getReconciliationArchiveDetails(Request $req)
    {
        $payment_year = $req->input('year');
        $payment_term = $req->input('term');
        $district_id = $req->input('district_id');
        $status_id = $req->input('status_id');
        try {
            $qry = DB::table('reconciliation_oversight_batches as t1')
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                ->join('districts as t3', 't2.district_id', '=', 't3.id')
                ->join('reconciliation_beneficiaries as t4', 't1.id', '=', 't4.oversight_batch_id')
                ->leftjoin('beneficiary_enrollments as t7', function ($join) use ($payment_year, $payment_term) {
                    $join->on('t7.id', '=', 't4.enrollment_id')
                        ->on('t7.school_id', '=', 't1.school_id');
                })
                ->leftJoin('users as t5', 't1.created_by', '=', 't5.id')
                ->leftJoin('payment_request_details as t6', 't1.payment_request_id', '=', 't6.id')
                //->join('reconciliation_suspense_account as t8', 't1.id', '=', 't8.oversight_batch_id')
                ->join('reconciliation_suspense_account as t8', function ($join) use ($payment_year, $payment_term) {
                    $join->on('t8.oversight_batch_id', '=', 't1.id')
                        ->on('t8.school_id', '=', 't1.school_id');
                })
                ->select(DB::raw('t1.*, t1.id as oversight_batch_id, t2.code as emis_code, t2.name as school_name, t2.district_id, t3.name as school_district,
                                  t6.payment_ref_no,COUNT(t4.id) as no_of_beneficiaries,SUM(t4.confirmed_fees) as confirmed_total_fees,
                                  SUM(t7.school_fees) as school_feessummary,decrypt(t5.first_name) as added_by,
                                  IF(t8.credit_debit=1,t8.amount,0-t8.amount) as amount_transfered'));
            if (isset($status_id) && $status_id != '') {
                $qry->where('t1.status_id', $status_id);
            }
            if (isset($payment_year) && $payment_year != '') {
                $qry->where('t1.payment_year', $payment_year);
            }
            if (isset($payment_term) && $payment_term != '') {
                $qry->where('t1.payment_term', $payment_term);
            }
            if (isset($district_id) && $district_id != '') {
                $qry->where('t2.district_id', $district_id);
            }
            $qry->groupBy('t1.id');
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

    public function getBeneficiariesForReconciliation(Request $req)
    {
        $school_id = $req->input('school_id');
        $payment_year = $req->input('year');
        $payment_term = $req->input('term');
        try {
            $qry = DB::table('payment_disbursement_details as t1')
                ->join('payment_request_details as t2', function ($join) use ($payment_year, $payment_term) {
                    $join->on('t2.id', '=', 't1.payment_request_id')
                        ->on('t2.term_id', '=', DB::raw($payment_term))
                        ->on('t2.payment_year', '=', DB::raw($payment_year));
                })
                ->join('beneficiary_payment_records as t3', 't3.payment_request_id', '=', 't2.id')
                ->join('beneficiary_enrollments as t4', function ($join) use ($school_id, $payment_year, $payment_term) {
                    $join->on('t4.id', '=', 't3.enrollment_id')
                        ->on('t4.school_id', '=', DB::raw($school_id))
                        ->on('t4.term_id', '=', DB::raw($payment_term))
                        ->on('t4.year_of_enrollment', '=', DB::raw($payment_year));
                })
                ->join('beneficiary_information as t5', 't4.beneficiary_id', '=', 't5.id')
                ->leftJoin('reconciliation_beneficiaries as t6', 't4.id', '=', 't6.enrollment_id')
                ->leftJoin('reconciliation_oversight_batches as t7', 't7.id', '=', 't6.oversight_batch_id')
                ->select(DB::raw('t4.id,t5.beneficiary_id,decrypt(t5.first_name) as first_name,decrypt(t5.last_name) as last_name,
                                  t6.oversight_batch_id,t7.ref_no,t4.school_fees'))
                ->where('t1.school_id', $school_id)
                ->groupBy('t4.id');
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

    public function addSelectedBeneficiariesReconciliationDetails(Request $req)
    {
        $oversight_batch_id = $req->input('oversight_batch_id');
        $selected = $req->input('selected');
        $selected_ids = json_decode($selected);
        try {
            $params = array();
            foreach ($selected_ids as $selected_id) {
                $params[] = array(
                    'enrollment_id' => $selected_id,
                    'oversight_batch_id' => $oversight_batch_id,
                    'created_by' => $this->user_id,
                    'created_at' => Carbon::now()
                );
            }
            DB::table('reconciliation_beneficiaries')
                ->insert($params);
            $res = array(
                'success' => true,
                'message' => 'Receipt details removed successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getOversightBatchBeneficiaries(Request $req)
    {
        $oversight_batch_id = $req->input('oversight_batch_id');
        try {
            $qry = DB::table('reconciliation_beneficiaries as t1')
                ->join('beneficiary_enrollments as t2', 't1.enrollment_id', '=', 't2.id')
                ->join('beneficiary_information as t3', 't2.beneficiary_id', '=', 't3.id')
                ->select(DB::raw('t1.id,t1.enrollment_id,t3.beneficiary_id as beneficiary_no,decrypt(t3.first_name) as first_name,decrypt(t3.last_name) as last_name,
                                  t3.id as girl_id,t3.school_id,t3.current_school_grade,t3.beneficiary_school_status,t2.school_fees,t1.confirmed_fees'))
                ->where('t1.oversight_batch_id', $oversight_batch_id);
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

    public function removeSelectedBeneficiariesReconciliationDetails(Request $req)
    {
        $selected = $req->input('selected');
        $enrollments = $req->input('enrollment_ids');
        $selected_ids = json_decode($selected);
        $enrollment_ids = json_decode($enrollments);
        try {
            DB::table('reconciliation_beneficiaries')
                ->whereIn('id', $selected_ids)
                ->delete();
            DB::table('beneficiary_enrollments')
                ->where('is_reconciliation', 1)
                ->whereIn('id', $enrollment_ids)
                ->delete();
            $res = array(
                'success' => true,
                'message' => 'Receipt details removed successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function updateReconciliationConfirmedFees(Request $req)
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
        $table_name = 'reconciliation_beneficiaries';
        try {
            foreach ($data as $key => $value) {
                $table_data = array(
                    'confirmed_fees' => $value['confirmed_fees'],
                    'updated_by' => $this->user_id,
                    'updated_at' => Carbon::now()
                );
                $update_main = array(
                    'beneficiary_school_status' => $value['beneficiary_school_status'],
                    'current_school_grade' => $value['current_school_grade']
                );
                DB::table('beneficiary_information')
                    ->where('id', $value['girl_id'])
                    ->update($update_main);
                logBeneficiaryGradeTransitioning($value['girl_id'], $value['current_school_grade'], $value['school_id'], $this->user_id);
                $where_data = array(
                    'id' => $value['id']
                );
                DB::table($table_name)
                    ->where($where_data)
                    ->update($table_data);
            }
            $res = array(
                'success' => true,
                'message' => 'Reconciliation Details Updated Successfully'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function confirmAndCloseReconciliationBatch(Request $req)
    {
        $oversight_batch_id = $req->input('oversight_batch_id');
        $school_id = $req->input('school_id');
        $payment_year = $req->input('payment_year');
        $payment_term = $req->input('payment_term');
        try {
            $checker = DB::table('reconciliation_beneficiaries')
                ->where('oversight_batch_id', $oversight_batch_id)
                ->whereNull('confirmed_fees')
                ->count();
            if ($checker > 0) {
                $res = array(
                    'success' => false,
                    'message' => 'Request rejected. Enter confirmed fees of all added beneficiaries!!'
                );
                return response()->json($res);
            }
            $update_params = array(
                'status_id' => 2,
                'updated_by' => $this->user_id,
                'updated_at' => Carbon::now()
            );
            $where = array(
                'id' => $oversight_batch_id
            );
            $table_name = 'reconciliation_oversight_batches';
            $prev_data = getPreviousRecords($table_name, $where);
            //Update suspense account first
            $this->updateSchoolSuspenseAccount($oversight_batch_id, $school_id, $payment_year, $payment_term);
            //Update batch info
            updateRecord($table_name, $prev_data, $where, $update_params, $this->user_id);
            $this->getReconciliationTransfers($oversight_batch_id, $school_id);
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

    public function updateSchoolSuspenseAccount($oversight_batch_id, $school_id, $payment_year, $payment_term)
    {
        try {
            $qry = DB::table('reconciliation_beneficiaries as t1')
                ->join('beneficiary_enrollments as t2', function ($join) use ($school_id, $payment_year, $payment_term) {
                    $join->on('t2.id', '=', 't1.enrollment_id')
                        ->on('t2.school_id', '=', DB::raw($school_id))
                        ->on('t2.term_id', '=', DB::raw($payment_term))
                        ->on('t2.year_of_enrollment', '=', DB::raw($payment_year));
                })
                ->select(DB::raw('(SUM(CASE WHEN t1.confirmed_fees IS NOT NULL THEN t1.confirmed_fees ELSE 0 END))-(SUM(CASE WHEN t2.school_fees IS NOT NULL THEN t2.school_fees ELSE 0 END)) as suspense_mount'))
                ->where('t1.oversight_batch_id', $oversight_batch_id)
                ->groupBy('t1.oversight_batch_id');
            $results = $qry->first();
            if (!is_null($results)) {
                $params = array(
                    'school_id' => $school_id,
                    'oversight_batch_id' => $oversight_batch_id,
                    'payment_year' => $payment_year,
                    'payment_term' => $payment_term,
                    'amount' => abs($results->suspense_mount),
                    'credit_debit' => $results->suspense_mount > 0 ? 1 : 2,
                    'created_by' => $this->user_id,
                    'created_at' => Carbon::now()
                );
                $table_name = 'reconciliation_suspense_account';
                insertRecord($table_name, $params, $this->user_id);
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'Problem updating suspense account, contact system Admin!!'
                );
                return response()->json($res);
            }
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
            return response()->json($res);
        }
    }

    public function getReconciliationTransfers($oversight_batch_id, $school_id)
    {
        $beneficiaries = DB::table('reconciliation_beneficiaries as t1')
            ->join('beneficiary_enrollments as t2', 't1.enrollment_id', '=', 't2.id')
            ->join('beneficiary_information as t3', 't2.beneficiary_id', '=', 't3.id')
            ->select('t3.id as girl_id', 't3.current_school_grade as grade', 't3.beneficiary_school_status as sch_status',
                't3.school_id as prev_school')
            ->where('oversight_batch_id', $oversight_batch_id)
            ->get();
        if (count($beneficiaries) > 0) {
            foreach ($beneficiaries as $beneficiary) {
                $this->doReconciliationTransfers($beneficiary->girl_id, $beneficiary->grade, $beneficiary->sch_status, $beneficiary->prev_school, $school_id);
            }
        }
    }

    public function doReconciliationTransfers($girl_id, $grade, $ben_school_status, $prev_school, $curr_school)
    {
        if ($prev_school != $curr_school) {
            $transfer_params = array(
                'girl_id' => $girl_id,
                'from_school_id' => $prev_school,
                'to_school_id' => $curr_school,
                'transfer_grade' => $grade,
                'transfer_school_status' => $ben_school_status,
                'reason_id' => 3,
                'effective_from_year' => date('Y'),
                'effective_from_term' => getSetCurrentTerm(),
                'transfer_type' => 2,
                'stage' => 3,
                'status' => 2,
                'request_by' => $this->user_id,
                'request_comment' => 'Correction during payment reconciliation',
                'requested_on' => Carbon::now(),
                'approval_by' => $this->user_id,
                'approval_comment' => 'Auto Approval',
                'approval_date' => Carbon::now(),
                'created_by' => $this->user_id,
                'source_module' => 'Reconciliation',
                'archived_by' => $this->user_id,
                'archive_comment' => 'Auto Archive',
                'archive_date' => Carbon::now()
            );
            insertRecord('beneficiary_school_transfers', $transfer_params, $this->user_id);
            DB::table('beneficiary_information')
                ->where('id', $girl_id)
                ->update(array('school_id' => $curr_school));
            logBeneficiaryGradeTransitioning($girl_id, $grade, $curr_school, $this->user_id);
        }
    }

    public function deleteReconciliationOversightBatch(Request $req)
    {
        $oversight_batch_id = $req->input('id');
        $res = array();
        DB::transaction(function () use (&$res, $oversight_batch_id) {
            try {
                DB::table('reconciliation_beneficiaries')
                    ->where('oversight_batch_id', $oversight_batch_id)
                    ->delete();
                DB::table('reconciliation_oversight_batches')
                    ->where('id', $oversight_batch_id)
                    ->delete();
                $res = array(
                    'success' => true,
                    'message' => 'Record deleted successfully!!'
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

    public function getReconciliationSummaries(Request $req)
    {
        try {
            $qry = DB::table('reconciliation_suspense_account as t1')
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                ->join('reconciliation_oversight_batches as t3', function ($join) {
                    $join->on('t3.id', '=', 't1.oversight_batch_id')
                        ->on('t3.school_id', '=', 't1.school_id')
                        ->on('t3.payment_term', '=', 't1.payment_term')
                        ->on('t3.payment_year', '=', 't1.payment_year');
                })
                ->join('districts as t4', 't2.district_id', '=', 't4.id')
                ->join('reconciliation_payment_types as t5', 't1.credit_debit', '=', 't5.id')
                ->select(DB::raw("t1.id, t2.name as school_name, t2.code as school_code, CONCAT_WS(' ',t2.code,t2.name) as school_codename,
                                  t3.id as oversight_batch_id,t1.payment_year,t1.payment_term,t1.school_id,t5.name as payment_type,t1.amount as reconciliation_amount,t3.ref_no,t4.name as school_district"));
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

    public function saveReconciliationRectificationDetails(Request $req)
    {
        $amount_transferred = $req->input('amount_transfered');
        $remark = $req->input('remarks');
        $rectification_id = $req->input('rectification_id');
        $oversight_batch_id = $req->input('oversight_batch_id');
        if ($amount_transferred > 5) {
            $params = $req->input();
        } else {
            $params = array(
                'oversight_batch_id' => $oversight_batch_id,
                'remarks' => $remark,
                'created_by' => $this->user_id,
                'created_at' => Carbon::now()
            );
        }
        $table_name = 'suspense_account_payments';
        try {
            if (isset($rectification_id) && $rectification_id != '') {
                $where = array(
                    'id' => $rectification_id
                );
                $prev_record = getPreviousRecords($table_name, $where);
                updateRecord($table_name, $prev_record, $where, $params, $this->user_id);
            } else {
                insertRecord($table_name, $params, $this->user_id);
            }
            DB::table('reconciliation_oversight_batches')
                ->where('id', $oversight_batch_id)
                ->update(array('status_id' => 3));
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

    public function removeSelectedSubmittedBeneficiaries(Request $req)
    {
        $selected = $req->input('selected');
        $selected_ids = json_decode($selected);
        $update_data = array(
            'submission_status' => 1
        );
        try {
            DB::table('beneficiary_enrollments')
                ->whereIn('id', $selected_ids)
                ->update($update_data);
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

    public function archiveActiveReconciliationBatch(Request $req)
    {
        $oversight_batch_id = $req->input('oversight_batch_id');
        $update_data = array(
            'is_achirved' => 1
        );
        try {
            DB::table('reconciliation_oversight_batches')
                ->where('id', $oversight_batch_id)
                ->update($update_data);
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

    public function getPaymentRequestsBatches(Request $req)
    {
        $year = $req->input('year');
        $term = $req->input('term');
        try {
            $qry = DB::table('payment_request_details as t1');
            if (isset($year) && $year != '') {
                $qry->where('payment_year', $year);
            }
            if (isset($term) && $term != '') {
                $qry->where('term_id', $term);
            }
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

    public function initializePaymentRequestPrintOut(Request $req)
    {
        $request_id = $req->input('request_id');
        try {
            DB::table('payment_request_details')
                ->where('id', $request_id)
                ->update(array('request_printed' => 1));
            $res = array(
                'success' => true,
                'message' => 'Request ready to print!!'
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

    public function initializeReconciliationSuspenseAccount1(Request $req)
    {
        $request_id = $req->input('request_id');
        try {
            $duplicates_exist = checkEnrollmentDuplicates($request_id);
            if ($duplicates_exist == true) {
                $res = array(
                    'success' => false,
                    'message' => 'Duplicates found, please process duplicates before attempting this action!!'
                );
                return response()->json($res);
            }
            $qry = DB::table('beneficiary_enrollments as t1')
                ->join('school_information as t2', 't2.id', '=', 't1.school_id')
                ->join('beneficiary_payment_records as t3', 't1.id', '=', 't3.enrollment_id')
                ->join('reconciliation_suspense_account as t4', function ($join) {
                    $join->on('t4.school_id', '=', 't2.id')
                        ->where(function ($query) {
                            $query->where('t4.payment_request_id', 0)
                                ->orWhereNull('t4.payment_request_id');
                        });
                })
                ->select('t2.id')
                ->where(array('t3.payment_request_id' => $request_id))
                ->groupBy('t2.id');
            $results = $qry->get();
            $results = convertStdClassObjToArray($results);
            $school_ids = convertAssArrayToSimpleArray($results, 'id');

            //update reconciliation suspense account and oversight batches
            $update_params = array(
                't1.payment_request_id' => $request_id,
                't2.payment_request_id' => $request_id,
                't2.status_id' => 3
            );
            DB::table('reconciliation_suspense_account as t1')
                ->join('reconciliation_oversight_batches as t2', 't1.oversight_batch_id', '=', 't2.id')
                ->whereIn('t1.school_id', $school_ids)
                ->where(function ($query) {
                    $query->where('t1.payment_request_id', 0)
                        ->orWhereNull('t1.payment_request_id');
                })
                ->update($update_params);
            //update payment request as reconciled
            DB::table('payment_request_details')
                ->where('id', $request_id)
                ->update(array('reconciled' => 1));
            $res = array(
                'success' => true,
                'message' => 'Accounts Reconciled Successfully!!'
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

    public function initializeReconciliationSuspenseAccount(Request $req)
    {
        $request_id = $req->input('request_id');
        try {
            $duplicates_exist = checkEnrollmentDuplicates($request_id);
            if ($duplicates_exist == true) {
                $res = array(
                    'success' => false,
                    'message' => 'Duplicates found, please process duplicates before attempting this action!!'
                );
                return response()->json($res);
            }
            //update payment request as reconciled
            DB::table('payment_request_details')
                ->where('id', $request_id)
                ->update(array('reconciled' => 1));
            $res = array(
                'success' => true,
                'message' => 'Accounts Reconciled Successfully!!'
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

    public function getPaymentDuplicateRecords(Request $req)
    {
        $request_id = $req->input('request_id');
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->join('beneficiary_enrollments as t2', 't1.id', '=', 't2.beneficiary_id')
                ->join('school_information as t3', 't2.school_id', '=', 't3.id')
                ->join(DB::raw("(SELECT t2.beneficiary_id,count(*) as c FROM beneficiary_payment_records 
                    as t1 inner join beneficiary_enrollments as t2
                    on t1.enrollment_id=t2.id where t1.payment_request_id = $request_id 
                    group by t2.beneficiary_id having c>1) AS p"), 'p.beneficiary_id', '=', 't1.id')
                ->join('beneficiary_payment_records as t4', 't2.id', '=', 't4.enrollment_id')
                ->select(DB::raw('t1.id as girl_id,t2.id,t4.payment_request_id,t2.school_id,
                    t1.beneficiary_id,decrypt(t1.first_name) as first_name,
                    decrypt(t1.last_name) as last_name, t3.name as school_name,
                    t2.school_fees'))
                ->where('t4.payment_request_id', $request_id);
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

    public function getEnrollmentDuplicateRecords(Request $req)
    {//GEWEL 2
        try {
            $batch_id = $req->input('batch_id');
            $year = $req->input('year');
            $qry = DB::table('beneficiary_information as t1')
                ->join('beneficiary_enrollments as t2', 't1.id', '=', 't2.beneficiary_id')
                ->join('school_information as t3', 't2.school_id', '=', 't3.id')
                ->leftJoin('beneficiary_payment_records as t4', 't2.id', '=', 't4.enrollment_id')
                ->join('payment_verificationbatch as t5', 't2.batch_id', '=', 't5.id')
                ->leftJoin('payment_request_details as t6', 't4.payment_request_id', '=', 't6.id')
                ->select(DB::raw("t1.id as girl_id,t2.id,t4.payment_request_id,
                    t2.school_id,t1.beneficiary_id,decrypt(t1.first_name) as first_name,
                    decrypt(t1.last_name) as last_name,t3.name as school_name,
                    decrypt(t2.annual_fees) as school_fees,t5.batch_no,t6.payment_ref_no,
                    t2.passed_rules,t2.is_validated,t2.submission_status,t2.batch_id,
                    t2.year_of_enrollment"))
                ->where(array(
                    't2.year_of_enrollment' => $year,
                    't2.is_validated' => 1,
                    't2.term_id' => 2
                ))
                ->whereIn('t2.beneficiary_id', function ($query) use ($batch_id, $year) {
                    $query->select(DB::raw('t2.beneficiary_id'))
                        ->from('beneficiary_information as t1')
                        ->join('beneficiary_enrollments as t2', 't1.id', '=', 't2.beneficiary_id')
                        ->join(DB::raw("(SELECT t1.beneficiary_id,count(*) as c FROM beneficiary_enrollments as t1 where t1.year_of_enrollment=
                            $year group by t1.beneficiary_id
                                 having c>1) AS p"), 'p.beneficiary_id', '=', 't1.id')
                        ->where(array(
                            't2.batch_id' => $batch_id,
                            't2.year_of_enrollment' => $year,
                            't2.term_id' => 2
                        ));
                    })
                ->where('t2.batch_id', '<>', $batch_id);
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'duplicate_count' => count($results),
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

    public function getEnrollmentDuplicateCount(Request $req)
    {
        try {//GEWEL 2
            $batch_id = $req->input('batch_id');
            $year = $req->input('year');
            $qry = DB::table('beneficiary_information as t1')
                ->join('beneficiary_enrollments as t2', 't1.id', '=','t2.beneficiary_id')
                ->join(DB::raw("(SELECT t1.beneficiary_id,count(*) as c 
                    FROM beneficiary_enrollments as t1 
                    where t1.year_of_enrollment=$year and t1.term_id = 2
                    group by t1.beneficiary_id having c>1) AS p"), 
                    'p.beneficiary_id', '=', 't1.id')
                ->where('t2.batch_id', $batch_id)
                ->where('t2.year_of_enrollment', $year)
                ->where('t1.batch_id', '<>', $batch_id)
                ->where('t2.term_id', 2);
            $count = $qry->count();
            $res = array(
                'success' => true,
                'duplicate_count' => 0,
                // 'duplicate_count' => $count,
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


    public function removeSelectedDuplicatedEnrollmentsForDataEntry(Request $req)
    {
        $selected_ids = $req->input('selected');
        $selected_enrollment_ids = json_decode($selected_ids);
        $user_id = $this->user_id;       
        try{
            $log_params = DB::table('beneficiary_enrollments AS t1')
            ->select(DB::raw("t1.*,$user_id as deleted_by"))
            ->whereIn('id', $selected_enrollment_ids)
            ->get();
        
            $log_params = convertStdClassObjToArray($log_params);
            DB::table('payment_duplicates_processing')->insert($log_params);
            DB::table('beneficiary_enrollments')
            ->whereIn('id', $selected_enrollment_ids)
            ->delete();
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
    public function removeSelectedDuplicatedEnrollments(Request $req)
    {
        $selected_ids = $req->input('selected');
        $payment_request_id = $req->input('payment_request_id');
        $selected_enrollment_ids = json_decode($selected_ids);
        $user_id = $this->user_id;
        try {
            //log deletion first
            $log_params = DB::table('beneficiary_enrollments AS t1')
                ->select(DB::raw("t1.*,$payment_request_id as payment_request_id,$user_id as deleted_by"))
                ->whereIn('id', $selected_enrollment_ids)
                ->get();
            $log_params = convertStdClassObjToArray($log_params);
            DB::table('payment_duplicates_processing')->insert($log_params);
            DB::table('beneficiary_payment_records')
                ->whereIn('enrollment_id', $selected_enrollment_ids)
                ->delete();
            DB::table('beneficiary_enrollments')
                ->whereIn('id', $selected_enrollment_ids)
                ->delete();
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

    public function removeIndividualDuplicatedEnrollment(Request $req)
    {
        $enrollment_id = $req->input('enrollment_id');
        $payment_request_id = $req->input('payment_request_id');
        $user_id = $this->user_id;
        try {
            //log deletion first
            $log_params = DB::table('beneficiary_enrollments AS t1')
                ->select(DB::raw("t1.*,$payment_request_id as payment_request_id,$user_id as deleted_by"))
                ->where('id', $enrollment_id)
                ->get();
            $log_params = convertStdClassObjToArray($log_params);
            DB::table('payment_duplicates_processing')->insert($log_params);
            DB::table('beneficiary_payment_records')
                ->where('enrollment_id', $enrollment_id)
                ->delete();
            DB::table('beneficiary_enrollments')
                ->where('id', $enrollment_id)
                ->delete();
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

    public function removeValidationDuplicatedEnrollments(Request $req)
    {
        try {
            $selected_ids = $req->input('selected');
            $selected_enrollment_ids = json_decode($selected_ids);
            $user_id = $this->user_id;
            //log deletion first
            $log_params = DB::table('beneficiary_enrollments AS t1')
                ->select(DB::raw("t1.*,$user_id as deleted_by"))
                ->whereIn('id', $selected_enrollment_ids)
                ->get();
            $log_params = convertStdClassObjToArray($log_params);
            DB::table('enrollment_duplicates_processing')->insert($log_params);
            DB::table('beneficiary_payment_records')
                ->whereIn('enrollment_id', $selected_enrollment_ids)
                ->delete();
            DB::table('beneficiary_enrollments')
                ->whereIn('id', $selected_enrollment_ids)
                ->delete();
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

    public function addExternalBeneficiaryForReconciliation(Request $req)
    {
        $postdata = $req->input();
        $school_id = $req->input('school_id');
        $oversight_batch_id = $req->input('oversight_batch_id');
        $year = $req->input('year');
        $term = $req->input('term');
        $user_id = \Auth::user()->id;
        unset($postdata['_token']);
        unset($postdata['school_id']);
        unset($postdata['oversight_batch_id']);
        unset($postdata['year']);
        unset($postdata['term']);
        $res = array();

        DB::transaction(function () use (&$res, $postdata, $user_id, $year, $term, $school_id, $oversight_batch_id) {
            //check for any reconciliation batch
            $where_check = array(
                'year_of_enrollment' => $year,
                'term_id' => $term,
                'school_id' => $school_id,
                'is_reconciliation' => 1
            );
            $exists = DB::table('payment_verificationbatch')
                ->where($where_check)
                ->first();
            if (is_null($exists)) {
                $batch_no = generatePaymentverificationBatchNo(1);
                $district_id = getSingleRecordColValue('school_information', array('id' => $school_id), 'district_id');
                $batch_details = array(
                    'batch_no' => $batch_no,
                    'year_of_enrollment' => $year,
                    'term_id' => $term,
                    'added_by' => $user_id,
                    'added_on' => Carbon::now(),
                    'school_id' => $school_id,
                    'created_by' => $user_id,
                    'is_reconciliation' => 1,
                    'status_id' => 7,
                    'district_id' => $district_id,
                    'submitted_on' => Carbon::now()
                );
                $batch_id = insertReturnID('payment_verificationbatch', $batch_details);
            } else {
                $batch_id = $exists->id;
            }

            foreach ($postdata as $value) {
                try {
                    $girl_id = $value['id'];
                    $do_transfer = $value['do_transfer'];
                    $confirmed_fees = $value['confirmed_fees'];
                    $is_allowed = $this->isReconciliationTransferAllowed($girl_id, $year, $term);
                    if ($is_allowed == 1) {
                        $is_duplicate = $this->checkDoubleReconAddition($year, $term, $girl_id);
                        if ($is_duplicate == true) {
                            $res = array(
                                'success' => false,
                                'message' => 'Not allowed. The beneficiary has been added for the payment period!!'
                            );
                        } else {
                            $enrollment_params = array(
                                'beneficiary_id' => $girl_id,
                                'school_id' => $school_id,
                                'year_of_enrollment' => $year,
                                'term_id' => $term,
                                'school_grade' => $value['current_school_grade'],
                                'beneficiary_schoolstatus_id' => $value['beneficiary_school_status'],
                                'enrollment_status_id' => 1,
                                'school_fees' => 0,
                                'batch_id' => $batch_id,
                                'has_signed' => 1,
                                'is_validated' => 1,
                                'submission_status' => 1,
                                'passed_rules' => 1,
                                'created_by' => $user_id,
                                'is_reconciliation' => 1
                            );
                            $enrollment_id = insertReturnID('beneficiary_enrollments', $enrollment_params);
                            $reconciliation_params = array(
                                'oversight_batch_id' => $oversight_batch_id,
                                'enrollment_id' => $enrollment_id,
                                'confirmed_fees' => $confirmed_fees,
                                'do_transfer' => $do_transfer,
                                'created_by' => $user_id
                            );
                            insertRecord('reconciliation_beneficiaries', $reconciliation_params, $user_id);
                            $res = array(
                                'success' => true,
                                'message' => 'Details saved successfully!!'
                            );
                        }
                    } else if ($is_allowed == 2) {
                        $res = array(
                            'success' => false,
                            'message' => 'Not allowed. Start by deducting from the previous school!!'
                        );
                    } else if ($is_allowed == 3) {
                        $res = array(
                            'success' => false,
                            'message' => 'Not allowed. You have to \'Close & Confirm\' the reconciliation details of the previous school!!'
                        );
                    } else if ($is_allowed == 4) {
                        $res = array(
                            'success' => false,
                            'message' => 'Not allowed. From the previous school, confirmed amount should be 0.00!!'
                        );
                    }
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

    public function isReconciliationTransferAllowed($girl_id, $year, $term)
    {
        $where = array(
            't1.beneficiary_id' => $girl_id,
            't1.year_of_enrollment' => $year,
            't1.term_id' => $term
        );
        //first check if the girl was enrolled
        $count = DB::table('beneficiary_enrollments as t1')
            ->join('beneficiary_payment_records as t2', 't1.id', '=', 't2.enrollment_id')
            ->select('t1.id', 't1.school_fees')
            ->where($where)
            ->where('is_reconciliation', '<>', 1)
            ->get();
        if (count($count) > 0) {
            //check if the girl has the amount deducted in the other enrollment
            $countAssArr = convertStdClassObjToArray($count);
            $countSimpArr = convertAssArrayToSimpleArray($countAssArr, 'id');
            $exists = DB::table('reconciliation_beneficiaries')
                ->whereIn('enrollment_id', $countSimpArr)
                ->count();
            if ($exists < 1) {
                return 2;
            }
            foreach ($count as $counter) {
                $captured_details = DB::table('reconciliation_beneficiaries as t1')
                    ->join('reconciliation_oversight_batches as t2', 't1.oversight_batch_id', '=', 't2.id')
                    ->where('enrollment_id', $counter->id)
                    ->select('t2.status_id', 't1.confirmed_fees')
                    ->first();
                if (!is_null($captured_details)) {
                    if ($captured_details->status_id != 2) {
                        return 3;
                    }
                    if ($captured_details->confirmed_fees > 0) {
                        return 4;
                    }
                }
            }
        }
        return 1;
    }

    public function checkDoubleReconAddition($year, $term, $girl_id)
    {
        $where = array(
            'beneficiary_id' => $girl_id,
            'year_of_enrollment' => $year,
            'term_id' => $term,
            'is_reconciliation' => 1
        );
        $count = DB::table('beneficiary_enrollments as t1')
            ->where($where)
            ->count();
        if ($count > 0) {
            return true;
        }
        return false;
    }

    public function doDeleteBenEnrollmentAnomaly(Request $request)
    {
        try {
            $batch_id = $request->input('batch_id');
            $enrollment_id = $request->input('enrollment_id');
            $details = DB::table('beneficiary_enrollments')
                ->where(array('id' => $enrollment_id, 'batch_id' => $batch_id))
                ->first();
            if (!is_null($details)) {
                $res = array(
                    'success' => false,
                    'message' => 'Action not allowed!! The beneficiary is still in this batch, kindly correct the anomalies!'
                );
            } else {
                DB::table('enrollment_error_log')
                    ->where(array('enrollment_id' => $enrollment_id, 'batch_id' => $batch_id))
                    ->delete();
                $res = array(
                    'success' => true,
                    'message' => 'Details deleted successfully!!'
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

    public function getSignedConsentUploadForms(Request $request)
    {
        try {
            $enrolment_id = $request->input('enrolment_id');
            $qry = DB::table('signedconsent_formsuploads as t1')
                ->where('enrolment_id', $enrolment_id);
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

    public function uploadSignedConsentForms(Request $request)
    {
        try {
            $enrolment_id = $request->input('enrolment_id');
            if ($request->hasFile('uploaded_files')) {
                $totalFiles = count($_FILES['uploaded_files']['name']);
                $destinationPath = public_path('storage/signedconsentforms_uploads/');
                $upload_details = array();

                for ($i = 0; $i < $totalFiles; $i++) {
                    //Get the temp file path
                    $tmpFilePath = $_FILES['uploaded_files']['tmp_name'][$i];
                    //Make sure we have a file path
                    if ($tmpFilePath != "") {
                        $fileName = $_FILES["uploaded_files"]["name"][$i];
                        $tmp = explode('.', $fileName);
                        $extension = end($tmp);
                        $newFileName = Str::random('6') . '.' . $extension;
                        if (move_uploaded_file($_FILES["uploaded_files"]["tmp_name"][$i], $destinationPath . $newFileName)) {
                            $upload_details[] = array(
                                'enrolment_id' => $enrolment_id,
                                'initial_name' => $_FILES['uploaded_files']['name'][$i],
                                'file_size' => $_FILES['uploaded_files']['size'][$i],
                                'file_type' => $_FILES['uploaded_files']['type'][$i],
                                'saved_name' => $newFileName,
                                'server_filepath' => url('/') . '/backend/public/storage/signedconsentforms_uploads/',
                                'server_filedirectory' => public_path('storage/signedconsentforms_uploads/')
                            );
                        }
                    }
                }
                DB::table('signedconsent_formsuploads')
                    ->insert($upload_details);
            }
            $res = array(
                'success' => true,
                'message' => "Request executed successfully!"
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

    public function exportPaymentRecords(Request $request)
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

     public function getSchoolPaymentVariances(Request $req)
    {
        try{
            $payment_request_id= $req->input('record_id');  
            $payment_year=DB::table('payment_request_details')->where('id',$payment_request_id)->value('payment_year');
            $qry="SELECT t1.school_id,decrypt(t1.amount_transfered) as disbursed_fees,t2.name as school_name FROM  payment_disbursement_details as t1
            INNER JOIN school_information AS t2 ON t2.id=t1.school_id WHERE payment_request_id=$payment_request_id";
             $results=db::select($qry);
            // dd($results);
    
             foreach($results as $key=>$result)
             {
                $qry2="SELECT SUM(t1.receipt_amount) as receipt_amount FROM beneficiary_receipting_details AS  t1 WHERE payment_receipt_id IN (
                    SELECT t4.id FROM beneficiary_enrollments AS t2 INNER JOIN 
                             beneficiary_payment_records AS t3 ON t3.enrollment_id=t2.id
                             INNER JOIN  payments_receipting_details AS t4 ON t2.id=t4.enrollment_id 
                                WHERE t2.school_id=$result->school_id) ";
                                //dd($qry2);
                 $results2=db::select($qry2);
                 if(is_array($results2) && count($results2)>0)
                 {
                    $result->receipted_fees=$results2[0]->receipt_amount;
                    $results[$key]=$result;
                 }
    
    
    
                 $qry2=Db::table('mne_datacollectiontool_dataentry_basicinfo as t1')
                ->join('mne_unstructuredquizes_dataentryinfo as t2','t2.record_id','t1.id')
                ->selectraw('SUM(t2.response) as response_amount,t1.school_id')
                ->where(['t1.datacollection_tool_id'=>1,'year_id'=> $payment_year,'t1.school_id'=>$result->school_id])//6075,6114
            ->whereIn('question_id',[215,216])
            ->groupby('t1.school_id');
                $results3=$qry2->first();
               
                $result->varied_amount=$result->disbursed_fees-$result->receipted_fees;
                if(is_object( $results3))
                {
                    $school_monitoring_varied_amount=$result->disbursed_fees-$results3->response_amount;
                    $result->school_monitoring_varied_amount=$school_monitoring_varied_amount;
                    $result->response_amount=$results2->response_amount;
                }
                $result->school_monitoring_varied_amount="";
                $result->response_amount="";
               
                $results[$key]=$result;
    
                
             }  
             $res = array(
                'success' => true,
                'message' => returnMessage($results),
                'results' => $results
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
//begin first old grant list code
    private function UploadBulkSQLData($data_to_save,$table_name)
   {
    $number_of_records=count($data_to_save);
    $limit=1500;
    if($number_of_records>$limit && $number_of_records>0)
    {
        $total_loop=ceil($number_of_records/$limit);
        $start_index=0;
        $end_index=$limit;
        for($i=1;$i<=$total_loop;$i++)
        {
            $results_to_insert=array();
            foreach($data_to_save as $key=>$result)
            {
                if($key>=$start_index && $key<=$end_index)
                {
                    $results_to_insert[]=$result;
                }
            }
            
            DB::table($table_name)->insert($results_to_insert);
            $results_to_insert=array();
            if($i!=$total_loop-1){
                $start_index=$end_index+1;
                $end_index=$start_index+$limit;
                }else{
                    $start_index=$end_index+1;
                    $end_index=($number_of_records-1);
            }

        }
    }else{
       DB::table($table_name)->insert($data_to_save);
    }
    if($number_of_records>0){
        return true;
    }
    return false;
   }







 public function uploadPaymentGrantList3($table_data,$payment_year,$normalTimeLimit)
    {
       // dd($table_data);
      
        try{
        $res=array();
        $table_data_insert=array();
        $invalid_entries=[];
        //validate beneficiary
        $updated_ben=[];
        $problems=array();
        $toavoid=false;
        $bouncedGirls=array();
        foreach($table_data as $key=>$beneficiary)
        {
            $the_key=$key+1;
            if(in_array($beneficiary[0],$updated_ben))
            {
                $problems[]="Information for beneficiary with ID ". $beneficiary[0]." has already been uploaded. Please remove the duplicate at 
                   row". $the_key;
                   $toavoid=true;
               // return response()->json([
                   // "success"=>false,
                  // "message"=> "Information for beneficiary with ID ". $beneficiary[0]." has already been uploaded. Please remove the duplicate at 
                  // row". $the_key
               // ]);
            }
           $is_available=1;
           if($beneficiary[0]!=="" && $beneficiary[0]!=null){
           $is_available=Db::table('beneficiary_information')->where('beneficiary_id',$beneficiary[0])->count();
           }
          
           if($is_available!=1 )
           {
             $toavoid=true;
             $problems[]="No beneficiary with ID" .$beneficiary[0]." exists at 
                   row". $the_key;
           // return response()->json([
              //  "success"=>false,
              // "message"=> "No beneficiary with ID" .$beneficiary[0]." exists at 
                //   row". $the_key
           // ]);


           }
           if($beneficiary[13]==null || ltrim(rtrim($beneficiary[13]))=="")
           {
             $toavoid=true;
             //$problems[]= "No information has been specified for Grant Recieved at Row " .$the_key;
            //return response()->json([
              //  "success"=>false,
              //  "message"=> "No information has been specified for Grant Recieved at Row " .$the_key
           // ]);
           }
           $grant_recieved="";
           $acceptable_grant_values=["yes","no"];
           if(in_array( rtrim(ltrim(strtolower($beneficiary[13]))),$acceptable_grant_values) && $toavoid==false)
           {
                $index_of_val=array_search(strtolower(rtrim(ltrim($beneficiary[13]))),$acceptable_grant_values);
               switch($index_of_val)
               {
                   case 0:
                    $grant_recieved=1;
                    break;
                   case 2:
                    $grant_recieved=0;
                    break;
               }
               
            $updated_ben[]=$beneficiary[0];
             $grant_for_year_captured=Db::table('payments_beneficiaries_grant')->where(
               array(
                "beneficiary_id"=>$beneficiary[0],
                "payment_year"=>$payment_year,
               )
           )->count();
           if($grant_for_year_captured==0){
           $table_data_insert[]=array(
               "beneficiary_id"=>$beneficiary[0],
               "grant_recieved"=> $grant_recieved,
               "created_at"=>carbon::now(),
               "created_by"=>$this->user_id,
               "payment_year"=>$payment_year

           );

           $resultofinsert=Db::table('payments_beneficiaries_grant')->insert(array(
               "beneficiary_id"=>$beneficiary[0],
               "grant_recieved"=> $grant_recieved,
               "created_at"=>carbon::now(),
               "created_by"=>$this->user_id,
               "payment_year"=>$payment_year

           ));
         
           if($resultofinsert=!true)
           {
            $bouncedGirls[]="Failed for for beneficiary at " .$the_key;
           }

       }else{
        $problems[]="Grant details already captured for beneficiary at " .$the_key;
         $toavoid=true;
       }
           }else{
             $toavoid=true;
             if($toavoid==false){
             $problems[]="Invalid reponse given for Grant Recieved at Row " .$the_key;
    
             }
           
           }

            $toavoid=false;
          

        }
         if(count($problems)>0)
        {
           $res = array(
            'success' => false,
            'message' => "There were problems encountered in uploading the grant list"
        );
        return $res;  
        }
        $uploadresult=false;
        if(count($bouncedGirls)<1)
        {

        ini_set('max_execution_time', $normalTimeLimit); //Job on 03/11/2022
            $uploadresult=true;
            $res = array(
            'success' => true,
            'message' => "Grant List has been Uploaded"
        );
        }else{
            ini_set('max_execution_time', $normalTimeLimit); //Job on 03/11/2022
            $res = array(
            'success' => false,
            'message' => "The grant list was partially uploaded"
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
    ini_set('max_execution_time', $normalTimeLimit); //Job on 03/11/2022
    return response()->json($res);
    }



//new job18/8/2024
    public function uploadPaymentGrantListForSuspension(Request $request)
    {
        try {

            // Get default limit
            $normalTimeLimit = ini_get('max_execution_time'); //Job on 18/10/2022

            // Set new limit
            ini_set('max_execution_time', 0); //Job on 18/10/2022
            $res = array();
            $table_data = array();
            $payment_year = $request->input('year');
            if ($request->hasFile('upload_file')) {
                $origFileName = $request->file('upload_file')->getClientOriginalName();
                if (validateExcelUpload($origFileName)) {
                    $data = Excel::toArray([], $request->file('upload_file'));
                    if (count($data) > 0) {
                        $table_data = $data[0];
                    }
                } else {
                    $res = array(
                        "success" => false,
                        "message" => "File Type Invalid"
                    );
                }
            }

            /*** suspend girls code **/
          
            $girl_ben_ids=array();
            //dd($table_data);
              foreach ($table_data as $key => $girl) {

                 if ($key > 0) {
                    //dump("heell");
                //dump($girl);
                //dump($girl[0]);
                    //dump($girl);
                  $girl_ben_ids[]=$girl[0];
                  //dd( $girl[0]);
                 }
              }
             // dd($girl_ben_ids);
            $girl_ids = array();
            $double_count_girls = array();
            foreach ($girl_ben_ids as $key => $girl) {
                if ($key >= 0) {
                    $girl_ids[] = $girl;
                    //code to suspend
                    $record = DB::table('beneficiary_information as t1')
                        ->where("t1.beneficiary_id", $girl)
                        ->first();


                    if ($record) {
                        $record_id = $record->id;
                    } else {
                        $record_id = 0;
                    }
                    $previous_data = getPreviousRecords("beneficiary_information", ["beneficiary_id" => $girl]);


                    // CREATE TABLE `beneficiary_information_prev_status_august_18_2024` (
                    //     `beneficiary_id` INT(10) NULL DEFAULT NULL,
                    //     `prev_kgs_status` INT(10) NULL DEFAULT NULL,
                    //     `kgs_status` INT(10) NULL DEFAULT NULL,
                    //     `created_at` DATETIME NULL DEFAULT NULL,
                    //     `created_by` INT(10) NULL DEFAULT NULL,
                    //     `updated_at` DATETIME NULL DEFAULT NULL,
                    //     `updated_by` INT(10) NULL DEFAULT NULL
                    // )
                    // COLLATE='utf8mb4_0900_ai_ci'
                    // ENGINE=InnoDB
                    // ;



                   

                   $res= updateRecord(
                        "beneficiary_information",
                        $previous_data,
                        ["beneficiary_id" => $girl],
                        [
                            "enrollment_status" => 2, //suspend
                            "updated_at" => carbon::now(),
                            "updated_by" => $this->user_id
                        ],
                        $this->user_id
                    );
                   //dd($res);

                    Db::table("beneficiary_information_prev_status_august_18_2024")->updateOrInsert([
                        "beneficiary_id" => $girl,
                    ], [
                        "beneficiary_id" => $girl,
                        "prev_enrollment_status" => $record->enrollment_status ?? 0,
                        "enrollment_status" => 2,
                        "enrollment_status_text" => $res['success'],
                        "created_at" => carbon::now(),
                        "created_by" => $this->user_id,
                        "updated_at" => carbon::now(),
                        "updated_by" => $this->user_id,
                    ]);

                          $girl_data_enrollments =  Db::table('beneficiary_enrollments as t5')
                        ->Join("beneficiary_payment_records as t8", 't5.id', '=', 't8.enrollment_id')
                        ->where(
                            [
                                "t5.beneficiary_id" => $record_id,
                                "t8.payment_request_id" => 48
                            ]
                        )->select("t5.id")->get();
                    if (count($girl_data_enrollments) > 1) {
                        $double_count_girls[] = $girl;
                    } else {
                    
                        if (count($girl_data_enrollments) > 0) {
                            $girl_data_payments =  Db::table('beneficiary_payment_records as t8')
                                ->where([
                                    "t8.enrollment_id" => $girl_data_enrollments[0]->id,
                                    "t8.payment_request_id" => 48
                                ])->get();
                            // dd("hey");
                            $previous_data = getPreviousRecords("beneficiary_payment_records", [
                                "enrollment_id" => $girl_data_enrollments[0]->id,
                                "payment_request_id" => 48
                            ]);
                            deleteRecord("beneficiary_payment_records", $previous_data, [
                                "enrollment_id" => $girl_data_enrollments[0]->id,
                                "payment_request_id" => 48
                            ], $this->user_id);
                            $girl_data_payments = convertStdClassObjToArray($girl_data_payments[0]);
                            //create_backup
                            // CREATE TABLE `deleted_beneficiary_payment_records_august_18_2024` (
                            //     `id` INT(10) NOT NULL ,
                            //     `enrollment_id` INT(10) NOT NULL,
                            //     `payment_request_id` INT(10) NOT NULL,
                            //     `created_at` DATETIME NOT NULL,
                            //     `created_by` INT(10) NOT NULL,
                            //     `updated_by` INT(10) NOT NULL,
                            //     `updated_at` DATETIME NOT NULL,
                            //     PRIMARY KEY (`id`) USING BTREE,
                            //     INDEX `enrollment_id` (`enrollment_id`) USING BTREE,
                            //     INDEX `payment_request_id` (`payment_request_id`) USING BTREE
                            // )
                            // COMMENT='holds the payment details for the paid for girls \r\n\r\n'
                            // COLLATE='latin1_swedish_ci'
                            // ENGINE=InnoDB
                            // ;
                            Db::table("deleted_beneficiary_payment_records_august_18_2024")->insert($girl_data_payments);
                        }
                        //dd($girl_data_payments);
                    }


                    // dd($record);
                    // $record = DB::table('beneficiary_information as t1')
                    //     ->join('beneficiary_enrollments as t5', 't1.id', '=', 't5.beneficiary_id')
                    //     ->join('beneficiary_payment_records as t8', 't5.id', '=', 't8.enrollment_id')
                    //     ->where("t8.payment_request_id", 48)
                    //     ->where("t1.beneficiary_id", $girl[0])->get();

                }
            }

            ini_set('max_execution_time', $normalTimeLimit); //Job on 18/10/2022
            dd($double_count_girls);

            dd($girl_ids);
            /***end suspend */

            

            $headings = $table_data[1];
            $valid_headings = [
                "Beneficiary Id",
                "Beneficiary Name",
                "Payment Grade",
                "Current Grade",
                "Current Status",
                "School Status",
                "School",
                "Home District",
                "Home Province",
                "School District",
                "CWAC",
                "House Hold NRC No",
                "House Hold Name",
                "SCT MIS ID",
                "Grant Received"
            ];
            $invalid_headings = [];
            foreach ($headings as $key => $heading) {
                if (!in_array($heading, $valid_headings) && $key < 11) {
                    $invalid_headings[] = $heading;
                }
            }
            if (count($invalid_headings) > 0) {
                if (count($invalid_headings) == 1) {
                    $message = "Invalid heading " . implode(",", $invalid_headings);
                } else {
                    $message = "Invalid headings :" . implode(",", $invalid_headings);
                }
                ini_set('max_execution_time', $normalTimeLimit); //Job on 18/10/2022
                return response()->json([
                    "success" => false,
                    "message" => $message
                ]);
            }
            //unset headings now we have pure asset data
            unset($table_data[0]);
            unset($table_data[1]);

            $invalid_entries = [];
            //validate beneficiary
            $updated_ben = [];
            $new_table_data = [];
            $beneficiaries_update_data = [];
            $already_captured_ben_for_year = false;
            $grant_list_limit = Db::table('payments_grant_list_limit')->where('id', 1)->value('grant_list_limit');
            if (count($table_data) > $grant_list_limit) {
                return $this->uploadPaymentGrantList3($table_data);
                ini_set('max_execution_time', $normalTimeLimit); //Job on 18/10/2022
                return response()->json([
                    "success" => false,
                    "message" => "Number of records uploaded  exceed limit. Number of records should not exceed  $grant_list_limit",
                ]);
            }
            foreach ($table_data as $key => $beneficiary) {

                $the_key = $key + 1;
                if (in_array($beneficiary[0], $updated_ben)) {
                    ini_set('max_execution_time', $normalTimeLimit); //Job on 18/10/2022
                    return response()->json([
                        "success" => false,
                        "message" => "Duplicate beneficiary ID found at Row " . $the_key,

                    ]);
                }
                $is_available = Db::table('payment_grant_list_log')->where(['payment_year' => $payment_year, "ben_id" => $beneficiary[0]])->count();
                if ($is_available < 1) {
                    ini_set('max_execution_time', $normalTimeLimit); //Job on 18/10/2022
                    return response()->json([
                        "success" => false,
                        "message" => "No beneficiary with ID " . $beneficiary[0] . " exists for payment year"
                    ]);
                }

                if ($beneficiary[14] == null || ltrim(rtrim($beneficiary[14])) == "") {
                    ini_set('max_execution_time', $normalTimeLimit); //Job on 18/10/2022
                    return response()->json([
                        "success" => false,
                        "message" => "No information has been specified for Grant Recieved at Row " . $the_key
                    ]);
                }
                $grant_recieved = "";
                $acceptable_grant_values = ["yes", "no"];
                if (in_array((strtolower(rtrim(ltrim($beneficiary[14])))), $acceptable_grant_values)) {
                    $index_of_val = array_search(strtolower(rtrim(ltrim($beneficiary[0]))), $acceptable_grant_values);
                    switch ($index_of_val) {
                        case 0:
                            $grant_recieved = 1;
                            break;
                        case 2:
                            $grant_recieved = 0;
                            break;
                    }
                } else {
                    ini_set('max_execution_time', $normalTimeLimit); //Job on 18/10/2022
                    return response()->json([
                        "success" => false,
                        "message" => "Invalid reponse given for Grant Recieved at Row " . $the_key
                    ]);
                }
                $updated_ben[] = $beneficiary[0];
                $grant_for_year_captured = Db::table('payments_beneficiaries_grant')->where(
                    array(
                        "beneficiary_id" => $beneficiary[0],
                        "payment_year" => $payment_year,
                    )
                )->count();
                if ($grant_for_year_captured == 0) {
                    $new_table_data[] = array(
                        "beneficiary_id" => $beneficiary[0],
                        "grant_recieved" => $grant_recieved,
                        "payment_year" => $payment_year,
                        "created_at" => carbon::now(),
                        "created_by" => $this->user_id,
                    );
                } else {
                    $already_captured_ben_for_year = true;
                    $beneficiaries_update_data[] = array(
                        "beneficiary_id" => $beneficiary[0],
                        "grant_recieved" => $grant_recieved,
                        "payment_year" => $payment_year,
                        "created_at" => carbon::now(),
                        "created_by" => $this->user_id,
                    );
                }
            }

            if (count($new_table_data) > 0) {

                $number_of_records = count($new_table_data);
                $limit = 1500;
                if ($number_of_records > $limit && $number_of_records > 0) {
                    $total_loop = ceil($number_of_records / $limit);
                    $start_index = 0;
                    $end_index = $limit - 1;
                    for ($i = 1; $i <= $total_loop; $i++) {
                        $results_to_insert = array();
                        foreach ($new_table_data as $key => $result) {
                            if ($key >= $start_index && $key <= $end_index) {
                                DB::table("payments_beneficiaries_grant")->insert($results_to_insert);
                                $results_to_insert[] = $result;
                            }
                        }

                        DB::table("payments_beneficiaries_grant")->insert($results_to_insert);
                        $results_to_insert = array();
                        if ($i != $total_loop - 1) {
                            $start_index = $end_index + 1;
                            $end_index = $start_index + $limit;
                        } else {
                            $start_index = $end_index + 1;
                            $end_index = ($number_of_records - 1);
                        }
                    }
                } else {
                    Db::table('payments_beneficiaries_grant')->insert($new_table_data);
                }


                if ($already_captured_ben_for_year == false) {
                    $res = array(
                        'success' => true,
                        'message' => "Grant List Uploaded Successfully"
                    );
                } else {

                    foreach ($beneficiaries_update_data as $update_data_for_beneficiary) {
                        Db::table('payments_beneficiaries_grant')->where(
                            array(
                                "beneficiary_id" => $update_data_for_beneficiary['beneficiary_id'],
                                "payment_year" => $payment_year
                            )
                        )->update(['grant_recieved' => $update_data_for_beneficiary['grant_recieved']]);
                    }
                    $res = array(
                        'success' => true,
                        'message' => "Previously not uploaded beneficiaries data has been successfully saved"
                    );
                }
            } else {
                $res = array(
                    'success' => true,
                    'message' => "All records already up-to date"
                );
            }
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'line'=>$exception->getLine(),
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        ini_set('max_execution_time', $normalTimeLimit); //Job on 18/10/2022
        return response()->json($res);
    }



//uploadPaymentGrantListForOldGrantListGeneration Active  function
public function uploadPaymentGrantList(Request $request)
    {

//return $this->DeleteGirlsfromPaymentRequest($request);

        try{
    // Get default limit
    $normalTimeLimit = ini_get('max_execution_time');//Job on 18/10/2022

    // Set new limit
      ini_set('max_execution_time',30000); //Job on 18/10/2022
        $res=array();
        $table_data=array();
        $payment_year=$request->input('year');
        if ($request->hasFile('upload_file')) {
            $origFileName = $request->file('upload_file')->getClientOriginalName();

            if (validateExcelUpload($origFileName)) {
                $data=Excel::toArray([],$request->file('upload_file'));
                if(count($data)>0){
                $table_data=$data[0];
             }
            }else{
                $res=array(
                    "success"=>false,
                    "message"=>"File Type Invalid"
                );
            }
        }




         /***
             * 
             * this code came later to suspend further rules
             */

            // $girls = DB::table("deleted_beneficiary_payment_records_august_18_2024")->get();
            // $girl_data_array = array();
            // foreach ($girls as $girl) {

            //     $previous_data = getPreviousRecords("beneficiary_enrollments", ["id" => $girl->enrollment_id]);
            //     Db::table("changed_beneficiary_info_with_suspension_records_august_18_2024")->insert(
            //         [
            //             "enrollment_id" => $girl->enrollment_id,
            //             "has_signed" => $previous_data[0]['has_signed'],
            //             "passed_rules" =>  $previous_data[0]['passed_rules'],
            //             "is_validated" =>  $previous_data[0]['is_validated'],
            //             "created_at" => carbon::now(),
            //             "created_by" => $this->user_id,
            //             "updated_at" => carbon::now(),
            //             "updated_by" => $this->user_id,
            //         ]
            //     );

            //     $res = updateRecord(
            //         "beneficiary_enrollments",
            //         $previous_data,
            //         ["id" => $girl->enrollment_id],
            //         [
            //             "has_signed" => 0,
            //             "passed_rules" => 0,
            //             "is_validated" => 0,
            //         ],
            //         $this->user_id
            //     );
            //     $girl_data_array[] = $girl->enrollment_id;
            // }
               /***
             * 
             *  End of this code that came later to suspend further rules
             */
            // dd($girl_data_array);
      
           
        //$headings=$table_data[1];
        $headings=$table_data[0];
        $valid_headings=["Beneficiary Id","Beneficiary Name","Payment Grade","Current Grade","Current Status","School Status","School", "Home District",  "Home Province","School District","CWAC",
        "House Hold NRC No","House Hold Name","SCT MIS ID","Grant Received"];
        $invalid_headings=[];
        foreach($headings as $key=>$heading)
        {
            if(!in_array($heading,$valid_headings) && $key<11)
            {
                $invalid_headings[]=$heading;
            }
            
        }
        if(count($invalid_headings)<0)
        {
            if(count($invalid_headings)==1){
            $message="Invalid heading ". implode(",",$invalid_headings);
            }else{
            $message="Invalid headings :". implode(",",$invalid_headings);
            }
            ini_set('max_execution_time', $normalTimeLimit); //Job on 18/10/2022
            return response()->json([
                "success"=>false,
               "message"=> $message
            ]);
        }
      
        //unset headings now we have pure asset data
        unset($table_data[0]);
       // unset($table_data[1]);//to reinstate
     //dd($table_data);
        $invalid_entries=[];
        //validate beneficiary
        $updated_ben=[];
        $new_table_data=[];
        $beneficiaries_update_data=[];
        $already_captured_ben_for_year=false;
        $grant_list_limit=Db::table('payments_grant_list_limit')->where('id',1)->value('grant_list_limit');
        return $this->uploadPaymentGrantList3($table_data,$payment_year,$normalTimeLimit);


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
//Job 3/10/2024
 public function DeleteGirlsfromPaymentRequest(Request $request)
    {


  $normalTimeLimit = ini_get('max_execution_time'); //Job on 18/10/2022


        ini_set('max_execution_time', 0); //Job on 18/10/2022


  $records = Db::table("deleted_beneficiary_payment_records_september_30_2024")->select("*")->get();

       $girl_ids=[];
        foreach ($records as $record) {
          

            $previous_data = getPreviousRecords("beneficiary_payment_records", [
                "enrollment_id" => $record->enrollment_id,
                "payment_request_id" => 48
            ]);
            if($previous_data){
             $girl_ids[]=$record;
              deleteRecord("beneficiary_payment_records", $previous_data, [
             "enrollment_id" => $record->enrollment_id,
             "payment_request_id" => 48
            ], $this->user_id);
           // $girl_data_payments = convertStdClassObjToArray($record);
           

           // Db::table("deleted_beneficiary_payment_records_september_30_2024")->insert($girl_data_payments);


            }
            //dd($previous_data);

        ini_set('max_execution_time', $normalTimeLimit); //Job on 18/10/2022
          

           
          
        }
        dd($girl_ids);
     
        /**** suspend girls code */

        /***
         * 
         * this code came later to suspend further rules
         */

        $girls = DB::table("deleted_beneficiary_payment_records_august_18_2024")->get();
      
        $girl_data_array = array();
        foreach ($girls as $girl) {

            $previous_data = getPreviousRecords("beneficiary_enrollments", ["id" => $girl->enrollment_id]);
            Db::table("changed_beneficiary_info_with_suspension_records_august_18_2024")->insert(
                [
                    "enrollment_id" => $girl->enrollment_id,
                    "has_signed" => $previous_data[0]['has_signed'],
                    "passed_rules" =>  $previous_data[0]['passed_rules'],
                    "is_validated" =>  $previous_data[0]['is_validated'],
                    "created_at" => carbon::now(),
                    "created_by" => $this->user_id,
                    "updated_at" => carbon::now(),
                    "updated_by" => $this->user_id,
                ]
            );

            $res = updateRecord(
                "beneficiary_enrollments",
                $previous_data,
                ["id" => $girl->enrollment_id],
                [
                    "has_signed" => 0,
                    "passed_rules" => 0,
                    "is_validated" => 0,
                ],
                $this->user_id
            );
            $girl_data_array[] = $girl->enrollment_id;
        }


        ini_set('max_execution_time', $normalTimeLimit); //Job on 18/10/2022
        dd("done");

        
    }
//end old grant list code
    //
 //public function uploadPaymentGrantListForNewListsToBeReturnedAFterUploa,1/19/2022
 public function uploadPaymentGrantListForNew(Request $request)
    {

        try{
    // Get default limit
    $normalTimeLimit = ini_get('max_execution_time');//Job on 18/10/2022

    // Set new limit
      ini_set('max_execution_time', 0); //Job on 18/10/2022
        $res=array();
        $table_data=array();
        $payment_year=$request->input('year');
        if ($request->hasFile('upload_file')) {
            $origFileName = $request->file('upload_file')->getClientOriginalName();
            if (validateExcelUpload($origFileName)) {
                $data=Excel::toArray([],$request->file('upload_file'));
                if(count($data)>0){
                $table_data=$data[0];
             }
            }else{
                $res=array(
                    "success"=>false,
                    "message"=>"File Type Invalid"
                );
            }
        }
            
        $headings=$table_data[1];
        $valid_headings=["Beneficiary Id","Beneficiary Name","Payment Grade","Current Grade","Current Status","School Status","School", "Home District",  "Home Province","School District","CWAC",
        "House Hold NRC No","House Hold Name","SCT MIS ID","Grant Received"];
        $invalid_headings=[];
        foreach($headings as $key=>$heading)
        {
            if(!in_array($heading,$valid_headings) && $key<11)
            {
                $invalid_headings[]=$heading;
            }
            
        }
        if(count($invalid_headings)>0)
        {
            if(count($invalid_headings)==1){
            $message="Invalid heading ". implode(",",$invalid_headings);
            }else{
            $message="Invalid headings :". implode(",",$invalid_headings);
            }
            ini_set('max_execution_time', $normalTimeLimit); //Job on 18/10/2022
            return response()->json([
                "success"=>false,
               "message"=> $message
            ]);
        }
        //unset headings now we have pure asset data
        unset($table_data[0]);
        unset($table_data[1]);
       
        $invalid_entries=[];
        //validate beneficiary
        $updated_ben=[];
        $new_table_data=[];
        $beneficiaries_update_data=[];
        $already_captured_ben_for_year=false;
        $grant_list_limit=Db::table('payments_grant_list_limit')->where('id',1)->value('grant_list_limit');
        if(count($table_data)> $grant_list_limit)
        {
            ini_set('max_execution_time', $normalTimeLimit); //Job on 18/10/2022
            return response()->json([
                "success"=>false,
                "message"=>"Number of records uploaded  exceed limit. Number of records should not exceed  $grant_list_limit",
            ]);

        }
        foreach($table_data as $key=>$beneficiary)
        {
           
            $the_key=$key+1;
            if(in_array($beneficiary[0],$updated_ben))
            {
                ini_set('max_execution_time', $normalTimeLimit); //Job on 18/10/2022
                return response()->json([
                    "success"=>false,
                    "message"=>"Duplicate beneficiary ID found at Row ".$the_key,
                 
                ]);
            }
           $is_available= Db::table('payment_grant_list_log')->where(['payment_year'=>$payment_year,"ben_id"=>$beneficiary[0]])->count();
           if($is_available<1)
           {
            ini_set('max_execution_time', $normalTimeLimit); //Job on 18/10/2022
            return response()->json([
                "success"=>false,
               "message"=> "No beneficiary with ID " .$beneficiary[0]." exists for payment year"
            ]);
           }
        
           if($beneficiary[14]==null || ltrim(rtrim($beneficiary[14]))=="")
           {
            ini_set('max_execution_time', $normalTimeLimit); //Job on 18/10/2022
            return response()->json([
                "success"=>false,
                "message"=> "No information has been specified for Grant Recieved at Row " .$the_key
            ]);
           }
           $grant_recieved="";
           $acceptable_grant_values=["yes","no"];
           if(in_array( (strtolower(rtrim(ltrim($beneficiary[14])))),$acceptable_grant_values))
           {
                $index_of_val=array_search(strtolower(rtrim(ltrim($beneficiary[0]))),$acceptable_grant_values);
               switch($index_of_val)
               {
                   case 0:
                    $grant_recieved=1;
                    break;
                   case 2:
                    $grant_recieved=0;
                    break;
               }
               
           }else{
            ini_set('max_execution_time', $normalTimeLimit); //Job on 18/10/2022
            return response()->json([
                "success"=>false,
                "message"=> "Invalid reponse given for Grant Recieved at Row " .$the_key
            ]);
           }
           $updated_ben[]=$beneficiary[0];
           $grant_for_year_captured=Db::table('payments_beneficiaries_grant')->where(
               array(
                "beneficiary_id"=>$beneficiary[0],
                "payment_year"=>$payment_year,
               )
           )->count();
           if($grant_for_year_captured==0){
             $new_table_data[]=array(
               "beneficiary_id"=>$beneficiary[0],
               "grant_recieved"=> $grant_recieved,
               "payment_year"=>$payment_year,
               "created_at"=>carbon::now(),
               "created_by"=>$this->user_id,
            );
            }else{
                $already_captured_ben_for_year=true;
                $beneficiaries_update_data[]=array(
                    "beneficiary_id"=>$beneficiary[0],
                    "grant_recieved"=> $grant_recieved,
                    "payment_year"=>$payment_year,
                    "created_at"=>carbon::now(),
                    "created_by"=>$this->user_id,
                 );
            }
        }
       
        if(count($new_table_data)>0)
        {

            $number_of_records=count($new_table_data);
            $limit=1500;
            if($number_of_records>$limit && $number_of_records>0)
            {
                $total_loop=ceil($number_of_records/$limit);
                $start_index=0;
                $end_index=$limit-1;
                for($i=1;$i<=$total_loop;$i++)
                {
                    $results_to_insert=array();
                    foreach($new_table_data as $key=>$result)
                    {
                        if($key>=$start_index && $key<=$end_index)
                        {
                            DB::table("payments_beneficiaries_grant")->insert($results_to_insert);
                            $results_to_insert[]=$result;
                        }
                    }
                  
                    DB::table("payments_beneficiaries_grant")->insert($results_to_insert);
                    $results_to_insert=array();
                    if($i!=$total_loop-1){
                        $start_index=$end_index+1;
                        $end_index=$start_index+$limit;
                        }else{
                            $start_index=$end_index+1;
                            $end_index=($number_of_records-1);
                    }
    
                }
            }else{
                Db::table('payments_beneficiaries_grant')->insert($new_table_data);
    
            }
           
          
            if($already_captured_ben_for_year==false)
            {
                $res = array(
                    'success' => true,
                    'message' => "Grant List Uploaded Successfully"
                );

            }else{

                foreach($beneficiaries_update_data as $update_data_for_beneficiary)
                {
                    Db::table('payments_beneficiaries_grant')->where(array( "beneficiary_id"=>$update_data_for_beneficiary['beneficiary_id'],
                            "payment_year"=>$payment_year)
                    )->update(['grant_recieved'=>$update_data_for_beneficiary['grant_recieved']]);
                }
                $res = array(
                    'success' => true,
                    'message' => "Previously not uploaded beneficiaries data has been successfully saved"
                );
            }
        }else{
            $res = array(
                'success' => true,
                'message' => "All records already up-to date"
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
    ini_set('max_execution_time', $normalTimeLimit); //Job on 18/10/2022
    return response()->json($res);
    
    }

     public function deletePaymentRequest(Request $req){
        $post_data = $req->all();
        $payment_request_id = $post_data['request_id'];

         $table_name = 'payment_request_details';
        // $payment_disbursement_id = $req->input('payment_disbursement_id');
        // $payment_request_id = $req->input('payment_request_id');
        $where_data = array(
            'id' => $payment_request_id,
           
        );

        $qry= DB::table('beneficiary_enrollments as t1')
        ->join('payment_verificationbatch as t2','t2.id','=','t1.batch_id')
        ->join('beneficiary_information as t3','t1.beneficiary_id','=','t3.id')
        ->leftjoin('districts as t4', 't2.district_id', '=', 't4.id')
        ->join('beneficiary_payment_records as t5','t5.enrollment_id','=','t1.id')
        ->where('t5.payment_request_id',$payment_request_id)
        
       ->selectRaw('t1.id');//payment validation
       $results = convertStdClassObjToArray($qry->get());
     
       if(count($results)>0)
       {
       return  $res = array(
            'success' => false,
            'message' =>"Sorry, You cant delete the payment request"
        );

       }
       

        try {
            $qry = DB::table($table_name)
                ->where($where_data)
                ->count();
            if ($qry > 0) {
               
               $payment_year=Db::table("payment_request_details")->where("id", $payment_request_id)->value("payment_year");
                $where_data = array(
                    'year' => $payment_year
                );
                $serial_data = DB::table("payrequest_serial_nos")
            ->where($where_data)
            ->value('serial_no');
           
            $serial_no =$serial_data-1;
            $current_data = array(
                'serial_no' => $serial_no,
                'updated_at' => date('Y-m-d H:i:s')
            );
           
          $previous_data = getPreviousRecords("payrequest_serial_nos", $where_data);
            updateRecord("payrequest_serial_nos", $previous_data, $where_data, $current_data, $this->user_id);
             $where_data = array(
            'id' => $payment_request_id,
           
        );
            $previous_data = getPreviousRecords($table_name, $where_data);
            deleteRecord($table_name, $previous_data, $where_data, $this->user_id);
            }
                
                
            $res = array(
                'success' => true,
                'message' => 'Payment Request details removed successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

     public function getPpmApppaymentConsolidations(Request $req)
    {
        try {
            $year = $req->input('year');
            $qry = DB::table('sa_app_beneficiary_list as t1')
                ->leftJoin(
                    'beneficiary_transaction_status as t2',
                    DB::raw('t1.transaction_id COLLATE utf8mb4_unicode_ci'),
                    '=',
                    DB::raw('t2.transaction_id COLLATE utf8mb4_unicode_ci')
                )
                ->leftJoin('payment_request_details as t3', 't2.payment_request_id', '=', 't3.id')
                ->leftJoin('beneficiary_information as t4', 't2.beneficiary_no', '=', 't4.beneficiary_id')
                ->select(DB::raw("
            COUNT(t1.beneficiary_no) as no_of_beneficiaries,
            SUM(t1.school_fees) as total_grant,
            t2.payment_status,
            t3.payment_ref_no,
            COUNT(DISTINCT t1.school_name) as no_of_schools,
            DATE_FORMAT(t1.transaction_time_initiated, '%Y') as transaction_year,
            t2.school_accountant_details"))
                ->where('t2.payment_status', 'PAID')
                ->whereNotNull('t2.payment_request_id')
                ->groupBy('t3.payment_ref_no')
                ->orderBy('transaction_year', 'DESC');
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

    public function getPpmAppSchoolpaymentschoolSummary(Request $req)
    {
        try {

            // $transaction_year=$req->input('transaction_year');
            $payment_ref_no = $req->input('payment_ref_no');
            $payment_status = 'PAID';
            $qry = DB::table('sa_app_beneficiary_list as t1')
                ->leftJoin(
                    'beneficiary_transaction_status as t2',
                    DB::raw('t1.transaction_id COLLATE utf8mb4_unicode_ci'),
                    '=',
                    DB::raw('t2.transaction_id COLLATE utf8mb4_unicode_ci')
                )
                ->leftJoin('beneficiary_information as t3', 't1.beneficiary_no', '=', 't3.beneficiary_id')
                ->leftJoin('school_information as t4', 't3.school_id', '=', 't4.id')
                ->leftJoin('payment_request_details as t5', 't2.payment_request_id', '=', 't5.id')
                ->select(
                    't1.beneficiary_no',
                    't1.school_name',
                    't1.school_fees',
                    't2.payment_status',
                    't5.payment_ref_no',
                    't1.transaction_time_initiated',
                    't2.school_accountant_details',
                    't4.code as sort_code',
                    DB::raw('COUNT(*) as girls_per_school')
                )
                ->where('t2.payment_status', $payment_status)
                ->groupBy('t1.school_name');
            // ->where(DB::raw("DATE_FORMAT(t1.transaction_time_initiated, '%Y')"), '=', $year)
            // if(isset($transaction_year) && $transaction_year !== ""){
            //     $qry->whereYear('t1.transaction_time_initiated',$transaction_year);
            // }
            if (isset($payment_ref_no) && $payment_ref_no !== "") {
                $qry->where('t5.payment_ref_no', $payment_ref_no);
            }
            $result = $qry->get();
            $res = array(
                'success' => true,
                'results' => $result,
                'message' => returnMessage($result)
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getPpmAppBeneficiary_requestpaymentInfo(Request $req)
    {
        try {

            
            $payment_ref_no = $req->input('payment_ref_no');
            $payment_status = 'PAID';
            $school_name = $req->input('school_name');
            $qry = DB::table('sa_app_beneficiary_list as t1')
                ->leftJoin(
                    'beneficiary_transaction_status as t2',
                    DB::raw('t1.transaction_id COLLATE utf8mb4_unicode_ci'),
                    '=',
                    DB::raw('t2.transaction_id COLLATE utf8mb4_unicode_ci')
                )
                ->leftJoin('beneficiary_information as t3', 't1.beneficiary_no', '=', 't3.beneficiary_id')
                ->leftJoin('school_information as t4', 't3.school_id', '=', 't4.id')
                ->leftJoin('districts as t5', 't4.district_id', '=', 't5.id')
                ->leftJoin('school_enrollment_statuses as t6', 't3.school_enrollment_status', '=', 't6.id')
                ->leftJoin('payment_request_details as t7', 't2.payment_request_id', '=', 't7.id')
                ->select(
                    't1.beneficiary_no',
                    't1.school_name',
                    't1.school_fees',
                    't1.payment_status',
                    't1.transaction_time_initiated',
                    't2.school_accountant_details',
                    't4.code as sort_code',
                    't3.first_name',
                    't3.last_name',
                    't7.payment_ref_no',
                    't3.dob',
                    't5.name as school_district',
                    't3.current_school_grade as school_grade',
                    't6.name as school_status',
                    DB::raw('YEAR(t3.enrollment_date) as year_of_enrollment'),

                )
                ->where('t2.payment_status', $payment_status);

            // if(isset($transaction_year) && $transaction_year !== ""){
            //     $qry->whereYear('t1.transaction_time_initiated',$transaction_year);
            // }
            if (isset($payment_ref_no) && $payment_ref_no !== "") {
                $qry->where('t7.payment_ref_no', $payment_ref_no);
            }
            if (isset($school_name) && $school_name !== "") {
                $qry->whereYear('t1.school_name', $school_name);
            }
            $result = $qry->get();
            $res = array(
                'success' => true,
                'results' => $result,
                'message' => returnMessage($result)
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getImagesData(Request $req)
    {
        try {
            $qry = DB::table('beneficiary_images_ppm as t1')
                ->leftJoin('beneficiary_information as t2', 't1.beneficiary_number', '=', 't2.beneficiary_id')
                ->leftJoin('school_information as t4','t2.school_id','=','t4.id')
                ->selectRaw('t2.id,t2.school_id,t1.beneficiary_number,t1.image_id,t1.other,t1.images_converted, t1.beneficiary_image,t1.guardian_image');
            $results = $qry->get();
            $hard_path = "\\backend\\public";

            foreach ($results as $beneficiary) {
                $any_other_image = $beneficiary->other ? $beneficiary->other : null;
                $beneficiary_image = $beneficiary->beneficiary_image ? $beneficiary->beneficiary_image : null;
                $guardian_image = $beneficiary->guardian_image ? $beneficiary->guardian_image : null;
                $beneficiary_number = $beneficiary->beneficiary_number;
                $beneficiary_id = $beneficiary->id;
                $school_id = $beneficiary->school_id;
                $image_id=$beneficiary->image_id;
                $images_converted = $beneficiary->images_converted ? $beneficiary->images_converted : null;
                if ($images_converted == 0) {
                    $img_update = [];

                    if ($any_other_image && preg_match('/^data:image\/(\w+);base64,/', $any_other_image) 
                    || base64_encode(base64_decode($any_other_image, true)) === $any_other_image) {
                        $disclaimer_url = "img" . date('YmdHis') .uniqid(). "." . "png";
                        $consent_hard_url = $hard_path . '\\img\\ppmimages\\' . $disclaimer_url;
                        $consent_url = public_path() . '\\img\\ppmimages\\' . $disclaimer_url;
                        // $consent_url = public_path('img/ppmimages/' . $disclaimer_url);
                        $consent_file = base64_decode($any_other_image);
                        if (!$consent_file) {
                            throw new \Exception("Invalid base64 image data for beneficiary {$beneficiary_number}");
                        }
                        $conversion_response = \Image::make($consent_file)->save($consent_url);
                        $img_update['other'] = $disclaimer_url;
                        if ($img_update) {
                            $img_update['images_converted'] = 1;
                            // $img_update['disclaimer_form'] = '';
                            DB::table('beneficiary_payresponses_staging_clone')
                                ->insert(
                                    array(

                                        'beneficiary_id' => $beneficiary_id,
                                        'school_id' => $school_id,
                                        'image_type' => 6,
                                        'image_name' => $consent_hard_url,
                                        'created_at'=>Carbon::now(),
                                    )
                                );
                        }
                    }
                    if ($beneficiary_image && preg_match('/^data:image\/(\w+);base64,/', $beneficiary_image) 
                    || base64_encode(base64_decode($beneficiary_image, true)) === $beneficiary_image) {
                        $disclaimer_url = "img" . date('YmdHis') .uniqid(). "." . "png";
                        $consent_hard_url = $hard_path . '\\img\\ppmimages\\' . $disclaimer_url;
                        $consent_url = public_path() . '\\img\\ppmimages\\' . $disclaimer_url;
                        // $consent_url = public_path('img/ppmimages/' . $disclaimer_url);
                        $consent_file = base64_decode($beneficiary_image);
                        if (!$consent_file) {
                            throw new \Exception("Invalid base64 image data for beneficiary {$beneficiary_number}");
                        }
                        $conversion_response = \Image::make($consent_file)->save($consent_url);
                        $img_update['beneficiary_image'] = $disclaimer_url;
                        if ($img_update) {
                            $img_update['images_converted'] = 1;
                            // $img_update['disclaimer_form'] = '';
                            DB::table('beneficiary_payresponses_staging_clone')
                                ->insert(
                                    array(

                                        'beneficiary_id' => $beneficiary_id,
                                        'school_id' => $school_id,
                                        'image_type' => 1,
                                        'image_name' => $consent_hard_url,
                                        'created_at'=>Carbon::now(),
                                    )
                                );
                        }
                    }
                    if ($guardian_image && preg_match('/^data:image\/(\w+);base64,/', $guardian_image) 
                    || base64_encode(base64_decode($guardian_image, true)) === $guardian_image) {
                        $disclaimer_url = "img" . date('YmdHis') .uniqid(). "." . "png";
                        $consent_hard_url = $hard_path . '\\img\\ppmimages\\' . $disclaimer_url;
                        $consent_url = public_path() . '\\img\\ppmimages\\' . $disclaimer_url;
                        // $consent_url = public_path('img/ppmimages/' . $disclaimer_url);
                        $consent_file = base64_decode($guardian_image);
                        if (!$consent_file) {
                            throw new \Exception("Invalid base64 image data for beneficiary {$beneficiary_number}");
                        }
                        $conversion_response = \Image::make($consent_file)->save($consent_url);
                        $img_update['guardian_image'] = $disclaimer_url;
                        if ($img_update) {
                            $img_update['images_converted'] = 1;
                            // $img_update['disclaimer_form'] = '';
                            DB::table('beneficiary_payresponses_staging_clone')
                                ->insert(
                                    array(

                                        'beneficiary_id' => $beneficiary_id,
                                        'school_id' => $school_id,
                                        'image_type' => 5,
                                        'image_name' => $consent_hard_url,
                                        'created_at'=>Carbon::now(),
                                    )
                                );
                        }
                    }

                    if ($img_update) {
                        DB::table('beneficiary_images_ppm')
                            ->where('beneficiary_number', $beneficiary->beneficiary_number)
                            ->where('image_id', $beneficiary->image_id)
                            ->update($img_update);
                    }
                }
            }

            $payment_ref_no = $req->input('payment_ref_no');
            $payment_status = 'PAID';
            $fin_qry = DB::table('beneficiary_uploadfiles_staging as t1')
                ->leftJoin('beneficiary_payresponses_staging_clone as t2', 't2.image_type', '=', 't1.id')

                ->leftJoin('beneficiary_information as t3', 't2.beneficiary_id', '=', 't3.id')
                ->leftJoin('beneficiary_images_ppm as t4', 't4.beneficiary_number', '=', 't3.beneficiary_id')
                ->leftJoin('beneficiary_transaction_status as t5','t4.beneficiary_number','=','t5.beneficiary_no')
                ->leftJoin('school_information as t6','t3.school_id','=','t6.id')
                ->leftJoin('payment_request_details as t7', 't5.payment_request_id', '=', 't7.id')

                ->selectRaw('t2.id,t2.beneficiary_id,t2.image_type,
                        t3.school_id,t7.payment_ref_no,t5.payment_status,   
                        t1.file_name as image_name,t2.image_name as image_file,t2.image_name as image_view,
                        CONCAT(t3.first_name," ",t3.last_name) AS full_name')
                ->where('t5.payment_status', $payment_status);
            if (isset($payment_ref_no) && $payment_ref_no !== "") {
                $fin_qry->where('t7.payment_ref_no', $payment_ref_no);
                
            }

            $fin_results = $fin_qry->get();

            $res = array(
                'success' => true,
                'results' => $fin_results,
                'message' => 'All is well'
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
    public function saveOfflinePaymentVerificationDetails(Request $req) {        
        try {
            if (Auth::check()) {
                if($this->user_id !=0) {
                    if($this->dms_id==0) {
                        $res = array(
                            'success' => false,
                            'message' => "Invalid DMS credentials, please contact system Admin"
                        );
                        return response()->json($res);
                    }
                } else {
                    $res = array(
                        'success' => false,
                        'message' => "user is not authenticated,Kindly Reload system"
                    );
                    return response()->json($res);
                }
            } else {
                $res = array(
                    'success' => false,
                    'message' => "User is not authenticated,Kindly Reload system"
                );
                return response()->json($res);            
            }
            $post_data = $req->all();
            $batch_id = $post_data['batch_id'];
            $term_id = $post_data['term_id'] ?? '';
            $school_id = $post_data['school_id'];
            $batch_no = $post_data['batch_no'];
            $checklistissued_by = $post_data['checklistissued_by'];
            $checklistissued_on = $post_data['checklistissued_on'];
            $year_of_enrollment = $post_data['year_of_enrollment'];
            $checklist_form_id = $post_data['checklist_form_id'];
            $school_headteacher = $req->input('school_headteacher');
            $headteacher_tel_no = $req->input('headteacher_tel_no');
            $school_guidance_teacher_name = $req->input('guidance_counselling_teacher');
            $school_guidance_teacher_phone = $req->input('guidance_counselling_teacher_phone_no');
            $where_data = array(
                'designation_id' => 1,
                'school_id' => $school_id
            );
            $table_data = array(
                'designation_id' => 1,
                'school_id' => $school_id,
                'full_names' => $school_headteacher,
                'telephone_no' => $headteacher_tel_no,
                'mobile_no' => $headteacher_tel_no
            );
            $this->funSaveschoolotherdetails('school_contactpersons', $table_data, $where_data);
            //job  16/3/2022
            //school guidance teacher info
            $where_data = array(
                'designation_id' => 2,//school guidance designation
                'school_id' => $school_id
            );
            $table_data = array(
                'designation_id' => 2,
                'school_id' => $school_id,
                'full_names' =>   $school_guidance_teacher_name,
                'telephone_no' =>  $school_guidance_teacher_phone,
                'mobile_no' =>  $school_guidance_teacher_phone
            );
            $result = $this->funSaveschoolotherdetails('school_contactpersons', $table_data, $where_data);
            //end
            //school information
            $school_type_id = $req->input('school_type_id');
            $district_id = $req->input('district_id');
            $cwac_id = $req->input('cwac_id');
            $running_agency_id =$req->input('running_agency_id');
            $table_data = array(
                'district_id' => $district_id,
                'cwac_id' => $cwac_id,
                'school_type_id' => $school_type_id,
                "running_agency_id"=> $running_agency_id
            );
            $where_data = array('id' => $school_id);
            $this->funSaveschoolotherdetails('school_information', $table_data, $where_data);

            //job 0n 29/03/2022
            if($running_agency_id==3) {//private               
                $grades=[8,9,10,11,12];
                foreach($grades as $school_grade) {
                    $table_name = 'school_feessetup';
                    //1. Day
                    $table_data = array(
                        'year' => date('Y'),
                        'school_enrollment_id' => 1,
                        'school_id' => $school_id,
                        'grade_id' => $school_grade,
                        'term1_fees' => 0,
                        'term2_fees' => 0,
                        'term3_fees' => 0
                    );
                    $where_data = array(
                        'year' => date('Y'),
                        'school_enrollment_id' => 1,
                        'school_id' => $school_id,
                        'grade_id' => $school_grade
                    );
                    $this->funcUpdateschoolFees($table_name, $where_data, $table_data);
                    //1. boarder
                    $table_data = array(
                        'year' => date('Y'),
                        'school_enrollment_id' => 1,
                        'school_id' => $school_id,
                        'grade_id' => $school_grade,
                        'term1_fees' => 0,
                        'term2_fees' => 0,
                        'term3_fees' => 0
                    );
                    $where_data = array(
                        'year' => date('Y'),
                        'school_enrollment_id' => 2,
                        'school_id' => $school_id,
                        'grade_id' => $school_grade
                    );
                    $this->funcUpdateschoolFees($table_name, $where_data, $table_data);
                    //1. weekly 
                    $table_data = array(
                        'year' => date('Y'),
                        'school_enrollment_id' => 3,
                        'school_id' => $school_id,
                        'grade_id' => $school_grade,
                        'term1_fees' => 0,
                        'term2_fees' => 0,
                        'term3_fees' => 0
                    );
                    $where_data = array(
                        'year' => date('Y'),
                        'school_enrollment_id' => 3,
                        'school_id' => $school_id,
                        'grade_id' => $school_grade
                    );
                    $this->funcUpdateschoolFees($table_name, $where_data, $table_data);
                }
            }
            //school bank details
            $branch_name = $req->input('branch_name');
            $sort_code = $req->input('sort_code');
            $account_no = $req->input('account_no');
            $bank_id = $req->input('bank_id');
            $where_data = array(
                'school_id' => $school_id
            );
            $table_data = array(
                'school_id' => $school_id,
                'bank_id' => $bank_id,
                'account_no' => aes_encrypt($account_no),
                'branch_name' => $branch_name,
                'is_activeaccount' => 1
            );
            $this->funSaveschoolotherdetails('school_bankinformation', $table_data, $where_data);
            //bank branch sort code
            $where_data = array('id' => $branch_name);
            $table_data = array(
                'sort_code' => $sort_code
            );
            $this->funSaveschoolotherdetails('bank_branches', $table_data, $where_data);

            $additional_remarks = $post_data['additional_remarks'];
            $folder_id = $post_data['folder_id'];
            $district_id = $post_data['district_id'];
            $table_name = 'payment_verificationbatch';
            $table_data = array(
                'school_id' => $school_id,
                'term_id' => $term_id,
                'submitted_on' => Carbon::now(),
                'submitted_by' => $this->user_id,
                'checklistissued_by' => $checklistissued_by,
                'checklistissued_on' => $checklistissued_on,
                'additional_remarks' => $additional_remarks,
                'district_id' => $district_id,
                'year_of_enrollment' => $year_of_enrollment,
                'checklist_form_id' => $checklist_form_id
            );
            if (validateisNumeric($batch_id)) {
                $where = array('id' => $batch_id);
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $this->user_id;
                $is_same_school = DB::table('payment_verificationbatch')
                    ->where(array('id' => $batch_id, 'school_id' => $school_id))
                    ->count(); 
                $where_prev = array(
                    'batch_id' => $batch_id
                );
                $prev_data = getPreviousRecords('beneficiary_enrollments', $where_prev);
                

                if($checklist_form_id) {
                    $ben_data = DB::table('payment_checklists_track_details as t0')
                        ->join('beneficiary_information as t1', 't0.girl_id', '=', 't1.id')
                        ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                        ->join('districts as t3', 't2.district_id', '=', 't3.id')
                        ->join('provinces as t4', 't3.province_id', '=', 't4.id')
                        ->select('t1.*')
                        ->where(array('t1.school_id' => $school_id, 't1.beneficiary_status' => 4, 't1.enrollment_status' => 1))
                        ->where('t1.payment_eligible', 1)
                        ->where('t0.track_id', $checklist_form_id)
                        ->where(function ($query) {
                            $query->where('t1.under_promotion', 0)
                                ->orWhereNull('t1.under_promotion');
                        })->get();
                } else {            
                    $ben_data = DB::table('beneficiary_payresponses_staging_clone as t0')
                        ->join('beneficiary_information as t1', 't0.id', '=', 't1.id')
                        ->selectRaw('t0.id,t0.confirmed_grade as current_school_grade,
                                t0.beneficiary_schoolstatus_id as beneficiary_school_status')
                        ->where(array('t0.school_id' => $school_id))
                        ->whereRaw("t0.verification_status = 'pending'")
                        ->get();
                }
                foreach ($ben_data as $ben_datum) {
                    $school_fees = getAnnualSchoolFees($school_id, $ben_datum->beneficiary_school_status, $ben_datum->current_school_grade, $year_of_enrollment);
                    $annual_fees = ($school_fees['term1_fees'] + $school_fees['term2_fees'] + $school_fees['term3_fees']);
                    $table_data = array(
                        'beneficiary_id' => $ben_datum->id,
                        'school_id' => $school_id,
                        'year_of_enrollment' => $year_of_enrollment,
                        'term_id' => $term_id,
                        'school_grade' => $ben_datum->current_school_grade,
                        'beneficiary_schoolstatus_id' => $ben_datum->beneficiary_school_status,
                        'enrollment_status_id' => 0,
                        'term1_fees' => aes_encrypt($school_fees['term1_fees']),
                        'term2_fees' => aes_encrypt($school_fees['term2_fees']),
                        'term3_fees' => aes_encrypt($school_fees['term3_fees']),
                        'annual_fees' => aes_encrypt($annual_fees),
                        'batch_id' => $batch_id,
                        'has_signed' => 0
                    );
                    $where = array(
                        'beneficiary_id' => $ben_datum->id,
                        'school_id' => $school_id,
                        'year_of_enrollment' => $year_of_enrollment,
                        //'term_id' => $term_id
                    );
                        $table_data['created_at'] = Carbon::now();
                        $table_data['created_by'] = $this->user_id;
                        $resp = insertRecordReturnId('beneficiary_enrollments', $table_data, $this->user_id);
                }

            } else {
                //check if checklist form id has been captured b4
                $checkDetails = DB::table($table_name)->where('checklist_form_id', $checklist_form_id)->first();
                $batch_no = generatePaymentverificationBatchNo();
                $parent_id = 4;
                $main_module_id = 1;
                $folder_id = createDMSParentFolder($parent_id, $main_module_id, $batch_no, '', $this->dms_id);;

                $table_data['created_at'] = Carbon::now();
                $table_data['created_by'] = $this->user_id;
                $table_data['batch_no'] = $batch_no;
                $table_data['folder_id'] = $folder_id;
                $table_data['added_by'] = $this->user_id;
                $table_data['added_on'] = Carbon::now();
                $table_data['status_id'] = 1;
                $batch_id = insertRecordReturnId($table_name, $table_data, $this->user_id);
                
                if (validateisNumeric($batch_id)) {
                    $this->addDefaultPaymentBeneficiaries($batch_id, $school_id, $term_id, $year_of_enrollment, $checklist_form_id);
                    $this->validateBeneficiaryEnrollment2($batch_id);
                }
            }
            if (validateisNumeric($batch_id)) {
                if (DB::table('beneficiary_metainfo_staging')->where('school_id', $school_id)->exists()) {
                    DB::table('beneficiary_metainfo_staging')
                        ->where('school_id', $school_id)
                        ->update(array(
                            'in_workflow' => 0,
                            'batch_id' => $batch_id
                        ));
                }
                $res = array(
                    'success' => true,
                    'message' => 'Payment Information saved successfully',
                    'batch_no' => $batch_no,
                    'folder_id' => $folder_id,
                    'batch_id' => $batch_id
                );
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'Problem encountered while saving data. Try again later!!'
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

     public function saveOfflineBeneficiaryEnrollmentbatchinfo(Request $req)
    {
        // $number =  240124345678;
        // return $this->validate_phone_number($number);       
        try {
            $post_data = $req->all();
            $batch_id = $post_data['batch_id'];
            $school_id = $post_data['school_id'];
            $current_stage = $post_data['status'];
            $year=  $post_data['year_of_enrollment'];
            $term_id= $post_data['term_id'];
            $school_type_id=$post_data['school_type_id'];
            $batch_id = $post_data['batch_id'] ? $post_data['batch_id'] : DB::table('beneficiary_metainfo_staging')
                    ->where('school_id', $school_id)->value('batch_id');
            $postdata = $req->input('all_data');
            $data = json_decode($postdata, true);
            // $postdata = file_get_contents("php://input");
            // $data = json_decode($postdata);
            // the post data is array thus handle as array data
            if (is_array($data)) {
                $data = json_decode($postdata, true);
            } else {
                $data = array();
                $data[] = json_decode($postdata, true);
            }
            $table_name = 'beneficiary_enrollments';
            foreach ($data as $key => $value) {
                if ($value['beneficiary_schoolstatus_id'] == 3 && ($value['wb_facility_manager_id'] == '' || !isset($value['wb_facility_manager_id']))) {
                    $test = array(
                        'success' => true,
                        'val_error' => 1,
                        'message' => '<u>Specify WB facility for: ' . $value['beneficiary_no'] . '</u><br> [When you see this message, note that some details are not saved, make the said change then save again]'
                    );
                    return response()->json($test);
                    exit();
                }
                $exam_fees = '';
                if ($value['school_grade'] == 9 || $value['school_grade'] == 12) {
                    $exam_fees = $value['exam_fees'];
                }
                $annual_fees = ((float)$value['term1_fees'] + (float)$value['term2_fees'] + (float)$value['term3_fees'] + (float)$exam_fees);
                $table_data = array(
                    'batch_id' => $batch_id,
                    'school_grade' => $value['school_grade'],
                    'beneficiary_schoolstatus_id' => $value['beneficiary_schoolstatus_id'],
                    'term1_fees' => $value['term1_fees'],
                    'term2_fees' => $value['term2_fees'],
                    'term3_fees' => $value['term3_fees'],
                    'exam_fees' => $exam_fees,
                    'annual_fees' => $annual_fees,
                    'enrollment_status_id' => $value['enrollment_status_id'],
                    'wb_facility_manager_id' => $value['wb_facility_manager_id'],
                    'has_signed_consent' => $value['has_signed_consent'],
                    'is_gce_external_candidate' => $value['is_gce_external_candidate'],
                    'has_signed' => 1,
                    'is_validated' => 1,
                    'passed_rules' => 1,
                    'remarks' => $value['remarks']
                );
                $beneficiary_id = $value['beneficiary_id'];
                $school_grade = $value['school_grade'];
                $school_id = $value['school_id'];
                $exam_no = $value['exam_number'];
                //where data
                $where_data = array(
                    'beneficiary_id' => $value['id']
                );
                $qry = DB::table('beneficiary_enrollments as t1')
                    ->where($where_data)
                    ->count();
                $enrollement_id = $value['id'];
                $where = array(
                    'id' => $value['id']
                );
                if ($qry > 0) {
                    $enrollement_info = DB::table('beneficiary_enrollments as t1')
                        ->where('beneficiary_id', $value['id'])
                        ->first();
                    $enrollement_id = $enrollement_info->id;
                    //save attendance
                    //$this->saveBeneficiaryPermanceattdetails($enrollement_id, $value);
                    $table_data['updated_at'] = Carbon::now();
                    $table_data['updated_by'] = $this->user_id;
                    $previous_data = getPreviousRecords($table_name, $where_data);
                    $table_data1 = updateRecord($table_name, $previous_data, $where_data, $table_data, $this->user_id);
                    //validate individual if in verification or validation
                    if ($current_stage == 2 || $current_stage == 3) {
                        $this->checkPaymentValidationRules($enrollement_id, $batch_id);
                    }
                }
                $benInfo = DB::table('beneficiary_information as t1')
                    ->where('id', $value['beneficiary_id'])
                    ->first();
                $data = array(
                    'is_letter_received' => 1,
                    'first_name' => $value['first_name'],
                    'id' => $value['beneficiary_id'],
                    'last_name' => $value['last_name']
                );
                if ($value['school_grade'] == 9) {
                    $data['grade9_exam_no'] = $exam_no;
                    $data['grade12_exam_no'] = '';
                } else if ($value['school_grade'] == 12) {
                    $data['grade12_exam_no'] = $exam_no;
                    $data['grade9_exam_no'] = '';
                } else {
                    $data['grade12_exam_no'] = '';
                    $data['grade9_exam_no'] = '';
                }
                if (!is_null($benInfo)) {      
                    //update beneficiary info
                    if ($value['school_grade'] != $benInfo->current_school_grade) {
                        logBeneficiaryGradeTransitioning($beneficiary_id, $school_grade, $school_id, $this->user_id);
                        $data['current_school_grade'] = $value['school_grade'];
                    }
                    $data['beneficiary_school_status'] = $value['beneficiary_schoolstatus_id'];
                    $data['verification_school_fees'] = 1;
                    //job 16/3/2022
                    if($value['mobile_phone_number_for_parent_guardian']!=null) {
                        //mobile_phone_number_for_cwac_contact_person
                        $data['mobile_phone_parent_guardian']=$value['mobile_phone_number_for_parent_guardian'];
                    }
                    if($value['mobile_phone_number_for_cwac_contact_person']!=null) {
                        $data['mobile_phone_cwac_contact_person']=$value['mobile_phone_number_for_cwac_contact_person'];
                    }
                    $data['updated_at'] = Carbon::now();
                    $data['updated_by'] = $this->user_id;
                    $previous_data = getPreviousRecords('beneficiary_information', $where);
                    updateRecord('beneficiary_information', $previous_data, $where, $data, $this->user_id);
                    $pre_first_name = $previous_data[0]['first_name'];
                    $pre_last_name = $previous_data[0]['last_name'];
                    $beneficiary_no = $value['beneficiary_no'];
                    $data['created_at'] = Carbon::now();
                    $data['created_by'] = $this->user_id;
                    $data['beneficiary_id'] = $beneficiary_no;
                    if ($pre_first_name != $value['first_name'] || $pre_last_name != $value['last_name']) {
                        //names have been changed
                        $data['girl_id'] = $data['id'];
                        unset($data['id']);
                        insertRecordReturnId('beneficiary_information_logs', $data, $this->user_id);
                    }
                }
            }
                //offline function
                //todo log errors
                $this->validateBeneficiaryEnrollment2($batch_id);
                //update school enrollment status
                $this->updateSchoolEnrollmentStatus($batch_id);
                //update school fees
                // $this->AutoUpdateEnrollmentSchoolFee($batch_id);

            $res = array(
                'success' => true,
                'val_error' => 0,
                'message' => 'Beneficiary Enrollment Details Updated Successfully!!'
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


    public function getOfflineAbsentGirlsbatchinfo(Request $req)//frank
    {
        try {            
            $post_data = $req->all();   
            $school_id = isset($post_data['school_id']) ? $post_data['school_id'] : null;                                 
            $qry = DB::table('beneficiary_information as t1')
            ->select(DB::raw("t1.*,t1.beneficiary_id as beneficiary_no,
                t6.name as missing_reason,t5.transfered_message,t5.unavailable_reason_id,
                COALESCE(t6.name,t5.transfered_message) as remarks,t5.is_transfered,t5.transfer_reason_id,
                t5.school_transfered_to,decrypt(t1.first_name) as first_name,decrypt(t1.last_name) as last_name"))
                ->leftJoin('school_information as t3', 't1.school_id', '=', 't3.id')
                ->leftJoin('districts as t4', 't1.district_id', '=', 't4.id')
                ->leftJoin('beneficiary_payresponses_staging_clone as t5', 't1.id', '=', 't5.id')
                ->leftJoin('suspension_reasons as t6', 't5.unavailable_reason_id', '=', 't6.id');
            if(validateisNumeric($school_id)) {
                $id_qry = DB::table('beneficiary_payresponses_staging_clone as t1')
                    ->select('t1.id')
                    ->whereRaw("t1.school_id = ".$school_id." AND t1.verification_status <> 'pending'")                    
                    ->get();
                $id_array1 = [];
                foreach ($id_qry as $key => $ben_id) {
                    $id_array1[] = $ben_id->id;
                }
                $qry->whereIn('t1.id', $id_array1);
                $results = $qry->groupBy('t1.id')->get();
            } else {
                $results = [];
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
        } catch (\Throwable $t) {
            $res = array(
                'success' => false,
                'message' => $t->getMessage()
            );
        }
        return response()->json($res);
    }
}
