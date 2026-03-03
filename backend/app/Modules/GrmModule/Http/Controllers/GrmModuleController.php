<?php

namespace App\Modules\GrmModule\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Jobs\ComplaintSubmissionEmailJob;
use App\Modules\GrmModule\Traits\GrmModuleTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GrmModuleController extends BaseController
{
    use GrmModuleTrait;

    public function index()
    {
        return view('grmmodule::index');
    }

    public function saveGrmModuleCommonData(Request $req)
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
            $table_data = $post_data;//encryptArray($post_data, $skipArray);
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

    public function getGrmModuleParamFromModel($model_name)
    {
        $model = 'App\\Modules\\GrmModule\\Entities\\' . $model_name;
        $results = $model::all()->toArray();
        $results = decryptArray($results);
        $res = array(
            'results' => $results
        );
        return response()->json($res);
    }

    public function getGrmModuleParamFromTable(Request $request)
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

    public function deleteGrmModuleRecord(Request $request)
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

    public function getAllLetterTypes()
    {
        $letterTypes = DB::table('grm_lettertypes')->get();
        $foundletterTypes = array(
            'letterTypes' => $letterTypes
        );
        return response()->json($foundletterTypes);
    }

    public function saveComplaintDetails(Request $request)
    {
        DB::beginTransaction();
        $validator = Validator::make($request->all(), [
            'complaint_form_no' => 'required|numeric',
            'programme_type_id' => 'required|numeric',
            'complaint_collection_date' => 'required',
            'complaint_collector' => 'required',
            'province_id' => 'required|numeric',
            'district_id' => 'required|numeric',
            'cwac_id' => 'required|numeric',
            'complaint_details' => 'required',
            'category_id' => 'required|numeric',
            'sub_category_id' => 'required|numeric'
        ]);
        try {
            $user_id = $this->user_id;
            $id = $request->input('complaint_id');
            $complaint_form_no = $request->input('complaint_form_no');
            $programme_type_id = $request->input('programme_type_id');
            $nongewel_programme_type_id = $request->input('nongewel_programme_type_id');
            $complaint_lodge_date = $request->input('complaint_lodge_date');
            $complaint_record_date = Carbon::now();
            $province_id = $request->input('province_id');
            $district_id = $request->input('district_id');
            $cwac_id = $request->input('cwac_id');
            $school_id = $request->input('school_id');
            $complainant_gender_id = $request->input('complainant_gender_id');
            $complainant_age = $request->input('complainant_age');
            $complaint_details = $request->input('complaint_details');
            $category_id = $request->input('category_id');
            $sub_category_id = $request->input('sub_category_id');
            $isOfflineSubmission = $request->input('isOfflineSubmission');

            $project_staff_involved = $request->input('project_staff_involved');
            $proj_staff_name = $request->input('proj_staff_name');
            $proj_staff_other_details = $request->input('proj_staff_other_details');

            $complainant_first_name = $request->input('complainant_first_name');
            $complainant_last_name = $request->input('complainant_last_name');
            $complainant_nrc = $request->input('complainant_nrc');
            $complainant_remarks = $request->input('complainant_remarks');
            $complainant_village = $request->input('complainant_village');
            $complainant_mobile = $request->input('complainant_mobile');
            $complainant_email = $request->input('complainant_email');
            $girl_id = $request->input('girl_id');
            $household_id = $request->input('household_id');

            $complaint_collection_date = $request->input('complaint_collection_date');
            $complaint_collector = $request->input('complaint_collector');

            if ($project_staff_involved == 2) {
                $proj_staff_name = '';
                $proj_staff_other_details = '';
            }

            $table_name = 'grm_complaint_details';
            $res = array();
            $operation = 'create';

            $validator->validate();

            $table_data = array(
                'complaint_form_no' => $complaint_form_no,
                'programme_type_id' => $programme_type_id,
                'nongewel_programme_type_id' => $nongewel_programme_type_id,
                'complaint_lodge_date' => $complaint_lodge_date,
                'province_id' => $province_id,
                'district_id' => $district_id,
                'cwac_id' => $cwac_id,
                'process_id' => 1,
                'school_id' => $school_id,
                'complainant_gender_id' => $complainant_gender_id,
                'complainant_age' => $complainant_age,
                'complaint_details' => $complaint_details,
                'category_id' => $category_id,
                'sub_category_id' => $sub_category_id,
                'project_staff_involved' => $project_staff_involved,
                'proj_staff_name' => $proj_staff_name,
                'proj_staff_other_details' => $proj_staff_other_details,
                'complainant_first_name' => $complainant_first_name,
                'complainant_last_name' => $complainant_last_name,
                'complainant_nrc' => $complainant_nrc,
                'complainant_remarks' => $complainant_remarks,
                'complainant_village' => $complainant_village,
                'complainant_mobile' => $complainant_mobile,
                'complainant_email' => $complainant_email,
                'girl_id' => $girl_id,
                'household_id' => $household_id,
                'complaint_collection_date' => $complaint_collection_date,
                'complaint_collector' => $complaint_collector
            );
            $where = array(
                'id' => $id
            );
            $workflow_stage_id = 2;
            $record_status_id = 1;
            $tab_index = 1;
            //category details
            $category_details = getTableData('grm_complaint_categories', array('id' => $category_id));
            $immediate_escalation = 0;
            if ($category_details) {
                $immediate_escalation = $category_details->immediate_escalation;
            }
            if (validateisNumeric($id)) {
                if (recordExists($table_name, $where)) {
                    $operation = 'update';
                    if ($isOfflineSubmission == 1) {
                        if ($programme_type_id == 3) {
                            $record_status_id = 3;
                            $tab_index = 3;
                            $table_data['processing_option_id'] = 4;
                        }
                        $table_data['workflow_stage_id'] = $workflow_stage_id;
                        $table_data['record_status_id'] = $record_status_id;
                        $table_data['tab_index'] = $tab_index;
                    }
                    $table_data['updated_at'] = Carbon::now();
                    $table_data['updated_by'] = $user_id;
                    $table_data['curr_from_userid'] = $user_id;
                    $table_data['curr_to_userid'] = $user_id;
                    $previous_data = getPreviousRecords($table_name, $where);
                    $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                    $res['record_id'] = $id;
                    if ($isOfflineSubmission == 1) {
                        //notifications
                        //1. Escalation
                        if ($immediate_escalation == 1 || $immediate_escalation === 1) {
                            $not_details = array(
                                'record_id' => $res['record_id'],
                                'notification_type_id' => 1,
                                'notification_date' => Carbon::now(),
                                'status' => 1
                            );
                            DB::table('grm_notifications')->insert($not_details);
                            $this->grievanceEscalationEmailNotification($programme_type_id, $province_id, $complaint_form_no);//HQ & Province
                        }
                        //2. Referrals
                        if ($programme_type_id == 3 || $programme_type_id === 3) {
                            //$this->grievanceReferralEmailNotification($request, $nongewel_programme_type_id);
                        }
                    }
                }
                //$folder_id = $previous_data[0]['folder_id'];
            } else {
                //check if form has been captured already
                $check_details = DB::table($table_name . ' as t1')
                    ->join('grm_workflow_stages as t2', 't1.workflow_stage_id', '=', 't2.id')
                    ->join('users as t3', 't1.complaint_recorded_by', '=', 't3.id')
                    ->select(DB::raw("t2.name as workflow_stage,CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as captured_by"))
                    ->where('complaint_form_no', $complaint_form_no)
                    ->first();
                if (!is_null($check_details)) {
                    $res = array(
                        'success' => false,
                        'message' => 'This complaint form number has been captured already (By ' . $check_details->captured_by . '), the grievance is currently in [' . $check_details->workflow_stage . '] stage'
                    );
                    return response()->json($res);
                }
                //todo DMS
                /*$parent_id = 258335;
                $folder_id = createDMSParentFolder($parent_id, '', $complaint_form_no, '', $this->dms_id);
                createDMSModuleFolders($folder_id, 31, $this->dms_id);
                $table_data['folder_id'] = $folder_id;*/
                //end DMS

                if ($programme_type_id == 3) {
                    $record_status_id = 3;
                    $tab_index = 3;
                    $table_data['processing_option_id'] = 4;
                }

                $table_data['workflow_stage_id'] = $workflow_stage_id;
                $table_data['record_status_id'] = $record_status_id;
                $table_data['tab_index'] = $tab_index;
                $table_data['view_id'] = generateRecordViewID();
                $table_data['created_at'] = Carbon::now();
                $table_data['created_by'] = $user_id;
                $table_data['complaint_recorded_by'] = $user_id;
                $table_data['complaint_record_date'] = $complaint_record_date;
                $res = insertRecord($table_name, $table_data, $user_id);
                $id = $res['record_id'];
                //notifications
                //1. Escalation
                if ($immediate_escalation == 1 || $immediate_escalation === 1) {
                    $not_details = array(
                        'record_id' => $res['record_id'],
                        'notification_type_id' => 1,
                        'notification_date' => Carbon::now(),
                        'status' => 1
                    );
                    DB::table('grm_notifications')->insert($not_details);
                    $this->grievanceEscalationEmailNotification($programme_type_id, $province_id, $complaint_form_no);//HQ & Province
                }
                //2. Referrals
                if ($programme_type_id == 3 || $programme_type_id === 3) {
                    //$this->grievanceReferralEmailNotification($request, $nongewel_programme_type_id);
                }
            }
            //handle file upload
            if ($res['success'] == true) {
                if ($request->hasFile('uploaded_files')) {
                    $totalFiles = count($_FILES['uploaded_files']['name']);
                    $destinationPath = public_path('storage/grm_grievanceforms_uploads/');
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
                                    'record_id' => $id,
                                    'initial_name' => $_FILES['uploaded_files']['name'][$i],
                                    'file_size' => $_FILES['uploaded_files']['size'][$i],
                                    'file_type' => $_FILES['uploaded_files']['type'][$i],
                                    'saved_name' => $newFileName,
                                    'server_filepath' => url('/') . '/backend/public/storage/grm_grievanceforms_uploads/',
                                    'server_filedirectory' => public_path('storage/grm_grievanceforms_uploads/')
                                );
                            }
                        }
                    }
                    DB::table('grm_grievance_formsuploads')
                        ->where('record_id', $id)
                        ->delete();
                    DB::table('grm_grievance_formsuploads')
                        ->insert($upload_details);
                }
                //check for need to notify district GRM focal point persons
                $this->grievanceLodgedNotificationToDistrict($request);
                //send referral emails here...to allow for attachments
                if ($operation == 'update' && $isOfflineSubmission != 1) {
                    //dont send email
                } else {
                    if ($programme_type_id == 3 || $programme_type_id === 3) {
                        $this->grievanceReferralEmailNotification($id, $request, $nongewel_programme_type_id);
                    }
                }
            }
            //end
            $res['form_no'] = $complaint_form_no;
            //$res['folder_id'] = $folder_id;
            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
            if ($exception instanceof ValidationException) {
                $errors = $validator->errors()->all();
                $error_count = count($errors);
                $error_msg = '';
                for ($i = 0; $i < $error_count; $i++) {
                    if ($i == $error_count - 1) {
                        $error_msg .= '[' . $errors[$i] . ']';
                    } else {
                        $error_msg .= '[' . $errors[$i] . '], ';
                    }
                }
                $res = array(
                    'success' => false,
                    'message' => $exception->getMessage() . '...' . $error_msg
                );
            }
        } catch (\Throwable $throwable) {
            DB::rollBack();
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return response()->json($res);
    }

    public function sendMissedEmails()
    {
        $qry = DB::table('grm_complaint_details as t1')
            ->where('t1.kip', 1);
        $results = $qry->get();
        $results = convertStdClassObjToArray($results);
        foreach ($results as $result) {
            $request = new Request(
                $result
            );
            $this->grievanceReferralEmailNotification($result->id, $request, $result->nongewel_programme_type_id);
        }
    }

    public function saveComplaintActionItem(Request $request)
    {
        $id = $request->input('id');
        $table_name = $request->input('table_name');
        $parent_folder_id = $request->input('parent_folder_id');
        $user_id = $this->user_id;
        $where = array(
            'id' => $id
        );
        try {
            $table_data = array(
                'complaint_id' => $request->input('complaint_id'),
                'workflow_stage_id' => $request->input('workflow_stage_id'),
                'action_item' => $request->input('action_item'),
                'action_level_id' => $request->input('action_level_id')
            );
            if (isset($id) && $id != "") {
                if (recordExists($table_name, $where)) {
                    $table_data['updated_at'] = Carbon::now();
                    $table_data['updated_by'] = $user_id;
                    $previous_data = getPreviousRecords($table_name, $where);
                    $folder_id = $previous_data[0]['folder_id'];
                    $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                    $res['folder_id'] = $folder_id;
                }
            } else {
                $action_no = time();
                $table_data['action_no'] = $action_no;
                $table_data['created_at'] = Carbon::now();
                $table_data['created_by'] = $user_id;
                $res = insertRecord($table_name, $table_data, $user_id);
                $parent_id = getSubModuleFolderID($parent_folder_id, 34);
                $folder_id = createDMSParentFolder($parent_id, '', $action_no, '', $this->dms_id);
                DB::table($table_name)->where('id', $res['record_id'])->update(array('folder_id' => $folder_id));
                $res['folder_id'] = $folder_id;
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

    public function getGrievancesQuery(Request $request)
    {
        $programme_type_id = $request->input('programme_type_id');
        $other_programme_type_id = $request->input('other_programme_type_id');
        $complaint_category_id = $request->input('complaint_category_id');
        $workflow_stage_id = $request->input('workflow_stage_id');
        $complaint_status_id = $request->input('complaint_status_id');
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $cwac_id = $request->input('cwac_id');
        $filter = $request->input('filter');
        $girl_id = $request->input('girl_id');
        $lodge_date_from = $request->input('lodge_date_from');
        $lodge_date_to = $request->input('lodge_date_to');
        $record_date_from = $request->input('record_date_from');
        $record_date_to = $request->input('record_date_to');
         //added 8/11/2022
         $access_point = $request->input('access_point');
        $qry = DB::table('grm_complaint_details as t1')
            ->join('districts as t2', 't1.district_id', '=', 't2.id')
            ->join('cwac as t3', 't1.cwac_id', '=', 't3.id')
            ->leftJoin('school_information as t4', 't1.school_id', '=', 't4.id')
            ->leftJoin('wf_kgsprocesses as t5', 't1.process_id', '=', 't5.id')
            ->join('grm_workflow_stages as t6', 't1.workflow_stage_id', '=', 't6.id')
            ->leftJoin('grm_grievance_statuses as t7', 't1.record_status_id', '=', 't7.id')
            ->leftJoin('beneficiary_information as t8', 't1.girl_id', '=', 't8.id')
            ->join('grm_complaint_categories as t12', 't1.category_id', '=', 't12.id')
            ->leftJoin('households as t13', 't1.household_id', '=', 't13.id')
            ->join('grm_gewel_programmes as t14', 't1.programme_type_id', '=', 't14.id')
            ->leftJoin('grm_complaint_subcategories as t15', 't1.sub_category_id', '=', 't15.id')
            ->join('provinces as t16', 't2.province_id', '=', 't16.id')
            ->leftJoin('grm_nongewel_programmes as t17', 't1.nongewel_programme_type_id', '=', 't17.id')
            ->leftJoin('users as t18', 't1.complaint_recorded_by', '=', 't18.id')
            ->select(DB::raw("t1.id as record_id,t1.id as complaint_id,t1.*,t2.name as district_name,t3.name as cwac_name,t4.name as school_name,t5.name as process_name,t6.name as workflow_stage,
                                         t7.name as record_status,t8.beneficiary_id,decrypt(t8.first_name) as first_name,decrypt(t8.last_name) as last_name,t8.dob,t15.name as category_subcategory,
                                         TOTAL_WEEKDAYS(t1.complaint_record_date,now()) as recorded_span,t16.name as province_name,t17.code as non_gewel_code,
                                         t12.name as category_name,t13.id as household_id,t13.hhh_nrc_number,t13.hhh_fname,t13.hhh_lname,t13.number_in_cwac,t14.code as programme_code,t6.interface_xtype,
                                         CONCAT_WS(' ',decrypt(t18.first_name),decrypt(t18.last_name)) as recorded_by"));
        if (validateisNumeric($programme_type_id)) {
            $qry->where('t1.programme_type_id', $programme_type_id);
        }
        if (validateisNumeric($other_programme_type_id)) {
            $qry->where('t1.nongewel_programme_type_id', $other_programme_type_id);
        }
        if (validateisNumeric($complaint_category_id)) {
            $qry->where('t1.category_id', $complaint_category_id);
        }
        if (validateisNumeric($workflow_stage_id)) {
            $qry->where('t1.workflow_stage_id', $workflow_stage_id);
        }
        if (validateisNumeric($complaint_status_id)) {
            $qry->where('t1.record_status_id', $complaint_status_id);
        }
        if (validateisNumeric($province_id)) {
            $qry->where('t1.province_id', $province_id);
        }
        if (validateisNumeric($district_id)) {
            $qry->where('t1.district_id', $district_id);
        }
        if (validateisNumeric($cwac_id)) {
            $qry->where('t1.cwac_id', $cwac_id);
        }
        if (validateisNumeric($girl_id)) {
            $qry->where('t1.girl_id', $girl_id);
        }
        if (isset($lodge_date_from) && isset($lodge_date_to)) {
            $qry->whereBetween('t1.complaint_lodge_date', [$lodge_date_from, $lodge_date_to]);
        }
        if (isset($record_date_from) && isset($record_date_to)) {
            $qry->whereBetween('t1.complaint_record_date', [$record_date_from, $record_date_to]);
        }
        if (isset($filter)) {
            $filters = json_decode($filter);
            $filter_string = $this->buildGrievanceSearchQuery($filters);
            if ($filter_string != '') {
                $qry->whereRAW($filter_string);
            }
        }
       //8/11/2022
        if (validateisNumeric($access_point)) {
                $qry->where('t18.access_point_id', $access_point);
        }
        return $qry;
    }

    public function getOfflineSubmittedGrievances(Request $request)
    {
        try {
            $user_id = $this->user_id;
            $groups = getUserGroups($user_id);
            $superUserID = getSuperUserGroupId();

            $qry = $this->getGrievancesQuery($request)
                ->where('is_mobile', 1);
            $qry = in_array($superUserID, $groups) == 1 ? $qry->whereRaw("1=1") : $qry->where('t1.mobile_submission_by', $user_id);

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

    public function getGrievances(Request $request)
    {
        try {
            $logged_in_user = $this->user_id;
            $user_access_point = Auth::user()->access_point_id;
            $viewAll = $request->input('viewAll');
            $start = $request->input('start');
            $limit = $request->input('limit');

            $qry = $this->getGrievancesQuery($request);
            if ($viewAll === true || $viewAll === 'true' || $viewAll == 1) {
                //do nothing
            } else {
                if ($user_access_point == 3 || $user_access_point == 4) {//assigned districts
                    $qry->whereIn('t1.district_id', function ($query) use ($logged_in_user) {
                        $query->select(DB::raw('user_district.district_id'))
                            ->from('user_district')
                            ->where('user_district.user_id', $logged_in_user);
                    });
                }
            }
            $total = $qry->count();
            if (validateisNumeric($limit)) {
                $qry->offset($start)
                    ->limit($limit);
            }
            $results = $qry->get();
            $res = array(
                'success' => true,
                'message' => returnMessage($results),
                'total' => $total,
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

    public function buildGrievanceSearchQuery($filters)
    {
        $return_string = '';
        $whereClauses = array();
        if ($filters != NULL) {
            foreach ($filters as $filter) {
                switch ($filter->property) {
                    case 'complaint_form_no' :
                        $whereClauses[] = "t1.complaint_form_no like '%" . ($filter->value) . "%'";
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

    public function getGrievancesForMonitoring(Request $request)
    {
        $monitoring_id = $request->input('monitoring_id');
        $district_id = $request->input('district_id');
        $cwac_id = $request->input('cwac_id');
        try {
            $qry = DB::table('grm_complaint_details as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->join('cwac as t3', 't1.cwac_id', '=', 't3.id')
                ->leftJoin('school_information as t4', 't1.school_id', '=', 't4.id')
                ->join('grm_workflow_stages as t6', 't1.workflow_stage_id', '=', 't6.id')
                ->join('grm_grievance_statuses as t7', 't1.record_status_id', '=', 't7.id')
                ->leftJoin('users as t10', 't1.curr_from_userid', '=', 't10.id')
                ->leftJoin('users as t11', 't1.curr_to_userid', '=', 't11.id')
                ->whereIn('t1.cwac_id', function ($query) use ($monitoring_id) {
                    $query->select(DB::raw('cwac_id'))
                        ->from('grm_monitoring_location')
                        ->where('monitoring_id', $monitoring_id);
                })
                ->select(DB::raw("t1.id as record_id,t1.id as complaint_id,t1.*,t2.name as district_name,t3.name as cwac_name,t4.name as school_name,t6.name as workflow_stage,
                                         t7.name as record_status,TOTAL_WEEKDAYS(t1.complaint_record_date,now()) as recorded_span,
                                         CONCAT_WS(' ',decrypt(t10.first_name),decrypt(t10.last_name)) as from_user,CONCAT_WS(' ',decrypt(t11.first_name),decrypt(t11.last_name)) as to_user
                                         "));
            if (validateisNumeric($district_id)) {
                $qry->where('t1.district_id', $district_id);
            }
            if (validateisNumeric($cwac_id)) {
                $qry->where('t1.cwac_id', $cwac_id);
            }
            $qry->orderBy('complaint_record_date', 'DESC');
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

    public function getComplaintSubCategories(Request $request)
    {
        $category_id = $request->input('category_id');
        try {
            $qry = DB::table('grm_complaint_subcategories as t1')
                ->join('grm_complaint_categories as t2', 't1.category_id', '=', 't2.id')
                ->select('t1.*', 't2.name as category_name', 't2.description as category_description');
            if (validateisNumeric($category_id)) {
                $qry->where('t1.category_id', $category_id);
            }
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

    public function getComplaintCategorizationDetails(Request $request)
    {
        $complaint_id = $request->input('complaint_id');
        try {
            $qry = DB::table('grm_complaint_categorization as t1')
                ->join('grm_complaint_subcategories as t2', 't1.sub_category_id', '=', 't2.id')
                ->join('grm_complaint_categories as t3', 't2.category_id', '=', 't3.id')
                ->select('t1.*', 't2.category_id', 't2.name', 't2.description', 't3.name as category_name')
                ->where('t1.complaint_id', $complaint_id);
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

    public function saveComplaintCategorizationDetails(Request $request)
    {
        $item_ids = $request->input();
        $complaint_id = $request->input('complaint_id');
        $category_id = $request->input('category_id');
        $user_id = $this->user_id;
        unset($item_ids['complaint_id']);
        unset($item_ids['category_id']);
        $params = array();
        $table_name = 'grm_complaint_categorization';
        try {
            foreach ($item_ids as $item_id) {
                if (recordExists($table_name, array('complaint_id' => $complaint_id, 'sub_category_id' => $item_id))) {
                    //do nothing
                } else {
                    $params[] = array(
                        'complaint_id' => $complaint_id,
                        'sub_category_id' => $item_id,
                        'created_by' => $user_id,
                        'created_at' => Carbon::now()
                    );
                }
            }
            DB::table($table_name . ' as t1')
                ->join('grm_complaint_subcategories as t2', 't1.sub_category_id', '=', 't2.id')
                ->where('complaint_id', $complaint_id)
                ->where('t2.category_id', '<>', $category_id)
                ->delete();
            DB::table($table_name)->insert($params);
            DB::table('grm_complaint_details')
                ->where('id', $complaint_id)
                ->update(array('category_id' => $category_id));
            $res = array(
                'success' => true,
                'message' => 'Details updated successfully!!'
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

    public function getComplaintNoteDetails(Request $request)
    {
        try {
            $complaint_id = $request->input('complaint_id');
            $table_name = $request->input('table_name');
            $qry = DB::table($table_name . ' as t1')
                ->leftJoin('wf_workflow_stages as t2', 't1.workflow_stage_id', '=', 't2.id')
                ->join('users as t3', 't1.created_by', '=', 't3.id')
                ->select(DB::raw("t1.*,t1.created_at as note_date,t2.name as workflow_stage,CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as note_by"))
                ->where('t1.complaint_id', $complaint_id);

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

    public function getComplaintActionItems(Request $request)
    {
        $complaint_id = $request->input('complaint_id');
        $workflow_stage_id = $request->input('workflow_stage_id');
        $table_name = $request->input('table_name');
        try {
            $qry = DB::table($table_name . ' as t1')
                ->leftJoin('wf_workflow_stages as t2', 't1.workflow_stage_id', '=', 't2.id')
                ->join('users as t3', 't1.created_by', '=', 't3.id')
                ->leftJoin('authority_levels as t4', 't1.action_level_id', '=', 't4.id')
                ->select(DB::raw("t1.*,t2.name as workflow_stage,CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as captured_by,
                                        t4.name as authority_level"))
                ->where('t1.complaint_id', $complaint_id);

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

    public function getGrmSubmissionDetails(Request $request)
    {
        $record_id = $request->input('record_id');
        $table_name = $request->input('table_name');
        try {
            $qry = DB::table($table_name . ' as t1')
                ->join('wf_kgsprocesses as t2', 't1.process_id', '=', 't2.id')
                ->join('wf_workflow_stages as t3', 't1.workflow_stage_id', 't3.id')
                ->leftJoin('system_statuses as t4', 't1.record_status_id', 't4.id')
                ->select('t1.id', 't1.reference_no', 't1.process_id as processId', 't1.workflow_stage_id as currentStageId', 't2.name as processName', 't3.name as currentStageName',
                    't4.name as recordStatus', 't4.id as recordStatusId')
                ->where('t1.id', $record_id);
            $results = $qry->first();

            $res = array(
                'success' => true,
                'results' => $results,
                'message' => 'Details fetched successfully!!'
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

    public function processGRMRecordSubmission(Request $request)
    {
        $record_id = $request->input('record_id');
        $process_id = $request->input('process_id');
        $table_name = $request->input('table_name');
        $prev_stage = $request->input('curr_stage_id');
        $action = $request->input('action');
        $to_stage = $request->input('next_stage');
        $remarks = $request->input('remarks');
        $responsible_user = $request->input('responsible_user');
        $user_id = $this->user_id;
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

            $action_details = getTableData('wf_workflow_actions', array('id' => $action));
            if (is_null($action_details)) {
                $res = array(
                    'success' => false,
                    'message' => 'Problem encountered while fetching action details!!'
                );
                return \response()->json($res);
            }

            $is_escalation = $action_details->is_escalation;
            $keep_prev_status = $action_details->keep_prev_status;
            $email_notification = $action_details->email_notification;
            //$email_notification = getSingleRecordColValue('wf_workflow_transitions', array('stage_id' => $prev_stage, 'action_id' => $action, 'nextstage_id' => $to_stage), 'email_notification');
            if ($keep_prev_status == 1) {
                $record_status_id = $record_details->record_status_id;
            } else {
                $record_status_id = getRecordTransitionStatus($prev_stage, $action, $to_stage);
            }
            if ($is_escalation == 1 || $is_escalation === 1) {
                $email_notification = 1;
                $app_update['escalated'] = $is_escalation;
            }

            if (!validateisNumeric($record_status_id)) {
                $record_status_id = 1;
            }

            $where = array(
                'id' => $record_id
            );
            $app_update = array(
                'workflow_stage_id' => $to_stage,
                'record_status_id' => $record_status_id,
                'isRead' => 0,
                'curr_from_userid' => $user_id,
                'curr_to_userid' => $responsible_user,
                'current_stage_entry_date' => Carbon::now(),
                'lag_email_sent' => 0
            );

            if ($email_notification == 1 || $email_notification === 1) {
                $this->complaintSubmissionEmailNotification($record_details, $responsible_user, $prev_stage, $action, $to_stage);
            }
            $prev_data = getPreviousRecords($table_name, $where);
            $update_res = updateRecord($table_name, $prev_data, $where, $app_update, $user_id);
            if ($update_res['success'] == false) {
                return \response()->json($update_res);
            }
            $transition_params = array(
                'record_id' => $record_id,
                'record_status_id' => $record_status_id,
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

    public function getComplaintRecommendationDetails(Request $request)
    {
        $complaint_id = $request->input('complaint_id');
        $table_name = $request->input('table_name');
        try {
            $qry = DB::table($table_name . ' as t1')
                ->leftJoin('modules as t2', 't1.notfy_department_id', '=', 't2.id')
                ->leftJoin('users as t3', 't1.feedback_by', '=', 't3.id')
                ->select(DB::raw("t1.*, t2.name as department,decrypt(t3.first_name) as feedback_author"));
            if (validateisNumeric($complaint_id)) {
                $qry->where('t1.complaint_id', $complaint_id);
            }
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
        return \response()->json($res);
    }

    public function saveComplaintResolutionDetails(Request $request)
    {
        try {
            $res = array();
            DB::transaction(function () use ($request, &$res) {
                $id = $request->input('id');
                $complaint_id = $request->input('complaint_id');
                $complaint_status_id = $request->input('complaint_status_id');
                $resolution_option_id = $request->input('resolution_option_id');
                $program_referred = $request->input('program_referred_id');
                $user_id = $this->user_id;
                $table_name = 'grm_complaint_resolutions';
                $params = array(
                    'complaint_id' => $complaint_id,
                    'resolution_option_id' => $resolution_option_id,
                    'program_referred_id' => $program_referred,
                    'remarks' => $request->input('remarks')
                );
                $where = array(
                    'id' => $id
                );
                DB::table('grm_complaint_details')
                    ->where('id', $complaint_id)
                    ->update(array('record_status_id' => $complaint_status_id));
                if (validateisNumeric($id)) {
                    $params['updated_by'] = $this->user_id;
                    $prev_data = getPreviousRecords($table_name, $where);
                    $res = updateRecord($table_name, $prev_data, $where, $params, $user_id);
                } else {
                    $params['created_by'] = $this->user_id;
                    $res = insertRecord($table_name, $params, $user_id);
                }
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

    public function getComplaintResolutionDetails(Request $request)
    {
        $complaint_id = $request->input('record_id');
        $table_name = 'grm_complaint_resolutions';
        try {
            $results = getTableData($table_name, array('complaint_id' => $complaint_id));
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => 'Successful!!'
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

    public function getGRMDashboardData(Request $request)
    {
        try {
            $lodge_date_from = $request->input('lodge_date_from');
            $lodge_date_to = $request->input('lodge_date_to');
            $record_date_from = $request->input('record_date_from');
            $record_date_to = $request->input('record_date_to');
            $logged_in_user = $this->user_id;
            $user_access_point = Auth::user()->access_point_id;

            $reported_complaints = 0;
            $ongoing_complaints = 0;
            $appealed_complaints = 0;
            $resolved_complaints = 0;
            $deferred_complaints = 0;
            $pending_complaints = 0;
            $qry = DB::table('grm_complaint_details as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->join('cwac as t3', 't1.cwac_id', '=', 't3.id')
                ->join('grm_gewel_programmes as t4', 't1.programme_type_id', '=', 't4.id')
                ->join('grm_workflow_stages as t5', 't1.workflow_stage_id', '=', 't5.id')
                ->join('grm_complaint_categories as t6', 't1.category_id', '=', 't6.id')
                ->select(DB::raw("COUNT(DISTINCT t1.id) as reported_complaints,
                                    SUM(IF(t1.record_status_id=1,1,0)) as ongoing_complaints,
                                    SUM(IF(t1.record_status_id=2,1,0)) as resolved_complaints,
                                    SUM(IF(t1.record_status_id=3,1,0)) as referred_complaints,
                                    SUM(IF(t1.record_status_id=4,1,0)) as appealed_complaints,
                                    SUM(IF(t1.record_status_id=5,1,0)) as pending_complaints"));

            if ($user_access_point == 3 || $user_access_point == 4) {//assigned districts
                $qry->whereIn('t1.district_id', function ($query) use ($logged_in_user) {
                    $query->select(DB::raw('user_district.district_id'))
                        ->from('user_district')
                        ->where('user_district.user_id', $logged_in_user);
                });
            }
            if (isset($lodge_date_from) && isset($lodge_date_to)) {
                $qry->whereBetween('t1.complaint_lodge_date', [$lodge_date_from, $lodge_date_to]);
            }
            if (isset($record_date_from) && isset($record_date_to)) {
                $qry->whereBetween('t1.complaint_record_date', [$record_date_from, $record_date_to]);
            }
            $results = $qry->first();

            if ($results) {
                $reported_complaints = $results->reported_complaints;
                $ongoing_complaints = $results->ongoing_complaints;
                $appealed_complaints = $results->appealed_complaints;
                $deferred_complaints = $results->referred_complaints;
                $resolved_complaints = $results->resolved_complaints;
                $pending_complaints = $results->pending_complaints;
            }
            $res = array(
                'reported_complaints' => ($reported_complaints) ? number_format($reported_complaints) : 0,
                'ongoing_complaints' => ($ongoing_complaints) ? number_format($ongoing_complaints) : 0,
                'appealed_complaints' => ($appealed_complaints) ? number_format($appealed_complaints) : 0,
                'resolved_complaints' => ($resolved_complaints) ? number_format($resolved_complaints) : 0,
                'referred_complaints' => ($deferred_complaints) ? number_format($deferred_complaints) : 0,
                'pending_complaints' => ($pending_complaints) ? number_format($pending_complaints) : 0
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

    public function getDashboardChartDetailsGrpOne(Request $request)
    {
        try {
            $group_by_field = $request->input('group_by_field');
            $group_by_table = $request->input('group_by_table');
            $lodge_date_from = $request->input('lodge_date_from');
            $lodge_date_to = $request->input('lodge_date_to');
            $record_date_from = $request->input('record_date_from');
            $record_date_to = $request->input('record_date_to');
            $logged_in_user = $this->user_id;
            $user_access_point = Auth::user()->access_point_id;
            $qry = DB::table('grm_complaint_details as t1')
                ->join($group_by_table . ' as t2', 't1.' . $group_by_field, '=', 't2.id')
                ->join('districts as t3', 't1.district_id', '=', 't3.id')
                ->join('cwac as t4', 't1.cwac_id', '=', 't4.id')
                ->join('grm_workflow_stages as t5', 't1.workflow_stage_id', '=', 't5.id')
                ->select(DB::raw("t1.id,t2.id as group_byid,t2.name as group_byname,t2.code as group_bycode,count(DISTINCT t1.id) as no_of_complaints"));
            if ($user_access_point == 3 || $user_access_point == 4) {//assigned districts
                $qry->whereIn('t1.district_id', function ($query) use ($logged_in_user) {
                    $query->select(DB::raw('user_district.district_id'))
                        ->from('user_district')
                        ->where('user_district.user_id', $logged_in_user);
                });
            }
            if (isset($lodge_date_from) && isset($lodge_date_to)) {
                $qry->whereBetween('t1.complaint_lodge_date', [$lodge_date_from, $lodge_date_to]);
            }
            if (isset($record_date_from) && isset($record_date_to)) {
                $qry->whereBetween('t1.complaint_record_date', [$record_date_from, $record_date_to]);
            }
            $qry->groupBy('t1.' . $group_by_field);
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

    public function getComplaintStatusesChartDetails2(Request $request)
    {
        try {
            $logged_in_user = $this->user_id;
            $user_access_point = Auth::user()->access_point_id;
            $group_by_table = $request->input('group_by_table');
            $group_by_field = $request->input('group_by_field');
            $lodge_date_from = $request->input('lodge_date_from');
            $lodge_date_to = $request->input('lodge_date_to');
            $record_date_from = $request->input('record_date_from');
            $record_date_to = $request->input('record_date_to');
            $qry = DB::table('grm_complaint_details as t1')
                ->join('grm_grievance_statuses as t2', 't1.record_status_id', '=', 't2.id')
                ->join($group_by_table . ' as t3', 't1.' . $group_by_field, '=', 't3.id')
                ->join('cwac as t33', 't1.cwac_id', '=', 't33.id')
                ->join('grm_gewel_programmes as t4', 't1.programme_type_id', '=', 't4.id')
                ->join('grm_workflow_stages as t5', 't1.workflow_stage_id', '=', 't5.id')
                ->join('grm_complaint_categories as t6', 't1.category_id', '=', 't6.id')
                ->select(DB::raw("t1.record_status_id,t1.province_id,t1.district_id,t2.name as complaint_status,count(DISTINCT t1.id) as no_of_complaints,t3.name as group_name,
                                        SUM(IF(t1.record_status_id = 1, 1,0)) as ongoing,
                                        SUM(IF(t1.record_status_id = 2, 1,0)) as resolved,
                                        SUM(IF(t1.record_status_id = 3, 1,0)) as referred,
                                        SUM(IF(t1.record_status_id = 4, 1,0)) as appealed"));
            if ($user_access_point == 3 || $user_access_point == 4) {//assigned districts
                $qry->whereIn('t1.district_id', function ($query) use ($logged_in_user) {
                    $query->select(DB::raw('user_district.district_id'))
                        ->from('user_district')
                        ->where('user_district.user_id', $logged_in_user);
                });
            }
            if (isset($lodge_date_from) && isset($lodge_date_to)) {
                $qry->whereBetween('t1.complaint_lodge_date', [$lodge_date_from, $lodge_date_to]);
            }
            if (isset($record_date_from) && isset($record_date_to)) {
                $qry->whereBetween('t1.complaint_record_date', [$record_date_from, $record_date_to]);
            }
            $qry->groupBy('t1.' . $group_by_field);
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


    public function getResolvedComplaintsChartDetails(Request $request)
    {
        $per_district = $request->input('per_district');
        try {
            if (validateisNumeric($per_district) && $per_district == 1) {
                $results = $this->getResolvedComplaintsChartDetails2();
            } else {
                $results = $this->getResolvedComplaintsChartDetails1();
            }
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

    public function getResolvedComplaintsChartDetails1()
    {
        $qry = DB::table('grm_complaint_details as t1')
            ->join('grm_complaint_resolutions as t2', 't1.id', '=', 't2.complaint_id')
            ->join('grm_complaint_resolutionoptions as t3', 't2.resolution_option_id', '=', 't3.id')
            ->select(DB::raw("t3.name as complaint_status,count(DISTINCT t1.id) as no_of_complaints"))
            ->groupBy('t2.resolution_option_id')
            ->where('t1.record_status_id', 2);
        $results = $qry->get();
        return $results;
    }

    public function getResolvedComplaintsChartDetails2()
    {
        $qry = DB::table('grm_complaint_details as t1')
            ->join('grm_complaint_resolutions as t2', 't1.id', '=', 't2.complaint_id')
            ->join('grm_complaint_resolutionoptions as t3', 't2.resolution_option_id', '=', 't3.id')
            ->join('districts as t4', 't1.district_id', '=', 't4.id')
            ->select(DB::raw("t3.name as resolution_option,t4.name as district_name,
                                        SUM(IF(t2.resolution_option_id = 1, 1,0)) as resolved1,
                                        SUM(IF(t2.resolution_option_id = 2, 1,0)) as resolved2,
                                        SUM(IF(t2.resolution_option_id = 3, 1,0)) as resolved3"))
            ->groupBy('t1.district_id');
        $results = $qry->get();
        return $results;
    }

    public function getComplaintResponseLettersConfig(Request $request)
    {
        try {
            $program_type_id = $request->input('program_type_id');
            $complaint_category_id = $request->input('complaint_category_id');
            $qry = DB::table('grm_lettertypes as t1')
                ->leftJoin('grm_gewel_programmes as t2', 't1.program_type_id', '=', 't2.id')
                ->leftJoin('grm_complaint_categories as t3', 't1.complaint_category_id', '=', 't3.id')
                ->select('t1.*', 't2.name as program_name', 't3.name as category_name');
            if (validateisNumeric($program_type_id)) {
                $qry->where('t1.program_type_id', $program_type_id);
            }
            if (validateisNumeric($complaint_category_id)) {
                $qry->where('t1.complaint_category_id', $complaint_category_id);
            }
            $qry->orderBy('t1.program_type_id');
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results
                //'message' => returnMessage($results)
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

    public function saveGrmMonitoringPlanDetails(Request $request)
    {
        try {
            $user_id = $this->user_id;
            $id = $request->input('monitoring_id');
            $process_id = $request->input('process_id');
            $workflow_stage_id = $request->input('workflow_stage_id');
            $start_date = $request->input('start_date');
            $end_date = $request->input('end_date');
            $monitoring_type_id = $request->input('monitoring_type_id');
            $description = $request->input('description');

            $table_name = 'grm_monitoring_plan_details';
            $res = array();
            $table_data = array(
                'process_id' => $process_id,
                'workflow_stage_id' => $workflow_stage_id,
                'monitoring_type_id' => $monitoring_type_id,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'description' => $description
            );
            $where = array(
                'id' => $id
            );
            if (isset($id) && $id != "") {
                $previous_data = array();
                $complaint_id = $id;
                if (recordExists($table_name, $where)) {
                    $table_data['updated_at'] = Carbon::now();
                    $table_data['updated_by'] = $user_id;
                    $previous_data = getPreviousRecords($table_name, $where);
                    $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                }
                $ref_no = $previous_data[0]['reference_no'];
                $folder_id = $previous_data[0]['folder_id'];
            } else {
                $codes_array = array(
                    'program_code' => 'KGS'
                );
                $ref_details = generateRecordRefNumber(2, $process_id, '', $codes_array, $table_name, $user_id);
                if ($ref_details['success'] == false) {
                    return \response()->json($ref_details);
                }
                $ref_no = $ref_details['ref_no'];
                //todo DMS
                $parent_id = 223506;//258336;
                $folder_id = createDMSParentFolder($parent_id, '', $ref_no, '', $this->dms_id);
                //end DMS
                $table_data['folder_id'] = $folder_id;
                $table_data['view_id'] = generateRecordViewID();
                $table_data['reference_no'] = $ref_no;
                //$table_data['record_status_id'] = getRecordInitialStatus($process_id)->status_id;
                $table_data['created_at'] = Carbon::now();
                $table_data['created_by'] = $user_id;
                $res = insertRecord($table_name, $table_data, $user_id);
                if ($res['success'] == false) {
                    return \response()->json($res);
                }
                $complaint_id = $res['record_id'];
            }
            $res['record_id'] = $complaint_id;
            $res['ref_no'] = $ref_no;
            $res['folder_id'] = $folder_id;
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

    public function getGrmMonitoringPlanDetails(Request $request)
    {
        $workflow_stage_id = $request->input('workflow_stage_id');
        try {
            $qry = DB::table('grm_monitoring_plan_details as t1')
                ->join('grm_monitoring_types as t2', 't1.monitoring_type_id', '=', 't2.id')
                ->join('wf_kgsprocesses as t3', 't1.process_id', '=', 't3.id')
                ->join('grm_monitoring_stages as t4', 't1.workflow_stage_id', '=', 't4.id')
                ->select(DB::raw("t1.*,t1.id as record_id,t1.id as monitoring_id,t1.*,t3.name as process_name,t4.name as workflow_stage,t4.interface_xtype"));
            if (validateisNumeric($workflow_stage_id)) {
                $qry->where('t1.workflow_stage_id', $workflow_stage_id);
            }
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

    public function getGrmMonitoringStaff(Request $request)
    {
        $monitoring_id = $request->input('record_id');
        try {
            $qry = DB::table('grm_monitoring_staff')
                ->where('monitoring_id', $monitoring_id);
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

    public function getGrmMonitoringLocationDetails(Request $request)
    {
        $monitoring_id = $request->input('record_id');
        $district_id = $request->input('district_id');
        $districts_filter = $request->input('districts_filter');
        try {
            $qry = DB::table('grm_monitoring_location as t1')
                ->join('cwac as t2', 't1.cwac_id', '=', 't2.id')
                ->join('districts as t3', 't2.district_id', '=', 't3.id')
                ->select('t1.*', 't2.name as cwac', 't3.name as district', 't2.district_id')
                ->where('monitoring_id', $monitoring_id);
            if (validateisNumeric($district_id)) {
                $qry->where('t2.district_id', $district_id);
            }
            if (validateisNumeric($districts_filter) && $districts_filter == 1) {
                $qry->groupBy('t2.district_id');
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

    public function saveGrmMonitoringLocationDetails(Request $request)
    {
        $item_ids = $request->input();
        $monitoring_id = $request->input('monitoring_id');
        $user_id = $this->user_id;
        unset($item_ids['monitoring_id']);
        $params = array();
        $table_name = 'grm_monitoring_location';
        try {
            foreach ($item_ids as $item_id) {
                if (recordExists($table_name, array('monitoring_id' => $monitoring_id, 'cwac_id' => $item_id))) {
                    //do nothing
                } else {
                    $params[] = array(
                        'monitoring_id' => $monitoring_id,
                        'cwac_id' => $item_id,
                        'created_by' => $user_id,
                        'created_at' => Carbon::now()
                    );
                }
            }
            DB::table($table_name)->insert($params);
            $res = array(
                'success' => true,
                'message' => 'Details updated successfully!!'
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

    public function saveMonitoringComplaints(Request $request)
    {
        $item_ids = $request->input();
        $monitoring_id = $request->input('monitoring_id');
        $user_id = $this->user_id;
        unset($item_ids['monitoring_id']);
        $params = array();
        $table_name = 'grm_monitoring_complaints';
        try {
            foreach ($item_ids as $item_id) {
                $params[] = array(
                    'monitoring_id' => $monitoring_id,
                    'complaint_id' => $item_id,
                    'created_by' => $user_id,
                    'created_at' => Carbon::now()
                );
            }
            DB::table($table_name)
                ->where('monitoring_id', $monitoring_id)
                ->delete();
            DB::table($table_name)->insert($params);
            $res = array(
                'success' => true,
                'message' => 'Details updated successfully!!'
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

    public function getMonitoringComplaints(Request $request)
    {
        $monitoring_id = $request->input('record_id');
        try {
            $qry = DB::table('grm_monitoring_complaints as t1')
                ->join('grm_complaint_details as t2', 't1.complaint_id', '=', 't2.id')
                ->join('districts as t3', 't2.district_id', '=', 't3.id')
                ->join('cwac as t4', 't2.cwac_id', '=', 't4.id')
                ->leftJoin('school_information as t5', 't2.school_id', '=', 't5.id')
                ->join('grm_workflow_stages as t6', 't2.workflow_stage_id', '=', 't6.id')
                ->join('grm_grievance_statuses as t7', 't2.record_status_id', '=', 't7.id')
                ->select(DB::raw("t2.*,t1.*,t3.name as district_name,t4.name as cwac_name,t5.name as school_name,t6.name as workflow_stage,
                                         t7.name as record_status,t7.name as complaint_status,TOTAL_WEEKDAYS(t2.complaint_record_date,now()) as recorded_span"))
                ->where('t1.monitoring_id', $monitoring_id)
                ->orderBy('t2.complaint_record_date', 'DESC');
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

    public function saveMonitoringComplaintsDataEntry()
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
        $table_name = 'grm_monitoring_complaints';
        try {
            foreach ($data as $key => $value) {
                $table_data = array(
                    'is_complaint_resolved' => $value['is_complaint_resolved'],
                    'is_complainant_satisfied' => $value['is_complainant_satisfied'],
                    'reasons' => $value['reasons'],
                    'additional_feedback' => $value['additional_feedback']
                );
                //where data
                $where_data = array(
                    'id' => $value['id']
                );
                DB::table($table_name)
                    ->where($where_data)
                    ->update($table_data);
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

    public function uploadGrmResponseDocument(Request $req)
    {
        try {
            $user_id = \Auth::user()->id;
            $post_data = $req->all();
            $table_name = $post_data['table_name'];
            $id = $post_data['id'];
            $res = array();
            //unset unnecessary values
            unset($post_data['_token']);
            unset($post_data['table_name']);
            unset($post_data['model']);
            unset($post_data['id']);
            unset($post_data['template_url']);
            $table_data = $post_data;

            //add extra params
            $table_data['created_at'] = Carbon::now();
            $table_data['created_by'] = $user_id;
            //handle file upload
            if ($req->hasFile('template_url')) {
                $file = $req->file('template_url');
                $destinationPath = public_path('storage/Template');
                $initialTemplateName = $file->getClientOriginalName();
                $templateExtension = $file->getClientOriginalExtension();
                $templateSavedName = Str::random(8) . '.' . $templateExtension;
                $file->move($destinationPath, $templateSavedName);
                $table_data['template_initial_name'] = $initialTemplateName;
                $table_data['template_saved_name'] = $templateSavedName;
            }
            //end
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

    public function getComplaintLetterResponses(Request $request)
    {
        $template_id = $request->input('template_id');
        $complaint_id = $request->input('complaint_id');
        try {
            $qry = DB::table('grm_responseletter_applicablesections as t1')
                ->join('grm_responseletter_sections as t2', 't1.section_id', '=', 't2.id')
                ->leftJoin('grm_complaintletter_responses as t3', function ($join) use ($complaint_id) {
                    $join->on('t1.id', '=', 't3.applicable_section_id')
                        ->where('t3.complaint_id', $complaint_id);
                })
                ->select(DB::raw("t1.*,CONCAT(t2.name,'(',COALESCE(t1.description,''),')') as section,t3.response"))
                ->where('t1.template_id', $template_id);
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

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncComplaintLetterResponses(Request $request)
    {
        $complaint_id = $request->input('complaint_id');
        $postdata = file_get_contents("php://input");
        $data = json_decode($postdata);
        // the post data is array thus handle as array data
        if (is_array($data)) {
            $data = json_decode($postdata, true);
        } else {
            $data = array();
            $data[] = json_decode($postdata, true);
        }
        $table_name = 'grm_complaintletter_responses';
        try {
            foreach ($data as $key => $value) {
                $table_data = array(
                    'applicable_section_id' => $value['id'],
                    'complaint_id' => $complaint_id,
                    'response' => $value['response']
                );
                //where data
                $where_data = array(
                    'applicable_section_id' => $value['id'],
                    'complaint_id' => $complaint_id
                );
                if (recordExists($table_name, $where_data)) {
                    $table_data['updated_by'] = $this->user_id;
                    $table_data['updated_at'] = Carbon::now();
                    DB::table($table_name)
                        ->where($where_data)
                        ->update($table_data);
                } else {
                    $table_data['created_by'] = $this->user_id;
                    $table_data['created_at'] = Carbon::now();
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

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLetterTemplateApplicableSections(Request $request)
    {
        $template_id = $request->input('template_id');
        try {
            $qry = DB::table('grm_responseletter_applicablesections as t1')
                ->join('grm_responseletter_sections as t2', 't1.section_id', '=', 't2.id')
                ->select('t2.*', 't1.*')
                ->where('t1.template_id', $template_id);
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

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveLetterTemplateApplicableSections(Request $request)
    {
        $template_id = $request->input('template_id');
        $section_id = $request->input('section_id');
        $description = $request->input('description');
        $user_id = $this->user_id;
        $table_name = 'grm_responseletter_applicablesections';
        try {
            $where = array(
                'template_id' => $template_id,
                'section_id' => $section_id
            );
            if (recordExists($table_name, $where)) {
                /*     $params = array(
                         'description' => $description,
                         'updated_by' => $user_id,
                         'updated_at' => Carbon::now()
                     );
                     $previous_data = getPreviousRecords($table_name, $where);
                     $res = updateRecord($table_name, $previous_data, $where, $params, $user_id);*/
                $res = array(
                    'success' => false,
                    'message' => 'Section already added, use edit option to make changes!!'
                );
            } else {
                $params = $where;
                $params['description'] = $description;
                $params['created_by'] = $user_id;
                $params['updated_at'] = Carbon::now();
                $res = insertRecord($table_name, $params, $user_id);
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

    public function saveComplaintLetterTemplate(Request $request)
    {
        $complaint_id = $request->input('complaint_id');
        $template_id = $request->input('template_id');
        $user_id = $this->user_id;
        $table_name = 'grm_complaintselected_lettertemplates';
        try {
            $where = array(
                'complaint_id' => $complaint_id,
                'template_id' => $template_id,
            );
            $params = $where;
            $params['created_by'] = $user_id;
            $params['created_at'] = Carbon::now();
            if (recordExists($table_name, $where)) {
                $res = array(
                    'success' => false,
                    'message' => 'Template added already!!'
                );
            } else {
                $res = insertRecord($table_name, $params, $user_id);
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

    public function getComplaintLetterTemplates(Request $request)
    {
        $complaint_id = $request->input('complaint_id');
        $table_name = 'grm_complaintselected_lettertemplates';
        try {
            $qry = DB::table($table_name . ' as t1')
                ->join('grm_lettertypes as t2', 't1.template_id', '=', 't2.id')
                ->select('t2.*')
                ->where('t1.complaint_id', $complaint_id);
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

    // Start Maureen
    public function getComplaintResult(Request $request)
    {
        $complaint_status_id = $request->input('complaint_status_id');
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $status_id = $request->input('status_id');
        $cwac_id = $request->input('cwac_id');
        $stage_id = $request->input('stage_id');
        try {
            $qry = DB::table('grm_complaint_details as t1')
                ->join('system_statuses as t2', 't1.record_status_id', '=', 't2.id')
                ->leftJoin('grm_complaint_resolutions as t3', 't3.complaint_id', '=', 't1.id')
                ->leftJoin('grm_complaint_resolutionoptions as t4', 't4.id', '=', 't3.resolution_option_id')
                ->join('districts as t5', 't5.id', '=', 't1.district_id')
                ->join('provinces as t6', 't6.id', '=', 't1.province_id')
                ->join('cwac as t7', 't7.id', '=', 't1.cwac_id')
                ->join('grm_complaint_categories as t8', 't8.id', '=', 't1.category_id')
                ->join('wf_workflow_stages as t9', 't9.id', '=', 't1.workflow_stage_id')
                ->select('t1.*', 't2.name', 't4.name as resolved_status', 't5.name as district_name', 't6.name as province_name', 't7.name as cwac_name', 't8.name as category_name', 't9.name as stage');
            if (isset($complaint_status_id) && $complaint_status_id != 0) {
                $qry->where('t1.record_status_id', '=', $complaint_status_id);
            } else if (isset($district_id) && $district_id != 0) {
                $qry->where('t1.district_id', '=', $district_id);
            } else if (isset($province_id) && $province_id != 0) {
                $qry->where('t1.province_id', '=', $province_id);
            } else if (isset($cwac_id) && $cwac_id != 0) {
                $qry->where('t1.cwac_id', '=', $cwac_id);
            } else if (isset($status_id) && $status_id != 0) {
                $qry->where('t1.record_status_id', '=', $status_id);
            } else if (isset($stage_id) && $stage_id != 0) {
                $qry->where('t1.workflow_stage_id', '=', $stage_id);
            }
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
        return \response()->json($res);
    }

    //End Maureen
    public function getGRMFocalPersons(Request $request)
    {
        $authority_level_id = $request->input('authority_level_id');
        $province_id = $request->input('province_id');
        $district_id = $request->input('district_id');
        $complaint_id = $request->input('complaint_id');
        if (validateisNumeric($complaint_id)) {
            $district_id = getSingleRecordColValue('grm_complaint_details', array('id' => $complaint_id), 'district_id');
        }
        try {
            $qry = DB::table('grm_focal_persons as t1')
                ->join('grm_gewel_programmes as t2', 't1.programme_type_id', '=', 't2.id')
                ->leftJoin('districts as t3', 't1.district_id', '=', 't3.id')
                ->leftJoin('provinces as t4', 't1.province_id', '=', 't4.id')
                ->select('t1.*', 't2.name as programme', 't3.name as district', 't4.name as province');
            if (validateisNumeric($authority_level_id)) {
                $qry->where('t1.authority_level_id', $authority_level_id);
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

    public function getGRMFocalPersonsForLetterPrinting(Request $request)
    {
        $complaint_id = $request->input('complaint_id');
        $district_id = '';
        if (validateisNumeric($complaint_id)) {
            $district_id = getSingleRecordColValue('grm_complaint_details', array('id' => $complaint_id), 'district_id');
        }
        try {
            $qry = DB::table('grm_focal_persons as t1')
                ->join('authority_levels as t2', 't1.authority_level_id', '=', 't2.id')
                ->leftJoin('districts as t3', 't1.district_id', '=', 't3.id')
                ->select('t1.*', 't2.name as authority_level', 't3.name as district');
            if (validateisNumeric($district_id)) {
                $qry->where('t1.district_id', $district_id);
            }
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

    public function getGrmNotifications()
    {
        try {
            $qry = DB::table('grm_notifications as t1')
                ->join('grm_complaint_details as t2', 't1.record_id', '=', 't2.id')
                ->join('grm_workflow_stages as t5', 't2.workflow_stage_id', '=', 't5.id')
                ->leftJoin('grm_grievance_statuses as t6', 't2.record_status_id', '=', 't6.id')
                ->join('grm_complaint_categories as t7', 't2.category_id', '=', 't7.id')
                ->join('grm_gewel_programmes as t8', 't2.programme_type_id', '=', 't8.id')
                ->leftJoin('grm_complaint_subcategories as t9', 't2.sub_category_id', '=', 't9.id')
                ->leftJoin('wf_kgsprocesses as t10', 't2.process_id', '=', 't10.id')
                ->join('grm_workflow_stages as t11', 't2.workflow_stage_id', '=', 't11.id')
                ->join('grm_grievance_statuses as t12', 't2.record_status_id', '=', 't12.id')
                ->leftJoin('beneficiary_information as t13', 't2.girl_id', '=', 't13.id')
                ->leftJoin('households as t14', 't2.household_id', '=', 't14.id')
                ->select(DB::raw("t2.id as record_id,t2.id as complaint_id,t2.*,t1.*,t5.name as workflow_stage,
                                         t6.name as record_status,t9.name as subcategory,t10.name as process_name,
                                         t7.name as category_name,t8.code as programme_code,t5.interface_xtype,t11.name as stage,t12.name as status,
                                         t13.beneficiary_id,decrypt(t13.first_name) as first_name,decrypt(t13.last_name) as last_name,t13.dob,
                                         t14.id as household_id,t14.hhh_nrc_number,t14.hhh_fname,t14.hhh_lname,t14.number_in_cwac,
                                         TOTAL_WEEKDAYS(t2.complaint_record_date,now()) as recorded_span"))
                ->where('t1.status', 1)
                ->orderBy('t1.notification_date', 'DESC');

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

    public function updateComplaintNotificationFeedback(Request $request)
    {
        $id = $request->input('id');
        $status = $request->input('status');
        $feedback = $request->input('feedback');
        $feedback_remarks = $request->input('feedback_remarks');
        $table_name = 'grm_complaint_recommendations';
        $user_id = $this->user_id;
        $where = array(
            'id' => $id
        );
        try {
            $table_data = array(
                'feedback' => $feedback,
                'status' => $status,
                'feedback_remarks' => $feedback_remarks,
                'feedback_date' => Carbon::now(),
                'feedback_by' => $user_id
            );
            $previous_data = getPreviousRecords($table_name, $where);
            $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
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

    public function getRecordedGrievancesChartView()
    {
        try {
            $qry = DB::table('grm_complaint_details as t1')
                ->leftJoin('programme_types as t2', 't1.programme_type_id', '=', 't2.id')
                ->selectRaw('t2.name as prog_type, count(*) as count')
                ->groupBy('t2.id');
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
        }
        return response()->json($res);
    }

    public function getProgrammeNotificationEmails(Request $request)
    {
        $programme_type_id = $request->input('programme_type_id');
        try {
            $qry = DB::table('grm_complaint_submission_emails as t1')
                ->leftJoin('programme_types as t2', 't1.programme_type_id', '=', 't2.id')
                ->selectRaw('t1.*,t2.name as programme_type');
            if (validateisNumeric($programme_type_id)) {
                $qry->where('t1.programme_type_id', $programme_type_id);
            }
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

    public function saveProgrammeNotificationEmails(Request $request)
    {
        try {
            $name = $request->input('name');
            $email_address = $request->input('email_address');
            $is_active = $request->input('is_active');
            $programme_type_id = $request->input('programme_type_id');
            $res = array();
            DB::transaction(function () use ($name, $email_address, $programme_type_id, $is_active, &$res) {
                $params = array(
                    'name' => $name,
                    'email_address' => $email_address,
                    'programme_type_id' => $programme_type_id
                );
                if ($is_active == 1) {
                    $params['is_active'] = 1;
                    $params['active_from'] = Carbon::now();
                    $update = array(
                        'is_active' => 0,
                        'active_to' => Carbon::now()
                    );
                    DB::table('grm_complaint_submission_emails')
                        ->where(array('is_active' => 1, 'programme_type_id' => $programme_type_id))
                        ->update($update);
                }
                $res = insertRecord('grm_complaint_submission_emails', $params, $this->user_id);
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

    public function saveGrievanceProcessingDetails(Request $request)
    {
        try {
            $record_id = $request->input('record_id');
            $processing_option_id = $request->input('processing_option_id');
            $curr_tab_index = 1;//$request->input('record_status_id');
            $table_name = 'grm_complaint_details';
            $where = array(
                'id' => $record_id
            );
            $user_id = $this->user_id;
            $next_tab_index = $curr_tab_index + 1;
            if ($processing_option_id == 1 || $processing_option_id == 3 || $processing_option_id == 4) {//automatic response
                $next_tab_index = $curr_tab_index + 2;
            }
            if ($processing_option_id == 3 || $processing_option_id == 4) {//Referral
                $next_status_id = 3;//referred
            } else {
                $next_status_id = 1;//ongoing
            }
            $next_status_name = getSingleRecordColValue('grm_grievance_statuses', array('id' => $next_status_id), 'name');

            $table_data = array(
                'processing_option_id' => $processing_option_id,
                'processing_remarks' => $request->input('processing_remarks'),
                'referral_component_id' => $request->input('referral_component_id'),
                'tab_index' => $next_tab_index,
                'record_status_id' => $next_status_id
            );

            if ($processing_option_id == 4) {
                $table_data['programme_type_id'] = 3;//Other
                $table_data['nongewel_programme_type_id'] = $request->input('nongewel_programme_type_id');
                $table_data['category_id'] = 8;//Others
                $table_data['sub_category_id'] = $request->input('sub_category_id');
            }

            $previous_data = getPreviousRecords($table_name, $where);
            $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
            $res['next_status_id'] = $next_status_id;
            $res['next_status_name'] = $next_status_name;
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

    public function saveGrievanceInvestigationDetails(Request $req)
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
            unset($post_data['fileNames']);
            unset($post_data['model']);
            unset($post_data['id']);
            unset($post_data['uploaded_files']);
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
                    if ($res['success'] == false) {
                        return response()->json($res);
                    }
                }
            } else {
                $res = insertRecord($table_name, $table_data, $user_id);
                if ($res['success'] == false) {
                    return response()->json($res);
                }
                $id = $res['record_id'];
            }
            //handle file upload
            if ($res['success'] == true) {
                if ($req->hasFile('uploaded_files')) {
                    $totalFiles = count($_FILES['uploaded_files']['name']);
                    $destinationPath = public_path('storage/grm_investigation_uploads/');
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
                                    'record_id' => $id,
                                    'initial_name' => $_FILES['uploaded_files']['name'][$i],
                                    'file_size' => $_FILES['uploaded_files']['size'][$i],
                                    'file_type' => $_FILES['uploaded_files']['type'][$i],
                                    'saved_name' => $newFileName,
                                    'server_filepath' => url('/') . '/backend/public/storage/grm_investigation_uploads/'
                                );
                            }
                        }
                    }
                    DB::table('grm_grievance_investigationuploads')
                        ->where('record_id', $id)
                        ->delete();
                    DB::table('grm_grievance_investigationuploads')
                        ->insert($upload_details);
                }
            }
            //end
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

    public function getGrievanceInvestigationDetails(Request $request)
    {
        $complaint_id = $request->input('complaint_id');
        try {
            $qry = DB::table('grm_grievance_investigationdetails as t1')
                ->where('t1.complaint_id', $complaint_id);

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

    public function validateGrievanceResolution(Request $request)
    {
        try {
            $record_id = $request->input('record_id');
            $record_status_id = $request->input('record_status_id');
            $asResolvedAnyway = $request->input('asResolvedAnyway');
            if ($asResolvedAnyway == 1 || $asResolvedAnyway === 1) {
                $this->markGrievanceAsResolved($record_id, $record_status_id);
                $res = array(
                    'success' => true,
                    'message' => 'Request executed successfully, the grievance has been marked as resolved and archived!!'
                );
            } else {
                if (recordExists('grm_responseletters_snapshot', array('complaint_id' => $record_id))) {
                    $this->markGrievanceAsResolved($record_id, $record_status_id);
                    $res = array(
                        'success' => true,
                        'message' => 'Request executed successfully, the grievance has been marked as resolved and archived!!'
                    );
                } else {
                    $res = array(
                        'success' => 2,
                        'message' => 'You have not printed a response letter, mark it as resolved anyway?'
                    );
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

    public function markGrievanceAsResolved($record_id, $record_status_id)
    {
        if ($record_status_id == 3 || $record_status_id === 3) {
            $updateParams = array(
                'workflow_stage_id' => 3,
                'tab_index' => 0,
                'complaint_resolution_date' => Carbon::now()
            );
        } else {
            $updateParams = array(
                'workflow_stage_id' => 3,
                'tab_index' => 0,
                'record_status_id' => 2,
                'complaint_resolution_date' => Carbon::now()
            );
        }
        DB::table('grm_complaint_details')
            ->where('id', $record_id)
            ->update($updateParams);
    }

    public function saveGrievanceAppealDetails(Request $request)
    {
        $complaint_id = $request->input('complaint_id');
        try {
            $appealDetails = array(
                'complaint_id' => $complaint_id,
                'appeal_reason_id' => $request->input('appeal_reason_id'),
                'remark' => $request->input('remark'),
                'created_at' => Carbon::now(),
                'created_by' => $this->user_id
            );
            Db::table('grm_appealed_grievances')->insert($appealDetails);
            DB::table('grm_complaint_details')
                ->where('id', $complaint_id)
                ->update(array('workflow_stage_id' => 2, 'tab_index' => 1, 'record_status_id' => 4));
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

    public function getGrievanceAppealDetails(Request $request)
    {
        $complaint_id = $request->input('complaint_id');
        try {
            $qry = DB::table('grm_appealed_grievances as t1')
                ->join('grm_grievance_appealreasons as t2', 't1.appeal_reason_id', '=', 't2.id')
                ->leftJoin('users as t3', 't1.created_by', '=', 't3.id')
                ->select(DB::raw("t1.*,t2.name as appeal_reason,CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as appeal_by"))
                ->where('t1.complaint_id', $complaint_id)
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

    public function getGrievanceFormsDetails()
    {
        try {
            $qry = DB::table('grm_grievance_forms as t1')
                ->join('users as t2', 't1.created_by', '=', 't2.id')
                ->join('districts as t3', 't1.district_id', '=', 't3.id')
                ->select(DB::raw("t1.*,CONCAT_WS(' ',decrypt(t2.first_name),decrypt(t2.last_name)) as author,t3.name as district"));
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

    public function saveGrievanceFormsDetailsold(Request $request)
    {
        try {
            $serial_from = $request->input('serial_from');
            $serial_to = $request->input('serial_to');
            $district_id = $request->input('district_id');

            $params = array(
                'serial_from' => $serial_from,
                'serial_to' => $serial_to,
                'district_id' => $district_id,
                'created_at' => Carbon::now(),
                'created_by' => $this->user_id
            );
            $checkQry1 = DB::table('grm_grievance_forms as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->whereRaw("$serial_from BETWEEN serial_from AND serial_to");
            $checkResults1 = $checkQry1->first();

            $checkQry2 = DB::table('grm_grievance_forms as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->whereRaw("$serial_to BETWEEN serial_from AND serial_to");
            $checkResults2 = $checkQry2->first();

            $checkQry3 = DB::table('grm_grievance_forms as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->where(function ($query) use ($serial_from, $serial_to) {
                    $query->whereBetween('t1.serial_from', [$serial_from, $serial_to])
                        ->orWhereBetween('t1.serial_to', [$serial_from, $serial_to]);
                });
            $checkResults3 = $checkQry3->first();

            if (!is_null($checkResults1)) {
                $res = array(
                    'success' => false,
                    'message' => 'The lower bound entered falls under an already captured range [' . $checkResults1->serial_from . '-' . $checkResults1->serial_to . '] for ' . $checkResults1->name . ' district !!'
                );
                return response()->json($res);
            }
            if (!is_null($checkResults2)) {
                $res = array(
                    'success' => false,
                    'message' => 'The upper bound entered falls under an already captured range [' . $checkResults2->serial_from . '-' . $checkResults2->serial_to . '] for ' . $checkResults2->name . ' district !!'
                );
                return response()->json($res);
            }
            if (!is_null($checkResults3)) {
                $res = array(
                    'success' => false,
                    'message' => 'This range violates the rules. There is an existing range of [' . $checkResults3->serial_from . '-' . $checkResults3->serial_to . '] for ' . $checkResults3->name . ' district. You can consider dividing the range into allowed limits !!'
                );
                return response()->json($res);
            }
            /*  $max_serial = DB::table('grm_grievance_forms')
                  ->max('serial_to');
              if ($serial_from <= $max_serial) {
                  $res = array(
                      'success' => false,
                      'message' => 'Current maximum serial number is ' . $max_serial . ', Serial numbers should therefore start from ' . ($max_serial + 1)
                  );
                  return response()->json($res);
              }*/
            DB::table('grm_grievance_forms')
                ->insert($params);
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

    public function saveGrievanceFormsDetails(Request $request)
    {
        try {
            $serial_from = $request->input('serial_from');
            $serial_to = $request->input('serial_to');
            $district_id = $request->input('district_id');
            $record_id = $request->input('id');
            $params = array(
                'serial_from' => $serial_from,
                'serial_to' => $serial_to,
                'district_id' => $district_id,
                'created_at' => Carbon::now(),
                'created_by' => $this->user_id
            );
            $checkQry1 = DB::table('grm_grievance_forms as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->whereRaw("$serial_from BETWEEN serial_from AND serial_to")
                ->select(DB::raw("t2.id,t1.id as record_id, t1.serial_from,t1.serial_to, t2.name, t1.district_id,t1.created_at, t2.province_id, t2.debs_id, t2.code,t2.description,t2.prevrecord_id,t1.created_by,t1.updated_by, t2.grm_focalperson_id"));
            $checkResults1 = $checkQry1->first();
            if(!empty($checkResults1)){
                $checkResults1_recordId=$checkResults1->record_id;
            }else{
                $checkResults1_recordId=0;
            }
            $checkQry2 = DB::table('grm_grievance_forms as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->whereRaw("$serial_to BETWEEN serial_from AND serial_to")
                ->select(DB::raw("t2.id,t1.id as record_id, t1.serial_from,t1.serial_to, t2.name, t1.district_id,t1.created_at, t2.province_id, t2.debs_id, t2.code,t2.description,t2.prevrecord_id,t1.created_by,t1.updated_by, t2.grm_focalperson_id"));
            $checkResults2 = $checkQry2->first();
            if(!empty($checkResults2)){
                $checkResults2_recordId=$checkResults2->record_id;
            }else{
                $checkResults2_recordId=0;
            }
            $checkQry3 = DB::table('grm_grievance_forms as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->where(function ($query) use ($serial_from, $serial_to) {
                    $query->whereBetween('t1.serial_from', [$serial_from, $serial_to])
                        ->orWhereBetween('t1.serial_to', [$serial_from, $serial_to]);
                })
                ->select(DB::raw("t2.id,t1.id as record_id, t1.serial_from,t1.serial_to, t2.name, t1.district_id,t1.created_at, t2.province_id, t2.debs_id, t2.code,t2.description,t2.prevrecord_id,t1.created_by,t1.updated_by, t2.grm_focalperson_id"));
            $checkResults3 = $checkQry3->first();
            if(!empty($checkResults3)){
                $checkResults3_recordId=$checkResults3->record_id;
            }else{
                $checkResults3_recordId=0;
            }
            if((isset($record_id) && $record_id == $checkResults1_recordId) || (isset($record_id) && $record_id == $checkResults1_recordId)){
                    if($record_id == $checkResults1_recordId){
                        if($record_id != $checkResults2_recordId){
                            $res = array(
                                'success' => false,
                                'message' => 'The upper bound entered falls under an already captured range [' . $checkResults2->serial_from . '-' . $checkResults2->serial_to . '] for ' . $checkResults2->name . ' district !!'
                                );
                        }else{
                            DB::table('grm_grievance_forms')
                            ->where('id', $checkResults1_recordId)
                            ->update($params);

                             $res = array(
                                    'success' => true,
                                    'message' => 'Details saved successfully testing!!'
                                );

                        }
                     }else if($record_id == $checkResults2_recordId){
                        if($record_id != $checkResults1_recordId){
                            $res = array(
                            'success' => false,
                            'message' => 'The lower bound entered falls under an already captured range [' . $checkResults1->serial_from . '-' . $checkResults1->serial_to . '] for ' . $checkResults1->name . ' district !!'
                            );

                        }else{
                            DB::table('grm_grievance_forms')
                            ->where('id', $checkResults2_recordId)
                            ->update($params);
                             $res = array(
                                    'success' => true,
                                    'message' => 'Details saved successfully!!'
                                );
                        }

                     }
               
                 return response()->json($res);
            }else{
                //lowerbound check
                if (!is_null($checkResults1)) {

                    $res = array(
                        'success' => false,
                        'message' => 'The lower bound entered falls under an already captured range [' . $checkResults1->serial_from . '-' . $checkResults1->serial_to . '] for ' . $checkResults1->name . ' district !!'
                    );
                     return response()->json($res);
                 }
                 //Upperbound Check
                 if (!is_null($checkResults2)) {
                    $res = array(
                        'success' => false,
                        'message' => 'The upper bound entered falls under an already captured range [' . $checkResults2->serial_from . '-' . $checkResults2->serial_to . '] for ' . $checkResults2->name . ' district !!'
                    );
                    return response()->json($res);
                }
                //Range check
                if (!is_null($checkResults3)) {
                    $res = array(
                        'success' => false,
                        'message' => 'This range violates the rules. There is an existing range of [' . $checkResults3->serial_from . '-' . $checkResults3->serial_to . '] for ' . $checkResults3->name . ' district. You can consider dividing the range into allowed limits !!'
                    );
                    return response()->json($res);
                }
                // if(isset($record_id)){
                //         DB::table('grm_grievance_forms')
                //         ->where('id', $record_id)
                //         ->update($params);

                // }else{
                         DB::table('grm_grievance_forms')
                        ->insert($params);
               // }
                 $res = array(
                    'success' => true,
                    'message' => 'Details saved successfully!!'
                     );
                   
            }
          

            
        } catch (\Exception $exception) {
            echo" error thrown record_id =".$record_id;
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

    public function validateFormSerialNumber(Request $request)
    {
        $form_serial = $request->input('form_serial');
        try {
            $qry = DB::table('grm_grievance_forms as t1')
                ->join('districts as t2', 't1.district_id', 't2.id')
                ->select('t1.*', 't2.province_id')
                ->whereRaw("$form_serial BETWEEN t1.serial_from AND t1.serial_to");
            $result = $qry->first();
            if (is_null($result)) {
                $res = array(
                    'success' => false,
                    'message' => 'Serial number entered is not registered in the system----Serial number not found!!'
                );
                return response()->json($res);
            }
            $res = array(
                'success' => true,
                'result' => $result
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

    public function dismissGrmNotification(Request $request)
    {
        $item_ids = $request->input();
        try {
            DB::table('grm_notifications')
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

    public function getGrmEmailNotificationsSetup(Request $request)
    {
        try {
            $level = $request->input('level');
            $province_id = $request->input('province_id');
            $program_type_id = $request->input('program_type_id');
            $qry = DB::table('grm_emailnotifications_setup as t1')
                ->join('grm_gewel_programmes as t2', 't1.gewel_programme_id', '=', 't2.id')
                ->leftJoin('provinces as t4', 't1.province_id', '=', 't4.id')
                ->leftJoin('districts as t5', 't1.district_id', '=', 't5.id')
                ->select('t1.*', 't2.name as gewel_programme', 't4.name as province', 't5.name as district');
            if (validateisNumeric($level)) {
                $qry->where('t1.level', $level);
            }
            if (validateisNumeric($province_id)) {
                $qry->where('t1.province_id', $province_id);
            }
            if (validateisNumeric($program_type_id)) {
                $qry->where('t1.gewel_programme_id', $program_type_id);
            }
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

    public function getGrmResponseLetterSnapShot(Request $request)
    {
        $complaint_id = $request->input('complaint_id');
        try {
            $details = DB::table('grm_responseletters_snapshot')
                ->select(DB::raw("focal_person,email_notification,primary_email,secondary_emails,email_subject,email_body"))
                ->where('complaint_id', $complaint_id)
                ->latest('log_date')
                ->first();
            $res = array(
                'success' => true,
                'results' => $details,
                'message' => 'Successful'
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

    public function getAllGrievanceResponseLetterSnapShots(Request $request)
    {
        $complaint_id = $request->input('complaint_id');
        try {
            $qry = DB::table('grm_responseletters_snapshot as t1')
                ->join('grm_lettertypes as t2', 't1.template_id', '=', 't2.id')
                ->leftJoin('grm_workflow_stages as t3', 't1.workflow_stage_id', '=', 't3.id')
                ->select('t2.name as template', 't1.*', 't3.name as workflow_stage')
                ->where('t1.complaint_id', $complaint_id);
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

    public function getInitialMonitoringWorkflowDetails(Request $request)
    {
        $process_id = $request->input('process_id');
        try {
            $processName = getSingleRecordColValue('wf_kgsprocesses', array('id' => $process_id), 'name');
            $details = DB::table('grm_monitoring_stages')
                ->where('order', 1)
                ->first();
            if ($details) {
                $details->processName = $processName;
            }
            $res = array(
                'success' => true,
                'message' => 'Successful',
                'result' => $details
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

    public function handleGrmMonitoringSubmission(Request $request)
    {
        $record_id = $request->input('record_id');
        $workflow_stage_id = $request->input('workflow_stage_id');
        $direction = $request->input('direction');
        if ($direction == 'next') {
            $next_workflow_stage_id = $workflow_stage_id + 1;
        } else if ($direction == 'previous') {
            $next_workflow_stage_id = $workflow_stage_id - 1;
        } else {
            $next_workflow_stage_id = $workflow_stage_id;
        }
        try {
            $params = array(
                'workflow_stage_id' => $next_workflow_stage_id,
                'updated_by' => $this->user_id,
                'updated_at' => Carbon::now()
            );
            DB::table('grm_monitoring_plan_details')
                ->where('id', $record_id)
                ->update($params);
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

    public function resendFailedGrmEmails(Request $request)
    {
        try {
            $selectedEmails = $request->input('selected_emails');
            if (isset($selectedEmails) && $selectedEmails != "") {
                $selected_emails = json_decode($selectedEmails, true);
                foreach ($selected_emails as $key => $emailContent) {
                    if (is_connected()) {
                        $singleMailData = array(
                            'id' => $emailContent['id'],
                            'emails_to' => $emailContent['email_to'],
                            'cc_emails' => $emailContent['cc_to'],
                            'subject' => $emailContent['subject'],
                            'programme_name' => $emailContent['programme_name'],
                            'body' => $emailContent['body']
                        );
                        $dispatchResponse = $this->performMailResend($singleMailData);
                        $res = array(
                            'success' => $dispatchResponse['success'],
                            'message' => $dispatchResponse['message']
                        );
                    } else {
                        $res = array(
                            'success' => false,
                            'message' => 'Poor Internet connection, Execution failed!!'
                        );
                    }
                }
            } else {
                $singleMailData = array(
                    'id' => $request->input('id'),
                    'emails_to' => $request->input('email_to'),
                    'cc_emails' => $request->input('cc_to'),
                    'subject' => $request->input('subject'),
                    'programme_name' => $request->input('programme_name'),
                    'body' => $request->input('body')
                );
                $dispatchResponse = $this->performMailResend($singleMailData);
                $res = array(
                    'success' => $dispatchResponse['success'],
                    'message' => $dispatchResponse['message']
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

    public function deleteFailedGrmEmails(Request $request)
    {
        try {
            $rec_ids = array();
            $selectedEmails = $request->input('selected_emails');
            $selected_emails = json_decode($selectedEmails, true);
            foreach ($selected_emails as $selected_email) {
                $rec_ids[] = array(
                    $selected_email['id']
                );
            }
            DB::table('tra_failed_emails')
                ->whereIn('id', $rec_ids)
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

    public function performMailResend($request)
    {
        try {
            $id = $request['id'];
            $emails_to = explode(',', $request['emails_to']);
            $cc_emails = explode(',', $request['cc_emails']);
            $emailJob = (new ComplaintSubmissionEmailJob($emails_to, $request['subject'], $request['body'], $request['programme_name'], $cc_emails))
                ->delay(Carbon::now()->addSeconds(10));
            dispatch($emailJob);
            DB::table('tra_failed_emails')->where('id', $id)->delete();
            $res = array(
                'success' => true,
                'message' => 'Mail Sent successfully!!'
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

    public function exportGRMRecords(Request $request)
    {
        try {
            $extraParams = $request->input('extraparams');
            $extraParams = json_decode($extraParams, true);
            if (empty($extraParams)) {
                $extraParams = array();
            }

            $route = $request->input('route');
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

    public function batchSubmitComplaintDetails(Request $request)
    { 
        // DB::beginTransaction();
        try {
            $user_id = $this->user_id;
            $groups = getUserGroups($user_id);
            $superUserID = getSuperUserGroupId();
            $results = DB::select("select t1.id as record_id,t1.id as complaint_id,t1.*,t2.name as district_name,t3.name as cwac_name,t4.name as school_name,t5.name as process_name,t6.name as workflow_stage,
                t7.name as record_status,t8.beneficiary_id,decrypt(t8.first_name) as first_name,decrypt(t8.last_name) as last_name,t8.dob,t15.name as category_subcategory,t16.name as province_name,t17.code as non_gewel_code,
                t12.name as category_name,t13.id as household_id,t13.hhh_nrc_number,t13.hhh_fname,t13.hhh_lname,t13.number_in_cwac,t14.code as programme_code,t6.interface_xtype,
                CONCAT_WS(' ',decrypt(t18.first_name),decrypt(t18.last_name)) as recorded_by 
                from `grm_complaint_details` as `t1` 
                inner join `districts` as `t2` on `t1`.`district_id` = `t2`.`id` 
                inner join `cwac` as `t3` on `t1`.`cwac_id` = `t3`.`id` 
                left join `school_information` as `t4` on `t1`.`school_id` = `t4`.`id` 
                left join `wf_kgsprocesses` as `t5` on `t1`.`process_id` = `t5`.`id` 
                inner join `grm_workflow_stages` as `t6` on `t1`.`workflow_stage_id` = `t6`.`id` 
                left join `grm_grievance_statuses` as `t7` on `t1`.`record_status_id` = `t7`.`id` 
                left join `beneficiary_information` as `t8` on `t1`.`girl_id` = `t8`.`id` 
                inner join `grm_complaint_categories` as `t12` on `t1`.`category_id` = `t12`.`id` 
                left join `households` as `t13` on `t1`.`household_id` = `t13`.`id` 
                inner join `grm_gewel_programmes` as `t14` on `t1`.`programme_type_id` = `t14`.`id` 
                left join `grm_complaint_subcategories` as `t15` on `t1`.`sub_category_id` = `t15`.`id` 
                inner join `provinces` as `t16` on `t2`.`province_id` = `t16`.`id` 
                left join `grm_nongewel_programmes` as `t17` on `t1`.`nongewel_programme_type_id` = `t17`.`id` 
                left join `users` as `t18` on `t1`.`complaint_recorded_by` = `t18`.`id` 
                where `t1`.`workflow_stage_id` = 1 and `is_mobile` = 1 and t1.id IN (26363,26365)
                ");
            $table_name = 'grm_complaint_details';
            $submitted_count = 0;
            foreach ($results as $key => $single_result) {
                $record_id = $single_result->record_id;
                $workflow_stage_id = 2;
                $record_status_id = 1;
                $tab_index = 1;
                // $responsible_user = $single_result->responsible_user;
                $user_id = $this->user_id;         
                $record_details = DB::table($table_name)
                    ->where('id', $record_id)
                    ->first();
                // $this->complaintSubmissionEmailNotification($record_details, $responsible_user, 1, 1, 2);
                $user_id = $this->user_id;
                $id = $single_result->complaint_id;
                $complaint_form_no = $single_result->complaint_form_no;
                $programme_type_id = $single_result->programme_type_id;
                $nongewel_programme_type_id = $single_result->nongewel_programme_type_id;
                $complaint_lodge_date = $single_result->complaint_lodge_date;
                $complaint_record_date = Carbon::now();
                $province_id = $single_result->province_id;
                $district_id = $single_result->district_id;
                $cwac_id = $single_result->cwac_id;
                $school_id = $single_result->school_id;
                $complainant_gender_id = $single_result->complainant_gender_id;
                $complainant_age = $single_result->complainant_age;
                $complaint_details = $single_result->complaint_details;
                $category_id = $single_result->category_id;
                $sub_category_id = $single_result->sub_category_id;
                $isOfflineSubmission = 1;

                $project_staff_involved = $single_result->project_staff_involved;
                $proj_staff_name = $single_result->proj_staff_name;
                $proj_staff_other_details = $single_result->proj_staff_other_details;

                $complainant_first_name = $single_result->complainant_first_name;
                $complainant_last_name = $single_result->complainant_last_name;
                $complainant_nrc = $single_result->complainant_nrc;
                $complainant_remarks = $single_result->complainant_remarks;
                $complainant_village = $single_result->complainant_village;
                $complainant_mobile = $single_result->complainant_mobile;
                $complainant_email = $single_result->complainant_email;
                $girl_id = $single_result->girl_id;
                $household_id = $single_result->household_id;

                $complaint_collection_date = $single_result->complaint_collection_date;
                $complaint_collector = $single_result->complaint_collector;

                if ($project_staff_involved == 2) {
                    $proj_staff_name = '';
                    $proj_staff_other_details = '';
                }

                $res = array();
                $operation = 'create';
                $table_data = array(
                    'complaint_form_no' => $complaint_form_no,
                    'programme_type_id' => $programme_type_id,
                    'nongewel_programme_type_id' => $nongewel_programme_type_id,
                    'complaint_lodge_date' => $complaint_lodge_date,
                    'province_id' => $province_id,
                    'district_id' => $district_id,
                    'cwac_id' => $cwac_id,
                    'process_id' => 1,
                    'school_id' => $school_id,
                    'complainant_gender_id' => $complainant_gender_id,
                    'complainant_age' => $complainant_age,
                    'complaint_details' => $complaint_details,
                    'category_id' => $category_id,
                    'sub_category_id' => $sub_category_id,
                    'project_staff_involved' => $project_staff_involved,
                    'proj_staff_name' => $proj_staff_name,
                    'proj_staff_other_details' => $proj_staff_other_details,
                    'complainant_first_name' => $complainant_first_name,
                    'complainant_last_name' => $complainant_last_name,
                    'complainant_nrc' => $complainant_nrc,
                    'complainant_remarks' => $complainant_remarks,
                    'complainant_village' => $complainant_village,
                    'complainant_mobile' => $complainant_mobile,
                    'complainant_email' => $complainant_email,
                    'girl_id' => $girl_id,
                    'household_id' => $household_id,
                    'complaint_collection_date' => $complaint_collection_date,
                    'complaint_collector' => $complaint_collector
                );
                $where = array(
                    'id' => $id
                );
                $workflow_stage_id = 2;
                $record_status_id = 1;
                $tab_index = 1;
                //category details
                $category_details = getTableData('grm_complaint_categories', array('id' => $category_id));
                $immediate_escalation = 0;
                /*if ($category_details) {
                    $immediate_escalation = $category_details->immediate_escalation;
                }*/
                if (validateisNumeric($id)) {
                    if (recordExists($table_name, $where)) {
                        $operation = 'update';
                        if ($isOfflineSubmission == 1) {
                            if ($programme_type_id == 3) {
                                $record_status_id = 3;
                                $tab_index = 3;
                                $table_data['processing_option_id'] = 4;
                            }
                            $table_data['workflow_stage_id'] = $workflow_stage_id;
                            $table_data['record_status_id'] = $record_status_id;
                            $table_data['tab_index'] = $tab_index;
                        }
                        $table_data['updated_at'] = Carbon::now();
                        $table_data['updated_by'] = $user_id;
                        $table_data['curr_from_userid'] = $user_id;
                        $table_data['curr_to_userid'] = $user_id;
                        $previous_data = getPreviousRecords($table_name, $where);
                        $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                        $res['record_id'] = $id;
                        if ($isOfflineSubmission == 1) {
                            //notifications
                            //1. Escalation
                            /*if ($immediate_escalation == 1 || $immediate_escalation === 1) {
                                $not_details = array(
                                    'record_id' => $res['record_id'],
                                    'notification_type_id' => 1,
                                    'notification_date' => Carbon::now(),
                                    'status' => 1
                                );
                                DB::table('grm_notifications')->insert($not_details);
                                $this->grievanceEscalationEmailNotification($programme_type_id, $province_id, $complaint_form_no);//HQ & Province
                            }*/
                            //2. Referrals
                            if ($programme_type_id == 3 || $programme_type_id === 3) {
                                // $this->grievanceReferralEmailNotification($request, $nongewel_programme_type_id);
                                // $this->grievanceReferralEmailNotification($id, $request, $nongewel_programme_type_id);
                            }
                        }
                    }
                    //$folder_id = $previous_data[0]['folder_id'];
                } else {
                    //check if form has been captured already
                    $check_details = DB::table($table_name . ' as t1')
                        ->join('grm_workflow_stages as t2', 't1.workflow_stage_id', '=', 't2.id')
                        ->join('users as t3', 't1.complaint_recorded_by', '=', 't3.id')
                        ->select(DB::raw("t2.name as workflow_stage,CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as captured_by"))
                        ->where('complaint_form_no', $complaint_form_no)
                        ->first();
                    /*if (!is_null($check_details)) {
                        $res = array(
                            'success' => false,
                            'message' => 'This complaint form number has been captured already (By ' . $check_details->captured_by . '), the grievance is currently in [' . $check_details->workflow_stage . '] stage'
                        );
                        return response()->json($res);
                    }*/

                    if ($programme_type_id == 3) {
                        $record_status_id = 3;
                        $tab_index = 3;
                        $table_data['processing_option_id'] = 4;
                    }

                    $table_data['workflow_stage_id'] = $workflow_stage_id;
                    $table_data['record_status_id'] = $record_status_id;
                    $table_data['tab_index'] = $tab_index;
                    $table_data['view_id'] = generateRecordViewID();
                    $table_data['created_at'] = Carbon::now();
                    $table_data['created_by'] = $user_id;
                    $table_data['complaint_recorded_by'] = $user_id;
                    $table_data['complaint_record_date'] = $complaint_record_date;
                    $res = insertRecord($table_name, $table_data, $user_id);
                    $id = $res['record_id'];
                    //notifications
                    //1. Escalation
                    /*if ($immediate_escalation == 1 || $immediate_escalation === 1) {
                        $not_details = array(
                            'record_id' => $res['record_id'],
                            'notification_type_id' => 1,
                            'notification_date' => Carbon::now(),
                            'status' => 1
                        );
                        DB::table('grm_notifications')->insert($not_details);
                        $this->grievanceEscalationEmailNotification($programme_type_id, $province_id, $complaint_form_no);//HQ & Province
                    }*/
                    //2. Referrals
                    if ($programme_type_id == 3 || $programme_type_id === 3) {
                        // $this->grievanceReferralEmailNotification($request, $nongewel_programme_type_id);
                        // $this->grievanceReferralEmailNotification($id, $request, $nongewel_programme_type_id);
                    }
                }
                //handle file upload
                /*if ($res['success'] == true) {
                    if ($request->hasFile('uploaded_files')) {
                        $totalFiles = count($_FILES['uploaded_files']['name']);
                        $destinationPath = public_path('storage/grm_grievanceforms_uploads/');
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
                                        'record_id' => $id,
                                        'initial_name' => $_FILES['uploaded_files']['name'][$i],
                                        'file_size' => $_FILES['uploaded_files']['size'][$i],
                                        'file_type' => $_FILES['uploaded_files']['type'][$i],
                                        'saved_name' => $newFileName,
                                        'server_filepath' => url('/') . '/backend/public/storage/grm_grievanceforms_uploads/',
                                        'server_filedirectory' => public_path('storage/grm_grievanceforms_uploads/')
                                    );
                                }
                            }
                        }
                        DB::table('grm_grievance_formsuploads')
                            ->where('record_id', $id)
                            ->delete();
                        DB::table('grm_grievance_formsuploads')
                            ->insert($upload_details);
                    }
                    //check for need to notify district GRM focal point persons
                    // $this->grievanceLodgedNotificationToDistrict($request);
                    //send referral emails here...to allow for attachments
                    if ($operation == 'update' && $isOfflineSubmission != 1) {
                        //dont send email
                    } else {
                        if ($programme_type_id == 3 || $programme_type_id === 3) {
                            $this->grievanceReferralEmailNotification($id, $request, $nongewel_programme_type_id);
                        }
                    }
                }*/
                //end
                $res['form_no'] = $complaint_form_no;
                $res['success_response'] = true;
                unset($res['message']);
                unset($res['success']);
                $log_result = DB::table('grm_batchsubmit_log')
                    ->insert($res);
                $submitted_count++;
            }
            $res['submitted_count'] = $submitted_count;
            // DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
            if ($exception instanceof ValidationException) {
                $errors = $validator->errors()->all();
                $error_count = count($errors);
                $error_msg = '';
                for ($i = 0; $i < $error_count; $i++) {
                    if ($i == $error_count - 1) {
                        $error_msg .= '[' . $errors[$i] . ']';
                    } else {
                        $error_msg .= '[' . $errors[$i] . '], ';
                    }
                }
                $res = array(
                    'success' => false,
                    'message' => $exception->getMessage() . '...' . $error_msg
                );
            }
        } catch (\Throwable $throwable) {
            DB::rollBack();
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return response()->json($res);
    }

}
