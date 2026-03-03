<?php

namespace App\Modules\IdentificationEnrollment\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Imports\BatchImport;
use App\Imports\BatchImportCustomTemplate;
use App\Imports\DummyDatasetImport;
use App\Modules\identificationEnrollment\Entities\BatchInfo;
use App\Modules\identificationEnrollment\Entities\BeneficiaryMaster;
use App\SerialTracker;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

class IdentificationEnrollmentController extends BaseController
{

    public $sheetMetaData,
        $currentCounter,
        $currentRow,
        $currentRowE,
        $currentCounterE,
        $rowErrorCount = 0,
        $totalErrorCount = 0,
        $rowErrorCountE = 0,
        $totalErrorCountE = 0;

    public function __construct()
    {
        parent::__construct();
        ini_set('display_errors', 1);
    }

    public function index()
    {
        return view('identificationenrollment::index');
    }

    public function saveCommonData(Request $req)
    {
        $user_id = Auth::user()->id;
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
        unset($post_data['contact_person_phone']);
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

    public function uploadNewBatch1(Request $req)
    {
        /*ini_set('display_errors', 1);
        ini_set('memory_limit', '640M');
        ini_set('max_execution_time', 1000);*/
        ini_set('memory_limit', '-1');
        $max_upload = Config('constants.max_excel_upload');
        $res = array();
        $batch_id = $req->input('batch_id');
        $template_id = $req->input('template_id');
        $comment = $req->input('comment');
        $parent_folder_id = getParentFolderID('batch_info', $batch_id);
        $folder_id = getSubModuleFolderID($parent_folder_id, 5);
        if ($req->hasFile('upload_file')) {
            $origFileName = $req->file('upload_file')->getClientOriginalName();
            $path = $req->file('upload_file')->getRealPath();
            //check file extensions for only excel and csv files
            if (validateExcelUpload($origFileName)) {
                $data = Excel::load($path, function ($reader) {
                    $this->sheetMetaData = $reader->first();
                })->get();
                if ($data->count() > $max_upload) {
                    $res = array(
                        'success' => false,
                        'message' => 'The uploaded dataset exceeds the maximum allowed ' . $max_upload . ' records!!'
                    );
                    return response()->json($res);
                }
                addDocument($origFileName, $comment, 'upload_file', $folder_id, $versioncomment = '');
                //check if uploaded file has some data
                if (!empty($data) && $data->count()) {
                    $colsNo = $this->sheetMetaData->count();
                    $colHeaders = $this->sheetMetaData->keys()->toArray();
                    //start transaction
                    DB::transaction(function () use ($template_id, $data, $colHeaders, $batch_id, &$res) {
                        try {
                            //delete all in the following tables
                            DB::table('beneficiary_master_info')->where('batch_id', $batch_id)->delete();
                            DB::table('beneficiary_additional_info')->where('batch_id', $batch_id)->delete();
                            //get std template ID
                            $stdTempID = getStdTemplateId();
                            $qry = DB::table('template_fields')->where('temp_id', $stdTempID);
                            $stdTemplateFields = $qry->get();
                            $stdTemplateFields = convertStdClassObjToArray($stdTemplateFields);
                            $stdTemplateFields = decryptArray($stdTemplateFields);
                            $values = array();
                            //check if this is a std template
                            if ($template_id != $stdTempID) {
                                try {
                                    $customTemplateFields = DB::table('template_fields')->where('temp_id', $template_id)->get();
                                    //insert into db one by one first those for std template
                                    foreach ($data as $key => $value) {
                                        foreach ($stdTemplateFields as $key => $stdTemplateField) {
                                            $values[$stdTemplateField['dataindex']] = $value->$colHeaders[$stdTemplateField['tabindex']];
                                            $values['batch_id'] = $batch_id;
                                        }
                                        $mainTempId = insertReturnID('beneficiary_master_info', $values);
                                        if (is_numeric($mainTempId)) {
                                            if (!is_null($customTemplateFields) && count($customTemplateFields) > 0) {
                                                foreach ($customTemplateFields as $customTemplateField) {
                                                    $params = array(
                                                        'main_temp_id' => $mainTempId,
                                                        'field_id' => $customTemplateField->id,
                                                        'value' => $value->$colHeaders[$customTemplateField->tabindex],
                                                        'batch_id' => $batch_id
                                                    );
                                                    DB::table('temp_additional_fields_values')->insert($params);
                                                }
                                            }
                                        }
                                    }
                                    DB::table('batch_info')
                                        ->where('id', $batch_id)
                                        ->update(array('error_checked' => 0));
                                    DB::table('importation_errors')
                                        ->where('batch_id', $batch_id)
                                        ->delete();
                                    $res = array(
                                        'success' => true,
                                        'message' => 'Data uploaded successfully!!'
                                    );
                                } catch (QueryException $e) {
                                    $res = array(
                                        'success' => false,
                                        'message' => $e->getMessage()
                                    );
                                }
                            } else {
                                //formulate the insert array for a std template
                                $insertValues = array();
                                foreach ($data as $key => $value) {
                                    $values = array();
                                    foreach ($stdTemplateFields as $key => $stdTemplateField) {
                                        $values[$stdTemplateField['dataindex']] = $value->$colHeaders[$stdTemplateField['tabindex']];
                                        $values['batch_id'] = $batch_id;
                                    }
                                    $insertValues[] = $values;
                                }
                                $size = 100;
                                $chunks = array_chunk($insertValues, $size);
                                $db_insert = '';
                                foreach ($chunks as $chunk) {
                                    $db_insert = DB::table('beneficiary_master_info')->insert($chunk);
                                }
                                //insert in db
                                // $db_insert = DB::table('beneficiary_master_info')->insert($insertValues);
                                if ($db_insert) {
                                    $res = array(
                                        'success' => true,
                                        'message' => 'Data uploaded successfully!!'
                                    );
                                } else {
                                    $res = array(
                                        'success' => false,
                                        'message' => 'Problem was encountered while uploading data. Please try again!!'
                                    );
                                }
                            }
                        } catch (\Exception $e) {
                            $res = array(
                                'success' => false,
                                'message' => $e->getMessage()
                            );
                        }

                    }, 5);//end of transaction
                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'The uploaded Excel file has no data. Check the file then upload again!!!!'
                    );
                }
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'Wrong file format, only .xls, .xlsx and .csv are allowed. Please upload a file in correct format!!'
                );
            }
        } else {
            $res = array(
                'success' => false,
                'message' => 'No file selected. Please select a file to upload'
            );
        }

        return response()->json($res);
    }

    public function uploadNewBatch(Request $req)
    {
        try {
            ini_set('memory_limit', '-1');
            $res = array();
            $batch_id = $req->input('batch_id');
            $template_id = $req->input('template_id');
            $comment = $req->input('comment');
            $parent_folder_id = getParentFolderID('batch_info', $batch_id);
            $folder_id = getSubModuleFolderID($parent_folder_id, 5);
            if ($req->hasFile('upload_file')) {
                $origFileName = $req->file('upload_file')->getClientOriginalName();
                //check file extensions for only excel and csv files
                if (validateExcelUpload($origFileName)) {
                    //start transaction
                    DB::transaction(function () use ($template_id, $batch_id, &$res, $origFileName, $comment, $folder_id) {
                        //delete all in the following tables
                        DB::table('beneficiary_master_info')->where('batch_id', $batch_id)->delete();
                        DB::table('beneficiary_additional_info')->where('batch_id', $batch_id)->delete();
                        //get std template ID
                        $stdTempID = getStdTemplateId();
                        $qry = DB::table('template_fields')->where('temp_id', $stdTempID);
                        $stdTemplateFields = $qry->get();
                        $stdTemplateFields = convertStdClassObjToArray($stdTemplateFields);
                        if (($template_id == $stdTempID) || ($template_id === $stdTempID)) {
                            Excel::import(new BatchImport($batch_id, $stdTemplateFields), request()->file('upload_file'));
                        } else {
                            $customTemplateFields = DB::table('template_fields')->where('temp_id', $template_id)->get();
                            Excel::import(new BatchImportCustomTemplate($batch_id, $stdTemplateFields, $customTemplateFields), request()->file('upload_file'));
                        }
                        addDocument($origFileName, $comment, 'upload_file', $folder_id, $versioncomment = '');
                        $res = array(
                            'success' => true,
                            'message' => 'Data uploaded successfully!!'
                        );
                    }, 5);//end of transaction
                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'Wrong file format, only .xls, .xlsx and .csv are allowed. Please upload a file in correct format!!'
                    );
                }
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'No file selected. Please select a file to upload'
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

    public function uploadDummyDataset(Request $req)
    {
        try {
            ini_set('memory_limit', '-1');
            $dummy_type = $req->input('dummy_type');
            $clear_flag = $req->input('clear_flag');
            $res = array();
            if ($req->hasFile('upload_file')) {
                $origFileName = $req->file('upload_file')->getClientOriginalName();
                //check file extensions for only excel and csv files
                if (validateExcelUpload($origFileName)) {
                    //start transaction
                    DB::transaction(function () use (&$res, $origFileName, $dummy_type, $clear_flag) {
                        //delete all in the following tables
                        if ($dummy_type == 1 && $clear_flag == 1) {//beneficiary
                            DB::table('dummy_beneficiary_master_info')->delete();
                        } else if ($dummy_type == 2 && $clear_flag == 1) {//household
                            DB::table('dummy_household_profile')->delete();
                        } else if ($dummy_type == 4 && $clear_flag == 1) {//beneficiary additional
                            DB::table('dummy_beneficiary_master_additional')->delete();
                        }
                        Excel::import(new DummyDatasetImport($dummy_type), request()->file('upload_file'));
                        $res = array(
                            'success' => true,
                            'message' => 'Data uploaded successfully!!'
                        );
                    }, 5);//end of transaction
                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'Wrong file format, only .xls, .xlsx and .csv are allowed. Please upload a file in correct format!!'
                    );
                }
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'No file selected. Please select a file to upload'
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

    public function getCurrentImports(Request $req)
    {
        $limit = $req->input('limit');
        $start = $req->input('start');
        $per_page = ($limit - $start);
        $batch_id = $req->input('batch_id');
        $template_id = $req->input('template_id');
        try {
            $masterInfo = new BeneficiaryMaster();
            $total = $masterInfo->where('batch_id', $batch_id)->count();
            $imports = $masterInfo->where('batch_id', $batch_id)->offset($start)->limit($limit)->get();

            $template_fields = DB::table('template_fields')->where('temp_id', $template_id)->get();
            $template_fields = convertStdClassObjToArray($template_fields);
            $stdTempID = getStdTemplateId();

            //std templates fields
            $std_template_fields = DB::table('template_fields')->where('temp_id', $stdTempID)->get();
            $std_template_fields = convertStdClassObjToArray($std_template_fields);
            //validations
            $row = 0;
            $count = 1;
            $customTemplateErrors = '';
            $error_log = array();
            foreach ($imports as $key => $import) {
                $row++;
                $this->currentCounter = $count;
                $this->currentRow = $row;
                $stdTemplateErrors = '';
                foreach ($std_template_fields as $key2 => $std_template_field) {
                    $dataindex = aes_decrypt($std_template_field['dataindex']);
                    // $stdTemplateErrors .= $this->validateValue($import[$dataindex], $stdTempID, $std_template_field['tabindex'], $row);
                    $stdTemplateErrors .= $this->validateValue($import[$dataindex], $stdTempID, $std_template_field['tabindex'], $row);
                }
                // print_r($key2);exit();
                // if ($template_id != $stdTempID) {
                //     foreach ($template_fields as $key3 => $template_field) {
                //         $value = $this->getAdditionalTemplateFieldValue($import['id'], $template_field['id']);
                //         $tabindex = $template_field['tabindex'];
                //         $imports[$key][aes_decrypt($template_field['dataindex'])] = $value;
                //         $customTemplateErrors .= $this->validateValue($value, $template_id, $tabindex, $row);
                //     }
                // }
                if ($stdTemplateErrors != '' || $customTemplateErrors != '') {
                    $errorString = '<h3 style="color: red">Errors</h3>' . $stdTemplateErrors . $customTemplateErrors;
                } else {
                    $errorString = '<h3 style="color: green">No Errors</h3>';
                }
                $imports[$key]['error_counter'] = $this->rowErrorCount;
                $imports[$key]['error_log'] = $errorString;
            }
            $res = array(
                'success' => true,
                'totalCount' => $total,
                'imports' => $imports,
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
    //only specific standard template fields, to improve on speed
    public function validatedUploadedDataset(Request $req)
    {
        $per_page = 100;
        $batch_id = $req->input('batch_id');
        try {
            $qry = DB::table('beneficiary_master_info')
                ->where('batch_id', $batch_id);
            $imports = $qry->get();
            $stdTempID = getStdTemplateId();
            //std templates fields
            $std_template_fields = DB::table('template_fields')->where('temp_id', $stdTempID)->get();
            $std_template_fields = convertStdClassObjToArray($std_template_fields);
            //validations
            $row = 0;
            $count = 1;
            $error_log = array();
            foreach ($imports as $key => $import) {
                $row++;
                $this->currentCounterE = $count;
                $this->currentRowE = $row;
                $stdTemplateErrors = '';
                foreach ($std_template_fields as $key2 => $std_template_field) {
                    $dataindex = aes_decrypt($std_template_field['dataindex']);
                    $tabindex = aes_decrypt($std_template_field['tabindex']);
                    if ($tabindex == 0 || $tabindex == 1 || $tabindex == 2 || $tabindex == 3 || $tabindex == 4 || $tabindex == 5 || $tabindex == 15 || $tabindex == 21 || $tabindex == 22 || $tabindex == 23) {
                        $stdTemplateErrors .= $this->validateValue($import->$dataindex, $stdTempID, $std_template_field['tabindex'], $row);
                    }
                }
                $div_results = floor(($row / $per_page));
                $mod_results = ($row % $per_page);
                $page = $div_results;
                if ($mod_results > 0) {
                    $page = $div_results + 1;
                }
                if ($this->rowErrorCountE > 0) {
                    $error_log[] = array(
                        'row_number' => $row,
                        'page_number' => $page,
                        'sn' => $import->sn,
                        'girl_name' => $import->girl_fname . ' ' . $import->girl_lname,
                        'error_count' => $this->rowErrorCountE,
                        'batch_id' => $batch_id
                    );
                }
            }
            DB::table('importation_errors')
                ->where('batch_id', $batch_id)
                ->delete();
            DB::table('importation_errors')
                ->insert($error_log);
            //update total number of error
            DB::table('active_error_count')->delete();
            DB::table('active_error_count')->insert(array('error_counter' => $this->totalErrorCountE));
            DB::table('batch_info')
                ->where('id', $batch_id)
                ->update(array('error_checked' => 1));
            $res = array(
                'success' => true,
                'message' => 'Dataset validated, please make changes where necessary!!'
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
    //frank moded
	
    public function getAllBatchAssessmentData(Request $req)
    {
        $limit = $req->limit;
        $start = $req->start;
        $batch_id = $req->batch_id;
        $template_id = $req->template_id;
        //$active_batch_id = getActiveBatchID();
        //$batch_template_id = getActiveBatchTemplateID();
        $masterInfo = new BeneficiaryMaster();
        $total = $masterInfo->where('batch_id', $batch_id)->count();
        $batch_imports = $masterInfo->where('batch_id', $batch_id)->offset($start)->limit($limit)->get();
        //$batch_imports = DB::table('beneficiary_master_info')->where('batch_id', $batch_id)->get();//$tempImport->offset($start)->limit($limit)->get();
        $template_fields = DB::table('template_fields')->where('temp_id', $template_id)->get();
        $template_fields = convertStdClassObjToArray($template_fields);
        $stdTempID = getStdTemplateId();
        // if ($template_id != $stdTempID) {
        //     foreach ($batch_imports as $key => $batch_import) {
        //         // if ($template_id != $stdTempID) {
        //         foreach ($template_fields as $key3 => $template_field) {
        //             $value = $this->getAdditionalTemplateFieldValue($batch_import['id'], $template_field['id']);
        //             $batch_imports[$key][aes_decrypt($template_field['dataindex'])] = $value;
        //         }
        //         //}
        //     }
        // }
        $res = array(
            'totalCount' => $total,
            'result' => $batch_imports
        );
        return response()->json($res);
    }
	
    public function getAdditionalTemplateFieldValue($main_temp_id, $field_id)
    {
        $where = array(
            'main_temp_id' => $main_temp_id,
            'field_id' => $field_id
        );
        $val = DB::table('temp_additional_fields_values')->where($where)->value('value');
        return $val;
    }

    //frank
    // public function directmessagetooneaddressbook($subusername,$department,$usernamee, $key,$senderIdd,$addressbook,$message,$msgtype,$dlr)
    // {
    //     $this->usernamee = $usernamee;
    //     $this->key = $key;
    //     $this->senderIdd = $senderIdd;
    //     $this->message = $message;
    //     $this->msgtype = $msgtype;
    //     $this->dlr = $dlr;
    //     $this->subusername = $subusername;
    //     $this->department = $department;        
    //     if($subusername==''){
    //         $members=AddressBookcontact::where('unique_phonebooknumber',$addressbook)->where('name',$usernamee);
    //     }if($subusername!=''){
    //         $members=AddressBookcontact::where('unique_phonebooknumber',$addressbook)->where('name',$subusername);
    //     }
    //     $members->chunk(500, function ($details) {
    //         foreach( $details as $detail ) {
    //             set_time_limit(999999);
    //             $name1 = $detail->clientname;
    //             $phonenumber = $detail->phonenumber;
	// 			$firstnamename= strtok($name1, ' ');
    //             $afterfisrtspace =   substr($name1, strpos($name1, " ") + 1); 
    //             $middlename= strtok($afterfisrtspace, ' ');
    //             $lastname = substr(strrchr($name1, " "), 1);           
    //             if($lastname==$middlename){
    //               $name=$firstnamename.' '.$middlename;  
    //             }if($lastname!=$middlename){                
    //               if (count(explode(' ', $name1)) <= 1) {
    //                    $name=$name1;
    //              }if (count(explode(' ', $name1)) > 1) {                    
    //                   $name=$firstnamename.' '.$middlename.' '.$lastname;  
    //                 }
    //             }                
    //             $msg = $this->message;           
    //             if(Auth::user()->smsunits > 1){                                      
    //                 $username = $this->usernamee;
    //                 $Key = $this->key;
    //                 $senderId = $this->senderIdd;
    //                 $finalmessage = $this->settingmessage($name, $msg);;
    //                 $msgtype = $this->msgtype;
    //                 $dlr = $this->dlr;
    //                 $subusername=$this->subusername;
    //                 $department=$this->department;    
    //                 $postData = array(
    //                     'action' => 'compose',
    //                     'username' => $username,
    //                     'api_key' => $Key,
    //                     'sender' => $senderId,
    //                     'to' => $phonenumber,
    //                     'message' => $finalmessage,
    //                     'msgtype' => $msgtype,
    //                     'dlr' => $dlr,
    //                     'subusername' => $subusername,
    //                     'department' => $department,
    //                 );       
    //                 $ch = curl_init();
    //                 curl_setopt_array($ch, array(
    //                     CURLOPT_URL => $url,
    //                     CURLOPT_RETURNTRANSFER => true,
    //                     CURLOPT_POST => true,
    //                     CURLOPT_POSTFIELDS => $postData    
    //                 ));    
    //                 curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    //                 curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);    
    //                 $output = curl_exec($ch);    
    //                 if (curl_errno($ch)) {
    //                     // echo 'error:' . curl_error($ch);
    //                     $output = curl_error($ch);
    //                 }    
    //                 curl_close($ch);
    //             }
    //             //return $output;
    //         }
    //     });

    // }

    function validateValue($value, $template_id, $tabindex, $row)
    {
        $currentCounter = $this->currentCounter;
        //check if passed row == current row
        if ($currentCounter != $row) {
            $this->rowErrorCount = 0;
            $this->currentCounter = $row;

            $this->rowErrorCountE = 0;
            $this->currentCounterE = $row;
        }
        //$validationResults = 'Errors for Column Index ' . $tabindex;
        // $validationResults .= '<ul>';
        $validationResults = '';
        $where = array(
            'temp_id' => $template_id,
            'tabindex' => $tabindex
        );
        $template_fields = DB::table('template_fields')->where($where)->get();
        $template_fields = convertStdClassObjToArray($template_fields);
        //validations as follows
        /*
         * 1. type
         * 2. is parameterized param table
         * 3. is mandatory
         * 4. is code_name hyphened
         */
        foreach ($template_fields as $template_field) {
            $needs_validations = $template_field['needs_validations'];
            if ($needs_validations == 1 || $needs_validations == '1') {
                $validateType = validateType($value, $template_field['type']);
                if ($validateType !== true) {
                    $validationResults .= '<li>' . $validateType . '</li>';
                    $this->rowErrorCount++;
                    $this->totalErrorCount++;

                    $this->rowErrorCountE++;
                    $this->totalErrorCountE++;
                }
                if ($template_field['is_mandatory'] == 1) {
                    $validateIsMandatory = validateIsMandatory($value);
                    if ($validateIsMandatory !== true) {
                        $validationResults .= '<li>' . $validateIsMandatory . '</li>';
                        $this->rowErrorCount++;
                        $this->totalErrorCount++;

                        $this->rowErrorCountE++;
                        $this->totalErrorCountE++;
                    }
                }
                /*if ($template_field['is_code_name_hyphened'] == 1) {
                    $validateIsCodeHyphened = validateCodeNameHyphened($value);
                    if ($validateIsCodeHyphened !== true) {
                        $validationResults .= '<li>' . $validateIsCodeHyphened . '</li>';
                        $this->rowErrorCount++;
                        $this->totalErrorCount++;
                    }
                }*/
                /* if ($template_field['is_parameterised'] == 1) {
                     //get param table
                     $paramTable = $template_field['param_table'];
                     if ($paramTable == 1) {
                         $table = 'provinces';
                     } else if ($paramTable == 2) {
                         $table = 'districts';
                     } else if ($paramTable == 3) {
                         $table = 'constituencies';
                     } else if ($paramTable == 4) {
                         $table = 'wards';
                     } else if ($paramTable == 5) {
                         $table = 'acc';
                     } else if ($paramTable == 6) {
                         $table = 'cwac';
                     } else if ($paramTable == 7) {
                         $table = 'school_information';
                     }
                     if ($template_field['is_value_parameterised'] == 1) {
                         $flag = 1;
                     } else {
                         $flag = 2;
                     }
                     $validateIsParameterized = validateIsParameterized($table, $value, $flag);
                     if ($validateIsParameterized !== true) {
                         $validationResults .= '<li>' . $validateIsParameterized . '</li>';
                         $this->rowErrorCount++;
                         $this->totalErrorCount++;
                     }
                 }*/
            }
        }

        // $this->totalErrorCount = $this->rowErrorCount;
        // $validationResults .= '</ul>';
        if ($validationResults !== '') {
            $validationResults = 'Errors for column index ' . $tabindex . ' <ul>' . $validationResults . '</ul>';
        }
        return $validationResults;
    }

    function getErrorCount1(Request $req)
    {
        $template_id = $req->template_id;
        // $import_id = $req->import_id;
        $user_id = \Auth::user()->id;
        $res = array();
        DB::transaction(function () use ($user_id, $template_id, &$res) {
            try {
                $errorCounter = DB::table('active_error_count')->value('error_counter');
                if ($errorCounter == 0 || $errorCounter < 1) {
                    $year = date('Y');
                    $process_type_id = getProcessTypeID('batch number');//DB::table('serialized_processes')->where('name', 'like', '%batch number%')->value('id');
                    if (is_null($process_type_id) || $process_type_id == '') {
                        $res = array(
                            'success' => false,
                            'message' => 'Error=>The process ID was not found. Please contact system Admin for assistance!!'
                        );
                    } else {
                        $where = array(
                            'year' => $year,
                            'process_type' => $process_type_id
                        );
                        $serial_num_tracker = new SerialTracker();
                        $serial_track = $serial_num_tracker->where($where)->first();
                        if ($serial_track == '' || is_null($serial_track)) {
                            $current_serial_id = 1;
                            $serial_num_tracker->year = $year;
                            $serial_num_tracker->process_type = $process_type_id;
                            $serial_num_tracker->created_by = $user_id;
                            $serial_num_tracker->last_serial_no = $current_serial_id;
                            try {
                                $serial_num_tracker->save();
                            } catch (\Exception $e) {
                                $res = array(
                                    'success' => false,
                                    'option' => false,
                                    'message' => $e->getMessage()
                                );
                                return response()->json($res);
                            }
                        } else {
                            $last_serial_id = $serial_track->last_serial_no;
                            $current_serial_id = $last_serial_id + 1;
                            $update_data = array(
                                'last_serial_no' => $current_serial_id
                            );
                            try {
                                $serial_num_tracker->where($where)->update($update_data);
                            } catch (QueryException $e) {
                                $res = array(
                                    'success' => false,
                                    'option' => false,
                                    'message' => $e->getMessage()
                                );
                                return response()->json($res);
                            }
                        }
                        //check if there is any active batch
                        //$any_active_batch = DB::table('batch_info')->where('is_active', 1)->first();
                        //if (is_null($any_active_batch)) {
                        $batch_number = 'KGS/BTH/' . substr(date('Y'), -2) . '/' . str_pad($current_serial_id, 4, 0, STR_PAD_LEFT);
                        $time = Carbon::now();
                        //$template_id = getActiveTemplateID();
                        // if (is_numeric($template_id)) {
                        $batch_info = array(
                            'batch_no' => $batch_number,
                            'generated_by' => $user_id,
                            'generated_on' => $time,
                            'template_id' => $template_id,
                            'is_active' => 1
                        );
                        $id = insertRecordReturnId('batch_info', $batch_info, $user_id);
                        $res = array(
                            'success' => true,
                            'id' => $id,
                            'current_serial_number' => $current_serial_id,
                            'batch_no' => $batch_number,
                            'generated_by' => aes_decrypt(\Auth::user()->first_name) . '&nbsp;' . aes_decrypt(\Auth::user()->first_name) . '&nbsp;(' . aes_decrypt(\Auth::user()->email) . ')',
                            'generated_on' => date_format($time, "d/m/Y H:i:s"),
                            'error_count' => $errorCounter
                        );
                        /*} else {
                            $res = array(
                                'success' => false,
                                'option' => false,
                                'message' => 'Problem was encountered trying to get current template info. Please contact system admin!!'
                            );
                        }
                        } else {
                            $batch_id = $any_active_batch->id;
                            //check if the batch has some data in the beneficiary master info table
                            $numOfRecords = DB::table('beneficiary_master_info')->where('batch_id', $batch_id)->count();
                            if ($numOfRecords > 0) {
                                $res = array(
                                    'success' => false,
                                    'option' => false,
                                    'message' => 'There is another active batch with records in the next stage. The records should be assessed first in order to generate a new batch information!!'
                                );
                            } else {
                                $user = User::find($any_active_batch->generated_by);
                                $batch_number = $any_active_batch->batch_no;
                                $generated_on = converter2($any_active_batch->generated_on);
                                $generated_by = aes_decrypt($user->first_name) . '&nbsp;' . aes_decrypt($user->last_name) . '&nbsp;(' . aes_decrypt($user->email) . ')';
                                $res = array(
                                    'success' => false,
                                    'option' => true,
                                    'id' => $batch_id,
                                    'batch_no' => $batch_number,
                                    'generated_on' => $generated_on,
                                    'generated_by' => $generated_by,
                                    'current_serial_number' => $current_serial_id,
                                    'message' => 'There is another active batch (Number:&nbsp;' . $batch_number . ',&nbsp;Generated on:&nbsp;' . $generated_on . ',&nbsp;By:&nbsp;' . $generated_by . '). Do you wish to use these batch information?'
                                );
                            }
                        }*/
                    }
                } else {
                    $res = array(
                        'success' => false,
                        'option' => false,
                        'message' => 'You have not validated all the records as required. Kindly validate the data to continue!!',
                        'error_count' => $errorCounter
                    );
                }
            } catch (\Exception $e) {
                $res = array(
                    'success' => false,
                    'option' => false,
                    'message' => $e->getMessage()
                );
                return response()->json($res);
            }
        }, 5);
        return response()->json($res);
    }

    function getErrorCount(Request $req)
    {
        $batch_id = $req->batch_id;
        $user_id = \Auth::user()->id;
        $res = array();

        DB::transaction(function () use ($user_id, $batch_id, &$res) {
            try {
                $errorCounter = DB::table('active_error_count')->value('error_counter');
                if ($errorCounter == 0 || $errorCounter < 1) {
                    //check for duplicate records
                    $dup_params = DB::table('beneficiary_duplicates_setup')->get();
                    if (count($dup_params) < 1) {
                        $res = array(
                            'success' => false,
                            'message' => 'No duplicate parameters are set, please set beneficiary duplicate parameters first!!'
                        );
                        return response()->json($res);
                    }
                    $dup_params_log = array();
                    foreach ($dup_params as $dup_param) {
                        $dup_params_log[] = array(
                            'batch_id' => $batch_id,
                            'dataindex' => $dup_param->dataindex,
                            'created_by' => $user_id
                        );
                    }
                    DB::table('duplicate_setup_log')->where('batch_id', $batch_id)->delete();
                    DB::table('duplicate_setup_log')->insert($dup_params_log);
                    $duplicates_array = array();
                    DB::table('beneficiary_master_info')->where('batch_id', $batch_id)->orderBy('id')->chunk(100, function ($allBatchRecords) use ($batch_id, &$duplicates_array) {
                        foreach ($allBatchRecords as $allBatchRecord) {
                            $is_duplicate = $this->isDuplicateRecord($allBatchRecord->id, $batch_id);
                            if ($is_duplicate > 0 || $is_duplicate == 1) {
                                $duplicates_array[] = array(
                                    'id' => $allBatchRecord->id
                                );
                                // DB::table('beneficiary_master_info')->where('id', $allBatchRecord->id)->update(array('is_duplicate' => 1));
                            }
                        }
                    });
                    $duplicates_array_Simp = convertAssArrayToSimpleArray($duplicates_array, 'id');
                    DB::table('beneficiary_master_info')
                        ->whereIn('id', $duplicates_array_Simp)
                        ->update(array('is_duplicate' => 1));
                    //change batch status to assessment
                    DB::table('batch_info')->where('id', $batch_id)->update(array('status' => 2));
                    //log in transition rpt table
                    $log_params = array(
                        'batch_id' => $batch_id,
                        'stage_id' => 2,
                        'to_date' => date(''),
                        'from_date' => Carbon::now(),
                        'author' => \Auth::user()->id,
                        'created_by' => \Auth::user()->id
                    );
                    DB::table('batches_transitional_report')
                        ->where(array('batch_id' => $batch_id, 'stage_id' => 1))
                        ->where(function ($query) {
                            $query->whereNull('to_date')
                                ->orWhere('to_date', '0000-00-00 00:00:00');
                        })
                        ->update(array('to_date' => Carbon::now()));
                    DB::table('batches_transitional_report')->insert($log_params);
                    $res = array(
                        'success' => true,
                        'message' => 'Data was moved successfully to the next stage!!',
                        'error_count' => $errorCounter
                    );
                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'You have not validated all the records as required. Kindly validate the data to continue!!',
                        'error_count' => $errorCounter
                    );
                }
            } catch (\Exception $e) {
                $res = array(
                    'success' => false,
                    'message' => $e->getMessage()
                );
                // return response()->json($res);
            }
        }, 5);
        return response()->json($res);
    }

    public function findDuplicatesFromExisting(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $category_id = $req->input('category_id');
            $whereCat = array($category_id);
            if ($category_id == 2) {
                $whereCat = array(2, 3);
            }
            //no need to continue if no other batch
            $count = DB::table('beneficiary_master_info')
                ->where('batch_id', '<>', $batch_id)
                ->count();
            if ($count < 1) {
                DB::table('batch_info')
                    ->where('id', $batch_id)
                    ->update(array('dup_checked' => 1));
                $res = array(
                    'success' => true,
                    'message' => 'Just proceed, no other batches found for comparison!!'
                );
                return response()->json($res);
            }
            $duplicates_array = array();
            $qry = DB::table('beneficiary_master_info')
                ->where('batch_id', $batch_id)
                ->where('is_duplicate_with_existing', '<>', 1)
                ->where('is_dup_processed', '<>', 1)
                ->where('is_mapped', '<>', 1);
            if (validateisNumeric($category_id)) {
                $qry->whereIn('category', $whereCat);
            } else {
                $qry->whereIn('category', array(1, 2, 3));
            }
            $qry->orderBy('id')
                ->chunk(100, function ($allBatchRecords) use ($batch_id, &$duplicates_array) {
                    foreach ($allBatchRecords as $allBatchRecord) {
                        $is_duplicate = $this->isDuplicateWithExistingRecord($allBatchRecord->id, $batch_id);
                        if ($is_duplicate > 0 || $is_duplicate == 1) {
                            $duplicates_array[] = array(
                                'id' => $allBatchRecord->id
                            );
                            /*  DB::table('beneficiary_master_info')
                                  ->where('id', $allBatchRecord->id)
                                  ->update(array('is_duplicate_with_existing' => 1));*/
                        }
                    }
                });
            $duplicates_array_simp = convertAssArrayToSimpleArray($duplicates_array, 'id');
            DB::table('beneficiary_master_info')
                ->whereIn('id', $duplicates_array_simp)
                ->update(array('is_duplicate_with_existing' => 1));
            DB::table('batch_info')
                ->where('id', $batch_id)
                ->update(array('dup_checked' => 1));
            $res = array(
                'success' => true,
                'message' => 'Process completed successfully!!'
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

    public function isDuplicateRecord($record_id, $batch_id)
    {
        $is_duplicate = 0;
        $where = $this->returnDuplicateCheckWhere($record_id);
        $duplicates_count = DB::table('beneficiary_master_info')
            ->where('batch_id', $batch_id)
            ->where('id', '<>', $record_id)
            ->where($where)
            ->count();
        if ($duplicates_count > 0) {
            $is_duplicate = 1;
        }
        //print_r(DB::getQueryLog());
        /* $duplicates = DB::table('beneficiary_master_info')
             //->select('subject', 'book_id')
             ->where('batch_id', $active_batch)
             ->groupBy(DB::raw($duplicates_params))
             ->havingRaw('COUNT(*) > 1')
             ->count();*/
        return $is_duplicate;
    }

    public function isDuplicateWithExistingRecord($record_id, $batch_id)
    {
        $is_duplicate = 0;
        $beneficiary_status = 4;
        $where = $this->returnDuplicateCheckWhere($record_id);
        $qry = DB::table('beneficiary_master_info')
            ->join('beneficiary_information', 'beneficiary_master_info.id', '=', 'beneficiary_information.master_id')
            //->where('beneficiary_information.beneficiary_status', $beneficiary_status)
            ->where('beneficiary_master_info.batch_id', '<>', $batch_id)
            ->where('beneficiary_master_info.id', '<>', $record_id)
            ->where('beneficiary_master_info.is_mapped', '=', 1)
            ->where($where);
        $duplicates_count = $qry->count();
        if ($duplicates_count > 0) {
            $is_duplicate = 1;
        }
        return $is_duplicate;
    }

    public function isStillDuplicateRecord($record_id, $batch_id)
    {
        $is_duplicate = 0;
        $where = $this->returnDuplicateCheckWhere($record_id);
        $duplicates_count = DB::table('beneficiary_master_info')
            ->where('batch_id', $batch_id)
            ->where('id', '<>', $record_id)
            ->where('is_duplicate', 1)
            ->where($where)
            ->count();
        if ($duplicates_count > 0) {
            $is_duplicate = 1;
        }
        return $is_duplicate;
    }

    public function recreateBatchNumber(Request $req)
    {
        $batch_id = $req->batch_id;
        $user_id = \Auth::user()->id;
        $process_type_id = getProcessTypeID('batch number');//DB::table('serialized_processes')->where('name', 'like', '%batch number%')->value('id');
        if (is_null($process_type_id) || $process_type_id == '') {
            $res = array(
                'success' => false,
                'message' => 'Error=>The process ID was not found. Please contact system Admin for assistance!!'
            );
        } else {
            $year = date('Y');
            $where = array(
                'year' => $year,
                'process_type' => $process_type_id
            );
            $serial_num_tracker = new SerialTracker();
            $serial_track = $serial_num_tracker->where($where)->first();
            if ($serial_track == '' || is_null($serial_track)) {
                $current_serial_id = 1;
                $serial_num_tracker->year = $year;
                $serial_num_tracker->process_type = $process_type_id;
                $serial_num_tracker->created_by = $user_id;
                $serial_num_tracker->last_serial_no = $current_serial_id;
                try {
                    $serial_num_tracker->save();
                } catch (QueryException $e) {
                    $res = array(
                        'success' => false,
                        'option' => false,
                        'message' => $e->getMessage()
                    );
                    return response()->json($res);
                }
            } else {
                $last_serial_id = $serial_track->last_serial_no;
                if ($last_serial_id == 1 || $last_serial_id < 2) {
                    $current_serial_id = $last_serial_id;
                } else {
                    $current_serial_id = $last_serial_id - 1;
                }
                $update_data = array(
                    'last_serial_no' => $current_serial_id
                );
                try {
                    $serial_num_tracker->where($where)->update($update_data);
                } catch (QueryException $e) {
                    $res = array(
                        'success' => false,
                        'option' => false,
                        'message' => $e->getMessage()
                    );
                    return response()->json($res);
                }
            }
            $batch_number = 'KGS/IMP/' . substr(date('Y'), -2) . '/' . str_pad($current_serial_id, 4, 0, STR_PAD_LEFT);
            $time = Carbon::now();
            $template_id = getActiveTemplateID();
            if (is_numeric($template_id)) {
                $batch_info = array(
                    'batch_no' => $batch_number,
                    'generated_by' => $user_id,
                    'generated_on' => $time,
                    'template_id' => $template_id,
                    'is_active' => 1
                );
                $where2 = array(
                    'id' => $batch_id
                );
                $previous_data = getPreviousRecords('batch_info', $where2);
                $success = updateRecord('batch_info', $previous_data, $where2, $batch_info, $user_id);
                if ($success || $success == true) {
                    $res = array(
                        'success' => true,
                        'id' => $batch_id,
                        'current_serial_number' => $current_serial_id,
                        'batch_no' => $batch_number,
                        'generated_by' => aes_decrypt(\Auth::user()->first_name) . '&nbsp;' . aes_decrypt(\Auth::user()->first_name) . '&nbsp;(' . aes_decrypt(\Auth::user()->email) . ')',
                        'generated_on' => date_format($time, "d/m/Y H:i:s"),
                    );
                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'Problem was encountered while updating batch information. Please contact system admin!!'
                    );
                }
            } else {
                $res = array(
                    'success' => false,
                    'option' => false,
                    'message' => 'Problem was encountered trying to get current template info. Please contact system admin!!'
                );
            }
        }
        return response()->json($res);
    }

    public function updateBatchTemplate(Request $req)
    {
        $batch_id = $req->batch_id;
        $template_id = getActiveTemplateID();
        $where = array(
            'id' => $batch_id
        );
        try {
            DB::table('batch_info')->where($where)->update(array('template_id' => $template_id));
            $res = array(
                'success' => true,
                'message' => 'Batch information updated successfully!!'
            );
        } catch (QueryException $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage() . '!!'
            );
        }

        return response()->json($res);
    }

    public function clearBatchInfo(Request $req)
    {
        $batch_id = $req->batch_id;
        $current_serial = $req->current_serial;
        $prev_serial = $current_serial - 1;
        $process_type_id = getProcessTypeID('batch number');
        $update_records = array(
            'last_serial_no' => $prev_serial
        );
        $where1 = array(
            'process_type' => $process_type_id,
            'year' => date('Y')
        );
        $where = array(
            'id' => $batch_id
        );
        $success = deleteRecordNoAudit('batch_info', $where);
        if ($success) {
            DB::table('serial_numbers_track')->where($where1)->update($update_records);
            $res = array(
                'success' => true,
                'message' => 'Action reverted successfully!!'
            );
        } else {
            $res = array(
                'success' => false,
                'message' => 'Problem was encountered while reverting your action. Please contact system admin!!'
            );
        }
        return response()->json($res);
    }
    //frank moded
    function getTemplateFields(Request $req)
    {
        $template_id = $req->template_id;
        $stdTemplateID = getStdTemplateId();
        $inArray = array($stdTemplateID, $template_id);
        $return = array();
        $qry = DB::table('template_fields');
        // $qry = $stdTemplateID == $template_id ? $qry->where('id', 0) : $qry->where('temp_id', $template_id);
        // $qry = $stdTemplateID == $template_id ? $qry->where('temp_id', $template_id) : $qry->whereIn('temp_id', $inArray);
        $qry->where('temp_id', $stdTemplateID);
        $data = $qry->get();
        $data = convertStdClassObjToArray($data);
        $data = decryptArray($data);
        foreach ($data as $datum) {
            $index = $datum['tabindex'];
            if ($index == 0) {
                $index = 0;
            }
            $return[] = array(
                'text' => '(' . $index . ') ' . $datum['name'],
                'dataIndex' => $datum['dataindex']
            );
        }
        $res = array(
            'success' => true,
            'message' => 'All is well!',
            'columns' => $return
        );
        return response()->json($res);
    }

    function getTemplateFieldsForMapping(Request $req)
    {
        $template_id = $req->template_id;
        $batch_id = $req->batch_id;
        $category = $req->category;
        $stdTemplateID = getStdTemplateId();
        $inArray = array($stdTemplateID, $template_id);
        $return = array();
        $qry = DB::table('template_fields');
        // $qry = $stdTemplateID == $template_id ? $qry->where('id', 0) : $qry->where('temp_id', $template_id);
        $qry = $stdTemplateID == $template_id ? $qry->where('temp_id', $template_id) : $qry->whereIn('temp_id', $inArray);
        $data = $qry->get();
        $data = convertStdClassObjToArray($data);
        $data = decryptArray($data);
        $mapped = DB::table('beneficiary_master_info')
            ->where(array('batch_id' => $batch_id, 'category' => $category, 'is_mapped' => 1))
            ->where('is_duplicate_with_existing', '<>', 1)
            ->where('is_active', 1)
            ->count();
        $unmapped = DB::table('beneficiary_master_info')
            ->where(array('batch_id' => $batch_id, 'category' => $category, 'is_mapped' => 0))
            ->where('is_duplicate_with_existing', '<>', 1)
            ->where('is_active', 1)
            ->count();
        foreach ($data as $datum) {
            $index = $datum['tabindex'];
            if ($index == 0) {
                $index = 0;
            }
            $return[] = array(
                'text' => '(' . $index . ')&nbsp;' . $datum['name'],
                'dataIndex' => $datum['dataindex']
            );
        }
        $res = array(
            'success' => true,
            'message' => 'All is well!',
            'columns' => $return,
            'mapped' => $mapped,
            'unmapped' => $unmapped
        );
        return response()->json($res);
    }

    function getMainActiveTemplateFields(Request $req)
    {
        // $template_id = $req->template_id;
        $stdTemplateID = getStdTemplateId();
        $templateData = DB::table('main_active_template')->first();
        if (!is_null($templateData)) {
            $template_id = $templateData->template_id;
            // $template_id=intval($template_id);
            $inArray = array($stdTemplateID, $template_id);
            $qry = DB::table('template_fields');
            $qry = $stdTemplateID == $template_id ? $qry->where('temp_id', $template_id) : $qry->whereIn('temp_id', $inArray);
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $data = decryptArray($data);
            foreach ($data as $datum) {
                $index = $datum['tabindex'];
                if ($index == 0) {
                    $index = 0;
                }
                $return[] = array(
                    'text' => '(' . $index . ')&nbsp;' . $datum['name'],
                    'dataIndex' => $datum['dataindex']
                );
            }
            $res = array(
                'success' => true,
                'message' => 'All is well!',
                'columns' => $return
            );
        } else {
            $res = array(
                'success' => false,
                'message' => 'No template found!!',
                'columns' => ''
            );
        }
        return response()->json($res);
    }

    function getActiveTemplate()
    {
        //check if there is any active upload data
        $check = DB::table('beneficiary_master_info')->first();
        if (is_null($check)) {
            $res = array(
                'success' => false,
                'message' => 'No active import data was found. Kindly import data by clicking the New Import button!!',
                'template_id' => '',
                'template_name' => ''
            );
        } else {
            $data = DB::table('active_template')->first();
            if (is_null($data)) {
                $res = array(
                    'success' => false,
                    'message' => 'No active template was found. Kindly import data by clicking the New Import button!!',
                    'template_id' => '',
                    'template_name' => ''
                );
            } else {
                $template_id = $data->template_id;
                $template_name = DB::table('templates')->where('id', $template_id)->value('name');
                $res = array(
                    'success' => true,
                    'message' => 'All is well!!',
                    'template_id' => $template_id,
                    'template_name' => aes_decrypt($template_name)
                );
            }
        }
        return json_encode($res);
    }

    function saveImportInformation(Request $req)
    {
        try {
            $res = array();
            $template_id = $req->template_id;
            $description = $req->description;
            $user_email = aes_decrypt(\Auth::user()->email);
            $owner = \Auth::user()->dms_id;
            $user_id = \Auth::user()->id;
            DB::transaction(function () use (&$res, $template_id, $description, $user_id, $owner, $user_email) {
                /*  $last_id = DB::table('batch_info')->max('id');
                  $curr_id = $last_id + 1;
                  $curr_id = str_pad($curr_id, 4, 0, STR_PAD_LEFT);
                  $year = substr(date('Y'), -2);
                  $batch_no = 'KGS/IMP/' . $year . '/' . $curr_id;*/
                $year = date('Y');
                $batch_no = generateBatchNumber($year, $user_id);
                $data = array(
                    'batch_no' => $batch_no,
                    'description' => $description,
                    'generated_on' => Carbon::now(),
                    'generated_by' => $user_id,
                    'template_id' => $template_id,
                    'status' => 1
                );
                $id = insertRecordReturnId('batch_info', $data, $user_id);
                $log_params = array(
                    'batch_id' => $id,
                    'stage_id' => 1,
                    'from_date' => Carbon::now(),
                    'author' => \Auth::user()->id,
                    'created_by' => \Auth::user()->id
                );
                DB::table('batches_transitional_report')->insert($log_params);
                //create dms details
                $main_module_id = 2;
                $parent_id = 3;
                $folder_id = 6;//dms_createFolder($parent_id, $batch_no, $description, $user_email);
                //createDMSModuleFolders($folder_id, $main_module_id, $owner);
                DB::table('batch_info')
                    ->where('id', $id)
                    ->update(array('folder_id' => $folder_id));
                $res = array(
                    'success' => true,
                    'batch_no' => $batch_no,
                    'batch_id' => $id,
                    'message' => 'Template was successfully set!!'
                );
            }, 5);
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

    function setCurrentTemplate(Request $req)
    {
        $template_id = $req->template_id;
        $data = array(
            'template_id' => $template_id,
            'date' => Carbon::now()
        );
        $res = array();
        DB::transaction(function () use ($data, &$res) {
            try {
                //clear concerned tables
                DB::table('beneficiary_master_info')->delete();
                DB::table('temp_additional_fields_values')->delete();
                DB::table('active_template')->delete();
                //DB::table('active_template')->insert($data);
                $template_id = insertReturnID('active_template', $data);
                $res = array(
                    'success' => true,
                    'template_id' => $template_id,
                    'message' => 'Template set successfully!!'
                );
            } catch (QueryException $exception) {
                $res = array(
                    'success' => false,
                    'template_id' => '',
                    'message' => 'Error: ' . $exception->getMessage()
                );
            }
        }, 5);
        return response()->json($res);
    }

    public function moveToBatchAssessment(Request $req)
    {
        $batch_id = $req->batch_id;
        $import_id = $req->import_id;
        $description = $req->description;
        $res = array();
        DB::transaction(function () use ($batch_id, $description, $import_id, &$res) {
            $batch_info = BatchInfo::find($batch_id);
            if (is_null($batch_info)) {
                $res = array(
                    'success' => false,
                    'message' => 'Problem was encountered while fetching batch information. Please contact system admin!!'
                );
            } else {
                $template_id = $batch_info->template_id;
                $batch_info->description = $description;
                $batch_info->save();
                $stdTempID = getStdTemplateId();
                $data = DB::table('beneficiary_master_info')->where('import_id', $import_id)->get();
                if ($template_id == $stdTempID) {
                    $data->map(function ($data) use ($template_id, $batch_id) {
                        $data->batch_id = $batch_id;
                        $data->is_duplicate = $this->isDuplicateRecord($data->id, $batch_id);
                        unset($data->id);
                        return $data;
                    });
                    $data = convertStdClassObjToArray($data);
                    $success = insertRecordNoAudit('beneficiary_master_info', $data);
                    if ($success) {
                        // DB::table('main_active_template')->insert($main_template_data);
                        DB::table('beneficiary_master_info')->where('import_id', $import_id)->delete();
                        DB::table('temp_additional_fields_values')->where('import_id', $import_id)->delete();
                        // DB::table('active_template')->delete();
                        $res = array(
                            'success' => true,
                            'message' => 'Data moved successfully to the next stage'
                        );
                    } else {
                        $res = array(
                            'success' => false,
                            'message' => $success
                        );
                    }
                } else {
                    $data->map(function ($data) use ($template_id, $batch_id) {
                        $data->batch_id = $batch_id;
                        $data->is_duplicate = $this->isDuplicateRecord($data->id, $batch_id);
                        return $data;
                    });
                    $data = convertStdClassObjToArray($data);
                    foreach ($data as $key => $datum) {
                        $main_temp_id = $datum['id'];
                        unset($datum['id']);
                        // $datum->batch_id = $batch_id;
                        $insertData = convertStdClassObjToArray($datum);
                        $additional_values = DB::table('temp_additional_fields_values')->where('main_temp_id', $main_temp_id)->get();
                        unset($additional_values['main_temp_id']);
                        $id = insertReturnID('beneficiary_master_info', $insertData);
                        $additional_values[0]->main_import_id = $id;
                        $insertData2 = convertStdClassObjToArray($additional_values);
                        $success = insertRecordNoAudit('imports_additional_fields_values', $insertData2);
                    }
                    if (is_numeric($id) && $success == true) {
                        //DB::table('main_active_template')->insert($main_template_data);
                        DB::table('beneficiary_master_info')->where('import_id', $import_id)->delete();
                        DB::table('temp_additional_fields_values')->where('import_id', $import_id)->delete();
                        //DB::table('active_template')->delete();
                        $res = array(
                            'success' => true,
                            'message' => 'Data moved successfully to the next stage'
                        );
                    } else {
                        $res = array(
                            'success' => false,
                            'message' => 'Problem was encountered while moving data. Please contact system admin'
                        );
                    }
                }
            }
        }, 5);
        return response()->json($res);
    }

    //franks
    public function getOutofSchool(Request $req)
    {
        $start = $req->input('start');
        $limit = $req->input('limit');
        $willingToReturn = $req->input('willing_to_return');
        $bursary_status = $req->input('bursary_status');
        $whereData = $req->input('whereData');
        $batch_id = $req->input('batch_id');
        $template_id = $req->input('template_id');
        $whereData = json_decode($whereData);
        $stdTempID = getStdTemplateId();
        try {
            $qry = DB::table('out_of_school_view')
                ->where('batch_id', $batch_id);
            //->where('is_duplicate', '<>', 1);
            if (isset($willingToReturn)) {
                if ($willingToReturn == 1) {
                    $qry = $qry->where('willing_to_return_sch', 'LIKE', 'y%');
                } else if ($willingToReturn == 2) {
                    $qry = $qry->where('willing_to_return_sch', 'LIKE', 'n%');
                }
            }
            if (isset($bursary_status)) {
                if ($bursary_status == 1) {
                    $qry->Where(function ($qry) {
                        $qry->whereIn('bursary_status', array('no', 'n/a', 'n'));
                    });
                } else if ($bursary_status == 2) {
                    $qry->Where(function ($qry) {
                        $qry->whereNotIn('bursary_status', array('no', 'n/a', 'n'));
                    });
                }
            }
            $qry = count($whereData) > 0 ? $qry->whereIn('highest_grade', $whereData) : $qry->whereRAW('1=1');
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            // $data=$this->SpliceDataOnLimits($start,$limit,$data);
            $data = $qry->skip($start)->take($limit)->get();
            // if ($template_id != $stdTempID) {
            //     $template_fields = DB::table('template_fields')->where('temp_id', $template_id)->get();
            //     $template_fields = convertStdClassObjToArray($template_fields);
            //     foreach ($data as $key => $datum) {
            //         foreach ($template_fields as $key3 => $template_field) {
            //             $value = $this->getAdditionalTemplateFieldValue($datum['id'], $template_field['id']);
            //             $data[$key][aes_decrypt($template_field['dataindex'])] = $value;
            //         }
            //     }
            // }
            $res = array(
                'success' => true,
                'result' => $data,
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
    
    // public function getOutofSchool(Request $req)
    // {
    //     $start = $req->input('start');
    //     $limit = $req->input('limit');
    //     $willingToReturn = $req->input('willing_to_return');
    //     $bursary_status = $req->input('bursary_status');
    //     $whereData = $req->input('whereData');
    //     $batch_id = $req->input('batch_id');
    //     $template_id = $req->input('template_id');
    //     $whereData = json_decode($whereData);
    //     $stdTempID = getStdTemplateId();
    //     $templateIdPlaceHolder = array(
    //         'template_id' => $template_id
    //     );
    //     try {
    //         $qry = DB::table('out_of_school_view')
    //             ->where('batch_id', $batch_id);
    //         //->where('is_duplicate', '<>', 1);
    //         if (isset($willingToReturn)) {
    //             if ($willingToReturn == 1) {
    //                 $qry = $qry->where('willing_to_return_sch', 'LIKE', 'y%');
    //             } else if ($willingToReturn == 2) {
    //                 $qry = $qry->where('willing_to_return_sch', 'LIKE', 'n%');
    //             }
    //         }
    //         if (isset($bursary_status)) {
    //             if ($bursary_status == 1) {
    //                 $qry->Where(function ($qry) {
    //                     $qry->whereIn('bursary_status', array('no', 'n/a', 'n'));
    //                 });
    //             } else if ($bursary_status == 2) {
    //                 $qry->Where(function ($qry) {
    //                     $qry->whereNotIn('bursary_status', array('no', 'n/a', 'n'));
    //                 });
    //             }
    //         }
    //         $qry = count($whereData) > 0 ? $qry->whereIn('highest_grade', $whereData) : $qry->whereRAW('1=1');
    //         // $data = $qry->get();
    //         if ($template_id != $stdTempID) {
    //             // function ($q) use ($code) { 
    //             $data2 = array();
    //             $qry->orderBy('id')->chunk(10, function ($allRecords) use ($templateIdPlaceHolder, $data2)  {
    //             foreach ($allRecords as $oneRecord) {
    //                 $data = convertStdClassObjToArray($oneRecord);
    //                 ////
    //                 // if ($template_id != $stdTempID) {
    //                     $template_fields = DB::table('template_fields')->where('temp_id', $templateIdPlaceHolder['template_id'])->get();
    //                     $template_fields = convertStdClassObjToArray($template_fields);
    //                     $i = 0;
    //                     $dataOne = array();
    //                     foreach ($data as $key => $datum) {
    //                         foreach ($template_fields as $key3 => $template_field) {
    //                             $value[] = $this->getAdditionalTemplateFieldValue($datum, $template_field['id']);
    //                             // $value = $this->getAdditionalTemplateFieldValue($datum['id'], $template_field['id']);
    //                             // $data[$key][$template_field['dataindex']] = $value;
    //                             // print_r($key);exit(); =
    //                             // $data[aes_decrypt($template_field['dataindex'])] = $value;
    //                             // $data[$template_field['dataindex']] = $value;
    //                             // $dataOne[$template_field['dataindex'] = $value];
    //                             // $dataOne += [$template_field['dataindex'] => $value];
    //                             // $dataOne = [$template_field['dataindex'] => $value];
    //                             // $arr = ['key' => 'value'];
    //                             // $arr = ['key' => 'value'];
    //                             // $data[$key][aes_decrypt($template_field['dataindex'])] = $value;
    //                             // $data += [$key => $value];
    //                         }
    //                         print_r($value);exit();
    //                         $i++;
    //                         // $data[$i] = [$data];
    //                         // $result[$i] = array_merge($data, $dataOne);
    //                         // print_r($result);exit();
    //                         // $data[$key][aes_decrypt($template_field['dataindex'])] = $value;
    //                     }
    //                     print_r($i);exit();
    //                 // }
    //                 }
    //             });
    //         }
    //         $res = array(
    //             'success' => true,
    //             'result' => $data,
    //             'message' => 'All is well'
    //         );
    //     } catch (\Exception $e) {
    //         $res = array(
    //             'success' => false,
    //             'message' => $e->getMessage()
    //         );
    //     }
    //     return response()->json($res);
    // }

    public function updateOutofSchoolCategory($willingToReturn, $bursary_status, $whereData, $batch_id)
    {
        /// try {
        $qry = DB::table('beneficiary_master_info')
            ->where('batch_id', $batch_id)
            ->where('school_going', 'LIKE', 'n%')
            ->where('is_duplicate', '<>', 1)
            ->whereBetween('highest_grade', array(4, 12));
        if (isset($willingToReturn)) {
            if ($willingToReturn == 1) {
                $qry = $qry->where('willing_to_return_sch', 'LIKE', 'y%');
            } else if ($willingToReturn == 2) {
                $qry = $qry->where('willing_to_return_sch', 'LIKE', 'n%');
            }
        }
        if (isset($bursary_status)) {
            if ($bursary_status == 1) {
                $qry->Where(function ($qry) {
                    $qry->whereIn('bursary_status', array('no', 'n/a', 'n'));
                });
            } else if ($bursary_status == 2) {
                $qry->Where(function ($qry) {
                    $qry->whereNotIn('bursary_status', array('no', 'n/a', 'n'));
                });
            }
        }
        $qry = count($whereData) > 0 ? $qry->whereIn('highest_grade', $whereData) : $qry->whereRAW('1=1');
        $qry->update(array('category' => 1, 'initial_category' => 1));
        /*    return true;
        } catch (\Exception $exception) {
            echo $exception->getMessage();
            return false;
        } catch (\Throwable $throwable) {
            echo $throwable->getMessage();
            return false;
        }*/
    }
    //frank moded
    public function getInSchool(Request $req)
    {
        try {
            $bursary_status = $req->input('bursary_status');
            $whereData = $req->input('whereData');
            $batch_id = $req->input('batch_id');
            $template_id = $req->input('template_id');
            $stdTempID = getStdTemplateId();
            $whereData = json_decode($whereData);
            $qry = DB::table('in_school_view')
                ->where('batch_id', $batch_id);
            if (isset($bursary_status)) {
                if ($bursary_status == 1) {
                    $qry->Where(function ($qry) {
                        $qry->whereIn('bursary_status', array('no', 'n/a', 'n'));
                    });
                } else if ($bursary_status == 2) {
                    $qry->Where(function ($qry) {
                        $qry->whereNotIn('bursary_status', array('no', 'n/a', 'n'));
                    });
                }
            }
            $qry = count($whereData) > 0 ? $qry->whereIn('current_school_grade', $whereData) : $qry->whereRAW('1=1');
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            // if ($template_id != $stdTempID) {
            //     $template_fields = DB::table('template_fields')->where('temp_id', $template_id)->get();
            //     $template_fields = convertStdClassObjToArray($template_fields);
            //     foreach ($data as $key => $datum) {
            //         foreach ($template_fields as $key3 => $template_field) {
            //             $value = $this->getAdditionalTemplateFieldValue($datum['id'], $template_field['id']);
            //             $data[$key][aes_decrypt($template_field['dataindex'])] = $value;
            //         }
            //     }
            // }
            $res = array(
                'success' => true,
                'result' => $data,
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

    public function updateInSchoolCategory($bursary_status, $whereData, $batch_id)
    {
        // try {
        //todo: Non Exam Classes
        $qry = DB::table('beneficiary_master_info')
            ->where('batch_id', $batch_id)
            ->where('school_going', 'LIKE', 'y%')
            ->where('is_duplicate', '<>', 1)
            ->whereBetween('current_school_grade', array(8, 12))
            ->whereNotIn('current_school_grade', array(9));
        if (isset($bursary_status)) {
            if ($bursary_status == 1) {
                $qry->Where(function ($qry) {
                    $qry->whereIn('bursary_status', array('no', 'n/a', 'n'));
                });
            } else if ($bursary_status == 2) {
                $qry->Where(function ($qry) {
                    $qry->whereNotIn('bursary_status', array('no', 'n/a', 'n'));
                });
            }
        }
        $qry = count($whereData) > 0 ? $qry->whereIn('current_school_grade', $whereData) : $qry->whereRAW('1=1');
        $qry->update(array('category' => 2, 'initial_category' => 2));

        //todo: Exam Classes
        $qry = DB::table('beneficiary_master_info')
            ->where('batch_id', $batch_id)
            ->where('school_going', 'LIKE', 'y%')
            ->where('is_duplicate', '<>', 1)
            ->whereIn('current_school_grade', array(7, 9));
        if (isset($bursary_status)) {
            if ($bursary_status == 1) {
                $qry->Where(function ($qry) {
                    $qry->whereIn('bursary_status', array('no', 'n/a', 'n'));
                });
            } else if ($bursary_status == 2) {
                $qry->Where(function ($qry) {
                    $qry->whereNotIn('bursary_status', array('no', 'n/a', 'n'));
                });
            }
        }
        $qry = count($whereData) > 0 ? $qry->whereIn('current_school_grade', $whereData) : $qry->whereRAW('1=1');
        $qry->update(array('category' => 2, 'initial_category' => 3));
        /*     return true;
         } catch (\Exception $exception) {
             echo $exception->getMessage();
             return false;
         } catch (\Throwable $throwable) {
             echo $throwable->getMessage();
             return false;
         }*/
    }
    //frank moded
    public function getMappedData(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $category = $req->input('category');
        $template_id = $req->input('template_id');
        $filter_id = $req->input('filter_id');
        $stdTempID = getStdTemplateId();
        $whereCat = array($category);
        if ($category == 2) {
            $whereCat = array(2, 3);
        }
        try {
            $qry = DB::table('beneficiary_master_info')
                ->where('batch_id', $batch_id)
                ->whereIn('category', $whereCat)
                ->where('is_active', 1)
                ->where('is_duplicate_with_existing', '<>', 1);
            if (isset($filter_id) && $filter_id != '') {
                $qry->where('is_mapped', $filter_id);
            }
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            // if ($template_id != $stdTempID) {
            //     $template_fields = DB::table('template_fields')->where('temp_id', $template_id)->get();
            //     $template_fields = convertStdClassObjToArray($template_fields);
            //     foreach ($data as $key => $datum) {
            //         foreach ($template_fields as $key3 => $template_field) {
            //             $value = $this->getAdditionalTemplateFieldValue($datum['id'], $template_field['id']);
            //             $data[$key][aes_decrypt($template_field['dataindex'])] = $value;
            //         }
            //     }
            // }
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
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getExamClasses(Request $req)
    {
        $bursary_status = $req->input('bursary_status');
        $whereData = $req->input('whereData');
        $batch_id = $req->input('batch_id');
        $template_id = $req->input('template_id');
        $stdTempID = getStdTemplateId();
        $whereData = json_decode($whereData);
        try {
            $qry = DB::table('exam_classes_view')
                ->where('batch_id', $batch_id);
            //->where('is_duplicate', '<>', 1);
            if (isset($bursary_status)) {
                if ($bursary_status == 1) {
                    $qry->Where(function ($qry) {
                        $qry->whereIn('bursary_status', array('no', 'n/a', 'n'));
                    });
                } else if ($bursary_status == 2) {
                    $qry->Where(function ($qry) {
                        $qry->whereNotIn('bursary_status', array('no', 'n/a', 'n'));
                    });
                }
            }
            $qry = count($whereData) > 0 ? $qry->whereIn('current_school_grade', $whereData) : $qry->whereRAW('1=1');
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            if ($template_id != $stdTempID) {
                $template_fields = DB::table('template_fields')->where('temp_id', $template_id)->get();
                $template_fields = convertStdClassObjToArray($template_fields);
                foreach ($data as $key => $datum) {
                    foreach ($template_fields as $key3 => $template_field) {
                        $value = $this->getAdditionalTemplateFieldValue($datum['id'], $template_field['id']);
                        $data[$key][aes_decrypt($template_field['dataindex'])] = $value;
                    }
                }
            }
            $res = array(
                'success' => true,
                'result' => $data,
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

    public function updateExamClassesCategory($bursary_status, $whereData, $batch_id)
    {
        try {
            $qry = DB::table('beneficiary_master_info')
                ->where('batch_id', $batch_id)
                ->where('school_going', 'LIKE', 'y%')
                ->where('is_duplicate', '<>', 1)
                ->whereBetween('current_school_grade', array(7, 9))
                ->whereNotIn('current_school_grade', array(8));
            if (isset($bursary_status)) {
                if ($bursary_status == 1) {
                    $qry->Where(function ($qry) {
                        $qry->whereIn('bursary_status', array('no', 'n/a', 'n'));
                    });
                } else if ($bursary_status == 2) {
                    $qry->Where(function ($qry) {
                        $qry->whereNotIn('bursary_status', array('no', 'n/a', 'n'));
                    });
                }
            }
            $qry = count($whereData) > 0 ? $qry->whereIn('current_school_grade', $whereData) : $qry->whereRAW('1=1');
            $qry->update(array('category' => 3));
            return true;
        } catch (\Exception $e) {
            echo $e->getMessage();
            return false;
        }
    }

    public function getOutofSchoolCount($willingToReturn, $bursary_status, $whereData, $batch_id, $out_of_school_check)
    {
        $outOfSchoolGirls = 0;
        if ($out_of_school_check == 1 || $out_of_school_check == '1') {
            $qry = DB::table('out_of_school_view')
                ->where('batch_id', $batch_id)
                // ->where('school_going', 'LIKE', '%no%')
                ->where('is_duplicate', '<>', 1)
                ->where('is_active', 1);
            if (isset($willingToReturn)) {
                if ($willingToReturn == 1) {
                    $qry = $qry->where('willing_to_return_sch', 'LIKE', 'y%');
                } else if ($willingToReturn == 2) {
                    $qry = $qry->where('willing_to_return_sch', 'LIKE', 'n%');
                }
            }
            if (isset($bursary_status)) {
                if ($bursary_status == 1) {
                    $qry->Where(function ($qry) {
                        $qry->whereIn('bursary_status', array('no', 'n/a', 'n'));
                    });
                } else if ($bursary_status == 2) {
                    $qry->Where(function ($qry) {
                        $qry->whereNotIn('bursary_status', array('no', 'n/a', 'n'));
                    });
                }
            }
            $qry = count($whereData) > 0 ? $qry->whereIn('highest_grade', $whereData) : $qry->whereRAW('1=1');
            $outOfSchoolGirls = $qry->count();
        }
        return $outOfSchoolGirls;
    }

    public function getInSchoolCount($bursary_status, $whereData, $batch_id, $in_school_check)
    {
        $inSchoolGirls = 0;
        if ($in_school_check == 1 || $in_school_check == '1') {
            $qry = DB::table('in_school_view')
                ->where('batch_id', $batch_id)
                ->where('is_active', 1)
                ->where('is_duplicate', '<>', 1);
            if (isset($bursary_status)) {
                if ($bursary_status == 1) {
                    $qry->Where(function ($qry) {
                        $qry->whereIn('bursary_status', array('no', 'n/a', 'n'));
                    });
                } else if ($bursary_status == 2) {
                    $qry->Where(function ($qry) {
                        $qry->whereNotIn('bursary_status', array('no', 'n/a', 'n'));
                    });
                }
            }
            $qry = count($whereData) > 0 ? $qry->whereIn('current_school_grade', $whereData) : $qry->whereRAW('1=1');
            $inSchoolGirls = $qry->count();
        }
        return $inSchoolGirls;
    }

    public function getExamClassesCount($bursary_status, $whereData, $batch_id, $exam_classes_check)
    {
        $girlsInExamClasses = 0;
        if ($exam_classes_check == 1 || $exam_classes_check == '1') {
            $qry = DB::table('exam_classes_view')
                ->where('batch_id', $batch_id)
                ->where('is_active', 1)
                ->where('is_duplicate', '<>', 1);
            if (isset($bursary_status)) {
                if ($bursary_status == 1) {
                    $qry->Where(function ($qry) {
                        $qry->whereIn('bursary_status', array('no', 'n/a', 'n'));
                    });
                } else if ($bursary_status == 2) {
                    $qry->Where(function ($qry) {
                        $qry->whereNotIn('bursary_status', array('no', 'n/a', 'n'));
                    });
                }
            }
            $qry = count($whereData) > 0 ? $qry->whereIn('current_school_grade', $whereData) : $qry->whereRAW('1=1');
            $girlsInExamClasses = $qry->count();
        }
        return $girlsInExamClasses;
    }

    public function testDuplicates()
    {
        $active_batch = 35;
        $duplicates_params = DB::table('beneficiary_duplicates_setup')->select('dataindex')->get();
        $duplicates_params = convertAssArrayToSimpleArray(convertStdClassObjToArray($duplicates_params), 'dataindex');
        //$duplicates_params = "'" . implode("', '", $duplicates_params) . "'";
        $duplicates_params = implode(',', $duplicates_params);
        $duplicates = DB::table('beneficiary_master_info')
            //->select('subject', 'book_id')
            ->where('batch_id', '<>', $active_batch)
            ->groupBy(DB::raw($duplicates_params))
            ->havingRaw('COUNT(*) > 1')
            ->get();
    }
    //frank moded
    public function getDuplicateRecords(Request $req)
    {
        $batch_id = $req->batch_id;
        $template_id = $req->template_id;
        $stdTempID = getStdTemplateId();
        $where = array(
            'batch_id' => $batch_id,
            'is_duplicate' => 1,
            'is_active' => 1
        );

        /*$duplicates_params = DB::table('beneficiary_duplicates_setup')->select('dataindex')->get();
        $duplicates_params = convertAssArrayToSimpleArray(convertStdClassObjToArray($duplicates_params), 'dataindex');
        $duplicates_params = implode(',', $duplicates_params);*/

        $data = DB::table('beneficiary_master_info')
            ->where($where)
            //->groupBy(DB::raw($duplicates_params))
            ->get();
        $data = convertStdClassObjToArray($data);
        // if ($template_id != $stdTempID) {
        //     $template_fields = DB::table('template_fields')->where('temp_id', $template_id)->get();
        //     $template_fields = convertStdClassObjToArray($template_fields);
        //     foreach ($data as $key => $datum) {
        //         foreach ($template_fields as $key3 => $template_field) {
        //             $value = $this->getAdditionalTemplateFieldValue($datum['id'], $template_field['id']);
        //             $data[$key][aes_decrypt($template_field['dataindex'])] = $value;
        //         }
        //     }
        // }
        $res = array(
            'results' => $data
        );
        return response()->json($res);
    }
    //frank moded
    public function getMappingDuplicateRecords(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $template_id = $req->input('template_id');
        $stdTempID = getStdTemplateId();
        $where = array(
            'batch_id' => $batch_id,
            'is_duplicate_with_existing' => 1
            //'is_active' => 1
        );
        $data = DB::table('beneficiary_master_info')
            ->where($where)
            ->get();
        $data = convertStdClassObjToArray($data);
        // if ($template_id != $stdTempID) {
        //     $template_fields = DB::table('template_fields')->where('temp_id', $template_id)->get();
        //     $template_fields = convertStdClassObjToArray($template_fields);
        //     foreach ($data as $key => $datum) {
        //         foreach ($template_fields as $key3 => $template_field) {
        //             $value = $this->getAdditionalTemplateFieldValue($datum['id'], $template_field['id']);
        //             $data[$key][aes_decrypt($template_field['dataindex'])] = $value;
        //         }
        //     }
        // }
        $res = array(
            'results' => $data
        );
        return response()->json($res);
    }

    public function getDuplicateRecordsCount($batch_id)
    {
        $where = array(
            'batch_id' => $batch_id,
            'is_duplicate' => 1,
            'is_active' => 1
        );
        $duplicateRecordsCount = DB::table('beneficiary_master_info')->where($where)->count();
        return $duplicateRecordsCount;
    }

    public function getSummaryStatistics(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $outOfSchool = $req->input('out_of_school');
        $inSchool = $req->input('in_school');
        $examClasses = $req->input('exam_classes');
        $willingness = $req->input('willingness');
        $bursary_status = $req->input('bursary_status');
        $out_of_school_check = $req->input('out_of_school_check');
        $in_school_check = $req->input('in_school_check');
        $exam_classes_check = $req->input('exam_classes_check');

        $out_of_school_array = json_decode($outOfSchool);
        $in_school_array = json_decode($inSchool);
        $exam_classes_array = json_decode($examClasses);

        $outOfSchoolCount = $this->getOutofSchoolCount($willingness, $bursary_status, $out_of_school_array, $batch_id, $out_of_school_check);
        $inSchoolCount = $this->getInSchoolCount($bursary_status, $in_school_array, $batch_id, $in_school_check);
        $examClassesCount = $this->getExamClassesCount($bursary_status, $exam_classes_array, $batch_id, $exam_classes_check);
        $duplicatesCount = $this->getDuplicateRecordsCount($batch_id);
        $totalRecords = DB::table('beneficiary_master_info')->where('batch_id', $batch_id)->count();
        $filteredOutCount = ($totalRecords - ($outOfSchoolCount + $inSchoolCount + $examClassesCount));
        $res = array(
            'out_of_school_count' => $outOfSchoolCount,
            'in_school_count' => $inSchoolCount,
            'exam_classes_count' => $examClassesCount,
            'duplicates_count' => $duplicatesCount,
            'filtered_out' => $filteredOutCount,
            'total_batch_records' => $totalRecords
        );
        return response()->json(array('results' => $res));
    }
    //frank moded getMappedData
    public function getFilteredOutRecords(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $template_id = $req->input('template_id');
        $stdTempID = getStdTemplateId();
        try {
            $qry1 = DB::table('beneficiary_master_info')
                ->where('batch_id', $batch_id)
                ->whereNotIn('category', array(1, 2, 3));
            $data1 = $qry1->get();
            $data1 = convertStdClassObjToArray($data1);
            $qry2 = DB::table('beneficiary_master_info')
                ->where('batch_id', $batch_id)
                ->where('is_duplicate_with_existing', 1);
            $data2 = $qry2->get();
            $data2 = convertStdClassObjToArray($data2);
            $data = array_merge($data1, $data2);

            // if ($template_id != $stdTempID) {
            //     $template_fields = DB::table('template_fields')
            //         ->where('temp_id', $template_id)
            //         ->get();
            //     $template_fields = convertStdClassObjToArray($template_fields);
            //     foreach ($data as $key => $datum) {
            //         foreach ($template_fields as $key3 => $template_field) {
            //             $value = $this->getAdditionalTemplateFieldValue($datum['id'], $template_field['id']);
            //             $data[$key][aes_decrypt($template_field['dataindex'])] = $value;
            //         }
            //     }
            // }
            $res = array(
                'results' => $data
            );
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
        return response()->json($res);
    }

    public function getOutOfSchoolFiltered($batch_id, $out_of_school_check, $out_of_school_array, $willingness, $bursary_status)
    {
        if ($out_of_school_check == 1 || $out_of_school_check == '1') {
            if (count($out_of_school_array) > 0) {//some grades specified
                $qry = DB::table('out_of_school_view')
                    ->where('batch_id', $batch_id)
                    ->where('is_duplicate', '<>', 1)
                    ->whereNotIn('highest_grade', $out_of_school_array);
                if (isset($willingness)) {
                    if ($willingness == 1) {
                        $qry = $qry->where('willing_to_return_sch', 'NOT LIKE', 'y%');
                    } else if ($willingness == 2) {
                        $qry = $qry->where('willing_to_return_sch', 'NOT LIKE', 'n%');
                    } else {

                    }
                }
                if (isset($bursary_status)) {
                    if ($bursary_status == 1) {
                        $qry->Where(function ($qry) {
                            $qry->whereNotIn('bursary_status', array('no', 'n/a', 'n'));
                        });
                    } else if ($bursary_status == 2) {
                        $qry->Where(function ($qry) {
                            $qry->whereIn('bursary_status', array('no', 'n/a', 'n'));
                        });
                    }
                }
            } else {//no grades specified
                if (isset($willingness)) {
                    if ($willingness == 1) {
                        $qry = DB::table('out_of_school_view')
                            ->where('batch_id', $batch_id)
                            ->where('is_duplicate', '<>', 1)
                            ->where('willing_to_return_sch', 'NOT LIKE', 'y%');
                        if (isset($bursary_status)) {
                            $qry = DB::table('out_of_school_view')
                                ->where('batch_id', $batch_id)
                                ->where('is_duplicate', '<>', 1);
                            if ($bursary_status == 1) {
                                $qry->Where(function ($qry) {
                                    $qry->whereNotIn('bursary_status', array('no', 'n/a', 'n'));
                                });
                            } else if ($bursary_status == 2) {
                                $qry->Where(function ($qry) {
                                    $qry->whereIn('bursary_status', array('no', 'n/a', 'n'));
                                });
                            }
                        }
                    } else if ($willingness == 2) {
                        $qry = DB::table('out_of_school_view')
                            ->where('batch_id', $batch_id)
                            ->where('is_duplicate', '<>', 1)
                            ->where('willing_to_return_sch', 'NOT LIKE', 'n%');
                        if (isset($bursary_status)) {
                            $qry = DB::table('out_of_school_view')
                                ->where('batch_id', $batch_id)
                                ->where('is_duplicate', '<>', 1);
                            if ($bursary_status == 1) {
                                $qry->Where(function ($qry) {
                                    $qry->whereNotIn('bursary_status', array('no', 'n/a', 'n'));
                                });
                            } else if ($bursary_status == 2) {
                                $qry->Where(function ($qry) {
                                    $qry->whereIn('bursary_status', array('no', 'n/a', 'n'));
                                });
                            }
                        }
                    } else {
                        if (isset($bursary_status)) {
                            if ($bursary_status == 1) {
                                $qry = DB::table('out_of_school_view')
                                    ->where('batch_id', $batch_id)
                                    ->where('is_duplicate', '<>', 1)
                                    ->Where(function ($qry) {
                                        $qry->whereNotIn('bursary_status', array('no', 'n/a', 'n'));
                                    });
                            } else if ($bursary_status == 2) {
                                $qry = DB::table('out_of_school_view')
                                    ->where('batch_id', $batch_id)
                                    ->where('is_duplicate', '<>', 1)
                                    ->Where(function ($qry) {
                                        $qry->whereIn('bursary_status', array('no', 'n/a', 'n'));
                                    });
                            } else {
                                $qry = DB::table('out_of_school_view')
                                    ->where('id', 0);
                            }
                        } else {
                            $qry = DB::table('out_of_school_view')
                                ->where('id', 0);
                        }
                    }
                } else {
                    /* $qry = DB::table('beneficiary_master_info')
                         ->where('id', 0);*/
                    if (isset($bursary_status)) {
                        if ($bursary_status == 1) {
                            $qry = DB::table('out_of_school_view')
                                ->where('batch_id', $batch_id)
                                ->where('is_duplicate', '<>', 1)
                                ->Where(function ($qry) {
                                    $qry->whereNotIn('bursary_status', array('no', 'n/a', 'n'));
                                });
                        } else if ($bursary_status == 2) {
                            $qry = DB::table('out_of_school_view')
                                ->where('batch_id', $batch_id)
                                ->where('is_duplicate', '<>', 1)
                                ->Where(function ($qry) {
                                    $qry->whereIn('bursary_status', array('no', 'n/a', 'n'));
                                });
                        } else {
                            $qry = DB::table('out_of_school_view')
                                ->where('id', 0);
                        }
                    } else {
                        $qry = DB::table('out_of_school_view')
                            ->where('id', 0);
                    }
                }
            }
        } else {
            $qry = DB::table('out_of_school_view')
                ->where('batch_id', $batch_id)
                ->where('is_duplicate', '<>', 1);
        }

        $outOfSchoolFilteredOut = $qry->get();
        $outOfSchoolFilteredOut = convertStdClassObjToArray($outOfSchoolFilteredOut);
        return $outOfSchoolFilteredOut;
    }

    public function getInSchoolFiltered($batch_id, $in_school_check, $in_school_array, $bursary_status)
    {
        if ($in_school_check == 1 || $in_school_check == '1') {
            if (count($in_school_array) > 0) {
                $qry = DB::table('in_school_view')
                    ->where('batch_id', $batch_id)
                    ->where('is_duplicate', '<>', 1)
                    ->whereNotIn('current_school_grade', $in_school_array);
                if (isset($bursary_status)) {
                    if ($bursary_status == 1) {
                        $qry->Where(function ($qry) {
                            $qry->whereNotIn('bursary_status', array('no', 'n/a', 'n'));
                        });
                    } else if ($bursary_status == 2) {
                        $qry->Where(function ($qry) {
                            $qry->whereIn('bursary_status', array('no', 'n/a', 'n'));
                        });
                    }
                }

            } else {
                if (isset($bursary_status)) {
                    if ($bursary_status == 1) {
                        $qry = DB::table('in_school_view')
                            ->where('batch_id', $batch_id)
                            ->where('is_duplicate', '<>', 1)
                            ->Where(function ($qry) {
                                $qry->whereNotIn('bursary_status', array('no', 'n/a', 'n'));
                            });
                    } else if ($bursary_status == 2) {
                        $qry = DB::table('in_school_view')
                            ->where('batch_id', $batch_id)
                            ->where('is_duplicate', '<>', 1)
                            ->Where(function ($qry) {
                                $qry->whereIn('bursary_status', array('no', 'n/a', 'n'));
                            });
                    } else {
                        $qry = DB::table('in_school_view')
                            ->where('id', 0);
                    }
                } else {
                    $qry = DB::table('in_school_view')
                        ->where('id', 0);
                }
            }
        } else {
            $qry = DB::table('in_school_view')
                ->where('batch_id', $batch_id)
                ->where('is_duplicate', '<>', 1);
        }
        $inSchoolFilteredOut = $qry->get();
        $inSchoolFilteredOut = convertStdClassObjToArray($inSchoolFilteredOut);
        return $inSchoolFilteredOut;
    }

    public function getExamClassesFiltered($batch_id, $exam_classes_check, $exam_classes_array)
    {
        if ($exam_classes_check == 1 || $exam_classes_check == '1') {
            if (count($exam_classes_array) > 0) {
                $qry = DB::table('exam_classes_view')
                    ->where('batch_id', $batch_id)
                    ->where('is_duplicate', '<>', 1)
                    ->whereNotIn('current_school_grade', $exam_classes_array);
                if (isset($bursary_status)) {
                    if ($bursary_status == 1) {
                        $qry->Where(function ($qry) {
                            $qry->whereNotIn('bursary_status', array('no', 'n/a', 'n'));
                        });
                    } else if ($bursary_status == 2) {
                        $qry->Where(function ($qry) {
                            $qry->whereIn('bursary_status', array('no', 'n/a', 'n'));
                        });
                    }
                }
            } else {
                if (isset($bursary_status)) {
                    if ($bursary_status == 1) {
                        $qry = DB::table('exam_classes_view')
                            ->where('batch_id', $batch_id)
                            ->where('is_duplicate', '<>', 1)
                            ->Where(function ($qry) {
                                $qry->whereNotIn('bursary_status', array('no', 'n/a', 'n'));
                            });
                    } else if ($bursary_status == 2) {
                        $qry = DB::table('exam_classes_view')
                            ->where('batch_id', $batch_id)
                            ->where('is_duplicate', '<>', 1)
                            ->Where(function ($qry) {
                                $qry->whereIn('bursary_status', array('no', 'n/a', 'n'));
                            });
                    } else {
                        $qry = DB::table('exam_classes_view')
                            ->where('id', 0);
                    }
                } else {
                    $qry = DB::table('exam_classes_view')
                        ->where('id', 0);
                }
            }
        } else {
            $qry = DB::table('exam_classes_view')
                ->where('batch_id', $batch_id)
                ->where('is_duplicate', '<>', 1);
        }
        $examClassesFilteredOut = $qry->get();
        $examClassesFilteredOut = convertStdClassObjToArray($examClassesFilteredOut);
        return $examClassesFilteredOut;
    }

    public function getImportationBatches(Request $req)
    {//franken
        try {
            $status = $req->input('status');
            $merge_status = $req->input('merge_status');
            // $qry = DB::table('batch_info')
            //     ->join('templates', 'batch_info.template_id', '=', 'templates.id')
            //     ->join('batch_statuses', 'batch_info.status', '=', 'batch_statuses.id')
            //     ->join('confirmations', 'batch_info.is_active', '=', 'confirmations.flag')
            //     ->join('users', 'batch_info.generated_by', '=', 'users.id')
            //     ->leftJoin('batch_merging as t6', 'batch_info.id', '=', 't6.batch_id')
            //     ->leftJoin('batch_info as t7', 't6.merged_to', '=', 't7.id')
            //     ->select('batch_info.*', 'batch_statuses.name as status_name', 'templates.id as template_id', 'templates.name as template_name',
            //         'confirmations.name as is_active_name', 'users.first_name', 'users.last_name', 'users.email',
            //         't6.id as is_merged', 't7.batch_no as merged_to_batch');
            //     $qry = $status == '' ? $qry->whereRaw('1=1') : $qry->where('batch_info.status', $status);
            // $qry->whereNull('t6.id');
             $data = DB::select("select `batch_info`.*, `batch_statuses`.`name` as `status_name`, 
                `templates`.`id` as `template_id`, `templates`.`name` as `template_name`, 
                `confirmations`.`name` as `is_active_name`, `users`.`first_name`, 
                `users`.`last_name`, `users`.`email`, `t6`.`id` as `is_merged`, 
                `t7`.`batch_no` as `merged_to_batch` from `batch_info` 
                inner join `templates` on `batch_info`.`template_id` = `templates`.`id` 
                inner join `batch_statuses` on `batch_info`.`status` = `batch_statuses`.`id` 
                inner join `confirmations` on `batch_info`.`is_active` = `confirmations`.`flag` 
                inner join `users` on `batch_info`.`generated_by` = `users`.`id` 
                left join `batch_merging` as `t6` on `batch_info`.`id` = `t6`.`batch_id` 
                left join `batch_info` as `t7` on `t6`.`merged_to` = `t7`.`id` 
                WHERE `t6`.`id` is NULL");
            // $data = $qry->get();
            // dd($qry);
            $data = convertStdClassObjToArray($data);
            $data = decryptArray($data);
            $res = array(
                'results' => $data
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

    public function getMergingBatches(Request $req)
    {
        $status = $req->input('status');
        $qry = DB::table('batch_info')
            ->join('templates', 'batch_info.template_id', '=', 'templates.id')
            ->join('batch_statuses', 'batch_info.status', '=', 'batch_statuses.id')
            ->join('confirmations', 'batch_info.is_active', '=', 'confirmations.flag')
            ->join('users', 'batch_info.generated_by', '=', 'users.id')
            ->leftJoin('batch_merging as t6', 'batch_info.id', '=', 't6.batch_id')
            ->leftJoin('batch_info as t7', 't6.merged_to', '=', 't7.id')
            ->leftJoin('beneficiary_information as t8', function ($join) {
                $join->on('t8.batch_id', '=', 'batch_info.id')
                    ->where('t8.beneficiary_status', '>', 1);
            })
            ->select(DB::raw("batch_info.*, batch_statuses.name as status_name, templates.id as template_id,
                templates.name as template_name, confirmations.name as is_active_name, users.first_name,
                users.last_name, users.email, t6.id as is_merged, t7.batch_no as merged_to_batch,
                count(t8.id) as beneficiary_count"));
        $qry = $status == '' ? $qry->whereRaw('1=1') : $qry->where('batch_info.status', $status);
        $qry->groupBy('batch_info.id');
        $data = $qry->get();
        $data = convertStdClassObjToArray($data);
        $data = decryptArray($data);
        $res = array(
            'results' => $data
        );
        return response()->json($res);
    }

    //franks
    public function getImportsInfo(Request $req)
    {
        try {
            $status = $req->input('start');
            $status = $req->input('limit');
            $qry = DB::table('imports_info')
                ->join('templates', 'imports_info.template_id', '=', 'templates.id')
                ->join('users', 'imports_info.generated_by', '=', 'users.id')
                ->select('imports_info.*', 'templates.id as template_id', 'templates.name as template_name', 'users.first_name', 'users.last_name', 'users.email');
                $results = $qry->skip($start)->take($limit)->get();
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

    // public function getImportsInfo(Request $req)
    // {
    //     try {
    //         $qry = DB::table('imports_info')
    //             ->join('templates', 'imports_info.template_id', '=', 'templates.id')
    //             ->join('users', 'imports_info.generated_by', '=', 'users.id')
    //             ->select('imports_info.*', 'templates.id as template_id', 'templates.name as template_name', 'users.first_name', 'users.last_name', 'users.email');
    //         $results = $qry->get();
    //         $res = array(
    //             'success' => true,
    //             'results' => $results,
    //             'message' => 'All is well'
    //         );
    //     } catch (\Exception $exception) {
    //         $res = array(
    //             'success' => false,
    //             'message' => $exception->getMessage()
    //         );
    //     } catch (\Throwable $throwable) {
    //         $res = array(
    //             'success' => false,
    //             'message' => $throwable->getMessage()
    //         );
    //     }
    //     return response()->json($res);
    // }

    function deleteBatchInfo(Request $req)
    {
        $id = $req->input('id');
        $res = array();
        DB::transaction(function () use ($id, &$res) {
            try {
                DB::table('batch_info')->where('id', $id)->delete();
                DB::table('beneficiary_master_info')->where('batch_id', $id)->delete();
                DB::table('temp_additional_fields_values')->where('batch_id', $id)->delete();
                $res = array(
                    'success' => true,
                    'message' => 'Deletion operation was successful!!'
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

    public function saveInclusionCriteria(Request $req)
    {
        try {
            $is_submit = $req->input('is_submit');
            $batch_id = $req->input('batch_id');
            $willingness = $req->input('willingness');
            $bursary_status = $req->input('bursary_status');

            $outGradeSevenCheckbox = $req->input('outGradeSevenCheckbox');
            $outGradeEightCheckbox = $req->input('outGradeEightCheckbox');
            $outGradeNineCheckbox = $req->input('outGradeNineCheckbox');
            $outGradeTenCheckbox = $req->input('outGradeTenCheckbox');
            $outGradeElevenCheckbox = $req->input('outGradeElevenCheckbox');
            $outGradeTwelveCheckbox = $req->input('outGradeTwelveCheckbox');
            $out_of_school_array = $this->generateOutOfSchoolArray($outGradeSevenCheckbox, $outGradeEightCheckbox, $outGradeNineCheckbox, $outGradeTenCheckbox, $outGradeElevenCheckbox, $outGradeTwelveCheckbox);

            $inGradeSevenCheckbox = $req->input('inGradeSevenCheckbox');
            $inGradeEightCheckbox = $req->input('inGradeEightCheckbox');
            $inGradeNineCheckbox = $req->input('inGradeNineCheckbox');
            $inGradeTenCheckbox = $req->input('inGradeTenCheckbox');
            $inGradeElevenCheckbox = $req->input('inGradeElevenCheckbox');
            $inGradeTwelveCheckbox = $req->input('inGradeTwelveCheckbox');
            $in_school_array = $this->generateInSchoolArray($inGradeSevenCheckbox, $inGradeEightCheckbox, $inGradeNineCheckbox, $inGradeTenCheckbox, $inGradeElevenCheckbox, $inGradeTwelveCheckbox);

            //$examGradeSevenCheckbox = $req->input('examGradeSevenCheckbox');
            //$examGradeNineCheckbox = $req->input('examGradeNineCheckbox');
            //$exam_classes_array = $this->generateExamClassesArray($examGradeSevenCheckbox, $examGradeNineCheckbox);

            $user_id = \Auth::user()->id;
            $post_data = $req->all();
            $table_name = $post_data['table_name'];
            $id = $post_data['id'];
            //unset unnecessary values
            unset($post_data['_token']);
            unset($post_data['id']);
            unset($post_data['table_name']);
            unset($post_data['template_id']);
            unset($post_data['is_submit']);
            $table_data = $post_data;
            //add extra params
            $table_data['created_at'] = Carbon::now();
            $table_data['created_by'] = $user_id;
            $where = array(
                'id' => $id
            );
            $res = array();
            DB::transaction(function () use (&$res, $id, $is_submit, $user_id, $table_name, $where, $table_data, $willingness, $bursary_status, $out_of_school_array, $in_school_array, $batch_id) {
                $this->updateOutofSchoolCategory($willingness, $bursary_status, $out_of_school_array, $batch_id);
                $this->updateInSchoolCategory($bursary_status, $in_school_array, $batch_id);
                //$tempExamClassesSaved = $this->updateExamClassesCategory($bursary_status, $exam_classes_array, $batch_id);
                if (validateisNumeric($id)) {
                    $msg = 'Criteria updated successfully!!';
                    if (recordExists($table_name, $where)) {
                        //set all to null coz of unusual behaviour...better solution later
                        $null_values = array(
                            'out_of_school_check' => null,
                            'outGradeSevenCheckbox' => null,
                            'outGradeEightCheckbox' => null,
                            'outGradeNineCheckbox' => null,
                            'outGradeTenCheckbox' => null,
                            'outGradeElevenCheckbox' => null,
                            'outGradeTwelveCheckbox' => null,
                            'in_school_check' => null,
                            'inGradeSevenCheckbox' => null,
                            'inGradeEightCheckbox' => null,
                            'inGradeNineCheckbox' => null,
                            'inGradeTenCheckbox' => null,
                            'inGradeElevenCheckbox' => null,
                            'inGradeTwelveCheckbox' => null,
                            'exam_classes_check' => null,
                            'examGradeSevenCheckbox' => null,
                            'examGradeNineCheckbox' => null,
                            'willingness_to_back' => null,
                            'bursary_status_check' => null,
                            'bursaryRadio' => null
                        );
                        DB::table('batch_inclusion_criteria')->where('id', $id)->update($null_values);
                        unset($table_data['created_at']);
                        unset($table_data['created_by']);
                        $table_data['updated_at'] = Carbon::now();
                        $table_data['updated_by'] = $user_id;
                        $previous_data = getPreviousRecords($table_name, $where);
                        $success = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                        if ($success) {
                            if ($is_submit == 1) {
                                $msg = 'Data Saved and moved Successfully to the next stage (Data Mapping)!!';
                                DB::table('batch_info')->where('id', $batch_id)->update(array('status' => 3));
                                //log in transition rpt table
                                $log_params = array(
                                    'batch_id' => $batch_id,
                                    'stage_id' => 3,
                                    'to_date' => date(''),
                                    'from_date' => Carbon::now(),
                                    'author' => \Auth::user()->id,
                                    'created_by' => \Auth::user()->id
                                );
                                DB::table('batches_transitional_report')
                                    ->where(array('batch_id' => $batch_id, 'stage_id' => 2))
                                    ->where(function ($query) {
                                        $query->whereNull('to_date')
                                            ->orWhere('to_date', '0000-00-00 00:00:00');
                                    })
                                    ->update(array('to_date' => Carbon::now()));
                                DB::table('batches_transitional_report')->insert($log_params);
                            }
                            $res = array(
                                'success' => true,
                                'message' => $msg
                            );
                        } else {
                            $res = array(
                                'success' => false,
                                'message' => 'Problem encountered while updating data. Try again later!!'
                            );
                        }
                    }
                } else {
                    $criteria_id = insertRecordReturnId($table_name, $table_data, $user_id);
                    if (is_numeric($criteria_id)) {
                        $msg = 'Criteria details saved successfully!!';
                        if ($is_submit == 1) {
                            DB::table('batch_info')->where('id', $batch_id)->update(array('status' => 3));
                            //log in transition rpt table
                            $log_params = array(
                                'batch_id' => $batch_id,
                                'stage_id' => 3,
                                'to_date' => date(''),
                                'from_date' => Carbon::now(),
                                'author' => \Auth::user()->id,
                                'created_by' => \Auth::user()->id
                            );
                            DB::table('batches_transitional_report')
                                ->where(array('batch_id' => $batch_id, 'stage_id' => 2))
                                ->where(function ($query) {
                                    $query->whereNull('to_date')
                                        ->orWhere('to_date', '0000-00-00 00:00:00');
                                })
                                ->update(array('to_date' => Carbon::now()));
                            DB::table('batches_transitional_report')->insert($log_params);
                            $msg = 'Data Saved and moved Successfully to the next stage (Data Mapping)!!';
                        }
                        $res = array(
                            'success' => true,
                            'id' => $criteria_id,
                            'message' => $msg
                        );
                    } else {
                        $res = array(
                            'success' => false,
                            'message' => 'Problem encountered while saving data. Try again later!!'
                        );
                    }
                }
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

    public function generateOutOfSchoolArray($outGradeSevenCheckbox, $outGradeEightCheckbox, $outGradeNineCheckbox, $outGradeTenCheckbox, $outGradeElevenCheckbox, $outGradeTwelveCheckbox)
    {
        $whereArray = array();
        // if ($inGradeFourCheckbox == true) {
        //     $whereArray[] = 4;
        // }
        // if ($inGradeFiveCheckbox == true) {
        //     $whereArray[] = 5;
        // }
        // if ($inGradeSixCheckbox == true) {
        //     $whereArray[] = 6;
        // }
            $whereArray[] = 4;
            $whereArray[] = 5;
            $whereArray[] = 6;
        if ($outGradeSevenCheckbox == true) {
            $whereArray[] = 7;
        }
        if ($outGradeEightCheckbox == true) {
            $whereArray[] = 8;
        }
        if ($outGradeNineCheckbox == true) {
            $whereArray[] = 9;
        }
        if ($outGradeTenCheckbox == true) {
            $whereArray[] = 10;
        }
        if ($outGradeElevenCheckbox == true) {
            $whereArray[] = 11;
        }
        if ($outGradeTwelveCheckbox == true) {
            $whereArray[] = 12;
        }
        return $whereArray;
    }

    public function generateInSchoolArray($inGradeSevenCheckbox, $inGradeEightCheckbox, $inGradeNineCheckbox, $inGradeTenCheckbox, $inGradeElevenCheckbox, $inGradeTwelveCheckbox)
    {
        $whereArray = array();
        // if ($inGradeFourCheckbox == true) {
        //     $whereArray[] = 4;
        // }
        // if ($inGradeFiveCheckbox == true) {
        //     $whereArray[] = 5;
        // }
        // if ($inGradeSixCheckbox == true) {
        // }
            $whereArray[] = 4;
            $whereArray[] = 5;
            $whereArray[] = 6;
        if ($inGradeSevenCheckbox == true) {
            $whereArray[] = 7;
        }
        if ($inGradeEightCheckbox == true) {
            $whereArray[] = 8;
        }
        if ($inGradeNineCheckbox == true) {
            $whereArray[] = 9;
        }
        if ($inGradeTenCheckbox == true) {
            $whereArray[] = 10;
        }
        if ($inGradeElevenCheckbox == true) {
            $whereArray[] = 11;
        }
        if ($inGradeTwelveCheckbox == true) {
            $whereArray[] = 12;
        }
        return $whereArray;
    }

    public function generateExamClassesArray($examGradeSevenCheckbox, $examGradeNineCheckbox)
    {
        $whereArray = array();
        if ($examGradeSevenCheckbox == true) {
            $whereArray[] = 7;
        }
        if ($examGradeNineCheckbox == true) {
            $whereArray[] = 9;
        }
        return $whereArray;
    }

    public function getMappingSummary(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $outOfSchool = DB::table('beneficiary_master_info')
            ->where('batch_id', $batch_id)
            ->where('is_duplicate_with_existing', '<>', 1)
            ->where('category', 1)
            ->where('is_active', 1)
            ->count();
        $inSchool = DB::table('beneficiary_master_info')
            ->where('batch_id', $batch_id)
            ->where('is_duplicate_with_existing', '<>', 1)
            ->whereIn('category', array(2, 3))
            ->where('is_active', 1)
            ->count();
        $examClasses = DB::table('beneficiary_master_info')
            ->where('batch_id', $batch_id)
            ->where('is_duplicate_with_existing', '<>', 1)
            ->where('category', 3)
            ->where('is_active', 1)
            ->count();
        $duplicates = DB::table('beneficiary_master_info')
            ->where('batch_id', $batch_id)
            ->where('is_duplicate', 1)
            ->where('is_active', 1)
            ->count();
        $duplicates_existing = DB::table('beneficiary_master_info')
            ->where('batch_id', $batch_id)
            ->where('is_duplicate_with_existing', 1)
            ->count();
        $total = DB::table('beneficiary_master_info')
            ->where('batch_id', $batch_id)
            // ->where('is_active', 1)
            ->count();
        $res = array(
            'out_of_school_count' => $outOfSchool,
            'in_school_count' => $inSchool,
            'exam_classes_count' => $examClasses,
            'duplicates_count' => $duplicates,
            'duplicates_count_existing' => $duplicates_existing,
            'total_records' => $total
        );
        return response()->json(array('results' => $res));
    }

    public function getChecklistsGenSummary(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $beneficiary_status_id = $req->input('beneficiary_status_id');

            $outOfSchoolQry = DB::table('beneficiary_information')
                ->where('batch_id', $batch_id)
                ->where('category', 1);
            if (validateisNumeric($beneficiary_status_id)) {
                $outOfSchoolQry->where('beneficiary_status', $beneficiary_status_id);
            }
            $outOfSchool = $outOfSchoolQry->count();

            $inSchoolQry = DB::table('beneficiary_information')
                ->where('batch_id', $batch_id)
                ->whereIn('category', array(2, 3));
            if (validateisNumeric($beneficiary_status_id)) {
                $inSchoolQry->where('beneficiary_status', $beneficiary_status_id);
            }
            $inSchool = $inSchoolQry->count();

            $examClassesQry = DB::table('beneficiary_information')
                ->where('batch_id', $batch_id)
                ->where('category', 3);
            if (validateisNumeric($beneficiary_status_id)) {
                $examClassesQry->where('beneficiary_status', $beneficiary_status_id);
            }
            $examClasses = $examClassesQry->count();

            $totalQry = DB::table('beneficiary_information')
                ->where('batch_id', $batch_id);
            if (validateisNumeric($beneficiary_status_id)) {
                $totalQry->where('beneficiary_status', $beneficiary_status_id);
            }
            $total = $totalQry->count();

            $res = array(
                'out_of_school_count' => $outOfSchool,
                'in_school_count' => $inSchool,
                'exam_classes_count' => $examClasses,
                'total_records' => $total
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
        return response()->json(array('results' => $res));
    }

    public function getAnalysisSummary(Request $req)
    {
        $batch_id = $req->batch_id;
        $category_id = $req->category_id;
        $school_id = $req->school_id;
        $cwac_id = $req->cwac_id;
        $verification_type = $req->verification_type;
        $whereIn = array(2, 3);
        if ($category_id == 1) {
            $whereIn = array(1);
        }

        $qry1 = DB::table('beneficiary_information')
            ->where('batch_id', $batch_id)
            ->whereIn('category', $whereIn);
        //->where('category', $category_id)
        //->whereIn('beneficiary_status', array(0, 1, 2, 3));
        if (isset($school_id) && $school_id != '') {
            $qry1->where('school_id', $school_id);
        }
        if (isset($cwac_id) && $cwac_id != '') {
            $qry1->where('cwac_id', $cwac_id);
        }
        if (isset($verification_type) && $verification_type != '') {
            $qry1->where('verification_type', $verification_type);
        }
        $identified = $qry1->count();

        $qry2 = DB::table('beneficiary_information')
            ->where('batch_id', $batch_id)
            ->whereIn('category', $whereIn)
            //->where('category', $category_id)
            ->where('verification_recommendation', 1)
            ->where('beneficiary_status', 3);
        if (isset($school_id) && $school_id != '') {
            $qry2->where('school_id', $school_id);
        }
        if (isset($cwac_id) && $cwac_id != '') {
            $qry2->where('cwac_id', $cwac_id);
        }
        if (isset($verification_type) && $verification_type != '') {
            $qry2->where('verification_type', $verification_type);
        }
        $recommended = $qry2->count();

        $qry3 = DB::table('beneficiary_information')
            ->where('batch_id', $batch_id)
            ->whereIn('category', $whereIn)
            //->where('category', $category_id)
            ->where('verification_recommendation', 2)
            ->where('beneficiary_status', 3);
        if (isset($school_id) && $school_id != '') {
            $qry3->where('school_id', $school_id);
        }
        if (isset($cwac_id) && $cwac_id != '') {
            $qry3->where('cwac_id', $cwac_id);
        }
        if (isset($verification_type) && $verification_type != '') {
            $qry3->where('verification_type', $verification_type);
        }
        $unrecommended = $qry3->count();

        $qry4 = DB::table('beneficiary_information')
            ->where('batch_id', $batch_id)
            ->whereIn('category', $whereIn)
            //->where('category', $category_id)
            ->where('verification_recommendation', 3)
            ->where('beneficiary_status', 3);
        if (isset($school_id) && $school_id != '') {
            $qry4->where('school_id', $school_id);
        }
        if (isset($cwac_id) && $cwac_id != '') {
            $qry4->where('cwac_id', $cwac_id);
        }
        if (isset($verification_type) && $verification_type != '') {
            $qry4->where('verification_type', $verification_type);
        }
        $notfound = $qry4->count();

        $qry5 = DB::table('beneficiary_information')
            ->where('batch_id', $batch_id)
            ->whereIn('category', $whereIn)
            // ->where('category', $category_id)
            ->where('beneficiary_status', 2);
        if (isset($school_id) && $school_id != '') {
            $qry5->where('school_id', $school_id);
        }
        if (isset($cwac_id) && $cwac_id != '') {
            $qry5->where('cwac_id', $cwac_id);
        }
        if (isset($verification_type) && $verification_type != '') {
            $qry5->where('verification_type', $verification_type);
        }
        $notSubmitted = $qry5->count();

        $qry6 = DB::table('beneficiary_information')
            ->where('batch_id', $batch_id)
            ->whereIn('category', $whereIn)
            // ->where('category', $category_id)
            ->where('beneficiary_status', '>', 3);
        if (isset($school_id) && $school_id != '') {
            $qry6->where('school_id', $school_id);
        }
        if (isset($cwac_id) && $cwac_id != '') {
            $qry6->where('cwac_id', $cwac_id);
        }
        if (isset($verification_type) && $verification_type != '') {
            $qry6->where('verification_type', $verification_type);
        }
        $forwarded = $qry6->count();

        /*$total = DB::table('beneficiary_information')
            ->where('category', $category_id)
            ->where('batch_id', $batch_id)
            ->where('beneficiary_status', 3)
            ->count();*/
        $res = array(
            'recommended_girls_count' => $recommended,
            'unrecommended_girls_count' => $unrecommended,
            'notfound_girls_count' => $notfound,
            'identified_girls_count' => $identified,
            'verified_notsubmitted_count' => $notSubmitted,
            'forwarded_count' => $forwarded
        );
        return response()->json(array('results' => $res));
    }

    public function getPlacementAnalysisSummary(Request $req)
    {
        $batch_id = $req->batch_id;
        $category_id = 3;//$req->category_id;
        $school_id = $req->school_id;
        $cwac_id = $req->cwac_id;

        $qry1 = DB::table('beneficiary_information')
            ->where('batch_id', $batch_id)
            ->where('category', $category_id)
            ->where('verification_recommendation', 1)
            ->where('school_placement_status', 1)
            ->where('beneficiary_status', 3);
        if (isset($school_id) && $school_id != '') {
            $qry1->where('school_id', $school_id);
        }
        if (isset($cwac_id) && $cwac_id != '') {
            $qry1->where('cwac_id', $cwac_id);
        }
        $placed_girls = $qry1->count();

        $qry2 = DB::table('beneficiary_information')
            ->where('batch_id', $batch_id)
            ->where('category', $category_id)
            ->where('verification_recommendation', 1)
            ->where('school_placement_status', 2)
            ->where('beneficiary_status', 3);
        if (isset($school_id) && $school_id != '') {
            $qry2->where('school_id', $school_id);
        }
        if (isset($cwac_id) && $cwac_id != '') {
            $qry2->where('cwac_id', $cwac_id);
        }
        $unplaced_girls = $qry2->count();
        $res = array(
            'placed_girls_count' => $placed_girls,
            'unplaced_girls_count' => $unplaced_girls
        );
        return response()->json(array('results' => $res));
    }

    public function getLettersGenSummary(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $verification_type = $req->input('verification_type');
        $school_id = $req->input('school_id');
        $qry1 = DB::table('beneficiary_information')
            ->where('batch_id', $batch_id)
            ->where('category', 1)
            ->where('verification_recommendation', 1)
            ->where('beneficiary_status', 4)
            ->where('enrollment_status', 1);
        if (isset($school_id) && $school_id != '') {
            $qry1->where('school_id', $school_id);
        }
        if (validateisNumeric($verification_type)) {
            $qry1->where('verification_type', $verification_type);
        }
        $recommended_out_of_school = $qry1->count();

        $qry2 = DB::table('beneficiary_information')
            ->where('batch_id', $batch_id)
            ->whereIn('category', array(2, 3))
            ->where('verification_recommendation', 1)
            ->where('beneficiary_status', 4)
            ->where('enrollment_status', 1);
        if (isset($school_id) && $school_id != '') {
            $qry2->where('school_id', $school_id);
        }
        if (validateisNumeric($verification_type)) {
            $qry2->where('verification_type', $verification_type);
        }
        $recommended_in_school = $qry2->count();

        /*$qry3 = DB::table('beneficiary_information')
            ->where('batch_id', $batch_id)
            ->where('category', 3)
            ->where('verification_recommendation', 1)
            ->where('beneficiary_status', 4);
        if (isset($school_id) && $school_id != '') {
            $qry3->where('school_id', $school_id);
        }
        $recommended_exam_classes = $qry3->count();*/

        $res = array(
            'recommended_out_of_sch_count' => $recommended_out_of_school,
            'recommended_in_sch_count' => $recommended_in_school
            //'recommended_exam_classes_count' => $recommended_exam_classes
        );
        return response()->json(array('results' => $res));
    }

    function updateMappingLogAnyway(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $category = $req->input('category');
            $mappingLog = DB::table('batch_mapping_log')
                ->where('batch_id', $batch_id)
                ->where('category_id', $category)
                ->first();
            if (is_null($mappingLog)) {
                $mappingLogParams = array(
                    'batch_id' => $batch_id,
                    'category_id' => $category,
                    'initial_mapping_by' => \Auth::user()->id
                );
                DB::table('batch_mapping_log')->insert($mappingLogParams);
            }
            $res = array(
                'success' => true,
                'message' => 'Logging done successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    function generator($batch_id, $category, $map_limit = '')
    {
        $where = array(
            'batch_id' => $batch_id,
            'is_active' => 1
        );
        $whereCat = array($category);
        if ($category == 2) {
            $whereCat = array(2, 3);
        }
        // $qry = DB::table('beneficiary_master_info')
        //     ->where($where)
        //     ->whereIn('category', $whereCat)
        //     ->where(("SELECT tcode.id FROM mapping_error_log tcode
        //          where tcode.batch_id = $batch_id"), '<>', id);
        //     ->where('is_duplicate_with_existing', '<>', 1)
        //     ->where('is_mapped', '<>', 1);
        // $qry->limit(5000);

        $qry = DB::table('beneficiary_master_info')
            ->where($where)
            ->whereIn('category', $whereCat)
            ->whereNotIn('id', function($q) use ($batch_id) {
                $q->select('tcode.id')
                  ->from('mapping_error_log as tcode')
                  ->where('tcode.batch_id', $batch_id);
            })
            ->where('is_duplicate_with_existing', '<>', 1)
            ->where('is_mapped', '<>', 1)
            ->limit(5000);

        // if (is_numeric($map_limit) && $map_limit != '') {
        //     $qry->limit($map_limit);
        // }

        $data = $qry->get();
        foreach ($data as $datum) {
            yield $datum;
        }
    }

    function mapOutOfSchoolBatchData(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $category = $req->input('category');
            $map_limit = $req->input('map_limit');
            $user_id = \Auth::user()->id;
            $dms_user = \Auth::user()->dms_id;
            $user_email = aes_decrypt(\Auth::user()->email);
            $where = array(
                'batch_id' => $batch_id,
                'category' => $category
            );
            $size = 100;
            $durationParams = array(
                'batch_id' => $batch_id,
                'started_at' => Carbon::now(),
                'created_by' => \Auth::user()->id,
                'created_at' => Carbon::now()
            );
            $timeTakenId = DB::table('batchmaptime')->insertGetId($durationParams);

            $res = array();
            if (!is_numeric($map_limit) || $map_limit < 1) {
                $res = array(
                    'success' => false,
                    'message' => 'Invalid Map Limit Specified!!'
                );
                return response()->json($res);
            }
            DB::transaction(function () use ($map_limit, $batch_id, $user_id, $dms_user, $user_email, $where, $category, &$res) {
                $mappingLogParams = array(
                    'batch_id' => $batch_id,
                    'category_id' => $category,
                    'map_limit' => $map_limit,
                    'initial_mapping_by' => \Auth::user()->id,
                    'created_at' => Carbon::now()
                );
                DB::table('batch_mapping_log')->insert($mappingLogParams);

                //todo map beneficiary main info
                $last_record_id = DB::table('beneficiary_information')->max('id');
                $serial_number_counter = $last_record_id + 1;
                $serial_number = str_pad($serial_number_counter, 4, 0, STR_PAD_LEFT);

                $batch_details = DB::table('batch_info')
                    ->select(DB::raw('YEAR(generated_on) as gen_year'))
                    ->where('id', $batch_id)
                    ->first();
                if (!is_null($batch_details)) {
                    $year = substr($batch_details->gen_year, -2);
                } else {
                    $year = substr(date('Y'), -2);
                }
                $beneficiary_details = array();
                $mapped_master_ids = array();
                foreach ($this->generator($batch_id, $category, $map_limit) as $datum) {
                    $mappedChecker = 0;
                    //check for home district(SCT) details
                    $district_id = $this->getDistrictID($datum->sct_district);
                    if ($district_id === false || $district_id == 0) {
                        $log_data = array(
                            'batch_id' => $batch_id,
                            'category' => $category,
                            'error_type' => 8,
                            'beneficiary_master_id' => $datum->id,
                            'error' => 'No SCT District details were found'
                        );
                        $error_check_where = array(
                            'beneficiary_master_id' => $datum->id,
                            'error_type' => 8,
                            'category' => $category,
                            'batch_id' => $batch_id
                        );
                        $errExists = DB::table('mapping_error_log')->where($error_check_where)->first();
                        if (is_null($errExists)) {
                            DB::table('mapping_error_log')->insert($log_data);
                        }
                        $mappedChecker = $mappedChecker + 1;
                    }
                    //check for home province of the beneficiary....using SCT district
                    $province_id = $this->getProvinceID($datum->sct_district);

                    //check for constituency details
                    $constituency_id = $this->getConstituencyID($datum->constituency);
                    if ($constituency_id === false || $constituency_id == 0) {
                        //no error logging
                        $district_id = $this->getDistrictID($datum->sct_district);
                        $addData = array(
                            'name' => $this->extractCodeName($datum->constituency, 2),
                            'code' => $this->extractCodeName($datum->constituency, 1),
                            'district_id' => $district_id,
                            'created_at' => Carbon::now(),
                            'created_by' => $user_id
                        );
                        $constituency_id = $this->justAddConstituencyParam($addData);
                    }
                    //check for ward details
                    $ward_id = $this->getWardID($datum->ward);
                    if ($ward_id === false || $ward_id == 0) {
                        //no error logging
                        $constituency_id = $this->getConstituencyID($datum->constituency);
                        $addData = array(
                            'name' => $this->extractCodeName($datum->ward, 2),
                            'code' => $this->extractCodeName($datum->ward, 1),
                            'constituency_id' => $constituency_id,
                            'created_at' => Carbon::now(),
                            'created_by' => $user_id
                        );
                        $ward_id = $this->justAddWardParam($addData);
                    }
                    //check for CWAC details
                    $cwac_id = $this->getCwacID($datum->cwac);
                    if ($cwac_id === false || $cwac_id == 0) {
                        $log_data = array(
                            'batch_id' => $batch_id,
                            'category' => $category,
                            'error_type' => 3,
                            'beneficiary_master_id' => $datum->id,
                            'error' => 'No CWAC details were found'
                        );
                        $error_check_where = array(
                            'beneficiary_master_id' => $datum->id,
                            'error_type' => 3,
                            'category' => $category,
                            'batch_id' => $batch_id
                        );
                        $errExists = DB::table('mapping_error_log')->where($error_check_where)->first();
                        if (is_null($errExists)) {
                            DB::table('mapping_error_log')->insert($log_data);
                        }
                        $mappedChecker = $mappedChecker + 1;
                    }
                    //check for ACC details
                    $acc_id = $this->getAccID($datum->acc);
                    if ($acc_id === false || $acc_id == 0) {
                        //no error logging
                        $ward_id = $this->getWardID($datum->ward);
                        $addData = array(
                            'name' => $this->extractCodeName($datum->acc, 2),
                            'code' => $this->extractCodeName($datum->acc, 1),
                            'ward_id' => $ward_id,
                            'created_at' => Carbon::now(),
                            'created_by' => $user_id
                        );
                        $acc_id = $this->justAddAccParam($addData);
                    }
                    //check for household details
                    $household_id = $this->getHouseHoldID($datum->hhh_nrc);
                    if ($household_id === false || $household_id == 0) {
                        //log error and continue///--changes...no logging for this, just add the missing details
                        //get CWAC ID
                        $cwac_id = $this->getCWACID($datum->cwac);
                        //get ACC ID
                        $acc_id = $this->getACCID($datum->acc);
                        $addData = array(
                            'number_in_cwac' => $datum->hh_in_cwac,
                            'cwac_id' => $cwac_id,
                            'acc_id' => $acc_id,
                            'hhh_nrc_number' => $datum->hhh_nrc,
                            'hhh_fname' => $datum->hhh_fname,
                            'hhh_lname' => $datum->hhh_lname,
                            'created_at' => Carbon::now(),
                            'created_by' => $user_id
                        );
                        $household_id = $this->justAddHouseHoldParam($addData);
                    }
                    if ($mappedChecker < 1 || $mappedChecker == 0) {
                        $district_id_ben_id = str_pad($district_id, 4, 0, STR_PAD_LEFT);
                        $beneficiary_id = $year . $district_id_ben_id . $serial_number;
                        //start DMS UPDATE
                        $folder_id = '';
                        /* $parent_id = 2;
                         $main_module_id = 1;
                         $description = $datum->girl_fname . ' ' . $datum->girl_lname;
                         $folder_id = createDMSParentFolder($parent_id, $main_module_id, $beneficiary_id, $description, $dms_user);
                         createDMSModuleFolders($folder_id, $main_module_id, $dms_user);*/
                        //end DMS UPDATE
                        $mapped_master_ids[] = array(
                            'id' => $datum->id
                        );
                        $beneficiary_details[] = array(
                            'beneficiary_id' => $beneficiary_id,
                            'household_id' => $household_id,
                            'first_name' => aes_encrypt($datum->girl_fname),
                            'last_name' => aes_encrypt($datum->girl_lname),
                            'dob' => converter11($datum->girl_dob),
                            'relation_to_hhh' => $datum->relation_to_hhh,
                            'school_going' => $datum->school_going,
                            'qualified_sec_sch' => $datum->qualified_sec_sch,
                            'willing_to_return_sch' => $datum->willing_to_return_sch,
                            'highest_grade' => $datum->highest_grade,
                            'current_school_grade' => $datum->current_school_grade,
                            'district_txt' => $datum->sct_district,
                            'cwac_txt' => $datum->cwac,
                            'cwac_id' => $cwac_id,
                            'acc_id' => $acc_id,
                            'ward_id' => $ward_id,
                            'constituency_id' => $constituency_id,
                            'district_id' => $district_id,
                            'province_id' => $province_id,
                            'bursary_status' => $datum->bursary_status,
                            'type_of_bursary' => $datum->type_of_bursary,
                            'initial_category' => $datum->initial_category,
                            'category' => $category,
                            'beneficiary_status' => 1,
                            'master_id' => $datum->id,
                            'batch_id' => $batch_id,
                            'created_by' => $user_id,
                            'folder_id' => $folder_id,
                            'is_migrated' => 0
                        );
                        $serial_number_counter = $serial_number_counter + 1;
                        $serial_number = str_pad($serial_number_counter, 4, 0, STR_PAD_LEFT);
                        //delete all errors if any
                        DB::table('mapping_error_log')->where('beneficiary_master_id', $datum->id)->delete();
                    } else {
                        //not mapped so leave it
                    }
                }

                $mapped_master_IDs = convertAssArrayToSimpleArray($mapped_master_ids, 'id');
                DB::table('beneficiary_master_info')
                    ->whereIn('id', $mapped_master_IDs)
                    ->update(array('is_mapped' => 1));
                $chunks = array_chunk($beneficiary_details, 100);
                // $chunks = array_chunk($beneficiary_details, $size);
                foreach ($chunks as $chunk) {
                    DB::table('beneficiary_information')->insert($chunk);
                }
                //get mapping summary
                $mapped = DB::table('beneficiary_master_info')
                    ->where(array('batch_id' => $batch_id, 'category' => $category, 'is_mapped' => 1, 'is_active' => 1))->count();
                $unmapped = DB::table('beneficiary_master_info')
                    ->where(array('batch_id' => $batch_id, 'category' => $category, 'is_mapped' => 0, 'is_active' => 1))->where('is_duplicate_with_existing', '<>', 1)->count();
                //todo map beneficiary additional info
                $res = array(
                    'success' => true,
                    'message' => 'Operation was successful. Check on the error log for any error encountered!!',
                    'mapped' => $mapped,
                    'unmapped' => $unmapped
                );
            }, 5);

            $durationParams = array(
                'ended_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            );
            DB::table('batchmaptime')
                ->where('id', $timeTakenId)->update($durationParams);

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


    function mapInSchoolBatchData(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $category = $req->input('category');
            $map_limit = $req->input('map_limit');
            $user_id = \Auth::user()->id;
            $dms_user = \Auth::user()->dms_id;
            $user_email = aes_decrypt(\Auth::user()->email);
            $where = array(
                'batch_id' => $batch_id,
                'category' => $category
            );
            $res = array();
            if (!is_numeric($map_limit) || $map_limit < 1) {
                $res = array(
                    'success' => false,
                    'message' => 'Invalid Map Limit Specified!!'
                );
                return response()->json($res);
            }
            // DB::transaction(function () use ($map_limit, $batch_id, $user_id, $dms_user, $user_email, $where, $category, &$res) {
                $mappingLogParams = array(
                    'batch_id' => $batch_id,
                    'category_id' => $category,
                    'map_limit' => $map_limit,
                    'initial_mapping_by' => \Auth::user()->id,
                    'created_at' => Carbon::now()
                );
                DB::table('batch_mapping_log')->insert($mappingLogParams);
                //todo map beneficiary main info
                $last_record_id = DB::table('beneficiary_information')->max('id');
                $serial_number_counter = $last_record_id + 1;
                $serial_number = str_pad($serial_number_counter, 4, 0, STR_PAD_LEFT);
                $batch_details = DB::table('batch_info')
                    ->select(DB::raw('YEAR(generated_on) as gen_year'))
                    ->where('id', $batch_id)
                    ->first();
                if (!is_null($batch_details)) {
                    $year = substr($batch_details->gen_year, -2);
                } else {
                    $year = substr(date('Y'), -2);
                }
                $beneficiary_details = array();
                $mapped_master_ids = array();
                $counts = 0;

                $where = array(
                    'batch_id' => $batch_id,
                    'is_active' => 1
                );
                $whereCat = array($category);
                if ($category == 2) {
                    $whereCat = array(2, 3);
                }
                $qry = DB::table('beneficiary_master_info')
                    ->where($where)
                    ->whereIn('category', $whereCat)
                    ->whereNotIn('id', function($q) use ($batch_id) {
                        $q->select('tcode.id')
                          ->from('mapping_error_log as tcode')
                          ->where('tcode.batch_id', $batch_id);
                    })
                    ->where('is_duplicate_with_existing', '<>', 1)
                    ->where('is_mapped', '<>', 1)
                    ->limit(5000);
                $geno = $qry->get();
                // $geno = $this->generator($batch_id, $category, $map_limit);
                //     dd($geno);

                // foreach ($this->generator($batch_id, $category, $map_limit) as $datum) 
                // {
                foreach ($geno as $datum) {
                    $codeArray = explode('-', $datum->school_code);
                    $main_sch_code = end($codeArray);
                    $qry = DB::table('school_information')->where('code', $main_sch_code);
                    $data = $qry->first();
                    if (is_null($data)) {
                        $school_id = null;
                        $log_data = array(
                            'batch_id' => $batch_id,
                            'category' => $category,
                            'error_type' => 2,
                            'beneficiary_master_id' => $datum->id,
                            'error' => 'No School details were found'
                        );
                        $error_check_where = array(
                            'beneficiary_master_id' => $datum->id,
                            'error_type' => 2,
                            'category' => $category,
                            'batch_id' => $batch_id
                        );
                        $errExists = DB::table('mapping_error_log')
                            ->where($error_check_where)->first();
                        if (is_null($errExists)) {
                            DB::table('mapping_error_log')->insert($log_data);
                        }
                    } else {                        
                        if($counts == $map_limit) {
                            break;
                        } else {
                            $counts++;
                        }
                        $school_id = $data->id;
                        //check for school district details {only for in school}
                        $district_id = $this->getDistrictID($datum->sct_district);
                        $sch_district_id = $this->getSchoolDistrictID($datum->district_name);
                        $province_id = $this->getProvinceID($datum->sct_district);
                        $constituency_id = $this->getConstituencyID($datum->constituency);
                        $ward_id = $this->getWardID($datum->ward);
                        // $school_id = $this->getSchoolID($datum->school_code);
                        $cwac_id = $this->getCwacID($datum->cwac);
                        $acc_id = $this->getAccID($datum->acc);
                        $household_id = $this->getHouseHoldID($datum->hhh_nrc);
                        $district_id = $this->getDistrictID($datum->sct_district);
                        $district_id_ben_id = str_pad($district_id,4,0, STR_PAD_LEFT);
                        $beneficiary_id = $year . $district_id_ben_id . $serial_number;
                        //start DMS UPDATE
                        $folder_id = '';
                        $mapped_master_ids[] = array(
                            'id' => $datum->id
                        );
                        $beneficiary_details[] = array(
                            'beneficiary_id' => $beneficiary_id,
                            'household_id' => $household_id,
                            'first_name' => aes_encrypt($datum->girl_fname),
                            'last_name' => aes_encrypt($datum->girl_lname),
                            //'dob' => $datum->girl_dob,
                            'dob' => converter11($datum->girl_dob),
                            'relation_to_hhh' => $datum->relation_to_hhh,
                            'school_going' => $datum->school_going,
                            'qualified_sec_sch' => $datum->qualified_sec_sch,
                            'willing_to_return_sch' => $datum->willing_to_return_sch,
                            'highest_grade' => $datum->highest_grade,
                            'current_school_grade' => $datum->current_school_grade,
                            'district_txt' => $datum->sct_district,
                            'cwac_txt' => $datum->cwac,
                            'school_id' => $school_id,
                            'exam_school_id' => $school_id,
                            'cwac_id' => $cwac_id,
                            'acc_id' => $acc_id,
                            'ward_id' => $ward_id,
                            'constituency_id' => $constituency_id,
                            'district_id' => $district_id,
                            'province_id' => $province_id,
                            'bursary_status' => $datum->bursary_status,
                            'type_of_bursary' => $datum->type_of_bursary,
                            'initial_category' => $datum->initial_category,
                            'category' => $category,
                            'beneficiary_status' => 1,
                            'master_id' => $datum->id,
                            'batch_id' => $batch_id,
                            'created_by' => $user_id,
                            'folder_id' => $folder_id,
                            'is_migrated' => 0
                        );
                        $serial_number_counter = $serial_number_counter + 1;
                        $serial_number = str_pad($serial_number_counter, 4, 0, STR_PAD_LEFT);
                        //delete all errors if any
                        DB::table('mapping_error_log')
                            ->where('beneficiary_master_id',$datum->id)->delete();
                        DB::table('map_logs')->insert(array(
                            'girl_id' => $beneficiary_id,
                            'master_id' => $datum->id
                        ));
                    }
                }
                $mapped_master_IDs = convertAssArrayToSimpleArray($mapped_master_ids, 'id');
                DB::table('beneficiary_master_info')
                    ->whereIn('id', $mapped_master_IDs)
                    ->update(array('is_mapped' => 1));
                $size = 100;
                $chunks = array_chunk($beneficiary_details, $size);
                foreach ($chunks as $chunk) {
                    DB::table('beneficiary_information')->insert($chunk);
                }
                //get mapping summary
                $mapped = DB::table('beneficiary_master_info')
                    ->where(array('batch_id' => $batch_id, 
                        'category' => $category, 'is_mapped' => 1, 
                        'is_active' => 1))->count();
                $unmapped = DB::table('beneficiary_master_info')
                    ->where(array('batch_id' => $batch_id, 
                        'category' => $category, 'is_mapped' => 0, 
                        'is_active' => 1))
                    ->where('is_duplicate_with_existing', '<>', 1)->count();
                //todo map beneficiary additional info
                $res = array(
                    'success' => true,
                    'message' => 'Operation was successful. Check on the error log for any error encountered!!',
                    'mapped' => $mapped,
                    'unmapped' => $unmapped
                );
            // }, 5);
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

    // function mapInSchoolBatchData(Request $req)
    // {
    //     try {
    //         $batch_id = $req->input('batch_id');
    //         $category = $req->input('category');
    //         $map_limit = 2;//$req->input('map_limit');
    //         $user_id = \Auth::user()->id;
    //         $dms_user = \Auth::user()->dms_id;
    //         $user_email = aes_decrypt(\Auth::user()->email);
    //         $where = array(
    //             'batch_id' => $batch_id,
    //             'category' => $category
    //         );
    //         $res = array();
    //         if (!is_numeric($map_limit) || $map_limit < 1) {
    //             $res = array(
    //                 'success' => false,
    //                 'message' => 'Invalid Map Limit Specified!!'
    //             );
    //             return response()->json($res);
    //         }
    //         DB::transaction(function () use ($map_limit, $batch_id, $user_id, $dms_user, $user_email, $where, $category, &$res) {
    //             $mappingLogParams = array(
    //                 'batch_id' => $batch_id,
    //                 'category_id' => $category,
    //                 'map_limit' => $map_limit,
    //                 'initial_mapping_by' => \Auth::user()->id,
    //                 'created_at' => Carbon::now()
    //             );
    //             DB::table('batch_mapping_log')->insert($mappingLogParams);

    //             //todo map beneficiary main info
    //             $last_record_id = DB::table('beneficiary_information')->max('id');
    //             $serial_number_counter = $last_record_id + 1;
    //             $serial_number = str_pad($serial_number_counter, 4, 0, STR_PAD_LEFT);

    //             $batch_details = DB::table('batch_info')
    //                 ->select(DB::raw('YEAR(generated_on) as gen_year'))
    //                 ->where('id', $batch_id)
    //                 ->first();
    //             if (!is_null($batch_details)) {
    //                 $year = substr($batch_details->gen_year, -2);
    //             } else {
    //                 $year = substr(date('Y'), -2);
    //             }

    //             $beneficiary_details = array();
    //             $mapped_master_ids = array();
    //             foreach ($this->generator($batch_id, $category, $map_limit) as $datum) {
    //                 $codeArray = explode('-', $datum->school_code);
    //                 $main_sch_code = end($codeArray);
    //                 $qry = DB::table('school_information')
    //                     ->where('code', $main_sch_code);
    //                 $data = $qry->first();
    //                 if (is_null($data)) {
    //                     $school_id = null;
    //                 } else {
    //                    $school_id = $data->id;
    //                 }
    //                 $district_id = $this->getDistrictID($datum->sct_district);
    //                 $sch_district_id = $this->getSchoolDistrictID($datum->district_name);
    //                 $province_id = $this->getProvinceID($datum->sct_district);
    //                 $constituency_id = $this->getConstituencyID($datum->constituency);
    //                 $ward_id = $this->getWardID($datum->ward);
    //                 $school_id = $this->getSchoolID($datum->school_code);
    //                 $cwac_id = $this->getCwacID($datum->cwac);
    //                 $acc_id = $this->getAccID($datum->acc);
    //                 $household_id = $this->getHouseHoldID($datum->hhh_nrc);

    //                 $mappedChecker = array(
    //                     'district_id' => $district_id,
    //                     'sch_district_id' => $sch_district_id,
    //                     'province_id' => $province_id,
    //                     'constituency_id' => $constituency_id,
    //                     'ward_id' => $ward_id,
    //                     'school_id' => $school_id,
    //                     'cwac_id' => $cwac_id,
    //                     'acc_id' => $acc_id,
    //                     'household_id' => $household_id
    //                 );

    //                 dd($mappedChecker);

    //                 /* if ($datum->current_school_grade < 8) {
    //                      continue;
    //                  }*/
    //                 $mappedChecker = 0;
    //                 //check for school district details {only for in school}
    //                 $sch_district_id = $this->getSchoolDistrictID($datum->district_name);

    //                 if ($sch_district_id === false || $sch_district_id == 0) {
    //                     $log_data = array(
    //                         'batch_id' => $batch_id,
    //                         'category' => $category,
    //                         'error_type' => 7,
    //                         'beneficiary_master_id' => $datum->id,
    //                         'error' => 'No School District details were found'
    //                     );
    //                     $error_check_where = array(
    //                         'beneficiary_master_id' => $datum->id,
    //                         'error_type' => 7,
    //                         'category' => $category,
    //                         'batch_id' => $batch_id
    //                     );
    //                     $errExists = DB::table('mapping_error_log')->where($error_check_where)->first();
    //                     if (is_null($errExists)) {
    //                         DB::table('mapping_error_log')->insert($log_data);
    //                     }
    //                     $mappedChecker = $mappedChecker + 1;
    //                 }
    //                 //check for home district(SCT) details
    //                 $district_id = $this->getDistrictID($datum->sct_district);

    //                 if ($district_id === false || $district_id == 0) {
    //                     $log_data = array(
    //                         'batch_id' => $batch_id,
    //                         'category' => $category,
    //                         'error_type' => 8,
    //                         'beneficiary_master_id' => $datum->id,
    //                         'error' => 'No SCT District details were found'
    //                     );
    //                     $error_check_where = array(
    //                         'beneficiary_master_id' => $datum->id,
    //                         'error_type' => 8,
    //                         'category' => $category,
    //                         'batch_id' => $batch_id
    //                     );
    //                     $errExists = DB::table('mapping_error_log')->where($error_check_where)->first();
    //                     if (is_null($errExists)) {
    //                         DB::table('mapping_error_log')->insert($log_data);
    //                     }
    //                     $mappedChecker = $mappedChecker + 1;
    //                 }
    //                 //check for home province of the beneficiary....using SCT district
    //                 $province_id = $this->getProvinceID($datum->sct_district);

    //                 //check for constituency details
    //                 $constituency_id = $this->getConstituencyID($datum->constituency);
    //                 if ($constituency_id === false || $constituency_id == 0) {
    //                     //no error logging
    //                     $district_id = $this->getDistrictID($datum->sct_district);
    //                     $addData = array(
    //                         'name' => $this->extractCodeName($datum->constituency, 2),
    //                         'code' => $this->extractCodeName($datum->constituency, 1),
    //                         'district_id' => $district_id,
    //                         'created_at' => Carbon::now(),
    //                         'created_by' => $user_id
    //                     );
    //                     $constituency_id = $this->justAddConstituencyParam($addData);
    //                 }
    //                 //check for ward details
    //                 $ward_id = $this->getWardID($datum->ward);
    //                 if ($ward_id === false || $ward_id == 0) {
    //                     //no error logging
    //                     $constituency_id = $this->getConstituencyID($datum->constituency);
    //                     $addData = array(
    //                         'name' => $this->extractCodeName($datum->ward, 2),
    //                         'code' => $this->extractCodeName($datum->ward, 1),
    //                         'constituency_id' => $constituency_id,
    //                         'created_at' => Carbon::now(),
    //                         'created_by' => $user_id
    //                     );
    //                     $ward_id = $this->justAddWardParam($addData);
    //                 }
    //                 //check for school details {only for in school}
    //                 $school_id = $this->getSchoolID($datum->school_code);
    //                 if ($school_id === false || $school_id == 0) {
    //                     $log_data = array(
    //                         'batch_id' => $batch_id,
    //                         'category' => $category,
    //                         'error_type' => 2,
    //                         'beneficiary_master_id' => $datum->id,
    //                         'error' => 'No School details were found'
    //                     );
    //                     $error_check_where = array(
    //                         'beneficiary_master_id' => $datum->id,
    //                         'error_type' => 2,
    //                         'category' => $category,
    //                         'batch_id' => $batch_id
    //                     );
    //                     $errExists = DB::table('mapping_error_log')->where($error_check_where)->first();
    //                     if (is_null($errExists)) {
    //                         DB::table('mapping_error_log')->insert($log_data);
    //                     }
    //                     $mappedChecker = $mappedChecker + 1;
    //                 }
    //                 //check for CWAC details
    //                 $cwac_id = $this->getCwacID($datum->cwac);
    //                 if ($cwac_id === false || $cwac_id == 0) {
    //                     $log_data = array(
    //                         'batch_id' => $batch_id,
    //                         'category' => $category,
    //                         'error_type' => 3,
    //                         'beneficiary_master_id' => $datum->id,
    //                         'error' => 'No CWAC details were found'
    //                     );
    //                     $error_check_where = array(
    //                         'beneficiary_master_id' => $datum->id,
    //                         'error_type' => 3,
    //                         'category' => $category,
    //                         'batch_id' => $batch_id
    //                     );
    //                     $errExists = DB::table('mapping_error_log')->where($error_check_where)->first();
    //                     if (is_null($errExists)) {
    //                         DB::table('mapping_error_log')->insert($log_data);
    //                     }
    //                     $mappedChecker = $mappedChecker + 1;
    //                 }
    //                 //check for ACC details
    //                 $acc_id = $this->getAccID($datum->acc);
    //                 if ($acc_id === false || $acc_id == 0) {
    //                     //no error logging
    //                     $ward_id = $this->getWardID($datum->ward);
    //                     $addData = array(
    //                         'name' => $this->extractCodeName($datum->acc, 2),
    //                         'code' => $this->extractCodeName($datum->acc, 1),
    //                         'ward_id' => $ward_id,
    //                         'created_at' => Carbon::now(),
    //                         'created_by' => $user_id
    //                     );
    //                     $acc_id = $this->justAddAccParam($addData);
    //                 }
    //                 //check for household details
    //                 $household_id = $this->getHouseHoldID($datum->hhh_nrc);
    //                 if ($household_id === false || $household_id == 0) {
    //                     //log error and continue///--changes...no logging for this, just add the missing details
    //                     //get CWAC ID
    //                     $cwac_id = $this->getCWACID($datum->cwac);
    //                     //get ACC ID
    //                     $acc_id = $this->getACCID($datum->acc);
    //                     $addData = array(
    //                         'number_in_cwac' => $datum->hh_in_cwac,
    //                         'cwac_id' => $cwac_id,
    //                         'acc_id' => $acc_id,
    //                         'hhh_nrc_number' => $datum->hhh_nrc,
    //                         'hhh_fname' => $datum->hhh_fname,
    //                         'hhh_lname' => $datum->hhh_lname,
    //                         'created_at' => Carbon::now(),
    //                         'created_by' => $user_id
    //                     );
    //                     $household_id = $this->justAddHouseHoldParam($addData);
    //                 }
    //                 // if ($mappedChecker < 1 || $mappedChecker == 0) {
    //                 if ($school_id) {
    //                     $district_id_ben_id = str_pad($district_id, 4, 0, STR_PAD_LEFT);
    //                     $beneficiary_id = $year . $district_id_ben_id . $serial_number;
    //                     //start DMS UPDATE
    //                     $folder_id = '';
    //                     /*$parent_id = 2;
    //                     $main_module_id = 1;
    //                     $description = $datum->girl_fname . ' ' . $datum->girl_lname;
    //                     $folder_id = createDMSParentFolder($parent_id, $main_module_id, $beneficiary_id, $description, $dms_user);
    //                     createDMSModuleFolders($folder_id, $main_module_id, $dms_user);*/
    //                     //end DMS UPDATE
    //                     $mapped_master_ids[] = array(
    //                         'id' => $datum->id
    //                     );
    //                     $beneficiary_details[] = array(
    //                         'beneficiary_id' => $beneficiary_id,
    //                         'household_id' => $household_id,
    //                         'first_name' => aes_encrypt($datum->girl_fname),
    //                         'last_name' => aes_encrypt($datum->girl_lname),
    //                         //'dob' => $datum->girl_dob,
    //                         'dob' => converter11($datum->girl_dob),
    //                         'relation_to_hhh' => $datum->relation_to_hhh,
    //                         'school_going' => $datum->school_going,
    //                         'qualified_sec_sch' => $datum->qualified_sec_sch,
    //                         'willing_to_return_sch' => $datum->willing_to_return_sch,
    //                         'highest_grade' => $datum->highest_grade,
    //                         'current_school_grade' => $datum->current_school_grade,
    //                         'district_txt' => $datum->sct_district,
    //                         'cwac_txt' => $datum->cwac,
    //                         'school_id' => $school_id,
    //                         'exam_school_id' => $school_id,
    //                         'cwac_id' => $cwac_id,
    //                         'acc_id' => $acc_id,
    //                         'ward_id' => $ward_id,
    //                         'constituency_id' => $constituency_id,
    //                         'district_id' => $district_id,
    //                         'province_id' => $province_id,
    //                         'bursary_status' => $datum->bursary_status,
    //                         'type_of_bursary' => $datum->type_of_bursary,
    //                         'initial_category' => $datum->initial_category,
    //                         'category' => $category,
    //                         'beneficiary_status' => 1,
    //                         'master_id' => $datum->id,
    //                         'batch_id' => $batch_id,
    //                         'created_by' => $user_id,
    //                         'folder_id' => $folder_id,
    //                         'is_migrated' => 0
    //                     );
    //                     $serial_number_counter = $serial_number_counter + 1;
    //                     $serial_number = str_pad($serial_number_counter, 4, 0, STR_PAD_LEFT);
    //                     //delete all errors if any
    //                     DB::table('mapping_error_log')->where('beneficiary_master_id', $datum->id)->delete();
    //                 // } else {
    //                 //     //not mapped so leave it
    //                 // }
    //                 // break;
    //             }
    //             $mapped_master_IDs = convertAssArrayToSimpleArray($mapped_master_ids, 'id');
    //             DB::table('beneficiary_master_info')
    //                 ->whereIn('id', $mapped_master_IDs)
    //                 ->update(array('is_mapped' => 1));
    //             $size = 100;
    //             $chunks = array_chunk($beneficiary_details, $size);
    //             foreach ($chunks as $chunk) {
    //                 DB::table('beneficiary_information')->insert($chunk);
    //             }
    //             //get mapping summary
    //             $mapped = DB::table('beneficiary_master_info')
    //                 ->where(array('batch_id' => $batch_id, 'category' => $category, 'is_mapped' => 1, 'is_active' => 1))->count();
    //             $unmapped = DB::table('beneficiary_master_info')
    //                 ->where(array('batch_id' => $batch_id, 'category' => $category, 'is_mapped' => 0, 'is_active' => 1))->where('is_duplicate_with_existing', '<>', 1)->count();
    //             //todo map beneficiary additional info
    //             $res = array(
    //                 'success' => true,
    //                 'message' => 'Operation was successful. Check on the error log for any error encountered!!',
    //                 'mapped' => $mapped,
    //                 'unmapped' => $unmapped
    //             );
    //         }, 5);
    //     } catch (\Exception $exception) {
    //         $res = array(
    //             'success' => false,
    //             'message' => $exception->getMessage()
    //         );
    //     } catch (\Throwable $throwable) {
    //         $res = array(
    //             'success' => false,
    //             'message' => $throwable->getMessage()
    //         );
    //     }
    //     return response()->json($res);
    // }

    public function checkAnySuccessfulMapping(Request $req)
    {
        $any_mapping = 0;
        $batch_id = $req->input('batch_id');
        $mapped_count = DB::table('beneficiary_master_info')
            ->where('batch_id', $batch_id)
            ->where('is_mapped', 1)
            ->count();
        if ($mapped_count > 0) {
            $any_mapping = 1;
        }
        $res = array(
            'success' => true,
            'any_mapping' => $any_mapping,
            'message' => 'All is well'
        );
        return response()->json($res);
    }

    public function returnBatchToPrevStage(Request $req)
    {
        $batch_id = $req->batch_id;
        $current_status = $req->current_status;
        $prev_stage = $current_status - 1;
        try {
            if ($current_status == 2) {
                DB::table('batch_inclusion_criteria')->where('batch_id', $batch_id)->delete();
                DB::table('beneficiary_master_info')
                    ->where('batch_id', $batch_id)
                    ->update(array('is_duplicate' => 0, 'is_active' => 1));
            }
            if ($current_status == 3) {
                DB::table('beneficiary_information')->where('batch_id', $batch_id)->delete();
                DB::table('beneficiary_master_info')
                    ->where('batch_id', $batch_id)
                    ->update(array('is_mapped' => 0, 'is_duplicate_with_existing' => 0));
                DB::table('beneficiary_master_info')
                    ->where('batch_id', $batch_id)
                    ->where('is_dup_processed', 1)
                    ->update(array('is_active' => 1));
                DB::table('beneficiary_master_info')
                    ->where('batch_id', $batch_id)
                    ->update(array('is_dup_processed' => 0));
                // DB::table('beneficiary_master_info')->where('batch_id', $batch_id)->update(array('is_mapped' => 0, 'is_dup_processed' => 0));
            }
            DB::table('batch_info')->where('id', $batch_id)->update(array('status' => $prev_stage, 'dup_checked' => 0));
            $log_params = array(
                'batch_id' => $batch_id,
                'stage_id' => $prev_stage,
                'to_date' => date(''),
                'from_date' => Carbon::now(),
                'author' => \Auth::user()->id,
                'created_by' => \Auth::user()->id
            );
            DB::table('batches_transitional_report')
                ->where(array('batch_id' => $batch_id, 'stage_id' => $current_status))
                ->where(function ($query) {
                    $query->whereNull('to_date')
                        ->orWhere('to_date', '0000-00-00 00:00:00');
                })
                ->update(array('to_date' => Carbon::now()));
            DB::table('batches_transitional_report')->insert($log_params);
            $res = array(
                'success' => true,
                'message' => 'Changes made successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getMappingErrorLogs(Request $req)
    {
        $batch_id = $req->batch_id;
        $category = $req->category;
        $limit = $req->limit;
        $start = $req->start;
        $where = array(
            'mapping_error_log.batch_id' => $batch_id,
            'mapping_error_log.category' => $category
        );
        try {
            $total = DB::table('mapping_error_log')
                ->where($where)
                ->count();
            $qry = DB::table('mapping_error_log')
                ->select('mapping_error_log.id', 'mapping_error_log.beneficiary_master_id', 'mapping_error_log.error', 'mapping_error_log.error_type', 'beneficiary_master_info.girl_fname', 'beneficiary_master_info.girl_lname', 'beneficiary_master_info.sct_district', 'beneficiary_master_info.school_name', 'beneficiary_master_info.cwac', 'beneficiary_master_info.district_name', 'beneficiary_master_info.type_of_school')
                ->join('beneficiary_master_info', 'mapping_error_log.beneficiary_master_id', '=', 'beneficiary_master_info.id')
                ->where($where)
                ->offset($start)
                ->limit($limit);
            $data = $qry->get();
            $data->map(function ($data) {
                $data->cwac_code = $this->extractCodeName($data->cwac, 1);
                $data->cwac_name = $this->extractCodeName($data->cwac, 2);
                $data->school_code = $this->extractCodeName($data->school_name, 1);
                $data->school_name = $this->extractCodeName($data->school_name, 2);
                $data->sct_district_code = $this->extractCodeName($data->sct_district, 1);
                $data->sct_district_name = $this->extractCodeName($data->sct_district, 2);
                $data->sct_district_id = $this->getDistrictID($data->sct_district);
                $data->sch_district_code = $this->extractCodeName($data->district_name, 1);
                $data->sch_district_name = $this->extractCodeName($data->district_name, 2);
                $data->sch_district_id = $this->getDistrictID($data->district_name);
                $data->home_province_id = $this->getProvinceID($data->sct_district);
                $data->sch_province_id = $this->getProvinceID($data->district_name);
                $data->school_type_id = $this->getSchoolTypeID($data->type_of_school);
                $data->girl_name = $data->girl_fname . '&nbsp;' . $data->girl_lname;
                return $data;
            });
            $res = array(
                'totalCount' => $total,
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

    function addMissingErrorParam(Request $req)
    {
        $error_id = $req->error_id;
        $error_type = $req->error_type;
        $master_id = $req->master_id;
        if ($error_type == 1) {//household
            $res = $this->addHouseHoldErrorParam($master_id, $error_id);
        } else if ($error_type == 2) {//school
            $school_code = $req->school_code;
            $school_name = $req->school_name;
            $province_id = $req->province_id;
            $district_id = $req->sch_district_id;
            $school_type = $req->school_type_id;
            $res = $this->addSchoolErrorParam($master_id, $error_id, $school_code, $school_name, $school_type, $province_id, $district_id);
        } else if ($error_type == 3) {//cwac
            $cwac_code = $req->cwac_code;
            $cwac_name = $req->cwac_name;
            $province_id = $req->home_province_id;
            $district_id = $req->sct_district_id;
            $res = $this->addCwacErrorParam($master_id, $error_id, $cwac_code, $cwac_name, $province_id, $district_id);
        } else if ($error_type == 4) {//acc
            $res = $this->addAccErrorParam($master_id, $error_id);
        } else if ($error_type == 5) {//ward
            $res = $this->addWardErrorParam($master_id, $error_id);
        } else if ($error_type == 6) {//constituency
            $res = $this->addConstituencyErrorParam($master_id, $error_id);
        } else if ($error_type == 7) {//school district
            $district_code = $req->sch_district_code;
            $district_name = $req->sch_district_name;
            $province_id = $req->sch_province_id;
            $res = $this->addSchoolDistrictErrorParam($master_id, $error_id, $district_code, $district_name, $province_id);
        } else if ($error_type == 8) {//SCT district
            $district_code = $req->sct_district_code;
            $district_name = $req->sct_district_name;
            $province_id = $req->home_province_id;
            $res = $this->addDistrictErrorParam($master_id, $error_id, $district_code, $district_name, $province_id);
        }
        return response()->json($res);
    }

    public function addHouseHoldErrorParam($master_id, $error_id)
    {
        $user_id = \Auth::user()->id;
        try {
            $qry = DB::table('beneficiary_master_info')
                ->select('hh_in_cwac', 'cwac', 'acc', 'hhh_nrc', 'hhh_fname', 'hhh_lname')
                ->where('id', $master_id);
            $householdInfo = $qry->first();
            if (!is_null($householdInfo)) {
                //get CWAC ID
                $cwac_id = $this->getCWACID($householdInfo->cwac);
                //get ACC ID
                $acc_id = $this->getACCID($householdInfo->acc);
                if ((!is_numeric($cwac_id) || $cwac_id == false) || (!is_numeric($acc_id) || $acc_id == false)) {
                    $res = array(
                        'success' => false,
                        'message' => 'Please make sure the ACC and CWAC details are updated first before updating HouseHold details!!'
                    );
                    return $res;
                }
                $addData = array(
                    'number_in_cwac' => $householdInfo->hh_in_cwac,
                    'cwac_id' => $cwac_id,
                    'acc_id' => $acc_id,
                    'hhh_nrc_number' => $householdInfo->hhh_nrc,
                    'hhh_fname' => $householdInfo->hhh_fname,
                    'hhh_lname' => $householdInfo->hhh_lname,
                    'created_at' => Carbon::now(),
                    'created_by' => $user_id
                );
                $success = insertRecordNoAudit('households', $addData);
                if ($success == true) {
                    //delete error
                    DB::table('mapping_error_log')->where('id', $error_id)->delete();
                    $res = array(
                        'success' => true,
                        'message' => 'Household details added successfully. Please try mapping data once more!!'
                    );
                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'Error adding data::' . $success
                    );
                }
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'Problem encountered while getting household information from master table!!'
                );
            }
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return $res;
    }

    public function justAddHouseHoldParam($params)
    {
        $houseHoldID = 0;
        try {
            $houseHoldID = insertReturnID('households', $params);
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
        return $houseHoldID;
    }

    public function addSchoolErrorParam($master_id, $error_id, $school_code, $school_name, $school_type, $province_id, $district_id)
    {
        $user_id = \Auth::user()->id;
        try {
            $addData = array(
                'name' => $school_name,
                'code' => $school_code,
                'province_id' => $province_id,
                'district_id' => $district_id,
                'school_type_id' => $school_type,
                'created_at' => Carbon::now(),
                'created_by' => $user_id
            );
            $district_info = getSingleRecord('districts', array('id' => $district_id));
            $masterUpdate = array(
                'school_name' => $school_code . '-' . $school_name,
                'school_code' => $district_info->code . '-' . $school_code,
                'district_name' => $district_info->code . '-' . aes_decrypt($district_info->name)
            );
            $success = insertRecordNoAudit('school_information', $addData);
            if ($success == true) {
                //update master
                DB::table('beneficiary_master_info')->where('id', $master_id)->update($masterUpdate);
                //delete error
                DB::table('mapping_error_log')->where('id', $error_id)->delete();
                $res = array(
                    'success' => true,
                    'message' => 'School details added successfully. Please try mapping data once more!!'
                );
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'Error adding data::' . $success
                );
            }
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }

        return $res;
    }

    public
    function addCwacErrorParam($master_id, $error_id, $cwac_code, $cwac_name, $province_id, $district_id)
    {
        $user_id = \Auth::user()->id;
        try {
            $addData = array(
                'name' => $cwac_name,
                'code' => $cwac_code,
                'province_id' => $province_id,
                'district_id' => $district_id,
                'created_at' => Carbon::now(),
                'created_by' => $user_id
            );
            $district_info = getSingleRecord('districts', array('id' => $district_id));
            $masterUpdate = array(
                'cwac' => $cwac_code . '-' . $cwac_name,
                'sct_district' => $district_info->code . '-' . aes_decrypt($district_info->name)
            );
            $success = insertRecordNoAudit('cwac', $addData);
            if ($success == true) {
                //update master
                DB::table('beneficiary_master_info')->where('id', $master_id)->update($masterUpdate);
                //delete error
                DB::table('mapping_error_log')->where('id', $error_id)->delete();
                $res = array(
                    'success' => true,
                    'message' => 'CWAC details added successfully. Please try mapping data once more!!'
                );
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'Error adding data::' . $success
                );
            }
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return $res;
    }

    public
    function addAccErrorParam($master_id, $error_id)
    {
        $user_id = \Auth::user()->id;
        try {
            $qry = DB::table('beneficiary_master_info')
                ->select('acc', 'ward')
                ->where('id', $master_id);
            $accInfo = $qry->first();
            if (!is_null($accInfo)) {
                $ward_id = $this->getWardID($accInfo->ward);
                if (!is_numeric($ward_id) || $ward_id == false) {
                    $res = array(
                        'success' => false,
                        'message' => 'Please make sure the Ward details are updated first before updating ACC details!!'
                    );
                    return $res;
                }
                $addData = array(
                    'name' => $this->extractCodeName($accInfo->acc, 2),
                    'code' => $this->extractCodeName($accInfo->acc, 1),
                    'ward_id' => $ward_id,
                    'created_at' => Carbon::now(),
                    'created_by' => $user_id
                );
                $success = insertRecordNoAudit('acc', $addData);
                if ($success == true) {
                    //delete error
                    DB::table('mapping_error_log')->where('id', $error_id)->delete();
                    $res = array(
                        'success' => true,
                        'message' => 'ACC details added successfully. Please try mapping data once more!!'
                    );
                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'Error adding data::' . $success
                    );
                }
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'Problem encountered while getting ACC information from master table!!'
                );
            }
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return $res;
    }

    public
    function justAddAccParam($params)
    {
        $accID = 0;
        try {
            $accID = insertReturnID('acc', $params);
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
        return $accID;
    }

    public
    function addWardErrorParam($master_id, $error_id)
    {
        $user_id = \Auth::user()->id;
        try {
            $qry = DB::table('beneficiary_master_info')
                ->select('ward', 'constituency')
                ->where('id', $master_id);
            $wardInfo = $qry->first();
            if (!is_null($wardInfo)) {
                $constituency_id = $this->getConstituencyID($wardInfo->constituency);
                if (!is_numeric($constituency_id) || $constituency_id == false) {
                    $res = array(
                        'success' => false,
                        'message' => 'Please make sure the Constituency details are updated first before updating Ward details!!'
                    );
                    return $res;
                }
                $addData = array(
                    'name' => $this->extractCodeName($wardInfo->ward, 2),
                    'code' => $this->extractCodeName($wardInfo->ward, 1),
                    'constituency_id' => $constituency_id,
                    'created_at' => Carbon::now(),
                    'created_by' => $user_id
                );
                $success = insertRecordNoAudit('wards', $addData);
                if ($success == true) {
                    //delete error
                    DB::table('mapping_error_log')->where('id', $error_id)->delete();
                    $res = array(
                        'success' => true,
                        'message' => 'Ward details added successfully. Please try mapping data once more!!'
                    );
                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'Error adding data::' . $success
                    );
                }
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'Problem encountered while getting Ward information from master table!!'
                );
            }
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return $res;
    }

    public
    function justAddWardParam($params)
    {
        $wardID = 0;
        try {
            $wardID = insertReturnID('wards', $params);
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
        return $wardID;
    }

    public
    function addConstituencyErrorParam($master_id, $error_id)
    {
        $user_id = \Auth::user()->id;
        try {
            $qry = DB::table('beneficiary_master_info')
                ->select('constituency', 'sct_district')
                ->where('id', $master_id);
            $constituencyInfo = $qry->first();
            if (!is_null($constituencyInfo)) {
                $district_id = $this->getDistrictID($constituencyInfo->sct_district);
                if (!is_numeric($district_id) || $district_id == false) {
                    $res = array(
                        'success' => false,
                        'message' => 'Please make sure the District details are updated first before updating Constituency details!!'
                    );
                    return $res;
                }
                $addData = array(
                    'name' => $this->extractCodeName($constituencyInfo->constituency, 2),
                    'code' => $this->extractCodeName($constituencyInfo->constituency, 1),
                    'district_id' => $district_id,
                    'created_at' => Carbon::now(),
                    'created_by' => $user_id
                );
                $success = insertRecordNoAudit('constituencies', $addData);
                if ($success == true) {
                    //delete error
                    DB::table('mapping_error_log')->where('id', $error_id)->delete();
                    $res = array(
                        'success' => true,
                        'message' => 'Constituency details added successfully. Please try mapping data once more!!'
                    );
                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'Error adding data::' . $success
                    );
                }
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'Problem encountered while getting Constituency information from master table!!'
                );
            }
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return $res;
    }

    public
    function justAddConstituencyParam($params)
    {
        $constituencyID = 0;
        try {
            $constituencyID = insertReturnID('constituencies', $params);
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
        return $constituencyID;
    }

    public
    function addSchoolDistrictErrorParam($master_id, $error_id, $district_code, $district_name, $province_id)
    {
        $user_id = \Auth::user()->id;
        try {
            $addData = array(
                'name' => $district_name,
                'code' => $district_code,
                'province_id' => $province_id,
                'created_at' => Carbon::now(),
                'created_by' => $user_id
            );
            $masterUpdate = array(
                'district_name' => $district_code . '-' . $district_name
            );
            $success = insertRecordNoAudit('districts', $addData);
            if ($success == true) {
                //update master
                DB::table('beneficiary_master_info')->where('id', $master_id)->update($masterUpdate);
                //delete error
                DB::table('mapping_error_log')->where('id', $error_id)->delete();
                $res = array(
                    'success' => true,
                    'message' => 'District details added successfully. Please try mapping data once more!!'
                );
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'Error adding data::' . $success
                );
            }
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return $res;
    }

    public
    function addDistrictErrorParam($master_id, $error_id, $district_code, $district_name, $province_id)
    {
        $user_id = \Auth::user()->id;
        try {
            $addData = array(
                'name' => $district_name,
                'code' => $district_code,
                'province_id' => $province_id,
                'created_at' => Carbon::now(),
                'created_by' => $user_id
            );
            $masterUpdate = array(
                'sct_district' => $district_code . '-' . $district_name
            );
            $success = insertRecordNoAudit('districts', $addData);
            if ($success == true) {
                //update master
                DB::table('beneficiary_master_info')->where('id', $master_id)->update($masterUpdate);
                //delete error
                DB::table('mapping_error_log')->where('id', $error_id)->delete();
                $res = array(
                    'success' => true,
                    'message' => 'District details added successfully. Please try mapping data once more!!'
                );
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'Error adding data::' . $success
                );
            }
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return $res;
    }

    public
    function getHouseHoldID($nrc_number)
    {
        if (is_null($nrc_number) || $nrc_number == 0) {
            return false;
        }
        $qry = DB::table('households')
            // ->where('number_in_cwac', $serial_num_cwac)
            ->Where('hhh_nrc_number', $nrc_number);
        $data = $qry->first();
        if (is_null($data)) {
            return false;
        } else {
            return $data->id;
        }
    }

    public
    function getSchoolID($school_code)
    {
        $codeArray = explode('-', $school_code);
        $main_sch_code = end($codeArray);
        if (is_null($school_code) || $main_sch_code == 0) {
            return false;
        }
        $qry = DB::table('school_information')
            ->where('code', $main_sch_code);
        $data = $qry->first();
        if (is_null($data)) {
            return false;
        } else {
            return $data->id;
        }
    }

    public function getCwacID($cwac_code)
    {//frank
        $codeArray = explode('-', $cwac_code);
        $cwac_name = $codeArray[1];
        $main_cwac_code = current($codeArray);
        // if (is_null($cwac_code) || $main_cwac_code == 0) {
        //     return false;
        // }
        $qry1 = DB::table('cwac')
            ->where('code', $main_cwac_code);
        $data1 = $qry1->first();  
        $qry = DB::table('cwac')
            ->where('name', 'like', $cwac_name);
        $data = $qry->first(); 
        if (is_null($data1)) {                
            if (is_null($data)) {
                return false;
            } else {
                return $data->id;
            }
        } else {
            return $data1->id;
        }
    }

    public
    function getAccID($acc_code)
    {
        $codeArray = explode('-', $acc_code);
        $main_acc_code = current($codeArray);
        if (is_null($acc_code) || $main_acc_code == 0) {
            return false;
        }
        $qry = DB::table('acc')
            ->where('code', $main_acc_code);
        $data = $qry->first();
        if (is_null($data)) {
            return false;
        } else {
            return $data->id;
        }
    }

    public
    function getWardID($ward_code)
    {
        $codeArray = explode('-', $ward_code);
        $main_ward_code = current($codeArray);
        if (is_null($ward_code) || $main_ward_code == 0) {
            return false;
        }
        $qry = DB::table('wards')
            ->where('code', $main_ward_code);
        $data = $qry->first();
        if (is_null($data)) {
            return false;
        } else {
            return $data->id;
        }
    }

    public
    function getConstituencyID($constituency_code)
    {
        $codeArray = explode('-', $constituency_code);
        $main_constituency_code = current($codeArray);
        if (is_null($constituency_code) || $main_constituency_code == 0) {
            return false;
        }
        $qry = DB::table('constituencies')
            ->where('code', $main_constituency_code);
        $data = $qry->first();
        if (is_null($data)) {
            return false;
        } else {
            return $data->id;
        }
    }

    public
    function getDistrictID($district_code)//SCT DISTRICT
    {
        $codeArray = explode('-', $district_code);
        $main_dist_code = current($codeArray);
        if (is_null($district_code) || $main_dist_code == 0) {
            return false;
        }
        $qry = DB::table('districts')
            ->where('code', $main_dist_code);
        $data = $qry->first();
        if (is_null($data)) {
            return false;
        } else {
            return $data->id;
        }
    }

    public
    function getProvinceID($district_code)//PROVINCE
    {
        $codeArray = explode('-', $district_code);
        $main_dist_code = current($codeArray);
        if (is_null($district_code) || $main_dist_code == 0) {
            return false;
        }
        $qry = DB::table('districts')
            ->where('code', $main_dist_code);
        $data = $qry->first();
        if (is_null($data)) {
            return false;
        } else {
            return $data->province_id;
        }
    }

    public
    function getSchoolDistrictID($district_name)
    {
        $codeArray = explode('-', $district_name);
        $main_dist_code = current($codeArray);
        if (is_null($district_name) || $main_dist_code == 0) {
            return false;
        }
        $qry = DB::table('districts')
            ->where('code', $main_dist_code);
        $data = $qry->first();
        if (is_null($data)) {
            return false;
        } else {
            return $data->id;
        }
    }

    public
    function getSchoolTypeID($school_type_string)
    {
        $allSchTypes = DB::table('school_types')->get();
        $allSchTypes = convertStdClassObjToArray($allSchTypes);
        $decryptedSchTypes = decryptArray($allSchTypes);
        foreach ($decryptedSchTypes as $decryptedSchType) {
            if (stripos($decryptedSchType['name'], $school_type_string) === false) {
                //  return 0;
            } else {
                return $decryptedSchType['id'];
            }
        }
        return 0;
    }

    public
    function extractCodeName($string, $direction)
    {
        $codeArray = explode('-', $string);
        //for easy comparisons, use 1 for start and 2 for last
        if ($direction == 1) {
            $extracted = current($codeArray);
        } else {
            $extracted = end($codeArray);
        }
        return $extracted;
    }

    public
    function submitBatchForVerification(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $is_super_submit = $req->input('is_super_submit');
        if ($is_super_submit == 1) {
            return $this->superSubmitBatchForVerification($batch_id);
        } else {
            return $this->normalSubmitBatchForVerification($batch_id);
        }
    }

    public
    function normalSubmitBatchForVerification($batch_id)
    {
        //check if this batch has been mapped successfully
        //first we need to check for all categories i.e you should map all categories be4 submission
        //but wait what if there are no beneficiaries in certain categories
        try {
            //get total counts for all categories
            $qry1 = DB::table('beneficiary_master_info')
                ->where('batch_id', $batch_id)
                ->select(DB::raw("SUM(IF(category=1,1,0)) as outOfSchoolCount,
                 SUM(IF(category=2,1,0)) as inSchoolCount,
                 SUM(IF(category=3,1,0)) as examClassesCount"));
            $master_data = $qry1->first();
            if (is_null($master_data)) {
                $res = array(
                    'success' => false,
                    'message' => 'Problem encountered getting data from master table!!'
                );
                return response()->json($res);
            }
            $qry = DB::table('batch_mapping_log')
                ->where('batch_id', $batch_id)
                ->select(DB::raw("SUM(IF(category_id=1,map_limit,0)) as outOfSchoolMapped,
                 SUM(IF(category_id=2,map_limit,0)) as inSchoolMapped,
                 SUM(IF(category_id=3,map_limit,0)) as examClassesMapped"));
            $error_data = $qry->first();
            if (is_null($error_data)) {
                $res = array(
                    'success' => false,
                    'message' => 'Problem encountered getting data from mapping error log table!!'
                );
                return response()->json($res);
            }
            $outofSchCount = $master_data->outOfSchoolCount;
            $outofSchMapped = $error_data->outOfSchoolMapped;

            $inSchCount = $master_data->inSchoolCount;
            $inSchMapped = $error_data->inSchoolMapped;

            $examClassesCount = $master_data->examClassesCount;
            $examClassesMapped = $error_data->examClassesMapped;

            /* $outofSchMapped = DB::table('batch_mapping_log')
                 ->where('batch_id', $batch_id)
                 ->where('category_id', 1)
                 ->count();
             $inSchMapped = DB::table('batch_mapping_log')
                 ->where('batch_id', $batch_id)
                 ->where('category_id', 2)
                 ->count();
             $examClassesMapped = DB::table('batch_mapping_log')
                 ->where('batch_id', $batch_id)
                 ->where('category_id', 3)
                 ->count();
             if ($outofSchMapped == 0) {
                 $res = array(
                     'success' => false,
                     'message' => 'Submission error. No mapping details found for Out of School category. Please make sure you map all categories before submission!!'
                 );
                 return response()->json($res);
             }*/
            // if ($outofSchMapped < $outofSchCount) {
            //     $res = array(
            //         'success' => false,
            //         'message' => 'You have not mapped all \'Out of School\' girls. Please map the remaining ' . ($outofSchCount - $outofSchMapped) . ' girls!!'
            //     );
            //     return response()->json($res);
            // }
            if ($inSchMapped < $inSchCount) {
                $res = array(
                    'success' => false,
                    'message' => 'You have not mapped all \'In school\' girls. Please map the remaining ' . ($inSchCount - $inSchMapped) . ' girls!!'
                );
                return response()->json($res);
            }
            if ($examClassesMapped < $examClassesCount) {
                $res = array(
                    'success' => false,
                    'message' => 'You have not mapped all \'Exam Classes\' girls. Please map the remaining ' . ($examClassesCount - $examClassesMapped) . ' girls!!'
                );
                return response()->json($res);
            }
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
            return response()->json($res);
        }
        //======================================================================================================================//
        try {
            //first has there been any single successful mapping?
            $mappedCounter = DB::table('beneficiary_master_info')->where('batch_id', $batch_id)->where('is_mapped', 1)->count();
            if ($mappedCounter == 0) {
                $res = array(
                    'success' => false,
                    'message' => 'Submission error. Seemingly you haven\'t mapped any data or your mapping hasn\'t been successful. Please try mapping the data or check on \'Error Log\' buttons for any mapping errors!!'
                );
            } else {
                //check for mapping errors
                $error_count = DB::table('mapping_error_log')->where('batch_id', $batch_id)->count();
                if ($error_count == 0) {
                    DB::table('batch_info')->where('id', $batch_id)->update(array('status' => 4));
                    //log in transition rpt table
                    $log_params = array(
                        'batch_id' => $batch_id,
                        'stage_id' => 4,
                        'to_date' => date(''),
                        'from_date' => Carbon::now(),
                        'author' => \Auth::user()->id,
                        'created_by' => \Auth::user()->id
                    );
                    DB::table('batches_transitional_report')
                        ->where(array('batch_id' => $batch_id, 'stage_id' => 3))
                        ->where(function ($query) {
                            $query->whereNull('to_date')
                                ->orWhere('to_date', '0000-00-00 00:00:00');
                        })
                        ->update(array('to_date' => Carbon::now()));
                    DB::table('batches_transitional_report')->insert($log_params);
                    $res = array(
                        'success' => true,
                        'message' => 'The batch was successfully submitted to the next stage (Verification)'
                    );
                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'Submission error. Some errors associated with this batch were detected, please correct all errors by clicking on \'Error Log\' button on each category!!'
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

    public
    function superSubmitBatchForVerification($batch_id)
    {
        //check if this batch has been mapped successfully
        //first we need to check for all categories i.e you should map all categories be4 submission
        //but wait what if there are no beneficiaries in certain categories
        try {
            //get total counts for all categories
            $qry1 = DB::table('beneficiary_master_info')
                ->where('batch_id', $batch_id)
                ->select(DB::raw("SUM(IF(category=1,1,0)) as outOfSchoolCount,
                 SUM(IF(category=2,1,0)) as inSchoolCount,
                 SUM(IF(category=3,1,0)) as examClassesCount"));
            $master_data = $qry1->first();
            if (is_null($master_data)) {
                $res = array(
                    'success' => false,
                    'message' => 'Problem encountered getting data from master table!!'
                );
                return response()->json($res);
            }
            $qry = DB::table('batch_mapping_log')
                ->where('batch_id', $batch_id)
                ->select(DB::raw("SUM(IF(category_id=1,map_limit,0)) as outOfSchoolMapped,
                 SUM(IF(category_id=2,map_limit,0)) as inSchoolMapped,
                 SUM(IF(category_id=3,map_limit,0)) as examClassesMapped"));
            $error_data = $qry->first();
            if (is_null($error_data)) {
                $res = array(
                    'success' => false,
                    'message' => 'Problem encountered getting data from mapping error log table!!'
                );
                return response()->json($res);
            }
            $outofSchCount = $master_data->outOfSchoolCount;
            $outofSchMapped = $error_data->outOfSchoolMapped;

            $inSchCount = $master_data->inSchoolCount;
            $inSchMapped = $error_data->inSchoolMapped;

            $examClassesCount = $master_data->examClassesCount;
            $examClassesMapped = $error_data->examClassesMapped;

            if ($outofSchMapped < $outofSchCount) {
                $res = array(
                    'success' => false,
                    'message' => 'You have not mapped all \'Out of School\' girls. Please map the remaining ' . ($outofSchCount - $outofSchMapped) . ' girls!!'
                );
                return response()->json($res);
            }
            if ($inSchMapped < $inSchCount) {
                $res = array(
                    'success' => false,
                    'message' => 'You have not mapped all \'In school\' girls. Please map the remaining ' . ($inSchCount - $inSchMapped) . ' girls!!'
                );
                return response()->json($res);
            }
            if ($examClassesMapped < $examClassesCount) {
                $res = array(
                    'success' => false,
                    'message' => 'You have not mapped all \'Exam Classes\' girls. Please map the remaining ' . ($examClassesCount - $examClassesMapped) . ' girls!!'
                );
                return response()->json($res);
            }
            /////////////////////
            //first has there been any single successful mapping?
            $mappedCounter = DB::table('beneficiary_master_info')->where('batch_id', $batch_id)->where('is_mapped', 1)->count();
            if ($mappedCounter == 0) {
                $res = array(
                    'success' => false,
                    'message' => 'Submission error. Seemingly you haven\'t mapped any data or your mapping hasn\'t been successful. Please try mapping the data or check on \'Error Log\' buttons for any mapping errors!!'
                );
            } else {
                DB::table('batch_info')->where('id', $batch_id)->update(array('status' => 4));
                //log in transition rpt table
                $log_params = array(
                    'batch_id' => $batch_id,
                    'stage_id' => 4,
                    'to_date' => date(''),
                    'from_date' => Carbon::now(),
                    'author' => \Auth::user()->id,
                    'created_by' => \Auth::user()->id
                );
                DB::table('batches_transitional_report')
                    ->where(array('batch_id' => $batch_id, 'stage_id' => 3))
                    ->where(function ($query) {
                        $query->whereNull('to_date')
                            ->orWhere('to_date', '0000-00-00 00:00:00');
                    })
                    ->update(array('to_date' => Carbon::now()));
                DB::table('batches_transitional_report')->insert($log_params);
                $res = array(
                    'success' => true,
                    'message' => 'The batch was successfully submitted to the next stage (Verification)'
                );
            }
            ////////////////////
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public
    function getMultipleAnswerOptions(Request $req)
    {
        try {
            $question_id = $req->input('question_id');
            $answer_type_id = $req->input('answer_type_id');

            $where = array(
                'checklist_item_id' => $question_id,
                'answer_type_id' => $answer_type_id
            );
            $data = DB::table('answer_options as t1')
                ->leftJoin('checklist_options as t2', 't1.option_id', '=', 't2.id')
                ->select('t1.*', 't2.option_name')
                ->where($where)->get();
            $res = array(
                'success' => true,
                'message' => returnMessage($data),
                'results' => $data
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

    public
    function getVerificationSchInfo(Request $req)
    {
        $results = array();
        $school_id = $req->input('school_id');
        /*$qry = DB::table('school_information')
            ->leftJoin('districts', 'school_information.district_id', '=', 'districts.id')
            ->leftJoin('school_contactpersons', 'school_information.id', '=', 'school_contactpersons.school_id')
            ->join('school_designation', function ($join) {
                $join->on('school_contactpersons.designation_id', '=', 'school_designation.id')
                    ->where('school_designation.id', 1);
            })
            ->where(array('school_information.id' => $school_id))
            ->select('school_information.*', 'districts.code as district_code', 'school_contactpersons.full_names', 'school_contactpersons.telephone_no');*/
        $data = DB::select("select school_information.*, districts.code as district_code, school_contactpersons.full_names, school_contactpersons.telephone_no from school_information
                          left join districts on school_information.district_id = districts.id
                          left join (school_contactpersons inner join school_designation on school_contactpersons.designation_id = school_designation.id
                          and school_designation.id = 1) on school_information.id = school_contactpersons.school_id
                          where (school_information.id = $school_id) limit 1");
        foreach ($data as $datum) {
            $results = array(
                'id' => $school_id,
                'school_name' => $datum->code . '-' . aes_decrypt($datum->name),
                'emis_code' => $datum->district_code . '-' . $datum->code,
                'province_id' => $datum->province_id,
                'district_id' => $datum->district_id,
                'head_teacher' => $datum->full_names,
                'head_teacher_contact' => $datum->telephone_no
            );
        }
        $res = array(
            'success' => true,
            'message' => 'School record fetched successfully!!',
            'results' => $results
        );
        return response()->json($res);
    }

    public function getVerificationCwacInfo(Request $req)
    {
        $cwac_id = $req->input('cwac_id');
        try {
            $qry = DB::table('cwac')
                ->where('id', $cwac_id);
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $data = decryptArray($data);
            foreach ($data as $datum) {
                $results = array(
                    'id' => $cwac_id,
                    'name' => $datum['name'],
                    'code' => $datum['code'],
                    'province_id' => $datum['province_id'],
                    'district_id' => $datum['district_id'],
                    'contact_person_id' => $datum['contact_person_id'],
                    'contact_person_name' => $datum['contact_person_name'],
                    'contact_person_phone' => $datum['contact_person_phone']
                );
            }
            $res = array(
                'success' => true,
                'message' => 'Results fetched successfully',
                'results' => $results
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getVerificationDistrictInfo(Request $req)
    {
        $district_id = $req->input('district_id');
        try {
            $qry = DB::table('districts')->where('id', $district_id);
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $data = decryptArray($data);
            foreach ($data as $datum) {
                $results = array(
                    'id' => $district_id,
                    'name' => $datum['name'],
                    'code' => $datum['code'],
                    'province_id' => $datum['province_id'],
                    'description' => $datum['description'],
                );
            }
            $res = array(
                'success' => true,
                'message' => 'Results fetched successfully',
                'results' => $results
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
    function updateSchoolInfo(Request $req)
    {
        $school_id = $req->input('id');
        $school_code_name = $req->input('school_name');
        $province_id = $req->input('province_id');
        $district_id = $req->input('district_id');
        $head_teacher = $req->input('head_teacher');
        $head_teacher_contact = $req->input('head_teacher_contact');
        $school_code_name_array = explode('-', $school_code_name);
        $school_name = end($school_code_name_array);
        $where1 = array(
            'id' => $school_id
        );
        $school_info = array(
            'name' => $school_name,
            'province_id' => $province_id,
            'district_id' => $district_id
        );
        $where2 = array(
            'school_id' => $school_id,
            'designation_id' => 1
        );
        $contact_info = array(
            'full_names' => $head_teacher,
            'telephone_no' => $head_teacher_contact,
            'mobile_no' => $head_teacher_contact
        );
        $res = array();
        DB::transaction(function () use (&$res, $where1, $where2, $school_info, $contact_info, $school_id) {
            try {
                DB::table('school_information')->where($where1)->update($school_info);
                $exists = DB::table('school_contactpersons')->where($where2)->first();
                if (is_null($exists)) {
                    $contact_info['created_by'] = \Auth::user()->id;
                    $contact_info['created_at'] = Carbon::now();
                    $contact_info['school_id'] = $school_id;
                    $contact_info['designation_id'] = 1;
                    DB::table('school_contactpersons')->insert($contact_info);
                } else {
                    $contact_info['updated_by'] = \Auth::user()->id;
                    $contact_info['updated_at'] = Carbon::now();
                    DB::table('school_contactpersons')->where($where2)->update($contact_info);
                }
                $res = array(
                    'success' => true,
                    'message' => 'School details updated successfully!!'
                );
            } catch (\Exception $e) {
                $res = array(
                    'success' => false,
                    'message' => $e->getMessage()
                );
            }
        }, 4);
        return response()->json($res);
    }

    public
    function updateCwacInfo(Request $req)
    {
        $cwac_id = $req->input('id');
        $cwac_name = $req->input('name');
        $cwac_code = $req->input('code');
        $province_id = $req->input('province_id');
        $district_id = $req->input('district_id');
        $contact_person = $req->input('contact_person_name');
        $contact_person_phone = $req->input('contact_person_phone');
        $where = array(
            'id' => $cwac_id
        );
        $cwac_info = array(
            'name' => $cwac_name,
            'code' => $cwac_code,
            'contact_person_name' => $contact_person,
            'contact_person_phone' => $contact_person_phone,
            'province_id' => $province_id,
            'district_id' => $district_id
        );
        try {
            DB::table('cwac')
                ->where($where)
                ->update($cwac_info);
            $res = array(
                'success' => true,
                'message' => 'CWAC information saved successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }


    public function getGirlsForVerification23(Request $req)
    // public function modifyHHHdetails(Request $req)
    {
        try {
            $girl_master_details = DB::table('beneficiary_information as t1')
                ->join('households as t2', 't1.household_id', '=', 't2.id')
                ->join('beneficiary_master_info as t3', 't1.master_id', '=', 't3.id')
                ->select(DB::raw('t1.id,decrypt(t1.first_name) AS first_name,
                    decrypt(t1.last_name) AS last_name,t3.hhh_fname AS orig_hhh_fname,
                    t3.hhh_lname AS orig_hhh_lname,t3.acc,t3.cwac,t3.hhh_nrc,
                    t3.hh_in_cwac,t2.hhh_fname AS old_hhh_fname,
                    t2.hhh_lname AS old_hhh_lname'))
                ->where('t1.beneficiary_status', 4)
                ->where('t1.verification_recommendation', 1)
                ->where('t2.hhh_fname', 'LIKE', 'Mary')
                ->where('t2.hhh_lname', 'LIKE', 'Mwanza')
                ->where(function ($query) {
                    $query->where('t2.hhh_nrc_number', 'LIKE', '%9999/99/9%')
                          ->orWhere('t2.hhh_nrc_number', 'LIKE', '%9999/99/1%');
                })
                ->toSql(); //->get();
                dd($girl_master_details);
            $key = 0;
            $data = [];
            foreach ($girl_master_details as $girl_details) {
                // $data[$key]['girl_updated_hh'] = $girl_details->id;
                $cwac_id = $this->getCwacID($girl_details->cwac);
                $acc_id = $this->getAccID($girl_details->acc);
                $addData = array(
                    'number_in_cwac' => $girl_details->hh_in_cwac,
                    'cwac_id' => $cwac_id,
                    'acc_id' => $acc_id,
                    'hhh_nrc_number' => $girl_details->hhh_nrc,
                    'hhh_fname' => $girl_details->orig_hhh_fname,
                    'hhh_lname' => $girl_details->orig_hhh_lname,
                    'created_at' => Carbon::now(),
                    'created_by' => 4
                );
                $houseHoldlogs = array(
                    'ben_info_id' => $girl_details->id,
                    'initial_hhh_firstname' => $girl_details->old_hhh_lname,
                    'initial_hhh_lastname' => $girl_details->old_hhh_fname,
                    'updated_hhh_firstname' => $girl_details->orig_hhh_lname,
                    'updated_hhh_lastname' => $girl_details->orig_hhh_lname,
                    'created_at' => Carbon::now(),
                    'created_by' => 4
                );
                $houseHoldCheckParams = array(
                    'hhh_fname' => $girl_details->orig_hhh_lname,
                    'hhh_lname' => $girl_details->orig_hhh_fname
                );                
                $houseHoldCheck = DB::table('households')
                    ->where($houseHoldCheckParams)->first();
                if ($houseHoldCheck) {
                    $houseHoldUpdate = array(
                        'household_id' => $houseHoldCheck->id
                    );
                } else {
                    $houseHoldID = insertReturnID('households', $addData); 
                    $houseHoldUpdate = array(
                        'household_id' => $houseHoldID
                    );
                }
                DB::table('beneficiary_information')->where('id', $girl_details->id)->update($houseHoldUpdate);
                DB::table('household_update_log')->insert($houseHoldlogs);
                $key++;
            }
            $res = array(
                'success' => true,
                'results' => $key,
                'message' => 'All is well!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getGirlsForVerification(Request $req)
    {
        try {
            $category_id = $req->input('category');
            $batch_id = $req->input('batch_id');
            $province_id = $req->input('province_id');
            $district_id = $req->input('district_id');
            $cwac_id = $req->input('cwac_id');
            $cwac_txt = $req->input('cwac_txt');
            $school_id = $req->input('school_id');
            $option_id = $req->input('option_id');

            $qry = DB::table('beneficiary_information')
                ->join('beneficiary_categories', 'beneficiary_information.category', '=', 'beneficiary_categories.id')
                ->leftJoin('beneficiary_master_info', 'beneficiary_information.master_id', '=', 'beneficiary_master_info.id')
                ->leftJoin('households', 'beneficiary_information.household_id', '=', 'households.id')
                ->leftJoin('cwac', 'beneficiary_information.cwac_id', '=', 'cwac.id')
                ->leftJoin('school_information', 'beneficiary_information.school_id', '=', 'school_information.id')
                ->leftJoin('districts as d1', 'school_information.district_id', '=', 'd1.id')
                ->leftJoin('districts', 'beneficiary_information.district_id', '=', 'districts.id')
                ->select(DB::raw("beneficiary_information.*, d1.name as school_district, school_information.name as school_name, cwac.name as cwac_name, beneficiary_categories.name as category_name, beneficiary_master_info.highest_grade as master_highest_grade, beneficiary_master_info.current_school_grade as master_current_school_grade, households.id as hh_id, households.hhh_fname, households.hhh_lname, households.hhh_nrc_number, households.number_in_cwac, districts.name as district_name,
                     decrypt(beneficiary_information.first_name) as first_name,decrypt(beneficiary_information.last_name) as last_name"))
                ->where('beneficiary_information.batch_id', $batch_id)
                ->whereIn('beneficiary_information.beneficiary_status', array(1, 2, 0));
            if (isset($option_id) && $option_id != '') {
                if ($option_id == 2) {
                    $qry->where('beneficiary_information.beneficiary_status', 2);
                } else if ($option_id == 3) {
                    $qry->whereIn('beneficiary_information.beneficiary_status', array(1, 0));
                }
            }
            if ($category_id == 1) {
                if (isset($school_id) && $school_id != '') {
                    $qry->where('beneficiary_information.school_id', $school_id);
                } else if (isset($cwac_id) && $cwac_id != '') {
                    $qry->where('beneficiary_information.cwac_id', $cwac_id);
                } else if (isset($district_id) && $district_id != '') {
                    $qry->where('beneficiary_information.district_id', $district_id);
                } else if (isset($province_id) && $province_id != '') {
                    $qry->where('beneficiary_information.province_id', $province_id);
                } else if (isset($cwac_txt) && $cwac_txt != '') {
                    $qry->whereRaw("beneficiary_information.cwac_txt LIKE '%$cwac_txt%'");
                }
            } else {
                if (isset($school_id) && $school_id != '') {
                    $qry->where('beneficiary_information.school_id', $school_id);
                } else if (isset($cwac_id) && $cwac_id != '') {
                    $qry->where('beneficiary_information.cwac_id', $cwac_id);
                } else if (isset($district_id) && $district_id != '') {
                    $qry->where('school_information.district_id', $district_id);
                } else if (isset($province_id) && $province_id != '') {
                    $qry->where('school_information.province_id', $province_id);
                }
            }
            if (isset($category_id) && $category_id != '') {
                if ($category_id == 2) {
                    $qry->whereIn('beneficiary_information.category', array(2, 3));
                } else {
                    $qry->where('beneficiary_information.category', $category_id);
                }
            }
            $data = $qry->get();
            $res = array(
                'success' => true,
                'message' => returnMessage($data),
                'results' => $data
            );
        } catch (\Exception $exception) {
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

    public
    function getGirlsForAnalysis(Request $req)
    {
        try {
            $cwac_id = $req->input('cwac_id');
            $school_id = $req->input('school_id');
            $category_id = $req->input('category');
            $batch_id = $req->input('batch_id');
            $province_id = $req->input('province_id');
            $district_id = $req->input('district_id');
            $filter_category = $req->input('filter_category');
            $filter_grade = $req->input('filter_grade');
            $recommendation = $req->input('recommendation');
            $checklist_item_id = $req->input('checklist_item_id');
            $answer = $req->input('answer');
            $filter = $req->input('filter');
            $verification_type = $req->input('verification_type');
            $whereClauses = array();
            $filter_string = '';
            if (isset($filter)) {
                $filters = json_decode($filter);
                if ($filters != NULL) {
                    foreach ($filters as $filter) {
                        switch ($filter->property) {
                            case 'beneficiary_id' :
                                $whereClauses[] = "t1.beneficiary_id like '%" . ($filter->value) . "%'";
                                break;
                            case 'first_name' :
                                $whereClauses[] = "decrypt(t1.first_name) like '%" . ($filter->value) . "%'";
                                break;
                            case 'last_name' :
                                $whereClauses[] = "decrypt(t1.last_name) like '%" . ($filter->value) . "%'";
                                break;
                            case 'cwac_txt' :
                                $whereClauses[] = "t1.cwac_txt like '%" . ($filter->value) . "%'";
                                break;
                            case 'district_name' :
                                $whereClauses[] = "t1.district_id = '" . ($filter->value) . "'";
                                break;
                            case 'school_district' :
                                $whereClauses[] = "school_information.district_id = '" . ($filter->value) . "'";
                                break;
                            case 'school_name' :
                                $whereClauses[] = "t1.school_id = '" . ($filter->value) . "'";
                                break;
                        }
                    }
                    $whereClauses = array_filter($whereClauses);
                }
                if (!empty($whereClauses)) {
                    $filter_string = implode(' AND ', $whereClauses);
                }
            }

            $qry = DB::table('beneficiary_information as t1')
                ->join('beneficiary_categories', 't1.category', '=', 'beneficiary_categories.id')
                ->leftJoin('beneficiary_master_info', 't1.master_id', '=', 'beneficiary_master_info.id')
                ->leftJoin('households', 't1.household_id', '=', 'households.id')
                ->leftJoin('cwac', 't1.cwac_id', '=', 'cwac.id')
                ->leftJoin('school_information', 't1.school_id', '=', 'school_information.id')
                ->leftJoin('districts as d1', 'school_information.district_id', '=', 'd1.id')
                ->leftJoin('districts', 't1.district_id', '=', 'districts.id')
                ->select(DB::raw("t1.id,t1.beneficiary_id,t1.household_id,t1.exam_school_id,t1.school_id,t1.cwac_id,t1.acc_id,t1.ward_id,t1.constituency_id,t1.district_id,t1.province_id,CASE WHEN decrypt(t1.first_name) IS NULL THEN first_name ELSE decrypt(t1.first_name) END as first_name, CASE WHEN decrypt(t1.last_name) IS NULL THEN last_name ELSE decrypt(t1.last_name) END as last_name,t1.dob,t1.highest_grade,t1.exam_grade,t1.current_school_grade,t1.category,t1.master_id,t1.beneficiary_status,t1.enrollment_status,t1.beneficiary_school_status,t1.exam_number,t1.enrollment_date,t1.batch_id,t1.folder_id,
                              t1.cwac_txt,d1.name as school_district,school_information.name as school_name, cwac.name as cwac_name, beneficiary_categories.name as category_name, beneficiary_master_info.highest_grade as master_highest_grade, beneficiary_master_info.current_school_grade as master_current_school_grade, households.id as hh_id, households.hhh_fname, households.hhh_lname, households.hhh_nrc_number, households.number_in_cwac, districts.name as district_name"))
                ->where('t1.batch_id', $batch_id)
                ->where('t1.verification_recommendation', $recommendation)
				// ->whereIn('t1.beneficiary_status',[3,6]);
				->where('t1.beneficiary_status',3);
				
            if (validateisNumeric($verification_type)) {
                $qry->where('t1.verification_type', $verification_type);
            }

            if (isset($answer) && $answer != '') {
                $qry->join('beneficiary_verification_report as t9', function ($join) use ($checklist_item_id, $answer) {
                    $join->on('t1.id', '=', 't9.beneficiary_id')
                        ->where(array('t9.checklist_item_id' => $checklist_item_id))
                        ->whereRaw("t9.response LIKE '$answer%'");
                    //->where('t9.response', 'LIKE', $answer . '%');
                });
            }
			
            if ($filter_string != '') {
                $qry->whereRAW($filter_string);
            }

            if ($category_id == 1) {
                $qry->where('t1.category', $category_id);
                if (isset($school_id) && $school_id != '') {
                    $qry->where('t1.school_id', $school_id);
                }
                if (isset($cwac_id) && $cwac_id != '') {
                    $qry->where('t1.cwac_id', $cwac_id);
                }
                if (isset($district_id) && $district_id != '') {
                    $qry->where('t1.district_id', $district_id);
                }
                if (isset($province_id) && $province_id != '') {
                    $qry->where('t1.province_id', $province_id);
                }
                if (isset($filter_grade) && $filter_grade != '') {
                    $qry->where('t1.highest_grade', $filter_grade);
                }
            } else {
                $qry->whereIn('t1.category', array(2, 3));
                if (isset($school_id) && $school_id != '') {
                    $qry->where('t1.school_id', $school_id);
                }
                if (isset($cwac_id) && $cwac_id != '') {
                    $qry->where('t1.cwac_id', $cwac_id);
                }
                if (isset($district_id) && $district_id != '') {
                    $qry->where('school_information.district_id', $district_id);
                }
                if (isset($province_id) && $province_id != '') {
                    $qry->where('school_information.province_id', $province_id);
                }
                if (isset($filter_category) && $filter_category != '') {
                    $qry->where('t1.category', $filter_category);
                }
                if (isset($filter_grade) && $filter_grade != '') {
                    $qry->where('t1.exam_grade', $filter_grade);
                }
            }
			
            $data = $qry->get();
            $res = array(
                'success' => true,
                'message' => returnMessage($data),
                'results' => $data
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage(),
                'results' => ''
            );
        }
        return response()->json($res);
    }

    public
    function getExamClassesGirlsForAnalysis(Request $req)
    {
        $cwac_id = $req->input('cwac_id');
        $school_id = $req->input('school_id');
        $category_id = $req->input('category');
        $batch_id = $req->input('batch_id');
        $province_id = $req->input('province_id');
        $district_id = $req->input('district_id');
        $recommendation = $req->input('recommendation');
        $placement_status = $req->input('placement_status');
        $qry = DB::table('beneficiary_information')
            ->leftJoin('households', 'beneficiary_information.household_id', '=', 'households.id')
            ->leftJoin('districts', 'beneficiary_information.district_id', '=', 'districts.id')
            ->select('beneficiary_information.*', 'households.id as hh_id', 'households.hhh_fname', 'households.hhh_lname', 'households.hhh_nrc_number', 'households.number_in_cwac', 'districts.name as district_name')
            ->where('beneficiary_information.batch_id', $batch_id)
            ->where('beneficiary_information.beneficiary_status', 3)
            ->where('beneficiary_information.verification_recommendation', $recommendation)
            ->where('beneficiary_information.school_placement_status', $placement_status);
        if (isset($school_id) && $school_id != '') {
            $qry->where('beneficiary_information.school_id', $school_id);
        } else if (isset($cwac_id) && $cwac_id != '') {
            $qry->where('beneficiary_information.cwac_id', $cwac_id);
        } else if (isset($district_id) && $district_id != '') {
            $qry->where('beneficiary_information.district_id', $district_id);
        } else if (isset($province_id) && $province_id != '') {
            $qry->where('beneficiary_information.province_id', $province_id);
        }
        if (isset($category_id) && $category_id != '') {
            $qry->where('beneficiary_information.category', $category_id);
        }
        try {
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $data = decryptArray($data);
            $res = array(
                'success' => true,
                'message' => 'Records fetched successfully!!',
                'results' => $data
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage(),
                'results' => ''
            );
        }
        return response()->json($res);
    }

    function getGirlMatchingInfo(Request $req)
    {
        try {
            $girl_id = $req->input('girl_id');
            $where = array(
                'school_matching_details.girl_id' => $girl_id
            );
            $qry = DB::table('school_matching_details')
                ->leftJoin('school_information', 'school_information.id', '=', 'school_matching_details.school_id')
                ->select('school_matching_details.*', 'school_information.constituency_id', 'school_information.province_id', 'school_information.district_id', 'school_information.id as school_id', 'school_information.name', 'school_information.code')
                ->where($where);
            $data = $qry->first();
            if (!is_null($data)) {
                $results = array(
                    'girl_id' => $data->girl_id,
                    'province_id' => $data->province_id,
                    'district_id' => $data->district_id,
                    'constituency_id' => $data->constituency_id,
                    'school_id' => $data->school_id,
                    'emis_code' => $data->code,
                    'grade' => $data->grade,
                    'beneficiary_school_status' => $data->beneficiary_school_status,
                    'school_fees' => $data->fees
                );
                $res = array(
                    'success' => true,
                    'message' => 'Data fetched successfully',
                    'results' => $results
                );
            } else {
                $res = array(
                    'success' => true,
                    'message' => 'No matching info found!!',
                    'results' => array()
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

    function getGirlSchPlacementInfo(Request $req)
    {
        $girl_id = $req->input('girl_id');
        $where = array(
            'school_placement_details.girl_id' => $girl_id
        );
        try {
            $qry = DB::table('school_placement_details')
                ->leftJoin('school_information', 'school_information.id', '=', 'school_placement_details.school_id')
                // ->select('beneficiary_information.id as girl_id', 'beneficiary_information.verification_school_fees', 'beneficiary_information.beneficiary_school_status', 'beneficiary_information.current_school_grade', 'school_information.*')
                ->select('school_placement_details.*', 'school_information.constituency_id', 'school_information.province_id', 'school_information.district_id', 'school_information.id as school_id', 'school_information.name', 'school_information.code')
                ->where($where);
            $data = $qry->first();
            if (!is_null($data)) {
                $results = array(
                    'girl_id' => $data->girl_id,
                    'qualified_for_selection' => $data->qualified,
                    'province_id' => $data->province_id,
                    'district_id' => $data->district_id,
                    'constituency_id' => $data->constituency_id,
                    'school_id' => $data->school_id,
                    'emis_code' => $data->code,
                    'grade' => $data->grade,
                    'performance' => $data->performance,
                    'beneficiary_school_status' => $data->beneficiary_school_status,
                    'school_fees' => $data->fees
                );
                $res = array(
                    'success' => true,
                    'message' => 'Data fetched successfully',
                    'results' => $results
                );
            } else {
                $res = array(
                    'success' => true,
                    'message' => 'No matching info found!!',
                    'results' => array()
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

    public function getGirlsForLettersGeneration(Request $req)
    {
        $category_id = $req->input('category_id');
        try {
            $data = 1;
            if ($category_id == 2) {
                $data1 = $this->getInschoolGirlsForLettersGeneration($req);
                $data = $data1->get();
            } else {
                $data = $this->getOutofSchoolGirlsForLetters($req);
            }
            $res = array(
                'success' => true,
                'message' => 'Records fetched successfully!!',
                'results' => $data
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage(),
                'results' => ''
            );
        }
        return response()->json($res);
    }

    // public function getGirlsForLettersGeneration(Request $req)
    public function getInschoolGirlsForLettersGeneration(Request $req)
    {
        try {
            $category_id = $req->input('category_id');
            $batch_id = $req->input('batch_id');
            $school_id = $req->input('school_id');
            $province_id = $req->input('province_id');
            $district_id = $req->input('district_id');
            $recommendation = 1;//$req->input('recommendation');
            $whereCat = array($category_id);
            if ($category_id == 2) {
                $whereCat = array(2, 3);
            }
            $qry = DB::table('beneficiary_information as ben_info')
                ->leftJoin('households', 'ben_info.household_id', '=', 'households.id')
                ->leftJoin('districts as dht', 'ben_info.district_id', '=', 'dht.id')
                ->join('school_information', 'ben_info.school_id', '=', 
                    'school_information.id')
                ->leftJoin('districts as td', 'td.id', '=', 
                    'school_information.district_id')
                ->select('ben_info.*',
                    'school_information.name as school_name','households.id as hh_id',
                    'households.hhh_fname', 
                    'households.hhh_lname','households.hhh_nrc_number', 
                    'households.number_in_cwac','dht.name as district_name',
                    'td.name as school_district_name')
                ->where('ben_info.batch_id', $batch_id)
                ->where('ben_info.beneficiary_status', 4)
                ->where('ben_info.enrollment_status', 1)
                ->where('ben_info.verification_recommendation', $recommendation);
            if (isset($category_id) && $category_id != '') {
                $qry->whereIn('ben_info.category', $whereCat);
            }
            if (isset($school_id) && $school_id != '') {
                $qry->where('ben_info.school_id', $school_id);
            } else if (isset($district_id) && $district_id != '') {
                $qry->where('ben_info.district_id', $district_id);
            } else if (isset($province_id) && $province_id != '') {
                $qry->where('ben_info.province_id', $province_id);
            }
            // $data = $qry->get();
            // $data = $qry->get()->toArray();
            // $data = convertStdClassObjToArray($data);
            // $data = decryptArray($data);
            return $qry;    

        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage(),
                'results' => ''
            );
            return response()->json($res);
        }
    }

    public function getOutofSchoolGirlsForLetters(Request $req)
    {
        try {
            $category_id = $req->input('category_id');
            $batch_id = $req->input('batch_id');
            // $qry = DB::table('beneficiary_information')
            //     ->select('beneficiary_information.*')
            //     ->where('beneficiary_information.batch_id', $batch_id)
            //     ->where('beneficiary_information.beneficiary_status', 4)
            //     ->where('beneficiary_information.category', 1)
            //     ->where('beneficiary_information.current_school_grade', '>',3);
            // $data = $qry->get();
            // $data = convertStdClassObjToArray($data);
            // $data = decryptArray($data);
            $qry = DB::table('beneficiary_information as ben_info')
                ->leftJoin('households', 'ben_info.household_id', '=', 'households.id')
                ->leftJoin('districts as dht', 'ben_info.district_id', '=', 'dht.id')
                ->leftjoin('school_information', 'ben_info.school_id', '=', 
                    'school_information.id')
                ->leftJoin('districts as td', 'td.id', '=', 
                    'school_information.district_id') 
                ->select('ben_info.*',
                    'school_information.name as school_name','households.id as hh_id',
                    'households.hhh_fname','households.hhh_lname',
                    'households.hhh_nrc_number','households.number_in_cwac',
                    'dht.name as district_name','td.name as school_district_name')
                ->where('ben_info.batch_id', $batch_id)
                ->where('ben_info.beneficiary_status', 4)
                ->where('ben_info.category', 1)
                ->where('ben_info.current_school_grade', '>',3);
            $data = $qry->get();


            return $data;
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage(),
                'results' => ''
            );
            return response()->json($res);
        }
    }

    public function getVerificationProgress(Request $req)
    {
        $cwac_id = $req->input('cwac_id');
        $school_id = $req->input('school_id');
        $batch_id = $req->input('batch_id');
        $category = $req->input('category_id');
        $column = 'school_id';
        $value = $school_id;
        $whereIn = array(2, 3);
        if ($category == 1) {
            $column = 'cwac_id';
            $value = $cwac_id;
            $whereIn = array(1);
        }
        $where1 = array(
            $column => $value,
            'batch_id' => $batch_id
            //'category' => $category
        );
        $where2 = array(
            $column => $value,
            'batch_id' => $batch_id,
            //'category' => $category,
            'beneficiary_status' => 2
        );
        $where3 = array(
            $column => $value,
            'batch_id' => $batch_id,
            // 'category' => $category,
            'beneficiary_status' => 3
        );
        try {
            $identified = DB::table('beneficiary_information')
                ->where($where1)
                ->whereIn('category', $whereIn)
                ->whereIn('beneficiary_status', array(0, 1, 2))
                ->count();
            $verified = DB::table('beneficiary_information')
                ->where($where2)
                ->whereIn('category', $whereIn)
                ->count();
            $verified_not_submitted = DB::table('beneficiary_information')
                ->where($where2)
                ->whereIn('category', $whereIn)
                ->count();
            $verified_submitted = DB::table('beneficiary_information')
                ->where($where3)
                ->whereIn('category', $whereIn)
                ->count();
            $results = array(
                'identified_girls_count' => $identified,
                'verified_submitted' => $verified_submitted,
                'verified_not_submitted' => $verified_not_submitted,
                'verified_girls_count' => $verified
            );
            $res = array(
                'success' => true,
                'message' => 'Records retrieved successfully',
                'results' => $results
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage(),
                'results' => ''
            );
        }
        return response()->json($res);
    }

	private
    function _getBeneficiaryDisabilities($ben_id)
    {
        $disabilities = DB::table('beneficiary_disabilities')->where(array('beneficiary_id' => $ben_id))->get();
        $disabilities = convertStdClassObjToArray($disabilities);
        $disabilities = convertAssArrayToSimpleArray($disabilities, 'disability_id');
        return implode(",", $disabilities);
    }

    public
    function saveInSchVerificationDetails(Request $req)
    {
        $responses = $req->input('responses');
        $remarks = $req->input('remarks');
        $question_ids = $req->input('question_ids');
        $girl_id = $req->input('girl_id');
        $orders = $req->input('orders');
        $responsesArray = json_decode($responses);
        $is_submit = $req->input('is_submit');
        $beneficiary_status = 2;
        if ($is_submit == 1) {
            $beneficiary_status = 3;
        }

        $res = array();
        $remarksArray = explode(',', $remarks);
        $questionsArray = explode(',', $question_ids);
        $ordersArray = explode(',', $orders);
        $count = count($questionsArray);
        $bursaryType = array();
        $bursaryRegularity = array();
        $scholarshipPackage = array();
        DB::transaction(function () use (&$res, $beneficiary_status, $responsesArray, $remarksArray, $questionsArray, $girl_id, $ordersArray, $count, $bursaryType, $bursaryRegularity, $scholarshipPackage) {
            try {
                for ($i = 0; $i < $count; $i++) {
                    $question_id = $questionsArray[$i];
                    $params[] = array(
                        'checklist_item_id' => $question_id,
                        'beneficiary_id' => $girl_id,
                        'response' => $responsesArray[$i],
                        'remark' => $remarksArray[$i],
                        'created_at' => \Auth::user()->id,
                        'created_by' => \Auth::user()->id
                    );
                    //knock out questions
                    //Question 1 if NO means girl is out of school so category is 'NOT FOUND"
                    if ($ordersArray[$i] == 1) {
                        $quizOneResponse = $responsesArray[$i];
                        if ($quizOneResponse != '') {
                            if ($quizOneResponse == 2) {
                                DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                                DB::table('beneficiary_verification_report')->insert($params);
                                //end survey...update girl recommendation to NOT FOUND(out of school)
                                DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 3, 'beneficiary_status' => $beneficiary_status));
                                //exit here
                                $res = array(
                                    'success' => true,
                                    'message' => 'Details saved successfully!!'
                                );
                                return response()->json($res);
                            }
                        } else {
                            $res = array(
                                'success' => false,
                                'message' => 'Please fill Question No. ' . $ordersArray[$i] . '!!'
                            );
                            return response()->json($res);
                        }
                    }
                    //quiz two
                    if ($ordersArray[$i] == 2) {
                        $quizTwoResponse = $responsesArray[$i];
                        if ($quizTwoResponse == '') {
                            $res = array(
                                'success' => false,
                                'message' => 'Please fill Question No. ' . $ordersArray[$i] . '!!'
                            );
                            return response()->json($res);
                        }
                    }
                    //quiz 3
                    if ($ordersArray[$i] == 3) {
                        $quizThreeResponse = $responsesArray[$i];
                        if ($quizTwoResponse == 2) {//then quiz three is a must
                            if ($quizThreeResponse == '') {
                                $res = array(
                                    'success' => false,
                                    'message' => 'Please fill Question No. ' . $ordersArray[$i] . '!!'
                                );
                                return response()->json($res);
                            } else {
                                DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                                DB::table('beneficiary_verification_report')->insert($params);
                                //end survey...update girl recommendation to NOT FOUND
                                DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 3, 'beneficiary_status' => $beneficiary_status));
                                //exit here
                                $res = array(
                                    'success' => true,
                                    'message' => 'Details saved successfully!!'
                                );
                                return response()->json($res);
                            }
                        } else {
                            //go to quiz four...
                        }
                    }
                    //quiz 4
                    if ($ordersArray[$i] == 4) {
                        $grade = $responsesArray[$i];
                        $next_grade = ($grade + 1);
                        if ($grade == 12) {
                            $next_grade = $grade;
                        }
                        if ($grade != '') {
                            if ($grade < 1 || $grade > 12) {
                                $res = array(
                                    'success' => false,
                                    'message' => 'Incorrect grade provided for question No. ' . $ordersArray[$i] . '. Please correct to proceed!!'
                                );
                                return response()->json($res);
                            } else {
                                if ($grade >= 4) {
                                    if ($grade == 7 || $grade == 9) {
                                        DB::table('beneficiary_information')->where('id', $girl_id)->update(array('current_school_grade' => $grade, 'exam_grade' => $grade, 'initial_category' => 3));
                                    } else {
                                        DB::table('beneficiary_information')->where('id', $girl_id)->update(array('current_school_grade' => $next_grade, 'exam_grade' => $grade, 'initial_category' => 2));
                                    }
                                } else {
                                    DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                                    DB::table('beneficiary_verification_report')->insert($params);
                                    //end survey...update girl recommendation to NOT RECOMMENDED
                                    DB::table('beneficiary_information')->where('id', $girl_id)->update(array('current_school_grade' => $next_grade, 'exam_grade' => $grade, 'verification_recommendation' => 2, 'beneficiary_status' => $beneficiary_status));
                                    //exit here
                                    $res = array(
                                        'success' => true,
                                        'message' => 'Details saved successfully!!'
                                    );
                                    return response()->json($res);
                                }
                            }
                        } else {
                            $res = array(
                                'success' => false,
                                'message' => 'Please enter grade for question No. ' . $ordersArray[$i] . ' !!'
                            );
                            return response()->json($res);
                        }
                    }
                    //quiz 5
                    if ($ordersArray[$i] == 5) {
                        $exam_number = $responsesArray[$i];
                        if (($grade == 7 || $grade == 9) && $exam_number == '') {
                            $res = array(
                                'success' => false,
                                'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                            );
                            return response()->json($res);
                        } else {
                            if ($grade == 7) {
                                $exam_fld = 'grade7_exam_no';
                            } else if ($grade == 9) {
                                $exam_fld = 'grade9_exam_no';
                            } else {
                                $exam_fld = 'exam_number';
                            }
                            DB::table('beneficiary_information')->where('id', $girl_id)->update(array($exam_fld => $exam_number));
                        }
                    }
                    //quiz 6
                    if ($ordersArray[$i] == 6) {
                        $response = $responsesArray[$i];
                        if ($response != '') {
                            $bursary_recipient = $response;
                        } else {
                            $res = array(
                                'success' => false,
                                'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                            );
                            return response()->json($res);
                        }
                    }
                    //quiz 7
                    if ($ordersArray[$i] == 7) {
                        $bursary_type = $responsesArray[$i];
                        $bursaryType['type'] = $responsesArray[$i];
                        if ($bursary_recipient == 1 && $bursary_type == '') {
                            $res = array(
                                'success' => false,
                                'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                            );
                            return response()->json($res);
                        }
                    }
                    //quiz 8
                    if ($ordersArray[$i] == 8) {
                        $scholarship_package = $responsesArray[$i];
                        $scholarshipPackage['package'] = $responsesArray[$i];
                        if ($bursary_type == 29 && $scholarship_package == '') {
                            $res = array(
                                'success' => false,
                                'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                            );
                            return response()->json($res);
                        }
                    }
                    //quiz 9
                    if ($ordersArray[$i] == 9) {
                        $bursary_regular = $responsesArray[$i];
                        $bursaryRegularity['regular'] = $responsesArray[$i];
                        if ($bursary_type == 29 && $scholarship_package == 24) {

                        } else {
                            if ($bursary_recipient == 1 && $bursary_regular == '') {
                                $res = array(
                                    'success' => false,
                                    'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                                );
                                return response()->json($res);
                            }
                        }
                    }
                    //quiz 10
                    if ($ordersArray[$i] == 10) {
                        $disability = $responsesArray[$i];
                    }
                    //quiz 11
                    if ($ordersArray[$i] == 11) {
                        $disabilitiesArray = explode(',', $responsesArray[$i]);
                        $disabilitiesArray = array_filter($disabilitiesArray);
                        $count2 = count($disabilitiesArray);
                        if ($disability == 1) {
                            if ($count2 < 1) {
                                $res = array(
                                    'success' => false,
                                    'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                                );
                                return response()->json($res);
                            } else {
                                for ($j = 0; $j < $count2; $j++) {
                                    $params2[] = array(
                                        'beneficiary_id' => $girl_id,
                                        'disability_id' => $disabilitiesArray[$j]
                                    );
                                }
                                DB::table('beneficiary_disabilities')->where(array('beneficiary_id' => $girl_id))->delete();
                                DB::table('beneficiary_disabilities')->insert($params2);
                            }
                        } else {
                            //just proceed...but clear any previously assigned disabilities
                            DB::table('beneficiary_disabilities')->where(array('beneficiary_id' => $girl_id))->delete();
                        }
                    }
                    //quiz 12
                    if ($ordersArray[$i] == 12) {
                        $girl_school_status = $responsesArray[$i];
                        //[1,17]=day, [2,16]=boarder, [3,18]=weekly, [4,31]=unspecified
                        if ($girl_school_status == '') {
                            $res = array(
                                'success' => false,
                                'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                            );
                            return response()->json($res);
                        } else {
                            if ($girl_school_status == 17) {
                                $foreign_id = 1;
                            } else if ($girl_school_status == 16) {
                                $foreign_id = 2;
                            } else if ($girl_school_status == 18) {
                                $foreign_id = 3;
                            } else if ($girl_school_status == 31) {
                                $foreign_id = 4;
                            }
                            DB::table('beneficiary_information')->where('id', $girl_id)->update(array('beneficiary_school_status' => $foreign_id));
                        }
                    }
                    //quiz 13
                    if ($ordersArray[$i] == 13) {
                        $school_fees = $responsesArray[$i];
                        if ($school_fees == '') {
                            $res = array(
                                'success' => false,
                                'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                            );
                            return response()->json($res);
                        } else {
                            DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_school_fees' => $school_fees));
                        }
                    }
                }
                if ($grade > 12) {//frank
                    DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                    DB::table('beneficiary_verification_report')->insert($params);
                    //knock out this girl
                    //update girl recommendation to NOT RECOMMENDED(not qualified)
                    DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 2, 'beneficiary_status' => $beneficiary_status));
                } else if ($bursaryType['type'] == 32) {
                    DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                    DB::table('beneficiary_verification_report')->insert($params);
                    //knock out this girl
                    //update girl recommendation to NOT RECOMMENDED(not qualified)
                    DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 2, 'beneficiary_status' => $beneficiary_status));
                } else if (($bursaryType['type'] == 25 || $bursaryType['type'] == 26 || $bursaryType['type'] == 27 || $bursaryType['type'] == 28) && $bursaryRegularity['regular'] == 1) {
                    DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                    DB::table('beneficiary_verification_report')->insert($params);
                    //knock out this girl
                    //update girl recommendation to NOT RECOMMENDED(not qualified)
                    DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 2, 'beneficiary_status' => $beneficiary_status));
                } else if ($bursaryType['type'] == 29 && $scholarshipPackage['package'] == 23 && $bursaryRegularity['regular'] == 1) {
                    DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                    DB::table('beneficiary_verification_report')->insert($params);
                    //knock out this girl
                    //update girl recommendation to NOT RECOMMENDED(not qualified)
                    DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 2, 'beneficiary_status' => $beneficiary_status));
                } else {
                    DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                    DB::table('beneficiary_verification_report')->insert($params);
                    //update girl recommendation to RECOMMENDED(qualified)
                    DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 1, 'beneficiary_status' => $beneficiary_status));
                }
                $res = array(
                    'success' => true,
                    'message' => 'Details saved successfully!!'
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
        return response()->json($res);
    }

    public
    function saveOutSchVerificationDetails(Request $req)
    {
        $responses = $req->input('responses');
        $remarks = $req->input('remarks');
        $question_ids = $req->input('question_ids');
        $girl_id = $req->input('girl_id');
        $orders = $req->input('orders');
        $responsesArray = json_decode($responses);
        $is_submit = $req->input('is_submit');
        $beneficiary_status = 2;
        if ($is_submit == 1) {
            $beneficiary_status = 3;
        }

        $res = array();
        // $responsesArray=explode(',',$responses);
        $remarksArray = explode(',', $remarks);
        $questionsArray = explode(',', $question_ids);
        $ordersArray = explode(',', $orders);
        $count = count($questionsArray);
        $bursaryKnockOut = array();
        DB::transaction(function () use (&$res, $responsesArray, $beneficiary_status, $remarksArray, $questionsArray, $girl_id, $ordersArray, $count, $bursaryKnockOut) {
            try {
                for ($i = 0; $i < $count; $i++) {
                    $question_id = $questionsArray[$i];
                    $params[] = array(
                        'checklist_item_id' => $question_id,
                        'beneficiary_id' => $girl_id,
                        'response' => $responsesArray[$i],
                        'remark' => $remarksArray[$i],
                        'created_at' => \Auth::user()->id,
                        'created_by' => \Auth::user()->id
                    );
                    //knock out questions
                    //Question 1 if NO means girl not in the CWAC so category is 'NOT FOUND"
                    if ($ordersArray[$i] == 1) {
                        $quizOneResponse = $responsesArray[$i];
                        if ($quizOneResponse != '') {
                            if ($responsesArray[$i] == 2) {
                                DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                                DB::table('beneficiary_verification_report')->insert($params);
                                //end survey...update girl recommendation to NOT FOUND(out of school)
                                DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 3, 'beneficiary_status' => $beneficiary_status));
                                //exit here
                                $res = array(
                                    'success' => true,
                                    'message' => 'Details saved successfully!!'
                                );
                                return response()->json($res);
                            }
                        } else {
                            $res = array(
                                'success' => false,
                                'message' => 'Please fill Question No. ' . $ordersArray[$i] . '!!'
                            );
                            return response()->json($res);
                        }
                    }
                    //Question 2 if NO means girl has never attended secondary school so category is 'NOT RECOMMENDED"
                    if ($ordersArray[$i] == 2) {
                        $quizTwoResponse = $responsesArray[$i];
                        if ($quizTwoResponse != '') {
                            if ($quizTwoResponse == 2) {
                                DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                                DB::table('beneficiary_verification_report')->insert($params);
                                //end survey...update girl recommendation to NOT RECOMMENDED
                                DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 2, 'beneficiary_status' => $beneficiary_status));
                                //exit here
                                $res = array(
                                    'success' => true,
                                    'message' => 'Details saved successfully!!'
                                );
                                return response()->json($res);
                            }
                        } else {
                            $res = array(
                                'success' => false,
                                'message' => 'Please fill Question No. ' . $ordersArray[$i] . '!!'
                            );
                            return response()->json($res);
                        }
                    }
                    //Question 3 if 7>grade>11 means girl hasn't qualified for secondary admission or has completed secondary education so category is 'NOT RECOMMENDED"
                    if ($ordersArray[$i] == 3) {
                        $grade = $responsesArray[$i];
                        if ($grade != '') {
                            // if ($grade < 7 || $grade > 12) {
                            if ($grade < 4 || $grade > 12) {
                                $res = array(
                                    'success' => false,
                                    'message' => 'Incorrect grade for a girl qualified for secondary school in question No. ' . $ordersArray[$i] . '. Please correct to proceed!!'
                                );
                                return response()->json($res);
                            } else {
                                DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 1, 'beneficiary_status' => $beneficiary_status));
                                DB::table('beneficiary_information')->where('id', $girl_id)->update(array('highest_grade' => $grade));
                            }
                        } else {
                            $res = array(
                                'success' => false,
                                'message' => 'Please enter grade for question No. ' . $ordersArray[$i] . ' !!'
                            );
                            return response()->json($res);
                        }
                    }
                    if ($ordersArray[$i] == 5) {
                        $quizFiveResponse = $responsesArray[$i];
                        if ($quizFiveResponse == '') {
                            $res = array(
                                'success' => false,
                                'message' => 'Please fill Question No. ' . $ordersArray[$i] . '!!'
                            );
                            return response()->json($res);
                        }
                    }
                    if ($ordersArray[$i] == 6) {
                        if ($quizFiveResponse == 2) {
                            $params[5]['response'] = null;//make sure that if has disability is NO then disabilities array be null
                            DB::table('beneficiary_disabilities')->where(array('beneficiary_id' => $girl_id))->delete();
                        } else {
                            $disabilitiesArray = explode(',', $responsesArray[$i]);
                            $disabilitiesArray = array_filter($disabilitiesArray);
                            $count2 = count($disabilitiesArray);
                            if ($count2 < 1) {
                                $res = array(
                                    'success' => false,
                                    'message' => 'Please specify the disability in question No. ' . $ordersArray[$i] . ' !!'
                                );
                                return response()->json($res);
                            } else {
                                for ($j = 0; $j < $count2; $j++) {
                                    $params2[] = array(
                                        'beneficiary_id' => $girl_id,
                                        'disability_id' => $disabilitiesArray[$j]
                                    );
                                }
                                DB::table('beneficiary_disabilities')->where(array('beneficiary_id' => $girl_id))->delete();
                                DB::table('beneficiary_disabilities')->insert($params2);
                            }
                        }
                    }
                }
                DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                DB::table('beneficiary_verification_report')->insert($params);
                $res = array(
                    'success' => true,
                    'message' => 'Details saved successfully!!'
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

    public
    function saveCommunityBasedVerificationDetailsTwo(Request $req)
    {
        $responses = $req->input('responses');
        $remarks = $req->input('remarks');
        $question_ids = $req->input('question_ids');
        $girl_id = $req->input('girl_id');
        $orders = $req->input('orders');
        $responsesArray = json_decode($responses);
        $is_submit = $req->input('is_submit');
        $beneficiary_status = 2;
        if ($is_submit == 1) {
            $beneficiary_status = 3;
        }

        $res = array();
        $remarksArray = explode(',', $remarks);
        $questionsArray = explode(',', $question_ids);
        $ordersArray = explode(',', $orders);
        $count = count($questionsArray);
        $bursaryType = array();
        $bursaryRegularity = array();
        $scholarshipPackage = array();
        $skip_matching = 0;
        DB::transaction(function () use (&$res, $skip_matching, $beneficiary_status, $responsesArray, $remarksArray, $questionsArray, $girl_id, $ordersArray, $count, $bursaryType, $bursaryRegularity, $scholarshipPackage) {
            try {
                for ($i = 0; $i < $count; $i++) {
                    $question_id = $questionsArray[$i];
                    $params[] = array(
                        'checklist_item_id' => $question_id,
                        'beneficiary_id' => $girl_id,
                        'response' => $responsesArray[$i],
                        'remark' => $remarksArray[$i],
                        'created_at' => Carbon::now(),
                        'created_by' => \Auth::user()->id
                    );
                    //knock out questions
                    //Question 1 if NO means girl is out of school so category is 'NOT FOUND"
                    if ($ordersArray[$i] == 1) {
                        $quizOneResponse = $responsesArray[$i];
                        if ($quizOneResponse != '') {
                            if ($quizOneResponse == 2) {
                                DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                                DB::table('beneficiary_verification_report')->insert($params);
                                //end survey...update girl recommendation to NOT FOUND(out of school)
                                DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 3, 'beneficiary_status' => $beneficiary_status));
                                //exit here
                                $res = array(
                                    'success' => true,
                                    'message' => 'Details saved successfully!!'
                                );
                                return response()->json($res);
                            }
                        } else {
                            $res = array(
                                'success' => false,
                                'message' => 'Please fill Question No. ' . $ordersArray[$i] . '!!'
                            );
                            return response()->json($res);
                        }
                    }
                    //Question 2 if NO means girl has never attended secondary school so category is 'NOT RECOMMENDED"
                    if ($ordersArray[$i] == 2) {
                        $quizTwoResponse = $responsesArray[$i];
                        if ($quizTwoResponse != '') {
                            if ($quizTwoResponse == 2) {
                                DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                                DB::table('beneficiary_verification_report')->insert($params);
                                //end survey...update girl recommendation to NOT RECOMMENDED
                                DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 2, 'beneficiary_status' => $beneficiary_status));
                                //exit here
                                $res = array(
                                    'success' => true,
                                    'message' => 'Details saved successfully!!'
                                );
                                return response()->json($res);
                            }
                        } else {
                            $res = array(
                                'success' => false,
                                'message' => 'Please fill Question No. ' . $ordersArray[$i] . '!!'
                            );
                            return response()->json($res);
                        }
                    }
                    //quiz 3
                    if ($ordersArray[$i] == 3) {
                        $quizThreeResponse = $responsesArray[$i];
                        if ($quizTwoResponse == 1) {//then quiz three is a must
                            if ($quizThreeResponse == '') {
                                $res = array(
                                    'success' => false,
                                    'message' => 'Please fill Question No. ' . $ordersArray[$i] . '!!'
                                );
                                return response()->json($res);
                            } else {
                                $skip_matching = 1;
                            }
                        } else {
                            //go to quiz four...
                        }
                    }
                    //Quiz 4
                    if ($ordersArray[$i] == 4) {
                        $quizFourResponse = $responsesArray[$i];
                        if ($quizThreeResponse == 2) {//then quiz four is a must
                            if ($quizFourResponse == '') {
                                $res = array(
                                    'success' => false,
                                    'message' => 'Please fill Question No. ' . $ordersArray[$i] . '!!'
                                );
                                return response()->json($res);
                            } else {
                                if ($quizFourResponse == 2) {
                                    DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                                    DB::table('beneficiary_verification_report')->insert($params);
                                    //end survey...update girl recommendation to NOT RECOMMENDED
                                    DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 2, 'beneficiary_status' => $beneficiary_status));
                                    //exit here
                                    $res = array(
                                        'success' => true,
                                        'message' => 'Details saved successfully!!'
                                    );
                                    return response()->json($res);
                                }
                            }
                        } else {
                            //continue...
                        }
                    }
                    //quiz 5
                    if ($ordersArray[$i] == 5) {
                        $quizFiveResponse = $responsesArray[$i];
                        if ($quizFourResponse == 1) {//then quiz five is a must
                            if ($quizFiveResponse == '') {
                                $res = array(
                                    'success' => false,
                                    'message' => 'Please fill Question No. ' . $ordersArray[$i] . '!!'
                                );
                                return response()->json($res);
                            } else {
                                if ($quizFiveResponse < 1 || $quizFiveResponse > 12) {
                                    $res = array(
                                        'success' => false,
                                        'message' => 'Incorrect grade provided for question No. ' . $ordersArray[$i] . '. Please correct to proceed!!'
                                    );
                                    return response()->json($res);
                                } else {
                                    // if ($quizFiveResponse < 8) {//Not Recommended
                                    if ($quizFiveResponse < 4) {//Not Recommended
                                        DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                                        DB::table('beneficiary_verification_report')->insert($params);
                                        //end survey...update girl recommendation to NOT RECOMMENDED
                                        DB::table('beneficiary_information')->where('id', $girl_id)->update(array('highest_grade' => $quizFiveResponse, 'verification_recommendation' => 2, 'beneficiary_status' => $beneficiary_status));
                                        //exit here
                                        $res = array(
                                            'success' => true,
                                            'message' => 'Details saved successfully!!'
                                        );
                                        return response()->json($res);
                                    } else {
                                        //continue
                                    }
                                    DB::table('beneficiary_information')->where('id', $girl_id)->update(array('highest_grade' => $quizFiveResponse));
                                }
                            }
                        } else {
                            //continue...
                        }
                    }
                    //quiz 7
                    if ($ordersArray[$i] == 7) {
                        $school = $responsesArray[$i];
                        if ($quizThreeResponse == 1 && $school == '') {
                            $res = array(
                                'success' => false,
                                'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                            );
                            return response()->json($res);
                        }
                    }
                    //quiz 9
                    if ($ordersArray[$i] == 9) {
                        $grade = $responsesArray[$i];
                        if ($quizThreeResponse == 1) {//then quiz nine is a must
                            if ($grade != '') {
                                //todo: comment out for 2021 verification
                                /*  $next_grade = ($grade + 1);
                                  if ($grade == 12) {
                                      $next_grade = $grade;
                                  }*/
                                //todo: end 2021 commented out
                                //added for 2021...no grade incrementation
                                $next_grade = $grade;
                                //end added for 2021
                                if ($grade < 1 || $grade > 12) {
                                    $res = array(
                                        'success' => false,
                                        'message' => 'Incorrect grade provided for question No. ' . $ordersArray[$i] . '. Please correct to proceed!!'
                                    );
                                    return response()->json($res);
                                } else {
                                    // if ($grade < 7) {//Not Recommended
                                    if ($grade < 4) {//Not Recommended
                                        DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                                        DB::table('beneficiary_verification_report')->insert($params);
                                        //end survey...update girl recommendation to NOT RECOMMENDED
                                        DB::table('beneficiary_information')->where('id', $girl_id)->update(array('current_school_grade' => $grade, 'exam_grade' => $grade, 'verification_recommendation' => 2, 'beneficiary_status' => $beneficiary_status));
                                        //exit here
                                        $res = array(
                                            'success' => true,
                                            'message' => 'Details saved successfully!!'
                                        );
                                        return response()->json($res);
                                    }
                                    if ($grade == 7 || $grade == 9 || $grade == 12) {
                                        DB::table('beneficiary_information')->where('id', $girl_id)->update(array('current_school_grade' => $grade, 'exam_grade' => $grade));
                                    } else {
                                        DB::table('beneficiary_information')->where('id', $girl_id)->update(array('current_school_grade' => $next_grade, 'exam_grade' => $next_grade));
                                    }
                                }
                            } else {
                                $res = array(
                                    'success' => false,
                                    'message' => 'Please enter grade for question No. ' . $ordersArray[$i] . ' !!'
                                );
                                return response()->json($res);
                            }
                        }
                    }
                    //quiz 10
                    if ($ordersArray[$i] == 10) {
                        $exam_number = $responsesArray[$i];
                        if (($grade == 9 || $grade == 7) && $exam_number == '') {
                            $res = array(
                                'success' => false,
                                'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                            );
                            return response()->json($res);
                        } else {
                            if ($grade == 7) {
                                DB::table('beneficiary_information')->where('id', $girl_id)->update(array('grade7_exam_no' => $exam_number));
                            } else {
                                DB::table('beneficiary_information')->where('id', $girl_id)->update(array('grade9_exam_no' => $exam_number));
                            }
                        }
                    }
                    //quiz 11
                    if ($ordersArray[$i] == 11) {
                        $girl_school_status = $responsesArray[$i];
                        //[1,17]=day, [2,16]=boarder, [3,18]=weekly, [4,31]=unspecified
                        // if ($grade > 6 && $grade < 13) {//if quiz 9 suffice then eleven is a must
                        if ($grade > 3 && $grade < 13) {//if quiz 9 suffice then eleven is a must
                            if ($girl_school_status == '') {
                                $res = array(
                                    'success' => false,
                                    'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                                );
                                return response()->json($res);
                            } else {
                                if ($girl_school_status == 17) {
                                    $foreign_id = 1;
                                } else if ($girl_school_status == 16) {
                                    $foreign_id = 2;
                                } else if ($girl_school_status == 18) {
                                    $foreign_id = 3;
                                } else if ($girl_school_status == 31) {
                                    $foreign_id = 4;
                                }
                                DB::table('beneficiary_information')->where('id', $girl_id)->update(array('beneficiary_school_status' => $foreign_id));
                            }
                        }
                    }
                    //quiz 12
                    if ($ordersArray[$i] == 12) {
                        $bursary_recipient = $responsesArray[$i];
                        if ($grade > 3 && $grade < 13) {//if quiz 9 suffice then twelve is a must
                            if ($bursary_recipient == '') {
                                $res = array(
                                    'success' => false,
                                    'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                                );
                                return response()->json($res);
                            } /*else {
                                $bursary_recipient = $response;
                            }*/
                        }
                    }
                    //quiz 13
                    if ($ordersArray[$i] == 13) {
                        $bursary_type = $responsesArray[$i];
                        $bursaryType['type'] = $responsesArray[$i];
                        if ($bursary_recipient == 1 && $bursary_type == '') {
                            $res = array(
                                'success' => false,
                                'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                            );
                            return response()->json($res);
                        }
                    }
                    //quiz 14
                    if ($ordersArray[$i] == 14) {
                        $scholarship_package = $responsesArray[$i];
                        $scholarshipPackage['package'] = $responsesArray[$i];
                        if ($bursary_type == 29 && $scholarship_package == '') {
                            $res = array(
                                'success' => false,
                                'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                            );
                            return response()->json($res);
                        }
                    }
                    //quiz 15
                    if ($ordersArray[$i] == 15) {
                        $bursary_regular = $responsesArray[$i];
                        $bursaryRegularity['regular'] = $responsesArray[$i];
                        if ($bursary_type == 29 && $scholarship_package == 24) {

                        } else {
                            if ($bursary_recipient == 1 && $bursary_regular == '') {
                                $res = array(
                                    'success' => false,
                                    'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                                );
                                return response()->json($res);
                            }
                        }
                    }
                    //quiz 16
                    if ($ordersArray[$i] == 16) {
                        $disability = $responsesArray[$i];
                        if ($quizFiveResponse > 6 || $grade > 3) {
                            if ($disability == '') {
                                $res = array(
                                    'success' => false,
                                    'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                                );
                                return response()->json($res);
                            }
                        }
                    }
                    //quiz 17
                    if ($ordersArray[$i] == 17) {
                        $disabilitiesArray = explode(',', $responsesArray[$i]);
                        $disabilitiesArray = array_filter($disabilitiesArray);
                        $count2 = count($disabilitiesArray);
                        if ($disability == 1) {
                            if ($count2 < 1) {
                                $res = array(
                                    'success' => false,
                                    'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                                );
                                return response()->json($res);
                            } else {
                                for ($j = 0; $j < $count2; $j++) {
                                    $params2[] = array(
                                        'beneficiary_id' => $girl_id,
                                        'disability_id' => $disabilitiesArray[$j]
                                    );
                                }
                                DB::table('beneficiary_disabilities')->where(array('beneficiary_id' => $girl_id))->delete();
                                DB::table('beneficiary_disabilities')->insert($params2);
                            }
                        } else {
                            //just proceed...but clear any previously assigned disabilities
                            DB::table('beneficiary_disabilities')->where(array('beneficiary_id' => $girl_id))->delete();
                        }
                    }
                }
                if ($grade > 12) {//frank
                    DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                    DB::table('beneficiary_verification_report')->insert($params);
                    //knock out this girl
                    //update girl recommendation to NOT RECOMMENDED(not qualified)
                    DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 2, 'beneficiary_status' => $beneficiary_status));
                } else if ($bursaryType['type'] == 32) {
                    DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                    DB::table('beneficiary_verification_report')->insert($params);
                    //knock out this girl
                    //update girl recommendation to NOT RECOMMENDED(not qualified)
                    DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 2, 'beneficiary_status' => $beneficiary_status));
                } else if (($bursaryType['type'] == 25 || $bursaryType['type'] == 26 || $bursaryType['type'] == 27 || $bursaryType['type'] == 28) && $bursaryRegularity['regular'] == 1) {
                    DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                    DB::table('beneficiary_verification_report')->insert($params);
                    //knock out this girl
                    //update girl recommendation to NOT RECOMMENDED(not qualified)
                    DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 2, 'beneficiary_status' => $beneficiary_status));
                } else if ($bursaryType['type'] == 29 && $scholarshipPackage['package'] == 23 && $bursaryRegularity['regular'] == 1) {
                    DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                    DB::table('beneficiary_verification_report')->insert($params);
                    //knock out this girl
                    //update girl recommendation to NOT RECOMMENDED(not qualified)
                    DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 2, 'beneficiary_status' => $beneficiary_status));
                } else {
                    DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                    DB::table('beneficiary_verification_report')->insert($params);
                    //update girl recommendation to RECOMMENDED(qualified)
                    DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 1, 'beneficiary_status' => $beneficiary_status));
                }
                DB::table('beneficiary_information')->where('id', $girl_id)->update(array('skip_matching' => $skip_matching));
                if ($quizThreeResponse == 1 && $school != '') {//update beneficiary school and category
                    DB::table('beneficiary_information')->where('id', $girl_id)->update(array('school_id' => $school, 'exam_school_id' => $school));//, 'category' => 2));
                }
                $res = array(
                    'success' => true,
                    'message' => 'Details saved successfully!!'
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
        return response()->json($res);
    }

    public
    function saveExamClassesVerificationDetails(Request $req)
    {
        $responses = $req->input('responses');
        $remarks = $req->input('remarks');
        $question_ids = $req->input('question_ids');
        $girl_id = $req->input('girl_id');
        $orders = $req->input('orders');
        $responsesArray = json_decode($responses);
        $is_submit = $req->input('is_submit');
        $beneficiary_status = 2;
        if ($is_submit == 1) {
            $beneficiary_status = 8;
        }

        $res = array();
        // $responsesArray=explode(',',$responses);
        $remarksArray = explode(',', $remarks);
        $questionsArray = explode(',', $question_ids);
        $ordersArray = explode(',', $orders);
        $count = count($questionsArray);
        $bursaryKnockOut = array();
        DB::transaction(function () use (&$res, $responsesArray, $beneficiary_status, $remarksArray, $questionsArray, $girl_id, $ordersArray, $count, $bursaryKnockOut) {
            try {
                for ($i = 0; $i < $count; $i++) {
                    $question_id = $questionsArray[$i];
                    $params[] = array(
                        'checklist_item_id' => $question_id,
                        'beneficiary_id' => $girl_id,
                        'response' => $responsesArray[$i],
                        'remark' => $remarksArray[$i],
                        'created_at' => \Auth::user()->id,
                        'created_by' => \Auth::user()->id
                    );
                    //knock out questions
                    //Question 1 if NO means girl not in the School so category is 'NOT FOUND"
                    if ($ordersArray[$i] == 1) {
                        if ($responsesArray[$i] == 2) {
                            DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                            DB::table('beneficiary_verification_report')->insert($params);
                            //end survey...update girl recommendation to NOT FOUND(out of school)
                            DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 3, 'beneficiary_status' => $beneficiary_status));
                            //exit here
                            $res = array(
                                'success' => true,
                                'message' => 'Details saved successfully!!'
                            );
                            return response()->json($res);
                        }
                    } else {
                        //go to quiz 2
                    }
                    //Question 2 if grade is either 7 or 9, this is the correct girl do RECOMMEND else move to FOLLOW UP
                    if ($ordersArray[$i] == 2) {
                        $grade = $responsesArray[$i];
                        if ($grade != '') {
                            if ($grade < 1 || $grade > 12) {
                                $res = array(
                                    'success' => false,
                                    'message' => 'Incorrect grade provided for question No. ' . $ordersArray[$i] . '. Please correct to proceed!!'
                                );
                                return response()->json($res);
                            } else {
                                if ($grade == 7 || $grade == 9) {
                                    DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 1, 'beneficiary_status' => $beneficiary_status));
                                } else {
                                    DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 2, 'beneficiary_status' => $beneficiary_status));
                                }
                                DB::table('beneficiary_information')->where('id', $girl_id)->update(array('current_school_grade' => $grade));
                            }
                        } else {
                            $res = array(
                                'success' => false,
                                'message' => 'Please enter grade for question No. ' . $ordersArray[$i] . ' !!'
                            );
                            return response()->json($res);
                        }
                    }
                    //Question 3 Capture Examination Numbers of the lady"
                    if ($ordersArray[$i] == 3) {
                        $exam_number = $responsesArray[$i];
                        if ($grade == 7 || $grade == 9) {
                            if ($exam_number == '') {
                                $res = array(
                                    'success' => false,
                                    'message' => 'Incorrect Examination Number provided for question No. ' . $ordersArray[$i] . '. Please correct to proceed!!'
                                );
                                return response()->json($res);
                            } else {
                                DB::table('beneficiary_information')->where('id', $girl_id)->update(array('exam_number' => $exam_number));
                            }
                        }
                    } else {
                        //go to quiz 4
                    }
                    if ($ordersArray[$i] == 5) {
                        $disabilitiesArray = explode(',', $responsesArray[$i]);
                        $count2 = count($disabilitiesArray);
                        for ($j = 0; $j < $count2; $j++) {
                            $params2[] = array(
                                'beneficiary_id' => $girl_id,
                                'disability_id' => $disabilitiesArray[$j]
                            );
                        }
                        DB::table('beneficiary_disabilities')->where(array('beneficiary_id' => $girl_id))->delete();
                        DB::table('beneficiary_disabilities')->insert($params2);
                    }
                }
                DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                DB::table('beneficiary_verification_report')->insert($params);
                $res = array(
                    'success' => true,
                    'message' => 'Details saved successfully!!'
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

    public function saveSchoolMatchingInfo(Request $req)
    {
        $girl_id = $req->input('girl_id');
        $school_id = $req->input('school_id');
        $constituency_id = $req->input('constituency_id');
        $grade = $req->input('grade');
        $status = $req->input('beneficiary_school_status');
        $fees = $req->input('school_fees');
        $is_submit = $req->input('is_submit');
        $alreadyCaptured = recordExists('school_matching_details', array('girl_id' => $girl_id));
        $matching_status = 1;
        $ben_status = 4;
        $enrollment_status = 1;
        $enrollment_date = Carbon::now();
        if ($grade < 8) {
            $matching_status = 2;
            $ben_status = 6;
            $enrollment_status = 0;
            $enrollment_date = '';
        }
        $params = array(
            'school_id' => $school_id,
            'exam_school_id' => $school_id,
            'current_school_grade' => $grade,
            'exam_grade' => ($grade - 1),
            'beneficiary_school_status' => $status,
            'verification_school_fees' => $fees,
            'school_matching_status' => $matching_status
        );
        $matchingParams = array(
            'girl_id' => $girl_id,
            'school_id' => $school_id,
            'grade' => $grade,
            'beneficiary_school_status' => $status,
            'fees' => $fees,
            'created_at' => Carbon::now(),
            'created_by' => \Auth::user()->id
        );
        if ($is_submit == 1) {
            $params = array(
                'school_id' => $school_id,
                'exam_school_id' => $school_id,
                'current_school_grade' => $grade,
                'exam_grade' => ($grade - 1),
                'beneficiary_school_status' => $status,
                'verification_school_fees' => $fees,
                'school_matching_status' => $matching_status,
                'beneficiary_status' => $ben_status,
                'enrollment_date' => $enrollment_date,
                'enrollment_status' => $enrollment_status
            );
        }
        try {
            if ($alreadyCaptured == true) {
                DB::table('school_matching_details')->where('girl_id', $girl_id)->update($matchingParams);
            } else {
                DB::table('school_matching_details')->insert($matchingParams);
            }
            DB::table('beneficiary_information')->where('id', $girl_id)->update($params);
            //update school constituency
            if (validateisNumeric($constituency_id)) {
                DB::table('school_information')->where('id', $school_id)->update(array('constituency_id' => $constituency_id));
            }
            $res = array(
                'success' => true,
                'message' => 'Details saved successfully!!'
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
     * @param Request $req
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveSchoolPlacementInfo(Request $req)
    {
        $girl_id = $req->input('girl_id');
        $school_id = $req->input('school_id');
        $exam_no = $req->input('exam_no');
        $exam_grade = $req->input('exam_grade');
        $grade = $req->input('grade');
        $status = $req->input('beneficiary_school_status');
        $fees = $req->input('school_fees');
        $performance = $req->input('performance');
        $is_submit = $req->input('is_submit');
        $constituency_id = $req->input('constituency_id');
        $qualified_for_selection = $req->input('qualified_for_selection');
        $alreadyCaptured = recordExists('school_placement_details', array('girl_id' => $girl_id));
        $exam_no_fdl = 'grade9_exam_no';
        if ($exam_grade == 7 || $exam_grade === 7) {
            $exam_no_fdl = 'grade7_exam_no';
        }
        if ($qualified_for_selection != 1 || $qualified_for_selection == 0) {
            $placementParams = array(
                'girl_id' => $girl_id,
                'qualified' => $qualified_for_selection,
                'school_id' => NULL,
                'grade' => NULL,
                'beneficiary_school_status' => NULL,
                'fees' => NULL,
                'performance' => NULL,
                'created_at' => Carbon::now(),
                'created_by' => \Auth::user()->id
            );
            try {
                $details = array(
                    'school_placement_status' => 2,
                    $exam_no_fdl => $exam_no
                );
                if ($is_submit == 1) {
                    $details = array(
                        'school_placement_status' => 2,
                        'beneficiary_status' => 6
                    );
                }
                if ($alreadyCaptured == true) {
                    DB::table('school_placement_details')->where('girl_id', $girl_id)->update($placementParams);
                } else {
                    DB::table('school_placement_details')->insert($placementParams);
                }
                DB::table('beneficiary_information')->where('id', $girl_id)->update($details);
                $res = array(
                    'success' => true,
                    'message' => 'Details saved successfully!!'
                );
            } catch (\Exception $e) {
                $res = array(
                    'success' => false,
                    'message' => $e->getMessage()
                );
            }
            return response()->json($res);
        }
        if ($qualified_for_selection == 1 || $qualified_for_selection != 0) {
            if ($school_id == '' || $grade == '' || $status == '') {
                $res = array(
                    'success' => false,
                    'message' => 'Fill all the mandatory fields(*)!!'
                );
                return response()->json($res);
            }
            // if ($exam_grade < 7) {
            if ($exam_grade < 4) {
                $res = array(
                    'success' => false,
                    'message' => 'Incorrect examination grade!!'
                );
                return response()->json($res);
            }
            if ($grade < 8) {
                $res = array(
                    'success' => false,
                    'message' => 'The assigned grade is not eligible for placement, kindly change eligibility status!!'
                );
                return response()->json($res);
            }
            if ($exam_grade == 7 && $grade != 8) {
                $res = array(
                    'success' => false,
                    'message' => 'Incorrect grade assigned to the learner!!'
                );
                return response()->json($res);
            }
            if ($exam_grade == 9) {
                if ($grade == 10 || $grade == 9 || $grade == 8) {

                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'Incorrect grade assigned to the learner!!'
                    );
                    return response()->json($res);
                }
            }
        }
        $placementParams = array(
            'girl_id' => $girl_id,
            'qualified' => $qualified_for_selection,
            'school_id' => $school_id,
            'grade' => $grade,
            'beneficiary_school_status' => $status,
            'fees' => $fees,
            'performance' => $performance,
            'created_at' => Carbon::now(),
            'created_by' => \Auth::user()->id
        );
        $benInfoParams = array(
            'school_id' => $school_id,
            'exam_grade' => $exam_grade,
            'current_school_grade' => $grade,
            'beneficiary_school_status' => $status,
            'verification_school_fees' => $fees,
            'school_placement_status' => 1,
            $exam_no_fdl => $exam_no
        );
        if ($is_submit == 1) {
            $benInfoParams = array(
                'school_id' => $school_id,
                'exam_grade' => $exam_grade,
                'current_school_grade' => $grade,
                'beneficiary_school_status' => $status,
                'verification_school_fees' => $fees,
                'beneficiary_status' => 4,
                'school_placement_status' => 1,
                'enrollment_date' => Carbon::now(),
                'enrollment_status' => 1,
                $exam_no_fdl => $exam_no
            );
        }
        try {
            if ($alreadyCaptured == true) {
                DB::table('school_placement_details')->where('girl_id', $girl_id)->update($placementParams);
            } else {
                DB::table('school_placement_details')->insert($placementParams);
            }
            DB::table('beneficiary_information')->where('id', $girl_id)->update($benInfoParams);
            //log beneficiary grade transitioning
            logBeneficiaryGradeTransitioning($girl_id, $grade, $school_id, \Auth::user()->id);
            //update school constituency
            if (validateisNumeric($constituency_id)) {
                DB::table('school_information')->where('id', $school_id)->update(array('constituency_id' => $constituency_id));
            }
            $res = array(
                'success' => true,
                'message' => 'Details saved successfully!!'
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
function getGirlsForSchoolMatching(Request $req)
{
    $category_id = $req->input('category');
    $batch_id = $req->input('batch_id');
    $province_id = $req->input('province_id');
    $district_id = $req->input('district_id');
    $cwac_id = $req->input('cwac_id');
    $option_id = $req->input('option_id');
    $qry = DB::table('beneficiary_information as t1')
        ->leftJoin('beneficiary_master_info', 't1.master_id', '=', 'beneficiary_master_info.id')
        ->leftJoin('households', 't1.household_id', '=', 'households.id')
        ->leftJoin('cwac', 't1.cwac_id', '=', 'cwac.id')
        ->leftJoin('school_information', 't1.school_id', '=', 'school_information.id')
        ->leftJoin('districts', 't1.district_id', '=', 'districts.id')
        ->select(DB::raw('t1.id,t1.matching_form_printed,t1.school_matching_status,
            t1.beneficiary_id,t1.household_id,t1.exam_school_id,t1.school_id,
            t1.cwac_id,t1.acc_id,t1.ward_id,t1.constituency_id,t1.district_id,
            t1.province_id,
            CASE WHEN decrypt(t1.first_name) IS NULL THEN first_name 
            ELSE decrypt(t1.first_name) 
            END as first_name, CASE WHEN decrypt(t1.last_name) IS NULL THEN last_name 
            ELSE decrypt(t1.last_name) END as last_name,t1.dob,t1.highest_grade,t1.exam_grade,
            t1.current_school_grade,t1.category,t1.master_id,t1.beneficiary_status,t1.enrollment_status,
            t1.beneficiary_school_status,t1.exam_number,t1.enrollment_date,t1.batch_id,t1.folder_id,
            t1.cwac_txt,cwac.name as cwac_name, school_information.name as school_name, 
            beneficiary_master_info.highest_grade as master_highest_grade, 
            beneficiary_master_info.current_school_grade as master_current_school_grade, 
            households.id as hh_id, households.hhh_fname, households.hhh_lname, 
            households.hhh_nrc_number, households.number_in_cwac, districts.name as district_name'))
        ->where('t1.batch_id', $batch_id)
        ->where('t1.beneficiary_status', 5);
    if (isset($option_id) && $option_id != '') {
        if ($option_id == 1) {
            $qry->where('t1.school_matching_status', 1);
        } else if ($option_id == 2) {
            $qry->where(function ($query) {
                $query->where('t1.school_matching_status', '<>', 1)
                    ->orWhereNull('t1.school_matching_status');
            });
        }
    }
    if (isset($cwac_id) && $cwac_id != '') {
        $qry->where('t1.cwac_id', $cwac_id);
    } else if (isset($district_id) && $district_id != '') {
        $qry->where('t1.district_id', $district_id);
    } else if (isset($province_id) && $province_id != '') {
        $qry->where('t1.province_id', $province_id);
    }
    if (isset($category_id) && $category_id != '') {
        $qry->where('t1.category', $category_id);
    }
    $qry->orderBy('t1.cwac_id');
    try {
        $data = $qry->get();
        $res = array(
            'success' => true,
            'message' => 'Records fetched successfully!!',
            'results' => $data
        );
    } catch (\Exception $e) {
        $res = array(
            'success' => false,
            'message' => $e->getMessage(),
            'results' => ''
        );
    }
    return response()->json($res);
}

    public
    function getGirlsForResultsEntry(Request $req)
    {//frank new
        try {
            $category_id = $req->input('category');
            $batch_id = $req->input('batch_id');
            $province_id = $req->input('province_id');
            $district_id = $req->input('district_id');
            $cwac_id = $req->input('cwac_id');
            $school_id = $req->input('school_id');
            $option_id = $req->input('option_id');
            $qry = DB::table('beneficiary_information as t1')
                ->join('households', 't1.household_id', '=', 'households.id')
                ->join('school_information', 't1.exam_school_id', '=', 'school_information.id')
                ->join('districts as d1', 'school_information.district_id', '=', 'd1.id')
                ->leftJoin('school_placement_details', 't1.id', '=', 'school_placement_details.girl_id')
                ->join('districts', 't1.district_id', '=', 'districts.id')
                ->join('beneficiary_categories as b1', 't1.category', '=', 'b1.id')
                ->select(DB::raw('t1.id,t1.placement_form_printed,t1.school_placement_status,t1.beneficiary_id,t1.household_id,t1.exam_school_id,t1.school_id,t1.cwac_id,t1.acc_id,t1.ward_id,t1.constituency_id,t1.district_id,t1.province_id,
                              b1.name as category_name,decrypt(t1.first_name) as first_name, decrypt(t1.last_name) as last_name,t1.dob,t1.highest_grade,t1.exam_grade,t1.current_school_grade,t1.category,t1.master_id,t1.beneficiary_status,t1.enrollment_status,
                              t1.beneficiary_school_status,if(t1.exam_grade=7,t1.grade7_exam_no,t1.grade9_exam_no) as exam_number,t1.enrollment_date,t1.batch_id,t1.folder_id,
                              t1.grade9_exam_no,d1.name as school_district,school_information.name as exam_school, households.id as hh_id, households.hhh_fname, households.hhh_lname, households.hhh_nrc_number, households.number_in_cwac, districts.name as district_name'))
                ->where('t1.batch_id', $batch_id)
                ->where('t1.beneficiary_status', 8);
            if (isset($option_id) && $option_id != '') {
                if ($option_id == 1) {
                    $qry->whereIn('t1.school_placement_status', array(1, 2));
                } else if ($option_id == 2) {
                    $qry->where(function ($query) {
                        $query->whereNotIn('t1.school_placement_status', array(1, 2))
                            ->orWhereNull('t1.school_placement_status');
                    });
                }
            }
			
            if (isset($cwac_id) && $cwac_id != '') {
                $qry->where('t1.cwac_id', $cwac_id);
            }
            if (isset($school_id) && $school_id != '') {
                $qry->where('t1.exam_school_id', $school_id);
            }
            if (isset($district_id) && $district_id != '') {
                $qry->where('school_information.district_id', $district_id);
            }
            if (isset($province_id) && $province_id != '') {
                $qry->where('school_information.province_id', $province_id);
            }
            if (isset($category_id) && $category_id != '') {
                //$qry->where('t1.category', $category_id);
            }
			
            $data = $qry->get();
            $res = array(
                'success' => true,
                'message' => returnMessage($data),
                'results' => $data
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

    public
    function getChecklistsGenGirls(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $category_id = $req->input('category_id');
            $province_id = $req->input('province_id');
            $district_id = $req->input('district_id');
            $cwac_id = $req->input('cwac_id');
            $school_id = $req->input('school_id');
            $beneficiary_status_id = $req->input('beneficiary_status_id');
            $whereCat = array($category_id);
            if ($category_id == 2) {
                $whereCat = array(2, 3);
            }
            $qry = DB::table('beneficiary_information')
                ->leftJoin('households', 'beneficiary_information.household_id', '=', 'households.id')
                ->leftJoin('cwac', 'beneficiary_information.cwac_id', '=', 'cwac.id')
                ->leftJoin('school_information', 'beneficiary_information.school_id', '=', 'school_information.id')
                ->leftJoin('districts', 'beneficiary_information.district_id', '=', 'districts.id')
                ->leftJoin('provinces', function ($join) use ($province_id) {
                    $join->on('provinces.id', '=', 'districts.province_id');
                })
                ->select('beneficiary_information.*', 'cwac.name as cwac_name', 'school_information.name as school_name', 'households.hhh_fname', 'households.hhh_lname', 'districts.name as district_name')
                ->where('beneficiary_information.batch_id', $batch_id)
                ->whereIn('beneficiary_information.category', $whereCat);
            if (validateisNumeric($beneficiary_status_id)) {
                $qry->where('beneficiary_information.beneficiary_status', $beneficiary_status_id);
            }
            if ($category_id == 1) {
                if ($province_id != '') {
                    $qry->where('provinces.id', $province_id);
                }
                if ($district_id != '') {
                    $qry->where('beneficiary_information.district_id', $district_id);
                }
            } else {
                if ($province_id != '') {
                    $qry->where('school_information.province_id', $province_id);
                }
                if ($district_id != '') {
                    $qry->where('school_information.district_id', $district_id);
                }
            }

            if ($cwac_id != '') {
                $qry->where('beneficiary_information.cwac_id', $cwac_id);
            }
            if ($school_id != '') {
                $qry->where('beneficiary_information.school_id', $school_id);
            }
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $data = decryptArray($data);
            $res = array(
                'success' => true,
                'message' => 'Records fetched successfully!!',
                'results' => $data
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

    function getDistrictsOnProvinceMultiSelect(Request $req)
    {
        $province_ids = $req->input('province_id');
        $table = $req->input('table');
        $qry = DB::table($table);
        if (isset($province_ids) && count(json_decode($province_ids)) > 0) {
            $wherein = json_decode($province_ids);
            $qry->whereIn('province_id', $wherein);
        }
        $data = $qry->get();
        $data = convertStdClassObjToArray($data);
        $data = decryptArray($data);
        $res = array(
            'results' => $data
        );
        return response()->json($res);
    }

    function getCwacsOnDistrictMultiSelect(Request $req)
    {
        $provinces_ids = $req->input('province_id');
        $districts_ids = $req->input('district_id');
        $table = $req->input('table');
        $qry = DB::table($table);
        if (isset($provinces_ids) && count(json_decode($provinces_ids)) > 0) {
            $whereIn = json_decode($provinces_ids);
            $qry->whereIn('province_id', $whereIn);
        }
        if (isset($districts_ids) && count(json_decode($districts_ids)) > 0) {
            $wherein = json_decode($districts_ids);
            $qry->whereIn('district_id', $wherein);
        }
        $data = $qry->get();
        $data = convertStdClassObjToArray($data);
        $data = decryptArray($data);
        $res = array(
            'results' => $data
        );
        return response()->json($res);
    }

    function getSchoolsOnDistrictMultiSelect(Request $req)
    {
        $provinces_ids = $req->input('province_id');
        $districts_ids = $req->input('district_id');
        $table = $req->input('table');
        $qry = DB::table($table);
        if (isset($provinces_ids) && count(json_decode($provinces_ids)) > 0) {
            $whereIn = json_decode($provinces_ids);
            $qry->whereIn('province_id', $whereIn);
        }
        if (isset($districts_ids) && count(json_decode($districts_ids)) > 0) {
            $wherein = json_decode($districts_ids);
            $qry->whereIn('district_id', $wherein);
        }
        $data = $qry->get();
        //$data = convertStdClassObjToArray($data);
        //$data = decryptArray($data);
        $res = array(
            'results' => $data
        );
        return response()->json($res);
    }

    function getSchoolsWithBeneficiariesOnDistrictMultiSelect(Request $req)
    {
        $provinces_ids = $req->input('province_id');
        $districts_ids = $req->input('district_id');
        $table = $req->input('table');
        try {
            $qry = DB::table($table . ' as t1')
                ->join('beneficiary_information as t2', 't1.id', '=', 't2.school_id')
                ->where('t2.enrollment_status', 1);
            if (isset($provinces_ids) && count(json_decode($provinces_ids)) > 0) {
                $whereIn = json_decode($provinces_ids);
                $qry->whereIn('t1.province_id', $whereIn);
            }
            if (isset($districts_ids) && count(json_decode($districts_ids)) > 0) {
                $wherein = json_decode($districts_ids);
                $qry->whereIn('t1.district_id', $wherein);
            }
            $qry->select('t1.id', 't1.name', 't1.code')
                ->groupBy('t1.id');
            $data = $qry->get();
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

    function updateGirlInfo(Request $req)
    {
        $ben_id = $req->input('id');
        $master_id = $req->input('master_id');
        $hh_id = $req->input('hh_id');
        $batch_id = $req->input('batch_id');
        $hhh_nrc = $req->input('hhh_nrc_number');
        $girl_fname = $req->input('first_name');
        $girl_lname = $req->input('last_name');
        $girl_dob = $req->input('dob');
        $verified_dob = $req->input('verified_dob');
        $additionalValues = $req->input('values');
        $additionalValues = json_decode($additionalValues);
        
        $girl_info = array(
            'first_name' => aes_encrypt($girl_fname),
            'last_name' => aes_encrypt($girl_lname),
            'dob' => converter11($girl_dob),
            'verified_dob' => converter11($verified_dob),
            'district_id' => $req->input('district_id'),
            'constituency_id' => $req->input('constituency_id'),
            'ward_id' => $req->input('ward_id'),
            'acc_id' => $req->input('acc_id'),
            'cwac_id' => $req->input('cwac_id'),
            'school_id' => $req->input('school_id'),
            'exam_school_id' => $req->input('school_id')
        );
        $masterParams = array(
            'hhh_nrc' => $hhh_nrc,
            'girl_fname' => $girl_fname,
            'girl_lname' => $girl_lname,
            'girl_dob' => $girl_dob,
            'verified_dob' => $verified_dob
        );
        $hh_info = array(
            'number_in_cwac' => $req->input('number_in_cwac'),
            'hhh_nrc_number' => $hhh_nrc,
            'hhh_fname' => $req->input('hhh_fname'),
            'hhh_lname' => $req->input('hhh_lname')
        );

        DB::beginTransaction();
        try {
            logUpdateBeneficiaryInfo($ben_id, $masterParams);
            
            DB::table('beneficiary_information')
                ->where(array('id' => $ben_id))
                ->update($girl_info);
                
            DB::table('households')
                ->where(array('id' => $hh_id))
                ->update($hh_info);

            if (count($additionalValues) > 0) {
                foreach ($additionalValues as $additionalValue) {
                    $where = array(
                        'main_temp_id' => $master_id,
                        'field_id' => $additionalValue->field_id
                    );
                    $insertValues = array(
                        'main_temp_id' => $master_id,
                        'field_id' => $additionalValue->field_id,
                        'batch_id' => $batch_id,
                        'value' => $additionalValue->value
                    );
                    if (recordExists('temp_additional_fields_values', $where)) {
                        DB::table('temp_additional_fields_values')
                            ->where($where)
                            ->update($insertValues);
                    } else {
                        DB::table('temp_additional_fields_values')
                            ->insert($insertValues);
                    }
                }
            }
            DB::commit();
            $res = array(
                'success' => true,
                'message' => 'Details were updated successfully!!'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        } catch (\Throwable $t) {
            DB::rollBack();
            $res = array(
                'success' => false,
                'message' => $t->getMessage()
            );
        }
        return response()->json($res);
    }

    public function submitForAnalysis(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $category_id = $req->input('category_id');
            $where = array(
                'batch_id' => $batch_id,
                //'category' => $category_id,
                'beneficiary_status' => 2
            );
            if ($category_id == 1) {
                DB::table('beneficiary_information')
                    ->where($where)
                    ->where('initial_category', 1)
                    ->update(array('beneficiary_status' => 3));
            } else if ($category_id == 2) {
                DB::table('beneficiary_information')
                    ->where($where)
                    ->whereIn('initial_category', array(2, 3))
                    ->update(array('beneficiary_status' => 3));
            }
            $res = array(
                'success' => true,
                'message' => 'Beneficiaries submitted successfully!!'
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

    public function submitSingleForNextStageAfterAnalysisInitial(Request $req)
    {
        $girl_id = $req->input('girl_id');
        $where = array(
            'id' => $girl_id
        );
        try {
            $girl_info = DB::table('beneficiary_information')
                ->where($where)
                ->first();
            if (is_null($girl_info)) {
                $res = array(
                    'success' => false,
                    'message' => 'Problem encountered while fetching girl information!!'
                );
                return response()->json($res);
            } else {
                $category = $girl_info->category;
                $recommendation = $girl_info->verification_recommendation;
                $current_school_grade = $girl_info->current_school_grade;
                if ($category == 1 && $recommendation == 1) {//school matching process   
                    $qry = DB::table('checklist_items')
                    ->select('checklist_items.*', 'beneficiary_verification_report.id as report_id', 
                        'beneficiary_verification_report.response', 'beneficiary_verification_report.remark')
                    ->leftJoin('beneficiary_verification_report', function ($join) use ($girl_id) {
                        $join->on('checklist_items.id', '=', 'beneficiary_verification_report.checklist_item_id')
                            ->on('beneficiary_verification_report.beneficiary_id', '=', DB::raw($girl_id));
                    })
                    ->whereIn('checklist_items.id',[78,82]);
                    $response_qry = $qry->get();
                    $counter = 0;
                    foreach ($response_qry as $each_response) {
                        $log_data[$counter] = array(
                            'response' => $each_response
                        );
                        $counter++;
                    }
                    $in_school = $log_data[0]['response']->response ? $log_data[0]['response']->response : 0;
                    $school_name = $log_data[1]['response']->response ? $log_data[1]['response']->response : 0;
                    if ($current_school_grade == 7 || $current_school_grade == '7' || $current_school_grade === 7) {//school placement
                        DB::table('beneficiary_information')
                            ->where($where)
                            ->update(array('beneficiary_status' => 8));
                    } else if($in_school > 0 && $school_name > 0) {//letter generation
                        DB::table('beneficiary_information')
                            ->where($where)
                            ->update(array('beneficiary_status' => 4, 'enrollment_date' => Carbon::now(), 'enrollment_status' => 1));
                    } else {//school matching
                        DB::table('beneficiary_information')
                            ->where($where)
                            ->update(array('beneficiary_status' => 5));                        
                    }
                } else if ($category == 2 && $recommendation == 1) {//letter generation
                    DB::table('beneficiary_information')
                        ->where($where)
                        ->update(array('beneficiary_status' => 4, 'enrollment_date' => Carbon::now(), 'enrollment_status' => 1));
                } else if ($category == 3 && $recommendation == 1) {//school placement
                    DB::table('beneficiary_information')
                        ->where($where)
                        ->update(array('beneficiary_status' => 8));
                } else {//follow ups
                    DB::table('beneficiary_information')
                        ->where($where)
                        ->update(array('beneficiary_status' => 6));
                }
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
        }
        return response()->json($res);
    }

    public function submitSingleForNextStageAfterAnalysis(Request $req)
    {//frank new
        $girl_id = $req->input('girl_id');
        $where = array(
            'id' => $girl_id
        );
        try {
            $girl_info = DB::table('beneficiary_information')
                ->where($where)
                ->first();
            if (is_null($girl_info)) {
                $res = array(
                    'success' => false,
                    'message' => 'Problem encountered while fetching girl information!!'
                );
                return response()->json($res);
            } else {
                $category = $girl_info->category;
                $recommendation = $girl_info->verification_recommendation;
                $current_school_grade = $girl_info->current_school_grade;
                if ($category == 1 && $recommendation == 1) {//school matching process   
                    $qry = DB::table('checklist_items')
                    ->select('checklist_items.*', 'beneficiary_verification_report.id as report_id', 
                        'beneficiary_verification_report.response', 'beneficiary_verification_report.remark')
                    ->leftJoin('beneficiary_verification_report', function ($join) use ($girl_id) {
                        $join->on('checklist_items.id', '=', 'beneficiary_verification_report.checklist_item_id')
                            ->on('beneficiary_verification_report.beneficiary_id', '=', DB::raw($girl_id));
                    })->whereIn('checklist_items.id',[78,82]);

                    $response_qry = $qry->get();
                    $counter = 0;
                    foreach ($response_qry as $each_response) {
                        $log_data[$counter] = array(
                            'response' => $each_response
                        );
                        $counter++;
                    }
                    $in_school = $log_data[0]['response']->response ? $log_data[0]['response']->response : 0;
                    $school_name = $log_data[1]['response']->response ? $log_data[1]['response']->response : 0;
                    if ($current_school_grade == 7 || $current_school_grade == '7' || $current_school_grade === 7) {//school placement
                        DB::table('beneficiary_information')
                            ->where($where)
                            ->update(array('beneficiary_status' => 8));
                    } else if($in_school > 0 && $school_name > 0) {//letter generation
                        DB::table('beneficiary_information')
                            ->where($where)
                            ->update(array('beneficiary_status' => 4, 'enrollment_date' => Carbon::now(), 'enrollment_status' => 1));
                    } else {//school matching
                        DB::table('beneficiary_information')
                            ->where($where)
                            ->update(array('beneficiary_status' => 5));                        
                    }
                } else if ($category == 2 && $recommendation == 1) {//In School
                    if ($current_school_grade == 7 || $current_school_grade == '7' || $current_school_grade === 7) {//school placement
                        DB::table('beneficiary_information')->where($where)
                            ->update(array('beneficiary_status' => 8));  
                    } else if($recommendation == 2 || $recommendation == 3) {//follow ups                  
                        DB::table('beneficiary_information')->where($where)
                            ->update(array('beneficiary_status' => 6));
                    } else {//Letter Gen
                        DB::table('beneficiary_information')->where($where)
                            ->update(array('beneficiary_status' => 4, 'enrollment_date' => Carbon::now(), 'enrollment_status' => 1));
                    }
                }
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
        }
        return response()->json($res);
    }
    
	// public function submitForNextStageAfterAnalysis(Request $req)
 //    {
 //        $res = array();
 //        DB::transaction(function () use (&$res, $req) {
 //            try {
 //                $batch_id = $req->input('batch_id');
 //                $category_id = $req->input('category_id');
 //                $payment_eligible = getBatchPaymentEligibility($batch_id);
 //                if ($category_id == 1) {//Out of school
 //                    //update the beneficiary status..to school matching
 //                    DB::table('beneficiary_information')
 //                        ->where(array('batch_id' => $batch_id, 
 //                        'beneficiary_status' => 3, 'category' => 1))
 //                        ->where('verification_recommendation', 1)
 //                        ->where('verification_type', 1)
 //                        ->where(function ($query) {
 //                            $query->where('skip_matching', 0)
 //                                ->orWhereNull('skip_matching');
 //                        })
 //                        ->update(array('beneficiary_status' => 5));//to school matching
 //                    //update the beneficiary status..to school placement..came up because of combined checklist
 //                    //2021 .. only grade 7s go to school placement, grade 9s nope
 //                    DB::table('beneficiary_information')
 //                        ->where(array('batch_id' => $batch_id, 
 //                        'beneficiary_status' => 3, 'category' => 1))
 //                        ->where('verification_recommendation', 1)
 //                        ->where('skip_matching', 1)
 //                        //->whereIn('current_school_grade', array(7, 9))//2021
 //                        // ->whereIn('current_school_grade', array(7))//2021
 //                        ->where('current_school_grade', 7)//2022
 //                        ->where('verification_type', 1)
 //                        ->update(array('beneficiary_status' => 8));//school placement
 //                    //update the beneficiary status..to letter generation
 //                    //log beneficiary grade transitioning
 //                    //1. Field/Normal Verification
 //                    $qry = DB::table('beneficiary_information')
 //                        ->where(array('batch_id' => $batch_id, 'beneficiary_status' => 3, 'category' => 1))
 //                        ->where('verification_recommendation', 1)
 //                        ->where('verification_type', 1)
 //                        ->where('skip_matching', 1)
 //                        //->whereIn('current_school_grade', array(8, 10, 11, 12))//2021
 //                        ->whereIn('current_school_grade', array(8, 9, 10, 11, 12))//2021
 //                        ->select(DB::raw('id as girl_id, school_id, current_school_grade as grade, YEAR(CURDATE()) as year'));
 //                    $data = $qry->get();
 //                    $data = convertStdClassObjToArray($data);
 //                    DB::table('beneficiary_grade_logs')->insert($data);
 //                    //2. School Assembly Verification
 //                    $qry2 = DB::table('beneficiary_information')
 //                        ->where(array('batch_id' => $batch_id, 'beneficiary_status' => 3, 'category' => 1))
 //                        ->where('verification_recommendation', 1)
 //                        ->where('verification_type', 2)
 //                        ->select(DB::raw('id as girl_id, school_id, current_school_grade as grade, YEAR(CURDATE()) as year'));
 //                    $data2 = $qry2->get();
 //                    $data2 = convertStdClassObjToArray($data2);
 //                    DB::table('beneficiary_grade_logs')->insert($data2);
 //                    //end log

 //                    //1. Field/Normal Verification
 //                    DB::table('beneficiary_information')
 //                        ->where(array('batch_id' => $batch_id,'beneficiary_status' => 3, 'category' => 1))
 //                        ->where('verification_recommendation', 1)
 //                        ->where('verification_type', 1)
 //                        ->where('skip_matching', 1)
 //                        //->whereIn('current_school_grade', array(8, 10, 11, 12))//2021
 //                        ->whereIn('highest_grade', array(8, 9, 10, 11, 12))//2021
 //                        /* ->where(function ($query) {
 //                            $query->where('skip_matching', 0)->orWhereNull('skip_matching');
 //                        }) */
 //                        ->update(
 //                            array( //letter generation
 //                                'beneficiary_status' => 4,
 //                                'enrollment_date' => Carbon::now(), 
 //                                'enrollment_status' => 1,
 //                                'payment_eligible' => $payment_eligible
 //                            )
 //                        );

 //                    //2. School Assembly Verification
 //                    DB::table('beneficiary_information')
 //                        ->where(array('batch_id' => $batch_id,'beneficiary_status' => 3,'category' => 1))
 //                        ->where('verification_recommendation', 1)
 //                        ->where('verification_type', 2)
 //                        ->update(
 //                            array(
 //                                'beneficiary_status' => 4, //enrolled
 //                                'enrollment_date' => Carbon::now(), 
 //                                'enrollment_status' => 1, 
 //                                'payment_eligible' => $payment_eligible
 //                                )
 //                            );
 //                    //the rest move to follow up
 //                    DB::table('beneficiary_information')
 //                        ->where(array('batch_id' => $batch_id, 'beneficiary_status' => 3, 'category' => 1))
 //                        ->whereIn('verification_recommendation', array(2, 3))//unrecommended/not found
 //                        ->update(array('beneficiary_status' => 6));//send to follow up
 //                } else if ($category_id == 2) {// In school
 //                    //update the beneficiary status
 //                    //todo: A. IN SCHOOL
 //                    //log beneficiary grade transitioning
 //                    //1. Field/Normal Verification
 //                    $qry = DB::table('beneficiary_information')
 //                        ->where(array('batch_id' => $batch_id, 'beneficiary_status' => 3))
 //                        ->whereIn('category', array(2, 3))
 //                        ->where('verification_recommendation', 1)
 //                        ->where('verification_type', 1)
 //                        //->whereNotIn('current_school_grade', array(7, 9))//2021
 //                        ->whereNotIn('current_school_grade', array(7))//2021
 //                        ->select(DB::raw('id as girl_id, school_id, current_school_grade as grade, YEAR(CURDATE()) as year'));
 //                    $data = $qry->get();
 //                    $data = convertStdClassObjToArray($data);
 //                    DB::table('beneficiary_grade_logs')->insert($data);
 //                    //2. School Assembly Verification
 //                    $qry2 = DB::table('beneficiary_information')
 //                        ->where(array('batch_id' => $batch_id, 'beneficiary_status' => 3))
 //                        ->whereIn('category', array(2, 3))
 //                        ->where('verification_recommendation', 1)
 //                        ->where('verification_type', 2)
 //                        ->select(DB::raw('id as girl_id, school_id, current_school_grade as grade, YEAR(CURDATE()) as year'));
 //                    $data2 = $qry2->get();
 //                    $data2 = convertStdClassObjToArray($data2);
 //                    DB::table('beneficiary_grade_logs')->insert($data2);
 //                    //end log

 //                    //1. Field/Normal Verification
 //                    DB::table('beneficiary_information')
 //                        ->where(array('batch_id' => $batch_id, 'beneficiary_status' => 3))
 //                        ->whereIn('category', array(2, 3))
 //                        ->where('verification_recommendation', 1)
 //                        ->where('verification_type', 1)
 //                        //->whereNotIn('current_school_grade', array(7, 9))//2021
 //                        ->whereNotIn('current_school_grade', array(7))//2021
 //                        ->update(array('beneficiary_status' => 4, 'enrollment_date' => Carbon::now(), 'enrollment_status' => 1, 'payment_eligible' => $payment_eligible));
 //                    //2. School Assembly Verification
 //                    DB::table('beneficiary_information')
 //                        ->where(array('batch_id' => $batch_id, 'beneficiary_status' => 3))
 //                        ->whereIn('category', array(2, 3))
 //                        ->where('verification_recommendation', 1)
 //                        ->where('verification_type', 2)
 //                        ->update(array('beneficiary_status' => 4, 'enrollment_date' => Carbon::now(), 'enrollment_status' => 1, 'payment_eligible' => $payment_eligible));
 //                    //todo: B. EXAM CLASSES
 //                    DB::table('beneficiary_information')
 //                        ->where(array('batch_id' => $batch_id, 'beneficiary_status' => 3))
 //                        ->whereIn('category', array(2, 3))
 //                        ->where('verification_recommendation', 1)
 //                        ->where('verification_type', 1)
 //                        //->whereIn('current_school_grade', array(7, 9))//2021
 //                        ->whereIn('current_school_grade', array(7))//2021
 //                        ->update(array('beneficiary_status' => 8,));
 //                    //the rest move to follow up
 //                    DB::table('beneficiary_information')//verification_rec
 //                        ->where(array('batch_id' => $batch_id, 'beneficiary_status' => 3))
 //                        ->whereIn('category', array(2, 3))
 //                        ->whereIn('verification_recommendation', array(2, 3))
 //                        ->update(array('beneficiary_status' => 6));
 //                }
                
	// 			$res = array(
 //                    'success' => true,
 //                    'message' => 'Beneficiaries submitted successfully!!'
 //                );
 //            } catch (\Exception $exception) {
 //                $res = array(
 //                    'success' => false,
 //                    'message' => $exception->getMessage()
 //                );
 //            } catch (\Throwable $throwable) {
 //                $res = array(
 //                    'success' => false,
 //                    'message' => $throwable->getMessage()
 //                );
 //            }
 //        }, 5);
 //        return response()->json($res);
 //    }

    
    public function submitForNextStageAfterAnalysis(Request $req)
    {
        $res = array();
        DB::transaction(function () use (&$res, $req) {
            try {
                $batch_id = $req->input('batch_id');
                $category_id = $req->input('category_id');
                $payment_eligible = getBatchPaymentEligibility($batch_id);
                if ($category_id == 1) {//Out of school
                    //update the beneficiary status..to school matching
                    DB::table('beneficiary_information')
                        ->where(array('batch_id' => $batch_id, 
                        'beneficiary_status' => 3, 'category' => 1))
                        ->where('verification_recommendation', 1)
                        ->where('verification_type', 1)
                        ->where(function ($query) {
                            $query->where('skip_matching', 0)
                                ->orWhereNull('skip_matching');
                        })
                        ->update(array('beneficiary_status' => 5));//to school matching
                    //update the beneficiary status..to school placement..came up because of combined checklist
                    //2021 .. only grade 7s go to school placement, grade 9s nope
                    DB::table('beneficiary_information')
                        ->where(array('batch_id' => $batch_id, 
                        'beneficiary_status' => 3, 'category' => 1))
                        ->where('verification_recommendation', 1)
                        ->where('skip_matching', 1)
                        //->whereIn('current_school_grade', array(7, 9))//2021
                        // ->whereIn('current_school_grade', array(7))//2021
                        ->where('current_school_grade', 7)//2022
                        ->where('verification_type', 1)
                        ->update(array('beneficiary_status' => 8));//school placement
                    //update the beneficiary status..to letter generation
                    //log beneficiary grade transitioning
                    //1. Field/Normal Verification
                    $qry = DB::table('beneficiary_information')
                        ->where(array('batch_id' => $batch_id, 'beneficiary_status' => 3, 'category' => 1))
                        ->where('verification_recommendation', 1)
                        ->where('verification_type', 1)
                        ->where('skip_matching', 1)
                        //->whereIn('current_school_grade', array(8, 10, 11, 12))//2021
                        ->whereIn('current_school_grade', array(8, 9, 10, 11, 12))//2021
                        ->select(DB::raw('id as girl_id, school_id, current_school_grade as grade, YEAR(CURDATE()) as year'));
                    $data = $qry->get();
                    $data = convertStdClassObjToArray($data);
                    // DB::table('beneficiary_grade_logs')->insert($data);
                    $this->insertLargeDataSet('beneficiary_grade_logs', $data);
                    //2. School Assembly Verification
                    $qry2 = DB::table('beneficiary_information')
                        ->where(array('batch_id' => $batch_id, 'beneficiary_status' => 3, 'category' => 1))
                        ->where('verification_recommendation', 1)
                        ->where('verification_type', 2)
                        ->select(DB::raw('id as girl_id, school_id, current_school_grade as grade, YEAR(CURDATE()) as year'));
                    $data2 = $qry2->get();
                    $data2 = convertStdClassObjToArray($data2);
                    // DB::table('beneficiary_grade_logs')->insert($data2);
                    $this->insertLargeDataSet('beneficiary_grade_logs', $data2);
                    //end log

                    //1. Field/Normal Verification
                    DB::table('beneficiary_information')
                        ->where(array('batch_id' => $batch_id,'beneficiary_status' => 3, 'category' => 1))
                        ->where('verification_recommendation', 1)
                        ->where('verification_type', 1)
                        ->where('skip_matching', 1)
                        //->whereIn('current_school_grade', array(8, 10, 11, 12))//2021
                        ->whereIn('highest_grade', array(8, 9, 10, 11, 12))//2021
                        /* ->where(function ($query) {
                            $query->where('skip_matching', 0)->orWhereNull('skip_matching');
                        }) */
                        ->update(
                            array( //letter generation
                                'beneficiary_status' => 4,
                                'enrollment_date' => Carbon::now(), 
                                'enrollment_status' => 1,
                                'payment_eligible' => $payment_eligible
                            )
                        );

                    //2. School Assembly Verification
                    DB::table('beneficiary_information')
                        ->where(array('batch_id' => $batch_id,'beneficiary_status' => 3,'category' => 1))
                        ->where('verification_recommendation', 1)
                        ->where('verification_type', 2)
                        ->update(
                            array(
                                'beneficiary_status' => 4, //enrolled
                                'enrollment_date' => Carbon::now(), 
                                'enrollment_status' => 1, 
                                'payment_eligible' => $payment_eligible
                                )
                            );
                    //the rest move to follow up
                    DB::table('beneficiary_information')
                        ->where(array('batch_id' => $batch_id, 'beneficiary_status' => 3, 'category' => 1))
                        ->whereIn('verification_recommendation', array(2, 3))//unrecommended/not found
                        ->update(array('beneficiary_status' => 6));//send to follow up
                } else if ($category_id == 2) {// In school
                    //update the beneficiary status
                    //todo: A. IN SCHOOL
                    //log beneficiary grade transitioning
                    //1. Field/Normal Verification
                    $qry = DB::table('beneficiary_information')
                        ->where(array('batch_id' => $batch_id, 'beneficiary_status' => 3))
                        ->whereIn('category', array(2, 3))
                        ->where('verification_recommendation', 1)
                        ->where('verification_type', 1)
                        //->whereNotIn('current_school_grade', array(7, 9))//2021
                        ->whereNotIn('current_school_grade', array(7))//2021
                        ->select(DB::raw('id as girl_id, school_id, current_school_grade as grade, YEAR(CURDATE()) as year'));
                    $data = $qry->get();
                    $data = convertStdClassObjToArray($data);
                    // DB::table('beneficiary_grade_logs')->insert($data);
                    $this->insertLargeDataSet('beneficiary_grade_logs', $data);
                    //2. School Assembly Verification
                    $qry2 = DB::table('beneficiary_information')
                        ->where(array('batch_id' => $batch_id, 'beneficiary_status' => 3))
                        ->whereIn('category', array(2, 3))
                        ->where('verification_recommendation', 1)
                        ->where('verification_type', 2)
                        ->select(DB::raw('id as girl_id, school_id, current_school_grade as grade, YEAR(CURDATE()) as year'));
                    $data2 = $qry2->get();
                    $data2 = convertStdClassObjToArray($data2);
                    // DB::table('beneficiary_grade_logs')->insert($data2);
                    $this->insertLargeDataSet('beneficiary_grade_logs', $data2);
                    //end log

                    //1. Field/Normal Verification
                    DB::table('beneficiary_information')
                        ->where(array('batch_id' => $batch_id, 'beneficiary_status' => 3))
                        ->whereIn('category', array(2, 3))
                        ->where('verification_recommendation', 1)
                        ->where('verification_type', 1)
                        //->whereNotIn('current_school_grade', array(7, 9))//2021
                        ->whereNotIn('current_school_grade', array(7))//2021
                        ->update(array('beneficiary_status' => 4, 'enrollment_date' => Carbon::now(), 'enrollment_status' => 1, 'payment_eligible' => $payment_eligible));
                    //2. School Assembly Verification
                    DB::table('beneficiary_information')
                        ->where(array('batch_id' => $batch_id, 'beneficiary_status' => 3))
                        ->whereIn('category', array(2, 3))
                        ->where('verification_recommendation', 1)
                        ->where('verification_type', 2)
                        ->update(array('beneficiary_status' => 4, 'enrollment_date' => Carbon::now(), 'enrollment_status' => 1, 'payment_eligible' => $payment_eligible));
                    //todo: B. EXAM CLASSES
                    DB::table('beneficiary_information')
                        ->where(array('batch_id' => $batch_id, 'beneficiary_status' => 3))
                        ->whereIn('category', array(2, 3))
                        ->where('verification_recommendation', 1)
                        ->where('verification_type', 1)
                        //->whereIn('current_school_grade', array(7, 9))//2021
                        ->whereIn('current_school_grade', array(7))//2021
                        ->update(array('beneficiary_status' => 8,));
                    //the rest move to follow up
                    DB::table('beneficiary_information')//verification_rec
                        ->where(array('batch_id' => $batch_id, 'beneficiary_status' => 3))
                        ->whereIn('category', array(2, 3))
                        ->whereIn('verification_recommendation', array(2, 3))
                        ->update(array('beneficiary_status' => 6));
                }
                
                $res = array(
                    'success' => true,
                    'message' => 'Beneficiaries submitted successfully!!'
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
        return response()->json($res);
    }
    
    public function insertLargeDataSet($table_name, $data_to_save)
    {        
        $number_of_records=count($data_to_save);
        $limit=1500;
        if($number_of_records>$limit && $number_of_records>0)
        {
            $total_loop=ceil($number_of_records/$limit);
            $start_index=0;
            $end_index=$limit;
            for($i=1;$i<=$total_loop;$i++)
            {
                $results_to_insert=array();
                foreach($data_to_save as $key=>$result)
                {
                    if($key>=$start_index && $key<=$end_index)
                    {
                        $results_to_insert[]=$result;
                    }
                }
                DB::table($table_name)->insert($results_to_insert);
                $results_to_insert=array();
                if($i!=$total_loop-1){
                    $start_index=$end_index+1;
                    $end_index=$start_index+$limit;
                }else{
                    $start_index=$end_index+1;
                    $end_index=($number_of_records-1);
                }
            }
        }else{
            DB::table($table_name)->insert($data_to_save);
        }
    }

    public function submitForResultsEntry(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $category_id = $req->input('category_id');
        $where = array(
            'batch_id' => $batch_id,
            'category' => $category_id,
            'beneficiary_status' => 2
        );
        //todo: for girls who were verified as being in examination classes move them to results entry stage, the rest are submitted for follow ups
        try {
            DB::table('beneficiary_information')
                ->where($where)
                ->where('verification_recommendation', 1)
                ->update(array('beneficiary_status' => 8));
            DB::table('beneficiary_information')
                ->where($where)
                ->whereIn('verification_recommendation', array(2, 3))
                ->update(array('beneficiary_status' => 6));
            $res = array(
                'success' => true,
                'message' => 'Beneficiaries submitted successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function submitForLettersGenAfterPlacement(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $category_id = $req->input('category_id');
        $where = array(
            'batch_id' => $batch_id,
            //'category' => $category_id,
            'beneficiary_status' => 8
        );
        try {
            $payment_eligible = getBatchPaymentEligibility($batch_id);
            //log beneficiary grade transitioning
            $qry = DB::table('beneficiary_information')
                ->where($where)
                ->where('school_placement_status', 1)
                ->select(DB::raw('id as girl_id, school_id, current_school_grade as grade, YEAR(CURDATE()) as year'));
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            DB::table('beneficiary_grade_logs')->insert($data);
            DB::table('beneficiary_information')
                ->where($where)
                ->where('school_placement_status', 1)
                ->update(array('beneficiary_status' => 4, 'enrollment_date' => Carbon::now(), 'enrollment_status' => 1, 'payment_eligible' => $payment_eligible));
            //Failed cohorts
            DB::table('beneficiary_information')
                ->where($where)
                ->where('school_placement_status', 2)
                ->update(array('beneficiary_status' => 6));
            $res = array(
                'success' => true,
                'message' => 'Beneficiaries submitted successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function submitForLettersGenAfterMatching(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $category_id = $req->input('category_id');
        $where = array(
            'batch_id' => $batch_id,
            'category' => $category_id,
            'beneficiary_status' => 5,
            'school_matching_status' => 1
        );
        $where2 = array(
            'batch_id' => $batch_id,
            'category' => $category_id,
            'beneficiary_status' => 5,
            'school_matching_status' => 2
        );
        try {
            $payment_eligible = getBatchPaymentEligibility($batch_id);
            //log beneficiary grade transitioning
            $qry = DB::table('beneficiary_information')
                ->where($where)
                ->select(DB::raw('id as girl_id, school_id, current_school_grade as grade, YEAR(CURDATE()) as year'));
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            DB::table('beneficiary_grade_logs')->insert($data);
            //end log
            DB::table('beneficiary_information')
                ->where($where)
                ->update(array('beneficiary_status' => 4, 'enrollment_date' => Carbon::now(), 'enrollment_status' => 1, 'payment_eligible' => $payment_eligible));
            DB::table('beneficiary_information')
                ->where($where2)
                ->update(array('beneficiary_status' => 6));
            $res = array(
                'success' => true,
                'message' => 'Beneficiaries submitted successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function onSubmitExamClassesIndividualForLettersGen(Request $req)
    {
        $girl_id = $req->input('girl_id');
        $is_placed = $req->input('is_placed');
        $to_status = 4;
        $update = array(
            'beneficiary_status' => $to_status,
            'enrollment_date' => Carbon::now(),
            'enrollment_status' => 1
        );
        if ($is_placed == 2) {
            $to_status = 6;
            $update = array(
                'beneficiary_status' => $to_status
            );
        }
        try {
            DB::table('beneficiary_information')
                ->where('id', $girl_id)
                ->update($update);
            $res = array(
                'success' => true,
                'message' => 'Beneficiary submitted successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function onSubmitExamClassesBatchForLettersGen(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $category_id = 3;//$req->input('category_id');
        $where1 = array(
            'batch_id' => $batch_id,
            'category' => $category_id,
            'beneficiary_status' => 3,
            'school_placement_status' => 1
        );
        $where2 = array(
            'batch_id' => $batch_id,
            'category' => $category_id,
            'beneficiary_status' => 3,
            'school_placement_status' => 2
        );
        try {
            DB::table('beneficiary_information')
                ->where($where1)
                ->update(array('beneficiary_status' => 4, 'enrollment_date' => Carbon::now(), 'enrollment_status' => 1));
            DB::table('beneficiary_information')
                ->where($where2)
                ->update(array('beneficiary_status' => 6));
            $res = array(
                'success' => true,
                'message' => 'Beneficiaries submitted successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    function saveRecommendationOverrule(Request $req)
    {
        $girl_id = $req->input('girl_id');
        $recomm_id = $req->input('recomm_id');
        $reason = $req->input('reason');
        try {
            $oldRecommID = DB::table('beneficiary_information')->where('id', $girl_id)->value('verification_recommendation');
            DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => $recomm_id));
            $log_data = array(
                'girl_id' => $girl_id,
                'from_recomm' => $oldRecommID,
                'to_recomm' => $recomm_id,
                'changes_by' => \Auth::user()->id,
                'changes_on' => Carbon::now(),
                'reason' => $reason
            );
            DB::table('recomm_overrule_logs')->insert($log_data);
            $res = array(
                'success' => true,
                'message' => 'Operation executed successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    function saveRecommendationOverruleBatch(Request $req)
    {
        try {
            $recomm_id = $req->input('recomm_id');
            $oldRecommID = $req->input('from_recomm_id');
            $reason = $req->input('reason');
            $selected = $req->input('selected');
            $selected = json_decode($selected);
            $res = array();
            DB::transaction(function () use (&$res, $recomm_id, $oldRecommID, $reason, $selected) {
                $log_data = array();
                foreach ($selected as $girl_id) {
                    $log_data[] = array(
                        'girl_id' => $girl_id,
                        'from_recomm' => $oldRecommID,
                        'to_recomm' => $recomm_id,
                        'changes_by' => \Auth::user()->id,
                        'changes_on' => Carbon::now(),
                        'reason' => $reason
                    );
                }
                DB::table('beneficiary_information')
                    ->whereIn('id', $selected)
                    ->update(array('verification_recommendation' => $recomm_id));
                DB::table('recomm_overrule_logs')->insert($log_data);
                $res = array(
                    'success' => true,
                    'message' => 'Operation executed successfully!!'
                );
            }, 5);
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getBatchInclusionCriteria(Request $req)
    {
        $batch_id = $req->input('batch_id');
        try {
            $data = DB::table('batch_inclusion_criteria')->where('batch_id', $batch_id)->first();
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

    public function getInclusionCriteria(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $outOfSchCheck = 2;
        $inSchCheck = 2;
        $examClassesCheck = 2;
        try {
            $data = DB::table('batch_inclusion_criteria')->where('batch_id', $batch_id)->first();
            if (is_null($data)) {
                $res = array(
                    'success' => false,
                    'results' => array(),
                    'message' => 'No inclusion criteria found!!'
                );
                return response()->json($res);
            }
            $outOfSch = $data->out_of_school_check;
            // $outGrade4 = $data->outGradeFourCheckbox;
            // $outGrade5 = $data->outGradeFiveCheckbox;
            // $outGrade6 = $data->outGradeSixCheckbox;
            $outGrade7 = $data->outGradeSevenCheckbox;
            $outGrade8 = $data->outGradeEightCheckbox;
            $outGrade9 = $data->outGradeNineCheckbox;
            $outGrade10 = $data->outGradeTenCheckbox;
            $outGrade11 = $data->outGradeElevenCheckbox;
            $outGrade12 = $data->outGradeTwelveCheckbox;
            $willingness = $data->willingness_to_back;
            $inSch = $data->in_school_check;
            $inGrade8 = $data->inGradeEightCheckbox;
            $inGrade10 = $data->inGradeTenCheckbox;
            $inGrade11 = $data->inGradeElevenCheckbox;
            $inGrade12 = $data->inGradeTwelveCheckbox;
            $examClasses = $data->exam_classes_check;
            $examGrade7 = $data->examGradeSevenCheckbox;
            $examGrade9 = $data->examGradeNineCheckbox;
            $bursary_status = $data->bursaryRadio;
            if ($outOfSch == 'on') {
                if ($outGrade7 == true || $outGrade8 == true || $outGrade9 == true || $outGrade10 == true || $outGrade11 == true || $outGrade12 == true) {
                    $outOfSchCheck = 1;
                } else {
                    //all
                }
            }
            if ($inSch == 'on') {
                if ($inGrade8 == true || $inGrade10 == true || $inGrade11 == true || $inGrade12 == true) {
                    $inSchCheck = 1;
                } else {
                    //all
                }
            }
            if ($examClasses == 'on') {
                if ($examGrade7 == true || $examGrade9 == true) {
                    $examClassesCheck = 1;
                } else {
                    //all
                }
            }
            $results = array(
                'outOfSchCheck' => $outOfSchCheck,
                'outGradeSevenCheckbox' => $outGrade7,
                'outGradeEightCheckbox' => $outGrade8,
                'outGradeNineCheckbox' => $outGrade9,
                'outGradeTenCheckbox' => $outGrade10,
                'outGradeElevenCheckbox' => $outGrade11,
                'outGradeTwelveCheckbox' => $outGrade12,
                'willingness_to_back' => $willingness,
                'inSchCheck' => $inSchCheck,
                'inGradeEightCheckbox' => $inGrade8,
                'inGradeTenCheckbox' => $inGrade10,
                'inGradeElevenCheckbox' => $inGrade11,
                'inGradeTwelveCheckbox' => $inGrade12,
                'examClassesCheck' => $examClassesCheck,
                'examGradeSevenCheckbox' => $examGrade7,
                'examGradeNineCheckbox' => $examGrade9,
                'bursary_status' => $bursary_status
            );
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

    public function getBeneficiary(Request $req)
    {
        try {
            $girl_id = $req->input('girl_id');
            $girl_info = DB::table('beneficiary_master_info')
                ->join('batch_info', 'beneficiary_master_info.batch_id', '=', 'batch_info.id')
                ->join('batch_statuses as t3', 'batch_info.status', '=', 't3.id')
                ->select('beneficiary_master_info.*', 'batch_info.batch_no', 't3.name as batch_stage')
                ->where('beneficiary_master_info.id', $girl_id)
                ->first();
            $res = array(
                'success' => true,
                'results' => $girl_info
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

    public function getPossibleDuplicatedRecords(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $girl_id = $req->input('girl_id');
        $where = $this->returnDuplicateCheckWhere($girl_id);

        $results = DB::table('beneficiary_master_info')
            ->where('batch_id', $batch_id)
            ->where('is_duplicate', 1)
            ->where('id', '<>', $girl_id)
            ->where($where)
            ->get();
        $res = array(
            'results' => $results
        );
        return response()->json($res);
    }

    public function getMapPossibleDuplicatedRecords(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $girl_id = $req->input('girl_id');
            $where = $this->returnDuplicateCheckWhere($girl_id);
            $beneficiary_status = 4;
            $results = DB::table('beneficiary_master_info')
                ->join('beneficiary_information', 'beneficiary_master_info.id', '=', 'beneficiary_information.master_id')
                ->join('batch_info', 'beneficiary_master_info.batch_id', '=', 'batch_info.id')
                ->leftJoin('beneficiary_statuses as t4', 'beneficiary_information.beneficiary_status', '=', 't4.id')
                ->join('batch_statuses as t5', 'batch_info.status', '=', 't5.id')
                ->select('beneficiary_master_info.*', 'beneficiary_information.beneficiary_id', 'batch_info.batch_no', 't4.name as status_name', 't5.name as batch_stage')
                //->where('beneficiary_information.beneficiary_status', $beneficiary_status)
                ->where('beneficiary_master_info.batch_id', '<>', $batch_id)
                ->where('beneficiary_master_info.id', '<>', $girl_id)
                ->where($where)
                ->get();
            $results->map(function ($results) {
                $results->school_going = 'Yes';
                $results->highest_grade = $results->current_school_grade;
                return $results;
            });
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

    function returnDuplicateCheckWhere($girl_id)
    {
        $duplicates_params = DB::table('beneficiary_duplicates_setup')->select('dataindex')->get();
        $duplicates_params = convertAssArrayToSimpleArray(convertStdClassObjToArray($duplicates_params), 'dataindex');
        $duplicates_params = implode(',', $duplicates_params);
        $where = DB::table('beneficiary_master_info')->where('id', $girl_id)->selectRaw($duplicates_params)->get();
        $where = convertStdClassObjToArray($where);
        return $where[0];
    }

    function getLetterGenDetailedSummary(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $print_filter = $req->input('print_filter');
        $sub_category = $req->input('sub_category');
        $province_id = $req->input('province_id');
        $verification_type = $req->input('verification_type');
        $qry = DB::table('school_information')
            ->join('districts', 'school_information.district_id', '=', 'districts.id')
            ->join('beneficiary_information', function ($join) use ($batch_id) {
                $join->on('beneficiary_information.school_id', '=', 'school_information.id')
                    ->where('beneficiary_information.beneficiary_status', 4)
                    ->where('beneficiary_information.enrollment_status', 1)
                    ->where('batch_id', $batch_id);
            })
            ->select(DB::raw('school_information.id,school_information.district_id,school_information.name,districts.name as district_name,SUM(IF(category = 1, 1, 0)) AS out_of_school_girls,SUM(IF(category IN (2,3), 1, 0)) AS continuing_girls,SUM(IF(category = 3, 1, 0)) AS exam_girls'));
        getBeneficiarySchMatchingPlacementDetails($qry, $sub_category, 'beneficiary_information');
        if (validateisNumeric($province_id)) {
            $qry->where('school_information.province_id', $province_id);
        }
        if (validateisNumeric($verification_type)) {
            $qry->where('beneficiary_information.verification_type', $verification_type);
        }
        if (isset($print_filter) && $print_filter != '') {
            if ($print_filter == 1) {
                $qry->where('beneficiary_information.letter_printed', 1);
            } else if ($print_filter == 2) {
                $qry->where('beneficiary_information.letter_printed', '<>', 1)
                    ->orWhere(function ($query) {
                        $query->whereNull('beneficiary_information.letter_printed');
                    });
            }
        }
        $qry->groupBy('school_information.id');
        $data = $qry->get();
        $res = array(
            'results' => $data
        );
        return response()->json($res);
    }

    public function getUnresponsiveGirlsSummary(Request $req)
    {
        $yes_no = $req->input('yes_no');
        $letter_printed = $req->input('letter_printed');
        try {
            $qry = DB::table('beneficiary_information')
                ->join('unresponsive_cohorts', 'beneficiary_information.id', '=', 'unresponsive_cohorts.girl_id')
                ->join('school_information', 'beneficiary_information.school_id', '=', 'school_information.id')
                ->join('districts', 'school_information.district_id', '=', 'districts.id')
                ->select(DB::raw('school_information.id,school_information.district_id,school_information.name,
                                  districts.name as district_name,count(unresponsive_cohorts.girl_id) as total_count'));
            if (isset($yes_no) && $yes_no != '') {
                if ($yes_no == 1) {
                    $qry->where('unresponsive_cohorts.matched', 1);
                    if (isset($letter_printed) && $letter_printed != '') {
                        if ($letter_printed == 1) {
                            $qry->where('beneficiary_information.letter_printed', 1);
                        } else {
                            $qry->where('beneficiary_information.letter_printed', '<>', 1)
                                ->orWhere(function ($query) {
                                    $query->whereNull('beneficiary_information.letter_printed');
                                });
                        }
                    }
                } else {
                    $qry->join('suspension_requests', 'suspension_requests.girl_id', '=', 'beneficiary_information.id');
                }
            }
            $qry->groupBy('school_information.id');
            $data = $qry->get();
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

    function getInSchDetailedSummary(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $beneficiary_status_id = $req->input('beneficiary_status_id');

            $dataQry = DB::table('school_information')
                ->join('districts', 'school_information.district_id', '=', 'districts.id')
                ->join('beneficiary_information', function ($join) use ($batch_id) {
                    $join->on('beneficiary_information.school_id', '=', 'school_information.id')
                        ->whereIn('beneficiary_information.category', array(2, 3))
                        ->where('batch_id', $batch_id);
                })
                ->select(DB::raw('school_information.*,districts.name as district_name, count(beneficiary_information.id) as continuing_girls'))
                ->groupBy('school_information.id');
            if (validateisNumeric($beneficiary_status_id)) {
                $dataQry->where('beneficiary_information.beneficiary_status', $beneficiary_status_id);
            }
            $data = $dataQry->get();

            $res = array(
                'success' => true,
                'message' => returnMessage($data),
                'results' => $data
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

    function getInSchDetailedAnalysisSummary(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $verification_type = $req->input('verification_type');
        $qry = DB::table('school_information')
            ->join('districts', 'school_information.district_id', '=', 'districts.id')
            ->join('beneficiary_information', function ($join) use ($batch_id) {
                $join->on('beneficiary_information.school_id', '=', 'school_information.id')
                    ->whereIn('beneficiary_information.category', array(2, 3))
                    ->where('batch_id', $batch_id);
            })
            ->select(DB::raw('SUM(IF(beneficiary_information.category IN (2,3), 1, 0)) AS identified_girls_count,
                              SUM(IF(beneficiary_status =3 AND verification_recommendation=1 , 1, 0)) AS recommended_girls_count,
                              SUM(IF(beneficiary_status =3 AND verification_recommendation=2 , 1, 0)) AS unrecommended_girls_count,
                              SUM(IF(beneficiary_status =3 AND verification_recommendation=3 , 1, 0)) AS notfound_girls_count,
                              SUM(IF(beneficiary_status =2 , 1, 0)) AS verified_notsubmitted_count,
                              SUM(IF(beneficiary_status =8 , 1, 0)) AS forwarded_sch_placement_count,
                              SUM(IF(beneficiary_status =4 , 1, 0)) AS forwarded_letters_count,
                              SUM(IF(beneficiary_status =6 , 1, 0)) AS forwarded_followups_count,
                              school_information.*,districts.name as district_name'));
        if (validateisNumeric($verification_type)) {
            $qry->where('verification_type', $verification_type);
        }
        $qry->groupBy('school_information.id');
        $data = $qry->get();
        $res = array(
            'results' => $data
        );
        return response()->json($res);
    }

    function getInSchUserAnalysisSummary(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $verification_type = $req->input('verification_type');
        $qry = DB::table('beneficiary_information')
            ->join('school_information', 'beneficiary_information.school_id', '=', 'school_information.id')
            ->join('districts', 'school_information.district_id', '=', 'districts.id')
            ->join('beneficiary_verification_report', 'beneficiary_information.id', '=', 'beneficiary_verification_report.beneficiary_id')
            ->join('users', 'beneficiary_verification_report.created_by', '=', 'users.id')
            ->select(DB::raw('users.id as user_id,COUNT(DISTINCT(beneficiary_information.beneficiary_id)) as done_count,districts.name as district_name,users.first_name,users.last_name,users.email'))
            ->whereIn('beneficiary_information.category', array(2, 3))
            ->where('beneficiary_information.batch_id', $batch_id)
            ->where('beneficiary_information.beneficiary_status', '>', 1);
        if (validateisNumeric($verification_type)) {
            $qry->where('verification_type', $verification_type);
        }
        $qry->groupBy('beneficiary_verification_report.created_by')
            ->groupBy('school_information.district_id');
        $data = $qry->get();
        $data = convertStdClassObjToArray($data);
        $results = decryptArray($data);

        /*$qry=DB::select( DB::raw("SELECT t2.name as district_name from school_information t1 INNER JOIN districts t2 on t1.district_id=t2.id
                          INNER JOIN beneficiary_information t3 ON t1.id=t3.school_id AND t3.category IN (2,3) AND t3.batch_id=2
                          LEFT JOIN (SELECT beneficiary_id,COUNT(DISTINCT(beneficiary_id)) as done_count FROM beneficiary_verification_report GROUP BY beneficiary_id) t4
                          ON t4.beneficiary_id=t3.id"));*/
        $res = array(
            'results' => $results
        );
        return response()->json($res);
    }

    function getInSchUserAnalysisSummaryTotals(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $verification_type = $req->input('verification_type');
        $qry = DB::table('beneficiary_information')
            ->join('school_information', 'beneficiary_information.school_id', '=', 'school_information.id')
            ->join('districts', 'school_information.district_id', '=', 'districts.id')
            ->select(DB::raw('COUNT(DISTINCT(beneficiary_information.beneficiary_id)) as total_count,SUM(IF(beneficiary_information.beneficiary_status>1,1,0)) as passed_dataentry_count,districts.name as district_name'))
            ->whereIn('beneficiary_information.category', array(2, 3))
            ->where('beneficiary_information.batch_id', $batch_id);
        if (validateisNumeric($verification_type)) {
            $qry->where('verification_type', $verification_type);
        }
        $qry->groupBy('school_information.district_id');
        $data = $qry->get();
        $res = array(
            'results' => $data
        );
        return response()->json($res);
    }

    function getInSchEntryOutcomeAnalysisSummary(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $verification_type = $req->input('verification_type');
        $qry = DB::table('school_information')
            ->join('districts', 'school_information.district_id', '=', 'districts.id')
            ->join('beneficiary_information', function ($join) use ($batch_id) {
                $join->on('beneficiary_information.school_id', '=', 'school_information.id')
                    ->whereIn('beneficiary_information.category', array(2, 3))
                    ->where('batch_id', $batch_id);
            })
            ->select(DB::raw('SUM(IF(beneficiary_information.category IN (2,3), 1, 0)) AS identified_girls_count,
                              SUM(IF(beneficiary_status >1 AND verification_recommendation=1 , 1, 0)) AS recommended_girls_count,
                              SUM(IF(beneficiary_status >1 AND verification_recommendation=2 , 1, 0)) AS unrecommended_girls_count,
                              SUM(IF(beneficiary_status >1 AND verification_recommendation=3 , 1, 0)) AS notfound_girls_count,
                              districts.name as district_name'));
        if (validateisNumeric($verification_type)) {
            $qry->where('verification_type', $verification_type);
        }
        $qry->groupBy('districts.id');
        $data = $qry->get();
        $res = array(
            'results' => $data
        );
        return response()->json($res);
    }

    function getOutSchUserAnalysisSummary(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $verification_type = $req->input('verification_type');
        $qry = DB::table('beneficiary_information as t1')
            ->join('districts as t2', 't1.district_id', '=', 't2.id')
            ->join('beneficiary_verification_report as t3', 't1.id', '=', 't3.beneficiary_id')
            ->join('users as t4', 't3.created_by', '=', 't4.id')
            ->select(DB::raw('t4.id as user_id,COUNT(DISTINCT(t1.beneficiary_id)) as done_count,t2.name as district_name,t4.first_name,t4.last_name,t4.email'))
            ->where('t1.category', 1)
            ->where('t1.beneficiary_status', '>', 1)
            ->where('t1.batch_id', $batch_id);
        if (validateisNumeric($verification_type)) {
            $qry->where('t1.verification_type', $verification_type);
        }
        $qry->groupBy('t3.created_by', 't2.id');
        $data = $qry->get();
        $data = convertStdClassObjToArray($data);
        $results = decryptArray($data);
        $res = array(
            'results' => $results
        );
        return response()->json($res);
    }

    function getOutSchUserAnalysisSummaryTotals(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $verification_type = $req->input('verification_type');
        $qry = DB::table('beneficiary_information as t1')
            ->join('districts as t2', 't1.district_id', '=', 't2.id')
            ->select(DB::raw('COUNT(DISTINCT(t1.beneficiary_id)) as total_count,SUM(IF(t1.beneficiary_status>1,1,0)) as passed_dataentry_count,t2.name as district_name'))
            ->where('t1.category', 1)
            ->where('t1.batch_id', $batch_id);
        if (validateisNumeric($verification_type)) {
            $qry->where('t1.verification_type', $verification_type);
        }
        $qry->groupBy('t1.district_id');
        $data = $qry->get();
        $res = array(
            'results' => $data
        );
        return response()->json($res);
    }

    function getOutSchEntryOutcomeAnalysisSummary(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $verification_type = $req->input('verification_type');
        $qry = DB::table('beneficiary_information as t1')
            ->join('districts as t2', 't1.district_id', '=', 't2.id')
            ->select(DB::raw('SUM(IF(t1.category = 1, 1, 0)) AS identified_girls_count,
                              SUM(IF(beneficiary_status >1 AND verification_recommendation=1 , 1, 0)) AS recommended_girls_count,
                              SUM(IF(beneficiary_status >1 AND verification_recommendation=2 , 1, 0)) AS unrecommended_girls_count,
                              SUM(IF(beneficiary_status >1 AND verification_recommendation=3 , 1, 0)) AS notfound_girls_count,
                              t2.name as district_name'))
            ->where('t1.category', 1)
            ->where('batch_id', $batch_id);
        if (validateisNumeric($verification_type)) {
            $qry->where('verification_type', $verification_type);
        }
        $qry->groupBy('t2.id');
        $data = $qry->get();
        $res = array(
            'results' => $data
        );
        return response()->json($res);
    }

    function getOutSchEntryOutcomeAnalysisSummary1(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $data = DB::table('cwac')
            ->join('districts', 'cwac.district_id', '=', 'districts.id')
            ->join('beneficiary_information', function ($join) use ($batch_id) {
                $join->on('beneficiary_information.cwac_id', '=', 'cwac.id')
                    ->where('beneficiary_information.category', 1)
                    ->where('batch_id', $batch_id);
            })
            ->select(DB::raw('SUM(IF(beneficiary_information.category = 1, 1, 0)) AS identified_girls_count,
                              SUM(IF(beneficiary_status >1 AND verification_recommendation=1 , 1, 0)) AS recommended_girls_count,
                              SUM(IF(beneficiary_status >1 AND verification_recommendation=2 , 1, 0)) AS unrecommended_girls_count,
                              SUM(IF(beneficiary_status >1 AND verification_recommendation=3 , 1, 0)) AS notfound_girls_count,
                              districts.name as district_name'))
            ->groupBy('districts.id')
            ->get();
        $res = array(
            'results' => $data
        );
        return response()->json($res);
    }

    function getOutSchDetailedSummary(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $beneficiary_status_id = $req->input('beneficiary_status_id');
            $qry = DB::table('beneficiary_information as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->select(DB::raw('t1.cwac_txt,t1.district_id,t2.name as district_name, count(t1.id) as out_of_school_girls'))
                ->where('t1.category', 1)
                ->where('t1.batch_id', $batch_id)
                ->groupBy('t1.cwac_txt');
            if (validateisNumeric($beneficiary_status_id)) {
                $qry->where('t1.beneficiary_status', $beneficiary_status_id);
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

    function getOutSchDetailedAnalysisSummary(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $verification_type = $req->input('verification_type');
        $qry = DB::table('beneficiary_information as t1')
            ->join('districts as t2', 't1.district_id', '=', 't2.id')
            ->select(DB::raw('SUM(IF(t1.category = 1, 1, 0)) AS identified_girls_count,
                              SUM(IF(beneficiary_status =3 AND verification_recommendation=1 , 1, 0)) AS recommended_girls_count,
                              SUM(IF(beneficiary_status =3 AND verification_recommendation=2 , 1, 0)) AS unrecommended_girls_count,
                              SUM(IF(beneficiary_status =3 AND verification_recommendation=3 , 1, 0)) AS notfound_girls_count,
                              SUM(IF(beneficiary_status =2 , 1, 0)) AS verified_notsubmitted_count,
                              SUM(IF(beneficiary_status =5 , 1, 0)) AS forwarded_sch_matching_count,
                              SUM(IF(beneficiary_status =8 , 1, 0)) AS forwarded_sch_placement_count,
                              SUM(IF(beneficiary_status =4 , 1, 0)) AS forwarded_letters_count,
                              SUM(IF(beneficiary_status =6 , 1, 0)) AS forwarded_followups_count,
                              t1.district_id,t1.cwac_txt,t2.name as district_name'))
            ->where('t1.category', 1)
            ->where('t1.batch_id', $batch_id)
            ->groupBy('t1.cwac_txt');
        if (validateisNumeric($verification_type)) {
            $qry->where('t1.verification_type', $verification_type);
        }
        $data = $qry->get();
        $res = array(
            'results' => $data
        );
        return response()->json($res);
    }

    function getSchoolMatchingSummary(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $print_filter = $req->input('print_filter');
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->select(DB::raw('t1.district_id,t1.cwac_txt,t2.name as district_name, count(t1.id) as out_of_school_girls'))
                ->where('t1.category', 1)
                ->where('t1.verification_recommendation', 1)
                ->where('t1.beneficiary_status', 5)
                ->where('batch_id', $batch_id);
            if (isset($print_filter) && $print_filter != '') {
                if ($print_filter == 1) {
                    $qry->where('t1.matching_form_printed', 1);
                } else if ($print_filter == 2) {
                    $qry->where('t1.matching_form_printed', '<>', 1)
                        ->orWhere(function ($query) {
                            $query->whereNull('t1.matching_form_printed');
                        });
                }
            }
            $qry->groupBy('t1.cwac_txt');
            $data = $qry->get();
            /*$data = convertStdClassObjToArray($data);
            $data = decryptArray($data);*/
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

    function getSchoolMatchingSummary1(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $print_filter = $req->input('print_filter');
        try {
            $qry = DB::table('cwac')
                ->join('districts', 'cwac.district_id', '=', 'districts.id')
                ->join('beneficiary_information', function ($join) use ($batch_id) {
                    $join->on('beneficiary_information.cwac_id', '=', 'cwac.id')
                        ->where('beneficiary_information.category', 1)
                        ->where('beneficiary_information.verification_recommendation', 1)
                        ->where('beneficiary_information.beneficiary_status', 5)
                        ->where('batch_id', $batch_id);
                })
                ->select(DB::raw('cwac.*,districts.name as district_name, count(beneficiary_information.id) as out_of_school_girls'));
            if (isset($print_filter) && $print_filter != '') {
                if ($print_filter == 1) {
                    $qry->where('beneficiary_information.matching_form_printed', 1);
                } else if ($print_filter == 2) {
                    $qry->where('beneficiary_information.matching_form_printed', '<>', 1)
                        ->orWhere(function ($query) {
                            $query->whereNull('beneficiary_information.matching_form_printed');
                        });
                }
            }
            $qry->groupBy('beneficiary_information.cwac_id');
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

    function getSchoolPlacementSummary(Request $req)
    {
        try {
            $batch_id = $req->input('batch_id');
            $print_filter = $req->input('print_filter');
            $qry = DB::table('school_information')
                ->join('beneficiary_information', function ($join) use ($batch_id) {
                    $join->on('beneficiary_information.exam_school_id', '=', 'school_information.id')
                        //->where('beneficiary_information.category', 3)
                        ->where('beneficiary_information.verification_recommendation', 1)
                        ->where('beneficiary_information.beneficiary_status', 8)
                        ->where('batch_id', $batch_id);
                })
                ->join('districts', 'school_information.district_id', '=', 'districts.id')
                ->select(DB::raw('school_information.district_id,school_information.name,districts.name as district_name, count(beneficiary_information.id) as out_of_school_girls'));
            if (isset($print_filter) && $print_filter != '') {
                if ($print_filter == 1) {
                    $qry->where('beneficiary_information.placement_form_printed', 1);
                } else if ($print_filter == 2) {
                    $qry->where('beneficiary_information.placement_form_printed', '<>', 1)
                        ->orWhere(function ($query) {
                            $query->whereNull('beneficiary_information.placement_form_printed');
                        });
                }
            }
            $qry->groupBy('beneficiary_information.exam_school_id');
            $data = $qry->get();
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

    function getDetailedSumCount($batch_id, $low_level_id, $category_id, $is_in_school)
    {
        $low_level = 'school_id';
        if ($is_in_school == 2) {
            $low_level = 'cwac_id';
        }
        $where = array(
            'batch_id' => $batch_id,
            $low_level => $low_level_id,
            'category' => $category_id
        );
        $count = DB::table('beneficiary_information')
            ->where($where)
            ->count();
        return $count;
    }

    function getLetterGenDetailedSumCount($batch_id, $low_level_id, $category_id)
    {
        $where = array(
            'batch_id' => $batch_id,
            'school_id' => $low_level_id,
            'category' => $category_id,
            'beneficiary_status' => 4
        );
        $count = DB::table('beneficiary_information')
            ->where($where)
            ->count();
        return $count;
    }

    function getDistrictCapacityAssessments(Request $req)
    {
        $district_id = $req->input('district_id');
        $year = $req->input('year');
        $school_id = $req->input('school_id');
        $qry = DB::table('school_information');
        if ($school_id != '') {
            $qry->where('id', $school_id);
        }
        if ($district_id != '') {
            $qry->where('district_id', $district_id);
        }
        $schools = $qry->get();
        $schools = convertStdClassObjToArray($schools);
        $schools = decryptArray($schools);
        foreach ($schools as $key => $school) {
            $schools[$key]['grade_8'] = $this->getGradeAvailableSpaces($school['id'], 8, $year);
            $schools[$key]['grade_9'] = $this->getGradeAvailableSpaces($school['id'], 9, $year);
            $schools[$key]['grade_10'] = $this->getGradeAvailableSpaces($school['id'], 10, $year);
            $schools[$key]['grade_11'] = $this->getGradeAvailableSpaces($school['id'], 11, $year);
            $schools[$key]['grade_12'] = $this->getGradeAvailableSpaces($school['id'], 12, $year);
        }
        $res = array(
            'results' => $schools
        );
        return response()->json($res);
    }

    function getGradeAvailableSpaces($school_id, $grade, $year)
    {
        $available = 0;
        $where = array(
            'school_id' => $school_id,
            'grade' => $grade,
            'year' => $year
        );
        $info = DB::table('school_capacity_assessments')
            ->where($where)
            ->first();
        //get already assigned
        $matching_year = $year - 1;
        $matched = DB::table('school_matching_details')
            ->where('school_id', $school_id)
            ->where('grade', $grade)
            ->whereYear('created_at', $matching_year)
            ->count();
        if (!is_null($info)) {
            $available = (($info->classroom_max - $info->current_capacity) - $matched);
        }
        return $available;
    }

    function getSchoolMatchingProgress(Request $req)
    {
        $batch_id = $req->input('batch_id');
        try {
            $qry = DB::table('beneficiary_information')
                ->join('school_matching_details', 'beneficiary_information.id', '=', 'school_matching_details.girl_id')
                ->join('school_information', 'school_matching_details.school_id', '=', 'school_information.id')
                ->leftJoin('districts', 'school_information.district_id', '=', 'districts.id')
                ->select('school_information.id', 'school_information.name', 'school_information.district_id', 'districts.name as district_name')
                ->where('batch_id', $batch_id)
                ->groupBy('school_matching_details.school_id');
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $data = decryptArray($data);
            foreach ($data as $key => $datum) {
                $data[$key]['girls_matched'] = $this->getMatchingCount($batch_id, $datum['id']);
            }
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'All is well!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    function getMatchingCount($batch_id, $school_id)
    {
        $count = DB::table('beneficiary_information')
            ->join('school_matching_details', 'beneficiary_information.id', '=', 'school_matching_details.girl_id')
            ->join('school_information', 'school_matching_details.school_id', '=', 'school_information.id')
            ->where('batch_id', $batch_id)
            ->where('school_matching_details.school_id', $school_id)
            ->count();
        return $count;
    }

    function getSchoolPlacementProgress(Request $req)
    {
        $batch_id = $req->input('batch_id');
        try {
            $qry = DB::table('beneficiary_information')
                ->join('school_placement_details', 'beneficiary_information.id', '=', 'school_placement_details.girl_id')
                ->join('school_information', 'school_placement_details.school_id', '=', 'school_information.id')
                ->leftJoin('districts', 'school_information.district_id', '=', 'districts.id')
                ->select('school_information.id', 'school_information.district_id', 'districts.name as district_name', 'school_information.name')
                ->where('batch_id', $batch_id)
                ->groupBy('school_placement_details.school_id');
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $data = decryptArray($data);
            foreach ($data as $key => $datum) {
                $data[$key]['girls_placed'] = $this->getPlacementCount($batch_id, $datum['id']);
            }
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'All is well!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    function getPlacementCount($batch_id, $school_id)
    {
        $count = DB::table('beneficiary_information')
            ->join('school_placement_details', 'beneficiary_information.id', '=', 'school_placement_details.girl_id')
            ->join('school_information', 'school_placement_details.school_id', '=', 'school_information.id')
            ->where('batch_id', $batch_id)
            //->where('qualified', 1)
            ->where('school_placement_details.school_id', $school_id)
            ->count();
        return $count;
    }

    public function updateDuplicatedGirlDetails(Request $req)
    {
        $post_data = $req->input();
        $id = $post_data['id'];
        $batch_id = $post_data['batch_id'];
        unset($post_data['id']);
        unset($post_data['batch_id']);
        unset($post_data['_token']);
        try {
            //check number of occurrences
            $duplicates = $this->getDuplicatedRecords($id, $batch_id);
            $duplicate_count = count($duplicates);
            $duplicates = convertStdClassObjToArray($duplicates);
            $duplicates = convertAssArrayToSimpleArray($duplicates, 'id');

            DB::table('beneficiary_master_info')
                ->where('id', $id)
                ->update($post_data);
            //check if still duplicate
            $is_duplicate = $this->isStillDuplicateRecord($id, $batch_id);
            if ($is_duplicate == 0 || $is_duplicate < 1) {//no longer a duplicate
                DB::table('beneficiary_master_info')
                    ->where('id', $id)
                    ->update(array('is_duplicate' => 0));
                if ($duplicate_count == 1) {
                    DB::table('beneficiary_master_info')
                        ->whereIn('id', $duplicates)
                        ->update(array('is_duplicate' => 0));
                }
            }
            $res = array(
                'success' => true,
                'message' => 'Details updated successfully'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function updateMappingDuplicatedGirlDetails(Request $req)
    {
        $post_data = $req->input();
        $id = $post_data['id'];
        $batch_id = $post_data['batch_id'];
        unset($post_data['id']);
        unset($post_data['batch_id']);
        unset($post_data['_token']);
        try {
            DB::table('beneficiary_master_info')
                ->where('id', $id)
                ->update($post_data);
            //check if still duplicate
            $is_duplicate = $this->isDuplicateWithExistingRecord($id, $batch_id);
            if ($is_duplicate == 0 || $is_duplicate < 1) {//no longer a duplicate
                DB::table('beneficiary_master_info')
                    ->where('id', $id)
                    ->update(array('is_duplicate_with_existing' => 0, 'is_dup_processed' => 1));
            }
            $res = array(
                'success' => true,
                'message' => 'Details updated successfully'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function updateIsDuplicate(Request $req)
    {
        $girl_id = $req->input('girl_id');
        $batch_id = $req->input('batch_id');
        try {
            //check number of occurrences
            $duplicates = $this->getDuplicatedRecords($girl_id, $batch_id);
            $duplicate_count = count($duplicates);
            $duplicates = convertStdClassObjToArray($duplicates);
            $duplicates = convertAssArrayToSimpleArray($duplicates, 'id');
            DB::table('beneficiary_master_info')
                ->where('id', $girl_id)
                ->update(array('is_duplicate' => 0));
            if ($duplicate_count == 1) {
                DB::table('beneficiary_master_info')
                    ->whereIn('id', $duplicates)
                    ->update(array('is_duplicate' => 0));
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
        }
        return response()->json($res);
    }

    public function updateIsMappingDuplicate(Request $req)
    {
        $girl_id = $req->input('girl_id');
        $is_duplicate = $req->input('is_duplicate');
        if ($is_duplicate == 1 || $is_duplicate === 1) {
            $update_params = array(
                'is_active' => 0,
                'is_dup_processed' => 1
            );
        } else {
            $update_params = array(
                'is_duplicate_with_existing' => 0,
                'is_dup_processed' => 1
            );
        }
        try {
            DB::table('beneficiary_master_info')
                ->where('id', $girl_id)
                ->update($update_params);
            //->update(array('is_duplicate_with_existing' => 0));
            $res = array(
                'success' => true,
                'message' => 'Request executed successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function processDuplicates(Request $req)
    {
        $main_girl_id = $req->input('main_girl_id');
        $girl_id = $req->input('girl_id');
        $batch_id = $req->input('batch_id');
        $duplicatesArr = $req->input('duplicates');
        $confirmed_duplicates = json_decode($duplicatesArr);

        $res = array();
        DB::transaction(function () use (&$res, $duplicatesArr, $confirmed_duplicates, $main_girl_id, $girl_id, $batch_id) {
            try {
                $suspected_duplicates = $this->getDuplicatedRecords($main_girl_id, $batch_id);
                $suspected_duplicates->push(array('id' => $main_girl_id));
                $all_suspected_duplicates = convertStdClassObjToArray($suspected_duplicates);
                $all_suspected_duplicates = convertAssArrayToSimpleArray($all_suspected_duplicates, 'id');
                $confirmed_not_duplicates = array_diff($all_suspected_duplicates, $confirmed_duplicates);
                $confirmed_duplicates_diff = array_diff($confirmed_duplicates, [$main_girl_id]);//less main girl ID
                $confirmed_duplicates_diff2 = array_diff($confirmed_duplicates, [$girl_id]);//less selected girl ID
                //first log this to avoid screwing the whole thing
                $duplicate_log = array();
                foreach ($confirmed_duplicates_diff as $duplicate) {
                    $duplicate_log[] = array(
                        'main_girl_id' => $main_girl_id,
                        'duplicate_girl_id' => $duplicate,
                        'selected_girl_id' => $girl_id,
                        'created_by' => \Auth::user()->id
                    );
                }
                DB::table('duplicate_processing_log')->insert($duplicate_log);
                //remove duplicate flag for the selected girl
                DB::table('beneficiary_master_info')
                    ->where('id', $girl_id)
                    ->update(array('is_duplicate' => 0));
                //remove duplicate flag for the confirmed not duplicate..but wait we still have to check for them if more than one
                if (count($confirmed_not_duplicates) == 1) {
                    DB::table('beneficiary_master_info')
                        ->whereIn('id', $confirmed_not_duplicates)
                        ->update(array('is_duplicate' => 0));
                }
                //deactivate the duplicated records
                DB::table('beneficiary_master_info')
                    ->whereIn('id', $confirmed_duplicates_diff2)
                    ->update(array('is_active' => 0));
                // ->update(array('is_duplicate' => 0, 'is_active' => 0));
                $res = array(
                    'success' => true,
                    'message' => 'Request executed successfully!!'
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

    public function processMappingDuplicates(Request $req)
    {
        $girl_id = $req->input('girl_id');
        try {
            DB::table('beneficiary_master_info')
                ->where('id', $girl_id)
                ->update(array('is_duplicate_with_existing' => 0, 'is_active' => 0));
            $res = array(
                'success' => true,
                'message' => 'Request executed successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getDuplicatedRecords($girl_id, $batch_id)
    {
        $duplicates_params = DB::table('beneficiary_duplicates_setup')->select('dataindex')->get();
        $duplicates_params = convertAssArrayToSimpleArray(convertStdClassObjToArray($duplicates_params), 'dataindex');
        $duplicates_params = implode(',', $duplicates_params);
        $where = DB::table('beneficiary_master_info')->where('id', $girl_id)->selectRaw($duplicates_params)->get();
        $where = convertStdClassObjToArray($where);
        $duplicates = DB::table('beneficiary_master_info')
            ->where('batch_id', $batch_id)
            ->where('id', '<>', $girl_id)
            ->where('is_duplicate', 1)
            ->where($where[0])
            ->select('id')
            ->get();
        return $duplicates;
    }

    public function getGirlAdditionalInfo(Request $req)
    {
        $template_id = $req->input('template_id');
        $girl_id = $req->input('girl_id');
        $master_id = $req->input('master_id');
        $batch_id = $req->input('batch_id');
        $stdTempID = getStdTemplateId();
        if ($template_id == $stdTempID) {
            return response()->json(array());
        }
        $qry = DB::table('template_fields');
        if ($girl_id != '') {
            $qry->leftJoin('temp_additional_fields_values', function ($join) use ($batch_id, $girl_id, $master_id) {
                $join->on('template_fields.id', '=', 'temp_additional_fields_values.field_id')
                    ->on('temp_additional_fields_values.batch_id', '=', DB::raw($batch_id))
                    ->on('temp_additional_fields_values.main_temp_id', '=', DB::raw($master_id));
            })
                ->select('template_fields.*', 'temp_additional_fields_values.value');
        }
        $qry->where('temp_id', $template_id);
        $data = $qry->get();
        $data = convertStdClassObjToArray($data);
        $data = decryptArray($data);
        $res = array(
            'results' => $data
        );
        return response()->json($res);
    }

    public function saveGirlAdditionalInfo(Request $req)
    {
        $post_data = $req->input();
        $additionalValues = $post_data['values'];
        $girl_id = $post_data['girl_id'];
        $batch_id = $post_data['batch_id'];
        unset($post_data['values']);
        unset($post_data['girl_id']);
        unset($post_data['_token']);
        $additionalValues = json_decode($additionalValues);
        try {
            if (is_numeric($girl_id) && $girl_id != '') {
                DB::table('beneficiary_master_info')
                    ->where('id', $girl_id)
                    ->update($post_data);
                if (count($additionalValues) > 0) {
                    foreach ($additionalValues as $additionalValue) {
                        $where = array(
                            'main_temp_id' => $girl_id,
                            'field_id' => $additionalValue->field_id
                        );
                        $insertValues = array(
                            'main_temp_id' => $girl_id,
                            'field_id' => $additionalValue->field_id,
                            'batch_id' => $batch_id,
                            'value' => $additionalValue->value
                        );
                        if (recordExists('temp_additional_fields_values', $where)) {
                            DB::table('temp_additional_fields_values')
                                ->where($where)
                                ->update($insertValues);
                        } else {
                            DB::table('temp_additional_fields_values')
                                ->insert($insertValues);
                        }
                    }
                }
            } else {
                $id = DB::table('beneficiary_master_info')
                    ->insertGetId($post_data);
                $girl_id = $id;
                if (count($additionalValues) > 0) {
                    foreach ($additionalValues as $additionalValue) {
                        $insertValues = array(
                            'main_temp_id' => $id,
                            'field_id' => $additionalValue->field_id,
                            'batch_id' => $batch_id,
                            'value' => $additionalValue->value
                        );
                        DB::table('temp_additional_fields_values')
                            ->insert($insertValues);
                    }
                }
            }
            $res = array(
                'success' => true,
                'girl_id' => $girl_id,
                'message' => 'Information saved successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    function getImportationBatchesSubModulesDMSFolderID(Request $req)
    {
        $batch_id = $req->input('parent_id');
        $sub_module_id = $req->input('sub_module_id');
        $parent_folder_id = DB::table('batch_info')
            ->where('id', $batch_id)
            ->value('folder_id');
        if ($parent_folder_id == '' || $parent_folder_id == 0) {
            $res = array(
                'success' => false,
                'message' => 'Problem was encountered while fetching folder details relating to this batch number!! Please contact system admin.'
            );
            return response()->json($res);
        }
        try {
            $folder_id = getSubModuleFolderID($parent_folder_id, $sub_module_id);
            $res = array(
                'success' => true,
                'folder_id' => $folder_id,
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

    public function getLetterGenerationHistory(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $province_id = $req->input('province_id');
        $district_id = $req->input('district_id');
        $school_id = $req->input('school_id');
        $start = $req->input('start');
        $limit = $req->input('limit');
        $filter = $req->input('filter');
        $whereClauses = array();
        $filter_string = '';
        if (isset($filter)) {
            $filters = json_decode($filter);
            if ($filters != NULL) {
                foreach ($filters as $filter) {
                    switch ($filter->property) {
                        case 'beneficiary_id' :
                            $whereClauses[] = "t1.beneficiary_id like '%" . ($filter->value) . "%'";
                            break;
                        case 'first_name' :
                            $whereClauses[] = "decrypt(t1.first_name) like '%" . ($filter->value) . "%'";
                            break;
                        case 'last_name' :
                            $whereClauses[] = "decrypt(t1.last_name) like '%" . ($filter->value) . "%'";
                            break;
                        case 'letter_ref' :
                            $whereClauses[] = "letters_gen_log.letter_ref like '%" . ($filter->value) . "%'";
                            break;
                        case 'start_year' :
                            $whereClauses[] = "letters_gen_tracker.start_year = '" . ($filter->value) . "'";
                            break;
                        case 'reporting_date' :
                            $whereClauses[] = "letters_gen_tracker.reporting_date = '" . (converter22($filter->value)) . "'";
                            break;
                        case 'grace_period' :
                            $whereClauses[] = "letters_gen_tracker.grace_period = '" . (converter22($filter->value)) . "'";
                            break;
                        case 'created_at' :
                            $whereClauses[] = "date(letters_gen_tracker.created_at) = '" . (converter1($filter->value)) . "'";
                            break;
                        case 'created_by' :
                            $whereClauses[] = "letters_gen_tracker.created_by = '" . $filter->value . "'";
                            break;
                    }
                }
                $whereClauses = array_filter($whereClauses);
            }
            if (!empty($whereClauses)) {
                $filter_string = implode(' AND ', $whereClauses);
            }
        }
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->select([
                    DB::raw("CASE WHEN decrypt(t1.first_name) IS NULL THEN t1.first_name ELSE decrypt(t1.first_name) END as first_name"),
                    DB::raw("CASE WHEN decrypt(t1.last_name) IS NULL THEN t1.last_name ELSE decrypt(t1.last_name) END as last_name"),
                    DB::raw("CASE WHEN decrypt(users.first_name) IS NULL THEN users.first_name ELSE decrypt(users.first_name) END as user_first_name"),
                    DB::raw("CASE WHEN decrypt(users.last_name) IS NULL THEN users.last_name ELSE decrypt(users.last_name) END as user_last_name"),
                    't1.id as girl_id','t1.beneficiary_id',
                    'letters_gen_log.id as log_id',
                    'letters_gen_log.letter_ref','letters_gen_tracker.*'
                ])
                ->join('school_information as s1', 't1.school_id', '=', 's1.id')
                ->join('letters_gen_log', 't1.id', '=', 'letters_gen_log.girl_id')
                ->join('letters_gen_tracker', 't1.id', '=', 'letters_gen_tracker.girl_id')
                ->leftJoin('users', 'letters_gen_tracker.created_by', '=', 'users.id')
                ->where('t1.batch_id', '=', $batch_id);

            if (isset($province_id) && $province_id != '') {
                $qry->where('s1.province_id', $province_id);
            }
            if (isset($district_id) && $district_id != '') {
                $qry->where('s1.district_id', $district_id);
            }
            if (isset($school_id) && $school_id != '') {
                $qry->where('s1.id', $school_id);
            }
            if ($filter_string != '') {
                $qry->whereRAW($filter_string);
            }

            $qry->offset($start)
                ->limit($limit);
            $data = $qry->get();
            $total = $data->count();
            $res = array(
                'success' => true,
                'results' => $data,
                'total' => $total,
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

    public function updateDatasetInfo(Request $req)
    {
        $id = $req->input('id');
        $post_data = $req->input();
        $user_id = \Auth::user()->id;
        unset($post_data['id']);
        unset($post_data['_token']);
        try {
            $where = array(
                'id' => $id
            );
            $table_name = 'beneficiary_master_info';
            $prev_data = getPreviousRecords($table_name, $where);
            updateRecord($table_name, $prev_data, $where, $post_data, $user_id);
            /*  DB::table($table_name)
                  ->where($where)
                  ->update($post_data);*/
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

    public function getDuplicateSetupLog(Request $req)
    {
        $batch_id = $req->input('batch_id');
        try {
            $qry = DB::table('duplicate_setup_log')
                ->where('batch_id', $batch_id);
            $results = $qry->get();
            $res = array(
                'success' => true,
                'result' => $results,
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

    function getGirlMappingInfo(Request $req)
    {
        $master_id = $req->input('master_id');
        try {
            $girl_details = DB::table('beneficiary_master_info')
                ->select('batch_id', 'sct_district', 'constituency', 'ward', 'acc', 'cwac', 'district_name', 'school_code', 'school_name')
                ->where('id', $master_id)
                ->first();
            if (!is_null($girl_details)) {
                $results = array(
                    'master_id' => $master_id,
                    'batch_id' => $girl_details->batch_id,
                    'province_id' => $this->getProvinceID($girl_details->sct_district),
                    'home_district_id' => $this->getDistrictID($girl_details->sct_district),
                    'school_district_id' => $this->getSchoolDistrictID($girl_details->district_name),
                    'cwac_id' => $this->getCwacID($girl_details->cwac),
                    'school_id' => $this->getSchoolID($girl_details->school_code),
                    'acc_id' => $this->getAccID($girl_details->acc),
                    'ward_id' => $this->getWardID($girl_details->ward),
                    'constituency_id' => $this->getConstituencyID($girl_details->constituency),
                );
                $res = array(
                    'success' => true,
                    'message' => 'Data fetched successfully',
                    'results' => $results
                );
            } else {
                $res = array(
                    'success' => true,
                    'message' => 'No matching info found!!',
                    'results' => array()
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

    public function mapSingleBeneficiaryInfo(Request $req)
    {
        $res = array();
        DB::transaction(function () use (&$res, $req) {
            $master_id = $req->input('master_id');
            $batch_id = $req->input('batch_id');
            $province_id = $req->input('province_id');
            $district_id = $req->input('home_district_id');
            $constituency_id = $req->input('constituency_id');
            $ward_id = $req->input('ward_id');
            $cwac_id = $req->input('cwac_id');
            $acc_id = $req->input('acc_id');
            $school_id = $req->input('school_id');
            $user_id = \Auth::user()->id;
            $dms_user = \Auth::user()->dms_id;
            try {
                $girl_details = DB::table('beneficiary_master_info')
                    ->where('id', $master_id)
                    ->first();
                if (is_null($girl_details)) {
                    $res = array(
                        'success' => false,
                        'message' => 'Beneficiary details not found!!'
                    );
                    return response()->json($res);
                }

                $household_id = $this->getHouseHoldID($girl_details->hhh_nrc);
                $dup = $girl_details->is_duplicate_with_existing;
                $dup_processed = $girl_details->is_dup_processed;
                if (!is_numeric($household_id) || $household_id == false) {
                    $addData = array(
                        'number_in_cwac' => $girl_details->hh_in_cwac,
                        'cwac_id' => $cwac_id,
                        'acc_id' => $acc_id,
                        'hhh_nrc_number' => $girl_details->hhh_nrc,
                        'hhh_fname' => $girl_details->hhh_fname,
                        'hhh_lname' => $girl_details->hhh_lname,
                        'created_at' => Carbon::now(),
                        'created_by' => $user_id
                    );
                    $household_id = $this->justAddHouseHoldParam($addData);
                }

                $last_record_id = DB::table('beneficiary_information')->max('id');
                $batch_details = DB::table('batch_info')
                    ->select(DB::raw('YEAR(generated_on) as gen_year'))
                    ->where('id', $batch_id)
                    ->first();
                if (!is_null($batch_details)) {
                    $batch_year = $batch_details->gen_year;
                } else {
                    $batch_year = date('Y');
                }
                $serial_number_counter = $last_record_id + 1;
                $serial_number = str_pad($serial_number_counter, 4, 0, STR_PAD_LEFT);
                $year = substr($batch_year, -2);
                $district_id_ben_id = str_pad($district_id, 4, 0, STR_PAD_LEFT);
                $beneficiary_id = $year . $district_id_ben_id . $serial_number;
                //start DMS UPDATE
                $parent_id = 2;
                $main_module_id = 1;
                $description = $girl_details->girl_fname . ' ' . $girl_details->girl_lname;
                $folder_id = createDMSParentFolder($parent_id, $main_module_id, $beneficiary_id, $description, $dms_user);
                createDMSModuleFolders($folder_id, $main_module_id, $dms_user);
                //end DMS UPDATE
                $beneficiary_details = array(
                    'beneficiary_id' => $beneficiary_id,
                    'household_id' => $household_id,
                    'first_name' => aes_encrypt($girl_details->girl_fname),
                    'last_name' => aes_encrypt($girl_details->girl_lname),
                    'dob' => converter11($girl_details->girl_dob),
                    'relation_to_hhh' => $girl_details->relation_to_hhh,
                    'school_going' => $girl_details->school_going,
                    'qualified_sec_sch' => $girl_details->qualified_sec_sch,
                    'willing_to_return_sch' => $girl_details->willing_to_return_sch,
                    'highest_grade' => $girl_details->highest_grade,
                    'current_school_grade' => $girl_details->current_school_grade,
                    'school_id' => $school_id,
                    'exam_school_id' => $school_id,
                    'cwac_id' => $cwac_id,
                    'acc_id' => $acc_id,
                    'ward_id' => $ward_id,
                    'constituency_id' => $constituency_id,
                    'district_id' => $district_id,
                    'province_id' => $province_id,
                    'bursary_status' => $girl_details->bursary_status,
                    'type_of_bursary' => $girl_details->type_of_bursary,
                    'category' => $girl_details->category,
                    'beneficiary_status' => 1,
                    'master_id' => $girl_details->id,
                    'batch_id' => $batch_id,
                    'created_by' => $user_id,
                    'folder_id' => $folder_id
                );
                $girl_id = DB::table('beneficiary_information')
                    ->insertGetId($beneficiary_details);
                if (is_numeric($girl_id)) {
                    $map_update = array(
                        'is_mapped' => 1
                    );
                    if ($dup == 1 && $dup_processed == 1) {
                        $map_update['is_active'] = 1;
                        $map_update['activated_by'] = $user_id;
                        $map_update['activated_on'] = Carbon::now();
                    }
                    DB::table('beneficiary_master_info')
                        ->where('id', $girl_details->id)
                        ->update($map_update);
                }
                $res = array(
                    'success' => true,
                    'message' => 'Beneficiary details mapped successfully!!'
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

    public function getBatchVerificationChecklist(Request $request)
    {
        $batch_id = $request->input('batch_id');
        $category_id = $request->input('category_id');
        try {
            $where = array(
                'batch_id' => $batch_id,
                'category_id' => $category_id
            );
            $checklist_type = DB::table('batch_checklist_types')
                ->where($where)
                ->value('checklist_type_id');
            $res = array(
                'success' => true,
                'checklist_type_id' => $checklist_type,
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

    public function getImportationErrorLog(Request $request)
    {
        $batch_id = $request->input('batch_id');
        $start = $request->input('start');
        $limit = $request->input('limit');
        try {
            $qry = DB::table('importation_errors')
                ->where('batch_id', $batch_id)
                ->orderBy('page_number');
            $total = $qry->count();
            $results = $qry->offset($start)->limit($limit)->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'totalCount' => $total,
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

    public function processBatchMerging(Request $req)
    {
        $selected_batch_id = $req->input('selected_batch_id');
        $selected_batches = $req->input('selected_batches');
        $selected_batches = json_decode($selected_batches);
        $user_id = \Auth::user()->id;

        $res = array();
        try {
            DB::transaction(function () use (&$res, $selected_batch_id, $selected_batches, $user_id) {
                $params = array();
                $selected_batches_diff = array_diff($selected_batches, [$selected_batch_id]);
                foreach ($selected_batches_diff as $selected_batch_diff) {
                    $params[] = array(
                        'batch_id' => $selected_batch_diff,
                        'merged_to' => $selected_batch_id,
                        'created_by' => $user_id
                    );
                }
                //batch_merging
                DB::table('batch_merging')
                    ->insert($params);
                //beneficiary_master_info
                DB::table('beneficiary_master_info')
                    ->whereIn('batch_id', $selected_batches_diff)
                    ->update(array(
                        'batch_merged' => 1,
                        'master_batch_id' => DB::raw('batch_id')
                    ));
                DB::table('beneficiary_master_info')
                    ->whereIn('batch_id', $selected_batches_diff)
                    ->update(array(
                        'batch_id' => $selected_batch_id
                    ));
                //beneficiary_information
                DB::table('beneficiary_information')
                    ->whereIn('batch_id', $selected_batches_diff)
                    ->update(array(
                        'batch_merged' => 1,
                        'master_batch_id' => DB::raw('batch_id')
                    ));
                DB::table('beneficiary_information')
                    ->whereIn('batch_id', $selected_batches_diff)
                    ->update(array(
                        'batch_id' => $selected_batch_id
                    ));

                $res = array(
                    'success' => true,
                    'message' => 'Request executed successfully!!'
                );
            }, 5);
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getBatchChecklistItems(Request $request)
    {
        try {
            $batch_id = $request->input('batch_id');
            $category_id = $request->input('category_id');
            if ($category_id == 3) {
                $category_id = 2;
            }
            $qry = DB::table('checklist_items as t1')
                ->select(DB::raw("CONCAT_WS('. ',CONCAT('Q',t1.order_no),t1.name) as question,t1.*"))
                ->whereIn('t1.checklist_id', function ($query) use ($batch_id, $category_id) {
                    $query->select(DB::raw('t2.checklist_type_id'))
                        ->from('batch_checklist_types as t2')
                        ->where('t2.batch_id', $batch_id)
                        ->where('t2.category_id', $category_id);
                });
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

    public function getEnrolmentDashboardData(Request $request)
    {
        try {
            $imported = 0;
            $enrolled = 0;
            $verification = 0;
            $school_matching = 0;
            $school_placement = 0;
            $followup = 0;

            $qry1 = DB::table('beneficiary_master_info as t1')
                ->selectRaw("COUNT(*) as imported");
            $results1 = $qry1->first();
            if ($results1) {
                $imported = $results1->imported;
            }

            $qry2 = DB::table('beneficiary_information as t2')
                ->selectRaw("SUM(IF(t2.beneficiary_status=4,1,0)) as enrolled,
                                    SUM(IF(t2.beneficiary_status IN (0,1,2),1,0)) as verification,
                                    SUM(IF(t2.beneficiary_status=5,1,0)) as school_matching,
                                    SUM(IF(t2.beneficiary_status=8,1,0)) as school_placement,
                                    SUM(IF(t2.beneficiary_status=6,1,0)) as followup");
            $results2 = $qry2->first();

            if ($results2) {
                $enrolled = $results2->enrolled;
                $verification = $results2->verification;
                $school_matching = $results2->school_matching;
                $school_placement = $results2->school_placement;
                $followup = $results2->followup;
            }
            $res = array(
                'imported' => ($imported) ? number_format($imported) : 0,
                'enrolled' => ($enrolled) ? number_format($enrolled) : 0,
                'verification' => ($verification) ? number_format($verification) : 0,
                'school_matching' => ($school_matching) ? number_format($school_matching) : 0,
                'school_placement' => ($school_placement) ? number_format($school_placement) : 0,
                'followup' => ($followup) ? number_format($followup) : 0
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

    public function exportEnrolmentRecords(Request $request)
    {
        try {
            ini_set('memory_limit', '-1');
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

    public function getDummyCombinedInfo()
    {
        try {
            $qry = DB::table('dummy_beneficiary_master_info as t1')
                ->leftJoin('dummy_household_profile as t2', 't1.household_id', '=', 't2.household_id')
                ->select('t1.*', 't2.first_name as hhh_fname', 't2.first_name as hhh_lname');
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

    public function correctDummyDataFormats(Request $request)
    {
        $search_value = $request->input('search_value');
        //codes
        /* array(
             1=>'SCT District', [table=districts, field=sct_district, flag=sct_district_updated]
             2=>'CWAC' [table=cwac, field=cwac, flag=cwac_updated]
         );*/
        try {
            $search = array(
                1 => ['table' => 'districts', 'field' => 'sct_district', 'flag' => 'sct_district_updated'],
                2 => ['table' => 'cwac', 'field' => 'cwac', 'flag' => 'cwac_updated']
            );
            $search_details = $search[$search_value];
            $table_name = 'dummy_beneficiary_master_add_info';
            if (!is_null($search_details)) {
                $table = $search_details['table'];
                $field = $search_details['field'];
                $flag = $search_details['flag'];
                $qry = DB::table($table_name)
                    ->where($flag, 0);
                    //->where('id', 2);
                    //->limit(10000);
                $dummy_data = $qry->get();
                foreach ($dummy_data as $dummy_datum) {
                    $code_res = $this->getCode($table, $dummy_datum->$field, $dummy_datum->sct_district);
                    if ($code_res['success'] == true) {
                        $update = array(
                            $flag => 1,
                            $field => $code_res['code_name']
                        );
                        DB::table($table_name)
                            ->where('id', $dummy_datum->id)
                            ->update($update);
                    }
                }
                $res = array(
                    'success' => true,
                    'message' => 'Update successful'
                );
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'Search value not found'
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

    public function getCode($table, $name, $district)
    {
        DB::enableQueryLog();
        $qry = DB::table($table)
            ->whereRaw("district LIKE '%$district%' AND name LIKE '%$name%'");
        $data = $qry->first();
        if (is_null($data)) {
            $res = array(
                'success' => false
            );
        } else {
            $res = array(
                'success' => true,
                'code_name' => $data->code . '-' . $data->name
            );
        }
        return $res;
    }

    public function getDummyImportedRecords()
    {
        try {
            $qry = DB::table('dummy_beneficiary_master_info');
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
	
    public
    function getVerificationChecklistItems(Request $req)
    {
        try {
            $checklist_type_check = $req->input('checklist_type');
            $checklist_type = $checklist_type_check == 7 ? 6 : $checklist_type_check;
            // $checklist_type = 6;
            $beneficiary_id = $req->input('beneficiary_id');
            $batch_id = $req->input('batch_id');
            $category_id = $req->input('category_id');
            if ($category_id == 3) {
                $category_id = 2;
            }
            if (!is_numeric($checklist_type)) {
                $checklist_type = getBatchVerificationChecklist($batch_id, $category_id);
            }
            $qry = DB::table('checklist_items')
                ->select('checklist_items.*', 'beneficiary_verification_report.id as report_id', 'beneficiary_verification_report.response', 'beneficiary_verification_report.remark')
                ->leftJoin('beneficiary_verification_report', function ($join) use ($beneficiary_id) {
                    $join->on('checklist_items.id', '=', 'beneficiary_verification_report.checklist_item_id')
                        ->on('beneficiary_verification_report.beneficiary_id', '=', DB::raw($beneficiary_id));
                })
                ->where(array('checklist_items.checklist_id' => $checklist_type))
                ->orderBy('checklist_items.order_no');
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $data = decryptArray($data);
            foreach ($data as $key => $datum) {
                //$recommendation_details=
                if ($checklist_type == 6) {
                    if ($datum['order_no'] == 17) {
                        $data[$key]['response'] = $this->_getBeneficiaryDisabilities($beneficiary_id);
                    }
                } else if ($checklist_type == 1) {
                    if ($datum['order_no'] == 11) {
                        $data[$key]['response'] = $this->_getBeneficiaryDisabilities($beneficiary_id);
                    }
                } else if ($checklist_type == 2) {
                    if ($datum['order_no'] == 6) {
                        $data[$key]['response'] = $this->_getBeneficiaryDisabilities($beneficiary_id);
                    }
                }
            }
            $res = array(
                'success' => true,
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
   
	//Job and Frank moded
    public function insertAndVerifyResponses(Request $request) {       
        try{
            $category_id = $request->input('category_id');
            $batch_id = $request->input('batch_id');
            $user_id = $this->user_id;
            $count = 0;            $girl_id = 1;

            // if($this->user_id == 30) {
            // $qry = DB::table('checklist_items')
            //     ->select('checklist_items.*', 'beneficiary_verification_report.id as report_id',
            //         'beneficiary_verification_report.response', 
            //         'beneficiary_verification_report.remark')
            //     ->leftJoin('beneficiary_verification_report', function ($join) use ($girl_id) {
            //         $join->on('checklist_items.id', '=', 'beneficiary_verification_report.checklist_item_id')
            //             ->on('beneficiary_verification_report.beneficiary_id', '=', DB::raw($girl_id));
            //     })->whereIn('checklist_items.id',[78,82]);

            $response_qry = DB::table('checklist_items')
                ->leftJoin('beneficiary_verification_report', 'checklist_items.id', '=',
                     'beneficiary_verification_report.checklist_item_id')
                ->select('checklist_items.*',
                    'beneficiary_verification_report.id as report_id',
                    'beneficiary_verification_report.response',
                    'beneficiary_verification_report.remark')
                ->whereIn('checklist_items.id', [78, 82])
                ->limit(2)->get();

            $record_qry = DB::table('beneficiary_information as t1')
                ->select('t1.id','t1.beneficiary_id','t1.master_id','t1.current_school_grade')
                ->where('t1.batch_id', '=', $batch_id)  
                ->where('t1.resp_inserted',' =', 0)
                ->whereIn('t1.beneficiary_status', [0,1])
                ->limit(5000);
            $master_ids_qry = clone $record_qry;
            $master_id_array = $master_ids_qry->pluck('t1.master_id')->toArray();
            $recordCount = $record_qry->get();

            if($recordCount->count() > 0) {
                $student_master_ben_ids = json_decode(
                    json_encode($recordCount->keyBy('master_id')->toArray()
                ),true);
                $checklist_item_min_id=76;
                $checklist_item_max_id=92;              
                //                    1  2  3  4  5  6  7  8  9  10 11 12 13 14 15 16 17
                $scope_field_ids =   [31,32,33,34,35,36,37,40,41,42,43,44,45,46,47,48,49]; //template field ids
                $checklist_items_ids=[76,77,78,79,80,81,82,83,84,85,86,87,88,89,90,91,92]; //checklist item ids
                $combo_response_checklist_item_ids=[76,77,78,79,83,87,91]; //combo checklist response ids
                $beneficiary_responses=array();
                // checklist_responses
                $res_ponse = DB::table('temp_additional_fields_values as t1')
                    ->select('t1.field_id','t1.value','t1.main_temp_id')
                    // ->where('t1.batch_id','=',$batch_id)
                    ->whereIn('t1.field_id',$scope_field_ids)
                    ->whereIn('t1.main_temp_id',$master_id_array)
                    ->get();
                $res_array = $res_ponse->toArray();
                $checklist_item_value_responses = json_decode(json_encode($res_array),true);  
                $data_to_insert = [];              
                foreach($student_master_ben_ids as $master_id=>$student) {
                    $add_resp=[];
                    $results=$this->_search_array_by_value($checklist_item_value_responses,'main_temp_id',$master_id);
                    $countResponses = 0;
                    foreach($results as $key=>$result) {
                        if(in_array( $checklist_items_ids[$key],$combo_response_checklist_item_ids)){
                            if(strtolower($result['value'])=="yes"){
                                $response_val=1;
                            }else if(strtolower($result['value'])=="no"){
                                $response_val=2;
                            } else {
                                $response_val=0;
                            }
                        }else if($checklist_items_ids[$key]==86){
                            if(Str::contains(strtolower($result['value']), 'schol')){
                                $response_val=17;
                            }else if(Str::contains(strtolower($result['value']), 'bo')){
                                if(Str::contains(strtolower($result['value']), 'week')){
                                    $response_val=18;
                                }else{
                                    $response_val=16;
                                }
                            }
                        }else if($checklist_items_ids[$key]==84){
                                $response_val=$student['current_school_grade'];
                        }else{
                            $response_val=$result['value'];
                        }
                        $beneficiary_responses[] = [
                            "beneficiary_id"=>$student['id'],
                            "checklist_item_id"=>$checklist_items_ids[$key],
                            "response"=>$response_val,
                            "created_at"=>Carbon::now(),
                            "created_by"=>$user_id
                        ];	                    		
                        $add_resp[$key] = $response_val;
                        $countResponses++;
                    }
                    if($countResponses < 16){
                        return response()->json(
                            array(
                                'success' => false,
                                'message' => 'Incorrect Checklist Configuration'
                            )
                        );
                    } else {
                        $beneficiary_responses = [];
                        $verification_responses = [
                            'girl_id'=>$student['id'],
                            'responses'=>''.$add_resp[0].','.$add_resp[1].','.$add_resp[2].','.$add_resp[3]
                            .','.$add_resp[4].','.$add_resp[5].','.$add_resp[6].','.$add_resp[7].','.$add_resp[8]
                            .','.$add_resp[9].','.$add_resp[10].','.$add_resp[11].','.$add_resp[12].','.$add_resp[13]
                            .','.$add_resp[14].','.$add_resp[15].','.$add_resp[16].'',
                            'question_ids'=>'76,77,78,79,80,81,82,83,84,85,86,87,88,89,90,91,92',
                            'orders'=>'1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17',
                            'remarks'=>',,,,,,,,,,,,,,,,',
                            'is_submit'=>2,
                            'created_at'=>Carbon::now(),
                            'created_by'=>$user_id
                        ];
                        $verification_response = $this->verificationOutSchlDetailsQuery($verification_responses);                                 
                        if($verification_response->getData()->success == true || $verification_response->getData()->success == 'true') {
                            $count++;
                        } else {
                            $params = array(
                                'girl_id' => $verification_response->getData()->girl_id, 
                                'message' => $verification_response->getData()->message,
                                'batch_id' => $batch_id, 
                                'checklist_item_id' => $verification_response->getData()->checklist_item_id,
                                'created_at'=>Carbon::now(),
                            );
                            DB::table('insert_response_failed')
                                ->insert($params);
                        }                          
                    }	
                }
                $res = array(
                    'success' => true,
                    'message' => $count.' Beneficiaries Verified Successfully'
                );

            } else {      
                $res = array(
                    'success' => true,
                    'message' => 'All Beneficiaries have been verified'
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

    public function verificationOutSchlDetailsQuery($req)
    {
        $responses = $req['responses'];
        $remarks = $req['remarks'];
        $question_ids = $req['question_ids'];
        $girl_id = $req['girl_id'];
        $orders = $req['orders'];
        // $responsesArray = json_decode($responses);
        $responsesArray = explode(',', $responses);
        $is_submit = 2;
        $beneficiary_status = 2;
        // if ($is_submit == 1) {
        //     $beneficiary_status = 3;
        // }
        $res = array();
        $remarksArray = explode(',', $remarks);
        $questionsArray = explode(',', $question_ids);
        $ordersArray = explode(',', $orders);
        $count = count($questionsArray);
        $bursaryType = array();
        $bursaryRegularity = array();
        $scholarshipPackage = array();
        $skip_matching = 0;
        // if($this->user_id == 30) {
        DB::transaction(function () use (&$res, $skip_matching, $beneficiary_status, 
        $responsesArray, $remarksArray, $questionsArray, $girl_id, $ordersArray, $count, 
        $bursaryType, $bursaryRegularity, $scholarshipPackage) {
            try {
                for ($i = 0; $i < $count; $i++) {
                    $question_id = $questionsArray[$i];
                    $params[] = array(
                        'checklist_item_id' => $question_id,
                        'beneficiary_id' => $girl_id,
                        'response' => $responsesArray[$i],
                        'remark' => $remarksArray[$i],
                        'created_at' => Carbon::now(),
                        'created_by' => \Auth::user()->id
                    );
                    //knock out questions
                    //Question 1 if NO means girl is out of school so category is 'NOT FOUND"                        
                    if ($ordersArray[$i] == 1) {
                        $quizOneResponse = $responsesArray[$i];
                        if ($quizOneResponse != '') {
                            if ($quizOneResponse == 2) {
                                DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                                DB::table('beneficiary_verification_report')->insert($params);
                                //end survey...update girl recommendation to NOT FOUND(out of school)
                                DB::table('beneficiary_information')
                                    ->where('id', $girl_id)->update(array(
                                        'verification_recommendation' => 3, 
                                        'beneficiary_status' => $beneficiary_status, 
                                        'resp_inserted' => 1
                                    ));
                                //exit here
                                $res = array(
                                    'success' => true,
                                    'girl_id' => $girl_id,
                                    'message' => 'Details saved successfully!!'
                                );
                                return response()->json($res);
                            }
                        } else {
                            $res = array(
                                'success' => false,
                                'girl_id' => $girl_id,
                                'checklist_item_id' => $question_id,
                                'message' => 'Please fill Question No. ' . $ordersArray[$i] . '!!'
                            );
                            return response()->json($res);
                        }
                    }
                    //Question 2 if NO means girl has never attended secondary school so category is 'NOT RECOMMENDED"
                    if ($ordersArray[$i] == 2) {
                        $quizTwoResponse = $responsesArray[$i];
                        if ($quizTwoResponse != '') {
                            if ($quizTwoResponse == 2) {
                                DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                                DB::table('beneficiary_verification_report')->insert($params);
                                //end survey...update girl recommendation to NOT RECOMMENDED
                                DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 2, 
                                'beneficiary_status' => $beneficiary_status));
                                //exit here
                                $res = array(
                                    'success' => true,
                                    'girl_id' => $girl_id,
                                    'message' => 'Details saved successfully!!'
                                );
                                return response()->json($res);
                            }
                        } else {
                            $res = array(
                                'success' => false,
                                'girl_id' => $girl_id,
                                'checklist_item_id' => $question_id,
                                'message' => 'Please fill Question No. ' . $ordersArray[$i] . '!!'
                            );
                            return response()->json($res);
                        }
                    }
                    //quiz 3
                    if ($ordersArray[$i] == 3) {
                        $quizThreeResponse = $responsesArray[$i];
                        if ($quizTwoResponse == 1) {//then quiz three is a must
                            if ($quizThreeResponse == '') {
                                $res = array(
                                    'success' => false,
                                    'girl_id' => $girl_id,
                                    'checklist_item_id' => $question_id,
                                    'message' => 'Please fill Question No. ' . $ordersArray[$i] . '!!'
                                );
                                return response()->json($res);
                            } else {
                                $skip_matching = 0;
                            }
                        } else {
                            //go to quiz four...
                        }
                    }
                    //Quiz 4
                    if ($ordersArray[$i] == 4) {
                        $quizFourResponse = $responsesArray[$i];
                        if ($quizThreeResponse == 2) {//then quiz four is a must
                            if ($quizFourResponse == '') {
                                $res = array(
                                    'success' => false,
                                    'girl_id' => $girl_id,
                                    'checklist_item_id' => $question_id,
                                    'message' => 'Please fill Question No. ' . $ordersArray[$i] . '!!'
                                );
                                return response()->json($res);
                            } else {
                                if ($quizFourResponse == 2) {
                                    DB::table('beneficiary_verification_report')
                                        ->where('beneficiary_id', $girl_id)->delete();
                                    DB::table('beneficiary_verification_report')->insert($params);
                                    //end survey...update girl recommendation to NOT RECOMMENDED
                                    DB::table('beneficiary_information')
                                    ->where('id', $girl_id)->update(array('verification_recommendation' => 2, 
                                    'beneficiary_status' => $beneficiary_status));
                                    //exit here
                                    $res = array(
                                        'success' => true,
                                        'girl_id' => $girl_id,
                                        'message' => 'Details saved successfully!!'
                                    );
                                    return response()->json($res);
                                }
                            }
                        } else {
                            //continue...
                        }
                    }
                    //quiz 5
                    if ($ordersArray[$i] == 5) {
                        $quizFiveResponse = $responsesArray[$i];
                        if ($quizFourResponse == 1) {//then quiz five is a must
                            if ($quizFiveResponse == '') {
                                $res = array(
                                    'success' => false,
                                    'girl_id' => $girl_id,
                                    'checklist_item_id' => $question_id,
                                    'message' => 'Please fill Question No. ' . $ordersArray[$i] . '!!'
                                );
                                return response()->json($res);
                            } else {
                                if ($quizFiveResponse < 1 || $quizFiveResponse > 12) {
                                    $res = array(
                                        'success' => false,
                                        'girl_id' => $girl_id,
                                        'checklist_item_id' => $question_id,
                                        'message' => 'Incorrect grade provided for question No. ' . $ordersArray[$i] . '. Please correct to proceed!!'
                                    );
                                    return response()->json($res);
                                } else {
                                    if ($quizFiveResponse < 8) {//Not Recommended
                                        DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                                        DB::table('beneficiary_verification_report')->insert($params);
                                        //end survey...update girl recommendation to NOT RECOMMENDED
                                        DB::table('beneficiary_information')->where('id', $girl_id)->update(array('highest_grade' => $quizFiveResponse, 
                                        'verification_recommendation' => 2, 'beneficiary_status' => $beneficiary_status));
                                        //exit here
                                        $res = array(
                                            'success' => true,
                                            'girl_id' => $girl_id,
                                            'message' => 'Details saved successfully!!'
                                        );
                                        return response()->json($res);
                                    } else {
                                        //continue
                                    }
                                    DB::table('beneficiary_information')->where('id', $girl_id)->update(array('highest_grade' => $quizFiveResponse));
                                }
                            }
                        } else {
                            //continue...
                        }
                    }
                    //quiz 7
                    if ($ordersArray[$i] == 7) {
                        $school = $responsesArray[$i];
                        if ($quizThreeResponse == 1 && $school == '') {
                            $res = array(
                                'success' => false,
                                'girl_id' => $girl_id,
                                'checklist_item_id' => $question_id,
                                'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                            );
                            return response()->json($res);
                        }
                    }
                    //quiz 9
                    if ($ordersArray[$i] == 9) {
                        $grade = $responsesArray[$i];
                        if ($quizThreeResponse == 1) {//then quiz nine is a must
                            if ($grade != '') {
                                //todo: comment out for 2021 verification
                                /*  $next_grade = ($grade + 1);
                                if ($grade == 12) {
                                    $next_grade = $grade;
                                }*/
                                //todo: end 2021 commented out
                                //added for 2021...no grade incrementation
                                $next_grade = $grade;
                                //end added for 2021
                                if ($grade < 1 || $grade > 12) {
                                    $res = array(
                                        'success' => false,
                                        'girl_id' => $girl_id,
                                        'checklist_item_id' => $question_id,
                                        'message' => 'Incorrect grade provided for question No. ' . $ordersArray[$i] . '. Please correct to proceed!!'
                                    );
                                    return response()->json($res);
                                } else {
                                    // if ($grade < 7) {//Not Recommended
                                    if ($grade < 4) {//Not Recommended
                                        DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                                        DB::table('beneficiary_verification_report')->insert($params);
                                        //end survey...update girl recommendation to NOT RECOMMENDED
                                        DB::table('beneficiary_information')->where('id', $girl_id)->update(array('current_school_grade' => $grade, 
                                        'exam_grade' => $grade, 'verification_recommendation' => 2, 'beneficiary_status' => $beneficiary_status));
                                        //exit here
                                        $res = array(
                                            'success' => true,
                                            'girl_id' => $girl_id,
                                            'message' => 'Details saved successfully!!'
                                        );
                                        return response()->json($res);
                                    }
                                    if ($grade == 7 || $grade == 9 || $grade == 12) {
                                        DB::table('beneficiary_information')->where('id', $girl_id)
                                        ->update(array('current_school_grade' => $grade, 'exam_grade' => $grade));
                                    } else {
                                        DB::table('beneficiary_information')->where('id', $girl_id)
                                        ->update(array('current_school_grade' => $next_grade, 'exam_grade' => $next_grade));
                                    }
                                }
                            } else {
                                $res = array(
                                    'success' => false,
                                    'girl_id' => $girl_id,
                                    'message' => 'Please enter grade for question No. ' . $ordersArray[$i] . ' !!'
                                );
                                return response()->json($res);
                            }
                        }
                    }
                    //quiz 10
                    if ($ordersArray[$i] == 10) {
                        $exam_number = $responsesArray[$i];
                        if (($grade == 9 || $grade == 7) && $exam_number == '') {
                            $res = array(
                                'success' => false,
                                'girl_id' => $girl_id,
                                'checklist_item_id' => $question_id,
                                'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                            );
                            // return response()->json($res);
                        } else {
                            if ($grade == 7) {
                                DB::table('beneficiary_information')->where('id', $girl_id)->update(array('grade7_exam_no' => $exam_number));
                            } else {
                                DB::table('beneficiary_information')->where('id', $girl_id)->update(array('grade9_exam_no' => $exam_number));
                            }
                        }
                    }
                    //quiz 11
                    if ($ordersArray[$i] == 11) {
                        $girl_school_status = $responsesArray[$i];
                        //[1,17]=day, [2,16]=boarder, [3,18]=weekly, [4,31]=unspecified
                        if ($grade > 3 && $grade < 13) {//if quiz 9 suffice then eleven is a must
                            if ($girl_school_status == '') {
                                $res = array(
                                    'success' => false,
                                    'girl_id' => $girl_id,
                                    'checklist_item_id' => $question_id,
                                    'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                                );
                                // return response()->json($res);
                            } else {
                                if ($girl_school_status == 17) {
                                    $foreign_id = 1;
                                } else if ($girl_school_status == 16) {
                                    $foreign_id = 2;
                                } else if ($girl_school_status == 18) {
                                    $foreign_id = 3;
                                } else if ($girl_school_status == 31) {
                                    $foreign_id = 4;
                                }
                                DB::table('beneficiary_information')->where('id', $girl_id)->update(array('beneficiary_school_status' => $foreign_id));
                            }
                        }
                    }
                    //quiz 12
                    if ($ordersArray[$i] == 12) {
                        $bursary_recipient = $responsesArray[$i];
                        if ($grade > 3 && $grade < 13) {//if quiz 9 suffice then twelve is a must
                            if ($bursary_recipient == '') {
                                $res = array(
                                    'success' => false,
                                    'girl_id' => $girl_id,
                                    'checklist_item_id' => $question_id,
                                    'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                                );
                                return response()->json($res);
                            } /*else {
                                $bursary_recipient = $response;
                            }*/
                        }
                    }
                    //quiz 13
                    if ($ordersArray[$i] == 13) {
                        $bursary_type = $responsesArray[$i];
                        $bursaryType['type'] = $responsesArray[$i];
                        if ($bursary_recipient == 1 && $bursary_type == '') {
                            $res = array(
                                'success' => false,
                                'girl_id' => $girl_id,
                                'checklist_item_id' => $question_id,
                                'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                            );
                            return response()->json($res);
                        }
                    }
                    //quiz 14
                    if ($ordersArray[$i] == 14) {
                        $scholarship_package = $responsesArray[$i];
                        $scholarshipPackage['package'] = $responsesArray[$i];
                        if ($bursary_type == 29 && $scholarship_package == '') {
                            $res = array(
                                'success' => false,
                                'girl_id' => $girl_id,
                                'checklist_item_id' => $question_id,
                                'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                            );
                            return response()->json($res);
                        }
                    }
                    //quiz 15
                    if ($ordersArray[$i] == 15) {
                        $bursary_regular = $responsesArray[$i];
                        $bursaryRegularity['regular'] = $responsesArray[$i];
                        if ($bursary_type == 29 && $scholarship_package == 24) {

                        } else {
                            if ($bursary_recipient == 1 && $bursary_regular == '') {
                                $res = array(
                                    'success' => false,
                                    'girl_id' => $girl_id,
                                    'checklist_item_id' => $question_id,
                                    'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                                );
                                return response()->json($res);
                            }
                        }
                    }
                    //quiz 16
                    if ($ordersArray[$i] == 16) {
                        $disability = $responsesArray[$i];
                        if ($quizFiveResponse > 6 || $grade > 3) {
                            if ($disability == '') {
                                $res = array(
                                    'success' => false,
                                    'girl_id' => $girl_id,
                                    'checklist_item_id' => $question_id,
                                    'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                                );
                                return response()->json($res);
                            }
                        }
                    }
                    //quiz 17
                    if ($ordersArray[$i] == 17) {
                        $disabilitiesArray = explode(',', $responsesArray[$i]);
                        $disabilitiesArray = array_filter($disabilitiesArray);
                        $count2 = count($disabilitiesArray);
                        if ($disability == 1) {
                            if ($count2 < 1) {
                                $res = array(
                                    'success' => false,
                                    'girl_id' => $girl_id,
                                    'checklist_item_id' => $question_id,
                                    'message' => 'Please fill response for question No. ' . $ordersArray[$i] . ' !!'
                                );
                                return response()->json($res);
                            } else {
                                for ($j = 0; $j < $count2; $j++) {
                                    $params2[] = array(
                                        'beneficiary_id' => $girl_id,
                                        'disability_id' => $disabilitiesArray[$j]
                                    );
                                }
                                DB::table('beneficiary_disabilities')->where(array('beneficiary_id' => $girl_id))->delete();
                                DB::table('beneficiary_disabilities')->insert($params2);
                            }
                        } else {
                            //just proceed...but clear any previously assigned disabilities
                            DB::table('beneficiary_disabilities')->where(array('beneficiary_id' => $girl_id))->delete();
                        }
                    }
                }
                if ($grade > 12) {//frank
                    DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                    DB::table('beneficiary_verification_report')->insert($params);
                    //knock out this girl
                    //update girl recommendation to NOT RECOMMENDED(not qualified)
                    DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 2, 'beneficiary_status' => $beneficiary_status));
                } else if ($bursaryType['type'] == 32) {
                    DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                    DB::table('beneficiary_verification_report')->insert($params);
                    //knock out this girl
                    //update girl recommendation to NOT RECOMMENDED(not qualified)
                    DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 2, 'beneficiary_status' => $beneficiary_status));
                } else if (($bursaryType['type'] == 25 || $bursaryType['type'] == 26 || $bursaryType['type'] == 27 || $bursaryType['type'] == 28) && $bursaryRegularity['regular'] == 1) {
                    DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                    DB::table('beneficiary_verification_report')->insert($params);
                    //knock out this girl
                    //update girl recommendation to NOT RECOMMENDED(not qualified)
                    DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 2, 'beneficiary_status' => $beneficiary_status));
                } else if ($bursaryType['type'] == 29 && $scholarshipPackage['package'] == 23 && $bursaryRegularity['regular'] == 1) {
                    DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                    DB::table('beneficiary_verification_report')->insert($params);
                    //knock out this girl
                    //update girl recommendation to NOT RECOMMENDED(not qualified)
                    DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 2, 'beneficiary_status' => $beneficiary_status));
                } else {
                    DB::table('beneficiary_verification_report')->where('beneficiary_id', $girl_id)->delete();
                    DB::table('beneficiary_verification_report')->insert($params);
                    //update girl recommendation to RECOMMENDED(qualified)
                    DB::table('beneficiary_information')->where('id', $girl_id)->update(array('verification_recommendation' => 1, 'beneficiary_status' => $beneficiary_status));
                }
                DB::table('beneficiary_information')->where('id', $girl_id)->update(array('skip_matching' => $skip_matching));
                if ($quizThreeResponse == 1 && $school != '') {//update beneficiary school and category
                    DB::table('beneficiary_information')->where('id', $girl_id)->update(array('school_id' => $school, 'exam_school_id' => $school));//, 'category' => 2));
                }
                $res = array(
                    'success' => true,
                    'girl_id' => $girl_id,
                    'message' => 'Details saved successfully!!'
                );
            } catch (\Exception $exception) {
                $res = array(
                    'success' => false,
                    'girl_id' => 0,
                    'message' => $exception->getMessage()
                );
            } catch (\Throwable $throwable) {
                $res = array(
                    'success' => false,
                    'girl_id' => 0,
                    'message' => $throwable->getMessage()
                );
            }
        }, 5);
        return response()->json($res);
    }
    
    public function recheckSchoolInfo(Request $request)
    {//called after mapping
        try {
            //check for school details {only for in school}  
            $category_id = $request->input('category_id');
            $batch_id = $request->input('batch_id');
            $results = json_decode(//get records with empty school ids
                json_encode(
                    DB::table('beneficiary_information as t1')
                    ->join('beneficiary_master_info as t2','t1.master_id','=','t2.id')
                    ->select('t1.id','t2.school_code')
                    // ->where('t1.batch_id', $batch_id)
                    ->where('t1.batch_id', 80)
                    ->whereNull('t1.school_id')
                    ->get()->toArray()
                ),true
            );
            $count = 0;
            foreach($results as $key => $result) {
                $school_id = $this->getSchoolID($result['school_code']);                
                if ($school_id === false || $school_id == 0) {
                } else {
                    $update_response = DB::table('beneficiary_information')
                        ->where('id', $result['id'])
                        ->update(array('school_id' => $school_id));
                    $count++;
                }                           
            } 
            $res = array(
                'success' => true,
                'message' => 'Request executed successfully!!<br> ' . 
                $count . ' Records Updated'
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
    
    // public function recheckSchoolInfo(Request $request)
    // {//called after mapping
    //     try {//franko
    //         //check for school details {only for in school}  
    //         $category_id = $request->input('category_id');
    //         $batch_id = $request->input('batch_id');       
    //         // if($this->user_id == 30) {   
    //             $results = json_decode(//get records with empty school ids
    //                 json_encode(
    //                     DB::table('beneficiary_information as t1')
    //                     ->join('beneficiary_master_info as t2', 
    //                         't1.master_id', '=', 't2.id')
    //                     ->select('t1.id','t2.school_code')
    //                     // ->where('t1.batch_id', $batch_id)
    //                     ->where('t1.batch_id', 80)
    //                     // ->whereIn('t1.batch_id', [79,80,81,82,83])
    //                     // ->where('t1.school_id', 0)
    //                     ->whereNull('t1.school_id')
    //                     ->get()->toArray()
    //                 ),true
    //             );
    //             dd($results);
    //             $count = 0;
    //             foreach($results as $key => $result){
    //                 $school_id = $this->getSchoolID($result['school_code']);                
    //                 if ($school_id === false || $school_id == 0) {
    //                 } else {
    //                     $update_response = DB::table('beneficiary_information')
    //                         ->where('id', $result['id'])
    //                         ->update(array('school_id' => $school_id));
    //                     $count++;
    //                 }                           
    //             } 
    //             $res = array(
    //                 'success' => true,
    //                 'message' => 'Request executed successfully!!<br> ' . $count . ' Records Updated'
    //             );
    //         // } else {
    //         //     $res = array(
    //         //         'success' => false,
    //         //         'message' => 'You are not allowed to perform this action'
    //         //     );
    //         // }
    //     } catch (\Exception $exception) {
    //         $res = array(
    //             'success' => false,
    //             'message' => $exception->getMessage()
    //         );
    //     } catch (\Throwable $throwable) {
    //         $res = array(
    //             'success' => false,
    //             'message' => $throwable->getMessage()
    //         );
    //     }
    //     return response()->json($res);
    // }

    public function recheckExamGradesInfo(Request $request)
    {
        try {
            //check for school details {only for in school exam grades 7 & 9}  
            $category_id = $request->input('category_id');
            $batch_id = $request->input('batch_id');
            if($this->user_id == 30) {
                $results = json_decode(
                    json_encode(
                        DB::table('beneficiary_information as t1')
                            ->select('t1.id','t1.school_id','t1.exam_school_id')
                            ->where('t1.batch_id', $batch_id)
                            ->where('exam_school_id', 0)
                            // ->whereIn('t1.current_school_grade', [7,9])
                            ->where('t1.current_school_grade', 7)
                            ->get()->toArray()
                    ),true
                );
                $count = 0;
                foreach($results as $key => $result) {
                    $update_response_one = DB::table('beneficiary_information')
                        ->where('id', $result['id'])
                        ->update(array('category' => 3));					
                    if($result['exam_school_id'] == 0 || $result['exam_school_id'] == ''){
                        $update_response_two = DB::table('beneficiary_information')
                            ->where('id', $result['id'])
                            ->where('exam_school_id', 0)
                            ->update(array('exam_school_id' => $result['school_id']));						
                    }
                    $count++;
                }
                //get checked summary
                $res = array(
                    'success' => true,
                    'count' => $count,
                    'message' => 'Request executed successfully!!<br> ' . $count . ' Records Updated'
                );
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'You are not allowed to perform this action'
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

    public function recheckGradeEightandNines(Request $request)
    {//Move grade 9s to school placement
        try{
            // $category_id = $request->input('category_id');
            // $batch_id = $request->input('batch_id');
            // $user_id = $this->user_id;			
            // $student_master_ben_ids = DB::table('beneficiary_information as t1')
			// 	->leftJoin('temp_additional_fields_values as t2', 't1.master_id', '=', 't2.main_temp_id')
			// 	->selectRaw('t2.value,t1.id,t1.master_id,t1.category,t1.beneficiary_status')
			// 	->where('t1.batch_id',$batch_id)
			// 	->where('t2.field_id',41)
			// 	// ->where('t2.field_id',43)
			// 	->where('t1.category',3)
			// 	->where('t2.value',9)
            //     ->get();		
            // $beneficiary_responses=array();
            $count = 0;
            // foreach($student_master_ben_ids as $student){
			// 	$update_response_two = DB::table('beneficiary_information')
			// 			->where('id', $student->id)
			// 			->update(array('beneficiary_status' => 8));
            //     $count++;
            // }
            $res = array(
                'success' => true,
                'count' => $count,
                'message' => 'Request executed successfully!!<br> ' . $count . ' Records Updated'
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
	
    public function submitSpecialGradeTwelveToLetterGen(Request $request)
    {        
        try {  
            $category_id = $request->input('category_id');
            $batch_id = $request->input('batch_id');
            if($this->user_id == 30) {
                $student_master_ben_ids = DB::table('beneficiary_information as t1')
                    ->select('t1.id')
                    ->where('t1.batch_id',$batch_id)
                    ->where('t1.verification_recommendation',2)
                    // ->where('t1.category',2)
                    ->where('t1.current_school_grade',12)
                    // ->where('t1.beneficiary_status',6)
                    ->get();
                $count = 0;                
                foreach($student_master_ben_ids as $student){
                    DB::table('beneficiary_information')
                        ->where('id', $student->id)
                        ->update(
                            array(
                                'beneficiary_status' => 4, 
                                'enrollment_date' => Carbon::now(), 
                                'enrollment_status' => 1, 
                                'verification_recommendation' => 1
                            )
                        );
                    $count++;
                }
                $res = array(
                    'success' => true,
                    'count' => $count,
                    'message' => 'Request executed successfully!!<br> ' . $count . ' Records Updated'
                );
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'You are not allowed to perform this action'
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
    
    // public function manualPromotionProcess()
    // {
    //     $year = date('Y') - 1;
    //     $description = 'Beneficiary Grade Promotions for the Year ' . $year;
    //     $meta_params = array(
    //         'year' => $year,
    //         'description' => $description,
    //         'created_at' => Carbon::now()
    //     );
    //     $log_data = array(
    //         'process_type' => 'Beneficiary Grade Annual Promotions',
    //         'process_description' => 'Annual Beneficiaries Grade Promotion',//$this->description,
    //         'created_at' => Carbon::now()
    //     );
    //     $checker = DB::table('ben_annual_promotions')
    //         ->where('year', $year)
    //         ->count();
    //     if ($checker > 0) {
    //         $log_data['status'] = 'Failed';
    //         $log_data['failure_reason'] = 'Found another promotion entry for ' . $year;
    //         DB::table('auto_processes_logs')
    //             ->insert($log_data);
    //         print_r('Status: Failed');
    //         print_r('');
    //         print_r('Message: Found another promotion entry for ' . $year);
    //         exit();
    //     }
    //      //check for a missed year
    //     $max_year = DB::table('ben_annual_promotions')->max('year');
    //     $next_year = ($max_year + 1);
    //     if ($next_year != $year) {
    //         print_r('failed year mismatch');
    //         exit();
    //         $log_data['status'] = 'Failed';
    //         $log_data['failure_reason'] = 'Promotion should be for the year ' . $next_year . ', but trying to do promotion for ' . $year;
    //         DB::table('auto_processes_logs')
    //             ->insert($log_data);
    //         print_r('Status: Failed');
    //         print_r('');
    //         print_r('Message: Promotion should be for the year ' . $next_year . ', but trying to do promotion for ' . $year);
    //         exit();
    //     }
    //     DB::transaction(function () use ($meta_params, $log_data, $year) {
    //         try {
    //             $prev_year = $year - 1;
    //             $promotion_id = DB::table('ben_annual_promotions')->insertGetId($meta_params);
    //             //gradeNines for Promotion
    //             $where = array(
    //                 'current_school_grade' => 9,
    //                 'enrollment_status' => 1,
    //                 'under_promotion' => 0
    //             );
    //             $grade_nines_main_qry = DB::table('beneficiary_information')
    //                 ->where($where);

    //             $grade_nines_qry = clone $grade_nines_main_qry;
    //             $grade_nines_qry->select(DB::raw("id as girl_id,$prev_year as prev_year,$year as promotion_year,'MIS Auto' as created_by"));
    //             $grade_nines = $grade_nines_qry->get();
    //             $grade_nines = convertStdClassObjToArray($grade_nines);
    //             $size = 100;
    //             $grade_nines_chunks = array_chunk($grade_nines, $size);
    //             foreach ($grade_nines_chunks as $grade_nines_chunk) {
    //                 DB::table('grade_nines_for_promotion')->insert($grade_nines_chunk);
    //             }

    //             $update_params = array(
    //                 'under_promotion' => 1,
    //                 'promotion_year' => $year
    //             );
    //             $grade_nines_update_qry = clone $grade_nines_main_qry;
    //             $grade_nines_update_qry->update($update_params);

    //             $promotion_data = DB::table('beneficiary_information')
    //                 ->select(DB::raw("id as girl_id,current_school_grade as from_grade,current_school_grade+1 as to_grade,school_id,$promotion_id as promotion_id"))
    //                 ->where('enrollment_status', 1)
    //                 ->whereIn('current_school_grade', array(8, 10, 11))
    //                 ->get();
    //             $promotion_data = convertStdClassObjToArray($promotion_data);

    //             $grade_log_data = DB::table('beneficiary_information')
    //                 ->select(DB::raw("id as girl_id,current_school_grade+1 as grade,school_id,$year as year"))
    //                 ->where('enrollment_status', 1)
    //                 ->whereIn('current_school_grade', array(8, 10, 11))
    //                 ->get();
    //             $grade_log_data = convertStdClassObjToArray($grade_log_data);

    //             DB::table('beneficiary_information')
    //                 ->where('enrollment_status', 1)
    //                 ->where('current_school_grade', 12)
    //                 ->update(array('enrollment_status' => 4));
    //             DB::table('beneficiary_information')
    //                 ->where('enrollment_status', 1)
    //                 ->whereIn('current_school_grade', array(8, 10, 11))
    //                 ->update(array('current_school_grade' => DB::raw('current_school_grade+1'), 'last_annual_promo_date' => DB::raw('NOW()')));

    //             $promotion_chunks = array_chunk($promotion_data, $size);
    //             foreach ($promotion_chunks as $promotion_chunk) {
    //                 DB::table('ben_annual_promotion_details')->insert($promotion_chunk);
    //             }
    //             $grade_log_chunks = array_chunk($grade_log_data, $size);
    //             foreach ($grade_log_chunks as $grade_log_chunk) {
    //                 DB::table('beneficiary_grade_logs')->insert($grade_log_chunk);
    //             }

    //             $log_data['status'] = 'Successful';
    //             DB::table('auto_processes_logs')
    //                 ->insert($log_data);
    //             print_r('Status: Successful');
    //             print_r('');
    //             print_r('Message: Promotion for ' . $year . ' executed successfully');
    //         } catch (\Exception $e) {
    //             $log_data['status'] = 'Failed';
    //             $log_data['failure_reason'] = $e->getMessage();
    //             DB::table('auto_processes_logs')
    //                 ->insert($log_data);
    //             print_r('Status: Failed');
    //             print_r('');
    //             print_r('Message: ' . $e->getMessage());
    //         } catch (\Throwable $throwable) {
    //             $log_data['status'] = 'Failed';
    //             $log_data['failure_reason'] = $throwable->getMessage();
    //             DB::table('auto_processes_logs')
    //                 ->insert($log_data);
    //             print_r('Status: Failed');
    //             print_r('');
    //             print_r('Message: ' . $throwable->getMessage());
    //         }
    //     }, 5);
    //     return;
    // }

    public function manualPromotionProcess()
    {
        // $year = date('Y') - 1;
        $year = 2025;//$initiail_year - 1;
        $description = 'Beneficiary Grade Promotions for the Year ' . $year;
        $meta_params = array(
            'year' => $year,
            'description' => $description,
            'created_at' => Carbon::now()
        );
        $log_data = array(
            'process_type' => 'Beneficiary Grade Annual Promotions',
            'process_description' => 'Annual Beneficiaries Grade Promotion',
            'created_at' => Carbon::now()
        );
        $checker = DB::table('ben_annual_promotions')
            ->where('year', $year)
            ->count();
        if ($checker > 0) {
            $log_data['status'] = 'Failed';
            $log_data['failure_reason'] = 'Found another promotion entry for ' . $year;
            DB::table('auto_processes_logs')
                ->insert($log_data);
            print_r('Status: Failed');
            print_r('');
            print_r('Message: Found another promotion entry for ' . $year);
            exit();
        }
         //check for a missed year
        $max_year = DB::table('ben_annual_promotions')->max('year');
        $next_year = ($max_year + 1);
        if ($next_year != $year) {
            print_r('failed year mismatch');
            // exit();
            $log_data['status'] = 'Failed';
            $log_data['failure_reason'] = 'Promotion should be for the year ' . $next_year . ', but trying to do promotion for ' . $year;
            DB::table('auto_processes_logs')->insert($log_data);
            print_r('Status: Failed');
            print_r('');
            print_r('Message: Promotion should be for the year ' . $next_year . ', but trying to do promotion for ' . $year);
            exit();
        }
        DB::transaction(function () use ($meta_params, $log_data, $year) {
            try {
                $prev_year = $year - 1;
                $promotion_id = DB::table('ben_annual_promotions')->insertGetId($meta_params);
                //gradeNines for Promotion
                $where = array(
                    'enrollment_status' => 1,
                    'under_promotion' => 0
                );
                $grade_nines_main_qry = DB::table('beneficiary_information')
                ->where($where)                
                ->whereIn('current_school_grade', [7, 9]);

                $grade_nines_qry = clone $grade_nines_main_qry;
                $grade_nines_qry->select(DB::raw("id as girl_id,$prev_year as prev_year,$year as promotion_year,'MIS Auto' as created_by"));
                $grade_nines = $grade_nines_qry->get();
                $grade_nines = convertStdClassObjToArray($grade_nines);
                $size = 100;
                $grade_nines_chunks = array_chunk($grade_nines, $size);

                foreach ($grade_nines_chunks as $grade_nines_chunk) {
                    DB::table('grade_nines_for_promotion')->insert($grade_nines_chunk);
                }

                $update_params = array(
                    'under_promotion' => 1,
                    'promotion_year' => $year
                );
                $grade_nines_update_qry = clone $grade_nines_main_qry;
                $grade_nines_update_qry->update($update_params);

                $promotion_data = DB::table('beneficiary_information')
                    ->select(DB::raw("id as girl_id,current_school_grade as from_grade,current_school_grade+1 as to_grade,school_id,$promotion_id as promotion_id"))
                    ->where('enrollment_status', 1)
                    ->whereIn('current_school_grade', array(4, 5, 6, 8, 10, 11))
                    ->get();
                $promotion_data = convertStdClassObjToArray($promotion_data);

                $grade_log_data = DB::table('beneficiary_information')
                    ->select(DB::raw("id as girl_id,current_school_grade+1 as grade,school_id,$year as year"))
                    ->where('enrollment_status', 1)
                    // ->whereIn('current_school_grade', array(8, 10, 11))
                    ->whereIn('current_school_grade', array(4, 5, 6, 8, 10, 11))
                    ->get();
                $grade_log_data = convertStdClassObjToArray($grade_log_data);

                DB::table('beneficiary_information')
                    ->where('enrollment_status', 1)
                    ->where('current_school_grade', 12)
                    ->update(array('enrollment_status' => 4));
                DB::table('beneficiary_information')
                    ->where('enrollment_status', 1)
                    ->whereIn('current_school_grade', array(4, 5, 6, 8, 10, 11))
                    ->update(array(
                        'current_school_grade' => DB::raw('current_school_grade+1'), 
                        'last_annual_promo_date' => DB::raw('NOW()')
                    ));
                $promotion_chunks = array_chunk($promotion_data, $size);

                foreach ($promotion_chunks as $promotion_chunk) {
                    DB::table('ben_annual_promotion_details')->insert($promotion_chunk);
                }                
                $grade_log_chunks = array_chunk($grade_log_data, $size);

                foreach ($grade_log_chunks as $grade_log_chunk) {
                    DB::table('beneficiary_grade_logs')->insert($grade_log_chunk);
                }

                $log_data['status'] = 'Successful';
                DB::table('auto_processes_logs')->insert($log_data);
                print_r('Status: Successful');
                print_r('');
                print_r('Message: Promotion for ' . $year . ' executed successfully');
            } catch (\Exception $e) {
                $log_data['status'] = 'Failed';
                $log_data['failure_reason'] = $e->getMessage();
                DB::table('auto_processes_logs')
                    ->insert($log_data);
                print_r('Status: Failed');
                print_r('');
                print_r('Message: ' . $e->getMessage());
            } catch (\Throwable $throwable) {
                $log_data['status'] = 'Failed';
                $log_data['failure_reason'] = $throwable->getMessage();
                DB::table('auto_processes_logs')
                    ->insert($log_data);
                print_r('Status: Failed');
                print_r('');
                print_r('Message: ' . $throwable->getMessage());
            }
        }, 5);
        return;
    }

    public function manualPromotionProcessRollBack()
    {
        $initiail_year = date('Y');
        $year = $initiail_year - 1;
        $description = 'Beneficiary Grade Promotions Roll Back for the Year ' . $year;
        $meta_params = array(
            'year' => $year,
            'description' => $description,
            'created_at' => Carbon::now()
        );
        $log_data = array(
            'process_type' => 'Beneficiary Grade Annual Promotions RollBack',
            'process_description' => 'Annual Beneficiaries Grade Promotion RollBack',//$this->description,
            'created_at' => Carbon::now()
        );
        
        $max_year = DB::table('ben_annual_promotions')->max('year');
        $next_year = ($max_year + 1);
        DB::transaction(function () use ($meta_params, $log_data, $year) {
            try {
                $latest_date = DB::table('ben_annual_promotions')->orderBy('id','DESC')->first();
                $promotion_created_date = $latest_date->created_at;
                $prev_year = $year - 1;
                $promotion_id = 4;
                //gradeNines for Promotion
                $whereOne = array(
                    'current_school_grade' => 9,
                    'enrollment_status' => 1,
                    'under_promotion' => 1
                );
                $previous_grade_nines_main_qry = DB::table('beneficiary_information')->where($whereOne);                
                $where = array(
                    'current_school_grade' => 9,
                    'enrollment_status' => 1,
                    'under_promotion' => 0
                );
                // $new_grade_nines_main_qry = DB::table('beneficiary_information')->where($where);

                $grade_nines_qry = clone $previous_grade_nines_main_qry;

                //rolls back all promotions
                DB::table('ben_annual_promotion_details as t1')
                    ->join('beneficiary_information as t2', 't2.id', '=', 't1.girl_id')
                    ->where('t1.promotion_id', 4)
                    ->update(['t2.current_school_grade' => DB::raw('t1.from_grade')]);
                
                //rolls back grade 12s promotions
                $res1 = DB::table('temp_beneficiary_information as t1')
                    ->join('beneficiary_information as t2', 't2.id', '=', 't1.girl_id')
                    ->where('t2.enrollment_status', 4)
                    ->where('t2.current_school_grade', 12)
                    ->update(array('t2.enrollment_status' => 1));
                $delete_where = array(
                    'promotion_year' => $year,
                    'prev_year' => $prev_year
                );
                $res2 = DB::table('grade_nines_for_promotion')->where($delete_where)->delete();
                $update_params = array(
                    'under_promotion' => 0,
                    'promotion_year' => $prev_year
                );
                $grade_nines_update_qry = clone $previous_grade_nines_main_qry;
                $grade_nines_update_qry->update($update_params);

                $res3 = DB::table('ben_annual_promotion_details')->where('created_at','>=',$promotion_created_date)->delete();
                print_r($res1);
                print_r($res2);
                print_r($res3);
                print_r('Status: Successful');
                print_r('');
                print_r('Message: Promotion Rollback for ' . $year . ' executed successfully');
            } catch (\Exception $e) {
                print_r('Status: Failed');
                print_r('');
                print_r('Message: ' . $e->getMessage());
            } catch (\Throwable $throwable) {
                print_r('Status: Failed');
                print_r('');
                print_r('Message: ' . $throwable->getMessage());
            }
        }, 5);
        return;
    }

}
