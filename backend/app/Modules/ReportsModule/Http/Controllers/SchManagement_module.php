<?php
/**
 * Created by PhpStorm.
 * User: Kip
 * Date: 12/7/2017
 * Time: 9:49 AM
 */

namespace App\Modules\ReportsModule\Http\Controllers;

use Carbon\Carbon;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDF;

class SchManagement_module extends Controller
{

    public function generateSchoolMonitoringForms(Request $req)
    {
        $provinces = $req->input('provincesStr');
        $districts = $req->input('districtsStr');
        $schools = $req->input('schoolsStr');
        if (!is_array(json_decode($schools))) {
            $school_ids = array($schools);
        } else {
            $school_ids = json_decode($schools);
        }
        $schoolsArray = array();
        if (isset($schools) && count($school_ids) > 0) {//schools selected
            //$school_ids = json_decode($schools);
            //todo::get school information
            $qry = DB::table('school_information')
                ->join('districts', 'school_information.district_id', '=', 'districts.id')
                ->leftjoin('beneficiary_information', function ($join) {
                    $join->on('school_information.id', '=', 'beneficiary_information.school_id')
                        ->where('beneficiary_information.enrollment_status', 1);
                })
                ->leftJoin('school_types', 'school_information.school_type_id', '=', 'school_types.id')
                ->leftJoin('school_contactpersons', function ($join) {//get head teacher details
                    $join->on('school_information.id', '=', 'school_contactpersons.school_id')
                        ->where('school_contactpersons.designation_id', '=', DB::raw(1));
                })
                ->select('school_information.*', 'school_contactpersons.full_names', 'school_contactpersons.telephone_no as head_telephone', 'districts.code as district_code', 'districts.name as district_name', 'school_types.name as school_type_name')
                ->whereIn('school_information.id', $school_ids)
                ->groupBy('school_information.id');
            $schoolsArray = $qry->get();
        } else if (isset($districts) && count(json_decode($districts)) > 0) {//schools not set..now use districts
            //get all schools in the selected districts
            $district_ids = json_decode($districts);
            //todo::get school information
            $qry = DB::table('school_information')
                ->join('districts', 'school_information.district_id', '=', 'districts.id')
                ->join('beneficiary_information', function ($join) {
                    $join->on('school_information.id', '=', 'beneficiary_information.school_id')
                        ->where('beneficiary_information.enrollment_status', 1);
                })
                ->leftJoin('school_types', 'school_information.school_type_id', '=', 'school_types.id')
                ->leftJoin('school_contactpersons', function ($join) {//get head teacher details
                    $join->on('school_information.id', '=', 'school_contactpersons.school_id')
                        ->where('school_contactpersons.designation_id', '=', DB::raw(1));
                })
                ->select('school_information.*', 'school_contactpersons.full_names', 'school_contactpersons.telephone_no as head_telephone', 'districts.code as district_code', 'districts.name as district_name', 'school_types.name as school_type_name')
                ->whereIn('school_information.district_id', $district_ids)
                ->groupBy('school_information.id');
            $schoolsArray = $qry->get();
        } else if (isset($provinces) && count(json_decode($provinces)) > 0) {//schools,districts not set...now use provinces
            //get all schools in the selected provinces
            $province_ids = json_decode($provinces);
            //todo::get school information
            $qry = DB::table('school_information')
                ->join('districts', 'school_information.district_id', '=', 'districts.id')
                ->join('beneficiary_information', function ($join) {
                    $join->on('school_information.id', '=', 'beneficiary_information.school_id')
                        ->where('beneficiary_information.enrollment_status', 1);
                })
                ->leftJoin('school_types', 'school_information.school_type_id', '=', 'school_types.id')
                ->leftJoin('school_contactpersons', function ($join) {//get head teacher details
                    $join->on('school_information.id', '=', 'school_contactpersons.school_id')
                        ->where('school_contactpersons.designation_id', '=', DB::raw(1));
                })
                ->select('school_information.*', 'school_contactpersons.full_names', 'school_contactpersons.telephone_no as head_telephone', 'districts.code as district_code', 'districts.name as district_name', 'school_types.name as school_type_name')
                ->whereIn('school_information.province_id', $province_ids);
            $schoolsArray = $qry->get()
                ->groupBy('school_information.id');
        }
        $this->printSchoolMonitoringForm($schoolsArray);
    }

    public function printSchoolMonitoringForm($schools)
    {
        if (is_null($schools)) {
            print_r('Details about the school you selected could not be found!!');
            return;
        }
        foreach ($schools as $school_info) {
            $school_id = $school_info->id;
            $school_code = $school_info->code;
            $school_name = aes_decrypt($school_info->name);
            $school_email = aes_decrypt($school_info->email_address);
            $school_type = aes_decrypt($school_info->school_type_name);
            $head_name = aes_decrypt($school_info->full_names);
            $head_phone = aes_decrypt($school_info->head_telephone);
            $district_code = $school_info->district_code;
            $district_name = aes_decrypt($school_info->district_name);
            //todo::get beneficiary information
            $where = array(
                'school_id' => $school_id,
                'enrollment_status' => 1
            );
            $qry = DB::table('beneficiary_information')
                ->select('beneficiary_information.*')
                ->where($where);
            $beneficiaries = $qry->get();
            if (is_null($beneficiaries)) {
                continue;
            }
            $beneficiaries = convertStdClassObjToArray($beneficiaries);
            $beneficiaries = decryptArray($beneficiaries);
            // ===========///////////////////////////////////////////////////////////////////==========//
            PDF::SetTitle('School Monitoring Tool');
            PDF::SetAutoPageBreak(TRUE, 25);//true sets it to on and 0 means margin is zero from sides
            PDF::setMargins(10, 18, 10, true);
            PDF::AddPage('P');
            // PDF::SetAutoPageBreak(TRUE, 0);//true sets it to on and 0 means margin is zero from sides
            PDF::SetFont('helvetica', 'B', 11);
            //headers
            $image_path = '\resources\images\kgs-logo.png';
            // Image($file, $x='', $y='', $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false)
            PDF::Image(getcwd() . $image_path, 'C', 2, 25, 28, 'PNG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
            PDF::SetY(32);
            PDF::Cell(0, 2, 'REPUBLIC OF ZAMBIA', 0, 1, 'C');
            PDF::Cell(0, 5, 'MINISTRY OF EDUCATION', 0, 1, 'C');
            PDF::Cell(0, 5, 'GIRLS’ EDUCATION AND WOMEN EMPOWERMENT AND LIVELIHOOD (GEWEL) PROJECT', 0, 1, 'C');
            PDF::ln(5);
            PDF::Cell(0, 5, 'Keeping Girls in School Initiative School Monitoring Tool', 0, 1, 'C');
            PDF::SetY(2);
            PDF::setY(7);
            PDF::SetFont('helvetica', '', 10);
            PDF::Cell(0, 2, 'YEAR: ' . date('Y'), 0, 1, 'R');
            PDF::SetY(70);
            PDF::Cell(0, 5, 'Type of Monitoring:_____________', 0, 1, 'L');
            PDF::ln(5);
            PDF::Cell(0, 5, 'Date:_____________', 0, 1, 'L');
            PDF::ln(5);
            PDF::Cell(0, 5, 'Inspectors:_____________', 0, 1, 'L');
            PDF::ln(5);
            $section_a = '<h4><u>SECTION A: SCHOOL DETAILS</u></h4>';
            PDF::writeHTML($section_a, true, false, false, false, 'L');
            PDF::ln(5);
            PDF::Cell(50, 5, 'District: ' . $district_code . '-' . $district_name, 0, 0, 'L');
            PDF::Cell(90, 5, 'School Name: ' . $school_name, 0, 0, 'L');
            PDF::Cell(40, 5, 'EMIS Number: ' . $school_code, 0, 1, 'L');
            PDF::ln(5);
            PDF::Cell(100, 5, 'School Email Address: ' . $school_email, 0, 0, 'L');
            PDF::Cell(50, 5, 'School Type (B or Day): ' . $school_type, 0, 1, 'L');
            PDF::ln(5);
            $section_b = '<h4><u>SECTION B: SCHOOL HEAD DETAILS</u></h4>';
            PDF::writeHTML($section_b, true, false, false, false, 'L');
            PDF::ln(5);
            PDF::Cell(100, 5, 'School Head Tr. Name: ' . $head_name, 0, 0, 'L');
            PDF::Cell(50, 5, 'Phone Number: ' . $head_phone, 0, 1, 'L');
            PDF::ln(5);
            $section_c = '<h4><u>SECTION C: KGS BENEFICIARY DETAILS</u></h4>';
            PDF::writeHTML($section_c, true, false, false, false, 'L');
            PDF::ln(5);
            //PDF::setY(57);
            $beneficiaries_table = '<table border="1" cellpadding="3">
                       <thead>
                       <tr>
                            <td>Name</td>
                            <td>Beneficiary ID</td>
                            <td>Grade</td>
                            <td>Verified?</td>
                            <td>Reason(s) (dropdown)</td>
                            <td>Reason(s) (open)</td>
                       </tr>                  
                       </thead><tbody>';
            $grade_sevens = array_filter($beneficiaries, function ($v) {
                if ($v['current_school_grade'] == 7) {
                    return $v;
                }
            });
            $grade_sevens_total = count($grade_sevens);
            $grade_eights = array_filter($beneficiaries, function ($v) {
                if ($v['current_school_grade'] == 8) {
                    return $v;
                }
            });
            $grade_eights_total = count($grade_eights);
            $grade_nines = array_filter($beneficiaries, function ($v) {
                if ($v['current_school_grade'] == 9) {
                    return $v;
                }
            });
            $grade_nines_total = count($grade_nines);
            $grade_tens = array_filter($beneficiaries, function ($v) {
                if ($v['current_school_grade'] == 10) {
                    return $v;
                }
            });
            $grade_tens_total = count($grade_tens);
            $grade_elevens = array_filter($beneficiaries, function ($v) {
                if ($v['current_school_grade'] == 11) {
                    return $v;
                }
            });
            $grade_elevens_total = count($grade_elevens);
            $grade_twelves = array_filter($beneficiaries, function ($v) {
                if ($v['current_school_grade'] == 12) {
                    return $v;
                }
            });
            $grade_twelves_tootal = count($grade_twelves);
            if ($grade_sevens_total > 0) {
                $beneficiaries_table .= '<tr><td colspan="6"><span style="font-weight: bold">GRADE 7</span> &nbsp;&nbsp;[Total:' . $grade_sevens_total . ' &nbsp;Verified:____&nbsp; Missing:____]</td></tr>';
                foreach ($grade_sevens as $beneficiary) {
                    $beneficiaries_table .= '<tr>
                                            <td>' . $beneficiary['first_name'] . ' ' . $beneficiary['last_name'] . '</td>
                                            <td>' . $beneficiary['beneficiary_id'] . '</td>
                                            <td>' . $beneficiary['current_school_grade'] . '</td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            </tr>';
                }
            }
            if ($grade_eights_total > 0) {
                $beneficiaries_table .= '<tr><td colspan="6"><span style="font-weight: bold">GRADE 8</span> &nbsp;&nbsp;[Total:' . $grade_eights_total . ' &nbsp;Verified:____&nbsp; Missing:____]</td></tr>';
                foreach ($grade_eights as $beneficiary) {
                    $beneficiaries_table .= '<tr>
                                            <td>' . $beneficiary['first_name'] . ' ' . $beneficiary['last_name'] . '</td>
                                            <td>' . $beneficiary['beneficiary_id'] . '</td>
                                            <td>' . $beneficiary['current_school_grade'] . '</td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            </tr>';
                }
            }
            if ($grade_nines_total > 0) {
                $beneficiaries_table .= '<tr><td colspan="6"><span style="font-weight: bold">GRADE 9</span> &nbsp;&nbsp;[Total:' . $grade_nines_total . ' &nbsp;Verified:____&nbsp; Missing:____]</td></tr>';
                foreach ($grade_nines as $beneficiary) {
                    $beneficiaries_table .= '<tr>
                                            <td>' . $beneficiary['first_name'] . ' ' . $beneficiary['last_name'] . '</td>
                                            <td>' . $beneficiary['beneficiary_id'] . '</td>
                                            <td>' . $beneficiary['current_school_grade'] . '</td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            </tr>';
                }
            }
            if ($grade_tens_total > 0) {
                $beneficiaries_table .= '<tr><td colspan="6"><span style="font-weight: bold">GRADE 10</span> &nbsp;&nbsp;[Total:' . $grade_tens_total . ' &nbsp;Verified:____&nbsp; Missing:____]</td></tr>';
                foreach ($grade_tens as $beneficiary) {
                    $beneficiaries_table .= '<tr>
                                            <td>' . $beneficiary['first_name'] . ' ' . $beneficiary['last_name'] . '</td>
                                            <td>' . $beneficiary['beneficiary_id'] . '</td>
                                            <td>' . $beneficiary['current_school_grade'] . '</td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            </tr>';
                }
            }
            if ($grade_elevens_total > 0) {
                $beneficiaries_table .= '<tr><td colspan="6"><span style="font-weight: bold">GRADE 11</span> &nbsp;&nbsp;[Total:' . $grade_elevens_total . ' &nbsp;Verified:____&nbsp; Missing:____]</td></tr>';
                foreach ($grade_elevens as $beneficiary) {
                    $beneficiaries_table .= '<tr>
                                            <td>' . $beneficiary['first_name'] . ' ' . $beneficiary['last_name'] . '</td>
                                            <td>' . $beneficiary['beneficiary_id'] . '</td>
                                            <td>' . $beneficiary['current_school_grade'] . '</td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            </tr>';
                }
            }
            if ($grade_twelves_tootal > 0) {
                $beneficiaries_table .= '<tr><td colspan="6"><span style="font-weight: bold">GRADE 12</span> &nbsp;&nbsp;[Total:' . $grade_twelves_tootal . ' &nbsp;Verified:____&nbsp; Missing:____]</td></tr>';
                foreach ($grade_twelves as $beneficiary) {
                    $beneficiaries_table .= '<tr>
                                            <td>' . $beneficiary['first_name'] . ' ' . $beneficiary['last_name'] . '</td>
                                            <td>' . $beneficiary['beneficiary_id'] . '</td>
                                            <td>' . $beneficiary['current_school_grade'] . '</td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            </tr>';
                }
            }
            $beneficiaries_table .= '</tbody></table>';
            PDF::writeHTML($beneficiaries_table, true, false, false, false, 'L');
            PDF::ln(5);
            PDF::Cell(100, 5, 'Suggested Reason(s) for Discrepancy (If any):_____________', 0, 0, 'L');
            PDF::ln(8);
            $section_d = '<h4><u>SECTION D: EDUCATION QUALITY</u></h4>';
            PDF::writeHTML($section_d, true, false, false, false, 'L');
            PDF::ln(5);
            PDF::Cell(100, 5, 'Average number of pupils per class:_____________', 0, 0, 'L');
            PDF::Cell(50, 5, 'Average learning hours per day:_____________', 0, 1, 'L');
            PDF::ln(5);
            PDF::Cell(100, 5, 'Desk type (1/2/3 seater etc.):_____________', 0, 0, 'L');
            PDF::Cell(50, 5, 'Average number of pupils per Desk:_____________', 0, 1, 'L');
            PDF::ln(5);
            $section_e = '<h4><u>SECTION E: RECOMMENDATION/COMMENT</u></h4>';
            PDF::writeHTML($section_e, true, false, false, false, 'L');
            PDF::ln(5);
            PDF::Cell(0, 5, 'Recommendations/comments about the schools:_____________', 0, 1, 'L');
            PDF::ln(5);
            PDF::Cell(0, 5, 'Recommendations/comments from the girls:_____________', 0, 1, 'L');
            //==========///////////////////////////////////////////////////////////////////===========//
        }
        $file_name = 'monitoring_tool_' . time();
        PDF::Output($file_name . '.pdf', 'I');
    }

    public function printSchoolMonitoringReport(Request $req)
    {
        $school_id = $req->input('school_id');
        $monitoring_id = $req->input('report_id');
        //todo::get school information
        $qry = DB::table('school_information')
            ->join('districts', 'school_information.district_id', '=', 'districts.id')
            ->leftJoin('school_types', 'school_information.school_type_id', '=', 'school_types.id')
            ->leftJoin('school_contactpersons', function ($join) {//get head teacher details
                $join->on('school_information.id', '=', 'school_contactpersons.school_id')
                    ->where('school_contactpersons.designation_id', '=', DB::raw(1));
            })
            ->select('school_information.*', 'school_contactpersons.full_names', 'school_contactpersons.telephone_no as head_telephone', 'districts.code as district_code', 'districts.name as district_name', 'school_types.name as school_type_name')
            ->where('school_information.id', $school_id);
        $school_info = $qry->first();
        if (!is_null($school_info)) {
            $school_code = $school_info->code;
            $school_name = aes_decrypt($school_info->name);
            $school_email = aes_decrypt($school_info->email_address);
            $school_type = aes_decrypt($school_info->school_type_name);
            $head_name = aes_decrypt($school_info->full_names);
            $head_phone = aes_decrypt($school_info->head_telephone);
            $district_code = $school_info->district_code;
            $district_name = aes_decrypt($school_info->district_name);
        } else {
            print_r('Problem encountered getting school information!!');
            exit();
        }
        //todo::get monitoring details
        $qry = DB::table('school_monitoring_rpt')
            ->join('school_monitoring_types', 'school_monitoring_rpt.monitoring_type', '=', 'school_monitoring_types.id')
            ->select(DB::raw('school_monitoring_rpt.id,school_monitoring_rpt.school_id,school_monitoring_rpt.reference_number,school_monitoring_rpt.monitoring_date,
                        school_monitoring_rpt.discrepancy,school_monitoring_rpt.monitoring_type,school_monitoring_rpt.ave_pupils_class,school_monitoring_rpt.ave_learning_hours_day,
                        school_monitoring_rpt.desk_type,school_monitoring_rpt.ave_pupils_desk,school_monitoring_rpt.school_comments,school_monitoring_rpt.girls_comments,
                        YEAR(school_monitoring_rpt.monitoring_date) as monitoring_year,school_monitoring_types.name as monitoring_type_name'))
            ->where('school_monitoring_rpt.id', $monitoring_id);
        $monitoring_info = $qry->first();
        if (!is_null($monitoring_info)) {
            $monitoring_type = $monitoring_info->monitoring_type_name;
            $monitoring_date = converter22($monitoring_info->monitoring_date);
            $monitoring_year = $monitoring_info->monitoring_year;
            $ave_pupils_class = $monitoring_info->ave_pupils_class;
            $ave_learning_hours_day = $monitoring_info->ave_learning_hours_day;
            $desk_type = $monitoring_info->desk_type;
            $ave_pupils_desk = $monitoring_info->ave_pupils_desk;
            $school_comments = $monitoring_info->school_comments;
            $girls_comments = $monitoring_info->girls_comments;
            $discrepancy = $monitoring_info->discrepancy;
        } else {
            print_r('Problem encountered getting monitoring information!!');
            exit();
        }
        //todo::get monitoring inspectors
        $qry = DB::table('school_monitoring_inspectors')
            ->join('users', 'school_monitoring_inspectors.inspector_id', '=', 'users.id')
            ->select('school_monitoring_inspectors.id', 'users.first_name', 'users.last_name', 'users.phone', 'users.email')
            ->where('report_id', $monitoring_id);
        $inspectors_info = $qry->get();
        $inspectors_info = convertStdClassObjToArray($inspectors_info);
        $inspectors_info = decryptArray($inspectors_info);
        //todo::get monitoring summary
        $monitoring_summary = DB::table('school_monitoring_summary')
            ->where('report_id', $monitoring_id)
            ->get();
        $monitoring_summary = $monitoring_summary->mapWithKeys(function ($item) {
            return [$item->grade => $item];
        });
        //todo::get beneficiaries
        $qry = DB::table('monitoring_found_beneficiaries')
            ->join('beneficiary_information', 'monitoring_found_beneficiaries.girl_id', '=', 'beneficiary_information.id')
            ->leftJoin('missing_girls_reasons', 'monitoring_found_beneficiaries.reason', '=', 'missing_girls_reasons.id')
            ->select('beneficiary_information.first_name', 'beneficiary_information.last_name', 'beneficiary_information.beneficiary_id',
                'monitoring_found_beneficiaries.remark', 'monitoring_found_beneficiaries.verified', 'monitoring_found_beneficiaries.grade', 'missing_girls_reasons.name as reason_name')
            ->where('report_id', $monitoring_id);
        $verified_beneficiaries = $qry->get();
        $qry = DB::table('monitoring_missing_beneficiaries')
            ->join('beneficiary_information', 'monitoring_missing_beneficiaries.girl_id', '=', 'beneficiary_information.id')
            ->leftJoin('missing_girls_reasons', 'monitoring_missing_beneficiaries.reason', '=', 'missing_girls_reasons.id')
            ->select('beneficiary_information.first_name', 'beneficiary_information.last_name', 'beneficiary_information.beneficiary_id',
                'monitoring_missing_beneficiaries.remark', 'monitoring_missing_beneficiaries.verified', 'monitoring_missing_beneficiaries.grade', 'missing_girls_reasons.name as reason_name')
            ->where('report_id', $monitoring_id);
        $missing_beneficiaries = $qry->get();
        //$beneficiaries=array_merge($verified_beneficiaries,$missing_beneficiaries);
        $beneficiaries = $verified_beneficiaries->merge($missing_beneficiaries);
        /*  echo count($beneficiaries);
          print_r($beneficiaries);
          $where = array(
              'school_id' => $school_id,
              'enrollment_status' => 1
          );
          $qry = DB::table('beneficiary_information')
              ->select('beneficiary_information.*')
              ->where($where);
          $beneficiaries = $qry->get();
          if (is_null($beneficiaries)) {
              print_r('No beneficiaries found in this school!!');
              exit();
          }*/
        $beneficiaries = convertStdClassObjToArray($beneficiaries);
        $beneficiaries = decryptArray($beneficiaries);
        // ===========///////////////////////////////////////////////////////////////////==========//
        PDF::SetTitle('School Monitoring Tool');
        PDF::SetAutoPageBreak(TRUE, 25);//true sets it to on and 0 means margin is zero from sides
        PDF::setMargins(10, 18, 10, true);
        PDF::AddPage('P');
        // PDF::SetAutoPageBreak(TRUE, 0);//true sets it to on and 0 means margin is zero from sides
        PDF::SetFont('helvetica', 'B', 11);
        //headers
        $image_path = '\resources\images\kgs-logo.png';
        // Image($file, $x='', $y='', $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false)
        PDF::Image(getcwd() . $image_path, 'C', 2, 25, 28, 'PNG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
        PDF::SetY(32);
        PDF::Cell(0, 2, 'REPUBLIC OF ZAMBIA', 0, 1, 'C');
        PDF::Cell(0, 5, 'MINISTRY OF EDUCATION', 0, 1, 'C');
        PDF::Cell(0, 5, 'GIRLS’ EDUCATION AND WOMEN EMPOWERMENT AND LIVELIHOOD (GEWEL) PROJECT', 0, 1, 'C');
        PDF::ln(5);
        PDF::Cell(0, 5, 'Keeping Girls in School Initiative School Monitoring Tool', 0, 1, 'C');
        PDF::SetY(2);
        PDF::setY(7);
        PDF::SetFont('helvetica', '', 10);
        PDF::Cell(0, 2, 'YEAR: ' . $monitoring_year, 0, 1, 'R');
        PDF::SetY(70);
        PDF::Cell(0, 5, 'Type of Monitoring: ' . $monitoring_type, 0, 1, 'L');
        PDF::ln(5);
        PDF::Cell(0, 5, 'Date: ' . $monitoring_date, 0, 1, 'L');
        PDF::ln(5);
        PDF::Cell(0, 5, 'Inspectors:', 0, 1, 'L');
        PDF::ln(5);
        $inspectors_table = '<table border="1" cellpadding="3">
                       <thead>
                       <tr>
                            <td>Name</td>
                            <td>Phone</td>
                            <td>Email</td>                        
                       </tr>                  
                       </thead><tbody>';
        foreach ($inspectors_info as $inspector) {
            $inspectors_table .= '<tr>
                                    <td>' . $inspector['first_name'] . ' ' . $inspector['last_name'] . '</td>
                                    <td>' . $inspector['phone'] . '</td>
                                    <td>' . $inspector['email'] . '</td>
                                </tr>';
        }
        $inspectors_table .= '</tbody></table>';
        PDF::writeHTML($inspectors_table, true, false, false, false, 'L');
        $section_a = '<h4><u>SECTION A: SCHOOL DETAILS</u></h4>';
        PDF::writeHTML($section_a, true, false, false, false, 'L');
        PDF::ln(5);
        PDF::Cell(50, 5, 'District: ' . $district_code . '-' . $district_name, 0, 0, 'L');
        PDF::Cell(90, 5, 'School Name: ' . $school_name, 0, 0, 'L');
        PDF::Cell(40, 5, 'EMIS Number: ' . $school_code, 0, 1, 'L');
        PDF::ln(5);
        PDF::Cell(100, 5, 'School Email Address: ' . $school_email, 0, 0, 'L');
        PDF::Cell(50, 5, 'School Type (B or Day): ' . $school_type, 0, 1, 'L');
        PDF::ln(5);
        $section_b = '<h4><u>SECTION B: SCHOOL HEAD DETAILS</u></h4>';
        PDF::writeHTML($section_b, true, false, false, false, 'L');
        PDF::ln(5);
        PDF::Cell(100, 5, 'School Head Tr. Name: ' . $head_name, 0, 0, 'L');
        PDF::Cell(50, 5, 'Phone Number: ' . $head_phone, 0, 1, 'L');
        PDF::ln(5);
        $section_c = '<h4><u>SECTION C: KGS BENEFICIARY DETAILS</u></h4>';
        PDF::writeHTML($section_c, true, false, false, false, 'L');
        PDF::ln(5);
        //PDF::setY(57);
        $beneficiaries_table = '<table border="1" cellpadding="3">
                       <thead>
                       <tr>
                            <td>Name</td>
                            <td>Beneficiary ID</td>
                            <td>Grade</td>
                            <td>Verified?</td>
                            <td>Reason(s) (dropdown)</td>
                            <td>Reason(s) (open)</td>
                       </tr>                  
                       </thead><tbody>';
        $grade_sevens = array_filter($beneficiaries, function ($v) {
            if ($v['grade'] == 7) {
                return $v;
            }
        });
        $grade_sevens_total = count($grade_sevens);
        $grade_eights = array_filter($beneficiaries, function ($v) {
            if ($v['grade'] == 8) {
                return $v;
            }
        });
        $grade_eights_total = count($grade_eights);
        $grade_nines = array_filter($beneficiaries, function ($v) {
            if ($v['grade'] == 9) {
                return $v;
            }
        });
        $grade_nines_total = count($grade_nines);
        $grade_tens = array_filter($beneficiaries, function ($v) {
            if ($v['grade'] == 10) {
                return $v;
            }
        });
        $grade_tens_total = count($grade_tens);
        $grade_elevens = array_filter($beneficiaries, function ($v) {
            if ($v['grade'] == 11) {
                return $v;
            }
        });
        $grade_elevens_total = count($grade_elevens);
        $grade_twelves = array_filter($beneficiaries, function ($v) {
            if ($v['grade'] == 12) {
                return $v;
            }
        });
        $grade_twelves_tootal = count($grade_twelves);
        if ($grade_sevens_total > 0) {
            $beneficiaries_table .= '<tr><td colspan="6"><span style="font-weight: bold">GRADE 7</span> &nbsp;&nbsp;[Total:' . $monitoring_summary[7]->total_indicated . ' &nbsp;Verified:' . ($monitoring_summary[7]->total_indicated - $monitoring_summary[7]->total_missing) . '&nbsp; Missing:' . $monitoring_summary[7]->total_missing . ']</td></tr>';
            foreach ($grade_sevens as $beneficiary) {
                $verified = $beneficiary['verified'] == 1 ? 'Yes' : 'No';
                $beneficiaries_table .= '<tr>
                                           <td>' . $beneficiary['first_name'] . ' ' . $beneficiary['last_name'] . '</td>
                                            <td>' . $beneficiary['beneficiary_id'] . '</td>
                                            <td>' . $beneficiary['grade'] . '</td>
                                            <td>' . $verified . '</td>
                                            <td>' . $beneficiary['reason_name'] . '</td>
                                            <td>' . $beneficiary['remark'] . '</td>
                                            </tr>';
            }
        }
        if ($grade_eights_total > 0) {
            $beneficiaries_table .= '<tr><td colspan="6"><span style="font-weight: bold">GRADE 8</span> &nbsp;&nbsp;[Total:' . $monitoring_summary[8]->total_indicated . ' &nbsp;Verified:' . ($monitoring_summary[8]->total_indicated - $monitoring_summary[8]->total_missing) . '&nbsp; Missing:' . $monitoring_summary[8]->total_missing . ']</td></tr>';
            foreach ($grade_eights as $beneficiary) {
                $verified = $beneficiary['verified'] == 1 ? 'Yes' : 'No';
                $beneficiaries_table .= '<tr>
                                            <td>' . $beneficiary['first_name'] . ' ' . $beneficiary['last_name'] . '</td>
                                            <td>' . $beneficiary['beneficiary_id'] . '</td>
                                            <td>' . $beneficiary['grade'] . '</td>
                                             <td>' . $verified . '</td>
                                             <td>' . $beneficiary['reason_name'] . '</td>
                                             <td>' . $beneficiary['remark'] . '</td>
                                            </tr>';
            }
        }
        if ($grade_nines_total > 0) {
            $beneficiaries_table .= '<tr><td colspan="6"><span style="font-weight: bold">GRADE 9</span> &nbsp;&nbsp;[Total:' . $monitoring_summary[9]->total_indicated . ' &nbsp;Verified:' . ($monitoring_summary[9]->total_indicated - $monitoring_summary[9]->total_missing) . '&nbsp; Missing:' . $monitoring_summary[9]->total_missing . ']</td></tr>';
            foreach ($grade_nines as $beneficiary) {
                $verified = $beneficiary['verified'] == 1 ? 'Yes' : 'No';
                $beneficiaries_table .= '<tr>
                                            <td>' . $beneficiary['first_name'] . ' ' . $beneficiary['last_name'] . '</td>
                                            <td>' . $beneficiary['beneficiary_id'] . '</td>
                                            <td>' . $beneficiary['grade'] . '</td>
                                            <td>' . $verified . '</td>
                                            <td>' . $beneficiary['reason_name'] . '</td> 
                                            <td>' . $beneficiary['remark'] . '</td>
                                            </tr>';
            }
        }
        if ($grade_tens_total > 0) {
            $beneficiaries_table .= '<tr><td colspan="6"><span style="font-weight: bold">GRADE 10</span> &nbsp;&nbsp;[Total:' . $monitoring_summary[10]->total_indicated . ' &nbsp;Verified:' . ($monitoring_summary[10]->total_indicated - $monitoring_summary[10]->total_missing) . '&nbsp; Missing:' . $monitoring_summary[10]->total_missing . ']</td></tr>';
            foreach ($grade_tens as $beneficiary) {
                $verified = $beneficiary['verified'] == 1 ? 'Yes' : 'No';
                $beneficiaries_table .= '<tr>
                                           <td>' . $beneficiary['first_name'] . ' ' . $beneficiary['last_name'] . '</td>
                                            <td>' . $beneficiary['beneficiary_id'] . '</td>
                                            <td>' . $beneficiary['grade'] . '</td>
                                            <td>' . $verified . '</td>
                                             <td>' . $beneficiary['reason_name'] . '</td>
                                             <td>' . $beneficiary['remark'] . '</td>
                                            </tr>';
            }
        }
        if ($grade_elevens_total > 0) {
            $beneficiaries_table .= '<tr><td colspan="6"><span style="font-weight: bold">GRADE 11</span> &nbsp;&nbsp;[Total:' . $monitoring_summary[11]->total_indicated . ' &nbsp;Verified:' . ($monitoring_summary[11]->total_indicated - $monitoring_summary[11]->total_missing) . '&nbsp; Missing:' . $monitoring_summary[11]->total_missing . ']</td></tr>';
            foreach ($grade_elevens as $beneficiary) {
                $verified = $beneficiary['verified'] == 1 ? 'Yes' : 'No';
                $beneficiaries_table .= '<tr>
                                          <td>' . $beneficiary['first_name'] . ' ' . $beneficiary['last_name'] . '</td>
                                            <td>' . $beneficiary['beneficiary_id'] . '</td>
                                            <td>' . $beneficiary['grade'] . '</td>
                                             <td>' . $verified . '</td>
                                            <td>' . $beneficiary['reason_name'] . '</td>
                                            <td>' . $beneficiary['remark'] . '</td>
                                            </tr>';
            }
        }
        if ($grade_twelves_tootal > 0) {
            $beneficiaries_table .= '<tr><td colspan="6"><span style="font-weight: bold">GRADE 12</span> &nbsp;&nbsp;[Total:' . $monitoring_summary[12]->total_indicated . ' &nbsp;Verified:' . ($monitoring_summary[12]->total_indicated - $monitoring_summary[12]->total_missing) . '&nbsp; Missing:' . $monitoring_summary[12]->total_missing . ']</td></tr>';
            foreach ($grade_twelves as $beneficiary) {
                $verified = $beneficiary['verified'] == 1 ? 'Yes' : 'No';
                $beneficiaries_table .= '<tr>
                                           <td>' . $beneficiary['first_name'] . ' ' . $beneficiary['last_name'] . '</td>
                                            <td>' . $beneficiary['beneficiary_id'] . '</td>
                                            <td>' . $beneficiary['grade'] . '</td>
                                             <td>' . $verified . '</td>
                                            <td>' . $beneficiary['reason_name'] . '</td>
                                             <td>' . $beneficiary['remark'] . '</td>
                                            </tr>';
            }
        }
        $beneficiaries_table .= '</tbody></table>';
        PDF::writeHTML($beneficiaries_table, true, false, false, false, 'L');
        PDF::ln(5);
        PDF::Cell(100, 5, 'Suggested Reason(s) for Discrepancy (If any): ' . $discrepancy, 0, 0, 'L');
        PDF::ln(8);
        $section_d = '<h4><u>SECTION D: EDUCATION QUALITY</u></h4>';
        PDF::writeHTML($section_d, true, false, false, false, 'L');
        PDF::ln(5);
        PDF::Cell(100, 5, 'Average number of pupils per class: ' . $ave_pupils_class, 0, 0, 'L');
        PDF::Cell(50, 5, 'Average learning hours per day: ' . $ave_learning_hours_day, 0, 1, 'L');
        PDF::ln(5);
        PDF::Cell(100, 5, 'Desk type (1/2/3 seater etc.): ' . $desk_type, 0, 0, 'L');
        PDF::Cell(50, 5, 'Average number of pupils per Desk: ' . $ave_pupils_desk, 0, 1, 'L');
        PDF::ln(5);
        $section_e = '<h4><u>SECTION E: RECOMMENDATION/COMMENT</u></h4>';
        PDF::writeHTML($section_e, true, false, false, false, 'L');
        PDF::ln(5);
        PDF::Cell(0, 5, 'Recommendations/comments about the schools: ' . $school_comments, 0, 1, 'L');
        PDF::ln(5);
        PDF::Cell(0, 5, 'Recommendations/comments from the girls: ' . $girls_comments, 0, 1, 'L');
        //==========///////////////////////////////////////////////////////////////////===========//
        $file_name = 'monitoring_tool_' . $school_name;
        PDF::Output($file_name . '.pdf', 'I');
    }

}
