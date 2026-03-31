<?php

namespace App\Http\Controllers;

use App\Modules\UserManagement\Entities\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PHLAK\SemVer;

class InitController extends Controller
{

    public function index()
    {
        try {
            DB::connection()->getPdo();
            if (DB::connection()->getDatabaseName()) {
                // echo "Yes! Successfully connected to the DB: " . DB::connection()->getDatabaseName();
            }
        } catch (\Exception $e) {
            die("<h4 style='text-align: center; color: red'>Could not connect to the database.  Please check your configuration!!</h4>
                 <p style='text-align: center; color: pink'>" . $e->getMessage() . "</p>");
        } catch (\Throwable $throwable) {
            die("<h4 style='text-align: center; color: red'>Could not connect to the database.  Please check your configuration!!</h4>
                 <p style='text-align: center; color: pink'>" . $throwable->getMessage() . "</p>");
        }
        $year = date('Y');
        $base_url = url('/');
        $current_term = DB::table('school_terms')->where('is_active', 1)->value('id');
        $weekly_border_plus = getWeeklyBordersTopUpAmount();
        $max_exam_fees = DB::table('weekly_borders_fees')->where('id', 1)->value('max_exam_fees');
        $default_dashboard = '{}';
        if (\Auth::check() || \Auth::viaRemember()) {
            $default_dashboard = $this->getUserDefaultDashboard(Auth::user()->id);
            $is_logged_in = true;
            $title = Title::findOrFail(\Auth::user()->title_id)->name;
            $title = aes_decrypt($title);
            $user_id = \Auth::user()->id;
            $title_id = \Auth::user()->title_id;
            $gender_id = \Auth::user()->gender_id;
            $first_name = aes_decrypt(\Auth::user()->first_name);
            $last_name = aes_decrypt(\Auth::user()->last_name);
            $email = aes_decrypt(\Auth::user()->email);
            $phone = aes_decrypt(\Auth::user()->phone);
            $mobile = aes_decrypt(\Auth::user()->mobile);
            $profile_pic_url = 'resources/images/placeholder.png';
            $saved_name = DB::table('user_images')->where('user_id', \Auth::user()->id)->value('saved_name');
            if ($saved_name != '') {
                $profile_pic_url = $base_url . '/resources/images/user-profile/' . $saved_name;
            }
            $access_point = DB::table('access_points')->where('id', \Auth::user()->access_point_id)->value('name');
            $role = DB::table('user_roles')->where('id', \Auth::user()->user_role_id)->value('name');
        } else {
            $is_logged_in = false;
            $user_id = '';
            $title_id = '';
            $gender_id = '';
            $title = '';
            $first_name = '';
            $last_name = '';
            $email = '';
            $phone = '';
            $mobile = '';
            $profile_pic_url = 'resources/images/placeholder.png';
            $access_point = '';
            $role = '';
        }
        $data['is_reset_pwd'] = false;
        $data['guid'] = '';
        $data['user_id'] = $user_id;
        $data['title_id'] = $title_id;
        $data['gender_id'] = $gender_id;
        $data['is_logged_in'] = $is_logged_in;
        $data['title'] = $title;
        $data['first_name'] = $first_name;
        $data['last_name'] = $last_name;
        $data['year'] = $year;
        $data['term'] = $current_term;
        $data['default_dashboard'] = $default_dashboard;
        $data['base_url'] = $base_url;
        $data['email'] = $email;
        $data['phone'] = $phone;
        $data['mobile'] = $mobile;
        $data['access_point'] = $access_point;
        $data['role'] = $role;
        $data['profile_pic_url'] = $profile_pic_url;
        $data['term_num_days'] = Config('constants.term_num_days');
        $data['active_tasks_height'] = Config('constants.active_tasks_height');
        $data['guidelines_height'] = Config('constants.guidelines_height');
        $data['threshhold_attendance_rate'] = Config('constants.threshhold_attendance_rate');
        $data['weekly_border_plus'] = $weekly_border_plus;
        $data['max_exam_fees'] = $max_exam_fees;
        $data['max_selection'] = 1200;//800; changed on 13th Jan 2020 consult Maureen/Frank
        // $data['max_selection_for_payment_request']=1500;//added by Job on 28/5/2022
        $data['max_selection_for_payment_request']=5000;//added by Job on 28/5/2022
        $data['componentsArray'] = getAssignedComponents($user_id);
        $data['max_excel_upload'] = Config('constants.max_excel_upload');
        $data['version'] = new SemVer\Version('v3.0.0');
        /*$version->incrementMajor();
        $version->incrementMinor();
        $version->incrementPatch();*/
        return view('init', $data);
    }

    public function getUserDefaultDashboard($user_id)
    {
        $dash_title = 'Dashboard';
        $dash_xtype = 'admindashboard';
        $dash_vtype = 'admindashboard';
        $dash_routeId = 'admindashboard';
        $dash_id = 1;
        $dashboard = DB::table('users as t1')
            ->join('menus as t2', 't1.dashboard_id', '=', 't2.id')
            ->select('t2.id', 't2.name', 't2.viewType', 't2.routeId')
            ->where('t1.id', $user_id)
            ->first();
        if ($dashboard) {
            $dash_title = $dashboard->name;
            $dash_xtype = $dashboard->viewType;
            $dash_vtype = $dashboard->viewType;
            $dash_routeId = $dashboard->routeId;
            $dash_id = $dashboard->id;
        }
        $dashboard_details = '{';
        $dashboard_details .= '"title": "' . $dash_title . '",';
        $dashboard_details .= '"xtype": "' . $dash_xtype . '",';
        $dashboard_details .= '"viewType": "' . $dash_vtype . '",';
        $dashboard_details .= '"routeId": "' . $dash_routeId . '",';
        $dashboard_details .= '"menu_id": ' . $dash_id . ',';
        $dashboard_details .= '"reorderable": false';
        $dashboard_details .= '}';
        return $dashboard_details;
    }

}
