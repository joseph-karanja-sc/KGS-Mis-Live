<?php
/**
 * Created by PhpStorm.
 * User: Kip
 * Date: 9/26/2017
 * Time: 10:39 AM
 */

namespace App\Modules\ReportsModule\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use PDF;
use JasperPHP;

class ReportsCurrent extends Controller
{
    protected $dms_path;

    public function __construct()
    {
        $this->dms_path = getcwd() . '/mis_dms/';
    }

    public function printInSchoolVerificationChecklists($batch_id, $provinces, $districts, $schools, $checker = 1, $beneficiary_status)
    {
        $category = 2;
        $filename = 'InSchool_checklist';
        $checklist_id = DB::table('batch_checklist_types')
            ->where(array('batch_id' => $batch_id, 'category_id' => $category))
            ->value('checklist_type_id');
        if (!is_numeric($checklist_id) || $checklist_id == '') {
            echo "<p style='text-align: center;color: red'>Checklist not configured!!</p>";
            exit();
        }
        //todo:: generation can be per school, per district or per province....this is a bit tricky...school supersedes all, district supersedes provinces
        if (isset($schools) && count(json_decode($schools)) > 0) {//schools selected
            $schoolsArray = json_decode($schools);
            if ($checker == 2) {
                $this->generateBeneficiariesList($checklist_id, $schoolsArray, $batch_id, $category, $filename);
            } else {
                $this->generateInSCHVerificationChecklist($checklist_id, $schoolsArray, $batch_id, $category, $filename, $beneficiary_status);
            }
        } else if (isset($cwacs) && count(json_decode($cwacs)) > 0) {//CWAC selected...this must be out of school
            //get all schools in the selected districts
            $districtsArray = json_decode($districts);
            $schoolsArray = DB::table('school_information')->select('id')->whereIn('district_id', $districtsArray)->get();
            $schoolsArray = convertStdClassObjToArray($schoolsArray);
            $schoolsArray = convertAssArrayToSimpleArray($schoolsArray, 'id');
            if ($checker == 2) {
                $this->generateBeneficiariesList($checklist_id, $schoolsArray, $batch_id, $category, $filename);
            } else {
                $this->generateInSCHVerificationChecklist($checklist_id, $schoolsArray, $batch_id, $category, $filename, $beneficiary_status);
            }
        } else if (isset($districts) && count(json_decode($districts)) > 0) {//schools not set..now use districts
            //get all schools in the selected districts
            $districtsArray = json_decode($districts);
            $schoolsArray = DB::table('school_information')
                ->join('beneficiary_information', 'beneficiary_information.school_id', '=', 'school_information.id')
                ->select('school_information.id')
                ->groupBy('school_information.id')
                ->whereIn('school_information.district_id', $districtsArray)
                ->get();
            $schoolsArray = convertStdClassObjToArray($schoolsArray);
            $schoolsArray = convertAssArrayToSimpleArray($schoolsArray, 'id');
            if ($checker == 2) {
                $this->generateBeneficiariesList($checklist_id, $schoolsArray, $batch_id, $category, $filename, $beneficiary_status);
            } else {
                $this->generateInSCHVerificationChecklist($checklist_id, $schoolsArray, $batch_id, $category, $filename, $beneficiary_status);
            }
        } else if (isset($provinces) && count(json_decode($provinces)) > 0) {//schools,districts not set...now use provinces
            //get all schools in the selected provinces
            $provincesArray = json_decode($provinces);
            $schoolsArray = DB::table('school_information')->select('id')->whereIn('province_id', $provincesArray)->get();
            $schoolsArray = convertStdClassObjToArray($schoolsArray);
            $schoolsArray = convertAssArrayToSimpleArray($schoolsArray, 'id');
            if ($checker == 2) {
                $this->generateBeneficiariesList($checklist_id, $schoolsArray, $batch_id, $category, $filename);
            } else {
                $this->generateInSCHVerificationChecklist($checklist_id, $schoolsArray, $batch_id, $category, $filename, $beneficiary_status);
            }
        } else {//nothing set....how did you find yourself here??

        }
    }

    public function printInSchoolSpecificChecklists(Request $request)
    {
        try {
            $batch_id = $request->input('batch_id');
            $school_id = $request->input('school_id');
            $print_type = $request->input('print_type');
            $category = 2;
            $filename = 'InSchool_checklist';
            $checklist_id = DB::table('batch_checklist_types')
                ->where(array('batch_id' => $batch_id, 'category_id' => $category))
                ->value('checklist_type_id');
            if (!is_numeric($checklist_id) || $checklist_id == '') {
                echo "<p style='text-align: center;color: red'>Checklist not configured!!</p>";
                exit();
            }
            $this->generateInSCHSpecificVerificationChecklist($checklist_id, $school_id, $batch_id, $print_type, $filename);
        } catch (\Exception $exception) {
            echo "<p style='text-align: center;color: red'>" . $exception->getMessage() . "</p>";
            exit();
        } catch (\Throwable $throwable) {
            echo "<p style='text-align: center;color: red'>" . $throwable->getMessage() . "</p>";
            exit();
        }
    }

    public function printOutOfSchoolVerificationChecklists($batch_id, $provinces, $districts, $cwacs, $checker = 1, $beneficiary_status)
    {
        $category = 1;
        $filename = 'OutOfSchool_checklist';
        $checklist_id = DB::table('batch_checklist_types')
            ->where(array('batch_id' => $batch_id, 'category_id' => $category))
            ->value('checklist_type_id');
        if (!is_numeric($checklist_id) || $checklist_id == '') {
            echo "<p style='text-align: center;color: red'>Checklist not configured!!</p>";
            exit();
        }
        //todo:: generation can be per school, per district or per province....this is a bit tricky...school supersedes all, district supersedes provinces
        if (isset($cwacs) && count(json_decode($cwacs)) > 0) {//CWAC selected...this must be out of school
            //get all schools in the selected districts
            $cwacsArray = json_decode($cwacs);
            if ($checker == 2) {
                $this->generateBeneficiariesList($checklist_id, $cwacsArray, $batch_id, $category, $filename);
            } else {
                $this->generateOutOfSCHVerificationChecklist($checklist_id, $cwacsArray, $batch_id, $category, $filename);
            }
        } else if (isset($districts) && count(json_decode($districts)) > 0) {//schools not set..now use districts
            //get all schools in the selected districts
            $districtsArray = json_decode($districts);
            $cwacsArray = DB::table('cwac')->select('id')->whereIn('district_id', $districtsArray)->get();
            $cwacsArray = convertStdClassObjToArray($cwacsArray);
            $cwacsArray = convertAssArrayToSimpleArray($cwacsArray, 'id');
            if ($checker == 2) {
                $this->generateBeneficiariesList($checklist_id, $cwacsArray, $batch_id, $category, $filename);
            } else {
                $this->generateOutOfSCHVerificationChecklist($districtsArray, $checklist_id, $cwacsArray, $batch_id, $category, $filename, $beneficiary_status);
            }
        } else if (isset($provinces) && count(json_decode($provinces)) > 0) {//schools,districts not set...now use provinces
            //get all schools in the selected provinces
            $provincesArray = json_decode($provinces);
            $cwacsArray = DB::table('cwac')->select('id')->whereIn('province_id', $provincesArray)->get();
            $cwacsArray = convertStdClassObjToArray($cwacsArray);
            $cwacsArray = convertAssArrayToSimpleArray($cwacsArray, 'id');
            $districtsArray = DB::table('districts')->select('id')->whereIn('province_id', $provincesArray)->get();
            $districtsArray = convertStdClassObjToArray($districtsArray);
            $districtsArray = convertAssArrayToSimpleArray($districtsArray, 'id');
            if ($checker == 2) {
                $this->generateBeneficiariesList($checklist_id, $cwacsArray, $batch_id, $category, $filename);
            } else {
                $this->generateOutOfSCHVerificationChecklist($districtsArray, $checklist_id, $cwacsArray, $batch_id, $category, $filename, $beneficiary_status);
            }
        } else {//nothing set....how did you find yourself here??

        }
    }

    public function printOutofSchoolSpecificChecklists(Request $request)
    {
        try {
            $batch_id = $request->input('batch_id');
            $cwac_id = $request->input('school_id');//for out of school...it's cwac_id
            $print_type = $request->input('print_type');
            $cwac_txt = $request->input('cwac_txt');
            $district_id = $request->input('district_id');
            $category = 1;
            $filename = 'OutofSchool_checklist';
            $checklist_id = DB::table('batch_checklist_types')
                ->where(array('batch_id' => $batch_id, 'category_id' => $category))
                ->value('checklist_type_id');
            if (!is_numeric($checklist_id) || $checklist_id == '') {
                echo "<p style='text-align: center;color: red'>Checklist not configured!!</p>";
                exit();
            }
            $this->generateOutOfSCHSpecificVerificationChecklist($checklist_id, $cwac_id, $batch_id, $print_type, $filename, $cwac_txt, $district_id);
        } catch (\Exception $exception) {
            echo "<p style='text-align: center;color: red'>" . $exception->getMessage() . "</p>";
            exit();
        } catch (\Throwable $throwable) {
            echo "<p style='text-align: center;color: red'>" . $throwable->getMessage() . "</p>";
            exit();
        }
    }

    public function printOutOfSchMatchingForms($batch_id, $provinces, $districts, $print_filter)
    {
        $category = 1;
        $checklist_id = 3;
        $filename = 'School_Matching';
        //todo:: generation can be per district or per province....this is a bit tricky...district supersedes provinces
        if (isset($districts) && count(json_decode($districts)) > 0) {//district selected
            $district_ids = json_decode($districts);
            $qry = DB::table('districts')
                ->leftJoin('provinces', 'districts.province_id', '=', 'provinces.id')
                ->select('districts.*', 'provinces.code as province_code', 'provinces.name as province_name')
                ->whereIn('districts.id', $district_ids);
            $districtsArray = $qry->get();
            $this->generateOutSchMatchingForm($checklist_id, $districtsArray, $batch_id, $category, $print_filter, $filename);
        } else if (isset($provinces) && count(json_decode($provinces)) > 0) {//districts not set...now use provinces
            //get all districts in the selected provinces
            $province_ids = json_decode($provinces);
            $qry = DB::table('districts')
                ->leftJoin('provinces', 'districts.province_id', '=', 'provinces.id')
                ->select('districts.*', 'provinces.code as province_code', 'provinces.name as province_name')
                ->whereIn('districts.province_id', $province_ids);
            $districtsArray = $qry->get();
            $this->generateOutSchMatchingForm($checklist_id, $districtsArray, $batch_id, $category, $print_filter, $filename);
        } else {//nothing set....how did you find yourself here??

        }
    }

    public function printExamClassesVerificationChecklists($batch_id, $provinces, $districts, $schools, $checker = 1)
    {
        $category = 3;
        $checklist_id = 4;
        $filename = 'ExamClasses_checklist';
        //todo:: generation can be per school, per district or per province....this is a bit tricky...school supersedes all, district supersedes provinces
        if (isset($schools) && count(json_decode($schools)) > 0) {//schools selected
            $schoolsArray = json_decode($schools);
            if ($checker == 2) {
                $this->generateBeneficiariesList($checklist_id, $schoolsArray, $batch_id, $category, $filename);
            } else {
                $this->generateExamClassesVerificationChecklist($checklist_id, $schoolsArray, $batch_id, $category, $filename);
            }
        } else if (isset($cwacs) && count(json_decode($cwacs)) > 0) {//CWAC selected...this must be out of school
            //get all schools in the selected districts
            $districtsArray = json_decode($districts);
            $schoolsArray = DB::table('school_information')->select('id')->whereIn('district_id', $districtsArray)->get();
            $schoolsArray = convertStdClassObjToArray($schoolsArray);
            $schoolsArray = convertAssArrayToSimpleArray($schoolsArray, 'id');
            if ($checker == 2) {
                $this->generateBeneficiariesList($checklist_id, $schoolsArray, $batch_id, $category, $filename);
            } else {
                $this->generateExamClassesVerificationChecklist($checklist_id, $schoolsArray, $batch_id, $category, $filename);
            }
        } else if (isset($districts) && count(json_decode($districts)) > 0) {//schools not set..now use districts
            //get all schools in the selected districts
            $districtsArray = json_decode($districts);
            $schoolsArray = DB::table('school_information')->select('id')->whereIn('district_id', $districtsArray)->get();
            $schoolsArray = convertStdClassObjToArray($schoolsArray);
            $schoolsArray = convertAssArrayToSimpleArray($schoolsArray, 'id');
            if ($checker == 2) {
                $this->generateBeneficiariesList($checklist_id, $schoolsArray, $batch_id, $category, $filename);
            } else {
                $this->generateInSCHVerificationChecklist($checklist_id, $schoolsArray, $batch_id, $category, $filename);
            }
        } else if (isset($provinces) && count(json_decode($provinces)) > 0) {//schools,districts not set...now use provinces
            //get all schools in the selected provinces
            $provincesArray = json_decode($provinces);
            $schoolsArray = DB::table('school_information')->select('id')->whereIn('province_id', $provincesArray)->get();
            $schoolsArray = convertStdClassObjToArray($schoolsArray);
            $schoolsArray = convertAssArrayToSimpleArray($schoolsArray, 'id');
            if ($checker == 2) {
                $this->generateBeneficiariesList($checklist_id, $schoolsArray, $batch_id, $category, $filename);
            } else {
                $this->generateInSCHVerificationChecklist($checklist_id, $schoolsArray, $batch_id, $category, $filename);
            }
        } else {//nothing set....how did you find yourself here??

        }
    }

    public function printSchPlacementForms($batch_id, $provinces, $districts, $schools, $print_filter)
    {
        $category = 3;
        $checklist_id = 5;
        $filename = 'School_Placement';
        //todo:: generation can be per school, district or per province....this is a bit tricky...schools supersedes all
        if (isset($schools) && count(json_decode($schools)) > 0) {//schools selected
            //$schoolsArray = json_decode($schools);
            $school_ids = json_decode($schools);
            $qry = DB::table('school_information')
                ->join('districts', 'school_information.district_id', '=', 'districts.id')
                ->join('provinces', 'districts.province_id', '=', 'provinces.id')
                ->select('school_information.*', 'districts.code as district_code', 'districts.name as district_name', 'provinces.name as province_name')
                ->whereIn('school_information.id', $school_ids);
            $schoolsArray = $qry->get();
            $this->generateSchPlacementForms($checklist_id, $schoolsArray, $batch_id, $category, $print_filter, $filename);
        } else if (isset($districts) && count(json_decode($districts)) > 0) {//schools not set..now use districts
            //get all schools in the selected districts
            //$districtsArray = json_decode($districts);
            $district_ids = json_decode($districts);
            //$schoolsArray = DB::table('school_information')->select('id')->whereIn('district_id', $districtsArray)->get();
            $qry = DB::table('school_information')
                ->join('districts', 'school_information.district_id', '=', 'districts.id')
                ->join('provinces', 'districts.province_id', '=', 'provinces.id')
                ->join('beneficiary_information as t1', function ($join) {
                    $join->on('school_information.id', '=', 't1.exam_school_id')
                        ->where('t1.beneficiary_status', 8);
                })
                ->select('school_information.*', 'districts.code as district_code', 'districts.name as district_name', 'provinces.name as province_name')
                ->whereIn('school_information.district_id', $district_ids)
                ->groupBy('school_information.id');
            $schoolsArray = $qry->get();
            //$schoolsArray = convertStdClassObjToArray($schoolsArray);
            //$schoolsArray = convertAssArrayToSimpleArray($schoolsArray, 'id');
            $this->generateSchPlacementForms($checklist_id, $schoolsArray, $batch_id, $category, $print_filter, $filename);
        } else if (isset($provinces) && count(json_decode($provinces)) > 0) {//schools,districts not set...now use provinces
            //get all schools in the selected provinces
            //$provincesArray = json_decode($provinces);
            $province_ids = json_decode($provinces);
            $qry = DB::table('school_information')
                ->leftJoin('districts', 'school_information.district_id', '=', 'districts.id')
                ->leftJoin('provinces', 'districts.province_id', '=', 'provinces.id')
                ->select('school_information.*', 'districts.code as district_code', 'districts.name as district_name', 'provinces.name as province_name')
                ->whereIn('school_information.province_id', $province_ids);
            $schoolsArray = $qry->get();
            // $schoolsArray = DB::table('school_information')->select('id')->whereIn('province_id', $provincesArray)->get();
            //$schoolsArray = convertStdClassObjToArray($schoolsArray);
            //$schoolsArray = convertAssArrayToSimpleArray($schoolsArray, 'id');
            $this->generateSchPlacementForms($checklist_id, $schoolsArray, $batch_id, $category, $print_filter, $filename);
        } else {//nothing set....how did you find yourself here??

        }
    }

    public function generateInSCHVerificationChecklist($checklist_id, $schoolsArray, $batch_id, $category, $filename, $beneficiary_status)
    {
        //checklist details
        $checklist_details = DB::table('checklist_types')->where('id', $checklist_id)->first();
        $checklist_name = aes_decrypt($checklist_details->name);
        $checklist_ref = $checklist_details->reference;
        $checklist_code = aes_decrypt($checklist_details->code);
        $batch_no = getSingleRecordColValue('batch_info', array('id' => $batch_id), 'batch_no');
        //school details
        foreach ($schoolsArray as $school_id) {
            //todo::get school information
            $qry = DB::table('school_information')
                ->join('districts', 'school_information.district_id', '=', 'districts.id')
                ->leftJoin('provinces', 'districts.province_id', '=', 'provinces.id')
                ->join('beneficiary_information', 'school_information.id', '=', 'beneficiary_information.school_id')
                ->select('school_information.*', 'districts.code as district_code', 'districts.name as district_name', 'provinces.name as province_name')
                ->groupBy('school_information.id')
                ->whereIn('category', array(2, 3))
                ->where('school_information.id', $school_id);
            $school_info = $qry->first();
            if (is_null($school_info)) {
                continue;
            }
            $school_code = $school_info->code;
            $school_name = aes_decrypt($school_info->name);
            $district_code = $school_info->district_code;
            $district_name = aes_decrypt($school_info->district_name);
            $province_name = aes_decrypt($school_info->province_name);
            //get TEAM ID
            $teamID = getTeamID($school_id, 1);
            $teamID = $teamID == '' ? 'TEAM ID:' : $teamID;
            //todo::get beneficiary information
            $where = array(
                'batch_id' => $batch_id,
                //'category' => $category,//accommodate both in school and examination classes
                'school_id' => $school_id
            );
            $qry = DB::table('beneficiary_information')
                ->leftJoin('districts', 'beneficiary_information.district_id', '=', 'districts.id')
                ->leftJoin('households', 'beneficiary_information.household_id', '=', 'households.id')
                ->select('beneficiary_information.*', 'districts.code as district_code2', 'districts.name as district_name2', 'households.hhh_fname', 'households.hhh_lname')
                ->where($where)
                ->whereIn('category', array(2, 3));
            if (validateisNumeric($beneficiary_status)) {
                $qry->where('beneficiary_status', $beneficiary_status);
            }
            $beneficiaries = $qry->get();
            if (is_null($beneficiaries) || count($beneficiaries) < 1) {
                continue;
            }
            $beneficiaries = convertStdClassObjToArray($beneficiaries);
            $beneficiaries = decryptArray($beneficiaries);
            //todo::get questions/checklist items
            $quizs = DB::table('checklist_items')
                ->select('name', 'order_no')
                ->where('checklist_id', $checklist_id)
                ->orderBy('order_no')
                ->get();
            $quizs = convertStdClassObjToArray($quizs);
            $quizs = decryptArray($quizs);
            $numOfQuizes = count($quizs);
            // ===========///////////////////////////////////////////////////////////////////==========//
            PDF::SetTitle('Checklist');
            PDF::SetAutoPageBreak(TRUE, 2);//true sets it to on and 0 means margin is zero from sides
            PDF::SetTitle('Checklist');
            PDF::setMargins(2, 18, 2, true);
            // PDF::setMargins(2, 18, 2, true);//frank margins
            //  PDF::setMargins(10,80,50, true);

            PDF::AddPage('L');
            // PDF::SetAutoPageBreak(TRUE, 0);//true sets it to on and 0 means margin is zero from sides
            PDF::SetFont('helvetica', '', 7);
            //headers
            $image_path = '\resources\images\kgs-logo.png';
            // Image($file, $x='', $y='', $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false)
            PDF::Image(getcwd() . $image_path, 0, 15, 30, 20, 'PNG', '', 'T', false, 300, 'R', false, false, 0, false, false, false);
            PDF::SetY(7);
            PDF::Cell(0, 2, strtoupper($teamID), 0, 1, 'R');
            PDF::SetY(3);
            PDF::Cell(0, 2, strtoupper($batch_no), 0, 1, 'C');
            PDF::SetY(2);
            //PDF::Cell(0, 30, '', 0, 1);
            PDF::Cell(0, 5, 'MINISTRY OF EDUCATION - KEEPING GIRLS IN SCHOOL PROGRAMME', 0, 1, 'L');
            //PDF::SetX(35);
            $checklist_header = '<p><b>' . $checklist_ref . '</b>: ' . strtoupper($checklist_name) . ' (School Based)</p>';
            //PDF::writeHTML($checklist_header, true, false, false, false, 'L');
            PDF::writeHTMLCell(0, 10, 10, 3, $checklist_header, 0, 1, 0, true, 'R', true);
            //PDF::writeHTMLCell(0,10,$checklist_header, 0, 0, 0, 0, 'L');
            //PDF::ln();
            //PDF::Image(getcwd() . $image_path, 15, 30, 90, 35);
            PDF::SetY(7);
            PDF::Cell(200, 5, 'Section I', 0, 1, 'L');
            //PDF::setY(57);
            $school_table = '<table border="1" width="650" cellpadding="3">
                       <tbody>
                       <tr>
                           <td>Name of School</td>
                           <td>' . $school_code . '&nbsp;-&nbsp;' . $school_name . '</td>
                       </tr>
                       <tr>
                           <td>EMIS Code</td>
                           <td>' . $district_code . '-' . $school_code . '</td>
                       </tr>
                       <tr>
                           <td>Province of School</td>
                           <td><b>' . $province_name . '</b></td>
                       </tr>
                       <tr>
                           <td>District of School</td>
                             <td>' . $district_code . '&nbsp;-&nbsp;' . $district_name . '</td>
                       </tr>
                       <tr>
                           <td>If district of school is incorrect, please write correct district name</td>
                           <td></td>
                       </tr>
                       <tr>
                           <td>Name of head teacher filling this form</td>
                           <td></td>
                       </tr>
                       <tr>
                           <td>Contact number of head teacher</td>
                           <td></td>
                       </tr>
                       </tbody></tbody>
                       </table>';
            PDF::writeHTML($school_table, true, false, false, false, 'L');
            //==========///////////////////////////////////////////////////////////////////===========//
            //==========///////////////////////////////////////////////////////////////////===========//
            $htmlTable = '
              <style>
                          table {
                                border-collapse: collapse;
                                white-space:nowrap;
                          }

                          table, th, td {
                                border: 1px solid black;
                          }
                          </style>

               <table border="1" cellpadding="2" align="center">
			   <thead>
			   <tr><td colspan="7">Section II</td><td colspan="' . $numOfQuizes . '">Section III</td></tr>
			    <tr>
			    <td colspan="7"></td>
			    <td colspan="' . $numOfQuizes . '">Note: Please fill with assistance from school/head teacher in school</td>
			    </tr>
				<tr>
		  			<td> Beneficiary ID</td>
					<td>Home District of Girl</td>
					<td>Girl First Name</td>
					<td>Girl Surname</td>
					<td>Grade of Learner(from DSW data)</td>
					<td>HH Head First Name</td>
					<td>HH Head Last Name</td>';
            PDF::SetFont('helvetica', '', 6);
            foreach ($quizs as $quiz) {
                $header = 'Q' . $quiz['order_no'] . '. ' . $quiz['name'];
                $htmlTable .= '<th>' . $header . '</th>';
            }
            $htmlTable .= '</tr></thead><tbody>';
            foreach ($beneficiaries as $beneficiary) {
                $htmlTable .= '<tr>
                             <td>' . $beneficiary['beneficiary_id'] . '</td>
                             <td>' . $beneficiary['district_code2'] . '-' . $beneficiary['district_name2'] . '</td>
                             <td>' . $beneficiary['first_name'] . '</td>
                             <td>' . $beneficiary['last_name'] . '</td>
                             <td>' . $beneficiary['current_school_grade'] . '</td>
                             <td>' . $beneficiary['hhh_fname'] . '</td>
                             <td>' . $beneficiary['hhh_lname'] . '</td>';
                for ($i = 1; $i <= $numOfQuizes; $i++) {
                    $htmlTable .= '<td></td>';
                }
                $htmlTable .= '</tr>';
            }
            $htmlTable .= '</tbody></table>';
            PDF::writeHTML($htmlTable);//, true, true, false, false, 'C'
            $footerText = '<p>*For any complaint relating to Keeping Girls in School or your welfare <b>(GBV, School Bullying or HIV)</b> 
                                please call on <b>+260953003103</b> <b>(regular call charges may apply)</b>.</p>';
            PDF::SetY(-8);
            PDF::writeHTML($footerText, true, 0, true, true);
            //==========///////////////////////////////////////////////////////////////////===========//
        }
        // $filename=$filename . time().'_'.$district_name . '.pdf';
        $dir = getcwd() . '/checklists/' . $filename;
        //PDF::Output($dir, 'F');
        PDF::Output($filename . time() . '_' . $district_name . '.pdf', 'I');
    }

    public function generateInSCHSpecificVerificationChecklist($checklist_id, $school_id, $batch_id, $print_type, $filename)
    {
        //checklist details
        $checklist_details = DB::table('checklist_types')->where('id', $checklist_id)->first();
        $checklist_name = aes_decrypt($checklist_details->name);
        $checklist_ref = $checklist_details->reference;
        $checklist_code = aes_decrypt($checklist_details->code);
        $batch_no = getSingleRecordColValue('batch_info', array('id' => $batch_id), 'batch_no');
        $whereIn = array(2, 3);
        if ($print_type == 1) {
            $whereIn = array(2);
        } else if ($print_type == 2) {
            $whereIn = array(3);
        }
        //school details
        //foreach ($schoolsArray as $school_id) {
        //todo::get school information
        $qry = DB::table('school_information')
            ->leftJoin('districts', 'school_information.district_id', '=', 'districts.id')
            ->leftJoin('provinces', 'districts.province_id', '=', 'provinces.id')
            ->select('school_information.*', 'districts.code as district_code', 'districts.name as district_name', 'provinces.name as province_name')
            ->where('school_information.id', $school_id);
        $school_info = $qry->first();
        if (is_null($school_info)) {
            print_r('Details about the school you selected could not be found!!');
            return;
        }

        $school_code = $school_info->code;
        $school_name = aes_decrypt($school_info->name);
        $district_code = $school_info->district_code;
        $district_name = aes_decrypt($school_info->district_name);
        $province_name = aes_decrypt($school_info->province_name);
        //get TEAM ID
        $teamID = getTeamID($school_id, 1);
        $teamID = $teamID == '' ? 'TEAM ID:' : $teamID;
        //todo::get beneficiary information
        $where = array(
            'batch_id' => $batch_id,
            //'category' => $category,//accommodate both in school and examination classes
            'school_id' => $school_id
        );
        $qry = DB::table('beneficiary_information')
            ->leftJoin('districts', 'beneficiary_information.district_id', '=', 'districts.id')
            ->leftJoin('households', 'beneficiary_information.household_id', '=', 'households.id')
            ->select('beneficiary_information.*', 'districts.code as district_code2', 'districts.name as district_name2', 'households.hhh_fname', 'households.hhh_lname')
            ->where($where)
            ->whereIn('category', $whereIn);
        $beneficiaries = $qry->get();
        if (is_null($beneficiaries) || count($beneficiaries) < 1) {
            print_r('No beneficiary details found on the selected school!!');
            return;
        }
        $beneficiaries = convertStdClassObjToArray($beneficiaries);
        $beneficiaries = decryptArray($beneficiaries);
        //todo::get questions/checklist items
        $quizs = DB::table('checklist_items')
            ->select('name', 'order_no')
            ->where('checklist_id', $checklist_id)
            ->orderBy('order_no')
            ->get();
        $quizs = convertStdClassObjToArray($quizs);
        $quizs = decryptArray($quizs);
        foreach ($quizs as $key => $test) {
            $quizs[$key]['header'] = $test['order_no'] . $test['name'];
        }
        $numOfQuizes = count($quizs);
        // ===========///////////////////////////////////////////////////////////////////==========//
        PDF::SetAutoPageBreak(TRUE, 2);//true sets it to on and 0 means margin is zero from sides
        PDF::SetTitle('Checklist');
        //  PDF::setMargins(10,20,10, true);
        //  PDF::setMargins(10,80,50, true);
        PDF::AddPage('L');
        PDF::SetFooterMargin(25);
        PDF::SetFont('helvetica', '', 7);
        //headers
        $image_path = '\resources\images\kgs-logo.png';
        // Image($file, $x='', $y='', $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false)
        PDF::Image(getcwd() . $image_path, 0, 15, 30, 20, 'PNG', '', 'T', false, 300, 'R', false, false, 0, false, false, false);
        PDF::SetY(7);
        PDF::Cell(0, 2, strtoupper($teamID), 0, 1, 'R');
        PDF::SetY(3);
        PDF::Cell(0, 2, strtoupper($batch_no), 0, 1, 'C');
        PDF::SetY(2);
        //PDF::Cell(0, 30, '', 0, 1);
        PDF::Cell(0, 5, 'MINISTRY OF EDUCATION - KEEPING GIRLS IN SCHOOL PROGRAMME', 0, 1, 'L');
        //PDF::SetX(35);
        $checklist_header = '<p><b>' . $checklist_ref . '</b>: ' . strtoupper($checklist_name) . ' (School Based)</p>';
        //PDF::writeHTML($checklist_header, true, false, false, false, 'L');
        PDF::writeHTMLCell(0, 10, 10, 3, $checklist_header, 0, 1, 0, true, 'R', true);
        //PDF::writeHTMLCell(0,10,$checklist_header, 0, 0, 0, 0, 'L');
        //PDF::ln();
        //PDF::Image(getcwd() . $image_path, 15, 30, 90, 35);
        PDF::SetY(7);
        PDF::Cell(200, 5, 'Section I', 0, 1, 'L');
        //PDF::setY(57);
        $school_table = '<table border="1" width="650" cellpadding="3">
                       <tbody>
                       <tr>
                           <td>Name of School</td>
                           <td>' . $school_code . '&nbsp;-&nbsp;' . $school_name . '</td>
                       </tr>
                       <tr>
                           <td>EMIS Code</td>
                           <td>' . $district_code . '-' . $school_code . '</td>
                       </tr>
                       <tr>
                           <td>Province of School</td>
                           <td><b>' . $province_name . '</b></td>
                       </tr>
                       <tr>
                           <td>District of School</td>
                             <td>' . $district_code . '&nbsp;-&nbsp;' . $district_name . '</td>
                       </tr>
                       <tr>
                           <td>If district of school is incorrect, please write correct district name</td>
                           <td></td>
                       </tr>
                       <tr>
                           <td>Name of head teacher filling this form</td>
                           <td></td>
                       </tr>
                       <tr>
                           <td>Contact number of head teacher</td>
                           <td></td>
                       </tr>
                       </tbody></tbody>
                       </table>';
        PDF::writeHTML($school_table, true, false, false, false, 'L');
        //==========///////////////////////////////////////////////////////////////////===========//
        //==========///////////////////////////////////////////////////////////////////===========//

        $htmlTable = '

                <table border="1" cellpadding="2" align="center">
			   <thead>
			   <tr><td colspan="7">Section II</td><td colspan="' . $numOfQuizes . '">Section III</td></tr>
			    <tr>
			    <td colspan="7"></td>
			    <td colspan="' . $numOfQuizes . '">Note: Please fill with assistance from school/head teacher in school</td>
			    </tr>
				<tr>
		  			<th>Beneficiary ID</th>
					<th>Home District of Girl</th>
					<th>Girl First Name</th>
					<th>Girl Surname</th>
					<th>Grade of Learner(from DSW data)</th>
					<th>HH Head First Name</th>
					<th>HH Head Last Name</th>';
        PDF::SetFont('helvetica', '', 6);
        foreach ($quizs as $quiz) {
            $header = 'Q' . $quiz['order_no'] . '. ' . $quiz['name'];
            $htmlTable .= '<th>' . $header . '</th>';
        }
        $htmlTable .= '</tr></thead><tbody>';
        foreach ($beneficiaries as $beneficiary) {
            $htmlTable .= '<tr>
                             <td>' . $beneficiary['beneficiary_id'] . '</td>
                             <td>' . $beneficiary['district_code2'] . '-' . $beneficiary['district_name2'] . '</td>
                             <td>' . $beneficiary['first_name'] . '</td>
                             <td>' . $beneficiary['last_name'] . '</td>
                             <td>' . $beneficiary['current_school_grade'] . '</td>
                             <td>' . $beneficiary['hhh_fname'] . '</td>
                             <td>' . $beneficiary['hhh_lname'] . '</td>';
            for ($i = 1; $i <= $numOfQuizes; $i++) {
                $htmlTable .= '<td></td>';
            }
            $htmlTable .= '</tr>';
        }
        $htmlTable .= '</tbody></table>';

        PDF::writeHTML($htmlTable, true, false, false, false, 'C');
        $footerText = '<p>*For any complaint relating to Keeping Girls in School or your welfare <b>(GBV, School Bullying or HIV)</b> 
                                please call on <b>+260953003103</b> <b>(regular call charges may apply)</b>.</p>';
        PDF::SetY(-8);
        PDF::writeHTML($footerText, true, 0, true, true);
        /* PDF::SetFont('', 'OI', 8);
         $user = aes_decrypt(\Auth::user()->first_name) . ' ' . aes_decrypt(\Auth::user()->last_name);
         PDF::SetY(200);
         PDF::Cell(0, 2, 'Printed by ' . $user . ' on ' . date('d/m/Y'), 0, 1, 'R');*/
        //==========///////////////////////////////////////////////////////////////////===========//
        // }

        PDF::Output($filename . time() . '.pdf', 'I');
    }

    public function generateOutOfSCHVerificationChecklist($districts, $checklist_id, $cwacsArray, $batch_id, $category, $filename, $beneficiary_status)
    {
        //todo::checklist details
        $checklist_details = DB::table('checklist_types')->where('id', $checklist_id)->first();
        $checklist_name = aes_decrypt($checklist_details->name);
        $checklist_ref = $checklist_details->reference;
        $checklist_code = aes_decrypt($checklist_details->code);
        $batch_no = getSingleRecordColValue('batch_info', array('id' => $batch_id), 'batch_no');
        //todo::get questions/checklist items
        $quizs = DB::table('checklist_items')->select('name', 'order_no')->where('checklist_id', $checklist_id)->get();
        $quizs = convertStdClassObjToArray($quizs);
        $quizs = decryptArray($quizs);
        $numOfQuizes = count($quizs);
        //todo::cwacs/beneficiaries
        $mainQry = DB::table('beneficiary_information as t1')
            ->join('districts as t2', 't1.district_id', '=', 't2.id')
            ->join('provinces as t3', 't1.province_id', '=', 't3.id');
        $cwacsQry = clone $mainQry;
        $cwacsQry->select(DB::raw("t1.cwac_txt,t1.district_id,CONCAT_WS('-',t2.code,t2.name) as district_name,CONCAT_WS('-',t3.code,t3.name) as province_name"))
            ->whereIn('t1.district_id', $districts)
            ->groupBy('t1.cwac_txt');
        $cwacs = $cwacsQry->get();

        $district_name = 'district_name';

        foreach ($cwacs as $cwac) {
            $cwac_txt = $cwac->cwac_txt;
            $district_name = $cwac->district_name;
            $province_name = $cwac->province_name;

            //todo::get beneficiary information
            $where = array(
                'batch_id' => $batch_id,
                'category' => $category,
                't1.cwac_txt' => $cwac_txt
            );
            $beneficiariesQry = clone $mainQry;
            $beneficiariesQry->join('households as t4', 't1.household_id', '=', 't4.id')
                ->select('t1.*', 't2.code as district_code2', 't2.name as district_name2', 't4.hhh_fname', 't4.hhh_lname', 't4.hhh_nrc_number')
                ->where($where);
            if (validateisNumeric($beneficiary_status)) {
                $beneficiariesQry->where('t1.beneficiary_status', $beneficiary_status);
            }
            $beneficiaries = $beneficiariesQry->get();
            if (is_null($beneficiaries) || count($beneficiaries) < 1) {
                continue;
            }
            $beneficiaries = convertStdClassObjToArray($beneficiaries);
            $beneficiaries = decryptArray($beneficiaries);

            // ===========///////////////////////////////////////////////////////////////////==========//
            PDF::SetTitle('Checklist');
            PDF::setMargins(2, 14, 2, true);
            //PDF::SetAutoPageBreak(TRUE, 4);//true sets it to on and 0 means margin is zero from sides

            PDF::AddPage('L');
            PDF::SetFont('helvetica', '', 7);
            //headers
            $image_path = '\resources\images\kgs-logo.png';
            // Image($file, $x='', $y='', $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false)
            PDF::Image(getcwd() . $image_path, 0, 15, 30, 20, 'PNG', '', 'T', false, 300, 'R', false, false, 0, false, false, false);
            PDF::SetY(7);
            //PDF::Cell(0, 2, strtoupper($teamID), 0, 1, 'R');
            PDF::SetY(3);
            PDF::Cell(0, 2, strtoupper($batch_no), 0, 1, 'C');
            PDF::SetY(2);
            //PDF::Cell(0, 30, '', 0, 1);
            PDF::Cell(0, 5, 'MINISTRY OF EDUCATION - KEEPING GIRLS IN SCHOOL PROGRAMME', 0, 1, 'L');
            $checklist_header = '<p><b>' . $checklist_ref . '</b>: ' . strtoupper($checklist_name) . ' (Community Based)</p>';
            //PDF::writeHTML($checklist_header, true, false, false, false, 'L');
            PDF::writeHTMLCell(0, 10, 10, 3, $checklist_header, 0, 1, 0, true, 'R', true);
            //PDF::writeHTMLCell(0,10,$checklist_header, 0, 0, 0, 0, 'L');
            //PDF::ln();
            //PDF::Image(getcwd() . $image_path, 15, 30, 90, 35);
            PDF::SetY(7);
            PDF::Cell(200, 5, 'Section I', 0, 1, 'L');
            //PDF::setY(57);
            $cwac_table = '<table border="1" width="650" cellpadding="3">
                       <tbody>
                       <tr>
                           <td>Name of CWAC</td>
                           <td>' . $cwac_txt . '</td>
                       </tr>
                       <tr>
                           <td>Province of CWAC</td>
                           <td><b>' . $province_name . '</b></td>
                       </tr>
                       <tr>
                           <td>District of CWAC</td>
                             <td>' . $district_name . '</td>
                       </tr>
                       <tr>
                           <td>If district of CWAC is incorrect, please write correct district name</td>
                           <td></td>
                       </tr>
                       <tr>
                           <td>CWAC Contact person</td>
                           <td></td>
                       </tr>
                       <tr>
                           <td>CWAC Contact person Phone No</td>
                           <td></td>
                       </tr>
                       </tbody></tbody>
                       </table>';
            PDF::writeHTML($cwac_table, true, false, false, false, 'L');
            //==========///////////////////////////////////////////////////////////////////===========//
            $htmlTable = '
                         <style>
                          table {
                                border-collapse: collapse;
                                white-space:nowrap;
                          }

                          table, th, td {
                                border: 1px solid black;
                          }
                          </style>
              <table border="1" cellpadding="2" align="center">
			   <thead>
			   <tr><td colspan="8">Section II</td><td colspan="' . $numOfQuizes . '">Section III</td></tr>
			    <tr>
			    <td colspan="8"></td>
			    <td colspan="' . $numOfQuizes . '">Note: Please fill with assistance from CWAC contact person</td>
			    </tr>
				<tr>
		  			<th> Beneficiary ID</th>
					<th>Home District of Girl</th>
					<th>Girl First Name</th>
					<th>Girl Surname</th>
					<th>Grade of Learner(from DSW data)</th>
					<th>HH Head First Name</th>
					<th>HH Head Last Name</th>
					<th>HHH NRC Number</th>';
            PDF::SetFont('helvetica', '', 6);
            foreach ($quizs as $quiz) {
                $header = 'Q' . $quiz['order_no'] . '. ' . $quiz['name'];
                $htmlTable .= '<th>' . $header . '</th>';
            }
            $htmlTable .= '</tr></thead><tbody>';
            foreach ($beneficiaries as $beneficiary) {
                $htmlTable .= '<tr>
                             <td>' . $beneficiary['beneficiary_id'] . '</td>
                             <td>' . $beneficiary['district_code2'] . '-' . $beneficiary['district_name2'] . '</td>
                             <td>' . $beneficiary['first_name'] . '</td>
                             <td>' . $beneficiary['last_name'] . '</td>
                             <td>' . $beneficiary['current_school_grade'] . '</td>
                             <td>' . $beneficiary['hhh_fname'] . '</td>
                             <td>' . $beneficiary['hhh_lname'] . '</td>
                             <td>' . $beneficiary['hhh_nrc_number'] . '</td>';
                for ($i = 1; $i <= $numOfQuizes; $i++) {
                    $htmlTable .= '<td></td>';
                }
                $htmlTable .= '</tr>';
            }
            $htmlTable .= '</tbody></table>';
            PDF::writeHTML($htmlTable, true, false, false, false, 'C');
            //==========///////////////////////////////////////////////////////////////////===========//
            $footerText = '<p>*For any complaint relating to Keeping Girls in School or your welfare <b>(GBV, School Bullying or HIV)</b> 
                                please call on <b>+260953003103</b> <b>(regular call charges may apply)</b>.</p>';
            PDF::SetY(-8);
            PDF::writeHTML($footerText, true, 0, true, true);
        }
        PDF::Output($filename . time() . '_' . $district_name . '.pdf', 'I');
        exit();
    }

    public function generateOutOfSCHSpecificVerificationChecklist($checklist_id, $cwac_id, $batch_id, $print_type, $filename, $cwac_txt, $district_id)
    {
        //checklist details
        $checklist_details = DB::table('checklist_types')->where('id', $checklist_id)->first();
        $checklist_name = aes_decrypt($checklist_details->name);
        $checklist_ref = $checklist_details->reference;
        $checklist_code = aes_decrypt($checklist_details->code);
        $batch_no = getSingleRecordColValue('batch_info', array('id' => $batch_id), 'batch_no');
        $qry = DB::table('districts as t1')
            ->join('provinces as t2', 't1.province_id', '=', 't2.id')
            ->select('t1.code as district_code', 't1.name as district_name', 't2.name as province_name')
            ->where('t1.id', $district_id);
        $cwac_info = $qry->first();
        if (is_null($cwac_info)) {
            print_r('Details about the CWAC you selected could not be found!!');
            return;
        }
        //
        $cwac_name = $cwac_txt;
        $district_code = $cwac_info->district_code;
        $district_name = $cwac_info->district_name;
        $province_name = $cwac_info->province_name;
        //get TEAM ID
        $teamID = getTeamID($cwac_id, 2);
        $teamID = $teamID == '' ? 'TEAM ID:' : $teamID;
        //todo::get beneficiary information
        $where = array(
            'batch_id' => $batch_id,
            'category' => 1,
            'beneficiary_information.cwac_txt' => $cwac_txt
        );
        $qry = DB::table('beneficiary_information')
            ->leftJoin('districts', 'beneficiary_information.district_id', '=', 'districts.id')
            ->leftJoin('households', 'beneficiary_information.household_id', '=', 'households.id')
            ->select('beneficiary_information.*', 'districts.code as district_code2', 'districts.name as district_name2', 'households.hhh_fname', 'households.hhh_lname', 'households.hhh_nrc_number')
            ->where($where);
        $beneficiaries = $qry->get();
        if (is_null($beneficiaries) || count($beneficiaries) < 1) {
            print_r('No beneficiary details found on the selected CWAC!!');
            return;
        }
        $beneficiaries = convertStdClassObjToArray($beneficiaries);
        $beneficiaries = decryptArray($beneficiaries);
        //todo::get questions/checklist items
        $quizs = DB::table('checklist_items')->select('name', 'order_no')->where('checklist_id', $checklist_id)->get();
        $quizs = convertStdClassObjToArray($quizs);
        $quizs = decryptArray($quizs);
        $numOfQuizes = count($quizs);
        // ===========///////////////////////////////////////////////////////////////////==========//
        PDF::SetTitle('Checklist');
        PDF::AddPage('L');
        PDF::SetAutoPageBreak(TRUE, 0);//true sets it to on and 0 means margin is zero from sides
        PDF::SetFont('helvetica', '', 7);
        //headers
        $image_path = '\resources\images\kgs-logo.png';
        // Image($file, $x='', $y='', $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false)
        PDF::Image(getcwd() . $image_path, 0, 15, 30, 20, 'PNG', '', 'T', false, 300, 'R', false, false, 0, false, false, false);
        PDF::SetY(7);
        PDF::Cell(0, 2, strtoupper($teamID), 0, 1, 'R');
        PDF::SetY(3);
        PDF::Cell(0, 2, strtoupper($batch_no), 0, 1, 'C');
        PDF::SetY(2);
        //PDF::Cell(0, 30, '', 0, 1);
        PDF::Cell(0, 5, 'MINISTRY OF EDUCATION - KEEPING GIRLS IN SCHOOL PROGRAMME', 0, 1, 'L');
        //PDF::SetX(35);
        $checklist_header = '<p><b>' . $checklist_ref . '</b>: ' . strtoupper($checklist_name) . ' (Community Based)</p>';
        //PDF::writeHTML($checklist_header, true, false, false, false, 'L');
        PDF::writeHTMLCell(0, 10, 10, 3, $checklist_header, 0, 1, 0, true, 'R', true);
        //PDF::writeHTMLCell(0,10,$checklist_header, 0, 0, 0, 0, 'L');
        //PDF::ln();
        //PDF::Image(getcwd() . $image_path, 15, 30, 90, 35);
        PDF::SetY(7);
        PDF::Cell(200, 5, 'Section I', 0, 1, 'L');
        //PDF::setY(57);
        $cwac_table = '<table border="1" width="650" cellpadding="3">
                       <tbody>
                       <tr>
                           <td>Name of CWAC</td>
                           <td>' . $cwac_name . '</td>
                       </tr>
                       <tr>
                           <td>Province of CWAC</td>
                           <td><b>' . $province_name . '</b></td>
                       </tr>
                       <tr>
                           <td>District of CWAC</td>
                             <td>' . $district_code . '&nbsp;-&nbsp;' . $district_name . '</td>
                       </tr>
                       <tr>
                           <td>If district of CWAC is incorrect, please write correct district name</td>
                           <td></td>
                       </tr>
                       <tr>
                           <td>CWAC Contact person</td>
                           <td></td>
                       </tr>
                       <tr>
                           <td>CWAC Contact person Phone No</td>
                           <td></td>
                       </tr>
                       </tbody></tbody>
                       </table>';
        PDF::writeHTML($cwac_table, true, false, false, false, 'L');
        //==========///////////////////////////////////////////////////////////////////===========//
        //==========///////////////////////////////////////////////////////////////////===========//
        $htmlTable = '
                         <style>
                          table {
                                border-collapse: collapse;
                                white-space:nowrap;
                          }

                          table, th, td {
                                border: 1px solid black;
                          }
                          </style>
              <table border="1" cellpadding="2" align="center">
			   <thead>
			   <tr><td colspan="8">Section II</td><td colspan="' . $numOfQuizes . '">Section III</td></tr>
			    <tr>
			    <td colspan="8"></td>
			    <td colspan="' . $numOfQuizes . '">Note: Please fill with assistance from CWAC contact person</td>
			    </tr>
				<tr>
		  			<th> Beneficiary ID</th>
					<th>Home District of Girl</th>
					<th>Girl First Name</th>
					<th>Girl Surname</th>
					<th>Grade of Learner(from DSW data)</th>
					<th>HH Head First Name</th>
					<th>HH Head Last Name</th>
					<th>HHH NRC Number</th>';
        PDF::SetFont('helvetica', '', 6);
        foreach ($quizs as $quiz) {
            $header = 'Q' . $quiz['order_no'] . '. ' . $quiz['name'];
            $htmlTable .= '<th>' . $header . '</th>';
        }
        $htmlTable .= '</tr></thead><tbody>';
        foreach ($beneficiaries as $beneficiary) {
            $htmlTable .= '<tr>
                             <td>' . $beneficiary['beneficiary_id'] . '</td>
                             <td>' . $beneficiary['district_code2'] . '-' . $beneficiary['district_name2'] . '</td>
                             <td>' . $beneficiary['first_name'] . '</td>
                             <td>' . $beneficiary['last_name'] . '</td>
                             <td>' . $beneficiary['current_school_grade'] . '</td>
                             <td>' . $beneficiary['hhh_fname'] . '</td>
                             <td>' . $beneficiary['hhh_lname'] . '</td>
                             <td>' . $beneficiary['hhh_nrc_number'] . '</td>';
            for ($i = 1; $i <= $numOfQuizes; $i++) {
                $htmlTable .= '<td></td>';
            }
            $htmlTable .= '</tr>';
        }
        $htmlTable .= '</tbody></table>';
        PDF::writeHTML($htmlTable, true, false, false, false, 'C');
        //==========///////////////////////////////////////////////////////////////////===========//
        $footerText = '<p>*For any complaint relating to Keeping Girls in School or your welfare <b>(GBV, School Bullying or HIV)</b> 
                please call on <b>+260953003103</b> <b>(regular call charges may apply)</b>.</p>';
        PDF::SetY(-8);
        PDF::writeHTML($footerText, true, 0, true, true);

        PDF::Output($filename . time() . '.pdf', 'I');
    }

    //school matching forms here
    public function generateOutSchMatchingForm($checklist_id, $districtsArray, $batch_id, $category, $print_filter, $filename)
    {
        //checklist details
        $checklist_details = DB::table('checklist_types')->where('id', $checklist_id)->first();
        $checklist_name = aes_decrypt($checklist_details->name);
        $checklist_ref = $checklist_details->reference;
        $stage = 5;
        //todo::get questions/checklist items
        $quizs = DB::table('checklist_items')
            ->select('name', 'order_no')
            ->where('checklist_id', $checklist_id)
            ->orderBy('order_no')
            ->get();
        $numOfQuizes = count($quizs);
        foreach ($districtsArray as $district_info) {
            $district_code = $district_info->code;
            $district_name = $district_info->name;
            $province_code = $district_info->province_code;
            $province_name = $district_info->province_name;
            //todo::get beneficiary information
            $where = array(
                'batch_id' => $batch_id,
                'category' => $category,
                't1.district_id' => $district_info->id,
                't1.beneficiary_status' => $stage,
                't1.verification_recommendation' => 1
            );
            $qry = DB::table('beneficiary_information as t1')
                ->leftJoin('households', 't1.household_id', '=', 'households.id')
                //->leftJoin('cwac', 't1.cwac_id', '=', 'cwac.id')
                ->leftJoin('wards', 't1.ward_id', '=', 'wards.id')
                ->leftJoin('constituencies', 't1.constituency_id', '=', 'constituencies.id')
                ->select(DB::raw('t1.id,t1.beneficiary_id,CASE WHEN decrypt(t1.first_name) IS NULL THEN t1.first_name ELSE decrypt(t1.first_name) END as first_name, CASE WHEN decrypt(t1.last_name) IS NULL THEN t1.last_name ELSE decrypt(t1.last_name) END as last_name,t1.dob,t1.highest_grade,
                                  t1.cwac_txt,households.hhh_fname, households.hhh_lname, wards.name as ward_name, wards.code as ward_code, constituencies.name as constituency_name, constituencies.code as constituency_code'))
                //->select('beneficiary_information.*', 'households.hhh_fname', 'households.hhh_lname', 'cwac.name as cwac_name', 'cwac.code as cwac_code', 'wards.name as ward_name', 'wards.code as ward_code', 'constituencies.name as constituency_name', 'constituencies.code as constituency_code')
                ->where($where);
            if (isset($print_filter) && $print_filter != '') {
                if ($print_filter == 1) {
                    $qry->where('t1.matching_form_printed', 1);
                } else if ($print_filter == 2) {
                    $qry->where('t1.matching_form_printed', '<>', 1)
                        ->orWhere(function ($query) {
                            $query->whereNull('t1.matching_form_printed');
                        });
                }
            }
            //$qry->orderBy('cwac.id');
            $qry->orderBy('t1.cwac_txt');
            $beneficiaries = $qry->get();
            if (is_null($beneficiaries) || count($beneficiaries) < 1) {
                continue;
            }
            // ===========///////////////////////////////////////////////////////////////////==========//
            PDF::SetTitle('Matching Form');
            PDF::SetAutoPageBreak(TRUE, 2);//true sets it to on and 0 means margin is zero from sides
            PDF::SetTitle('Checklist');
            PDF::setMargins(10, 10, 10, true);

            PDF::AddPage('L');
            PDF::SetFont('times', '', 7);
            //headers
            $image_path = '\resources\images\kgs-logo.png';
            // Image($file, $x='', $y='', $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false)
            PDF::Image(getcwd() . $image_path, 0, 9, 40, 30, 'PNG', '', 'T', false, 300, 'R', false, false, 0, false, false, false);
            PDF::SetY(5);
            //PDF::Cell(0, 30, '', 0, 1);
            PDF::Cell(0, 5, 'MINISTRY OF EDUCATION - KEEPING GIRLS IN SCHOOL PROGRAMME', 0, 1, 'L');
            //PDF::SetX(35);
            $checklist_header = '<p><b>' . $checklist_ref . '</b>: ' . strtoupper($checklist_name) . '</p>';
            //PDF::writeHTML($checklist_header, true, false, false, false, 'L');
            PDF::writeHTMLCell(0, 10, 10, 10, $checklist_header, 0, 1, 0, true, 'L', true);
            PDF::SetY(15);
            PDF::Cell(200, 5, 'Section I', 0, 1, 'L');
            //PDF::setY(57);
            $district_table = '<table border="1" width="650" cellpadding="3">
                       <tbody>
                       <tr>
                           <td>Name of District</td>
                           <td>' . $district_code . '&nbsp;-&nbsp;' . $district_name . '</td>
                       </tr>
                       <tr>
                           <td>Name of DEBS Officer filling this form</td>
                           <td></td>
                       </tr>
                       <tr>
                           <td>Contact Number of DEBS Officer</td>
                           <td><b></b></td>
                       </tr>
                       </tbody></tbody>
                       </table>';
            PDF::writeHTML($district_table, true, false, false, false, 'L');
            //==========///////////////////////////////////////////////////////////////////===========//
            //==========///////////////////////////////////////////////////////////////////===========//
            $htmlTable = '
                         <style>
                          table {
                                border-collapse: collapse;
                                white-space:nowrap;
                          }

                          table, th, td {
                                border: 1px solid black;
                          }

                          </style>
              <table border="1" cellpadding="2" align="center">
			   <thead>
			   <tr><td colspan="10">Section II</td><td colspan="' . $numOfQuizes . '">Section III</td></tr>
			    <tr>
			    <td colspan="10"></td>
			    <td colspan="' . $numOfQuizes . '">Note: This "Matching" section needs to be filled by the DEBS</td>
			    </tr>
				<tr>
				    <th>Name of District</th>
				    <th>Constituency</th>
				    <th>Ward</th>
				    <th>CWAC</th>
		  			<th>Beneficiary ID</th>
					<th>Girl First Name</th>
					<th>Girl Last Name</th>
					<th>HH Head First Name</th>
					<th>HH Head Last Name</th>
					<th>Highest Grade</th>';
            foreach ($quizs as $quiz) {
                $header = 'Q' . $quiz->order_no . '. ' . $quiz->name;
                $htmlTable .= '<th>' . $header . '</th>';
            }
            $htmlTable .= '</tr></thead><tbody>';
            $printed_array = array();
            foreach ($beneficiaries as $beneficiary) {
                $htmlTable .= '<tr>
                             <td>' . $district_code . '-' . $district_name . '</td>
                             <td>' . $beneficiary->constituency_code . '-' . $beneficiary->constituency_name . '</td>
                             <td>' . $beneficiary->ward_code . '-' . $beneficiary->ward_name . '</td>
                             <td>' . $beneficiary->cwac_txt . '</td>
                             <td>' . $beneficiary->beneficiary_id . '</td>
                             <td>' . $beneficiary->first_name . '</td>
                             <td>' . $beneficiary->last_name . '</td>
                             <td>' . $beneficiary->hhh_fname . '</td>
                             <td>' . $beneficiary->hhh_lname . '</td>
                             <td>' . $beneficiary->highest_grade . '</td>';
                for ($i = 1; $i <= $numOfQuizes; $i++) {
                    $htmlTable .= '<td></td>';
                }
                $htmlTable .= '</tr>';
                $printed_array[] = array(
                    'id' => $beneficiary->id
                );
            }
            $printed_IDs = convertAssArrayToSimpleArray($printed_array, 'id');
            DB::table('beneficiary_information')
                ->whereIn('id', $printed_IDs)
                ->update(array('matching_form_printed' => 1));
            $htmlTable .= '</tbody></table>';
            PDF::writeHTML($htmlTable, true, false, false, false, 'C');
            //==========///////////////////////////////////////////////////////////////////===========//
            $footerText = '<p>*For any complaint relating to Keeping Girls in School or your welfare <b>(GBV, School Bullying or HIV)</b> 
                please call on <b>+260953003103</b> <b>(regular call charges may apply)</b>.</p>';
            PDF::SetY(-8);
            PDF::writeHTML($footerText, true, 0, true, true);
        }
        //PDF::Output($filename . time() . '.pdf', 'I');
        PDF::Output($filename . time() . '_' . $district_name . '.pdf', 'I');
    }

    public function generateExamClassesVerificationChecklist($checklist_id, $schoolsArray, $batch_id, $category, $filename)
    {
        //checklist details
        $checklist_details = DB::table('checklist_types')->where('id', $checklist_id)->first();
        $checklist_name = aes_decrypt($checklist_details->name);
        $checklist_ref = $checklist_details->reference;
        $checklist_code = aes_decrypt($checklist_details->code);
        //school details
        foreach ($schoolsArray as $school_id) {
            //todo::get school information
            $qry = DB::table('school_information')
                ->leftJoin('districts', 'school_information.district_id', '=', 'districts.id')
                ->leftJoin('provinces', 'districts.province_id', '=', 'provinces.id')
                ->select('school_information.*', 'districts.code as district_code', 'districts.name as district_name', 'provinces.name as province_name')
                ->where('school_information.id', $school_id);
            $school_info = $qry->first();
            $school_code = $school_info->code;
            $school_name = aes_decrypt($school_info->name);
            $district_code = $school_info->district_code;
            $district_name = aes_decrypt($school_info->district_name);
            $province_name = aes_decrypt($school_info->province_name);
            //todo::get beneficiary information
            $where = array(
                'batch_id' => $batch_id,
                'category' => $category,
                'school_id' => $school_id
            );
            $qry = DB::table('beneficiary_information')
                ->leftJoin('districts', 'beneficiary_information.district_id', '=', 'districts.id')
                ->leftJoin('households', 'beneficiary_information.household_id', '=', 'households.id')
                ->select('beneficiary_information.*', 'districts.code as district_code2', 'districts.name as district_name2', 'households.hhh_fname', 'households.hhh_lname')
                ->where($where);
            $beneficiaries = $qry->get();
            if (is_null($beneficiaries) || count($beneficiaries) < 1) {
                continue;
            }
            $beneficiaries = convertStdClassObjToArray($beneficiaries);
            $beneficiaries = decryptArray($beneficiaries);
            //todo::get questions/checklist items
            $quizs = DB::table('checklist_items')->select('name', 'order_no')->where('checklist_id', $checklist_id)->get();
            $quizs = convertStdClassObjToArray($quizs);
            $quizs = decryptArray($quizs);
            $numOfQuizes = count($quizs);
            // ===========///////////////////////////////////////////////////////////////////==========//
            PDF::SetTitle('Checklist');
            PDF::AddPage('L');
            PDF::SetAutoPageBreak(TRUE, 0);//true sets it to on and 0 means margin is zero from sides
            PDF::SetFont('helvetica', '', 9);
            //headers
            $image_path = '\resources\images\kgs-logo.png';
            // Image($file, $x='', $y='', $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false)
            PDF::Image(getcwd() . $image_path, 0, 26, 40, 30, 'PNG', '', 'T', false, 300, 'R', false, false, 0, false, false, false);
            PDF::SetY(7);
            PDF::Cell(0, 2, 'TEAM ID: A', 0, 1, 'R');
            PDF::SetY(5);
            //PDF::Cell(0, 30, '', 0, 1);
            PDF::Cell(0, 5, 'MINISTRY OF EDUCATION - KEEPING GIRLS IN SCHOOL PROGRAMME', 0, 1, 'L');
            //PDF::SetX(35);
            $checklist_header = '<p><b>' . $checklist_ref . '</b>: ' . strtoupper($checklist_name) . '</p>';
            //PDF::writeHTML($checklist_header, true, false, false, false, 'L');
            PDF::writeHTMLCell(0, 10, 10, 10, $checklist_header, 0, 1, 0, true, 'L', true);
            //PDF::writeHTMLCell(0,10,$checklist_header, 0, 0, 0, 0, 'L');
            //PDF::ln();
            //PDF::Image(getcwd() . $image_path, 15, 30, 90, 35);
            //PDF::SetY(50);
            PDF::Cell(200, 5, 'Section I', 0, 1, 'L');
            //PDF::setY(57);
            $school_table = '<table border="1" width="650" cellpadding="3">
                       <tbody>
                       <tr>
                           <td>Name of School</td>
                           <td>' . $school_code . '&nbsp;-&nbsp;' . $school_name . '</td>
                       </tr>
                       <tr>
                           <td>EMIS Code</td>
                           <td>' . $district_code . '-' . $school_code . '</td>
                       </tr>
                       <tr>
                           <td>Province of School</td>
                           <td><b>' . $province_name . '</b></td>
                       </tr>
                       <tr>
                           <td>District of School</td>
                             <td>' . $district_code . '&nbsp;-&nbsp;' . $district_name . '</td>
                       </tr>
                       <tr>
                           <td>If district of school is incorrect, please write correct district name</td>
                           <td></td>
                       </tr>
                       <tr>
                           <td>Name of head teacher filling this form</td>
                           <td></td>
                       </tr>
                       <tr>
                           <td>Contact number of head teacher</td>
                           <td></td>
                       </tr>
                       </tbody></tbody>
                       </table>';
            PDF::writeHTML($school_table, true, false, false, false, 'L');
            //==========///////////////////////////////////////////////////////////////////===========//
            //==========///////////////////////////////////////////////////////////////////===========//
            $htmlTable = '
                         <style>
                          table {
                                border-collapse: collapse;
                                white-space:nowrap;
                          }

                          table, th, td {
                                border: 1px solid black;
                          }
                          </style>
              <table border="1" cellpadding="2" align="center">
			   <thead>
			   <tr><td colspan="7">Section II</td><td colspan="' . $numOfQuizes . '">Section III</td></tr>
			    <tr>
			    <td colspan="7"></td>
			    <td colspan="' . $numOfQuizes . '">Note: Please fill with assistance from school/head teacher in school</td>
			    </tr>
				<tr>
		  			<th> Beneficiary ID</th>
					<th>Home District of Girl</th>
					<th>Girl First Name</th>
					<th>Girl Surname</th>
					<th>Grade of Learner(from DSW data)</th>
					<th>HH Head First Name</th>
					<th>HH Head Last Name</th>';
            foreach ($quizs as $quiz) {
                $header = 'Q' . $quiz['order_no'] . '. ' . $quiz['name'];
                $htmlTable .= '<th>' . $header . '</th>';
            }
            $htmlTable .= '</tr></thead><tbody>';
            foreach ($beneficiaries as $beneficiary) {
                $htmlTable .= '<tr>
                             <td>' . $beneficiary['beneficiary_id'] . '</td>
                             <td>' . $beneficiary['district_code2'] . '-' . $beneficiary['district_name2'] . '</td>
                             <td>' . $beneficiary['first_name'] . '</td>
                             <td>' . $beneficiary['last_name'] . '</td>
                             <td>' . $beneficiary['current_school_grade'] . '</td>
                             <td>' . $beneficiary['hhh_fname'] . '</td>
                             <td>' . $beneficiary['hhh_lname'] . '</td>';
                for ($i = 1; $i <= $numOfQuizes; $i++) {
                    $htmlTable .= '<td></td>';
                }
                $htmlTable .= '</tr>';
            }
            $htmlTable .= '</tbody></table>';
            PDF::writeHTML($htmlTable, true, false, false, false, 'C');
            PDF::SetFont('', 'OI', 8);
            $user = aes_decrypt(\Auth::user()->first_name) . ' ' . aes_decrypt(\Auth::user()->last_name);
            PDF::SetY(200);
            PDF::Cell(0, 2, 'Printed by ' . $user . ' on ' . date('d/m/Y'), 0, 1, 'R');
            //==========///////////////////////////////////////////////////////////////////===========//
            $footerText = '<p>*For any complaint relating to Keeping Girls in School or your welfare <b>(GBV, School Bullying or HIV)</b> 
                please call on <b>+260953003103</b> <b>(regular call charges may apply)</b>.</p>';
            PDF::SetY(-8);
            PDF::writeHTML($footerText, true, 0, true, true);
        }
        PDF::Output($filename . time() . '.pdf', 'I');
    }

    //Placement forms
    public function generateSchPlacementForms($checklist_id, $schoolsArray, $batch_id, $category, $print_filter, $filename)
    {
        //checklist details
        $checklist_details = DB::table('checklist_types')->where('id', $checklist_id)->first();
        $checklist_name = aes_decrypt($checklist_details->name);
        $checklist_ref = $checklist_details->reference;
        $checklist_code = aes_decrypt($checklist_details->code);
        //todo::get questions/checklist items
        $quizs = DB::table('checklist_items')
            ->select('name', 'order_no')
            ->where('checklist_id', $checklist_id)
            ->orderBy('order_no')
            ->get();
        $numOfQuizes = count($quizs);
        foreach ($schoolsArray as $school_info) {
            $school_code = $school_info->code;
            $school_name = $school_info->name;
            $district_code = $school_info->district_code;
            $district_name = $school_info->district_name;
            $province_name = $school_info->province_name;
            $beneficiary_status = 8;//$stage;
            //todo::get beneficiary information
            $school_id = $school_info->id;
            $where = array(
                't1.batch_id' => $batch_id,
                //'t1.category' => $category,
                't1.exam_school_id' => $school_id,
                't1.beneficiary_status' => $beneficiary_status,
                't1.verification_recommendation' => 1
            );
            $qry = DB::table('beneficiary_information as t1')
                ->leftJoin('households', 't1.household_id', '=', 'households.id')
                ->leftJoin('cwac', 't1.cwac_id', '=', 'cwac.id')
                ->leftJoin('wards', 't1.ward_id', '=', 'wards.id')
                ->leftJoin('constituencies', 't1.constituency_id', '=', 'constituencies.id')
                // ->select(DB::raw('t1.id,t1.beneficiary_id,t1.exam_number,decrypt(t1.first_name) as first_name, decrypt(t1.last_name) as last_name,t1.dob,t1.exam_grade,t1.current_school_grade,
                //                   households.hhh_fname,households.hhh_lname,cwac.name as cwac_name,cwac.code as cwac_code,wards.name as ward_name,wards.code as ward_code,constituencies.name as constituency_name,constituencies.code as constituency_code'))
                ->select(DB::raw("t1.id,t1.beneficiary_id,t1.exam_number,decrypt(t1.first_name) as first_name, decrypt(t1.last_name) as last_name,t1.dob,t1.exam_grade,t1.current_school_grade,
                households.hhh_fname,households.hhh_lname,SUBSTRING_INDEX(t1.cwac_txt, '-', -1) as cwac_name,SUBSTRING_INDEX(t1.cwac_txt, '-', 1) as cwac_code,wards.name as ward_name,wards.code as ward_code,constituencies.name as constituency_name,constituencies.code as constituency_code"))
                ->where($where);
                
            if (isset($print_filter) && $print_filter != '') {
                if ($print_filter == 1) {
                    $qry->where('t1.placement_form_printed', 1);
                } else if ($print_filter == 2) {
                    $qry->where('t1.placement_form_printed', '<>', 1)
                        ->orWhere(function ($query) {
                            $query->whereNull('t1.placement_form_printed');
                        });
                }
            }
            $qry->orderBy('t1.exam_school_id');
            $beneficiaries = $qry->get();
            if (is_null($beneficiaries) || count($beneficiaries) < 1) {
                continue;
            }
            // ===========///////////////////////////////////////////////////////////////////==========//
            PDF::SetTitle('Placement Form');
            PDF::SetAutoPageBreak(TRUE, 2);//true sets it to on and 0 means margin is zero from sides
            PDF::SetTitle('Checklist');
            PDF::setMargins(10, 10, 10, true);

            PDF::AddPage('L');
            PDF::SetFont('times', '', 7);
            //headers
            $image_path = '\resources\images\kgs-logo.png';
            // Image($file, $x='', $y='', $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false)
            PDF::Image(getcwd() . $image_path, 0, 23, 40, 30, 'PNG', '', 'T', false, 300, 'R', false, false, 0, false, false, false);
            PDF::SetY(7);
            // PDF::Cell(0, 2, 'TEAM ID: A', 0, 1, 'R');
            PDF::SetY(5);
            //PDF::Cell(0, 30, '', 0, 1);
            PDF::Cell(0, 5, 'MINISTRY OF EDUCATION - KEEPING GIRLS IN SCHOOL PROGRAMME', 0, 1, 'L');
            //PDF::SetX(35);
            $checklist_header = '<p><b>' . $checklist_ref . '</b>: ' . strtoupper($checklist_name) . '</p>';
            //PDF::writeHTML($checklist_header, true, false, false, false, 'L');
            PDF::writeHTMLCell(0, 10, 10, 10, $checklist_header, 0, 1, 0, true, 'L', true);
            PDF::Cell(200, 5, 'Section I', 0, 1, 'L');
            //PDF::setY(57);
            $school_table = '<table border="1" width="650" cellpadding="3">
                       <tbody>
                       <tr>
                           <td>Name of School</td>
                           <td>' . $school_code . '&nbsp;-&nbsp;' . $school_name . '</td>
                       </tr>
                       <tr>
                           <td>EMIS Code</td>
                           <td>' . $district_code . '-' . $school_code . '</td>
                       </tr>
                       <tr>
                           <td>Province of School</td>
                           <td><b>' . $province_name . '</b></td>
                       </tr>
                       <tr>
                           <td>District of School</td>
                             <td>' . $district_code . '&nbsp;-&nbsp;' . $district_name . '</td>
                       </tr>
                       <tr>
                           <td>School contact person</td>
                           <td></td>
                       </tr>
                       </tbody></tbody>
                       </table>';
            PDF::writeHTML($school_table, true, false, false, false, 'L');
            //==========///////////////////////////////////////////////////////////////////===========//
            //==========///////////////////////////////////////////////////////////////////===========//
            $htmlTable = '
                         <style>
                          table {
                                border-collapse: collapse;
                                white-space:nowrap;
                          }

                          table, th, td {
                                border: 1px solid black;
                          }
                          </style>
              <table border="1" cellpadding="2" align="center">
			   <thead>
			   <tr><td colspan="11">Section II</td><td colspan="' . $numOfQuizes . '">Section III</td></tr>
			    <tr>
			    <td colspan="11"></td>
			    <td colspan="' . $numOfQuizes . '">Note: This "Assignment" section needs to be filled by the PIU</td>
			    </tr>
				<tr>
				    <th>Name of District</th>
				    <th>Constituency</th>
				    <th>Ward</th>
				    <th>CWAC</th>
		  			<th>Beneficiary ID</th>
		  			<th>Examination No.</th>
					<th>Girl First Name</th>
					<th>Girl Last Name</th>
					<th>HH Head First Name</th>
					<th>HH Head Last Name</th>
					<th>Type of exam the candidate sat for</th>';
            foreach ($quizs as $quiz) {
                $header = 'Q' . $quiz->order_no . '. ' . $quiz->name;
                $htmlTable .= '<th>' . $header . '</th>';
            }
            $htmlTable .= '</tr></thead><tbody>';
            $printed_array = array();
            foreach ($beneficiaries as $beneficiary) {
                $htmlTable .= '<tr>
                             <td>' . $district_code . '-' . $district_name . '</td>
                             <td>' . $beneficiary->constituency_code . '-' . $beneficiary->constituency_name . '</td>
                             <td>' . $beneficiary->ward_code . '-' . $beneficiary->ward_name . '</td>
                             <td>' . $beneficiary->cwac_code . '-' . $beneficiary->cwac_name . '</td>
                             <td>' . $beneficiary->beneficiary_id . '</td>
                             <td>' . $beneficiary->exam_number . '</td>
                             <td>' . $beneficiary->first_name . '</td>
                             <td>' . $beneficiary->last_name . '</td>
                             <td>' . $beneficiary->hhh_fname . '</td>
                             <td>' . $beneficiary->hhh_lname . '</td>
                             <td>Grade ' . $beneficiary->exam_grade . ' Exams</td>';
                for ($i = 1; $i <= $numOfQuizes; $i++) {
                    $htmlTable .= '<td></td>';
                }
                $htmlTable .= '</tr>';
                $printed_array[] = array(
                    'id' => $beneficiary->id
                );
            }
            $printed_IDs = convertAssArrayToSimpleArray($printed_array, 'id');
            DB::table('beneficiary_information')
                ->whereIn('id', $printed_IDs)
                ->update(array('placement_form_printed' => 1));
            $htmlTable .= '</tbody></table>';
            PDF::writeHTML($htmlTable, true, false, false, false, 'C');
            //==========///////////////////////////////////////////////////////////////////===========//
            $footerText = '<p>*For any complaint relating to Keeping Girls in School or your welfare <b>(GBV, School Bullying or HIV)</b> 
                please call on <b>+260953003103</b> <b>(regular call charges may apply)</b>.</p>';
            PDF::SetY(-8);
            PDF::writeHTML($footerText, true, 0, true, true);
        }
        //PDF::Output($filename . time() . '.pdf', 'I');
        PDF::Output($filename . time() . '_' . $district_name . '.pdf', 'I');
    }

    public function generateBeneficiariesList($schoolsArray, $batch_id, $category, $filename)
    {
        foreach ($schoolsArray as $school_info) {
            $school_code = $school_info->code;
            $school_name = aes_decrypt($school_info->name);
            $district_code = $school_info->district_code;
            $district_name = aes_decrypt($school_info->district_name);
            $province_name = aes_decrypt($school_info->province_name);
            //todo::get beneficiary information
            $where = array(
                'batch_id' => $batch_id,
                'category' => $category,
                'school_id' => $school_info->id
            );
            $qry = DB::table('beneficiary_information')
                ->leftJoin('districts', 'beneficiary_information.district_id', '=', 'districts.id')
                ->leftJoin('households', 'beneficiary_information.household_id', '=', 'households.id')
                ->select('beneficiary_information.*', 'districts.code as district_code2', 'districts.name as district_name2', 'households.hhh_fname', 'households.hhh_lname')
                ->where($where);
            $beneficiaries = $qry->get();
            $beneficiaries = convertStdClassObjToArray($beneficiaries);
            $beneficiaries = decryptArray($beneficiaries);
            // ===========///////////////////////////////////////////////////////////////////==========//
            PDF::SetTitle('Checklist');
            PDF::AddPage('L');
            PDF::SetAutoPageBreak(TRUE, 0);//true sets it to on and 0 means margin is zero from sides
            PDF::SetFont('helvetica', '', 9);
            //headers
            $image_path = '\resources\images\kgs-logo.png';
            // Image($file, $x='', $y='', $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false)
            PDF::Image(getcwd() . $image_path, 0, 26, 40, 30, 'PNG', '', 'T', false, 300, 'R', false, false, 0, false, false, false);
            PDF::SetY(5);
            //PDF::Cell(0, 30, '', 0, 1);
            PDF::Cell(0, 5, 'MINISTRY OF EDUCATION - KEEPING GIRLS IN SCHOOL PROGRAMME', 0, 1, 'L');
            PDF::Cell(0, 5, 'BENEFICIARY LIST', 0, 1, 'L');
            PDF::Cell(200, 5, 'School Details', 0, 1, 'L');
            //PDF::setY(57);
            $school_table = '<table border="1" width="650" cellpadding="3">
                       <tbody>
                       <tr>
                           <td>Name of School</td>
                           <td>' . $school_code . '&nbsp;-&nbsp;' . $school_name . '</td>
                       </tr>
                       <tr>
                           <td>EMIS Code</td>
                           <td>' . $district_code . '-' . $school_code . '</td>
                       </tr>
                       <tr>
                           <td>Province of School</td>
                           <td><b>' . $province_name . '</b></td>
                       </tr>
                       <tr>
                           <td>District of School</td>
                             <td>' . $district_code . '&nbsp;-&nbsp;' . $district_name . '</td>
                       </tr>
                       <tr>
                           <td>If district of school is incorrect, please write correct district name</td>
                           <td></td>
                       </tr>
                       </tbody></tbody>
                       </table>';
            PDF::writeHTML($school_table, true, false, false, false, 'L');
            //==========///////////////////////////////////////////////////////////////////===========//
            //==========///////////////////////////////////////////////////////////////////===========//
            PDF::Cell(200, 5, 'Girls List', 0, 1, 'L');
            $htmlTable = '
               <table border="1" cellpadding="2" align="center">
			   <thead>
				<tr>
		  			<th> Beneficiary ID</th>
					<th>Home District of Girl</th>
					<th>Girl First Name</th>
					<th>Girl Surname</th>
					<th>Grade of Learner(from DSW data)</th>
					<th>HH Head First Name</th>
					<th>HH Head Last Name</th>';
            $htmlTable .= '</tr></thead><tbody>';
            foreach ($beneficiaries as $beneficiary) {
                $htmlTable .= '<tr>
                             <td>' . $beneficiary['beneficiary_id'] . '</td>
                             <td>' . $beneficiary['district_code2'] . '-' . $beneficiary['district_name2'] . '</td>
                             <td>' . $beneficiary['first_name'] . '</td>
                             <td>' . $beneficiary['last_name'] . '</td>
                             <td>' . $beneficiary['current_school_grade'] . '</td>
                             <td>' . $beneficiary['hhh_fname'] . '</td>
                             <td>' . $beneficiary['hhh_lname'] . '</td>';
                $htmlTable .= '</tr>';
            }
            $htmlTable .= '</tbody></table>';
            PDF::writeHTML($htmlTable, true, false, false, false, 'C');
            PDF::SetFont('', 'OI', 8);
            $user = aes_decrypt(\Auth::user()->first_name) . ' ' . aes_decrypt(\Auth::user()->last_name);
            PDF::SetY(200);
            PDF::Cell(0, 2, 'Printed by ' . $user . ' on ' . date('d/m/Y'), 0, 1, 'R');
            //==========///////////////////////////////////////////////////////////////////===========//
            // $footerText = '<p>*For any complaint relating to Keeping Girls in School or your welfare (GBV, School Bullying or HIV) 
            // please call Lifeline ChildLine (Toll-Free) on <b>933</b> or <b>116</b>.</p>';
            $footerText = '<p>*For any complaint relating to Keeping Girls in School or your welfare <b>(GBV, School Bullying or HIV)</b> 
                please call on <b>+260953003103</b> <b>(regular call charges may apply)</b>.</p>';
            PDF::SetY(-8);
            PDF::writeHTML($footerText, true, 0, true, true);
        }
        PDF::Output($filename . time() . '.pdf', 'I');
    }

    //Offer letters printing
    public function printSingleOfferLetter($girl_id, $category)
    {
        $filename = 'offerletter';
        if ($category == 1) {
            $this->generateSingleOutofSCHOfferLetters($girl_id, $filename);
        } else if ($category == 2) {
            $this->generateSingleInSCHOfferLetters($girl_id, $filename);
        } else if ($category == 3) {
            $this->generateSingleExamClassesOfferLetters($girl_id, $filename);
        } else {
            print_r('No details matches your print options!!');
            exit();
        }
    }

    public function printSpecificOfferLetters($batch_id, $school_id, $category)
    {
        $filename = 'offerletters';
        if ($category == 1) {
            $this->generateSpecificOutofSCHOfferLetters($school_id, $batch_id, $category, $filename);
        } else if ($category == 2) {
            $this->generateSpecificInSCHOfferLetters($school_id, $batch_id, $category, $filename);
        } else if ($category == 3) {
            $this->generateSpecificExamClassesOfferLetters($school_id, $batch_id, $category, $filename);
        } else {
            print_r('No details matches your print options!!');
            exit();
        }
    }

    public function getBeneficiaryDetails($batch_id, $category, $school_id, $print_filter = 3, $sub_category = 0, $verification_type = null)
    {
        //todo::get beneficiary information
        $where = array(
            't1.batch_id' => $batch_id,
            't1.beneficiary_status' => 4,//just to be sure
            't1.enrollment_status' => 1,//just to be sure
            't1.verification_recommendation' => 1,//just to be sure
            't1.school_id' => $school_id
        );
        $whereCat = array($category);
        if ($category == 2) {
            $whereCat = array(2, 3);
        }
        $qry = DB::table('beneficiary_information as t1')
            ->leftJoin('beneficiary_school_statuses', 't1.beneficiary_school_status', '=', 'beneficiary_school_statuses.id')
            ->join('school_information', 't1.school_id', '=', 'school_information.id')
            ->leftJoin('provinces', 'school_information.province_id', '=', 'provinces.id')
            ->join('districts as school_district', 'school_information.district_id', '=', 'school_district.id')
            ->join('districts', 't1.district_id', '=', 'districts.id')
            ->leftJoin('users', 'districts.debs_id', '=', 'users.id')
            ->leftJoin('titles', 'users.title_id', '=', 'titles.id')
            ->leftJoin('cwac', 't1.cwac_id', '=', 'cwac.id')
            ->leftJoin('households', 't1.household_id', '=', 'households.id')
            ->select(DB::raw('t1.id,t1.cwac_id,t1.district_id,t1.school_id,t1.beneficiary_id,decrypt(t1.first_name) as first_name, decrypt(t1.last_name) as last_name,t1.current_school_grade,t1.beneficiary_school_status,
                              t1.category,t1.skip_matching,t1.cwac_txt,households.hhh_fname,households.hhh_lname,titles.name as debs_title, decrypt(users.first_name) as debs_fname, decrypt(users.last_name) as debs_lname, decrypt(users.phone) as phone,
                              beneficiary_school_statuses.name as ben_sch_status, provinces.name as province_name, school_district.code as sch_dist_code, school_district.name as sch_dist_name, cwac.code as cwac_code, cwac.name as cwac_name,
                              t1.under_promotion,school_information.name as school_name, districts.code as district_code2, districts.name as district_name2,households.hhh_nrc_number'))
            ->where($where)
            ->whereIn('t1.category', $whereCat);
        if (validateisNumeric($verification_type)) {
            $qry->where('t1.verification_type', $verification_type);
        }
        getBeneficiarySchMatchingPlacementDetails($qry, $sub_category, 't1');
        if (isset($print_filter) && $print_filter != '') {
            if ($print_filter == 1) {
                $qry->where('t1.letter_printed', 1);
            } else if ($print_filter == 2) {
                $qry->where('t1.letter_printed', '<>', 1)
                    ->orWhere(function ($query) {
                        $query->whereNull('t1.letter_printed');
                    });
            }
        }
        return $qry->get();
    }

    public function getSchMatchingProvisionalBeneficiaryDetails($batch_id, $category, $district_id, $print_filter = 3, $sub_category = 0, $verification_type = null)
    {
        //todo::get beneficiary information
        $where = array(
            't1.batch_id' => $batch_id,
            't1.beneficiary_status' => 5,//school matching
            't1.verification_recommendation' => 1,//just to be sure
            't1.district_id' => $district_id
        );
        $qry = DB::table('beneficiary_information as t1')
            ->join('districts', 't1.district_id', '=', 'districts.id')
            ->leftJoin('users', 'districts.debs_id', '=', 'users.id')
            ->leftJoin('titles', 'users.title_id', '=', 'titles.id')
            ->leftJoin('cwac', 't1.cwac_id', '=', 'cwac.id')
            ->leftJoin('households', 't1.household_id', '=', 'households.id')
            ->select(DB::raw('t1.id,t1.cwac_id,t1.district_id,t1.school_id,t1.beneficiary_id,decrypt(t1.first_name) as first_name, decrypt(t1.last_name) as last_name,t1.current_school_grade,t1.beneficiary_school_status,
                t1.category,t1.skip_matching,t1.cwac_txt,households.hhh_fname,households.hhh_lname,titles.name as debs_title, decrypt(users.first_name) as debs_fname, decrypt(users.last_name) as debs_lname, 
                decrypt(users.phone) as phone,cwac.code as cwac_code, 
                cwac.name as cwac_name,t1.under_promotion, districts.code as district_code2, 
                districts.name as district_name2,households.hhh_nrc_number,
                t1.beneficiary_status'))
            ->where($where);
        if (validateisNumeric($verification_type)) {
            $qry->where('t1.verification_type', $verification_type);
        }
        if (isset($print_filter) && $print_filter != '') {
            if ($print_filter == 1) {
                $qry->where('t1.letter_printed', 1);
            } else if ($print_filter == 2) {
                $qry->where('t1.letter_printed', '<>', 1)
                    ->orWhere(function ($query) {
                        $query->whereNull('t1.letter_printed');
                    });
            }
        }
        return $qry->get();
    }

    public function getSchPlacementProvisionalBeneficiaryDetails($batch_id, $category, $school_id, $print_filter = 3, $sub_category = 0, $verification_type = null)
    {
        //todo::get beneficiary information
        $where = array(
            't1.batch_id' => $batch_id,
            't1.beneficiary_status' => 8,//School Placement
            't1.verification_recommendation' => 1,//just to be sure
            't1.school_id' => $school_id
        );
        $qry = DB::table('beneficiary_information as t1')
            ->leftJoin('beneficiary_school_statuses', 't1.beneficiary_school_status', '=', 'beneficiary_school_statuses.id')
            ->join('school_information', 't1.exam_school_id', '=', 'school_information.id')
            ->join('provinces', 'school_information.province_id', '=', 'provinces.id')
            ->join('districts as school_district', 'school_information.district_id', '=', 'school_district.id')
            ->join('districts', 't1.district_id', '=', 'districts.id')
            ->leftJoin('users', 'districts.debs_id', '=', 'users.id')
            ->leftJoin('titles', 'users.title_id', '=', 'titles.id')
            ->leftJoin('cwac', 't1.cwac_id', '=', 'cwac.id')
            ->leftJoin('households', 't1.household_id', '=', 'households.id')
            ->select(DB::raw('t1.id,t1.cwac_id,t1.district_id,t1.school_id,t1.beneficiary_id,decrypt(t1.first_name) as first_name, decrypt(t1.last_name) as last_name,t1.current_school_grade,t1.beneficiary_school_status,t1.grade7_exam_no,
                              t1.category,t1.skip_matching,t1.cwac_txt,households.hhh_fname,households.hhh_lname,titles.name as debs_title, decrypt(users.first_name) as debs_fname, decrypt(users.last_name) as debs_lname, decrypt(users.phone) as phone,
                              beneficiary_school_statuses.name as ben_sch_status, provinces.name as province_name, school_district.code as sch_dist_code, school_district.name as sch_dist_name, cwac.code as cwac_code, cwac.name as cwac_name,
                              t1.under_promotion,school_information.name as school_name, districts.code as district_code2, districts.name as district_name2,households.hhh_nrc_number,t1.grade9_exam_no,t1.grade12_exam_no,t1.beneficiary_status'))
            ->where($where);
        if (validateisNumeric($verification_type)) {
            $qry->where('t1.verification_type', $verification_type);
        }
        //getBeneficiarySchMatchingPlacementDetails($qry, $sub_category, 't1');
        if (isset($print_filter) && $print_filter != '') {
            if ($print_filter == 1) {
                $qry->where('t1.letter_printed', 1);
            } else if ($print_filter == 2) {
                $qry->where('t1.letter_printed', '<>', 1)
                    ->orWhere(function ($query) {
                        $query->whereNull('t1.letter_printed');
                    });
            }
        }
        return $qry->get();
    }

    public function getUnresponsiveBeneficiaryDetails($school_id, $print_filter)
    {
        //todo::get beneficiary information
        $where = array(
            't1.beneficiary_status' => 4,//just to be sure
            't1.verification_recommendation' => 1,//just to be sure
            't1.school_id' => $school_id,
            'unresponsive_cohorts.matched' => 1
        );
        $qry = DB::table('beneficiary_information as t1')
            ->join('unresponsive_cohorts', 't1.id', '=', 'unresponsive_cohorts.girl_id')
            ->leftJoin('beneficiary_school_statuses', 't1.beneficiary_school_status', '=', 'beneficiary_school_statuses.id')
            ->leftJoin('school_information', 't1.school_id', '=', 'school_information.id')
            ->leftJoin('provinces', 'school_information.province_id', '=', 'provinces.id')
            ->leftJoin('districts as school_district', 'school_information.district_id', '=', 'school_district.id')
            ->leftJoin('districts', 't1.district_id', '=', 'districts.id')
            ->leftJoin('users', 'districts.debs_id', '=', 'users.id')
            ->leftJoin('titles', 'users.title_id', '=', 'titles.id')
            ->leftJoin('cwac', 't1.cwac_id', '=', 'cwac.id')
            ->leftJoin('households', 't1.household_id', '=', 'households.id')
            ->select(DB::raw('t1.id,t1.cwac_id,t1.district_id,t1.school_id,t1.beneficiary_id,CASE WHEN decrypt(t1.first_name) IS NULL THEN t1.first_name ELSE decrypt(t1.first_name) END as first_name, CASE WHEN decrypt(t1.last_name) IS NULL THEN t1.last_name ELSE decrypt(t1.last_name) END as last_name,t1.current_school_grade,t1.beneficiary_school_status,
                                  households.hhh_fname,households.hhh_lname,titles.name as debs_title, decrypt(users.first_name) as debs_fname, decrypt(users.last_name) as debs_lname, CASE WHEN decrypt(users.phone) IS NULL THEN users.phone ELSE decrypt(users.phone) END as phone, beneficiary_school_statuses.name as ben_sch_status, provinces.name as province_name, school_district.code as sch_dist_code, school_district.name as sch_dist_name, cwac.code as cwac_code, cwac.name as cwac_name, school_information.name as school_name, districts.code as district_code2, districts.name as district_name2'))
            ->where($where);
        if (isset($print_filter) && $print_filter != '') {
            if ($print_filter == 1) {
                $qry->where('t1.letter_printed', 1);
            } else if ($print_filter == 2) {
                $qry->where('t1.letter_printed', '<>', 1)
                    ->orWhere(function ($query) {
                        $query->whereNull('t1.letter_printed');
                    });
            }
        }
        $beneficiaries = $qry->get();
        return $beneficiaries;
    }

    public function printProvisionalOfferLetters(Request $request)//provisional offerletters
    {
        $batch_id = $request->input('batch_id');
        $category = $request->input('category');
        $sub_category = $request->input('sub_category');
        $provinces = $request->input('provinceStr');
        $districts = $request->input('districtsStr');
        $schools = $request->input('schoolsStr');
        $print_filter = $request->input('print_filter');
        $verification_type = $request->input('verification_type');
        $filename = 'Offer_letters';
        $date_details = DB::table('letter_dates')
            ->where('is_active', 1)
            ->first();
        if (is_null($date_details)) {
            print_r('<h3 style="color: red; text-align: center">No active date details were found. Please set letter date details before printing!!</h3>');
            exit();
        }
        //todo:: generation can be per school, per district or per province....this is a bit tricky...school supersedes all, district supersedes provinces
        if (isset($districts) && count(json_decode($districts)) > 0) {//schools not set..now use districts
            //get all schools in the selected districts
            $districtsArray = json_decode($districts);
            if ($sub_category == 5) {//school matching
                $re = $this->generateOutofSCHProvisionalOfferLetters($districtsArray, $batch_id, $category, $date_details, $filename, $print_filter, $sub_category, $verification_type);
            } else {//school placement
                $schoolsArray = DB::table('school_information')->select('id')->whereIn('district_id', $districtsArray)->get();
                $schoolsArray = convertStdClassObjToArray($schoolsArray);
                $schoolsArray = convertAssArrayToSimpleArray($schoolsArray, 'id');
                $this->generateInSCHProvisionalOfferLetters($schoolsArray, $batch_id, $category, $date_details, $filename, $print_filter, $sub_category, $verification_type);
            }
        } else if (isset($provinces) && count(json_decode($provinces)) > 0) {//schools,districts not set...now use provinces
            //get all schools in the selected provinces
            $provincesArray = json_decode($provinces);
            $schoolsArray = DB::table('school_information')->select('id')->whereIn('province_id', $provincesArray)->get();
            $schoolsArray = convertStdClassObjToArray($schoolsArray);
            $schoolsArray = convertAssArrayToSimpleArray($schoolsArray, 'id');
            if ($category == 1) {
                $this->generateOutofSCHOfferLetters($schoolsArray, $batch_id, $category, $date_details, $filename, $print_filter, $sub_category, $verification_type);
            } else if ($category == 2 || $category == 3) {
                $this->generateInSCHOfferLetters($schoolsArray, $batch_id, $category, $date_details, $filename, $print_filter, $sub_category, $verification_type);
            } /*else if ($category == 3) {
                $this->generateExamClassesOfferLetters($schoolsArray, $batch_id, $category, $date_details, $filename, $print_filter, $sub_category);
            }*/ else if ($category == 4) {
                $this->generateUnresponsiveOfferLetters($schoolsArray, $date_details, $filename, $print_filter);
            }
        } else {//nothing set....how did you find yourself here??

        }
    }

    public function printOfferLetters(Request $request)//offerletters
    {
        $batch_id = $request->input('batch_id');
        $category = $request->input('category');
        $sub_category = $request->input('sub_category');
        $provinces = $request->input('provinceStr');
        $districts = $request->input('districtsStr');;
        $schools = $request->input('schoolsStr');;
        $print_filter = $request->input('print_filter');
        $verification_type = $request->input('verification_type');
        $filename = 'Offer_letters';
        $date_details = DB::table('letter_dates')
            ->where('is_active', 1)
            ->first();
        if (is_null($date_details)) {
            print_r('<h3 style="color: red; text-align: center">No active date details were found. Please set letter date details before printing!!</h3>');
            exit();
        }
        //todo:: generation can be per school, per district or per province....this is a bit tricky...school supersedes all, district supersedes provinces
        if (isset($schools) && count(json_decode($schools)) > 0) {//schools selected
            $schoolsArray = json_decode($schools);
            if ($category == 1) {
                $this->generateOutofSCHOfferLetters($schoolsArray, $batch_id, $category, $date_details, $filename, $print_filter, $sub_category, $verification_type);
            } else if ($category == 2 || $category == 3) {
                $this->generateInSCHOfferLetters($schoolsArray, $batch_id, $category, $date_details, $filename, $print_filter, $sub_category, $verification_type);
            } /*else if ($category == 3) {
                $this->generateExamClassesOfferLetters($schoolsArray, $batch_id, $category, $date_details, $filename, $print_filter, $sub_category);
            }*/ else if ($category == 4) {
                $this->generateUnresponsiveOfferLetters($schoolsArray, $date_details, $filename, $print_filter);
            }
        } else if (isset($districts) && count(json_decode($districts)) > 0) {//schools not set..now use districts
            //get all schools in the selected districts
            $districtsArray = json_decode($districts);
            $schoolsArray = DB::table('school_information')->select('id')->whereIn('district_id', $districtsArray)->get();
            $schoolsArray = convertStdClassObjToArray($schoolsArray);
            $schoolsArray = convertAssArrayToSimpleArray($schoolsArray, 'id');
            if ($category == 1) {
                $this->generateOutofSCHOfferLetters($schoolsArray, $batch_id, $category, $date_details, $filename, $print_filter, $sub_category, $verification_type);
            } else if ($category == 2 || $category == 3) {
                $this->generateInSCHOfferLetters($schoolsArray, $batch_id, $category, $date_details, $filename, $print_filter, $sub_category, $verification_type);
            } /*else if ($category == 3) {
                $this->generateExamClassesOfferLetters($schoolsArray, $batch_id, $category, $date_details, $filename, $print_filter, $sub_category);
            }*/ else if ($category == 4) {
                $this->generateUnresponsiveOfferLetters($schoolsArray, $date_details, $filename, $print_filter);
            }
        } else if (isset($provinces) && count(json_decode($provinces)) > 0) {//schools,districts not set...now use provinces
            //get all schools in the selected provinces
            $provincesArray = json_decode($provinces);
            $schoolsArray = DB::table('school_information')->select('id')->whereIn('province_id', $provincesArray)->get();
            $schoolsArray = convertStdClassObjToArray($schoolsArray);
            $schoolsArray = convertAssArrayToSimpleArray($schoolsArray, 'id');
            if ($category == 1) {
                $this->generateOutofSCHOfferLetters($schoolsArray, $batch_id, $category, $date_details, $filename, $print_filter, $sub_category, $verification_type);
            } else if ($category == 2 || $category == 3) {
                $this->generateInSCHOfferLetters($schoolsArray, $batch_id, $category, $date_details, $filename, $print_filter, $sub_category, $verification_type);
            } /*else if ($category == 3) {
                $this->generateExamClassesOfferLetters($schoolsArray, $batch_id, $category, $date_details, $filename, $print_filter, $sub_category);
            }*/ else if ($category == 4) {
                $this->generateUnresponsiveOfferLetters($schoolsArray, $date_details, $filename, $print_filter);
            }
        } else {//nothing set....how did you find yourself here??

        }
    }

    public function generateOutofSCHOfferLetters($schoolsArray, $batch_id, $category, $details, $filename, $print_filter = 3, $sub_category = 0, $verification_type = null)
    {
        $sch_district_name = '';
        //todo: get beneficiaries
        foreach ($schoolsArray as $school_id) {
            //todo::get beneficiary information
            $beneficiaries = $this->getBeneficiaryDetails($batch_id, $category, $school_id, $print_filter, $sub_category, $verification_type);
            if (is_null($beneficiaries) || count($beneficiaries) < 1) {
                continue;
            }
            $printed = array();
            foreach ($beneficiaries as $beneficiary) {
                $this->outOfSCHOfferLetter($beneficiary, $details);
                $printed[] = array(
                    'id' => $beneficiary->id
                );
            }
            $printed_ids = convertAssArrayToSimpleArray($printed, 'id');
            DB::table('beneficiary_information')
                ->whereIn('id', $printed_ids)
                ->update(array('letter_printed' => 1));
        }
        PDF::Output($filename . time() . '_' . $sch_district_name . '.pdf', 'I');
    }

    public function generateInSCHOfferLetters($schoolsArray, $batch_id, $category, $details, $filename, $print_filter = 3, $sub_category, $verification_type = null)
    {
        $sch_district_name = 'N_A';
        foreach ($schoolsArray as $school_id) {
            //todo::get beneficiary information
            $beneficiaries = $this->getBeneficiaryDetails($batch_id, $category, $school_id, $print_filter, $sub_category, $verification_type);
            if (is_null($beneficiaries) || count($beneficiaries) < 1) {
                continue;
            }
            $printed = array();
            foreach ($beneficiaries as $beneficiary) {
                $this->inSCHOfferLetter($beneficiary, $details);
                $sch_district_name = $beneficiary->sch_dist_name;
                $printed[] = array(
                    'id' => $beneficiary->id
                );
            }
            $printed_ids = convertAssArrayToSimpleArray($printed, 'id');
            DB::table('beneficiary_information')
                ->whereIn('id', $printed_ids)
                ->update(array('letter_printed' => 1));
        }
        PDF::Output($filename . time() . '_' . $sch_district_name . '.pdf', 'I');
    }

    public function generateOutofSCHProvisionalOfferLetters($districtsArray, $batch_id, $category, $details, $filename, $print_filter = 3, $sub_category = 0, $verification_type = null)
    {
        $sch_district_name = '';
        //todo: get beneficiaries
        foreach ($districtsArray as $district_id) {
            //todo::get beneficiary information
            //getSchPlacementProvisionalBeneficiaryDetails
            $beneficiaries = $this->getSchMatchingProvisionalBeneficiaryDetails($batch_id, $category, $district_id, $print_filter, $sub_category, $verification_type);
            if (is_null($beneficiaries) || count($beneficiaries) < 1) {
                continue;
            }
            $printed = array();
            foreach ($beneficiaries as $beneficiary) {
                $this->provisionalOfferLetter($beneficiary, $details);
                $printed[] = array(
                    'id' => $beneficiary->id
                );
            }
            $printed_ids = convertAssArrayToSimpleArray($printed, 'id');
            DB::table('beneficiary_information')
                ->whereIn('id', $printed_ids)
                ->update(array('prov_letter_printed' => 1));
        }
        PDF::Output($filename . time() . '_' . $sch_district_name . '.pdf', 'I');
    }

    public function generateInSCHProvisionalOfferLetters($schoolsArray, $batch_id, $category, $details, $filename, $print_filter = 3, $sub_category, $verification_type = null)
    {
        $sch_district_name = 'N_A';
        foreach ($schoolsArray as $school_id) {
            //todo::get beneficiary information
            $beneficiaries = $this->getSchPlacementProvisionalBeneficiaryDetails($batch_id, $category, $school_id, $print_filter, $sub_category, $verification_type);
            if (is_null($beneficiaries) || count($beneficiaries) < 1) {
                continue;
            }
            $printed = array();
            foreach ($beneficiaries as $beneficiary) {
                $this->provisionalOfferLetter($beneficiary, $details);
                $sch_district_name = $beneficiary->sch_dist_name;
                $printed[] = array(
                    'id' => $beneficiary->id
                );
            }
            $printed_ids = convertAssArrayToSimpleArray($printed, 'id');
            DB::table('beneficiary_information')
                ->whereIn('id', $printed_ids)
                ->update(array('prov_letter_printed' => 1));
        }
        PDF::Output($filename . time() . '_' . $sch_district_name . '.pdf', 'I');
    }

    public function generateExamClassesOfferLetters($schoolsArray, $batch_id, $category, $details, $filename, $print_filter = 3, $sub_category = 0)
    {
        $start_year = $details->start_year;
        $orig_reporting_date = $details->reporting_date;
        $reporting_date = 'on <b>' + converter22($details->reporting_date) + '</b>';
        // $grace_period = 'The grace period for reporting is until <b>' + converter22($details->grace_period) + '</b>';
        $grace_period = 'The grace period for reporting for Term 3, 2025 is up to <b>' . converter22($details->grace_period) . '.</b>';
        $ps = $details->ps;
        $signature = $details->signature_specimen;
        $signature_path = '\resources\images\signatures\\' . $signature;
        //replaceable
        $reporting_date = '<b>as soon as you receive this letter</b>';
        $grace_period = '';
        //todo: get beneficiaries
        foreach ($schoolsArray as $school_id) {
            //todo::get beneficiary information
            $beneficiaries = $this->getBeneficiaryDetails($batch_id, $category, $school_id, $print_filter);
            if (is_null($beneficiaries) || count($beneficiaries) < 1) {
                continue;
            }
            $printed = array();
            foreach ($beneficiaries as $beneficiary) {
                //get the last ID//letter generation logging here
                $last_id = DB::table('letters_gen_log')->max('id');
                $log_check = Db::table('letters_gen_log')->where('girl_id', $beneficiary->id)->first();
                if (is_null($log_check)) {
                    $serial = $last_id + 1;
                    $serial = str_pad($serial, 5, 0, STR_PAD_LEFT);
                    $ref = 'KGS/examclass/' . $serial;
                    $params = array(
                        'girl_id' => $beneficiary->id,
                        'letter_ref' => $ref,
                        'generated_by' => \Auth::user()->id,
                        'generated_on' => Carbon::now()
                    );
                    DB::table('letters_gen_log')->insert($params);
                } else {
                    $ref = $log_check->letter_ref;
                }
                $letter_tracker = $start_year . strtotime($orig_reporting_date);
                $last_version = DB::table('letters_gen_tracker')
                    ->where('girl_id', $beneficiary->id)
                    ->where('tracker', $letter_tracker)
                    ->max('version');
                if (is_null($last_version) || $last_version == '') {
                    $version = 1;
                } else {
                    $version = $last_version + 1;
                }
                $log_data = array(
                    'girl_id' => $beneficiary->id,
                    'start_year' => $start_year,
                    'reporting_date' => $reporting_date,
                    'grace_period' => $grace_period,
                    'ps' => $ps,
                    'signature_specimen' => $signature,
                    'girl_cwac' => $beneficiary->cwac_id,
                    'girl_district' => $beneficiary->district_id,
                    'girl_first_name' => $beneficiary->first_name,
                    'girl_last_name' => $beneficiary->last_name,
                    'beneficiary_school_status' => $beneficiary->beneficiary_school_status,
                    'version' => $version,
                    'grade' => $beneficiary->current_school_grade,
                    'school_id' => $beneficiary->school_id,
                    'tracker' => $letter_tracker,
                    'created_at' => Carbon::now(),
                    'created_by' => \Auth::user()->id
                );
                DB::table('letters_gen_tracker')
                    ->insert($log_data);
                //end letter logging here
                //check if learner is under promotion
                if ($beneficiary->under_promotion == 1) {
                    $grade = ($beneficiary->current_school_grade + 1);
                } else {
                    $grade = $beneficiary->current_school_grade;
                }
                $extension_txt = '';
                $end_year = $start_year + (12 - $grade);
                if ($end_year > 2020) {
                    $end_year = 2020;
                    $extension_txt = 'Should the project be extended beyond 2020, your scholarship will still be valid within the extension period.';
                }
                $ben_sch_status = $beneficiary->ben_sch_status;
                $debs_name = $beneficiary->debs_fname . '&nbsp;' . $beneficiary->debs_lname;
                $debs_phone = $beneficiary->phone;
                $debs_title = $beneficiary->debs_title;
                $learner_sch = $beneficiary->school_name;
                if ($beneficiary->debs_fname == '' && $beneficiary->debs_lname == '') {
                    $debs_name = '_________________';
                }
                if ($debs_phone == '') {
                    $debs_phone = '_________________';
                }
                if ($ben_sch_status == '') {
                    $ben_sch_status = '_________________';
                }
                $sch_district_name = $beneficiary->sch_dist_name;
                // ===========///////////////////////////////////////////////////////////////////==========//
                $date = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', time());
                PDF::SetTitle('Offer Letters');
                PDF::SetAutoPageBreak(TRUE, 0);//true sets it to on and 0 means margin is zero from sides
                //PDF::SetMargins($left,$top,$right = -1,$keepmargins = false)
                PDF::SetMargins(10, 5, 10, true);
                PDF::AddPage();

                PDF::SetLineStyle(array('width' => 0.50, 'color' => array(0, 0, 0)));
                PDF::Rect(0, 0, PDF::getPageWidth(), PDF::getPageHeight());
                PDF::SetFont('times', 'B', 11);
                //headers
                $image_path = '\resources\images\kgs-logo.png';
                // Image($file, $x='', $y='', $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false)
                PDF::Image(getcwd() . $image_path, 0, 2, 25, 28, 'PNG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
                PDF::SetY(32);
                PDF::Cell(0, 2, 'REPUBLIC OF ZAMBIA', 0, 1, 'C');
                PDF::Cell(0, 5, 'MINISTRY OF EDUCATION', 0, 1, 'C');
                PDF::Cell(0, 5, 'P.O BOX 50093', 0, 1, 'C');
                PDF::Cell(0, 5, 'LUSAKA', 0, 1, 'C');
                PDF::SetY(2);
                // Start clipping.
                PDF::SetFont('times', 'I', 9);
                PDF::StartTransform();
                $html = '<p>All communications should be addressed to:<br>';
                $html .= 'The Permanent Secretary Ministry of Education, Science, Vocational Training and Early Education.</p>';
                $html .= '<p>Not to an individual by name</p>';
                $html .= '<p>Telephone: <span>250855/251315/251283<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                              251293/211318/251219<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                              251003/251319</span></p>';
                //$html = $left_header_one;
                #writeHTMLCell(w, h, x, y, html = '', border = 0, ln = 0, fill = 0, reseth = true, align = '', autopadding = true)
                PDF::writeHTMLCell(70, 0, 5, 9, $html, 0, 1, 0, 1, 'L');
                PDF::SetFont('times', 'BI', 9);
                PDF::SetY(7);
                PDF::Cell(0, 2, 'In reply please quote:', 0, 1, 'R');
                PDF::Cell(0, 2, 'No:' . $ref, 0, 1, 'R');
                //PDF::SetY(40);
                PDF::SetFont('times', '', 10);
                $html = '<p>' . $date . '</p>';
                $html .= '<p>Province of School:&nbsp;&nbsp;<b>' . strtoupper($beneficiary->province_name) . '</b><br>';
                $html .= 'District of School:&nbsp;&nbsp;<b>' . $beneficiary->sch_dist_code . '-' . strtoupper($beneficiary->sch_dist_name) . '</b><br>';
                $html .= 'School of Learner:&nbsp;&nbsp;<b>' . strtoupper($learner_sch) . '</b><br>';
                $html .= 'District of Learner:&nbsp;&nbsp;<b>' . strtoupper($beneficiary->district_name2) . '</b><br>';
                $html .= 'CWAC:&nbsp;&nbsp;<b>' . strtoupper($beneficiary->cwac_txt) . '</b><br>';
                $html .= 'C/O:&nbsp;&nbsp;<b>' . strtoupper($beneficiary->hhh_fname) . ' ' . strtoupper($beneficiary->hhh_lname) . '</b><br></p>';
                PDF::writeHTMLCell(0, 0, 5, 60, $html, 0, 1, 0, 1, 'L');
                PDF::SetFont('times', '', 11);
                $salutation = '<p>Dear <b>' . strtoupper($beneficiary->first_name) . '&nbsp;' . strtoupper($beneficiary->last_name) . ' (' . $beneficiary->beneficiary_id . ')</b></p>';
                $salutation .= '<p><b>RE: </b><u><b>ADMISSION LETTER FOR IN SCHOOL GIRLS ' . $start_year . ' SCHOLARSHIP: YOURSELF</b></u></p>';
                PDF::writeHTMLCell(0, 0, 5, 100, $salutation, 0, 1, 0, 1, 'L');
                $letter = '<p align="justify">Refer to the above captioned subject matter.<br><br>';//p1
                $letter .= 'The Government of the Republic of Zambia, through the Ministry of Education is implementing Keeping Girls in School initiative; a component of Girls Education and Women Empowerment Livelihood (GEWEL) project.
                  The ministry has undertaken a process of identifying the beneficiaries who are girls in Social Cash Transfer households to access Secondary Education through payment of School fees.</p>';

                $grade = $grade == 8 ? 'in <b> Form I' : 'in <b> Grade '. $grade;

                $letter .= '<p align="justify">In view of the above, I would like to congratulate you on your selection for Keeping Girls in School Scholarship at <b>' . strtoupper($learner_sch) . '</b> school as a <b>' . strtoupper($ben_sch_status) . '</b> beginning in <b>' . 
                $start_year . '</b> ' . $grade . '</b> and ending in <b>' . 
                $end_year . '</b>. ' . $extension_txt . '
                  This is a provisional offer, which is dependent on you reporting to school ' . $reporting_date . ' and subsequently attending school regularly.</p>';
                $letter .= '<p align="justify">Please be informed that when you report to your assigned school, carry this admission letter along with your grade 7 or 9 certificate as being proof of passing and upon producing the two documents you will be enrolled in school under KGS Scholarship.
                  ' . $grace_period . ' Further, you are encouraged to work hard in your schoolwork to avoid failure, which may lead to withdrawal of the scholarship.</p>';
                $letter .= '<p align="justify">For any clarification or concern you may have, please contact your CWAC in your area, if your concerns are not addressed you can contact the head teacher at the school to which you have been assigned and in an event that your head teacher does not
                  address your concerns, please do not hesitate to contact the District Education Board Secretary (DEBS) ' . $debs_title . ' ' . $debs_name . ' in your district on the telephone number ' . $debs_phone . '.</p>';
                PDF::writeHTMLCell(0, 0, 5, 117, $letter, 0, 1, 0, 1, 'L');
                PDF::ln(5);

                $signP1 = '<p><b>' . $ps . '</b></p>';
                $signP2 = '<p>KGS Coordinator<br><b><i>for</i> Permanent Secretary - Administration<br>Ministry of Education</b></p>';
                $footerText = '<p>*For any complaint relating to Keeping Girls in School or your welfare <b>(GBV, School Bullying or HIV)</b> 
                please call on <b>+260953003103</b> <b>(regular call charges may apply)</b>.</p>';
                PDF::Image(getcwd() . $signature_path, 0, '', 25, 12, '', '', 'N', true, 300, 'L', false, false, 0, false, false, false);
                PDF::writeHTML($signP1, true, 0, true, true);
                PDF::writeHTML($signP2, true, 0, true, true);
                //==========///////////////////////////////////////////////////////////////////===========//
                PDF::SetFont('times', '', 9);
                PDF::SetY(-18);
                PDF::writeHTML($footerText, true, 0, true, true);
                $printed[] = array(
                    'id' => $beneficiary->id
                );
            }
            $printed_ids = convertAssArrayToSimpleArray($printed, 'id');
            DB::table('beneficiary_information')
                ->whereIn('id', $printed_ids)
                ->update(array('letter_printed' => 1));
        }
        //PDF::Output($filename . time() . '.pdf', 'I');
        PDF::Output($filename . time() . '_' . $sch_district_name . '.pdf', 'D');
    }

    public function outOfSCHOfferLetter($beneficiary, $details)
    {//dd("here");
        $start_year = $details->start_year;
        $orig_reporting_date = $details->reporting_date;
        $ps = $details->ps;
        $signature = $details->signature_specimen;
        $signature_path = '\resources\images\signatures\\' . $signature;
        //replaceable
        $reporting_date = '<b>as soon as you receive this letter</b>';
        $grace_period = '';
        //get the last ID//letter generation logging here
        $skip_matching = $beneficiary->skip_matching;
        $salutation_category = 'OUT OF SCHOOL';
        $ref_category = 'outofschool';
        if ($skip_matching == 1) {
            $salutation_category = 'IN SCHOOL';
            $ref_category = 'inschool';
            //Date format changed
            /* $reporting_date = 'on <b>' . converter22($details->reporting_date) . '</b>';
            $grace_period = 'The grace period for reporting is until <b>' . converter22($details->grace_period) . '.</b>';*/
            $reporting_date = 'on <b>' . date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', strtotime(str_replace('/', '-', $details->reporting_date))) . '</b>';
            // $grace_period = 'The grace period for reporting is up to <b>' . date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', strtotime(str_replace('/', '-', $details->grace_period))) . '</b>';
            $grace_period = 'The grace period for reporting for Term 3, 2025 is up to <b>' . converter22($details->grace_period) . '.</b>';
        }
        $last_id = DB::table('letters_gen_log')->max('id');
        $log_check = Db::table('letters_gen_log')->where('girl_id', $beneficiary->id)->first();
        if (is_null($log_check)) {
            $serial = $last_id + 1;
            $serial = str_pad($serial, 5, 0, STR_PAD_LEFT);
            $ref = 'KGS/' . $ref_category . '/' . $serial;
            $params = array(
                'girl_id' => $beneficiary->id,
                'letter_ref' => $ref,
                'generated_by' => \Auth::user()->id,
                'generated_on' => Carbon::now()
            );
            DB::table('letters_gen_log')->insert($params);
        } else {
            $ref = $log_check->letter_ref;
        }
        $letter_tracker = $start_year . strtotime($orig_reporting_date);
        $last_version = DB::table('letters_gen_tracker')
            ->where('girl_id', $beneficiary->id)
            ->where('tracker', $letter_tracker)
            ->max('version');
        if (is_null($last_version) || $last_version == '') {
            $version = 1;
        } else {
            $version = $last_version + 1;
        }
        $log_data = array(
            'girl_id' => $beneficiary->id,
            'start_year' => $start_year,
            'reporting_date' => $reporting_date,
            'grace_period' => $grace_period,
            'ps' => $ps,
            'signature_specimen' => $signature,
            'girl_cwac' => $beneficiary->cwac_id,
            'girl_district' => $beneficiary->district_id,
            'girl_first_name' => $beneficiary->first_name,
            'girl_last_name' => $beneficiary->last_name,
            'beneficiary_school_status' => $beneficiary->beneficiary_school_status,
            'version' => $version,
            'grade' => $beneficiary->current_school_grade,
            'school_id' => $beneficiary->school_id,
            'tracker' => $letter_tracker,
            'created_at' => Carbon::now(),
            'created_by' => \Auth::user()->id
        );
        DB::table('letters_gen_tracker')
            ->insert($log_data);
        //end letter logging here
        //check if learner is under promotion
        if ($beneficiary->under_promotion == 1) {
            $grade = ($beneficiary->current_school_grade + 1);
        } else {
            $grade = $beneficiary->current_school_grade;
        }
        $extension_txt = '';
        if (is_numeric($grade) && is_numeric($start_year)) {//frank
            $end_year = $start_year + (12 - $grade);
        } else {
            $end_year = 2024;
        }
        if ($end_year > 2024) {
            $end_year = 2024;
            $extension_txt = 'Should the project be extended beyond 2024, your scholarship will still be valid within the extension period.';
        }
        $ben_sch_status = $beneficiary->ben_sch_status;
        $debs_name = $beneficiary->debs_fname . '&nbsp;' . $beneficiary->debs_lname;
        $debs_phone = $beneficiary->phone;
        $debs_title = $beneficiary->debs_title;
        $learner_sch = $beneficiary->school_name;
        if ($beneficiary->debs_fname == '' && $beneficiary->debs_lname == '') {
            $debs_name = '_________________';
        }
        if ($debs_phone == '') {
            $debs_phone = '_________________';
        }
        if ($ben_sch_status == '') {
            $ben_sch_status = '_________________';
        }
        $sch_district_name = $beneficiary->sch_dist_name;
        // ===========///////////////////////////////////////////////////////////////////==========//
        $date = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', time());
        PDF::SetTitle('Offer Letters');
        PDF::SetAutoPageBreak(TRUE, 0);//true sets it to on and 0 means margin is zero from sides
        //PDF::SetMargins($left,$top,$right = -1,$keepmargins = false)
        PDF::SetMargins(10, 5, 10, true);
        PDF::AddPage();

        PDF::SetLineStyle(array('width' => 0.50, 'color' => array(0, 0, 0)));
        PDF::Rect(0, 0, PDF::getPageWidth(), PDF::getPageHeight());
        PDF::SetFont('times', 'B', 11);
        //headers
        $image_path = '\resources\images\kgs-logo.png';
        
        PDF::Image(getcwd() . $image_path, 0, 2, 25, 28, 'PNG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
        PDF::SetY(32);
        PDF::Cell(0, 2, 'REPUBLIC OF ZAMBIA', 0, 1, 'C');
        PDF::Cell(0, 5, 'MINISTRY OF EDUCATION', 0, 1, 'C');
        PDF::Cell(0, 5, 'P.O BOX 50093', 0, 1, 'C');
        PDF::Cell(0, 5, 'LUSAKA', 0, 1, 'C');
        PDF::SetY(2);
        // Start clipping.
        PDF::SetFont('times', 'I', 9);
        PDF::StartTransform();
        $html = '<p>All communications should be addressed to:<br>';
        $html .= 'The Permanent Secretary Ministry of Education.</p>';
        $html .= '<p>Not to an individual by name</p>';
        $html .= '<p>Telephone: <span>250855/251315/251283<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                              251293/211318/251219<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                              251003/251319</span></p>';
        
        PDF::writeHTMLCell(70, 0, 5, 9, $html, 0, 1, 0, 1, 'L');
        PDF::SetFont('times', 'BI', 9);
        PDF::SetY(5);
        PDF::Cell(0, 2, 'In reply please quote:', 0, 1, 'R');
        PDF::Cell(0, 2, 'No:' . $ref, 0, 1, 'R');
        //PDF::SetY(40);
        PDF::SetFont('times', '', 10);
        $html = '<p>' . $date . '</p>';
        $html .= '<p>Province of School:&nbsp;&nbsp;<b>' . strtoupper($beneficiary->province_name) . '</b><br>';
        $html .= 'District of School:&nbsp;&nbsp;<b>' . $beneficiary->sch_dist_code . '-' . strtoupper($beneficiary->sch_dist_name) . '</b><br>';
        $html .= 'School of Learner:&nbsp;&nbsp;<b>' . strtoupper($learner_sch) . '</b><br>';
        $html .= 'District of Learner:&nbsp;&nbsp;<b>' . strtoupper($beneficiary->district_name2) . '</b><br>';
        $html .= 'CWAC:&nbsp;&nbsp;<b>' . strtoupper($beneficiary->cwac_txt) . '</b><br>';
        $html .= 'C/O:&nbsp;&nbsp;<b>' . strtoupper($beneficiary->hhh_fname) . ' ' . strtoupper($beneficiary->hhh_lname) . '</b><br>';
        $html .= 'HHH NRC:&nbsp;&nbsp;<b>' . strtoupper($beneficiary->hhh_nrc_number) . '</b><br></p>';
        // PDF::writeHTMLCell(0, 0, 5, 60, $html, 0, 1, 0, 1, 'L');
        PDF::writeHTMLCell(0, 0, 5, 50, $html, 0, 1, 0, 1, 'L');
        PDF::SetFont('times', '', 10);
        $salutation = '<p>Dear <b>' . strtoupper($beneficiary->first_name) . '&nbsp;' . strtoupper($beneficiary->last_name) . ' (' . $beneficiary->beneficiary_id . ')</b></p>';
        $salutation .= '<p><b>RE: </b><u><b>PROVISIONAL OFFER LETTER FOR KEEPING  GIRLS IN SCHOOL AND BEYOND (KGS) INITIATIVE SCHOLARSHIP (' . $start_year . ') - YOURSELF</b></u></p>';
        PDF::writeHTMLCell(0, 0, 5, 95, $salutation, 0, 1, 0, 1, 'L');

        $letter = '<p align="justify"><br>Refer to the above captioned subject.<br>';//p1
        $letter .= 'The Government of the Republic of Zambia, through the Ministry of Education, is implementing the Keeping Girls in School and Beyond (KGS) Initiative; a component of the Girls’ Education and Women’s Empowerment and Livelihoods for Human Capital (GEWEL 2.0) Project. The Ministry has identified girls residing in Social Cash Transfer households to be awarded scholarships in order for them to access Upper Primary and Secondary Education through payment of school and examination fees, education grant and other school-related support services. The annual education grant will be paid directly to your household or to respective Guidance and Counselling Teacher by your host school. The Education grant is meant to help your household purchase for you other school-related requisites to ensure that you stay in school and attend classes regularly. The school related fees will be paid directly to the host school termly. This scholarship package is subject to revision depending on the prevailing circumstances.</p>';

            $grade = $grade == 8 ? 'in <b> Form I' : 'in <b> Grade '. $grade;
        
            $letter .= '<p align="justify">Given the above, I would like to congratulate you on your selection for the KGS Scholarship at  
                    <b>' . strtoupper($learner_sch) . '</b> as a <b>' . strtoupper($ben_sch_status) . 
            '</b> beginning in Term 3 of <b>' . $start_year . '</b> ' . $grade . '</b>. This is a provisional offer that is dependent on you reporting to school and signing the checklist as soon as you receive this letter and subsequently attending classes regularly. </p>';

        $letter .= '<p align="justify">When reporting to your assigned school, please remember to carry this offer letter. Upon producing this offer letter, you will be enrolled in school under the KGS Scholarship.<b>' . $grace_period . '</b>.</p>';

        $letter .= '<p align="justify">Further, you are encouraged to work hard at school to avoid failing which may result in you losing the scholarship. By taking up this scholarship, you also give express permission to KGS to access your academic results for purposes of monitoring your performance in order to provide you with extra support services.</p>';

        $letter .= '<p align="justify">For any clarification or concern, please contact any member of the CWAC in your area. 
                    In case your concerns are not addressed you can contact the Head Teacher at the school where you have been assigned. 
                    In an event that your Head Teacher does not address your concerns, please do not hesitate to contact the District 
                    Education Board Secretary (DEBS) <b>' . $debs_title . ' ' . $debs_name . '</b> in your district on the telephone 
                    number <b>' . $debs_phone . '</b> or the KGS Project Coordinator, <b>Mr. Willie C. Kaputo</b> on <b>0955983224.</b></p>';

        PDF::writeHTMLCell(0, 0, 5, 110, $letter, 0, 1, 0, 1, 'L');
        PDF::ln(5);

        $signP1 = '<p><b>' . $ps . '</b></p>';
        $signP2 = '<p>KGS Coordinator<br><b><i>for</i> Permanent Secretary - Administration<br>Ministry of Education</b></p>';
        $footerText = '<p>*For any complaint relating to Keeping Girls in School or your welfare <b>(GBV, School Bullying or HIV)</b> 
                please call on <b>+260953003103</b> <b>(regular call charges may apply)</b>) or Toll free on 994.  <em>*By accepting this offer, you pledge not to engage in any form of GBV</em></p>';

        PDF::Image(getcwd() . $signature_path, 0, '', 25, 12, '', '', 'N', true, 300, 'L', false, false, 0, false, false, false);
        PDF::writeHTML($signP1, true, 0, true, true);
        PDF::writeHTML($signP2, true, 0, true, true);
        //==========///////////////////////////////////////////////////////////////////===========//
        PDF::SetY(-18);
        PDF::writeHTML($footerText, true, 0, true, true);
    }
    
    public function inSCHOfferLetterInitial($beneficiary, $details)
    {//frank update
        $start_year = $details->start_year;
        $orig_reporting_date = $details->reporting_date;
        //Maureen
        // $reporting_date = converter22($details->reporting_date);
        $reporting_date = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', strtotime(str_replace('/', '-', $details->reporting_date)));
        // $grace_period = converter22($details->grace_period);
        $grace_period = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', strtotime(str_replace('/', '-', $details->grace_period)));
            $grace_period = 'The grace period for reporting for Term 3, 2025 is up to <b>' . converter22($details->grace_period) . '.</b>';
        $ps = $details->ps;
        $signature = $details->signature_specimen;
        $signature_path = '\resources\images\signatures\\' . $signature;
        //get the last ID//letter generation logging here
        $last_id = DB::table('letters_gen_log')->max('id');
        $log_check = Db::table('letters_gen_log')->where('girl_id', $beneficiary->id)->first();
        if (is_null($log_check)) {
            $serial = $last_id + 1;
            $serial = str_pad($serial, 5, 0, STR_PAD_LEFT);
            $ref = 'KGS/inschool/' . $serial;
            $params = array(
                'girl_id' => $beneficiary->id,
                'letter_ref' => $ref,
                'generated_by' => \Auth::user()->id,
                'generated_on' => Carbon::now()
            );
            DB::table('letters_gen_log')->insert($params);
        } else {
            $ref = $log_check->letter_ref;
        }
        $letter_tracker = $start_year . strtotime($orig_reporting_date);
        $last_version = DB::table('letters_gen_tracker')
            ->where('girl_id', $beneficiary->id)
            ->where('tracker', $letter_tracker)
            ->max('version');
        if (is_null($last_version) || $last_version == '') {
            $version = 1;
        } else {
            $version = $last_version + 1;
        }
        $log_data = array(
            'girl_id' => $beneficiary->id,
            'start_year' => $start_year,
            'reporting_date' => $reporting_date,
            'grace_period' => $grace_period,
            'ps' => $ps,
            'signature_specimen' => $signature,
            'girl_cwac' => $beneficiary->cwac_id,
            'girl_district' => $beneficiary->district_id,
            'girl_first_name' => $beneficiary->first_name,
            'girl_last_name' => $beneficiary->last_name,
            'beneficiary_school_status' => $beneficiary->beneficiary_school_status,
            'version' => $version,
            'grade' => $beneficiary->current_school_grade,
            'school_id' => $beneficiary->school_id,
            'tracker' => $letter_tracker,
            'created_at' => Carbon::now(),
            'created_by' => \Auth::user()->id
        );
        DB::table('letters_gen_tracker')
            ->insert($log_data);
        //end letter logging here
        //check if learner is under promotion
        if ($beneficiary->under_promotion == 1) {
            $grade = ($beneficiary->current_school_grade + 1);
        } else {
            $grade = $beneficiary->current_school_grade;
        }
        $extension_txt = '';
        $end_year = $start_year + (12 - $grade);
        if ($end_year > 2024) {
            $end_year = 2024;
            $extension_txt = 'Should the project be extended beyond 2024, your scholarship will still be valid within the extension period.';
        }
        $ben_sch_status = $beneficiary->ben_sch_status;
        $debs_name = $beneficiary->debs_fname . '&nbsp;' . $beneficiary->debs_lname;
        $debs_phone = $beneficiary->phone;
        $debs_title = $beneficiary->debs_title;
        $learner_sch = $beneficiary->school_name;
        if ($beneficiary->debs_fname == '' && $beneficiary->debs_lname == '') {
            $debs_name = '_________________';
        }
        if ($debs_phone == '') {
            $debs_phone = '_________________';
        }
        if ($ben_sch_status == '') {
            $ben_sch_status = '_________________';
        }
        $sch_district_name = $beneficiary->sch_dist_name;
        // ===========///////////////////////////////////////////////////////////////////==========//
        $date = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', time());
        PDF::SetTitle('Offer Letters');
        PDF::SetAutoPageBreak(TRUE, 0);//true sets it to on and 0 means margin is zero from sides
        //PDF::SetMargins($left,$top,$right = -1,$keepmargins = false)
        PDF::SetMargins(10, 5, 10, true);
        PDF::AddPage();

        PDF::SetLineStyle(array('width' => 0.50, 'color' => array(0, 0, 0)));
        PDF::Rect(0, 0, PDF::getPageWidth(), PDF::getPageHeight());
        PDF::SetFont('times', 'B', 11);
        //headers
        $image_path = '\resources\images\kgs-logo.png';
        // Image($file, $x='', $y='', $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false)
        PDF::Image(getcwd() . $image_path, 0, 2, 25, 28, 'PNG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
        PDF::SetY(32);
        PDF::Cell(0, 2, 'REPUBLIC OF ZAMBIA', 0, 1, 'C');
        PDF::Cell(0, 5, 'MINISTRY OF EDUCATION', 0, 1, 'C');
        PDF::Cell(0, 5, 'P.O BOX 50093', 0, 1, 'C');
        PDF::Cell(0, 5, 'LUSAKA', 0, 1, 'C');
        PDF::SetY(2);
        // Start clipping.
        PDF::SetFont('times', 'I', 9);
        PDF::StartTransform();
        $html = '<p>All communications should be addressed to:<br>';
        $html .= 'The Permanent Secretary Ministry of Education.</p>';
        $html .= '<p>Not to an individual by name</p>';
        $html .= '<p>Telephone: <span>250855/251315/251283<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                              251293/211318/251219<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                              251003/251319</span></p>';
        //$html = $left_header_one;
        #writeHTMLCell(w, h, x, y, html = '', border = 0, ln = 0, fill = 0, reseth = true, align = '', autopadding = true)
        PDF::writeHTMLCell(70, 0, 5, 9, $html, 0, 1, 0, 1, 'L');
        PDF::SetFont('times', 'BI', 9);
        PDF::SetY(7);
        PDF::Cell(0, 2, 'In reply please quote:', 0, 1, 'R');
        PDF::Cell(0, 2, 'No:' . $ref, 0, 1, 'R');
        //PDF::SetY(40);
        PDF::SetFont('times', '', 10);
        $html = '<p>' . $date . '</p>';
        $html .= '<p>Province of School:&nbsp;&nbsp;' . strtoupper($beneficiary->province_name) . '<br>';
        $html .= 'District of School:&nbsp;&nbsp;' . $beneficiary->sch_dist_code . '-' . strtoupper($beneficiary->sch_dist_name) . '<br>';
        $html .= 'School of Learner:&nbsp;&nbsp;' . strtoupper($learner_sch) . '<br>';
        $html .= 'District of Learner:&nbsp;&nbsp;' . strtoupper($beneficiary->district_name2) . '<br>';
        $html .= 'CWAC:&nbsp;&nbsp;' . strtoupper($beneficiary->cwac_txt) . '<br>';
        $html .= 'C/O:&nbsp;&nbsp;' . strtoupper($beneficiary->hhh_fname) . ' ' . strtoupper($beneficiary->hhh_lname) . '<br>';
        $html .= 'HHH NRC:&nbsp;&nbsp;' . strtoupper($beneficiary->hhh_nrc_number) . '<br></p>';
        PDF::writeHTMLCell(0, 0, 5, 60, $html, 0, 1, 0, 1, 'L');
        PDF::SetFont('times', '', 11);
        $salutation = '<p>Dear <b>' . strtoupper($beneficiary->first_name) . '&nbsp;' . strtoupper($beneficiary->last_name) . ' (' . $beneficiary->beneficiary_id . ')</b></p>';
        $salutation .= '<p><b>RE: </b><u><b>PROVISIONAL OFFER LETTER FOR KEEPING GIRLS IN SCHOOL AND BEYOND (KGS) INITIATIVE SCHOLARSHIP (' . $start_year . ') - YOURSELF</b></u></p>';
        PDF::writeHTMLCell(0, 0, 5, 105, $salutation, 0, 1, 0, 1, 'L');
            
        $letter = '<p align="justify"><br>Refer to the above captioned subject.<br>';//p1
        $letter .= 'The Government of the Republic of Zambia, through the Ministry of Education, is implementing the Keeping Girls in School and Beyond (KGS) Initiative; a component of the Girls’ Education and Women’s Empowerment and Livelihoods for Human Capital (GEWEL 2.0) Project. The Ministry has identified girls residing in Social Cash Transfer households to be awarded scholarships in order for them to access Upper Primary and Secondary Education through payment of school and examination fees, education grant and other school-related support services. The annual education grant will be paid directly to your household or to respective Guidance and Counselling Teacher by your host school. The Education grant is meant to help your household purchase for you other school-related requisites to ensure that you stay in school and attend classes regularly. The school related fees will be paid directly to the host school termly. This scholarship package is subject to revision depending on the prevailing circumstances.</p>';
        

        $grade = $grade == 8 ? 'in <b> Form I' : 'in <b> Grade '. $grade;

        $letter .= '<p align="justify">Given the above, I would like to congratulate you on your selection for the KGS Scholarship at  
                    <b>' . strtoupper($learner_sch) . '</b> as a <b>' . strtoupper($ben_sch_status) . '</b> beginning in Term 3 of <b>' . $start_year . 
            '</b> ' . $grade . '</b>. 
            This is a provisional offer that is dependent on you reporting to school and signing the checklist as soon as you receive this letter and subsequently attending classes regularly. </p>';

        $letter .= '<p align="justify">When reporting to your assigned school, please remember to carry this offer letter. Upon producing this offer letter, you will be enrolled in school under the KGS Scholarship.<b>' . $grace_period . '</b>.</p>';

        $letter .= '<p align="justify">Further, you are encouraged to work hard at school to avoid failing which may result in you losing the scholarship. By taking up this scholarship, you also give express permission to KGS to access your academic results for purposes of monitoring your performance in order to provide you with extra support services.</p>';

        $letter .= '<p align="justify">For any clarification or concern, please contact any member of the CWAC in your area. 
                    In case your concerns are not addressed you can contact the Head Teacher at the school where you have been assigned. 
                    In an event that your Head Teacher does not address your concerns, please do not hesitate to contact the District 
                    Education Board Secretary (DEBS) <b>' . $debs_title . ' ' . $debs_name . '</b> in your district on the telephone 
                    number <b>' . $debs_phone . '</b> or the KGS Project Coordinator, <b>Mr. Willie C. Kaputo</b> on <b>0955983224.</b></p>';

        PDF::writeHTMLCell(0, 0, 5, 110, $letter, 0, 1, 0, 1, 'L');
        PDF::ln(5);

        $signP1 = '<p><b>' . $ps . '</b></p>';
        $signP2 = '<p>KGS Coordinator<br><b><i>for</i> Permanent Secretary - Administration<br>Ministry of Education</b></p>';
        $footerText = '<p>*For any complaint relating to Keeping Girls in School or your welfare <b>(GBV, School Bullying or HIV)</b> 
                please call on <b>+260953003103</b> <b>(regular call charges may apply)</b>) or Toll free on 994.  <em>*By accepting this offer, you pledge not to engage in any form of GBV</em></p>';

        PDF::Image(getcwd() . $signature_path, 0, '', 25, 12, '', '', 'N', true, 300, 'L', false, false, 0, false, false, false);
        PDF::writeHTML($signP1, true, 0, true, true);
        PDF::writeHTML($signP2, true, 0, true, true);
        //==========///////////////////////////////////////////////////////////////////===========//
        PDF::SetY(-18);
        PDF::writeHTML($footerText, true, 0, true, true);
    }

    
    public function inSCHOfferLetter($beneficiary, $details)
    {//frank update
        $start_year = $details->start_year;
        $orig_reporting_date = $details->reporting_date;
        //Maureen
        // $reporting_date = converter22($details->reporting_date);
        $reporting_date = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', strtotime(str_replace('/', '-', $details->reporting_date)));
        // $grace_period = converter22($details->grace_period);
        $grace_period = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', strtotime(str_replace('/', '-', $details->grace_period)));
        $ps = $details->ps;
        $signature = $details->signature_specimen;
        $signature_path = '\resources\images\signatures\\' . $signature;
        //get the last ID//letter generation logging here
        $last_id = DB::table('letters_gen_log')->max('id');
        $log_check = Db::table('letters_gen_log')->where('girl_id', $beneficiary->id)->first();
        if (is_null($log_check)) {
            $serial = $last_id + 1;
            $serial = str_pad($serial, 5, 0, STR_PAD_LEFT);
            $ref = 'KGS/inschool/' . $serial;
            $params = array(
                'girl_id' => $beneficiary->id,
                'letter_ref' => $ref,
                'generated_by' => \Auth::user()->id,
                'generated_on' => Carbon::now()
            );
            DB::table('letters_gen_log')->insert($params);
        } else {
            $ref = $log_check->letter_ref;
        }
        $letter_tracker = $start_year . strtotime($orig_reporting_date);
        $last_version = DB::table('letters_gen_tracker')
            ->where('girl_id', $beneficiary->id)
            ->where('tracker', $letter_tracker)
            ->max('version');
        if (is_null($last_version) || $last_version == '') {
            $version = 1;
        } else {
            $version = $last_version + 1;
        }
        $log_data = array(
            'girl_id' => $beneficiary->id,
            'start_year' => $start_year,
            'reporting_date' => $reporting_date,
            'grace_period' => $grace_period,
            'ps' => $ps,
            'signature_specimen' => $signature,
            'girl_cwac' => $beneficiary->cwac_id,
            'girl_district' => $beneficiary->district_id,
            'girl_first_name' => $beneficiary->first_name,
            'girl_last_name' => $beneficiary->last_name,
            'beneficiary_school_status' => $beneficiary->beneficiary_school_status,
            'version' => $version,
            'grade' => $beneficiary->current_school_grade,
            'school_id' => $beneficiary->school_id,
            'tracker' => $letter_tracker,
            'created_at' => Carbon::now(),
            'created_by' => \Auth::user()->id
        );
        DB::table('letters_gen_tracker')
            ->insert($log_data);
        //end letter logging here
        //check if learner is under promotion
        if ($beneficiary->under_promotion == 1) {
            $grade = ($beneficiary->current_school_grade + 1);
        } else {
            $grade = $beneficiary->current_school_grade;
        }
        $extension_txt = '';
        $end_year = $start_year + (12 - $grade);
        if ($end_year > 2024) {
            $end_year = 2024;
            $extension_txt = 'Should the project be extended beyond 2024, your scholarship will still be valid within the extension period.';
        }
        $ben_sch_status = $beneficiary->ben_sch_status;
        $debs_name = $beneficiary->debs_fname . '&nbsp;' . $beneficiary->debs_lname;
        $debs_phone = $beneficiary->phone;
        $debs_title = $beneficiary->debs_title;
        $learner_sch = $beneficiary->school_name;
        if ($beneficiary->debs_fname == '' && $beneficiary->debs_lname == '') {
            $debs_name = '_________________';
        }
        if ($debs_phone == '') {
            $debs_phone = '_________________';
        }
        if ($ben_sch_status == '') {
            $ben_sch_status = '_________________';
        }
        $sch_district_name = $beneficiary->sch_dist_name;
        // ===========///////////////////////////////////////////////////////////////////==========//
        $date = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', time());
        PDF::SetTitle('Offer Letters');
        PDF::SetAutoPageBreak(TRUE, 0);//true sets it to on and 0 means margin is zero from sides
        //PDF::SetMargins($left,$top,$right = -1,$keepmargins = false)
        PDF::SetMargins(10, 5, 10, true);
        PDF::AddPage();

        PDF::SetLineStyle(array('width' => 0.50, 'color' => array(0, 0, 0)));
        PDF::Rect(0, 0, PDF::getPageWidth(), PDF::getPageHeight());
        PDF::SetFont('times', 'B', 11);
        //headers
        $image_path = '\resources\images\kgs-logo.png';
        // Image($file, $x='', $y='', $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false)
        PDF::Image(getcwd() . $image_path, 0, 2, 25, 28, 'PNG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
        PDF::SetY(32);
        PDF::Cell(0, 2, 'REPUBLIC OF ZAMBIA', 0, 1, 'C');
        PDF::Cell(0, 5, 'MINISTRY OF EDUCATION', 0, 1, 'C');
        PDF::Cell(0, 5, 'P.O BOX 50093', 0, 1, 'C');
        PDF::Cell(0, 5, 'LUSAKA', 0, 1, 'C');
        PDF::SetY(2);
        // Start clipping.
        PDF::SetFont('times', 'I', 9);
        PDF::StartTransform();
        $html = '<p>All communications should be addressed to:<br>';
        $html .= 'The Permanent Secretary Ministry of Education.</p>';
        $html .= '<p>Not to an individual by name</p>';
        $html .= '<p>Telephone: <span>250855/251315/251283<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                              251293/211318/251219<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                              251003/251319</span></p>';
        //$html = $left_header_one;
        #writeHTMLCell(w, h, x, y, html = '', border = 0, ln = 0, fill = 0, reseth = true, align = '', autopadding = true)
        PDF::writeHTMLCell(70, 0, 5, 9, $html, 0, 1, 0, 1, 'L');
        PDF::SetFont('times', 'BI', 9);
        PDF::SetY(7);
        PDF::Cell(0, 2, 'In reply please quote:', 0, 1, 'R');
        PDF::Cell(0, 2, 'No:' . $ref, 0, 1, 'R');
        //PDF::SetY(40);
        PDF::SetFont('times', '', 10);
        $html = '<p><b>' . $date . '</b></p>';
        $html .= '<p>Province of School:&nbsp;&nbsp;<b>' . strtoupper($beneficiary->province_name) . '</b><br>';
        $html .= 'District of School:&nbsp;&nbsp;<b>' . $beneficiary->sch_dist_code . '-' . strtoupper($beneficiary->sch_dist_name) . '</b><br>';
        $html .= 'School of Learner:&nbsp;&nbsp;<b>' . strtoupper($learner_sch) . '</b><br>';
        $html .= 'District of Learner:&nbsp;&nbsp;<b>' . strtoupper($beneficiary->district_name2) . '</b><br>';
        $html .= 'CWAC:&nbsp;&nbsp;<b>' . strtoupper($beneficiary->cwac_txt) . '</b><br>';
        $html .= 'C/O:&nbsp;&nbsp;<b>' . strtoupper($beneficiary->hhh_fname) . ' ' . strtoupper($beneficiary->hhh_lname) . '</b><br>';
        $html .= 'HHH NRC:&nbsp;&nbsp;<b>' . strtoupper($beneficiary->hhh_nrc_number) . '</b><br></p>';
        PDF::writeHTMLCell(0, 0, 5, 50, $html, 0, 1, 0, 1, 'L');
        PDF::SetFont('times', '', 10);
        $salutation = '<p>Dear <b>' . strtoupper($beneficiary->first_name) . '&nbsp;' . strtoupper($beneficiary->last_name) . ' (' . $beneficiary->beneficiary_id . ')</b></p>';
        $salutation .= '<p><b>RE: </b><u><b>PROVISIONAL OFFER LETTER FOR KEEPING GIRLS IN SCHOOL AND BEYOND (KGS) INITIATIVE SCHOLARSHIP (' . $start_year . ') - YOURSELF</b></u></p>';
        PDF::writeHTMLCell(0, 0, 5, 90, $salutation, 0, 1, 0, 1, 'L');
            
        $letter = '<p align="justify"><br>Refer to the above captioned subject.<br>';//p1
        $letter .= 'The Government of the Republic of Zambia, through the Ministry of Education, is implementing the Keeping Girls in School and Beyond (KGS) Initiative; a component of the Girls’ Education and Women’s Empowerment and Livelihoods for Human Capital (GEWEL 2.0) Project. The Ministry has identified girls residing in Social Cash Transfer households to be awarded scholarships in order for them to access Upper Primary and Secondary Education through payment of school and examination fees, education grant and other school-related support services. The annual education grant will be paid directly to your household or to respective Guidance and Counselling Teacher by your host school. The Education grant is meant to help your household purchase for you other school-related requisites to ensure that you stay in school and attend classes regularly. The school related fees will be paid directly to the host school termly. This scholarship package is subject to revision depending on the prevailing circumstances.</p>';
        
        $grade = $grade == 8 ? 'in <b> Form I' : 'in <b> Grade '. $grade;

        $letter .= '<p align="justify">Given the above, I would like to congratulate you on your selection for the KGS Scholarship at  
                    <b>' . strtoupper($learner_sch) . '</b> as a <b>' . strtoupper($ben_sch_status) . '</b> beginning in Term 3 of <b>' . $start_year . 
                    '</b> ' . $grade . '</b>. 
                    This is a provisional offer that is dependent on you reporting to school and signing the checklist as soon as you receive this letter and subsequently attending classes regularly. </p>';

        $letter .= '<p align="justify">When reporting to your assigned school, please remember to carry this offer letter. Upon producing this offer letter, you will be enrolled in school under the KGS Scholarship.<b>' . $grace_period . '</b>.</p>';

        $letter .= '<p align="justify">Further, you are encouraged to work hard at school to avoid failing which may result in you losing the scholarship. By taking up this scholarship, you also give express permission to KGS to access your academic results for purposes of monitoring your performance in order to provide you with extra support services.</p>';

        $letter .= '<p align="justify">For any clarification or concern, please contact any member of the CWAC in your area. 
                    In case your concerns are not addressed you can contact the Head Teacher at the school where you have been assigned. 
                    In an event that your Head Teacher does not address your concerns, please do not hesitate to contact the District 
                    Education Board Secretary (DEBS) <b>' . $debs_title . ' ' . $debs_name . '</b> in your district on the telephone 
                    number <b>' . $debs_phone . '</b> or the KGS Project Coordinator, <b>Mr. Willie C. Kaputo</b> on <b>0955983224.</b></p>';

        PDF::writeHTMLCell(0, 0, 5, 110, $letter, 0, 1, 0, 1, 'L');
        PDF::ln(5);

        $signP1 = '<p><b>' . $ps . '</b></p>';
        $signP2 = '<p>KGS Coordinator<br><b><i>for</i> Permanent Secretary - Administration<br>Ministry of Education</b></p>';
        $footerText = '<p>*For any complaint relating to Keeping Girls in School and Beyond Initiative or your welfare <b>(GBV, School Bullying or HIV)</b> 
                please call on <b>+260953003103</b> <b>(regular call charges may apply)</b>) or Toll free on 994.  <em>*By accepting this offer, you pledge not to engage in any form of GBV</em></p>';

        PDF::Image(getcwd() . $signature_path, 0, '', 25, 12, '', '', 'N', true, 300, 'L', false, false, 0, false, false, false);
        PDF::writeHTML($signP1, true, 0, true, true);
        PDF::writeHTML($signP2, true, 0, true, true);
        //==========///////////////////////////////////////////////////////////////////===========//
        PDF::SetY(-18);
        PDF::writeHTML($footerText, true, 0, true, true);
    }

    public function outOfSCHProvisionalOfferLetter($beneficiary, $details)
    {
        $start_year = $details->start_year;
        $orig_reporting_date = $details->reporting_date;
        $ps = $details->ps;
        $signature = $details->signature_specimen;
        $signature_path = '\resources\images\signatures\\' . $signature;
        //replaceable
        $reporting_date = '<b>as soon as you receive this letter</b>';
        $grace_period = '';
        //get the last ID//letter generation logging here
        $skip_matching = $beneficiary->skip_matching;
        $salutation_category = 'OUT OF SCHOOL';
        $ref_category = 'outofschool';
        if ($skip_matching == 1) {
            $salutation_category = 'IN SCHOOL';
            $ref_category = 'inschool';
            //Date format changed
            /* $reporting_date = 'on <b>' . converter22($details->reporting_date) . '</b>';*/
            // $grace_period = 'The grace period for reporting is until <b>' . converter22($details->grace_period) . '.</b>';
            $grace_period = 'The grace period for reporting for Term 3, 2025 is up to <b>' . converter22($details->grace_period) . '.</b>';
            $reporting_date = 'on <b>' . date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', strtotime(str_replace('/', '-', $details->reporting_date))) . '</b>';
            // $grace_period = 'The grace period for reporting is up to <b>' . date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', strtotime(str_replace('/', '-', $details->grace_period))) . '.</b>';
        }
        $last_id = DB::table('letters_gen_log')->max('id');
        $log_check = Db::table('letters_gen_log')->where('girl_id', $beneficiary->id)->first();
        if (is_null($log_check)) {
            $serial = $last_id + 1;
            $serial = str_pad($serial, 5, 0, STR_PAD_LEFT);
            $ref = 'KGS/' . $ref_category . '/' . $serial;
            $params = array(
                'girl_id' => $beneficiary->id,
                'letter_ref' => $ref,
                'generated_by' => \Auth::user()->id,
                'generated_on' => Carbon::now()
            );
            DB::table('letters_gen_log')->insert($params);
        } else {
            $ref = $log_check->letter_ref;
        }
        $letter_tracker = $start_year . strtotime($orig_reporting_date);
        $last_version = DB::table('letters_gen_tracker')
            ->where('girl_id', $beneficiary->id)
            ->where('tracker', $letter_tracker)
            ->max('version');
        if (is_null($last_version) || $last_version == '') {
            $version = 1;
        } else {
            $version = $last_version + 1;
        }
        $log_data = array(
            'girl_id' => $beneficiary->id,
            'start_year' => $start_year,
            'reporting_date' => $reporting_date,
            'grace_period' => $grace_period,
            'ps' => $ps,
            'signature_specimen' => $signature,
            'girl_cwac' => $beneficiary->cwac_id,
            'girl_district' => $beneficiary->district_id,
            'girl_first_name' => $beneficiary->first_name,
            'girl_last_name' => $beneficiary->last_name,
            'beneficiary_school_status' => $beneficiary->beneficiary_school_status,
            'version' => $version,
            'grade' => $beneficiary->current_school_grade,
            'school_id' => $beneficiary->school_id,
            'tracker' => $letter_tracker,
            'created_at' => Carbon::now(),
            'created_by' => \Auth::user()->id
        );
        DB::table('letters_gen_tracker')
            ->insert($log_data);
        //end letter logging here
        //check if learner is under promotion
        /* if ($beneficiary->under_promotion == 1) {
             $grade = ($beneficiary->current_school_grade + 1);
         } else {
             $grade = $beneficiary->current_school_grade;
         }*/
        $grade = '________________';
        $extension_txt = '';
        $end_year = 2024;//$start_year + (12 - $grade);
        if ($end_year > 2024) {
            $end_year = 2024;
            $extension_txt = 'Should the project be extended beyond 2024, your scholarship will still be valid within the extension period.';
        }
        $ben_sch_status = '____________________';
        $debs_name = $beneficiary->debs_fname . '&nbsp;' . $beneficiary->debs_lname;
        $debs_phone = $beneficiary->phone;
        $debs_title = $beneficiary->debs_title;
        $learner_sch = '_____________________';
        if ($beneficiary->debs_fname == '' && $beneficiary->debs_lname == '') {
            $debs_name = '_________________';
        }
        if ($debs_phone == '') {
            $debs_phone = '_________________';
        }
        // ===========///////////////////////////////////////////////////////////////////==========//
        $date = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', time());
        PDF::SetTitle('Offer Letters');
        PDF::SetAutoPageBreak(TRUE, 0);//true sets it to on and 0 means margin is zero from sides
        //PDF::SetMargins($left,$top,$right = -1,$keepmargins = false)
        PDF::SetMargins(10, 5, 10, true);
        PDF::AddPage();

        PDF::SetLineStyle(array('width' => 0.50, 'color' => array(0, 0, 0)));
        PDF::Rect(0, 0, PDF::getPageWidth(), PDF::getPageHeight());
        PDF::SetFont('times', 'B', 11);
        //headers
        $image_path = '\resources\images\kgs-logo.png';
        // Image($file, $x='', $y='', $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false)
        PDF::Image(getcwd() . $image_path, 0, 2, 25, 28, 'PNG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
        PDF::SetY(32);
        PDF::Cell(0, 2, 'REPUBLIC OF ZAMBIA', 0, 1, 'C');
        PDF::Cell(0, 5, 'MINISTRY OF EDUCATION', 0, 1, 'C');
        PDF::Cell(0, 5, 'P.O BOX 50093', 0, 1, 'C');
        PDF::Cell(0, 5, 'LUSAKA', 0, 1, 'C');
        PDF::SetY(2);
        // Start clipping.
        PDF::SetFont('times', 'I', 9);
        PDF::StartTransform();
        $html = '<p>All communications should be addressed to:<br>';
        $html .= 'The Permanent Secretary Ministry of Education, Science, Vocational Training and Early Education.</p>';
        $html .= '<p>Not to an individual by name</p>';
        $html .= '<p>Telephone: <span>250855/251315/251283<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                              251293/211318/251219<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                              251003/251319</span></p>';
        //$html = $left_header_one;
        #writeHTMLCell(w, h, x, y, html = '', border = 0, ln = 0, fill = 0, reseth = true, align = '', autopadding = true)
        PDF::writeHTMLCell(70, 0, 5, 9, $html, 0, 1, 0, 1, 'L');
        PDF::SetFont('times', 'BI', 9);
        PDF::SetY(7);
        PDF::Cell(0, 2, 'In reply please quote:', 0, 1, 'R');
        PDF::Cell(0, 2, 'No:' . $ref, 0, 1, 'R');
        //PDF::SetY(40);
        PDF::SetFont('times', '', 10);
        $html = '<p>' . $date . '</p>';
        $html .= '<p>Province of School:&nbsp;&nbsp;________________________________<br>';
        $html .= 'District of School:&nbsp;&nbsp;______________________________<br>';
        $html .= 'School of Learner:&nbsp;&nbsp;____________________________<br>';
        $html .= 'District of Learner:&nbsp;&nbsp;' . strtoupper($beneficiary->district_name2) . '<br>';
        $html .= 'CWAC:&nbsp;&nbsp;' . strtoupper($beneficiary->cwac_txt) . '<br>';
        $html .= 'C/O:&nbsp;&nbsp;' . strtoupper($beneficiary->hhh_fname) . ' ' . strtoupper($beneficiary->hhh_lname) . '<br>';
        $html .= 'HHH NRC:&nbsp;&nbsp;' . strtoupper($beneficiary->hhh_nrc_number) . '<br></p>';
        PDF::writeHTMLCell(0, 0, 5, 60, $html, 0, 1, 0, 1, 'L');
        PDF::SetFont('times', '', 10);
        $salutation = '<p>Dear <b>' . strtoupper($beneficiary->first_name) . '&nbsp;' . strtoupper($beneficiary->last_name) . ' (' . $beneficiary->beneficiary_id . ')</b></p>';
        $salutation .= '<p><b>RE: </b><u><b>PROVISIONAL OFFER LETTER FOR KEEPING  GIRLS IN SCHOOL SCHOLARSHIP (' . $start_year . ') - YOURSELF</b></u></p>';
        PDF::writeHTMLCell(0, 0, 5, 105, $salutation, 0, 1, 0, 1, 'L');
            
        $letter = '<p align="justify"><br>Refer to the above captioned subject.<br>';//p1
        $letter .= 'The Government of the Republic of Zambia, through the Ministry of Education, is implementing the Keeping Girls in School and Beyond (KGS) Initiative; a component of the Girls’ Education and Women’s Empowerment and Livelihoods for Human Capital (GEWEL 2.0) Project. The Ministry has identified girls residing in Social Cash Transfer households to be awarded scholarships in order for them to access Upper Primary and Secondary Education through payment of school and examination fees, education grant and other school-related support services. The annual education grant will be paid directly to your household or to respective Guidance and Counselling Teacher by your host school. The Education grant is meant to help your household purchase for you other school-related requisites to ensure that you stay in school and attend classes regularly. The school related fees will be paid directly to the host school termly. This scholarship package is subject to revision depending on the prevailing circumstances.</p>';


        $grade = $grade == 8 ? 'in <b> Form I' : 'in <b> Grade '. $grade;

        $letter .= '<p align="justify">Given the above, I would like to congratulate you on your selection for the KGS Scholarship at <b>' . strtoupper($learner_sch) . '</b> as a <b>' . strtoupper($ben_sch_status) . '</b> beginning in Term 3 of <b>' . $start_year . '</b> ' . $grade . '</b>. This is a provisional offer that is dependent on you reporting to school and signing the checklist as soon as you receive this letter and subsequently attending classes regularly. </p>';

        $letter .= '<p align="justify">When reporting to your assigned school, please remember to carry this offer letter. Upon producing this offer letter, you will be enrolled in school under the KGS Scholarship.<b>' . $grace_period . '</b>.</p>';

        $letter .= '<p align="justify">Further, you are encouraged to work hard at school to avoid failing which may result in you losing the scholarship. By taking up this scholarship, you also give express permission to KGS to access your academic results for purposes of monitoring your performance in order to provide you with extra support services.</p>';

        $letter .= '<p align="justify">For any clarification or concern, please contact any member of the CWAC in your area. 
                    In case your concerns are not addressed you can contact the Head Teacher at the school where you have been assigned. 
                    In an event that your Head Teacher does not address your concerns, please do not hesitate to contact the District 
                    Education Board Secretary (DEBS) <b>' . $debs_title . ' ' . $debs_name . '</b> in your district on the telephone 
                    number <b>' . $debs_phone . '</b> or the KGS Project Coordinator, <b>Mr. Willie C. Kaputo</b> on <b>0955983224.</b></p>';

        PDF::writeHTMLCell(0, 0, 5, 110, $letter, 0, 1, 0, 1, 'L');
        PDF::ln(5);

        $signP1 = '<p><b>' . $ps . '</b></p>';
        $signP2 = '<p>KGS Coordinator<br><b><i>for</i> Permanent Secretary - Administration<br>Ministry of Education</b></p>';
        $footerText = '<p>*For any complaint relating to Keeping Girls in School and Beyond Initiative or your welfare <b>(GBV, School Bullying or HIV)</b> 
                please call on <b>+260953003103</b> <b>(regular call charges may apply)</b>) or Toll free on 994.  <em>*By accepting this offer, you pledge not to engage in any form of GBV</em></p>';

        PDF::Image(getcwd() . $signature_path, 0, '', 25, 12, '', '', 'N', true, 300, 'L', false, false, 0, false, false, false);
        PDF::writeHTML($signP1, true, 0, true, true);
        PDF::writeHTML($signP2, true, 0, true, true);
        //==========///////////////////////////////////////////////////////////////////===========//
        PDF::SetY(-18);
        PDF::writeHTML($footerText, true, 0, true, true);
    }

    public function provisionalOfferLetter($beneficiary, $details)
    {
        $start_year = $details->start_year;
        $ps = $details->ps;
        $signature = $details->signature_specimen;
        $signature_path = '\resources\images\signatures\\' . $signature;
        //replaceable
        $reporting_date = '<b>as soon as you receive this letter</b>';
        $grace_period = '';
        $beneficiary_status = $beneficiary->beneficiary_status;
        $ref_category = 'inschool';
        $learner_sch = '__________________________';
        if ($beneficiary_status == 5) {
            $ref_category = 'outofschool';
        }
        //todo: start log
        $ref = $this->logOfferLetterGenerations($beneficiary, $ref_category, $details, $reporting_date, $grace_period);
        //todo: end letter logging here
        $grade = '________________';
        $extension_txt = '';
        if (is_numeric($grade) && is_numeric($start_year)) {//frank
            $end_year = $start_year + (12 - $grade);
        } else {
            $end_year = 2024;
        }
        if ($end_year > 2024) {
            $end_year = 2024;
            $extension_txt = 'Should the project be extended beyond 2024, your scholarship will still be valid within the extension period.';
        }
        $ben_sch_status = '____________________';
        $debs_name = $beneficiary->debs_fname . '&nbsp;' . $beneficiary->debs_lname;
        $debs_phone = $beneficiary->phone;
        $debs_title = $beneficiary->debs_title;

        if ($beneficiary->debs_fname == '' && $beneficiary->debs_lname == '') {
            $debs_name = '_________________';
        }
        if ($debs_phone == '') {
            $debs_phone = '_________________';
        }
        // ===========////////////////////////////////////////////////==========//
        //todo: offer letter header
        $this->offerLetterHeader($ref);
        //todo: end header
        //todo: specific header section
        if ($beneficiary_status == 5) {
            $this->provisionalSchMatchingOfferLetterHeader($beneficiary);
        } else {
            $this->provisionalSchPlacementOfferLetterHeader($beneficiary);
        }
        //todo: end specific header section
        PDF::SetFont('times', '', 10);
        $salutation = '<p>Dear <b>' . strtoupper($beneficiary->first_name) . '&nbsp;' . strtoupper($beneficiary->last_name) . ' (' . $beneficiary->beneficiary_id . ')</b><br><b>RE: </b><u><b>PROVISIONAL OFFER LETTER FOR KEEPING  GIRLS IN SCHOOL AND BEYOND (KGS) INITIATIVE SCHOLARSHIP (' . $start_year . ') - YOURSELF</b></u></p>';
        // PDF::writeHTMLCell(0, 0, 5, 110, $salutation, 0, 1, 0, 1, 'L');
        // PDF::writeHTMLCell(0, 0, 5, 105, $salutation, 0, 1, 0, 1, 'L');
        PDF::writeHTMLCell(0, 0, 5, 100, $salutation, 0, 1, 0, 1, 'L');
        $grade = $grade == 8 ? '</b> in <b> Form I' : '</b> in <b> Grade '. $grade;
        $letter = '<p align="justify"><br>Refer to the above captioned subject.<br>';//p1
        $letter .= 'The Government of the Republic of Zambia, through the Ministry of Education, is implementing the Keeping Girls in School and Beyond (KGS) Initiative; a component of the Girls’ Education and Women’s Empowerment and Livelihoods for Human Capital (GEWEL 2.0) Project. The Ministry has identified girls residing in Social Cash Transfer households to be awarded scholarships in order for them to access Upper Primary and Secondary Education through payment of school and examination fees, education grant and other school-related support services. The annual education grant will be paid directly to your household or to respective Guidance and Counselling Teacher by your host school. The Education grant is meant to help your household purchase for you other school-related requisites to ensure that you stay in school and attend classes regularly. The school related fees will be paid directly to the host school termly. This scholarship package is subject to revision depending on the prevailing circumstances.</p>';
        
        // $letter .= '<p align="justify">Given the above, I would like to congratulate you on your selection for the KGS Scholarship at  
        //             <b>' . strtoupper($learner_sch) . '</b> as a <b>' . strtoupper($ben_sch_status) . '</b> beginning in <b> ' . $start_year . 
        //             '</b> in grade/form <b>' . $grade . '</b>. 
        //             This is a provisional offer that is dependent on you reporting to school and signing the checklist as soon as you receive this letter and subsequently attending classes regularly. </p>';

        $letter .= '<p align="justify">Given the above, I would like to congratulate you on your selection for the KGS Scholarship at  
        <b>' . strtoupper($learner_sch) . '</b> as a <b>' . strtoupper($ben_sch_status) . '</b> beginning in Term 3 of <b> ' . $start_year . $grade . '</b>. 
        This is a provisional offer that is dependent on you reporting to school and signing the checklist as soon as you receive this letter and subsequently attending classes regularly. </p>';

        $letter .= '<p align="justify">When reporting to your assigned school, please remember to carry this offer letter. Upon producing this offer letter, you will be enrolled in school under the KGS Scholarship.<b>' . $grace_period . '</b>.</p>';

        $letter .= '<p align="justify">Further, you are encouraged to work hard at school to avoid failing which may result in you losing the scholarship. By taking up this scholarship, you also give express permission to KGS to access your academic results for purposes of monitoring your performance in order to provide you with extra support services.</p>';

        $letter .= '<p align="justify">For any clarification or concern, please contact any member of the CWAC in your area. 
                    In case your concerns are not addressed you can contact the Head Teacher at the school where you have been assigned. 
                    In an event that your Head Teacher does not address your concerns, please do not hesitate to contact the District 
                    Education Board Secretary (DEBS) <b>' . $debs_title . ' ' . $debs_name . '</b> in your district on the telephone 
                    number <b>' . $debs_phone . '</b> or the KGS Project Coordinator, <b>Mr. Willie C. Kaputo</b> on <b>0955983224.</b></p>';

        PDF::writeHTMLCell(0, 0, 5, 110, $letter, 0, 1, 0, 1, 'L');
        PDF::ln(5);

        $signP1 = '<p><b>' . $ps . '</b></p>';
        $signP2 = '<p>KGS Coordinator<br><b><i>for</i> Permanent Secretary - Administration<br>Ministry of Education</b></p>';
        $footerText = '<p>*For any complaint relating to Keeping Girls in School and Beyond Initiative or your welfare <b>(GBV, School Bullying or HIV)</b> 
                please call on <b>+260953003103</b> <b>(regular call charges may apply)</b>) or Toll free on 994.  <em>*By accepting this offer, you pledge not to engage in any form of GBV</em></p>';

        PDF::Image(getcwd() . $signature_path, 0, '', 25, 12, '', '', 'N', true, 300, 'L', false, false, 0, false, false, false);
        PDF::writeHTML($signP1, true, 0, true, true);
        PDF::writeHTML($signP2, true, 0, true, true);
        //==========///////////////////////////////////////////////////////////////////===========//
        PDF::SetY(-10);
        PDF::SetFont('times', '', 8); 
        PDF::writeHTML($footerText, true, 0, true, true);
    }

    public function offerLetterHeader($ref)
    {
        //todo: offer letter header
        PDF::SetTitle('Offer Letters');
        PDF::SetAutoPageBreak(TRUE, 0);//true sets it to on and 0 means margin is zero from sides
        //PDF::SetMargins($left,$top,$right = -1,$keepmargins = false)
        PDF::SetMargins(10, 5, 10, true);
        PDF::AddPage();

        PDF::SetLineStyle(array('width' => 0.50, 'color' => array(0, 0, 0)));
        PDF::Rect(0, 0, PDF::getPageWidth(), PDF::getPageHeight());
        PDF::SetFont('times', 'B', 11);
        //headers
        $image_path = '\resources\images\kgs-logo.png';
        // Image($file, $x='', $y='', $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false)
        PDF::Image(getcwd() . $image_path, 0, 2, 25, 28, 'PNG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
        PDF::SetY(32);
        PDF::Cell(0, 2, 'REPUBLIC OF ZAMBIA', 0, 1, 'C');
        PDF::Cell(0, 5, 'MINISTRY OF EDUCATION', 0, 1, 'C');
        PDF::Cell(0, 5, 'P.O BOX 50093', 0, 1, 'C');
        PDF::Cell(0, 5, 'LUSAKA', 0, 1, 'C');
        PDF::SetY(2);
        // Start clipping.
        PDF::SetFont('times', 'I', 9);
        PDF::StartTransform();
        $html = '<p>All communications should be addressed to:<br>';
        $html .= 'The Permanent Secretary Ministry of Education.</p>';
        $html .= '<p>Not to an individual by name</p>';
        $html .= '<p>Telephone: <span>250855/251315/251283<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                              251293/211318/251219<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                              251003/251319</span></p>';
        //$html = $left_header_one;
        #writeHTMLCell(w, h, x, y, html = '', border = 0, ln = 0, fill = 0, reseth = true, align = '', autopadding = true)
        PDF::writeHTMLCell(70, 0, 5, 9, $html, 0, 1, 0, 1, 'L');
        PDF::SetFont('times', 'BI', 9);
        PDF::SetY(7);
        PDF::Cell(0, 2, 'In reply please quote:', 0, 1, 'R');
        PDF::Cell(0, 2, 'No:' . $ref, 0, 1, 'R');
        //todo: end header
    }

    public function provisionalSchPlacementOfferLetterHeader($beneficiary)
    {
        //todo: specific header section
        $learner_sch = $beneficiary->school_name;
        $exam_grade = $beneficiary->current_school_grade;
        $exam_number = '______________________';
        if ($exam_grade == 7) {
            $exam_number = $beneficiary->grade7_exam_no;
        } else if ($exam_grade == 9) {
            $exam_number = $beneficiary->grade9_exam_no;
        } else if ($exam_grade == 12) {
            $exam_number = $beneficiary->grade12_exam_no;
        }
        $date = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', time());
        PDF::SetFont('times', '', 10);
        $html = '<p>' . $date . '</p>';
        $html .= '<p>Province of Exam School:&nbsp;&nbsp;<b>' . strtoupper($beneficiary->province_name) . '</b><br>';
        $html .= 'District of Exam School:&nbsp;&nbsp;<b>' . $beneficiary->sch_dist_code . '-' . strtoupper($beneficiary->sch_dist_name) . '</b><br>';
        $html .= 'Exam School of Learner:&nbsp;&nbsp;<b>' . strtoupper($learner_sch) . '</b><br>';
        $html .= 'Examination Number:&nbsp;&nbsp;<b>' . $exam_number . '</b><br>';
        $html .= 'Examination Type:&nbsp;&nbsp;<b>Grade ' . $exam_grade . '</b><br>';
        $html .= 'District of Learner:&nbsp;&nbsp;<b>' . strtoupper($beneficiary->district_name2) . '</b><br>';
        $html .= 'CWAC:&nbsp;&nbsp;<b>' . strtoupper($beneficiary->cwac_txt) . '</b><br>';
        $html .= 'C/O:&nbsp;&nbsp;<b>' . strtoupper($beneficiary->hhh_fname) . ' ' . strtoupper($beneficiary->hhh_lname) . '</b><br>';
        $html .= 'HHH NRC:&nbsp;&nbsp;<b>' . strtoupper($beneficiary->hhh_nrc_number) . '</b><br></p>';
        // PDF::writeHTMLCell(0, 0, 5, 58, $html, 0, 1, 0, 1, 'L');
        PDF::writeHTMLCell(0, 0, 5, 52, $html, 0, 1, 0, 1, 'L');
        //todo: end specific header section
    }

    public function provisionalSchMatchingOfferLetterHeader($beneficiary)
    {
        //todo: specific header section
        $date = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', time());
        PDF::SetFont('times', '', 10);
        $html = '<p>' . $date . '</p>';
        $html .= '<p>Province of School:&nbsp;&nbsp;________________________________<br>';
        $html .= 'District of School:&nbsp;&nbsp;______________________________<br>';
        $html .= 'School of Learner:&nbsp;&nbsp;____________________________<br>';
        $html .= 'District of Learner:&nbsp;&nbsp;' . strtoupper($beneficiary->district_name2) . '<br>';
        $html .= 'CWAC:&nbsp;&nbsp;' . strtoupper($beneficiary->cwac_txt) . '<br>';
        $html .= 'C/O:&nbsp;&nbsp;' . strtoupper($beneficiary->hhh_fname) . ' ' . strtoupper($beneficiary->hhh_lname) . '<br>';
        $html .= 'HHH NRC:&nbsp;&nbsp;' . strtoupper($beneficiary->hhh_nrc_number) . '<br></p>';
        PDF::writeHTMLCell(0, 0, 5, 60, $html, 0, 1, 0, 1, 'L');
        //todo: end specific header section
    }
    public function logOfferLetterGenerations($beneficiary, $ref_category, $details, $reporting_date, $grace_period)
    {
        $start_year = $details->start_year;
        $orig_reporting_date = $details->reporting_date;
        $ps = $details->ps;
        $signature = $details->signature_specimen;

        $last_id = DB::table('prov_letters_gen_log')->max('id');
        $log_check = Db::table('prov_letters_gen_log')->where('girl_id', $beneficiary->id)->first();
        if (is_null($log_check)) {
            $serial = $last_id + 1;
            $serial = str_pad($serial, 5, 0, STR_PAD_LEFT);
            $ref = 'KGS/' . $ref_category . '/' . $serial;
            $params = array(
                'girl_id' => $beneficiary->id,
                'letter_ref' => $ref,
                'generated_by' => \Auth::user()->id,
                'generated_on' => Carbon::now()
            );
            DB::table('prov_letters_gen_log')->insert($params);
        } else {
            $ref = $log_check->letter_ref;
        }
        $letter_tracker = $start_year . strtotime($orig_reporting_date);
        $last_version = DB::table('prov_letters_gen_tracker')
            ->where('girl_id', $beneficiary->id)
            ->where('tracker', $letter_tracker)
            ->max('version');
        if (is_null($last_version) || $last_version == '') {
            $version = 1;
        } else {
            $version = $last_version + 1;
        }
        $log_data = array(
            'girl_id' => $beneficiary->id,
            'start_year' => $start_year,
            'reporting_date' => $reporting_date,
            'grace_period' => $grace_period,
            'ps' => $ps,
            'signature_specimen' => $signature,
            'girl_cwac' => $beneficiary->cwac_id,
            'girl_district' => $beneficiary->district_id,
            'girl_first_name' => $beneficiary->first_name,
            'girl_last_name' => $beneficiary->last_name,
            'beneficiary_school_status' => $beneficiary->beneficiary_school_status,
            'version' => $version,
            'grade' => $beneficiary->current_school_grade,
            'school_id' => $beneficiary->school_id,
            'tracker' => $letter_tracker,
            'created_at' => Carbon::now(),
            'created_by' => \Auth::user()->id
        );
        DB::table('prov_letters_gen_tracker')
            ->insert($log_data);
        return $ref;
    }

    public function generateUnresponsiveOfferLetters($schoolsArray, $details, $filename, $print_filter = 3)
    {
        $start_year = $details->start_year;
        $orig_reporting_date = $details->reporting_date;
        $reporting_date = 'on <b>' + converter22($details->reporting_date) + '</b>';
        // $grace_period = 'The grace period for reporting is until <b>' + converter22($details->grace_period) + '</b>';
        $grace_period = 'The grace period for reporting for Term 3, 2025 is up to <b>' . converter22($details->grace_period) . '.</b>';
        $ps = $details->ps;
        $signature = $details->signature_specimen;
        $signature_path = '\resources\images\signatures\\' . $signature;
        //replaceable
        $reporting_date = '<b>as soon as you receive this letter</b>';
        $grace_period = '';
        foreach ($schoolsArray as $school_id) {
            //todo::get beneficiary information
            $beneficiaries = $this->getUnresponsiveBeneficiaryDetails($school_id, $print_filter);
            if (is_null($beneficiaries) || count($beneficiaries) < 1) {
                continue;
            }
            $printed = array();
            foreach ($beneficiaries as $beneficiary) {
                //get the last ID//letter generation logging here
                $last_id = DB::table('letters_gen_log')->max('id');
                $log_check = Db::table('letters_gen_log')->where('girl_id', $beneficiary->id)->first();
                if (is_null($log_check)) {
                    $serial = $last_id + 1;
                    $serial = str_pad($serial, 5, 0, STR_PAD_LEFT);
                    $ref = 'KGS/unresp/' . $serial;
                    $params = array(
                        'girl_id' => $beneficiary->id,
                        'letter_ref' => $ref,
                        'generated_by' => \Auth::user()->id,
                        'generated_on' => Carbon::now()
                    );
                    DB::table('letters_gen_log')->insert($params);
                } else {
                    $ref = $log_check->letter_ref;
                }
                $letter_tracker = $start_year . strtotime($orig_reporting_date);
                $last_version = DB::table('letters_gen_tracker')
                    ->where('girl_id', $beneficiary->id)
                    ->where('tracker', $letter_tracker)
                    ->max('version');
                if (is_null($last_version) || $last_version == '') {
                    $version = 1;
                } else {
                    $version = $last_version + 1;
                }
                $log_data = array(
                    'girl_id' => $beneficiary->id,
                    'start_year' => $start_year,
                    'reporting_date' => $reporting_date,
                    'grace_period' => $grace_period,
                    'ps' => $ps,
                    'signature_specimen' => $signature,
                    'girl_cwac' => $beneficiary->cwac_id,
                    'girl_district' => $beneficiary->district_id,
                    'girl_first_name' => $beneficiary->first_name,
                    'girl_last_name' => $beneficiary->last_name,
                    'beneficiary_school_status' => $beneficiary->beneficiary_school_status,
                    'version' => $version,
                    'grade' => $beneficiary->current_school_grade,
                    'school_id' => $beneficiary->school_id,
                    'tracker' => $letter_tracker,
                    'created_at' => Carbon::now(),
                    'created_by' => \Auth::user()->id
                );
                DB::table('letters_gen_tracker')
                    ->insert($log_data);
                //end letter logging here
                //check if learner is under promotion
                if ($beneficiary->under_promotion == 1) {
                    $grade = ($beneficiary->current_school_grade + 1);
                } else {
                    $grade = $beneficiary->current_school_grade;
                }
                $extension_txt = '';
                $end_year = $start_year + (12 - $grade);
                if ($end_year > 2020) {
                    $end_year = 2020;
                    $extension_txt = 'Should the project be extended beyond 2020, your scholarship will still be valid within the extension period.';
                }
                $ben_sch_status = $beneficiary->ben_sch_status;
                $debs_name = $beneficiary->debs_fname . '&nbsp;' . $beneficiary->debs_lname;
                $debs_phone = $beneficiary->phone;
                $debs_title = $beneficiary->debs_title;
                $learner_sch = $beneficiary->school_name;
                if ($beneficiary->debs_fname == '' && $beneficiary->debs_lname == '') {
                    $debs_name = '_________________';
                }
                if ($debs_phone == '') {
                    $debs_phone = '_________________';
                }
                if ($ben_sch_status == '') {
                    $ben_sch_status = '_________________';
                }
                $sch_district_name = $beneficiary->sch_dist_name;
                // ===========///////////////////////////////////////////////////////////////////==========//
                $date = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', time());
                PDF::SetTitle('Offer Letters');
                PDF::SetAutoPageBreak(TRUE, 0);//true sets it to on and 0 means margin is zero from sides
                //PDF::SetMargins($left,$top,$right = -1,$keepmargins = false)
                PDF::SetMargins(10, 5, 10, true);
                PDF::AddPage();

                PDF::SetLineStyle(array('width' => 0.50, 'color' => array(0, 0, 0)));
                PDF::Rect(0, 0, PDF::getPageWidth(), PDF::getPageHeight());
                PDF::SetFont('times', 'B', 11);
                //headers
                $image_path = '\resources\images\kgs-logo.png';
                // Image($file, $x='', $y='', $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false)
                PDF::Image(getcwd() . $image_path, 0, 2, 25, 28, 'PNG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
                PDF::SetY(32);
                PDF::Cell(0, 2, 'REPUBLIC OF ZAMBIA', 0, 1, 'C');
                PDF::Cell(0, 5, 'MINISTRY OF EDUCATION', 0, 1, 'C');
                PDF::Cell(0, 5, 'P.O BOX 50093', 0, 1, 'C');
                PDF::Cell(0, 5, 'LUSAKA', 0, 1, 'C');
                PDF::SetY(2);
                // Start clipping.
                PDF::SetFont('times', 'I', 9);
                PDF::StartTransform();
                $html = '<p>All communications should be addressed to:<br>';
                $html .= 'The Permanent Secretary Ministry of Education, Science, Vocational Training and Early Education.</p>';
                $html .= '<p>Not to an individual by name</p>';
                $html .= '<p>Telephone: <span>250855/251315/251283<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                              251293/211318/251219<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                              251003/251319</span></p>';
                //$html = $left_header_one;
                #writeHTMLCell(w, h, x, y, html = '', border = 0, ln = 0, fill = 0, reseth = true, align = '', autopadding = true)
                PDF::writeHTMLCell(70, 0, 5, 9, $html, 0, 1, 0, 1, 'L');
                PDF::SetFont('times', 'BI', 9);
                PDF::SetY(7);
                PDF::Cell(0, 2, 'In reply please quote:', 0, 1, 'R');
                PDF::Cell(0, 2, 'No:' . $ref, 0, 1, 'R');
                //PDF::SetY(40);
                PDF::SetFont('times', '', 10);
                $html = '<p>' . $date . '</p>';
                $html .= '<p>Province of School:&nbsp;&nbsp;<b>' . strtoupper($beneficiary->province_name) . '</b><br>';
                $html .= 'District of School:&nbsp;&nbsp;<b>' . $beneficiary->sch_dist_code . '-' . strtoupper($beneficiary->sch_dist_name) . '</b><br>';
                $html .= 'School of Learner:&nbsp;&nbsp;<b>' . strtoupper($learner_sch) . '</b><br>';
                $html .= 'District of Learner:&nbsp;&nbsp;<b>' . strtoupper($beneficiary->district_name2) . '</b><br>';
                $html .= 'CWAC:&nbsp;&nbsp;<b>' . $beneficiary->cwac_code . '-' . strtoupper($beneficiary->cwac_name) . '</b><br>';
                $html .= 'C/O:&nbsp;&nbsp;<b>' . strtoupper($beneficiary->hhh_fname) . ' ' . strtoupper($beneficiary->hhh_lname) . '</b><br></p>';
                PDF::writeHTMLCell(0, 0, 5, 60, $html, 0, 1, 0, 1, 'L');
                PDF::SetFont('times', '', 11);
                $salutation = '<p>Dear <b>' . strtoupper($beneficiary->first_name) . '&nbsp;' . strtoupper($beneficiary->last_name) . ' (' . $beneficiary->beneficiary_id . ')</b></p>';
                $salutation .= '<p><b>RE: </b><u><b>ADMISSION LETTER FOR IN SCHOOL GIRLS ' . $start_year . ' SCHOLARSHIP: YOURSELF</b></u></p>';
                PDF::writeHTMLCell(0, 0, 5, 100, $salutation, 0, 1, 0, 1, 'L');
                $letter = '<p align="justify">Refer to the above captioned subject matter.<br><br>';//p1
                $letter .= 'The Government of the Republic of Zambia, through the Ministry of Education is implementing Keeping Girls in School initiative; a component of Girls Education and Women Empowerment Livelihood (GEWEL) project.
                  The ministry has undertaken a process of identifying the beneficiaries who are girls in Social Cash Transfer households to access Secondary Education through payment of School fees.</p>';

                $grade = $grade == 8 ? 'in <b> Form I' : 'in <b> Grade '. $grade;

                $letter .= '<p align="justify">In view of the above, I would like to congratulate you on your selection for Keeping Girls in School Scholarship at <b>' . strtoupper($learner_sch) . '</b> school as a <b>' . strtoupper($ben_sch_status) . '</b> beginning in Term 3 of <b>' . $start_year . '</b> ' . $grade . '</b> and ending in <b>' . $end_year . '</b>. ' . $extension_txt . '
                  This is a provisional offer, which is dependent on you reporting to school ' . $reporting_date . ' and subsequently attending school regularly.</p>';
                $letter .= '<p align="justify">Please be informed that when you report to your assigned school, carry this admission letter along with your grade 7 or 9 certificate as being proof of passing and upon producing the two documents you will be enrolled in school under KGS Scholarship.
                  ' . $grace_period . ' Further, you are encouraged to work hard in your schoolwork to avoid failure, which may lead to withdrawal of the scholarship.</p>';
                $letter .= '<p align="justify">For any clarification or concern you may have, please contact your CWAC in your area, if your concerns are not addressed you can contact the head teacher at the school to which you have been assigned and in an event that your head teacher does not
                  address your concerns, please do not hesitate to contact the District Education Board Secretary (DEBS) ' . $debs_title . ' ' . $debs_name . ' in your district on the telephone number ' . $debs_phone . '.</p>';
                PDF::writeHTMLCell(0, 0, 5, 117, $letter, 0, 1, 0, 1, 'L');
                PDF::ln(2);

                $signP1 = '<p><b>' . $ps . '</b></p>';
                $signP2 = '<p>KGS Coordinator<br><b><i>for</i> Permanent Secretary - Administration<br>Ministry of Education</b></p>';
                $footerText = '<p>*For any complaint relating to Keeping Girls in School and Beyond Initiative or your welfare <b>(GBV, School Bullying or HIV)</b> 
                                please call on <b>+260953003103</b> <b>(regular call charges may apply)</b>.</p>';
                PDF::Image(getcwd() . $signature_path, 0, '', 25, 12, '', '', 'N', true, 300, 'L', false, false, 0, false, false, false);
                PDF::writeHTML($signP1, true, 0, true, true);
                PDF::writeHTML($signP2, true, 0, true, true);
                //==========///////////////////////////////////////////////////////////////////===========//
                PDF::SetFont('times', '', 9);
                PDF::SetY(-18);
                PDF::writeHTML($footerText, true, 0, true, true);
                $printed[] = array(
                    'id' => $beneficiary->id
                );
            }
            $printed_ids = convertAssArrayToSimpleArray($printed, 'id');
            DB::table('beneficiary_information')
                ->whereIn('id', $printed_ids)
                ->update(array('letter_printed' => 1));
        }
        PDF::Output($filename . time() . '_' . $sch_district_name . '.pdf', 'D');
    }

    public function generateSpecificInSCHOfferLetters($school_id, $batch_id, $category, $filename)
    {
        $date_details = DB::table('letter_dates')
            ->where('is_active', 1)
            ->first();
        if (is_null($date_details)) {
            print_r('<h3 style="color: red; text-align: center">No active date details were found. Please set letter date details before printing!!</h3>');
            exit();
        }
        $schoolsArray = array($school_id);
        $this->generateInSCHOfferLetters($schoolsArray, $batch_id, $category, $date_details, $filename, 3, 0);
    }

    public function generateSpecificExamClassesOfferLetters($school_id, $batch_id, $category, $filename)
    {
        $date_details = DB::table('letter_dates')
            ->where('is_active', 1)
            ->first();
        if (is_null($date_details)) {
            print_r('<h3 style="color: red; text-align: center">No active date details were found. Please set letter date details before printing!!</h3>');
            exit();
        }
        $schoolsArray = array($school_id);
        $this->generateExamClassesOfferLetters($schoolsArray, $batch_id, $category, $date_details, $filename);
    }

    public function generateSpecificOutofSCHOfferLetters($school_id, $batch_id, $category, $filename)
    {
        $date_details = DB::table('letter_dates')
            ->where('is_active', 1)
            ->first();
        if (is_null($date_details)) {
            print_r('<h3 style="color: red; text-align: center">No active date details were found. Please set letter date details before printing!!</h3>');
            exit();
        }
        $schoolsArray = array($school_id);
        $this->generateOutofSCHOfferLetters($schoolsArray, $batch_id, $category, $date_details, $filename);
    }

    public function generateSingleOutofSCHOfferLetters($girl_id, $filename)
    {
        $details = DB::table('letter_dates')
            ->where('is_active', 1)
            ->first();
        if (is_null($details)) {
            print_r('<h3 style="color: red; text-align: center">No active date details were found. Please set letter date details before printing!!</h3>');
            exit();
        }
        $start_year = $details->start_year;
        $orig_reporting_date = $details->reporting_date;
        $reporting_date = converter22($details->reporting_date);
        $grace_period = converter22($details->grace_period);
        $ps = $details->ps;
        $signature = $details->signature_specimen;
        $signature_path = '\resources\images\signatures\\' . $signature;
        $salutation_category = 'OUT OF SCHOOL';
        //todo::get beneficiary information
        $where = array(
            'beneficiary_information.id' => $girl_id
        );
        $qry = DB::table('beneficiary_information')
            ->leftJoin('beneficiary_school_statuses', 'beneficiary_information.beneficiary_school_status', '=', 'beneficiary_school_statuses.id')
            ->leftJoin('school_information', 'beneficiary_information.school_id', '=', 'school_information.id')
            ->leftJoin('provinces', 'school_information.province_id', '=', 'provinces.id')
            ->leftJoin('districts as school_district', 'school_information.district_id', '=', 'school_district.id')
            ->leftJoin('districts', 'beneficiary_information.district_id', '=', 'districts.id')
            ->leftJoin('users', 'districts.debs_id', '=', 'users.id')
            ->leftJoin('titles', 'users.title_id', '=', 'titles.id')
            ->leftJoin('cwac', 'beneficiary_information.cwac_id', '=', 'cwac.id')
            ->leftJoin('households', 'beneficiary_information.household_id', '=', 'households.id')
            ->select('beneficiary_information.*', 'titles.name as debs_title', 'users.first_name as debs_fname', 'users.last_name as debs_lname', 'users.phone', 'beneficiary_school_statuses.name as ben_sch_status', 'provinces.name as province_name', 'school_district.code as sch_dist_code', 'school_district.name as sch_dist_name', 'cwac.code as cwac_code', 'cwac.name as cwac_name', 'school_information.name as school_name', 'districts.code as district_code2', 'districts.name as district_name2', 'households.hhh_fname', 'households.hhh_lname', 'households.hhh_nrc_number')
            ->where($where);
        $beneficiary_info = $qry->get();
        $beneficiary_info = convertStdClassObjToArray($beneficiary_info);
        $beneficiary_info = decryptArray($beneficiary_info);

        if (!is_null($beneficiary_info)) {
            foreach ($beneficiary_info as $beneficiary) {
                if ($beneficiary['skip_matching'] == 1) {
                    $salutation_category = 'IN SCHOOL';
                }
                //get the last ID//letter generation logging here
                $last_id = DB::table('letters_gen_log')->max('id');
                $log_check = Db::table('letters_gen_log')->where('girl_id', $beneficiary['id'])->first();
                if (is_null($log_check)) {
                    $serial = $last_id + 1;
                    $serial = str_pad($serial, 5, 0, STR_PAD_LEFT);
                    $ref = 'KGS/outofschool/' . $serial;
                    $params = array(
                        'girl_id' => $beneficiary['id'],
                        'letter_ref' => $ref,
                        'generated_by' => \Auth::user()->id,
                        'generated_on' => Carbon::now()
                    );
                    DB::table('letters_gen_log')->insert($params);
                } else {
                    $ref = $log_check->letter_ref;
                }
                $letter_tracker = $start_year . strtotime($orig_reporting_date);
                $last_version = DB::table('letters_gen_tracker')
                    ->where('girl_id', $beneficiary['id'])
                    ->where('tracker', $letter_tracker)
                    ->max('version');
                if (is_null($last_version) || $last_version == '') {
                    $version = 1;
                } else {
                    $version = $last_version + 1;
                }
                $log_data = array(
                    'girl_id' => $beneficiary['id'],
                    'start_year' => $start_year,
                    'reporting_date' => $reporting_date,
                    'grace_period' => $grace_period,
                    'ps' => $ps,
                    'signature_specimen' => $signature,
                    'girl_cwac' => $beneficiary['cwac_id'],
                    'girl_district' => $beneficiary['district_id'],
                    'girl_first_name' => $beneficiary['first_name'],
                    'girl_last_name' => $beneficiary['last_name'],
                    'beneficiary_school_status' => $beneficiary['beneficiary_school_status'],
                    'version' => $version,
                    'grade' => $beneficiary['current_school_grade'],
                    'school_id' => $beneficiary['school_id'],
                    'tracker' => $letter_tracker,
                    'created_at' => Carbon::now(),
                    'created_by' => \Auth::user()->id
                );
                DB::table('letters_gen_tracker')
                    ->insert($log_data);
                //end letter logging here
                //check if learner is under promotion
                if ($beneficiary['under_promotion'] == 1) {
                    $grade = ($beneficiary['current_school_grade'] + 1);
                } else {
                    $grade = $beneficiary['current_school_grade'];
                }
                $extension_txt = '';
                $end_year = $start_year + (12 - $grade);
                if ($end_year > 2020) {
                    $end_year = 2020;
                    $extension_txt = 'Should the project be extended beyond 2020, your scholarship will still be valid within the extension period.';
                }
                $ben_sch_status = $beneficiary['ben_sch_status'];
                $debs_name = $beneficiary['debs_fname'] . '&nbsp;' . $beneficiary['debs_lname'];
                $debs_phone = $beneficiary['phone'];
                $debs_title = $beneficiary['debs_title'];
                $learner_sch = $beneficiary['school_name'];
                if ($beneficiary['debs_fname'] == '' && $beneficiary['debs_lname'] == '') {
                    $debs_name = '_________________';
                }
                if ($debs_phone == '') {
                    $debs_phone = '_________________';
                }
                if ($ben_sch_status == '') {
                    $ben_sch_status = '_________________';
                }
                // ===========///////////////////////////////////////////////////////////////////==========//
                $date = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', time());
                PDF::SetTitle('Offer Letters');
                PDF::SetAutoPageBreak(TRUE, 0);//true sets it to on and 0 means margin is zero from sides
                //PDF::SetMargins($left,$top,$right = -1,$keepmargins = false)
                PDF::SetMargins(10, 5, 10, true);
                PDF::AddPage();

                PDF::SetLineStyle(array('width' => 0.50, 'color' => array(0, 0, 0)));
                PDF::Rect(0, 0, PDF::getPageWidth(), PDF::getPageHeight());
                PDF::SetFont('times', 'B', 11);
                //headers
                $image_path = '\resources\images\kgs-logo.png';
                // Image($file, $x='', $y='', $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false)
                PDF::Image(getcwd() . $image_path, 0, 2, 25, 28, 'PNG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
                PDF::SetY(32);
                PDF::Cell(0, 2, 'REPUBLIC OF ZAMBIA', 0, 1, 'C');
                PDF::Cell(0, 5, 'MINISTRY OF EDUCATION', 0, 1, 'C');
                PDF::Cell(0, 5, 'P.O BOX 50093', 0, 1, 'C');
                PDF::Cell(0, 5, 'LUSAKA', 0, 1, 'C');
                PDF::SetY(2);
                // Start clipping.
                PDF::SetFont('times', 'I', 9);
                PDF::StartTransform();
                $html = '<p>All communications should be addressed to:<br>';
                $html .= 'The Permanent Secretary Ministry of Education, Science, Vocational Training and Early Education.</p>';
                $html .= '<p>Not to an individual by name</p>';
                $html .= '<p>Telephone: <span>250855/251315/251283<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                              251293/211318/251219<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                              251003/251319</span></p>';
                //$html = $left_header_one;
                #writeHTMLCell(w, h, x, y, html = '', border = 0, ln = 0, fill = 0, reseth = true, align = '', autopadding = true)
                PDF::writeHTMLCell(70, 0, 5, 9, $html, 0, 1, 0, 1, 'L');
                PDF::SetFont('times', 'BI', 9);
                PDF::SetY(7);
                PDF::Cell(0, 2, 'In reply please quote:', 0, 1, 'R');
                PDF::Cell(0, 2, 'No:' . $ref, 0, 1, 'R');
                //PDF::SetY(40);
                PDF::SetFont('times', '', 10);
                $html = '<p>' . $date . '</p>';
                $html .= '<p>Province of School:&nbsp;&nbsp;<b>' . strtoupper($beneficiary['province_name']) . '</b><br>';
                $html .= 'District of School:&nbsp;&nbsp;<b>' . $beneficiary['sch_dist_code'] . '-' . strtoupper($beneficiary['sch_dist_name']) . '</b><br>';
                $html .= 'School of Learner:&nbsp;&nbsp;<b>' . strtoupper($learner_sch) . '</b><br>';
                $html .= 'District of Learner:&nbsp;&nbsp;<b>' . strtoupper($beneficiary['district_name2']) . '</b><br>';
                $html .= 'CWAC:&nbsp;&nbsp;<b>' . $beneficiary['cwac_code'] . '-' . strtoupper($beneficiary['cwac_name']) . '</b><br>';
                $html .= 'C/O:&nbsp;&nbsp;<b>' . strtoupper($beneficiary['hhh_fname']) . ' ' . strtoupper($beneficiary['hhh_lname']) . '</b><br></p>';
                PDF::writeHTMLCell(0, 0, 5, 60, $html, 0, 1, 0, 1, 'L');
                PDF::SetFont('times', '', 11);
                $salutation = '<p>Dear <b>' . strtoupper($beneficiary['first_name']) . '&nbsp;' . strtoupper($beneficiary['last_name']) . ' (' . $beneficiary['beneficiary_id'] . ')</b></p>';
                $salutation .= '<p><b>RE: </b><u><b>ADMISSION LETTER FOR ' . $salutation_category . ' GIRLS ' . $start_year . ' SCHOLARSHIP: YOURSELF</b></u></p>';
                PDF::writeHTMLCell(0, 0, 5, 100, $salutation, 0, 1, 0, 1, 'L');
                $letter = '<p align="justify">Refer to the above captioned subject matter.<br><br>';//p1
                $letter .= 'The Government of the Republic of Zambia, through the Ministry of Education is implementing Keeping Girls in School initiative; a component of Girls Education and Women Empowerment Livelihood (GEWEL) project.
                  The ministry has undertaken a process of identifying the beneficiaries who are girls in Social Cash Transfer households to access Secondary Education through payment of School fees.</p>';

                $grade = $grade == 8 ? 'in <b> Form I' : 'in <b> Grade '. $grade;

                $letter .= '<p align="justify">In view of the above, I would like to congratulate you on your selection for Keeping Girls in School Scholarship at <b>' . strtoupper($learner_sch) . '</b> school as a <b>' . strtoupper($ben_sch_status) . '</b> beginning in Term 3 of <b>' . $start_year . '</b> ' . $grade . '</b> and ending in <b>' . $end_year . '</b>. ' . $extension_txt . '
                  This is a provisional offer, which is dependent on you reporting to school on <b>' . $reporting_date . '</b> and subsequently attending school regularly.</p>';
                $letter .= '<p align="justify">Please be informed that when you report to your assigned school, carry this admission letter along with your grade 7 or 9 certificate as being proof of passing and upon producing the two documents you will be enrolled in school under KGS Scholarship. <b>' . $grace_period . '</b>. Further, you are encouraged to work hard in your schoolwork to avoid failure, which may lead to withdrawal of the scholarship.</p>';
                $letter .= '<p align="justify">For any clarification or concern you may have, please contact your CWAC in your area, if your concerns are not addressed you can contact the head teacher at the school to which you have been assigned and in an event that your head teacher does not
                  address your concerns, please do not hesitate to contact the District Education Board Secretary (DEBS) ' . $debs_title . ' ' . $debs_name . ' in your district on the telephone number ' . $debs_phone . '.</p>';
                PDF::writeHTMLCell(0, 0, 5, 117, $letter, 0, 1, 0, 1, 'L');
                PDF::ln(2);

                $signP1 = '<p><b>' . $ps . '</b></p>';
                $signP2 = '<p>KGS Coordinator<br><b><i>for</i> Permanent Secretary - Administration<br>Ministry of Education</b></p>';
                // $footerText = '<p>*For any complaint relating to Keeping Girls in School or your welfare (GBV, School Bullying or HIV) 
                //                please call Lifeline ChildLine (Toll-Free) on <b>933</b> or <b>116</b>.</p>';
                $footerText = '<p>*For any complaint relating to Keeping Girls in School and Beyond Initiative or your welfare <b>(GBV, School Bullying or HIV)</b> 
                                please call on <b>+260953003103</b> <b>(regular call charges may apply)</b>.</p>';
                PDF::Image(getcwd() . $signature_path, 0, '', 25, 12, '', '', 'N', true, 300, 'L', false, false, 0, false, false, false);
                PDF::writeHTML($signP1, true, 0, true, true);
                PDF::writeHTML($signP2, true, 0, true, true);
                //==========///////////////////////////////////////////////////////////////////===========//
                PDF::SetY(-18);
                PDF::writeHTML($footerText, true, 0, true, true);
            }
        } else {
            print_r('Problem encountered getting details!!');
            exit();
        }
        PDF::Output($filename . time() . '.pdf', 'I');
    }

    public function generateSingleInSCHOfferLetters($girl_id, $filename)
    {
        $details = DB::table('letter_dates')
            ->where('is_active', 1)
            ->first();
        if (is_null($details)) {
            print_r('<h3 style="color: red; text-align: center">No active date details were found. Please set letter date details before printing!!</h3>');
            exit();
        }
        $start_year = $details->start_year;
        $orig_reporting_date = $details->reporting_date;
        $reporting_date = converter22($details->reporting_date);
        $grace_period = converter22($details->grace_period);
        $ps = $details->ps;
        $signature = $details->signature_specimen;
        $signature_path = '\resources\images\signatures\\' . $signature;
        //todo::get beneficiary information
        $where = array(
            'beneficiary_information.id' => $girl_id
        );
        $qry = DB::table('beneficiary_information')
            ->leftJoin('beneficiary_school_statuses', 'beneficiary_information.beneficiary_school_status', '=', 'beneficiary_school_statuses.id')
            ->leftJoin('school_information', 'beneficiary_information.school_id', '=', 'school_information.id')
            ->leftJoin('provinces', 'school_information.province_id', '=', 'provinces.id')
            ->leftJoin('districts as school_district', 'school_information.district_id', '=', 'school_district.id')
            ->leftJoin('districts', 'beneficiary_information.district_id', '=', 'districts.id')
            ->leftJoin('users', 'districts.debs_id', '=', 'users.id')
            ->leftJoin('titles', 'users.title_id', '=', 'titles.id')
            ->leftJoin('cwac', 'beneficiary_information.cwac_id', '=', 'cwac.id')
            ->leftJoin('households', 'beneficiary_information.household_id', '=', 'households.id')
            ->select('beneficiary_information.*', 'titles.name as debs_title', 'users.first_name as debs_fname', 'users.last_name as debs_lname', 'users.phone', 'beneficiary_school_statuses.name as ben_sch_status', 'provinces.name as province_name', 'school_district.code as sch_dist_code', 'school_district.name as sch_dist_name', 'cwac.code as cwac_code', 'cwac.name as cwac_name', 'school_information.name as school_name', 'districts.code as district_code2', 'districts.name as district_name2', 'households.hhh_fname', 'households.hhh_lname', 'households.hhh_nrc_number')
            ->where($where);
        $beneficiary_info = $qry->get();
        $beneficiary_info = convertStdClassObjToArray($beneficiary_info);
        $beneficiary_info = decryptArray($beneficiary_info);

        if (!is_null($beneficiary_info)) {
            foreach ($beneficiary_info as $beneficiary) {
                //get the last ID//letter generation logging here
                $last_id = DB::table('letters_gen_log')->max('id');
                $log_check = Db::table('letters_gen_log')->where('girl_id', $beneficiary['id'])->first();
                if (is_null($log_check)) {
                    $serial = $last_id + 1;
                    $serial = str_pad($serial, 5, 0, STR_PAD_LEFT);
                    $ref = 'KGS/inschool/' . $serial;
                    $params = array(
                        'girl_id' => $beneficiary['id'],
                        'letter_ref' => $ref,
                        'generated_by' => \Auth::user()->id,
                        'generated_on' => Carbon::now()
                    );
                    DB::table('letters_gen_log')->insert($params);
                } else {
                    $ref = $log_check->letter_ref;
                }
                $letter_tracker = $start_year . strtotime($orig_reporting_date);
                $last_version = DB::table('letters_gen_tracker')
                    ->where('girl_id', $beneficiary['id'])
                    ->where('tracker', $letter_tracker)
                    ->max('version');
                if (is_null($last_version) || $last_version == '') {
                    $version = 1;
                } else {
                    $version = $last_version + 1;
                }
                $log_data = array(
                    'girl_id' => $beneficiary['id'],
                    'start_year' => $start_year,
                    'reporting_date' => $reporting_date,
                    'grace_period' => $grace_period,
                    'ps' => $ps,
                    'signature_specimen' => $signature,
                    'girl_cwac' => $beneficiary['cwac_id'],
                    'girl_district' => $beneficiary['district_id'],
                    'girl_first_name' => $beneficiary['first_name'],
                    'girl_last_name' => $beneficiary['last_name'],
                    'beneficiary_school_status' => $beneficiary['beneficiary_school_status'],
                    'version' => $version,
                    'grade' => $beneficiary['current_school_grade'],
                    'school_id' => $beneficiary['school_id'],
                    'tracker' => $letter_tracker,
                    'created_at' => Carbon::now(),
                    'created_by' => \Auth::user()->id
                );
                DB::table('letters_gen_tracker')
                    ->insert($log_data);
                //end letter logging here
                //check if learner is under promotion
                if ($beneficiary['under_promotion'] == 1) {
                    $grade = ($beneficiary['current_school_grade'] + 1);
                } else {
                    $grade = $beneficiary['current_school_grade'];
                }
                $extension_txt = '';
                $end_year = $start_year + (12 - $grade);
                if ($end_year > 2020) {
                    $end_year = 2020;
                    $extension_txt = 'Should the project be extended beyond 2020, your scholarship will still be valid within the extension period.';
                }
                $ben_sch_status = $beneficiary['ben_sch_status'];
                $debs_name = $beneficiary['debs_fname'] . '&nbsp;' . $beneficiary['debs_lname'];
                $debs_phone = $beneficiary['phone'];
                $debs_title = $beneficiary['debs_title'];
                $learner_sch = $beneficiary['school_name'];
                if ($beneficiary['debs_fname'] == '' && $beneficiary['debs_lname'] == '') {
                    $debs_name = '_________________';
                }
                if ($debs_phone == '') {
                    $debs_phone = '_________________';
                }
                if ($ben_sch_status == '') {
                    $ben_sch_status = '_________________';
                }
                // ===========///////////////////////////////////////////////////////////////////==========//
                $date = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', time());
                PDF::SetTitle('Offer Letters');
                PDF::SetAutoPageBreak(TRUE, 0);//true sets it to on and 0 means margin is zero from sides
                //PDF::SetMargins($left,$top,$right = -1,$keepmargins = false)
                PDF::SetMargins(10, 5, 10, true);
                PDF::AddPage();

                PDF::SetLineStyle(array('width' => 0.50, 'color' => array(0, 0, 0)));
                PDF::Rect(0, 0, PDF::getPageWidth(), PDF::getPageHeight());
                PDF::SetFont('times', 'B', 11);
                //headers
                $image_path = '\resources\images\kgs-logo.png';
                // Image($file, $x='', $y='', $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false)
                PDF::Image(getcwd() . $image_path, 0, 2, 25, 28, 'PNG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
                PDF::SetY(32);
                PDF::Cell(0, 2, 'REPUBLIC OF ZAMBIA', 0, 1, 'C');
                PDF::Cell(0, 5, 'MINISTRY OF EDUCATION', 0, 1, 'C');
                PDF::Cell(0, 5, 'P.O BOX 50093', 0, 1, 'C');
                PDF::Cell(0, 5, 'LUSAKA', 0, 1, 'C');
                PDF::SetY(2);
                // Start clipping.
                PDF::SetFont('times', 'I', 9);
                PDF::StartTransform();
                $html = '<p>All communications should be addressed to:<br>';
                $html .= 'The Permanent Secretary Ministry of Education, Science, Vocational Training and Early Education.</p>';
                $html .= '<p>Not to an individual by name</p>';
                $html .= '<p>Telephone: <span>250855/251315/251283<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                              251293/211318/251219<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                              251003/251319</span></p>';
                //$html = $left_header_one;
                #writeHTMLCell(w, h, x, y, html = '', border = 0, ln = 0, fill = 0, reseth = true, align = '', autopadding = true)
                PDF::writeHTMLCell(70, 0, 5, 9, $html, 0, 1, 0, 1, 'L');
                PDF::SetFont('times', 'BI', 9);
                PDF::SetY(7);
                PDF::Cell(0, 2, 'In reply please quote:', 0, 1, 'R');
                PDF::Cell(0, 2, 'No:' . $ref, 0, 1, 'R');
                //PDF::SetY(40);
                PDF::SetFont('times', '', 10);
                $html = '<p>' . $date . '</p>';
                $html .= '<p>Province of School:&nbsp;&nbsp;<b>' . strtoupper($beneficiary['province_name']) . '</b><br>';
                $html .= 'District of School:&nbsp;&nbsp;<b>' . $beneficiary['sch_dist_code'] . '-' . strtoupper($beneficiary['sch_dist_name']) . '</b><br>';
                $html .= 'School of Learner:&nbsp;&nbsp;<b>' . strtoupper($learner_sch) . '</b><br>';
                $html .= 'District of Learner:&nbsp;&nbsp;<b>' . strtoupper($beneficiary['district_name2']) . '</b><br>';
                $html .= 'CWAC:&nbsp;&nbsp;<b>' . $beneficiary['cwac_code'] . '-' . strtoupper($beneficiary['cwac_name']) . '</b><br>';
                $html .= 'C/O:&nbsp;&nbsp;<b>' . strtoupper($beneficiary['hhh_fname']) . ' ' . strtoupper($beneficiary['hhh_lname']) . '</b><br></p>';
                PDF::writeHTMLCell(0, 0, 5, 60, $html, 0, 1, 0, 1, 'L');
                PDF::SetFont('times', '', 11);
                $salutation = '<p>Dear <b>' . strtoupper($beneficiary['first_name']) . '&nbsp;' . strtoupper($beneficiary['last_name']) . ' (' . $beneficiary['beneficiary_id'] . ')</b></p>';
                $salutation .= '<p><b>RE: </b><u><b>ADMISSION LETTER FOR IN SCHOOL GIRLS ' . $start_year . ' SCHOLARSHIP: YOURSELF</b></u></p>';
                PDF::writeHTMLCell(0, 0, 5, 100, $salutation, 0, 1, 0, 1, 'L');
                $letter = '<p align="justify">Refer to the above captioned subject matter.<br><br>';//p1
                $letter .= 'The Government of the Republic of Zambia, through the Ministry of Education is implementing Keeping Girls in School initiative; a component of Girls Education and Women Empowerment Livelihood (GEWEL) project.
                  The ministry has undertaken a process of identifying the beneficiaries who are girls in Social Cash Transfer households to access Secondary Education through payment of School fees.</p>';

                $grade = $grade == 8 ? 'in <b> Form I' : 'in <b> Grade '. $grade;

                $letter .= '<p align="justify">In view of the above, I would like to congratulate you on your selection for Keeping Girls in School Scholarship at <b>' . strtoupper($learner_sch) . '</b> school as a <b>' . strtoupper($ben_sch_status) . '</b> beginning in Term 3 of <b>' . $start_year . '</b> ' . $grade . '</b> and ending in <b>' . $end_year . '</b>. ' . $extension_txt . '
                  This is a provisional offer, which is dependent on you reporting to school on <b>' . $reporting_date . '</b> and subsequently attending school regularly.</p>';
                $letter .= '<p align="justify">Please be informed that when you report to your assigned school, carry this admission letter along with your grade 7 or 9 certificate as being proof of passing and upon producing the two documents you will be enrolled in school under KGS Scholarship. <b>' . $grace_period . '</b>. Further, you are encouraged to work hard in your schoolwork to avoid failure, which may lead to withdrawal of the scholarship.</p>';
                $letter .= '<p align="justify">For any clarification or concern you may have, please contact your CWAC in your area, if your concerns are not addressed you can contact the head teacher at the school to which you have been assigned and in an event that your head teacher does not
                  address your concerns, please do not hesitate to contact the District Education Board Secretary (DEBS) ' . $debs_title . ' ' . $debs_name . ' in your district on the telephone number ' . $debs_phone . '.</p>';
                PDF::writeHTMLCell(0, 0, 5, 117, $letter, 0, 1, 0, 1, 'L');
                PDF::ln(5);

                $signP1 = '<p><b>' . $ps . '</b></p>';
                $signP2 = '<p>KGS Coordinator<br><b><i>for</i> Permanent Secretary - Administration<br>Ministry of Education</b></p>';
                $footerText = '<p>*For any complaint relating to Keeping Girls in School and Beyond Initiative or your welfare <b>(GBV, School Bullying or HIV)</b> 
                                please call on <b>+260953003103</b> <b>(regular call charges may apply)</b>.</p>';
                PDF::Image(getcwd() . $signature_path, 0, '', 25, 12, '', '', 'N', true, 300, 'L', false, false, 0, false, false, false);
                PDF::writeHTML($signP1, true, 0, true, true);
                PDF::writeHTML($signP2, true, 0, true, true);
                //==========///////////////////////////////////////////////////////////////////===========//
                PDF::SetFont('times', '', 9);
                PDF::SetY(-18);
                PDF::writeHTML($footerText, true, 0, true, true);
            }
        } else {
            print_r('Problem encountered getting details!!');
            exit();
        }
        PDF::Output($filename . time() . '.pdf', 'I');
    }

    public function generateSingleExamClassesOfferLetters($girl_id, $filename)
    {
        $details = DB::table('letter_dates')
            ->where('is_active', 1)
            ->first();
        if (is_null($details)) {
            print_r('<h3 style="color: red; text-align: center">No active date details were found. Please set letter date details before printing!!</h3>');
            exit();
        }
        $start_year = $details->start_year;
        $orig_reporting_date = $details->reporting_date;
        $reporting_date = converter22($details->reporting_date);
        $grace_period = converter22($details->grace_period);
        $ps = $details->ps;
        //todo::get beneficiary information
        $where = array(
            'beneficiary_information.id' => $girl_id
        );
        $qry = DB::table('beneficiary_information')
            ->leftJoin('beneficiary_school_statuses', 'beneficiary_information.beneficiary_school_status', '=', 'beneficiary_school_statuses.id')
            ->leftJoin('school_information', 'beneficiary_information.school_id', '=', 'school_information.id')
            ->leftJoin('provinces', 'school_information.province_id', '=', 'provinces.id')
            ->leftJoin('districts as school_district', 'school_information.district_id', '=', 'school_district.id')
            ->leftJoin('districts', 'beneficiary_information.district_id', '=', 'districts.id')
            ->leftJoin('users', 'districts.debs_id', '=', 'users.id')
            ->leftJoin('titles', 'users.title_id', '=', 'titles.id')
            ->leftJoin('cwac', 'beneficiary_information.cwac_id', '=', 'cwac.id')
            ->leftJoin('households', 'beneficiary_information.household_id', '=', 'households.id')
            ->select('beneficiary_information.*', 'titles.name as debs_title', 'users.first_name as debs_fname', 'users.last_name as debs_lname', 'users.phone', 'beneficiary_school_statuses.name as ben_sch_status', 'provinces.name as province_name', 'school_district.code as sch_dist_code', 'school_district.name as sch_dist_name', 'cwac.code as cwac_code', 'cwac.name as cwac_name', 'school_information.name as school_name', 'districts.code as district_code2', 'districts.name as district_name2', 'households.hhh_fname', 'households.hhh_lname', 'households.hhh_nrc_number')
            ->where($where);
        $beneficiary_info = $qry->get();
        $beneficiary_info = convertStdClassObjToArray($beneficiary_info);
        $beneficiary_info = decryptArray($beneficiary_info);

        if (!is_null($beneficiary_info)) {
            foreach ($beneficiary_info as $beneficiary) {
                //get the last ID//letter generation logging here
                $last_id = DB::table('letters_gen_log')->max('id');
                $log_check = Db::table('letters_gen_log')->where('girl_id', $beneficiary['id'])->first();
                if (is_null($log_check)) {
                    $serial = $last_id + 1;
                    $serial = str_pad($serial, 5, 0, STR_PAD_LEFT);
                    $ref = 'KGS/examclass/' . $serial;
                    $params = array(
                        'girl_id' => $beneficiary['id'],
                        'letter_ref' => $ref,
                        'generated_by' => \Auth::user()->id,
                        'generated_on' => Carbon::now()
                    );
                    DB::table('letters_gen_log')->insert($params);
                } else {
                    $ref = $log_check->letter_ref;
                }
                $letter_tracker = $start_year . strtotime($orig_reporting_date);
                $last_version = DB::table('letters_gen_tracker')
                    ->where('girl_id', $beneficiary['id'])
                    ->where('tracker', $letter_tracker)
                    ->max('version');
                if (is_null($last_version) || $last_version == '') {
                    $version = 1;
                } else {
                    $version = $last_version + 1;
                }
                $log_data = array(
                    'girl_id' => $beneficiary['id'],
                    'start_year' => $start_year,
                    'reporting_date' => $reporting_date,
                    'grace_period' => $grace_period,
                    'ps' => $ps,
                    'girl_cwac' => $beneficiary['cwac_id'],
                    'girl_district' => $beneficiary['district_id'],
                    'girl_first_name' => $beneficiary['first_name'],
                    'girl_last_name' => $beneficiary['last_name'],
                    'beneficiary_school_status' => $beneficiary['beneficiary_school_status'],
                    'version' => $version,
                    'grade' => $beneficiary['current_school_grade'],
                    'school_id' => $beneficiary['school_id'],
                    'tracker' => $letter_tracker,
                    'created_at' => Carbon::now(),
                    'created_by' => \Auth::user()->id
                );
                DB::table('letters_gen_tracker')
                    ->insert($log_data);
                //end letter logging here
                $extension_txt = '';
                $grade = $beneficiary['current_school_grade'];
                $end_year = $start_year + (12 - $grade);
                if ($end_year > 2020) {
                    $end_year = 2020;
                    $extension_txt = 'Should the project be extended beyond 2020, your scholarship will still be valid within the extension period.';
                }
                $ben_sch_status = $beneficiary['ben_sch_status'];
                $debs_name = $beneficiary['debs_fname'] . '&nbsp;' . $beneficiary['debs_lname'];
                $debs_phone = $beneficiary['phone'];
                $debs_title = $beneficiary['debs_title'];
                $learner_sch = $beneficiary['school_name'];
                if ($beneficiary['debs_fname'] == '' && $beneficiary['debs_lname'] == '') {
                    $debs_name = '_________________';
                }
                if ($debs_phone == '') {
                    $debs_phone = '_________________';
                }
                if ($ben_sch_status == '') {
                    $ben_sch_status = '_________________';
                }
                // ===========///////////////////////////////////////////////////////////////////==========//
                $date = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', time());
                PDF::SetTitle('Offer Letters');
                PDF::SetAutoPageBreak(TRUE, 0);//true sets it to on and 0 means margin is zero from sides
                //PDF::SetMargins($left,$top,$right = -1,$keepmargins = false)
                PDF::SetMargins(10, 5, 10, true);
                PDF::AddPage();

                PDF::SetLineStyle(array('width' => 0.50, 'color' => array(0, 0, 0)));
                PDF::Rect(0, 0, PDF::getPageWidth(), PDF::getPageHeight());
                PDF::SetFont('times', 'B', 11);
                //headers
                $image_path = '\resources\images\kgs-logo.png';
                // Image($file, $x='', $y='', $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false)
                PDF::Image(getcwd() . $image_path, 0, 2, 25, 28, 'PNG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
                PDF::SetY(32);
                PDF::Cell(0, 2, 'REPUBLIC OF ZAMBIA', 0, 1, 'C');
                PDF::Cell(0, 5, 'MINISTRY OF EDUCATION', 0, 1, 'C');
                PDF::Cell(0, 5, 'P.O BOX 50093', 0, 1, 'C');
                PDF::Cell(0, 5, 'LUSAKA', 0, 1, 'C');
                PDF::SetY(2);
                // Start clipping.
                PDF::SetFont('times', 'I', 9);
                PDF::StartTransform();
                $html = '<p>All communications should be addressed to:<br>';
                $html .= 'The Permanent Secretary Ministry of Education, Science, Vocational Training and Early Education.</p>';
                $html .= '<p>Not to an individual by name</p>';
                $html .= '<p>Telephone: <span>250855/251315/251283<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                              251293/211318/251219<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                              251003/251319</span></p>';
                //$html = $left_header_one;
                #writeHTMLCell(w, h, x, y, html = '', border = 0, ln = 0, fill = 0, reseth = true, align = '', autopadding = true)
                PDF::writeHTMLCell(70, 0, 5, 9, $html, 0, 1, 0, 1, 'L');
                PDF::SetFont('times', 'BI', 9);
                PDF::SetY(7);
                PDF::Cell(0, 2, 'In reply please quote:', 0, 1, 'R');
                PDF::Cell(0, 2, 'No:' . $ref, 0, 1, 'R');
                //PDF::SetY(40);
                PDF::SetFont('times', '', 10);
                $html = '<p>' . $date . '</p>';
                $html .= '<p>Province of School:&nbsp;&nbsp;<b>' . strtoupper($beneficiary['province_name']) . '</b><br>';
                $html .= 'District of School:&nbsp;&nbsp;<b>' . $beneficiary['sch_dist_code'] . '-' . strtoupper($beneficiary['sch_dist_name']) . '</b><br>';
                $html .= 'School of Learner:&nbsp;&nbsp;<b>' . strtoupper($learner_sch) . '</b><br>';
                $html .= 'District of Learner:&nbsp;&nbsp;<b>' . strtoupper($beneficiary['district_name2']) . '</b><br>';
                $html .= 'CWAC:&nbsp;&nbsp;<b>' . $beneficiary['cwac_code'] . '-' . strtoupper($beneficiary['cwac_name']) . '</b><br>';
                $html .= 'C/O:&nbsp;&nbsp;<b>' . strtoupper($beneficiary['hhh_fname']) . ' ' . strtoupper($beneficiary['hhh_lname']) . '</b><br></p>';
                PDF::writeHTMLCell(0, 0, 5, 60, $html, 0, 1, 0, 1, 'L');
                PDF::SetFont('times', '', 11);
                $salutation = '<p>Dear <b>' . strtoupper($beneficiary['first_name']) . '&nbsp;' . strtoupper($beneficiary['last_name']) . ' (' . $beneficiary['beneficiary_id'] . ')</b></p>';
                $salutation .= '<p><b>RE: </b><u><b>ADMISSION LETTER FOR IN SCHOOL GIRLS ' . $start_year . ' SCHOLARSHIP: YOURSELF</b></u></p>';
                PDF::writeHTMLCell(0, 0, 5, 100, $salutation, 0, 1, 0, 1, 'L');
                $letter = '<p align="justify">Refer to the above captioned subject matter.<br><br>';//p1
                $letter .= 'The Government of the Republic of Zambia, through the Ministry of Education is implementing Keeping Girls in School initiative; a component of Girls Education and Women Empowerment Livelihood (GEWEL) project.
                  The ministry has undertaken a process of identifying the beneficiaries who are girls in Social Cash Transfer households to access Secondary Education through payment of School fees.</p>';

                $grade = $grade == 8 ? 'in <b> Form I' : 'in <b> Grade '. $grade;

                $letter .= '<p align="justify">In view of the above, I would like to congratulate you on your selection for Keeping Girls in School Scholarship at <b>' . strtoupper($learner_sch) . '</b> school as a <b>' . strtoupper($ben_sch_status) . '</b> beginning in Term 3 of <b>' . $start_year . '</b> ' . $grade . '</b> and ending in <b>' . $end_year . '</b>. ' . $extension_txt . '
                  This is a provisional offer, which is dependent on you reporting to school on <b>' . $reporting_date . '</b> and subsequently attending school regularly.</p>';
                $letter .= '<p align="justify">Please be informed that when you report to your assigned school, carry this admission letter along with your grade 7 or 9 certificate as being proof of passing and upon producing the two documents you will be enrolled in school under KGS Scholarship. <b>' . $grace_period . '</b>. Further, you are encouraged to work hard in your schoolwork to avoid failure, which may lead to withdrawal of the scholarship.</p>';
                $letter .= '<p align="justify">For any clarification or concern you may have, please contact your CWAC in your area, if your concerns are not addressed you can contact the head teacher at the school to which you have been assigned and in an event that your head teacher does not
                  address your concerns, please do not hesitate to contact the District Education Board Secretary (DEBS) ' . $debs_title . ' ' . $debs_name . ' in your district on the telephone number ' . $debs_phone . '.</p>';
                PDF::writeHTMLCell(0, 0, 5, 117, $letter, 0, 1, 0, 1, 'L');

                $sign = '<p><b>' . $ps . '</b><br>Permanent Secretary<br>Ministry of Education</p>';
                PDF::writeHTMLCell(70, 0, 5, 230, $sign, 0, 1, 0, 1, 'L');
                //==========///////////////////////////////////////////////////////////////////===========//
            }
        } else {
            print_r('Problem encountered getting details!!');
            exit();
        }
        PDF::Output($filename . time() . '.pdf', 'I');
    }

    public function downloadOfferLetter($girl_id, $category, $log_id, $track_id)
    {
        if ($category == 1) {
            $category_name = 'OUT OF SCHOOL';
        } else {
            $category_name = 'IN SCHOOL';
        }
        //log details
        $log_details = DB::table('letters_gen_log')->where('id', $log_id)->first();
        $ref = $log_details->letter_ref;
        //tracking details
        $tracking_details = DB::table('letters_gen_tracker')->where('id', $track_id)->first();
        $start_year = $tracking_details->start_year;
        $reporting_date = $tracking_details->reporting_date;
        $grace_period = $tracking_details->grace_period;
        $ps = $tracking_details->ps;
        $signature_path = '\resources\images\signatures\\' . $tracking_details->signature_specimen;
        $grade = $tracking_details->grade;
        $school_id = $tracking_details->school_id;
        $ben_school_status = $tracking_details->beneficiary_school_status;
        $girl_first_name = $tracking_details->girl_first_name;
        $girl_last_name = $tracking_details->girl_last_name;
        $cwac_id = $tracking_details->girl_cwac;
        $district_id = $tracking_details->girl_district;
        $track_code = $tracking_details->tracker;
        $version = $tracking_details->version;
        $time = $tracking_details->created_at;
        //todo::get house hold head details
        $hhh_fname = '';
        $hhh_lname = '';
        $hhh_info = DB::table('beneficiary_information')
            ->join('households', 'beneficiary_information.household_id', '=', 'households.id')
            ->select('households.hhh_fname', 'households.hhh_lname')
            ->where('beneficiary_information.id', $girl_id)
            ->first();
        if (!is_null($hhh_info)) {
            $hhh_fname = $hhh_info->hhh_fname;
            $hhh_lname = $hhh_info->hhh_lname;
        }
        //todo::get school information
        $school_info = DB::table('school_information')
            ->join('districts', 'school_information.district_id', '=', 'districts.id')
            ->join('provinces', 'school_information.province_id', '=', 'provinces.id')
            ->select('school_information.name', 'districts.code as district_code', 'districts.name as district_name', 'provinces.name as province_name')
            ->where('school_information.id', $school_id)
            ->first();
        $school_name = $school_info->name;
        $school_district_code = $school_info->district_code;
        $school_district_name = $school_info->district_name;
        $school_province_name = $school_info->province_name;
        //todo::get beneficiary school status details
        $ben_school_status_info = DB::table('beneficiary_school_statuses')
            ->where('id', $ben_school_status)
            ->first();
        $ben_school_status = '_________________';
        if (!is_null($ben_school_status_info)) {
            $ben_school_status = $ben_school_status_info->name;
        }
        //todo::get beneficiary CWAC details
        $cwac_info = DB::table('cwac')
            ->where('id', $cwac_id)
            ->first();
        $cwac_code = $cwac_info->code;
        $cwac_name = $cwac_info->name;
        //todo::get beneficiary District details
        $ben_district_info = DB::table('districts')
            ->leftJoin('users', 'districts.debs_id', '=', 'users.id')
            ->leftJoin('titles', 'users.title_id', '=', 'titles.id')
            ->select('districts.name', 'titles.name as debs_title', 'users.first_name as debs_fname', 'users.last_name as debs_lname', 'users.phone')
            ->where('districts.id', $district_id)
            ->first();
        $district_name = $ben_district_info->name;
        $extension_txt = '';
        $end_year = $start_year + (12 - $grade);
        if ($end_year > 2020) {
            $end_year = 2020;
            $extension_txt = 'Should the project be extended beyond 2020, your scholarship will still be valid within the extension period.';
        }
        $debs_name = aes_decrypt($ben_district_info->debs_fname) . '&nbsp;' . aes_decrypt($ben_district_info->debs_lname);
        $debs_phone = aes_decrypt($ben_district_info->phone);
        $debs_title = aes_decrypt($ben_district_info->debs_title);
        if ($ben_district_info->debs_fname == '' && $ben_district_info->debs_lname == '') {
            $debs_name = '_________________';
        }
        if ($debs_phone == '') {
            $debs_phone = '_________________';
        }
        //todo::get beneficiary information
        $where = array(
            'beneficiary_information.id' => $girl_id
        );
        $qry = DB::table('beneficiary_information')
            ->select('beneficiary_information.beneficiary_id')
            ->where($where);
        $beneficiary_info = $qry->first();

        if (!is_null($beneficiary_info)) {
            $beneficiary_id = $beneficiary_info->beneficiary_id;
            // ===========///////////////////////////////////////////////////////////////////==========//
            $date = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', strtotime($time));
            PDF::SetTitle('Offer Letters');
            PDF::SetAutoPageBreak(TRUE, 0);//true sets it to on and 0 means margin is zero from sides
            //PDF::SetMargins($left,$top,$right = -1,$keepmargins = false)
            PDF::SetMargins(10, 5, 10, true);
            PDF::AddPage();

            PDF::SetLineStyle(array('width' => 0.50, 'color' => array(0, 0, 0)));
            PDF::Rect(0, 0, PDF::getPageWidth(), PDF::getPageHeight());
            PDF::SetFont('times', 'B', 11);
            //headers
            $image_path = '\resources\images\kgs-logo.png';
            // Image($file, $x='', $y='', $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false)
            PDF::Image(getcwd() . $image_path, 0, 2, 25, 28, 'PNG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
            PDF::SetY(32);
            PDF::Cell(0, 2, 'REPUBLIC OF ZAMBIA', 0, 1, 'C');
            PDF::Cell(0, 5, 'MINISTRY OF EDUCATION', 0, 1, 'C');
            PDF::Cell(0, 5, 'P.O BOX 50093', 0, 1, 'C');
            PDF::Cell(0, 5, 'LUSAKA', 0, 1, 'C');
            PDF::SetY(2);
            // Start clipping.
            PDF::SetFont('times', 'I', 9);
            PDF::StartTransform();
            $html = '<p>All communications should be addressed to:<br>';
            $html .= 'The Permanent Secretary Ministry of Education, Science, Vocational Training and Early Education.</p>';
            $html .= '<p>Not to an individual by name</p>';
            $html .= '<p>Telephone: <span>250855/251315/251283<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                              251293/211318/251219<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                              251003/251319</span></p>';
            //$html = $left_header_one;
            #writeHTMLCell(w, h, x, y, html = '', border = 0, ln = 0, fill = 0, reseth = true, align = '', autopadding = true)
            PDF::writeHTMLCell(70, 0, 5, 9, $html, 0, 1, 0, 1, 'L');
            PDF::SetFont('times', 'BI', 9);
            PDF::SetY(7);
            PDF::Cell(0, 2, 'In reply please quote:', 0, 1, 'R');
            PDF::Cell(0, 2, 'No:' . $ref, 0, 1, 'R');
            //PDF::SetY(40);
            PDF::SetFont('times', '', 10);
            $html = '<p>' . $date . '</p>';
            $html .= '<p>Province of School:&nbsp;&nbsp;' . strtoupper($school_province_name) . '<br>';
            $html .= 'District of School:&nbsp;&nbsp;' . $school_district_code . '-' . strtoupper($school_district_name) . '<br>';
            $html .= 'School of Learner:&nbsp;&nbsp;' . strtoupper($school_name) . '<br>';
            $html .= 'District of Learner:&nbsp;&nbsp;' . strtoupper($district_name) . '<br>';
            $html .= 'CWAC:&nbsp;&nbsp;' . $cwac_code . '-' . strtoupper($cwac_name) . '<br>';
            $html .= 'C/O:&nbsp;&nbsp;' . strtoupper($hhh_fname) . ' ' . strtoupper($hhh_lname) . '<br></p>';
            PDF::writeHTMLCell(0, 0, 5, 60, $html, 0, 1, 0, 1, 'L');
            PDF::SetFont('times', '', 11);
            $salutation = '<p>Dear <b>' . strtoupper($girl_first_name) . '&nbsp;' . strtoupper($girl_last_name) . ' (' . $beneficiary_id . ')</b></p>';
            $salutation .= '<p><b>RE: </b><u><b>ADMISSION LETTER FOR ' . $category_name . ' GIRLS ' . $start_year . ' SCHOLARSHIP: YOURSELF</b></u></p>';
            PDF::writeHTMLCell(0, 0, 5, 100, $salutation, 0, 1, 0, 1, 'L');
            $letter = '<p>Refer to the above captioned subject matter.</p>';//p1
            $letter .= '<p>The Government of the Republic of Zambia, through the Ministry of Education is 
                    implementing Keeping Girls in School initiative; a component of Girls Education and 
                    Women Empowerment Livelihood (GEWEL) project.
                    The ministry has undertaken a process of identifying the beneficiaries who are girls 
                    in Social Cash Transfer households to access Secondary Education through payment of 
                    School fees.</p>';

            $grade = $grade == 8 ? 'in <b> Form I' : 'in <b> Grade '. $grade;

            $letter .= '<p>In view of the above, I would like to congratulate you on your selection for 
                    Keeping Girls in School Scholarship at <b>' . strtoupper($school_name) . '</b> school 
                    as a <b>' . strtoupper($ben_school_status) . '</b> beginning in Term 3 of <b>' . $start_year . 
                    '</b> ' . $grade . '</b> and ending in <b>' . $end_year . '</b>. ' . $extension_txt . '
                    This is a provisional offer, which is dependent on you reporting to school on 
                    <b>' . $reporting_date . '</b> and subsequently attending school regularly.</p>';
            $letter .= '<p>Please be informed that when you report to your assigned school, carry this admission 
                    letter along with your grade 7 or 9 certificate as being proof of passing and upon producing 
                    the two documents you will be enrolled in school under KGS Scholarship. The grace period for 
                    reporting is until <b>' . $grace_period . '</b>. Further, you are encouraged to work hard 
                    in your schoolwork to avoid failure, which may lead to withdrawal of the scholarship.</p>';
            $letter .= '<p>For any clarification or concern you may have, please contact your CWAC in your area, 
                    if your concerns are not addressed you can contact the head teacher at the school to which 
                    you have been assigned and in an event that your head teacher does not address your concerns, 
                    please do not hesitate to contact the District Education Board Secretary (DEBS) ' . $debs_title . ' ' . 
                    $debs_name . ' in your district on the telephone number ' . $debs_phone . '.</p>';
            PDF::writeHTMLCell(0, 0, 5, 117, $letter, 0, 1, 0, 1, 'L');
            PDF::ln(5);
            // $sign = '<p><b>' . $ps . '</b><br>Permanent Secretary<br>Ministry of Education</p>';
            //PDF::writeHTMLCell(70, 0, 5, 230, $sign, 0, 1, 0, 1, 'L');
            $signP1 = '<p><b>' . $ps . '</b></p>';
            $signP2 = '<p>KGS Coordinator<br><b><i>for</i> Permanent Secretary - Administration<br>Ministry of Education</b></p>';
            $footerText = '<p>*For any complaint relating to Keeping Girls in School and Beyond Initiative or your welfare (GBV, School Bullying or HIV) 
                            please call Lifeline ChildLine (Toll-Free) on <b>933</b> or <b>116</b>.</p>';
            PDF::Image(getcwd() . $signature_path, 0, '', 25, 12, '', '', 'N', true, 300, 'L', false, false, 0, false, false, false);
            PDF::writeHTML($signP1, true, 0, true, true);
            PDF::writeHTML($signP2, true, 0, true, true);
            //==========///////////////////////////////////////////////////////////////////===========//
            PDF::SetY(-18);
            PDF::writeHTML($footerText, true, 0, true, true);
        } else {
            print_r('Problem encountered getting details!!');
            exit();
        }
        PDF::Output($beneficiary_id . '_offerletter_' . $track_code . '_version' . $version . '.pdf', 'D');
    }

    //school capacity assessments
    public function printCapacityAssessmentsForms($year, $provinces, $districts, $schools)
    {
        $filename = 'capacity_assessment_form';
        //todo:: generation can be per school, per district or per province....this is a bit tricky...school supersedes all, district supersedes provinces
        if (isset($schools) && count(json_decode($schools)) > 0) {//schools selected
            $schoolsArray = json_decode($schools);
            $this->generateCapacityAssessmentsForms($schoolsArray, $year, $filename);
        } else if (isset($districts) && count(json_decode($districts)) > 0) {//schools not set..now use districts
            //get all schools in the selected districts
            $districtsArray = json_decode($districts);
            $schoolsArray = DB::table('school_information')->select('id')->whereIn('district_id', $districtsArray)->get();
            $schoolsArray = convertStdClassObjToArray($schoolsArray);
            $schoolsArray = convertAssArrayToSimpleArray($schoolsArray, 'id');
            $this->generateCapacityAssessmentsForms($schoolsArray, $year, $filename);
        } else if (isset($provinces) && count(json_decode($provinces)) > 0) {//schools,districts not set...now use provinces
            //get all schools in the selected provinces
            $provincesArray = json_decode($provinces);
            $schoolsArray = DB::table('school_information')->select('id')->whereIn('province_id', $provincesArray)->get();
            $schoolsArray = convertStdClassObjToArray($schoolsArray);
            $schoolsArray = convertAssArrayToSimpleArray($schoolsArray, 'id');
            $this->generateCapacityAssessmentsForms($schoolsArray, $year, $filename);
        } else {//nothing set....how did you find yourself here??

        }
    }

    public function generateCapacityAssessmentsForms($schoolsArray, $year, $filename)
    {
        //school details
        foreach ($schoolsArray as $school_id) {
            //todo::get school information
            $qry = DB::table('school_information')
                ->leftJoin('districts', 'school_information.district_id', '=', 'districts.id')
                ->leftJoin('provinces', 'districts.province_id', '=', 'provinces.id')
                ->select('school_information.*', 'districts.code as district_code', 'districts.name as district_name', 'provinces.name as province_name')
                ->where('school_information.id', $school_id);
            $school_info = $qry->first();
            if (is_null($school_info)) {
                continue;
            }
            $school_code = $school_info->code;
            $school_name = aes_decrypt($school_info->name);
            $district_code = $school_info->district_code;
            $district_name = aes_decrypt($school_info->district_name);
            $province_name = aes_decrypt($school_info->province_name);

            // ===========///////////////////////////////////////////////////////////////////==========//
            PDF::SetTitle('Capacity Assessment');
            PDF::AddPage('L');
            PDF::SetAutoPageBreak(TRUE, 0);//true sets it to on and 0 means margin is zero from sides
            PDF::SetFont('helvetica', '', 9);
            //headers
            $image_path = '\resources\images\kgs-logo.png';
            // Image($file, $x='', $y='', $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false)
            PDF::Image(getcwd() . $image_path, 0, 16, 40, 30, 'PNG', '', 'T', false, 300, 'R', false, false, 0, false, false, false);
            PDF::SetY(5);
            //PDF::Cell(0, 30, '', 0, 1);
            PDF::Cell(0, 5, 'MINISTRY OF EDUCATION - KEEPING GIRLS IN SCHOOL PROGRAMME', 0, 1, 'L');
            //PDF::SetX(35);
            $checklist_header = '<p><b>' . strtoupper('Capacity Assessment Form for the Year ') . $year . '</b></p>';
            //PDF::writeHTML($checklist_header, true, false, false, false, 'L');
            PDF::writeHTMLCell(0, 10, 10, 10, $checklist_header, 0, 1, 0, true, 'L', true);
            //PDF::writeHTMLCell(0,10,$checklist_header, 0, 0, 0, 0, 'L');
            //PDF::ln();
            //PDF::Image(getcwd() . $image_path, 15, 30, 90, 35);
            //PDF::SetY(50);
            //PDF::setY(57);
            $school_table = '<table border="1" width="650" cellpadding="3">
                       <tbody>
                       <tr>
                           <td>Name of School</td>
                           <td>' . $school_code . '&nbsp;-&nbsp;' . $school_name . '</td>
                       </tr>
                       <tr>
                           <td>EMIS Code</td>
                           <td>' . $district_code . '-' . $school_code . '</td>
                       </tr>
                       <tr>
                           <td>Province of School</td>
                           <td><b>' . $province_name . '</b></td>
                       </tr>
                       <tr>
                           <td>District of School</td>
                             <td>' . $district_code . '&nbsp;-&nbsp;' . $district_name . '</td>
                       </tr>
                       </tbody></tbody>
                       </table>';
            PDF::writeHTML($school_table, true, false, false, false, 'L');
            //==========///////////////////////////////////////////////////////////////////===========//
            //==========///////////////////////////////////////////////////////////////////===========//
            $htmlTable = '
                         <style>
                          table {
                                border-collapse: collapse;
                                white-space:nowrap;
                          }

                          table, th, td {
                                border: 1px solid black;
                          }
                          </style>
              <table border="1" cellpadding="2" align="center">
			   <thead>
				<tr>
				    <th>Grade</th>
		  			<th>Classroom Maximum</th>
					<th>Occupied Space</th>
					<th>Available Space (Optional)</th>
				</tr>
				</thead>
				<tbody>
				<tr>
				<td>Assessments for Grade 8</td>
				<td></td>
				<td></td>
				<td></td>
				</tr>
				<tr>
				<td>Assessments for Grade 9</td>
				<td></td>
				<td></td>
				<td></td>
				</tr>
				<tr>
				<td>Assessments for Grade 10</td>
				<td></td>
				<td></td>
				<td></td>
				</tr>
				<tr>
				<td>Assessments for Grade 11</td>
				<td></td>
				<td></td>
				<td></td>
				</tr>
				<tr>
				<td>Assessments for Grade 12</td>
				<td></td>
				<td></td>
				<td></td>
                </tr>
                </tbody>
                </table>';
            PDF::writeHTML($htmlTable, true, false, false, false, 'C');
            PDF::SetFont('', 'OI', 8);
            $user = aes_decrypt(\Auth::user()->first_name) . ' ' . aes_decrypt(\Auth::user()->last_name);
            PDF::SetY(200);
            PDF::Cell(0, 2, 'Printed by ' . $user . ' on ' . date('d/m/Y'), 0, 1, 'R');
            //==========///////////////////////////////////////////////////////////////////===========//
            $footerText = '<p>*For any complaint relating to Keeping Girls in School and Beyond Initiative or your welfare <b>(GBV, School Bullying or HIV)</b> 
                                please call on <b>+260953003103</b> <b>(regular call charges may apply)</b>.</p>';
            PDF::SetY(-8);
            PDF::writeHTML($footerText, true, 0, true, true);
        }
        PDF::Output($filename . time() . '.pdf', 'I');
    }

    //Grade NINES Promotion Forms
    public function printGradeNineSchPlacementForms($provinces, $districts, $schools)
    {
        $checklist_id = 5;
        $filename = 'School_Placement';
        //todo:: generation can be per school, district or per province....this is a bit tricky...schools supersedes all
        if (isset($schools) && count(json_decode($schools)) > 0) {//schools selected
            $school_ids = json_decode($schools);
            $qry = DB::table('school_information')
                ->leftJoin('districts', 'school_information.district_id', '=', 'districts.id')
                ->leftJoin('provinces', 'districts.province_id', '=', 'provinces.id')
                ->select('school_information.*', 'districts.code as district_code', 'districts.name as district_name', 'provinces.name as province_name')
                ->whereIn('school_information.id', $school_ids);
            $schoolsArray = $qry->get();
            $this->generateGradeNineSchPlacementForms($checklist_id, $schoolsArray, $filename);
        } else if (isset($districts) && count(json_decode($districts)) > 0) {//schools not set..now use districts
            //get all schools in the selected districts
            $district_ids = json_decode($districts);
            $qry = DB::table('school_information')
                ->leftJoin('districts', 'school_information.district_id', '=', 'districts.id')
                ->leftJoin('provinces', 'districts.province_id', '=', 'provinces.id')
                ->select('school_information.*', 'districts.code as district_code', 'districts.name as district_name', 'provinces.name as province_name')
                ->whereIn('school_information.district_id', $district_ids);
            $schoolsArray = $qry->get();
            $this->generateGradeNineSchPlacementForms($checklist_id, $schoolsArray, $filename);
        } else if (isset($provinces) && count(json_decode($provinces)) > 0) {//schools,districts not set...now use provinces
            //get all schools in the selected provinces
            $province_ids = json_decode($provinces);
            $qry = DB::table('school_information')
                ->leftJoin('districts', 'school_information.district_id', '=', 'districts.id')
                ->leftJoin('provinces', 'districts.province_id', '=', 'provinces.id')
                ->select('school_information.*', 'districts.code as district_code', 'districts.name as district_name', 'provinces.name as province_name')
                ->whereIn('school_information.province_id', $province_ids);
            $schoolsArray = $qry->get();
            $this->generateGradeNineSchPlacementForms($checklist_id, $schoolsArray, $filename);
        } else {//nothing set....how did you find yourself here??

        }
    }

    public function generateGradeNineSchPlacementForms($checklist_id, $schoolsArray, $filename)
    {
        //checklist details
        $checklist_details = DB::table('checklist_types')->where('id', $checklist_id)->first();
        $checklist_name = aes_decrypt($checklist_details->name);
        $checklist_ref = $checklist_details->reference;
        $checklist_code = aes_decrypt($checklist_details->code);
        //todo::get questions/checklist items
        $quizs = DB::table('checklist_items')
            ->select('name', 'order_no')
            ->where('checklist_id', $checklist_id)
            ->orderBy('order_no')
            ->get();
        $numOfQuizes = count($quizs);
        foreach ($schoolsArray as $school_info) {
            $school_code = $school_info->code;
            $school_name = $school_info->name;
            $district_code = $school_info->district_code;
            $district_name = $school_info->district_name;
            $province_name = $school_info->province_name;
            $beneficiary_status = 4;//$stage;
            //todo::get beneficiary information
            $school_id = $school_info->id;
            $where = array(
                //'t1.batch_id' => $batch_id,
                't1.school_id' => $school_id,
                't1.current_school_grade' => 9,
                't1.beneficiary_status' => $beneficiary_status,
                't1.verification_recommendation' => 1
            );
            $qry = DB::table('grade_nines_for_promotion as t0')
                ->join('beneficiary_information as t1', 't0.girl_id', '=', 't1.id')
                ->leftJoin('households', 't1.household_id', '=', 'households.id')
                ->leftJoin('cwac', 't1.cwac_id', '=', 'cwac.id')
                ->leftJoin('wards', 't1.ward_id', '=', 'wards.id')
                ->leftJoin('constituencies', 't1.constituency_id', '=', 'constituencies.id')
                ->select(DB::raw('t1.id,t1.beneficiary_id,t1.exam_number,CASE WHEN decrypt(t1.first_name) IS NULL THEN t1.first_name ELSE decrypt(t1.first_name) END as first_name, CASE WHEN decrypt(t1.last_name) IS NULL THEN t1.last_name ELSE decrypt(t1.last_name) END as last_name,t1.dob,t1.exam_grade,t1.current_school_grade,
                                  households.hhh_fname,households.hhh_lname,cwac.name as cwac_name,cwac.code as cwac_code,wards.name as ward_name,wards.code as ward_code,constituencies.name as constituency_name,constituencies.code as constituency_code'))
                ->where($where)
                ->where('t0.stage', 1)//data entry...to tally with summaries
                ->whereNotIn('t1.enrollment_status', array(2, 4));

            /*$qry = DB::table('beneficiary_information as t1')
                ->leftJoin('households', 't1.household_id', '=', 'households.id')
                ->leftJoin('cwac', 't1.cwac_id', '=', 'cwac.id')
                ->leftJoin('wards', 't1.ward_id', '=', 'wards.id')
                ->leftJoin('constituencies', 't1.constituency_id', '=', 'constituencies.id')
                ->select(DB::raw('t1.id,t1.beneficiary_id,t1.exam_number,CASE WHEN decrypt(t1.first_name) IS NULL THEN t1.first_name ELSE decrypt(t1.first_name) END as first_name, CASE WHEN decrypt(t1.last_name) IS NULL THEN t1.last_name ELSE decrypt(t1.last_name) END as last_name,t1.dob,t1.exam_grade,t1.current_school_grade,
                                  households.hhh_fname,households.hhh_lname,cwac.name as cwac_name,cwac.code as cwac_code,wards.name as ward_name,wards.code as ward_code,constituencies.name as constituency_name,constituencies.code as constituency_code'))
                ->where($where)
                ->whereNotIn('t1.enrollment_status', array(2, 4));*/

            $qry->orderBy('t1.school_id');
            $beneficiaries = $qry->get();
            if (is_null($beneficiaries) || count($beneficiaries) < 1) {
                continue;
            }
            // ===========///////////////////////////////////////////////////////////////////==========//
            PDF::SetTitle('Placement Form');
            PDF::SetAutoPageBreak(TRUE, 2);//true sets it to on and 0 means margin is zero from sides
            PDF::SetTitle('Checklist');
            PDF::setMargins(10, 10, 10, true);

            PDF::AddPage('L');
            PDF::SetFont('times', '', 7);
            //headers
            $image_path = '\resources\images\kgs-logo.png';
            // Image($file, $x='', $y='', $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false)
            PDF::Image(getcwd() . $image_path, 0, 23, 40, 30, 'PNG', '', 'T', false, 300, 'R', false, false, 0, false, false, false);
            PDF::SetY(7);
            // PDF::Cell(0, 2, 'TEAM ID: A', 0, 1, 'R');
            PDF::SetY(5);
            //PDF::Cell(0, 30, '', 0, 1);
            PDF::Cell(0, 5, 'MINISTRY OF EDUCATION - KEEPING GIRLS IN SCHOOL PROGRAMME', 0, 1, 'L');
            //PDF::SetX(35);
            $checklist_header = '<p><b>' . $checklist_ref . '</b>: ' . strtoupper($checklist_name) . '</p>';
            //PDF::writeHTML($checklist_header, true, false, false, false, 'L');
            PDF::writeHTMLCell(0, 10, 10, 10, $checklist_header, 0, 1, 0, true, 'L', true);
            PDF::Cell(200, 5, 'Section I', 0, 1, 'L');
            //PDF::setY(57);
            $school_table = '<table border="1" width="650" cellpadding="3">
                       <tbody>
                       <tr>
                           <td>Name of School</td>
                           <td>' . $school_code . '&nbsp;-&nbsp;' . $school_name . '</td>
                       </tr>
                       <tr>
                           <td>EMIS Code</td>
                           <td>' . $district_code . '-' . $school_code . '</td>
                       </tr>
                       <tr>
                           <td>Province of School</td>
                           <td><b>' . $province_name . '</b></td>
                       </tr>
                       <tr>
                           <td>District of School</td>
                             <td>' . $district_code . '&nbsp;-&nbsp;' . $district_name . '</td>
                       </tr>
                       <tr>
                           <td>School contact person</td>
                           <td></td>
                       </tr>
                       </tbody></tbody>
                       </table>';
            PDF::writeHTML($school_table, true, false, false, false, 'L');
            //==========///////////////////////////////////////////////////////////////////===========//
            //==========///////////////////////////////////////////////////////////////////===========//
            $htmlTable = '
                         <style>
                          table {
                                border-collapse: collapse;
                                white-space:nowrap;
                          }

                          table, th, td {
                                border: 1px solid black;
                          }
                          </style>
              <table border="1" cellpadding="2" align="center">
			   <thead>
			   <tr><td colspan="11">Section II</td><td colspan="' . $numOfQuizes . '">Section III</td></tr>
			    <tr>
			    <td colspan="11"></td>
			    <td colspan="' . $numOfQuizes . '">Note: This "Assignment" section needs to be filled by the PIU</td>
			    </tr>
				<tr>
				    <th>Name of District</th>
				    <th>Constituency</th>
				    <th>Ward</th>
				    <th>CWAC</th>
		  			<th>Beneficiary ID</th>
		  			<th>Examination No.</th>
					<th>Girl First Name</th>
					<th>Girl Last Name</th>
					<th>HH Head First Name</th>
					<th>HH Head Last Name</th>
					<th>Type of exam the candidate sat for</th>';
            foreach ($quizs as $quiz) {
                $header = 'Q' . $quiz->order_no . '. ' . $quiz->name;
                $htmlTable .= '<th>' . $header . '</th>';
            }
            $htmlTable .= '</tr></thead><tbody>';
            foreach ($beneficiaries as $beneficiary) {
                $htmlTable .= '<tr>
                             <td>' . $district_code . '-' . $district_name . '</td>
                             <td>' . $beneficiary->constituency_code . '-' . $beneficiary->constituency_name . '</td>
                             <td>' . $beneficiary->ward_code . '-' . $beneficiary->ward_name . '</td>
                             <td>' . $beneficiary->cwac_code . '-' . $beneficiary->cwac_name . '</td>
                             <td>' . $beneficiary->beneficiary_id . '</td>
                             <td>' . $beneficiary->exam_number . '</td>
                             <td>' . $beneficiary->first_name . '</td>
                             <td>' . $beneficiary->last_name . '</td>
                             <td>' . $beneficiary->hhh_fname . '</td>
                             <td>' . $beneficiary->hhh_lname . '</td>
                             <td>Grade ' . $beneficiary->current_school_grade . ' Exams</td>';
                for ($i = 1; $i <= $numOfQuizes; $i++) {
                    $htmlTable .= '<td></td>';
                }
                $htmlTable .= '</tr>';
            }
            $htmlTable .= '</tbody></table>';
            PDF::writeHTML($htmlTable, true, false, false, false, 'C');
            //==========///////////////////////////////////////////////////////////////////===========//
            $footerText = '<p>*For any complaint relating to Keeping Girls in School and Beyond Initiative or your welfare <b>(GBV, School Bullying or HIV)</b> 
                        please call on <b>+260953003103</b> <b>(regular call charges may apply)</b>.</p>';
            PDF::SetY(-8);
            PDF::writeHTML($footerText, true, 0, true, true);
        }
        //PDF::Output($filename . time() . '.pdf', 'I');
        PDF::Output($filename . time() . '_' . $district_name . '.pdf', 'I');
    }

    public function testJasper()
    {
        // print_r('C:\xampp\htdocs\zambia_moge\trunk\mis\development/backend/vendor/autoload.php');


        $input = __DIR__ . '/vendor/geekcom/phpjasper/examples/hello_world.jasper';
        $output = __DIR__ . '/vendor/geekcom/phpjasper/examples';
        $options = [
            'format' => ['pdf', 'rtf']
        ];
        $options = [
            'db_connection' => [
                'driver' => 'mysql',
                'host' => Config('database.mysql.host'),
                'port' => Config('database.mysql.port'),
                'database' => Config('database.mysql.database'),
                'username' => Config('database.mysql.username'),
                'password' => Config('database.mysql.password')
            ]];

        JasperPHP::process(
            getcwd() . '/jasper_reports/testreport1.jasper', //Input file
            getcwd() . '/jasper_reports/', //Output file without extension
            array("pdf"), //Output format
            $options
        //array("php_version" => phpversion()) //Parameters array
        // Config::get('database.connections.mysql'), //DB connection array
        )->execute();
        /*JasperPHP::process(
            $input,
            $output,
            $options
        )->execute();*/
    }

}

