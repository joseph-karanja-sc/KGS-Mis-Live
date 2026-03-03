<?php
    namespace App\ScheduleObjects;
    use Illuminate\Support\Facades\DB;
    use Carbon\Carbon;
    use Illuminate\Support\Facades\Auth;
    class AssetsAutoMaintMove
    {
        public function __invoke()
        {
           
                    $user_id=4;
                    $from_inventory_to_schedule = DB::table('ar_asset_inventory as t1')
                    ->join('ar_asset_categories as t2', 't1.category_id', '=', 't2.id')
                    ->join('ar_asset_subcategories as t9', 't1.sub_category_id', '=', 't9.id')
                    ->leftjoin('ar_asset_brands as t3', 't1.brand_id', '=', 't3.id')
                    ->leftjoin('ar_asset_models as t4', 't1.model_id', '=', 't4.id')
                    ->join('ar_asset_sites as t5', 't1.site_id', '=', 't5.id')
                    ->leftjoin('ar_asset_locations as t6', 't1.location_id', '=', 't6.id')
                    ->leftjoin('departments as t7', 't1.department_id', '=', 't7.id')
                    ->join('ar_asset_statuses as t8', 't1.status_id', '=', 't8.id')
                    ->selectRaw("t1.id as asset_id, t1.description,t1.serial_no,t1.grz_no,t5.id as site_id,t5.name as site_name,
                    t1.location_id,t1.department_id,t6.name as location_name,t7.name as department_name,
                    t8.name as record_status,t1.maintainance_frequency,t1.maintainance_schedule_date")
                    ->where('t1.maintainance_schedule_date', '=', now()->format('Y-m-d') )->get();
                    //->whereDate('t1.maintainance_schedule_date', '=', now()->format('Y-m-d'))->get();
                    $from_inventory_to_schedule_array=convertStdClassObjToArray($from_inventory_to_schedule);
                  
                    foreach($from_inventory_to_schedule_array as $asset)
                    {
                     
                        $result=DB::table('ar_asset_maintainance as t1')
                            ->where('asset_id',$asset['asset_id'])
                           // ->where('maintainance_status','>=',0)//is scheduled
                            ->where('maintainance_due_date','=',$asset['maintainance_schedule_date'])->count();
                            if($result==0)
                            {
                                $table_data=array(
                                    "asset_id"=>$asset['asset_id'],
                                    "maintainance_status"=>1,
                                    "maintainance_due_date"=>$asset['maintainance_schedule_date'],
                                   
                                );
                                $res = insertRecord('ar_asset_maintainance', $table_data, $user_id);
                            }
                       
                    }


                    $from_inventory_to_schedule2 = DB::table('ar_asset_inventory as t1')
                    ->join('stores_categories as t2', 't1.category_id', '=', 't2.id')
                    ->join('stores_subcategories as t9', 't1.sub_category_id', '=', 't9.id')
                    ->leftjoin('stores_brands as t3', 't1.brand_id', '=', 't3.id')
                    ->leftjoin('stores_models as t4', 't1.model_id', '=', 't4.id')
                    ->join('stores_sites as t5', 't1.site_id', '=', 't5.id')
                    ->leftjoin('stores_locations as t6', 't1.location_id', '=', 't6.id')
                    ->leftjoin('stores_departments as t7', 't1.department_id', '=', 't7.id')
                    ->join('stores_statuses as t8', 't1.status_id', '=', 't8.id')
                    ->selectRaw("t1.id as asset_id, t1.description,t1.serial_no,t1.grz_no,t5.id as site_id,t5.name as site_name,
                    t1.location_id,t1.department_id,t6.name as location_name,t7.name as department_name,
                    t8.name as record_status,t1.maintainance_frequency,t1.maintainance_schedule_date")
                    ->where('t1.maintainance_schedule_date', '=', now()->format('Y-m-d') )->get();

                    $from_inventory_to_schedule_array2=convertStdClassObjToArray($from_inventory_to_schedule2);
                  
                    foreach($from_inventory_to_schedule_array2 as $asset)
                    {
                     
                        $result=DB::table('stores_asset_maintainance as t1')
                            ->where('asset_id',$asset['asset_id'])
                           // ->where('maintainance_status','>=',0)//is scheduled
                            ->where('maintainance_due_date','=',$asset['maintainance_schedule_date'])->count();
                            if($result==0)
                            {
                                $table_data=array(
                                    "asset_id"=>$asset['asset_id'],
                                    "maintainance_status"=>1,
                                    "maintainance_due_date"=>$asset['maintainance_schedule_date'],
                                   
                                );
                                $res = insertRecord('stores_asset_maintainance', $table_data, $user_id);
                            }
                       
                    }

        }
    }