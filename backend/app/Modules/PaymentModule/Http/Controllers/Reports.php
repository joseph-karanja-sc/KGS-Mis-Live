<?php


namespace App\Modules\PaymentModule\Http\Controllers;

use App\Exports\PaymentScheduleExport;
use App\Exports\PaymentScheduleExportDisbursed;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use PDF;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Database\Query\Builder;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

Builder::macro('if', function ($condition, $column, $operator, $value) {
    if ($condition) {
        return $this->where($column, $operator, $value);
    }

    return $this;
});

class Reports extends Controller
{
    function formatSignatory($text = '')
    {
        if ($text == '') {
            $text = ".....................";
        }
        return $text;
    }

    //generate the excel details
    public function paymentScheduleForNonDisbursed(Request $req)
    {
        $payment_request_id = $req->input('payment_request_id');
        $school_id = $req->input('school_id');
        //payment request
        $details_info = DB::table('payment_request_details')
            ->where('id', $payment_request_id)
            ->first();
        $results = $this->getPaymentDetails($payment_request_id, $school_id);
        return Excel::download(new PaymentScheduleExport($results, $details_info), $details_info->payment_year . '_PaymentSchedule.xls');
    }




    public function postPaymentsQueryToPayGateway($auth_cookie)//check on a payment status
    {
        // $url = 'http://41.175.18.172:8080/sps/api/zispis/dev/kgs/query/8d7bd744-28b8-42da-b4dc-005f20b5c54f/8e1097d4-d95c-49f9-9666-6d026e029224';
        // $url = 'http://41.175.18.172:8080/sps/api/zispis/dev/kgs/query/8e1097d4-d95c-49f9-9666-6d026e029224';
        $baseUrl=env('PG_URL');
        $url="{baseUrl}/sps/api/zispis/dev/kgs/query/8e1097d4-d95c-49f9-9666-6d026e029224";
        $username=env('PG_USERNAME');
        $password=env('PG_PASSWORD');
        $apikey=env('PG_APIKEY');

        try {
            // Initialize the Guzzle client
            $client = new Client();
            // Make the GET request
            $response = $client->request('GET', $url, [
                'headers' => [
                    'User-Agent' => 'vscode-restclient',
                    'X-APIKey' => $apikey,
                    'Content-Type' => 'application/json',
                    'accept-encoding' => 'gzip, deflate',
                    'cookie' => 'session='.$auth_cookie,
                ]
            ]);
            return $response;
            // Return successful response
            // return response()->json([
            //     'status' => 'success',
            //     'data' => json_decode($response->getBody(), true),
            // ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }



    public function postSinglePaymentToGateway(Request $req)
    {
        $baseUrl=env('PG_URL');
        $username=env('PG_USERNAME');
        $password=env('PG_PASSWORD');
        $apikey=env('PG_APIKEY');
        try {
            $records = $req->input('records');
            $record_details = json_decode($records, true);
            $uuid = Uuid::uuid4()->toString();
            $TransactionDate = Carbon::now()->format('Y-m-d\TH:i:s');            
            $school_id = $record_details['school_id'];         
            $school_fees = $record_details['school_feessummary'];
            $no_of_girls = $record_details['no_of_beneficiary'];
            $getOtherDetails = DB::table('school_information as t1')
                ->leftJoin('wards as t2', 't1.constituency_id', '=', 't2.constituency_id')
                ->leftJoin('cwac as t3', 't1.cwac_id', '=', 't3.id')
                ->leftJoin('districts as t4', 't1.district_id', '=', 't4.id')
                ->leftJoin('school_bankinformation as t5', 't1.id', '=', 't5.school_id')
                ->leftJoin('provinces as t6', 't1.province_id', '=', 't6.id')
                ->where(array('t1.id' => $school_id))
                ->selectRaw('t2.id as ward_id,t3.id as cwac_id,t1.code,t1.name as school_name,t6.name as province_name,
                    t2.name AS ward_name,t3.name AS cwac_name,t4.name AS district_name,
                    t4.id as district_id,t5.sort_code,decrypt(t5.account_no) as account_no')
                ->first();
                // dd($getOtherDetails);
            $ward_name = $getOtherDetails->ward_name ? $getOtherDetails->ward_name : "";
            $cwac_name = $getOtherDetails->cwac_name;
            $ward_id = $getOtherDetails->ward_id ? $getOtherDetails->ward_id : 0;
            $cwac_id = $getOtherDetails->cwac_id ? $getOtherDetails->cwac_id : 0;
            $district_id = $getOtherDetails->district_id;
            $school_district = $getOtherDetails->district_name;
            $school_province = $getOtherDetails->province_name;
            $sort_code = $getOtherDetails->sort_code;
            $school_code = $getOtherDetails->code;
            $account_no = $getOtherDetails->account_no;
            $school_name = $getOtherDetails->school_name;
            
            $household_id = 0;
            $hhh_nrc_number = "117919/92/1";
            // $str_length = strlen($beneficiary_id) + 1;
            // $str = Str::substr($uuid, 0, -$str_length);
            // $TransactionID = "KGS-TID-$uuid";
            $TransactionID = (string) $uuid;
            $RecipientID = (string) $uuid;
            // $url = "http://41.175.18.172:8080/sps/api/zispis/dev/kgs/payment/$TransactionID";    
            $url="{$baseUrl}/sps/api/zispis/dev/kgs/payment/{$TransactionID}";        
            $get_cookie = DB::table('payments_trans')
                ->orderBy('id', 'desc')
                ->value('auth_cookie');
            $auth_cookie = preg_match('/=(.*?);/', $get_cookie, $matches) ? $matches[1] : null;       
            $headers = [
                "User-Agent" => "vscode-restclient",
                "X-APIKey" => $apikey,
                "Content-Type" => "application/json",
                "accept-encoding" => "gzip, deflate",
                "cookie" => "session=$auth_cookie"
            ]; 
            // $household_id2 = self::postPaymentsQueryToPayGateway($auth_cookie);            
            $school_fees ? $school_fees : 0;
            $cwac_name ? $cwac_name : "";
            $dob = "";
            $TransID = "$TransactionID";
            $RecipID = "$RecipientID";
            $TransDate = "$TransactionDate";
            $MobileNumber = "260978166385";
            $TransactionType = "Grant";
            $data = [
                "TransactionID" => $TransID,                "TransactionDate" => $TransDate,
                "RecipientID" => $RecipID,                  "RecipientType" => "Beneficiary",
                "Gender" => "Female",                       "FirstName" => $school_code,
                "LastName" => $school_name,                   "MobileNumber" => $MobileNumber,
                "Language" => "English",                    "LanguageCode" => "eng",
                "Country" => "ZM",                          "PSP" => "Zanaco",
                "Province" => $school_province,             "District" => $school_district,
                "Ward" => $ward_name,                       "CWAC" => $cwac_name,
                "RegisteredTown" => "",                     "DistrictID" => $district_id,
                "WardID" => $ward_id,                       "CWACID" => $cwac_id,
                "HouseholdID" => $household_id,             "NRC" => $hhh_nrc_number,
                "DateOfBirth" => $dob,                      "AccountNumber" => $account_no,
                "AccountExtra" => $sort_code,               "Currency" => "ZMW",
                "TransactionType" => $TransactionType,      "Amount" => $school_fees,
                "GPSAccuracy" => 0,                         "GPSAltitude" => 0,
                "GPSLatitude" => 0,                         "GPSLongitude" => 0,
                "PaymentReference" => "[UNDELIVERED][UNDELIVERED][UNDELIVERED]Manual payment",
                "PaymentCycle" => "January - February 2024 SCT Payment Cycle"                
            ];
            $insert_data = $data;
            $insert_data['school_id'] = $school_id;
            $insert_data['no_of_girls'] = $no_of_girls;

            $client = new Client();
            $response = $client->post($url, [
                'headers' => $headers,
                'json' => $data
            ]);
            
            $statusCode = $response->getStatusCode();
            $headers = $response->getHeaders();
            $body = $response->getBody()->getContents();
            $responses = [
                "status_code" => $statusCode,
                "response_body" => $body                          
            ];

            DB::table('payments_pg_responses')
                ->insert($responses);
            if ($statusCode == 200) {
                $insert_data['trans_status'] = 1;
                DB::table('payments_pg_transactions')
                    ->insert($insert_data);
                return response()->json([
                    'success' => true,
                    'message' => 'Payment Request Submitted Successfully'
                ]);
            } else {
                DB::table('payments_pg_transactions')
                    ->insert($insert_data);
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
        } catch (\Exception $e) {
            $errorString = $e->getMessage();
            $statusCode = null;
            $str_response = null;
            $cleanedString = null;
            $result = [];
            $result['reponse_string'] = $errorString;
            if (preg_match('/`(POST|GET|PUT|DELETE|PATCH)\s(.*?)`/', $errorString, $matches)) {
                // $result['method'] = $matches[1];
                $result['request_url'] = $matches[2]; 
            }        
            if (preg_match('/`(\d{3}\s\w+)`/', $errorString, $matches)) {
                $result['error_code'] = $matches[1];
                $statusCode = $matches[1];
            }    
            $startPos = strpos($errorString, 'response:'); 
            if ($startPos !== false) {
                $str_response = trim(substr($errorString, $startPos + 9));
            }
            $str_response .= "\}";
            $cleanedString = str_replace(['\\', '(truncated...)'], '', $str_response);  
            if (substr(trim($cleanedString), -1) !== '}') {
                $cleanedString .= '}';
            }
            $cleanedString2 = str_replace(['\\', '"'], '', $cleanedString);
            $result['success_status'] = 0;
            $result['return_msg'] = $cleanedString2;
            DB::table('payments_trans_failed')
                ->insert($result);
            return response()->json([
                'success' => false,
                'message' => $result
            ], 500);
        }
    }
        
    public function postOnePaymentToGateway($school_info)
    {
        try {
            $uuid = Uuid::uuid4()->toString();
            // $str_length = strlen('KGS-TID-') + 1;            
            // $str = Str::substr($uuid, 0, 8);
            // $str2 = Str::substr($uuid, 0, -8);
            // $str1 = $str . '+++' . $uuid . '+++' . $str2;
            $str = Str::after($uuid, '-');
            $TransactionID = "KGSTRIDT-$str";
            $RecipientID = $uuid;
            $TransactionDate = Carbon::now()->format('Y-m-d\TH:i:s');            
            $school_id = $school_info->school_id;
            $school_fees = $school_info->school_feessummary;
            $no_of_girls = $school_info->no_of_beneficiary;
            $getOtherDetails = DB::table('school_information as t1')
                ->leftJoin('wards as t2', 't1.constituency_id', '=', 't2.constituency_id')
                ->leftJoin('cwac as t3', 't1.cwac_id', '=', 't3.id')
                ->leftJoin('districts as t4', 't1.district_id', '=', 't4.id')
                ->leftJoin('school_bankinformation as t5', 't1.id', '=', 't5.school_id')
                ->leftJoin('provinces as t6', 't1.province_id', '=', 't6.id')
                ->where(array('t1.id' => $school_id))
                ->selectRaw("t2.id as ward_id,t3.id as cwac_id,t1.code,t1.name as school_name,t6.name as province_name,
                    t2.name AS ward_name,t3.name AS cwac_name,t4.name AS district_name,
                    t4.id as district_id,t5.sort_code,decrypt(t5.account_no) as account_no")
                ->first();
            $ward_name = $getOtherDetails->ward_name ? $getOtherDetails->ward_name : "";
            $cwac_name = $getOtherDetails->cwac_name;
            $ward_id = $getOtherDetails->ward_id ? $getOtherDetails->ward_id : 0;
            $cwac_id = $getOtherDetails->cwac_id ? $getOtherDetails->cwac_id : 0;
            $district_id = $getOtherDetails->district_id;
            $school_district = $getOtherDetails->district_name;
            $school_province = $getOtherDetails->province_name;
            $sort_code = $getOtherDetails->sort_code;
            $school_code = $getOtherDetails->code;
            $account_no = $getOtherDetails->account_no;
            $school_name = $getOtherDetails->school_name;
            
            $household_id = 0;
            // $hhh_nrc_number = "117919/92/1";
            $hhh_nrc_number = "000000/00/0";
            // $url = "http://41.175.18.172:8080/sps/api/zispis/dev/kgs/payment/$TransactionID"; 
            $baseUrl=env('PG_URL');
            $username=env('PG_USERNAME');
            $password=env('PG_PASSWORD');
            $apikey=env('PG_APIKEY');  
            $url="{$baseUrl}/sps/api/zispis/dev/kgs/payment/{$TransactionID}";       
            $get_cookie = DB::table('payments_trans')
                ->orderBy('id', 'desc')
                ->value('auth_cookie');
            $auth_cookie = preg_match('/=(.*?);/', $get_cookie, $matches) ? $matches[1] : null;       
            $headers = [
                "User-Agent" => "vscode-restclient",
                "X-APIKey" => $apikey,
                "Content-Type" => "application/json",
                "accept-encoding" => "gzip, deflate",
                "cookie" => "session=$auth_cookie"
            ]; 
            // $household_id2 = self::postPaymentsQueryToPayGateway($auth_cookie);            
            $school_fees ? $school_fees : 0;
            $cwac_name ? $cwac_name : "";
            $dob = "";
            $TransDate = "$TransactionDate";
            $MobileNumber = "260978166385";
            $TransactionType = "Grant";
            $data = [
                "TransactionID" => $TransactionID,          "TransactionDate" => $TransDate,
                "RecipientID" => $RecipientID,              "RecipientType" => "Beneficiary",
                "Gender" => "Female",                       "FirstName" => $school_code,
                "LastName" => $school_name,                 "MobileNumber" => $MobileNumber,
                "Language" => "English",                    "LanguageCode" => "eng",
                "Country" => "ZM",                          "PSP" => "Zanaco",
                "Province" => $school_province,             "District" => $school_district,
                "Ward" => $ward_name,                       "CWAC" => $cwac_name,
                "RegisteredTown" => "",                     "DistrictID" => $district_id,
                "WardID" => $ward_id,                       "CWACID" => $cwac_id,
                "HouseholdID" => $household_id,             "NRC" => $hhh_nrc_number,
                "DateOfBirth" => $dob,                      "AccountNumber" => $account_no,
                "AccountExtra" => $sort_code,               "Currency" => "ZMW",
                "TransactionType" => $TransactionType,      "Amount" => $school_fees,
                "GPSAccuracy" => 0,                         "GPSAltitude" => 0,
                "GPSLatitude" => 0,                         "GPSLongitude" => 0,
                // "PaymentReference" => "[UNDELIVERED][UNDELIVERED][UNDELIVERED]Manual payment",
                "PaymentReference" => "Automatic payment",
                "PaymentCycle" => "January - February 2025 KGS Payment Cycle"                
            ];
            $insert_data = $data;
            $insert_data['school_id'] = $school_id;
            $insert_data['no_of_girls'] = $no_of_girls;

            $client = new Client();
            $response = $client->post($url, [
                'headers' => $headers,
                'json' => $data
            ]);
            
            $statusCode = $response->getStatusCode();
            $headers = $response->getHeaders();
            $body = $response->getBody()->getContents();
            $responses = [
                "status_code" => $statusCode,
                "response_body" => $body                          
            ];

            DB::table('payments_pg_responses')
                ->insert($responses);
            if ($statusCode == 200) {
                $insert_data['trans_status'] = 1;
                DB::table('payments_pg_transactions')
                    ->insert($insert_data);
                    // Disbursed status
                $disbursed_status=DB::table('school_transfer_statuses')
                                      ->where('id',5)
                                      ->value('name');

                    // Update disbursment approval status
                DB::table('payment_disbursement_approvals')
                    ->where('payment_request_id',$school_info ->payment_request_id)
                    ->update([
                        'approval_status_name' => $disbursed_status,
                        'updated_at'=> now()
                    ]);
                return response()->json([
                    'success' => true,
                    'message' => 'Payment Request Submitted Successfully'
                ]);
            } else {
                DB::table('payments_pg_transactions')
                    ->insert($insert_data);
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
        } catch (\Exception $e) {
            $errorString = $e->getMessage();
            $statusCode = null;
            $str_response = null;
            $cleanedString = null;
            $result = [];
            $result['reponse_string'] = $errorString;
            if (preg_match('/`(POST|GET|PUT|DELETE|PATCH)\s(.*?)`/', $errorString, $matches)) {
                // $result['method'] = $matches[1];
                $result['request_url'] = $matches[2]; 
            }        
            if (preg_match('/`(\d{3}\s\w+)`/', $errorString, $matches)) {
                $result['error_code'] = $matches[1];
                $statusCode = $matches[1];
            }    
            $startPos = strpos($errorString, 'response:'); 
            if ($startPos !== false) {
                $str_response = trim(substr($errorString, $startPos + 9));
            }
            $str_response .= "\}";
            $cleanedString = str_replace(['\\', '(truncated...)'], '', $str_response);  
            if (substr(trim($cleanedString), -1) !== '}') {
                $cleanedString .= '}';
            }
            $cleanedString2 = str_replace(['\\', '"'], '', $cleanedString);
            $result['success_status'] = 0;
            $result['return_msg'] = $cleanedString2;
            DB::table('payments_trans_failed')
                ->insert($result);
            return response()->json([
                'success' => false,
                'message' => $result
            ], 500);
        }
    }
        
    public function postMultiplePaymentsToGateway(Request $req)
    {
        try {
            // $app_info = self::savePaymentApprovalInfo($req);            
            $normalTimeLimit = ini_get('max_execution_time');// Get default limit            
            ini_set('max_execution_time', 30000);// Set new limit            
            $payment_request_id = $req->input('payment_request_id');                    
            $qry = DB::table('beneficiary_information as t1')
                ->select(DB::raw("t2.id as school_id,count(t1.id) as no_of_beneficiary,t2.name as school_name,
                        sum(decrypt(annual_fees)) as school_feessummary,sum(decrypt(annual_fees)) as payable_amount,t8.payment_request_id"))
                ->join('beneficiary_enrollments as t5', 't1.id', '=', 't5.beneficiary_id')
                ->join('school_information as t2', 't2.id', '=', 't5.school_id')
                ->join('beneficiary_payment_records as t8', 't5.id', '=', 't8.enrollment_id')
                ->leftJoin('payment_disbursement_details as t11', function ($join) {
                    $join->on('t8.payment_request_id', '=', 't11.payment_request_id');
                    $join->on('t2.id', '=', 't11.school_id');
                })
                ->leftJoin('payment_disbursement_status as t12', 't11.payment_status_id', '=', 't12.id')
                ->where(array('t8.payment_request_id' => $payment_request_id))
                ->whereRaw("IF(`t8`.`payment_request_id` = `t11`.`payment_request_id`, `payment_status_id` = 1,1)");            
            $qry->groupBy('t2.id');
            $total = count($qry->get());
            $results = $qry->get();
            foreach($results as $key => $result) {
                $trans_result = self::postOnePaymentToGateway($result);
            }
            $message = 'Transactions Submitted Successfully to the Gateway, Please check the transaction statuses under the Disbursement Management';
            
            $res = array(
                'success' => true,
                'message' => $message
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
        ini_set('max_execution_time', $normalTimeLimit); //Job on 03/11/2022
        return response()->json($res);
    }

    public function savePaymentApprovalInfo(Request $req)
    {
        $user_id = Auth::user()->id;
        $post_data = $req->all();
        $table_name = $post_data['table_name'];
        $id = $post_data['id'];
        $stage_id = $post_data['workflow_id'];
        //unset unnecessary values
        unset($post_data['_token']);
        unset($post_data['table_name']);
        unset($post_data['model']);
        unset($post_data['id']);
        unset($post_data['is_submission']);
        unset($post_data['prevstage_id']);
        unset($post_data['workflow_id']);
        unset($post_data['workflow_stage']);
        unset($post_data['submitted_on']);
        $table_data = $post_data;     
        //add extra params
        $table_data['created_at'] = Carbon::now();
        $table_data['created_by'] = $user_id;
        $table_data['stage_id'] = $stage_id;
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
                    $record_id = $id;
                    $success = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                    if ($success) {
                        $res = array(
                            'success' => true,
                            'message' => 'Data updated Successfully!!',
                            'record_id' => $record_id
                        );
                    } else {
                        $res = array(
                            'success' => false,
                            'message' => 'Problem encountered while updating data. Try again later!!'
                        );
                    }
                } else {
                    $record_id = insertRecordReturnId($table_name, $table_data, $user_id);
                    $res = array(
                        'success' => true,
                        'message' => 'Data Saved Successfully!!',
                        'record_id' => $record_id
                    );
                }
            } else {
                $record_id = insertRecordReturnId($table_name, $table_data, $user_id);
                if (validateisNumeric($record_id)) {
                    $res = array(
                        'success' => true,
                        'message' => 'Data Saved Successfully!!',
                        'record_id' => $record_id
                    );
                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'Problem encountered while saving details!!'
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
    //version 3 updates

    // public function getPaymentDetails($payment_request_id, $school_id)
    // {
    //     $qry = DB::table('beneficiary_information as t1')
    //         ->select(DB::raw("t2.id as school_id,t2.code as school_emis_code,t2.name as school_name, t10.name as bank_name, decrypt(t9.account_no) as account_no,t11.name as branch_name,t11.sort_code,

    //                           0 as school_fees,
    //                           ROUND(COALESCE(((SELECT SUM(CASE WHEN k.credit_debit = 1 THEN k.amount ELSE 0 END)
    //                           from reconciliation_suspense_account k where k.school_id=t2.id and k.payment_request_id=$payment_request_id
    //                           group by k.school_id,k.payment_request_id))-
    //                           ((SELECT SUM(CASE WHEN k.credit_debit = 2 THEN k.amount ELSE 0 END)
    //                           from reconciliation_suspense_account k where k.school_id=t2.id and k.payment_request_id=$payment_request_id
    //                           group by k.school_id,k.payment_request_id)),0),2) as suspense_account,

    //                           (0+COALESCE(((SELECT SUM(CASE WHEN k.credit_debit = 1 THEN k.amount ELSE 0 END)
    //                           from reconciliation_suspense_account k where k.school_id=t2.id and k.payment_request_id=$payment_request_id
    //                           group by k.school_id,k.payment_request_id))-
    //                           ((SELECT SUM(CASE WHEN k.credit_debit = 2 THEN k.amount ELSE 0 END)
    //                           from reconciliation_suspense_account k where k.school_id=t2.id and k.payment_request_id=$payment_request_id
    //                           group by k.school_id,k.payment_request_id)),0)) as payable_amount
    //                          "))
    //         ->join('beneficiary_enrollments as t5', 't1.id', '=', 't5.beneficiary_id')
    //         ->join('school_information as t2', 't2.id', '=', 't5.school_id')
    //         ->join('districts as t3', 't2.district_id', '=', 't3.id')
    //         ->join('provinces as t4', 't3.province_id', '=', 't4.id')
    //         ->join('beneficiary_payment_records as t8', 't5.id', '=', 't8.enrollment_id')
    //         ->join('payment_request_details as p1', 'p1.id', '=', 't8.payment_request_id')
    //         ->leftJoin('school_bankinformation as t9', function ($join) {
    //             $join->on('t2.id', '=', 't9.school_id')
    //                 ->where('t9.is_activeaccount', 1);
    //         })
    //         ->leftJoin('payment_disbursement_details as d1', function ($join) {
    //             $join->on('t8.payment_request_id', '=', 'd1.payment_request_id');
    //             $join->on('t2.id', '=', 'd1.school_id');
    //         })
    //         ->leftJoin('bank_details as t10', 't9.bank_id', '=', 't10.id')
    //         ->leftJoin('bank_branches as t11', 't9.branch_name', '=', 't11.id')
    //         ->where(array('t8.payment_request_id' => $payment_request_id))
    //         ->whereNull('d1.id')
    //         // ->whereraw('decrypt(t5.annual_fees)>0');//Job on 1/6/2022
    //     if (isset($school_id) && $school_id != '') {
    //         $qry->where('t2.id', $school_id);
    //     }
    //     $qry->groupBy('t2.id')
    //         ->get();
    //     $data = $qry->get();
    //     $results = convertStdClassObjToArray($data);
    //     return $results;
    // }

     public function getPaymentDetails($payment_request_id, $school_id)
    {
        $qry = DB::table('beneficiary_information as t1')
            ->select(DB::raw("t2.id as school_id,t2.code as school_emis_code,t2.name as school_name, t10.name as bank_name, decrypt(t9.account_no) as account_no,t11.name as branch_name,t11.sort_code,
                ROUND(SUM(
                IF(decrypt(t5.term1_fees) IS NULL OR decrypt(t5.term1_fees) < 0, 0, decrypt(t5.term1_fees)) +
                                IF(decrypt(t5.term2_fees) IS NULL OR decrypt(t5.term2_fees) < 0, 0, decrypt(t5.term2_fees)) +
                                IF(decrypt(t5.term3_fees) IS NULL OR decrypt(t5.term3_fees) < 0, 0, decrypt(t5.term3_fees))
                ),2) as school_fees,
                              ROUND(COALESCE(((SELECT SUM(CASE WHEN k.credit_debit = 1 THEN k.amount ELSE 0 END)
                              from reconciliation_suspense_account k where k.school_id=t2.id and k.payment_request_id=$payment_request_id
                              group by k.school_id,k.payment_request_id))-
                              ((SELECT SUM(CASE WHEN k.credit_debit = 2 THEN k.amount ELSE 0 END)
                              from reconciliation_suspense_account k where k.school_id=t2.id and k.payment_request_id=$payment_request_id
                              group by k.school_id,k.payment_request_id)),0),2) as suspense_account,

                              ROUND((SUM(
                              IF(decrypt(t5.term1_fees) IS NULL OR decrypt(t5.term1_fees) < 0, 0, decrypt(t5.term1_fees)) +
                                IF(decrypt(t5.term2_fees) IS NULL OR decrypt(t5.term2_fees) < 0, 0, decrypt(t5.term2_fees)) +
                                IF(decrypt(t5.term3_fees) IS NULL OR decrypt(t5.term3_fees) < 0, 0, decrypt(t5.term3_fees))
                              )+COALESCE(((SELECT SUM(CASE WHEN k.credit_debit = 1 THEN k.amount ELSE 0 END)
                              from reconciliation_suspense_account k where k.school_id=t2.id and k.payment_request_id=$payment_request_id
                              group by k.school_id,k.payment_request_id))-
                              ((SELECT SUM(CASE WHEN k.credit_debit = 2 THEN k.amount ELSE 0 END)
                              from reconciliation_suspense_account k where k.school_id=t2.id and k.payment_request_id=$payment_request_id
                              group by k.school_id,k.payment_request_id)),0)),2) as payable_amount
                             "))
            ->join('beneficiary_enrollments as t5', 't1.id', '=', 't5.beneficiary_id')
            ->join('school_information as t2', 't2.id', '=', 't5.school_id')
            ->join('districts as t3', 't2.district_id', '=', 't3.id')
            ->join('provinces as t4', 't3.province_id', '=', 't4.id')
            ->join('beneficiary_payment_records as t8', 't5.id', '=', 't8.enrollment_id')
            ->join('payment_request_details as p1', 'p1.id', '=', 't8.payment_request_id')
            ->leftJoin('school_bankinformation as t9', function ($join) {
                $join->on('t2.id', '=', 't9.school_id')
                    ->where('t9.is_activeaccount', 1);
            })
            ->leftJoin('payment_disbursement_details as d1', function ($join) {
                $join->on('t8.payment_request_id', '=', 'd1.payment_request_id');
                $join->on('t2.id', '=', 'd1.school_id');
            })
            ->leftJoin('bank_details as t10', 't9.bank_id', '=', 't10.id')
            ->leftJoin('bank_branches as t11', 't9.branch_name', '=', 't11.id')
            ->where(array('t8.payment_request_id' => $payment_request_id))
            ->whereNull('d1.id')
            ->whereraw('decrypt(t5.term2_fees)>0');//Job on 1/6/2022
        if (isset($school_id) && $school_id != '') {
            $qry->where('t2.id', $school_id);
        }
        $qry->groupBy('t2.id')
            ->get();
        $data = $qry->get();
        $results = convertStdClassObjToArray($data);
        return $results;
    }

    public function paymentScheduleForDisbursed(Request $req)
    {
        $payment_request_id = $req->input('payment_request_id');
        //payment request
        $details_info = DB::table('payment_request_details')
            ->where('id', $payment_request_id)
            ->first();
        $qry = DB::table('beneficiary_information as t1')
            ->select(DB::raw("t3.name as sch_district,t2.id as school_id,t2.code as school_emis_code,t2.name as school_name, t10.name as bank_name, decrypt(t9.account_no) as account_no,t11.name as branch_name,t11.sort_code,
                              ROUND(sum(decrypt(annual_fees)),2) as school_fees,

                              ROUND(COALESCE(((SELECT SUM(CASE WHEN k.credit_debit = 1 THEN k.amount ELSE 0 END)
                              from reconciliation_suspense_account k where k.school_id=t2.id and k.payment_request_id=$payment_request_id
                              group by k.school_id,k.payment_request_id))-
                              ((SELECT SUM(CASE WHEN k.credit_debit = 2 THEN k.amount ELSE 0 END)
                              from reconciliation_suspense_account k where k.school_id=t2.id and k.payment_request_id=$payment_request_id
                              group by k.school_id,k.payment_request_id)),0),2) as suspense_account,

                              ROUND((SUM(decrypt(annual_fees))+COALESCE(((SELECT SUM(CASE WHEN k.credit_debit = 1 THEN k.amount ELSE 0 END)
                              from reconciliation_suspense_account k where k.school_id=t2.id and k.payment_request_id=$payment_request_id
                              group by k.school_id,k.payment_request_id))-
                              ((SELECT SUM(CASE WHEN k.credit_debit = 2 THEN k.amount ELSE 0 END)
                              from reconciliation_suspense_account k where k.school_id=t2.id and k.payment_request_id=$payment_request_id
                              group by k.school_id,k.payment_request_id)),0)),2) as payable_amount,

                              ROUND(decrypt(d1.amount_transfered),2) as amount_transfered,
                              decrypt(d1.transaction_no) as transaction_no,date(d1.transaction_date) as transaction_date
                              "))
            ->join('beneficiary_enrollments as t5', 't1.id', '=', 't5.beneficiary_id')
            ->join('school_information as t2', 't2.id', '=', 't5.school_id')
            ->join('districts as t3', 't2.district_id', '=', 't3.id')
            ->join('provinces as t4', 't3.province_id', '=', 't4.id')
            ->join('beneficiary_payment_records as t8', 't5.id', '=', 't8.enrollment_id')
            ->join('payment_request_details as p1', 'p1.id', '=', 't8.payment_request_id')
            ->join('payment_disbursement_details as d1', function ($join) {
                $join->on('t8.payment_request_id', '=', 'd1.payment_request_id');
                $join->on('t2.id', '=', 'd1.school_id');
            })
            ->join('school_bankinformation as t9', 't9.id', '=', 'd1.school_bank_id')
            ->leftJoin('bank_details as t10', 't9.bank_id', '=', 't10.id')
            ->leftJoin('bank_branches as t11', 't9.branch_name', '=', 't11.id');

        $qry->where(array('t8.payment_request_id' => $payment_request_id))
            ->groupBy('t2.id');
        $results = $qry->get();
        $results = convertStdClassObjToArray($results);
        return Excel::download(new PaymentScheduleExportDisbursed($results, $details_info), $details_info->payment_year . '_PaymentSchedule.xls');
    }

    //the disbursement reports
    public function printPaymentdisbusementReport(Request $req)
    {
        $payment_request_id = $req->input('payment_request_id');
        //the details from the payment table
        PDF::AddPage('l');
        PDF::setMargins(8, 25, 8, true);
        PDF::SetAutoPageBreak(TRUE, 0);//true sets it to on and 0 means margin is zero from sides
        PDF::SetFont('times', '', 8);
        $qry = DB::table('payment_request_details as t1')
            ->select('t1.*', 't1.id as payment_request_id')
            //->join('school_terms as t5', 't1.term_id', '=', 't5.id')
            ->where(array('t1.id' => $payment_request_id))
            ->get();
        //user_role_id school_bankinformation
        $results = convertStdClassObjToArray($qry);
        $data = decryptArray($results);
        if (count($data) > 0) {
            $data = $data[0];

            landscapereport_header();
            PDF::ln();
            PDF::SetFont('times', '', 8);
            PDF::cell(0, 6, 'PAYMENT DISBURSEMENT REPORT FOR THE YEAR ' . $data['payment_year'], 0, 1);
            PDF::cell(0, 6, $data['payment_ref_no'], 0, 1, '');

            //the school payment details
            //get the school details

            $qry = DB::table('beneficiary_information as t1')
                ->select(DB::raw('t12.school_bank_id,decrypt(t12.amount_transfered) as amount_transfered, t2.id as school_id,t8.payment_request_id,count(t1.id) as no_of_beneficiary,sum(decrypt(annual_fees)) as school_feessummary,t2.name as school_name,
                                  t3.name as district_name,t4.name as province_name'))
                ->join('beneficiary_enrollments as t5', 't1.id', '=', 't5.beneficiary_id')
                ->join('school_information as t2', 't2.id', '=', 't5.school_id')
                ->join('districts as t3', 't2.district_id', '=', 't3.id')
                ->join('provinces as t4', 't3.province_id', '=', 't4.id')
                ->join('beneficiary_payment_records as t8', 't5.id', '=', 't8.enrollment_id')
                ->leftJoin('payment_disbursement_details as t12', function ($join) {
                    $join->on('t8.payment_request_id', '=', 't12.payment_request_id');
                    $join->on('t2.id', '=', 't12.school_id');
                })
                ->where(array('t8.payment_request_id' => $payment_request_id))
                ->groupBy('t2.id');
            $results = $qry->get();
            $results = convertStdClassObjToArray($results);
            if (count($results)) {
                //the table header
                PDF::ln(5);
                PDF::SetFont('times', '', 8);
                $payment_table = '<table border="1" cellpadding="3">
                                       <tbody>
                                         <tr>
                                                <td width="40" >S/n</td>
                                                <td >PROVINCE</td>
                                                <td>DISTRICT OF SCHOOL</td>
                                                <td >SCHOOL NAME</td>
                                                <td>GIRLS PER SCHOOL</td>
                                                <td>BANK NAME</td>
                                                <td>BANK ACCOUNT</td>
                                                <td>BRANCH</td>
                                                <td>SORT CODE</td>
                                                <td>TOTAL SCHOOL FEES</td>
                                                <td>AMOUNT DISBURSED</td>
                                            </tr>
                                      ';
                $i = 1;
                $total_schoolfees = 0;
                $total_feesdisbursed = 0;
                $total_girls = 0;
                foreach ($results as $rec) {
                    //the school details
                    $bank_details = getSchoolBankDetails($rec['school_bank_id'], $rec['school_id']);
                    $payment_table .= '<tr>
                                                <td>' . $i . '</td>
                                                <td>' . $rec['province_name'] . '</td>
                                                <td>' . $rec['district_name'] . '</td>
                                                <td>' . $rec['school_name'] . '</td>
                                                <td style="text-align:right">' . $rec['no_of_beneficiary'] . '</td>
                                                <td>' . $bank_details->bank_name . '</td>
                                                <td>' . aes_decrypt($bank_details->account_no) . '</td>
                                                <td>' . $bank_details->branch_name . '</td>
                                                <td>' . $bank_details->sort_code . '</td>
                                                <td style="text-align:right">' . formatMoney($rec['school_feessummary']) . '</td>
                                                <td style="text-align:right">' . formatMoney($rec['amount_transfered']) . '</td>
                                           </tr>';

                    //amount_transfered transaction_no transaction_date
                    $total_girls = $total_girls + $rec['no_of_beneficiary'];
                    $total_schoolfees = $total_schoolfees + $rec['school_feessummary'];
                    $total_feesdisbursed = $total_feesdisbursed + $rec['amount_transfered'];


                    $i++;
                }

                $payment_table .= '<tr style="font-weight:bold">
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td>TOTAL GIRLS</td>
                                                <td style="text-align:right">' . $total_girls . '</td>
                                                <td></td>
                                                <td></td>
                                                <td colspan="2">Total Amount</td>
                                                <td style="text-align:right">' . formatMoney($total_schoolfees) . '</td>
                                                <td style="text-align:right">' . formatMoney($total_feesdisbursed) . '</td>

                                            </tr>
                                      ';

                $payment_table .= '</table>';

                PDF::writeHTML($payment_table, true, false, false, false, 'L');

            } else {
                //no details found
                PDF::Cell(0, 5, 'No Enrollment Details', 0, 1, 'C');
            }

        } else {

            //
        }

        PDF::Output('Payment Schedule' . time() . '.pdf', 'I');
    }

    public function printPaymentrequestscheduleOld(Request $req)
    {
        //$memory_limit = ini_get('memory_limit');
       // $startMemory = memory_get_usage();
        //dd( $startMemory);
        //dd( $memory_limit);
        //ini_set('memory_limit', '-1');
        $payment_request_id = $req->input('payment_request_id');
        /* $duplicates_exist=checkEnrollmentDuplicates($payment_request_id);
         if($duplicates_exist==true){
             echo '<p align="center" style="color: red">Duplicates found, please process duplicates before printing this schedule!!</p>';
             exit();
         }*/
        //the details from the payment table
        PDF::AddPage('');
        PDF::setMargins(8, 25, 8, true);
        PDF::SetAutoPageBreak(TRUE, 0);//true sets it to on and 0 means margin is zero from sides
        PDF::SetFont('times', '', 8);
        $qry = DB::table('payment_request_details as t1')
            ->select(DB::raw("t6.name as preparedby_pos, t7.name as checkedby_pos, t8.name as approvedby_pos,
                              t1.*, CONCAT_WS(' ',decrypt(t2.first_name),decrypt(t2.last_name)) as prepared_by,
                              t1.id as payment_request_id, CONCAT_WS(' ',decrypt(t4.first_name),decrypt(t4.last_name)) as approved_byname,
                              CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as checked_byname"))
            /* ->select('t6.name as preparedby_pos', 't7.name as checkedby_pos', 't8.name as approvedby_pos',
                       't5.name as school_term', 't1.*', 't2.first_name as prepared_by', 't1.id as payment_request_id',
                       't3.first_name as checked_byname', 't4.first_name as approved_byname')*/
            ->leftJoin('users as t2', 't1.prepared_by', '=', 't2.id')
            ->leftJoin('user_roles as t6', 't2.user_role_id', '=', 't6.id')
            ->leftJoin('users as t3', 't1.checked_by', '=', 't3.id')
            ->leftJoin('user_roles as t7', 't3.user_role_id', '=', 't7.id')
            ->leftJoin('users as t4', 't1.approved_by', '=', 't4.id')
            ->leftJoin('user_roles as t8', 't4.user_role_id', '=', 't8.id')
            ->where(array('t1.id' => $payment_request_id));
        $results = $qry->get();
        $data = convertStdClassObjToArray($results);
        //$data = decryptArray($results);
        if (count($data) > 0) {
            $data = $data[0];

            $image_path = '\resources\images\kgs-logo.png';
            PDF::Image(getcwd() . $image_path, 150, 10, 30, 20);

            //the cell details
            PDF::ln(4);
            PDF::cell(0, 6, 'MINISTRY OF  EDUCATION', 0, 1);
            PDF::cell(0, 6, 'GIRLS EDUCATION AND WOMEN EMPOWERMENT AND LIVELIHOOD PROJECT', 0, 1);
            PDF::cell(0, 6, 'KEEPING GIRLS IN SCHOOL INITIATIVE', 0, 1);
            PDF::cell(0, 6, 'PAYMENT REQUEST REPORT FOR THE YEAR ' . $data['payment_year'] . ' TERM I', 0, 1);
            PDF::cell(0, 6, $data['payment_ref_no'], 0, 1, 'R');

            //
            //the school payment details
            //get the school details
            $qry = DB::table('beneficiary_information as t1')
                ->select(DB::raw('t2.id as school_id,t10.name as bank_name, t11.name as branch_name,decrypt(t9.account_no) as account_no,t11.sort_code, count(t1.id) as no_of_beneficiary,
                    SUM(
            IF(decrypt(t5.term1_fees) IS NULL OR decrypt(t5.term1_fees) < 0, 0, decrypt(t5.term1_fees)) +
            IF(decrypt(t5.term2_fees) IS NULL OR decrypt(t5.term2_fees) < 0, 0, decrypt(t5.term2_fees)) +
            IF(decrypt(t5.term3_fees) IS NULL OR decrypt(t5.term3_fees) < 0, 0, decrypt(t5.term3_fees))
        ) as school_feessummary,
                    t2.name as school_name, t3.name as district_name,t4.name as province_name'))
                ->join('beneficiary_enrollments as t5', 't1.id', '=', 't5.beneficiary_id')
                ->join('school_information as t2', 't2.id', '=', 't5.school_id')
                ->join('districts as t3', 't2.district_id', '=', 't3.id')
                ->join('provinces as t4', 't3.province_id', '=', 't4.id')
                ->join('beneficiary_payment_records as t8', 't5.id', '=', 't8.enrollment_id')
                ->leftJoin('school_bankinformation as t9', function ($join) {
                    $join->on('t2.id', '=', 't9.school_id')
                        ->where('t9.is_activeaccount', 1);
                })
                ->leftJoin('bank_details as t10', 't9.bank_id', '=', 't10.id')
                ->leftJoin('bank_branches as t11', 't9.branch_name', '=', 't11.id')
                ->where(array('t8.payment_request_id' => $payment_request_id))
                ->groupBy('t2.id');
            $results = $qry->get();
            $results = convertStdClassObjToArray($results);
            //$results = decryptArray($results);
            if (count($results)) {
                //the table header
                PDF::ln(4.2);
                PDF::SetFont('times', '', 7.74);
                $payment_table = '<table border="1" cellpadding="3">
                                       <tbody>
                                         <tr nobr="true">
                                                <td rowspan="2" width="40" >S/n</td>
                                                <td rowspan="2">PROVINCE</td>
                                                <td rowspan="2">DISTRICT OF SCHOOL</td>
                                                <td rowspan="2">SCHOOL NAME</td>
                                                <td rowspan="2">GIRLS PER SCHOOL</td>
                                                <td rowspan="2">BANK NAME</td>
                                                <td rowspan="2">BANK ACCOUNT</td>
                                                <td rowspan="2">BRANCH</td>
                                                <td rowspan="2">SORT CODE</td>
                                                <td colspan="3" align="center">AMOUNTS</td>
                                            </tr>

                                            <tr>
                                                  <td>INDICATED</td>
                                                  <td>SUSPENSE</td>
                                                  <td>PAYABLE</td>
                                            </tr>';
                $i = 1;
                $total_schoolfees = 0;
                $total_suspense = 0;
                $total_payable = 0;
                $total_girls = 0;
                //$results= array_slice($results, 0, 360); 
               // dd($results);
                foreach ($results as $rec) {
                    //the school details
                    $suspense_amount = getReconciliationSuspenseAmount($payment_request_id, $rec['school_id']);

                    $amount_payable = $rec['school_feessummary'] + $suspense_amount;
                    $payment_table .= '<tr nobr="true">
                                                <td>' . $i . '</td>
                                                <td>' . $rec['province_name'] . '</td>
                                                <td>' . $rec['district_name'] . '</td>
                                                <td>' . $rec['school_name'] . '</td>
                                                <td style="text-align:right">' . $rec['no_of_beneficiary'] . '</td>
                                                <td>' . $rec['bank_name'] . '</td>
                                                <td>' . $rec['account_no'] . '</td>
                                                <td>' . $rec['branch_name'] . '</td>
                                                <td>' . $rec['sort_code'] . '</td>
                                                <td style="text-align:right">K ' . formatMoney($rec['school_feessummary']) . '</td>
                                                <td style="text-align:right">K ' . formatMoney($suspense_amount) . '</td>
                                                <td style="text-align:right">K ' . formatMoney($amount_payable) . '</td>
                                            </tr>';
                    $total_girls = $total_girls + $rec['no_of_beneficiary'];
                    $total_schoolfees += $rec['school_feessummary'];
                    $total_suspense += $suspense_amount;
                    $total_payable += $amount_payable;
                    $i++;
                    ob_flush();
                }
                
                $payment_table .= '<tr style="font-weight:bold" nobr="true">
                                                <td colspan="4" align="right">TOTAL BENEFICIARIES</td>
                                                <td style="text-align:right">' . $total_girls . '</td>
                                                <td colspan="4" align="right">TOTAL AMOUNT</td>
                                                <td style="text-align:right">K ' . formatMoney($total_schoolfees) . '</td>
                                                <td style="text-align:right">K ' . formatMoney($total_suspense) . '</td>
                                                <td style="text-align:right">K ' . formatMoney($total_payable) . '</td>
                                            </tr>
                                      ';

                $payment_table .= '</table>';


                PDF::writeHTML($payment_table, true, false, false, false, 'L');
                //the other details of prepared by
                PDF::setCellHeightRatio(1.8);
                $approval_table = '<table width="80%" border="1" cellpadding="3">
                                       <tbody>
                                           <tr nobr="true">
                                                <td width="70">PREPARED BY:</td>
                                                <td width="100">' . $this->formatSignatory($data['prepared_by']) . '</td>
                                                <td width="50">POSITION:</td>
                                                <td width="70">' . $this->formatSignatory($data['preparedby_pos']) . '</td>
                                                <td width="70">SIGNATURE:</td>
                                                <td width="70">' . $this->formatSignatory() . '</td>
                                                <td width="50">DATE:</td>
                                                <td width="50">' . formatDaterpt($data['prepared_on']) . '</td>
                                            </tr>
                                            <tr nobr="true">
                                                <td>CHECKED BY:</td>
                                                <td>' . $this->formatSignatory($data['checked_byname']) . '</td>
                                                <td>POSITION:</td>
                                                <td>' . $this->formatSignatory($data['checkedby_pos']) . '</td>
                                                <td>SIGNATURE:</td>
                                                <td>' . $this->formatSignatory() . '</td>
                                                <td>DATE:</td>
                                                <td>' . formatDaterpt($data['checked_on']) . '</td>
                                            </tr>
                                             <tr nobr="true">
                                                <td>APPROVED BY:</td>
                                                <td>' . $this->formatSignatory($data['approved_byname']) . '</td>
                                                <td>POSITION:</td>
                                                <td >' . $this->formatSignatory($data['approvedby_pos']) . '</td>
                                                <td>SIGNATURE:</td>
                                                <td>' . $this->formatSignatory() . '</td>
                                                <td>DATE:</td>
                                                <td>' . formatDaterpt($data['approved_on']) . '</td>
                                            </tr>
                                        <tbody>
                                      ';
                PDF::writeHTML($approval_table, true, false, false, false, 'L');
            } else {
                //no details found
                PDF::Cell(0, 5, 'No Enrollment Details', 0, 1, 'C');
            }

        } else {

            //
        }
          //ini_set('memory_limit',$memory_limit);
        PDF::Output('Payment Schedule' . time() . '.pdf', 'I');
    }
    public function printPaymentrequestschedule(Request $req) // 31st March 2026
    {
        $payment_request_id = $req->input('payment_request_id');
        //the details from the payment table
        PDF::AddPage('');
        PDF::setMargins(8, 25, 8, true);
        PDF::SetAutoPageBreak(TRUE, 0);//true sets it to on and 0 means margin is zero from sides
        PDF::SetFont('times', '', 8);
        $qry = DB::table('payment_request_details as t1')
            ->select(DB::raw("t6.name as preparedby_pos, t7.name as checkedby_pos, t8.name as approvedby_pos,
                              t1.*, CONCAT_WS(' ',decrypt(t2.first_name),decrypt(t2.last_name)) as prepared_by,
                              t1.id as payment_request_id, CONCAT_WS(' ',decrypt(t4.first_name),decrypt(t4.last_name)) as approved_byname,
                              CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as checked_byname"))
            ->leftJoin('users as t2', 't1.prepared_by', '=', 't2.id')
            ->leftJoin('user_roles as t6', 't2.user_role_id', '=', 't6.id')
            ->leftJoin('users as t3', 't1.checked_by', '=', 't3.id')
            ->leftJoin('user_roles as t7', 't3.user_role_id', '=', 't7.id')
            ->leftJoin('users as t4', 't1.approved_by', '=', 't4.id')
            ->leftJoin('user_roles as t8', 't4.user_role_id', '=', 't8.id')
            ->where(array('t1.id' => $payment_request_id));
        $results = $qry->get();
        $data = convertStdClassObjToArray($results);
        //$data = decryptArray($results);
        if (count($data) > 0) {
            $data = $data[0];

            $image_path = '\resources\images\kgs-logo.png';
            PDF::Image(getcwd() . $image_path, 150, 10, 30, 20);

            //the cell details
            PDF::ln(4);
            PDF::cell(0, 6, 'MINISTRY OF  EDUCATION', 0, 1);
            PDF::cell(0, 6, 'GIRLS EDUCATION AND WOMEN EMPOWERMENT AND LIVELIHOOD PROJECT', 0, 1);
            PDF::cell(0, 6, 'KEEPING GIRLS IN SCHOOL INITIATIVE', 0, 1);
            PDF::cell(0, 6, 'PAYMENT REQUEST REPORT FOR THE YEAR ' . $data['payment_year'] . ' TERM I', 0, 1);
            PDF::cell(0, 6, $data['payment_ref_no'], 0, 1, 'R');

            //
            //the school payment details
            //get the school details
            $qry = DB::table('beneficiary_payresponses_report as t5')
                ->select(DB::raw('t2.id as school_id,t10.name as bank_name, t11.name as branch_name,
                decrypt(t9.account_no) as account_no,t11.sort_code, count(t5.id) as no_of_beneficiary,
                    t5.total_payable_fees as school_feessummary,
                    t2.name as school_name, t3.name as district_name,t4.name as province_name'))
                // ->join('beneficiary_enrollments as t5', 't1.id', '=', 't5.beneficiary_id')
                ->join('school_information as t2', 't2.id', '=', 't5.school_id')
                ->join('districts as t3', 't2.district_id', '=', 't3.id')
                ->join('provinces as t4', 't3.province_id', '=', 't4.id')
                ->join('beneficiary_payment_records as t8', 't5.id', '=', 't8.enrollment_id')
                ->leftJoin('school_bankinformation as t9', function ($join) {
                    $join->on('t2.id', '=', 't9.school_id')
                        ->where('t9.is_activeaccount', 1);
                })
                ->leftJoin('bank_details as t10', 't9.bank_id', '=', 't10.id')
                ->leftJoin('bank_branches as t11', 't9.branch_name', '=', 't11.id')
                ->where(array('t8.payment_request_id' => $payment_request_id))
                ->groupBy('t2.id');
            $results = $qry->get();
            $results = convertStdClassObjToArray($results);
            //$results = decryptArray($results);
            if (count($results)) {
                //the table header
                PDF::ln(4.2);
                PDF::SetFont('times', '', 7.74);
                $payment_table = '<table border="1" cellpadding="3">
                                       <tbody>
                                         <tr nobr="true">
                                                <td rowspan="2" width="40" >S/n</td>
                                                <td rowspan="2">PROVINCE</td>
                                                <td rowspan="2">DISTRICT OF SCHOOL</td>
                                                <td rowspan="2">SCHOOL NAME</td>
                                                <td rowspan="2">GIRLS PER SCHOOL</td>
                                                <td rowspan="2">BANK NAME</td>
                                                <td rowspan="2">BANK ACCOUNT</td>
                                                <td rowspan="2">BRANCH</td>
                                                <td rowspan="2">SORT CODE</td>
                                                <td colspan="3" align="center">AMOUNTS</td>
                                            </tr>

                                            <tr>
                                                  <td>INDICATED</td>
                                                  <td>SUSPENSE</td>
                                                  <td>PAYABLE</td>
                                            </tr>';
                $i = 1;
                $total_schoolfees = 0;
                $total_suspense = 0;
                $total_payable = 0;
                $total_girls = 0;
                //$results= array_slice($results, 0, 360); 
               // dd($results);
                foreach ($results as $rec) {
                    //the school details
                    $suspense_amount = getReconciliationSuspenseAmount($payment_request_id, $rec['school_id']);

                    $amount_payable = $rec['school_feessummary'] + $suspense_amount;
                    $payment_table .= '<tr nobr="true">
                                                <td>' . $i . '</td>
                                                <td>' . $rec['province_name'] . '</td>
                                                <td>' . $rec['district_name'] . '</td>
                                                <td>' . $rec['school_name'] . '</td>
                                                <td style="text-align:right">' . $rec['no_of_beneficiary'] . '</td>
                                                <td>' . $rec['bank_name'] . '</td>
                                                <td>' . $rec['account_no'] . '</td>
                                                <td>' . $rec['branch_name'] . '</td>
                                                <td>' . $rec['sort_code'] . '</td>
                                                <td style="text-align:right">K ' . formatMoney($rec['school_feessummary']) . '</td>
                                                <td style="text-align:right">K ' . formatMoney($suspense_amount) . '</td>
                                                <td style="text-align:right">K ' . formatMoney($amount_payable) . '</td>
                                            </tr>';
                    $total_girls = $total_girls + $rec['no_of_beneficiary'];
                    $total_schoolfees += $rec['school_feessummary'];
                    $total_suspense += $suspense_amount;
                    $total_payable += $amount_payable;
                    $i++;
                    ob_flush();
                }
                
                $payment_table .= '<tr style="font-weight:bold" nobr="true">
                                                <td colspan="4" align="right">TOTAL BENEFICIARIES</td>
                                                <td style="text-align:right">' . $total_girls . '</td>
                                                <td colspan="4" align="right">TOTAL AMOUNT</td>
                                                <td style="text-align:right">K ' . formatMoney($total_schoolfees) . '</td>
                                                <td style="text-align:right">K ' . formatMoney($total_suspense) . '</td>
                                                <td style="text-align:right">K ' . formatMoney($total_payable) . '</td>
                                            </tr>
                                      ';

                $payment_table .= '</table>';


                PDF::writeHTML($payment_table, true, false, false, false, 'L');
                //the other details of prepared by
                PDF::setCellHeightRatio(1.8);
                $approval_table = '<table width="80%" border="1" cellpadding="3">
                                       <tbody>
                                           <tr nobr="true">
                                                <td width="70">PREPARED BY:</td>
                                                <td width="100">' . $this->formatSignatory($data['prepared_by']) . '</td>
                                                <td width="50">POSITION:</td>
                                                <td width="70">' . $this->formatSignatory($data['preparedby_pos']) . '</td>
                                                <td width="70">SIGNATURE:</td>
                                                <td width="70">' . $this->formatSignatory() . '</td>
                                                <td width="50">DATE:</td>
                                                <td width="50">' . formatDaterpt($data['prepared_on']) . '</td>
                                            </tr>
                                            <tr nobr="true">
                                                <td>CHECKED BY:</td>
                                                <td>' . $this->formatSignatory($data['checked_byname']) . '</td>
                                                <td>POSITION:</td>
                                                <td>' . $this->formatSignatory($data['checkedby_pos']) . '</td>
                                                <td>SIGNATURE:</td>
                                                <td>' . $this->formatSignatory() . '</td>
                                                <td>DATE:</td>
                                                <td>' . formatDaterpt($data['checked_on']) . '</td>
                                            </tr>
                                             <tr nobr="true">
                                                <td>APPROVED BY:</td>
                                                <td>' . $this->formatSignatory($data['approved_byname']) . '</td>
                                                <td>POSITION:</td>
                                                <td >' . $this->formatSignatory($data['approvedby_pos']) . '</td>
                                                <td>SIGNATURE:</td>
                                                <td>' . $this->formatSignatory() . '</td>
                                                <td>DATE:</td>
                                                <td>' . formatDaterpt($data['approved_on']) . '</td>
                                            </tr>
                                        <tbody>
                                      ';
                PDF::writeHTML($approval_table, true, false, false, false, 'L');
            } else {
                //no details found
                PDF::Cell(0, 5, 'No Enrollment Details', 0, 1, 'C');
            }

        } else {

            //
        }
          //ini_set('memory_limit',$memory_limit);
        PDF::Output('Payment Schedule' . time() . '.pdf', 'I');
    }


    //ote details
    public function printGeneratBatchVerificationchk(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $qry = DB::table('payment_verificationbatch as t1')
            ->select(DB::raw('t1.*,t9.name as term_name,t2.school_type_id,t8.name as school_type,t7.name as bank_name,
                              t10.name as branch_name,decrypt(t6.account_no) as account_no, t10.sort_code,t5.full_names as head_teacher,
                              t5.mobile_no as head_contact,t1.school_id,t2.name as school_name,t2.code as school_code,t3.name as district_name,
                              t11.name as cwac_name,t4.name as province_name,CONCAT(decrypt(t12.phone),"/", decrypt(t12.mobile)) as cwac_contacts
                             '))
            //->select('t1.*', 't9.name as term_name', 't2.school_type_id', 't8.name as school_type', 't7.name as bank_name', 't10.name as branch_name', 't6.account_no', 't10.sort_code', 't5.full_names as head_teacher', 't5.mobile_no as head_contact', 't1.school_id', 't2.name as school_name', 't2.code as school_code', 't3.name as district_name', 't4.name as province_name')
            ->join('school_information as t2', 't1.school_id', '=', 't2.id')
            ->join('districts  as t3', 't2.district_id', '=', 't3.id')
            ->join('provinces  as t4', 't3.province_id', '=', 't4.id')
            ->leftJoin('school_contactpersons as t5', 't2.id', '=', 't5.school_id')
            ->leftJoin('school_bankinformation as t6', 't2.id', '=', 't6.school_id')
            ->leftJoin('bank_details as t7', 't6.bank_id', '=', 't7.id')
            ->leftJoin('bank_branches as t10', 't6.branch_name', '=', 't10.id')
            ->leftJoin('school_types as t8', 't2.school_type_id', '=', 't8.id')
            ->leftJoin('school_terms as t9', 't1.term_id', '=', 't9.id')
            ->leftJoin('cwac as t11', 't2.cwac_id', '=', 't11.id')
            ->leftJoin('users as t12', 't11.contact_person_id', '=', 't11.id')//job on 17/4/2022
            ->if('t5.designation_id' > 0, 't5.designation_id', '=', 1)
            ->groupBy('t2.id')
            ->where(array('t1.id' => $batch_id))
            ->first();
        $term_name = $qry->term_name;
        $term_id = $qry->term_id;
        $year_of_enrollment = $qry->year_of_enrollment;
       // CONCAT_WS('/',decrypt(t12.mobile),decrypt(t12.phone)) as cwac_contacts
        $this->getBatchPaymentcheclistDetails($batch_id, $qry, $term_name, $year_of_enrollment, $term_id);
    }

    //the details
    public function printGeneratBatchVerificationchkOr(Request $req)
    {
        $batch_id = $req->input('batch_id');
        $qry = DB::table('payment_verificationbatch as t1')
            ->select(DB::raw('t1.*,t9.name as term_name,t2.school_type_id,t8.name as school_type,t7.name as bank_name,
                              t10.name as branch_name,decrypt(t6.account_no) as account_no, t10.sort_code,t5.full_names as head_teacher,
                              t5.mobile_no as head_contact,t1.school_id,t2.name as school_name,t2.code as school_code,t3.name as district_name,
                              t11.name as cwac_name,t4.name as province_name'))
            //->select('t1.*', 't9.name as term_name', 't2.school_type_id', 't8.name as school_type', 't7.name as bank_name', 't10.name as branch_name', 't6.account_no', 't10.sort_code', 't5.full_names as head_teacher', 't5.mobile_no as head_contact', 't1.school_id', 't2.name as school_name', 't2.code as school_code', 't3.name as district_name', 't4.name as province_name')
            ->join('school_information as t2', 't1.school_id', '=', 't2.id')
            ->join('districts  as t3', 't2.district_id', '=', 't3.id')
            ->join('provinces  as t4', 't3.province_id', '=', 't4.id')
            ->leftJoin('school_contactpersons as t5', 't2.id', '=', 't5.school_id')
            ->leftJoin('school_bankinformation as t6', 't2.id', '=', 't6.school_id')
            ->leftJoin('bank_details as t7', 't6.bank_id', '=', 't7.id')
            ->leftJoin('bank_branches as t10', 't6.branch_name', '=', 't10.id')
            ->leftJoin('school_types as t8', 't2.school_type_id', '=', 't8.id')
            ->leftJoin('school_terms as t9', 't1.term_id', '=', 't9.id')
            ->leftJoin('cwac as t11', 't2.cwac_id', '=', 't11.id')
            ->if('t5.designation_id' > 0, 't5.designation_id', '=', 1)
            ->groupBy('t2.id')
            ->where(array('t1.id' => $batch_id))
            ->first();
        $term_name = $qry->term_name;
        $term_id = $qry->term_id;
        $year_of_enrollment = $qry->year_of_enrollment;

        $this->getBatchPaymentcheclistDetails($batch_id, $qry, $term_name, $year_of_enrollment, $term_id);
    }

    function getBatchPaymentcheclistDetails($batch_id, $rec, $term_name, $year, $term_id)
    {
        $this->getPaymentchecklistOtherdetails($rec, $term_name, $year, $term_id);
        $sql = DB::table('beneficiary_enrollments as t1')
            ->select(DB::raw("SUM(IF(t1.has_signed=1,1,0)) as passed_rules,
                              SUM(IF(t1.has_signed <>1 OR t1.has_signed IS NULL,1,0)) as failed_rules"))
            //->where(array('school_id' => $school_id, 'year_of_enrollment' => $year, 'term_id' => $term_id))
            ->where(array('batch_id' => $batch_id, 'year_of_enrollment' => $year))//, 'term_id' => $term_id))
            ->groupBy('t1.batch_id')
            ->first();
        if ($sql) {
            PDF::cell(0, 8, 'Enrollment Summary Details', 0, 1);
            PDF::cell(10, 8, 'Sn', 1, 0);
            PDF::cell(35, 8, 'Signed on the Checklist?', 1, 0);
            PDF::cell(35, 8, '# of Beneficiaries', 1, 1);
            PDF::cell(10, 8, 1, 1, 0);
            PDF::cell(35, 8, 'YES', 1, 0);
            PDF::cell(35, 8, $sql->passed_rules, 1, 1, 'C');
            PDF::cell(10, 8, 2, 1, 0);
            PDF::cell(35, 8, 'NO', 1, 0);
            PDF::cell(35, 8, $sql->failed_rules, 1, 1, 'C');
            /*   foreach ($sql as $rows) {
                   PDF::cell(10, 8, $i, 1, 0);
                   PDF::cell(35, 8, $rows->status, 1, 0);
                   PDF::cell(35, 8, $rows->counter, 1, 1, 'C');
                   $i++;
               }*/
        }
        PDF::ln();
        $this->getBatchenrolledBeneficairydetails($batch_id, $rec->school_id, $year, $term_id);
        PDF::output();
    }

    function getBatchenrolledBeneficairydetails($batch_id, $school_id, $year, $term_id)
    {
        $qry = DB::table('beneficiary_information as t1')
            ->join('beneficiary_enrollments as t9', 't1.id', '=', 't9.beneficiary_id')
            ->select(DB::raw("t9.remarks,decrypt(t9.annual_fees) as confirmed_fees,t13.name as has_signed, t11.name as letter_received,t1.beneficiary_id, t1.first_name,t1.last_name,t9.school_grade,t9.school_fees,t7.name as beneficiary_school_status,t5.hhh_fname,t6.name as hhh_district, t10.name as is_enrolled"))
            ->leftJoin('beneficiary_school_statuses as t7', 't9.beneficiary_schoolstatus_id', '=', 't7.id')
            ->leftJoin('households as t5', 't1.household_id', '=', 't5.id')
            //->leftJoin('cwac as t8', 't5.cwac_id', '=', 't8.id')
            ->leftJoin('districts as t6', 't1.district_id', '=', 't6.id')
            ->leftJoin('confirmations as t10', 't9.enrollment_status_id', '=', 't10.flag')
            ->leftJoin('confirmations as t11', 't1.is_letter_received', '=', 't11.flag')
            //->leftJoin('beneficiary_attendanceperform_details as t12', 't9.id', '=', 't12.enrollment_id')
            ->leftJoin('confirmations as t13', 't9.has_signed', '=', 't13.flag')
            ->where(array('t9.school_id' => $school_id, 't9.batch_id' => $batch_id))
            ->get();
        $records = $qry;//decryptArray($qry);
        //then the details of a beneficiaries
        //<td rowspan="2">SCHOOL STATUS OF GIRL</td> confirmed_fees
        $ben_table = '<table border="1" cellpadding="3">
                       <tbody>
                           <tr>
                               <td rowspan="2" >S/N</td>
                                <td colspan="4">BENEFICIARY INFORMATION</td>
                                <td rowspan="2">CONFIRMED FORM/GRADE</td>
                                <td rowspan="2">CONFIRMED SCHOOL STATUS(Day/Boarder/Weekly Boarder)</td>
                                <td rowspan="2">LETTER RECEIVED(Yes/No)</td>
                                <td rowspan="2">ENROLLED(Yes/No)</td>
                                <td rowspan="2">CONFIRMED FEES</td>
                                <td rowspan="2">BENEFICIARY SIGNATURE</td>
                           </tr>
                            <tr>
                                <td>Beneficiary ID</td>
                                <td>FIRST NAME</td>
                                <td>SURNAME</td>
                                <td>HOME DISTRICT OF THE GIRL</td>
                            </tr>
                       </tbody>';
        //<td rowspan="2">AVERAGE MARKS(Previous Term)</td>
        $i = 1;
        //<td>'.$rec['beneficiary_school_status'].'</td>
        $total_fees = '';
        $counter = 0;
        foreach ($records as $rec) {//
            $previous_grade = $this->getPreviousTermdetails($term_id, $rec->school_grade);
            $ben_table .= '<tr>
                                <td>' . $i . '</td>
                               <td>' . $rec->beneficiary_id . '</td>
                               <td>' . aes_decrypt($rec->first_name) . '</td>
                               <td>' . aes_decrypt($rec->last_name) . '</td>
                               <td>' . $rec->hhh_district . '</td>
                                <td>' . $rec->school_grade . '</td>
                               <td>' . $rec->beneficiary_school_status . '</td>
                               <td>' . $rec->letter_received . '</td>
                               <td>' . $rec->is_enrolled . '</td>
                                <td>' . formatMoney($rec->confirmed_fees) . '</td>

                                <td>' . $rec->has_signed . '</td>
                                <td></td>
                            </tr><tr><td colspan="20">Remarks:' . $rec->remarks . '</td></tr>';
            $i++;
        }

        $ben_table .= '</table>';

        PDF::writeHTML($ben_table, true, false, false, false, 'L');

    }

    //get the checklist other details
    function getPaymentchecklistOtherdetails($rec, $term_name, $year, $term_id, $checklist_no = '', $isPromotion = false)
    {//frank
        if ($isPromotion == true || $isPromotion == 1) {
            $year = $year + 1;
        }
        PDF::AddPage('L');
        PDF::setMargins(8, 13, 8, true);
        PDF::SetAutoPageBreak(TRUE, 20);//true sets it to on and 0 means margin is zero from sides
        landscapereport_header($checklist_no);
        PDF::ln(4);        
        $school_table = '<table border="1" width="350" cellpadding="3">
                       <tbody>
                        <tr>
                           <td colspan="6"><b>GEWEL 2.0/KGS BENEFICIARY GIRLS - ' . $year . '</b></td>
                       </tr>
                       <tr>
                           <td colspan="4">Name of School:<br/><b>' . $rec->school_name . '</b></td>
                           <td colspan="2">EMIS Code:<br/><b>' . $rec->school_code . '</b></td>
                       </tr>
                        <tr>
                           <td colspan="3">School Type(Day, Boarding, Day/Boarding):</td>
                           <td colspan="3"><b>' . $rec->school_type . '</b></td>
                       </tr>
                        <tr>
                            <td colspan="1">Province of School:<br/><b>' . $rec->province_name . '</b></td>
                            <td colspan="1">District of School:<br/><b>' . $rec->district_name . '</b></td>
                            <td colspan="1">CWAC of School:<br/><b>' . $rec->cwac_name . '</b></td>
                            <td colspan="3">CWAC Contact Person \'s Phone No.:<br/><b>' . $rec->cwac_contacts . '</b></td>
                       </tr>
                       <tr>
                       <td colspan="3"> RUNNING AGENCY(1=GRZ 2=PRIVATE 3=GRANT-AIDED(FROM GRZ) 4=COMMUNITY)  </td>
                       <td colspan="3"></td>
                       </tr>
                        <tr>
                           <td colspan="3">School Headteacher: <br/><b>' . $rec->head_teacher . '</b></td>
                           <td colspan="3">Headteacher Phone No: <br/><b>' . $rec->head_contact . '</b></td>
                       </tr>
                        <tr>
                           <td colspan="3">School Guidance & Counselling Teacher: <br/><b></b></td>
                           <td colspan="3">Guidance & Counselling Teacher Phone No: <br/><b></b></td>
                       </tr>
                        <tr>
                            <td colspan="3">Bank Name: <br/><b>' . $rec->bank_name . '</b></td>
                            <td colspan="3">Branch Name: <br/><b>' . $rec->branch_name . '</b></td>

                       </tr>
                        <tr>
                            <td colspan="3">Account No: <br/><b>' . aes_decrypt($rec->account_no) . '</b></td>
                            <td colspan="3">Sort Code: <br/><b>' . $rec->sort_code . '</b></td>

                       </tr>
                       </tbody>
                       </table>';

        PDF::writeHTML($school_table, true, true);
        $qry_grade = DB::table('school_grades')
            // ->whereIn('id', array(8, 9, 10, 11, 12));
            ->whereIn('id', array(4, 5, 6, 7, 8, 9, 10, 11, 12));
        $results_grade = $qry_grade->get();
        $fees_table = '';
        if (count($results_grade) > 0) {
            $fees_table = '<table border="1" width="350" cellpadding="3">';
            //
            $enrollments = getSchoolenrollments($rec->school_type_id);
            $col_span = 7;// count($enrollments) + 1;
            $fees_table .= '
                        <tr>
                           <td colspan="' . $col_span . '"><b>Termly School Fees for ' . $year . ' (ZMW)</b></td>
                       </tr>';

            $fees_table .= '<tr>
                                <td rowspan="2">School Grade</td>
                                <td colspan="2">Term 1</td>
                                <td colspan="2">Term 2</td>
                                <td colspan="2">Term 3</td>
                           </tr>
                           <tr>
                                <td>Day</td><td>Border</td>
                                <td>Day</td><td>Border</td>
                                <td>Day</td><td>Border</td>
                           </tr>';
            foreach ($results_grade as $rec_grade) {
                $fees_table .= '<tr><td>' . $rec_grade->name . ' </td>';
                for ($i = 1; $i < $col_span; $i++) {
                    $fees_table .= '<td></td>';
                }
                $fees_table .= '</tr>';
            }
            $fees_table .= '</tbody>
                        </table>';
        }
        PDF::setY(32);
        //PDF::Cell(200, 5, '<b>Table 1: School Information</b>', 0, 1, 'L');
        PDF::writeHTMLCell(0, 10, 12, 32,'<b>Table 1: School Information</b>', 0, 1, 0, true, 'L', true);
        PDF::setXY(140, 30);
        //PDF::Cell(200, 5, 'Table 2: Fees Information', 0, 1, 'L');
        PDF::writeHTMLCell(0, 10, 140, 32,'<b>Table 2: Fees Information</b>', 0, 1, 0, true, 'L', true);
        PDF::SetXY(140, 37);
        PDF::writeHTML($fees_table, true);

        $checklist_notes_data=DB::table('checklist_notes')->selectraw('note,note_order')->orderby('note_order','ASC')->get()->toArray();
        $checklist_notes="<ul>";
        foreach($checklist_notes_data as $note)
        {
            $checklist_notes.="<li>".$note->note."<li>";
        }
        $checklist_notes.="</ul>";
        $checklist_notes2 = ''; 
        //initial
        // PDF::writeHTMLCell(0, 10, 133, 82, $checklist_notes, 0, 1, 0, true, 'R', true);
        // PDF::writeHTMLCell(0, 10, 3, 100, $checklist_notes2, 0, 1, 0, true, 'R', true);
        PDF::writeHTMLCell(0, 10, 133, 120, $checklist_notes, 0, 1, 0, true, 'R', true);
        PDF::writeHTMLCell(0, 10, 3, 138, $checklist_notes2, 0, 1, 0, true, 'R', true);
    }

    public function printGeneratpayVerificationchk(Request $req)
    {
        $year_of_enrollment = $req->input('year_of_enrollment');
        $term_id = $req->input('term_name');//but gives id
        $school_id = $req->input('school_id');
        $sub_category = $req->input('sub_category');
        $verification_type = $req->input('verification_type');
        $term_details = getSingleRecord('school_terms', array('id' => $term_id));
        $term_name = 'NA';
        $district_id_kip = $req->input('district_id');
        $json_batch_id = $req->input('batch');        
        $batch_id = json_decode($json_batch_id);
        $category_id = $req->input('category');
        $print_filter = $req->input('print_filter');
        $grades = $req->input('grades');
        if (isset($school_id) && $school_id != '') {
            $table_name = 'school_information';
            $where_id = $school_id;
            $add = 'school';
            $where_txt = 't2.id';
        } else if (isset($district_id_kip) && $district_id_kip != '') {
            $table_name = 'districts';
            $where_id = $district_id_kip;
            $add = 'district';
            $where_txt = 't2.district_id';
        }
        $download_suffix = DB::table($table_name)
            ->where('id', $where_id)
            ->value('name');
        if ($term_details) {
            $term_name = aes_decrypt($term_details->name);
        }
        //get term id
        if (is_array(json_decode($req->district_id))) {
            $district_id = json_decode($req->district_id);
            $where_txt = 't2.district_id';
            $where_value = $district_id;
        } else {
            $district_id = $req->district_id;
            $district_id = array($district_id);
            $where_txt = 't2.district_id';
            $where_value = $district_id;
        }
        if (validateisNumeric($school_id)) {
            $school_id = array($school_id);
            $where_txt = 't2.id';
            $where_value = $school_id;
        }
        if (isset($grades) && is_array(json_decode($grades))) {
            $grades = json_decode($grades);
        } else {
            $grades = array();
        }
        $filename = 'Payment_checklist';
        PDF::SetTitle('Payment Verification Checklist');
        
        $qry = DB::table('beneficiary_information as t1')
            ->select(DB::raw("t2.school_type_id,t8.name as school_type,t7.name as bank_name,t10.name as branch_name,
                decrypt(t6.account_no) as account_no,t10.sort_code,t5.full_names as head_teacher,t5.mobile_no as head_contact,
                t1.school_id,t2.name as school_name,t2.code as school_code,t3.name as district_name,
                t9.name as cwac_name,t4.name as province_name,CONCAT_WS('/',decrypt(t11.mobile),decrypt(t11.phone)) as cwac_contacts"))
            ->join('school_information  as t2', 't1.school_id', '=', 't2.id')
            ->join('districts as t3', 't2.district_id', '=', 't3.id')
            ->join('provinces as t4', 't3.province_id', '=', 't4.id')
            ->leftJoin('school_contactpersons as t5', 't2.id', '=', 't5.school_id')
            ->leftJoin('school_bankinformation as t6', function ($join) {
                $join->on('t2.id', '=', 't6.school_id')
                    ->where('t6.is_activeaccount', 1);
            })
            ->leftJoin('bank_details as t7', 't6.bank_id', '=', 't7.id')
            ->leftJoin('bank_branches as t10', 't6.branch_name', '=', 't10.id')
            ->leftJoin('school_types as t8', 't2.school_type_id', '=', 't8.id')
            ->leftJoin('cwac as t9', 't2.cwac_id', '=', 't9.id')
            ->leftJoin('users as t11', 't9.contact_person_id', '=', 't11.id')
            ->where(array('enrollment_status' => 1, 'beneficiary_status' => 4))
            ->where('t1.under_promotion', 0)
            ->where('t1.payment_eligible', 1)
            ->where($where_txt, $where_id);
        getBeneficiarySchMatchingPlacementDetails($qry, $sub_category, 't1');
        // if (isset($batch_id) && $batch_id != '') {
        //     $qry->where('t1.batch_id', $batch_id);
        // }
        if(is_array($batch_id) && count($batch_id) > 0){
            $qry->whereIn('t1.batch_id',$batch_id);
        }
        if (isset($category_id) && $category_id != '') {
            $qry->where('t1.category', $category_id);
        }
        if (validateisNumeric($verification_type)) {
            $qry->where('t1.verification_type', $verification_type);
        }
        if (count($grades) > 0) {
            $qry->whereIn('t1.current_school_grade', $grades);
        }
        if (isset($print_filter) && $print_filter != '') {
            if ($print_filter == 1) {
                $qry->where('t1.payment_printed', 1);
            } else if ($print_filter == 2) {
                $qry->where('t1.payment_printed', '<>', 1)
                    ->orWhere(function ($query) {
                        $query->whereNull('t1.payment_printed');
                    });
            }
        }
        $qry->groupBy('t2.id');
        $results = $qry->get();
        foreach ($results as $rec) {
            $this->getPaymentChecklistDetails($rec, $term_name, $year_of_enrollment, $term_id, $batch_id, $category_id, $grades, $print_filter, false, $sub_category, $verification_type);
        }
    
        PDF::Output($filename . time() . '_' . $download_suffix . '_' . $add . '.pdf', 'I');
    }
     private function returnArrayFromStringArray($string_array)
    {

        $string_array=substr(trim($string_array), 0, -1);
        $final_array=explode(',' ,substr($string_array,1));
        return $final_array;
    }//job on 22/11/2022
    public function printPaymentVerificationChecklist(Request $req)
    {
       
        $year_of_enrollment = $req->input('year_of_enrollment');
        $term_id = $req->input('term_name');//but gives id
        $school_id = $req->input('school_id');
        $sub_category = $req->input('sub_category');
        $verification_type = $req->input('verification_type');
        $term_details = getSingleRecord('school_terms', array('id' => $term_id));
        $term_name = 'NA';
        $district_id_kip = $req->input('district_id');
        $json_batch_id = $req->input('batch');        
        $batch_id = json_decode($json_batch_id);
        $category_id = $req->input('category');
        $print_filter = $req->input('print_filter');
        $grades = $req->input('grades');
        $enrollment_status = $req->input('enrollment_status');//job on 22/11/2022
        $school_status=$req->input('school_status');//job on 22/11/2022
        if (isset($school_status) && $school_status != '') 
        {
            $school_status=$this->returnArrayFromStringArray($school_status);

        }
        //job
        if (isset($school_id) && $school_id != '') {
            $table_name = 'school_information';
            $where_id = $school_id;
            $add = 'school';
            $where_txt = 't2.id';
        } else if (isset($district_id_kip) && $district_id_kip != '') {
            $table_name = 'districts';
            $where_id = $district_id_kip;
            $add = 'district';
            $where_txt = 't2.district_id';
        }
        $download_suffix = DB::table($table_name)
            ->where('id', $where_id)
            ->value('name');
        if ($term_details) {
            $term_name = aes_decrypt($term_details->name);
        }
        //get term id
        if (is_array(json_decode($req->district_id))) {
            $district_id = json_decode($req->district_id);
            $where_txt = 't2.district_id';
            $where_value = $district_id;
        } else {
            $district_id = $req->district_id;
            $district_id = array($district_id);
            $where_txt = 't2.district_id';
            $where_value = $district_id;
        }
        if (validateisNumeric($school_id)) {
            $school_id = array($school_id);
            $where_txt = 't2.id';
            $where_value = $school_id;
        }
        if (isset($grades) && is_array(json_decode($grades))) {
            $grades = json_decode($grades);
        } else {
            $grades = array();
        }

        $filename = 'Payment_checklist';
        PDF::SetTitle('Payment Verification Checklist');
        
        $qry = DB::table('beneficiary_information as t1')
            ->select(DB::raw("t2.school_type_id,t8.name as school_type,t7.name as bank_name,t10.name as branch_name,t12.has_signed,
                decrypt(t6.account_no) as account_no,t10.sort_code,t5.full_names as head_teacher,t5.mobile_no as head_contact,
                t1.school_id,t2.name as school_name,t2.code as school_code,t3.name as district_name,
                t9.name as cwac_name,t4.name as province_name,CONCAT_WS('/',decrypt(t11.mobile),decrypt(t11.phone)) as cwac_contacts"))
             ->leftjoin('beneficiary_enrollments as t12','t12.beneficiary_id','t1.id')//job on 21/11/2022
            ->join('school_information  as t2', 't1.school_id', '=', 't2.id')
            ->join('districts as t3', 't2.district_id', '=', 't3.id')
            ->join('provinces as t4', 't3.province_id', '=', 't4.id')
            ->leftJoin('school_contactpersons as t5', 't2.id', '=', 't5.school_id')
            ->leftJoin('school_bankinformation as t6', function ($join) {
                $join->on('t2.id', '=', 't6.school_id')
                    ->where('t6.is_activeaccount', 1);
            })
            ->leftJoin('bank_details as t7', 't6.bank_id', '=', 't7.id')
            ->leftJoin('bank_branches as t10', 't6.branch_name', '=', 't10.id')
            ->leftJoin('school_types as t8', 't2.school_type_id', '=', 't8.id')
            ->leftJoin('cwac as t9', 't2.cwac_id', '=', 't9.id')
            ->leftJoin('users as t11', 't9.contact_person_id', '=', 't11.id')
            ->where(array('enrollment_status' => 1, 'beneficiary_status' => 4))
            // ->where(array('enrollment_status' => 1))
            ->wherein('t1.under_promotion', [0,1])
            // ->where('t1.under_promotion', 0)
            ->where('t1.payment_eligible', 1)
            ->where($where_txt, $where_id);
        getBeneficiarySchMatchingPlacementDetails($qry, $sub_category, 't1');
         if (isset($school_status) && $school_status != '') //job  on 22/11/2022
        {
            $qry->whereIn('t12.beneficiary_schoolstatus_id',$school_status);

        }
 

         if (isset($enrollment_status) && $enrollment_status != '') {//job on 21/11/2022
            $qry->where('year_of_enrollment', $year_of_enrollment);
         }

        if(is_array($batch_id) && count($batch_id)>0){
            $qry->whereIn('t1.batch_id',$batch_id);
        }
        if (isset($category_id) && $category_id != '') {
            $qry->where('t1.category', $category_id);
        }
        if (validateisNumeric($verification_type)) {
            $qry->where('t1.verification_type', $verification_type);
        }
        if (count($grades) > 0) {
            $qry->whereIn('t1.current_school_grade', $grades);
        }
        if (isset($print_filter) && $print_filter != '') {
            if ($print_filter == 1) {
                $qry->where('t1.payment_printed', 1);
            } else if ($print_filter == 2) {
                $qry->where('t1.payment_printed', '<>', 1)
                    ->orWhere(function ($query) {
                        $query->whereNull('t1.payment_printed');
                    });
            }
        }
        $qry->groupBy('t2.id');
       
        $results = $qry->get();
        // $results2 = array();
        // foreach ($results as $rec) {
        //     if($rec->has_signed!=1)
        //     {
        //         $results2[]= $rec;
        //     }
        // }
        foreach ($results as $rec) {
            $this->getPaymentChecklistDetails($rec, $term_name, $year_of_enrollment, $term_id, $batch_id, $category_id, $grades, $print_filter, false, $sub_category, $verification_type,$enrollment_status,$school_status);
        }
    
        PDF::Output($filename . time() . '_' . $download_suffix . '_' . $add . '.pdf', 'I');
    }

    public function printPromotionPaymentChecklists(Request $req)
    {
        $promotion_year = $req->input('prom_year');
        $batch_id = $req->input('batch_id');
        $category_id = $req->input('category_id');
        $school_id = $req->input('school_id');
        $district_id_kip = $req->input('district_id');
        $where_id = '';
        if (isset($school_id) && is_numeric($school_id)) {
            $table_name = 'school_information';
            $where_id = $school_id;
            $add = 'school';
            $where_txt = 't2.id';
        } else if (isset($district_id_kip) && is_numeric($district_id_kip)) {
            $table_name = 'districts';
            $where_id = $district_id_kip;
            $add = 'district';
            $where_txt = 't2.district_id';
        }
        $download_suffix = DB::table($table_name)
            ->where('id', $where_id)
            ->value('name');

        $filename = 'Payment_checklist';
        PDF::SetTitle('Payment Verification Checklist');

        $qry = DB::table('grade_nines_for_promotion as t0')
            ->join('beneficiary_information as t1', 't0.girl_id', '=', 't1.id')
            ->join('school_information  as t2', 't1.school_id', '=', 't2.id')
            ->join('districts as t3', 't2.district_id', '=', 't3.id')
            ->join('provinces as t4', 't3.province_id', '=', 't4.id')
            ->leftJoin('school_contactpersons as t5', 't2.id', '=', 't5.school_id')
            ->leftJoin('school_bankinformation as t6', function ($join) {
                $join->on('t2.id', '=', 't6.school_id')
                    ->where('t6.is_activeaccount', 1);
            })
            ->leftJoin('bank_details as t7', 't6.bank_id', '=', 't7.id')
            ->leftJoin('bank_branches as t10', 't6.branch_name', '=', 't10.id')
            ->leftJoin('school_types as t8', 't2.school_type_id', '=', 't8.id')
            ->leftJoin('cwac as t9', 't2.cwac_id', '=', 't9.id')
            //->join('gradenine_promotions as t11', 't0.girl_id', '=', 't11.girl_id')
            ->join('gradenine_promotions as t11', function ($join) {
                $join->on('t0.girl_id', '=', 't11.girl_id')
                    ->on('t0.promotion_year', '=', 't11.promotion_year');
            })
            ->leftJoin('users as t12', 't9.contact_person_id', '=', 't12.id')
            ->select(DB::raw('t2.school_type_id,t8.name as school_type,t7.name as bank_name,t10.name as branch_name,
                decrypt(t6.account_no) as account_no,t10.sort_code,t5.full_names as head_teacher,t5.mobile_no as head_contact,
                t1.school_id,t2.name as school_name,t2.code as school_code,t3.name as district_name,
                t9.name as cwac_name,t4.name as province_name,CONCAT_WS(\'/\',decrypt(t12.mobile),decrypt(t12.phone)) as cwac_contacts'))
            ->where('enrollment_status', 1)
            ->where('beneficiary_status', 4)
            ->where('t0.promotion_year', $promotion_year)
            ->where($where_txt, $where_id)
            ->where('t0.stage', 3)
            ->whereIn('t11.qualified', array(1, 2));
        if (is_numeric($batch_id)) {
            $qry->where('t1.batch_id', $batch_id);
        }
        if (is_numeric($category_id)) {
            $qry->where('t1.category', $category_id);
        }
        $qry->groupBy('t2.id');
        $results = $qry->get();
        foreach ($results as $rec) {
            $this->getPaymentChecklistDetails($rec, '', $promotion_year, '', $batch_id, $category_id, array(), '', 1);
        }
        PDF::Output($filename . time() . '_' . $download_suffix . '_' . $add . '.pdf', 'I');
    }

    public function printRevokedPromotionPaymentChecklists(Request $req)
    {
        $promotion_year = $req->input('prom_year');
        $batch_id = $req->input('batch_id');
        $category_id = $req->input('category_id');
        $school_id = $req->input('school_id');
        $district_id_kip = $req->input('district_id');
        $where_id = '';
        $where_txt = '';
        if (isset($school_id) && is_numeric($school_id)) {
            $table_name = 'school_information';
            $where_id = $school_id;
            $add = 'school';
            $where_txt = 't2.id';
        } else if (isset($district_id_kip) && is_numeric($district_id_kip)) {
            $table_name = 'districts';
            $where_id = $district_id_kip;
            $add = 'district';
            $where_txt = 't2.district_id';
        }
        $download_suffix = DB::table($table_name)
            ->where('id', $where_id)
            ->value('name');

        $filename = 'Payment_checklist';
        PDF::SetTitle('Payment Verification Checklist');
        $qry = DB::table('revoked_gradenine_promotions as t00')
            ->join('grade_nines_for_promotion as t0', 't00.promotion_id', '=', 't0.id')
            ->join('beneficiary_information as t1', 't0.girl_id', '=', 't1.id')
            ->join('school_information  as t2', 't1.school_id', '=', 't2.id')
            ->join('districts as t3', 't2.district_id', '=', 't3.id')
            ->join('provinces as t4', 't3.province_id', '=', 't4.id')
            ->leftJoin('school_contactpersons as t5', 't2.id', '=', 't5.school_id')
            ->leftJoin('school_bankinformation as t6', function ($join) {
                $join->on('t2.id', '=', 't6.school_id')
                    ->where('t6.is_activeaccount', 1);
            })
            ->leftJoin('bank_details as t7', 't6.bank_id', '=', 't7.id')
            ->leftJoin('bank_branches as t10', 't6.branch_name', '=', 't10.id')
            ->leftJoin('school_types as t8', 't2.school_type_id', '=', 't8.id')
            ->leftJoin('cwac as t9', 't2.cwac_id', '=', 't9.id')
            ->leftJoin('users as t11', 't9.contact_person_id', '=', 't11.id')
            ->select(DB::raw('t2.school_type_id,t8.name as school_type,t7.name as bank_name,t10.name as branch_name,
                decrypt(t6.account_no) as account_no,t10.sort_code,t5.full_names as head_teacher,t5.mobile_no as head_contact,
                t1.school_id,t2.name as school_name,t2.code as school_code,t3.name as district_name,
                t9.name as cwac_name,t4.name as province_name,CONCAT_WS(\'/\',decrypt(t11.mobile),decrypt(t11.phone)) as cwac_contacts'))
            ->where('enrollment_status', 1)
            ->where('beneficiary_status', 4)
            ->where('t0.promotion_year', $promotion_year)
            ->where($where_txt, $where_id);
        if (is_numeric($batch_id)) {
            $qry->where('t1.batch_id', $batch_id);
        }
        if (is_numeric($category_id)) {
            $qry->where('t1.category', $category_id);
        }
        $qry->groupBy('t2.id');
        $results = $qry->get();

        foreach ($results as $rec) {
            $this->getPaymentChecklistDetails($rec, '', $promotion_year, '', $batch_id, $category_id, array(), '', 2);
        }
        PDF::Output($filename . time() . '_' . $download_suffix . '_' . $add . '.pdf', 'I');
    }

    function getPaymentChecklistDetails($rec, $term_name, $year, $term_id, $batch_id, $category, $grades, $print_filter, $is_promo = false, $sub_category = 0, $verification_type = 0,$enrollment_status="",$school_status="")
    {
        //PDF::Output("filenmeee" . time() . '_' . "suffx" . '_' .'addd' . '.pdf', 'I');
        $checklist_no = $rec->school_id . '-' . \Auth::user()->id . '-' . time();
        $this->getPaymentchecklistOtherdetails($rec, $term_name, $year, $term_id, $checklist_no, $is_promo);
        //$this->getPaymentchecklistBeneficairydetails($rec->school_id, $year, $term_id, $batch_id, $category, $grades, $print_filter, $checklist_no, $is_promo, $sub_category, $verification_type);
        $this->getGenericPaymentChecklistBeneficiaryDetails($rec->school_id, $year, $term_id, $batch_id, $category, $grades, $print_filter, $checklist_no, $is_promo, $sub_category, $verification_type, 1,'',$enrollment_status,$school_status);
    }

    public function printFollowupPaymentChecklists(Request $req)
    {
        $year_of_enrollment = $req->input('year');
        $term_id = $req->input('term');
        $school_id = $req->input('school_id');
        $term_details = getSingleRecord('school_terms', array('id' => $term_id));
        $term_name = 'NA';
        $district_id_kip = $req->input('district_id');
        if (isset($school_id) && $school_id != '') {
            $table_name = 'school_information';
            $where_id = $school_id;
            $add = 'school';
            $where_txt = 't2.id';
        } else if (isset($district_id_kip) && $district_id_kip != '') {
            $table_name = 'districts';
            $where_id = $district_id_kip;
            $add = 'district';
            $where_txt = 't2.district_id';
        }
        $download_suffix = DB::table($table_name)
            ->where('id', $where_id)
            ->value('name');
        if ($term_details) {
            $term_name = aes_decrypt($term_details->name);
        }
        //get term id
        if (is_array(json_decode($req->district_id))) {
            $district_id = json_decode($req->district_id);
            $where_txt = 't2.district_id';
        } else {
            $district_id = $req->district_id;
            $district_id = array($district_id);
            $where_txt = 't2.district_id';
        }
        if (validateisNumeric($school_id)) {
            $school_id = array($school_id);
            $where_txt = 't2.id';
        }
        $filename = 'Payment_checklist';
        PDF::SetTitle('Payment Verification Checklist');
        $qry = DB::table('beneficiary_information as t1')
            ->select(DB::raw('t9.name as cwac_name,t2.school_type_id,t8.name as school_type,t7.name as bank_name,
                t10.name as branch_name, decrypt(t6.account_no) as account_no, t10.sort_code, t5.full_names as head_teacher, t5.mobile_no as head_contact,
                t1.school_id, t2.name as school_name, t2.code as school_code, t3.name as district_name, t4.name as province_name,CONCAT_WS(\'/\',decrypt(t11.mobile),decrypt(t11.phone)) as cwac_contacts'))
            //->select('t9.name as cwac_name','t2.school_type_id', 't8.name as school_type', 't7.name as bank_name', 't10.name as branch_name', 't6.account_no', 't10.sort_code', 't5.full_names as head_teacher', 't5.mobile_no as head_contact', 't1.school_id', 't2.name as school_name', 't2.code as school_code', 't3.name as district_name', 't4.name as province_name')
            ->join('school_information  as t2', 't1.school_id', '=', 't2.id')
            ->join('districts as t3', 't2.district_id', '=', 't3.id')
            ->join('provinces as t4', 't3.province_id', '=', 't4.id')
            ->join('beneficiary_enrollments as e1', 't1.id', '=', 'e1.beneficiary_id')
            ->leftJoin('school_contactpersons as t5', 't2.id', '=', 't5.school_id')
            ->leftJoin('school_bankinformation as t6', function ($join) {
                $join->on('t2.id', '=', 't6.school_id')
                    ->where('t6.is_activeaccount', 1);
            })
            ->leftJoin('bank_details as t7', 't6.bank_id', '=', 't7.id')
            ->leftJoin('bank_branches as t10', 't6.branch_name', '=', 't10.id')
            ->leftJoin('school_types as t8', 't2.school_type_id', '=', 't8.id')
            ->leftJoin('cwac as t9', 't2.cwac_id', '=', 't9.id')
            ->leftJoin('users as t11', 't9.contact_person_id', '=', 't11.id')
            ->where($where_txt, $where_id)
            ->where('e1.year_of_enrollment', $year_of_enrollment)
            ->where(function ($query) {
                $query->where('e1.is_validated', 0)
                    ->orWhereNull('e1.is_validated');
            });
        $qry->groupBy('t2.id');
        $results = $qry->get();
        foreach ($results as $rec) {
            $this->getFollowupPaymentChecklistDetails($rec, $term_name, $year_of_enrollment, 0);
        }
        PDF::Output($filename . time() . '_' . $download_suffix . '_' . $add . '.pdf', 'I');
    }

    function getFollowupPaymentChecklistDetails($rec, $term_name, $year, $for_not_paid, $batch_id = '')
    {
        $checklist_no = $rec->school_id . '-' . \Auth::user()->id . '-' . time();
        $this->getPaymentchecklistOtherdetails($rec, $term_name, $year, '', $checklist_no);
        //$this->getFollowupPaymentChecklistBeneficiaryDetails($rec->school_id, $year, $for_not_paid, $checklist_no, $batch_id);
        $this->getGenericPaymentChecklistBeneficiaryDetails($rec->school_id, $year, '', $batch_id, '', '', '', $checklist_no, false, '', '', 4, $for_not_paid);
    }

    public function printUnprintedPaymentChecklists(Request $req)
    {
        $year_of_enrollment = $req->input('year');
        $school_id = $req->input('school_id');
        $batch_id = $req->input('batch_id');
        $district_id_kip = $req->input('district_id');
        $category_id = $req->input('category');
        $verification_type = $req->input('verification_type');
        $grades = $req->input('grades');
        if (isset($school_id) && $school_id != '') {
            $table_name = 'school_information';
            $where_id = $school_id;
            $add = 'school';
            $where_txt = 't2.id';
        } else if (isset($district_id_kip) && $district_id_kip != '') {
            $table_name = 'districts';
            $where_id = $district_id_kip;
            $add = 'district';
            $where_txt = 't2.district_id';
        }
        $download_suffix = DB::table($table_name)
            ->where('id', $where_id)
            ->value('name');
        if (is_array(json_decode($req->district_id))) {
            $district_id = json_decode($req->district_id);
            $where_txt = 't2.district_id';
            $where_value = $district_id;
        } else {
            $district_id = $req->district_id;
            $district_id = array($district_id);
            $where_txt = 't2.district_id';
            $where_value = $district_id;
        }
        if (validateisNumeric($school_id)) {
            $school_id = array($school_id);
            $where_txt = 't2.id';
            $where_value = $school_id;
        }
        if (isset($grades) && is_array(json_decode($grades))) {
            $grades = json_decode($grades);
        } else {
            $grades = array();
        }
        $filename = 'Payment_checklist';
        PDF::SetTitle('Payment Verification Checklist');

        $qry = DB::table('beneficiary_information as t1')
            ->select(DB::raw('t2.school_type_id,t8.name as school_type,t7.name as bank_name,t10.name as branch_name,
                decrypt(t6.account_no) as account_no,t10.sort_code,t5.full_names as head_teacher,t5.mobile_no as head_contact,
                t1.school_id,t2.name as school_name,t2.code as school_code,t3.name as district_name,
                t9.name as cwac_name,t4.name as province_name,CONCAT_WS(\'/\',decrypt(t11.mobile),decrypt(t11.phone)) as cwac_contacts'))
            ->join('school_information  as t2', 't1.school_id', '=', 't2.id')
            ->join('districts as t3', 't2.district_id', '=', 't3.id')
            ->join('provinces as t4', 't3.province_id', '=', 't4.id')
            ->leftJoin('school_contactpersons as t5', 't2.id', '=', 't5.school_id')
            ->leftJoin('school_bankinformation as t6', function ($join) {
                $join->on('t2.id', '=', 't6.school_id')
                    ->where('t6.is_activeaccount', 1);
            })
            ->leftJoin('bank_details as t7', 't6.bank_id', '=', 't7.id')
            ->leftJoin('bank_branches as t10', 't6.branch_name', '=', 't10.id')
            ->leftJoin('school_types as t8', 't2.school_type_id', '=', 't8.id')
            ->leftJoin('cwac as t9', 't2.cwac_id', '=', 't9.id')
            ->leftJoin('users as t11', 't9.contact_person_id', '=', 't11.id')
            ->where(array('enrollment_status' => 1, 'beneficiary_status' => 4))
            ->where('t1.under_promotion', 0)
            ->where('t1.payment_eligible', 1)
            ->where($where_txt, $where_id)
            ->whereNotIn('t1.id', function ($query) use ($year_of_enrollment) {
                $query->select(DB::raw('girl_id'))
                    ->from('vw_paymentchecklist_generations')
                    ->where('year', $year_of_enrollment);
            });
        if (validateisNumeric($verification_type)) {
            $qry->where('t1.verification_type', $verification_type);
        }
        if (isset($category_id) && $category_id != '') {
            $qry->where('t1.category', $category_id);
        }
        if (count($grades) > 0) {
            $qry->whereIn('t1.current_school_grade', $grades);
        }
        if (validateisNumeric($batch_id)) {
            $qry->where('t1.batch_id', $batch_id);
        }
        $qry->groupBy('t2.id');
        $results = $qry->get();
        foreach ($results as $rec) {
            $this->getFollowupPaymentChecklistDetails($rec, '', $year_of_enrollment, 1, $batch_id);
        }
        PDF::Output($filename . time() . '_' . $download_suffix . '_' . $add . '.pdf', 'I');
    }

    function returnUnprintedBeneficiaryChecklistQuery($school_id, $year, $batch_id = '')
    {
        $qry = DB::table('beneficiary_information as t1')
            ->select(DB::raw('t1.id,t1.beneficiary_id,t1.household_id,t1.exam_school_id,t1.school_id,t1.cwac_id,t1.acc_id,t1.ward_id,t1.constituency_id,t1.district_id,t1.province_id,
                              decrypt(t1.first_name) as first_name,decrypt(t1.last_name) as last_name,t1.dob,t1.highest_grade,t1.exam_grade,t1.current_school_grade,t1.beneficiary_school_status,
                              t7.name as beneficiary_school_status,t5.hhh_fname,t6.name as hhh_district'))
            ->leftJoin('beneficiary_school_statuses as t7', 't1.beneficiary_school_status', '=', 't7.id')
            ->leftJoin('households as t5', 't1.household_id', '=', 't5.id')
            ->leftJoin('cwac as t8', 't5.cwac_id', '=', 't8.id')
            ->leftJoin('districts as t6', 't1.district_id', '=', 't6.id')
            ->where(array('t1.school_id' => $school_id, 'enrollment_status' => 1, 'beneficiary_status' => 4))
            ->where('t1.under_promotion', 0)
            ->where('t1.payment_eligible', 1)
            ->whereNotIn('t1.id', function ($query) use ($year) {
                $query->select(DB::raw('girl_id'))
                    ->from('vw_paymentchecklist_generations')
                    ->where('year', $year);
            });
        if (isset($batch_id) && is_numeric($batch_id)) {
            $qry->where('t1.batch_id', $batch_id);
        }
        if(is_array($batch_id) && count($batch_id) > 0){
            $qry->whereIn('t1.batch_id',$batch_id);
        }
        if (isset($category) && is_numeric($category)) {
            $qry->where('t1.category', $category);
        }
        if (validateisNumeric($batch_id)) {
            $qry->where('t1.batch_id', $batch_id);
        }
        if(is_array($batch_id) && count($batch_id) > 0){
            $qry->whereIn('t1.batch_id',$batch_id);
        }
        $qry->groupBy('t1.id');
        $results = $qry->get();
        return $results;
    }

    public function printSpecificPaymentChecklists(Request $req)
    {//frank
        $year_of_enrollment = $req->input('year');
        $term_id = $req->input('term');
        $school_id = $req->input('school_id');
        $values = $req->input('values');
        $girl_ids = json_decode($values);
        $term_details = getSingleRecord('school_terms', array('id' => $term_id));
        $term_name = 'NA';
        if (!validateisNumeric($school_id)) {  
            if (empty($girl_ids)) {
                echo 'Please select beneficiaries to appear in the list';
                exit();
            } else {
                $sch_qry = DB::table('beneficiary_information as t1')
                    ->whereIn('t1.id', $girl_ids)
                    ->groupBy('t1.school_id');
                $school_id = $sch_qry->pluck('t1.school_id');
                if(!validateisNumeric($school_id)) {                    
                    echo 'Please use the "More Summaries" option. The Selected beneficiaries dont have a school assigned';
                    exit();
                }
            }
        }
        $checklist_no = $school_id . '-' . \Auth::user()->id . '-' . time();
        if ($term_details) {
            $term_name = aes_decrypt($term_details->name);
        }
        $filename = 'Payment_checklist';
        PDF::SetTitle('Payment Verification Checklist');
        $sch_qry = DB::table('school_information as t1')
            ->join('provinces', 't1.province_id', '=', 'provinces.id')
            ->join('districts', 't1.district_id', '=', 'districts.id')
            ->leftJoin('school_contactpersons as t5', 't1.id', '=', 't5.school_id')
            ->leftJoin('school_bankinformation as t6', function ($join) {
                $join->on('t1.id', '=', 't6.school_id')
                    ->where('t6.is_activeaccount', 1);
            })
            ->leftJoin('bank_details as t7', 't6.bank_id', '=', 't7.id')
            ->leftJoin('bank_branches as t10', 't6.branch_name', '=', 't10.id')
            ->leftJoin('school_types as t8', 't1.school_type_id', '=', 't8.id')
            ->leftJoin('cwac as t9', 't1.cwac_id', '=', 't9.id')
            ->select(DB::raw('t1.id as school_id,t1.school_type_id, t7.name as bank_name, t10.name as branch_name, decrypt(t6.account_no) as account_no,
                t10.sort_code, t5.full_names as head_teacher, t5.mobile_no as head_contact, t8.name as school_type,
                t9.name as cwac_name,t1.code as school_code, t1.name as school_name, provinces.name as province_name,
                districts.name as district_name, t9.contact_person_phone as cwac_contacts'))
            ->where('t1.id', $school_id);
        $school_rec = $sch_qry->first();
        $qry = DB::table('beneficiary_information as t1')
            // ->select(DB::raw('CASE WHEN decrypt(t1.first_name) IS NULL THEN t1.first_name ELSE decrypt(t1.first_name) END as first_name, CASE WHEN decrypt(t1.last_name) IS NULL THEN t1.last_name ELSE decrypt(t1.last_name) END as last_name,
            //                   t1.id,t1.exam_grade,t1.current_school_grade,t1.beneficiary_id,t3.name as hhh_district,t4.name as province_name'))
            ->select(DB::raw('decrypt(t1.first_name) as first_name, 
                decrypt(t1.last_name) as last_name,
                t1.id,t1.exam_grade,t1.current_school_grade,t1.beneficiary_id,t3.name as hhh_district,t4.name as province_name'))
            ->join('school_information  as t2', 't1.school_id', '=', 't2.id')
            ->join('districts as t3', 't2.district_id', '=', 't3.id')
            ->join('provinces as t4', 't3.province_id', '=', 't4.id')
            ->whereIn('t1.id', $girl_ids)
            ->groupBy('t1.id');
        $results = $qry->get();
        $track_details = array(
            'year' => $year_of_enrollment,
            'term' => $term_id,
            'school_id' => $school_id,
            'checklist_number' => $checklist_no,
            'no_of_girls' => count($results),
            'created_at' => Carbon::now(),
            'created_by' => \Auth::user()->id
        );
        $track_id = DB::table('payment_checklists_track')->insertGetId($track_details);
        //$this->getPaymentchecklistOtherdetails($school_rec, $term_name, $year_of_enrollment, $term_id, $checklist_no);
        if(!is_null($school_rec)){
            $this->getPaymentchecklistOtherdetails($school_rec, $term_name, $year_of_enrollment, $term_id, $checklist_no);
        }
        PDF::SetY(115);
        $title = 'Table 3: Enrollment Details';
        $note = 'N/B: Please add extra girls on the blank rows. If the provided blank rows are not enough, feel free to add an extra sheet.';
        PDF::Cell(200, 5, $title, 0, 0, 'L');
        PDF::writeHTMLCell(0, 4, 10, 115, $note, 0, 1, 0, true, 'R', true);
        $ben_table = '<table border="1" cellpadding="3">
                       <tbody>
                           <tr>
                               <td rowspan="2" width="30">S/N</td>
                                <td colspan="4">BENEFICIARY INFORMATION</td>
                                <td rowspan="2">GRADE</td>
                                <td rowspan="2">CONFIRMED FORM/GRADE</td>
                                <td rowspan="2">CONFIRMED SCHOOL STATUS(Day/Boarder/Weekly Boarder)</td>
                                <td rowspan="2">LETTER RECEIVED(Yes/No)</td>
                                <td rowspan="2">ENROLLED(Yes/No)</td>
                                <td rowspan="2">Previous Grade</td>
                                <td colspan="2">MATHS(Prev Term)</td>
                                <td colspan="2">ENGLISH(Prev Term)</td>
                                <td colspan="2">SCIENCE(Prev Term)</td>
                                <td rowspan="2">No of Days Attended(Prev Term)</td>
                                <td rowspan="2">BENEFICIARY SIGNATURE</td>
                           </tr>
                            <tr>
                                <td >Benficiary ID</td>
                                <td >FIRST NAME</td>
                                <td>SURNAME</td>
                                <td >HOME DISTRICT OF THE GIRL</td>
                                <td>Score</td>
                                <td>Class Average </td>
                                <td>Score</td>
                                <td>Class Average </td>
                                <td>Score</td>
                                <td>Class Average </td>
                            </tr>
                       </tbody>';
        $i = 1;
        $printed = array();
        $girls_ids = array();
        foreach ($results as $rec) {
            // $previous_grade = $this->getPreviousTermdetails($term_id, $rec->current_school_grade);
            $ben_table .= '<tr>
                               <td width="30">' . $i . '</td>
                               <td>' . $rec->beneficiary_id . '</td>
                               <td>' . $rec->first_name . '</td>
                               <td>' . $rec->last_name . '</td>
                               <td>' . $rec->hhh_district . '</td>
                               <td align="center">' . $rec->current_school_grade . '</td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td align="center">' . $rec->exam_grade . '</td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                            </tr><tr><td colspan="19">Remarks:</td></tr>';
            $i++;
            $printed[] = array(
                'id' => $rec->id
            );
            $girls_ids[] = array(
                'girl_id' => $rec->id,
                'track_id' => $track_id,
                'created_at' => Carbon::now(),
                'created_by' => \Auth::user()->id
            );
        }//school_status
        $printed_ids = convertAssArrayToSimpleArray($printed, 'id');
        DB::table('beneficiary_information')
            ->whereIn('id', $printed_ids)
            ->update(array('payment_printed' => 1));
        DB::table('payment_checklists_track_details')->insert($girls_ids);
        $lower_limit = $i;
        $upper_limit = $lower_limit + 10;
        for ($j = $lower_limit; $j < $upper_limit; $j++) {
            $ben_table .= '<tr>
                               <td width="30">' . $j . '</td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                            </tr><tr><td colspan="19">Remarks:</td></tr>';
        }

        $ben_table .= '</table>';

        PDF::writeHTML($ben_table, true, false, false, false, 'L');

        PDF::Output($filename . time() . '.pdf', 'I');
    }

    public function downloadPrintedPaymentChecklists(Request $req)
    {
        $track_id = $req->input('track_id');
        $track_details = DB::table('payment_checklists_track')
            ->where('id', $track_id)
            ->first();
        if (is_null($track_details)) {
            echo 'No details found.';
            exit();
        }
        $year_of_enrollment = $track_details->year;
        $term_id = $track_details->term;
        $school_id = $track_details->school_id;
        $checklist_no = $track_details->checklist_number;
        //get girls in the checklist
        $girl_ids = DB::table('payment_checklists_track_details')
            ->select('girl_id')
            ->where('track_id', $track_id)
            ->get();
        $girl_ids = convertStdClassObjToArray($girl_ids);
        $girl_ids = convertAssArrayToSimpleArray($girl_ids, 'girl_id');

        $term_details = getSingleRecord('school_terms', array('id' => $term_id));
        $term_name = 'NA';
        if ($term_details) {
            $term_name = aes_decrypt($term_details->name);
        }
        $filename = 'Payment_checklist';
        PDF::SetTitle('Payment Verification Checklist');

        $sch_qry = DB::table('school_information as t1')
            ->join('provinces', 't1.province_id', '=', 'provinces.id')
            ->join('districts', 't1.district_id', '=', 'districts.id')
            ->leftJoin('school_contactpersons as t5', 't1.id', '=', 't5.school_id')
            ->leftJoin('school_bankinformation as t6', function ($join) {
                $join->on('t1.id', '=', 't6.school_id')
                    ->where('t6.is_activeaccount', 1);
            })
            ->leftJoin('bank_details as t7', 't6.bank_id', '=', 't7.id')
            ->leftJoin('bank_branches as t10', 't6.branch_name', '=', 't10.id')
            ->leftJoin('school_types as t8', 't1.school_type_id', '=', 't8.id')
            ->leftJoin('cwac as t9', 't1.cwac_id', '=', 't9.id')
            ->leftJoin('users as t11', 't9.contact_person_id', '=', 't11.id')
            ->select(DB::raw('t1.id as school_id, t9.name as cwac_name, t1.school_type_id, t7.name as bank_name,
                t10.name as branch_name, decrypt(t6.account_no) as account_no, t10.sort_code, t5.full_names as head_teacher, t5.mobile_no as head_contact,
                t8.name as school_type, t1.code as school_code, t1.name as school_name, provinces.name as province_name, districts.name as district_name,
                CONCAT_WS(\'/\',decrypt(t11.mobile),decrypt(t11.phone)) as cwac_contacts'))
            //->select('t1.id as school_id', 't9.name as cwac_name', 't1.school_type_id', 't7.name as bank_name', 't10.name as branch_name', 't6.account_no', 't10.sort_code', 't5.full_names as head_teacher', 't5.mobile_no as head_contact', 't8.name as school_type', 't1.code as school_code', 't1.name as school_name', 'provinces.name as province_name', 'districts.name as district_name')
            ->where('t1.id', $school_id);
        $school_rec = $sch_qry->first();
        $qry = DB::table('beneficiary_information as t1')
            ->select(DB::raw('CASE WHEN decrypt(t1.first_name) IS NULL THEN t1.first_name ELSE decrypt(t1.first_name) END as first_name, CASE WHEN decrypt(t1.last_name) IS NULL THEN t1.last_name ELSE decrypt(t1.last_name) END as last_name,t1.id,t1.exam_grade,t1.current_school_grade,t1.beneficiary_id,t3.name as hhh_district,t4.name as province_name'))
            ->join('school_information  as t2', 't1.school_id', '=', 't2.id')
            ->join('districts as t3', 't2.district_id', '=', 't3.id')
            ->join('provinces as t4', 't3.province_id', '=', 't4.id')
            ->whereIn('t1.id', $girl_ids);
        $results = $qry->get();

        $this->getPaymentchecklistOtherdetails($school_rec, $term_name, $year_of_enrollment, $term_id, $checklist_no);//kip here

        $ben_table = '<table border="1" cellpadding="3">
                       <tbody>
                           <tr>
                               <td rowspan="2" width="30">S/N</td>
                                <td colspan="4" align="center">Beneficiary Information</td>
                                <td rowspan="2">Grade</td>
                                <td rowspan="2">Confirmed Form/Grade</td>
                                <td rowspan="2">Confirmed School Status(Day/Boarder/Weekly Boarder)</td>
                                <!--<td rowspan="2">Letter Received(Yes/No)</td>-->
                                <td colspan="2" align="center">Weekly Boarders ONLY</td>
                                <td rowspan="2">Enrolled(Yes/No)</td>
                                <td rowspan="2">Previous Grade</td>
                                <td rowspan="2">Mobile Phone No. For Guardian/Parent</td>
                                <td colspan="3" align="center">Grade 9s & 12s ONLY</td>
                                <td rowspan="2">Beneficiary Signature</td>
                           </tr>
                            <tr>
                                <td>Beneficiary ID</td>
                                <td>First Name</td>
                                <td>Surname</td>
                                <td>Home District of the Girl</td>
                                <td>If Weekly Boarder, Is she renting a Private or School managed WB facility? {Private/School managed}</td>
                                <td>If she is renting a Private Weekly Boarding facility, Has she got a signed consent form from Parents/Guardians? {Yes/No}</td>
                                <td>Examination Number</td>
                                <td>Examination Fees</td>
                                <td>Is [girl] a GCE/External candidate? {Yes/No}</td>
                            </tr>
                       </tbody>';
        $i = 1;
        foreach ($results as $rec) {
            $ben_table .= '<tr>
                               <td width="30">' . $i . '</td>
                               <td>' . $rec->beneficiary_id . '</td>
                               <td>' . $rec->first_name . '</td>
                               <td>' . $rec->last_name . '</td>
                               <td>' . $rec->hhh_district . '</td>
                               <td align="center">' . $rec->current_school_grade . '</td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td align="center">' . ($rec->current_school_grade - 1) . '</td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>';
            $ben_table .= '</tr><tr><td colspan="17">Remarks:</td></tr>';
            $i++;

        }
        $lower_limit = $i;
        $upper_limit = $lower_limit + 10;
        for ($j = $lower_limit; $j < $upper_limit; $j++) {
            $ben_table .= '<tr>
                               <td width="30">' . $j . '</td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                            </tr><tr><td colspan="17">Remarks:</td></tr>';
        }

        $ben_table .= '</table>';
        PDF::writeHTML($ben_table, true, false, false, false, 'L');
        PDF::Output($filename . time() . '.pdf', 'I');
    }

    function getPreviousTermdetails($term_id, $school_grade)
    {

        if ($term_id == 1) {
            if ($school_grade == 8 || $school_grade == 10) {
                $school_grade = '-';
            } else {
                $school_grade = $school_grade - 1;
            }
        } else if ($term_id == 2) {
            $term_id = 1;
        } else {
            $term_id = 2;
        }
        return $school_grade;
    }

   //job on 29/05/2023
    function returnBeneciaryChecklistQuery($school_id, $batch_id, $category, $grades, $print_filter, $sub_category, $verification_type,$enrollment_status="",$year_of_enrollment="",$school_status="")
    {
        $qry = DB::table('beneficiary_information as t1')
            ->select(DB::raw('t1.id,t1.beneficiary_id,t1.household_id,t1.exam_school_id,t1.school_id,t1.cwac_id,t1.acc_id,t1.ward_id,t1.constituency_id,t1.district_id,t1.province_id,
              decrypt(t1.first_name) as first_name,decrypt(t1.last_name) as last_name,t1.dob,t1.highest_grade,t1.exam_grade,t1.current_school_grade,t1.beneficiary_school_status,
              t7.name as beneficiary_school_status,t5.hhh_fname,t6.name as hhh_district'))
             //->leftjoin('beneficiary_enrollments as t12','t12.beneficiary_id','t1.id')//job on 22/11/2022
            ->leftJoin('beneficiary_school_statuses as t7', 't1.beneficiary_school_status', '=', 't7.id')
            ->leftJoin('households as t5', 't1.household_id', '=', 't5.id')
            ->leftJoin('cwac as t8', 't5.cwac_id', '=', 't8.id')
            ->leftJoin('districts as t6', 't1.district_id', '=', 't6.id')
            ->where(array('t1.school_id' => $school_id, 'enrollment_status' => 1, 'beneficiary_status' => 4))
            ->wherein('t1.under_promotion', [0,1])
            ->where('t1.payment_eligible', 1);

        getBeneficiarySchMatchingPlacementDetails($qry, $sub_category, 't1');

        
       
        if (isset($school_status) && is_array($school_status)) {
            $qry->whereIn('t12.beneficiary_schoolstatus_id',$school_status);
        }
        //end job on 22/11/2022
        // if (isset($batch_id) && is_numeric($batch_id)) {
        //     $qry->where('t1.batch_id', $batch_id);
        // }
        //frank
        if(is_array($batch_id) && count($batch_id) > 0){
            $qry->whereIn('t1.batch_id',$batch_id);
        }
        if (isset($category) && is_numeric($category)) {
            $qry->where('t1.category', $category);
        }
        if (validateisNumeric($verification_type)) {
            $qry->where('t1.verification_type', $verification_type);
        }
        if (count($grades) > 0) {
            $qry->whereIn('t1.current_school_grade', $grades);
        }
        if (isset($print_filter) && $print_filter != '') {
            if ($print_filter == 1) {
                $qry->where('t1.payment_printed', 1);
            } else if ($print_filter == 2) {
                $qry->where('t1.payment_printed', '<>', 1)
                    ->orWhere(function ($query) {
                        $query->whereNull('t1.payment_printed');
                    });
            }
        }
        $results = $qry->get();
          if (!isset($enrollment_status) && !is_numeric($enrollment_status)) {
            return $results;
          }
      
        $new_results =array();
        foreach($results as $rec)
        {
            $re=Db::table('beneficiary_enrollments')->where(['beneficiary_id'=>$rec->id,"year_of_enrollment"=>$year_of_enrollment])->selectraw('has_signed')->get()->toArray();
            if($enrollment_status=="0")
            {
                if(count($re)<1 || (count($re)>0 && $re[0]->has_signed==0))
            {
                $new_results[]=$rec;
            }

            }else{
                 if(is_array($re) && count($re)>0 && $re[0]->has_signed==1)
                 {
                     $new_results[] = $rec; 

                 }

            }
            
            
        }
      
        return $new_results;
    }

    function getPromotionPaymentChecklistBeneficiaryDetails($school_id, $batch_id, $category, $grades, $print_filter, $promotion_year)
    {
        $qry = DB::table('grade_nines_for_promotion as t0')
            ->join('beneficiary_information as t1', 't0.girl_id', '=', 't1.id')
            ->leftJoin('beneficiary_school_statuses as t7', 't1.beneficiary_school_status', '=', 't7.id')
            ->leftJoin('households as t5', 't1.household_id', '=', 't5.id')
            ->join('districts as t6', 't1.district_id', '=', 't6.id')
            //->join('gradenine_promotions as t9', 't0.girl_id', '=', 't9.girl_id')
            ->join('gradenine_promotions as t9', function ($join) {
                $join->on('t0.girl_id', '=', 't9.girl_id')
                    ->on('t0.promotion_year', '=', 't9.promotion_year');
            })
            ->select(DB::raw('t1.id,t1.beneficiary_id,t1.household_id,t1.exam_school_id,t1.school_id,t1.cwac_id,t1.acc_id,t1.ward_id,t1.constituency_id,t1.district_id,t1.province_id,CASE WHEN decrypt(t1.first_name) IS NULL THEN first_name ELSE decrypt(t1.first_name) END as first_name, CASE WHEN decrypt(t1.last_name) IS NULL THEN last_name ELSE decrypt(t1.last_name) END as last_name,t1.dob,t1.highest_grade,t1.exam_grade,t1.current_school_grade,t1.beneficiary_school_status,
                              t7.name as beneficiary_school_status,t5.hhh_fname,t6.name as hhh_district'))
            ->where(array('t1.school_id' => $school_id, 'enrollment_status' => 1, 'beneficiary_status' => 4))
            ->where('t0.promotion_year', $promotion_year)
            ->where('t0.stage', 3)
            ->where('t1.payment_eligible', 1)
            ->whereIn('t9.qualified', array(1, 2));
        if (is_numeric($batch_id)) {
            $qry->where('t1.batch_id', $batch_id);
        }
        if (is_numeric($category)) {
            $qry->where('t1.category', $category);
        }
        if(is_array($batch_id) && count($batch_id) > 0){
            $qry->whereIn('t1.batch_id',$batch_id);
        }
        $qry->groupBy('t1.id');
        $results = $qry->get();
        return $results;
    }

    function getRevokedPromotionPaymentChecklistBeneficiaryDetails($school_id, $batch_id, $category, $grades, $print_filter, $promotion_year)
    {
        $qry = DB::table('revoked_gradenine_promotions as t00')
            ->join('grade_nines_for_promotion as t0', 't00.promotion_id', '=', 't0.id')
            ->join('beneficiary_information as t1', 't0.girl_id', '=', 't1.id')
            ->leftJoin('beneficiary_school_statuses as t7', 't1.beneficiary_school_status', '=', 't7.id')
            ->leftJoin('households as t5', 't1.household_id', '=', 't5.id')
            ->join('districts as t6', 't1.district_id', '=', 't6.id')
            ->select(DB::raw('t1.id,t1.beneficiary_id,t1.household_id,t1.exam_school_id,t1.school_id,t1.cwac_id,t1.acc_id,t1.ward_id,t1.constituency_id,t1.district_id,t1.province_id,CASE WHEN decrypt(t1.first_name) IS NULL THEN first_name ELSE decrypt(t1.first_name) END as first_name, CASE WHEN decrypt(t1.last_name) IS NULL THEN last_name ELSE decrypt(t1.last_name) END as last_name,t1.dob,t1.highest_grade,t1.exam_grade,t1.current_school_grade,t1.beneficiary_school_status,
                              t7.name as beneficiary_school_status,t5.hhh_fname,t6.name as hhh_district'))
            ->where(array('t1.school_id' => $school_id, 'enrollment_status' => 1, 'beneficiary_status' => 4))
            ->where('t1.payment_eligible', 1)
            ->where('t0.promotion_year', $promotion_year);
        if (is_numeric($batch_id)) {
            $qry->where('t1.batch_id', $batch_id);
        }
        if(is_array($batch_id) && count($batch_id) > 0){
            $qry->whereIn('t1.batch_id',$batch_id);
        }
        if (is_numeric($category)) {
            $qry->where('t1.category', $category);
        }
        $qry->groupBy('t1.id');
        $results = $qry->get();
        return $results;
    }

    function getGenericPaymentChecklistBeneficiaryDetails($school_id, $year, $term_id, $batch_id, $category, $grades, $print_filter, $checklist_no = '', $is_promo = false, $sub_category, $verification_type, $checklist_type, $for_not_paid = '',$enrollment_status="",$school_status="")
    {
        //checklist type flag [1:Normal, 2:G9 promotions, 3:Revoked G9, 4:Follow ups]
        //PDF::SetY(104);//previus 
        PDF::SetY(114);
        //job 
        //PDF::AddPage('L');
        //PDF::setMargins(8, 10, 8, true);
        //PDF::SetAutoPageBreak(TRUE, 2);//true sets it to on and 0 means margin is zero from sides
       //end job 9/03/2022
        PDF::ln(4);

        $title = '<b>Table 3: Enrollment Details</b>';
        // PDF::writeHTMLCell(0, 10, 10, 123,$title, 0, 1, 0, true, 'L', true);
        PDF::writeHTMLCell(0, 10, 10, 230,$title, 0, 1, 0, true, 'L', true);
        $note = 'N/B: Please add extra girls on the blank rows. If the provided blank rows are not enough, feel free to add an extra sheet.';
        //PDF::Cell(200, 5, $title, 0, 0, 'L');
        // PDF::writeHTMLCell(0, 4, 10, 125, $note, 0, 1, 0, true, 'R', true);//15 was 104 job 9/03/2022
        PDF::writeHTMLCell(0, 4, 10, 10, $note, 0, 1, 0, true, 'R', true);//15 was 104 job 9/03/2022
        //PDF::SetY(20);//job 9/03/2022
        // PDF::SetY(130);
        PDF::SetY(20);
           $year_of_enrollment=$year;

        if ($checklist_type == 4) {

            if ($for_not_paid == 1) {
                $records = $this->returnUnprintedBeneficiaryChecklistQuery($school_id, $year, $batch_id);
            } else {
                $records = $this->returnFollowupBeneficiaryChecklistQuery($school_id, $year);
            }
        } else { //[checklist type: 1,2,3]
       
            if ($is_promo == 1) {
                $records = $this->getPromotionPaymentChecklistBeneficiaryDetails($school_id, $batch_id, $category, $grades, $print_filter, $year);
                $year = ($year + 1);
            } else if ($is_promo == 2) {//revoked
                $records = $this->getRevokedPromotionPaymentChecklistBeneficiaryDetails($school_id, $batch_id, $category, $grades, $print_filter, $year);
                $year = ($year + 1);
            } else {
               
                $records = $this->returnBeneciaryChecklistQuery($school_id, $batch_id, $category, $grades, $print_filter, $sub_category, $verification_type,$enrollment_status, $year_of_enrollment,$school_status);
                 
            }
        }
        $ben_counter = count($records);
        $params = array(
            'year' => $year,
            'term' => $term_id,
            'school_id' => $school_id,
            'checklist_number' => $checklist_no,
            'no_of_girls' => $ben_counter,
            'created_at' => Carbon::now(),
            'created_by' => \Auth::user()->id
        );
        $track_id = DB::table('payment_checklists_track')->insertGetId($params);
        //then the details of a beneficiaries
        //<td rowspan="2">SCHOOL STATUS OF GIRL</td>
        $ben_table = '<table border="1" cellpadding="2.5">
                       <tbody>
                           <tr>
                               <td rowspan="2" width="30">S/N</td>
                                <td colspan="4" align="center">Beneficiary Information</td>
                                <td rowspan="2">Grade</td>
                                <td rowspan="2"  width="34">Confirmed Form/Grade</td>
                                <td rowspan="2">Confirmed School Status(Day/Boarder/Weekly Boarder)</td>
                                <!--<td rowspan="2">Letter Received(Yes/No)</td>-->
                                <td colspan="2" align="center">Weekly Boarders ONLY</td>
                                <td rowspan="2">Enrolled(Yes/No)</td>
                                <td rowspan="2">Previous Grade</td>
                                <td rowspan="2"><b>Mobile Phone No. For Guardian/Parent</b></td>
                                <td rowspan="2"><b>Mobile Phone No. For CWAC Contact Person</b></td>
                                <td colspan="3" align="center"><b>Grade 9s & 12s ONLY</b></td>
                                <td rowspan="2"><b>Beneficiary Signature</b></td>
                           </tr>
                            <tr>
                                <td>Beneficiary ID</td>
                                <td>First Name</td>
                                <td>Surname</td>
                                <td>Home District of the Girl</td>
                                <td>If Weekly Boarder, Is she renting a Private or School managed WB facility? {Private/School managed}</td>
                                <td>If Weekly Boarder,Has she got a signed Disclaimer or Consent form from Parents/Guardians? {Yes/No}</td>
                                <td>Examination Number</td>
                                <td>Examination Fees</td>
                                <td>Is [girl] a GCE/External candidate? {Yes/No}</td>
                            </tr>
                       </tbody>';
        $i = 1;
        $printed = array();
        $girl_ids = array();
        $checker=1; 
        foreach ($records as $rec) {            
            // $previous_grade = $this->getPreviousTermdetails($term_id, $rec->current_school_grade);
            $ben_table .= '<tr>
                               <td width="30">' . $i . '</td>
                               <td>' . $rec->beneficiary_id . '</td>
                               <td>' . $rec->first_name . '</td>
                               <td>' . $rec->last_name . '</td>
                               <td>' . $rec->hhh_district . '</td>
                               <td align="center">' . $rec->current_school_grade . '</td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td align="center">' . ($rec->current_school_grade - 1) . '</td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>';
            /* if ($rec->current_school_grade == 9) {
                 $ben_table .= '</tr><tr><td colspan="6">Examination Number:</td><td colspan="13">Remarks:</td></tr>';
             } else {
                 $ben_table .= '</tr><tr><td colspan="19">Remarks:</td></tr>';
             }*/
            $ben_table .= '</tr><tr><td colspan="18">Remarks:</td></tr>';
            $i++;
            $printed[] = array(
                'id' => $rec->id
            );
            //$track_id="";
            $girl_ids[] = array(
                'girl_id' => $rec->id,
                'track_id' => $track_id,
                'created_at' => Carbon::now(),
                'created_by' => \Auth::user()->id
            );
        }//school_status
        $printed_ids = convertAssArrayToSimpleArray($printed, 'id');
        DB::table('beneficiary_information')
           ->whereIn('id', $printed_ids)
           ->update(array('payment_printed' => 1));
        DB::table('payment_checklists_track_details')->insert($girl_ids);

        $lower_limit = $i;
        $upper_limit = $lower_limit + 10;
        $ben_table2='<table border="1">';
        for ($j = $lower_limit; $j < $upper_limit; $j++) {
            $ben_table .= '<tr>
                               <td width="30">' . $j . '</td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                        </tr><tr><td colspan="18">Remarks:</td></tr>';
        }

        $ben_table .= '</table>';

        PDF::writeHTML($ben_table, true, false, false, false, 'L');
    }

    function getFollowupPaymentChecklistBeneficiaryDetails($school_id, $year, $for_not_paid, $checklist_no = '', $batch_id)
    {
        PDF::SetY(115);
        $title = 'Table 3: Enrollment Details';
        $note = 'N/B: Please add extra girls on the blank rows. If the provided blank rows are not enough, feel free to add an extra sheet.';
        PDF::Cell(200, 5, $title, 0, 0, 'L');
        PDF::writeHTMLCell(0, 4, 10, 115, $note, 0, 1, 0, true, 'R', true);
        if ($for_not_paid == 1) {
            $records = $this->returnUnprintedBeneficiaryChecklistQuery($school_id, $year, $batch_id);
        } else {
            $records = $this->returnFollowupBeneficiaryChecklistQuery($school_id, $year);
        }
        $ben_counter = count($records);
        $params = array(
            'year' => $year,
            'school_id' => $school_id,
            'checklist_number' => $checklist_no,
            'no_of_girls' => $ben_counter,
            'created_at' => Carbon::now(),
            'created_by' => \Auth::user()->id
        );
        $track_id = DB::table('payment_checklists_track')->insertGetId($params);
        $ben_table = '<table border="1" cellpadding="3">
                       <tbody>
                           <tr>
                               <td rowspan="2" width="30">S/N</td>
                                <td colspan="4">BENEFICIARY INFORMATION</td>
                                <td rowspan="2">GRADE</td>
                                <td rowspan="2">CONFIRMED FORM/GRADE</td>
                                <td rowspan="2">CONFIRMED SCHOOL STATUS(Day/Boarder/Weekly Boarder)</td>
                                <td rowspan="2">LETTER RECEIVED(Yes/No)</td>
                                <td rowspan="2">ENROLLED(Yes/No)</td>
                                <td rowspan="2">Previous Grade</td>
                                <td colspan="2">MATHS(Prev Term)</td>
                                <td colspan="2">ENGLISH(Prev Term)</td>
                                <td colspan="2">SCIENCE(Prev Term)</td>
                                <td rowspan="2">No of Days Attended(Prev Term)</td>
                                <td rowspan="2">BENEFICIARY SIGNATURE</td>
                           </tr>
                            <tr>
                                <td >Beneficiary ID</td>
                                <td >FIRST NAME</td>
                                <td>SURNAME</td>
                                <td >HOME DISTRICT OF THE GIRL</td>
                                <td>Score</td>
                                <td>Class Average </td>
                                <td>Score</td>
                                <td>Class Average </td>
                                <td>Score</td>
                                <td>Class Average </td>
                            </tr>
                       </tbody>';
        //<td rowspan="2">AVERAGE MARKS(Previous Term)</td>
        $i = 1;
        //<td>'.$rec['beneficiary_school_status'].'</td>
        $printed = array();
        $girl_ids = array();
        foreach ($records as $rec) {
            // $previous_grade = $this->getPreviousTermdetails($term_id, $rec->current_school_grade);
            $ben_table .= '<tr>
                               <td width="30">' . $i . '</td>
                               <td>' . $rec->beneficiary_id . '</td>
                               <td>' . $rec->first_name . '</td>
                               <td>' . $rec->last_name . '</td>
                               <td>' . $rec->hhh_district . '</td>
                               <td align="center">' . $rec->current_school_grade . '</td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td align="center">' . ($rec->current_school_grade - 1) . '</td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>';
            if ($rec->current_school_grade == 9) {
                $ben_table .= '</tr><tr><td colspan="6">Examination Number:</td><td colspan="13">Remarks:</td></tr>';
            } else {
                $ben_table .= '</tr><tr><td colspan="19">Remarks:</td></tr>';
            }
            $i++;
            $printed[] = array(
                'id' => $rec->id
            );
            $girl_ids[] = array(
                'girl_id' => $rec->id,
                'track_id' => $track_id,
                'created_at' => Carbon::now(),
                'created_by' => \Auth::user()->id
            );
        }//school_status
        $printed_ids = convertAssArrayToSimpleArray($printed, 'id');
        DB::table('beneficiary_information')
            ->whereIn('id', $printed_ids)
            ->update(array('payment_printed' => 1));
        DB::table('payment_checklists_track_details')->insert($girl_ids);
        $ben_table .= '</table>';

        PDF::writeHTML($ben_table, true, false, false, false, 'L');
    }

    function returnFollowupBeneficiaryChecklistQuery($school_id, $year)
    {
        $qry = DB::table('beneficiary_information as t1')
            ->select(DB::raw('distinct(t1.id),t1.beneficiary_id,t1.household_id,t1.exam_school_id,t1.school_id,t1.cwac_id,t1.acc_id,t1.ward_id,t1.constituency_id,t1.district_id,t1.province_id,CASE WHEN decrypt(t1.first_name) IS NULL THEN first_name ELSE decrypt(t1.first_name) END as first_name, CASE WHEN decrypt(t1.last_name) IS NULL THEN last_name ELSE decrypt(t1.last_name) END as last_name,t1.dob,t1.highest_grade,t1.exam_grade,t1.current_school_grade,t1.beneficiary_school_status,
                              t7.name as beneficiary_school_status,t5.hhh_fname,t6.name as hhh_district'))
            ->join('beneficiary_enrollments as e1', 't1.id', '=', 'e1.beneficiary_id')
            ->leftJoin('beneficiary_school_statuses as t7', 't1.beneficiary_school_status', '=', 't7.id')
            ->leftJoin('households as t5', 't1.household_id', '=', 't5.id')
            ->leftJoin('cwac as t8', 't5.cwac_id', '=', 't8.id')
            ->leftJoin('districts as t6', 't1.district_id', '=', 't6.id')
            ->where(array('e1.school_id' => $school_id))
            ->where('e1.year_of_enrollment', $year)
            ->where(function ($query) {
                $query->where('e1.is_validated', 0)
                    ->orWhereNull('e1.is_validated');
            });
            $qry->groupBy('t1.id');
        $results = $qry->get();
        return $results;
    }

    //print details
    public function printReconcilliationRpt(Request $req)
    {
        $year_of_enrollment = $req->year_of_enrollment;
        $term_id = $req->term_id;
        $title = 'Payment Reconciliation Report';
        $this->getReconcilliationsummaryRpt($title, $term_id, $year_of_enrollment, 0);

    }

    public function exportComprehesiveReconcilliationRpt(Request $req)
    {

        $year_of_enrollment = $req->year_of_enrollment;
        $term_id = $req->term_id;
        $school_id = $req->school_id;
        $district_id = $req->district_id;
        $title = 'Comprehensive Payment Reconciliation Report';
        PDF::AddPage('l');
        PDF::setMargins(8, 25, 8, true);
        PDF::SetAutoPageBreak(TRUE, 0);//true sets it to on and 0 means margin is zero from sides

        defaultreport_headerLandscape($title);
        //the details
        //$where_statement = array('year_of_enrollment'=>$year_of_enrollment,'term_id'=>$term_id);
        $where_statement = array();
        if (validateisNumeric($school_id)) {
            $where_statement['t2.id'] = $school_id;
            $start = 0;
        }
        if (validateisNumeric($district_id)) {
            $where_statement['t2.district_id'] = $district_id;
            $start = 0;
        }
        if (validateisNumeric($term_id)) {
            $where_statement['t1.term_id'] = $term_id;
            $start = 0;
        }
        if (validateisNumeric($year_of_enrollment)) {
            $where_statement['t1.year_of_enrollment'] = $year_of_enrollment;
            $start = 0;
        }
        $qry = DB::table('beneficiary_enrollments as t1')
            ->select(DB::raw("t1.school_id,term_id,year_of_enrollment, t2.name as school_name,t2.code as school_code, t3.name as district_name,count(t1.id) as verified_bencount"))
            ->join('school_information as t2', 't1.school_id', '=', 't2.id')
            ->join('districts as t3', 't2.district_id', '=', 't3.id')
            ->where($where_statement)
            ->groupBy('t2.id')
            ->get();
        $data = array();
        if ($qry->count() > 0) {
            foreach ($qry as $row) {
                $school_name = $row->school_name;
                $school_id = $row->school_id;
                $school_code = $row->school_code;
                $district_name = $row->district_name;

                $verified_bencount = $row->verified_bencount;

                $validated_ben = getSchBenefiaryenrollmentcounter($year_of_enrollment, $term_id, $school_id);

                $disbursement_rpt = getSchoolPaymentdisbursements($year_of_enrollment, $term_id, $school_id);
                //z
                $validated_fees = $validated_ben->total_fees;
                $reconcilliation_status = 0;
                if ($disbursement_rpt->total_fees >= $validated_fees && $validated_fees != 0) {
                    $reconcilliation_status = 1;
                }
                $data[] = array('school_name' => $school_name,
                    'reconcilliation_status' => $reconcilliation_status,
                    'school_id' => $school_id,
                    'school_code' => $school_code,
                    'district_name' => $district_name,
                    'district_name' => $district_name,
                    'verified_beneficiaries' => (int)$verified_bencount,
                    'validated_beneficiaries' => (int)$validated_ben->beneficiary_counter,
                    'confirmed_total_fees' => (int)$validated_ben->total_fees,
                    'paid_for_beneficiares' => (int)$disbursement_rpt->beneficiary_counter,
                    'total_disbursement' => (int)$disbursement_rpt->total_fees,
                );
            }

        }
        if (!empty($data)) {
            //$results = convertStdClassObjToArray($sql_query);
            Excel::create('Payment Reconcilliation Report', function ($excel) use ($data) {
                // Set the title
                $excel->setTitle('Payment Reconcilliation Report');
                $excel->setCreator('KGS -Softclans Technologies');
                $excel->setDescription('Payment Reconcilliation Report');
                $excel->sheet('sheet1', function ($sheet) use ($data) {
                    //append the details for the records
                    $sheet->fromArray($data, null, 'A1', false, true);

                });
                //})->export('pdf');;
            })->download('xlsx');

        } else {
            echo "<center>No Record Found</center>";

        }

    }

    function getReconcilliationsummaryRpt($title, $term_id, $year_of_enrollment, $is_comprehensive = NULL)
    {
        PDF::AddPage('l');
        PDF::setMargins(8, 25, 8, true);
        PDF::SetAutoPageBreak(TRUE, 0);//true sets it to on and 0 means margin is zero from sides

        defaultreport_headerLandscape($title);
        //the details
        PDF::ln();
        PDF::SetFont('times', 'B', 12);

        $start_year = 2017;
        $end_year = date('Y');
        $data = array();
        $qry = DB::table('school_terms')->get();
        if (validateisNumeric($term_id)) {
            $qry = DB::table('school_terms')->where(array('id' => $term_id))->get();
        }
        if (validateisNumeric($year_of_enrollment)) {
            $start_year = $year_of_enrollment;
            $end_year = $year_of_enrollment;
        }
        $total_schoolfees = 0;
        $total_feedisbursed = 0;
        PDF::SetFont('times', 'B', 10);
        $i = 1;
        PDF::cell(8, 6, 'S/n', 1, 0);
        PDF::cell(40, 6, 'Reconciliation Status', 1, 0);
        //PDF::cell(30,6,'Year Of Enrollment',1,0);
        PDF::cell(25, 6, 'Term', 1, 0);
        PDF::cell(40, 6, 'Verified Beneficiaries', 1, 0);
        PDF::cell(40, 6, 'Validated Beneficiaries', 1, 0);
        PDF::cell(40, 6, 'Confirmed Total Fees', 1, 0);
        PDF::cell(40, 6, 'Beneficiaries Paid For', 1, 0);
        PDF::cell(0, 6, 'Total Disbursement', 1, 1, 'R');
        PDF::SetFont('times', '', 10);
        for ($year_loop = $start_year; $year_loop <= $end_year; $year_loop++) {

            if ($qry->count() > 0) {
                //the reports header
                $year_of_enrollment = $year_loop;
                PDF::SetFont('times', 'B', 10);
                PDF::cell(0, 8, 'Year of Enrollment: ' . $year_of_enrollment, 1, 1);
                PDF::SetFont('times', '', 10);
                foreach ($qry as $row) {
                    $term_id = $row->id;
                    $term_name = $row->name;
                    $verified_ben = getBenefiaryenrollmentcounter($year_of_enrollment, $term_id, 0);

                    $validated_ben = getBenefiaryenrollmentcounter($year_of_enrollment, $term_id, 1);

                    $disbursement_rpt = getPaymentdisbursements($year_of_enrollment, $term_id);
                    $reconcilliation_status = 'Not Balanced';
                    if ($disbursement_rpt->total_fees >= $validated_ben->total_fees && $validated_ben->total_fees != 0) {
                        $reconcilliation_status = 'Balanced';
                    }
                    PDF::cell(8, 6, $i, 1, 0);
                    PDF::cell(40, 6, $reconcilliation_status, 1, 0);
                    PDF::cell(25, 6, $term_name, 1, 0);
                    PDF::cell(40, 6, $verified_ben->beneficiary_counter, 1, 0);
                    PDF::cell(40, 6, $validated_ben->beneficiary_counter, 1, 0);
                    PDF::cell(40, 6, formatMoney($validated_ben->total_fees), 1, 0, 'R');
                    PDF::cell(40, 6, $disbursement_rpt->beneficiary_counter, 1, 0);
                    PDF::cell(0, 6, formatMoney($disbursement_rpt->total_fees), 1, 1, 'R');

                    $total_schoolfees = $total_schoolfees + $validated_ben->total_fees;
                    $total_feedisbursed = $total_feedisbursed + $disbursement_rpt->total_fees;
                    //get the comprehensive report
                    if ($is_comprehensive == 1) {


                    }

                    //get the detaila

                    $i++;
                }

            }
        }
        PDF::cell(153, 6, 'Total School Fees', 1, 0);
        PDF::cell(40, 6, formatMoney($total_schoolfees), 1, 0, 'R');

        PDF::cell(40, 6, 'Total Fees', 1, 0);
        PDF::cell(0, 6, formatMoney($total_feedisbursed), 1, 0, 'R');

        PDF::Output('Reconciliation Report', 'I');

    }

    public function createFolders()
    {

        $qry = DB::table('beneficiary_information as t1')
            ->select('t2.name as school_name', 't2.code as school_code', 't3.name as district_name')
            ->join('school_information  as t2', 't1.school_id', '=', 't2.id')
            ->join('districts as t3', 't2.district_id', '=', 't3.id')
            ->join('provinces as t4', 't3.province_id', '=', 't4.id')
            ->groupBy('t2.id')
            ->get();
        foreach ($qry as $row) {
            $school_name = str_replace('/', '-', $row->district_name . '-' . $row->school_name);
            $dir = "School_folders/" . $school_name;
            if (is_dir($dir) === false) {

                mkdir($dir);
                echo "Directory Created Successfully</br>";
            }
        }


    }

    public function printPaymentverificationproces(Request $req)
    {
        $status_id = $req->status_id;
        $sql = DB::table('payment_verificationbatch as t1')
            ->select(DB::raw('TOTAL_WEEKDAYS(t1.added_on,now()) as added_span,TOTAL_WEEKDAYS(t1.submitted_on,now()) as submission_span,  count(t9.beneficiary_id) as no_of_girls,sum(t9.school_fees) as total_fees, t1.*,t8.first_name as submitted_by, t7.name as status_name,t1.id as batch_id, t2.name as school_name,t3.name as district_name,t4.name as term_name,t5.first_name as added_by_name,t6.name as province_name'))
            //->select('t1.status_id')
            ->join('school_information as t2', 't1.school_id', '=', 't2.id')
            ->join('districts as t3', 't1.district_id', '=', 't3.id')
            ->join('school_terms as t4', 't1.term_id', '=', 't4.id')
            ->leftJoin('users as t5', 't1.added_by', '=', 't5.id')
            ->join('provinces as t6', 't3.province_id', '=', 't6.id')
            ->join('payment_verification_statuses as t7', 't1.status_id', '=', 't7.id')
            ->leftJoin('beneficiary_enrollments as t9', 't1.id', '=', 't9.batch_id')
            ->leftJoin('users as t8', 't1.submitted_by', '=', 't8.id')
            ->havingRaw('count(t2.id)  > 0')
            ->orderBy('t1.added_on', 'asc')
            ->groupBy('t1.id');

        if (validateisNumeric($status_id)) {

            $sql = $sql->where(array('t1.status_id' => $status_id));

        }
        $sql = $sql->get();
        //echo "welcome";
        $title = "Payment Verification Process";
        PDF::AddPage('l');
        PDF::setMargins(8, 25, 8, true);
        PDF::SetAutoPageBreak(TRUE, 0);//true sets it to on and 0 means margin is zero from sides

        landscapereport_header();
        //the details
        $i = 1;
        PDF::ln();
        PDF::SetFont('times', 'B', 12);
        $ben_table = '<table border="1" cellpadding="3">
                       <tbody>
                            <tr>
                                <td>Sn</td>
                                <td>School Name</td>
                                <td>District</td>
                                <td>Payment Verification Ref No</td>
                                <td>Year of Enrollment</td>
                                <td>Term</td>
                                <td>Date Added</td>
                                <td>Current Status</td>
                                <td>Remarks</td>
                                <td># of Beneficiaries</td>
                                <td>Total School Fees</td>
                            </tr>
                      ';
        if ($qry) {

            foreach ($sql as $rows) {
                $ben_table .= '<tr>
                                <td>' . $i . '</td>
                                <td>' . $rows->school_name . '</td>
                                <td>' . $rows->district_name . '</td>
                                <td>' . $rows->batch_no . '</td>
                                <td>' . $rows->year_of_enrollment . '</td>
                                <td>' . $rows->term_name . '</td>
                                <td>' . $rows->added_on . '</td>
                                <td>' . $rows->status_name . '</td>
                                <td>' . $rows->no_of_girls . '</td>
                                <td>' . $rows->total_fees . '</td>
                            </tr>';

            }

        }
        $ben_table .= '</tbody>
                        </table>';
        PDF::cell(0, 5, strtoupper($title), 0, 1, 'C');
        PDF::Output($title, 'I');
    }

    function getGroupingdetails($id)
    {
        $data = DB::table('beneficiary_groupingdetails')
            ->where(array('id' => $id))
            ->first();
        return $data;
    }

    public function getBeneficiaryGroupingstr()
    {
        $data = DB::table('beneficiary_groupingdetails')
            ->get();

        json_output(array('results' => $data));
    }

    public function getActivebengroupRptStr(Request $req)
    {
        $group_fieldid = $req->group_by;
        $group_fieldid = $req->group_by;
        $sql = DB::table('beneficiary_information as t1');

        $group_details = $this->getGroupingdetails($group_fieldid);
        if (validateisNumeric($group_fieldid)) {
            $group_field = $group_details->grouping_field;
            $group_name = $group_details->grouping_name;

        } else {
            $group_field = 't2.district_id';
            $group_name = 't3.name';
        }

        $sql = $sql->select(DB::raw("count(t1.id) as counter,t3.name as district_name, $group_name as group_fieldvalue"));

        if ($group_fieldid == 1) {
            $sql = $sql->select(DB::raw("count(t1.id) as counter,'' as district_name, $group_name as group_fieldvalue"));

        }
        $sql = $sql->join('school_information as t2', 't1.school_id', '=', 't2.id')
            ->join('districts as t3', 't2.district_id', 't3.id')
            ->groupBy($group_field)
            ->where(array('enrollment_status' => 1, 'beneficiary_status' => 4))
            ->get();
        json_output(array('results' => $sql));
    }

    public function getPaymentsubGroupingstr()
    {
        $data = array();
        $data[] = array('id' => '11', 'name' => 'School District');
        $data[] = array('id' => '22', 'name' => 'Home District');
        $data[] = array('id' => '33', 'name' => 'School Name');
        $data[] = array('id' => '44', 'name' => 'Add By Details');
        $data[] = array('id' => '51', 'name' => 'Categories');
        $data[] = array('id' => '52', 'name' => 'School Statuses');
        json_output(array('results' => $data));
    }

    public function getviewPaymentsubmissionStr(Request $req)
    {//Term 2
        try {
            $group_fieldid = $req->input('group_field');
            $year_of_enrollment = $req->input('year_of_enrollment');
            $enrolment_batch_id = $req->input('enrolment_batch_id');
            $verification_type_id = $req->input('verification_type_id');
            $where_term = array();
            $where_year = array();
            if (validateisNumeric($year_of_enrollment)) {
                $where_year = array('t5.year_of_enrollment' => $year_of_enrollment);
            }
            $where_data = array_merge($where_term, $where_year);
            $qry = DB::table('payment_verificationbatch as t1');
            if ($group_fieldid == 44) {
            // SUM(IF(t5.passed_rules=1 AND DISTINCT t5.beneficiary_id,1,0)) as passed_rules_girls,            
                $qry->select(DB::raw('t11.name as district_name,count(t5.id) as verified_beneficiaries,
            SUM(IF(t5.is_validated=1,1,0)) as validated_beneficiaries, 
            COUNT(DISTINCT IF(t5.passed_rules = 1, t5.beneficiary_id, NULL)) AS passed_rules_girls,
            decrypt(t44.first_name) as group_fieldvalue,t5.year_of_enrollment,
            SUM(CASE WHEN t5.passed_rules = 1 THEN decrypt(t5.annual_fees) ELSE 0 END) AS rule_total_fees,
            SUM(CASE WHEN t5.is_validated = 1 THEN decrypt(t5.annual_fees) ELSE 0 END) AS school_fees,
            SUM(CASE WHEN t55.id IS NOT NULL THEN decrypt(t5.annual_fees) ELSE 0 END) AS total_fees,count(t55.id) as added_for_payments'));
            } else {
                $qry->select(DB::raw('t11.name as district_name,count(t5.id) as verified_beneficiaries,
                    SUM(IF(t5.is_validated=1,1,0)) as validated_beneficiaries, SUM(IF(t5.passed_rules=1,1,0)) as passed_rules_girls,
                    t' . $group_fieldid . '.name as group_fieldvalue,t5.year_of_enrollment,
                    SUM(CASE WHEN t5.passed_rules = 1 THEN decrypt(t5.annual_fees) ELSE 0 END) AS rule_total_fees,
                    SUM(CASE WHEN t5.is_validated = 1 THEN decrypt(t5.annual_fees) ELSE 0 END) AS school_fees,
                    SUM(CASE WHEN t55.id IS NOT NULL THEN decrypt(t5.annual_fees) ELSE 0 END) AS total_fees,count(t55.id) as added_for_payments'));
            }
            $qry->join('school_information as t33', 't1.school_id', '=', 't33.id')
                ->join('districts as t11', 't33.district_id', '=', 't11.id')
                ->join('beneficiary_enrollments as t5', 't1.id', '=', 't5.batch_id')
                ->join('beneficiary_information as t6', 't5.beneficiary_id', '=', 't6.id');
            /* */
            if ($group_fieldid == 51) {
                $qry->join('beneficiary_categories as t51', 't6.category', '=', 't51.id');
            }
            if ($group_fieldid == 52) {
                $qry->join('beneficiary_school_statuses as t52', 't5.beneficiary_schoolstatus_id', '=', 't52.id');
            }
            /* */
            $qry->join('districts as t22', 't6.district_id', '=', 't22.id')
                ->leftJoin('users as t44', 't1.added_by', '=', 't44.id')
                ->leftJoin('beneficiary_payment_records as t55', 't5.id', '=', 't55.enrollment_id')
                ->groupBy('t' . $group_fieldid . '.id', 't5.year_of_enrollment')
                ->where($where_data);
            if (validateisNumeric($enrolment_batch_id)) {
                $qry->where('t6.batch_id', $enrolment_batch_id);
            }
            if (validateisNumeric($verification_type_id)) {
                $qry->where('t6.verification_type', $verification_type_id);
            }
            // $qry1 = $qry->groupBy('t5.beneficiary_id');
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
        } catch (\Throwable $t) {
            $res = array(
                'success' => false,
                'message' => $t->getMessage()
            );
        }
        return response()->json($res);
    }


    public function getBeneficairypaymentgroupRpt(Request $req)//Job on 14/06/2022
    {
        $term_id = $req->input('term_id');
        $year_of_enrollment = $req->input('year_of_enrollment');
        $group_fieldid = $req->input('group_by');
        $where_data = array();
        if (validateisNumeric($year_of_enrollment)) {
           $where_data['t2.year_of_enrollment'] = $year_of_enrollment;
        }

        $group_name="t4.name";//district
        if($group_fieldid==1)
        {
            $group_name="t7.name";
        }
        if($group_fieldid==2)
        {
            $group_name="t3.name";
        }
        if($group_fieldid==22)
        {
            $group_name="t8.name";
        }
        $sql=DB::table('beneficiary_information as t1')
        ->join('beneficiary_enrollments as t2', 't1.id', '=', 't2.beneficiary_id')
        ->join('school_information as t3', 't3.id', '=', 't2.school_id')
        ->leftjoin('districts as t4', 't3.district_id', '=', 't4.id')
        ->leftjoin('districts as t8','t8.id','t1.district_id')
        ->join('beneficiary_payment_records as t5', 't2.id', '=', 't5.enrollment_id')
        ->join('payment_disbursement_details as t6','t6.school_id','t3.id')
        ->join('school_grades as t7','t7.id','t1.current_school_grade')
        ->selectraw("sum(amount_transfered) as total_fees,current_school_grade,count(t1.id) as no_of_beneficiaries,$group_name as group_fieldvalue");
        if (validateisNumeric($group_fieldid)) {
        if($group_fieldid==1){
        $sql->groupBy('current_school_grade')
          ->orderBy('t7.id','ASC');
         }
         if($group_fieldid==2)
         {
            $sql->groupBy('t2.school_id');
         }
         if($group_fieldid==3)
         {
            $sql->groupBy('t3.district_id');
         }
         if($group_fieldid==22)
         {
            $sql->groupBy('t1.district_id');
         }
        }else{
            $sql->groupBy('t3.district_id');
        }
        if (count($where_data) > 0) {
            $sql = $sql->where($where_data);
        }
         $sql=$sql->get();
        json_output(array('results' => $sql));

    }

    public function getBeneficairypaymentgroupRptInitialByKip(Request $req)
    {
        $term_id = $req->input('term_id');
        $year_of_enrollment = $req->input('year_of_enrollment');
        $group_fieldid = $req->input('group_by');
        $where_data = array();
        if (validateisNumeric($term_id)) {
            $where_data['t4.term_id'] = $term_id;
        }
        if (validateisNumeric($year_of_enrollment)) {
            $where_data['t4.payment_year'] = $year_of_enrollment;
        }
        $sql = DB::table('payment_disbursement_details as t1')
            ->join('school_information as t2', 't1.school_id', '=', 't2.id')
            ->join('districts as t3', 't2.district_id', '=', 't3.id')
            ->join('payment_request_details as t4', 't1.payment_request_id', '=', 't4.id');

        $group_details = $this->getGroupingdetails($group_fieldid);
        if (validateisNumeric($group_fieldid)) {
            $group_field = $group_details->grouping_field;
            $group_name = $group_details->grouping_name;

        } else {
            $group_field = 't2.district_id';
            $group_name = 't3.name';
        }
        $sql = $sql->select(DB::raw("(select count(t.id) from beneficiary_payment_records q inner join beneficiary_enrollments t on q.enrollment_id = t.id where q.payment_request_id = t4.id and t.school_id = t1.school_id) as no_of_beneficiaries, sum(amount_transfered) as total_fees,t3.name as district_name, $group_name as group_fieldvalue"));
        if ($group_fieldid == 1) {
            $sql = $sql->select(DB::raw("(select count(t.id) from beneficiary_payment_records q inner join beneficiary_enrollments t on q.enrollment_id = t.id where q.payment_request_id = t4.id and t.school_id = t1.school_id) as no_of_beneficiaries, sum(amount_transfered) as total_fees,'' as district_name, $group_name as group_fieldvalue"));
        }
        //get the disbursements plus the counts
        if (count($where_data) > 0) {
            $sql = $sql->where($where_data);
        }
        $sql = $sql->groupBy($group_field)->get();
        json_output(array('results' => $sql));

    }

    public function generatePaymentrequestUploadtemplate(Request $req)
    {
        $payment_request_id = $req->input('payment_request_id');
        $qry = DB::table('beneficiary_information as t1')
            ->select(DB::raw("t2.id as school_id, t2.name as school_name, t10.name as bank_name,t11.name as branch_name, decrypt(t9.account_no) as account_no,t9.sort_code,(sum(t5.school_fees) -(select sum(amount_transfered) from payment_disbursement_details a where a.payment_request_id = t8.payment_request_id and a.school_id = t2.id))  as school_fees,'' as amount_transfered,'' as transaction_no,'' as transaction_date"))
            ->join('beneficiary_enrollments as t5', 't1.id', '=', 't5.beneficiary_id')
            ->join('school_information as t2', 't2.id', '=', 't5.school_id')
            ->join('districts as t3', 't2.district_id', '=', 't3.id')
            ->join('provinces as t4', 't3.province_id', '=', 't4.id')
            ->join('beneficiary_payment_records as t8', 't5.id', '=', 't8.enrollment_id')
            ->leftJoin('school_bankinformation as t9', 't2.id', '=', 't9.school_id')
            ->leftJoin('bank_details as t10', 't9.bank_id', '=', 't10.id')
            ->leftJoin('bank_branches as t11', 't9.branch_name', '=', 't11.id')
            ->leftJoin('temppayment_disbursement_details as t12', function ($join) {
                $join->on('t8.payment_request_id', '=', 't12.payment_request_id');
                $join->on('t2.id', '=', 't12.school_id');
            })
            ->where(array('t8.payment_request_id' => $payment_request_id))
            ->groupBy('t2.id')
            ->get();

        //print_r(DB::getQueryLog());
        $results = convertStdClassObjToArray($qry);
        $results = decryptArray($results);

        Excel::create('Payment Upload Template', function ($excel) use ($results) {
            // Set the title
            $excel->setTitle('KGS Payment Schedule');
            $excel->setCreator('KGS - Softclans Technologies');
            $excel->setDescription('Termly Payment Schedule');
            //mysql_num_fields($result)
            $excel->sheet('sheet1', function ($sheet) use ($results) {
                $sheet->fromArray($results, null, 'A2', false, true);
            });
        })->download('csv');

    }

    public function getPaymentDisbursementReport(Request $req)
    {
        try {
            $year = $req->input('year');
            $district = $req->input('district');
            // $qry = DB::table('payment_disbursement_details as t1')
            $qry = DB::table('beneficiary_payresponses_report as t1')
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                ->join('payment_request_details as t3', 't1.payment_request_id', '=', 't3.id')
                ->join('districts as t4', 't4.id', '=', 't2.district_id')
                ->join('provinces as t5', 't5.id', '=', 't4.province_id')
                // ->select(DB::raw('t2.name as school_name, t4.name as district_name, t5.name as province_name,
                //                   t2.id as school_id,t4.id as district_id,t5.id as province_id,COUNT(t2.id) as no_of_schools,
                //                   sum(decrypt(t1.amount_transfered)) as total_disbursement'))
                ->select(DB::raw('t2.name as school_name, t4.name as district_name, t5.name as province_name,
                                  t2.id as school_id,t4.id as district_id,t5.id as province_id,COUNT(t2.id) as no_of_schools,
                                  sum(t1.total_payable_fees) as total_disbursement'))
                ->where('t3.payment_year', $year);
            if (isset($district) && $district != '') {
                $qry->where('t4.id', $district);
            }
            $qry->groupBy('t4.id');
            $results = $qry->get();
            $res = array(
                'success' => true,
                'message' => returnMessage($results),
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

    // public function getPaymentDisbursementReport(Request $req)
    // {
    //     try {
    //         $year = $req->input('year');
    //         $district = $req->input('district');
    //         $query = DB::table('sa_app_beneficiary_list_3 as t')
    //         ->join('districts as t4',DB::raw("SUBSTRING_INDEX(t.school_district, '-', 1)"),'=', 't4.code')
    //             ->select('t.id','t.school_name','t.school_district as district_name','t4.id as district_id',
    //                 't.cwac_name','t.school_province as province_name',
    //                 DB::raw('sum(t.grant_amount) as total_disbursement'),
    //                 DB::raw('COUNT(DISTINCT school_id) as no_of_schools'))
    //                 ->where('t.in_excel','=',1);
    //         $query->orderBy('t.created_at', 'desc');
    //         $query->groupBy('t.school_district');
    //         $results = $query->get();
    //         $res = array(
    //             'success' => true,
    //             'message' => returnMessage($results),
    //             'results' => $results
    //         );
    //     } catch (\Exception $e) {
    //         $res = array(
    //             'success' => false,
    //             'message' => $e->getMessage()
    //         );
    //     }
    //     return response()->json($res);
    // }

    public function getSchoolsDisbursementReport(Request $req)
    {
        $year = $req->input('year');
        $district = $req->input('district');
        $for_schools_flag = $req->input('for_schools_flag');
        try {
            // $qry = DB::table('sa_app_beneficiary_list_3 as t1')
            $qry = DB::table('beneficiary_payresponses_report as t1')
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                // ->join('districts as t4', DB::raw("TRIM(SUBSTRING_INDEX(t1.school_district, '-', 1))"),'=', 't4.code')
                ->join('districts as t4', 't1.school_district_id','=', 't4.id')
                // ->join('payment_request_details as t3', 't1.payment_request_id', '=', 't3.id')
                // ->join('districts as t4', 't4.id', '=', 't2.district_id')
                // ->join('beneficiary_payment_records as t6', 't6.payment_request_id', '=', 't3.id')
                // ->join('beneficiary_enrollments as t7', function ($join) {
                    // $join->on('t7.id', '=', 't6.enrollment_id')
                        // ->on('t7.school_id', '=', 't1.school_id');
                // })
                // ->join('beneficiary_information as t8', 't7.beneficiary_id', '=', 't8.id')
                ->select(DB::raw("t1.id,t1.beneficiary_id as beneficiary_no,t2.name as school_name, t4.name as district_name,
                                  t2.id as school_id,t4.id as district_id,
                                  t1.first_name,t1.surname as last_name,t1.total_payable_fees as school_fees"))
                // ->where('t1.in_excel','=', 1)
                ->where('t4.id', $district);
            if (isset($for_schools_flag) && $for_schools_flag == 1) {
                $qry->select(DB::raw('t2.id,t2.name as school_name,t4.name as district_name,sum(t1.total_payable_fees) as school_fees,
                                      COUNT(t1.id) as no_of_beneficiaries'))
                    ->groupBy('t2.id');
            }
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

    public function getDisbursementReportPayReqDetails($school_id, $year)
    {
        $qry = DB::table('beneficiary_payresponses_report as t1')
            ->join('school_information as t2', 't1.school_id', '=', 't2.id')
            ->join('payment_request_details as t3', 't1.payment_request_id', '=', 't3.id')
            ->join('districts as t4', 't4.id', '=', 't2.district_id')
            // ->join('beneficiary_payment_records as t6', 't6.payment_request_id', '=', 't3.id')
            ->join('beneficiary_information as t8', 't1.girl_id', '=', 't8.id')
            ->select(DB::raw('t3.payment_ref_no, t8.beneficiary_id as beneficiary_no,t2.name as school_name, t4.name as district_name,
                              count(t1.id) as no_of_beneficiaries,t2.id as school_id,t4.id as district_id,sum(t1.total_payable_fees) as total_disbursement,
                              decrypt(t8.first_name) as first_name,decrypt(t8.last_name) as last_name,sum(t1.total_payable_fees) as total_school_fees'))
            ->where('t3.payment_year', $year)
            // ->where('t3.payment_ref_no','KGS/PAY/REQ/2025/0002')
            ->where('t2.id', $school_id)
            ->groupBy('t3.id');
        $results = $qry->get();
        return $results;
    }
    // public function getDisbursementReportPayReqDetails($school_id, $year)
    // {
    //     $qry = DB::table('payment_disbursement_details as t1')
    //         ->join('school_information as t2', 't1.school_id', '=', 't2.id')
    //         ->join('payment_request_details as t3', 't1.payment_request_id', '=', 't3.id')
    //         ->join('districts as t4', 't4.id', '=', 't2.district_id')
    //         ->join('beneficiary_payment_records as t6', 't6.payment_request_id', '=', 't3.id')
    //         ->join('beneficiary_enrollments as t7', function ($join) {
    //             $join->on('t7.id', '=', 't6.enrollment_id')
    //                 ->on('t7.school_id', '=', 't1.school_id');
    //         })
    //         ->join('beneficiary_information as t8', 't7.beneficiary_id', '=', 't8.id')
    //         ->select(DB::raw('t3.payment_ref_no, t8.beneficiary_id as beneficiary_no,t2.name as school_name, t4.name as district_name,
    //                           count(t6.enrollment_id) as no_of_beneficiaries,t2.id as school_id,t4.id as district_id,decrypt(t1.amount_transfered) as total_disbursement,
    //                           decrypt(t8.first_name) as first_name,decrypt(t8.last_name) as last_name,sum(decrypt(t7.annual_fees)) as total_school_fees'))
    //         ->where('t3.payment_year', $year)
    //         // ->where('t3.payment_ref_no','KGS/PAY/REQ/2025/0002')
    //         ->where('t2.id', $school_id)
    //         ->groupBy('t3.id');
    //     $results = $qry->get();
    //     return $results;
    // }

    public function printDisbursementReportPaidBeneficiaries($school_id, $year)
    {
        $qry = DB::table('beneficiary_payresponses_report as t1')
            ->join('school_information as t2', 't1.school_id', '=', 't2.id')
            ->join('payment_request_details as t3', 't1.payment_request_id', '=', 't3.id')
            ->join('districts as t4', 't4.id', '=', 't2.district_id')
            ->join('beneficiary_payment_records as t6', 't6.payment_request_id', '=', 't3.id')
            // ->join('beneficiary_enrollments as t7', function ($join) {
            //     $join->on('t7.id', '=', 't6.enrollment_id')
            //         ->on('t7.school_id', '=', 't1.school_id');
            // })
            ->join('beneficiary_information as t8', 't1.girl_id', '=', 't8.id')
            ->leftJoin('beneficiary_school_statuses as t9', 't1.beneficiary_schoolstatus_id', '=', 't9.id')
            ->select(DB::raw('distinct(t8.id), t8.beneficiary_id as beneficiary_no,t2.name as school_name, t4.name as district_name,
                              t2.id as school_id,t4.id as district_id,t1.confirmed_grade as school_grade,t9.name as school_status_name,
                              t1.term1_fee as term1_fees,t1.term2_fee as term2_fees,
                              t1.term3_fee as term3_fees,
                              t1.exam_fees, t1.additional_fee_amount,
                              decrypt(t8.first_name) as first_name,decrypt(t8.last_name) as last_name, t1.total_payable_fees as school_fees'))
            ->where('t3.payment_year', $year)
            ->where('t2.id', $school_id);
        return $qry->get();
    }
    // public function printDisbursementReportPaidBeneficiaries($school_id, $year)
    // {
    //     $qry = DB::table('payment_disbursement_details as t1')
    //         ->join('school_information as t2', 't1.school_id', '=', 't2.id')
    //         ->join('payment_request_details as t3', 't1.payment_request_id', '=', 't3.id')
    //         ->join('districts as t4', 't4.id', '=', 't2.district_id')
    //         ->join('beneficiary_payment_records as t6', 't6.payment_request_id', '=', 't3.id')
    //         ->join('beneficiary_enrollments as t7', function ($join) {
    //             $join->on('t7.id', '=', 't6.enrollment_id')
    //                 ->on('t7.school_id', '=', 't1.school_id');
    //         })
    //         ->join('beneficiary_information as t8', 't7.beneficiary_id', '=', 't8.id')
    //         ->leftJoin('beneficiary_school_statuses as t9', 't7.beneficiary_schoolstatus_id', '=', 't9.id')
    //         ->select(DB::raw('distinct(t8.id), t8.beneficiary_id as beneficiary_no,t2.name as school_name, t4.name as district_name,
    //                           t2.id as school_id,t4.id as district_id,t7.school_grade,t9.name as school_status_name,decrypt(t7.term1_fee as term1_fees,decrypt(t7.term2_fees) as term2_fees,
    //                           decrypt(t7.term3_fees) as term3_fees,decrypt(t7.exam_fees) as exam_fees,decrypt(t8.first_name) as first_name,decrypt(t8.last_name) as last_name,decrypt(t7.annual_fees) as school_fees'))
    //         ->where('t3.payment_year', $year)
    //         ->where('t2.id', $school_id);
    //     return $qry->get();
    // }

    // public function getDisbursementReportForSchools(Request $req)
    // {
    //     $year = $req->input('year');
    //     $selected_schools = $req->input('selected_schools');
    //     $selected_schools = json_decode($selected_schools);
    //     $district_id = $req->input('district_id');
    //     $district_name = getSingleRecordColValue('districts', array('id' => $district_id), 'name');
    //     $qry = DB::table('payment_disbursement_details as t1')
    //         ->join('school_information as t2', 't1.school_id', '=', 't2.id')
    //         ->join('payment_request_details as t3', 't1.payment_request_id', '=', 't3.id')
    //         ->join('districts as t4', 't4.id', '=', 't2.district_id')
    //         ->join('provinces as t5', 't5.id', '=', 't4.province_id')
    //         ->select(DB::raw('t2.code as school_code,t2.name as school_name, t4.name as district_name, t5.name as province_name,
    //                           t4.code as district_code,t2.id as school_id,t4.id as district_id,t5.id as province_id,
    //                           sum(decrypt(t1.amount_transfered)) as total_disbursement'))
    //         ->where('t3.payment_year', $year)
    //         ->whereIn('t2.id', $selected_schools)
    //         ->groupBy('t2.id');
    //     $results = $qry->get();
    //     $this->printDisbursementReport($results, $district_name, $year);
    // }
    public function getDisbursementReportForSchools(Request $req)
    {
        $year = $req->input('year');
        $selected_schools = $req->input('selected_schools');
        $selected_schools = json_decode($selected_schools);
        $district_id = $req->input('district_id');
        $district_name = getSingleRecordColValue('districts', array('id' => $district_id), 'name');
        $qry = DB::table('beneficiary_payresponses_report as t1')
            ->join('school_information as t2', 't1.school_id', '=', 't2.id')
            ->join('payment_request_details as t3', 't1.payment_request_id', '=', 't3.id')
            ->join('districts as t4', 't4.id', '=', 't2.district_id')
            ->join('provinces as t5', 't5.id', '=', 't4.province_id')
            ->select(DB::raw('t2.code as school_code,t2.name as school_name, t4.name as district_name, t5.name as province_name,
                              t4.code as district_code,t2.id as school_id,t4.id as district_id,t5.id as province_id,
                              sum(t1.total_payable_fees) as total_disbursement'))
            ->where('t3.payment_year', $year)
            ->whereIn('t2.id', $selected_schools)
            ->groupBy('t2.id');
        $results = $qry->get();
        $this->printDisbursementReport($results, $district_name, $year);
    }

    // public function getDisbursementReportForDistrict(Request $req)
    // {
    //     $year = $req->input('year');
    //     $district = $req->input('district');
    //     $district_name = getSingleRecordColValue('districts', array('id' => $district), 'name');
    //     $qry = DB::table('sa_app_beneficiary_list_3 as t1')
    //         ->join('school_information as t2', 't1.school_id', '=', 't2.id')
    //         // ->join('payment_request_details as t3', 't1.payment_request_id', '=', 't3.id')
    //        ->join('districts as t4', DB::raw("SUBSTRING_INDEX(t1.school_district, '-', 1)"), '=', 't4.code')
    //         // ->join('provinces as t5', 't5.id', '=', 't4.province_id')
    //         ->select(DB::raw('t2.code as school_code,t2.name as school_name, t1.school_district as district_name, t1.school_province as province_name,t4.id as district_id,
    //                           t4.code as district_code,t2.id as school_id,
    //                           sum(decrypt(t1.grant_amount)) as total_disbursement'))
    //         // ->where('t3.payment_year', $year)
    //         ->where('t4.id', $district)
    //         ->groupBy('t2.id');
    //     $results = $qry->get();
    //     $this->printDisbursementReport($results, $district_name, $year);
    // }
     public function getDisbursementReportForDistrict(Request $req)
    {
        $year = $req->input('year');
        $district = $req->input('district');
        $district_name = getSingleRecordColValue('districts', array('id' => $district), 'name');
        // $qry = DB::table('payment_disbursement_details as t1')
        $qry = DB::table('beneficiary_payresponses_report as t1')
            ->join('school_information as t2', 't1.school_id', '=', 't2.id')
            ->join('payment_request_details as t3', 't1.payment_request_id', '=', 't3.id')
            ->join('districts as t4', 't4.id', '=', 't2.district_id')
            ->join('provinces as t5', 't5.id', '=', 't4.province_id')
            // ->select(DB::raw('t2.code as school_code,t2.name as school_name, t4.name as district_name, t5.name as province_name,
            //                   t4.code as district_code,t2.id as school_id,t4.id as district_id,t5.id as province_id,
            //                   sum(decrypt(t1.amount_transfered)) as total_disbursement'))
            ->select(DB::raw('t2.code as school_code,t2.name as school_name, t4.name as district_name, t5.name as province_name,
                              t4.code as district_code,t2.id as school_id,t4.id as district_id,t5.id as province_id,
                              sum(t1.total_payable_fees) as total_disbursement'))
            ->where('t3.payment_year', $year)
            ->where('t4.id', $district)
            ->groupBy('t2.id');
        $results = $qry->get();
        $this->printDisbursementReport($results, $district_name, $year);
    }

    public function printDisbursementReport($results, $district_name, $year)
    {
        foreach ($results as $result) {
            PDF::SetTitle('Disbursement Report');
            PDF::SetAutoPageBreak(TRUE, 2);//true sets it to on and 0 means margin is zero from sides
            PDF::SetTitle('Disbursement Report');
            PDF::setMargins(10, 18, 10, true);

            PDF::AddPage('P');
            PDF::SetFont('helvetica', 'B', 10);
            $image_path = '\resources\images\kgs-logo.png';
            PDF::Image(getcwd() . $image_path, 0, 15, 35, 25, 'PNG', '', 'T', false, 300, 'R', false, false, 0, false, false, false);
            PDF::SetY(7);
            PDF::Cell(0, 5, 'MINISTRY OF EDUCATION - KEEPING GIRLS IN SCHOOL PROGRAMME', 0, 1, 'L');
            PDF::Cell(0, 5, 'PAYMENT DISBURSEMENT REPORT FOR THE YEAR ' . $year, 0, 1, 'L');
            PDF::ln();
            PDF::SetFont('helvetica', '', 8);

            //School details
            $school_table = '<table border="1" width="550" cellpadding="3">
                       <tbody>
                       <tr>
                           <td width="100">Name of School:</td>
                           <td>' . $result->school_code . '&nbsp;-&nbsp;' . $result->school_name . '</td>
                       </tr>
                       <tr>
                           <td>Province of School:</td>
                           <td><b>' . $result->province_name . '</b></td>
                       </tr>
                       <tr>
                           <td>District of School:</td>
                           <td>' . $result->district_code . '&nbsp;-&nbsp;' . $result->district_name . '</td>
                       </tr>
                       </tbody></tbody>
                       </table>';
            PDF::writeHTML($school_table, true, false, false, false, 'L');

            //Payment request details
            $paymentReqDetails = $this->getDisbursementReportPayReqDetails($result->school_id, $year);
            // Log::info('Payment request details query: ' . DB::getQueryLog());
            Log::info('Payment request details: ' . json_encode($paymentReqDetails));
            $htmlTable = '
               <table border="1" cellpadding="3" align="center">
               <thead>
                <tr style="font-weight: bold">
                <td>Payment Request Ref</td>
                <td>Number Of Beneficiaries</td>
                <td>Request Amount (Kwacha)</td>
                <td>Disbursed Amount (Kwacha)</td>
                </tr>
                </thead>
                <tbody>';
            $totals_beneficiaries = 0;
            $totals_fees = 0;
            $totals_disbursement = 0;
            foreach ($paymentReqDetails as $paymentReqDetail) {
                $totals_beneficiaries += $paymentReqDetail->no_of_beneficiaries;
                $totals_fees += $paymentReqDetail->total_school_fees;
                $totals_disbursement += $paymentReqDetail->total_disbursement;
                $htmlTable .= '<tr>
                               <td>' . $paymentReqDetail->payment_ref_no . '</td>
                               <td>' . $paymentReqDetail->no_of_beneficiaries . '</td>
                               <td>' . formatMoney($paymentReqDetail->total_school_fees) . '</td>
                               <td>' . formatMoney($paymentReqDetail->total_disbursement) . '</td>';
                $htmlTable .= '</tr>';
            }
            $htmlTable .= '<tr style="font-weight: bold"><td>Total:</td><td>' . $totals_beneficiaries . '</td><td>' . formatMoney($totals_fees) . '</td><td>' . formatMoney($totals_disbursement) . '</td></tr>
                          </tbody></table>';
            PDF::writeHTML($htmlTable);

            //Paid beneficiaries details
            $beneficiaryDetails = $this->printDisbursementReportPaidBeneficiaries($result->school_id, $year);
            $htmlTable = '
               <table border="1" cellpadding="3">
               <thead>
                <tr style="font-weight: bold">
                <td>Beneficiary ID</td>
                <td>First Name</td>
                <td>Last Name</td>
                <td align="center">Grade</td>
                <td align="center">School Status</td>
                <td align="right">Exam Fees</td>
                <td align="right">School Fees</td>
                <td align="right">Total Amount</td>
                </tr>
                </thead>
                <tbody>';
            $tallying_totals = 0;
            foreach ($beneficiaryDetails as $beneficiaryDetail) {
                $tallying_totals += $beneficiaryDetail->school_fees;
                $term1_fees = ($beneficiaryDetail->term1_fees > 0) ? $beneficiaryDetail->term1_fees : 0;
                $term2_fees = ($beneficiaryDetail->term2_fees > 0) ? $beneficiaryDetail->term2_fees : 0;
                $term3_fees = ($beneficiaryDetail->term3_fees > 0) ? $beneficiaryDetail->term3_fees : 0;
                $additional_fee_amount = ($beneficiaryDetail->additional_fee_amount > 0) ? $beneficiaryDetail->additional_fee_amount : 0;
                // $fees = $term1_fees + $term2_fees + $term3_fees;
                $fees = $term1_fees + $term2_fees + $term3_fees + $additional_fee_amount;
                $htmlTable .= '<tr>
                             <td>' . $beneficiaryDetail->beneficiary_no . '</td>
                             <td>' . $beneficiaryDetail->first_name . '</td>
                             <td>' . $beneficiaryDetail->last_name . '</td>
                             <td align="center">' . $beneficiaryDetail->school_grade . '</td>
                             <td align="center">' . $beneficiaryDetail->school_status_name . '</td>

                             <td align="right">' . formatMoney($beneficiaryDetail->exam_fees) . '</td>
                             <td align="right">' . formatMoney($fees) . '</td>

                             <td align="right">' . formatMoney($beneficiaryDetail->school_fees) . '</td>';
                $htmlTable .= '</tr>';
            }
            $htmlTable .= '<tr style="font-weight: bold"><td colspan="7" align="right">Total:</td><td align="right">' . formatMoney($tallying_totals) . '</td></tr>
                          </tbody></table>';
            PDF::writeHTML($htmlTable);
        }
        PDF::Output($district_name . '_' . time() . '.pdf', 'I');
    }
    // public function printDisbursementReport($results, $district_name, $year)
    // {
    //     foreach ($results as $result) {
    //         PDF::SetTitle('Disbursement Report');
    //         PDF::SetAutoPageBreak(TRUE, 2); //true sets it to on and 0 means margin is zero from sides
    //         PDF::SetTitle('Disbursement Report');
    //         PDF::setMargins(10, 18, 10, true);

    //         PDF::AddPage('P');
    //         PDF::SetFont('helvetica', 'B', 10);
    //         $image_path = '\resources\images\kgs-logo.png';
    //         PDF::Image(getcwd() . $image_path, 0, 15, 35, 25, 'PNG', '', 'T', false, 300, 'R', false, false, 0, false, false, false);
    //         PDF::SetY(7);
    //         PDF::Cell(0, 5, 'MINISTRY OF EDUCATION - KEEPING GIRLS IN SCHOOL PROGRAMME', 0, 1, 'L');
    //         PDF::Cell(0, 5, 'PAYMENT DISBURSEMENT REPORT FOR THE YEAR ' . $year, 0, 1, 'L');
    //         PDF::ln();
    //         PDF::SetFont('helvetica', '', 8);

    //         //School details
    //         $school_table = '<table border="1" width="550" cellpadding="3">
    //                    <tbody>
    //                    <tr>
    //                        <td width="100">Name of School:</td>
    //                        <td>' . $result->school_code . '&nbsp;-&nbsp;' . $result->school_name . '</td>
    //                    </tr>
    //                    <tr>
    //                        <td>Province of School:</td>
    //                        <td><b>' . $result->province_name . '</b></td>
    //                    </tr>
    //                    <tr>
    //                        <td>District of School:</td>
    //                        <td>' . $result->district_code . '&nbsp;-&nbsp;' . $result->district_name . '</td>
    //                    </tr>
    //                    </tbody></tbody>
    //                    </table>';
    //         PDF::writeHTML($school_table, true, false, false, false, 'L');

    //         //Payment request details
    //         $paymentReqDetails = $this->getDisbursementReportPayReqDetails($result->school_id, $year);
    //         $htmlTable = '
    //            <table border="1" cellpadding="3" align="center">
    //            <thead>
    //             <tr style="font-weight: bold">
    //             <td>Payment Request Ref</td>
    //             <td>Number Of Beneficiaries</td>
    //             <td>Request Amount (Kwacha)</td>
    //             <td>Disbursed Amount (Kwacha)</td>
    //             </tr>
    //             </thead>
    //             <tbody>';
    //         $totals_beneficiaries = 0;
    //         $totals_fees = 0;
    //         $totals_disbursement = 0;
    //         foreach ($paymentReqDetails as $paymentReqDetail) {
    //             $totals_beneficiaries += $paymentReqDetail->no_of_beneficiaries;
    //             $totals_fees += $paymentReqDetail->total_school_fees;
    //             $totals_disbursement += $paymentReqDetail->total_disbursement;
    //             $htmlTable .= '<tr>
    //                            <td>' . $paymentReqDetail->payment_ref_no . '</td>
    //                            <td>' . $paymentReqDetail->no_of_beneficiaries . '</td>
    //                            <td>' . formatMoney($paymentReqDetail->total_school_fees) . '</td>
    //                            <td>' . formatMoney($paymentReqDetail->total_disbursement) . '</td>';
    //             $htmlTable .= '</tr>';
    //         }
    //         $htmlTable .= '<tr style="font-weight: bold"><td>Total:</td><td>' . $totals_beneficiaries . '</td><td>' . formatMoney($totals_fees) . '</td><td>' . formatMoney($totals_disbursement) . '</td></tr>
    //                       </tbody></table>';
    //         PDF::writeHTML($htmlTable);

    //         //Paid beneficiaries details
    //         $beneficiaryDetails = $this->printDisbursementReportPaidBeneficiaries($result->school_id, $year);
    //         $htmlTable = '
    //            <table border="1" cellpadding="3">
    //            <thead>
    //             <tr style="font-weight: bold">
    //             <td>Beneficiary ID</td>
    //             <td>First Name</td>
    //             <td>Last Name</td>
    //             <td align="center">Grade</td>
    //             <td align="center">School Status</td>
    //             <td align="right">Exam Fees</td>
    //             <td align="right">School Fees</td>
    //             <td align="right">Total Amount</td>
    //             </tr>
    //             </thead>
    //             <tbody>';
    //         $tallying_totals = 0;
    //         foreach ($beneficiaryDetails as $beneficiaryDetail) {
    //             $tallying_totals += $beneficiaryDetail->school_fees;
    //             $term1_fees = ($beneficiaryDetail->term1_fees > 0) ? $beneficiaryDetail->term1_fees : 0;
    //             $term2_fees = ($beneficiaryDetail->term2_fees > 0) ? $beneficiaryDetail->term2_fees : 0;
    //             $term3_fees = ($beneficiaryDetail->term3_fees > 0) ? $beneficiaryDetail->term3_fees : 0;
    //             $fees = $term1_fees + $term2_fees + $term3_fees;
    //             $htmlTable .= '<tr>
    //                          <td>' . $beneficiaryDetail->beneficiary_no . '</td>
    //                          <td>' . $beneficiaryDetail->first_name . '</td>
    //                          <td>' . $beneficiaryDetail->last_name . '</td>
    //                          <td align="center">' . $beneficiaryDetail->school_grade . '</td>
    //                          <td align="center">' . $beneficiaryDetail->school_status_name . '</td>

    //                          <td align="right">' . formatMoney($beneficiaryDetail->exam_fees) . '</td>
    //                          <td align="right">' . formatMoney($fees) . '</td>

    //                          <td align="right">' . formatMoney($beneficiaryDetail->school_fees) . '</td>';
    //             $htmlTable .= '</tr>';
    //         }
    //         $htmlTable .= '<tr style="font-weight: bold"><td colspan="7" align="right">Total:</td><td align="right">' . formatMoney($tallying_totals) . '</td></tr>
    //                       </tbody></table>';
    //         PDF::writeHTML($htmlTable);
    //         $footerText = '<p>*For any complaint relating to Keeping Girls in School or your welfare (GBV, School Bullying or HIV) 
    //         please call Lifeline ChildLine (Toll-Free) on <b>933</b> or <b>116</b>.</p>';
    //         PDF::SetY(-8);
    //         PDF::writeHTML($footerText, true, 0, true, true);
    //     }
    //     PDF::Output($district_name . '_' . time() . '.pdf', 'I');
    // }

    public function detailedReport()
    {
        $year = 2017;
        $term = 2;
        $qry = DB::table('beneficiary_enrollments as t1')
            ->join('beneficiary_information as t2', 't1.beneficiary_id', '=', 't2.id')
            ->join('districts as t3', 't2.district_id', '=', 't3.id')
            ->select(DB::raw("count(t2.id) as no_of_beneficiaries,
                  sum(t1.school_fees) as total_fees, t3.name as home_district"))
            ->where('t1.year_of_enrollment', $year)
            ->where('t1.term_id', $term)
            ->where('t1.is_validated', 1)
            ->groupBy('t3.id');

        $results = $qry->get();
        $total_fees = 0;
        $total_beneficiaries = 0;
        $table = "<table border='1'>
                <thead>
                <tr>
                <td>District</td>
                <td>Number of Beneficiaries</td>
                <td>School Fees</td>
                </tr>
                </thead>";
        foreach ($results as $result) {
            $total_beneficiaries += $result->no_of_beneficiaries;
            $total_fees += $result->total_fees;
            $table .= "<tr>
                     <td>" . $result->home_district . "</td>
                     <td>" . $result->no_of_beneficiaries . "</td>
                     <td>" . $result->total_fees . "</td></tr>";
        }
        $table .= "<tr><td>Totals</td><td>" . $total_beneficiaries . "</td><td>" . $total_fees . "</td></tr>";
        $table .= "</table>";
        print_r($table);
    }

    public function paymentVerificationProgressReport(Request $request)
    {
        $year = $request->input('year');
        $term = $request->input('term');
        $term_txt = '';
        if (validateisNumeric($term)) {
            $term_txt = 'Term ' . $term;
        }
        $data = DB::table('beneficiary_information as t1')
            ->join('school_information as t2', 't1.school_id', '=', 't2.id')
            ->join('districts as t3', 't2.district_id', '=', 't3.id')
            ->select(DB::raw('count(t1.id) as total,t1.school_id,t2.name as school_name,t3.name as district_name'))
            ->where('enrollment_status', 1)
            ->where('beneficiary_status', 4)
            ->groupBy('t1.school_id')
            ->orderBy('t2.district_id')
            ->get();
        $table = "<p>Payment Verification Progress for $year $term_txt</p>
                <table border='1'>
                <thead>
                <tr>
                <td>District</td>
                <td>School</td>
                <td>Currently enrolled</td>
                <td>Total verified</td>
                <td>Total valid</td>
                <td>Checklist Received?</td>
                </tr>
                </thead>";
        foreach ($data as $key => $datum) {
            $record = $this->getEntered($datum->school_id, $year, $term);
            $flag = 'YES';
            if ($record->entered == 0) {
                $flag = 'NO';
            }
            $table .= "<tr>
                     <td>" . $datum->district_name . "</td>
                     <td>" . $datum->school_name . "</td>
                     <td>" . $datum->total . "</td>
                     <td>" . $record->entered . "</td>
                     <td>" . $record->valid . "</td>
                     <td>" . $flag . "</td></tr>";
        }
        $table .= "</table>";
        print_r($table);
    }

    public function getEntered($school_id, $year, $term)
    {
        $qry = DB::table('beneficiary_enrollments as t1')
            ->select(DB::raw('count(t1.beneficiary_id) as entered,SUM(IF(t1.passed_rules=1,1,0)) as valid'))
            ->where(array('t1.year_of_enrollment' => $year))
            ->where('t1.school_id', $school_id);
        if (validateisNumeric($term)) {
            $qry->where('term_id', $term);
        }
        $entered = $qry->first();
        return $entered;
    }

    public function postPaymentsToPayFlexi()//perfect login
    {        
        $username = 'zispis';
        $password = 'aquiwei5taelaijohn6shoopeeQuie7e';
        $apikey   = 'eecai6EKi7wohm8Uongaif7obuayoh1c';
        $url = 'https://pg.zispis.gov.zm/sps/api/zispis/prod/kgs/login';
        try {
            $client = new \GuzzleHttp\Client([
                'verify' => false
            ]);
            $response = $client->request('POST', $url, [
                'auth' => [$username, $password],
                'headers' => [
                    'X-APIKey' => $apikey,
                    'Accept' => 'application/json',
                ],
                'verify' => false, // skip SSL verification
                'http_errors' => false // prevents exceptions
            ]);
            $statusCode = $response->getStatusCode();
            $headers = $response->getHeaders();
            $body = (string) $response->getBody();
            $head_cookie = $headers['Set-Cookie'][0];
            $exploded = explode(';', $head_cookie);
            $session_str = substr($exploded[0],strlen('session='));
            $auth_cookie = substr($exploded[0],strlen('session='));
            $pay_load = array(
                'success_status' => 1,
                'attempts' => 1,
                'session_str' => $session_str,
                'return_msg' => $body,
                'error_code' => $statusCode,
                'connection_server' => $headers['Server'][0],
                'response_date' => $headers['Date'][0],
                'content_type' => $headers['Content-Type'][0],
                'content_length' => $headers['Content-Length'][0],
                'connection_comment' => $headers['Connection'][0],
                'auth_cookie' => $head_cookie
                );
            $save_resp = DB::table('pg_payments_trans')
                    ->insert($pay_load);
            return response()->json([
                'success' => true,
                'data' => $pay_load,
                'message' => 'Login successfull!!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }  

    public function testPgLogin23()//perfect payment
    {
        try {
            $school_info = 0;
            $uuid = Uuid::uuid4()->toString();
            $str = Str::after($uuid, '-');
            $TransactionID = "KGSTRIDT-$str";
            $RecipientID = $uuid;            
            $MobileNumber = "260978166385";
            $TransactionType = "Grant";
            $TransactionDate=Carbon::now()->format('Y-m-d\TH:i:s');
            $username = 'zispis';
            $password = 'aquiwei5taelaijohn6shoopeeQuie7e';
            $apikey   = 'eecai6EKi7wohm8Uongaif7obuayoh1c';
            $url = "https://pg.zispis.gov.zm/sps/api/zispis/prod/kgs/payment/$TransactionID";
            $get_cookie = DB::table('pg_payments_trans')
                ->orderBy('id', 'desc')
                ->value('auth_cookie');
            $auth_cook = preg_match('/=(.*?);/', $get_cookie, $matches) ? $matches[1] : null;          
            $data = [
                "TransactionID" => "$TransactionID",
                "TransactionDate" => "$TransactionDate",
                "RecipientID" => "$RecipientID",                  "RecipientType" => "Beneficiary",
                "Gender" => "Female",                       "FirstName" => 'Miriam',
                "LastName" => 'Chongo',                     "MobileNumber" => "$MobileNumber",
                "Language" => "English",                    "LanguageCode" => "eng",
                "Country" => "ZM",                          "PSP" => "Zanaco",
                "Province" => 'Copperbelt',                 "District" => 'Ndola',
                "Ward" => '',                               "CWAC" => 'Ngugu',
                "RegisteredTown" => "",                     "DistrictID" =>54,
                "WardID" => 127,                            "CWACID" => 564,
                "HouseholdID" => 765,                       "NRC" => '138446/21/1',
                "DateOfBirth" => '08/09/2010',              "AccountNumber" => '5755132300226',
                "AccountExtra" => "",                       "Currency" => "ZMW",
                "TransactionType" => "$TransactionType",      "Amount" => 0,
                "GPSAccuracy" => 0,                         "GPSAltitude" => 0,
                "GPSLatitude" => 0,                         "GPSLongitude" => 0,
                "PaymentReference" => "[UNDELIVERED][UNDELIVERED][UNDELIVERED]Manual payment",
                "PaymentCycle" => "KGS 2025 Term 2 Payment"                
            ];
            $client = new \GuzzleHttp\Client([
                'verify' => false
            ]);
            $auth = base64_encode("$username:$password");
            $headers = [
                "Authorization: Basic $auth",
                "Content-Type: application/json",
                "Accept: application/json",
                "X-APIKey" => $apikey,
                "accept-encoding" => "gzip, deflate"
            ];
            $response = $client->request('POST', $url, [
                'headers' => $headers,
                "User-Agent" => "vscode-restclient",
                'json' => $data,
                'verify' => false,
                'http_errors' => false
            ]);
            // dd($response);

            $statusCode = $response->getStatusCode();
            $headers = $response->getHeaders();
            $body = $response->getBody()->getContents();
            $responses = [
                "status_code" => $statusCode,
                "headers" => $headers,
                "response_body" => $body                          
            ];
            return response()->json([
                'success' => true,
                'responses' => $responses,
                'message' => 'Payment Request Submitted Successfully'
            ]);
        } catch (\Exception $e) {
            $errorString = $e->getMessage();
            return response()->json([
                'success' => false,
                'message' => $errorString
            ], 500);
        }
    }

    public function testPgLogin()//perfect query
    {
        try {
            $school_info = 0;
            $uuid = Uuid::uuid4()->toString();
            $str = Str::after($uuid, '-');
            $TransactionID = "KGSTRIDT-$str";
            $RecipientID = $uuid;            
            $MobileNumber = "260978166385";
            $TransactionType = "Grant";
            $tid = "KGSTRIDT-763d-4477-a9c1-88670eaebcd6";
            $TransactionDate=Carbon::now()->format('Y-m-d\TH:i:s');
            $username = 'zispis';
            $password = 'aquiwei5taelaijohn6shoopeeQuie7e';
            $apikey   = 'eecai6EKi7wohm8Uongaif7obuayoh1c';
            $url = "https://pg.zispis.gov.zm/sps/api/zispis/prod/kgs/query/$tid";
            $get_cookie = DB::table('pg_payments_trans')
                ->orderBy('id', 'desc')
                ->value('auth_cookie');
            $auth_cook = preg_match('/=(.*?);/', $get_cookie, $matches) ? $matches[1] : null;          
            $data = [
                "TransactionID" => "$TransactionID",
                "TransactionDate" => "$TransactionDate",
                "RecipientID" => "$RecipientID",                  "RecipientType" => "Beneficiary",
                "Gender" => "Female",                       "FirstName" => 'Miriam',
                "LastName" => 'Chongo',                     "MobileNumber" => "$MobileNumber",
                "Language" => "English",                    "LanguageCode" => "eng",
                "Country" => "ZM",                          "PSP" => "Zanaco",
                "Province" => 'Copperbelt',                 "District" => 'Ndola',
                "Ward" => '',                               "CWAC" => 'Ngugu',
                "RegisteredTown" => "",                     "DistrictID" =>54,
                "WardID" => 127,                            "CWACID" => 564,
                "HouseholdID" => 765,                       "NRC" => '138446/21/1',
                "DateOfBirth" => '08/09/2010',              "AccountNumber" => '5755132300226',
                "AccountExtra" => "",                       "Currency" => "ZMW",
                "TransactionType" => "$TransactionType",      "Amount" => 0,
                "GPSAccuracy" => 0,                         "GPSAltitude" => 0,
                "GPSLatitude" => 0,                         "GPSLongitude" => 0,
                "PaymentReference" => "[UNDELIVERED][UNDELIVERED][UNDELIVERED]Manual payment",
                "PaymentCycle" => "KGS 2025 Term 2 Payment"                
            ];
            $client = new \GuzzleHttp\Client([
                'verify' => false
            ]);
            $auth = base64_encode("$username:$password");
            $headers = [
                "Authorization: Basic $auth",
                "Content-Type: application/json",
                "Accept: application/json",
                "X-APIKey" => $apikey,
                "accept-encoding" => "gzip, deflate"
            ];
            $response = $client->request('POST', $url, [
                'headers' => $headers,
                "User-Agent" => "vscode-restclient",
                // 'json' => $data,
                'verify' => false,
                'http_errors' => false
            ]);

            dd($response);

            $statusCode = $response->getStatusCode();
            $headers = $response->getHeaders();
            $body = $response->getBody()->getContents();
            $responses = [
                "status_code" => $statusCode,
                "headers" => $headers,
                "response_body" => $body                          
            ];
            return response()->json([
                'success' => true,
                'responses' => $responses,
                'message' => 'Payment Request Submitted Successfully'
            ]);
        } catch (\Exception $e) {
            $errorString = $e->getMessage();
            return response()->json([
                'success' => false,
                'message' => $errorString
            ], 500);
        }
    }  

    public function getPGresponseData(Request $request)
    {        
        $res = array(
            'success' => true,
            'results' => [],
            'message' => 'All is well'
        );
        return response()->json($res);
    }

    //  public function getDisbursementReportForDistrict(Request $req)
    // {
    //     $district = (int) $req->input('district');
    //     $year = (int) $req->input('year', 2025);

    //     return $this->buildDistrictDisbursementData($district, $year);
    // }
    public function buildDistrictDisbursementData(int $district, int $year)
    {


        $district_name = getSingleRecordColValue(
            'districts',
            ['id' => $district],
            'name'
        );

        //    Fetch schools and total disbursment
        $schools = DB::table('sa_app_beneficiary_list_3 as t1')
            ->join('school_information as t2', 't1.school_id', '=', 't2.id')
            ->join(
                'districts as t4',
                DB::raw("SUBSTRING_INDEX(t1.school_district, '-', 1)"),
                '=',
                't4.code'
            )
            ->select(
                't2.id as school_id',
                't2.code as school_code',
                't2.name as school_name',
                't1.school_province as province_name',
                't4.code as district_code',
                't4.name as district_name',
                't1.grant_amount',
                DB::raw('SUM(decrypt(t1.grant_amount)) as total_disbursement')
            )
            ->where('t4.id', $district)
             ->where('t1.in_excel', '=',1)
            ->groupBy(
                't2.id',
                't2.code',
                't2.name',
                't1.school_province',
                't4.code',
                't4.name'
            )
            ->orderBy('t2.name')
            ->get();

        //   Payment and beneficiaries per school
        foreach ($schools as $school) {

            // payment request details
            $school->payment_requests = DB::table('sa_app_beneficiary_list_3')
                ->where('school_id', $school->school_id)
                ->where('in_excel','=',1)
                ->select(
                    'payment_ref_no',
                    'grant_amount',
                    DB::raw('COUNT(beneficiary_id) as no_of_beneficiaries'),
                    // DB::raw('SUM(school_fees) as total_school_fees'),
                    // DB::raw('SUM(school_fees) as total_disbursement')
                )
                ->groupBy('payment_ref_no')
                ->orderBy('payment_ref_no')
                ->get();

            //    Beneficiaries Details
            $school->beneficiaries = DB::table('sa_app_beneficiary_list_3')
                ->where('school_id', $school->school_id)
                ->where('in_excel','=',1)
                ->select(
                    'beneficiary_no',
                    'first_name',
                    'last_name',
                    'school_grade',
                    'school_status',
                    'exam_fees',
                    'grant_amount',
                    'school_fees',
                    'term1_fees',
                    'term2_fees',
                    'term3_fees'
                )
                // ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();
        }

        //   send to pdf
        $this->printGrantDisbursementReport(
            $schools,
            $district_name,
            $year
        );
    }
    public function printGrantDisbursementReport($results, $district_name, $year)
    {
        foreach ($results as $result) {
            PDF::SetTitle('Disbursement Report');
            PDF::SetAutoPageBreak(TRUE, 2); //true sets it to on and 0 means margin is zero from sides
            PDF::SetTitle('Disbursement Report');
            PDF::setMargins(10, 18, 10, true);

            PDF::AddPage('P');
            PDF::SetFont('helvetica', 'B', 10);
            $image_path = '\resources\images\kgs-logo.png';
            PDF::Image(getcwd() . $image_path, 0, 15, 35, 25, 'PNG', '', 'T', false, 300, 'R', false, false, 0, false, false, false);
            PDF::SetY(7);
            PDF::Cell(0, 5, 'MINISTRY OF EDUCATION - KEEPING GIRLS IN SCHOOL PROGRAMME', 0, 1, 'L');
            PDF::Cell(0, 5, 'PAYMENT DISBURSEMENT REPORT FOR THE YEAR ' . $year, 0, 1, 'L');
            PDF::ln();
            PDF::SetFont('helvetica', '', 8);

            //School details
            $school_table = '<table border="1" width="550" cellpadding="3">
                       <tbody>
                       <tr>
                           <td width="100">Name of School:</td>
                           <td>' . $result->school_code . '&nbsp;-&nbsp;' . $result->school_name . '</td>
                       </tr>
                       <tr>
                           <td>Province of School:</td>
                           <td><b>' . $result->province_name . '</b></td>
                       </tr>
                       <tr>
                           <td>District of School:</td>
                           <td>' . $result->district_code . '&nbsp;-&nbsp;' . $result->district_name . '</td>
                       </tr>
                       </tbody></tbody>
                       </table>';
            PDF::writeHTML($school_table, true, false, false, false, 'L');

            //Payment request details
            // $paymentReqDetails = $this->getDisbursementReportForDistrict($result->school_id, $year);
            $paymentReqDetails = $result->payment_requests;
            $htmlTable = '
               <table border="1" cellpadding="3" align="center">
               <thead>
                <tr style="font-weight: bold">
                <td>Payment Request Ref</td>
                <td>Number Of Beneficiaries</td>
                <td>Request Amount (Kwacha)</td>
                <td>Disbursed Amount (Kwacha)</td>
                </tr>
                </thead>
                <tbody>';
            $totals_beneficiaries = 0;
            $totals_fees = 0;
            $totals_disbursement = 0;
            foreach ($paymentReqDetails as $paymentReqDetail) {
                $totals_beneficiaries += $paymentReqDetail->no_of_beneficiaries;
                $totals_fees += $paymentReqDetail->grant_amount;
                $totals_disbursement += $paymentReqDetail->grant_amount;
                $htmlTable .= '<tr>
                               <td>' . $paymentReqDetail->payment_ref_no . '</td>
                               <td>' . $paymentReqDetail->no_of_beneficiaries . '</td>
                               <td>' . formatMoney($paymentReqDetail->grant_amount) . '</td>
                               <td>' . formatMoney($paymentReqDetail->grant_amount) . '</td>';
                $htmlTable .= '</tr>';
            }
            $htmlTable .= '<tr style="font-weight: bold"><td>Total:</td><td>' . $totals_beneficiaries . '</td><td>' . formatMoney($totals_fees) . '</td><td>' . formatMoney($totals_disbursement) . '</td></tr>
                          </tbody></table>';
            PDF::writeHTML($htmlTable);

            //Paid beneficiaries details
            // $beneficiaryDetails = $this->getDisbursementReportForDistrict($result->school_id, $year);
            $beneficiaryDetails = $result->beneficiaries;

            $htmlTable = '
               <table border="1" cellpadding="3">
               <thead>
                <tr style="font-weight: bold">
                <td>No.</td>
                <td>Beneficiary ID</td>
                <td>First Name</td>
                <td>Last Name</td>
                <td align="right">Grant Amount</td>
                <td align="right">Total Amount</td>
                </tr>
                </thead>
                <tbody>';
            $tallying_totals = 0;
            $counter = 1;
            foreach ($beneficiaryDetails as $beneficiaryDetail) {
                $grant = (float) $beneficiaryDetail->grant_amount;
                // $tallying_totals += $beneficiaryDetail->grant_amount;
                $tallying_totals += $grant;
                $fees = $beneficiaryDetail->grant_amount;
                $htmlTable .= '<tr>
                            <td align="center">' . $counter++ . '</td>
                             <td>' . $beneficiaryDetail->beneficiary_no . '</td>
                             <td>' . $beneficiaryDetail->first_name . '</td>
                             <td>' . $beneficiaryDetail->last_name . '</td>
                             <td align="right">' . formatMoney($beneficiaryDetail->grant_amount) . '</td>
                             <td align="right">' . formatMoney($fees) . '</td>';


                $htmlTable .= '</tr>';
            }
            $htmlTable .= '<tr style="font-weight: bold">
    <td colspan="5" align="right">Total:</td>
    <td align="right">' . formatMoney($tallying_totals) . '</td>
</tr>
                          </tbody></table>';
            PDF::writeHTML($htmlTable);
            $footerText = '<p>*For any complaint relating to KGS or your welfare (GBV, School Bullying or HIV) 
            please call Lifeline ChildLine (Toll-Free) on <b>933</b> or <b>116</b>.</p>';
            PDF::SetY(-8);
            PDF::writeHTML($footerText, true, 0, true, true);
        }
        PDF::Output($district_name . '_' . time() . '.pdf', 'I');
    }

}
