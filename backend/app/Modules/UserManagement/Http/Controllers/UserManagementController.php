<?php

namespace App\Modules\UserManagement\Http\Controllers;

use App\ApiUser;
use App\Http\Controllers\BaseController;
use App\Mail\ActivateActivateAccount;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Modules\UserManagement\Exports\SystemUsers;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Support\Facades\Log;


class UserManagementController extends BaseController
{

    public function index()
    {
        return view('usermanagement::index');
    }

    public function saveUserCommonData(Request $req)
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
                $success = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                if ($success) {
                    $res = array(
                        'success' => true,
                        'message' => 'Data updated Successfully!!'
                    );
                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'Problem encountered while updating data. Try again later!!'
                    );
                }
            }
        } else {
            $success = insertRecord($table_name, $table_data, $user_id);
            if ($success) {
                $res = array(
                    'success' => true,
                    'message' => 'Data Saved Successfully!!'
                );
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'Problem encountered while saving data. Try again later!!'
                );
            }
        }
        return response()->json($res);
    }

    public function saveUserParam(Request $req)
    {
        $modelName = 'App\\Modules\\usermanagement\\Entities\\' . $req->model;
        $post_data = $req->all();
        unset($post_data['id']);
        unset($post_data['model']);
        $data = encryptArray($post_data, array());
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

    public function getUserModuleParamFromTable(Request $request)
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

    public function saveSystemUser(Request $req)
    {
        $user_id = \Auth::user()->id;
        $post_data = $req->all();//all post data
        // $groups = $post_data['group'];//groups are posted as json array
        $assigned_groups = $post_data['assigned_groups'];//assigned groups are posted as comma separated values
        $assigned_districts = $post_data['assigned_districts'];//assigned districts are posted as comma separated values
        //$username = $post_data['email'];
        $email = $post_data['email'];
        $password = str_random(8);//$username;//for the first time password==username
        $id = $post_data['id'];
        $skip = $post_data['skip'];
        $skipArray = explode(",", $skip);
        $assigned_groups_array = explode(",", $assigned_groups);
        $assigned_groups_array = array_filter($assigned_groups_array);
        $assigned_districts_array = explode(",", $assigned_districts);
        $assigned_districts_array = array_filter($assigned_districts_array);
        $table_name = $post_data['table_name'];
        //unset unnecessary values
        unset($post_data['_token']);
        unset($post_data['table_name']);
        unset($post_data['model']);
        unset($post_data['id']);
        unset($post_data['skip']);
        unset($post_data['id']);
        unset($post_data['password']);
        unset($post_data['group']);
        unset($post_data['assigned_groups']);
        unset($post_data['assigned_districts']);
        unset($post_data['assigned_approvers']);
        $encryptedEmail = aes_encrypt($email);
        $uuid = generateUniqID();//unique user ID
        $pwd = hashPwd($encryptedEmail, $uuid, $password);

        $fullnames = $post_data['first_name'] . ' ' . $post_data['middlename'] . ' ' . $post_data['last_name'];

        $this->saveDMSUserdetails($uuid, $fullnames, $email);
        //$pwd = hashPwd($encryptedEmail, $uuid, 'admin');
        $table_data = encryptArray($post_data, $skipArray);
        //add extra params
        $table_data['created_at'] = Carbon::now();
        $table_data['created_by'] = $user_id;
        $table_data['password'] = $pwd;
        $table_data['uuid'] = $uuid;
        $where = array(
            'id' => $id
        );
        //$groupsArray = json_decode($groups);//convert json array to a format that can be looped thro' in PHP
        $params2 = array();//to hold an associative array for users and their groups
        $params3 = array();//to hold an associative array for users and their districts foe data acess control

        if (isset($id) && $id != "") {
            if (recordExists($table_name, $where)) {
                unset($table_data['created_at']);
                unset($table_data['created_by']);
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $previous_data = getPreviousRecords($table_name, $where);
                $success = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                if ($success) {
                    //now lets update group(s) of this user but
                    //first delete all where user_id is equal to this user's id
                    if (count($assigned_groups_array) > 0) {
                        DB::table('user_group')->where('user_id', $id)->delete();
                        foreach ($assigned_groups_array as $groupId) {
                            $array1['user_id'] = $id;
                            $array1['group_id'] = $groupId;
                            array_push($params2, $array1);
                        }
                        DB::table('user_group')->insert($params2);
                    }
                    //lets also update district(s) of this user but
                    //first delete all where user_id is equal to this user's id
                    if (count($assigned_districts_array) > 0) {
                        DB::table('user_district')->where('user_id', $id)->delete();
                        foreach ($assigned_districts_array as $districtId) {
                            $array2['user_id'] = $id;
                            $array2['district_id'] = $districtId;
                            array_push($params3, $array2);
                        }
                        DB::table('user_district')->insert($params3);
                    }
                    $res = array(
                        'success' => true,
                        'message' => 'Data updated Successfully!!'
                    );
                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'Problem encountered while updating data. Try again later!!'
                    );
                }
            }
        } else {
            //first lets send this user an email with random password to avoid having a user in the db who hasn't receive pwd
            if (is_connected()) {

                //send the mail here
                $link = 'http://localhost/kgs_mis2017/';
                Mail::to($email)->send(new ActivateActivateAccount($email, $password, $link));
                if (count(Mail::failures()) > 0) {
                    $res = array(
                        'success' => false,
                        'message' => 'Problem was encountered while sending email with account instructions. Please try again later!!'
                    );
                } else {
                    $insertId = insertRecordReturnId($table_name, $table_data, $user_id);
                    if (is_numeric($insertId)) {
                        //now lets update group(s) of this user
                        if (count($assigned_groups_array) > 0) {
                            foreach ($assigned_groups_array as $groupId) {
                                $array1['user_id'] = $insertId;
                                $array1['group_id'] = $groupId;
                                array_push($params2, $array1);
                            }
                            DB::table('user_group')->insert($params2);
                        }
                        //lets also update district(s) of this user
                        if (count($assigned_districts_array) > 0) {
                            DB::table('user_district')->where('user_id', $id)->delete();
                            foreach ($assigned_districts_array as $districtId) {
                                $array2['user_id'] = $insertId;
                                $array2['district_id'] = $districtId;
                                array_push($params3, $array2);
                            }
                            DB::table('user_district')->insert($params3);
                        }
                        $res = array(
                            'success' => true,
                            'message' => 'User created successfully. Further account login credentials have been send to ' . $email . '. The user should check his/her email for login details!'
                        );
                    } else {
                        $res = array(
                            'success' => false,
                            'message' => 'Problem encountered while saving data. Try again later!!'
                        );
                    }
                }
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'Whoops!! There is no internet connection. Check your connection then try again!!'
                );
            }
        }
        return response()->json($res);
    }

    function getUserParam($model_name)
    {
        $model = 'App\\Modules\\usermanagement\\Entities\\' . $model_name;
        $results = $model::all()->toArray();
        $results = decryptArray($results);
        $res = array(
            'results' => $results
        );
        return response()->json($res);
    }
     //job 13/02/2022
    public function returnSystemUsersDownloadUrl(Request $request)
    {
        if (Auth::check()){

        $url=url('/');
        $url=$url."/usermanagement/DownloadSystemUsers";
        $res=array(
            "success"=>true,
            "url"=>$url
            
        );
        
        return response()->json($res);
        }else{
            $res=array(
            "success"=>true,
            "url"=>$url,
            "message"=>"You must be logged In!"
            
            );
         }

    }
    //job 24/01/2022
    public function DownloadSystemUsers(Request $request)
    {
        $time= date('H:i:s d-M-Y');
        return Excel::download(new SystemUsers,"MOEUtilisationReport$time.xlsx");
    }
      //job on 27/4/2022
    public function logaccess(Request $request)
    {
        $menu_id= $request->input('menu_id');
        $module_name = $request->input('module_name');
        $previous_similar_menu_item_time=Db::table('user_activity_log')->where(['menu_item_id'=>$menu_id,'created_by'=>$this->user_id])->value('created_at');
        $insert_item = false;
       if($previous_similar_menu_item_time!=null)
       {
        $later_time=Carbon::parse($previous_similar_menu_item_time)->addMinutes(5)->format('Y-m-d h:i:s');
        $start_date=new \DateTime($later_time);
        $end_date= new \DateTime(Carbon::now());
        if($start_date<$end_date)
        {
            $insert_item=true;
        }
       }else{
           $insert_item=true;
       }
       //use \DateTime;
      if($insert_item==true){
        if($this->user_id!=0 || $this->user_id!=null || $this->user_id!="")
        {
            Db::table('user_activity_log')->insert([
                "menu_item_id"=>$menu_id,
                "module_name"=>$module_name,
                "ip_address" => request()->ip(),
                "user_agent" => request()->userAgent(),
                "created_by"=>$this->user_id,
                "created_at"=>Carbon::now()       
            ]);
        }   
        } 
        $res = array(
            'success' => true    
        );
        return response()->json($res);
    }
    public
    function getSystemUsers(Request $request)
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
                $users = convertStdClassObjToArray($users);
                $users = convertAssArrayToSimpleArray($users, 'user_id');
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

    public function getAPIClients()
    {
        try {
            $qry = DB::table('api_users')
                ->select(DB::raw("id,client_name,client_postal_address,client_physical_address,contact_person,contact_person_phone,contact_person_email,
                            access_status,decrypt(client_username) as client_username,decrypt(client_secret_txt) as client_secret_txt,last_access_time"));
            $data = $qry->get();
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => returnMessage($data)
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public
    function getBlockedSystemUsers()
    {
        try {
            $qry = DB::table('users')
                ->join('blocked_accounts', 'users.id', '=', 'blocked_accounts.account_id')
                ->leftJoin('users as users2', 'users2.id', '=', 'blocked_accounts.action_by')
                ->leftJoin('user_images', 'users.id', '=', 'user_images.user_id')
                ->leftJoin('access_points', 'users.access_point_id', '=', 'access_points.id')
                ->leftJoin('user_roles', 'users.user_role_id', '=', 'user_roles.id')
                ->leftJoin('gender', 'users.gender_id', '=', 'gender.id')
                ->leftJoin('titles', 'users.title_id', '=', 'titles.id')
                ->select('blocked_accounts.id', 'blocked_accounts.account_id', 'blocked_accounts.date as blocked_on', 'blocked_accounts.reason', 'users2.first_name as first_name2', 'users2.last_name as last_name2', 'users.first_name', 'users.last_name', 'users.last_login_time', 'users.title_id', 'users.email', 'users.phone', 'users.mobile', 'users.gender_id', 'users.access_point_id', 'users.user_role_id', 'gender.name as gender_name', 'titles.name as title_name', 'user_images.saved_name', 'access_points.name as access_point_name', 'user_roles.name as user_role_name');
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $data = decryptArray($data);
            $res = array(
                'success' => true,
                'users' => $data,
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

    public
    function getUnblockedSystemUsers()
    {
        try {
            $qry = DB::table('users')
                ->join('unblocked_accounts', 'users.id', '=', 'unblocked_accounts.account_id')
                ->leftJoin('users as users2', 'users2.id', '=', 'unblocked_accounts.action_by')
                ->leftJoin('users as users3', 'users3.id', '=', 'unblocked_accounts.unblock_by')
                ->leftJoin('user_images', 'users.id', '=', 'user_images.user_id')
                ->leftJoin('access_points', 'users.access_point_id', '=', 'access_points.id')
                ->leftJoin('user_roles', 'users.user_role_id', '=', 'user_roles.id')
                ->leftJoin('gender', 'users.gender_id', '=', 'gender.id')
                ->leftJoin('titles', 'users.title_id', '=', 'titles.id')
                ->select('unblocked_accounts.id', 'unblocked_accounts.account_id', 'unblocked_accounts.date as blocked_on', 'unblocked_accounts.unblock_date', 'unblocked_accounts.reason', 'unblocked_accounts.unblock_reason', 'users3.first_name as first_name3', 'users3.last_name as last_name3', 'users2.first_name as first_name2', 'users2.last_name as last_name2', 'users.first_name', 'users.last_name', 'users.last_login_time', 'users.title_id', 'users.email', 'users.phone', 'users.mobile', 'users.gender_id', 'users.access_point_id', 'users.user_role_id', 'gender.name as gender_name', 'titles.name as title_name', 'user_images.saved_name', 'access_points.name as access_point_name', 'user_roles.name as user_role_name');
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $data = decryptArray($data);
            $res = array(
                'success' => true,
                'users' => $data,
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

    public
    function deleteUserRecord(Request $req)
    {
        $record_id = $req->id;
        $table_name = $req->table_name;
        $user_id = \Auth::user()->id;
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

    public
    function deleteSystemUser(Request $req)
    {
        $record_id = $req->id;
        $table_name = $req->table_name;
        $user_id = \Auth::user()->id;
        $where = array(
            'id' => $record_id
        );
        $previous_data = getPreviousRecords($table_name, $where);
        $success = deleteRecord($table_name, $previous_data, $where, $user_id);
        if ($success['success'] == true) {
            //delete associated user group assignments
            DB::table('user_group')->where('user_id', $record_id)->delete();
            $res = array(
                'success' => true,
                'message' => 'Record deleted successfully!!'
            );
        } else {
            $res = array(
                'success' => false,
                'message' => 'Problem encountered while deleting the record. Try again later!!'
            );
        }
        return response()->json($res);
    }

    public
    function resetUserPassword(Request $req)
    {
        //i need his/her encrypted username and UUID
        $user_id = $req->id;
        $res = array();
        DB::transaction(function () use ($user_id, &$res) {
            try {
                $user = new User();
                $userData = $user->find($user_id);
                if ($userData) {
                    $encryptedEmail = $userData->email;
                    $dms_id = $userData->dms_id;
                    $decryptedEmail = aes_decrypt($encryptedEmail);
                    $uuid = $userData->uuid;
                    $prevPwd = $userData->password;
                    $newPassword = hashPwd($encryptedEmail, $uuid, $decryptedEmail);
                    $new_dms_pwd = md5($decryptedEmail);
                    $data = array(
                        'password' => $newPassword
                    );
                    $logData = array(
                        'account_id' => $user_id,
                        'prev_password' => $prevPwd,
                        'new_password' => $newPassword,
                        'action_by' => \Auth::user()->id,
                        'time' => Carbon::now()
                    );
                    $pwd_updated = User::find($user_id)->update($data);
                    if ($pwd_updated) {
                        DB::table('password_reset_logs')->insert($logData);
                        DB::table('failed_login_attempts')->where('account_id', $user_id)->delete();
                        //update dms password too
                        $dms_db = DB::connection('dms_db');
                        $dms_db->table('tblusers')
                            ->where('id', $dms_id)
                            ->update(array('pwd' => $new_dms_pwd));
                        $res = array(
                            'success' => true,
                            'message' => 'Password was reset successfully!!'
                        );
                    } else {
                        $res = array(
                            'success' => false,
                            'message' => 'Problem encountered while resetting the password. Please try again!!'
                        );
                    }
                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'Problem encountered while resetting the password-->User not found!!'
                    );
                }
            } catch (\Exception $e) {
                $res = array(
                    'success' => false,
                    'message' => $e->getMessage()
                );
            }
        }, 5);
        return response()->json($res);
    }

    function updateUserPassword(Request $req)
    {
        $user_id = $req->id;
        $newPassword = $req->new_pwd;
        $userData = User::find($user_id);
        if ($userData) {
            $encryptedEmail = $userData->email;
            $dms_id = $userData->dms_id;
            $uuid = $userData->uuid;
            $prevPwd = $userData->password;
            $newPassword = hashPwd($encryptedEmail, $uuid, $newPassword);
            $new_dms_pwd = md5($newPassword);
            $data = array(
                'password' => $newPassword
            );
            $logData = array(
                'account_id' => $user_id,
                'prev_password' => $prevPwd,
                'new_password' => $newPassword,
                'action_by' => \Auth::user()->id,
                'time' => Carbon::now()
            );
            $pwd_updated = User::find($user_id)->update($data);
            if ($pwd_updated) {
                DB::table('password_reset_logs')->insert($logData);
                DB::table('failed_login_attempts')->where('account_id', $user_id)->delete();
                //update dms password too
                $dms_db = DB::connection('dms_db');
                $dms_db->table('tblusers')
                    ->where('id', $dms_id)
                    ->update(array('pwd' => $new_dms_pwd));
                $res = array(
                    'success' => true,
                    'message' => 'Password was reset successfully!!'
                );
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'Problem encountered while resetting the password. Please try again!!'
                );
            }
        } else {
            $res = array(
                'success' => false,
                'message' => 'Problem encountered while resetting the password-->User not found!!'
            );
        }
        return response()->json($res);
    }

    public function getUserRoles(Request $req)
    {
        $accessPointID = $req->accessPointId;
        $qry = DB::table('user_roles')
            ->join('access_points', 'user_roles.access_point_id', '=', 'access_points.id')
            ->select('user_roles.*', 'access_points.name as access_point_name');
        $qry = $accessPointID == '' ? $qry->whereRaw(1, 1) : $qry->where('user_roles.access_point_id', $accessPointID);
        $data = $qry->get();
        $results = convertStdClassObjToArray($data);
        $results = decryptArray($results);
        $res = array(
            'results' => $results
        );
        return response()->json($res);
    }

    public function getOpenGroups(Request $req)
    {
        $user_id = $req->input('user_id');
        $access_point_id = $req->input('accessPointId');
        try {
            $qry = DB::table('groups')
                ->where('group_owner_level', $access_point_id);
            if (isset($user_id) && $user_id != '') {
                $qry->whereNotIn('groups.id', function ($query) use ($user_id) {
                    $query->select(DB::raw('user_group.group_id'))
                        ->from('user_group')
                        ->whereRaw('user_group.user_id=' . $user_id);
                });
            }
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $data = decryptArray($data);
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

    public function getAssignedGroups(Request $req)
    {
        $user_id = $req->input('user_id');
        try {
            $qry = DB::table('user_group')
                ->join('groups', 'user_group.group_id', '=', 'groups.id')
                ->select('groups.*')
                ->where('user_group.user_id', $user_id);
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $data = decryptArray($data);
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

    public function getLoggedInUserGroups(Request $req)
    {
        $user_id = \Auth::user()->id;
        try {
            $qry = DB::table('user_group')
                ->join('groups', 'user_group.group_id', '=', 'groups.id')
                ->select('groups.*')
                ->where('user_group.user_id', $user_id);
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $data = decryptArray($data);
            $res = array(
                'success' => true,
                'results' => $data,
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

    public function getOpenDistricts(Request $req)
    {
        $user_id = $req->input('user_id');
        try {
            $qry = DB::table('districts');
            if (isset($user_id) && $user_id != '') {
                $qry->whereNotIn('districts.id', function ($query) use ($user_id) {
                    $query->select(DB::raw('user_district.district_id'))
                        ->from('user_district')
                        ->whereRaw('user_district.user_id=' . $user_id);
                });
            }
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $data = decryptArray($data);
            $res = array(
                'success' => true,
                'results' => $data,
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

    public function getAssignedDistricts(Request $req)
    {
        $user_id = $req->input('user_id');
        try {
            $qry = DB::table('user_district')
                ->join('districts', 'user_district.district_id', '=', 'districts.id')
                ->select('districts.*')
                ->where('user_district.user_id', $user_id);
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $data = decryptArray($data);
            $res = array(
                'success' => true,
                'results' => $data,
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

    public function getLoggedInUserDistricts(Request $req)
    {
        if(!\Auth::check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        $user_id = \Auth::user()->id;
        try {
            $qry = DB::table('user_district')
                ->join('districts', 'user_district.district_id', '=', 'districts.id')
                ->select('districts.*')
                ->where('user_district.user_id', $user_id);
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $data = decryptArray($data);
            $res = array(
                'success' => true,
                'results' => $data,
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

    public function saveUserInformation(Request $req)
    {
        $res = array();
        DB::transaction(function () use ($req, &$res) {
            $user_id = Auth::user()->id;

            $id = $req->input('id');
            $email = $req->input('email');
            $profile_url = $req->input('saved_name');
            $first_name = $req->input('first_name');
            $othernames = $req->input('last_name');
            //districts
            $districts = $req->input('districts');
            $districts = json_decode($districts);
            $assigned_districts = array();
            $first_district_id = (is_array($districts) && count($districts) > 0) ? $districts[0] : null; // added on Feb 10th 2026
            //groups
            $groups = $req->input('groups');
            $groups = json_decode($groups);
            $assigned_groups = array();
            //schools
            $schools = $req->input('schools');
            $schools = json_decode($schools);
            $assigned_schools = array();
            //Cwac
            $cwac = $req->input('cwac');
            $cwac = json_decode($cwac);
            $assigned_cwac = array();

            $table_data = array(
                'access_point_id' => $req->input('access_point_id'),
                'gewel_programme_id' => $req->input('gewel_programme_id'),
                'nongewel_programme_id' => $req->input('nongewel_programme_id'),
                'first_name' => $first_name,
                'last_name' => $othernames,
                'gender_id' => $req->input('gender_id'),
                'dashboard_id' => $req->input('dashboard_id'),
                'mobile' => $req->input('mobile'),
                'phone' => $req->input('phone'),
                'title_id' => $req->input('title_id'),
                'user_role_id' => $req->input('user_role_id'),
                'allocated_district_id' => $first_district_id // added on Feb 10th 2026
            );

            $skip = $req->input('skip');
            $skipArray = explode(",", $skip);
            if (!in_array('allocated_district_id', $skipArray)) {
                $skipArray[] = 'allocated_district_id';
            }

            $table_data = encryptArray($table_data, $skipArray);

            $where = array(
                'id' => $id
            );
            $table_name = 'users';
            try {
                if (isset($id) && $id != "") {
                    if (recordExists($table_name, $where)) {
                        $table_data['updated_at'] = Carbon::now();
                        $table_data['updated_by'] = $user_id;
                        $previous_data = getPreviousRecords($table_name, $where);
                        $dms_user_id = $previous_data[0]['dms_id'];
                        $success = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                        //update dms
                        $dms_data = array(
                            'fullName' => $first_name . ' ' . $othernames
                        );
                        $this->updateDMSUserdetails($dms_user_id, $dms_data, $groups);
                        //check profile pic
                        if (recordExists('user_images', array('user_id' => $id))) {
                            if ($profile_url != '') {
                                DB::table('user_images')
                                    ->where(array('user_id' => $id))
                                    ->update(array('saved_name' => $profile_url));
                            }
                        }
                        if ($success['success'] == true) {
                            Log::info("Users updated ".$id. " district: ".$first_district_id);
                            if (count($groups) > 0) {
                                foreach ($groups as $group_id) {
                                    $assigned_groups[] = array(
                                        'user_id' => $id,
                                        'group_id' => $group_id
                                    );
                                }
                                DB::table('user_group')->where('user_id', $id)->delete();
                                DB::table('user_group')->insert($assigned_groups);
                            }
                            if (count($districts) > 0) {
                                foreach ($districts as $district_id) {
                                    $assigned_districts[] = array(
                                        'user_id' => $id,
                                        'district_id' => $district_id
                                    );
                                }
                                DB::table('user_district')->where('user_id', $id)->delete();
                                DB::table('user_district')->insert($assigned_districts);
                            } else {
                                DB::table('user_district')->where('user_id', $id)->delete();
                            }
                            //Schools
                            if (count($schools) > 0) {
                                foreach ($schools as $school_id) {
                                    $assigned_schools[] = array(
                                        'user_id' => $id,
                                        'school_id' => $school_id
                                    );
                                }
                                DB::table('user_school')->where('user_id', $id)->delete();
                                DB::table('user_school')->insert($assigned_schools);
                            } else {
                                DB::table('user_school')->where('user_id', $id)->delete();
                            }
                            //Cwac
                            if (count($cwac) > 0) {
                                foreach ($cwac as $cwac_id) {
                                    $assigned_cwac[] = array(
                                        'user_id' => $id,
                                        'cwac_id' => $cwac_id
                                    );
                                }
                                DB::table('user_cwac')->where('user_id', $id)->delete();
                                DB::table('user_cwac')->insert($assigned_cwac);
                            } else {
                                DB::table('user_cwac')->where('user_id', $id)->delete();
                            }

                            $res = array(
                                'success' => true,
                                'message' => 'Data updated Successfully!!'
                            );
                        } else {
                            Log::error("User update failed");
                            $res = $success;
                        }
                    }
                } else {
                    //check that the user is on public IP
                    if (checkForLocalIPs()) {
                        $res = array(
                            'success' => false,
                            'message' => 'You seem to be accessing the system locally (via a local IP), this action require use of public IP. Kindly switch to the public IP!!'
                        );
                        return response()->json($res);
                    }
                    //check if this email has been used before
                    $encryptedEmail = aes_encrypt($email);
                    $email_exists = DB::table('users')
                        ->where('email', $encryptedEmail)
                        ->first();
                    if (!is_null($email_exists)) {
                        $res = array(
                            'success' => false,
                            'message' => 'This Email Address (' . $email . ') is already registered. Please use a different Email Address!!'
                        );
                        return response()->json($res);
                    }
                    $password = str_random(8);
                    $uuid = generateUniqID();//unique user ID
                    $pwd = hashPwd($encryptedEmail, $uuid, $password);
                    //add extra params
                    $table_data['email'] = $encryptedEmail;
                    $table_data['password'] = $pwd;
                    $table_data['uuid'] = $uuid;

                    //first lets send this user an email with random password to avoid having a user in the db who hasn't receive pwd
                    if (is_connected()) {
                        //send the mail here
                        $link = url('/');
                        Mail::to($email)->send(new ActivateActivateAccount($email, $password, $link));
                        if (count(Mail::failures()) > 0) {

                            $res = array(
                                'success' => false,
                                'message' => 'Problem was encountered while sending email with account instructions. Please try again later!!'
                            );
                        } else {
                            $table_data['created_at'] = Carbon::now();
                            $table_data['created_by'] = $user_id;
                            $insertDetails = insertRecord($table_name, $table_data, $user_id);
                            if ($insertDetails['success'] == true) {
                                $insertId = $insertDetails['record_id'];
                                // Log::info("Users created ".$insertId);
                                //DMS details....only when the user has been created successfully..ama namna gani
                                $fullnames = $first_name . ' ' . $othernames;
                                $dms_user_id = $this->saveDMSUserdetails($email, $password, $fullnames, $groups);
                                if (is_numeric($dms_user_id)) {
                                    DB::table('users')
                                        ->where('id', $insertId)
                                        ->update(array('dms_id' => $dms_user_id));
                                }
                                //end dms
                                if (count($groups) > 0) {
                                    foreach ($groups as $group_id) {
                                        $assigned_groups[] = array(
                                            'user_id' => $insertId,
                                            'group_id' => $group_id
                                        );
                                    }
                                    DB::table('user_group')->insert($assigned_groups);
                                }
                                if (count($districts) > 0) {
                                    foreach ($districts as $district_id) {
                                        $assigned_districts[] = array(
                                            'user_id' => $insertId,
                                            'district_id' => $district_id
                                        );
                                    }
                                    DB::table('user_district')->insert($assigned_districts);
                                }
                                  //Schools
                                if (count($schools) > 0) {
                                    foreach ($schools as $school_id) {
                                        $assigned_schools[] = array(
                                                'user_id' => $id,
                                                'school_id' => $school_id,
                                            );
                                        }
                                    DB::table('user_school')->insert($assigned_schools);
                                }
                               //Cwac
                                if (count($cwac) > 0) {
                                    foreach ($cwac as $cwac_id) {
                                        $assigned_cwac[] = array(
                                                'user_id' => $id,
                                                'cwac_id' => $cwac_id,
                                        );
                                    }
                                        DB::table('user_cwac')->insert($assigned_cwac);
                                } 
                                if ($profile_url != '') {
                                    DB::table('user_images')
                                        ->where(array('saved_name' => $profile_url))
                                        ->update(array('user_id' => $insertId));
                                }
                                $res = array(
                                    'success' => true,
                                    'message' => 'User created successfully. Further account login credentials have been send to ' . $email . '. The user should check his/her email for login details!'
                                );
                            } else {
                                Log::error("Users creation failed ");
                                $res = $insertDetails;
                            }
                        }
                    } else {
                        $res = array(
                            'success' => false,
                            'message' => 'Whoops!! There is no internet connection. Check your connection then try again!!'
                        );
                    }
                }
            } catch (\Exception $e) {
                $res = array(
                    'success' => false,
                    'message' => $e->getMessage()
                );
            }
        }, 5);
        return response()->json($res);
    }

    public function saveApiClientInformation(Request $req)
    {
        $res = array();
        DB::transaction(function () use ($req, &$res) {
            $user_id = Auth::user()->id;
            $id = $req->input('id');
            $table_data = array(
                'client_name' => $req->input('client_name'),
                'client_postal_address' => $req->input('client_postal_address'),
                'client_physical_address' => $req->input('client_physical_address'),
                'contact_person' => $req->input('contact_person'),
                'contact_person_phone' => $req->input('contact_person_phone'),
                'contact_person_email' => $req->input('contact_person_email')
            );

            $where = array(
                'id' => $id
            );
            $table_name = 'api_users';
            try {
                if (isset($id) && $id != "") {
                    if (recordExists($table_name, $where)) {
                        $table_data['updated_at'] = Carbon::now();
                        $table_data['updated_by'] = $user_id;
                        $previous_data = getPreviousRecords($table_name, $where);
                        $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                    }
                } else {
                    //generate client id and client secret
                    $uuid = generateUniqID();//unique user ID
                    $client_username = aes_encrypt(Str::random(6));//username
                    $client_secret_txt = generateUniqID();//password
                    $client_secret = hashPwd($client_username, $uuid, $client_secret_txt);
                    //add extra params
                    $table_data['client_username'] = $client_username;
                    $table_data['client_secret_txt'] = aes_encrypt($client_secret_txt);
                    $table_data['client_secret'] = $client_secret;
                    $table_data['uuid'] = $uuid;

                    $table_data['created_at'] = Carbon::now();
                    $table_data['created_by'] = $user_id;
                    $res = insertRecord($table_name, $table_data, $user_id);
                }
            } catch (\Exception $e) {
                $res = array(
                    'success' => false,
                    'message' => $e->getMessage()
                );
            }
        }, 5);
        return response()->json($res);
    }

    function saveDMSUserdetails($email_address, $password, $fullnames, $groups)
    {
        //database dms_db
        $dms_db = DB::connection('dms_db');
        $exists = $dms_db->table('tblusers')
            ->where(array('login' => $email_address))
            ->first();
        $userID = 0;
        $dms_grp_members = array();
        if (is_null($exists)) {
            $data = array(
                'login' => $email_address,
                'pwd' => md5($password),
                'fullName' => $fullnames,
                'email' => $email_address,
                'language' => 'en_GB',
                'theme' => 'bootstrap',
                'role' => 1//0=user, 1=administrator, 3=guest
            );
            //then make an insertion
            $userID = $dms_db->table('tblusers')->insertGetId($data);
            if (count($groups) > 0) {
                foreach ($groups as $group_id) {
                    $dms_grp_members[] = array(
                        'userID' => $userID,
                        'groupID' => $this->getDMSID($group_id)
                    );
                }
                $dms_db->table('tblgroupmembers')
                    ->insert($dms_grp_members);
            }
        }
        return $userID;
    }

    function updateDMSUserdetails($dms_user_id, $params, $groups)
    {
        if (validateisNumeric($dms_user_id)) {
            //database dms_db
            $dms_db = DB::connection('dms_db');
            $dms_db->table('tblusers')
                ->where('id', $dms_user_id)
                ->update($params);
            $dms_grp_members = array();

            $dms_db->table('tblgroupmembers')
                ->where('userID', $dms_user_id)
                ->delete();
            if (count($groups) > 0) {
                foreach ($groups as $group_id) {
                    $dms_grp_members[] = array(
                        'userID' => $dms_user_id,
                        'groupID' => $this->getDMSID($group_id)
                    );
                }
                $dms_db->table('tblgroupmembers')
                    ->insert($dms_grp_members);
            }
        }
    }

    public function getDMSID($group_id)
    {
        $dms_id = DB::table('groups')
            ->where('id', $group_id)
            ->value('dms_id');
        return $dms_id;
    }

    public function blockSystemUser(Request $req)
    {
        $user_id = $req->input('id');
        $email = $req->input('email');
        $reason = $req->input('reason');
        try {
            $params = array(
                'account_id' => $user_id,
                'email' => $email,
                'reason' => $reason,
                'action_by' => \Auth::user()->id,
                'date' => Carbon::now()
            );
            DB::table('blocked_accounts')
                ->insert($params);
            $res = array(
                'success' => true,
                'message' => 'User blocked successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function unblockSystemUser(Request $req)
    {
        $id = $req->input('id');
        $user_id = $req->input('user_id');
        $reason = $req->input('reason');
        $res = array();
        DB::transaction(function () use ($user_id, $reason, &$res) {
            try {
                $blocking_details = DB::table('blocked_accounts')
                    ->where('account_id', $user_id)
                    ->first();
                if (!is_null($blocking_details)) {
                    $unblock_details = array(
                        'account_id' => $blocking_details->account_id,
                        'email' => $blocking_details->email,
                        'date' => $blocking_details->date,
                        'action_by' => $blocking_details->action_by,
                        'reason' => $blocking_details->reason,
                        'unblock_reason' => $reason,
                        'unblock_by' => \Auth::user()->id,
                        'unblock_date' => Carbon::now()
                    );
                    DB::table('unblocked_accounts')
                        ->insert($unblock_details);
                    DB::table('blocked_accounts')
                        ->where('account_id', $user_id)
                        ->delete();
                    DB::table('failed_login_attempts')
                        ->where('account_id', $user_id)
                        ->delete();
                    $res = array(
                        'success' => true,
                        'message' => 'User activated successfully!!'
                    );
                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'Anomaly encountered. Blocked details not found!!'
                    );
                }
            } catch (\Exception $e) {
                $res = array(
                    'success' => false,
                    'message' => $e->getMessage()
                );
            }
        }, 5);
        return response()->json($res);
    }

    public function saveUserImage(Request $req)
    {
        $user_id = $req->input('id');
        $res = array();
        try {
            if ($req->hasFile('profile_photo')) {
                $ben_image = $req->file('profile_photo');
                $origImageName = $ben_image->getClientOriginalName();
                $extension = $ben_image->getClientOriginalExtension();
                $destination = getcwd() . '\resources\images\user-profile';
                $savedName = str_random(5) . time() . '.' . $extension;
                $ben_image->move($destination, $savedName);
                $where = array(
                    'user_id' => $user_id
                );
                if ($user_id != '') {
                    $recordExists = recordExists('user_images', $where);
                    if ($recordExists) {
                        $update_params = array(
                            'initial_name' => $origImageName,
                            'saved_name' => $savedName,
                            'updated_by' => \Auth::user()->id
                        );
                        DB::table('user_images')
                            ->where($where)
                            ->update($update_params);
                    } else {
                        $insert_params = array(
                            'user_id' => $user_id,
                            'initial_name' => $origImageName,
                            'saved_name' => $savedName,
                            'created_by' => \Auth::user()->id
                        );
                        DB::table('user_images')
                            ->insert($insert_params);
                    }
                } else {
                    $insert_params = array(
                        'user_id' => $user_id,
                        'initial_name' => $origImageName,
                        'saved_name' => $savedName,
                        'created_by' => \Auth::user()->id
                    );
                    DB::table('user_images')
                        ->insert($insert_params);
                }
                $res = array(
                    'success' => true,
                    'image_name' => $savedName,
                    'message' => 'Image uploaded successfully!!'
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

    public function shareUserUpdate(Request $req)
    {
        $user_update = $req->input('user_update');
        try {
            $params = array(
                'user_id' => \Auth::user()->id,
                'content' => $user_update,
                'date' => Carbon::now(),
                'created_at' => Carbon::now(),
                'created_by' => \Auth::user()->id,
                'is_active' => 1
            );
            DB::table('user_shared_items')->insert($params);
            $res = array(
                'success' => true,
                'message' => 'Status update shared successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getUsersUpdates(Request $req)
    {
        try {
            $qry = DB::table('user_shared_items as t1')
                ->join('users as t2', 't2.id', '=', 't1.user_id')
                ->leftJoin('user_images as t3', 't2.id', '=', 't3.user_id')
                ->select(DB::raw('t1.*,decrypt(t2.email) as name, t3.saved_name as profile_pic,
                                  CASE WHEN decrypt(t2.first_name) IS NULL THEN t2.first_name ELSE decrypt(t2.first_name) END as sender_fname,
                                  CASE WHEN decrypt(t2.last_name) IS NULL THEN t2.last_name ELSE decrypt(t2.last_name) END as sender_lname'))
                ->where('t1.is_active', 1)
                ->orderBy('t1.date', 'DESC');
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

    public function sendUserMessage(Request $req)
    {
        $recipient = $req->input('recipient');
        $message = $req->input('message');
        $sender = \Auth::user()->id;
        try {
            $params = array(
                'sender_id' => $sender,
                'recipient_id' => $recipient,
                'message' => $message,
                'date' => Carbon::now(),
                'created_at' => Carbon::now(),
                'created_by' => $sender,
                'is_active' => 1
            );
            DB::table('user_messages')->insert($params);
            $res = array(
                'success' => true,
                'message' => 'Message sent successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getUserMessages(Request $req)
    {
        // Check if user is authenticated first
        if (!\Auth::check()) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }
        $user_id = \Auth::user()->id;
        try {
            $qry = DB::table('user_messages as t1')
                ->join('users as t2', 't2.id', '=', 't1.sender_id')
                ->join('users as t22', 't22.id', '=', 't1.recipient_id')
                ->leftJoin('user_images as t3', 't2.id', '=', 't3.user_id')
                ->select(DB::raw('t1.*,t3.saved_name as profile_pic,
                                  CASE WHEN decrypt(t2.first_name) IS NULL THEN t2.first_name ELSE decrypt(t2.first_name) END as sender_fname,
                                  CASE WHEN decrypt(t2.last_name) IS NULL THEN t2.last_name ELSE decrypt(t2.last_name) END as sender_lname,
                                  CASE WHEN decrypt(t22.first_name) IS NULL THEN t22.first_name ELSE decrypt(t22.first_name) END as recipient_fname,
                                  CASE WHEN decrypt(t22.last_name) IS NULL THEN t22.last_name ELSE decrypt(t22.last_name) END as recipient_lname'))
                ->where('t1.is_active', 1)
                ->where('t1.sender_id', $user_id)
                ->orWhere('t1.recipient_id', $user_id)
                ->orderBy('t1.date', 'ASC');
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

    public function updateUserProfileInfo(Request $req)
    {
        try {
            $user_id = \Auth::user()->id;
            $where = array('id' => $user_id);
            $params = array(
                'first_name' => aes_encrypt($req->input('first_name')),
                'last_name' => aes_encrypt($req->input('last_name')),
                'title_id' => $req->input('title_id'),
                'phone' => $req->input('phone'),
                'mobile' => $req->input('mobile'),
                'gender_id' => $req->input('gender_id')
            );
            $prev_record = getPreviousRecords('users', $where);
            updateRecord('users', $prev_record, $where, $params, $user_id);
            $res = array(
                'success' => true,
                'message' => 'Details updated successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getPaymentApprovers(Request $req)
    {
        try {
            $roles = getPaymentApproverRoles();
            $qry = DB::table('users')
                ->leftJoin('titles', 'users.title_id', '=', 'titles.id')
                ->select(DB::raw("users.id,CONCAT_WS(' ',titles.name,decrypt(first_name),decrypt(last_name)) as name"))
                ->whereIn('user_role_id', $roles);
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

    public function getUserNotifications()
    {
        try {
            $user_id = \Auth::user()->id;
            $messages_count = DB::table('user_messages')
                ->where('recipient_id', $user_id)
                ->where(function ($query) {
                    $query->where('read_receipt', 0)
                        ->orWhereNull('read_receipt');
                })
                ->count();
            $posts_count = DB::table('user_shared_items as t1')
                ->where('t1.user_id', '<>', $user_id)
                ->whereNotIn('t1.id', function ($query) use ($user_id) {
                    $query->select(DB::raw('t2.item_id'))
                        ->from('shared_items_read_receipts as t2')
                        ->where('t2.user_id', $user_id);
                })
                ->count();
            $notification_count = $messages_count + $posts_count;
            $res = array(
                'success' => true,
                'count' => $notification_count,
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

    public function updateUserNotifications()
    {
        try {
            $user_id = \Auth::user()->id;
            DB::table('user_messages')
                ->where('recipient_id', $user_id)
                ->where(function ($query) {
                    $query->where('read_receipt', 0)
                        ->orWhereNull('read_receipt');
                })
                ->update(array('read_receipt' => 1));
            $unread_posts = DB::table('user_shared_items as t1')
                ->select(DB::raw("t1.id as item_id,$user_id as user_id"))
                ->where('t1.user_id', '<>', $user_id)
                ->whereNotIn('t1.id', function ($query) use ($user_id) {
                    $query->select(DB::raw('t2.item_id'))
                        ->from('shared_items_read_receipts as t2')
                        ->where('t2.user_id', $user_id);
                })
                ->get();
            $now_read_posts = convertStdClassObjToArray($unread_posts);
            DB::table('shared_items_read_receipts')->insert($now_read_posts);
            $res = array(
                'success' => true,
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

    public function flipApiClientAccess(Request $request)
    {
        try {
            $access_status = $request->input('access_status');
            $id = $request->input('id');
            $api_user = ApiUser::find($id);
            if ($api_user) {
                $api_user->access_status = $access_status;
                $api_user->save();
            }
            $res = array(
                'success' => true,
                'message' => 'Request executed successfully!!'
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

    public function mailTest()
    {
        Mail::to('ronokip55@gmail.com')->send(new ActivateActivateAccount('ronokip55@gmail.com', 'test', 'link'));
    }
    public function getOpenCwac(Request $req)
    {
        $user_id = $req->input('user_id');
        try {
            $qry = DB::table('cwac');
            if (isset($user_id) && $user_id != '') {
                $qry->whereNotIn('cwac.id', function ($query) use ($user_id) {
                    $query->select(DB::raw('user_cwac.cwac_id'))
                    ->from('user_cwac')
                    ->whereRaw('user_cwac.user_id=' . $user_id);
                });
            }
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $data = decryptArray($data);
            $res = array(
                'success' => true,
                'results' => $data,
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
/*
    public function getAssignedCwac(Request $req)
    {
        $user_id = $req->input('user_id');
        try {
            $qry = DB::table('user_cwac')
                ->join('cwac', 'user_cwac.cwac_id', '=', 'cwac.id')
                ->select('cwac.*')
                ->where('user_cwac.user_id', $user_id);
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $data = decryptArray($data);
            $res = array(
                'success' => true,
                'results' => $data,
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
*/
    public function getAssignedCwac(Request $req)
    {
        $user_id = $req->input('user_id');
        try {
            $qry = DB::table('user_cwac')
                ->join('cwac', 'user_cwac.cwac_id', '=', 'cwac.id')
                ->select('cwac.*');
            if (validateisNumeric($user_id)) {
                $qry->where('user_cwac.user_id', $user_id);
                $data = $qry->get();
                $data = convertStdClassObjToArray($data);
                $data = decryptArray($data);
            } else {
                $data = [];
            }
            $res = array(
                'success' => true,
                'results' => $data,
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

    public function getOpenSchools(Request $req)
    {
        $user_id = $req->input('user_id');
        try {
            $qry = DB::table('school_information');
            if (isset($user_id) && $user_id != '') {
                $qry->whereNotIn('school_information.id', function ($query) use ($user_id) {
                    $query->select(DB::raw('user_school.school_id'))
                    ->from('user_school')
                    ->whereRaw('user_school.user_id=' . $user_id);
                });
            }
            $data = $qry->select(DB::raw('school_information.*, CONCAT(school_information.name, \'-\', school_information.code) AS name'))->get();
            $data = convertStdClassObjToArray($data);
            $data = decryptArray($data);
            $res = array(
                'success' => true,
                'results' => $data,
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
/*
    public function getAssignedSchools(Request $req)
    {
        $user_id = $req->input('user_id');
        try {
            $qry = DB::table('school_information')
                ->join('user_school', 'user_school.school_id', '=', 'school_information.id')
                ->select('school_information.*')
                ->where('user_school.user_id', $user_id);
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $data = decryptArray($data);
            $res = array(
                'success' => true,
                'results' => $data,
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
*/
    public function getAssignedSchools(Request $req)
    {
        $user_id = $req->input('user_id');
        try {
            $qry = DB::table('school_information')
                ->join('user_school', 'user_school.school_id', '=', 'school_information.id')
                ->select('school_information.*');
            if (validateisNumeric($user_id)) {
                $qry->where('user_school.user_id', $user_id);
                $data = $qry->get();
                $data = convertStdClassObjToArray($data);
                $data = decryptArray($data);
            } else {
                $data = [];
            }
            $res = array(
                'success' => true,
                'results' => $data,
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
// ppmuserssetup controller methods start
    
    /**
     * Get all PPM users with their details
     * ppmuserssetup controller - getPpmUsers
     */
    public function getPpmUsers(Request $req)
    {
        Log::info("Fetching PPM users list");
        try {
            $qry = DB::table('ppmuserssetup_details as pum')
                ->leftJoin('users', 'users.id', '=', 'pum.user_id')
                ->leftJoin('titles', 'titles.id', '=', 'users.title_id')
                ->leftJoin('access_points', 'access_points.id', '=', 'users.access_point_id')
                ->leftJoin('user_images', 'users.id', '=', 'user_images.user_id')
                ->select(
                    'pum.*',
                    'users.id as user_id',
                    'titles.name as title',
                    'access_points.name as access_point_name',
                    DB::raw('CONCAT("/resources/images/user-profile/", user_images.saved_name) as profile_photo'),
                    DB::raw('CONCAT(decrypt(first_name)," ",decrypt(last_name)) as fullnames'),
                    DB::raw('decrypt(users.email) as email')
                )
                ->orderBy('fullnames', 'asc')
                ->limit(500);
            
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            
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

    /**
     * Get available users not already in PPM users setup
     * ppmuserssetup controller - getAvailablePpmUsers
     */
    public function getAvailablePpmUsers(Request $req)
    {
        Log::info("ppmuserssetup: Fetching available users for PPM setup");
        try {
            $query = $req->input('query');
            // Get users that are not in ppmuserssetup_details
            $qry = DB::table('users as u')
                ->leftJoin('ppmuserssetup_details as pum', function($join) {
                    $join->on('u.id', '=', 'pum.user_id');
                })
                ->whereNull('pum.id')
                ->select(
                    'u.id',
                    DB::raw('CONCAT(decrypt(u.first_name)," ",decrypt(u.last_name)," - ",decrypt(u.email)) as fullnames'),
                    DB::raw('decrypt(u.email) as email')
                );
                
                
            if (!empty($query)) {
                $qry->where(function($q) use ($query) {
                    // Use whereRaw so MySQL sees the function, not a column name
                    $q->whereRaw('decrypt(u.first_name) LIKE ?', ["%{$query}%"])
                    ->orWhereRaw('decrypt(u.last_name) LIKE ?', ["%{$query}%"])
                    ->orWhereRaw('decrypt(u.email) LIKE ?', ["%{$query}%"]);
                });
            }

            $qry->orderBy('fullnames', 'asc')
                ->limit(500);
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            
            Log::info("ppmuserssetup: Found " . count($data) . " available users");
            
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'All is well'
            );
        } catch (\Exception $e) {
            Log::error("ppmuserssetup: Error fetching available users - " . $e->getMessage());
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    /**
     * Save new PPM user
     * ppmuserssetup controller - saveNewPpmUser
     */
    public function saveNewPpmUser(Request $req)
    {
        Log::info("ppmuserssetup: Saving new PPM user");
        // Check if user is authenticated first
        if (!Auth::check()) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }
        $user_id = Auth::user()->id;
        
        try {
            $selected_user_id = $req->input('user_id');
            $account_type = $req->input('account_type');
            $has_kgs_app_access = $req->boolean('has_kgs_app_access');
            $has_ppm_app_access = $req->boolean('has_ppm_app_access');
            
            // Check if user already exists in ppmuserssetup_details
            $existing = DB::table('ppmuserssetup_details')
                ->where('user_id', $selected_user_id)
                ->first();
            
            if ($existing) {
                // Update existing
                DB::table('ppmuserssetup_details')
                    ->where('id', $existing->id)
                    ->update([
                        'account_type' => $account_type,
                        'has_kgs_app_access' => $has_kgs_app_access,
                        'has_ppm_app_access' => $has_ppm_app_access,
                        'updated_at' => Carbon::now(),
                        'updated_by' => $user_id
                    ]);
                $detail_id = $existing->id;
                Log::info("ppmuserssetup: Updated existing PPM user detail_id=$detail_id");
            } else {
                // Insert new
                $detail_id = DB::table('ppmuserssetup_details')->insertGetId([
                    'user_id' => $selected_user_id,
                    'account_type' => $account_type,
                    'has_kgs_app_access' => $has_kgs_app_access,
                    'has_ppm_app_access' => $has_ppm_app_access,
                    'created_at' => Carbon::now(),
                    'created_by' => $user_id
                ]);
                Log::info("ppmuserssetup: Created new PPM user detail_id=$detail_id");
            }
            
            $res = array(
                'success' => true,
                'detail_id' => $detail_id,
                'message' => 'PPM user created successfully'
            );
        } catch (\Exception $e) {
            Log::error("ppmuserssetup: Error saving new PPM user - " . $e->getMessage());
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    /**
     * Get PPM user details for a specific user
     * ppmuserssetup controller - getPpmUserDetail
     */
    public function getPpmUserDetail(Request $req)
    {
        $user_id = $req->input('user_id');
        try {
            $detail = DB::table('ppmuserssetup_details as pum')
                ->leftJoin('users', 'users.id', '=', 'pum.user_id')
                ->leftJoin('titles', 'titles.id', '=', 'users.title_id')
                ->leftJoin('access_points', 'access_points.id', '=', 'users.access_point_id')
                ->leftJoin('user_images', 'users.id', '=', 'user_images.user_id')
                ->select(
                    'pum.*',
                    'titles.name as title',
                    'access_points.name as access_point_name',
                    DB::raw('CONCAT("/resources/images/user-profile/", user_images.saved_name) as profile_photo'),
                    DB::raw('CONCAT(decrypt(first_name)," ",decrypt(last_name)) as fullnames'),
                    DB::raw('decrypt(users.email) as email')
                )
                ->where('pum.user_id', $user_id)
                ->first();
            
            if ($detail) {
                // Get allocated districts
                $districts = DB::table('ppmuserssetup_allocated_districts as pud')
                    ->join('districts', 'districts.id', '=', 'pud.district_id')
                    ->where('pud.ppm_user_detail_id', $detail->id)
                    ->select('districts.id', DB::raw('districts.name as district_name'))
                    ->get();
                
                // Get allocated schools
                $schools = DB::table('ppmuserssetup_allocated_schools as pus')
                    ->join('school_information', 'school_information.id', '=', 'pus.school_id')
                    ->where('pus.ppm_user_detail_id', $detail->id)
                    ->select('school_information.id', DB::raw('school_information.name as school_name'))
                    ->get();
                
                $res = array(
                    'success' => true,
                    'detail' => $detail,
                    'districts' => $districts,
                    'schools' => $schools,
                    'message' => 'All is well'
                );
            } else {
                $res = array(
                    'success' => true,
                    'detail' => null,
                    'districts' => [],
                    'schools' => [],
                    'message' => 'No PPM user detail found'
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

    /**
     * Save PPM user details (account type and app access)
     * ppmuserssetup controller - savePpmUserDetail
     */
    public function savePpmUserDetail(Request $req)
    {
        Log::info("ppmuserssetup: Saving PPM user detail");
        $user_id = Auth::user()->id;
        $account_type = $req->input('account_type');
        // Handle checkbox values - convert to boolean/integer
        $has_kgs_app_access = $req->input('has_kgs_app_access') == '1' || $req->input('has_kgs_app_access') === true || $req->input('has_kgs_app_access') === 'true' ? 1 : 0;
        $has_ppm_app_access = $req->input('has_ppm_app_access') == '1' || $req->input('has_ppm_app_access') === true || $req->input('has_ppm_app_access') === 'true' ? 1 : 0;
        
        Log::info("ppmuserssetup: Saving user detail - account_type=$account_type, kgs=$has_kgs_app_access, ppm=$has_ppm_app_access");
        
        try {
            $detail = DB::table('ppmuserssetup_details')
                ->where('user_id', $req->input('original_user_id'))
                ->first();
            
            if ($detail) {
                DB::table('ppmuserssetup_details')
                    ->where('id', $detail->id)
                    ->update([
                        'account_type' => $account_type,
                        'has_kgs_app_access' => $has_kgs_app_access,
                        'has_ppm_app_access' => $has_ppm_app_access,
                        'updated_at' => Carbon::now(),
                        'updated_by' => $user_id
                    ]);
                $detail_id = $detail->id;
            } else {
                $detail_id = DB::table('ppmuserssetup_details')->insertGetId([
                    'user_id' => $req->input('original_user_id'),
                    'account_type' => $account_type,
                    'has_kgs_app_access' => $has_kgs_app_access,
                    'has_ppm_app_access' => $has_ppm_app_access,
                    'created_at' => Carbon::now(),
                    'created_by' => $user_id
                ]);
            }

            // clean-up districts and schools if account type is school accountant and leave the first entry if multiple exist
            if ($account_type === 'school_accountant') {
                // Get allocated districts
                $allocatedDistricts = DB::table('ppmuserssetup_allocated_districts')
                    ->where('ppm_user_detail_id', $detail_id)
                    ->get();
                
                if ($allocatedDistricts->count() > 1) {
                    // Keep the first entry and delete the rest
                    $firstDistrictId = $allocatedDistricts->first()->id;
                    DB::table('ppmuserssetup_allocated_districts')
                        ->where('ppm_user_detail_id', $detail_id)
                        ->where('id', '!=', $firstDistrictId)
                        ->delete();
                }

                // Get allocated schools
                $allocatedSchools = DB::table('ppmuserssetup_allocated_schools')
                    ->where('ppm_user_detail_id', $detail_id)
                    ->get();
                
                if ($allocatedSchools->count() > 1) {
                    // Keep the first entry and delete the rest
                    $firstSchoolId = $allocatedSchools->first()->id;
                    DB::table('ppmuserssetup_allocated_schools')
                        ->where('ppm_user_detail_id', $detail_id)
                        ->where('id', '!=', $firstSchoolId)
                        ->delete();
                }
            }

            /** OLD COMPATIBILITY */ // Will be deleted by Jose
            DB::table('users')
                ->where('id', $req->input('original_user_id'))
                ->update([
                    'has_kgs_app_access' => $has_kgs_app_access,
                    'has_ppm_app_access' => $has_ppm_app_access,
                    'updated_at' => now(),
                    'updated_by' => $user_id
                ]);
            /* END OF OLD COMPATIBILITY */
            
            $res = array(
                'success' => true,
                'detail_id' => $detail_id,
                'message' => 'PPM user detail saved successfully'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    /**
     * Save allocated districts for a PPM user
     * ppmuserssetup controller - savePpmUserDistricts
     */
    public function savePpmUserDistricts(Request $req)
    {
        $user_id = $req->input('user_id');
        $district_ids = $req->input('district_ids', []);
        $current_user_id = $this->user_id;
        
        try {
            // Get the detail record
            $detail = DB::table('ppmuserssetup_details')
                ->where('user_id', $user_id)
                ->first();
            
            if (!$detail) {
                $res = array(
                    'success' => false,
                    'message' => 'PPM user detail not found. Please save user details first.'
                );
                return response()->json($res);
            }

            // Validate district accountant constraint (max 1 district)
            if ($detail->account_type === 'school_accountant' && count($district_ids) > 1) {
                $res = array(
                    'success' => false,
                    'message' => 'School accountants can only be assigned to one district.'
                );
                return response()->json($res);
            }
            
            $districtsInfo = DB::table('districts')
                ->whereIn('id', $district_ids)
                ->select('id', 'name as district_name')
                ->get()
                ->keyBy('id');

            
            DB::table('ppmuserssetup_allocated_districts')
                ->where('ppm_user_detail_id', $detail->id)
                ->delete();
            
            if (!empty($district_ids)) {
                $insertData = [];
                foreach ($district_ids as $id) {
                    $info = $districtsInfo->get($id);
                    if ($info) {
                        $insertData[] = [
                            'ppm_user_detail_id' => $detail->id,
                            'district_id'        => $id,
                            'district_name'      => $info->district_name, // Mapped to your new column
                            'created_at'         => Carbon::now(),
                            'updated_at'         => Carbon::now()
                        ];
                    }
                }
                
                if (!empty($insertData)) {
                    DB::table('ppmuserssetup_allocated_districts')->insert($insertData);
                }
            }

            /** OLD COMPATIBILITY */ // Jose delete this
            if (!empty($district_ids)) {
                $firstDistrictId = $district_ids[0];
                $districtInfo = $districtsInfo->get($firstDistrictId);
                
                if ($districtInfo) {
                    // Get first allocated school for the assigned string
                    $firstSchool = DB::table('ppmuserssetup_allocated_schools')
                        ->where('ppm_user_detail_id', $detail->id)
                        ->first();
                    
                    $assignedString = $firstSchool 
                        ? $firstSchool->school_id . ' - ' . $firstSchool->school_name . ' - ' . $districtInfo->district_name
                        : '0 - N/A - ' . $districtInfo->district_name;

                    DB::table('sa_app_user_details')->updateOrInsert(
                        ['user_id' => $user_id],
                        [
                            'uuid' => DB::table('users')->where('id', $user_id)->value('uuid'),
                            'district_assigned_id' => $firstDistrictId,
                            'district_assigned_string' => $districtInfo->district_name,
                            'school_assigned_string' => $assignedString,
                            'updated_at' => now()
                        ]
                    );
                }
            } 
            /* END OF OLD COMPATIBILITY */
            
            $res = array(
                'success' => true,
                'message' => 'Districts saved successfully'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    /**
     * Save allocated schools for a PPM user
     * ppmuserssetup controller - savePpmUserSchools
     */
    public function savePpmUserSchools(Request $req)
    {
        $user_id = $req->input('user_id');
        $school_ids = $req->input('school_ids', []);
        $current_user_id = $this->user_id;
        Log::info("ppmuserssetup: Saving schools for user_id=$user_id, school_ids=" . json_encode($school_ids));

        try {
            // Get the detail record
            $detail = DB::table('ppmuserssetup_details')
                ->where('user_id', $user_id)
                ->first();
            
            if (!$detail) {
                $res = array(
                    'success' => false,
                    'message' => 'PPM user detail not found. Please save user details first.'
                );
                return response()->json($res);
            }
            
            // Validate school accountant constraint (max 1 school)
            if ($detail->account_type === 'school_accountant' && count($school_ids) > 1) {
                $res = array(
                    'success' => false,
                    'message' => 'School accountants can only be assigned to one school.'
                );
                return response()->json($res);
            }
            
            $schoolsInfo = DB::table('school_information as s')
                ->leftJoin('districts as d', 's.district_id', '=', 'd.id')
                ->leftJoin('cwac as c', 's.cwac_id', '=', 'c.id')
                ->whereIn('s.id', $school_ids)
                ->select('s.id', DB::raw('s.name as school_name'), 's.code as emis_code', 's.cwac_id', 'c.name as cwac_name', 'd.name as district_name')
                ->get()
                ->keyBy('id'); 

            
            DB::table('ppmuserssetup_allocated_schools')
                ->where('ppm_user_detail_id', $detail->id)
                ->delete();
            
            
            if (!empty($school_ids)) {
                $insertData = [];
                foreach ($school_ids as $id) {
                    $info = $schoolsInfo->get($id);
                    if ($info) {
                        $insertData[] = [
                            'ppm_user_detail_id' => $detail->id,
                            'school_id'          => $id,
                            'school_name'        => $id.' - '.$info->school_name.' - '.$info->district_name,
                            'emis_code'          => $info->emis_code,
                            'cwac_id'            => $info->cwac_id,
                            'cwac_name'          => $info->cwac_name,
                            'created_at'         => Carbon::now(),
                            'updated_at'         => Carbon::now()
                        ];
                    }
                }
                
                if (!empty($insertData)) {
                    DB::table('ppmuserssetup_allocated_schools')->insert($insertData);
                }
            }

            /** OLD COMPATIBILITY */ // Jose delete this
            // Save first school to old sa_app_user_details table
            if (!empty($school_ids)) {
                $firstSchoolId = $school_ids[0];
                $schoolInfo = $schoolsInfo->get($firstSchoolId);
                
                if ($schoolInfo) {
                    $assignedString = $firstSchoolId . ' - ' . $schoolInfo->school_name. ' - ' . $schoolInfo->district_name;
                    
                    // Get cwac info
                    $cwacId = $schoolInfo->cwac_id ?? 0;
                    $cwacName = $schoolInfo->cwac_name ?: 'N/A';

                    DB::table('sa_app_user_details')->updateOrInsert(
                        ['user_id' => $user_id],
                        [
                            'uuid' => DB::table('users')->where('id', $user_id)->value('uuid'),
                            'school_assigned_id' => $firstSchoolId,
                            'school_assigned_emis' => $schoolInfo->emis_code,
                            'school_assigned_string' => $assignedString,
                            'school_cwac_id' => $cwacId,
                            'school_cwac_string' => $cwacName,
                            'updated_at' => now()
                        ]
                    );
                }
            }
            /* END OF OLD COMPATIBILITY */
            
            $res = array(
                'success' => true,
                'message' => 'Schools saved successfully'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    /**
     * Get available districts for PPM user assignment
     * ppmuserssetup controller - getPpmOpenDistricts
     */
    public function getPpmOpenDistricts(Request $req)
    {
        $user_id = $req->input('user_id');
        try {
            $qry = DB::table('districts');
            
            if (isset($user_id) && $user_id != '') {
                // Get the PPM user detail id
                $detail = DB::table('ppmuserssetup_details')
                    ->where('user_id', $user_id)
                    ->first();
                
                if ($detail) {
                    $qry->whereNotIn('districts.id', function ($query) use ($detail) {
                        $query->select(DB::raw('ppmuserssetup_allocated_districts.district_id'))
                            ->from('ppmuserssetup_allocated_districts')
                            ->where('ppmuserssetup_allocated_districts.ppm_user_detail_id', $detail->id);
                    });
                }
            }
            
            // Change name to district_name for frontend display
            $qry->select('districts.id', DB::raw('districts.name as district_name'));

            $data = $qry->orderBy('district_name', 'asc')->get();
            $data = convertStdClassObjToArray($data);
            
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

    /**
     * Get assigned districts for a PPM user
     * ppmuserssetup controller - getPpmAssignedDistricts
     */
    public function getPpmAssignedDistricts(Request $req)
    {
        $user_id = $req->input('user_id');
        try {
            $detail = DB::table('ppmuserssetup_details')
                ->where('user_id', $user_id)
                ->first();
            
            if (!$detail) {
                $res = array(
                    'success' => true,
                    'results' => [],
                    'message' => 'No PPM user detail found'
                );
                return response()->json($res);
            }
            
            $data = DB::table('ppmuserssetup_allocated_districts as pud')
                ->join('districts', 'districts.id', '=', 'pud.district_id')
                ->where('pud.ppm_user_detail_id', $detail->id)
                ->select('districts.id', DB::raw('districts.name as district_name'))
                ->get();
            $data = convertStdClassObjToArray($data);
            
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

    /**
     * Get available schools for PPM user assignment
     * ppmuserssetup controller - getPpmOpenSchools
     */
    public function getPpmOpenSchools(Request $req)
    {
        $user_id = $req->input('user_id');
        $district_id = $req->input('district_id'); // Optional filter by district
        
        try {
            $qry = DB::table('school_information');
            
            // Filter by district if provided
            if (isset($district_id) && $district_id != '') {
                $qry->where('district_id', $district_id);
            }
            
            if (isset($user_id) && $user_id != '') {
                // Get the PPM user detail id
                $detail = DB::table('ppmuserssetup_details')
                    ->where('user_id', $user_id)
                    ->first();
                
                if ($detail) {
                    $qry->whereNotIn('school_information.id', function ($query) use ($detail) {
                        $query->select(DB::raw('ppmuserssetup_allocated_schools.school_id'))
                            ->from('ppmuserssetup_allocated_schools')
                            ->where('ppmuserssetup_allocated_schools.ppm_user_detail_id', $detail->id);
                    });
                }
            }

            // Change name to school_name for frontend display
            $qry->select('school_information.id', DB::raw('school_information.name as school_name'), DB::raw('school_information.code as emis_code'));
            $qry->where('school_information.isDeleted', 0); // Only active schools
            
            $data = $qry->orderBy('school_name', 'asc')->get();
            $data = convertStdClassObjToArray($data);
            
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

    /**
     * Get assigned schools for a PPM user
     * ppmuserssetup controller - getPpmAssignedSchools
     */
    public function getPpmAssignedSchools(Request $req)
    {
        $user_id = $req->input('user_id');
        try {
            $detail = DB::table('ppmuserssetup_details')
                ->where('user_id', $user_id)
                ->first();
            
            if (!$detail) {
                $res = array(
                    'success' => true,
                    'results' => [],
                    'message' => 'No PPM user detail found'
                );
                return response()->json($res);
            }
            
            $data = DB::table('ppmuserssetup_allocated_schools as pus')
                ->join('school_information', 'school_information.id', '=', 'pus.school_id')
                ->where('pus.ppm_user_detail_id', $detail->id)
                ->select('school_information.id', DB::raw('school_information.name as school_name'), DB::raw('school_information.code as emis_code'))
                ->get();
            $data = convertStdClassObjToArray($data);
            
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

    // ppmuserssetup controller methods end

}
