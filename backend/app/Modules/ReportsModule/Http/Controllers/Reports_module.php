<?php
/**
 * Created by PhpStorm.
 * User: Kip
 * Date: 9/26/2017
 * Time: 10:39 AM
 */

namespace App\Modules\ReportsModule\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\configurations\Models\Template;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\DB;
use PDF;
use JasperPHP;
use Excel;
use Illuminate\Database\Query\Builder;

Builder::macro('if', function ($condition, $column, $operator, $value) {
    if ($condition) {
        return $this->where($column, $operator, $value);
    }

    return $this;
});

class Reports_module extends Controller
{
    //functions for the reports module process
    public function getBenEnrollmentstatuses()
    {

        $qry = DB::table('beneficiary_enrollement_statuses');
        $data = $qry->get();
        getParamsdata($qry);
    }

    function returnFiltervariable($name, $value)
    {
        $data = array();
        if (validateisNumeric($value)) {
            $data[$name] = intval($value);
        }
        return $data;

    }

    public function getTermlyEnrollmentDetailsRpt(Request $req)
    {
        try {
            $qry = DB::table('beneficiary_enrollments as t1')
                ->select(DB::raw('t1.year_of_enrollment,
                                  SUM(IF(t1.term_id = 1, 1,0)) as term1,
                                  SUM(IF(t1.term_id = 2, 1,0)) as term2,
                                  SUM(IF(t1.term_id = 3, 1,0)) as term3'))
                ->where('t1.is_validated', 1)
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

    public function getBeneficiaryenrollmentRpt(Request $req)
    {
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->select(DB::raw('t2.name as district_name,
                                  SUM(IF(t1.enrollment_status = 1,1,0)) as active,
                                  SUM(IF(t1.enrollment_status = 4,1,0)) as completed,
                                  SUM(IF(t1.enrollment_status = 5,1,0)) as pending,
                                  SUM(IF(t1.enrollment_status = 2,1,0)) as suspended'))
                ->groupBy('t1.district_id');
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

    public function getEnrollmentStatusesDataReportsPerHomeDistrict(Request $req)
    {
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->join('cwac as t3', 't1.cwac_id', '=', 't3.id')
                ->select(DB::raw('t1.district_id,t2.name as district_name,t3.name as cwac_name,
                                  SUM(IF(t1.enrollment_status = 1,1,0)) as active,
                                  SUM(IF(t1.enrollment_status = 4,1,0)) as completed,
                                  SUM(IF(t1.enrollment_status = 5,1,0)) as pending,
                                  SUM(IF(t1.enrollment_status = 2,1,0)) as suspended'))
                ->groupBy('t1.cwac_id');
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

    function getDistrictfilterstr($district_id)
    {
        $district_filter = '';
        $district_id = json_decode($district_id);
        $case_data = array();
        foreach ($district_id as $key => $district) {

            $district_filter .= $case_data['case_id'];

        }
        return $district_filter;

    }

    function returnBeneficiairyenrollmentfilter($req)
    {
        $province_filter = $this->returnFiltervariable('province_id', $req->province_id);
        //$district_filter = $this->returnFiltervariable('t3.id', $req->district_id);
        $district_id = $req->district_id;
        $home_district = $req->home_district;

        $enrollment_id = $req->enrollment_id;

        $group_byhome = $req->group_byhome;

        $year_of_enrollment = $req->year_of_enrollment;

        //print_r($district_filter);
        $enrollment_filter = $this->returnFiltervariable('enrollment_id', $enrollment_id);
        $year_of_enrollment = $this->returnFiltervariable('year_of_enrollment', $year_of_enrollment);

        $group_byarray = array('group_byhome' => intval($group_byhome));
        $district_filter = $this->returnFiltervariable('district_id', $req->district_id);
        $homedistrict_filter = $this->returnFiltervariable('home_district', $req->home_district);

        $where_array = array_merge($province_filter, $enrollment_filter, $group_byarray, $homedistrict_filter, $district_filter, $year_of_enrollment);
        return $where_array;
    }

    function returnFilterNotnullString($key, $value)
    {
        $data = array();
        if ($value != '' && $value != null) {
            $data[$key] = (string)implode(',', json_decode($value));
        }
        return $data;
    }

    public function printBeneficiarysummaryDetails(Request $req)
    {

        $where_array = $this->returnBeneficiairyenrollmentfilter($req);
        $input = 'beneficiary_reports\PrintsummaryEnrollment.jasper';
        generateJasperrpt($input, 'Beneficiary Summary', 'pdf', $where_array);
    }

    public function exportBeneficiarysummaryDetails(Request $req)
    {

        $where_array = $this->returnBeneficiairyenrollmentfilter($req);
        $input = 'beneficiary_reports\PrintsummaryEnrollment.jasper';
        generateJasperrpt($input, 'Beneficiary Summary', 'pdf', $where_array);

    }

    ///the termly enrollements reports
    public function printTermlysummaryDetails(Request $req)
    {
        $filter_data = array();
        $where_array = array('beneficiary_status' => 4);
        $province_filter = $this->returnFiltervariable('t3.province_id', $req->province_id);
        $enrollment_year_filter = $this->returnFiltervariable('t5.year_of_enrollment', $req->year_of_enrollment);
        $term_filter = $this->returnFiltervariable('t5.term_id', $req->term_id);
        $grade_filter = $this->returnFiltervariable('t5.school_grade', $req->grade);

        //$district_filter = $this->returnFiltervariable('t3.id', $req->district_id);
        $district_id = $req->district_id;
        $enrollment_id = $req->enrollment_id;

        $where_array = array_merge($where_array, $province_filter, $enrollment_year_filter, $term_filter, $grade_filter);

        $input = 'Beneficairy_enrollmentDetailedrpt.jasper';//'TermlyEnrollmentreport.jasper';

        //$output = public_path().'\reports_templates\Case_Information';

        generateJasperrpt($input, 'Termly Enrollment Report', 'pdf', array());


    }

    public function printTermlyenrollmentDetailedrpt(Request $req)
    {
        $filter_data = array();
        $where_array = array('beneficiary_status' => 4);
        $province_filter = $this->returnFiltervariable('t3.province_id', $req->province_id);
        $enrollment_year_filter = $this->returnFiltervariable('t5.year_of_enrollment', $req->year_of_enrollment);
        $term_filter = $this->returnFiltervariable('t5.term_id', $req->term_id);
        $grade_filter = $this->returnFiltervariable('t5.school_grade', $req->grade);

        //$district_filter = $this->returnFiltervariable('t3.id', $req->district_id);
        $district_id = $req->district_id;
        $enrollment_id = $req->enrollment_id;

        $where_array = array_merge($where_array, $province_filter, $enrollment_year_filter, $term_filter, $grade_filter);

        $input = 'TermlyEnrollmentdetailsreport.jasper';

        //$output = public_path().'\reports_templates\Case_Information';

        generateJasperrpt($input, 'Termly Enrollment Detailed Report', 'pdf', array());


    }

    public function exportTermlyenrollmentDetailedrpt(Request $req)
    {
        $filter_data = array();
        $where_array = array('beneficiary_status' => 4);
        $province_filter = $this->returnFiltervariable('t3.province_id', $req->province_id);
        $enrollment_year_filter = $this->returnFiltervariable('t5.year_of_enrollment', $req->year_of_enrollment);
        $term_filter = $this->returnFiltervariable('t5.term_id', $req->term_id);
        $grade_filter = $this->returnFiltervariable('t5.school_grade', $req->grade);

        //$district_filter = $this->returnFiltervariable('t3.id', $req->district_id);
        $district_id = $req->district_id;
        $enrollment_id = $req->enrollment_id;

        $where_array = array_merge($where_array, $province_filter, $enrollment_year_filter, $term_filter, $grade_filter);

        $input = 'TermlyEnrollmentdetailsreport.jasper';

        //$output = public_path().'\reports_templates\Case_Information';

        generateJasperrpt($input, 'Termly Enrollment Detailed Report', 'pdf', array());


    }

    ///the termly enrollements reports
    public function exportTermlysummaryDetails(Request $req)
    {
        $filter_data = array();
        $where_array = array('beneficiary_status' => 4);
        $province_filter = $this->returnFiltervariable('t3.province_id', $req->province_id);
        $enrollment_year_filter = $this->returnFiltervariable('t5.year_of_enrollment', $req->year_of_enrollment);
        $term_filter = $this->returnFiltervariable('t5.term_id', $req->term_id);
        $grade_filter = $this->returnFiltervariable('t5.school_grade', $req->grade);

        //$district_filter = $this->returnFiltervariable('t3.id', $req->district_id);
        $district_id = $req->district_id;
        $enrollment_id = $req->enrollment_id;

        $where_array = array_merge($where_array, $province_filter, $enrollment_year_filter, $term_filter, $grade_filter);

        $input = 'TermlyEnrollmentreport.jasper';

        //$output = public_path().'\reports_templates\Case_Information';

        generateJasperrpt($input, 'Termly Enrollment Report', 'xls', $where_array);


    }

    public function printBeneficiaryDetailedrpt(Request $req)
    {
        $province_filter = $this->returnFiltervariable('t3.province_id', $req->province_id);
        //$district_filter = $this->returnFiltervariable('t3.id', $req->district_id);
        $district_id = $req->district_id;
        $enrollment_id = $req->enrollment_id;
        $enrolled_from = ($req->enrolled_from);
        $enrolled_to = formatDate($req->enrolled_to);
        //print_r($district_filter);
        $enrollment_filter = $this->returnFiltervariable('t4.id', $enrollment_id);

        //checnge the district id as strings

        $where_array = array_merge($province_filter, $enrollment_filter);

        $input = 'Beneficairy_enrollmentDetailedrpt.jasper';

        //$output = public_path().'\reports_templates\Case_Information';
        //  $where_array
        generateJasperrpt($input, 'Beneficiary Information', 'pdf', array());

    }

    public function exportBeneficiaryDetailedrpt(Request $req)
    {
        $province_filter = $this->returnFiltervariable('t3.province_id', $req->province_id);
        //$district_filter = $this->returnFiltervariable('t3.id', $req->district_id);
        $district_id = $req->district_id;
        $enrollment_id = $req->enrollment_id;
        $enrolled_from = ($req->enrolled_from);
        $enrolled_to = formatDate($req->enrolled_to);
        //print_r($district_filter);
        $enrollment_filter = $this->returnFiltervariable('t4.id', $enrollment_id);

        //checnge the district id as strings

        $where_array = array_merge($province_filter, $enrollment_filter);

        $input = 'Beneficairy_enrollmentDetailedrpt.jasper';

        //$output = public_path().'\reports_templates\Case_Information';
        //  $where_array
        generateJasperrpt($input, 'Beneficiary Information', 'xls', array());

    }

    function getQueryfields($qry)
    {
        $results = convertStdClassObjToArray($qry->first());
        $results = decryptSimpleArray($results);
        $fields = mysqli_num_fields($qry->first());
        return $fields;
    }

    public function exportDataBeneficiaryDetailedrpt(Request $req)
    {
        $province_filter = $this->returnFiltervariable('t3.province_id', $req->province_id);
        //$district_filter = $this->returnFiltervariable('t3.id', $req->district_id);
        $district_id = $req->district_id;
        $enrollment_id = $req->enrollment_id;
        $enrolled_from = ($req->enrolled_from);
        $enrolled_to = formatDate($req->enrolled_to);
        //print_r($district_filter);
        $enrollment_filter = $this->returnFiltervariable('t4.id', $enrollment_id);

        //checnge the district id as strings
        //t1.*,
        $where_array = array_merge($province_filter, $enrollment_filter);
        //use the phpExcel//select sum(t1.school_fees) from beneficiary_enrollments t1 inner join beneficiary_payment_records t2 on t1.id = t2.enrollment_id where t1.beneficiary_id = t1.id as school_fees',
        $qry = DB::table('beneficiary_information as t1')
            ->select('t1.beneficiary_id as Beneficiary Id', 't1.first_name as FirstName', 't1.last_name as LastName', 't1.dob as DOB', 't1.current_school_grade', 't1.exam_number', 't9.name as VerificationRecommendation', 't10.name as Letter Received', 't1.date_on_enrollment as EnrollmentDate', 't2.name as School Name', 't7.name as School Province', 't3.name as School District', 't5.hhh_nrc_number', 't5.hhh_fname as HHH FirstName', 't5.hhh_lname as  HHH LastName', 't8.name as CWAC Name', 't6.name as Home District', 't4.name as BeneficiaryStatus')
            ->join('school_information as t2', 't1.school_id', '=', 't2.id')
            ->join('districts as t3', 't2.district_id', '=', 't3.id')
            ->join('beneficiary_enrollement_statuses as t4', 't1.enrollment_status', '=', 't4.id')
            ->join('households as t5', 't1.household_id', '=', 't5.id')
            ->join('districts as t6', 't1.district_id', '=', 't6.id')
            ->join('provinces as t7', 't3.province_id', '=', 't7.id')
            ->join('cwac as t8', 't5.cwac_id', '=', 't8.id')
            ->leftJoin('verification_recommendation as t9', 't1.verification_recommendation', '=', 't9.id')
            ->leftJoin('confirmations as t10', 't1.is_letter_received', '=', 't10.id')
            ->where($where_array);
        //verification_recommendation

        $results = convertStdClassObjToArray($qry->get());
        $results = decryptArray($results);

        Excel::create('Beneficiary Information', function ($excel) use ($results) {
            // Set the title
            $excel->setTitle('Beneficiary Information');
            $excel->setCreator('KGS -Softclans Technologies');
            $excel->setDescription('Enrolled Beneficiaries');

            //get the detail

            $excel->sheet('sheet1', function ($sheet) use ($results) {
                $sheet->fromArray($results, null, 'A1', false, true);

            });

        })->download('xlsx');

    }

    public function exportDataTermlyDetailedrpt(Request $req)
    {
        $filter_data = array();
        $where_array = array('beneficiary_status' => 4);
        $province_filter = $this->returnFiltervariable('t3.province_id', $req->province_id);
        $enrollment_year_filter = $this->returnFiltervariable('t5.year_of_enrollment', $req->year_of_enrollment);
        $term_filter = $this->returnFiltervariable('t5.term_id', $req->term_id);
        $grade_filter = $this->returnFiltervariable('t5.school_grade', $req->grade);

        //$district_filter = $this->returnFiltervariable('t3.id', $req->district_id);
        $district_id = $req->district_id;

        $where_array = array_merge($where_array, $province_filter, $enrollment_year_filter, $term_filter, $grade_filter);
        //the query
        $qry = DB::table('beneficiary_information as t1')
            ->select('t12.name as school_enrollment_status', 't1.beneficiary_id as Beneficiary Id', 't1.first_name as FirstName', 't1.last_name as LastName', 't1.dob as DOB', 't1.current_school_grade', 't1.exam_number', 't9.name as VerificationRecommendation', 't10.name as Letter Received', 't1.date_on_enrollment as EnrollmentDate', 't2.name as School Name', 't7.name as School Province', 't3.name as School District', 't5.hhh_nrc_number', 't5.hhh_fname as HHH FirstName', 't5.hhh_lname as  HHH LastName', 't8.name as CWAC Name', 't6.name as Home District', 't4.name as BeneficiaryStatus', 't11.school_grade', 't11.year_of_enrollment', 't13.name as school_term', 't11.school_fees')
            ->join('school_information as t2', 't1.school_id', '=', 't2.id')
            ->join('districts as t3', 't2.district_id', '=', 't3.id')
            ->join('beneficiary_enrollement_statuses as t4', 't1.enrollment_status', '=', 't4.id')
            ->join('households as t5', 't1.household_id', '=', 't5.id')
            ->join('districts as t6', 't1.district_id', '=', 't6.id')
            ->join('provinces as t7', 't3.province_id', '=', 't7.id')
            ->join('cwac as t8', 't5.cwac_id', '=', 't8.id')
            ->leftJoin('verification_recommendation as t9', 't1.verification_recommendation', '=', 't9.id')
            ->leftJoin('confirmations as t10', 't1.is_letter_received', '=', 't10.id')
            ->join('beneficiary_enrollment as t11', 't1.id', '=', 't10.beneficiary_id')
            ->join('beneficiary_school_statuses as t12', 't11.beneficiary_schoolstatus_id', '=', 't10.id')
            ->join('school_terms as t13', 't11.term_id', '=', 't13.id')
            ->where($where_array);
        $results = convertStdClassObjToArray($qry->get());
        $results = decryptArray($results);

        Excel::create('Termly Enrollment Information', function ($excel) use ($results) {
            // Set the title
            $excel->setTitle('Enrollment Information');
            $excel->setCreator('KGS -Softclans Technologies');
            $excel->setDescription('Enrollment Information');

            //get the detail

            $excel->sheet('sheet1', function ($sheet) use ($results) {
                $sheet->fromArray($results, null, 'A1', false, true);

            });

        })->download('xlsx');

    }

    public function getBenhome_Districts(Request $req)
    {
        $get_data = $req->all();
        $where_data = array();
        if (isset($get_data['province_id'])) {

            $where_data['t3.province_id'] = $get_data['province_id'];
        }

        $qry = DB::table('beneficiary_information as t1')
            ->select('t3.name', 't3.id')
            ->join('districts as t3', 't1.district_id', '=', 't3.id')
            ->groupBy('t3.id')
            ->where($where_data)
            ->get();
        $results = convertStdClassObjToArray($qry);
        $results = decryptArray($results);
        $res = array(
            'results' => $results
        );
        //var_dump($res);
        json_output($res);
    }

    public function getPayment_disbursementrptStr1(Request $req)
    {
        $school_id = $req->input('school_id');
        $district_id = $req->input('district_id');
        $province_id = $req->input('province_id');
        $term_id = $req->input('term_id');
        $year_of_enrollment = $req->input('year_of_enrollment');
        $filter = array();
        $school_filter = getEnquiryfilter($req->input('school_id'), 't2.id');
        $district_filter = getEnquiryfilter($req->input('district_id'), 't2.district_id');
        $province_filter = getEnquiryfilter($req->input('province_id'), 't3.province_id');
        $term_filter = getEnquiryfilter($req->input('term_id'), 't7.term_id');
        $year_filter = getEnquiryfilter($req->input('year_filter'), 't7.payment_year');
        $filter = array_merge($school_filter, $district_filter, $province_filter, $year_filter, $term_filter);
        $qry = DB::table('beneficiary_enrollments as t1')
            ->select(DB::raw('t2.name as school_name,t2.code as school_emisno,t3.code as district_code, t3.name as district_name,
                              t4.name as province_name,count(t1.beneficiary_id) as no_of_beneficiairies, sum(t1.school_fees) as requested_disbursement,
                              (select sum(amount_transfered) from payment_disbursement_details t where t.school_id = t2.id and t.payment_request_id = t7.id) as totalfees_disbursed'))
            ->join('school_information as t2', 't1.school_id', '=', 't2.id')
            ->join('districts as t3', 't2.district_id', '=', 't3.id')
            ->join('provinces as t4', 't3.province_id', '=', 't4.id')
            ->join('beneficiary_payment_records as t6', 't1.id', '=', 't6.enrollment_id')
            ->join('payment_request_details as t7', 't6.payment_request_id', '=', 't7.id')
            ->join('school_terms as t8', 't7.term_id', '=', 't8.id')
            ->where($filter)
            ->groupBy('t2.id')
            ->get();
        $res = array(
            'results' => $qry
        );
        json_output($res);
    }

    public function getPayment_disbursementrptStr(Request $req)
    {
        $year = $req->input('year_filter');
        $qry = DB::table('beneficiary_enrollments as t1')
            ->select(DB::raw("t2.name as school_name,t2.code as school_emisno,t3.code as district_code, t3.name as district_name,
                              count(t1.beneficiary_id) as no_of_beneficiairies, sum(t1.school_fees) as requested_disbursement,
                              SUM(IF(t1.term_id=1 AND t1.is_validated=1,1,0)) as validated_term1,
                              SUM(IF(t1.term_id=2 AND t1.is_validated=1,1,0)) as validated_term2,
                              SUM(IF(t1.term_id=3 AND t1.is_validated=1,1,0)) as validated_term3,
                              SUM(IF(t6.id IS NOT NULL AND t1.term_id=1,1,0)) as committed_term1,
                              SUM(IF(t6.id IS NOT NULL AND t1.term_id=2,1,0)) as committed_term2,
                              SUM(IF(t6.id IS NOT NULL AND t1.term_id=3,1,0)) as committed_term3,
                              SUM(IF(t1.term_id=1 AND t1.is_validated=1,t1.school_fees,0)) as validated_fees_term1,
                              SUM(IF(t1.term_id=2 AND t1.is_validated=1,t1.school_fees,0)) as validated_fees_term2,
                              SUM(IF(t1.term_id=3 AND t1.is_validated=1,t1.school_fees,0)) as validated_fees_term3,
                              SUM(IF(t6.id IS NOT NULL AND t1.term_id=1,t1.school_fees,0)) as committed_fees_term1,
                              SUM(IF(t6.id IS NOT NULL AND t1.term_id=2,t1.school_fees,0)) as committed_fees_term2,
                              SUM(IF(t6.id IS NOT NULL AND t1.term_id=3,t1.school_fees,0)) as committed_fees_term3
                              "))
            ->join('school_information as t2', 't1.school_id', '=', 't2.id')
            ->join('districts as t3', 't2.district_id', '=', 't3.id')
            ->leftJoin('beneficiary_payment_records as t6', 't1.id', '=', 't6.enrollment_id')
            ->leftJoin('payment_request_details as t7', 't6.payment_request_id', '=', 't7.id')
            /*    ->leftJoin('payment_disbursement_details as t8', function ($join) {
                    $join->on('t8.payment_request_id', '=', 't7.id')
                        ->on('t8.school_id', '=', 't2.id');
                })*/
            ->where('t1.is_validated', 1)
            ->where('t1.year_of_enrollment', $year)
            ->groupBy('t2.id');
        $results = $qry->get();
        $res = array(
            'results' => $results
        );
        json_output($res);
    }

    public function getTermlySchoolsDisbursementsRpt(Request $req)
    {
        $year = $req->input('year_filter');
        try {
            $qry = DB::table('school_information as t1')
                ->join('payment_disbursement_details as t2', 't1.id', '=', 't2.school_id')
                ->join('payment_request_details as t3', 't3.id', '=', 't2.payment_request_id')
                ->join('districts as t4', 't1.district_id', '=', 't4.id')
                ->select(DB::raw('t1.district_id,t1.name as school_name,t1.code as school_emisno,t4.name as district_name,
                                  t4.code as district_code,
                                  SUM(IF(t3.term_id=1,t2.amount_transfered,0)) as disbursed_fees_term1,
                                  SUM(IF(t3.term_id=2,t2.amount_transfered,0)) as disbursed_fees_term2,
                                  SUM(IF(t3.term_id=3,t2.amount_transfered,0)) as disbursed_fees_term3 '))
                ->where('t3.payment_year', $year)
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

    function returnFilter($filter)
    {
        $where_statement = '';
        if (isset($filter)) {// No filter passed in
            $whereClauses = array();
            $whereClausesLocal = array();
            // Stores whereClauses
            $filters = json_decode($filter);
            // Decode the filter
            if ($filters != NULL) {
                foreach ($filters as $filter) {
                    switch ($filter->property) {
                        case 'beneficairy_no' :
                            $whereClauses[] = "beneficairy_no like '%" . ($filter->value) . "%'";
                            break;
                        case 'first_name' :
                            $whereClauses[] = "first_name like '%" . ($filter->value) . "%'";
                            break;
                        case 'last_name' :
                            $whereClauses[] = "last_name like '%" . ($filter->value) . "%'";
                            break;
                        case 'school_name' :
                            $whereClauses[] = "school_id = '" . ($filter->value) . "'";
                            break;
                        case 'current_school_grade' :
                            $whereClauses[] = "current_school_grade = '" . ($filter->value) . "'";
                            break;
                        case 'school_code' :
                            $whereClauses[] = "school_code like '%" . ($filter->value) . "%'";
                            break;
                        case 'email_address' :
                            $whereClauses[] = "email_address like '%" . ($filter->value) . "%'";
                            break;
                        case 'telephone_no' :
                            $whereClauses[] = "telephone_no like '%" . ($filter->value) . "%'";
                            break;
                        case 'sch_district' :
                            $whereClauses[] = "sch_district_id = '" . ($filter->value) . "'";
                            break;
                        case 'sch_province' :
                            $whereClauses[] = "sch_province_id = '" . ($filter->value) . "'";
                            break;
                        case 'sch_type' :
                            $whereClauses[] = "sch_type_id = '" . ($filter->value) . "'";
                            break;
                        case 'sch_type' :
                            $whereClauses[] = "sch_type_id = '" . ($filter->value) . "'";
                            break;
                        case 'hhh_fname' :
                            $whereClauses[] = "hhh_fname like '%" . ($filter->value) . "%'";
                            break;
                        case 'hhh_lname' :
                            $whereClauses[] = "hhh_lname like '%" . ($filter->value) . "%'";
                            break;
                        case 'cwac_name' :
                            $whereClauses[] = "cwac_name like '%" . ($filter->value) . "%'";
                            break;
                        case 'ben_district' :
                            $whereClauses[] = "ben_district_id = '" . ($filter->value) . "'";
                            break;
                        case 'ben_province' :
                            $whereClauses[] = "ben_province_id = '" . ($filter->value) . "'";
                            break;
                        case 'ben_category' :
                            $whereClauses[] = "ben_category like '%" . ($filter->value) . "%'";
                            break;
                        case 'ben_statuses' :
                            $whereClauses[] = "ben_statuses like '%" . ($filter->value) . "%'";
                            break;
                        case 'ben_school_statuses' :
                            $whereClauses[] = "ben_school_statuses_id = '" . ($filter->value) . "'";
                            break;
                        case 'enrollment_date' :
                            $whereClauses[] = "enrollment_date = '" . ($filter->value) . "'";
                            break;
                        case 'exam_number' :
                            $whereClauses[] = "exam_number like '%" . ($filter->value) . "%'";
                            break;
                        case 'is_letter_received' :
                            $whereClauses[] = "exam_number like '%" . ($filter->value) . "%'";
                            break;
                        case 'hhh_nrc_number' :
                            $whereClauses[] = "hhh_nrc_number like '%" . ($filter->value) . "%'";
                            break;
                        case 'hhh_nrc_number' :
                            $whereClauses[] = "hhh_nrc_number like '%" . ($filter->value) . "%'";
                            break;
                        case 'transaction_no' :
                            $whereClauses[] = "transaction_no like '%" . ($filter->value) . "%'";
                            break;
                        case 'payment_request_ref' :
                            $whereClauses[] = "payment_request_ref like '%" . ($filter->value) . "%'";
                            break;
                        case 'payment_status' :
                            $whereClauses[] = "payment_status = '" . ($filter->value) . "'";
                            break;
                        case 'is_validated' :
                            $whereClauses[] = "is_validated = '" . ($filter->value) . "'";
                            break;
                        case 'has_signed' :
                            $whereClauses[] = "has_signed = '" . ($filter->value) . "'";
                            break;
                        case 'previous_school_grade' :
                            $whereClauses[] = "previous_school_grade = '" . ($filter->value) . "'";
                            break;
                        case 'ben_enrollment_statuses' :
                            $whereClauses[] = "ben_enrollment_statuses_id = '" . ($filter->value) . "'";
                            break;

                        case 'term_name' :
                            $whereClauses[] = "term_id = '" . ($filter->value) . "'";
                            break;
                        case 'year_of_enrollment' :
                            $whereClauses[] = "year_of_enrollment = '" . ($filter->value) . "'";
                            break;
                        case 'year_of_enrollment' :
                            $whereClauses[] = "year_of_enrollment = '" . ($filter->value) . "'";
                            break;

                    }
                }
            }
            $testwhere_value = array_filter($whereClauses);
            if (!empty($testwhere_value)) {
                $where_statement = implode(' AND ', $whereClauses);
                $where_statement = 'where (' . $where_statement . ')';
            }

        }
        return $where_statement;

    }

    public function getBeneficiarypaymentsspreadsheetstr(Request $req)
    {

        $filter = $req->filter;
        $start = $req->start;
        $limit = $req->limit;
        $page = $req->page;
        $where_statement = '';
        $where_statement = $this->returnFilter($filter);

        $total_rows = DB::select("select count(beneficairy_id) as counter from vw_beneficiarypayment_information $where_statement");

        $qry = DB::select("select * from vw_beneficiarypayment_information $where_statement limit $start,$limit");
        $res = array(
            'results' => $qry,
            'totals' => $total_rows[0]->counter
        );
        //var_dump($res);
        json_output($res);

    }

    public function getBeneficiaryspreadsheetstr(Request $req)
    {

        $filter = $req->filter;
        $start = $req->start;
        $limit = $req->limit;
        $page = $req->page;
        $where_statement = '';
        $where_statement = $this->returnFilter($filter);

        $total_rows = DB::select("select count(beneficairy_id) as counter from vw_beneficiary_information $where_statement");
        $qry = DB::select("select * from vw_beneficiary_information $where_statement limit $start,$limit");

        $res = array(
            'results' => $qry,
            'totals' => $total_rows[0]->counter
        );
        //var_dump($res);
        json_output($res);

    }

    public function exportenrollmentcompleteBeneficiaryspreadsheet(Request $req)
    {

        //  $filter_array = $req->filter_array;
        $filter_array = Input::get('filter_array');
        $set_search = 0;

        $data = $filter_array;
        $whereClauses = array();

        $l = 1;
        $check_input = $req;
        //loop thru the inputs
        $check_input = Input::get();
        $where_statement = $this->getSamplespreadsheetfilter($filter_array);

        $data = array();
        $sql_query = DB::select("select * from vw_beneficiarypayment_information $where_statement");
        $results = convertStdClassObjToArray($sql_query);
        Excel::create('Termly Enrollment Information', function ($excel) use ($results) {
            // Set the title
            $excel->setTitle('Beneficiary Termly Enrollment Information');
            $excel->setCreator('KGS -Softclans Technologies');
            $excel->setDescription('Beneficiary Information');

            $excel->sheet('sheet1', function ($sheet) use ($results) {
                $sheet->fromArray($results, null, 'A1', false, true);

            });
        })->download('xlsx');

    }

    public function exportcompleteBeneficiaryspreadsheet(Request $req)
    {

        //  $filter_array = $req->filter_array;
        $filter_array = Input::get('filter_array');
        $set_search = 0;

        $data = $filter_array;
        $whereClauses = array();

        $l = 1;
        $check_input = $req;
        //loop thru the inputs
        $check_input = Input::get();
        $where_statement = $this->getSamplespreadsheetfilter($filter_array);

        $data = array();
        $sql_query = DB::select("select * from vw_beneficiary_information $where_statement");
        $results = convertStdClassObjToArray($sql_query);
        Excel::create('Beneficiary Information', function ($excel) use ($results) {
            // Set the title
            $excel->setTitle('Beneficiary Information');
            $excel->setCreator('KGS -Softclans Technologies');
            $excel->setDescription('Beneficiary Information');

            $excel->sheet('sheet1', function ($sheet) use ($results) {
                $sheet->fromArray($results, null, 'A1', false, true);

            });
        })->download('xlsx');

    }

    public function exportBeneficiaryspreadsheet(Request $req)
    {


        $title = 'Beneficiary Information';
        $filter_array = Input::get('filter_array');
        $set_search = 0;

        $data = $filter_array;
        $whereClauses = array();

        $l = 1;
        $check_input = $req;
        //loop thru the inputs
        $check_input = Input::get();
        foreach ($check_input as $key => $value) {
            if (is_numeric($key)) {
                $l = $l + 1;
            }
        }
        $where_statement = $this->getSamplespreadsheetfilter($filter_array);

        $data = array();

        $str = "<table border='1' width='70%'>";

        $str .= getExportReportHeader($l, $title);
        $sql_query = DB::select("select * from vw_beneficiary_information $where_statement");
        if ($sql_query) {
            $str .= "<tr><td>No</td>";
            $label = $this->getSpreadsheetlabel();

            foreach ($check_input as $key => $value) {

                if (is_numeric($key)) {
                    $key = $key - 1;
                    if ($value == 'on') {
                        //echo $key;

                        $str .= "<td>" . $label[$key] . "</td>";


                    }
                }
            }
            $str .= "</tr>";
            $i = 1;
            foreach ($sql_query as $rows) {
                $str .= "<tr><td>" . $i . "</td>";

                $values = $this->getSpreadsheetvalues($rows);


                foreach ($check_input as $key => $value) {

                    if (is_numeric($key)) {
                        $key = $key - 1;
                        if ($value == 'on') {

                            $str .= "<td>" . $values[$key] . "</td>";

                        }
                    }
                }
                $str .= "</tr>";
                $i++;
            }
        } else {

            $str .= "<tr align='center' style='font-weight: bold; font-type: 'Bookman Old Style'; font-size:14;'><td colspan = " . $l . ">No Beneficiary found under the specified filters.</td></tr>";

        }
        $str .= "</table>";

        $this->excelFooter($str);
    }

    //export exntollment reports
    public function exportenrollmentBeneficiaryspreadsheet(Request $req)
    {
        $title = 'Termly Beneficiary Information';
        $filter_array = Input::get('filter_array');
        $set_search = 0;

        $data = $filter_array;
        $whereClauses = array();
        $l = 1;
        $check_input = $req;
        //loop thru the inputs
        $check_input = Input::get();
        foreach ($check_input as $key => $value) {
            if (is_numeric($key)) {
                $l = $l + 1;
            }
        }
        $where_statement = $this->getSamplespreadsheetfilter($filter_array);

        $data = array();

        $str = "<table border='1' width='70%'>";

        $str .= getExportReportHeader($l, $title);
        $sql_query = DB::select("select * from vw_beneficiarypayment_information $where_statement");
        if ($sql_query) {
            $str .= "<tr><td>No</td>";
            $label = $this->getTermlyenrollSpreadsheetlabel();

            foreach ($check_input as $key => $value) {

                if (is_numeric($key)) {
                    $key = $key - 1;
                    if ($value == 'on') {
                        //echo $key;

                        $str .= "<td>" . $label[$key] . "</td>";


                    }
                }
            }
            $str .= "</tr>";
            $i = 1;
            foreach ($sql_query as $rows) {
                $str .= "<tr><td>" . $i . "</td>";

                $values = $this->getTermlyenrolSpreadsheetvalues($rows);


                foreach ($check_input as $key => $value) {

                    if (is_numeric($key)) {
                        $key = $key - 1;
                        if ($value == 'on') {

                            $str .= "<td>" . $values[$key] . "</td>";

                        }
                    }
                }
                $str .= "</tr>";
                $i++;
            }
        } else {

            $str .= "<tr align='center' style='font-weight: bold; font-type: 'Bookman Old Style'; font-size:14;'><td colspan = " . $l . ">No Beneficiary found under the specified filters.</td></tr>";

        }
        $str .= "</table>";

        $this->excelFooter($str);
    }

    function excelFooter($str)
    {
        $val = date('Y') . date('m') . date('d') . date('h') . date('i') . date('s');

        header("Content-type: application/octet-stream");
        header("Content-Disposition: attachment; filename=Beneficiary.xls");
        header("Pragma: no-cache");
        header("Expires: 0");
        echo $str;
        exit();
    }

    public function getSamplespreadsheetfilter($filter)
    {
        $where_statement = '';
        if (isset($filter)) {// No filter passed in
            $whereClauses = array();
            $whereClausesLocal = array();
            // Stores whereClauses
            $filters = json_decode($filter, true);

            // Decode the filter
            if (isset($filters->{'first_name'}) && $filters->{'first_name'} != '') {

                $whereClauses[] = "reference_no like '%" . $filters->{'beneficairy_no'} . "%'";
                $set_search = 1;
            }

            if ($filters != NULL) {
                foreach ($filters as $filter) {
                    $filter = $filter['initialConfig'];

                    switch ($filter['property']) {
                        case 'beneficairy_no' :
                            $whereClauses[] = "beneficairy_no like '%" . ($filter['value']) . "%'";
                            break;
                        case 'first_name' :
                            $whereClauses[] = "first_name like '%" . ($filter['value']) . "%'";
                            break;
                        case 'last_name' :
                            $whereClauses[] = "last_name like '%" . ($filter['value']) . "%'";
                            break;
                        case 'last_name' :
                            $whereClauses[] = "last_name like '%" . ($filter['value']) . "%'";
                            break;
                        case 'school_name' :
                            $whereClauses[] = "school_id = '" . ($filter['value']) . "'";
                            break;
                        case 'current_school_grade' :
                            $whereClauses[] = "current_school_grade like '%" . ($filter['value']) . "%'";
                            break;
                        case 'school_code' :
                            $whereClauses[] = "school_code like '%" . ($filter['value']) . "%'";
                            break;
                        case 'email_address' :
                            $whereClauses[] = "email_address like '%" . ($filter['value']) . "%'";
                            break;
                        case 'telephone_no' :
                            $whereClauses[] = "telephone_no like '%" . ($filter['value']) . "%'";
                            break;
                        case 'sch_district' :
                            $whereClauses[] = "sch_district_id = '" . ($filter['value']) . "'";
                            break;
                        case 'sch_province' :
                            $whereClauses[] = "sch_province_id = '" . ($filter['value']) . "'";
                            break;
                        case 'sch_type' :
                            $whereClauses[] = "sch_type_id = '" . ($filter['value']) . "'";
                            break;
                        case 'sch_type' :
                            $whereClauses[] = "sch_type_id = '" . ($filter['value']) . "'";
                            break;
                        case 'hhh_fname' :
                            $whereClauses[] = "hhh_fname like '%" . ($filter['value']) . "%'";
                            break;
                        case 'hhh_lname' :
                            $whereClauses[] = "hhh_lname like '%" . ($filter['value']) . "%'";
                            break;
                        case 'cwac_name' :
                            $whereClauses[] = "cwac_name like '%" . ($filter['value']) . "%'";
                            break;
                        case 'ben_district' :
                            $whereClauses[] = "ben_district_id = '" . ($filter['value']) . "'";
                            break;
                        case 'ben_province' :
                            $whereClauses[] = "ben_province_id = '" . ($filter['value']) . "'";
                            break;
                        case 'ben_category' :
                            $whereClauses[] = "ben_category like '%" . ($filter['value']) . "%'";
                            break;
                        case 'ben_statuses' :
                            $whereClauses[] = "ben_statuses like '%" . ($filter['value']) . "%'";
                            break;
                        case 'ben_school_statuses' :
                            $whereClauses[] = "ben_school_statuses_id = '" . ($filter['value']) . "'";
                            break;
                        case 'enrollment_date' :
                            $whereClauses[] = "enrollment_date = '" . ($filter['value']) . "'";
                            break;
                        case 'exam_number' :
                            $whereClauses[] = "exam_number like '%" . ($filter['value']) . "%'";
                            break;
                        case 'is_letter_received' :
                            $whereClauses[] = "exam_number like '%" . ($filter['value']) . "%'";
                            break;

                        //7
                        //9
                        //12
                    }
                }
            }

            $testwhere_value = array_filter($whereClauses);
            if (!empty($testwhere_value)) {
                $where_statement = implode(' AND ', $whereClauses);
                $where_statement = 'where (' . $where_statement . ')';

            }

        }
        return $where_statement;
    }

    function getSpreadsheetlabel()
    {
        $label = array('Beneficiary No', 'First Name', 'Last Name', 'DOB', 'First Name', 'Relationship to House Hold Head', 'School Grade', 'School Name',
            'School Code', 'School Email Address', 'School Telephone', 'School District',
            'School Province', 'School Type', 'HHH NRC No', 'HHH Name', 'HHH First Name', 'HHH Last Name', 'CWAC Name', 'Beneficiary District', 'Beneficiary Province', 'Beneficiary Category', 'Beneficiary Status', 'Beneficiary School Status', 'Beneficiary Enrollment Status', 'Enrollment Date', 'Examination No', 'Letter Received', 'Beneficiary Disability',);

        return $label;
    }

    function getTermlyenrollSpreadsheetlabel()
    {
        $label = array('Beneficiary No', 'First Name', 'Last Name', 'DOB', 'School Name', 'School Code', 'School Email Address', 'School Telephone', 'School District', 'School Province', 'School Type', 'HHH NRC No', 'HHH Name', 'First Name', 'HHH Last Name', 'Beneficiary District', 'Beneficiary Province', 'Beneficiary Status', 'Beneficiary School Status', 'Beneficiary Enrollment Status', 'Payment Verification Batch No', 'Year of Enrollment', 'Term', 'School Grade', 'Enrollment Status', 'Previous School Grade', 'Score', 'Class Average', 'Score', 'Class Average', 'Score', 'Class Average', 'Attendance (previous)', 'Attendance Rate<br/>(65%)', 'Has Signed(Signiture of Girl)', 'Remarks', 'School Fees', 'Has Been Validated', 'Payment Status', 'Payment Request Ref No', 'Total Amount Transfered', 'Transaction No', 'Date of Transaction,Importation batch no');

        return $label;

    }

    function getSpreadsheetvalues($rows)
    {
        $values = array($rows->beneficairy_no, $rows->first_name, $rows->last_name, $rows->dob, $rows->first_name, $rows->relation_to_hhh, $rows->current_school_grade,
            $rows->school_name, $rows->school_code, $rows->email_address, $rows->telephone_no, $rows->sch_district, $rows->sch_province, $rows->sch_type,
            $rows->hhh_nrc_number, $rows->hhh_fname, $rows->hhh_lname, $rows->hhh_lname, $rows->cwac_name, $rows->ben_district, $rows->ben_province,
            $rows->ben_category, $rows->ben_statuses, $rows->ben_school_statuses, $rows->ben_enrollment_statuses, $rows->enrollment_date, $rows->exam_number, $rows->is_letter_received,
            $rows->ben_disabilities
        );
        return $values;
    }

    function getTermlyenrolSpreadsheetvalues($rows)
    {
        $values = array($rows->beneficairy_no, $rows->first_name, $rows->last_name, $rows->dob, $rows->school_name, $rows->school_code, $rows->email_address, $rows->telephone_no, $rows->sch_district, $rows->sch_province, $rows->sch_type, $rows->hhh_nrc_number, $rows->hhh_fname, $rows->hhh_lname, $rows->hhh_lname, $rows->ben_district, $rows->ben_province, $rows->ben_statuses, $rows->ben_school_statuses, $rows->ben_enrollment_statuses, $rows->batch_no, $rows->year_of_enrollment, $rows->term_name, $rows->current_school_grade, $rows->ben_enrollment_statuses, $rows->previous_school_grade, $rows->english_score, $rows->engclass_average, $rows->mathematics_score, $rows->mathsclass_average, $rows->science_score, $rows->scienceclass_average, $rows->benficiary_attendance, $rows->attendance_rate, $rows->has_signed, $rows->remarks, $rows->school_fees, $rows->is_validated, $rows->payment_status, $rows->payment_request_ref, $rows->amount_transfered, $rows->transaction_no, $rows->transaction_date,$row->importation_batchno
        );
        return $values;
    }

    //KIP
    public function getImportationDataReports(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $province_filter = $req->input('province_filter');
            $district_filter = $req->input('district_filter');
            $qry = DB::table('beneficiary_master_info as t1')
                ->select(DB::raw('t1.sct_district,count(t1.id) as no_of_girls'));
            if (validateisNumeric($batch_id)) {
                $qry->where('t1.batch_id', $batch_id);
            }
            $qry->groupBy('t1.sct_district');
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


    public function getImportationDataReportsPerHomeDistrict(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $province_filter = $req->input('province_filter');
        $district_filter = $req->input('district_filter');
        try {
            $qry = DB::table('beneficiary_master_info as t1')
                ->select(DB::raw('t1.sct_district,count(t1.id) as no_of_girls,cwac'))
                ->where('t1.batch_id', $batch_id)
                ->groupBy('t1.cwac');
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

    public function getImportationDataReportsPerSchoolDistrict(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $province_filter = $req->input('province_filter');
        $district_filter = $req->input('district_filter');
        try {
            $qry = DB::table('beneficiary_master_info as t1')
                ->select(DB::raw('t1.district_name,count(t1.id) as no_of_girls,school_name'))
                ->where('t1.batch_id', $batch_id)
                ->groupBy('t1.school_name');
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

    public function getImportationBrief(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $qry = DB::table('beneficiary_master_info as t1')
                ->select(DB::raw('count(t1.id) as total_import,SUM(IF(t1.school_going LIKE "%yes%",1,0)) as school_going_yes,
                                  SUM(IF(t1.school_going LIKE "%no%",1,0)) as school_going_no'));
            if (validateisNumeric($batch_id)) {
                $qry->where('t1.batch_id', $batch_id);
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

    public function getAssessmentDataReports(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $province_filter = $req->input('province_filter');
            $district_filter = $req->input('district_filter');
            $qry = DB::table('beneficiary_master_info as t1')
                ->select(DB::raw('SUM(IF(t1.category = 1,1,0)) as out_of_school,
                                  SUM(IF(t1.category = 2,1,0)) as in_school,
                                  t1.sct_district'));
            if (validateisNumeric($batch_id)) {
                $qry->where('t1.batch_id', $batch_id);
            }
            $qry->groupBy('t1.sct_district');
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

    public function getAssessmentDataReportsPerHomeDistrict(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $province_filter = $req->input('province_filter');
        $district_filter = $req->input('district_filter');
        try {
            $qry = DB::table('beneficiary_master_info as t1')
                ->select(DB::raw('t1.sct_district,cwac,
                                  SUM(IF(t1.category = 1,1,0)) as out_of_school,
                                  SUM(IF(t1.category = 2,1,0)) as in_school,
                                  SUM(IF(t1.category = 3,1,0)) as exam_classes'))
                ->where('t1.batch_id', $batch_id)
                ->groupBy('t1.cwac');
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

    public function getAssessmentDataReportsPerSchoolDistrict(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $province_filter = $req->input('province_filter');
        $district_filter = $req->input('district_filter');
        try {
            $qry = DB::table('beneficiary_master_info as t1')
                ->select(DB::raw('t1.district_name,school_name,
                                  SUM(IF(t1.category = 1,1,0)) as out_of_school,
                                  SUM(IF(t1.category = 2,1,0)) as in_school,
                                  SUM(IF(t1.category = 3,1,0)) as exam_classes'))
                ->where('t1.batch_id', $batch_id)
                ->groupBy('t1.school_name');
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

    public function getAssessmentBrief(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $qry = DB::table('beneficiary_master_info as t1')
                ->select(DB::raw('t1.district_name,school_name,
                                  SUM(IF(t1.category = 1,1,0)) as out_of_school,
                                  SUM(IF(t1.category IN (2,3),1,0)) as in_school,
                                  SUM(IF(t1.category = 3,1,0)) as exam_classes'));
            if (validateisNumeric($batch_id)) {
                $qry->where('t1.batch_id', $batch_id);
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

    public function getMappingBrief(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $qry = DB::table('beneficiary_master_info as t1')
                ->select(DB::raw('SUM(IF(t1.category = 1,1,0)) as out_of_school,
                                  SUM(IF(t1.category IN (2,3),1,0)) as in_school,
                                  SUM(IF(t1.category = 3,1,0)) as exam_classes'))
                ->where('t1.is_mapped', 1);
            if (validateisNumeric($batch_id)) {
                $qry->where('t1.batch_id', $batch_id);
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

    public function getMappingDataReports(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $province_filter = $req->input('province_filter');
            $district_filter = $req->input('district_filter');
            $qry = DB::table('beneficiary_master_info as t1')
                ->select(DB::raw('SUM(IF(t1.category = 1,1,0)) as out_of_school,
                                  SUM(IF(t1.category = 2,1,0)) as in_school,
                                  SUM(IF(t1.category = 3,1,0)) as exam_classes,
                                  t1.sct_district'))
                ->where('t1.is_mapped', 1);
            if (validateisNumeric($batch_id)) {
                $qry->where('t1.batch_id', $batch_id);
            }
            $qry->groupBy('t1.sct_district');
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

    public function getMappingDataReportsPerHomeDistrict(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $province_filter = $req->input('province_filter');
        $district_filter = $req->input('district_filter');
        try {
            $qry = DB::table('beneficiary_master_info as t1')
                ->select(DB::raw('t1.sct_district,cwac,
                                  SUM(IF(t1.category = 1,1,0)) as out_of_school,
                                  SUM(IF(t1.category = 2,1,0)) as in_school,
                                  SUM(IF(t1.category = 3,1,0)) as exam_classes'))
                ->where('t1.is_mapped', 1)
                ->where('t1.batch_id', $batch_id)
                ->groupBy('t1.cwac');
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

    public function getMappingDataReportsPerSchoolDistrict(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $province_filter = $req->input('province_filter');
        $district_filter = $req->input('district_filter');
        try {
            $qry = DB::table('beneficiary_master_info as t1')
                ->select(DB::raw('t1.district_name,school_name,
                                  SUM(IF(t1.category = 1,1,0)) as out_of_school,
                                  SUM(IF(t1.category = 2,1,0)) as in_school,
                                  SUM(IF(t1.category = 3,1,0)) as exam_classes'))
                ->where('t1.is_mapped', 1)
                ->where('t1.batch_id', $batch_id)
                ->groupBy('t1.school_name');
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

    public function getVerificationBrief(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $qry = DB::table('beneficiary_information as t1')
                ->select(DB::raw('SUM(IF(t1.category = 1,1,0)) as out_of_school,
                                  SUM(IF(t1.category = 2,1,0)) as in_school,
                                  SUM(IF(t1.category = 3,1,0)) as exam_classes'));
            if (validateisNumeric($batch_id)) {
                $qry->where('t1.batch_id', $batch_id);
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

    public function getVerificationDetailed(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $qry = DB::table('beneficiary_information as t1')
                ->select(DB::raw('SUM(IF(t1.category = 1 AND t1.beneficiary_status >= 2 AND t1.verification_recommendation=1,1,0)) as out_of_school_eligible,
                                  SUM(IF(t1.category = 1 AND t1.beneficiary_status >= 2 AND t1.verification_recommendation!=1,1,0)) as out_of_school_not_eligible,
                                  SUM(IF(t1.category = 1 AND (t1.beneficiary_status < 2 OR t1.beneficiary_status IS NULL),1,0)) as out_of_school_unverified,
                                  SUM(IF(t1.category IN (2,3) AND t1.beneficiary_status >= 2 AND t1.verification_recommendation=1,1,0)) as in_school_eligible,
                                  SUM(IF(t1.category IN (2,3) AND t1.beneficiary_status >= 2 AND t1.verification_recommendation!=1,1,0)) as in_school_not_eligible,
                                  SUM(IF(t1.category IN (2,3) AND (t1.beneficiary_status < 2 OR t1.beneficiary_status IS NULL),1,0)) as in_school_unverified,
                                  SUM(IF(t1.category = 3 AND t1.beneficiary_status >= 2 AND t1.verification_recommendation=1,1,0)) as exam_classes_eligible,
                                  SUM(IF(t1.category = 3 AND t1.beneficiary_status >= 2 AND t1.verification_recommendation!=1,1,0)) as exam_classes_not_eligible,
                                  SUM(IF(t1.category = 3 AND (t1.beneficiary_status < 2 OR t1.beneficiary_status IS NULL),1,0)) as exam_classes_unverified'));
            if (validateisNumeric($batch_id)) {
                $qry->where('t1.batch_id', $batch_id);
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

    public function getVerInSchoolDataReportsPerHomeDistrict(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $qry = DB::table('beneficiary_information as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->select(DB::raw('t2.name as district_name,
                                  SUM(IF(t1.category IN (2,3) AND t1.beneficiary_status >= 2 AND t1.verification_recommendation=1,1,0)) as in_school_eligible,
                                  SUM(IF(t1.category IN (2,3) AND t1.beneficiary_status >= 2 AND t1.verification_recommendation!=1,1,0)) as in_school_not_eligible,
                                  SUM(IF(t1.category IN (2,3) AND (t1.beneficiary_status < 2 OR t1.beneficiary_status IS NULL),1,0)) as in_school_unverified'));
            if (validateisNumeric($batch_id)) {
                $qry->where('t1.batch_id', $batch_id);
            }
            $qry->groupBy('t1.district_id');
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

    public function getVerOutSchoolDataReportsPerHomeDistrict(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $qry = DB::table('beneficiary_information as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->select(DB::raw('t2.name as district_name,
                                  SUM(IF(t1.category = 1 AND t1.beneficiary_status >= 2 AND t1.verification_recommendation=1,1,0)) as out_of_school_eligible,
                                  SUM(IF(t1.category = 1 AND t1.beneficiary_status >= 2 AND t1.verification_recommendation!=1,1,0)) as out_of_school_not_eligible,
                                  SUM(IF(t1.category = 1 AND (t1.beneficiary_status < 2 OR t1.beneficiary_status IS NULL),1,0)) as out_of_school_unverified'));
            if (validateisNumeric($batch_id)) {
                $qry->where('t1.batch_id', $batch_id);
            }
            $qry->groupBy('t1.district_id');
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

    public function getVerExamClassesDataReportsPerHomeDistrict(Request $req)
    {
        $batch_id = $req->input('batch_id');
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->select(DB::raw('t2.name as district_name,
                                  SUM(IF(t1.category = 3 AND t1.beneficiary_status >= 2 AND t1.verification_recommendation=1,1,0)) as exam_classes_eligible,
                                  SUM(IF(t1.category = 3 AND t1.beneficiary_status >= 2 AND t1.verification_recommendation!=1,1,0)) as exam_classes_not_eligible,
                                  SUM(IF(t1.category = 3 AND (t1.beneficiary_status < 2 OR t1.beneficiary_status IS NULL),1,0)) as exam_classes_unverified'))
                ->where('t1.batch_id', $batch_id)
                ->groupBy('t1.district_id');
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

    public function getVerInSchoolPerHomeDistrict(Request $req)
    {
        $batch_id = $req->input('batch_id');
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->join('cwac as t3', 't1.cwac_id', '=', 't3.id')
                ->select(DB::raw('t2.name as district_name, t3.name as cwac_name, t1.district_id,
                                  SUM(IF(t1.category = 2 AND t1.beneficiary_status >= 2 AND t1.verification_recommendation=1,1,0)) as eligible,
                                  SUM(IF(t1.category = 2 AND t1.beneficiary_status >= 2 AND t1.verification_recommendation!=1,1,0)) as not_eligible,
                                  SUM(IF(t1.category = 2 AND (t1.beneficiary_status < 2 OR t1.beneficiary_status IS NULL),1,0)) as unverified'))
                ->where('t1.batch_id', $batch_id)
                ->groupBy('t1.cwac_id');
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

    public function getVerOutSchoolPerHomeDistrict(Request $req)
    {
        $batch_id = $req->input('batch_id');
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->join('cwac as t3', 't1.cwac_id', '=', 't3.id')
                ->select(DB::raw('t2.name as district_name, t3.name as cwac_name, t1.district_id,
                                  SUM(IF(t1.category = 1 AND t1.beneficiary_status >= 2 AND t1.verification_recommendation=1,1,0)) as eligible,
                                  SUM(IF(t1.category = 1 AND t1.beneficiary_status >= 2 AND t1.verification_recommendation!=1,1,0)) as not_eligible,
                                  SUM(IF(t1.category = 1 AND (t1.beneficiary_status < 2 OR t1.beneficiary_status IS NULL),1,0)) as unverified'))
                ->where('t1.batch_id', $batch_id)
                ->groupBy('t1.cwac_id');
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

    public function getVerExamClassesPerHomeDistrict(Request $req)
    {
        $batch_id = $req->input('batch_id');
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->join('cwac as t3', 't1.cwac_id', '=', 't3.id')
                ->select(DB::raw('t2.name as district_name, t3.name as cwac_name, t1.district_id,
                                  SUM(IF(t1.category = 3 AND t1.beneficiary_status >= 2 AND t1.verification_recommendation=1,1,0)) as eligible,
                                  SUM(IF(t1.category = 3 AND t1.beneficiary_status >= 2 AND t1.verification_recommendation!=1,1,0)) as not_eligible,
                                  SUM(IF(t1.category = 3 AND (t1.beneficiary_status < 2 OR t1.beneficiary_status IS NULL),1,0)) as unverified'))
                ->where('t1.batch_id', $batch_id)
                ->groupBy('t1.cwac_id');
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

    public function getVerExamClassesPerSchoolDistrict(Request $req)
    {
        $batch_id = $req->input('batch_id');
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                ->join('districts as t3', 't2.district_id', '=', 't3.id')
                ->select(DB::raw('t2.name as school_name, t3.name as district_name, t2.district_id,
                                  SUM(IF(t1.category = 3 AND t1.beneficiary_status >= 2 AND t1.verification_recommendation=1,1,0)) as eligible,
                                  SUM(IF(t1.category = 3 AND t1.beneficiary_status >= 2 AND t1.verification_recommendation!=1,1,0)) as not_eligible,
                                  SUM(IF(t1.category = 3 AND (t1.beneficiary_status < 2 OR t1.beneficiary_status IS NULL),1,0)) as unverified'))
                ->where('t1.batch_id', $batch_id)
                ->groupBy('t1.school_id');
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

    public function getVerInSchoolPerSchoolDistrict(Request $req)
    {
        $batch_id = $req->input('batch_id');
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                ->join('districts as t3', 't2.district_id', '=', 't3.id')
                ->select(DB::raw('t2.name as school_name, t3.name as district_name, t2.district_id,
                                  SUM(IF(t1.category = 2 AND t1.beneficiary_status >= 2 AND t1.verification_recommendation=1,1,0)) as eligible,
                                  SUM(IF(t1.category = 2 AND t1.beneficiary_status >= 2 AND t1.verification_recommendation!=1,1,0)) as not_eligible,
                                  SUM(IF(t1.category = 2 AND (t1.beneficiary_status < 2 OR t1.beneficiary_status IS NULL),1,0)) as unverified'))
                ->where('t1.batch_id', $batch_id)
                ->groupBy('t1.school_id');
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

    public function getSchoolMatchingBrief(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $qry = DB::table('beneficiary_information as t1')
                ->select(DB::raw('SUM(IF(t1.category = 1 AND t1.beneficiary_status >= 2 AND t1.verification_recommendation=1 AND school_matching_status =1,1,0)) as passed_matching,
                                  SUM(IF(t1.category = 1 AND t1.beneficiary_status >= 2 AND t1.verification_recommendation=1 AND school_matching_status =2,1,0)) as failed_matching,
                                  SUM(IF(t1.category = 1 AND t1.beneficiary_status >= 2 AND t1.verification_recommendation=1,1,0)) as total'));
            if (validateisNumeric($batch_id)) {
                $qry->where('t1.batch_id', $batch_id);
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

    public function getSchoolMatchingChartData(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $qry = DB::table('beneficiary_information as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->select(DB::raw('t2.name as district_name, t1.district_id,
                                  SUM(IF(t1.category = 1 AND t1.beneficiary_status >= 2 AND t1.verification_recommendation=1 AND school_matching_status =1,1,0)) as passed_matching,
                                  SUM(IF(t1.category = 1 AND t1.beneficiary_status >= 2 AND t1.verification_recommendation=1 AND school_matching_status =2,1,0)) as failed_matching,
                                  SUM(IF(t1.category = 1 AND t1.beneficiary_status >= 2 AND t1.verification_recommendation=1 AND (school_matching_status NOT IN (1,2) OR school_matching_status IS NULL) ,1,0)) as unmatched'));
            if (validateisNumeric($batch_id)) {
                $qry->where('t1.batch_id', $batch_id);
            }
            $qry->groupBy('t1.district_id');
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

    public function getSchoolMatchingDistrictDistribution(Request $req)
    {
        $batch_id = $req->input('batch_id');
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->join('school_matching_details as t2', 't1.id', '=', 't2.girl_id')
                ->join('school_information as t3', 't2.school_id', '=', 't3.id')
                ->join('districts as t4', 't3.district_id', '=', 't4.id')
                ->join('districts as t5', 't1.district_id', '=', 't5.id')
                ->select(DB::raw('t1.district_id,t5.name as home_district, t4.name as school_district, COUNT(t1.id) as no_of_girls'))
                ->where('t1.batch_id', $batch_id)
                ->where('t1.school_matching_status', 1)
                ->groupBy('t1.district_id')
                ->groupBy('t3.district_id');
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

    public function getSchoolMatchingSchoolDistribution(Request $req)
    {
        $batch_id = $req->input('batch_id');
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->join('school_matching_details as t2', 't1.id', '=', 't2.girl_id')
                ->join('school_information as t3', 't2.school_id', '=', 't3.id')
                ->join('districts as t4', 't3.district_id', '=', 't4.id')
                ->select(DB::raw('t3.district_id, t3.name as school_name, t4.name as school_district, COUNT(t1.id) as no_of_girls'))
                ->where('t1.batch_id', $batch_id)
                ->where('t1.school_matching_status', 1)
                ->groupBy('t2.school_id');
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

    public function getSchoolPlacementBrief(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $qry = DB::table('beneficiary_information as t1')
                ->select(DB::raw('SUM(IF(t1.category = 3 AND t1.beneficiary_status >= 2 AND t1.verification_recommendation=1 AND school_placement_status =1,1,0)) as passed_exams,
                                  SUM(IF(t1.category = 3 AND t1.beneficiary_status >= 2 AND t1.verification_recommendation=1 AND school_placement_status =2,1,0)) as failed_exams,
                                  SUM(IF(t1.category = 3 AND t1.beneficiary_status >= 2 AND t1.verification_recommendation=1,1,0)) as total'));
            if (validateisNumeric($batch_id)) {
                $qry->where('t1.batch_id', $batch_id);
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

    public function getSchoolPlacementChartData(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $qry = DB::table('beneficiary_information as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->select(DB::raw('t2.name as district_name, t1.district_id,
                                  SUM(IF(t1.category = 3 AND t1.beneficiary_status >= 2 AND t1.verification_recommendation=1 AND school_placement_status =1,1,0)) as passed_exams,
                                  SUM(IF(t1.category = 3 AND t1.beneficiary_status >= 2 AND t1.verification_recommendation=1 AND school_placement_status =2,1,0)) as failed_exams,
                                  SUM(IF(t1.category = 3 AND t1.beneficiary_status >= 2 AND t1.verification_recommendation=1 AND (school_placement_status NOT IN (1,2) OR school_placement_status IS NULL) ,1,0)) as unplaced'));
            if (validateisNumeric($batch_id)) {
                $qry->where('t1.batch_id', $batch_id);
            }
            $qry->groupBy('t1.district_id');
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

    public function getSchoolPlacementDistrictDistribution(Request $req)
    {
        $batch_id = $req->input('batch_id');
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->join('school_placement_details as t2', 't1.id', '=', 't2.girl_id')
                ->join('school_information as t3', 't2.school_id', '=', 't3.id')
                ->join('districts as t4', 't3.district_id', '=', 't4.id')
                ->join('school_information as t5', 't1.exam_school_id', '=', 't5.id')
                ->join('districts as t6', 't5.district_id', '=', 't6.id')
                ->select(DB::raw('t1.district_id,t6.name as exam_district, t4.name as assigned_district, COUNT(t1.id) as no_of_girls'))
                ->where('t1.batch_id', $batch_id)
                ->where('t1.school_placement_status', 1)
                ->groupBy('t4.id')
                ->groupBy('t6.id');
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

    public function getSchoolPlacementSchoolDistribution(Request $req)
    {
        $batch_id = $req->input('batch_id');
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->join('school_placement_details as t2', 't1.id', '=', 't2.girl_id')
                ->join('school_information as t3', 't2.school_id', '=', 't3.id')
                ->join('districts as t4', 't3.district_id', '=', 't4.id')
                ->join('school_information as t5', 't1.exam_school_id', '=', 't5.id')
                ->join('districts as t6', 't5.district_id', '=', 't6.id')
                ->select(DB::raw('t3.name as assigned_school, t5.name as exam_school, t6.id as exam_district_id, t6.name as exam_district, t4.id as assigned_district_id, t4.name as assigned_district, COUNT(t1.id) as no_of_girls'))
                ->where('t1.batch_id', $batch_id)
                ->where('t1.school_placement_status', 1)
                ->groupBy('t3.id')
                ->groupBy('t5.id');
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

    public function getSchPlacementDistributionBrief(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $qry = DB::table('beneficiary_information as t1')
                ->join('school_placement_details as t2', 't1.id', '=', 't2.girl_id')
                ->join('school_information as t3', 't2.school_id', '=', 't3.id')
                ->join('school_information as t5', 't1.exam_school_id', '=', 't5.id')
                ->select(DB::raw('SUM(IF(t3.district_id = t5.district_id,1,0)) as same_district,
                                  SUM(IF(t3.district_id != t5.district_id,1,0)) as different_district,
                                  SUM(IF(t1.exam_school_id = t2.school_id,1,0)) as same_school,
                                  SUM(IF(t1.exam_school_id != t2.school_id,1,0)) as different_school'))
                ->where('t1.school_placement_status', 1);
            if (validateisNumeric($batch_id)) {
                $qry->where('t1.batch_id', $batch_id);
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

    public function getSchoolMatchingDistributionBrief(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $qry = DB::table('beneficiary_information as t1')
                ->join('school_matching_details as t2', 't1.id', '=', 't2.girl_id')
                ->join('school_information as t3', 't2.school_id', '=', 't3.id')
                ->select(DB::raw('SUM(IF(t1.district_id = t3.district_id,1,0)) as same_district,
                                  SUM(IF(t1.district_id != t3.district_id,1,0)) as different_district'))
                ->where('t1.school_matching_status', 1);
            if (validateisNumeric($batch_id)) {
                $qry->where('t1.batch_id', $batch_id);
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

    //Beneficiary statuses
    public function getBeneficiariesPerBenStatus(Request $req)
    {
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->select(DB::raw('t2.name as district_name,
                                  SUM(IF(t1.beneficiary_status = 1,1,0)) as identified,
                                  SUM(IF(t1.beneficiary_status = 2,1,0)) as verified,
                                  SUM(IF(t1.beneficiary_status = 3,1,0)) as analysis,
                                  SUM(IF(t1.beneficiary_status = 4,1,0)) as enrolled,
                                  SUM(IF(t1.beneficiary_status = 5,1,0)) as matching,
                                  SUM(IF(t1.beneficiary_status = 6,1,0)) as followup,
                                  SUM(IF(t1.beneficiary_status = 8,1,0)) as placement'))
                ->groupBy('t1.district_id');
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

    public function getBenStatusesBrief(Request $req)
    {
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->select(DB::raw('SUM(IF(t1.beneficiary_status = 1,1,0)) as identified,
                                  SUM(IF(t1.beneficiary_status = 2,1,0)) as verified,
                                  SUM(IF(t1.beneficiary_status = 3,1,0)) as analysis,
                                  SUM(IF(t1.beneficiary_status = 4,1,0)) as enrolled,
                                  SUM(IF(t1.beneficiary_status = 5,1,0)) as matching,
                                  SUM(IF(t1.beneficiary_status = 6,1,0)) as followup,
                                  SUM(IF(t1.beneficiary_status = 8,1,0)) as placement'));
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

    public function getBenStatusesDataReportsPerHomeDistrict(Request $req)
    {
        $province_filter = $req->input('province_filter');
        $district_filter = $req->input('district_filter');
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->join('cwac as t3', 't1.cwac_id', '=', 't3.id')
                ->select(DB::raw('t1.district_id,t2.name as district_name,t3.name as cwac_name,
                                  SUM(IF(t1.beneficiary_status = 1,1,0)) as identified,
                                  SUM(IF(t1.beneficiary_status = 2,1,0)) as verified,
                                  SUM(IF(t1.beneficiary_status = 3,1,0)) as analysis,
                                  SUM(IF(t1.beneficiary_status = 4,1,0)) as enrolled,
                                  SUM(IF(t1.beneficiary_status = 5,1,0)) as matching,
                                  SUM(IF(t1.beneficiary_status = 6,1,0)) as followup,
                                  SUM(IF(t1.beneficiary_status = 8,1,0)) as placement'))
                ->groupBy('t1.cwac_id');
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

    public function getBenStatusesDataReportsPerSchoolDistrict(Request $req)
    {
        $province_filter = $req->input('province_filter');
        $district_filter = $req->input('district_filter');
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                ->join('districts as t3', 't2.district_id', '=', 't3.id')
                ->select(DB::raw('t2.district_id,t2.name as school_name,t3.name as district_name,
                                  SUM(IF(t1.beneficiary_status = 1,1,0)) as identified,
                                  SUM(IF(t1.beneficiary_status = 2,1,0)) as verified,
                                  SUM(IF(t1.beneficiary_status = 3,1,0)) as analysis,
                                  SUM(IF(t1.beneficiary_status = 4,1,0)) as enrolled,
                                  SUM(IF(t1.beneficiary_status = 5,1,0)) as matching,
                                  SUM(IF(t1.beneficiary_status = 6,1,0)) as followup,
                                  SUM(IF(t1.beneficiary_status = 8,1,0)) as placement'))
                ->groupBy('t1.school_id');
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

    public function getBenEnrollmentStatusesPerSchoolDistrict(Request $req)
    {
        $province_filter = $req->input('province_filter');
        $district_filter = $req->input('district_filter');
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                ->join('districts as t3', 't2.district_id', '=', 't3.id')
                ->select(DB::raw('t2.district_id,t2.name as school_name,t3.name as district_name,
                                  SUM(IF(t1.enrollment_status = 1,1,0)) as active,
                                  SUM(IF(t1.enrollment_status = 4,1,0)) as completed,
                                  SUM(IF(t1.enrollment_status = 5,1,0)) as pending,
                                  SUM(IF(t1.enrollment_status = 2,1,0)) as suspended'))
                ->groupBy('t1.school_id');
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

    public function getAllBeneficiaryStatuses(Request $req)
    {
        try {
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
                                $whereClauses[] = "t2.beneficiary_id like '%" . ($filter->value) . "%'";
                                break;
                            case 'full_name' :
                                $whereClauses[] = "CONCAT_WS(' ',t1.girl_fname,t1.girl_lname) like '%" . ($filter->value) . "%' OR CONCAT_WS(' ',decrypt(t2.first_name),decrypt(t2.last_name)) like '%" . ($filter->value) . "%'";
                                break;
                            case 'ben_status' :
                                $whereClauses[] = "t2.beneficiary_status = '" . ($filter->value) . "'";
                                break;
                            case 'enroll_status' :
                                $whereClauses[] = "t2.enrollment_status = '" . ($filter->value) . "'";
                                break;
                            case 'school_name' :
                                $whereClauses[] = "t2.school_id = '" . ($filter->value) . "'";
                                break;
                            case 'batch_no' :
                                $whereClauses[] = "t1.batch_id = '" . ($filter->value) . "'";
                                break;
                        }
                    }
                    $whereClauses = array_filter($whereClauses);
                }
                if (!empty($whereClauses)) {
                    $filter_string = implode(' AND ', $whereClauses);
                }
            }

            $qry = DB::table('beneficiary_master_info as t1')
                ->leftJoin('beneficiary_information as t2', 't1.id', '=', 't2.master_id')
                ->leftJoin('beneficiary_statuses as t3', 't2.beneficiary_status', '=', 't3.id')
                ->leftJoin('beneficiary_enrollement_statuses as t4', 't2.enrollment_status', '=', 't4.id')
                ->leftJoin('beneficiary_categories as t5', 't2.category', '=', 't5.id')
                ->leftJoin('school_information as t6', 't2.school_id', '=', 't6.id')
                ->join('batch_info as t7', 't1.batch_id', '=', 't7.id')
                ->select(DB::raw("t1.id,t2.beneficiary_id,t3.name as ben_status,t4.name as enroll_status,t6.name as school_name,t7.batch_no,t2.under_promotion,t2.school_enrollment_status,
                                  t5.name as category_name,CONCAT_WS(' ',CASE WHEN t2.id IS NULL THEN t1.girl_fname ELSE decrypt(t2.first_name) END,CASE WHEN t2.id IS NULL THEN t1.girl_lname ELSE decrypt(t2.last_name) END) as full_name"));
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
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getBeneficiariesResponseRate(Request $req)
    {
        try {
            $group_fieldid = $req->input('group_field');
            $term_id = $req->input('term_id');
            $year_of_enrollment = $req->input('year_of_enrollment');
            $request_batch_id = $req->input('request_batch_id');
            $enrollment_batch_id = $req->input('enrollment_batch_id');
            $where_term = array();
            $where_year = array();
            $enrollment_batch = array();
            if (validateisNumeric($term_id)) {
                $where_term = array('t5.term_id' => $term_id);
            }
            if (validateisNumeric($year_of_enrollment)) {
                $where_year = array('t5.year_of_enrollment' => $year_of_enrollment);
            }
            if (validateisNumeric($enrollment_batch_id)) {
                $enrollment_batch = array('t1.batch_id' => $enrollment_batch_id);
            }
            /*1. Home Province 11
            2. Home District 22
            3. Constituency 33
            4. Ward 44
            5. CWAC 55
            6. School District 66
            7. School 77
            8. Enrollment grades 10
            9. Current grades 10*/
            $where_data = array_merge($where_term, $where_year, $enrollment_batch);
            $qry = DB::table('beneficiary_information as t1');
            $qry->select(DB::raw('t22.name as district_name,
                    count((t5.id)) as verified_beneficiaries,
                    SUM(IF(t5.is_validated=1,1,0)) as validated_beneficiaries,
                    SUM(IF(t5.passed_rules=1,1,0)) as passed_rules_girls,
                    SUM(CASE WHEN t5.passed_rules = 1 THEN decrypt(t5.annual_fees) ELSE 0 END) AS rule_total_fees,
                    SUM(CASE WHEN t5.is_validated = 1 THEN decrypt(t5.annual_fees) ELSE 0 END) AS school_fees,
                    SUM(CASE WHEN t55.id IS NOT NULL THEN decrypt(t5.annual_fees) ELSE 0 END) AS total_fees,
                    count(t55.id) as added_for_payments,
                    t' . $group_fieldid . '.name as group_fieldvalue,t5.year_of_enrollment'))
                ->join('beneficiary_enrollments as t5', 't1.id', '=', 't5.beneficiary_id')
                ->join('districts as t22', 't1.district_id', '=', 't22.id');
            if ($group_fieldid == 10) {
                $qry->join('school_grades as t10', 't5.school_grade', '=', 't10.id');
            } else if ($group_fieldid == 12) {
                $qry->join('school_grades as t12', 't1.current_school_grade', '=', 't12.id');
            } else if ($group_fieldid == 11) {
                $qry->join('provinces as t11', 't1.province_id', '=', 't11.id');
            } else if ($group_fieldid == 33) {
                $qry->join('constituencies as t33', 't1.constituency_id', '=', 't33.id');
            } else if ($group_fieldid == 44) {
                $qry->join('wards as t44', 't1.ward_id', '=', 't44.id');
            } else if ($group_fieldid == 55) {
                $qry->join('cwacs as t55', 't1.cwac_id', '=', 't55.id');
            } else if ($group_fieldid == 66) {
                $qry->join('school_information as t77', 't1.school_id', '=', 't77.id')
                    ->join('districts as t66', 't77.district_id', '=', 't66.id');
            } else if ($group_fieldid == 77) {
                $qry->join('school_information as t77', 't5.school_id', '=', 't77.id')
                    ->join('districts as d2', 't77.district_id', '=', 'd2.id');
            } else if ($group_fieldid == 88) {
                $qry->join('beneficiary_school_statuses as t88', 't5.beneficiary_schoolstatus_id', '=', 't88.id');
            } else if ($group_fieldid == 99) {
                $qry->join('beneficiary_categories as t99', 't1.category', '=', 't99.id');
            } else if ($group_fieldid == 10) {
                $qry->join('school_grades as t10', 't5.school_grade', '=', 't10.id');
            }
            if (isset($request_batch_id) && $request_batch_id != '') {
                $qry->leftJoin('beneficiary_payment_records as t55', function ($join) use ($request_batch_id) {
                    $join->on('t5.id', '=', 't55.enrollment_id')
                        ->on('t55.payment_request_id', '=', DB::raw($request_batch_id));
                });
            } else {
                $qry->leftJoin('beneficiary_payment_records as t55', 't5.id', '=', 't55.enrollment_id');
            }
            if ($group_fieldid == 77) {
                $qry->addSelect('d2.name as sch_district');
            }
            $qry->groupBy('t' . $group_fieldid . '.id')
                ->where($where_data);
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

    public function getBeneficiariesMoreResponseRate(Request $req)
    {
        try {
            $group_fieldid = $req->input('group_field');
            $term_id = $req->input('term_id');
            $year_of_enrollment = $req->input('year_of_enrollment');
            $request_batch_id = $req->input('request_batch_id');
            $enrollment_batch_id = $req->input('enrollment_batch_id');
            $where_term = array();
            $where_year = array();
            $enrollment_batch = array();
            if (validateisNumeric($term_id)) {
                $where_term = array('t5.term_id' => $term_id);
            }
            if (validateisNumeric($year_of_enrollment)) {
                $where_year = array('t5.year_of_enrollment' => $year_of_enrollment);
            }
            if (validateisNumeric($enrollment_batch_id)) {
                $enrollment_batch = array('t1.batch_id' => $enrollment_batch_id);
            }
            /*1. Home Province 11
              2. Home District 22
              3. Constituency 33
              4. Ward 44
              5. CWAC 55
              6. School District 66
              7. School 77
              8. Enrollment grades 10
              9. Current grades 12*/
            $where_data = array_merge($where_term, $where_year, $enrollment_batch);
            $qry = DB::table('beneficiary_information as t1');
            $qry->select(DB::raw('t22.name as district_name,
                    SUM(IF(t1.category=1,1,0)) as verified_beneficiaries_out,
                    SUM(IF(t1.category in (2,3),1,0)) as verified_beneficiaries_in,
                    SUM(IF(t1.category=3,1,0)) as verified_beneficiaries_exam,
                    SUM(IF(t5.is_validated=1 AND t1.category=1,1,0)) as validated_beneficiaries_out,
                    SUM(IF(t5.is_validated=1 AND t1.category in (2,3),1,0)) as validated_beneficiaries_in,
                    SUM(IF(t5.is_validated=1 AND t1.category=3,1,0)) as validated_beneficiaries_exam,
                    SUM(IF(t5.passed_rules=1 AND t1.category=1,1,0)) as passed_rules_girls_out,
                    SUM(IF(t5.passed_rules=1 AND t1.category in (2,3),1,0)) as passed_rules_girls_in,
                    SUM(IF(t5.passed_rules=1 AND t1.category=3,1,0)) as passed_rules_girls_exam,
                    SUM(IF(t1.category=1 AND t55.id IS NOT NULL,1,0)) as added_for_payments_out,
                    SUM(IF(t1.category in (2,3) AND t55.id IS NOT NULL,1,0)) as added_for_payments_in,
                    SUM(IF(t1.category=3 AND t55.id IS NOT NULL,1,0)) as added_for_payments_exam,
                    t' . $group_fieldid . '.name as group_fieldvalue,t5.year_of_enrollment'))
                ->join('beneficiary_enrollments as t5', 't1.id', '=', 't5.beneficiary_id')
                ->join('districts as t22', 't1.district_id', '=', 't22.id');
            if ($group_fieldid == 10) {
                $qry->join('school_grades as t10', 't5.school_grade', '=', 't10.id');
            } else if ($group_fieldid == 12) {
                $qry->join('school_grades as t12', 't1.current_school_grade', '=', 't12.id');
            } else if ($group_fieldid == 11) {
                $qry->join('provinces as t11', 't1.province_id', '=', 't11.id');
            } else if ($group_fieldid == 33) {
                $qry->join('constituencies as t33', 't1.constituency_id', '=', 't33.id');
            } else if ($group_fieldid == 44) {
                $qry->join('wards as t44', 't1.ward_id', '=', 't44.id');
            } else if ($group_fieldid == 55) {
                $qry->join('cwacs as t55', 't1.cwac_id', '=', 't55.id');
            } else if ($group_fieldid == 66) {
                $qry->join('school_information as t77', 't1.school_id', '=', 't77.id')
                    ->join('districts as t66', 't77.district_id', '=', 't66.id');
            } else if ($group_fieldid == 77) {
                $qry->join('school_information as t77', 't5.school_id', '=', 't77.id');
            } else if ($group_fieldid == 88) {
                $qry->join('beneficiary_school_statuses as t88', 't5.beneficiary_schoolstatus_id', '=', 't88.id');
            } else if ($group_fieldid == 99) {
                $qry->join('beneficiary_categories as t99', 't1.category', '=', 't99.id');
            }
            if (isset($request_batch_id) && $request_batch_id != '') {
                $qry->leftJoin('beneficiary_payment_records as t55', function ($join) use ($request_batch_id) {
                    $join->on('t5.id', '=', 't55.enrollment_id')
                        ->on('t55.payment_request_id', '=', DB::raw($request_batch_id));
                });
            } else {
                $qry->leftJoin('beneficiary_payment_records as t55', 't5.id', '=', 't55.enrollment_id');
            }
            //$qry->leftJoin('beneficiary_payment_records as t55', 't5.id', '=', 't55.enrollment_id')
            $qry->groupBy('t' . $group_fieldid . '.id')
                ->where($where_data);
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

    public function getBeneficiariesMoreSchStatusesResponseRate(Request $req)
    {
        try {
            $group_fieldid = $req->input('group_field');
            $term_id = $req->input('term_id');
            $year_of_enrollment = $req->input('year_of_enrollment');
            $request_batch_id = $req->input('request_batch_id');
            $enrollment_batch_id = $req->input('enrollment_batch_id');
            $where_term = array();
            $where_year = array();
            $enrollment_batch = array();
            if (validateisNumeric($term_id)) {
                $where_term = array('t5.term_id' => $term_id);
            }
            if (validateisNumeric($year_of_enrollment)) {
                $where_year = array('t5.year_of_enrollment' => $year_of_enrollment);
            }
            if (validateisNumeric($enrollment_batch_id)) {
                $enrollment_batch = array('t1.batch_id' => $enrollment_batch_id);
            }
            /*1. Home Province 11
            2. Home District 22
            3. Constituency 33
            4. Ward 44
            5. CWAC 55
            6. School District 66
            7. School 77
            8. Enrollment grades 10
            9. Current grades 12*/
            $where_data = array_merge($where_term, $where_year, $enrollment_batch);
            $qry = DB::table('beneficiary_information as t1');
            $qry->select(DB::raw('t22.name as district_name,
                    SUM(IF(t5.beneficiary_schoolstatus_id=1,1,0)) as verified_beneficiaries_day,
                    SUM(IF(t5.beneficiary_schoolstatus_id=2,1,0)) as verified_beneficiaries_border,
                    SUM(IF(t5.beneficiary_schoolstatus_id=3,1,0)) as verified_beneficiaries_wborder,
                    SUM(IF(t5.beneficiary_schoolstatus_id=4,1,0)) as verified_beneficiaries_unspecified,
                    SUM(IF(t5.is_validated=1 AND t5.beneficiary_schoolstatus_id=1,1,0)) as validated_beneficiaries_day,
                    SUM(IF(t5.is_validated=1 AND t5.beneficiary_schoolstatus_id=2,1,0)) as validated_beneficiaries_border,
                    SUM(IF(t5.is_validated=1 AND t5.beneficiary_schoolstatus_id=3,1,0)) as validated_beneficiaries_wborder,
                    SUM(IF(t5.is_validated=1 AND t5.beneficiary_schoolstatus_id=4,1,0)) as validated_beneficiaries_unspecified,
                    SUM(IF(t5.passed_rules=1 AND t5.beneficiary_schoolstatus_id=1,1,0)) as passed_rules_girls_day,
                    SUM(IF(t5.passed_rules=1 AND t5.beneficiary_schoolstatus_id=2,1,0)) as passed_rules_girls_border,
                    SUM(IF(t5.passed_rules=1 AND t5.beneficiary_schoolstatus_id=3,1,0)) as passed_rules_girls_wborder,
                    SUM(IF(t5.passed_rules=1 AND t5.beneficiary_schoolstatus_id=4,1,0)) as passed_rules_girls_unspecified,
                    SUM(IF(t5.beneficiary_schoolstatus_id=1 AND t55.id IS NOT NULL,1,0)) as added_for_payments_day,
                    SUM(IF(t5.beneficiary_schoolstatus_id=2 AND t55.id IS NOT NULL,1,0)) as added_for_payments_border,
                    SUM(IF(t5.beneficiary_schoolstatus_id=3 AND t55.id IS NOT NULL,1,0)) as added_for_payments_wborder,
                    SUM(IF(t5.beneficiary_schoolstatus_id=4 AND t55.id IS NOT NULL,1,0)) as added_for_payments_unspecified,
                    t' . $group_fieldid . '.name as group_fieldvalue,t5.year_of_enrollment'))
                ->join('beneficiary_enrollments as t5', 't1.id', '=', 't5.beneficiary_id')
                ->join('districts as t22', 't1.district_id', '=', 't22.id');
            if ($group_fieldid == 10) {
                $qry->join('school_grades as t10', 't5.school_grade', '=', 't10.id');
            } else if ($group_fieldid == 12) {
                $qry->join('school_grades as t12', 't1.current_school_grade', '=', 't12.id');
            } else if ($group_fieldid == 11) {
                $qry->join('provinces as t11', 't1.province_id', '=', 't11.id');
            } else if ($group_fieldid == 33) {
                $qry->join('constituencies as t33', 't1.constituency_id', '=', 't33.id');
            } else if ($group_fieldid == 44) {
                $qry->join('wards as t44', 't1.ward_id', '=', 't44.id');
            } else if ($group_fieldid == 55) {
                $qry->join('cwacs as t55', 't1.cwac_id', '=', 't55.id');
            } else if ($group_fieldid == 66) {
                $qry->join('school_information as t77', 't1.school_id', '=', 't77.id')
                    ->join('districts as t66', 't77.district_id', '=', 't66.id');
            } else if ($group_fieldid == 77) {
                $qry->join('school_information as t77', 't5.school_id', '=', 't77.id');
            } else if ($group_fieldid == 88) {
                $qry->join('beneficiary_school_statuses as t88', 't5.beneficiary_schoolstatus_id', '=', 't88.id');
            } else if ($group_fieldid == 99) {
                $qry->join('beneficiary_categories as t99', 't1.category', '=', 't99.id');
            }
            if (validateisNumeric($request_batch_id)) {
                $qry->leftJoin('beneficiary_payment_records as t55', function ($join) use ($request_batch_id) {
                    $join->on('t5.id', '=', 't55.enrollment_id')
                        ->on('t55.payment_request_id', '=', DB::raw($request_batch_id));
                });
            } else {
                $qry->leftJoin('beneficiary_payment_records as t55', 't5.id', '=', 't55.enrollment_id');
            }
            //$qry->leftJoin('beneficiary_payment_records as t55', 't5.id', '=', 't55.enrollment_id')
            $qry->groupBy('t' . $group_fieldid . '.id')
                ->where($where_data);
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

    public function getUnresponsiveBeneficiariesMainReport(Request $req)
    {
        try {
            $group_fieldid = $req->input('group_field');
            $years_paid_for = $req->input('years_paid_for');
            $years_not_paid_for = $req->input('years_not_paid_for');
            $terms_paid_for = $req->input('terms_paid_for');
            $terms_not_paid_for = $req->input('terms_not_paid_for');
            $batch_id = $req->input('batch_id');
            $payment_request_id = $req->input('payment_request_id');
            $filter = $req->input('filter');
            /*1. Home Province 11
            2. Home District 22
            3. Constituency 33
            4. Ward 44
            5. CWAC 55
            6. School District 66
            7. School 77
            8. School statuses 88
            9. Categories 99
            10. Payment grades 10
            11. Current grades 12*/

            $filter_string = '';
            $whereClauses = array();
            if (isset($filter)) {
                $filters = json_decode($filter);
                if ($filters != NULL) {
                    foreach ($filters as $filter) {
                        switch ($filter->property) {
                            case 'group_fieldvalue' :
                                $whereClauses[] = "t" . $group_fieldid . ".name like '%" . ($filter->value) . "%'";
                                break;
                        }
                    }
                    $whereClauses = array_filter($whereClauses);
                }
                if (!empty($whereClauses)) {
                    $filter_string = implode(' AND ', $whereClauses);
                }
            }

            $qry = DB::table('beneficiary_information as t1');
            $qry->select(DB::raw('t22.name as district_name,
                    SUM(IF(t1.category=1,1,0)) as unresponsive_out,
                    SUM(IF(t1.category=2,1,0)) as unresponsive_in,
                    SUM(IF(t1.category=3,1,0)) as unresponsive_exam,
                    SUM(IF(t1.beneficiary_school_status=1,1,0)) as unresponsive_day,
                    SUM(IF(t1.beneficiary_school_status=2,1,0)) as unresponsive_border,
                    SUM(IF(t1.beneficiary_school_status=3,1,0)) as unresponsive_wborder,
                    SUM(IF(t1.beneficiary_school_status in(0,4) OR t1.beneficiary_school_status IS NULL,1,0)) as unresponsive_unspecified,
                    t' . $group_fieldid . '.name as group_fieldvalue'))
                ->join('districts as t22', 't1.district_id', '=', 't22.id');
            if ($group_fieldid == 11) {
                $qry->join('provinces as t11', 't1.province_id', '=', 't11.id');
            } else if ($group_fieldid == 33) {
                $qry->join('constituencies as t33', 't1.constituency_id', '=', 't33.id');
            } else if ($group_fieldid == 44) {
                $qry->join('wards as t44', 't1.ward_id', '=', 't44.id');
            } else if ($group_fieldid == 55) {
                $qry->join('cwacs as t55', 't1.cwac_id', '=', 't55.id');
            } else if ($group_fieldid == 66) {
                $qry->join('school_information as t77', 't1.school_id', '=', 't77.id')
                    ->join('districts as t66', 't77.district_id', '=', 't66.id');
            } else if ($group_fieldid == 77) {
                $qry->join('school_information as t77', 't1.school_id', '=', 't77.id');
            } else if ($group_fieldid == 88) {
                $qry->join('beneficiary_school_statuses as t88', 't1.beneficiary_school_status', '=', 't88.id');
            } else if ($group_fieldid == 99) {
                $qry->join('beneficiary_categories as t99', 't1.category', '=', 't99.id');
            } else if ($group_fieldid == 10) {
                $qry->join('beneficiary_enrollments as bt10', 't1.id', '=', 'bt10.beneficiary_id')
                    ->join('school_grades as t10', 'bt10.school_grade', '=', 't10.id');
            } else if ($group_fieldid == 12) {
                $qry->join('school_grades as t12', 't1.current_school_grade', '=', 't12.id');
            }
            if (isset($batch_id) && $batch_id != '') {
                $qry->where('t1.batch_id', $batch_id);
            }
            //paid for selected, not paid for unselected
            if (count(json_decode($years_paid_for)) > 0 && count(json_decode($years_not_paid_for)) < 1) {
                $years_paid_for_where = json_decode($years_paid_for);
                $qry->whereIn('t1.id', function ($query) use ($years_paid_for_where, $terms_paid_for, $payment_request_id) {
                    $query->select(DB::raw('en.beneficiary_id'))
                        ->from('beneficiary_enrollments as en')
                        ->join('beneficiary_payment_records as pr', 'en.id', '=', 'pr.enrollment_id')
                        ->whereIn('en.year_of_enrollment', $years_paid_for_where);
                    if (validateisNumeric($payment_request_id)) {
                        $query->where('pr.payment_request_id', $payment_request_id);
                    }
                    if (isset($terms_paid_for) && count(json_decode($terms_paid_for)) > 0) {
                        $terms_paid_for_where = json_decode($terms_paid_for);
                        $query->whereIn('en.term_id', $terms_paid_for_where);
                    }
                    $query->groupBy('en.beneficiary_id');
                });
            }
            //not paid for selected, paid for unselected
            if (count(json_decode($years_not_paid_for)) > 0 && count(json_decode($years_paid_for)) < 1) {
                $years_not_paid_for_where = json_decode($years_not_paid_for);
                $qry->whereNotIn('t1.id', function ($query) use ($years_not_paid_for_where, $terms_not_paid_for) {
                    $query->select(DB::raw('en.beneficiary_id'))
                        ->from('beneficiary_enrollments as en')
                        ->join('beneficiary_payment_records as pr', 'en.id', '=', 'pr.enrollment_id')
                        ->whereIn('en.year_of_enrollment', $years_not_paid_for_where);
                    if (isset($terms_not_paid_for) && count(json_decode($terms_not_paid_for)) > 0) {
                        $terms_not_paid_for_where = json_decode($terms_not_paid_for);
                        $query->whereIn('en.term_id', $terms_not_paid_for_where);
                    }
                    $query->groupBy('en.beneficiary_id');
                });
            }
            //both selected
            if (count(json_decode($years_paid_for)) > 0 && count(json_decode($years_not_paid_for)) > 0) {
                $years_paid_for_where = json_decode($years_paid_for);
                $qry->whereIn('t1.id', function ($query) use ($years_paid_for_where, $terms_paid_for, $payment_request_id) {
                    $query->select(DB::raw('en.beneficiary_id'))
                        ->from('beneficiary_enrollments as en')
                        ->join('beneficiary_payment_records as pr', 'en.id', '=', 'pr.enrollment_id')
                        ->whereIn('en.year_of_enrollment', $years_paid_for_where);
                    if (validateisNumeric($payment_request_id)) {
                        $query->where('pr.payment_request_id', $payment_request_id);
                    }
                    if (isset($terms_paid_for) && count(json_decode($terms_paid_for)) > 0) {
                        $terms_paid_for_where = json_decode($terms_paid_for);
                        $query->whereIn('en.term_id', $terms_paid_for_where);
                    }
                    $query->groupBy('en.beneficiary_id');
                });
                $years_not_paid_for_where = json_decode($years_not_paid_for);
                $qry->whereNotIn('t1.id', function ($query) use ($years_not_paid_for_where, $terms_not_paid_for) {
                    $query->select(DB::raw('en.beneficiary_id'))
                        ->from('beneficiary_enrollments as en')
                        ->join('beneficiary_payment_records as pr', 'en.id', '=', 'pr.enrollment_id')
                        ->whereIn('en.year_of_enrollment', $years_not_paid_for_where);
                    if (isset($terms_not_paid_for) && count(json_decode($terms_not_paid_for)) > 0) {
                        $terms_not_paid_for_where = json_decode($terms_not_paid_for);
                        $query->whereIn('en.term_id', $terms_not_paid_for_where);
                    }
                    $query->groupBy('en.beneficiary_id');
                });
            }
            //none selected
            if (count(json_decode($years_paid_for)) < 1 && count(json_decode($years_not_paid_for)) < 1) {
                $qry->whereNotIn('t1.id', function ($query) use ($years_not_paid_for, $terms_not_paid_for) {
                    $query->select(DB::raw('en.beneficiary_id'))
                        ->from('beneficiary_enrollments as en')
                        ->join('beneficiary_payment_records as pr', 'en.id', '=', 'pr.enrollment_id')
                        ->groupBy('en.beneficiary_id');
                });
            }
            if ($filter_string != '') {
                $qry->whereRAW($filter_string);
            }
            $qry->where('t1.beneficiary_status', 4)
                ->groupBy('t' . $group_fieldid . '.id');
            $results = $qry->get();
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
        }
        return response()->json($res);
    }

    public function getUnresponsiveBeneficiaries(Request $req)
    {
        try {
            $start = $req->input('start');
            $limit = $req->input('limit');
            $filter = $req->input('filter');
            $years_paid_for = $req->input('years_paid_for');
            $years_not_paid_for = $req->input('years_not_paid_for');
            $terms_paid_for = $req->input('terms_paid_for');
            $terms_not_paid_for = $req->input('terms_not_paid_for');
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
                            case 'beneficiary_name' :
                                $whereClauses[] = "t1.beneficiary_name like '%" . ($filter->value) . "%'";
                                break;
                            case 'school_grade' :
                                $whereClauses[] = "t1.school_grade = '" . ($filter->value) . "'";
                                break;
                            case 'current_school_grade' :
                                $whereClauses[] = "t1.current_school_grade = '" . ($filter->value) . "'";
                                break;
                            case 'beneficiary_category' :
                                $whereClauses[] = "t1.category = '" . ($filter->value) . "'";
                                break;
                            case 'school_status_name' :
                                $whereClauses[] = "t1.beneficiary_school_status = '" . ($filter->value) . "'";
                                break;
                            case 'school_name' :
                                $whereClauses[] = "t1.school_id = '" . ($filter->value) . "'";
                                break;
                            case 'home_district_name' :
                                $whereClauses[] = "t1.home_district_id = '" . ($filter->value) . "'";
                                break;
                            case 'school_district_name' :
                                $whereClauses[] = "t1.sch_district_id = '" . ($filter->value) . "'";
                                break;
                            case 'current_status_name' :
                                $whereClauses[] = "t1.enrollment_status = '" . ($filter->value) . "'";
                                break;
                        }
                    }
                    $whereClauses = array_filter($whereClauses);
                }
                if (!empty($whereClauses)) {
                    $filter_string = implode(' AND ', $whereClauses);
                }
            }

            $get_fees = false;
            $fees = array();

            $mainQry = DB::table('vw_payments_rpt as t1');
            $qry = clone $mainQry;
            $qry1 = clone $mainQry;

            //todo: paid for selected, not paid for unselected
            if (count(json_decode($years_paid_for)) > 0 && count(json_decode($years_not_paid_for)) < 1) {
                $years_paid_for_where = json_decode($years_paid_for);
                $qry->whereIn('t1.year_of_enrollment', $years_paid_for_where)
                    ->whereNotNull('t1.req_id');

            }
            if (count(json_decode($terms_paid_for)) > 0) {
                $terms_paid_for_where = json_decode($terms_paid_for);
                $qry->whereIn('t1.term_id', $terms_paid_for_where)
                    ->whereNotNull('t1.req_id');
            }
            //todo: not paid for selected, paid for unselected
            if (count(json_decode($years_not_paid_for)) > 0 && count(json_decode($years_paid_for)) < 1) {
               
                $years_not_paid_for_where = json_decode($years_not_paid_for);
                $qry1->select('t1.girl_id')
                    ->whereIn('t1.year_of_enrollment', $years_not_paid_for_where)
                    ->whereNotNull('t1.req_id')
                    ->groupBy('t1.girl_id');
                if (isset($terms_not_paid_for) && count(json_decode($terms_not_paid_for)) > 0) {
                    $terms_not_paid_for_where = json_decode($terms_not_paid_for);
                    $qry1->whereIn('t1.term_id', $terms_not_paid_for_where);
                }
                $whereNotResultSet = $qry1->get();
                $whereNotArray = convertStdClassObjToArray($whereNotResultSet);
                $whereNotArray = convertAssArrayToSimpleArray($whereNotArray, 'girl_id');
                $qry->whereNotIn('t1.girl_id', $whereNotArray);
            }
            //todo: both selected
             $to_remove=array(); //Job on 22/04/2023
            if (count(json_decode($years_paid_for)) > 0 && count(json_decode($years_not_paid_for)) > 0) {
                 
                $years_paid_for_where = json_decode($years_paid_for);
                $qry->whereIn('t1.year_of_enrollment', $years_paid_for_where)
                    ->whereNotNull('t1.req_id');
                if (count(json_decode($terms_paid_for)) > 0) {
                    $terms_paid_for_where = json_decode($terms_paid_for);
                    $qry->whereIn('t1.term_id', $terms_paid_for_where);
                }

                $years_not_paid_for_where = json_decode($years_not_paid_for);
                $qry1->select('t1.girl_id')
                    ->whereIn('t1.year_of_enrollment', $years_not_paid_for_where)
                    ->whereNotNull('t1.req_id')
                    ->groupBy('t1.girl_id');
                if (isset($terms_not_paid_for) && count(json_decode($terms_not_paid_for)) > 0) {
                    $terms_not_paid_for_where = json_decode($terms_not_paid_for);
                    $qry1->whereIn('t1.term_id', $terms_not_paid_for_where);
                }
                $whereNotResultSet = $qry1->get();
                 // dd($qry->get());
                $whereNotArray = convertStdClassObjToArray($whereNotResultSet);
                $whereNotArray = convertAssArrayToSimpleArray($whereNotArray, 'girl_id');
                 $to_remove = $whereNotArray; //Job on 22/04/2023
                //$qry->whereNotIn('t1.girl_id', $whereNotArray);//removed ,old
                
            }
            //none selected
            if (count(json_decode($years_paid_for)) < 1 && count(json_decode($years_not_paid_for)) < 1) {
                 
                $qry->whereNull('t1.req_id');
            } else {
                
                 
                $qry->whereNotNull('t1.req_id');
            }
            $qry->where('t1.beneficiary_status', 4)
                ->groupBy('t1.girl_id');
            if ($filter_string != '') {
                $qry->whereRAW($filter_string);
            }
            $total = count($qry->get());
            $qry->offset($start)
                ->limit($limit);
            $results = $qry->get();
            //Job on 22/04/2023 start
             $new_results = array();
            $new_results_keys=array();

            foreach ($results as $key => $result) {
                if (!array_key_exists($result->girl_id, $to_remove)) {
                    if (!array_key_exists($result->girl_id, $new_results_keys)) {
                        $new_results[]=$result;
                        $new_results_keys[]=$result->girl_id;
                    }
                }
            }
            if(count( $new_results)>0)
            {
                $results=$new_results;
                 $total = count( $results);
            }
            //end  //Job on 22/04/2023, fix for too large sql statement
            if ($get_fees == true) {
                foreach ($results as $key => $result) {
                    if (array_key_exists($result->id, $fees)) {
                        $results[$key]->school_fees = $fees[$result->id];
                    }
                }
            }
            $res = array(
                'success' => true,
                'results' => $results,
                'totalCount' => $total,
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

    public function getFees($year, $term)
    {
        $where = array(
            'year_of_enrollment' => $year
        );
        if (validateisNumeric($term)) {
            $where['term_id'] = $term;
        }
        $school_fees = DB::table('beneficiary_enrollments as t1')
            ->join('beneficiary_payment_records as t2', 't1.id', '=', 't2.enrollment_id')
            ->select(DB::raw("beneficiary_id,decrypt(annual_fees) as school_fees"))
            ->where($where)
            ->get();
        $school_fees_array = convertStdClassObjToArray($school_fees);
        $school_fees_array = array_column($school_fees_array, 'school_fees', 'beneficiary_id');
        return $school_fees_array;
    }

    public function getExamFeesReport(Request $request)
    {
        try {
            $enrolment_year = $request->input('enrolment_year');
            $enrolment_term = $request->input('enrolment_term');
            $criteria = $request->input('criteria');
            $group_fieldid = $request->input('group_field');
            /*1. Home Province 11
            2. Home District 22
            3. Constituency 33
            4. Ward 44
            5. CWAC 55
            6. School District 66
            7. School 77
            8. School Statuses 88
            9. Categories 99
            10. Enrolment grades 10
            12. Enrolment grades 13*/

            $qry = DB::table('beneficiary_enrollments as t1')
                ->join('beneficiary_information as t2', 't1.beneficiary_id', '=', 't2.id')
                ->join('districts as t22', 't2.district_id', '=', 't22.id')
                ->select(DB::raw('
                    SUM(decrypt(t1.exam_fees)) as total_exam_fees,
                    SUM(IF(t2.category=1,1,0)) as outSchoolCount,
                    SUM(IF(t2.category In (2,3),1,0)) as inSchoolCount,
                    SUM(IF(t2.beneficiary_school_status=1,1,0)) as dayCount,
                    SUM(IF(t2.beneficiary_school_status=2,1,0)) as borderCount,
                    SUM(IF(t2.beneficiary_school_status=3,1,0)) as wborderCount,
                    SUM(IF(t2.beneficiary_school_status in(0,4) OR t2.beneficiary_school_status IS NULL,1,0)) as unspecifiedCount,
                    t' . $group_fieldid . '.name as group_fieldvalue
                '))
                ->where('t1.year_of_enrollment', $enrolment_year)
                ->whereIn('t1.school_grade', array(9, 12));
            //
            if ($group_fieldid == 11) {
                $qry->join('provinces as t11', 't2.province_id', '=', 't11.id');
            } else if ($group_fieldid == 33) {
                $qry->join('constituencies as t33', 't2.constituency_id', '=', 't33.id');
            } else if ($group_fieldid == 44) {
                $qry->join('wards as t44', 't2.ward_id', '=', 't44.id');
            } else if ($group_fieldid == 55) {
                $qry->join('cwacs as t55', 't2.cwac_id', '=', 't55.id');
            } else if ($group_fieldid == 66) {
                $qry->join('school_information as t77', 't1.school_id', '=', 't77.id')
                    ->join('districts as t66', 't77.district_id', '=', 't66.id');
            } else if ($group_fieldid == 77) {
                $qry->join('school_information as t77', 't1.school_id', '=', 't77.id');
            } else if ($group_fieldid == 88) {
                $qry->join('beneficiary_school_statuses as t88', 't1.beneficiary_schoolstatus_id', '=', 't88.id');
            } else if ($group_fieldid == 99) {
                $qry->join('beneficiary_categories as t99', 't2.category', '=', 't99.id');
            } else if ($group_fieldid == 10) {
                $qry->join('school_grades as t10', 't1.school_grade', '=', 't10.id');
            } else if ($group_fieldid == 12) {
                $qry->join('school_grades as t10', 't2.current_school_grade', '=', 't10.id');
            }
            //
            if (validateisNumeric($enrolment_term)) {
                $qry->where('t1.term_id', $enrolment_term);
            }
            if ($criteria == 1) {
                $qry->whereRaw("decrypt(t1.exam_fees)>1");
            } else {
                $qry->whereRaw("decrypt(t1.exam_fees)<1");
            }
            $qry->groupBy('t' . $group_fieldid . '.id');
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

    public function getExamFeesBeneficiariesReport(Request $request)
    {
        try {
            $enrolment_year = $request->input('enrolment_year');
            $enrolment_term = $request->input('enrolment_term');
            $criteria = $request->input('criteria');
            $school_dist_id = $request->input('school_dist_id');
            $home_dist_id = $request->input('home_dist_id');
            $filter = $request->input('filter');
            $whereClauses = array();
            $filter_string = '';
            if (isset($filter)) {
                $filters = json_decode($filter);
                if ($filters != NULL) {
                    foreach ($filters as $filter) {
                        switch ($filter->property) {
                            case 'beneficiary_id' :
                                $whereClauses[] = "t2.beneficiary_id like '%" . ($filter->value) . "%'";
                                break;
                            case 'beneficiary_name' :
                                $whereClauses[] = "decrypt(t2.first_name) like '%" . ($filter->value) . "%' OR decrypt(t2.last_name) like '%" . ($filter->value) . "%'";
                                break;
                            case 'school_name' :
                                $whereClauses[] = "t1.school_id = '" . ($filter->value) . "'";
                                break;
                        }
                    }
                    $whereClauses = array_filter($whereClauses);
                }
                if (!empty($whereClauses)) {
                    $filter_string = implode(' AND ', $whereClauses);
                }
            }

            $qry = DB::table('beneficiary_enrollments as t1')
                ->join('beneficiary_information as t2', 't1.beneficiary_id', '=', 't2.id')
                ->join('school_information as t3', 't1.school_id', '=', 't3.id')
                ->join('districts as t4', 't3.district_id', '=', 't4.id')
                ->select(DB::raw("t1.*,t2.*,CONCAT_WS(' ',decrypt(t2.first_name),decrypt(t2.last_name)) as beneficiary_name,
                        decrypt(t1.exam_fees) as exam_fees,CONCAT_WS('-',t4.code,t4.name) as school_district_name,t3.name as school_name"))
                ->where('t1.year_of_enrollment', $enrolment_year)
                ->whereIn('t1.school_grade', array(9, 12));
            if (validateisNumeric($enrolment_term)) {
                $qry->where('t1.term_id', $enrolment_term);
            }
            if (validateisNumeric($school_dist_id)) {
                $qry->where('t3.district_id', $school_dist_id);
            }
            if (validateisNumeric($home_dist_id)) {
                $qry->where('t2.district_id', $home_dist_id);
            }
            if ($criteria == 1) {
                $qry->whereRaw("decrypt(t1.exam_fees)>1");
            } else {
                $qry->whereRaw("decrypt(t1.exam_fees)<1");
            }
            if ($filter_string != '') {
                $qry->whereRAW($filter_string);
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


    public function getPerformanceAttendanceReport_version2020(Request $req)
    {
        try {
            $group_fieldid = $req->input('group_field');
            $term_id = $req->input('term_id');
            $year_of_enrollment = $req->input('year_of_enrollment');
            $enrollment_batch_id = $req->input('enrollment_batch_id');
            $where_term = array();
            $where_year = array();
            $enrollment_batch = array();
            $total_term_days = getTermTotalLearningDays($year_of_enrollment, $term_id);
            $curr_term_details = getCurrentTerm($year_of_enrollment, $term_id);
            $term_id = $curr_term_details['term'];
            $year_of_enrollment = $curr_term_details['year'];
            if (validateisNumeric($term_id)) {
                $where_term = array('t1.term_id' => $term_id);
            }
            if (validateisNumeric($year_of_enrollment)) {
                $where_year = array('t1.year_id' => $year_of_enrollment);
            }
            if (validateisNumeric($enrollment_batch_id)) {
                $enrollment_batch = array('t1.batch_id' => $enrollment_batch_id);
            }
            /*1. Home Province 11
            2. Home District 22
            3. Constituency 33
            4. Ward 44
            5. CWAC 55
            6. School District 66
            7. School 77
            8. School grades 10*/

            $where_data = array_merge($where_term, $where_year, $enrollment_batch);
            $qry = DB::table('mne_performanceattendance_info as t1')
                ->join('mne_datacollectiontool_dataentry_basicinfo as t2', 't1.record_id', '=', 't2.id')
                //->join('districts as t22', 't2.district_id', '=', 't22.id')
                ->join('districts as t66', 't2.district_id', '=', 't66.id')
                ->select(DB::raw('t66.name as district_name,
                    COUNT(DISTINCT(t1.id)) as no_of_beneficiaries,
                    SUM(IF(t1.mathematics_score>0 AND t1.mathematics_score<100,1,0)) as math_count,
                    SUM(IF(t1.mathsclass_average>0 AND t1.mathsclass_average<100,1,0)) as math_average_count,
                    SUM(IF(t1.english_score>0 AND t1.english_score<100,1,0)) as eng_count,
                    SUM(IF(t1.engclass_average>0 AND t1.engclass_average<100,1,0)) as eng_average_count,
                    SUM(IF(t1.science_score>0 AND t1.science_score<100,1,0)) as scie_count,
                    SUM(IF(t1.scienceclass_average>0 AND t1.scienceclass_average<100,1,0)) as scie_average_count,
                    SUM(IF(t1.benficiary_attendance>0 AND t1.benficiary_attendance<=' . $total_term_days . ',1,0)) as attendance_count,
                    SUM(CASE WHEN t1.mathematics_score>0 AND t1.mathematics_score<100 THEN t1.mathematics_score ELSE 0 END) AS math_total,
                    SUM(CASE WHEN t1.mathsclass_average>0 AND t1.mathsclass_average<100 THEN t1.mathsclass_average ELSE 0 END) AS math_average_total,
                    SUM(CASE WHEN t1.english_score>0 AND t1.english_score<100 THEN t1.english_score ELSE 0 END) AS eng_total,
                    SUM(CASE WHEN t1.engclass_average>0 AND t1.engclass_average<100 THEN t1.engclass_average ELSE 0 END) AS eng_average_total,
                    SUM(CASE WHEN t1.science_score>0 AND t1.science_score<100 THEN t1.science_score ELSE 0 END) AS scie_total,
                    SUM(CASE WHEN t1.scienceclass_average>0 AND t1.scienceclass_average<100 THEN t1.scienceclass_average ELSE 0 END) AS scie_average_total,
                    SUM(CASE WHEN t1.benficiary_attendance>0 AND t1.benficiary_attendance<=' . $total_term_days . ' THEN t1.benficiary_attendance ELSE 0 END) AS total_attendance,
                    t' . $group_fieldid . '.name as group_fieldvalue,t1.year_id as year_of_enrollment'));
            if ($group_fieldid == 11) {
                $qry->join('provinces as t11', 't1.province_id', '=', 't11.id');
            } else if ($group_fieldid == 22) {
                $qry->join('beneficiary_information as t77', 't1.girl_id', '=', 't77.id')
                    ->join('districts as t22', 't77.district_id', '=', 't22.id');
            } else if ($group_fieldid == 33) {
                $qry->join('constituencies as t33', 't1.constituency_id', '=', 't33.id');
            } else if ($group_fieldid == 44) {
                $qry->join('wards as t44', 't1.ward_id', '=', 't44.id');
            } else if ($group_fieldid == 55) {
                $qry->join('cwacs as t55', 't1.cwac_id', '=', 't55.id');
            } /*else if ($group_fieldid == 66) {
                $qry->join('school_information as t77', 't5.school_id', '=', 't77.id')
                    ->join('districts as t66', 't77.district_id', '=', 't66.id');
            }*/ else if ($group_fieldid == 77) {
                $qry->join('school_information as t77', 't1.school_id', '=', 't77.id');
            } else if ($group_fieldid == 88) {
                $qry->join('beneficiary_school_statuses as t88', 't5.beneficiary_schoolstatus_id', '=', 't88.id');
            } else if ($group_fieldid == 99) {
                $qry->join('beneficiary_categories as t99', 't1.category', '=', 't99.id');
            } else if ($group_fieldid == 10) {
                $qry->join('school_grades as t10', 't5.school_grade', '=', 't10.id');
            }
            $qry->groupBy('t' . $group_fieldid . '.id')
                ->where($where_data);
            $results = $qry->get();
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
        public function getPerformanceAttendanceReport(Request $req)
    {
        try {
            $group_fieldid = $req->input('group_field');
            $term_id = $req->input('term_id');
            $year_of_enrollment = $req->input('year_of_enrollment');
            $enrollment_batch_id = $req->input('enrollment_batch_id');
            $where_term = array();
            $where_year = array();
            $enrollment_batch = array();
            $total_term_days = getTermTotalLearningDays($year_of_enrollment, $term_id);
            $curr_term_details = getCurrentTerm($year_of_enrollment, $term_id);
            $term_id = $curr_term_details['term'];
            $year_of_enrollment = $curr_term_details['year'];
            if (validateisNumeric($term_id)) {
                $where_term = array('t1.term_id' => $term_id);
            }
            if (validateisNumeric($year_of_enrollment)) {
                $where_year = array('t1.year_id' => $year_of_enrollment);
            }
            if (validateisNumeric($enrollment_batch_id)) {
                $enrollment_batch = array('t1.batch_id' => $enrollment_batch_id);
            }
            /*1. Home Province 11
            2. Home District 22
            3. Constituency 33
            4. Ward 44
            5. CWAC 55
            6. School District 66
            7. School 77
            8. School grades 10*/
           
           
            if ($year_of_enrollment <= 2020){
                 $where_data = array_merge($where_term, $where_year, $enrollment_batch);
                $qry = DB::table('mne_performanceattendance_info as t1')
                ->join('mne_datacollectiontool_dataentry_basicinfo as t2', 't1.record_id', '=', 't2.id')
                //->join('districts as t22', 't2.district_id', '=', 't22.id')
                ->join('districts as t66', 't2.district_id', '=', 't66.id')
                ->select(DB::raw('t66.name as district_name,
                    COUNT(DISTINCT(t1.id)) as no_of_beneficiaries,
                    SUM(IF(t1.mathematics_score>0 AND t1.mathematics_score<100,1,0)) as math_count,
                    SUM(IF(t1.mathsclass_average>0 AND t1.mathsclass_average<100,1,0)) as math_average_count,
                    SUM(IF(t1.english_score>0 AND t1.english_score<100,1,0)) as eng_count,
                    SUM(IF(t1.engclass_average>0 AND t1.engclass_average<100,1,0)) as eng_average_count,
                    SUM(IF(t1.science_score>0 AND t1.science_score<100,1,0)) as scie_count,
                    SUM(IF(t1.scienceclass_average>0 AND t1.scienceclass_average<100,1,0)) as scie_average_count,
                    SUM(IF(t1.benficiary_attendance>0 AND t1.benficiary_attendance<=' . $total_term_days . ',1,0)) as attendance_count,
                    SUM(CASE WHEN t1.mathematics_score>0 AND t1.mathematics_score<100 THEN t1.mathematics_score ELSE 0 END) AS math_total,
                     SUM(CASE WHEN t1.mathsclass_average>0 AND t1.mathsclass_average<100 THEN t1.mathsclass_average ELSE 0 END) AS math_average_total,
                    SUM(CASE WHEN t1.english_score>0 AND t1.english_score<100 THEN t1.english_score ELSE 0 END) AS eng_total,
                    SUM(CASE WHEN t1.engclass_average>0 AND t1.engclass_average<100 THEN t1.engclass_average ELSE 0 END) AS eng_average_total,
                    SUM(CASE WHEN t1.science_score>0 AND t1.science_score<100 THEN t1.science_score ELSE 0 END) AS scie_total,
                    SUM(CASE WHEN t1.scienceclass_average>0 AND t1.scienceclass_average<100 THEN t1.scienceclass_average ELSE 0 END) AS scie_average_total,
                    SUM(CASE WHEN t1.benficiary_attendance>0 AND t1.benficiary_attendance<=' . $total_term_days . ' THEN t1.benficiary_attendance ELSE 0 END) AS total_attendance,
                    t' . $group_fieldid . '.name as group_fieldvalue,t1.year_id as year_of_enrollment'));


            if ($group_fieldid == 11) {
                $qry->join('provinces as t11','t1.province_id','=','t11.id');
                    } else if ($group_fieldid == 22) {
                        $qry->join('beneficiary_information as t77','t1.girl_id', '=','t77.id')
                            ->join('districts as t22','t77.district_id', '=', 't22.id');
                    } else if ($group_fieldid == 33) {
                        $qry->join('constituencies as t33','t1.constituency_id', '=','t33.id');
                    } else if ($group_fieldid == 44) {
                        $qry->join('wards as t44','t1.ward_id','=', 't44.id');
                    } else if ($group_fieldid == 55) {
                        $qry->join('cwacs as t55','t1.cwac_id','=', 't55.id');
                    } /*else if ($group_fieldid == 66) {
                        $qry->join('school_information as t77', 't5.school_id', '=', 't77.id')
                            ->join('districts as t66', 't77.district_id', '=', 't66.id');
                    }*/ else if ($group_fieldid == 77) {
                        $qry->join('school_information as t77','t1.school_id', '=', 't77.id');
                    } else if ($group_fieldid == 88) {
                        $qry->join('beneficiary_school_statuses as t88','t5.beneficiary_schoolstatus_id', '=', 't88.id');
                    } else if ($group_fieldid == 99) {
                        $qry->join('beneficiary_categories as t99','t1.category', '=', 't99.id');
                    } else if ($group_fieldid == 10) {
                        $qry->join('school_grades as t10','t5.school_grade', '=', 't10.id');
                    }

                    $qry->groupBy('t' . $group_fieldid . '.id')
                        ->where($where_data);
                    

            }else{
                 $where_data = array('t2.entry_year' => $year_of_enrollment,'t2.entry_term' => $req->input('term_id'));
                // Calculation based on the new M&E framework
                $qry = DB::table('mne_spotcheck_perfomance_info AS t1')
                    ->join('mne_datacollectiontool_dataentry_basicinfo AS t2','t1.record_id','=','t2.id')
                    ->join('mne_spotcheck_kgs_girl_enrolments AS t3','t3.record_id','=','t2.id')
                    ->join('districts as t66','t66.id','=','t2.district_id')
                    ->join('mne_pupilsstatistics_info as t7','t7.record_id','=','t2.id')
                    ->select(DB::raw('t66.name as district_name,t2.entry_term as term_id,t1.record_id,t2.entry_term as term_id,
                        COUNT(t1.kgs_mathematics) as math_count,
                            SUM(t1.kgs_mathematics) as math_total, 
                        COUNT(t1.non_kgs_mathematics) + COUNT(t1.kgs_mathematics) as math_average_count,
                            SUM(t1.kgs_mathematics) + SUM(t1.non_kgs_mathematics) as math_average_total, 
                        COUNT(t1.kgs_english) as eng_count,                 
                           SUM(t1.kgs_english) as  eng_total, 
                        COUNT(t1.non_kgs_english) AS eng_average_count,
                            SUM(t1.kgs_english) + SUM(t1.non_kgs_english) as eng_average_total,
                        COUNT(t1.kgs_science)AS scie_count,
                            SUM(t1.kgs_science) AS scie_total,
                        COUNT(t1.non_kgs_science)+COUNT(t1.kgs_science) AS scie_average_count,
                            SUM(t1.kgs_science) + SUM(t1.non_kgs_science) as scie_average_total, 
                        SUM(DISTINCT (t3.kgsgirls_grade_eight+t3.kgsgirls_grade_nine+t3.kgsgirls_grade_ten+t3.kgsgirls_grade_eleven
                                        +t3.kgsgirls_grade_twelve)) AS no_of_beneficiaries,
                         COUNT(total_kgsgirls)+COUNT(total_othergirls) AS attendance_count,
                          SUM(t7.total_kgsgirls)+ SUM(t7.total_othergirls) AS total_attendance,t' . $group_fieldid . '.name as group_fieldvalue'));


                  if ($group_fieldid == 11) {
                      $qry->join('provinces as t11','t2.province_id','=','t11.id');
                    } else if ($group_fieldid == 55) {
                        $qry->join('cwacs as t55','t2.cwac_id','=', 't55.id');
                    } else if ($group_fieldid == 77) {
                        $qry->join('school_information as t77','t2.school_id', '=', 't77.id');
                    } 
                    $qry->groupBy('t' . $group_fieldid . '.id')
                        ->where($where_data);
                }
            $results = $qry->get();
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


    public function getEnrollmentResponse(Request $req)
    {
        $batch_id = $req->input('batch_id');
        try {
            $qry = DB::table('beneficiary_master_info as t1')
                ->leftJoin('beneficiary_information as t2', 't1.id', '=', 't2.master_id')
                ->select(DB::raw('t1.id,t1.sct_district,
                                  COUNT(t1.id) as no_of_girls,
                                  SUM(IF(t2.enrollment_status=1,1,0)) as active,
                                  SUM(IF(t2.enrollment_status=4,1,0)) as completed,
                                  SUM(IF(t2.enrollment_status=5,1,0)) as pending,
                                  SUM(IF(t2.enrollment_status=2,1,0)) as suspended
                '));
            if (isset($batch_id) && $batch_id != '') {
                $qry->where('t1.batch_id', $batch_id);
            }
            $qry->groupBy('t1.sct_district');
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

    public function getBenEnrollmentStatusesBrief(Request $req)
    {
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->select(DB::raw('SUM(IF(t1.enrollment_status = 1,1,0)) as active_total,
                                  SUM(IF(t1.enrollment_status = 4,1,0)) as completed_total,
                                  SUM(IF(t1.enrollment_status = 5,1,0)) as pending_total,
                                  SUM(IF(t1.enrollment_status = 2,1,0)) as suspended_total'));
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

    public function getEnrollmentsPerHomeDistricts(Request $req)
    {
        $year = $req->input('year_filter');
        if ($year == '') {
            $res = array(
                'success' => false,
                'message' => 'Select Year of Enrollment!!'
            );
            return response()->json($res);
        }
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->join('beneficiary_enrollments as t3', function ($join) use ($year) {
                    $join->on('t1.id', '=', 't3.beneficiary_id')
                        ->on('t3.year_of_enrollment', '=', DB::raw($year));
                })
                ->select(DB::raw('t1.district_id,t2.name as district_name,t3.year_of_enrollment,
                                  SUM(IF(t3.term_id = 1,1,0)) as term1,
                                  SUM(IF(t3.term_id = 2,1,0)) as term2,
                                  SUM(IF(t3.term_id = 3,1,0)) as term3'))
                ->where('t3.is_validated', 1)
                ->where('t3.year_of_enrollment', $year)
                ->groupBy('t1.district_id');
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

    public function getEnrollmentsPerHomeDistrictsCWAC(Request $req)
    {
        $year = $req->input('year_filter');
        if ($year == '') {
            $res = array(
                'success' => false,
                'message' => 'Select Year of Enrollment!!'
            );
            return response()->json($res);
        }
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->join('cwac as t4', 't1.cwac_id', '=', 't4.id')
                ->join('beneficiary_enrollments as t3', function ($join) use ($year) {
                    $join->on('t1.id', '=', 't3.beneficiary_id')
                        ->on('t3.year_of_enrollment', '=', DB::raw($year));
                })
                ->select(DB::raw('t1.district_id,t2.name as district_name,t3.year_of_enrollment,t4.name as cwac,
                                  SUM(IF(t3.term_id = 1,1,0)) as term1,
                                  SUM(IF(t3.term_id = 2,1,0)) as term2,
                                  SUM(IF(t3.term_id = 3,1,0)) as term3'))
                ->where('t3.is_validated', 1)
                ->where('t3.year_of_enrollment', $year)
                ->groupBy('t1.cwac_id');
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

    public function getEnrollmentsPerSchoolDistricts(Request $req)
    {
        $year = $req->input('year_filter');
        if ($year == '') {
            $res = array(
                'success' => false,
                'message' => 'Select Year of Enrollment!!'
            );
            return response()->json($res);
        }
        try {
            $qry = DB::table('beneficiary_enrollments as t1')
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                ->join('districts as t3', 't2.district_id', '=', 't3.id')
                ->select(DB::raw('t2.district_id,t3.name as district_name,t1.year_of_enrollment,t2.name as school,
                                  SUM(IF(t1.term_id = 1,1,0)) as term1,
                                  SUM(IF(t1.term_id = 2,1,0)) as term2,
                                  SUM(IF(t1.term_id = 3,1,0)) as term3'))
                ->where('t1.is_validated', 1)
                ->where('t1.year_of_enrollment', $year)
                ->groupBy('t1.school_id');
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

    function printVerificationData(Request $request)
    {
        $sheet_name = "verification_data";
        $qry = DB::table('beneficiary_verification_report as t1')
            ->join('checklist_items as t2', 't1.checklist_item_id', '=', 't2.id')
            ->join('beneficiary_information as t3', 't1.beneficiary_id', '=', 't3.id')
            ->leftJoin('checklist_options as t4', 't1.response', '=', 't4.id')
            ->whereNotIn('t2.checklist_id', array(3, 4))
            ->select(DB::raw("t3.id as girl_id,t2.answer_type,t4.option_name,t3.beneficiary_id as Beneficiary_ID,t2.name as Checklist_Item,t1.response,t1.remark"))
            ->orderBy('t3.id')
            ->limit(500);
        $results = $qry->get();
        foreach ($results as $key => $result) {
            if ($result->answer_type == 2) {
                $results[$key]->response = $result->option_name;
            }
            if ($result->answer_type == 1) {
                $results[$key]->response = $this->_getBeneficiaryDisabilities($result->girl_id);
            }
        }

        $results = convertStdClassObjToArray($results);

        Excel::create($sheet_name, function ($excel) use ($results, $sheet_name) {
            // Set the spreadsheet title, creator, and description
            $excel->setTitle($sheet_name);
            $excel->setCreator('KGSMIS')->setCompany('SoftClans Technologies LTD');
            $excel->setDescription($sheet_name . ' Excel File');
            $excel->sheet('sheet1', function ($sheet) use ($results) {
                $sheet->fromArray($results, null, 'A1', false, true);
            });

        })->download('xls');
    }

    private
    function _getBeneficiaryDisabilities($ben_id)
    {
        $disabilities = DB::table('beneficiary_disabilities')->where(array('beneficiary_id' => $ben_id))->get();
        $disabilities = convertStdClassObjToArray($disabilities);
        $disabilities = convertAssArrayToSimpleArray($disabilities, 'disability_id');
        return implode(",", $disabilities);
    }

    public function getSchoolFacilityTypes(Request $request)
    {
        try {
            $enrollment_year = $request->input('year_of_enrollment');
            $enrollment_term = $request->input('term_id');
            $signed = $request->input('signed');
            $validated = $request->input('validated');
            $province_id = $request->input('province_id');
            $district_id = $request->input('district_id');
            $qry = DB::table('beneficiary_information as t1')
                ->join('beneficiary_enrollments as t2', 't1.id', '=', 't2.beneficiary_id')
                ->join('school_information as t3', 't1.school_id', '=', 't3.id')
                ->join('school_types as t4', 't3.school_type_id', '=', 't4.id')
                ->leftJoin('school_types_categories as t5', 't3.facility_type_id', '=', 't5.id')
                ->join('districts as t6', 't3.district_id', '=', 't6.id')
                ->leftJoin('weeklyboarding_facility_managers as t7', 't3.facility_manager_id', '=', 't7.id')
                ->leftJoin('weeklyboarding_facility_locations as t8', 't3.facility_location_id', '=', 't8.id')
                ->leftJoin('confirmations as t9', 't3.facility_check1', '=', 't9.id')
                ->leftJoin('confirmations as t10', 't3.facility_check2', '=', 't10.id')
                ->leftJoin('confirmations as t11', 't3.facility_check3', '=', 't11.id')
                ->select(DB::raw("t6.name as district_name,CONCAT_WS('-',t3.CODE,t3.NAME) as school_name,t4.NAME as school_type,t5.name as facility_type,
                                        t9.NAME AS matron,t10.NAME AS toilet,t11.NAME AS lock_system,t7.NAME AS wb_facilityManager,t8.name AS wb_facilityLocation,
                                        SUM(IF(t1.beneficiary_school_status=1,1,0)) AS day_scholars,
                                        SUM(IF(t1.beneficiary_school_status=2,1,0)) AS boarders,
                                        SUM(IF(t1.beneficiary_school_status=3,1,0)) AS weekly_boarders,
                                        SUM(IF(t1.beneficiary_school_status not in(1,2,3),1,0)) AS unspecified,
                                        SUM(IF(t1.current_school_grade=7,1,0)) AS grade7,
                                        SUM(IF(t1.current_school_grade=8,1,0)) AS grade8,
                                        SUM(IF(t1.current_school_grade=9,1,0)) AS grade9,
                                        SUM(IF(t1.current_school_grade=10,1,0)) AS grade10,
                                        SUM(IF(t1.current_school_grade=11,1,0)) AS grade11,
                                        SUM(IF(t1.current_school_grade=12,1,0)) AS grade12"));
            if (validateisNumeric($enrollment_year)) {
                $qry->where('t2.year_of_enrollment', $enrollment_year);
            }
            if (validateisNumeric($enrollment_term)) {
                $qry->where('t2.term_id', $enrollment_term);
            }
            if (validateisNumeric($province_id)) {
                $qry->where('t3.province_id', $province_id);
            }
            if (validateisNumeric($district_id)) {
                $qry->where('t3.district_id', $district_id);
            }
            if ($signed == 1) {
                $qry->where('t2.has_signed', 1);
            } else {
                $qry->where(function ($query) {
                    $query->where('t2.has_signed', 0)
                        ->orWhereNull('t2.has_signed');
                });
            }
            if ($validated == true || $validated === true) {
                $qry->where('t2.is_validated', 1);
            }
            $qry->groupBy('t1.school_id')
                ->orderBy('t6.id');
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

    public function getInitialAnnualEnrollments(Request $request)
    {
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->join('provinces as t3', 't2.province_id', '=', 't3.id')
                ->selectRaw("t2.name as district_name,t3.name as province_name,YEAR(t1.enrollment_date) as enrol_year,
                            sum(IF(TIMESTAMPDIFF(YEAR,t1.dob,NOW()) between 1 and 9,1,0)) as firstAgeBracket,
                            sum(IF(TIMESTAMPDIFF(YEAR,t1.dob, NOW()) between 10 and 14,1,0)) as secondAgeBracket,
                            sum(IF(TIMESTAMPDIFF(YEAR,t1.dob,NOW()) between 15 and 19,1,0)) as thirdAgeBracket,
                            sum(IF(TIMESTAMPDIFF(YEAR,t1.dob,NOW()) >19,1,0)) as fourthAgeBracket,

                            sum(IF(t1.current_school_grade=7,1,0)) as grade7,
                            sum(IF(t1.current_school_grade=8,1,0)) as grade8,
                            sum(IF(t1.current_school_grade=9,1,0)) as grade9,
                            sum(IF(t1.current_school_grade=10,1,0)) as grade10,
                            sum(IF(t1.current_school_grade=11,1,0)) as grade11,
                            sum(IF(t1.current_school_grade=12,1,0)) as grade12")
                ->where('t1.beneficiary_status', 4)
                ->groupBy(DB::raw("t1.district_id,YEAR(t1.enrollment_date)"));
            //->groupBy('t1.district_id','YEAR(t1.enrollment_date)');
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

    public function getBeneficiaryAnnualEnrollmentsRptTabular()
    {
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->join('beneficiary_enrollments as t2', 't1.id', '=', 't2.beneficiary_id')
                ->join('districts as t3', 't1.district_id', '=', 't3.id')
                ->join('provinces as t4', 't3.province_id', '=', 't4.id')
                ->select(DB::raw('t2.year_of_enrollment as enrol_year,t3.name as district_name,t4.name as province_name,
                            COUNT(DISTINCT CASE WHEN TIMESTAMPDIFF(YEAR,t1.dob,NOW()) between 1 and 9 THEN t1.id END) as firstAgeBracket,
                            COUNT(DISTINCT CASE WHEN TIMESTAMPDIFF(YEAR,t1.dob,NOW()) between 10 and 14 THEN t1.id END) as secondAgeBracket,
                            COUNT(DISTINCT CASE WHEN TIMESTAMPDIFF(YEAR,t1.dob,NOW()) between 15 and 19 THEN t1.id END) as thirdAgeBracket,
                            COUNT(DISTINCT CASE WHEN TIMESTAMPDIFF(YEAR,t1.dob,NOW()) > 19 THEN t1.id END) as fourthAgeBracket,

                            COUNT(DISTINCT CASE WHEN t2.school_grade = 7 THEN t1.id END) as grade7,
                            COUNT(DISTINCT CASE WHEN t2.school_grade = 8 THEN t1.id END) as grade8,
                            COUNT(DISTINCT CASE WHEN t2.school_grade = 9 THEN t1.id END) as grade9,
                            COUNT(DISTINCT CASE WHEN t2.school_grade = 10 THEN t1.id END) as grade10,
                            COUNT(DISTINCT CASE WHEN t2.school_grade = 11 THEN t1.id END) as grade11,
                            COUNT(DISTINCT CASE WHEN t2.school_grade = 12 THEN t1.id END) as grade12'));
            $qry->where('t2.is_validated', 1)
                ->groupBy(DB::raw("t1.district_id,t2.year_of_enrollment"));
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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

    public function getAnnualTakeUpStatusReport()
    {
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->join('districts as t3', 't1.district_id', '=', 't3.id')
                ->join('provinces as t4', 't3.province_id', '=', 't4.id')
                ->select(DB::raw('YEAR(t1.kgs_takeup_date) as enrol_year,t3.name as district_name,t4.name as province_name,
                            COUNT(DISTINCT CASE WHEN TIMESTAMPDIFF(YEAR,t1.dob,t1.kgs_takeup_date) between 1 and 9 THEN t1.id END) as firstAgeBracket,
                            COUNT(DISTINCT CASE WHEN TIMESTAMPDIFF(YEAR,t1.dob,t1.kgs_takeup_date) between 10 and 14 THEN t1.id END) as secondAgeBracket,
                            COUNT(DISTINCT CASE WHEN TIMESTAMPDIFF(YEAR,t1.dob,t1.kgs_takeup_date) between 15 and 19 THEN t1.id END) as thirdAgeBracket,
                            COUNT(DISTINCT CASE WHEN TIMESTAMPDIFF(YEAR,t1.dob,t1.kgs_takeup_date) > 19 THEN t1.id END) as fourthAgeBracket,

                            COUNT(DISTINCT CASE WHEN t1.kgs_takeup_grade = 7 THEN t1.id END) as grade7,
                            COUNT(DISTINCT CASE WHEN t1.kgs_takeup_grade = 8 THEN t1.id END) as grade8,
                            COUNT(DISTINCT CASE WHEN t1.kgs_takeup_grade = 9 THEN t1.id END) as grade9,
                            COUNT(DISTINCT CASE WHEN t1.kgs_takeup_grade = 10 THEN t1.id END) as grade10,
                            COUNT(DISTINCT CASE WHEN t1.kgs_takeup_grade = 11 THEN t1.id END) as grade11,
                            COUNT(DISTINCT CASE WHEN t1.kgs_takeup_grade = 12 THEN t1.id END) as grade12'));
            $qry->where('t1.kgs_takeup_status', 1)
                ->groupBy(DB::raw("t1.district_id,YEAR(t1.kgs_takeup_date)"));
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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

    public function getEnrollmentProgressionCriteria(Request $request)
    {
        try {
            $criteria = $request->input('criteria');
            $results = array();
            if ($criteria == 1 || $criteria === 1) {
                $results = $this->getBeneficiaryAnnualEnrollmentProgression($request);
            } else if ($criteria == 2 || $criteria === 2) {
                $results = $this->getBeneficiaryAnnualEnrollmentDropout($request);
            } else if ($criteria == 3 || $criteria === 3) {
                $results = $this->getBeneficiaryAnnualEnrollmentRepetition($request);
            }
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => 'Successful!'
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

    public function getBeneficiaryAnnualEnrollmentProgression(Request $request)
    {
        $enrol_year1 = $request->input('enrol_year1');
        $enrol_year2 = $request->input('enrol_year2');
        $qry = DB::table('beneficiary_information as t1')
            ->join('beneficiary_enrollments as t2', 't1.id', '=', 't2.beneficiary_id')
            ->join('districts as t3', 't1.district_id', '=', 't3.id')
            ->join('provinces as t4', 't3.province_id', '=', 't4.id')
            ->select(DB::raw('t2.year_of_enrollment as enrol_year,t3.name as district_name,t4.name as province_name,
                            COUNT(DISTINCT CASE WHEN TIMESTAMPDIFF(YEAR,t1.dob,NOW()) between 1 and 9 THEN t1.id END) as firstAgeBracket,
                            COUNT(DISTINCT CASE WHEN TIMESTAMPDIFF(YEAR,t1.dob,NOW()) between 10 and 14 THEN t1.id END) as secondAgeBracket,
                            COUNT(DISTINCT CASE WHEN TIMESTAMPDIFF(YEAR,t1.dob,NOW()) between 15 and 19 THEN t1.id END) as thirdAgeBracket,
                            COUNT(DISTINCT CASE WHEN TIMESTAMPDIFF(YEAR,t1.dob,NOW()) > 19 THEN t1.id END) as fourthAgeBracket,

                            COUNT(DISTINCT CASE WHEN t2.school_grade = 7 THEN t1.id END) as grade7,
                            COUNT(DISTINCT CASE WHEN t2.school_grade = 8 THEN t1.id END) as grade8,
                            COUNT(DISTINCT CASE WHEN t2.school_grade = 9 THEN t1.id END) as grade9,
                            COUNT(DISTINCT CASE WHEN t2.school_grade = 10 THEN t1.id END) as grade10,
                            COUNT(DISTINCT CASE WHEN t2.school_grade = 11 THEN t1.id END) as grade11,
                            COUNT(DISTINCT CASE WHEN t2.school_grade = 12 THEN t1.id END) as grade12'));
        $qry->where('t2.is_validated', 1)
            ->where('t2.year_of_enrollment', $enrol_year1)
            ->whereIn('t1.id', function ($query) use ($enrol_year2) {
                $query->select(DB::raw('en.beneficiary_id'))
                    ->from('beneficiary_enrollments as en')
                    ->where('en.year_of_enrollment', $enrol_year2)
                    ->where('en.is_validated', 1);
            })
            ->groupBy(DB::raw("t1.district_id"));
        $results = $qry->get();
        return $results;
    }

    public function getBeneficiaryAnnualEnrollmentDropout(Request $request)
    {
        $enrol_year1 = $request->input('enrol_year1');
        $enrol_year2 = $request->input('enrol_year2');
        $qry = DB::table('beneficiary_information as t1')
            ->join('beneficiary_enrollments as t2', 't1.id', '=', 't2.beneficiary_id')
            ->join('districts as t3', 't1.district_id', '=', 't3.id')
            ->join('provinces as t4', 't3.province_id', '=', 't4.id')
            ->select(DB::raw("t2.year_of_enrollment as enrol_year,t3.name as district_name,t4.name as province_name,
                            COUNT(DISTINCT CASE WHEN TIMESTAMPDIFF(YEAR,t1.dob,NOW()) between 1 and 9 THEN t1.id END) as firstAgeBracket,
                            COUNT(DISTINCT CASE WHEN TIMESTAMPDIFF(YEAR,t1.dob,NOW()) between 10 and 14 THEN t1.id END) as secondAgeBracket,
                            COUNT(DISTINCT CASE WHEN TIMESTAMPDIFF(YEAR,t1.dob,NOW()) between 15 and 19 THEN t1.id END) as thirdAgeBracket,
                            COUNT(DISTINCT CASE WHEN TIMESTAMPDIFF(YEAR,t1.dob,NOW()) > 19 THEN t1.id END) as fourthAgeBracket,

                            COUNT(DISTINCT CASE WHEN t2.school_grade = 7 THEN t1.id END) as grade7,
                            COUNT(DISTINCT CASE WHEN t2.school_grade = 8 THEN t1.id END) as grade8,
                            COUNT(DISTINCT CASE WHEN t2.school_grade = 9 THEN t1.id END) as grade9,
                            COUNT(DISTINCT CASE WHEN t2.school_grade = 10 THEN t1.id END) as grade10,
                            COUNT(DISTINCT CASE WHEN t2.school_grade = 11 THEN t1.id END) as grade11,
                            COUNT(DISTINCT CASE WHEN t2.school_grade = 12 THEN t1.id END) as grade12"));
        $qry->where('t2.is_validated', 1)
            ->where('t2.year_of_enrollment', $enrol_year1)
            ->where('t2.school_grade', '<>', 12)
            ->whereNotIn('t1.id', function ($query) use ($enrol_year2) {
                $query->select(DB::raw('en.beneficiary_id'))
                    ->from('beneficiary_enrollments as en')
                    ->where('en.year_of_enrollment', $enrol_year2)
                    ->where('en.is_validated', 1);
            })
            ->groupBy(DB::raw("t1.district_id"));
        $results = $qry->get();
        return $results;
    }

    public function getBeneficiaryAnnualEnrollmentRepetition(Request $request)
    {
        $enrol_year1 = $request->input('enrol_year1');
        $enrol_year2 = $request->input('enrol_year2');
        $qry = DB::table('beneficiary_information as t1')
            ->join('beneficiary_enrollments as t2', 't1.id', '=', 't2.beneficiary_id')
            ->join('districts as t3', 't1.district_id', '=', 't3.id')
            ->join('provinces as t4', 't3.province_id', '=', 't4.id')
            ->join('beneficiary_enrollments as t5', 't2.beneficiary_id', '=', 't5.beneficiary_id')
            ->select(DB::raw('t2.year_of_enrollment as enrol_year,t3.name as district_name,t4.name as province_name,
                            COUNT(DISTINCT CASE WHEN TIMESTAMPDIFF(YEAR,t1.dob,NOW()) between 1 and 9 THEN t1.id END) as firstAgeBracket,
                            COUNT(DISTINCT CASE WHEN TIMESTAMPDIFF(YEAR,t1.dob,NOW()) between 10 and 14 THEN t1.id END) as secondAgeBracket,
                            COUNT(DISTINCT CASE WHEN TIMESTAMPDIFF(YEAR,t1.dob,NOW()) between 15 and 19 THEN t1.id END) as thirdAgeBracket,
                            COUNT(DISTINCT CASE WHEN TIMESTAMPDIFF(YEAR,t1.dob,NOW()) > 19 THEN t1.id END) as fourthAgeBracket,

                            COUNT(DISTINCT CASE WHEN t2.school_grade = 7 THEN t1.id END) as grade7,
                            COUNT(DISTINCT CASE WHEN t2.school_grade = 8 THEN t1.id END) as grade8,
                            COUNT(DISTINCT CASE WHEN t2.school_grade = 9 THEN t1.id END) as grade9,
                            COUNT(DISTINCT CASE WHEN t2.school_grade = 10 THEN t1.id END) as grade10,
                            COUNT(DISTINCT CASE WHEN t2.school_grade = 11 THEN t1.id END) as grade11,
                            COUNT(DISTINCT CASE WHEN t2.school_grade = 12 THEN t1.id END) as grade12'));
        $qry->where('t2.is_validated', 1)
            ->where('t2.year_of_enrollment', $enrol_year1)
            ->where('t5.year_of_enrollment', $enrol_year2)
            ->where('t5.is_validated', 1)
            ->whereRaw('t2.school_grade=t5.school_grade')
            ->groupBy(DB::raw("t1.district_id"));
        $results = $qry->get();
        return $results;
    }

    public function getBeneficiaryAnnualEnrollmentCompletion()
    {
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->join('beneficiaries_transitional_report as t2', 't1.id', '=', 't2.girl_id')
                ->join('districts as t3', 't1.district_id', '=', 't3.id')
                ->join('provinces as t4', 't3.province_id', '=', 't4.id')
                ->select(DB::raw('YEAR(t2.created_at) as enrol_year,t3.name as district_name,t4.name as province_name,
                            COUNT(DISTINCT CASE WHEN TIMESTAMPDIFF(YEAR,t1.dob,NOW()) between 1 and 9 THEN t1.id END) as firstAgeBracket,
                            COUNT(DISTINCT CASE WHEN TIMESTAMPDIFF(YEAR,t1.dob,NOW()) between 10 and 14 THEN t1.id END) as secondAgeBracket,
                            COUNT(DISTINCT CASE WHEN TIMESTAMPDIFF(YEAR,t1.dob,NOW()) between 15 and 19 THEN t1.id END) as thirdAgeBracket,
                            COUNT(DISTINCT CASE WHEN TIMESTAMPDIFF(YEAR,t1.dob,NOW()) > 19 THEN t1.id END) as fourthAgeBracket'));
            $qry->where('t1.enrollment_status', '=', 4)
                ->where('t1.kgs_takeup_status', '=', 1)
                ->where('t2.to_stage', '=', 4)
                ->groupBy(DB::raw("t1.district_id,YEAR(t2.created_at)"));
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

    public function getBeneficiaryEnrolments(Request $request)
    {
        try {
            $enrolment_year = $request->input('enrolment_year');
            $term_id = $request->input('term_id');
            $province_id = $request->input('province_id');
            $district_id = $request->input('district_id');
            $school_id = $request->input('school_id');
            $uploaded_signed_consent = $request->input('uploaded_signed_consent');
            $beneficiary_id = $request->input('beneficiary_id');

            $filter = $request->input('filter');
            $whereClauses = array();
            $filter_string = '';
            if (isset($filter)) {
                $filters = json_decode($filter);
                if ($filters != NULL) {
                    foreach ($filters as $filter) {
                        switch ($filter->property) {
                            case 'wb_facility_manager_id' :
                                $whereClauses[] = "t2.wb_facility_manager_id = '" . ($filter->value) . "'";
                                break;
                            case 'has_signed_consent' :
                                $whereClauses[] = "t2.has_signed_consent = '" . ($filter->value) . "'";
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
                ->select(DB::raw("t1.*, t1.id as girl_id,
                                  t2.id as enrollement_id, t2.*, t4.name as home_district, t1.beneficiary_id as beneficiary_no,
                                  t2.school_grade-1 as performance_grade,t3.name as school,t4.name as district,
                                  decrypt(t1.first_name) as first_name,decrypt(t1.last_name) as last_name,
                                  decrypt(t2.term1_fees) as term1_fees,decrypt(t2.term2_fees) as term2_fees,decrypt(t2.term3_fees) as term3_fees,decrypt(t2.exam_fees) as exam_fees,decrypt(t2.annual_fees) as annual_fees,
                                  CASE WHEN t2.school_grade=9 THEN t1.grade9_exam_no WHEN t2.school_grade=12 THEN t1.grade12_exam_no ELSE '' END as exam_number
                                 "))
                ->join('beneficiary_enrollments as t2', 't1.id', '=', 't2.beneficiary_id')
                ->join('school_information as t3', 't2.school_id', '=', 't3.id')
                ->join('districts as t4', 't1.district_id', '=', 't4.id')
                ->where(array('t2.year_of_enrollment' => $enrolment_year));
            if ($filter_string != '') {
                $qry->whereRAW($filter_string);
            }
            if (validateisNumeric($term_id)) {
                $qry->where('t2.term_id', $term_id);
            }
            if (validateisNumeric($province_id)) {
                $qry->where('t4.province_id', $province_id);
            }
            if (validateisNumeric($district_id)) {
                $qry->where('t4.id', $district_id);
            }
            if (validateisNumeric($school_id)) {
                $qry->where('t3.id', $school_id);
            }
            if (validateisNumeric($uploaded_signed_consent)) {
                if ($uploaded_signed_consent == 1) {
                    $qry->join('signedconsent_formsuploads as t5', 't2.id', '=', 't5.enrolment_id');
                } else if ($uploaded_signed_consent == 2) {
                    $qry->leftJoin('signedconsent_formsuploads as t5', 't2.id', '=', 't5.enrolment_id')
                        ->whereNull('t5.id');
                }
            }
            if (validateisNumeric($beneficiary_id)) {
                $qry->where('t1.beneficiary_id', 'LIKE', "%$beneficiary_id%");
            }
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

    public function exportReportsModuleRecords(Request $request)
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

}
