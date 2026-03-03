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
                                        t7.name as gewel_programme,t8.dashboard_name"))
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
        //check that the user is on public IP
       if(checkForLocalIPs()){
           $res=array(
               'success'=>false,
               'message'=>'You seem to be accessing the system locally (via a local IP), this action require use of public IP. Kindly switch to the public IP!!'
           );
           return response()->json($res);
       }
        DB::transaction(function () use ($req, &$res) {
            $user_id = Auth::user()->id;

            $id = $req->input('id');
            $email = $req->input('email');
            $profile_url = $req->input('saved_name');
            $first_name = $req->input('first_name');
            $othernames = $req->input('last_name');

            $districts = $req->input('districts');
            $groups = $req->input('groups');
            $groups = json_decode($groups);
            $districts = json_decode($districts);
            $assigned_groups = array();
            $assigned_districts = array();

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
                'user_role_id' => $req->input('user_role_id')
            );

            $skip = $req->input('skip');
            $skipArray = explode(",", $skip);

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

                            $res = array(
                                'success' => true,
                                'message' => 'Data updated Successfully!!'
                            );
                        } else {
                            $res = $success;
                        }
                    }
                } else {
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

}
