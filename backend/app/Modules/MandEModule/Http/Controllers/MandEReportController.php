<?php

namespace App\Modules\MandEModule\Http\Controllers;


use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class MandEReportController extends Controller
{
     public function printDataCollectionTool(Request $request)
    {
         $datacollectiontool_id=$request->input('datacollectiontool_id');
         $district_id=$request->input('district_id');
         $school_id =$request->input('school_id');
         $province_id=$request->input('province_id');
         $year=$request->input('year_id');
         $base_url = url('/');
         $is_default=$request->input('is_default');
        try{
                if($datacollectiontool_id==1){
                    if($is_default==1){
                         $params = array(
                            'base_url'=>$base_url. '/resources/images/logo.jpg',
                            );
                          $report = generateJasperReport('SchoolHeadFormv2_default', 'School_Report_Termly_Monitoring_Form_Default' . time(), 'pdf',$params);
                    }else{
                        if (validateisNumeric($district_id)&&empty(validateisNumeric($school_id))){
                            $params = array('district_id' => $district_id,
                                            'base_url'=>$base_url. '/resources/images/kgs-logo.png',
                                            'enrollment_year'=>$year
                                            );
                            $report = generateJasperReport('SchoolHeadFormv2', 'School_Report_Termly_Monitoring_Form_' . time(), 'pdf', $params);

                        }else if(validateisNumeric($school_id)&&validateisNumeric($district_id)){
                            $params = array('school_id' => $school_id,
                                            'district_id' => $district_id,
                                            'base_url'=>$base_url. '/resources/images/kgs-logo.png',
                                            'enrollment_year'=>$year
                                            );
                            $report = generateJasperReport('SchoolHeadFormSchv2', 'School_Report_Termly_Monitoring_Form_Sch' . time(), 'pdf', $params);

                        }
                    }

                }else if($datacollectiontool_id==2){
                    if (validateisNumeric($district_id)){
                       $params = array(
                        'datacollectiontool_id'=>$datacollectiontool_id,
                        'district_id'=>$district_id
                      );
                       $report = generateJasperReport('DEBSSelfform', 'debs_self_form_' . time(), 'pdf', $params);
                    }else{
                       $params = array(
                        'datacollectiontool_id'=>$datacollectiontool_id,
                        'Province_id'=>$province_id
                      );
                       $report = generateJasperReport('DEBSSelfformProvince', 'debs_self_form_province' . time(), 'pdf', $params);
                    }
                }else if($datacollectiontool_id==3){
                      $params = array(
                        'datacollectiontool_id'=>$datacollectiontool_id,
                          'school_id' => $school_id
                    );
                    $report = generateJasperReport('BenGirlsSelf_adminForm', 'beneficiary_self_form_' . time(), 'pdf', $params);
                }else if($datacollectiontool_id==4){
                      $params = array(
                        'datacollectiontool_id'=>$datacollectiontool_id
                    );
                    $report = generateJasperReport('parentFocusGroup', 'parentFocusGroup_form_' . time(), 'pdf', $params);
                }else if($datacollectiontool_id==5){
                      $params = array(
                        'datacollectiontool_id'=>$datacollectiontool_id
                    );
                    $report = generateJasperReport('benGirlsgroup', 'benGirlsgroup_form_' . time(), 'pdf', $params);
                }else if($datacollectiontool_id==6){
                       if (validateisNumeric($district_id)){
                        $params = array(
                            'district_id' => $district_id
                          );
                         $report = generateJasperReport('M_E/Monitoring_tool_district', 'Monitoring_tool_district_' . time(), 'pdf', $params);
                    }
                    // else{

                    //     $params = array(
                    //         'province_id' => $province_id
                    //     );
                    //      $report = generateJasperReport('M_E/Monitoring_tool_province', 'Monitoring_tool_province_' . time(), 'pdf', $params);

                    // }
                }else if($datacollectiontool_id==7){
                   $report = generateJasperReport('M_E/Audit_Tool_Landscape','Audit_Tool_Landscape_' . time(), 'pdf');
                }else if($datacollectiontool_id==11){
                    $params = array(
                        'datacollectiontool_id'=>$datacollectiontool_id
                    );
                    $report = generateJasperReport('M_E/Training_Reg_Landscape', 'Training_Reg_' . time(), 'pdf', $params);
                }else if($datacollectiontool_id==16){
                    $params = array(
                        'datacollectiontool_id'=>$datacollectiontool_id
                    );
                    $report = generateJasperReport('M_E/weeklyboardingMonitoringtool', 'Training_Reg_' . time(), 'pdf', $params);
                 }

        }catch (\Exception $exception) {
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
        
        return $report;
    }
}
