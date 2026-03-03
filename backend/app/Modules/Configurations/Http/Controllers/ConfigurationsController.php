<?php

namespace App\Modules\Configurations\Http\Controllers;

use App\Exports\BatchImportTemplate;
use App\Http\Controllers\BaseController;
use App\Modules\configurations\Entities\MISDMSModules;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Elibyy\TCPDF\Facades\TCPDF as PDF;

class ConfigurationsController extends BaseController
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
        return view('configurations::index');
    }

     public function getParameterSetUpInfo()
    {
        try {
            $qry = DB::table('parameters_setup as t1')
                ->join('menus as t2', 't1.module_id', '=', 't2.id')
                ->select('t1.*', 't2.name as module_name');
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
        return \response()->json($res);
    }

     private function returnArrayFromStringArray($string_array)
    {

        $string_array=substr(trim($string_array), 0, -1);
        $final_array=explode(',' ,substr($string_array,1));
        return $final_array;
    }
     public function saveParameterSetupInfo(Request $request)
    {
        try {
            $user_id = $this->user_id;
            $post_data = $request->all();
            $param_setup_id = $post_data['param_setup_id'];
            $table_name = 'parameters_setup_properties';
            $postdata = file_get_contents("php://input");
            $data = json_decode($postdata);
            // the post data is array thus handle as array data
            if (is_array($data)) {
                $data = json_decode($postdata, true);
            } else {
                $data = array();
                $data[] = json_decode($postdata, true);
            }
            foreach ($data as $key => $value) {
                $table_data = array(
                    'name' => $value['name'],
                    'dataindex' => $value['dataindex'],
                    'field_name' => $value['field_name'],
                    'allow_blank' => $value['allow_blank'],
                    'input_type_id' => $value['input_type_id'],
                    'tabindex' => $value['tabindex'],
                    'sql_query' => $value['sql_query'],
                    'param_setup_id' => $param_setup_id
                );
                if (validateisNumeric($value['id'])) {
                    $table_data['created_at'] = Carbon::now();
                    $table_data['created_by'] = $user_id;
                    DB::table($table_name)
                        ->where(array('id' => $value['id']))
                        ->update($table_data);
                } else {
                    $table_data['updated_at'] = Carbon::now();
                    $table_data['updated_by'] = $user_id;
                    DB::table($table_name)
                        ->insert($table_data);
                }
            }
            $res = array(
                'success' => true,
                'message' => 'Request executed successfully'
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

    public function saveConfigCommonDataInitial(Request $req)
    {
        try {
            $user_id = $this->user_id;
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
            $res = array();
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

     //Job on 20/4/2022


    public function getGrantAidedGCEExternalTopUp(Request $req)
    {
        try {
            $qry = DB::table('grant_aided_fees as t1')
                ->leftJoin('users as t2', 't1.created_by', '=', 't2.id')
                ->leftJoin('users as t3', 't1.updated_by', '=', 't3.id')
                ->select(DB::raw("t1.*,CONCAT_WS(' ',decrypt(t2.first_name),decrypt(t2.last_name)) as creator_names,
                                  CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as updator_names"));
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

        public function saveGrantAidedGCEExternalTopUp(Request $req)
    {
        try {
            $user_id = \Auth::user()->id;
            $postdata = file_get_contents("php://input");
            $data = json_decode($postdata);
            if (is_array($data)) {
                $data = json_decode($postdata, true);
            } else {
                $data = array();
                $data[] = json_decode($postdata, true);
            }
            foreach ($data as $key => $value) {
                $id = $value['id'];
                $topUpAmount = $value['topup_amount'];
                $where = array(
                    'id' => $id
                );
                $data = array(
                    'topup_amount' => $topUpAmount,
                    
                    'updated_by' => $user_id
                );
                $prev_data = getPreviousRecords('grant_aided_fees', $where);
                updateRecord('grant_aided_fees', $prev_data, $where, $data, $user_id);
            }
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

    public function saveConfigCommonDataInitial2(Request $req)
    {
        try {
            $user_id = $this->user_id;
            $post_data = $req->all();
            $table_name = $post_data['table_name'];
            $id = $post_data['id'];
            $is_duplicate=false;
         
           
            //unset unnecessary values
            unset($post_data['_token']);
            unset($post_data['table_name']);
            unset($post_data['model']);
            unset($post_data['id']);
            unset($post_data['skip']);
            if($table_name=="checklist_notes")
              {
                 $total_num_checklist_notes= DB::table('checklist_notes')->count();
                 if($total_num_checklist_notes>7)
                 {
                  $res=array(
                      "success"=>false,
                      "message"=>"Total Number of notes is limited to 5.Please delete some to add others."
                  );
                  return response()->json($res);
                 }
                
  
              }
            if($table_name=="ar_asset_categories")
            {
                $post_data['multiple_checkout']=$post_data['multiple_checkout_id'];
                unset($post_data['multiple_checkout_id']);
            }
            //Job 7/12/2021
            $tables_to_check_duplicates_with_referenece_to_pk=["ar_asset_locations","ar_asset_brands",
                 "ar_asset_subcategories","ar_asset_models","ar_asset_category_attributes"];
            $tables_to_check_duplicates_with_no_referenece_to_pk=['ar_asset_disposal_methods','ar_asset_sites',
            'ar_asset_statuses','ar_asset_categories','departments','ar_reservation_categories','ar_depreciation_methods'];
            $table_data = $post_data;//encryptArray($post_data, $skipArray);
            $if_duplicate_val="";
            if(in_array($table_name,$tables_to_check_duplicates_with_referenece_to_pk)){
                $tables_data = array(
                    "ar_asset_locations"=>[
                        "fk_name"=>"name",
                        "pk"=>'site_id',
                    ],
                    "ar_asset_subcategories"=>[
                        "fk_name"=>"name",
                        "pk"=>'category_id',
                    ],
                    "ar_asset_brands"=>[
                        "fk_name"=>"name",
                        "pk"=>'category_id',
                    ],
                    "ar_asset_models"=>[
                        "fk_name"=>"name",
                        "pk"=>'brand_id',
                    ],
                    "ar_asset_category_attributes"=>[
                        "fk_name"=>"attribute_name",
                        "pk"=>'category_id'
                    ]
                );
                $found_table_data=$tables_data[$table_name];
                $comparison_val="";
          
                if($table_name!="ar_asset_brands"){
                $if_duplicate_val=DB::table($table_name)
                ->where($found_table_data["fk_name"],'=',$table_data[$found_table_data["fk_name"]])
                ->where($found_table_data["pk"],$table_data[$found_table_data["pk"]])->value($found_table_data["fk_name"]);
                $comparison_val=$table_data[$found_table_data["fk_name"]];
                }else{
                    
                   if(!isset($id)) {
                $if_duplicate_val= DB::table($table_name)->where('name',$table_data['name'])->value('name');
                $comparison_val=$table_data['name'];
                   }
                }
                if((strtolower($if_duplicate_val))==(strtolower($comparison_val)))
                {
                    $is_duplicate=true;
                    if(isset($id) && $id!="")
                    {
                        $if_duplicate_val_id=DB::table($table_name)->where('name','=',$table_data['name'])->value('id');
                        if($if_duplicate_val_id==$id)
                        {
                            //otherwise if the ids aint equal it means its a duplicate.
                            $is_duplicate=false;
                        }
                    }
                }

          
            }
          
            if(in_array($table_name,$tables_to_check_duplicates_with_no_referenece_to_pk))
            {   
               
                if($table_name!="ar_reservation_categories"){
                    $if_duplicate_val=DB::table($table_name)->where('name','=',$table_data['name'])->value('name');
                    $comparison_val=$table_data['name'];
                }else{
                    $if_duplicate_val=DB::table($table_name)->where('category_name','=',$table_data['category_name'])->value('category_name');
                    $comparison_val=$table_data['category_name'];
                }
                if((strtolower($if_duplicate_val))==(strtolower($comparison_val)))
                {
                    $is_duplicate=true;
                    if(isset($id) && $id!="")
                    {
                        $if_duplicate_val_id=DB::table($table_name)->where('name','=',$table_data['name'])->value('id');
                        if($if_duplicate_val_id==$id)
                        {
                            $is_duplicate=false;
                        }
                    }


                }

            }
            //add extra params
            $table_data['created_at'] = Carbon::now();
            $table_data['created_by'] = $user_id;
            $where = array(
                'id' => $id
            );
            $res = array();
           
            if($table_name!="ar_asset_brands"){
              
                if (isset($id) && $id != "") {
                  
                    if (recordExists($table_name, $where)) {
                        unset($table_data['created_at']);
                        unset($table_data['created_by']);
                        unset($table_data['multiple_checkout_id']);
                        $table_data['updated_at'] = Carbon::now();
                        $table_data['updated_by'] = $user_id;
                        $tables_to_prevent_duplicate_updates=['ar_asset_locations'];
                        if($is_duplicate==false && !in_array($table_name,$tables_to_prevent_duplicate_updates)){
                        $previous_data = getPreviousRecords($table_name, $where);
                        $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                        }else{
                            $res=array(
                                "success"=>false,
                                "message"=>"Error Updating  record,similar record found"
                            );
                        }
                    }
                } else {
                    if($is_duplicate==false){
                    $res = insertRecord($table_name, $table_data, $user_id);
                    }else{
                        $res=array(
                            "success"=>false,
                            "message"=>"Error saving record,similar record found"
                        );
                    }
                }
            }else{
                    

                    if (isset($id) && $id != "") {
                        $table_name="ar_asset_categories";
                        $where=[
                            "id"=>$post_data['category_id']
                        ];
                       
                        if (recordExists($table_name, $where)) {
                            $table_data=[];
                           
                            $table_data['name']=$post_data['category_name'];
                            $table_data['updated_at'] = Carbon::now();
                            $table_data['updated_by'] = $user_id;
                            $previous_data = getPreviousRecords($table_name, $where);
                            $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                        }
                    }else{
                     
                        $category_ids=$this->returnArrayFromStringArray($table_data['category_id']);      
                        unset($table_data['category_id']); 
                        if($is_duplicate==false){
                        $res = insertRecord($table_name, $table_data, $user_id);
                        }else{  
                            $res=array(
                                "success"=>false,
                                "message"=>"Error saving record,similar record found"
                            );
                        }
                    }
                if($res["success"]==true && !isset($id))
                {
                    $brand_id=$res['record_id'];
                    $table_data=[];
                    foreach($category_ids as $category_id)
                    {
                        $table_data[]=[
                            "brand_id"=>$brand_id,
                            "category_id"=> (int)(filter_var($category_id, FILTER_SANITIZE_NUMBER_INT)),
                            "created_at"=>Carbon::now(),
                            "created_by"=>$this->user_id
                        ];
                    }
                    DB::table('ar_asset_brands_categories')->insert($table_data);
                }
               
            }
            if( strpos($res['message'],"Duplicate entry"))
            {
                $res['message']="Duplicate  entry";
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

    public function saveConfigCommonData(Request $req)
    {
        try {
            $user_id = $this->user_id;
            $post_data = $req->all();
            $table_name = $post_data['table_name'];
            $id = $post_data['id'];
            $is_duplicate=false;
         
           
            //unset unnecessary values
            unset($post_data['_token']);
            unset($post_data['table_name']);
            unset($post_data['model']);
            unset($post_data['id']);
            unset($post_data['skip']);
              //checklist_notes_table

            if($table_name=="checklist_notes") {
                // $total_num_checklist_notes= DB::table('checklist_notes')->count();
                // if($total_num_checklist_notes > 5) {
                if (!validateisNumeric($id)) {
                    $res=array(
                        "success"=>false,
                        "message"=>"Total Number of notes is limited to 5.Please delete some to add others."
                    );
                    return response()->json($res);
                }  
            }
            //generate code
            $table_name_to_check = $table_name;
            $tables_to_generate_code=["ar_asset_sites1"];
            if(in_array($table_name_to_check,$tables_to_generate_code)){
            $last_code=Db::table($table_name)->max('code');
            if($last_code=="" || $last_code==0)
            {
                $last_code=100;
            }else{
                $last_code=$last_code+1;
            }
            $post_data['code']=$last_code;
        
            }
            //end generate code
            $multiple_checkout_tables =["ar_asset_categories","stores_categories"];
            if(in_array($table_name,$multiple_checkout_tables)){
            //if($table_name=="ar_asset_categories" || $table_name=="stores_categories")//Job on 7/8/2022
            //{
                $post_data['multiple_checkout']=$post_data['multiple_checkout_id'];
                unset($post_data['multiple_checkout_id']);
            }
            //Job 7/12/2021
            $tables_to_check_duplicates_with_referenece_to_pk=["ar_asset_locations","ar_asset_brands",
                 "ar_asset_subcategories","ar_asset_models","ar_asset_category_attributes",
                 "stores_locations","stores_brands","stores_subcategories","stores_models","stores_category_attributes"
                ];
            $tables_to_check_duplicates_with_no_referenece_to_pk=['ar_asset_disposal_methods','ar_asset_sites',
            'ar_asset_statuses','ar_asset_categories','departments','ar_reservation_categories','ar_depreciation_methods',
            "stores_disposal_methods","stores_sites","stores_statuses","stores_categories","stores_departments","stores_reservation_categories",
            "stores_asset_depreciation"  
        ];
            $table_data = $post_data;//encryptArray($post_data, $skipArray);
            $if_duplicate_val="";
            if(in_array($table_name,$tables_to_check_duplicates_with_referenece_to_pk)){
                $tables_data = array(
                    "ar_asset_locations"=>[
                        "fk_name"=>"name",
                        "pk"=>'site_id',
                    ],
                    "stores_locations"=>[
                        "fk_name"=>"name",
                        "pk"=>'site_id',
                    ],
                    "ar_asset_subcategories"=>[
                        "fk_name"=>"name",
                        "pk"=>'category_id',
                    ],
                    "stores_subcategories"=>[
                        "fk_name"=>"name",
                        "pk"=>'category_id',
                    ],
                    "ar_asset_brands"=>[
                        "fk_name"=>"name",
                        "pk"=>'category_id',
                    ],
                    "stores_brands"=>[
                        "fk_name"=>"name",
                        "pk"=>'category_id',
                    ],
                    "ar_asset_models"=>[
                        "fk_name"=>"name",
                        "pk"=>'brand_id',
                    ],
                    "stores_models"=>[
                        "fk_name"=>"name",
                        "pk"=>'brand_id',
                    ],
                    "ar_asset_category_attributes"=>[
                        "fk_name"=>"attribute_name",
                        "pk"=>'category_id'
                    ],
                    "stores_category_attributes"=>[
                        "fk_name"=>"attribute_name",
                        "pk"=>'category_id'
                    ]
                );
                $found_table_data=$tables_data[$table_name];
                $comparison_val="";
                $brands_core_tables=['ar_asset_brands','stores_brands'];
                if(!in_array($table_name,$brands_core_tables)){
                //if($table_name!="ar_asset_brands"){
                $if_duplicate_val=DB::table($table_name)
                ->where($found_table_data["fk_name"],'=',$table_data[$found_table_data["fk_name"]])
                ->where($found_table_data["pk"],$table_data[$found_table_data["pk"]])->value($found_table_data["fk_name"]);
                $comparison_val=$table_data[$found_table_data["fk_name"]];
                }else{
                    if($table_name=="ar_asset_brands" || $table_name="stores_brands")
                    {
                        $table_data['name']=$table_data['name'];
                        //$table_data['name']=$table_data['category_name'];
                    }
                   if(!isset($id)) {
                $if_duplicate_val= DB::table($table_name)->where('name',$table_data['name'])->value('name');
                $comparison_val=$table_data['name'];
                   }
                }
                if((strtolower($if_duplicate_val))==(strtolower($comparison_val)))
                {
                    $is_duplicate=true;
                    if(isset($id) && $id!="")
                    {
                        $if_duplicate_val_id=DB::table($table_name)->where('name','=',$table_data['name'])->value('id');
                        if($if_duplicate_val_id==$id)
                        {
                            //otherwise if the ids aint equal it means its a duplicate.
                            $is_duplicate=false;
                        }
                    }
                }

          
            }
          
            if(in_array($table_name,$tables_to_check_duplicates_with_no_referenece_to_pk))
            {   
               $reservation_tables=["ar_reservation_categories","stores_asset_reservations"];
               if(!in_array($table_name,$reservation_tables)){
                //if($table_name!="ar_reservation_categories"){
                    $if_duplicate_val=DB::table($table_name)->where('name','=',$table_data['name'])->value('name');
                    $comparison_val=$table_data['name'];
                }else{
                    $if_duplicate_val=DB::table($table_name)->where('category_name','=',$table_data['category_name'])->value('category_name');
                    $comparison_val=$table_data['category_name'];
                }
                if((strtolower($if_duplicate_val))==(strtolower($comparison_val)))
                {
                    $is_duplicate=true;
                    if(isset($id) && $id!="")
                    {
                        $if_duplicate_val_id=DB::table($table_name)->where('name','=',$table_data['name'])->value('id');
                        if($if_duplicate_val_id==$id)
                        {
                            $is_duplicate=false;
                        }
                    }


                }

            }
            //add extra params
            $table_data['created_at'] = Carbon::now();
            $table_data['created_by'] = $user_id;
            $where = array(
                'id' => $id
            );
            $res = array();
            $exempted_tables=["ar_asset_brands","stores_brands"];//Job on 7/8/2022
            if(!in_array($table_name,$exempted_tables)){
            //if($table_name!="ar_asset_brands" || $table_name!="stores_brands"){
              
                if (isset($id) && $id != "") {
                    if (recordExists($table_name, $where)) {
            
                        unset($table_data['created_at']);
                        unset($table_data['created_by']);
                        unset($table_data['multiple_checkout_id']);
                        $table_data['updated_at'] = Carbon::now();
                        $table_data['updated_by'] = $user_id;
                        $tables_to_prevent_duplicate_updates=['ar_asset_locations'];
                        if($is_duplicate==false && !in_array($table_name,$tables_to_prevent_duplicate_updates)){
                        $previous_data = getPreviousRecords($table_name, $where);
                        $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                        }else{
                            $res=array(
                                "success"=>false,
                                "message"=>"Error Updating  record,similar record found"
                            );
                        }
                    }
                } else {
                    if($is_duplicate==false){
                    $res = insertRecord($table_name, $table_data, $user_id);
                    }else{
                        $res=array(
                            "success"=>false,
                            "message"=>"Error saving record,similar record found"
                        );
                    }
                }
            }else{
                
                    if (isset($id) && $id != "") {
                        if($table_name=="stores_brands"){
                            $table_name="stores_categories";
                        }else{
                        $table_name="ar_asset_categories";
                        }
                       
                        $where=[
                            "id"=>$post_data['category_id']
                        ];
                       
                        if (recordExists($table_name, $where)) {
                            $table_data=[];
                           
                            $table_data['name']=$post_data['category_name'];
                            $table_data['updated_at'] = Carbon::now();
                            $table_data['updated_by'] = $user_id;
                            $previous_data = getPreviousRecords($table_name, $where);
                            $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                        }
                    }else{
                     
                        $category_ids=$this->returnArrayFromStringArray($table_data['category_id']);      
                        unset($table_data['category_id']); 
                        if($is_duplicate==false){   
                       $res = insertRecord($table_name, $table_data, $user_id);
                        }else{  
                            $res=array(
                                "success"=>false,
                                "message"=>"Error saving record,similar record found"
                            );
                        }
                    }
                    // $res=array(
                    //     "success"=>true,
                    //     "record_id"=>41

                    // );
                if($res["success"]==true && !isset($id))
                {
                    $brand_id=$res['record_id'];
                    $table_data=[];
                    foreach($category_ids as $category_id)
                    {
                        $table_data[]=[
                            "brand_id"=>$brand_id,
                            "category_id"=> (int)(filter_var($category_id, FILTER_SANITIZE_NUMBER_INT)),
                            "created_at"=>Carbon::now(),
                            "created_by"=>$this->user_id
                        ];
                    }
                    if($table_name=="ar_asset_brands"){//job on 7/8/2022 ar_asset_categories
                        DB::table('ar_asset_brands_categories')->insert($table_data);
                    }else{
                        DB::table('stores_brands_categories')->insert($table_data);

                    }
                   
                }
               
            }
            if( strpos($res['message'],"Duplicate entry"))
            {
                $res['message']="Duplicate  entry";
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
    
    public function saveChecklistType(Request $req)
    {
        $user_id = \Auth::user()->id;
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
        try {
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
                //generation of checklist references
                $last_id = DB::table('checklist_types')->max('id');
                $current_id = $last_id + 1;
                $year = substr(date('Y'), -2);
                $reference = 'KGS/CHL/' . $year . '/' . str_pad($current_id, '4', 0, STR_PAD_LEFT);
                $table_data['reference'] = $reference;
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
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function saveTemplateColumn(Request $req)
    {
        $user_id = \Auth::user()->id;
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
        $col_name = $post_data['dataindex'];
        $template_id = $post_data['temp_id'];
        $stdTempID = getStdTemplateId();
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
                $prev_col = aes_decrypt($previous_data[0]['dataindex']);
                $success = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                if ($success) {
                    if ($template_id == $stdTempID) {
                        Schema::table('beneficiary_master_info', function ($table) use ($prev_col, $col_name) {
                            $table->renameColumn($prev_col, $col_name);
                        });
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
            $success = insertRecord($table_name, $table_data, $user_id);
            if ($success) {
                if ($template_id == $stdTempID) {
                    Schema::table('beneficiary_master_info', function ($table) use ($col_name) {
                        $table->string($col_name);
                    });
                }
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

    public function saveDataFromEditor(Request $req)
    {
        $user_id = \Auth::user()->id;
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
        //unset($post_data['_dc']);
        $table_data = $post_data;// encryptArray($post_data, $skipArray);
        //add extra params
        $table_data['created_at'] = Carbon::now();
        $table_data['created_by'] = $user_id;
        $where = array(
            'id' => $id
        );
        if (is_numeric($id) && $id != 0) {
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

    public function addColumns(Request $req)
    {
        $postdata = $req->all();
        $insertdata = array();
        $user_id = \Auth::user()->id;
        unset($postdata['_token']);
        foreach ($postdata as $value) {
            unset($value['updated_at']);
            unset($value['updated_by']);
            unset($value['template_name']);
            $value['name'] = $value['name'];
            $value['dataindex'] = $value['dataindex'];
            $value['existing_id'] = $value['id'];
            unset($value['id']);
            $value['created_at'] = Carbon::now();
            $value['created_by'] = $user_id;
            $insertdata [] = $value;
        }
        $success = DB::table('template_fields')->insert($insertdata);
        if ($success) {
            $res = array(
                'success' => true,
                'message' => 'Columns added Successfully!!'
            );
        } else {
            $res = array(
                'success' => false,
                'message' => 'Problem encountered while adding columns. Please try again!!'
            );
        }
        return response()->json($res);
    }

    public function saveLetterDates(Request $req)
    {
        $user_id = \Auth::user()->id;
        $is_active = $req->input('is_active');
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
        $res = array();

        //signature specimen upload
        if ($req->hasFile('signature_specimen')) {
            $validator = Validator::make($req->all(), [
                'signature_specimen' => 'image',
            ]);
            if ($validator->fails()) {
                $res = array(
                    'success' => false,
                    'message' => 'Only image is allowed for signature specimen!!'
                );
                return response()->json($res);
            }
            $file = $req->file('signature_specimen');
            $destinationPath = getcwd().'/resources/images/signatures';
            $initialSpecimenName = $file->getClientOriginalName();
            $specimenExtension = $file->getClientOriginalExtension();
            $specimenSavedName = Str::random(8) . '.' . $specimenExtension;
            $file->move($destinationPath, $specimenSavedName);
            $table_data['signature_specimen'] = $specimenSavedName;
            $table_data['signature_path'] = $destinationPath;
        }
        //end
        if (validateisNumeric($id)) {
            $current_active_id = Db::table('letter_dates')
                ->where('is_active', 1)
                ->where('id', '<>', $id)
                ->value('id');
            if (is_numeric($current_active_id) && $is_active == 1) {
                $res = array(
                    'success' => false,
                    'message' => 'There is another active date information. Only one date info can be active at a time!!'
                );
                return response()->json($res);
            }
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
            $current_active_date = Db::table('letter_dates')
                ->where('is_active', 1)
                ->first();
            if (!is_null($current_active_date) && $is_active == 1) {
                $res = array(
                    'success' => false,
                    'message' => 'There is another active date information. Only one date info can be active at a time!!'
                );
                return response()->json($res);
            }
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

    public function getConfigParam($model_name)
    {
        $model = 'App\\Modules\\configurations\\Entities\\' . $model_name;
        $results = $model::all()->toArray();
        $results = decryptArray($results);
        $res = array(
            'results' => $results
        );
        return response()->json($res);
    }

    public function getChecklistTypes(Request $request)
    {
        $category = $request->input('category_id');
        $strict_checklist = $request->input('strict_checklist');
        try {
            $qry = DB::table('checklist_types as t1')
                ->leftJoin('beneficiary_categories as t2', 't1.category_id', '=', 't2.id')
                ->select(DB::raw("t1.*, t2.name as category_name,CONCAT_WS('-',t1.reference,t1.name) as checklist_type"));
            if (isset($category) && $category != '') {
                $qry->where('category_id', $category);
            }
            if (isset($strict_checklist) && $strict_checklist != '') {
                $qry->where('strict_checklist', $strict_checklist);
            }
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

    public function getBatchChecklistTypesLinkage()
    {
        try {
            $qry = DB::table('batch_checklist_types as t1')
                ->join('batch_info as t2', 't1.batch_id', '=', 't2.id')
                ->join('beneficiary_categories as t3', 't1.category_id', '=', 't3.id')
                ->join('checklist_types as t4', 't1.checklist_type_id', '=', 't4.id')
                ->select(DB::raw("t1.*,t2.batch_no,t3.name as category_name,CONCAT_WS('-',t4.reference,t4.name) as checklist_type"));
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

    public function deleteConfigRecord(Request $req)
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
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

     public function deleteRecordWithComments(Request $req)
    {
        $record_id = $req->id;
        $table_name = $req->table_name;
        $delete_reason =$req->delete_reason;
        $user_id = \Auth::user()->id;
        $comments = $req->input('delete_reason');
        $where = array(
            'id' => $record_id
        );
        
        try {
            $previous_data = getPreviousRecords($table_name, $where);
            $res = deleteRecordWithComments($table_name, $previous_data, $where, $user_id,$delete_reason);
             $result=strpos('foreign',$res['message']);
           
            if(strpos($res['message'],'foreign key constraint fails')==true)
            {
                $res['message']="The Item can't be deleted  as it has related data";
            }
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function deleteTemplateColRecord(Request $req)
    {
        $record_id = $req->id;
        $table_name = $req->table_name;
        $template_id = $req->template_id;
        $stdTempID = getStdTemplateId();
        $user_id = \Auth::user()->id;
        $where = array(
            'id' => $record_id
        );
        try {
            $previous_data = getPreviousRecords($table_name, $where);
            $col_name = aes_decrypt($previous_data[0]['dataindex']);
            $success = deleteRecord($table_name, $previous_data, $where, $user_id);
            if ($success) {
                if ($template_id == $stdTempID) {
                    Schema::table('temp_uploads', function ($table) use ($col_name) {
                        $table->dropColumn($col_name);
                    });
                }
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
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function deleteChecklistQuiz(Request $req)
    {
        $record_id = $req->id;
        $table_name = $req->table_name;
        $user_id = \Auth::user()->id;
        $where = array(
            'id' => $record_id
        );
        try {
            $previous_data = getPreviousRecords($table_name, $where);
            $success = deleteRecord($table_name, $previous_data, $where, $user_id);
            if ($success) {
                DB::table('answer_options')->where('checklist_item_id', $record_id)->delete();
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
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getTemplateInformation(Request $req)
    {
        $template_id = $req->template_id;
        $stdTempID = getStdTemplateId();
        $whereIn = array(
            $template_id, $stdTempID
        );
        $qry = DB::table('template_fields')
            ->join('templates', 'template_fields.temp_id', '=', 'templates.id')
            ->leftJoin('datatypes', 'template_fields.type', '=', 'datatypes.id')
            ->leftJoin('paramtables', 'template_fields.param_table', '=', 'paramtables.id')
            ->select('template_fields.*', 'templates.name as template_name', 'datatypes.name as datatype_name', 'paramtables.name as paramtables_name')
            ->orderBy('template_fields.tabindex');
        $qry = $template_id == '' ? $qry->whereRaw(1, 1) : $qry->whereIn('template_fields.temp_id', $whereIn);
        $data = $qry->get();
        $results = convertStdClassObjToArray($data);
        $results = decryptArray($results);
        foreach ($results as $key => $result) {
            $results[$key]['is_parameterised_name'] = getConfirmationFlag($result['is_parameterised']);
            $results[$key]['is_value_parameterised_name'] = getConfirmationFlag($result['is_value_parameterised']);
            $results[$key]['is_mandatory_name'] = getConfirmationFlag($result['is_mandatory']);
            $results[$key]['needs_validations_name'] = getConfirmationFlag($result['needs_validations']);
        }
        $res = array(
            'results' => $results
        );
        return response()->json($res);
    }

    public function getExistingColumns(Request $req)
    {
        $template_id = $req->template_id;
        $stdTempID = getStdTemplateId();
        //get all added columns....to avoid repetition
        $existingIDsAssArr = DB::table('template_fields')
            ->select('existing_id')
            ->where('temp_id', $template_id)
            ->where('existing_id', '<>', 0)
            ->whereNotNull('existing_id')
            ->get();
        $existingIDsSimpArr = convertAssArrayToSimpleArray(convertStdClassObjToArray($existingIDsAssArr), 'existing_id');
        $notArray = array($template_id, $stdTempID);
        //DB::enableQueryLog();
        $qry = DB::table('template_fields')
            ->join('templates', 'template_fields.temp_id', '=', 'templates.id')
            ->select('template_fields.*', 'templates.name as template_name')
            ->groupBy('template_fields.name')
            ->WhereNotIn('template_fields.temp_id', $notArray)
            ->WhereNotIn('template_fields.id', $existingIDsSimpArr);
        //->groupBy('template_fields.name');
        $data = $qry->get();
        // print_r(DB::getQueryLog());
        $results = convertStdClassObjToArray($data);
        $results = decryptArray($results);
        $res = array(
            'results' => $results
        );
        return response()->json($res);
    }

    function downloadTemplate($template_id)
    {
        //get template fields which represent excel column headers
        $stdTemplateID = getStdTemplateId();
        //$template_id = $req->input('template_id');
        $template_name = DB::table('templates')->where('id', $template_id)->value('name');
        $template_name = aes_decrypt($template_name);
        $inArray = array($stdTemplateID, $template_id);
        $qry = DB::table('template_fields')->whereIn('temp_id', $inArray);
        $templateFields = $qry->get();

        $templateFields = convertStdClassObjToArray($templateFields);
        $templateFields = decryptArray($templateFields);
        $templateFields = convertAssArrayToSimpleArray($templateFields, 'name');

        return Excel::download(new BatchImportTemplate($templateFields), $template_name . '.xls');
    }

    public function getDuplicateParams()
    {
        $stdTemplateID = getStdTemplateId();
        $params = DB::table('template_fields')->where('temp_id', $stdTemplateID)->get();
        $params = convertStdClassObjToArray($params);
        $params = decryptArray($params);
        $res = array(
            'results' => $params
        );
        return response()->json($res);
    }

    public function getChecklistItems(Request $req)
    {
        $checklist_id = $req->checklist_id;
        $qry = DB::table('checklist_items')
            ->join('checklist_types', 'checklist_items.checklist_id', '=', 'checklist_types.id')
            ->select('checklist_items.*', 'checklist_types.name as checklist_name', 'checklist_types.reference as checklist_reference')
            ->orderBy('order_no', 'ASC');
        $qry = $checklist_id == '' ? $qry->whereRaw(1, 1) : $qry->where('checklist_items.checklist_id', $checklist_id);
        $data = $qry->get();
        $data = convertStdClassObjToArray($data);
        $data = decryptArray($data);
        $res = array(
            'results' => $data
        );
        return response()->json($res);
    }

    public function getAnswerTypes()
    {
        $data = DB::table('answer_types')->get();
        $res = array(
            'results' => $data
        );
        return response()->json($res);
    }

    public function addChecklistQuestion(Request $req)
    {
        $id = $req->input('id');
        $checklist_id = $req->input('checklist_id');
        $question = $req->input('name');
        $description = $req->input('description');
        $order_no = $req->input('order_no');
        $answer_type = $req->input('answer_type');
        $options_count = $req->input('answer_options_count');
        $is_knock_out = $req->input('is_knock_out');
        $user_id = \Auth::user()->id;
        $res = array();
        DB::transaction(function () use (&$res, $req, $id, $checklist_id, $description, $order_no, $answer_type, $question, $user_id, $options_count, $is_knock_out) {
            try {
                $questions_data = array(
                    'checklist_id' => $checklist_id,
                    'name' => $question,
                    'order_no' => $order_no,
                    'answer_type' => $answer_type,
                    'description' => $description,
                    'created_at' => Carbon::now(),
                    'created_by' => $user_id,
                    'is_knock_out' => $is_knock_out
                );
                $where = array(
                    'id' => $id
                );
                if (isset($id) && $id != '') {
                    $prev_records = getPreviousRecords('checklist_items', $where);
                    updateRecord('checklist_items', $prev_records, $where, $questions_data, $user_id);
                    DB::table('answer_options')->where(array('checklist_item_id' => $id))->delete();
                    for ($i = 1; $i <= $options_count; $i++) {
                        $option = $req->input('answer_option' . $i);
                        $label_name = $req->input('label_option' . $i);
                        /*if ($answer_type == 3 || $answer_type == 4 || $answer_type == 5 || $answer_type == 6) {
                            if (isset($label_name) && $label_name != '') {
                                $option = $label_name;
                            } else {
                                $option = 'No Label';
                            }
                        }*/
                        $answer_options_data = array(
                            'checklist_item_id' => $id,
                            'answer_type_id' => $answer_type,
                            'option_id' => $option,
                            'option_label' => $label_name,
                            'created_at' => Carbon::now(),
                            'created_by' => $user_id
                        );
                        DB::table('answer_options')->insert($answer_options_data);
                    }
                    $res = array(
                        'success' => true,
                        'message' => 'Question updated successfully!!'
                    );
                } else {
                    $question_id = insertRecordReturnId('checklist_items', $questions_data, $user_id);
                    if (is_numeric($question_id)) {
                        for ($i = 1; $i <= $options_count; $i++) {
                            $option = $req->input('answer_option' . $i);
                            $label_name = $req->input('label_option' . $i);
                            /*if ($answer_type == 3 || $answer_type == 4 || $answer_type == 5 || $answer_type == 6) {
                                if (isset($label_name) && $label_name != '') {
                                    $option = $label_name;
                                } else {
                                    $option = 'No Label';
                                }
                            }*/
                            $answer_options_data = array(
                                'checklist_item_id' => $question_id,
                                'answer_type_id' => $answer_type,
                                'option_id' => $option,
                                'option_label' => $label_name,
                                'created_at' => Carbon::now(),
                                'created_by' => $user_id
                            );
                            DB::table('answer_options')->insert($answer_options_data);
                        }
                    }
                    $res = array(
                        'success' => true,
                        'message' => 'Question Save successfully!!'
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

    function getAnswerOptionsSetup(Request $req)
    {
        $question_id = $req->input('question_id');
        $ans_type_id = $req->input('answer_type_id');
        $where = array(
            'checklist_item_id' => $question_id,
            'answer_type_id' => $ans_type_id
        );
        try {
            $data = DB::table('answer_options')
                ->leftJoin('checklist_options', 'answer_options.option_id', '=', 'checklist_options.id')
                ->select('answer_options.*', 'checklist_options.option_name')
                ->where($where)->get();
            $res = array(
                'success' => true,
                'results' => $data
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'results' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getChecklistOptions()
    {
        $data = DB::table('checklist_options')->get();
        $res = array(
            'results' => $data
        );
        return response()->json($res);
    }

    public function addFieldTeamMembers(Request $req)
    {
        $postdata = $req->all();
        unset($postdata['_token']);
        foreach ($postdata as $key => $value) {
            $insertdata [] = array(
                'user_id' => $postdata[$key]['id'],
                'team_id' => $postdata[$key]['team_id']
            );
        }
        $success = DB::table('fieldteam_users')->insert($insertdata);
        if ($success) {
            $res = array(
                'success' => true,
                'message' => 'Members added Successfully!!'
            );
        } else {
            $res = array(
                'success' => false,
                'message' => 'Problem encountered while adding members. Please try again!!'
            );
        }
        return response()->json($res);
    }

    public function addFieldTeamProvinces(Request $req)
    {
        $postdata = $req->all();
        unset($postdata['_token']);
        foreach ($postdata as $key => $value) {
            $insertdata [] = array(
                'province_id' => $postdata[$key]['id'],
                'team_id' => $postdata[$key]['team_id']
            );
        }
        $success = DB::table('fieldteam_provinces')->insert($insertdata);
        if ($success) {
            $res = array(
                'success' => true,
                'message' => 'Provinces added Successfully!!'
            );
        } else {
            $res = array(
                'success' => false,
                'message' => 'Problem encountered while adding members. Please try again!!'
            );
        }
        return response()->json($res);
    }

    public function getFieldTeamMembers(Request $req)
    {
        $team_id = $req->input('team_id');
        $qry = DB::table('fieldteam_users')
            ->join('users', 'fieldteam_users.user_id', '=', 'users.id')
            ->leftJoin('user_roles', 'users.user_role_id', '=', 'user_roles.id')
            ->select('fieldteam_users.*', 'users.first_name', 'users.middlename', 'users.last_name', 'users.phone', 'users.email', 'user_roles.name as role_name')
            ->where('team_id', $team_id);
        getParamsdata($qry);
    }

    public function getFieldTeamProvinces(Request $req)
    {
        $team_id = $req->input('team_id');
        $qry = DB::table('fieldteam_provinces')
            ->join('provinces', 'fieldteam_provinces.province_id', '=', 'provinces.id')
            ->select('fieldteam_provinces.*', 'provinces.name', 'provinces.code')
            ->where('team_id', $team_id);
        getParamsdata($qry);
    }

    public function getUnselectedTeamMembers(Request $req)
    {
        $team_id = $req->input('team_id');
        $qry = DB::table('users')
            ->leftJoin('user_roles', 'users.user_role_id', '=', 'user_roles.id')
            ->select('users.id', 'users.first_name', 'users.middlename', 'users.last_name', 'users.phone', 'users.email', 'user_roles.name as role_name')
            ->whereNotIn('users.id', function ($query) use ($team_id) {
                $query->select(DB::raw('fieldteam_users.user_id'))
                    ->from('fieldteam_users')
                    ->whereRaw('fieldteam_users.team_id=' . $team_id);
            });
        $data = $qry->get();
        $data = convertStdClassObjToArray($data);
        $data = decryptArray($data);
        $res = array(
            'results' => $data
        );
        return response()->json($res);
    }

    public function getUnselectedTeamProvinces(Request $req)
    {
        $team_id = $req->input('team_id');
        $qry = DB::table('provinces')
            //->select('provinces.id','province.name')
            ->whereNotIn('provinces.id', function ($query) use ($team_id) {
                $query->select(DB::raw('fieldteam_provinces.province_id'))
                    ->from('fieldteam_provinces')
                    ->whereRaw('fieldteam_provinces.team_id=' . $team_id);
            });
        $data = $qry->get();
        $data = convertStdClassObjToArray($data);
        $data = decryptArray($data);
        $res = array(
            'results' => $data
        );
        return response()->json($res);
    }

    public function getParentModules()
    {
        $parents = MISDMSModules::where('level', 0)->get()->toArray();
        $parents = decryptArray($parents);
        $res = array(
            'results' => $parents
        );
        return response()->json($res);
    }

    public function getChildModules(Request $req)
    {
        $parent_id = $req->parent_id;
        $where = array(
            'level' => 1,
            'parent_id' => $parent_id
        );
        $parents = MISDMSModules::where($where)->get()->toArray();
        $parents = decryptArray($parents);
        $res = array(
            'results' => $parents
        );
        return response()->json($res);
    }

    public function saveMISDMSModuleItem(Request $req)
    {
        try {
            $user_id = $this->user_id;
            $post_data = $req->all();
            $table_name = $post_data['table_name'];
            $id = $post_data['id'];
            $skip = $post_data['skip'];
            $level = $post_data['level'];
            $parent_id = $post_data['parent_id'];
            $child_id = $post_data['child_id'];
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
            $table_data = $post_data;//encryptArray($post_data, $skipArray);
            //add extra params
            $table_data['created_at'] = Carbon::now();
            $table_data['created_by'] = $user_id;
            $table_data['parent_id'] = $parent_id;
            $where = array(
                'id' => $id
            );
            $res = array();
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

    public function getModule($level = 0, $parent_id = 0)
    {
        $level = $level;
        $parent_id = $parent_id;
        $qry = DB::table('mis_dms_modules')
            ->where('level', $level);
        $qry = $parent_id == 0 ? $qry->orderBy('id') : $qry->where('parent_id', $parent_id)->orderBy('id');
        $modules = $qry->get();
        $modules = json_decode(json_encode($modules), true);
        return $modules;
    }

       public function getMISDMSModules()
    {
        try {
            $row = $this->getModule(0, 0);
            $menus = '{"modules": "."';
            $menus .= ',';
            $menus .= '"children": [';
            if (count($row)) {
                $menu_count = count($row);
                $menu_counter = 0;

                foreach ($row as $item) {
                    $menu_counter++;
                    $id = $item['id'];
                    $name = aes_decrypt($item['name']);
                    $description = aes_decrypt($item['description']);
                    $level = $item['level'];
                    $is_synced = $item['is_synced'];
                    $dms_id = $item['dms_id'];
                    $sync_to_dms = $item['sync_to_dms'];

                    $menus .= '{';
                    $menus .= '"id": ' . $id . ',';
                    $menus .= '"name": "' . $name . '",';
                    $menus .= '"description": "' . $description . '",';
                    $menus .= '"level": ' . $level . ',';
                    $menus .= '"is_synced": ' . $is_synced . ',';
                    $menus .= '"dms_id": ' . $dms_id . ',';
                    $menus .= '"sync_to_dms": ' . $sync_to_dms . ',';

                    $children = $this->getModule(1, $id);
                    if (count($children) > 0) {
                        $children_count = count($children);
                        $children_counter = 0;
                        $menus .= '"expanded": false,';
                        $menus .= '"children": [';
                        foreach ($children as $child) {
                            $children_counter++;
                            $child_id = $child['id'];
                            $child_name = aes_decrypt($child['name']);
                            $child_description = aes_decrypt($child['description']);
                            $child_level = $child['level'];
                            $child_parent_id = $child['parent_id'];
                            $child_is_synced = $child['is_synced'];
                            $child_dms_id = $child['dms_id'];
                            $child_sync_to_dms = $child['sync_to_dms'];

                            $menus .= '{';
                            $menus .= '"id": ' . $child_id . ',';
                            $menus .= '"name": "' . $child_name . '",';
                            $menus .= '"description": "' . $child_description . '",';
                            $menus .= '"level": ' . $child_level . ',';
                            $menus .= '"parent_id": ' . $child_parent_id . ',';
                            $menus .= '"is_synced": ' . $child_is_synced . ',';
                            $menus .= '"dms_id": ' . $child_dms_id . ',';
                            $menus .= '"sync_to_dms": ' . $child_sync_to_dms . ',';
                            //$menus.="leaf: true";
                            //level 2 menu items
                            $grandchildren = $this->getModule(2, $child_id);
                            if (count($grandchildren) > 0) {
                                $grandchildren_count = count($grandchildren);
                                $grandchildren_counter = 0;
                                $menus .= '"expanded": false,';
                                $menus .= '"iconCls": "tree-parent",';
                                $menus .= '"children": [';
                                foreach ($grandchildren as $grandchild) {
                                    $grandchildren_counter++;
                                    $grandchild_id = $grandchild['id'];
                                    $grandchild_name = aes_decrypt($grandchild['name']);
                                    $grandchild_description = aes_decrypt($grandchild['description']);
                                    $grandchild_level = $grandchild['level'];
                                    $grandchild_parent_id = $child['parent_id'];
                                    $grandchild_child_id = $grandchild['parent_id'];
                                    $grandchild_is_synced = $grandchild['is_synced'];
                                    $grandchild_dms_id = $grandchild['dms_id'];
                                    $grandchild_sync_to_dms = $grandchild['sync_to_dms'];

                                    $menus .= '{';
                                    $menus .= '"id": ' . $grandchild_id . ',';
                                    $menus .= '"name": "' . $grandchild_name . '",';
                                    $menus .= '"description": "' . $grandchild_description . '",';
                                    $menus .= '"level": ' . $grandchild_level . ',';
                                    $menus .= '"parent_id": ' . $grandchild_parent_id . ',';
                                    $menus .= '"child_id": ' . $grandchild_child_id . ',';
                                    $menus .= '"is_synced": ' . $grandchild_is_synced . ',';
                                    $menus .= '"dms_id": ' . $grandchild_dms_id . ',';
                                    $menus .= '"sync_to_dms": ' . $grandchild_sync_to_dms . ',';
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
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
            return $res;
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
            return $res;
        }
    }

    public function syncMISModulesToDMS()
    {
        try {
            $qry = DB::table('mis_dms_modules')
                ->where('level', 0);
            //->where('sync_to_dms', 1);
            //->where('is_synced', '<>', 1);
            $parents = $qry->get();
            foreach ($parents as $parent) {
                $dms_details = $this->syncModule($parent->id, $parent->name, $parent->description);
                $parent_id = $dms_details['dms_id'];
                $folder_list = $dms_details['folder_list'] . $parent_id . ':';
                $qry2 = DB::table('mis_dms_modules')
                    ->where('level', 1)
                    ->where('sync_to_dms', 1)
                    ->where('parent_id', $parent->id)
                    ->where('is_synced', '<>', 1);
                $children = $qry2->get();
                foreach ($children as $child) {
                    $this->syncModule($child->id, $child->name, $child->description, $parent_id, $folder_list);
                }
            }
            $res = array(
                'success' => true,
                'message' => 'Modules synced successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function syncModule($module_id, $module_name, $comment, $parent_id = 1, $folder_list = ':1:')
    {
        $dms_db = DB::connection('dms_db');
        $exists = $dms_db->table('tblfolders')
            ->where('module_id', $module_id)
            ->first();
        if (is_null($exists)) {
            $params = array(
                'name' => $module_name,
                'parent' => $parent_id,
                'folderList' => $folder_list,
                'comment' => $comment,
                'date' => strtotime(date('Y/m/d H:i:s')),
                'owner' => \Auth::user()->dms_id,
                'inheritAccess' => 1,
                'defaultAccess' => 1,
                'sequence' => 0,
                'module_id' => $module_id
            );
            $dms_id = $dms_db->table('tblfolders')
                ->insertGetId($params);
            DB::table('mis_dms_modules')
                ->where('id', $module_id)
                ->update(array('is_synced' => 1, 'dms_id' => $dms_id));
            $last = $dms_db->table('tblfolders')->latest('id')->first();
            $parent_dms_id = $last->id;
            $parent_folder_list = $last->folderList;
        } else {
            $dms_db->table('tblfolders')
                ->where('module_id', $module_id)
                ->update(array('name' => $module_name, 'comment' => $comment));
            $parent_dms_id = $exists->id;
            $parent_folder_list = $exists->folderList;
        }
        return array(
            'dms_id' => $parent_dms_id,
            'folder_list' => $parent_folder_list
        );
    }

    public function saveDmsConnectionConfigs(Request $req)
    {
        try {
            $filename = getcwd() . '/mis_dms/conf/settings.xml';
            $contents = File::get($filename);
            $resource = fopen($filename, "w");
            $contents = str_replace('kip', 'rono', $contents);
            fwrite($resource, $contents);
            fclose($resource);
            print_r($contents);
            exit();
            $res = array(
                'success' => false,
                'message' => 'Settings saved successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function previewOtherChecklistTemplate(Request $req)
    {
        $checklist_id = $req->input('checklist_id');
        $rows = $req->input('rows');
        if (!is_numeric($rows) || $rows < 1) {
            echo '<p style="color: red;">Invalid number of blank rows!!</p>';
            exit();
        }
        try {
            // ===========///////////////////////////////////////////////////////////////////==========//
            //header sections
            if ($checklist_id == 1) {//school based verification form
                $this->schoolBasedChecklist($checklist_id, $rows);
            } else if ($checklist_id == 2) {//community based verification form
                $this->communityBasedChecklist($checklist_id, $rows);
            } else if ($checklist_id == 3) {//out of school matching form
                $this->schoolMatchingChecklist($checklist_id, $rows);
            } else if ($checklist_id == 4) {
                echo '<p style="color: red">Locked. Use school based!!</p>';
                exit();
            } else if ($checklist_id == 5) {//exam classes school assignment form
                $this->schoolAssignmentChecklist($checklist_id, $rows);
            } else {
                echo '<p style="color: red;">Unknown checklist!!</p>';
                exit();
            }

        } catch (\Exception $e) {
            echo '<p style="color: red">' . $e->getMessage() . '!!</p>';
            exit();
        }
    }

    public function previewVerificationChecklistTemplate(Request $req)
    {
        $checklist_id = $req->input('checklist_id');
        $category = $req->input('category_id');
        $rows = $req->input('rows');
        if (!is_numeric($rows) || $rows < 1) {
            echo '<p style="color: red;">Invalid number of blank rows!!</p>';
            exit();
        }
        try {
            // ===========///////////////////////////////////////////////////////////////////==========//
            //header sections
            if ($category == 2) {//school based verification form
                $this->schoolBasedChecklist($checklist_id, $rows);
            } else if ($category == 1) {//community based verification form
                $this->communityBasedChecklist($checklist_id, $rows);
            } else {
                echo '<p style="color: red;">Unknown checklist!!</p>';
                exit();
            }

        } catch (\Exception $e) {
            echo '<p style="color: red">' . $e->getMessage() . '!!</p>';
            exit();
        }catch(\THrowable $t){
             echo '<p style="color: red">' . $t->getMessage() . '!!</p>';
            exit();
        }
    }

    public function schoolBasedChecklist($checklist_id, $rows)
    {
        //checklist details
        $checklist_details = DB::table('checklist_types')->where('id', $checklist_id)->first();
        $checklist_name = aes_decrypt($checklist_details->name);
        $checklist_ref = $checklist_details->reference;
        $checklist_code = aes_decrypt($checklist_details->code);
        $teamID = 'TEAM ID:';
        //todo::get questions/checklist items
        $quizs = DB::table('checklist_items')
            ->select('name', 'order_no')
            ->where('checklist_id', $checklist_id)
            ->orderBy('order_no')
            ->get();
        $quizs = convertStdClassObjToArray($quizs);
        $quizs = decryptArray($quizs);
        $numOfQuizes = count($quizs);
        PDF::SetTitle('Checklist');
        PDF::SetAutoPageBreak(TRUE, 2);//true sets it to on and 0 means margin is zero from sides
        PDF::SetTitle('Checklist');
        PDF::setMargins(2, 18, 2, true);
        PDF::AddPage('L');
        PDF::SetFont('helvetica', '', 7);
        //headers
        $image_path = '\resources\images\kgs-logo.png';
        PDF::Image(getcwd() . $image_path, 0, 26, 40, 30, 'PNG', '', 'T', false, 300, 'R', false, false, 0, false, false, false);
        PDF::SetY(7);
        PDF::Cell(0, 2, strtoupper($teamID), 0, 1, 'R');
        PDF::SetY(5);
        PDF::Cell(0, 5, 'MINISTRY OF EDUCATION - KEEPING GIRLS IN SCHOOL PROGRAMME', 0, 1, 'L');
        $checklist_header = '<p><b>' . $checklist_ref . '</b>: ' . strtoupper($checklist_name) . '</p>';
        PDF::writeHTMLCell(0, 10, 10, 10, $checklist_header, 0, 1, 0, true, 'L', true);
        PDF::Cell(200, 5, 'Section I', 0, 1, 'L');
        $school_table = '<table border="1" width="650" cellpadding="3">
                       <tbody>
                       <tr>
                           <td>Name of School</td>
                           <td></td>
                       </tr>
                       <tr>
                           <td>EMIS Code</td>
                           <td></td>
                       </tr>
                       <tr>
                           <td>Province of School</td>
                           <td></td>
                       </tr>
                       <tr>
                           <td>District of School</td>
                             <td></td>
                       </tr>
                       <tr>
                           <td>If district of school is incorrect, please write correct district name</td>
                           <td></td>
                       </tr>
                       <tr>
                           <td>Name of head teacher filling this form</td>
                           <td></td>
                       </tr>
                       <tr>
                           <td>Contact number of head teacher</td>
                           <td></td>
                       </tr>
                       </tbody></tbody>
                       </table>';
        PDF::writeHTML($school_table, true, false, false, false, 'L');
        $htmlTable = '
              <style>
                          table {
                                border-collapse: collapse;
                                white-space:nowrap;
                          }

                          table, th, td {
                                border: 1px solid black;
                          }
                          </style>
                          
               <table border="1" cellpadding="2" align="center">
			   <thead>
			   <tr><td colspan="7">Section II</td><td colspan="' . $numOfQuizes . '">Section III</td></tr>
			    <tr>
			    <td colspan="7"></td>
			    <td colspan="' . $numOfQuizes . '">Note: Please fill with assistance from school/head teacher in school</td>
			    </tr>
				<tr>
		  			<td> Beneficiary ID</td>
					<td>Home District of Girl</td>
					<td>Girl First Name</td>
					<td>Girl Surname</td>
					<td>Grade of Learner(from DSW data)</td>
					<td>HH Head First Name</td>
					<td>HH Head Last Name</td>';
        foreach ($quizs as $quiz) {
            $header = 'Q' . $quiz['order_no'] . '. ' . $quiz['name'];
            $htmlTable .= '<th>' . $header . '</th>';
        }
        $htmlTable .= '</tr></thead><tbody>';
        for ($j = 1; $j <= $rows; $j++) {
            $htmlTable .= '<tr>
                             <td></td>
                             <td></td>
                             <td></td>
                             <td></td>
                             <td></td>
                             <td></td>
                             <td></td>';
            for ($i = 1; $i <= $numOfQuizes; $i++) {
                $htmlTable .= '<td></td>';
            }
            $htmlTable .= '</tr>';
        }
        $htmlTable .= '</tbody></table>';
        PDF::writeHTML($htmlTable);
        PDF::Output($checklist_code . '_template.pdf', 'I');
    }

    public function communityBasedChecklist($checklist_id, $rows)
    {
        //checklist details
        $checklist_details = DB::table('checklist_types')->where('id', $checklist_id)->first();
        $checklist_name = aes_decrypt($checklist_details->name);
        $checklist_ref = $checklist_details->reference;
        $checklist_code = aes_decrypt($checklist_details->code);
        $teamID = 'TEAM ID:';
        //todo::get questions/checklist items
        $quizs = DB::table('checklist_items')
            ->select('name', 'order_no')
            ->where('checklist_id', $checklist_id)
            ->orderBy('order_no')
            ->get();
        $quizs = convertStdClassObjToArray($quizs);
        $quizs = decryptArray($quizs);
        $numOfQuizes = count($quizs);
        PDF::SetTitle('Checklist');
        PDF::SetAutoPageBreak(TRUE, 2);//true sets it to on and 0 means margin is zero from sides
        PDF::SetTitle('Checklist');
        PDF::setMargins(2, 18, 2, true);
        PDF::AddPage('L');
        PDF::SetFont('helvetica', '', 7);
        //headers
        $image_path = '\resources\images\kgs-logo.png';
        PDF::Image(getcwd() . $image_path, 0, 15, 30, 20, 'PNG', '', 'T', false, 300, 'R', false, false, 0, false, false, false);
        PDF::SetY(7);
        PDF::Cell(0, 2, strtoupper($teamID), 0, 1, 'R');
        PDF::SetY(2);
        PDF::Cell(0, 5, 'MINISTRY OF EDUCATION - KEEPING GIRLS IN SCHOOL PROGRAMME', 0, 1, 'L');
        $checklist_header = '<p><b>' . $checklist_ref . '</b>: ' . strtoupper($checklist_name) . '</p>';
        PDF::writeHTMLCell(0, 10, 10, 3, $checklist_header, 0, 1, 0, true, 'R', true);
        PDF::SetY(7);
        PDF::Cell(200, 5, 'Section I', 0, 1, 'L');
        $cwac_table = '<table border="1" width="650" cellpadding="3">
                       <tbody>
                       <tr>
                           <td>Name of CWAC</td>
                           <td></td>
                       </tr>
                       <tr>
                           <td>Province of CWAC</td>
                           <td></td>
                       </tr>
                       <tr>
                           <td>District of CWAC</td>
                             <td></td>
                       </tr>
                       <tr>
                           <td>If district of CWAC is incorrect, please write correct district name</td>
                           <td></td>
                       </tr>
                       <tr>
                           <td>CWAC Contact person</td>
                           <td></td>
                       </tr>
                       <tr>
                           <td>CWAC Contact person Phone No</td>
                           <td></td>
                       </tr>
                       </tbody></tbody>
                       </table>';
        PDF::writeHTML($cwac_table, true, false, false, false, 'L');
        $htmlTable = '
              <style>
                          table {
                                border-collapse: collapse;
                                white-space:nowrap;
                          }

                          table, th, td {
                                border: 1px solid black;
                          }
                          </style>
                          
               <table border="1" cellpadding="2" align="center">
			   <thead>
			   <tr><td colspan="7">Section II</td><td colspan="' . $numOfQuizes . '">Section III</td></tr>
			    <tr>
			    <td colspan="7"></td>
			    <td colspan="' . $numOfQuizes . '">Note: Please fill with assistance from CWAC contact person</td>
			    </tr>
				<tr>
		  			<td> Beneficiary ID</td>
					<td>Home District of Girl</td>
					<td>Girl First Name</td>
					<td>Girl Surname</td>
					<td>Grade of Learner(from DSW data)</td>
					<td>HH Head First Name</td>
					<td>HH Head Last Name</td>';
        PDF::SetFont('helvetica', '', 6);
        foreach ($quizs as $quiz) {
            $header = 'Q' . $quiz['order_no'] . '. ' . $quiz['name'];
            $htmlTable .= '<th>' . $header . '</th>';
        }
        $htmlTable .= '</tr></thead><tbody>';
        for ($j = 1; $j <= $rows; $j++) {
            $htmlTable .= '<tr>
                             <td></td>
                             <td></td>
                             <td></td>
                             <td></td>
                             <td></td>
                             <td></td>
                             <td></td>';
            for ($i = 1; $i <= $numOfQuizes; $i++) {
                $htmlTable .= '<td></td>';
            }
            $htmlTable .= '</tr>';
        }
        $htmlTable .= '</tbody></table>';
        PDF::writeHTML($htmlTable);
        PDF::Output($checklist_code . '_template.pdf', 'I');
    }

    public function schoolMatchingChecklist($checklist_id, $rows)
    {
        //checklist details
        $checklist_details = DB::table('checklist_types')->where('id', $checklist_id)->first();
        $checklist_name = aes_decrypt($checklist_details->name);
        $checklist_ref = $checklist_details->reference;
        $checklist_code = aes_decrypt($checklist_details->code);
        $teamID = 'TEAM ID:';
        //todo::get questions/checklist items
        $quizs = DB::table('checklist_items')
            ->select('name', 'order_no')
            ->where('checklist_id', $checklist_id)
            ->orderBy('order_no')
            ->get();
        $quizs = convertStdClassObjToArray($quizs);
        $quizs = decryptArray($quizs);
        $numOfQuizes = count($quizs);

        PDF::SetTitle('Matching Form');
        PDF::SetAutoPageBreak(TRUE, 2);//true sets it to on and 0 means margin is zero from sides
        PDF::SetTitle('Checklist');
        PDF::setMargins(10, 10, 10, true);

        PDF::AddPage('L');
        PDF::SetFont('times', '', 7);
        //headers
        $image_path = '\resources\images\kgs-logo.png';
        PDF::Image(getcwd() . $image_path, 0, 9, 40, 30, 'PNG', '', 'T', false, 300, 'R', false, false, 0, false, false, false);
        PDF::SetY(5);
        PDF::Cell(0, 5, 'MINISTRY OF EDUCATION - KEEPING GIRLS IN SCHOOL PROGRAMME', 0, 1, 'L');
        $checklist_header = '<p><b>' . $checklist_ref . '</b>: ' . strtoupper($checklist_name) . '</p>';
        PDF::writeHTMLCell(0, 10, 10, 10, $checklist_header, 0, 1, 0, true, 'L', true);
        PDF::SetY(15);
        PDF::Cell(200, 5, 'Section I', 0, 1, 'L');
        $district_table = '<table border="1" width="650" cellpadding="3">
                       <tbody>
                       <tr>
                           <td>Name of District</td>
                           <td></td>
                       </tr>
                       <tr>
                           <td>Name of DEBS Officer filling this form</td>
                           <td></td>
                       </tr>
                       <tr>
                           <td>Contact Number of DEBS Officer</td>
                           <td><b></b></td>
                       </tr>
                       </tbody></tbody>
                       </table>';
        PDF::writeHTML($district_table, true, false, false, false, 'L');

        $htmlTable = '
              <style>
                          table {
                                border-collapse: collapse;
                                white-space:nowrap;
                          }

                          table, th, td {
                                border: 1px solid black;
                          }
                          </style>
                          
               <table border="1" cellpadding="2" align="center">
			   <thead>
			   <tr><td colspan="7">Section II</td><td colspan="' . $numOfQuizes . '">Section III</td></tr>
			    <tr>
			    <td colspan="7"></td>
			     <td colspan="' . $numOfQuizes . '">Note: This "Matching" section needs to be filled by the DEBS</td>
			    </tr>
				<tr>
		  			<td> Beneficiary ID</td>
					<td>Home District of Girl</td>
					<td>Girl First Name</td>
					<td>Girl Surname</td>
					<td>Grade of Learner(from DSW data)</td>
					<td>HH Head First Name</td>
					<td>HH Head Last Name</td>';
        foreach ($quizs as $quiz) {
            $header = 'Q' . $quiz['order_no'] . '. ' . $quiz['name'];
            $htmlTable .= '<th>' . $header . '</th>';
        }
        $htmlTable .= '</tr></thead><tbody>';
        for ($j = 1; $j <= $rows; $j++) {
            $htmlTable .= '<tr>
                             <td></td>
                             <td></td>
                             <td></td>
                             <td></td>
                             <td></td>
                             <td></td>
                             <td></td>';
            for ($i = 1; $i <= $numOfQuizes; $i++) {
                $htmlTable .= '<td></td>';
            }
            $htmlTable .= '</tr>';
        }
        $htmlTable .= '</tbody></table>';
        PDF::writeHTML($htmlTable);
        PDF::Output($checklist_code . '_template.pdf', 'I');
    }

    public function schoolAssignmentChecklist($checklist_id, $rows)
    {
        //checklist details
        $checklist_details = DB::table('checklist_types')->where('id', $checklist_id)->first();
        $checklist_name = aes_decrypt($checklist_details->name);
        $checklist_ref = $checklist_details->reference;
        $checklist_code = aes_decrypt($checklist_details->code);
        $teamID = 'TEAM ID:';
        //todo::get questions/checklist items
        $quizs = DB::table('checklist_items')
            ->select('name', 'order_no')
            ->where('checklist_id', $checklist_id)
            ->orderBy('order_no')
            ->get();
        $quizs = convertStdClassObjToArray($quizs);
        $quizs = decryptArray($quizs);
        $numOfQuizes = count($quizs);
        PDF::SetTitle('Checklist');
        PDF::SetAutoPageBreak(TRUE, 2);//true sets it to on and 0 means margin is zero from sides
        PDF::SetTitle('Checklist');
        PDF::setMargins(10, 18, 10, true);
        PDF::AddPage('L');
        PDF::SetFont('helvetica', '', 7);
        //headers
        $image_path = '\resources\images\kgs-logo.png';
        PDF::Image(getcwd() . $image_path, 0, 26, 40, 30, 'PNG', '', 'T', false, 300, 'R', false, false, 0, false, false, false);
        PDF::SetY(7);
        PDF::Cell(0, 2, strtoupper($teamID), 0, 1, 'R');
        PDF::SetY(5);
        PDF::Cell(0, 5, 'MINISTRY OF EDUCATION - KEEPING GIRLS IN SCHOOL PROGRAMME', 0, 1, 'L');
        $checklist_header = '<p><b>' . $checklist_ref . '</b>: ' . strtoupper($checklist_name) . '</p>';
        PDF::writeHTMLCell(0, 10, 10, 10, $checklist_header, 0, 1, 0, true, 'L', true);
        PDF::Cell(200, 5, 'Section I', 0, 1, 'L');
        $school_table = '<table border="1" width="650" cellpadding="3">
                       <tbody>
                       <tr>
                           <td>Name of School</td>
                           <td></td>
                       </tr>
                       <tr>
                           <td>EMIS Code</td>
                           <td></td>
                       </tr>
                       <tr>
                           <td>Province of School</td>
                           <td></td>
                       </tr>
                       <tr>
                           <td>District of School</td>
                             <td></td>
                       </tr>               
                       <tr>
                           <td>School contact person</td>
                           <td></td>
                       </tr>             
                       </tbody></tbody>
                       </table>';
        PDF::writeHTML($school_table, true, false, false, false, 'L');
        $htmlTable = '
              <style>
                          table {
                                border-collapse: collapse;
                                white-space:nowrap;
                          }

                          table, th, td {
                                border: 1px solid black;
                          }
                          </style>
                          
               <table border="1" cellpadding="2" align="center">
			   <thead>
			   <tr><td colspan="7">Section II</td><td colspan="' . $numOfQuizes . '">Section III</td></tr>
			    <tr>
			    <td colspan="7"></td>
			    <td colspan="' . $numOfQuizes . '">Note: This "Assignment" section needs to be filled by the PIU</td>
			    </tr>
				<tr>
		  			<td> Beneficiary ID</td>
					<td>Home District of Girl</td>
					<td>Girl First Name</td>
					<td>Girl Surname</td>
					<td>Grade of Learner(from DSW data)</td>
					<td>HH Head First Name</td>
					<td>HH Head Last Name</td>';
        foreach ($quizs as $quiz) {
            $header = 'Q' . $quiz['order_no'] . '. ' . $quiz['name'];
            $htmlTable .= '<th>' . $header . '</th>';
        }
        $htmlTable .= '</tr></thead><tbody>';
        for ($j = 1; $j <= $rows; $j++) {
            $htmlTable .= '<tr>
                             <td></td>
                             <td></td>
                             <td></td>
                             <td></td>
                             <td></td>
                             <td></td>
                             <td></td>';
            for ($i = 1; $i <= $numOfQuizes; $i++) {
                $htmlTable .= '<td></td>';
            }
            $htmlTable .= '</tr>';
        }
        $htmlTable .= '</tbody></table>';
        PDF::writeHTML($htmlTable);
        PDF::Output($checklist_code . '_template.pdf', 'I');
    }

    public function markPromotionMonth(Request $req)
    {
        $month_id = $req->input('id');
        $user_id = \Auth::user()->id;
        try {
            $where = array(
                'id' => $month_id
            );
            $data = array(
                'is_promotion' => 1
            );
            $prev_data = getPreviousRecords('months', $where);
            updateRecord('months', $prev_data, $where, $data, $user_id);
            DB::table('months')
                ->where('id', '<>', $month_id)
                ->update(array('is_promotion' => 0));
            $res = array(
                'success' => true,
                'message' => 'Update made successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getPromotionMonth()
    {
        try {
            $det = DB::table('months')
                ->where('is_promotion', 1)
                ->first();
            if (is_null($det)) {
                $res = array(
                    'success' => false,
                    'message' => 'Promotion month not set!!'
                );
            } else {
                $res = array(
                    'success' => true,
                    'month_id' => $det->id,
                    'message' => 'Month set'
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

    public function getTemplateLastTabIndex(Request $req)
    {
        $template_id = $req->input('template_id');
        $stdTempID = getStdTemplateId();
        try {
            $count = DB::table('template_fields')
                ->whereIn('temp_id', array($template_id, $stdTempID))
                ->count();
            $tabindex = $count;
            $res = array(
                'success' => true,
                'tabindex' => $tabindex,
                'message' => 'Last TabIndex'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getSchoolFeesAmendmentsSetup(Request $req)
    {
        try {
            $qry = DB::table('school_grades')
                ->where('id', '>', 7);
            $school_grades = $qry->get();
            $enrollments = getSchoolenrollments();
            $combined_data = array();
            $grades_data = array();
            $results = array();
            foreach ($school_grades as $school_grade) {
                $grade_id = $school_grade->id;
                $grades_data['school_grade'] = $grade_id;
                $school_status_data = array();
                foreach ($enrollments as $enrollment) {
                    $sch_status_id = $enrollment->school_enrollment_id;
                    $where = array(
                        'grade_id' => $grade_id,
                        'school_status_id' => $sch_status_id
                    );
                    $allowed_percentage_data = DB::table('fees_increment_range as t1')
                        ->leftjoin('users as t2', 't1.created_by', '=', 't2.id')
                        ->leftjoin('users as t3', 't1.updated_by', '=', 't3.id')
                        ->select(DB::raw("t1.allowed_percentage,CONCAT_WS(' ',decrypt(t2.first_name),decrypt(t2.last_name)) as creator_names,CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as updator_names"))
                        ->where($where)
                        ->first();
                    if (!is_null($allowed_percentage_data)) {
                        $school_status_data['.' . $sch_status_id] = $allowed_percentage_data->allowed_percentage;
                        $school_status_data['creator_names'] = $allowed_percentage_data->creator_names;
                        $school_status_data['updator_names'] = $allowed_percentage_data->updator_names;
                    } else {
                        $school_status_data['.' . $sch_status_id] = '';
                    }
                    $combined_data = array_merge($grades_data, $school_status_data);
                }
                $results[] = $combined_data;
            }
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

    public function saveSchoolFeesAmendmentsSetup(Request $req)
    {
        try {
            $user_id = \Auth::user()->id;
            $postdata = file_get_contents("php://input");
            $data = json_decode($postdata);
            if (is_array($data)) {
                $data = json_decode($postdata, true);
            } else {
                $data = array();
                $data[] = json_decode($postdata, true);
            }
            foreach ($data as $key => $value) {
                $grade_id = $value['school_grade'];
                if (isset($value['.1'])) {
                    $day_percentage = $value['.1'];
                    $this->updateSchoolFeesChangeRange($grade_id, 1, $day_percentage, $user_id);
                }
                if (isset($value['.2'])) {
                    $boarder_percentage = $value['.2'];
                    $this->updateSchoolFeesChangeRange($grade_id, 2, $boarder_percentage, $user_id);
                }
                if (isset($value['.3'])) {
                    $weekly_percentage = $value['.3'];
                    $this->updateSchoolFeesChangeRange($grade_id, 3, $weekly_percentage, $user_id);
                }
            }
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

    public function updateSchoolFeesChangeRange($grade_id, $sch_status_id, $allowed_percentage, $user_id)
    {
        $where = array(
            'grade_id' => $grade_id,
            'school_status_id' => $sch_status_id
        );
        $checker = DB::table('fees_increment_range')
            ->where($where)
            ->count();
        $params = array(
            'allowed_percentage' => $allowed_percentage
        );
        if ($checker > 0) {
            $params['updated_by'] = $user_id;
            DB::table('fees_increment_range')
                ->where($where)
                ->update($params);
        } else {
            $params['grade_id'] = $grade_id;
            $params['school_status_id'] = $sch_status_id;
            $params['created_by'] = $user_id;
            DB::table('fees_increment_range')
                ->insert($params);
        }
    }

    public function getWeeklyBordersTopUp(Request $req)
    {
        try {
            $qry = DB::table('weekly_borders_fees as t1')
                ->leftJoin('users as t2', 't1.created_by', '=', 't2.id')
                ->leftJoin('users as t3', 't1.updated_by', '=', 't3.id')
                ->select(DB::raw("t1.*,CONCAT_WS(' ',decrypt(t2.first_name),decrypt(t2.last_name)) as creator_names,
                                  CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as updator_names"));
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

        public function saveWeeklyBordersTopUp(Request $req)
    {
        try {
            $user_id = \Auth::user()->id;
            $postdata = file_get_contents("php://input");
            $data = json_decode($postdata);
            if (is_array($data)) {
                $data = json_decode($postdata, true);
            } else {
                $data = array();
                $data[] = json_decode($postdata, true);
            }
            foreach ($data as $key => $value) {
                $id = $value['id'];
                $topUpAmount = $value['topup_amount'];
                $maxExamFees = $value['max_exam_fees'];
                $where = array(
                    'id' => $id
                );
                $data = array(
                    'topup_amount' => $topUpAmount,
                    'max_exam_fees'=>$maxExamFees,
                    'updated_by' => $user_id
                );
                $prev_data = getPreviousRecords('weekly_borders_fees', $where);
                updateRecord('weekly_borders_fees', $prev_data, $where, $data, $user_id);
            }
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

    public function updateEBatchesPaymentSetup(Request $request)
    {
        DB::beginTransaction();
        try {
            $user_id = \Auth::user()->id;
            $batch_id = $request->input('batch_id');
            $change_request_id = $request->input('change_request_id');
            $remarks = $request->input('remarks');
            $table_name = $request->input('table_name');
            $params = array(
                'batch_id' => $batch_id,
                'change_request_id' => $change_request_id,
                'remarks' => $remarks,
                'from_date' => Carbon::now(),
                'from_author_id' => $user_id,
                'is_active' => 1
            );
            //checks
            $where1 = array(
                'batch_id' => $batch_id,
                'change_request_id' => $change_request_id,
                'is_active' => 1
            );
            if (DB::table($table_name)->where($where1)->count() > 0) {
                $res = array(
                    'success' => false,
                    'message' => 'Status exists!!'
                );
                return response()->json($res);
            }
            $last_id = DB::table($table_name)
                ->where('batch_id', $batch_id)
                ->max('id');
            DB::table($table_name)
                ->where('batch_id', $batch_id)
                ->update(array('is_active' => 0));
            $res = insertRecord($table_name, $params, $user_id);
            DB::table($table_name)
                ->where('id', $last_id)
                ->update(array('to_date' => Carbon::now(), 'to_author_id' => $user_id));
            $this->updateBeneficiariesPaymentEligibility($batch_id, $change_request_id);
            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            DB::rollBack();
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return response()->json($res);
    }

    public function updateBeneficiariesPaymentEligibility($batch_id, $eligibility)
    {
        DB::table('beneficiary_information')
            ->where('batch_id', $batch_id)
            ->where('beneficiary_status', 4)
            ->update(array('payment_eligible' => $eligibility));
    }

    public function getEBatchesPaymentSetup()
    {
        try {
            $qry = DB::table('ebatchespaymentsetup as t1')
                ->join('batch_info as t2', 't1.batch_id', '=', 't2.id')
                ->join('users as t3', 't1.from_author_id', '=', 't3.id')
                ->leftJoin('users as t4', 't1.to_author_id', '=', 't4.id')
                ->join('paymentsetupchanges as t5', 't1.change_request_id', '=', 't5.id')
                ->select(DB::raw("t1.*,t2.batch_no,CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as from_author,
                CONCAT_WS(' ',decrypt(t4.first_name),decrypt(t4.last_name)) as to_author,t5.name as change_request"))
                ->orderBy('t1.id', 'ASC');
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

    //Added by Frank
    public function saveConfigModuleCommonData(Request $req)
    {
        try {
            $user_id = \Auth::user()->id;
            $post_data = $req->all();
            $table_name = $post_data['table_name'];
            $id = $post_data['id'];
            $skip = $post_data['skip'];
            $skipArray = explode(",", $skip);
            $res = array();
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

     public function getConfigModuleParamFromTable(Request $request)
    {
        try {
            $table_name = $request->input('table_name');
            $param_setup_id = $request->input('param_setup_id');
            $filters = $request->input('filters');
            $filters = (array)json_decode($filters);
            $primary_table = '';
            $qry = DB::table($table_name);
            if (count((array)$filters) > 0) {
                $qry->where($filters);
            }
            if (validateisNumeric($param_setup_id)) {
                $primary_table = DB::table('parameters_setup')
                    ->where('id', $param_setup_id)
                    ->value('primary_table');
                $qry->orderBy('tabindex');
            }
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'table_name' => $primary_table,
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

    public function getProcessStatuses(Request $request)
    {
        $process_id = $request->input('process_id');
        try {
            $qry = DB::table('process_statuses as t1')
                ->join('system_statuses as t2', 't1.status_id', '=', 't2.id')
                ->join('wf_kgsprocesses as t3', 't1.process_id', '=', 't3.id')
                ->select('t1.*', 't2.name as system_status', 't3.name as process');
            if (validateisNumeric($process_id)) {
                $qry->where('t1.process_id', $process_id);
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

    public function exportConfigRecords(Request $request)
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

    function utf8ize( $mixed ) {
        if (is_array($mixed)) {
            foreach ($mixed as $key => $value) {
                $mixed[$key] = utf8ize($value);
            }
        } elseif (is_string($mixed)) {
            return mb_convert_encoding($mixed, "UTF-8", "UTF-8");
        }
        return $mixed;
    }

    function getParameterGridResultSet(Request $request)
    {
        try {
            $param_setup_id = $request->input('param_setup_id');           
            $qry = DB::table('parameters_setup')
                ->where('id', $param_setup_id);
            $sql_query = $qry->value('sql_query');
            $results = DB::select($sql_query);
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
        return response()->json($this->utf8ize($res));
    }

    function getParameterComboResultSet(Request $request)
    {
        try {
            $sql_query = $request->input('sql_query');
            $results = DB::select($sql_query);
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
        return response()->json($this->utf8ize($res));
    }
    
    function getParameterGridCols(Request $request)
    {
        try {
            $param_setup_id = $request->input('param_setup_id');
            $return = array();
            $qry = DB::table('parameters_setup_properties')
                ->where('param_setup_id', $param_setup_id)
                ->orderBy('tabindex');
            $data = $qry->get();
            foreach ($data as $datum) {
                $return[] = array(
                    'text' => $datum->name,
                    'dataIndex' => $datum->dataindex,
                    'flex' => 1
                );
            }
            $res = array(
                'success' => true,
                'columns' => $return
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
}
