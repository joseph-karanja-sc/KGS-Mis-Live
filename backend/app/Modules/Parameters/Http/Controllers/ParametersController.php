<?php

namespace App\Modules\Parameters\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Jobs\GenericSendEmailJob;
use App\Jobs\ProcessBeneficiaryImagesJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ParametersController extends BaseController
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
        return view('parameters::index');
    }

    public function saveParamCommonDataInitial(Request $req)
    {
        $user_id = $this->user_id;
        $post_data = $req->all();
        $table_name = $post_data['table_name'];
        $id = $post_data['id'];
        $skip = $post_data['skip'];
        $skipArray = explode(",", $skip);
        //unset unnecessary values
        unset($post_data['_token']);
        unset($post_data['table_name']);
        unset($post_data['model']);
        unset($post_data['id']);
        unset($post_data['skip']);
        // $table_data = encryptArray($post_data, $skipArray);
        $table_data = $post_data;
        //add extra params
        $table_data['created_at'] = Carbon::now();
        $table_data['created_by'] = $user_id;
        $where = array(
            'id' => $id
        );
        try {
            if (isset($id) && $id != "") {
                if (recordExists($table_name, $where)) {
                    unset($table_data['created_at']);
                    unset($table_data['created_by']);
                    $table_data['updated_at'] = Carbon::now();
                    $table_data['updated_by'] = $user_id;
                    $previous_data = getPreviousRecords($table_name, $where);
                    $record_id = $id;
                    $success = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                    if ($success) {
                        $res = array(
                            'success' => true,
                            'message' => 'Data updated Successfully!!',
                            'record_id' => $record_id
                        );
                    } else {
                        $res = array(
                            'success' => false,
                            'message' => 'Problem encountered while updating data. Try again later!!'
                        );
                    }
                }
            } else {
                $record_id = insertRecordReturnId($table_name, $table_data, $user_id);
                if (validateisNumeric($record_id)) {
                    $res = array(
                        'success' => true,
                        'message' => 'Data Saved Successfully!!',
                        'record_id' => $record_id
                    );
                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'Problem encountered while saving details!!'
                    );
                }
            }
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function saveParamCommonDataInitial2(Request $req)
    {
        $user_id = $this->user_id;
        $post_data = $req->all();
        $table_name = $post_data['table_name'];
        $id = $post_data['id'];
        $is_duplicate=false;
        $folder_id="";
        //unset unnecessary values
        unset($post_data['_token']);
        unset($post_data['table_name']);
        unset($post_data['model']);
        unset($post_data['id']);
        unset($post_data['skip']);
        // $table_data = encryptArray($post_data, $skipArray);
        $table_data = $post_data;
        //add extra params
        $table_data['created_at'] = Carbon::now();
        $table_data['created_by'] = $user_id;
        $where = array(
            'id' => $id
        );
        try {
            if (isset($id) && $id != "") {
                if (recordExists($table_name, $where)) {
                    unset($table_data['created_at']);
                    unset($table_data['created_by']);
                    $table_data['updated_at'] = Carbon::now();
                    $table_data['updated_by'] = $user_id;
                    $previous_data = getPreviousRecords($table_name, $where);
                    //$table_data['payment_schedule']=2;
                    $record_id = $id;
                    if($table_name=="ar_insurance_policies")
                    {
                        unset($table_data['asset_count']);
                    }
                    $success = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                    if ($success) {
                        $res = array(
                            'success' => true,
                            'message' => 'Data updated Successfully!!',
                            'record_id' => $record_id
                        );
                    } else {
                        $res = array(
                            'success' => false,
                            'message' => 'Problem encountered while updating data. Try again later!!'
                        );
                    }
                }
            } else {

                if($table_name=="ar_insurance_policies")
                {
                    unset($table_data['asset_count']);
                    $if_duplicate_policy_no=DB::table('ar_insurance_policies')->where('policy_no',$table_data['policy_no'])->value('policy_no');
                    if( (strtolower($if_duplicate_policy_no))!=(strtolower($table_data['policy_no']))){
                    $parent_id= 226347;
                    $parent_id=228976;
                    $folder_id=createAssetDMSParentFolder($parent_id,34 , $table_data['policy_no'], '', $this->dms_id);
                    $table_data['folder_id']=$folder_id;
                    createAssetRegisterDMSModuleFolders($folder_id, 34,36, $this->dms_id);
                    }else{
                        $is_duplicate=true;
                    }
                    
                }
                if($table_name=="ar_funding_details")
                {
                    $if_duplicate_funding_name=DB::table('ar_funding_details')->where('name',$table_data['name'])->value('name');
                    if( (strtolower($if_duplicate_funding_name))==(strtolower($table_data['name']))){
                        $is_duplicate=true;
                    }else{
                    $parent_id= 226347;
                     $parent_id=228976;
                    $folder_id=createAssetDMSParentFolder($parent_id,34, $table_data['name'], '', $this->dms_id);
                    $table_data['folder_id']=$folder_id;
                    createAssetRegisterDMSModuleFolders($folder_id, 34,37, $this->dms_id);
                 }
                   
                    
                }
                $insert_info=array();
                if($is_duplicate==false){
                $insert_info = insertRecord($table_name, $table_data, $user_id);
                }else{
                    $insert_info=[
                        "success"=>false,
                        "message"=>"Similar record found!!"
                    ];
                }

               
                if (!$insert_info['success']) {
                    $res = $insert_info;
                } else {
                    $res = array(
                        'success' => true,
                        'message' => 'Data Saved Successfully!!',
                        'record_id' => $insert_info['record_id']
                    );
                    if($folder_id!="")
                    {
                        $res['folder_id']=$folder_id;
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

    public function saveParamCommonData(Request $req)
    {
        $user_id = $this->user_id;
        $post_data = $req->all();
        $table_name = $post_data['table_name'];
        $id = $post_data['id'];
        $is_duplicate=false;
        $folder_id="";
        //unset unnecessary values
        unset($post_data['_token']);
        unset($post_data['table_name']);
        unset($post_data['model']);
        unset($post_data['id']);
        unset($post_data['skip']);
        // $table_data = encryptArray($post_data, $skipArray);
        $table_data = $post_data;
        //add extra params
        $table_data['created_at'] = Carbon::now();
        $table_data['created_by'] = $user_id;
        $where = array(
            'id' => $id
        );
        try {
            if (isset($id) && $id != "") {
                if (recordExists($table_name, $where)) {
                    unset($table_data['created_at']);
                    unset($table_data['created_by']);
                    $table_data['updated_at'] = Carbon::now();
                    $table_data['updated_by'] = $user_id;
                    $previous_data = getPreviousRecords($table_name, $where);
                    //$table_data['payment_schedule']=2;
                    $record_id = $id;
                    if($table_name=="ar_insurance_policies" || $table_name=="stores_insurance_policies" )
                    {
                        unset($table_data['asset_count']);
                    }
                    $success = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                    if ($success) {
                        $res = array(
                            'success' => true,
                            'message' => 'Data updated Successfully!!',
                            'record_id' => $record_id
                        );
                    } else {
                        $res = array(
                            'success' => false,
                            'message' => 'Problem encountered while updating data. Try again later!!'
                        );
                    }
                }
            } else {

                if($table_name=="ar_insurance_policies" || $table_name=="stores_insurance_policies")
                {
                    unset($table_data['asset_count']);
                    $if_duplicate_policy_no=DB::table('ar_insurance_policies')->where('policy_no',$table_data['policy_no'])->value('policy_no');
                    if( (strtolower($if_duplicate_policy_no))!=(strtolower($table_data['policy_no']))){
                    $parent_id= 226347;
                    $parent_id=228976;
                    $folder_id=createAssetDMSParentFolder($parent_id,34 , $table_data['policy_no'], '', $this->dms_id);
                    $table_data['folder_id']=$folder_id;
                    createAssetRegisterDMSModuleFolders($folder_id, 34,36, $this->dms_id);
                    }else{
                        $is_duplicate=true;
                    }
                    
                }
                if($table_name=="ar_funding_details" || $table_name=="stores_funding_details")
                {
                    $if_duplicate_funding_name=DB::table('ar_funding_details')->where('name',$table_data['name'])->value('name');
                    if( (strtolower($if_duplicate_funding_name))==(strtolower($table_data['name']))){
                        $is_duplicate=true;
                    }else{
                    $parent_id= 226347;
                     $parent_id=228976;
                    $folder_id=createAssetDMSParentFolder($parent_id,34, $table_data['name'], '', $this->dms_id);
                    $table_data['folder_id']=$folder_id;
                    createAssetRegisterDMSModuleFolders($folder_id, 34,37, $this->dms_id);
                 }
                   
                    
                }
                $insert_info=array();
                if($is_duplicate==false){
                $insert_info = insertRecord($table_name, $table_data, $user_id);
                }else{
                    $insert_info=[
                        "success"=>false,
                        "message"=>"Similar record found!!"
                    ];
                }

               
                if (!$insert_info['success']) {
                    $res = $insert_info;
                } else {
                    $res = array(
                        'success' => true,
                        'message' => 'Data Saved Successfully!!',
                        'record_id' => $insert_info['record_id']
                    );
                    if($folder_id!="")
                    {
                        $res['folder_id']=$folder_id;
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

    public function saveParam(Request $req)
    {
        $user = new User();
        $modelName = 'App\\Modules\\parameters\\Entities\\' . $req->model;
        $post_data = $req->all();
        unset($post_data['id']);
        unset($post_data['model']);
        $skip = $post_data['skip'];
        unset($post_data['skip']);
        $skipArray = explode(",", $skip);
        $data = encryptArray($post_data, $skipArray);
        $data['created_by'] = $user->getId();
        $data['updated_by'] = $user->getId();
        $param = $modelName::updateOrCreate(
            ['id' => $req->id],
            $data
        );
        if ($param) {
            $res = array(
                'success' => true,
                'message' => 'Details Saved Successfully!!'
            );
        } else {
            $res = array(
                'success' => false,
                'message' => "Problem encountered. Try again later!!"
            );
        }
        return response()->json($res);
    }

    public function getParam($model_name)
    {
        $model = 'App\\Modules\\Parameters\\Entities\\' . $model_name;
        $results = $model::all()->toArray();
        $results = decryptArray($results);
        $res = array(
            'results' => $results
        );
        echo json_output($res);
    }

    public function getCommonParamFromTable(Request $request)
    {
        try {
            $table_name = $request->input('table_name');
            $filters = $request->input('filters');
            $filters = (array)json_decode($filters);
            $qry = DB::table($table_name);
             if (count((array)$filters) > 0) {
                if($table_name=="ar_asset_identifiers")//job on 26/5/2022
                {
                    $qry->whereIn('category_id',[$filters['category_id'],0]);
                }else{
                    $qry->where($filters);
                }
               
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
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getBeneficiarySearchstr()
    {
        $data = array('id' => 1, 'flag' => 'beneficiary_no', 'name' => 'Beneficiary No');
        $data[] = array('id' => 2, 'flag' => 'first_name', 'name' => 'First Name');
        $data[] = array('id' => 3, 'flag' => 'last_name', 'name' => 'Last Name');

        echo json_output(array('results' => $data));
    }

    public function getProvinces(Request $req)
    {
        try {
            $province_id = $req->input('province_id');
            $only_kgs = $req->input('only_kgs');
            $qry = DB::table('provinces')
                ->select(DB::raw("provinces.id,CONCAT_WS('-',provinces.code,provinces.name) as code_name"));
            
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
    
    public function getDistricts(Request $req)
    {
        try {
            $province_id = $req->input('province_id');
            $only_kgs = $req->input('only_kgs');
            $qry = DB::table('districts')
                ->join('provinces', 'districts.province_id', '=', 'provinces.id')
                ->leftJoin('users', 'districts.debs_id', '=', 'users.id')
                ->select(DB::raw("districts.*, provinces.name as province_name, decrypt(users.first_name) as first_name, decrypt(users.last_name) as last_name,
                             decrypt(users.email) as email, decrypt(users.phone) as phone, decrypt(users.mobile) as mobile,CONCAT_WS('-',districts.code,districts.name) as code_name"));
            $qry = $province_id == '' ? $qry->whereRaw(1, 1) : $qry->where('districts.province_id', $province_id);
            if (validateisNumeric($only_kgs) && $only_kgs == 1) {
                $qry->join('kgs_districts as t4', 'districts.id', '=', 't4.district_id');
            }
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

    public function getWards(Request $req)
    {
        try {
            $province_id = $req->input('province_id');
            $district_id = $req->input('district_id');
            $qry = DB::table('wards')
                ->join('constituencies', 'wards.constituency_id', '=', 'constituencies.id')
                ->select(DB::raw("wards.*, constituencies.name as constituency_name,CONCAT_WS('-',wards.code,wards.name) as code_name"));
            if (isset($province_id) && $province_id != '') {
                $qry = $qry->where(array('province_id' => $province_id));
            }
            if (isset($district_id) && $district_id != '') {
                $qry = $qry->where(array('wards.district_id' => $district_id));
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

    public function getpaymentschool_informationstr()
    {
        $results = DB::table('school_information as t1')
            ->select('t1.id', 't1.name')
            ->join('beneficiary_enrollments as t2', 't1.id', '=', 't2.school_id')
            ->groupBy('t1.id')
            ->get();
        $res = array(
            'results' => $results
        );
        return response()->json($res);
    }

    // changed to v2 by Joseph
    public function getSchoolinfoParamV2(Request $req)
    {
        try {
            $filter = $req->input('filter');
            $whereClauses = array();
            $filter_string = '';
            if (isset($filter)) {
                $filters = json_decode($filter);
                if ($filters != NULL) {
                    foreach ($filters as $filter) {
                        switch ($filter->property) {
                            case 'code_name' :
                                $whereClauses[] = "t1.name like '%" . ($filter->value) . "%' OR t1.code like '%" . ($filter->value) . "%'";
                                break;
                        }
                    }
                    $whereClauses = array_filter($whereClauses);
                }
                if (!empty($whereClauses)) {
                    $filter_string = implode(' AND ', $whereClauses);
                }
            }

            $district_id = $req->input('district_id');
            $province_id = $req->input('province_id');
            $limit = $req->input('limit');
            $start = $req->input('start');
            $qry = DB::table('school_information as t1')
                ->join('districts as t3', 't1.district_id', '=', 't3.id')
                ->join('provinces as t4', 't3.province_id', '=', 't4.id')
                ->leftJoin('school_types as t5', 't1.school_type_id', '=', 't5.id')
                ->leftJoin('school_types_categories as t6', 't1.facility_type_id', '=', 't6.id')
                ->leftJoin('school_bankinformation as t2', 't1.id', '=', 't2.school_id')
                ->leftJoin('school_contactpersons', function ($join) {//get head teacher details
                    $join->on('t1.id', '=', 'school_contactpersons.school_id')
                        ->where('school_contactpersons.designation_id', '=', DB::raw(1));
                })
                ->select(DB::raw("school_contactpersons.full_names, school_contactpersons.telephone_no as head_telephone, school_contactpersons.mobile_no as head_mobile,
                school_contactpersons.email_address as head_email, t5.name as school_type, t4.name as province_name, t1.*, t2.bank_id, t2.branch_name, t2.account_no,
                t2.sort_code, t1.id as school_id, t3.name as district_name, CONCAT_WS('-',t1.code,t1.name) as code_name,t1.code,t6.name as facility_type"));
            if (isset($district_id) && $district_id != '') {
                $qry = $qry->where('t1.district_id', $district_id);
            }
            if (isset($province_id) && $province_id != '') {
                $qry = $qry->where('t1.province_id', $province_id);
            }
            if ($filter_string != '') {
                $qry->whereRAW($filter_string);
            }
            $total = $qry->count();
            // $qry->offset($start)->limit($limit);
            $qry->offset(0)->limit(5000);
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

    public function getJustSchools(Request $req)
    {
        try {
            $district_id = $req->input('district_id');
            $qry = DB::table('school_information as t1')
                ->select(DB::raw("t1.*,CONCAT_WS(' ',t1.code,t1.name) as codename"));
            if (isset($district_id) && $district_id != '') {
                $qry->where('district_id', $district_id);
            }
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

    public function getSchoolsAndRelated(Request $req)
    {
        try {
            $district_id = $req->input('district_id');
            $filter = $req->input('filter');
            $whereClauses = array();
            $filter_string = '';
            if (isset($filter)) {
                $filters = json_decode($filter);
                if ($filters != NULL) {
                    foreach ($filters as $filter) {
                        switch ($filter->property) {
                            case 'district' :
                                $whereClauses[] = "t1.district_id = '" . ($filter->value) . "'";
                                break;
                            case 'codename' :
                                $whereClauses[] = "t1.id = '" . ($filter->value) . "'";
                                break;
                        }
                    }
                    $whereClauses = array_filter($whereClauses);
                }
                if (!empty($whereClauses)) {
                    $filter_string = implode(' AND ', $whereClauses);
                }
            }
            $qry = DB::table('school_information as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->select(DB::raw("t1.*,CONCAT_WS(' ',t1.code,t1.name) as codename,t2.name as district"));
            if (isset($district_id) && $district_id != '') {
                $qry->where('district_id', $district_id);
            }
            if ($filter_string != '') {
                $qry->whereRAW($filter_string);
            }
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

    public function getJustBeneficiarySchools(Request $req)
    {
        try {
            $qry = DB::table('school_information as t1')
                ->join('beneficiary_information as t2', 't1.id', '=', 't2.school_id')
                ->select(DB::raw("t1.id,CONCAT_WS(' ',t1.code,t1.name) as codename,t1.code,t1.name"));
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

    public function getSchoolsWithFeesDisbursement(Request $req)
    {
        $payment_year = $req->input('year');
        $payment_term = $req->input('term');
        try {
            $qry = DB::table('school_information as t1')
                ->join('payment_disbursement_details as t2', 't1.id', '=', 't2.school_id');
            if ((isset($payment_year) && $payment_year != '') || (isset($payment_term) && $payment_term != '')) {
                $qry->join('payment_request_details as t3', 't2.payment_request_id', '=', 't3.id');
                if ($payment_year != '') {
                    $qry->where('t3.payment_year', $payment_year);
                }
                if ($payment_term != '') {
                    $qry->where('t3.term_id', $payment_term);
                }
            }
            $qry->select(DB::raw("t1.id,CONCAT_WS(' ',t1.code,t1.name) as codename, t1.name, t1.code"))
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

    public function getSchoolsWithPayVerBatches(Request $req)
    {
        $district_id = $req->input('district_id');
        $province_id = $req->input('province_id');
        $year = $req->input('year');
        $term = $req->input('term');
        try {
            $qry = DB::table('school_information as t1')
                ->join('payment_verificationbatch as t2', 't1.id', '=', 't2.school_id')
                ->select(DB::raw("t1.id,t1.name,t1.code, CONCAT_WS(' ',t1.code,t1.name) as codename"));
            if (isset($district_id) && $district_id != '') {
                $qry->where('t1.district_id', $district_id);
            }
            if (isset($province_id) && $province_id != '') {
                $qry->where('t1.province_id', $province_id);
            }
            if (isset($year) && $year != '') {
                $qry->where('t2.year_of_enrollment', $year);
            }
            if (isset($term) && $term != '') {
                $qry->where('t2.term_id', $term);
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
                'success' => true,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getAccCwac(Request $req)
    {
        try {
            $table_name = $req->input('table_name');
            $province_id = $req->input('province_id');
            $district_id = $req->input('district_id');
            $ward_id = $req->input('ward_id');
            $qry = DB::table($table_name)
                ->leftJoin('wards', $table_name . '.ward_id', '=', 'wards.id')
                ->leftJoin('districts', $table_name . '.district_id', '=', 'districts.id')
                ->leftJoin('provinces', $table_name . '.province_id', '=', 'provinces.id')
                ->leftJoin('users', $table_name . '.contact_person_id', '=', 'users.id')
                ->select(DB::raw("$table_name.*, users.first_name,users.last_name,users.phone,wards.name as ward_name,districts.name as district_name,provinces.name as province_name,
                                     CONCAT_WS('-',$table_name.code,$table_name.name) as code_name"));
            if (isset($province_id) && $province_id != '') {
                $qry = $qry->where(array($table_name . '.province_id' => $province_id));
            }
            if (isset($district_id) && $district_id != '') {
                $qry = $qry->where(array($table_name . '.district_id' => $district_id));
            }
            if (isset($ward_id) && $ward_id != '') {
                $qry = $qry->where(array($table_name . '.ward_id' => $ward_id));
            }
            $data = $qry->limit(5000)->get();
            $res = array(
                'success' => true,
                'message' => returnMessage($data),
                'results' => $data
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

    public function getConstituencies(Request $req)
    {
        try {
            $district_id = $req->input('district_id');
            $qry = DB::table('constituencies')
                ->join('districts', 'constituencies.district_id', '=', 'districts.id')
                ->select(DB::raw("constituencies.*, districts.name as district_name,CONCAT_WS('-',constituencies.code,constituencies.name) as code_name"));
            $qry = $district_id == '' ? $qry->whereRaw(1, 1) : $qry->where('constituencies.district_id', $district_id);
            $results = $qry->limit(5000)->get();
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

    public function getHouseholds(Request $req)
    {
        try {
            $table_name = $req->input('table_name');
            $cwac_id = $req->input('cwac_id');
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
                            case 'hhh_nrc_number' :
                                $whereClauses[] = "t1.hhh_nrc_number like '%" . ($filter->value) . "%'";
                                break;
                            case 'hhh_fname' :
                                $whereClauses[] = "decrypt(t1.hhh_fname) like '%" . ($filter->value) . "%'";
                                break;
                            case 'hhh_lname' :
                                $whereClauses[] = "decrypt(t1.hhh_lname) like '%" . ($filter->value) . "%'";
                                break;
                            case 'number_in_cwac' :
                                $whereClauses[] = "number_in_cwac like '%" . ($filter->value) . "%'";
                                break;
                        }
                    }
                    $whereClauses = array_filter($whereClauses);
                }
                if (!empty($whereClauses)) {
                    $filter_string = implode(' AND ', $whereClauses);
                }
            }

            $qry = DB::table($table_name . ' as t1')
                ->leftJoin('cwac as t2', 't1.cwac_id', '=', 't2.id')
                ->select(DB::raw("t1.*, t1.id as household_id, t2.name as cwac_name"));
            if (isset($cwac_id) && $cwac_id != '') {
                $qry = $qry->where(array($table_name . '.cwac_id' => $cwac_id));
            }
            if ($filter_string != '') {
                $qry->whereRAW($filter_string);
            }
            $total = $qry->count();
            $qry->offset($start)->limit($limit);
            $data = $qry->get();
            $res = array(
                'results' => $data,
                'total' => $total
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

    public function deleteParamRecord(Request $req)
    {
        $record_id = $req->id;
        $table_name = $req->table_name;
        $user_id = Auth::user()->id;
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

    //hiram code
    public function getSchoolinfoParam1()
    {
        $qry = DB::table('school_information')
            ->leftJoin('school_types', 'school_information.school_type_id', '=', 'school_types.id')
            ->join('districts', 'school_information.district_id', '=', 'districts.id')
            ->leftJoin('bank_details', 'school_information.bank_id', '=', 'bank_details.id')
            ->select('school_information.*', 'districts.name as district_name', 'provinces.name as province_name', 'school_types.name as school_type', 'bank_details.name as bank_name ');
        getParamsdata($qry);
    }

    public function getSchooltypesParam()
    {

        $qry = DB::table('school_types');
        // $data = $qry->get();
        getParamsdata($qry);

    }

    public function getpayment_verificationstatus()
    {

        $qry = DB::table('payment_verification_statuses');
        //  $data = $qry->get();
        getParamsdata($qry);

    }

    public function getBank_detailParams()
    {

        $qry = DB::table('bank_details');
        // $data = $qry->get();
        getParamsdata($qry);

    }

    public function getBankbranch_detailParams(Request $req)
    {
        $bank_id = $req->input('bank_id');
        $qry = DB::table('bank_branches as t1')
            ->select('t1.*', 't2.name as bank_name')
            ->join('bank_details as t2', 't1.bank_id', '=', 't2.id');
        if (validateisNumeric($bank_id)) {
            $qry = $qry->where(array('bank_id' => $bank_id));
        }
        getParamsdata($qry);
    }

    public function getSchool_termsParam()
    {

        $qry = DB::table('school_terms');
        // $data = $qry->get();
        getParamsdata($qry);

    }

    public function getSchoolgradeParam()
    {

        $qry = DB::table('school_grades');
        //$data = $qry->get();
        getParamsdata($qry);

    }

    public function getbeneficiary_enrollementstatusstr()
    {
        $qry = DB::table('beneficiary_enrollement_statuses');
        //$data = $qry->get();
        getParamsdata($qry);
    }

    public function getSchool_designation()
    {
        $qry = DB::table('school_designation');
        //  $data = $qry->get();
        getParamsdata($qry);
    }

    public function getPayment_validation_rules()
    {
        $qry = DB::table('payment_validation_rules');
        //$data = $qry->get();
        getParamsdata($qry);
    }

    public function getSchool_termDaysParam()
    {
        $qry = DB::table('school_term_days as t1')
            ->select('t1.*', 't2.name as school_terms')
            ->join('school_terms as t2', 't1.term_id', '=', 't2.id');
        //  $data = $qry->get();
        getParamsdata($qry);
    }

    public function getSchool_typeenrollment_setup()
    {
        $qry = DB::table('school_typeenrollment_setup as t1')
            ->select(DB::raw('t1.*, t2.name as school_type, t3.name as school_enrollment'))
            ->join('school_types as t2', 't1.school_type_id', '=', 't2.id')
            ->join('beneficiary_school_statuses as t3', 't1.school_enrollment_id', '=', 't3.id');
        //  $data = $qry->get();
        getParamsdata($qry);
    }

    public function getSchool_contactpersonParams(Request $req)
    {
        $school_id = $req->school_id;
        $qry = DB::table('school_contactpersons')
            ->join('school_designation', 'school_contactpersons.designation_id', '=', 'school_designation.id')
            ->select('school_contactpersons.*', 'school_designation.name as designation')
            ->where(array('school_id' => $school_id));
        getParamsdata($qry);
    }

    public function saveSchool_feesdata(Request $req)
    {
        //save the data

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
            $table_name = 'school_feessetup';
            $grade_id = $value['grade_id'];

            $term_id = $value['term_id'];
            $school_id = $value['school_id'];
            $fees_amount = $value['fees_amount'];
            $table_data = 'school_feessetup';
            $user_id = \Auth::user()->id;
            $table_data = array('school_id' => $school_id, 'grade_id' => $grade_id, 'term_id' => $term_id);
            $where = array('school_id' => $school_id, 'grade_id' => $grade_id, 'term_id' => $term_id);
            $qry = DB::table($table_name)
                ->where($where)
                ->get();
            $table_data['fees_amount'] = $fees_amount;
            if (count($qry) > 0) {

                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $previous_data = getPreviousRecords($table_name, $where);
                $success = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);


            } else {
                $table_data['created_at'] = Carbon::now();
                $table_data['created_by'] = $user_id;

                $success = insertRecord($table_name, $table_data, $user_id);

            }
        }

    }

    public function getSchool_feesdata(Request $req)
    {
        ///get the
        $school_id = $req->school_id;
        $qry = DB::table('school_information')
            ->where(array('id' => $school_id))
            ->get();
        $data = array();

        foreach ($qry as $row_school) {
            //get the graders
            $qry = DB::table('school_grades')
                ->whereIn('id', array(8, 9, 10, 11, 12))
                ->get();
            foreach ($qry as $row_grade) {
                $qry = DB::table('school_terms')
                    ->get();
                foreach ($qry as $row_term) {
                    $data[] = array('school_id' => $row_school->id,
                        'school_name' => aes_decrypt($row_school->name),
                        'school_grade' => aes_decrypt($row_grade->name),
                        'school_term' => aes_decrypt($row_term->name),
                        'grade_id' => $row_grade->id,
                        'term_id' => $row_term->id,
                        'fees_amount' => ''// getSchoolfees($row_school->id, $row_grade->id, $row_term->id)

                    );

                }
            }


        }
        json_output(array('results' => $data));

    }

    public function getSchool_feessetup()
    {
        ///get the
        $qry = DB::table('school_information as t1')
            ->select('t1.id as school_id', 't2.school_enrollment_id', 't1.name as school_name', 't3.name as school_enrollment')
            ->join('school_typeenrollment_setup as t2', 't1.school_type_id', '=', 't2.school_type_id')
            ->join('beneficiary_school_statuses as t3', 't2.school_enrollment_id', '=', 't3.id')
            ->limit(200)
            ->get();

        $results = convertStdClassObjToArray($qry);
        $results = decryptArray($results);
        $data = array();//enrollment_type
        $dataset = array();

        if (count($results) > 0) {
            foreach ($results as $rec) {

                $school_id = $rec['school_id'];
                $school_name = $rec['school_name'];
                $school_enrollment = $rec['school_enrollment'];
                $school_enrollment_id = $rec['school_enrollment_id'];
                $year = 2018;
                $data[] = array('school_id' => $school_id,
                    'school_name' => $school_name,
                    'school_enrollment' => $school_enrollment,
                    'grade8_term1' => getSchoolfees($school_id, $school_enrollment_id, 8, 1, $year),
                    'grade8_term2' => getSchoolfees($school_id, $school_enrollment_id, 8, 2, $year),
                    'grade8_term3' => getSchoolfees($school_id, $school_enrollment_id, 8, 3, $year),
                    'grade9_term1' => getSchoolfees($school_id, $school_enrollment_id, 9, 1, $year),
                    'grade9_term2' => getSchoolfees($school_id, $school_enrollment_id, 9, 2, $year),
                    'grade9_term3' => getSchoolfees($school_id, $school_enrollment_id, 9, 3, $year),
                    'grade10_term1' => getSchoolfees($school_id, $school_enrollment_id, 10, 1, $year),
                    'grade10_term2' => getSchoolfees($school_id, $school_enrollment_id, 10, 2, $year),
                    'grade10_term3' => getSchoolfees($school_id, $school_enrollment_id, 10, 3, $year),
                    'grade11_term1' => getSchoolfees($school_id, $school_enrollment_id, 11, 1, $year),
                    'grade11_term2' => getSchoolfees($school_id, $school_enrollment_id, 11, 2, $year),
                    'grade11_term3' => getSchoolfees($school_id, $school_enrollment_id, 11, 3, $year),
                    'grade12_term1' => getSchoolfees($school_id, $school_enrollment_id, 12, 1, $year),
                    'grade12_term2' => getSchoolfees($school_id, $school_enrollment_id, 12, 2, $year),
                    'grade12_term3' => getSchoolfees($school_id, $school_enrollment_id, 12, 3, $year)
                );

            }
        }

        json_output(array('results' => $data));
    }

    public function saveschoolBankdetails(Request $req)
    {
        $user_id = \Auth::user()->id;
        $school_id = $req->school_id;
        $bank_id = $req->bank_id;
        $account_no = $req->account_no;
        $sort_code = $req->sort_code;
        $branch_name = $req->branch_name;
        $table_name = 'school_bankinformation';
        $table_data = array('school_id' => $school_id,
            'bank_id' => $bank_id,
            'account_no' => $account_no,
            'sort_code' => $sort_code,
            'branch_name' => $branch_name

        );
        $skipArray = array('school_id' => $school_id,
            'bank_id' => $bank_id
        );
        //commented out  $table_data = encryptArray($table_data, $skipArray);
        $table_data['school_id'] = $school_id;
        $table_data['bank_id'] = $bank_id;

        $where = array('school_id' => $school_id);

        $qry = DB::table($table_name)
            ->where(array('school_id' => $school_id))
            ->count();
        if ($qry > 0) {

            $table_data['updated_at'] = Carbon::now();
            $table_data['updated_by'] = $user_id;
            $previous_data = getPreviousRecords($table_name, $where);
            $success = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);

        } else {
            $table_data['created_at'] = Carbon::now();
            $table_data['created_by'] = $user_id;

            $success = insertRecord($table_name, $table_data, $user_id);

        }
        if ($success) {

            $resp = array('success' => true, 'message' => 'School Bank Details saved successfully');

        } else {
            $resp = array('success' => false, 'message' => 'Error occured, details not saved');


        }
        json_output($resp);

    }

    //end hirams code

    public function getKgsDistricts(Request $req)
    {
        try {
            $qry = DB::table('kgs_districts')
                ->join('districts', 'kgs_districts.district_id', '=', 'districts.id')
                ->leftJoin('users', 'districts.debs_id', '=', 'users.id')
                ->leftJoin('provinces', 'districts.province_id', '=', 'provinces.id')
                ->leftJoin('beneficiary_information as t5', function ($join) {
                    $join->on('t5.district_id', '=', 'districts.id')
                        ->on('t5.beneficiary_status', '=', DB::raw(4))
                        ->on('t5.enrollment_status', '=', DB::raw(1));
                })
                ->select(DB::raw('decrypt(users.first_name) as first_name, decrypt(users.last_name) as last_name,
                                  decrypt(users.email) as email, decrypt(users.phone) as phone, decrypt(users.mobile) as mobile,
                                  COUNT(DISTINCT(t5.id)) as no_of_beneficiaries,districts.name,kgs_districts.id,provinces.name as province_name,
                                  kgs_districts.district_id,districts.code,districts.province_id,kgs_districts.kgs_code'))
                ->groupBy('districts.id');
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

    public function addSchoolTerm(Request $req)
    {
        $is_active = $req->input('is_active');
        $user_id = \Auth::user()->id;
        $post_data = $req->all();
        $table_name = $post_data['table_name'];
        $id = $post_data['id'];
        $skip = $post_data['skip'];

        //unset unnecessary values
        unset($post_data['_token']);
        unset($post_data['table_name']);
        unset($post_data['model']);
        unset($post_data['id']);
        unset($post_data['skip']);

        $table_data = $post_data;
        //add extra params
        $table_data['created_at'] = Carbon::now();
        $table_data['created_by'] = $user_id;
        $where = array(
            'id' => $id
        );
        try {
            if (isset($id) && $id != "") {
                if (recordExists($table_name, $where)) {
                    unset($table_data['created_at']);
                    unset($table_data['created_by']);
                    $table_data['updated_at'] = Carbon::now();
                    $table_data['updated_by'] = $user_id;
                    $previous_data = getPreviousRecords($table_name, $where);
                    $record_id = $id;
                    $success = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                    if ($success) {
                        $res = array(
                            'success' => true,
                            'message' => 'Data updated Successfully!!',
                            'record_id' => $record_id
                        );
                    } else {
                        $res = array(
                            'success' => false,
                            'message' => 'Problem encountered while updating data. Try again later!!'
                        );
                    }
                }
            } else {
                $record_id = insertRecordReturnId($table_name, $table_data, $user_id);
                if (validateisNumeric($record_id)) {
                    $res = array(
                        'success' => true,
                        'message' => 'Data Saved Successfully!!',
                        'record_id' => $record_id
                    );
                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'Problem encountered while saving details!!'
                    );
                }
            }
            if ($is_active == 1) {
                DB::table($table_name)
                    ->where('id', '<>', $record_id)
                    ->update(array('is_active' => 0));
            }
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getUserAssignedDistricts()
    {
        // Check if user is authenticated first
        // if (!\Auth::check()) {
        //     return response()->json(['error' => 'User not authenticated'], 401);
        // }
        $user_id = \Auth::user()->id;
        try {
            $groups = getUserGroups($user_id);
            $superUserID = getSuperUserGroupId();
            $districts = getUserDistricts($user_id);
            $qry = DB::table('districts');
            $qry = in_array($superUserID, $groups) == 1 ? $qry->whereRaw("1=1") : $qry->whereIn('id', $districts);
            $results = $qry->orderBy('name', 'asc')->get();
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

    public function getSchoolBankInformation(Request $req)
    {
        $school_id = $req->input('school_id');
        $bank_id = $req->input('bank_id');
        try {
            $qry = DB::table('school_bankinformation as t1')
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                ->join('bank_details as t3', 't1.bank_id', '=', 't3.id')
                ->leftJoin('bank_branches as t4', 't1.branch_name', '=', 't4.id')
                ->select(DB::raw('t1.bank_id,t1.branch_name,t1.id,decrypt(t1.account_no) as account_no,t2.code as school_code,t2.name as school_name,
                                  t1.school_id,t1.is_activeaccount,t3.name as bank_name,t4.name as branch_name_name,t4.sort_code'));
            if (isset($school_id) && $school_id != '') {
                $qry->where('t1.school_id', $school_id);
            }
            if (isset($bank_id) && $bank_id != '') {
                $qry->where('t1.bank_id', $bank_id);
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

    public function saveSchoolBankInformation(Request $req)
    {
        $is_active = $req->input('is_activeaccount');
        $user_id = \Auth::user()->id;
        $post_data = $req->all();
        $table_name = $post_data['table_name'];
        $id = $post_data['id'];
        $branch_id = $post_data['branch_name'];
        $sort_code = $post_data['sort_code'];
        $school_id = $post_data['school_id'];
        $account_no = $post_data['account_no'];
        //unset unnecessary values
        unset($post_data['_token']);
        unset($post_data['table_name']);
        unset($post_data['model']);
        unset($post_data['id']);
        unset($post_data['skip']);
        unset($post_data['account_no']);

        $table_data = $post_data;
        //add extra params
        $table_data['account_no'] = aes_encryptAll($account_no);
        $table_data['created_at'] = Carbon::now();
        $table_data['created_by'] = $user_id;
        $where = array(
            'id' => $id
        );
        try {
            if (isset($id) && $id != "") {
                if (recordExists($table_name, $where)) {
                    unset($table_data['created_at']);
                    unset($table_data['created_by']);
                    $table_data['updated_at'] = Carbon::now();
                    $table_data['updated_by'] = $user_id;
                    $previous_data = getPreviousRecords($table_name, $where);
                    $record_id = $id;
                    $success = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                    if ($success) {
                        $res = array(
                            'success' => true,
                            'message' => 'Data updated Successfully!!',
                            'record_id' => $record_id
                        );
                    } else {
                        $res = array(
                            'success' => false,
                            'message' => 'Problem encountered while updating data. Try again later!!'
                        );
                    }
                }
            } else {
                $record_id = insertRecordReturnId($table_name, $table_data, $user_id);
                if (validateisNumeric($record_id)) {
                    $res = array(
                        'success' => true,
                        'message' => 'Data Saved Successfully!!',
                        'record_id' => $record_id
                    );
                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'Problem encountered while saving details!!'
                    );
                }
            }
            DB::table('bank_branches')
                ->where('id', $branch_id)
                ->update(array('sort_code' => $sort_code));
            if ($is_active == 1) {
                DB::table($table_name)
                    ->where('id', '<>', $record_id)
                    ->where('school_id', $school_id)
                    ->update(array('is_activeaccount' => 0));
            }
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getSystemUsers(Request $request)
    {
        try {
            $group_id = $request->input('group_id');
            $qry = DB::table('users')
                ->leftJoin('user_images', 'users.id', '=', 'user_images.user_id')
                ->join('access_points', 'users.access_point_id', '=', 'access_points.id')
                ->leftJoin('user_roles', 'users.user_role_id', '=', 'user_roles.id')
                ->join('gender', 'users.gender_id', '=', 'gender.id')
                ->join('titles', 'users.title_id', '=', 'titles.id')
                ->leftJoin('grm_gewel_programmes as t7', 'users.gewel_programme_id', '=', 't7.id')
                ->leftJoin('menus as t8', 'users.dashboard_id', '=', 't8.id')
                ->select(DB::raw("users.id, users.first_name, users.last_name, users.last_login_time, users.title_id, users.email, decrypt(users.phone) as phone, decrypt(users.mobile) as mobile,users.gewel_programme_id,users.nongewel_programme_id,
                                        users.gender_id, users.access_point_id, users.user_role_id, gender.name as gender_name, titles.name as title_name, user_images.saved_name,users.dashboard_id,
                                        access_points.name as access_point_name, user_roles.name as user_role_name, CONCAT_WS(' ',decrypt(users.first_name),decrypt(users.last_name)) as names,
                                        t7.name as gewel_programme,t8.dashboard_name,users.is_coordinator"))
                ->whereNotIn('users.id', function ($query) {
                    $query->select(DB::raw('blocked_accounts.account_id'))
                        ->from('blocked_accounts');
                });
            if (isset($group_id) && $group_id != '') {
                $users = DB::table('user_group')
                    ->select('user_id')
                    ->where('group_id', $group_id)
                    ->get();
                $users = self::convertStdClassObjToArray($users);
                $users = self::convertAssArrayToSimpleArray($users, 'user_id');
                $qry->whereIn('users.id', $users);
            }
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $data = decryptArray($data);
            $res = array(
                'success' => true,
                'message' => returnMessage($data),
                'users' => $data,
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
    
    public function getBeneficiaries(Request $req)
    {
        try {
            $start = $req->input('start');
            $limit = $req->input('limit');
            $school_prov = $req->input('school_prov');
            $home_prov = $req->input('home_prov');
            $girl_id = $req->input('girl_id');
            $kgs_status = $req->input('kgs_status');
            $school_status = $req->input('school_status');
            $takeup_status = $req->input('takeup_status');
            $grades = $req->input('grades');
            $unresponsive = $req->input('unresponsive');
            $beneficiary_status = 4;
            $recommendation = 1;
            $filter = $req->input('filter');
            $gradesIn = array();
            if (isset($grades) && $grades != '') {
                $gradesIn = json_decode($grades);
            }
            $qry = DB::table('beneficiary_information as t1')
                ->join('beneficiary_statuses', 't1.beneficiary_status', '=', 'beneficiary_statuses.id')
                ->leftJoin('beneficiary_school_statuses', 't1.beneficiary_school_status', '=', 'beneficiary_school_statuses.id')
                ->leftJoin('beneficiary_enrollement_statuses', 't1.enrollment_status', '=', 'beneficiary_enrollement_statuses.id')
                ->leftJoin('beneficiary_images', 't1.id', '=', 'beneficiary_images.girl_id')
                ->leftJoin('households', 't1.household_id', '=', 'households.id')
                ->leftJoin('school_information', 't1.school_id', '=', 'school_information.id')
                ->leftJoin('districts as d1', 'school_information.district_id', '=', 'd1.id')
                ->join('districts', 't1.district_id', '=', 'districts.id')
                ->leftJoin('cwac', 't1.cwac_id', '=', 'cwac.id')
                ->join('batch_info', 't1.batch_id', '=', 'batch_info.id')
                ->leftJoin('school_enrollment_statuses as t12', 't1.school_enrollment_status', '=', 't12.id')
                ->join('beneficiary_school_statuses as t13', 't1.beneficiary_school_status', '=', 't13.id')
                ->join('beneficiary_categories as t14', 't1.category', '=', 't14.id')
                ->leftJoin('school_types as t15', 'school_information.school_type_id', '=', 't15.id')
                ->select(DB::raw("t1.id,t1.id as girl_id,t1.beneficiary_id,t1.household_id,t1.exam_school_id,t1.school_id,t1.cwac_id,t1.acc_id,t1.ward_id,t1.constituency_id,t1.district_id,t1.province_id,
                              CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.last_name)) as beneficiary_name,decrypt(t1.first_name) as first_name, decrypt(t1.last_name) as last_name,t1.dob,t1.verified_dob,t1.highest_grade,t1.exam_grade,t1.current_school_grade,t1.category,t1.master_id,
                              t1.beneficiary_status,t1.enrollment_status,t1.beneficiary_school_status,t1.exam_number,t1.enrollment_date,t1.batch_id,t1.folder_id,t1.letter_printed as letterPrinted,t1.relation_to_hhh,t1.cwac_txt,
                              batch_info.batch_no,d1.name as school_district,cwac.name as cwac_name,beneficiary_school_statuses.name as ben_school_status,beneficiary_enrollement_statuses.name as enrollment_status_name,
                              beneficiary_images.saved_name,beneficiary_statuses.name as status_name,school_information.name as school,households.id as hh_id,CONCAT_WS(' ',households.hhh_fname,households.hhh_lname) as hhh_name,households.hhh_fname,households.hhh_lname,households.hhh_nrc_number,
                              households.number_in_cwac,districts.name as district_name,t1.kgs_takeup_status,t1.school_enrollment_status,
                              t12.name as schenrollment_status,t13.name as sch_status,t14.name as category_name,t15.name as school_type,school_information.code as sch_code"))
                ->where('t1.beneficiary_status', $beneficiary_status)
                ->where('t1.verification_recommendation', $recommendation);
            if (isset($unresponsive) && $unresponsive != '') {
                if ($unresponsive == 1) {
                    $qry->join('unresponsive_cohorts', 'unresponsive_cohorts.girl_id', '=', 't1.id')
                        ->where('unresponsive_cohorts.matched', '<>', 1);
                }
            }
            if (count($gradesIn) > 0 && $gradesIn != '') {
                $qry->whereIn('current_school_grade', $gradesIn);
            }
            if (isset($school_prov) && $school_prov != '') {
                $qry->where('school_information.province_id', $school_prov);
            }
            if (isset($home_prov) && $home_prov != '') {
                $qry->where('t1.province_id', $home_prov);
            }
            if (isset($kgs_status) && $kgs_status != '') {
                $qry->where('t1.enrollment_status', $kgs_status);
            }
            if (isset($school_status) && $school_status != '') {
                $qry->where('t1.school_enrollment_status', $school_status);
            }
            if (isset($takeup_status) && $takeup_status != '') {
                $qry->where('t1.kgs_takeup_status', $takeup_status);
            }
            if (validateisNumeric($girl_id)) {
                $qry->where('t1.id', $girl_id);
            }
            if (isset($filter)) {
                $filters = json_decode($filter);
                $filter_string = $this->buildBeneficiarySearchQuery($filters);
                if ($filter_string != '') {
                    $qry->whereRAW($filter_string);
                }
            }
            $total = $qry->count();
            if (validateisNumeric($limit)) {
                $qry->offset($start)
                    ->limit($limit);
            }
            $qry->offset(0)->limit(15000);
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
                'message' => $e->getMessage(),
                'results' => ''
            );
        }
        return response()->json($res);
    }
    
    
    public function getCwacID($cwac_code)
    {
        $codeArray = explode('-', $cwac_code);
        $main_cwac_code = current($codeArray);
        if (is_null($cwac_code) || $main_cwac_code == 0) {
            return false;
        }
        $qry = DB::table('cwac')
            ->where('code', $main_cwac_code);
        $data = $qry->first();
        if (is_null($data)) {
            return false;
        } else {
            return $data->id;
        }
    }

    public function getAccID($acc_code)
    {
        $codeArray = explode('-', $acc_code);
        $main_acc_code = current($codeArray);
        if (is_null($acc_code) || $main_acc_code == 0) {
            return false;
        }
        $qry = DB::table('acc')
            ->where('code', $main_acc_code);
        $data = $qry->first();
        if (is_null($data)) {
            return false;
        } else {
            return $data->id;
        }
    }

    // updated by Joseph to v1
    public function getBeneficiariesForMobileV1(Request $req)
    {
        try {
            $start = 0;
            $limit = 10000;
            $beneficiary_status = 4;
            $recommendation = 1;
            $qry = DB::table('beneficiary_information as t1')
                ->select("t1.*")
                ->offset($start)->limit($limit);
            $total = $limit - $start;
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
                'message' => $e->getMessage(),
                'results' => ''
            );
        }
        return response()->json($res);
    }

    public function runPaymentCronJobs(Request $req)
    {
        try {
            $saved_batches = DB::table('beneficiary_enrollments_execdelay as t1')
                ->select('t1.*')
                ->where('t1.execution_status', 0)
                ->groupBy('t1.batch_id')->limit(1)->get();
            
            foreach ($saved_batches as $key => $value) {
                dd($value['batch_id']);
                //todo log errors
                self::validateBeneficiaryEnrollment2($value['batch_id']);
                //update school enrollment status
                // self::updateSchoolEnrollmentStatus($value['batch_id']);
                //update school fees
                // self::AutoUpdateEnrollmentSchoolFee($value['batch_id']);
                // DB::table('beneficiary_enrollments_execdelay as t1')
                //     ->where('t1.batch_id', $value['batch_id'])
                //     ->update(array('t1.execution_status' => 1));
            }

            $res = array(
                'success' => true,
                'results' => $data,
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


    public function getMobileParamsOldVersion(Request $req)
    {
        $request_item = $req->input('request_item');
        // $unique_id = $req->input('unique_id');
        try {
            if ($request_item == 'provinces') {
                $results = self::getProvinces($req);
                $res = $results->original;
            } else if ($request_item == 'districts') {
                $results = self::getDistricts($req);
                $res = $results->original;
            } else if ($request_item == 'acc') {
                $results = self::getAccCwac($req);
                $res = $results->original;
            } else if ($request_item == 'schools') {
                $results = self::getSchoolinfoParam($req);
                $res = $results->original;
            } else if ($request_item == 'constituencies') {
                $results = self::getConstituencies($req);
                $res = $results->original;
            } else if ($request_item == 'beneficiaries') {
                // $results = self::getBeneficiaries($req);
                $results = self::getBeneficiariesForMobile($req);                
                $res = $results->original;
            } else if ($request_item == 'users') {
                $results = self::getSystemUsers($req);
                $res = $results->original;
            } else if ($request_item == 'getgraphdetails') {
                $results = self::getBeneficiaryEnrollmentsRpt($req);
                $res = $results->original;
            } else if ($request_item == 'sendemailnotice') {
                $results = self::sendEmailNotice($req);
                $res = $results->original;
            } else if ($request_item == 'payment_batches') {
                $results = self::runPaymentCronJobs($req);
                $res = $results->original;
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'A unique Id and Request Item must be included in your payload'
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

    public function sendEmailNotice(Request $request)
    {
        try {
            $priProvEmails = '';
            $ccProvEmails = '';        
            $results = DB::select(
                "SELECT t1.id,t1.complaint_form_no,t3.primary_email,
                t3.cc_email,t4.name,t1.complaint_form_no
                FROM grm_complaint_details t1
                INNER JOIN grm_workflow_stages t2 ON t1.workflow_stage_id = t2.id
                INNER JOIN grm_emailnotifications_setup t3 ON t1.province_id = t3.province_id
                LEFT JOIN grm_gewel_programmes t4 ON t1.programme_type_id = t4.id
                LEFT JOIN batch_emailsent_log t5 ON t5.complaint_form_no = t1.complaint_form_no
                WHERE t1.record_status_id = 1 AND t5.complaint_form_no IS NULL 
                GROUP BY t1.id
            ");
            $submitted_count = 0;
            $res = array();
            foreach ($results as $key => $single_result) {
                // dd($single_result);
                $priProvEmails = null;
                $ccProvEmails = null;
                $priProvEmailsArr = null;
                $ccProvEmailsArr = null;
                if ($single_result) {
                    $priProvEmails = $single_result->primary_email;
                    $ccProvEmails = $single_result->cc_email;
                }
                $priProvEmailsArr = explode(',', $priProvEmails);
                $ccProvEmailsArr = explode(',', $ccProvEmails);
                $log_array = array(
                    "complaint_form_no" => $single_result->complaint_form_no,
                    "primary_email" => $single_result->primary_email,
                    "cc_email" => $single_result->cc_email,
                    "programme_name" => $single_result->name
                );
                if (is_connected()) {
                    $vars = array(
                        '{complaint_refno}' => $single_result->complaint_form_no,
                        '{prog_name}' => $single_result->name
                    );
                    $emailTemplateInfo = getEmailTemplateInfo(8, $vars);
                    $emailJob = (new GenericSendEmailJob($priProvEmailsArr, 
                        $emailTemplateInfo->subject, $emailTemplateInfo->body, 
                        $ccProvEmailsArr))->delay(Carbon::now()->addSeconds(10));
                    dispatch($emailJob);
                    $log_array['is_sent'] = 1;
                } else {
                    $log_array['is_sent'] = 0;
                }
                DB::table('batch_emailsent_log')->insert($log_array);
            }
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'results' => array(),
                'message' => $e->getMessage(),
            );
        }
        return response()->json($res);
    }
    public function sendEmailNoticeInit(Request $request)
    {
        if (is_connected()) {
            $vars = array(
                '{lodgedBy}' => 'Frank Otieno',
                '{complaintFormNo}' => '123456789',
                '{collectionDate}' => '07/11/2024',
                '{complainantName}' => 'Frank Otieno',
                '{complainantNRC}' => '31179197',
                '{complainantMobile}' => '0729570880',
                '{provinceName}' => 'Lusaka',
                '{districtName}' => 'Lusaka',
                '{cwacName}' => 'Jesmondine',
                '{villageName}' => 'Jesmondine',
                '{grievanceDetails}' => 'Check for email nootices'
            );
            $emailTemplateInfo = getEmailTemplateInfo(8, $vars);
            $emailJob = (new GenericSendEmailJob('franklin.otieno@softclans.co.ke', $emailTemplateInfo->subject, $emailTemplateInfo->body, array()))->delay(Carbon::now()->addSeconds(10));
            dispatch($emailJob);


            // $emailJob = (new ComplaintSubmissionEmailJob($submission_email, $emailTemplateInfo->subject, $emailTemplateInfo->body, $program_name, $cc_array))->delay(Carbon::now()->addSeconds(10));
            // dispatch($emailJob);

                dd('mail sent');
        } else {
            dd('mail not sent');
        }
    }

    public function modifyHHHdetails(Request $req)
    {
        try {
            $girl_master_details = DB::table('beneficiary_information as t1')
                ->join('households as t2', 't1.household_id', '=', 't2.id')
                ->join('beneficiary_master_info as t3', 't1.master_id', '=', 't3.id')
                ->select(DB::raw('t1.id,decrypt(t1.first_name) AS first_name,
                    decrypt(t1.last_name) AS last_name,t3.hhh_fname AS orig_hhh_fname,
                    t3.hhh_lname AS orig_hhh_lname,t3.acc,t3.cwac,t3.hhh_nrc,
                    t3.hh_in_cwac,t2.hhh_fname AS old_hhh_fname,
                    t2.hhh_lname AS old_hhh_lname'))
                ->where('t1.beneficiary_status', 4)
                ->where('t1.verification_recommendation', 1)
                ->where('t2.hhh_fname', 'LIKE', 'Mary')
                ->where('t2.hhh_lname', 'LIKE', 'Mwanza')
                ->where(function ($query) {
                    $query->where('t2.hhh_nrc_number', 'LIKE', '%9999/99/9%')
                          ->orWhere('t2.hhh_nrc_number', 'LIKE', '%9999/99/1%');
                })
                ->get();
            $key = 0;
            $data = [];
            foreach ($girl_master_details as $girl_details) {
                // $data[$key]['girl_updated_hh'] = $girl_details->id;
                $cwac_id = $this->getCwacID($girl_details->cwac);
                $acc_id = $this->getAccID($girl_details->acc);
                $addData = array(
                    'number_in_cwac' => $girl_details->hh_in_cwac,
                    'cwac_id' => $cwac_id,
                    'acc_id' => $acc_id,
                    'hhh_nrc_number' => $girl_details->hhh_nrc,
                    'hhh_fname' => $girl_details->orig_hhh_fname,
                    'hhh_lname' => $girl_details->orig_hhh_lname,
                    'created_at' => Carbon::now(),
                    'created_by' => 4
                );
                $houseHoldlogs = array(
                    'ben_info_id' => $girl_details->id,
                    'initial_hhh_firstname' => $girl_details->old_hhh_lname,
                    'initial_hhh_lastname' => $girl_details->old_hhh_fname,
                    'updated_hhh_firstname' => $girl_details->orig_hhh_lname,
                    'updated_hhh_lastname' => $girl_details->orig_hhh_lname,
                    'created_at' => Carbon::now(),
                    'created_by' => 4
                );
                $houseHoldCheckParams = array(
                    'hhh_fname' => $girl_details->orig_hhh_lname,
                    'hhh_lname' => $girl_details->orig_hhh_fname
                );                
                $houseHoldCheck = DB::table('households')
                    ->where($houseHoldCheckParams)->first();
                if ($houseHoldCheck) {
                    $houseHoldUpdate = array(
                        'household_id' => $houseHoldCheck->id
                    );
                } else {
                    $houseHoldID = insertReturnID('households', $addData); 
                    $houseHoldUpdate = array(
                        'household_id' => $houseHoldID
                    );
                }
                DB::table('beneficiary_information')->where('id', $girl_details->id)->update($houseHoldUpdate);
                DB::table('household_update_log')->insert($houseHoldlogs);
                $key++;
            }
            $res = array(
                'success' => true,
                'results' => $key,
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
    
    private function _search_array_by_value($array,$key,$value) {
        $results = array();

        if (is_array($array)) {
            if (isset($array[$key]) && $array[$key] == $value) {
                $results[] = $array;
            }
    
            foreach ($array as $subarray) {
                $results = array_merge($results,$this->_search_array_by_value($subarray, $key, $value));
            }
        }
    
        return $results;
    }

    public function getBeneficiaryEnrollmentsRpt()
    {
        try {
            // Get default limit
            $normalTimeLimit = ini_get('max_execution_time'); //Job on 18/10/2022
            // Set new limit
            ini_set('max_execution_time', 0); //Job on 18/10/2022
            $normalTimeLimit = ini_get('max_execution_time'); //Job
            $qry = DB::table('beneficiary_enrollments as t1')
                ->join('beneficiary_information as t2', 't2.id', '=', 't1.beneficiary_id')
                ->groupby('t1.beneficiary_id')
                ->where('is_validated', 1)
                ->where('t2.beneficiary_status', 4)
                ->where('t2.kgs_takeup_status', 1)
                ->selectraw('t1.beneficiary_id as ben_id,t2.enrollment_status as enrollment_status');                    
            $formatted_qry = $qry->get();
            $formatted_array = convertStdClassObjToArray($formatted_qry);
            $active_count = $this->_search_array_by_value($formatted_array, 'enrollment_status', 1);
            $suspended_count = $this->_search_array_by_value($formatted_array, 'enrollment_status', 2);
            $completed_count = $this->_search_array_by_value($formatted_array, 'enrollment_status', 4);
            $pending_count = $this->_search_array_by_value($formatted_array, 'enrollment_status', 5);
            $formatted_array = array(
                'active_count' => count($active_count),
                'pending_count' => count($pending_count),
                'suspended_count' => count($suspended_count),
                'completed_count' => count($completed_count),
                'created_at' => Carbon::now()
            );
            $resp = DB::table('beneficiary_enrollment_graphs')->insert($formatted_array);
            $res = array(
                'success' => true,
                'reponse' => $resp,
                'results' => $formatted_array,
                'message' => 'All is well'
            );
            ini_set('max_execution_time', $normalTimeLimit); //Job on 18/10/2022
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'results' => array(),
                'message' => $e->getMessage(),
            );
        }
        return response()->json($res);
    }

    public function getPaymentdashboarddata()//Job on 1223/06/2022
    {
        try{
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

            $sql_query3 = db::table('beneficiary_information as t1')
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
                'supported_beneficiaries'=>number_format(count($merged_count)),
                'created_at' => Carbon::now()
            );
            $resp = DB::table('beneficiary_payment_graphs')->insert($data);
            ini_set('max_execution_time', $normalTimeLimit); //Job on 18/10/2022
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'results' => array(),
                'message' => $e->getMessage(),
            );
        }
        return response()->json($resp);
        // json_output($resp);
    }

    //payment cron functions

    function convertAssArrayToSimpleArray($assArray, $targetField)
    {
        $simpleArray = array();
        foreach ($assArray as $key => $array) {
            $simpleArray[] = $array[$targetField];
        }
        return $simpleArray;
    }

    function convertStdClassObjToArray($stdObj)
    {
        return json_decode(json_encode($stdObj), true);
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
        $user_id = \Auth::user()->id;
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
                } else {//Other Students
                    if (($term1_fees < 0 || $term1_fees == '') || ($term2_fees < 0 || $term2_fees == '') || ($term3_fees < 0 || $term3_fees == '')) {
                        $error = 'Signed beneficiary MUST have specified school fees for all terms';
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
            }
        }
        $batch_ben_ids = self::convertAssArrayToSimpleArray($batch_ben_ids, 'id');
        DB::table('enrollment_error_log')
            ->where('batch_id', $batch_id)
            ->whereIn('enrollment_id', $batch_ben_ids)
            ->delete();
        DB::table('enrollment_error_log')
            ->insert($params);
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
            ->update(array('t2.school_enrollment_status' => 3));
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
                $running_agency_id=DB::table('school_information')->where('id',$school_id)->value('running_agency_id');
                $enrollment_type_id = $enrollment_detail->beneficiary_schoolstatus_id;
                $grade_id = $enrollment_detail->school_grade;
                $year = $enrollment_detail->year_of_enrollment;
                $isGCE = $enrollment_detail->is_gce_external_candidate;
                $wb_facility_manager_id=$enrollment_detail->wb_facility_manager_id;
                $fees = getAnnualSchoolFees($school_id, $enrollment_type_id, $grade_id, $year);
                $term3_fees = $fees['term3_fees'];
                $exam_fees = '';
                $varied_grade_nine_fees= Db::table('school_running_agencies')->where('id',$running_agency_id)->value('grade_nine_twelve');
                $boarder_fess_for_agency = Db::table('school_running_agencies')->where('id',$running_agency_id)->value('b_fees');
                $varied_fees=Db::table('school_running_agencies')->where('id',$running_agency_id)->value('varied_fees');
                $counter++;
                if ($enrollment_detail->school_grade == 9 || $enrollment_detail->school_grade == 12) {
                    $exam_fees = aes_decrypt($enrollment_detail->exam_fees);
                 
                }
                if ($isGCE == 1 || $isGCE === 1) {//term 3 fees not applicable
                    $term3_fees = '';
                    if ($enrollment_detail->school_grade == 9 || $enrollment_detail->school_grade == 12) {
                        if($varied_grade_nine_fees==1  &&  $varied_fees==1 && $running_agency_id==2)
                        {   
                            if( $enrollment_type_id==3)//weekly boarder
                            {
                                if($wb_facility_manager_id==1){
                                    $initial_fees_t1= $fees['term1_fees'];
                                    $initial_fees_t2= $fees['term2_fees'];
                                    $term3_fees =0;
                                    $fees['term1_fees']=$grant_aided_plus+$initial_fees_t1;
                                    $fees['term2_fees']=$grant_aided_plus+$initial_fees_t2;

                                }else{
                                   //private facility
                                    $term3_fees =0;
                                    $fees['term1_fees']=0;
                                    $fees['term2_fees']=0;
                                }

                            }else{

                                $initial_fees_t1= $fees['term1_fees'];
                                $initial_fees_t2= $fees['term2_fees'];
                                $term3_fees =0;
                                $fees['term1_fees']=$grant_aided_plus+$initial_fees_t1;
                                $fees['term2_fees']=$grant_aided_plus+$initial_fees_t2;
                            }
                           
                            
                        }
                        if( $varied_grade_nine_fees==1 && $running_agency_id!=2)
                        {
                            if( $enrollment_type_id==3)//weekly boarder
                            {
                                if($wb_facility_manager_id==1){
                                $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
                                $fees['term1_fees']=$weekly_border_plus+$fees2['term1_fees'];
                                $fees['term2_fees']=$weekly_border_plus+$fees2['term2_fees'];
                                $term3_fees =0;
                                }else{
                                    $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
                                    $fees['term1_fees']=0;
                                    $fees['term2_fees']=0;
                                    $term3_fees =0;
                                    $exam_fees=0;
                                }

                            }
                        }
                       
                    }else{
                       
                            if( $enrollment_type_id==3)//weekly boarder
                            {
                                if($wb_facility_manager_id==1){
                                $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
                                $fees['term1_fees']=$weekly_border_plus+$fees2['term1_fees'];
                                $fees['term2_fees']=$weekly_border_plus+$fees2['term2_fees'];
                                $term3_fees =0;
                                
                                }else{
                                    $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
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
                        if( $varied_grade_nine_fees==1)
                        {
                            if( ($enrollment_type_id==1 || $enrollment_type_id==4) && $varied_fees==2)//day schlaf
                            {
                                $fees['term1_fees']=0;
                                $fees['term2_fees']=0;
                                $term3_fees =0;
                            }

                           
                            if( $enrollment_type_id==3 )//weekly boarder
                            {
                                if($wb_facility_manager_id==1){

                                    if($varied_fees==2){//to enure grant-aided students get fees
                                $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
                                $fees['term1_fees']=$weekly_border_plus;
                                $fees['term2_fees']=$weekly_border_plus;
                                $term3_fees =$weekly_border_plus;
                                    }
                                }else{//private facility
                                $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
                                $fees['term1_fees']=0;
                                $fees['term2_fees']=0;
                                $term3_fees =0;
                            
                                }
                                // $fees['term1_fees']=0;
                                // $fees['term2_fees']=0;
                                // $term3_fees =0;

                            }

                            if( $enrollment_type_id==2 && $varied_fees==2)// boarder
                            {
                                $fees2 = getAnnualSchoolFees($school_id, 2, $grade_id, $year);//boarders
                                $fees['term1_fees']=$boarder_fess_for_agency;
                                $fees['term2_fees']=$boarder_fess_for_agency;
                                $term3_fees =$boarder_fess_for_agency;

                            }

                            
                            
                        }
                    }else{

                        if( $enrollment_type_id==3)//weekly boarder
                        {
                            if($wb_facility_manager_id==1){

                                if($varied_fees==2){//to enure grant-aided students get fees
                            $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
                            $fees['term1_fees']=$weekly_border_plus;
                            $fees['term2_fees']=$weekly_border_plus;
                            $term3_fees =$weekly_border_plus;
                                }
                            }else{//private facility
                            $fees2 = getAnnualSchoolFees($school_id, 1, $grade_id, $year);//day scholars
                            $fees['term1_fees']=0;
                            $fees['term2_fees']=0;
                            $term3_fees =0;
                        
                            }
                          

                        }

                    }
                }
               
                //private agencies
                if($running_agency_id==3)
                {
                    $fees['term1_fees']=0;
                    $fees['term2_fees']=0;
                    $term3_fees =0;
                    $exam_fees=0;

                }
                $annual_fees = ((float)$fees['term1_fees'] + (float)$fees['term2_fees'] + (float)$term3_fees + (float)$exam_fees);
                $update_params = array(
                    'term1_fees' => aes_encrypt($fees['term1_fees']),
                    'term2_fees' => aes_encrypt($fees['term2_fees']),
                    'term3_fees' => aes_encrypt($term3_fees),
                    'annual_fees' => aes_encrypt($annual_fees)
                );
                DB::table('beneficiary_enrollments')
                    ->where('id', $enrollment_detail->id)
                    ->update($update_params);
            }
            $this->validateBeneficiaryEnrollment2($batch_id);
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

    /**
     * @param Request $req
     * @return \Illuminate\Http\JsonResponse
     */

    public function getSyncedUploadResults(Request $req)
    {
        try {
            $schoolId = $req->input('school_id');
            $offset = (int) ($req->input('offset') ?? 0);
            $limit = (int) ($req->input('limit') ?? 500);

            $query = DB::table('beneficiary_uploadfiles_staging as t1')
                ->leftJoin('beneficiary_images_staging as t2', 't2.image_type', '=', 't1.id')
                ->leftJoin('beneficiary_payresponses_staging as t3', 't2.beneficiary_id', '=', 't3.id')
                ->leftJoin('beneficiary_images as t4', 't2.beneficiary_id', '=', 't4.girl_id')
                ->selectRaw(
                    't2.id, t2.beneficiary_id, t2.image_type, t2.school_id, 
                    t4.saved_name as ben_photo,
                    t1.file_name as image_name, t2.image_name as image_file, 
                    t2.image_name as image_view,
                    CONCAT(t3.first_name, " ", t3.surname) as full_name, 
                    t3.id as ben_id, "Image-PNG" as file_type'
                )
                ->where('t3.verification_status', 'pending')
                ->when($schoolId, function ($query) use ($schoolId) {
                    return $query->where('t2.school_id', $schoolId);
                });

            $total = (clone $query)->count();
            $results = $query->groupBy('t2.beneficiary_id', 'image_type')
                ->offset($offset)
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'results' => $results,
                'message' => 'Results retrieved successfully',
                'pagination' => [
                    'offset' => $offset,
                    'limit' => $limit,
                    'total' => $total,
                    'has_more' => ($offset + $limit) < $total
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('getSyncedUploadResults error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving results'
            ], 500);
        }
    }
    
    /**
     * @param Request $req
     * @return \Illuminate\Http\JsonResponse
     */
    // public function getSyncedUploadData(Request $req)
    // {
    //     try {
    //         $second_req = $req;
    //         // Validate input
    //         $schoolId = $req->input('school_id');
    //         $limit = (int) ($req->input('limit') ?? 500); // Paginate results
            
    //         if ($schoolId && !is_numeric($schoolId)) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Invalid school ID provided'
    //             ], 400);
    //         }

    //         // Use pagination to avoid memory issues
    //         $query = DB::table('beneficiary_payresponses_staging as t1')
    //             ->selectRaw('t1.id, t1.school_id, t1.signature, t1.beneficiary_image, 
    //                 t1.disclaimer_form, t1.images_converted')
    //             ->where('t1.verification_status', 'pending')
    //             ->where('t1.school_id', $schoolId)
    //             ->where('t1.is_enrolled', 1)
    //             ->where('t1.images_converted', 0) // Only fetch unconverted images
    //             ->when($schoolId, function ($query) use ($schoolId) {
    //                 return $query->where('t1.school_id', $schoolId);
    //             });

    //         // Get total count without loading data
    //         $totalPending = (clone $query)->count();

    //         if ($totalPending === 0) {
    //             return $this->getSyncedUploadResults($second_req);
    //             // return response()->json([
    //             //     'success' => true,
    //             //     'results' => [],
    //             //     'message' => 'No pending beneficiaries to process',
    //             //     'total' => 0
    //             // ]);
    //         }
    //         $girls = $query->get()->toArray();
    //         // dd($girls);
    //         dispatch(new ProcessBeneficiaryImagesJob($girls));
    //         // // Dispatch async processing job for each chunk
    //         // $query->limit($limit)->orderBy('t1.id')->chunk(100, function ($query) {
    //         //     dispatch(new ProcessBeneficiaryImagesJob($query->toArray()));
    //         // });
    //         // Return immediately without waiting for processing
    //         // return response()->json([
    //         //     'success' => true,
    //         //     'message' => "Processing {$totalPending} pending records asynchronously",
    //         //     'total_pending' => $totalPending,
    //         //     'status' => 'queued'
    //         // ], 202); // 202 Accepted

    //     } catch (\Exception $e) {
    //         Log::error('getSyncedUploadData error: ' . $e->getMessage(), [
    //             'trace' => $e->getTraceAsString()
    //         ]);
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'An error occurred while processing synced upload data'
    //         ], 500);
    //     }
    // }
     public function getSyncedUploadData(Request $req)
    {
        try {
            $post_data = $req->all();
            $school_id = $post_data['school_id'];
            $qry = DB::table('beneficiary_payresponses_staging as t1')
                ->selectRaw('t1.id,t1.signature,t1.beneficiary_image,t1.disclaimer_form,t1.images_converted');
            if(isset($school_id)) {
                $qry->where('t1.school_id', $school_id);
            }
            $qry->whereRaw("t1.verification_status = 'pending' AND t1.is_enrolled = 1");
            $results = $qry->get();
            // $hard_path = "http:\\"."\\10.3.248.20:88\\moe_cms_test_variant\\backend\\public";
            // $hard_path = "\\moe_cms_test_variant\\backend\\public";
            $hard_path = "\\backend\\public";
                       
                foreach ($results as $beneficiary) {
                    $beneficiary_image = $beneficiary->beneficiary_image ? $beneficiary->beneficiary_image : null;
                    $disclaimer_form = $beneficiary->disclaimer_form ? $beneficiary->disclaimer_form : null;
                    $signature = $beneficiary->signature ? $beneficiary->signature : null;
                    $beneficiary_id = $beneficiary->id;
                    $images_converted = $beneficiary->images_converted;
                    if ($images_converted == 0) {
                        $img_update = [];
                        if($beneficiary_image) {
                            $img_url = "img".date('YmdHis')."."."png";
                            $image_url1 = $hard_path . '\\img\\images\\' . $img_url;
                            $image_url = public_path() . '\\img\\images\\' . $img_url;
                            $image_file = base64_decode($beneficiary_image);
                            $conversion_response = \Image::make($image_file)->save($image_url);
                            $img_update['image_url'] = $img_url;                        
                            if($img_update) {
                                $img_update['images_converted'] = 1;
                                // $img_update['beneficiary_image'] = '';
                                DB::table('beneficiary_images_staging')
                                    ->insert(array(
                                        'school_id' => $school_id,
                                        'beneficiary_id' => $beneficiary_id,
                                        'image_type' => 1,
                                        'image_name' => $image_url1
                                    )
                                );
                            }
                        }
                        if($disclaimer_form) {
                            $disclaimer_url = "img".date('YmdHis')."."."png";
                            $consent_hard_url = $hard_path . '\\img\\consentforms\\' . $disclaimer_url;
                            $consent_url = public_path() . '\\img\\consentforms\\' . $disclaimer_url;
                            $consent_file = base64_decode($disclaimer_form);
                            $conversion_response = \Image::make($consent_file)->save($consent_url);
                            $img_update['consentform_url'] = $disclaimer_url;
                            if($img_update) {
                                $img_update['images_converted'] = 1;
                                // $img_update['disclaimer_form'] = '';
                                DB::table('beneficiary_images_staging')
                                    ->insert(array(
                                        'school_id' => $school_id,
                                        'beneficiary_id' => $beneficiary_id,
                                        'image_type' => 3,
                                        'image_name' => $consent_hard_url
                                    )
                                );
                            }
                        }
                        if($signature) {
                            $signature_name = "img".date('YmdHis')."."."png";
                            $signature_hard_url = $hard_path . '\\img\\signatures\\' . $signature_name;
                            $signature_url = public_path() . '\\img\\signatures\\' . $signature_name;
                            $consent_file = base64_decode($signature);
                            $conversion_response = \Image::make($consent_file)->save($signature_url);
                            $img_update['consentform_url'] = $signature_name;
                            if($img_update) {
                                $img_update['images_converted'] = 1;
                                // $img_update['signature'] = '';
                                DB::table('beneficiary_images_staging')
                                    ->insert(array(
                                        'school_id' => $school_id,
                                        'beneficiary_id' => $beneficiary_id,
                                        'image_type' => 2,
                                        'image_name' => $signature_hard_url
                                    )
                                );
                            }
                        }
                        if ($img_update) {
                            DB::table('beneficiary_payresponses_staging')
                                ->where('id', $beneficiary->id)
                                ->update($img_update);
                        }
                    }
                }

            $fin_qry = DB::table('beneficiary_uploadfiles_staging as t1')
                ->leftJoin('beneficiary_images_staging as t2', 't2.image_type', '=', 't1.id')
                ->leftJoin('beneficiary_payresponses_staging as t3', 't2.beneficiary_id', '=', 't3.id')
                ->selectRaw('t2.id,t2.beneficiary_id,t2.image_type,t2.school_id,
                        t1.file_name as image_name,t2.image_name as image_file,t2.image_name as image_view,
                        CONCAT(t3.first_name," ",t3.surname) AS full_name,t3.id as ben_id, "Image-PNG" as file_type');
            if(isset($school_id)) {
                $fin_qry->where('t2.school_id', $school_id);
            } 
            $fin_qry->whereRaw("t3.verification_status = 'pending'")
                ->groupBy('t2.beneficiary_id', 'image_type');
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

    public function getCwacDropdowns(Request $request)
    {
        try {
            $table_name = 'cwac';
            $district_id = $request->input('district_id');
            $filters = $request->input('filters');
            $filters = (array)json_decode($filters);
            $qry = DB::table($table_name);
            if ($filters){
                $qry->where($filters);
            }
            if ($district_id){
                $qry->where('district_id', $district_id);
            }
            $results = $qry->limit(100)->get();
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
    // all below functions for mobile added by joseph (15th june 2025)

    public function getMobileParams(Request $req)
    {
        $request_item = $req->input('request_item');
        try {
            if ($request_item == 'provinces') {
                $results = self::getMobileTableParams('provinces');
                $res = $results->original;
            } else if ($request_item == 'districts') {
                $results = self::getMobileTableParams('districts');
                $res = $results->original;
            } else if ($request_item == 'acc') {
                $results = self::getLargeDataForMobile($req);
                $res = $results->original;
            } else if ($request_item == 'wards') {
                $results = self::getLocationDataForMobile($req);
                $res = $results->original;
            } else if ($request_item == 'cwac') {
                $results = self::getLargeDataForMobile($req);
                $res = $results->original;
            } else if ($request_item == 'schools') {
                $results = self::getSchoolinfoParam($req);
                $res = $results->original;
            } else if ($request_item == 'constituencies') {
                $results = self::getLocationDataForMobile($req);
                $res = $results->original;
            } else if ($request_item == 'beneficiaries') {
                $results = self::getBeneficiariesForMobile($req);
                $res = $results->original;
            } else if ($request_item == 'enrollment_beneficiaries') {
                $results = self::getBenForMobileEnrollments($req);
                $res = $results->original;
            } else if ($request_item == 'new_enrollment_beneficiaries') {
                $results = self::fetchIdentificationBeneficiaries($req);
                $res = $results->original;
            } else if ($request_item == 'users') {
                $results = self::getSystemUsersForMobile($req);
                $res = $results->original;
            } else if ($request_item == 'updatebeninfo') {
                $results = self::updateBeneficiaryInfo($req);
                $res = $results->original;
            } else {
                $results = self::getMobileTableParams($request_item);
                $res = $results->original;
                // $res = array(
                //     'success' => true,
                //     'message' => 'all is well'
                //     // 'message' => 'A unique Id and Request Item must be included in your payload'
                // );
            }
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    // public function syncMobileInfo(Request $req)

    // {
    //     Log::info("------------------------------------------------------");
    //     $sync_item = $req->input('sync_item');
        
    //     $sender_id = $req->input('user_id');
    //     $total_in_table = $req->input('total_in_table');
    //     $total_in_batch = $req->input('total_in_batch');
    //     $results = $req->input('results'); 
    //     Log::info("syncMobileInfo called - Sender ID: " . $sender_id);

    //     try {

    //         if($results) {

    //             foreach ($results as $t) {
    //                 $enrollment_info = isset($t['enrollment_info']) ?  $t['enrollment_info'] : null;
    //                 $fees_info = isset($t['fees']) ?  $t['fees'] : null;
    //                 unset($t['enrollment_info']);
    //                 unset($t['fees']);
    //                 $meta_info = $t;

    //                 if($enrollment_info) {                    
    //                     DB::table('beneficiary_payresponses_staging')
    //                         ->insert($enrollment_info);   
    //                 }
    //                 if($fees_info) {
    //                     Db::table('beneficiary_fees_staging')
    //                         ->where('school_id', $t['school_id'])
    //                         ->delete();
    //                     unset($fees_info['id']);
    //                     DB::table('beneficiary_fees_staging')
    //                     ->insert($fees_info); 
    //                 }
    //                 if($meta_info) {
    //                     Db::table('beneficiary_metainfo_staging')
    //                         ->where('school_id', $t['school_id'])
    //                         ->delete();
    //                     DB::table('beneficiary_metainfo_staging')
    //                     ->insert($meta_info);  
    //                 }
    //             }

    //             $res = array(
    //                 'success' => true,
    //                 'message' => 'Sync Info Received'
    //             );

    //         } else {
    //             $res = array(
    //                 'success' => false,
    //                 'message' => 'Empty Sync Request'
    //             );
    //         }
    //         Log::info("Process complete: ".json_encode($res));
    //         Log::info("-------------------------------------");

    //     } catch (\Exception $e) {
    //         Log::error("Error occured ".$e->getMessage());
    //         // error_log($e->getMessage());
    //         $res = array(
    //             'success' => false,
    //             'message' => $e->getMessage()
    //         );
    //     }

    //     return response()->json($res);

    // }
    public function syncMobileInfo(Request $req)
    {
        Log::info("------------------------------------------------------");
        $sender_id = $req->input('user_id');
        $results = $req->input('results');
        Log::info("syncMobileInfo called - Sender ID: " . $sender_id);
        
        $insertedCount = 0;
        $updatedCount = 0;
        
        try {
            if ($results && is_array($results)) {
                foreach ($results as $schoolIndex => $t) {
                    $school_id = $t['school_id'] ?? null;
                    
                    $enrollment_info_array = $t['enrollment_info'] ?? null;
                    $fees_info_array       = $t['fees'] ?? null;
                
                    unset($t['enrollment_info']);
                    unset($t['fees']);
                    $meta_info = $t;
                
                    // Process enrollment info - INSERT ON DUPLICATE KEY UPDATE
                    if ($enrollment_info_array && is_array($enrollment_info_array)) {
                        foreach ($enrollment_info_array as $enrollIndex => $enrollment_info) {
                            try {
                                if (!is_array($enrollment_info) || empty($enrollment_info)) {
                                    Log::warning("Skipping invalid enrollment at school index {$schoolIndex}, enrollment index {$enrollIndex}");
                                    continue;
                                }
                                
                                $beneficiary_id = $enrollment_info['beneficiary_id'] ?? null;
                                if (!$beneficiary_id) {
                                    Log::warning("Missing beneficiary_id at school index {$schoolIndex}, enrollment index {$enrollIndex}");
                                    continue;
                                }
                                
                                // Prepare enrollment data with all required fields
                                $enrollment_data = array_merge($enrollment_info, [
                                    'school_id' => $school_id,
                                    'in_workflow' => $enrollment_info['in_workflow'] ?? 0,
                                    'batch_id' => $enrollment_info['batch_id'] ?? 0,
                                    'batch_no' => $enrollment_info['batch_no'] ?? null,
                                    'beneficiary_schoolstatus_id' => $enrollment_info['beneficiary_schoolstatus_id'] ?? 0,
                                    'updated_at' => now(),
                                    'updated_by' => $sender_id,
                                    'prevrecord_id' => $enrollment_info['prevrecord_id'] ?? 0,
                                    'images_converted' => $enrollment_info['images_converted'] ?? 0,
                                    'created_at' => now(),
                                    'created_by' => $sender_id,
                                ]);
                                
                                // Build columns and values
                                $columns = array_keys($enrollment_data);
                                $values = array_values($enrollment_data);
                                
                                // Build placeholders
                                $placeholders = array_fill(0, count($values), '?');
                                
                                // Build ON DUPLICATE KEY UPDATE clause (exclude primary key columns)
                                $updateClauses = [];
                                foreach ($columns as $col) {
                                    // Skip auto-increment id and key columns from update
                                    if ($col !== 'id' && $col !== 'beneficiary_id' && $col !== 'school_id') {
                                        $updateClauses[] = "`{$col}` = VALUES(`{$col}`)";
                                    }
                                }
                                
                                $sql = "INSERT INTO `beneficiary_payresponses_staging` (" . 
                                    implode(', ', array_map(function($col) { return "`{$col}`"; }, $columns)) . 
                                    ") VALUES (" . implode(', ', $placeholders) . 
                                    ") ON DUPLICATE KEY UPDATE " . 
                                    implode(', ', $updateClauses);
                                
                                $result = DB::statement($sql, $values);
                                
                                // Check if insert or update occurred using ROW_COUNT()
                                // ROW_COUNT() returns 1 for insert, 2 for update (in MySQL)
                                $rowCount = DB::select('SELECT ROW_COUNT() as row_count')[0]->row_count;
                                
                                if ($rowCount == 1) {
                                    $insertedCount++;
                                    Log::info("Inserted enrollment for beneficiary: {$beneficiary_id}, school: {$school_id}");
                                } else {
                                    $updatedCount++;
                                    Log::info("Updated enrollment for beneficiary: {$beneficiary_id}, school: {$school_id}");
                                }
                                
                            } catch (\Exception $e) {
                                Log::error("Error processing enrollment at school index {$schoolIndex}, enrollment index {$enrollIndex}");
                                Log::error("Beneficiary ID: " . ($enrollment_info['beneficiary_id'] ?? 'unknown'));
                                Log::error("Error: " . $e->getMessage());
                            }
                        }
                    }
                
                    // Process fees info - DELETE existing then INSERT new
                    if ($fees_info_array && is_array($fees_info_array)) {
                        try {
                            // Get beneficiary IDs from enrollment data
                            $beneficiaryIds = array_filter(array_map(function($enrollment) {
                                return $enrollment['beneficiary_id'] ?? null;
                            }, $enrollment_info_array ?? []));
                            
                            if (!empty($beneficiaryIds)) {
                                DB::table('beneficiary_fees_staging')
                                    ->where('school_id', $school_id)
                                    ->whereIn('beneficiary_id', $beneficiaryIds)
                                    ->delete();
                            }
                            
                            foreach ($fees_info_array as $feeIndex => $fee_info) {
                                if (!is_array($fee_info) || empty($fee_info)) {
                                    Log::warning("Skipping invalid fee at school index {$schoolIndex}, fee index {$feeIndex}");
                                    continue;
                                }
                                
                                unset($fee_info['id']);
                                
                                if (count($fee_info) > 0) {
                                    DB::table('beneficiary_fees_staging')->insert($fee_info);
                                }
                            }
                            
                            Log::info("Processed fees for school_id: {$school_id}");
                            
                        } catch (\Exception $e) {
                            Log::error("Error processing fees for school_id {$school_id}: " . $e->getMessage());
                        }
                    }
                
                    // Process meta info - INSERT ON DUPLICATE KEY UPDATE
                    if ($meta_info && is_array($meta_info) && count($meta_info) > 0) {
                        try {
                            // Ensure school_id is in meta_info
                            $meta_info['school_id'] = $school_id;
                            
                            $columns = array_keys($meta_info);
                            $values = array_values($meta_info);
                            $placeholders = array_fill(0, count($values), '?');
                            
                            // Build ON DUPLICATE KEY UPDATE clause
                            $updateClauses = [];
                            foreach ($columns as $col) {
                                if ($col !== 'id' && $col !== 'school_id') {
                                    $updateClauses[] = "`{$col}` = VALUES(`{$col}`)";
                                }
                            }
                            
                            $sql = "INSERT INTO `beneficiary_metainfo_staging` (" . 
                                implode(', ', array_map(function($col) { return "`{$col}`"; }, $columns)) . 
                                ") VALUES (" . implode(', ', $placeholders) . 
                                ") ON DUPLICATE KEY UPDATE " . 
                                implode(', ', $updateClauses);
                            
                            $result = DB::statement($sql, $values);
                            
                            // Check if insert or update occurred
                            $rowCount = DB::select('SELECT ROW_COUNT() as row_count')[0]->row_count;
                            
                            if ($rowCount == 1) {
                                Log::info("Inserted meta info for school_id: {$school_id}");
                            } else {
                                Log::info("Updated meta info for school_id: {$school_id}");
                            }
                            
                        } catch (\Exception $e) {
                            Log::error("Error processing meta info for school_id {$school_id}: " . $e->getMessage());
                        }
                    }
                }
            
                // Build response message
                $messageParts = [];
                if ($insertedCount > 0) {
                    $messageParts[] = "{$insertedCount} inserted";
                }
                if ($updatedCount > 0) {
                    $messageParts[] = "{$updatedCount} updated";
                }
                
                $res = [
                    'success' => true,
                    'message' => count($messageParts) > 0 
                        ? 'Sync complete: ' . implode(', ', $messageParts)
                        : 'Sync Info Received (no changes)'
                ];
            } else {
                $res = [
                    'success' => false,
                    'message' => 'Empty Sync Request'
                ];
            }
        
            Log::info("Process complete: " . json_encode($res));
        } catch (\Exception $e) {
            Log::error("Error occurred: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
        
            $res = [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
        
        return response()->json($res);
    }

    public function getMobileTableParams($table_name)
    {
        try {
            $qry = DB::table($table_name);
            $total_in_table = $qry->count();
            $data = $qry->get();
            $res = array(
                'success' => true,
                'total_in_table' => $total_in_table,
                'results' => $data,
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

    public function getLargeDataForMobile($req)
    {
        try {
            $start = $req->input('start_at') ? $req->input('start_at') : 0;
            $limit = $req->input('end_at') ? $req->input('end_at') : 100000;
            $district_id=$req->input('district_id') ? $req->input('district_id') : null;
            $table_name = $req->input('request_item') == 'schools' ? 'school_information' : $req->input('request_item');
            $qry = DB::table($table_name . ' as t1')
                ->select('t1.*');                
            if($district_id) {
                $qry->where('t1.district_id', $district_id);
            } else {
                $qry->where('t1.id', '>=', $start);
                $qry->where('t1.id', '<=', $limit);
            }
            $total_in_table = DB::table($table_name)
                ->count();        
            $total = $qry->count();
            $data = $qry->get();
            $res = array(
                'success' => true,
                'message' => 'Records fetched successfully!!',
                'total_in_table' => $total_in_table,
                'total_in_batch' => $total,
                'results' => $data
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

    public function getLocationDataForMobile($req)
    {
        try {
            $start = $req->input('start_at') ? $req->input('start_at') : 0;
            $limit = $req->input('end_at') ? $req->input('end_at') : 100000;
            $district_id = $req->input('district_id') ? $req->input('district_id') : null;
            $table_name = $req->input('request_item');
            $qry = DB::table($table_name . ' as t1');
            $table_name == 'wards' ? (
                $qry->select('t1.id','t1.name','t1.constituency_id')
            ) : ($table_name == 'constituencies' ? (
                $qry->select('t1.id','t1.name','t1.district_id')
            ) : $qry->select('t1.id','t1.name'));
            if($district_id) {
                $qry->where('t1.district_id', $district_id);
            } else {
                $qry->where('t1.id', '>=', $start);
                $qry->where('t1.id', '<=', $limit);
            }
 
            $total_in_table = DB::table($table_name)
                ->count();        
            $total = $qry->count();
            $data = $qry->get();
            $res = array(
                'success' => true,
                'message' => 'Records fetched successfully!!',
                'total_in_table' => $total_in_table,
                'total_in_batch' => $total,
                'results' => $data
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

    public function getSchoolinfoParam(Request $req)
    {
        try {
            $start = $req->input('start_at') ? $req->input('start_at') : 0;
            $limit = $req->input('end_at') ? $req->input('end_at') : 140000;
            $filter = $req->input('filter');
            $whereClauses = array();
            $filter_string = '';
            if (isset($filter)) {
                $filters = json_decode($filter);
                if ($filters != NULL) {
                    foreach ($filters as $filter) {
                        switch ($filter->property) {
                            case 'code_name' :
                                $whereClauses[] = "t1.name like '%" . ($filter->value) . "%' OR t1.code like '%" . ($filter->value) . "%'";
                                break;
                        }
                    }
                    $whereClauses = array_filter($whereClauses);
                }
                if (!empty($whereClauses)) {
                    $filter_string = implode(' AND ', $whereClauses);
                }
            }
 
            $district_id = $req->input('district_id');
            $province_id = $req->input('province_id');
            // $limit = $req->input('limit');
            // $start = $req->input('start');
            $qry = DB::table('school_information as t1')
                ->join('districts as t3', 't1.district_id', '=', 't3.id')
                ->join('provinces as t4', 't3.province_id', '=', 't4.id')
                ->leftJoin('school_types as t5', 't1.school_type_id', '=', 't5.id')
                ->leftJoin('school_types_categories as t6', 't1.facility_type_id', '=', 't6.id')
                ->leftJoin('school_bankinformation as t2', 't1.id', '=', 't2.school_id')
                // ->leftJoin('bank_details as bank', 't2.bank_id', '=', 'bank.id')  // Added
                // ->leftJoin('bank_branches as branch', 't2.branch_name', '=', 'branch.id')
                ->leftJoin('school_contactpersons', function ($join) {//get head teacher details
                    $join->on('t1.id', '=', 'school_contactpersons.school_id')
                        ->where('school_contactpersons.designation_id', '=', DB::raw(1));
                })
                ->leftJoin('district_bank_accounts as ed_acc', function($join) {
                    $join->on('t1.district_id', '=', 'ed_acc.district_id')
                        ->where('ed_acc.bank_account_type_id', '=', 2);
                })
                ->leftJoin('district_bank_accounts as adm_acc', function($join) {
                    $join->on('t1.district_id', '=', 'adm_acc.district_id')
                        ->where('adm_acc.bank_account_type_id', '=', 3);
                })
                ->select(DB::raw(" t1.*, school_contactpersons.full_names, school_contactpersons.telephone_no as head_telephone, school_contactpersons.mobile_no as head_mobile,
                school_contactpersons.email_address as head_email, t5.name as school_type, t4.name as province_name, t2.bank_id, t2.branch_name,
                decrypt(t2.account_no) as account_no,
                t2.sort_code, t1.id as school_id, t3.name as district_name, CONCAT_WS('-',t1.code,t1.name) as code_name,t1.code,t6.name as facility_type,
                ed_acc.bank_name as education_grant_bank,
                ed_acc.branch_name as education_grant_branch,
                ed_acc.account_number as education_grant_account,
                ed_acc.sort_code as education_grant_sort_code,

                adm_acc.bank_name as administration_fees_bank,
                adm_acc.branch_name as administration_fees_branch,
                adm_acc.account_number as administration_fees_account,
                adm_acc.sort_code as administration_fees_sort_code"));
 
            $qry->where('t1.id', '>=', $start)
                        ->where('t1.id', '<=', $limit)
                        ->where('t1.isDeleted', '=', 0);
 
            if (isset($district_id) && $district_id != '') {
                $qry = $qry->where('t1.district_id', $district_id);
            }
            if (isset($province_id) && $province_id != '') {
                $qry = $qry->where('t1.province_id', $province_id);
            }
            if ($filter_string != '') {
                $qry->whereRAW($filter_string);
            }
            /*$total = $qry->count();
            $results = $qry->get();
            $res = array(
                'success' => true,
                'message' => returnMessage($results),
                'total' => $total,
                'results' => $results
            );*/
            $total_in_table = DB::table('school_information')
                ->count();        
            $total = $qry->count();
            $data = $qry->get();
            $res = array(
                'success' => true,
                'message' => 'Records fetched successfully!!',
                'total_in_table' => $total_in_table,
                'total_in_batch' => $total,
                'total' => $total,
                'results' => $data
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

    public function getBeneficiariesForMobile(Request $req)
    {
        try {
            $start=$req->input('start_at') ? $req->input('start_at') : 0;
            $limit=$req->input('end_at') ? $req->input('end_at') : 10000;
            $district_id=$req->input('district_id') ? $req->input('district_id') : null;
            $beneficiary_status = 4;
            $recommendation = 1;
            $qry = DB::table('beneficiary_information as t1')
                ->join('school_information as t2','t1.school_id', '=', 't2.id')
                ->leftJoin('beneficiary_payresponses_staging as t3', 't1.id', '=', 't3.id')
                ->select('t1.id','t1.beneficiary_id','t1.household_id',
                    't1.exam_school_id','t1.school_id','t1.cwac_id',
                    't1.acc_id','t1.ward_id','t1.constituency_id',
                    't1.district_id','t1.province_id','t1.cwac_txt',
                    't1.district_txt',
                    DB::Raw('t2.district_id as sch_district_id'),
                    DB::Raw('decrypt(t1.first_name) as first_name'),
                    DB::Raw('decrypt(t1.last_name) as last_name'),
                    't1.dob','t1.verified_dob','t1.relation_to_hhh',
                    't1.school_going','t1.qualified_sec_sch',
                    't1.willing_to_return_sch','t1.highest_grade',
                    't1.exam_grade','t1.current_school_grade','t1.exam_number')
                ->where('t1.beneficiary_status', 4)
                ->where('t1.is_checklist_verified', 0)
                ->where('t1.enrollment_status', 1)
                // ->where('t3.id', null) // beneficiaries not yet synced to staging
                ->where('t1.verification_recommendation', 1);
            $count_qry = DB::table('beneficiary_information as t1')
                ->join('school_information as t2','t1.school_id', '=', 't2.id')
                ->leftJoin('beneficiary_payresponses_staging as t3', 't1.id', '=', 't3.id')
                ->selectRaw('COUNT(t1.id) as id_count')
                ->where('t1.beneficiary_status', 4)
                ->where('t1.is_checklist_verified', 0)
                ->where('t1.enrollment_status', 1)
                // ->where('t3.id', null) // beneficiaries not yet synced to staging
                ->where('t1.verification_recommendation', 1);
            if($district_id) {
                $qry->where('t2.district_id', $district_id); 
                $total_active = $count_qry->where('t2.district_id', $district_id)->value('id_count');
            } else {
                $qry->where('t1.id', '>=', $start);
                $qry->where('t1.id', '<=', $limit);
                $total_active = 175619;
            }
 
            $data = $qry->get();
            $res = array(
                'success' => true,
                'message' => 'Records fetched successfully!!',
                'total_active_in_table' => $total_active,
                'results' => $data
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

    public function getBenForMobileEnrollments(Request $req)
    {
        try {
            $start=$req->input('start_at') ? $req->input('start_at') : 0;
            $limit=$req->input('end_at') ? $req->input('end_at') : 10000;
            $batch_id=$req->input('batch_id') ? $req->input('batch_id') : 1;
            $district_id=$req->input('district_id') ? $req->input('district_id') : null;
            $qry = DB::table('beneficiary_information as t1')
                ->join('households as t2','t1.household_id', '=', 't2.id')
                ->selectRaw('t1.id,t1.beneficiary_id,t1.household_id,
                    t1.school_id,t1.cwac_id,t1.acc_id,t1.ward_id,
                    t1.constituency_id,t1.district_id,t1.province_id,
                    decrypt(first_name) as first_name,
                    decrypt(last_name) as last_name,t1.dob,t1.verified_dob,
                    t1.current_school_grade,t1.category,t1.batch_id,
                    t2.number_in_cwac,t2.cwac_id,t2.acc_id,
                    t2.hhh_nrc_number,t2.hhh_fname,t2.hhh_lname');
            if($district_id) {
                $qry->where('t1.district_id', $district_id);
            }
            if($batch_id) {
                $qry->where('t1.batch_id', $batch_id);
            } else {
                $qry->where('t1.id', '>=', $start);
                $qry->where('t1.id', '<=', $limit);
            }
            $total_in_table = 0;
            $total_active = 0;
            $total = $qry->count();
            $data = $qry->get();
            $res = array(
                'success' => true,
                'message' => 'Records fetched successfully!!',
                'total_in_table' => $total_in_table,
                'total_active_in_table' => $total_active,
                'total_in_batch' => $total,
                'results' => $data
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

    public function getSystemUsersForMobile(Request $request)
    {
        try {
            // $group_id = $request->input('group_id');
            $qry = DB::table('users')
                ->select(DB::raw("id,decrypt(username) AS username,decrypt(first_name) AS first_name,decrypt(middlename) AS middlename,
                    decrypt(last_name) AS last_name,title_id,decrypt(dob) AS dob,decrypt(email) AS email,    
                    decrypt(phone) AS phone,decrypt(mobile) AS mobile,gender_id,
                    access_point_id,
                    user_role_id,gewel_programme_id,nongewel_programme_id,
                    dashboard_id,dms_id,password,uuid,remember_token,created_at,
                    created_by,updated_at,updated_by,last_login_time,
                    last_logout_time,is_coordinator,logout_type"))
                ->whereNotIn('users.id', function ($query) {
                    $query->select(DB::raw('blocked_accounts.account_id'))
                        ->from('blocked_accounts');
                });
            // if (isset($group_id) && $group_id != '') {
            //     $users = DB::table('user_group')
            //         ->select('user_id')
            //         ->where('group_id', $group_id)
            //         ->get();
            //     $users = convertStdClassObjToArray($users);
            //     $users = convertAssArrayToSimpleArray($users, 'user_id');
            //     $qry->whereIn('users.id', $users);
            // }
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $data = decryptArray($data);
            $res = array(
                'success' => true,
                'message' => returnMessage($data),
                // 'users' => $data,
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

    // updated function that checks for active batch (joseph - june 2025) fetchIdentificationBeneficiaries
    public function fetchIdentificationBeneficiaries(Request $request)
    {
        try {
            // Extract input
            // $startAt    = $request->input('start_at', 0);
            // $endAt      = $request->input('end_at', 10000);
            $districtId = $request->input('district_id');
 
            if (empty($districtId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'District ID is required.',
                    'results' => [],
                ], 422);
            }
 
            // Fetch active batch_id from batch_info table
            $activeBatch = DB::table('batch_info')
                ->where('mobile_verification_allowed', 1)
                ->orderByDesc('id')
                ->first();
 
            if (!$activeBatch) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active batch found for mobile verification.',
                    'results' => [],
                ], 404);
            }
 
            $batchId = $activeBatch->id;
 
            // Build the main query
            $query = DB::table('beneficiary_information as b')
                ->join('households as h', 'b.household_id', '=', 'h.id')
                ->selectRaw("
                    b.id, b.beneficiary_id, b.household_id,
                    b.school_id, b.cwac_id, b.acc_id, b.ward_id,b.constituency_id, 
                    b.district_id, b.province_id,decrypt(b.first_name) as first_name,
                    decrypt(b.last_name) as last_name,b.dob, b.verified_dob, 
                    b.current_school_grade,b.category, b.batch_id,
                    h.hhh_nrc_number, h.hhh_fname, h.hhh_lname
                ")
                ->where('b.district_id', $districtId)
                ->where('b.batch_id', $batchId);
                // ->whereBetween('b.id', [$startAt, $endAt]);
 
            $results = $query->get();
            $totalInBatch = $results->count();
 
            return response()->json([
                'success' => true,
                'message' => 'Records fetched successfully.',
                'total_in_table' => 0,
                'total_active_in_table' => 0,
                'total_in_batch' => $totalInBatch,
                'results' => $results,
            ]);
 
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'results' => [],
            ], 500);
        }
    }

    public function syncEnrollmentInfo(Request $req)
    {
        $sync_item = $req->input('sync_item');
        $sender_id = $req->input('user_id');
        $total_in_table = $req->input('total_in_table');
        $total_in_batch = $req->input('total_in_batch');
        $results = $req->input('results');
        $skippedRecords = [];
        // if (DB::table('beneficiary_payresponses_staging')->where('school_id', $school_id)->exists()) {
        //                         $skippedRecords[] = $school_id;
        //                         continue;
        //                     }
        try {
            if ($results) {
                foreach ($results as $t) {
                    $girl_id = null;
                    if (!$girl_id) continue;
                    $alreadyExists = DB::table('benenrollment_sync_staging')
                    ->where('id', $girl_id)
                    ->exists();

                    if ($alreadyExists) {
                        $skippedRecords[] = $girl_id;
                        continue; // Skip to next girl
                    }

                    $checklist_responses = isset($t['checklist_responses']) ? $t['checklist_responses'] : null;
                    unset($t['checklist_responses']);
                    if ($t) {
                        $girl_id = $t['id'];
                        // Add audit fields
                        $t['created_at'] = now();
                        $t['updated_at'] = now();
                        $t['created_by'] = $sender_id;
                        $t['updated_by'] = $sender_id;
                        DB::table('benenrollment_sync_staging')->where('id', $girl_id)->delete();
                        DB::table('benenrollment_sync_staging')->insert($t);
                    }
                    if ($checklist_responses) {
                        // Prepare checklist entries with audit fields
                        $checklistWithAudit = array_map(function ($response) use ($sender_id) {
                            $response['created_at'] = now();
                            $response['updated_at'] = now();
                            $response['created_by'] = $sender_id;
                            $response['updated_by'] = $sender_id;
                            return $response;
                        }, $checklist_responses);
                        DB::table('benenrollchecklist_sync_staging')->where('girl_id', $girl_id)->delete();
                        DB::table('benenrollchecklist_sync_staging')->insert($checklistWithAudit);
                    }
                }
                 $res = [
                    'success' => true,
                    'message' => count($skippedRecords) > 0 
                        ? 'Sync successful (' . count($skippedRecords) . ' records were already synced by another user and skipped)' 
                        : 'Sync Info Received'
                ];
            } else {
                $res = [
                    'success' => false,
                    'message' => 'No data submitted'
                ];
            }
        } catch (\Exception $e) {
            $res = [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
        return response()->json($res);
    }
 
    public function getUsersForApp(Request $request)
    {
        try {
            $latestSchool = DB::table('sa_app_user_details')
                ->select(
                    'user_id',
                    'school_assigned_id',
                    'school_assigned_emis AS emis',
                    'school_assigned_string'
                )
                ->selectRaw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY created_at DESC) AS rn');

            $users = DB::table('users AS u')
                ->leftJoinSub($latestSchool, 'latest', function ($join) {
                    $join->on('u.id', '=', 'latest.user_id')
                         ->where('latest.rn', '=', 1);
                })
                ->select(DB::raw("
                    u.id AS user_id,
                    decrypt(u.first_name) AS first_name,
                    decrypt(u.last_name) AS last_name,
                    decrypt(u.dob) AS dob,
                    decrypt(u.email) AS email,
                    u.has_kgs_app_access,
                    u.has_ppm_app_access,
                    u.allocated_district_id,
                    u.is_app_admin,
                    COALESCE(latest.school_assigned_id, NULL) AS school_assigned_id,
                    COALESCE(latest.emis, NULL) AS emis,
                    COALESCE(latest.school_assigned_string, NULL) AS school_assigned_string
                "))
                ->whereNotIn('u.id', function ($q) {
                    $q->select('account_id')->from('blocked_accounts');
                })
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Data retrieved successfully',
                'results' => $users
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }
    
    public function updateUsersForApp(Request $request)
    {
        $payload = $request->input('users'); // Array of user updates

        if (!is_array($payload) || empty($payload)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or empty payload. Expected non-empty array of users.'
            ], 400);
        }

        DB::beginTransaction();
        try {
            foreach ($payload as $userData) {
                if (!isset($userData['user_id'])) {
                    continue; // Skip malformed
                }

                $userId = $userData['user_id'];

                // === 1. Update user flags (always) ===
                DB::table('users')
                    ->where('id', $userId)
                    ->update([
                        'has_kgs_app_access'     => $userData['has_kgs_app_access'] ?? 0,
                        'has_ppm_app_access'     => !empty($userData['school_id']) ? 1 : ($userData['has_ppm_app_access'] ?? 0),
                        'allocated_district_id'  => $userData['allocated_district_id'] ?? null,
                        'updated_at'             => now()
                    ]);

                // === 2. Handle School Assignment (optional) ===
                if (!empty($userData['school_id'])) {
                    $schoolId = $userData['school_id'];

                    // Validate school exists
                    $school = DB::table('school_information AS si')
                        ->leftJoin('districts AS d', 'si.district_id', '=', 'd.id')
                        ->leftJoin('cwac AS c', 'si.cwac_id', '=', 'c.id')
                        ->where('si.id', $schoolId)
                        ->select(
                            'si.id AS school_id',
                            'si.code AS emis',
                            'si.name AS school_name',
                            'd.name AS district_name',
                            'c.name AS cwac_name'
                        )
                        ->first();

                    if (!$school) {
                        continue; // Skip invalid school
                    }

                    // Build display string
                    $assignedString = "{$school->school_id} - {$school->school_name} - {$school->district_name}";

                    // Upsert: delete old + insert since user_id is not uniq

                    DB::table('sa_app_user_details')->updateOrInsert(
                        ['user_id'               => $userId],
                        ['uuid'                  => DB::table('users')->where('id', $userId)->value('uuid'),
                        'school_assigned_id'    => $school->school_id,
                        'school_assigned_emis'  => $school->emis,
                        'school_assigned_string'=> $assignedString,
                        'district_assigned_id'  => $school->district_name ? DB::table('districts')->where('name', $school->district_name)->value('id') : 0,
                        'district_assigned_string' => $school->district_name,
                        'school_cwac_id'        => $school->cwac_name ? DB::table('cwac')->where('name', $school->cwac_name)->value('id') : 0,
                        'school_cwac_string'    => $school->cwac_name ?: 'N/A',
                        'created_at'            => now(),
                        'updated_at'            => now(),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Users and school assignments updated successfully.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getCwacsForEnrollmentsMobile(Request $request)
    {
        $districtId = $request->input('district_id');
 
        if (!$districtId) {
            return response()->json([
                'error' => 'Missing required parameter: district_id'
            ], 400);
        }
 
        // Step 1: Get active batch_id from batch_info table
        $activeBatch = DB::table('batch_info')
            ->where('mobile_verification_allowed', 1)
            ->select('id')
            ->first();
 
        if (!$activeBatch) {
            return response()->json(['error' => 'No active batch found'], 404);
        }
 
        $batchId = $activeBatch->id;
 
        // Step 2: Get distinct cwac_ids from beneficiary_information
        $cwacIds = DB::table('beneficiary_information')
            ->where('district_id', $districtId)
            ->where('batch_id', $batchId)
            ->distinct()
            ->pluck('cwac_id');
 
        // Step 3: Fetch cwac id and name where id IN $cwacIds
        $cwacs = DB::table('cwac')
            ->whereIn('id', $cwacIds)
            ->select('id', 'name')
            ->get();

        $res = [
                'success' => true,
                'message' => 'Enrollment Cwacs Fetched successfully',
                'results' => $cwacs
            ];
 
        return response()->json($res);
    }

    public function getEnrollmentStatisticsMobileOld()
    {
        // A. Records per district
        $recordsPerDistrict = DB::table('benenrollment_sync_staging as b')
            ->join('districts as d', 'b.district_id', '=', 'd.id')
            ->select('d.name as district_name', DB::raw('COUNT(b.id) as total_records'))
            ->groupBy('b.district_id', 'd.name')
            ->orderBy('d.name')
            ->get();
     
        // B. Records per user
        $recordsPerUser = DB::table('benenrollment_sync_staging as b')
            ->join('users as u', 'b.created_by', '=', 'u.id')
            ->join('districts as d', 'b.district_id', '=', 'd.id')
            ->selectRaw("
                decrypt(u.first_name) as first_name,
                decrypt(u.last_name) as last_name,
                d.name as district_name,
                COUNT(b.id) as total_records
            ")
            ->groupBy(
                'b.created_by',
                DB::raw('decrypt(u.first_name)'),
                DB::raw('decrypt(u.last_name)'),
                'd.name'
            )
            ->orderBy('total_records', 'asc')
            ->get();
     
        return response()->json([
            'records_per_district' => $recordsPerDistrict,
            'records_per_user' => $recordsPerUser,
        ]);
    }

    public function getEnrollmentStatisticsMobile()
    {
        $cutoff = '2025-07-01 13:00:00';
     
        // A. Records per district (after specific date and time)
        $recordsPerDistrict = DB::table('benenrollment_sync_staging as b')
            ->join('districts as d', 'b.district_id', '=', 'd.id')
            ->select('d.name as district_name', DB::raw('COUNT(b.id) as total_records'))
            ->where('b.created_at', '>', $cutoff)
            ->groupBy('b.district_id', 'd.name')
            ->orderBy('d.name')
            ->get();
        // B. Records per user (after specific date and time)
        $recordsPerUser = DB::table('benenrollment_sync_staging as b')
            ->join('users as u', 'b.created_by', '=', 'u.id')
            ->join('districts as d', 'b.district_id', '=', 'd.id')
            ->selectRaw("
                decrypt(u.first_name) as first_name,
                decrypt(u.last_name) as last_name,
                d.name as district_name,
                COUNT(b.id) as total_records
            ")
            ->where('b.created_at', '>', $cutoff)
            ->groupBy(
                'b.created_by',
                DB::raw('decrypt(u.first_name)'),
                DB::raw('decrypt(u.last_name)'),
                'd.name'
            )
            ->orderBy('total_records', 'asc')
            ->get();
        return response()->json([
            'records_per_district' => $recordsPerDistrict,
            'records_per_user' => $recordsPerUser,
        ]);
    }

    public function verificationOutSchlDetailsQuery_mobile($req)
    {
        $responses = $req['responses'];
        $remarks = $req['remarks'];
        $question_ids = $req['question_ids'];
        $girl_id = $req['girl_id'];
        $orders = $req['orders'];
        // $responsesArray = json_decode($responses);
        $responsesArray = explode(',', $responses);
        $is_submit = 2;
        $beneficiary_status = 2;
        // Added by Peter for mobile app submission compatibility
        $authorizedUser = \Auth::user()->id ?? $req['authorized_user'];
        // if ($is_submit == 1) {
        //     $beneficiary_status = 3;
        // }
        $res = array();
        $remarksArray = explode(',', $remarks);
        $questionsArray = explode(',', $question_ids);
        $ordersArray = explode(',', $orders);
        $count = count($questionsArray);
        $bursaryType = array();
        $bursaryRegularity = array();
        $scholarshipPackage = array();
        $skip_matching = 0;
        // if($this->user_id == 30) {
            DB::transaction(function () use (&$res, $skip_matching, $beneficiary_status, 
            $responsesArray, $remarksArray, $questionsArray, $girl_id, $ordersArray, $count, 
            $bursaryType, $bursaryRegularity, $scholarshipPackage, $authorizedUser) {
                try {
                    for ($i = 0; $i < $count; $i++) {
                        $question_id = $questionsArray[$i];
                        $params[] = array(
                            'checklist_item_id' => $question_id,
                            'beneficiary_id' => $girl_id,
                            'response' => $responsesArray[$i],
                            'remark' => $remarksArray[$i],
                            'is_mobile' => 1, // Only this line was added after replicating the function
                            'created_at' => Carbon::now(),
                            'created_by' => $authorizedUser,
                        );
                        //knock out questions
                        //Question 1 if NO means girl is out of school so category is 'NOT FOUND"
                        
                        if ($ordersArray[$i] == 1) {
                            $quizOneResponse = $responsesArray[$i];
                            if ($quizOneResponse != '') {
                                if ($quizOneResponse == 2) {
                                    DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                                    DB::table('beneficiary_verification_report')->insert($params);
                                    //end survey...update girl recommendation to NOT FOUND(out of school)
                                    DB::table('beneficiary_information')
                                    ->where('id', $girl_id)->update(array('verification_recommendation' => 3, 
                                    'beneficiary_status' => $beneficiary_status));
                                    //exit here
                                    $res = array(
                                        'success' => true,
                                        'girl_id' => $girl_id,
                                        'message' => 'Details saved successfully!!'
                                    );
                                    return response()->json($res);
                                }
                            } else {
                                $res = array(
                                    'success' => false,
                                    'girl_id' => $girl_id,
                                    'message' => 'Please fill Question No. ' . $ordersArray[$i] . '!!'
                                );
                                return response()->json($res);
                            }
                        }
                        //Question 2 if NO means girl has never attended secondary school so category is 'NOT RECOMMENDED"
                        if ($ordersArray[$i] == 2) {
                            $quizTwoResponse = $responsesArray[$i];
                            if ($quizTwoResponse != '') {
                                if ($quizTwoResponse == 2) {
                                    DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                                    DB::table('beneficiary_verification_report')->insert($params);
                                    //end survey...update girl recommendation to NOT RECOMMENDED
                                    DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 2, 
                                    'beneficiary_status' => $beneficiary_status));
                                    //exit here
                                    $res = array(
                                        'success' => true,
                                        'girl_id' => $girl_id,
                                        'message' => 'Details saved successfully!!'
                                    );
                                    return response()->json($res);
                                }
                            } else {
                                $res = array(
                                    'success' => false,
                                    'girl_id' => $girl_id,
                                    'message' => 'Please fill Question No. ' . $ordersArray[$i] . '!!'
                                );
                                return response()->json($res);
                            }
                        }
                        //quiz 3
                        if ($ordersArray[$i] == 3) {
                            $quizThreeResponse = $responsesArray[$i];
                            if ($quizTwoResponse == 1) {//then quiz three is a must
                                if ($quizThreeResponse == '') {
                                    $res = array(
                                        'success' => false,
                                        'girl_id' => $girl_id,
                                        'message' => 'Please fill Question No. ' . $ordersArray[$i] . '!!'
                                    );
                                    return response()->json($res);
                                } else {
                                    $skip_matching = 0;
                                }
                            } else {
                                //go to quiz four...
                            }
                        }
                        //Quiz 4
                        if ($ordersArray[$i] == 4) {
                            $quizFourResponse = $responsesArray[$i];
                            if ($quizThreeResponse == 2) {//then quiz four is a must
                                if ($quizFourResponse == '') {
                                    $res = array(
                                        'success' => false,
                                        'girl_id' => $girl_id,
                                        'message' => 'Please fill Question No. ' . $ordersArray[$i] . '!!'
                                    );
                                    return response()->json($res);
                                } else {
                                    if ($quizFourResponse == 2) {
                                        DB::table('beneficiary_verification_report')
                                            ->where('beneficiary_id', $girl_id)->delete();
                                        DB::table('beneficiary_verification_report')->insert($params);
                                        //end survey...update girl recommendation to NOT RECOMMENDED
                                        DB::table('beneficiary_information')
                                        ->where('id', $girl_id)->update(array('verification_recommendation' => 2, 
                                        'beneficiary_status' => $beneficiary_status));
                                        //exit here
                                        $res = array(
                                            'success' => true,
                                            'girl_id' => $girl_id,
                                            'message' => 'Details saved successfully!!'
                                        );
                                        return response()->json($res);
                                    }
                                }
                            } else {
                                //continue...
                            }
                        }
                        //quiz 5
                        if ($ordersArray[$i] == 5) {
                            $quizFiveResponse = $responsesArray[$i];
                            if ($quizFourResponse == 1) {//then quiz five is a must
                                if ($quizFiveResponse == '') {
                                    $res = array(
                                        'success' => false,
                                        'girl_id' => $girl_id,
                                        'message' => 'Please fill Question No. ' . $ordersArray[$i] . '!!'
                                    );
                                    return response()->json($res);
                                } else {
                                    if ($quizFiveResponse < 1 || $quizFiveResponse > 12) {
                                        $res = array(
                                            'success' => false,
                                            'girl_id' => $girl_id,
                                            'message' => 'Incorrect grade provided for question No. ' . $ordersArray[$i] . '. Please correct to proceed!!'
                                        );
                                        return response()->json($res);
                                    } else {
                                        if ($quizFiveResponse < 8) {//Not Recommended
                                            DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                                            DB::table('beneficiary_verification_report')->insert($params);
                                            //end survey...update girl recommendation to NOT RECOMMENDED
                                            DB::table('beneficiary_information')->where('id', $girl_id)->update(array('highest_grade' => $quizFiveResponse, 
                                            'verification_recommendation' => 2, 'beneficiary_status' => $beneficiary_status));
                                            //exit here
                                            $res = array(
                                                'success' => true,
                                                'girl_id' => $girl_id,
                                                'message' => 'Details saved successfully!!'
                                            );
                                            return response()->json($res);
                                        } else {
                                            //continue
                                        }
                                        DB::table('beneficiary_information')->where('id', $girl_id)->update(array('highest_grade' => $quizFiveResponse));
                                    }
                                }
                            } else {
                                //continue...
                            }
                        }
                        //quiz 7
                        if ($ordersArray[$i] == 7) {
                            $school = $responsesArray[$i];
                            if ($quizThreeResponse == 1 && $school == '') {
                                $res = array(
                                    'success' => false,
                                    'girl_id' => $girl_id,
                                    'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                                );
                                return response()->json($res);
                            }
                        }
                        //quiz 9
                        if ($ordersArray[$i] == 9) {
                            $grade = $responsesArray[$i];
                            if ($quizThreeResponse == 1) {//then quiz nine is a must
                                if ($grade != '') {
                                    //todo: comment out for 2021 verification
                                    /*  $next_grade = ($grade + 1);
                                    if ($grade == 12) {
                                        $next_grade = $grade;
                                    }*/
                                    //todo: end 2021 commented out
                                    //added for 2021...no grade incrementation
                                    $next_grade = $grade;
                                    //end added for 2021
                                    if ($grade < 1 || $grade > 12) {
                                        $res = array(
                                            'success' => false,
                                            'girl_id' => $girl_id,
                                            'message' => 'Incorrect grade provided for question No. ' . $ordersArray[$i] . '. Please correct to proceed!!'
                                        );
                                        return response()->json($res);
                                    } else {
                                        if ($grade < 7) {//Not Recommended
                                            DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                                            DB::table('beneficiary_verification_report')->insert($params);
                                            //end survey...update girl recommendation to NOT RECOMMENDED
                                            DB::table('beneficiary_information')->where('id', $girl_id)->update(array('current_school_grade' => $grade, 
                                            'exam_grade' => $grade, 'verification_recommendation' => 2, 'beneficiary_status' => $beneficiary_status));
                                            //exit here
                                            $res = array(
                                                'success' => true,
                                                'girl_id' => $girl_id,
                                                'message' => 'Details saved successfully!!'
                                            );
                                            return response()->json($res);
                                        }
                                        if ($grade == 7 || $grade == 9 || $grade == 12) {
                                            DB::table('beneficiary_information')->where('id', $girl_id)
                                            ->update(array('current_school_grade' => $grade, 'exam_grade' => $grade));
                                        } else {
                                            DB::table('beneficiary_information')->where('id', $girl_id)
                                            ->update(array('current_school_grade' => $next_grade, 'exam_grade' => $next_grade));
                                        }
                                    }
                                } else {
                                    $res = array(
                                        'success' => false,
                                        'girl_id' => $girl_id,
                                        'message' => 'Please enter grade for question No. ' . $ordersArray[$i] . ' !!'
                                    );
                                    return response()->json($res);
                                }
                            }
                        }
                        //quiz 10
                        if ($ordersArray[$i] == 10) {
                            $exam_number = $responsesArray[$i];
                            if (($grade == 9 || $grade == 7) && $exam_number == '') {
                                $res = array(
                                    'success' => false,
                                    'girl_id' => $girl_id,
                                    'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                                );
                                return response()->json($res);
                            } else {
                                if ($grade == 7) {
                                    DB::table('beneficiary_information')->where('id', $girl_id)->update(array('grade7_exam_no' => $exam_number));
                                } else {
                                    DB::table('beneficiary_information')->where('id', $girl_id)->update(array('grade9_exam_no' => $exam_number));
                                }
                            }
                        }
                        //quiz 11
                        if ($ordersArray[$i] == 11) {
                            $girl_school_status = $responsesArray[$i];
                            //[1,17]=day, [2,16]=boarder, [3,18]=weekly, [4,31]=unspecified
                            if ($grade > 6 && $grade < 13) {//if quiz 9 suffice then eleven is a must
                                if ($girl_school_status == '') {
                                    $res = array(
                                        'success' => false,
                                        'girl_id' => $girl_id,
                                        'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                                    );
                                    return response()->json($res);
                                } else {
                                    if ($girl_school_status == 17) {
                                        $foreign_id = 1;
                                    } else if ($girl_school_status == 16) {
                                        $foreign_id = 2;
                                    } else if ($girl_school_status == 18) {
                                        $foreign_id = 3;
                                    } else if ($girl_school_status == 31) {
                                        $foreign_id = 4;
                                    }
                                    DB::table('beneficiary_information')->where('id', $girl_id)->update(array('beneficiary_school_status' => $foreign_id));
                                }
                            }
                        }
                        //quiz 12
                        if ($ordersArray[$i] == 12) {
                            $bursary_recipient = $responsesArray[$i];
                            if ($grade > 6 && $grade < 13) {//if quiz 9 suffice then twelve is a must
                                if ($bursary_recipient == '') {
                                    $res = array(
                                        'success' => false,
                                        'girl_id' => $girl_id,
                                        'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                                    );
                                    return response()->json($res);
                                } /*else {
                                    $bursary_recipient = $response;
                                }*/
                            }
                        }
                        //quiz 13
                        if ($ordersArray[$i] == 13) {
                            $bursary_type = $responsesArray[$i];
                            $bursaryType['type'] = $responsesArray[$i];
                            if ($bursary_recipient == 1 && $bursary_type == '') {
                                $res = array(
                                    'success' => false,
                                    'girl_id' => $girl_id,
                                    'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                                );
                                return response()->json($res);
                            }
                        }
                        //quiz 14
                        if ($ordersArray[$i] == 14) {
                            $scholarship_package = $responsesArray[$i];
                            $scholarshipPackage['package'] = $responsesArray[$i];
                            if ($bursary_type == 29 && $scholarship_package == '') {
                                $res = array(
                                    'success' => false,
                                    'girl_id' => $girl_id,
                                    'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                                );
                                return response()->json($res);
                            }
                        }
                        //quiz 15
                        if ($ordersArray[$i] == 15) {
                            $bursary_regular = $responsesArray[$i];
                            $bursaryRegularity['regular'] = $responsesArray[$i];
                            if ($bursary_type == 29 && $scholarship_package == 24) {

                            } else {
                                if ($bursary_recipient == 1 && $bursary_regular == '') {
                                    $res = array(
                                        'success' => false,
                                        'girl_id' => $girl_id,
                                        'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                                    );
                                    return response()->json($res);
                                }
                            }
                        }
                        //quiz 16
                        if ($ordersArray[$i] == 16) {
                            $disability = $responsesArray[$i];
                            if ($quizFiveResponse > 6 || $grade > 6) {
                                if ($disability == '') {
                                    $res = array(
                                        'success' => false,
                                        'girl_id' => $girl_id,
                                        'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                                    );
                                    return response()->json($res);
                                }
                            }
                        }
                        //quiz 17
                        if ($ordersArray[$i] == 17) {
                            $disabilitiesArray = explode(',', $responsesArray[$i]);
                            $disabilitiesArray = array_filter($disabilitiesArray);
                            $count2 = count($disabilitiesArray);
                            if ($disability == 1) {
                                if ($count2 < 1) {
                                    $res = array(
                                        'success' => false,
                                        'girl_id' => $girl_id,
                                        'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                                    );
                                    return response()->json($res);
                                } else {
                                    for ($j = 0; $j < $count2; $j++) {
                                        $params2[] = array(
                                            'beneficiary_id' => $girl_id,
                                            'disability_id' => $disabilitiesArray[$j]
                                        );
                                    }
                                    DB::table('beneficiary_disabilities')->where(array('beneficiary_id' => $girl_id))->delete();
                                    DB::table('beneficiary_disabilities')->insert($params2);
                                }
                            } else {
                                //just proceed...but clear any previously assigned disabilities
                                DB::table('beneficiary_disabilities')->where(array('beneficiary_id' => $girl_id))->delete();
                            }
                        }
                    }
                    if ($grade > 12) {//frank
                        DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                        DB::table('beneficiary_verification_report')->insert($params);
                        //knock out this girl
                        //update girl recommendation to NOT RECOMMENDED(not qualified)
                        DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 2, 'beneficiary_status' => $beneficiary_status));
                    } else if ($bursaryType['type'] == 32) {
                        DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                        DB::table('beneficiary_verification_report')->insert($params);
                        //knock out this girl
                        //update girl recommendation to NOT RECOMMENDED(not qualified)
                        DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 2, 'beneficiary_status' => $beneficiary_status));
                    } else if (($bursaryType['type'] == 25 || $bursaryType['type'] == 26 || $bursaryType['type'] == 27 || $bursaryType['type'] == 28) && $bursaryRegularity['regular'] == 1) {
                        DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                        DB::table('beneficiary_verification_report')->insert($params);
                        //knock out this girl
                        //update girl recommendation to NOT RECOMMENDED(not qualified)
                        DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 2, 'beneficiary_status' => $beneficiary_status));
                    } else if ($bursaryType['type'] == 29 && $scholarshipPackage['package'] == 23 && $bursaryRegularity['regular'] == 1) {
                        DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                        DB::table('beneficiary_verification_report')->insert($params);
                        //knock out this girl
                        //update girl recommendation to NOT RECOMMENDED(not qualified)
                        DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 2, 'beneficiary_status' => $beneficiary_status));
                    } else {
                        DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                        DB::table('beneficiary_verification_report')->insert($params);
                        //update girl recommendation to RECOMMENDED(qualified)
                        DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 1, 'beneficiary_status' => $beneficiary_status));
                    }
                    DB::table('beneficiary_information')->where('id', $girl_id)->update(array('skip_matching' => $skip_matching));
                    if ($quizThreeResponse == 1 && $school != '') {//update beneficiary school and category
                        DB::table('beneficiary_information')->where('id', $girl_id)->update(array('school_id' => $school, 'exam_school_id' => $school));//, 'category' => 2));
                    }
                    $res = array(
                        'success' => true,
                        'girl_id' => $girl_id,
                        'message' => 'Details saved successfully!!'
                    );
                } catch (\Exception $exception) {
                    $res = array(
                        'success' => false,
                        'girl_id' => 0,
                        'message' => $exception->getMessage()
                    );
                } catch (\Throwable $throwable) {
                    $res = array(
                        'success' => false,
                        'girl_id' => 0,
                        'message' => $throwable->getMessage()
                    );
                }
            }, 5);          
        // } else {
            // $res = array(
                // 'success' => false,
                // 'message' => 'You are not allowed to perform this action'
            // );
        // }
        return response()->json($res);
    }

    public function verifyAllUnverifiedFromMobile(Request $request)
    {
        $batchId = $request->query('batch_id');// optional
        $userId  = $request->query('user_id');// optional
        $limit   = (int) ($request->query('limit') ?? 5000); // optional safety cap

        $start = Carbon::now();
        Log::info(str_repeat('-', 90));
        Log::info("I&E::Verify ALL unverified from Mobile | batch_id=" . ($batchId ?? 'NULL') . " | limit={$limit}");

        try {
            $userId = $userId ?? $this->user_id;

            // 1) Pull all unverified IDs from staging (optionally limited for safety)
            $stagingQry = DB::table('benenrollment_sync_staging')
                ->where('is_verified', 0)
                ->orderBy('id', 'asc');

            if ($batchId !== null) {
                // If your staging table has batch_id, uncomment this line:
                // $stagingQry->where('batch_id', $batchId);
            }

            $stagingIds = $stagingQry->limit($limit)->pluck('id')->toArray();

            if (empty($stagingIds)) {
                return response()->json([
                    'success' => true,
                    'message' => 'No unverified staging rows found.',
                    'processed' => 0,
                    'verified'  => 0,
                    'failed'    => 0,
                    'results'   => []
                ], 200);
            }

            // 2) Preload eligible beneficiaries from MIS
            $benQry = DB::table('beneficiary_information as t1')
                ->select(
                    't1.id as girl_id',
                    't1.beneficiary_id',
                    't1.current_school_grade',
                    't1.batch_id',
                    't1.beneficiary_status'
                )
                ->whereIn('t1.id', $stagingIds)
                ->whereIn('t1.beneficiary_status', [0, 1]);

            if ($batchId !== null) {
                $benQry->where('t1.batch_id', $batchId);
            }

            $eligible = $benQry->get()->keyBy('girl_id');

            // 3) Process each girl_id
            $QUESTION_IDS = range(76, 92);                   // 17 items
            $COMBO_IDS    = [76, 77, 78, 79, 83, 87, 91];    // yes/no style

            $results = [];
            $okCount = 0;
            $failCount = 0;

            foreach ($stagingIds as $girlId) {
                $girlStart = microtime(true);

                if (!isset($eligible[$girlId])) {
                    $results[] = [
                        'girl_id' => $girlId,
                        'success' => false,
                        'message' => 'Beneficiary not eligible (missing/invalid status or batch mismatch).'
                    ];
                    $failCount++;
                    continue;
                }

                $student = $eligible[$girlId];

                // 3a) Fetch staging checklist answers for this girl
                $answers = DB::table('benenrollchecklist_sync_staging as t1')
                    ->select('t1.checklist_id', 't1.option_id as value', 't1.remark', 't1.girl_id')
                    ->where('t1.girl_id', $girlId)
                    // If the staging checklist table has batch_id and you want to enforce it:
                    // ->where('t1.batch_id', $batchId)
                    ->get()
                    ->keyBy('checklist_id');

                $responses = [];
                $remarks   = [];

                foreach ($QUESTION_IDS as $qid) {
                    $val = null;
                    $remark = '';

                    if (isset($answers[$qid])) {
                        $raw   = $answers[$qid]->value;
                        $remark = $answers[$qid]->remark ?? '';
                        $low   = is_string($raw) ? strtolower(trim($raw)) : $raw;

                        if (in_array($qid, $COMBO_IDS, true)) {
                            if ($low === 'yes') {
                                $val = 1;
                            } elseif ($low === 'no') {
                                $val = 2;
                            } elseif (is_numeric($raw)) {
                                $val = (int)$raw;
                            } else {
                                $val = 0;
                            }
                        } elseif ($qid === 86) {
                            if (is_numeric($raw)) {
                                $val = (int)$raw;
                            } elseif (is_string($low)) {
                                if (Str::contains($low, 'schol')) {
                                    $val = 17;
                                } elseif (Str::contains($low, 'bo') && Str::contains($low, 'week')) {
                                    $val = 18;
                                } elseif (Str::contains($low, 'bo')) {
                                    $val = 16;
                                } else {
                                    $val = 17;
                                }
                            } else {
                                $val = 17;
                            }
                        } elseif ($qid === 84) {
                            if (is_numeric($raw)) {
                                $val = (int)$raw;
                            } else {
                                $val = (int)($student->current_school_grade ?? 0);
                            }
                        } else {
                            if (is_numeric($raw)) {
                                $val = (int)$raw;
                            } elseif (is_string($raw) && $raw !== '') {
                                $val = $raw;
                            } else {
                                $val = 0;
                            }
                        }
                    } else {
                        // Missing → defaults
                        if ($qid === 84) {
                            $val = (int)($student->current_school_grade ?? 0);
                        } elseif ($qid === 86) {
                            $val = 17;
                        } else {
                            $val = 0;
                        }
                    }

                    $responses[] = $val;
                    $remarks[]   = $remark;
                }

                if (count($responses) !== 17) {
                    $results[] = [
                        'girl_id' => $girlId,
                        'success' => false,
                        'message' => 'Incorrect Checklist Configuration (expected 17 responses).'
                    ];
                    $failCount++;
                    continue;
                }

                // 3b) Build MIS payload
                $payload = [
                    'girl_id'         => $girlId,
                    'responses'       => implode(',', $responses),
                    'question_ids'    => implode(',', $QUESTION_IDS),
                    'orders'          => implode(',', range(1, count($QUESTION_IDS))),
                    'remarks'         => implode(',', array_map(function ($r) { return (string)$r; }, $remarks)),
                    'is_submit'       => 2,
                    'created_at'      => Carbon::now(),
                    'created_by'      => $userId,
                    'authorized_user' => $userId,
                ];

                Log::info("Prepared MIS payload | girl_id={$girlId}", ['payload_preview' => $payload]);

                // 3c) Call MIS writer
                $ok = false;
                try {
                    $result = $this->verificationOutSchlDetailsQuery_mobile($payload);
                    if (is_object($result) && method_exists($result, 'getData')) {
                        $data = $result->getData();
                        $ok = ($data->success === true || $data->success === 'true');
                        Log::info("MIS response | girl_id={$girlId}: " . json_encode($data));
                    } else {
                        Log::warning("Unexpected MIS response type | girl_id={$girlId}");
                    }
                } catch (\Throwable $e) {
                    Log::error("MIS call failed | girl_id={$girlId} | " . $e->getMessage());
                    $ok = false;
                }

                if ($ok) {
                    DB::table('benenrollment_sync_staging')
                        ->where('id', $girlId)
                        ->update(['is_verified' => 1, 'updated_at' => Carbon::now()]);

                    $okCount++;
                    $results[] = [
                        'girl_id' => $girlId,
                        'success' => true,
                        'message' => 'Verified'
                    ];
                } else {
                    $failCount++;
                    $results[] = [
                        'girl_id' => $girlId,
                        'success' => false,
                        'message' => isset($data) && isset($data->message) ? $data->message : 'MIS rejected'
                    ];
                }

                $elapsed = round((microtime(true) - $girlStart) * 1000);
                Log::info("Processed girl_id={$girlId} in {$elapsed}ms");
            }

            $end = Carbon::now();
            Log::info("I&E::Verify ALL done | processed=" . count($stagingIds) . " | ok={$okCount} | fail={$failCount} | duration=" . $end->diff($start)->format('%H:%I:%S'));

            return response()->json([
                'success'   => true,
                'message'   => 'Batch verification completed.',
                'processed' => count($stagingIds),
                'verified'  => $okCount,
                'failed'    => $failCount,
                'results'   => $results
            ], 200);

        } catch (\Throwable $e) {
            Log::error("verifyAllUnverifiedFromMobile error | " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

      public function saveSchoolBankApprovalInfo(Request $req)
    {
        $user_id = \Auth::user()->id;
        $post_data = $req->all();
        $table_name = $post_data['table_name'] ? $post_data['table_name'] : null;
        $id = $post_data['id'] ? $post_data['id'] : null;
        $workflow_id = $post_data['workflow_id'] ? $post_data['workflow_id'] : 0;
        $prevstage_id = $workflow_id - 1;
        $res = [];
        $is_active = $req->input('is_activeaccount');
        $bank_id = isset($post_data['bank_id']) ? $post_data['bank_id'] : null;
        $approved_on = isset($post_data['approved_on']) ? $post_data['approved_on'] : null;
        $branch_id = isset($post_data['branch_name']) ? $post_data['branch_name'] : null;
        $sort_code = isset($post_data['sort_code']) ? $post_data['sort_code'] : null;
        $school_id = isset($post_data['school_id']) ? $post_data['school_id'] : null;
        $account_no = isset($post_data['account_no']) ? $post_data['account_no'] : null;
        $account_type = isset($post_data['account_type']) ? $post_data['account_type'] : null;
        $is_submission = isset($post_data['is_submission']) ? $post_data['is_submission'] : null;
        $workflow_stage = isset($post_data['workflow_stage']) ? $post_data['workflow_stage'] : null;
        $submitted_on = isset($post_data['submitted_on']) ? $post_data['submitted_on'] : null;
        $special_comments = isset($post_data['special_comments']) ? $post_data['special_comments'] : null;
        $approval_comment = isset($post_data['approval_comment']) ? $post_data['approval_comment'] : null;
        $approval_status = isset($post_data['approval_status']) ? $post_data['approval_status'] : null;
        $approved_by_id = isset($post_data['approved_by_id']) ? $post_data['approved_by_id'] : null;
        // $table_data = $post_data;  
        //add extra params
        $table_data['account_no'] = $account_no;
        $table_data['created_at'] = Carbon::now();
        $table_data['created_by'] = $user_id;
        $where = array(
            'id' => $id
        );
        try {
            $table_data = array(
                'record_id' => $id,
                'branch_id' => $branch_id,
                'sort_code' => $sort_code,
                'school_id' => $school_id,
                'account_no' => $account_no,
                'account_type' => $account_type,
                'is_submission' => $is_submission,
                'workflow_stage' => $workflow_stage,
                'submitted_on' => $submitted_on,
                'approved_on' => $approved_on,
                'is_activeaccount' => $is_active,
                'approved_by_id' => $approved_by_id,
                'approval_status' => $approval_status,
                'special_comments' => $special_comments,
                'approval_comment' => $approval_comment
            );
            $bank_update = array(
                'is_activeaccount' => $is_active,
                'school_id' => $school_id,
                'account_no' => $account_no,
                'sort_code' => $sort_code,
                'bank_id' => $bank_id,
                'account_type' => $account_type,
                'branch_name' => $branch_id
            );
            $record_id = insertRecordReturnId('bank_details_applog', $table_data, $user_id);
            if (validateisNumeric($record_id)) {
                $update_resp = DB::table('school_bankinformation')
                    ->where('id', $id)
                    ->update($bank_update);
                if (validateisNumeric($update_resp) || $update_resp == 0) {
                    $branch_resp = DB::table('bank_branches')
                        ->where('id', $branch_id)
                        ->update(array('sort_code' => $sort_code));
                    if ($is_active == 1) {
                        DB::table('school_bankinformation')
                            ->where('id', '<>', $id)
                            ->where('school_id', $school_id)
                            ->where('account_type', $account_type)
                            ->update(array('is_activeaccount' => 0));
                        $res = array(
                            'success' => true,
                            'message' => 'Data Saved Successfully!!',
                            'record_id' => $record_id
                        );
                    } else {
                        $res = array(
                            'success' => true,
                            'message' => 'Data Saved Successfully!!',
                            'record_id' => $record_id
                        );
                    }
                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'Problem Updating Branch Details!!'
                    );
                }
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'Problem Saving Approval Log Details!!'
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

    public function getSyncedVerificationData(Request $req)
    {
        try {
            //filter by districts get from the user rights
            $post_data = $req->all();
            // $absent_girls = $post_data['absent_girls'];
            $user_id = $this->user_id;
            $district_id = $req->input('district_id');
            $year = $req->input('year');
            $term = $req->input('term');
            $in_workflow = $req->input('in_workflow');
            $status_id = $req->input('status_id');
            $is_verified = isset($post_data['absent_girls']) ? ($post_data['absent_girls'] == 1 ? 0 : 1) : 1;
            $groups = getUserGroups($user_id);
            $superUserID = getSuperUserGroupId();
            $assignedDistricts = getUserDistricts($user_id); // getUserAssignedDistricts()
            $isSuperUser = in_array($superUserID, $groups);
            $qry = DB::table('beneficiary_metainfo_staging as t9')
                ->select(DB::raw("t9.id,t9.full_names,
                    t9.head_mobile as head_telephone,
                    COALESCE(t9.latitude,t9.new_latitude) as latitude,
                    COALESCE(t9.longitude,t9.new_longitude) as longitude,
                    t9.education_grant_sort_code as eduction_grant_sort_code,t9.running_agency_id,
                    sbi.bank_id, sbi.branch_name, sbi.account_no, sbi.sort_code,
                    t9.facility_type,t9.cwac_contact_person_phone_no,t9.additional_remarks,
                    t9.submitted_by as checklistissued_by,COALESCE(t9.bank_name, bd.name) as bank_name,t9.guidance_counselling_teacher_phone_no,
                    t9.guidance_counselling_teacher, t9.submitted_by as added_by,t9.batch_id,
                    t9.education_grant_bank,t9.education_grant_branch,t9.education_grant_account,
                    t9.administration_fees_bank,t9.administration_fees_branch,
                    t9.administration_fees_account,t9.administration_fees_sort_code,t9.cwac_id,
                    t9.is_visited,t9.witness_comment,t9.submitted_on,t9.school_id,t9.created_at,
                    t9.created_at as datetime,t9.submitted_by as created_by,t2.school_type_id,
                    COALESCE(YEAR(t9.created_at),2024) as selected_year,0 as added_span,0 as submission_span,
                    t2.name as school_name,t2.code as emis_code,t9.witness_name,t10.batch_no,t9.head_mobile as headteacher_tel_no,
                    t3.name as district_name,t6.name as province_name,t9.in_workflow,t9.full_names as school_headteacher,
                    (
                        SELECT COUNT(t11.id) FROM beneficiary_payresponses_staging t11 
                        WHERE " . ($is_verified == 1 ? ("t11.verification_status = 'pending'") : 
                        (" (t11.verification_status = 'marked_for_transfer' OR t11.verification_status = 'unavailable')"
                        )) . " AND t11.school_id = t9.school_id
                    ) 
                    AS beneficiary_no,
                    (
                        SELECT t12.term FROM beneficiary_fees_staging t12 
                        WHERE t12.school_id = t9.school_id 
                        LIMIT 1
                    ) as term_id,
                    COALESCE(YEAR(t9.created_at), 2024) AS year_of_enrollment,t3.id AS district_id,t9.cwac_id"))
                ->join('school_information as t2', 't9.school_id', '=', 't2.id')
                ->leftJoin('districts as t3', 't2.district_id', '=', 't3.id')
                ->leftJoin('provinces as t6', 't3.province_id', '=', 't6.id')
                ->leftJoin('payment_verificationbatch as t10', 't9.batch_id', '=', 't10.id')
                // ->leftJoin('users as t8', 't1.created_by', '=', 't8.id')
                ->leftJoin('school_bankinformation as sbi', 't9.school_id', '=', 'sbi.school_id')
                ->leftJoin('bank_details as bd', 'sbi.bank_id', '=', 'bd.id')
                ->leftJoin('beneficiary_payresponses_staging as t1', 't9.school_id', '=', 't1.school_id');
            if (isset($year) && $year != '') {
                $qry->whereRaw("YEAR(t9.created_at)=".$year);
            }
            if (isset($district_id) && $district_id != '') {
                // $qry->where(array('t2.district_id' => $district_id));
                if (!$isSuperUser && !in_array($district_id, $assignedDistricts)) {
                    $qry->where('t2.district_id', -1); 
                } else {
                        $qry->where('t2.district_id', $district_id);
                }
            }
            if (!$isSuperUser) {
                // ONLY show districts they are assigned to
                $qry->whereIn('t2.district_id', $assignedDistricts);
            }
            if (isset($in_workflow) && $in_workflow != '' && $in_workflow == 0) {
                $qry->where(array('t1.in_workflow' => $in_workflow));
            }
            $qry->whereRaw(($is_verified == 1 ? ("t1.verification_status = 'pending'") : 
                (" (t1.verification_status = 'marked_for_transfer' OR t1.verification_status = 'unavailable')"
                )));
            // $qry->where(array('t1.verification_status' => $is_verified));
            // $qry->where('t9.in_workflow', 0);
            $qry->orderBy('t9.created_at', 'asc')
                ->groupBy('t9.school_id');
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

    public function processTransfersEnterprise()
    {
        DB::beginTransaction();

        try {

            $date = '2026-02-19 00:00:00';

            // 1. Insert only NEW transfers into audit
            DB::statement("
                INSERT INTO transfers_audit (beneficiary_id, prev_school_id, current_school_id)
                SELECT 
                    s1.beneficiary_id,
                    s1.school_id,
                    s1.school_transfered_to
                FROM beneficiary_payresponses_staging s1
                LEFT JOIN transfers_audit ta
                    ON ta.beneficiary_id = s1.beneficiary_id
                WHERE s1.DATETIME >= ?
                AND s1.is_transfered = 1
                AND ta.beneficiary_id IS NULL
            ", [$date]);


            // 2. Update beneficiary school ONLY if not already updated
            DB::statement("
                UPDATE beneficiary_information t1
                INNER JOIN beneficiary_payresponses_staging s1
                    ON t1.beneficiary_id = s1.beneficiary_id
                LEFT JOIN transfers_audit ta
                    ON ta.beneficiary_id = s1.beneficiary_id
                SET t1.school_id = s1.school_transfered_to
                WHERE s1.DATETIME >= ?
                AND s1.is_transfered = 1
                AND ta.beneficiary_id IS NULL
            ", [$date]);


            // 3. Delete processed staging records
            DB::statement("
                DELETE s1
                FROM beneficiary_payresponses_staging s1
                INNER JOIN transfers_audit ta
                    ON ta.beneficiary_id = s1.beneficiary_id
                WHERE s1.DATETIME >= ?
                AND s1.is_transfered = 1
            ", [$date]);


            DB::commit();

            return response()->json([
                'message' => 'Enterprise transfer processing completed safely'
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'message' => 'Transfer processing failed',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    // temporary function to mark girls as verified
    public function markChecklistVerified()
    {
        try {

            $date = '2026-02-19 00:00:00';
            $batchSize = 1000;
            $totalUpdated = 0;

            do {

                // Fetch batch of beneficiary_ids
                $ids = DB::table('beneficiary_payresponses_staging')
                    ->where('DATETIME', '>=', $date)
                    ->limit($batchSize)
                    ->pluck('beneficiary_id')
                    ->toArray();

                if (empty($ids)) {
                    break;
                }

                DB::beginTransaction();

                $affected = DB::table('beneficiary_information')
                    ->whereIn('beneficiary_id', $ids)
                    ->update([
                        'is_checklist_verified' => 1
                    ]);

                DB::commit();

                $totalUpdated += $affected;

            } while (count($ids) == $batchSize);


            return response()->json([
                'message' => 'Checklist verification updated successfully',
                'updated_count' => $totalUpdated
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'message' => 'Checklist update failed',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    //temporary func to reset is_checklist_verified to 0 for transfers
    public function resetChecklistVerification()
    {
        try {

            $updatedCount = 0;

            DB::table('transfers_audit')
                ->select('beneficiary_id')
                ->orderBy('beneficiary_id')
                ->chunk(100, function ($records) use (&$updatedCount) {

                    $ids = $records->pluck('beneficiary_id')->toArray();

                    $affected = DB::table('beneficiary_information')
                        ->whereIn('beneficiary_id', $ids)
                        ->where('is_checklist_verified', 1)
                        ->update([
                            'is_checklist_verified' => 0
                        ]);

                    $updatedCount += $affected;
                });

            return response()->json([
                'message' => 'Checklist verification reset successfully',
                'updated_records' => $updatedCount
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
