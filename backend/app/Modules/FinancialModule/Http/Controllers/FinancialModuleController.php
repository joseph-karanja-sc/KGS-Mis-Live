<?php

namespace App\Modules\FinancialModule\Http\Controllers;
use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Jobs\GenericSendEmailJob;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use PDF;



class FinancialModuleController extends BaseController
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
        return view('financialmodule::index');
    }
    public function getFinancialManagementParamFromTable(Request $request)
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
    //Budget
    public function getallBudgets(){
        try {

            $qry = DB::table('budget_allocation')
                ->join('thematic_areas', 'budget_allocation.thematic_id', '=', 'thematic_areas.id')
                ->join('financial_programmes','budget_allocation.programme_id','=','financial_programmes.id')
                ->join('financial_activities','budget_allocation.activitiy_id','=','financial_activities.id')
                ->leftjoin('cost_centers','budget_allocation.cost_center_id','=','cost_centers.id')
                ->select('budget_allocation.*','thematic_areas.name as thematic_area','thematic_areas.code as thematic_code','financial_programmes.programme_name as programme_name','financial_activities.activity_name','financial_activities.code as activity_code','cost_centers.name as cost_center');
                
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
    public function savebudgetdetails(Request $request){
        try{
             $id = $request->input('id');
             $user_id = $this->user_id;
             $table_name = $request->input('table_name');
             $table_data['thematic_id']=$request->input('thematic_id');
             $table_data['programme_id']=$request->input('programme_id');
             $table_data['cost_center_id']=$request->input('cost_center_id');
             $table_data['activitiy_id']=$request->input('activity_id');
             $table_data['budget_desc']=$request->input('budget_desc');
             $table_data['budget_amount_zmw']=$request->input('budget_amount_zmw');
             $table_data['budget_amount_dollar']=$request->input('budget_amount_dollar');
             $table_data['fiscal_year']=$request->input('fiscal_year');
             $table_data['rate']=$request->input('rate');
             $where = array(
                'id' => $id
            );
              //duplicate where
             /*$whereData=array('thematic_id'=>$request->input('thematic_id'),'fiscal_year'=>$request->input('fiscal_year'));*/
             $whereData=array('thematic_id'=>$request->input('thematic_id'),'activitiy_id'=>$request->input('activity_id'),'fiscal_year'=>$request->input('fiscal_year'));
             $duplicate=$this->func_recordexist($table_name,$whereData);

             if(validateisNumeric($id)){
                //Update budget details
                 $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $previous_data = getPreviousRecords($table_name, $where);
                $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                
             }else{
                //Add new Budget
                 $duplicate=$this->func_recordexist($table_name,$whereData);
                if($duplicate==true){
                    $res = array(
                        'success' => false,
                        'message' => "This Budget already exists!"
                    );
                }else{
                    $table_data['created_at'] = Carbon::now();
                    $table_data['created_by'] = $user_id;
                    $res = insertRecord($table_name, $table_data, $user_id);  
                }                  
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
        return response()->json($res);

    }
//Activities
    public  function getallActivities(Request $request){
        try {

            $programme_id = $request->input('programme_id');
            $qry = DB::table('financial_activities')
                ->join('financial_programmes', 'financial_activities.programme', '=', 'financial_programmes.id')
                ->select('financial_activities.*','financial_programmes.programme_name');
                if($programme_id){
                    $qry->where('programme',$programme_id);
                }
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
//Workplan

    public  function getallWorkplans(Request $request){
        try {
            $workflowStatusid=$request->input('workflowStatusid');
            $qry = DB::table('financial_workplan')
                ->join('thematic_areas', 'financial_workplan.thematic_area', '=', 'thematic_areas.id')
                ->join('financial_programmes', 'financial_workplan.programme_id', '=', 'financial_programmes.id')
                ->join('financial_activities', 'financial_workplan.activity', '=', 'financial_activities.id')
                ->join ('finance_workflow_stages','finance_workflow_stages.id','=','financial_workplan.workflow_stage_id')
                ->select(DB::raw("financial_workplan.*,thematic_areas.name as thematic_name,financial_activities.activity_name,financial_programmes.programme_name as programme_name,finance_workflow_stages.status as status,finance_workflow_stages.name as workflowstage,DATE_FORMAT(financial_workplan.date_from,'%Y-%m-%d') as date_from,DATE_FORMAT(financial_workplan.date_to, '%Y-%m-%d') as date_to,(SELECT rate FROM financial_currency_rates WHERE financial_currency_rates.current_rate_date =CURDATE()) as current_rate"))
                /*->select('financial_workplan.*','thematic_areas.name as thematic_name','financial_activities.activity_name','financial_programmes.programme_name as programme_name','finance_workflow_stages.status as status','finance_workflow_stages.name as workflowstage','DATE_FORMAT(financial_workplan.date_from, %d-%M-%Y) as date_from','DATE_FORMAT(financial_workplan.date_to, %d-%M-%Y) as date_to')*/
                  ->where('financial_workplan.workflow_stage_id', $workflowStatusid);
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
    public function getalltaskUsers(Request $req){
        try{
            $workplan_id =$req->input('workplan_id');
            $qry = DB::table('workplan_users')
                ->leftJoin('financial_workplan', 'financial_workplan.id','=','workplan_users.workplan_id')
                ->leftJoin('users', 'users.id','=','workplan_users.user_id')
                ->leftJoin('user_roles','user_roles.id','=','users.user_role_id')
                ->select(DB::raw("workplan_users.*, financial_workplan.task,user_roles.name as user_role,CONCAT_WS(' ',decrypt(users.first_name),decrypt(users.last_name)) as fullname"))
                //->select('workplan_users.*','CONCAT_WS(' ',decrypt(users.first_name),decrypt(users.last_name))as fullnames');
                ->where('workplan_users.workplan_id', $workplan_id);
                $data = $qry->get();
                //dd($data);
                $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'All is well'
            );

        }catch (\Exception $e){
             $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }
    public function getallTaskComments(Request $req){
        $workplan_id =$req->input('workplan_id');
        try{
            $qry = DB::table('workplan_comments')
                ->leftjoin('users', 'workplan_comments.created_by', '=', 'users.id')
                ->leftjoin('user_roles','user_roles.id','=','users.user_role_id')
                ->select(DB::raw("workplan_comments.*,CONCAT_WS(decrypt(first_name),' ',decrypt(last_name)) as fullnames"))
                 ->where('workplan_comments.workplan_id', $workplan_id);
               /* ->select('workplan_comments.*',
                    'CONCAT(decrypt(users.first_name),decrypt(users.last_name))as fullnames)');*/
                $data = $qry->get();
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'All is well'
            );

        }catch (\Exception $e){
             $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }
    public function saveWorkplantasks(Request $request){
        try{
             $id = $request->input('mainId');
             $user_id = $this->user_id;
             $table_name = $request->input('table_name');
             $table_data['thematic_area']=$request->input('thematic_area');
             $table_data['programme_id']=$request->input('programme_id');
             $table_data['cost_center']=$request->input('cost_center');
             $table_data['activity']=$request->input('activity');
             $table_data['task']=$request->input('task');
             $table_data['task_desc']=$request->input('task_desc');
             $table_data['responsible_users']=$request->input('responsible_users');
             $table_data['workflow_stage_id']=$request->input('workflow_stage_id');
             $table_data['budget_id']=$request->input('budget_id');
             $table_data['budgetAmount']=$request->input('budgetAmount');
             $dt = Carbon::now();
             $datefrom= new Carbon($request->input('date_from'));
             $table_data['date_from'] = $datefrom->format("Y-m-d ".$dt->hour.":".$dt->minute.":".$dt->second);
              $dt = Carbon::now();
             $date_to= new Carbon($request->input('date_to'));
             $table_data['date_to']=$date_to->format("Y-m-d ".$dt->hour.":".$dt->minute.":".$dt->second);//
             $process_id=5;
             $where = array('id' => $id);
             if(validateisNumeric($id)){
                //Update Task details
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $previous_data = getPreviousRecords($table_name, $where);
                $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                
             }else{
                //Add new Tasks
                 $ref_details = generateRecordRefNumber(6, $process_id, 0, array(), $table_name, $user_id);//generateRefNumber(array(),6);
                    if ($ref_details['success'] == false) {
                        return \response()->json($ref_details);
                    }
                    $mis_no = $ref_details['ref_no'];
                    //todo DMS
                    $parent_id = 258527;//258529;
                    $folder_id = createDMSParentFolder($parent_id, '', $mis_no, '', $this->dms_id);
                    createDMSModuleFolders($folder_id, 37, $this->dms_id);//39
                    //end DMS
                    $table_data['view_id'] = generateRecordViewID();
                    $table_data['mis_no'] = $mis_no;
                    $table_data['folder_id'] = $folder_id;
                    $table_data['created_at'] = Carbon::now();
                    $table_data['created_by'] = $user_id;
                    $res = insertRecord($table_name, $table_data, $user_id);    
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
        return response()->json($res);
    }
    //Reports

    public function getActivitiesOverBudget(){
        try {
            $qry = DB::table('activities_over_budget')
                ->join('financial_implementation', 'financial_implementation.id', '=', 'activities_over_budget.implementation_id')
                ->join('financial_workplan', 'financial_workplan.id', '=', 'financial_implementation.workplan_id')
                ->join('thematic_areas', 'financial_workplan.thematic_area', '=', 'thematic_areas.id')
                ->join('financial_activities', 'financial_workplan.activity', '=', 'financial_activities.id')
                ->select(DB::raw("financial_workplan.*,thematic_areas.name as thematic_name,financial_activities.activity_name,financial_implementation.*"));
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

    public function getWorkPlanCountPerThematicArea(Request $request)
    {
        try {
            // $user_access_point = Auth::user()->access_point_id;
            $logged_in_user = $this->user_id;
            $year = $request->input('year');
            $qry = DB::table('financial_workplan as t1')
                ->leftJoin('thematic_areas as t2', 't1.thematic_area', '=', 't2.id')
                ->select(DB::raw("t1.id,t1.thematic_area,COUNT(DISTINCT t1.id) as no_of_records,t2.name as group_name"));
            // if (validateisNumeric($year)) {
            //     $qry->whereRaw("YEAR(t1.case_sysrecording_date)=$year");
            // }
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

    public function getWorkPlanCountPerCostCentre(Request $request)
    {
        try {
            // $user_access_point = Auth::user()->access_point_id;
            $logged_in_user = $this->user_id;
            $year = $request->input('year');
            $qry = DB::table('financial_workplan as t1')
                ->leftJoin('cost_centers as t2', 't1.cost_center', '=', 't2.id')
                ->select(DB::raw("t1.id,t1.cost_center,COUNT(DISTINCT t1.id) as no_of_records,t2.name as group_name"));
            // if (validateisNumeric($year)) {
            //     $qry->whereRaw("YEAR(t1.case_sysrecording_date)=$year");
            // }
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

    public function getWorkPlanCountPerProgramme(Request $request)
    {
        try {
            // $user_access_point = Auth::user()->access_point_id;
            $logged_in_user = $this->user_id;
            $year = $request->input('year');
            $qry = DB::table('financial_workplan as t1')
                ->leftJoin('financial_programmes as t2', 't1.programme_id', '=', 't2.id')
                ->select(DB::raw("t1.id,t1.programme_id,COUNT(DISTINCT t1.id) as no_of_records,t2.programme_name as group_name"));
            // if (validateisNumeric($year)) {
            //     $qry->whereRaw("YEAR(t1.case_sysrecording_date)=$year");
            // }
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
    
    public function uploadBudget(Request $request)
     {
            try{
                $res=array();
                $user_id=$this->user_id;
                $table_data=array();
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
                        if(count($table_data)>0){  
                            unset($table_data[0]);
                            unset($table_data[1]);
                            unset($table_data[2]);
                            unset($table_data[3]);
                            unset($table_data[4]);
                            unset($table_data[5]);
                            $tblkeystounset=array();
                            foreach($table_data as $key=>$budget_data)
                                {  
                                    foreach($budget_data as $keybudget=>$budget){
                                        if($keybudget < 3 && ($budget == null || $budget == '')){
                                            if(!in_array($key,$tblkeystounset)){
                                                 $tblkeystounset[]=$key;
                                            }
                                        }
                                    }
                                    $budgetanalysis=array();
                                    $new_date_to='';
                                    $new_date_from='';
                                    //Format date from and date to
                                    if (!in_array($key, $tblkeystounset))
                                    {
                                        $datetoObj = \DateTime::createFromFormat("d/m/Y", $budget_data[15]);
                                        if(!$datetoObj ){
                                            throw new \UnexpectedValueException("Could not parse the date:  $budget_data[15] @ $key+1");
                                        }
                                        $new_date_to= $datetoObj->format("Y-m-d"); 
                                    }
                                   
                                    if (!in_array($key, $tblkeystounset))
                                    {
                                        $datefromObj = \DateTime::createFromFormat("d/m/Y", $budget_data[14]);
                                        if(!$datefromObj){
                                            throw new \UnexpectedValueException("Could not parse the date:  $budget_data[14] $key+1");
                                        }
                                         $new_date_from= $datefromObj->format("Y-m-d");
                                    }
                                                    
                                    $budgetanalysis['thematic_name']=$budget_data[0];
                                    $budgetanalysis['thematic_code']=$budget_data[1];
                                    $budgetanalysis['programme_name']=$budget_data[2];
                                    $budgetanalysis['programme_code']=$budget_data[3];
                                    $budgetanalysis['activity_name']=$budget_data[4];
                                    $budgetanalysis['activity_code']=$budget_data[5];
                                    $budgetanalysis['budget_desc']=$budget_data[6];
                                    $budgetanalysis['fiscal_year']=$budget_data[7];
                                    $budgetanalysis['currency_rate']=$budget_data[8];
                                    $budgetanalysis['responsible_user']=$budget_data[9];
                                    $budgetanalysis['budget_amount_zmw']=$budget_data[10];
                                    if(!$budget_data[10]==null && $budget_data[10]!=0 && $budget_data[8]!=0 && !$budget_data[8]==null){
                                        $budgetanalysis['budget_amount_dollar']=$budget_data[10]/$budget_data[8];
                                    } 
                                    $budgetanalysis['source_funds']=$budget_data[12];
                                    $budgetanalysis['finance_period']=$budget_data[13];
                                    //$date_from= new Carbon($request->input($budget_data[14]));
                                    $budgetanalysis['date_from']=$new_date_from;//$date_to->format("Y-m-d ");
                                   // $date_to= new Carbon($request->input($budget_data[15]));
                                    $budgetanalysis['date_to']=$new_date_to;//$date_to->format("Y-m-d ");
                                    $budgetanalysis['expected_output']=$budget_data[16];
                                    $budgetanalysis['comment']=$budget_data[17];
                                    $budgetanalysis['created_at']=Carbon::now();
                                    $budgetanalysis['created_by']=$this->user_id;
                                    $budgetanalysis['updated_by']=$this->user_id;
                                   //todo DMS
                                       $parent_id = 258527;
                                       $folder_id = createDMSParentFolder($parent_id, '', 'Budget', '', $this->dms_id);
                                       createDMSModuleFolders($folder_id, 37, $this->dms_id);
                                       //End
                                   $budgetanalysis['folder_id']=$folder_id;
                                   $budgetanalysis['view_id'] = generateRecordViewID();      
                                   $table_data[$key]=$budgetanalysis;
                                  
                               };      
                               foreach($tblkeystounset as $tounset){
                                    unset($table_data[$tounset]);
                               }
                               $info=DB::table('budget_allocation_temp_data')->insert($table_data);  
                               if($info==true)
                                  {
                                       //get the import data
                                        $qry = DB::table('budget_allocation_temp_data')
                                           ->select('*')
                                           ->where('is_validated', 0);
                                       $importedData = $qry->get();
                                       //add imported data
                                       foreach($importedData as $value){
                                           $formatted =array(
                                                   'fiscal_year'=>$value->fiscal_year,
                                                   'thematic_id'=>DB::table('thematic_areas')->select('id')->where('code',$value->thematic_code)->value('id'),
                                                   'budget_desc'=>$value->budget_desc,
                                                   'programme_id'=>DB::table('financial_programmes')->select('id')->where('programme_code',$value->programme_code)->value('id'),
                                                   'activitiy_id'=>DB::table('financial_activities')->select('id')->where('code',$value->activity_code)->value('id'),
                                                   'created_by'=>$this->user_id,
                                                   'rate'=>$value->currency_rate,
                                                   'budget_amount_zmw'=>$value->budget_amount_zmw,
                                                   'budget_amount_dollar'=>$value->budget_amount_dollar,
                                                    'source_funds'=>$value->source_funds,
                                                    'finance_period'=>DB::table('finance_periods')->select('id')->where('name',$value->finance_period)->value('id'),
                                                    'date_from'=>$value->date_from,
                                                    'date_to'=>$value->date_to,              
                                                    'expected_output'=>$value->expected_output,
                                                    'comment'=>$value->expected_output,
                                                   'updated_by'=>$this->user_id,
                                                   'created_at'=>Carbon::now(),
                                                   'updated_at'=>Carbon::now()
                                               );
                                            $whereData=array('thematic_id'=>$formatted['thematic_id'],'activitiy_id'=>$formatted['activitiy_id'],'fiscal_year'=>$formatted['fiscal_year']);
                                                $duplicate=$this->func_recordexist('budget_allocation',$whereData);
                                               if($duplicate==true){
                                                   DB::table('budget_allocation_temp_data')
                                                   ->where('id', $value->id)
                                                   ->update(['is_validated' => 2]);

                                               }else{
                                                   $res = insertRecord('budget_allocation', $formatted, $user_id); 
                                                       if( $res['success']==true){
                                                            DB::table('budget_allocation_temp_data')
                                                           ->where('id', $value->id)
                                                           ->update(['is_validated' => 1]);
                                                       } 
                                                   }
                                       }

                                       $res=array("success"=>true,"message"=>"Budget Upload Successfuly!");
                               }else{
                                       $res=array("success"=>false,"message"=>"Budget Upload Failed"); 
                               }
                       
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
    public function archiveplan(Request $request){
        try{
             $id = $request->input('mainId');
             $table_name = 'financial_implementation';
             $user_id=$this->user_id;
             $where = array('id' => $id);
            if(validateisNumeric($id)){
                //Update Activities
                $table_data['workflow_stage_id']=6;
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $previous_data = getPreviousRecords($table_name, $where);
                $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);     
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
         return response()->json($res);

    }

    public function func_recordexist($table_name,$whereData)
    {
        $duplicate = false;
        $count =DB::table($table_name)->where($whereData)->count();
        if ($count >0) {
            $duplicate = true;
        }
        return $duplicate;        
    }

    public function saveWorkplanusers(Request $request){
        try{
             $id = $request->input('id');
             $user_id = $this->user_id;
             $table_name = 'workplan_users';
             $table_data['user_id']=$request->input('user_id');
             $table_data['workplan_id']=$request->input('workplan_id');
             $where = array('id' => $id);
             $column_name='user_id';
             $whereData= array('user_id'=>$request->input('user_id'),
                                'workplan_id'=>$request->input('workplan_id'));
             $duplicate=$this->func_recordexist($table_name,$whereData);
             if($duplicate==true){
                 $res = array(
                'success' => false,
                'message' => "The User already exists!"
                 );

             }else{
                if(validateisNumeric($id)){
                //Update Users
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $previous_data = getPreviousRecords($table_name, $where);
                $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);   
                 }else{
                    //Add new users
                    $table_data['created_at'] = Carbon::now();
                    $table_data['created_by'] = $user_id;
                    $res = insertRecord($table_name, $table_data, $user_id);    
                }

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
        return response()->json($res);
    }

    public function saveWorkPlanCommentsDetails(Request $request){
          try{
             $id = $request->input('id');
             $table_name = $request->input('table_name');
             $user_id=$this->user_id;
             $table_data['workplan_id']=$request->input('workplan_id');
             $table_data['comment']=$request->input('comment');
             $where = array('id' => $id);
            if(validateisNumeric($id)){
                //Update Comments
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $previous_data = getPreviousRecords($table_name, $where);
                if($request->input('user_id')==$user_id){
                    $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);  
                }else{
                    $res = array(
                        'success' => false,
                        'message' => 'You can only update your comments'
                    );

                }                 
            }else{
                //Add new Comments
                $table_data['user_id']= $user_id;
                $table_data['created_at'] = Carbon::now();
                $table_data['created_by'] = $user_id;
                $res = insertRecord($table_name, $table_data, $user_id);    
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
         return response()->json($res);
    }
     public function getAllImplementationcostingrec(Request $request){
         try {
                $workflow_stage_id = $request->input('workflow_stage_id');

                $qry = DB::table('financial_implementation')
                    ->join('financial_workplan', 'financial_workplan.id', '=', 'financial_implementation.workplan_id')
                    ->join('thematic_areas', 'financial_workplan.thematic_area', '=', 'thematic_areas.id')
                    ->join('financial_activities', 'financial_workplan.activity', '=', 'financial_activities.id')
                    ->select(DB::raw("financial_workplan.*,thematic_areas.name as thematic_name,financial_activities.activity_name,financial_implementation.*,(SELECT rate FROM financial_currency_rates WHERE financial_currency_rates.current_rate_date =CURDATE()) as current_rate,DATE_FORMAT(financial_workplan.date_from,'%Y-%m-%d') as date_from,DATE_FORMAT(financial_workplan.date_to, '%Y-%m-%d') as date_to"))
                    ->where('financial_implementation.workflow_stage_id',$workflow_stage_id);
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
    public function updateworkplanstatus(Request $request){
        try{ 
             $workplan_id = $request->input('workplan_id');
             $where = array('id' => $workplan_id);
             $workplantbl='financial_workplan';
             $implementationtbl='financial_implementation';
             $user_id=$this->user_id;
             if(validateisNumeric($workplan_id)){
                //Insert New Implementation record 
                $implementation_data['workplan_id']=$workplan_id;
                $implementation_data['workflow_stage_id']=3;
                $implementation_data['created_at'] = Carbon::now();
                $implementation_data['created_by'] = $user_id;
                $res = insertRecord($implementationtbl, $implementation_data, $user_id); 
                if($res['success']==true){
                    //Update workplan transaction status
                     $table_data['workflow_stage_id']=3;
                    $table_data['submitted']=1;
                    $table_data['updated_at'] = Carbon::now();
                    $table_data['updated_by'] = $user_id;
                    $previous_data = getPreviousRecords($workplantbl, $where);
                    $res = updateRecord($workplantbl, $previous_data, $where, $table_data, $user_id); 
                 }  
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
         return response()->json($res);

    }
    public function assignedtasks(Request $request){
        try{
            $mgs="Hello, a Task is assigned to you. The task referrence number is".$request->input('mis_no')."to you.";
            $subject="This is a subject";
            $mail_to_person="maureen.wagema@softclans.co.ke";
            $id = $request->input('mainId');
            $where = array('id' => $id);
            $implementationtbl='financial_implementation';
            $assigntaskstbl='financial_tasks_assinged';
            $financial_activities_due="financial_activities_due";
            $user_id=$this->user_id;
            $implementation_id=$request->input('id');
            $workplan_id=$request->input('workplan_id');
            $users_email= array();
            $workflow_stage_id=$request->input('workflow_stage_id');
            if($workflow_stage_id==4){
                if(validateisNumeric($id)){
                //Insert New Tasks record 
                $assigntask_data['implementation_id']=$id;
                $assigntask_data['created_at'] = Carbon::now();
                $assigntask_data['created_by'] = $user_id;
                $res = insertRecord($assigntaskstbl, $assigntask_data, $user_id); 
                if($res['success']==true){
                    //Update alerts
                    $alertstbl_data['implementation_id']=$id;
                    $alertstbl_data['is_active']=1;
                    $alertstbl_data['created_at'] = Carbon::now();
                    $alertstbl_data['created_by'] = $user_id;
                    $res = insertRecord($financial_activities_due, $alertstbl_data, $user_id);
                    if($res['success']==true){
                        //email
                         //Get emails
                    $emialqry = DB::table('workplan_users')
                     ->join('users', 'workplan_users.id', '=', 'users.id')
                     ->select(DB::raw("decrypt(users.email) as email"))
                     ->where('workplan_users.workplan_id','=',$workplan_id);
                    $users_email = $emialqry->get()->toArray();
                       // foreach ($users_email as $key => $emailcon) {
                        $data=array('msg' => $mgs);
                        $result= Mail::send('mail.financeEmail',$data,function($message) use($mail_to_person,$subject,$data){
                                $message->to($mail_to_person,$mail_to_person);
                                $message->subject($subject);
                                $message->from('mogekgs@gmail.com','MOGE');
                           }); 
                      //  }
                        
                        
                    }
                    //Update implementation plan
                    $table_data['workflow_stage_id']=5;
                    $table_data['submitted']=1;
                    $table_data['updated_at'] = Carbon::now();
                    $table_data['updated_by'] = $user_id;
                    $previous_data = getPreviousRecords($implementationtbl, $where);
                    $res = updateRecord($implementationtbl, $previous_data, $where, $table_data, $user_id); 
                 }  
            }

            }else {
                 //Update implementation plan
                    $table_data['workflow_stage_id']=3;
                    $table_data['updated_at'] = Carbon::now();
                    $table_data['updated_by'] = $user_id;
                    $previous_data = getPreviousRecords($implementationtbl, $where);
                    $res = updateRecord($implementationtbl, $previous_data, $where, $table_data, $user_id);

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
         return response()->json($res);
    }
    public function getFinaltaskAssignedList(){
          try {

                $qry = DB::table('financial_tasks_assinged')
                     ->join('financial_implementation', 'financial_implementation.id', '=', 'financial_tasks_assinged.implementation_id')
                    ->join('financial_workplan', 'financial_workplan.id', '=', 'financial_implementation.workplan_id')
                    ->join('thematic_areas', 'financial_workplan.thematic_area', '=', 'thematic_areas.id')
                    ->join('financial_activities', 'financial_workplan.activity', '=', 'financial_activities.id')
                    ->select(DB::raw("financial_workplan.*,thematic_areas.name as thematic_name,financial_activities.activity_name,financial_implementation.*"))
                    ->where('financial_implementation.workflow_stage_id','5');
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
    public function getschedulerecords(){
        try {
                $qry=DB::table('calender_details')
                ->select('*');
                    $data = $qry->get();
                $res = array(
                    'calendars' => $data
                );
        } catch (\Exception $e) {
                $res = array(
                    'success' => false,
                    'message' => $e->getMessage()
                );
        }
            return response()->json($data);
    }
    public function calenderevents(){
        try {

                $qry = DB::table('financial_tasks_assinged')
                     ->join('financial_implementation', 'financial_tasks_assinged.implementation_id', '=', 'financial_implementation.id')
                    ->join('financial_workplan', 'financial_workplan.id', '=', 'financial_implementation.workplan_id')
                    ->join('financial_programmes', 'financial_workplan.programme_id', '=', 'financial_programmes.id')
                    ->select('financial_tasks_assinged.id as id','financial_tasks_assinged.calendarId as calendarId','financial_workplan.date_from AS startDate','financial_workplan.date_to AS endDate','financial_programmes.programme_name AS title');
                    $data = $qry->get();
                $res = array(
                    'results' => $data
                );
        } catch (\Exception $e) {
                $res = array(
                    'success' => false,
                    'message' => $e->getMessage()
                );
        }
            return response()->json($data);

    }
    public function deleteWorkplanRecord(Request $req)
    {
        $record_id = $req->id;
        $table_name = $req->table_name;
        $user_id = Auth::user()->id;
        $where = array(
            'id' => $record_id
        );
        $childwhere =array(
            'workplan_id'=>$record_id);
        try {
            $previous_user_data = getPreviousRecords('workplan_users', $childwhere);
            deleteRecord('workplan_users', $previous_user_data, $childwhere, $user_id);
            $previous_comment_data = getPreviousRecords('workplan_comments', $childwhere);
            deleteRecord('workplan_comments', $previous_comment_data, $childwhere, $user_id);
           /* $previous_doc_data = getPreviousRecords('workplan_comments', $childwhere);
            deleteRecord('workplan_comments', $previous_comment_data, $childwhere, $user_id);*/
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
    public function saveActivities(Request $request)
    {
        // code...
         try{
             $id = $request->input('id');
             $table_name = $request->input('table_name');
             $user_id=$this->user_id;
             $table_data['programme']=$request->input('programme');
             $table_data['activity_name']=$request->input('activity_name');
              $table_data['code']=$request->input('code');
             $table_data['activity_explanation']=$request->input('activity_explanation');
             $where = array('id' => $id);
            if(validateisNumeric($id)){
                //Update Activities
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $previous_data = getPreviousRecords($table_name, $where);
                $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);  
                 
            }else{
                    //Add new Activities
                    $table_data['created_at'] = Carbon::now();
                    $table_data['created_by'] = $user_id;
                    $res = insertRecord($table_name, $table_data, $user_id);    
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
         return response()->json($res);
    }
    public function getCurrencyRates(){
        try{
        $qry = DB::table('currency as t1')
                ->join('financial_currency_rates as t2', 't1.id', '=', 't2.from_currency')
                ->join('currency as t3','t3.id','=','t2.to_currency')
                ->select('t1.NAME AS currency_from','t2.*','t3.NAME AS currency_to');
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

    public function getActivitiesdue(){
          try {

                $qry = DB::table('financial_activities_due')
                     ->join('financial_implementation', 'financial_implementation.id', '=', 'financial_activities_due.implementation_id')
                    ->join('financial_workplan', 'financial_workplan.id', '=', 'financial_implementation.workplan_id')
                    ->join('thematic_areas', 'financial_workplan.thematic_area', '=', 'thematic_areas.id')
                    ->join('financial_activities', 'financial_workplan.activity', '=', 'financial_activities.id')
                    ->select(DB::raw("financial_workplan.*,thematic_areas.name as thematic_name,financial_activities.activity_name,financial_implementation.*"));
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
    public function getAllcostinglist(Request $req){
        $implementation_id =$req->input('implementation_id');
        try{
            $qry = DB::table('financial_implementation_costing')
                ->leftjoin('financial_implementation', 'financial_implementation.id', '=', 'financial_implementation_costing.implementation_id')
                ->select('financial_implementation_costing.*')
                 ->where('financial_implementation_costing.implementation_id', $implementation_id);
                $data = $qry->get();
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'All is well'
            );

        }catch (\Exception $e){
             $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);

    }
        public function getgrandTotal($implementation_id){
                        //get all totals
        $implementation_data['unit_days'] = DB::table('financial_implementation_costing')
                                                    ->where('financial_implementation_costing.implementation_id', '=', $implementation_id)
                                                    ->sum('unit_days');
        $implementation_data['cost_per_unit'] = DB::table('financial_implementation_costing')
                                                        ->where('financial_implementation_costing.implementation_id', '=',$implementation_id1)
                                                        ->sum('cost_per_unit');

        $implementation_data['num_of_units']=DB::table('financial_implementation_costing')
                                                        ->where('financial_implementation_costing.implementation_id', '=',$implementation_id1)
                                                        ->sum('num_of_units');
        $implementation_data['total_cost_per_item']=DB::table('financial_implementation_costing')
                                                        ->where('financial_implementation_costing.implementation_id', '=',$implementation_id1)
                                                        ->sum('total_cost_per_item');
        $implementation_data['total_cost_zmw']=DB::table('financial_implementation_costing')
                                                        ->where('financial_implementation_costing.implementation_id', '=',$implementation_id1)
                                                        ->sum('total_cost_zmw');
        $implementation_data['total_cost_usd']=DB::table('financial_implementation_costing')
                                                        ->where('financial_implementation_costing.implementation_id', '=',$implementation_id1)
                                                        ->sum('total_cost_usd');
        //Previous data
        $table_name='financial_implementation';
        $user_id=$this->user_id;
        $where = array('id' => $implementation_id);
        $previous_data = getPreviousRecords($table_name, $where);
        $res = updateRecord($table_name, $previous_data, $where, $implementation_data, $user_id);  
    }
        public function saveCost(Request $request){
       try{
             $id = $request->input('id');
             $user_id= $this->user_id;
             $implementation_id=$request->input('implementation_id');
             $table_name = $request->input('table_name');
             $table_data['items']=$request->input('items');
             $table_data['implementation_id']=$request->input('implementation_id');
             $table_data['unit_days']=$request->input('unit_days');
             $table_data['cost_per_unit']=$request->input('cost_per_unit');
             $table_data['num_of_units']=$request->input('num_of_units');
             $table_data['total_cost_per_item']=$request->input('total_cost_per_item');
             $table_data['frequency']=$request->input('frequency');
             $table_data['rate']=$request->input('rate');
             $table_data['total_cost_usd']=$request->input('total_cost_usd');
             $table_data['total_cost_zmw']=$request->input('total_cost_zmw');
             $table_data['comment']=$request->input('comment');
             $table_data['expected_output']=$request->input('expected_output');
             $where = array('id' => $id);
             //duplicate where
             $whereData=array('items'=>$request->input('items'));
             $duplicate=$this->func_recordexist($table_name,$whereData);
             if(validateisNumeric($id)){
                //Update Costs
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $previous_data = getPreviousRecords($table_name, $where);
                $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);  
                if($res['success']=true){
                   // $this->getgrandTotal( $implementation_id);
                     $implementation_data['unit_days'] = DB::table('financial_implementation_costing')
                                                    ->where('financial_implementation_costing.implementation_id', '=', $implementation_id)
                                                    ->sum('unit_days');
                    $implementation_data['cost_per_unit'] = DB::table('financial_implementation_costing')
                                                                    ->where('financial_implementation_costing.implementation_id', '=',$implementation_id)
                                                                    ->sum('cost_per_unit');

                    $implementation_data['num_of_units']=DB::table('financial_implementation_costing')
                                                                    ->where('financial_implementation_costing.implementation_id', '=',$implementation_id)
                                                                    ->sum('num_of_units');
                    $implementation_data['total_cost_per_item']=DB::table('financial_implementation_costing')
                                                                    ->where('financial_implementation_costing.implementation_id', '=',$implementation_id)
                                                                    ->sum('total_cost_per_item');
                    $implementation_data['total_cost_zmw']=DB::table('financial_implementation_costing')
                                                                    ->where('financial_implementation_costing.implementation_id', '=',$implementation_id)
                                                                    ->sum('total_cost_zmw');
                    $implementation_data['total_cost_usd']=DB::table('financial_implementation_costing')
                                                                    ->where('financial_implementation_costing.implementation_id', '=',$implementation_id)
                                                                    ->sum('total_cost_usd');
                    //Previous data
                    $table_name='financial_implementation';
                    $user_id=$this->user_id;
                    $where = array('id' => $implementation_id);
                    $previous_data = getPreviousRecords($table_name, $where);
                    updateRecord($table_name, $previous_data, $where, $implementation_data, $user_id);  
                }
                 
            }else{
                if($duplicate==true){
                     $res = array(
                    'success' => false,
                    'message' => "The Item already exists!"
                     );

                 }else{
                        //Add new Cost
                        $table_data['created_at'] = Carbon::now();
                        $table_data['created_by'] = $user_id;
                        $res = insertRecord($table_name, $table_data, $user_id); 
                         if($res['success']=true){
                             $implementation_data['unit_days'] = DB::table('financial_implementation_costing')
                                                            ->where('financial_implementation_costing.implementation_id', '=', $implementation_id)
                                                            ->sum('unit_days');
                            $implementation_data['cost_per_unit'] = DB::table('financial_implementation_costing')
                                                                            ->where('financial_implementation_costing.implementation_id', '=',$implementation_id)
                                                                            ->sum('cost_per_unit');

                            $implementation_data['num_of_units']=DB::table('financial_implementation_costing')
                                                                            ->where('financial_implementation_costing.implementation_id', '=',$implementation_id)
                                                                            ->sum('num_of_units');
                            $implementation_data['total_cost_per_item']=DB::table('financial_implementation_costing')
                                                                            ->where('financial_implementation_costing.implementation_id', '=',$implementation_id)
                                                                            ->sum('total_cost_per_item');
                            $implementation_data['total_cost_zmw']=DB::table('financial_implementation_costing')
                                                                            ->where('financial_implementation_costing.implementation_id', '=',$implementation_id)
                                                                            ->sum('total_cost_zmw');
                            $implementation_data['total_cost_usd']=DB::table('financial_implementation_costing')
                                                                            ->where('financial_implementation_costing.implementation_id', '=',$implementation_id)
                                                                            ->sum('total_cost_usd');
                            //Previous data
                            $table_name='financial_implementation';
                            $user_id=$this->user_id;
                            $where = array('id' => $implementation_id);
                            $previous_data = getPreviousRecords($table_name, $where);
                            updateRecord($table_name, $previous_data, $where, $implementation_data, $user_id);  
                        }   
                     }
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
         return response()->json($res);


    }




















    //Maureen
    public function test(){
        $qry = DB::table('financial_commitment_requsition')
            ->select('*');
            foreach($importedData as $value){
                 $formatted =array(
                        'test_id'=>$value->id,
                        'Thematic_Code'=>$value->code,
                        'thematic_id'=>DB::table('thematic_areas')->select('id')->where('code',$value->code)->value('id')
                    );
                

            }

        print_r( $formatted);
    }

    public function getAllRequisitionList(){
    try {

            $qry = DB::table('financial_commitment_requsition')
                ->select('*');
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
    public function saveRequisitionDet(Request $request)
    {
        // code...
        try{
             $id = $request->input('id');
             $user_id = $this->user_id;
             $table_name = $request->input('table_name');
             $table_data['description']=$request->input('description');
             $table_data['budget']=$request->input('budget');
             $table_data['requested_by']=$request->input('requested_by');
             $table_data['code']=$request->input('code');
             $table_data['cum_exp']=$request->input('cum_exp');
             $table_data['budget_id']=$request->input('budget_id');
             $table_data['balance']=$request->input('balance');
             $where = array(
                'id' => $id
            );

             /*$whereData=array(''=>$request->input(''));
             $duplicate=$this->func_recordexist($table_name,$whereData);*/

             if(validateisNumeric($id)){
                //Update Requisition details
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $previous_data = getPreviousRecords($table_name, $where);
                $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                
             }else{
                //Add new Requisition
                    $table_data['created_at'] = Carbon::now();
                    $table_data['created_by'] = $user_id;
                    $res = insertRecord($table_name, $table_data, $user_id);  
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
        return response()->json($res);
    }
    public function printcommitmentReqForm(Request $request)
    {
        $record_id=$request->input('record_id');
        $doc_url='/reports/Kgs_misv2/Finance/';
        $report='';
        try{
          if (validateisNumeric($record_id)){
             $params = array('record_id' => $record_id);
             $report = generateJasperReportwithcustomurl('commitment_requisition', 'commitment_requsition' . time(), 'pdf', $params,$doc_url);
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
//End Maureen

}
