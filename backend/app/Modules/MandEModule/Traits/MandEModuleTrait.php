<?php
/**
 * Created by PhpStorm.
 * User: Kip
 * Date: 12/20/2019
 * Time: 9:52 AM
 */

namespace App\Modules\MandEModule\Traits;


use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use \DateTime;

trait MandEModuleTrait

{

    //Data Analysis MIS Values
    public function getMISValue($question_id, Request $request, $recordId)
    {
        $school_id = $request->input('school_id');
        $year = $request->input('year_id');
        $term = $request->input('term_id');
        $mis_value = '';
        if ($question_id == 9) {
            $disbursementDetails = $this->getSchoolDisbursementDetails($school_id, $year, $term);
            if (!is_null($disbursementDetails)) {
                $mis_value = dateConverter($disbursementDetails->transaction_date, 'd-m-Y');
            }
        } else if ($question_id == 72) {
            $mis_value = $this->signedOnPaymentChecklist($school_id, $year, $term, 1);
        } else if ($question_id == 73) {
            $mis_value = $this->signedOnPaymentChecklist($school_id, $year, $term, 2);
        } else if ($question_id == 74) {
            $mis_value = $this->getSchoolDisbursementPerSchStatus($school_id, $year, $term, 1);
        } else if ($question_id == 75) {
            $mis_value = $this->getSchoolDisbursementPerSchStatus($school_id, $year, $term, 2);
        } else if ($question_id == 13) {
            $mis_value = $this->getPaymentSuspense($school_id, $year, $term, $recordId, $question_id);
        } else if ($question_id == 78) {
            $mis_value = $this->getPaymentSuspense($school_id, $year, $term, $recordId, $question_id);
        } else if ($question_id == 15) {
            $receiptDetails = $this->getSchoolReceiptingDetails($school_id, $year, $term);
            if (!is_null($receiptDetails)) {
                $mis_value = dateConverter($receiptDetails->created_at, 'd-m-Y');
            }
        }
        return $mis_value;
    }

    public function getSchoolDisbursementDetails($school_id, $year, $term)
    {
        $where = array(
            't1.school_id' => $school_id,
            't2.payment_year' => $year
        );
        if ($year < 2019) {
            $where['term_id'] = $term;
        }
        $qry = DB::table('payment_disbursement_details as t1')
            ->join('payment_request_details as t2', 't1.payment_request_id', '=', 't2.id')
            ->where($where);
        $disbursementDetails = $qry->first();
        return $disbursementDetails;
    }

    public function signedOnPaymentChecklist($school_id, $year, $term, $school_status)
    {
        $where = array(
            'school_id' => $school_id,
            'year_of_enrollment' => $year,
            'beneficiary_schoolstatus_id' => $school_status
        );
        if ($year < 2019) {
            $where['term_id'] = $term;
        }
        $count = DB::table('beneficiary_enrollments')
            ->where($where)
            ->where('has_signed', 1)
            ->count();
        return $count;
    }

    public function getSchoolDisbursementPerSchStatus($school_id, $year, $term, $school_status)
    {
        $where = array(
            'school_id' => $school_id,
            'year_of_enrollment' => $year,
            'beneficiary_schoolstatus_id' => $school_status
        );
        $col = 'annual_fees';
        if ($year < 2019) {
            $where['term_id'] = $term;
            $col = 'school_fees';
        }
        $amount = DB::table('beneficiary_enrollments')
            ->select(DB::raw("SUM(decrypt($col)) as amount"))
            ->where($where)
            ->where('is_validated', 1)
            ->value('amount');
        return $amount;
    }

    public function getSchoolChargeableFees()
    {

    }

    public function getPaymentSuspense($school_id, $year, $term, $recordId, $questionId)
    {
        //13 ->overpayment, 78 ->underpayment
        $expected_amount1 = $this->getSchoolDisbursementPerSchStatus($school_id, $year, $term, 1);
        $expected_amount2 = $this->getSchoolDisbursementPerSchStatus($school_id, $year, $term, 2);
        $expected_amount = ($expected_amount1 + $expected_amount2);
        $entryVal1 = $this->getToolUnstructuredQuizValue($recordId, 74);
        $entryVal2 = $this->getToolUnstructuredQuizValue($recordId, 75);
        $entryVal = ($entryVal1 + $entryVal2);
        $suspense = ($expected_amount - $entryVal);
        if ($suspense < 0) {//overpaid
            if ($questionId == 13) {
                return abs($suspense);
            }
        } else if ($suspense > 0) {//underpaid
            if ($questionId == 78) {
                return $suspense;
            }
        }
        return 0;
    }

    public function getSchoolReceiptingDetails($school_id, $year, $term)
    {
        $where = array(
            'school_id' => $school_id,
            'payment_year' => $year
        );
        if ($year < 2019) {
            $where['term_id'] = $term;
        }
        $qry = DB::table('payment_receiptingbatch as t1')
            ->where($where);
        $receiptDetails = $qry->first();
        return $receiptDetails;
    }

    public function getToolUnstructuredQuizValue($record_id, $question_id)
    {
        $response = DB::table('mne_unstructuredquizes_dataentryinfo')
            ->where('record_id', $record_id)
            ->where('question_id', $question_id)
            ->value('response');
        return $response;
    }

    //TODO: KPI CALCULATIONS
    //TODO SCHOOL LEVEL KPIs
    //KPI 1
    public function KPI1Query(Request $request, $female_count_baseline)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        $term_id = $request->input('term_id');
        $baseline_year = Config('constants.MandE.baseline_year');
        $qry = DB::table('mne_datacollectiontool_dataentry_basicinfo as t1')
            ->join('mne_pupilsstatistics_info as t2', 't1.id', '=', 't2.record_id')
            ->select(DB::raw("'$female_count_baseline' as female_count_baseline, t2.year_id,t2.term_id,COALESCE(sum(t2.total_kgsgirls+t2.total_othergirls),0) as female_count_value,
                                    COALESCE(sum(t2.total_boys),0) as male_count_value,COALESCE(sum(t2.total_kgsgirls),0) as detail"))
            ->where('t1.workflow_stage_id', 3);//analysis
        if (validateisNumeric($province_id)) {
            $qry->where('t1.province_id', $province_id);
        }
        if (validateisNumeric($district_id)) {
            $qry->where('t1.district_id', $district_id);
        }
        if (validateisNumeric($school_id)) {
            $qry->where('t1.school_id', $school_id);
        }
        if (validateisNumeric($year_from) && validateisNumeric($year_to)) {
            $qry->whereBetween('t2.year_id', array($year_from, $year_to));
        }
        if (validateisNumeric($term_id)) {
            $qry->where('t2.term_id', $term_id);
        }
        return $qry;
    }

    public function calculateKPI1(Request $request, $baselineNeeded = true)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        $term_id = $request->input('term_id');
        $baseline_year = Config('constants.MandE.baseline_year');
        //baseline
        $female_count_baseline = 0;
        if ($baselineNeeded == true) {
            $female_qry_baseline = DB::table('beneficiary_enrollments as t1')
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                ->where('t1.year_of_enrollment', $baseline_year)
                ->where('t1.has_signed', 1);
            if (validateisNumeric($province_id)) {
                $female_qry_baseline->where('t2.province_id', $province_id);
            }
            if (validateisNumeric($district_id)) {
                $female_qry_baseline->where('t2.district_id', $district_id);
            }
            if (validateisNumeric($school_id)) {
                $female_qry_baseline->where('t1.school_id', $school_id);
            }
            $female_count_baseline = $female_qry_baseline->distinct()
                ->count('t1.beneficiary_id');
        }
        //Source: Pupils Statistics
        $qry = DB::table('mne_datacollectiontool_dataentry_basicinfo as t1')
            ->join('mne_pupilsstatistics_info as t2', 't1.id', '=', 't2.record_id')
            ->select(DB::raw("'$female_count_baseline' as female_count_baseline, t2.year_id,t2.term_id,COALESCE(sum(t2.total_kgsgirls+t2.total_othergirls),0) as female_count_value,
                                    COALESCE(sum(t2.total_boys),0) as male_count_value,COALESCE(sum(t2.total_kgsgirls),0) as detail"))
            ->where('t1.workflow_stage_id', 3)//analysis
            ->where('t1.is_monitoring', '<>', 1);
        if (validateisNumeric($province_id)) {
            $qry->where('t1.province_id', $province_id);
        }
        if (validateisNumeric($district_id)) {
            $qry->where('t1.district_id', $district_id);
        }
        if (validateisNumeric($school_id)) {
            $qry->where('t1.school_id', $school_id);
        }
        if (validateisNumeric($year_from) && validateisNumeric($year_to)) {
            $qry->whereBetween('t2.year_id', array($year_from, $year_to));
        }
        if (validateisNumeric($term_id)) {
            $qry->where('t2.term_id', $term_id);
        }
        $qry->groupBy('t2.year_id', 't2.term_id')
            ->orderBy('t2.year_id', 'ASC')
            ->orderBy('t2.term_id', 'ASC');
        $results = $qry->get();
        return $results;
    }

    public function determineKPI1Performance(Request $request, $result)
    {
        $target = $result->target;
        $val = 0;
        $calculationDetails = $this->calculateKPI1($request, false);
        if ($calculationDetails->count() > 0) {
            $val = $calculationDetails[0]->female_count_value;
        }

        return $this->formatKPIPerformance($target, $val);
    }

    public function getKPI1Graph(Request $request)
    {
        $qry = $this->KPI1Query($request, 23)
            ->join('provinces as p', 'p.id', '=', 't1.province_id')
            ->addSelect('p.name as province_name')
            ->groupBy('t1.province_id');
        $results = $qry->get();
        return $results;
    }

    //KPI 2
    public function calculateKPI2(Request $request, $baselineNeeded = true)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        $term_id = $request->input('term_id');
        $baseline_year = Config('constants.MandE.baseline_year');
        //baseline
        $baseline = 0;
        if ($baselineNeeded == true) {
            $baseline_qry = DB::table('beneficiary_enrollments as t1')
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                ->where('t1.year_of_enrollment', $baseline_year)
                ->where('t1.has_signed', 1);
            if (validateisNumeric($province_id)) {
                $baseline_qry->where('t2.province_id', $province_id);
            }
            if (validateisNumeric($district_id)) {
                $baseline_qry->where('t2.district_id', $district_id);
            }
            if (validateisNumeric($school_id)) {
                $baseline_qry->where('t1.school_id', $school_id);
            }
            $baseline = $baseline_qry->distinct()
                ->count('t1.beneficiary_id');
        }
        //Source: Background info
        $qry = DB::table('mne_datacollectiontool_dataentry_basicinfo as t1')
            ->join('mne_consolidatedschlevel_background_info as t2', 't1.id', '=', 't2.record_id')
            ->select(DB::raw("t2.year_id,t2.term_id,COALESCE(sum(t2.kgsgirls_enrolled),0) as detail_value,'$baseline' as detail_baseline"))
            ->where('t1.workflow_stage_id', 3)//analysis
            ->where('t1.is_monitoring', '<>', 1);
        if (validateisNumeric($province_id)) {
            $qry->where('t1.province_id', $province_id);
        }
        if (validateisNumeric($district_id)) {
            $qry->where('t1.district_id', $district_id);
        }
        if (validateisNumeric($school_id)) {
            $qry->where('t1.school_id', $school_id);
        }
        if (validateisNumeric($year_from) && validateisNumeric($year_to)) {
            $qry->whereBetween('t2.year_id', array($year_from, $year_to));
        }
        if (validateisNumeric($term_id)) {
            $qry->where('t2.term_id', $term_id);
        }
        $qry->groupBy('t2.year_id', 't2.term_id')
            ->orderBy('t2.year_id', 'ASC')
            ->orderBy('t2.term_id', 'ASC');
        $results = $qry->get();
        return $results;
    }

    public function determineKPI2Performance(Request $request, $result)
    {
        $target = $result->target;
        $val = 0;
        $calculationDetails = $this->calculateKPI2($request, false);
        if ($calculationDetails->count() > 0) {
            $val = $calculationDetails[0]->detail_value;
        }
        return $this->formatKPIPerformance($target, $val);
    }

    //KPI 3
    public function calculateKPI3(Request $request, $baselineNeeded = true)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        $term_id = $request->input('term_id');
        $baseline_year = Config('constants.MandE.baseline_year');
        //baseline
        $baseline = 0;
        if ($baselineNeeded == true) {
            $baseline_qry = DB::table('beneficiary_enrollments as t1')
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                ->where('t1.year_of_enrollment', $baseline_year)
                ->where('t1.is_validated', 1);
            if (validateisNumeric($province_id)) {
                $baseline_qry->where('t2.province_id', $province_id);
            }
            if (validateisNumeric($district_id)) {
                $baseline_qry->where('t2.district_id', $district_id);
            }
            if (validateisNumeric($school_id)) {
                $baseline_qry->where('t1.school_id', $school_id);
            }
            $baseline = $baseline_qry->distinct()
                ->count('t1.beneficiary_id');
        }
        //Source: Background info
        $qry = DB::table('mne_datacollectiontool_dataentry_basicinfo as t1')
            ->join('mne_consolidatedschlevel_background_info as t2', 't1.id', '=', 't2.record_id')
            ->select(DB::raw("t2.year_id,t2.term_id,COALESCE(sum(t2.kgsgirls_paidfor),0) as detail_value,'$baseline' as detail_baseline"))
            ->where('t1.workflow_stage_id', 3)//analysis
            ->where('t1.is_monitoring', '<>', 1);
        if (validateisNumeric($province_id)) {
            $qry->where('t1.province_id', $province_id);
        }
        if (validateisNumeric($district_id)) {
            $qry->where('t1.district_id', $district_id);
        }
        if (validateisNumeric($school_id)) {
            $qry->where('t1.school_id', $school_id);
        }
        if (validateisNumeric($year_from) && validateisNumeric($year_to)) {
            $qry->whereBetween('t2.year_id', array($year_from, $year_to));
        }
        if (validateisNumeric($term_id)) {
            $qry->where('t2.term_id', $term_id);
        }
        $qry->groupBy('t2.year_id', 't2.term_id')
            ->orderBy('t2.year_id', 'ASC')
            ->orderBy('t2.term_id', 'ASC');
        $results = $qry->get();
        return $results;
    }

    public function determineKPI3Performance(Request $request, $result)
    {
        $target = $result->target;
        $val = 0;
        $calculationDetails = $this->calculateKPI3($request, false);
        if ($calculationDetails->count() > 0) {
            $val = $calculationDetails[0]->detail_value;
        }
        return $this->formatKPIPerformance($target, $val);
    }
    public function calculateKPI38(Request $request)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        
        if(!validateisNumeric($year_from))
        {
            $year_from=2016;
        }

        if(!validateisNumeric($year_to))
        {
            $year_to=date('Y');
        }

        $years=[];
        if($year_from==$year_to)
        {
            $years[]=$year_from;
        }else{
            $total_year_from=($year_to-$year_from)+1;
            for($i=0;$i<$total_year_from;$i++)
            {
                $year_to_use=$year_from+$i;
                $years[]=$year_to_use;
            }
        }
        $year_months_start_end_days=[];
       
        foreach($years as $year)
        {
            $year_months=[];
            for($i=1;$i<13;$i++){
            $end="";
            $month=$i;
            switch($month)
            {
                case 1://31 days
                case 3:
                case 5:
                case 7:
                case 8:
                case 10:
                case 12:
                    if($month<10)
                    {
                        $month="0".$month;
                    }
                    $end="$year/$month/31"; 
                    break;
                case 2://28/29 days
                    $isleapyear=!($year % 4) && ($year % 100 || !($year % 400));
                    $days=$isleapyear==true?29:28;
                    if($month<10)
                    {
                        $month="0".$month;
                    }
                    $end="$year/$month/$days"; 
                    break;
                case 4:
                case 6:
                case 9:
                case 11:
                    if($month<10)
                    {
                        $month="0".$month;
                    }
                    $end="$year/$month/30"; 
                    break;
            }
            $year_months[$i]=array(
                "start"=>"$year/$month/01",
                "end"=>$end,
                "year_id"=>$year
            );
          
            }
            $year_months_start_end_days[$year]=$year_months;
            $year_months=[];
          
        }
       // return   $year_months_start_end_days;
        $complaints_by_year_month=[];
        $months=array(
            "January",
            "February",
            "March",
            "April",
            "May",
            "June",
            "July",
            "August",
            "September",
            "October",
            "November",
            "December"
        );
       
        foreach($year_months_start_end_days as $year_data)
        {
            //return $year_data;
            $monthly_count=[];
          
            foreach($year_data as $key=>$month){
            
                $comp_reported_within_in_a_day_count_query=Db::table('grm_complaint_details as t1')
            ->whereBetween('complaint_lodge_date',array($month['start'],$month['end']));
           

             if (validateisNumeric($province_id)) {
                $comp_reported_within_in_a_day_count_query->where('t1.province_id', $province_id);
                }
            if (validateisNumeric($district_id)) {
                $comp_reported_within_in_a_day_count_query->where('t1.district_id', $district_id);
                }
            if (validateisNumeric($school_id)) {
                $comp_reported_within_in_a_day_count_query->where('t1.school_id', $school_id);
                }
           
            $comp_reported_within_in_a_day_count= $comp_reported_within_in_a_day_count_query
            ->where('t1.record_status_id',2)
            ->whereRaw('DATEDIFF(t1.complaint_lodge_date,t1.complaint_collection_date) <1')
            ->count('t1.id');
            //->selectraw(" DATEDIFF(t1.updated_at,t1.complaint_lodge_date) as date_diff,t1.complaint_lodge_date,t1.updated_at")->get();
          

             $complaints_by_year_month[]=array(
                 "period"=>$month['start']." to ".$month['end'],
                 "month"=>$months[$key-1],
                 "count"=>  $comp_reported_within_in_a_day_count,
                 "year_id"=>$month['year_id']
             );
            }
            
          


        }
      
        return  $complaints_by_year_month;
        

    }
    public function calculateKPI37(Request $request)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        
        if(!validateisNumeric($year_from))
        {
            $year_from=2016;
        }

        if(!validateisNumeric($year_to))
        {
            $year_to=date('Y');
        }

        $years=[];
        if($year_from==$year_to)
        {
            $years[]=$year_from;
        }else{
            $total_year_from=($year_to-$year_from)+1;
            for($i=0;$i<$total_year_from;$i++)
            {
                $year_to_use=$year_from+$i;
                $years[]=$year_to_use;
            }
        }
        $year_months_start_end_days=[];
       
        foreach($years as $year)
        {
            $year_months=[];
            for($i=1;$i<13;$i++){
            $end="";
            $month=$i;
            switch($month)
            {
                case 1://31 days
                case 3:
                case 5:
                case 7:
                case 8:
                case 10:
                case 12:
                    if($month<10)
                    {
                        $month="0".$month;
                    }
                    $end="$year/$month/31"; 
                    break;
                case 2://28/29 days
                    $isleapyear=!($year % 4) && ($year % 100 || !($year % 400));
                    $days=$isleapyear==true?29:28;
                    if($month<10)
                    {
                        $month="0".$month;
                    }
                    $end="$year/$month/$days"; 
                    break;
                case 4:
                case 6:
                case 9:
                case 11:
                    if($month<10)
                    {
                        $month="0".$month;
                    }
                    $end="$year/$month/30"; 
                    break;
            }
            $year_months[$i]=array(
                "start"=>"$year/$month/01",
                "end"=>$end,
                "year_id"=>$year
            );
          
            }
            $year_months_start_end_days[$year]=$year_months;
            $year_months=[];
          
        }
       // return   $year_months_start_end_days;
        $complaints_by_year_month=[];
        $months=array(
            "January",
            "February",
            "March",
            "April",
            "May",
            "June",
            "July",
            "August",
            "September",
            "October",
            "November",
            "December"
        );
       
        foreach($year_months_start_end_days as $year_data)
        {
            //return $year_data;
            $monthly_count=[];
          
            foreach($year_data as $key=>$month){
            
            $kgs_complaints_query=Db::table('grm_complaint_details as t1')
            ->whereBetween('complaint_lodge_date',array($month['start'],$month['end']));
           

             if (validateisNumeric($province_id)) {
                $kgs_complaints_query->where('t1.province_id', $province_id);
                }
            if (validateisNumeric($district_id)) {
                $kgs_complaints_query->where('t1.district_id', $district_id);
                }
            if (validateisNumeric($school_id)) {
                $kgs_complaints_query->where('t1.school_id', $school_id);
                }
            $kgs_total_monthly_complaints_query=$kgs_complaints_query;
            $kgs_resolved_monthly_complaints_query=$kgs_complaints_query;
            
            $total_monthly_complaints =   $kgs_total_monthly_complaints_query->count('t1.id');
          
            $resolved_within_6_weeks_complaints_count=  $kgs_resolved_monthly_complaints_query
            ->where('t1.record_status_id',2)
            ->whereRaw('DATEDIFF(t1.updated_at,t1.complaint_lodge_date) <30')
            ->count('t1.id');

            
            $monthly_percentage="";
            if($total_monthly_complaints!=0 && $resolved_within_6_weeks_complaints_count!=0)
            {
                $monthly_percentage=($resolved_within_6_weeks_complaints_count*100)/$total_monthly_complaints;
            }else{
                $monthly_percentage=0;
            }

             $complaints_by_year_month[]=array(
                 "period"=>$month['start']." to ".$month['end'],
                 "month"=>$months[$key-1],
                 "percentage"=> round($monthly_percentage)."%",
                 "year_id"=>$month['year_id']
             );
            }
            
          


        }
      
        return  $complaints_by_year_month;
        

    }
    public function determineKPI37Performance($year)
    {
            $kgs_complaints_query=Db::table('grm_complaint_details as t1')
            ->whereRaw('YEAR(complaint_lodge_date) ='.$year);
           
            $kgs_total_yearly_complaints_query=$kgs_complaints_query;
            $kgs_resolved_yearly_complaints_query=$kgs_complaints_query;
            
            $total_yearly_complaints =   $kgs_total_yearly_complaints_query->count('t1.id');
          
            $resolved_within_6_weeks_complaints_count=  $kgs_resolved_yearly_complaints_query
            ->where('t1.record_status_id',2)
            ->whereRaw('DATEDIFF(t1.updated_at,t1.complaint_lodge_date) <30')
            ->count('t1.id');
            $yearly_percentage="";
            if($total_yearly_complaints!=0 && $resolved_within_6_weeks_complaints_count!=0)
            {
                $yearly_percentage=($resolved_within_6_weeks_complaints_count*100)/$total_yearly_complaints;
            }else{
                $yearly_percentage=0;
            }

            $percentage=round($yearly_percentage);

        
        return $percentage;
    }
    public function calculateKPI36(Request $request)
    {

        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        
        if(!validateisNumeric($year_from))
        {
            $year_from=2016;
        }

        if(!validateisNumeric($year_to))
        {
            $year_to=date('Y');
        }

        $years=[];
        if($year_from==$year_to)
        {
            $years[]=$year_from;
        }else{
            $total_year_from=($year_to-$year_from)+1;
            for($i=0;$i<$total_year_from;$i++)
            {
                $year_to_use=$year_from+$i;
                $years[]=$year_to_use;
            }
        }
        $year_months_start_end_days=[];
       
        foreach($years as $year)
        {
            $year_months=[];
            for($i=1;$i<13;$i++){
            $end="";
            $month=$i;
            switch($month)
            {
                case 1://31 days
                case 3:
                case 5:
                case 7:
                case 8:
                case 10:
                case 12:
                    if($month<10)
                    {
                        $month="0".$month;
                    }
                    $end="$year/$month/31"; 
                    break;
                case 2://28/29 days
                    $isleapyear=!($year % 4) && ($year % 100 || !($year % 400));
                    $days=$isleapyear==true?29:28;
                    if($month<10)
                    {
                        $month="0".$month;
                    }
                    $end="$year/$month/$days"; 
                    break;
                case 4:
                case 6:
                case 9:
                case 11:
                    if($month<10)
                    {
                        $month="0".$month;
                    }
                    $end="$year/$month/30"; 
                    break;
            }
            $year_months[$i]=array(
                "start"=>"$year/$month/01",
                "end"=>$end,
                "year_id"=>$year
            );
          
            }
            $year_months_start_end_days[$year]=$year_months;
            $year_months=[];
          
        }
       // return   $year_months_start_end_days;
        $complaints_by_year_month=[];
        $months=array(
            "January",
            "February",
            "March",
            "April",
            "May",
            "June",
            "July",
            "August",
            "September",
            "October",
            "November",
            "December"
        );
        foreach($year_months_start_end_days as $year_data)
        {
            //return $year_data;
            // gbv - 70,71,50,9
          
            foreach($year_data as $key=>$month){
            
            $serious_gbv_complaints_query=Db::table('grm_complaint_details as t1')
            //->join('beneficiary_information as t2','t1.girl_id','t2.id')
            ->whereIn('sub_category_id',array(70,71,50,9))
            ->whereBetween('complaint_lodge_date',array($month['start'],$month['end']));
           

             if (validateisNumeric($province_id)) {
                $serious_gbv_complaints_query->where('t1.province_id', $province_id);
            }
            if (validateisNumeric($district_id)) {
                $serious_gbv_complaints_query->where('t1.district_id', $district_id);
            }
            if (validateisNumeric($school_id)) {
                $serious_gbv_complaints_query->where('t1.school_id', $school_id);
             }

             $complaints_count=    $serious_gbv_complaints_query->count('t1.id');
            
             
          
             $complaints_by_year_month[]=array(
                 "period"=>$month['start']." to ".$month['end'],
                 "month"=>$months[$key-1],
                 "count"=>$complaints_count,
                 "year_id"=>$month['year_id']
             );
            }

        }
        return  $complaints_by_year_month;

    }
   
    public function calculateKPI35(Request $request)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        
        if(!validateisNumeric($year_from))
        {
            $year_from=2016;
        }

        if(!validateisNumeric($year_to))
        {
            $year_to=date('Y');
        }

        $years=[];
        if($year_from==$year_to)
        {
            $years[]=$year_from;
        }else{
            $total_year_from=($year_to-$year_from)+1;
            for($i=0;$i<$total_year_from;$i++)
            {
                $year_to_use=$year_from+$i;
                $years[]=$year_to_use;
            }
        }
        $year_months_start_end_days=[];
       
        foreach($years as $year)
        {
            $year_months=[];
            for($i=1;$i<13;$i++){
            $end="";
            $month=$i;
            switch($month)
            {
                case 1://31 days
                case 3:
                case 5:
                case 7:
                case 8:
                case 10:
                case 12:
                    if($month<10)
                    {
                        $month="0".$month;
                    }
                    $end="$year/$month/31"; 
                    break;
                case 2://28/29 days
                    $isleapyear=!($year % 4) && ($year % 100 || !($year % 400));
                    $days=$isleapyear==true?29:28;
                    if($month<10)
                    {
                        $month="0".$month;
                    }
                    $end="$year/$month/$days"; 
                    break;
                case 4:
                case 6:
                case 9:
                case 11:
                    if($month<10)
                    {
                        $month="0".$month;
                    }
                    $end="$year/$month/30"; 
                    break;
            }
            $year_months[$i]=array(
                "start"=>"$year/$month/01",
                "end"=>$end,
                "year_id"=>$year
            );
          
            }
            $year_months_start_end_days[$year]=$year_months;
            $year_months=[];
          
        }
       // return   $year_months_start_end_days;
        $complaints_by_year_month=[];
        $months=array(
            "January",
            "February",
            "March",
            "April",
            "May",
            "June",
            "July",
            "August",
            "September",
            "October",
            "November",
            "December"
        );
        foreach($year_months_start_end_days as $year_data)
        {
            //return $year_data;
            $monthly_count=[];
          
            foreach($year_data as $key=>$month){
            
            $kgs_complaints_query=Db::table('grm_complaint_details as t1')
            ->join('beneficiary_information as t2','t1.girl_id','t2.id')
            ->whereBetween('complaint_lodge_date',array($month['start'],$month['end']));
           

             if (validateisNumeric($province_id)) {
            $kgs_complaints_query->where('t1.province_id', $province_id);
            }
            if (validateisNumeric($district_id)) {
                $kgs_complaints_query->where('t1.district_id', $district_id);
            }
            if (validateisNumeric($school_id)) {
             $kgs_complaints_query->where('t1.school_id', $school_id);
             }

             //$complaints_count=  $kgs_complaints_query->distinct()->count('t1.girl_id');
             $complaints_count=  $kgs_complaints_query->count('t1.id');
             //$monthly_count[$month]=$complaints_count;
             
          
             $complaints_by_year_month[]=array(
                 "period"=>$month['start']." to ".$month['end'],
                 "month"=>$months[$key-1],
                 "count"=>$complaints_count,
                 "year_id"=>$month['year_id']
             );
            }
            // $complaints_by_year_month[]=array(
            //     "year"=>2016,
            //     "monthly_data"=>$monthly_count,
            // );
          


        }
        return  $complaints_by_year_month;
        

    }  
    public function calculateKPI34Or(Request $request)
    {
        
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        
       $baseline=DB::table('mne_kpis')->where('id',34)->value('baseline');
        if(!validateisNumeric($year_from))
        {
            $year_from=2016;
        }

        if(!validateisNumeric($year_to))
        {
            $year_to=date('Y');
        }
       
            $supported_qry = DB::table('beneficiary_enrollments as t1')
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                ->whereBetween('t1.year_of_enrollment', array($year_from,$year_to))
                ->where('t1.is_validated', 1)
                ->select(DB::raw("$baseline as baseline_year_count_value, t1.year_of_enrollment as year_count,count(DISTINCT t1.beneficiary_id) as beneficiary_count"));
            if (validateisNumeric($province_id)) {
                $supported_qry->where('t2.province_id', $province_id);
            }
            if (validateisNumeric($district_id)) {
                $supported_qry->where('t2.district_id', $district_id);
            }
            if (validateisNumeric($school_id)) {
                $supported_qry->where('t1.school_id', $school_id);
            }
            $supported_qry->groupBy('t1.year_of_enrollment')
                        ->orderBy('t1.year_of_enrollment', 'ASC');
            $numSupported=$supported_qry->get();
            return $numSupported;
                // $baseline = $baseline_qry->distinct()
                //->count('t1.beneficiary_id');

              
        

    }
    public function calculateKPI34Grade12(Request $request)
    {
       
        //get total enrolled beneficiaries as well as those who transitioned
        //kgs_enrolled changed to kgs_enrolled. data entry for only term 1 previous year (Maureen)
        $qry = DB::table('mne_datacollectiontool_dataentry_basicinfo as t1')
            ->join('mne_progression_info as t2', 't1.id', '=', 't2.record_id')
            ->select(DB::raw(" t2.year_id,COALESCE(SUM(t2.kgs_enrolled),0) as detail_value"))
            ->where('t2.transition_id', 11)
            ->where('t1.workflow_stage_id', 3)//analysis
            ->where('t1.is_monitoring', '<>', 1);
        if (validateisNumeric($province_id)) {
            $qry->where('t1.province_id', $province_id);
        }
        if (validateisNumeric($district_id)) {
            $qry->where('t1.district_id', $district_id);
        }
        if (validateisNumeric($school_id)) {
            $qry->where('t1.school_id', $school_id);
        }
        if (validateisNumeric($year_from) && validateisNumeric($year_to)) {
            $qry->whereBetween('t2.year_id', array($year_from, $year_to));
        }
        $qry->groupBy('t2.year_id')
            ->orderBy('t2.year_id', 'ASC')
            ->orderBy('t2.term_id', 'ASC');
        $results = $qry->get();
        return $results;


    }

    //KPI34

    public function KPI34BaseQuery($year,$term,$province_id,$district_id,$school_id)
    {
              
        $base_query=DB::table('mne_datacollection_tools as t1')
        ->join('mne_datacollectiontool_dataentry_basicinfo as t2','t2.datacollection_tool_id','t1.id')
        ->join('mne_consolidatedschlevel_background_info as t3','t3.record_id','t2.id')
        ->where('t2.datacollection_tool_id',1)
        ->where(['t2.entry_term'=>$term,"t2.entry_year"=>$year])
        ->GroupBy('t2.datacollection_tool_id');
        $base_query=$this->filterKPIQueryLocationWise($base_query,'t2',$province_id,$district_id,$school_id);
        return $base_query;

    }
    public function calculateKPI34(Request $request)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');

        if(!validateisNumeric($year_from))
        {
            $year_from=2016;
        }

        if(!validateisNumeric($year_to))
        {
            $year_to=date('Y');
        }

        $years=[];
        if($year_from==$year_to)
        {
            $years[]=$year_from;
        }else{
            $total_year_from=($year_to-$year_from)+1;
            for($i=0;$i<$total_year_from;$i++)
            {
                $year_to_use=$year_from+$i;
                $years[]=$year_to_use;
            }
        }
        $yearly_targets_data=[];
        //get eligible girls
        $yearly_count_eligible=[];
        

        //get disabled and non-disabled girls


        $year_not_disabled_values=[];
        $year_disabled_values=[];
        $yearly_count_paid_for=[];
        foreach($years as $year){
            $target_data=DB::table('mne_kpis_targets')->where(['year'=>$year,'kpi_id'=>33])->selectraw('target_val,target_type')->get()->toArray();

            if(is_array($target_data) && count($target_data)>0){
             $yearly_targets_data[$year]=$target_data;
             }else{
              $yearly_targets_data[$year][0]=(object)(array("target_val"=>0,"target_type"=>0));
             }
            // $qry_disabled_not_disabled= Db::table('school_information as t1')
            // ->join('mne_datacollectiontool_dataentry_basicinfo as t2','t1.id','t2.school_id')
            // ->join('mne_consolidatedschlevel_background_info as t3','t3.record_id','t2.id')
            // ->where(['t2.entry_term'=>1,"t2.entry_year"=>$year]);

             

            $qry_disabled_not_disabled=DB::table('mne_datacollection_tools as t1')
            ->join('mne_datacollectiontool_dataentry_basicinfo as t2','t2.datacollection_tool_id','t1.id')
            ->join('mne_consolidatedschlevel_background_info as t3','t3.record_id','t2.id')
            ->where('t2.datacollection_tool_id',1)
            ->where(['t2.entry_term'=>1,"t2.entry_year"=>$year])
            ->GroupBy('t2.datacollection_tool_id');
            $qry_disabled_not_disabled=$this->filterKPIQueryLocationWise( $qry_disabled_not_disabled,'t2',$province_id,$district_id,$school_id);




            // $qry_girls_paid_for= Db::table('school_information as t1')
            // ->join('mne_datacollectiontool_dataentry_basicinfo as t2','t1.id','t2.school_id')
            // ->join('mne_spotcheck_boardingfacility as t3','t3.record_id','t2.id')
            // ->where(['t2.entry_term'=>1,"t2.entry_year"=>$year]);


            $qry_girls_paid_for=DB::table('mne_datacollection_tools as t1')
            ->join('mne_datacollectiontool_dataentry_basicinfo as t2','t2.datacollection_tool_id','t1.id')
            ->join('mne_spotcheck_boardingfacility as t3','t3.record_id','t2.id')
            ->where('t2.datacollection_tool_id',1)
            ->where(['t2.entry_term'=>1,"t2.entry_year"=>$year])
            ->GroupBy('t2.datacollection_tool_id');
            $qry_girls_paid_for=$this->filterKPIQueryLocationWise($qry_girls_paid_for,'t2',$province_id,$district_id,$school_id);
            //$paid_for_count =   $qry_girls_paid_for->sum('kgsgirls_paidfor');
            $paid_for_count_t1=$this->KPI33BaseQueryPaidFor($province_id,$district_id,$school_id)->where(['t2.entry_term'=>1,"t2.entry_year"=>$year])->sum('kgsgirls_paidfor');
            $paid_for_count_t2=$this->KPI33BaseQueryPaidFor($province_id,$district_id,$school_id)->where(['t2.entry_term'=>2,"t2.entry_year"=>$year])->sum('kgsgirls_paidfor');
            $paid_for_count_t3=$this->KPI33BaseQueryPaidFor($province_id,$district_id,$school_id)->where(['t2.entry_term'=>3,"t2.entry_year"=>$year])->sum('kgsgirls_paidfor');
            

            $paid_for_count=0;
            $annual_value_to_divide_with=0;
              
             if( $paid_for_count_t1>0)
          {
            $paid_for_count= $paid_for_count_t1;
            $annual_value_to_divide_with+=1;
          }
          if( $paid_for_count_t2>0)
          {
            $paid_for_count+= $paid_for_count_t2;
            $annual_value_to_divide_with+=1;
          }
          if( $paid_for_count_t3>0)
          {
            $paid_for_count+= $paid_for_count_t3;
            $annual_value_to_divide_with+=1;
          }
           if($paid_for_count>0){
              
            $paid_for_count= $paid_for_count/$annual_value_to_divide_with;
          }


            $not_disabled_count_t1=$this->KPI34BaseQuery($year,1,$province_id,$district_id,$school_id)->sum('kgsgirls_without_disability');
            $disabled_count_t1=$this->KPI34BaseQuery($year,1,$province_id,$district_id,$school_id)->sum('kgsgirls_with_disability');
            $not_disabled_count_t2=$this->KPI34BaseQuery($year,2,$province_id,$district_id,$school_id)->sum('kgsgirls_without_disability');
            $disabled_count_t2=$this->KPI34BaseQuery($year,2,$province_id,$district_id,$school_id)->sum('kgsgirls_with_disability');
            $not_disabled_count_t3=$this->KPI34BaseQuery($year,3,$province_id,$district_id,$school_id)->sum('kgsgirls_without_disability');
            $disabled_count_t3=$this->KPI34BaseQuery($year,3,$province_id,$district_id,$school_id)->sum('kgsgirls_with_disability');

            $disabled_count=0;
            $annual_value_to_divide_with_disabled=0;
              
             if(  $disabled_count_t1>0)
          {
            $disabled_count=  $disabled_count_t1;
            $annual_value_to_divide_with_disabled+=1;
          }
          if(  $disabled_count_t2>0)
          {
            $disabled_count=  $disabled_count_t2;
            $annual_value_to_divide_with_disabled+=1;
          }
          if(  $disabled_count_t3>0)
          {
            $disabled_count=  $disabled_count_t3;
            $annual_value_to_divide_with_disabled+=1;
          }

          if( $disabled_count>0){
              
            $disabled_count=  $disabled_count/$annual_value_to_divide_with_disabled;
          }


            $not_disabled_count=0;
            $annual_value_to_divide_with_not_disabled=0;
              
             if( $not_disabled_count_t1>0)
          {
            $not_disabled_count= $paid_for_count_t1;
            $annual_value_to_divide_with_not_disabled+=1;
          }
          if( $paid_for_count_t2>0)
          {
            $not_disabled_count+= $paid_for_count_t2;
            $annual_value_to_divide_with_not_disabled+=1;
          }
          if( $paid_for_count_t3>0)
          {
            $not_disabled_count+= $paid_for_count_t3;
            $annual_value_to_divide_with_not_disabled+=1;
          }
           if( $not_disabled_count>0){
              
            $not_disabled_count=  $not_disabled_count/$annual_value_to_divide_with_not_disabled;
          }

            $year_not_disabled_values[$year]=$not_disabled_count;
            $year_disabled_values[$year]=$disabled_count;
            $yearly_count_paid_for[$year]=$paid_for_count;
           
        }
      
       
       
        $summary=[];
      
        foreach($years as $key=>$year)
        {
            $summary[]=array(
                "year_id"=>$year,
                "baseline_val_count"=>$yearly_targets_data[$year][0]->target_val,
                "target_type"=>$yearly_targets_data[$year][0]->target_type,
                "disabled_count_value"=>$year_disabled_values[$year],
                "not_disabled_count"=>  $year_not_disabled_values[$year],
                "target_achieved"=>$yearly_count_paid_for[$year]
            );
        }
        return $summary;

      
    }
    //kpi40
    private function filterKPIQueryLocationWise($query,$tbl,$province_id,$district_id,$school_id)
    {
        if (isset($school_id) && $school_id != '') {
            $query->where($tbl.'.id', $school_id);
        } else if (isset($district_id) && $district_id != '') {
            $query->where($tbl.'.district_id', $district_id);
        } else if (isset($province_id) && $province_id != '') {
            $query->where($tbl.'.province_id', $province_id);
        }
        return $query;
    }

    private function KPI45FilterBaseQuery($year,$quarter,$province_id,$district_id)
    {
        $school_id="";
        // Quater 1
        if($quarter==1){
            $monthfrom= date($year . '-01-01');
            $monthto=date($year . '-03-31');
        }else if ($quarter==2){
            $monthfrom=date($year . '-04-01');
            $monthto=date($year . '-06-30');
        }else if($quarter==3){
            $monthfrom=date($year . '-07-01');
            $monthto=date($year . '-09-30');

        }else if ($quarter==4){
            $monthfrom=date($year . '-10-01');
            $monthto=date($year . '-12-31');
        }


        /*Jobs Version 
        $base_query=DB::table('mne_datacollection_tools as t1')
        ->join('mne_datacollectiontool_dataentry_basicinfo as t2','t2.datacollection_tool_id','t1.id')
        ->join('mne_unstructuredquizes_dataentryinfo as t3','t3.record_id','t2.id')
        ->where('t2.datacollection_tool_id',2)
        ->where('t3.question_id',21)
        ->where(["t2.entry_year"=>$year])
        ->where(['t2.entry_term'=>1,"t2.entry_year"=>2022])
        ->GroupBy('t2.datacollection_tool_id');
        by district/ province
       $base_query=$this->filterKPIQueryLocationWise($base_query,'t2',$province_id,$district_id,$school_id);*/

       //updated 2/08/2022
        $query=DB::table('mne_datacollectiontool_dataentry_basicinfo as t1')
        ->select(DB::raw("COUNT(CASE WHEN t1.school_geo_type=1 THEN t1.id ELSE NULL END) AS urbanschools,
                         COUNT(CASE WHEN t1.school_geo_type=2 THEN t1.id ELSE NULL END) AS ruralschools"))
        ->where('t1.datacollection_tool_id',1)
        ->whereBetween('t1.created_at', [$monthfrom, $monthto]);

        if(isset($province_id) && isset($district)){
            $query->where(['t1.province_id'=>$province_id,'t1.district_id'=>$district_id]);
            echo "all";
        }else if(isset($province_id)){
            $query->where('t1.province_id',$province_id);
            echo "province_id";
        }else if(isset($district)){
             $query->where('t1.district_id',$district_id);
             echo "district_id";
        }
        exit();
        $results=$query->get();
        return json_encode($results);
    }

    private function KPI45BaseQuery($year,$quarter,$province_id,$district_id,$school_id)
    {
        // Quater 1
        if($quarter==1){
            $monthfrom= date($year . '-01-01');
            $monthto=date($year . '-03-31');
        }else if ($quarter==2){
            $monthfrom=date($year . '-04-01');
            $monthto=date($year . '-06-30');
        }else if($quarter==3){
            $monthfrom=date($year . '-07-01');
            $monthto=date($year . '-09-30');

        }else if ($quarter==4){
            $monthfrom=date($year . '-10-01');
            $monthto=date($year . '-12-31');

        }

       //updated 2/08/2022
        $query=DB::table('mne_datacollectiontool_dataentry_basicinfo as t1')
        ->select(DB::raw("COUNT(CASE WHEN t1.school_geo_type=1 THEN t1.id ELSE NULL END) AS urbanschools,COUNT(CASE WHEN t1.school_geo_type=2 THEN t1.id ELSE NULL END) AS ruralschools"))
        ->where('t1.datacollection_tool_id',1)
        ->whereBetween('t1.created_at', [$monthfrom, $monthto]);
        if (validateisNumeric($province_id)) {
            $query->where('t1.province_id', $province_id);
        }
        if (validateisNumeric($district_id)) {
            $query->where('t1.district_id', $district_id);
        }
        if (validateisNumeric($school_id)) {
            $query->where('t1.school_id', $school_id);
        }
        $response=$query->get(); 
        return json_encode($response);
    }

    //kpi 45
    public function calculateKPI45(Request $request)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        if(!validateisNumeric($year_from))
        {
            $year_from=2016;
        }

        if(!validateisNumeric($year_to))
        {
            $year_to=date('Y');
        }

        $years=[];
        if($year_from==$year_to)
        {
            $years[]=$year_from;
        }else{
            $total_year_from=($year_to-$year_from)+1;
            for($i=0;$i<$total_year_from;$i++)
            {
                $year_to_use=$year_from+$i;
                $years[]=$year_to_use;
            }
        }

        //districts
        //after first load.
        $summary=array();
        //$quarter_1= array();
        foreach($years as $year)
        {

                $quarter_1=json_decode($this->KPI45BaseQuery($year,1,$province_id,$district_id,$school_id));
                $quarter_2=json_decode($this->KPI45BaseQuery($year,2,$province_id,$district_id,$school_id));
                $quarter_3=json_decode($this->KPI45BaseQuery($year,3,$province_id,$district_id,$school_id)); 
                $quarter_4=json_decode($this->KPI45BaseQuery($year,4,$province_id,$district_id,$school_id));
            $summary[]=array(
                "year_id"=>$year,
                "rural_t1"=>$quarter_1[0]->urbanschools,
                "urban_t1"=>$quarter_1[0]->ruralschools,
                "rural_t2"=>$quarter_2[0]->urbanschools,
                "urban_t2"=>$quarter_2[0]->ruralschools,
                "rural_t3"=>$quarter_3[0]->urbanschools,
                "urban_t3"=>$quarter_3[0]->ruralschools,
                "rural_t4"=>$quarter_4[0]->urbanschools,
                "urban_t4"=>$quarter_4[0]->ruralschools
            );
        }
        return $summary;

    }
    //kpi 44
    public function  calculateKPI44(Request $request)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        if(!validateisNumeric($year_from))
        {
            $year_from=2016;
        }

        if(!validateisNumeric($year_to))
        {
            $year_to=date('Y');
        }

        $years=[];
        if($year_from==$year_to)
        {
            $years[]=$year_from;
        }else{
            $total_year_from=($year_to-$year_from)+1;
            for($i=0;$i<$total_year_from;$i++)
            {
                $year_to_use=$year_from+$i;
                $years[]=$year_to_use;
            }
        }
        $summary=array();
        foreach($years as $year)
        {
            $grades=['8','9','10','11','12'];
            foreach($grades as $grade){
                //disabled girls 
            $grade_columns=[8=>"kgsgirls_grade_eight",9=>"kgsgirls_grade_nine",10=>"kgsgirls_grade_ten",
                11=>"kgsgirls_grade_eleven",12=>"kgsgirls_grade_twelve"];
           $grade_qry=DB::table('mne_datacollection_tools as t1')
           ->join('mne_datacollectiontool_dataentry_basicinfo as t2','t2.datacollection_tool_id','t1.id')
           ->join('mne_spotcheck_boardingfacility as t3','t3.record_id','t2.id')
           ->where('t2.datacollection_tool_id',1)
           ->where('grade_id',$grade)
           ->GroupBy('t2.datacollection_tool_id');
           $grade_qry=$this->filterKPIQueryLocationWise($grade_qry,'t2',$province_id,$district_id,$school_id);
            
           // paid for term one
           //term one
           $paid_for_t1=$grade_qry->where(['t2.entry_term'=>1,"t2.entry_year"=>$year])->sum('kgsgirls_paidfor');     
            //term one
           $paid_for_t2=$grade_qry->where(['t2.entry_term'=>2,"t2.entry_year"=>$year])->sum('kgsgirls_paidfor');     
            //term three
           $paid_for_t3=$grade_qry->where(['t2.entry_term'=>3,"t2.entry_year"=>$year])->sum('kgsgirls_paidfor');     
      
          $kgs_paid_for_annually=0;
          $annual_value_to_divide_with=0;
            
           if($paid_for_t1>0)
        {
            $kgs_paid_for_annually=$paid_for_t1;
            $annual_value_to_divide_with+=1;
        }
        if($paid_for_t2>0)
        {
            $kgs_paid_for_annually=$paid_for_t2;
            $annual_value_to_divide_with+=1;
        }
        if($paid_for_t3>0)
        {
            $kgs_paid_for_annually=$paid_for_t3;
            $annual_value_to_divide_with+=1;
        }
        if($kgs_paid_for_annually>0){
            
        $kgs_paid_for_annually=$kgs_paid_for_annually/$annual_value_to_divide_with;
        }
        
        $summary[]=array(
            'year_id'=>$year,
            "grade"=>$grade,
            "paid_for_val"=> $kgs_paid_for_annually,

        );

        }
        }

     return $summary;   

       
    }
    //kpi43
    public function calculateKPI43(Request $request)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        if(!validateisNumeric($year_from))
        {
            $year_from=2016;
        }

        if(!validateisNumeric($year_to))
        {
            $year_to=date('Y');
        }

        $years=[];
        if($year_from==$year_to)
        {
            $years[]=$year_from;
        }else{
            $total_year_from=($year_to-$year_from)+1;
            for($i=0;$i<$total_year_from;$i++)
            {
                $year_to_use=$year_from+$i;
                $years[]=$year_to_use;
            }
        }
        $summary=array();
        foreach($years as $year)
        {
            $grades=['8','9','10','11','12'];
            foreach($grades as $grade){
                //disabled girls 
            $grade_columns=[8=>"kgsgirls_grade_eight",9=>"kgsgirls_grade_nine",10=>"kgsgirls_grade_ten",
                11=>"kgsgirls_grade_eleven",12=>"kgsgirls_grade_twelve"];
           $grade_qry=DB::table('mne_datacollection_tools as t1')
           ->join('mne_datacollectiontool_dataentry_basicinfo as t2','t2.datacollection_tool_id','t1.id')
           ->join('mne_spotcheck_kgs_girl_enrolments as t3','t3.record_id','t2.id')
           ->where('t2.datacollection_tool_id',1)
           ->GroupBy('t2.datacollection_tool_id');
           $grade_qry=$this->filterKPIQueryLocationWise($grade_qry,'t2',$province_id,$district_id,$school_id);
            
           //enrolled kgs girls
           //term one
           $enrolled_t1=$grade_qry->where(['t2.entry_term'=>1,"t2.entry_year"=>$year])->sum($grade_columns[$grade]);     
           //term two
           $enrolled_t2=$grade_qry->where(['t2.entry_term'=>2,"t2.entry_year"=>$year])->sum($grade_columns[$grade]);
           //term three      
           $enrolled_t3=$grade_qry->where(['t2.entry_term'=>3,"t2.entry_year"=>$year])->sum($grade_columns[$grade]);   
              
         
            $summary[]=array(
                'year_id'=>$year,
                "grade"=>$grade,
                "disabled_val_t1"=> $enrolled_t1,
                "non_disabled_val_t1"=> $enrolled_t1,
                "disabled_val_t2"=> $enrolled_t2,
                "non_disabled_val_t2"=> $enrolled_t2,
                "disabled_val_t3"=> $enrolled_t3,
                "non_disabled_val_t3"=> $enrolled_t3,

            );
        
          }

        }
        return $summary;
    }
    //kpi42
    public function calculateKPI42(Request $request)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        if(!validateisNumeric($year_from))
        {
            $year_from=2016;
        }

        if(!validateisNumeric($year_to))
        {
            $year_to=date('Y');
        }

        $years=[];
        if($year_from==$year_to)
        {
            $years[]=$year_from;
        }else{
            $total_year_from=($year_to-$year_from)+1;
            for($i=0;$i<$total_year_from;$i++)
            {
                $year_to_use=$year_from+$i;
                $years[]=$year_to_use;
            }
        }
        $summary=array();
        foreach($years as $year)
        {
            $grades=['8','9','10','11','12'];
            foreach($grades as $grade){
                //disabled girls 
           $grade_qry=DB::table('mne_datacollection_tools as t1')
           ->join('mne_datacollectiontool_dataentry_basicinfo as t2','t2.datacollection_tool_id','t1.id')
           ->join('mne_consolidatedschlevel_background_info as t3','t3.record_id','t2.id')
           ->where('t2.datacollection_tool_id',1)
           ->GroupBy('t2.datacollection_tool_id')
           ->where('grade_id',$grade);
           $grade_qry=$this->filterKPIQueryLocationWise($grade_qry,'t2',$province_id,$district_id,$school_id);
            
           //girls
           //term one
           $disabled_t1 = $grade_qry->where(['t2.entry_term'=>1,"t2.entry_year"=>$year])->sum('kgsgirls_with_disability');//disabled
           $non_disabled_t1 = $grade_qry->where(['t2.entry_term'=>1,"t2.entry_year"=>$year])->sum('kgsgirls_without_disability');
                
           //term two
           $disabled_t2 = $grade_qry->where(['t2.entry_term'=>2,"t2.entry_year"=>$year])->sum('kgsgirls_with_disability');//disabled
           $non_disabled_t2 = $grade_qry->where(['t2.entry_term'=>2,"t2.entry_year"=>$year])->sum('kgsgirls_without_disability');
         
           //term three      
           $disabled_t3 = $grade_qry->where(['t2.entry_term'=>3,"t2.entry_year"=>$year])->sum('kgsgirls_with_disability');//disabled
           $non_disabled_t3 = $grade_qry->where(['t2.entry_term'=>3,"t2.entry_year"=>$year])->sum('kgsgirls_without_disability');
            
           //boys 
            //term one
            $boys_disabled_t1 = $grade_qry->where(['t2.entry_term'=>1,"t2.entry_year"=>$year])->sum('boys_with_disability');//disabled
            $boys_non_disabled_t1 = $grade_qry->where(['t2.entry_term'=>1,"t2.entry_year"=>$year])->sum('boys_without_disability');
                 
            //term two
            $boys_disabled_t2 = $grade_qry->where(['t2.entry_term'=>2,"t2.entry_year"=>$year])->sum('boys_without_disability');//disabled
            $boys_non_disabled_t2 = $grade_qry->where(['t2.entry_term'=>2,"t2.entry_year"=>$year])->sum('boys_without_disability');
          
            //term three      
            $boys_disabled_t3 = $grade_qry->where(['t2.entry_term'=>3,"t2.entry_year"=>$year])->sum('boys_without_disability');//disabled
            $boys_non_disabled_t3 = $grade_qry->where(['t2.entry_term'=>3,"t2.entry_year"=>$year])->sum('boys_without_disability');
               
            $summary[]=array(
                'year_id'=>$year,
                "grade"=>$grade,
                "girls_disabled_val_t1"=> $disabled_t1,
                "boys_disabled_val_t1"=> $boys_disabled_t1,
                "girls_non_disabled_val_t1"=>$non_disabled_t1,
                "boys_non_disabled_val_t1"=> $boys_non_disabled_t1,

                "girls_disabled_val_t2"=> $disabled_t2,
                "boys_disabled_val_t2"=> $boys_disabled_t2,
                "girls_non_disabled_val_t2"=>$non_disabled_t2,
                "boys_non_disabled_val_t2"=> $boys_non_disabled_t2,

                "girls_disabled_val_t3"=> $disabled_t3,
                "boys_disabled_val_t3"=> $boys_disabled_t3,
                "girls_non_disabled_val_t3"=>$non_disabled_t3,
                "boys_non_disabled_val_t3"=> $boys_non_disabled_t3,

            );
          }

        }
        return $summary;
    }
    public function calculateKPI41(Request $request)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        if(!validateisNumeric($year_from))
        {
            $year_from=2016;
        }

        if(!validateisNumeric($year_to))
        {
            $year_to=date('Y');
        }

        $years=[];
        if($year_from==$year_to)
        {
            $years[]=$year_from;
        }else{
            $total_year_from=($year_to-$year_from)+1;
            for($i=0;$i<$total_year_from;$i++)
            {
                $year_to_use=$year_from+$i;
                $years[]=$year_to_use;
            }
        }
        $summary=array();
        foreach($years as $year)
        {
            $grades=['8','9','10','11','12'];
            foreach($grades as $grade){
                   //learners who started
                   $grade_qry=DB::table('mne_datacollection_tools as t1')
                   ->join('mne_datacollectiontool_dataentry_basicinfo as t2','t2.datacollection_tool_id','t1.id')
                   ->join('mne_progression_info as t3','t3.record_id','t2.id')
                   ->where('t2.datacollection_tool_id',1)
                   ->GroupBy('t2.datacollection_tool_id')
                   ->where('transition_id',$grade);
                   $grade_qry=$this->filterKPIQueryLocationWise($grade_qry,'t2',$province_id,$district_id,$school_id);
                    
                   //term one
                   $enrolled_grade_count_kgs_t1 = $grade_qry->where(['t2.entry_term'=>1,"t2.entry_year"=>$year])->sum('kgs_enrolled');//enrolled
                   $finished_grade_count_kgs_t1 = $grade_qry->where(['t2.entry_term'=>1,"t2.entry_year"=>$year])->sum('kgs_started');
                        
                   //term two
                   $enrolled_grade_count_kgs_t2 = $grade_qry->where(['t2.entry_term'=>2,"t2.entry_year"=>$year])->sum('kgs_enrolled');//enrolled
                   $finished_grade_count_kgs_t2 = $grade_qry->where(['t2.entry_term'=>2,"t2.entry_year"=>$year])->sum('kgs_started');
                 
                   //term three      
                   $enrolled_grade_count_kgs_t3 = $grade_qry->where(['t2.entry_term'=>3,"t2.entry_year"=>$year])->sum('kgs_enrolled');//enrolled
                   $finished_grade_count_kgs_t3 = $grade_qry->where(['t2.entry_term'=>3,"t2.entry_year"=>$year])->sum('kgs_started');
                        

                   $kgs_enrolled_annually=0;
                   $annual_value_to_divide_with=0;
                    if($enrolled_grade_count_kgs_t1>0)
                    {
                        $kgs_enrolled_annually=$enrolled_grade_count_kgs_t1;
                        $annual_value_to_divide_with+=1;
                    }
                   
                    if($enrolled_grade_count_kgs_t2>0)
                    {
                        $kgs_enrolled_annually=$enrolled_grade_count_kgs_t2;
                        $annual_value_to_divide_with+=1;
                    }
                    if($enrolled_grade_count_kgs_t3>0)
                    {
                        $kgs_enrolled_annually=$enrolled_grade_count_kgs_t3;
                        $annual_value_to_divide_with+=1;
                    }

                    if($kgs_enrolled_annually>0){
                        
                    $kgs_enrolled_annually=$kgs_enrolled_annually/$annual_value_to_divide_with;
                    }
                    
                    $kgs_finished_annually=0;
                    $annual_value_to_divide_with_finished=0;
                    if($finished_grade_count_kgs_t1>0)
                    {
                        $kgs_finished_annually=$finished_grade_count_kgs_t1;
                        $annual_value_to_divide_with_finished+=1;
                    }
                    if($finished_grade_count_kgs_t2>0)
                    {
                        $kgs_finished_annually=$finished_grade_count_kgs_t2;
                        $annual_value_to_divide_with_finished+=1;
                    }
                    if($finished_grade_count_kgs_t3>0)
                    {
                        $kgs_finished_annually=$finished_grade_count_kgs_t3;
                        $annual_value_to_divide_with_finished+=1;
                    }


                    $percentage_kgs_girls_annually =0;
                    if($kgs_finished_annually!=0 || $kgs_enrolled_annually!=0  ){
                        $percentage_kgs_girls_annually = round(($kgs_finished_annually/$kgs_enrolled_annually)*100);
                    }
                     
                    $percentage_non_kgs_girls_annually=0;

                    //term one
                    $enrolled_grade_count_non_kgs_t1 = $grade_qry->where(['t2.entry_term'=>1,"t2.entry_year"=>$year])->sum('nonkgs_enrolled');//enrolled
                    $finished_grade_count_non_kgs_t1 = $grade_qry->where(['t2.entry_term'=>1,"t2.entry_year"=>$year])->sum('nonkgs_started');
                     
                     //term two
                     $enrolled_grade_count_non_kgs_t2 = $grade_qry->where(['t2.entry_term'=>2,"t2.entry_year"=>$year])->sum('nonkgs_enrolled');//enrolled
                     $finished_grade_count_non_kgs_t2 = $grade_qry->where(['t2.entry_term'=>2,"t2.entry_year"=>$year])->sum('nonkgs_started');
                    
                     //term three
                     $enrolled_grade_count_non_kgs_t3 = $grade_qry->where(['t2.entry_term'=>3,"t2.entry_year"=>$year])->sum('nonkgs_enrolled');//enrolled
                     $finished_grade_count_non_kgs_t3 = $grade_qry->where(['t2.entry_term'=>3,"t2.entry_year"=>$year])->sum('nonkgs_started');
                    

                     $non_kgs_enrolled_annually=0;
                    $annual_value_to_divide_with_non_kgs=0;
                    if($enrolled_grade_count_non_kgs_t1>0)
                    {
                        $non_kgs_enrolled_annually=$enrolled_grade_count_non_kgs_t1;
                        $annual_value_to_divide_with_non_kgs+=1;
                    }
                   
                    if($enrolled_grade_count_non_kgs_t2>0)
                    {
                        $non_kgs_enrolled_annually=$enrolled_grade_count_non_kgs_t2;
                        $annual_value_to_divide_with_non_kgs+=1;
                    }
                    if($enrolled_grade_count_non_kgs_t3>0)
                    {
                        $non_kgs_enrolled_annually=$enrolled_grade_count_non_kgs_t3;
                        $annual_value_to_divide_with_non_kgs+=1;
                    }

                    if($non_kgs_enrolled_annually>0){
                        
                    $kgs_enrolled_annually=$kgs_enrolled_annually/$annual_value_to_divide_with;
                    }
                    
                    $non_kgs_finished_annually=0;
                    $annual_value_to_divide_with_finished_non_kgs=0;
                    if($finished_grade_count_non_kgs_t1>0)
                    {
                        $non_kgs_finished_annually=$finished_grade_count_non_kgs_t1;
                        $annual_value_to_divide_with_finished_non_kgs+=1;
                    }
                    if($finished_grade_count_non_kgs_t2>0)
                    {
                        $non_kgs_finished_annually=$finished_grade_count_non_kgs_t2;
                        $annual_value_to_divide_with_finished_non_kgs+=1;
                    }
                    if($finished_grade_count_non_kgs_t3>0)
                    {
                        $non_kgs_finished_annually=$finished_grade_count_non_kgs_t3;
                        $annual_value_to_divide_with_finished_non_kgs+=1;
                    }


                    $percentage_non_kgs_girls_annually =0;
                    if($non_kgs_finished_annually!=0 || $non_kgs_enrolled_annually!=0  ){
                        $percentage_non_kgs_girls_annually = round(($non_kgs_finished_annually/$non_kgs_enrolled_annually)*100);
                    }
                    
                 
                    $summary[]=array(
                        'year_id'=>$year,
                        "grade"=>$grade,
                        "kgs_ben_value"=> $percentage_kgs_girls_annually,
                        "non_kgs_ben_value"=>$percentage_non_kgs_girls_annually
                    );
          }

        }
        return $summary;
    }

    public function determineKPI41Performance($year,$filterdatefrom,$filterdateto,$filterprovince,$filterdistrict)
    {

                $progression_qry=DB::table('mne_institutional_info as t1')
                      ->join('mne_datacollectiontool_dataentry_basicinfo as t2','t2.id','t1.record_id')
                       ->join('mne_datacollection_tools as t3','t3.id','t2.datacollection_tool_id')
                       ->join('mne_progression_info as t4','t4.id','t1.record_id')
                       ->where(["t2.datacollection_tool_id"=>1]);
               //Filter
                if(validateisNumeric($filterprovince)){
                    $progression_qry->where('t2.province_id',$filterprovince);      
                }
                if(validateisNumeric($filterdistrict)){
                    $progression_qry->where('t2.district_id',$filterdistrict);
                }
                if($filterdatefrom && $filterdateto){
                      $progression_qry->whereBetween('t1.survey_completion_date',[$filterdatefrom." 00:00:00",$filterdateto." 00:00:00"]);
                    //Calculate based on the IDs
                        $enrolled_grade_count_kgs_t1 = $progression_qry->sum('t4.kgs_started');//enrolled
                       $finished_grade_count_kgs_t1 = $progression_qry->sum('t4.kgs_finished');
           
                }else{
                    $progression_qry->where('t2.entry_year',$year);
                    $enrolled_grade_count_kgs_t1 = $progression_qry->where(["t2.entry_year"=>$year])->sum('t4.kgs_started');//enrolled
                    $finished_grade_count_kgs_t1 = $progression_qry->where(["t2.entry_year"=>$year])->sum('t4.kgs_finished');
                }

               $kgs_enrolled_annually=0;
               $annual_value_to_divide_with=0;
                if($enrolled_grade_count_kgs_t1>0)
                {
                    $kgs_enrolled_annually=$enrolled_grade_count_kgs_t1;
                    $annual_value_to_divide_with+=1;
                }

                if($kgs_enrolled_annually>0){
                    
                $kgs_enrolled_annually=$kgs_enrolled_annually/$annual_value_to_divide_with;
                }
                
                $kgs_finished_annually=0;
                $annual_value_to_divide_with_finished=0;
                if($finished_grade_count_kgs_t1>0)
                {
                    $kgs_finished_annually=$finished_grade_count_kgs_t1;
                    $annual_value_to_divide_with_finished+=1;
                }


                $percentageMet =0;
                if($kgs_finished_annually!=0 && $kgs_enrolled_annually!=0  ){
                    $percentageMet = round(($kgs_finished_annually/$kgs_enrolled_annually)*100);
                }
        return $percentageMet;
    }
    public function calculateKPI40(Request $request)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');

        if(!validateisNumeric($year_from))
        {
            $year_from=2016;
        }

        if(!validateisNumeric($year_to))
        {
            $year_to=date('Y');
        }

        $years=[];
        if($year_from==$year_to)
        {
            $years[]=$year_from;
        }else{
            $total_year_from=($year_to-$year_from)+1;
            for($i=0;$i<$total_year_from;$i++)
            {
                $year_to_use=$year_from+$i;
                $years[]=$year_to_use;
            }
        }
        $kgs_enrolled_data=[];
        $kgs_dropouts_data=[];
        $non_kgs_dropouts_data=[];
        $percentages=[];
        foreach($years as $year){

            $kgs_enrolled_qry=DB::table('mne_datacollection_tools as t1')
            ->join('mne_datacollectiontool_dataentry_basicinfo as t2','t2.datacollection_tool_id','t1.id')
            ->join('mne_spotcheck_kgs_girl_enrolments as t3','t3.record_id','t2.id')
            ->where('t2.datacollection_tool_id',1)
            ->GroupBy('t2.datacollection_tool_id')
            ->select(DB::raw("SUM(kgsgirls_grade_eight+kgsgirls_grade_nine+kgsgirls_grade_ten+kgsgirls_grade_eleven+kgsgirls_grade_twelve) as count"));
            //->get();

            $kgs_enrolled_qry=$this->filterKPIQueryLocationWise($kgs_enrolled_qry,'t2',$province_id,$district_id,$school_id);
            // if (isset($school_id) && $school_id != '') {
            //     $kgs_enrolled_qry->where('t2.id', $school_id);
            // } else if (isset($district_id) && $district_id != '') {
            //     $kgs_enrolled_qry->where('t2.district_id', $district_id);
            // } else if (isset($province_id) && $province_id != '') {
            //     $kgs_enrolled_qry->where('t2.province_id', $province_id);
            // }
            $kgs_enrolled_t1=$kgs_enrolled_qry->where(['t2.entry_term'=>1,"t2.entry_year"=>$year])->get()->toArray();
            $kgs_enrolled_t2=$kgs_enrolled_qry->where(['t2.entry_term'=>2,"t2.entry_year"=>$year])->get()->toArray();
            $kgs_enrolled_t3=$kgs_enrolled_qry->where(['t2.entry_term'=>3,"t2.entry_year"=>$year])->get()->toArray();
            $kgs_enrolled_annually=0;
            $annual_value_to_divide_with=0;
            if(count($kgs_enrolled_t1)>0 && $kgs_enrolled_t1[0]->count>0)
            {
                $kgs_enrolled_annually=$kgs_enrolled_t1[0]->count;
                $annual_value_to_divide_with+=1;
            }
            if(count($kgs_enrolled_t2)>0 && $kgs_enrolled_t2[0]->count>0)
            {
                $kgs_enrolled_annually+=$kgs_enrolled_t2[0]->count;
                $annual_value_to_divide_with+=1;

            }
            if(count($kgs_enrolled_t3)>0 && $kgs_enrolled_t3[0]->count>0)
            {
                $kgs_enrolled_annually+=$kgs_enrolled_t3[0]->count;
                $annual_value_to_divide_with+=1;

            }
            if($kgs_enrolled_annually>0){
                
            $kgs_enrolled_annually=$kgs_enrolled_annually/$annual_value_to_divide_with;
            }
            $kgs_enrolled_data[$year]=array(
                "t1"=> count($kgs_enrolled_t1)>0?$kgs_enrolled_t1[0]->count:0,
                "t2"=>count($kgs_enrolled_t2)>0?$kgs_enrolled_t2[0]->count:0,
                "t3"=>count($kgs_enrolled_t3)>0?$kgs_enrolled_t3[0]->count:0,
                "annually"=>$kgs_enrolled_annually
            );
            

            //dropouts
            $kgs_dropouts_query=DB::table('mne_datacollection_tools as t1')
            ->join('mne_datacollectiontool_dataentry_basicinfo as t2','t2.datacollection_tool_id','t1.id')
            ->join('mne_spotcheck_dropouts_info as t3','t3.record_id','t2.id')
            ->where('t2.datacollection_tool_id',1)
            ->GroupBy('t2.datacollection_tool_id')
            ->select(DB::raw("SUM(kgs_grade_eight+kgs_grade_nine+kgs_grade_ten+kgs_grade_eleven+kgs_grade_twelve) as count"));
            $kgs_dropouts_query=$this->filterKPIQueryLocationWise($kgs_dropouts_query,'t2',$province_id,$district_id,$school_id);
           
            $kgs_dropout_t1= $kgs_dropouts_query->where(['t2.entry_term'=>1,"t2.entry_year"=>$year])->get()->toArray();
            $kgs_dropout_t2= $kgs_dropouts_query->where(['t2.entry_term'=>2,"t2.entry_year"=>$year])->get()->toArray();
            $kgs_dropout_t3= $kgs_dropouts_query->where(['t2.entry_term'=>3,"t2.entry_year"=>$year])->get()->toArray();
            $kgs_dropout_annually=0;
            $annual_value_to_divide_with_kgs_dropouts=0;
            if(count( $kgs_dropout_t1)>0 &&  $kgs_dropout_t1[0]->count>0)
            {
                $kgs_dropout_annually= $kgs_dropout_t1[0]->count;
                $annual_value_to_divide_with_kgs_dropouts+=1;
            }
            if(count( $kgs_dropout_t2)>0 &&  $kgs_dropout_t2[0]->count>0)
            {
                $kgs_dropout_annually= $kgs_dropout_t2[0]->count;
                $annual_value_to_divide_with_kgs_dropouts+=1;
            }
            if(count( $kgs_dropout_t3)>0 &&  $kgs_dropout_t3[0]->count>0)
            {
                $kgs_dropout_annually= $kgs_dropout_t3[0]->count;
                $annual_value_to_divide_with_kgs_dropouts+=1;
            }
            if( $kgs_dropout_annually>0){
                
                $kgs_dropout_annually= $kgs_dropout_annually/$annual_value_to_divide_with_kgs_dropouts;
            }

            $kgs_dropouts_data[$year]=array(
                "t1"=> count($kgs_dropout_t1)>0?$kgs_dropout_t1[0]->count:0,
                "t2"=>count($kgs_dropout_t2)>0?$kgs_dropout_t2[0]->count:0,
                "t3"=>count($kgs_dropout_t3)>0?$kgs_dropout_t3[0]->count:0,
                "annually"=>$kgs_dropout_annually
            );
            // if( $kgs_dropout_t1>0 || $kgs_dropout_t2>0 ||  $kgs_dropout_t3>0)
            // {
            //     $kgs_dropout_annually=($kgs_dropout_t1+$kgs_dropout_t2+$kgs_dropout_t3)/3;
            // }
            // $kgs_dropout_data=array(
            //     "t1"=>$kgs_dropout_t1,
            //     "t2"=>$kgs_dropout_t2,
            //     "t3"=>$kgs_dropout_t3,
            // );
         
            $non_kgs_dropouts_query=DB::table('mne_datacollection_tools as t1')
            ->join('mne_datacollectiontool_dataentry_basicinfo as t2','t2.datacollection_tool_id','t1.id')
            ->join('mne_spotcheck_dropouts_info as t3','t3.record_id','t2.id')
            ->where('t2.datacollection_tool_id',1)
            ->GroupBy('t2.datacollection_tool_id')
            ->select(DB::raw("SUM(non_kgs_grade_eight+non_kgs_grade_nine+non_kgs_grade_ten+non_kgs_grade_eleven+non_kgs_grade_twelve) as count"));
            
            $non_kgs_dropouts_query=$this->filterKPIQueryLocationWise( $non_kgs_dropouts_query,'t2',$province_id,$district_id,$school_id);
            $non_kgs_dropout_t1= $non_kgs_dropouts_query->where(['t2.entry_term'=>1,"t2.entry_year"=>$year])->get()->toArray();
            $non_kgs_dropout_t2= $non_kgs_dropouts_query->where(['t2.entry_term'=>2,"t2.entry_year"=>$year])->get()->toArray();
            $non_kgs_dropout_t3= $non_kgs_dropouts_query->where(['t2.entry_term'=>3,"t2.entry_year"=>$year])->get()->toArray();
            $non_kgs_dropout_annually=0;

            $annual_value_to_divide_with_non_kgs_dropouts=0;
            if(count($non_kgs_dropout_t1)>0 &&   $non_kgs_dropout_t1[0]->count>0)
            {
                $non_kgs_dropout_annually=  $non_kgs_dropout_t1[0]->count;
                $annual_value_to_divide_with_non_kgs_dropouts+=1;
            }
            if(count(  $non_kgs_dropout_t2)>0 &&   $non_kgs_dropout_t2[0]->count>0)
            {
                $non_kgs_dropout_annually=  $non_kgs_dropout_t2[0]->count;
                $annual_value_to_divide_with_non_kgs_dropouts+=1;
            }
            if(count($non_kgs_dropout_t3)>0 &&   $non_kgs_dropout_t3[0]->count>0)
            {
                $non_kgs_dropout_annually=  $non_kgs_dropout_t3[0]->count;
                $annual_value_to_divide_with_non_kgs_dropouts+=1;
            }
            // if( $non_kgs_dropout_annually>0){
                
            //     $non_kgs_dropout_annually= $non_kgs_dropout_annually/$annual_value_to_divide_with_non_kgs_dropouts;
            // }

            $non_kgs_dropouts_data[$year]=array(
                "t1"=> count($non_kgs_dropout_t1)>0?$non_kgs_dropout_t1[0]->count:0,
                "t2"=>count($non_kgs_dropout_t2)>0?$non_kgs_dropout_t2[0]->count:0,
                "t3"=>count($non_kgs_dropout_t3)>0?$non_kgs_dropout_t3[0]->count:0,
                "annually"=>$non_kgs_dropout_annually
            );




            // if( $non_kgs_dropout_t1>0 || $non_kgs_dropout_t2>0 ||  $non_kgs_dropout_t3>0)
            // {
            //     $non_kgs_dropout_annually=($non_kgs_dropout_t1+$non_kgs_dropout_t2+$non_kgs_dropout_t3)/3;
            // }
            // $non_kgs_dropout_data=array(
            //     "t1"=>$non_kgs_dropout_t1,
            //     "t2"=>$non_kgs_dropout_t2,
            //     "t3"=>$non_kgs_dropout_t3,
            // );
            //$kgs_enrolled_t1= $kgs_enrolled_qry->where(['t2.entry_term'=>1,"t2.entry_year"=>$year]);
             
        } 
        $summary=array();
        foreach($years as $key=>$year)
        {
            $kgs_enrolled=$kgs_enrolled_data[$year];
            $kgs_dropouts=$kgs_dropouts_data[$year];
            $non_kgs_dropouts=$non_kgs_dropouts_data[$year];
            //dd($kgs_enrolled);

            //term one
            $kgs_dropouts_percentages_t1=0;
            if($kgs_enrolled['t1']>0 && $kgs_dropouts['t1']>0)
            {
                $kgs_dropouts_percentages_t1=round(($kgs_dropouts['t1']/$kgs_enrolled['t1'])*100);
            }
            //term two
             $kgs_dropouts_percentages_t2=0;
             if($kgs_enrolled['t2']>0 && $kgs_dropouts['t2']>0)
             {
                 $kgs_dropouts_percentages_t2=round(($kgs_dropouts['t2']/$kgs_enrolled['t2'])*100);
             }
             //term three
             $kgs_dropouts_percentages_t3=0;
             if($kgs_enrolled['t3']>0 && $kgs_dropouts['t3']>0)
             {
                 $kgs_dropouts_percentages_t3=round(($kgs_dropouts['t3']/$kgs_enrolled['t3'])*100);
             }
             //annually
             $kgs_dropouts_percentages_annually=0;
             if($kgs_enrolled['annually']>0 && $kgs_dropouts['annually']>0)
             {
                 $kgs_dropouts_percentages_annually=round(($kgs_dropouts['annually']/$kgs_enrolled['annually'])*100);
             }

            $summary[]=array(
                "year_id"=>$year,
                "kgs_ben_value_t1"=>$kgs_dropouts_percentages_t1,
                "kgs_ben_value_t2"=>$kgs_dropouts_percentages_t2,
                "kgs_ben_value_t3"=>$kgs_dropouts_percentages_t3,
                "kgs_ben_value_an"=>$kgs_dropouts_percentages_annually
                //"kgs_ben_value_an"=>
               
            );

          
            

        }
       
        return $summary;
    }
    //KPI39
    public function KPIBaseQuery39BaseQuery($province_id,$district_id,$school_id)
    {
        $base_query=DB::table('mne_datacollection_tools as t1')
        ->join('mne_datacollectiontool_dataentry_basicinfo as t2','t2.datacollection_tool_id','t1.id')
        ->join('mne_progression_info as t3','t3.record_id','t2.id')
        ->where('t2.datacollection_tool_id',1)
        ->GroupBy('t2.datacollection_tool_id')
        ->where('transition_id',12);
        $base_query=$this->filterKPIQueryLocationWise($base_query,'t2',$province_id,$district_id,$school_id);
        return $base_query;
    }
    public function calculateKPI39(Request $request)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');

        if(!validateisNumeric($year_from))
        {
            $year_from=2016;
        }

        if(!validateisNumeric($year_to))
        {
            $year_to=date('Y');
        }

        $years=[];
        if($year_from==$year_to)
        {
            $years[]=$year_from;
        }else{
            $total_year_from=($year_to-$year_from)+1;
            for($i=0;$i<$total_year_from;$i++)
            {
                $year_to_use=$year_from+$i;
                $years[]=$year_to_use;
            }
        }
        $yearly_targets_data=[];
        $percentages=[];
        foreach($years as $year){
            //targets
            $target_data=DB::table('mne_kpis_targets')->where(['year'=>$year,'kpi_id'=>39])->selectraw('target_val,target_type')->get()->toArray();

            if(is_array($target_data) && count($target_data)>0){
             $yearly_targets_data[$year]=$target_data;
             }else{
              $yearly_targets_data[$year][0]=(object)(array("target_val"=>0,"target_type"=>0));
             }

             
          

          

            //learners who started
            $grade_12_qry=DB::table('mne_datacollection_tools as t1')
            ->join('mne_datacollectiontool_dataentry_basicinfo as t2','t2.datacollection_tool_id','t1.id')
            ->join('mne_progression_info as t3','t3.record_id','t2.id')
            ->where('t2.datacollection_tool_id',1)
            ->GroupBy('t2.datacollection_tool_id')
            ->where('transition_id',12);
            // $grade_12_qry= Db::table('school_information as t1')
            // ->join('mne_datacollectiontool_dataentry_basicinfo as t2','t1.id','t2.school_id')
            // ->join('mne_progression_info as t3','t3.record_id','t2.id')
            // ->where(['t2.entry_term'=>1,"t2.entry_year"=>$year])
            // ->where('transition_id',12);

            $grade_12_qry=$this->filterKPIQueryLocationWise( $grade_12_qry,'t2',$province_id,$district_id,$school_id);
            

            $enrolled_grade_12_count_kgs_t1=$this->KPIBaseQuery39BaseQuery($province_id,$district_id,$school_id)->where(['t2.entry_term'=>1,"t2.entry_year"=>$year])->sum('kgs_enrolled');
            //$enrolled_grade_12_count_kgs_t1 = $grade_12_qry->where(['t2.entry_term'=>1,"t2.entry_year"=>$year])->sum('kgs_enrolled');//enrolled
            $finished_grade_12_count_kgs_t1 =$this->KPIBaseQuery39BaseQuery($province_id,$district_id,$school_id)->where(['t2.entry_term'=>1,"t2.entry_year"=>$year])->sum('kgs_finished');
            $enrolled_grade_12_count_kgs_t2 =$this->KPIBaseQuery39BaseQuery($province_id,$district_id,$school_id)->where(['t2.entry_term'=>2,"t2.entry_year"=>$year])->sum('kgs_enrolled');//enrolled
            $finished_grade_12_count_kgs_t2 = $this->KPIBaseQuery39BaseQuery($province_id,$district_id,$school_id)->where(['t2.entry_term'=>2,"t2.entry_year"=>$year])->sum('kgs_finished');
            $enrolled_grade_12_count_kgs_t3 = $this->KPIBaseQuery39BaseQuery($province_id,$district_id,$school_id)->where(['t2.entry_term'=>3,"t2.entry_year"=>$year])->sum('kgs_enrolled');//enrolled
            $finished_grade_12_count_kgs_t3 = $this->KPIBaseQuery39BaseQuery($province_id,$district_id,$school_id)->where(['t2.entry_term'=>3,"t2.entry_year"=>$year])->sum('kgs_finished');
           
            $kgs_grade_12_enrolled_annually=0;
            $annual_value_to_divide_with=0;
              
             if($enrolled_grade_12_count_kgs_t1>0)
          {
            $kgs_grade_12_enrolled_annually=$enrolled_grade_12_count_kgs_t1;
            $annual_value_to_divide_with+=1;
          }
          if($enrolled_grade_12_count_kgs_t2>0)
          {
            $kgs_grade_12_enrolled_annually=$enrolled_grade_12_count_kgs_t2;
            $annual_value_to_divide_with+=1;
          }
          if($enrolled_grade_12_count_kgs_t3>0)
          {
            $kgs_grade_12_enrolled_annually=$enrolled_grade_12_count_kgs_t3;
            $annual_value_to_divide_with+=1;
          }
           if($kgs_grade_12_enrolled_annually>0){
              
            $kgs_grade_12_enrolled_annually= $kgs_grade_12_enrolled_annually/$annual_value_to_divide_with;
          }



          $kgs_grade_12_finished_annually=0;
          $annual_value_to_divide_with_finished=0;
            
           if($finished_grade_12_count_kgs_t1>0)
        {
            $kgs_paid_for_annually=$finished_grade_12_count_kgs_t1;
            $annual_value_to_divide_with_finished+=1;
        }
        if($finished_grade_12_count_kgs_t2>0)
        {
            $kgs_paid_for_annually=$finished_grade_12_count_kgs_t2;
            $annual_value_to_divide_with_finished+=1;
        }
        if($finished_grade_12_count_kgs_t3>0)
        {
            $kgs_paid_for_annually=$finished_grade_12_count_kgs_t3;
            $annual_value_to_divide_with_finished+=1;
        }
         if($kgs_grade_12_enrolled_annually>0){
            
            $kgs_grade_12_finished_annually=  $kgs_grade_12_finished_annually/$annual_value_to_divide_with_finished;
        }
        $finished_grade_12_count_kgs= $kgs_grade_12_finished_annually;
        $enrolled_grade_12_count_kgs=$kgs_grade_12_enrolled_annually;
        


            $percentage_kgs_girls_grade_12 =0;
            if($finished_grade_12_count_kgs!=0 || $enrolled_grade_12_count_kgs!=0  ){
            $percentage_kgs_girls_grade_12 =    round(($finished_grade_12_count_kgs/$enrolled_grade_12_count_kgs)*100);
            }
            $percentage_non_kgs=0;
            $enrolled_grade_12_count_non_kgs_t1=$this->KPIBaseQuery39BaseQuery($province_id,$district_id,$school_id)->where(['t2.entry_term'=>1,"t2.entry_year"=>$year])->sum('nonkgs_enrolled');
            //$enrolled_grade_12_count_non_kgs_t1 = $grade_12_qry->where(['t2.entry_term'=>1,"t2.entry_year"=>$year])->sum('nonkgs_enrolled');//enrolled
            $finished_grade_12_count_non_kgs_t1 =$this->KPIBaseQuery39BaseQuery($province_id,$district_id,$school_id)->where(['t2.entry_term'=>1,"t2.entry_year"=>$year])->sum('nonkgs_started');
            $enrolled_grade_12_count_non_kgs_t2 = $this->KPIBaseQuery39BaseQuery($province_id,$district_id,$school_id)->where(['t2.entry_term'=>2,"t2.entry_year"=>$year])->sum('nonkgs_enrolled');//enrolled
            $finished_grade_12_count_non_kgs_t2 =$this->KPIBaseQuery39BaseQuery($province_id,$district_id,$school_id)->where(['t2.entry_term'=>2,"t2.entry_year"=>$year])->sum('nonkgs_started');
            $enrolled_grade_12_count_non_kgs_t3 = $this->KPIBaseQuery39BaseQuery($province_id,$district_id,$school_id)->where(['t2.entry_term'=>3,"t2.entry_year"=>$year])->sum('nonkgs_enrolled');//enrolled
            $finished_grade_12_count_non_kgs_t3 = $this->KPIBaseQuery39BaseQuery($province_id,$district_id,$school_id)->where(['t2.entry_term'=>3,"t2.entry_year"=>$year])->sum('nonkgs_started');
           
            
            $kgs_grade_12_enrolled_annually_non_kgs=0;
            $annual_value_to_divide_with_non_kgs=0;
              
             if($enrolled_grade_12_count_non_kgs_t1>0)
          {
            $kgs_grade_12_enrolled_annually_non_kgs=$enrolled_grade_12_count_non_kgs_t1;
            $annual_value_to_divide_with_non_kgs+=1;
          }
          if($enrolled_grade_12_count_non_kgs_t2>0)
          {
            $kgs_grade_12_enrolled_annually_non_kgs=$enrolled_grade_12_count_non_kgs_t2;
            $annual_value_to_divide_with_non_kgs+=1;
          }
          if($enrolled_grade_12_count_non_kgs_t3>0)
          {
            $kgs_grade_12_enrolled_annually_non_kgs=$enrolled_grade_12_count_non_kgs_t3;
            $annual_value_to_divide_with_non_kgs+=1;
          }
           if ($kgs_grade_12_enrolled_annually_non_kgs>0){
              
            $kgs_grade_12_enrolled_annually_non_kgs=  $kgs_grade_12_enrolled_annually_non_kgs/$annual_value_to_divide_with_non_kgs;
          }

         
          

          $non_kgs_finished_annually=0;
          $annual_value_to_divide_with_finished_non_kgs=0;
            
           if(  $finished_grade_12_count_non_kgs_t1>0)
        {
            $non_kgs_finished_annually=  $finished_grade_12_count_non_kgs_t1;
            $annual_value_to_divide_with_finished_non_kgs+=1;
        }
        if(  $finished_grade_12_count_non_kgs_t2>0)
        {
            $non_kgs_finished_annually=  $finished_grade_12_count_non_kgs_t2;
            $annual_value_to_divide_with_finished_non_kgs+=1;
        }
        if(  $finished_grade_12_count_non_kgs_t3>0)
        {
            $non_kgs_finished_annually=  $finished_grade_12_count_non_kgs_t3;
            $annual_value_to_divide_with_finished_non_kgs+=1;
        }
         if($non_kgs_finished_annually>0){
            
            $non_kgs_finished_annually=   $non_kgs_finished_annually/$annual_value_to_divide_with_finished_non_kgs;
        }

        
        $finished_grade_12_count_non_kgs= $non_kgs_finished_annually;
        $enrolled_grade_12_count_non_kgs= $kgs_grade_12_enrolled_annually_non_kgs;
          
      
           // if($finished_grade_12_count_non_kgs!=0 || $enrolled_grade_12_count_non_kgs!=0  ){
            if( $finished_grade_12_count_non_kgs !=0 || $enrolled_grade_12_count_non_kgs!=0){
            //$percentage_non_kgs= round (($finished_grade_12_count_non_kgs/$enrolled_grade_12_count_non_kgs)*100);
            $percentage_non_kgs= round (($finished_grade_12_count_non_kgs/ $enrolled_grade_12_count_non_kgs)*100);
            }
            
            $percentages[$year]=array(
                "kgs_girls"=>$percentage_kgs_girls_grade_12,
                "non_kgs_girls"=> $percentage_non_kgs
            );
        }

       

        $summary=[];
      
        foreach($years as $key=>$year)
        {
            $summary[]=array(
                "year_id"=>$year,
                "baseline_val_count"=>$yearly_targets_data[$year][0]->target_val,
                "target_type"=>$yearly_targets_data[$year][0]->target_type,
                "kgs_ben_value"=>$percentages[$year]['kgs_girls'],
                "non_kgs_ben_value"=>  $percentages[$year]['non_kgs_girls'],
        
            );
        }
        return $summary;
    }
    //KPI33
    private function KPI33BaseQueryPaidFor($province_id,$district_id,$school_id)
    {
        $base_query=DB::table('mne_datacollection_tools as t1')
        ->join('mne_datacollectiontool_dataentry_basicinfo as t2','t2.datacollection_tool_id','t1.id')
        ->join('mne_spotcheck_boardingfacility as t3','t3.record_id','t2.id')
        ->where('t2.datacollection_tool_id',1)
        ->GroupBy('t2.datacollection_tool_id');
        $base_query=$this->filterKPIQueryLocationWise($base_query,'t2',$province_id,$district_id,$school_id);
        return $base_query;
    }
    private function KPI33BaseQueryEligible($province_id,$district_id,$school_id)
    {
              
        $base_query=DB::table('mne_datacollection_tools as t1')
        ->join('mne_datacollectiontool_dataentry_basicinfo as t2','t2.datacollection_tool_id','t1.id')
        ->join('mne_spotcheck_kgs_girl_enrolments as t3','t3.record_id','t2.id')
        ->where('t2.datacollection_tool_id',1)
        ->GroupBy('t2.datacollection_tool_id');
       
        $base_query=$this->filterKPIQueryLocationWise( $base_query,'t2',$province_id,$district_id,$school_id);
        return $base_query;
    }
    public function calculateKPI33(Request $request)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');

        if(!validateisNumeric($year_from))
        {
            $year_from=2016;
        }

        if(!validateisNumeric($year_to))
        {
            $year_to=date('Y');
        }

        $years=[];
        if($year_from==$year_to)
        {
            $years[]=$year_from;
        }else{
            $total_year_from=($year_to-$year_from)+1;
            for($i=0;$i<$total_year_from;$i++)
            {
                $year_to_use=$year_from+$i;
                $years[]=$year_to_use;
            }
        }
        $yearly_targets_data=[];
        //get eligible girls
        $yearly_count_eligible=[];
        

        //get disabled and non-disabled girls


        $year_not_disabled_values=[];
        $year_disabled_values=[];
        $yearly_count_paid_for=[];
        foreach($years as $year){
            $target_data=DB::table('mne_kpis_targets')->where(['year'=>$year,'kpi_id'=>33])->selectraw('target_val,target_type')->get()->toArray();

            if(is_array($target_data) && count($target_data)>0){
             $yearly_targets_data[$year]=$target_data;
             }else{
              $yearly_targets_data[$year][0]=(object)(array("target_val"=>0,"target_type"=>0));
             }

            $non_disabled_count_t1=$this->KPI34BaseQuery($year,1,$province_id,$district_id,$school_id)->sum('kgsgirls_without_disability');
            $disabled_count_t1= $this->KPI34BaseQuery($year,1,$province_id,$district_id,$school_id)->sum('kgsgirls_with_disability');
            $non_disabled_count_t2=$this->KPI34BaseQuery($year,2,$province_id,$district_id,$school_id)->sum('kgsgirls_without_disability');
            $disabled_count_t2= $this->KPI34BaseQuery($year,2,$province_id,$district_id,$school_id)->sum('kgsgirls_with_disability');
            $non_disabled_count_t3=$this->KPI34BaseQuery($year,3,$province_id,$district_id,$school_id)->sum('kgsgirls_without_disability');
            $disabled_count_t3= $this->KPI34BaseQuery($year,3,$province_id,$district_id,$school_id)->sum('kgsgirls_with_disability');
           

            $kgs_supported_disabled_annually=0;
            $annual_value_to_divide_with=0;
             if( $disabled_count_t1>0)
             {
                $kgs_supported_disabled_annually= $disabled_count_t1;
                 $annual_value_to_divide_with+=1;
             }
             if( $disabled_count_t2>0)
             {
                $kgs_supported_disabled_annually= $disabled_count_t2;
                 $annual_value_to_divide_with+=1;
             }
             if( $disabled_count_t3>0)
             {
                $kgs_supported_disabled_annually= $disabled_count_t3;
                 $annual_value_to_divide_with+=1;
             }
 
             if( $kgs_supported_disabled_annually>0){
                 
                $kgs_supported_disabled_annually= $kgs_supported_disabled_annually/$annual_value_to_divide_with;
             }



             $kgs_supported_non_disabled_annually=0;
            $annual_value_to_divide_with_non_disabled=0;
             if( $non_disabled_count_t1>0)
             {
                $kgs_supported_non_disabled_annually= $non_disabled_count_t1;
                 $annual_value_to_divide_with_non_disabled+=1;
             }
             if( $non_disabled_count_t2>0)
             {
                $kgs_supported_non_disabled_annually= $non_disabled_count_t2;
                 $annual_value_to_divide_with_non_disabled+=1;
             }
             if( $non_disabled_count_t3>0)
             {
                $kgs_supported_non_disabled_annually= $non_disabled_count_t3;
                $annual_value_to_divide_with_non_disabled+=1;
             }
 
             if( $kgs_supported_non_disabled_annually>0){
                 
                $kgs_supported_non_disabled_annually= $kgs_supported_non_disabled_annually/$annual_value_to_divide_with_non_disabled;
             }
           
             $paid_for_count_t1 = $this->KPI33BaseQueryPaidFor($province_id,$district_id,$school_id)->where(['t2.entry_term'=>1,"t2.entry_year"=>$year])->sum('kgsgirls_paidfor');
            $paid_for_count_t2 = $this->KPI33BaseQueryPaidFor($province_id,$district_id,$school_id) ->where(['t2.entry_term'=>2,"t2.entry_year"=>$year])->sum('kgsgirls_paidfor');
            $paid_for_count_t3 = $this->KPI33BaseQueryPaidFor($province_id,$district_id,$school_id) ->where(['t2.entry_term'=>3,"t2.entry_year"=>$year])->sum('kgsgirls_paidfor');
           

            $kgs_paid_for_annually=0;
            $annual_value_to_divide_with_paid_for=0;
             if( $paid_for_count_t1>0)
             {
                $kgs_paid_for_annually= $disabled_count_t1;
                $annual_value_to_divide_with_paid_for+=1;
             }
             if(  $paid_for_count_t2>0)
             {
                $kgs_paid_for_annually= $disabled_count_t2;
                $annual_value_to_divide_with_paid_for+=1;
             }
             if( $paid_for_count_t3>0)
             {
                $kgs_paid_for_annually= $disabled_count_t3;
                $annual_value_to_divide_with_paid_for+=1;
             }
 
             if( $kgs_paid_for_annually>0){
                 
                $kgs_paid_for_annually= $kgs_paid_for_annually/$annual_value_to_divide_with_paid_for;
             }
         
            
            $eligible_count_t1=$this->KPI33BaseQueryEligible($province_id,$district_id,$school_id)->where(['t2.entry_term'=>1,"t2.entry_year"=>$year])->select(DB::raw("SUM(t3.kgsgirls_grade_eight+t3.kgsgirls_grade_nine+kgsgirls_grade_ten+kgsgirls_grade_eleven+kgsgirls_grade_twelve) as count"))->get();
            $eligible_count_t2= $this->KPI33BaseQueryEligible($province_id,$district_id,$school_id)->where(['t2.entry_term'=>2,"t2.entry_year"=>$year])->select(DB::raw("SUM(t3.kgsgirls_grade_eight+t3.kgsgirls_grade_nine+kgsgirls_grade_ten+kgsgirls_grade_eleven+kgsgirls_grade_twelve) as count"))->get();
            $eligible_count_t3= $this->KPI33BaseQueryEligible($province_id,$district_id,$school_id)->where(['t2.entry_term'=>3,"t2.entry_year"=>$year])->select(DB::raw("SUM(t3.kgsgirls_grade_eight+t3.kgsgirls_grade_nine+kgsgirls_grade_ten+kgsgirls_grade_eleven+kgsgirls_grade_twelve) as count"))->get();
           
            $kgs_eligible_annually=0;
            $annual_value_to_divide_with_eligible=0;
             if(count($eligible_count_t1)>0 && $eligible_count_t1[0]->count )
             {
                $kgs_eligible_annually= $eligible_count_t1[0]->count;
                $annual_value_to_divide_with_eligible+=1;
             }
             if(count($eligible_count_t2)>0 && $eligible_count_t2[0]->count )
             {
                $kgs_eligible_annually=$eligible_count_t2[0]->count;
                $annual_value_to_divide_with_eligible+=1;
             }
             if(count($eligible_count_t3)>0 && $eligible_count_t3[0]->count )
             {
                $kgs_eligible_annually= $eligible_count_t3[0]->count;
                $annual_value_to_divide_with_eligible+=1;
             }
 
             if($kgs_eligible_annually>0){
                 
                $kgs_eligible_annually=  $kgs_eligible_annually/$annual_value_to_divide_with_eligible;
             }

             $paid_for_percentage=0;
             if($kgs_eligible_annually>0 && $kgs_paid_for_annually>0)
             {
                 $paid_for_percentage=($kgs_eligible_annually/$kgs_paid_for_annually)*100;
             }
             $disabled_annual_percentage=0;
             $non_disabled_annual_percentage=0;
             if($kgs_supported_disabled_annually>0  ||  $kgs_supported_non_disabled_annually>0)
              {
                $disabled_annual_percentage=round(($kgs_supported_disabled_annually/($kgs_supported_disabled_annually+ $kgs_supported_non_disabled_annually))*100);
                $non_disabled_annual_percentage=round(($kgs_supported_non_disabled_annually/($kgs_supported_disabled_annually+$kgs_supported_non_disabled_annually))*100);
             }
             
            $year_not_disabled_values[$year]= $kgs_supported_non_disabled_annually;
            $year_disabled_values[$year]=  $kgs_supported_disabled_annually;
            $yearly_count_paid_for[$year]=$kgs_paid_for_annually;
            $yearly_count_eligible[$year]= $kgs_eligible_annually;
        }
    
        $percentages=[];
        foreach($yearly_count_paid_for as $key=>$done_values)
        {
            //$expected=count($yearly_count_eligible[$key])>0?$yearly_count_eligible[$key][0]->count:0;
            $expected= $yearly_count_eligible[$key];
            //$achieved=$done_values[0]->count;
            $achieved=$done_values;
          
            if($expected!=0 || $achieved!=0)
            {   $achieved_percentages=round(($achieved*100)/$expected);
                $percentages[$key]=$achieved_percentages;
            }else{
                $percentages[$key]=0;
            }
            

        }
       
        $summary=[];
        foreach($years as $key=>$year)
        {
            $summary[]=array(
                "year_id"=>$year,
                "baseline_val_count"=>$yearly_targets_data[$year][0]->target_val,
                "target_type"=>$yearly_targets_data[$year][0]->target_type,
                "disabled_count_value"=>$year_disabled_values[$year],
                "not_disabled_count_baseline"=>  $year_not_disabled_values[$year],
                "target_achieved"=>$percentages[$year]
            );
        }
        return $summary;

      
    }
    public function calculateKPI33Q(Request $request)
    {

        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');

        if(!validateisNumeric($year_from))
        {
            $year_from=2016;
        }

        if(!validateisNumeric($year_to))
        {
            $year_to=date('Y');
        }
        $years=[];
        if($year_from==$year_to)
        {
            $years[]=$year_from;
        }else{
            $total_year_from=($year_to-$year_from)+1;
            for($i=0;$i<$total_year_from;$i++)
            {
                $year_to_use=$year_from+$i;
                $years[]=$year_to_use;
            }
        }
        
        //payment
        $qry = DB::table('beneficiary_payment_records as t1')
                ->join('beneficiary_enrollments as t2', 't2.id', '=', 't1.enrollment_id')
                ->select(DB::raw('t2.year_of_enrollment,count(DISTINCT t2.beneficiary_id) as no_of_girls'));
        $qry->where('t2.is_validated', 1)
                ->groupBy('t2.year_of_enrollment');

        //end
        $year_not_disabled_values=[];
        $year_disabled_values=[];
        foreach($years as $year){
            $year_from_new="$year/01/01";
            $year_to_new="$year/12/31";
            $qry_not_disabled=Db::table('beneficiary_verification_report as t1')
            ->join('beneficiary_information as t2','t1.beneficiary_id','=','t2.id')
            ->join('beneficiary_enrollments as t3','t1.beneficiary_id','=','t3.beneficiary_id')
            ->join('payment_verificationbatch as t4','t4.id','=','t3.batch_id')
            ->join('beneficiary_payment_records as t5','t5.enrollment_id','=','t3.id')
            ->where(['checklist_item_id'=>10,'response'=>2]);


            // join('beneficiary_enrollments as t1')
            // ->join('payment_verificationbatch as t2','t2.id','=','t1.batch_id')
            // ->join('beneficiary_information as t3','t1.beneficiary_id','=','t3.id')
            // ->join('districts as t4', 't2.district_id', '=', 't4.id')
            // ->join('beneficiary_payment_records as t5','t5.enrollment_id','=','t1.id');



            $qry_not_disabled->whereBetween('t2.enrollment_date', array($year_from_new, $year_to_new));
            if (isset($school_id) && $school_id != '') {
                $qry_not_disabled->where('t2.school_id', $school_id);
            } else if (isset($district_id) && $district_id != '') {
                $qry_not_disabled->where('t2.district_id', $district_id);
            } else if (isset($province_id) && $province_id != '') {
                $qry_not_disabled->where('t2.province_id', $province_id);
            }
            $not_disabled_count=  $qry_not_disabled->count('t1.id');
            $year_not_disabled_values[$year]=$not_disabled_count;
          


            $qry_disabled=Db::table('beneficiary_verification_report as t1')
            ->join('beneficiary_information as t2','t1.beneficiary_id','=','t2.id')
            ->join('beneficiary_enrollments as t3','t1.beneficiary_id','=','t3.beneficiary_id')
            ->join('payment_verificationbatch as t4','t4.id','=','t3.batch_id')
            ->join('beneficiary_payment_records as t5','t5.enrollment_id','=','t3.id')
            ->where(['checklist_item_id'=>10,'response'=>1]);
            $qry_disabled->whereBetween('t2.enrollment_date', array($year_from_new, $year_to_new));
            if (isset($school_id) && $school_id != '') {
                $qry_disabled->where('t2.school_id', $school_id);
            } else if (isset($district_id) && $district_id != '') {
                $qry_disabled->where('t2.district_id', $district_id);
            } else if (isset($province_id) && $province_id != '') {
                $qry_disabled->where('t2.province_id', $province_id);
            }
            $disabled_count=  $qry_disabled->count('t1.id');
            $year_disabled_values[$year]=$disabled_count;
        }
    
        $disabled_percentages=[];
        $not_disabled_percentages=[];
        foreach($year_disabled_values as $key=>$disabled) 
        {
            $disabled_val=$disabled;
            $undisabled_val=$year_not_disabled_values[$key];
            if(  $disabled_val!=0 ||   $undisabled_val!=0)
            {
                $disabled_percentages[]=($disabled_val/($disabled_val+$undisabled_val))*100;
                $not_disabled_percentages[]=($undisabled_val/($disabled_val+$undisabled_val))*100;
              
            }else{
               $disabled_percentages[]=0;
               $not_disabled_percentages[]=0;
            }
        }
        //return $disabled_percentages;
       
        $year_expected_values=[];
        foreach($years as $year)
        {
            $year_from_new="$year/01/01";
            $year_to_new="$year/12/31";
            $qry = DB::table('beneficiary_information')
            ->leftJoin('districts', 'beneficiary_information.district_id', '=', 'districts.id')
            ->join('school_information', 'beneficiary_information.school_id', '=', 'school_information.id')
            //->select('beneficiary_information.*', 'school_information.name as school_name', 'households.id as hh_id', 'households.hhh_fname', 'households.hhh_lname', 'households.hhh_nrc_number', 'households.number_in_cwac', 'districts.name as district_name')
            ->where('beneficiary_information.beneficiary_status', 4)
            ->where('beneficiary_information.enrollment_status', 1)
            ->where('beneficiary_information.verification_recommendation', 1);
          $qry->whereBetween('beneficiary_information.enrollment_date', array($year_from_new, $year_to_new));
        

            if (isset($school_id) && $school_id != '') {
                $qry->where('beneficiary_information.school_id', $school_id);
            } else if (isset($district_id) && $district_id != '') {
                $qry->where('beneficiary_information.district_id', $district_id);
            } else if (isset($province_id) && $province_id != '') {
                $qry->where('beneficiary_information.province_id', $province_id);
            }
            $year_expected_values[$year]=$qry->count();
        }
        //get total eligible students
       
        //return $year_expected_values;
        
        $year_done_values=[];
        //end total eligible
        
        //return $province_id;

        // $total_eligible=DB::table('beneficiary_information as t1')
        //     ->count('t1.id');
        // $total_supported= DB::table('beneficiary_enrollments as t1')
        // ->join('beneficiary_payment_records as t2','t2.enrollment_id','=','t1.id')
        // ->count('t1.id');
       
        foreach($years as $year)
        {
            $year_from_new="$year/01/01";
            $year_to_new="$year/12/31";
            $total_supported_qry= DB::table('beneficiary_enrollments as t1')
            ->join('payment_verificationbatch as t2','t2.id','=','t1.batch_id')
            ->join('beneficiary_information as t3','t1.beneficiary_id','=','t3.id')
            ->leftjoin('districts as t4', 't2.district_id', '=', 't4.id')
            ->join('beneficiary_payment_records as t5','t5.enrollment_id','=','t1.id');
            if (isset($school_id) && $school_id != '') {
            $total_supported_qry->where('t3.school_id', $school_id);
            } else if (isset($district_id) && $district_id != '') {
                $total_supported_qry->where('t3.district_id', $district_id);
            } else if (isset($province_id) && $province_id != '') {
                $total_supported_qry->where('t3.province_id', $province_id);
            }
            $total_supported_qry->whereBetween('t3.enrollment_date', array($year_from_new, $year_to_new));
        
            
            $total_supported=$total_supported_qry->count('t1.id');
        
            $year_done_values[$year]=$total_supported;
        }
        
      // return $year_done_values;
        $percentages=[];
        foreach($year_done_values as $key=>$done_values)
        {
            $expected= $year_expected_values[$key];
            $achieved=$done_values;
          
            if($expected!=0 || $achieved!=0)
            {
                $achieved_percentages=($done_values/($expected+$achieved))*100;
                $percentages[$key]=$achieved_percentages;
            }else{
                $percentages[$key]=0;
            }
            

        }
       
        $summary=[];
        foreach($years as $key=>$year)
        {
            $summary[]=array(
                "year_id"=>$year,
                "baseline_val_count"=>$year_expected_values[$year],
                "disabled_count_value"=> $year_disabled_values[$year],
                "not_disabled_count_baseline"=> $year_not_disabled_values[$year],
                "target_achieved"=>$percentages[$key]
            );
        }
        return $summary;
        //return $percentages;
        //return ($total_supported/$total_eligible)*100;

    }
    public function determineKPI33Performance($year)
    {   
        $province_id = '';
        $district_id = '';
        $school_id = '';
        $year_not_disabled_values=[];
        $year_disabled_values=[];
        $yearly_count_paid_for=[];
            /*        foreach($years as $year){
                        $target_data=DB::table('mne_kpis_targets')->where(['year'=>$year,'kpi_id'=>33])->selectraw('target_val,target_type')->get()->toArray();

                        if(is_array($target_data) && count($target_data)>0){
                         $yearly_targets_data[$year]=$target_data;
                         }else{
                          $yearly_targets_data[$year][0]=(object)(array("target_val"=>0,"target_type"=>0));
                         }
            */
            $non_disabled_count_t1=$this->KPI34BaseQuery($year,1,$province_id,$district_id,$school_id)->sum('kgsgirls_without_disability');
            $disabled_count_t1= $this->KPI34BaseQuery($year,1,$province_id,$district_id,$school_id)->sum('kgsgirls_with_disability');
            $non_disabled_count_t2=$this->KPI34BaseQuery($year,2,$province_id,$district_id,$school_id)->sum('kgsgirls_without_disability');
            $disabled_count_t2= $this->KPI34BaseQuery($year,2,$province_id,$district_id,$school_id)->sum('kgsgirls_with_disability');
            $non_disabled_count_t3=$this->KPI34BaseQuery($year,3,$province_id,$district_id,$school_id)->sum('kgsgirls_without_disability');
            $disabled_count_t3= $this->KPI34BaseQuery($year,3,$province_id,$district_id,$school_id)->sum('kgsgirls_with_disability');
           

            $kgs_supported_disabled_annually=0;
            $annual_value_to_divide_with=0;
             if( $disabled_count_t1>0)
             {
                $kgs_supported_disabled_annually= $disabled_count_t1;
                 $annual_value_to_divide_with+=1;
             }
             if( $disabled_count_t2>0)
             {
                $kgs_supported_disabled_annually= $disabled_count_t2;
                 $annual_value_to_divide_with+=1;
             }
             if( $disabled_count_t3>0)
             {
                $kgs_supported_disabled_annually= $disabled_count_t3;
                 $annual_value_to_divide_with+=1;
             }
 
             if( $kgs_supported_disabled_annually>0){
                 
                $kgs_supported_disabled_annually= $kgs_supported_disabled_annually/$annual_value_to_divide_with;
             }



             $kgs_supported_non_disabled_annually=0;
            $annual_value_to_divide_with_non_disabled=0;
             if( $non_disabled_count_t1>0)
             {
                $kgs_supported_non_disabled_annually= $non_disabled_count_t1;
                 $annual_value_to_divide_with_non_disabled+=1;
             }
             if( $non_disabled_count_t2>0)
             {
                $kgs_supported_non_disabled_annually= $non_disabled_count_t2;
                 $annual_value_to_divide_with_non_disabled+=1;
             }
             if( $non_disabled_count_t3>0)
             {
                $kgs_supported_non_disabled_annually= $non_disabled_count_t3;
                $annual_value_to_divide_with_non_disabled+=1;
             }
 
             if( $kgs_supported_non_disabled_annually>0){
                 
                $kgs_supported_non_disabled_annually= $kgs_supported_non_disabled_annually/$annual_value_to_divide_with_non_disabled;
             }
           
             $paid_for_count_t1 = $this->KPI33BaseQueryPaidFor($province_id,$district_id,$school_id)->where(['t2.entry_term'=>1,"t2.entry_year"=>$year])->sum('kgsgirls_paidfor');
            $paid_for_count_t2 = $this->KPI33BaseQueryPaidFor($province_id,$district_id,$school_id) ->where(['t2.entry_term'=>2,"t2.entry_year"=>$year])->sum('kgsgirls_paidfor');
            $paid_for_count_t3 = $this->KPI33BaseQueryPaidFor($province_id,$district_id,$school_id) ->where(['t2.entry_term'=>3,"t2.entry_year"=>$year])->sum('kgsgirls_paidfor');
           

            $kgs_paid_for_annually=0;
            $annual_value_to_divide_with_paid_for=0;
            $percentageMet=0;
             if( $paid_for_count_t1>0)
             {
                $kgs_paid_for_annually= $disabled_count_t1;
                $annual_value_to_divide_with_paid_for+=1;
             }
             if(  $paid_for_count_t2>0)
             {
                $kgs_paid_for_annually= $disabled_count_t2;
                $annual_value_to_divide_with_paid_for+=1;
             }
             if( $paid_for_count_t3>0)
             {
                $kgs_paid_for_annually= $disabled_count_t3;
                $annual_value_to_divide_with_paid_for+=1;
             }
 
             if( $kgs_paid_for_annually>0){
                 
                $percentageMet =$kgs_paid_for_annually;
             }
         
            

        return $percentageMet;
    }
    //KPI 4
    public function calculateKPI4(Request $request, $baselineNeeded = true)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        $baseline_year = Config('constants.MandE.baseline_year');
        //baseline
        $baseline = 0;
        if ($baselineNeeded == true) {
            $baseline_qry = DB::table('payment_request_details as t1')
                ->join('payment_disbursement_details as t3', 't1.id', '=', 't3.payment_request_id')
                ->join('school_information as t2', 't3.school_id', '=', 't2.id')
                ->where('t1.payment_year', $baseline_year);
            if (validateisNumeric($province_id)) {
                $baseline_qry->where('t2.province_id', $province_id);
            }
            if (validateisNumeric($district_id)) {
                $baseline_qry->where('t2.district_id', $district_id);
            }
            if (validateisNumeric($school_id)) {
                $baseline_qry->where('t1.school_id', $school_id);
            }
            $baseline = $baseline_qry->max('transaction_date');
        }
        //Source: School verification (Finance)
        $qry = DB::table('mne_datacollectiontool_dataentry_basicinfo as t1')
            ->join('mne_unstructuredquizes_dataentryinfo as t2', 't1.id', '=', 't2.record_id')
            ->select(DB::raw("'$baseline' as detail_baseline,
                    t2.year_id,t2.term_id,FROM_UNIXTIME(AVG(UNIX_TIMESTAMP(response))) as detail_value"))
            ->where('t1.workflow_stage_id', 3)//analysis
            ->where('t1.is_monitoring', '<>', 1);
        if (validateisNumeric($province_id)) {
            $qry->where('t1.province_id', $province_id);
        }
        if (validateisNumeric($district_id)) {
            $qry->where('t1.district_id', $district_id);
        }
        if (validateisNumeric($school_id)) {
            $qry->where('t1.school_id', $school_id);
        }
        if (validateisNumeric($year_from) && validateisNumeric($year_to)) {
            $qry->whereBetween('t2.year_id', array($year_from, $year_to));
        }
        $qry->groupBy('t2.year_id', 't2.term_id')
            ->orderBy('t2.year_id', 'ASC')
            ->orderBy('t2.term_id', 'ASC')
            ->where('t2.question_id', 9);
        $results = $qry->get();
        return $results;
    }

    public function determineKPI4Performance(Request $request, $result)
    {
        $year = $request->input('year_from');
        $val = 0;
        $percentage_met = 0;
        $target = strtotime($year . '-03-31');
        $calculationDetails = $this->calculateKPI4($request, false);
        if ($calculationDetails->count() > 0) {
            $val = $calculationDetails[0]->detail_value;
            $percentage_met = ($target / (strtotime($val)) * 100);
        }
        return $this->formatKPIPerformance($target, $val, $percentage_met);
    }

    //KPI 5
    public function calculateKPI5(Request $request, $baselineNeeded = true)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        $term_id = $request->input('term_id');
        $baseline_year = 2022;//Config('constants.MandE.baseline_year');
        //baseline
        $term1_fees = '';
        $term2_fees = '';
        $term3_fees = '';
        if ($baselineNeeded == true) {
            $baseline_qry = DB::table('beneficiary_enrollments as t1')
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                ->select(DB::raw("SUM(decrypt(t1.term1_fees)) as term1_fees,SUM(decrypt(t1.term2_fees)) as term2_fees,SUM(decrypt(t1.term3_fees)) as term3_fees"))
                ->where('t1.year_of_enrollment', $baseline_year)
                ->where('t1.is_validated', 1);
            if (validateisNumeric($province_id)) {
                $baseline_qry->where('t2.province_id', $province_id);
            }
            if (validateisNumeric($district_id)) {
                $baseline_qry->where('t2.district_id', $district_id);
            }
            if (validateisNumeric($school_id)) {
                $baseline_qry->where('t1.school_id', $school_id);
            }
            $baseline_results = $baseline_qry->first();
            if ($baseline_results) {
                $term1_fees = $baseline_results->term1_fees;
                $term2_fees = $baseline_results->term2_fees;
                $term3_fees = $baseline_results->term3_fees;
            }
        }
        //Source: School verification (Finance)
        $qry = DB::table('mne_datacollectiontool_dataentry_basicinfo as t1')
            ->join('mne_unstructuredquizes_dataentryinfo as t2', 't1.id', '=', 't2.record_id')
            ->select(DB::raw("CASE WHEN t2.term_id=1 THEN '$term1_fees' WHEN t2.term_id=2 THEN '$term2_fees' WHEN t2.term_id=3 THEN '$term3_fees' ELSE '' END as detail_baseline,
                    t2.year_id,t2.term_id,COALESCE(sum(t2.response),0) as detail_value"))
            ->whereIn('t2.question_id', array(74, 75))
            ->where('t1.workflow_stage_id', 3)//analysis
            ->where('t1.is_monitoring', '<>', 1);
        if (validateisNumeric($province_id)) {
            $qry->where('t1.province_id', $province_id);
        }
        if (validateisNumeric($district_id)) {
            $qry->where('t1.district_id', $district_id);
        }
        if (validateisNumeric($school_id)) {
            $qry->where('t1.school_id', $school_id);
        }
        if (validateisNumeric($year_from) && validateisNumeric($year_to)) {
            $qry->whereBetween('t2.year_id', array($year_from, $year_to));
        }
        if (validateisNumeric($term_id)) {
            $qry->where('t2.term_id', $term_id);
        }
        $qry->groupBy('t2.year_id', 't2.term_id')
            ->orderBy('t2.year_id', 'ASC')
            ->orderBy('t2.term_id', 'ASC');
        $results = $qry->get();
        return $results;
    }

    public function determineKPI5Performance(Request $request, $result)
    {
        $target = $result->target;
        $val = 0;
        $calculationDetails = $this->calculateKPI5($request, false);
        if ($calculationDetails->count() > 0) {
            $val = $calculationDetails[0]->detail_value;
        }
        return $this->formatKPIPerformance($target, $val);
    }

    //KPI 6
    public function calculateKPI6(Request $request, $baselineNeeded = true)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        $term_id = $request->input('term_id');
        //baseline
        $baseline = '';
        if ($baselineNeeded == true) {

        }
        //Source: Performance/Attendance
        $qry = DB::table('mne_datacollectiontool_dataentry_basicinfo as t1')
            ->join('mne_performanceattendance_info as t2', 't1.id', '=', 't2.record_id')
            ->select(DB::raw("'$baseline' as detail_baseline, t2.year_id,t2.term_id,ROUND(AVG(t2.benficiary_attendance),2) as detail_value"))
            ->where('t1.workflow_stage_id', 3)//analysis
            ->where('t1.is_monitoring', '<>', 1);
        if (validateisNumeric($province_id)) {
            $qry->where('t1.province_id', $province_id);
        }
        if (validateisNumeric($district_id)) {
            $qry->where('t1.district_id', $district_id);
        }
        if (validateisNumeric($school_id)) {
            $qry->where('t1.school_id', $school_id);
        }
        if (validateisNumeric($year_from) && validateisNumeric($year_to)) {
            $qry->whereBetween('t2.year_id', array($year_from, $year_to));
        }
        if (validateisNumeric($term_id)) {
            $qry->where('t2.term_id', $term_id);
        }
        $qry->groupBy('t2.year_id', 't2.term_id')
            ->orderBy('t2.year_id', 'ASC')
            ->orderBy('t2.term_id', 'ASC');
        $results = $qry->get();
        return $results;
    }

    public function determineKPI6Performance(Request $request, $result)
    {
        //$target = (float)$result->target;
        $target = Config('constants.term_num_days');
        $val = 0;
        $calculationDetails = $this->calculateKPI6($request, false);
        if ($calculationDetails->count() > 0) {
            $val = $calculationDetails[0]->detail_value;
        }
        return $this->formatKPIPerformance($target, $val);
    }

    //KPI 7
    public function calculateKPI7(Request $request, $baselineNeeded = true)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        $baseline_year = Config('constants.MandE.baseline_year');
        //baseline
        $baseline = '';
        if ($baselineNeeded == true) {
            $baseline_qry = DB::table('beneficiary_enrollments as t1')
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                ->where('t1.year_of_enrollment', $baseline_year)
                ->where('t1.has_signed', 1);
            if (validateisNumeric($province_id)) {
                $baseline_qry->where('t2.province_id', $province_id);
            }
            if (validateisNumeric($district_id)) {
                $baseline_qry->where('t2.district_id', $district_id);
            }
            if (validateisNumeric($school_id)) {
                $baseline_qry->where('t1.school_id', $school_id);
            }
            $baseline = $baseline_qry->distinct()->count('t1.beneficiary_id');
        }
        //Source: Progression
        $qry = DB::table('mne_datacollectiontool_dataentry_basicinfo as t1')
            ->join('mne_progression_info as t2', 't1.id', '=', 't2.record_id')
            ->select(DB::raw("'$baseline' as detail_baseline, t2.year_id,t2.term_id,COALESCE(SUM(t2.kgs_enrolled),0) as detail_value"))
            ->where('t2.transition_id', 11)
            ->where('t1.workflow_stage_id', 3)//analysis
            ->where('t1.is_monitoring', '<>', 1);
        if (validateisNumeric($province_id)) {
            $qry->where('t1.province_id', $province_id);
        }
        if (validateisNumeric($district_id)) {
            $qry->where('t1.district_id', $district_id);
        }
        if (validateisNumeric($school_id)) {
            $qry->where('t1.school_id', $school_id);
        }
        if (validateisNumeric($year_from) && validateisNumeric($year_to)) {
            $qry->whereBetween('t2.year_id', array($year_from, $year_to));
        }
        $qry->groupBy('t2.year_id')
            ->orderBy('t2.year_id', 'ASC')
            ->orderBy('t2.term_id', 'ASC');
        $results = $qry->get();
        return $results;
    }

    public function determineKPI7Performance(Request $request, $result)
    {
        $target = $result->target;
        $val = 0;
        $calculationDetails = $this->calculateKPI7($request, false);
        if ($calculationDetails->count() > 0) {
            $val = $calculationDetails[0]->detail_value;
        }
        return $this->formatKPIPerformance($target, $val);
    }

    //KPI 8
    public function calculateKPI8(Request $request, $baselineNeeded = true)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        //baseline
        $baseline = '';
        if ($baselineNeeded == true) {

        }
        //Source: Progression
        $qry = DB::table('mne_datacollectiontool_dataentry_basicinfo as t1')
            ->join('mne_progression_info as t2', 't1.id', '=', 't2.record_id')
            ->select(DB::raw("'$baseline' as detail_baseline, t2.year_id,t2.term_id,COALESCE(SUM(t2.kgs_enrolled),0) as detail_value"))
            ->where('t1.workflow_stage_id', 3)//analysis
            ->where('t1.is_monitoring', '<>', 1);
        if (validateisNumeric($province_id)) {
            $qry->where('t1.province_id', $province_id);
        }
        if (validateisNumeric($district_id)) {
            $qry->where('t1.district_id', $district_id);
        }
        if (validateisNumeric($school_id)) {
            $qry->where('t1.school_id', $school_id);
        }
        if (validateisNumeric($year_from) && validateisNumeric($year_to)) {
            $qry->whereBetween('t2.year_id', array($year_from, $year_to));
        }
        $qry->groupBy('t2.year_id')
            ->orderBy('t2.year_id', 'ASC')
            ->orderBy('t2.term_id', 'ASC');
        $results = $qry->get();
        return $results;
    }

    public function determineKPI8Performance(Request $request, $result)
    {
        $target = $result->target;
        $val = 0;
        $calculationDetails = $this->calculateKPI8($request, false);
        if ($calculationDetails->count() > 0) {
            $val = $calculationDetails[0]->detail_value;
        }
        return $this->formatKPIPerformance($target, $val);
    }
     //KPI 9 - New KPI
    public function calculateKPI9(Request $request, $baselineNeeded = true)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        $kpi_id=$request->input('kpi_id');
        $total_ben=$this->getallbeneficiaries($year_from,$year_to);
       $districts_years=$this->getdistrictsandyears($kpi_id,$year_from,$year_to,$province_id,$district_id);  

        foreach($districts_years as $row){
            $between_from = strtotime($row['year_id'] . '-01-01');
            $between_to = strtotime($row['year_id'] . '-03-31');
            //get beneficiary paid for by 31st march
            $getallpaymentsbymarch =Db::select("SELECT COUNT(t1.beneficiary_id) FROM beneficiary_enrollments AS t1 
                                                                     INNER JOIN beneficiary_payment_records AS t2 ON t1.id=t2.enrollment_id WHERE  DATE(t2.created_at) BETWEEN  $between_from AND $between_to
                                                                      GROUP BY t1.beneficiary_id");
            if (validateisNumeric($province_id)) {
                $getallpaymentsbymarch->where('t1.province_id', $province_id);
            }
            if (validateisNumeric($district_id)) {
                $getallpaymentsbymarch->where('t1.district_id', $district_id);
            }
            if (validateisNumeric($school_id)) {
                $getallpaymentsbymarch->where('t1.school_id', $school_id);
            }
            if (validateisNumeric($year_from) && validateisNumeric($year_to)) {
                $getallpaymentsbymarch->whereBetween('t2.year_id', array($year_from, $year_to));
            }

            if($getallpaymentsbymarch)
            {
                $total_ben_count=count($total_ben);
                $total_getallpaymentsbymarch=count($getallpaymentsbymarch);
            }else{
                $total_ben_count=0;
                $total_getallpaymentsbymarch=0;
            }
            if($total_ben_count==0){
                $percentage=0;
            }else{
                $percentage = ($total_getallpaymentsbymarch/$total_ben_count)*100;
            }
                     $ben_paidontime[]=array(
                        "value"=>$percentage,
                        "year_id"=>$row['year_id'],
                        "district_name"=>$row['district_name'],
                        "target_val"=>$row['target_val'],
                        "baseline_val"=>$row['baseline_val'],
                     );
                }
        return $ben_paidontime;
    }

    public function determineKPI9Performance(Request $request, $result)
    {
        $target = $result->target;
        $val = 0;
        $calculationDetails = $this->calculateKPI8($request, false);
        if ($calculationDetails->count() > 0) {
            $val = $calculationDetails[0]->detail_value;
        }
        return $this->formatKPIPerformance($target, $val);
    }
    //KPI 10
    public function calculateKPI10(Request $request, $baselineNeeded = true)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        //baseline
        $baseline = '';
        if ($baselineNeeded == true) {

        }
        //Source: Progression
        $qry = DB::table('mne_datacollectiontool_dataentry_basicinfo as t1')
            ->join('mne_unstructuredquizes_dataentryinfo as t2', 't1.id', '=', 't2.record_id')
            ->select(DB::raw("t2.year_id,t2.term_id,COALESCE(SUM(IF(t2.question_id=13,t2.response,0)),0) as overpayment,
                                COALESCE(SUM(IF(t2.question_id=78,t2.response,0)),0) as underpayment"))
            ->whereIn('t2.question_id', array(13, 78))
            ->where('t1.workflow_stage_id', 3)//analysis
            ->where('t1.is_monitoring', '<>', 1);
        if (validateisNumeric($province_id)) {
            $qry->where('t1.province_id', $province_id);
        }
        if (validateisNumeric($district_id)) {
            $qry->where('t1.district_id', $district_id);
        }
        if (validateisNumeric($school_id)) {
            $qry->where('t1.school_id', $school_id);
        }
        if (validateisNumeric($year_from) && validateisNumeric($year_to)) {
            $qry->whereBetween('t2.year_id', array($year_from, $year_to));
        }
        $qry->groupBy('t2.year_id')
            ->orderBy('t2.year_id', 'ASC')
            ->orderBy('t2.term_id', 'ASC');
        $results = $qry->get();
        return $results;
    }

    public function determineKPI10Performance(Request $request, $result)
    {
        $target = $result->target;
        $val = 0;
        $calculationDetails = $this->calculateKPI10($request, false);
        $detail_value = '';
        if ($calculationDetails->count() > 0) {
            $val = 34;//$calculationDetails[0]->detail_value;
            $overpayment = $calculationDetails[0]->overpayment;
            $underpayment = $calculationDetails[0]->underpayment;
            if ($overpayment > 0) {
                $detail_value = 'Over:' . $overpayment;
            }
            if ($underpayment > 0) {
                $detail_value .= 'Under:' . $underpayment;
            }
        }
        return $this->formatKPIPerformance($target, $val, $detail_value, false, true);
    }

    //KPI 11
    public function calculateKPI11(Request $request, $baselineNeeded = true)
    {

        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        $term_id = $request->input('term_id');
        $kpi_id = $request->input('kpi_id');
        //baseline
        $baseline = '';
        if ($baselineNeeded == true) {

        }
        //Source: Progression
        $qry = DB::table('school_information as t1')
            ->leftjoin('beneficiary_information as t2', 't1.id', '=', 't2.school_id')
            ->leftjoin('payment_disbursement_details AS t3','t1.id','=','t3.school_id')
            ->join('mne_kpis_targets AS t4',DB::raw('YEAR(t1.created_at)'),'=','t4.year')
            ->join('districts AS t5','t1.district_id','=','t5.id')
            ->select(DB::raw("COUNT(t2.school_id ) as total_school, COUNT(t3.school_id) AS schools_payedfor,((COUNT(t3.school_id)/COUNT(t2.school_id))*100) AS value,YEAR(t3.transaction_date) AS year_id,t4.target_val,t4.baseline_val,t5.NAME AS district_name"))
            ->where('t4.kpi_id',$kpi_id);
        if (validateisNumeric($province_id)) {
            $qry->where('t1.province_id', $province_id);
        }
        if (validateisNumeric($district_id)) {
            $qry->where('t1.district_id', $district_id);
        }
        if (validateisNumeric($school_id)) {
            $qry->where('t1.school_id', $school_id);
        }
        if (validateisNumeric($year_from) && validateisNumeric($year_to)) {
            $qry->whereBetween(DB::raw('YEAR(t3.transaction_date)'), array($year_from, $year_to));
        }
        /*$qry->groupBy(DB::raw('YEAR(t1.created_at)'));*/
        $qry->groupBy('district_name');
        $qry->orderBy('district_name', 'asc');
        $results = $qry->get();
        return $results;
    }

    public function determineKPI11Performance(Request $request, $result)
    {
        $target = $result->target;
        $val = '';
        $calculationDetails = $this->calculateKPI11($request, false);
        if ($calculationDetails->count() > 0) {
            $val = $calculationDetails[0]->detail_value;
        }
        return $this->formatKPIPerformance($target, $val, $val, false, true);
    }

    //KPI 12
    public function calculateKPI12(Request $request, $baselineNeeded = true)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        //baseline
        $baseline = '';
        if ($baselineNeeded == true) {

        }
        //Source: School verification (Finance)
        $qry = DB::table('mne_datacollectiontool_dataentry_basicinfo as t1')
            ->join('mne_unstructuredquizes_dataentryinfo as t2', 't1.id', '=', 't2.record_id')
            ->select(DB::raw("'$baseline' as detail_baseline,
                    t2.year_id,t2.term_id,FROM_UNIXTIME(AVG(UNIX_TIMESTAMP(response))) as detail_value"))
            ->where('t2.question_id', 15)
            ->where('t1.workflow_stage_id', 3)//analysis
            ->where('t1.is_monitoring', '<>', 1);
        if (validateisNumeric($province_id)) {
            $qry->where('t1.province_id', $province_id);
        }
        if (validateisNumeric($district_id)) {
            $qry->where('t1.district_id', $district_id);
        }
        if (validateisNumeric($school_id)) {
            $qry->where('t1.school_id', $school_id);
        }
        if (validateisNumeric($year_from) && validateisNumeric($year_to)) {
            $qry->whereBetween('t2.year_id', array($year_from, $year_to));
        }
        $qry->groupBy('t2.year_id', 't2.term_id')
            ->orderBy('t2.year_id', 'ASC')
            ->orderBy('t2.term_id', 'ASC');
        $results = $qry->get();
        return $results;
    }

    public function determineKPI12Performance(Request $request, $result)
    {
        $year = $request->input('year_from');
        $val = 0;
        $percentage_met = 0;
        $target = strtotime($year . '-04-30');
        $calculationDetails = $this->calculateKPI12($request, false);
        if ($calculationDetails->count() > 0) {
            $val = $calculationDetails[0]->detail_value;
            $percentage_met = ($target / (strtotime($val)) * 100);
        }
        return $this->formatKPIPerformance($target, $val, $percentage_met);
    }

    //TODO DISTRICT LEVEL KPIs
    //KPI 16
    public function calculateKPI16(Request $request, $baselineNeeded = true)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        //baseline
        $baseline = '';
        if ($baselineNeeded == true) {

        }
        $qry = DB::table('mne_datacollectiontool_dataentry_basicinfo as t1')
            ->join('mne_unstructuredquizes_dataentryinfo as t2', 't1.id', '=', 't2.record_id')
            ->select(DB::raw("'$baseline' as detail_baseline, t2.year_id,t2.term_id,COALESCE(sum(t2.response),0) as detail_value"))
            ->where('t2.question_id', 20)
            ->where('t1.workflow_stage_id', 3)//analysis
            ->where('t1.is_monitoring', '<>', 1);
        if (validateisNumeric($province_id)) {
            $qry->where('t1.province_id', $province_id);
        }
        if (validateisNumeric($district_id)) {
            $qry->where('t1.district_id', $district_id);
        }
        if (validateisNumeric($school_id)) {
            $qry->where('t1.school_id', $school_id);
        }
        if (validateisNumeric($year_from) && validateisNumeric($year_to)) {
            $qry->whereBetween('t2.year_id', array($year_from, $year_to));
        }
        $qry->groupBy('t2.year_id')
            ->orderBy('t2.year_id', 'ASC')
            ->orderBy('t2.term_id', 'ASC');
        $results = $qry->get();
        return $results;
    }

    public function determineKPI16Performance(Request $request, $result)
    {
        $target = $result->target;
        $val = 0;
        $calculationDetails = $this->calculateKPI16($request, false);
        if ($calculationDetails->count() > 0) {
            $val = $calculationDetails[0]->detail_value;
        }
        return $this->formatKPIPerformance($target, $val);
    }

    //KPI 17
    public function calculateKPI17(Request $request, $baselineNeeded = true)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        //baseline
        $baseline = '';
        if ($baselineNeeded == true) {

        }
        $qry = DB::table('mne_datacollectiontool_dataentry_basicinfo as t1')
            ->join('mne_unstructuredquizes_dataentryinfo as t2', 't1.id', '=', 't2.record_id')
            ->select(DB::raw("'$baseline' as detail_baseline, t2.year_id,t2.term_id,
                                CONCAT(((SUM(IF(t2.question_id=21,t2.response,0))/SUM(IF(t2.question_id=20,t2.response,0)))*100),'%') as detail_value
                                "))
            ->whereIn('t2.question_id', array(20, 21))
            ->where('t1.workflow_stage_id', 3)//analysis
            ->where('t1.is_monitoring', '<>', 1);
        if (validateisNumeric($province_id)) {
            $qry->where('t1.province_id', $province_id);
        }
        if (validateisNumeric($district_id)) {
            $qry->where('t1.district_id', $district_id);
        }
        if (validateisNumeric($school_id)) {
            $qry->where('t1.school_id', $school_id);
        }
        if (validateisNumeric($year_from) && validateisNumeric($year_to)) {
            $qry->whereBetween('t2.year_id', array($year_from, $year_to));
        }
        $qry->groupBy('t2.year_id')
            ->orderBy('t2.year_id', 'ASC')
            ->orderBy('t2.term_id', 'ASC');
        $results = $qry->get();
        return $results;
    }

    public function determineKPI17Performance(Request $request, $result)
    {
        $target = (float)$result->target;
        $val = 0;
        $percentageMet = 0;
        $calculationDetails = $this->calculateKPI17($request, false);
        if ($calculationDetails->count() > 0) {
            $val = $calculationDetails[0]->detail_value;
            $percentageMet = (((float)$val / $target) * 100);
        }
        return $this->formatKPIPerformance($target, $val, $percentageMet);
    }

    //KPI 20
    public function calculateKPI20(Request $request, $baselineNeeded = true)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        //baseline
        $baseline = '';
        if ($baselineNeeded == true) {

        }
        $qry = DB::table('mne_datacollectiontool_dataentry_basicinfo as t1')
            ->join('mne_unstructuredquizes_dataentryinfo as t2', 't1.id', '=', 't2.record_id')
            ->select(DB::raw("'$baseline' as detail_baseline, t2.year_id,t2.term_id,
                                CONCAT(((SUM(IF(t2.question_id=80,t2.response,0))/SUM(IF(t2.question_id=21,t2.response,0)))*100),'%') as detail_value
                                "))
            ->whereIn('t2.question_id', array(21, 80))
            ->where('t1.workflow_stage_id', 3)//analysis
            ->where('t1.is_monitoring', '<>', 1);
        if (validateisNumeric($province_id)) {
            $qry->where('t1.province_id', $province_id);
        }
        if (validateisNumeric($district_id)) {
            $qry->where('t1.district_id', $district_id);
        }
        if (validateisNumeric($school_id)) {
            $qry->where('t1.school_id', $school_id);
        }
        if (validateisNumeric($year_from) && validateisNumeric($year_to)) {
            $qry->whereBetween('t2.year_id', array($year_from, $year_to));
        }
        $qry->groupBy('t2.year_id')
            ->orderBy('t2.year_id', 'ASC')
            ->orderBy('t2.term_id', 'ASC');
        $results = $qry->get();
        return $results;
    }

    public function determineKPI20Performance(Request $request, $result)
    {
        $target = (float)$result->target;
        $val = 0;
        $percentageMet = 0;
        $calculationDetails = $this->calculateKPI20($request, false);
        if ($calculationDetails->count() > 0) {
            $val = $calculationDetails[0]->detail_value;
            $percentageMet = (((float)$val / $target) * 100);
        }
        return $this->formatKPIPerformance($target, $val, $percentageMet);
    }

    //KPI 21
    public function calculateKPI21(Request $request, $baselineNeeded = true)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        //baseline
        $baseline = '';
        if ($baselineNeeded == true) {

        }
        $qry = DB::table('mne_datacollectiontool_dataentry_basicinfo as t1')
            ->join('mne_unstructuredquizes_dataentryinfo as t2', 't1.id', '=', 't2.record_id')
            ->select(DB::raw("'$baseline' as detail_baseline, t2.year_id,t2.term_id,
                                CONCAT(((SUM(IF(t2.question_id=22,t2.response,0))/((SUM(IF(t2.question_id=20,t2.response,0)))-(SUM(IF(t2.question_id=21,t2.response,0)))))*100),'%') as detail_value
                                "))
            ->whereIn('t2.question_id', array(20, 21, 22))
            ->where('t1.workflow_stage_id', 3)//analysis
            ->where('t1.is_monitoring', '<>', 1);
        if (validateisNumeric($province_id)) {
            $qry->where('t1.province_id', $province_id);
        }
        if (validateisNumeric($district_id)) {
            $qry->where('t1.district_id', $district_id);
        }
        if (validateisNumeric($school_id)) {
            $qry->where('t1.school_id', $school_id);
        }
        if (validateisNumeric($year_from) && validateisNumeric($year_to)) {
            $qry->whereBetween('t2.year_id', array($year_from, $year_to));
        }
        $qry->groupBy('t2.year_id')
            ->orderBy('t2.year_id', 'ASC')
            ->orderBy('t2.term_id', 'ASC');
        $results = $qry->get();
        return $results;
    }

    public function determineKPI21Performance(Request $request, $result)
    {
        $target = (float)$result->target;
        $val = 0;
        $percentageMet = 0;
        $calculationDetails = $this->calculateKPI21($request, false);
        if ($calculationDetails->count() > 0) {
            $val = $calculationDetails[0]->detail_value;
            $percentageMet = (((float)$val / $target) * 100);
        }
        return $this->formatKPIPerformance($target, $val, $percentageMet);
    }

    //KPI 22
    public function calculateKPI22(Request $request, $baselineNeeded = true)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        //baseline
        $baseline = '';
        if ($baselineNeeded == true) {

        }
        $qry = DB::table('mne_datacollectiontool_dataentry_basicinfo as t1')
            ->join('mne_unstructuredquizes_dataentryinfo as t2', 't1.id', '=', 't2.record_id')
            ->join('mne_datacollectiontool_quizes as t3', 't2.question_id', '=', 't3.id')
            ->select(DB::raw("'$baseline' as detail_baseline, t2.year_id,t3.name as training_area,t2.response as detail_value
                                "))
            ->where('t3.parent_id', 23)
            ->where('t1.workflow_stage_id', 3)//analysis
            ->where('t1.is_monitoring', '<>', 1);
        //->groupBy('t2.year_id')
        if (validateisNumeric($province_id)) {
            $qry->where('t1.province_id', $province_id);
        }
        if (validateisNumeric($district_id)) {
            $qry->where('t1.district_id', $district_id);
        }
        if (validateisNumeric($school_id)) {
            $qry->where('t1.school_id', $school_id);
        }
        if (validateisNumeric($year_from) && validateisNumeric($year_to)) {
            $qry->whereBetween('t2.year_id', array($year_from, $year_to));
        }
        $qry->orderBy('t2.year_id', 'ASC')
            ->orderBy('t2.term_id', 'ASC');
        $results = $qry->get();
        return $results;
    }

    //KPI 23
    public function calculateKPI23(Request $request, $baselineNeeded = true)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        //baseline
        $baseline = '';
        if ($baselineNeeded == true) {

        }
        $qry = DB::table('mne_datacollectiontool_dataentry_basicinfo as t1')
            ->join('mne_unstructuredquizes_dataentryinfo as t2', 't1.id', '=', 't2.record_id')
            ->join('mne_datacollectiontool_quizes as t3', 't2.question_id', '=', 't3.id')
            ->select(DB::raw("'$baseline' as detail_baseline, t2.year_id,t3.name as training_area,SUM(IF(t2.response=1,1,0)) as detail_value"))
            ->where('t3.parent_id', 107)
            ->where('t2.response', 1)
            ->where('t1.workflow_stage_id', 3)//analysis
            ->where('t1.is_monitoring', '<>', 1);
        if (validateisNumeric($province_id)) {
            $qry->where('t1.province_id', $province_id);
        }
        if (validateisNumeric($district_id)) {
            $qry->where('t1.district_id', $district_id);
        }
        if (validateisNumeric($school_id)) {
            $qry->where('t1.school_id', $school_id);
        }
        if (validateisNumeric($year_from) && validateisNumeric($year_to)) {
            $qry->whereBetween('t2.year_id', array($year_from, $year_to));
        }
        $qry->groupBy('t3.id')
            ->orderBy('t2.year_id', 'ASC')
            ->orderBy('t2.term_id', 'ASC');
        $results = $qry->get();
        return $results;
    }

    //KPI 24
    public function calculateKPI24(Request $request, $baselineNeeded = true)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        //baseline
        $baseline = '';
        if ($baselineNeeded == true) {

        }
        $qry = DB::table('mne_datacollectiontool_dataentry_basicinfo as t1')
            ->join('mne_unstructuredquizes_dataentryinfo as t2', 't1.id', '=', 't2.record_id')
            ->join('mne_datacollectiontool_quizes as t3', 't2.question_id', '=', 't3.id')
            ->join('districts as t4', 't1.district_id', '=', 't4.id')
            ->select(DB::raw("t1.district_id,t4.name as district_name,'$baseline' as detail_baseline, t2.year_id,t3.name as training_area,SUM(IF(t2.response=1,1,0)) as detail_value"))
            ->where('t3.parent_id', 38)
            ->where('t2.response', 1)
            ->where('t1.workflow_stage_id', 3)//analysis
            ->where('t1.is_monitoring', '<>', 1);
        if (validateisNumeric($province_id)) {
            $qry->where('t1.province_id', $province_id);
        }
        if (validateisNumeric($district_id)) {
            $qry->where('t1.district_id', $district_id);
        }
        if (validateisNumeric($school_id)) {
            $qry->where('t1.school_id', $school_id);
        }
        if (validateisNumeric($year_from) && validateisNumeric($year_to)) {
            $qry->whereBetween('t2.year_id', array($year_from, $year_to));
        }
        $qry->groupBy('t1.district_id', 't2.year_id', 't3.id')
            ->orderBy('t2.year_id', 'ASC')
            ->orderBy('t2.term_id', 'ASC');
        $results = $qry->get();
        return $results;
    }

    public function formatKPIPerformance($target, $val, $percentage_met = 0, $needsFormatting = true, $unclassified = false)
    {
        $kpistatus_id = '';
        $kpistatus = '';
        if ($percentage_met === 0) {
            if ($target > 0) {
                $percentage_met = (($val / $target) * 100);
            }
        }

        if ($unclassified == true) {
            $val = $percentage_met;
            $kpistatus_id = 4;
            $kpistatus = 'Unclassified';
        } else {
            if ($percentage_met < 65) {
                $kpistatus_id = 1;
                $kpistatus = 'Requires Attention';
            } else if ($percentage_met > 64 && $percentage_met < 80) {
                $kpistatus_id = 2;
                $kpistatus = 'Lagging Behind';
            } else if ($percentage_met >= 80) {
                $kpistatus_id = 3;
                $kpistatus = 'On Track';
            }
        }

        if ($needsFormatting == true) {
            $target_met = number_format($percentage_met, 2, '.', ',') . '%';
        } else {
            $target_met = $percentage_met;
        }
        return array(
            'val' => $val,
            'target_met' => $target_met,
            'kpistatus_id' => $kpistatus_id,
            'kpistatus' => $kpistatus
        );
    }
    // Start Maureen

    //KPI 46
    public function calculateKPI46(Request $request){
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        $kpi_id=$request->input('kpi_id');
        $years=$this->getallyears($year_from,$year_to);
        $districts_years=$this->getdistrictsandyears($kpi_id,$year_from,$year_to,$province_id,$district_id);  
        //default if district_id is null show all districts
            foreach($districts_years as $row){
                $values=json_decode($this->KPI46Basequery($province_id,$row['district_id'],$row['year_id']));
                $satisfiedben[]=array(
                        "value"=>(int)$values[0]->value,
                        "year_id"=>$row['year_id'],
                        "district_name"=>$row['district_name'],
                        "target_val"=>$row['target_val'],
                        "baseline_val"=>$row['baseline_val'],
                );
             }
         return  $satisfiedben;

    }
    public function KPI46Basequery($province_id,$district_id,$year_id)
    {
        // code...
          $query=DB::table('mne_survey_dataentry as t1')
                    ->select(DB::raw( "(SUM(if(t1.perc_kgs_ben,t1.perc_kgs_ben,0))*100)/SUM(if(t1.total_kgs_ben,t1.total_kgs_ben,0)) AS value"));

        if (validateisNumeric($province_id)) {
            $query->where('t1.province_id', $province_id);
        }
        if (validateisNumeric($district_id)) {
            $query->where('t1.district_id', $district_id);
        }
        if(validateisNumeric($year_id)){
            $query->where(DB::raw('YEAR(t1.created_at)'), $year_id);
        }
        $response=$query->get(); 
        return json_encode($response);
    }
    
   public function determineKPI46Performance($year,$filterdatefrom,$filterdateto,$filterprovince,$filterdistrict)
    { 
        if(validateisNumeric($filterprovince)){
            $province_id=$filterprovince;     
        }else{
            $province_id='';
        }
        if(validateisNumeric($filterdistrict)){
            $district_id=$filterdistrict;
        }else{
            $district_id='';
        }

        $qryResults=json_decode($this->KPI46Basequery($province_id,$district_id,$year,$filterdatefrom,$filterdateto));
        $percentageMet=(int)$qryResults[0]->value;
        return $percentageMet;
    }

    //KPI 47
    public function calculateKPI47(Request $request)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        $kpi_id=$request->input('kpi_id');
        
        if(!validateisNumeric($year_from))
        {
            $year_from=2016;
        }

        if(!validateisNumeric($year_to))
        {
            $year_to=date('Y');
        }
        $years=[];
        if($year_from==$year_to)
        {
            $years[]=$year_from;
        }else{
            $total_year_from=($year_to-$year_from)+1;
            for($i=0;$i<$total_year_from;$i++)
            {
                $year_to_use=$year_from+$i;
                $years[]=$year_to_use;
            }
        }

        $year_months_start_end_days=[];
        foreach($years as $year)
        {
            $year_months=[];
            for($i=1;$i<13;$i++){
            $end="";
            $month=$i;
            switch($month)
            {
                case 1://31 days
                case 3:
                case 5:
                case 7:
                case 8:
                case 10:
                case 12:
                    if($month<10)
                    {
                        $month="0".$month;
                    }
                    $end="$year/$month/31"; 
                    break;
                case 2://28/29 days
                    $isleapyear=!($year % 4) && ($year % 100 || !($year % 400));
                    $days=$isleapyear==true?29:28;
                    if($month<10)
                    {
                        $month="0".$month;
                    }
                    $end="$year/$month/$days"; 
                    break;
                case 4:
                case 6:
                case 9:
                case 11:
                    if($month<10)
                    {
                        $month="0".$month;
                    }
                    $end="$year/$month/30"; 
                    break;
            }
            //Get Target & Baseline
            $targetqry=Db::table('mne_kpis_targets')
                ->select('target_val','baseline_val')
                ->where('year',$year)
                ->where('kpi_id',$kpi_id)
                ->get();
            $targetresult=json_decode($targetqry);
            if(!empty($targetresult)){
               $target=$targetresult[0]->target_val;
               $baseline=$targetresult[0]->baseline_val; 
            }else{
                $target='No Traget';
                $baseline='No Baseline set'; 
            }

            $year_months[$i]=array(
                "start"=>"$year/$month/01",
                "end"=>$end,
                "year_id"=>$year,
                "target_val"=>$target,
                "baseline_val"=>$baseline
            );
            }
            $year_months_start_end_days[$year]=$year_months;
            $year_months=[];
        }
        $months=array(
            "January",
            "February",
            "March",
            "April",
            "May",
            "June",
            "July",
            "August",
            "September",
            "October",
            "November",
            "December"
        );

        foreach($year_months_start_end_days as $year_data)
        {
            //return $year_data;
            $monthly_count=[];
          
            foreach($year_data as $key=>$month){
            
                $ben_ews_query=Db::table('case_basicdataentry_details as t1')
                ->where('t1.target_group_id',1)
                ->whereBetween('t1.case_formrecording_date',array($month['start'],$month['end']));
               
                if (validateisNumeric($province_id)) {
                    $ben_ews_query->where('t1.province_id', $province_id);
                }
                if (validateisNumeric($district_id)) {
                    $ben_ews_query->where('t1.district_id', $district_id);
                }
                if (validateisNumeric($school_id)) {
                    $ben_ews_query->where('t1.school_id', $school_id);
                }
                
                $ews_count=  $ben_ews_query->count('t1.id');

                 $ben_ews_by_year_month[]=array(
                     "period"=>$month['start']." to ".$month['end'],
                     "month"=>$months[$key-1],
                     "count"=>$ews_count,
                     "year_id"=>$month['year_id'],
                     "target_val"=>$month['target_val'],
                     "baseline_val"=>$month['baseline_val']
                 );
            }
        }
        return  $ben_ews_by_year_month;
    }  
    public function determineKPI47Performance(Request $request, $result)
    {
        $target = (float)$result->target;
        $val = 0;
        $percentageMet = 0;
        $calculationDetails = $this->calculateKPI21($request, false);
        if ($calculationDetails->count() > 0) {
            $val = 100;
            $percentageMet = (((float)$val / $target) * 100);
        }
        return $this->formatKPIPerformance($target, $val, $percentageMet);
    }
    //KPI 48
    public function determineKPI48Performance($year)
    {

            $percentageMet = 0;

        return $percentageMet;
    }

    //KPI 49
    public function determineKPI49Performance($year)
    {


            $percentageMet = 0;

        return $percentageMet;
    }
    //KPI 50
    public function determineKPI50Performance($year)
    {
        $target = 2;//(float)$result->target;
        $val = 0;
        $percentageMet = 0;
        $calculationDetails = 2;//$this->calculateKPI21($request, false);
        if ($calculationDetails > 0) {
            $val = 50;
            $percentageMet = (((float)$val / $target) * 100);
        }
        return 0;//$this->formatKPIPerformance($target, $val, $percentageMet);
    }
    //KPI 51
    public function calculateKPI51(Request $request, $baselineNeeded = true)
    {

        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        $term_id = $request->input('term_id');
        $kpi_id = $request->input('kpi_id');
        // Get Tota ben no 
        $total_ben_initial= $this->getallbeneficiaries($year_from,$year_to);
        $total_ben=intval(preg_replace('/[^\d. ]/', '',$total_ben_initial));
        //districts_years
        $districts_years=$this->getdistrictsandyears($kpi_id,$year_from,$year_to,$province_id,$district_id);  
        //Get total beneficiaries with grant
         $grant_ben_qry=DB::table('payments_beneficiaries_grant as t1')
               ->leftjoin('beneficiary_information as t2', 't1.beneficiary_id', '=', 't2.beneficiary_id');
        if (validateisNumeric($province_id)) {
            $grant_ben_qry->where('t2.province_id', $province_id);
        }
        if (validateisNumeric($district_id)) {
            $grant_ben_qry->where('t2.district_id', $district_id);
        }
        if (validateisNumeric($school_id)) {
            $grant_ben_qry->where('t2.school_id', $school_id);
        }
        if (validateisNumeric($year_from) && validateisNumeric($year_to)) {
            $grant_ben_qry->whereBetween('t1.payment_year',array($year_from,$year_to));   
        }

        if (validateisNumeric($district_id)) {
                $results = $grant_ben_qry->get();
                if($results->isNotEmpty())
                {
                    $grant_count=$grant_ben_qry->count('t1.id');
                }else{
                    $grant_count=0;
                }

                if($grant_count==0){
                    $percentage=0.000000;
                }else{
                     $percentage=number_format((($grant_count/$total_ben) * 100),7);  
                }
            foreach($districts_years as $row){
                     $grant_ben_by_year[]=array(
                        "value"=>$percentage,
                        "year_id"=>$row['year_id'],
                        "district_name"=>$row['district_name'],
                        "target_val"=>$row['target_val'],
                        "baseline_val"=>$row['baseline_val'],
                     );
                }
           
        }else{
            foreach($districts_years as $row){
                $grant_ben_qry->where(array('t2.district_id'=>$row['district_id'],'t1.payment_year'=>$row['year_id']));
                $results = $grant_ben_qry->get();
                    if($results->isNotEmpty())
                    {
                        $grant_count=$grant_ben_qry->count('t1.id');
                    }else{
                        $grant_count=0;
                    }
                    if($grant_count==0){
                        $percentage=0.000000;
                    }else{
                        $percentage = number_format((($grant_count/$total_ben) * 100),5);
                    }
                     $grant_ben_by_year[]=array(
                        "value"=>$percentage,
                        "year_id"=>$row['year_id'],
                        "district_name"=>$row['district_name'],
                        "target_val"=>$row['target_val'],
                        "baseline_val"=>$row['baseline_val'],
                     );
                }

        }
         return  $grant_ben_by_year;
    }
    public function determineKPI51Performance($year,$filterdatefrom,$filterdateto,$filterprovince,$filterdistrict)
    {
       //Get Years

        if($filterdatefrom && $filterdateto){
            $year_from=date('Y', strtotime($filterdatefrom));
            $year_to=date('Y', strtotime($filterdateto));
        }else{
            $year_from='';
            $year_to='';
        }

        $total_ben_initial= $this->getallbeneficiaries($year_from,$year_to);
        $total_ben=intval(preg_replace('/[^\d. ]/', '',$total_ben_initial));
                 $grant_ben_qry=DB::table('payments_beneficiaries_grant as t1')
                 ->leftjoin('beneficiary_information as t2', 't1.beneficiary_id', '=', 't2.beneficiary_id')
                ->where('t1.payment_year',$year);
            if(validateisNumeric($filterprovince)){
                $grant_ben_qry->where('t2.province_id',$filterprovince);      
            }
            if(validateisNumeric($filterdistrict)){
                $grant_ben_qry->where('t2.district_id',$filterdistrict);
            }
                 $results = $grant_ben_qry->get();
                printr($total_ben);
                 if($results->isNotEmpty())
                    {
                        $grant_count=$grant_ben_qry->count('t1.id');
                    }else{
                        $grant_count=0;
                    }
                    if($grant_count==0){
                        $percentage=0.000000;
                    }else{
                        $percentage = number_format((($grant_count/$total_ben) * 100),7);
                    }
        return $percentage;

    }
    //KPI 52
    public function determineKPI52Performance(Request $request, $result)
    {
        $target = (float)$result->target;
        $val = 0;
        $percentageMet = 0;
        $calculationDetails = $this->calculateKPI21($request, false);
        if ($calculationDetails->count() > 0) {
            $val = 100;
            $percentageMet = (((float)$val / $target) * 100);
        }
        return $this->formatKPIPerformance($target, $val, $percentageMet);
    }
    //KPI 53
    public function calculateKPI53(Request $request)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        $kpi_id=$request->input('kpi_id');
        
        if(!validateisNumeric($year_from))
        {
            $year_from=2016;
        }

        if(!validateisNumeric($year_to))
        {
            $year_to=date('Y');
        }
        $years=[];
        if($year_from==$year_to)
        {
            $years[]=$year_from;
        }else{
            $total_year_from=($year_to-$year_from)+1;
            for($i=0;$i<$total_year_from;$i++)
            {
                $year_to_use=$year_from+$i;
                $years[]=$year_to_use;
            }
        }

        $year_months_start_end_days=[];
        foreach($years as $year)
        {
            $year_months=[];
            for($i=1;$i<13;$i++){
            $end="";
            $month=$i;
            switch($month)
            {
                case 1://31 days
                case 3:
                case 5:
                case 7:
                case 8:
                case 10:
                case 12:
                    if($month<10)
                    {
                        $month="0".$month;
                    }
                    $end="$year/$month/31"; 
                    break;
                case 2://28/29 days
                    $isleapyear=!($year % 4) && ($year % 100 || !($year % 400));
                    $days=$isleapyear==true?29:28;
                    if($month<10)
                    {
                        $month="0".$month;
                    }
                    $end="$year/$month/$days"; 
                    break;
                case 4:
                case 6:
                case 9:
                case 11:
                    if($month<10)
                    {
                        $month="0".$month;
                    }
                    $end="$year/$month/30"; 
                    break;
            }
            //Get Target & Baseline
            $targetqry=Db::table('mne_kpis_targets')
                ->select('target_val','baseline_val')
                ->where('year',$year)
                ->where('kpi_id',$kpi_id)
                ->get();
            $targetresult=json_decode($targetqry);
            if(!empty($targetresult)){
               $target=$targetresult[0]->target_val;
               $baseline=$targetresult[0]->baseline_val; 
            }else{
                $target='No Traget';
                $baseline='No Baseline set'; 
            }

            $year_months[$i]=array(
                "start"=>"$year/$month/01",
                "end"=>$end,
                "year_id"=>$year,
                "target_val"=>$target,
                "baseline_val"=>$baseline
            );
            }
            $year_months_start_end_days[$year]=$year_months;
            $year_months=[];
        }
        $months=array(
            "January",
            "February",
            "March",
            "April",
            "May",
            "June",
            "July",
            "August",
            "September",
            "October",
            "November",
            "December"
        );
        foreach($year_months_start_end_days as $year_data)
        {
            $monthly_count=[];
          
            foreach($year_data as $key=>$month){
            
                $ben_ous_query=Db::table('case_basicdataentry_details as t1')
                ->where('t1.target_group_id',3)
                ->whereBetween('t1.case_formrecording_date',array($month['start'],$month['end']));
               
                if (validateisNumeric($province_id)) {
                    $ben_ous_query->where('t1.province_id', $province_id);
                }
                if (validateisNumeric($district_id)) {
                    $ben_ous_query->where('t1.district_id', $district_id);
                }
                if (validateisNumeric($school_id)) {
                    $ben_ous_query->where('t1.school_id', $school_id);
                }
                
                $ous_count=  $ben_ous_query->count('t1.id');

                 $ben_ous_by_year_month[]=array(
                     "period"=>$month['start']." to ".$month['end'],
                     "month"=>$months[$key-1],
                     "count"=>$ous_count,
                     "year_id"=>$month['year_id'],
                     "target_val"=>$month['target_val'],
                     "baseline_val"=>$month['baseline_val']
                 );
            }
        }
        return  $ben_ous_by_year_month;
    }  
    public function determineKPI53Performance(Request $request, $result)
    {
        $target = (float)$result->target;
        $val = 0;
        $percentageMet = 0;
        $calculationDetails = $this->calculateKPI21($request, false);
        if ($calculationDetails->count() > 0) {
           $val = 100;
            $percentageMet = (((float)$val / $target) * 100);
        }
        return $this->formatKPIPerformance($target, $val, $percentageMet);
    }
    //KPI 54
    public function determineKPI54Performance(Request $request, $result)
    {
        $target = (float)$result->target;
        $val = 0;
        $percentageMet = 0;
        $calculationDetails = $this->calculateKPI21($request, false);
        if ($calculationDetails->count() > 0) {
            $val = 100;//$calculationDetails[0]->detail_value;
            $percentageMet = (((float)$val / $target) * 100);
        }
        return $this->formatKPIPerformance($target, $val, $percentageMet);
    }
    //KPI 55
    public function determineKPI55Performance(Request $request, $result)
    {
        $target = (float)$result->target;
        $val = 0;
        $percentageMet = 0;
        $calculationDetails = $this->calculateKPI21($request, false);
        if ($calculationDetails->count() > 0) {
           $val = 100;
            $percentageMet = (((float)$val / $target) * 100);
        }
        return $this->formatKPIPerformance($target, $val, $percentageMet);
    }
    public function calculateKPI55(Request $request){
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        $kpi_id=$request->input('kpi_id');
        $years=$this->getallyears($year_from,$year_to);
        $summary=array();
        foreach($years as $year)
            {
                $quarter_1=json_decode($this->KPItrainingBaseQuery($year,1,$province_id,$district_id,$school_id,$kpi_id));
                $quarter_2=json_decode($this->KPItrainingBaseQuery($year,2,$province_id,$district_id,$school_id,$kpi_id));
                $quarter_3=json_decode($this->KPItrainingBaseQuery($year,3,$province_id,$district_id,$school_id,$kpi_id)); 
                $quarter_4=json_decode($this->KPItrainingBaseQuery($year,4,$province_id,$district_id,$school_id,$kpi_id));
                $summary[]=array(
                    "year_id"=>$year,
                    "quaterone"=>$quarter_1[0]->total_attendance,
                    "quatertwo"=>$quarter_2[0]->total_attendance,
                    "quaterthree"=>$quarter_3[0]->total_attendance,
                    "quaterfour"=>$quarter_4[0]->total_attendance
                );
            }
        return $summary;

    }
    public function KPItrainingBaseQuery($year,$quarter,$province_id,$district_id,$school_id,$kpi_id)
    {
        // Quater 1
        if($quarter==1){
            $monthfrom= date($year . '-01-01');
            $monthto=date($year . '-03-31');
        }else if ($quarter==2){
            $monthfrom=date($year.'-04-01');
            $monthto=date($year.'-06-30');
        }else if($quarter==3){
            $monthfrom=date($year . '-07-01');
            $monthto=date($year . '-09-30');

        }else if ($quarter==4){
            $monthfrom=date($year . '-10-01');
            $monthto=date($year . '-12-31');
        }
        if($kpi_id==55)
        {  //GBV KPI
            $thematic_area=37;
        }else if($kpi_id==66){
            //KGS-MIS KPI
            $thematic_area=38;
        }else{
             $thematic_area='';
        }
        $query=DB::table('mne_trainingdata_entry as t1')
        ->select(DB::raw("COUNT(t2.id) AS total_attendance"))
        ->leftjoin('mne_training_attendance as t2','t2.training_id','=','t1.id')
        ->where('t1.date_from','>=',$monthfrom)
        ->where('t1.date_to','<=',$monthto);

        if(validateisNumeric($thematic_area)){
               $query->where('t1.thematic_area',$thematic_area);
        }else{
               $query->whereNotIn('t1.thematic_area', [38,37]);
        }
        if (validateisNumeric($province_id)) {
            $query->where('t1.province_id', $province_id);
        }
        if (validateisNumeric($district_id)) {
            $query->where('t1.district_id', $district_id);
        }
        if (validateisNumeric($school_id)) {
            $query->where('t1.school_id', $school_id);
        }
        
        $response=$query->get(); 
        return json_encode($response);
    }
    //KPI 56
    public function calculateKPI56(Request $request)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        $kpi_id=$request->input('kpi_id');
        if(!validateisNumeric($year_from))
        {
            $year_from=2016;
        }

        if(!validateisNumeric($year_to))
        {
            $year_to=date('Y');
        }
        $years=[];
        if($year_from==$year_to)
        {
            $years[]=$year_from;
        }else{
            $total_year_from=($year_to-$year_from)+1;
            for($i=0;$i<$total_year_from;$i++)
            {
                $year_to_use=$year_from+$i;
                $years[]=$year_to_use;
            }
        }

        $year_months_start_end_days=[];
        foreach($years as $year)
        {
            $year_months=[];
            for($i=1;$i<13;$i++){
            $end="";
            $month=$i;
            switch($month)
            {
                case 1://31 days
                case 3:
                case 5:
                case 7:
                case 8:
                case 10:
                case 12:
                    if($month<10)
                    {
                        $month="0".$month;
                    }
                    $end="$year/$month/31"; 
                    break;
                case 2://28/29 days
                    $isleapyear=!($year % 4) && ($year % 100 || !($year % 400));
                    $days=$isleapyear==true?29:28;
                    if($month<10)
                    {
                        $month="0".$month;
                    }
                    $end="$year/$month/$days"; 
                    break;
                case 4:
                case 6:
                case 9:
                case 11:
                    if($month<10)
                    {
                        $month="0".$month;
                    }
                    $end="$year/$month/30"; 
                    break;
            }
            //Get Target & Baseline
            $targetqry=Db::table('mne_kpis_targets')
                ->select('target_val','baseline_val')
                ->where('year',$year)
                ->where('kpi_id',$kpi_id)
                ->get();
            $targetresult=json_decode($targetqry);
            if(!empty($targetresult)){
               $target=$targetresult[0]->target_val;
               $baseline=$targetresult[0]->baseline_val; 
            }else{
                $target='No Traget';
                $baseline='No Baseline set'; 
            }

            $year_months[$i]=array(
                "start"=>"$year/$month/01",
                "end"=>$end,
                "year_id"=>$year,
                "target_val"=>$target,
                "baseline_val"=>$baseline
            );
            }
            $year_months_start_end_days[$year]=$year_months;
            $year_months=[];
        }
        $months=array(
            "January",
            "February",
            "March",
            "April",
            "May",
            "June",
            "July",
            "August",
            "September",
            "October",
            "November",
            "December"
        );
        foreach($year_months_start_end_days as $year_data)
        {
            $monthly_count=[];
          
            foreach($year_data as $key=>$month){
            
                $ben_srh_query=Db::table('case_basicdataentry_details as t1')
                ->join('case_referrals as t2','t1.id','=','t2.case_id')
                ->whereBetween('t1.case_formrecording_date',array($month['start'],$month['end']));
               
                if (validateisNumeric($province_id)) {
                    $ben_srh_query->where('t1.province_id', $province_id);
                }
                if (validateisNumeric($district_id)) {
                    $ben_srh_query->where('t1.district_id', $district_id);
                }
                if (validateisNumeric($school_id)) {
                    $ben_srh_query->where('t1.school_id', $school_id);
                }
                
                $srh_count=$ben_srh_query->count('t2.id');
                if($srh_count>0){
                    $totalsrh= $srh_count;
                }else{
                    $totalsrh= 0;
                }

                 $ben_srh_by_year_month[]=array(
                     "period"=>$month['start']." to ".$month['end'],
                     "month"=>$months[$key-1],
                     "count"=>$srh_count,
                     "year_id"=>$month['year_id'],
                     "target_val"=>$month['target_val'],
                     "baseline_val"=>$month['baseline_val']
                 );
            }
        }
        return  $ben_srh_by_year_month;
    }  
    public function determineKPI56Performance(Request $request, $result)
    {
        $target = (float)$result->target;
        $val = 0;
        $percentageMet = 0;
        $calculationDetails = $this->calculateKPI21($request, false);
        if ($calculationDetails->count() > 0) {
            $val = 100;
            $percentageMet = (((float)$val / $target) * 100);
        }
        return $this->formatKPIPerformance($target, $val, $percentageMet);
    }
    //KPI 57
    public function calculateKPI57(Request $request){
        //maureen
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        $kpi_id=$request->input('kpi_id');
        $getmonths=$this->getmonthsandyears($year_from,$year_to,$kpi_id);
        $getmonthnames=$this->getmonthnames();
        foreach($getmonths as $year_data)
            {
                foreach($year_data as $key=>$month){
                    $ben_srhealth_query=Db::table('case_basicdataentry_details as t1')
                    ->join('case_referrals as t2','t1.id','=','t2.case_id')
                    ->join('case_careplan_details as t3','t1.id','=','t3.case_id')
                    ->whereBetween('t2.date_reported',array($month['start'],$month['end']))
                    ->where('t3.checklist_id','=','8');
                   
                    if (validateisNumeric($province_id)) {
                        $ben_srhealth_query->where('t1.province_id', $province_id);
                    }
                    if (validateisNumeric($district_id)) {
                        $ben_srhealth_query->where('t1.district_id', $district_id);
                    }
                    if (validateisNumeric($school_id)) {
                        $ben_srhealth_query->where('t1.school_id', $school_id);
                    }
                    
                    $srhealth_count=$ben_srhealth_query->count('t2.id');
                    if($srhealth_count>0){
                        $totalsrhealth= $srhealth_count;
                    }else{
                        $totalsrhealth= 0;
                    }

                     $ben_srh_by_year_month[]=array(
                         "period"=>$month['start']." to ".$month['end'],
                         "month"=>$getmonthnames[$key-1],
                         "count"=>$totalsrhealth,
                         "year_id"=>$month['year_id'],
                         "target_val"=>$month['target_val'],
                         "baseline_val"=>$month['baseline_val']
                     );
                }
            }
        return $ben_srh_by_year_month;
    }
    //KPI 58
    public function calculateKPI58(Request $request)
    {
        // code...
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        $kpi_id=$request->input('kpi_id');
        $getmonths=$this->getmonthsandyears($year_from,$year_to,$kpi_id);
        $getmonthnames=$this->getmonthnames();
        foreach($getmonths as $year_data)
            {
                foreach($year_data as $key=>$month){
                    $ben_srhealth_query=Db::table('case_basicdataentry_details as t1')
                    ->join('case_referrals as t2','t1.id','=','t2.case_id')
                    ->join('case_careplan_details as t3','t1.id','=','t3.case_id')
                    ->whereBetween('t2.date_reported',array($month['start'],$month['end']))
                    ->where('t3.checklist_id','=','2');
                   
                    if (validateisNumeric($province_id)) {
                        $ben_srhealth_query->where('t1.province_id', $province_id);
                    }
                    if (validateisNumeric($district_id)) {
                        $ben_srhealth_query->where('t1.district_id', $district_id);
                    }
                    if (validateisNumeric($school_id)) {
                        $ben_srhealth_query->where('t1.school_id', $school_id);
                    }
                    
                    $srhealth_count=$ben_srhealth_query->count('t2.id');
                    if($srhealth_count>0){
                        $totalsrhealth= $srhealth_count;
                    }else{
                        $totalsrhealth= 0;
                    }

                     $ben_srh_by_year_month[]=array(
                         "period"=>$month['start']." to ".$month['end'],
                         "month"=>$getmonthnames[$key-1],
                         "count"=>$totalsrhealth,
                         "year_id"=>$month['year_id'],
                         "target_val"=>$month['target_val'],
                         "baseline_val"=>$month['baseline_val']
                     );
                }
            }
        return $ben_srh_by_year_month;
    }
    //KPI 59
    public function calculateKPI59(Request $request)
    {
        // code...
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        $kpi_id=$request->input('kpi_id');
        $getmonths=$this->getmonthsandyears($year_from,$year_to,$kpi_id);
        $getmonthnames=$this->getmonthnames();

        foreach($getmonths as $year_data)
            {
                foreach($year_data as $key=>$month){
                    $ben_srhealth_query=Db::table('case_basicdataentry_details as t1')
                    ->join('case_referrals as t2','t1.id','=','t2.case_id')
                    ->join('case_careplan_details as t3','t1.id','=','t3.case_id')
                    ->whereBetween('t2.date_reported',array($month['start'],$month['end']))
                    ->where('t3.checklist_id','>','0')
                    ->where('t3.checklist_id','<','24');

                   
                    if (validateisNumeric($province_id)) {
                        $ben_srhealth_query->where('t1.province_id', $province_id);
                    }
                    if (validateisNumeric($district_id)) {
                        $ben_srhealth_query->where('t1.district_id', $district_id);
                    }
                    if (validateisNumeric($school_id)) {
                        $ben_srhealth_query->where('t1.school_id', $school_id);
                    }
                    
                    $srhealth_count=$ben_srhealth_query->count('t2.id');
                    if($srhealth_count>0){
                        $totalsrhealth= $srhealth_count;
                    }else{
                        $totalsrhealth= 0;
                    }

                     $ben_srh_by_year_month[]=array(
                         "period"=>$month['start']." to ".$month['end'],
                         "month"=>$getmonthnames[$key-1],
                         "count"=>$totalsrhealth,
                         "year_id"=>$month['year_id'],
                         "target_val"=>$month['target_val'],
                         "baseline_val"=>$month['baseline_val']
                     );
                }
            }
        return $ben_srh_by_year_month;
    }
    public function calculateKPI60(Request $request)
    {
        // code...
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        $kpi_id=$request->input('kpi_id');
        $getmonths=$this->getmonthsandyears($year_from,$year_to,$kpi_id);
        $getmonthnames=$this->getmonthnames();

        foreach($getmonths as $year_data)
            {
                foreach($year_data as $key=>$month){
                    $targetthreeben=DB::table('case_basicdataentry_details')
                            ->where('target_group_id','=','3')
                            ->whereBetween('case_formrecording_date',array($month['start'],$month['end']));

                    $linkedben=DB::table('case_careplan_details as t1')
                    ->join('case_basicdataentry_details as t2','t2.id','=','t1.case_id')
                    ->whereBetween('t1.created_at',array($month['start'],$month['end']))
                    ->where('t2.target_group_id','=','3');
                    
                   
                    if (validateisNumeric($province_id)) {
                        $targetthreeben->where('t1.province_id', $province_id);
                        $linkedben->where('t2.province_id', $province_id);
                    }
                    if (validateisNumeric($district_id)) {
                        $targetthreeben->where('t1.district_id', $district_id);
                        $linkedben->where('t2.district_id', $district_id);
                    }
                    if (validateisNumeric($school_id)) {
                        $targetthreeben->where('t1.school_id', $school_id);
                        $linkedben->where('t2.school_id', $school_id);
                    }
                    
                    $totallinkedben=$linkedben->count('t2.id');
                    $totaltargetthreeben=$targetthreeben->count('id');
                    if($totallinkedben>0 && $totaltargetthreeben>0){
                         $precentage=($totaltargetthreeben*100)/$totallinkedben;
                    }else{
                        $precentage= 0;
                    }

                     $benlinked[]=array(
                         "period"=>$month['start']." to ".$month['end'],
                         "month"=>$getmonthnames[$key-1],
                         "count"=>$precentage,
                         "year_id"=>$month['year_id'],
                         "target_val"=>$month['target_val'],
                         "baseline_val"=>$month['baseline_val']
                     );
                }
            }
        return $benlinked;
    }
    //KPI 62
    public function calculateKPI62(Request $request){
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        $kpi_id=$request->input('kpi_id');
        $years=$this->getallyears($year_from,$year_to);
        $districts_years=$this->getdistrictsandyears($kpi_id,$year_from,$year_to,$province_id,$district_id);  
        //default if district_id is null show all districts
            foreach($districts_years as $row){
                $terms=json_decode($this->KPI62Basequery($province_id,$row['district_id'],$row['year_id']));

                $safefacility_year[]=array(
                        "termone"=>(int)$terms[0]->termone,
                        "termtwo"=>(int)$terms[0]->termtwo,
                        "termthree"=>(int)$terms[0]->termthree,
                        "year_id"=>$row['year_id'],
                        "district_name"=>$row['district_name'],
                        "target_val"=>$row['target_val'],
                        "baseline_val"=>$row['baseline_val'],
                );
             }

         return  $safefacility_year;
    }
    public function KPI62Basequery($province_id,$district_id,$year){

        $query=DB::table('mne_weeklyboarding_dataentry as t1')
                    ->select(DB::raw( "(SUM(if((t1.term=1 && t1.boarding_status=1),t1.no_of_kgs_ben,0))*100)/SUM(if(t1.term=1,t1.no_of_kgs_ben,0)) AS termone,
                         (SUM(if((t1.term=2 && t1.boarding_status=1),t1.no_of_kgs_ben,0))*100)/SUM(if(t1.term=2,t1.no_of_kgs_ben,0)) AS termtwo,
                         (SUM(if((t1.term=3 && t1.boarding_status=1),t1.no_of_kgs_ben,0))*100)/SUM(if(t1.term=3,t1.no_of_kgs_ben,0)) AS termthree"));


        if (validateisNumeric($province_id)) {
            $query->where('t1.province_id', $province_id);
        }
        if (validateisNumeric($district_id)) {
            $query->where('t1.district_id', $district_id);
        }
        if(validateisNumeric($year)){
            $query->where(DB::raw('YEAR(t1.created_at)'), $year);
        }
        $response=$query->get(); 
        return json_encode($response);

    }
    public function calculateKPI63(Request $request){
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $year_from = $request->input('year_from');
        $year_to = $request->input('year_to');
        $kpi_id=$request->input('kpi_id');
        $years=$this->getallyears($year_from,$year_to);
        $districts_years=$this->getdistrictsandyears($kpi_id,$year_from,$year_to,$province_id,$district_id);  
        //default if district_id is null show all districts
            foreach($districts_years as $row){
                $values=json_decode($this->KPI63Basequery($province_id,$row['district_id'],$row['year_id']));
                $facilitytransfers[]=array(
                        "termone"=>(int)$values[0]->termone,
                        "termtwo"=>(int)$values[0]->termtwo,
                        "termthree"=>(int)$values[0]->termthree,
                        "year_id"=>$row['year_id'],
                        "district_name"=>$row['district_name'],
                        "target_val"=>$row['target_val'],
                        "baseline_val"=>$row['baseline_val'],
                );
             }

  

         return  $facilitytransfers;
    }
    public function KPI63Basequery($province_id,$district_id,$year){

        $query=DB::table('beneficiary_information as t1')
                    ->leftjoin('beneficiary_enrollments as t2','t1.id','=','t2.beneficiary_id')
                    ->select(DB::raw( "COUNT(distinct(if( t2.term_id=1,t2.beneficiary_id,0 ))) AS termone,COUNT(distinct(if( t2.term_id=2,t2.beneficiary_id,0 ))) AS termtwo,COUNT(distinct(if( t2.term_id=3,t2.beneficiary_id,0 ))) AS termthree"));


        if (validateisNumeric($province_id)) {
            $query->where('t1.province_id', $province_id);
        }
        if (validateisNumeric($district_id)) {
            $query->where('t1.district_id', $district_id);
        }
        if(validateisNumeric($year)){
            $query->where(DB::raw('YEAR(t1.created_at)'), $year);
        }
        $response=$query->get(); 
        return json_encode($response);

    }
    public function determineKPI63Performance(Request $request, $result)
    {
        $target = (float)$result->target;
        $val = 0;
        $percentageMet = 0;
        $calculationDetails = $this->calculateKPI21($request, false);
        if ($calculationDetails->count() > 0) {
           $val = 100;
            $percentageMet = (((float)$val / $target) * 100);
        }
        return $this->formatKPIPerformance($target, $val, $percentageMet);
    }
    public function determineKPI64Performance(Request $request, $result)
    {
        $target = (float)$result->target;
        $val = 0;
        $percentageMet = 0;
        $calculationDetails = $this->calculateKPI21($request, false);
        if ($calculationDetails->count() > 0) {
            $val = 100;
            $percentageMet = (((float)$val / $target) * 100);
        }
        return $this->formatKPIPerformance($target, $val, $percentageMet);
    }
    public function determineKPI65Performance(Request $request, $result)
    {
        $target = (float)$result->target;
        $val = 0;
        $percentageMet = 0;
        $calculationDetails = $this->calculateKPI21($request, false);
        if ($calculationDetails->count() > 0) {
           $val = 100;
            $percentageMet = (((float)$val / $target) * 100);
        }
        return $this->formatKPIPerformance($target, $val, $percentageMet);
    }
    public function determineKPI66Performance(Request $request, $result)
    {
        $target = (float)$result->target;
        $val = 0;
        $percentageMet = 0;
        $calculationDetails = $this->calculateKPI21($request, false);
        if ($calculationDetails->count() > 0) {
            $val = 100;
            $percentageMet = (((float)$val / $target) * 100);
        }
        return $this->formatKPIPerformance($target, $val, $percentageMet);
    }
    public function determineKPI67Performance(Request $request, $result)
    {
         $counted_beneficiries=array();
        $total_supported=array();
        if($year<2022){
            /*echo "<2022 for the year ->".$year;*/
            //Get ben with feed payed
            $paid_4_beneficiaries_with_fees_query=Db::select("SELECT COUNT(t1.beneficiary_id),t1.beneficiary_id as ben_id,t5.beneficiary_id 
                                                            as ben2id FROM beneficiary_enrollments AS t1 
                                                            INNER JOIN beneficiary_payment_records AS t2 
                                                            ON t1.id=t2.enrollment_id 
                                                            LEFT JOIN beneficiary_information as t5
                                                            on t5.id=t1.beneficiary_id 
                                                            WHERE t1.year_of_enrollment=$year
                                                            GROUP BY t1.beneficiary_id");
            //Check if beneficiary was supported in previous year.
            
                if (validateisNumeric($filterprovince)) {
                    $paid_4_beneficiaries_with_fees_query->where('t5.province_id', $filterprovince);
                }
                if (validateisNumeric($filterdistrict)) {
                    $paid_4_beneficiaries_with_fees_query->where('t5.district_id', $filterdistrict);
                }
             $overall_total_supported=count($paid_4_beneficiaries_with_fees_query);

        }else{
               /*  echo ">2022 for the year ->".$year;*/
            $paid_4_beneficiaries_query_with_no_zero_fees_query=Db::select("SELECT COUNT(t2.beneficiary_id),t2.beneficiary_id as ben_id,t5.beneficiary_id as ben2id 
                                 FROM   beneficiary_enrollments as t2 
                                 INNER JOIN beneficiary_payment_records AS t3 ON t2.id=t3.enrollment_id
                                 INNER JOIN payment_request_details as t4 
                                 on t4.id=t3.payment_request_id
                                  LEFT JOIN beneficiary_information as t5 
                                  on t5.id=t2.beneficiary_id 
                                   WHERE t2.year_of_enrollment>=$year AND decrypt(t2.annual_fees)>0  AND t4.status_id>=4 
                                    GROUP by t2.beneficiary_id ");
              if (validateisNumeric($filterprovince)) {
                    $paid_4_beneficiaries_query_with_no_zero_fees_query->where('t5.province_id', $filterprovince);
                }
                if (validateisNumeric($filterdistrict)) {
                    $paid_4_beneficiaries_query_with_no_zero_fees_query->where('t5.district_id', $filterdistrict);
                }
             // beneficiaries who received grant but not fees
                foreach($paid_4_beneficiaries_query_with_no_zero_fees_query as $ben_data)
                {
                   
                    if(!in_array($ben_data->ben2id,$counted_beneficiries)){
                       
                        $counted_beneficiries[]=$ben_data->ben2id;
                    }
                }
        
        $total_supported=$counted_beneficiries;
            $grant_recieved_query=Db::select("SELECT DISTINCT t1.beneficiary_id  as ben_id 
                                                FROM   payments_beneficiaries_grant as t1
                                                LEFT JOIN beneficiary_information as t2
                                                on t2.id=t1.beneficiary_id 
                                                WHERE t1.grant_recieved=1 AND t1.payment_year=$year");
                if (validateisNumeric($filterprovince)) {
                    $grant_recieved_query->where('t2.province_id', $filterprovince);
                }
                if (validateisNumeric($filterdistrict)) {
                    $grant_recieved_query->where('t2.district_id', $filterdistrict);
                }
            foreach($grant_recieved_query as $ben)
            {
                if(!in_array($ben->ben_id,$total_supported))
                {
                    $total_supported[]=$ben->ben_id;
                }
            }
             $overall_total_supported=count($total_supported);
        }
    }
    
    public function getallbeneficiaries($year_from,$year_to)
    {

        if (validateisNumeric($year_from) && validateisNumeric($year_to)) {
            $paid_4_beneficiaries_with_zero_fees_query=Db::select("SELECT COUNT(t1.beneficiary_id) FROM beneficiary_enrollments AS t1 
                                                                 INNER JOIN beneficiary_payment_records AS t2 ON t1.id=t2.enrollment_id 
                                                                 WHERE  t1.year_of_enrollment BETWEEN  $year_from AND $year_to
                                                                  GROUP BY t1.beneficiary_id");
        }else{
             $paid_4_beneficiaries_with_zero_fees_query=Db::select("SELECT COUNT(t1.beneficiary_id) FROM beneficiary_enrollments AS t1 
                                                                 INNER JOIN beneficiary_payment_records AS t2 ON t1.id=t2.enrollment_id 
                                                                  GROUP BY t1.beneficiary_id");
        }
      
        $paid_4_beneficiaries=count($paid_4_beneficiaries_with_zero_fees_query);
       
        return  number_format($paid_4_beneficiaries);

    }

    //Get all years from 2016
    public function getallyears($year_from,$year_to){
         if (validateisNumeric($year_from) && validateisNumeric($year_to) ) {
             //start year
            $year_from=$year_from;
            //End Year
            $year_to=$year_to;
        }else{
            //start year
            $year_from=2016;
            //End Year
            $year_to=date('Y');
        }
        $years=[];
        $total_year_from=($year_to-$year_from)+1;
       for($i=0;$i<$total_year_from;$i++)
        {
            $year_to_use=$year_from+$i;
            $years[]=$year_to_use;
        }
     return $years;
    }
    //Get all districts with years
    public function getdistrictsandyears($kpi,$year_from,$year_to,$province_id,$district_id){
        //Get Years
        $years=$this->getallyears($year_from,$year_to);
        //get all districts
            $district_query=Db::table('districts')
                        ->select('id','name');

        if(validateisNumeric($province_id)){
            //get Filtered districts
            $district_query->where('province_id',$province_id);
        }
        if(validateisNumeric($district_id)){
             //get one districts
            $district_query->where('id',$district_id);
        }
          $results = $district_query->get();
        //get baseline &target
        foreach($years as $year){
            $targetqry=Db::table('mne_kpis_targets')
                ->select('target_val','baseline_val')
                ->where('year',$year)
                ->where('kpi_id',$kpi)
                ->get();
            $targetresult=json_decode($targetqry);
            if(!empty($targetresult)){
               $target=$targetresult[0]->target_val;
               $baseline=$targetresult[0]->baseline_val; 
            }else{
                $target='No Traget';
                $baseline='No Baseline set'; 
            }
            $years_targets[]=array(
                "year_id"=>$year,
                "target_val"=>$target,
                "baseline_val"=>$baseline
            );
        }
        foreach($results as $row){
            foreach($years_targets as $value){
                $district_year[]=array(
                    "year_id"=>$value['year_id'],
                    "target_val"=>$value['target_val'],
                    "baseline_val"=>$value['baseline_val'],
                    "district_id"=>$row->id,
                    "district_name"=>$row->name
                );
            }
        }
        return $district_year;
    }

    public function getmonthsandyears($year_from,$year_to,$kpi_id)
    {
        //Get Years
        $years=$this->getallyears($year_from,$year_to);
        $year_months_start_end_days=[];
        foreach($years as $year)
        {
            $year_months=[];
            for($i=1;$i<13;$i++){
            $end="";
            $month=$i;
            switch($month)
            {
                case 1://31 days
                case 3:
                case 5:
                case 7:
                case 8:
                case 10:
                case 12:
                    if($month<10)
                    {
                        $month="0".$month;
                    }
                    $end="$year/$month/31"; 
                    break;
                case 2://28/29 days
                    $isleapyear=!($year % 4) && ($year % 100 || !($year % 400));
                    $days=$isleapyear==true?29:28;
                    if($month<10)
                    {
                        $month="0".$month;
                    }
                    $end="$year/$month/$days"; 
                    break;
                case 4:
                case 6:
                case 9:
                case 11:
                    if($month<10)
                    {
                        $month="0".$month;
                    }
                    $end="$year/$month/30"; 
                    break;
            }
            //Get Target & Baseline
            $targetqry=Db::table('mne_kpis_targets')
                ->select('target_val','baseline_val')
                ->where('year',$year)
                ->where('kpi_id',$kpi_id)
                ->get();
            $targetresult=json_decode($targetqry);
            if(!empty($targetresult)){
               $target=$targetresult[0]->target_val;
               $baseline=$targetresult[0]->baseline_val; 
            }else{
                $target='No Traget';
                $baseline='No Baseline set'; 
            }

            $year_months[$i]=array(
                "start"=>"$year/$month/01",
                "end"=>$end,
                "year_id"=>$year,
                "target_val"=>$target,
                "baseline_val"=>$baseline
            );
            }
            $year_months_start_end_days[$year]=$year_months;
            $year_months=[];
        }

        return $year_months_start_end_days;
    }
    public function getmonthnames()
    {
        // code...
        $months=array(
            "January",
            "February",
            "March",
            "April",
            "May",
            "June",
            "July",
            "August",
            "September",
            "October",
            "November",
            "December"
        );
        return $months;
    }
    //End Maureen

}
