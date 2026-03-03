<?php

namespace App\Http\Controllers;

//use App\Mail\ForgetPassword;
use App\ApiUser;
use App\Mail\ForgetPassword;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use PHLAK\SemVer;

class AuthController extends Controller
{
    protected $external_api_client;

    public function __construct()
    {
        $external_api_id = Config('constants.api.external_api_client_id');
        $this->external_api_client = DB::table('oauth_clients')->where('id', $external_api_id)->first();
    }

    public function handleLoginOld(Request $req)
    {
        $email = $req->input('email');
        $password = $req->input('password');
        $remember_me = $req->input('remember_me');
        $check_rem = false;
        if (is_numeric($remember_me) || !is_null($remember_me)) {
            $check_rem = true;
        }
        $encryptedEmail = aes_encrypt($email);
        $user = User::where('email', $encryptedEmail)->first();
        if (is_null($user) || $user == null || empty($user) || (!$user->exists())) {
            //log the login attempt
            $attemptLoginParams = array(
                'email' => $email,
                'password' => $password,
                'ip_address' => request()->ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'time' => Carbon::now()//date('Y-m-d H:i:s')
            );
            DB::table('login_attempts')->insert($attemptLoginParams);
            $res = array(
                'success' => false,
                'message' => 'Authentication Failed...User Not found!!'
            );
        } else {
            $uuid = $user->uuid;
            $user_id = $user->id;
            //check if account is blocked
            $is_account_blocked = DB::table('blocked_accounts')->where('account_id', $user_id)->first();
            if (!empty($is_account_blocked) || (!is_null($is_account_blocked))) {
                $res = array(
                    'success' => false,
                    'message' => 'Authentication Failed...This account is blocked from accessing the system!!'
                );
            } else {
                $authParams = array(
                    'email' => $encryptedEmail,
                    'password' => $password,
                    'uuid' => $uuid
                );
                if (Auth::attempt($authParams, $check_rem)) {
                    //check if this user have login failed attempts then clear
                    DB::table('failed_login_attempts')->where('account_id', Auth::user()->id)->delete();
                    $res = array(
                        'success' => true,
                        'user_name' => $email,
                        'message' => 'Login Successful. Redirecting...'
                    );
                } else {
                    //lets log the login attempts, for every attempted/failed login we increment the attempts counter
                    //first we get the number of attempts for this user within a 24hrs time span, beyond the time frame we reset the counter
                    //NB: max number of attempts is 5 after which we block the account
                    $attemptsCount = DB::table('failed_login_attempts')->where('account_id', $user_id)->first();
                    if (!empty($attemptsCount) || (!is_null($attemptsCount))) {
                        $no_of_attempts = $attemptsCount->attempts;
                        $time1 = Carbon::now();//date('Y-m-d H:i:s');
                        $time2 = $attemptsCount->time;
                        //now check for time span
                        $timeSpan = getTimeDiffHrs($time1, $time2);
                        if ($timeSpan > 24) {
                            //clear or rather update the attempt count to 1
                            $update = array(
                                'attempts' => 1
                            );
                            DB::table('failed_login_attempts')->where('account_id', $user_id)->update($update);
                            $no_of_attempts = 0;
                        } else {

                        }
                        //increment the counter
                        //if counter is 4 then this was the last attempt so block the account
                        if ($no_of_attempts == 4 || $no_of_attempts == '4' || $no_of_attempts > 4 || $no_of_attempts == 5 || $no_of_attempts == '5') {
                            $blockedAccountParams = array(
                                'account_id' => $user_id,
                                'email' => $email,
                                'date' => date('Y-m-d H:i:s'),
                                'reason' => 'Failed login attempts 5 times within 24hrs'
                            );
                            DB::table('blocked_accounts')->insert($blockedAccountParams);
                            $res = array(
                                'success' => false,
                                'message' => 'Authentication Failed...Your account has been blocked!!'
                            );
                        } else {
                            $res = array(
                                'success' => false,
                                'message' => 'Authentication Failed...You have ' . (5 - ($no_of_attempts + 1)) . ' attempts remaining!!'
                            );
                        }
                        //update
                        DB::table('failed_login_attempts')->where('account_id', $user_id)->update(array('attempts' => $no_of_attempts + 1));
                    } else {
                        //no attempts so fresh logging
                        $attempts = 1;
                        $loginAttemptsParams = array(
                            'account_id' => $user_id,
                            'email' => $email,
                            'ip_address' => request()->ip(),
                            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                            'attempts' => $attempts,
                            'time' => date('Y-m-d H:i:s')
                        );
                        DB::table('failed_login_attempts')->insert($loginAttemptsParams);
                        $res = array(
                            'success' => false,
                            'message' => 'Authentication Failed...You have ' . (5 - $attempts) . ' attempts remaining!!!!'
                        );
                    }
                }
            }
        }
        return response()->json($res);
    }

    // added by joseph june 2025
    public function handleLogin(Request $req)
    {
        $email = $req->input('email');
        $password = $req->input('password');
        $remember_me = $req->input('remember_me');
        $check_rem = false;
        if (is_numeric($remember_me) || !is_null($remember_me)) {
            $check_rem = true;
        }
        $encryptedEmail = aes_encrypt($email);
        $user = User::where('email', $encryptedEmail)->first();
        if (is_null($user) || $user == null || empty($user) || (!$user->exists())) {
            //log the login attempt
            $attemptLoginParams = array(
                'email' => $email,
                'password' => $password,
                'ip_address' => request()->ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'time' => Carbon::now()//date('Y-m-d H:i:s')
            );
            DB::table('login_attempts')->insert($attemptLoginParams);
            $res = array(
                'success' => false,
                'message' => 'Authentication Failed...User Not found!!',
                'code' => 404
            );
        } else {
            $uuid = $user->uuid;
            $user_id = $user->id;
            //check if account is blocked
            $is_account_blocked = DB::table('blocked_accounts')->where('account_id', $user_id)->first();
            if (!empty($is_account_blocked) || (!is_null($is_account_blocked))) {
                $res = array(
                    'success' => false,
                    'message' => 'Authentication Failed...This account is blocked from accessing the system!!',
                    'code' => 403
                );
            } else {
                $authParams = array(
                    'email' => $encryptedEmail,
                    'password' => $password,
                    'uuid' => $uuid
                );
                if (Auth::attempt($authParams, $check_rem)) {
                    DB::table('failed_login_attempts')->where('account_id', Auth::user()->id)->delete();
                    // Fetch the full user object
                    $loggedInUser = Auth::user();

                    $profileImage = DB::table('user_images')
                    ->where('user_id', $loggedInUser->id)
                    ->value('saved_name');

                    $baseUrl = url('/'); 
                    $profile_url = $profileImage 
                        ? $baseUrl . '/resources/images/user-profile/' . $profileImage 
                        : null; 

                    $userData = array(
                        'user_id' => $loggedInUser->id,
                        'first_name' => $loggedInUser->first_name,
                        'last_name' => $loggedInUser->last_name,
                        'email' => $email,
                        'phone' => $loggedInUser->phone,
                        'uuid' => $loggedInUser->uuid,
                        'profile_url' => $profile_url,
                        'allocated_district_id' => $loggedInUser->allocated_district_id,
                        'has_kgs_app_access' => $loggedInUser->has_kgs_app_access,
                        'is_app_admin' => $loggedInUser->is_app_admin
                    );
                    $res = array(
                        'success' => true,
                        'user_name' => $email,
                        'message' => 'Login Successful. Redirecting...',
                        'code' => 200,
                        'user' => $userData
                    );
                } else {
                    //lets log the login attempts, for every attempted/failed login we increment the attempts counter
                    //first we get the number of attempts for this user within a 24hrs time span, beyond the time frame we reset the counter
                    //NB: max number of attempts is 5 after which we block the account
                    $attemptsCount = DB::table('failed_login_attempts')->where('account_id', $user_id)->first();
                    if (!empty($attemptsCount) || (!is_null($attemptsCount))) {
                        $no_of_attempts = $attemptsCount->attempts;
                        $time1 = Carbon::now();//date('Y-m-d H:i:s');
                        $time2 = $attemptsCount->time;
                        //now check for time span
                        $timeSpan = getTimeDiffHrs($time1, $time2);
                        if ($timeSpan > 24) {
                            //clear or rather update the attempt count to 1
                            $update = array(
                                'attempts' => 1
                            );
                            DB::table('failed_login_attempts')->where('account_id', $user_id)->update($update);
                            $no_of_attempts = 0;
                        } else {
                        }
                        //increment the counter
                        //if counter is 4 then this was the last attempt so block the account
                        if ($no_of_attempts == 4 || $no_of_attempts == '4' || $no_of_attempts > 4 || $no_of_attempts == 5 || $no_of_attempts == '5') {
                            $blockedAccountParams = array(
                                'account_id' => $user_id,
                                'email' => $email,
                                'date' => date('Y-m-d H:i:s'),
                                'reason' => 'Failed login attempts 5 times within 24hrs'
                            );
                            DB::table('blocked_accounts')->insert($blockedAccountParams);
                            $res = array(
                                'success' => false,
                                'message' => 'Authentication Failed...Your account has been blocked!!',
                                'code' => 401
                            );
                        } else {
                            $res = array(
                                'success' => false,
                                'message' => 'Authentication Failed...You have ' . (5 - ($no_of_attempts + 1)) . ' attempts remaining!!',
                                'code' => 401
                            );
                        }
                        //update
                        DB::table('failed_login_attempts')->where('account_id', $user_id)->update(array('attempts' => $no_of_attempts + 1));
                    } else {
                        //no attempts so fresh logging
                        $attempts = 1;
                        $loginAttemptsParams = array(
                            'account_id' => $user_id,
                            'email' => $email,
                            'ip_address' => request()->ip(),
                            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                            'attempts' => $attempts,
                            'time' => date('Y-m-d H:i:s')
                        );
                        DB::table('failed_login_attempts')->insert($loginAttemptsParams);
                        $res = array(
                            'success' => false,
                            'message' => 'Authentication Failed...You have ' . (5 - $attempts) . ' attempts remaining!!!!',
                            'code' => 401
                        );
                    }
                }
            }
        }
        return response()->json($res);
    }
    public function handleLoginv2(Request $req)
    {
        $email = $req->input('email');
        $password = $req->input('password');
        $remember_me = $req->input('remember_me');
        $check_rem = false;
        if (is_numeric($remember_me) || !is_null($remember_me)) {
            $check_rem = true;
        }
        $encryptedEmail = aes_encrypt($email);
        $user = User::where('email', $encryptedEmail)->first();
        if (is_null($user) || $user == null || empty($user) || (!$user->exists())) {
            //log the login attempt
            $attemptLoginParams = array(
                'email' => $email,
                'password' => $password,
                'ip_address' => request()->ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'time' => Carbon::now()//date('Y-m-d H:i:s')
            );
            DB::table('login_attempts')->insert($attemptLoginParams);
            $res = array(
                'success' => false,
                'message' => 'Authentication Failed...User Not found!!',
                'code' => 404
            );
        } else {
            $uuid = $user->uuid;
            $user_id = $user->id;
            //check if account is blocked
            $is_account_blocked = DB::table('blocked_accounts')->where('account_id', $user_id)->first();
            if (!empty($is_account_blocked) || (!is_null($is_account_blocked))) {
                $res = array(
                    'success' => false,
                    'message' => 'Authentication Failed...This account is blocked from accessing the system!!',
                    'code' => 403
                );
            } else {
                $authParams = array(
                    'email' => $encryptedEmail,
                    'password' => $password,
                    'uuid' => $uuid
                );
                if (Auth::attempt($authParams, $check_rem)) {
                    DB::table('failed_login_attempts')->where('account_id', Auth::user()->id)->delete();
                    // Fetch the full user object
                    $loggedInUser = Auth::user();
                    $userData = array(
                        'user_id' => $loggedInUser->id,
                        'first_name' => $loggedInUser->first_name,
                        'last_name' => $loggedInUser->last_name,
                        'email' => $email,
                        'phone' => $loggedInUser->phone,
                        'uuid' => $loggedInUser->uuid,
                        'allocated_district_id' => $loggedInUser->allocated_district_id,
                        'has_kgs_app_access' => $loggedInUser->has_kgs_app_access,
                        'has_ppm_app_access' => $loggedInUser->has_ppm_app_access,
                        'is_app_admin' => $loggedInUser->is_app_admin
                    );
                    $res = array(
                        'success' => true,
                        'user_name' => $email,
                        'message' => 'Login Successful. Redirecting...',
                        'code' => 200,
                        'user' => $userData
                    );
                } else {
                    //lets log the login attempts, for every attempted/failed login we increment the attempts counter
                    //first we get the number of attempts for this user within a 24hrs time span, beyond the time frame we reset the counter
                    //NB: max number of attempts is 5 after which we block the account
                    $attemptsCount = DB::table('failed_login_attempts')->where('account_id', $user_id)->first();
                    if (!empty($attemptsCount) || (!is_null($attemptsCount))) {
                        $no_of_attempts = $attemptsCount->attempts;
                        $time1 = Carbon::now();//date('Y-m-d H:i:s');
                        $time2 = $attemptsCount->time;
                        //now check for time span
                        $timeSpan = getTimeDiffHrs($time1, $time2);
                        if ($timeSpan > 24) {
                            //clear or rather update the attempt count to 1
                            $update = array(
                                'attempts' => 1
                            );
                            DB::table('failed_login_attempts')->where('account_id', $user_id)->update($update);
                            $no_of_attempts = 0;
                        } else {
                        }
                        //increment the counter
                        //if counter is 4 then this was the last attempt so block the account
                        if ($no_of_attempts == 4 || $no_of_attempts == '4' || $no_of_attempts > 4 || $no_of_attempts == 5 || $no_of_attempts == '5') {
                            $blockedAccountParams = array(
                                'account_id' => $user_id,
                                'email' => $email,
                                'date' => date('Y-m-d H:i:s'),
                                'reason' => 'Failed login attempts 5 times within 24hrs'
                            );
                            DB::table('blocked_accounts')->insert($blockedAccountParams);
                            $res = array(
                                'success' => false,
                                'message' => 'Authentication Failed...Your account has been blocked!!',
                                'code' => 401
                            );
                        } else {
                            $res = array(
                                'success' => false,
                                'message' => 'Authentication Failed...You have ' . (5 - ($no_of_attempts + 1)) . ' attempts remaining!!',
                                'code' => 401
                            );
                        }
                        //update
                        DB::table('failed_login_attempts')->where('account_id', $user_id)->update(array('attempts' => $no_of_attempts + 1));
                    } else {
                        //no attempts so fresh logging
                        $attempts = 1;
                        $loginAttemptsParams = array(
                            'account_id' => $user_id,
                            'email' => $email,
                            'ip_address' => request()->ip(),
                            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                            'attempts' => $attempts,
                            'time' => date('Y-m-d H:i:s')
                        );
                        DB::table('failed_login_attempts')->insert($loginAttemptsParams);
                        $res = array(
                            'success' => false,
                            'message' => 'Authentication Failed...You have ' . (5 - $attempts) . ' attempts remaining!!!!',
                            'code' => 401
                        );
                    }
                }
            }
        }
        return response()->json($res);
    }

    
    // added by joseph june 2025
    public function getDecryptedUserDataByUuidMobile(Request $request)
    {
        $uuid = $request->query('uuid');
        if (!$uuid) {
            return response()->json([
                'success' => false,
                'message' => 'UUID is required.'
            ], 400);
        }
        try {
            $user = DB::table('users')
                ->select(
                    DB::raw("decrypt(first_name) as first_name"),
                    DB::raw("decrypt(last_name) as last_name"),
                    DB::raw("decrypt(phone) as phone")
                )
                ->where('uuid', $uuid)
                ->first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found for provided UUID.'
                ], 404);
            }
            return response()->json([
                'success' => true,
                'user' => $user
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching decrypted user by UUID: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving the user.'
            ], 500);
        }
    }

    public function forgotPasswordHandler(Request $req)
    {
        try {
            $email = $req->input('email');
            $encryptedEmail = aes_encrypt($email);
            //check if this mail is registered in the system
            $user = User::where('email', $encryptedEmail)->first();
            if (is_null($user)) {
                $res = array(
                    'success' => false,
                    'message' => 'Request Failed...This email address is not registered in the system!!'
                );
            } else {
                $user_id = $user->id;
                $guid = md5(uniqid());
                $pwdResetParams = array(
                    'user_id' => $user_id,
                    'guid' => $guid,
                    'date_generated' => Carbon::now()
                );
                DB::table('password_reset')->insert($pwdResetParams);
                if (is_connected()) {
                    //send the mail here
                    //$link = 'http://localhost/kgs_mis2017/resetPassword?guid=' . $guid;
                    $link = url('/') . '/resetPassword?guid=' . $guid;
                    Mail::to($email)->send(new ForgetPassword($email, $link));
                    if (count(Mail::failures()) > 0) {
                        $res = array(
                            'success' => false,
                            'message' => 'Problem was encountered while sending email. Please try again later!!'
                        );
                    } else {
                        $res = array(
                            'success' => true,
                            'message' => 'Password reset instructions sent to your email address!!'
                        );
                    }
                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'Whoops!! There is no internet connection. Check your connection then try again!!'
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

    public function passwordResetLoader(Request $req)
    {
        $guid = $req->guid;
        $data['is_reset_pwd'] = true;
        $data['guid'] = $guid;
        $data['user_id'] = '';
        $data['title_id'] = '';
        $data['gender_id'] = '';
        $data['is_logged_in'] = false;
        $data['title'] = '';
        $data['first_name'] = '';
        $data['last_name'] = '';
        $data['year'] = date('Y');
        $data['term'] = 1;
        $data['base_url'] = url('/');
        $data['email'] = '';
        $data['phone'] = '';
        $data['mobile'] = '';
        $data['access_point'] = '';
        $data['role'] = '';
        $data['profile_pic_url'] = '';
        $data['term_num_days'] = Config('constants.term_num_days');
        $data['active_tasks_height'] = Config('constants.active_tasks_height');
        $data['guidelines_height'] = Config('constants.guidelines_height');
        $data['threshhold_attendance_rate'] = Config('constants.threshhold_attendance_rate');
        $data['version'] = new SemVer\Version('v2.0.0');

        return view('init', $data);
    }

    function passwordResetHandler(Request $req)
    {
        $guid = $req->guid;
        $newPassword = $req->new_password;
        //check if guid exists
        $guid_exists = DB::table('password_reset')->where('guid', $guid)->first();
        if (is_null($guid_exists) || empty($guid_exists)) {
            $res = array(
                'success' => false,
                'message' => 'Your password reset token is invalid. Try again requesting for password reset!!'
            );
        } else {
            //check for time validity of the reset token
            $time1 = Carbon::now();
            $time2 = $guid_exists->date_generated;
            $user_id = $guid_exists->user_id;
            $time_diff = getTimeDiffHrs($time1, $time2);
            if ($time_diff > 24) {
                //the token has expired...delete
                DB::table('password_reset')->where('guid', $guid)->delete();
                $res = array(
                    'success' => false,
                    'message' => 'Your password reset token has expired. Try again requesting for password reset!!'
                );
            } else {
                //all is well..allow for password reset
                //check if the fetched user id really exists in users table
                $user_exists = User::find($user_id);
                if ($user_exists->count() > 0) {
                    $username = $user_exists->email;
                    $uuid = $user_exists->uuid;
                    $dms_id = $user_exists->dms_id;
                    $dms_pwd = md5($newPassword);
                    $hashedPassword = hashPwd($username, $uuid, $newPassword);
                    $user_exists->password = $hashedPassword;
                    if ($user_exists->save()) {
                        //save new dms password
                        $dms_db = DB::connection('dms_db');
                        $dms_db->table('tblusers')
                            ->where('id', $dms_id)
                            ->update(array('pwd' => $dms_pwd));
                        //delete the reset password token
                        DB::table('password_reset')->where('guid', $guid)->delete();
                        //also delete any tokens associated with this user
                        DB::table('password_reset')->where('user_id', $user_id)->delete();
                        $res = array(
                            'success' => true,
                            'message' => 'Congratulations...Your password was reset successfully!!'
                        );
                    } else {
                        $res = array(
                            'success' => false,
                            'message' => 'Sorry problem was encountered while saving your new password. Please try again!!'
                        );
                    }
                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'Your request couldn\'t be authenticated...User not found!!'
                    );
                }
            }
        }
        return response()->json($res);
    }

    public function updateUserPassword(Request $req)
    {
        try {
            $user_id = Auth::user()->id;
            $dms_id = Auth::user()->dms_id;
            $username = Auth::user()->email;
            $uuid = Auth::user()->uuid;
            $password = Auth::user()->password;
            $old_password = $req->input('old_password');
            $new_password = $req->input('new_password');
            $new_dms_pwd = md5($new_password);
            $encryptedNewPwd = hashPwd($username, $uuid, $new_password);
            //check if the provided old password is correct
            $encryptedOldPwd = hashPwd($username, $uuid, $old_password);
            if ($encryptedOldPwd == $password) {
                $user = User::find($user_id);
                $user->password = $encryptedNewPwd;
                if ($user->save()) {
                    //update dms password too
                    $dms_db = DB::connection('dms_db');
                    $dms_db->table('tblusers')
                        ->where('id', $dms_id)
                        ->update(array('pwd' => $new_dms_pwd));
                    $res = array(
                        'success' => true,
                        'message' => 'Password changed successfully!!'
                    );
                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'Problem was encountered while changing your password. Please try again later!!'
                    );
                }
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'Your old password is wrong. Try again!!'
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

    public function getUserAccessLevel(Request $req)
    {
        try {
            $menu_id = $req->menu_id;
            $superUserID = getSuperUserGroupId();
            //first get his/her groups
            $user_id = \Auth::user()->id;
            $groups = getUserGroups($user_id);
            //check if this user belongs to the super user group...if so then should have system full access
            if (in_array($superUserID, $groups)) {
                $access_level = 4;
            } else {
                $results = DB::table('permissions')
                    ->select(DB::raw('max(accesslevel_id) as highestAccessLevel'))
                    ->where('menu_id', $menu_id)
                    ->whereIn('permissions.group_id', $groups)
                    ->value('highestAccessLevel');
                if (is_null($results)) {
                    $access_level = 1;
                } else {
                    $access_level = convertStdClassObjToArray($results);
                }
            }
        } catch (\Exception $e) {
            $access_level = $e->getMessage();
        }
        return response()->json($access_level);
    }

    public function authenticateUserSession()
    {
        if (!\Auth::check()) {
            $res = array(
                'success' => false,
                'message' => 'Your session has expired. Please reload the application to continue!!'
            );
        } else {
            $res = array(
                'success' => true,
                'message' => 'Session still valid!!'
            );
        }
        return response()->json($res);
    }

    public function reValidateUser(Request $req)
    {
        $user_id = $req->input('user_id');
        $password = $req->input('password');
        try {
            $user = new User();
            $currentUser = $user->find($user_id);
            if (!is_null($currentUser)) {
                $email = $currentUser->email;
                $uuid = $currentUser->uuid;
                $authParams = array(
                    'email' => $email,
                    'password' => $password,
                    'uuid' => $uuid
                );
                if (\Auth::attempt($authParams)) {
                    $res = array(
                        'success' => true,
                        'message' => 'You were successfully authenticated, you can now proceed!!'
                    );
                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'Wrong credentials, please try again or reload the application from your browser refresh/reload icon/button or login with other credentials!!'
                    );
                }
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'User not found, please reload the application from your browser refresh/reload icon/button or login with other credentials!!'
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

    public function authenticateApiClient(Request $request)
    {
        $client_username = $request->input('client_username');
        $client_secret = $request->input('client_secret');
        $client_username = aes_encrypt($client_username);
        if (is_null($this->external_api_client)) {
            $res = array(
                'success' => false,
                'message' => 'API user not found!!'
            );
            return response()->json($res);
        }
        //check access status
        $apiUser = ApiUser::where(array('client_username' => $client_username))->first();
        if ($apiUser) {
            if ($apiUser->access_status != 1) {
                $res = array(
                    'success' => false,
                    'message' => 'Access Denied, contact MoGE KGS for guidance!!'
                );
                return response()->json($res);
            }
        }
        $request->request->add([
            'grant_type' => 'password',
            'provider' => 'apiusers',
            'client_id' => $this->external_api_client->id,
            'client_secret' => $this->external_api_client->secret,
            'username' => $client_username,
            'password' => $client_secret
        ]);
        $tokenRequest = $request->create('/oauth/token', 'POST', $request->all());
        $token = Route::dispatch($tokenRequest);
        return \response($token->getContent(), 200, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    public function logout2()
    {
        Auth::logout();
    }
   

    //job 12/02/2022
    public function logout()
    {
        $res = array(
            'success' => true,
        );
        // $id = isset(Auth::user()->id) ? Auth::user()->id : null;
        // if ($id) {
        //     DB::table('users')->where('id', $id)->update([
        //         "last_logout_time"=>Carbon::now(),
        //         "logout_type"=>"User"           
        //     ]);
        //     Auth::logout();
        // }
        // return response()->json($res);
        // Check if user is authenticated before accessing their id
        if (Auth::check()) {
            DB::table('users')->where('id', Auth::user()->id)->update([
                "last_logout_time"=>Carbon::now(),
                "logout_type"=>"User"
                
            ]);
        }
        Auth::logout();
        return response()->json($res);
        // return redirect('/login');        
    }
    public function createAdminPwd($username, $uuid, $pwd)
    {
        $username = aes_encrypt($username);
        echo 'username is: ' . $username;
        echo '<br>';
        echo 'password is: ' . hashPwd($username, $uuid, $pwd);
    }

}
