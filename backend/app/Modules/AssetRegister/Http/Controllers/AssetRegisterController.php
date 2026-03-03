<?php

namespace App\Modules\AssetRegister\Http\Controllers;

use App\Http\Controllers\BaseController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use PDF;
use Dompdf\Dompdf;
use Mail;
use Maatwebsite\Excel\Facades\Excel;
use App\Helpers\AssetsImport;
use App\Modules\Dms\Http\Controllers\DmsController;
use Illuminate\Support\Facades\Redirect;
use App\Modules\AssetRegister\Exports\ConsolidatedAssetsData;
use App\Modules\AssetRegister\Exports\DisposalReport;
use App\Modules\AssetRegister\Exports\ConsolidatedStoresAssetsData;
use App\Modules\AssetRegister\Exports\DisposalStoresReport;
//use App\Helpers\PDFHelper;
class AssetRegisterController extends BaseController
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
        return view('assetregister::index');
    }

    public function saveAssetRegisterCommonData(Request $req)
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
            return $where;
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

    

    public function getAssetRegisterParamFromTable(Request $request)
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

    public function deleteAssetRegisterRecord(Request $request)
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
    
    public function getExpiredAssetWarranties(Request $request)
    {
       
        try {
        $fifteen_days_to= DB::table('ar_asset_warranties as t1')
        ->where('t1.expiration_date','<=',  Carbon::now()->addDays(15)->format('Y-m-d'))//equal to no. days to expiry
        ->where('t1.expiration_date','>',  Carbon::now()->addDays(1)->format('Y-m-d'))
        ->join('ar_asset_inventory as t2','t1.asset_id','=','t2.id')
        ->selectRaw("t1.*,t1.expiration_date,t2.description,t2.grz_no,t2.serial_no,DATEDIFF(t1.expiration_date,CURDATE()) as days_to_expiry")
        ->get()->toArray();
        $expired_ones= DB::table('ar_asset_warranties as t1')
        //->where('t1.expiration_date','>',  Carbon::now()->addDays(1)->format('Y-m-d'))
        ->where('t1.expiration_date','<', now()->format('Y-m-d'))
        ->join('ar_asset_inventory as t2','t1.asset_id','=','t2.id')
        ->selectRaw("t1.*,t1.expiration_date,t2.description,t2.grz_no,t2.serial_no,DATEDIFF(t1.expiration_date,CURDATE()) as days_to_expiry")
        ->get()->toArray();

        //initial
        // $qry = DB::table('ar_asset_warranties as t1');
        // ->orwhere('t1.expiration_date','<',  Carbon::now()->addDays(15)->format('Y-m-d'))//equal to no. days to expiry
        // ->orwhere('t1.expiration_date','>',  Carbon::now()->addDays(1)->format('Y-m-d'))
        //         //->where('t1.expiration_date','>',  Carbon::now()->addDays(1)->format('Y-m-d'))//equal to no. days to expiry
        //         ->join('ar_asset_inventory as t2','t1.asset_id','=','t2.id')
        //         ->orwhere('t1.expiration_date', '<', now()->format('Y-m-d'))
        //         ->selectRaw("t1.*,t1.expiration_date,t2.description,t2.grz_no,t2.serial_no,DATEDIFF(t1.expiration_date,CURDATE()) as days_to_expiry");
         $results=array_merge($fifteen_days_to,$expired_ones);
       
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
    public function getDueScheduledRepairs(Request $request)
    {
        try{
           
            $qry = DB::table('ar_asset_inventory as t1')
                ->join('ar_asset_categories as t2', 't1.category_id', '=', 't2.id')
                ->join('ar_asset_brands as t3', 't1.brand_id', '=', 't3.id')
                ->join('ar_asset_models as t4', 't1.model_id', '=', 't4.id')
                ->join('ar_asset_sites as t5', 't1.site_id', '=', 't5.id')
                ->join('ar_asset_locations as t6', 't1.location_id', '=', 't6.id')
                ->join('departments as t7', 't1.department_id', '=', 't7.id')
                ->join('ar_asset_statuses as t8', 't1.status_id', '=', 't8.id')
                ->join('ar_asset_repairs as t9','t1.id',"=","t9.asset_id")
                ->selectRaw("t1.*, t1.id as record_id,t1.id as asset_id, t2.name as category_name, t3.name as brand_name, t4.name as model_name,t5.name as site_name,
                     t6.name as location_name,t7.name as department_name, t8.name as record_status,CONCAT_WS('-',t1.description,t1.grz_no) as desc_grz_no,t9.*");
                     $qry->where('t9.handled',"=",'0')
                      ->whereDate('t9.repair_date', '<', now()->format('Y-m-d'));

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
    public function getActiveScheduledRepairs(Request $request)
    {
        try{
           
            $qry = DB::table('ar_asset_repairs as t1')
                    ->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
                   
                    ->join('ar_asset_sites as t4', 't2.site_id', '=', 't4.id')
                    ->leftjoin('ar_asset_locations as t5', 't2.location_id', '=', 't5.id')
                    ->leftjoin('departments as t6', 't2.department_id', '=', 't6.id')
                    ->join('ar_asset_statuses as t7', 't2.status_id', '=', 't7.id')
                    ->selectRaw('t1.id as repair_record_id,t1.*,t2.id as parent_asset_id, t2.description,t2.serial_no,t2.grz_no,t4.id as site_id,t4.name as site_name,
                    t2.location_id,t2.department_id,t5.name as location_name,t6.name as department_name, t7.name as record_status,t1.scheduled_repair_date,
                    t1.repair_status');
                   
                    $qry->where('repair_status', 0);
                    $qry->where('scheduled_for_repair','=','1');
                
        
            


            // $qry = DB::table('ar_asset_inventory as t1')
            //     ->join('ar_asset_categories as t2', 't1.category_id', '=', 't2.id')
            //     ->join('ar_asset_brands as t3', 't1.brand_id', '=', 't3.id')
            //     ->join('ar_asset_models as t4', 't1.model_id', '=', 't4.id')
            //     ->join('ar_asset_sites as t5', 't1.site_id', '=', 't5.id')
            //     ->join('ar_asset_locations as t6', 't1.location_id', '=', 't6.id')
            //     ->join('departments as t7', 't1.department_id', '=', 't7.id')
            //     ->join('ar_asset_statuses as t8', 't1.status_id', '=', 't8.id')
            //     ->join('ar_asset_repairs as t9','t1.id',"=","t9.asset_id")
            //     ->selectRaw("t1.*, t1.id as record_id,t1.id as asset_id, t2.name as category_name, t3.name as brand_name, t4.name as model_name,t5.name as site_name,
            //          t6.name as location_name,t7.name as department_name, t8.name as record_status,CONCAT_WS('-',t1.description,t1.grz_no) as desc_grz_no,t9.*");
            //          $qry->where('t9.handled',"=",'0')
            //           ->whereDate('t9.repair_date', '>', now()->format('Y-m-d'));

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

    public function saveAssetRepairReportDetails(Request $request)
    {

        try {
            DB::transaction(function() use ($request, &$res) {
            $repair_record_id = $request->input('id');
            $record_id = $request->input('asset_id');
            //$repair_remarks=$request->input('repair_remarks');
            $actual_repair_date=$request->input("actual_repair_date");
            $repair_successful=$request->input("repair_successful");
            $updated_date= Carbon::now();
            $user_id= $this->user_id;
          
            $inventory_details_table_name = 'ar_asset_inventory';
            $table_data=[
                "asset_id"=>$record_id,
                //"repair_remarks"=>$repair_remarks,
                "actual_repair_date"=>$actual_repair_date,
                "repair_successful"=>$repair_successful,
                "updated_by"=>$user_id,
                "updated_on"=>$updated_date,  
                "handled"=>1,
            ];
            $where = array(
                'id' =>  $repair_record_id
            );
           
            if(recordExists('ar_asset_repairs', $where)) {
               
                $previous_data = getPreviousRecords('ar_asset_repairs', $where);
                $res=updateRecord('ar_asset_repairs', $previous_data, $where, $table_data, $user_id);
                        
            }
            $where = array(
                'id' =>  $record_id
            );
            if(recordExists($inventory_details_table_name, $where)) {
                $params=[];
                $previous_data = getPreviousRecords($inventory_details_table_name, $where);
                if($repair_successful==1)
                {
                    $params['status_id']=1;
                }else{
                    $params['status_id']=10;
                }
                $params['updated_at'] = Carbon::now();
                $params['updated_by'] = $user_id;
                $res=updateRecord($inventory_details_table_name, $previous_data, $where, $params, $user_id);
                $res['message']="Asset Repair Details have been saved successfully";           
            }
    
            },5);
           }
            catch (\Exception $exception) {
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

public function getReservedAssets(Request $request)
    {
        try {
            $qry = DB::table('ar_asset_reservations as t1')
                ->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
                ->join('users as t3', 't1.user_id', '=', 't3.id')
                ->join('ar_asset_sites as t4', 't1.reservation_site_id', '=', 't4.id')
                ->leftjoin('ar_asset_locations as t5', 't1.reservation_location_id', '=', 't5.id')//to cover nulls
                ->leftjoin('departments as t6', 't1.reservation_department_id', '=', 't6.id')
                ->join('ar_asset_statuses as t7', 't2.status_id', '=', 't7.id')
                //->leftJoin('ar_asset_maintainance as t8', 't1.id', '=', 't8.asset_id')
               // ->leftJoin('ar_asset_repairs as t9', 't1.id', '=', 't9.asset_id')

               //->leftJoin('ar_asset_checkin_details as t7', 't1.id', '=', 't7.checkout_id')
                ->selectRaw("t1.id as reservation_id,t1.*,t2.description,t2.serial_no,t1.checkin_status,t2.grz_no,t4.name as site_name,t5.name as location_name,
                        t2.site_id,t2.location_id,t2.department_id,CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as assigned_user,t6.name as department_name,
                        t7.name as record_status,t1.start_date,t1.end_date,t1.reserve_purpose,t1.reserve_for_who as reservation_for_id,t1.id as reservation_record_id,
                        t1.reservation_site_id as checkout_site_id,t1.reservation_location_id as checkout_location_id,
                        t1.reservation_department_id as checkout_department_id,t2.sub_category_id as category_id,t1.end_date as due_date");
           
            $results = $qry->get()
            ->where('reserve_for_who',1);;
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
   

    public function getUserDetails()
    {
         $user = DB::table('users')
        ->select('email')
        ->where('id', '=', 4)
        ->get();
        $user=decryptArray(convertStdClassObjToArray($user));
       

        

        
    }

    public function saveAssetTransferDetails(Request $request)
    {
        try {
            $transfer_record_id = $request->input('transfer_record_id');
            $asset_id = $request->input('parent_asset_id');
            $category_being_transfered_to= $request->input('transfer_to_site_or_ind_id');
            $site_id = $request->input('transfer_to_site_id');
            $location_id = $request->input('transfer_location_id');
            $department_id = $request->input('transfer_department_id');
            $transfer_to_who=$request->input('transfer_to_individual_id');
            $transfer_reason =  $request->input('transfer_reason');
            $transfer_date_checkin_date= $request->input('transfer_date');
            $checkout_transfer_due_date= $request->input('due_date');
            $checkout_id =  $request->input('checkout_id');
            $user_transfered_from=  $request->input('currently_assigned_user_id');
            $site_asset_individual_responsible=$request->input('site_asset_individual_responsible');
            // $previous_site_id=$request->input('site_id');
            // $previous_location_id=$request->input('location_id')
            // $previous_department_id=

            $user_id = $this->user_id;
            $table_name = 'ar_asset_transfers';

            $tranfer_data=array(
                'asset_id' => $asset_id,
                "transfer_category"=>$category_being_transfered_to,
                "transfer_reason"=>$transfer_reason,
                "transfer_date"=>$transfer_date_checkin_date,    
            );
            if($category_being_transfered_to==1)
            {
                $tranfer_data['user_transfered_to']=$transfer_to_who;
                $tranfer_data['site_transfered_to']=$site_id;
                $tranfer_data['location_transfered_to']=$location_id;
                $tranfer_data['department_transfered_to']=$department_id;
            }else{
                $tranfer_data['site_transfered_to']=$site_id;
                $tranfer_data['location_transfered_to']=$location_id;
                $tranfer_data['department_transfered_to']=$department_id;
               // $tranfer_data['site_asset_individual_responsible_fld ']=$site_asset_individual_responsible_fld; 
            }
            if(validateisNumeric($user_transfered_from))
            {
                $tranfer_data['user_transfered_from']=$user_transfered_from;
            }
            

           if(isset($transfer_record_id) && $transfer_record_id!="")
           {
               //update edit
           }else{
              
               //insert
               $table_data= $tranfer_data;
               $table_data['checkout_id']=$checkout_id;
               $table_data['created_at'] = Carbon::now();
               $table_data['created_by'] = $user_id;
               $res = insertRecord($table_name, $table_data, $user_id);
               if ($res['success'] == true) {
                 $table_name="ar_asset_checkin_details";
                  
                    $table_data=[];
                    $table_data['checkin_site_id'] = $request->input('site_id');
                    $table_data['checkin_location_id'] =$request->input('location_id');
                    $table_data['checkin_department_id'] = $request->input('department_id');
                    $table_data['return_date'] =  $transfer_date_checkin_date;
                    $table_data['checkout_id'] = $checkout_id;
                    $table_data['created_at'] = Carbon::now();
                    $table_data['created_by'] = $user_id;
                    $res = insertRecord($table_name, $table_data, $user_id);

                   
                   
                    if (validateisNumeric($checkout_id)) {
                        $table_name="ar_asset_checkout_details";
                            //checkout item
                            $asset_status_id = 2;
                            $checkout_status_id=1;
                            $id = $checkout_id;
                            $table_data=[];
                            $where = array(
                                'id' => $id
                            );
                            //checkin previous user
                            $table_data['checkout_status']=2;
                            $table_data['updated_at'] = Carbon::now();
                            $table_data['updated_by'] = $user_id;
                            $previous_data = getPreviousRecords($table_name, $where);
                            $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);  
                            
                            if ($res['success'] == true) //check in new user/site
                            {
                            $table_data=[];
                            $table_data['checkout_status']=1;
                            $table_data['checkout_site_id'] = $site_id;
                            $table_data['checkout_location_id'] = $location_id;
                            $table_data['checkout_department_id'] = $department_id;
                            $table_data['user_id'] = $transfer_to_who;
                            $table_data['checkout_date'] = $transfer_date_checkin_date;
                            $table_data['no_due_date'] = $request->input('no_due_date');
                            $table_data['due_date'] = $checkout_transfer_due_date;
                            $table_data['asset_id'] = $asset_id;
                            $table_data['updated_at'] = Carbon::now();
                            $table_data['updated_by'] = $user_id;
                            $table_data['site_asset_individual_responsible']=$site_asset_individual_responsible; 
                            $res = insertRecord($table_name, $table_data, $user_id);
                            $this->insert_into_asset_history($asset_id,"transfered");
                            }
                    }
                   //no need to update item status in inventry table its the samee
                  
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
    public function saveAssetReservationDetails(Request $request)
    {

        try {
           
            $id = $request->input('reservation_id');
            $asset_id = $request->input('asset_id');
            if(!validateisNumeric($asset_id)){
                $asset_id = $request->input('parent_asset_id');
                   }
            $site_id = $request->input('reservation_site_id');
            $location_id = $request->input('reservation_location_id');
            $department_id = $request->input('reservation_department_id');
            $reserve_for_who=$request->input('reservation_for_id');
            $reserve_puprose =  $request->input('reserve_purpose');
            $user_id = $this->user_id;
            $table_name = 'ar_asset_reservations';
            $table_data = array(
                'asset_id' => $asset_id,
                'user_id' => $request->input('user_id'),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'send_email' => $request->input('send_email'),
                'user_email' => $request->input('user_email'),
                'reserve_for_who'=>$reserve_for_who,
                'reserve_purpose'=>$reserve_puprose,
                'reservation_site_id' => $site_id,
                'reservation_location_id' => $location_id,
                'reservation_department_id' => $department_id
            );
          
            $where = array(
                'id' => $id
            );
            if (isset($id) && $id != "") {
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $previous_data = getPreviousRecords($table_name, $where);
                $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                $this->insert_into_asset_history($asset_id,"reservation details updated");
            } else {
               
                $table_data['created_at'] = Carbon::now();
                $table_data['created_by'] = $user_id;
                
        
           
               $res = insertRecord($table_name, $table_data, $user_id);
              
               $this->insert_into_asset_history($asset_id,"reservation created");
                if ($res['success'] == true) {
                    
                    if($table_data['send_email']==1)
                    {   $mail_to=$table_data['user_email'];
                        //$mail_to="murumbajob78@gmail.com";
                        $subject = "Asset Reservation";
                        $data=array(
                            "description"=>$request->input('description'),
                            //"serial_no"=>$request->input('serial_no'),
                            "no_due_date"=>0,//0 for due date,
                            "checkout_date"=>date('d-m-Y',strtotime( $request->input('start_date'))),
                            "due_date"=> date('d-m-Y',strtotime($request->input('end_date'))),
                            "user_id"=>$request->input('user_id')
                        ); 
                        if($request->input('serial_no')!="")
                        {
                            $data['serial_no']=$request->input('serial_no');

                        }else{
                            $data['grz_no']=$request->input('grz_no');
                        }
                        $this->sendAssetCheckOutEmail($mail_to,"Asset Reservation",$data,false,true);
                      //$this->sendAssetCheckOutEmail("murumbajob78@gmail.com","Asset Reservation",$data,false,true);
                    }
                   
                           $this->sendAssetHasBeenReservedMailToStakeHolders([
                   "description"=>$request->input('description'),
                   "serial_no"=>$request->input('serial_no'),
                   "grz_no"=>$request->input('grz_no')
                   
               ],$request->input('start_date'),$request->input('end_date'));
                    //update asset details
                    $asset_update = array(
                        'status_id' => 3,
                        'site_id' => $site_id,
                        'location_id' => $location_id,
                        'department_id' => $department_id
                    );
                    DB::table('ar_asset_inventory')
                        ->where('id', $asset_id)
                        ->update($asset_update);//checked out
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
    private function sendAssetHasBeenReservedMailToStakeHolders(Array $asset,$period_from,$period_to)
    {
        $users=Db::table('users as t1')->selectRaw('decrypt(t1.last_name) as user_last_name,
        decrypt(t1.email) as user_email')->get()->toArray();
        $clean_user_with_mails=[];
        $url=url('/');
        $image_url=$url.'/backend/public/moge_logo.png';
      
        foreach($users as $key=>$user)
        {
            $email  = $user->user_email;
            $emailB = filter_var($email, FILTER_SANITIZE_EMAIL);

            if (filter_var($emailB, FILTER_VALIDATE_EMAIL) === false || $emailB != $email ) {
                $to_send=false;
                //echo "This email adress isn't valid!";
                //exit(0);
            }else{
                $clean_user_with_mails[]=$user;
            }
        }
       
        foreach($clean_user_with_mails as $key=>$mail_user_data)
        {
            $mail_to_person=$mail_user_data->user_email;
            //$mail_to_person="murumbajob78@gmail.com";
            $subject = "Asset Unavailability Due to Reservation";
            $sal="Dear ".$mail_user_data->user_last_name.",";
            $msg="The asset below will be unavailable from ".date('d-m-Y',strtotime($period_from))." to ".
            date('d-m-Y', strtotime($period_to))." as it has been reserved";
            $use_serial_no=0;
            if($asset['serial_no']!=="" && $asset['serial_no']!=null)
            {
                $use_serial_no=1;
            }
            $data=[
            "assets"=>[$asset],
            "subject"=>$subject,
            "use_serial_no"=>$use_serial_no,
            "image_url"=>$image_url,
            "msg"=>$msg,
            "sal"=>"Dear  ".$mail_user_data->user_last_name.","
        ];
       
    
      if($key==1){
          $mail_to_person="murumbajob78@gmail.com";
           $result= Mail::send('mail.assetReservebulkUsersMail',$data,function($message) use($mail_to_person,$subject,$data){
            $message->to($mail_to_person,$mail_to_person);
            $message->subject($subject);
            $message->from('mogekgs@gmail.com','MOGE');

        });
       
    }
        }
       

    }
    public function saveAssetSellDetails(Request $request)
    {
        try {
            DB::transaction(function() use ($request, &$res) {
            $record_id = $request->input('asset_id');
            $status_id=7;
            $remarks=$request->input('remarks');
            $date_written_off=$request->input("date_sold");
            $user_id= $this->user_id;
            $inventory_details_table_name = 'ar_asset_inventory';
            $table_data=[
                "asset_id"=>$record_id,
                "remarks"=>$remarks,
                "reported_by"=>$user_id,
                "date_written_off"=>$date_written_off,  
            ];
            insertRecord('ar_asset_sell_details',$table_data,$user_id);
            $where = array(
                'id' => $record_id
            );
           
            if (recordExists($inventory_details_table_name, $where)) {
                $params=[];
                $previous_data = getPreviousRecords($inventory_details_table_name, $where);
                $params['status_id']=$status_id;
                $params['updated_at'] = Carbon::now();
                $params['updated_by'] = $user_id;
               $res=updateRecord($inventory_details_table_name, $previous_data, $where, $params, $user_id);
               $res['message']="Asset Details has been saved successfully";           
                }
    
            },5);
           }
            catch (\Exception $exception) {
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
    public function saveAssetMaintainanceScheduleDetails(Request $request)
    {
        
        try {
           // DB::transaction(function() use ($request, &$res) {
            $maintainance_record_id = $request->input('maintainance_record_id');//for edit
            //$maintainance_location_id =$request->input('maintainance_location_id');
            $date_maintainance_completed =$request->input('date_maintainance_completed');
            $cost =$request->input('maintainance_cost');
            $record_id = $request->input('parent_asset_id');
            $status_id=11;
           // $remarks=$request->input('remarks');
            $schedule_maintainance_date=$request->input('maintainance_due_date_form');
           
            $user_id= $this->user_id;
            $inventory_details_table_name = 'ar_asset_inventory';
            $maintainance_by= $request->input('maintainance_by');
            $maintainance_frequency=$request->input('maintainance_frequency');
            $main_status=$request->input('maintainance_status_form');
            $table_name='ar_asset_maintainance';
            $table_data=[
                "asset_id"=>$record_id,
                //"remarks"=>$remarks,
                "created_by"=>$user_id,
                "created_at"=>carbon::now(),
                "maintainance_due_date"=>$schedule_maintainance_date,  
            ];
            
            
            if(isset($maintainance_record_id) && $maintainance_record_id!="")
            {   $where = array(
                'id' => $maintainance_record_id
            );
                if( (isset($maintainance_by) && $maintainance_by!="") && $date_maintainance_completed=="")
                {
                $table_data=[];
               // $table_data['location_id']=$maintainance_location_id;
                $table_data['maintainance_by']=$maintainance_by;
                $table_data['maintainance_status']=1;
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $previous_data = getPreviousRecords($table_name, $where);
                $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                $new_asset_update = array(
                    'status_id' =>11,
            
                );
                $where=array(
                    'id'=>$record_id
                );
                $previous_data = getPreviousRecords('ar_asset_inventory', $where);
                $res = updateRecord('ar_asset_inventory', $previous_data, $where,  $new_asset_update , $user_id);
           
                 $res['message']="Asset Maintainance Dispatch Details have been saved successfully";
                 $this->insert_into_asset_history($record_id,"Dispatched for maintainance");
                }else if(isset($date_maintainance_completed) && $date_maintainance_completed!="")
                {   
                    $where = array(
                        'id' => $maintainance_record_id
                    );
                    
                    $table_data=[];
                    $table_data['date_maintainance_completed']=$date_maintainance_completed;
                    $table_data['maintainance_cost']=$cost;
                    $table_data['funding_id']=$request->input('funding_id');
                    $table_data['maintainance_status']=2;
                    $table_data['updated_at'] = Carbon::now();
                    $table_data['updated_by'] = $user_id;
                    //$table_data['next_maintainance_date']=$next_main_date;
                    $previous_data = getPreviousRecords($table_name, $where);
                    $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                    $asset_update_data = array(
                        'status_id' =>1,
                        'scheduled_for_maintainance'=>0,
                        //"next_maintainance_date"=>$next_main_date,
                        //"has_maintainance_scheduled"=>1,
                    );
                    if(isset($maintainance_frequency) &&$maintainance_frequency!="" ){
                   
                    $next_main_date=Carbon::createFromFormat('Y-m-d', $date_maintainance_completed)->addDays($maintainance_frequency)->toDateString();
                    $asset_update_data["next_maintainance_date"]=$next_main_date;
                    $asset_update_data['has_maintainance_scheduled']=0;
                    $asset_update_data['scheduled_for_maintainanc']=0; 
                    
                    
                }
                    $where=array(
                        'id'=>$record_id
                    );
                    
                        $previous_data = getPreviousRecords('ar_asset_inventory', $where);
                        $res = updateRecord('ar_asset_inventory', $previous_data, $where, $asset_update_data, $user_id);
                     $res['message']="Asset Maintainance Report Details have been saved successfully";
                     $this->insert_into_asset_history($record_id,"maintainance report added");

                }else{
               //edit operation
                   
             
               $asset_update_data = array(//previosuly asset_update incase bug
                    'maintainance_schedule_date'=>$schedule_maintainance_date,
                    "maintainance_frequency"=>$maintainance_frequency,
                    //"next_maintainance_date"=>$next_main_date,
                    //"has_maintainance_scheduled"=>1,
                );
                
                if(isset($maintainance_frequency) && $maintainance_frequency!="" ){
                   
                    $next_main_date=Carbon::createFromFormat('Y-m-d', $schedule_maintainance_date)->addDays($maintainance_frequency)->toDateString();
                    $asset_update_data["next_maintainance_date"]=$next_main_date;
                    $asset_update_data['has_maintainance_scheduled']=1;   
                    $asset_update_data['scheduled_for_maintainance']=1;    
                }
                $where=array(
                    'id'=>$record_id
                );
                
                if($main_status==4|| $main_status==3)//canced or on hold
                {
                    $asset_update_data['status_id']=1;
                    $asset_update_data['has_maintainance_scheduled']=0;  
                    $asset_update_data['scheduled_for_maintainance']=0; 
                    $asset_update_data['next_maintainance_date']="";
                   
                }
                $table_data['maintainance_status']=$main_status;
               
             
                
               $previous_data = getPreviousRecords('ar_asset_inventory', $where);
               $res = updateRecord('ar_asset_inventory', $previous_data, $where, $asset_update_data, $user_id);
                
               unset($table_data["created_at"]);
                   
               unset($table_data["created_by"]);
                
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
               
                $where=array(
                    'id'=>$maintainance_record_id
                );
             
               $previous_data = getPreviousRecords($table_name, $where);
               $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
               $res['message']="Asset Maintainance Schedule Details have been saved successfully";
               $verb="";
               if($main_status==3)
               {
                   $verb="put on hold";
               }else{
                   $verb="canceled";
               }

              $this->insert_into_asset_history($record_id,"maintainance schedule ".$verb);
            }
            }else{
               
              
               //to be modify after confirm schedule status wether 0 assumed o.dipatch 1.
                $If_has_pending_shedule=DB::table('ar_asset_maintainance')->where('asset_id',$record_id)
                ->whereIn('maintainance_status',[0,1])->count();
                //->orwhere('maintainance_status',1)->orwhere('maintainance_status',0)->count();
             
            if($If_has_pending_shedule==0){
               

    
                $res=insertRecord('ar_asset_maintainance',$table_data,$user_id);
                $next_main_date=Carbon::createFromFormat('Y-m-d', $schedule_maintainance_date)->addDays($maintainance_frequency)->toDateString();
                $table_data = array(
                    'maintainance_frequency' =>$maintainance_frequency,
                    'scheduled_for_maintainance'=>1,
                    "maintainance_schedule_date"=>$schedule_maintainance_date,
                    "next_maintainance_date"=>$next_main_date,
                    "has_maintainance_scheduled"=>1,

                );
                $where = array(
                    'id' => $record_id
                );
                $previous_data = getPreviousRecords('ar_asset_inventory', $where);
                $res = updateRecord('ar_asset_inventory', $previous_data, $where, $table_data, $user_id);
                
                $res['message']="Asset Maintainance Schedule Details have been saved successfully";
                $this->insert_into_asset_history($record_id,"scheduled for maintainance");
            } else{
                $res=[
                    "success"=>false,
                     "message"=> "Maintainance Schedule available for asset"
                ];
            }
            }
           
          
            // if (recordExists($inventory_details_table_name, $where)) {
            //     $params=[];
            //     $previous_data = getPreviousRecords($inventory_details_table_name, $where);
            //     $params['status_id']=$status_id;
            //     $params['updated_at'] = Carbon::now();
            //     $params['updated_by'] = $user_id;
            //     $res=updateRecord($inventory_details_table_name, $previous_data, $where, $params, $user_id);
            //     $res['message']="Asset Maintainance Schedule Details have been saved successfully";           
            //     }
    
          // },5);
           }
            catch (\Exception $exception) {
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
    
    public function saveAssetRepairScheduleDetails(Request $request)
    {

        try {
           
            DB::transaction(function() use ($request, &$res) {
            $repair_record_id=$request->input('repair_record_id');
            $date_repair_completed=$request->input('date_repair_completed');
            $record_id = $request->input('asset_id');
            if (!validateisNumeric($record_id)) {
                $record_id = $request->input('parent_asset_id');
            }
           
            //$repair_location_id= $request->input('repair_location_id');
            $status_id=8;
            $user_responsible=$request->input('user_responsible');
            $schedule_repair_date=$request->input("scheduled_repair_date");
            $cost=$request->input('repair_cost');
            $repair_status=$request->input('repair_status_form');
            $user_id= $this->user_id;
            $table_name ='ar_asset_repairs';
            $inventory_details_table_name = 'ar_asset_inventory';
            $repair_wether_successful=$request->input('repair_successful');
           
           
           
            if(isset($repair_record_id) && $repair_record_id!=""){
                $where = array(
                    'id' => $repair_record_id
                );
                    if( (isset($user_responsible) && $user_responsible!="") && $date_repair_completed=="")
                    {
                    $table_data=[];
                    //$table_data['location_id']=$repair_location_id;
                    $table_data['user_responsible']=$user_responsible;
                    $table_data['repair_status']=1;
                    $table_data['updated_at'] = Carbon::now();
                    $table_data['updated_by'] = $user_id;
                    $previous_data = getPreviousRecords($table_name, $where);
                    $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                    
                    $new_asset_update = array(
                        'status_id' =>8,
                
                    );
                    $where=array(
                        'id'=>$record_id
                    );
                $previous_data = getPreviousRecords('ar_asset_inventory', $where);
                $res = updateRecord('ar_asset_inventory', $previous_data, $where,  $new_asset_update , $user_id);
                    // DB::table('ar_asset_inventory')
                    //     ->where('id', $record_id)
                    //     ->update($asset_update);//under maintainance
                $res['message']="Asset Repair Dispatch Details have been saved successfully";
                $this->insert_into_asset_history($record_id,"dispatched for repair");
                }else if(isset($date_repair_completed) && $date_repair_completed!="")
                {   
                    
                    $table_data=[];
                    $table_data['date_repair_completed']=$date_repair_completed;
                    $table_data['funding_id']=$request->input('funding_id');
                    $table_data['repair_cost']=$cost;
                    $table_data['repair_status']=2;
                    $table_data['repair_successful']= $repair_wether_successful;
                    $table_data['updated_at'] = Carbon::now();
                    $table_data['updated_by'] = $user_id;
                   // $table_data['after_repair_remarks']=$after_repair_remarks;

                    $previous_data = getPreviousRecords($table_name, $where);
                    $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                   
                    if($repair_wether_successful!=1)
                    {
                        $asset_update = array(
                            'status_id' =>10,
                            "scheduled_for_repair"=>0
                        );
              
                    }else{
                        $asset_update = array(
                            'status_id' =>1,
                            "scheduled_for_repair"=>0
                        );
                    }
                    DB::table('ar_asset_inventory')
                        ->where('id', $record_id)
                        ->update($asset_update);//make item available
                $res['message']="Asset Repair Report Details have been saved successfully";
                           //update repair status to under repair for damged asset in that table
                           DB::table('ar_asset_loss_damage_details')->where('asset_id',$record_id)
                           ->where('repair_status',1)->update(['repair_status'=>2]);//complted and unsuccess where had been scheduled
                $this->insert_into_asset_history($record_id,"repair report added");

                }else{
                   
                    if($repair_status==1){
                    $where = array(
                        'id' => $repair_record_id
                    );
                    $table_data=[
                       
                        "scheduled_repair_date"=>$schedule_repair_date,  
                       // "repair_status_form"=>$repair_status,
                    ];
                    $table_data['updated_at'] = Carbon::now();
                    $table_data['updated_by'] = $user_id;
                    $previous_data = getPreviousRecords($table_name, $where);
                    $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                    $res['message']="Asset Repair Schedule Details have been saved successfully";
                    $this->insert_into_asset_history($record_id,"repair schedule details updated");
                     //update repair status to under repair  for damged asset in that table
              //updated data above 
              //below canceld
                     }
                    if($repair_status==3)
                    {
                       
                        $where=[
                            'id'=>$record_id
                        ];
                        $asset_update_data['status_id']=5;//return to damaged
                       
                        $asset_update_data['scheduled_for_repair']=3;//return to unschedule; 
                        $asset_update_data['updated_at'] = Carbon::now();
                        $asset_update_data['updated_by'] = $user_id;
                        $previous_data = getPreviousRecords('ar_asset_inventory', $where);
                        $res = updateRecord('ar_asset_inventory', $previous_data, $where, $asset_update_data, $user_id);
                         //update repair status to canceled for damged asset in that table
                DB::table('ar_asset_loss_damage_details')->where('asset_id',$record_id)
                ->whereIn('repair_status',[1,2])->update(['repair_status'=>0]);
                //where is scheduled
                DB::table('ar_asset_repairs')->where('asset_id',$record_id)->where('repair_status',0) 
                ->OrderBy('created_at',"DESC")
                ->update(['repair_status'=>3]);//update canceled
                        $this->insert_into_asset_history($record_id,"repair schedule canceled");
                        DB::table('ar_asset_loss_damage_details')->where('asset_id',$record_id)
                        ->where('repair_status',1)->update(['repair_status'=>0]);
                       //not at any juncture to insert a report damage/loss if asset is there or is in repair.
                    }
                }
            }else{//insert operation
                
                $table_data=[
                    "asset_id"=>$record_id,
                    //"remarks"=>$remarks,
                    "created_by"=>$user_id,
                    "created_at"=>carbon::now(),
                    "scheduled_repair_date"=>$schedule_repair_date,  
                ];
              
                $check=DB::table('ar_asset_repairs')->where('asset_id',$record_id)->whereIn('repair_status',[0,1,3])->count();
                      
                    if($check==0) {
                      
                        $res=insertRecord('ar_asset_repairs',$table_data,$user_id);
                       
                        //update repair status to  repair  scheduled for damged asset in that table
                        DB::table('ar_asset_loss_damage_details')->where('asset_id',$record_id)
                            ->where('repair_status',0)->update(['repair_status'=>1]);
                    }else{
                      
                        $qry_status=DB::table('ar_asset_repairs')->where('asset_id',$record_id)->whereIn('repair_status',[3])
                        ->selectRaw('id as transaction_id')->get()->toArray();
                       
                            if( count($qry_status)==1) {
                                    $res=Db::table('ar_asset_repairs')->where('id',$qry_status[0]->transaction_id)->update(
                                        [
                                            "scheduled_repair_date"=>$schedule_repair_date,
                                            "repair_status"=>0,
                                        
                                            "updated_at"=> Carbon::now(),
                                            "updated_by"=>$this->user_id
                                        ]
                                        );
                                      //update repair status to  repair  scheduled for damged asset in that table
                                DB::table('ar_asset_loss_damage_details')->where('asset_id',$record_id)
                                ->where('repair_status',0)->update(['repair_status'=>1]);
                                    
                                
                            }else{
                                $res=array(
                                    "success"=>false,
                                    "message"=>"Asset already scheduled for repair"
                                );
                            }
                     
                     
                        //new loc
                       
                        //emd new oc
                   
             }
                //previos loc
                if($res==1 || $res['success']==true){
                    $table_data = array(
                        "scheduled_for_repair"=>1,
                        //"repair_status_form"=>$repair_status

                    );
                  
                    $where = array(
                        'id' => $record_id
                    );
                    $previous_data = getPreviousRecords('ar_asset_inventory', $where);
                    $res = updateRecord('ar_asset_inventory', $previous_data, $where, $table_data, $user_id);
            
                    if($res['success']==true){
                  
                    $res['message']="Asset Repair Schedule Details have been saved successfully"; 
                    $this->insert_into_asset_history($record_id,"scheduled for repair");
                    }else{
                        $res=array(
                            "success"=>false,
                            "message"=>"There was an error saving the record"
                        );
                    }

                }
            }
        

            
           
        
            // if (recordExists($inventory_details_table_name, $where)) {
            //     $params=[];
            //     $previous_data = getPreviousRecords($inventory_details_table_name, $where);
            //     $params['status_id']=$status_id;
            //     $params['updated_at'] = Carbon::now();
            //     $params['updated_by'] = $user_id;
            //     $res=updateRecord($inventory_details_table_name, $previous_data, $where, $params, $user_id);
            //     $res['message']="Asset Repair Schedule Details have been saved successfully";           
            //     }
    
           },5);
           }
            catch (\Exception $exception) {
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
    
    public function saveAssetDisposalDetails(Request $request)
    {
        try {
            DB::transaction(function() use ($request, &$res) {
            $record_id = $request->input('asset_id');
            $status_id=9;
            $remarks=$request->input('remarks');
            $date_disposed=$request->input("date_of_disposal");
            $disposal_method=$request->input('disposal_method');
            $disposal_reason=$request->input('disposal_reason');
            $user_id= $this->user_id;
            $inventory_details_table_name = 'ar_asset_inventory';
            $table_data=[
                "asset_id"=>$record_id,
                "remarks"=>$remarks,
                "created_by"=>$user_id,
                "date_of_disposal"=>$date_disposed,  
                "disposal_method"=>$disposal_method,
                "disposal_reason"=>$disposal_reason

            ];
        
           $res= insertRecord('ar_asset_disposal_details',$table_data,$user_id);
            $where = array(
                'id' => $record_id
            );
            $this->insert_into_asset_history($record_id,"disposed");
            if (recordExists($inventory_details_table_name, $where)) {
                $params=[];
                $previous_data = getPreviousRecords($inventory_details_table_name, $where);
                $params['status_id']=$status_id;
                $params['updated_at'] = Carbon::now();
                $params['updated_by'] = $user_id;
               $res=updateRecord($inventory_details_table_name, $previous_data, $where, $params, $user_id);
               $res['message']="Asset Disposal Details have been saved successfully";           
                }
    
            },5);
           }
            catch (\Exception $exception) {
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
    public function saveAssetWriteOffDetails(Request $request)
    {
        try {
            DB::transaction(function() use ($request, &$res) {
            $record_id = $request->input('asset_id');
            $status_id=6;
            $remarks=$request->input('remarks');
            $date_written_off=$request->input("date_written_off");
            $write_off_reason=$request->input('write_off_reason');
            $user_id= $this->user_id;
            $inventory_details_table_name = 'ar_asset_inventory';
            $table_data=[
                "asset_id"=>$record_id,
                "remarks"=>$remarks,
                "created_at"=>Carbon::now(),
                "created_by"=>$user_id,
                "date_written_off"=>$date_written_off,  
                "write_off_reason"=>$write_off_reason
            ];
            insertRecord('ar_asset_write_off_details',$table_data,$user_id);
            $where = array(
                'id' => $record_id
            );
           
            if (recordExists($inventory_details_table_name, $where)) {
                $params=[];
                $previous_data = getPreviousRecords($inventory_details_table_name, $where);
                $params['status_id']=$status_id;
                $params['updated_at'] = Carbon::now();
                $params['updated_by'] = $user_id;
               $res=updateRecord($inventory_details_table_name, $previous_data, $where, $params, $user_id);
               $res['message']="Asset Write-off Details has been saved successfully";           
                }
    
            },5);
           }
            catch (\Exception $exception) {
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
    public function saveAssetLossDamageDetails(Request $request)
    {
      
            try {
     //DB::transaction(function() use ($request, &$res) {
        $record_id = $request->input('asset_id');
        $lost_damage_id=$request->input('lost_damaged_id');
        $remarks=$request->input('remarks');
        $date_lost_damaged=$request->input("date_lost_damaged");
        $user_id= $this->user_id;
        $inventory_details_table_name = 'ar_asset_inventory';
        $status_id=$request->input('status_id');
      //return $request->all();
        $table_data=[
            "asset_id"=>$record_id,
            "lost_damaged_id"=>$lost_damage_id,
            "lost_damaged_site_id"=>$request->input('lost_damaged_in_site_id'),
            "lost_damaged_location_id"=>$request->input('lost_damaged_in_location_id'),
            "lost_damaged_department_id"=>$request->input('lost_damaged_in_department_id'),
            "individuals_responsible"=>$request->input('user_id'),
            "remarks"=>$remarks,
            "reported_by"=>$user_id,
            "loss_damage_date"=>$date_lost_damaged,
            "created_at"=>Carbon::now()
        ];
      
       
       
        $verb="Loss";
        if($lost_damage_id==5)
        {
            $verb="Damage";
        }

        $check="";
        $record_checkout_asset_ids=[];
      
        if($lost_damage_id==4)
        {
            if($status_id==2)
            {   //$record_id=171;
                $count=DB::table('ar_asset_checkout_details')->where('asset_id',$record_id)->where('checkout_status',1)->count();
              
                if($count==1){
                $result= DB::table('ar_asset_checkout_details')->where('asset_id',$record_id)->where('checkout_status',1)
                   ->update(['checkout_status'=>3,"lost_items"=>$record_id]);//symbolizes a loss;
                  
            
                }else{
                 
                   $data= DB::table('ar_asset_checkout_details')->orwhere('is_individual_bulk_checkout',1)
                   ->orwhere('is_site_bulk_checkout',1)
                   ->where('asset_id','like',"%".$record_id."%")
                   ->selectraw('asset_id,id')
                   ->where('checkout_status',1)
                   ->get()->toArray();
                   $checkout_id="";
                   
                   foreach($data as $specific_record)
                   {
                       $specific_record_asset_ids=$this->returnArrayFromStringArray($specific_record->asset_id);
                       foreach($specific_record_asset_ids as $asset_id)
                       {
                           if($asset_id==$record_id)
                           {
                            $checkout_id= $specific_record->id;
                            $record_checkout_asset_ids=$specific_record_asset_ids;
                            break;
                           }
                       }


                   }
                 
                   if($lost_damage_id==4){//lost
                   $lost_item_value= DB::table('ar_asset_checkout_details')->where('id',$checkout_id)->value('lost_items');
                   if($lost_item_value==null || $lost_item_value=="")
                   {
                       $val="[".$record_id."]";
                         $res=DB::table('ar_asset_checkout_details')->where('id',$checkout_id)
                        ->update(["lost_items"=>$val]);
                       
                        $asset_index=array_search($record_id,$record_checkout_asset_ids);
                        unset($record_checkout_asset_ids[$asset_index]);
                        if(count($record_checkout_asset_ids)==0)
                        {
                            DB::table('ar_asset_checkout_details')->where('id',$checkout_id)
                            ->update(["checkout_status"=>3]);
                        }
                      
                    }else{
                        $lost_items=$this->returnArrayFromStringArray($lost_item_value);
                        $lost_items[]=$record_id;
                        $lost_items_string="[".(implode(",",$lost_items))."]";
                        DB::table('ar_asset_checkout_details')->where('id',$checkout_id)
                        ->update(["lost_items"=>$lost_items_string]);
                        foreach($lost_items as $item_id)
                        {
                            $asset_index=array_search($item_id,$record_checkout_asset_ids);
                            unset($record_checkout_asset_ids[$asset_index]);
                        }
                      
                        if(count($record_checkout_asset_ids)==0)
                        {
                            DB::table('ar_asset_checkout_details')->where('id',$checkout_id)
                            ->update(["checkout_status"=>3]);
                        }

                    }
                   }

                //    if($lost_damage_id==5){
                //        //date lost to be used to update lost item
                //     //$damaged_item_value= DB::table('ar_asset_checkout_details')->where('id',$checkout_id)->value('damaged_items');    
                //     if(  $damaged_item_value==null)
                //     {
                //         $val="[".$record_id."]";
                //           DB::table('ar_asset_checkout_details')->where('id',$checkout_id)
                //          ->update(["damaged_items"=>$val]);
                //      }else{
                //          $damaged_items=$this->returnArrayFromStringArray($damaged_item_value);
                //          $damaged_items[]=$record_id;
                //          $damaged_items_string="[".(implode(",",$damaged_items))."]";
                //          DB::table('ar_asset_checkout_details')->where('id',$checkout_id)
                //          ->update(["damaged_items"=>$lost_items_string]);
                //      }
                //     }
                   
                
                   
                //    ->selectRaw(
                //         "@checkout_id:=(select id from  ar_asset_checkout_details where asset_id ".$like." ".$record_id.") as checkout_id"
                //     )->get();
                   
                }
             
            }
        }
        
        if($lost_damage_id==4){
        $check=DB::table('ar_asset_loss_damage_details')->whereIn('repair_status',[0,1])->where('asset_id',$record_id)
        ->count();
        }else{
            $check=DB::table('ar_asset_loss_damage_details')->whereIn('repair_status',[0])
            ->where('asset_id',$record_id)
            ->where('lost_damaged_id',5)->count();
        }
      
        if($check==0){
            if($lost_damage_id==4){//lost create docs
            $folder_id=Db::table('ar_asset_inventory')->where('id',$record_id)->value('folder_id');
        
            createAssetRegisterDMSModuleFolders($folder_id, 34,41, $this->dms_id);
         
            $request->merge(['dms'=>$this->dms_id,"comment"=>"none","folder_id"=>$folder_id]);
            $dms=new  DmsController();
            $dms->addDocumentNoFolderIdForAssetLoss(  $request->input('parent_folder_id'),$request->input('sub_module_id'),
            $this->dms_id,$request->input('name'),"None",$request->versioncomment);
          
             //$dms->addDocumentNoFolderId($request);
            }
           
        $res=insertRecord('ar_asset_loss_damage_details',$table_data,$user_id);
     
        }else{
            $res=array(
                "success"=>false,
                "message"=>"Asset Records Already Exist"
            );
        }
        $where = array(
            'id' => $record_id
        );
        
        if (recordExists($inventory_details_table_name, $where)) {
            $params=[];
            $previous_data = getPreviousRecords($inventory_details_table_name, $where);
            $params['status_id']=$lost_damage_id;
            $params['site_id']=$request->input('lost_damaged_in_site_id');
            $params['location_id']=$request->input('lost_damaged_in_location_id');
            $params['department_id']=$request->input('lost_damaged_in_department_id');
            $params['updated_at'] = Carbon::now();
            $params['updated_by'] = $user_id;
            $res=[];
           $res=updateRecord($inventory_details_table_name, $previous_data, $where, $params, $user_id);
           $res['message']="Asset Loss/Damage Details has been saved successfully"; 
           $this->insert_into_asset_history($record_id,$verb." reported");
           
            }

        //},5);
       }
        catch (\Exception $exception) {
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
   
    

    public function  saveAssetRequisitionRequestDetails(Request $request)
    {
        
       
            try {
            $res = array();
            $request_date=$request->input('request_date');
            $requested_by =$this->user_id;
            $site_id=$request->input('site_id');
            $location_id=$request->input('location_id');
            $department_id=$request->input('department_id');
            $request_for=$request->input('request_for_id');
            $item_category=$request->input('category_id');
            $record_id=$request->input('id');
            $asset_quantity=$request->input('asset_quantity_item');
            $user_id= $this->user_id;
            $request_type="";
            
            if($request_for==1)
            {
                $request_type=$request->input('request_type');
                if($request_type==2)
                {
                    $user_id=$request->input('individual_id');
                    $requested_by=$request->input('individual_id');
                }
            }

            
                $table_data=[
                    "request_date"=>$request_date,
                    "requistion_site_id"=>$site_id,
                    "requistion_location_id"=>$location_id,
                    "requistion_department_id"=>$department_id,
                    "requested_for"=>$request_for,
                    "asset_category"=>$item_category,
                    "created_at"=>Carbon::now(),
                    "created_by"=> $this->user_id

            ];

            if($request_for==1)
            {
                $request_type=$request->input('request_type');
                if($request_type==2)
                {
                    
                    $table_data['onbehalfof']=1;
                }
            }
            $active_requisitions_of_category_not_checked_out=Db::table('ar_asset_requisitions as t1')
            ->where('asset_category','=',$item_category)//the category is asset sub category late change thus mismatch in name
            ->where('requisition_status','=',1)->count();

            $item_count_pending="";
            $item_count="";
            if($request_for==1){
            $item_count_pending=Db::table('ar_asset_requisitions as t1')
            ->where('asset_category','=',$item_category)
            ->where('t1.requested_by','=',$user_id)
            ->where('t1.requested_for',$request_for)
            ->where('requisition_status','=',1)->count();
            $item_count=Db::table('ar_asset_requisitions as t1')
            ->where('asset_category','=',$item_category)
            ->where('t1.requested_by','=',$user_id)
            ->where('requisition_status','=',2)->count();
            }else{
            $item_count_pending=Db::table('ar_asset_requisitions as t1')//pending assignments
            ->where('asset_category','=',$item_category)
            ->where('t1.requistion_site_id','=',$site_id)
            ->where('t1.requested_for',$request_for)
            ->where('requisition_status','=',1)->count();
            $item_count=Db::table('ar_asset_requisitions as t1')
            ->where('asset_category','=',$item_category)
            ->where('t1.requistion_site_id','=',$site_id)
            ->where('requisition_status','=',2)->count();
            }
            
            $actual_item_category=DB::table('ar_asset_subcategories')->where('id',$item_category)->value('category_id');
            $if_multiple_checkout=DB::table('ar_asset_categories')
                ->where('id',$actual_item_category)
                ->selectRaw('multiple_checkout')->get()->toArray()[0]->multiple_checkout;

            if($item_count>0 && $if_multiple_checkout==0)
            {   
                $table_data['requisition_status']=0;
                $table_data["remarks"]="Item assigment is Limited to One";
            }else{
            // $item_count_pending="0";//over rule rule ,remember to remove
                if($item_count_pending==0){
                    
                    $available_count=0;
                    $asset_count= Db::table('ar_asset_inventory')
                    ->where('sub_category_id','=',$item_category)
                    ->where('module_id',350)
                    ->where('status_id','=',1)->count();
                    if($active_requisitions_of_category_not_checked_out>$asset_count){
                    $available_count=$active_requisitions_of_category_not_checked_out-$asset_count;
                    }else{
                        $available_count=$asset_count-$active_requisitions_of_category_not_checked_out;
                    }
                
                    if($available_count>=1)
                    {
                        $asset_quantity=intval($asset_quantity);
                    
                    
                        if($asset_quantity==1){
                        $table_data['requisition_status']=5;//was 1 now 5
                        $table_data['verified']=1;
                        //$table_data["remarks"]="Asset  for assignment is available";
                        //$table_data["remarks"]="Asset  for assignment is available";
                        
                        }
                        if( $asset_quantity>1)
                        {
                        
                            if($asset_quantity>$available_count)
                            {
                                    $data_per_item_assignable=[
                                        "request_date"=>$request_date,
                                        "requistion_site_id"=>$site_id,
                                        "requistion_location_id"=>$location_id,
                                        "requistion_department_id"=>$department_id,
                                        "requested_for"=>$request_for,
                                        "asset_category"=>$item_category,
                                        "created_at"=>Carbon::now(),
                                        "created_by"=> $this->user_id,
                                        "verified"=>1,
                                        //"remarks"=>"Asset  for assignment is available",
                                        'requisition_status'=>5
                                ];
                                if($request_type==2)
                                {
                                
                                    $data_per_item_assignable['onbehalfof']=1;
                                }
                                $data_per_item_unassignable=[
                                    "request_date"=>$request_date,
                                    "requistion_site_id"=>$site_id,
                                    "requistion_location_id"=>$location_id,
                                    "requistion_department_id"=>$department_id,
                                    "requested_for"=>$request_for,
                                    "asset_category"=>$item_category,
                                    "created_at"=>Carbon::now(),
                                    "created_by"=> $this->user_id,
                                    "remarks"=>"Asset  for assignment is unavailable",
                                    'requisition_status'=>0
                                ];

                                if($request_type==2)
                                {
                                
                                    $data_per_item_unassignable['onbehalfof']=1;
                                }

                        
                            if($request_for==1)
                            {
                                $data_per_item_assignable['requested_by']=$requested_by;
                                $data_per_item_unassignable['requested_by']=$requested_by;
                            }
                            $table_data=[];     
                                $assignable=$available_count;
                                $unassignable= $asset_quantity-$available_count;
                                $combined_results=[$assignable,$unassignable];
                                $combined_data=[$data_per_item_assignable,$data_per_item_unassignable];
                                foreach ($combined_results as $key => $value) {
                                    for($i=1;$i<=$value;$i++)//vlaue is count
                                    {
                                        $table_data[]=$combined_data[$key];//use outer loop to get data for each of inner loop based on category
                                    }
                                    
                                }
                            
                            }else{
                                $table_data=[];
                                $assignable= $asset_quantity;
                                $data_per_item_assignable=[
                                    "request_date"=>$request_date,
                                    "requistion_site_id"=>$site_id,
                                    "requistion_location_id"=>$location_id,
                                    "requistion_department_id"=>$department_id,
                                    "requested_for"=>$request_for,
                                    "asset_category"=>$item_category,
                                    "created_at"=>Carbon::now(),
                                    "created_by"=> $this->user_id,
                                    "verified"=>1,
                                // "remarks"=>"Asset  for assignment is available",
                                    'requisition_status'=>5
                            ];
                            if($request_type==2)
                            {
                                
                                $data_per_item_assignable['onbehalfof']=1;
                            }
                                    //if($request_for==1 && $asset_quantity==1)
                                    if($request_for==1)
                                    {
                                        $data_per_item_assignable['requested_by']=$requested_by;
                                        $data_per_item_unassignable['requested_by']=$requested_by;
                                    }

                                    for($i=1;$i<=$assignable;$i++)
                                            {
                                                $table_data[]=$data_per_item_assignable;//use outer loop to get data for each of inner loop based on category
                    
                                            }

                            }
                            
                        }
                    
                    }else{
                        $table_data['requisition_status']=0;
                    
                        $table_data["remarks"]="Asset  is Unavailable";
                    }
                }else{
                $table_data['requisition_status']=0;
                $table_data["remarks"]="There's a pending  assigment for the Requested Asset";
                }
            }
        
            //$table_data['requisition_status']=1;//to bre removed,just for time saving
            if($request_for==1 && $asset_quantity==1)
            {
                $table_data['requested_by']=$requested_by;
            }
            if(isset($record_id) && $record_id!="")
            {
                $res["message"]="Requisition Request Details already saved";

            }else{
            $res=DB::table('ar_asset_requisitions')->insert($table_data);
                if($res==true)
                {
                    $res=array(
                        "success"=>true,
                        "record_id"=>0
                    );
                }else{
                    $res=array(
                        "success"=>false,
                    );
                }
            //$res = insertRecord('ar_asset_requisitions', $table_data,$this->user_id);

            if($res['success']==true)
            {
                $res["message"]="Requisition Request Details saved";
            }else{
                $res["message"]="Error while saving  Requisition Request Details";
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
    private function insert_into_asset_history($asset_id,$action)
    {
        $table_data['asset_id']=$asset_id;
        $table_data['action']=$action;
        $table_data['created_by']=$this->user_id;
        $table_data['created_at']=Carbon::now();
        $table_name="ar_asset_history";

        DB::table($table_name)->insert($table_data);

    }
    public function saveSiteBulkCheckoutDetails(Request $request)
    {
        try {
            $data=$request->input('data');
            $data= json_decode($data,TRUE); 
            $user_id = $this->user_id;
            $bulk_insert_table_data=[];
            $array_of_requisition_ids=[];
            $array_of_asset_ids_string="[";
            $array_of_requisition_ids_string="[";
            $array_of_asset_ids2=[];
            $full_location_status_details=[];
            foreach($data as $key=>$input_data)
            {
                if($key=0)
                {
                    $full_location_status_details=[
                        "site_id"=>$input_data['checkout_site_id'],
                        "location_id"=>$input_data['checkout_location_id'],
                        "department_id"=>$input_data['checkout_location_id']
                    ];

                }
                $array_of_requisition_ids[]=$input_data['requisition_id'];
               // $table_data['checkout_site_id'] = $input_data['checkout_site_id'];
                $table_data['checkout_location_id'] = $input_data['checkout_location_id'];
                $table_data['checkout_department_id'] = $input_data['checkout_location_id'];
                $table_data['checkout_site_id'] = $input_data['requistion_site_id'];  
               
                $table_data['checkout_date']=$input_data['checkout_date'];
                $table_data['no_due_date']=$input_data['no_due_date'];
                $table_data['site_asset_individual_responsible']=$input_data['site_asset_individual_responsible'];
                if($input_data['no_due_date']==0){
                    $table_data['due_date']=$input_data['due_date'];
                }
                $array_of_asset_ids2[]=$input_data['asset_id'];
                if($key<(count($data)-1)){
                $array_of_asset_ids_string.=$input_data['asset_id'].',';
                $array_of_requisition_ids_string.=$input_data['requisition_id'].',';

                }else{
                    $array_of_asset_ids_string.=$input_data['asset_id'];
                    $array_of_requisition_ids_string.=$input_data['requisition_id'];

                }
               
                $table_data['is_site_bulk_checkout']=1;
                
            }
            if(substr(  $array_of_asset_ids_string, -1)==",")
            {
                $array_of_asset_ids_string=substr_replace($array_of_asset_ids_string, "", -1);
            }
            if(substr(   $array_of_requisition_ids_string, -1)==",")
            {
                $array_of_requisition_ids_string=substr_replace( $array_of_requisition_ids_string, "", -1);
            }

            $array_of_asset_ids_string.="]";
            $array_of_requisition_ids_string.="]";
            $table_data['asset_id']=$array_of_asset_ids_string;
            $table_data['requisition_id']=$array_of_requisition_ids_string;
           $res = insertRecord('ar_asset_checkout_details', $table_data, $user_id);
                
            
          
            if ($res['success'] == true) {
                $asset_ids_array=$this->returnArrayFromStringArray($array_of_asset_ids_string);
                foreach($asset_ids_array as $asset_id_insert)
                {
                    $this->insert_into_asset_history($asset_id_insert,"Checked-Out");
                }
              
                
               
                $res=DB::table('ar_asset_requisitions')
                  ->whereIn('id',$array_of_requisition_ids)
                  ->update(['requisition_status'=>'2',"is_site_bulk_checkout"=>"1",
                  "checkout_id"=>$res['record_id'],
                  "updated_at"=>Carbon::now(),"updated_by"=> $user_id]);//set checked out
                 
               
                  //should be looked at why it returns false instead of true above query despite update
                  $full_location_status_details['status_id']=2;//update to two on 11/1/2022 job
                 $res= DB::table('ar_asset_inventory')
                  ->whereIn('id',$array_of_asset_ids2)
                  ->update($full_location_status_details);
                 
                
                   
                    $res=[];
                    $res['success'] =true;
                    $res['message'] ="Check-Out Data saved successfully";
                  
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
   public function saveIndividualBulkCheckout(Request $request)
    {
        try {
          
           
            $data=$request->input('data');
            $data= json_decode($data,TRUE); 
            $user_id = $this->user_id;
            $bulk_insert_table_data=[];
            $array_of_requisition_ids=[];
            $array_of_asset_ids_string="[";
            $array_of_requisition_ids_string="[";
            $array_of_asset_ids2=[];
            $full_location_status_details=[];

            $email_data=[];
            $email_assets=[];
            $full_email_data=[];
            $send_email=0;
        
            foreach($data as $key=>$input_data)
            {
                if($key==0)
                {
                    $full_location_status_details=[
                        "site_id"=>$input_data['checkout_site_id'],
                        "location_id"=>$input_data['checkout_location_id'],
                        "department_id"=>$input_data['checkout_department_id']
                    ];
                 
                    if($input_data['send_email']==1)
                    {
                        $send_email=1;
                        $user_last_name=DB::table('users')->where('id',$this->user_id)->selectRaw('decrypt(last_name)')->value('last_name');
                        $user_details=DB::table('users')->where('id',$this->user_id)->selectRaw('decrypt(last_name) as last_name,decrypt(email) as email')->get();
                        $user_details=convertStdClassObjToArray($user_details);
                        $user_details=$user_details[0];
                        //$email_data['user_last_name']=$user_last_name;
                        $email_data['user_last_name']=$user_details['last_name'];
                        //$email_data['user_email']=$input_data['user_email'];
                        $email_data['user_email']=$user_details['email'];
                        $email_data['checkout_date']=$input_data['checkout_date'];
                        $email_data['no_due_date']=$input_data['no_due_date'];
                        if($input_data['no_due_date']==0){
                            
                            $email_data['due_date']=$input_data['due_date'];

                           
                        }
                    }
                   

                }
                    if($input_data['send_email']==true)
                    {   
                        $identifier_name="";
                        $send_array=array(
                            "description"=>$input_data['description'],
                            //"serial_no"=>$input_data['serial_no']
                        );
                        if($input_data['serial_no']!="")
                        {
                            $send_array["serial_no"]=$input_data["serial_no"];
                            $identifier_name=Db::table('ar_asset_inventory as t1')
                            ->join('ar_asset_identifiers as t2','t1.identifier_id','t2.id')
                            ->where('t1.serial_no',$input_data['serial_no'])
                            ->value('t2.name');
                        }else{
                            $send_array["grz_no"]=$input_data['grz_no'];
                            $identifier_name=Db::table('ar_asset_inventory as t1')
                            ->join('ar_asset_identifiers as t2','t1.identifier_id','t2.id')
                            ->where('t1.grz_no',$input_data['grz_no'])
                            ->value('t2.name');
                        }
                       
                        $send_array['identifier']=$identifier_name;
                    $email_assets[]=$send_array;
                    }
                $array_of_requisition_ids[]=$input_data['requisition_id'];
                $table_data['checkout_site_id'] = $input_data['checkout_site_id'];
                $table_data['checkout_location_id'] = $input_data['checkout_location_id'];
                $table_data['checkout_department_id'] = $input_data['checkout_department_id'];
                $table_data['user_id'] = $input_data['requisition_user_id'];  
                $table_data['send_email'] = $input_data['send_email'];
                $table_data['checkout_date']=$input_data['checkout_date'];
                $table_data['no_due_date']=$input_data['no_due_date'];
                
                if($input_data['no_due_date']==0){
                    $table_data['due_date']= $input_data['due_date'];
                   
                }
                $array_of_asset_ids2[]=$input_data['asset_id'];
                if($key<(count($data)-1)){
                $array_of_asset_ids_string.=$input_data['asset_id'].',';
                $array_of_requisition_ids_string.=$input_data['requisition_id'].',';

                }else{
                    $array_of_asset_ids_string.=$input_data['asset_id'];
                    $array_of_requisition_ids_string.=$input_data['requisition_id'];

                }
                //$table_data['asset_id']=$input_data['asset_id'];
                //$table_data['requisition_id']=$input_data['requisition_id'];
                $table_data['is_individual_bulk_checkout']=1;
                
            }
            if(substr(  $array_of_asset_ids_string, -1)==",")
            {
                $array_of_asset_ids_string=substr_replace($array_of_asset_ids_string, "", -1);
            }
            if(substr(   $array_of_requisition_ids_string, -1)==",")
            {
                $array_of_requisition_ids_string=substr_replace( $array_of_requisition_ids_string, "", -1);
            }
            $array_of_asset_ids_string.="]";
            $array_of_requisition_ids_string.="]";
            $table_data['asset_id']=$array_of_asset_ids_string;
            $table_data['requisition_id']=$array_of_requisition_ids_string;
            //email
            $full_email_data["email_data"]=$email_data;
            $full_email_data["assets"]= $email_assets;
           
         $res = insertRecord('ar_asset_checkout_details', $table_data, $user_id);
                
               // $bulk_insert_table_data[]=$table_data;
         
          $res['success']=true;
            if ($res['success'] == true) {
                $asset_ids_array=$this->returnArrayFromStringArray($array_of_asset_ids_string);
                foreach($asset_ids_array as $asset_id_insert)
                {
                    $this->insert_into_asset_history($asset_id_insert,"Checked-Out");
                }
                
               
                $res=DB::table('ar_asset_requisitions')
                  ->whereIn('id',$array_of_requisition_ids)
                  ->update(['requisition_status'=>'2',"is_individual_bulk_checkout"=>"1",
                  "checkout_id"=>$res['record_id'],
                  "updated_at"=>Carbon::now(),"updated_by"=> $user_id]);//set checked out
                 
               
                  //should be looked at why it returns false instead of true above query despite update
                  $full_location_status_details['status_id']=2;//checked-out not 1 as previous
                
                 $res= DB::table('ar_asset_inventory')
                  ->whereIn('id',$array_of_asset_ids2)
                  ->update($full_location_status_details);
                 
                  if($send_email==1)
                  {

                   $this->sendAssetCheckOutEmail($email_data['user_email'],"Asset Check-Out",$full_email_data,true);
                      
              //$this->sendAssetCheckOutEmail("murumbajob78@gmail.com","Asset Check-Out",$full_email_data,true);
                  }
                   
                    $res=[];
                    $res['success'] =true;
                    $res['message'] ="Check-Out Data saved successfully";
                  
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


public function saveMultipleIndividualsBulkCheckoutDetails(Request $request)
    {
        try{
        $user_id = $this->user_id;
        $individuals_to_assign = $request->input('users_for_asset');  
        $asset_id = $request->input('assets_for_individuals'); 
        //unique identfier name
        $identifier_name=Db::table('ar_asset_inventory as t1')
        ->join('ar_asset_identifiers as t2','t1.identifier_id','t2.id')
        ->where('t1.serial_no',$input_data['serial_no'])
        ->value('t2.name');
        //esnure no duplicates in requisitions ids from front end mixup
        $requisition_ids= $request->input('requisition_ids');
        $requisition_ids_array=explode(",",$requisition_ids);
        $unique_requistion_ids_array=array_unique($requisition_ids_array);
        $requisition_ids=implode(',',$unique_requistion_ids_array);
        
        $users=DB::table('users as t1')->whereIn('id',$this->returnArrayFromStringArray($individuals_to_assign))
        ->selectRaw('decrypt(t1.last_name) as last_name,decrypt(t1.email) as email')->get();
        $users=convertStdClassObjToArray($users);
      
        $site_id = $request->input('checkout_site_id');
        $location_id = $request->input('checkout_location_id');
        $department_id = $request->input('checkout_department_id');
        $table_data['checkout_site_id'] = $site_id;
        $table_data['checkout_location_id'] = $location_id;
        $table_data['checkout_department_id'] = $department_id;
        $table_data['user_id'] = $individuals_to_assign;
        $table_data['send_email']= $request->input('send_email');
        $table_data['checkout_date'] = $request->input('checkout_date');
        $table_data['no_due_date'] = $request->input('no_due_date');
        $table_data['due_date'] = $request->input('due_date');
        $table_data['asset_id'] = $asset_id;
        $table_data['requisition_id']="[".$requisition_ids."]";
        $table_data['is_group_checkout']=1;
        $res = insertRecord('ar_asset_checkout_details', $table_data, $user_id);

        $array_of_requisition_ids=explode(',',$requisition_ids);
        if(empty($array_of_requisition_ids[count($array_of_requisition_ids)-1])) {
            unset($array_of_requisition_ids[count($array_of_requisition_ids)-1]);
        }
        if ($res['success'] == true) {
           $res= DB::table('ar_asset_requisitions')
              ->whereIn('id',$array_of_requisition_ids)
              ->update(['requisition_status'=>'2',"is_group_checkout"=>"1","checkout_id"=>$res['record_id'],
              "updated_at"=>Carbon::now(),"updated_by"=> $user_id]);//set checked out
              //should be looked at why it returns false instead of true above query despite update
            $where=array(
                "id"=>$asset_id
            );
            //modified to two on 11/1/2021
           $params= ['status_id'=>2,'site_id'=>$site_id,"location_id"=>$location_id,"department_id"=>$department_id];
            
              $previous_data = getPreviousRecords('ar_asset_inventory', $where);
              $res = updateRecord('ar_asset_inventory', $previous_data, $where, $params, $user_id);


              if ($res['success'] == true) {

                if( $request->input('send_email')==true){
                    $users_email_details=[];
                    $asset=DB::table('ar_asset_inventory')->where('id',$asset_id)->selectRaw('description,serial_no,grz_no')->get();
                    $asset=convertStdClassObjToArray($asset);
                    $asset=$asset[0];
                    if($asset['serial_no']=="")
                    {
                        unset($asset['serial_no']);
                    }
                    
                    $counter=0;
                    $users_email_details=[];
                    foreach($users as $user)
                    {
                        $users_email_details[$counter]=array(
                            "user_email"=>$user['email'],
                            "last_name"=>$user['last_name'],
                            "checkout_date"=>$request->input('checkout_date'),
                            'no_due_date'=>$request->input('no_due_date'),
                            "asset"=>$asset,
                            "identifier"=>$identifier_name
                        );
            
                        if($request->input('no_due_date')==0 || $request->input('no_due_date')=="0")
                        {
                            $users_email_details[$counter]['due_date']=$request->input('due_date');
                        }
                        $counter+=1;
                      
                    }
                 $this->sendAssetCheckOutEmail("","Asset Check-Out",$users_email_details);
                }
                $this->insert_into_asset_history($asset_id,"Checked-Out");
                  $res=[];
                $res['success'] =true;
                $res['message'] ="Check-Out Data saved successfully";
              }else{
                  return $res;
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

    

    public function saveAssetApprovalDetails(Request $request)
    {
        try{
            $res=array();
        $requisition_record_id=$request->input('requisition_record_id');
        $asset_action_status=$request->input('asset_action_status');
        $rejection_reason=$request->input('rejection_reason');

        $update_data=[];
        $update_data=[
        "updated_at"=>Carbon::now(),
        "updated_by"=>$this->user_id
        ];
        if($asset_action_status==2)
        {   
            $update_data["requisition_status"]=0;
            $update_data['remarks']= $rejection_reason;
          
        }else{
            $update_data['remarks']= "Request Approved";
            $update_data["requisition_status"]=1;
        }
       $result= Db::table('ar_asset_requisitions')->where('id',$requisition_record_id)->update($update_data);
        if($result==true)
        {
            $res=array(
                "success"=>true,
                "message"=>"Requisition Details Saved"
            );
        }else{
            $res=array(
                "success"=>false,
                "message"=>"Error Saving Requisition Details"
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
    public function saveAssetInventoryDetails(Request $request)
    {
        try {
            $res = array();
            DB::transaction(function () use ($request, &$res) {
                $user_id = $this->user_id;
                $record_id = $request->input('record_id');
                $serial_no = $request->input('serial_no');
                $grz_no = $request->input('grz_no');
                $description = $request->input('description');
                $table_name = 'ar_asset_inventory';
                $params = array(
                    'description' => $description,
                    'category_id' => $request->input('category_id'),
                    'brand_id' => $request->input('brand_id'),
                    'model_id' => $request->input('model_id'),
                    'purchase_from' => $request->input('purchase_from'),
                    'purchase_date' => $request->input('purchase_date'),
                    'cost' => $request->input('cost'),
                    'supplier_id'=>$request->input('supplier_id'),//Job on 30/06/2022
                    'sub_category_id'=>$request->input('sub_category_id'),
                    'serial_no' => $serial_no,
                    'grz_no' => $grz_no,
                    'site_id' => $request->input('site_id'),
                    'location_id' => $request->input('location_id'),
                    'department_id' => $request->input('department_id'),
                    'identifier_id' => $request->input('identifier_id')//job on 26/5/2022
                );
                $where = array(
                    'id' => $record_id
                );
              
                if (validateisNumeric($record_id)) {
                    $previous_data = array();
                    if (recordExists($table_name, $where)) {
                        $params['updated_at'] = Carbon::now();
                        $params['updated_by'] = $user_id;
                        $previous_data = getPreviousRecords($table_name, $where);
                        $res = updateRecord($table_name, $previous_data, $where, $params, $user_id);
                        $this->insert_into_asset_history($record_id,"Details Updated");
                    }
                    $folder_id = $previous_data[0]['folder_id'];
                
                  
                } else {
                  $if_duplicate_serial_no=DB::table('ar_asset_inventory')->where('serial_no', $serial_no)->value('serial_no');
                if((strtolower($if_duplicate_serial_no))!=(strtolower($serial_no))){
                  
                  $params['created_at'] = Carbon::now();
                    $params['created_by'] = $user_id;
                    //todo DMS
                    //$parent_id = 226347;	was initial
                    //$parent_id=	226531;
                    $parent_id=	226347;
                     $parent_id=228976;
                    //$parent_id=226356;    dd($serial_no);
                    //job u
                    //createDMSParentFolder($parent_folder, $module_id, $name, $comment, $owner)

                    //$folder_id = createDMSParentFolder($parent_id,34 , $serial_no, '', $this->dms_id);
                    if($serial_no=="")
                    {
                        $serial_no= $grz_no;
                    }
                    $folder_id=createAssetDMSParentFolder($parent_id,34 , $serial_no, '', $this->dms_id);
                    //$folder_id = createDMSParentFolder($parent_id, '', $serial_no, '', $this->dms_id);
                    createAssetRegisterDMSModuleFolders($folder_id, 34,35, $this->dms_id);
                    //$folder_id = 226531;
                    //createDMSModuleFolders($folder_id, 35, $this->dms_id);
                    //end DMS
                    $params['view_id'] = generateRecordViewID();
                    $params['serial_no'] = $serial_no;
                    $params['folder_id'] = $folder_id;
                    $params['created_at'] = Carbon::now();
                    $params['created_by'] = $user_id;
                    $res = insertRecord($table_name, $params, $user_id);
                 }else{
                     $res=array(
                         "success"=>false,
                         "message"=>"Asset with matching Identifier exists"
                     );
                 }
                    if ($res['success'] == false) {
                      
                        return \response()->json($res);
                    }
                   
                    $record_id = $res['record_id'];
                    $this->insert_into_asset_history($record_id,"Added to inventory");
                }
                $res['description'] = $description;
                $res['grz_no'] = $grz_no;
                $res['serial_no'] = $serial_no;
                $res['folder_id'] = $folder_id;
                $res['record_id'] = $record_id;

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

    public function getAssetForLossDamageDetails(Request $request)
    {
        $status=$request->input('status');
        try{
            switch($status)
            {
                case "asset_insurance_claims":
                    $qry = DB::table('ar_asset_inventory as t1')
                    ->join('ar_asset_insurance_claims as t2','t2.asset_id','t1.id')
                    ->selectraw('t2.id as record_id,t2.policy_id, t1.id as asset_id,description,serial_no,claim_number,claim_amount,t2.folder_id');
                    $results = $qry->get()->toArray();
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($results),
                        'results' => $results
                    );
                    break;
                 case "stores_asset_insurance_claims":
                    $qry = DB::table('ar_asset_inventory as t1')
                    ->join('stores_asset_insurance_claims as t2','t2.asset_id','t1.id')
                    ->selectraw('t2.id as record_id,t2.policy_id, t1.id as asset_id,description,serial_no,claim_number,claim_amount,t2.folder_id');
                    $results = $qry->get()->toArray();
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($results),
                        'results' => $results
                    );
                    break;
                case "lost" :
                    $qry = DB::table('ar_asset_inventory as t1')
                    ->join('ar_asset_categories as t2', 't1.category_id', '=', 't2.id')
                    ->join('ar_asset_subcategories as t9', 't1.sub_category_id', '=', 't9.id')
                    ->leftjoin('ar_asset_brands as t3', 't1.brand_id', '=', 't3.id')
                    ->leftjoin('ar_asset_models as t4', 't1.model_id', '=', 't4.id')
                    ->join('ar_asset_sites as t5', 't1.site_id', '=', 't5.id')
                    ->leftjoin('ar_asset_locations as t6', 't1.location_id', '=', 't6.id')
                    ->leftjoin('departments as t7', 't1.department_id', '=', 't7.id')
                    ->join('ar_asset_statuses as t8', 't1.status_id', '=', 't8.id')
                    ->join('ar_asset_loss_damage_details as t10','t10.asset_id','t1.id')
                    ->selectRaw("t1.*, t1.id as record_id,t1.id as asset_id,t9.name as sub_category_name, t2.name as category_name, t3.name as brand_name, t4.name as model_name,t5.name as site_name,
                        t6.name as location_name,t7.name as department_name, t8.name as record_status,t10.loss_damage_date as lost_date,t10.individuals_responsible");
                    $qry->whereIn('status_id', [4]);   
                    $results = $qry->get()->toArray();
                    
                    foreach($results as $result)
                    {
                        $user_ids=$this->returnArrayFromStringArray($result->individuals_responsible);
                        $users_res=[];
                        
                        foreach($user_ids as $user_id)
                        {

                            // ->selectRaw("t1.id,
                            // CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.middlename),decrypt(t1.last_name)) as name")->get();

                            $user = DB::table('users as t1')
                            ->where('id',$user_id)
                            ->selectRaw("t1.id,CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.middlename),decrypt(t1.last_name),decrypt(t1.email)) as name")->get();
                           
                            $user=$user->toArray();
                            $users_res[]=$user[0]->name;
                        }
                       $result->individuals_responsible=implode(",",$users_res);

                    }
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($results),
                        'results' => $results
                    );
                    break;
                case "stores_lost" :
                        $qry = DB::table('ar_asset_inventory as t1')
                        ->join('stores_categories as t2', 't1.category_id', '=', 't2.id')
                        ->join('stores_subcategories as t9', 't1.sub_category_id', '=', 't9.id')
                        ->leftjoin('stores_brands as t3', 't1.brand_id', '=', 't3.id')
                        ->leftjoin('stores_models as t4', 't1.model_id', '=', 't4.id')
                        ->join('stores_sites as t5', 't1.site_id', '=', 't5.id')
                        ->leftjoin('stores_locations as t6', 't1.location_id', '=', 't6.id')
                        ->leftjoin('stores_departments as t7', 't1.department_id', '=', 't7.id')
                        ->join('stores_statuses as t8', 't1.status_id', '=', 't8.id')
                        ->join('stores_asset_loss_damage_details as t10','t10.asset_id','t1.id')
                        ->selectRaw("t1.*, t1.id as record_id,t1.id as asset_id,t9.name as sub_category_name, t2.name as category_name, t3.name as brand_name, t4.name as model_name,t5.name as site_name,
                            t6.name as location_name,t7.name as department_name, t8.name as record_status,t10.loss_damage_date as lost_date,t10.individuals_responsible");
                        $qry->whereIn('status_id', [4]);   
                        $results = $qry->get()->toArray();
                        
                        foreach($results as $result)
                        {
                            $user_ids=$this->returnArrayFromStringArray($result->individuals_responsible);
                            $users_res=[];
                            
                            foreach($user_ids as $user_id)
                            {
                                $user = DB::table('users as t1')
                                ->where('id',$user_id)
                                ->selectRaw("t1.id,CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.middlename),decrypt(t1.last_name),decrypt(t1.email)) as name")->get();
                               
                                $user=$user->toArray();
                                $users_res[]=$user[0]->name;
                            }
                           $result->individuals_responsible=implode(",",$users_res);
    
                        }
                        $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                        );
                        break;
                case "damaged" :
                    
                        $qry = DB::table('ar_asset_inventory as t1')
                        ->join('ar_asset_categories as t2', 't1.category_id', '=', 't2.id')
                        ->join('ar_asset_subcategories as t9', 't1.sub_category_id', '=', 't9.id')
                        ->leftjoin('ar_asset_brands as t3', 't1.brand_id', '=', 't3.id')
                        ->leftjoin('ar_asset_models as t4', 't1.model_id', '=', 't4.id')
                        ->join('ar_asset_sites as t5', 't1.site_id', '=', 't5.id')
                        ->leftjoin('ar_asset_locations as t6', 't1.location_id', '=', 't6.id')
                        ->leftjoin('departments as t7', 't1.department_id', '=', 't7.id')
                        ->join('ar_asset_statuses as t8', 't1.status_id', '=', 't8.id')
                        
                        //->leftjoin('ar_asset_repairs as t9','t1.id','=','t9.asset_id')repair_status,t10.loss_damage_date 
                            //as damage_date_of_asset
                       // ->leftjoin('ar_asset_loss_damage_details as t10','t10.asset_id','=','t1.id')
                        ->selectRaw("t1.*, t1.id as record_id,t1.id as asset_id, t2.name as category_name, t3.name as brand_name, t4.name as model_name,t5.name as site_name,
                            t6.name as location_name,t7.name as department_name, t8.name as record_status,t9.name as sub_category_name");
                           // ->orwhere('t10.repair_scheduled',0);
                            //->orwhereNotIn('t10.repair_scheduled',[1,2]);
                        
                        $qry->whereIn('status_id', [5]);   
                        $results = $qry->get();
                       
                        $loss_damage_details=DB::table('ar_asset_loss_damage_details')->selectRaw('loss_damage_date as damage_date_of_asset,asset_id,individuals_responsible')->orderBy('created_at','DESC')->get();
                        $repair_details=DB::table('ar_asset_repairs')->selectRaw('repair_status,asset_id,scheduled_repair_date')->orderBy('created_at','DESC')->get();
                        $loss_damage_details=convertStdClassObjToArray($loss_damage_details);
                        $repair_details=convertStdClassObjToArray($repair_details);
                        
                        foreach($results as $index=>$asset)
                        {
                          
                            $asset_loss_damage_details=$this->_search_array_by_value($loss_damage_details,'asset_id',$asset->id);
                            $asset_repair_details=$this->_search_array_by_value($repair_details,'asset_id',$asset->id);

                            if(count($asset_loss_damage_details)>0)
                            {
                                $asset->individuals_responsible=$asset_loss_damage_details[0]['individuals_responsible'];
                                $asset->damage_date_of_asset=$asset_loss_damage_details[0]['damage_date_of_asset'];
                                
                            }else{
                                $asset->damage_date_of_asset="";
                                $asset->individuals_responsible="";
                            }
                            if(count( $asset_repair_details)>0)
                            {
                                $asset->repair_status=$asset_repair_details[0]['repair_status'];
                            }else{
                                $asset->repair_status="";
                            }
                            $result[$index]=$asset;
                        }
                        foreach($results as $result)
                        {
                            $user_ids=$this->returnArrayFromStringArray($result->individuals_responsible);
                            $users_res=[];
                            
                            foreach($user_ids as $user_id)
                            {
    
                                // ->selectRaw("t1.id,
                                // CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.middlename),decrypt(t1.last_name)) as name")->get();
    
                                $user = DB::table('users as t1')
                                ->where('id',$user_id)
                                ->selectRaw("t1.id,CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.middlename),decrypt(t1.last_name),decrypt(t1.email)) as name")->get();
                               
                                $user=$user->toArray();
                                $users_res[]=$user[0]->name;
                            }
                           $result->individuals_responsible=implode(",",$users_res);
    
                        }
                       
                        $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                        );
                        break;
                    case "stores_damaged" :
                    
                        $qry = DB::table('ar_asset_inventory as t1')
                        ->join('stores_categories as t2', 't1.category_id', '=', 't2.id')
                        ->join('stores_subcategories as t9', 't1.sub_category_id', '=', 't9.id')
                        ->leftjoin('stores_brands as t3', 't1.brand_id', '=', 't3.id')
                        ->leftjoin('stores_models as t4', 't1.model_id', '=', 't4.id')
                        ->join('stores_sites as t5', 't1.site_id', '=', 't5.id')
                        ->leftjoin('stores_locations as t6', 't1.location_id', '=', 't6.id')
                        ->leftjoin('stores_departments as t7', 't1.department_id', '=', 't7.id')
                        ->join('stores_statuses as t8', 't1.status_id', '=', 't8.id')
                        
                          ->selectRaw("t1.*, t1.id as record_id,t1.id as asset_id, t2.name as category_name, t3.name as brand_name, t4.name as model_name,t5.name as site_name,
                            t6.name as location_name,t7.name as department_name, t8.name as record_status,t9.name as sub_category_name");
                           // ->orwhere('t10.repair_scheduled',0);
                            //->orwhereNotIn('t10.repair_scheduled',[1,2]);
                        
                        $qry->whereIn('status_id', [5]);   
                        $results = $qry->get();
                       
                        $loss_damage_details=DB::table('stores_asset_loss_damage_details')->selectRaw('loss_damage_date as damage_date_of_asset,asset_id,individuals_responsible')->orderBy('created_at','DESC')->get();
                        $repair_details=DB::table('stores_asset_repairs')->selectRaw('repair_status,asset_id,scheduled_repair_date')->orderBy('created_at','DESC')->get();
                        $loss_damage_details=convertStdClassObjToArray($loss_damage_details);
                        $repair_details=convertStdClassObjToArray($repair_details);
                        
                        foreach($results as $index=>$asset)
                        {
                          
                            $asset_loss_damage_details=$this->_search_array_by_value($loss_damage_details,'asset_id',$asset->id);
                            $asset_repair_details=$this->_search_array_by_value($repair_details,'asset_id',$asset->id);

                            if(count($asset_loss_damage_details)>0)
                            {
                                $asset->individuals_responsible=$asset_loss_damage_details[0]['individuals_responsible'];
                                $asset->damage_date_of_asset=$asset_loss_damage_details[0]['damage_date_of_asset'];
                                
                            }else{
                                $asset->damage_date_of_asset="";
                                $asset->individuals_responsible="";
                            }
                            if(count( $asset_repair_details)>0)
                            {
                                $asset->repair_status=$asset_repair_details[0]['repair_status'];
                            }else{
                                $asset->repair_status="";
                            }
                            $result[$index]=$asset;
                        }
                        foreach($results as $result)
                        {
                            $user_ids=$this->returnArrayFromStringArray($result->individuals_responsible);
                            $users_res=[];
                            
                            foreach($user_ids as $user_id)
                            {
    
                               
                                $user = DB::table('users as t1')
                                ->where('id',$user_id)
                                ->selectRaw("t1.id,CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.middlename),decrypt(t1.last_name),decrypt(t1.email)) as name")->get();
                               
                                $user=$user->toArray();
                                $users_res[]=$user[0]->name;
                            }
                           $result->individuals_responsible=implode(",",$users_res);
    
                        }
                       
                        $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                        );
                        break;
                case "assets_linked_to_insurance_count":
                    $count=DB::table('ar_asset_insurance_linkage')->where('policy_id',$request->input('policy_id'))->count();
                    $res = array(
                        'success' => true,
                        //'message' => returnMessage($results),
                        'results' => ["asset_count"=>$count]
                    );
                    break;
                case "funding_amount_allocated_to_asset_for_fund":
                    $asset_id=$request->input('asset_id');
                    $funding_id=$request->input('funding_id');
                    $max_amount=DB::table('ar_asset_funding_linkage')->where('asset_id',$asset_id)->where('funding_id',$funding_id)->value('amount');
                    $sum_maintenance=Db::table('ar_asset_maintainance')->where('asset_id',$asset_id)->sum('maintainance_cost');
                    $sum_repairs=Db::table('ar_asset_repairs')->where('asset_id',$asset_id)->sum('repair_cost');
                    $total_accumulated_usage=$sum_maintenance+$sum_repairs;
                    $max_amount=$max_amount-$total_accumulated_usage;
                    $res = array(
                        'success' => true,
                        'results' => ["available_amount"=>$max_amount]
                    );
                    
                    break;
                 case "stores_funding_amount_allocated_to_asset_for_fund":
                    $asset_id=$request->input('asset_id');
                    $funding_id=$request->input('funding_id');
                    $max_amount=DB::table('ar_asset_funding_linkage')->where('asset_id',$asset_id)->where('funding_id',$funding_id)->value('amount');
                    $sum_maintenance=Db::table('ar_asset_maintainance')->where('asset_id',$asset_id)->sum('maintainance_cost');
                    $sum_repairs=Db::table('ar_asset_repairs')->where('asset_id',$asset_id)->sum('repair_cost');
                    $total_accumulated_usage=$sum_maintenance+$sum_repairs;
                    $max_amount=$max_amount-$total_accumulated_usage;
                    $res = array(
                        'success' => true,
                        'results' => ["available_amount"=>$max_amount]
                    );
                    
                    break;
                case "department_id_for_location":
                    $department_id=Db::table('ar_asset_locations')->where('id',$request->input('location_id'))->value('department_id');
                    $res = array(
                        'success' => true,
                        'department_id' => $department_id
                    );
                    break;
             case "stores_department_id_for_location":
                    $department_id=Db::table('stores_locations')->where('id',$request->input('location_id'))->value('department_id');
                    $res = array(
                        'success' => true,
                        'department_id' => $department_id
                    );
                    break;
              case "stores_department_id_for_location":
                    $department_id=Db::table('stores_locations')->where('id',$request->input('location_id'))->value('department_id');
                    $res = array(
                        'success' => true,
                        'department_id' => $department_id
                    );
                    break;
                case "written-off":
                    $qry = DB::table('ar_asset_inventory as t1')
                    ->join('ar_asset_categories as t2', 't1.category_id', '=', 't2.id')
                    ->join('ar_asset_subcategories as t9', 't1.sub_category_id', '=', 't9.id')
                    ->leftjoin('ar_asset_brands as t3', 't1.brand_id', '=', 't3.id')
                    ->leftjoin('ar_asset_models as t4', 't1.model_id', '=', 't4.id')
                    ->join('ar_asset_sites as t5', 't1.site_id', '=', 't5.id')
                    ->leftjoin('ar_asset_locations as t6', 't1.location_id', '=', 't6.id')
                    ->leftjoin('departments as t7', 't1.department_id', '=', 't7.id')
                    ->join('ar_asset_write_off_details as t10','t10.asset_id','t1.id')
                    // ->join('ar_asset_statuses as t8', 't1.status_id', '=', 't8.id')
                    ->selectRaw("t1.*, t1.id as record_id,t1.id as asset_id, t2.name as category_name,t9.name as sub_category_name,t3.name as brand_name, t4.name as model_name,t5.name as site_name,
                        t6.name as location_name,t7.name as department_name,t10.date_written_off,t10.write_off_reason");
                    $qry->where('status_id',"=",6);
            
                    $results = $qry->get();
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($results),
                        'results' => $results
                    );
                    break;
                case "stores_written-off":
                    $qry = DB::table('ar_asset_inventory as t1')
                    ->join('stores_categories as t2', 't1.category_id', '=', 't2.id')
                    ->join('stores_subcategories as t9', 't1.sub_category_id', '=', 't9.id')
                    ->leftjoin('stores_brands as t3', 't1.brand_id', '=', 't3.id')
                    ->leftjoin('stores_models as t4', 't1.model_id', '=', 't4.id')
                    ->join('stores_sites as t5', 't1.site_id', '=', 't5.id')
                    ->leftjoin('stores_locations as t6', 't1.location_id', '=', 't6.id')
                    ->leftjoin('stores_departments as t7', 't1.department_id', '=', 't7.id')
                    ->join('stores_asset_write_off_details as t10','t10.asset_id','t1.id')
                    ->selectRaw("t1.*, t1.id as record_id,t1.id as asset_id, t2.name as category_name,t9.name as sub_category_name,t3.name as brand_name, t4.name as model_name,t5.name as site_name,
                        t6.name as location_name,t7.name as department_name,t10.date_written_off,t10.write_off_reason");
                    $qry->where('status_id',"=",6);
                    $results = $qry->get();
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($results),
                        'results' => $results
                    );
                    break;
                case "depreciated":
                  
                     $qry = DB::table('ar_asset_inventory as t1')
                    ->join('ar_asset_categories as t2', 't1.category_id', '=', 't2.id')
                    ->join('ar_asset_subcategories as t3','t1.sub_category_id','t3.id')
                    ->leftjoin('ar_asset_brands as t4', 't1.brand_id', '=', 't4.id')
                    ->leftjoin('ar_asset_models as t5', 't1.model_id', '=', 't5.id')
                    ->join('ar_asset_sites as t6', 't1.site_id', '=', 't6.id')
                    ->leftjoin('ar_asset_locations as t7', 't1.location_id', '=', 't7.id')
                    ->leftjoin('departments as t8', 't1.department_id', '=', 't8.id')
                    ->join('ar_asset_statuses as t9', 't1.status_id', '=', 't9.id')
                    ->join('ar_asset_depreciation as t10','t10.asset_id','=','t1.id')
                    ->selectRaw("t1.*, t1.id as record_id,t1.id as asset_id, t2.name as category_name, t4.name as brand_name, t4.name as model_name,t5.name as site_name,
                        t7.name as location_name,t8.name as department_name, t9.name as record_status,CONCAT_WS('-',t1.description,t1.grz_no) as desc_grz_no,
                        t10.asset_end_depreciation_date")
                   // $qry->where('status_id',"=", 7);
                    //should be changed to less than
                   ->whereDate('t10.asset_end_depreciation_date', '<', now()->format('Y-m-d'));
                    
    
                    //$qry->orWhere('status_id',2);
                
                $results = $qry->get();
                $res = array(
                    'success' => true,
                    'message' => returnMessage($results),
                    'results' => $results
                );
                    break;
             case "stores_depreciated":
                  
                     $qry = DB::table('ar_asset_inventory as t1')
                    ->join('stores_categories as t2', 't1.category_id', '=', 't2.id')
                    ->join('stores_subcategories as t3','t1.sub_category_id','t3.id')
                    ->leftjoin('stores_brands as t4', 't1.brand_id', '=', 't4.id')
                    ->leftjoin('stores_models as t5', 't1.model_id', '=', 't5.id')
                    ->join('stores_sites as t6', 't1.site_id', '=', 't6.id')
                    ->leftjoin('stores_locations as t7', 't1.location_id', '=', 't7.id')
                    ->leftjoin('stores_departments as t8', 't1.department_id', '=', 't8.id')
                    ->join('stores_statuses as t9', 't1.status_id', '=', 't9.id')
                    ->join('stores_asset_depreciation as t10','t10.asset_id','=','t1.id')
                    ->selectRaw("t1.*, t1.id as record_id,t1.id as asset_id, t2.name as category_name, t4.name as brand_name, t4.name as model_name,t5.name as site_name,
                        t7.name as location_name,t8.name as department_name, t9.name as record_status,CONCAT_WS('-',t1.description,t1.grz_no) as desc_grz_no,
                        t10.asset_end_depreciation_date")
                   // $qry->where('status_id',"=", 7);
                    //should be changed to less than
                   ->whereDate('t10.asset_end_depreciation_date', '<', now()->format('Y-m-d'));
                    
    
                    //$qry->orWhere('status_id',2);
                
                $results = $qry->get();
                $res = array(
                    'success' => true,
                    'message' => returnMessage($results),
                    'results' => $results
                );
                    break;
                case "unsuccessfull_in_repair":

                    $qry = DB::table('ar_asset_repairs as t1')
                    ->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
                    ->join('ar_asset_subcategories as t8','t8.id','t2.sub_category_id')
                    ->join('ar_asset_brands as t9','t9.id','t2.brand_id')
                    ->join('ar_asset_models as t10','t10.id','t2.model_id')
                    ->join('ar_asset_sites as t4', 't2.site_id', '=', 't4.id')
                    ->leftjoin('ar_asset_locations as t5', 't2.location_id', '=', 't5.id')
                    ->leftjoin('departments as t6', 't2.department_id', '=', 't6.id')
                    ->join('ar_asset_statuses as t7', 't2.status_id', '=', 't7.id')
                   // ->join('ar_asset_maintainance_repair_locations as t8','t8.id','=','t1.location_id')
                    ->selectRaw('t1.id as repair_record_id,t1.*, t2.description,t2.serial_no,t2.grz_no,t2.purchase_date,t2.cost,t4.id as site_id,t4.name as site_name,
                    t2.location_id,t2.department_id,t5.name as location_name,t6.name as department_name,t7.id as asset_status_id,t7.name as record_status,t1.scheduled_repair_date,
                    t1.repair_status,t1.repair_successful as repair_success,t8.name as sub_category_name,t9.name as brand_name,t10.name as model_name');
                
                    $qry->where('repair_status', 2)
                    ->where('repair_successful',2); //unsuccessful

                        
                    
                    $results = $qry->get();
                  
                    $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                    );

                    
                    break;
             case "stores_unsuccessfull_in_repair":

                    $qry = DB::table('stores_asset_repairs as t1')
                    ->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
                    ->join('stores_subcategories as t8','t8.id','t2.sub_category_id')
                    ->join('stores_brands as t9','t9.id','t2.brand_id')
                    ->join('stores_models as t10','t10.id','t2.model_id')
                    ->join('stores_sites as t4', 't2.site_id', '=', 't4.id')
                    ->leftjoin('stores_locations as t5', 't2.location_id', '=', 't5.id')
                    ->leftjoin('stores_departments as t6', 't2.department_id', '=', 't6.id')
                    ->join('stores_statuses as t7', 't2.status_id', '=', 't7.id')
                    ->selectRaw('t1.id as repair_record_id,t1.*, t2.description,t2.serial_no,t2.grz_no,t2.purchase_date,t2.cost,t4.id as site_id,t4.name as site_name,
                    t2.location_id,t2.department_id,t5.name as location_name,t6.name as department_name,t7.id as asset_status_id,t7.name as record_status,t1.scheduled_repair_date,
                    t1.repair_status,t1.repair_successful as repair_success,t8.name as sub_category_name,t9.name as brand_name,t10.name as model_name');
                
                    $qry->where('repair_status', 2)
                    ->where('repair_successful',2); //unsuccessful
                    $results = $qry->get();
                    $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                    );

                case "maintainance_schedule":
                    $qry = DB::table('ar_asset_maintainance as t1')
                    ->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
                   
                    ->join('ar_asset_sites as t4', 't2.site_id', '=', 't4.id')
                    ->leftjoin('ar_asset_locations as t5', 't2.location_id', '=', 't5.id')
                    ->leftjoin('departments as t6', 't2.department_id', '=', 't6.id')
                    ->join('ar_asset_statuses as t7', 't2.status_id', '=', 't7.id')
                    ->selectRaw('t1.id as maintainance_record_id,t1.*,t2.id as parent_asset_id,t2.description,t2.serial_no,t2.grz_no,t4.id as site_id,t4.name as site_name,
                    t2.location_id,t2.department_id,t5.name as location_name,t6.name as department_name, t7.name as record_status,t2.maintainance_frequency,t2.maintainance_schedule_date');
                    //$qry->where('has_maintainance_scheduled','=','1');
                    //$qry->whereIn('maintainance_status', [0,3])
                    $qry->whereIn('maintainance_status', [0,3]);//3 is on hold
                   // ->orWhere('next_maintainance_date','=',now()->format('Y-m-d'));
                   
                    $results = $qry->get();
                  
                    $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                    );

                    break;
                 case "stores_maintainance_schedule":
                    $qry = DB::table('stores_asset_maintainance as t1')
                    ->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
                   
                    ->join('stores_sites as t4', 't2.site_id', '=', 't4.id')
                    ->leftjoin('stores_locations as t5', 't2.location_id', '=', 't5.id')
                    ->leftjoin('stores_departments as t6', 't2.department_id', '=', 't6.id')
                    ->join('stores_statuses as t7', 't2.status_id', '=', 't7.id')
                    ->selectRaw('t1.id as maintainance_record_id,t1.*,t2.id as parent_asset_id,t2.description,t2.serial_no,t2.grz_no,t4.id as site_id,t4.name as site_name,
                    t2.location_id,t2.department_id,t5.name as location_name,t6.name as department_name, t7.name as record_status,t2.maintainance_frequency,t2.maintainance_schedule_date');
                    $qry->whereIn('maintainance_status', [0,3]);//3 is on hold
                    $results = $qry->get();
                    $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                    );

                    break;
                case "maintainance_due":
                    $user_id=$this->user_id;

                    // $from_inventory_to_schedule = DB::table('ar_asset_inventory as t1')
                    // ->join('ar_asset_categories as t2', 't1.category_id', '=', 't2.id')
                    // ->leftjoin('ar_asset_brands as t3', 't1.brand_id', '=', 't3.id')
                    // ->leftjoin('ar_asset_models as t4', 't1.model_id', '=', 't4.id')
                    // ->join('ar_asset_sites as t5', 't1.site_id', '=', 't5.id')
                    // ->leftjoin('ar_asset_locations as t6', 't1.location_id', '=', 't6.id')
                    // ->leftjoin('departments as t7', 't1.department_id', '=', 't7.id')
                    // ->join('ar_asset_statuses as t8', 't1.status_id', '=', 't8.id')
                    // ->selectRaw("t1.id as asset_id, t1.description,t1.serial_no,t1.grz_no,t5.id as site_id,t5.name as site_name,
                    // t1.location_id,t1.department_id,t6.name as location_name,t7.name as department_name,
                    // t8.name as record_status,t1.maintainance_frequency,t1.maintainance_schedule_date")
                    // ->where('t1.maintainance_schedule_date', '!=', null)->get();
                    // //->whereDate('t1.maintainance_schedule_date', '=', now()->format('Y-m-d'))->get();
                    // $from_inventory_to_schedule_array=convertStdClassObjToArray($from_inventory_to_schedule);
                  
                    // foreach($from_inventory_to_schedule_array as $asset)
                    // {
                    //     //Carbon::now()->addDays(10)->format('Y-m-d')
                    //     $result=DB::table('ar_asset_maintainance as t1')
                    //         ->where('asset_id',$asset['asset_id'])
                    //         //->where('maintainance_status','>=',1)//is scheduled
                    //         ->where('maintainance_due_date','=',$asset['maintainance_schedule_date'])->count();
                    //         if($result==0)
                    //         {
                    //             $table_data=array(
                    //                 "asset_id"=>$asset['asset_id'],
                    //                 "maintainance_status"=>1,
                    //                 "maintainance_due_date"=>$asset['maintainance_schedule_date'],
                    //                 //"created_by"=>$this->user_id
                    //                 //"created_at"=>Carbon::now(),
                    //             );
                    //             $res = insertRecord('ar_asset_maintainance', $table_data, $user_id);
                    //         }
                       
                    // }
                    $due_in_manual_schedule= DB::table('ar_asset_maintainance as t1')
                    ->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
                    ->join('ar_asset_sites as t4', 't2.site_id', '=', 't4.id')
                    ->leftjoin('ar_asset_locations as t5', 't2.location_id', '=', 't5.id')
                    ->leftjoin('departments as t6', 't2.department_id', '=', 't6.id')
                    ->join('ar_asset_statuses as t7', 't2.status_id', '=', 't7.id')  
                    ->selectRaw('t2.id as asset_id, t2.description,t2.serial_no,t2.grz_no,t4.id as site_id,t4.name as site_name,
                    t2.location_id,t2.department_id,t5.name as location_name,t6.name as department_name, t7.name as record_status,
                  t2.id as parent_asset_id,t1.maintainance_due_date')
                  ->whereDate('t1.maintainance_due_date', '=', now()->format('Y-m-d'))
                  ->where('t1.maintainance_status',1)->get();

                    //$assets_due=array_merge($overdue_in_manual_schedule->toArray(),$due_in_automatic_schedule->toArray());
                    $res = array(
                            'success' => true,
                            'message' => returnMessage($due_in_manual_schedule),
                            'results' =>  $due_in_manual_schedule
                    );
                    break;
                case "maintainance_overdue":
                    $user_id=$this->user_id;
                   
                    $overdue_in_manual_schedule= DB::table('ar_asset_maintainance as t1')
                    ->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
                    ->join('ar_asset_sites as t4', 't2.site_id', '=', 't4.id')
                    ->leftjoin('ar_asset_locations as t5', 't2.location_id', '=', 't5.id')
                    ->leftjoin('departments as t6', 't2.department_id', '=', 't6.id')
                    ->join('ar_asset_statuses as t7', 't2.status_id', '=', 't7.id')  
                    ->selectRaw('t2.id as asset_id, t2.description,t2.serial_no,t2.grz_no,t4.id as site_id,t4.name as site_name,
                    t2.location_id,t2.department_id,t5.name as location_name,t6.name as department_name, t7.name as record_status,
                  t2.id as parent_asset_id,t1.maintainance_due_date,maintainance_schedule_date')
                  ->whereDate('t1.maintainance_due_date', '<', now()->format('Y-m-d'))
                  ->where('t1.maintainance_status',0)->get();

                    //$assets_due=array_merge($overdue_in_manual_schedule->toArray(),$due_in_automatic_schedule->toArray());
                    $res = array(
                            'success' => true,
                            'message' => returnMessage($overdue_in_manual_schedule),
                            'results' =>    $overdue_in_manual_schedule
                    );
                    break;
                case "repair_due":
                    $due_in_repairs= DB::table('ar_asset_repairs as t1')
                    ->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
                    ->join('ar_asset_sites as t4', 't2.site_id', '=', 't4.id')
                    ->leftjoin('ar_asset_locations as t5', 't2.location_id', '=', 't5.id')
                    ->leftjoin('departments as t6', 't2.department_id', '=', 't6.id')
                    ->join('ar_asset_statuses as t7', 't2.status_id', '=', 't7.id')  
                    ->selectRaw('t2.id as asset_id, t2.description,t2.serial_no,t2.grz_no,t4.id as site_id,t4.name as site_name,
                    t2.location_id,t2.department_id,t5.name as location_name,t6.name as department_name, t7.name as record_status,
                  t2.id as parent_asset_id')
                  ->whereDate('t1.scheduled_repair_date', '=', now()->format('Y-m-d'))
                  ->where('t1.repair_status',0)->get();//is scheduled instead of 1

                    //$assets_due=array_merge($overdue_in_manual_schedule->toArray(),$due_in_automatic_schedule->toArray());
                    $res = array(
                            'success' => true,
                            'message' => returnMessage($due_in_repairs),
                            'results' =>    $due_in_repairs
                    );
                    break;
                    case "repair_overdue":
                        $overdue_in_repairs= DB::table('ar_asset_repairs as t1')
                        ->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
                        ->join('ar_asset_sites as t4', 't2.site_id', '=', 't4.id')
                        ->leftjoin('ar_asset_locations as t5', 't2.location_id', '=', 't5.id')
                        ->leftjoin('departments as t6', 't2.department_id', '=', 't6.id')
                        ->join('ar_asset_statuses as t7', 't2.status_id', '=', 't7.id')  
                        ->selectRaw('t2.id as asset_id, t2.description,t2.serial_no,t2.grz_no,t4.id as site_id,t4.name as site_name,
                        t2.location_id,t2.department_id,t5.name as location_name,t6.name as department_name, t7.name as record_status,
                      t2.id as parent_asset_id')
                      ->whereDate('t1.scheduled_repair_date', '<', now()->format('Y-m-d'))
                      ->where('t1.repair_status',0)->get();//0 is scheduled instead of 1
    
                        $res = array(
                                'success' => true,
                                'message' => returnMessage($overdue_in_repairs),
                                'results' =>  $overdue_in_repairs
                        );
                        break;
                case "categories_of_this_brand":
                    $brand_id =  $request->input('brand_id');
                    $qry=DB::table('ar_asset_brands_categories')->where('brand_id',$brand_id);
                    $results=$qry->selectRaw('category_id')->get();
                    $res = array(
                        'success' => true,
                       // 'message' => returnMessage($results),
                        'results' =>  $results
                );

                    break;
                case "asset_inventory_columns":
                    
                   $modified_columns= DB::getSchemaBuilder()->getColumnListing('ar_asset_inventory');
                  

                   $modified_columns[2]="category";
                 
                   $modified_columns[3]="brand";
                   $modified_columns[4]="model";
                   $modified_columns[5]="Purchase Date";
                   $modified_columns[6]="Purchase From";
                   $modified_columns[8]="Serial No";
                   $modified_columns[9]="Grz No";
                   $modified_columns[10]="site";
                   $modified_columns[11]="location";
                   
                   $modified_columns[12]="department";
                   $modified_columns[15]="status";
                   $modified_columns[20]="Frequency of Maintainance";
                   
                //to unset 0,13,14,16,17,18,19
              
                 $keys_to_unset=[0,7,13,14,16,17,18,19,21,22,23,24,25,26,20];
                 foreach($keys_to_unset as $key)
                 {
                    unset( $modified_columns[$key]);
                    
                 }
                
                 $grid_columns=[];
                 foreach($modified_columns as $column)
                 {
                     $grid_columns[]=array(
                         "field_name"=>$column
                         
                     );

                 }
                // dd($grid_columns);
                 $res = array(
                    'success' => true,
                    'message' => returnMessage($grid_columns),
                    'results' =>  $grid_columns
            );
                    break;
                case "assets_disposed":
                    $qry= DB::table('ar_asset_disposal_details as t9')
                         ->leftjoin('ar_asset_disposal_methods as t10','t10.id','=','t9.disposal_method')
                         ->join('ar_asset_inventory as t1','t1.id','=','t9.asset_id')
                         ->join('ar_asset_categories as t2', 't1.category_id', '=', 't2.id')
                         ->join('ar_asset_subcategories as t8', 't1.sub_category_id', '=', 't8.id')
                         ->leftjoin('ar_asset_brands as t3', 't1.brand_id', '=', 't3.id')
                         ->leftjoin('ar_asset_models as t4', 't1.model_id', '=', 't4.id')
                         ->join('ar_asset_sites as t5', 't1.site_id', '=', 't5.id')
                         ->leftjoin('ar_asset_locations as t6', 't1.location_id', '=', 't6.id')
                         ->leftjoin('departments as t7', 't1.department_id', '=', 't7.id')
                         ->selectRaw("t1.*, t1.id as record_id,t1.id as asset_id, t2.name as category_name, t3.name as brand_name, 
                         t4.name as model_name,t5.name as site_name,t8.name as sub_category_name,
                             t6.name as location_name,t7.name as department_name,t9.date_of_disposal as disposal_date,t10.name as disposal_method,
                             disposal_reason
                             "); 
                    $results = $qry->get();
                    
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($results),
                        'results' => $results
                    );
                    break;
                case "stores_assets_disposed":
                    $qry= DB::table('stores_asset_disposal_details as t9')
                         ->leftjoin('stores_asset_disposal_methods as t10','t10.id','=','t9.disposal_method')
                         ->join('ar_asset_inventory as t1','t1.id','=','t9.asset_id')
                         ->join('stores_categories as t2', 't1.category_id', '=', 't2.id')
                         ->join('stores_subcategories as t8', 't1.sub_category_id', '=', 't8.id')
                         ->leftjoin('stores_brands as t3', 't1.brand_id', '=', 't3.id')
                         ->leftjoin('stores_models as t4', 't1.model_id', '=', 't4.id')
                         ->join('stores_sites as t5', 't1.site_id', '=', 't5.id')
                         ->leftjoin('stores_locations as t6', 't1.location_id', '=', 't6.id')
                         ->leftjoin('stores_departments as t7', 't1.department_id', '=', 't7.id')
                         ->selectRaw("t1.*, t1.id as record_id,t1.id as asset_id, t2.name as category_name, t3.name as brand_name, 
                         t4.name as model_name,t5.name as site_name,t8.name as sub_category_name,
                             t6.name as location_name,t7.name as department_name,t9.date_of_disposal as disposal_date,t10.name as disposal_method,
                             disposal_reason
                             "); 
                    $results = $qry->get();
                    
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($results),
                        'results' => $results
                    );
                    break;
                case "asset_category_additional_fields":
                   $category_id = $request->query('asset_category_id');
                   $asset_id=$request->query('asset_id');
                   $qry= DB::table('ar_asset_category_attributes as t1')
                    ->where('category_id',$category_id)
                    ->selectRaw('t1.id,t1.attribute_name,t1.data_type_id');
                    $fields=$qry->get();
                  
                    $qry=DB::table('ar_asset_category_attributes_values as t1')
                        ->join('ar_asset_category_attributes as t2','t2.id','=','t1.attribute_id')
                        ->where('asset_id',$asset_id)
                        ->selectRaw('t1.attribute_id,t1.value,t2.attribute_name');
                    $field_values=convertStdClassObjToArray($qry->get());
                    $results=array(
                        "fields"=>$fields,
                        "values"=>$field_values
                    );
                   
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($results),
                        'results' =>  $results
                 );
                    break;
                case "stores_asset_category_additional_fields":
                   $category_id = $request->query('asset_category_id');
                   $asset_id=$request->query('asset_id');
                   $qry= DB::table('stores_category_attributes as t1')
                    ->where('category_id',$category_id)
                    ->selectRaw('t1.id,t1.attribute_name,t1.data_type_id');
                    $fields=$qry->get();
                  
                    $qry=DB::table('stores_category_attributes_values as t1')
                        ->join('stores_category_attributes as t2','t2.id','=','t1.attribute_id')
                        ->where('asset_id',$asset_id)
                        ->selectRaw('t1.attribute_id,t1.value,t2.attribute_name');
                    $field_values=convertStdClassObjToArray($qry->get());
                    $results=array(
                        "fields"=>$fields,
                        "values"=>$field_values
                    );
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($results),
                        'results' =>  $results
                 );
                    break;
                case "assets_insurances_graph":
                    $valid_insurances = DB::table('ar_insurance_policies as t1')
                      ->whereDate("t1.end_date",">", now()->format('Y-m-d'))->count();
                    $invalid_insurances=  DB::table('ar_insurance_policies as t1')
                         ->whereDate("t1.end_date","<=", now()->format('Y-m-d'))->count();
                        $data=[
                            ["status"=>"Valid Insurances","count"=> $valid_insurances],
                            ["status"=>"Invalid Insurances","count"=> $invalid_insurances]
                        ];
                        $res = array(
                            'success' => true,
                            'message' => returnMessage($data),
                            'results' =>  $data
                            );
                    break;
                case "stores_assets_insurances_graph":
                    $valid_insurances = DB::table('stores_insurance_policies as t1')
                      ->whereDate("t1.end_date",">", now()->format('Y-m-d'))->count();
                    $invalid_insurances=  DB::table('stores_insurance_policies as t1')
                         ->whereDate("t1.end_date","<=", now()->format('Y-m-d'))->count();
                        $data=[
                            ["status"=>"Valid Insurances","count"=> $valid_insurances],
                            ["status"=>"Invalid Insurances","count"=> $invalid_insurances]
                        ];
                        $res = array(
                            'success' => true,
                            'message' => returnMessage($data),
                            'results' =>  $data
                            );
                    break;
                case "assets_warranties_graph":
                    $active_warranties = DB::table('ar_asset_warranties as t1')
                    ->whereDate('t1.expiration_date', '>', now()->format('Y-m-d'))->count();
                    $expired_warranties =  DB::table('ar_asset_warranties as t1')
                    ->whereDate('t1.expiration_date', '<=', now()->format('Y-m-d'))->count();
                    //$assets_without_warranties//todo
                    $data=[
                        ["type"=>"Active warranties","count"=> $active_warranties],
                        ["type"=>"Expired warranties","count"=> $expired_warranties]
                    ];
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($data),
                        'results' =>  $data
                        );
                 
                    break;
                case "stores_assets_warranties_graph":
                    $active_warranties = DB::table('stores_warranties as t1')
                    ->whereDate('t1.expiration_date', '>', now()->format('Y-m-d'))->count();
                    $expired_warranties =  DB::table('stores_warranties as t1')
                    ->whereDate('t1.expiration_date', '<=', now()->format('Y-m-d'))->count();
                    //$assets_without_warranties//todo
                    $data=[
                        ["type"=>"Active warranties","count"=> $active_warranties],
                        ["type"=>"Expired warranties","count"=> $expired_warranties]
                    ];
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($data),
                        'results' =>  $data
                        );
                 
                    break;
                case "assets_funds_graph":
                    $active_funds = DB::table('ar_funding_details as t1')
                    ->where("t1.end_date",">", now()->format('Y-m-d'))->count();
                    $expired_funds = DB::table('ar_funding_details as t1')
                    ->orwhere("t1.end_date","<=",now()->format('Y-m-d'))->count();
                    //$asset_fund_linkage;todo
                   //nla 
                    $data=[
                        ["type"=>"Active fundings","count"=>$active_funds],
                        ["type"=>"Expired fundings","count"=>$expired_funds]
                    ];
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($data),
                        'results' =>  $data
                        );
                    break;
                case "stores_assets_funds_graph":
                    $active_funds = DB::table('stores_funding_details as t1')
                    ->where("t1.end_date",">", now()->format('Y-m-d'))->count();
                    $expired_funds = DB::table('stores_funding_details as t1')
                    ->orwhere("t1.end_date","<=",now()->format('Y-m-d'))->count();
                    //$asset_fund_linkage;todo
                   //nla 
                    $data=[
                        ["type"=>"Active fundings","count"=>$active_funds],
                        ["type"=>"Expired fundings","count"=>$expired_funds]
                    ];
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($data),
                        'results' =>  $data
                        );
                    break;
                case "brands_for_categories":
                    $qry=DB::table('ar_asset_brands_categories as t1')
                     ->join('ar_asset_brands as t2','t1.brand_id','=','t2.id')
                    ->where('t1.category_id',$request->query('category_id'))
                    ->selectRaw('t1.brand_id as id,t2.name');
                    $results=$qry->get();
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($results),
                        'results' =>  $results
                        );
                    break;
                case "stores_brands_for_categories":
                    $qry=DB::table('stores_brands_categories as t1')
                     ->join('stores_brands as t2','t1.brand_id','=','t2.id')
                    ->where('t1.category_id',$request->query('category_id'))
                    ->selectRaw('t1.brand_id as id,t2.name');
                    $results=$qry->get();
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($results),
                        'results' =>  $results
                        );
                    break;
                case "asset_status_graph":
                    $available_in_inventory=DB::table('ar_asset_inventory')->where('status_id',1)->where('module_id',350)->count();
                    $reserved = DB::table('ar_asset_inventory')->where('status_id',3)->where('module_id',350)->count();
                    $checked_out=DB::table('ar_asset_inventory')->where('status_id',2)->where('module_id',350)->count();
                    $assets_past_due = DB::table('ar_asset_checkout_details as t1')
                        ->where('checkout_status','=','1')
                        ->whereDate('t1.due_date', '<', now()->format('Y-m-d'))->get()->where('module_id',350)->count();
                    $lost_assets=DB::table('ar_asset_inventory')->where('status_id',4)->where('module_id',350)->count();
                    $under_repair=DB::table('ar_asset_inventory')->where('status_id',8)->where('module_id',350)->count();
                    $under_repair_unsuccessful=DB::table('ar_asset_inventory')->where('status_id',10)->where('module_id',350)->count();
                    $under_maintainance=DB::table('ar_asset_inventory')->where('status_id',11)->where('module_id',350)->count();
                    $written_off=DB::table('ar_asset_inventory')->where('status_id',6)->where('module_id',350)->count();
                    $damaged=DB::table('ar_asset_inventory')->where('status_id',5)->where('module_id',350)->count();
                    $disposed=DB::table('ar_asset_inventory')->where('status_id',9)->where('module_id',350)->count();
                    $depreciated = DB::table('ar_asset_inventory')->where('status_id',7)->where('module_id',350)->count();
                    $archived = DB::table('ar_asset_inventory')->where('status_id',12)->where('module_id',350)->count();
                    $data=[
                        ["status"=>"Available","count"=>$available_in_inventory],
                        ["status"=>"Reserved","count"=>$reserved],
                        ["status"=>"Checked-Out","count"=>$checked_out],
                        ['status'=>"Past Check-Out","count"=>$assets_past_due ],
                        ["status"=>"Lost","count"=>$lost_assets],
                        ["status"=>"Damaged","count"=>$damaged],
                        ["status"=>"In Repair","count"=>$under_repair],
                        ["status"=>"Failed Repairs","count"=>$under_repair_unsuccessful],
                        ["status"=>"In Maintenance","count"=>$under_maintainance],
                        ["status"=>"Disposed","count"=>$disposed],
                        ["status"=>"Depreciated","count"=>$depreciated],
                        ["status"=>"Written Off","count"=>$written_off],
                        ["status"=>"Archived","count"=>$archived]
                    ];
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($data),
                        'results' =>  $data
                        );

                    break;
                case "stores_asset_status_graph":
                    $available_in_inventory=DB::table('ar_asset_inventory')->where('status_id',1)->where('module_id',637)->count();
                    $reserved = DB::table('ar_asset_inventory')->where('status_id',3)->where('module_id',637)->count();
                    $checked_out=DB::table('ar_asset_inventory')->where('status_id',2)->where('module_id',637)->count();
                    $assets_past_due = DB::table('stores_asset_checkout_details as t1')
                        ->where('checkout_status','=','1')
                        ->whereDate('t1.due_date', '<', now()->format('Y-m-d'))->get()->count();
                    $lost_assets=DB::table('ar_asset_inventory')->where('status_id',4)->where('module_id',637)->count();
                    $under_repair=DB::table('ar_asset_inventory')->where('status_id',8)->where('module_id',637)->count();
                    $under_repair_unsuccessful=DB::table('ar_asset_inventory')->where('status_id',10)->where('module_id',637)->count();
                    $under_maintainance=DB::table('ar_asset_inventory')->where('status_id',11)->where('module_id',637)->count();
                    $written_off=DB::table('ar_asset_inventory')->where('status_id',6)->where('module_id',637)->count();
                    $damaged=DB::table('ar_asset_inventory')->where('status_id',5)->where('module_id',637)->count();
                    $disposed=DB::table('ar_asset_inventory')->where('status_id',9)->where('module_id',637)->count();
                    $depreciated = DB::table('ar_asset_inventory')->where('status_id',7)->where('module_id',637)->count();
                    $archived = DB::table('ar_asset_inventory')->where('status_id',12)->where('module_id',637)->count();
                    $data=[
                        ["status"=>"Available","count"=>$available_in_inventory],
                        ["status"=>"Reserved","count"=>$reserved],
                        ["status"=>"Checked-Out","count"=>$checked_out],
                        ['status'=>"Past Check-Out","count"=>$assets_past_due ],
                        ["status"=>"Lost","count"=>$lost_assets],
                        ["status"=>"Damaged","count"=>$damaged],
                        ["status"=>"In Repair","count"=>$under_repair],
                        ["status"=>"Failed Repairs","count"=>$under_repair_unsuccessful],
                        ["status"=>"In Maintenance","count"=>$under_maintainance],
                        ["status"=>"Disposed","count"=>$disposed],
                        ["status"=>"Depreciated","count"=>$depreciated],
                        ["status"=>"Written Off","count"=>$written_off],
                        ["status"=>"Archived","count"=>$archived]
                    ];
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($data),
                        'results' =>  $data
                        );

                    break;
                case "asset_checkout_checkoutin_graph":
                   $checkouts= DB::table('ar_asset_checkout_details')->where('checkout_status',1)->count();
                   $checkins= DB::table('ar_asset_checkout_details')->where('checkout_status',2)->count();
                   $requisitions = DB::table('ar_asset_requisitions')->count();
                   $individual_assignments = DB::table('ar_asset_requisitions')->where('requested_for',1)->where('requisition_status',2)->count();
                   $site_assignments = DB::table('ar_asset_requisitions')->where('requested_for',2)->where('requisition_status',2)->count();
                   $reservations =  DB::table('ar_asset_reservations')->where('checkin_status',0)->count();//active reservations
                   $reservations_out =  DB::table('ar_asset_reservations')->where('checkin_status',1)->count();//checkedout;
                   $transfers = DB::table('ar_asset_transfers')->count();



                   $data=[
                       ["action"=>"Active Check-Outs","count"=>$checkouts],
                       ["action"=>"Individual Check-Outs","count"=>$individual_assignments],
                       ["action"=>"Site Check-Outs","count"=>$site_assignments],
                       ["action"=>"Check-Ins","count"=>$checkins],
                       ["action"=>"Active Reservations","count"=>$reservations],
                       ["action"=>"Checked-Out Reservations","count"=>$reservations_out],
                       ["action"=>"Asset Transfers","count"=>$transfers],
                      
                   ];
                   $res = array(
                    'success' => true,
                    'message' => returnMessage($data),
                    'results' =>  $data
                    );

                    break;
                case "stores_asset_checkout_checkoutin_graph":
                   $checkouts= DB::table('stores_asset_checkout_details')->where('checkout_status',1)->count();
                   $checkins= DB::table('stores_asset_checkout_details')->where('checkout_status',2)->count();
                   $requisitions = DB::table('stores_asset_requisitions')->count();
                   $individual_assignments = DB::table('stores_asset_requisitions')->where('requested_for',1)->where('requisition_status',2)->count();
                   $site_assignments = DB::table('stores_asset_requisitions')->where('requested_for',2)->where('requisition_status',2)->count();
                   $reservations =  DB::table('stores_asset_reservations')->where('checkin_status',0)->count();//active reservations
                   $reservations_out =  DB::table('stores_asset_reservations')->where('checkin_status',1)->count();//checkedout;
                   $transfers = DB::table('stores_asset_transfers')->count();



                   $data=[
                       ["action"=>"Active Check-Outs","count"=>$checkouts],
                       ["action"=>"Individual Check-Outs","count"=>$individual_assignments],
                       ["action"=>"Site Check-Outs","count"=>$site_assignments],
                       ["action"=>"Check-Ins","count"=>$checkins],
                       ["action"=>"Active Reservations","count"=>$reservations],
                       ["action"=>"Checked-Out Reservations","count"=>$reservations_out],
                       ["action"=>"Asset Transfers","count"=>$transfers],
                      
                   ];
                   $res = array(
                    'success' => true,
                    'message' => returnMessage($data),
                    'results' =>  $data
                    );

                    break;
                case "assets_dashboard_data": 
                    $user_id=$this->user_id;
                    $assets_in_inventory=DB::table('ar_asset_inventory')->whereNotIn('status_id',[12])->where('module_id',350)->count();
                    $new_requisitions = DB::table('ar_asset_requisitions')->where('requisition_status',1)->count();
                    //due maintainance
                    // $from_inventory_to_schedule = DB::table('ar_asset_inventory as t1')
                    // ->join('ar_asset_categories as t2', 't1.category_id', '=', 't2.id')
                    // ->join('ar_asset_brands as t3', 't1.brand_id', '=', 't3.id')
                    // ->join('ar_asset_models as t4', 't1.model_id', '=', 't4.id')
                    // ->join('ar_asset_sites as t5', 't1.site_id', '=', 't5.id')
                    // ->join('ar_asset_locations as t6', 't1.location_id', '=', 't6.id')
                    // ->join('departments as t7', 't1.department_id', '=', 't7.id')
                    // ->join('ar_asset_statuses as t8', 't1.status_id', '=', 't8.id')
                    // ->selectRaw("t1.id as asset_id, t1.description,t1.serial_no,t1.grz_no,t5.id as site_id,t5.name as site_name,
                    // t1.location_id,t1.department_id,t6.name as location_name,t7.name as department_name,
                    // t8.name as record_status,t1.maintainance_frequency,t1.maintainance_schedule_date")
                    // ->where('t1.maintainance_schedule_date', '!=', null)->get();
                    // //->whereDate('t1.maintainance_schedule_date', '=', now()->format('Y-m-d'))->get();
                    // $from_inventory_to_schedule_array=convertStdClassObjToArray($from_inventory_to_schedule);
                  
                    // foreach($from_inventory_to_schedule_array as $asset)
                    // {
                    //     //Carbon::now()->addDays(10)->format('Y-m-d')
                    //     $result=DB::table('ar_asset_maintainance as t1')
                    //         ->where('asset_id',$asset['asset_id'])
                    //         //->where('maintainance_status','>=',1)//is scheduled
                    //         ->where('maintainance_due_date','=',$asset['maintainance_schedule_date'])->count();
                    //         if($result==0)
                    //         {
                    //             $table_data=array(
                    //                 "asset_id"=>$asset['asset_id'],
                    //                 "maintainance_status"=>1,
                    //                 "maintainance_due_date"=>$asset['maintainance_schedule_date'],
                                  
                    //             );
                    //             $res = insertRecord('ar_asset_maintainance', $table_data, $user_id);
                    //         }
                       
                    // }
                    $due_maintainance= DB::table('ar_asset_maintainance as t1')
                  ->whereDate('t1.maintainance_due_date', '=', now()->format('Y-m-d'))
                  ->where('t1.maintainance_status',0)->count();
                    //maintainance_overdue
                   
                    // $from_inventory_to_schedule = DB::table('ar_asset_inventory as t1')
                    // ->join('ar_asset_categories as t2', 't1.category_id', '=', 't2.id')
                    // ->join('ar_asset_brands as t3', 't1.brand_id', '=', 't3.id')
                    // ->join('ar_asset_models as t4', 't1.model_id', '=', 't4.id')
                    // ->join('ar_asset_sites as t5', 't1.site_id', '=', 't5.id')
                    // ->join('ar_asset_locations as t6', 't1.location_id', '=', 't6.id')
                    // ->join('departments as t7', 't1.department_id', '=', 't7.id')
                    // ->join('ar_asset_statuses as t8', 't1.status_id', '=', 't8.id')
                    // ->selectRaw("t1.id as asset_id, t1.description,t1.serial_no,t1.grz_no,t5.id as site_id,t5.name as site_name,
                    // t1.location_id,t1.department_id,t6.name as location_name,t7.name as department_name,
                    // t8.name as record_status,t1.maintainance_frequency,maintainance_schedule_date")
                    // ->where('t1.maintainance_schedule_date', '!=', null)->get();
                    // //->whereDate('t1.maintainance_schedule_date', '!=', now()->format('Y-m-d'))->get();
                    // $from_inventory_to_schedule_array=convertStdClassObjToArray($from_inventory_to_schedule);
                    // foreach($from_inventory_to_schedule_array as $asset)
                    // {
                        
                    //     $result=DB::table('ar_asset_maintainance as t1')
                    //         ->where('asset_id',$asset['asset_id'])
                    //         //->where('maintainance_status','>=',1)//is scheduled
                    //         ->where('maintainance_due_date','=',$asset['maintainance_schedule_date'])->count();
                          
                    //         if($result==0)
                    //         {
                    //             $table_data=array(
                    //                 "asset_id"=>$asset['asset_id'],
                    //                 "maintainance_status"=>1,
                    //                 "maintainance_due_date"=>$asset['maintainance_schedule_date'],
                    //                 //"created_by"=>$this->user_id
                    //                 //"created_at"=>Carbon::now(),
                    //             );
                               
                    //             $res = insertRecord('ar_asset_maintainance', $table_data, $user_id);
                    //         }
                       
                    // }
                    $overdue_maintainance= DB::table('ar_asset_maintainance as t1')
                  ->whereDate('t1.maintainance_due_date', '<', now()->format('Y-m-d'))
                  ->where('t1.maintainance_status',0)->count();
                //due repairs
                  $due_in_repairs= DB::table('ar_asset_repairs as t1')
                  ->whereDate('t1.scheduled_repair_date', '=', now()->format('Y-m-d'))
                  ->where('t1.repair_status',0)->count();
                  //overdue repairs
                  $overdue_in_repairs= DB::table('ar_asset_repairs as t1')
                ->whereDate('t1.scheduled_repair_date', '<', now()->format('Y-m-d'))
                ->where('t1.repair_status',0)->count();
                    
                    
                    $results=[
                        "assets_in_inventory"=>$assets_in_inventory,
                        "new_requisitions"=>$new_requisitions,
                        "due_maintainance"=>$due_maintainance,
                        "overdue_maintainance"=> $overdue_maintainance,
                        "due_repairs"=>$due_in_repairs,
                        "overdue_repairs"=>$overdue_in_repairs
                    ];
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($results),
                        'results' =>  $results
                 );
                    break;
                case "stores_assets_dashboard_data": 
                    $user_id=$this->user_id;
                    $assets_in_inventory=DB::table('ar_asset_inventory')->whereNotIn('status_id',[12])->where('module_id',637)->count();
                    $new_requisitions = DB::table('stores_asset_requisitions')->where('requisition_status',1)->count();
                   
                    $due_maintainance= DB::table('stores_asset_maintainance as t1')
                  ->whereDate('t1.maintainance_due_date', '=', now()->format('Y-m-d'))
                  ->where('t1.maintainance_status',0)->count();
                   
                    $overdue_maintainance= DB::table('stores_asset_maintainance as t1')
                  ->whereDate('t1.maintainance_due_date', '<', now()->format('Y-m-d'))
                  ->where('t1.maintainance_status',0)->count();
                //due repairs
                  $due_in_repairs= DB::table('stores_asset_repairs as t1')
                  ->whereDate('t1.scheduled_repair_date', '=', now()->format('Y-m-d'))
                  ->where('t1.repair_status',0)->count();
                  //overdue repairs
                  $overdue_in_repairs= DB::table('stores_asset_repairs as t1')
                ->whereDate('t1.scheduled_repair_date', '<', now()->format('Y-m-d'))
                ->where('t1.repair_status',0)->count();
                    
                    
                    $results=[
                        "assets_in_inventory"=>$assets_in_inventory,
                        "new_requisitions"=>$new_requisitions,
                        "due_maintainance"=>$due_maintainance,
                        "overdue_maintainance"=> $overdue_maintainance,
                        "due_repairs"=>$due_in_repairs,
                        "overdue_repairs"=>$overdue_in_repairs
                    ];
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($results),
                        'results' =>  $results
                 );
                    break;
                case "funding_for_asset":
                    $qry=DB::table('ar_asset_funding_linkage as t1')
                    ->join('ar_funding_details as t2','t2.id','=','funding_id')
                    ->where('asset_id',$request->query('asset_id'))
                    ->selectRaw('t2.id,t2.name,t2.start_date,t2.end_date,
                  
                   @available_amount := (t2.funding_amount-(Select COALESCE(sum(amount),0) from ar_asset_funding_linkage where funding_id=t2.id) - 
                   (Select COALESCE(sum(maintainance_cost),0) from ar_asset_maintainance where funding_id=t2.id)-
                   (Select COALESCE(sum(repair_cost),0) from ar_asset_repairs where funding_id=t2.id)
                   ) as available_amount
                    ');
                    $results=$qry->get();
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($results),
                        'results' =>  $results
                    );
                    break;
                case "stores_funding_for_asset":
                    $qry=DB::table('stores_funding_linkage as t1')
                    ->join('stores_funding_details as t2','t2.id','=','funding_id')
                    ->where('asset_id',$request->query('asset_id'))
                    ->selectRaw('t2.id,t2.name,t2.start_date,t2.end_date,
                  
                   @available_amount := (t2.funding_amount-(Select COALESCE(sum(amount),0) from ar_asset_funding_linkage where funding_id=t2.id) - 
                   (Select COALESCE(sum(maintainance_cost),0) from ar_asset_maintainance where funding_id=t2.id)-
                   (Select COALESCE(sum(repair_cost),0) from ar_asset_repairs where funding_id=t2.id)
                   ) as available_amount
                    ');
                    $results=$qry->get();
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($results),
                        'results' =>  $results
                    );
                    break;
                case "under_maintainance":
                    $qry = DB::table('ar_asset_maintainance as t1')
                    ->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
                   
                    ->join('ar_asset_sites as t4', 't2.site_id', '=', 't4.id')
                    ->leftjoin('ar_asset_locations as t5', 't2.location_id', '=', 't5.id')
                    ->leftjoin('departments as t6', 't2.department_id', '=', 't6.id')
                    ->join('ar_asset_statuses as t7', 't2.status_id', '=', 't7.id')
                    //->join('ar_asset_maintainance_repair_locations as t8','t8.id','=','t1.location_id')
                    
                    ->selectRaw('t1.id as maintainance_record_id,t1.*, t2.description,t2.serial_no,t2.grz_no,t4.id as site_id,t4.name as site_name,
                    t2.location_id,t2.department_id,t5.name as location_name,t6.name as department_name, t7.name as record_status,
                  t2.id as parent_asset_id');
                   
                   $qry->where('maintainance_status', 1);
                   $results = $qry->get();
                    $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                    );

                    break;
                
                case "stores_under_maintainance":
                    $qry = DB::table('stores_asset_maintainance as t1')
                    ->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
                   
                    ->join('stores_sites as t4', 't2.site_id', '=', 't4.id')
                    ->leftjoin('stores_locations as t5', 't2.location_id', '=', 't5.id')
                    ->leftjoin('stores_departments as t6', 't2.department_id', '=', 't6.id')
                    ->join('stores_statuses as t7', 't2.status_id', '=', 't7.id')
                    
                    ->selectRaw('t1.id as maintainance_record_id,t1.*, t2.description,t2.serial_no,t2.grz_no,t4.id as site_id,t4.name as site_name,
                    t2.location_id,t2.department_id,t5.name as location_name,t6.name as department_name, t7.name as record_status,
                  t2.id as parent_asset_id');
                   
                   $qry->where('maintainance_status', 1);
                   $results = $qry->get();
                    $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                    );

                    break;
                case "maintainance_completed":
                    $qry = DB::table('ar_asset_maintainance as t1')
                    ->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
                   
                    ->join('ar_asset_sites as t4', 't2.site_id', '=', 't4.id')
                    ->leftjoin('ar_asset_locations as t5', 't2.location_id', '=', 't5.id')
                    ->leftjoin('departments as t6', 't2.department_id', '=', 't6.id')
                    ->join('ar_asset_statuses as t7', 't2.status_id', '=', 't7.id')
                    //->join('ar_asset_maintainance_repair_locations as t8','t8.id','=','t1.location_id')
                    
                    ->selectRaw('t1.id as maintainance_record_id,t1.*,t2.id as parent_asset_id, t2.description,t2.serial_no,t2.grz_no,t4.id as site_id,t4.name as site_name,
                    t2.location_id,t2.department_id,t5.name as location_name,t6.name as department_name, t7.name as record_status,
                      t1.date_maintainance_completed,t1.maintainance_cost');
                    
                    $qry->where('maintainance_status', 2);
                    $results = $qry->get();
                  
                    $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                    );
                    break;
                case "stores_maintainance_completed":
                    $qry = DB::table('stores_asset_maintainance as t1')
                    ->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
                   
                    ->join('stores_sites as t4', 't2.site_id', '=', 't4.id')
                    ->leftjoin('stores_locations as t5', 't2.location_id', '=', 't5.id')
                    ->leftjoin('stores_departments as t6', 't2.department_id', '=', 't6.id')
                    ->join('stores_statuses as t7', 't2.status_id', '=', 't7.id')
                    //->join('ar_asset_maintainance_repair_locations as t8','t8.id','=','t1.location_id')
                    
                    ->selectRaw('t1.id as maintainance_record_id,t1.*,t2.id as parent_asset_id, t2.description,t2.serial_no,t2.grz_no,t4.id as site_id,t4.name as site_name,
                    t2.location_id,t2.department_id,t5.name as location_name,t6.name as department_name, t7.name as record_status,
                      t1.date_maintainance_completed,t1.maintainance_cost');
                    
                    $qry->where('maintainance_status', 2);
                    $results = $qry->get();
                  
                    $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                    );
                    break;
                case "under_repair":
                     
                    $qry = DB::table('ar_asset_repairs as t1')
                    ->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
                
                    ->join('ar_asset_sites as t4', 't2.site_id', '=', 't4.id')
                    ->leftjoin('ar_asset_locations as t5', 't2.location_id', '=', 't5.id')
                    ->leftjoin('departments as t6', 't2.department_id', '=', 't6.id')
                    ->join('ar_asset_statuses as t7', 't2.status_id', '=', 't7.id')
                    //->join('ar_asset_maintainance_repair_locations as t8','t8.id','=','t1.location_id')
                    ->selectRaw('t1.id as repair_record_id,t1.*, t2.description,t2.serial_no,t2.grz_no,t4.id as site_id,t4.name as site_name,
                    t2.location_id,t2.department_id,t5.name as location_name,t6.name as department_name, t7.name as record_status,t1.scheduled_repair_date,
                    t1.repair_status');
                
                    $qry->where('repair_status', 1);
                    $results = $qry->get();
                  
                    $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                    );
                    break;
                case "stores_under_repair":
                     
                    $qry = DB::table('stores_asset_repairs as t1')
                    ->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
                    ->join('stores_sites as t4', 't2.site_id', '=', 't4.id')
                    ->leftjoin('stores_locations as t5', 't2.location_id', '=', 't5.id')
                    ->leftjoin('stores_departments as t6', 't2.department_id', '=', 't6.id')
                    ->join('stores_statuses as t7', 't2.status_id', '=', 't7.id')
                    ->selectRaw('t1.id as repair_record_id,t1.*, t2.description,t2.serial_no,t2.grz_no,t4.id as site_id,t4.name as site_name,
                    t2.location_id,t2.department_id,t5.name as location_name,t6.name as department_name, t7.name as record_status,t1.scheduled_repair_date,
                    t1.repair_status');
                
                    $qry->where('repair_status', 1);
                    $results = $qry->get();
                  
                    $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                    );
                    break;
                case "repair_completed":
                    $qry = DB::table('ar_asset_repairs as t1')
                    ->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
                
                    ->join('ar_asset_sites as t4', 't2.site_id', '=', 't4.id')
                    ->leftjoin('ar_asset_locations as t5', 't2.location_id', '=', 't5.id')
                    ->leftjoin('departments as t6', 't2.department_id', '=', 't6.id')
                    ->join('ar_asset_statuses as t7', 't2.status_id', '=', 't7.id')
                   // ->join('ar_asset_maintainance_repair_locations as t8','t8.id','=','t1.location_id')
                    ->selectRaw('t1.id as repair_record_id,t1.*, t2.description,t2.serial_no,t2.grz_no,t4.id as site_id,t4.name as site_name,
                    t2.location_id,t2.department_id,t5.name as location_name,t6.name as department_name,t7.id as asset_status_id,t7.name as record_status,t1.scheduled_repair_date,
                    t1.repair_status,t1.repair_successful as repair_success');
                
                    $qry->where('repair_status', 2); 
                        
                  // dd( $qry->toSQL());

                    
                    $results = $qry->get();
                  
                    $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                    );
                    break;
                case "stores_repair_completed":
                    $qry = DB::table('stores_asset_repairs as t1')
                    ->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
                
                    ->join('stores_sites as t4', 't2.site_id', '=', 't4.id')
                    ->leftjoin('stores_locations as t5', 't2.location_id', '=', 't5.id')
                    ->leftjoin('stores_departments as t6', 't2.department_id', '=', 't6.id')
                    ->join('stores_statuses as t7', 't2.status_id', '=', 't7.id')
                   // ->join('ar_asset_maintainance_repair_locations as t8','t8.id','=','t1.location_id')
                    ->selectRaw('t1.id as repair_record_id,t1.*, t2.description,t2.serial_no,t2.grz_no,t4.id as site_id,t4.name as site_name,
                    t2.location_id,t2.department_id,t5.name as location_name,t6.name as department_name,t7.id as asset_status_id,t7.name as record_status,t1.scheduled_repair_date,
                    t1.repair_status,t1.repair_successful as repair_success');
                
                    $qry->where('repair_status', 2); 
                        
                 

                    
                    $results = $qry->get();
                  
                    $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                    );
                    break;
                case "asset_for_deletion":
                    $qry = DB::table('ar_asset_inventory as t1')
                   
                    ->join('ar_asset_statuses as t8', 't1.status_id', '=', 't8.id')
                    ->selectRaw("t1.id as record_id,t1.id as parent_asset_id,t1.serial_no,t1.grz_no,t1.description")
                    ->where('module_id',350);
                    $qry->where('status_id','=','1');
                    $results = $qry->get();
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($results),
                        'results' => $results
                    );
                    break;
                 case "stores_asset_for_deletion":
                    $qry = DB::table('ar_asset_inventory as t1')
                   
                    ->join('stores_statuses as t8', 't1.status_id', '=', 't8.id')
                    ->selectRaw("t1.id as record_id,t1.id as parent_asset_id,t1.serial_no,t1.grz_no,t1.description")
                    ->where('module_id',637);
                    $qry->where('status_id','=','1');
                    $results = $qry->get();
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($results),
                        'results' => $results
                    );
                    break;
                case "archived_assets":
                    $qry = DB::table('ar_asset_archive as t1')
                    ->join('ar_asset_inventory as t2','t2.id','=','t1.asset_id')
                    ->join('users as t3', 't1.archived_by', '=', 't3.id')
                    ->selectRaw("t1.id as record_id,t2.serial_no,t2.grz_no,t2.description,t1.archive_date,t1.archive_date,
                    CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as archirved_by,t2.id as asset_id")
                    ->where('t2.module_id',350);
                   
                    $results = $qry->get();
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($results),
                        'results' => $results
                    );
                    break;
                case "stores_archived_assets":
                        $qry = DB::table('ar_asset_archive as t1')
                        ->join('ar_asset_inventory as t2','t2.id','=','t1.asset_id')
                        ->join('users as t3', 't1.archived_by', '=', 't3.id')
                        ->selectRaw("t1.id as record_id,t2.serial_no,t2.grz_no,t2.description,t1.archive_date,t1.archive_date,
                        CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as archirved_by,t2.id as asset_id")
                        ->where('t2.module_id',637);
                       
                        $results = $qry->get();
                        $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                        );
                        break;
                case "assets_transferable":
                   $qry= DB::table('ar_asset_checkout_details as t1')
                     ->leftjoin('users as t2','t1.user_id','=','t2.id')
                    ->join('ar_asset_inventory as t3','t1.asset_id','t3.id')
                    ->join('ar_asset_sites as t4', 't1.checkout_site_id', '=', 't4.id')
                    ->leftjoin('ar_asset_locations as t5', 't1.checkout_location_id', '=', 't5.id')
                    ->leftjoin('departments as t6', 't1.checkout_department_id', '=', 't6.id')
                    ->selectRaw("t1.id as checkout_id,t1.asset_id as parent_asset_id,t4.name as site_name,t4.id as site_id,t3.description,
                    CONCAT_WS(' ',decrypt(t2.first_name),decrypt(t2.last_name)) as currently_assigned_user,t3.serial_no,t3.grz_no,
                    t5.name as location_name,t6.id as location_id,t6.name as department_name,t6.id as department_id,t2.id as currently_assigned_user_id");
                    // ->selectRaw("t1.id as record_id,t1.asset_id as parent_asset_id,t4.name as site_name,t3.description,
                    // t5.name as location_name,t6.name as department_name,CONCAT_WS('-',t3.description,t3.grz_no) as desc_grz_no,
                    // t2.id as currently_assigned_user_id,CONCAT_WS(' ',decrypt(t2.first_name),decrypt(t2.last_name)) as currently_assigned_user");
                   $qry->where('checkout_status','=','1');
                   $results = $qry->get()->toArray();
                
                 
                    // foreach($results as $key=>$result)
                    // {
                    //     $result=json_decode(json_encode($result),true);
                    //     $assigned_user= $qry = DB::table('ar_asset_checkout_details as t1')
                    //     ->join('users as t2', 't1.user_id', '=', 't2.id')
                    //     ->selectRaw("t1.user_id,CONCAT_WS(' ',decrypt(t2.first_name),decrypt(t2.last_name)) as assigned_user,t1.id as checkout_id")
                    //         ->where('asset_id','=',$result['id'])
                    //         ->latest('t1.created_at')->first();
                          
                    //     $assigned_user_array=json_decode(json_encode($assigned_user),true);
                    //     $assets[]=$assigned_user_array;
                    //     $result['currently_assigned_user_id']=$assigned_user_array['user_id'];
                    //     $result['currently_assigned_user']=$assigned_user_array['assigned_user'];
                    //     $result['checkout_id']=$assigned_user_array['checkout_id'];
                    //    $results[$key]=$result;
                    // }
                    
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($results),
                        'results' => $results
                    );
                    break;
                 case "stores_assets_transferable":
                   $qry= DB::table('stores_asset_checkout_details as t1')
                     ->leftjoin('users as t2','t1.user_id','=','t2.id')
                    ->join('ar_asset_inventory as t3','t1.asset_id','t3.id')
                    ->join('stores_sites as t4', 't1.checkout_site_id', '=', 't4.id')
                    ->leftjoin('stores_locations as t5', 't1.checkout_location_id', '=', 't5.id')
                    ->leftjoin('stores_departments as t6', 't1.checkout_department_id', '=', 't6.id')
                    ->selectRaw("t1.id as checkout_id,t1.asset_id as parent_asset_id,t4.name as site_name,t4.id as site_id,t3.description,
                    CONCAT_WS(' ',decrypt(t2.first_name),decrypt(t2.last_name)) as currently_assigned_user,t3.serial_no,t3.grz_no,
                    t5.name as location_name,t6.id as location_id,t6.name as department_name,t6.id as department_id,t2.id as currently_assigned_user_id");
                      $qry->where('checkout_status','=','1');
                   $results = $qry->get()->toArray();
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($results),
                        'results' => $results
                    );
                    break;
                case "report_order":
                    $report_type=$request->query('report_type');
                    switch($report_type)
                    {   
                        case 12:
                        case 11:
                            $filter_columns=[
                                ["id"=>"description","text"=>"Description"],
                            ];
                            $res = array(
                                'success' => true,
                                'message' => returnMessage($filter_columns),
                                'results' => $filter_columns
                            );
                            break;
                        case 10:
                            $filter_columns=[
                                ["id"=>"description","text"=>"Description"],
                                ["id"=>"archive_date","text"=>"Archive Date"],
                            ];
                            $res = array(
                                'success' => true,
                                'message' => returnMessage($filter_columns),
                                'results' => $filter_columns
                            );
                            break;
                        case 9:
                        case 8:
                            $filter_columns=[
                                ["id"=>"description","text"=>"Description"],
                                ["id"=>"loss_damage_date","text"=>"Loss/Damage Date"],
                            ];
                            $res = array(
                                'success' => true,
                                'message' => returnMessage($filter_columns),
                                'results' => $filter_columns
                            );
                            break;
                        case 7:
                            $filter_columns=[
                                ["id"=>"description","text"=>"Description"],
                                ["id"=>"date_written_off","text"=>"Date Written-Off"],
                            ];
                            $res = array(
                                'success' => true,
                                'message' => returnMessage($filter_columns),
                                'results' => $filter_columns
                            );
                         break;
                        case 6:
                            $filter_columns=[
                                ["id"=>"description","text"=>"Description"],
                                ["id"=>"scheduled_repair_date","text"=>"Scheduled Repair Date"],
                            ];
                            $res = array(
                                'success' => true,
                                'message' => returnMessage($filter_columns),
                                'results' => $filter_columns
                            );
                            break;
                        case 5:
                            $filter_columns=[
                                ["id"=>"description","text"=>"Description"],
                                ["id"=>"maintainance_due_date","text"=>"Maintainance Due Date"],
                            ];
                            $res = array(
                                'success' => true,
                                'message' => returnMessage($filter_columns),
                                'results' => $filter_columns
                            );
                             break;
                        case 4:
                            $filter_columns=[
                                ["id"=>"description","text"=>"Description"],
                                ["id"=>"salvagevalue","text"=>"Salvage Value"],
                            ];
                            $res = array(
                                'success' => true,
                                'message' => returnMessage($filter_columns),
                                'results' => $filter_columns
                            );
                            break;
                        case 3:
                            $filter_columns=[
                                ["id"=>"firstname","text"=>"First Name"], 
                                ["id"=>"middlename","text"=>"Middle Name"], 
                                ["id"=>"lastname","text"=>"Last Name"], 
                            ];
                            $res = array(
                                'success' => true,
                                'message' => returnMessage($filter_columns),
                                'results' => $filter_columns
                            );
                            break;
                        case 1:
                            $filter_columns=[
                               ["id"=>"description","text"=>"Description"],
                               ["id"=>"category_name","text"=>"Category"],
                               //['id'=>'sub_category_name','text'=>"Sub-Category"],
                               ["id"=>"brand_name","text"=>"Brand"],
                               ["id"=>"model_name","text"=>"Model"],
                               ["id"=>"purchase_from","text"=>"Purchase From"],
                               ["id"=>"purchase_date","text"=>"Purchase Date"],
                               ["id"=>"cost","text"=>"Cost"],
                               ["id"=>"serial_no","text"=>"Serial No"],
                               ["id"=>"grz_no","text"=>"Grz No"],
                               ["id"=>"site","text"=>"Site"],
                               ["id"=>"location_name","text"=>"Location"],
                               ["id"=>"department_name","text"=>"Department"],
                            ];
                            $res = array(
                                'success' => true,
                                'message' => returnMessage($filter_columns),
                                'results' => $filter_columns
                            );
                            break;
                    }
                    break;
                case "transfer_history":
                    $transfers = DB::table('ar_asset_transfers as t1')
                       
                    ->join('ar_asset_checkin_details as t2','t2.checkout_id','=','t1.checkout_id')
                    ->join('ar_asset_inventory as t3','t3.id','=','t1.asset_id')
                   
                    ->selectRaw("t1.id as record_id,t1.asset_id as transfer_asset_id,t1.transfer_category,t1.transfer_reason,t1.transfer_date,
                    t1.user_transfered_to,t1.user_transfered_from,t1.site_transfered_to,t1.location_transfered_to,t1.department_transfered_to,t2.checkin_site_id as
                    previous_site_id,t2.checkin_location_id as previous_location_id,t2.checkin_department_id as previous_department_id,
                    t3.serial_no,t3.grz_no,t3.description")->orderBy('t1.created_at','DESC')->get();
                    //return $transfers;
                    $transfers= json_decode(json_encode($transfers),true);
                    $sites = json_decode(json_encode(DB::table('ar_asset_sites as t2')->selectRaw('t2.id,t2.name')->get()->toArray()),true);
                    $locations = json_decode(json_encode(DB::table('ar_asset_locations as t3')->selectRaw('t3.id,t3.name')->get()->toArray()),true);
                    $deparments = json_decode(json_encode(DB::table('departments as t3')->selectRaw('t3.id,t3.name')->get()->toArray()),true);    
                    $users = json_decode(json_encode(DB::table('users as t3')->selectRaw("t3.id,CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as assigned_user")->get()->toArray()),true);     
                    foreach($transfers as $key=>$transfer)
                    {   //user
                        if($transfer['user_transfered_to']!=null)
                        {
                            foreach($users as $user)
                            {
        
                                if($user['id']==$transfer['user_transfered_to'])
                                {
                                    $transfer['user_transfered_to_name']=$user['assigned_user'];
                                }
                               
                            }
                        }

                        //user
                        if($transfer['user_transfered_from']!=null)
                        {
                            foreach($users as $user)
                            {
        
                                if($user['id']==$transfer['user_transfered_from'])
                                {
                                    $transfer['user_transfered_from_name']=$user['assigned_user'];
                                }
                               
                            }
                        }
                        //site
                        if($transfer['site_transfered_to']!=null)
                        {
                            foreach($sites as $site)
                            {
        
                                if($site['id']==$transfer['site_transfered_to'])
                                {
                                    $transfer['site_transfered_to_name']=$site['name'];
                                }
                               
                            }
                        }
                        if($transfer['previous_site_id']!=null)
                        {
                            foreach($sites as $site)
                            {
        
                                if($site['id']==$transfer['previous_site_id'])
                                {
                                    $transfer['previous_site_name']=$site['name'];
                                }
                               
                            }
                        }
                        //location
                        if($transfer['location_transfered_to']!=null)
                        {
                            foreach($locations as $location)
                            {
        
                                if($location['id']==$transfer['location_transfered_to'])
                                {
                                    $transfer['location_transfered_to_name']=$location['name'];
                                }
                               
                            }
                        }
                        if($transfer['previous_location_id']!=null)
                        {
                            foreach($locations as $location)
                            {
        
                                if($location['id']==$transfer['previous_location_id'])
                                {
                                    $transfer['previous_location_name']=$location['name'];
                                }
                               
                            }
                        }
                        //department
                        if($transfer['department_transfered_to']!=null)
                        {
                            foreach($deparments as $deparment)
                            {
        
                                if($deparment['id']==$transfer['department_transfered_to'])
                                {
                                    $transfer['department_transfered_to_name']=$deparment['name'];
                                }
                               
                            }
                        }
                        if($transfer['previous_department_id']!=null)
                        {
                            foreach($deparments as $deparment)
                            {
        
                                if($deparment['id']==$transfer['previous_department_id'])
                                {
                                    $transfer['previous_department_name']=$deparment['name'];
                                }
                               
                            }
                        }
                        $transfers[$key]=$transfer;
                      
                    }
                  
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($transfers),
                        'results' => $transfers
                    );
                    break;
                case "stores_transfer_history":
                    $transfers = DB::table('stores_asset_transfers as t1')
                       
                    ->join('stores_asset_checkin_details as t2','t2.checkout_id','=','t1.checkout_id')
                    ->join('ar_asset_inventory as t3','t3.id','=','t1.asset_id')
                   
                    ->selectRaw("t1.asset_id as transfer_asset_id,t1.transfer_category,t1.transfer_reason,1.transfer_date,
                    t1.user_transfered_to,t1.user_transfered_from,t1.site_transfered_to,t1.location_transfered_to,t1.department_transfered_to,t2.checkin_site_id as
                    previous_site_id,t2.checkin_location_id as previous_location_id,t2.checkin_department_id as previous_department_id,
                    t3.serial_no,t3.grz_no,t3.description")->orderBy('t1.created_at','DESC')->get();
                    //return $transfers;
                    $transfers= json_decode(json_encode($transfers),true);
                    $sites = json_decode(json_encode(DB::table('ar_asset_sites as t2')->selectRaw('t2.id,t2.name')->get()->toArray()),true);
                    $locations = json_decode(json_encode(DB::table('ar_asset_locations as t3')->selectRaw('t3.id,t3.name')->get()->toArray()),true);
                    $deparments = json_decode(json_encode(DB::table('departments as t3')->selectRaw('t3.id,t3.name')->get()->toArray()),true);    
                    $users = json_decode(json_encode(DB::table('users as t3')->selectRaw("t3.id,CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as assigned_user")->get()->toArray()),true);     
                    foreach($transfers as $key=>$transfer)
                    {   //user
                        if($transfer['user_transfered_to']!=null)
                        {
                            foreach($users as $user)
                            {
        
                                if($user['id']==$transfer['user_transfered_to'])
                                {
                                    $transfer['user_transfered_to_name']=$user['assigned_user'];
                                }
                               
                            }
                        }

                        //user
                        if($transfer['user_transfered_from']!=null)
                        {
                            foreach($users as $user)
                            {
        
                                if($user['id']==$transfer['user_transfered_from'])
                                {
                                    $transfer['user_transfered_from_name']=$user['assigned_user'];
                                }
                               
                            }
                        }
                        //site
                        if($transfer['site_transfered_to']!=null)
                        {
                            foreach($sites as $site)
                            {
        
                                if($site['id']==$transfer['site_transfered_to'])
                                {
                                    $transfer['site_transfered_to_name']=$site['name'];
                                }
                               
                            }
                        }
                        if($transfer['previous_site_id']!=null)
                        {
                            foreach($sites as $site)
                            {
        
                                if($site['id']==$transfer['previous_site_id'])
                                {
                                    $transfer['previous_site_name']=$site['name'];
                                }
                               
                            }
                        }
                        //location
                        if($transfer['location_transfered_to']!=null)
                        {
                            foreach($locations as $location)
                            {
        
                                if($location['id']==$transfer['location_transfered_to'])
                                {
                                    $transfer['location_transfered_to_name']=$location['name'];
                                }
                               
                            }
                        }
                        if($transfer['previous_location_id']!=null)
                        {
                            foreach($locations as $location)
                            {
        
                                if($location['id']==$transfer['previous_location_id'])
                                {
                                    $transfer['previous_location_name']=$location['name'];
                                }
                               
                            }
                        }
                        //department
                        if($transfer['department_transfered_to']!=null)
                        {
                            foreach($deparments as $deparment)
                            {
        
                                if($deparment['id']==$transfer['department_transfered_to'])
                                {
                                    $transfer['department_transfered_to_name']=$deparment['name'];
                                }
                               
                            }
                        }
                        if($transfer['previous_department_id']!=null)
                        {
                            foreach($deparments as $deparment)
                            {
        
                                if($deparment['id']==$transfer['previous_department_id'])
                                {
                                    $transfer['previous_department_name']=$deparment['name'];
                                }
                               
                            }
                        }
                        $transfers[$key]=$transfer;
                      
                    }
                  
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($transfers),
                        'results' => $transfers
                    );
                    break;
                case "asset_history":
                    $qry = DB::table('ar_asset_history as t1')
                    ->join('ar_asset_inventory as t3','t1.asset_id','=','t3.id')
                    ->join('users as t2', 't1.created_by', '=', 't2.id')
                    ->selectRaw("t1.*,t1.action as action_on_asset, t1.created_at as action_date,t1.action as action,CONCAT_WS(' ',decrypt(t2.first_name),decrypt(t2.last_name)) as action_user,
                    t3.grz_no,t3.serial_no,t3.description")
                    ->where('t3.module_id',350);
                    $results=$qry->get();
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($results),
                        'results' => $results
                    );
                    break;
                case "stores_asset_history":
                    $qry = DB::table('ar_asset_history as t1')
                    ->join('ar_asset_inventory as t3','t1.asset_id','=','t3.id')
                    ->join('users as t2', 't1.created_by', '=', 't2.id')
                    ->selectRaw("t1.*,t1.action as action_on_asset, t1.created_at as action_date,t1.action as action,CONCAT_WS(' ',decrypt(t2.first_name),decrypt(t2.last_name)) as action_user,
                    t3.grz_no,t3.serial_no,t3.description")
                    ->where('t3.module_id',637);
                    $results=$qry->get();
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($results),
                        'results' => $results
                    );
                    break;
                case "assets_past_due":

                    $normal_user_checkouts = DB::table('ar_asset_checkout_details as t1')
                    ->join('ar_asset_inventory as t2','t1.asset_id','=','t2.id')
                    ->join('users as t3', 't1.user_id', '=', 't3.id')
                 
                    ->join('ar_asset_sites as t4', 't1.checkout_site_id', '=', 't4.id')
                    ->leftjoin('ar_asset_locations as t5', 't1.checkout_location_id', '=', 't5.id')
                    ->leftjoin('departments as t6', 't1.checkout_department_id', '=', 't6.id')
                    ->selectRaw("t2.description,t2.serial_no,t2.grz_no,t1.due_date,DATEDIFF(CURDATE(),t1.due_date) as days_due,
                    CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as assigned_user,t4.name as site_name,t5.name as location_name,t6.name as department_name")
                    ->where('t1.due_date','<',Carbon::now()->format('Y-m-d'))
                    ->where('checkout_status','=','1')->get()->toArray();

                    $normal_site_checkouts = DB::table('ar_asset_checkout_details as t1')
                    ->join('ar_asset_inventory as t2','t1.asset_id','=','t2.id')
                    ->join('ar_asset_sites as t4', 't1.checkout_site_id', '=', 't4.id')
                    ->leftjoin('ar_asset_locations as t5', 't1.checkout_location_id', '=', 't5.id')
                    ->leftjoin('departments as t6', 't1.checkout_department_id', '=', 't6.id')
                    ->selectRaw("t2.description,t2.serial_no,t2.grz_no,t1.due_date,DATEDIFF(CURDATE(),t1.due_date) as days_due,
                   t4.name as site_name,t5.name as location_name,t6.name as department_name")
                    ->where('t1.due_date','<',Carbon::now()->format('Y-m-d'))
                    ->where('checkout_status','=','1')
                   ->where('user_id',NULL)
                   
                    ->get()->toArray();
                
                    $users = DB::table('users as t1')
                     ->selectRaw("t1.id,
                    CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.middlename),decrypt(t1.last_name)) as name")->get();
                    $users_ids=convertStdClassObjToArray($users);

                    $group_checkouts=DB::table('ar_asset_checkout_details as t1')
                    ->join('ar_asset_inventory as t2','t2.id','=','t1.asset_id')
                    ->join('ar_asset_sites as t4', 't1.checkout_site_id', '=', 't4.id')
                    ->leftjoin('ar_asset_locations as t5', 't1.checkout_location_id', '=', 't5.id')
                    ->leftjoin('departments as t6', 't1.checkout_department_id', '=', 't6.id')
                    ->where('checkout_status',1)
                    ->where('is_group_checkout',1)
                    ->selectRaw("t2.description,t2.serial_no,t2.grz_no,t1.due_date,DATEDIFF(CURDATE(),t1.due_date) as days_due,t4.name as site_name,t5.name as location_name,t6.name as department_name,user_id")
                    ->get();
                    

                $group_checkouts=convertStdClassObjToArray($group_checkouts);
           
                foreach($group_checkouts as $key=>$group_checkout)
                {
                    $user_ids=$this->returnArrayFromStringArray($group_checkout['user_id']);
                    foreach($user_ids as $user_id)
                    {
                       
                        $user_details=$this->_search_array_by_value($users_ids,'id',$user_id);
                        $group_checkout["assigned_user"]=$user_details['name'];
                        $group_checkouts[$key]=$group_checkout;
                       

                    }
                    
                    
                }
               

                $individual_bulk_checkouts=DB::table('ar_asset_checkout_details as t1')
                    ->join('users as t3', 't1.user_id', '=', 't3.id')
                    ->join('ar_asset_sites as t4', 't1.checkout_site_id', '=', 't4.id')
                    ->leftjoin('ar_asset_locations as t5', 't1.checkout_location_id', '=', 't5.id')
                    ->leftjoin('departments as t6', 't1.checkout_department_id', '=', 't6.id')
                    ->where('checkout_status',1)
                    ->where('is_individual_bulk_checkout',1)
                   
                    ->selectRaw("asset_id,t1.due_date,DATEDIFF(CURDATE(),t1.due_date) as days_due,t4.name as site_name,t5.name as location_name,t6.name as department_name,
                    CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as assigned_user")
                    ->get();
                    $individual_bulk_checkouts=convertStdClassObjToArray($individual_bulk_checkouts);
                  
                    foreach($individual_bulk_checkouts as $key=>$checkout_data)
                    {
                        $asset_ids=$this->returnArrayFromStringArray($checkout_data['asset_id']);
                        foreach($asset_ids as $asset_id)
                        {
                            $asset_data=DB::table('ar_asset_inventory')->where('id',$asset_id)->selectRaw('description,serial_no,grz_no')->get()->toArray();
                            $checkout_data['description']=$asset_data[0]->description;
                            $checkout_data['serial_no']=$asset_data[0]->serial_no;
                            $checkout_data['grz_no']=$asset_data[0]->grz_no;
                            $individual_bulk_checkouts[$key]=$checkout_data;
                        }
                    }
                    

                    $site_bulk_checkouts=DB::table('ar_asset_checkout_details as t1')

                    ->join('ar_asset_sites as t4', 't1.checkout_site_id', '=', 't4.id')
                    ->leftjoin('ar_asset_locations as t5', 't1.checkout_location_id', '=', 't5.id')
                    ->leftjoin('departments as t6', 't1.checkout_department_id', '=', 't6.id')
                    ->where('checkout_status',1)
                    ->where('is_site_bulk_checkout',1)
                   
                    ->selectRaw("asset_id,t1.due_date,DATEDIFF(CURDATE(),t1.due_date) as days_due,t4.name as site_name,t5.name as location_name,t6.name as department_name")
                    ->get();
                    $site_bulk_checkouts=convertStdClassObjToArray($site_bulk_checkouts);
               
                    foreach($site_bulk_checkouts as $key=>$checkout_data)
                    {
                        $asset_ids=$this->returnArrayFromStringArray($checkout_data['asset_id']);
                        foreach($asset_ids as $asset_id)
                        {
                            $asset_data=DB::table('ar_asset_inventory')->where('id',$asset_id)->selectRaw('description,serial_no,grz_no')->get()->toArray();
                            $checkout_data['description']=$asset_data[0]->description;
                            $checkout_data['serial_no']=$asset_data[0]->serial_no;
                            $checkout_data['grz_no']=$asset_data[0]->grz_no;
                            $site_bulk_checkouts[$key]=$checkout_data;
                        }
                    }
                    
                    $results=array_merge($normal_user_checkouts,  $normal_site_checkouts,$group_checkouts,$individual_bulk_checkouts,$site_bulk_checkouts);
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($results),
                        'results' => $results
                    );
                    break;
                case "insurances_expiring":
                    $qry = DB::table('ar_insurance_policies as t1')
                    ->where('t1.end_date','<=',  Carbon::now()->addDays(15)->format('Y-m-d'))//equal to no. days to expiry
                    ->where('t1.end_date','>',  Carbon::now()->addDays(1)->format('Y-m-d'))
                    //->orwhere("t1.end_date","<",Carbon::now()->format('Y-m-d'))
                    ->selectRaw("t1.*,DATEDIFF(t1.end_date,CURDATE()) as days_to_expiry");
                    $expired_ones= DB::table('ar_insurance_policies as t1')
                    ->where('t1.end_date','<', now()->format('Y-m-d'))
                    ->selectRaw("t1.*,DATEDIFF(t1.end_date,CURDATE()) as days_to_expiry")->get()->toArray();
                    $results = $qry->get()->toArray();
                    $results=array_merge($results,$expired_ones);
                    $res = array(
                        'success' => true,
                        'results' => $results
                    );
                    
                    break;
                case "funds_expiring":
                    $qry = DB::table('ar_funding_details as t1')
                    ->where('t1.end_date','<',  Carbon::now()->addDays(15)->format('Y-m-d'))//equal to no. days to expiry
                    ->where('t1.end_date','>',  Carbon::now()->addDays(1)->format('Y-m-d'))
                    //->orwhere("t1.end_date","<",Carbon::now()->format('Y-m-d'))
                ->selectRaw("t1.*,(t1.funding_amount-(Select sum(amount) from ar_asset_funding_linkage where funding_id=t1.id)) as available_amount,
                   @available_amount := (t1.funding_amount-(Select COALESCE(sum(amount),0) from ar_asset_funding_linkage where funding_id=t1.id)) as available_amount,
                   CONCAT(t1.name,' Available ',@available_amount,' (',DATE_FORMAT(t1.start_date, '%d/%m/%Y'),'-',DATE_FORMAT(t1.end_date, '%d/%m/%Y'),')') as display,
                   DATEDIFF(t1.end_date,CURDATE()) as days_to_expiry");
                    $results = $qry->get()->toArray();
                    $expired_ones = DB::table('ar_funding_details as t1')
                    ->where("t1.end_date","<",Carbon::now()->format('Y-m-d'))
                    ->selectRaw("t1.*,(t1.funding_amount-(Select sum(amount) from ar_asset_funding_linkage where funding_id=t1.id)) as available_amount,
                    @available_amount := (t1.funding_amount-(Select COALESCE(sum(amount),0) from ar_asset_funding_linkage where funding_id=t1.id)) as available_amount,
                    CONCAT(t1.name,' Available ',@available_amount,' (',DATE_FORMAT(t1.start_date, '%d/%m/%Y'),'-',DATE_FORMAT(t1.end_date, '%d/%m/%Y'),')') as display,
                    DATEDIFF(t1.end_date,CURDATE()) as days_to_expiry")
                    ->get()
                    ->toArray();
                    $results=array_merge($results,$expired_ones);
                    $res = array(
                        'success' => true,
                        'results' => $results
                    );
                    break;
                case "requisition_approval":
                    $user_id=$this->user_id;
                    $qry = DB::table('ar_asset_requisitions as t1')
                    ->leftjoin('users as t3','t3.id','t1.requested_by')
                    ->join('ar_asset_subcategories as t2', 't1.asset_category', '=', 't2.id')//updated subcategeories table
                    ->selectRaw("t1.request_date,t2.name as requested_asset,t1.requisition_status as status,t1.created_by,
                    t1.asset_category,t1.remarks,t1.requested_for,onbehalfof, 
                    CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as requested_for_name")
                    ->where('t1.created_by',"=",$user_id)
                    ->whereIn('t1.requisition_status',[0,1,5])->orderBy('t1.created_at','DESC');
                    //exclude checked out
                   // ->where('t1.requisition_status',"<",2)->orderBy('t1.created_at','DESC');
                    $results = $qry->get();
                    $res = array(
                        'success' => true,
                        'results' => $results
                    );

                    break;
                case "stores_requisition_approval":
                    $user_id=$this->user_id;
                    $qry = DB::table('stores_asset_requisitions as t1')
                    ->leftjoin('users as t3','t3.id','t1.requested_by')
                    ->join('stores_subcategories as t2', 't1.asset_category', '=', 't2.id')//updated subcategeories table
                    ->selectRaw("t1.request_date,t2.name as requested_asset,t1.requisition_status as status,t1.created_by,
                    t1.asset_category,t1.remarks,t1.requested_for,onbehalfof, 
                    CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as requested_for_name")
                    ->where('t1.created_by',"=",$user_id)
                    ->whereIn('t1.requisition_status',[0,1,5])->orderBy('t1.created_at','DESC');
                    //exclude checked out
                   // ->where('t1.requisition_status',"<",2)->orderBy('t1.created_at','DESC');
                    $results = $qry->get();
                    $res = array(
                        'success' => true,
                        'results' => $results
                    );

                    break;
                case "requisition_pending_approval_individual":
                    $qry=DB::table('ar_asset_requisitions as t1')
                        ->join('users as t2','t2.id','t1.requested_by')
                        ->join('ar_asset_subcategories as t3','t3.id','t1.asset_category')
                        ->where('requested_for',1)
                        ->where('verified',1)
                        ->selectRaw("t3.name as requested_asset,t1.request_date as request_date,
                        CONCAT_WS(' ',decrypt(t2.first_name),decrypt(t2.last_name)) as requested_by,
                        t1.requisition_status as status,t1.id as requisition_record_id,t2.id as assigned_user_id,
                        t3.id as sub_category_id
                        ")->orderBy('t1.created_at','DESC');
                    $results=$qry->get();
                    $res = array(
                        'success' => true,
                        'results' => $results
                    );

                    break;
                case "stores_requisition_pending_approval_individual":
                    $qry=DB::table('stores_asset_requisitions as t1')
                        ->join('users as t2','t2.id','t1.requested_by')
                        ->join('stores_subcategories as t3','t3.id','t1.asset_category')
                        ->where('requested_for',1)
                        ->where('verified',1)
                        ->selectRaw("t3.name as requested_asset,t1.request_date as request_date,
                        CONCAT_WS(' ',decrypt(t2.first_name),decrypt(t2.last_name)) as requested_by,
                        t1.requisition_status as status,t1.id as requisition_record_id,t2.id as assigned_user_id,
                        t3.id as sub_category_id
                        ")->orderBy('t1.created_at','DESC');
                    $results=$qry->get();
                    $res = array(
                        'success' => true,
                        'results' => $results
                    );

                    break;
                case "requisition_pending_approval_site":
                        $qry=DB::table('ar_asset_requisitions as t1')
                          ->join('ar_asset_sites as t2','t2.id','=','t1.requistion_site_id')
                            ->join('ar_asset_subcategories as t3','t3.id','t1.asset_category')
                            ->where('requested_for',2)
                            ->where('verified',1)
                            ->selectRaw("t3.name as requested_asset,t1.request_date as request_date,
                           t2.name  as site_name,t1.requisition_status as status,t1.id as requisition_record_id,
                           t2.id as assigned_site_id,t3.id as sub_category_id")
                           ->orderBy("t1.created_at","DESC");
                        $results=$qry->get();
                        $res = array(
                            'success' => true,
                            'results' => $results
                        );
                        break;
              case "stores_requisition_pending_approval_site":
                        $qry=DB::table('stores_asset_requisitions as t1')
                          ->join('stores_sites as t2','t2.id','=','t1.requistion_site_id')
                            ->join('stores_subcategories as t3','t3.id','t1.asset_category')
                            ->where('requested_for',2)
                            ->where('verified',1)
                            ->selectRaw("t3.name as requested_asset,t1.request_date as request_date,
                           t2.name  as site_name,t1.requisition_status as status,t1.id as requisition_record_id,
                           t2.id as assigned_site_id,t3.id as sub_category_id")
                           ->orderBy("t1.created_at","DESC");
                        $results=$qry->get();
                        $res = array(
                            'success' => true,
                            'results' => $results
                        );
                        break;
                 case "requisition_approval_approved":

                    $qry = DB::table('ar_asset_subcategories as t1')
                        ->selectRaw('t1.id,t1.name');
                        $category_ids=convertStdClassObjToArray($qry->get());
                        
                        $requisition_ids=[];
                        
                        $clean_requisition_records=[];
                        foreach($category_ids as $category)
                        {
                            $qry = DB::table('ar_asset_requisitions as t2')
                            ->join('ar_asset_subcategories as t1','t1.id','t2.asset_category')
                            ->join('users as t3','t3.id','=','t2.requested_by')
                            ->where('asset_category',$category['id'])
                            ->where('requisition_status',1)
                            ->selectRaw("t2.id as requisition_record_id,t2.request_date,t1.name as requested_asset,
                            t2.requisition_status as status,t2.requested_by as created_by,t2.asset_category,
                           CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name))  as requested_by,decrypt(t3.phone) as phone,
                            t2.requistion_site_id,t2.requistion_location_id,t2.requistion_department_id")
                            ->orderBy('t2.created_at','DESC');
                            //requested at replaces created at,alias remains on 10/1/2022 job
                            // ->selectRaw("t3.id as requisition_user_id,t2.id as requistion_id,
                            // CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as requested_made_by");
                            $requisition_records=convertStdClassObjToArray($qry->get());
                            
                            $category_user_requests_count=count($requisition_records);
                            if($category_user_requests_count==1){
                             $clean_requisition_records[]=$requisition_records[0];
                            }

                            $other_requisitions = DB::table('ar_asset_requisitions as t2')
                            ->join('ar_asset_subcategories as t1','t1.id','t2.asset_category')
                            ->join('users as t3','t3.id','=','t2.requested_by')
                            //->join('ar_asset_categories as t4','t4.id','=','t2.asset_category')
                            ->where('requisition_status',">","1")
                            ->where('is_single_checkout',1)
                            ->selectRaw("t2.id as requisition_record_id,t2.request_date,t1.name as requested_asset,
                            t2.requisition_status as status,t2.created_by,t2.asset_category,
                           CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name))  as requested_by,decrypt(t3.phone) as phone,
                            t2.requistion_site_id,t2.requistion_location_id,t2.requistion_department_id")
                            ->orderBy('t2.created_at','DESC')
                               ->get();
                            $other_requisitions=convertStdClassObjToArray($other_requisitions);
                            
                            $combined_results="";
                            if(count($other_requisitions)>0)
                            {
                                $combined_results=array_merge($clean_requisition_records,$other_requisitions);
                            }else{
                                $combined_results=$clean_requisition_records;
                            }
                        
                        }
                        
                        $res = array(
                            'success' => true,
                            'message' => returnMessage($combined_results),
                            'results' => $combined_results
                        );
                    break;
                  case "stores_requisition_approval_approved":

                        $qry = DB::table('stores_subcategories as t1')
                            ->selectRaw('t1.id,t1.name');
                            $category_ids=convertStdClassObjToArray($qry->get());
                            
                            $requisition_ids=[];
                            
                            $clean_requisition_records=[];
                            foreach($category_ids as $category)
                            {
                                $qry = DB::table('stores_asset_requisitions as t2')
                                ->join('stores_subcategories as t1','t1.id','t2.asset_category')
                                ->join('users as t3','t3.id','=','t2.requested_by')
                                ->where('asset_category',$category['id'])
                                ->where('requisition_status',1)
                                ->selectRaw("t2.id as requisition_record_id,t2.request_date,t1.name as requested_asset,
                                t2.requisition_status as status,t2.requested_by as created_by,t2.asset_category,
                               CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name))  as requested_by,decrypt(t3.phone) as phone,
                                t2.requistion_site_id,t2.requistion_location_id,t2.requistion_department_id")
                                ->orderBy('t2.created_at','DESC');
                                
                                $requisition_records=convertStdClassObjToArray($qry->get());
                                
                                $category_user_requests_count=count($requisition_records);
                                if($category_user_requests_count==1){
                                 $clean_requisition_records[]=$requisition_records[0];
                                }
    
                                $other_requisitions = DB::table('stores_asset_requisitions as t2')
                                ->join('stores_subcategories as t1','t1.id','t2.asset_category')
                                ->join('users as t3','t3.id','=','t2.requested_by')
                                //->join('ar_asset_categories as t4','t4.id','=','t2.asset_category')
                                ->where('requisition_status',">","1")
                                ->where('is_single_checkout',1)
                                ->selectRaw("t2.id as requisition_record_id,t2.request_date,t1.name as requested_asset,
                                t2.requisition_status as status,t2.created_by,t2.asset_category,
                               CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name))  as requested_by,decrypt(t3.phone) as phone,
                                t2.requistion_site_id,t2.requistion_location_id,t2.requistion_department_id")
                                ->orderBy('t2.created_at','DESC')
                                   ->get();
                                $other_requisitions=convertStdClassObjToArray($other_requisitions);
                                
                                $combined_results="";
                                if(count($other_requisitions)>0)
                                {
                                    $combined_results=array_merge($clean_requisition_records,$other_requisitions);
                                }else{
                                    $combined_results=$clean_requisition_records;
                                }
                            
                            }
                            $res = array(
                                'success' => true,
                                'message' => returnMessage($combined_results),
                                'results' => $combined_results
                            );
                        break;
                    
                case "requisition_approval_approved_for_site":
                    //initial query
                //     $qry = DB::table('ar_asset_categories as t1')
                //     ->leftjoin('ar_asset_requisitions as t2','t1.id','=','t2.asset_category')
                //     ->join('ar_asset_sites as t3','t3.id','=','t2.requistion_site_id')
                //     ->join('users as t4','t2.created_by','=','t4.id')
                //     ->selectRaw("t2.id as requisition_record_id,t2.request_date,t1.name as requested_asset,
                //     t2.requisition_status as status,t2.created_by,t2.asset_category, t2.requistion_site_id,t2.requistion_location_id,t2.requistion_department_id,
                //     t3.name as site_name,CONCAT_WS(' ',decrypt(t4.first_name),decrypt(t4.last_name))  as requested_made_by,decrypt(t4.phone) as phone")
                //     //->groupBy('t2.asset_category')
                //     //->groupBy('t2.checkout_id')
                //    ->havingRaw('count(t2.asset_category) = 1')
                //     //->havingRaw('count(t2.requistion_site_id) =1 ')
                //     ->where('t2.requisition_status',">",0)
                //     //->where('t2.is_group_checkout',0)
                //     //->where('is_individual_bulk_checkout',0);
                //     ->where('t2.requested_for',2);

                //     $qry = DB::table('ar_asset_sites as t1')
                //     ->leftjoin('ar_asset_requisitions as t2','t1.id','=','t2.requistion_site_id')
                //     ->join('ar_asset_categories as t3','t3.id','=','t2.asset_category')
                //     ->where('t2.requisition_status',"=",1)
                //     ->where('t2.requested_for',2);
                    
                    $qry = DB::table('ar_asset_sites as t1') 
                    ->selectRaw('t1.id');
                    $site_ids=convertStdClassObjToArray($qry->get());
                  
                    
                    $requisition_ids=[];
                    foreach($site_ids as $site)
                    {
                       
                        $site_requisitions= DB::table('ar_asset_requisitions  as t1')
                            ->where('requistion_site_id',$site['id'])
                            ->where('t1.requisition_status',"=",1)
                           // ->where('t1.requested_by',0)
                            ->where('t1.requested_for',2)
                            ->selectRaw('t1.id')
                            ->orderBy('t1.created_at','DESC');
                        $site_requisitions_results=convertStdClassObjToArray($site_requisitions->get());
                        if(count($site_requisitions_results)==1)
                        {
                            foreach($site_requisitions_results as $requisition_record)
                            {
                                $requisition_ids[]=$requisition_record['id'];
                            }
                        }
                        
                            
                        
                    }
                    $records=[];
                   
                    if(count($requisition_ids)>0){
                    $records= DB::table('ar_asset_requisitions as t1')
                    ->join('ar_asset_subcategories as t2','t2.id','=','t1.asset_category')
                    ->join('ar_asset_sites as t3','t3.id','=','t1.requistion_site_id')
                    ->join('users as t4','t2.created_by','=','t4.id')
                    ->selectRaw("t1.id as requisition_record_id,t1.request_date,t2.name as requested_asset,
                    t1.requisition_status as status,t1.created_by,t1.asset_category, t1.requistion_site_id,
                    t1.requistion_location_id,t1.requistion_department_id,
                    t3.name as site_name,CONCAT_WS(' ',decrypt(t4.first_name),decrypt(t4.last_name)) 
                     as requested_made_by,decrypt(t4.phone) as phone")
                     ->orwhereIn('t1.id',[$requisition_ids])
                     ->orwhere('is_site_single_checkout',1)
                     ->orderBy('t1.created_at','DESC')
                     ->get();
                    }else{

                        $records= DB::table('ar_asset_requisitions as t1')
                        ->join('ar_asset_subcategories as t2','t2.id','=','t1.asset_category')
                        ->join('ar_asset_sites as t3','t3.id','=','t1.requistion_site_id')
                        ->join('users as t4','t2.created_by','=','t4.id')
                        ->selectRaw("t1.id as requisition_record_id,t1.request_date,t2.name as requested_asset,
                        t1.requisition_status as status,t1.created_by,t1.asset_category, t1.requistion_site_id,
                        t1.requistion_location_id,t1.requistion_department_id,
                        t3.name as site_name,CONCAT_WS(' ',decrypt(t4.first_name),decrypt(t4.last_name)) 
                         as requested_made_by,decrypt(t4.phone) as phone")
                         ->where('is_site_single_checkout',1)->get();
                    }
                 
                    $res = array(
                        'success' => true,
                        'results' => $records
                    );
                    break;
                case "stores_requisition_approval_approved_for_site":
                    $qry = DB::table('stores_sites as t1') 
                    ->selectRaw('t1.id');
                    $site_ids=convertStdClassObjToArray($qry->get());
                  
                    
                    $requisition_ids=[];
                    foreach($site_ids as $site)
                    {
                       
                        $site_requisitions= DB::table('stores_asset_requisitions  as t1')
                            ->where('requistion_site_id',$site['id'])
                            ->where('t1.requisition_status',"=",1)
                            ->where('t1.requested_for',2)
                            ->selectRaw('t1.id')
                            ->orderBy('t1.created_at','DESC');
                        $site_requisitions_results=convertStdClassObjToArray($site_requisitions->get());
                        if(count($site_requisitions_results)==1)
                        {
                            foreach($site_requisitions_results as $requisition_record)
                            {
                                $requisition_ids[]=$requisition_record['id'];
                            }
                        }
                        
                            
                        
                    }
                    $records=[];
                   
                    if(count($requisition_ids)>0){
                    $records= DB::table('stores_asset_requisitions as t1')
                    ->join('stores_subcategories as t2','t2.id','=','t1.asset_category')
                    ->join('stores_sites as t3','t3.id','=','t1.requistion_site_id')
                    ->join('users as t4','t2.created_by','=','t4.id')
                    ->selectRaw("t1.id as requisition_record_id,t1.request_date,t2.name as requested_asset,
                    t1.requisition_status as status,t1.created_by,t1.asset_category, t1.requistion_site_id,
                    t1.requistion_location_id,t1.requistion_department_id,
                    t3.name as site_name,CONCAT_WS(' ',decrypt(t4.first_name),decrypt(t4.last_name)) 
                     as requested_made_by,decrypt(t4.phone) as phone")
                     ->orwhereIn('t1.id',[$requisition_ids])
                     ->orwhere('is_site_single_checkout',1)
                     ->orderBy('t1.created_at','DESC')
                     ->get();
                    }else{

                        $records= DB::table('stores_asset_requisitions as t1')
                        ->join('stores_subcategories as t2','t2.id','=','t1.asset_category')
                        ->join('stores_sites as t3','t3.id','=','t1.requistion_site_id')
                        ->join('users as t4','t2.created_by','=','t4.id')
                        ->selectRaw("t1.id as requisition_record_id,t1.request_date,t2.name as requested_asset,
                        t1.requisition_status as status,t1.created_by,t1.asset_category, t1.requistion_site_id,
                        t1.requistion_location_id,t1.requistion_department_id,
                        t3.name as site_name,CONCAT_WS(' ',decrypt(t4.first_name),decrypt(t4.last_name)) 
                         as requested_made_by,decrypt(t4.phone) as phone")
                         ->where('is_site_single_checkout',1)->get();
                    }
                 
                    $res = array(
                        'success' => true,
                        'results' => $records
                    );
                    break;
                case "multiple_users_on_asset":
                        $qry = DB::table('users as t1') 
                        ->selectRaw("t1.id,t1.id as requisition_user_id,
                        CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.last_name)) as requisition_user_name");
                        $users_ids=convertStdClassObjToArray($qry->get());
                        $qry = DB::table('ar_asset_subcategories as t1')
                        ->selectRaw('t1.id,t1.name');
                        $category_ids=convertStdClassObjToArray($qry->get());
                       
                        $requisition_ids=[];
                        
                        $clean_requisition_records=[];
                        $user_ids_array=[];
                        foreach($category_ids as $category)
                        {
                          
                            $qry = DB::table('ar_asset_requisitions as t2')
                            ->join('users as t3','t3.id','=','t2.requested_by')
                            ->where('asset_category',$category['id'])
                            ->where('requisition_status',1)
                            ->selectRaw("t3.id as requisition_user_id,t2.id as requistion_id,
                            CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as requested_made_by")
                            ->orderBy('t2.created_at','DESC');
                            //->where('is_individual_bulk_checkout',0);//to prevent reappeanec in indiv bulk
                            $requisition_records=convertStdClassObjToArray($qry->get());

                            
                            $user_ids_array_for_validate=[];
                            $requisition_records_ids_to_unset=[];
                            //ensure user requisition is only once for category
                            foreach($requisition_records as $key=>$record_data)
                            {
                                if(!in_array($record_data['requisition_user_id'],$user_ids_array_for_validate))
                                {
                                    $user_ids_array_for_validate[]= $record_data['requisition_user_id'];
                                }else{
                                    $requisition_records_ids_to_unset[]=$key;

                                }

                            }
                            foreach( $requisition_records_ids_to_unset as $unset_key)
                            {
                                unset( $requisition_records[$key]);

                            }
                            //end
                            //esnure unqiue users per categorry
                            $requisition_ids_array=[];
                            foreach($requisition_records as $record)
                            {
                                $requisition_ids_array[]=$record['requistion_id'];
                            }
                            //above requistions are of category 
                             //get duplicate keys
                            //$requisition_ids_array=[1,1,2,1];
                            $unique_values=array_unique($requisition_ids_array);
                            $duplicates_values=array_diff_assoc($requisition_ids_array,$unique_values);
                            $duplicates_keys=array_keys(array_intersect($requisition_ids_array,$duplicates_values));
                            foreach($duplicates_keys as $key_of_duplicate)
                            {
                                unset($key_of_duplicate,$requisition_ids_array);
                                unset($key_of_duplicate,$requisition_records);
                            }
                          

                            $category_user_requests_count=count($requisition_records);
                            if( $category_user_requests_count>1){
                             $clean_requisition_records[]=array(
                                "number_of_asset_category_requests"=> $category_user_requests_count,
                                "requisition_ids"=>$requisition_ids_array,
                                "category_id"=>$category['id'],//is sub category id
                                "asset_category_name"=>$category['name'],
                                "status"=>1,
                                "checkout_id"=>""
                            );
                         }

                            $other_requisitions = DB::table('ar_asset_requisitions as t2')
                            ->join('users as t3','t3.id','=','t2.requested_by')
                            ->join('ar_asset_subcategories as t4','t4.id','=','t2.asset_category')
                            ->where('requisition_status',">","1")
                            ->where('is_group_checkout',1)
                            ->groupBy('checkout_id')
                            
                            ->selectRaw("count(checkout_id) as number_of_asset_category_requests,t2.asset_category as 
                            category_id,t4.name as asset_category_name,t2.requisition_status as status,t2.checkout_id")
                            ->orderBy('t2.created_at','DESC')
                            ->get();
                            $other_requisitions=convertStdClassObjToArray($other_requisitions);
                            $combined_results="";
                            if(count($other_requisitions)>0)
                            {
                                $combined_results=array_merge($clean_requisition_records,$other_requisitions);
                            }else{
                                $combined_results=$clean_requisition_records;
                            }
                         

                         

                  
                            
                        
                        }
                        
                        $res = array(
                            'success' => true,
                            'message' => returnMessage($combined_results),
                            'results' => $combined_results
                        );
                        //initial query
                        // $qry = DB::table('ar_asset_categories as t1')
                        //     ->leftjoin('ar_asset_requisitions as t2','t1.id','=','t2.asset_category')
                        //     ->join('users as t3','t3.id','=','t2.requested_by')
                        //     ->selectRaw("count(t2.asset_category) as number_of_asset_category_requests, count(t2.requested_by),t1.id as category_id,t1.name as asset_category_name,CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as requisition_user_name,t3.id as requisition_user_id,
                        //     t2.requisition_status as status,t2.checkout_id,t2.is_site_bulk_checkout,t2.is_individual_bulk_checkout")
                            
                        //     //->where('requisition_status','=','1')
                        //     ->groupBy('t2.asset_category')
                        //     ->groupBy('t2.checkout_id')
                        //     //->havingRaw('count(t2.asset_category) = 0')
                        //     ->havingRaw('count(t2.requested_by) >1 ');
                            
                        //     //query above depends on concept that a user can only have one asset category at a time
                        //     $results = $qry->get();
                        //     $results=convertStdClassObjToArray($results);
                        //     //$results=decryptArray(convertStdClassObjToArray($results));
                            // foreach($results as $key=>$result)
                            // {
                            //     if($result['status']>1 && ($result['is_individual_bulk_checkout']==1 || $result['is_site_bulk_checkout']==1))
                            //     {
                            //         unset($results[$key]);
                            //     }
                            // }
                            // $res = array(
                            //     'success' => true,
                            //     'message' => returnMessage($results),
                            //     'results' => $results
                            // );
                        break;
                case "stores_multiple_users_on_asset":
                        $qry = DB::table('users as t1') 
                        ->selectRaw("t1.id,t1.id as requisition_user_id,
                        CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.last_name)) as requisition_user_name");
                        $users_ids=convertStdClassObjToArray($qry->get());
                        $qry = DB::table('stores_subcategories as t1')
                        ->selectRaw('t1.id,t1.name');
                        $category_ids=convertStdClassObjToArray($qry->get());
                       
                        $requisition_ids=[];
                        
                        $clean_requisition_records=[];
                        $user_ids_array=[];
                        foreach($category_ids as $category)
                        {
                          
                            $qry = DB::table('stores_asset_requisitions as t2')
                            ->join('users as t3','t3.id','=','t2.requested_by')
                            ->where('asset_category',$category['id'])
                            ->where('requisition_status',1)
                            ->selectRaw("t3.id as requisition_user_id,t2.id as requistion_id,
                            CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as requested_made_by")
                            ->orderBy('t2.created_at','DESC');
                            //->where('is_individual_bulk_checkout',0);//to prevent reappeanec in indiv bulk
                            $requisition_records=convertStdClassObjToArray($qry->get());

                            
                            $user_ids_array_for_validate=[];
                            $requisition_records_ids_to_unset=[];
                            //ensure user requisition is only once for category
                            foreach($requisition_records as $key=>$record_data)
                            {
                                if(!in_array($record_data['requisition_user_id'],$user_ids_array_for_validate))
                                {
                                    $user_ids_array_for_validate[]= $record_data['requisition_user_id'];
                                }else{
                                    $requisition_records_ids_to_unset[]=$key;

                                }

                            }
                            foreach( $requisition_records_ids_to_unset as $unset_key)
                            {
                                unset( $requisition_records[$key]);

                            }
                           
                            $requisition_ids_array=[];
                            foreach($requisition_records as $record)
                            {
                                $requisition_ids_array[]=$record['requistion_id'];
                            }
                           
                            $unique_values=array_unique($requisition_ids_array);
                            $duplicates_values=array_diff_assoc($requisition_ids_array,$unique_values);
                            $duplicates_keys=array_keys(array_intersect($requisition_ids_array,$duplicates_values));
                            foreach($duplicates_keys as $key_of_duplicate)
                            {
                                unset($key_of_duplicate,$requisition_ids_array);
                                unset($key_of_duplicate,$requisition_records);
                            }
                          

                            $category_user_requests_count=count($requisition_records);
                            if( $category_user_requests_count>1){
                             $clean_requisition_records[]=array(
                                "number_of_asset_category_requests"=> $category_user_requests_count,
                                "requisition_ids"=>$requisition_ids_array,
                                "category_id"=>$category['id'],//is sub category id
                                "asset_category_name"=>$category['name'],
                                "status"=>1,
                                "checkout_id"=>""
                            );
                         }

                            $other_requisitions = DB::table('stores_asset_requisitions as t2')
                            ->join('users as t3','t3.id','=','t2.requested_by')
                            ->join('stores_subcategories as t4','t4.id','=','t2.asset_category')
                            ->where('requisition_status',">","1")
                            ->where('is_group_checkout',1)
                            ->groupBy('checkout_id')
                            
                            ->selectRaw("count(checkout_id) as number_of_asset_category_requests,t2.asset_category as 
                            category_id,t4.name as asset_category_name,t2.requisition_status as status,t2.checkout_id")
                            ->orderBy('t2.created_at','DESC')
                            ->get();
                            $other_requisitions=convertStdClassObjToArray($other_requisitions);
                            $combined_results="";
                            if(count($other_requisitions)>0)
                            {
                                $combined_results=array_merge($clean_requisition_records,$other_requisitions);
                            }else{
                                $combined_results=$clean_requisition_records;
                            }
                        
                        }
                        
                        $res = array(
                            'success' => true,
                            'message' => returnMessage($combined_results),
                            'results' => $combined_results
                        );
                       
                        break;
                case "requisition_record_details":
                    $requisition_record_id=$request->input('requisition_record_id');
                    $qry = DB::table('ar_asset_checkout_details as t1')
                   // ->join('ar_asset_categories as t2', 't1.asset_category', '=', 't2.id')
                    ->join('ar_asset_inventory as t2','t1.asset_id','=','t2.id')
                    ->join('users as t3', 't1.user_id', '=', 't3.id')
                    ->selectRaw('t2.description,t2.serial_no,t2.grz_no,t2.department_id,t2.site_id,t2.location_id,
                    t1.due_date,t1.checkout_date')
                    ->where('t1.requisition_id',"=",$requisition_record_id);
                    $results = $qry->get();
                    $res = array(
                        'success' => true,
                        'data' => $results[0]
                    );
                    break;
                case "stores_requisition_record_details":
                    $requisition_record_id=$request->input('requisition_record_id');
                    $qry = DB::table('stores_asset_checkout_details as t1')
                    ->join('ar_asset_inventory as t2','t1.asset_id','=','t2.id')
                    ->join('users as t3', 't1.user_id', '=', 't3.id')
                    ->selectRaw('t2.description,t2.serial_no,t2.grz_no,t2.department_id,t2.site_id,t2.location_id,
                    t1.due_date,t1.checkout_date')
                    ->where('t1.requisition_id',"=",$requisition_record_id);
                    $results = $qry->get();
                    $res = array(
                        'success' => true,
                        'data' => $results[0]
                    );
                    break;
                case "requisition_record_details_for_site":
                    $requisition_record_id=$request->input('requisition_record_id');
                    $qry = DB::table('ar_asset_checkout_details as t1')
                   // ->join('ar_asset_categories as t2', 't1.asset_category', '=', 't2.id')
                    ->join('ar_asset_inventory as t2','t1.asset_id','=','t2.id')
                   // ->join('users as t3', 't1.user_id', '=', 't3.id')
                    ->selectRaw('t2.description,t2.serial_no,t2.grz_no,t2.department_id,t2.site_id,t2.location_id,
                    t1.assignment_category as checkout_type,t1.checkout_date,t1.due_date,t1.no_due_date,t1.checkout_site_id,
                    t1.checkout_location_id,t1.checkout_department_id,t1.site_asset_individual_responsible')
                    ->where('t1.requisition_id',"=",$requisition_record_id);
                    $results = $qry->get();
                   
                    $res = array(
                        'success' => true,
                        'results' => $results[0]
                    );
                    break;
                
                case "my_register_assets":
                    try {
                        $qry = DB::table('ar_asset_checkout_details as t1')
                            ->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
                            ->join('users as t3', 't1.user_id', '=', 't3.id')
                            ->join('ar_asset_sites as t4', 't1.checkout_site_id', '=', 't4.id')
                            ->join('ar_asset_locations as t5', 't1.checkout_location_id', '=', 't5.id')
                            ->join('departments as t6', 't1.checkout_department_id', '=', 't6.id')
                            ->leftJoin('ar_asset_checkin_details as t7', 't1.id', '=', 't7.checkout_id')
                            ->selectRaw("t1.id as checkout_id,t1.*,t2.description,t2.serial_no,t2.grz_no,t4.name as site_name,t5.name as location_name,
                                    t2.site_id,t2.location_id,t2.department_id,CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as assigned_user,t6.name as department_name,
                                    t7.return_date,t7.checkin_site_id,t7.checkin_location_id,t7.checkin_department_id");
                        $results = $qry->get();
                        $res = array(
                            'success' => true,
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
                    break;
                case "funding_amount_validation":
                    try {
                        $qry = DB::table('ar_funding_details as t1')
                            //->whereRaw("t1.end_date>NOW()")
                            ->selectRaw("(t1.funding_amount-(Select sum(amount) from ar_asset_funding_linkage where funding_id=t1.id)) as available_amount,
                               @available_amount := (t1.funding_amount-(Select COALESCE(sum(amount),0) from ar_asset_funding_linkage where funding_id=t1.id)) as available_amount,t1.end_date");
                        $results = $qry->get();
                        $res = array(
                            'success' => true,
                            'funding_validation_details' => $results[0]
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
                    break;
               case "stores_funding_amount_validation":
                    try {
                        $qry = DB::table('stores_funding_details as t1')
                            //->whereRaw("t1.end_date>NOW()")
                            ->selectRaw("(t1.funding_amount-(Select sum(amount) from stores_funding_linkage where funding_id=t1.id)) as available_amount,
                               @available_amount := (t1.funding_amount-(Select COALESCE(sum(amount),0) from ar_asset_funding_linkage where funding_id=t1.id)) as available_amount,t1.end_date");
                        $results = $qry->get();
                        $res = array(
                            'success' => true,
                            'funding_validation_details' => $results[0]
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
                    break;
                case "funding_has_asset_linkage":
                    $asset_id=$request->query('asset_id');
                    $funding_id=$request->query('funding_id');
                    
                    try{
                        $count = DB::table('ar_asset_funding_linkage as t1')
                        ->where('funding_id','=',$funding_id)
                        ->where('asset_id','=',$asset_id)
                        ->count();
                        $res = array(
                            'success' => true,
                            'asset_count' => $count
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
                    break;
               case "stores_funding_has_asset_linkage":
                    $asset_id=$request->query('asset_id');
                    $funding_id=$request->query('funding_id');
                    
                    try{
                        $count = DB::table('stores_funding_linkage as t1')
                        ->where('funding_id','=',$funding_id)
                        ->where('asset_id','=',$asset_id)
                        ->count();
                        $res = array(
                            'success' => true,
                            'asset_count' => $count
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
                    break;
                case "check_user_has_reservation_item_category":

                    $asset_category_id=$request->query('asset_category_id');
                    $reservation_user_id=$request->query('user_id');
                    try{
                        $count = DB::table('ar_asset_reservations as t1')
                        ->join('ar_asset_inventory as t2','t1.asset_id','=','t2.id')
                        ->where('t1.user_id','=',$reservation_user_id)
                        ->where('t2.sub_category_id','=',$asset_category_id)
                        ->whereIn('checkin_status',[0,1])
                        ->count();
                        $res = array(
                            'success' => true,
                            'asset_count' => $count
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
                    break;
                case "stores_check_user_has_reservation_item_category":

                    $asset_category_id=$request->query('asset_category_id');
                    $reservation_user_id=$request->query('user_id');
                    try{
                        $count = DB::table('stores_asset_reservations as t1')
                        ->join('ar_asset_inventory as t2','t1.asset_id','=','t2.id')
                        ->where('t1.user_id','=',$reservation_user_id)
                        ->where('t2.sub_category_id','=',$asset_category_id)
                        ->whereIn('checkin_status',[0,1])
                        ->count();
                        $res = array(
                            'success' => true,
                            'asset_count' => $count
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
                    break;
                case "insurance_expiry_validation":
                    $policy_id=$request->policy_id;
                    try {
                        $qry = DB::table('ar_insurance_policies as t1')
                            ->selectRaw("t1.end_date")
                            ->where('id','=',$policy_id);
                        $results = $qry->get();
                        $count=DB::table('ar_asset_insurance_linkage')->where('policy_id',$policy_id)->count();
                        $res = array(
                            'success' => true,
                            'insurance_validation_details' => $results[0],
                            "results"=>["asset_count"=>$count],
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
                
                    break;
                case "stores_insurance_expiry_validation":
                    $policy_id=$request->policy_id;
                    try {
                        $qry = DB::table('stores_insurance_policies as t1')
                            ->selectRaw("t1.end_date")
                            ->where('id','=',$policy_id);
                        $results = $qry->get();
                        $count=DB::table('stores_insurance_linkage')->where('policy_id',$policy_id)->count();
                        $res = array(
                            'success' => true,
                            'insurance_validation_details' => $results[0],
                            "results"=>["asset_count"=>$count],
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
                
                    break;
                case "site_per_asset":
                    //initial query
                    // $qry = DB::table('ar_asset_sites as t1')
                    //     ->leftjoin('ar_asset_requisitions as t2','t1.id','=','t2.requistion_site_id')
                    //     ->selectRaw("count(t2.requistion_site_id) as number_of_asset_requests,t1.name as requisition_site_name,t1.id as requisition_site_id,
                    //     t2.requisition_status as status,checkout_id")
                        
                    //     ->orWhere('requisition_status','=','1')
                    //     ->groupBy('t2.requistion_site_id')  
                    //     ->groupby('t2.checkout_id')  
                    //     ->where('requested_for',2) 
                    //     ->orWhere('is_site_bulk_checkout',1)
                       
                    //     ->havingRaw('count(t2.requistion_site_id) > 1');
                       
                    //     $results = $qry->get()->toArray();
                    //     $res = array(
                    //         'success' => true,
                    //         'message' => returnMessage($results),
                    //         'results' => $results
                    //     );

                     


                        //sites
                        $qry = DB::table('ar_asset_sites as t1') 
                        ->selectRaw('t1.id,t1.name as requisition_site_name');
                        $site_ids=convertStdClassObjToArray($qry->get());
                      
                        
                       
                        $requisition_records=[];
                        foreach($site_ids as $site)
                        {
                           
                            $site_requisitions= DB::table('ar_asset_requisitions  as t1')
                                ->where('requistion_site_id',$site['id'])
                                ->where('t1.requisition_status',"=",1)
                               // ->where('t1.requested_by',0)
                                ->where('t1.requested_for',2)
                                ->selectRaw('t1.id');
                            $site_requisitions_results=convertStdClassObjToArray($site_requisitions->get());
                          

                            if(count($site_requisitions_results)>1)
                            {
                                $sites_requisition_ids=[];
                                foreach($site_requisitions_results as $result)
                                {
                                    $sites_requisition_ids[]=$result['id'];
                                }      
                               
                                $requisition_records[]=array(
                                    "number_of_asset_requests"=>count($site_requisitions_results),
                                    "requisition_site_name"=>$site['requisition_site_name'],
                                    "status"=>1,
                                    "checkout_id"=>"",
                                    "requisition_ids"=>$sites_requisition_ids,
                                    "requisition_site_id"=>$site['id']

                                );
                                // foreach($site_requisitions_results as $requisition_record)
                                // {
                                //     $requisition_ids[]=$requisition_record['id'];
                                // }
                            }
                            
                                
                            
                        }
                     
                        $combined_records=[];
                      
                        $results= DB::table('ar_asset_requisitions as t1')
                        ->join('ar_asset_subcategories as t2','t2.id','=','t1.asset_category')
                        ->join('ar_asset_sites as t3','t3.id','=','t1.requistion_site_id')
                        ->join('users as t4','t2.created_by','=','t4.id') 
                        ->selectRaw("count(t1.checkout_id) as  number_of_asset_requests,t3.name as requisition_site_name,
                        t1.requisition_status as status,t1.checkout_id")
                      
                        ->groupby('checkout_id')
                        ->where('is_site_bulk_checkout',1)->get();
                        //number_ofsite_bulk_checkout_form_details_asset_requests
                        $records=convertStdClassObjToArray($results);

                        $combined_records=array_merge(  $requisition_records, $records);
                     
                     

                        $res = array(
                            'success' => true,
                            'results' =>$combined_records
                        );
                     break;
                    case "stores_site_per_asset":
                       
                            //sites
                            $qry = DB::table('stores_sites as t1') 
                            ->selectRaw('t1.id,t1.name as requisition_site_name');
                            $site_ids=convertStdClassObjToArray($qry->get());
                          
                            
                           
                            $requisition_records=[];
                            foreach($site_ids as $site)
                            {
                               
                                $site_requisitions= DB::table('stores_asset_requisitions  as t1')
                                    ->where('requistion_site_id',$site['id'])
                                    ->where('t1.requisition_status',"=",1)
                                   // ->where('t1.requested_by',0)
                                    ->where('t1.requested_for',2)
                                    ->selectRaw('t1.id');
                                $site_requisitions_results=convertStdClassObjToArray($site_requisitions->get());
                              
    
                                if(count($site_requisitions_results)>1)
                                {
                                    $sites_requisition_ids=[];
                                    foreach($site_requisitions_results as $result)
                                    {
                                        $sites_requisition_ids[]=$result['id'];
                                    }      
                                   
                                    $requisition_records[]=array(
                                        "number_of_asset_requests"=>count($site_requisitions_results),
                                        "requisition_site_name"=>$site['requisition_site_name'],
                                        "status"=>1,
                                        "checkout_id"=>"",
                                        "requisition_ids"=>$sites_requisition_ids,
                                        "requisition_site_id"=>$site['id']
    
                                    );
                                   
                                }
                                
                                    
                                
                            }
                         
                            $combined_records=[];
                          
                            $results= DB::table('stores_asset_requisitions as t1')
                            ->join('stores_subcategories as t2','t2.id','=','t1.asset_category')
                            ->join('stores_sites as t3','t3.id','=','t1.requistion_site_id')
                            ->join('users as t4','t2.created_by','=','t4.id') 
                            ->selectRaw("count(t1.checkout_id) as  number_of_asset_requests,t3.name as requisition_site_name,
                            t1.requisition_status as status,t1.checkout_id")
                          
                            ->groupby('checkout_id')
                            ->where('is_site_bulk_checkout',1)->get();
                            $records=convertStdClassObjToArray($results);
    
                            $combined_records=array_merge(  $requisition_records, $records);
                            $res = array(
                                'success' => true,
                                'results' =>$combined_records
                            );
                        break;
                case "user_per_asset":
                    //initial query
                    // $qry = DB::table('users as t1')
                    //     ->leftjoin('ar_asset_requisitions as t2','t1.id','=','t2.requested_by')
                    //     ->selectRaw("count(t2.requested_by) as number_of_asset_requests,t1.id,CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.last_name)) as requisition_user_name,t1.id as requisition_user_id,
                    //     t2.requisition_status as status,checkout_id")
                        
                    //     ->orWhere('requisition_status','=','1')
                    //     ->groupBy('t2.requested_by')  
                    //     ->groupby('t2.checkout_id')   
                    //     ->orWhere('is_individual_bulk_checkout',1)
                    //     ->havingRaw('count(t2.requested_by) > 1')->get();

                   
                        
                        $qry = DB::table('users as t1') 
                        ->selectRaw("t1.id,t1.id as requisition_user_id,
                        CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.last_name)) as requisition_user_name");
                        $users_ids=convertStdClassObjToArray($qry->get());
                      
                        $requisition_record=[];
                        $requisition_ids=[];
                        foreach($users_ids as $this_user)
                        {
                      
                            $user_requisitions= DB::table('ar_asset_requisitions  as t1')
                                ->where('requested_by',$this_user['id'])
                                ->where('t1.requisition_status',"=",1)
                                ->where('t1.requested_for',1)
                                ->selectRaw('t1.id,requistion_site_id,requistion_location_id,requistion_department_id');

                            $user_requisitions_results=convertStdClassObjToArray($user_requisitions->get());
                            if(count($user_requisitions_results)>1)
                            {
                                $requisition_record[]=array(
                                    "number_of_asset_requests"=>count($user_requisitions_results),
                                    "requisition_user_name"=>$this_user['requisition_user_name'],
                                    "status"=>1,
                                    "requisition_user_id"=>$this_user['requisition_user_id'],
                                    "checkout_id"=>"",
                                    "requistion_site_id"=> $user_requisitions_results[0]['requistion_site_id'],
                                    "requistion_location_id"=>$user_requisitions_results[0]['requistion_location_id'],
                                    "requistion_department_id"=> $user_requisitions_results[0]['requistion_department_id'],
                                    
                                );
                                
                            }
                            
                        
                                
                            
                        }

                        foreach($users_ids as $this_user)
                        {
                      
                            $user_requisitions= DB::table('ar_asset_requisitions  as t1')
                                ->where('requested_by',$this_user['id'])
                                ->where('t1.requisition_status',">",1)
                                ->where('t1.is_individual_bulk_checkout',1)
                                ->groupby('t1.checkout_id')   
                                ->selectRaw('t1.id,count(checkout_id) as number_of_asset_requests,t1.checkout_id,requisition_status,requistion_site_id,
                                requistion_location_id,requistion_department_id');

                            $user_requisitions_results=convertStdClassObjToArray($user_requisitions->get());
                            foreach( $user_requisitions_results as $req_record)
                            {
                                $requisition_record[]=array(
                                    "number_of_asset_requests"=> $req_record['number_of_asset_requests'],
                                    "requisition_user_name"=>$this_user['requisition_user_name'],
                                    "status"=>$req_record['requisition_status'],
                                    "requisition_user_id"=>$this_user['requisition_user_id'],
                                    "checkout_id"=>$req_record['checkout_id'],
                                    "requistion_site_id"=>$req_record['requistion_site_id'],
                                    "requistion_location_id"=>$req_record['requistion_location_id'],
                                    "requistion_department_id"=>$req_record['requistion_department_id'],
                                    
                                );
                            }
                         
                            
                            
                        
                                
                            
                        }
                     

                        $res = array(
                            'success' => true,
                            'results' => $requisition_record
                        );
                    break;
                case "stores_user_per_asset":
                        $qry = DB::table('users as t1') 
                        ->selectRaw("t1.id,t1.id as requisition_user_id,
                        CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.last_name)) as requisition_user_name");
                        $users_ids=convertStdClassObjToArray($qry->get());
                      
                        $requisition_record=[];
                        $requisition_ids=[];
                        foreach($users_ids as $this_user)
                        {
                      
                            $user_requisitions= DB::table('stores_asset_requisitions  as t1')
                                ->where('requested_by',$this_user['id'])
                                ->where('t1.requisition_status',"=",1)
                                ->where('t1.requested_for',1)
                                ->selectRaw('t1.id,requistion_site_id,requistion_location_id,requistion_department_id');

                            $user_requisitions_results=convertStdClassObjToArray($user_requisitions->get());
                            if(count($user_requisitions_results)>1)
                            {
                                $requisition_record[]=array(
                                    "number_of_asset_requests"=>count($user_requisitions_results),
                                    "requisition_user_name"=>$this_user['requisition_user_name'],
                                    "status"=>1,
                                    "requisition_user_id"=>$this_user['requisition_user_id'],
                                    "checkout_id"=>"",
                                    "requistion_site_id"=> $user_requisitions_results[0]['requistion_site_id'],
                                    "requistion_location_id"=>$user_requisitions_results[0]['requistion_location_id'],
                                    "requistion_department_id"=> $user_requisitions_results[0]['requistion_department_id'],   
                                );  
                            }
                        }
                        foreach($users_ids as $this_user)
                        {
                            $user_requisitions= DB::table('stores_asset_requisitions  as t1')
                                ->where('requested_by',$this_user['id'])
                                ->where('t1.requisition_status',">",1)
                                ->where('t1.is_individual_bulk_checkout',1)
                                ->groupby('t1.checkout_id')   
                                ->selectRaw('t1.id,count(checkout_id) as number_of_asset_requests,t1.checkout_id,requisition_status,requistion_site_id,
                                requistion_location_id,requistion_department_id');
                            $user_requisitions_results=convertStdClassObjToArray($user_requisitions->get());
                            foreach( $user_requisitions_results as $req_record)
                            {
                                $requisition_record[]=array(
                                    "number_of_asset_requests"=> $req_record['number_of_asset_requests'],
                                    "requisition_user_name"=>$this_user['requisition_user_name'],
                                    "status"=>$req_record['requisition_status'],
                                    "requisition_user_id"=>$this_user['requisition_user_id'],
                                    "checkout_id"=>$req_record['checkout_id'],
                                    "requistion_site_id"=>$req_record['requistion_site_id'],
                                    "requistion_location_id"=>$req_record['requistion_location_id'],
                                    "requistion_department_id"=>$req_record['requistion_department_id'],
                                    
                                );
                            }
                        }
                        $res = array(
                            'success' => true,
                            'results' => $requisition_record
                        );
                    break;
                case "single_site_checkin_list":
                    $qry = DB::table('ar_asset_checkout_details as t1')
                    ->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
                    //->join('users as t3', 't1.user_id', '=', 't3.id')
                    ->join('ar_asset_sites as t4', 't1.checkout_site_id', '=', 't4.id')
                    
                    ->leftJoin('ar_asset_checkin_details as t7', 't1.id', '=', 't7.checkout_id')
                    ->leftjoin('ar_asset_reservations as t8','t8.checkout_id','=','t1.id')
                    ->selectRaw("t1.id as checkout_id,t1.*,t2.description,t2.serial_no,t2.grz_no,t4.name as assigned_site,
                            t2.site_id,t2.location_id,t2.department_id,t1.checkout_location_id,t1.checkout_department_id,
                            t7.return_date,t7.checkin_site_id,t7.checkin_location_id,t7.checkin_department_id,t8.id as reservation_record_id")
                            
                    ->where('t1.assignment_category',2)
                   ->where('t1.is_single_site_checkout',1)
                   ->orderBy('t1.created_at','DESC');
                            $results = $qry->get()->toArray();
                            $res = array(
                                'success' => true,
                                'message' => returnMessage($results),
                                'results' => $results
                            );
                    break;
                 case "stores_single_site_checkin_list":
                    $qry = DB::table('stores_asset_checkout_details as t1')
                    ->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
                    ->join('stores_sites as t4', 't1.checkout_site_id', '=', 't4.id')
                    ->leftJoin('stores_asset_checkin_details as t7', 't1.id', '=', 't7.checkout_id')
                    ->leftjoin('stores_asset_reservations as t8','t8.checkout_id','=','t1.id')
                    ->selectRaw("t1.id as checkout_id,t1.*,t2.description,t2.serial_no,t2.grz_no,t4.name as assigned_site,
                            t2.site_id,t2.location_id,t2.department_id,t1.checkout_location_id,t1.checkout_department_id,
                            t7.return_date,t7.checkin_site_id,t7.checkin_location_id,t7.checkin_department_id,t8.id as reservation_record_id")    
                    ->where('t1.assignment_category',2)
                   ->where('t1.is_single_site_checkout',1)
                   ->orderBy('t1.created_at','DESC');
                            $results = $qry->get()->toArray();
                            $res = array(
                                'success' => true,
                                'message' => returnMessage($results),
                                'results' => $results
                            );
                    break;
                case "group_bulk_asset_checkin_list":
                    $qry = DB::table('ar_asset_checkout_details as t1')
                    ->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
                    //->join('ar_asset_requisitions as t3','t2.category_id','=','t3.asset_category')
                    //->join('users as t3', 't1.user_id', '=', 't3.id')
                    ->join('ar_asset_sites as t4', 't1.checkout_site_id', '=', 't4.id')
                    ->leftjoin('ar_asset_locations as t5', 't1.checkout_location_id', '=', 't5.id')
                    ->leftjoin('departments as t6', 't1.checkout_department_id', '=', 't6.id')
                    ->leftJoin('ar_asset_checkin_details as t7', 't1.id', '=', 't7.checkout_id')
                    ->selectRaw("t1.id as checkout_id,t1.*,t2.description,t2.serial_no,t2.grz_no,t4.name as site_name,t5.name as location_name,
                            t2.site_id,t2.location_id,t2.department_id,t6.name as department_name,
                            t7.return_date,t7.checkin_site_id,t7.checkin_location_id,t7.checkin_department_id,t2.sub_category_id as category_id")
                            //category id has been selected as sub_category_id mismatch due to late fix 
                    ->where('is_group_checkout',1)
                    ->orderBy('t1.created_at','DESC');;
                    $bulk_list = convertStdClassObjToArray($qry->get());
                    $qry = DB::table('users  as t3')
                        ->selectRaw("id,CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as assigned_user");
                    $users = convertStdClassObjToArray($qry->get());
                    foreach($bulk_list as $key=>$list_item)
                    { 
                     $users_id_array=$this->returnArrayFromStringArray($list_item['user_id']);
                        $user_details=[];
                       foreach ($users_id_array as $key_count=>$user_id_this)
                        { 
                            $result_asset= $this->_search_array_by_value($users,'id',$user_id_this)[0];
                            $user_details[]=$result_asset['assigned_user'];
                        }
                        $list_item['assigned_users']=implode(",",$user_details);
                        $user_details=[];
                        $bulk_list[$key]=$list_item;
                    }
                  
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($bulk_list),
                        'results' => $bulk_list
                    );

                    break;
                case "stores_group_bulk_asset_checkin_list":
                    $qry = DB::table('stores_asset_checkout_details as t1')
                    ->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
                    ->join('stores_sites as t4', 't1.checkout_site_id', '=', 't4.id')
                    ->leftjoin('stores_locations as t5', 't1.checkout_location_id', '=', 't5.id')
                    ->leftjoin('stores_departments as t6', 't1.checkout_department_id', '=', 't6.id')
                    ->leftJoin('stores_asset_checkin_details as t7', 't1.id', '=', 't7.checkout_id')
                    ->selectRaw("t1.id as checkout_id,t1.*,t2.description,t2.serial_no,t2.grz_no,t4.name as site_name,t5.name as location_name,
                            t2.site_id,t2.location_id,t2.department_id,t6.name as department_name,
                            t7.return_date,t7.checkin_site_id,t7.checkin_location_id,t7.checkin_department_id,t2.sub_category_id as category_id")
                            //category id has been selected as sub_category_id mismatch due to late fix 
                    ->where('is_group_checkout',1)
                    ->orderBy('t1.created_at','DESC');;
                    $bulk_list = convertStdClassObjToArray($qry->get());
                    $qry = DB::table('users  as t3')
                        ->selectRaw("id,CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as assigned_user");
                    $users = convertStdClassObjToArray($qry->get());
                    foreach($bulk_list as $key=>$list_item)
                    { 
                     $users_id_array=$this->returnArrayFromStringArray($list_item['user_id']);
                        $user_details=[];
                       foreach ($users_id_array as $key_count=>$user_id_this)
                        { 
                            $result_asset= $this->_search_array_by_value($users,'id',$user_id_this)[0];
                            $user_details[]=$result_asset['assigned_user'];
                        }
                        $list_item['assigned_users']=implode(",",$user_details);
                        $user_details=[];
                        $bulk_list[$key]=$list_item;
                    }
                  
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($bulk_list),
                        'results' => $bulk_list
                    );

                    break;
                case "site_bulk_asset_checkin_list":
                    $qry = DB::table('ar_asset_checkout_details as t1')
                    //->join('users as t3', 't1.user_id', '=', 't3.id')
                    ->join('ar_asset_sites as t4', 't1.checkout_site_id', '=', 't4.id')
                    ->leftjoin('ar_asset_locations as t5', 't1.checkout_location_id', '=', 't5.id')
                    ->leftjoin('departments as t6', 't1.checkout_department_id', '=', 't6.id')
                    ->leftJoin('ar_asset_checkin_details as t7', 't1.id', '=', 't7.checkout_id')
                    ->where('is_site_bulk_checkout',1)
                    ->selectRaw("t1.id as checkout_id,t1.*,t4.name as assigned_site,t5.name as location_name,t6.name as department_name,
                  
                   
                    t4.id as requisition_site_id,t7.return_date")
                    ->orderBy('t1.created_at','DESC');
                    $bulk_list = convertStdClassObjToArray($qry->get());
                    $qry = DB::table('ar_asset_inventory as t2');
                    $asset_register = convertStdClassObjToArray($qry->get());
                    foreach($bulk_list as $key=>$list_item)
                     { 
                       // $asset_ids=substr($list_item['asset_id'], 0, -1);
                        //$asset_ids_array=explode(',' ,substr($asset_ids,1));
                        $asset_ids_array=$this->returnArrayFromStringArray($list_item['asset_id']);
                        $asset_descriptions_serial_no_grz_no=[];
                        foreach($asset_ids_array as $key_count=>$asset_id)
                         { 
                            $result_asset= $this->_search_array_by_value( $asset_register,'id',$asset_id)[0];
                            $asset_descriptions_serial_no_grz_no[]=$result_asset['description']." ".$result_asset['serial_no'] ." ".$result_asset['grz_no'];
                            
                           
                            if((count($asset_ids_array)-1)==$key_count){
                            $list_item['description']=implode(",",$asset_descriptions_serial_no_grz_no);
                            $list_item["site_id"]=$result_asset['site_id'];
                            $list_item["location_id"]=$result_asset['location_id'];
                            $list_item["department_id"]=$result_asset['department_id'];
                           }
                        
                        
                        //or above if problem what is below
                         }
                         $bulk_list[$key]=$list_item;
                     
                    }
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($bulk_list),
                        'results' => $bulk_list
                    );
                    break;
                case "stores_site_bulk_asset_checkin_list":
                    $qry = DB::table('stores_asset_checkout_details as t1')
                    //->join('users as t3', 't1.user_id', '=', 't3.id')
                    ->join('stores_sites as t4', 't1.checkout_site_id', '=', 't4.id')
                    ->leftjoin('stores_locations as t5', 't1.checkout_location_id', '=', 't5.id')
                    ->leftjoin('stores_departments as t6', 't1.checkout_department_id', '=', 't6.id')
                    ->leftJoin('stores_asset_checkin_details as t7', 't1.id', '=', 't7.checkout_id')
                    ->where('is_site_bulk_checkout',1)
                    ->selectRaw("t1.id as checkout_id,t1.*,t4.name as assigned_site,t5.name as location_name,t6.name as department_name,
                  
                   
                    t4.id as requisition_site_id,t7.return_date")
                    ->orderBy('t1.created_at','DESC');
                    $bulk_list = convertStdClassObjToArray($qry->get());
                    $qry = DB::table('ar_asset_inventory as t2');
                    $asset_register = convertStdClassObjToArray($qry->get());
                    foreach($bulk_list as $key=>$list_item)
                     { 
                       // $asset_ids=substr($list_item['asset_id'], 0, -1);
                        //$asset_ids_array=explode(',' ,substr($asset_ids,1));
                        $asset_ids_array=$this->returnArrayFromStringArray($list_item['asset_id']);
                        $asset_descriptions_serial_no_grz_no=[];
                        foreach($asset_ids_array as $key_count=>$asset_id)
                         { 
                            $result_asset= $this->_search_array_by_value( $asset_register,'id',$asset_id)[0];
                            $asset_descriptions_serial_no_grz_no[]=$result_asset['description']." ".$result_asset['serial_no'] ." ".$result_asset['grz_no'];
                            
                           
                            if((count($asset_ids_array)-1)==$key_count){
                            $list_item['description']=implode(",",$asset_descriptions_serial_no_grz_no);
                            $list_item["site_id"]=$result_asset['site_id'];
                            $list_item["location_id"]=$result_asset['location_id'];
                            $list_item["department_id"]=$result_asset['department_id'];
                           }
                        
                        
                        //or above if problem what is below
                         }
                         $bulk_list[$key]=$list_item;
                     
                    }
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($bulk_list),
                        'results' => $bulk_list
                    );
                    break;
                case "individuals_bulk_asset_checkin_list":
                    $qry = DB::table('ar_asset_checkout_details as t1')
                     ->join('users as t3', 't1.user_id', '=', 't3.id')
                     ->join('ar_asset_sites as t4', 't1.checkout_site_id', '=', 't4.id')
                     ->leftjoin('ar_asset_locations as t5', 't1.checkout_location_id', '=', 't5.id')
                     ->leftjoin('departments as t6', 't1.checkout_department_id', '=', 't6.id')
                     ->leftJoin('ar_asset_checkin_details as t7', 't1.id', '=', 't7.checkout_id')
                     ->where('is_individual_bulk_checkout',1)
                     ->selectRaw("t1.id as checkout_id,t1.*,t4.name as site_name,t5.name as location_name,t6.name as department_name,
                   
                     CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as assigned_user,
                     t3.id as requisition_user_id,t7.return_date")
                     ->orderBy('t1.created_at','DESC');
                     $bulk_list = convertStdClassObjToArray($qry->get());
                     $qry = DB::table('ar_asset_inventory as t2');
                     $asset_register = convertStdClassObjToArray($qry->get());
                    
                     foreach($bulk_list as $key=>$list_item)
                     { 
                        $asset_ids=substr($list_item['asset_id'], 0, -1);
                        $asset_ids_array=explode(',' ,substr($asset_ids,1));
                        $asset_descriptions_serial_no_grz_no=[];
                        foreach($asset_ids_array as $key_count=>$asset_id)
                         { 
                            $result_asset= $this->_search_array_by_value( $asset_register,'id',$asset_id)[0];
                            $asset_descriptions_serial_no_grz_no[]=$result_asset['description']." ".$result_asset['serial_no'] ." ".$result_asset['grz_no'];
                            
                           
                            if((count($asset_ids_array)-1)==$key_count){
                            $list_item['description']=implode(",",$asset_descriptions_serial_no_grz_no);
                            $list_item["site_id"]=$result_asset['site_id'];
                            $list_item["location_id"]=$result_asset['location_id'];
                            $list_item["department_id"]=$result_asset['department_id'];
                           }
                        
                        
                        //or above if problem what is below
                         }
                         $bulk_list[$key]=$list_item;
                     
                    }
                  
                     $res = array(
                         'success' => true,
                         'message' => returnMessage($bulk_list),
                         'results' => $bulk_list
                     );


                     
          
               
                    break;
                case "stores_individuals_bulk_asset_checkin_list":
                    $qry = DB::table('stores_asset_checkout_details as t1')
                     ->join('users as t3', 't1.user_id', '=', 't3.id')
                     ->join('stores_sites as t4', 't1.checkout_site_id', '=', 't4.id')
                     ->leftjoin('stores_locations as t5', 't1.checkout_location_id', '=', 't5.id')
                     ->leftjoin('stores_departments as t6', 't1.checkout_department_id', '=', 't6.id')
                     ->leftJoin('stores_asset_checkin_details as t7', 't1.id', '=', 't7.checkout_id')
                     ->where('is_individual_bulk_checkout',1)
                     ->selectRaw("t1.id as checkout_id,t1.*,t4.name as site_name,t5.name as location_name,t6.name as department_name,
                   
                     CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as assigned_user,
                     t3.id as requisition_user_id,t7.return_date")
                     ->orderBy('t1.created_at','DESC');
                     $bulk_list = convertStdClassObjToArray($qry->get());
                     $qry = DB::table('ar_asset_inventory as t2');
                     $asset_register = convertStdClassObjToArray($qry->get());
                    
                     foreach($bulk_list as $key=>$list_item)
                     { 
                        $asset_ids=substr($list_item['asset_id'], 0, -1);
                        $asset_ids_array=explode(',' ,substr($asset_ids,1));
                        $asset_descriptions_serial_no_grz_no=[];
                        foreach($asset_ids_array as $key_count=>$asset_id)
                         { 
                            $result_asset= $this->_search_array_by_value( $asset_register,'id',$asset_id)[0];
                            $asset_descriptions_serial_no_grz_no[]=$result_asset['description']." ".$result_asset['serial_no'] ." ".$result_asset['grz_no'];
                            
                           
                            if((count($asset_ids_array)-1)==$key_count){
                            $list_item['description']=implode(",",$asset_descriptions_serial_no_grz_no);
                            $list_item["site_id"]=$result_asset['site_id'];
                            $list_item["location_id"]=$result_asset['location_id'];
                            $list_item["department_id"]=$result_asset['department_id'];
                           }
                        
                        
                        //or above if problem what is below
                         }
                         $bulk_list[$key]=$list_item;
                     
                    }
                  
                     $res = array(
                         'success' => true,
                         'message' => returnMessage($bulk_list),
                         'results' => $bulk_list
                     );


                     
          
               
                    break;
                case "multiple_users_checkout_form_details":
                    $qry= DB::table('ar_asset_checkout_details as t1')
                        ->selectRaw('requisition_id as requisition_ids,asset_id as assets_for_individuals,
                        user_id as users_for_asset,checkout_date,no_due_date,due_date,send_email,checkout_site_id,
                        checkout_location_id,checkout_department_id')
                        ->where('id',$request->query('checkout_id'));
                       
                        $results = $qry->get();
                       
                        $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                        );
                    break;
                 case "stores_multiple_users_checkout_form_details":
                    $qry= DB::table('stores_asset_checkout_details as t1')
                        ->selectRaw('requisition_id as requisition_ids,asset_id as assets_for_individuals,
                        user_id as users_for_asset,checkout_date,no_due_date,due_date,send_email,checkout_site_id,
                        checkout_location_id,checkout_department_id')
                        ->where('id',$request->query('checkout_id'));
                       
                        $results = $qry->get();
                       
                        $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                        );
                    break;
                case "site_bulk_checkout_form_details":
                    
                    $qry= DB::table('ar_asset_checkout_details as t1')
                    ->selectRaw('requisition_id as requisition_ids,asset_id as assets_for_site,
                    checkout_date,no_due_date,due_date,user_email,checkout_site_id,
                    checkout_location_id,checkout_department_id,lost_items')
                    ->where('id',$request->query('checkout_id'));
                    $assets_lost_data="";
                    $results = $qry->get()->toArray();
            
                    if($results[0]->lost_items!=null && $results[0]->lost_items!="")
                    {
                        $asset_ids=$this->returnArrayFromStringArray($results[0]->lost_items);
                      
                        foreach($asset_ids as $asset_id)
                        {
                           $asset_result= DB::table('ar_asset_inventory as t1')->where("id",$asset_id)->selectRaw(
                                "CONCAT_WS('-',t1.description,t1.grz_no,t1.serial_no) as desc_grz_no_serial_no"
                            )->get();
                            if($assets_lost_data=="")
                            {
                                $assets_lost_data=$asset_result[0]->desc_grz_no_serial_no;
                            }else{
                                $assets_lost_data."<br/>".$asset_result[0]->desc_grz_no_serial_no;
                            }

                        }
                    }
                   if($assets_lost_data!="")
                   {
                       $results[0]->items_lost=$assets_lost_data;
                   }
                 
                        $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                        );
                    break;
                case "stores_site_bulk_checkout_form_details":
                    
                    $qry= DB::table('stores_asset_checkout_details as t1')
                    ->selectRaw('requisition_id as requisition_ids,asset_id as assets_for_site,
                    checkout_date,no_due_date,due_date,user_email,checkout_site_id,
                    checkout_location_id,checkout_department_id,lost_items')
                    ->where('id',$request->query('checkout_id'));
                    $assets_lost_data="";
                    $results = $qry->get()->toArray();
            
                    if($results[0]->lost_items!=null && $results[0]->lost_items!="")
                    {
                        $asset_ids=$this->returnArrayFromStringArray($results[0]->lost_items);
                      
                        foreach($asset_ids as $asset_id)
                        {
                           $asset_result= DB::table('ar_asset_inventory as t1')->where("id",$asset_id)->selectRaw(
                                "CONCAT_WS('-',t1.description,t1.grz_no,t1.serial_no) as desc_grz_no_serial_no"
                            )->get();
                            if($assets_lost_data=="")
                            {
                                $assets_lost_data=$asset_result[0]->desc_grz_no_serial_no;
                            }else{
                                $assets_lost_data."<br/>".$asset_result[0]->desc_grz_no_serial_no;
                            }

                        }
                    }
                   if($assets_lost_data!="")
                   {
                       $results[0]->items_lost=$assets_lost_data;
                   }
                 
                        $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                        );
                    break;
                case "stores_single_user_checkout_form_details":
                    $qry= DB::table('stores_asset_checkout_details as t1')
                        ->selectRaw('requisition_id as requisition_ids,asset_id as assets_for_individual,
                        checkout_date,no_due_date,due_date,send_email,checkout_site_id,
                        checkout_location_id,checkout_department_id,lost_items')
                        ->where('id',$request->query('checkout_id'));
                        $assets_lost_data="";
                        $results = $qry->get()->toArray();

                        if($results[0]->lost_items!=null && $results[0]->lost_items!="" )
                        {
                          
                            $asset_ids=$this->returnArrayFromStringArray($results[0]->lost_items);
                        
                            foreach($asset_ids as $asset_id)
                            {
                                
                               $asset_result= DB::table('ar_asset_inventory as t1')->where("id",$asset_id)->selectRaw(
                                    "CONCAT_WS('-',t1.description,t1.grz_no,t1.serial_no) as desc_grz_no_serial_no"
                                )->get();
                                if($assets_lost_data=="")
                                {
                                    $assets_lost_data=$asset_result[0]->desc_grz_no_serial_no;
                                }else{
                                    $assets_lost_data."<br/>".$asset_result[0]->desc_grz_no_serial_no;
                                }

                            }
                        }
                       if($assets_lost_data!="")
                       {
                           $results[0]->items_lost=$assets_lost_data;
                       }
                     
                        $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                        );
                    break;
                case "get_assets_for_combo":
                    $qry= DB::table('ar_asset_inventory as t1')
                        ->where('status_id',1)
                        ->selectRaw("id,CONCAT_WS('-',t1.description,t1.grz_no,t1.serial_no) as desc_grz_no_serial_no");
                        $results = $qry->get()->toArray();
                        $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                        );
                    break;
                case "get_assets_for_combo_check_out_all":
                    $qry= DB::table('ar_asset_inventory as t1')
                        ->whereIn('status_id',[1,2])
                        ->where('module_id',350)
                        ->selectRaw("id,CONCAT_WS('-',t1.description,t1.grz_no,t1.serial_no) as desc_grz_no_serial_no");
                        $results = $qry->get()->toArray();
                        $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                        );
                    break;
                  case "stores_get_assets_for_combo_check_out_all":
                    $qry= DB::table('ar_asset_inventory as t1')
                        ->where('module_id',637)
                        ->whereIn('status_id',[1,2])
                        ->selectRaw("id,CONCAT_WS('-',t1.description,t1.grz_no,t1.serial_no) as desc_grz_no_serial_no");
                        $results = $qry->get()->toArray();
                        $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                        );
                    break;
                case "get_assets_for_combo_check_out":
                    $qry= DB::table('ar_asset_inventory as t1')
                        ->where('status_id',2)
                        ->selectRaw("id,CONCAT_WS('-',t1.description,t1.grz_no,t1.serial_no) as desc_grz_no_serial_no");
                        $results = $qry->get()->toArray();
                        $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                        );
                    break;
                
                case "users_requisitions_on_Asset":
                    $asset_category_id=$request->query('asset_category_id');
                    $requisition_ids=$request->query('requisition_ids');
                   
                 
                    if($requisition_ids[0]!="[")
                    {
                       
                     $requisition_ids_array=explode(',',$requisition_ids);
                   
                    foreach($requisition_ids_array as $index=>$ids)
                    {
                        if($ids=="")
                        {
                            unset($requisition_ids_array[$index]);
                            //remove empty values
                        }
                    }
                 
                    }else{
                        
                        $requisition_ids_array=$this->returnArrayFromStringArray($requisition_ids);

                    }
                 
                 
                   
                    $qry=DB::table('ar_asset_requisitions as t1')
                     ->join('users as t2','t2.id','=','t1.requested_by')
                    //->where('t1.asset_category','=',$asset_category_id)
                    ->whereIn('t1.id',$requisition_ids_array)
                    ->selectRaw("t2.id,CONCAT_WS(' ',decrypt(t2.first_name),decrypt(t2.last_name),decrypt(t2.email))  as user_details,t1.id as requisition_id");
                    $results = $qry->get();

                    
                    $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                    );
                    break;
                case "stores_users_requisitions_on_Asset":
                    $asset_category_id=$request->query('asset_category_id');
                    $requisition_ids=$request->query('requisition_ids');
                   
                 
                    if($requisition_ids[0]!="[")
                    {
                       
                     $requisition_ids_array=explode(',',$requisition_ids);
                   
                    foreach($requisition_ids_array as $index=>$ids)
                    {
                        if($ids=="")
                        {
                            unset($requisition_ids_array[$index]);
                            //remove empty values
                        }
                    }
                 
                    }else{
                        
                        $requisition_ids_array=$this->returnArrayFromStringArray($requisition_ids);

                    }
                 
                 
                   
                    $qry=DB::table('stores_asset_requisitions as t1')
                     ->join('users as t2','t2.id','=','t1.requested_by')
                    //->where('t1.asset_category','=',$asset_category_id)
                    ->whereIn('t1.id',$requisition_ids_array)
                    ->selectRaw("t2.id,CONCAT_WS(' ',decrypt(t2.first_name),decrypt(t2.last_name),decrypt(t2.email))  as user_details,t1.id as requisition_id");
                    $results = $qry->get();

                    
                    $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                    );
                    break;
                case "categories_for_individuals":

                    //aim to produce multiple categories for multiple indviduals bulk request
                    //not completed
                    $asset_category_id=$request->query('asset_category_id');
                    $qry=DB::table('ar_asset_requisitions as t1')
                    ->join('users as t2','t2.id','=','t1.requested_by')
                   ->where('t1.asset_category','=',$asset_category_id)
                   ->selectRaw("t2.id");
                   $results = $qry->get()->toArray();
                   $user_ids=[];
                   foreach($results as $result)
                   {
                    $user_ids[]=$result->id;
                   }

                    $qry = DB::table('ar_asset_categories as t1')
                    ->leftjoin('ar_asset_requisitions as t2','t1.id','=','t2.asset_category')
                    ->join('users as t3','t3.id','=','t2.requested_by')
                    ->selectRaw("t1.id,CONCAT_WS('-',t1.description,t1.grz_no,t1.serial_no) as desc_grz_no_serial_no")    
                    ->where('requisition_status','=','1')
                    ->groupBy('t2.asset_category')
                    ->havingRaw('count(t2.asset_category) > 1')
                    ->havingRaw('count(t2.requested_by) >1 ')
                    ->whereIn('requested_by',$user_ids);
                    $results = $qry->get();
                    $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                    );
                    break;
                
                case "stores_assets_for_category_for_individuals":

                    //modfied on friday no testing
                   //main result
                    $asset_category_id=$request->query('asset_category_id');//sub category id
                   
                    $qry = DB::table('ar_asset_inventory as t1')
                    ->where('module_id',637)
                   // ->leftJoin('ar_asset_maintainance as t9', 't1.id', '=', 't9.asset_id')
                   // ->leftJoin('ar_asset_repairs as t10', 't1.id', '=', 't10.asset_id')
                    ->selectRaw("t1.id,CONCAT_WS('-',t1.description,t1.grz_no,t1.serial_no) as desc_grz_no_serial_no,
                    maintainance_schedule_date as maintainance_date");
                    //,scheduled_repair_date");
                    if($request->input('is_view_action')!=1){
                        $qry->where('status_id', 1);
                    }
                    $qry->where('sub_category_id',$asset_category_id);

                    $maintainance_details=DB::table('stores_asset_maintainance')->selectRaw('maintainance_status,asset_id,maintainance_due_date')->orderBy('created_at','DESC')->get();
                    $repair_details=DB::table('stores_asset_repairs')->selectRaw('asset_id,scheduled_repair_date,repair_status')->orderBy('created_at','DESC')->get();
                 
                   $maintainance_details=convertStdClassObjToArray($maintainance_details);
                   $repair_details=convertStdClassObjToArray($repair_details);
                
                    $results = $qry->get()->toArray();

                
                    $to_add_asset=true;

                    $ammended_results=[];
                    foreach($results as $asset)
                    {
                        $asset_maintainance_details=$this->_search_array_by_value($maintainance_details,'asset_id',$asset->id);
                        $asset_repair_details=$this->_search_array_by_value($repair_details,'asset_id',$asset->id);
                        if(count($asset_maintainance_details)>0){
                        $asset->maintainance_due_date=$asset_maintainance_details[0]['maintainance_due_date'];
                        $asset->maintainance_status=$asset_maintainance_details[0]['maintainance_status'];
                                if( (new \DateTime($asset->maintainance_due_date))<= (new \DateTime(date('Y-m-d'))) )
                            {
                                if($asset->maintainance_status==0){
                                $to_add_asset=false;
                                }
                            }
                            if($asset->maintainance_status==2 || $asset->maintainance_status==3 || $asset->maintainance_status==4)
                            {
                                $to_add_asset=true;
                            }
                           
                        }else{
                            $asset->maintainance_status="";
                        }
                        if(count($asset_repair_details)>0){
                            $asset->scheduled_repair_date=$asset_repair_details[0]['scheduled_repair_date'];
                            $asset->repair_status=$asset_repair_details[0]['repair_status'];

                            if( (new \DateTime($asset->scheduled_repair_date))<= (new \DateTime(date('Y-m-d'))) )
                            {
                                if($asset->repair_status==0){
                                $to_add_asset=false;
                                }
                            }
                            if($asset->repair_status==2 || $asset->repair_status==3){
                                $to_add_asset=true;
                            }
                         
                          
                        }else{
                            
                            $asset->scheduled_repair_date="";
                        }
                        //new add on
                        if($to_add_asset==false && $request->input('is_view_action')==1)
                        {
                        $to_add_asset=true;
                        }
                        //end add on
                        if($to_add_asset==true){
                        $ammended_results[]=$asset;
                        }
                     $to_add_asset=true;
                       
                    }
                    $results=$ammended_results;
                    $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                    );
                    break;
                case "stores_assets_for_category_for_individuals_suspended":

                    //modfied on friday no testing
                   //main result
                    $asset_category_id=$request->query('asset_category_id');//sub category id
                   
                    $qry = DB::table('ar_asset_inventory as t1')
                    ->where('module_id',637)
                      ->selectRaw("t1.id,CONCAT_WS('-',t1.description,t1.grz_no,t1.serial_no) as desc_grz_no_serial_no,
                    maintainance_schedule_date as maintainance_date");
                    //,scheduled_repair_date");
                    if($request->input('is_view_action')!=1){
                        $qry->where('status_id', 1);
                    }
                    $qry->where('sub_category_id',$asset_category_id);

                    $maintainance_details=DB::table('stores_asset_maintainance')->selectRaw('maintainance_status,asset_id,maintainance_due_date')->orderBy('created_at','DESC')->get();
                    $repair_details=DB::table('stores_asset_repairs')->selectRaw('asset_id,scheduled_repair_date,repair_status')->orderBy('created_at','DESC')->get();
                 
                   $maintainance_details=convertStdClassObjToArray($maintainance_details);
                   $repair_details=convertStdClassObjToArray($repair_details);
                
                    $results = $qry->get()->toArray();

                
                    $to_add_asset=true;

                    $ammended_results=[];
                    foreach($results as $asset)
                    {
                        $asset_maintainance_details=$this->_search_array_by_value($maintainance_details,'asset_id',$asset->id);
                        $asset_repair_details=$this->_search_array_by_value($repair_details,'asset_id',$asset->id);
                        if(count($asset_maintainance_details)>0){
                        $asset->maintainance_due_date=$asset_maintainance_details[0]['maintainance_due_date'];
                        $asset->maintainance_status=$asset_maintainance_details[0]['maintainance_status'];
                                if( (new \DateTime($asset->maintainance_due_date))<= (new \DateTime(date('Y-m-d'))) )
                            {
                                if($asset->maintainance_status==0){
                                $to_add_asset=false;
                                }
                            }
                            if($asset->maintainance_status==2 || $asset->maintainance_status==3 || $asset->maintainance_status==4)
                            {
                                $to_add_asset=true;
                            }
                           
                        }else{
                            $asset->maintainance_status="";
                        }
                        if(count($asset_repair_details)>0){
                            $asset->scheduled_repair_date=$asset_repair_details[0]['scheduled_repair_date'];
                            $asset->repair_status=$asset_repair_details[0]['repair_status'];

                            if( (new \DateTime($asset->scheduled_repair_date))<= (new \DateTime(date('Y-m-d'))) )
                            {
                                if($asset->repair_status==0){
                                $to_add_asset=false;
                                }
                            }
                            if($asset->repair_status==2 || $asset->repair_status==3){
                                $to_add_asset=true;
                            }
                         
                          
                        }else{
                            
                            $asset->scheduled_repair_date="";
                        }
                        //new add on
                        if($to_add_asset==false && $request->input('is_view_action')==1)
                        {
                        $to_add_asset=true;
                        }
                        //end add on
                        if($to_add_asset==true){
                        $ammended_results[]=$asset;
                        }
                     $to_add_asset=true;
                       
                    }
                    $results=$ammended_results;
                    $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                    );
                    break;
                    
                 case "assets_for_category_for_individuals":

                    //modfied on friday no testing
                   //main result
                    $asset_category_id=$request->query('asset_category_id');//sub category id
                   
                    $qry = DB::table('ar_asset_inventory as t1')
                    ->where('module_id',350)
                      ->selectRaw("t1.id,CONCAT_WS('-',t1.description,t1.grz_no,t1.serial_no) as desc_grz_no_serial_no,
                    maintainance_schedule_date as maintainance_date");
                    //,scheduled_repair_date");
                    if($request->input('is_view_action')!=1){
                        $qry->where('status_id', 1);
                    }
                    $qry->where('sub_category_id',$asset_category_id);

                    $maintainance_details=DB::table('ar_asset_maintainance')->selectRaw('maintainance_status,asset_id,maintainance_due_date')->orderBy('created_at','DESC')->get();
                    $repair_details=DB::table('ar_asset_repairs')->selectRaw('asset_id,scheduled_repair_date,repair_status')->orderBy('created_at','DESC')->get();
                 
                   $maintainance_details=convertStdClassObjToArray($maintainance_details);
                   $repair_details=convertStdClassObjToArray($repair_details);
                
                    $results = $qry->get()->toArray();

                
                    $to_add_asset=true;

                    $ammended_results=[];
                    foreach($results as $asset)
                    {
                        $asset_maintainance_details=$this->_search_array_by_value($maintainance_details,'asset_id',$asset->id);
                        $asset_repair_details=$this->_search_array_by_value($repair_details,'asset_id',$asset->id);
                        if(count($asset_maintainance_details)>0){
                        $asset->maintainance_due_date=$asset_maintainance_details[0]['maintainance_due_date'];
                        $asset->maintainance_status=$asset_maintainance_details[0]['maintainance_status'];
                                if( (new \DateTime($asset->maintainance_due_date))<= (new \DateTime(date('Y-m-d'))) )
                            {
                                if($asset->maintainance_status==0){
                                $to_add_asset=false;
                                }
                            }
                            if($asset->maintainance_status==2 || $asset->maintainance_status==3 || $asset->maintainance_status==4)
                            {
                                $to_add_asset=true;
                            }
                           
                        }else{
                            $asset->maintainance_status="";
                        }
                        if(count($asset_repair_details)>0){
                            $asset->scheduled_repair_date=$asset_repair_details[0]['scheduled_repair_date'];
                            $asset->repair_status=$asset_repair_details[0]['repair_status'];

                            if( (new \DateTime($asset->scheduled_repair_date))<= (new \DateTime(date('Y-m-d'))) )
                            {
                                if($asset->repair_status==0){
                                $to_add_asset=false;
                                }
                            }
                            if($asset->repair_status==2 || $asset->repair_status==3){
                                $to_add_asset=true;
                            }
                         
                          
                        }else{
                            
                            $asset->scheduled_repair_date="";
                        }
                        //new add on
                        if($to_add_asset==false && $request->input('is_view_action')==1)
                        {
                        $to_add_asset=true;
                        }
                        //end add on
                        if($to_add_asset==true){
                        $ammended_results[]=$asset;
                        }
                     $to_add_asset=true;
                       
                    }
                    $results=$ammended_results;
                    $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                    );
                    break;
                    
                case "assets_category_per_site":
                    $requisition_site_id=$request->query('requisition_site_id');
                    $qry=DB::table('ar_asset_requisitions as t1')
                    ->join('ar_asset_subcategories as t2', 't1.asset_category', '=', 't2.id')
                    ->where('requistion_site_id','=',$requisition_site_id)
                    ->where('requisition_status','=','1')
                    ->selectRaw('t1.id as requisition_id,t2.id as category_id,t2.name,requistion_site_id')
                    ->where('requested_for',2);
                    $results = $qry->get();
                    
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($results),
                        'results' => $results
                    );
                    
                    break;
                case "stores_assets_category_per_site":
                    $requisition_site_id=$request->query('requisition_site_id');
                    $qry=DB::table('stores_asset_requisitions as t1')
                    ->join('stores_subcategories as t2', 't1.asset_category', '=', 't2.id')
                    ->where('requistion_site_id','=',$requisition_site_id)
                    ->where('requisition_status','=','1')
                    ->selectRaw('t1.id as requisition_id,t2.id as category_id,t2.name,requistion_site_id')
                    ->where('requested_for',2);
                    $results = $qry->get();
                    
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($results),
                        'results' => $results
                    );
                    
                    break;
                case "validate_and_revert_individual_bulk_checkout":
                    $requisition_user_id=$request->query('requisition_user_id');
                    $requisition_site_id=$request->query('requisition_site_id');
                    $res=array();
                    $has_reverted=false;
                    $results="";
                    if (validateisNumeric($requisition_user_id)) {
                    $qry=DB::table('ar_asset_requisitions as t1')
                        ->join('ar_asset_subcategories as t2', 't1.asset_category', '=', 't2.id')
                        ->where('requested_by','=',$requisition_user_id)
                        ->where('requisition_status','=','1')
                        ->selectRaw('t1.id as requisition_id,t2.id as category_id,t2.name');
                    }else{
                        $qry=DB::table('ar_asset_requisitions as t1')
                        ->join('ar_asset_subcategories as t2', 't1.asset_category', '=', 't2.id')
                        ->where('requistion_site_id','=',  $requisition_site_id)
                        ->where('requisition_status','=','1')
                        ->selectRaw('t1.id as requisition_id,t2.id as category_id,t2.name');

                    }
                    $results=$qry->get();
                    $results=convertStdClassObjToArray($results);
                   
                    $category_count=[];
                   
                    foreach($results as $category)
                    {
                        $assets=DB::table('ar_asset_inventory')->where('sub_category_id',$category['category_id'])->where('status_id',1)->get();
                        $assets=convertStdClassObjToArray($assets);
                      
                        $asset_count=0;
                        
                        foreach($assets as $asset)
                        {
                        $result_mai=DB::table('ar_asset_maintainance')->where('asset_id',$asset['id'])->orderBy('created_at', 'desc')->limit(1)->get();
                        $result_mai=convertStdClassObjToArray( $result_mai);
                     
                        $results_repair=DB::table('ar_asset_repairs')->where('asset_id',$asset['id'])->orderBy('created_at', 'desc')->limit(1)->get();
                        $results_repair=convertStdClassObjToArray( $results_repair);
                    
                        $incremeted=false;
                        if(count($result_mai)>0)
                        {
                           
                            $main_data=$result_mai[0];
                            if($main_data['maintainance_status']==0 || $main_data['maintainance_status']==2 
                            || $main_data['maintainance_status']==3 ||  $main_data['maintainance_status']==4 )
                            {
                                //new implementation,scheduled and completed both okay
                               


                                if((new \DateTime($main_data['maintainance_due_date']))>(new \DateTime(date('Y-m-d'))))
                                {
                                    $asset_count+=1;
                                    $incremeted=true;
                                }else if($main_data['maintainance_status']==2 || $main_data['maintainance_status']==3 ||  $main_data['maintainance_status']==4)
                                {
                                    $asset_count+=1;
                                    $incremeted=true;
                                }
                               
                               

                            }

                            
                            //initial implementation
                            // if($main_data['maintainance_status']==0)
                            // {
                            //     if( (new \DateTime($main_data['maintainance_due_date']))> 
                            //     (new \DateTime(date('d-m-Y'))) ){
                            //         $asset_count=+1;
                            //     }
                            // }
                            // if($main_data['maintainance_status']==2)
                            // {
                            //     $asset_count=+1;
                            // }



                        }else{
                            $asset_count+=1;
                            $incremeted=true;
                        }
                        //incremented to prevent double adding in repair and maintaianace
                        if(count($results_repair)>0)
                        {
                            $repair_data=$results_repair[0];
                            //eclude those under repair
                            if($repair_data['repair_status']==1)
                            {
                               if($incremeted==true)
                               {
                                   $asset_count-=1;
                               }
                                
                            }else if(($repair_data['repair_status']==0)){
                                //include those scheduled for future
                                if((new \DateTime($results_repair[0]['scheduled_repair_date']))>(new \DateTime(date('Y-m-d'))))
                                {
                                if($incremeted==false)
                               {
                                   $asset_count+=1;
                               }
                                 }
                            
                            }else if(($repair_data['repair_status']==2) || ($repair_data['repair_status']==3)){
                                if($incremeted==false)
                                {
                                    $asset_count+=1;
                                }
                            }
                           
                            
                        }else{
                            if($incremeted==false)
                                {
                                    $asset_count+=1;
                                }
                        }

                        $incremeted=false;
                        $category_count[$category['requisition_id']]=$asset_count;
                       
                        }

                    }
                  
                    $full_asset_category_count=0;
                   
                    foreach($category_count as $key=>$cate_count)
                    {
                        $full_asset_category_count+=$cate_count;
                        if($cate_count==0)
                        {
                            DB::table('ar_asset_requisitions')->where('id',$key)
                            ->update(['requisition_status'=>1,"remarks"=>"Item for assignment is Unavailable"]);
                            $has_reverted=true;
                        }

                    }
                    if($has_reverted==true)
                    {
                        $res = array(
                            'success' => true,
                            'message' => "Some requisitions were reverted",
                            "results"=>$full_asset_category_count,
                            "anyRevert"=>$has_reverted
                        );
                    }else{
                       
                        $res = array(
                            'success' => true,
                            "results"=>$full_asset_category_count,
                            "anyRevert"=>$has_reverted
                        );
                    }
                    
                    break;
                case "stores_validate_and_revert_individual_bulk_checkout":
                    $requisition_user_id=$request->query('requisition_user_id');
                    $requisition_site_id=$request->query('requisition_site_id');
                    $res=array();
                    $has_reverted=false;
                    $results="";
                    if (validateisNumeric($requisition_user_id)) {
                    $qry=DB::table('stores_asset_requisitions as t1')
                        ->join('stores_subcategories as t2', 't1.asset_category', '=', 't2.id')
                        ->where('requested_by','=',$requisition_user_id)
                        ->where('requisition_status','=','1')
                        ->selectRaw('t1.id as requisition_id,t2.id as category_id,t2.name');
                    }else{
                        $qry=DB::table('stores_asset_requisitions as t1')
                        ->join('stores_subcategories as t2', 't1.asset_category', '=', 't2.id')
                        ->where('requistion_site_id','=',  $requisition_site_id)
                        ->where('requisition_status','=','1')
                        ->selectRaw('t1.id as requisition_id,t2.id as category_id,t2.name');

                    }
                    $results=$qry->get();
                    $results=convertStdClassObjToArray($results);
                   
                    $category_count=[];
                   
                    foreach($results as $category)
                    {
                        $assets=DB::table('ar_asset_inventory')->where('sub_category_id',$category['category_id'])->where('status_id',1)->get();
                        $assets=convertStdClassObjToArray($assets);
                      
                        $asset_count=0;
                        
                        foreach($assets as $asset)
                        {
                        $result_mai=DB::table('stores_asset_maintainance')->where('asset_id',$asset['id'])->orderBy('created_at', 'desc')->limit(1)->get();
                        $result_mai=convertStdClassObjToArray( $result_mai);
                     
                        $results_repair=DB::table('stores_asset_repairs')->where('asset_id',$asset['id'])->orderBy('created_at', 'desc')->limit(1)->get();
                        $results_repair=convertStdClassObjToArray( $results_repair);
                    
                        $incremeted=false;
                        if(count($result_mai)>0)
                        {
                           
                            $main_data=$result_mai[0];
                            if($main_data['maintainance_status']==0 || $main_data['maintainance_status']==2 
                            || $main_data['maintainance_status']==3 ||  $main_data['maintainance_status']==4 )
                            {
                                //new implementation,scheduled and completed both okay
                               


                                if((new \DateTime($main_data['maintainance_due_date']))>(new \DateTime(date('Y-m-d'))))
                                {
                                    $asset_count+=1;
                                    $incremeted=true;
                                }else if($main_data['maintainance_status']==2 || $main_data['maintainance_status']==3 ||  $main_data['maintainance_status']==4)
                                {
                                    $asset_count+=1;
                                    $incremeted=true;
                                }
                      

                            }


                        }else{
                            $asset_count+=1;
                            $incremeted=true;
                        }
                        //incremented to prevent double adding in repair and maintaianace
                        if(count($results_repair)>0)
                        {
                            $repair_data=$results_repair[0];
                            //eclude those under repair
                            if($repair_data['repair_status']==1)
                            {
                               if($incremeted==true)
                               {
                                   $asset_count-=1;
                               }
                                
                            }else if(($repair_data['repair_status']==0)){
                                //include those scheduled for future
                                if((new \DateTime($results_repair[0]['scheduled_repair_date']))>(new \DateTime(date('Y-m-d'))))
                                {
                                if($incremeted==false)
                               {
                                   $asset_count+=1;
                               }
                                 }
                            
                            }else if(($repair_data['repair_status']==2) || ($repair_data['repair_status']==3)){
                                if($incremeted==false)
                                {
                                    $asset_count+=1;
                                }
                            }
                           
                            
                        }else{
                            if($incremeted==false)
                                {
                                    $asset_count+=1;
                                }
                        }

                        $incremeted=false;
                        $category_count[$category['requisition_id']]=$asset_count;
                       
                        }

                    }
                  
                    $full_asset_category_count=0;
                   
                    foreach($category_count as $key=>$cate_count)
                    {
                        $full_asset_category_count+=$cate_count;
                        if($cate_count==0)
                        {
                            DB::table('stores_asset_requisitions')->where('id',$key)
                            ->update(['requisition_status'=>1,"remarks"=>"Item for assignment is Unavailable"]);
                            $has_reverted=true;
                        }

                    }
                    if($has_reverted==true)
                    {
                        $res = array(
                            'success' => true,
                            'message' => "Some requisitions were reverted",
                            "results"=>$full_asset_category_count,
                            "anyRevert"=>$has_reverted
                        );
                    }else{
                       
                        $res = array(
                            'success' => true,
                            "results"=>$full_asset_category_count,
                            "anyRevert"=>$has_reverted
                        );
                    }
                    
                    break;
                 case "stores_validate_and_revert_individual_bulk_checkout":
                    $requisition_user_id=$request->query('requisition_user_id');
                    $requisition_site_id=$request->query('requisition_site_id');
                    $res=array();
                    $has_reverted=false;
                    $results="";
                    if (validateisNumeric($requisition_user_id)) {
                    $qry=DB::table('stores_asset_requisitions as t1')
                        ->join('stores_subcategories as t2', 't1.asset_category', '=', 't2.id')
                        ->where('requested_by','=',$requisition_user_id)
                        ->where('requisition_status','=','1')
                        ->selectRaw('t1.id as requisition_id,t2.id as category_id,t2.name');
                    }else{
                        $qry=DB::table('stores_asset_requisitions as t1')
                        ->join('stores_subcategories as t2', 't1.asset_category', '=', 't2.id')
                        ->where('requistion_site_id','=',  $requisition_site_id)
                        ->where('requisition_status','=','1')
                        ->selectRaw('t1.id as requisition_id,t2.id as category_id,t2.name');

                    }
                    $results=$qry->get();
                    $results=convertStdClassObjToArray($results);
                   
                    $category_count=[];
                   
                    foreach($results as $category)
                    {
                        $assets=DB::table('ar_asset_inventory')->where('sub_category_id',$category['category_id'])->where('status_id',1)->get();
                        $assets=convertStdClassObjToArray($assets);
                      
                        $asset_count=0;
                        
                        foreach($assets as $asset)
                        {
                        $result_mai=DB::table('stores_asset_maintainance')->where('asset_id',$asset['id'])->orderBy('created_at', 'desc')->limit(1)->get();
                        $result_mai=convertStdClassObjToArray( $result_mai);
                     
                        $results_repair=DB::table('stores_asset_repairs')->where('asset_id',$asset['id'])->orderBy('created_at', 'desc')->limit(1)->get();
                        $results_repair=convertStdClassObjToArray( $results_repair);
                    
                        $incremeted=false;
                        if(count($result_mai)>0)
                        {
                           
                            $main_data=$result_mai[0];
                            if($main_data['maintainance_status']==0 || $main_data['maintainance_status']==2 
                            || $main_data['maintainance_status']==3 ||  $main_data['maintainance_status']==4 )
                            {
                                //new implementation,scheduled and completed both okay
                               


                                if((new \DateTime($main_data['maintainance_due_date']))>(new \DateTime(date('Y-m-d'))))
                                {
                                    $asset_count+=1;
                                    $incremeted=true;
                                }else if($main_data['maintainance_status']==2 || $main_data['maintainance_status']==3 ||  $main_data['maintainance_status']==4)
                                {
                                    $asset_count+=1;
                                    $incremeted=true;
                                }
                               
                               

                            }

                          



                        }else{
                            $asset_count+=1;
                            $incremeted=true;
                        }
                        //incremented to prevent double adding in repair and maintaianace
                        if(count($results_repair)>0)
                        {
                            $repair_data=$results_repair[0];
                            //eclude those under repair
                            if($repair_data['repair_status']==1)
                            {
                               if($incremeted==true)
                               {
                                   $asset_count-=1;
                               }
                                
                            }else if(($repair_data['repair_status']==0)){
                                //include those scheduled for future
                                if((new \DateTime($results_repair[0]['scheduled_repair_date']))>(new \DateTime(date('Y-m-d'))))
                                {
                                if($incremeted==false)
                               {
                                   $asset_count+=1;
                               }
                                 }
                            
                            }else if(($repair_data['repair_status']==2) || ($repair_data['repair_status']==3)){
                                if($incremeted==false)
                                {
                                    $asset_count+=1;
                                }
                            }
                           
                            
                        }else{
                            if($incremeted==false)
                                {
                                    $asset_count+=1;
                                }
                        }

                        $incremeted=false;
                        $category_count[$category['requisition_id']]=$asset_count;
                       
                        }

                    }
                  
                    $full_asset_category_count=0;
                   
                    foreach($category_count as $key=>$cate_count)
                    {
                        $full_asset_category_count+=$cate_count;
                        if($cate_count==0)
                        {
                            DB::table('stores_asset_requisitions')->where('id',$key)
                            ->update(['requisition_status'=>1,"remarks"=>"Item for assignment is Unavailable"]);
                            $has_reverted=true;
                        }

                    }
                    if($has_reverted==true)
                    {
                        $res = array(
                            'success' => true,
                            'message' => "Some requisitions were reverted",
                            "results"=>$full_asset_category_count,
                            "anyRevert"=>$has_reverted
                        );
                    }else{
                       
                        $res = array(
                            'success' => true,
                            "results"=>$full_asset_category_count,
                            "anyRevert"=>$has_reverted
                        );
                    }
                    
                    break;
                case "assets_category_requisition_per_user":
                    $requisition_user_id=$request->query('requisition_user_id');
                    $qry=DB::table('ar_asset_requisitions as t1')
                        ->join('ar_asset_subcategories as t2', 't1.asset_category', '=', 't2.id')
                        ->where('requested_by','=',$requisition_user_id)
                        ->where('requisition_status','=','1')
                        ->selectRaw('t1.id as requisition_id,t2.id as category_id,t2.name');
                        


                       
                    $results = $qry->get()->toArray();
                 
                    $chained_results=[];
                    foreach($results as $result)
                    {
                        $result->requisition_user_id=$request->query('requisition_user_id');
                        $chained_results[]=$result;
                    }

                   


                        //$results = $qry->get()->toArray();
                        // $category_ids =[];
                    
                        // foreach($results as $result)
                        // {
                           
                        //     $category_ids[]=$result->asset_category;
                            

                        // }
                        //assets matching user request categories
                        // $assets = DB::table('ar_asset_inventory as t1')
                        //    ->whereIn('category_id',$category_ids)
                        //    ->leftjoin('ar_asset_categories as t2', 't1.category_id', '=', 't2.id')
                        //    ->groupBy('t1.category_id')
                        //    ->where('status_id',1)->get();
                        
                        $res = array(
                            'success' => true,
                            //'message' => returnMessage($results),
                            'results' => $chained_results
                        );
                     break;
                    case "stores_assets_category_requisition_per_user":
                        $requisition_user_id=$request->query('requisition_user_id');
                        $qry=DB::table('stores_asset_requisitions as t1')
                            ->join('stores_subcategories as t2', 't1.asset_category', '=', 't2.id')
                            ->where('requested_by','=',$requisition_user_id)
                            ->where('requisition_status','=','1')
                            ->selectRaw('t1.id as requisition_id,t2.id as category_id,t2.name');
                        $results = $qry->get()->toArray();
                        $chained_results=[];
                        foreach($results as $result)
                        {
                            $result->requisition_user_id=$request->query('requisition_user_id');
                            $chained_results[]=$result;
                        }
                            $res = array(
                                'success' => true,
                                'results' => $chained_results
                            );
                        break;
                    case "site_reserved_assets":
                        $qry = DB::table('ar_asset_reservations as t1')
                        ->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
                        ->join('ar_asset_sites as t4', 't1.reservation_site_id', '=', 't4.id')
                        ->leftjoin('ar_asset_locations as t5', 't1.reservation_location_id', '=', 't5.id')
                        ->leftjoin('departments as t6', 't1.reservation_department_id', '=', 't6.id')
                        ->join('ar_asset_statuses as t7', 't2.status_id', '=', 't7.id')
                        ->selectRaw("t1.id as reservation_id,t1.*,t2.description,t2.serial_no,t1.checkin_status,t2.grz_no,t4.name as site_name,t5.name as location_name,
                                t2.site_id,t2.location_id,t2.department_id,t6.name as department_name,
                                t7.name as record_status,t1.start_date,t1.end_date,t1.reserve_purpose,t1.reserve_for_who as reservation_for_id,t1.id as reservation_record_id,
                                t1.reservation_site_id as checkout_site_id,t1.reservation_location_id as checkout_location_id,
                                t1.reservation_department_id as checkout_department_id,t2.sub_category_id as category_id,t1.start_date as checkout_date,t1.end_date as due_date")
                                ->where('reserve_for_who',2);
                                //alias for sub category id here is category_id
                        $results = $qry->get();
                      
                        $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                        );
                        break;
                    case "stores_site_reserved_assets":
                        $qry = DB::table('stores_asset_reservations as t1')
                        ->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
                        ->join('stores_sites as t4', 't1.reservation_site_id', '=', 't4.id')
                        ->leftjoin('stores_locations as t5', 't1.reservation_location_id', '=', 't5.id')
                        ->leftjoin('stores_departments as t6', 't1.reservation_department_id', '=', 't6.id')
                        ->join('stores_statuses as t7', 't2.status_id', '=', 't7.id')
                        ->selectRaw("t1.id as reservation_id,t1.*,t2.description,t2.serial_no,t1.checkin_status,t2.grz_no,t4.name as site_name,t5.name as location_name,
                                t2.site_id,t2.location_id,t2.department_id,t6.name as department_name,
                                t7.name as record_status,t1.start_date,t1.end_date,t1.reserve_purpose,t1.reserve_for_who as reservation_for_id,t1.id as reservation_record_id,
                                t1.reservation_site_id as checkout_site_id,t1.reservation_location_id as checkout_location_id,
                                t1.reservation_department_id as checkout_department_id,t2.sub_category_id as category_id,t1.start_date as checkout_date,t1.end_date as due_date")
                                ->where('reserve_for_who',2);
                                //alias for sub category id here is category_id
                        $results = $qry->get();
                      
                        $res = array(
                            'success' => true,
                            'message' => returnMessage($results),
                            'results' => $results
                        );
                        break;
                case "stores_default":
                    $qry = DB::table('ar_asset_inventory as t1')
                    ->join('stores_categories as t2', 't1.category_id', '=', 't2.id')
                    ->join('stores_brands as t3', 't1.brand_id', '=', 't3.id')
                    ->leftjoin('stores_models as t4', 't1.model_id', '=', 't4.id')
                    ->leftjoin('stores_sites as t5', 't1.site_id', '=', 't5.id')
                    ->leftjoin('stores_locations as t6', 't1.location_id', '=', 't6.id')
                    ->leftjoin('stores_departments as t7', 't1.department_id', '=', 't7.id')
                    ->join('stores_statuses as t8', 't1.status_id', '=', 't8.id')
                    ->selectRaw("t1.*, t1.id as record_id,t1.id as asset_id, t2.name as category_name, t3.name as brand_name, t4.name as model_name,t5.name as site_name,
                        t6.name as location_name,t7.name as department_name, t8.name as record_status,CONCAT_WS('-',t1.description,t1.grz_no) as desc_grz_no");
                    $qry->where('t1.module_id',637)
                    ->whereIn('status_id', [1,2,3]);
                    $results = $qry->get();
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($results),
                        'results' => $results
                    );
                    break;
                default:
                    $qry = DB::table('ar_asset_inventory as t1')
                    ->join('ar_asset_categories as t2', 't1.category_id', '=', 't2.id')
                    ->join('ar_asset_brands as t3', 't1.brand_id', '=', 't3.id')
                    ->leftjoin('ar_asset_models as t4', 't1.model_id', '=', 't4.id')
                    ->leftjoin('ar_asset_sites as t5', 't1.site_id', '=', 't5.id')
                    ->leftjoin('ar_asset_locations as t6', 't1.location_id', '=', 't6.id')
                    ->leftjoin('departments as t7', 't1.department_id', '=', 't7.id')
                    ->join('ar_asset_statuses as t8', 't1.status_id', '=', 't8.id')
                    ->selectRaw("t1.*, t1.id as record_id,t1.id as asset_id, t2.name as category_name, t3.name as brand_name, t4.name as model_name,t5.name as site_name,
                        t6.name as location_name,t7.name as department_name, t8.name as record_status,CONCAT_WS('-',t1.description,t1.grz_no) as desc_grz_no");
                    $qry ->where('t1.module_id',350)
                    ->whereIn('status_id', [1,2,3]);
                    
                    
            
                    $results = $qry->get();
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($results),
                        'results' => $results
                    );
                    break;

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
    public function DeleteAsset(Request $request)
    {

        try{
        $asset_id = $request->input('asset_id');
        $user_id=$this->user_id;
        $where=array(
            'id'=>$asset_id
        );
        $table_data=[
            "asset_id"=>$asset_id,
            "archived_by"=>$user_id,
            "archive_date"=>Carbon::now()
        ];
        $res = insertRecord('ar_asset_archive', $table_data, $user_id);
       
        if($res['success']==true)
        {
           
            $table_data=[];
            $table_data['status_id']=12;
            $table_data['updated_at']=Carbon::now();
            $table_data['updated_by']=$user_id;
            $previous_data = getPreviousRecords('ar_asset_inventory', $where);
           

            $res = updateRecord('ar_asset_inventory', $previous_data, $where, $table_data, $user_id);
           
            if($res['success']==true){
              
               $res['message']= "Asset Archived Successfully";
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
    public function search_array ( $array, $key, $value )
    {
       return array_search($value,array_column($array,$key));
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
    
    public function generateAssetsReport(Request $request)
    {
        $res=array();
        try{
        $url=url('/');
        $image_url=$url.'/backend/public/moge_logo.png';
        $report_type= $request->query('report_type');
        $pdf_url = $url."/assetregister/generateAssetsReport?report_type=".$report_type;
        $report_order=$request->query('report_order');
        $start_date=$request->query('start_date');
        $end_date=$request->query('end_date');
       
        if($report_order)
        {
            $pdf_url.="&report_order=".$report_order;
        }
    
        switch($report_type)
        {
            case 12:
                return Excel::download(new  DisposalReport (),'.DisposalReport.xlsx');
                break;
            case 11:
                return Excel::download(new ConsolidatedAssetsData(),'.ConsolidatedAssetsData.xlsx');
                break;
            case 10:
                $qry=DB::table('ar_asset_archive as t1')
                ->join('ar_asset_inventory as t2','t2.id','t1.asset_id')
               
                 ->selectRaw("t2.description,t2.serial_no,t2.grz_no,t1.archive_date,
                 DATE_FORMAT(t1.archive_date,'%d-%b-%Y') as archive_date");
                 if($start_date)
                 {
                     $qry->whereDate('t1.archive_date','>=',Carbon::parse($start_date));    
                 }
                 if($end_date)
                 { 
                     $qry->whereDate('t1.archive_date','<=',Carbon::parse($end_date));   
                 }
                 
                if($report_order && $report_order=="description")
                {
            $qry->orderBy('t2.description', 'desc');
                }
                if($report_order && $report_order=="archive_date")
                {
            $qry->orderBy('t1.archive_date', 'desc');
                }
                $results=$qry->get();
                 $results=convertStdClassObjToArray($results);
                 $report_title="Asset Archive Report";
                    $table_headers=["Description","Serial No","Grz No","Archive  Date"];
                         $width=100/count($table_headers);
                         $header_style= "width:".$width."%; background-color: #04AA6D;color: white;text-align: left";
                         $data_style="width:".$width."%;text-align: left;padding-left:7px;";
                        $asset_inventory=$results;
                     
                        $exclusion_fields=[];
                        
                        $report_date= date('d-M-Y H:i:s');
                        $view=view('customassetreport',compact('asset_inventory','report_date','image_url',
                        'table_headers','header_style','data_style','exclusion_fields','report_title'))->render();
                        //return $view;
                       $this->generatePDFFIle($view);
                break;
            case 9://asset damaged
                $qry=DB::table('ar_asset_loss_damage_details as t1')
                ->join('ar_asset_inventory as t2','t2.id','t1.asset_id')
                ->where('lost_damaged_id',5)
                 //->where('maintainance_status',1)//dispatched
                 ->selectRaw("t2.description,t2.serial_no,t2.grz_no,DATE_FORMAT(t1.loss_damage_date,'%d-%b-%Y') as loss_date");
                 if($start_date)
                 {
                     $qry->whereDate('t1.loss_damage_date','>=',Carbon::parse($start_date));    
                 }
                 if($end_date)
                 { 
                     $qry->whereDate('t1.loss_damage_date','<=',Carbon::parse($end_date));   
                 }
                 
                 if($report_order && $report_order=="description")
                 {
             $qry->orderBy('t2.description', 'desc');
                 }
                 if($report_order && $report_order=="loss_damage_date")
                 {
             $qry->orderBy('t1.loss_damage_date', 'desc');
                 }
                 $results=$qry->get();
                 $results=convertStdClassObjToArray($results);
                 $report_title="Asset Damage Report";
                    $table_headers=["Description","Serial No","Grz No","Damage  Date"];
                         $width=100/count($table_headers);
                         $header_style= "width:".$width."%; background-color: #04AA6D;color: white;text-align: left";
                         $data_style="width:".$width."%;text-align: left;padding-left:7px;";
                        $asset_inventory=$results;
                     
                        $exclusion_fields=[];
                        
                        $report_date= date('d-M-Y H:i:s');
                        $view=view('customassetreport',compact('asset_inventory','report_date','image_url',
                        'table_headers','header_style','data_style','exclusion_fields','report_title'))->render();
                        //return $view;
                       $this->generatePDFFIle($view);
                break;
            case 8://asset loss
                $qry=DB::table('ar_asset_loss_damage_details as t1')
                ->join('ar_asset_inventory as t2','t2.id','t1.asset_id')
                ->where('lost_damaged_id',4)
               
                 ->selectRaw("t2.description,t2.serial_no,t2.grz_no,DATE_FORMAT(t1.loss_damage_date,'%d-%b-%Y') as loss_date");
                 
                 if($start_date)
                 {
                     $qry->whereDate('t2.loss_damage_date','>=',Carbon::parse($start_date));    
                 }
                 if($end_date)
                 { 
                     $qry->whereDate('t1.loss_damage_date','<=',Carbon::parse($end_date));   
                 }
                if($report_order && $report_order=="description")
                {
            $qry->orderBy('t2.description', 'desc');
                }
                if($report_order && $report_order=="loss_damage_date")
                {
            $qry->orderBy('t1.loss_damage_date', 'desc');
                }
                $results=$qry->get();
                 $results=convertStdClassObjToArray($results);
                 $report_title="Asset Loss Report";
                    $table_headers=["Description","Serial No","Grz No","Date lost"];
                         $width=100/count($table_headers);
                         $header_style= "width:".$width."%; background-color: #04AA6D;color: white;text-align: left";
                         $data_style="width:".$width."%;text-align: left;padding-left:7px;";
                        $asset_inventory=$results;
                       
                     
                        $exclusion_fields=[];
                        
                        $report_date= date('d-M-Y H:i:s');
                        $view=view('customassetreport',compact('asset_inventory','report_date','image_url',
                        'table_headers','header_style','data_style','exclusion_fields','report_title'))->render();
                       // return $view;
                       $this->generatePDFFIle($view);

                break;
            case 7://asset write off

                $qry=DB::table('ar_asset_write_off_details as t1')
                ->join('ar_asset_inventory as t2','t2.id','t1.asset_id')
               
               
                 ->selectRaw("t2.description,t2.serial_no,
                 DATE_FORMAT(t1.date_written_off,'%d-%b-%Y') as date_written_off,t1.write_off_reason");
                 if($start_date)
                 {
                     $qry->whereDate('t1.date_written_off','>=',Carbon::parse($start_date));    
                 }
                 if($end_date)
                 { 
                     $qry->whereDate('t1.date_written_off','<=',Carbon::parse($end_date));   
                 }
                 if($report_order && $report_order=="description")
                 {
             $qry->orderBy('t2.description', 'desc');
                 }
                 if($report_order && $report_order=="date_written_off")
                 {
             $qry->orderBy('t1.date_written_off', 'desc');
                 }
                 $results=$qry->get();
                
                 $results=convertStdClassObjToArray($results);
                 $report_title="Asset Write-off Report";
                    $table_headers=["Description","Serial No","Grz No",
                        "Date Written-Off","Write-Off Reason"];
                         $width=100/count($table_headers);
                         $header_style= "width:".$width."%; background-color: #04AA6D;color: white;text-align: left";
                         $data_style="width:".$width."%;text-align: left;padding-left:7px;";
                        $asset_inventory=$results;
                        //dd($asset_inventory);
                        $exclusion_fields=[];
                        
                        $report_date= date('d-M-Y H:i:s');
                        $view=view('customassetreport',compact('asset_inventory','report_date','image_url',
                        'table_headers','header_style','exclusion_fields','report_title'))->render();
                       // return $view;
                       $this->generatePDFFIle($view);

                break;
            case 6://asset reapir

                


                    
                $qry=DB::table('ar_asset_repairs as t1')
                ->join('ar_asset_inventory as t2','t2.id','t1.asset_id')
                ->selectRaw('asset_id,t1.id as repair_record_id,t2.description,t2.serial_no,t1.scheduled_repair_date,
                date_repair_completed,repair_status')
                ->orderBy('t1.created_at','DESC');
                if($report_order && $report_order=="description")
                {
                $qry->orderBy('t2.description', 'desc');
                }
                if($report_order && $report_order=="scheduled_repair_date")
                {
                $qry->orderBy('t1.scheduled_repair_date', 'desc');
                }
                if($start_date)
                {
                 
                    $qry->whereDate('t1.scheduled_repair_date','>=',Carbon::parse($start_date));
                    
                }
                if($end_date)
                {
                   
                    $qry->whereDate('t1.scheduled_repair_date','<=',Carbon::parse($end_date));   
                }
                $assets_ids=$qry->get()->toArray();
                //sort to pick only lastes entry of asset
                $ids_of_assets=[];
                $repair_records=[];
                $repair_records_ids=[];
                foreach($assets_ids as $asset_data)
                {
                    if(!in_array($asset_data->asset_id,$ids_of_assets))
                    {
                    $ids_of_assets[]=$asset_data->asset_id;
                    if($asset_data->repair_status==0)
                    {
                        $asset_data->status="Scheduled";
                    }
                    if($asset_data->repair_status==1)
                    {
                        $asset_data->status="In Progress";
                    }
                    if($asset_data->repair_status==2)
                    {
                        $asset_data->status="Completed";
                    }
                    $repair_records_ids[]=$asset_data->repair_record_id;
                    $asset_data=convertStdClassObjToArray($asset_data);
                    unset($asset_data['repair_record_id']);
                    unset($asset_data['asset_id']);
                    unset($asset_data['repair_status']);
                    $repair_records[]=$asset_data;
                   
                    }
                }
               
                    //end new query
                    $report_title="Asset Repair Report";
                    $table_headers=["Description","Serial No","Grz No",
                        "Due Date","Status"];
                         $width=100/count($table_headers);
                         $header_style= "width:".$width."%; background-color: #04AA6D;color: white;text-align: left";
                         $data_style="width:".$width."%;text-align: left;padding-left:7px;";
                        //$asset_inventory=$repair_report;
                        $asset_inventory=$repair_records;
                        //dd($asset_inventory);
                        $exclusion_fields=[];
                        
                        $report_date= date('d-M-Y H:i:s');
                        $view=view('customassetreport',compact('asset_inventory','report_date','image_url',
                        'table_headers','header_style','data_style','exclusion_fields','report_title'))->render();
                        
                       $this->generatePDFFIle($view);
            case 5://asset maintainance

                $qry=DB::table('ar_asset_maintainance as t1')
                ->join('ar_asset_inventory as t2','t2.id','t1.asset_id')
                ->selectRaw('asset_id,t1.id as main_record_id,t2.description,t2.serial_no,t1.maintainance_due_date,
                date_maintainance_completed,maintainance_status')
                ->orderBy('t1.created_at','DESC');
                if($report_order && $report_order=="description")
                {
                $qry->orderBy('t2.description', 'desc');
                }
                if($report_order && $report_order=="maintainance_due_date")
                {
                $qry->orderBy('t1.maintainance_due_date', 'desc');
                }
                if($start_date)
                {
                 
                    $qry->whereDate('t1.maintainance_due_date','>=',Carbon::parse($start_date));
                    
                }
                if($end_date)
                {
                   
                    $qry->whereDate('t1.maintainance_due_date','<=',Carbon::parse($end_date));   
                }
                $assets_ids=$qry->get()->toArray();
               
                //sort to pick only lastes entry of asset
                $ids_of_assets=[];
                $maintainace_records=[];
                $maintainance_records_ids=[];
                foreach($assets_ids as $asset_data)
                {
                    if(!in_array($asset_data->asset_id,$ids_of_assets))
                    {
                       
                    $ids_of_assets[]=$asset_data->asset_id;
                    if($asset_data->maintainance_status==0)
                    {
                        $asset_data->status="Scheduled";
                    }
                    if($asset_data->maintainance_status==1)
                    {
                        $asset_data->status="In Progress";
                    }
                    if($asset_data->maintainance_status==2)
                    {
                        $asset_data->status="Completed";
                    }
                    if($asset_data->maintainance_status==4)
                    {
                        $asset_data->status="Cancelled";
                    }
                    if($asset_data->maintainance_status==3)
                    {
                        $asset_data->status="On Hold";
                    }
                    $maintainance_records_ids[]=$asset_data->main_record_id;
                    $asset_data=convertStdClassObjToArray($asset_data);
                    unset($asset_data['main_record_id']);
                    unset($asset_data['asset_id']);
                    unset($asset_data['maintainance_status']);
                    $maintainace_records[]=$asset_data;
                   
                    }
                }
                
                    
                   
                $report_title="Asset Maintainance Report";
                $table_headers=["Description","Serial No",
                    "Due Date","Completed" ,"Status"];
                     $width=100/count($table_headers);
                     $header_style= "width:".$width."%; background-color: #04AA6D;color: white;text-align: left";
                     $data_style="width:".$width."%;text-align: left;padding-left:7px;";
                   // $asset_inventory=$maintainance_report;
                    $asset_inventory= $maintainace_records;
                
                    $exclusion_fields=[];
                    
                    $report_date= Carbon::now();
                    $report_date= date('d-m-y H:i:s');
                    $view=view('customassetreport',compact('asset_inventory','report_date','image_url',
                    'table_headers','header_style','data_style','exclusion_fields','report_title'))->render();
                    //return $view;
                   $this->generatePDFFIle($view);
                break;
            case 4://depreciation of assets report
                $qry= DB::table('ar_asset_depreciation as t1')
                 ->join('ar_asset_inventory as t2','t2.id','=','t1.asset_id')
                 ->join('ar_depreciation_methods as t3','t1.depreciation_method_id','=','t3.id')
                //  ->selectRaw("t2.description,t2.serial_no,t2.grz_no,t1.depreciable_cost,t1.salvage_value,
                //  t1.asset_life,t1.depreciation_method_id,t1.depreciation_rate,
                //  DATE_FORMAT(t2.purchase_date ,'%d-%b-%Y') as purchase_date,t3.name as dep_method");
                 ->selectRaw("t2.description,t2.serial_no,t2.grz_no,t1.depreciable_cost,t1.salvage_value,
                 t1.asset_life,t1.depreciation_method_id,t1.depreciation_rate,
                 t2.purchase_date,t3.name as dep_method");
                 if($start_date)
                 {
                  
                     $qry->whereDate('t2.purchase_date','>=',Carbon::parse($start_date));
                     
                 }
                 if($end_date)
                 {
                    
                     $qry->whereDate('t2.purchase_date','<=',Carbon::parse($end_date));   
                 }
                 if($report_order && $report_order=="description")
                        {
                    $qry->orderBy('t2.description', 'desc');
                        }
                        if($report_order && $report_order=="salvagevalue")
                        {
                    $qry->orderBy('t1.salvage_value', 'desc');
                        }
                $assets=$qry->get();
                
                
                 $assets=convertStdClassObjToArray($assets);
                        
                 $depreciation_data=[];
                 foreach($assets as $asset)
                 {
                   
                   
                     $depreciation_details=(object)array(
                         "asset_life"=>$asset['asset_life'],
                         "depreciable_cost"=>$asset['depreciable_cost'],
                         "salvage_value"=>$asset['salvage_value'],
                         "date_acquired"=>$asset['purchase_date'],
                         "depreciation_rate"=>$asset['depreciation_rate']
 
                     );
                    
                     switch($asset['depreciation_method_id']){
                           case 4:
                            $current_val=calculatePercentageDepreciation($depreciation_details,date('Y-m-d'),1.5);
                            $depreciation_data[]=array(
                                "description"=>$asset['description'],
                                "serial_no"=>$asset['serial_no'],
                                "depreciable_cost"=>$asset['depreciable_cost'],
                                "salvage_value"=>$asset['salvage_value'],
                                //"depreciation_rate"=>$asset['depreciation_rate'],
                                "purchase_date"=>$asset['purchase_date'],
                                "current_value"=>$current_val,
                                "method"=>$asset['dep_method']
                            );
                             break;
                         case 3:
                           
                            $current_val=calculatePercentageDepreciation($depreciation_details,date('Y-m-d'),2);
                            $depreciation_data[]=array(
                                "description"=>$asset['description'],
                                "serial_no"=>$asset['serial_no'],
                                "depreciable_cost"=>$asset['depreciable_cost'],
                                "salvage_value"=>$asset['salvage_value'],
                                //"depreciation_rate"=>$asset['depreciation_rate'],
                                "purchase_date"=>$asset['purchase_date'],
                                "current_value"=>$current_val,
                                "method"=>$asset['dep_method']
                            );
                             break;
                         case 2:
                             $rate=$asset['depreciation_rate']/100;
                             $current_val=calculatePercentageDepreciation($depreciation_details,date('Y-m-d'),$rate);
                             $depreciation_data[]=array(
                                "description"=>$asset['description'],
                                "serial_no"=>$asset['serial_no'],
                                "depreciable_cost"=>$asset['depreciable_cost'],
                                "salvage_value"=>$asset['salvage_value'],
                                //"depreciation_rate"=>$asset['depreciation_rate'],
                                "purchase_date"=>$asset['purchase_date'],
                                "current_value"=>$current_val,
                                "method"=>$asset['dep_method']
                            );
                             break;
                         case 1:
                             $current_val=calculateStraightLineDepreciation($depreciation_details,date('Y-m-d'));
                             $depreciation_data[]=array(
                                 "description"=>$asset['description'],
                                 "serial_no"=>$asset['serial_no'],
                                 "depreciable_cost"=>$asset['depreciable_cost'],
                                 "salvage_value"=>$asset['salvage_value'],
                                 //"depreciation_rate"=>$asset['depreciation_rate'],
                                 "purchase_date"=>$asset['purchase_date'],
                                 "current_value"=>$current_val,
                                 "method"=>$asset['dep_method']
                             );
                             break;
                         default:
                             $current_val=calculateStraightLineDepreciation($depreciation_details,false,date('Y-m-d'));
                             $depreciation_data[]=array(
                                "description"=>$asset['description'],
                                "serial_no"=>$asset['serial_no'],
                                "depreciable_cost"=>$asset['depreciable_cost'],
                                "salvage_value"=>$asset['salvage_value'],
                                //"depreciation_rate"=>$asset['depreciation_rate'],
                                "purchase_date"=>$asset['purchase_date'],
                                "current_value"=>$current_val,
                                "method"=>$asset['dep_method']
                            );
                             break;
                     }
                 }
                   $report_title="Asset Depreciation Report";
                    $table_headers=["Description","Serial No","Depreciable Cost","Salvage Value",
                    "Purchase Date","Current Value","Method"];
                     $width=100/count($table_headers);
                     $header_style= "width:".$width."%; background-color: #04AA6D;color: white;text-align: left";
                     $data_style="width:".$width."%;text-align: left;padding-left:7px;";
                    $asset_inventory=$depreciation_data;
                    //dd($asset_inventory);
                    $exclusion_fields=["checkout_id","user_id"];
                    
                  
                    //$report_date= date('d-m-y H:i:s');
                    $report_date= date('d-M-Y H:i:s');

                    $view=view('customassetreport',compact('asset_inventory','report_date','image_url',
                    'table_headers','header_style','data_style','exclusion_fields','report_title'))->render();
                   $this->generatePDFFIle($view);
 
                 break;
             case 3://asset aggregation per staff
                 
                     $qry = DB::table('users as t1') 
                     ->selectRaw("t1.id,
                     CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.middlename),decrypt(t1.last_name)) as name");
                     $users=$qry->get();
                     if($report_order && $report_order=="firstname")
                        {
                    $qry->orderByRaw('decrypt(t1.first_name)', 'desc');
                        }
                        if($report_order && $report_order=="lastname")
                        {
                    $qry->orderByRaw('decrypt(t1.last_name)', 'desc');
                        }
                        if($report_order && $report_order=="middlename")
                        {
                    $qry->orderByRaw('decrypt(t1.middlename)', 'desc');
                        }
                     //$users_ids=convertStdClassObjToArray($qry->get());
                     $users_ids=convertStdClassObjToArray($users);
                   
                     $qry=DB::table('ar_asset_checkout_details as t1')
                         ->join('ar_asset_inventory as t2','t2.id','=','t1.asset_id')
                         ->where('checkout_status',1)
                         ->where('is_group_checkout',1)->selectRaw("t1.id as checkout_id,user_id,t2.description,
                         DATE_FORMAT(t1.checkout_date,'%d-%b-%Y') as checkout_date,
                         t2.serial_no,t2.grz_no");
                        
                         if($start_date)
                         {
                          
                             $qry->whereDate('t1.checkout_date','>=',Carbon::parse($start_date));
                             
                         }
                         if($end_date)
                         {
                            
                             $qry->whereDate('t1.checkout_date','<=',Carbon::parse($end_date));   
                         }
                    $group_checkouts=$qry->get();
                     $group_checkouts=convertStdClassObjToArray($group_checkouts);
                   
                     $group_transaction_breakdown=[];
                     $all_group_checkout_user_ids=[];
                     foreach($group_checkouts as $group_checkout)
                     {
                         $user_ids=$this->returnArrayFromStringArray($group_checkout['user_id']);
                         foreach($user_ids as $user_id)
                         {
                             if(!in_array($user_id,$all_group_checkout_user_ids))
                             {
                                 $all_group_checkout_user_ids[]=$user_id;
                             }
                             $user_details=$this->_search_array_by_value($users_ids,'id',$user_id);
 
                             $group_transaction_breakdown[]=array(
                                 "checkout_id"=>$group_checkout['checkout_id'],
                                 "user_id"   =>$user_details[0]['id'],
                                 "name" =>$user_details[0]['name'],
                                 "asset_description"=>$group_checkout["description"],
                                 "serial_no"=>$group_checkout["serial_no"],
                                 "grz_no"=>$group_checkout['grz_no'],
                                 "date_assigned"=>$group_checkout['checkout_date']
                             );
                            
 
                         }
                         
                         
                     }
                  
                     //dd($group_transaction_breakdown);
 
                     $qry=DB::table('users as t1')
                         ->join('ar_asset_checkout_details as t2','t1.id','=','t2.user_id')
                          ->where('checkout_status',1)
                         ->selectRaw("t1.id as user_id,CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.middlename),decrypt(t1.last_name)) as name");
                        
                         if($start_date)
                         {
                          
                             $qry->whereDate('t2.checkout_date','>=',Carbon::parse($start_date));
                             
                         }
                         if($end_date)
                         {
                            
                             $qry->whereDate('t2.checkout_date','<=',Carbon::parse($end_date));   
                         }
                        
                         if($report_order && $report_order=="firstname")
                         {
                     $qry->orderByRaw('decrypt(t1.first_name)', 'desc');
                         }
                         if($report_order && $report_order=="lastname")
                         {
                     $qry->orderByRaw('decrypt(t1.last_name)', 'desc');
                         }
                         if($report_order && $report_order=="middlename")
                         {
                     $qry->orderByRaw('decrypt(t1.middlename)', 'desc');
                         }
                        
                     $checkouts_user_ids=$qry->get();
                   
                     //$checkouts_user_ids=$users;
                     $checkouts_user_ids=convertStdClassObjToArray($checkouts_user_ids);
                     $qry=Db::table('ar_asset_checkout_details as t1')
                        ->join('ar_asset_inventory as t2','t2.id','=','t1.asset_id')
                        ->join('users as t3', 't3.id', '=', 't1.user_id')
                         ->selectRaw("t1.id as checkout_id,t1.user_id,t2.description as asset_description,
                         DATE_FORMAT(t1.checkout_date ,'%d-%b-%Y') as date_assigned,t2.serial_no,t2.grz_no,
                         CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.middlename),decrypt(t3.last_name)) as name");
                       
                         if($start_date)
                         {
                          
                             $qry->whereDate('t1.checkout_date','>=',Carbon::parse($start_date));
                             
                         }
                         if($end_date)
                         {
                            
                             $qry->whereDate('t1.checkout_date','<=',Carbon::parse($end_date));   
                         }
                         if($report_order && $report_order=="firstname")
                         {
                     $qry->orderByRaw('decrypt(t3.first_name)', 'desc');
                         }
                         if($report_order && $report_order=="lastname")
                         {
                     $qry->orderByRaw('decrypt(t3.last_name)', 'desc');
                         }
                         if($report_order && $report_order=="middlename")
                         {
                     $qry->orderByRaw('decrypt(t3.middlename)', 'desc');
                         }
                        // dd($qry->get());
                     $checkouts=$qry->get();
                     $checkouts_per_user=[];
                     $checkouts=convertStdClassObjToArray($checkouts);
                  
                     foreach($all_group_checkout_user_ids as $user_id_from_group)
                     {
                         $results=$this->_search_array_by_value($checkouts_user_ids,'user_id',$user_id_from_group);
                         if((count($results))==0)//if not in array
                         {
                             $checkouts_user_ids[]=array(
                                 "user_id"=>$user_id_from_group
                             );
                         }
                     }//combine group user ids with normal user ids
                    
                    
                     
                     foreach($checkouts_user_ids as $user )
                     {  
                         $user_results=[];
                         $results=$this->_search_array_by_value($checkouts,'user_id',$user['user_id']);
                        if(count($results)>0)
                        {
                         
                            //$results=$results[0];
                            foreach($results as $user_r)
                            {
                                $user_results[]=$user_r;
                            }

                        }
                
                         $results2=$this->_search_array_by_value($group_transaction_breakdown,'user_id',$user['user_id']);
                    
                         if(count($results2)>0)
                         {
                            // $results2=$results2[0];
                             foreach($results2 as $user_r)
                             {
                                 $user_results[]=$user_r;
                             }
                         }
                         //$combined_results=array_merge($results,$results2);
                        // dd($combined_results);
                         
                       
 
                        // $checkouts_per_user[$user['user_id']]=$combined_results;
                         $checkouts_per_user[$user['user_id'].",".$user['name']]=$user_results;
                     }
                   
                     //$checkouts_per_user
                     $table_headers=[
                         "Individual name","Asset Description","Date Assigned"
                     ];
                    //  dd($checkouts_per_user);
                     $width=100/count($table_headers);
                     $width=100/4;
                   
                     $header_style= "width:".$width."%; background-color: #04AA6D;color: white;text-align: left";
                     $data_style="width:".$width."%;text-align: left;padding-left:7px;";
                    $asset_inventory=$checkouts_per_user;
                    //dd($asset_inventory);
                    $exclusion_fields=["checkout_id","user_id"];
                    
                    $report_date= date('d-M-Y H:i:s');
                    $view=view('assetperstaffreport',compact('asset_inventory','report_date','image_url',
                    'table_headers','header_style','data_style','exclusion_fields'))->render();
                  
                   $this->generatePDFFIle($view);
                     
                 break;
            case 2:
                $qry = DB::table('ar_asset_inventory as t1');
                $fields_to_use=explode(',',$request->query('fields_to_use'));
               
               
                $column_match=[
                    "description"=>"description",
                    "category"=>"category_id",
                    "FrequencyofMaintainance"=>"maintainance_frequency",
                    "brand"=>"brand_id",
                    "model"=>"model_id",
                    "PurchaseFrom"=>"purchase_from",
                    "PurchaseDate"=>"purchase_date",
                    "cost"=>"cost",
                    "SerialNo"=>"serial_no",
                    "GrzNo"=>"grz_no",
                    "site"=>"site_id",
                    "location"=>"location_id",
                    "department"=>"department_id",
                    "status"=>"status_id"
                ];
              
               // return $column_match['']
                $inventory_columns=[];
               
                foreach ( $fields_to_use as $value) {
                   
                $inventory_columns[]=$column_match[$value];
                
                  }
                
                  $qry = DB::table('ar_asset_inventory as t1');
                  $select_raw_statement=[];
                  $table_headers=[];
                  if(in_array("description",$inventory_columns))
                  {
                      $select_raw_statement[]="t1.description";
                      $table_headers[]="Description";
                  }else{
                    $select_raw_statement[]="t1.description";
                    $table_headers[]="Description"; 
                  }
                  if(in_array("category_id",$inventory_columns))
                  {
                     
                      $qry->join('ar_asset_categories as t2', 't1.category_id', '=', 't2.id');
                      $select_raw_statement[]="t2.name as category_name";
                      $table_headers[]="Category Name";
                  }
                  if(in_array("brand_id",$inventory_columns))
                  {
                     
                      $qry->join('ar_asset_brands as t3', 't1.brand_id', '=', 't3.id');
                      $select_raw_statement[]="t3.name as brand_name";
                      $table_headers[]="Brand Name";
                  }
                  if(in_array("model_id",$inventory_columns))
                  {
                     
                      $qry->join('ar_asset_models as t4', 't1.model_id', '=', 't4.id');
                      $select_raw_statement[]="t4.name as model_name";
                      $table_headers[]="Model Name";
                  }
                  if(in_array("purchase_date",$inventory_columns))
                  {
                     
                      $select_raw_statement[]="DATE_FORMAT(t1.purchase_date,'%d-%b-%Y') as purchase_date";
                      $table_headers[]="Purchase Date";
                  }
                  if(in_array("purchase_from",$inventory_columns))
                  {
                     
                      $select_raw_statement[]="t1.purchase_from";
                      $table_headers[]="Purchase From";
                  }
                  if(in_array("cost",$inventory_columns))
                  {
                     
                      $select_raw_statement[]="t1.cost";
                      $table_headers[]="Acquisition cost";
                  }
                  if(in_array("serial_no",$inventory_columns))
                  {
                     
                      $select_raw_statement[]="t1.serial_no";
                      $table_headers[]="Serial No";
                  }
                  if(in_array("grz_no",$inventory_columns))
                  {
                     
                      $select_raw_statement[]="grz_no";
                      $table_headers[]="Grz No";

                  }
                  if(in_array("site_id",$inventory_columns))
                  {
                     
                      $select_raw_statement[]="t5.name as site_name";
                      $qry->join('ar_asset_sites as t5', 't1.site_id', '=', 't5.id');
                      $table_headers[]="Site ";
                      
                     
                  }
                  if(in_array("location_id",$inventory_columns))
                  {
                      $qry->join('ar_asset_locations as t6', 't1.location_id', '=', 't6.id');
                     
                      $select_raw_statement[]="t6.name as location_name";
                      $table_headers[]="Location";
                  }
                  if(in_array("department_id",$inventory_columns))
                  {
                      $qry->join('departments as t7', 't1.department_id', '=', 't7.id');
                     
                      $select_raw_statement[]="t7.name as department_name";
                      $table_headers[]="Department ";
                  }

                  if(in_array('status_id',$inventory_columns))
                  {
                     
                      $qry->join('ar_asset_statuses as t11','t1.status_id','t11.id');
                      $select_raw_statement[]="t11.name as status";
                      $table_headers[]="Status";
                  }
                 
                  $select_raw_statement=implode(",",$select_raw_statement);
                  $width=100/count($table_headers);
                  $header_style= "width:".$width."%; background-color: #04AA6D;color: white;text-align:left";
                  $data_style="width:".$width."%;text-align: left;padding-left:2px;";
                 
                  $exclusion_fields=[];
                 $qry->selectRaw($select_raw_statement);
                 $results=$qry->get();
                 $asset_inventory=convertStdClassObjToArray($results);
               
                 $report_title="Custom Asset Report ";
                 $report_date= date('d-M-Y H:i:s');
                 $view=view('customassetreport',compact('asset_inventory','report_date','image_url',
                 'table_headers','header_style','data_style','exclusion_fields','report_title'))->render();
                // return $view;
                $this->generatePDFFIle($view);
               

                break;
            case 1://assesory report all inventory items
                
                $other_headers=[
                    'purchase_from'=>array(
                        "header_name"=>"Purchase From",
                        "style"=>"width:20%; background-color: #04AA6D;color: white;text-align: left;",
                        "sql_statement"=>"t1.purchase_from",
                        "data_style"=>"width:20%;text-align: left;padding:5px;"
                    ),
                    'purchase_date'=>array(
                        "header_name"=>"Purchase Date",
                        "style"=>"width:20%; background-color: #04AA6D;color: white;text-align: left;",
                        "sql_statement"=>"t1.purchase_date",
                        "data_style"=>"width:20%;text-align: left;padding:5px;"
                    ),
                    'brand_name'=>array(
                        "header_name"=>"Brand",
                        "style"=>"width:20%; background-color: #04AA6D;color: white;text-align: left;",
                        "sql_statement"=>"t3.name as brand_name",
                        "data_style"=>"width:20%;text-align: left;padding:5px;"
                    ),
                    'model_name'=>array(
                        "header_name"=>"Model",
                        "style"=>"width:20%; background-color: #04AA6D;color: white;text-align: left;",
                        "sql_statement"=>"t4.name as model_name",
                        "data_style"=>"width:20%;text-align: left;padding:5px;"
                    ),
                    // 'sub_category_name'=>array(
                    //     "header_name"=>"Sub-Category",
                    //     "style"=>"width:20%; background-color: #04AA6D;color: white;text-align: left;",
                    //     "sql_statement"=>"t9.name as sub_category_name",
                    //     "data_style"=>"width:20%;text-align: left;padding:5px;"
                    // ),
                    'site_name'=>array(
                        "header_name"=>"Site",
                        "style"=>"width:20%; background-color: #04AA6D;color: white;text-align: left;",
                        "sql_statement"=>"t5.name as site_name",
                        "data_style"=>"width:20%;text-align: left;padding:5px;"
                    ),
                    'location_name'=>array(
                        "header_name"=>"Location",
                        "style"=>"width:20%; background-color: #04AA6D;color: white;text-align: left;",
                        "sql_statement"=>"t6.name as location_name",
                        "data_style"=>"width:20%;text-align: left;padding:5px;"
                    ),
                    'department_name'=>array(
                        "header_name"=>"Department",
                        "style"=>"width:20%; background-color: #04AA6D;color: white;text-align: left;",
                        "sql_statement"=>"t7.name as department_name",
                        "data_style"=>"width:20%;text-align: left;padding:5px;"
                    ),
                    'grz_no'=>array(
                        "header_name"=>"Grz No",
                        "style"=>"width:20%; background-color: #04AA6D;color: white;text-align: left;",
                        "sql_statement"=>"t1.grz_no",
                        "data_style"=>"width:20%;text-align: left;padding:5px;"
                    ),
                    'cost'=>array(
                        "header_name"=>"Purchase Cost",
                        "style"=>"width:20%; background-color: #04AA6D;color: white;text-align: left;",
                        "sql_statement"=>"t1.cost",
                        "data_style"=>"width:20%;text-align: left;padding:5px;"
                    ),
                ];
             
                $headers=[
                    //must math db column names--header keys
                    "description"=>array("header_name"=>"Asset Descitpion",
                                    "style"=>"width:30%; background-color: #04AA6D;color: white;text-align: left;",
                                    "sql_statement"=>"t1.description",
                                    "data_style"=>"width:30%;text-align: left;padding:5px;"
                    ),
                  
            'sub_category_name'=>array(
                "header_name"=>"Sub-Category",
                "style"=>"width:20%; background-color: #04AA6D;color: white;text-align: left;",
                "sql_statement"=>"t9.name as sub_category_name",
                "data_style"=>"width:20%;text-align: left;padding:5px;"
            ),
            "serial_no"=>array("header_name"=>"Serial No",
            "style"=>"width:20%; background-color: #04AA6D;color: white;text-align: left;",
            "sql_statement"=>"t1.serial_no",
            "data_style"=>"width:30%;text-align: left;padding:5px;"
            ),
            "status"=>
                    array(
                    "header_name"=>"Status",
                    "style"=>"width:20%; background-color: #04AA6D;color: white;text-align: left;margin-left:12px;",
                    "sql_statement"=>"t8.name as status",
                    "data_style"=>"width:30%;text-align: left;padding:5px;"
                ),
            

                    ];

                    if($start_date || $end_date)
                    {
                        $headers["purchase_date"]=array(

                            "header_name"=>"Purchase Date",
                            "style"=>"width:20%; background-color: #04AA6D;color: white;text-align: left;",
                            "sql_statement"=>"t1.purchase_date",
                            "data_style"=>"width:30%;text-align: left;padding:5px;"
                        );
                    }
                 $sql_select=[];
              foreach ($headers as $key => $value) {
                 $sql_select[]=$value['sql_statement'];
              }
             
              
            
                if(array_key_exists($report_order,$other_headers))
                {
                   
                    $headers[$report_order]=$other_headers[$report_order];
                    $sql_select[]=$other_headers[$report_order]['sql_statement'];
                }
               
               
        //         foreach($headers as $main_key=>$header)
        //  {
        //         dump($header[$]);
        //     }

                $qry = DB::table('ar_asset_inventory as t1')
                ->join('ar_asset_categories as t2', 't1.category_id', '=', 't2.id')
                ->join('ar_asset_subcategories as t9', 't1.sub_category_id','t9.id')
                ->leftjoin('ar_asset_brands as t3', 't1.brand_id', '=', 't3.id')
                ->leftjoin('ar_asset_models as t4', 't1.model_id', '=', 't4.id')
                ->join('ar_asset_sites as t5', 't1.site_id', '=', 't5.id')
                ->leftjoin('ar_asset_locations as t6', 't1.location_id', '=', 't6.id')
                ->leftjoin('departments as t7', 't1.department_id', '=', 't7.id')
                ->join('ar_asset_statuses as t8', 't1.status_id', '=', 't8.id')
                ->selectRaw(implode(',',$sql_select));
                
               // ->leftJoin('ar_asset_maintainance as t9', 't1.id', '=', 't9.asset_id')
                //->leftJoin('ar_asset_repairs as t10', 't1.id', '=', 't10.asset_id')
                // ->selectRaw("t1.description,t1.serial_no,t1.grz_no,t1.id as record_id,t1.id as asset_id, t2.name as category_name, t3.name as brand_name, t4.name as model_name,t5.name as site_name,
                //      t6.name as location_name,t7.name as department_name, t8.name as record_status,t1.purchase_date,
                //      CONCAT_WS('-',t1.description,t1.grz_no) as desc_grz_no,purchase_from");
                    //  $qry = DB::table('ar_asset_inventory as t1')
                    //  ->join('ar_asset_categories as t2', 't1.category_id', '=', 't2.id')
                    //  ->selectRaw('t1.id as record_id,t1.id as asset_id, t2.name as category_name');
                    
                   
                     $filter_columns=[
                        ["id"=>"description","text"=>"Description"],
                        ["id"=>"category_name","text"=>"Category"],
                        ["id"=>"brand_name","text"=>"Brand"],
                        ["id"=>"model_name","text"=>"Model"],
                        ["id"=>"purchase_from","text"=>"Purchase From"],
                        ["id"=>"purchase_date","text"=>"Purchase Date"],
                        ["id"=>"cost","text"=>"Cost"],
                        ["id"=>"serial_no","text"=>"Serial No"],
                        ["id"=>"grz_no","text"=>"Grz No"],
                        ["id"=>"site","text"=>"Site"],
                        ["id"=>"location","text"=>"Location"],
                        ["id"=>"department","text"=>"Department"],
                     ];
                //dates

                if($start_date && $end_date)
                {
                 // dd(Carbon::parse($start_date));
                   // $today = Carbon::createFromFormat('Y-m-d',  $start_date); 
                   // dd($today);
                    $qry->whereBetween('t1.purchase_date',[$start_date,$end_date]);
                    
                }else if($start_date)
                {
                    $qry->whereDate('t1.purchase_date','>=',Carbon::parse($start_date));
                }else if($end_date)
                {
                   
                    $qry->whereDate('t1.purchase_date','<=',Carbon::parse($end_date));   
                }

                //ordering
                    
                if($report_order && $report_order=="description")
                {
                    $qry->orderBy('description', 'desc');
                }
                if($report_order && $report_order=="category_name")
                {
                    $qry->orderBy('t2.name', 'desc');
                }
                if($report_order && $report_order=="brand_name")
                {
                    $qry->orderBy('t3.name', 'desc');
                }
                if($report_order && $report_order=="model_name")
                {
                    $qry->orderBy('t4.name', 'desc');
                }
                if($report_order && $report_order=="purchase_from")
                {
                    $qry->orderBy('purchase_from', 'desc');
                }
                if($report_order && $report_order=="purchase_date")
                {
                    $qry->orderBy('t1.purchase_date', 'desc');
                }
                if($report_order && $report_order=="cost")
                {
                    $qry->orderBy('t1.cost', 'desc');
                }
                if($report_order && $report_order=="serial_no")
                {
                    $qry->orderBy('t1.serial_no', 'desc');
                }
                if($report_order && $report_order=="grz_no")
                {
                    $qry->orderBy('t1.grz_no', 'desc');
                }
                if($report_order && $report_order=="site")
                {
                    $qry->orderBy('t5.name', 'desc');
                }
                if($report_order && $report_order=="location")
                {
                    $qry->orderBy('t6.name', 'desc');
                }
                if($report_order && $report_order=="department")
                {
                    
                 
                    $qry->orderBy('t7.name', 'desc');
                }

                

                
                $results = $qry->get();
                $asset_inventory=convertStdClassObjToArray($results);
               
        
            
                $report_date= date('d-M-Y H:i:s');
                $report_name= Db::table('asset_report_types')->where('id',1)->value('name');

                $view=view('assesory_report',compact('asset_inventory','report_date','image_url','headers','report_name'))->render();
               
              
                $this->generatePDFFIle($view);
              
               

                break;
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
   
    public function getAssetReport(Request $request)
    {
       
        $url=url('/');
        $image_url=$url.'/backend/public/moge_logo.png';
        $report_type= $request->query('report_type');
        $pdf_url = $url."/assetregister/generateAssetsReport?report_type=".$report_type;
        $res=array();
        $report_order=$request->query('report_order');
        $start_date=$request->query('start_date');
        $end_date=$request->query('end_date');
       
        if($report_order)
        {
            $pdf_url.="&report_order=".$report_order;
        }
        if($start_date)
        {
            $pdf_url.="&start_date=".$start_date; 
        }
        if($end_date)
        {
            $pdf_url.="&end_date=".$end_date; 
        }
    
       
        switch($report_type)
        {   
            case 12:
            case 11:
               $pdf_url = $url."/assetregister/generateAssetsReport?report_type=".$report_type;
               $res = array(
                'success' => true,
                'message' => "Report Generated",
                'results' => $pdf_url
                );
                //return Excel::download(new ConsolidatedAssetsData(),'.ConsolidatedAssetsData.xlsx');
                break;
            case 10:
                $qry=DB::table('ar_asset_archive as t1')
                ->join('ar_asset_inventory as t2','t2.id','t1.asset_id')
                ->where('module_id',350)
                 ->selectRaw('t2.description,t2.serial_no,t2.grz_no,t1.archive_date');
                 if($start_date)
                 {
                     $qry->whereDate('t1.archive_date','>=',Carbon::parse($start_date));    
                 }
                 if($end_date)
                 { 
                     $qry->whereDate('t1.archive_date','<=',Carbon::parse($end_date));   
                 }
                 
                if($report_order && $report_order=="description")
                {
            $qry->orderBy('t2.description', 'desc');
                }
                if($report_order && $report_order=="archive_date")
                {
            $qry->orderBy('t1.archive_date', 'desc');
                }
                $results=$qry->get();
                 $results=convertStdClassObjToArray($results);
                 if((count($results))>0)
                 {
                
                     $res = array(
                         'success' => true,
                         'message' => "Report Generated",
                         'results' => $pdf_url
                 );
                 }else{
                     $res = array(
                         'success' => false,
                         'message' => "Requested Report Data Not Available",
                         'results' => 0 
                     );  
                 }          
                break;
            case 9://asset damged
                $qry=DB::table('ar_asset_loss_damage_details as t1')
                ->join('ar_asset_inventory as t2','t2.id','t1.asset_id')
                ->where('module_id',350)
                ->where('lost_damaged_id',5)
                
                 ->selectRaw("t2.description,t2.serial_no,t2.grz_no,DATE_FORMAT(t1.loss_damage_date,'%d-%b-%Y') as loss_date");
                 if($start_date)
                 {
                     $qry->whereDate('t1.loss_damage_date','>=',Carbon::parse($start_date));    
                 }
                 if($end_date)
                 { 
                     $qry->whereDate('t1.loss_damage_date','<=',Carbon::parse($end_date));   
                 }
                 
                 if($report_order && $report_order=="description")
                 {
             $qry->orderBy('t2.description', 'desc');
                 }
                 if($report_order && $report_order=="loss_damage_date")
                 {
             $qry->orderBy('t1.loss_damage_date', 'desc');
                 }
                 $results=$qry->get();
                 $results=convertStdClassObjToArray($results);
                 if((count($results))>0)
                 {
                
                     $res = array(
                         'success' => true,
                         'message' => "Report Generated",
                         'results' => $pdf_url
                 );
                 }else{
                     $res = array(
                         'success' => false,
                         'message' => "Requested Report Data Not Available",
                         'results' => 0 
                     );  
                 }          
                break;
            case 8://asset loss
                $qry=DB::table('ar_asset_loss_damage_details as t1')
                ->join('ar_asset_inventory as t2','t2.id','t1.asset_id')
                ->where('lost_damaged_id',4)
                ->where('module_id',350)
               
                 ->selectRaw("t2.description,t2.serial_no,DATE_FORMAT(t1.loss_damage_date,'%d-%b-%Y') as loss_date");
                 
                 if($start_date)
                 {
                     $qry->whereDate('t2.loss_damage_date','>=',Carbon::parse($start_date));    
                 }
                 if($end_date)
                 { 
                     $qry->whereDate('t1.loss_damage_date','<=',Carbon::parse($end_date));   
                 }
                if($report_order && $report_order=="description")
                {
            $qry->orderBy('t2.description', 'desc');
                }
                if($report_order && $report_order=="loss_damage_date")
                {
            $qry->orderBy('t1.loss_damage_date', 'desc');
                }
                $results=$qry->get();
                 $results=convertStdClassObjToArray($results);
                 if((count($results))>0)
                 {
                
                     $res = array(
                         'success' => true,
                         'message' => "Report Generated",
                         'results' => $pdf_url
                 );
                 }else{
                     $res = array(
                         'success' => false,
                         'message' => "Requested Report Data Not Available",
                         'results' => 0 
                     );  
                 }          

                break;
            case 7://asset write off

                $qry=DB::table('ar_asset_write_off_details as t1')
                ->join('ar_asset_inventory as t2','t2.id','t1.asset_id')
                ->where('module_id',350)
                 ->selectRaw("t2.description,t2.serial_no,
                 DATE_FORMAT(t1.date_written_off,'%d-%b-%Y') as date_written_off,t1.write_off_reason");
                 if($start_date)
                 {
                     $qry->whereDate('t1.date_written_off','>=',Carbon::parse($start_date));    
                 }
                 if($end_date)
                 { 
                     $qry->whereDate('t1.date_written_off','<=',Carbon::parse($end_date));   
                 }
                 if($report_order && $report_order=="description")
                 {
             $qry->orderBy('t2.description', 'desc');
                 }
                 if($report_order && $report_order=="date_written_off")
                 {
             $qry->orderBy('t1.date_written_off', 'desc');
                 }
                 $results=$qry->get();
                 $results=convertStdClassObjToArray($results);
                 if((count($results))>0)
                 {
                
                     $res = array(
                         'success' => true,
                         'message' => "Report Generated",
                         'results' => $pdf_url
                 );
                 }else{
                     $res = array(
                         'success' => false,
                         'message' => "Requested Report Data Not Available",
                         'results' => 0 
                     );  
                 }          

                break;
            case 6://asset repair
                //o is schedlued,1 dispatched,2 completed
                $qry=DB::table('ar_asset_repairs as t1')
                    ->join('ar_asset_inventory as t2','t2.id','t1.asset_id')
                   ->where('repair_status','<',2)//scheduled
                   ->where('module_id',350)
                    //->where('maintainance_status',1)//dispatched
                    ->selectRaw("t2.description,t2.serial_no,t2.grz_no,
                    DATE_FORMAT(t1.scheduled_repair_date,'%d-%b-%Y') as scheduled_repair_date,repair_status");
                  

                    if($start_date)
                    {
                        $qry->whereDate('t1.scheduled_repair_date','>=',Carbon::parse($start_date));    
                    }
                    if($end_date)
                    { 
                        $qry->whereDate('t1.scheduled_repair_date','<=',Carbon::parse($end_date));   
                    }
                    if($report_order && $report_order=="description")
                    {
                $qry->orderBy('t2.description', 'desc');
                    }
                    if($report_order && $report_order=="scheduled_repair_date")
                    {
                $qry->orderBy('t1.scheduled_repair_date', 'desc');
                    }
                   $results=$qry->get();
                    $results=convertStdClassObjToArray($results);
                    if((count($results))>0)
                    {
                   
                        $res = array(
                            'success' => true,
                            'message' => "Report Generated",
                            'results' => $pdf_url
                    );
                    }else{
                        $res = array(
                            'success' => false,
                            'message' => "Requested Report Data Not Available",
                            'results' => 0 
                        );  
                    }          
            case 5://asset maintainance
                //o is schedlued,1 dispatched,2 completed
                    $qry=DB::table('ar_asset_maintainance as t1')
                    ->join('ar_asset_inventory as t2','t2.id','t1.asset_id')
                    ->where('module_id',350)
                    ->selectRaw('asset_id,t1.id as main_record_id,t2.description,t2.serial_no,t1.maintainance_due_date,
                    date_maintainance_completed,maintainance_status')
                    ->orderBy('t1.created_at','ASC');
                    if($report_order && $report_order=="description")
                    {
                    $qry->orderBy('t2.description', 'desc');
                    }
                    if($report_order && $report_order=="maintainance_due_date")
                    {
                    $qry->orderBy('t1.maintainance_due_date', 'desc');
                    }
                    if($start_date)
                    {
                     
                        $qry->whereDate('t1.maintainance_due_date','>=',Carbon::parse($start_date));
                        
                    }
                    if($end_date)
                    {
                       
                        $qry->whereDate('t1.maintainance_due_date','<=',Carbon::parse($end_date));   
                    }
                    $assets_ids=$qry->get()->toArray();
                    //sort to pick only lastes entry of asset
                    $ids_of_assets=[];
                    $maintainace_records=[];
                    $maintainance_records_ids=[];
                    foreach($assets_ids as $asset_data)
                    {
                        if(!in_array($asset_data->asset_id,$ids_of_assets))
                        {
                        $ids_of_assets[]=$asset_data->asset_id;
                        if($asset_data->maintainance_status==0)
                        {
                            $asset_data->status="Scheduled";
                        }
                        if($asset_data->maintainance_status==1)
                        {
                            $asset_data->status="Under Maintainance";
                        }
                        if($asset_data->maintainance_status==2)
                        {
                            $asset_data->status="Maintainance Completed";
                        }
                        $maintainance_records_ids[]=$asset_data->main_record_id;
                        $asset_data=convertStdClassObjToArray($asset_data);
                        unset($asset_data['main_record_id']);
                        unset($asset_data['asset_id']);
                        unset($asset_data['maintainance_status']);
                        $maintainace_records[]=$asset_data;
                       
                        }
                    }
                    if((count( $maintainace_records))>0)
                    {
                   
                        $res = array(
                            'success' => true,
                            'message' => "Report Generated",
                            'results' => $pdf_url
                    );
                    }else{
                        $res = array(
                            'success' => false,
                            'message' => "Requested Report Data Not Available",
                            'results' => 0 
                        );  
                    }          
                   
                break;
            case 4://depreciation of assets report
                $qry= DB::table('ar_asset_depreciation as t1')
                ->join('ar_asset_inventory as t2','t2.id','=','t1.asset_id')
                ->join('ar_depreciation_methods as t3','t1.depreciation_method_id','=','t3.id')
                ->where('module_id',350)
                ->selectRaw("t2.description,t2.serial_no,t2.grz_no,t1.depreciable_cost,t1.salvage_value,
                t1.asset_life,t1.depreciation_method_id,t1.depreciation_rate,
                DATE_FORMAT(t2.purchase_date ,'%d-%b-%Y') as purchase_date,t3.name as dep_method");
                if($start_date)
                {
                 
                    $qry->whereDate('t2.purchase_date','>=',Carbon::parse($start_date));
                    
                }
                if($end_date)
                {
                   
                    $qry->whereDate('t2.purchase_date','<=',Carbon::parse($end_date));   
                }
                if($report_order && $report_order=="description")
                       {
                   $qry->orderBy('t2.description', 'desc');
                       }
                       if($report_order && $report_order=="salvagevalue")
                       {
                   $qry->orderBy('t1.salvage_value', 'desc');
                       }
               $assets=$qry->get();
               
               
              
                 $asset_inventory=convertStdClassObjToArray($assets);
                if((count($asset_inventory))>0)
                {
                   // $pdf_url.="&fields_to_use=".implode(',',$fields_to_use);
                    $res = array(
                        'success' => true,
                        'message' => "Report Generated",
                        'results' => $pdf_url
                );
                }else{
                    $res = array(
                        'success' => false,
                        'message' => "Requested Report Data Not Available",
                        'results' => 0 
                    );  
                }

                

                break;
            case 3://asset aggregation per staff
                
                     
                $qry = DB::table('users as t1') 
                ->selectRaw("t1.id,
                CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.middlename),decrypt(t1.last_name)) as name");
                if($report_order && $report_order=="firstname")
                   {
               $qry->orderByRaw('decrypt(t1.first_name)', 'desc');
                   }
                   if($report_order && $report_order=="lastname")
                   {
               $qry->orderByRaw('decrypt(t1.last_name)', 'desc');
                   }
                   if($report_order && $report_order=="middlename")
                   {
               $qry->orderByRaw('decrypt(t1.middlename)', 'desc');
                   }
                $users_ids=convertStdClassObjToArray($qry->get());
              
                $qry=DB::table('ar_asset_checkout_details as t1')
                    ->join('ar_asset_inventory as t2','t2.id','=','t1.asset_id')
                    ->where('checkout_status',1)
                    ->where('module_id',350)
                    ->where('is_group_checkout',1)->selectRaw("t1.id as checkout_id,user_id,t2.description,
                    DATE_FORMAT(t1.checkout_date,'%d-%b-%Y') as checkout_date,
                    t2.serial_no,t2.grz_no");
                   
                    if($start_date)
                    {
                     
                        $qry->whereDate('t1.checkout_date','>=',Carbon::parse($start_date));
                        
                    }
                    if($end_date)
                    {
                       
                        $qry->whereDate('t1.checkout_date','<=',Carbon::parse($end_date));   
                    }
               $group_checkouts=$qry->get();
                $group_checkouts=convertStdClassObjToArray($group_checkouts);
                $group_transaction_breakdown=[];
                $all_group_checkout_user_ids=[];
                foreach($group_checkouts as $group_checkout)
                {
                    $user_ids=$this->returnArrayFromStringArray($group_checkout['user_id']);
                    foreach($user_ids as $user_id)
                    {
                        if(!in_array($user_id,$all_group_checkout_user_ids))
                        {
                            $all_group_checkout_user_ids[]=$user_id;
                        }
                        $user_details=$this->_search_array_by_value($users_ids,'id',$user_id);

                        $group_transaction_breakdown[]=array(
                            "checkout_id"=>$group_checkout['checkout_id'],
                            "user_id"   =>$user_details[0]['id'],
                            "name" =>$user_details[0]['name'],
                            "asset_description"=>$group_checkout["description"],
                            "serial_no"=>$group_checkout["serial_no"],
                            "grz_no"=>$group_checkout['grz_no'],
                            "date_assigned"=>$group_checkout['checkout_date']
                        );
                       

                    }
                    
                    
                }
                //dd($group_transaction_breakdown);

                $qry=DB::table('users as t1')
                    ->join('ar_asset_checkout_details as t2','t1.id','=','t2.user_id')
                     ->where('checkout_status',1)
                    ->selectRaw("t1.id as user_id,CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.middlename),decrypt(t1.last_name)) as name");
                   
                    if($start_date)
                    {
                     
                        $qry->whereDate('t2.checkout_date','>=',Carbon::parse($start_date));
                        
                    }
                    if($end_date)
                    {
                       
                        $qry->whereDate('t2.checkout_date','<=',Carbon::parse($end_date));   
                    }
                   
                    if($report_order && $report_order=="firstname")
                    {
                $qry->orderByRaw('decrypt(t1.first_name)', 'desc');
                    }
                    if($report_order && $report_order=="lastname")
                    {
                $qry->orderByRaw('decrypt(t1.last_name)', 'desc');
                    }
                    if($report_order && $report_order=="middlename")
                    {
                $qry->orderByRaw('decrypt(t1.middlename)', 'desc');
                    }
                $checkouts_user_ids=$qry->get();
                $checkouts_user_ids=convertStdClassObjToArray($checkouts_user_ids);
                $qry=Db::table('ar_asset_checkout_details as t1')
                   ->join('ar_asset_inventory as t2','t2.id','=','t1.asset_id')
                   ->join('users as t3', 't3.id', '=', 't1.user_id')
                    ->selectRaw("t1.id as checkout_id,t1.user_id,t2.description as asset_description,
                    DATE_FORMAT(t1.checkout_date ,'%d-%b-%Y') as date_assigned,t2.serial_no,t2.grz_no,
                    CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.middlename),decrypt(t3.last_name)) as name");
                    if($start_date)
                    {
                     
                        $qry->whereDate('t1.checkout_date','>=',Carbon::parse($start_date));
                        
                    }
                    if($end_date)
                    {
                       
                        $qry->whereDate('t1.checkout_date','<=',Carbon::parse($end_date));   
                    }
                    if($report_order && $report_order=="firstname")
                    {
                $qry->orderByRaw('decrypt(t3.first_name)', 'desc');
                    }
                    if($report_order && $report_order=="lastname")
                    {
                $qry->orderByRaw('decrypt(t3.last_name)', 'desc');
                    }
                    if($report_order && $report_order=="middlename")
                    {
                $qry->orderByRaw('decrypt(t3.middlename)', 'desc');
                    }
                $checkouts=$qry->get();
                $checkouts_per_user=[];
                $checkouts=convertStdClassObjToArray($checkouts);
                foreach($all_group_checkout_user_ids as $user_id_from_group)
                {
                    $results=$this->_search_array_by_value($checkouts_user_ids,'user_id',$user_id_from_group);
                    if((count($results))==0)
                    {
                        $checkouts_user_ids[]=array(
                            "user_id"=>$user_id_from_group
                        );
                    }
                }//combine group user ids with normal user ids
               
               

                foreach($checkouts_user_ids as $user )
                {  
                    $user_results=[];
                    $results=$this->_search_array_by_value($checkouts,'user_id',$user['user_id']);
                   if(count($results)>0)
                   {
                       //$results=$results[0];
                       foreach($results as $user_r)
                       {
                           $user_results[]=$user_r;
                       }
                   }
           
                    $results2=$this->_search_array_by_value($group_transaction_breakdown,'user_id',$user['user_id']);
               
                    if(count($results2)>0)
                    {
                       // $results2=$results2[0];
                        foreach($results2 as $user_r)
                        {
                            $user_results[]=$user_r;
                        }
                    }
                    //$combined_results=array_merge($results,$results2);
                   // dd($combined_results);
                    
                  

                   // $checkouts_per_user[$user['user_id']]=$combined_results;
                    $checkouts_per_user[$user['user_id'].",".$user['name']]=$user_results;
                }
              
                    if((count($checkouts_per_user))>0)
                    {
                   
                        $res = array(
                            'success' => true,
                            'message' => "Report Generated",
                            'results' => $pdf_url
                    );
                    }else{
                        $res = array(
                            'success' => false,
                            'message' => "Requested Report Data Not Available",
                            'results' => 0 
                        );  
                    }            
                break;
            case 2://custom asset report
                $qry = DB::table('ar_asset_inventory as t1')->where('module_id',350);
                $fields_to_use=explode(',',$request->query('fields_to_use'));
               
              
                $column_match=[
                    "description"=>"description",
                    "category"=>"category_id",
                    "FrequencyofMaintainance"=>"maintainance_frequency",
                    "brand"=>"brand_id",
                    "model"=>"model_id",
                    "PurchaseFrom"=>"purchase_from",
                    "PurchaseDate"=>"purchase_date",
                    "cost"=>"cost",
                    "SerialNo"=>"serial_no",
                    "GrzNo"=>"grz_no",
                    "site"=>"site_id",
                    "location"=>"location_id",
                    "department"=>"department_id",
                    "status"=>"status_id"
                ];
              
               // return $column_match['']
                $inventory_columns=[];
                foreach ( $fields_to_use as $value) {
                    //echo "$value <br>";
                $inventory_columns[]=$column_match[$value];
                  }

                  $qry = DB::table('ar_asset_inventory as t1')->where('module_id',350);
                  $select_raw_statement=[];
                  $table_headers=[];
                  if(in_array("description",$inventory_columns))
                  {
                      $select_raw_statement[]="t1.description";
                      $table_headers[]="Description";
                  }
                  if(in_array("category_id",$inventory_columns))
                  {
                     
                      $qry->join('ar_asset_categories as t2', 't1.category_id', '=', 't2.id');
                      $select_raw_statement[]="t2.name as category_name";
                      $table_headers[]="Category Name";
                  }
                  if(in_array("brand_id",$inventory_columns))
                  {
                     
                      $qry->join('ar_asset_brands as t3', 't1.brand_id', '=', 't3.id');
                      $select_raw_statement[]="t3.name as brand_name";
                      $table_headers[]="Brand Name";
                  }
                  if(in_array("model_id",$inventory_columns))
                  {
                     
                      $qry->join('ar_asset_models as t4', 't1.model_id', '=', 't4.id');
                      $select_raw_statement[]="t4.name as model_name";
                      $table_headers[]="Model Name";
                  }
                  if(in_array("purchase_date",$inventory_columns))
                  {
                     
                      $select_raw_statement[]="t1.purchase_date";
                      $table_headers[]="Purchase Date";
                  }
                  if(in_array("purchase_from",$inventory_columns))
                  {
                     
                      $select_raw_statement[]="t1.purchase_from";
                      $table_headers[]="Purchase From";
                  }
                  if(in_array("cost",$inventory_columns))
                  {
                     
                      $select_raw_statement[]="t1.cost";
                      $table_headers[]="Acquisition cost";
                  }
                  if(in_array("serial_no",$inventory_columns))
                  {
                     
                      $select_raw_statement[]="t1.serial_no";
                      $table_headers[]="Serial No";
                  }
                  if(in_array("grz_no",$inventory_columns))
                  {
                     
                      $select_raw_statement[]="grz_no";
                      $table_headers[]="Grz No";

                  }
                  if(in_array("site_id",$inventory_columns))
                  {
                     
                      $select_raw_statement[]="t5.name as site_name";
                      $qry->join('ar_asset_sites as t5', 't1.site_id', '=', 't5.id');
                      $table_headers[]="Site name";
                      
                     
                  }
                  if(in_array("location_id",$inventory_columns))
                  {
                      $qry->join('ar_asset_locations as t6', 't1.location_id', '=', 't6.id');
                     
                      $select_raw_statement[]="t6.name as location_name";
                      $table_headers[]="Location Name";
                  }
                  if(in_array("department_id",$inventory_columns))
                  {
                      $qry->join('departments as t7', 't1.department_id', '=', 't7.id');
                     
                      $select_raw_statement[]="t7.name as department_name";
                      $table_headers[]="Department Name";
                  }

                  $select_raw_statement=implode(",",$select_raw_statement);
                  $width=100/count($table_headers);
                  $header_style= "{width:".$width."%; background-color: #04AA6D;;color: white;text-align: left}";
                  $data_style="width:".$width."%;text-align: left;padding-left:7px;";
                $qry->selectRaw($select_raw_statement);
                 $results=$qry->get();
                 $asset_inventory=convertStdClassObjToArray($results);
                 if((count($asset_inventory))>0)
                {
                    $pdf_url.="&fields_to_use=".implode(',',$fields_to_use);
                    $res = array(
                        'success' => true,
                        'message' => "Report Generated",
                        'results' => $pdf_url
                );
                }else{
                    $res = array(
                        'success' => false,
                        'message' => "Requested Report Data Not Available",
                        'results' => 0 
                    );  
                }




             

                break;
            case 1://assesory report all inventory items
             
                $qry = DB::table('ar_asset_inventory as t1')
                ->join('ar_asset_categories as t2', 't1.category_id', '=', 't2.id')
                ->leftjoin('ar_asset_brands as t3', 't1.brand_id', '=', 't3.id')
                ->leftjoin('ar_asset_models as t4', 't1.model_id', '=', 't4.id')
                ->leftjoin('ar_asset_sites as t5', 't1.site_id', '=', 't5.id')
                ->leftjoin('ar_asset_locations as t6', 't1.location_id', '=', 't6.id')
                ->leftjoin('departments as t7', 't1.department_id', '=', 't7.id')
                ->join('ar_asset_statuses as t8', 't1.status_id', '=', 't8.id')
                ->where('module_id',350)
                // ->Join('ar_asset_maintainance as t9', 't1.id', '=', 't9.asset_id')
                // ->Join('ar_asset_repairs as t10', 't1.id', '=', 't10.asset_id')
                ->selectRaw("t1.*, t1.id as record_id,t1.id as asset_id, t2.name as category_name, t3.name as brand_name, t4.name as model_name,t5.name as site_name,
                     t6.name as location_name,t7.name as department_name, t8.name as record_status,t1.purchase_date,
                     CONCAT_WS('-',t1.description,t1.grz_no) as desc_grz_no");
                     $filter_columns=[
                        ["id"=>"description","text"=>"Description"],
                        ["id"=>"category_name","text"=>"Category"],
                        ["id"=>"brand_name","text"=>"Brand"],
                        ["id"=>"model_name","text"=>"Model"],
                        ["id"=>"purchase_from","text"=>"Purchase From"],
                        ["id"=>"purchase_date","text"=>"Purchase Date"],
                        ["id"=>"cost","text"=>"Cost"],
                        ["id"=>"serial_no","text"=>"Serial No"],
                        ["id"=>"grz_no","text"=>"Grz No"],
                        ["id"=>"site","text"=>"Site"],
                        ["id"=>"location","text"=>"Location"],
                        ["id"=>"department","text"=>"Department"],
                     ];
                //dates

                if($start_date)
                {
                 
                    $qry->whereDate('t1.purchase_date','>=',Carbon::parse($start_date));
                    
                }
                if($end_date)
                {
                   
                    $qry->whereDate('t1.purchase_date','<=',Carbon::parse($end_date));   
                }

                //ordering
                    
                if($report_order && $report_order=="description")
                {
                    $qry->orderBy('description', 'desc');
                }
                if($report_order && $report_order=="category_name")
                {
                    $qry->orderBy('t2.name', 'desc');
                }
                if($report_order && $report_order=="brand_name")
                {
                    $qry->orderBy('t3.name', 'desc');
                }
                if($report_order && $report_order=="model_name")
                {
                    $qry->orderBy('t4.name', 'desc');
                }
                if($report_order && $report_order=="purchase_from")
                {
                    $qry->orderBy('t1.purchase_from', 'desc');
                }
                if($report_order && $report_order=="purchase_date")
                {
                    $qry->orderBy('t1.purchase_date', 'desc');
                }
                if($report_order && $report_order=="cost")
                {
                    $qry->orderBy('t1.cost', 'desc');
                }
                if($report_order && $report_order=="serial_no")
                {
                    $qry->orderBy('t1.serial_no', 'desc');
                }
                if($report_order && $report_order=="grz_no")
                {
                    $qry->orderBy('t1.grz_no', 'desc');
                }
                if($report_order && $report_order=="site")
                {
                    $qry->orderBy('t5.name', 'desc');
                }
                if($report_order && $report_order=="location")
                {
                    $qry->orderBy('t6.name', 'desc');
                }
                if($report_order && $report_order=="department")
                {
                 
                    $qry->orderBy('t7.name', 'desc');
                }

                

                
                $results = $qry->get();
                $asset_inventory=convertStdClassObjToArray($results);
              

                if((count($asset_inventory))>0)
                {
                    $res = array(
                        'success' => true,
                        'message' => "Report Generated",
                        'results' => $pdf_url
                );
                }else{
                    $res = array(
                        'success' => false,
                        'message' => "Requested Report Data Not Available",
                        'results' => 0 
                    );  
                }
                
                break;
        }
        return response()->json($res);
    }
    private function generatePDFFIle($view,$paper_size='A4',$mode='landscape')
    {
        $dompdf = new Dompdf(array('enable_remote' => true));
        //$view=view('pdfview')->render();
        $dompdf->loadHtml($view);
        $dompdf->setPaper($paper_size, $mode);
        // Render the HTML as PDF
        $dompdf->render();
       
        // Output the generated PDF to Browser
        $dompdf->stream();

    }
    public function benmasterinfo(Request $request)
    {
    try{
       // echo 
       $public_path=public_path();
       //dd($public_path);
      // $css_path=$public_path."\";
       //dd($css_path);
       
        
        $dompdf = new Dompdf();
       // $dompdf->set_base_path($public_path);
        //return view('pdfview');
        $view=view('pdfview')->render();
        //$view = view('app.Modules.AssetRegisterindex.Resources.views.pdfview')->render();
        $dompdf->loadHtml($view);
    //     $dompdf->loadHtml('<div style="border:3px solid red;width:30px">
    //     hey
    //   </div>');
        $dompdf->setPaper('A4', 'landscape');

// Render the HTML as PDF
$dompdf->render();

// Output the generated PDF to Browser
$dompdf->stream();

        $header=["name",'age','dob','month'];
        $data=[
            ["john",24,"12/12/20","32","32"]

        ];
       // $pdf = PDF::loadView('pdfview');
        //dd($pdf);
        //return $pdf->download('pdfview.pdf');
        $pdf= runImprovedtable($header,$data);
        
        //dd($pdf);
        // $pdf = new PDF();
        // $pdf->ImprovedTable($header,$data);
        // $pdf->AddPage();
        // $pdf->Output();

    $user_id = $this->user_id;
    $student_master_ben_ids= json_decode(json_encode(DB::table
    ('beneficiary_information as t1')->selectRaw(
        't1.beneficiary_id,t1.master_id')->where('batch_id',"=",52)
        ->where('category',2)
        ->get()->keyBy('master_id')->toArray()),true);          
   // $checklist_item_ids=   json_decode(json_encode(DB::table('beneficiary_information as t1')->selectRaw('t1.beneficiary_id,t1.master_id')->where('batch_id',"=",52)->get()->toArray()),true);            
    $checklist_item_min_id=76;   
    $checklist_item_max_id=92;  
    $scope_field_ids=[31,32,33,34,35,36,37,40,41,42,43,44,45,46,47,48,49];
    $checklist_items_ids=[76,77,78,79,80,81,82,83,84,85,86,87,88,89,90,91,92];
    $beneficiary_responses=array();
        //checklist_responses
    $checklist_item_value_responses= json_decode(json_encode(DB::table('temp_additional_fields_values as t1')->
    join('beneficiary_information as t2','t2.master_id','main_temp_id' )->where('t2.category','2')->
     selectRaw('t1.field_id,value,t1.main_temp_id')->where('t1.batch_id',"=",52)->whereIn('field_id',$scope_field_ids)->get()->toArray()),true); 
  //dd($checklist_item_value_responses) ;
    $results=$this->_search_array_by_value($checklist_item_value_responses,'field_id',"32");
    //$results=$this->_search_array_by_value($results,'value',"YES");
    dd($results);
   // dd($checklist_item_value_responses);
    $data_to_insert = [];
    dd($student_master_ben_ids);
    $combo_response_checklist_item_ids=[76,77,78,83,87,91];
    foreach($student_master_ben_ids as $master_id=>$student){
       
    //$results=json_encode($checklist_item_value_responses[$this->search_array($checklist_item_value_responses,'main_temp_id',$master_id)]);
    
    $results=$this->_search_array_by_value($checklist_item_value_responses,'main_temp_id',$master_id);
        ///weekly border -18 is val
        //border 16
        //day scholar 17
   
    foreach($results as $key=>$result)
    {
       //option oe
        //$data_to_insert[$checklist_items_ids[$key]]=$result['value'];
        //option two
        $response_val="";
        if(in_array( $checklist_items_ids[$key],$combo_response_checklist_item_ids) )
        {
            if( strtolower($result['value'])=="yes")
            {
                $response_val=1;
            }else if(strtolower($result['value'])=="no"){
                $response_val=2;
            }
        }else if($checklist_items_ids[$key]==86){
            
                if(Str::contains($result['value'], 'schol'))
                {
                    $response_val=17;
                }else  if(Str::contains($result['value'], 'bor'))
                {
                    if(Str::contains($result['value'], 'week'))
                    {
                        $response_val=18;
                    }else{
                        $response_val=16;
                    }


                }
        }else{
            $response_val=$result['value'];
        }
        
        $beneficiary_responses[]=[
            "beneficiary_id"=>$student['beneficiary_id'],
            "checklist_item_id"=>$checklist_items_ids[$key],
            "response"=>$response_val,
            "created_at"=>Carbon::now(),
            "created_by"=>$user_id
            
        ];
    }
    //option one
    //$beneficiary_responses[$student['beneficiary_id']]=$data_to_insert;
    //option two
   
    // dd($results);
   }
    //$beneficiary_responses=$data_to_insert;
    //dd($beneficiary_responses);
  // dd($beneficiary_responses);
   //$beneficiary_responses=array_splice($beneficiary_responses,1,10);
  // $beneficiary_responses=$beneficiary_responses[0];
   //dd($beneficiary_responses);
   //dd($beneficiary_responses);
   //$res = insertRecord('beneficiary_verification_report', $beneficiary_responses, $user_id);
   dd($beneficiary_responses);
   $result=DB::table('beneficiary_verification_report')->insert($beneficiary_responses);
   dd($result);
   //dd($beneficiary_responses);
    
//    foreach($beneficiary_responses as $response)
//    {

//    }
    
    
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($checklist_item_value_responses),
                        'results' => $checklist_item_value_responses
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
    private function  SynCMaintainanceRecords()
    {
        $user_id=$this->user_id;
        $from_inventory_to_schedule = DB::table('ar_asset_inventory as t1')
                    ->join('ar_asset_categories as t2', 't1.category_id', '=', 't2.id')
                    ->join('ar_asset_brands as t3', 't1.brand_id', '=', 't3.id')
                    ->join('ar_asset_models as t4', 't1.model_id', '=', 't4.id')
                    ->join('ar_asset_sites as t5', 't1.site_id', '=', 't5.id')
                    ->join('ar_asset_locations as t6', 't1.location_id', '=', 't6.id')
                    ->join('departments as t7', 't1.department_id', '=', 't7.id')
                    ->join('ar_asset_statuses as t8', 't1.status_id', '=', 't8.id')
                    ->selectRaw("t1.id as asset_id, t1.description,t1.serial_no,t1.grz_no,t5.id as site_id,t5.name as site_name,
                    t1.location_id,t1.department_id,t6.name as location_name,t7.name as department_name,
                    t8.name as record_status,t1.maintainance_frequency,t1.maintainance_schedule_date")
                    ->where('t1.maintainance_schedule_date', '!=', null)->get();
                    //->whereDate('t1.maintainance_schedule_date', '=', now()->format('Y-m-d'))->get();
                    $from_inventory_to_schedule_array=convertStdClassObjToArray($from_inventory_to_schedule);
                   
                    foreach($from_inventory_to_schedule_array as $asset)
                    {
                        //Carbon::now()->addDays(10)->format('Y-m-d')
                        $result=DB::table('ar_asset_maintainance as t1')
                            ->where('asset_id',$asset['asset_id'])
                            //->where('maintainance_status','>=',1)//is scheduled
                            ->where('maintainance_due_date','=',$asset['maintainance_schedule_date'])->count();
                            
                            if($result==0)
                            {
                                $table_data=array(
                                    "asset_id"=>$asset['asset_id'],
                                    "maintainance_status"=>0,
                                    "maintainance_due_date"=>$asset['maintainance_schedule_date'],
                                    "created_by"=>$this->user_id,
                                    "created_at"=>Carbon::now(),
                                );
                                $res = insertRecord('ar_asset_maintainance', $table_data, $user_id);
                            }
                       
                    }
                   
    }

 public function returnAssetStatusID($string)
    {
      $statues=["available"=>1,'checked_out'=>2,'disposed'=>9,'lost'=>4];
      return $statues[$string];
    }
    public function uploadAssets(Request $request)
    {
       

                try{
                $res=array();
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
                }
                $batch_number=$request->input('batch_no');
                $batch_description=$request->input('description');
                
                //header validations
                $document_title=rtrim(ltrim($table_data[0][0]));
            
                if($document_title!="MINISTRY OF EDUCATION - KEEPING GIRLS IN SCHOOL PROJECT")
                {
                    return response()->json([
                        "success"=>false,
                    "message"=> "Invalid Document Header" 
                    ]);
                }
                $document_sub_title=rtrim(ltrim($table_data[1][0]));
                if($document_sub_title!="ASSET REGISTER")
                {
                    return response()->json([
                        "success"=>false,
                    "message"=> "Invalid Document Sub-Header" 
                    ]);
                }
                //column headings
                $headings=$table_data[2];
                $valid_headings=["ITEM DESCRIPTION","CATEGORY","SUB-CATEGORY","BRAND","MODEL","PURCHASED FROM",
                "DATE PURCHASED","PURCHASE COST","SUPPLIER","IDENTIFIER_TYPE","UNIQUE_IDENTIFIER","GRZ SERIAL NO","Site"];
                $invalid_headings=[];
                foreach($headings as $key=>$heading)
                {
                    if(!in_array($heading,$valid_headings) && $key<11)
                    {
                        $invalid_headings[]=$heading;
                    }
                    
                }
                if(count($invalid_headings)>0)
                {
                    if(count($invalid_headings)==1){
                    $message="Invalid heading ". implode(",",$invalid_headings);
                    }else{
                    $message="Invalid headings :". implode(",",$invalid_headings);
                    }
                    return response()->json([
                        "success"=>false,
                    "message"=> $message
                    ]);
                }
                //unset headings now we have pure asset data
                unset($table_data[0]);
                unset($table_data[1]);
                unset($table_data[2]);
                $invalid_entries=[];
                // $item=$table_data[3][1];
                // //$pieces=explode("-",$item);
                // $res=$this->ValidateNumStringEntry($item);
                // if($res==0)
                // {
                //     return response()->json([
                //         "success"=>false,
                //        "message"=> "Invalid Entry for Asset Category at row number "
                //     ]);
                // }
                
                //build on missing identifiers
                $missing_identifiers=[];
                foreach($table_data as $key=>$asset_data)
                {   
                    
                    if($asset_data[10]!="" && $asset_data[10]!=null){
                        $unique_identifier=ltrim($asset_data[10]);
                        $unique_identifier=strtolower(rtrim($unique_identifier));
                        if($unique_identifier=="unknown")
                        {
                            $missing_identifiers[]=$asset_data;
                            unset($table_data[$key]);

                        }

                    }
                }

            
                //end build identfiers
                //check deupliacte serials within upload 
                $duplicate_serials=[];
                $compared_keys=[];//the duplicates match
                foreach($table_data as $key=>$asset_data)
                {   

                    if($asset_data[10]!="" && $asset_data[10]!=null){
                        $serial_no_one=rtrim($asset_data[10]);
                        $serial_no_one=ltrim($serial_no_one);
                    foreach($table_data as $similar_key_cand=>$asset_data_check)
                    {
                        if(!in_array($key,$compared_keys) && $similar_key_cand!=$key){
                        if($asset_data_check[10]!="" && $asset_data_check[10]!=null){
                            $serial_no_two=rtrim($asset_data_check[10]);
                            $serial_no_two=ltrim($serial_no_two);
                            if($serial_no_one==$serial_no_two)
                            {
                                $duplicate_serials[]="Unique Identifier. of Asset at row ".($key+1)." similar to that of asset at row".($similar_key_cand+1);
                                $compared_keys[]=$similar_key_cand;
                            
                            }
                        }
                    }
                    }
                }
                }
            
            
                if(count( $duplicate_serials)>0){
                    return response()->json([
                    "success"=>false,
                    "message"=>"Duplicate Unique Identifiers: " ."<br/>".implode("<br/>",  $duplicate_serials)
                    ]);
                }
                //end check duplicates identifiers within upload
            
                $duplicate_grz_nos=[];
                $compared_keys_grz=[];//the duplicates match
            
                foreach($table_data as $key=>$asset_data)
                {   

                    if($asset_data[11]!="" && $asset_data[11]!=null){
                        $serial_no_one=rtrim($asset_data[11]);
                        $serial_no_one=ltrim($serial_no_one);
                    foreach($table_data as $similar_key_cand=>$asset_data_check)
                    {
                        if(!in_array($key,$compared_keys_grz) && $similar_key_cand!=$key){
                        if($asset_data_check[11]!="" && $asset_data_check[11]!=null){
                            $serial_no_two=rtrim($asset_data_check[11]);
                            $serial_no_two=ltrim($serial_no_two);
                        
                            if($serial_no_one==$serial_no_two)
                            {
                                $duplicate_grz_nos[]="Grz No. of Asset at row ".($key+1)." similar to that of asset at row ".($similar_key_cand+1);
                                $compared_keys_grz[]=$similar_key_cand;
                            
                            }
                        }
                    }
                    }
                }
                }
                if(count(  $duplicate_grz_nos)>0){
                    return response()->json([
                    "success"=>false,
                    "message"=>"Duplicate Grz Nos: " ."<br/>".implode("<br/>",   $duplicate_grz_nos)
                    ]);
                }
            
                //end check duplicates grz no within upload

            
                //assign system identifiers
                $identifiers_max=[];
                foreach($missing_identifiers as $key=>$asset_data)
                {
                    $pieces=explode("-",$asset_data[1]);
                    $asset_category=DB::table('ar_asset_categories')->where('code',$pieces[0])->value('name');
                    $asset_category=rtrim(ltrim($asset_category));
                    $cat_pieces=explode(" ",$asset_category);
                    if($cat_pieces>1)
                    {
                        $asset_category=implode("-",$cat_pieces);
                    }
                    $category_id=DB::table('ar_asset_categories')->where('code',$pieces[0])->value('id');
                    if(!array_key_exists($category_id,$identifiers_max)){
                    $max_category_id=DB::table('ar_asset_inventory')->where('category_id',$category_id)->max('id');
                    $suffix=$max_category_id+1;
                    $identifiers_max[$category_id]=$suffix;
                    }else{
                        $current_max=$identifiers_max[$category_id];
                        $identifiers_max[$category_id]=$current_max+1;
                    }
                    $unique_identifier=strtoupper($asset_category)."-".$identifiers_max[$category_id];
                    $asset_data[10]=$unique_identifier;
                    $missing_identifiers[$key]=$asset_data;
                }
            
                //flag bad serial numbers
                $invalid_serial_nums=[];
                foreach($table_data as $key=>$asset_data)
                {

                    if($asset_data[10]!="" && $asset_data[10]!=null){
                        $serial_no=$asset_data[10];
                        if(strlen($serial_no)<3)
                        {
                            $invalid_serial_nums[]="short unique Identifier at at row ".($key+1);
                        }

                    }

                }
                
                if(count( $invalid_serial_nums)>0){
                    return response()->json([
                    "success"=>false,
                    "message"=>"Invalid serial Nos: " ."<br/>".implode("<br/>",  $invalid_serial_nums)
                    ]);
                }

                //end flag serial nums
                //flag bad grz no
                $invalid_grz_nums=[];
                foreach($table_data as $key=>$asset_data)
                {
                    if($asset_data[11]!="" && $asset_data[11]!=null){
                        $serial_no=$asset_data[11];
                        if(strlen($serial_no)<3)
                        {
                            $invalid_grz_nums[]="short Grz No. at at row ".($key+1);
                        }

                    }

                }
                if(count( $invalid_grz_nums)>0){
                    return response()->json([
                    "success"=>false,
                    "message"=>"Invalid Grz. Nos: " ."<br/>".implode("<br/>",$invalid_grz_nums)
                    ]);
                }
            $invalid_statuses=array();
            $allowed_statuses=["available",'checked_out','disposed','lost'];
            foreach($table_data as $key=>$asset_data)
            {
                if($asset_data[13]!="" && $asset_data[13]!=null){
                    $cleaned_data= strtolower(ltrim(rtrim($asset_data[13])));
                    if(!in_array($cleaned_data,$allowed_statuses))
                    {
                        $invalid_statuses[]="Invalid  Asset Status at  row ".($key+1); 
                    }else{
                        if($cleaned_data=="available" || $cleaned_data=="checked_out"){
                            if($asset_data[12]=="" || $asset_data[12]==null){
                                $invalid_statuses[]="Location is required for Asset  at  row ".($key+1); 
                            }
                        }
                    }
                }else{
                    $invalid_statuses[]="Missing Asset Status at  row ".($key+1); 
                }
            }   
            if(count($invalid_statuses)>0){
                return response()->json([
                "success"=>false,
                "message"=>"Invalid/Missing Asset Statuses: " ."<br/>".implode("<br/>", $invalid_statuses)
                ]);
            }
                //end flag grz no

            //merge assets with missing identifiers to others
            if(count($missing_identifiers)>0)
            {
                $table_data=array_merge($table_data,$missing_identifiers);
            }

            //end merge
                foreach($table_data as $key=>$asset_data)
                {
                    $asset_description = $asset_data[0];
                    // $validate_entries_array=[$asset_data[1],$asset_data[2],$asset_data[3],$asset_data[4],$asset_data[8],$asset_data[9],$asset_data[12]];
                    // $header_names=["Asset Category","Asset Sub-Category","Asset Brand","Asset Model","Supplier","Asset Identifier","Site"];
                    $validate_entries_array=[$asset_data[1],$asset_data[2],$asset_data[3],$asset_data[4],$asset_data[8],$asset_data[9]];
                    $header_names=["Asset Category","Asset Sub-Category","Asset Brand","Asset Model","Supplier","Asset Identifier"];
                    foreach($validate_entries_array as $pos=>$to_validate_data)
                    {
                        $res=$this->ValidateNumStringEntry($to_validate_data);
                        if($res==0)
                        {
                            $invalid_entries[]=$header_names[$pos]." at row ".($key+1);
                        }
                    }
                        
                }
                if(count($invalid_entries)>0){
                    return response()->json([
                    "success"=>false,
                    "message"=>"Invalid Entries: " ."<br/>".implode("<br/>",$invalid_entries)
                    ]);
                }
                //validate against db data
                $invalid_entries_not_match_db=[];
                foreach($table_data as $key=>$asset_data)
                {
                    // $validate_entries_array=[$asset_data[1],$asset_data[2],$asset_data[3],$asset_data[4],$asset_data[8],$asset_data[9],$asset_data[12]];
                    // $validate_entries_data_match_keys=[1,2,3,4,8,9,12];
                    // $header_names=["Asset Category","Asset Sub-Category","Asset Brand","Asset Model","Supplier","Asset Identifier","Site"];
                    // //$table_column_names=["id","id","id","id","id"]; 
                    // $table_column_names=["code","code","code","code","code","code","code"]; 
                    // $table_names=["ar_asset_categories","ar_asset_subcategories","ar_asset_brands","ar_asset_models","ar_asset_suppliers", "ar_asset_identifiers","ar_asset_sites"];
                
                    $validate_entries_array=[$asset_data[1],$asset_data[2],$asset_data[3],$asset_data[4],$asset_data[8],$asset_data[9]];
                    $validate_entries_data_match_keys=[1,2,3,4,8,9,12];
                    $header_names=["Asset Category","Asset Sub-Category","Asset Brand","Asset Model","Supplier","Asset Identifier"];
                    $table_column_names=["code","code","code","code","code","code","code"]; 
                    $table_names=["ar_asset_categories","ar_asset_subcategories","ar_asset_brands","ar_asset_models","ar_asset_suppliers", "ar_asset_identifiers"];
            
                
                    foreach($validate_entries_array as $pos=>$to_table_check_data)
                {
                    $pieces=explode("-",$to_table_check_data);
                    
                    //    $if_available=DB::table($table_names[$pos])->where($table_column_names[$pos],$pieces[0])->count();
                    //    if($if_available==0)
                    //    {
                    //     $invalid_entries_not_match_db[]=$header_names[$pos]." at row ".($key+1);
                    //    }
                        $value_if_available=DB::table($table_names[$pos])->where($table_column_names[$pos],$pieces[0])->value('id');
                        if($value_if_available=="")
                        {
                            //return $pieces[0];
                            $invalid_entries_not_match_db[]=$header_names[$pos]." at row ".($key+1);
                        }else{
                            //shift code to ids
                            $asset_data [$validate_entries_data_match_keys[$pos]]=$value_if_available;
                            $table_data[$key]=$asset_data;
                        }
                    
                }

                }
                if(count($invalid_entries_not_match_db)>0){
                    return response()->json([
                    "success"=>false,
                    "message"=>"Non Existing Items: " ."<br/>".implode("<br/>", $invalid_entries_not_match_db)
                    ]);
                }
                
            
                $to_add_assets=[];
                foreach($table_data as $key=>$asset_data)
                {
                
                    $if_exists_already_serial=0;
                    $if_exists_already_grz=0;
                    if($asset_data[10]!="" && $asset_data[10]!=null){
                        $serial_no=$asset_data[10];
                    $qry=DB::table('ar_asset_inventory');
                    $qry->where('serial_no',$serial_no);
                    $if_exists_already_serial+=$qry->count();
                    $qry=DB::table('assets_pre_upload');
                    $qry->where('serial_no',$serial_no);
                    $if_exists_already_serial+=$qry->count();
                    }
                    if($asset_data[11]!="" && $asset_data[11]!=null){
                        $grz_no=$asset_data[11];
                        $qry=DB::table('ar_asset_inventory');
                        $qry->where('grz_no',$grz_no);
                        $if_exists_already_grz+=$qry->count();
                        $grz_no=$asset_data[11];
                        $qry=DB::table('assets_pre_upload');
                        $qry->where('grz_no',$grz_no);
                        $if_exists_already_grz+=$qry->count();
                    }
                

                    if($if_exists_already_serial==0  && $if_exists_already_grz==0)//to change to 0
                    {
                        $to_add_assets[]=$asset_data;
                    }

                }
                if(count($to_add_assets)==0){
                    return response()->json([
                    "success"=>false,
                    "message"=>"All uploaded items already exist in Inventory/Pending Upload Batches"
                    ]);
                }
            


                $table_data=array(
                    "batch_no"=>$batch_number,
                    "description"=>$batch_description,
                    "status"=>0,
                    "created_at"=>Carbon::now(),
                    "created_by"=>$this->user_id,
                );
                $batch_id =DB::table('ar_asset_upload_batches')->insertGetId($table_data);
                if(!validateisNumeric($batch_id)) {
                    return response()->json([
                        "success"=>false,
                        "message"=>"An unexpected error was encountered during the upload"
                        ]);
                    }
                    //$batch_id=1;

                $table_data=$to_add_assets;
            
                
                $tblkeystounset=[];//for null rows;
                if(count($table_data)>0)
                {
                    foreach($table_data as $key=>$asset_data)
                    {
                    foreach($asset_data as $keyData=>$asset_data_inner)
                    {
                        if($keyData<7 && ($asset_data_inner==null || $asset_data_inner==""))
                        {
                            if(!in_array($key,$tblkeystounset))
                            {
                                $tblkeystounset[]=$key;
                            }
                        }
                    }
                        
                            $initial_asset_data_count=count($asset_data);
                            $purchase_date = $asset_data[6];
                            if(strlen($purchase_date)==4)
                            {
                                $purchase_date="01/01/".$purchase_date;
                            }
                            $purchasedateObj="";
                            if($purchase_date!=null && $purchase_date!=""){
                                if (strpos($purchase_date, '.') !== false){
                                    $purchase_date_array=explode(".",$purchase_date);
                                   
                                    $purchase_date=implode("/",$purchase_date_array);
                                  }
                            $purchasedateObj = \DateTime::createFromFormat("d/m/Y", $purchase_date);
                            }
                            if (!$purchasedateObj)
                            {   
                                if(!in_array($key,$tblkeystounset)){
                                     DB::table('ar_asset_upload_batches')->where(['id'=>$batch_id])->delete();
                        
                                throw new \UnexpectedValueException("Could not parse the date:  $asset_data[6]");
                                }
                            }
                            $finalPurchaseDate= $purchasedateObj->format("Y-m-d");
                            // $finalPurchaseDate=$purchase_date;
                            $cleaned_status= strtolower(ltrim(rtrim($asset_data[13])));

                        $asset_data['description']=$asset_data[0];
                        $asset_data['category_id']=explode("-",$asset_data[1])[0];
                        $asset_data['sub_category_id']=explode("-",$asset_data[2])[0];
                        $asset_data['brand_id']=explode("-",$asset_data[3])[0];
                        $asset_data['model_id']=explode("-",$asset_data[4])[0];
                        $asset_data['purchase_from']=$asset_data[5];
                        $asset_data['purchase_date']= $finalPurchaseDate;
                        $asset_data['cost']=$asset_data[7];
                        $asset_data['supplier_id']=$asset_data[8];
                        $asset_data['identifier_id']=explode("-",$asset_data[9])[0];
                        $asset_data['serial_no']=$asset_data[10];
                        $asset_data['grz_no']=$asset_data[11];
                        //$asset_data['site_id']=explode("-",$asset_data[12])[0];
                        $asset_data['location']=$asset_data[12];
                        $asset_data['status_id']=$this->returnAssetStatusID($cleaned_status);
                        $asset_data['upload_status']=1;
                        $asset_data['created_at']=Carbon::now();
                        $asset_data['created_by']=$this->user_id;
                        $asset_data['batch_id']=$batch_id;
                        $parent_id= 226347;
                        //$parent_id=259105;
                        $parent_id=228976;
                    
                    $folder_id=createAssetDMSParentFolder($parent_id,34 , $asset_data[10], '', $this->dms_id);
                        createAssetRegisterDMSModuleFolders($folder_id, 34,35, $this->dms_id);
                        $asset_data['folder_id']=$folder_id;
                        $asset_data['view_id'] = generateRecordViewID();              
                        //for($i=0;$i<11;$i++)
                        for($i=0;$i<$initial_asset_data_count;$i++)
                        {
                            unset($asset_data[$i]);
                        }
                        $table_data[$key]=$asset_data;
                    
                    
                    }
                
                    //remove null rows
                    foreach($tblkeystounset as $to_unset)
                    {
                        unset($table_data[$to_unset]);
                    }
                    //end remove null
                //create upload batch 30/07/2022
                
                
                    $info="";
                    $results=$table_data;
                    if(count($table_data)>500)
                    {
                    
                    $total_loop=ceil(count($results)/500);
                    $start_index=0;
                    $end_index=500;
                        for($i=1;$i<=$total_loop;$i++)
                        {
            
                            $results_to_insert=array();
                            foreach($results as $key=>$result)
                            {
                                if($key>=$start_index && $key<=$end_index)
                                {
                                    $results_to_insert[]=$result;
                                }
                            }
                            // $info=DB::table('ar_asset_inventory')->insert($results_to_insert);
                            $info=DB::table('assets_pre_upload')->insert($results_to_insert);

                            $results_to_insert=[];
                                    if($i!=$total_loop-1){
                                    $start_index=$end_index+1;
                                    $end_index=$start_index+500;
                                    }else{
                                        $start_index=$end_index+1;
                                        $end_index=(count($results)-1);
                                    }
            
                        }
                    }else{
                        //$info=DB::table('ar_asset_inventory')->insert($results);
                        $info=DB::table('assets_pre_upload')->insert($results);
                    }
                    
                if($info==true)
                {
                        $res=array(
                            "success"=>true,
                            "message"=>"Assets Upload Success",
                        
                        );
                }else{
                     DB::table('ar_asset_upload_batches')->where(['id'=>$batch_id])->delete();
                    $res=array(
                        "success"=>false,
                        "message"=>"Assets Upload Failed",
                    
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
    private function  ValidateNumStringEntry($item)
    {
        $res="";
        try{
            $pieces=explode("-",$item);
           
            $valid=1;
            if(!validateisNumeric($pieces[0])) {
                $valid=0;
            }
           
            if(!$this->ValidateIsString($pieces[1]))
            {
                $valid=0;
            }

            $res=$valid;
           


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
    private function ValidateIsString($item)
    {
        return is_string($item);

    }
    
    public function getAssetInventoryDetailsForReservations(Request $request)
    {   
    
   

        try{

          
           
           $status_id=3;
            $status_id_damaged=$request->input('status');


            $qry = DB::table('ar_asset_inventory as t1')
               // ->join('users as t9', 't9.user_id', '=', 't1.id')
                ->join('ar_asset_categories as t2', 't1.category_id', '=', 't2.id')
                ->leftjoin('ar_asset_brands as t3', 't1.brand_id', '=', 't3.id')
                ->leftjoin('ar_asset_models as t4', 't1.model_id', '=', 't4.id')
                ->leftjoin('ar_asset_sites as t5', 't1.site_id', '=', 't5.id')
                ->leftjoin('ar_asset_locations as t6', 't1.location_id', '=', 't6.id')
                ->leftjoin('departments as t7', 't1.department_id', '=', 't7.id')
                ->leftjoin('ar_asset_statuses as t8', 't1.status_id', '=', 't8.id')
               //->leftJoin('ar_asset_maintainance as t9', 't1.id', '=', 't9.asset_id')
                //->leftJoin('ar_asset_repairs as t10', 't1.id', '=', 't10.asset_id')
                ->selectRaw('t1.*,t1.id as record_id,t1.id as parent_asset_id,t2.name as category_name,t3.name as brand_name,t4.name as model_name,t5.name as site_name,
                 t6.name as location_name,t7.name as department_name,t8.name as record_status,t1.id as asset_id');
              // maintainance_status,repair_status,
                // maintainance_schedule_date as maintainance_date,scheduled_repair_date');
             
              if (!validateisNumeric($status_id_damaged)){
              if (validateisNumeric($status_id)) {
                $qry->where('status_id', 1);//where a
               // $qry->orWhere('status_id',2);
                }
                }else{
                    $qry->where('status_id', 5);//damaged,qualify for repair
                }
            //$qry->where('maintainance_schedule_date',"!=",now()->format('Y-m-d'));
            $results=$qry->get();

       
            
            //$results=$qry->whereRaw('maintainance_schedule_date != CURDATE()')->get();
            

            $maintainance_details=DB::table('ar_asset_maintainance')->selectRaw('maintainance_status,asset_id,maintainance_due_date')->orderBy('created_at','DESC')->get();
            $repair_details=DB::table('ar_asset_repairs')->selectRaw('repair_status,asset_id,scheduled_repair_date')->orderBy('created_at','DESC')->get();
            $maintainance_details=convertStdClassObjToArray($maintainance_details);
            $repair_details=convertStdClassObjToArray($repair_details);
           // $qry->where('scheduled_for_repair','=','0');
            //$qry->where('scheduled_for_maintainance','=','0');
            //$qry->where('has_maintainance_scheduled','=','0');

            //$qry->where('t9.maintainance_status','=',0);
            //$qry->where('t9.maintainance_status','=','null');
            //$qry->where('t10.repair_status','0');
            $results = $qry->get()->toArray();
            
            $ammended_results=[];
            foreach($results as $asset)
            {
                $to_add_to_array=true;
                $asset_maintainance_details=$this->_search_array_by_value($maintainance_details,'asset_id',$asset->id);
                $asset_repair_details=$this->_search_array_by_value($repair_details,'asset_id',$asset->id);
                if(count($asset_maintainance_details)>0){
                    
                $asset->maintainance_status=$asset_maintainance_details[0]['maintainance_status'];
                $asset->maintainance_date=$asset_maintainance_details[0]['maintainance_due_date'];
                    if(
                        $asset->maintainance_status==0 &&
                        ((new \DateTime($asset_maintainance_details[0]['maintainance_due_date']))==(new \DateTime(date('Y-m-d'))))
                        )
                    {
                       $to_add_to_array=false; 
                    }
                    //exclude assets that are maintainance overdue
                    if(
                        (
                        (new \DateTime($asset_maintainance_details[0]['maintainance_due_date']))<(new \DateTime(date('Y-m-d')))
                        ) &&  $asset->maintainance_status==0
                      
                        )
                    {
                       $to_add_to_array=false; 
                    }
                    
                }else{
                    $asset->maintainance_status="";
                    $asset->maintainance_date="";
                }
                if(count($asset_repair_details)>0){
                  
                    $asset->repair_status=$asset_repair_details[0]['repair_status'];
                    $asset->scheduled_repair_date=$asset_repair_details[0]['scheduled_repair_date'];
                    if( $asset->repair_status==0 &&
                        ((new \DateTime($asset_repair_details[0]['scheduled_repair_date']))==
                        (new \DateTime(date('Y-m-d'))))
                        )
                    {
                       
                       $to_add_to_array=false; 
                    }
                    //to exclude repair overude
                    if(
                        (
                        (new \DateTime($asset_repair_details[0]['scheduled_repair_date']))<(new \DateTime(date('Y-m-d')))
                        ) && (  $asset->repair_status==0)
                        )
                    {
                       $to_add_to_array=false; 
                    }
                }else{
                    $asset->repair_status="";
                    $asset->scheduled_repair_date="";
                }
                if($to_add_to_array==true){//to exclude assets due/overdue repair/maintainance
                
                $ammended_results[]=$asset;
                }
                $to_add_to_array=true;
               
            }
            $results=$ammended_results;
          
            if($request->query('status_funding_linkage2')=='repair_maintainance_status_has_funding'){//suspended due to user requirements 
                //Job on 
            

                    $new_ammended_results=[];
                   foreach($ammended_results as $asset)
                   {
                        
                
                    $asset_link_fund_count=Db::table('ar_asset_funding_linkage as t1')->where('asset_id',$asset->parent_asset_id)
                    ->join('ar_funding_details as t2','t2.id','=','t1.funding_id')
                    ->whereDate('end_date', '>', now()->format('Y-m-d'))->count();
                    
                  
                    if($asset_link_fund_count>0)
                    {
                        $new_ammended_results[]=$asset;
                    }
                

                   }
                   $results=$new_ammended_results;
                }

            //end
          
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


   
   public function RequisitionRevert(Request $request)
   {
       $res=array();
        try{
            $requisition_ids=$request->input('requisitionIds');
            $requisition_ids_array=explode(",",$requisition_ids);
            $reservation_ids=$request->input('reservation_ids');
            
            if(is_array($requisition_ids_array))
            {
                
                DB::table('ar_asset_requisitions')->whereIn('id',$requisition_ids_array)
                ->update(['requisition_status'=>0,"remarks"=>"Item is unavailable for assignement"]);
                $res = array(
                    'success' => true,
                    'message' => "Requisition revert successfull!"
                );
            }else{
                if($reservation_ids!=""){
                DB::table('ar_asset_requisitions')->where('id',$requisition_ids)
                ->update(['requisition_status'=>0,"remarks"=>"Item is unavailable for assignement"]);
                $res = array(
                    'success' => true,
                    'message' => "Requisition revert successfull!"
                );
             }

            }
            if($reservation_ids && $reservation_ids!="")
            {
                DB::table('ar_asset_reservations')->where('id')->update(['checkin_status'=>4]);//dpne processing
                $res = array(
                    'success' => true,
                    'message' => "Reservation revert successfull!"
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
   public function getAssetInventoryDetails(Request $request)
   {
     
       try {
           $status_id = $request->input('status_id');
           $requested_category_id= $request->input('requested_category_id');//is the subcategory id

           $qry = DB::table('ar_asset_inventory as t1')
               ->join('ar_asset_categories as t2', 't1.category_id', '=', 't2.id')
               ->leftjoin('ar_asset_subcategories as t12', 't1.sub_category_id', '=', 't12.id')//new late fix
               ->leftjoin('ar_asset_brands as t3', 't1.brand_id', '=', 't3.id')
               ->leftjoin('ar_asset_models as t4', 't1.model_id', '=', 't4.id')
               ->leftjoin('ar_asset_sites as t5', 't1.site_id', '=', 't5.id')
               ->leftjoin('ar_asset_locations as t6', 't1.location_id', '=', 't6.id')
               ->leftjoin('departments as t7', 't1.department_id', '=', 't7.id')
               ->join('ar_asset_identifiers as t9','t1.identifier_id','t9.id')
               ->join('ar_asset_statuses as t8', 't1.status_id', '=', 't8.id')
               ->leftjoin('ar_asset_suppliers as t13', 't1.supplier_id', '=', 't13.id')
               //->leftJoin('ar_asset_maintainance as t9', 't1.id', '=', 't9.asset_id')//was letjin
               //->leftJoin('ar_asset_repairs as t10', 't1.id', '=', 't10.asset_id')//was eleftjoin
               ->leftjoin('ar_asset_depreciation as t11','t11.asset_id','t1.id')
               ->selectRaw("t1.*, t1.id as record_id,t1.id as asset_id, t2.name as category_name, t3.name as brand_name, t4.name as model_name,t5.name as site_name,
                    t6.name as location_name,t7.name as department_name, t8.name as record_status,t9.name as unique_identifier_type,
                    CONCAT_WS('-',t1.description,t1.grz_no) as desc_grz_no,  CONCAT_WS('-',t1.description,t1.serial_no) as desc_serial_no,
                    maintainance_schedule_date as maintainance_date,t13.name as supplier_name,
                 
                    depreciation_method_id,depreciation_rate,date_acquired,asset_life,salvage_value,depreciable_cost,t12.name as sub_category_name")
                    ->where('t1.module_id',350);
                  
               $asset_ids_to_exclude_for_funding=[];
           if($request->input('status')=="exclude_assets_already_to_funding2")//suspended by Job on 18/8-20210
           {
               
               $asset_ids_to_exclude=Db::table('ar_asset_funding_linkage')->where('funding_id',$request->input('funding_id'))->selectraw('asset_id')->get()->toArray();
               foreach($asset_ids_to_exclude as $asset)
               {
                   $asset_ids_to_exclude_for_funding[]=$asset->asset_id;

               }
           }


           $maintainance_details=DB::table('ar_asset_maintainance')->selectRaw('maintainance_status,asset_id,maintainance_due_date')->orderBy('created_at','DESC')->get();
           $repair_details=DB::table('ar_asset_repairs')->selectRaw('repair_status,asset_id,scheduled_repair_date')->orderBy('created_at','DESC')->get();
           
           $asset_ids_to_exclude_for_funding=[];
           if($request->input('status')=="exclude_assets_already_to_funding2")
           {
               
               $asset_ids_to_exclude=Db::table('ar_asset_funding_linkage')->where('funding_id',$request->input('funding_id'))->selectraw('asset_id')->get()->toArray();
               foreach($asset_ids_to_exclude as $asset)
               {
                   $asset_ids_to_exclude_for_funding[]=$asset->asset_id;

               }
           }

          $maintainance_details=convertStdClassObjToArray($maintainance_details);
          $repair_details=convertStdClassObjToArray($repair_details);
                  
           if(validateisNumeric($requested_category_id))
           {
               //change to match subcategoryid instead of category_id
               
                   $qry->where('t1.sub_category_id', $requested_category_id);
               
                  
           }else {
               $requested_category_id=$request->input('asset_category_id');
              
               if(validateisNumeric($requested_category_id))
               {
                   $qry->where('t1.sub_category_id', $requested_category_id);
               }
           }
          
               if (validateisNumeric($status_id)) {
               $qry->where('status_id', $status_id);
               
           }
           
           if ($request->query('exclusion_asset_ids')) {

               $set_ids= explode('_',$request->query('exclusion_asset_ids'));
               $qry->whereNotIn('t1.id', $set_ids);
           }
           if(count( $asset_ids_to_exclude_for_funding)>0)
           {
               $qry->whereNotIn('t1.id',$asset_ids_to_exclude_for_funding);
           }
           
          
           $results = $qry->get()->toArray();
           $ammended_results=[];
           foreach($results as $asset)
           {
               $to_add_to_array=true; 
               $asset_maintainance_details=$this->_search_array_by_value($maintainance_details,'asset_id',$asset->id);
               $asset_repair_details=$this->_search_array_by_value($repair_details,'asset_id',$asset->id);
               if(count($asset_maintainance_details)>0){
               $asset->maintainance_status=$asset_maintainance_details[0]['maintainance_status'];
               $asset->maintainance_date=$asset_maintainance_details[0]['maintainance_due_date'];
                  //lates update on 16/12/2021 

               if($request->query('status')=="exclude_maintainance_repair_overdue_due"){
                   if(  $asset->maintainance_status==0 &&
                       ((new \DateTime($asset_maintainance_details[0]['maintainance_due_date']))==(new \DateTime(date('Y-m-d')))
                       )
                       )
                   {
                   $to_add_to_array=false; 
                   }
                   //exclude assets that are maintainance overdue
                   if(
                       (
                       (new \DateTime($asset_maintainance_details[0]['maintainance_due_date']))<(new \DateTime(date('Y-m-d')))
                       ) &&  $asset->maintainance_status==0
                   
                       )
                   {
                   $to_add_to_array=false; 
                   }
               }

               }else{
                   $asset->maintainance_status="";
               }
              
              
               if(count($asset_repair_details)>0){
                   $asset->repair_status=$asset_repair_details[0]['repair_status'];
                   $asset->scheduled_repair_date=$asset_repair_details[0]['scheduled_repair_date'];
               if($request->query('status')=="exclude_maintainance_repair_overdue_due"){
                       if(
                           $asset->repair_status==0 &&
                           ((new \DateTime($asset_repair_details[0]['scheduled_repair_date']))==
                           (new \DateTime(date('Y-m-d'))))
                           )
                       {
                       $to_add_to_array=false; 
                       }
                       //to exclude repair overdue
                       if(
                           (
                           (new \DateTime($asset_repair_details[0]['scheduled_repair_date']))<(new \DateTime(date('Y-m-d')))
                           ) && (  $asset->repair_status==0)
                           )
                       {
                       $to_add_to_array=false; 
                       }
               }

               }else{
                   $asset->repair_status="";
                   $asset->scheduled_repair_date="";
               }
               if($to_add_to_array==true){
               $ammended_results[]=$asset;
                   }
           }
         
           
           if(validateisNumeric($request->query('requisition_id')))
           {

           $results = $ammended_results;
           $chained_results=[];
           foreach($results as $result)
           {
               $result->requisition_id=$request->query('requisition_id');
               $result->requisition_user_id=$request->query('requisition_user_id');
               $chained_results[]=$result;
           }
          
           $res = array(
               'success' => true,
               'message' => returnMessage($chained_results),
               'results' => $chained_results
           );

           }else{

           //$results = $qry->get()->toArray();
           $results=convertStdClassObjToArray($ammended_results);
             
           $new_results=[];
           foreach($results as $index=>$result)
           {
               
               if($result['depreciation_method_id']!=null)
               {
                   
                   $depreciation_method=$result['depreciation_method_id'];
                   $depreciation_details=(object)[
                       "asset_life"=>$result['asset_life'],
                       "depreciable_cost"=>$result['depreciable_cost'],
                       "salvage_value"=>$result['salvage_value'],
                       "date_acquired"=>$result['date_acquired'],
                       "depreciation_rate"=>$result['depreciation_rate']
                   ];
                  
                   switch($depreciation_method)
                   {
                       
                       case 1:
                           $result_value = calculateStraightLineDepreciation($depreciation_details,date('Y-m-d'));
                           $result["current_asset_value"]=round($result_value);
                           $new_results[]=$result;
                           break;
                       case 3:
                           $result_value = calculatePercentageDepreciation($depreciation_details,date('Y-m-d'),2);
                           $result["current_asset_value"]=round($result_value);
                          
                           $new_results[]=$result;
                          
                           break;
                       case 4:
                           $result_value = calculatePercentageDepreciation($depreciation_details,date('Y-m-d'),1.5);
                           $result["current_asset_value"]=round($result_value);
                           $new_results[]=$result;
                           break;
                       case 2:
                           $percentage_dep=$depreciation_details->depreciation_rate/100;
                           $result_value= calculatePercentageDepreciation($depreciation_details,date('Y-m-d'),$percentage_dep);
                           $result["current_asset_value"]=round($result_value);
                           $new_results[]=$result;
                           break;
                     
                   }
               }else{
                   $new_results[]=$result;
               }
              
             
           }
           
         
           $res = array(
               'success' => true,
               'message' => returnMessage($new_results),
               'results' => $new_results
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


    public function deletebrand(Request $request)
    {
        try{
        $record_id=$request->input('brand_id');       
        $where=array(
            'id' => $record_id
        );
        $user_id=$this->user_id;
        $previous_data = getPreviousRecords('ar_asset_brands', $where);
        $res =  deleteRecordWithComments('ar_asset_brands', $previous_data, $where, $user_id,$request->input('delete_reason'));
        if($res['success']==true)
        {
            $res=Db::table('ar_asset_brands_categories')->where('brand_id',$record_id)->delete();
            if($res>=0)
            {
                $res=array(
                    "success"=>true,
                );
            }else{
                $res=array(
                    "success"=>false,
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
    public function saveAssetAdditionalData(Request $request)
    {
        $res="";
        try {
            $form_data=$request->all();
            $table_data=[];
            $asset_id=$form_data['asset_id'];
            unset($form_data['asset_id']);
            $count= DB::table('ar_asset_category_attributes_values')
            ->where('asset_id',$asset_id)->count();
        
            if($count>1)
            {
                foreach ($form_data as $key => $value) {
                
                    $attribute_id = preg_replace('/[^0-9.]+/', '', $key);
                    $table_data[]=array(
                        "attribute_id"=>$attribute_id,
                        "value"=>$value
                    );
                  
                    foreach ($table_data as $key => $data) {
                    
                       $where=array(
                           "attribute_id"=>$data['attribute_id'],
                           "asset_id"=>$asset_id
                       );
                       $previous_data = getPreviousRecords('ar_asset_category_attributes_values', $where);
                       $res = updateRecord('ar_asset_category_attributes_values', $previous_data, $where, ["value"=>$data['value']], $this->user_id);
                  
                    }
                   
            }
         
            }else{
                foreach ($form_data as $key => $value) {
                
                    $attribute_id = preg_replace('/[^0-9.]+/', '', $key);
                    $table_data[]=array(
                        "asset_id"=>$asset_id,
                        "attribute_id"=>$attribute_id,
                        "value"=>$value
                    );
                }
            $res=DB::table('ar_asset_category_attributes_values')->insert($table_data);
            if($res==1 || $res==true)
            {
                $res=array(
                    "success"=>true,
                    "message"=>"Data Saved Successfully"

                );
            }else{
                $res=array(
                    "success"=>false,
                    "message"=>"Data Not Saved"

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
    public function saveAssetRegisterParamData(Request $req)
    {
        try {
            $user_id = $this->user_id;
            $linkage_record_id=$req->input('linkage_record_id');
            $post_data = $req->all();
            $table_name = $post_data['table_name'];
            $id = $post_data['id'];
            $res = array();
          
            //unset unnecessary values
            unset($post_data['status_id']);
            unset($post_data['_token']);
            unset($post_data['table_name']);
            unset($post_data['id']);
            unset($post_data['linkage_record_id']);
            $table_data = $post_data;
            //add extra params
            $asset_depreciation_method="";
            $depreciation_details;
            if(array_key_exists('depreciation_method_id',$post_data)){
            $asset_depreciation_method=$post_data['depreciation_method_id'];
                $method_id=$post_data['depreciation_method_id'];
            $depreciation_details=[
                "asset_life"=>$post_data['asset_life'],
                "depreciable_cost"=>$post_data['depreciable_cost'],
                "date_acquired"=>$post_data['date_acquired']
            ];
            
            if(  $method_id=="1")
            {
                $depreciation_details['depreciation_rate']=100;
            }
            if(  $method_id=="3")
            {
                $depreciation_details['depreciation_rate']=200;
            }
            if(  $method_id=="4")
            {
                $depreciation_details['depreciation_rate']=150;
            }
            // $depreciation_details=(object)[
            //     "asset_life"=>$post_data['asset_life'],
            //     "depreciable_cost"=>$post_data['depreciable_cost'],
            //     "salvage_value"=>$post_data['salvage_value'],
            //     "date_acquired"=>$post_data['date_acquired']
            // ];
        }
        if (array_key_exists('salvage_value',$post_data)) {

            $depreciation_details["salvage_value"]=$post_data['salvage_value'];
            $depreciation_details=(object)$depreciation_details;
        }else{
            if($table_name=="ar_asset_depreciation"){
            $depreciation_details=(object)$depreciation_details;
        }
            
        }
      
            switch($asset_depreciation_method){
                // case 5:
                    
                //     $table_data['asset_end_depreciation_date']=calculateSumofYearofDigits($depreciation_details,true);
                //     break;
                case 4:
                    $table_data['salvage_value']=calculatePercentageDepreciation($depreciation_details,"",1.5,false,true);
                   
                    $table_data['asset_end_depreciation_date']=calculatePercentageDepreciation($depreciation_details,"",1.5,true);
                    break;
                case 3:
                    $table_data['salvage_value']=calculatePercentageDepreciation($depreciation_details,"",2,false,true);
                    $table_data['asset_end_depreciation_date']=calculatePercentageDepreciation($depreciation_details,"",2,true);
                    break;
                case 2:
                    $rate=$post_data['depreciation_rate']/100;
                    $table_data['salvage_value']=calculatePercentageDepreciation($depreciation_details,"",$rate,false,true);
                    $table_data['asset_end_depreciation_date']=calculatePercentageDepreciation($depreciation_details,"", $rate,true);
                    break;
                case 1:
                    $table_data['asset_end_depreciation_date']=calculateStraightLineDepreciation($depreciation_details,"",true);
                    break;
            }
           
            $table_data['created_at'] = Carbon::now();
            $table_data['created_by'] = $user_id;
            $where = array(
                'id' => $id
            );
            //if (isset($id) && $id != "") {
        if (validateisNumeric($linkage_record_id)) {

                $result=Db::table('ar_asset_funding_linkage')->where('id',$linkage_record_id)//funding suspended Job on 17/98/2022
                ->update(['amount'=>$req->input('amount'),
                "funding_id"=>$req->input('funding_id'),
                "updated_at"=>carbon::now(),
                "updated_at"=>$this->user_id,
                "notes"=>$req->input('notes')]);

                if($result==1)
                {
                    $res=array(
                        "success"=>true,
                        "message"=>"Data updated Successfully!"
                    );
                }else{
                    $res=array(
                        "success"=>false,
                        "message"=>"Data update Failed!"
                    );
                }
               


              
            } else {
                if (isset($id) && $id != "" && validateisNumeric($id)) {
                   // if($table_data['depreciation_method_id']!="2")
                    if(array_key_exists('depreciation_method_id',$table_data) && $table_data['depreciation_method_id']!="2")

                    {
                        if( $table_data['depreciation_method_id']=="1")
                        {
                            $table_data['depreciation_rate']=100;
                        }
                        if( $table_data['depreciation_method_id']=="3")
                        {
                            $table_data['depreciation_rate']=200;
                        }
                        if( $table_data['depreciation_method_id']=="4")
                        {
                            $table_data['depreciation_rate']=150;
                        }
                       // $table_data['depreciation_rate']="";
                       
                    }
                    $previous_data = getPreviousRecords($table_name, $where);
                    $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                    
                }else{
                    $res = insertRecord($table_name, $table_data, $user_id);
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
    public function updateTagFields(Request $request)
    {
        try{
        $brand_id=$request->input('brand_id');
        $brand_id_categories=$request->input('brand_category_ids');
        $proposed_brand_linkage_data=$this->returnArrayFromStringArray($brand_id_categories);
        foreach($proposed_brand_linkage_data as $key=>$category_id)
        {
            $proposed_brand_linkage_data[$key]=(int)(filter_var($category_id, FILTER_SANITIZE_NUMBER_INT));
        }
        $qry=Db::table('ar_asset_brands_categories')->where('brand_id',$brand_id)
        ->selectraw('id,category_id');
        $current_brand_linkage_data=convertStdClassObjToArray($qry->get());
        $maintained_ids=[];
        $ids_to_remove=[];
        $record_ids_to_remove=[];
       
        if(count($current_brand_linkage_data))
        foreach($current_brand_linkage_data as $linkage)
        {
            if(in_array($linkage['category_id'] ,$proposed_brand_linkage_data))
            {
                $maintained_ids[]=$linkage['category_id'];
            }else{
                $ids_to_remove[]=$linkage['category_id'];
                $record_ids_to_remove[]=$linkage['id'];
            }

        }
       
    
        foreach   ($maintained_ids as $available_id)
        {
            $key=array_search($available_id,$proposed_brand_linkage_data);
        
          
            if($key>-1)
            {
                unset($proposed_brand_linkage_data[$key]);
            }

        }
      
     
        foreach($ids_to_remove as $remove_id)
        {
            $key=array_search($remove_id,$proposed_brand_linkage_data);
            if($key>-1)
            {
                unset($proposed_brand_linkage_data[$key]);
            }

        }
       
        foreach($record_ids_to_remove as $record_id)
        {
            $where=array(
                "id"=>$record_id
            );
            $previous_data = getPreviousRecords('ar_asset_brands_categories', $where);
            $res = deleteRecord('ar_asset_brands_categories',$previous_data, $where,$this->user_id);
        }
        foreach($proposed_brand_linkage_data as $key=>$linkage_data_category_id)
        {
           
            $proposed_brand_linkage_data[$key]=array(
                "brand_id"=>$brand_id,
                "category_id"=>$linkage_data_category_id,
                "created_at"=> Carbon::now(),
                "created_by"=>$this->user_id
            );
        }
        $res=Db::table('ar_asset_brands_categories')->insert($proposed_brand_linkage_data);
        if($res==true)
        {
            $res=array(
                "success"=>true,
                "message"=>"Brand Data Successfully Updated"
            );
        }else{
            $res=array(
                "success"=>false,
                "message"=>"Error while updating brand etau"
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
    public function getAssetDepreciation(Request $request)
    {
        try {
            $asset_id = $request->input('asset_id');
            $qry = DB::table('ar_asset_depreciation as t1')
                ->join('ar_asset_inventory as t3','t3.id','=','t1.asset_id')
                ->join('ar_depreciation_methods as t2', 't1.depreciation_method_id', '=', 't2.id')
                //->select('t1.*', 't2.name as depreciation_method','t3.cost as depreciable_cost')
                ->selectRaw('t1.id,t1.asset_life,t1.depreciation_method_id,t1.depreciation_rate,t3.cost as depreciable_cost,
                t3.purchase_date as date_acquired,t2.name as depreciation_method,salvage_value,t1.depreciation_rate')
                ->where('t1.asset_id', $asset_id);
            $results = $qry->get()->toArray();
            if(count( $results)==0)
            {
                $results= DB::table('ar_asset_inventory')
                ->where('id', $asset_id)
                ->selectRaw('cost as depreciable_cost,purchase_date as date_acquired')->get();
            }else{
                switch($results[0]->depreciation_method_id)
                {
                    
                    case 1:
                        $results[0]->depreciation_rate='100%';
                    case 3:
                        $results[0]->depreciation_rate='200%';
                    case 4:
                        $results[0]->depreciation_rate='150%';
                        break;

                }
            }
            
            $res = array(
                'success' => true,
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

    public function getInsurancePoliciesForLinkage(Request $request)
    {
        try {
            $asset_id = $request->input('asset_id');
            $policy_id = $request->input('policy_id');
            $qry = '';
            if (validateisNumeric($asset_id)) {
                $qry = DB::table('ar_insurance_policies as t1')
                    ->whereNotIn('t1.id', function ($query) use ($asset_id) {
                        $query->select(DB::raw('policy_id'))
                            ->from('ar_asset_insurance_linkage')
                            ->where('asset_id', $asset_id);
                    })
                    ->whereRaw("t1.end_date>NOW()");
            }
            if (validateisNumeric($policy_id)) {
                $qry = DB::table('ar_asset_inventory as t1')
                    ->whereNotIn('t1.id', function ($query) use ($policy_id) {
                        $query->select(DB::raw('asset_id'))
                            ->from('ar_asset_insurance_linkage')
                            ->where('policy_id', $policy_id);
                    });
            }
            $qry->select('t1.*');
            $results = $qry->get();
            $res = array(
                'success' => true,
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

    public function saveAssetInsuranceLinkage(Request $req)
    {
        try {
            $selected = $req->input('selected');
            $asset_id = $req->input('asset_id');
            $policy_id = $req->input('policy_id');
            $selected_ids = json_decode($selected);
            $linkage_insert = array();
            if (validateisNumeric($asset_id)) {
                foreach ($selected_ids as $selected_id) {
                    $linkage_insert[] = array(
                        'asset_id' => $asset_id,
                        'policy_id' => $selected_id
                    );
                }
            }
            if (validateisNumeric($policy_id)) {
                foreach ($selected_ids as $selected_id) {
                    $linkage_insert[] = array(
                        'policy_id' => $policy_id,
                        'asset_id' => $selected_id
                    );
                }
            }
            DB::table('ar_asset_insurance_linkage')
                ->insert($linkage_insert);
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

    public function getAssetInsuranceLinkageDetails(Request $request)
    {
        try {
            $asset_id = $request->input('asset_id');
            $policy_id = $request->input('policy_id');
            $qry = DB::table('ar_asset_insurance_linkage as t1')
                ->select('t1.*');
            if (validateisNumeric($asset_id)) {
                $qry->join('ar_insurance_policies as t2', 't1.policy_id', '=', 't2.id')
                    ->addSelect('t2.*')
                    ->where('t1.asset_id', $asset_id);
            }else{
                //Job
                if (!validateisNumeric($policy_id)) {
                $res = array(
                    'success' => true,
                    'results' => []
                );
                return response()->json($res);
                }
            }
            if (validateisNumeric($policy_id)) {
                $qry->join('ar_asset_inventory as t3', 't1.asset_id', '=', 't3.id')
                    //->addSelect('t3.*')
                     //Job update to remove bug id in join
                     ->addSelect(DB::raw('t3.description,t3.serial_no,t3.grz_no,t3.purchase_from,t3.purchase_date,t3.cost'))
                    ->where('t1.policy_id', $policy_id);
            }else{
                //Job
                if (!validateisNumeric($asset_id)) {
                $res = array(
                    'success' => true,
                    'results' => []
                );
                return response()->json($res);
            }
            }
            $qry->addSelect('t1.id');
            $results = $qry->get();
            $res = array(
                'success' => true,
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

    public function getFundingForLinkage(Request $request)
    {
        try {
            $qry = DB::table('ar_funding_details as t1')
                //->whereRaw("t1.end_date>NOW()")
                ->selectRaw("t1.*,(t1.funding_amount-(Select sum(amount) from ar_asset_funding_linkage where funding_id=t1.id)) as available_amount,
                   @available_amount := (t1.funding_amount-(Select COALESCE(sum(amount),0) from ar_asset_funding_linkage where funding_id=t1.id)) as available_amount,
                   @asset_count:=(select count(asset_id) from ar_asset_funding_linkage where funding_id=t1.id) as asset_count,
                   CONCAT(t1.name,' Available ',@available_amount,' (',DATE_FORMAT(t1.start_date, '%d/%m/%Y'),'-',DATE_FORMAT(t1.end_date, '%d/%m/%Y'),')') as display");
            $results = $qry->get();
            $fundings=convertStdClassObjToArray($results);
            foreach ($fundings as $key => $fund) {
                
               $total_maintainance_sum= DB::table('ar_asset_maintainance')->where('funding_id',$fund['id'])->sum('maintainance_cost');
               $fund['available_amount']= $fund['available_amount']-$total_maintainance_sum;
               $total_repair_sum= DB::table('ar_asset_repairs')->where('funding_id',$fund['id'])->sum('repair_cost');
               $fund['available_amount']= $fund['available_amount']-$total_repair_sum;
                
              
              $fundings[$key]=$fund;  
            }
            $results=$fundings;
            $res = array(
                'success' => true,
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

    public function getAssetFundingLinkageDetails(Request $request)
    {
        //Job
        try {
            $asset_id = $request->input('asset_id');
           
            $funding_id = $request->input('funding_id');
            $qry = DB::table('ar_asset_funding_linkage as t1')
            ->select('t1.id', 't1.amount', 't1.funding_id', 't1.asset_id', 't1.notes as notes_asset', 't1.amount as amount_from_fund',
                    't1.id as linkage_record_id');

                
            $results=[];//update job
            
            if (validateisNumeric($asset_id)) {
                $qry->join('ar_funding_details as t2', 't1.funding_id', '=', 't2.id')
                    ->where('t1.asset_id', $asset_id)
                    ->addSelect('t2.*');
            }
           $results="";
            if (validateisNumeric($funding_id)) {
               
                $qry->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
                    ->where('t1.funding_id', $funding_id)
                    //->addSelect('t2.*');
                    //Job update to remove bug id in join
                    ->addSelect(DB::raw('t2.id as asset_id,t2.description,t2.serial_no,t2.grz_no,t2.purchase_from,t2.purchase_date,cost'));
                    $results = $qry->get();
            }else{
                $results=$qry->get();
            }
            //new upadate to prevent global linkage on new funds
            if($funding_id=="" && !validateisNumeric($asset_id))
            {
                $results=[];
            }
          
            $assets=convertStdClassObjToArray($results);
          foreach ($assets as $key => $asset) {
            $total_maintainance_sum= DB::table('ar_asset_maintainance')->where('funding_id',$funding_id)
            ->where('asset_id',$asset['asset_id'])->sum('maintainance_cost');
            $asset['maintainance_amount_from_funding']= $total_maintainance_sum;
            $total_repair_sum= DB::table('ar_asset_repairs')->where('funding_id',$funding_id)
            ->where('asset_id',$asset['asset_id'])->sum('repair_cost');
            $asset['repair_amount_from_funding']= $total_repair_sum;
            $assets[$key]=$asset;   
          }
         $results=$assets;
            $res = array(
                'success' => true,
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

    public function calculateAssetDepreciation(Request $request)
    {
       
    
        
        try {
            $asset_id = $request->input('asset_id');
            //get depreciation details
            $depreciation_details = DB::table('ar_asset_depreciation')
                ->where('asset_id', $asset_id)
                ->first();
           
            $results = array();
            if (!is_null($depreciation_details)) {
                $depreciation_method = $depreciation_details->depreciation_method_id;
                
                switch($depreciation_method)
                {
                    case 1:
                        $results = calculateStraightLineDepreciation($depreciation_details);
                        break;
                    case 3:
                        $results = calculatePercentageDepreciation($depreciation_details,"",2);
                        break;
                    case 4:
                        $results = calculatePercentageDepreciation($depreciation_details,"",1.5);
                        break;
                    case 2:
                        $percentage_dep=$depreciation_details->depreciation_rate/100;
                        $results= calculatePercentageDepreciation($depreciation_details,"",$percentage_dep);
                    // case 5:
                    //    $results = calculateSumofYearofDigits($depreciation_details);
                        //break;
                }
               // if ($depreciation_method == 1) {//Straight Line }
            }
            $res = array(
                'success' => true,
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

    private function returnArrayFromStringArray($string_array)
    {

        $string_array=substr(trim($string_array), 0, -1);
        $final_array=explode(',' ,substr($string_array,1));
        return $final_array;
    }
    public function RestoreAsset(Request $request)
    {
        $asset_id=$request->input('asset_id');
        $user_id=$this->user_id;
        $res="";
        if (validateisNumeric($asset_id)) {
           // $res=Db::table('ar_asset_inventory')->where('id',$asset_id)->update('status_id',12);
            $where=array(
                "id"=>$asset_id
            );
            $table_data['status_id']=1;
            $previous_data = getPreviousRecords('ar_asset_inventory', $where);
            $res = updateRecord('ar_asset_inventory', $previous_data, $where, $table_data, $user_id);
            
            if($res['success']==true)
            {
                $where=array(
                    "asset_id"=>$asset_id
                );
              $res=  DB::table('ar_asset_archive')->where($where)->delete();
            if($res==1)
            {
                $res=array(
                    "success"=>true,
                    "message"=>"Asset has been restored"
                );
                $this->insert_into_asset_history($asset_id,"Removed from Archive");
            }else{
                $res=array(
                    "success"=>false,
                    "message"=>"Asset restore failed"
                );
            }
            }

        }
        return response()->json($res);
    }
    public function saveMultipleusersBulkCheckInDetails(Request $request)
    {
        try{
            $asset_id = $request->input('assets_for_individuals');
            $requisition_record_id=$request->input('requisition_ids');
          
            $site_id = $request->input('checkin_site_id');
            $location_id = $request->input('checkin_location_id');
            $department_id = $request->input('checkin_department_id');
            $checkout_id=$request->input('checkout_id');
            $return_date=$request->input('return_date');
            $user_id = $this->user_id;
            $table_data = array(
                
                'checkout_id'=>$checkout_id,
                'return_date'=>$return_date,
                'checkin_site_id' => $site_id,
                'checkin_location_id' => $location_id,
                'checkin_department_id' => $department_id,
               
                
            );
            
            $res = insertRecord('ar_asset_checkin_details', $table_data, $user_id);
            if ($res['success'] == true) {
                //update requisitions table
                //update checkout status
                DB::table('ar_asset_checkout_details')
                ->where('id',$checkout_id)
                ->update(['checkout_status'=>2]);
          //update asset inventory table
        DB::table('ar_asset_inventory')
        ->where('id',  $asset_id)
        ->update(['status_id'=>1,'site_id'=>$site_id,"location_id"=>$location_id,"department_id"=>$department_id]);//checked in
        if(substr(  $requisition_record_id, -1)==",")
        {
            $requisition_record_id=substr_replace($requisition_record_id, "", -1);
        }
        $requisition_ids_array=explode(',',$requisition_record_id);
            DB::table('ar_asset_requisitions')
            ->whereIn('id', $requisition_ids_array)
            ->update(['requisition_status'=>3]);//checked in for requisitions
        
        //update requistions table
       
        //log history
       $this->insert_into_asset_history($asset_id,"Checked-In");  
       $res=[];
       $res["success"]=true;
       $res['message']="Check-In Details Saved Successfully";

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
    public function SaveSingleSiteAssetCheckInDetails(Request $request)
    {
        try{
           
            $asset_id = $request->input('asset_id');
            $requisition_record_id=$request->input('requisition_record_id');
            $reservation_record_id=$request->input('reservation_record_id');
            $asset_id = $request->input('asset_id');
            $site_id = $request->input('checkin_site_id');
            $location_id = $request->input('checkin_location_id');
            $department_id = $request->input('checkin_department_id');
            $checkout_id=$request->input('checkout_id');
            $return_date=$request->input('return_date');
            $user_id = $this->user_id;
            $table_data = array(
                
                'checkout_id'=>$checkout_id,
                'return_date'=>$return_date,
                'checkin_site_id' => $site_id,
                'checkin_location_id' => $location_id,
                'checkin_department_id' => $department_id,
               
                //"requisition_id"=>$requisition_record_id,
            );
            $res = insertRecord('ar_asset_checkin_details', $table_data, $user_id);
            if ($res['success'] == true) {

                //update reservations table
                if (validateisNumeric($reservation_record_id)) {
                    $reservations_table_update=[
                        "checkin_status"=>2,//checked out
                        
                    ];
                     DB::table('ar_asset_reservations')
                            ->where('id', $reservation_record_id)
                            ->update($reservations_table_update);//checked out
                }
                //update requisitions table
                //update checkout status
                DB::table('ar_asset_checkout_details')
                ->where('id',$checkout_id)
                ->update(['checkout_status'=>2]);
          //update asset inventory table
        DB::table('ar_asset_inventory')
        ->where('id',  $asset_id)
        ->update(['status_id'=>1,'site_id'=>$site_id,"location_id"=>$location_id,"department_id"=>$department_id]);//checked in
        
        //update requistions table
        DB::table('ar_asset_requisitions')
        ->where('id', $requisition_record_id)
        ->update(['requisition_status'=>3]);//checked in for requisitions
        //log history
       $this->insert_into_asset_history($asset_id,"Checked-In");  
       $res=[];
       $res["success"]=true;
       $res['message']="Check-In Details Saved Successfully";

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
    public function SaveSingleSiteAssetCheckOutDetails(Request $request)
    {
        try{
            $asset_id = $request->input('asset_id');
            $reservation_record_id=$request->input('reservation_record_id');
            $requisition_record_id=$request->input('requisition_record_id');
            $asset_id = $request->input('asset_id');
            $site_id = $request->input('checkout_site_id');
            $location_id = $request->input('checkout_location_id');
            $department_id = $request->input('checkout_department_id');
            $user_id = $this->user_id;
            $site_asset_individual_responsible=$request->input('site_asset_individual_responsible');
            $table_data = array(
                'asset_id' => $asset_id,
                'checkout_date' => $request->input('checkout_date'),
                'no_due_date' => $request->input('no_due_date'),
                'due_date' => $request->input('due_date'),
                'checkout_site_id' => $site_id,
                'checkout_location_id' => $location_id,
                'checkout_department_id' => $department_id,
                "checkout_status"=>1,
                "site_asset_individual_responsible"=>$site_asset_individual_responsible,
                //"requisition_id"=>,
                "assignment_category"=>2,
                "is_single_site_checkout"=>1
            );
            if (validateisNumeric($requisition_record_id)) {
                $table_data['requisition_id']=$requisition_record_id;
            }
            
            $res = insertRecord('ar_asset_checkout_details', $table_data, $user_id);
            if ($res['success'] == true) {
                //update requisitions table
                $new_record_id=$res['record_id'];
                
          //update asset inventory table
        DB::table('ar_asset_inventory')
        ->where('id',   $asset_id)
        ->update(['status_id'=>2,'site_id'=>$site_id,"location_id"=>$location_id,"department_id"=>$department_id]);//checked in
        
        //update requistions table
        if (validateisNumeric($requisition_record_id)) {
        DB::table('ar_asset_requisitions')
        ->where('id', $requisition_record_id)
        ->update(['requisition_status'=>2,"is_site_single_checkout"=>1]);//checked in for requisitions
        }else{
            DB::table('ar_asset_reservations')
            ->where('id', $reservation_record_id)
            ->update(['checkin_status'=>1,"checkout_id"=> $new_record_id]);//checked out for reservations,2 for checked in
        }
        //log history
       $this->insert_into_asset_history($asset_id,"checked-Out");  
       $res=[];
       $res["success"]=true;
       $res['message']="Check-Out Details Saved Successfully";

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
    public function saveSiteBulkCheckInDetails(Request $request)
    {
        try{
        //checkin
        $site_id = $request->input('checkin_site_id');
        $location_id = $request->input('checkin_location_id');
        $department_id = $request->input('checkin_department_id');
        $site_id=$request->input('checkin_site_id');
        $requisition_ids=$this->returnArrayFromStringArray($request->input('requisition_record_id'));
        $assets_for_site=$this->returnArrayFromStringArray($request->input('assets_for_site'));
        $checkout_id=$request->input('checkout_id');
        $return_date=$request->input('return_date');
       
        $user_id = $this->user_id;
       
        $table_data['checkin_site_id'] = $site_id;
        $table_data['checkin_location_id'] = $location_id;
        $table_data['checkin_department_id'] = $department_id;
        $table_data['return_date'] = $return_date;
        $table_data['checkout_id'] = $checkout_id;
        
        $res = insertRecord('ar_asset_checkin_details', $table_data, $user_id);
        if ($res['success'] == true) {
        //update checkout status
        DB::table('ar_asset_checkout_details')
        ->where('id', $checkout_id)
        ->update(['checkout_status'=>2]);//checked in
   
          //update asset inventory table
        DB::table('ar_asset_inventory')
        ->whereIn('id',   $assets_for_site)
        ->update(['status_id'=>1,'site_id'=>$site_id,"location_id"=>$location_id,"department_id"=>$department_id]);//checked in
        
        //update requistions table
        DB::table('ar_asset_requisitions')
        ->whereIn('id', $requisition_ids)
        ->update(['requisition_status'=>3]);//checked in for requisitions
        //log history
        foreach($assets_for_site as $asset_id){
        $this->insert_into_asset_history($asset_id,"Checked-In");  
        }
            $res=[];
            $res["success"]=true;
            $res['message']="Check-In Details Saved Successfully";
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
    public function saveIndividualBulkCheckInDetails(Request $request)
    {
        try{
        //checkin
        $site_id = $request->input('checkin_site_id');
        $location_id = $request->input('checkin_location_id');
        $department_id = $request->input('checkin_department_id');
        $site_id=$request->input('checkin_site_id');
        $requisition_ids=$this->returnArrayFromStringArray($request->input('requisition_record_id'));
        $assets_for_individual=$this->returnArrayFromStringArray($request->input('assets_for_individual'));
        $checkout_id=$request->input('checkout_id');
        $location_id=$request->input('checkin_location_id');
        $department_id=$request->input('checkin_department_id');
        $return_date=$request->input('return_date');
        $send_email=$request->input('send_email');
        $user_email=$request->input('user_email');
        $user_id = $this->user_id;
       
        if($send_email=="true")
        {
            $send_email=1;
        }else{
            $send_email=0;
        }
        $table_data['checkin_site_id'] = $site_id;
        $table_data['checkin_location_id'] = $location_id;
        $table_data['checkin_department_id'] = $department_id;
        $table_data['return_date'] = $return_date;
        $table_data['checkout_id'] = $checkout_id;
        $table_data['send_email']=$send_email;
        $table_data['user_email']=$user_email;

    
        $res = insertRecord('ar_asset_checkin_details', $table_data, $user_id);
        if ($res['success'] == true) {
        //update checkout status
        DB::table('ar_asset_checkout_details')
        ->where('id', $checkout_id)
        ->update(['checkout_status'=>2]);//checked in
   
          //update asset inventory table
        DB::table('ar_asset_inventory')
        ->whereIn('id',   $assets_for_individual)
        ->update(['status_id'=>1,'site_id'=>$site_id,"location_id"=>$location_id,"department_id"=>$department_id]);//checked in
        
        //update requistions table
        DB::table('ar_asset_requisitions')
        ->whereIn('id', $requisition_ids)
        ->update(['requisition_status'=>3]);//checked in for requisitions
        //log history
        foreach($assets_for_individual as $asset_id){
        $this->insert_into_asset_history($asset_id,"checked-in");  
        }
            $res=[];
            $res["success"]=true;
            $res['message']="Check-In Details Saved Successfully";
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
    
    public function saveAssetCheckOutDetails(Request $request)
    {
        try {
            
            $id = $request->input('checkout_id');
            $asset_id = $request->input('asset_id');
            $site_id = $request->input('checkout_site_id');
            $location_id = $request->input('checkout_location_id');
            $department_id = $request->input('checkout_department_id');
            $user_id = $this->user_id;
            $assign_to_id=$request->input('assign_to_id');
            $table_name = 'ar_asset_checkout_details';
            $table_data = array(
                'asset_id' => $asset_id,
                'user_id' => $request->input('user_id'),
                'checkout_date' => $request->input('checkout_date'),
                'no_due_date' => $request->input('no_due_date'),
                'due_date' => $request->input('due_date'),
                'send_email' => $request->input('send_email'),
                'user_email' => $request->input('user_email'),
                'checkout_site_id' => $site_id,
                'checkout_location_id' => $location_id,
                'checkout_department_id' => $department_id,
            );
            $where = array(
                'id' => $id
            );
           
            if (isset($id) && $id != "") {
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $previous_data = getPreviousRecords($table_name, $where);
                $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
            } else {
                $table_data['assignment_category']=$assign_to_id;
                $table_data['created_at'] = Carbon::now();
                $table_data['created_by'] = $user_id;
                $res = insertRecord($table_name, $table_data, $user_id);
                if ($res['success'] == true) {
                    //update asset details
                    $asset_update = array(
                        'status_id' => 2,
                        'site_id' => $site_id,
                        'location_id' => $location_id,
                        'department_id' => $department_id
                    );
                    DB::table('ar_asset_inventory')
                        ->where('id', $asset_id)
                        ->update($asset_update);//checked out
                   
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

    public function getAssetCheckOutDetails(Request $request)
    {
        $user_filter=$request->query('user_filter');
        try {
            $qry = DB::table('ar_asset_checkout_details as t1')
                ->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
                ->leftjoin('ar_asset_requisitions as t8','t8.checkout_id','t1.id')
                ->join('users as t3', 't1.user_id', '=', 't3.id')
                ->join('ar_asset_sites as t4', 't1.checkout_site_id', '=', 't4.id')
                ->leftjoin('ar_asset_locations as t5', 't1.checkout_location_id', '=', 't5.id')
                ->leftjoin('departments as t6', 't1.checkout_department_id', '=', 't6.id')
                ->leftJoin('ar_asset_checkin_details as t7', 't1.id', '=', 't7.checkout_id')
                ->selectRaw("t1.id as checkout_id,t1.*,t2.description,t2.serial_no,t2.grz_no,t4.name as site_name,t5.name as location_name,
                        t2.site_id,t2.location_id,t2.department_id,CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as assigned_user,t6.name as department_name,
                        t7.return_date,t7.checkin_site_id,t7.checkin_location_id,t7.checkin_department_id")
                    ->orwhere('t8.is_single_checkout',1)
                    ->where('t2.module_id',350);

                if (validateisNumeric($user_filter)) {
                    $qry->where('t1.user_id',$this->user_id);
                }
            $results = $qry->get();

           $qry= DB::table('ar_asset_checkout_details as t1')
                ->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
                ->join('ar_asset_reservations as t8','t8.checkout_id','=', 't1.id')
                ->join('users as t3', 't1.user_id', '=', 't3.id')
                ->join('ar_asset_sites as t4', 't1.checkout_site_id', '=', 't4.id')
                ->leftjoin('ar_asset_locations as t5', 't1.checkout_location_id', '=', 't5.id')
                ->leftjoin('departments as t6', 't1.checkout_department_id', '=', 't6.id')
                ->leftJoin('ar_asset_checkin_details as t7', 't1.id', '=', 't7.checkout_id')
                ->selectRaw("t1.id as checkout_id,t1.*,t2.description,t2.serial_no,t2.grz_no,t4.name as site_name,t5.name as location_name,
                        t2.site_id,t2.location_id,t2.department_id,CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as assigned_user,t6.name as department_name,
                        t7.return_date,t7.checkin_site_id,t7.checkin_location_id,t7.checkin_department_id,t8.id as reservation_record_id ")
                        ->where('t2.module_id',350);
                       
                     
            $combined_results= array_merge($results->toArray(),$qry->get()->toArray());
            
            $res = array(
                'success' => true,
                'message' => returnMessage($combined_results),
                'results' => $combined_results,
               
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
    
    private function sendAssetCheckOutEmail($mail_to_person,$subject,$param_data,$isIndividualbulk=false,$isReservation=false)
    {
        $res=array();
    
        $url=url('/');
        $image_url=$url.'/backend/public/moge_logo.png';
        $data=array();

        if($mail_to_person==""){
            $email_array=$param_data;


            $users_email_details=$param_data;
            foreach($users_email_details as $details)
            {
                $data_data=array(
                    "checkout_date"=>$details['checkout_date'],
                    "no_due_date"=>$details['no_due_date'],
                    "description"=>$details['asset']['description'],
                    "user_last_name"=>$details['last_name'],
                    "identifier"=>$details['identifier'],
                    //"serial_no"=>$details['asset']['serial_no']
                );
                $user_serial_no=1;
               
                if(array_key_exists("grz_no",$details['asset']))
                {
                   $data_data['grz_no']=$details['asset']['grz_no'];
                   $user_serial_no=0;
                }else{
                    $data_data['serial_no']=$details['asset']['serial_no'];
                }
                
                $mail_to_person=$details['user_email'];
                //$mail_to_person="murumbajob78@gmail.com";
               
                if($details['no_due_date']=="0" || $details['no_due_date']==0 )
                {
                    $data_data['due_date']=$details['due_date'];
                }
                
                $data=[
                    'isIndividualbulk'=>0,//previosuly false
                    "isReservation"=>0,
                    "data"=>$data_data,
                    "user_last_name"=>$details['last_name'],
                    "image_url"=>$image_url,
                    "user_serial_no"=>$user_serial_no,
                    //"identifier"=>$details['identifier']
                ];
                
                $result= Mail::send('mail.assetCheckout',$data,function($message) use($mail_to_person,$subject,$data){
                    $message->to($mail_to_person,$mail_to_person);
                    $message->subject($subject);
                    $message->from('mogekgs@gmail.com','MOE');
                });

            }
        }else{
        if($isIndividualbulk==true)
        {
           
            // $full_email_data["email_data"]=$email_data;
            // $full_email_data["assets"]= $email_assets;
     
            //$user_email=$param_data['email_data']['user_email'];
            $data['image_url']=$image_url;
            $data['isIndividualbulk']=1;
            $data['no_due_date']=0;
            $data['checkout_date']=date('d-m-Y',strtotime($param_data['email_data']['checkout_date']));
            $data['due_date']=$param_data['email_data']['due_date'];
            $data['user_last_name']=$param_data['email_data']['user_last_name'];
            if($param_data['email_data']['no_due_date']==0)
            {
                $data['due_date']=date('d-m-Y',strtotime($param_data['email_data']['due_date']));
            }
            //$data['assets']=$param_data['assets'];
           
             
            $data=[
                'isIndividualbulk'=>1,
                'isReservation'=>0,
                "data"=>$data,
                "assets"=>$param_data['assets'],
                "image_url"=>$image_url
            ];

            

        }else{
            $use_serial_no;
            if($isReservation==false){
            $user_last_name=DB::table('users')->where('id',$param_data['user_id'])->selectRaw('decrypt(last_name)')->value('last_name');
            $param_data['user_last_name']=$user_last_name;
            unset($param_data['user_id']);
            if(array_key_exists('serial_no',$param_data))
            {
                $use_serial_no=1;
            }else{
                $use_serial_no=0;
            }
            
            $identifier=$param_data['identifier'];
            unset($param_data['identifier']);
            $data=[
                'isIndividualbulk'=>0,
                "data"=>$param_data,
                "user_last_name"=>$user_last_name,
                "image_url"=>$image_url,
                "user_serial_no"=>$use_serial_no,
                "isReservation"=>0,
                "identifier"=>$identifier
            ];
         }else{
            $use_serial_no;
            $user_last_name=DB::table('users')->where('id',$param_data['user_id'])->selectRaw('decrypt(last_name)')->value('last_name');
            unset($param_data['user_id']);
            $param_data['user_last_name']=$user_last_name;
            if(array_key_exists('serial_no',$param_data))
            {
                $use_serial_no=1;
            }else{
                $use_serial_no=0;
            }
            $identifier=$param_data['identifier'];
            unset($param_data['identifier']);
            $data=[
                'isIndividualbulk'=>0,
                "data"=>$param_data,
                "user_last_name"=>$user_last_name,
                "image_url"=>$image_url,
                "isReservation"=>1,
                "user_serial_no"=>$use_serial_no,
                "identifier"=>$identifier
            ];
         }

           
        }
    }
    
    if($mail_to_person!=""){
        $mail_to_person="murumbajob78@gmail.com";
        $result= Mail::send('mail.assetCheckout',$data,function($message) use($mail_to_person,$subject,$data){
            $message->to($mail_to_person,$mail_to_person);
            $message->subject($subject);
            $message->from('mogekgs@gmail.com','MOE');
        });
    }
    
    

    }

    
    public function saveAssetCheckOutInDetails(Request $request)
    {
        try {
           
           
            $checkin_status=$request->input('checkin_status');
            $reservation_record_id=$request->input('reservation_record_id');
            $checkout_id = $request->input('checkout_id');
            $checkin_id = $request->input('checkin_id');
            $asset_id = $request->input('asset_id');
            $operation_type = $request->input('operation_type');
            $table_name = $request->input('table_name');
            $requisition_record_id= $request->input('requisition_record_id');
            $user_id = $this->user_id;
            $table_data = array(
                'send_email' => $request->input('send_email'),
                'user_email' => $request->input('user_email')
            );
        
            $identifier_name=Db::table('ar_asset_inventory as t1')
            ->join('ar_asset_identifiers as t2','t1.identifier_id','t2.id')
            ->where('t1.id',$asset_id)
            ->value('t2.name');
        
         
            if ($operation_type == 'check-in') {
                $asset_status_id = 1;
                $checkout_status_id = 2;
                $id = $checkin_id;
                $site_id = $request->input('checkin_site_id');
                $location_id = $request->input('checkin_location_id');
                $department_id = $request->input('checkin_department_id');
                $table_data['checkin_site_id'] = $site_id;
                $table_data['checkin_location_id'] = $location_id;
                $table_data['checkin_department_id'] = $department_id;
                $table_data['return_date'] = $request->input('return_date');
                $table_data['checkout_id'] = $checkout_id;
                $this->insert_into_asset_history($asset_id,"Checked-In");
            } else {
                $asset_status_id = 2;
                $checkout_status_id = 1;
                $id = $checkout_id;
                $site_id = $request->input('checkout_site_id');
                $location_id = $request->input('checkout_location_id');
                $department_id = $request->input('checkout_department_id');
                $table_data['checkout_site_id'] = $site_id;
                $table_data['checkout_location_id'] = $location_id;
                $table_data['checkout_department_id'] = $department_id;
                $table_data['user_id'] = $request->input('user_id');
                $table_data['checkout_date'] = $request->input('checkout_date');
                $table_data['no_due_date'] = $request->input('no_due_date');
                $table_data['due_date'] = $request->input('due_date');
                $table_data['asset_id'] = $asset_id;
                $table_data['requisition_id']=$requisition_record_id;
                $this->insert_into_asset_history($asset_id,"Checked-Out");
            }
           
            $where = array(
                'id' => $id
            );
           
            if (validateisNumeric($id)) {
               
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $previous_data = getPreviousRecords($table_name, $where);
                $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
            } else {
               
                $table_data['created_at'] = Carbon::now();
                $table_data['created_by'] = $user_id;
                $res = insertRecord($table_name, $table_data, $user_id);
               
               
                //  $res=[];
                //  $res['success']= true;
                if ($res['success'] == true) {

                
                    //update asset details
                    $asset_update = array(
                        'status_id' => $asset_status_id,
                        'site_id' => $site_id,
                        'location_id' => $location_id,
                        'department_id' => $department_id
                    );
                   
                    DB::table('ar_asset_inventory')
                        ->where('id', $asset_id)
                        ->update($asset_update);//checked in
                   
                    //update checkout status
                   
                     if (validateisNumeric($checkout_id)) {
                        
                        DB::table('ar_asset_checkout_details')
                        ->where('id', $checkout_id)
                        ->update(array('checkout_status' => $checkout_status_id));
                     }
           
                 

                        //if(isset($checkin_status) && $checkin_status!=""){
                          
                        
                            if($operation_type != 'check-in'){//checkout
                                $new_checkout_id=$res['record_id'];
                                
                        if (validateisNumeric($reservation_record_id)) {
                            $reservations_table_update=[
                                "checkin_status"=>1,//checked out
                                "checkout_id"=>$new_checkout_id
                            ];
                             DB::table('ar_asset_reservations')
                                    ->where('id', $reservation_record_id)
                                    ->update($reservations_table_update);//checked out
                        }
                        if($table_data['send_email']==true)
                        {   $mail_to=$table_data['user_email'];
                            $subject = "Asset Check-Out";
                            $data=array(
                                "user_id"=>$request->input('user_id'),
                                "description"=>$request->input('description'),
                                //"serial_no"=>$request->input('serial_no'),
                                "no_due_date"=>$request->input('no_due_date'),//0 for due date,
                                "checkout_date"=>date('d-m-Y',strtotime( $request->input('checkout_date'))),    
                            ); 
                            if($request->input('serial_no')!="")
                            {
                                $data['serial_no']=$request->input('serial_no');

                            }else{
                                $data['grz_no']=$request->input('grz_no');
                            }
                            if($request->input('no_due_date')==0)
                            {
                               
            
                                $data["due_date"]=date('d-m-Y',strtotime($request->input('due_date')));
                            }
                            $data['identifier']=$identifier_name;
                          //$this->sendAssetCheckOutEmail("murumbajob78@gmail.com","Asset Check-Out",$data);
                          //$this->sendAssetCheckOutEmail("job.murumba@softclans.co.ke","Asset Check-Out",$data);
                         $this->sendAssetCheckOutEmail($mail_to,"Asset Check-Out",$data);
                        }
                            if (validateisNumeric($requisition_record_id)) { 
                           
                            $requisition_table_update=[
                                "requisition_status"=>2,
                                "is_single_checkout"=>1,
                                "checkout_id"=>$new_checkout_id
                            ];//to do make sure it gets and updates checkout id
                          
                            $where=[
                                "id"=>$requisition_record_id,
                            ];
                            
                            $previous_data = getPreviousRecords('ar_asset_requisitions', $where);
                            $res = updateRecord('ar_asset_requisitions', $previous_data, $where,  $requisition_table_update, $user_id);
                        }
                        }else{//checkin
                          
                            $reservations_table_update=[
                                "checkin_status"=>2//checked in
                            ];
                            
                          
                               
                               if (validateisNumeric( $reservation_record_id)) {
                                   
                                DB::table('ar_asset_reservations')
                                ->where('id', $reservation_record_id)
                                ->update($reservations_table_update);//checked in
                                $requisition_table_update=[
                                    "requisition_status"=>3
                                ];
                                 }
                                 if (validateisNumeric($requisition_record_id)){
                                    $where=[
                                        "id"=>$requisition_record_id,
                                        "requisition_status"=>3
                                    ];
                                    $previous_data = getPreviousRecords('ar_asset_requisitions', $where);
                                    $res = updateRecord('ar_asset_requisitions', $previous_data, $where,  $requisition_table_update, $user_id);
                                }


                                }
                            
                        //}//commented set checkin status
                    
                    
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


    public function getPreUploadedAssets(Request $req)
    {
        $batch_id=$req->input('batch_id');
        $qry = DB::table('assets_pre_upload as t1')
                ->join('ar_asset_categories as t2', 't1.category_id', '=', 't2.id')
                ->leftjoin('ar_asset_subcategories as t12', 't1.sub_category_id', '=', 't12.id')//new late fix
                ->leftjoin('ar_asset_brands as t3', 't1.brand_id', '=', 't3.id')
                ->leftjoin('ar_asset_models as t4', 't1.model_id', '=', 't4.id')
                ->leftjoin('ar_asset_sites as t5', 't1.site_id', '=', 't5.id')
                ->leftjoin('users as t6','t6.id','t1.individual_id')
                
                ->selectRaw("t1.*,t1.id as batch_id, t1.id as record_id,t1.id as asset_id, t2.name as category_name, t3.name as brand_name, t4.name as model_name,t1.location,
                    
                     CONCAT_WS('-',t1.description,t1.grz_no) as desc_grz_no,  CONCAT_WS('-',t1.description,t1.serial_no) as desc_serial_no,t5.name as site,                             
                    t12.name as sub_category_name,CONCAT_WS(' ',decrypt(t6.first_name),decrypt(t6.last_name)) as individual,checkout_type as checkout_type_id,t6.id as user_id,t1.upload_status as status");
                    if (validateisNumeric($batch_id)) {
                        $qry->where('t1.batch_id',$batch_id);
                    }
                    $results = $qry->get()->toArray();
                    foreach($results as $key=>$result)
                    {
                        if($result->disposal_details!=null)
                        {
                            // $disposal_details=array(
                            //     "date_of_disposal"=> $date_of_disposal,
                            //     'disposal_reason'=>$disposal_reason,
                            //     'disposal_method'=> $disposal_method,
                            //     'remarks'=>$remarks
                            // );
                            $disposal_details=json_decode($result->disposal_details);
                            $result->date_of_disposal=$disposal_details->date_of_disposal;
                            $result->disposal_reason=$disposal_details->disposal_reason;
                            $result->disposal_method=$disposal_details->disposal_method;
                            $result->remarks=$disposal_details->remarks;
                            $results[$key]=$result;
                            //$loss_details->individuals_responsible
                        }
                        if($result->loss_details!=null)
                        {
                            $loss_details=json_decode($result->loss_details);
                            $result->date_lost_damaged=$loss_details->loss_damage_date;
                            $result->document_name=$loss_details->document_name;
                            $result->user_id=$this->returnArrayFromStringArray($loss_details->individuals_responsible);
                            $result->remarks=$loss_details->remarks;
                            $result->lost_damaged_in_site_id=$loss_details->lost_damaged_site_id;
                            $result->lost_damaged_in_location_id=$loss_details->lost_damaged_location_id;
                            $result->lost_damaged_in_department_id=$loss_details->lost_damaged_department_id;
                            $results[$key]=$result;

                        }
                        
                    }

                  
                    $res = array(
                        'success' => true,
                        'message' => returnMessage($results),
                        'results' => $results
                    );
                    return response()->json($res);
               
    }
public function getSystemUsers(){
   
        $qry = DB::table('users')
            ->select(DB::raw("users.id,  CONCAT_WS(' ',decrypt(users.first_name),decrypt(users.last_name)) as names"))
            ->whereNotIn('users.id', function ($query) {
                $query->select(DB::raw('blocked_accounts.account_id'))
                    ->from('blocked_accounts');
            });
            $data=$qry->get();
            $res = array(
                'success' => true,
                 'results' => $data
            );
            return response()->json($res);
}

public function getSites(){
    $qry=db::table('ar_asset_sites')
    ->selectraw('id,name');
    $results = $qry->get();
    $res = array(
        'success' => true,
         'results' => $results
    );
    return response()->json($res);
}

public function saveAssetMappingDetails(Request $req)
{
    $record_id= $req->input('record_id');
    $checkout_type_id = $req->input('checkout_type_id');
    $site_id = $req->input('site_id');
    $user_id = $req->input('user_id');
    $table_data=array();
    if($checkout_type_id==2){
        $table_data['Individual_id']=$user_id;
    }
    $table_data['site_id']=$site_id;
    $table_data['checkout_type']=$checkout_type_id;
    $table_data['status_id']=$req->input('status_id');//mapped
    $table_data['upload_status']=2;
    $table_data['updated_at'] = Carbon::now();
    $table_data['updated_by'] = $this->user_id;
    $where=array(
        'id'=>$record_id
    );
   
    $previous_data = getPreviousRecords('assets_pre_upload', $where);
    $res = updateRecord('assets_pre_upload', $previous_data, $where, $table_data, $this->user_id);
    $res = array(
        'success' => true,
        'message' => "Asset Data Mapped Successfully",
       
    );
    return response()->json($res);

}

public function UploadMappedAssets(Request $req){
    $selected = $req->input('selected');
    $selected_ids = json_decode($selected);
    $asset_insert = array();
    $res=array();
    $result;
        try{
    //return response()->json($res); 
        foreach ($selected_ids as $selected_id) {
            $asset_data=Db::table('assets_pre_upload')->selectraw('*')->where('id',$selected_id)->first();
            $params = array(
                'description' => $asset_data->description,
                'category_id' =>  $asset_data->category_id,
                'brand_id' => $asset_data->brand_id,
                'model_id' =>  $asset_data->model_id,
                'purchase_from' => $asset_data->purchase_from,
                'purchase_date' => $asset_data->purchase_date,
                'cost' => $asset_data->cost,
                'supplier_id'=>$asset_data->supplier_id,//Job on 30/06/2022
                'sub_category_id'=>$asset_data->sub_category_id,
                'serial_no' =>$asset_data->serial_no,
                'grz_no' => $asset_data->grz_no,
                'site_id' => $asset_data->site_id,
                'status_id'=>$asset_data->status_id,
                // 'location_id' => $asset_data->location,
                // 'department_id' => $asset_data->department_id,
                'identifier_id' => $asset_data->identifier_id,//job on 26/5/2022,
                "view_id"=>$asset_data->view_id,
                "folder_id"=>$asset_data->folder_id,
                "created_at"=>Carbon::now(),
                "created_by"=>$this->user_id,
                'module_id'=>350,
            );
           
           $asset_id= Db::table('ar_asset_inventory')->insertGetId($params);
           if($asset_data->status_id==9){
          
            if($asset_data->disposal_details!=null)
            {
                $disposal_data=array();
                $disposal_details=json_decode($asset_data->disposal_details);
                $disposal_data['asset_id']=$asset_id;
                $disposal_data['date_of_disposal']=$disposal_details->date_of_disposal;
                $disposal_data['disposal_reason']=$disposal_details->disposal_reason;
                $disposal_data['disposal_method']=$disposal_details->disposal_method;
                $disposal_data['remarks']=$disposal_details->remarks;
                $disposal_data['created_by']=$this->user_id;
                $disposal_data['created_at']=Carbon::now();
                Db::table('ar_asset_disposal_details')->insert($disposal_data);
            }

           
          }

          if($asset_data->status_id==4){
          
          if($asset_data->loss_details!=null)
          { 
              $loss_data=array();
              $loss_details=json_decode($asset_data->loss_details);
              $loss_data['asset_id']=$asset_id;
              $loss_data['reported_by']=$loss_details->reported_by;
              $loss_data['loss_damage_date']=$loss_details->loss_damage_date;
              $loss_data['individuals_responsible']=$loss_details->individuals_responsible;
              $loss_data['lost_damaged_site_id']=$loss_details->lost_damaged_site_id;
              $loss_data['lost_damaged_location_id']=$loss_details->lost_damaged_location_id;
              $loss_data['lost_damaged_department_id']=$loss_details->lost_damaged_department_id;
              $loss_data['created_by']=$this->user_id;
              $loss_data['created_at']=Carbon::now();
              $loss_data['remarks']=$loss_details->remarks;
              Db::table('ar_asset_disposal_details')->insert($loss_data);
          }
        }
            $upload_data=[
                "request_date"=>carbon::now(),
                "requistion_site_id"=>$asset_data->site_id,
                //"requistion_location_id"=>$location_id,
                //"requistion_department_id"=>$department_id,
                "requested_for"=>$asset_data->checkout_type,
                "asset_category"=>$asset_data->sub_category_id,
                "created_at"=>Carbon::now(),
                "created_by"=> $this->user_id,
                "verified"=>1,
                "remarks"=>"Asset  assignment on upload ",
                'requisition_status'=>1,
                'requested_by'=>$asset_data->checkout_type==2?$asset_data->Individual_id:0,
                "onbehalfof"=> 1,
                "is_single_checkout"=>$asset_data->checkout_type==2?:0,
                "is_site_single_checkout"=>$asset_data->checkout_type==1?1:0,
          ];
         
          $requisition_id="";
          if($asset_data->status_id==2){
          $requisition_id= Db::table('ar_asset_requisitions')->insertGetId($upload_data);
          }
          //checkout asset
          $table_name = 'ar_asset_checkout_details';
          $table_data = array(
              'asset_id' => $asset_id,
              'user_id' => $asset_data->checkout_type==2? $asset_data->Individual_id:0, 
              'checkout_date' => Carbon::now(),
              'no_due_date' => 1,
              //'due_date' => $request->input('due_date'),
              'send_email' => 0,
             // 'user_email' => $request->input('user_email'),
              'checkout_site_id' => $asset_data->site_id,
              'checkout_status'=>1,
              'requisition_id'=> $requisition_id,
              "is_single_site_checkout"=>$asset_data->checkout_type==1?1:0,
              "site_asset_individual_responsible"=>$asset_data->Individual_id,
              //'checkout_location_id' => $location_id,
              //'checkout_department_id' => $department_id,
          );
          if($asset_data->status_id==2){
          $checkout_id= Db::table($table_name)->insertGetId($table_data);
          $result=Db::table('ar_asset_requisitions')
          ->where('id',$requisition_id)
          ->update(array(
            "checkout_id"=> $checkout_id
          ));
        }

       
          $result=DB::table('assets_pre_upload')->where('id',$selected_id)->update(['upload_status'=>3]);//is uploaded
        }

        if($result)
                {
                    $res=array(
                        "success"=>true,
                        "message"=>"Assets Uploaded To Inventory"
                    );
                }else{
                    $res=array(
                        "success"=>false,
                        "message"=>"Assets Upload Failed"
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

public function getNextAssetBatchNo(){
    try{
    $latest_batch= DB::table('ar_asset_upload_batches')->latest('created_at')->first();
    if(!is_object($latest_batch))
    {
        $next_batch_no="KGS-ARU-01";
        $res = array(
            'success' => true,
            'results' =>   $next_batch_no
             );
        return response()->json($res);  
    }

    $latest_batch_no=$latest_batch->batch_no;
    $last_number=explode('-',$latest_batch_no)[2];
    $last_number=intval($last_number);
    $next_number=$last_number+1;
    if($next_number<10)
    {
        $next_number="0$next_number";
    }
    $next_batch_no="KGS-ARU-$next_number";
    $res = array(
        'success' => true,
        'results' =>   $next_batch_no
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

public function getAssetUploadBatches()
{
    $qry=Db::table('ar_asset_upload_batches as t1')
    ->join('users as t2','t2.id','t1.created_by')
    ->selectraw("t1.id,batch_no,t1.id as batch_id,description,t1.created_at as upload_date,CONCAT_WS(' ',decrypt(t2.first_name),decrypt(t2.last_name)) as uploaded_by,t1.status");
    $results=convertStdClassObjToArray($qry->get());
   foreach($results as $key=>$result)
   {
    $count=Db::table('assets_pre_upload')->where('batch_id',$result['id'])->where('status_id','<',3)->count();
    if($count>0)
    {
        $result['status']=0;
    }else{
        $result['status']=1;
    }
    $results[$key]=$result;
   }
    $res = array(
        'success' => true,
        'results' =>  $results
         );
    return response()->json($res);
}

public function saveAssetMappingDetailsForDisposal(Request $req)
{
    $record_id= $req->input('record_id');
    $date_of_disposal = $req->input('date_of_disposal');
    $disposal_reason = $req->input('disposal_reason');
    $disposal_method = $req->input('disposal_method');
    $remarks = $req->input('remarks');
    $disposal_details=array(
        "date_of_disposal"=> $date_of_disposal,
        'disposal_reason'=>$disposal_reason,
        'disposal_method'=> $disposal_method,
        'remarks'=>$remarks
    );
    $table_data=array();
    $table_data['status_id']=$req->input('status_id');//actual asset status
    $table_data['upload_status']=2;//mapped
    $table_data['updated_at'] = Carbon::now();
    $table_data['updated_by'] = $this->user_id;
    $table_data['disposal_details']=json_encode($disposal_details);
    $where=array(
        'id'=>$record_id
    );
   
    $previous_data = getPreviousRecords('assets_pre_upload', $where);
    $res = updateRecord('assets_pre_upload', $previous_data, $where, $table_data, $this->user_id);
    $res = array(
        'success' => true,
        'message' => "Asset Data Mapped Successfully",
       
    );
    return response()->json($res);
}


public function saveAssetMappingDetailsForLostAsset(Request $req)
{
    $record_id= $req->input('record_id');
    //$lost_damaged_id = $req->input('lost_damaged_id');
    $date_lost_damaged = $req->input('date_lost_damaged');
    $document_name = $req->input('document_name');
    $individuals_responsible = $req->input('user_id');
    $remarks = $req->input('remarks');
    $lost_damaged_in_site_id = $req->input('lost_damaged_in_site_id');
    $lost_damaged_in_location_id = $req->input('lost_damaged_in_location_id');
    $lost_damaged_in_department_id = $req->input('lost_damaged_in_department_id');
    $folder_id=Db::table('assets_pre_upload')->where('id',$record_id)->value('folder_id');
    createAssetRegisterDMSModuleFolders($folder_id, 34,41, $this->dms_id);   
    $req->merge(['dms'=>$this->dms_id,"comment"=>"none","folder_id"=>$folder_id]);
    $dms=new  DmsController();
    $dms->addDocumentNoFolderIdForAssetLoss(  $req->input('parent_folder_id'),$req->input('sub_module_id'),
    $this->dms_id,$req->input('document_name'),"None",$req->versioncomment);
    $lost_details=array(
        "lost_damaged_id"=> 4,
        'reported_by'=>$this->user_id,
        'loss_damage_date'=> $date_lost_damaged,
        'remarks'=>$remarks,
        'individuals_responsible'=>$individuals_responsible,
        "lost_damaged_site_id"=> $lost_damaged_in_site_id,
        "lost_damaged_location_id"=> $lost_damaged_in_location_id,
        "lost_damaged_department_id"=> $lost_damaged_in_department_id,
        'document_name'=>$document_name
    );  
    $table_data=array();
    $table_data['status_id']=$req->input('status_id');//actual asset status
    $table_data['upload_status']=2;//mapped
    $table_data['updated_at'] = Carbon::now();
    $table_data['updated_by'] = $this->user_id;
    $table_data['loss_details']=json_encode($lost_details);
    $where=array(
        'id'=>$record_id
    );
    $previous_data = getPreviousRecords('assets_pre_upload', $where);
    $res = updateRecord('assets_pre_upload', $previous_data, $where, $table_data, $this->user_id);
    $res = array(
        'success' => true,
        'message' => "Asset Data Mapped Successfully",
       
    );
    return response()->json($res);
}

public function saveStoresAssetInventoryDetails(Request $request)
{
    
    try {
        $res = array();
        DB::transaction(function () use ($request, &$res) {
            $user_id = $this->user_id;
            $record_id = $request->input('record_id');
            $serial_no = $request->input('serial_no');
            $grz_no = $request->input('grz_no');
            $description = $request->input('description');
            $table_name = 'ar_asset_inventory';
            $params = array(
                'module_id'=>637,
                'description' => $description,
                'category_id' => $request->input('category_id'),
                'brand_id' => $request->input('brand_id'),
                'model_id' => $request->input('model_id'),
                'purchase_from' => $request->input('purchase_from'),
                'purchase_date' => $request->input('purchase_date'),
                'cost' => $request->input('cost'),
                'supplier_id'=>$request->input('supplier_id'),//Job on 30/06/2022
                'sub_category_id'=>$request->input('sub_category_id'),
                'serial_no' => $serial_no,
                'grz_no' => $grz_no,
                'site_id' => $request->input('site_id'),
                'location_id' => $request->input('location_id'),
                'department_id' => $request->input('department_id'),
                'identifier_id' => $request->input('identifier_id')//job on 26/5/2022
            );

        
            $where = array(
                'id' => $record_id
            );
          
            if (validateisNumeric($record_id)) {
                $previous_data = array();
                if (recordExists($table_name, $where)) {
                    $params['updated_at'] = Carbon::now();
                    $params['updated_by'] = $user_id;
                    $previous_data = getPreviousRecords($table_name, $where);
                    $res = updateRecord($table_name, $previous_data, $where, $params, $user_id);
                    $this->insert_into_asset_history($record_id,"Details Updated");
                }
                $folder_id = $previous_data[0]['folder_id'];
            
              
            } else {
              $if_duplicate_serial_no=DB::table('ar_asset_inventory')->where('serial_no', $serial_no)->value('serial_no');
            if((strtolower($if_duplicate_serial_no))!=(strtolower($serial_no))){
              
              $params['created_at'] = Carbon::now();
                $params['created_by'] = $user_id;
                //todo DMS
                //$parent_id = 226347;	was initial
                //$parent_id=	226531;
                $parent_id=	226347;
               $parent_id=228976;//live
                //$parent_id=226356;    dd($serial_no);
                //job u
                //createDMSParentFolder($parent_folder, $module_id, $name, $comment, $owner)

                //$folder_id = createDMSParentFolder($parent_id,34 , $serial_no, '', $this->dms_id);
                if($serial_no=="")
                {
                    $serial_no= $grz_no;
                }
                $folder_id=createAssetDMSParentFolder($parent_id,34 , $serial_no, '', $this->dms_id);
                //$folder_id = createDMSParentFolder($parent_id, '', $serial_no, '', $this->dms_id);
                createAssetRegisterDMSModuleFolders($folder_id, 34,35, $this->dms_id);
                //$folder_id = 226531;
                //createDMSModuleFolders($folder_id, 35, $this->dms_id);
                //end DMS
                $params['view_id'] = generateRecordViewID();
                $params['serial_no'] = $serial_no;
                $params['folder_id'] = $folder_id;
                $params['created_at'] = Carbon::now();
                $params['created_by'] = $user_id;
                $res = insertRecord($table_name, $params, $user_id);
             }else{
                 $res=array(
                     "success"=>false,
                     "message"=>"Asset with matching Identifier exists"
                 );
             }
                if ($res['success'] == false) {
                  
                    return \response()->json($res);
                }
               
                $record_id = $res['record_id'];
                $this->insert_into_asset_history($record_id,"Added to inventory");
            }
            $res['description'] = $description;
            $res['grz_no'] = $grz_no;
            $res['serial_no'] = $serial_no;
            $res['folder_id'] = $folder_id;
            $res['record_id'] = $record_id;

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

public function getStoresAssetInventoryDetails(Request $request)
{
  
    try {
        $status_id = $request->input('status_id');
        $requested_category_id= $request->input('requested_category_id');//is the subcategory id

        $qry = DB::table('ar_asset_inventory as t1')
            ->join('stores_categories as t2', 't1.category_id', '=', 't2.id')
            ->leftjoin('stores_subcategories as t12', 't1.sub_category_id', '=', 't12.id')//new late fix
            ->leftjoin('stores_brands as t3', 't1.brand_id', '=', 't3.id')
            ->leftjoin('stores_models as t4', 't1.model_id', '=', 't4.id')
            ->leftjoin('stores_sites as t5', 't1.site_id', '=', 't5.id')
            ->leftjoin('stores_locations as t6', 't1.location_id', '=', 't6.id')
            ->leftjoin('stores_departments as t7', 't1.department_id', '=', 't7.id')
            ->join('stores_identifiers as t9','t1.identifier_id','t9.id')
            ->join('stores_statuses as t8', 't1.status_id', '=', 't8.id')
            ->leftjoin('stores_suppliers as t13', 't1.supplier_id', '=', 't13.id')
            //->leftJoin('ar_asset_maintainance as t9', 't1.id', '=', 't9.asset_id')//was letjin
            //->leftJoin('ar_asset_repairs as t10', 't1.id', '=', 't10.asset_id')//was eleftjoin
            ->leftjoin('stores_depreciation as t11','t11.asset_id','t1.id')
            ->selectRaw("t1.*, t1.id as record_id,t1.id as asset_id, t2.name as category_name, t3.name as brand_name, t4.name as model_name,t5.name as site_name,
                 t6.name as location_name,t7.name as department_name, t8.name as record_status,t9.name as unique_identifier_type,
                 CONCAT_WS('-',t1.description,t1.grz_no) as desc_grz_no,  CONCAT_WS('-',t1.description,t1.serial_no) as desc_serial_no,
                 maintainance_schedule_date as maintainance_date,t13.name as supplier_name,
              
                 depreciation_method_id,depreciation_rate,date_acquired,asset_life,salvage_value,depreciable_cost,t12.name as sub_category_name")
                 ->where('t1.module_id',637);
                
               
            $asset_ids_to_exclude_for_funding=[];
        if($request->input('status')=="exclude_assets_already_to_funding2")
        {
            
            $asset_ids_to_exclude=Db::table('stores_funding_linkage')->where('funding_id',$request->input('funding_id'))->selectraw('asset_id')->get()->toArray();
            foreach($asset_ids_to_exclude as $asset)
            {
                $asset_ids_to_exclude_for_funding[]=$asset->asset_id;

            }
        }


        $maintainance_details=DB::table('stores_asset_maintainance')->selectRaw('maintainance_status,asset_id,maintainance_due_date')->orderBy('created_at','DESC')->get();
        $repair_details=DB::table('stores_asset_repairs')->selectRaw('repair_status,asset_id,scheduled_repair_date')->orderBy('created_at','DESC')->get();
        
        $asset_ids_to_exclude_for_funding=[];
        if($request->input('status')=="exclude_assets_already_to_funding2")
        {
            
            $asset_ids_to_exclude=Db::table('stores_funding_linkage')->where('funding_id',$request->input('funding_id'))->selectraw('asset_id')->get()->toArray();
            foreach($asset_ids_to_exclude as $asset)
            {
                $asset_ids_to_exclude_for_funding[]=$asset->asset_id;

            }
        }

       $maintainance_details=convertStdClassObjToArray($maintainance_details);
       $repair_details=convertStdClassObjToArray($repair_details);
               
        if(validateisNumeric($requested_category_id))
        {
            //change to match subcategoryid instead of category_id
            
                $qry->where('t1.sub_category_id', $requested_category_id);
            
               
        }else {
            $requested_category_id=$request->input('asset_category_id');
           
            if(validateisNumeric($requested_category_id))
            {
                $qry->where('t1.sub_category_id', $requested_category_id);
            }
        }
       
            if (validateisNumeric($status_id)) {
            $qry->where('status_id', $status_id);
            
        }
        
        if ($request->query('exclusion_asset_ids')) {

            $set_ids= explode('_',$request->query('exclusion_asset_ids'));
            $qry->whereNotIn('t1.id', $set_ids);
        }
        if(count( $asset_ids_to_exclude_for_funding)>0)
        {
            $qry->whereNotIn('t1.id',$asset_ids_to_exclude_for_funding);
        }
        
       
        $results = $qry->get()->toArray();
        $ammended_results=[];
        foreach($results as $asset)
        {
            $to_add_to_array=true; 
            $asset_maintainance_details=$this->_search_array_by_value($maintainance_details,'asset_id',$asset->id);
            $asset_repair_details=$this->_search_array_by_value($repair_details,'asset_id',$asset->id);
            if(count($asset_maintainance_details)>0){
            $asset->maintainance_status=$asset_maintainance_details[0]['maintainance_status'];
            $asset->maintainance_date=$asset_maintainance_details[0]['maintainance_due_date'];
               //lates update on 16/12/2021 

            if($request->query('status')=="exclude_maintainance_repair_overdue_due"){
                if(  $asset->maintainance_status==0 &&
                    ((new \DateTime($asset_maintainance_details[0]['maintainance_due_date']))==(new \DateTime(date('Y-m-d')))
                    )
                    )
                {
                $to_add_to_array=false; 
                }
                //exclude assets that are maintainance overdue
                if(
                    (
                    (new \DateTime($asset_maintainance_details[0]['maintainance_due_date']))<(new \DateTime(date('Y-m-d')))
                    ) &&  $asset->maintainance_status==0
                
                    )
                {
                $to_add_to_array=false; 
                }
            }

            }else{
                $asset->maintainance_status="";
            }
           
           
            if(count($asset_repair_details)>0){
                $asset->repair_status=$asset_repair_details[0]['repair_status'];
                $asset->scheduled_repair_date=$asset_repair_details[0]['scheduled_repair_date'];
            if($request->query('status')=="exclude_maintainance_repair_overdue_due"){
                    if(
                        $asset->repair_status==0 &&
                        ((new \DateTime($asset_repair_details[0]['scheduled_repair_date']))==
                        (new \DateTime(date('Y-m-d'))))
                        )
                    {
                    $to_add_to_array=false; 
                    }
                    //to exclude repair overdue
                    if(
                        (
                        (new \DateTime($asset_repair_details[0]['scheduled_repair_date']))<(new \DateTime(date('Y-m-d')))
                        ) && (  $asset->repair_status==0)
                        )
                    {
                    $to_add_to_array=false; 
                    }
            }

            }else{
                $asset->repair_status="";
                $asset->scheduled_repair_date="";
            }
            if($to_add_to_array==true){
            $ammended_results[]=$asset;
                }
        }
      
        
        if(validateisNumeric($request->query('requisition_id')))
        {

        $results = $ammended_results;
        $chained_results=[];
        foreach($results as $result)
        {
            $result->requisition_id=$request->query('requisition_id');
            $result->requisition_user_id=$request->query('requisition_user_id');
            $chained_results[]=$result;
        }
       
        $res = array(
            'success' => true,
            'message' => returnMessage($chained_results),
            'results' => $chained_results
        );

        }else{

        //$results = $qry->get()->toArray();
        $results=convertStdClassObjToArray($ammended_results);
          
        $new_results=[];
        foreach($results as $index=>$result)
        {
            
            if($result['depreciation_method_id']!=null)
            {
                
                $depreciation_method=$result['depreciation_method_id'];
                $depreciation_details=(object)[
                    "asset_life"=>$result['asset_life'],
                    "depreciable_cost"=>$result['depreciable_cost'],
                    "salvage_value"=>$result['salvage_value'],
                    "date_acquired"=>$result['date_acquired'],
                    "depreciation_rate"=>$result['depreciation_rate']
                ];
               
                switch($depreciation_method)
                {
                    
                    case 1:
                        $result_value = calculateStraightLineDepreciation($depreciation_details,date('Y-m-d'));
                        $result["current_asset_value"]=round($result_value);
                        $new_results[]=$result;
                        break;
                    case 3:
                        $result_value = calculatePercentageDepreciation($depreciation_details,date('Y-m-d'),2);
                        $result["current_asset_value"]=round($result_value);
                       
                        $new_results[]=$result;
                       
                        break;
                    case 4:
                        $result_value = calculatePercentageDepreciation($depreciation_details,date('Y-m-d'),1.5);
                        $result["current_asset_value"]=round($result_value);
                        $new_results[]=$result;
                        break;
                    case 2:
                        $percentage_dep=$depreciation_details->depreciation_rate/100;
                        $result_value= calculatePercentageDepreciation($depreciation_details,date('Y-m-d'),$percentage_dep);
                        $result["current_asset_value"]=round($result_value);
                        $new_results[]=$result;
                        break;
                  
                }
            }else{
                $new_results[]=$result;
            }
           
          
        }
        
      
        $res = array(
            'success' => true,
            'message' => returnMessage($new_results),
            'results' => $new_results
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

public function getStoresAssetDepreciation(Request $request)
{
    try {
        $asset_id = $request->input('asset_id');
        $qry = DB::table('stores_depreciation as t1')
            ->join('ar_asset_inventory as t3','t3.id','=','t1.asset_id')
            ->join('stores_depreciation_methods as t2', 't1.depreciation_method_id', '=', 't2.id')
            //->select('t1.*', 't2.name as depreciation_method','t3.cost as depreciable_cost')
            ->selectRaw('t1.id,t1.asset_life,t1.depreciation_method_id,t1.depreciation_rate,t3.cost as depreciable_cost,
            t3.purchase_date as date_acquired,t2.name as depreciation_method,salvage_value,t1.depreciation_rate')
            ->where('t1.asset_id', $asset_id);
        $results = $qry->get()->toArray();
        if(count( $results)==0)
        {
            $results= DB::table('ar_asset_inventory')
            ->where('id', $asset_id)
            ->selectRaw('cost as depreciable_cost,purchase_date as date_acquired')->get();
        }else{
            switch($results[0]->depreciation_method_id)
            {
                
                case 1:
                    $results[0]->depreciation_rate='100%';
                case 3:
                    $results[0]->depreciation_rate='200%';
                case 4:
                    $results[0]->depreciation_rate='150%';
                    break;

            }
        }
        
        $res = array(
            'success' => true,
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

public function calculateStoresAssetDepreciation(Request $request)
{
   

    try {
        $asset_id = $request->input('asset_id');
        //get depreciation details
        $depreciation_details = DB::table('stores_depreciation')
            ->where('asset_id', $asset_id)
            ->first();
           
       
        $results = array();
        if (!is_null($depreciation_details)) {
            $depreciation_method = $depreciation_details->depreciation_method_id;
            
            switch($depreciation_method)
            {
                case 1:
                    $results = calculateStraightLineDepreciation($depreciation_details);
                    break;
                case 3:
                    $results = calculatePercentageDepreciation($depreciation_details,"",2);
                    break;
                case 4:
                    $results = calculatePercentageDepreciation($depreciation_details,"",1.5);
                    break;
                case 2:
                    $percentage_dep=$depreciation_details->depreciation_rate/100;
                    $results= calculatePercentageDepreciation($depreciation_details,"",$percentage_dep);
                // case 5:
                //    $results = calculateSumofYearofDigits($depreciation_details);
                    //break;
            }
           // if ($depreciation_method == 1) {//Straight Line }
        }
        $res = array(
            'success' => true,
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
public function saveStoresAssetRegisterParamData(Request $req)
{
    try {
        $user_id = $this->user_id;
        $linkage_record_id=$req->input('linkage_record_id');
        $post_data = $req->all();
        $table_name = $post_data['table_name'];
        $id = $post_data['id'];
        $res = array();
      
        //unset unnecessary values
        unset($post_data['status_id']);
        unset($post_data['_token']);
        unset($post_data['table_name']);
        unset($post_data['id']);
        unset($post_data['linkage_record_id']);
        $table_data = $post_data;
        //add extra params
        $asset_depreciation_method="";
        $depreciation_details;
        if(array_key_exists('depreciation_method_id',$post_data)){
        $asset_depreciation_method=$post_data['depreciation_method_id'];
            
        $depreciation_details=[
            "asset_life"=>$post_data['asset_life'],
            "depreciable_cost"=>$post_data['depreciable_cost'],
            "date_acquired"=>$post_data['date_acquired']
        ];
        
        // $depreciation_details=(object)[
        //     "asset_life"=>$post_data['asset_life'],
        //     "depreciable_cost"=>$post_data['depreciable_cost'],
        //     "salvage_value"=>$post_data['salvage_value'],
        //     "date_acquired"=>$post_data['date_acquired']
        // ];
    }
    if (array_key_exists('salvage_value',$post_data)) {

        $depreciation_details["salvage_value"]=$post_data['salvage_value'];
        $depreciation_details=(object)$depreciation_details;
    }else{
        if($table_name=="stores_depreciation"){
        $depreciation_details=(object)$depreciation_details;
    }
        
    }
  
        switch($asset_depreciation_method){
            // case 5:
                
            //     $table_data['asset_end_depreciation_date']=calculateSumofYearofDigits($depreciation_details,true);
            //     break;
            case 4:
                $table_data['salvage_value']=calculatePercentageDepreciation($depreciation_details,"",1.5,false,true);
               
                $table_data['asset_end_depreciation_date']=calculatePercentageDepreciation($depreciation_details,"",1.5,true);
                break;
            case 3:
                $table_data['salvage_value']=calculatePercentageDepreciation($depreciation_details,"",2,false,true);
                $table_data['asset_end_depreciation_date']=calculatePercentageDepreciation($depreciation_details,"",2,true);
                break;
            case 2:
                $rate=$post_data['depreciation_rate']/100;
                $table_data['salvage_value']=calculatePercentageDepreciation($depreciation_details,"",$rate,false,true);
                $table_data['asset_end_depreciation_date']=calculatePercentageDepreciation($depreciation_details,"", $rate,true);
                break;
            case 1:
                $table_data['asset_end_depreciation_date']=calculateStraightLineDepreciation($depreciation_details,"",true);
                break;
        }
       
        $table_data['created_at'] = Carbon::now();
        $table_data['created_by'] = $user_id;
        $where = array(
            'id' => $id
        );
        //if (isset($id) && $id != "") {
    if (validateisNumeric($linkage_record_id)) {

            $result=Db::table('stores_funding_linkage')->where('id',$linkage_record_id)
            ->update(['amount'=>$req->input('amount'),
            "funding_id"=>$req->input('funding_id'),
            "updated_at"=>carbon::now(),
            "updated_at"=>$this->user_id,
            "notes"=>$req->input('notes')]);

            if($result==1)
            {
                $res=array(
                    "success"=>true,
                    "message"=>"Data updated Successfully!"
                );
            }else{
                $res=array(
                    "success"=>false,
                    "message"=>"Data update Failed!"
                );
            }
           


          
        } else {
            if (isset($id) && $id != "" && validateisNumeric($id)) {
                if(array_key_exists('depreciation_method_id',$table_data) && $table_data['depreciation_method_id']!="2")
                {
                    $table_data['depreciation_rate']="";
                   
                }
                $previous_data = getPreviousRecords($table_name, $where);
                $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                
            }else{
                $res = insertRecord($table_name, $table_data, $user_id);
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

public function getStoresAssetInsuranceLinkageDetails(Request $request)
{
    try {
        $asset_id = $request->input('asset_id');
        $policy_id = $request->input('policy_id');
        $qry = DB::table('stores_insurance_linkage as t1')
            ->select('t1.*');
        if (validateisNumeric($asset_id)) {
            $qry->join('stores_insurance_policies as t2', 't1.policy_id', '=', 't2.id')
                ->addSelect('t2.*')
                ->where('t1.asset_id', $asset_id);
        }else{
            //Job
            if (!validateisNumeric($policy_id)) {
            $res = array(
                'success' => true,
                'results' => []
            );
            return response()->json($res);
            }
        }
        if (validateisNumeric($policy_id)) {
            $qry->join('ar_asset_inventory as t3', 't1.asset_id', '=', 't3.id')
                //->addSelect('t3.*')
                 //Job update to remove bug id in join
                 ->addSelect(DB::raw('t3.description,t3.serial_no,t3.grz_no,t3.purchase_from,t3.purchase_date,t3.cost'))
                ->where('t1.policy_id', $policy_id);
        }else{
            //Job
            if (!validateisNumeric($asset_id)) {
            $res = array(
                'success' => true,
                'results' => []
            );
            return response()->json($res);
        }
        }
        $qry->addSelect('t1.id');
        $results = $qry->get();
        $res = array(
            'success' => true,
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

public function getStoresInsurancePoliciesForLinkage(Request $request)
{
        try {
            $asset_id = $request->input('asset_id');
            $policy_id = $request->input('policy_id');
            $qry = '';
            if (validateisNumeric($asset_id)) {
                $qry = DB::table('stores_insurance_policies as t1')
                    ->whereNotIn('t1.id', function ($query) use ($asset_id) {
                        $query->select(DB::raw('policy_id'))
                            ->from('stores_insurance_linkage')
                            ->where('asset_id', $asset_id);
                    })
                    ->whereRaw("t1.end_date>NOW()");
            }
            if (validateisNumeric($policy_id)) {
                $qry = DB::table('ar_asset_inventory as t1')
                   ->where('module_id',637)
                    ->whereNotIn('t1.id', function ($query) use ($policy_id) {
                        $query->select(DB::raw('asset_id'))
                            ->from('stores_insurance_linkage')
                            ->where('policy_id', $policy_id);
                    });
            }
            $qry->select('t1.*');
            $results = $qry->get();
            $res = array(
                'success' => true,
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

public function   saveStoresAssetInsuranceLinkage(Request $req)
{
        try {
            $selected = $req->input('selected');
            $asset_id = $req->input('asset_id');
            $policy_id = $req->input('policy_id');
            $selected_ids = json_decode($selected);
            $linkage_insert = array();
            if (validateisNumeric($asset_id)) {
                foreach ($selected_ids as $selected_id) {
                    $linkage_insert[] = array(
                        'asset_id' => $asset_id,
                        'policy_id' => $selected_id
                    );
                }
            }
            if (validateisNumeric($policy_id)) {
                foreach ($selected_ids as $selected_id) {
                    $linkage_insert[] = array(
                        'policy_id' => $policy_id,
                        'asset_id' => $selected_id
                    );
                }
            }
            DB::table('stores_insurance_linkage')
                ->insert($linkage_insert);
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

public function getStoresFundingForLinkage(Request $request)
{
    try {
        $qry = DB::table('stores_funding_details as t1')
            //->whereRaw("t1.end_date>NOW()")
            ->selectRaw("t1.*,(t1.funding_amount-(Select sum(amount) from ar_asset_funding_linkage where funding_id=t1.id)) as available_amount,
               @available_amount := (t1.funding_amount-(Select COALESCE(sum(amount),0) from ar_asset_funding_linkage where funding_id=t1.id)) as available_amount,
               @asset_count:=(select count(asset_id) from ar_asset_funding_linkage where funding_id=t1.id) as asset_count,
               CONCAT(t1.name,' Available ',@available_amount,' (',DATE_FORMAT(t1.start_date, '%d/%m/%Y'),'-',DATE_FORMAT(t1.end_date, '%d/%m/%Y'),')') as display");
        $results = $qry->get();
        $fundings=convertStdClassObjToArray($results);
        foreach ($fundings as $key => $fund) {
            
           $total_maintainance_sum= DB::table('stores_asset_maintainance')->where('funding_id',$fund['id'])->sum('maintainance_cost');
           $fund['available_amount']= $fund['available_amount']-$total_maintainance_sum;
           $total_repair_sum= DB::table('ar_asset_repairs')->where('funding_id',$fund['id'])->sum('repair_cost');
           $fund['available_amount']= $fund['available_amount']-$total_repair_sum;
            
          
          $fundings[$key]=$fund;  
        }
        $results=$fundings;
        $res = array(
            'success' => true,
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

public function getStoresAssetFundingLinkageDetails(Request $request)
{
        //Job
        try {
            $asset_id = $request->input('asset_id');
        
            $funding_id = $request->input('funding_id');
            $qry = DB::table('stores_funding_linkage as t1')
            ->select('t1.id', 't1.amount', 't1.funding_id', 't1.asset_id', 't1.notes as notes_asset', 't1.amount as amount_from_fund',
                    't1.id as linkage_record_id');

                
            $results=[];//update job
            
            if (validateisNumeric($asset_id)) {
                $qry->join('stores_funding_details as t2', 't1.funding_id', '=', 't2.id')
                    ->where('t1.asset_id', $asset_id)
                    ->addSelect('t2.*');
            }
        $results="";
            if (validateisNumeric($funding_id)) {
            
                $qry->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
                    ->where('t1.funding_id', $funding_id)
                    //->addSelect('t2.*');
                    //Job update to remove bug id in join
                    ->addSelect(DB::raw('t2.id as asset_id,t2.description,t2.serial_no,t2.grz_no,t2.purchase_from,t2.purchase_date,cost'));
                    $results = $qry->get();
            }else{
                $results=$qry->get();
            }
            //new upadate to prevent global linkage on new funds
            if($funding_id=="" && !validateisNumeric($asset_id))
            {
                $results=[];
            }
        
            $assets=convertStdClassObjToArray($results);
        foreach ($assets as $key => $asset) {
            $total_maintainance_sum= DB::table('stores_asset_maintainance')->where('funding_id',$funding_id)
            ->where('asset_id',$asset['asset_id'])->sum('maintainance_cost');
            $asset['maintainance_amount_from_funding']= $total_maintainance_sum;
            $total_repair_sum= DB::table('stores_asset_repairs')->where('funding_id',$funding_id)
            ->where('asset_id',$asset['asset_id'])->sum('repair_cost');
            $asset['repair_amount_from_funding']= $total_repair_sum;
            $assets[$key]=$asset;   
        }
        $results=$assets;
            $res = array(
                'success' => true,
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
public function   saveStoresAssetRequisitionRequestDetails(Request $request)
{
    
    try {
    $res = array();
    $request_date=$request->input('request_date');
    $requested_by =$this->user_id;
    $site_id=$request->input('site_id');
    $location_id=$request->input('location_id');
    $department_id=$request->input('department_id');
    $request_for=$request->input('request_for_id');
    $item_category=$request->input('category_id');
    $record_id=$request->input('id');
    $asset_quantity=$request->input('asset_quantity_item');
    $user_id= $this->user_id;
    $request_type="";
    
    if($request_for==1)
    {
        $request_type=$request->input('request_type');
        if($request_type==2)
        {
            $user_id=$request->input('individual_id');
            $requested_by=$request->input('individual_id');
        }
    }

    
        $table_data=[
            "request_date"=>$request_date,
            "requistion_site_id"=>$site_id,
            "requistion_location_id"=>$location_id,
            "requistion_department_id"=>$department_id,
            "requested_for"=>$request_for,
            "asset_category"=>$item_category,
            "created_at"=>Carbon::now(),
            "created_by"=> $this->user_id

    ];

    if($request_for==1)
    {
        $request_type=$request->input('request_type');
        if($request_type==2)
        {
            
            $table_data['onbehalfof']=1;
        }
    }
    $active_requisitions_of_category_not_checked_out=Db::table('stores_asset_requisitions as t1')
    ->where('asset_category','=',$item_category)//the category is asset sub category late change thus mismatch in name
    ->where('requisition_status','=',1)->count();

    $item_count_pending="";
    $item_count="";
    if($request_for==1){
    $item_count_pending=Db::table('stores_asset_requisitions as t1')
    ->where('asset_category','=',$item_category)
    ->where('t1.requested_by','=',$user_id)
    ->where('t1.requested_for',$request_for)
    ->where('requisition_status','=',1)->count();
    $item_count=Db::table('stores_asset_requisitions as t1')
    ->where('asset_category','=',$item_category)
    ->where('t1.requested_by','=',$user_id)
    ->where('requisition_status','=',2)->count();
    }else{
    $item_count_pending=Db::table('stores_asset_requisitions as t1')//pending assignments
    ->where('asset_category','=',$item_category)
    ->where('t1.requistion_site_id','=',$site_id)
    ->where('t1.requested_for',$request_for)
    ->where('requisition_status','=',1)->count();
    $item_count=Db::table('stores_asset_requisitions as t1')
    ->where('asset_category','=',$item_category)
    ->where('t1.requistion_site_id','=',$site_id)
    ->where('requisition_status','=',2)->count();
    }
    
    $actual_item_category=DB::table('stores_subcategories')->where('id',$item_category)->value('category_id');
    $if_multiple_checkout=DB::table('stores_categories')
        ->where('id',$actual_item_category)
        ->selectRaw('multiple_checkout')->get()->toArray()[0]->multiple_checkout;

    if($item_count>0 && $if_multiple_checkout==0)
    {   
        $table_data['requisition_status']=0;
        $table_data["remarks"]="Item assigment is Limited to One";
    }else{
    // $item_count_pending="0";//over rule rule ,remember to remove
        if($item_count_pending==0){
            
            $available_count=0;
            $asset_count= Db::table('ar_asset_inventory')
            ->where('sub_category_id','=',$item_category)
            ->where('module_id',637)
            ->where('status_id','=',1)->count();
            if($active_requisitions_of_category_not_checked_out>$asset_count){
            $available_count=$active_requisitions_of_category_not_checked_out-$asset_count;
            }else{
                $available_count=$asset_count-$active_requisitions_of_category_not_checked_out;
            }
        
            if($available_count>=1)
            {
                $asset_quantity=intval($asset_quantity);
            
            
                if($asset_quantity==1){
                $table_data['requisition_status']=5;//was 1 now 5
                $table_data['verified']=1;
                
                }
                if( $asset_quantity>1)
                {
                
                    if($asset_quantity>$available_count)
                    {
                            $data_per_item_assignable=[
                                "request_date"=>$request_date,
                                "requistion_site_id"=>$site_id,
                                "requistion_location_id"=>$location_id,
                                "requistion_department_id"=>$department_id,
                                "requested_for"=>$request_for,
                                "asset_category"=>$item_category,
                                "created_at"=>Carbon::now(),
                                "created_by"=> $this->user_id,
                                "verified"=>1,
                                //"remarks"=>"Asset  for assignment is available",
                                'requisition_status'=>5
                        ];
                        if($request_type==2)
                        {
                        
                            $data_per_item_assignable['onbehalfof']=1;
                        }
                        $data_per_item_unassignable=[
                            "request_date"=>$request_date,
                            "requistion_site_id"=>$site_id,
                            "requistion_location_id"=>$location_id,
                            "requistion_department_id"=>$department_id,
                            "requested_for"=>$request_for,
                            "asset_category"=>$item_category,
                            "created_at"=>Carbon::now(),
                            "created_by"=> $this->user_id,
                            "remarks"=>"Asset  for assignment is unavailable",
                            'requisition_status'=>0
                        ];

                        if($request_type==2)
                        {
                        
                            $data_per_item_unassignable['onbehalfof']=1;
                        }

                
                    if($request_for==1)
                    {
                        $data_per_item_assignable['requested_by']=$requested_by;
                        $data_per_item_unassignable['requested_by']=$requested_by;
                    }
                    $table_data=[];     
                        $assignable=$available_count;
                        $unassignable= $asset_quantity-$available_count;
                        $combined_results=[$assignable,$unassignable];
                        $combined_data=[$data_per_item_assignable,$data_per_item_unassignable];
                        foreach ($combined_results as $key => $value) {
                            for($i=1;$i<=$value;$i++)//vlaue is count
                            {
                                $table_data[]=$combined_data[$key];//use outer loop to get data for each of inner loop based on category
                            }
                            
                        }
                    
                    }else{
                        $table_data=[];
                        $assignable= $asset_quantity;
                        $data_per_item_assignable=[
                            "request_date"=>$request_date,
                            "requistion_site_id"=>$site_id,
                            "requistion_location_id"=>$location_id,
                            "requistion_department_id"=>$department_id,
                            "requested_for"=>$request_for,
                            "asset_category"=>$item_category,
                            "created_at"=>Carbon::now(),
                            "created_by"=> $this->user_id,
                            "verified"=>1,
                        // "remarks"=>"Asset  for assignment is available",
                            'requisition_status'=>5
                    ];
                    if($request_type==2)
                    {
                        
                        $data_per_item_assignable['onbehalfof']=1;
                    }
                            //if($request_for==1 && $asset_quantity==1)
                            if($request_for==1)
                            {
                                $data_per_item_assignable['requested_by']=$requested_by;
                                $data_per_item_unassignable['requested_by']=$requested_by;
                            }

                            for($i=1;$i<=$assignable;$i++)
                                    {
                                        $table_data[]=$data_per_item_assignable;//use outer loop to get data for each of inner loop based on category
            
                                    }

                    }
                    
                }
            
            }else{
                $table_data['requisition_status']=0;
            
                $table_data["remarks"]="Asset  is Unavailable";
            }
        }else{
        $table_data['requisition_status']=0;
        $table_data["remarks"]="There's a pending  assigment for the Requested Asset";
        }
    }

    //$table_data['requisition_status']=1;//to bre removed,just for time saving
    if($request_for==1 && $asset_quantity==1)
    {
        $table_data['requested_by']=$requested_by;
    }
    if(isset($record_id) && $record_id!="")
    {
        $res["message"]="Requisition Request Details already saved";

    }else{
    $res=DB::table('stores_asset_requisitions')->insert($table_data);
        if($res==true)
        {
            $res=array(
                "success"=>true,
                "record_id"=>0
            );
        }else{
            $res=array(
                "success"=>false,
            );
        }
    //$res = insertRecord('ar_asset_requisitions', $table_data,$this->user_id);

    if($res['success']==true)
    {
        $res["message"]="Requisition Request Details saved";
    }else{
        $res["message"]="Error while saving  Requisition Request Details";
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

public function saveStoresAssetApprovalDetails(Request $request)
{
    try{
        $res=array();
    $requisition_record_id=$request->input('requisition_record_id');
    $asset_action_status=$request->input('asset_action_status');
    $rejection_reason=$request->input('rejection_reason');

    $update_data=[];
    $update_data=[
    "updated_at"=>Carbon::now(),
    "updated_by"=>$this->user_id
    ];
    if($asset_action_status==2)
    {   
        $update_data["requisition_status"]=0;
        $update_data['remarks']= $rejection_reason;
      
    }else{
        $update_data['remarks']= "Request Approved";
        $update_data["requisition_status"]=1;
    }
   $result= Db::table('stores_asset_requisitions')->where('id',$requisition_record_id)->update($update_data);
    if($result==true)
    {
        $res=array(
            "success"=>true,
            "message"=>"Requisition Details Saved"
        );
    }else{
        $res=array(
            "success"=>false,
            "message"=>"Error Saving Requisition Details"
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

public function getStoresAssetCheckOutDetails(Request $request)
{
    $user_filter=$request->query('user_filter');
    try {
        $qry = DB::table('stores_asset_checkout_details as t1')
            ->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
            ->leftjoin('stores_asset_requisitions as t8','t8.checkout_id','t1.id')
            ->join('users as t3', 't1.user_id', '=', 't3.id')
            ->join('stores_sites as t4', 't1.checkout_site_id', '=', 't4.id')
            ->leftjoin('stores_locations as t5', 't1.checkout_location_id', '=', 't5.id')
            ->leftjoin('stores_departments as t6', 't1.checkout_department_id', '=', 't6.id')
            ->leftJoin('stores_asset_checkin_details as t7', 't1.id', '=', 't7.checkout_id')
            ->selectRaw("t1.id as checkout_id,t1.*,t2.description,t2.serial_no,t2.grz_no,t4.name as site_name,t5.name as location_name,
                    t2.site_id,t2.location_id,t2.department_id,CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as assigned_user,t6.name as department_name,
                    t7.return_date,t7.checkin_site_id,t7.checkin_location_id,t7.checkin_department_id")
                ->orwhere('t8.is_single_checkout',1)
                ->where('t2.module_id',637);

            if (validateisNumeric($user_filter)) {
                $qry->where('t1.user_id',$this->user_id);
            }
        $results = $qry->get();


       $qry= DB::table('stores_asset_checkout_details as t1')
            ->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
            ->join('stores_asset_reservations as t8','t8.checkout_id','=', 't1.id')
            ->join('users as t3', 't1.user_id', '=', 't3.id')
            ->join('stores_sites as t4', 't1.checkout_site_id', '=', 't4.id')
            ->leftjoin('stores_locations as t5', 't1.checkout_location_id', '=', 't5.id')
            ->leftjoin('stores_departments as t6', 't1.checkout_department_id', '=', 't6.id')
            ->leftJoin('stores_asset_checkin_details as t7', 't1.id', '=', 't7.checkout_id')
            ->selectRaw("t1.id as checkout_id,t1.*,t2.description,t2.serial_no,t2.grz_no,t4.name as site_name,t5.name as location_name,
                    t2.site_id,t2.location_id,t2.department_id,CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as assigned_user,t6.name as department_name,
                    t7.return_date,t7.checkin_site_id,t7.checkin_location_id,t7.checkin_department_id,t8.id as reservation_record_id ")
                    ->where('t2.module_id',637);
                   
               
        $combined_results= array_merge($results->toArray(),$qry->get()->toArray());
         
        $res = array(
            'success' => true,
            'message' => returnMessage($combined_results),
            'results' => $combined_results,
           
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



public function getStoresAssetInventoryDetailsForReservations(Request $request)
{   



    try{

        $status_id=3;
        $status_id_damaged=$request->input('status');


        $qry = DB::table('ar_asset_inventory as t1')
           // ->join('users as t9', 't9.user_id', '=', 't1.id')
            ->join('stores_categories as t2', 't1.category_id', '=', 't2.id')
            ->leftjoin('stores_brands as t3', 't1.brand_id', '=', 't3.id')
            ->leftjoin('stores_models as t4', 't1.model_id', '=', 't4.id')
            ->leftjoin('stores_sites as t5', 't1.site_id', '=', 't5.id')
            ->leftjoin('stores_locations as t6', 't1.location_id', '=', 't6.id')
            ->leftjoin('stores_departments as t7', 't1.department_id', '=', 't7.id')
            ->leftjoin('stores_statuses as t8', 't1.status_id', '=', 't8.id')
             ->selectRaw('t1.*,t1.id as record_id,t1.id as parent_asset_id,t2.name as category_name,t3.name as brand_name,t4.name as model_name,t5.name as site_name,
             t6.name as location_name,t7.name as department_name,t8.name as record_status,t1.id as asset_id');
           
          if (!validateisNumeric($status_id_damaged)){
          if (validateisNumeric($status_id)) {
            $qry->where('status_id', 1);//where a
           // $qry->orWhere('status_id',2);
            }
            }else{
                $qry->where('status_id', 5);//damaged,qualify for repair
            }
        //$qry->where('maintainance_schedule_date',"!=",now()->format('Y-m-d'));
        $results=$qry->get();

   
        
        //$results=$qry->whereRaw('maintainance_schedule_date != CURDATE()')->get();
        

        $maintainance_details=DB::table('stores_asset_maintainance')->selectRaw('maintainance_status,asset_id,maintainance_due_date')->orderBy('created_at','DESC')->get();
        $repair_details=DB::table('stores_asset_repairs')->selectRaw('repair_status,asset_id,scheduled_repair_date')->orderBy('created_at','DESC')->get();
        $maintainance_details=convertStdClassObjToArray($maintainance_details);
        $repair_details=convertStdClassObjToArray($repair_details);
        $results = $qry->get()->toArray();
        
        $ammended_results=[];
        foreach($results as $asset)
        {
            $to_add_to_array=true;
            $asset_maintainance_details=$this->_search_array_by_value($maintainance_details,'asset_id',$asset->id);
            $asset_repair_details=$this->_search_array_by_value($repair_details,'asset_id',$asset->id);
            if(count($asset_maintainance_details)>0){
                
            $asset->maintainance_status=$asset_maintainance_details[0]['maintainance_status'];
            $asset->maintainance_date=$asset_maintainance_details[0]['maintainance_due_date'];
                if(
                    $asset->maintainance_status==0 &&
                    ((new \DateTime($asset_maintainance_details[0]['maintainance_due_date']))==(new \DateTime(date('Y-m-d'))))
                    )
                {
                   $to_add_to_array=false; 
                }
                //exclude assets that are maintainance overdue
                if(
                    (
                    (new \DateTime($asset_maintainance_details[0]['maintainance_due_date']))<(new \DateTime(date('Y-m-d')))
                    ) &&  $asset->maintainance_status==0
                  
                    )
                {
                   $to_add_to_array=false; 
                }
                
            }else{
                $asset->maintainance_status="";
                $asset->maintainance_date="";
            }
            if(count($asset_repair_details)>0){
              
                $asset->repair_status=$asset_repair_details[0]['repair_status'];
                $asset->scheduled_repair_date=$asset_repair_details[0]['scheduled_repair_date'];
                if( $asset->repair_status==0 &&
                    ((new \DateTime($asset_repair_details[0]['scheduled_repair_date']))==
                    (new \DateTime(date('Y-m-d'))))
                    )
                {
                   
                   $to_add_to_array=false; 
                }
                //to exclude repair overude
                if(
                    (
                    (new \DateTime($asset_repair_details[0]['scheduled_repair_date']))<(new \DateTime(date('Y-m-d')))
                    ) && (  $asset->repair_status==0)
                    )
                {
                   $to_add_to_array=false; 
                }
            }else{
                $asset->repair_status="";
                $asset->scheduled_repair_date="";
            }
            if($to_add_to_array==true){//to exclude assets due/overdue repair/maintainance
            
            $ammended_results[]=$asset;
            }
            $to_add_to_array=true;
           
        }
        $results=$ammended_results;
      
        if($request->query('status_funding_linkage2')=='repair_maintainance_status_has_funding'){
        //for repair/maintainance

                $new_ammended_results=[];
               foreach($ammended_results as $asset)
               {
                    
            
                $asset_link_fund_count=Db::table('stores_funding_linkage as t1')->where('asset_id',$asset->parent_asset_id)
                ->join('ar_funding_details as t2','t2.id','=','t1.funding_id')
                ->whereDate('end_date', '>', now()->format('Y-m-d'))->count();
                
              
                if($asset_link_fund_count>0)
                {
                    $new_ammended_results[]=$asset;
                }
            

               }
               $results=$new_ammended_results;
            }

        //end
      
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

public function saveStoresAssetReservationDetails(Request $request)
{

    try {
       
        $id = $request->input('reservation_id');
        $asset_id = $request->input('asset_id');
        if(!validateisNumeric($asset_id)){
            $asset_id = $request->input('parent_asset_id');
               }
        $site_id = $request->input('reservation_site_id');
        $location_id = $request->input('reservation_location_id');
        $department_id = $request->input('reservation_department_id');
        $reserve_for_who=$request->input('reservation_for_id');
        $reserve_puprose =  $request->input('reserve_purpose');
        $user_id = $this->user_id;
        $table_name = 'stores_asset_reservations';
        $table_data = array(
            'asset_id' => $asset_id,
            'user_id' => $request->input('user_id'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'send_email' => $request->input('send_email'),
            'user_email' => $request->input('user_email'),
            'reserve_for_who'=>$reserve_for_who,
            'reserve_purpose'=>$reserve_puprose,
            'reservation_site_id' => $site_id,
            'reservation_location_id' => $location_id,
            'reservation_department_id' => $department_id
        );
        $identifier_name=Db::table('ar_asset_inventory as t1')
        ->join('stores_identifiers as t2','t1.identifier_id','t2.id')
        ->where('t1.id',$asset_id)
        ->value('t2.name');
        $where = array(
            'id' => $id
        );
        if (isset($id) && $id != "") {
            $table_data['updated_at'] = Carbon::now();
            $table_data['updated_by'] = $user_id;
            $previous_data = getPreviousRecords($table_name, $where);
            $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
            $this->insert_into_asset_history($asset_id,"reservation details updated");
        } else {
           
            $table_data['created_at'] = Carbon::now();
            $table_data['created_by'] = $user_id;
            
    
       
           $res = insertRecord($table_name, $table_data, $user_id);
          
           $this->insert_into_asset_history($asset_id,"reservation created");
            if ($res['success'] == true) {
                
                if($table_data['send_email']==1)
                {   $mail_to=$table_data['user_email'];
                    //$mail_to="murumbajob78@gmail.com";
                    $subject = "Asset Reservation";
                    $data=array(
                        "description"=>$request->input('description'),
                        //"serial_no"=>$request->input('serial_no'),
                        "no_due_date"=>0,//0 for due date,
                        "checkout_date"=>date('d-m-Y',strtotime( $request->input('start_date'))),
                        "due_date"=> date('d-m-Y',strtotime($request->input('end_date'))),
                        "user_id"=>$request->input('user_id')
                    ); 
                    if($request->input('serial_no')!="")
                    {
                        $data['serial_no']=$request->input('serial_no');

                    }else{
                        $data['grz_no']=$request->input('grz_no');
                    }
                    $data['identifier']=$identifier_name;
                    $this->sendAssetCheckOutEmail($mail_to,"Asset Reservation",$data,false,true);
                  //$this->sendAssetCheckOutEmail("murumbajob78@gmail.com","Asset Reservation",$data,false,true);
                }
               
                       $this->sendAssetHasBeenReservedMailToStakeHolders([
               "description"=>$request->input('description'),
               "serial_no"=>$request->input('serial_no'),
               "grz_no"=>$request->input('grz_no')
               
           ],$request->input('start_date'),$request->input('end_date'));
                //update asset details
                $asset_update = array(
                    'status_id' => 3,
                    'site_id' => $site_id,
                    'location_id' => $location_id,
                    'department_id' => $department_id
                );
                DB::table('ar_asset_inventory')
                    ->where('id', $asset_id)
                    ->update($asset_update);//checked out
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

public function saveStoresAssetCheckOutInDetails(Request $request)
    {
        try {
           
           
            $checkin_status=$request->input('checkin_status');
            $reservation_record_id=$request->input('reservation_record_id');
            $checkout_id = $request->input('checkout_id');
            $checkin_id = $request->input('checkin_id');
            $asset_id = $request->input('asset_id');
            $operation_type = $request->input('operation_type');
            $table_name = $request->input('table_name');
            $requisition_record_id= $request->input('requisition_record_id');
            $user_id = $this->user_id;
            $table_data = array(
                'send_email' => $request->input('send_email'),
                'user_email' => $request->input('user_email')
            );
        
            $identifier_name=Db::table('ar_asset_inventory as t1')
            ->join('stores_identifiers as t2','t1.identifier_id','t2.id')
            ->where('t1.id',$asset_id)
            ->value('t2.name');
        
         
            if ($operation_type == 'check-in') {
                $asset_status_id = 1;
                $checkout_status_id = 2;
                $id = $checkin_id;
                $site_id = $request->input('checkin_site_id');
                $location_id = $request->input('checkin_location_id');
                $department_id = $request->input('checkin_department_id');
                $table_data['checkin_site_id'] = $site_id;
                $table_data['checkin_location_id'] = $location_id;
                $table_data['checkin_department_id'] = $department_id;
                $table_data['return_date'] = $request->input('return_date');
                $table_data['checkout_id'] = $checkout_id;
                $this->insert_into_asset_history($asset_id,"Checked-In");
            } else {
                $asset_status_id = 2;
                $checkout_status_id = 1;
                $id = $checkout_id;
                $site_id = $request->input('checkout_site_id');
                $location_id = $request->input('checkout_location_id');
                $department_id = $request->input('checkout_department_id');
                $table_data['checkout_site_id'] = $site_id;
                $table_data['checkout_location_id'] = $location_id;
                $table_data['checkout_department_id'] = $department_id;
                $table_data['user_id'] = $request->input('user_id');
                $table_data['checkout_date'] = $request->input('checkout_date');
                $table_data['no_due_date'] = $request->input('no_due_date');
                $table_data['due_date'] = $request->input('due_date');
                $table_data['asset_id'] = $asset_id;
                $table_data['requisition_id']=$requisition_record_id;
                $this->insert_into_asset_history($asset_id,"Checked-Out");
            }
           
            $where = array(
                'id' => $id
            );
           
            if (validateisNumeric($id)) {
               
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $previous_data = getPreviousRecords($table_name, $where);
                $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
            } else {
               
                $table_data['created_at'] = Carbon::now();
                $table_data['created_by'] = $user_id;
                $res = insertRecord($table_name, $table_data, $user_id);
               
               
                //  $res=[];
                //  $res['success']= true;
                if ($res['success'] == true) {

                
                    //update asset details
                    $asset_update = array(
                        'status_id' => $asset_status_id,
                        'site_id' => $site_id,
                        'location_id' => $location_id,
                        'department_id' => $department_id
                    );
                   
                    DB::table('ar_asset_inventory')
                        ->where('id', $asset_id)
                        ->update($asset_update);//checked in
                   
                    //update checkout status
                   
                     if (validateisNumeric($checkout_id)) {
                        
                        DB::table('stores_asset_checkout_details')
                        ->where('id', $checkout_id)
                        ->update(array('checkout_status' => $checkout_status_id));
                     }
           
                 

                        //if(isset($checkin_status) && $checkin_status!=""){
                          
                        
                            if($operation_type != 'check-in'){//checkout
                                $new_checkout_id=$res['record_id'];
                                
                        if (validateisNumeric($reservation_record_id)) {
                            $reservations_table_update=[
                                "checkin_status"=>1,//checked out
                                "checkout_id"=>$new_checkout_id
                            ];
                             DB::table('stores_asset_reservations')
                                    ->where('id', $reservation_record_id)
                                    ->update($reservations_table_update);//checked out
                        }
                        if($table_data['send_email']==true)
                        {   $mail_to=$table_data['user_email'];
                            $subject = "Asset Check-Out";
                            $data=array(
                                "user_id"=>$request->input('user_id'),
                                "description"=>$request->input('description'),
                                //"serial_no"=>$request->input('serial_no'),
                                "no_due_date"=>$request->input('no_due_date'),//0 for due date,
                                "checkout_date"=>date('d-m-Y',strtotime( $request->input('checkout_date'))),    
                            ); 
                            if($request->input('serial_no')!="")
                            {
                                $data['serial_no']=$request->input('serial_no');

                            }else{
                                $data['grz_no']=$request->input('grz_no');
                            }
                            if($request->input('no_due_date')==0)
                            {
                               
            
                                $data["due_date"]=date('d-m-Y',strtotime($request->input('due_date')));
                            }
                            $data['identifier']=$identifier_name;
                          //$this->sendAssetCheckOutEmail("murumbajob78@gmail.com","Asset Check-Out",$data);
                          //$this->sendAssetCheckOutEmail("job.murumba@softclans.co.ke","Asset Check-Out",$data);
                         $this->sendAssetCheckOutEmail($mail_to,"Asset Check-Out",$data);
                        }
                            if (validateisNumeric($requisition_record_id)) { 
                           
                            $requisition_table_update=[
                                "requisition_status"=>2,
                                "is_single_checkout"=>1,
                                "checkout_id"=>$new_checkout_id
                            ];//to do make sure it gets and updates checkout id
                          
                            $where=[
                                "id"=>$requisition_record_id,
                            ];
                            
                            $previous_data = getPreviousRecords('stores_asset_requisitions', $where);
                            $res = updateRecord('stores_asset_requisitions', $previous_data, $where,  $requisition_table_update, $user_id);
                        }
                        }else{//checkin
                          
                            $reservations_table_update=[
                                "checkin_status"=>2//checked in
                            ];
                            
                          
                               
                               if (validateisNumeric( $reservation_record_id)) {
                                   
                                DB::table('stores_asset_reservations')
                                ->where('id', $reservation_record_id)
                                ->update($reservations_table_update);//checked in
                                $requisition_table_update=[
                                    "requisition_status"=>3
                                ];
                                 }
                                 if (validateisNumeric($requisition_record_id)){
                                    $where=[
                                        "id"=>$requisition_record_id,
                                        "requisition_status"=>3
                                    ];
                                    $previous_data = getPreviousRecords('stores_asset_requisitions', $where);
                                    $res = updateRecord('stores_asset_requisitions', $previous_data, $where,  $requisition_table_update, $user_id);
                                }


                                }
                            
                        //}//commented set checkin status
                    
                    
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

public function SaveStoresSingleSiteAssetCheckOutDetails(Request $request)
{
        try{
            $asset_id = $request->input('asset_id');
            $reservation_record_id=$request->input('reservation_record_id');
            $requisition_record_id=$request->input('requisition_record_id');
            $asset_id = $request->input('asset_id');
            $site_id = $request->input('checkout_site_id');
            $location_id = $request->input('checkout_location_id');
            $department_id = $request->input('checkout_department_id');
            $user_id = $this->user_id;
            $site_asset_individual_responsible=$request->input('site_asset_individual_responsible');
            $table_data = array(
                'asset_id' => $asset_id,
                'checkout_date' => $request->input('checkout_date'),
                'no_due_date' => $request->input('no_due_date'),
                'due_date' => $request->input('due_date'),
                'checkout_site_id' => $site_id,
                'checkout_location_id' => $location_id,
                'checkout_department_id' => $department_id,
                "checkout_status"=>1,
                "site_asset_individual_responsible"=>$site_asset_individual_responsible,
                //"requisition_id"=>,
                "assignment_category"=>2,
                "is_single_site_checkout"=>1
            );
            if (validateisNumeric($requisition_record_id)) {
                $table_data['requisition_id']=$requisition_record_id;
            }
            
            $res = insertRecord('stores_asset_checkout_details', $table_data, $user_id);
            if ($res['success'] == true) {
                //update requisitions table
                $new_record_id=$res['record_id'];
                
          //update asset inventory table
        DB::table('ar_asset_inventory')
        ->where('id',   $asset_id)
        ->update(['status_id'=>2,'site_id'=>$site_id,"location_id"=>$location_id,"department_id"=>$department_id]);//checked in
        
        //update requistions table
        if (validateisNumeric($requisition_record_id)) {
        DB::table('stores_asset_requisitions')
        ->where('id', $requisition_record_id)
        ->update(['requisition_status'=>2,"is_site_single_checkout"=>1]);//checked in for requisitions
        }else{
            DB::table('stores_asset_reservations')
            ->where('id', $reservation_record_id)
            ->update(['checkin_status'=>1,"checkout_id"=> $new_record_id]);//checked out for reservations,2 for checked in
        }
        //log history
       $this->insert_into_asset_history($asset_id,"checked-Out");  
       $res=[];
       $res["success"]=true;
       $res['message']="Check-Out Details Saved Successfully";

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


public function saveStoresMultipleIndividualsBulkCheckoutDetails(Request $request)
{
        try{
        $user_id = $this->user_id;
        $individuals_to_assign = $request->input('users_for_asset');  
        $asset_id = $request->input('assets_for_individuals'); 
        //unique identfier name
        $identifier_name=Db::table('ar_asset_inventory as t1')
        ->join('stores_identifiers as t2','t1.identifier_id','t2.id')
        ->where('t1.serial_no',$input_data['serial_no'])
        ->value('t2.name');
        //esnure no duplicates in requisitions ids from front end mixup
        $requisition_ids= $request->input('requisition_ids');
        $requisition_ids_array=explode(",",$requisition_ids);
        $unique_requistion_ids_array=array_unique($requisition_ids_array);
        $requisition_ids=implode(',',$unique_requistion_ids_array);
        
        $users=DB::table('users as t1')->whereIn('id',$this->returnArrayFromStringArray($individuals_to_assign))
        ->selectRaw('decrypt(t1.last_name) as last_name,decrypt(t1.email) as email')->get();
        $users=convertStdClassObjToArray($users);
      
        $site_id = $request->input('checkout_site_id');
        $location_id = $request->input('checkout_location_id');
        $department_id = $request->input('checkout_department_id');
        $table_data['checkout_site_id'] = $site_id;
        $table_data['checkout_location_id'] = $location_id;
        $table_data['checkout_department_id'] = $department_id;
        $table_data['user_id'] = $individuals_to_assign;
        $table_data['send_email']= $request->input('send_email');
        $table_data['checkout_date'] = $request->input('checkout_date');
        $table_data['no_due_date'] = $request->input('no_due_date');
        $table_data['due_date'] = $request->input('due_date');
        $table_data['asset_id'] = $asset_id;
        $table_data['requisition_id']="[".$requisition_ids."]";
        $table_data['is_group_checkout']=1;
        $res = insertRecord('stores_asset_checkout_details', $table_data, $user_id);

        $array_of_requisition_ids=explode(',',$requisition_ids);
        if(empty($array_of_requisition_ids[count($array_of_requisition_ids)-1])) {
            unset($array_of_requisition_ids[count($array_of_requisition_ids)-1]);
        }
        if ($res['success'] == true) {
           $res= DB::table('stores_asset_requisitions')
              ->whereIn('id',$array_of_requisition_ids)
              ->update(['requisition_status'=>'2',"is_group_checkout"=>"1","checkout_id"=>$res['record_id'],
              "updated_at"=>Carbon::now(),"updated_by"=> $user_id]);//set checked out
              //should be looked at why it returns false instead of true above query despite update
            $where=array(
                "id"=>$asset_id
            );
            //modified to two on 11/1/2021
           $params= ['status_id'=>2,'site_id'=>$site_id,"location_id"=>$location_id,"department_id"=>$department_id];
            
              $previous_data = getPreviousRecords('ar_asset_inventory', $where);
              $res = updateRecord('ar_asset_inventory', $previous_data, $where, $params, $user_id);


              if ($res['success'] == true) {

                if( $request->input('send_email')==true){
                    $users_email_details=[];
                    $asset=DB::table('ar_asset_inventory')->where('id',$asset_id)->selectRaw('description,serial_no,grz_no')->get();
                    $asset=convertStdClassObjToArray($asset);
                    $asset=$asset[0];
                    if($asset['serial_no']=="")
                    {
                        unset($asset['serial_no']);
                    }
                    
                    $counter=0;
                    $users_email_details=[];
                    foreach($users as $user)
                    {
                        $users_email_details[$counter]=array(
                            "user_email"=>$user['email'],
                            "last_name"=>$user['last_name'],
                            "checkout_date"=>$request->input('checkout_date'),
                            'no_due_date'=>$request->input('no_due_date'),
                            "asset"=>$asset,
                            "identifier"=>$identifier_name
                        );
            
                        if($request->input('no_due_date')==0 || $request->input('no_due_date')=="0")
                        {
                            $users_email_details[$counter]['due_date']=$request->input('due_date');
                        }
                        $counter+=1;
                      
                    }
                 $this->sendAssetCheckOutEmail("","Asset Check-Out",$users_email_details);
                }
                $this->insert_into_asset_history($asset_id,"Checked-Out");
                  $res=[];
                $res['success'] =true;
                $res['message'] ="Check-Out Data saved successfully";
              }else{
                  return $res;
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

public function SaveStoresSingleSiteAssetCheckInDetails(Request $request)
    {
        try{
           
            $asset_id = $request->input('asset_id');
            $requisition_record_id=$request->input('requisition_record_id');
            $reservation_record_id=$request->input('reservation_record_id');
            $asset_id = $request->input('asset_id');
            $site_id = $request->input('checkin_site_id');
            $location_id = $request->input('checkin_location_id');
            $department_id = $request->input('checkin_department_id');
            $checkout_id=$request->input('checkout_id');
            $return_date=$request->input('return_date');
            $user_id = $this->user_id;
            $table_data = array(
                
                'checkout_id'=>$checkout_id,
                'return_date'=>$return_date,
                'checkin_site_id' => $site_id,
                'checkin_location_id' => $location_id,
                'checkin_department_id' => $department_id,
               
              
            );
            $res = insertRecord('stores_asset_checkin_details', $table_data, $user_id);
            if ($res['success'] == true) {

                //update reservations table
                if (validateisNumeric($reservation_record_id)) {
                    $reservations_table_update=[
                        "checkin_status"=>2,//checked out
                        
                    ];
                     DB::table('stores_asset_reservations')
                            ->where('id', $reservation_record_id)
                            ->update($reservations_table_update);//checked out
                }
                //update requisitions table
                //update checkout status
                DB::table('stores_asset_checkout_details')
                ->where('id',$checkout_id)
                ->update(['checkout_status'=>2]);
          //update asset inventory table
        DB::table('ar_asset_inventory')
        ->where('id',  $asset_id)
        ->update(['status_id'=>1,'site_id'=>$site_id,"location_id"=>$location_id,"department_id"=>$department_id]);//checked in
        
        //update requistions table
        DB::table('stores_asset_requisitions')
        ->where('id', $requisition_record_id)
        ->update(['requisition_status'=>3]);//checked in for requisitions
        //log history
       $this->insert_into_asset_history($asset_id,"Checked-In");  
       $res=[];
       $res["success"]=true;
       $res['message']="Check-In Details Saved Successfully";

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

    public function saveStoresIndividualBulkCheckInDetails(Request $request)
    {
        try{
        //checkin
        $site_id = $request->input('checkin_site_id');
        $location_id = $request->input('checkin_location_id');
        $department_id = $request->input('checkin_department_id');
        $site_id=$request->input('checkin_site_id');
        $requisition_ids=$this->returnArrayFromStringArray($request->input('requisition_record_id'));
        $assets_for_individual=$this->returnArrayFromStringArray($request->input('assets_for_individual'));
        $checkout_id=$request->input('checkout_id');
        $location_id=$request->input('checkin_location_id');
        $department_id=$request->input('checkin_department_id');
        $return_date=$request->input('return_date');
        $send_email=$request->input('send_email');
        $user_email=$request->input('user_email');
        $user_id = $this->user_id;
       
        if($send_email=="true")
        {
            $send_email=1;
        }else{
            $send_email=0;
        }
        $table_data['checkin_site_id'] = $site_id;
        $table_data['checkin_location_id'] = $location_id;
        $table_data['checkin_department_id'] = $department_id;
        $table_data['return_date'] = $return_date;
        $table_data['checkout_id'] = $checkout_id;
        $table_data['send_email']=$send_email;
        $table_data['user_email']=$user_email;

    
        $res = insertRecord('stores_asset_checkin_details', $table_data, $user_id);
        if ($res['success'] == true) {
        //update checkout status
        DB::table('stores_asset_checkout_details')
        ->where('id', $checkout_id)
        ->update(['checkout_status'=>2]);//checked in
   
          //update asset inventory table
        DB::table('ar_asset_inventory')
        ->whereIn('id',   $assets_for_individual)
        ->update(['status_id'=>1,'site_id'=>$site_id,"location_id"=>$location_id,"department_id"=>$department_id]);//checked in
        
        //update requistions table
        DB::table('stores_asset_requisitions')
        ->whereIn('id', $requisition_ids)
        ->update(['requisition_status'=>3]);//checked in for requisitions
        //log history
        foreach($assets_for_individual as $asset_id){
        $this->insert_into_asset_history($asset_id,"checked-in");  
        }
            $res=[];
            $res["success"]=true;
            $res['message']="Check-In Details Saved Successfully";
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

 public function saveStoresSiteBulkCheckInDetails(Request $request)
{
        try{
        //checkin
        $site_id = $request->input('checkin_site_id');
        $location_id = $request->input('checkin_location_id');
        $department_id = $request->input('checkin_department_id');
        $site_id=$request->input('checkin_site_id');
        $requisition_ids=$this->returnArrayFromStringArray($request->input('requisition_record_id'));
        $assets_for_site=$this->returnArrayFromStringArray($request->input('assets_for_site'));
        $checkout_id=$request->input('checkout_id');
        $return_date=$request->input('return_date');
       
        $user_id = $this->user_id;
       
        $table_data['checkin_site_id'] = $site_id;
        $table_data['checkin_location_id'] = $location_id;
        $table_data['checkin_department_id'] = $department_id;
        $table_data['return_date'] = $return_date;
        $table_data['checkout_id'] = $checkout_id;
        
        $res = insertRecord('stores_asset_checkin_details', $table_data, $user_id);
        if ($res['success'] == true) {
        //update checkout status
        DB::table('stores_asset_checkout_details')
        ->where('id', $checkout_id)
        ->update(['checkout_status'=>2]);//checked in
   
          //update asset inventory table
        DB::table('ar_asset_inventory')
        ->whereIn('id',   $assets_for_site)
        ->update(['status_id'=>1,'site_id'=>$site_id,"location_id"=>$location_id,"department_id"=>$department_id]);//checked in
        
        //update requistions table
        DB::table('stores_asset_requisitions')
        ->whereIn('id', $requisition_ids)
        ->update(['requisition_status'=>3]);//checked in for requisitions
        //log history
        foreach($assets_for_site as $asset_id){
        $this->insert_into_asset_history($asset_id,"Checked-In");  
        }
            $res=[];
            $res["success"]=true;
            $res['message']="Check-In Details Saved Successfully";
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
public function saveStoresMultipleusersBulkCheckInDetails(Request $request)
{
    try{
        $asset_id = $request->input('assets_for_individuals');
        $requisition_record_id=$request->input('requisition_ids');
      
        $site_id = $request->input('checkin_site_id');
        $location_id = $request->input('checkin_location_id');
        $department_id = $request->input('checkin_department_id');
        $checkout_id=$request->input('checkout_id');
        $return_date=$request->input('return_date');
        $user_id = $this->user_id;
        $table_data = array(
            
            'checkout_id'=>$checkout_id,
            'return_date'=>$return_date,
            'checkin_site_id' => $site_id,
            'checkin_location_id' => $location_id,
            'checkin_department_id' => $department_id,
           
            
        );
        
        $res = insertRecord('stores_asset_checkin_details', $table_data, $user_id);
        if ($res['success'] == true) {
            //update requisitions table
            //update checkout status
            DB::table('stores_asset_checkout_details')
            ->where('id',$checkout_id)
            ->update(['checkout_status'=>2]);
      //update asset inventory table
    DB::table('ar_asset_inventory')
    ->where('id',  $asset_id)
    ->update(['status_id'=>1,'site_id'=>$site_id,"location_id"=>$location_id,"department_id"=>$department_id]);//checked in
    if(substr(  $requisition_record_id, -1)==",")
    {
        $requisition_record_id=substr_replace($requisition_record_id, "", -1);
    }
    $requisition_ids_array=explode(',',$requisition_record_id);
        DB::table('stores_asset_requisitions')
        ->whereIn('id', $requisition_ids_array)
        ->update(['requisition_status'=>3]);//checked in for requisitions
    
    //update requistions table
   
    //log history
   $this->insert_into_asset_history($asset_id,"Checked-In");  
   $res=[];
   $res["success"]=true;
   $res['message']="Check-In Details Saved Successfully";

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

public function saveStoresAssetTransferDetails(Request $request)
{
    try {
        $transfer_record_id = $request->input('transfer_record_id');
        $asset_id = $request->input('parent_asset_id');
        $category_being_transfered_to= $request->input('transfer_to_site_or_ind_id');
        $site_id = $request->input('transfer_to_site_id');
        $location_id = $request->input('transfer_location_id');
        $department_id = $request->input('transfer_department_id');
        $transfer_to_who=$request->input('transfer_to_individual_id');
        $transfer_reason =  $request->input('transfer_reason');
        $transfer_date_checkin_date= $request->input('transfer_date');
        $checkout_transfer_due_date= $request->input('due_date');
        $checkout_id =  $request->input('checkout_id');
        $user_transfered_from=  $request->input('currently_assigned_user_id');
        $site_asset_individual_responsible=$request->input('site_asset_individual_responsible');
     
        $user_id = $this->user_id;
        $table_name = 'stores_asset_transfers';

        $tranfer_data=array(
            'asset_id' => $asset_id,
            "transfer_category"=>$category_being_transfered_to,
            "transfer_reason"=>$transfer_reason,
            "transfer_date"=>$transfer_date_checkin_date,    
        );
        if($category_being_transfered_to==1)
        {
            $tranfer_data['user_transfered_to']=$transfer_to_who;
            $tranfer_data['site_transfered_to']=$site_id;
            $tranfer_data['location_transfered_to']=$location_id;
            $tranfer_data['department_transfered_to']=$department_id;
        }else{
            $tranfer_data['site_transfered_to']=$site_id;
            $tranfer_data['location_transfered_to']=$location_id;
            $tranfer_data['department_transfered_to']=$department_id;
        }
        if(validateisNumeric($user_transfered_from))
        {
            $tranfer_data['user_transfered_from']=$user_transfered_from;
        }
        

       if(isset($transfer_record_id) && $transfer_record_id!="")
       {
           //update edit
       }else{
          
           //insert
           $table_data= $tranfer_data;
           $table_data['checkout_id']=$checkout_id;
           $table_data['created_at'] = Carbon::now();
           $table_data['created_by'] = $user_id;
           $res = insertRecord($table_name, $table_data, $user_id);
           if ($res['success'] == true) {
             $table_name="stores_asset_checkin_details";
              
                $table_data=[];
                $table_data['checkin_site_id'] = $request->input('site_id');
                $table_data['checkin_location_id'] =$request->input('location_id');
                $table_data['checkin_department_id'] = $request->input('department_id');
                $table_data['return_date'] =  $transfer_date_checkin_date;
                $table_data['checkout_id'] = $checkout_id;
                $table_data['created_at'] = Carbon::now();
                $table_data['created_by'] = $user_id;
                $res = insertRecord($table_name, $table_data, $user_id);

               
               
                if (validateisNumeric($checkout_id)) {
                    $table_name="stores_asset_checkout_details";
                        //checkout item
                        $asset_status_id = 2;
                        $checkout_status_id=1;
                        $id = $checkout_id;
                        $table_data=[];
                        $where = array(
                            'id' => $id
                        );
                        //checkin previous user
                        $table_data['checkout_status']=2;
                        $table_data['updated_at'] = Carbon::now();
                        $table_data['updated_by'] = $user_id;
                        $previous_data = getPreviousRecords($table_name, $where);
                        $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);  
                        
                        if ($res['success'] == true) //check in new user/site
                        {
                        $table_data=[];
                        $table_data['checkout_status']=1;
                        $table_data['checkout_site_id'] = $site_id;
                        $table_data['checkout_location_id'] = $location_id;
                        $table_data['checkout_department_id'] = $department_id;
                        $table_data['user_id'] = $transfer_to_who;
                        $table_data['checkout_date'] = $transfer_date_checkin_date;
                        $table_data['no_due_date'] = $request->input('no_due_date');
                        $table_data['due_date'] = $checkout_transfer_due_date;
                        $table_data['asset_id'] = $asset_id;
                        $table_data['updated_at'] = Carbon::now();
                        $table_data['updated_by'] = $user_id;
                        $table_data['site_asset_individual_responsible']=$site_asset_individual_responsible; 
                        $res = insertRecord($table_name, $table_data, $user_id);
                        $this->insert_into_asset_history($asset_id,"transfered");
                        }
                }
               //no need to update item status in inventry table its the samee
              
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


public function saveStoresAssetMaintainanceScheduleDetails(Request $request)
{
    
    try {
       // DB::transaction(function() use ($request, &$res) {
        $maintainance_record_id = $request->input('maintainance_record_id');//for edit
        //$maintainance_location_id =$request->input('maintainance_location_id');
        $date_maintainance_completed =$request->input('date_maintainance_completed');
        $cost =$request->input('maintainance_cost');
        $record_id = $request->input('parent_asset_id');
        $status_id=11;
       // $remarks=$request->input('remarks');
        $schedule_maintainance_date=$request->input('maintainance_due_date_form');
       
        $user_id= $this->user_id;
        $inventory_details_table_name = 'ar_asset_inventory';
        $maintainance_by= $request->input('maintainance_by');
        $maintainance_frequency=$request->input('maintainance_frequency');
        $main_status=$request->input('maintainance_status_form');
        $table_name='stores_asset_maintainance';
        $table_data=[
            "asset_id"=>$record_id,
            //"remarks"=>$remarks,
            "created_by"=>$user_id,
            "created_at"=>carbon::now(),
            "maintainance_due_date"=>$schedule_maintainance_date,  
        ];
        
        
        if(isset($maintainance_record_id) && $maintainance_record_id!="")
        {   $where = array(
            'id' => $maintainance_record_id
        );
            if( (isset($maintainance_by) && $maintainance_by!="") && $date_maintainance_completed=="")
            {
            $table_data=[];
           // $table_data['location_id']=$maintainance_location_id;
            $table_data['maintainance_by']=$maintainance_by;
            $table_data['maintainance_status']=1;
            $table_data['updated_at'] = Carbon::now();
            $table_data['updated_by'] = $user_id;
            $previous_data = getPreviousRecords($table_name, $where);
            $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
            $new_asset_update = array(
                'status_id' =>11,
        
            );
            $where=array(
                'id'=>$record_id
            );
            $previous_data = getPreviousRecords('ar_asset_inventory', $where);
            $res = updateRecord('ar_asset_inventory', $previous_data, $where,  $new_asset_update , $user_id);
       
             $res['message']="Asset Maintainance Dispatch Details have been saved successfully";
             $this->insert_into_asset_history($record_id,"Dispatched for maintainance");
            }else if(isset($date_maintainance_completed) && $date_maintainance_completed!="")
            {   
                $where = array(
                    'id' => $maintainance_record_id
                );
                
                $table_data=[];
                $table_data['date_maintainance_completed']=$date_maintainance_completed;
                $table_data['maintainance_cost']=$cost;
                $table_data['funding_id']=$request->input('funding_id');
                $table_data['maintainance_status']=2;
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                //$table_data['next_maintainance_date']=$next_main_date;
                $previous_data = getPreviousRecords($table_name, $where);
                $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                $asset_update_data = array(
                    'status_id' =>1,
                    'scheduled_for_maintainance'=>0,
                    //"next_maintainance_date"=>$next_main_date,
                    //"has_maintainance_scheduled"=>1,
                );
                if(isset($maintainance_frequency) &&$maintainance_frequency!="" ){
               
                $next_main_date=Carbon::createFromFormat('Y-m-d', $date_maintainance_completed)->addDays($maintainance_frequency)->toDateString();
                $asset_update_data["next_maintainance_date"]=$next_main_date;
                $asset_update_data['has_maintainance_scheduled']=0;
                $asset_update_data['scheduled_for_maintainanc']=0; 
                
                
            }
                $where=array(
                    'id'=>$record_id
                );
                
                    $previous_data = getPreviousRecords('ar_asset_inventory', $where);
                    $res = updateRecord('ar_asset_inventory', $previous_data, $where, $asset_update_data, $user_id);
                 $res['message']="Asset Maintainance Report Details have been saved successfully";
                 $this->insert_into_asset_history($record_id,"maintainance report added");

            }else{
           //edit operation
               
         
           $asset_update_data = array(//previosuly asset_update incase bug
                'maintainance_schedule_date'=>$schedule_maintainance_date,
                "maintainance_frequency"=>$maintainance_frequency,
                //"next_maintainance_date"=>$next_main_date,
                //"has_maintainance_scheduled"=>1,
            );
            
            if(isset($maintainance_frequency) && $maintainance_frequency!="" ){
               
                $next_main_date=Carbon::createFromFormat('Y-m-d', $schedule_maintainance_date)->addDays($maintainance_frequency)->toDateString();
                $asset_update_data["next_maintainance_date"]=$next_main_date;
                $asset_update_data['has_maintainance_scheduled']=1;   
                $asset_update_data['scheduled_for_maintainance']=1;    
            }
            $where=array(
                'id'=>$record_id
            );
            
            if($main_status==4|| $main_status==3)//canced or on hold
            {
                $asset_update_data['status_id']=1;
                $asset_update_data['has_maintainance_scheduled']=0;  
                $asset_update_data['scheduled_for_maintainance']=0; 
                $asset_update_data['next_maintainance_date']="";
               
            }
            $table_data['maintainance_status']=$main_status;
           
         
            
           $previous_data = getPreviousRecords('ar_asset_inventory', $where);
           $res = updateRecord('ar_asset_inventory', $previous_data, $where, $asset_update_data, $user_id);
            
           unset($table_data["created_at"]);
               
           unset($table_data["created_by"]);
            
            $table_data['updated_at'] = Carbon::now();
            $table_data['updated_by'] = $user_id;
           
            $where=array(
                'id'=>$maintainance_record_id
            );
         
           $previous_data = getPreviousRecords($table_name, $where);
           $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
           $res['message']="Asset Maintainance Schedule Details have been saved successfully";
           $verb="";
           if($main_status==3)
           {
               $verb="put on hold";
           }else{
               $verb="canceled";
           }

          $this->insert_into_asset_history($record_id,"maintainance schedule ".$verb);
        }
        }else{
           
          
           //to be modify after confirm schedule status wether 0 assumed o.dipatch 1.
            $If_has_pending_shedule=DB::table('stores_asset_maintainance')->where('asset_id',$record_id)
            ->whereIn('maintainance_status',[0,1])->count();
            //->orwhere('maintainance_status',1)->orwhere('maintainance_status',0)->count();
         
        if($If_has_pending_shedule==0){
           


            $res=insertRecord('stores_asset_maintainance',$table_data,$user_id);
            $next_main_date=Carbon::createFromFormat('Y-m-d', $schedule_maintainance_date)->addDays($maintainance_frequency)->toDateString();
            $table_data = array(
                'maintainance_frequency' =>$maintainance_frequency,
                'scheduled_for_maintainance'=>1,
                "maintainance_schedule_date"=>$schedule_maintainance_date,
                "next_maintainance_date"=>$next_main_date,
                "has_maintainance_scheduled"=>1,

            );
            $where = array(
                'id' => $record_id
            );
            $previous_data = getPreviousRecords('ar_asset_inventory', $where);
            $res = updateRecord('ar_asset_inventory', $previous_data, $where, $table_data, $user_id);
            
            $res['message']="Asset Maintainance Schedule Details have been saved successfully";
            $this->insert_into_asset_history($record_id,"scheduled for maintainance");
        } else{
            $res=[
                "success"=>false,
                 "message"=> "Maintainance Schedule available for asset"
            ];
        }
        }
       
      
        
       }
        catch (\Exception $exception) {
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

public function getStoresActiveScheduledRepairs(Request $request)
{
    try{
       
        $qry = DB::table('stores_asset_repairs as t1')
                ->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
               
                ->join('stores_sites as t4', 't2.site_id', '=', 't4.id')
                ->leftjoin('stores_locations as t5', 't2.location_id', '=', 't5.id')
                ->leftjoin('stores_departments as t6', 't2.department_id', '=', 't6.id')
                ->join('stores_statuses as t7', 't2.status_id', '=', 't7.id')
                ->selectRaw('t1.id as repair_record_id,t1.*,t2.id as parent_asset_id, t2.description,t2.serial_no,t2.grz_no,t4.id as site_id,t4.name as site_name,
                t2.location_id,t2.department_id,t5.name as location_name,t6.name as department_name, t7.name as record_status,t1.scheduled_repair_date,
                t1.repair_status');
               
                $qry->where('repair_status', 0);
                $qry->where('scheduled_for_repair','=','1');
            
    

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


public function saveStoresAssetRepairScheduleDetails(Request $request)
{

    try {
       
        DB::transaction(function() use ($request, &$res) {
        $repair_record_id=$request->input('repair_record_id');
        $date_repair_completed=$request->input('date_repair_completed');
        $record_id = $request->input('asset_id');
        if (!validateisNumeric($record_id)) {
            $record_id = $request->input('parent_asset_id');
        }
       
        //$repair_location_id= $request->input('repair_location_id');
        $status_id=8;
        $user_responsible=$request->input('user_responsible');
        $schedule_repair_date=$request->input("scheduled_repair_date");
        $cost=$request->input('repair_cost');
        $repair_status=$request->input('repair_status_form');
        $user_id= $this->user_id;
        $table_name ='stores_asset_repairs';
        $inventory_details_table_name = 'ar_asset_inventory';
        $repair_wether_successful=$request->input('repair_successful');
       
       
       
        if(isset($repair_record_id) && $repair_record_id!=""){
            $where = array(
                'id' => $repair_record_id
            );
                if( (isset($user_responsible) && $user_responsible!="") && $date_repair_completed=="")
                {
                $table_data=[];
                //$table_data['location_id']=$repair_location_id;
                $table_data['user_responsible']=$user_responsible;
                $table_data['repair_status']=1;
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $previous_data = getPreviousRecords($table_name, $where);
                $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                
                $new_asset_update = array(
                    'status_id' =>8,
            
                );
                $where=array(
                    'id'=>$record_id
                );
            $previous_data = getPreviousRecords('ar_asset_inventory', $where);
            $res = updateRecord('ar_asset_inventory', $previous_data, $where,  $new_asset_update , $user_id);
                // DB::table('ar_asset_inventory')
                //     ->where('id', $record_id)
                //     ->update($asset_update);//under maintainance
            $res['message']="Asset Repair Dispatch Details have been saved successfully";
            $this->insert_into_asset_history($record_id,"dispatched for repair");
            }else if(isset($date_repair_completed) && $date_repair_completed!="")
            {   
                
                $table_data=[];
                $table_data['date_repair_completed']=$date_repair_completed;
                $table_data['funding_id']=$request->input('funding_id');
                $table_data['repair_cost']=$cost;
                $table_data['repair_status']=2;
                $table_data['repair_successful']= $repair_wether_successful;
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
               // $table_data['after_repair_remarks']=$after_repair_remarks;

                $previous_data = getPreviousRecords($table_name, $where);
                $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
               
                if($repair_wether_successful!=1)
                {
                    $asset_update = array(
                        'status_id' =>10,
                        "scheduled_for_repair"=>0
                    );
          
                }else{
                    $asset_update = array(
                        'status_id' =>1,
                        "scheduled_for_repair"=>0
                    );
                }
                DB::table('ar_asset_inventory')
                    ->where('id', $record_id)
                    ->update($asset_update);//make item available
            $res['message']="Asset Repair Report Details have been saved successfully";
                       //update repair status to under repair for damged asset in that table
                       DB::table('stores_asset_loss_damage_details')->where('asset_id',$record_id)
                       ->where('repair_status',1)->update(['repair_status'=>2]);//complted and unsuccess where had been scheduled
            $this->insert_into_asset_history($record_id,"repair report added");

            }else{
               
                if($repair_status==1){
                $where = array(
                    'id' => $repair_record_id
                );
                $table_data=[
                   
                    "scheduled_repair_date"=>$schedule_repair_date,  
                   // "repair_status_form"=>$repair_status,
                ];
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $previous_data = getPreviousRecords($table_name, $where);
                $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                $res['message']="Asset Repair Schedule Details have been saved successfully";
                $this->insert_into_asset_history($record_id,"repair schedule details updated");
                 //update repair status to under repair  for damged asset in that table
          //updated data above 
          //below canceld
                 }
                if($repair_status==3)
                {
                   
                    $where=[
                        'id'=>$record_id
                    ];
                    $asset_update_data['status_id']=5;//return to damaged
                   
                    $asset_update_data['scheduled_for_repair']=3;//return to unschedule; 
                    $asset_update_data['updated_at'] = Carbon::now();
                    $asset_update_data['updated_by'] = $user_id;
                    $previous_data = getPreviousRecords('ar_asset_inventory', $where);
                    $res = updateRecord('ar_asset_inventory', $previous_data, $where, $asset_update_data, $user_id);
                     //update repair status to canceled for damged asset in that table
            DB::table('stores_asset_loss_damage_details')->where('asset_id',$record_id)
            ->whereIn('repair_status',[1,2])->update(['repair_status'=>0]);
            //where is scheduled
            DB::table('stores_asset_repairs')->where('asset_id',$record_id)->where('repair_status',0) 
            ->OrderBy('created_at',"DESC")
            ->update(['repair_status'=>3]);//update canceled
                    $this->insert_into_asset_history($record_id,"repair schedule canceled");
                    DB::table('stores_asset_loss_damage_details')->where('asset_id',$record_id)
                    ->where('repair_status',1)->update(['repair_status'=>0]);
                   //not at any juncture to insert a report damage/loss if asset is there or is in repair.
                }
            }
        }else{//insert operation
            
            $table_data=[
                "asset_id"=>$record_id,
                //"remarks"=>$remarks,
                "created_by"=>$user_id,
                "created_at"=>carbon::now(),
                "scheduled_repair_date"=>$schedule_repair_date,  
            ];
          
            $check=DB::table('stores_asset_repairs')->where('asset_id',$record_id)->whereIn('repair_status',[0,1,3])->count();
                  
                if($check==0) {
                  
                    $res=insertRecord('stores_asset_repairs',$table_data,$user_id);
                   
                    //update repair status to  repair  scheduled for damged asset in that table
                    DB::table('stores_asset_loss_damage_details')->where('asset_id',$record_id)
                        ->where('repair_status',0)->update(['repair_status'=>1]);
                }else{
                  
                    $qry_status=DB::table('stores_asset_repairs')->where('asset_id',$record_id)->whereIn('repair_status',[3])
                    ->selectRaw('id as transaction_id')->get()->toArray();
                   
                        if( count($qry_status)==1) {
                                $res=Db::table('stores_asset_repairs')->where('id',$qry_status[0]->transaction_id)->update(
                                    [
                                        "scheduled_repair_date"=>$schedule_repair_date,
                                        "repair_status"=>0,
                                    
                                        "updated_at"=> Carbon::now(),
                                        "updated_by"=>$this->user_id
                                    ]
                                    );
                                  //update repair status to  repair  scheduled for damged asset in that table
                            DB::table('stores_asset_loss_damage_details')->where('asset_id',$record_id)
                            ->where('repair_status',0)->update(['repair_status'=>1]);
                                
                            
                        }else{
                            $res=array(
                                "success"=>false,
                                "message"=>"Asset already scheduled for repair"
                            );
                        }
                 
                 
                    //new loc
                   
                    //emd new oc
               
         }
            //previos loc
            if($res==1 || $res['success']==true){
                $table_data = array(
                    "scheduled_for_repair"=>1,
                    //"repair_status_form"=>$repair_status

                );
              
                $where = array(
                    'id' => $record_id
                );
                $previous_data = getPreviousRecords('ar_asset_inventory', $where);
                $res = updateRecord('ar_asset_inventory', $previous_data, $where, $table_data, $user_id);
        
                if($res['success']==true){
              
                $res['message']="Asset Repair Schedule Details have been saved successfully"; 
                $this->insert_into_asset_history($record_id,"scheduled for repair");
                }else{
                    $res=array(
                        "success"=>false,
                        "message"=>"There was an error saving the record"
                    );
                }

            }
        }
    

        
    

       },5);
       }
        catch (\Exception $exception) {
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

public function getStoresReservedAssets(Request $request)
{
    try {
        $qry = DB::table('stores_asset_reservations as t1')
            ->join('ar_asset_inventory as t2', 't1.asset_id', '=', 't2.id')
            ->join('users as t3', 't1.user_id', '=', 't3.id')
            ->join('stores_sites as t4', 't1.reservation_site_id', '=', 't4.id')
            ->leftjoin('stores_locations as t5', 't1.reservation_location_id', '=', 't5.id')//to cover nulls
            ->leftjoin('stores_departments as t6', 't1.reservation_department_id', '=', 't6.id')
            ->join('ar_asset_statuses as t7', 't2.status_id', '=', 't7.id')
                   ->selectRaw("t1.id as reservation_id,t1.*,t2.description,t2.serial_no,t1.checkin_status,t2.grz_no,t4.name as site_name,t5.name as location_name,
                    t2.site_id,t2.location_id,t2.department_id,CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as assigned_user,t6.name as department_name,
                    t7.name as record_status,t1.start_date,t1.end_date,t1.reserve_purpose,t1.reserve_for_who as reservation_for_id,t1.id as reservation_record_id,
                    t1.reservation_site_id as checkout_site_id,t1.reservation_location_id as checkout_location_id,
                    t1.reservation_department_id as checkout_department_id,t2.sub_category_id as category_id,t1.end_date as due_date");
        $results = $qry->get()
        ->where('reserve_for_who',1);
       
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

public function saveStoresAssetDisposalDetails(Request $request)
{
    try {
        DB::transaction(function() use ($request, &$res) {
        $record_id = $request->input('asset_id');
        $status_id=9;
        $remarks=$request->input('remarks');
        $date_disposed=$request->input("date_of_disposal");
        $disposal_method=$request->input('disposal_method');
        $disposal_reason=$request->input('disposal_reason');
        $user_id= $this->user_id;
        $inventory_details_table_name = 'ar_asset_inventory';
        $table_data=[
            "asset_id"=>$record_id,
            "remarks"=>$remarks,
            "created_by"=>$user_id,
            "date_of_disposal"=>$date_disposed,  
            "disposal_method"=>$disposal_method,
            "disposal_reason"=>$disposal_reason

        ];
    
       $res= insertRecord('stores_asset_disposal_details',$table_data,$user_id);
        $where = array(
            'id' => $record_id
        );
        $this->insert_into_asset_history($record_id,"disposed");
        if (recordExists($inventory_details_table_name, $where)) {
            $params=[];
            $previous_data = getPreviousRecords($inventory_details_table_name, $where);
            $params['status_id']=$status_id;
            $params['updated_at'] = Carbon::now();
            $params['updated_by'] = $user_id;
           $res=updateRecord($inventory_details_table_name, $previous_data, $where, $params, $user_id);
           $res['message']="Asset Disposal Details have been saved successfully";           
            }

        },5);
       }
        catch (\Exception $exception) {
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


public function saveStoresAssetLossDamageDetails(Request $request)
{
  
        try {
 //DB::transaction(function() use ($request, &$res) {
    $record_id = $request->input('asset_id');
    $lost_damage_id=$request->input('lost_damaged_id');
    $remarks=$request->input('remarks');
    $date_lost_damaged=$request->input("date_lost_damaged");
    $user_id= $this->user_id;
    $inventory_details_table_name = 'ar_asset_inventory';
    $status_id=$request->input('status_id');
  //return $request->all();
    $table_data=[
        "asset_id"=>$record_id,
        "lost_damaged_id"=>$lost_damage_id,
        "lost_damaged_site_id"=>$request->input('lost_damaged_in_site_id'),
        "lost_damaged_location_id"=>$request->input('lost_damaged_in_location_id'),
        "lost_damaged_department_id"=>$request->input('lost_damaged_in_department_id'),
        "individuals_responsible"=>$request->input('user_id'),
        "remarks"=>$remarks,
        "reported_by"=>$user_id,
        "loss_damage_date"=>$date_lost_damaged,
        "created_at"=>Carbon::now()
    ];
  
   
   
    $verb="Loss";
    if($lost_damage_id==5)
    {
        $verb="Damage";
    }

    $check="";
    $record_checkout_asset_ids=[];
  
    if($lost_damage_id==4)
    {
        if($status_id==2)
        {   //$record_id=171;
            $count=DB::table('stores_asset_checkout_details')->where('asset_id',$record_id)->where('checkout_status',1)->count();
          
            if($count==1){
            $result= DB::table('stores_asset_checkout_details')->where('asset_id',$record_id)->where('checkout_status',1)
               ->update(['checkout_status'=>3,"lost_items"=>$record_id]);//symbolizes a loss;
              
        
            }else{
             
               $data= DB::table('stores_asset_checkout_details')->orwhere('is_individual_bulk_checkout',1)
               ->orwhere('is_site_bulk_checkout',1)
               ->where('asset_id','like',"%".$record_id."%")
               ->selectraw('asset_id,id')
               ->where('checkout_status',1)
               ->get()->toArray();
               $checkout_id="";
               
               foreach($data as $specific_record)
               {
                   $specific_record_asset_ids=$this->returnArrayFromStringArray($specific_record->asset_id);
                   foreach($specific_record_asset_ids as $asset_id)
                   {
                       if($asset_id==$record_id)
                       {
                        $checkout_id= $specific_record->id;
                        $record_checkout_asset_ids=$specific_record_asset_ids;
                        break;
                       }
                   }


               }
             
               if($lost_damage_id==4){//lost
               $lost_item_value= DB::table('stores_asset_checkout_details')->where('id',$checkout_id)->value('lost_items');
               if($lost_item_value==null || $lost_item_value=="")
               {
                   $val="[".$record_id."]";
                     $res=DB::table('stores_asset_checkout_details')->where('id',$checkout_id)
                    ->update(["lost_items"=>$val]);
                   
                    $asset_index=array_search($record_id,$record_checkout_asset_ids);
                    unset($record_checkout_asset_ids[$asset_index]);
                    if(count($record_checkout_asset_ids)==0)
                    {
                        DB::table('stores_asset_checkout_details')->where('id',$checkout_id)
                        ->update(["checkout_status"=>3]);
                    }
                  
                }else{
                    $lost_items=$this->returnArrayFromStringArray($lost_item_value);
                    $lost_items[]=$record_id;
                    $lost_items_string="[".(implode(",",$lost_items))."]";
                    DB::table('stores_asset_checkout_details')->where('id',$checkout_id)
                    ->update(["lost_items"=>$lost_items_string]);
                    foreach($lost_items as $item_id)
                    {
                        $asset_index=array_search($item_id,$record_checkout_asset_ids);
                        unset($record_checkout_asset_ids[$asset_index]);
                    }
                  
                    if(count($record_checkout_asset_ids)==0)
                    {
                        DB::table('stores_asset_checkout_details')->where('id',$checkout_id)
                        ->update(["checkout_status"=>3]);
                    }

                }
               }

           
               
            }
         
        }
    }
    
    if($lost_damage_id==4){
    $check=DB::table('stores_asset_loss_damage_details')->whereIn('repair_status',[0,1])->where('asset_id',$record_id)
    ->count();
    }else{
        $check=DB::table('stores_asset_loss_damage_details')->whereIn('repair_status',[0])
        ->where('asset_id',$record_id)
        ->where('lost_damaged_id',5)->count();
    }
  
    if($check==0){
        if($lost_damage_id==4){//lost create docs
        $folder_id=Db::table('ar_asset_inventory')->where('id',$record_id)->value('folder_id');
    
        createAssetRegisterDMSModuleFolders($folder_id, 34,41, $this->dms_id);
     
        $request->merge(['dms'=>$this->dms_id,"comment"=>"none","folder_id"=>$folder_id]);
        $dms=new  DmsController();
        $dms->addDocumentNoFolderIdForAssetLoss(  $request->input('parent_folder_id'),$request->input('sub_module_id'),
        $this->dms_id,$request->input('name'),"None",$request->versioncomment);
      
         //$dms->addDocumentNoFolderId($request);
        }
       
    $res=insertRecord('stores_asset_loss_damage_details',$table_data,$user_id);
 
    }else{
        $res=array(
            "success"=>false,
            "message"=>"Asset Records Already Exist"
        );
    }
    $where = array(
        'id' => $record_id
    );
    
    if (recordExists($inventory_details_table_name, $where)) {
        $params=[];
        $previous_data = getPreviousRecords($inventory_details_table_name, $where);
        $params['status_id']=$lost_damage_id;
        $params['site_id']=$request->input('lost_damaged_in_site_id');
        $params['location_id']=$request->input('lost_damaged_in_location_id');
        $params['department_id']=$request->input('lost_damaged_in_department_id');
        $params['updated_at'] = Carbon::now();
        $params['updated_by'] = $user_id;
        $res=[];
       $res=updateRecord($inventory_details_table_name, $previous_data, $where, $params, $user_id);
       $res['message']="Asset Loss/Damage Details has been saved successfully"; 
       $this->insert_into_asset_history($record_id,$verb." reported");
       
        }

    //},5);
   }
    catch (\Exception $exception) {
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

public function saveStoresAssetWriteOffDetails(Request $request)
{
    try {
        DB::transaction(function() use ($request, &$res) {
        $record_id = $request->input('asset_id');
        $status_id=6;
        $remarks=$request->input('remarks');
        $date_written_off=$request->input("date_written_off");
        $write_off_reason=$request->input('write_off_reason');
        $user_id= $this->user_id;
        $inventory_details_table_name = 'ar_asset_inventory';
        $table_data=[
            "asset_id"=>$record_id,
            "remarks"=>$remarks,
            "created_at"=>Carbon::now(),
            "created_by"=>$user_id,
            "date_written_off"=>$date_written_off,  
            "write_off_reason"=>$write_off_reason
        ];
        insertRecord('stores_asset_write_off_details',$table_data,$user_id);
        $where = array(
            'id' => $record_id
        );
       
        if (recordExists($inventory_details_table_name, $where)) {
            $params=[];
            $previous_data = getPreviousRecords($inventory_details_table_name, $where);
            $params['status_id']=$status_id;
            $params['updated_at'] = Carbon::now();
            $params['updated_by'] = $user_id;
           $res=updateRecord($inventory_details_table_name, $previous_data, $where, $params, $user_id);
           $res['message']="Asset Write-off Details has been saved successfully";           
            }

        },5);
       }
        catch (\Exception $exception) {
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


public function StoresuploadAssets(Request $request)
{
            try{
            $res=array();
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
            }
            $batch_number=$request->input('batch_no');
            $batch_description=$request->input('description');
        
            //header validations
            $document_title=rtrim(ltrim($table_data[0][0]));
        
            if($document_title!="MINISTRY OF EDUCATION - KEEPING GIRLS IN SCHOOL PROJECT")
            {
                return response()->json([
                    "success"=>false,
                "message"=> "Invalid Document Header" 
                ]);
            }
            $document_sub_title=rtrim(ltrim($table_data[1][0]));
            if($document_sub_title!="ASSET REGISTER")
            {
                return response()->json([
                    "success"=>false,
                "message"=> "Invalid Document Sub-Header" 
                ]);
            }
            //column headings
            $headings=$table_data[2];
            $valid_headings=["ITEM DESCRIPTION","CATEGORY","SUB-CATEGORY","BRAND","MODEL","PURCHASED FROM",
            "DATE PURCHASED","PURCHASE COST","SUPPLIER","IDENTIFIER_TYPE","UNIQUE_IDENTIFIER","GRZ SERIAL NO","Site"];
            $invalid_headings=[];
            foreach($headings as $key=>$heading)
            {
                if(!in_array($heading,$valid_headings) && $key<11)
                {
                    $invalid_headings[]=$heading;
                }
                
            }
            if(count($invalid_headings)>0)
            {
                if(count($invalid_headings)==1){
                $message="Invalid heading ". implode(",",$invalid_headings);
                }else{
                $message="Invalid headings :". implode(",",$invalid_headings);
                }
                return response()->json([
                    "success"=>false,
                "message"=> $message
                ]);
            }
            //unset headings now we have pure asset data
            unset($table_data[0]);
            unset($table_data[1]);
            unset($table_data[2]);
            $invalid_entries=[];
            // $item=$table_data[3][1];
            // //$pieces=explode("-",$item);
            // $res=$this->ValidateNumStringEntry($item);
            // if($res==0)
            // {
            //     return response()->json([
            //         "success"=>false,
            //        "message"=> "Invalid Entry for Asset Category at row number "
            //     ]);
            // }
            
            //build on missing identifiers
            $missing_identifiers=[];
            foreach($table_data as $key=>$asset_data)
            {   
                
                if($asset_data[10]!="" && $asset_data[10]!=null){
                    $unique_identifier=ltrim($asset_data[10]);
                    $unique_identifier=strtolower(rtrim($unique_identifier));
                    if($unique_identifier=="unknown")
                    {
                        $missing_identifiers[]=$asset_data;
                        unset($table_data[$key]);

                    }

                }
            }

        
            //end build identfiers
            //check deupliacte serials within upload 
            $duplicate_serials=[];
            $compared_keys=[];//the duplicates match
            foreach($table_data as $key=>$asset_data)
            {   

                if($asset_data[10]!="" && $asset_data[10]!=null){
                    $serial_no_one=rtrim($asset_data[10]);
                    $serial_no_one=ltrim($serial_no_one);
                foreach($table_data as $similar_key_cand=>$asset_data_check)
                {
                    if(!in_array($key,$compared_keys) && $similar_key_cand!=$key){
                    if($asset_data_check[10]!="" && $asset_data_check[10]!=null){
                        $serial_no_two=rtrim($asset_data_check[10]);
                        $serial_no_two=ltrim($serial_no_two);
                        if($serial_no_one==$serial_no_two)
                        {
                            $duplicate_serials[]="Unique Identifier. of Asset at row ".($key+1)." similar to that of asset at row".($similar_key_cand+1);
                            $compared_keys[]=$similar_key_cand;
                        
                        }
                    }
                }
                }
            }
            }
        
        
            if(count( $duplicate_serials)>0){
                return response()->json([
                "success"=>false,
                "message"=>"Duplicate Unique Identifiers: " ."<br/>".implode("<br/>",  $duplicate_serials)
                ]);
            }
            //end check duplicates identifiers within upload
        
            $duplicate_grz_nos=[];
            $compared_keys_grz=[];//the duplicates match
        
            foreach($table_data as $key=>$asset_data)
            {   

                if($asset_data[11]!="" && $asset_data[11]!=null){
                    $serial_no_one=rtrim($asset_data[11]);
                    $serial_no_one=ltrim($serial_no_one);
                foreach($table_data as $similar_key_cand=>$asset_data_check)
                {
                    if(!in_array($key,$compared_keys_grz) && $similar_key_cand!=$key){
                    if($asset_data_check[11]!="" && $asset_data_check[11]!=null){
                        $serial_no_two=rtrim($asset_data_check[11]);
                        $serial_no_two=ltrim($serial_no_two);
                    
                        if($serial_no_one==$serial_no_two)
                        {
                            $duplicate_grz_nos[]="Grz No. of Asset at row ".($key+1)." similar to that of asset at row ".($similar_key_cand+1);
                            $compared_keys_grz[]=$similar_key_cand;
                        
                        }
                    }
                }
                }
            }
            }
            if(count(  $duplicate_grz_nos)>0){
                return response()->json([
                "success"=>false,
                "message"=>"Duplicate Grz Nos: " ."<br/>".implode("<br/>",   $duplicate_grz_nos)
                ]);
            }
        
            //end check duplicates grz no within upload

        
            //assign system identifiers
            $identifiers_max=[];
            foreach($missing_identifiers as $key=>$asset_data)
            {
                $pieces=explode("-",$asset_data[1]);
                $asset_category=DB::table('stores_categories')->where('code',$pieces[0])->value('name');
                $asset_category=rtrim(ltrim($asset_category));
                $cat_pieces=explode(" ",$asset_category);
                if($cat_pieces>1)
                {
                    $asset_category=implode("-",$cat_pieces);
                }
                $category_id=DB::table('stores_categories')->where('code',$pieces[0])->value('id');
                if(!array_key_exists($category_id,$identifiers_max)){
                $max_category_id=DB::table('ar_asset_inventory')->where('category_id',$category_id)->max('id');
                $suffix=$max_category_id+1;
                $identifiers_max[$category_id]=$suffix;
                }else{
                    $current_max=$identifiers_max[$category_id];
                    $identifiers_max[$category_id]=$current_max+1;
                }
                $unique_identifier=strtoupper($asset_category)."-".$identifiers_max[$category_id];
                $asset_data[10]=$unique_identifier;
                $missing_identifiers[$key]=$asset_data;
            }
        
            //flag bad serial numbers
            $invalid_serial_nums=[];
            foreach($table_data as $key=>$asset_data)
            {

                if($asset_data[10]!="" && $asset_data[10]!=null){
                    $serial_no=$asset_data[10];
                    if(strlen($serial_no)<3)
                    {
                        $invalid_serial_nums[]="short unique Identifier at at row ".($key+1);
                    }

                }

            }
            
            if(count( $invalid_serial_nums)>0){
                return response()->json([
                "success"=>false,
                "message"=>"Invalid serial Nos: " ."<br/>".implode("<br/>",  $invalid_serial_nums)
                ]);
            }

            //end flag serial nums
            //flag bad grz no
            $invalid_grz_nums=[];
            foreach($table_data as $key=>$asset_data)
            {
                if($asset_data[11]!="" && $asset_data[11]!=null){
                    $serial_no=$asset_data[11];
                    if(strlen($serial_no)<3)
                    {
                        $invalid_grz_nums[]="short Grz No. at at row ".($key+1);
                    }

                }

            }
            if(count( $invalid_grz_nums)>0){
                return response()->json([
                "success"=>false,
                "message"=>"Invalid Grz. Nos: " ."<br/>".implode("<br/>",$invalid_grz_nums)
                ]);
            }
        $invalid_statuses=array();
        $allowed_statuses=["available",'checked_out','disposed','lost'];
        foreach($table_data as $key=>$asset_data)
        {
            if($asset_data[13]!="" && $asset_data[13]!=null){
                $cleaned_data= strtolower(ltrim(rtrim($asset_data[13])));
                if(!in_array($cleaned_data,$allowed_statuses))
                {
                    $invalid_statuses[]="Invalid  Asset Status at  row ".($key+1); 
                }else{
                    if($cleaned_data=="available" || $cleaned_data=="checked_out"){
                        if($asset_data[12]=="" || $asset_data[12]==null){
                            $invalid_statuses[]="Location is required for Asset  at  row ".($key+1); 
                        }
                    }
                }
            }else{
                $invalid_statuses[]="Missing Asset Status at  row ".($key+1); 
            }
        }   
        if(count($invalid_statuses)>0){
            return response()->json([
            "success"=>false,
            "message"=>"Invalid/Missing Asset Statuses: " ."<br/>".implode("<br/>", $invalid_statuses)
            ]);
        }
            //end flag grz no

        //merge assets with missing identifiers to others
        if(count($missing_identifiers)>0)
        {
            $table_data=array_merge($table_data,$missing_identifiers);
        }

        //end merge
            foreach($table_data as $key=>$asset_data)
            {
                $asset_description = $asset_data[0];
                // $validate_entries_array=[$asset_data[1],$asset_data[2],$asset_data[3],$asset_data[4],$asset_data[8],$asset_data[9],$asset_data[12]];
                // $header_names=["Asset Category","Asset Sub-Category","Asset Brand","Asset Model","Supplier","Asset Identifier","Site"];
                $validate_entries_array=[$asset_data[1],$asset_data[2],$asset_data[3],$asset_data[4],$asset_data[8],$asset_data[9]];
                $header_names=["Asset Category","Asset Sub-Category","Asset Brand","Asset Model","Supplier","Asset Identifier"];
                foreach($validate_entries_array as $pos=>$to_validate_data)
                {
                    $res=$this->ValidateNumStringEntry($to_validate_data);
                    if($res==0)
                    {
                        $invalid_entries[]=$header_names[$pos]." at row ".($key+1);
                    }
                }
                    
            }
            if(count($invalid_entries)>0){
                return response()->json([
                "success"=>false,
                "message"=>"Invalid Entries: " ."<br/>".implode("<br/>",$invalid_entries)
                ]);
            }
            //validate against db data
            $invalid_entries_not_match_db=[];
            foreach($table_data as $key=>$asset_data)
            {
                // $validate_entries_array=[$asset_data[1],$asset_data[2],$asset_data[3],$asset_data[4],$asset_data[8],$asset_data[9],$asset_data[12]];
                // $validate_entries_data_match_keys=[1,2,3,4,8,9,12];
                // $header_names=["Asset Category","Asset Sub-Category","Asset Brand","Asset Model","Supplier","Asset Identifier","Site"];
                // //$table_column_names=["id","id","id","id","id"]; 
                // $table_column_names=["code","code","code","code","code","code","code"]; 
                // $table_names=["ar_asset_categories","ar_asset_subcategories","ar_asset_brands","ar_asset_models","ar_asset_suppliers", "ar_asset_identifiers","ar_asset_sites"];
            
                $validate_entries_array=[$asset_data[1],$asset_data[2],$asset_data[3],$asset_data[4],$asset_data[8],$asset_data[9]];
                $validate_entries_data_match_keys=[1,2,3,4,8,9,12];
                $header_names=["Asset Category","Asset Sub-Category","Asset Brand","Asset Model","Supplier","Asset Identifier"];
                $table_column_names=["code","code","code","code","code","code","code"]; 
                $table_names=["stores_categories","stores_subcategories","stores_brands","stores_models","stores_suppliers", "stores_identifiers"];
        
            
                foreach($validate_entries_array as $pos=>$to_table_check_data)
            {
                $pieces=explode("-",$to_table_check_data);
                
                //    $if_available=DB::table($table_names[$pos])->where($table_column_names[$pos],$pieces[0])->count();
                //    if($if_available==0)
                //    {
                //     $invalid_entries_not_match_db[]=$header_names[$pos]." at row ".($key+1);
                //    }
                    $value_if_available=DB::table($table_names[$pos])->where($table_column_names[$pos],$pieces[0])->value('id');
                    if($value_if_available=="")
                    {
                        //return $pieces[0];
                        $invalid_entries_not_match_db[]=$header_names[$pos]." at row ".($key+1);
                    }else{
                        //shift code to ids
                        $asset_data [$validate_entries_data_match_keys[$pos]]=$value_if_available;
                        $table_data[$key]=$asset_data;
                    }
                
            }

            }
            if(count($invalid_entries_not_match_db)>0){
                return response()->json([
                "success"=>false,
                "message"=>"Non Existing Items: " ."<br/>".implode("<br/>", $invalid_entries_not_match_db)
                ]);
            }
            

            $to_add_assets=[];
            foreach($table_data as $key=>$asset_data)
            {
            
                $if_exists_already_serial=0;
                $if_exists_already_grz=0;
                if($asset_data[10]!="" && $asset_data[10]!=null){
                    $serial_no=$asset_data[10];
                $qry=DB::table('ar_asset_inventory');
                $qry->where('serial_no',$serial_no);
                $if_exists_already_serial+=$qry->count();
                $qry=DB::table('stores_pre_upload');
                $qry->where('serial_no',$serial_no);
                $if_exists_already_serial+=$qry->count();
                }
                if($asset_data[11]!="" && $asset_data[11]!=null){
                    $grz_no=$asset_data[11];
                    $qry=DB::table('ar_asset_inventory');
                    $qry->where('grz_no',$grz_no);
                    $if_exists_already_grz+=$qry->count();
                    $grz_no=$asset_data[11];
                    $qry=DB::table('stores_pre_upload');
                    $qry->where('grz_no',$grz_no);
                    $if_exists_already_grz+=$qry->count();
                }
            

                if($if_exists_already_serial==0  && $if_exists_already_grz==0)//to change to 0
                {
                    $to_add_assets[]=$asset_data;
                }

            }
            if(count($to_add_assets)==0){
                    return response()->json([
                    "success"=>false,
                    "message"=>"All uploaded items already exist in Inventory/Pending Upload Batches"
                    ]);
            }


            $table_data=array(
                "batch_no"=>$batch_number,
                "description"=>$batch_description,
                "status"=>0,
                "created_at"=>Carbon::now(),
                "created_by"=>$this->user_id,
            );
            $batch_id =DB::table('stores_asset_upload_batches')->insertGetId($table_data);
            if(!validateisNumeric($batch_id)) {
                return response()->json([
                    "success"=>false,
                    "message"=>"An unexpected error was encountered during the upload"
                    ]);
                }
            

            $table_data=$to_add_assets;
        
            
            $tblkeystounset=[];//for null rows;
            if(count($table_data)>0)
            {
                foreach($table_data as $key=>$asset_data)
                {
                foreach($asset_data as $keyData=>$asset_data_inner)
                {
                    if($keyData<7 && ($asset_data_inner==null || $asset_data_inner==""))
                    {
                        if(!in_array($key,$tblkeystounset))
                        {
                            $tblkeystounset[]=$key;
                        }
                    }
                }
                    
                        $initial_asset_data_count=count($asset_data);
                        $purchase_date = $asset_data[6];
                        if(strlen($purchase_date)==4)
                        {
                            $purchase_date="01/01/".$purchase_date;
                        }
                        $purchasedateObj="";
                        if($purchase_date!=null && $purchase_date!=""){
                        $purchasedateObj = \DateTime::createFromFormat("d/m/Y", $purchase_date);
                        }
                        if (!$purchasedateObj)
                        {   
                            if(!in_array($key,$tblkeystounset)){
                    
                            throw new \UnexpectedValueException("Could not parse the date:  $asset_data[6]");
                            }
                        }
                        $finalPurchaseDate= $purchasedateObj->format("Y-m-d");
                        // $finalPurchaseDate=$purchase_date;
                        $cleaned_status= strtolower(ltrim(rtrim($asset_data[13])));

                    $asset_data['description']=$asset_data[0];
                    $asset_data['category_id']=explode("-",$asset_data[1])[0];
                    $asset_data['sub_category_id']=explode("-",$asset_data[2])[0];
                    $asset_data['brand_id']=explode("-",$asset_data[3])[0];
                    $asset_data['model_id']=explode("-",$asset_data[4])[0];
                    $asset_data['purchase_from']=$asset_data[5];
                    $asset_data['purchase_date']= $finalPurchaseDate;
                    $asset_data['cost']=$asset_data[7];
                    $asset_data['supplier_id']=$asset_data[8];
                    $asset_data['identifier_id']=explode("-",$asset_data[9])[0];
                    $asset_data['serial_no']=$asset_data[10];
                    $asset_data['grz_no']=$asset_data[11];
                    //$asset_data['site_id']=explode("-",$asset_data[12])[0];
                    $asset_data['location']=$asset_data[12];
                    $asset_data['status_id']=$this->returnAssetStatusID($cleaned_status);
                    $asset_data['upload_status']=1;
                    $asset_data['created_at']=Carbon::now();
                    $asset_data['created_at']=Carbon::now();
                    $asset_data['created_by']=$this->user_id;
                    $asset_data['batch_id']=$batch_id;
                    $parent_id= 226347;
                    //$parent_id=259105;
                    $parent_id=228976;
                
                $folder_id=createAssetDMSParentFolder($parent_id,34 , $asset_data[10], '', $this->dms_id);
                    createAssetRegisterDMSModuleFolders($folder_id, 34,35, $this->dms_id);
                    $asset_data['folder_id']=$folder_id;
                    $asset_data['view_id'] = generateRecordViewID();              
                    //for($i=0;$i<11;$i++)
                    for($i=0;$i<$initial_asset_data_count;$i++)
                    {
                        unset($asset_data[$i]);
                    }
                    $table_data[$key]=$asset_data;
                
                
                }
            
                //remove null rows
                foreach($tblkeystounset as $to_unset)
                {
                    unset($table_data[$to_unset]);
                }
                //end remove null
            //create upload batch 30/07/2022
            
            
                $info="";
                $results=$table_data;
                if(count($table_data)>500)
                {
                
                $total_loop=ceil(count($results)/500);
                $start_index=0;
                $end_index=500;
                    for($i=1;$i<=$total_loop;$i++)
                    {
        
                        $results_to_insert=array();
                        foreach($results as $key=>$result)
                        {
                            if($key>=$start_index && $key<=$end_index)
                            {
                                $results_to_insert[]=$result;
                            }
                        }
                        // $info=DB::table('ar_asset_inventory')->insert($results_to_insert);
                        $info=DB::table('stores_pre_upload')->insert($results_to_insert);

                        $results_to_insert=[];
                                if($i!=$total_loop-1){
                                $start_index=$end_index+1;
                                $end_index=$start_index+500;
                                }else{
                                    $start_index=$end_index+1;
                                    $end_index=(count($results)-1);
                                }
        
                    }
                }else{
                    //$info=DB::table('ar_asset_inventory')->insert($results);
                    $info=DB::table('stores_pre_upload')->insert($results);
                }
                
            if($info==true)
            {
                    $res=array(
                        "success"=>true,
                        "message"=>"Assets Upload Success",
                    
                    );
            }else{
                $res=array(
                    "success"=>false,
                    "message"=>"Assets Upload Failed",
                
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

public function getStoresNextAssetBatchNo(){
    try{
        $latest_batch= DB::table('stores_asset_upload_batches')->latest('created_at')->first();
        if(!is_object($latest_batch))
    {
        $next_batch_no="KGS-ARU-01";
        $res = array(
            'success' => true,
            'results' =>   $next_batch_no
             );
        return response()->json($res);  
    }
        $latest_batch_no=$latest_batch->batch_no;
        $last_number=explode('-',$latest_batch_no)[2];
        $last_number=intval($last_number);
        $next_number=$last_number+1;
        if($next_number<10)
        {
            $next_number="0$next_number";
        }
        $next_batch_no="KGS-ARU-$next_number";
        $res = array(
            'success' => true,
            'results' =>   $next_batch_no
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



public function saveAssetInsuranceClaimDetails(Request $request)
{
      
            try {
     //DB::transaction(function() use ($request, &$res) {
        $policy_id = $request->input('asset_id');
        $asset_id=$request->input('asset_id');
        $claim_amount=$request->input('claim_amount');
        $claim_number=$request->input('claim_number');
        $sub_module_id=$request->input('sub_module_id');
        $folder_id=$request->input('parent_folder_id');
        $document_name =$request->input('document_name');
        $record_id=$request->input('record_id');

        if($folder_id==null)
        {
            $parent_id= 226347;
            $parent_id=228976;
            $folder_id=createAssetDMSParentFolder($parent_id,34 , $claim_number, '', $this->dms_id);
            $table_data['folder_id']=$folder_id;
            createAssetRegisterDMSModuleFolders($folder_id, 34,36, $this->dms_id);
        }else{
            $folder_id=$request->input('parent_folder_id');
        }
     
        $where = array(
            'id' => $record_id
        );
       $res=array();
       $table_name='ar_asset_insurance_claims';
        if (validateisNumeric($record_id)) {
          
            $previous_data = array();
            if (recordExists($table_name, $where)) {
                $table_data=[
                    "asset_id"=>$asset_id,
                    "claim_amount"=>$claim_amount,
                    "claim_number"=>$claim_number,
                ];
               
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $this->user_id;
                $previous_data = getPreviousRecords($table_name, $where);
                $res = updateRecord($table_name, $previous_data, $where, $table_data, $this->user_id);
                $this->insert_into_asset_history($record_id,"Details Updated");
            }
        }else{
        $request->merge(['dms'=>$this->dms_id,"comment"=>"none","folder_id"=>$folder_id]);
        $dms=new  DmsController();
        $dms->addDocumentNoFolderIdForAssetLoss($folder_id,$request->input('sub_module_id'),
        $this->dms_id,$document_name,"None",$request->versioncomment);
        $table_data=[
            "asset_id"=>$asset_id,
            "policy_id"=>$policy_id,
            "claim_amount"=>$claim_amount,
            "claim_number"=>$claim_number,
            "folder_id"=>$folder_id,
            "created_at"=>Carbon::now(),
            "created_by"=>$this->user_id,
        ];
        $res=insertRecord($table_name,$table_data,$this->user_id);
      }

        //},5);
       }
        catch (\Exception $exception) {
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

public function getAssetTransferReport(Request $request)
{
        $url=url('/');
        $pdf_url = $url."/assetregister/generateAssetTransferReport?module=".$request->input('module_id')."&record_id=".$request->input('record_id');  
        $res = array(
            'success' => true,
            'message' => "Report Generated",
            'results' => $pdf_url
        );
    return response()->json($res);
}


 public function generateAssetTransferReport(Request $request)
{
        $url=url('/');
        $image_url=$url.'/backend/public/moge_logo.png';

        $table_name='ar_asset_transfers';
        $transfers="";
        if($request->input('module')==350)
        {
            $transfers = DB::table("ar_asset_transfers as t1")        
        ->join('ar_asset_checkin_details as t2','t2.checkout_id','=','t1.checkout_id')
        ->join('ar_asset_inventory as t3','t3.id','=','t1.asset_id')
       
        ->selectRaw("t1.id as record_id,t1.asset_id as transfer_asset_id,t1.transfer_category,t1.transfer_reason,t1.transfer_date,
        t1.user_transfered_to,t1.user_transfered_from,t1.site_transfered_to,t1.location_transfered_to,t1.department_transfered_to,t2.checkin_site_id as
        previous_site_id,t2.checkin_location_id as previous_location_id,t2.checkin_department_id as previous_department_id,
        t3.serial_no,t3.grz_no,t3.description")->orderBy('t1.created_at','DESC')
        ->where('t1.id',$request->input('record_id'))->get();
        //return $transfers;
        $transfers= json_decode(json_encode($transfers),true);
        $sites = json_decode(json_encode(DB::table('ar_asset_sites as t2')->selectRaw('t2.id,t2.name')->get()->toArray()),true);
        $locations = json_decode(json_encode(DB::table('ar_asset_locations as t3')->selectRaw('t3.id,t3.name')->get()->toArray()),true);
        $deparments = json_decode(json_encode(DB::table('departments as t3')->selectRaw('t3.id,t3.name')->get()->toArray()),true);    
        $users = json_decode(json_encode(DB::table('users as t3')->selectRaw("t3.id,CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as assigned_user")->get()->toArray()),true);     
        foreach($transfers as $key=>$transfer)
        {   //user
            if($transfer['user_transfered_to']!=null)
            {
                foreach($users as $user)
                {

                    if($user['id']==$transfer['user_transfered_to'])
                    {
                        $transfer['user_transfered_to_name']=$user['assigned_user'];
                    }
                   
                }
            }

            //user
            if($transfer['user_transfered_from']!=null)
            {
                foreach($users as $user)
                {

                    if($user['id']==$transfer['user_transfered_from'])
                    {
                        $transfer['user_transfered_from_name']=$user['assigned_user'];
                    }
                   
                }
            }else{
                $transfer['user_transfered_from_name']='';

            }
            //site
            if($transfer['site_transfered_to']!=null)
            {
                foreach($sites as $site)
                {

                    if($site['id']==$transfer['site_transfered_to'])
                    {
                        $transfer['site_transfered_to_name']=$site['name'];
                    }
                   
                }
            }
            if($transfer['previous_site_id']!=null)
            {
                foreach($sites as $site)
                {

                    if($site['id']==$transfer['previous_site_id'])
                    {
                        $transfer['previous_site_name']=$site['name'];
                    }
                   
                }
            }
            //location
            if($transfer['location_transfered_to']!=null)
            {
                foreach($locations as $location)
                {

                    if($location['id']==$transfer['location_transfered_to'])
                    {
                        $transfer['location_transfered_to_name']=$location['name'];
                    }
                   
                }
            }
            if($transfer['previous_location_id']!=null)
            {
                foreach($locations as $location)
                {

                    if($location['id']==$transfer['previous_location_id'])
                    {
                        $transfer['previous_location_name']=$location['name'];
                    }
                   
                }
            }
            //department
            if($transfer['department_transfered_to']!=null)
            {
                foreach($deparments as $deparment)
                {

                    if($deparment['id']==$transfer['department_transfered_to'])
                    {
                        $transfer['department_transfered_to_name']=$deparment['name'];
                    }
                   
                }
            }
            if($transfer['previous_department_id']!=null)
            {
                foreach($deparments as $deparment)
                {

                    if($deparment['id']==$transfer['previous_department_id'])
                    {
                        $transfer['previous_department_name']=$deparment['name'];
                    }
                   
                }
            }
            $transfers[$key]=$transfer;
          
        }
           
        }else{
            
            $transfers = DB::table("stores_asset_transfers as t1")        
        ->join('stores_asset_checkin_details as t2','t2.checkout_id','=','t1.checkout_id')
        ->join('ar_asset_inventory as t3','t3.id','=','t1.asset_id')
       
        ->selectRaw("t1.id as record_id,t1.asset_id as transfer_asset_id,t1.transfer_category,t1.transfer_reason,t1.transfer_date,
        t1.user_transfered_to,t1.user_transfered_from,t1.site_transfered_to,t1.location_transfered_to,t1.department_transfered_to,t2.checkin_site_id as
        previous_site_id,t2.checkin_location_id as previous_location_id,t2.checkin_department_id as previous_department_id,
        t3.serial_no,t3.grz_no,t3.description")->orderBy('t1.created_at','DESC')
        ->where('t1.id',$request->input('record_id'))->get();
        //return $transfers;
        $transfers= json_decode(json_encode($transfers),true);
        $sites = json_decode(json_encode(DB::table('stores_sites as t2')->selectRaw('t2.id,t2.name')->get()->toArray()),true);
        $locations = json_decode(json_encode(DB::table('stores_locations as t3')->selectRaw('t3.id,t3.name')->get()->toArray()),true);
        $deparments = json_decode(json_encode(DB::table('stores_departments as t3')->selectRaw('t3.id,t3.name')->get()->toArray()),true);    
        $users = json_decode(json_encode(DB::table('users as t3')->selectRaw("t3.id,CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as assigned_user")->get()->toArray()),true);     
        foreach($transfers as $key=>$transfer)
        {   //user
            if($transfer['user_transfered_to']!=null)
            {
                foreach($users as $user)
                {

                    if($user['id']==$transfer['user_transfered_to'])
                    {
                        $transfer['user_transfered_to_name']=$user['assigned_user'];
                    }
                   
                }
            }

            //user
            if($transfer['user_transfered_from']!=null)
            {
                foreach($users as $user)
                {

                    if($user['id']==$transfer['user_transfered_from'])
                    {
                        $transfer['user_transfered_from_name']=$user['assigned_user'];
                    }
                   
                }
            }
            //site
            if($transfer['site_transfered_to']!=null)
            {
                foreach($sites as $site)
                {

                    if($site['id']==$transfer['site_transfered_to'])
                    {
                        $transfer['site_transfered_to_name']=$site['name'];
                    }
                   
                }
            }
            if($transfer['previous_site_id']!=null)
            {
                foreach($sites as $site)
                {

                    if($site['id']==$transfer['previous_site_id'])
                    {
                        $transfer['previous_site_name']=$site['name'];
                    }
                   
                }
            }
            //location
            if($transfer['location_transfered_to']!=null)
            {
                foreach($locations as $location)
                {

                    if($location['id']==$transfer['location_transfered_to'])
                    {
                        $transfer['location_transfered_to_name']=$location['name'];
                    }
                   
                }
            }
            if($transfer['previous_location_id']!=null)
            {
                foreach($locations as $location)
                {

                    if($location['id']==$transfer['previous_location_id'])
                    {
                        $transfer['previous_location_name']=$location['name'];
                    }
                   
                }
            }
            //department
            if($transfer['department_transfered_to']!=null)
            {
                foreach($deparments as $deparment)
                {

                    if($deparment['id']==$transfer['department_transfered_to'])
                    {
                        $transfer['department_transfered_to_name']=$deparment['name'];
                    }
                   
                }
            }
            if($transfer['previous_department_id']!=null)
            {
                foreach($deparments as $deparment)
                {

                    if($deparment['id']==$transfer['previous_department_id'])
                    {
                        $transfer['previous_department_name']=$deparment['name'];
                    }
                   
                }
            }
            $transfers[$key]=$transfer;
          
        }


        }
        


                        $table_headers=["User"];
                         $width=100/count($table_headers);
                         $report_title='';
                         $header_style= "width:".$width."%; background-color: #04AA6D;color: white;text-align: left";
                         $data_style="width:".$width."%;text-align: left;padding-left:7px;";
                        $asset_inventory=$transfers;
                     
                        $exclusion_fields=[];
                        
                        $report_date= date('d-M-Y H:i:s');
                        $view=view('assettransferreport',compact('asset_inventory','report_date','image_url',
                        'table_headers','header_style','data_style','exclusion_fields','report_title'))->render();
                        $this->generatePDFFIle($view,'A6','potrait');
                        //return $view;

}

public function saveStoresAssetInsuranceClaimDetails(Request $request)
{
  
        try {
 //DB::transaction(function() use ($request, &$res) {
    $policy_id = $request->input('asset_id');
    $asset_id=$request->input('asset_id');
    $claim_amount=$request->input('claim_amount');
    $claim_number=$request->input('claim_number');
    $sub_module_id=$request->input('sub_module_id');
    $folder_id=$request->input('parent_folder_id');
    $document_name =$request->input('document_name');
    $record_id=$request->input('record_id');

    if($folder_id==null)
    {
        $parent_id= 226347;
        $parent_id=228976;
        $folder_id=createAssetDMSParentFolder($parent_id,34 , $claim_number, '', $this->dms_id);
        $table_data['folder_id']=$folder_id;
        createAssetRegisterDMSModuleFolders($folder_id, 34,36, $this->dms_id);
    }else{
        $folder_id=$request->input('parent_folder_id');
    }
 
    $where = array(
        'id' => $record_id
    );
   $res=array();
   $table_name='stores_asset_insurance_claims';
    if (validateisNumeric($record_id)) {
      
        $previous_data = array();
        if (recordExists($table_name, $where)) {
            $table_data=[
                "asset_id"=>$asset_id,
                "claim_amount"=>$claim_amount,
                "claim_number"=>$claim_number,
            ];
           
            $table_data['updated_at'] = Carbon::now();
            $table_data['updated_by'] = $this->user_id;
            $previous_data = getPreviousRecords($table_name, $where);
            $res = updateRecord($table_name, $previous_data, $where, $table_data, $this->user_id);
            $this->insert_into_asset_history($record_id,"Details Updated");
        }
    }else{
    $request->merge(['dms'=>$this->dms_id,"comment"=>"none","folder_id"=>$folder_id]);
    $dms=new  DmsController();
    $dms->addDocumentNoFolderIdForAssetLoss($folder_id,$request->input('sub_module_id'),
    $this->dms_id,$document_name,"None",$request->versioncomment);
    $table_data=[
        "asset_id"=>$asset_id,
        "policy_id"=>$policy_id,
        "claim_amount"=>$claim_amount,
        "claim_number"=>$claim_number,
        "folder_id"=>$folder_id,
        "created_at"=>Carbon::now(),
        "created_by"=>$this->user_id,
    ];
    $res=insertRecord($table_name,$table_data,$this->user_id);
  }

    //},5);
   }
    catch (\Exception $exception) {
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

public function getStoresAssetReport(Request $request)
{
   
    $url=url('/');
    $image_url=$url.'/backend/public/moge_logo.png';
    $report_type= $request->query('report_type');
    $pdf_url = $url."/assetregister/generateStoresAssetsReport?report_type=".$report_type;
    $res=array();
    $report_order=$request->query('report_order');
    $start_date=$request->query('start_date');
    $end_date=$request->query('end_date');
   
    if($report_order)
    {
        $pdf_url.="&report_order=".$report_order;
    }
    if($start_date)
    {
        $pdf_url.="&start_date=".$start_date; 
    }
    if($end_date)
    {
        $pdf_url.="&end_date=".$end_date; 
    }

   
    switch($report_type)
    {   
        case 12:
        case 11:
           $pdf_url = $url."/assetregister/generateStoresAssetsReport?report_type=".$report_type;
           $res = array(
            'success' => true,
            'message' => "Report Generated",
            'results' => $pdf_url
            );
            //return Excel::download(new ConsolidatedAssetsData(),'.ConsolidatedAssetsData.xlsx');
            break;
        case 10:
            $qry=DB::table('ar_asset_archive as t1')
            ->join('ar_asset_inventory as t2','t2.id','t1.asset_id')
            ->where('module_id',637)
           
             ->selectRaw('t2.description,t2.serial_no,t2.grz_no,t1.archive_date');
             if($start_date)
             {
                 $qry->whereDate('t1.archive_date','>=',Carbon::parse($start_date));    
             }
             if($end_date)
             { 
                 $qry->whereDate('t1.archive_date','<=',Carbon::parse($end_date));   
             }
             
            if($report_order && $report_order=="description")
            {
        $qry->orderBy('t2.description', 'desc');
            }
            if($report_order && $report_order=="archive_date")
            {
        $qry->orderBy('t1.archive_date', 'desc');
            }
            $results=$qry->get();
             $results=convertStdClassObjToArray($results);
             if((count($results))>0)
             {
            
                 $res = array(
                     'success' => true,
                     'message' => "Report Generated",
                     'results' => $pdf_url
             );
             }else{
                 $res = array(
                     'success' => false,
                     'message' => "Requested Report Data Not Available",
                     'results' => 0 
                 );  
             }          
            break;
        case 9://asset damged
            $qry=DB::table('stores_asset_loss_damage_details as t1')
            ->join('ar_asset_inventory as t2','t2.id','t1.asset_id')
            ->where('lost_damaged_id',5)
            ->where('module_id',637)
            
             ->selectRaw("t2.description,t2.serial_no,t2.grz_no,DATE_FORMAT(t1.loss_damage_date,'%d-%b-%Y') as loss_date");
             if($start_date)
             {
                 $qry->whereDate('t1.loss_damage_date','>=',Carbon::parse($start_date));    
             }
             if($end_date)
             { 
                 $qry->whereDate('t1.loss_damage_date','<=',Carbon::parse($end_date));   
             }
             
             if($report_order && $report_order=="description")
             {
         $qry->orderBy('t2.description', 'desc');
             }
             if($report_order && $report_order=="loss_damage_date")
             {
         $qry->orderBy('t1.loss_damage_date', 'desc');
             }
             $results=$qry->get();
             $results=convertStdClassObjToArray($results);
             if((count($results))>0)
             {
            
                 $res = array(
                     'success' => true,
                     'message' => "Report Generated",
                     'results' => $pdf_url
             );
             }else{
                 $res = array(
                     'success' => false,
                     'message' => "Requested Report Data Not Available",
                     'results' => 0 
                 );  
             }          
            break;
        case 8://asset loss
            $qry=DB::table('stores_asset_loss_damage_details as t1')
            ->join('ar_asset_inventory as t2','t2.id','t1.asset_id')
            ->where('lost_damaged_id',4)
            ->where('module_id',637)
           
             ->selectRaw("t2.description,t2.serial_no,DATE_FORMAT(t1.loss_damage_date,'%d-%b-%Y') as loss_date");
             
             if($start_date)
             {
                 $qry->whereDate('t2.loss_damage_date','>=',Carbon::parse($start_date));    
             }
             if($end_date)
             { 
                 $qry->whereDate('t1.loss_damage_date','<=',Carbon::parse($end_date));   
             }
            if($report_order && $report_order=="description")
            {
        $qry->orderBy('t2.description', 'desc');
            }
            if($report_order && $report_order=="loss_damage_date")
            {
        $qry->orderBy('t1.loss_damage_date', 'desc');
            }
            $results=$qry->get();
             $results=convertStdClassObjToArray($results);
             if((count($results))>0)
             {
            
                 $res = array(
                     'success' => true,
                     'message' => "Report Generated",
                     'results' => $pdf_url
             );
             }else{
                 $res = array(
                     'success' => false,
                     'message' => "Requested Report Data Not Available",
                     'results' => 0 
                 );  
             }          

            break;
        case 7://asset write off

            $qry=DB::table('stores_asset_write_off_details as t1')
            ->join('ar_asset_inventory as t2','t2.id','t1.asset_id')
            ->where('module_id',637)
           
             ->selectRaw("t2.description,t2.serial_no,
             DATE_FORMAT(t1.date_written_off,'%d-%b-%Y') as date_written_off,t1.write_off_reason");
             if($start_date)
             {
                 $qry->whereDate('t1.date_written_off','>=',Carbon::parse($start_date));    
             }
             if($end_date)
             { 
                 $qry->whereDate('t1.date_written_off','<=',Carbon::parse($end_date));   
             }
             if($report_order && $report_order=="description")
             {
         $qry->orderBy('t2.description', 'desc');
             }
             if($report_order && $report_order=="date_written_off")
             {
         $qry->orderBy('t1.date_written_off', 'desc');
             }
             $results=$qry->get();
             $results=convertStdClassObjToArray($results);
             if((count($results))>0)
             {
            
                 $res = array(
                     'success' => true,
                     'message' => "Report Generated",
                     'results' => $pdf_url
             );
             }else{
                 $res = array(
                     'success' => false,
                     'message' => "Requested Report Data Not Available",
                     'results' => 0 
                 );  
             }          

            break;
        case 6://asset repair
            //o is schedlued,1 dispatched,2 completed
            $qry=DB::table('stores_asset_repairs as t1')
                ->join('ar_asset_inventory as t2','t2.id','t1.asset_id')
               ->where('repair_status','<',2)//scheduled
               ->where('module_id',637)
                //->where('maintainance_status',1)//dispatched
                ->selectRaw("t2.description,t2.serial_no,t2.grz_no,
                DATE_FORMAT(t1.scheduled_repair_date,'%d-%b-%Y') as scheduled_repair_date,repair_status");
              

                if($start_date)
                {
                    $qry->whereDate('t1.scheduled_repair_date','>=',Carbon::parse($start_date));    
                }
                if($end_date)
                { 
                    $qry->whereDate('t1.scheduled_repair_date','<=',Carbon::parse($end_date));   
                }
                if($report_order && $report_order=="description")
                {
            $qry->orderBy('t2.description', 'desc');
                }
                if($report_order && $report_order=="scheduled_repair_date")
                {
            $qry->orderBy('t1.scheduled_repair_date', 'desc');
                }
               $results=$qry->get();
                $results=convertStdClassObjToArray($results);
                if((count($results))>0)
                {
               
                    $res = array(
                        'success' => true,
                        'message' => "Report Generated",
                        'results' => $pdf_url
                );
                }else{
                    $res = array(
                        'success' => false,
                        'message' => "Requested Report Data Not Available",
                        'results' => 0 
                    );  
                }          
        case 5:
           
                $qry=DB::table('stores_asset_maintainance as t1')
                ->join('ar_asset_inventory as t2','t2.id','t1.asset_id')
                ->selectRaw('asset_id,t1.id as main_record_id,t2.description,t2.serial_no,t1.maintainance_due_date,
                date_maintainance_completed,maintainance_status')
                ->where('module_id',637)
                ->orderBy('t1.created_at','ASC');
                if($report_order && $report_order=="description")
                {
                $qry->orderBy('t2.description', 'desc');
                }
                if($report_order && $report_order=="maintainance_due_date")
                {
                $qry->orderBy('t1.maintainance_due_date', 'desc');
                }
                if($start_date)
                {
                 
                    $qry->whereDate('t1.maintainance_due_date','>=',Carbon::parse($start_date));
                    
                }
                if($end_date)
                {
                   
                    $qry->whereDate('t1.maintainance_due_date','<=',Carbon::parse($end_date));   
                }
                $assets_ids=$qry->get()->toArray();
                //sort to pick only lastes entry of asset
                $ids_of_assets=[];
                $maintainace_records=[];
                $maintainance_records_ids=[];
                foreach($assets_ids as $asset_data)
                {
                    if(!in_array($asset_data->asset_id,$ids_of_assets))
                    {
                    $ids_of_assets[]=$asset_data->asset_id;
                    if($asset_data->maintainance_status==0)
                    {
                        $asset_data->status="Scheduled";
                    }
                    if($asset_data->maintainance_status==1)
                    {
                        $asset_data->status="Under Maintainance";
                    }
                    if($asset_data->maintainance_status==2)
                    {
                        $asset_data->status="Maintainance Completed";
                    }
                    $maintainance_records_ids[]=$asset_data->main_record_id;
                    $asset_data=convertStdClassObjToArray($asset_data);
                    unset($asset_data['main_record_id']);
                    unset($asset_data['asset_id']);
                    unset($asset_data['maintainance_status']);
                    $maintainace_records[]=$asset_data;
                   
                    }
                }
                if((count( $maintainace_records))>0)
                {
               
                    $res = array(
                        'success' => true,
                        'message' => "Report Generated",
                        'results' => $pdf_url
                );
                }else{
                    $res = array(
                        'success' => false,
                        'message' => "Requested Report Data Not Available",
                        'results' => 0 
                    );  
                }          
               
            break;
        case 4://depreciation of assets report
            $qry= DB::table('stores_depreciation as t1')
            ->join('ar_asset_inventory as t2','t2.id','=','t1.asset_id')
            ->join('stores_depreciation_methods as t3','t1.depreciation_method_id','=','t3.id')
            ->where('module_id',637)
            ->selectRaw("t2.description,t2.serial_no,t2.grz_no,t1.depreciable_cost,t1.salvage_value,
            t1.asset_life,t1.depreciation_method_id,t1.depreciation_rate,
            DATE_FORMAT(t2.purchase_date ,'%d-%b-%Y') as purchase_date,t3.name as dep_method");
            if($start_date)
            {
             
                $qry->whereDate('t2.purchase_date','>=',Carbon::parse($start_date));
                
            }
            if($end_date)
            {
               
                $qry->whereDate('t2.purchase_date','<=',Carbon::parse($end_date));   
            }
            if($report_order && $report_order=="description")
                   {
               $qry->orderBy('t2.description', 'desc');
                   }
                   if($report_order && $report_order=="salvagevalue")
                   {
               $qry->orderBy('t1.salvage_value', 'desc');
                   }
           $assets=$qry->get();
           
           
          
             $asset_inventory=convertStdClassObjToArray($assets);
            if((count($asset_inventory))>0)
            {
               // $pdf_url.="&fields_to_use=".implode(',',$fields_to_use);
                $res = array(
                    'success' => true,
                    'message' => "Report Generated",
                    'results' => $pdf_url
            );
            }else{
                $res = array(
                    'success' => false,
                    'message' => "Requested Report Data Not Available",
                    'results' => 0 
                );  
            }

            

            break;
        case 3://asset aggregation per staff
            
                 
            $qry = DB::table('users as t1') 
            ->selectRaw("t1.id,
            CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.middlename),decrypt(t1.last_name)) as name");
            if($report_order && $report_order=="firstname")
               {
           $qry->orderByRaw('decrypt(t1.first_name)', 'desc');
               }
               if($report_order && $report_order=="lastname")
               {
           $qry->orderByRaw('decrypt(t1.last_name)', 'desc');
               }
               if($report_order && $report_order=="middlename")
               {
           $qry->orderByRaw('decrypt(t1.middlename)', 'desc');
               }
            $users_ids=convertStdClassObjToArray($qry->get());
          
            $qry=DB::table('stores_asset_checkout_details as t1')
                ->join('ar_asset_inventory as t2','t2.id','=','t1.asset_id')
                ->where('checkout_status',1)
                ->where('module_id',637)
                ->where('is_group_checkout',1)->selectRaw("t1.id as checkout_id,user_id,t2.description,
                DATE_FORMAT(t1.checkout_date,'%d-%b-%Y') as checkout_date,
                t2.serial_no,t2.grz_no");
               
                if($start_date)
                {
                 
                    $qry->whereDate('t1.checkout_date','>=',Carbon::parse($start_date));
                    
                }
                if($end_date)
                {
                   
                    $qry->whereDate('t1.checkout_date','<=',Carbon::parse($end_date));   
                }
           $group_checkouts=$qry->get();
            $group_checkouts=convertStdClassObjToArray($group_checkouts);
            $group_transaction_breakdown=[];
            $all_group_checkout_user_ids=[];
            foreach($group_checkouts as $group_checkout)
            {
                $user_ids=$this->returnArrayFromStringArray($group_checkout['user_id']);
                foreach($user_ids as $user_id)
                {
                    if(!in_array($user_id,$all_group_checkout_user_ids))
                    {
                        $all_group_checkout_user_ids[]=$user_id;
                    }
                    $user_details=$this->_search_array_by_value($users_ids,'id',$user_id);

                    $group_transaction_breakdown[]=array(
                        "checkout_id"=>$group_checkout['checkout_id'],
                        "user_id"   =>$user_details[0]['id'],
                        "name" =>$user_details[0]['name'],
                        "asset_description"=>$group_checkout["description"],
                        "serial_no"=>$group_checkout["serial_no"],
                        "grz_no"=>$group_checkout['grz_no'],
                        "date_assigned"=>$group_checkout['checkout_date']
                    );
                   

                }
                
                
            }
            //dd($group_transaction_breakdown);

            $qry=DB::table('users as t1')
                ->join('stores_asset_checkout_details as t2','t1.id','=','t2.user_id')
                 ->where('checkout_status',1)
                ->selectRaw("t1.id as user_id,CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.middlename),decrypt(t1.last_name)) as name");
               
                if($start_date)
                {
                 
                    $qry->whereDate('t2.checkout_date','>=',Carbon::parse($start_date));
                    
                }
                if($end_date)
                {
                   
                    $qry->whereDate('t2.checkout_date','<=',Carbon::parse($end_date));   
                }
               
                if($report_order && $report_order=="firstname")
                {
            $qry->orderByRaw('decrypt(t1.first_name)', 'desc');
                }
                if($report_order && $report_order=="lastname")
                {
            $qry->orderByRaw('decrypt(t1.last_name)', 'desc');
                }
                if($report_order && $report_order=="middlename")
                {
            $qry->orderByRaw('decrypt(t1.middlename)', 'desc');
                }
            $checkouts_user_ids=$qry->get();
            $checkouts_user_ids=convertStdClassObjToArray($checkouts_user_ids);
            $qry=Db::table('stores_asset_checkout_details as t1')
               ->join('ar_asset_inventory as t2','t2.id','=','t1.asset_id')
               ->join('users as t3', 't3.id', '=', 't1.user_id')
               ->where('module_id',637)
                ->selectRaw("t1.id as checkout_id,t1.user_id,t2.description as asset_description,
                DATE_FORMAT(t1.checkout_date ,'%d-%b-%Y') as date_assigned,t2.serial_no,t2.grz_no,
                CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.middlename),decrypt(t3.last_name)) as name");
                if($start_date)
                {
                 
                    $qry->whereDate('t1.checkout_date','>=',Carbon::parse($start_date));
                    
                }
                if($end_date)
                {
                   
                    $qry->whereDate('t1.checkout_date','<=',Carbon::parse($end_date));   
                }
                if($report_order && $report_order=="firstname")
                {
            $qry->orderByRaw('decrypt(t3.first_name)', 'desc');
                }
                if($report_order && $report_order=="lastname")
                {
            $qry->orderByRaw('decrypt(t3.last_name)', 'desc');
                }
                if($report_order && $report_order=="middlename")
                {
            $qry->orderByRaw('decrypt(t3.middlename)', 'desc');
                }
            $checkouts=$qry->get();
            $checkouts_per_user=[];
            $checkouts=convertStdClassObjToArray($checkouts);
            foreach($all_group_checkout_user_ids as $user_id_from_group)
            {
                $results=$this->_search_array_by_value($checkouts_user_ids,'user_id',$user_id_from_group);
                if((count($results))==0)
                {
                    $checkouts_user_ids[]=array(
                        "user_id"=>$user_id_from_group
                    );
                }
            }//combine group user ids with normal user ids
           
           

            foreach($checkouts_user_ids as $user )
            {  
                $user_results=[];
                $results=$this->_search_array_by_value($checkouts,'user_id',$user['user_id']);
               if(count($results)>0)
               {
                   //$results=$results[0];
                   foreach($results as $user_r)
                   {
                       $user_results[]=$user_r;
                   }
               }
       
                $results2=$this->_search_array_by_value($group_transaction_breakdown,'user_id',$user['user_id']);
           
                if(count($results2)>0)
                {
                   // $results2=$results2[0];
                    foreach($results2 as $user_r)
                    {
                        $user_results[]=$user_r;
                    }
                }
               
                $checkouts_per_user[$user['user_id'].",".$user['name']]=$user_results;
            }
          
                if((count($checkouts_per_user))>0)
                {
               
                    $res = array(
                        'success' => true,
                        'message' => "Report Generated",
                        'results' => $pdf_url
                );
                }else{
                    $res = array(
                        'success' => false,
                        'message' => "Requested Report Data Not Available",
                        'results' => 0 
                    );  
                }            
            break;
        case 2://custom asset report
            $qry = DB::table('ar_asset_inventory as t1')->where('module_id',637);
            $fields_to_use=explode(',',$request->query('fields_to_use'));
           
          
            $column_match=[
                "description"=>"description",
                "category"=>"category_id",
                "FrequencyofMaintainance"=>"maintainance_frequency",
                "brand"=>"brand_id",
                "model"=>"model_id",
                "PurchaseFrom"=>"purchase_from",
                "PurchaseDate"=>"purchase_date",
                "cost"=>"cost",
                "SerialNo"=>"serial_no",
                "GrzNo"=>"grz_no",
                "site"=>"site_id",
                "location"=>"location_id",
                "department"=>"department_id",
                "status"=>"status_id"
            ];
          
           // return $column_match['']
            $inventory_columns=[];
            foreach ( $fields_to_use as $value) {
                //echo "$value <br>";
            $inventory_columns[]=$column_match[$value];
              }

              $qry = DB::table('ar_asset_inventory as t1')->where('module_id',637);
              $select_raw_statement=[];
              $table_headers=[];
              if(in_array("description",$inventory_columns))
              {
                  $select_raw_statement[]="t1.description";
                  $table_headers[]="Description";
              }
              if(in_array("category_id",$inventory_columns))
              {
                 
                  $qry->join('stores_categories as t2', 't1.category_id', '=', 't2.id');
                  $select_raw_statement[]="t2.name as category_name";
                  $table_headers[]="Category Name";
              }
              if(in_array("brand_id",$inventory_columns))
              {
                 
                  $qry->join('stores_brands as t3', 't1.brand_id', '=', 't3.id');
                  $select_raw_statement[]="t3.name as brand_name";
                  $table_headers[]="Brand Name";
              }
              if(in_array("model_id",$inventory_columns))
              {
                 
                  $qry->join('stores_models as t4', 't1.model_id', '=', 't4.id');
                  $select_raw_statement[]="t4.name as model_name";
                  $table_headers[]="Model Name";
              }
              if(in_array("purchase_date",$inventory_columns))
              {
                 
                  $select_raw_statement[]="t1.purchase_date";
                  $table_headers[]="Purchase Date";
              }
              if(in_array("purchase_from",$inventory_columns))
              {
                 
                  $select_raw_statement[]="t1.purchase_from";
                  $table_headers[]="Purchase From";
              }
              if(in_array("cost",$inventory_columns))
              {
                 
                  $select_raw_statement[]="t1.cost";
                  $table_headers[]="Acquisition cost";
              }
              if(in_array("serial_no",$inventory_columns))
              {
                 
                  $select_raw_statement[]="t1.serial_no";
                  $table_headers[]="Serial No";
              }
              if(in_array("grz_no",$inventory_columns))
              {
                 
                  $select_raw_statement[]="grz_no";
                  $table_headers[]="Grz No";

              }
              if(in_array("site_id",$inventory_columns))
              {
                 
                  $select_raw_statement[]="t5.name as site_name";
                  $qry->join('stores_sites as t5', 't1.site_id', '=', 't5.id');
                  $table_headers[]="Site name";
                  
                 
              }
              if(in_array("location_id",$inventory_columns))
              {
                  $qry->join('stores_locations as t6', 't1.location_id', '=', 't6.id');
                 
                  $select_raw_statement[]="t6.name as location_name";
                  $table_headers[]="Location Name";
              }
              if(in_array("department_id",$inventory_columns))
              {
                  $qry->join('stores_departments as t7', 't1.department_id', '=', 't7.id');
                 
                  $select_raw_statement[]="t7.name as department_name";
                  $table_headers[]="Department Name";
              }

              $select_raw_statement=implode(",",$select_raw_statement);
              $width=100/count($table_headers);
              $header_style= "{width:".$width."%; background-color: #04AA6D;;color: white;text-align: left}";
              $data_style="width:".$width."%;text-align: left;padding-left:7px;";
            $qry->selectRaw($select_raw_statement);
             $results=$qry->get();
             $asset_inventory=convertStdClassObjToArray($results);
             if((count($asset_inventory))>0)
            {
                $pdf_url.="&fields_to_use=".implode(',',$fields_to_use);
                $res = array(
                    'success' => true,
                    'message' => "Report Generated",
                    'results' => $pdf_url
            );
            }else{
                $res = array(
                    'success' => false,
                    'message' => "Requested Report Data Not Available",
                    'results' => 0 
                );  
            }




         

            break;
        case 1://assesory report all inventory items
         
            $qry = DB::table('ar_asset_inventory as t1')
            ->join('stores_categories as t2', 't1.category_id', '=', 't2.id')
            ->leftjoin('stores_brands as t3', 't1.brand_id', '=', 't3.id')
            ->leftjoin('stores_models as t4', 't1.model_id', '=', 't4.id')
            ->leftjoin('stores_sites as t5', 't1.site_id', '=', 't5.id')
            ->leftjoin('stores_locations as t6', 't1.location_id', '=', 't6.id')
            ->leftjoin('stores_departments as t7', 't1.department_id', '=', 't7.id')
            ->join('stores_statuses as t8', 't1.status_id', '=', 't8.id')
            ->where('module_id',637)
            // ->Join('ar_asset_maintainance as t9', 't1.id', '=', 't9.asset_id')
            // ->Join('ar_asset_repairs as t10', 't1.id', '=', 't10.asset_id')
            ->selectRaw("t1.*, t1.id as record_id,t1.id as asset_id, t2.name as category_name, t3.name as brand_name, t4.name as model_name,t5.name as site_name,
                 t6.name as location_name,t7.name as department_name, t8.name as record_status,t1.purchase_date,
                 CONCAT_WS('-',t1.description,t1.grz_no) as desc_grz_no");
                 $filter_columns=[
                    ["id"=>"description","text"=>"Description"],
                    ["id"=>"category_name","text"=>"Category"],
                    ["id"=>"brand_name","text"=>"Brand"],
                    ["id"=>"model_name","text"=>"Model"],
                    ["id"=>"purchase_from","text"=>"Purchase From"],
                    ["id"=>"purchase_date","text"=>"Purchase Date"],
                    ["id"=>"cost","text"=>"Cost"],
                    ["id"=>"serial_no","text"=>"Serial No"],
                    ["id"=>"grz_no","text"=>"Grz No"],
                    ["id"=>"site","text"=>"Site"],
                    ["id"=>"location","text"=>"Location"],
                    ["id"=>"department","text"=>"Department"],
                 ];
            //dates

            if($start_date)
            {
             
                $qry->whereDate('t1.purchase_date','>=',Carbon::parse($start_date));
                
            }
            if($end_date)
            {
               
                $qry->whereDate('t1.purchase_date','<=',Carbon::parse($end_date));   
            }

            //ordering
                
            if($report_order && $report_order=="description")
            {
                $qry->orderBy('description', 'desc');
            }
            if($report_order && $report_order=="category_name")
            {
                $qry->orderBy('t2.name', 'desc');
            }
            if($report_order && $report_order=="brand_name")
            {
                $qry->orderBy('t3.name', 'desc');
            }
            if($report_order && $report_order=="model_name")
            {
                $qry->orderBy('t4.name', 'desc');
            }
            if($report_order && $report_order=="purchase_from")
            {
                $qry->orderBy('t1.purchase_from', 'desc');
            }
            if($report_order && $report_order=="purchase_date")
            {
                $qry->orderBy('t1.purchase_date', 'desc');
            }
            if($report_order && $report_order=="cost")
            {
                $qry->orderBy('t1.cost', 'desc');
            }
            if($report_order && $report_order=="serial_no")
            {
                $qry->orderBy('t1.serial_no', 'desc');
            }
            if($report_order && $report_order=="grz_no")
            {
                $qry->orderBy('t1.grz_no', 'desc');
            }
            if($report_order && $report_order=="site")
            {
                $qry->orderBy('t5.name', 'desc');
            }
            if($report_order && $report_order=="location")
            {
                $qry->orderBy('t6.name', 'desc');
            }
            if($report_order && $report_order=="department")
            {
             
                $qry->orderBy('t7.name', 'desc');
            }
            
            $results = $qry->get();
            $asset_inventory=convertStdClassObjToArray($results);
          

            if((count($asset_inventory))>0)
            {
                $res = array(
                    'success' => true,
                    'message' => "Report Generated",
                    'results' => $pdf_url
            );
            }else{
                $res = array(
                    'success' => false,
                    'message' => "Requested Report Data Not Available",
                    'results' => 0 
                );  
            }
            
            break;
    }
    return response()->json($res);
}


public function generateStoresAssetsReport(Request $request)
    {
        $res=array();
        try{
        $url=url('/');
        $image_url=$url.'/backend/public/moge_logo.png';
        $report_type= $request->query('report_type');
        $pdf_url = $url."/assetregister/generateStoresAssetsReport?report_type=".$report_type;
        $report_order=$request->query('report_order');
        $start_date=$request->query('start_date');
        $end_date=$request->query('end_date');
       
        if($report_order)
        {
            $pdf_url.="&report_order=".$report_order;
        }
    
        switch($report_type)
        {
            case 12:
                return Excel::download(new  DisposalStoresReport (),'. DisposalReport.xlsx');
                break;
            case 11:
                return Excel::download(new ConsolidatedStoresAssetsData(),'.ConsolidatedAssetsData.xlsx');
                break;
            case 10:
                $qry=DB::table('ar_asset_archive as t1')
                ->join('ar_asset_inventory as t2','t2.id','t1.asset_id')
                ->where('module_id',637)
                 ->selectRaw("t2.description,t2.serial_no,t2.grz_no,t1.archive_date,
                 DATE_FORMAT(t1.archive_date,'%d-%b-%Y') as archive_date");
                 if($start_date)
                 {
                     $qry->whereDate('t1.archive_date','>=',Carbon::parse($start_date));    
                 }
                 if($end_date)
                 { 
                     $qry->whereDate('t1.archive_date','<=',Carbon::parse($end_date));   
                 }
                 
                if($report_order && $report_order=="description")
                {
            $qry->orderBy('t2.description', 'desc');
                }
                if($report_order && $report_order=="archive_date")
                {
            $qry->orderBy('t1.archive_date', 'desc');
                }
                $results=$qry->get();
                 $results=convertStdClassObjToArray($results);
                 $report_title="Asset Archive Report";
                    $table_headers=["Description","Serial No","Grz No","Archive  Date"];
                         $width=100/count($table_headers);
                         $header_style= "width:".$width."%; background-color: #04AA6D;color: white;text-align: left";
                         $data_style="width:".$width."%;text-align: left;padding-left:7px;";
                        $asset_inventory=$results;
                     
                        $exclusion_fields=[];
                        
                        $report_date= date('d-M-Y H:i:s');
                        $view=view('customassetreport',compact('asset_inventory','report_date','image_url',
                        'table_headers','header_style','data_style','exclusion_fields','report_title'))->render();
                        //return $view;
                       $this->generatePDFFIle($view);
                break;
            case 9://asset damaged
                $qry=DB::table('stores_asset_loss_damage_details as t1')
                ->join('ar_asset_inventory as t2','t2.id','t1.asset_id')
                ->where('lost_damaged_id',5)
                ->where('module_id',637)
                 //->where('maintainance_status',1)//dispatched
                 ->selectRaw("t2.description,t2.serial_no,t2.grz_no,DATE_FORMAT(t1.loss_damage_date,'%d-%b-%Y') as loss_date");
                 if($start_date)
                 {
                     $qry->whereDate('t1.loss_damage_date','>=',Carbon::parse($start_date));    
                 }
                 if($end_date)
                 { 
                     $qry->whereDate('t1.loss_damage_date','<=',Carbon::parse($end_date));   
                 }
                 
                 if($report_order && $report_order=="description")
                 {
             $qry->orderBy('t2.description', 'desc');
                 }
                 if($report_order && $report_order=="loss_damage_date")
                 {
             $qry->orderBy('t1.loss_damage_date', 'desc');
                 }
                 $results=$qry->get();
                 $results=convertStdClassObjToArray($results);
                 $report_title="Asset Damage Report";
                    $table_headers=["Description","Serial No","Grz No","Damage  Date"];
                         $width=100/count($table_headers);
                         $header_style= "width:".$width."%; background-color: #04AA6D;color: white;text-align: left";
                         $data_style="width:".$width."%;text-align: left;padding-left:7px;";
                        $asset_inventory=$results;
                     
                        $exclusion_fields=[];
                        
                        $report_date= date('d-M-Y H:i:s');
                        $view=view('customassetreport',compact('asset_inventory','report_date','image_url',
                        'table_headers','header_style','data_style','exclusion_fields','report_title'))->render();
                        //return $view;
                       $this->generatePDFFIle($view);
                break;
            case 8://asset loss
                $qry=DB::table('stores_asset_loss_damage_details as t1')
                ->join('ar_asset_inventory as t2','t2.id','t1.asset_id')
                ->where('lost_damaged_id',4)
                ->where('module_id',637)
               
                 ->selectRaw("t2.description,t2.serial_no,t2.grz_no,DATE_FORMAT(t1.loss_damage_date,'%d-%b-%Y') as loss_date");
                 
                 if($start_date)
                 {
                     $qry->whereDate('t2.loss_damage_date','>=',Carbon::parse($start_date));    
                 }
                 if($end_date)
                 { 
                     $qry->whereDate('t1.loss_damage_date','<=',Carbon::parse($end_date));   
                 }
                if($report_order && $report_order=="description")
                {
            $qry->orderBy('t2.description', 'desc');
                }
                if($report_order && $report_order=="loss_damage_date")
                {
            $qry->orderBy('t1.loss_damage_date', 'desc');
                }
                $results=$qry->get();
                 $results=convertStdClassObjToArray($results);
                 $report_title="Asset Loss Report";
                    $table_headers=["Description","Serial No","Grz No","Date lost"];
                         $width=100/count($table_headers);
                         $header_style= "width:".$width."%; background-color: #04AA6D;color: white;text-align: left";
                         $data_style="width:".$width."%;text-align: left;padding-left:7px;";
                        $asset_inventory=$results;
                       
                     
                        $exclusion_fields=[];
                        
                        $report_date= date('d-M-Y H:i:s');
                        $view=view('customassetreport',compact('asset_inventory','report_date','image_url',
                        'table_headers','header_style','data_style','exclusion_fields','report_title'))->render();
                       // return $view;
                       $this->generatePDFFIle($view);

                break;
            case 7://asset write off

                $qry=DB::table('stores_asset_write_off_details as t1')
                ->join('ar_asset_inventory as t2','t2.id','t1.asset_id')
                ->where('module_id',637)
                 ->selectRaw("t2.description,t2.serial_no,
                 DATE_FORMAT(t1.date_written_off,'%d-%b-%Y') as date_written_off,t1.write_off_reason");
                 if($start_date)
                 {
                     $qry->whereDate('t1.date_written_off','>=',Carbon::parse($start_date));    
                 }
                 if($end_date)
                 { 
                     $qry->whereDate('t1.date_written_off','<=',Carbon::parse($end_date));   
                 }
                 if($report_order && $report_order=="description")
                 {
             $qry->orderBy('t2.description', 'desc');
                 }
                 if($report_order && $report_order=="date_written_off")
                 {
             $qry->orderBy('t1.date_written_off', 'desc');
                 }
                 $results=$qry->get();
                
                 $results=convertStdClassObjToArray($results);
                 $report_title="Asset Write-off Report";
                    $table_headers=["Description","Serial No","Grz No",
                        "Date Written-Off","Write-Off Reason"];
                         $width=100/count($table_headers);
                         $header_style= "width:".$width."%; background-color: #04AA6D;color: white;text-align: left";
                         $data_style="width:".$width."%;text-align: left;padding-left:7px;";
                        $asset_inventory=$results;
                        //dd($asset_inventory);
                        $exclusion_fields=[];
                        
                        $report_date= date('d-M-Y H:i:s');
                        $view=view('customassetreport',compact('asset_inventory','report_date','image_url',
                        'table_headers','header_style','exclusion_fields','report_title'))->render();
                       // return $view;
                       $this->generatePDFFIle($view);

                break;
            case 6://asset reapir

                


                    
                $qry=DB::table('stores_asset_repairs as t1')
                ->join('ar_asset_inventory as t2','t2.id','t1.asset_id')
                ->where('module_id',637)
                ->selectRaw('asset_id,t1.id as repair_record_id,t2.description,t2.serial_no,t1.scheduled_repair_date,
                date_repair_completed,repair_status')
                ->orderBy('t1.created_at','DESC');
                if($report_order && $report_order=="description")
                {
                $qry->orderBy('t2.description', 'desc');
                }
                if($report_order && $report_order=="scheduled_repair_date")
                {
                $qry->orderBy('t1.scheduled_repair_date', 'desc');
                }
                if($start_date)
                {
                 
                    $qry->whereDate('t1.scheduled_repair_date','>=',Carbon::parse($start_date));
                    
                }
                if($end_date)
                {
                   
                    $qry->whereDate('t1.scheduled_repair_date','<=',Carbon::parse($end_date));   
                }
                $assets_ids=$qry->get()->toArray();
                //sort to pick only lastes entry of asset
                $ids_of_assets=[];
                $repair_records=[];
                $repair_records_ids=[];
                foreach($assets_ids as $asset_data)
                {
                    if(!in_array($asset_data->asset_id,$ids_of_assets))
                    {
                    $ids_of_assets[]=$asset_data->asset_id;
                    if($asset_data->repair_status==0)
                    {
                        $asset_data->status="Scheduled";
                    }
                    if($asset_data->repair_status==1)
                    {
                        $asset_data->status="In Progress";
                    }
                    if($asset_data->repair_status==2)
                    {
                        $asset_data->status="Completed";
                    }
                    $repair_records_ids[]=$asset_data->repair_record_id;
                    $asset_data=convertStdClassObjToArray($asset_data);
                    unset($asset_data['repair_record_id']);
                    unset($asset_data['asset_id']);
                    unset($asset_data['repair_status']);
                    $repair_records[]=$asset_data;
                   
                    }
                }
               
                    //end new query
                    $report_title="Asset Repair Report";
                    $table_headers=["Description","Serial No","Grz No",
                        "Due Date","Status"];
                         $width=100/count($table_headers);
                         $header_style= "width:".$width."%; background-color: #04AA6D;color: white;text-align: left";
                         $data_style="width:".$width."%;text-align: left;padding-left:7px;";
                        //$asset_inventory=$repair_report;
                        $asset_inventory=$repair_records;
                        //dd($asset_inventory);
                        $exclusion_fields=[];
                        
                        $report_date= date('d-M-Y H:i:s');
                        $view=view('customassetreport',compact('asset_inventory','report_date','image_url',
                        'table_headers','header_style','data_style','exclusion_fields','report_title'))->render();
                        
                       $this->generatePDFFIle($view);
            case 5://asset maintainance

                $qry=DB::table('stores_asset_maintainance as t1')
                ->join('ar_asset_inventory as t2','t2.id','t1.asset_id')
                ->where('module_id',637)
                ->selectRaw('asset_id,t1.id as main_record_id,t2.description,t2.serial_no,t1.maintainance_due_date,
                date_maintainance_completed,maintainance_status')
                ->orderBy('t1.created_at','DESC');
                if($report_order && $report_order=="description")
                {
                $qry->orderBy('t2.description', 'desc');
                }
                if($report_order && $report_order=="maintainance_due_date")
                {
                $qry->orderBy('t1.maintainance_due_date', 'desc');
                }
                if($start_date)
                {
                 
                    $qry->whereDate('t1.maintainance_due_date','>=',Carbon::parse($start_date));
                    
                }
                if($end_date)
                {
                   
                    $qry->whereDate('t1.maintainance_due_date','<=',Carbon::parse($end_date));   
                }
                $assets_ids=$qry->get()->toArray();
               
                //sort to pick only lastes entry of asset
                $ids_of_assets=[];
                $maintainace_records=[];
                $maintainance_records_ids=[];
                foreach($assets_ids as $asset_data)
                {
                    if(!in_array($asset_data->asset_id,$ids_of_assets))
                    {
                       
                    $ids_of_assets[]=$asset_data->asset_id;
                    if($asset_data->maintainance_status==0)
                    {
                        $asset_data->status="Scheduled";
                    }
                    if($asset_data->maintainance_status==1)
                    {
                        $asset_data->status="In Progress";
                    }
                    if($asset_data->maintainance_status==2)
                    {
                        $asset_data->status="Completed";
                    }
                    if($asset_data->maintainance_status==4)
                    {
                        $asset_data->status="Cancelled";
                    }
                    if($asset_data->maintainance_status==3)
                    {
                        $asset_data->status="On Hold";
                    }
                    $maintainance_records_ids[]=$asset_data->main_record_id;
                    $asset_data=convertStdClassObjToArray($asset_data);
                    unset($asset_data['main_record_id']);
                    unset($asset_data['asset_id']);
                    unset($asset_data['maintainance_status']);
                    $maintainace_records[]=$asset_data;
                   
                    }
                }
                
                    
                   
                $report_title="Asset Maintainance Report";
                $table_headers=["Description","Serial No",
                    "Due Date","Completed" ,"Status"];
                     $width=100/count($table_headers);
                     $header_style= "width:".$width."%; background-color: #04AA6D;color: white;text-align: left";
                     $data_style="width:".$width."%;text-align: left;padding-left:7px;";
                   // $asset_inventory=$maintainance_report;
                    $asset_inventory= $maintainace_records;
                
                    $exclusion_fields=[];
                    
                    $report_date= Carbon::now();
                    $report_date= date('d-m-y H:i:s');
                    $view=view('customassetreport',compact('asset_inventory','report_date','image_url',
                    'table_headers','header_style','data_style','exclusion_fields','report_title'))->render();
                    //return $view;
                   $this->generatePDFFIle($view);
                break;
            case 4://depreciation of assets report
                $qry= DB::table('stores_depreciation as t1')
                 ->join('ar_asset_inventory as t2','t2.id','=','t1.asset_id')
                 ->join('stores_depreciation_methods as t3','t1.depreciation_method_id','=','t3.id')
                 ->where('module_id',637)
                //  ->selectRaw("t2.description,t2.serial_no,t2.grz_no,t1.depreciable_cost,t1.salvage_value,
                //  t1.asset_life,t1.depreciation_method_id,t1.depreciation_rate,
                //  DATE_FORMAT(t2.purchase_date ,'%d-%b-%Y') as purchase_date,t3.name as dep_method");
                 ->selectRaw("t2.description,t2.serial_no,t2.grz_no,t1.depreciable_cost,t1.salvage_value,
                 t1.asset_life,t1.depreciation_method_id,t1.depreciation_rate,
                 t2.purchase_date,t3.name as dep_method");
                 if($start_date)
                 {
                  
                     $qry->whereDate('t2.purchase_date','>=',Carbon::parse($start_date));
                     
                 }
                 if($end_date)
                 {
                    
                     $qry->whereDate('t2.purchase_date','<=',Carbon::parse($end_date));   
                 }
                 if($report_order && $report_order=="description")
                        {
                    $qry->orderBy('t2.description', 'desc');
                        }
                        if($report_order && $report_order=="salvagevalue")
                        {
                    $qry->orderBy('t1.salvage_value', 'desc');
                        }
                $assets=$qry->get();
                
                
                 $assets=convertStdClassObjToArray($assets);
                        
                 $depreciation_data=[];
                 foreach($assets as $asset)
                 {
                   
                   
                     $depreciation_details=(object)array(
                         "asset_life"=>$asset['asset_life'],
                         "depreciable_cost"=>$asset['depreciable_cost'],
                         "salvage_value"=>$asset['salvage_value'],
                         "date_acquired"=>$asset['purchase_date'],
                         "depreciation_rate"=>$asset['depreciation_rate']
 
                     );
                    
                     switch($asset['depreciation_method_id']){
                           case 4:
                            $current_val=calculatePercentageDepreciation($depreciation_details,date('Y-m-d'),1.5);
                            $depreciation_data[]=array(
                                "description"=>$asset['description'],
                                "serial_no"=>$asset['serial_no'],
                                "depreciable_cost"=>$asset['depreciable_cost'],
                                "salvage_value"=>$asset['salvage_value'],
                                //"depreciation_rate"=>$asset['depreciation_rate'],
                                "purchase_date"=>$asset['purchase_date'],
                                "current_value"=>$current_val,
                                "method"=>$asset['dep_method']
                            );
                             break;
                         case 3:
                           
                            $current_val=calculatePercentageDepreciation($depreciation_details,date('Y-m-d'),2);
                            $depreciation_data[]=array(
                                "description"=>$asset['description'],
                                "serial_no"=>$asset['serial_no'],
                                "depreciable_cost"=>$asset['depreciable_cost'],
                                "salvage_value"=>$asset['salvage_value'],
                                //"depreciation_rate"=>$asset['depreciation_rate'],
                                "purchase_date"=>$asset['purchase_date'],
                                "current_value"=>$current_val,
                                "method"=>$asset['dep_method']
                            );
                             break;
                         case 2:
                             $rate=$asset['depreciation_rate']/100;
                             $current_val=calculatePercentageDepreciation($depreciation_details,date('Y-m-d'),$rate);
                             $depreciation_data[]=array(
                                "description"=>$asset['description'],
                                "serial_no"=>$asset['serial_no'],
                                "depreciable_cost"=>$asset['depreciable_cost'],
                                "salvage_value"=>$asset['salvage_value'],
                                //"depreciation_rate"=>$asset['depreciation_rate'],
                                "purchase_date"=>$asset['purchase_date'],
                                "current_value"=>$current_val,
                                "method"=>$asset['dep_method']
                            );
                             break;
                         case 1:
                             $current_val=calculateStraightLineDepreciation($depreciation_details,date('Y-m-d'));
                             $depreciation_data[]=array(
                                 "description"=>$asset['description'],
                                 "serial_no"=>$asset['serial_no'],
                                 "depreciable_cost"=>$asset['depreciable_cost'],
                                 "salvage_value"=>$asset['salvage_value'],
                                 //"depreciation_rate"=>$asset['depreciation_rate'],
                                 "purchase_date"=>$asset['purchase_date'],
                                 "current_value"=>$current_val,
                                 "method"=>$asset['dep_method']
                             );
                             break;
                         default:
                             $current_val=calculateStraightLineDepreciation($depreciation_details,false,date('Y-m-d'));
                             $depreciation_data[]=array(
                                "description"=>$asset['description'],
                                "serial_no"=>$asset['serial_no'],
                                "depreciable_cost"=>$asset['depreciable_cost'],
                                "salvage_value"=>$asset['salvage_value'],
                                //"depreciation_rate"=>$asset['depreciation_rate'],
                                "purchase_date"=>$asset['purchase_date'],
                                "current_value"=>$current_val,
                                "method"=>$asset['dep_method']
                            );
                             break;
                     }
                 }
                   $report_title="Asset Depreciation Report";
                    $table_headers=["Description","Serial No","Depreciable Cost","Salvage Value",
                    "Purchase Date","Current Value","Method"];
                     $width=100/count($table_headers);
                     $header_style= "width:".$width."%; background-color: #04AA6D;color: white;text-align: left";
                     $data_style="width:".$width."%;text-align: left;padding-left:7px;";
                    $asset_inventory=$depreciation_data;
                    //dd($asset_inventory);
                    $exclusion_fields=["checkout_id","user_id"];
                    
                  
                    //$report_date= date('d-m-y H:i:s');
                    $report_date= date('d-M-Y H:i:s');

                    $view=view('customassetreport',compact('asset_inventory','report_date','image_url',
                    'table_headers','header_style','data_style','exclusion_fields','report_title'))->render();
                   $this->generatePDFFIle($view);
 
                 break;
             case 3://asset aggregation per staff
                 
                     $qry = DB::table('users as t1') 
                     ->selectRaw("t1.id,
                     CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.middlename),decrypt(t1.last_name)) as name");
                     $users=$qry->get();
                     if($report_order && $report_order=="firstname")
                        {
                    $qry->orderByRaw('decrypt(t1.first_name)', 'desc');
                        }
                        if($report_order && $report_order=="lastname")
                        {
                    $qry->orderByRaw('decrypt(t1.last_name)', 'desc');
                        }
                        if($report_order && $report_order=="middlename")
                        {
                    $qry->orderByRaw('decrypt(t1.middlename)', 'desc');
                        }
                     //$users_ids=convertStdClassObjToArray($qry->get());
                     $users_ids=convertStdClassObjToArray($users);
                   
                     $qry=DB::table('stores_asset_checkout_details as t1')
                         ->join('ar_asset_inventory as t2','t2.id','=','t1.asset_id')
                         ->where('module_id',637)
                         ->where('checkout_status',1)
                         ->where('is_group_checkout',1)->selectRaw("t1.id as checkout_id,user_id,t2.description,
                         DATE_FORMAT(t1.checkout_date,'%d-%b-%Y') as checkout_date,
                         t2.serial_no,t2.grz_no");
                        
                         if($start_date)
                         {
                          
                             $qry->whereDate('t1.checkout_date','>=',Carbon::parse($start_date));
                             
                         }
                         if($end_date)
                         {
                            
                             $qry->whereDate('t1.checkout_date','<=',Carbon::parse($end_date));   
                         }
                    $group_checkouts=$qry->get();
                     $group_checkouts=convertStdClassObjToArray($group_checkouts);
                   
                     $group_transaction_breakdown=[];
                     $all_group_checkout_user_ids=[];
                     foreach($group_checkouts as $group_checkout)
                     {
                         $user_ids=$this->returnArrayFromStringArray($group_checkout['user_id']);
                         foreach($user_ids as $user_id)
                         {
                             if(!in_array($user_id,$all_group_checkout_user_ids))
                             {
                                 $all_group_checkout_user_ids[]=$user_id;
                             }
                             $user_details=$this->_search_array_by_value($users_ids,'id',$user_id);
 
                             $group_transaction_breakdown[]=array(
                                 "checkout_id"=>$group_checkout['checkout_id'],
                                 "user_id"   =>$user_details[0]['id'],
                                 "name" =>$user_details[0]['name'],
                                 "asset_description"=>$group_checkout["description"],
                                 "serial_no"=>$group_checkout["serial_no"],
                                 "grz_no"=>$group_checkout['grz_no'],
                                 "date_assigned"=>$group_checkout['checkout_date']
                             );
                            
 
                         }
                         
                         
                     }
                  
                     //dd($group_transaction_breakdown);
 
                     $qry=DB::table('users as t1')
                         ->join('stores_asset_checkout_details as t2','t1.id','=','t2.user_id')
                          ->where('checkout_status',1)
                         ->selectRaw("t1.id as user_id,CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.middlename),decrypt(t1.last_name)) as name");
                        
                         if($start_date)
                         {
                          
                             $qry->whereDate('t2.checkout_date','>=',Carbon::parse($start_date));
                             
                         }
                         if($end_date)
                         {
                            
                             $qry->whereDate('t2.checkout_date','<=',Carbon::parse($end_date));   
                         }
                        
                         if($report_order && $report_order=="firstname")
                         {
                     $qry->orderByRaw('decrypt(t1.first_name)', 'desc');
                         }
                         if($report_order && $report_order=="lastname")
                         {
                     $qry->orderByRaw('decrypt(t1.last_name)', 'desc');
                         }
                         if($report_order && $report_order=="middlename")
                         {
                     $qry->orderByRaw('decrypt(t1.middlename)', 'desc');
                         }
                        
                     $checkouts_user_ids=$qry->get();
                   
                     //$checkouts_user_ids=$users;
                     $checkouts_user_ids=convertStdClassObjToArray($checkouts_user_ids);
                     $qry=Db::table('stores_asset_checkout_details as t1')
                        ->join('ar_asset_inventory as t2','t2.id','=','t1.asset_id')
                        ->join('users as t3', 't3.id', '=', 't1.user_id')
                         ->selectRaw("t1.id as checkout_id,t1.user_id,t2.description as asset_description,
                         DATE_FORMAT(t1.checkout_date ,'%d-%b-%Y') as date_assigned,t2.serial_no,t2.grz_no,
                         CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.middlename),decrypt(t3.last_name)) as name");
                       
                         if($start_date)
                         {
                          
                             $qry->whereDate('t1.checkout_date','>=',Carbon::parse($start_date));
                             
                         }
                         if($end_date)
                         {
                            
                             $qry->whereDate('t1.checkout_date','<=',Carbon::parse($end_date));   
                         }
                         if($report_order && $report_order=="firstname")
                         {
                     $qry->orderByRaw('decrypt(t3.first_name)', 'desc');
                         }
                         if($report_order && $report_order=="lastname")
                         {
                     $qry->orderByRaw('decrypt(t3.last_name)', 'desc');
                         }
                         if($report_order && $report_order=="middlename")
                         {
                     $qry->orderByRaw('decrypt(t3.middlename)', 'desc');
                         }
                        // dd($qry->get());
                     $checkouts=$qry->get();
                     $checkouts_per_user=[];
                     $checkouts=convertStdClassObjToArray($checkouts);
                  
                     foreach($all_group_checkout_user_ids as $user_id_from_group)
                     {
                         $results=$this->_search_array_by_value($checkouts_user_ids,'user_id',$user_id_from_group);
                         if((count($results))==0)//if not in array
                         {
                             $checkouts_user_ids[]=array(
                                 "user_id"=>$user_id_from_group
                             );
                         }
                     }//combine group user ids with normal user ids
                    
                    
                     
                     foreach($checkouts_user_ids as $user )
                     {  
                         $user_results=[];
                         $results=$this->_search_array_by_value($checkouts,'user_id',$user['user_id']);
                        if(count($results)>0)
                        {
                         
                          
                            foreach($results as $user_r)
                            {
                                $user_results[]=$user_r;
                            }

                        }
                
                         $results2=$this->_search_array_by_value($group_transaction_breakdown,'user_id',$user['user_id']);
                    
                         if(count($results2)>0)
                         {
                           
                             foreach($results2 as $user_r)
                             {
                                 $user_results[]=$user_r;
                             }
                         }
                        
                         $checkouts_per_user[$user['user_id'].",".$user['name']]=$user_results;
                     }
                   
                     
                     $table_headers=[
                         "Individual name","Asset Description","Date Assigned"
                     ];
                   
                     $width=100/count($table_headers);
                     $width=100/4;
                   
                     $header_style= "width:".$width."%; background-color: #04AA6D;color: white;text-align: left";
                     $data_style="width:".$width."%;text-align: left;padding-left:7px;";
                    $asset_inventory=$checkouts_per_user;
                  
                    $exclusion_fields=["checkout_id","user_id"];
                    
                    $report_date= date('d-M-Y H:i:s');
                    $view=view('assetperstaffreport',compact('asset_inventory','report_date','image_url',
                    'table_headers','header_style','data_style','exclusion_fields'))->render();
                  
                   $this->generatePDFFIle($view);
                     
                 break;
            case 2:
                $qry = DB::table('ar_asset_inventory as t1');
                $fields_to_use=explode(',',$request->query('fields_to_use'));
               
               
                $column_match=[
                    "description"=>"description",
                    "category"=>"category_id",
                    "FrequencyofMaintainance"=>"maintainance_frequency",
                    "brand"=>"brand_id",
                    "model"=>"model_id",
                    "PurchaseFrom"=>"purchase_from",
                    "PurchaseDate"=>"purchase_date",
                    "cost"=>"cost",
                    "SerialNo"=>"serial_no",
                    "GrzNo"=>"grz_no",
                    "site"=>"site_id",
                    "location"=>"location_id",
                    "department"=>"department_id",
                    "status"=>"status_id"
                ];
              
               // return $column_match['']
                $inventory_columns=[];
               
                foreach ( $fields_to_use as $value) {
                   
                $inventory_columns[]=$column_match[$value];
                
                  }
                
                  $qry = DB::table('ar_asset_inventory as t1');
                  $select_raw_statement=[];
                  $table_headers=[];
                  if(in_array("description",$inventory_columns))
                  {
                      $select_raw_statement[]="t1.description";
                      $table_headers[]="Description";
                  }else{
                    $select_raw_statement[]="t1.description";
                    $table_headers[]="Description"; 
                  }
                  if(in_array("category_id",$inventory_columns))
                  {
                     
                      $qry->join('stores_categories as t2', 't1.category_id', '=', 't2.id');
                      $select_raw_statement[]="t2.name as category_name";
                      $table_headers[]="Category Name";
                  }
                  if(in_array("brand_id",$inventory_columns))
                  {
                     
                      $qry->join('stores_brands as t3', 't1.brand_id', '=', 't3.id');
                      $select_raw_statement[]="t3.name as brand_name";
                      $table_headers[]="Brand Name";
                  }
                  if(in_array("model_id",$inventory_columns))
                  {
                     
                      $qry->join('stores_models as t4', 't1.model_id', '=', 't4.id');
                      $select_raw_statement[]="t4.name as model_name";
                      $table_headers[]="Model Name";
                  }
                  if(in_array("purchase_date",$inventory_columns))
                  {
                     
                      $select_raw_statement[]="DATE_FORMAT(t1.purchase_date,'%d-%b-%Y') as purchase_date";
                      $table_headers[]="Purchase Date";
                  }
                  if(in_array("purchase_from",$inventory_columns))
                  {
                     
                      $select_raw_statement[]="t1.purchase_from";
                      $table_headers[]="Purchase From";
                  }
                  if(in_array("cost",$inventory_columns))
                  {
                     
                      $select_raw_statement[]="t1.cost";
                      $table_headers[]="Acquisition cost";
                  }
                  if(in_array("serial_no",$inventory_columns))
                  {
                     
                      $select_raw_statement[]="t1.serial_no";
                      $table_headers[]="Serial No";
                  }
                  if(in_array("grz_no",$inventory_columns))
                  {
                     
                      $select_raw_statement[]="grz_no";
                      $table_headers[]="Grz No";

                  }
                  if(in_array("site_id",$inventory_columns))
                  {
                     
                      $select_raw_statement[]="t5.name as site_name";
                      $qry->join('stores_sites as t5', 't1.site_id', '=', 't5.id');
                      $table_headers[]="Site ";
                      
                     
                  }
                  if(in_array("location_id",$inventory_columns))
                  {
                      $qry->join('stores_locations as t6', 't1.location_id', '=', 't6.id');
                     
                      $select_raw_statement[]="t6.name as location_name";
                      $table_headers[]="Location";
                  }
                  if(in_array("department_id",$inventory_columns))
                  {
                      $qry->join('stores_departments as t7', 't1.department_id', '=', 't7.id');
                     
                      $select_raw_statement[]="t7.name as department_name";
                      $table_headers[]="Department ";
                  }

                  if(in_array('status_id',$inventory_columns))
                  {
                     
                      $qry->join('stores_statuses as t11','t1.status_id','t11.id');
                      $select_raw_statement[]="t11.name as status";
                      $table_headers[]="Status";
                  }
                 
                  $select_raw_statement=implode(",",$select_raw_statement);
                  $width=100/count($table_headers);
                  $header_style= "width:".$width."%; background-color: #04AA6D;color: white;text-align:left";
                  $data_style="width:".$width."%;text-align: left;padding-left:2px;";
                 
                  $exclusion_fields=[];
                 $qry->selectRaw($select_raw_statement);
                 $results=$qry->get();
                 $asset_inventory=convertStdClassObjToArray($results);
               
                 $report_title="Custom Asset Report ";
                 $report_date= date('d-M-Y H:i:s');
                 $view=view('customassetreport',compact('asset_inventory','report_date','image_url',
                 'table_headers','header_style','data_style','exclusion_fields','report_title'))->render();
                // return $view;
                $this->generatePDFFIle($view);
               

                break;
            case 1://assesory report all inventory items
                
                $other_headers=[
                    'purchase_from'=>array(
                        "header_name"=>"Purchase From",
                        "style"=>"width:20%; background-color: #04AA6D;color: white;text-align: left;",
                        "sql_statement"=>"t1.purchase_from",
                        "data_style"=>"width:20%;text-align: left;padding:5px;"
                    ),
                    'purchase_date'=>array(
                        "header_name"=>"Purchase Date",
                        "style"=>"width:20%; background-color: #04AA6D;color: white;text-align: left;",
                        "sql_statement"=>"t1.purchase_date",
                        "data_style"=>"width:20%;text-align: left;padding:5px;"
                    ),
                    'brand_name'=>array(
                        "header_name"=>"Brand",
                        "style"=>"width:20%; background-color: #04AA6D;color: white;text-align: left;",
                        "sql_statement"=>"t3.name as brand_name",
                        "data_style"=>"width:20%;text-align: left;padding:5px;"
                    ),
                    'model_name'=>array(
                        "header_name"=>"Model",
                        "style"=>"width:20%; background-color: #04AA6D;color: white;text-align: left;",
                        "sql_statement"=>"t4.name as model_name",
                        "data_style"=>"width:20%;text-align: left;padding:5px;"
                    ),
                    // 'sub_category_name'=>array(
                    //     "header_name"=>"Sub-Category",
                    //     "style"=>"width:20%; background-color: #04AA6D;color: white;text-align: left;",
                    //     "sql_statement"=>"t9.name as sub_category_name",
                    //     "data_style"=>"width:20%;text-align: left;padding:5px;"
                    // ),
                    'site_name'=>array(
                        "header_name"=>"Site",
                        "style"=>"width:20%; background-color: #04AA6D;color: white;text-align: left;",
                        "sql_statement"=>"t5.name as site_name",
                        "data_style"=>"width:20%;text-align: left;padding:5px;"
                    ),
                    'location_name'=>array(
                        "header_name"=>"Location",
                        "style"=>"width:20%; background-color: #04AA6D;color: white;text-align: left;",
                        "sql_statement"=>"t6.name as location_name",
                        "data_style"=>"width:20%;text-align: left;padding:5px;"
                    ),
                    'department_name'=>array(
                        "header_name"=>"Department",
                        "style"=>"width:20%; background-color: #04AA6D;color: white;text-align: left;",
                        "sql_statement"=>"t7.name as department_name",
                        "data_style"=>"width:20%;text-align: left;padding:5px;"
                    ),
                    'grz_no'=>array(
                        "header_name"=>"Grz No",
                        "style"=>"width:20%; background-color: #04AA6D;color: white;text-align: left;",
                        "sql_statement"=>"t1.grz_no",
                        "data_style"=>"width:20%;text-align: left;padding:5px;"
                    ),
                    'cost'=>array(
                        "header_name"=>"Purchase Cost",
                        "style"=>"width:20%; background-color: #04AA6D;color: white;text-align: left;",
                        "sql_statement"=>"t1.cost",
                        "data_style"=>"width:20%;text-align: left;padding:5px;"
                    ),
                ];
             
                $headers=[
                    //must math db column names--header keys
                    "description"=>array("header_name"=>"Asset Descitpion",
                                    "style"=>"width:30%; background-color: #04AA6D;color: white;text-align: left;",
                                    "sql_statement"=>"t1.description",
                                    "data_style"=>"width:30%;text-align: left;padding:5px;"
                    ),
                  
            'sub_category_name'=>array(
                "header_name"=>"Sub-Category",
                "style"=>"width:20%; background-color: #04AA6D;color: white;text-align: left;",
                "sql_statement"=>"t9.name as sub_category_name",
                "data_style"=>"width:20%;text-align: left;padding:5px;"
            ),
            "serial_no"=>array("header_name"=>"Serial No",
            "style"=>"width:20%; background-color: #04AA6D;color: white;text-align: left;",
            "sql_statement"=>"t1.serial_no",
            "data_style"=>"width:30%;text-align: left;padding:5px;"
            ),
            "status"=>
                    array(
                    "header_name"=>"Status",
                    "style"=>"width:20%; background-color: #04AA6D;color: white;text-align: left;margin-left:12px;",
                    "sql_statement"=>"t8.name as status",
                    "data_style"=>"width:30%;text-align: left;padding:5px;"
                ),
            

                    ];

                    if($start_date || $end_date)
                    {
                        $headers["purchase_date"]=array(

                            "header_name"=>"Purchase Date",
                            "style"=>"width:20%; background-color: #04AA6D;color: white;text-align: left;",
                            "sql_statement"=>"t1.purchase_date",
                            "data_style"=>"width:30%;text-align: left;padding:5px;"
                        );
                    }
                 $sql_select=[];
              foreach ($headers as $key => $value) {
                 $sql_select[]=$value['sql_statement'];
              }
             
              
            
                if(array_key_exists($report_order,$other_headers))
                {
                   
                    $headers[$report_order]=$other_headers[$report_order];
                    $sql_select[]=$other_headers[$report_order]['sql_statement'];
                }
               
                $qry = DB::table('ar_asset_inventory as t1')
                ->join('stores_categories as t2', 't1.category_id', '=', 't2.id')
                ->join('stores_subcategories as t9', 't1.sub_category_id','t9.id')
                ->leftjoin('stores_brands as t3', 't1.brand_id', '=', 't3.id')
                ->leftjoin('stores_models as t4', 't1.model_id', '=', 't4.id')
                ->join('stores_sites as t5', 't1.site_id', '=', 't5.id')
                ->leftjoin('stores_locations as t6', 't1.location_id', '=', 't6.id')
                ->leftjoin('stores_departments as t7', 't1.department_id', '=', 't7.id')
                ->join('stores_statuses as t8', 't1.status_id', '=', 't8.id')
                ->selectRaw(implode(',',$sql_select));
                     $filter_columns=[
                        ["id"=>"description","text"=>"Description"],
                        ["id"=>"category_name","text"=>"Category"],
                        ["id"=>"brand_name","text"=>"Brand"],
                        ["id"=>"model_name","text"=>"Model"],
                        ["id"=>"purchase_from","text"=>"Purchase From"],
                        ["id"=>"purchase_date","text"=>"Purchase Date"],
                        ["id"=>"cost","text"=>"Cost"],
                        ["id"=>"serial_no","text"=>"Serial No"],
                        ["id"=>"grz_no","text"=>"Grz No"],
                        ["id"=>"site","text"=>"Site"],
                        ["id"=>"location","text"=>"Location"],
                        ["id"=>"department","text"=>"Department"],
                     ];
                //dates

                if($start_date && $end_date)
                {
                
                    $qry->whereBetween('t1.purchase_date',[$start_date,$end_date]);
                    
                }else if($start_date)
                {
                    $qry->whereDate('t1.purchase_date','>=',Carbon::parse($start_date));
                }else if($end_date)
                {
                   
                    $qry->whereDate('t1.purchase_date','<=',Carbon::parse($end_date));   
                }

                //ordering
                    
                if($report_order && $report_order=="description")
                {
                    $qry->orderBy('description', 'desc');
                }
                if($report_order && $report_order=="category_name")
                {
                    $qry->orderBy('t2.name', 'desc');
                }
                if($report_order && $report_order=="brand_name")
                {
                    $qry->orderBy('t3.name', 'desc');
                }
                if($report_order && $report_order=="model_name")
                {
                    $qry->orderBy('t4.name', 'desc');
                }
                if($report_order && $report_order=="purchase_from")
                {
                    $qry->orderBy('purchase_from', 'desc');
                }
                if($report_order && $report_order=="purchase_date")
                {
                    $qry->orderBy('t1.purchase_date', 'desc');
                }
                if($report_order && $report_order=="cost")
                {
                    $qry->orderBy('t1.cost', 'desc');
                }
                if($report_order && $report_order=="serial_no")
                {
                    $qry->orderBy('t1.serial_no', 'desc');
                }
                if($report_order && $report_order=="grz_no")
                {
                    $qry->orderBy('t1.grz_no', 'desc');
                }
                if($report_order && $report_order=="site")
                {
                    $qry->orderBy('t5.name', 'desc');
                }
                if($report_order && $report_order=="location")
                {
                    $qry->orderBy('t6.name', 'desc');
                }
                if($report_order && $report_order=="department")
                {
                    
                 
                    $qry->orderBy('t7.name', 'desc');
                }

                

                
                $results = $qry->get();
                $asset_inventory=convertStdClassObjToArray($results);
               
        
            
                $report_date= date('d-M-Y H:i:s');
                $report_name= Db::table('stores_asset_report_types')->where('id',1)->value('name');

                $view=view('assesory_report',compact('asset_inventory','report_date','image_url','headers','report_name'))->render();

                //$view=view('assesory_report',compact('asset_inventory','report_date','image_url','headers'))->render();
                $this->generatePDFFIle($view);
                break;
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

public function getStoresAssetUploadBatches()
{
        $qry=Db::table('stores_asset_upload_batches as t1')
        ->join('users as t2','t2.id','t1.created_by')
        ->selectraw("t1.id,batch_no,t1.id as batch_id,description,t1.created_at as upload_date,CONCAT_WS(' ',decrypt(t2.first_name),decrypt(t2.last_name)) as uploaded_by,t1.status");
        $results=convertStdClassObjToArray($qry->get());
       foreach($results as $key=>$result)
       {
        $count=Db::table('stores_pre_upload')->where('batch_id',$result['id'])->where('status_id','<',3)->count();
        if($count>0)
        {
            $result['status']=0;
        }else{
            $result['status']=1;
        }
        $results[$key]=$result;
       }
        $res = array(
            'success' => true,
            'results' =>  $results
             );
        return response()->json($res);
}


public function UploadStoresMappedAssets(Request $req){
    $selected = $req->input('selected');
    $selected_ids = json_decode($selected);
    $asset_insert = array();
    $res=array();
    $result;
        try{
    //return response()->json($res); 
        foreach ($selected_ids as $selected_id) {
            $asset_data=Db::table('stores_pre_upload')->selectraw('*')->where('id',$selected_id)->first();
            $params = array(
                'description' => $asset_data->description,
                'category_id' =>  $asset_data->category_id,
                'brand_id' => $asset_data->brand_id,
                'model_id' =>  $asset_data->model_id,
                'purchase_from' => $asset_data->purchase_from,
                'purchase_date' => $asset_data->purchase_date,
                'cost' => $asset_data->cost,
                'supplier_id'=>$asset_data->supplier_id,//Job on 30/06/2022
                'sub_category_id'=>$asset_data->sub_category_id,
                'serial_no' =>$asset_data->serial_no,
                'grz_no' => $asset_data->grz_no,
                'site_id' => $asset_data->site_id,
                'status_id'=>$asset_data->status_id,
                // 'location_id' => $asset_data->location,
                // 'department_id' => $asset_data->department_id,
                'identifier_id' => $asset_data->identifier_id,//job on 26/5/2022,
                "view_id"=>$asset_data->view_id,
                "folder_id"=>$asset_data->folder_id,
                "created_at"=>Carbon::now(),
                "created_by"=>$this->user_id,
                'module_id'=>637,
            );

          
          
           $asset_id= Db::table('ar_asset_inventory')->insertGetId($params);
           if($asset_data->status_id==9){
          
            if($asset_data->disposal_details!=null)
            {
                $disposal_data=array();
                $disposal_details=json_decode($asset_data->disposal_details);
                $disposal_data['asset_id']=$asset_id;
                $disposal_data['date_of_disposal']=$disposal_details->date_of_disposal;
                $disposal_data['disposal_reason']=$disposal_details->disposal_reason;
                $disposal_data['disposal_method']=$disposal_details->disposal_method;
                $disposal_data['remarks']=$disposal_details->remarks;
                $disposal_data['created_by']=$this->user_id;
                $disposal_data['created_at']=Carbon::now();
                Db::table('ar_asset_disposal_details')->insert($disposal_data);
            }
          }
          if($asset_data->status_id==4){
          
            if($asset_data->loss_details!=null)
            { 
                $loss_data=array();
                $loss_details=json_decode($asset_data->loss_details);
                $loss_data['asset_id']=$asset_id;
                $loss_data['reported_by']=$loss_details->reported_by;
                $loss_data['loss_damage_date']=$loss_details->loss_damage_date;
                $loss_data['individuals_responsible']=$loss_details->individuals_responsible;
                $loss_data['lost_damaged_site_id']=$loss_details->lost_damaged_site_id;
                $loss_data['lost_damaged_location_id']=$loss_details->lost_damaged_location_id;
                $loss_data['lost_damaged_department_id']=$loss_details->lost_damaged_department_id;
                $loss_data['created_by']=$this->user_id;
                $loss_data['created_at']=Carbon::now();
                $loss_data['remarks']=$loss_details->remarks;
                Db::table('stores_asset_disposal_details')->insert($loss_data);
            }
          }
            $upload_data=[
                "request_date"=>carbon::now(),
                "requistion_site_id"=>$asset_data->site_id,
                //"requistion_location_id"=>$location_id,
                //"requistion_department_id"=>$department_id,
                "requested_for"=>$asset_data->checkout_type,
                "asset_category"=>$asset_data->sub_category_id,
                "created_at"=>Carbon::now(),
                "created_by"=> $this->user_id,
                "verified"=>1,
                "remarks"=>"Asset  assignment on upload ",
                'requisition_status'=>1,
                'requested_by'=>$asset_data->checkout_type==2?$asset_data->Individual_id:0,
                "onbehalfof"=> 1,
                "is_single_checkout"=>$asset_data->checkout_type==2?:0,
                "is_site_single_checkout"=>$asset_data->checkout_type==1?1:0,
          ];
          $requisition_id="";
          if($asset_data->status_id==2){
          $requisition_id= Db::table('stores_asset_requisitions')->insertGetId($upload_data);
          }
          //checkout asset
          $table_name = 'stores_asset_checkout_details';
          $table_data = array(
              'asset_id' => $asset_id,
              'user_id' => $asset_data->checkout_type==2? $asset_data->Individual_id:0, 
              'checkout_date' => Carbon::now(),
              'no_due_date' => 1,
              //'due_date' => $request->input('due_date'),
              'send_email' => 0,
             // 'user_email' => $request->input('user_email'),
              'checkout_site_id' => $asset_data->site_id,
              'checkout_status'=>1,
              'requisition_id'=> $requisition_id,
              "is_single_site_checkout"=>$asset_data->checkout_type==1?1:0,
              "site_asset_individual_responsible"=>$asset_data->Individual_id,
              //'checkout_location_id' => $location_id,
              //'checkout_department_id' => $department_id,
          );
          if($asset_data->status_id==2){
          $checkout_id= Db::table($table_name)->insertGetId($table_data);
          $result=Db::table('stores_asset_requisitions')
          ->where('id',$requisition_id)
          ->update(array(
            "checkout_id"=> $checkout_id
          ));
         }
          $result=DB::table('stores_pre_upload')->where('id',$selected_id)->update(['upload_status'=>3]);//is uploaded
        }

        if($result)
                {
                    $res=array(
                        "success"=>true,
                        "message"=>"Assets Uploaded To Inventory"
                    );
                }else{
                    $res=array(
                        "success"=>false,
                        "message"=>"Assets Upload Failed"
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

public function getStoresPreUploadedAssets(Request $req)
{
    $batch_id=$req->input('batch_id');
    $qry = DB::table('stores_pre_upload as t1')
            ->join('stores_categories as t2', 't1.category_id', '=', 't2.id')
            ->leftjoin('stores_subcategories as t12', 't1.sub_category_id', '=', 't12.id')//new late fix
            ->leftjoin('stores_brands as t3', 't1.brand_id', '=', 't3.id')
            ->leftjoin('stores_models as t4', 't1.model_id', '=', 't4.id')
            ->leftjoin('stores_sites as t5', 't1.site_id', '=', 't5.id')
            ->leftjoin('users as t6','t6.id','t1.individual_id')
            
            ->selectRaw("t1.*,t1.id as record_id,t1.id as asset_id, t2.name as category_name, t3.name as brand_name, t4.name as model_name,t1.location,
                
                 CONCAT_WS('-',t1.description,t1.grz_no) as desc_grz_no,  CONCAT_WS('-',t1.description,t1.serial_no) as desc_serial_no,t5.name as site,                             
                t12.name as sub_category_name,CONCAT_WS(' ',decrypt(t6.first_name),decrypt(t6.last_name)) as individual,checkout_type as checkout_type_id,t6.id as user_id,t1.upload_status as status");
               
                if (validateisNumeric($batch_id)) {
                    $qry->where('t1.batch_id',$batch_id);
                }
                $results = $qry->get()->toArray();
                foreach($results as $key=>$result)
                {
                    if($result->disposal_details!=null)
                    {
                        $disposal_details=json_decode($result->disposal_details);
                        $result->date_of_disposal=$disposal_details->date_of_disposal;
                        $result->disposal_reason=$disposal_details->disposal_reason;
                        $result->disposal_method=$disposal_details->disposal_method;
                        $result->remarks=$disposal_details->remarks;
                        $results[$key]=$result;
                    }
                    if($result->loss_details!=null)
                    {
                        $loss_details=json_decode($result->loss_details);
                        $result->date_lost_damaged=$loss_details->loss_damage_date;
                        $result->document_name=$loss_details->document_name;
                        $result->user_id=$this->returnArrayFromStringArray($loss_details->individuals_responsible);
                        $result->remarks=$loss_details->remarks;
                        $result->lost_damaged_in_site_id=$loss_details->lost_damaged_site_id;
                        $result->lost_damaged_in_location_id=$loss_details->lost_damaged_location_id;
                        $result->lost_damaged_in_department_id=$loss_details->lost_damaged_department_id;
                        $results[$key]=$result;
                    }
                }
                $res = array(
                    'success' => true,
                    'message' => returnMessage($results),
                    'results' => $results
                );
                return response()->json($res);
           
}

public function getStoresSites(){
    $qry=db::table('stores_sites')
    ->selectraw('id,name');
    $results = $qry->get();
    $res = array(
        'success' => true,
         'results' => $results
    );
    return response()->json($res);
}

public function saveStoresAssetMappingDetails(Request $req)
{
    $record_id= $req->input('record_id');
    $checkout_type_id = $req->input('checkout_type_id');
    $site_id = $req->input('site_id');
    $user_id = $req->input('user_id');
    $table_data=array();
    if($checkout_type_id==2){
        $table_data['Individual_id']=$user_id;
    }
    $table_data['site_id']=$site_id;
    $table_data['checkout_type']=$checkout_type_id;
    $table_data['status_id']=$req->input('status_id');//mapped
    $table_data['upload_status']=2;
    $table_data['updated_at'] = Carbon::now();
    $table_data['updated_by'] = $this->user_id;
    
    $where=array(
        'id'=>$record_id
    );
   
    $previous_data = getPreviousRecords('stores_pre_upload', $where);
    $res = updateRecord('stores_pre_upload', $previous_data, $where, $table_data, $this->user_id);
    $res = array(
        'success' => true,
        'message' => "Asset Data Mapped Successfully",
       
    );
    return response()->json($res);

}

public function saveStoresAssetAdditionalData(Request $request)
    {
        $res="";
        try {
            $form_data=$request->all();
            $table_data=[];
            $asset_id=$form_data['asset_id'];
            unset($form_data['asset_id']);
            $count= DB::table('stores_category_attributes_values')
            ->where('asset_id',$asset_id)->count();
        
            if($count>1)
            {
                foreach ($form_data as $key => $value) {
                
                    $attribute_id = preg_replace('/[^0-9.]+/', '', $key);
                    $table_data[]=array(
                        "attribute_id"=>$attribute_id,
                        "value"=>$value
                    );
                  
                    foreach ($table_data as $key => $data) {
                    
                       $where=array(
                           "attribute_id"=>$data['attribute_id'],
                           "asset_id"=>$asset_id
                       );
                       $previous_data = getPreviousRecords('stores_category_attributes_values', $where);
                       $res = updateRecord('stores_category_attributes_values', $previous_data, $where, ["value"=>$data['value']], $this->user_id);
                  
                    }
                   
            }
         
            }else{
                foreach ($form_data as $key => $value) {
                
                    $attribute_id = preg_replace('/[^0-9.]+/', '', $key);
                    $table_data[]=array(
                        "asset_id"=>$asset_id,
                        "attribute_id"=>$attribute_id,
                        "value"=>$value
                    );
                }
            $res=DB::table('stores_category_attributes_values')->insert($table_data);
            if($res==1 || $res==true)
            {
                $res=array(
                    "success"=>true,
                    "message"=>"Data Saved Successfully"

                );
            }else{
                $res=array(
                    "success"=>false,
                    "message"=>"Data Not Saved"

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


public function saveStoresAssetMappingDetailsForDisposal(Request $req)
{
        $record_id= $req->input('record_id');
        $date_of_disposal = $req->input('date_of_disposal');
        $disposal_reason = $req->input('disposal_reason');
        $disposal_method = $req->input('disposal_method');
        $remarks = $req->input('remarks');
        $disposal_details=array(
            "date_of_disposal"=> $date_of_disposal,
            'disposal_reason'=>$disposal_reason,
            'disposal_method'=> $disposal_method,
            'remarks'=>$remarks
        );
        $table_data=array();
        $table_data['status_id']=$req->input('status_id');//actual asset status
        $table_data['upload_status']=2;//mapped
        $table_data['updated_at'] = Carbon::now();
        $table_data['updated_by'] = $this->user_id;
        $table_data['disposal_details']=json_encode($disposal_details);
        $where=array(
            'id'=>$record_id
        );
       
        $previous_data = getPreviousRecords('stores_pre_upload', $where);
        $res = updateRecord('stores_pre_upload', $previous_data, $where, $table_data, $this->user_id);
        $res = array(
            'success' => true,
            'message' => "Asset Data Mapped Successfully",
           
        );
        return response()->json($res);
}

public function saveStoresAssetMappingDetailsForLostAsset(Request $req)
    {
        $record_id= $req->input('record_id');
        //$lost_damaged_id = $req->input('lost_damaged_id');
        $date_lost_damaged = $req->input('date_lost_damaged');
        $document_name = $req->input('document_name');
        $individuals_responsible = $req->input('user_id');
        $remarks = $req->input('remarks');
        $lost_damaged_in_site_id = $req->input('lost_damaged_in_site_id');
        $lost_damaged_in_location_id = $req->input('lost_damaged_in_location_id');
        $lost_damaged_in_department_id = $req->input('lost_damaged_in_department_id');
        $folder_id=Db::table('assets_pre_upload')->where('id',$record_id)->value('folder_id');
        createAssetRegisterDMSModuleFolders($folder_id, 34,41, $this->dms_id);   
        $req->merge(['dms'=>$this->dms_id,"comment"=>"none","folder_id"=>$folder_id]);
        $dms=new  DmsController();
        $dms->addDocumentNoFolderIdForAssetLoss(  $req->input('parent_folder_id'),$req->input('sub_module_id'),
        $this->dms_id,$req->input('document_name'),"None",$req->versioncomment);
        $lost_details=array(
            "lost_damaged_id"=> 4,
            'reported_by'=>$this->user_id,
            'loss_damage_date'=> $date_lost_damaged,
            'remarks'=>$remarks,
            'individuals_responsible'=>$individuals_responsible,
            "lost_damaged_site_id"=> $lost_damaged_in_site_id,
            "lost_damaged_location_id"=> $lost_damaged_in_location_id,
            "lost_damaged_department_id"=> $lost_damaged_in_department_id,
            'document_name'=>$document_name
        );  
        $table_data=array();
        $table_data['status_id']=$req->input('status_id');//actual asset status
        $table_data['upload_status']=2;//mapped
        $table_data['updated_at'] = Carbon::now();
        $table_data['updated_by'] = $this->user_id;
        $table_data['loss_details']=json_encode($lost_details);
        $where=array(
            'id'=>$record_id
        );
        $previous_data = getPreviousRecords('stores_pre_upload', $where);
        $res = updateRecord('stores_pre_upload', $previous_data, $where, $table_data, $this->user_id);
        $res = array(
            'success' => true,
            'message' => "Asset Data Mapped Successfully",
           
        );
        return response()->json($res);
    } 

}
