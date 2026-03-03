<?php

namespace App\Modules\CaseManagement\Http\Controllers;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use App\Jobs\GenericSendEmailJob;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use PDF;

class CaseManagementController extends BaseController
{
    //Start Maureen
    public function getAllServicesParam(Request $request)
    {
        try {
            $qry = DB::table('case_services as t1')
                ->select('t1.*', 't2.category_name', 't3.level')
                ->leftjoin('case_implementation_category as t2', 't1.category_id', '=', 't2.id')
                ->leftjoin('case_implementation_levels as t3', 't1.level_id', '=', 't3.id');
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
        return \response()->json($res);
    }

    public function getCasesPerLocation(Request $request)
    {
        try {
            $qry = DB::table('case_basicdataentry_details as t1')
                ->select(DB::raw(" t1.*,t2.NAME AS province_name,t3.NAME AS district_name,
                                sum(if(t1.target_group_id=1,t1.target_group_id,0)) AS targetgroup_one,
                                sum(if(t1.target_group_id=2,t1.target_group_id,0)) AS targetgroup_two,
                                sum(if(t1.target_group_id=3,t1.target_group_id,0)) AS targetgroup_three"))
                ->join('provinces as t2', 't1.province_id', '=', 't2.id')
                ->join('districts as t3', 't1.district_id', '=', 't3.id')
                ->groupBy('t1.district_id');
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
        return \response()->json($res);
    }

    public function getCasesperLocationgraph()
    {
        try {
            $qry = DB::table('case_target_groups as t1')
                ->select(DB::raw("t3.NAME AS province_name,SUM(if(t2.target_group_id=1,t2.target_group_id,0)) as target_group_one,SUM(if(t2.target_group_id=2,t2.target_group_id,0)) as target_group_two,SUM(if(t2.target_group_id=3,t2.target_group_id,0)) as target_group_three"))
                ->join('case_basicdataentry_details as t2', 't1.id', '=', 't2.target_group_id')
                ->join('provinces as t3', 't2.province_id', '=', 't3.id')
                ->groupBy('t2.province_id');
            $results = $qry->get();
            $res = array('success' => true, 'results' => $results);
        } catch (\Exception $e) {
            $res = array('success' => false, 'message' => $e->getMessage());
        } catch (\Throwable $throwable) {
            $res = array('success' => false, 'message' => $throwable->getMessage());
        }
        return response()->json($res);
    }

    public function getCasesperTargetgraph()
    {
        try {
            $qry = DB::table('case_target_groups as t1')
                ->select(DB::raw("YEAR (t2.created_at) AS year,SUM(if(t2.target_group_id=1,t2.target_group_id,0)) as target_group_one,SUM(if(t2.target_group_id=2,t2.target_group_id,0)) as target_group_two,SUM(if(t2.target_group_id=3,t2.target_group_id,0)) as target_group_three"))
                ->join('case_basicdataentry_details as t2', 't1.id', '=', 't2.target_group_id')
                ->groupBy('year');
            $results = $qry->get();
            $res = array('success' => true, 'results' => $results);
        } catch (\Exception $e) {
            $res = array('success' => false, 'message' => $e->getMessage());
        } catch (\Throwable $throwable) {
            $res = array('success' => false, 'message' => $throwable->getMessage());
        }
        return response()->json($res);
    }

    public function getCustomReportCases(Request $request)
    {
        try {
            $target_group_id = $request->input('target_group_id');
            $year = $request->input('created_at');
            $qry = DB::table('case_basicdataentry_details as t1')
                ->leftjoin('case_girl_details as t2', 't1.case_girl_id', '=', 't2.id')
                ->select(DB::raw("t1.*,CONCAT_WS(' ',t2.first_name,t2.last_name) as girl_name"))
                ->where('t1.created_at', 'LIKE', '%' . $year . '%')
                ->where('t1.target_group_id', $target_group_id);
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
        return \response()->json($res);
    }

    public function getCasesPerTarget(Request $request)
    {
        try {
            $qry = DB::table('case_target_groups as t1')
                ->select(DB::raw("t1.name as target,t1.id as target_id,t1.description,YEAR (t2.created_at) AS year,COUNT(t2.target_group_id) as total_no_target"))
                ->join('case_basicdataentry_details as t2', 't2.target_group_id', '=', 't1.id')
                ->groupBy('year', 't2.target_group_id');
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
        return \response()->json($res);
    }

    public function saveCaseAssessmentDetailInitial(Request $request)
    {
        try {
            $table_name = $request->input('table_name');
            $case_id = $request->input('case_id');
            $user_id = $this->user_id;
            $formdata = $request->all();
            unset($formdata['table_name']);
            unset($formdata['referral_id']);
            if ($table_name == 'case_girl_details') {
                $keys = array_keys($formdata);
                $last = end($keys);
                unset($formdata[$last]);
                unset($formdata['case_id']);
                unset($formdata['case_girl_id']);
                unset($formdata['id']);
                $id = $request->input('case_girl_id');
            } else if ($table_name == 'case_implemetation_details') {
                $keys = array_keys($formdata);
                $last = end($keys);
                unset($formdata[$last]);
                unset($formdata['case_girl_id']);
                $id = $request->input('id');
            } else if ($table_name == 'case_assessment_info') {
                $where_data = array('case_id' => $case_id);
                $prev_record = getPreviousRecords($table_name, $where_data);
                if (!empty($prev_record)) {
                    $id = $prev_record[0]['id'];
                    $formdata['id'] = $id;
                } else {
                    $id = '';
                }
            } else if ($table_name == 'case_sibling_significantdetails') {
                if ($formdata['relationcat'] == 1) {
                    $where_data = array('case_id' => $case_id, 'relationship' => $formdata['relationship'], 'relationcat' => $formdata['relationcat']);
                    $prev_record = getPreviousRecords($table_name, $where_data);
                    if (!empty($prev_record)) {
                        $id = $prev_record[0]['id'];
                        $formdata['id'] = $id;
                    } else {
                        $id = '';
                    }
                } else {
                    $id = $request->input('id');
                }
            } else if ($table_name == 'case_referrals') {
                unset($formdata['maleSurname']);unset($formdata['maleFirstname']);
                unset($formdata['maleAddress']);unset($formdata['maleTelephone']);
                unset($formdata['femaleSurname']);unset($formdata['femaleFirstname']);
                unset($formdata['femaleAddress']);unset($formdata['femaleTelephone']);
                unset($formdata['last_name']);unset($formdata['first_name']);
                unset($formdata['id_no']);unset($formdata['contact']);
                unset($formdata['Address']);
                $id = $request->input('referral_id');//kip here
            } else {
                $id = $request->input('id');
            }
            unset($formdata['id']);
            if ($id != '' && isset($id)) {
                //Edit
                $table_data = $formdata;
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $where = array(
                    'id' => $id
                );
                //get previous_data
                $previous_data = getPreviousRecords($table_name, $where);
                $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                $res['record_id'] = $id;
            } else {
                //new insert
                $table_data = $formdata;
                $table_data['created_at'] = Carbon::now();
                $table_data['created_by'] = $user_id;
                $res = insertRecord($table_name, $table_data, $user_id);
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

    public function saveCaseAssessmentDetail(Request $request)
    {
        try {
            $table_name = $request->input('table_name');
            $case_id = $request->input('case_id');
            $id = $request->input('id');
            $user_id = $this->user_id;
            $formdata = $request->all();
            unset($formdata['table_name']);
            unset($formdata['id']);
            if ($id != '' && isset($id)) {
                //Edit
                $table_data = $formdata;
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $where = array(
                    'id' => $id
                );
                //get previous_data
                $previous_data = getPreviousRecords($table_name, $where);
                if($previous_data){
                    $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                    $res['record_id'] = $id;
                } else {
                    //new insert
                    $table_data = $formdata;
                    $table_data['created_at'] = Carbon::now();
                    $table_data['created_by'] = $user_id;
                    $res = insertRecord($table_name, $table_data, $user_id);}
            } else {
                //new insert
                $table_data = $formdata;
                $table_data['created_at'] = Carbon::now();
                $table_data['created_by'] = $user_id;
                $res = insertRecord($table_name, $table_data, $user_id);
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

    public function saveInitialCarePlanDetails(Request $request)
    {
        try {
            $table_name = $request->input('table_name');
            $formdata = $request->input('careplandetails');
            $case_monitoring_id = $request->input('case_monitoring_id');
            $data = json_decode($formdata, true);
            foreach ($data as $key => $value) {
                $table_data = array(
                    'case_id' => $value['case_id'],
                    'checklist_id' => $value['checklist_id'],
                    'timeframe' => $value['timeframe'],
                    'responsible_person' => $value['responsible_person']
                );
                //where data
                $where_data = array(
                    'id' => $value['id']
                );
                if (recordExists($table_name, $where_data)) {
                    $table_data['updated_by'] = $this->user_id;
                    DB::table($table_name)
                        ->where($where_data)
                        ->update($table_data);
                } else {
                    $table_data['created_by'] = $this->user_id;
                    DB::table($table_name)
                        ->insert($table_data);
                }
                ///////Revised care plan...monitoring log details
                if (validateisNumeric($case_monitoring_id)) {
                    $rev_table_name = 'case_revisedcareplan_details';
                    $rev_data = array(
                        'case_id' => $value['case_id'],
                        'case_monitoring_id' => $case_monitoring_id,
                        'checklist_id' => $value['checklist_id'],
                        'timeframe' => $value['timeframe'],
                        'responsible_person' => $value['responsible_person']
                    );
                    //where data
                    $rev_where_data = array(
                        'case_monitoring_id' => $case_monitoring_id,
                        'checklist_id' => $value['checklist_id']
                    );
                    if (recordExists($rev_table_name, $rev_where_data)) {
                        $table_data['updated_by'] = $this->user_id;
                        DB::table($rev_table_name)
                            ->where($rev_where_data)
                            ->update($rev_data);
                    } else {
                        $rev_data['created_by'] = $this->user_id;
                        DB::table($rev_table_name)
                            ->insert($rev_data);
                    }
                }
                /////
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

    public function saveCarePlanDetails(Request $request)
    {
        try {
            $table_name = 'case_careplan_details';// $request->input('table_name');
            $selected = $request->input('selected');
            $selected_ids = json_decode($selected);
            $case_id = $request->input('case_id');
            $monitoring_id = $request->input('case_monitoring_id');
            if (!validateisNumeric($monitoring_id)) {
                $monitoring_id = $this->createDefaultCounterZeroCaseMonitoring($case_id);
            }
            $table_data = array();
            foreach ($selected_ids as $key => $selected_id) {
                $table_data [] = array(
                    'case_id' => $case_id,
                    'case_monitoring_id' => $monitoring_id,
                    'checklist_id' => $selected_id,
                    'created_by' => $this->user_id
                );
            }
            DB::table($table_name)
                ->insert($table_data);
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

    public function createDefaultCounterZeroCaseMonitoring($case_id)
    {
        $exists = getSingleRecord('case_monitoring_information', array('case_id' => $case_id, 'counter' => 0));
        if (is_null($exists)) {
            $params = array(
                'case_id' => $case_id,
                'review_date' => Carbon::now(),
                'review_type' => 'Initial Care Plan',
                'description' => 'Initial Care Plan Details captured',
                'counter' => 0
            );
            $res = insertRecord('case_monitoring_information', $params, $this->user_id);
            if ($res['success'] == false) {
                return \response()->json($res);
            }
            $monitoring_id = $res['record_id'];
        } else {
            $monitoring_id = $exists->id;
        }
        return $monitoring_id;
    }

    public function deleteCaseCarePlanDetails(Request $request)
    {
        try {
            $care_plan_id = $request->input('care_plan_id');
            $revised_care_plan_id = $request->input('revised_care_plan_id');
            //check if service has been provided
            $check = array(
                'careplan_detail_id' => $care_plan_id,
                'action_provided' => 1
            );
            if (recordExists('case_monitoring_careplan_details', $check)) {
                $res = array(
                    'success' => false,
                    'message' => 'Action not allowed, this service has been provided!!'
                );
                return \response()->json($res);
            }
            DB::table('case_careplan_details')
                ->where('id', $care_plan_id)
                ->delete();
            DB::table('case_revisedcareplan_details')
                ->where('id', $revised_care_plan_id)
                ->delete();
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

    public function getFamilyRefferalDetails(Request $request)
    {
        try {
            $case_id = $request->input('case_id');
            $case_girl_id = $request->input('case_girl_id');
            $main_qry = DB::table('case_basicdataentry_details as t1')
                ->leftJoin('case_sibling_significantdetails as t2', function ($join) use ($case_girl_id) {
                    $join->on('t1.case_girl_id', '=', 't2.case_girl_id')
                        ->where(array('t2.case_girl_id' => $case_girl_id, 't2.relationship' => 1));
                })
                ->leftJoin('case_sibling_significantdetails as t3', function ($join) use ($case_girl_id) {
                    $join->on('t1.case_girl_id', '=', 't3.case_girl_id')
                        ->where(array('t3.case_girl_id' => $case_girl_id, 't3.relationship' => 2));
                })
                ->join('case_girl_details as t4', 't1.case_girl_id', '=', 't4.id')
                ->select('t4.*', 't1.*', 't2.first_name as maleFirstname', 't2.last_name as maleSurname', 't2.address as maleAddress', 't2.telephone as maleTelephone', 't3.first_name as female', 't3.last_name as femaleSurname', 't3.address as femaleAddress', 't3.telephone as femaleTelephone', 't1.id')
                ->where('t1.id', $case_id);
            $main_results = $main_qry->first();
            $res = array(
                'success' => true,
                'results' => $main_results
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
        return \response()->json($res);
    }
    
    public function prepareCaseAssessmentDetails(Request $request)
    {
        try {
            $from_main_tbl = 0;
            $record_id = $request->input('record_id');
            $form_number = $request->input('form_number');
            $agreement_module = $request->input('agreement_module');
            if($form_number == 1) {
                $table_name = 'case_assessmentbasic_info as t1';
            } else if($form_number == 2) {
                $table_name = 'case_careplanbasic_info as t1';
            } else if($form_number == 3) {
                $table_name = 'case_reviewbasic_info as t1';
            } else if($form_number == 4) {
                $table_name = 'case_refferalbasic_info as t1';
            } else if($form_number == 5) {
                $table_name = 'case_logsheetbasic_info as t1';
            } else if($form_number == 6) {
                $table_name = 'case_closurebasic_info as t1';
            } else if($form_number == 7) {//form 8 already in use
                $table_name = 'case_careplan_notes as t1';
            } else if($form_number == 9) {
                $table_name = 'case_agreement_info as t1';
            } else if($form_number == 10) {
                $table_name = 'case_reviewagreement_info as t1';
            } else if($form_number == 11) {
                $table_name = 'case_refferalfeedback_info as t1';
            } else if($form_number == 12) {
                $table_name = 'case_earlywarningsigns_info as t1';
            } else if($form_number == 13) {
                $table_name = 'case_closureagreement_info as t1';
            } else {
                $table_name = 'case_assessmentbasic_info as t1';
            }
            $where_data = array(
                'case_id' => $record_id
            );
            $where_agreement_data = array(
                'case_id' => $record_id,
                'agreement_module' => $agreement_module
            );
            if (recordExists($table_name, $where_data)) {
                $qry = DB::table($table_name)
                    ->select('t1.*')
                    ->where('t1.case_id', $record_id);
            } else if ($form_number == 9 || $form_number == 10 || $form_number == 13) {
                $qry = DB::table($table_name)
                    ->select('t1.*')
                    ->where($where_agreement_data);
            } else {
                if($table_name == 'case_assessmentbasic_info as t1'){
                    $qry = DB::table($table_name)
                        ->select('t1.*')
                        ->where('t1.id', $record_id);
                } else {
                    $qry = DB::table('case_basicdataentry_details as t1')
                        ->select('t1.*')
                        ->where('t1.id', $record_id);
                    $from_main_tbl = 1;
                }
            }
            $results = $qry->first();
            if(!$results){
                $qry = DB::table('case_basicdataentry_details as t1')
                    ->select('t1.*')
                    ->where('t1.id', $record_id);
                $from_main_tbl = 1;
                $results = $qry->first();
            }
            $res = array(
                'success' => true,
                'results' => $results,
                'from_main_tbl' => 0
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
        return \response()->json($res);
    }
    
    public function prepareMainBasicCaseAssessmentFrmDetails(Request $request)
    {
        try {
            $from_main_tbl = 0;
            $record_id = $request->input('record_id');
            $form_number = $request->input('form_number');
            $agreement_module = $request->input('agreement_module');
            $qry = DB::table('case_assessmentbasic_info as t1')
                ->select('t1.*')
                ->where('t1.case_id', $record_id);
            $results = $qry->first();
            if(!$results) {
                $qry = DB::table('case_basicdataentry_details as t1')
                    ->select('t1.*')
                    ->where('t1.id', $record_id);
                $from_main_tbl = 1;
                $results = $qry->first();
            }
            $res = array(
                'success' => true,
                'results' => $results,
                'from_main_tbl' => 0
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
        return \response()->json($res);
    }

    public function prepareCaseAssessParentDetails(Request $request)
    {
        try {
            $record_id = $request->input('record_id');
            $qry = DB::table('case_assessment_parent_info as t1')
                ->select('t1.*')
                ->where('t1.case_id', $record_id);
            $results = $qry->first();
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
        return \response()->json($res);

    }

    public function prepareCaseAssessmentHealthDetails(Request $request)
    {
        try {
            $record_id = $request->input('record_id');
            $qry = DB::table('case_basicdataentry_details as t1')
                ->leftjoin('case_general_health_details as t2', 't1.id', '=', 't2.case_id')
                // ->join('case_girl_details as t3', 't1.case_girl_id', '=', 't3.id')
                ->select('t1.*', 't1.id as case_id', 't2.*')//, 't3.*', 't3.beneficiary_id as id_no')
                ->where('t1.id', $record_id);
            $results = $qry->first();
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
        return \response()->json($res);

    }

    public function getcaseagreement(Request $request)
    {
        try {
            $case_id = $request->input('case_id');
            $qry = DB::table('case_agreement_info')
                ->where('case_id', $case_id)
                ->select('*');
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

    public function getReviewNotes(Request $request)
    {
        try {
            $note_module = $request->input('note_module');
            $case_id = $request->input('record_id');
            if($note_module == 1) {
                $table_name = 'case_closure_notes';
            } else if($note_module == 2) {
                $table_name = 'case_review_notes';
            } else {
                $table_name = 'case_review_notes';
            }
            $qry = DB::table($table_name)
                ->where('case_id', $case_id)
                ->select('*')->get();
            if($qry->count() > 0){
                $results = $qry->first();
            } else {
                $results = [];
            }
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

    public function getcaseParticipant(Request $request)
    {
        $case_id = $request->input('case_id');
        $case_girl_id = $request->input('case_girl_id');
        try {
            $qry = DB::table('case_assessment_participants')
                ->where('case_id', $case_id)
                ->select('*');
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

    public function getcaseImplementation(Request $request)
    {
        try {
            $case_id = $request->input('case_id');
            $careplan_id = $request->input('careplan_id');
            $where_data = array(
                'case_id' => $case_id,
                'careplan_id' => $careplan_id
            );
            $qry = DB::table('case_implemetation_details as t1')
                ->leftjoin('case_implementation_levels as t2', 't1.level_id', '=', 't2.id')
                ->join('case_services as t3', 't1.service_id', '=', 't3.id')
                ->join('case_implementation_category as t4', 't1.category_id', '=', 't4.id')
                ->join('case_services_statuses as t5', 't1.service_status_id', '=', 't5.id')
                ->where($where_data)
                ->select('t1.*', 't2.level AS level', 't3.service_name', 't4.category_name as category', 't5.name as service_status');
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

    public function getCaseReferralDetails(Request $request)
    {
        try {
            $case_id = $request->input('case_id');
            $case_implementation_id = $request->input('case_implementation_id');
            $where_data = array(
                'case_id' => $case_id,
                'case_implementation_id' => $case_implementation_id
            );
            $qry = DB::table('case_referrals as t1')
                ->join('case_services_statuses as t2', 't1.referral_status_id', '=', 't2.id')
                ->where($where_data)
                ->select('t1.*', 't1.id as referral_id', 't2.name as referral_status');
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

    public function getcareplan(Request $request)
    {
        try {
            $case_id = $request->input('case_id');
            $qry = DB::table('case_careplan as t1')
                ->leftJoin('case_careplan_details as t2', function ($join) use ($case_id) {
                    $join->on('t1.id', '=', 't2.checklist_id')
                        ->where('t2.case_id', $case_id);
                })
                ->where('category_id', 1)
                ->whereNull('t2.id')
                ->select(DB::raw("t1.id,t1.action,if(t2.checklist_id>=1,true,false) as checklist_id,t2.id as rec_id"));
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

    public function getcareplandetailsInitial(Request $request)
    {
        try {
            $case_id = $request->input('case_id');
            $qry = DB::table('case_careplan_details as t1')
                ->join('case_careplan as t2', 't1.checklist_id', '=', 't2.id')
                ->join('case_monitoring_information as t3', 't1.case_monitoring_id', '=', 't3.id')
                ->where('t1.case_id', $case_id)
                //->where('t3.counter', 0)
                ->groupBy('t2.id')
                ->select('t3.*', 't1.*', 't3.id as monitoring_id', 't2.action as action', 't1.id as care_plan_id', 't1.created_at as date_added');
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

    public function getcareplandetails(Request $request)
    {
        try {
            $case_id = $request->input('case_id');
            $qry = DB::table('case_careplan_details as t1')
                ->join('case_careplan as t2', 't1.checklist_id', '=', 't2.id')
                ->join('case_monitoring_information as t3', 't1.case_monitoring_id', '=', 't3.id')
                ->where('t1.case_id', $case_id)
                //->where('t3.counter', 0)
                ->groupBy('t2.id')
                ->select('t3.*', 't1.*', 't3.id as monitoring_id', 't2.action as action', 't1.id as care_plan_id', 't1.created_at as date_added');
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

    public function getCaseMonitoringDetails(Request $request)
    {
        try {
            $case_id = $request->input('case_id');
            $qry = DB::table('case_monitoring_information as t1')
                ->select('t1.*', 't1.id as monitoring_id')
                ->where('case_id', $case_id);
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

    public function getRevisedCarePlanDetails(Request $request)
    {
        try {
            $monitoring_id = $request->input('monitoring_id');
            $qry = DB::table('case_careplan_details as t1')
                ->join('case_careplan as t2', 't1.checklist_id', '=', 't2.id')
                ->join('case_monitoring_information as t3', 't1.case_monitoring_id', '=', 't3.id')
                ->where('t1.case_monitoring_id', $monitoring_id)
                ->groupBy('t2.id')
                ->select('t1.*', 't2.action as action', 't1.id as care_plan_id');
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

    public function getsiblingSignificant(Request $request)
    {
        # code...
        $case_id = $request->input('case_id');
        $case_girl_id = $request->input('case_girl_id');
        try {
            $qry = DB::table('case_sibling_significantdetails as t1')
                ->join('relationshipcategory as t2', 't1.relationcat', '=', 't2.id')
                ->join('case_relationship_details as t3', 't1.relationship', '=', 't3.id')
                ->where('case_id', $case_id)
                ->select('t1.*', 't2.name as category', 't3.name as relationship_name');
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

    public function getAllCaseforms()
    {
        try {
            $qry = DB::table('case_formstools')
                ->select('*');
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

    public function getAllCaseKPIs()
    {
        try {
            $qry = DB::table('case_kpis')
                ->select('*');
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

    public function editCaseforms(Request $request)
    {
        try {
            $formdata = $request->all();
            $id = $request->input('id');
            $user_id = $this->user_id;
            $table_name = 'case_formstools';
            if ($id != '' && isset($id)) {
                //Edit
                $table_data = $formdata;
                $table_data['updated_on'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $where = array(
                    'id' => $id
                );
                //get previous_data
                $previous_data = getPreviousRecords($table_name, $where);
                $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);

            } else {
                //new insert
                $table_data = $formdata;
                $table_data['created_on'] = Carbon::now();
                $table_data['created_by'] = $user_id;
                $res = insertRecord($table_name, $table_data, $user_id);
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

    public function getCaseChildren(Request $request)
    {
        //get assement_id
        $case_id = $request->input('case_id');
        $case_girl_id = $request->input('case_girl_id');

        try {
            $qry = DB::table('case_childreninfo as t1')
                ->leftJoin('gender as t2', 't1.gender', '=', 't2.id')
                ->leftJoin('case_relationship_details as t3', 't1.responsible_guardian', '=', 't3.id')
                ->select('t1.*', 't2.name as gender_name', 't3.name as responsible_person_name')
                ->where('case_id', $case_id);
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

    //End Maureen

    //start Frank
    
    public function getCaseConferenceDetails(Request $request)
    {
        $case_id = $request->input('case_id');
        $case_girl_id = $request->input('case_girl_id');
        try {
            $qry = DB::table('case_conference_info as t1')
                ->select('t1.*')->where('case_id', $case_id);
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
    
    public function getCaseServiceResourceDetails(Request $request)
    {
        $case_id = $request->input('case_id');
        $case_girl_id = $request->input('case_girl_id');
        try {
            $qry = DB::table('case_services_required as t1')
                ->select('t1.*')->where('case_id', $case_id);
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

    public function saveSocioEmotionalInfo(Request $request)
    {
        try {
            $selected_behaviours = $request->input('selected_behaviours');
            $case_id = $request->input('case_id');
            $selectedArray = json_decode($selected_behaviours, true);
            $table_name = 'case_social_emotional_details';
            foreach ($selectedArray as $key => $value) {
                $table_data = array(
                    'case_id' => $case_id,
                    'she_does' => isset($value['she_does']) ? $value['she_does'] : '',
                    'behaviour_id' => isset($value['behaviour_id']) ? $value['behaviour_id'] : '',
                    'case_girl_id' => isset($value['case_girl_id']) ? $value['case_girl_id'] : '',
                    'she_does_not' => isset($value['she_does_not']) ? $value['she_does_not'] : '',
                    'observations' => isset($value['observations']) ? $value['observations'] : ''
                );  
                //where data
                $where_data = array(
                    'id' => $value['record_id']
                );
                if (recordExists($table_name, $where_data)) {
                    $table_data['updated_by'] = $this->user_id;
                    DB::table($table_name)
                        ->where($where_data)
                        ->update($table_data);
                } else {
                    $table_data['created_by'] = $this->user_id;
                    DB::table($table_name)->insert($table_data);
                }
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

    public function saveCarePlanGridDetails(Request $request)
    {
        try {
            $edited_records = $request->input('edited_records');
            $case_id = $request->input('case_id');
            $selectedArray = json_decode($edited_records, true);
            $table_name = 'case_careplan_details';
            foreach ($selectedArray as $key => $value) {
                $table_data = array(
                    'case_id' => $case_id,
                    'timeframe' => isset($value['timeframe']) ? $value['timeframe'] : '',
                    'case_girl_id' => isset($value['case_girl_id']) ? $value['case_girl_id'] : '',
                    'responsible_person' => isset($value['responsible_person']) ? $value['responsible_person'] : '',
                    'first_review_date' => isset($value['first_review_date']) ? $value['first_review_date'] : '',
                    'second_review_date' => isset($value['second_review_date']) ? $value['second_review_date'] : '',
                    'third_review_date' => isset($value['third_review_date']) ? $value['third_review_date'] : '',
                    'final_coments' => isset($value['final_coments']) ? $value['final_coments'] : ''
                );  
                //where data
                $where_data = array(
                    'id' => $value['record_id']
                );
                if (recordExists($table_name, $where_data)) {
                    $table_data['updated_by'] = $this->user_id;
                    DB::table($table_name)
                        ->where($where_data)
                        ->update($table_data);
                } else {
                    $table_data['created_by'] = $this->user_id;
                    DB::table($table_name)->insert($table_data);
                }
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
    
    public function getSocioEmotionalInfo(Request $request)
    {
        $case_id = $request->input('case_id');
        try {
            $qry = DB::table('case_social_emotional_info as t1')
                ->leftJoin('case_social_emotional_details as t2', function ($join) use ($case_id) {
                    $join->on('t1.id', '=', 't2.behaviour_id')
                        ->where(array('t2.case_id' => $case_id));
                })
                ->select('t1.id as socio_emotional_id',DB::Raw('t1.name as behaviour'),'t2.*');
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
        
    public function saveAccessToResourcesInfo(Request $request)
    {
        try {
            $table_name = $request->input('table_name');
            $case_id = $request->input('case_id');
            $user_id = $this->user_id;
            $table_data = $request->all();
            $id = $request->input('id');
            unset($table_data['id']);
            unset($table_data['_token']);
            unset($table_data['table_name']);
            if (isset($id) && $id != '') {
                //Edit
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $where = array(
                    'id' => $id
                );
                //get previous_data
                $previous_data = getPreviousRecords($table_name, $where);
                $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                $res['record_id'] = $id;
            } else {
                //new insert
                $table_data['created_at'] = Carbon::now();
                $table_data['created_by'] = $user_id;
                $res = insertRecord($table_name, $table_data, $user_id);
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

    public function getAccessToResourcesInfo(Request $request)
    {
        $case_id = $request->input('case_id');
        try {
            $qry = DB::table('case_access_to_resources as t1')->select('t1.*')
                ->where('t1.case_id', $case_id);
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
    
    public function saveFamilySurvivalInfo(Request $request)
    {        
        $term_id = $request->input('single_term_id');
        $year_id = $request->input('single_year_id');
        $record_id = $request->input('single_record_id');
        $postdata = file_get_contents("php://input");
        $data = json_decode($postdata);
        // the post data is array thus handle as array data
        if (is_array($data)) {
            $data = json_decode($postdata, true);
        } else {
            $data = array();
            $data[] = json_decode($postdata, true);
        }
        $table_name = 'mne_dqa_enrolment_info';
        try {
            foreach ($data as $key => $value) {
                $table_data = array(
                    'grade_id' => $value['gradeId'],
                    'term_id' => $term_id,
                    'year_id' => $year_id,
                    'record_id' => $record_id,
                    'kgs_reported' => $value['kgs_reported'],
                    'non_kgs_reported' => $value['non_kgs_reported'],
                    'kgs_recounted' => $value['kgs_recounted'],
                    'non_kgs_recounted' => $value['non_kgs_recounted'],
                    'kgs_discrepancy' => $value['kgs_discrepancy'],
                    'non_kgs_discrepancy' => $value['non_kgs_discrepancy']
                );
                //where data
                $where_data = array(
                    'id' => $value['id']
                );
                if (recordExists($table_name, $where_data)) {
                    $table_data['updated_by'] = $this->user_id;
                    DB::table($table_name)->where($where_data)->update($table_data);
                } else {
                    $table_data['created_by'] = $this->user_id;
                    DB::table($table_name)->insert($table_data);
                }
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

    public function saveFamilySurvivalDetails(Request $request)
    {
        try {
            $selected_needs = $request->input('selected_needs');
            $case_id = $request->input('case_id');
            $selectedArray = json_decode($selected_needs, true);
            $table_name = 'case_family_survival_details';
            foreach ($selectedArray as $key => $value) {
                $table_data = array(
                    'case_id' => $case_id,
                    'survival_needs_id' => isset($value['survival_needs_id']) ? $value['survival_needs_id'] : '',
                    'case_girl_id' => isset($value['case_girl_id']) ? $value['case_girl_id'] : '',
                    'well_met' => isset($value['well_met']) ? $value['well_met'] : '',
                    'adequately_met' => isset($value['adequately_met']) ? $value['adequately_met'] : '',
                    'somewhat_met' => isset($value['somewhat_met']) ? $value['somewhat_met'] : '',
                    'not_met' => isset($value['not_met']) ? $value['not_met'] : '',
                    'areas_of_concern' => isset($value['areas_of_concern']) ? $value['areas_of_concern'] : ''
                );  
                //where data
                $where_data = array(
                    'id' => $value['record_id']
                );
                if (recordExists($table_name, $where_data)) {
                    $table_data['updated_by'] = $this->user_id;
                    DB::table($table_name)
                        ->where($where_data)
                        ->update($table_data);
                } else {
                    $table_data['created_by'] = $this->user_id;
                    DB::table($table_name)->insert($table_data);
                }
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
    
    public function getFamilySurvivalInfo(Request $request)
    {
        $case_id = $request->input('case_id');
        try {
            $qry = DB::table('case_family_survival_info as t1')
                ->leftJoin('case_family_survival_details as t2', function ($join) use ($case_id) {
                    $join->on('t1.id', '=', 't2.survival_needs_id')
                        ->where(array('t2.case_id' => $case_id));
                })
                ->select('t1.id as needs_id',DB::Raw('t1.name as type_of_need'),'t2.*');
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
 
    public function saveEducationalBckGrndInfo(Request $request)
    {
        try {
            $table_name = $request->input('table_name');
            $case_id = $request->input('case_id');
            $user_id = $this->user_id;
            $table_data = $request->all();
            $id = $request->input('id');
            unset($table_data['id']);
            unset($table_data['_token']);
            unset($table_data['table_name']);
            if (isset($id) && $id != '') {
                //Edit
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $where = array(
                    'id' => $id
                );
                //get previous_data
                $previous_data = getPreviousRecords($table_name, $where);
                $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                $res['record_id'] = $id;
            } else {
                //new insert
                $table_data['created_at'] = Carbon::now();
                $table_data['created_by'] = $user_id;
                $res = insertRecord($table_name, $table_data, $user_id);
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

    public function saveEducationalBckGrndDetails(Request $request)
    {
        try {
            $selected_details = $request->input('selected_details');
            $case_id = $request->input('case_id');
            $selectedArray = json_decode($selected_details, true);
            $table_name = 'case_educational_background_details';
            foreach ($selectedArray as $key => $value) {
                $table_data = array(
                    'case_id' => $case_id,
                    'educational_bg_id' => isset($value['performance_id']) ? $value['performance_id'] : '',
                    'case_girl_id' => isset($value['case_girl_id']) ? $value['case_girl_id'] : '',
                    'good_performance' => isset($value['good_performance']) ? $value['good_performance'] : '',
                    'fair_performance' => isset($value['fair_performance']) ? $value['fair_performance'] : '',
                    'poor_performance' => isset($value['poor_performance']) ? $value['poor_performance'] : '',
                    'beneficiary_strengths' => isset($value['beneficiary_strengths']) ? $value['beneficiary_strengths'] : '',
                    'excellent_performance' => isset($value['excellent_performance']) ? $value['excellent_performance'] : ''
                );
                //where data
                $where_data = array(
                    'id' => $value['record_id']
                );
                if (recordExists($table_name, $where_data)) {
                    $table_data['updated_by'] = $this->user_id;
                    DB::table($table_name)
                        ->where($where_data)
                        ->update($table_data);
                } else {
                    $table_data['created_by'] = $this->user_id;
                    DB::table($table_name)->insert($table_data);
                }
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
    
    public function getEducationalBckGrndInfo(Request $request)
    {
        $case_id = $request->input('case_id');
        try {
            $qry = DB::table('case_educational_background_info as t1')
                ->leftJoin('case_educational_background_details as t2', function ($join) use ($case_id) {
                    $join->on('t1.id', '=', 't2.educational_bg_id')
                        ->where(array('t2.case_id' => $case_id));
                })
                ->select('t1.id as performance_id',DB::Raw('t1.name as performance_area'),'t2.*');
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
    
    public function saveEducationalBgInfo(Request $request)
    {        
        $term_id = $request->input('single_term_id');
        $year_id = $request->input('single_year_id');
        $record_id = $request->input('single_record_id');
        $postdata = file_get_contents("php://input");
        $data = json_decode($postdata);
        // the post data is array thus handle as array data
        if (is_array($data)) {
            $data = json_decode($postdata, true);
        } else {
            $data = array();
            $data[] = json_decode($postdata, true);
        }
        $table_name = 'mne_dqa_enrolment_info';
        try {
            foreach ($data as $key => $value) {
                $table_data = array(
                    'grade_id' => $value['gradeId'],
                    'term_id' => $term_id,
                    'year_id' => $year_id,
                    'record_id' => $record_id,
                    'kgs_reported' => $value['kgs_reported'],
                    'non_kgs_reported' => $value['non_kgs_reported'],
                    'kgs_recounted' => $value['kgs_recounted'],
                    'non_kgs_recounted' => $value['non_kgs_recounted'],
                    'kgs_discrepancy' => $value['kgs_discrepancy'],
                    'non_kgs_discrepancy' => $value['non_kgs_discrepancy']
                );
                //where data
                $where_data = array(
                    'id' => $value['id']
                );
                if (recordExists($table_name, $where_data)) {
                    $table_data['updated_by'] = $this->user_id;
                    DB::table($table_name)->where($where_data)->update($table_data);
                } else {
                    $table_data['created_by'] = $this->user_id;
                    DB::table($table_name)->insert($table_data);
                }
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
    
    public function saveCaseModuleCommonData(Request $req)
    {
        try {
            $user_id = $this->user_id;
            $post_data = $req->all();
            $table_name = $post_data['table_name'];
            $id = $post_data['id'];
            $res = array();
            //unset unnecessary values
            unset($post_data['_token']);
            unset($post_data['table_name']);
            unset($post_data['model']);
            unset($post_data['id']);
            $table_data = $post_data;
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
                    $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                } else {
                    $res = insertRecord($table_name, $table_data, $user_id);
                }
            } else {
                $res = insertRecord($table_name, $table_data, $user_id);
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

    public function getCaseModuleParamFromTable(Request $request)
    {
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

    public function deleteCaseModuleRecord(Request $request)
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

    public function updateCaseGirlOtherDetails($case_girl_id, $case_id)
    {
        $user_id = $this->user_id;
        $details = DB::table('case_sibling_significantdetails as t1')
            ->select(DB::raw("case_girl_id,$case_id as case_id,first_name,last_name,occupation,id_no,employer,
                     address,telephone,dob,relationship,relationcat,category_id,remarks,$user_id as created_by"))
            ->where('case_girl_id', $case_girl_id)
            ->orderBy('case_id', 'desc')->limit(3)
            ->get();
        $details = convertStdClassObjToArray($details);
        DB::table('case_sibling_significantdetails')
            ->insert($details);
    }
    
    public function getCaseEntriesInfo(Request $request)
    {
        try {
            $user_id = $this->user_id;
            $user_access_point = Auth::user()->access_point_id;
            $workflow_stage_id = $request->input('workflow_stage_id');
            $province_id = $request->input('province_id');
            $district_id = $request->input('district_id');
            $case_status_id = $request->input('case_status_id');
            $target_group_id = $request->input('target_group_id');
            $dropout_reason_id = $request->input('dropout_reason_id');
            $careplan_id = $request->input('careplan_id');
            $careplan_provided_id = $request->input('careplan_provided_id');
            $filter = $request->input('filter');
            $girl_id = $request->input('girl_id');
            $year_id = $request->input('year_id');
            $validation_status = $request->input('validation_status');
            $target_grp_id = $request->input('target_grp_id');
            //Get user grp details
            $user_grp_details = DB::table('users as t1')
                ->join('user_group as t2', 't2.user_id', '=', 't1.id')
                ->join('user_roles as t3', 't3.id', '=', 't1.user_role_id')
                ->select('t1.user_role_id','t2.user_id','t2.group_id',
                    DB::raw("t3.access_point_id,CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.last_name)) as logged_in_user,t3.name as user_role"))
                ->where('t1.id', $user_id)->first();
            // dd($user_grp_details);
            $user_role_id = $user_grp_details ? $user_grp_details->user_role_id : null;
            $group_id = $user_grp_details ? $user_grp_details->group_id : null;
            $logged_in_user = $user_grp_details ? $user_grp_details->logged_in_user : null;
            $access_point_id = $user_grp_details ? $user_grp_details->access_point_id : null;
            $logged_user = $logged_in_user;
            $qry = DB::table('case_basicdataentry_details as t1')
                ->join('case_workflow_stages as t2', 't1.workflow_stage_id', '=', 't2.id')
                ->join('case_statuses as t3', 't1.record_status_id', '=', 't3.id')
                ->join('case_girl_details as t4', 't1.case_girl_id', '=', 't4.id')
                ->join('districts as t5', 't1.district_id', '=', 't5.id')
                ->join('case_target_groups as t7', 't1.target_group_id', '=', 't7.id')
                ->join('wf_kgsprocesses as t8', 't1.process_id', '=', 't8.id')
                ->leftJoin('school_information as t9', 't1.school_id', '=', 't9.id')
                ->leftJoin('users as t10', 't1.created_by', '=', 't10.id')
                // ->leftJoin('user_district as t11', 't5.id', '=', 't11.district_id')
                // ->leftJoin('user_school as t12', 't9.id', '=', 't12.school_id')
                ->select(DB::raw("t4.*,t1.*,t1.id as recordId,t2.name as workflow_stage,t2.interface_xtype,t2.tab_index,CONCAT_WS(' ',decrypt(t10.first_name),decrypt(t10.last_name)) as entry_by,
                    t5.name as district_name,t3.name as record_status,t7.name as target_group,t8.name as process_name,TOTAL_WEEKDAYS(t1.case_sysrecording_date,now()) as sys_recorded_span,
                    CONCAT_WS(' ',t1.informant_first_name,t1.informant_last_name) as informant_name,CONCAT_WS(' ',t1.personel_first_name,t1.personel_last_name) as person_filling_form_name,
                    CONCAT_WS(' ',t4.first_name,t4.last_name) as girl_name,t9.name as school_name,
                    TOTAL_WEEKDAYS(t1.case_sysrecording_date,now()) as recorded_span,'".$logged_in_user."' as logged_in_user,
                    '".$group_id."' as user_grp_id,'".$user_role_id."' as user_role_id,'".$user_id."' as user_id"));
            if (validateisNumeric($workflow_stage_id)) {
                $qry->where('t1.workflow_stage_id', $workflow_stage_id); 
            }
            if (validateisNumeric($province_id)) {
                $qry->where('t1.province_id', $province_id);
            }
            if (validateisNumeric($district_id)) {
                $qry->where('t1.district_id', $district_id);
            }
            if (validateisNumeric($case_status_id)) {
                $qry->where('t1.record_status_id', $case_status_id);
            }
            if (validateisNumeric($target_group_id)) {
                $qry->where('t1.target_group_id', $target_group_id);
            }
            if (validateisNumeric($girl_id)) {
                $qry->where('t4.ben_girl_id', $girl_id);
            }
            if (validateisNumeric($dropout_reason_id)) {
                $qry->where('t1.dropout_reason_id', $dropout_reason_id);
            }
            if (isset($filter)) {
                $filters = json_decode($filter);
                $filter_string = $this->buildCaseSearchQuery($filters);
                if ($filter_string != '') {
                    $qry->whereRAW($filter_string);
                }
            }            
            if (validateisNumeric($year_id)) {
                $qry->whereRaw("YEAR(t1.case_sysrecording_date)=$year_id");
            }
            if (validateisNumeric($validation_status)) {
                $qry->whereRaw("t1.validation_status=$validation_status");
            }   
            if (validateisNumeric($target_grp_id)) {
                $qry->whereRaw("t1.target_group_id=$target_grp_id");
            }
            if (validateisNumeric($user_access_point)) {                
                if ($user_access_point == 3 || $user_access_point == 4) {//assigned districts
                    $qry->whereIn('t1.personel_district_id', function ($query) use ($user_id) {
                        $query->select(DB::raw('user_district.district_id'))
                            ->from('user_district')
                            ->whereRaw('user_district.user_id=' . $user_id);
                    });
                }
                if ($user_access_point == 1 || $user_access_point == 2) {//HQ
                    $qry->whereIn('t1.access_point_id', [1,2]);
                } else if ($user_access_point == 5) {//School
                    $qry->where('t1.access_point_id', 5);
                    if (validateisNumeric($group_id)) {
                        if ($group_id == 59) {//if Head Teacher 61
                            $qry->where('t1.submitted_for_verification', 1);
                            $qry->whereIn('t1.school_id', function ($qry) use ($user_id) {//assigned schools
                                $qry->select(DB::raw('user_school.school_id'))
                                    ->from('user_school')
                                    ->whereRaw('user_school.user_id=' . $user_id);
                            });
                        } else if ($group_id == 57) {//if G&C Teachers 62
                            $qry->where('t1.submitted_for_verification', 0);
                            $qry->whereIn('t1.school_id', function ($qry) use ($user_id) {//assigned schools
                                $qry->select(DB::raw('user_school.school_id'))
                                    ->from('user_school')
                                    ->whereRaw('user_school.user_id=' . $user_id);
                            });
                        }
                    }
                    /*
                    if ($user_access_point == 3 || $user_access_point == 4) {//assigned districts/prov
                        $qry->whereIn('t6.district_id', function ($query) use ($logged_in_user) {
                            $query->select(DB::raw('user_district.district_id'))
                                ->from('user_district')
                                ->whereRaw('user_district.user_id=' . $logged_in_user);
                        });
                    } 
                    */
                } else {
                    $qry->where('t1.access_point_id', $user_access_point);
                }
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
    
    public function getCaseEntriesInfoToDasboard(Request $request)
    {
        try {
            $user_id = $this->user_id;
            $user_access_point = Auth::user()->access_point_id;
            $workflow_stage_id = $request->input('workflow_stage_id');
            $province_id = $request->input('province_id');
            $district_id = $request->input('district_id');
            $case_status_id = $request->input('case_status_id');
            $target_group_id = $request->input('target_group_id');
            $dropout_reason_id = $request->input('dropout_reason_id');
            $careplan_id = $request->input('careplan_id');
            $careplan_provided_id = $request->input('careplan_provided_id');
            $filter = $request->input('filter');
            $girl_id = $request->input('girl_id');
            $year_id = $request->input('year_id');
            $validation_status = $request->input('validation_status');
            $target_grp_id = $request->input('target_grp_id');
            //Get user grp details
            $user_grp_details = DB::table('users as t1')
                ->join('user_group as t2', 't2.user_id', '=', 't1.id')
                ->join('user_roles as t3', 't3.id', '=', 't1.user_role_id')
                ->select('t1.user_role_id','t2.user_id','t2.group_id',
                    DB::raw("t3.access_point_id,CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.last_name)) as logged_in_user,t3.name as user_role"))
                ->where('t1.id', $user_id)->first();
            $user_role_id = $user_grp_details ? $user_grp_details->user_role_id : null;
            $group_id = $user_grp_details ? $user_grp_details->group_id : null;
            $logged_in_user = $user_grp_details ? $user_grp_details->logged_in_user : null;
            $access_point_id = $user_grp_details ? $user_grp_details->access_point_id : null;
            $logged_user = $logged_in_user;
            $qry = DB::table('case_basicdataentry_details as t1')
                ->join('case_workflow_stages as t2', 't1.workflow_stage_id', '=', 't2.id')
                ->join('case_statuses as t3', 't1.record_status_id', '=', 't3.id')
                ->join('case_girl_details as t4', 't1.case_girl_id', '=', 't4.id')
                ->join('districts as t5', 't1.district_id', '=', 't5.id')
                ->join('case_target_groups as t7', 't1.target_group_id', '=', 't7.id')
                ->join('wf_kgsprocesses as t8', 't1.process_id', '=', 't8.id')
                ->leftJoin('school_information as t9', 't1.school_id', '=', 't9.id')
                ->leftJoin('users as t10', 't1.created_by', '=', 't10.id')
                ->select(DB::raw("t4.*,t1.*,t1.id as recordId,t2.name as workflow_stage,t2.interface_xtype,t2.tab_index,CONCAT_WS(' ',decrypt(t10.first_name),decrypt(t10.last_name)) as entry_by,
                    t5.name as district_name,t3.name as record_status,t7.name as target_group,t8.name as process_name,TOTAL_WEEKDAYS(t1.case_sysrecording_date,now()) as sys_recorded_span,
                    CONCAT_WS(' ',t1.informant_first_name,t1.informant_last_name) as informant_name,CONCAT_WS(' ',t1.personel_first_name,t1.personel_last_name) as person_filling_form_name,
                    CONCAT_WS(' ',t4.first_name,t4.last_name) as girl_name,t9.name as school_name,
                    TOTAL_WEEKDAYS(t1.case_sysrecording_date,now()) as recorded_span,'".$logged_in_user."' as logged_in_user,
                    '".$group_id."' as user_grp_id,'".$user_role_id."' as user_role_id,'".$user_id."' as user_id"));
            if (validateisNumeric($workflow_stage_id)) {
                $qry->where('t1.workflow_stage_id', $workflow_stage_id); 
            }
            if (validateisNumeric($province_id)) {
                $qry->where('t1.province_id', $province_id);
            }
            if (validateisNumeric($district_id)) {
                $qry->where('t1.district_id', $district_id);
            }
            if (validateisNumeric($case_status_id)) {
                $qry->where('t1.record_status_id', $case_status_id);
            }
            if (validateisNumeric($target_group_id)) {
                $qry->where('t1.target_group_id', $target_group_id);
            }
            if (validateisNumeric($girl_id)) {
                $qry->where('t4.ben_girl_id', $girl_id);
            }
            if (validateisNumeric($dropout_reason_id)) {
                $qry->where('t1.dropout_reason_id', $dropout_reason_id);
            }
            if (isset($filter)) {
                $filters = json_decode($filter);
                $filter_string = $this->buildCaseSearchQuery($filters);
                if ($filter_string != '') {
                    $qry->whereRAW($filter_string);
                }
            }            
            if (validateisNumeric($year_id)) {
                $qry->whereRaw("YEAR(t1.case_sysrecording_date)=$year_id");
            }
            if (validateisNumeric($validation_status)) {
                $qry->whereRaw("t1.validation_status=$validation_status");
            }   
            if (validateisNumeric($target_grp_id)) {
                $qry->whereRaw("t1.target_group_id=$target_grp_id");
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
    
    public function getCaseLogSheetInfo(Request $request)
    {
        try {
            $user_id = $this->user_id;
            $user_access_point = Auth::user()->access_point_id;
            $workflow_stage_id = $request->input('workflow_stage_id');
            $province_id = $request->input('province_id');
            $district_id = $request->input('district_id');
            $case_status_id = $request->input('case_status_id');
            $target_group_id = $request->input('target_group_id');
            $dropout_reason_id = $request->input('dropout_reason_id');
            $careplan_id = $request->input('careplan_id');
            $careplan_provided_id = $request->input('careplan_provided_id');
            $filter = $request->input('filter');
            $girl_id = $request->input('girl_id');
            $year_id = $request->input('year_id');
            $validation_status = $request->input('validation_status');
            $target_grp_id = $request->input('target_grp_id');
            //Get user grp details
            $user_grp_details = DB::table('users as t1')
                ->join('user_group as t2', 't2.user_id', '=', 't1.id')
                ->join('user_roles as t3', 't3.id', '=', 't1.user_role_id')
                ->select('t1.user_role_id','t2.user_id','t2.group_id',
                    DB::raw("t3.access_point_id,CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.last_name)) as logged_in_user,t3.name as user_role"))
                ->where('t1.id', $user_id)->first();
            $user_role_id = $user_grp_details ? $user_grp_details->user_role_id : null;
            $group_id = $user_grp_details ? $user_grp_details->group_id : null;
            $logged_in_user = $user_grp_details ? $user_grp_details->logged_in_user : null;
            $access_point_id = $user_grp_details ? $user_grp_details->access_point_id : null;
            $logged_user = $logged_in_user;
            $qry = DB::table('case_basicdataentry_details as t1')
                ->join('case_workflow_stages as t2', 't1.workflow_stage_id', '=', 't2.id')
                ->join('case_statuses as t3', 't1.record_status_id', '=', 't3.id')
                ->join('case_girl_details as t4', 't1.case_girl_id', '=', 't4.id')
                ->join('districts as t5', 't1.district_id', '=', 't5.id')
                ->join('case_target_groups as t7', 't1.target_group_id', '=', 't7.id')
                ->join('wf_kgsprocesses as t8', 't1.process_id', '=', 't8.id')
                ->leftJoin('school_information as t9', 't1.school_id', '=', 't9.id')
                ->leftJoin('users as t10', 't1.created_by', '=', 't10.id')
                ->select(DB::raw("t4.*,t1.*,t1.id as recordId,t2.name as workflow_stage,t2.interface_xtype,t2.tab_index,CONCAT_WS(' ',decrypt(t10.first_name),decrypt(t10.last_name)) as entry_by,
                    t5.name as district_name,t3.name as record_status,t7.name as target_group,t8.name as process_name,TOTAL_WEEKDAYS(t1.case_sysrecording_date,now()) as sys_recorded_span,
                    CONCAT_WS(' ',t1.informant_first_name,t1.informant_last_name) as informant_name,CONCAT_WS(' ',t1.personel_first_name,t1.personel_last_name) as person_filling_form_name,
                    CONCAT_WS(' ',t4.first_name,t4.last_name) as girl_name,t9.name as school_name,
                    TOTAL_WEEKDAYS(t1.case_sysrecording_date,now()) as recorded_span,'".$logged_in_user."' as logged_in_user,
                    '".$group_id."' as user_grp_id,'".$user_role_id."' as user_role_id,'".$user_id."' as user_id"));
                    
            $qry->where('t1.workflow_stage_id', '<>',9);

            if (validateisNumeric($province_id)) {
                $qry->where('t1.province_id', $province_id);
            }
            if (validateisNumeric($district_id)) {
                $qry->where('t1.district_id', $district_id);
            }
            if (validateisNumeric($case_status_id)) {
                $qry->where('t1.record_status_id', $case_status_id);
            }
            if (validateisNumeric($target_group_id)) {
                $qry->where('t1.target_group_id', $target_group_id);
            }
            if (validateisNumeric($girl_id)) {
                $qry->where('t4.ben_girl_id', $girl_id);
            }
            if (validateisNumeric($dropout_reason_id)) {
                $qry->where('t1.dropout_reason_id', $dropout_reason_id);
            }
            if (isset($filter)) {
                $filters = json_decode($filter);
                $filter_string = $this->buildCaseSearchQuery($filters);
                if ($filter_string != '') {
                    $qry->whereRAW($filter_string);
                }
            }            
            if (validateisNumeric($year_id)) {
                $qry->whereRaw("YEAR(t1.case_sysrecording_date)=$year_id");
            }
            if (validateisNumeric($validation_status)) {
                $qry->whereRaw("t1.validation_status=$validation_status");
            }   
            if (validateisNumeric($target_grp_id)) {
                $qry->whereRaw("t1.target_group_id=$target_grp_id");
            }
            if (validateisNumeric($user_access_point)) {              
                if ($user_access_point == 3 || $user_access_point == 4) {//assigned districts
                    $qry->whereIn('t1.personel_district_id', function ($query) use ($user_id) {
                        $query->select(DB::raw('user_district.district_id'))
                            ->from('user_district')
                            ->whereRaw('user_district.user_id=' . $user_id);
                    });
                } else if ($user_access_point == 5) {//School
                    $qry->whereIn('t1.school_id', function ($qry) use ($user_id) {//assigned schools
                        $qry->select(DB::raw('user_school.school_id'))
                            ->from('user_school')
                            ->whereRaw('user_school.user_id=' . $user_id);
                    });
                }
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

    public function buildCaseSearchQuery($filters)
    {
        $return_string = '';
        $whereClauses = array();
        if ($filters != NULL) {
            foreach ($filters as $filter) {
                switch ($filter->property) {
                    case 'case_file_number' :
                        $whereClauses[] = "t1.case_file_number like '%" . ($filter->value) . "%'";
                        break;
                    case 'mis_no' :
                        $whereClauses[] = "t1.mis_no like '%" . ($filter->value) . "%'";
                        break;
                    case 'workflow_stage' :
                        $whereClauses[] = "t1.workflow_stage_id = '" . ($filter->value) . "'";
                        break;
                    case 'record_status' :
                        $whereClauses[] = "t1.record_status_id = '" . ($filter->value) . "'";
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

    public function saveCaseBasicDataEntryInfo(Request $request)
    {
        try {
            $res = array();
            DB::transaction(function () use ($request, &$res) {
                $user_id = $this->user_id;
                $record_id = $request->input('record_id');
                $district_id = $request->input('district_id');
                $province_id = $request->input('province_id');
                $process_id = $request->input('process_id');
                $file_no = $request->input('case_file_number');
                $table_name = 'case_basicdataentry_details';
                $case_girl_id = $request->input('case_girl_id');
                $ben_girl_id = $request->input('ben_girl_id');                
                $case_details = array(
                    'case_file_number' => $request->input('case_file_number'),
                    'field_file_number' => $request->input('field_file_number'),
                    'target_group_id' => $request->input('target_group_id'),
                    'dropout_reason_id' => $request->input('dropout_reason_id'),
                    'case_formrecording_date' => $request->input('case_formrecording_date'),
                    'personel_designation_id' => $request->input('personel_designation_id'),
                    'personel_first_name' => $request->input('personel_first_name'),
                    'personel_last_name' => $request->input('personel_last_name'),
                    'personel_mobile_no' => $request->input('personel_mobile_no'),
                    'personel_district_id' => $request->input('personel_district_id'),
                    'personel_other_position' => $request->input('personel_other_position'),
                    
                    'rec_province_id' => $province_id,
                    'rec_district_id' => $request->input('district_id'),
                    'rec_ward' => $request->input('ward'),
                    'rec_village' => $request->input('village'),
                    'rec_chief' => $request->input('chief'),
                    'rec_school_id' => $request->input('gnc_teacher_school_id'),
                    'rec_nearest_clinic' => $request->input('nearest_clinic'),
                    'rec_nearest_business_center' => $request->input('nearest_business_center'),
                    'rec_other_significant_places' => $request->input('other_significant_places'),

                    'province_id' => $province_id,
                    'district_id' => $request->input('district_id'),
                    'ward' => $request->input('ward'),
                    'village' => $request->input('village'),
                    'chief' => $request->input('chief'),
                    // 'school_id' => $request->input('school_id'),
                    'school_id' => $request->input('gnc_teacher_school_id'),
                    'nearest_clinic' => $request->input('nearest_clinic'),
                    'nearest_business_center' => $request->input('nearest_business_center'),
                    'other_significant_places' => $request->input('other_significant_places'),
                    'gnc_teacher_school_id' => $request->input('gnc_teacher_school_id'),
                    'cwac_member_cwac_id' => $request->input('cwac_member_cwac_id'),

                    'informant_first_name' => $request->input('informant_first_name'),
                    'informant_last_name' => $request->input('informant_last_name'),
                    'informant_idno' => $request->input('informant_idno'),
                    'informant_gender_id' => $request->input('informant_gender_id'),
                    'informant_address' => $request->input('informant_address'),
                    'informant_rshipwith_girl' => $request->input('informant_rshipwith_girl'),
                    'informant_contacts' => $request->input('informant_contacts'),
                    'informant_email_address' => $request->input('informant_email_address'),
                    'refferal_date' => $request->input('refferal_date'),
                    'reasons_for_refferal' => $request->input('reasons_for_refferal'),
                    'other_position' => $request->input('other_position'),

                    'profiling_reason_id' => $request->input('profiling_reason_id'),
                    'other_reasons_for_profiling' => $request->input('other_reasons_for_profiling'),
                    'other_background_info' => $request->input('other_background_info'),
                    'action_points' => $request->input('action_points'),
                    'girl_mobile_no' => $request->input('girl_mobile_no'),
                    'landmark' => $request->input('landmark'),
                    'township' => $request->input('township'),
                    'deputy_ht_name' => $request->input('deputy_ht_name'),
                    'deputy_ht_comment' => $request->input('deputy_ht_comment'),
                    'deputy_ht_comment_date' => $request->input('deputy_ht_comment_date'),
                    'ht_deputy_ht_designation_id' => $request->input('ht_deputy_ht_designation_id'),
                    'ht_name' => $request->input('ht_name'),
                    'ht_comment' => $request->input('ht_comment'),
                    'ht_comment_date' => $request->input('ht_comment_date'),
                    'ht_email' => $request->input('ht_email'),
                    
                    'parent_nrc' => $request->input('parent_nrc'),
                    'parent_name' => $request->input('parent_name'),
                    'parent_phone' => $request->input('parent_phone'),
                    'access_point_id' => $request->input('access_point_id'),
                    'supervisor_signature' => $request->input('supervisor_signature'),
                    'case_id' => $request->input('case_id'),
                    'last_completed_grade' => $request->input('last_completed_grade'),
                    'case_mis_number' => $request->input('case_mis_number'),
                    'beneficiary_id' => $request->input('beneficiary_id'),
                    'girl_last_name' => $request->input('last_name'),
                    'girl_first_name' => $request->input('first_name'),
                    'rship_status' => $request->input('rship_status'),
                    'current_grade' => $request->input('current_grade'),
                    'rship_other_status' => $request->input('rship_other_status'),
                    'is_disabled' => $request->input('is_disabled'),
                    'disability_details' => $request->input('disability_details'),
                    'assessment_date' => $request->input('assessment_date'),
                    'dob' => $request->input('dob')
                );
                $case_girl_details = array(
                    'is_kgs_beneficiary' => 1,
                    'ben_girl_id' => $ben_girl_id,
                    'beneficiary_id' => $request->input('beneficiary_id'),
                    'first_name' => $request->input('first_name'),
                    'last_name' => $request->input('last_name'),
                    'dob' => $request->input('dob'),
                    'girl_is_disabled' => $request->input('girl_is_disabled'),
                    'details_of_disability' => $request->input('details_of_disability'),
                    'girl_address' => $request->input('girl_address'),                    
                    'last_completed_grade' => $request->input('last_completed_grade'),
                    'current_grade' => $request->input('current_grade')
                );
                $where = array(
                    'id' => $record_id
                );
                //Get user grp details G&C
                $user_grp_details = DB::table('users as t1')
                    ->join('user_group as t2', 't2.user_id', '=', 't1.id')
                    ->select('t1.user_role_id','t2.user_id','t2.group_id',DB::raw("CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.last_name)) as logged_in_user"))
                    ->where('t1.id', $user_id)->first();
                $user_role_id = $user_grp_details->user_role_id;
                $group_id = $user_grp_details->group_id;
                $case_details['user_role_id'] = $user_role_id;
                $case_details['user_grp_id'] = $group_id;
                if (validateisNumeric($record_id)) {
                    $previous_data = array();
                    if (recordExists($table_name, $where)) {
                        $case_details['updated_at'] = Carbon::now();
                        $case_details['updated_by'] = $user_id;
                        $previous_data = getPreviousRecords($table_name, $where);
                        $res = updateRecord($table_name, $previous_data, $where, $case_details, $user_id);
                    }
                    DB::table('case_girl_details')
                        ->where('id', $case_girl_id)
                        ->update($case_girl_details);
                    $mis_no = $previous_data[0]['mis_no'];
                    $folder_id = $previous_data[0]['folder_id'];
                } else {
                    $case_details['workflow_stage_id'] = 1;
                    $case_details['case_sysrecording_date'] = Carbon::now();
                    $case_details['created_at'] = Carbon::now();
                    $case_details['created_by'] = $user_id;
                    $case_details['source_module'] = $request->input('source_module');
                    $codes_array = array(
                        'province_code' => getSingleRecordColValue('provinces', array('id' => $province_id), 'code'),
                        'district_code' => getSingleRecordColValue('districts', array('id' => $district_id), 'code'),
                        'record_month' => date('m')
                    );
                    $ref_details = generateRecordRefNumber(5, $process_id, $district_id, $codes_array, $table_name, $user_id);
                    if ($ref_details['success'] == false) {
                        return \response()->json($ref_details);
                    }
                    $case_girl_insert = insertRecord('case_girl_details', $case_girl_details, $user_id);
                    if ($case_girl_insert['success'] == false) {
                        return \response()->json($case_girl_insert);
                    }
                    $mis_no = $ref_details['ref_no'];
                    //todo DMS
                    $parent_id = 258558;
                    $folder_id = 1234;//createDMSParentFolder($parent_id, '', $mis_no, '', $this->dms_id);
                    //createDMSModuleFolders($folder_id, 39, $this->dms_id);
                    //end DMS
                    $case_details['view_id'] = generateRecordViewID();
                    $case_details['mis_no'] = $mis_no;
                    $case_details['folder_id'] = $folder_id;
                    $case_details['case_girl_id'] = $case_girl_insert['record_id'];
                    $case_details['created_at'] = Carbon::now();
                    $case_details['created_by'] = $user_id;                        
                    $res = insertRecord($table_name, $case_details, $user_id);
                    if ($res['success'] == false) {
                        return \response()->json($res);
                    }
                    $record_id = $res['record_id'];
                    $this->updateCaseGirlOtherDetails($case_girl_id, $res['record_id']);//update case_girl other details
                }
                $res['file_no'] = $file_no;
                $res['folder_id'] = $folder_id;
                $res['mis_no'] = $mis_no;
                $res['process_name'] = getSingleRecordColValue('wf_kgsprocesses', array('id' => 4), 'name');
                $res['record_status'] = getSingleRecordColValue('case_statuses', array('id' => 1), 'name');
                $res['caseProfilingFrmId'] = $record_id;
                $res['record_id'] = $record_id;
                $res['user_role_id'] = $user_role_id;
                $res['user_grp_id'] = $group_id;
                $res['user_id'] = $user_id;
            }, 5);
            //Get user grp details
            $user_id = $this->user_id;
            $user_grp_details = DB::table('users as t1')
                ->join('user_group as t2', 't2.user_id', '=', 't1.id')
                ->join('user_roles as t3', 't3.id', '=', 't1.user_role_id')
                ->select('t1.user_role_id','t2.user_id','t2.group_id',
                    DB::raw("t3.access_point_id,CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.last_name)) as logged_in_user,t3.name as user_role"))
                ->where('t1.id', $user_id)->first();
                // dd($user_grp_details);
            $group_id = $user_grp_details ? $user_grp_details->group_id : null;
            $logged_in_user = $user_grp_details ? $user_grp_details->logged_in_user : null;
            $user_role = $user_grp_details ? $user_grp_details->user_role : null;
            $access_point_id = $user_grp_details ? $user_grp_details->access_point_id : null;
            $res['entry_by'] = $logged_in_user;
            $res['group_id'] = $group_id;
            $res['access_point_id'] = $access_point_id;
            $res['logged_in_user'] = $user_role;
            $res['user_role'] = $user_role;
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

    public function getCaseNotificationInfo(Request $request)//Frank here
    {
        try {
            $user_id = $this->user_id;
            $user_access_point = Auth::user()->access_point_id;
            //Get user grp details
            $user_grp_details = DB::table('users as t1')
                ->join('user_group as t2', 't2.user_id', '=', 't1.id')
                ->join('user_roles as t3', 't3.id', '=', 't1.user_role_id')
                ->select('t1.user_role_id','t2.user_id','t2.group_id',
                    DB::raw("t3.access_point_id,CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.last_name)) as logged_in_user,t3.name as user_role"))
                ->where('t1.id', $user_id)->first();
            $user_role_id = $user_grp_details ? $user_grp_details->user_role_id : null;
            $group_id = $user_grp_details ? $user_grp_details->group_id : null;
            $logged_in_user = $user_grp_details ? $user_grp_details->logged_in_user : null;
            $access_point_id = $user_grp_details ? $user_grp_details->access_point_id : null;
            $logged_user = $logged_in_user;

            $qry = DB::table('case_notifications as t1')
                ->join('case_basicdataentry_details as t11', 't1.record_id', '=', 't11.id')
                ->join('case_workflow_stages as t2', 't11.workflow_stage_id', '=', 't2.id')
                ->join('case_statuses as t3', 't11.record_status_id', '=', 't3.id')
                ->join('case_girl_details as t4', 't11.case_girl_id', '=', 't4.id')
                ->join('districts as t5', 't11.district_id', '=', 't5.id')
                ->join('case_target_groups as t7', 't11.target_group_id', '=', 't7.id')
                ->join('wf_kgsprocesses as t8', 't11.process_id', '=', 't8.id')
                ->leftJoin('school_information as t9', 't11.school_id', '=', 't9.id')
                ->leftJoin('users as t10', 't11.created_by', '=', 't10.id')
                ->select(DB::raw("t1.*,t4.*,t11.*,t11.id as recordId,t2.name as workflow_stage,
                    t2.interface_xtype,t2.tab_index,CONCAT_WS(' ',decrypt(t10.first_name),decrypt(t10.last_name)) as entry_by,
                    t5.name as district_name,t3.name as record_status,t7.name as target_group,
                    t8.name as process_name,TOTAL_WEEKDAYS(t11.case_sysrecording_date,now()) as sys_recorded_span,
                    CONCAT_WS(' ',t11.informant_first_name,t11.informant_last_name) as informant_name,
                    CONCAT_WS(' ',t11.personel_first_name,t11.personel_last_name) as person_filling_form_name,
                    CONCAT_WS(' ',t4.first_name,t4.last_name) as girl_name,t9.name as school_name,
                    TOTAL_WEEKDAYS(t11.case_sysrecording_date,now()) as recorded_span,'".$logged_in_user."' as logged_in_user,
                    '".$user_access_point."' as access_point_id,'".$group_id."' as user_grp_id,'".$user_role_id."' as user_role_id"))                    
                ->where('t1.status', 1);
                if (validateisNumeric($user_access_point)) {                
                    if ($user_access_point == 3 || $user_access_point == 4) {//assigned districts
                        $qry->whereIn('t11.personel_district_id', function ($query) use ($user_id) {
                            $query->select(DB::raw('user_district.district_id'))
                                ->from('user_district')
                                ->whereRaw('user_district.user_id=' . $user_id);
                        });
                    }
                    if ($user_access_point == 1 || $user_access_point == 2) {//HQ
                        $qry->whereIn('t11.access_point_id', [1,2]);
                    } else if ($user_access_point == 5) {//School
                        $qry->where('t11.access_point_id', 5);
                        if (validateisNumeric($group_id)) {
                            if ($group_id == 59) {//if Head Teacher 61
                                $qry->where('t11.submitted_for_verification', 1);
                                $qry->whereIn('t11.school_id', function ($qry) use ($user_id) {//assigned schools
                                    $qry->select(DB::raw('user_school.school_id'))
                                        ->from('user_school')
                                        ->whereRaw('user_school.user_id=' . $user_id);
                                });
                            } else if ($group_id == 57) {//if G&C Teachers 62
                                $qry->where('t11.submitted_for_verification', 0);
                                $qry->whereIn('t11.school_id', function ($qry) use ($user_id) {//assigned schools
                                    $qry->select(DB::raw('user_school.school_id'))
                                        ->from('user_school')
                                        ->whereRaw('user_school.user_id=' . $user_id);
                                });
                            }
                        }
                    } else {
                        $qry->where('t11.access_point_id', $user_access_point);
                    }
                }
            $qry->orderBy('t1.notification_date', 'DESC');
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

    public function dismissCaseNotification(Request $request)
    {
        $item_ids = $request->input();
        try {
            DB::table('case_notifications')
                ->whereIn('id', $item_ids)
                ->update(array('status' => 2, 'dismissedBy' => $this->user_id));
            $res = array(
                'success' => true,
                'message' => 'Notifications dismissed successfully!!'
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

    public function prepareCaseRecordingForm(Request $request)
    {
        try {
            $record_id = $request->input('record_id');
            $qry = DB::table('case_basicdataentry_details as t1')
                ->join('case_girl_details as t2', 't1.case_girl_id', '=', 't2.id')
                ->select('t2.*', 't1.*')
                ->where('t1.id', $record_id);
            $results = $qry->first();
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
        return \response()->json($res);
    }

    public function processEWSsubmission($record_id)
    {
        $process_id = 0;
        $prev_stage = 1;
        $to_stage = 2;
        $email_notification = 1;
        $user_id = $this->user_id;
        $logged_in_user = $this->user_id;
        $table_name = 'case_basicdataentry_details';
        $remarks = 'Automatic Submission via the Early Warning Signs Tool';
        $submission_reason = 'Automatic Submission via the Early Warning Signs Tool';
        $special_comments = 'Automatic Submission via the Early Warning Signs Tool';
        
        // DB::beginTransaction();
        try {
            $record_details = DB::table($table_name)
                ->where('id', $record_id)
                ->first();
            if (is_null($record_details)) {
                $res = array(
                    'success' => false,
                    'message' => 'Problem encountered while fetching record details!!'
                );
                return \response()->json($res);
            }            
            $file_no = $record_details->case_file_number;
            $mis_no = $record_details->mis_no;
            $target_group = $record_details->target_group_id;
            $created_at = $record_details->created_at;
            $headTeacherMail = $record_details->created_at;
            $workflow_stage = getSingleRecordColValue('case_workflow_stages', array('id' => $prev_stage), 'name');
            $where = array(
                'id' => $record_id
            );            
            $app_update = array(
                'submitted_for_verification' => 1,
                'ht_approved' => 0,
                'curr_from_userid' => $user_id,
                // 'curr_to_userid' => $responsible_user,
                'current_stage_entry_date' => Carbon::now()
            );
            $prev_data = getPreviousRecords($table_name, $where);
            $update_res = updateRecord($table_name, $prev_data, $where, $app_update, $user_id);
            if ($update_res['success'] == false) {
                return \response()->json($update_res);
            }
            //notifications
            $notification_details = array(
                'record_id' => $record_id,
                'notification_type_id' => 1,
                'notification_date' => Carbon::now(),
                'status' => 1
            );
            DB::table('case_notifications')->insert($notification_details);
            $transition_params = array(
                'record_id' => $record_id,
                'process_id' => $process_id,
                'from_stage' => $prev_stage,
                'to_stage' => $prev_stage,
                'from_user' => $user_id,
                // 'to_user' => $responsible_user,
                'author' => $user_id,
                'remarks' => $remarks,
                'created_at' => Carbon::now(),
                'created_by' => $user_id
            );
            DB::table('records_workflow_transitions')->insert($transition_params);
            // DB::commit();
            //email
            $workflow_stage = 'Case Intake/Profiling';
            //Mail notification for CMS Expert
            if (is_connected()) {
                $vars = array(
                    '{workflow_stage}' => $workflow_stage,
                    '{submitted_at}' =>  Carbon::now()->format('d/m/Y'),
                    '{target_group}' => $target_group,
                    '{from_stage}' => $workflow_stage,
                    '{created_at}' => Carbon::parse($created_at)->format('d/m/Y'),
                    '{user_id}' => $logged_in_user,
                    '{to_stage}' => $workflow_stage,
                    '{file_no}' => $file_no,
                    '{remarks}' => $special_comments,
                    '{mis_no}' => $mis_no
                );                
                $emailTemplateInfo = getEmailTemplateInfo(6, $vars);
                $emailJob = (new GenericSendEmailJob('rodgers.ch2@gmail.com', $emailTemplateInfo->subject, $emailTemplateInfo->body, array()))->delay(Carbon::now()->addSeconds(10));
                dispatch($emailJob);
                $emailTemplateInfo = getEmailTemplateInfo(6, $vars);
                $emailJob = (new GenericSendEmailJob('david.mbao@edu.gov.zm', $emailTemplateInfo->subject, $emailTemplateInfo->body, array()))->delay(Carbon::now()->addSeconds(10));
                dispatch($emailJob);
                // $emailTemplateInfo = getEmailTemplateInfo(6, $vars);
                // $emailJob = (new GenericSendEmailJob('franklin.otieno@softclans.co.ke', $emailTemplateInfo->subject, $emailTemplateInfo->body, array()))->delay(Carbon::now()->addSeconds(10));
                dispatch($emailJob);
            }
            $res = array(
                'success' => true,
                'message' => 'Record submitted successfully!!'
            );
        } catch (\Exception $exception) {
            // DB::rollBack();
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            // DB::rollBack();
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return \response()->json($res);
    }

    public function processCaseRecordSubmission(Request $request)
    {//frank
        $record_id = $request->input('record_id');
        $process_id = $request->input('process_id');
        $table_name = $request->input('table_name');
        $prev_stage = $request->input('prevstage_id');
        $to_stage = $request->input('nextstage_id');
        $remarks = $request->input('remarks');
        $responsible_user = $request->input('responsible_user');
        $email_notification = $request->input('email_notification');
        $email_address = $request->input('email_address');
        $submitted_for_verification = $request->input('submitted_for_verification');
        $user_id = $this->user_id;
        $logged_in_user = $request->input('logged_in_user');
        $submission_reason = $request->input('submission_reason');
        $special_comments = $request->input('special_comments');
        $ht_approved = $request->input('ht_approved');
        $submitted_for_verification = $request->input('submitted_for_verification');
        DB::beginTransaction();
        try {
            $record_details = DB::table($table_name)
                ->where('id', $record_id)
                ->first();
            if (is_null($record_details)) {
                $res = array(
                    'success' => false,
                    'message' => 'Problem encountered while fetching record details!!'
                );
                return \response()->json($res);
            }

            $file_no = $record_details->case_file_number;
            $mis_no = $record_details->mis_no;
            $target_group = $record_details->target_group_id;
            $created_at = $record_details->created_at;
            $workflow_stage = getSingleRecordColValue('case_workflow_stages', array('id' => $to_stage), 'name');
            $where = array(
                'id' => $record_id
            );
            $app_update = array(
                // 'visible_atschool_level' => 0,
                'validation_status' => 0,
                // 'submitted_for_verification' => $submitted_for_verification,
                'workflow_stage_id' => $to_stage,
                'isRead' => 0,
                'curr_from_userid' => $user_id,
                'curr_to_userid' => $responsible_user,
                'current_stage_entry_date' => Carbon::now()
            );

            $prev_data = getPreviousRecords($table_name, $where);
            $update_res = updateRecord($table_name, $prev_data, $where, $app_update, $user_id);
            if ($update_res['success'] == false) {
                return \response()->json($update_res);
            }
            $transition_params = array(
                'record_id' => $record_id,
                'file_no' => $file_no,
                'process_id' => $process_id,
                'from_stage' => $prev_stage,
                'to_stage' => $to_stage,
                'from_user' => $user_id,
                'to_user' => $responsible_user,
                'author' => $user_id,
                'remarks' => $remarks,
                'created_at' => Carbon::now(),
                'created_by' => $user_id
            );
            DB::table('records_workflow_transitions')->insert($transition_params);
            DB::commit();            
            //notifications
            $notification_details = array(
                'record_id' => $record_id,
                'notification_type_id' => 1,
                'notification_date' => Carbon::now(),
                'status' => 1
            );
            DB::table('case_notifications')->insert($notification_details);
            $this->grievanceEscalationEmailNotification($programme_type_id, $province_id, $complaint_form_no);//HQ & Province
            //email
            if ($email_notification == 1) {
                if (is_connected()) {
                    $vars = array(
                        '{file_no}' => $file_no,
                        '{mis_no}' => $mis_no,
                        '{workflow_stage}' => $workflow_stage
                    );
                    $emailTemplateInfo = getEmailTemplateInfo(6, $vars);
                    $emailJob = (new GenericSendEmailJob($email_address, $emailTemplateInfo->subject, $emailTemplateInfo->body, array()))->delay(Carbon::now()->addSeconds(10));
                    dispatch($emailJob);
                }
            }
            //Mail notification for CMS Expert
            if (is_connected()) {
                $vars = array(
                    '{file_no}' => $file_no,
                    '{mis_no}' => $mis_no,
                    '{workflow_stage}' => $workflow_stage
                );
                $emailTemplateInfo = getEmailTemplateInfo(6, $vars);
                // $emailJob = (new GenericSendEmailJob('rodgers.ch2@gmail.com', $emailTemplateInfo->subject, $emailTemplateInfo->body, array()))->delay(Carbon::now()->addSeconds(10));
                // dispatch($emailJob);
                // $emailJob = (new GenericSendEmailJob('david.mbao@edu.gov.zm', $emailTemplateInfo->subject, $emailTemplateInfo->body, array()))->delay(Carbon::now()->addSeconds(10));
                // dispatch($emailJob);
                $emailJob = (new GenericSendEmailJob('franklin.otieno@softclans.co.ke', $emailTemplateInfo->subject, $emailTemplateInfo->body, array()))->delay(Carbon::now()->addSeconds(10));
                dispatch($emailJob);
            }
            $res = array(
                'success' => true,
                'message' => 'Record submitted successfully!!'
            );
        } catch (\Exception $exception) {
            DB::rollBack();
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            DB::rollBack();
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return \response()->json($res);
    }

    public function processCaseRecordValidationSubmission(Request $request)
    {//For G&C teacher
        $form_data = $request->all();
        unset($form_data['table_name']);
        unset($form_data['storeID']);
        unset($form_data['ht_approved']);
        unset($form_data['nextstage_id']);
        unset($form_data['submission_lvl']);

        $user_access_point = Auth::user()->access_point_id;
        $record_id = $request->input('record_id');
        $process_id = $request->input('process_id');
        $table_name = $request->input('table_name');
        $prev_stage = $request->input('prevstage_id');
        $to_stage = $request->input('nextstage_id');
        $remarks = $request->input('remarks');
        $submission_lvl = $request->input('submission_lvl');
        $responsible_user = $request->input('responsible_user');
        $email_notification = $request->input('email_notification');
        $email_address = $request->input('email_address');
        $user_id = $this->user_id;
        $logged_in_user = $request->input('logged_in_user');
        $submission_reason = $request->input('submission_reason');
        $special_comments = $request->input('special_comments');
        $ht_approved = $request->input('ht_approved');
        $submitted_for_verification = $request->input('submitted_for_verification');
        $ht_submission_reason = $request->input('ht_submission_reason');
        $access_point_id = $request->input('access_point_id');
        DB::beginTransaction();
        try {
            $record_details = DB::table($table_name)
                ->where('id', $record_id)
                ->first();
            if (is_null($record_details)) {
                $res = array(
                    'success' => false,
                    'message' => 'Problem encountered while fetching record details!!'
                );
                return \response()->json($res);
            }
            
            $urgent_details = DB::table('case_general_health_details')->where('case_id', $record_id)->first();
            if($urgent_details){
                if($urgent_details->require_urgent_protection == 'Yes' || $urgent_details->require_urgent_protection === 'Yes'){
                    $urgent_template = 1;
                }else{
                    $urgent_template = 0;                
                }
            }else{
                $urgent_template = 0; 
            }
            $file_no = $record_details->case_file_number;
            $mis_no = $record_details->mis_no;
            $target_group = $record_details->target_group_id;
            $created_at = $record_details->created_at;
            $nxt_stage_id = $prev_stage == 8 ? $prev_stage : $prev_stage + 1;
            $workflow_stage = getSingleRecordColValue('case_workflow_stages', array('id' => $prev_stage), 'name');
            $where = array(
                'id' => $record_id
            );
            
            if (validateisNumeric($ht_submission_reason) && $ht_submission_reason == 2) {//next stage
                if ($to_stage == 9) {
                    $to_workflow_stage = getSingleRecordColValue('case_workflow_stages', array('id' => $nxt_stage_id), 'name');
                    $app_update = array(
                        'access_point_id' => $access_point_id,                
                        'workflow_stage_id' => $to_stage,
                        'submitted_for_verification' => 0,
                        'ht_approved' => 0,//$ht_approved,
                        'curr_from_userid' => $user_id,
                        'curr_to_userid' => $responsible_user,
                        'record_status_id' => 2,//case closed
                        'current_stage_entry_date' => Carbon::now()
                    );
                } else {
                    $to_workflow_stage = getSingleRecordColValue('case_workflow_stages', array('id' => $nxt_stage_id), 'name');
                    $app_update = array(
                        'access_point_id' => $access_point_id,                
                        'workflow_stage_id' => $to_stage,
                        'submitted_for_verification' => 0,
                        'ht_approved' => 0,//$ht_approved,
                        'curr_from_userid' => $user_id,
                        'curr_to_userid' => $responsible_user,
                        'current_stage_entry_date' => Carbon::now()
                    );
                }
            } else if (validateisNumeric($ht_submission_reason) && $ht_submission_reason == 3) {//administrative stages
                $to_workflow_stage = $workflow_stage;
                $app_update = array(
                    'curr_from_userid' => $user_id,
                    'access_point_id' => $user_access_point - 1,
                    'curr_to_userid' => $responsible_user,
                    'submitted_for_verification' => 1,
                    'ht_approved' => 1,
                    'current_stage_entry_date' => Carbon::now()
                );
            } else {//Return to HT
                $to_workflow_stage = $workflow_stage;
                if($submission_lvl == 4){// from HQ
                    $app_update = array(
                        'access_point_id' => 3,
                        'submitted_for_verification' => 1,
                        'ht_approved' => 0,//$ht_approved,
                        'curr_from_userid' => $user_id,
                        'curr_to_userid' => $responsible_user,
                        'current_stage_entry_date' => Carbon::now()
                    );
                } else
                if($submission_lvl == 3){// from province
                    $app_update = array(
                        'access_point_id' => 4,
                        'submitted_for_verification' => 1,
                        'ht_approved' => 0,//$ht_approved,
                        'curr_from_userid' => $user_id,
                        'curr_to_userid' => $responsible_user,
                        'current_stage_entry_date' => Carbon::now()
                    );
                } else if($submission_lvl == 2){// from district
                    $app_update = array(
                        'access_point_id' => 5,
                        'submitted_for_verification' => 1,
                        'ht_approved' => 1,//$ht_approved,
                        'curr_from_userid' => $user_id,
                        'curr_to_userid' => $responsible_user,
                        'current_stage_entry_date' => Carbon::now()
                    );
                } else {
                    $app_update = array(
                        // 'visible_atschool_level' => 0,
                        // 'validation_status' => 0,
                        // 'access_point_id' => $access_point_id,
                        'access_point_id' => 5,
                        'submitted_for_verification' => $submitted_for_verification,
                        'ht_approved' => $ht_approved,
                        'curr_from_userid' => $user_id,
                        'curr_to_userid' => $responsible_user,
                        'current_stage_entry_date' => Carbon::now()
                    );
                }
            }
            $prev_data = getPreviousRecords($table_name, $where);
            $update_res = updateRecord($table_name, $prev_data, $where, $app_update, $user_id);
            if ($update_res['success'] == false) {
                return \response()->json($update_res);
            }
            //notifications
            $notification_details = array(
                'record_id' => $record_id,
                'notification_type_id' => 1,
                'notification_date' => Carbon::now(),
                'status' => 1
            );
            DB::table('case_notifications')->insert($notification_details);
            $transition_params = array(
                'record_id' => $record_id,
                'process_id' => $process_id,
                'from_stage' => $prev_stage,
                'to_stage' => $prev_stage,
                'from_user' => $user_id,
                'to_user' => $responsible_user,
                'author' => $user_id,
                'remarks' => $remarks,
                'created_at' => Carbon::now(),
                'created_by' => $user_id
            );
            DB::table('records_workflow_transitions')->insert($transition_params);
            DB::table('case_transitionsubmissions_details')->insert($form_data);
            DB::commit();
            //Mail notification for CMS Expert
            if (is_connected()) {
                $vars = array(
                    '{workflow_stage}' => $workflow_stage,
                    '{submitted_at}' =>  Carbon::now()->format('d/m/Y'),
                    '{target_group}' => $target_group,
                    '{from_stage}' => $workflow_stage,
                    '{created_at}' => Carbon::parse($created_at)->format('d/m/Y'),
                    '{user_id}' => $logged_in_user,
                    '{to_stage}' => $to_workflow_stage,
                    '{file_no}' => $file_no,
                    '{remarks}' => $special_comments,
                    '{mis_no}' => $mis_no
                );
                if($urgent_template == 1){
                    $emailTemplateInfo = getEmailTemplateInfo(7, $vars);
                }else{
                    $emailTemplateInfo = getEmailTemplateInfo(6, $vars);
                }
                $emailJobOne = (new GenericSendEmailJob('rodgers.ch2@gmail.com', $emailTemplateInfo->subject, $emailTemplateInfo->body, array()))->delay(Carbon::now()->addSeconds(10));
                dispatch($emailJobOne);
                $emailJobTwo = (new GenericSendEmailJob('david.mbao@edu.gov.zm', $emailTemplateInfo->subject, $emailTemplateInfo->body, array()))->delay(Carbon::now()->addSeconds(10));
                dispatch($emailJobTwo);
                // $emailJobThree = (new GenericSendEmailJob('franklin.otieno@softclans.co.ke', $emailTemplateInfo->subject, $emailTemplateInfo->body, array()))->delay(Carbon::now()->addSeconds(10));
                // dispatch($emailJobThree);
                $emailJobFour = (new GenericSendEmailJob($email_address, $emailTemplateInfo->subject, $emailTemplateInfo->body, array()))->delay(Carbon::now()->addSeconds(10));
                dispatch($emailJobFour);
            }
            $res = array(
                'success' => true,
                'message' => 'Record submitted successfully!!'
            );
        } catch (\Exception $exception) {
            DB::rollBack();
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            DB::rollBack();
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return \response()->json($res);
    }
    
    public function processCaseRecordClosureSubmission(Request $request)
    {//For G&C teacher
        $form_data = $request->all();
        unset($form_data['table_name']);
        unset($form_data['storeID']);
        unset($form_data['ht_approved']);
        unset($form_data['nextstage_id']);

        $record_id = $request->input('record_id');
        $process_id = $request->input('process_id');
        $table_name = $request->input('table_name');
        $prev_stage = $request->input('prevstage_id');
        $to_stage = $request->input('nextstage_id');
        $remarks = $request->input('remarks');
        $responsible_user = $request->input('responsible_user');
        $email_notification = $request->input('email_notification');
        $email_address = $request->input('email_address');
        $user_id = $this->user_id;
        $logged_in_user = $request->input('logged_in_user');
        $submission_reason = $request->input('submission_reason');
        $special_comments = $request->input('special_comments');
        $ht_approved = $request->input('ht_approved');
        $submitted_for_verification = $request->input('submitted_for_verification');
        $ht_submission_reason = $request->input('ht_submission_reason');

        DB::beginTransaction();
        try {
            $record_details = DB::table($table_name)
                ->where('id', $record_id)
                ->first();
            if (is_null($record_details)) {
                $res = array(
                    'success' => false,
                    'message' => 'Problem encountered while fetching record details!!'
                );
                return \response()->json($res);
            }
            
            $file_no = $record_details->case_file_number;
            $mis_no = $record_details->mis_no;
            $target_group = $record_details->target_group_id;
            $created_at = $record_details->created_at;
            $workflow_stage = getSingleRecordColValue('case_workflow_stages', array('id' => $prev_stage), 'name');
            $where = array(
                'id' => $record_id
            );
            
            if (validateisNumeric($ht_submission_reason) && $ht_submission_reason == 2) {
                $app_update = array(                    
                    'workflow_stage_id' => $to_stage,
                    'submitted_for_verification' => $submitted_for_verification,
                    'ht_approved' => $ht_approved,
                    'curr_from_userid' => $user_id,
                    'curr_to_userid' => $responsible_user,
                    'current_stage_entry_date' => Carbon::now()
                );
            } else {                
                $app_update = array(
                    // 'visible_atschool_level' => 0,
                    // 'validation_status' => 0,
                    'submitted_for_verification' => $submitted_for_verification,
                    'ht_approved' => $ht_approved,
                    'curr_from_userid' => $user_id,
                    'curr_to_userid' => $responsible_user,
                    'current_stage_entry_date' => Carbon::now()
                );
            }
            $prev_data = getPreviousRecords($table_name, $where);
            $update_res = updateRecord($table_name, $prev_data, $where, $app_update, $user_id);
            if ($update_res['success'] == false) {
                return \response()->json($update_res);
            }
            //notifications
            $notification_details = array(
                'record_id' => $record_id,
                'notification_type_id' => 1,
                'notification_date' => Carbon::now(),
                'status' => 1
            );
            DB::table('case_notifications')->insert($notification_details);
            $transition_params = array(
                'record_id' => $record_id,
                'process_id' => $process_id,
                'from_stage' => $prev_stage,
                'to_stage' => $prev_stage,
                'from_user' => $user_id,
                'to_user' => $responsible_user,
                'author' => $user_id,
                'remarks' => $remarks,
                'created_at' => Carbon::now(),
                'created_by' => $user_id
            );
            DB::table('records_workflow_transitions')->insert($transition_params);
            DB::table('case_transitionsubmissions_details')->insert($form_data);
            DB::commit();
            //email
            if ($email_notification == 1) {
                if (is_connected()) {
                    $vars = array(
                        '{workflow_stage}' => $workflow_stage,
                        '{submitted_at}' =>  Carbon::now()->format('d/m/Y'),
                        '{target_group}' => $target_group,
                        '{from_stage}' => $workflow_stage,
                        '{created_at}' => Carbon::parse($created_at)->format('d/m/Y'),
                        '{user_id}' => $logged_in_user,
                        '{to_stage}' => $workflow_stage,
                        '{file_no}' => $file_no,
                        '{remarks}' => $special_comments,
                        '{mis_no}' => $mis_no
                    );
                    $emailTemplateInfo = getEmailTemplateInfo(6, $vars);
                    $emailJob = (new GenericSendEmailJob($email_address, $emailTemplateInfo->subject, $emailTemplateInfo->body, array()))->delay(Carbon::now()->addSeconds(10));
                    dispatch($emailJob);
                }
            }
            $workflow_stage = 'Case Intake/Profiling';
            //Mail notification for CMS Expert
            if (is_connected()) {
                $vars = array(
                    '{workflow_stage}' => $workflow_stage,
                    '{submitted_at}' =>  Carbon::now()->format('d/m/Y'),
                    '{target_group}' => $target_group,
                    '{from_stage}' => $workflow_stage,
                    '{created_at}' => Carbon::parse($created_at)->format('d/m/Y'),
                    '{user_id}' => $logged_in_user,
                    '{to_stage}' => $workflow_stage,
                    '{file_no}' => $file_no,
                    '{remarks}' => $special_comments,
                    '{mis_no}' => $mis_no
                );                
                $emailTemplateInfo = getEmailTemplateInfo(6, $vars);
                $emailJob = (new GenericSendEmailJob('rodgers.ch2@gmail.com', $emailTemplateInfo->subject, $emailTemplateInfo->body, array()))->delay(Carbon::now()->addSeconds(10));
                dispatch($emailJob);
                $emailJob = (new GenericSendEmailJob('david.mbao@edu.gov.zm', $emailTemplateInfo->subject, $emailTemplateInfo->body, array()))->delay(Carbon::now()->addSeconds(10));
                dispatch($emailJob);
                // $emailJob = (new GenericSendEmailJob('franklin.otieno@softclans.co.ke', $emailTemplateInfo->subject, $emailTemplateInfo->body, array()))->delay(Carbon::now()->addSeconds(10));
                // dispatch($emailJob);
            }
            $res = array(
                'success' => true,
                'message' => 'Record submitted successfully!!'
            );
        } catch (\Exception $exception) {
            DB::rollBack();
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            DB::rollBack();
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return \response()->json($res);
    }

    public function getCaseWarningSigns(Request $request)
    {
        try {
            $qry = DB::table('case_earlywarningsign_indicators as t1')
                ->leftJoin('case_overal_indicators as t2', 't1.overal_indicator_id', '=', 't2.id')
                ->selectRaw('t1.id,t1.overal_indicator_id,t1.name as description,t2.name as overal_indicator');
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

    public function getNextCaseWorkflowStageDetails(Request $request)
    {
        try {
            $current_stage_id = $request->input('workflow_stage_id');
            $submission_type = $request->input('submission_type');
            $op = '+';
            if (validateisNumeric($submission_type) && $submission_type == 2) {
                $op = '-';
            }
            $qry = DB::table('case_workflow_stages as t1')
                ->where('t1.order', function ($query) use ($current_stage_id, $op) {
                    $query->select(DB::raw("t2.order $op 1"))
                        ->from('case_workflow_stages as t2')
                        ->where('t2.id', $current_stage_id);
                });
            $nextStageId = $qry->value('t1.id');
            $res = array(
                'success' => true,
                'nextStageId' => $nextStageId
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

    public function getSupervisorComments(Request $request)
    {
        try {
            $filters = $request->input('filters');
            $filters = (array)json_decode($filters);
            $qry = DB::table('case_comments_personel as t1')
                ->leftJoin('case_supervisor_comments as t2', function ($join) use ($filters) {
                    $join->on('t1.id', '=', 't2.personel_title')->where($filters);
                })
                ->select(DB::raw("t2.id,t1.id as personel_title,t1.name,t2.personel_name,t2.comments,t2.collected_on"));
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

    public function saveSupervisorComments(Request $request)
    {
        try {
            $case_id = $request->input('case_id');
            $comment_details = $request->input('comment_details');
            $data = json_decode($comment_details, true);
            $table_name = 'case_supervisor_comments';
            foreach ($data as $key => $value) {
                $table_data = array(
                    'case_id' => $case_id,
                    'personel_title' => $value['personel_title'],
                    'personel_name' => $value['personel_name'],
                    'comments' => $value['comments'],
                    'collected_on' => $value['collected_on']
                );
                //where data
                $where_data = array(
                    'id' => $value['id']
                );
                if (recordExists($table_name, $where_data)) {
                    $table_data['updated_by'] = $this->user_id;
                    DB::table($table_name)
                        ->where($where_data)
                        ->update($table_data);
                } else {
                    $table_data['created_by'] = $this->user_id;
                    DB::table($table_name)
                        ->insert($table_data);
                }
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

    public function saveCaseEarlyWarningSignInfo(Request $request)
    {
        try {
            $user_id = $this->user_id;
            $id = $request->input('id');
            $case_id = $request->input('case_id');
            $year_id = $request->input('year_id');
            $table_name = 'case_earlywarningsigns_info';$record_id = 0;
            $where = array(
                'case_id' => $case_id
            );
            $table_data = array(
                'case_id' => $case_id,'personel_email' => $request->input('personel_email'),
                'year_id' => $year_id,'ews_info_id' => $request->input('ews_info_id'),
                'remarks' => $request->input('remarks'),'ews_date' => $request->input('ews_date'),
                'store_id' => $request->input('store_id'),'folder_id' => $request->input('folder_id'),
                'school_id' => $request->input('school_id'),'process_id' => $request->input('process_id'),
                'position_id' => $request->input('position_id'),'current_grade' => $request->input('current_grade'),
                'other_position' => $request->input('other_position'),'girl_last_name' => $request->input('girl_last_name'),
                'beneficiary_id' => $request->input('beneficiary_id'),'girl_first_name' => $request->input('girl_first_name'),
                'dob' => $request->input('dob'),'person_filling_district_id' => $request->input('person_filling_district_id'),
                'case_mis_number' => $request->input('case_mis_number'),'workflow_stage_id' => $request->input('workflow_stage_id'),
                'personel_mobile_no' => $request->input('personel_mobile_no'),'personel_last_name' => $request->input('personel_last_name'),
                'personel_first_name' => $request->input('personel_first_name'),'personel_designation_id' => $request->input('personel_designation_id'),
                'personel_district_id' => $request->input('personel_district_id'),
                'created_by' => $user_id,'district_id' => $request->input('district_id'),'mis_no' => $request->input('mis_no'),
                'created_at' => Carbon::now()
            );

            if (isset($case_id) && $case_id != "" && recordExists($table_name, $where)) {
                unset($table_data['created_at']);
                unset($table_data['created_by']);
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $previous_data = getPreviousRecords($table_name, $where);
                $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                $record_id = $previous_data[0]['id'];
            } else {
                $record_id = DB::table($table_name)->insertGetId($table_data);
            }
            $res = array(
                'success' => true,
                'record_id' => $record_id,
                'message' => 'Details saved successfully!!'
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

    public function getCaseEarlyWarningSignsInfo(Request $request)
    {
        try {
            $case_id = $request->input('case_id');
            $qry = DB::table('case_earlywarningsigns_info as t1')
                ->leftJoin('case_earlywarningsign_details as t2', 't1.id', '=', 't2.ews_info_id')
                ->leftJoin('case_personel_designation as t3', 't1.position_id', '=', 't3.id')
                ->select(DB::raw("t1.*,CONCAT_WS(' ',t1.personel_first_name,t1.personel_last_name) as captured_by,t3.name as personel_designation,
                    (SUM(IF(t2.first_term_score=1,1,0))+SUM(IF(t2.second_term_score=1,1,0))+SUM(IF(t2.third_term_score=1,1,0))) AS total_score"))
                ->where('t1.case_id', $case_id)
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

    public function getRecordedWarningSignsDetails1(Request $request)
    {
        try {
            $year_id = $request->input('selected_year_id');
            $case_id = $request->input('record_id');
            $whereArray = array(
                'year_id' => $year_id,
                'case_id' => $case_id
            );
            $qry = DB::table('case_overal_indicators as t1')
                ->leftJoin('case_earlywarningsign_indicators as t2', 't1.id', '=', 't2.overal_indicator_id')
                ->leftJoin('case_earlywarningsign_details as t3', function ($join) use ($whereArray) {
                    $join->on('t2.id', '=', 't3.warning_sign_id')
                        ->where($whereArray);
                })
                ->select(DB::raw("t1.id as overal_indicator_id,t1.name as overal_indicator,t2.id as warning_sign_id,t2.name as warning_sign,
                        t3.first_term_score,t3.second_term_score,t3.third_term_score,t3.year_id,t3.id"));
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

    public function getRecordedWarningSignsDetails(Request $request)
    {
        try {
            $record_id = $request->input('record_id');
            $selected_year_id = $request->input('selected_year_id');
            $ews_info_id = $request->input('ews_info_id');
            $overall_indicator_id = $request->input('overall_indicator_id');
            $viewMarked = $request->input('viewMarkedOnly');
            $qry = DB::table('case_earlywarningsign_indicators as t1')
                ->join('case_overal_indicators as t2', 't1.overal_indicator_id', '=', 't2.id')
                ->leftJoin('case_earlywarningsign_details as t3', function ($join) use ($ews_info_id) {
                    $join->on('t1.id', '=', 't3.warning_sign_id')
                        ->where('t3.ews_info_id', $ews_info_id);
                })
                ->select(DB::raw("t1.id,t2.id as overal_indicator_id,t2.name as overal_indicator,t1.id as warning_sign_id,t1.name as warning_sign,
                        t3.first_term_score,t3.second_term_score,t3.third_term_score,t3.id as rec_id"));//->limit(20);
            if (validateisNumeric($overall_indicator_id)) {
                $qry->where('t1.overal_indicator_id', $overall_indicator_id);
            }
            if ($viewMarked == 1 || $viewMarked === 'true') {
                $qry->where(function ($query) {
                    $query->where('t3.first_term_score', 1)
                        ->orWhere('t3.second_term_score', 1)
                        ->orWhere('t3.third_term_score', 1);
                });
            }
            if (!$ews_info_id || $ews_info_id == null) {
                $qry->where(function ($query) {
                    $query->where('t3.first_term_score', '<>', 1)
                        ->Where('t3.second_term_score', '<>', 1)
                        ->Where('t3.third_term_score', '<>', 1);
                });
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

    public function saveSelectedWarningSigns(Request $request)
    {
        try {
            $ews_info_id = $request->input('ews_info_id');
            $record_id = $request->input('record_id');
            $selected_signs = $request->input('selected_signs');
            $selectedArray = json_decode($selected_signs, true);
            $table_name = 'case_earlywarningsign_details';
            $submit_to_assessment = 0;
            $ews_score = 0;$score_across = 0;
            foreach ($selectedArray as $key => $value) {
                $table_data = array(
                    'ews_info_id' => $ews_info_id,
                    'warning_sign_id' => $value['warning_sign_id'],
                    'first_term_score' => $value['first_term_score'],
                    'second_term_score' => $value['second_term_score'],
                    'third_term_score' => $value['third_term_score']
                );             
                $overal_indicator_id = $value['overal_indicator_id'];
                //where data
                $where_data = array(
                    'id' => $value['rec_id']
                );
                if (recordExists($table_name, $where_data)) {
                    $table_data['updated_by'] = $this->user_id;
                    DB::table($table_name)
                        ->where($where_data)
                        ->update($table_data);
                } else {
                    $table_data['created_by'] = $this->user_id;
                    DB::table($table_name)->insert($table_data);
                }
            }            
            $score_qry = DB::table('case_earlywarningsign_indicators as t1')
                ->join('case_overal_indicators as t2', 't1.overal_indicator_id', '=', 't2.id')
                ->join('case_earlywarningsign_details as t3', function ($join) use ($ews_info_id) {
                    $join->on('t1.id', '=', 't3.warning_sign_id')
                        ->where('t3.ews_info_id', $ews_info_id);
                })
                ->select(DB::raw("COUNT(DISTINCT t2.id) as score_across"));
            $qry_resp = $score_qry->get()->toArray();
            $score_across_from_db = $qry_resp[0]->score_across;
            if($score_across_from_db >= 2) {
                $submit_to_assessment = 1;
                $process_id = 0;$prev_stage = 2;
                $to_stage = 3;$email_notification = 1;
                $user_id = $this->user_id;
                $logged_in_user = $this->user_id;
                $table_name = 'case_basicdataentry_details';
                $remarks = 'Automatic Submission via the Early Warning Signs Tool';
                $submission_reason = 'Automatic Submission via the Early Warning Signs Tool';
                $special_comments = 'Automatic Submission via the Early Warning Signs Tool';        
                // DB::beginTransaction();
                $record_details = DB::table($table_name)
                    ->where('id', $record_id)
                    ->first();
                if (is_null($record_details)) {
                    $res = array(
                        'success' => false,
                        'message' => 'Problem encountered while fetching record details!!'
                    );
                    return \response()->json($res);
                }            
                $file_no = $record_details->case_file_number;
                $mis_no = $record_details->mis_no;
                $target_group = $record_details->target_group_id;
                $created_at = $record_details->created_at;
                $headTeacherMail = $record_details->ht_email;
                $workflow_stage = getSingleRecordColValue('case_workflow_stages', array('id' => $prev_stage), 'name');
                $personel_email = getSingleRecordColValue('case_earlywarningsigns_info', array('case_id' => $record_id), 'personel_email');
                $where = array(
                    'id' => $record_id
                );
                $app_update = array(
                    'access_point_id' => 5,                  
                    'workflow_stage_id' => $to_stage,
                    'submitted_for_verification' => 0,
                    'ht_approved' => 0,
                    'curr_from_userid' => $user_id,
                    // 'curr_to_userid' => $responsible_user,
                    'current_stage_entry_date' => Carbon::now()
                );
                $prev_data = getPreviousRecords($table_name, $where);
                $update_res = updateRecord($table_name, $prev_data, $where, $app_update, $user_id);
                if ($update_res['success'] == false) {
                    return \response()->json($update_res);
                }
                //notifications
                $notification_details = array(
                    'record_id' => $record_id,
                    'notification_type_id' => 1,
                    'notification_date' => Carbon::now(),
                    'status' => 1
                );
                DB::table('case_notifications')->insert($notification_details);
                $transition_params = array(
                    'record_id' => $record_id,
                    'process_id' => $process_id,
                    'from_stage' => $prev_stage,
                    'to_stage' => $prev_stage,
                    'from_user' => $user_id,
                    // 'to_user' => $responsible_user,
                    'author' => $user_id,
                    'remarks' => $remarks,
                    'created_at' => Carbon::now(),
                    'created_by' => $user_id
                );
                DB::table('records_workflow_transitions')->insert($transition_params);
                // DB::commit();
                //email
                $workflow_stage = 'Case Intake/Profiling';
                //Mail notification for CMS Expert
                if (is_connected()) {
                    $vars = array(
                        '{workflow_stage}' => $workflow_stage,
                        '{submitted_at}' =>  Carbon::now()->format('d/m/Y'),
                        '{target_group}' => $target_group,
                        '{from_stage}' => $workflow_stage,
                        '{created_at}' => Carbon::parse($created_at)->format('d/m/Y'),
                        '{user_id}' => $logged_in_user,
                        '{to_stage}' => $workflow_stage,
                        '{file_no}' => $file_no,
                        '{remarks}' => $special_comments,
                        '{mis_no}' => $mis_no
                    );                
                    $emailTemplateInfo = getEmailTemplateInfo(6, $vars);
                    if($headTeacherMail) {
                        $emailJob = (new GenericSendEmailJob($headTeacherMail, $emailTemplateInfo->subject, $emailTemplateInfo->body, array()))->delay(Carbon::now()->addSeconds(10));
                        dispatch($emailJob);
                    }
                    $emailJob = (new GenericSendEmailJob('rodgers.ch2@gmail.com', $emailTemplateInfo->subject, $emailTemplateInfo->body, array()))->delay(Carbon::now()->addSeconds(10));
                    dispatch($emailJob);
                    $emailJob = (new GenericSendEmailJob('david.mbao@edu.gov.zm', $emailTemplateInfo->subject, $emailTemplateInfo->body, array()))->delay(Carbon::now()->addSeconds(10));
                    dispatch($emailJob);
                    // $emailJob = (new GenericSendEmailJob('franklin.otieno@softclans.co.ke', $emailTemplateInfo->subject, $emailTemplateInfo->body, array()))->delay(Carbon::now()->addSeconds(10));
                    // dispatch($emailJob);
                }
            } else {
                $submit_to_assessment = 0;
            }
            $res = array(
                'success' => true,
                'submit_to_assessment' => $submit_to_assessment,
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

    public function saveWarningSignsRemarkInfo(Request $request)
    {
        try {
            $remarks = $request->input('remarks');
            $selectedRemarks = json_decode($remarks, true);
            $table_name = 'case_earlywarningsigns_info';
            foreach ($selectedRemarks as $key => $value) {
                $table_data = array(
                    'remarks' => $value['remarks']
                );
                //where data
                $where_data = array(
                    'id' => $value['id']
                );
                if (recordExists($table_name, $where_data)) {
                    $table_data['updated_by'] = $this->user_id;
                    DB::table($table_name)
                        ->where($where_data)
                        ->update($table_data);
                } else {
                    $table_data['created_by'] = $this->user_id;
                    DB::table($table_name)->insert($table_data);
                }
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
    
    public function saveCaseAssessmentBasicDetailsFrm(Request $request)
    {
        try {
            $formdata = $request->all();
            $id = $request->input('id');
            $user_id = $this->user_id;
            // $table_name = 'case_assessmentbasic_info';
            $table_name = $request->input('table_name');
            unset($formdata['table_name']);
            if ($id != '' && isset($id)) {//Edit
                $table_data = $formdata;
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $where = array(
                    'id' => $id
                );
                //get previous_data
                $previous_data = getPreviousRecords($table_name, $where);
                if($previous_data){
                    $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                } else {//new insert
                    $table_data = $formdata;
                    unset($table_data['id']);
                    $table_data['created_at'] = Carbon::now();
                    $table_data['created_by'] = $user_id;
                    $res = insertRecord($table_name, $table_data, $user_id);
                }
            } else {//new insert
                $table_data = $formdata;
                unset($table_data['id']);
                $table_data['created_at'] = Carbon::now();
                $table_data['created_by'] = $user_id;
                $res = insertRecord($table_name, $table_data, $user_id);
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
    
    public function saveCaseReferralBasicDetailsFrm(Request $request)
    {
        try {
            $formdata = $request->all();
            $id = $request->input('id');
            $case_id = $request->input('case_id');
            $user_id = $this->user_id;
            // $table_name = 'case_assessmentbasic_info';
            $table_name = $request->input('table_name');
            unset($formdata['table_name']);
            if ($id != '' && isset($id)) {//Edit
                $table_data = $formdata;
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $where = array(
                    'case_id' => $case_id
                );
                //get previous_data
                $previous_data = getPreviousRecords($table_name, $where);
                if($previous_data){
                    $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                } else {//new insert
                    $table_data = $formdata;
                    unset($table_data['id']);
                    $table_data['created_at'] = Carbon::now();
                    $table_data['created_by'] = $user_id;
                    $res = insertRecord($table_name, $table_data, $user_id);
                }
            } else {//new insert
                $table_data = $formdata;
                unset($table_data['id']);
                $table_data['created_at'] = Carbon::now();
                $table_data['created_by'] = $user_id;
                $res = insertRecord($table_name, $table_data, $user_id);
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
    
    public function saveCaseCarePlanBasicDetailsFrm(Request $request)
    {
        try {
            $formdata = $request->all();
            $id = $request->input('id');
            $case_id = $request->input('case_id');
            $user_id = $this->user_id;
            $table_name = $request->input('table_name');
            unset($formdata['table_name']);
            unset($formdata['id']);
            if ($id != '' && isset($id)){//Edit
                $table_data = $formdata;
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $where = array(
                    'case_id' => $case_id
                );
                //get previous_data
                $previous_data = getPreviousRecords($table_name, $where);
                if($previous_data){
                    $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                } else {//new insert
                    $table_data = $formdata;
                    unset($table_data['id']);
                    $table_data['created_at'] = Carbon::now();
                    $table_data['created_by'] = $user_id;
                    $res = insertRecord($table_name, $table_data, $user_id);
                }
            } else {//new insert
                $table_data = $formdata;
                unset($table_data['id']);
                $table_data['created_at'] = Carbon::now();
                $table_data['created_by'] = $user_id;
                $res = insertRecord($table_name, $table_data, $user_id);
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
    
    public function saveCaseParentDetailsFrm(Request $request)
    {
        try {
            $formdata = $request->all();
            $id = $request->input('id');
            $user_id = $this->user_id;
            $table_name = 'case_assessment_parent_info';
            if ($id != '' && isset($id)) {//Edit
                $table_data = $formdata;
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $where = array(
                    'id' => $id
                );
                //get previous_data
                $previous_data = getPreviousRecords($table_name, $where);
                if($previous_data){
                    $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                } else {//new insert
                    $table_data = $formdata;
                    unset($table_data['id']);
                    $table_data['created_at'] = Carbon::now();
                    $table_data['created_by'] = $user_id;
                    $res = insertRecord($table_name, $table_data, $user_id);
                }
            } else {//new insert
                $table_data = $formdata;
                unset($table_data['id']);
                $table_data['created_at'] = Carbon::now();
                $table_data['created_by'] = $user_id;
                $res = insertRecord($table_name, $table_data, $user_id);
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
    
    public function saveGeneralHealthAndHiegienFrm(Request $request)
    {
        try {
            $formdata = $request->all();
            $id = $request->input('id');
            $user_id = $this->user_id;
            $table_name = 'case_general_health_details';
            if ($id != '' && isset($id)) {//Edit
                $table_data = $formdata;
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $where = array(
                    'id' => $id
                );
                //get previous_data
                $previous_data = getPreviousRecords($table_name, $where);
                if($previous_data){
                    $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                } else {//new insert
                    $table_data = $formdata;
                    unset($table_data['id']);
                    $table_data['created_at'] = Carbon::now();
                    $table_data['created_by'] = $user_id;
                    $res = insertRecord($table_name, $table_data, $user_id);
                }
            } else {//new insert
                $table_data = $formdata;
                unset($table_data['id']);
                $table_data['created_at'] = Carbon::now();
                $table_data['created_by'] = $user_id;
                $res = insertRecord($table_name, $table_data, $user_id);
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

    //start kpis
    public function getCaseKpiNumberOfGrmQueries(Request $request)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $record_status_id = $request->input('record_status_id');
        try {
            if (validateisNumeric($record_status_id)) {
                $totalComplaintsQry = DB::table('grm_complaint_details')->selectRaw('COUNT(id) as number')
                    ->where('record_status_id', $record_status_id)->first();
            } else {
                $totalComplaintsQry = DB::table('grm_complaint_details')->selectRaw('COUNT(id) as number')->first();
            }
            $qry = DB::table('grm_complaint_details as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->join('provinces as t3', 't2.province_id', '=', 't3.id')
                ->selectRaw("t2.name as district_name,t3.name as province_name,t2.province_id,
                    sum(IF(t1.complainant_age between 1 and 9,1,0)) as firstAgeBracket,
                    ROUND(sum(IF(t1.complainant_age between 1 and 9,1,0)/" . $totalComplaintsQry->number . ")*100,2) as first_percentage_count,
                    sum(IF(t1.complainant_age between 10 and 14,1,0)) as secondAgeBracket,
                    ROUND(sum(IF(t1.complainant_age between 10 and 14,1,0)/" . $totalComplaintsQry->number . ")*100,2) as second_percentage_count,
                    sum(IF(t1.complainant_age between 15 and 17,1,0)) as thirdAgeBracket,
                    ROUND(sum(IF(t1.complainant_age between 15 and 17,1,0)/" . $totalComplaintsQry->number . ")*100,2) as third_percentage_count,
                    sum(IF(t1.complainant_age between 18 and 24,1,0)) as fourthAgeBracket,
                    ROUND(sum(IF(t1.complainant_age between 18 and 24,1,0)/" . $totalComplaintsQry->number . ")*100,2) as fourth_percentage_count,
                    sum(IF(t1.complainant_age > 25,1,0)) as fifthAgeBracket,
                    ROUND(sum(IF(t1.complainant_age > 25,1,0)/" . $totalComplaintsQry->number . ")*100,2) as fifth_percentage_count")
                ->groupBy('t1.district_id');
            if (validateisNumeric($record_status_id)) {
                $qry->where('t1.record_status_id', $record_status_id);
            }
            if (validateisNumeric($province_id)) {
                $qry->where('t1.province_id', $province_id);
            }
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

    public function getKgsBursaryInvitesInSchool(Request $request)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $currentYear = $request->input('currentYear');
        try {
            $totalRecordsQry = DB::table('grm_complaint_details')->selectRaw('COUNT(DISTINCT id) as number')->first();
            $qry = DB::table('beneficiary_information as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->join('provinces as t3', 't2.province_id', '=', 't3.id')
                ->join('beneficiary_enrollments as t4', 't1.id', '=', 't4.beneficiary_id')
                ->selectRaw("t2.name as district_name,t3.name as province_name,t2.province_id,
                    sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t1.dob') . ",NOW()) between 1 and 9,1,0)) as firstAgeBracket,
                    ROUND((sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t1.dob') . ",NOW()) between 1 and 9,1,0))/" . $totalRecordsQry->number . ")*100,2) as first_percentage_count,
                    sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t1.dob') . ",NOW()) between 10 and 14,1,0)) as secondAgeBracket,
                    ROUND((sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t1.dob') . ",NOW()) between 10 and 14,1,0))/" . $totalRecordsQry->number . ")*100,2) as second_percentage_count,
                    sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t1.dob') . ",NOW()) between 15 and 17,1,0)) as thirdAgeBracket,
                    ROUND((sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t1.dob') . ",NOW()) between 15 and 17,1,0))/" . $totalRecordsQry->number . ")*100,2) as third_percentage_count,
                    sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t1.dob') . ",NOW()) between 18 and 24,1,0)) as fourthAgeBracket,
                    ROUND((sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t1.dob') . ",NOW()) between 18 and 24,1,0))/" . $totalRecordsQry->number . ")*100,2) as fourth_percentage_count,
                    sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t1.dob') . ",NOW()) >25,1,0)) as fifthAgeBracket,
                    ROUND((sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t1.dob') . ",NOW()) > 25,1,0)) /" . $totalRecordsQry->number . ")*100,2) as fifth_percentage_count")
                ->where('t4.year_of_enrollment', $currentYear)
                ->groupBy('t1.district_id');
            if (validateisNumeric($province_id)) {
                $qry->where('t1.province_id', $province_id);
            }
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

    public function formatDOB($dob)
    {
        return "IFNULL(
            DATE_FORMAT(STR_TO_DATE(" . $dob . ", '%d/%m/%Y'), '%Y-%m-%d'),
            IFNULL(
                DATE_FORMAT(STR_TO_DATE(" . $dob . ", '%d.%m.%Y'), '%Y-%m-%d'),
                DATE_FORMAT(STR_TO_DATE(" . $dob . ", '%Y-%m-%d'), '%Y-%m-%d')
            )
        )";
    }

    public function updateDOB()
    {
        $all = DB::table('beneficiary_information as t1')
            ->where('t1.kip', '<>', 1)
            ->limit(10000)
            ->get();
        foreach ($all as $item) {
            DB::table('beneficiary_information as t2')
                ->where('t2.id', $item->id)
                ->where('t2.kip', '<>', 1)
                ->update(array('t2.dob2' => converter11($item->dob), 'kip' => 1));
        }
    }

    public function getBursaryInvitesAndGraduates(Request $request)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $whereColumn = $request->input('where_column');
        try {
            $totalRecordsQry = DB::table('beneficiary_information')->selectRaw('COUNT(id) as number')
                ->where($whereColumn, 4)->first();
            $qry = DB::table('beneficiary_information as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->join('provinces as t3', 't2.province_id', '=', 't3.id')
                ->selectRaw("t2.name as district_name,t3.name as province_name,t2.province_id,
                    sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t1.dob') . ",NOW()) between 1 and 9,1,0)) as firstAgeBracket,
                    ROUND((sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t1.dob') . ",NOW()) between 1 and 9,1,0))/" . $totalRecordsQry->number . ")*100,2) as first_percentage_count,
                    sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t1.dob') . ",NOW()) between 10 and 14,1,0)) as secondAgeBracket,
                    ROUND((sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t1.dob') . ",NOW()) between 10 and 14,1,0))/" . $totalRecordsQry->number . ")*100,2) as second_percentage_count,
                    sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t1.dob') . ",NOW()) between 15 and 17,1,0)) as thirdAgeBracket,
                    ROUND((sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t1.dob') . ",NOW()) between 15 and 17,1,0))/" . $totalRecordsQry->number . ")*100,2) as third_percentage_count,
                    sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t1.dob') . ",NOW()) between 18 and 24,1,0)) as fourthAgeBracket,
                    ROUND((sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t1.dob') . ",NOW()) between 18 and 24,1,0))/" . $totalRecordsQry->number . ")*100,2) as fourth_percentage_count,
                    sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t1.dob') . ",NOW()) >25,1,0)) as fifthAgeBracket,
                    ROUND((sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t1.dob') . ",NOW()) > 25,1,0)) /" . $totalRecordsQry->number . ")*100,2) as fifth_percentage_count")
                ->where('t1.' . $whereColumn, 4)
                ->groupBy('t1.district_id');
            if (validateisNumeric($province_id)) {
                $qry->where('t1.province_id', $province_id);
            }
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

    public function getBeneficiariesShowingSchoolProgress(Request $request)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        try {
            $qry = DB::table('beneficiary_enrollments as t1')
                ->join('beneficiary_information as t2', 't1.beneficiary_id', '=', 't2.id')
                ->join('districts as t3', 't2.district_id', '=', 't3.id')
                ->join('provinces as t4', 't3.province_id', '=', 't4.id')
                ->selectRaw("t3.name as district_name,t4.name as province_name,t3.province_id,COUNT(*) as tt_count,
                    SUM(IF(TIMESTAMPDIFF(YEAR,t2.dob,NOW()) between 1 and 9,1,0)) as firstAgeBracket,
                    sum(IF(TIMESTAMPDIFF(YEAR,
                    " . $this->formatDOB('t2.dob') . ",NOW()) between 1 and 9,1,0)) as firstAgeBracket1,
                    sum(IF(TIMESTAMPDIFF(YEAR,
                    " . $this->formatDOB('t2.dob') . ",NOW()) between 10 and 14,1,0)) as secondAgeBracket,
                    sum(IF(TIMESTAMPDIFF(YEAR,
                    " . $this->formatDOB('t2.dob') . ",NOW()) between 15 and 17,1,0)) as thirdAgeBracket,
                    sum(IF(TIMESTAMPDIFF(YEAR,
                    " . $this->formatDOB('t2.dob') . ",NOW()) between 18 and 24,1,0)) as fourthAgeBracket,
                    sum(IF(TIMESTAMPDIFF(YEAR,
                    " . $this->formatDOB('t2.dob') . ",NOW()) >25,1,0)) as fifthAgeBracket")
                ->groupBy('t1.beneficiary_id')
                ->having(DB::raw("COUNT(*)"), ">", 1)
                ->groupBy('t2.district_id');
            if (validateisNumeric($province_id)) {
                $qry->where('t2.province_id', $province_id);
            }
            if (validateisNumeric($district_id)) {
                $qry->where('t2.district_id', $district_id);
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

    public function getKpiNumberAndPercentage(Request $request)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $targetGrp = $request->input('target_grp');
        try {
            $totalRecordsQry = DB::table('case_basicdataentry_details')->selectRaw('COUNT(DISTINCT id) as number')
                ->where('target_group_id', $targetGrp)->first();
            $qry = DB::table('case_girl_details as t1')
                ->join('case_basicdataentry_details as t2', 't1.id', '=', 't2.case_girl_id')
                ->join('districts as t4', 't2.district_id', '=', 't4.id')
                ->join('provinces as t5', 't4.province_id', '=', 't5.id')
                ->selectRaw("t4.name as district_name,t5.name as province_name,t4.province_id,
                    sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t1.dob') . ",NOW()) between 1 and 9,1,0)) as firstAgeBracket,
                    ROUND((sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t1.dob') . ",NOW()) between 1 and 9,1,0))/" . $totalRecordsQry->number . ")*100,2) as first_percentage_count,
                    sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t1.dob') . ",NOW()) between 10 and 14,1,0)) as secondAgeBracket,
                    ROUND((sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t1.dob') . ",NOW()) between 10 and 14,1,0))/" . $totalRecordsQry->number . ")*100,2) as second_percentage_count,
                    sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t1.dob') . ",NOW()) between 15 and 17,1,0)) as thirdAgeBracket,
                    ROUND((sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t1.dob') . ",NOW()) between 15 and 17,1,0))/" . $totalRecordsQry->number . ")*100,2) as third_percentage_count,
                    sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t1.dob') . ",NOW()) between 18 and 24,1,0)) as fourthAgeBracket,
                    ROUND((sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t1.dob') . ",NOW()) between 18 and 24,1,0))/" . $totalRecordsQry->number . ")*100,2) as fourth_percentage_count,
                    sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t1.dob') . ",NOW()) >25,1,0)) as fifthAgeBracket,
                    ROUND((sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t1.dob') . ",NOW()) >25,1,0)) /" . $totalRecordsQry->number . ")*100,2) as fifth_percentage_count")
                ->where('t2.target_group_id', $targetGrp)
                ->groupBy('t2.district_id');
            if (validateisNumeric($province_id)) {
                $qry->where('t2.province_id', $province_id);
            }
            if (validateisNumeric($district_id)) {
                $qry->where('t2.district_id', $district_id);
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

    public function getSupportKpis(Request $request)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $carePlanId = $request->input('care_plan_id');
        try {
            $totalRecordsQry = DB::table('case_careplan_details')->selectRaw('COUNT(*) as number')
                ->where('checklist_id', $carePlanId)->first();
            $qry = DB::table('case_careplan_details as t1')
                ->join('case_basicdataentry_details as t2', 't1.case_id', '=', 't2.id')
                ->join('case_girl_details as t3', 't2.case_girl_id', '=', 't3.id')
                ->join('districts as t4', 't2.district_id', '=', 't4.id')
                ->join('provinces as t5', 't4.province_id', '=', 't5.id')
                ->selectRaw("t4.name as district_name,t5.name as province_name,t4.province_id,
                    sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t3.dob') . ",NOW()) between 1 and 9,1,0)) as firstAgeBracket,
                    ROUND((sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t3.dob') . ",NOW()) between 1 and 9,1,0))/" . $totalRecordsQry->number . ")*100,2) as first_percentage_count,
                    sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t3.dob') . ",NOW()) between 10 and 14,1,0)) as secondAgeBracket,
                    ROUND((sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t3.dob') . ",NOW()) between 10 and 14,1,0))/" . $totalRecordsQry->number . ")*100,2) as second_percentage_count,
                    sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t3.dob') . ",NOW()) between 15 and 17,1,0)) as thirdAgeBracket,
                    ROUND((sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t3.dob') . ",NOW()) between 15 and 17,1,0))/" . $totalRecordsQry->number . ")*100,2) as third_percentage_count,
                    sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t3.dob') . ",NOW()) between 18 and 24,1,0)) as fourthAgeBracket,
                    ROUND((sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t3.dob') . ",NOW()) between 18 and 24,1,0))/" . $totalRecordsQry->number . ")*100,2) as fourth_percentage_count,
                    sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t3.dob') . ",NOW()) >25,1,0)) as fifthAgeBracket,
                    ROUND((sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t3.dob') . ",NOW()) >25,1,0)) /" . $totalRecordsQry->number . ")*100,2) as fifth_percentage_count")
                ->where('t1.checklist_id', $carePlanId)
                ->groupBy('t2.district_id');
            if (validateisNumeric($province_id)) {
                $qry->where('t2.province_id', $province_id);
            }
            if (validateisNumeric($district_id)) {
                $qry->where('t2.district_id', $district_id);
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

    public function getBeneficiaryRefferalKpis(Request $request)
    {
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $carePlanId = $request->input('care_plan_id');
        try {
            $totalRecordsQry = DB::table('case_careplan_details')->selectRaw('COUNT(*) as number')
                ->where('checklist_id', $carePlanId)->first();
            $qry = DB::table('case_implemetation_details as t1')
                ->join('case_basicdataentry_details as t2', 't1.case_id', '=', 't2.id')
                ->join('case_girl_details as t3', 't2.case_girl_id', '=', 't3.id')
                ->join('districts as t4', 't2.district_id', '=', 't4.id')
                ->join('provinces as t5', 't4.province_id', '=', 't5.id')
                ->selectRaw("t4.name as district_name,t5.name as province_name,t4.province_id,
                                sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t3.dob') . ",NOW()) between 1 and 9,1,0)) as firstAgeBracket,
                                ROUND((sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t3.dob') . ",NOW()) between 1 and 9,1,0))/" .
                    $totalRecordsQry->number . ")*100,2) as first_percentage_count,
                                sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t3.dob') . ",NOW()) between 10 and 14,1,0)) as secondAgeBracket,
                                ROUND((sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t3.dob') . ",NOW()) between 10 and 14,1,0))/" .
                    $totalRecordsQry->number . ")*100,2) as second_percentage_count,
                                sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t3.dob') . ",NOW()) between 15 and 17,1,0)) as thirdAgeBracket,
                                ROUND((sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t3.dob') . ",NOW()) between 15 and 17,1,0))/" .
                    $totalRecordsQry->number . ")*100,2) as third_percentage_count,
                                sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t3.dob') . ",NOW()) between 18 and 24,1,0)) as fourthAgeBracket,
                                ROUND((sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t3.dob') . ",NOW()) between 18 and 24,1,0))/" .
                    $totalRecordsQry->number . ")*100,2) as fourth_percentage_count,
                                sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t3.dob') . ",NOW()) >25,1,0)) as fifthAgeBracket,
                                ROUND((sum(IF(TIMESTAMPDIFF(YEAR," . $this->formatDOB('t3.dob') . ",NOW()) >25,1,0)) /" .
                    $totalRecordsQry->number . ")*100,2) as fifth_percentage_count")
                ->where('t1.careplan_id', $carePlanId)
                ->groupBy('t2.district_id');
            if (validateisNumeric($province_id)) {
                $qry->where('t2.province_id', $province_id);
            }
            if (validateisNumeric($district_id)) {
                $qry->where('t2.district_id', $district_id);
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

    public function getCaseKpisForAnalysis(Request $request)
    {
        $category_id = $request->input('category_id');
        $components = '[';
        $qry = DB::table('case_kpis')
            ->selectRaw('id,child_xtype,category_id,kpi_indicator');
        if (validateisNumeric($category_id)) {
            $qry->where('category_id', $category_id);
        }
        $results = $qry->get();
        $totalRecords = $results->count();
        $count = 0;
        foreach ($results as $result) {
            $count++;
            $components .= "{";
            $components .= "hidden:true,";
            $components .= "},";
            $components .= "{";
            $components .= "title:'" . $result->kpi_indicator . "',";
            $components .= "frame:true,";
            $components .= "scrollable:true,";
            $components .= "layout: 'fit',";
            $components .= "bodyPadding: 2,";
            $components .= "kpi_id: " . $result->id;
            if (isset($result->child_xtype)) {
                $components .= ",";
                $components .= "items: [{";
                $components .= "xtype:'" . $result->child_xtype . "'";
                $components .= "}]";
            }
            $components .= " }";
            $components .= $count == $totalRecords ? '' : ',';
        }
        $components .= "]";
        return $components;
    }

    public function getCaseKpiCategories(Request $request)
    {
        $qry = DB::table('case_kpi_categories')->select('*');
        $results = $qry->get();
        $totalRecords = $results->count();
        $count = 0;
        $components = "[";
        foreach ($results as $result) {
            $count++;
            $components .= "{";
            $components .= "title:'" . $result->name . "',";
            $components .= "xtype: 'casekpianalysispnl',";
            $components .= "category_id: " . $result->id;
            $components .= "}";
            $components .= $count == $totalRecords ? '' : ',';
        }
        $components .= "]";
        return $components;
    }
    // end kpis
    // end frank    
    public function getCaseReviewChecklists(Request $request)
    {
        try {
            $case_id = $request->input('case_id');
            $category_id = $request->input('category_id');
            $qry = DB::table('case_careplan as t1')
                ->leftJoin('case_reviewchecklists_details as t2', function ($join) use ($case_id) {
                    $join->on('t1.id', '=', 't2.checklist_id')
                        ->where('t2.case_id', $case_id);
                })
                ->select('t1.*', 't1.id as record_id', 't2.detail')
                ->where('t1.category_id', $category_id);
            $results = $qry->get();
            $res = array(
                'success' => true,
                'message' => returnMessage($results),
                'results' => $results
            );
        } catch (\Exception $exception) {
            DB::rollBack();
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            DB::rollBack();
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return \response()->json($res);
    }
    
    public function saveCaseReviewChecklists(Request $request)
    {
        try {
            $sent_records = $request->input('edited_records');
            $case_id = $request->input('case_id');
            $edited_records = json_decode($sent_records, true);
            $table_name = 'case_reviewchecklists_details';
            foreach ($edited_records as $key => $value) {
                $table_data = array(
                    'case_id' => $case_id,
                    'checklist_id' => isset($value['record_id']) ? $value['record_id'] : '',
                    'detail' => isset($value['detail']) ? $value['detail'] : ''
                );  
                //where data
                $where_data = array(
                    'checklist_id' => $value['record_id'],
                    'case_id' => $case_id
                );
                if (recordExists($table_name, $where_data)) {
                    $table_data['updated_by'] = $this->user_id;
                    DB::table($table_name)
                        ->where($where_data)
                        ->update($table_data);
                } else {
                    $table_data['created_by'] = $this->user_id;
                    DB::table($table_name)->insert($table_data);
                }
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

    public function saveRevisedCarePlanDetails(Request $request)
    {
        try {
            $post_data = $request->all();
            $case_id = $post_data['case_id'];
            $monitoring_id = $post_data['monitoring_id'];

            $care_plan_id = $request->input('care_plan_id');
            $revised_care_plan_id = $request->input('revised_care_plan_id');

            $postdata = file_get_contents("php://input");
            $data = json_decode($postdata);
            // the post data is array thus handle as array data
            if (is_array($data)) {
                $data = json_decode($postdata, true);
            } else {
                $data = array();
                $data[] = json_decode($postdata, true);
            }
            foreach ($data as $key => $value) {
                $care_plan_id = $value['care_plan_id'];
                $carePlanUpdate = array(
                    //'case_id' => $case_id,
                    //'checklist_id' => $value['checklist_id'],
                    'timeframe' => $value['timeframe'],
                    'responsible_person' => $value['responsible_person'],
                    'updated_by' => $this->user_id,
                    'updated_at' => Carbon::now()
                );
                DB::table('case_careplan_details')
                    ->where('id', $care_plan_id)
                    ->update($carePlanUpdate);
                /*$carePlanUpdate['case_monitoring_id'] = $monitoring_id;
                DB::table('case_revisedcareplan_details')
                    ->where('id', $revised_care_plan_id)
                    ->update($carePlanUpdate);*/
            }
            $res = array(
                'success' => true,
                'message' => 'Details saved successfully!!'
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

    public function getCaseMonitoringCarePlanDetails(Request $request)
    {
        try {
            $case_id = $request->input('case_id');
            $monitoring_id = $request->input('monitoring_id');
            $monitoring_counter = $request->input('counter');
            $category_id = $request->input('category_id');
            if ($monitoring_counter < 1) {
                $monitoring_counter = 1;
            }
            $qry = DB::table('case_careplan_details as t1')
                ->join('case_careplan as t2', 't1.checklist_id', '=', 't2.id')
                ->join('case_monitoring_information as t3', function ($join) use ($monitoring_id, $monitoring_counter) {
                    $join->on('t1.case_monitoring_id', '=', 't3.id')
                        ->where('t3.counter', '<', $monitoring_counter);
                })
                ->leftJoin('case_implemetation_details as t4', function ($join) use ($case_id) {
                    $join->on('t2.id', '=', 't4.careplan_id')
                        ->where('t4.case_id', '=', $case_id);
                })
                ->select(DB::raw("t1.*, t2.action,t1.id as recordId,t2.id as checklist_id,SUM(IF(t4.service_status_id=1,1,0)) as open_services"))
                //->select('t1.*', 't2.action', 't3.id as recordId', 't3.action_provided', 't3.date_provided', 't3.case_monitoring_id')
                ->where('t1.case_id', $case_id)
                ->where('t2.category_id', $category_id)
                ->groupBy('t2.id');
            $results = $qry->get();
            $res = array(
                'success' => true,
                'message' => returnMessage($results),
                'results' => $results
            );
        } catch (\Exception $exception) {
            DB::rollBack();
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            DB::rollBack();
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return \response()->json($res);
    }

    public function saveCaseMonitoringCarePlanDetails(Request $req)
    {
        try {
            $post_data = $req->all();
            $monitoring_id = $post_data['monitoring_id'];
            $postdata = file_get_contents("php://input");
            $data = json_decode($postdata);
            // the post data is array thus handle as array data
            if (is_array($data)) {
                $data = json_decode($postdata, true);
            } else {
                $data = array();
                $data[] = json_decode($postdata, true);
            }

            $table_name = 'case_careplan_details';
            $params = array();
            foreach ($data as $key => $value) {
                if (validateisNumeric($value['recordId'])) {
                    $update = array(
                        'provided' => $value['provided'],
                        'date_provided' => $value['date_provided'],
                        'updated_by' => $this->user_id,
                        'updated_at' => Carbon::now()
                    );
                    DB::table($table_name)
                        ->where('id', $value['recordId'])
                        ->update($update);
                } else {
                    $params[] = array(
                        'case_monitoring_id' => $monitoring_id,
                        'case_id' => $value['case_id'],
                        'checklist_id' => $value['checklist_id'],
                        'provided' => $value['provided'],
                        'date_provided' => $value['date_provided'],
                        'created_by' => $this->user_id,
                        'created_at' => Carbon::now()
                    );
                }
            }
            DB::table($table_name)->insert($params);
            $res = array(
                'success' => true,
                'message' => 'Details saved successfully!!'
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

    public function saveCaseMonitoringReviewBasicInfo(Request $request)
    {
        try {
            $res = array();
            DB::transaction(function () use ($request, &$res) {
                $case_id = $request->input('case_id');
                $id = $request->input('id');
                $table_name = 'case_monitoring_information';
                $params = array();
                //get monitoring counter
                if ($request->input('form_id') == 1) {
                    $params = array(
                        'case_id' => $case_id,
                        'review_date' => $request->input('review_date'),
                        'review_type' => $request->input('review_type'),
                        'description' => $request->input('description'),
                        'counter' => $request->input('counter')
                    );
                }
                if (validateisNumeric($id)) {
                    $params['updated_by'] = $this->user_id;
                    if ($request->input('form_id') == 2) {
                        $params['gc_teacher_name'] = $request->input('gc_teacher_name');
                        $params['gc_teacher_sign'] = $request->input('gc_teacher_sign');
                        $params['gc_teacher_date'] = $request->input('gc_teacher_date');
                        $params['parent_name'] = $request->input('parent_name');
                        $params['parent_sign'] = $request->input('parent_sign');
                        $params['parent_date'] = $request->input('parent_date');
                        $params['girl_sign'] = $request->input('girl_sign');
                        $params['girl_date'] = $request->input('girl_date');
                        $params['gc_supervisor_name'] = $request->input('gc_supervisor_name');
                        $params['gc_supervisor_sign'] = $request->input('gc_supervisor_sign');
                        $params['gc_supervisor_date'] = $request->input('gc_supervisor_date');
                        $params['next_review_date'] = $request->input('next_review_date');
                    }
                    DB::table($table_name)
                        ->where('id', $id)
                        ->update($params);
                } else {
                    $params['created_by'] = $this->user_id;
                    $id = DB::table($table_name)
                        ->insertGetId($params);
                }
                $res = array(
                    'success' => true,
                    'record_id' => $id,
                    'message' => 'Details saved successfully!!'
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
        return response()->json($res);
    }

    public function getCaseMonitoringReviewNextCounter(Request $request)
    {
        try {
            $case_id = $request->input('case_id');
            $counter = DB::table('case_monitoring_information')
                ->where('case_id', $case_id)
                ->max('counter');
            $res = array(
                'success' => true,
                'nextCounter' => ++$counter
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

    public function getCaseClosureReasonsDetails(Request $request)
    {
        try {
            $case_id = $request->input('case_id');
            $qry = DB::table('case_closure_reasons as t1')
                ->leftJoin('case_closurereasons_details as t2', function ($join) use ($case_id) {
                    $join->on('t1.id', '=', 't2.reason_id')
                        ->where('t2.case_id', $case_id);
                })
                ->select('t1.*', 't2.id as recordId', 't2.detail');
            $results = $qry->get();
            $res = array(
                'success' => true,
                'message' => returnMessage($results),
                'results' => $results
            );
        } catch (\Exception $exception) {
            DB::rollBack();
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            DB::rollBack();
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return \response()->json($res);
    }
    
    public function getCaseProcessCompletedDetails(Request $request)
    {
        try {
            $case_id = $request->input('case_id');
            $qry = DB::table('case_process_completed as t1')
                ->leftJoin('case_processcompleted_details as t2', function ($join) use ($case_id) {
                    $join->on('t1.id', '=', 't2.process_id')
                        ->where('t2.case_id', $case_id);
                })
                ->select('t1.*', 't2.id as recordId', 't2.detail');
            $results = $qry->get();
            $res = array(
                'success' => true,
                'message' => returnMessage($results),
                'results' => $results
            );
        } catch (\Exception $exception) {
            DB::rollBack();
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            DB::rollBack();
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return \response()->json($res);
    }
    
    public function saveCaseProcessCompletedDetails(Request $request)
    {
        try {
            $sent_records = $request->input('edited_records');
            $case_id = $request->input('case_id');
            $edited_records = json_decode($sent_records, true);
            $table_name = 'case_processcompleted_details';
            foreach ($edited_records as $key => $value) {
                $table_data = array(
                    'case_id' => $case_id,
                    'process_id' => isset($value['record_id']) ? $value['record_id'] : '',
                    'detail' => isset($value['detail']) ? $value['detail'] : ''
                );  
                //where data
                $where_data = array(
                    'id' => $value['record_id']
                );
                if (recordExists($table_name, $where_data)) {
                    $table_data['updated_by'] = $this->user_id;
                    DB::table($table_name)
                        ->where($where_data)
                        ->update($table_data);
                } else {
                    $table_data['created_by'] = $this->user_id;
                    DB::table($table_name)->insert($table_data);
                }
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
    
    public function saveCaseClosureReasonsDetails(Request $request)
    {
        try {
            $sent_records = $request->input('edited_records');
            $case_id = $request->input('case_id');
            $edited_records = json_decode($sent_records, true);
            $table_name = 'case_closurereasons_details';
            foreach ($edited_records as $key => $value) {
                $table_data = array(
                    'case_id' => $case_id,
                    'reason_id' => isset($value['record_id']) ? $value['record_id'] : '',
                    'detail' => isset($value['detail']) ? $value['detail'] : ''
                );  
                //where data
                $where_data = array(
                    'id' => $value['record_id']
                );
                if (recordExists($table_name, $where_data)) {
                    $table_data['updated_by'] = $this->user_id;
                    DB::table($table_name)
                        ->where($where_data)
                        ->update($table_data);
                } else {
                    $table_data['created_by'] = $this->user_id;
                    DB::table($table_name)->insert($table_data);
                }
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

    public function saveCaseClosureReasonsDetailsInitial(Request $req)
    {
        try {
            $post_data = $req->all();
            $case_id = $post_data['case_id'];
            $postdata = file_get_contents("php://input");
            $data = json_decode($postdata);
            // the post data is array thus handle as array data
            if (is_array($data)) {
                $data = json_decode($postdata, true);
            } else {
                $data = array();
                $data[] = json_decode($postdata, true);
            }

            $table_name = 'case_closurereasons_details';
            $params = array();
            foreach ($data as $key => $value) {
                if (validateisNumeric($value['recordId'])) {
                    $update = array(
                        'detail' => $value['detail'],
                        'updated_by' => $this->user_id,
                        'updated_at' => Carbon::now()
                    );
                    DB::table($table_name)
                        ->where('id', $value['recordId'])
                        ->update($update);
                } else {
                    $params[] = array(
                        'case_id' => $case_id,
                        'reason_id' => $value['id'],
                        'detail' => $value['detail'],
                        'created_by' => $this->user_id,
                        'created_at' => Carbon::now()
                    );
                }
            }
            DB::table($table_name)->insert($params);
            $res = array(
                'success' => true,
                'message' => 'Details saved successfully!!'
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
    
    public function getCaseClosureProcessesCompletedDetails(Request $request)
    {
        try {
            $case_id = $request->input('case_id');
            $qry = DB::table('case_closure_processes as t1')
                ->leftJoin('case_closureprocesses_details as t2', function ($join) use ($case_id) {
                    $join->on('t1.id', '=', 't2.process_id')
                        ->where('t2.case_id', $case_id);
                })
                ->select('t1.*', 't2.id as recordId', 't2.detail');
            $results = $qry->get();
            $res = array(
                'success' => true,
                'message' => returnMessage($results),
                'results' => $results
            );
        } catch (\Exception $exception) {
            DB::rollBack();
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            DB::rollBack();
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return \response()->json($res);
    }

    public function saveCaseClosureProcessesCompletedDetails(Request $req)
    {
        try {
            $post_data = $req->all();
            $case_id = $post_data['case_id'];
            $postdata = file_get_contents("php://input");
            $data = json_decode($postdata);
            // the post data is array thus handle as array data
            if (is_array($data)) {
                $data = json_decode($postdata, true);
            } else {
                $data = array();
                $data[] = json_decode($postdata, true);
            }

            $table_name = 'case_closureprocesses_details';
            $params = array();
            foreach ($data as $key => $value) {
                if (validateisNumeric($value['recordId'])) {
                    $update = array(
                        'detail' => $value['detail'],
                        'updated_by' => $this->user_id,
                        'updated_at' => Carbon::now()
                    );
                    DB::table($table_name)
                        ->where('id', $value['recordId'])
                        ->update($update);
                } else {
                    $params[] = array(
                        'case_id' => $case_id,
                        'process_id' => $value['id'],
                        'detail' => $value['detail'],
                        'created_by' => $this->user_id,
                        'created_at' => Carbon::now()
                    );
                }
            }
            DB::table($table_name)->insert($params);
            $res = array(
                'success' => true,
                'message' => 'Details saved successfully!!'
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

    public function processCaseClosure(Request $request)
    {
        try {
            $res = array();
            DB::transaction(function () use ($request, &$res) {
                $id = $request->input('id');
                $case_id = $request->input('case_id');
                $table_name = 'case_closuredecision_descriptions';
                $params = array(
                    'case_id' => $case_id,
                    'description_one' => $request->input('description_one'),
                    'description_two' => $request->input('description_two'),
                    'closure_by' => $request->input('closure_by'),
                    'designation' => $request->input('designation'),
                    'closure_date' => $request->input('closure_date')
                );
                if (validateisNumeric($id)) {
                    $params['updated_by'] = $this->user_id;
                    $params['updated_at'] = Carbon::now();
                    DB::table($table_name)
                        ->where('id', $id)
                        ->update($params);
                } else {
                    $params['created_by'] = $this->user_id;
                    $params['created_at'] = Carbon::now();
                    DB::table($table_name)->insert($params);
                }
                DB::table('case_basicdataentry_details')
                    ->where('id', $case_id)
                    ->update(array('workflow_stage_id' => 4, 'record_status_id' => 2));
                $res = array(
                    'success' => true,
                    'message' => 'Details saved and complaint moved to Archive!!'
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
        return response()->json($res);
    }

    public function saveCaseAppealDetails(Request $request)
    {
        try {
            $case_id = $request->input('case_id');
            $to_stage_id = $request->input('workflow_stage_id');
            $appealDetails = array(
                'case_id' => $case_id,
                'to_stage_id' => $to_stage_id,
                'appeal_reason_id' => $request->input('appeal_reason_id'),
                'remark' => $request->input('remark'),
                'created_at' => Carbon::now(),
                'created_by' => $this->user_id
            );
            Db::table('cases_appealedagainst')->insert($appealDetails);
            DB::table('case_basicdataentry_details')
                ->where('id', $case_id)
                ->update(
                    array(
                        'workflow_stage_id' => $to_stage_id, 
                        'record_status_id' => 3,            
                        'submitted_for_verification' => 0,
                        'access_point_id' => 5,
                        'ht_approved' => 1
                    )
                );
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

    public function getCaseAppealDetails(Request $request)
    {
        try {
            $case_id = $request->input('case_id');
            $qry = DB::table('cases_appealedagainst as t1')
                ->join('grm_grievance_appealreasons as t2', 't1.appeal_reason_id', '=', 't2.id')
                ->leftJoin('users as t3', 't1.created_by', '=', 't3.id')
                ->select(DB::raw("t1.*,t2.name as appeal_reason,CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as appeal_by"))
                ->where('t1.case_id', $case_id)
                ->orderBy('id', 'DESC');
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

    public function getCaseGirlsDetails(Request $req)
    {
        try {
            $logged_in_user = $this->user_id;
            $user_access_point =Auth::user()->access_point_id;
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
                            case 'beneficiary_name' :
                                $whereClauses[] = "t1.first_name like '%" . ($filter->value) . "%' OR t1.last_name like '%" . ($filter->value) . "%'";
                                break;
                            case 'province_name' :
                                $whereClauses[] = "t3.name like '%" . ($filter->value) . "%'";
                                break;
                            case 'district_name' :
                                $whereClauses[] = "t4.name like '%" . ($filter->value) . "%'";
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
                //->leftJoin('case_girl_details as t2', 't1.id', '=', 't2.ben_girl_id')
                ->join('provinces as t3', 't1.province_id', '=', 't3.id')
                ->join('districts as t4', 't1.district_id', '=', 't4.id')
                ->join('households as t5', 't1.household_id', '=', 't5.id')
                ->join('school_information as t6', 't1.school_id', '=', 't6.id')
                ->join('districts as t7', 't6.district_id', '=', 't7.id')
                ->select(DB::raw("DISTINCT(t1.id) as ben_girl_id,t1.beneficiary_id,t1.id as girl_id,t3.name as province_name,t4.name as home_district,
                        CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.last_name)) as beneficiary_name,t1.first_name, t1.last_name,t6.name as school_name,
                        t1.dob,decrypt(t1.first_name) as first_name, decrypt(t1.last_name) as last_name,t7.name as sch_district,t6.district_id,t1.school_id,
                        t6.province_id,CONCAT_WS(' ',t5.hhh_fname,t5.hhh_lname) as hhh_name,t5.hhh_fname,t5.hhh_lname,t5.hhh_nrc_number,                              
                        t1.current_school_grade as current_grade,t1.highest_grade as last_completed_grade,t1.ward_id as ward,
                        t1.mobile_phone_parent_guardian as girl_mobile_no"));

            if ($filter_string != '') {
                $qry->whereRAW($filter_string);
            }

            if ($user_access_point == 3 || $user_access_point == 4) {//assigned districts/prov
                $qry->whereIn('t6.district_id', function ($query) use ($logged_in_user) {
                    $query->select(DB::raw('user_district.district_id'))
                        ->from('user_district')
                        ->whereRaw('user_district.user_id=' . $logged_in_user);
                });
            }

            if ($user_access_point == 6) {//assigned schools
                $qry->whereIn('t1.school_id', function ($query) use ($logged_in_user) {
                    $query->select(DB::raw('user_school.school_id'))
                        ->from('user_school')
                        ->whereRaw('user_school.user_id=' . $logged_in_user);
                });
            }

            $total = $qry->count();
            $qry->offset($start)->limit($limit);
            $results = $qry->get();
            $res = array(
                'success' => true,
                'totalCount' => $total,
                'results' => $results,
                'message' => 'Records fetched successfully!!'
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

    public function getCaseDashboardData(Request $req)
    {//franken
        try {
            $reported_cases = 0;$ongoing_cases = 0;
            $appealed_cases = 0;$closed_cases = 0;
            $lodge_date = $req->input('lodge_date') ?  Carbon::createFromFormat('Y-m-d', Str::limit($req->input('lodge_date'), 10, ''))->format('Y/m/d') : null;
            $closure_date = $req->input('closure_date') ? Carbon::createFromFormat('Y-m-d', Str::limit($req->input('closure_date'), 10, ''))->format('Y/m/d') : null;
            $rec_date_from = $req->input('rec_date_from') ?  Carbon::createFromFormat('Y-m-d', Str::limit($req->input('rec_date_from'), 10, ''))->format('Y/m/d') : null;
            $rec_date_to = $req->input('rec_date_to') ? Carbon::createFromFormat('Y-m-d', Str::limit($req->input('rec_date_to'), 10, ''))->format('Y/m/d') : null;
            
            $user_access_point = Auth::user()->access_point_id;
            $logged_in_user = $this->user_id;
            $qry = DB::table('case_basicdataentry_details as t1')
                ->select(DB::raw("COUNT(DISTINCT t1.id) as reported_cases,
                                    SUM(IF(t1.record_status_id=1,1,0)) as ongoing_cases,
                                    SUM(IF(t1.record_status_id=3,1,0)) as appealed_cases,
                                    SUM(IF(t1.record_status_id=2,1,0)) as closed_cases"));
            if ($user_access_point == 3 || $user_access_point == 4) {//assigned districts
                $qry->whereIn('t1.district_id', function ($query) use ($logged_in_user) {
                    $query->select(DB::raw('user_district.district_id'))
                        ->from('user_district')
                        ->whereRaw('user_district.user_id=' . $logged_in_user);
                });
            }
            if ($user_access_point == 6) {//assigned schools
                $qry->whereIn('t1.school_id', function ($query) use ($logged_in_user) {
                    $query->select(DB::raw('user_school.school_id'))
                        ->from('user_school')
                        ->whereRaw('user_school.user_id=' . $logged_in_user);
                });
            }
            // if ($lodge_date != null && $closure_date != null) {//dates
            //     $qry->whereDate('t1.case_formrecording_date', '>=', $lodge_date);
            //     $qry->whereDate('t1.updated_at', '<=', $closure_date);
            // }
            if ($lodge_date != null && $closure_date != null) {//dates
                $qry->whereDate('t1.case_formrecording_date', '>=', $lodge_date);
                $qry->whereDate('t1.created_at', '<=', $closure_date);
            }
            if ($rec_date_from != null && $rec_date_to != null) {//dates
                $qry->whereDate('t1.case_formrecording_date', '>=', $rec_date_from);
                $qry->whereDate('t1.created_at', '<=', $rec_date_to);
            }
            $results = $qry->first();
            if ($results) {
                $reported_cases = $results->reported_cases;
                $ongoing_cases = $results->ongoing_cases;
                $appealed_cases = $results->appealed_cases;
                $closed_cases = $results->closed_cases;
            }
            $res = array(
                'reported_cases' => number_format($reported_cases),
                'ongoing_cases' => number_format($ongoing_cases),
                'appealed_cases' => number_format($appealed_cases),
                'closed_cases' => number_format($closed_cases)
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
        return \response()->json($res);
    }

    public function getDashboardGraphGridCasesCount(Request $request)
    {
        try {
            $user_access_point = Auth::user()->access_point_id;
            $logged_in_user = $this->user_id;
            $primary_table = $request->input('primary_table');
            $group_by_fld = $request->input('group_by_fld');
            $qry = DB::table($primary_table . ' as t1')
                ->leftJoin('case_basicdataentry_details as t2', 't1.id', '=', 't2.' . $group_by_fld)
                ->select(DB::raw("t1.id,t2.target_group_id,COUNT(DISTINCT t2.id) as no_of_cases,
                                   t2.record_status_id,t2.workflow_stage_id,t1.name as group_name"));
            if ($user_access_point == 3 || $user_access_point == 4) {//assigned districts
                $qry->whereIn('t2.district_id', function ($query) use ($logged_in_user) {
                    $query->select(DB::raw('user_district.district_id'))
                        ->from('user_district')
                        ->whereRaw('user_district.user_id=' . $logged_in_user);
                });
            }
            if ($user_access_point == 6) {//assigned schools
                $qry->whereIn('t2.school_id', function ($query) use ($logged_in_user) {
                    $query->select(DB::raw('user_school.school_id'))
                        ->from('user_school')
                        ->whereRaw('user_school.user_id=' . $logged_in_user);
                });
            }
            $qry->groupBy('t1.id');
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

    public function getDropoutsCountPerCategory(Request $request)
    {
        try {
            $user_access_point = Auth::user()->access_point_id;
            $logged_in_user = $this->user_id;
            $year = $request->input('year');
            $lodge_date = $request->input('lodge_date') ?  Carbon::createFromFormat('Y-m-d', Str::limit($request->input('lodge_date'), 10, ''))->format('Y/m/d') : null;
            $closure_date = $request->input('closure_date') ? Carbon::createFromFormat('Y-m-d', Str::limit($request->input('closure_date'), 10, ''))->format('Y/m/d') : null;
            $rec_date_from = $request->input('rec_date_from') ?  Carbon::createFromFormat('Y-m-d', Str::limit($request->input('rec_date_from'), 10, ''))->format('Y/m/d') : null;
            $rec_date_to = $request->input('rec_date_to') ? Carbon::createFromFormat('Y-m-d', Str::limit($request->input('rec_date_to'), 10, ''))->format('Y/m/d') : null;
            
            $qry = DB::table('case_basicdataentry_details as t1')
                ->join('suspension_reasons as t2', 't1.dropout_reason_id', '=', 't2.id')
                ->select(DB::raw("t1.id,t1.dropout_reason_id,COUNT(DISTINCT t1.id) as no_of_dropouts,t2.name as group_name"));
            if (validateisNumeric($year)) {
                $qry->whereRaw("YEAR(t1.case_sysrecording_date)=$year");
            }
            if ($user_access_point == 3 || $user_access_point == 4) {//assigned districts
                $qry->whereIn('t1.district_id', function ($query) use ($logged_in_user) {
                    $query->select(DB::raw('user_district.district_id'))
                        ->from('user_district')
                        ->whereRaw('user_district.user_id=' . $logged_in_user);
                });
            }
            if ($user_access_point == 6) {//assigned schools
                $qry->whereIn('t1.school_id', function ($query) use ($logged_in_user) {
                    $query->select(DB::raw('user_school.school_id'))
                        ->from('user_school')
                        ->whereRaw('user_school.user_id=' . $logged_in_user);
                });
            }
            if ($lodge_date != null && $closure_date != null) {//dates
                $qry->whereDate('t1.case_formrecording_date', '>=', $lodge_date);
                $qry->whereDate('t1.created_at', '<=', $closure_date);
            }
            if ($rec_date_from != null && $rec_date_to != null) {//dates
                $qry->whereDate('t1.case_formrecording_date', '>=', $rec_date_from);
                $qry->whereDate('t1.created_at', '<=', $rec_date_to);
            }
            $qry->groupBy('t2.id');
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

    public function saveCaseTransferDetails(Request $request)
    {
        try {
            $case_id = $request->input('case_id');
            $res = array();
            $update = array(
                'province_id' => $request->input('to_province_id'),
                'district_id' => $request->input('to_district_id'),
                'ward' => $request->input('to_ward'),
                'village' => $request->input('to_village'),
                'chief' => $request->input('to_chief'),
                'school_id' => $request->input('to_school_id'),
                'nearest_clinic' => $request->input('to_nearest_clinic'),
                'nearest_business_center' => $request->input('to_nearest_business_center'),
                'other_significant_places' => $request->input('to_other_significant_places')
            );
            $params = array(
                'case_id' => $case_id,

                'from_province_id' => $request->input('province_id'),
                'from_district_id' => $request->input('district_id'),
                'from_ward' => $request->input('ward'),
                'from_village' => $request->input('village'),
                'from_chief' => $request->input('chief'),
                'from_school_id' => $request->input('school_id'),
                'from_nearest_clinic' => $request->input('nearest_clinic'),
                'from_nearest_business_center' => $request->input('nearest_business_center'),
                'from_other_significant_places' => $request->input('other_significant_places'),

                'to_province_id' => $request->input('to_province_id'),
                'to_district_id' => $request->input('to_district_id'),
                'to_ward' => $request->input('to_ward'),
                'to_village' => $request->input('to_village'),
                'to_chief' => $request->input('to_chief'),
                'to_school_id' => $request->input('to_school_id'),
                'to_nearest_clinic' => $request->input('to_nearest_clinic'),
                'to_nearest_business_center' => $request->input('to_nearest_business_center'),
                'to_other_significant_places' => $request->input('to_other_significant_places'),
                'remarks' => $request->input('remarks'),
                'transfer_date' => Carbon::now(),
                'transfer_by' => $this->user_id

            );
            DB::transaction(function () use ($params, $update, $case_id, &$res) {
                DB::table('case_transfer_details')
                    ->insert($params);
                DB::table('case_basicdataentry_details')
                    ->where('id', $case_id)
                    ->update($update);
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

    public function getCasePersonnelInfo(Request $request)
    {
        try {
            $designation_id = $request->input('designation_id');
            $personnel_type = $request->input('personnel_type');
            if ($personnel_type == 1) {
                $results = $this->getPersonnelInfo($designation_id);
            } else {
                $results = $this->getInformantInfo();
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

    public function getPersonnelInfo($designation_id)
    {
        $qry = DB::table('case_basicdataentry_details as t1')
            ->select('personel_first_name', 'personel_last_name', 'personel_mobile_no')
            ->where('personel_designation_id', $designation_id)
            ->groupBy('personel_first_name', 'personel_last_name');
        return $qry->get();
    }

    public function getInformantInfo()
    {
        $qry = DB::table('case_basicdataentry_details as t1')
            ->select('informant_first_name', 'informant_last_name', 'informant_idno', 'informant_gender_id', 'informant_address', 'informant_rshipwith_girl', 'informant_contacts')
            ->groupBy('informant_idno');
        return $qry->get();
    }

    function getCaseManagementSubModulesDMSFolderID(Request $req)
    {
        try {
            $parent_folder_id = $req->input('parent_id');
            $sub_module_id = $req->input('sub_module_id');
            if ($parent_folder_id == '' || $parent_folder_id == 0) {
                $res = array(
                    'success' => false,
                    'message' => 'Problem was encountered while fetching folder details relating to this record!! Please contact system admin.'
                );
                return response()->json($res);
            }
            $folder_id = getSubModuleFolderID($parent_folder_id, $sub_module_id);
            $res = array(
                'success' => true,
                'folder_id' => $folder_id,
                'message' => 'Folder ID retrieved'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getCarePlanTrackingInfo(Request $request)
    {
        try {
            //$service_provided_check = $request->input('service_provided_check');
            $qry = DB::table('case_careplan_details as t1')
                ->join('case_careplan as t2', 't1.checklist_id', '=', 't2.id')
                ->select(DB::raw("t2.id as careplan_id,t2.action,COUNT(*) as care_plan_count,
                         SUM(IF(t1.provided=1,1,0)) as provided_count,
                         SUM(IF(t1.provided<>1 OR t1.provided IS NULL,1,0)) as not_provided_count"))
                ->groupBy('t1.checklist_id');
            /*   if (validateisNumeric($service_provided_check)) {
                   if ($service_provided_check == 1) {
                       $qry->where('t1.provided', 1);
                   } else {
                       $qry->where(function ($query) {
                           $query->where('t1.provided', '<>', 1)
                               ->orWhereNull('t1.provided');
                       });
                   }
               }*/
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

    public function getCaseReferralsTrackingInfo(Request $request)
    {
        try {
            $referral_status_id = $request->input('referral_status_id');
            $qry = DB::table('case_referrals as t1')
                ->join('case_implemetation_details as t2', 't1.case_implementation_id', '=', 't2.id')
                ->join('case_services as t3', 't2.service_id', '=', 't3.id')
                ->join('case_services_statuses as t4', 't1.referral_status_id', '=', 't4.id')
                ->join('case_basicdataentry_details as t5', 't1.case_id', '=', 't5.id')
                ->select(DB::raw("t1.*,t3.service_name,t4.name as referral_status,t5.mis_no,t5.case_file_number"));
            if (validateisNumeric($referral_status_id)) {
                $qry->where('t1.referral_status_id', $referral_status_id);
            }
            $qry->groupBy('t1.id');
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

    public function validateCaseClosure(Request $request)
    {
        try {
            $case_id = $request->input('case_id');
            $qry = DB::table('case_careplan_details as t1')
                ->join('case_careplan as t2', 't1.checklist_id', '=', 't2.id')
                ->join('case_monitoring_information as t3', 't1.case_monitoring_id', '=', 't3.id')
                ->where('t1.case_id', $case_id)
                ->where(function ($query) {
                    $query->where('t1.provided', '<>', 1)
                        ->orWhereNull('t1.provided');
                });
            $res = array(
                'success' => true,
                'not_provided_count' => $qry->count()
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

    public function getCaseTransfersLogs()
    {
        try {
            $qry = DB::table('case_transfer_details as t1')
                ->join('case_basicdataentry_details as t2', 't1.case_id', '=', 't2.id')
                ->join('provinces as t3', 't1.from_province_id', '=', 't3.id')
                ->join('districts as t4', 't1.from_district_id', '=', 't4.id')
                ->join('school_information as t5', 't1.from_school_id', '=', 't5.id')
                ->join('provinces as t6', 't1.to_province_id', '=', 't6.id')
                ->join('districts as t7', 't1.to_district_id', '=', 't7.id')
                ->join('school_information as t8', 't1.to_school_id', '=', 't8.id')
                ->select('t1.*', 't2.case_file_number', 't2.mis_no', 't3.name as from_province', 't4.name as from_district', 't5.name as from_school',
                    't6.name as to_province', 't7.name as to_district', 't8.name as to_school');
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

    public function exportCMSRecords(Request $request)
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
    
    public function getRecordSubmissionReportDetails(Request $request)
    {
        $record_id = $request->input('record_id');
        $user_role_id = $request->input('user_role_id');
        $user_grp_id = $request->input('user_grp_id');
        $logged_in_user = $request->input('logged_in_user'); 
        $access_point_id = $request->input('access_point_id'); 
        $submit_direction = $request->input('submit_direction');        
        $user_id = $this->user_id;       
        $results = array();
        $allow_submit = true;
        try {
            $results = 0;
            $res = array('success' => true, 'results' => $results, 'allow_submit' => $allow_submit);
        } catch (\Exception $e) {
            $res = array('success' => false, 'message' => $e->getMessage());
        } catch (\Throwable $throwable) {
            $res = array('success' => false, 'message' => $throwable->getMessage());
        }
        return response()->json($res);
    }

    public function getCaseRelationshipDetails(Request $request)
    {
        try {
            $qry = DB::table('case_relationship_details as t1')
                ->leftJoin('relationshipcategory as t2', 't1.category', '=', 't2.id')
                ->selectRaw('t1.*,t2.name as category_name,t2.id as category_id');
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

    public function getCaseSystemUsers(Request $request)
    {
        try {       
            $user_id = $this->user_id; 
            $group_id = $request->input('group_id') ? (int)$request->input('group_id') : null;
            $stage_id_fld = $request->input('stage_id_fld') ? (int)$request->input('stage_id_fld') : null;
            $access_point_fld = $request->input('access_point_fld') ? (int)$request->input('access_point_fld') : null;
            $user_grp_fld = $request->input('user_grp_fld') ? (int)$request->input('user_grp_fld') : null;
            $record_id = $request->input('record_id_fld') ? (int)$request->input('record_id_fld') : null;
            $school_id = getSingleRecordColValue('case_basicdataentry_details', array('id' => $record_id), 'school_id');
            $qry = DB::table('users')
                ->leftJoin('user_images', 'users.id', '=', 'user_images.user_id')
                ->join('access_points', 'users.access_point_id', '=', 'access_points.id')
                ->leftJoin('user_roles', 'users.user_role_id', '=', 'user_roles.id')
                ->join('gender', 'users.gender_id', '=', 'gender.id')
                ->join('titles', 'users.title_id', '=', 'titles.id')
                ->leftJoin('grm_gewel_programmes as t7', 'users.gewel_programme_id', '=', 't7.id')
                ->leftJoin('menus as t8', 'users.dashboard_id', '=', 't8.id')
                ->join('user_group', 'users.id', '=', 'user_group.user_id')
                ->select(DB::raw("users.id, users.first_name, users.last_name, users.last_login_time, users.title_id, users.email, decrypt(users.phone) as phone, decrypt(users.mobile) as mobile,users.gewel_programme_id,users.nongewel_programme_id,
                    users.gender_id, users.access_point_id, users.user_role_id, gender.name as gender_name, titles.name as title_name, user_images.saved_name,users.dashboard_id,
                    access_points.name as access_point_name, user_roles.name as user_role_name, CONCAT_WS(' ',decrypt(users.first_name),decrypt(users.last_name)) as names,
                    t7.name as gewel_programme,t8.dashboard_name,users.is_coordinator"))
                ->whereNotIn('users.id', function ($query) {
                    $query->select(DB::raw('blocked_accounts.account_id'))
                        ->from('blocked_accounts');
                });
            $qry->where('users.id', '<>', $user_id);
            if ($access_point_fld) {
                if ($access_point_fld == 5) {//assigned schools
                    $school_qry_one = clone $qry;
                    $school_qry_one->whereIn('users.id', function ($query) use ($user_id) {
                        $query->select(DB::raw('user_school.user_id'))
                            ->from('user_school')
                            ->whereRaw("user_school.school_id=(
                                SELECT user_school.school_id from `user_school` where user_school.user_id=$user_id)" );
                    });
                    $school_level_data = $school_qry_one->get();
                    if($school_level_data->count() > 0){
                        $qry->whereIn('users.id', function ($query) use ($user_id) {
                            $query->select(DB::raw('user_school.user_id'))
                                ->from('user_school')
                                ->whereRaw("user_school.school_id=(
                                    SELECT user_school.school_id from `user_school` where user_school.user_id=$user_id)" );
                        });
                    } else {
                        $qry->whereIn('users.id', function ($query) use ($school_id) {
                            $query->select(DB::raw('user_school.user_id'))
                                ->from('user_school')
                                ->whereRaw("user_school.school_id=$school_id");
                        });
                    }
                } else if ($access_point_fld == 4) {//assigned districts
                    $qry->where('user_group.group_id', 58);
                } else if ($access_point_fld == 3) {//assigned provinces
                    $qry->where('user_group.group_id', 63);
                }  else if ($access_point_fld == 1 || $access_point_fld == 2) {//national level
                    $qry->whereIn('user_group.group_id', [16,64]);
                } else {
                    $qry->whereRaw('users.access_point_id=' . $access_point_fld);
                }
            }
            $data = $qry->get();
            // $data = $qry->toSql();
            // dd($data);
            $data = convertStdClassObjToArray($data);
            $data = decryptArray($data);
            $res = array(
                'success' => true,
                'message' => returnMessage($data),
                'users' => $data,
                'results' => $data,
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage(),
            );
        }
        return response()->json($res);
    }

    public function getCaseTotalLoadInfo(Request $request)
    {
        try {
            $user_id = $this->user_id;
            $user_access_point = Auth::user()->access_point_id;
            $workflow_stage_id = $request->input('workflow_stage_id');
            $province_id = $request->input('province_id');
            $district_id = $request->input('district_id');
            $case_status_id = $request->input('case_status_id');
            $target_group_id = $request->input('target_group_id');
            $dropout_reason_id = $request->input('dropout_reason_id');
            $careplan_id = $request->input('careplan_id');
            $careplan_provided_id = $request->input('careplan_provided_id');
            $filter = $request->input('filter');
            $girl_id = $request->input('girl_id');
            $year_id = $request->input('year_id');
            $validation_status = $request->input('validation_status');
            $target_grp_id = $request->input('target_grp_id'); 
            $school_id = $request->input('school_id');
            $lodge_date = $request->input('lodge_date') ? Carbon::createFromFormat('Y-m-d', Str::limit($request->input('lodge_date'), 10, ''))->format('Y/m/d') : null;
            $closure_date = $request->input('closure_date') ? Carbon::createFromFormat('Y-m-d', Str::limit($request->input('closure_date'), 10, ''))->format('Y/m/d') : null;
            $case_statuses = $request->input('case_statuses'); 
            $closure_reasons = $request->input('closure_reasons'); 
            $referral_reasons = $request->input('referral_reasons');
            //Get user grp details
            $user_grp_details = DB::table('users as t1')
                ->join('user_group as t2', 't2.user_id', '=', 't1.id')
                ->join('user_roles as t3', 't3.id', '=', 't1.user_role_id')
                ->select('t1.user_role_id','t2.user_id','t2.group_id',
                    DB::raw("t3.access_point_id,CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.last_name)) as logged_in_user,t3.name as user_role"))
                ->where('t1.id', $user_id)->first();
            $user_role_id = $user_grp_details ? $user_grp_details->user_role_id : null;
            $group_id = $user_grp_details ? $user_grp_details->group_id : null;
            $logged_in_user = $user_grp_details ? $user_grp_details->logged_in_user : null;
            $access_point_id = $user_grp_details ? $user_grp_details->access_point_id : null;
            $logged_user = $logged_in_user;
            $qry = DB::table('case_basicdataentry_details as t1')
                ->join('case_workflow_stages as t2', 't1.workflow_stage_id', '=', 't2.id')
                ->join('case_statuses as t3', 't1.record_status_id', '=', 't3.id')
                ->join('case_girl_details as t4', 't1.case_girl_id', '=', 't4.id')
                ->join('districts as t5', 't1.district_id', '=', 't5.id')
                ->join('case_target_groups as t7', 't1.target_group_id', '=', 't7.id')
                ->join('wf_kgsprocesses as t8', 't1.process_id', '=', 't8.id')
                ->leftJoin('school_information as t9', 't1.school_id', '=', 't9.id')
                ->leftJoin('users as t10', 't1.created_by', '=', 't10.id')
                ->select(DB::raw("t4.*,t1.*,t1.id as recordId,t2.name as workflow_stage,t2.interface_xtype,t2.tab_index,CONCAT_WS(' ',decrypt(t10.first_name),decrypt(t10.last_name)) as entry_by,
                    t5.name as district_name,t3.name as record_status,t7.name as target_group,t8.name as process_name,TOTAL_WEEKDAYS(t1.case_sysrecording_date,now()) as sys_recorded_span,
                    CONCAT_WS(' ',t1.informant_first_name,t1.informant_last_name) as informant_name,CONCAT_WS(' ',t1.personel_first_name,t1.personel_last_name) as person_filling_form_name,
                    CONCAT_WS(' ',t4.first_name,t4.last_name) as girl_name,t9.name as school_name,
                    TOTAL_WEEKDAYS(t1.case_sysrecording_date,now()) as recorded_span,'".$logged_in_user."' as logged_in_user,
                    '".$group_id."' as user_grp_id,'".$user_role_id."' as user_role_id,'".$user_id."' as user_id"));           
            if ($lodge_date != null && $closure_date != null) {//dates
                $qry->whereDate('t1.case_formrecording_date', '>=', $lodge_date);
                $qry->whereDate('t1.updated_at', '<=', $closure_date);
            }
            if (validateisNumeric($province_id)) {
                $qry->where('t1.province_id', $province_id);
            }
            if (validateisNumeric($district_id)) {
                $qry->where('t1.district_id', $district_id);
            }
            if (validateisNumeric($case_status_id)) {
                $qry->where('t1.record_status_id', $case_status_id);
            }
            if (validateisNumeric($target_group_id)) {
                $qry->where('t1.target_group_id', $target_group_id);
            }
            if (validateisNumeric($girl_id)) {
                $qry->where('t4.ben_girl_id', $girl_id);
            }
            if (validateisNumeric($dropout_reason_id)) {
                $qry->where('t1.dropout_reason_id', $dropout_reason_id);
            }
            if (isset($filter)) {
                $filters = json_decode($filter);
                $filter_string = $this->buildCaseSearchQuery($filters);
                if ($filter_string != '') {
                    $qry->whereRAW($filter_string);
                }
            }
            if (validateisNumeric($year_id)) {
                $qry->whereRaw("YEAR(t1.case_sysrecording_date)=$year_id");
            }
            if (validateisNumeric($validation_status)) {
                $qry->whereRaw("t1.validation_status=$validation_status");
            }   
            if (validateisNumeric($target_grp_id)) {
                $qry->whereRaw("t1.target_group_id=$target_grp_id");
            }
            if (validateisNumeric($school_id)) {
                $qry->where('t9.id', $school_id);
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

    public function tcpdfSamplePage(Request $request)
    {
        //============================================================+
        // File name   : TcpdfOutputExample.php
        // Begin       : 2008-03-04
        // Last Update : 2013-05-14
        //
        // Description : TcpdfOutputExample for TCPDF class
        //               WriteHTML and RTL support
        //
        // Author: Frank Otieno
        //
        // (c) Copyright:
        //               Frank Otieno
        //               Softclans Technologies LTD
        //               www.softclans.co.ke
        //               info@softclans.co.ke
        //============================================================+

        /**
         * Creates an example PDF TEST document using TCPDF
         * @package com.softclans.tcpdf
         * @abstract TCPDF - Example: WriteHTML and RTL support
         * @author Frank Otieno
         * @since 2008-03-04
         */

        // Include the main TCPDF library (search for installation path).
        // require_once('tcpdf_include.php');

        // create new PDF document
        //$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // set document information
        PDF::SetCreator('PDF_CREATOR');
        PDF::SetAuthor('Frank Otieno');
        PDF::SetTitle('TCPDF TcpdfOutputExample');
        PDF::SetSubject('TCPDF Tutorial');
        PDF::SetKeywords('TCPDF, PDF, example, test, guide');

        // set default header data
        PDF::SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE.' 006', PDF_HEADER_STRING);

        // set header and footer fonts
        PDF::setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        PDF::setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

        // set default monospaced font
        PDF::SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        // set margins
        PDF::SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        PDF::SetHeaderMargin(PDF_MARGIN_HEADER);
        PDF::SetFooterMargin(PDF_MARGIN_FOOTER);

        // set auto page breaks
        PDF::SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

        // set image scale factor
        PDF::setImageScale(PDF_IMAGE_SCALE_RATIO);

        // set some language-dependent strings (optional)
        if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
            require_once(dirname(__FILE__).'/lang/eng.php');
            PDF::setLanguageArray($l);
        }

        // ---------------------------------------------------------

        // set font
        PDF::SetFont('dejavusans', '', 10);

        // add a page
        PDF::AddPage();

        // writeHTML($html, $ln=true, $fill=false, $reseth=false, $cell=false, $align='')
        // writeHTMLCell($w, $h, $x, $y, $html='', $border=0, $ln=0, $fill=0, $reseth=true, $align='', $autopadding=true)

        // create some HTML content
        $html = '<h1>HTML Example</h1>
        Some special characters: &lt; € &euro; &#8364; &amp; è &egrave; &copy; &gt; \\slash \\\\double-slash \\\\\\triple-slash
        <h2>List</h2>
        List example:
        <ol>
            <li><img src="images/logo_example.png" alt="test alt attribute" width="30" height="30" border="0" /> test image</li>
            <li><b>bold text</b></li>
            <li><i>italic text</i></li>
            <li><u>underlined text</u></li>
            <li><b>b<i>bi<u>biu</u>bi</i>b</b></li>
            <li><a href="http://www.softclans.co.ke" dir="ltr">link to http://www.softclans.co.ke</a></li>
            <li>Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo.<br />Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt.</li>
            <li>SUBLIST
                <ol>
                    <li>row one
                        <ul>
                            <li>sublist</li>
                        </ul>
                    </li>
                    <li>row two</li>
                </ol>
            </li>
            <li><b>T</b>E<i>S</i><u>T</u> <del>line through</del></li>
            <li><font size="+3">font + 3</font></li>
            <li><small>small text</small> normal <small>small text</small> normal <sub>subscript</sub> normal <sup>superscript</sup> normal</li>
        </ol>
        <dl>
            <dt>Coffee</dt>
            <dd>Black hot drink</dd>
            <dt>Milk</dt>
            <dd>White cold drink</dd>
        </dl>';
        
        // output the HTML content
        PDF::writeHTML($html, true, false, true, false, '');


        // output some RTL HTML content
        $html = '<div style="text-align:center">The words &#8220;<span dir="rtl">&#1502;&#1494;&#1500; [mazel] &#1496;&#1493;&#1489; [tov]</span>&#8221; mean &#8220;Congratulations!&#8221;</div>';
        PDF::writeHTML($html, true, false, true, false, '');

        // test some inline CSS
        $html = '
            <p>This is just an example of html code to demonstrate some supported CSS inline styles.
                <span style="font-weight: bold;">bold text</span>
                <span style="text-decoration: line-through;">line-trough</span>
                <span style="text-decoration: underline line-through;">underline and line-trough</span>
                <span style="color: rgb(0, 128, 64);">color</span>
                <span style="background-color: rgb(255, 0, 0); color: rgb(255, 255, 255);">background color</span>
                <span style="font-weight: bold;">bold</span>
                <span style="font-size: xx-small;">xx-small</span>
                <span style="font-size: x-small;">x-small</span>
                <span style="font-size: small;">small</span>
                <span style="font-size: medium;">medium</span>
                <span style="font-size: large;">large</span>
                <span style="font-size: x-large;">x-large</span>
                <span style="font-size: xx-large;">xx-large</span>
            </p>
        ';

        PDF::writeHTML($html, true, false, true, false, '');

        // reset pointer to the last page
        PDF::lastPage();

        // - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
        // Print a table

        // add a page
        PDF::AddPage();

        // create some HTML content
        $subtable = '<table border="1" cellspacing="6" cellpadding="4"><tr><td>a</td><td>b</td></tr><tr><td>c</td><td>d</td></tr></table>';

        $html = '<h2>HTML TABLE:</h2>
        <table border="1" cellspacing="3" cellpadding="4">
            <tr>
                <th>#</th>
                <th align="right">RIGHT align</th>
                <th align="left">LEFT align</th>
                <th>4A</th>
            </tr>
            <tr>
                <td>1</td>
                <td bgcolor="#cccccc" align="center" colspan="2">A1 ex<i>amp</i>le <a href="http://www.tcpdf.org">link</a> column span. One two tree four five six seven eight nine ten.<br />line after br<br /><small>small text</small> normal <sub>subscript</sub> normal <sup>superscript</sup> normal  bla bla bla bla bla bla bla bla bla bla bla bla bla bla bla bla bla bla bla bla bla bla bla<ol><li>first<ol><li>sublist</li><li>sublist</li></ol></li><li>second</li></ol><small color="#FF0000" bgcolor="#FFFF00">small small small small small small small small small small small small small small small small small small small small</small></td>
                <td>4B</td>
            </tr>
            <tr>
                <td>'.$subtable.'</td>
                <td bgcolor="#0000FF" color="yellow" align="center">A2 € &euro; &#8364; &amp; è &egrave;<br/>A2 € &euro; &#8364; &amp; è &egrave;</td>
                <td bgcolor="#FFFF00" align="left"><font color="#FF0000">Red</font> Yellow BG</td>
                <td>4C</td>
            </tr>
            <tr>
                <td>1A</td>
                <td rowspan="2" colspan="2" bgcolor="#FFFFCC">2AA<br />2AB<br />2AC</td>
                <td bgcolor="#FF0000">4D</td>
            </tr>
            <tr>
                <td>1B</td>
                <td>4E</td>
            </tr>
            <tr>
                <td>1C</td>
                <td>2C</td>
                <td>3C</td>
                <td>4F</td>
            </tr>
        </table>';

        // output the HTML content
        PDF::writeHTML($html, true, false, true, false, '');

        // Print some HTML Cells

        $html = '<span color="red">red</span> <span color="green">green</span> <span color="blue">blue</span><br /><span color="red">red</span> <span color="green">green</span> <span color="blue">blue</span>';

        PDF::SetFillColor(255,255,0);

        PDF::writeHTMLCell(0, 0, '', '', $html, 'LRTB', 1, 0, true, 'L', true);
        PDF::writeHTMLCell(0, 0, '', '', $html, 'LRTB', 1, 1, true, 'C', true);
        PDF::writeHTMLCell(0, 0, '', '', $html, 'LRTB', 1, 0, true, 'R', true);

        // reset pointer to the last page
        PDF::lastPage();

        // - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
        // Print a table

        // add a page
        PDF::AddPage();

        // output the HTML content
        PDF::writeHTML('$html', true, false, true, false, '');

        // reset pointer to the last page
        PDF::lastPage();

        // - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
        // Print all HTML colors

        // add a page
        PDF::AddPage();

        // add a page
        PDF::AddPage();
        
        // - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

        // reset pointer to the last page
        PDF::lastPage();

        // ---------------------------------------------------------

        //Close and output PDF document
        PDF::Output('example_006.pdf', 'I');

        //============================================================+
        // END OF FILE
        //============================================================+
    }

}

