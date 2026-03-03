<?php

namespace App\Modules\StatusesManagement\Http\Controllers;

use App\Http\Controllers\BaseController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StatusesManagementController extends BaseController
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
        return view('statusesmanagement::index');
    }

    public function getStatusParam($model_name)
    {
        $model = 'App\\Modules\\statusesmanagement\\Entities\\' . $model_name;
        $results = $model::all()->toArray();
        $results = decryptArray($results);
        $res = array(
            'results' => $results
        );
        return response()->json($res);
    }

    public function saveStatusCommonData(Request $req)
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
        $table_data = encryptArray($post_data, $skipArray);
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

    public function deleteStatusRecord(Request $req)
    {
        $record_id = $req->id;
        $table_name = $req->table_name;
        $user_id = \Auth::user()->id;
        $where = array(
            'id' => $record_id
        );
        try{
            $previous_data = getPreviousRecords($table_name, $where);
            $res = deleteRecord($table_name, $previous_data, $where, $user_id);
        }catch(\Exception $exception){
            $res=array(
                'success'=>false,
                'message'=>$exception->getMessage()
            );
        }
        return response()->json($res);
    }
}
