<?php

namespace App\Modules\AuditTrail\Http\Controllers;

use App\Exports\GridExport;
use Carbon\Carbon;
use Excel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class AuditTrailController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function getPortalAuditTrail(Request $Request)
    {
        $con = DB::Connection('TRAILDB_CONNECTION');
        $qry = $con->table('wb_portalaudit_trail as t1')
            ->select(DB::raw('t1.id,t1.table_name,t1.table_action,t1.ip_address,t1.created_at,t1.created_by,t1.record_id'));

        //filters
        $table_name = $Request->table_name;
        $table_data = $Request->table_data;
        $created_by = $Request->created_by;
        $filters = $Request->filter;
        $id = $Request->id;
        $start = $Request->start;
        $limit = $Request->limit;

        if (isset($table_name)) {
            $qry->where('table_name', $table_name);
        }
        if (isset($created_by)) {
            $qry->where('t1.created_by', $created_by);
        }
        if (isset($table_data)) {
            $qry->where("(t1.current_tabledata LIKE '%" . $table_data . "%' OR t1.prev_tabledata LIKE '%" . $table_data . "%')");
        }

        $filters = (array) json_decode($filters);
        $whereClauses = array();
        if ($filters != '') {
            foreach ($filters as $filter) {
                switch ($filter->property) {
                    case 'table_action':
                        $whereClauses[] = "t1.table_action like '%" . ($filter->value) . "%'";
                        break;
                    case 'record_id':
                        $whereClauses[] = "t1.record_id like '%" . ($filter->value) . "%'";
                        break;
                    case 'created_at':
                        $whereClauses[] = "date_format(t1.created_at, '%Y%-%m-%d')='" . formatDate($filter->value) . "'";
                        break;
                }
            }
        }
        if (!empty($whereClauses)) {
            $filter_string = implode(' AND ', $whereClauses);
            $qry->whereRAW($filter_string);
        }

        //get total entries
        $total = $qry->count();

        //limit results
        if (isset($start) && isset($limit)) {
            $res = $qry->skip($start)->take($limit)->get();
        } else {
            $res = $qry->get();
        }

        $finalArray = array();
        //loop records to add user
        foreach ($res as $content) {
            $user = $this->getUsers($content->created_by, 'wb_trader_account');
            $content->created_by = $user;
            $finalArray[] = $content;

        }
        $res = array(
            'success' => true,
            'results' => $finalArray,
            'message' => 'All is well',
            'total' => $total,
        );

        return $res;
    }

    public function getUsers($id, $table)
    {
        $qry = DB::table($table)
            ->where('id', $id);
        if ($table == 'users') {
            $qry->select(DB::raw('CONCAT(decrypt(first_name),decrypt(last_name)) as name'));
        } else {
            $qry->select('name');
        }
        $res = $qry->get()->first();
        if ($qry->count() == 0) {
            return 'null';
        } else {
            return $res->name;
        }
    }

    public function getAllUsers($table, $id = null)
    {
        $qry = DB::table($table);

        if ($id != null) {
            $qry->where('id', $id);
        }

        if ($table == 'users') {
            $qry->select(DB::raw('id,CONCAT(decrypt(first_name),decrypt(last_name)) as name'));
        } else {
            $qry->select('id', 'name');
        }
        $res = $qry->get();
        if ($qry->count() == 0) {
            return 'null';
        } else {
            return $res;
        }
    }

    public function getMisAuditTrail(Request $Request)
    {
        $con = DB::Connection('TRAILDB_CONNECTION');
        $qry = $con->table('wb_misaudit_trail as t1')
            ->select(DB::raw('t1.id,t1.table_name,t1.table_action,t1.ip_address,t1.created_at,t1.created_by,t1.record_id'));

        //filters
        $table_name = $Request->table_name;
        $table_data = $Request->table_data;
        $created_by = $Request->created_by;
        $filters = $Request->filter;
        $start = $Request->start;
        $limit = $Request->limit;

        if (isset($table_name)) {
            $qry->where('table_name', $table_name);
        }
        if (isset($created_by)) {
            $qry->where('t1.created_by', $created_by);
        }
        if (isset($table_data)) {
            $qry->whereRAW("(t1.current_tabledata LIKE '%" . $table_data . "%' OR t1.prev_tabledata LIKE '%" . $table_data . "%')");
        }

        $filters = (array) json_decode($filters);
        $whereClauses = array();
        if ($filters != '') {
            foreach ($filters as $filter) {
                switch ($filter->property) {
                    case 'table_action':
                        $whereClauses[] = "t1.table_action like '%" . ($filter->value) . "%'";
                        break;
                    case 'record_id':
                        $whereClauses[] = "t1.record_id like '%" . ($filter->value) . "%'";
                        break;
                    case 'created_at':
                        $whereClauses[] = "date_format(t1.created_at, '%Y%-%m-%d')='" . formatDate($filter->value) . "'";
                        break;
                }
            }
        }
        if (!empty($whereClauses)) {
            $filter_string = implode(' AND ', $whereClauses);
            $qry->whereRAW($filter_string);
        }
        //get total entries
        $total = $qry->count();

        if (isset($start) && isset($limit)) {
            $res = $qry->skip($start)->take($limit)->get();
        } else {
            $res = $qry->get();
        }

        $finalArray = array();
        //loop records to add user
        foreach ($res as $content) {
            $user = $this->getUsers($content->created_by, 'users');
            $content->created_by = $user;
            $finalArray[] = $content;

        }

        $res = array(
            'success' => true,
            'results' => $finalArray,
            'message' => 'All is well',
            'total' => $total,
        );

        return $res;
    }

    public function getPortalAuditTableData(Request $Request)
    {
        $id = $Request->id;
        $type = $Request->type;
        $con = DB::Connection('TRAILDB_CONNECTION');
        $qry = $con->table('wb_portalaudit_trail')
            ->where('id', $id);
        if ($type == 'updated') {
            $qry->select('current_tabledata');
            $results = $qry->get()->first();
            $data = $results->current_tabledata;
        } else {
            $qry->select('prev_tabledata');
            $results = $qry->get()->first();
            $data = $results->prev_tabledata;
        }

        $data = (array) unserialize($data);

        $flaten_data = $this->mergeImpureArray($data);

        $res = array(
            'success' => true,
            'results' => $flaten_data,
            'message' => 'All is well',
        );
        return $res;
    }

    public function getMISAuditTableData(Request $Request)
    {
        $id = $Request->id;
        $type = $Request->type;
        $con = DB::Connection('TRAILDB_CONNECTION');
        $qry = $con->table('wb_misaudit_trail')
            ->where('id', $id);
        if ($type == 'updated') {
            $qry->select('current_tabledata');
            $results = $qry->first();
            $data = $results->current_tabledata;

        } else {
            $qry->select('prev_tabledata');
            $results = $qry->first();
            $data = $results->prev_tabledata;
        }

        $data = (array) unserialize($data);
        $flaten_data = $this->mergeImpureArray($data);
        $res = array(
            'success' => true,
            'results' => $flaten_data,
            'message' => 'All is well',
        );
        return $res;
    }

    public function mergeImpureArray($array)
    {
        $finalArray = array();
        $temp = '';
        foreach ($array as $key => $value) {

            if (is_array($value)) {
                $finalArray = array_merge($finalArray, $value);
            } else {
                $finalArray[$key] = $value;
            }

        }
        if (array_key_exists(0, $finalArray)) {
            return $finalArray[0];
        }

        return $finalArray;

    }
    public function revertAuditRecord(Request $req)
    {
        $id = $req->id;
        $type = $req->type;
        $Audit_table = '';
        if ($type == 'mis') {
            $Audit_table = 'wb_misaudit_trail';
        } else {
            $Audit_table = 'wb_portalaudit_trail';
        }
        $con = DB::Connection('TRAILDB_CONNECTION');
        $qry = $con->table($Audit_table)
            ->where('id', $id);
        $res = $qry->get()->first();
        $multi_array_current_tabledata = unserialize($res->current_tabledata);
        $multi_array_prev_tabledata = unserialize($res->prev_tabledata);
        $record_id = $res->record_id;
        $table_name = $res->table_name;

        if ($multi_array_prev_tabledata != false) {
            // $current_tabledata=$this->mergeImpureArray($multi_array_current_tabledata);
            $prev_tabledata = $this->mergeImpureArray($multi_array_prev_tabledata);

            $updateTbl = DB::table($table_name)
                ->where('id', $record_id)
                ->update($prev_tabledata);

            $updateprev = $con->table($Audit_table)
                ->insert([
                    'current_tabledata' => $res->prev_tabledata,
                    'record_id' => $res->record_id,
                    'table_name' => $res->table_name,
                    'table_action' => 'update',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

            if ($updateTbl && $updateprev) {
                $res = array(
                    'results' => 'Update successfull',
                    'success' => true,
                    'message' => 'All is well',
                );
                return $res;
            } else {
                $res = array(
                    'results' => 'Some updates failed try Again',
                    'success' => true,
                    'message' => 'All is well',
                );
                return $res;
            }
        } else {
            $res = array(
                'results' => 'No previous records Found',
                'success' => true,
                'message' => 'All is well',
            );
            return $res;
        }

    }

    public function getTableslist(Request $Request)
    {
        $in_db = $Request->in_db;
        $prefix = $Request->prefix;
        if ($in_db == 'portal') {
            $tables = DB::connection('portal_db')->getDoctrineSchemaManager()->listTableNames();
        } else {
            $tables = DB::connection()->getDoctrineSchemaManager()->listTableNames();
        }
        try {
            if (validateIsNumeric($prefix)) {
                $is_filtered = true;
                switch ($prefix) {
                    case 1:
                        $prefix_txt = 'tra_';
                        break;
                    case 2:
                        $prefix_txt = 'wf_';
                        break;
                    case 3:
                        $prefix_txt = 'par_';
                        break;

                    default:
                        $is_filtered = false;
                        break;
                }
            } else {
                $is_filtered = false;
            }

            foreach ($tables as $table) {
                if ($is_filtered) {
                    if (strpos(" " . $table, $prefix_txt) == 1) {
                        $data[] = array('table_name' => $table);
                    }
                } else {
                    $data[] = array('table_name' => $table);
                }

            }
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'All is well',
            );
        } catch (\Exception $exception) {
            $res = sys_error_handler($exception->getMessage(), 2, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1), explode('\\', __CLASS__), \Auth::user()->id);

        } catch (\Throwable $throwable) {
            $res = sys_error_handler($throwable->getMessage(), 2, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1), explode('\\', __CLASS__), \Auth::user()->id);
        }
        return \response()->json($res);

    }

    public function dataUnserialization($data, $keyPrefix)
    {
        $un_data = unserialize($data);
        $flaten_data = $this->mergeImpureArray($un_data);
        $withKeys = array();
        foreach ($flaten_data as $key => $value) {
            $withKeys[$keyPrefix . '_' . $key] = $value;
        }

        return $withKeys;

    }

    public function getAllAuditTrans(Request $Request)
    {
        $con = DB::Connection('TRAILDB_CONNECTION');
        //get type
        $record_id = $Request->record_id;
        $table_name = $Request->table_name;
        $type = $Request->type;

        $Audit_table = '';
        $user_table = '';
        if ($type == 'mis') {
            $Audit_table = 'wb_misaudit_trail';
            $user_table = 'users';
        } else {
            $Audit_table = 'wb_portalaudit_trail';
            $user_table = 'wb_trader_account';
        }

        $qry = $con->table($Audit_table)
            ->where('record_id', $record_id)
            ->where('table_name', $table_name);
        $res = $qry->get();
        $final_data = array();
        foreach ($res as $Entry) {
            $data = array(
                'table_action' => $Entry->table_action,
                'action_by' => $this->getUsers($Entry->created_by, $user_table),
                'record_id' => $Entry->record_id,
                'created_at' => $Entry->created_at,
            );

            if ($Entry->current_tabledata != '') {
                $curent_records = $this->dataUnserialization($Entry->current_tabledata, 'current');
            } else {
                $curent_records = array();
            }

            if ($Entry->prev_tabledata != null) {
                $prev_records = $this->dataUnserialization($Entry->prev_tabledata, 'prev');
            } else {
                $prev_records = array();
            }
            $final_data[] = array_merge($data, $prev_records, $curent_records);
            rsort($final_data);
        }
        return $final_data;
    }

    public function getArrayColumns($array)
    {
        $temp = array();
        if (!empty($array[0])) {
            foreach ($array[0] as $key => $udata) {
                $temp[] = $key;
            }
        }
        return $temp;

    }

    public function exportAudit(request $request)
    {

        $type = $request->type;
        if ($type == 'mis') {
            $function = 'getMisAuditTrail';
        } else {
            $function = 'getPortalAuditTrail';
        }
        //send request to function
        $records = $this->$function($request);
        $flaten_data = $records['results'];

        $header = $this->getArrayColumns($flaten_data);
        $k = 0;
        $sortedData = array();
        $total = count($header);
        //convert to allowed format
        foreach ($records['results'] as $udata) {
            for ($v = 0; $v < $total; $v++) {
                $temp1 = $header[$v];
                $sortedData[$k][] = $udata->$temp1;

            }

            $k++;
        }

        $heading = "Audit Trail Records";
        $export = new GridExport($sortedData, $header, $heading);

        $file = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);

        $response = array(
            'success' => true,
            'name' => "AuditTrail.xlsx", //no extention needed
            'file' => "data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64," . base64_encode($file), //mime type of used format
        );
        return response()->json($response);

    }

    public function getloginLogs(Request $req)
    {

        $directorate_id = $req->directorate_id;
        $department_id = $req->department_id;
        $day = $req->day;
        $user_id = $req->user_id;
        $filters = $req->filters;

        $qry = DB::table('tra_login_logs as t1')
            ->join('users as t2', 't1.account_id', 't2.id')
            ->leftJoin('par_departments as t3', 't2.department_id', 't3.id')
            ->leftJoin('par_directorates as t4', 't2.directorate_id', 't4.id')
            ->select('t1.ip_address', 't1.user_agent', 't1.time as loging_time', 't2.username', DB::raw('CONCAT(decrypt(t2.first_name),decrypt(t2.last_name)) as name, decrypt(t2.email) as email, decrypt(t2.mobile) as mobile_no, decrypt(t2.phone) as phone_no'));

        //filters
        if (validateIsNumeric($directorate_id)) {
            $qry->where('t2.directorate_id', $directorate_id);
        }
        if (validateIsNumeric($department_id)) {
            $qry->where('t2.directorate_id', $department_id);
        }
        if (validateIsNumeric($user_id)) {
            $qry->where('t2.id', $user_id);
        }
        if (isset($day)) {
            $qry->whereRAW("date_format(t1.time, '%Y%-%m-%d')='" . formatDate($day) . "'");
        }

        $filter = $req->input('filter');
        $whereClauses = array();
        $filter_string = '';
        if (isset($filter)) {
            $filters = json_decode($filter);
            if ($filters != null) {
                foreach ($filters as $filter) {
                    switch ($filter->property) {
                        case 'email':
                            $whereClauses[] = "decrypt(t2.email) like '%" . ($filter->value) . "%'";
                            break;
                        case 'username':
                            $whereClauses[] = "decrypt(t2.username) like '%" . ($filter->value) . "%'";
                            break;
                        case 'loging_time':
                            $whereClauses[] = "DATE_FORMAT(t1.time, '%H:%i:%s') like '%" . ($filter->value) . "%'";
                            break;
                    }
                }
                $whereClauses = array_filter($whereClauses);
            }
            if (!empty($whereClauses)) {
                $filter_string = implode(' AND ', $whereClauses);
            }
        }

        if ($filter_string != '') {
            $qry->whereRAW($filter_string);
        }

        $results = $qry->get();

        return response()->json($results);
    }

    public function getloginAttemptsLogs(Request $req)
    {

        $directorate_id = $req->directorate_id;
        $department_id = $req->department_id;
        $day = $req->day;
        $user_id = $req->user_id;
        $filters = $req->filters;

        $qry = DB::table('tra_failed_login_attempts as t1')
            ->join('users as t2', 't1.account_id', 't2.id')
            ->leftJoin('par_departments as t3', 't2.department_id', 't3.id')
            ->leftJoin('par_directorates as t4', 't2.directorate_id', 't4.id')
            ->select('t1.ip_address', 't1.user_agent', 't1.time as last_Attempt_time', 't2.username', DB::raw('CONCAT(decrypt(t2.first_name),decrypt(t2.last_name)) as name, decrypt(t2.email) as email, decrypt(t2.mobile) as mobile_no, decrypt(t2.phone) as phone_no'));

        //filters
        if (validateIsNumeric($directorate_id)) {
            $qry->where('t2.directorate_id', $directorate_id);
        }
        if (validateIsNumeric($department_id)) {
            $qry->where('t2.directorate_id', $department_id);
        }
        if (validateIsNumeric($user_id)) {
            $qry->where('t2.id', $user_id);
        }
        if (isset($day)) {
            $qry->whereRAW("date_format(t1.time, '%Y%-%m-%d')='" . formatDate($day) . "'");
        }

        $filter = $req->input('filter');
        $whereClauses = array();
        $filter_string = '';
        if (isset($filter)) {
            $filters = json_decode($filter);
            if ($filters != null) {
                foreach ($filters as $filter) {
                    switch ($filter->property) {
                        case 'email':
                            $whereClauses[] = "decrypt(t2.email) like '%" . ($filter->value) . "%'";
                            break;
                        case 'username':
                            $whereClauses[] = "decrypt(t2.username) like '%" . ($filter->value) . "%'";
                            break;
                        case 'loging_time':
                            $whereClauses[] = "DATE_FORMAT(t1.time, '%H:%i:%s') like '%" . ($filter->value) . "%'";
                            break;
                    }
                }
                $whereClauses = array_filter($whereClauses);
            }
            if (!empty($whereClauses)) {
                $filter_string = implode(' AND ', $whereClauses);
            }
        }

        if ($filter_string != '') {
            $qry->whereRAW($filter_string);
        }

        $results = $qry->get();

        return response()->json($results);
    }
    public function getSystemErrorLogs(Request $req)
    {
        $resolved_by = $req->resolved_by;
        $originated_from_user_id = $req->originated_from_user_id;
        $is_resolved = $req->is_resolved;
        $error_level_id = $req->error_level_id;
        $user_id = \Auth::user()->id;
        //results
        try {
            $qry = DB::table('system_error_logs as t1')
                ->leftJoin('users as t2', 't1.originated_from_user_id', 't2.id')
                ->leftJoin('par_error_levels as t3', 't1.error_level_id', 't3.id')
                ->leftJoin('users as t4', 't1.resolved_by', 't4.id')

                ->select(DB::raw('CONCAT(decrypt(t2.first_name),decrypt(t2.last_name)) as originated_from_user, CONCAT(decrypt(t4.first_name),decrypt(t4.last_name)) as resolved_by_name, t3.name as error_level, t1.*'))
                ->orderBy('t1.generated_on', 'Desc');

            //filters
            $filter = $req->input('filter');
            $whereClauses = array();
            $filter_string = '';
            if (isset($filter)) {
                $filters = json_decode($filter);
                if ($filters != null) {
                    foreach ($filters as $filter) {
                        switch ($filter->property) {
                            case 'originated_on':
                                $whereClauses[] = "date_format(t1.generated_on, '%Y%-%m-%d')='" . formatDate($filter->value) . "'";
                                break;
                            case 'resolved_on':
                                $whereClauses[] = "date_format(t1.resolved_on, '%Y%-%m-%d')='" . formatDate($filter->value) . "'";
                                break;
                        }
                    }
                    $whereClauses = array_filter($whereClauses);
                }
                if (!empty($whereClauses)) {
                    $filter_string = implode(' AND ', $whereClauses);
                }
            }

            if ($filter_string != '') {
                $qry->whereRAW($filter_string);
            }
            if (validateIsNumeric($is_resolved) && $is_resolved != 3) {
                $qry->where('t1.is_resolved', $is_resolved);
            }
            if (validateIsNumeric($resolved_by)) {
                $qry->where('t1.resolved_by', $resolved_by);
            }
            if (validateIsNumeric($originated_from_user_id)) {
                $qry->where('t1.originated_from_user_id', $originated_from_user_id);
            }
            if (validateIsNumeric($error_level_id)) {
                $qry->where('t1.error_level_id', $error_level_id);
            }
            $start = $req->start;
            $limit = $req->limit;
            //get total entries
            $total = $qry->count();

            if (isset($start) && isset($limit)) {
                $res = $qry->skip($start)->take($limit)->get();
            } else {
                $res = $qry->get();
            }

            $res = array(
                'success' => true,
                'results' => $res,
                'total' => $total,
                'message' => 'all is well',
            );
        } catch (\Exception $exception) {
            //defaults
            $function = "failed to fetch";
            //class
            $class_array = explode('\\', __CLASS__);
            if (isset($class_array[5])) {
                $class = $class_array[5];
            } else {
                $class = "Failed to fetch";
            }
            //specifics
            $me = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
            if ($me[0]['function']) {
                $function = $me[0]['function'];
            }
            if ($me[0]['class']) {
                $class = $me[0]['class'];
            }
            $res = sys_error_handler($exception->getMessage(), 2, "function-->" . $function . " class-->" . $class, \Auth::user()->id);
        } catch (\Throwable $throwable) {
            //defaults
            $function = "failed to fetch";
            //class
            $class_array = explode('\\', __CLASS__);
            if (isset($class_array[5])) {
                $class = $class_array[5];
            } else {
                $class = "Failed to fetch";
            }
            //specifics
            $me = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
            if ($me[0]['function']) {
                $function = $me[0]['function'];
            }
            if ($me[0]['class']) {
                $class = $me[0]['class'];
            }
            $res = sys_error_handler($throwable->getMessage(), 2, "function-->" . $function . " class-->" . $class, \Auth::user()->id);
        }
        return response()->json($res);
    }

    public function markErrorLogAsResolved(Request $req)
    {
        $id = $req->id;
        $user_id = \Auth::user()->id;
        try {
            if (validateIsNumeric($id)) {
                $qry = DB::table('system_error_logs');
                $where_app = array('id' => $id);
                $previous_data = getPreviousRecords('system_error_logs', $where_app);
                $previous_data = $previous_data['results'];
                $app_data['dola'] = Carbon::now();
                $app_data['altered_by'] = $user_id;
                $app_data['is_resolved'] = 1;
                $app_data['resolved_by'] = $user_id;
                $app_data['resolution_comment'] = $req->comment;
                $app_data['resolved_on'] = Carbon::now();
                $res = updateRecord('system_error_logs', $previous_data, $where_app, $app_data, $user_id);

            }
        } catch (\Exception $exception) {
            //defaults
            $function = "failed to fetch";
            //class
            $class_array = explode('\\', __CLASS__);
            if (isset($class_array[5])) {
                $class = $class_array[5];
            } else {
                $class = "Failed to fetch";
            }
            //specifics
            $me = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
            if (isset($me[0]['function'])) {
                $function = $me[0]['function'];
            }
            if (isset($me[0]['class'])) {
                $class = $me[0]['class'];
            }
            $res = sys_error_handler($exception->getMessage(), 2, "function-->" . $function . " class-->" . $class, \Auth::user()->id);
        } catch (\Throwable $throwable) {
            //defaults
            $function = "failed to fetch";
            //class
            $class_array = explode('\\', __CLASS__);
            if (isset($class_array[5])) {
                $class = $class_array[5];
            } else {
                $class = "Failed to fetch";
            }
            //specifics
            $me = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
            if (isset($me[0]['function'])) {
                $function = $me[0]['function'];
            }
            if (isset($me[0]['class'])) {
                $class = $me[0]['class'];
            }
            $res = sys_error_handler($throwable->getMessage(), 2, "function-->" . $function . " class-->" . $class, \Auth::user()->id);
        }
        return response()->json($res);
    }

    public function getUserAccessLogs(Request $request)
    {
        try {
            $year_id = $request->input('year_id');
            $qry = DB::table('users')
                ->join('user_activity_log', 'users.id', '=', 'user_activity_log.created_by')
                ->leftJoin('user_images', 'users.id', '=', 'user_images.user_id')
                ->join('access_points', 'users.access_point_id', '=', 'access_points.id')
                ->leftJoin('user_roles', 'users.user_role_id', '=', 'user_roles.id')
                ->join('gender', 'users.gender_id', '=', 'gender.id')
                ->join('titles', 'users.title_id', '=', 'titles.id')
                ->leftJoin('menus as t8', 'users.dashboard_id', '=', 't8.id')
                ->select(DB::raw("users.id as user_id, users.first_name, users.last_name, users.last_login_time, users.title_id,
              decrypt(users.email) as email, decrypt(users.phone) as phone, decrypt(users.mobile) as mobile,users.gewel_programme_id,
              users.nongewel_programme_id,users.gender_id, users.access_point_id, users.user_role_id, gender.name as gender_name,
              titles.name as title_name, user_images.saved_name,users.dashboard_id,
              access_points.name as access_point_name, user_roles.name as user_role_name,
              CONCAT_WS(' ',decrypt(users.first_name),decrypt(users.last_name)) as names,t8.dashboard_name,users.is_coordinator,
              user_activity_log.module_name,user_activity_log.user_agent,user_activity_log.ip_address"));
            /* ->whereNotIn('users.id', function ($query) {
            $query->select(DB::raw('blocked_accounts.account_id'))
            ->from('blocked_accounts');
            }); */

            //$qry->where('email',aes_encrypt('kaputow@gmail.com'));518 id
            /* if (isset($group_id) && $group_id != '') {
            $users = DB::table('user_group')
            ->select('user_id')
            ->where('group_id', $group_id)
            ->get();
            $users = convertStdClassObjToArray($users);
            $users = convertAssArrayToSimpleArray($users, 'user_id');
            $qry->whereIn('users.id', $users);
            } */
            if (validateisNumeric($year_id)) {
                $qry->whereRaw("YEAR(user_activity_log.created_at)=$year_id");
            }
            $data = $qry->get();
            // $data = convertStdClassObjToArray($data);
            // $data = decryptArray($data);
            $res = array(
                'success' => true,
                'message' => returnMessage($data),
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

    public function getUserLoginLogs(Request $request)
    {
        try {
            $year_id = $request->input('year_id');
            $qry = DB::table('users as t1')
                ->join('login_logs as t2', 't1.id', '=', 't2.account_id')
                ->leftJoin('user_roles as t3', 't1.user_role_id', '=', 't3.id')
                ->select(DB::raw("t1.*,t2.*,t3.name as user_role_name,t1.id as user_id,decrypt(t1.email) as email,
                  CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.last_name)) as action_by"));
            if (validateisNumeric($year_id)) {
                $qry->whereRaw("YEAR(t2.time)=$year_id");
            }
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $data = decryptArray($data);
            $res = array(
                'success' => true,
                'message' => returnMessage($data),
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

    public function getSystemAuditTrailLogs(Request $Request)
    {
        $year_id = $Request->input('year_id');
        $qry = DB::table('audit_trail as t1')
            ->join('users as t2', 't1.created_by', '=', 't2.id')
            ->leftJoin('user_roles as t3', 't2.user_role_id', '=', 't3.id')
            ->select(DB::raw("t1.id,t1.table_name,t1.table_action,t1.ip_address,t1.created_at,t1.created_by,
            t1.record_id,t2.username,CONCAT_WS(' ',decrypt(t2.first_name),decrypt(t2.last_name)) as action_by,
            t2.title_id,t2.dob,decrypt(t2.email) as email,t2.phone,t2.mobile,t2.gender_id,t2.access_point_id,t2.user_role_id,
            t2.id as user_id,t3.name as user_role_name"));
        //filters
        $table_name = $Request->table_name;
        $table_data = $Request->table_data;
        $created_by = $Request->created_by;
        $filters = $Request->filter;
        $start = $Request->start ? $Request->start : 0;
        $limit = $Request->limit ? $Request->limit : 100;

        if (isset($table_name)) {
            $qry->where('table_name', $table_name);
        }

        if (isset($created_by)) {
            $qry->where('t1.created_by', $created_by);
        }

        if (isset($table_data)) {
            $qry->whereRAW("(t1.current_tabledata LIKE '%" . $table_data . "%' OR t1.prev_tabledata LIKE '%" . $table_data . "%')");
        }

        if (validateisNumeric($year_id)) {
            $qry->whereRaw("YEAR(t1.created_at)=$year_id");
        }

        $filters = (array) json_decode($filters);
        $whereClauses = array();
        if ($filters != '') {
            foreach ($filters as $filter) {
                switch ($filter->property) {
                    case 'table_action':
                        $whereClauses[] = "t1.table_action like '%" . ($filter->value) . "%'";
                        break;
                    case 'record_id':
                        $whereClauses[] = "t1.record_id like '%" . ($filter->value) . "%'";
                        break;
                    case 'created_at':
                        $whereClauses[] = "date_format(t1.created_at, '%Y%-%m-%d')='" . formatDate($filter->value) . "'";
                        break;
                }
            }
        }

        if (!empty($whereClauses)) {
            $filter_string = implode(' AND ', $whereClauses);
            $qry->whereRAW($filter_string);
        }

        //get total entries
        $total = $qry->count();
        if (isset($start) && isset($limit)) {
            $res = $qry->skip($start)->take($limit)->get();
        } else {
            $res = $qry->get();
        }

        $finalArray = array();
        //loop records to add user
        // foreach ($res as $content) {
        //   $user_from_content = $content->created_by;
        //   $user_from_content = (int)$user_from_content;
        //   $user = $this->getUsers($user_from_content,'users') ? $this->getUsers($user_from_content,'users') : $user_from_content;
        //   $content->created_by = $user;
        //   $finalArray[] = $content;
        // }

        $res = array(
            'success' => true,
            'results' => $res,
            'message' => 'All is well',
            'total' => $total,
        );
        return $res;
    }
}
