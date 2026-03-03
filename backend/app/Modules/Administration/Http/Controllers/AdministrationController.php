<?php

namespace App\Modules\Administration\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\Administration\Entities\ComponentsPermission;
use App\Modules\Administration\Entities\Menu;
use App\Modules\Administration\Entities\Permission;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdministrationController extends BaseController
{

    public function index()
    {
        return view('administration::index');
    }

    function getMenu($level = 0, $parent_id = 0)
    {
        $where = array(
            'menus.is_menu' => 1,
            'menus.is_disabled' => 0,
            'menus.level' => $level
        );
        $user_id = $this->user_id;
        $groups = getUserGroups($user_id);
        $superUserID = getSuperUserGroupId();
        if (in_array($superUserID, $groups)) {
            //superuser
        }
        $qry = DB::table('menus')
            ->leftJoin('parameters_setup AS t2','menus.param_setup_id','=','t2.id')
            ->distinct()
            ->select('menus.*','t2.primary_table AS param_table_name')
            ->where($where);

        $qry = $parent_id == 0 ? $qry->orderBy('menus.order_no') : $qry->where('menus.parent_id', $parent_id)->orderBy('menus.order_no');
        $qry = in_array($superUserID, $groups) == 1 ? $qry->whereRaw("1=1") : $qry->join('permissions', 'menus.id', '=', 'permissions.menu_id')
            ->where('permissions.status', 1)
            ->where('permissions.accesslevel_id', '<>', 1)
            ->whereIn('permissions.group_id', $groups);

        $menus = $qry->get();
        $menus = convertStdClassObjToArray($menus);
        return $menus;
    }

    function getSuperUserGroupId()
    {
        $allGroups = DB::table('groups')->get();
        $allGroups = convertStdClassObjToArray($allGroups);
        $decryptedGroups = decryptArray($allGroups);
        foreach ($decryptedGroups as $decryptedGroup) {
            if (stripos($decryptedGroup['name'], 'super') === false) {
                //  return 0;
            } else {
                return $decryptedGroup['id'];
            }
        }
        return 0;
    }

    public function getGrmNotifications()
    {
        $count = DB::table('grm_notifications')
            ->where('status', 1)
            ->count();
        return $count;
    }
    
    public function getAssetPastCheckOutDueCount()
    {
       $count= DB::table('ar_asset_checkout_details as t1')
       ->where('t1.due_date','<',Carbon::now()->format('Y-m-d'))
                        ->where('checkout_status','=','1')->count();
        return $count;

    }
    public function getAssetInsuranceExpiringCount()
    {
        $fifteen_days_to= DB::table('ar_insurance_policies as t1')
        ->where('t1.end_date','<=',  Carbon::now()->addDays(15)->format('Y-m-d'))//equal to no. days to expiry
        ->where('t1.end_date','>',  Carbon::now()->addDays(1)->format('Y-m-d'))
         ->count();
        $expired_ones= DB::table('ar_insurance_policies as t1')
        ->where('t1.end_date','<', now()->format('Y-m-d'))
         ->count();
        return  validateisNumeric($fifteen_days_to+$expired_ones)?$fifteen_days_to+$expired_ones:"";
      //initial
        // $count = DB::table('ar_insurance_policies as t1')
        // ->where('t1.end_date','>',  Carbon::now()->addDays(15)->format('Y-m-d'))//equal to no. days to expiry
        // ->orwhere("t1.end_date","<",Carbon::now())
        // ->count();
        // return $count;
    }
    public function getFundsExpiringCount()
    {

        $fifteen_days_to= DB::table('ar_funding_details as t1')
        ->where('t1.end_date','<=',  Carbon::now()->addDays(15)->format('Y-m-d'))//equal to no. days to expiry
        ->where('t1.end_date','>',  Carbon::now()->addDays(1)->format('Y-m-d'))
         ->count();
        $expired_ones= DB::table('ar_funding_details as t1')
        ->where('t1.end_date','<', now()->format('Y-m-d'))
         ->count();
         return  validateisNumeric($fifteen_days_to+$expired_ones)?$fifteen_days_to+$expired_ones:"";
        //initial
        // $count = DB::table('ar_funding_details as t1')
        // ->where('t1.end_date','>',  Carbon::now()->addDays(15)->format('Y-m-d'))//equal to no. days to expiry
        // ->orwhere("t1.end_date","<",Carbon::now())->count();
        // return $count;
   
    }
    public function getAssetDueMaintainanceCount()
    {
          $due_count= DB::table('ar_asset_maintainance as t1')
                  ->whereDate('t1.maintainance_due_date', '=', now()->format('Y-m-d'))
                  ->where('t1.maintainance_status',0)->count();
                  return $due_count;

    }
    public function getAssetsOverdueMaintainanceCount()
    {
        $overdue_count= DB::table('ar_asset_maintainance as t1')
      ->whereDate('t1.maintainance_due_date', '<', now()->format('Y-m-d'))
      ->where('t1.maintainance_status',0)->count();
      return $overdue_count;
    }
    public function  getRepairDueCount()
    {
        $repair_count= DB::table('ar_asset_repairs as t1')
                  ->whereDate('t1.scheduled_repair_date', '=', now()->format('Y-m-d'))
              ->where('t1.repair_status',0)->count();
        return $repair_count;

    }
    public function getRepairOverdueCount()
    {
        $repair_count= DB::table('ar_asset_repairs as t1')
        ->whereDate('t1.scheduled_repair_date', '<', now()->format('Y-m-d'))
        ->where('t1.repair_status',0)->count();
        return $repair_count;
    }
    public function getExpiringWarrantyCount()
    {   

        $fifteen_days_to= DB::table('ar_asset_warranties as t1')
        ->where('t1.expiration_date','<=',  Carbon::now()->addDays(15)->format('Y-m-d'))//equal to no. days to expiry
        ->where('t1.expiration_date','>',  Carbon::now()->addDays(1)->format('Y-m-d'))
            ->count();
        $expired_ones= DB::table('ar_asset_warranties as t1')
        //->where('t1.expiration_date','>',  Carbon::now()->addDays(1)->format('Y-m-d'))
        ->where('t1.expiration_date','<', now()->format('Y-m-d'))
        ->count();
        
            //initial
            // $count = DB::table('ar_asset_warranties as t1')
            //         ->where('t1.expiration_date','>',  Carbon::now()->addDays(10)->format('Y-m-d'))//equal to no. days to expiry
            //         ->whereDate('t1.expiration_date', '>', now()->format('Y-m-d'))
            //        ->count();
            return  validateisNumeric($fifteen_days_to+$expired_ones)?$fifteen_days_to+$expired_ones:"";
    }
    public function getNavigationItems()
    {
        $row = $this->getMenu(0, 0);

        $menus = "[";
        if (count($row)) {
            $menu_count = count($row);
            $menu_counter = 0;

            foreach ($row as $item) {
                $menu_counter++;
                $id = $item['id'];
                $name = $item['name'];
                $parent_module_name = $name;
                $viewType = $item['viewType'];
                $iconCls = $item['iconCls'];
                $routeId = $item['routeId'];
                $isParam = $item['is_param'];
                $paramSetupId = $item['param_setup_id'];
                $paramTableName = $item['param_table_name'];
                $access_level = $this->getMenuAccessLevel($id);
                $text = '<span title="' . $name . '">' . $name . '</span>';
                $menus .= "{";
                $menus .= "text: '" . $text . "',";
                $menus .= "name: '" . $name . "',";
                $menus .= "module_name: '" . $parent_module_name . "',";
                $menus .= "iconCls: '" . $iconCls . "',";
                $menus .= "menu_id: " . $id . ",";
                $menus .= "param_setup_id: '" . $paramSetupId . "',";
                $menus .= "param_table_name: '" . $paramTableName . "',";
                $menus .= "is_param: " . $isParam . ",";
                $menus .= "access_level: " . $access_level . ",";
                $menus .= "viewType: '" . $viewType . "',";
                $menus .= "routeId: '" . $routeId . "',";

                $children = $this->getMenu(1, $id);
                if (count($children) > 0) {
                    $menus .= "selectable: false,";
                    $children_count = count($children);
                    $children_counter = 0;
                    $menus .= "children: [";
                    foreach ($children as $child) {
                        $children_counter++;
                        $child_id = $child['id'];
                        $child_name = $child['name'];
                        $child_module_name = $parent_module_name . ' >> ' . $child_name;
                        $child_viewType = $child['viewType'];
                        $child_iconCls = 'x-fa fa-angle-double-right';//$child['iconCls'];
                        $child_route = $child['routeId'];
                        $child_isParam = $child['is_param'];
                        $child_paramSetupId = $child['param_setup_id'];
                        $child_paramTableName = $child['param_table_name'];
                        $child_text = '<span title="' . $child_name . '">' . $child_name . '</span>';
                        $child_access_level = $this->getMenuAccessLevel($child_id);
                        if ($child_id == 216) {
                            $child_name = 'Payment-' . $child_name;
                        }
                        if ($child_id == 246) {
                            $child_name = 'GRM-' . $child_name;
                        }
                
                        if ($child_id == 288) {
                            $child_name = 'M&E-' . $child_name;
                        }
                        if ($child_id == 302) {
                            $child_name = 'CMS-' . $child_name;
                        }
                        if ($child_id == 343) {
                            $child_name = 'Enrolment-' . $child_name;
                        }
                        if($child_id == 351)
                        {
                            $child_name = 'Asset Register-' .$child_name;
                        }
                        if($child_id == 395)
                        {
                            $child_name = 'Asset Register-' .$child_name;

                        }
                        if($child_id == 947)
                        {
                            $child_name = 'Stores-' .$child_name;

                        }
                        if ($child_id == 269) {
                            $notificationCount = $this->getGrmNotifications();
                            if($notificationCount>0){
                                $child_text = $child_text . '<span class="badge">' . $notificationCount . '</span>';
                                $child_name = $child_name . '<span class="badge">' . $notificationCount . '</span>';
                            }
                        }
                        $menus .= "{";
                        $menus .= "text: '" . $child_text . "',";
                        $menus .= "name: '" . $child_name . "',";
                        $menus .= "module_name: '" . $child_module_name . "',";
                        $menus .= "iconCls: '" . $child_iconCls . "',";
                        $menus .= "viewType: '" . $child_viewType . "',";
                        $menus .= "menu_id: " . $child_id . ",";
                        $menus .= "param_setup_id: '" . $child_paramSetupId . "',";
                        $menus .= "param_table_name: '" . $child_paramTableName . "',";
                        $menus .= "is_param: " . $child_isParam . ",";
                        $menus .= "access_level: " . $child_access_level . ",";
                        $menus .= "routeId: '" . $child_route . "',";

                        //level 2 menu items
                        $grandchildren = $this->getMenu(2, $child_id);
                        if (count($grandchildren) > 0) {
                            $menus .= "selectable: false,";
                            $grandchildren_count = count($grandchildren);
                            $grandchildren_counter = 0;
                            $menus .= "children: [";
                            foreach ($grandchildren as $grandchild) {
                                $grandchildren_counter++;
                                $grandchild_id = $grandchild['id'];
                                $grandchild_name = $grandchild['name'];
                                $grandchild_module_name = $child_module_name . ' >> ' . $grandchild_name;
                                $grandchild_viewType = $grandchild['viewType'];
                                $grandchild_iconCls = 'x-fa fa-arrow-circle-right';//$grandchild['iconCls'];
                                $grandchild_route = $grandchild['routeId'];
                                $grandchild_isParam = $grandchild['is_param'];
                                $grandchild_paramSetupId = $grandchild['param_setup_id'];
                                $grandchild_paramTableName = $grandchild['param_table_name'];
                                $grandchild_text = '<span title="' . $grandchild_name . '">' . $grandchild_name . '</span>';
                                $grandchild_access_level = $this->getMenuAccessLevel($grandchild_id);

                                if ($child_id == 87) {
                                    $grandchild_name = 'Community Based-' . $grandchild_name;
                                }
                                if ($child_id == 88) {
                                    $grandchild_name = 'In School-' . $grandchild_name;
                                }
                                if ($child_id == 89) {
                                    $grandchild_name = 'Exam Classes-' . $grandchild_name;
                                }
                                if ($child_id == 152) {
                                    $grandchild_name = 'Promotion-' . $grandchild_name;
                                }
                                
                                if ($grandchild_id == 145) {
                                    $grandchild_name = 'Sch Transfer-' . $grandchild_name;
                                }
                                if ($grandchild_id == 171) {
                                    $grandchild_name = 'Monitoring-' . $grandchild_name;
                                }
                                if ($grandchild_id == 172) {
                                    $grandchild_name = 'Monitoring-' . $grandchild_name;
                                }
                                //assets past due
                                if ($grandchild_id == 377) {
                                    $notificationCount = $this->getAssetPastCheckOutDueCount();
                                    if($notificationCount>0){
                                        $grandchild_text = '<span class="badge">'. $notificationCount . '</span>'. $grandchild_text;
                                        $grandchild_name = '<span class="badge">'. $notificationCount . '</span>'. $grandchild_name;
                                    }
                                }
                                 //insurances expiring
                                 if ($grandchild_id == 379) {
                                    $notificationCount = $this->getAssetInsuranceExpiringCount();
                                    if($notificationCount>0){
                                        $grandchild_text = '<span class="badge">'. $notificationCount . '</span>'. $grandchild_text;
                                        $grandchild_name = '<span class="badge">'. $notificationCount . '</span>'. $grandchild_name;
                                    }
                                }
                                   //funds expiring
                                   if ($grandchild_id == 378) {
                                    $notificationCount = $this->getFundsExpiringCount();
                                    if($notificationCount>0){
                                        $grandchild_text = '<span class="badge">'. $notificationCount . '</span>'. $grandchild_text;
                                        $grandchild_name = '<span class="badge">'. $notificationCount . '</span>'. $grandchild_name;
                                    }
                                }

                                //expiring warranties
                                      if ($grandchild_id == 380) {
                                        $notificationCount = $this->getExpiringWarrantyCount();
                                        if($notificationCount>0){
                                            $grandchild_text = '<span class="badge">'. $notificationCount . '</span>'. $grandchild_text;
                                            $grandchild_name = '<span class="badge">'. $notificationCount . '</span>'. $grandchild_name;
                                        }
                                    }
                                
                                //due maintainance
                                if ($grandchild_id == 391) {
                                    $notificationCount = $this->getAssetDueMaintainanceCount();
                                    if($notificationCount>0){
                                        $grandchild_text = '<span class="badge">'. $notificationCount . '</span>'. $grandchild_text;
                                        $grandchild_name = '<span class="badge">'. $notificationCount . '</span>'. $grandchild_name;
                                    }
                                }
                                   
                             
                                //overdue maintainance
                                if ($grandchild_id == 392) {
                                    $notificationCount = $this->getAssetsOverdueMaintainanceCount();
                                    if($notificationCount>0){
                                        $grandchild_text = '<span class="badge">'. $notificationCount . '</span>'. $grandchild_text;
                                        $grandchild_name = '<span class="badge">'. $notificationCount . '</span>'. $grandchild_name;
                                    }
                                }
                                 //due repair
                                 if ($grandchild_id == 393) {
                                    $notificationCount = $this->getRepairDueCount();
                                    if($notificationCount>0){
                                        $grandchild_text = '<span class="badge">'. $notificationCount . '</span>'. $grandchild_text;
                                        $grandchild_name = '<span class="badge">'. $notificationCount . '</span>'. $grandchild_name;
                                    }
                                }
                                //overdue repair
                                if ($grandchild_id == 394) {
                                    $notificationCount = $this->getRepairOverdueCount();
                                    if($notificationCount>0){
                                        $grandchild_text = '<span class="badge">'. $notificationCount . '</span>'. $grandchild_text;
                                        $grandchild_name = '<span class="badge">'. $notificationCount . '</span>'. $grandchild_name;
                                    }
                                }
                                //Finance activities Due
                                 if ($grandchild_id == 603) {
                                    $notificationCount = $this->getfinancialActivitiesDueCount();
                                    if($notificationCount>0){
                                        $grandchild_text = '<span class="badge">'. $notificationCount . '</span>'. $grandchild_text;
                                        $grandchild_name = '<span class="badge">'. $notificationCount . '</span>'. $grandchild_name;
                                    }
                                }
                               
                                $menus .= "{";
                                $menus .= "text: '" . $grandchild_text . "',";
                                $menus .= "name: '" . $grandchild_name . "',";
                                $menus .= "module_name: '" . $grandchild_module_name . "',";
                                $menus .= "iconCls: '" . $grandchild_iconCls . "',";
                                $menus .= "viewType: '" . $grandchild_viewType . "',";
                                $menus .= "menu_id: " . $grandchild_id . ",";
                                $menus .= "param_setup_id: '" . $grandchild_paramSetupId . "',";
                                $menus .= "param_table_name: '" . $grandchild_paramTableName . "',";
                                $menus .= "is_param: " . $grandchild_isParam . ",";
                                $menus .= "access_level: " . $grandchild_access_level . ",";
                                $menus .= "routeId: '" . $grandchild_route . "',";
                                $menus .= "leaf: true";

                                if ($grandchildren_counter == $grandchildren_count) {
                                    //Last Child in this level. Level=2
                                    $menus .= "}";
                                } else {
                                    $menus .= "},";
                                }
                            }
                            $menus .= "]";
                        } else {
                            $menus .= "leaf: true";
                        }
                        if ($children_counter == $children_count) {
                            //Last Child in this level. Level=1
                            $menus .= "}";
                        } else {
                            $menus .= "},";
                        }
                    }
                    $menus .= "]";

                } else {
                    //$menus.="viewType: '".$viewType."',";
                    $menus .= "leaf: true";
                }

                if ($menu_counter == $menu_count) {
                    $menus .= "}";
                } else {
                    $menus .= "},";
                }
            }
        }
        $menus .= "]";
        echo $menus;
    }

    public function getMenuAccessLevel($menu_id)
    {
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
                $access_level = $results;
            }
        }
        return $access_level;
    }

    public function getMenuAccessLevelData(Request $request)
    {
        try {
            $page = $request->input('page', 1);
            $start = $request->input('start', 0);
            $limit = $request->input('limit', 25);
            
            $superUserID = getSuperUserGroupId();
            $user_id = \Auth::user()->id;
            $groups = getUserGroups($user_id);
            
            $qry = DB::table('menus')
                ->leftJoin('permissions', function($join) use ($groups) {
                    $join->on('menus.id', '=', 'permissions.menu_id')
                         ->whereIn('permissions.group_id', $groups);
                })
                ->leftJoin('parameters_setup as t2', 'menus.param_setup_id', '=', 't2.id')
                ->select([
                    'menus.id',
                    'menus.name',
                    'menus.description',
                    'menus.level',
                    'menus.parent_id',
                    'menus.order_no',
                    'menus.url',
                    't2.param_table_name',
                    DB::raw('COALESCE(MAX(permissions.accesslevel_id), 1) as access_level')
                ])
                ->where('menus.is_menu', 1)
                ->where('menus.is_disabled', 0)
                ->groupBy([
                    'menus.id',
                    'menus.name',
                    'menus.description',
                    'menus.level',
                    'menus.parent_id',
                    'menus.order_no',
                    'menus.url',
                    't2.param_table_name'
                ]);
            
            // If not super user, filter by permissions
            if (!in_array($superUserID, $groups)) {
                // Already filtered by the join condition
            }
            
            $total = $qry->count();
            
            if ($limit > 0) {
                $results = $qry->skip($start)->take($limit)->get();
            } else {
                $results = $qry->get();
            }
            
            $res = array(
                'success' => true,
                'results' => $results,
                'total' => $total
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

    public function saveAdminCommonData(Request $req)
    {
        try {
            $user_id = \Auth::user()->id;
            $post_data = $req->all();
            $table_name = $post_data['table_name'];
            $id = $post_data['id'];
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

    public function saveGuidelinesdetails(Request $req)
    {
        $user_id = \Auth::user()->id;
        $post_data = $req->all();
        $table_name = 'system_guidelines';
        $id = $post_data['id'];
        unset($post_data['_token']);
        $menu_id = $post_data['menu_id'];
        $table_data = encryptArray($post_data, array('menu_id', 'id', 'order_no'));
        //add extra params

        $table_data['menu_id'] = $menu_id;
        $where = array(
            'id' => $id
        );
        if (validateisNumeric($id)) {
            if (recordExists($table_name, $where)) {
                unset($table_data['created_at']);
                unset($table_data['created_by']);
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $previous_data = getPreviousRecords($table_name, $where);
                $success = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
            }
        } else {
            $success = insertRecord($table_name, $table_data, $user_id);
        }

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
        return response()->json($res);

    }

    public function addUserGroup(Request $req)
    {
        $user_id = $this->user_id;
        $id = $req->input('id');
        $name = $req->input('name');
        $access_point = $req->input('group_owner_level');
        $description = $req->input('description');
        $res = array();
        DB::transaction(function () use (&$res, $id, $user_id, $name, $description, $access_point) {
            try {
                $params = array(
                    'name' => $name,
                    'description' => $description,
                    'group_owner_level' => $access_point
                );
                $dms_params = array(
                    'name' => $name,
                    'comment' => $description
                );
                if (is_numeric($id) && $id != '') {
                    $where = array('id' => $id);
                    $prev_data = getPreviousRecords('groups', $where);
                    $dms_id = $prev_data[0]['dms_id'];
                    $res=updateRecord('groups', $prev_data, $where, $params, $user_id);
                    $this->updateDMSGroup($dms_id, $dms_params);
                } else {
                    $res = insertRecord('groups', $params, $user_id);
                    if($res['success']==true){
                        $group_id=$res['record_id'];
                        $dms_id = $this->addGroupToDMS($name, $description);
                        if (is_numeric($dms_id)) {
                            DB::table('groups')
                                ->where('id', $group_id)
                                ->update(array('dms_id' => $dms_id));
                        }
                    }
                }
                $res = array(
                    'success' => true,
                    'message' => 'Group added successfully!!'
                );
            } catch (\Exception $e) {
                $res = array(
                    'success' => false,
                    'message' => $e->getMessage()
                );
            }
        }, 5);
        return response()->json($res);
    }

    public function deleteUserGroup(Request $req)
    {
        $user_id = \Auth::user()->id;
        $id = $req->input('id');
        $prev_data = getPreviousRecords('groups', array('id' => $id));
        $dms_id = $prev_data[0]['dms_id'];
        $success = deleteRecord('groups', $prev_data, array('id' => $id), $user_id);
        if ($success['success'] == true) {
            $dms_db = DB::connection('dms_db');
            $dms_db->table('tblgroups')
                ->where('id', $dms_id)
                ->delete();
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

    public function addGroupToDMS($groupname, $description)
    {
        $dms_db = DB::connection('dms_db');
        $params = array(
            'name' => $groupname,
            'comment' => $description
        );
        $dms_id = $dms_db->table('tblgroups')
            ->insertGetId($params);
        return $dms_id;
    }

    public function updateDMSGroup($dms_id, $params)
    {
        $dms_db = DB::connection('dms_db');
        $where = array(
            'id' => $dms_id
        );
        $exists = $dms_db->table('tblgroups')
            ->where($where)
            ->first();
        if (is_null($exists)) {
            $dms_db->table('tblgroups')
                ->insert($params);
        } else {
            $dms_db->table('tblgroups')
                ->where($where)
                ->update($params);
        }
        return $dms_id;
    }

    public function getAdminParam($model_name)
    {
        try {
            $model = 'App\\Modules\\administration\\Entities\\' . $model_name;
            $results = $model::all()->toArray();
            $results = decryptArray($results);
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

    public function getAdminModuleParamFromTable(Request $request)
    {
        try {
            $table_name = $request->input('table_name');
            $filters = $request->input('filters');
            $filters = (array)json_decode($filters);
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

    public function deleteAdminRecord(Request $req)
    {
        $record_id = $req->input('id');
        $table_name = $req->input('table_name');
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

    public function getSystemMenu($level = 0, $parent_id = 0)
    {
        $level = $level;
        $parent_id = $parent_id;
        $qry = DB::table('menus')
            ->where('level', $level);
        $qry = $parent_id == 0 ? $qry->orderBy('order_no') : $qry->where('parent_id', $parent_id)->orderBy('order_no');
        $menus = $qry->get();
        $menus = convertStdClassObjToArray($menus);
        return $menus;
    }

    public function getSystemMenus()
    {
        $row = $this->getSystemMenu(0, 0);
        $menus = '{"menus": "."';
        $menus .= ',';
        $menus .= '"children": [';
        if (count($row)) {
            $menu_count = count($row);
            $menu_counter = 0;

            foreach ($row as $item) {
                $menu_counter++;
                $id = $item['id'];
                $name = $item['name'];
                $parent_module_name = $name;
                $route_id = $item['routeId'];
                $icon = $item['iconCls'];
                $level = $item['level'];
                $order_no = $item['order_no'];
                $viewType = $item['viewType'];
                $is_dashboard = $item['is_dashboard'];
                $dashboard_name = $item['dashboard_name'];
                $is_menu = $item['is_menu'];
                $is_disabled = $item['is_disabled'];
                $is_param = $item['is_param'];
                $paramSetupId = $item['param_setup_id'];

                $menus .= '{';
                $menus .= '"id": ' . $id . ',';
                $menus .= '"menu_id": ' . $id . ',';
                $menus .= '"name": "' . $name . '",';
                $menus .= '"module_name": "' . $parent_module_name . '",';
                $menus .= '"routeId": "' . $route_id . '",';
                $menus .= '"iconCls": "' . $icon . '",';
                $menus .= '"level": ' . $level . ',';
                $menus .= '"order_no": ' . $order_no . ',';
                $menus .= '"viewType": "' . $viewType . '",';
                $menus .= '"is_dashboard": ' . $is_dashboard . ',';
                $menus .= '"dashboard_name": "' . $dashboard_name . '",';
                $menus .= '"is_menu": ' . $is_menu . ',';
                $menus .= '"is_disabled": ' . $is_disabled . ',';
                $menus .= '"is_param": ' . $is_param . ',';
                $menus .= '"param_setup_id": "' . $paramSetupId . '",';

                $children = $this->getSystemMenu(1, $id);
                if (count($children) > 0) {
                    $children_count = count($children);
                    $children_counter = 0;
                    $menus .= '"expanded": false,';
                    $menus .= '"children": [';
                    foreach ($children as $child) {
                        $children_counter++;
                        $child_id = $child['id'];
                        $child_name = $child['name'];
                        $child_module_name = $child_name;
                        $module_name = $parent_module_name . ' >> ' . $child_module_name;
                        $child_route_id = $child['routeId'];
                        $child_icon = 'x-fa fa-angle-double-right';//$child['iconCls'];
                        $child_level = $child['level'];
                        $child_order_no = $child['order_no'];
                        $child_viewType = $child['viewType'];
                        $child_is_dashboard = $child['is_dashboard'];
                        $child_dashboard_name = $child['dashboard_name'];
                        $child_is_menu = $child['is_menu'];
                        $child_is_disabled = $child['is_disabled'];
                        $child_is_param = $child['is_param'];
                        $child_paramSetupId = $child['param_setup_id'];
                        $child_parent_id = $child['parent_id'];

                        $menus .= '{';
                        $menus .= '"id": ' . $child_id . ',';
                        $menus .= '"menu_id": ' . $child_id . ',';
                        $menus .= '"name": "' . $child_name . '",';
                        $menus .= '"module_name": "' . $module_name . '",';
                        $menus .= '"routeId": "' . $child_route_id . '",';
                        $menus .= '"iconCls": "' . $child_icon . '",';
                        $menus .= '"level": ' . $child_level . ',';
                        $menus .= '"order_no": ' . $child_order_no . ',';
                        $menus .= '"viewType": "' . $child_viewType . '",';
                        $menus .= '"is_dashboard": ' . $child_is_dashboard . ',';
                        $menus .= '"dashboard_name": "' . $child_dashboard_name . '",';
                        $menus .= '"is_menu": ' . $child_is_menu . ',';
                        $menus .= '"is_disabled": ' . $child_is_disabled . ',';
                        $menus .= '"is_param": ' . $child_is_param . ',';
                        $menus .= '"param_setup_id": "' . $child_paramSetupId . '",';
                        $menus .= '"parent_id": ' . $child_parent_id . ',';

                        //level 2 menu items
                        $grandchildren = $this->getSystemMenu(2, $child_id);
                        if (count($grandchildren) > 0) {
                            $grandchildren_count = count($grandchildren);
                            $grandchildren_counter = 0;
                            $menus .= '"expanded": false,';
                            // $menus .= '"iconCls": "tree-parent",';
                            $menus .= '"children": [';
                            foreach ($grandchildren as $grandchild) {
                                $grandchildren_counter++;
                                $grandchild_id = $grandchild['id'];
                                $grandchild_name = $grandchild['name'];
                                $module_name = $parent_module_name . ' >> ' . $child_module_name . ' >> ' . $grandchild_name;
                                $grandchild_route_id = $grandchild['routeId'];
                                $grandchild_icon = 'x-fa fa-arrow-circle-right';//$grandchild['iconCls'];
                                $grandchild_level = $grandchild['level'];
                                $grandchild_order_no = $grandchild['order_no'];
                                $grandchild_viewType = $grandchild['viewType'];
                                $grandchild_is_dashboard = $grandchild['is_dashboard'];
                                $grandchild_dashboard_name = $grandchild['dashboard_name'];
                                $grandchild_is_menu = $grandchild['is_menu'];
                                $grandchild_is_disabled = $grandchild['is_disabled'];
                                $grandchild_is_param = $grandchild['is_param'];
                                $grandchild_paramSetupId = $grandchild['param_setup_id'];
                                $grandchild_parent_id = $child['parent_id'];
                                $grandchild_child_id = $grandchild['parent_id'];

                                $menus .= '{';
                                $menus .= '"id": ' . $grandchild_id . ',';
                                $menus .= '"menu_id": ' . $grandchild_id . ',';
                                $menus .= '"name": "' . $grandchild_name . '",';
                                $menus .= '"module_name": "' . $module_name . '",';
                                $menus .= '"routeId": "' . $grandchild_route_id . '",';
                                $menus .= '"iconCls": "' . $grandchild_icon . '",';
                                $menus .= '"level": ' . $grandchild_level . ',';
                                $menus .= '"order_no": ' . $grandchild_order_no . ',';
                                $menus .= '"viewType": "' . $grandchild_viewType . '",';
                                $menus .= '"is_dashboard": ' . $grandchild_is_dashboard . ',';
                                $menus .= '"dashboard_name": "' . $grandchild_dashboard_name . '",';
                                $menus .= '"is_menu": ' . $grandchild_is_menu . ',';
                                $menus .= '"is_disabled": ' . $grandchild_is_disabled . ',';
                                $menus .= '"is_param": ' . $grandchild_is_param . ',';
                                $menus .= '"param_setup_id": "' . $grandchild_paramSetupId . '",';
                                $menus .= '"parent_id": ' . $grandchild_parent_id . ',';
                                $menus .= '"child_id": ' . $grandchild_child_id . ',';
                                $menus .= '"leaf": true';

                                if ($grandchildren_counter == $grandchildren_count) {
                                    //Last Child in this level. Level=2
                                    $menus .= '}';
                                } else {
                                    $menus .= '},';
                                }
                            }
                            $menus .= '],';
                        } else {
                            $menus .= '"leaf": true';
                        }
                        if ($children_counter == $children_count) {
                            //Last Child in this level. Level=1
                            $menus .= '}';
                        } else {
                            $menus .= '},';
                        }
                    }
                    $menus .= '],';

                } else {
                    //$menus.="viewType: '".$viewType."',";
                    $menus .= '"leaf": true';
                }

                if ($menu_counter == $menu_count) {
                    $menus .= '}';
                } else {
                    $menus .= '},';
                }
            }
        }
        $menus .= ']}';
        return $menus;
    }

    public function getParentMenus()
    {
        $parents = Menu::where('level', 0)->get()->toArray();
        //$parents = decryptArray($parents);
        $res = array(
            'parentmenus' => $parents
        );
        return response()->json($res);
    }

    public function getChildMenus(Request $req)
    {
        $parent_id = $req->parent_id;
        $where = array(
            'level' => 1,
            'parent_id' => $parent_id
        );
        $parents = Menu::where($where)->get()->toArray();
        //$parents = decryptArray($parents);
        $res = array(
            'childmenus' => $parents
        );
        return response()->json($res);
    }

    public function saveMenuItem(Request $req)
    {
        try {
            $user_id = \Auth::user()->id;
            $post_data = $req->all();
            $table_name = $post_data['table_name'];
            $id = $post_data['id'];
            $skip = $post_data['skip'];
            $level = $post_data['level'];
            $parent_id = $post_data['parent_id'];
            $child_id = $post_data['child_id'];
            $skipArray = explode(",", $skip);
            if ($level > 1) {
                $parent_id = $child_id;
            }
            //unset unnecessary values
            unset($post_data['_token']);
            unset($post_data['table_name']);
            unset($post_data['model']);
            unset($post_data['id']);
            unset($post_data['skip']);
            unset($post_data['child_id']);
            unset($post_data['parent_id']);
            // $table_data = encryptArray($post_data, $skipArray);
            $table_data = $post_data;
            //add extra params
            $table_data['created_at'] = Carbon::now();
            $table_data['created_by'] = $user_id;
            $table_data['parent_id'] = $parent_id;
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

    function getSystemRole($level = 0, $parent_id = 0, $user_group)
    {
        $level = $level;
        $parent_id = $parent_id;
        $where = array(
            'menus.is_menu' => 1,
            'menus.is_disabled' => 0,
            'menus.level' => $level
        );
        //$groups=$this->getUserGroups();
        //DB::enableQueryLog();
        $qry = DB::table('menus')
            ->select('menus.id as menu_id', 'menus.name as menu_name', 'accesslevels.name as level_name', 'accesslevels.id as level_id', 'permissions.id as permission_id')
            ->leftJoin('permissions', function ($join) use ($user_group) {
                $join->on('menus.id', '=', 'permissions.menu_id')
                    ->on('permissions.group_id', '=', DB::raw($user_group));
            })
            ->leftJoin('accesslevels', 'permissions.accesslevel_id', '=', 'accesslevels.id')
            ->where($where);
        $qry = $parent_id == 0 ? $qry->orderBy('menus.order_no') : $qry->where('menus.parent_id', $parent_id)->orderBy('menus.order_no');
        $menus = $qry->get();
        //print_r(DB::getQueryLog());
        $menus = json_decode(json_encode($menus), true);
        return $menus;
    }
    
    public function getSystemRoles(Request $req)
    {
        $user_group = $req->user_group;
        $row = $this->getSystemRole(0, 0, $user_group);
        $roles = '{"roles": "."';
        $roles .= ',';
        $roles .= '"children": [';
        if (count($row)) {
            $menu_count = count($row);
            $menu_counter = 0;

            foreach ($row as $item) {
                $menu_counter++;
                $id = $item['menu_id'];
                $permission_id = $item['permission_id'];
                $name = aes_decrypt($item['menu_name']);
                $level = aes_decrypt($item['level_name']);
                $level_id = $item['level_id'];

                $roles .= '{';
                $roles .= '"menu_id": ' . $id . ',';
                $roles .= '"permission_id": "' . $permission_id . '",';
                $roles .= '"menu_name": "' . $name . '",';
                //$roles.='"iconCls": "tree-parent",';
                $roles .= '"level_name": "' . $level . '",';
                $roles .= '"level_id": "' . $level_id . '",';

                $children = $this->getSystemRole(1, $id, $user_group);
                if (count($children) > 0) {
                    $children_count = count($children);
                    $children_counter = 0;
                    $roles .= '"expanded": false,';
                    //$roles.='"iconCls": "tree-parent",';
                    $roles .= '"children": [';
                    foreach ($children as $child) {
                        $children_counter++;
                        $child_id = $child['menu_id'];
                        $child_permission_id = $child['permission_id'];
                        $child_name = aes_decrypt($child['menu_name']);
                        $child_level = aes_decrypt($child['level_name']);
                        $child_level_id = $child['level_id'];

                        $roles .= '{';
                        $roles .= '"menu_id": ' . $child_id . ',';
                        $roles .= '"permission_id": "' . $child_permission_id . '",';
                        $roles .= '"menu_name": "' . $child_name . '",';
                        $roles .= '"level_name": "' . $child_level . '",';
                        $roles .= '"level_id": "' . $child_level_id . '",';
                        $roles .= '"iconCls": "tree-parent",';
                        //$menus.="leaf: true";
                        //level 2 menu items
                        $grandchildren = $this->getSystemRole(2, $child_id, $user_group);
                        if (count($grandchildren) > 0) {
                            $grandchildren_count = count($grandchildren);
                            $grandchildren_counter = 0;
                            $roles .= '"expanded": false,';
                            $roles .= '"iconCls": "tree-parent",';
                            $roles .= '"children": [';
                            foreach ($grandchildren as $grandchild) {
                                $grandchildren_counter++;
                                $grandchild_id = $grandchild['menu_id'];
                                $grand_permission_id = $grandchild['permission_id'];
                                $grandchild_name = aes_decrypt($grandchild['menu_name']);
                                $grandchild_level = aes_decrypt($grandchild['level_name']);
                                $grandchild_level_id = $grandchild['level_id'];

                                $roles .= '{';
                                $roles .= '"menu_id": ' . $grandchild_id . ',';
                                $roles .= '"permission_id": "' . $grand_permission_id . '",';
                                $roles .= '"menu_name": "' . $grandchild_name . '",';
                                $roles .= '"level_name": "' . $grandchild_level . '",';
                                $roles .= '"level_id": "' . $grandchild_level_id . '",';
                                //$roles.="text: '".$grandchild_name."',";
                                $roles .= '"leaf": true';

                                if ($grandchildren_counter == $grandchildren_count) {
                                    //Last Child in this level. Level=2
                                    $roles .= '}';
                                } else {
                                    $roles .= '},';
                                }
                            }
                            $roles .= '],';
                        } else {
                            $roles .= '"leaf": true';
                        }
                        if ($children_counter == $children_count) {
                            //Last Child in this level. Level=1
                            $roles .= '}';
                        } else {
                            $roles .= '},';
                        }
                    }
                    $roles .= '],';

                } else {
                    //$menus.="viewType: '".$viewType."',";
                    $roles .= '"leaf": true';
                }

                if ($menu_counter == $menu_count) {
                    $roles .= '}';
                } else {
                    $roles .= '},';
                }
            }
        }
        $roles .= ']}';
        return $roles;
    }

    public function updateAccessRoles(Request $req)
    {
        $permission_id = $req->permission_id;
        $level_id = $req->level_id;
        $group_id = $req->group_id;
        $menu_id = $req->menu_id;
        $res = array();

        $permissions = explode(',', $permission_id);
        $levels = explode(',', $level_id);
        $menus = explode(',', $menu_id);
        $checkForEmptyArr = $permissions[0];
        $count = count($permissions);
        if ($checkForEmptyArr == '' || $checkForEmptyArr == NULL || is_null($checkForEmptyArr)) {
            $res = array(
                'success' => false,
                'message' => "Operation failed-->No record was changed for saving!!"
            );
        } else {
            DB::transaction(function () use ($count, $permissions, $levels, $menus, $group_id, &$res) {
                try {
                    for ($i = 0; $i < $count; $i++) {
                        $permission_id = $permissions[$i];
                        $params = array(
                            'group_id' => $group_id,
                            'menu_id' => $menus[$i],
                            'accesslevel_id' => $levels[$i],
                            'status' => 1,
                            'created_by' => \Auth::user()->id,
                            'updated_by' => \Auth::user()->id
                        );
                        $updateRole = Permission::updateOrCreate(
                            ['id' => $permission_id],
                            $params
                        );
                    }
                    if ($updateRole) {
                        $res = array(
                            'success' => true,
                            'message' => "Access Roles updated successfully!"
                        );
                    } else {
                        $res = array(
                            'success' => false,
                            'message' => "Access Roles NOT updated successfully. Try again!"
                        );
                    }
                } catch (\Exception $exception) {
                    $res = array(
                        'success' => false,
                        'message' => $exception->getMessage()
                    );
                }
            }, 5);
        }
        return response()->json($res);
    }

    public function updateSystemNavigationAccessRoles(Request $req)
    {
        $group_id = $req->input('group_id');
        $menuPermission_id = $req->input('menuPermission_id');
        $menuLevel_id = $req->input('menuLevel_id');
        $menu_id = $req->input('menu_id');

        $res = array();
        $menuPermissions = array_filter(explode(',', $menuPermission_id));
        $menuLevels = array_filter(explode(',', $menuLevel_id));
        $menus = array_filter(explode(',', $menu_id));

        $count = count($menus);

        if ($count < 1) {
            $res = array(
                'success' => false,
                'message' => "Operation failed-->No record was changed for saving!!"
            );
        } else {
            DB::transaction(function () use ($count, $menuPermissions, $menuLevels, $menus, $group_id, &$res) {
                try {
                    //for menus
                    if ($count > 0) {
                        for ($i = 0; $i < $count; $i++) {
                            $params = array(
                                'group_id' => $group_id,
                                'menu_id' => $menus[$i],
                                'accesslevel_id' => $menuLevels[$i],
                                'status' => 1,
                                'created_by' => $this->user_id,
                                'updated_by' => $this->user_id
                            );
                            if (isset($menuPermissions[$i])) {
                                $menuPermission_id = $menuPermissions[$i];
                                $menuPermission = Permission::find($menuPermission_id);
                                if ($menuPermission) {
                                    $menuPermission->group_id = $group_id;
                                    $menuPermission->menu_id = $menus[$i];
                                    $menuPermission->accesslevel_id = $menuLevels[$i];
                                    $menuPermission->status = 1;
                                    $menuPermission->created_by = \Auth::user()->id;
                                    $menuPermission->save();
                                }
                            } else {
                                Permission::create($params);
                            }
                        }
                    }
                    $res = array(
                        'success' => true,
                        'message' => "Access Roles updated successfully!"
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
            }, 5);
        }
        return response()->json($res);
    }

    public function updateSystemComponentsAccessRoles(Request $req)
    {
        $group_id = $req->input('group_id');
        $componentPermission_id = $req->input('componentPermission_id');
        $componentLevel_id = $req->input('componentLevel_id');
        $component_id = $req->input('component_id');

        $res = array();
        $componentPermissions = array_filter(explode(',', $componentPermission_id));
        $componentLevels = array_filter(explode(',', $componentLevel_id));
        $components = array_filter(explode(',', $component_id));

        $count2 = count($components);

        if ($count2 < 1) {
            $res = array(
                'success' => false,
                'message' => "Operation failed-->No record was changed for saving!!"
            );
        } else {
            DB::transaction(function () use ($count2, $componentPermissions, $componentLevels, $components, $group_id, &$res) {
                try {
                    //for menus processes
                    if ($count2 > 0) {
                        for ($j = 0; $j < $count2; $j++) {
                            $params = array(
                                'group_id' => $group_id,
                                'component_id' => $components[$j],
                                'accesslevel_id' => $componentLevels[$j],
                                'status' => 1,
                                'created_by' => $this->user_id,
                                'updated_by' => $this->user_id
                            );
                            if (isset($componentPermissions[$j])) {
                                $componentPermission_id = $componentPermissions[$j];
                                $componentPermission = ComponentsPermission::find($componentPermission_id);
                                if ($componentPermission) {
                                    $componentPermission->group_id = $group_id;
                                    $componentPermission->component_id = $components[$j];
                                    $componentPermission->accesslevel_id = $componentLevels[$j];
                                    $componentPermission->status = 1;
                                    $componentPermission->created_by = $this->user_id;
                                    $componentPermission->save();
                                }
                            } else {
                                ComponentsPermission::create($params);
                            }
                        }
                    }
                    $res = array(
                        'success' => true,
                        'message' => "Access Roles updated successfully!"
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
            }, 5);
        }
        return response()->json($res);
    }

    public function getGroups(Request $req)
    {
        $accessPointID = $req->accessPointId;
        /*$data = $accessPointID == '' ? Group::all()->toArray() : Group::where('group_owner_level', $accessPointID)->get()->toArray();
        $groups=decryptArray($data);*/
        $qry = DB::table('groups')
            ->join('access_points', 'groups.group_owner_level', '=', 'access_points.id')
            ->select('groups.*', 'access_points.code', 'access_points.name as accessPointName');
        $qry = $accessPointID == '' ? $qry->whereRaw(1, 1) : $qry->where('groups.group_owner_level', $accessPointID);
        $data = $qry->get();
        $groups = convertStdClassObjToArray($data);
        $groups = decryptArray($groups);
        $res = array(
            'groups' => $groups
        );
        return response()->json($res);
    }

    public function getSystem_guidelines(Request $req)
    {
        $menu_id = $req->menu_id;

        $qry = DB::table('system_guidelines as t1')
            ->select('t1.*')
            ->where(array('menu_id' => $menu_id))
            ->orderBy('t1.order_no', 'asc')
            ->get();
        $data = convertStdClassObjToArray($qry);
        $data = decryptArray($data);
        $res = array(
            'results' => $data
        );
        json_output($res);

    }

    public function getAccessPointUsers(Request $req)
    {
        $access_point_id = $req->input('access_point_id');
        $group_id = $req->input('group_id');
        try {
            $qry = DB::table('users')
                ->join('titles', 'users.title_id', '=', 'titles.id')
                ->leftJoin('user_roles', 'users.user_role_id', '=', 'user_roles.id')
                ->join('gender', 'users.gender_id', '=', 'gender.id')
                ->select('users.first_name', 'users.last_name', 'users.email', 'users.phone', 'users.mobile', 'user_roles.name as role_name', 'gender.name as gender_name', 'titles.name as title_name');
            if (isset($access_point_id) & $access_point_id != '') {
                $qry->where('users.access_point_id', $access_point_id);
            }
            if (isset($group_id) && $group_id != '') {
                $qry->join('user_group', function ($join) use ($group_id) {
                    $join->on('users.id', '=', 'user_group.user_id')
                        ->on('user_group.group_id', '=', DB::raw($group_id));
                });
            }
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $results = decryptArray($data);
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

    public function getGroupUsers(Request $req)
    {
        $group_id = $req->input('group_id');
        try {
            $qry = DB::table('users')
                ->join('titles', 'users.title_id', '=', 'titles.id')
                ->leftJoin('user_roles', 'users.user_role_id', '=', 'user_roles.id')
                ->join('gender', 'users.gender_id', '=', 'gender.id')
                ->join('user_group', function ($join) use ($group_id) {
                    $join->on('users.id', '=', 'user_group.user_id')
                        ->on('user_group.group_id', '=', DB::raw($group_id));
                })
                ->select('users.first_name', 'users.last_name', 'users.email', 'users.phone', 'users.mobile', 'user_roles.name as role_name', 'gender.name as gender_name', 'titles.name as title_name');
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $results = decryptArray($data);
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

    public function getWorkflowStages(Request $request)
    {
        $workflow_id = $request->input('workflow_id');
        try {
            $qry = DB::table('wf_workflow_stages as t1')
                ->join('wf_workflowstages_statuses as t3', 't1.stage_status', '=', 't3.id')
                ->leftJoin('wf_workflow_interfaces as t4', 't1.interface_id', '=', 't4.id')
                ->leftJoin('menus as t5', 't1.menu_id', '=', 't5.id')
                ->select('t1.*', 't3.name as stage_status_name', 't4.name as interface_name', 't5.name as menu_name')
                ->where('workflow_id', $workflow_id)
                ->orderBy('t1.order_no');
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

    public function getWorkflowTransitions(Request $request)
    {
        $workflow_id = $request->input('workflow_id');
        try {
            $qry = DB::table('wf_workflow_transitions as t1')
                ->join('wf_workflow_stages as t2', 't1.stage_id', '=', 't2.id')
                ->join('wf_workflow_stages as t3', 't1.nextstage_id', '=', 't3.id')
                ->join('wf_workflow_actions as t4', 't1.action_id', '=', 't4.id')
                ->leftJoin('system_statuses as t5', 't1.record_status_id', '=', 't5.id')
                ->select('t1.*', 't2.name as stage_name', 't3.name as nextstage_name', 't4.name as action_name', 't5.name as application_status')
                ->where('t1.workflow_id', $workflow_id)
                ->orderBy('t2.order_no');
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => 'All is well'
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

    public function showWorkflowDiagram(Request $request)
    {
        try {
            $data = array();
            $workflow_id = $request->input('workflow_id');
            $states = DB::table('wf_workflow_stages')
                ->select(DB::raw("id,name as text,stage_status as status"))
                ->where('workflow_id', $workflow_id)
                ->get();
            $transitions = DB::table('wf_workflow_transitions as t1')
                ->join('wf_workflow_actions as t2', 't1.action_id', '=', 't2.id')
                ->select(DB::raw("t1.stage_id as 'from',t1.nextstage_id as 'to',t2.name as text"))
                ->where('t1.workflow_id', $workflow_id)
                ->get();
            $diagramDataArray = array(
                "nodeKeyProperty" => "id",
                'nodeDataArray' => $states,
                'linkDataArray' => $transitions
            );
            $data['workflowData'] = $diagramDataArray;
        } catch (\Exception $exception) {

        } catch (\Throwable $throwable) {

        }
        return view('administration::workflowDiagram', $data);
    }

    public function getBasicWorkflowDetails(Request $request)
    {
        $process_id = $request->input('process_id');
        try {
            $qry = DB::table('wf_kgsprocesses as t1')
                ->join('wf_workflows as t2', 't1.workflow_id', '=', 't2.id')
                ->select('t1.workflow_id', 't2.name')
                ->where('t1.id', $process_id);
            $results = $qry->first();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => 'Workflow details fetched!!'
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

    public function getInitialWorkflowDetails(Request $request)
    {
        $process_id = $request->input('process_id');
        try {
            //get workflow id
            $qry = DB::table('wf_kgsprocesses as t1')
                ->join('wf_workflows as t2', 't1.workflow_id', '=', 't2.id')
                ->join('wf_workflow_stages as t3', function ($join) {
                    $join->on('t2.id', '=', 't3.workflow_id')
                        ->on('t3.stage_status', '=', DB::raw(1));
                })
                ->join('wf_workflow_interfaces as t4', 't3.interface_id', '=', 't4.id')
                ->select('t4.viewtype', 't1.id as processId', 't1.name as processName', 't3.name as initialStageName', 't3.id as initialStageId')
                ->where('t1.id', $process_id);
            $results = $qry->first();
            //initial status details
            $statusDetails = getRecordInitialStatus($process_id);
            $results->initialRecordStatus = $statusDetails->name;
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => 'Workflow details fetched!!'
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

    public function getAllWorkflowDetails(Request $request)
    {
        $process_id = $request->input('process_id');
        $stage_id = $request->input('workflow_stage');
        try {
            //get workflow id
            $where = array(
                't1.id' => $process_id,
                't3.id' => $stage_id
            );
            $qry = DB::table('wf_kgsprocesses as t1')
                ->join('wf_workflows as t2', 't1.workflow_id', '=', 't2.id')
                ->join('wf_workflow_stages as t3', 't3.workflow_id', '=', 't2.id')
                ->join('wf_workflow_interfaces as t4', 't3.interface_id', '=', 't4.id')
                ->select('t1.workflow_id', 't2.name', 't4.viewtype', 't1.id as processId', 't1.name as processName', 't3.name as initialStageName', 't3.id as initialStageId');
            $qry->where($where);
            $results = $qry->first();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => 'Workflow details fetched!!'
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

    public function getWorkflowActions(Request $request)
    {
        $stage_id = $request->input('stage_id');
        $is_submission = $request->input('is_submission');
        $complaint_category_id = $request->input('complaint_category_id');
        try {
            $immediate_escalation = $this->checkStageForImmediateEscalation($stage_id, $complaint_category_id);
            $qry = DB::table('wf_workflow_actions as t1')
                ->join('wf_workflow_stages as t2', 't1.stage_id', '=', 't2.id')
                ->join('wf_workflowaction_types as t3', 't1.action_type_id', '=', 't3.id')
                ->select('t1.*', 't2.name as stage_name', 't3.name as action_type')
                ->where('stage_id', $stage_id);
            if (validateisNumeric($is_submission) && $is_submission == 1) {
                $qry->where('t1.is_enabled', 1);
            }
            if ($immediate_escalation === true) {
                $qry->where('t1.is_escalation', 1);
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

    public function checkStageForImmediateEscalation($stage_id, $complaint_category_id)
    {
        $immediateStageEscalation = DB::table('wf_workflow_stages')
            ->where('id', $stage_id)
            ->value('immediate_escalation');
        $immediateCategoryEscalation = DB::table('grm_complaint_categories')
            ->where('id', $complaint_category_id)
            ->value('immediate_escalation');
        if ($immediateStageEscalation == 1 && $immediateCategoryEscalation == 1) {
            return true;
        }
        return false;
    }

    public function getSubmissionWorkflowStages(Request $request)
    {
        $process_id = $request->input('process_id');
        try {
            $qry = DB::table('wf_kgsprocesses as t1')
                ->join('wf_workflows as t2', 't1.workflow_id', '=', 't2.id')
                ->join('wf_workflow_stages as t3', 't2.id', '=', 't3.workflow_id')
                ->select('t3.*')
                ->where('t1.id', $process_id);
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => 'All is well'
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

    public function getSubmissionNextStageDetails(Request $request)
    {
        $current_stage = $request->input('current_stage');
        $action = $request->input('action');
        $where = array(
            'stage_id' => $current_stage,
            'action_id' => $action
        );
        try {
            $qry = DB::table('wf_workflow_transitions as t1')
                ->select('t1.*')
                ->where($where);
            $results = $qry->first();
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

    public function updateRecordNotificationReadingFlag(Request $request)
    {
        $record_id = $request->input('record_id');
        $table_name = $request->input('table_name');
        try {
            DB::table($table_name)
                ->where('record_id', $record_id)
                ->where('isRead', '<>', 1)
                ->update(array('isRead' => 1, 'readBy' => $this->user_id));
            $res = array(
                'success' => true,
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

    public function getMenuItemAssignedUsers(Request $request)
    {
        $workflow_stage_id = $request->input('next_stage');
        try {
            //get associated menus
            $qry1 = DB::table('wf_workflow_stages')
                ->select('menu_id')
                ->where('id', $workflow_stage_id);
            $menus = $qry1->first();
            $menu_id = $menus->menu_id;

            $qry3 = DB::table('user_group as t1')
                ->join('users as t2', 't1.user_id', '=', 't2.id')
                ->join('groups as t3', function ($join) use ($menu_id) {
                    $join->on('t1.group_id', '=', 't3.id')
                        ->whereIn('t3.id', function ($query) use ($menu_id) {
                            $query->select(DB::raw('t2.group_id'))
                                ->from('permissions as t2')
                                ->where('t2.accesslevel_id', '<>', 1)
                                ->where('t2.menu_id', $menu_id);
                        });
                })
                ->select(DB::raw("t2.id,CONCAT_WS(' ',decrypt(t2.first_name),decrypt(t2.last_name)) as fullnames"));
            $results = $qry3->get();

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

    public function getProcesses()
    {
        try {
            $qry = DB::table('wf_kgsprocesses as t1')
                ->leftJoin('wf_workflows as t2', 't1.workflow_id', '=', 't2.id')
                ->select('t1.*', 't2.name as workflow');
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

    public function getProcessStageMaxDaysSetup(Request $request)
    {
        $process_id = $request->input('process_id');
        $workflow_id = $request->input('workflow_id');
        try {
            $qry = DB::table('wf_workflow_stages as t1')
                ->leftJoin('process_stage_max_days as t2', 't1.id', '=', 't2.stage_id')
                ->where('t1.workflow_id', $workflow_id)
                ->select('t1.*', 't2.max_days', 't2.stage_id');
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

    public function saveProcessStageMaxDaysSetup(Request $request)
    {
        $process_id = $request->input('process_id');
        $postdata = file_get_contents("php://input");
        $data = json_decode($postdata);
        // the post data is array thus handle as array data
        if (is_array($data)) {
            $data = json_decode($postdata, true);
        } else {
            $data = array();
            $data[] = json_decode($postdata, true);
        }
        $table_name = 'process_stage_max_days';
        try {
            $insert_data = array();
            foreach ($data as $key => $value) {
                $where_data = array(
                    'stage_id' => $value['id'],
                    'process_id' => $process_id
                );
                if (recordExists($table_name, $where_data)) {
                    //update
                    $update_data = array(
                        'max_days' => $value['max_days'],
                        'updated_by' => $this->user_id,
                        'updated_at' => Carbon::now()
                    );
                    DB::table($table_name)
                        ->where($where_data)
                        ->update($update_data);
                } else {
                    //create insert array
                    $insert_data[] = array(
                        'stage_id' => $value['id'],
                        'process_id' => $process_id,
                        'max_days' => $value['max_days'],
                        'created_by' => $this->user_id,
                        'created_at' => Carbon::now()
                    );
                }
            }
            DB::table($table_name)
                ->insert($insert_data);
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

    function getSystemComponentsRoles(Request $request)
    {
        try{
            $user_group = $request->input('user_group');
            $qry = DB::table('system_components as t1')
                ->leftJoin('components_permissions as t2', function ($join) use ($user_group) {
                    $join->on('t1.id', '=', 't2.component_id')
                        ->where('t2.group_id', '=', $user_group);
                })
                ->leftJoin('components_accesslevels as t3', 't2.accesslevel_id', '=', 't3.id')
                ->select('t1.id', 't1.name', 't1.description', 't1.id as component_id', 't1.identifier', 't3.name as level_name', 't3.id as level_id', 't2.id as permission_id')
                ->orderBy('t1.id');
            $menus = $qry->get();
            $menus = json_decode(json_encode($menus), true);
        }catch(\Exception $exception){
            $menus=$exception->getMessage();
        }catch(\Throwable $throwable){
            $menus=$throwable->getMessage();
        }
        return $menus;
    }

    public function removeSelectedUsersFromGroup(Request $request)
    {
        try {
            $selected = $request->input('selected');
            $group_id = $request->input('group_id');
            $selected_ids = json_decode($selected);
            $user_id = $this->user_id;
            $params = DB::table('user_group as t1')
                ->select(DB::raw("t1.*,$user_id as deletion_by"))
                ->where('group_id', $group_id)
                ->whereIn('user_id', $selected_ids)
                ->get();
            $params = convertStdClassObjToArray($params);
            DB::table('user_group_log')
                ->insert($params);
            DB::table('user_group')
                ->where('group_id', $group_id)
                ->whereIn('user_id', $selected_ids)
                ->delete();
            $res = array(
                'success' => true,
                'message' => 'User(s) removed successfully!!'
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

    public function exportAdminRecords(Request $request)
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
    //Finance
    public function getfinancialActivitiesDueCount(){
         $count= DB::table('financial_activities_due')
                        ->where('is_active','=','1')->count();
        return $count;

    }

}
