<?php

namespace App\Modules\CaseManagement\Http\Controllers;


use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CaseReportController extends Controller
{
    public function printAllcaseform(Request $request)
    {
        try{
          $form_id=$request->input('form_id');
          $case_id=$request->input('case_id');
          $case_girl_id=$request->input('case_girl_id');
          $year=$request->input('year');
          $is_mainReport=$request->input('is_mainReport');
          $report='';
          $base_url = url('/');
          $params = array('base_url'=>$base_url. '/resources/images/');
          if(isset($form_id) && $form_id==1){
               $report = generateJasperReport('casemanagement/KGS_CMS_Form_1A__Case_Intake', 'KGS_CMS_Form_1A__Case_Intake' . time(), 'pdf',$params);
          }else if(isset($form_id) && $form_id==2){
              $report = generateJasperReport('casemanagement/KGS_CMS_Form_2A__Assessment_Form', 'assessment_form_' . time(), 'pdf',$params);
          }else if(isset($form_id) && $form_id==3){
              $report = generateJasperReport('casemanagement/KGS_CMS_Form_3_Referral_Tool', 'referral_form_' . time(), 'pdf',$params);
          }else if(isset($form_id) && $form_id==4){
              $report = generateJasperReport('casemanagement/KGS_CMS_Form_4__Case_Review_Form', 'case_review_form_' . time(), 'pdf',$params);
          }else if(isset($form_id) && $form_id==5){
              $report = generateJasperReport('casemanagement/KGS_CMS_Form_5_Case_Log_Sheet', 'case_Log_sheet_' . time(), 'pdf',$params);
          }else if(isset($form_id) && $form_id==6){
              $report = generateJasperReport('casemanagement/KGS_CMS_Form_6_Case_Closure', 'case_closure_form_' . time(), 'pdf',$params);
          }else if(isset($form_id) && $form_id==7){
              $report = generateJasperReport('casemanagement/KGS_CMS_Form_1B_EWS_Tool', 'ews_form_' . time(), 'pdf',$params);
          }else if(isset($form_id) && $form_id==8){
              $report = generateJasperReport('casemanagement/KGS_CMS_Form_2B_Care_Plan', 'careplan_form_' . time(), 'pdf',$params);
          }
          // else if(isset($case_id) && $is_mainReport!=1){
          //   echo "wrong";
          //    $params = array(
          //     'case_id'=>$case_id,
          //   );
          //  $report = generateJasperReport('casemanagement/preloaded_referral_form', 'preloaded_referral_form_' . time(), 'pdf', $params);
          // }else{
          //   //Print combined report
          //   $params = array(
          //     'case_id'=>$case_id,
          //     'case_girl_id'=>$case_girl_id,
          //     'year'=>$year
          //   );
          //   $report = generateJasperReport('casemanagement/MainCaseManagementForms', 'preloaded_referral_form_' . time(), 'pdf', $params);
          // }
        }catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
            print_r($res);
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
            print_r($res);
        }
        return $report;
    }
}
