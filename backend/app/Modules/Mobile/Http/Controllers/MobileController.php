<?php

namespace App\Modules\Mobile\Http\Controllers;

use App\Modules\GrmModule\Traits\GrmModuleTrait;
use App\Http\Controllers\BaseController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Validator;
use Ramsey\Uuid\Uuid;

use TCPDF;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Storage;

class MobileController extends Controller
{
    use GrmModuleTrait;
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
        return view('mobile::index');
    }

	public function syncGrm(Request $req){
		
		$form_data = $req->form_data;
		$form_data = (array)json_decode($form_data);
		$form_data_count = sizeof($form_data);
        $table_name = "grm_complaint_details";
        $user_id = '4';
        
        try{

            $form_array = [];
            for($i=0;$i<$form_data_count;$i++){

                $form_insert = (array)json_decode($form_data[$i]->form_data);
                $complaint_form_no = $form_insert['complaint_form_no'];
                //generate the view_id
                //generateRecordViewID();
                $form_insert['view_id'] = generateRecordViewID();
                $form_insert['mobile_submission_date'] = Carbon::now()->toDateTimeString();
                $table_data = $form_insert;
                $user_id = $form_insert['created_by'];
                //do the insert here....
                
                $where = array(
                    'complaint_form_no' => $complaint_form_no
                );
                
                if (recordExists($table_name, $where)) {
                    $previous_data = getPreviousRecords($table_name, $where);
                    updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                } else {
                    $form_array[] = $table_data;
                }
                
            }

            DB::table($table_name)->insert($form_array);

            //send email and put view id to the synced records
            /*for($x=0;$x<$form_data_count;$x++){
                $form_insert = (array)json_decode($form_data[$x]->form_data);
                $complaint_form_no = $form_insert['complaint_form_no'];
                $synced_details = getTableData('grm_complaint_details', array(
                    'created_by' => $user_id, 'is_mobile' => 1, 'complaint_form_no' => $complaint_form_no)
                );

                self::sendemails($synced_details->category_id,$synced_details->id,$synced_details->programme_type_id,$synced_details->province_id,$synced_details->nongewel_programme_type_id);

            }*/

            $res = array(
                'success' => true,
                'message' => 'Data updated Successfully!!'
            );

        }catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        }catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        //print_r($res);
        //exit();
		return response()->json($res);
	}
	
	function sendemails($category_id,$record_id,$programme_type_id,$province_id,$nongewel_programme_type_id){
		$category_details = getTableData('grm_complaint_categories', array('id' => $category_id));
        $immediate_escalation = 0;
        if ($category_details) {
            $immediate_escalation = $category_details->immediate_escalation;
        }
        //1. Escalation
        if ($immediate_escalation == 1 || $immediate_escalation === 1) {
            $not_details = array(
                        'record_id' => $record_id,
                        'notification_type_id' => 1,
                        'notification_date' => Carbon::now(),
                        'status' => 1
            );
            DB::table('grm_notifications')->insert($not_details);
            $this->grievanceEscalationEmailNotification($programme_type_id, $province_id);//HQ & Province
        }
        //2. Referrals
        if ($programme_type_id == 3 || $programme_type_id === 3) {
            $not_details = array(
                'record_id' => $record_id,
                'notification_type_id' => 2,
                'notification_date' => Carbon::now(),
                'status' => 1
            );
            DB::table('grm_notifications')->insert($not_details);
            $this->grievanceReferralEmailNotification($nongewel_programme_type_id);
        }
	}



    // functions for School accountant app are below (added by joseph oct 2025)
    //login
    public function loginV1(Request $request)
    {
        $validatedData = $request->validate([
            'Email'    => 'required|string',
            'Password' => 'required|string',
        ]);

        try {
            $client = new \GuzzleHttp\Client([
                'base_uri' => 'https://kgsmis.edu.gov.zm/api/',
                'timeout'  => 15,
            ]);

            $response = $client->post('api-login', [
                'json' => [
                    'email'       => $validatedData['Email'],
                    'password'    => $validatedData['Password'],
                    'remember_me' => 1,
                ],
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            $httpStatus = $response->getStatusCode();
            $payload    = json_decode((string) $response->getBody(), true) ?: [];

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $httpStatus = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;
            $body       = $e->hasResponse() ? (string) $e->getResponse()->getBody() : null;

            return response()->json([
                'Message' => 'Could not reach the server. Please try again later.',
                'Code'    => $httpStatus,
                'Details' => $body,
            ], $httpStatus === 0 ? 502 : $httpStatus);
        }

        $code            = isset($payload['code']) ? (int) $payload['code'] : $httpStatus;
        $externalMessage = $payload['message'] ?? null;

        if ($code !== 200) {
            switch ($code) {
                case 404:
                    $friendlyMessage = "We couldn't find an account with that email address.";
                    break;
                case 403:
                    $friendlyMessage = "This account has been blocked. Please contact support.";
                    break;
                case 401:
                    $friendlyMessage = "Incorrect password or too many failed login attempts. Try again later.";
                    break;
                default:
                    $friendlyMessage = "Login failed. Please try again.";
                    break;
            }

            return response()->json([
                'Message' => $friendlyMessage,
                'Code'    => $code,
                'Details' => $externalMessage,
            ], ($code >= 100 && $code < 600) ? $code : 400);
        }

        $apiUser = $payload['user'] ?? null;
        $userId  = is_array($apiUser) ? ($apiUser['user_id'] ?? $apiUser['id'] ?? null) : null;

        if (!$userId) {
            return response()->json([
                'Message' => 'Login successful but user_id missing in API response.',
                'Code'    => 422,
            ], 422);
        }

        // Ensure the user has access to the School Accountant App
        if (isset($apiUser['has_ppm_app_access']) && $apiUser['has_ppm_app_access'] == 0) {
            return response()->json([
                'Message' => 'You do not have access to the school accountant app.',
                'Code'    => 403,
            ], 403);
        }

        $kgsMisUser = \DB::table('users')
            ->where('id', $userId)
            ->select(\DB::raw('uuid, decrypt(email) AS email'))
            ->first();

        if (!$kgsMisUser) {
            return response()->json([
                'Message' => 'User login was successful but KGS MIS User record not found.',
                'Code'    => 404,
            ], 404);
        }

        $userUuid = $kgsMisUser->uuid;

        $tokenString = \Illuminate\Support\Str::random(80);

        \DB::table('sa_app_token_management')->insert([
            'user_uuid'  => $userUuid,
            'token'      => $tokenString,
            'expires_at' => now()->addHours(24),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $existing = \DB::table('sa_app_user_management')
            ->where('user_id', $userId)
            ->first();

        if (!$existing) {
            \DB::table('sa_app_user_management')->insert([
                'user_id'           => $userId,
                'district_assigned' => '',
                'school_assigned'   => '',
                'school_cwac'       => '',
                'access_token'      => $tokenString,
                'API_key'           => \Illuminate\Support\Str::random(40),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        } else {
            \DB::table('sa_app_user_management')
                ->where('user_id', $userId)
                ->update([
                    'access_token' => $tokenString,
                    'updated_at'   => now(),
                ]);
        }

        \DB::table('sa_user_activity_logs')->insert([
            'user_uuid'    => $userUuid,
            'activity_type'=> 'login',
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->header('User-Agent'),
            'created_at'   => now(),
        ]);

        $ppmAppUser = \DB::table('sa_app_user_details')
            ->where('user_id', $userId)
            ->select('district_assigned_string', 'school_assigned_string', 'school_cwac_string')
            ->first();

        return response()->json([
            'access_token' => $tokenString,
            'token_type'   => 'Bearer',
            'expires_at'   => now()->addHours(24)->toDateTimeString(),
            'user' => [
                'uuid'              => $kgsMisUser->uuid,
                'email'             => $kgsMisUser->email,
                'district_assigned' => $ppmAppUser->district_assigned_string ?? null,
                'school_assigned'   => $ppmAppUser->school_assigned_string ?? null,
                'school_cwac'       => $ppmAppUser->school_cwac_string ?? null,
            ],
        ], 200);
    }

    public function login(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | 1) Enforce JSON-only API contract
        |--------------------------------------------------------------------------
        */
        if (!$request->expectsJson()) {
            return response()->json([
                'Message' => 'Unsupported media type. JSON requests only.',
                'Code'    => 415,
            ], 415);
        }

        /*
        |--------------------------------------------------------------------------
        | 2) Manual validation (NO $request->validate())
        |--------------------------------------------------------------------------
        */
        $validator = \Validator::make($request->all(), [
            'Email'    => 'required|string',
            'Password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'Message' => 'Validation failed.',
                'Errors'  => $validator->errors(),
                'Code'    => 422,
            ], 422);
        }

        $validatedData = $validator->validated();

        /*
        |--------------------------------------------------------------------------
        | 3) Call upstream MIS login API
        |--------------------------------------------------------------------------
        */
        try {
            $client = new \GuzzleHttp\Client([
                // 'base_uri' => 'https://kgsmis.edu.gov.zm/api/',
                'base_uri' => config('app.pg_base_url') . '/api/',
                'timeout'  => 15,
            ]);

            $response = $client->post('api-login', [
                'json' => [
                    'email'       => $validatedData['Email'],
                    'password'    => $validatedData['Password'],
                    'remember_me' => 1,
                ],
                'headers' => [
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ]);

            $httpStatus = $response->getStatusCode();
            $payload    = json_decode((string) $response->getBody(), true) ?: [];

        } catch (\GuzzleHttp\Exception\RequestException $e) {

            $httpStatus = $e->hasResponse()
                ? $e->getResponse()->getStatusCode()
                : 502;

            // Log upstream response internally (never expose to client)
            \Log::error('MIS Login Error', [
                'status' => $httpStatus,
                'body'   => $e->hasResponse() ? (string) $e->getResponse()->getBody() : null,
            ]);

            return response()->json([
                'Message' => 'Authentication service unavailable. Please try again later.',
                'Code'    => $httpStatus,
            ], $httpStatus);
        }

        /*
        |--------------------------------------------------------------------------
        | 4) Handle upstream error codes cleanly
        |--------------------------------------------------------------------------
        */
        $code            = (int) ($payload['code'] ?? $httpStatus);
        $externalMessage = $payload['message'] ?? null;

        if ($code !== 200) {
            switch ($code) {
                case 404:
                    $friendlyMessage = "We couldn't find an account with that email address.";
                    break;
                case 403:
                    $friendlyMessage = "This account has been blocked. Please contact support.";
                    break;
                case 401:
                    $friendlyMessage = "Incorrect credentials or too many failed attempts.";
                    break;
                default:
                    $friendlyMessage = "Login failed. Please try again.";
            }

            return response()->json([
                'Message' => $friendlyMessage,
                'Code'    => $code,
            ], ($code >= 100 && $code < 600) ? $code : 400);
        }

        /*
        |--------------------------------------------------------------------------
        | 5) Extract user + access checks
        |--------------------------------------------------------------------------
        */
        $apiUser = $payload['user'] ?? null;
        $userId  = is_array($apiUser)
            ? ($apiUser['user_id'] ?? $apiUser['id'] ?? null)
            : null;

        if (!$userId) {
            return response()->json([
                'Message' => 'Login successful but user identifier missing.',
                'Code'    => 422,
            ], 422);
        }

        if (!empty($apiUser['has_ppm_app_access']) && $apiUser['has_ppm_app_access'] == 0) {
            return response()->json([
                'Message' => 'You do not have access to the School Accountant App.',
                'Code'    => 403,
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | 6) Fetch local MIS user
        |--------------------------------------------------------------------------
        */
        $kgsMisUser = \DB::table('users')
            ->where('id', $userId)
            ->select(\DB::raw('uuid, decrypt(email) AS email'))
            ->first();

        if (!$kgsMisUser) {
            return response()->json([
                'Message' => 'User authenticated but not found in MIS.',
                'Code'    => 404,
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | 7) Token generation + persistence
        |--------------------------------------------------------------------------
        */
        $tokenString = \Illuminate\Support\Str::random(80);

        \DB::table('sa_app_token_management')->insert([
            'user_uuid'  => $kgsMisUser->uuid,
            'token'      => $tokenString,
            'expires_at' => now()->addHours(24),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('sa_app_user_management')->updateOrInsert(
            ['user_id' => $userId],
            [
                'access_token' => $tokenString,
                'API_key'      => \Illuminate\Support\Str::random(40),
                'updated_at'   => now(),
                'created_at'   => now(),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | 8) Activity logging
        |--------------------------------------------------------------------------
        */
        \DB::table('sa_user_activity_logs')->insert([
            'user_uuid'     => $kgsMisUser->uuid,
            'activity_type' => 'login',
            'ip_address'    => $request->ip(),
            'user_agent'    => $request->header('User-Agent'),
            'created_at'    => now(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | 9) Load app-specific assignments
        |--------------------------------------------------------------------------
        */
        $ppmAppUser = \DB::table('sa_app_user_details')
            ->where('user_id', $userId)
            ->select(
                'district_assigned_string',
                'school_assigned_string',
                'school_cwac_string',
                'zonal_accountant'
            )
            ->first();

        /*
        |--------------------------------------------------------------------------
        | 10) Final JSON response (locked & safe)
        |--------------------------------------------------------------------------
        */
        return response()
            ->json([
                'access_token' => $tokenString,
                'token_type'   => 'Bearer',
                'expires_at'   => now()->addHours(24)->toDateTimeString(),
                'user' => [
                    'uuid'              => $kgsMisUser->uuid,
                    'email'             => $kgsMisUser->email,
                    'district_assigned' => $ppmAppUser->district_assigned_string ?? null,
                    'school_assigned'   => $ppmAppUser->school_assigned_string ?? null,
                    'school_cwac'       => $ppmAppUser->school_cwac_string ?? null,
                    'zonal_accountant'  => $ppmAppUser->zonal_accountant ?? null,
                ],
            ], 200)
            ->header('Content-Type', 'application/json')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    public function testing(Request $request)
    {
        return response()->json([
            'status' => true,
            'message' => 'Everything is OK'
        ], 200);
    }

    //get beneficiary payment list
    public function getBeneficiariesBySchoolOlddz(Request $request)
    {
        $userUuid = $request->header('UUID');
        if (empty($userUuid)) {
            return response()->json(['message' => 'Please provide UUID'], 400);
        }

        //Get user from users table
        $user = DB::table('users')->where('uuid', $userUuid)->first();
        if (!$user) {
            return response()->json(['message' => 'This user does not exist'], 404);
        }

        //Get assigned school (string + ID) from user details table
        $userDetails = DB::table('sa_app_user_details')
            ->where('uuid', $userUuid)
            ->first();

        if (!$userDetails || empty($userDetails->school_assigned_id)) {
            return response()->json(['message' => 'User has no assigned school.'], 403);
        }

        $schoolCode = $request->query('school');
        if (empty($schoolCode)) {
            return response()->json(['message' => 'Please provide a school code'], 400);
        }

        //Validate access — does the user match the requested school?
        $assignedSchoolCode = explode(' ', $userDetails->school_assigned_id)[0];
        if ($assignedSchoolCode !== $schoolCode) {
            return response()->json(['message' => 'You are forbidden to download this payment list'], 403);
        }

        //Validate that school code exists in DB
        $schoolInfo = DB::table('school_information')->where('id', $schoolCode)->first();
        if (!$schoolInfo) {
            return response()->json(['message' => 'Invalid school code provided'], 404);
        }
        $schoolId = $schoolInfo->id;

        //Create Payment Batch ID
        $paymentPeriod = now()->format('Y-m');
        $randomNumber = strtoupper(bin2hex(random_bytes(6))); // 12-character random string
        $schoolNameHyphenated = str_replace(' ', '-', $userDetails->school_assigned_string); // Replace spaces with hyphens
        $paymentBatchId = $schoolNameHyphenated . '-' . $paymentPeriod . '-' . $randomNumber;

        //Get beneficiaries from batch (filtered by school and payment status)
        $beneficiaries = DB::table('sa_app_beneficiary_list_4')
            ->where('school_id', $schoolId)
            ->where('payment_status_id', 1)
            ->select(DB::raw("
                COALESCE(transaction_id, 'N/A') AS TransactionID,
                COALESCE(beneficiary_no, 'N/A') AS BeneficiaryNumber,
                COALESCE(decrypt(first_name), 'N/A') AS FirstName,
                COALESCE(decrypt(last_name), 'N/A') AS LastName,
                COALESCE(school_name, 'N/A') AS School,
                COALESCE(school_district, 'N/A') AS SchoolDistrict,
                COALESCE(school_province, 'N/A') AS Province,
                COALESCE(cwac_name, 'N/A') AS SchoolCwac,
                COALESCE(mobile_phone_parent_guardian, 'N/A') AS GuardianPhoneNumber,
                COALESCE(hhh_nrc_number, 'N/A') AS GuardianNRC,
                COALESCE(decrypt(hhh_fname), 'N/A') AS GuardianFirstName,
                COALESCE(decrypt(hhh_lname), 'N/A') AS GuardianLastName,
                COALESCE(grant_amount, 0) AS EducationGrantAmount,
                COALESCE(transaction_time_initiated, 'N/A') AS TransactionInitiatedAt
            "))
            ->get();

            


        // Log user activity
        DB::table('sa_user_activity_logs')->insert([
            'user_uuid'    => $userUuid,
            'activity_type'=> 'download beneficiary list',
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->header('User-Agent'),
            'created_at'   => now(),
        ]);

        //Log the download attempt
        DB::table('sa_app_payment_list_downloads_log')->insert([
            'downloaded_by' => $userUuid,
            'start_timestamp' => now(),
            'end_timestamp' => now(),
            'download_successful' => !$beneficiaries->isEmpty(),
            'school_downloaded' => $userDetails->school_assigned_id,
            'payment_batch_id' => $paymentBatchId
        ]);

        //Create the payment batch record
        DB::table('sa_app_payment_batches')->insert([
            'PaymentBatchID' => $paymentBatchId,
            'SchoolID' => $schoolId,
            'UserUUID' => $userUuid,
            'DateGenerated' => now(),
            'Status' => 'Pending',
            'NumberOfStudents' => $beneficiaries->count(),
            'AmountDisbursed' => 0,
            'AmountReturned' => 0
        ]);

        if ($beneficiaries->isEmpty()) {
            return response()->json(['message' => 'No beneficiaries found for the provided school.'], 404);
        }

        //NEW: Pull Head Teacher (designation_id=1) & Guidance Teacher (designation_id=2)
        // Use school_assigned_id from sch_acc_app_user_details to query school_contactpersons.school_id
        $schoolAssignedId = $userDetails->school_assigned_id ?? null;

        $headTeacher = null;
        $guidanceTeacher = null;

        if (!empty($schoolAssignedId)) {
            // Get both in one query, then map by designation_id
            $contacts = DB::table('school_contactpersons')
                ->where('school_id', $schoolAssignedId)
                ->whereIn('designation_id', [1, 2])
                ->select('designation_id', 'full_names')
                ->get()
                ->keyBy('designation_id');

            $headTeacher = optional($contacts->get(1))->full_names ?? 'N/A';
            $guidanceTeacher = optional($contacts->get(2))->full_names ?? 'N/A';

        }

        $totalBeneficiaries = $beneficiaries->count();
        //Return JSON response (now includes head_teacher & guidance_teacher)
        return response()->json([
            'payment_batch_id' => $paymentBatchId ?? 'N/A',
            'school' => $userDetails->school_assigned_string ?? 'N/A',
            'head_teacher' => $headTeacher ?? 'N/A',
            'guidance_teacher' => $guidanceTeacher ?? 'N/A',
            'total_beneficiaries' => $totalBeneficiaries,
            'payment_list' => $beneficiaries
        ]);

    }

    public function getBeneficiariesBySchool(Request $request)
    {

        // Validate UUID
        $userUuid = $request->header('UUID');
        if (empty($userUuid)) {
            return response()->json(['message' => 'Please provide UUID'], 400);
        }

        $user = DB::table('users')->where('uuid', $userUuid)->first();
        if (!$user) {
            return response()->json(['message' => 'This user does not exist'], 404);
        }

        // Validate School Access
        $userDetails = DB::table('sa_app_user_details')
            ->where('uuid', $userUuid)
            ->first();

        if (!$userDetails || empty($userDetails->school_assigned_id)) {
            return response()->json(['message' => 'User has no assigned school.'], 403);
        }

        $schoolCode = $request->query('school');
        if (empty($schoolCode)) {
            return response()->json(['message' => 'Please provide a school code'], 400);
        }

        $assignedSchoolCode = explode(' ', $userDetails->school_assigned_id)[0];
        if ($assignedSchoolCode !== $schoolCode) {
            return response()->json(['message' => 'You are forbidden to download this payment list'], 403);
        }

        $schoolInfo = DB::table('school_information')
            ->where('id', $schoolCode)
            ->first();

        if (!$schoolInfo) {
            return response()->json(['message' => 'Invalid school code provided'], 404);
        }

        $schoolId = $schoolInfo->id;

        // Generate Payment Batch ID
        $paymentPeriod = now()->format('Y-m');
        $randomNumber = strtoupper(bin2hex(random_bytes(6)));
        $schoolNameHyphenated = str_replace(' ', '-', $userDetails->school_assigned_string);
        $paymentBatchId = $schoolNameHyphenated . '-' . $paymentPeriod . '-' . $randomNumber;

        // Fetch Beneficiaries
        $beneficiaries = DB::table('sa_app_beneficiary_list_4')
            ->where('school_id', $schoolId)
            ->where('payment_status_id', 1)
            ->select(DB::raw("
                COALESCE(transaction_id, 'N/A') AS TransactionID,
                COALESCE(beneficiary_no, 'N/A') AS BeneficiaryNumber,
                COALESCE(decrypt(first_name), 'N/A') AS FirstName,
                COALESCE(decrypt(last_name), 'N/A') AS LastName,
                COALESCE(school_name, 'N/A') AS School,
                COALESCE(school_district, 'N/A') AS SchoolDistrict,
                COALESCE(school_province, 'N/A') AS Province,
                COALESCE(cwac_name, 'N/A') AS SchoolCwac,
                COALESCE(mobile_phone_parent_guardian, 'N/A') AS GuardianPhoneNumber,
                COALESCE(hhh_nrc_number, 'N/A') AS GuardianNRC,
                COALESCE(decrypt(hhh_fname), 'N/A') AS GuardianFirstName,
                COALESCE(decrypt(hhh_lname), 'N/A') AS GuardianLastName,
                COALESCE(grant_amount, 0) AS EducationGrantAmount,
                COALESCE(transaction_time_initiated, 'N/A') AS TransactionInitiatedAt
            "))
            ->get();

        if ($beneficiaries->isEmpty()) {
            return response()->json(['message' => 'No beneficiaries found for the provided school.'], 404);
        }

        // Logging
        DB::table('sa_user_activity_logs')->insert([
            'user_uuid'    => $userUuid,
            'activity_type'=> 'download beneficiary list',
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->header('User-Agent'),
            'created_at'   => now(),
        ]);

        DB::table('sa_app_payment_list_downloads_log')->insert([
            'downloaded_by' => $userUuid,
            'start_timestamp' => now(),
            'end_timestamp' => now(),
            'download_successful' => !$beneficiaries->isEmpty(),
            'school_downloaded' => $userDetails->school_assigned_id,
            'payment_batch_id' => $paymentBatchId
        ]);

        DB::table('sa_app_payment_batches')->insert([
            'PaymentBatchID' => $paymentBatchId,
            'SchoolID' => $schoolId,
            'UserUUID' => $userUuid,
            'DateGenerated' => now(),
            'Status' => 'Pending',
            'NumberOfStudents' => $beneficiaries->count(),
            'AmountDisbursed' => 0,
            'AmountReturned' => 0
        ]);

        // Head Teacher & Guidance Teacher
        $contacts = DB::table('school_contactpersons')
            ->where('school_id', $userDetails->school_assigned_id)
            ->whereIn('designation_id', [1, 2])
            ->select('designation_id', 'full_names')
            ->get()
            ->keyBy('designation_id');

        $headTeacher = optional($contacts->get(1))->full_names ?? 'N/A';
        $guidanceTeacher = optional($contacts->get(2))->full_names ?? 'N/A';

        // Generate PDF Using TCPDF
        $fileName = $paymentBatchId . '.pdf';
        $filePath = storage_path('app/public/payment_lists/' . $fileName);

        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->AddPage();

        $html = '<h3>Payment Batch ID: '.$paymentBatchId.'</h3>
        <p><strong>School:</strong> '.$userDetails->school_assigned_string.'</p>
        <table border="1" cellpadding="3">
            <tr>
                <th width="15">#</th>
                <th width="50">Ben. No</th>
                <th width="70">Full Name</th>
                <th width="35">Grant</th>
                <th width="70">Guardian</th>
            </tr>';

        $counter = 1;
        $totalAmount = 0;

        foreach ($beneficiaries as $row) {

            $html .= '<tr>
                <td align="center">'.$counter.'</td>
                <td align="center">'.$row->BeneficiaryNumber.'</td>
                <td>'.$row->FirstName.' '.$row->LastName.'</td>
                <td align="right">'.number_format($row->EducationGrantAmount, 2).'</td>
                <td>'.$row->GuardianFirstName.' '.$row->GuardianLastName.'</td>
            </tr>';

            $totalAmount += $row->EducationGrantAmount;
            $counter++;
        }

        $html .= '<tr>
            <td colspan="3" align="right"><strong>TOTAL</strong></td>
            <td align="right"><strong>'.number_format($totalAmount, 2).'</strong></td>
            <td></td>
        </tr></table>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output($filePath, 'F');

        // Signed URL (10 Minutes)
        $downloadUrl = URL::temporarySignedRoute(
            'download.payment.list',
            now()->addMinutes(10),
            ['filename' => $fileName]
        );

        // Final JSON
        return response()->json([
            'payment_batch_id' => $paymentBatchId,
            'school' => $userDetails->school_assigned_string,
            'head_teacher' => $headTeacher,
            'guidance_teacher' => $guidanceTeacher,
            'total_beneficiaries' => $beneficiaries->count(),
            'pdf_download_link' => $downloadUrl,
            'payment_list' => $beneficiaries
        ]);
    }

    public function downloadPaymentList(Request $request, $filename)
    {
        $path = storage_path('app/public/payment_lists/' . $filename);

        if (!file_exists($path)) {
            abort(404);
        }

        return response()->download($path);
    }

    //post transaction status
    public function sendPaymentStatuses(Request $request)
    {
        try {
            // === 1) Validate Bearer Token ===
            $authorizationHeader = $request->header('Authorization');

            if (empty($authorizationHeader) || !preg_match('/Bearer\s+(.*)$/i', $authorizationHeader, $matches)) {
                return response()->json(['Message' => 'Bearer token is required'], 401);
            }

            $token = trim($matches[1]);

            $tokenRecord = DB::table('sa_app_token_management')
                ->where('token', $token)
                ->first();

            if (!$tokenRecord) {
                return response()->json(['Message' => 'Invalid token. Please log in again.'], 403);
            }

            if (!empty($tokenRecord->expires_at) && now()->greaterThan($tokenRecord->expires_at)) {
                return response()->json(['Message' => 'Token has expired. Please log in again.'], 403);
            }

            $userUuid = $tokenRecord->user_uuid;

            // === 2) Check for district param ===
            $district = $request->query('district');
            if (empty($district)) {
                return response()->json(['Message' => 'The district parameter is required'], 400);
            }

            $data = $request->json()->all();
            $errors = [];
            $successCount = 0;

            $paymentStatusMap = [
                'PAID'    => 3,
                'FAILED'  => 4,
                'DEFAULT' => 6,
            ];

            foreach ($data as $item) {
                $item['PaymentStatus'] = strtoupper($item['PaymentStatus']);

                

                $validator = Validator::make($item, [
                    'TransactionId'          => 'required|string',
                    'BeneficiaryNo'          => 'required|string',
                    'PaymentStatus'          => 'required|string',
                    'ImageIDs'               => 'required|array',
                    'DateReceived'           => 'required|date',
                    'GpsLatitude'            => 'required', // Removed |required to test
                    'GpsLongitude'           => 'required',
                    'GpsAltitude'            => 'required',
                    'GpsTimestamp'           => 'required|date',
                    'SchoolAccountantDetails'=> 'required|string',
                ]);

                if ($validator->fails()) {
                    $errors[] = [
                        'TransactionId' => $item['TransactionId'] ?? null,
                        'errors' => $validator->errors()->all()
                    ];
                    continue;
                }

                $statusId = $paymentStatusMap[$item['PaymentStatus']] ?? $paymentStatusMap['DEFAULT'];

                try {
                    DB::beginTransaction();

                    // Check if record already exists
                    $existing = DB::table('beneficiary_transaction_status')
                        ->where('transaction_id', $item['TransactionId'])
                        ->first();

                    // test logging
                    Log::info($item['TransactionId'] . ": Lat: ".$item['GpsLatitude']." Long: ".$item['GpsLongitude']." Alt: ". $item['GpsAltitude']);

                    if ($existing) {
                        // Update existing record
                        DB::table('beneficiary_transaction_status')
                            ->where('transaction_id', $item['TransactionId'])
                            ->update([
                                'beneficiary_no' => $item['BeneficiaryNo'],
                                'payment_status' => $item['PaymentStatus'],
                                'payment_status_id' => $statusId,
                                'images' => implode(',', $item['ImageIDs']),
                                'date_received' => $item['DateReceived'],
                                'gps_latitude' => $item['GpsLatitude'],
                                'gps_longitude' => $item['GpsLongitude'],
                                'gps_altitude' => $item['GpsAltitude'],
                                'gps_timestamp' => (new \DateTime($item['GpsTimestamp']))->format('Y-m-d H:i:s'),
                                'school_accountant_details' => $item['SchoolAccountantDetails'],
                                'updated_at' => now(),
                            ]);
                    } else {
                        // Insert new record
                        DB::table('beneficiary_transaction_status')->insert([
                            'transaction_id' => $item['TransactionId'],
                            'beneficiary_no' => $item['BeneficiaryNo'],
                            'payment_status' => $item['PaymentStatus'],
                            'payment_status_id' => $statusId,
                            'images' => implode(',', $item['ImageIDs']),
                            'date_received' => $item['DateReceived'],
                            'gps_latitude' => $item['GpsLatitude'],
                            'gps_longitude' => $item['GpsLongitude'],
                            'gps_altitude' => $item['GpsAltitude'],
                            'gps_timestamp' => (new \DateTime($item['GpsTimestamp']))->format('Y-m-d H:i:s'),
                            'school_accountant_details' => $item['SchoolAccountantDetails'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    // Log user activity
                    DB::table('sa_user_activity_logs')->insert([
                        'user_uuid'     => $userUuid,
                        'activity_type' => 'posted transaction status',
                        'ip_address'    => $request->ip(),
                        'user_agent'    => $request->header('User-Agent'),
                        'created_at'    => now(),
                    ]);

                    // Update related table
                    DB::table('sa_app_beneficiary_list')
                        ->where('transaction_id', $item['TransactionId'])
                        ->update([
                            'payment_status' => $item['PaymentStatus'],
                            'payment_status_id' => $statusId,
                            'updated_at' => now(),
                        ]);

                    DB::commit();
                    $successCount++;

                } catch (\Exception $e) {
                    DB::rollBack();
                    $errors[] = [
                        'TransactionId' => $item['TransactionId'],
                        'error' => $e->getMessage()
                    ];
                }
            }

            if (!empty($errors)) {
                Log::info('Some records failed to process. Details:', [
                    'Errors' => $errors,
                    'SuccessCount' => $successCount
                ]);
                return response()->json([
                    'Message' => 'Some records failed to process',
                    'Errors' => $errors,
                    'SuccessCount' => $successCount
                ], 422);
            }

            return response()->json([
                'Message' => $successCount . ' records received successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Payment status submission failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'Message' => 'Server error while processing request',
                'Error' => $e->getMessage()
            ], 500);
        }
    }

    //beneficiary images
    public function beneficiaryImagesV1(Request $request)
    {
        try {
            // === 1) Bearer token validation ===
            $authorizationHeader = $request->header('Authorization');

            if (empty($authorizationHeader) || !preg_match('/Bearer\s+(.*)$/i', $authorizationHeader, $matches)) {
                return response()->json(['error' => 'Bearer token is required'], 401);
            }

            $token = trim($matches[1]);

            $tokenRecord = DB::table('sa_app_token_management')
                ->where('token', $token)
                ->first();

            if (!$tokenRecord) {
                return response()->json(['error' => 'Invalid token. Please log in again.'], 403);
            }

            if (!empty($tokenRecord->expires_at) && now()->greaterThan($tokenRecord->expires_at)) {
                return response()->json(['error' => 'Token has expired. Please log in again.'], 403);
            }

            $userUuid = $tokenRecord->user_uuid;

            // === 2) Check for district param ===
            $district = $request->query('district');
            if (empty($district)) {
                return response()->json(['error' => 'The district parameter is required'], 400);
            }

            $data = $request->json()->all();
            if (!is_array($data) || empty($data)) {
                return response()->json(['error' => 'Request body must contain image records'], 400);
            }

            // === 3) Initialize counters ===
            $insertedRecordsCount = 0;
            $errors = [];

            foreach ($data as $index => $item) {
                $validator = Validator::make(
                    $item,
                    [
                        'BeneficiaryNumber' => 'required|string',
                        'ImageId'           => 'required|string',
                        'ImageUrl'          => 'required|string',
                        'ImageCategory'     => 'required|integer|in:1,2,3',
                        'ImageDescription'  => 'nullable|string',
                    ],
                    [
                        'ImageCategory.in' => 'ImageCategory must be one of: 1 (beneficiary_image), 2 (Guardian_image), 3 (other).',
                    ]
                );

                if ($validator->fails()) {
                    $errors[$index] = $validator->errors()->all();
                    continue;
                }

                // Verify ImageUrl is valid base64
                $decoded = base64_decode($item['ImageUrl'], true);
                if ($decoded === false) {
                    $errors[$index][] = 'ImageUrl must be a valid base64-encoded string.';
                    continue;
                }

                // Calculate image size in KB
                $imageSize = strlen($decoded) / 1024;

                try {
                    DB::table('sa_app_beneficiary_images')->insert([
                        'beneficiary_number' => $item['BeneficiaryNumber'],
                        'image_id'           => $item['ImageId'],
                        'image_category'     => (int) $item['ImageCategory'],
                        'image_description'  => $item['ImageDescription'] ?? null,
                        'image_url'          => $item['ImageUrl'],
                        'image_size_kb'      => $imageSize,
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);

                    $insertedRecordsCount++;
                } catch (\Throwable $e) {
                    $errors[$index][] = 'Database insert failed: ' . $e->getMessage();
                }
            }

            // === 4) Response ===
            if (!empty($errors)) {
                return response()->json([
                    'message'          => 'Some records could not be saved due to validation errors',
                    'inserted_records' => $insertedRecordsCount,
                    'errors'           => $errors
                ], 422);
            }

            // === 5) Log user activity ===
            DB::table('sa_user_activity_logs')->insert([
                'user_uuid'    => $userUuid,
                'activity_type'=> 'uploaded beneficiary images',
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->header('User-Agent'),
                'created_at'   => now(),
            ]);

            // === 6) Success ===
            return response()->json([
                'message' => $insertedRecordsCount . ' image records sent successfully'
            ], 201);

        } catch (\Exception $e) {
            Log::error('Beneficiary image upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Server error while processing request',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function beneficiaryImages(Request $request)
    {
        try {
            // === 1) Bearer token validation ===
            $authorizationHeader = $request->header('Authorization');

            if (empty($authorizationHeader) || !preg_match('/Bearer\s+(.*)$/i', $authorizationHeader, $matches)) {
                return response()->json(['error' => 'Bearer token is required'], 401);
            }

            $token = trim($matches[1]);

            $tokenRecord = DB::table('sa_app_token_management')
                ->where('token', $token)
                ->first();

            if (!$tokenRecord) {
                return response()->json(['error' => 'Invalid token. Please log in again.'], 403);
            }

            if (!empty($tokenRecord->expires_at) && now()->greaterThan($tokenRecord->expires_at)) {
                return response()->json(['error' => 'Token has expired. Please log in again.'], 403);
            }

            $userUuid = $tokenRecord->user_uuid;

            // === 2) Check for district param ===
            $district = $request->query('district');
            if (empty($district)) {
                return response()->json(['error' => 'The district parameter is required'], 400);
            }

            $data = $request->json()->all();
            if (!is_array($data) || empty($data)) {
                return response()->json(['error' => 'Request body must contain image records'], 400);
            }

            // === 3) Initialize counters ===
            $insertedRecordsCount = 0;
            $errors = [];

            foreach ($data as $index => $item) {
                $validator = Validator::make(
                    $item,
                    [
                        'BeneficiaryNumber' => 'required|string',
                        'ImageId'           => 'required|string',
                        'ImageUrl'          => 'required|string',
                        'ImageCategory'     => 'required|integer|in:1,2,3',
                        'ImageDescription'  => 'nullable|string',
                    ],
                    [
                        'ImageCategory.in' => 'ImageCategory must be one of: 1 (beneficiary_image), 2 (Guardian_image), 3 (other).',
                    ]
                );

                if ($validator->fails()) {
                    $errors[$index] = $validator->errors()->all();
                    continue;
                }

                // Verify ImageUrl is valid base64
                $decoded = base64_decode($item['ImageUrl'], true);
                if ($decoded === false) {
                    $errors[$index][] = 'ImageUrl must be a valid base64-encoded string.';
                    continue;
                }

                // Calculate image size in KB
                $imageSize = strlen($decoded) / 1024;

                try {
                    DB::table('sa_app_beneficiary_images')->insert([
                        'beneficiary_number' => $item['BeneficiaryNumber'],
                        'image_id'           => $item['ImageId'],
                        'image_category'     => (int) $item['ImageCategory'],
                        'image_description'  => $item['ImageDescription'] ?? null,
                        'image_url'          => $item['ImageUrl'],
                        'image_size_kb'      => $imageSize,
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);

                    $insertedRecordsCount++;
                } catch (\Throwable $e) {
                    $errors[$index][] = 'Database insert failed: ' . $e->getMessage();
                }
            }

            // === 4) Response ===
            if (!empty($errors)) {
                return response()->json([
                    'message'          => 'Some records could not be saved due to validation errors',
                    'inserted_records' => $insertedRecordsCount,
                    'errors'           => $errors
                ], 422);
            }

            // === 5) Log user activity ===
            DB::table('sa_user_activity_logs')->insert([
                'user_uuid'    => $userUuid,
                'activity_type'=> 'uploaded beneficiary images',
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->header('User-Agent'),
                'created_at'   => now(),
            ]);

            // === 6) Success ===
            return response()->json([
                'message' => $insertedRecordsCount . ' image records sent successfully'
            ], 201);

        } catch (\Exception $e) {
            Log::error('Beneficiary image upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Server error while processing request',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    //submit deposit slip
    public function submitDepositSlip(Request $request)
    {
        try {
            // === 1) Validate Bearer Token ===
            $authorizationHeader = $request->header('Authorization');

            if (empty($authorizationHeader) || !preg_match('/Bearer\s+(.*)$/i', $authorizationHeader, $matches)) {
                return response()->json(['message' => 'Bearer token is required'], 401);
            }

            $token = trim($matches[1]);

            $tokenRecord = DB::table('sa_app_token_management')
                ->where('token', $token)
                ->first();

            if (!$tokenRecord) {
                return response()->json(['message' => 'Invalid token. Please log in again.'], 403);
            }

            if (!empty($tokenRecord->expires_at) && now()->greaterThan($tokenRecord->expires_at)) {
                return response()->json(['message' => 'Token has expired. Please log in again.'], 403);
            }

            $userUuid = $tokenRecord->user_uuid;

            // === 2) Validate Payment Batch ID ===
            $paymentBatchId = $request->input('payment_batch_id');
            if (empty($paymentBatchId)) {
                return response()->json(['message' => 'Payment batch ID is required'], 400);
            }

            $batchExists = DB::table('sa_app_payment_batches')
                ->where('PaymentBatchID', $paymentBatchId)
                ->exists();

            if (!$batchExists) {
                return response()->json(['message' => 'Payment batch ID does not exist'], 404);
            }

            // === 3) Validate Request Body ===
            $data = $request->validate([
                'deposit_slip_image' => 'required|string',
                'amount_deposited'   => 'required|numeric',
                'comments'           => 'nullable|string'
            ]);

            // === 4) Save Deposit Slip Submission ===
            DB::beginTransaction();

            DB::table('sa_app_deposit_slip_submissions')->insert([
                'PaymentBatchID'   => $paymentBatchId,
                'UserUUID'         => $userUuid,
                'DepositSlipImage' => $data['deposit_slip_image'],
                'AmountDeposited'  => $data['amount_deposited'],
                'Comments'         => $data['comments'] ?? null,
                'DateSubmitted'    => now(),
            ]);

            // === 5) Log User Activity ===
            DB::table('sa_user_activity_logs')->insert([
                'user_uuid'     => $userUuid,
                'activity_type' => 'deposit slip upload',
                'ip_address'    => $request->ip(),
                'user_agent'    => $request->header('User-Agent'),
                'created_at'    => now(),
            ]);

            DB::commit();

            return response()->json(['message' => 'Deposit slip submitted successfully'], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Input validation error
            return response()->json([
                'message' => 'Validation error',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // Rollback in case of failure
            DB::rollBack();

            Log::error('Deposit slip submission failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Server error while submitting deposit slip',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // MNE
    public function postMNEData(Request $request)
    {
        Log::info("---------------------------------------------------------------------------------------------------");
        Log::info("-----------------------------Mobile M&E Data Processing --------------------------------------");
        Log::info("---------------------------------------------------------------------------------------------------");

        $rawData = $request->all();
        $processResponse = [];
        if (!isset($rawData['forms']) || !is_array($rawData['forms']) || empty($rawData['forms'])) {
            Log::error("Invalid data structure: 'forms' array is missing or empty");
            $processResponse = [
                'message' => 'Invalid data structure: forms array is required',
                'code' => 422,
            ];
        }

        $data = $rawData['forms'][0]; // Get the first form
        Log::info("Sync enrollment result", ['data' => $data]);

        // Validate the incoming data
        $validator = Validator::make($data, [
            'tracking_no' => 'required|string|max:50',
            'data_entry_collection_tool' => 'required|string|max:100',
            'entry_year' => 'required|string|max:4',
            'entry_term' => 'required|string|max:20',
            'date_started' => 'required|date',
            'date_completed' => 'required|date',
            'tab1_institutional_info' => 'required|array',
            'tab2.background_info_grades' => 'required|array',
            'tab2.background_info_totals' => 'required|array',
            'tab2.kgs_enrollment_details' => 'required|array',
            'tab2.kgs_enrollment_totals' => 'required|array',
            'tab2.boarding_facility_grades' => 'required|array',
            'tab2.boarding_facility_totals' => 'required|array',
            'tab3.mne_termly_avg_att' => 'required|array',
            'tab3.mne_dropouts' => 'required|array',
            'tab3.mne_dropouts_totals' => 'required|array',
            'tab4.mne_grade_termly_performance' => 'required|array',
            'tab4.mne_average_termly_performance' => 'required|array',
            'tab5.mne_education_quality' => 'required|array',
            'tab5.mne_education_quality.responses' => 'required|array',
            'tab6.mne_payment_checklist' => 'required|array',
            'tab6.mne_payment_checklist.responses' => 'required|array',
            'tab7.mne_progression_grade12_completion' => 'required|array',
        ]);

        if ($validator->fails()) {
            Log::error("Validation failed", ['errors' => $validator->errors()]);
            $processResponse = [
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
                'code' => 422,
            ];
        }

        try {
            // Start a database transaction
            DB::beginTransaction();

            // Check if tracking_no already exists
            if (DB::table('mne_form_info_staging')->where('tracking_no', $data['tracking_no'])->exists()) {
                $processResponse = [
                    'message' => 'Form with this tracking number has already been submitted',
                    'tracking_no' => $data['tracking_no'],
                    'code' => 409,
                ];
            }

            // Insert into mne_form_info_staging and check success
            $inserted = DB::table('mne_form_info_staging')->insert([
                'tracking_no' => $data['tracking_no'],
                'data_entry_collection_tool' => $data['data_entry_collection_tool'],
                'entry_year' => $data['entry_year'],
                'entry_term' => $data['entry_term'],
                'date_started' => $data['date_started'],
                'date_completed' => $data['date_completed'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Proceed only if insert was successful
            if (!$inserted) {
                DB::rollBack();
                Log::warning("Failed to insert into mne_form_info_staging", ['tracking_no' => $data['tracking_no']]);
                return response()->json([
                    'message' => 'Failed to insert form data',
                    'tracking_no' => $data['tracking_no'],
                ], 500);
            }

            // Insert into mne_tab1_form_data_staging (one-to-one)
            DB::table('mne_tab1_form_data_staging')->insert([
                'reference_no' => $data['tab1_institutional_info']['reference_no'],
                'form_name' => $data['tab1_institutional_info']['form_name'],
                'province' => $data['tab1_institutional_info']['province'],
                'district' => $data['tab1_institutional_info']['district'],
                'school_id' => $data['tab1_institutional_info']['school_id'],
                'school_head_teacher' => $data['tab1_institutional_info']['school_head_teacher'],
                'gender_head_teacher' => $data['tab1_institutional_info']['gender_head_teacher'],
                'rural_urban' => $data['tab1_institutional_info']['rural_urban'],
                'running_agency' => $data['tab1_institutional_info']['running_agency'],
                'term' => $data['tab1_institutional_info']['term'],
                'landline_head_teacher' => $data['tab1_institutional_info']['landline_head_teacher'],
                'mobile_head_teacher' => $data['tab1_institutional_info']['mobile_head_teacher'],
                'email_head_teacher' => $data['tab1_institutional_info']['email_head_teacher'],
                'date_of_data_collection' => $data['tab1_institutional_info']['date_of_data_collection'],
                'is_GEWEL_complain_available' => $data['tab1_institutional_info']['is_GEWEL_complain_available'],
                'is_GEWEL_focalpoint_person_available' => $data['tab1_institutional_info']['is_GEWEL_focalpoint_person_available'],
                'comments' => $data['tab1_institutional_info']['comments'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert into mne_tab2_1_form_data_staging (background_info_grades)
            foreach ($data['tab2']['background_info_grades'] as $grade) {
                DB::table('mne_tab2_1_form_data_staging')->insert([
                    'reference_no' => $grade['reference_no'],
                    'school_id' => $grade['school_id'],
                    'grade' => $grade['grade'],
                    'form_name' => $grade['form_name'],
                    'total_kgs_girls_with_disability' => $grade['total_kgs_girls_with_disability'],
                    'total_kgs_girls_without_disability' => $grade['total_kgs_girls_without_disability'],
                    'total_kgs_girls' => $grade['total_kgs_girls'],
                    'total_non_kgs_girls_with_disability' => $grade['total_non_kgs_girls_with_disability'],
                    'total_non_kgs_girls_without_disability' => $grade['total_non_kgs_girls_without_disability'],
                    'total_non_kgs_girls' => $grade['total_non_kgs_girls'],
                    'total_boys_with_disability' => $grade['total_boys_with_disability'],
                    'total_boys_without_disability' => $grade['total_boys_without_disability'],
                    'total_boys' => $grade['total_boys'],
                    'total_boys_and_girls' => $grade['total_boys_and_girls'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Insert into mne_tab2_1_form_data_totals_staging
            DB::table('mne_tab2_1_form_data_totals_staging')->insert([
                'reference_no' => $data['tab2']['background_info_totals']['reference_no'],
                'school_id' => $data['tab2']['background_info_totals']['school_id'],
                'form_name' => $data['tab2']['background_info_totals']['form_name'],
                'total_kgs_girls_with_disability' => $data['tab2']['background_info_totals']['total_kgs_girls_with_disability'],
                'total_kgs_girls_without_disability' => $data['tab2']['background_info_totals']['total_kgs_girls_without_disability'],
                'total_kgs_girls' => $data['tab2']['background_info_totals']['total_kgs_girls'],
                'total_non_kgs_girls_with_disability' => $data['tab2']['background_info_totals']['total_non_kgs_girls_with_disability'],
                'total_non_kgs_girls_without_disability' => $data['tab2']['background_info_totals']['total_non_kgs_girls_without_disability'],
                'total_non_kgs_girls' => $data['tab2']['background_info_totals']['total_non_kgs_girls'],
                'total_boys_with_disability' => $data['tab2']['background_info_totals']['total_boys_with_disability'],
                'total_boys_without_disability' => $data['tab2']['background_info_totals']['total_boys_without_disability'],
                'total_boys' => $data['tab2']['background_info_totals']['total_boys'],
                'total_boys_and_girls' => $data['tab2']['background_info_totals']['total_boys_and_girls'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert into mne_tab2_2_form_data_staging (kgs_enrollment_details)
            foreach ($data['tab2']['kgs_enrollment_details'] as $detail) {
                DB::table('mne_tab2_2_form_data_staging')->insert([
                    'reference_no' => $detail['reference_no'],
                    'grade' => $detail['grade'],
                    'age_group' => $detail['age_group'],
                    'form_name' => $detail['form_name'],
                    'student_count' => $detail['student_count'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Insert into mne_tab2_2_form_data_totals_staging
            foreach ($data['tab2']['kgs_enrollment_totals'] as $total) {
                DB::table('mne_tab2_2_form_data_totals_staging')->insert([
                    'reference_no' => $total['reference_no'],
                    'category' => $total['category'],
                    'name_of_category' => $total['name_of_category'],
                    'form_name' => $total['form_name'],
                    'student_count' => $total['student_count'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Insert into mne_tab2_3_form_data_staging (boarding_facility_grades)
            foreach ($data['tab2']['boarding_facility_grades'] as $grade) {
                DB::table('mne_tab2_3_form_data_staging')->insert([
                    'reference_no' => $grade['reference_no'],
                    'school_id' => $grade['school_id'],
                    'grade' => $grade['grade'],
                    'form_name' => $grade['form_name'],
                    'total_kgs_girls_enrolled' => $grade['total_kgs_girls_enrolled'],
                    'total_kgs_girls_paid_for' => $grade['total_kgs_girls_paid_for'],
                    'total_girls_in_formal_boarding' => $grade['total_girls_in_formal_boarding'],
                    'total_kgs_girls_in_weekly_boarding_managed_by_school' => $grade['total_kgs_girls_in_weekly_boarding_managed_by_school'],
                    'total_kgs_girls_in_private_weekly_boarding_facilities' => $grade['total_kgs_girls_in_private_weekly_boarding_facilities'],
                    'total_kgs_girls_who_are_day_scholars' => $grade['total_kgs_girls_who_are_day_scholars'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Insert into mne_tab2_3_form_data_totals_staging
            DB::table('mne_tab2_3_form_data_totals_staging')->insert([
                'reference_no' => $data['tab2']['boarding_facility_totals']['reference_no'],
                'school_id' => $data['tab2']['boarding_facility_totals']['school_id'],
                'form_name' => $data['tab2']['boarding_facility_totals']['form_name'],
                'total_kgs_girls_enrolled' => $data['tab2']['boarding_facility_totals']['total_kgs_girls_enrolled'],
                'total_kgs_girls_paid_for' => $data['tab2']['boarding_facility_totals']['total_kgs_girls_paid_for'],
                'total_girls_in_formal_boarding' => $data['tab2']['boarding_facility_totals']['total_girls_in_formal_boarding'],
                'total_kgs_girls_in_weekly_boarding_managed_by_school' => $data['tab2']['boarding_facility_totals']['total_kgs_girls_in_weekly_boarding_managed_by_school'],
                'total_kgs_girls_in_private_weekly_boarding_facilities' => $data['tab2']['boarding_facility_totals']['total_kgs_girls_in_private_weekly_boarding_facilities'],
                'total_kgs_girls_who_are_day_scholars' => $data['tab2']['boarding_facility_totals']['total_kgs_girls_who_are_day_scholars'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert into mne_tab3_1_form_data_staging (mne_termly_avg_att)
            foreach ($data['tab3']['mne_termly_avg_att'] as $att) {
                DB::table('mne_tab3_1_form_data_staging')->insert([
                    'reference_no' => $att['reference_no'],
                    'school_id' => $att['school_id'],
                    'grade' => $att['grade'],
                    'form_name' => $att['form_name'],
                    'termly_avg_attendance_for_kgs_girls' => $att['termly_avg_attendance_for_kgs_girls'],
                    'termly_avg_attendance_for_non_kgs_girls' => $att['termly_avg_attendance_for_non_kgs_girls'],
                    'avg_total' => $att['avg_total'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Insert into mne_tab3_2_form_data_staging (mne_dropouts)
            foreach ($data['tab3']['mne_dropouts'] as $dropout) {
                DB::table('mne_tab3_2_form_data_staging')->insert([
                    'reference_no' => $dropout['reference_no'],
                    'school_id' => $dropout['school_id'],
                    'form_name' => $dropout['form_name'],
                    'grade' => $dropout['grade'],
                    'category' => $dropout['category'],
                    'reason' => $dropout['reason'],
                    'value' => $dropout['value'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Insert into mne_tab3_2_form_data_totals_staging
            foreach ($data['tab3']['mne_dropouts_totals'] as $total) {
                DB::table('mne_tab3_2_form_data_totals_staging')->insert([
                    'reference_no' => $total['reference_no'],
                    'school_id' => $total['school_id'],
                    'form_name' => $total['form_name'],
                    'reason' => $total['reason'],
                    'total_kgs_girls' => $total['total_kgs_girls'],
                    'total_non_kgs_girls' => $total['total_non_kgs_girls'],
                    'total_girls' => $total['total_girls'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Insert into mne_tab4_form_data_staging (mne_grade_termly_performance)
            foreach ($data['tab4']['mne_grade_termly_performance'] as $performance) {
                DB::table('mne_tab4_form_data_staging')->insert([
                    'reference_no' => $performance['reference_no'],
                    'form_name' => $performance['form_name'],
                    'grade' => $performance['grade'],
                    'subject' => $performance['subject'],
                    'program_status' => $performance['program_status'],
                    'value' => $performance['value'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Insert into mne_tab4_form_data_average_staging
            foreach ($data['tab4']['mne_average_termly_performance'] as $average) {
                DB::table('mne_tab4_form_data_average_staging')->insert([
                    'reference_no' => $average['reference_no'],
                    'form_name' => $average['form_name'],
                    'subject' => $average['subject'],
                    'average' => $average['average'],
                    'program_name' => $average['program_name'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Insert into mne_tab5_form_data_staging (mne_education_quality)
            DB::table('mne_tab5_form_data_staging')->insert([
                'reference_no' => $data['tab5']['mne_education_quality']['reference_no'],
                'school_id' => $data['tab5']['mne_education_quality']['school_id'],
                'form_name' => $data['tab5']['mne_education_quality']['form_name'],
                'responses' => json_encode($data['tab5']['mne_education_quality']['responses']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert into mne_tab6_form_data_staging (mne_payment_checklist)
            DB::table('mne_tab6_form_data_staging')->insert([
                'reference_no' => $data['tab6']['mne_payment_checklist']['reference_no'],
                'school_id' => $data['tab6']['mne_payment_checklist']['school_id'],
                'form_name' => $data['tab6']['mne_payment_checklist']['form_name'],
                'responses' => json_encode($data['tab6']['mne_payment_checklist']['responses']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert into mne_tab7_form_data_staging (mne_progression_grade12_completion)
            foreach ($data['tab7']['mne_progression_grade12_completion'] as $progression) {
                DB::table('mne_tab7_form_data_staging')->insert([
                    'reference_no' => $progression['reference_no'],
                    'form_name' => $progression['form_name'],
                    'grade' => $progression['grade'],
                    'program_status' => $progression['program_status'],
                    'school_status' => $progression['school_status'],
                    'scores' => $progression['scores'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Commit the transaction
            DB::commit();

            $processResponse = [
                'message' => 'MNE data inserted successfully',
                // 'data' => $data,
                'code' => 201,
            ];

        } catch (\Exception $e) {
            // Roll back the transaction on error
            DB::rollBack();
            Log::error("Error inserting MNE data", ['error' => $e->getMessage()]);
            $processResponse = [
                'message' => 'Error inserting MNE data',
                'error' => $e->getMessage(),
                'code' => 500,
            ];
        }

        Log::info("MNE Data Processing Completed", ['response' => $processResponse]);
        Log::info("---------------------------------------------------------------------------------------------------");

        return response()->json($processResponse, $processResponse['code']);
    }

    // test pg login
    public function testPgLoginOld()
    {
        // Credentials and endpoint
        $username = 'zispis';
        $password = 'aquiwei5taelaijohn6shoopeeQuie7e';
        $apikey   = 'eecai6EKi7wohm8Uongaif7obuayoh1c';
        $url      = 'https://pg.zispis.gov.zm/sps/api/zispis/prod/kgs/login';

        try {
            $client = new Client();

            $response = $client->request('POST', $url, [
                'auth' => [$username, $password],
                'headers' => [
                    'X-APIKey' => $apikey,
                    'Accept'   => 'application/json',
                ],
                'verify' => false // skip SSL verification if needed
            ]);

            // Decode response
            $body = json_decode($response->getBody()->getContents(), true);

            return response()->json([
                'success' => true,
                'message' => 'Connection successful',
                'status_code' => $response->getStatusCode(),
                'data' => $body
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login request failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function testPgLogin12()//perfect login
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

    public function generateTransactionIds()
    {
        // Fetch all records where transaction_id is NULL
        $beneficiaries = DB::table('sa_app_beneficiary_list_4')
            ->whereNull('transaction_id')
            ->get();

        if ($beneficiaries->isEmpty()) {
            return response()->json(['message' => 'All records already have transaction IDs.'], 404);
        }

        $now = Carbon::now();
        $updates = [];

        foreach ($beneficiaries as $beneficiary) {
            $uuid = Str::uuid();
            $timestamp = $now->timestamp;

            // Generate transaction ID
            $transactionId = "KGS_TID_{$uuid}_{$timestamp}";

            $updates[] = [
                'id' => $beneficiary->id,
                'transaction_id' => $transactionId,
                'transaction_time_initiated' => $now,
            ];
        }

        // Perform updates in a loop (or use batch update if preferred)
        foreach ($updates as $update) {
            DB::table('sa_app_beneficiary_list_4')
                ->where('id', $update['id'])
                ->update([
                    'transaction_id' => $update['transaction_id'],
                    'transaction_time_initiated' => $update['transaction_time_initiated'],
                ]);
        }

        return response()->json([
            'message' => 'Generated transaction IDs for ' . count($beneficiaries) . ' records',
            'updated_records' => count($beneficiaries),
        ]);
    }

    public function addSchoolToPaymentList($schoolId)
    {

        if (empty($schoolId)) {
        return response()->json([
            'error' => 'Missing required parameter: school_id'
        ], 400);
        }   

        try {
            DB::beginTransaction();

            // 1️⃣ Add school to payment list
            DB::statement("
                INSERT INTO sa_app_beneficiary_list_2 (
                    beneficiary_id,
                    school_id,
                    year_of_enrollment,
                    term_id,
                    school_grade,
                    term1_fees,
                    term2_fees,
                    term3_fees,
                    exam_fees
                )
                SELECT 
                    t1.beneficiary_id,
                    t1.school_id,
                    t1.year_of_enrollment,
                    t1.term_id,
                    t1.school_grade,
                    t1.term1_fees,
                    t1.term2_fees,
                    t1.term3_fees,
                    t1.exam_fees
                FROM beneficiary_enrollments t1
                WHERE 
                    t1.year_of_enrollment = 2025
                    AND t1.term_id = 1
                    AND t1.school_id = ?
            ", [$schoolId]);

            // 2️⃣ Provide grant amount for newly added school
            DB::statement("
                UPDATE sa_app_beneficiary_list_2
                SET grant_amount = 800.00
                WHERE grant_amount IS NULL
            ");

            // 3️⃣ Update payment status
            DB::statement("
                UPDATE sa_app_beneficiary_list_2
                SET payment_status = 'Pending Release'
                WHERE payment_status = '0'
            ");

            // 4️⃣ Update payment status ID
            DB::statement("
                UPDATE sa_app_beneficiary_list_2
                SET payment_status_id = '1'
                WHERE payment_status_id = '0'
            ");

            // 5️⃣ Update school name & district
            DB::statement("
                UPDATE sa_app_beneficiary_list_2 AS t1
                JOIN school_information AS t2 ON t1.school_id = t2.id
                JOIN districts AS t3 ON t2.district_id = t3.id
                SET
                    t1.school_name = CONCAT(t1.school_id, ' - ', t2.name),
                    t1.school_district = t3.name
                WHERE 
                    t1.school_name IS NULL 
                    OR t1.school_district IS NULL
            ");

            // 6️⃣ Update first & last name (with decrypt function)
            DB::statement("
                UPDATE sa_app_beneficiary_list_2 AS t2
                JOIN beneficiary_information AS t1 
                    ON t2.beneficiary_id = t1.id
                SET
                    t2.first_name = decrypt(t1.first_name),
                    t2.last_name  = decrypt(t1.last_name)
                WHERE 
                    t2.first_name IS NULL 
                    OR t2.last_name IS NULL
            ");

            // 7️⃣ Update household details
            DB::statement("
                UPDATE sa_app_beneficiary_list_2 AS t1
                JOIN beneficiary_information AS t2 
                    ON t1.beneficiary_id = t2.id
                JOIN households AS t3 
                    ON t3.id = t2.household_id
                SET 
                    t1.hhh_nrc_number = t3.hhh_nrc_number,
                    t1.hhh_fname = t3.hhh_fname,
                    t1.hhh_lname = t3.hhh_lname
                WHERE 
                    t1.hhh_nrc_number IS NULL 
                    OR t1.hhh_fname IS NULL 
                    OR t1.hhh_lname IS NULL
            ");

            // 8️⃣ Update beneficiary number
            DB::statement("
                UPDATE sa_app_beneficiary_list_2 AS t1
                JOIN beneficiary_information AS t2 
                    ON t1.beneficiary_id = t2.id
                SET 
                    t1.beneficiary_no = t2.beneficiary_id
                WHERE 
                    t1.beneficiary_no IS NULL
            ");

            // 9️⃣ Update school province & CWAC name
            DB::statement("
                UPDATE sa_app_beneficiary_list_2 AS t1
                JOIN school_information AS t2 
                    ON t1.school_id = t2.id
                JOIN provinces AS t3 
                    ON t2.province_id = t3.id
                JOIN cwac AS t4 
                    ON t2.cwac_id = t4.id
                SET 
                    t1.school_province = t3.name,
                    t1.cwac_name = t4.name
                WHERE 
                    t1.school_province IS NULL 
                    OR t1.cwac_name IS NULL
            ");

            // 🔟 Generate Transaction IDs for all null entries
            $beneficiaries = DB::table('sa_app_beneficiary_list_2')
                ->whereNull('transaction_id')
                ->get();

            $now = Carbon::now();

            foreach ($beneficiaries as $b) {
                $uuid = Str::uuid();
                $timestamp = $now->timestamp;
                $transactionId = "KGS_TID_{$uuid}_{$timestamp}";

                DB::table('sa_app_beneficiary_list_2')
                    ->where('id', $b->id)
                    ->update([
                        'transaction_id' => $transactionId,
                        'transaction_time_initiated' => $now,
                    ]);
            }

            DB::commit();

            return response()->json([
                'message' => "Payment data successfully prepared for school_id {$schoolId}",
                'transaction_ids_generated' => count($beneficiaries),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'An error occurred during processing.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function assignSchoolListToUserOld(Request $request)
    {
        $email = $request->query('email');
        $schoolId = $request->query('school');

        // 1️⃣ Validate parameters
        if (empty($email) || empty($schoolId)) {
            return response()->json([
                'error' => 'Missing required parameters. Provide both email and school.'
            ], 400);
        }

        // 2️⃣ Find user using decrypted email
        $user = DB::selectOne("SELECT * FROM users WHERE decrypt(email) = ?", [$email]);

        if (!$user) {
            return response()->json([
                'error' => 'Email is not registered in the system.'
            ], 404);
        }

        try {
            DB::beginTransaction();

            // 3️⃣ Update has_ppm_app_access from 0 → 1
            DB::table('users')
                ->where('id', $user->id)
                ->update(['has_ppm_app_access' => 1]);

            // 4️⃣ Fetch school, district, and CWAC info
            $school = DB::table('school_information AS t3')
                ->join('districts AS t4', 't3.district_id', '=', 't4.id')
                ->join('cwac AS t5', 't3.cwac_id', '=', 't5.id')
                ->select(
                    't3.id AS school_id',
                    't3.code AS emis',
                    't3.name AS school_name',
                    't3.district_id',
                    't4.name AS district_name',
                    't3.cwac_id',
                    't5.name AS cwac_name'
                )
                ->where('t3.id', $schoolId)
                ->first();

            if (!$school) {
                DB::rollBack();
                return response()->json([
                    'error' => 'School not found for the provided school ID.'
                ], 404);
            }

            // 5️⃣ Insert record into sa_app_user_details
            DB::table('sa_app_user_details')->insert([
                'user_id' => $user->id,
                'uuid' => $user->uuid,
                'school_assigned_id' => $school->school_id,
                'school_assigned_emis' => $school->emis,
                'school_assigned_string' => $school->school_id . ' - ' . $school->school_name,
                'district_assigned_id' => $school->district_id,
                'district_assigned_string' => $school->district_name,
                'school_cwac_id' => $school->cwac_id,
                'school_cwac_string' => $school->cwac_name,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'User access successfully updated and linked to school.',
                'user_id' => $user->id,
                'email' => $email,
                'school' => $school->school_name,
                'district' => $school->district_name,
                'cwac' => $school->cwac_name,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'An error occurred while updating user access.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function assignSchoolListToUser(Request $request)
    {
        $email = $request->query('email');
        $schoolId = $request->query('school');

        if (empty($email) || empty($schoolId)) {
            return response()->json([
                'error' => 'Missing required parameters. Provide both email and school.'
            ], 400);
        }

        // Check if the school exists
        $schoolExists = DB::table('school_information')->where('id', $schoolId)->exists();
        if (!$schoolExists) {
            return response()->json([
                'error' => 'The provided school ID does not exist in the school_information table.'
            ], 404);
        }

        // Find user
        $user = DB::selectOne("SELECT * FROM users WHERE decrypt(email) = ?", [$email]);
        if (!$user) {
            return response()->json([
                'error' => 'Email is not registered in the system.'
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Update access
            DB::table('users')
                ->where('id', $user->id)
                ->update(['has_ppm_app_access' => 1]);

            // Use LEFT JOINs to avoid missing relations
            $school = DB::table('school_information AS t3')
                ->leftJoin('districts AS t4', 't3.district_id', '=', 't4.id')
                ->leftJoin('cwac AS t5', 't3.cwac_id', '=', 't5.id')
                ->select(
                    't3.id AS school_id',
                    't3.code AS emis',
                    't3.name AS school_name',
                    't3.district_id',
                    't4.name AS district_name',
                    't3.cwac_id',
                    't5.name AS cwac_name'
                )
                ->where('t3.id', $schoolId)
                ->first();

            // Double-check that school was found after join
            if (!$school) {
                DB::rollBack();
                return response()->json([
                    'error' => 'School record found, but related district or CWAC info could not be retrieved.'
                ], 404);
            }

            DB::table('sa_app_user_details')->insert([
                'user_id' => $user->id,
                'uuid' => $user->uuid,
                'school_assigned_id' => $school->school_id,
                'school_assigned_emis' => $school->emis,
                'school_assigned_string' => $school->school_id . ' - ' . $school->school_name,
                'district_assigned_id' => $school->district_id,
                'district_assigned_string' => $school->district_name,
                'school_cwac_id' => $school->cwac_id,
                'school_cwac_string' => $school->cwac_name,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'User access successfully updated and linked to school.',
                'user_id' => $user->id,
                'email' => $email,
                'school' => $school->school_name,
                'district' => $school->district_name,
                'cwac' => $school->cwac_name,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'An error occurred while updating user access.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function getPaymentPhaseSchools()
    {
        $schools = DB::table('beneficiary_enrollments')
            ->where('year_of_enrollment', 2025)
            ->where('term_id', 2)
            ->where('payment_phase', 2)
            ->distinct()
            ->pluck('school_id');

        if ($schools->isEmpty()) {
            return response()->json([
                'message' => 'No schools found for the given criteria.'
            ], 404);
        }

        return response()->json([
            'total_schools' => count($schools),
            'school_ids' => $schools
        ]);
    }

    public function addPhase2BenToPaymentList()
    {
        try {
            DB::beginTransaction();

            // 1️⃣ Insert all matching beneficiaries into payment list
            DB::statement("
                INSERT INTO sa_app_beneficiary_list_2 (
                    beneficiary_id,
                    school_id,
                    year_of_enrollment,
                    term_id,
                    school_grade,
                    term1_fees,
                    term2_fees,
                    term3_fees,
                    exam_fees
                )
                SELECT 
                    t1.beneficiary_id,
                    t1.school_id,
                    t1.year_of_enrollment,
                    t1.term_id,
                    t1.school_grade,
                    t1.term1_fees,
                    t1.term2_fees,
                    t1.term3_fees,
                    t1.exam_fees
                FROM beneficiary_enrollments t1
                WHERE 
                    t1.year_of_enrollment = 2025
                    AND t1.term_id = 2
                    AND t1.payment_phase = 1
            ");

            // 2️⃣ Grant amount
            DB::statement("
                UPDATE sa_app_beneficiary_list_2
                SET grant_amount = 800.00
                WHERE grant_amount IS NULL
            ");

            // 3️⃣ Payment status updates
            DB::statement("
                UPDATE sa_app_beneficiary_list_2
                SET payment_status = 'Pending Release'
                WHERE payment_status = '0'
            ");

            DB::statement("
                UPDATE sa_app_beneficiary_list_2
                SET payment_status_id = '1'
                WHERE payment_status_id = '0'
            ");

            // 4️⃣ School name and district
            DB::statement("
                UPDATE sa_app_beneficiary_list_2 AS t1
                JOIN school_information AS t2 ON t1.school_id = t2.id
                JOIN districts AS t3 ON t2.district_id = t3.id
                SET
                    t1.school_name = CONCAT(t1.school_id, ' - ', t2.name),
                    t1.school_district = t3.name
                WHERE 
                    t1.school_name IS NULL 
                    OR t1.school_district IS NULL
            ");

            // 5️⃣ First & last name
            DB::statement("
                UPDATE sa_app_beneficiary_list_2 AS t2
                JOIN beneficiary_information AS t1 
                    ON t2.beneficiary_id = t1.id
                SET
                    t2.first_name = decrypt(t1.first_name),
                    t2.last_name  = decrypt(t1.last_name)
                WHERE 
                    t2.first_name IS NULL 
                    OR t2.last_name IS NULL
            ");

            // 6️⃣ Household details
            DB::statement("
                UPDATE sa_app_beneficiary_list_2 AS t1
                JOIN beneficiary_information AS t2 
                    ON t1.beneficiary_id = t2.id
                JOIN households AS t3 
                    ON t3.id = t2.household_id
                SET 
                    t1.hhh_nrc_number = t3.hhh_nrc_number,
                    t1.hhh_fname = t3.hhh_fname,
                    t1.hhh_lname = t3.hhh_lname
                WHERE 
                    t1.hhh_nrc_number IS NULL 
                    OR t1.hhh_fname IS NULL 
                    OR t1.hhh_lname IS NULL
            ");

            // 7️⃣ Beneficiary number
            DB::statement("
                UPDATE sa_app_beneficiary_list_2 AS t1
                JOIN beneficiary_information AS t2 
                    ON t1.beneficiary_id = t2.id
                SET 
                    t1.beneficiary_no = t2.beneficiary_id
                WHERE 
                    t1.beneficiary_no IS NULL
            ");

            // 8️⃣ School province & CWAC
            DB::statement("
                UPDATE sa_app_beneficiary_list_2 AS t1
                JOIN school_information AS t2 
                    ON t1.school_id = t2.id
                JOIN provinces AS t3 
                    ON t2.province_id = t3.id
                JOIN cwac AS t4 
                    ON t2.cwac_id = t4.id
                SET 
                    t1.school_province = t3.name,
                    t1.cwac_name = t4.name
                WHERE 
                    t1.school_province IS NULL 
                    OR t1.cwac_name IS NULL
            ");

            // 9️⃣ Transaction IDs
            $beneficiaries = DB::table('sa_app_beneficiary_list_2')
                ->whereNull('transaction_id')
                ->get();

            $now = Carbon::now();

            foreach ($beneficiaries as $b) {
                $uuid = Str::uuid();
                $timestamp = $now->timestamp;
                $transactionId = "KGS_TID_{$uuid}_{$timestamp}";

                DB::table('sa_app_beneficiary_list_2')
                    ->where('id', $b->id)
                    ->update([
                        'transaction_id' => $transactionId,
                        'transaction_time_initiated' => $now,
                    ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'All beneficiaries (payment_phase = 2) successfully added to payment list.',
                'transaction_ids_generated' => count($beneficiaries)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'An error occurred during processing.',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function addPh2BenFromConsolidatedListOld()
    {
        try {
            DB::beginTransaction();

            // 1️⃣ Insert matching beneficiaries from consolidated list
            DB::statement("
                INSERT INTO sa_app_beneficiary_list_3 (
                    school_id,
                    beneficiary_id,
                    beneficiary_no,
                    first_name,
                    last_name,
                    school_district,
                    hhh_nrc_number,
                    cwac_name
                )
                SELECT 
                    t1.school_id,
                    t1.beneficiary_id,
                    t1.beneficiary_no,
                    t1.first_name,
                    t1.last_name,
                    t1.school_district_name,
                    t1.household_nrc_no,
                    t1.cwac_name
                FROM consolidated_payment_list_all AS t1
                WHERE t1.school_district_id IN (18, 37, 32, 66)
            ");

            // 2️⃣ Household details (use provided hhh_nrc_number to get hhh_fname and hhh_lname)
            DB::statement("
                UPDATE sa_app_beneficiary_list_3 AS t1
                JOIN households AS t2 
                    ON t1.hhh_nrc_number = t2.hhh_nrc_number
                SET 
                    t1.hhh_fname = t2.hhh_fname,
                    t1.hhh_lname = t2.hhh_lname
                WHERE 
                    (t1.hhh_fname IS NULL OR t1.hhh_lname IS NULL)
            ");

            // 3️⃣ Update school province (no CWAC this time)
            DB::statement("
                UPDATE sa_app_beneficiary_list_3 AS t1
                JOIN school_information AS t2 
                    ON t1.school_id = t2.id
                JOIN provinces AS t3 
                    ON t2.province_id = t3.id
                SET 
                    t1.school_province = t3.name
                WHERE 
                    t1.school_province IS NULL
            ");

            DB::commit();

            return response()->json([
                'message' => 'Beneficiaries successfully added to sa_app_beneficiary_list_3 from consolidated list for selected districts (18, 37, 32, 66).'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'An error occurred during processing.',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function addPh2BenFromConsolidatedList()
    {
        try {
            DB::beginTransaction();

            // 1️⃣ Insert matching beneficiaries from consolidated list
            DB::statement("
                INSERT INTO sa_app_beneficiary_list_3 (
                    school_id,
                    beneficiary_id,
                    beneficiary_no,
                    first_name,
                    last_name,
                    school_district,
                    hhh_nrc_number,
                    cwac_name
                )
                SELECT 
                    t1.school_id,
                    t1.beneficiary_id,
                    t1.beneficiary_no,
                    t1.first_name,
                    t1.last_name,
                    t1.school_district_name,
                    t1.household_nrc_no,
                    t1.cwac_name
                FROM consolidated_payment_list_all AS t1
                WHERE t1.school_district_id IN (18, 37, 32, 66)
            ");

            // 2️⃣ Household details (derive hhh_fname and hhh_lname directly from household_name)
            DB::statement("
                UPDATE sa_app_beneficiary_list_3 AS t1
                JOIN consolidated_payment_list_all AS t2 
                    ON t1.beneficiary_no COLLATE utf8mb4_unicode_ci = t2.beneficiary_no COLLATE utf8mb4_unicode_ci
                SET 
                    t1.hhh_fname = TRIM(SUBSTRING_INDEX(t2.household_name, ' ', 1)),
                    t1.hhh_lname = TRIM(SUBSTRING_INDEX(t2.household_name, ' ', -1))
                WHERE 
                    (t1.hhh_fname IS NULL OR t1.hhh_lname IS NULL)
            ");

            // 🆕 3️⃣ Update school name (using school_id)
            DB::statement("
                UPDATE sa_app_beneficiary_list_3 AS t1
                JOIN school_information AS t2 
                    ON t1.school_id = t2.id
                SET 
                    t1.school_name = CONCAT(t2.id, ' - ', t2.name)
                WHERE 
                    t1.school_name IS NULL OR t1.school_name = ''
            ");

            // 4️⃣ Update school province (no CWAC this time)
            DB::statement("
                UPDATE sa_app_beneficiary_list_3 AS t1
                JOIN school_information AS t2 
                    ON t1.school_id = t2.id
                JOIN provinces AS t3 
                    ON t2.province_id = t3.id
                SET 
                    t1.school_province = t3.name
                WHERE 
                    t1.school_province IS NULL
            ");

            // 5️⃣ Grant amount
            DB::statement("
                UPDATE sa_app_beneficiary_list_3
                SET grant_amount = 800.00
                WHERE grant_amount IS NULL
            ");

            // 6️⃣ Payment status updates
            DB::statement("
                UPDATE sa_app_beneficiary_list_3
                SET payment_status = 'Pending Release'
                WHERE payment_status = '0'
            ");

            DB::statement("
                UPDATE sa_app_beneficiary_list_3
                SET payment_status_id = '1'
                WHERE payment_status_id = '0'
            ");

            // 7️⃣ Transaction IDs
            $beneficiaries = DB::table('sa_app_beneficiary_list_3')
                ->whereNull('transaction_id')
                ->get();

            $now = Carbon::now();

            foreach ($beneficiaries as $b) {
                $uuid = Str::uuid();
                $timestamp = $now->timestamp;
                $transactionId = "KGS_TID_{$uuid}_{$timestamp}";

                DB::table('sa_app_beneficiary_list_3')
                    ->where('id', $b->id)
                    ->update([
                        'transaction_id' => $transactionId,
                        'transaction_time_initiated' => $now,
                    ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Beneficiaries successfully added to sa_app_beneficiary_list_3 from consolidated list for selected districts (18, 37, 32, 66).'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'An error occurred during processing.',
                'details' => $e->getMessage()
            ], 500);
        }
    }


    public function getPaymentSummaries()
    {
        $data = DB::table('payment_disbursements_summary as t1')
            ->leftJoin('users as t3', 't3.id', '=', 't1.prepared_by')
            ->leftJoin('pg_workflow_status as w', 'w.id', '=', 't1.workflow_status_id')
            ->select(
                't1.*',
                DB::raw("CONCAT(decrypt(t3.first_name), ' ', decrypt(t3.last_name)) AS prepared_by"),
                'w.name as workflow_status'
            )
            ->get();

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    public function getPaymentPhasesOld(Request $request)
    {
        $refNo = $request->payment_ref_no;

        if (!$refNo) {
            return response()->json([
                "status" => false,
                "message" => "payment_ref_no is required"
            ], 400);
        }

        $rows = DB::table('sa_app_beneficiary_list_3 as t1')
            ->select(
                't1.payment_phase',
                DB::raw('COUNT(DISTINCT t1.school_id) as total_schools'),
                DB::raw('COUNT(t1.beneficiary_id) as total_beneficiaries'),
                DB::raw('SUM(t1.grant_amount) as amount')
            )
            ->where('t1.payment_ref_no', $refNo)
            ->where('t1.payment_phase', '>', 0)
            ->groupBy('t1.payment_phase')
            ->orderBy('t1.payment_phase')
            ->get();

        return response()->json([
            "status" => true,
            "payment_ref_no" => $refNo,
            "total_phases" => $rows->count(),
            "data" => $rows
        ]);
    }

    public function getPaymentPhases(Request $request)
    {
        $refNo = $request->payment_ref_no;

        if (!$refNo) {
            return response()->json([
                "status" => false,
                "message" => "payment_ref_no is required"
            ], 400);
        }

        // ─────────────────────────────────────────────
        // 1. Get workflow status from summary + join status name
        // ─────────────────────────────────────────────
        $workflow = DB::table('payment_disbursements_summary as p')
            ->leftJoin('pg_workflow_status as w', 'w.id', '=', 'p.workflow_status_id')
            ->select('p.workflow_status_id', 'w.name as workflow_status_name')
            ->where('p.payment_ref_no', $refNo)
            ->first();

        // Default fallback (in case no record found)
        $workflowStatusId   = $workflow->workflow_status_id ?? null;
        $workflowStatusName = $workflow->workflow_status_name ?? "unknown";

        // ─────────────────────────────────────────────
        // 2. Get phases and their totals
        // ─────────────────────────────────────────────
        $rows = DB::table('sa_app_beneficiary_list_3 as t1')
            ->select(
                't1.payment_phase',
                DB::raw('COUNT(DISTINCT t1.school_id) as total_schools'),
                DB::raw('COUNT(t1.beneficiary_id) as total_beneficiaries'),
                DB::raw('SUM(t1.grant_amount) as amount')
            )
            ->where('t1.payment_ref_no', $refNo)
            //added this line to only show 1 phase
            ->where('t1.payment_phase', 1) 
            //added below to filter Luanshya district
            ->whereIn('t1.school_district', ['205-Luanshya'])
            ->groupBy('t1.payment_phase')
            ->orderBy('t1.payment_phase')
            ->get();

        // ─────────────────────────────────────────────
        // 3. Return unified JSON structure
        // ─────────────────────────────────────────────
        return response()->json([
            "status" => true,
            "payment_ref_no" => $refNo,

            // NEW FIELDS
            "workflow_status_id"   => $workflowStatusId,
            "workflow_status_name" => $workflowStatusName,

            "total_phases" => $rows->count(),
            "data" => $rows
        ]);
    }

    public function getPhaseSchoolsOld(Request $request)
    {
        $refNo = $request->payment_ref_no;
        $phase = $request->payment_phase;

        if (!$refNo || !$phase) {
            return response()->json([
                "status" => false,
                "message" => "payment_ref_no and payment_phase are required"
            ], 400);
        }

        $rows = DB::table('sa_app_beneficiary_list_3 as t1')
            ->select(
                't1.school_name',
                't1.school_district',
                't1.school_id',
                DB::raw('COUNT(t1.beneficiary_id) as total_beneficiaries'),
                DB::raw('SUM(t1.grant_amount) as total_amount')
            )
            ->where('t1.payment_ref_no', $refNo)
            ->where('t1.payment_phase', $phase)
            ->groupBy('t1.school_id', 't1.school_name', 't1.school_district')
            ->orderBy('t1.school_name')
            ->get();

        return response()->json([
            "status" => true,
            "payment_ref_no" => $refNo,
            "payment_phase" => $phase,
            "total_schools" => $rows->count(),
            "data" => $rows
        ]);
    }

    public function getPhaseSchools(Request $request)
    {
        $refNo = $request->payment_ref_no;
        $phase = $request->payment_phase;

        if (!$refNo || !$phase) {
            return response()->json([
                "status" => false,
                "message" => "payment_ref_no and payment_phase are required"
            ], 400);
        }

        // Fetch school-level aggregation from sa_app_beneficiary_list_3
        $rows = DB::table('sa_app_beneficiary_list_3 as t1')
            ->select(
                't1.school_name',
                't1.school_district',
                't1.school_id',
                DB::raw('COUNT(t1.beneficiary_id) as total_beneficiaries'),
                DB::raw('SUM(t1.grant_amount) as total_amount')
            )
            ->where('t1.payment_ref_no', $refNo)
            ->where('t1.payment_phase', $phase)
            //added below filter to only see 2 district schools
            ->whereIn('t1.school_district', ['205-Luanshya'])  
            ->groupBy('t1.school_id', 't1.school_name', 't1.school_district')
            ->orderBy('t1.school_name')
            ->get();

        // Enhance each row with district bank account information
        $enhanced = $rows->map(function ($row) {

            // 1️⃣ Extract district name from "1011-Chisamba"
            $districtParts = explode('-', $row->school_district);
            $districtName = trim($districtParts[1] ?? '');

            // 2️⃣ Find district ID from districts table
            $district = DB::table('districts')
                ->where('name', $districtName)
                ->first();

            $districtId = $district->id ?? null;

            // 3️⃣ Fetch district bank account record
            $bank = null;

            if ($districtId) {
                $bank = DB::table('district_bank_accounts')
                    ->where('district_id', $districtId)
                    ->first();
            }

            // 4️⃣ Append bank info to JSON (or null if missing)
            $row->bank_name      = $bank->bank_name      ?? null;
            $row->branch_name    = $bank->branch_name    ?? null;
            $row->account_number = $bank->account_number ?? null;
            $row->sort_code      = $bank->sort_code      ?? null;

            return $row;
        });

        return response()->json([
            "status" => true,
            "payment_ref_no" => $refNo,
            "payment_phase" => $phase,
            "total_schools" => $enhanced->count(),
            "data" => $enhanced
        ]);
    }

    public function getPaymentBeneficiaries(Request $request)
    {
        $refNo  = $request->payment_ref_no;
        $phase  = $request->payment_phase;
        $school = $request->school_id;

        if (!$refNo || !$phase || !$school) {
            return response()->json([
                "status" => false,
                "message" => "payment_ref_no, payment_phase and school_id are required"
            ], 400);
        }

        $rows = DB::table('sa_app_beneficiary_list_3 as t1')
            ->select(
                't1.beneficiary_no',
                't1.first_name',
                't1.last_name',
                't1.school_name',
                't1.hhh_nrc_number',
                't1.hhh_fname',
                't1.hhh_lname',
                't1.payment_status_id',
                't1.grant_amount'
            )
            ->where('t1.payment_ref_no', $refNo)
            ->where('t1.payment_phase', $phase)
            ->where('t1.school_id', $school)
            ->orderBy('t1.beneficiary_no')
            ->get();

        return response()->json([
            "status" => true,
            "payment_ref_no" => $refNo,
            "payment_phase" => $phase,
            "school_id" => $school,
            "total_beneficiaries" => $rows->count(),
            "data" => $rows
        ]);
    }

    public function submitToPanelA(Request $request)
    {
        $refNo = $request->payment_ref_no;

        if (!$refNo) {
            return response()->json([
                "status" => false,
                "message" => "payment_ref_no is required"
            ], 400);
        }
        // Fetch payment
        $payment = DB::table('payment_disbursements_summary')
            ->where('payment_ref_no', $request->payment_ref_no)
            ->first();

        if (!$payment) {
            return response()->json([
                'status' => false,
                'message' => 'Payment record not found.'
            ], 404);
        }

        // === VALIDATION: Check workflow status ===
        if ($payment->workflow_status_id != 1) {

            // Workflow status messages by ID
            $messages = [
                2 => 'This payment has already been submitted to Panel A.',
                3 => 'This payment has already been approved by Panel A and sent to Panel B.',
                4 => 'This payment is already pending PG submission.',
                5 => 'This payment has already been submitted to PG.'
            ];

            $msg = $messages[$payment->workflow_status_id] ?? 'Invalid workflow stage.';

            return response()->json([
                'status' => false,
                'message' => $msg
            ], 400);
        }

        // === UPDATE STAGE: Move to pending panel A approval ===
        DB::table('payment_disbursements_summary')
            ->where('payment_ref_no', $request->payment_ref_no)
            ->update([
                'workflow_status_id' => 2,
                'updated_at' => now()
            ]);

        return response()->json([
            'status' => true,
            'message' => 'Successfully submitted to Panel A for approval.'
        ]);
    }

    public function submitToPanelB(Request $request)
    {
        $refNo = $request->payment_ref_no;

        if (!$refNo) {
            return response()->json([
                "status" => false,
                "message" => "payment_ref_no is required"
            ], 400);
        }
        // Fetch payment
        $payment = DB::table('payment_disbursements_summary')
            ->where('payment_ref_no', $request->payment_ref_no)
            ->first();

        if (!$payment) {
            return response()->json([
                'status' => false,
                'message' => 'Payment record not found.'
            ], 404);
        }

        // === VALIDATION: Check workflow status ===
        if ($payment->workflow_status_id != 2) {

            // Workflow status messages by ID
            $messages = [
                1 => 'This payment is still pending Accountant approval.',
                3 => 'This payment has already been submitted to Panel B.',
                4 => 'This payment is already pending PG submission.',
                5 => 'This payment has already been submitted to PG.'
            ];

            $msg = $messages[$payment->workflow_status_id] ?? 'Invalid workflow stage.';

            return response()->json([
                'status' => false,
                'message' => $msg
            ], 400);
        }

        // === UPDATE STAGE: Move to pending panel A approval ===
        DB::table('payment_disbursements_summary')
            ->where('payment_ref_no', $request->payment_ref_no)
            ->update([
                'workflow_status_id' => 3,
                'updated_at' => now()
            ]);

        return response()->json([
            'status' => true,
            'message' => 'Successfully submitted to Panel B for approval.'
        ]);
    }

    public function PanelBApproval(Request $request)
    {
        $refNo = $request->payment_ref_no;

        if (!$refNo) {
            return response()->json([
                "status" => false,
                "message" => "payment_ref_no is required"
            ], 400);
        }
        // Fetch payment
        $payment = DB::table('payment_disbursements_summary')
            ->where('payment_ref_no', $request->payment_ref_no)
            ->first();

        if (!$payment) {
            return response()->json([
                'status' => false,
                'message' => 'Payment record not found.'
            ], 404);
        }

        // === VALIDATION: Check workflow status ===
        if ($payment->workflow_status_id != 3) {

            // Workflow status messages by ID
            $messages = [
                1 => 'This payment is still pending Accountant approval.',
                2 => 'This payment is still pending Panel A approval.',
                3 => 'This payment is pending Panel B approval.',
                4 => 'This payment has already been approved by Panel B and is pending PG submission.',
                5 => 'This payment has already been submitted to PG.'
            ];

            $msg = $messages[$payment->workflow_status_id] ?? 'Invalid workflow stage.';

            return response()->json([
                'status' => false,
                'message' => $msg
            ], 400);
        }

        // === UPDATE STAGE: Move to pending panel A approval ===
        DB::table('payment_disbursements_summary')
            ->where('payment_ref_no', $request->payment_ref_no)
            ->update([
                'workflow_status_id' => 4,
                'updated_at' => now()
            ]);

        return response()->json([
            'status' => true,
            'message' => 'Payment Request Successfully Approved.'
        ]);
    }

    public function getPGCoordinators()
    {
        $users = DB::table('users')
            ->select(
                'id',
                DB::raw("decrypt(first_name) AS first_name"),
                DB::raw("decrypt(last_name) AS last_name"),
                DB::raw("decrypt(email) AS email")
            )
            ->where('is_coordinator', 1)
            ->get();

        return response()->json([
            'status' => true,
            'data' => $users
        ]);
    }

    //submit to PG functions
    private function generateTransactionIdOld($paymentRefNo, $phase) //not in use
    {
        $parts   = explode('/', $paymentRefNo);
        $refCode = end($parts);

        $uuid = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $year = date('Y');

        return "KGS_PAY_REQ_{$year}_{$refCode}_PH_{$phase}_{$uuid}";
    }

    public function buildPaymentPayload(Request $request) //not in use
    {
        $refNo  = $request->payment_ref_no;
        $phase  = $request->payment_phase;

        if (!$refNo || !$phase) {
            return response()->json([
                "status"  => false,
                "message" => "payment_ref_no and payment_phase are required"
            ], 400);
        }

        // Fetch beneficiaries for specific phase
        $beneficiaries = DB::table('sa_app_beneficiary_list_3')
            ->where('payment_ref_no', $refNo)
            ->where('payment_phase', $phase)
            ->where('in_excel', 1)
            ->get();

        if ($beneficiaries->isEmpty()) {
            return response()->json([
                "status" => false,
                "message" => "No beneficiaries found for this payment reference and phase"
            ], 404);
        }

        $payloadItems = [];

        foreach ($beneficiaries as $b) {

            // Extract district
            $districtName = trim(explode('-', $b->school_district)[1] ?? '');

            // District ID
            $districtRow = DB::table('districts')
                ->where('name', $districtName)
                ->first();

            $districtId = $districtRow->id ?? 0;

            // Get bank details
            $bank = DB::table('district_bank_accounts')
                ->where('district_id', $districtId)
                ->first();

            $payloadItems[] = [
                "TransactionID"    => $b->transaction_id,
                "TransactionDate"  => Carbon::now()->format('Y-m-d\TH:i:s'),
                "RecipientID"      => Uuid::uuid4()->toString(),
                "RecipientType"    => "Beneficiary",
                "Gender"           => $b->gender ?? "Female",
                "FirstName"        => $b->first_name,
                "LastName"         => $b->last_name,
                "MobileNumber"     => $b->mobile_phone_parent_guardian ?? "",
                "Language"         => "English",
                "LanguageCode"     => "eng",
                "Country"          => "ZM",
                "PSP"              => $bank->bank_name ?? "Zanaco",
                "Province"         => $b->school_province,
                "District"         => $districtName,
                "Ward"             => "",
                "CWAC"             => $b->cwac_name,
                "RegisteredTown"   => "",
                "DistrictID"       => $districtId,
                "WardID"           => 0,
                "CWACID"           => $b->cwac_id,
                "HouseholdID"      => 0,
                "NRC"              => $b->hhh_nrc_number,
                "DateOfBirth"      => Carbon::parse($b->dob)->format('d/m/Y'),
                "AccountNumber"    => $bank->account_number ?? "",
                "AccountExtra"     => "",
                "Currency"         => "ZMW",
                "TransactionType"  => "Grant",
                "Amount"           => floatval($b->grant_amount),
                "GPSAccuracy"      => 0,
                "GPSAltitude"      => 0,
                "GPSLatitude"      => 0,
                "GPSLongitude"     => 0,
                "PaymentReference" => $refNo,
                "PaymentCycle"     => "KGS 2025 Term 2 Payment"
            ];
        }

        return response()->json([
            "status"         => true,
            "payment_ref_no" => $refNo,
            "payment_phase"  => $phase,
            "records"        => count($payloadItems),
            "payload"        => [
                "TotalRecords" => count($payloadItems),
                "Transactions" => $payloadItems
            ]
        ]);
    }

    public function submitPaymentToPG(Request $request) //not in use
    {
        $refNo = $request->payment_ref_no;
        $phase = $request->payment_phase;

        if (!$refNo || !$phase) {
            return response()->json([
                "status" => false,
                "message" => "payment_ref_no and payment_phase are required"
            ], 400);
        }

        // Step 1: Build payload using internal helper
        $payloadResult = $this->buildPaymentPayloadInternal($refNo, $phase);

        if (!$payloadResult["status"]) {
            return response()->json($payloadResult, 404);
        }

        $payload = $payloadResult["payload"];

        // Step 2: Generate transaction ID
        $transactionID = $this->generateTransactionId($refNo, $phase);

        // PG URL
        $url = "https://pg.zispis.gov.zm/sps/api/zispis/prod/kgs/payment/{$transactionID}";

        // Step 3: Headers
        $headers = $this->preparePGHeaders();

        // Step 4: Log initial request
        $logId = DB::table('pg_payment_logs')->insertGetId([
            "payment_ref_no"  => $refNo,
            "payment_phase"   => $phase,
            "request_payload" => json_encode($payload),
            "status"          => "pending",
            "created_at"      => now(),
            "updated_at"      => now()
        ]);

        // Step 5: Submit to PG
        try {
            $client = new \GuzzleHttp\Client(['verify' => false]);

            $response = $client->post($url, [
                'headers'     => $headers,
                'json'        => $payload,
                'http_errors' => false
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();

            // Update logs
            DB::table('pg_payment_logs')
                ->where('id', $logId)
                ->update([
                    "http_status"   => $statusCode,
                    "response_body" => $responseBody,
                    "status"        => $statusCode == 200 ? "success" : "failed",
                    "updated_at"    => now()
                ]);

            return response()->json([
                "status" => $statusCode == 200,
                "message" => $statusCode == 200 ? "Payment submitted successfully" : "PG rejected the request",
                "transaction_id" => $transactionID,
                "pg_response" => json_decode($responseBody, true)
            ]);

        } catch (\Exception $e) {

            DB::table('pg_payment_logs')
                ->where('id', $logId)
                ->update([
                    "status"        => "failed",
                    "response_body" => $e->getMessage(),
                    "updated_at"    => now()
                ]);

            return response()->json([
                "status"  => false,
                "message" => "Error connecting to Payment Gateway",
                "error"   => $e->getMessage()
            ], 500);
        }
    }

    private function buildPaymentPayloadInternalOld($refNo, $phase) //not in use
    {
        $request = new Request([
            'payment_ref_no' => $refNo,
            'payment_phase'  => $phase
        ]);

        return json_decode($this->buildPaymentPayload($request)->getContent(), true);
    }

    public function submitPaymentToPGDebugOld(Request $request) //not in use
    {
        $refNo   = $request->payment_ref_no;
        $phase   = $request->payment_phase;
        $debug   = $request->debug == 1 ? true : false;   // Enable debug mode

        if (!$refNo || !$phase) {
            return response()->json([
                "status"  => false,
                "message" => "payment_ref_no and payment_phase are required"
            ], 400);
        }

        // Step 1: Build payload using internal helper
        $payloadResult = $this->buildPaymentPayloadSchools();

        dd('all is ok');

        if (!$payloadResult["status"]) {
            return response()->json($payloadResult, 404);
        }

        $payload = $payloadResult["payload"];

        // dd('all is well');

        // Step 2: Generate transaction ID
        $transactionID = 'KGS_TID_45f567f2-c51a-4050-ba08-1f3c5a21a946';

        // dd('all is well', $transactionID);

        // Step 3: Build URL
        $url = "https://pg.zispis.gov.zm/sps/api/zispis/prod/kgs/payment/{$transactionID}";
        // $url = "https://pg.zispis.gov.zm/sps/api/zispis/prod/kgs/payment";

        // dd('all is well', $url);

        // Step 4: PG Headers
        $headers = $this->preparePGHeaders();

        // dd('all is well', $headers);

        // Step 5: Insert initial log
        $logId = DB::table('pg_payment_logs')->insertGetId([
            "payment_ref_no"  => $refNo,
            "payment_phase"   => $phase,
            "request_payload" => json_encode($payload),
            "request_url"     => $url,
            "headers"         => json_encode($headers),
            "status"          => "pending",
            "created_at"      => now(),
            "updated_at"      => now()
        ]);

        // dd('all is well', $logId);

        // ⭐⭐⭐ DEBUG MODE RESPONSE — no PG call is made
        if ($debug) {
            return response()->json([
                "status"            => true,
                "debug_mode"        => true,
                "message"           => "DEBUG MODE ENABLED — No PG request sent",
                "transaction_id"    => $transactionID,
                "pg_url"            => $url,
                "pg_headers"        => $headers,
                "payload_being_sent"=> $payload
            ]);
        }

        // Step 6: Submit to PG
        try {
            $client = new \GuzzleHttp\Client(['verify' => false]);

            $response = $client->post($url, [
                'headers'     => $headers,
                'json'        => $payload,
                'http_errors' => false
            ]);

            $statusCode   = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();

            // Update log
            DB::table('pg_payment_logs')
                ->where('id', $logId)
                ->update([
                    "http_status"   => $statusCode,
                    "response_body" => $responseBody,
                    "status"        => $statusCode == 200 ? "success" : "failed",
                    "updated_at"    => now()
                ]);

            return response()->json([
                "status"        => $statusCode == 200,
                "message"       => $statusCode == 200 ? "Payment submitted successfully" : "PG rejected the request",
                "transaction_id"=> $transactionID,
                "pg_response"   => json_decode($responseBody, true)
            ]);

        } catch (\Exception $e) {

            DB::table('pg_payment_logs')
                ->where('id', $logId)
                ->update([
                    "status"        => "failed",
                    "response_body" => $e->getMessage(),
                    "updated_at"    => now()
                ]);

            return response()->json([
                "status"  => false,
                "message" => "Error connecting to Payment Gateway",
                "error"   => $e->getMessage()
            ], 500);
        }
    }

    private function buildPaymentPayloadInternal($refNo, $phase) //not in use 
    {
        if (!$refNo || !$phase) {
            return [
                "status" => false,
                "message" => "payment_ref_no and payment_phase are required"
            ];
        }

        $beneficiaries = DB::table('sa_app_beneficiary_list_3')
            ->where('payment_ref_no', $refNo)
            ->where('payment_phase', $phase)
            ->where('in_excel', 1)
            ->get();

        if ($beneficiaries->isEmpty()) {
            return [
                "status" => false,
                "message" => "No beneficiaries found for this payment reference and phase"
            ];
        }

        $items = [];

        foreach ($beneficiaries as $b) {

            $districtName = trim(explode('-', $b->school_district)[1] ?? '');

            $districtRow = DB::table('districts')
                ->where('name', $districtName)
                ->first();

            $districtId = $districtRow->id ?? 0;

            $bank = DB::table('district_bank_accounts')
                ->where('district_id', $districtId)
                ->first();

            $items[] = [
                "TransactionID"    => $b->transaction_id,
                "TransactionDate"  => now()->format('Y-m-d\TH:i:s'),
                "RecipientID"      => Uuid::uuid4()->toString(),
                "RecipientType"    => "Beneficiary",
                "Gender"           => "Female",
                "FirstName"        => $b->first_name,
                "LastName"         => $b->last_name,
                "MobileNumber"     => $b->mobile_phone_parent_guardian,
                "Language"         => "English",
                "LanguageCode"     => "eng",
                "Country"          => "ZM",
                "PSP"              => $bank->bank_name ?? "Zanaco",
                "Province"         => $b->school_province,
                "District"         => $districtName,
                "Ward"             => "",
                "CWAC"             => $b->cwac_name,
                "RegisteredTown"   => "",
                "DistrictID"       => $districtId,
                "WardID"           => 0,
                "CWACID"           => $b->cwac_id,
                "HouseholdID"      => 0,
                "NRC"              => $b->hhh_nrc_number,
                "DateOfBirth"      => Carbon::parse($b->dob)->format('d/m/Y'),
                "AccountNumber"    => $bank->account_number ?? "",
                "AccountExtra"     => "",
                "Currency"         => "ZMW",
                "TransactionType"  => "Grant",
                "Amount"           => floatval($b->grant_amount),
                "GPSAccuracy"      => 0,
                "GPSAltitude"      => 0,
                "GPSLatitude"      => 0,
                "GPSLongitude"     => 0,
                "PaymentReference" => $refNo,
                "PaymentCycle"     => "KGS 2025 Term 2 Payment"
            ];
        }

        return [
            "status"  => true,
            "payload" => [
                "TotalRecords" => count($items),
                "Transactions" => $items
            ]
        ];
    }
    public function buildPaymentPayloadSchools(Request $request) //not in use
    {
        $schools = DB::table('grant_pilotschedule_one')
        ->limit (1)
        ->get();

        if ($schools->isEmpty()) {
            return response()->json([
                "status" => false,
                "message" => "No schools found"
            ], 404);
        }


        $payloadItems = [];

        foreach ($schools as $b) {

            // 1️⃣ Generate transaction ID only if null
            if (is_null($b->transaction_id) || $b->transaction_id === "") {

                $newTransId = "KGS_TID_" . Str::uuid()->toString();

                // Save into DB
                DB::table('grant_pilotschedule_one')
                    ->where('id', $b->id)
                    ->update([
                        'transaction_id' => $newTransId
                    ]);

                $b->transaction_id = $newTransId;
            }


            // Extract district
            $districtName = trim(explode('-', $b->school_district)[1] ?? '');

            // District ID
            $districtRow = DB::table('districts')
                ->where('name', $districtName)
                ->first();

            $districtId = $districtRow->id ?? 0;

            // Bank details
            $bank = DB::table('district_bank_accounts')
                ->where('district_id', $districtId)
                ->first();

            // 2️⃣ Build item
            $payloadItems[] = [
                "TransactionID"    => $b->transaction_id,
                "TransactionDate"  => Carbon::now()->format('Y-m-d\TH:i:s'),
                "RecipientID"      => Uuid::uuid4()->toString(),
                "RecipientType"    => "Beneficiary",
                "Gender"           => "Female",
                "FirstName"        => $b->school_emis ?? "",
                "LastName"         => $b->school_name ?? "",
                "MobileNumber"     => "",
                "Language"         => "English",
                "LanguageCode"     => "eng",
                "Country"          => "ZM",
                "PSP"              => $bank->bank_name ?? "Zanaco",
                "Province"         => $b->school_province ?? "",
                "District"         => $districtName ?? "",
                "Ward"             => "",
                "CWAC"             => $b->cwac_name ?? "",
                "RegisteredTown"   => "",
                "DistrictID"       => $districtId ?? "",
                "WardID"           => 0,
                "CWACID"           => $b->cwac_id ?? "",
                "HouseholdID"      => 0,
                "NRC"              => $b->hhh_nrc_number ?? "999999/99/1",
                "DateOfBirth"      => $b->dob ?? "",
                "AccountNumber"    => $b->bank_account ?? "",
                "AccountExtra"     => "",
                "Currency"         => "ZMW",
                "TransactionType"  => "Grant",
                "Amount"           => floatval($b->grant_amount_test),
                "GPSAccuracy"      => 0,
                "GPSAltitude"      => 0,
                "GPSLatitude"      => 0,
                "GPSLongitude"     => 0,
                "PaymentReference" => "",
                "PaymentCycle"     => "KGS 2025 Term 2 Payment"
            ];
        }

        return response()->json([
            "status"         => true,
            "records"        => count($payloadItems),
            "payload"        => [
                "TotalRecords" => count($payloadItems),
                "Transactions" => $payloadItems
            ]
        ]);
    }

    //below are the in use pg functions
    private function preparePGHeadersOld() //not in use
    {
        $username = 'zispis';
        $password = 'aquiwei5taelaijohn6shoopeeQuie7e';
        $apikey   = 'eecai6EKi7wohm8Uongaif7obuayoh1c';

        $auth = base64_encode("$username:$password");

        return [
            "Authorization" => "Basic $auth",
            "Content-Type"  => "application/json",
            "Accept"        => "application/json",
            "X-APIKey"      => $apikey,
            "User-Agent"    => "KGS-MIS/1.0"
        ];
    }

    private function buildPaymentPayloadSchoolsInternal()
    {
        $schools = DB::table('grant_pilotschedule_one')
            ->limit(1)
            ->get();

        if ($schools->isEmpty()) {
            return [
                "status" => false,
                "message" => "No schools found"
            ];
        }

        $items = [];

        foreach ($schools as $b) {

            // Create TID if missing
            if (empty($b->transaction_id)) {
                $newTid = "KGSTRIDT-" . Str::uuid()->toString();

                DB::table('grant_pilotschedule_one')
                    ->where('id', $b->id)
                    ->update([
                        'transaction_id' => $newTid
                    ]);

                $b->transaction_id = $newTid;
            }

            // extract district
            $districtName = trim(explode('-', $b->school_district)[1] ?? '');

            $districtRow = DB::table('districts')
                ->where('name', $districtName)
                ->first();

            $districtId = $districtRow->id ?? 0;

            $bank = DB::table('district_bank_accounts')
                ->where('district_id', $districtId)
                ->first();

            $items[] = [
                "TransactionID"    => $b->transaction_id,
                "TransactionDate"  => now()->format('Y-m-d\TH:i:s'),
                "RecipientID"      => Str::uuid()->toString(),
                "RecipientType"    => "Beneficiary",
                "Gender"           => "Female",
                "FirstName"        => $b->school_emis ?? "",
                "LastName"         => $b->school_name ?? "",
                "MobileNumber"     => "",
                "Language"         => "English",
                "LanguageCode"     => "eng",
                "Country"          => "ZM",
                "PSP"              => $bank->bank_name ?? "Zanaco",
                "Province"         => $b->school_province ?? "",
                "District"         => $districtName ?? "",
                "Ward"             => "",
                "CWAC"             => $b->cwac_name ?? "",
                "RegisteredTown"   => "",
                "DistrictID"       => $districtId ?? "",
                "WardID"           => 0,
                "CWACID"           => $b->cwac_id ?? "",
                "HouseholdID"      => 0,
                "NRC"              => "999999/99/1",
                "DateOfBirth"      => "",
                "AccountNumber"    => $b->bank_account ?? "",
                "AccountExtra"     => "",
                "Currency"         => "ZMW",
                "TransactionType"  => "Grant",
                "Amount"           => floatval($b->grant_amount_test),
                "GPSAccuracy"      => 0,
                "GPSAltitude"      => 0,
                "GPSLatitude"      => 0,
                "GPSLongitude"     => 0,
                "PaymentReference" => "",
                "PaymentCycle"     => "KGS 2025 Term 2 Payment"
            ];
        }

        return [
            "status"  => true,
            "payload" => [
                "TotalRecords" => count($items),
                "Transactions" => $items
            ]
        ];
    }

    public function submitPaymentToPGDebug(Request $request)
    {
        $refNo   = $request->payment_ref_no;
        $phase   = $request->payment_phase;
        $debug   = $request->debug == 1;

        // Step 1: Get school-based payload (internal)
        $payloadResult = $this->buildPaymentPayloadSchoolsInternal();

        if (!$payloadResult["status"]) {
            return response()->json($payloadResult, 404);
        }

        $payload = $payloadResult["payload"];

        // Step 2: Use VALID TID format
        $transactionID = $payload["Transactions"][0]["TransactionID"];

        // Step 3: URL MUST match TID exactly
        $url = "https://pg.zispis.gov.zm/sps/api/zispis/prod/kgs/payment/{$transactionID}";

        // Step 4: Headers
        $headers = $this->preparePGHeaders();

        if ($debug) {
            return response()->json([
                "debug" => true,
                "url" => $url,
                "tid" => $transactionID,
                "headers" => $headers,
                "payload" => $payload
            ]);
        }

        // No sending yet
    }

    //new apis
    private function generateTransactionId()
    {
        return "KGSTRIDT-" . \Ramsey\Uuid\Uuid::uuid4()->toString();
    }

    private function preparePGHeaders()
    {
        $username = 'zispis';
        $password = 'aquiwei5taelaijohn6shoopeeQuie7e';
        $apikey   = 'eecai6EKi7wohm8Uongaif7obuayoh1c';

        return [
            "Authorization" => "Basic " . base64_encode("$username:$password"),
            "Content-Type"  => "application/json",
            "Accept"        => "application/json",
            "X-APIKey"      => $apikey,
            "User-Agent"    => "KGS-MIS/1.0"
        ];
    }


    public function getNextSchoolForPaymentOld($limit = 1)
    {
        $schools = DB::table('grant_pilotschedule_one')
            ->whereNull('is_sent_to_pg')
            ->limit($limit)
            ->get();

        return response()->json([
            "status" => true,
            "count"  => $schools->count(),
            "data"   => $schools
        ]);
    }


    public function submitSinglePaymentToPGOld(Request $request)
    {
        $schoolId = $request->school_id;
        $debug    = $request->debug == 1;

        if (!$schoolId) {
            return response()->json(["status" => false, "message" => "school_id required"]);
        }

        // Fetch school
        $school = DB::table('grant_pilotschedule_one')
            ->where('school_id', $schoolId)
            ->first();

        if (!$school) {
            return response()->json([
                "status" => false,
                "message" => "School not found"
            ]);
        }

        // Build payload
        $payloadItem = $this->buildSingleSchoolPayload($school);

        // Transaction ID MUST match
        $tid = $payloadItem["TransactionID"];

        // PG URL
        $url = "https://pg.zispis.gov.zm/sps/api/zispis/prod/kgs/payment/{$tid}";

        // Headers
        $headers = $this->preparePGHeaders();

        // Log request
        $logId = DB::table('pg_payment_logs')->insertGetId([
            "school_id"        => $schoolId,
            "transaction_id"   => $tid,
            "request_url"      => $url,
            "request_payload"  => json_encode($payloadItem),
            "headers"          => json_encode($headers),
            "status"           => "pending",
            "created_at"       => now(),
            "updated_at"       => now()
        ]);

        // DEBUG MODE
        if ($debug) {
            return response()->json([
                "status"       => true,
                "debug_mode"   => true,
                "transaction_id" => $tid,
                "pg_url"         => $url,
                "pg_headers"     => $headers,
                "payload"        => $payloadItem
            ]);
        }

        // REAL PG SUBMISSION
        try {
            $client   = new \GuzzleHttp\Client(['verify' => false]);

            $response = $client->post($url, [
                'headers'     => $headers,
                'json'        => $payloadItem,
                'http_errors' => false
            ]);

            $status = $response->getStatusCode();
            $body   = $response->getBody()->getContents();

            DB::table('pg_payment_logs')
                ->where('id', $logId)
                ->update([
                    "http_status"   => $status,
                    "response_body" => $body,
                    "status"        => $status == 200 ? "success" : "failed",
                    "updated_at"    => now()
                ]);

            // If success → Mark as sent
            if ($status == 200) {
                DB::table('grant_pilotschedule_one')
                    ->where('school_id', $schoolId)
                    ->update(['is_sent_to_pg' => 1]);
            }

            return response()->json([
                "status"         => $status == 200,
                "transaction_id" => $tid,
                "pg_response"    => json_decode($body, true)
            ]);

        } catch (\Exception $e) {

            DB::table('pg_payment_logs')
                ->where('id', $logId)
                ->update([
                    "status"        => "failed",
                    "response_body" => $e->getMessage(),
                    "updated_at"    => now()
                ]);

            return response()->json([
                "status" => false,
                "error"  => $e->getMessage()
            ]);
        }
    }

    public function buildSingleSchoolPayload1($school)
    {
        // Generate TID if not present
        if (empty($school->transaction_id)) {
            $tid = "KGSTRIDT-" . Str::uuid()->toString();

            DB::table('grant_pilotschedule_one')
                ->where('id', $school->id)
                ->update([
                    'transaction_id' => $tid
                ]);

            $school->transaction_id = $tid;
        }

        // Extract district
        $districtName = trim(explode('-', $school->school_district)[1] ?? '');

        $districtRow = DB::table('districts')
            ->where('name', $districtName)
            ->first();

        $districtId = $districtRow->id ?? 0;

        // Bank
        $bank = DB::table('district_bank_accounts')
            ->where('district_id', $districtId)
            ->first();

        // Final payload item
        return [
            "TransactionID"    => $school->transaction_id,
            "TransactionDate"  => now()->format('Y-m-d\TH:i:s'),
            "RecipientID"      => Str::uuid()->toString(),
            "RecipientType"    => "Beneficiary",
            "Gender"           => "Female",
            "FirstName"        => $school->school_emis,
            "LastName"         => $school->school_name,
            "MobileNumber"     => "0",
            "Language"         => "English",
            "LanguageCode"     => "eng",
            "Country"          => "ZM",
            "PSP"              => $bank->bank_name ?? "Zanaco",
            "Province"         => $school->school_province,
            "District"         => $districtName,
            "Ward"             => "0",
            "CWAC"             => $school->cwac_name,
            "RegisteredTown"   => "0",
            "DistrictID"       => $districtId,
            "WardID"           => 0,
            "CWACID"           => $school->cwac_id,
            "HouseholdID"      => 0,
            "NRC"              => "999999/99/1",
            "DateOfBirth"      => "0",
            "AccountNumber"    => $school->bank_account,
            "AccountExtra"     => "0",
            "Currency"         => "ZMW",
            "TransactionType"  => "Grant",
            "Amount"           => floatval($school->grant_amount_test),
            "GPSAccuracy"      => 0,
            "GPSAltitude"      => 0,
            "GPSLatitude"      => 0,
            "GPSLongitude"     => 0,
            "PaymentReference" => "0",
            "PaymentCycle"     => "KGS 2025 Term 2 Payment"
        ];
    }

    public function regenerateTransactionIds()
    {
        // Only regenerate for schools in district 66
        $rows = DB::table('grant_pilotschedule_one')
            ->where('district_id', 66)
            ->get();

        $updated = 0;

        foreach ($rows as $r) {

            $uuid = Str::uuid()->toString();
            $shortUuid = substr($uuid, 0, 30);
            $newTid = "KGSTR-" . $shortUuid;

            DB::table('grant_pilotschedule_one')
                ->where('id', $r->id)
                ->update(['transaction_id' => $newTid]);

            $updated++;
        }

        return response()->json([
            "status"  => true,
            "updated" => $updated,
            "message" => "Transaction IDs regenerated for all records where district_id = 66."
        ]);
    }



    //final working functions
    public function processAllSchoolsForPGOld()
    {
        $batchSize = 1; // process 1 at a time (required by PG)

        while (true) {

            // Fetch next unsent school
            $school = DB::table('grant_pilotschedule_one')
                ->where('is_sent_to_pg', 0)
                ->orderBy('school_id')
                ->first();

            if (!$school) {
                return response()->json([
                    "status" => true,
                    "message" => "All schools have been processed!"
                ]);
            }

            // Build payload
            $payloadItem = $this->buildSingleSchoolPayload($school);

            // Must match URL TID
            $tid = $payloadItem["TransactionID"];
            $url = "https://pg.zispis.gov.zm/sps/api/zispis/prod/kgs/payment/{$tid}";
            $headers = $this->preparePGHeaders();

            // Log request
            $logId = DB::table('pg_payment_logs')->insertGetId([
                "school_id"        => $school->school_id,
                "transaction_id"   => $tid,
                "request_url"      => $url,
                "request_payload"  => json_encode($payloadItem),
                "headers"          => json_encode($headers),
                "status"           => "pending",
                "created_at"       => now(),
                "updated_at"       => now()
            ]);

            // Send to PG
            try {
                $client = new \GuzzleHttp\Client(['verify' => false]);

                $response = $client->post($url, [
                    'headers' => $headers,
                    'json'    => $payloadItem,
                    'http_errors' => false
                ]);

                $status = $response->getStatusCode();
                $body   = $response->getBody()->getContents();

                // Update log
                DB::table('pg_payment_logs')
                    ->where('id', $logId)
                    ->update([
                        "http_status"   => $status,
                        "response_body" => $body,
                        "status"        => $status == 200 ? "success" : "failed",
                        "updated_at"    => now()
                    ]);

                // Mark as sent only if PG accepts
                if ($status == 200) {
                    DB::table('grant_pilotschedule_one')
                        ->where('id', $school->id)
                        ->update(['is_sent_to_pg' => 1]);
                }

            } catch (\Exception $e) {

                DB::table('pg_payment_logs')
                    ->where('id', $logId)
                    ->update([
                        "status"        => "failed",
                        "response_body" => $e->getMessage(),
                        "updated_at"    => now()
                    ]);

                return response()->json([
                    "status" => false,
                    "error"  => $e->getMessage()
                ]);
            }
        }
    }
    public function processAllSchoolsForPG(Request $request)
    {

        $payment_ref_no = $request->payment_ref_no;
        $payment_phase  = $request->payment_phase;

        if (!$payment_ref_no || !$payment_phase) {
            return response()->json([
                "status"  => false,
                "message" => "payment_ref_no and payment_phase are required."
            ], 400);
        }

        $successCount = 0;
        $failedCount  = 0;
        $processed    = 0;

        while (true) {

            // Fetch next unsent school
            $school = DB::table('grant_pilotschedule_one')
                ->where('is_sent_to_pg', 0)
                ->where('payment_phase', $payment_phase)    
                ->where('payment_ref_no', $payment_ref_no)
                ->orderBy('school_id')
                ->first();

            if (!$school) {
                // No more records — return summary
                return response()->json([
                    "status"        => true,
                    "message"       => "Processing completed.",
                    "processed"     => $processed,
                    "success"       => $successCount,
                    "failed"        => $failedCount,
                    "remaining"     => DB::table('grant_pilotschedule_one')
                                        ->where('is_sent_to_pg', 0)
                                        ->where('payment_phase', $payment_phase)
                                        ->where('payment_ref_no', $payment_ref_no)
                                        ->count()
                ]);
            }

            // Build the payload
            $payloadItem = $this->buildSingleSchoolPayload($school);

            // TID must match
            $tid     = $payloadItem["TransactionID"];
            $url     = "https://pg.zispis.gov.zm/sps/api/zispis/prod/kgs/payment/{$tid}";
            $headers = $this->preparePGHeaders();

            // Log request
            $logId = DB::table('pg_payment_logs')->insertGetId([
                "payment_ref_no"   => $payment_ref_no,
                "payment_phase"    => $payment_phase,
                "school_id"        => $school->school_id,
                "transaction_id"   => $tid,
                "request_url"      => $url,
                "request_payload"  => json_encode($payloadItem),
                "headers"          => json_encode($headers),
                "status"           => "pending",
                "created_at"       => now(),
                "updated_at"       => now()
            ]);

            $processed++;

            try {

                $client = new \GuzzleHttp\Client(['verify' => false]);

                $response = $client->post($url, [
                    'headers'     => $headers,
                    'json'        => $payloadItem,
                    'http_errors' => false
                ]);

                $status = $response->getStatusCode();
                $body   = $response->getBody()->getContents();

                // Update log
                DB::table('pg_payment_logs')
                    ->where('id', $logId)
                    ->update([
                        "http_status"   => $status,
                        "response_body" => $body,
                        "status"        => $status == 200 ? "success" : "failed",
                        "updated_at"    => now()
                    ]);

                if ($status == 200) {
                    // SUCCESS
                    DB::table('grant_pilotschedule_one')
                        ->where('id', $school->id)
                        ->update(['is_sent_to_pg' => 1]);

                    $successCount++;

                } else {
                    // FAILED — still mark as processed (so it won't loop infinitely)
                    DB::table('grant_pilotschedule_one')
                        ->where('id', $school->id)
                        ->update(['is_sent_to_pg' => 2]);  // 2 = failed but processed

                    $failedCount++;
                }

            } catch (\Exception $e) {

                // Log error
                DB::table('pg_payment_logs')
                    ->where('id', $logId)
                    ->update([
                        "status"        => "error",
                        "response_body" => $e->getMessage(),
                        "updated_at"    => now()
                    ]);

                // Mark school as processed but failed
                DB::table('grant_pilotschedule_one')
                    ->where('id', $school->id)
                    ->update(['is_sent_to_pg' => 2]);

                $failedCount++;
            }
        }
    }
    private function buildSingleSchoolPayload($school)
    {
        // Ensure the DB has a TID for tracking
        if (empty($school->transaction_id)) {
            $tid = "KGSTRIDT-" . Str::uuid()->toString();

            DB::table('grant_pilotschedule_one')
                ->where('id', $school->id)
                ->update(['transaction_id' => $tid]);

            $school->transaction_id = $tid;
        }

        // Extract district name (e.g. "1011-Chisamba" → "Chisamba")
        $districtName = trim(explode("-", $school->school_district)[1] ?? '');

        // Fetch district ID
        $districtRow = DB::table('districts')->where('name', $districtName)->first();
        $districtId  = $districtRow->id ?? 0;

        // Fetch bank details for district
        $bank = DB::table('district_bank_accounts')
            ->where('district_id', $districtId)
            ->first();

        // Build PG payload EXACTLY as required
        return [
            "TransactionID"    => $school->transaction_id,
            "TransactionDate"  => now()->format('Y-m-d\TH:i:s'),
            "RecipientID"      => Str::uuid()->toString(),
            "RecipientType"    => "Beneficiary",
            "Gender"           => "Female",
            "FirstName"        => $school->school_emis ?? "0",
            "LastName"         => $school->school_name ?? "0",
            "MobileNumber"     => "",
            "Language"         => "English",
            "LanguageCode"     => "eng",
            "Country"          => "ZM",
            "PSP"              => "Zanaco",
            "Province"         => $school->school_province ?? "0",
            "District"         => $districtName,
            "Ward"             => "0",
            "CWAC"             => $school->cwac_name ?? "0",
            "RegisteredTown"   => "0",
            "DistrictID"       => $districtId,
            "WardID"           => 0,
            "CWACID"           => $school->cwac_id ?? 0,
            "HouseholdID"      => 0,
            "NRC"              => "999999/99/1",
            "DateOfBirth"      => "0",
            "AccountNumber"    => $school->bank_account ?? "",
            "AccountExtra"     => $school->bank_name ?? "ZANACO",
            "Currency"         => "ZMW",
            "TransactionType"  => "Grant",
            "Amount"           => floatval($school->grant_amount),
            "GPSAccuracy"      => 0,
            "GPSAltitude"      => 0,
            "GPSLatitude"      => 0,
            "GPSLongitude"     => 0,
            "PaymentReference" => "[UNDELIVERED][UNDELIVERED][UNDELIVERED]Manual payment",
            "PaymentCycle"     => "KGS 2025 Term 2 Payment"
        ];
    }
    public function getNextSchoolForPayment()
    {
        $school = DB::table('grant_pilotschedule_one')
            ->whereNull('is_sent_to_pg')
            ->orderBy('id')
            ->first();

        return response()->json([
            "status" => true,
            "data"   => $school
        ]);
    }
    public function submitSinglePaymentToPG()
    {
        $school = DB::table('grant_pilotschedule_one')
            ->whereNull('is_sent_to_pg')
            ->orderBy('id')
            ->first();

        if (!$school) {
            return response()->json([
                "status" => false,
                "message" => "No unsent schools found"
            ]);
        }

        return $this->submitSchoolToPG($school);
    }
    private function submitSchoolToPG($school)
    {
        $payloadItem = $this->buildSingleSchoolPayload($school);
        $tid = $payloadItem["TransactionID"];
        $url = "https://pg.zispis.gov.zm/sps/api/zispis/prod/kgs/payment/{$tid}";
        $headers = $this->preparePGHeaders();

        $logId = DB::table('pg_payment_logs')->insertGetId([
            "school_id"        => $school->school_id,
            "transaction_id"   => $tid,
            "request_url"      => $url,
            "request_payload"  => json_encode($payloadItem),
            "headers"          => json_encode($headers),
            "status"           => "pending",
            "created_at"       => now(),
            "updated_at"       => now()
        ]);

        try {
            $client = new \GuzzleHttp\Client(['verify' => false]);

            $response = $client->post($url, [
                'headers' => $headers,
                'json'    => $payloadItem,
                'http_errors' => false
            ]);

            $status = $response->getStatusCode();
            $body   = $response->getBody()->getContents();

            DB::table('pg_payment_logs')
                ->where('id', $logId)
                ->update([
                    "http_status"   => $status,
                    "response_body" => $body,
                    "status"        => $status == 200 ? "success" : "failed",
                    "updated_at"    => now()
                ]);

            if ($status == 200) {
                DB::table('grant_pilotschedule_one')
                    ->where('id', $school->id)
                    ->update(['is_sent_to_pg' => 1]);
            }

            return response()->json([
                "status"         => $status == 200,
                "transaction_id" => $tid,
                "pg_response"    => json_decode($body, true)
            ]);

        } catch (\Exception $e) {

            DB::table('pg_payment_logs')
                ->where('id', $logId)
                ->update([
                    "status"        => "failed",
                    "response_body" => $e->getMessage(),
                    "updated_at"    => now()
                ]);

            return response()->json([
                "status" => false,
                "error"  => $e->getMessage()
            ]);
        }
    }
    //retry single school payment
    public function retrySingleSchoolPayment(Request $request)
    {
        $school_id      = $request->school_id;
        $payment_ref_no = $request->payment_ref_no;
        $payment_phase  = $request->payment_phase;

        if (!$school_id || !$payment_ref_no || !$payment_phase) {
            return response()->json([
                "status"  => false,
                "message" => "school_id, payment_ref_no and payment_phase are required."
            ], 400);
        }

        // Fetch the school record
        $school = DB::table('grant_pilotschedule_one')
            ->where('school_id', $school_id)
            ->where('payment_ref_no', $payment_ref_no)
            ->where('payment_phase', $payment_phase)
            ->first();

        if (!$school) {
            return response()->json([
                "status"  => false,
                "message" => "School record not found."
            ], 404);
        }

        // Build PG payload (creates TID if missing)
        $payloadItem = $this->buildSingleSchoolPayload($school);
        // dd($payloadItem);
        $tid         = $payloadItem["TransactionID"];

        $url     = "https://pg.zispis.gov.zm/sps/api/zispis/prod/kgs/payment/{$tid}";
        $headers = $this->preparePGHeaders();

        // Log before sending
        $logId = DB::table('pg_payment_logs')->insertGetId([
            "payment_ref_no"   => $payment_ref_no,
            "payment_phase"    => $payment_phase,
            "school_id"        => $school->school_id,
            "transaction_id"   => $tid,
            "request_url"      => $url,
            "request_payload"  => json_encode($payloadItem),
            "headers"          => json_encode($headers),
            "status"           => "pending",
            "created_at"       => now(),
            "updated_at"       => now()
        ]);

        try {
            $client = new \GuzzleHttp\Client(['verify' => false]);

            $response = $client->post($url, [
                'headers'     => $headers,
                'json'        => $payloadItem,
                'http_errors' => false
            ]);

            $body = $response->getBody()->getContents();
            $json = json_decode($body, true);

            // Extract PG result code
            $resultCode = $json['ResultCode'] ?? null;

            // Determine if successful
            $isSuccess = ($resultCode == 100);

            // Update payment log
            DB::table('pg_payment_logs')
                ->where('id', $logId)
                ->update([
                    "http_status"   => $resultCode,     // store PG ResultCode
                    "response_body" => $body,
                    "status"        => $isSuccess ? "success" : "failed",
                    "updated_at"    => now()
                ]);

            // Mark school as processed
            DB::table('grant_pilotschedule_one')
                ->where('id', $school->id)
                ->update([
                    'is_sent_to_pg' => $isSuccess ? 1 : 2
                ]);

            return response()->json([
                "status"         => $isSuccess,
                "result_code"    => $resultCode,
                "transaction_id" => $tid,
                "pg_response"    => $json
            ]);

        } catch (\Exception $e) {

            // Log exception
            DB::table('pg_payment_logs')
                ->where('id', $logId)
                ->update([
                    "status"        => "failed",
                    "response_body" => $e->getMessage(),
                    "updated_at"    => now()
                ]);

            // Mark school as failed
            DB::table('grant_pilotschedule_one')
                ->where('id', $school->id)
                ->update(['is_sent_to_pg' => 2]);

            return response()->json([
                "status"       => false,
                "error"        => $e->getMessage(),
                "transaction_id" => $tid
            ]);
        }
    }

    //transaction statuses
    public function pgLogsList(Request $request)
    {
        $query = DB::table('pg_payment_logs as t')
            ->leftJoin('school_information as s', 's.id', '=', 't.school_id')
            ->select(
                't.id',
                't.transaction_id',
                't.payment_ref_no',
                't.payment_phase',
                't.school_id',
                's.name as school_name',
                't.http_status',
                't.status as pg_status',
                't.created_at'
            );

        // ─────────────────────────────────────────────
        // Apply filters
        // ─────────────────────────────────────────────
        if ($request->payment_ref_no) {
            $query->where('t.payment_ref_no', $request->payment_ref_no);
        }

        if ($request->payment_phase) {
            $query->where('t.payment_phase', $request->payment_phase);
        }

        if ($request->school_id) {
            $query->where('t.school_id', $request->school_id);
        }

        if ($request->status) {
            $query->where('t.status', $request->status);
        }

        if ($request->from_date) {
            $query->whereDate('t.created_at', '>=', $request->from_date);
        }

        if ($request->to_date) {
            $query->whereDate('t.created_at', '<=', $request->to_date);
        }

        // General search (school name + transaction id)
        if ($request->search) {
            $search = '%'.$request->search.'%';

            $query->where(function ($q) use ($search) {
                $q->where('t.transaction_id', 'like', $search)
                  ->orWhere('s.name', 'like', $search);
            });
        }

        $query->orderBy('t.created_at', 'desc');

        $rows = $query->paginate(50); // smooth pagination

        return response()->json([
            "status" => true,
            "count" => $rows->total(),
            "data" => $rows
        ]);
    }
    public function pgLogsDetails($transaction_id)
    {
        $row = DB::table('pg_payment_logs as t')
            ->leftJoin('school_information as s', 's.id', '=', 't.school_id')
            ->select(
                't.id',
                't.transaction_id',
                't.payment_ref_no',
                't.payment_phase',
                't.school_id',
                's.name as school_name',
                't.http_status',
                't.status as pg_status',
                't.request_url',
                't.request_payload',
                't.response_body',
                't.headers',
                't.created_at',
                't.updated_at'
            )
            ->where('t.transaction_id', $transaction_id)
            ->first();

        if (!$row) {
            return response()->json([
                "status" => false,
                "message" => "Transaction not found"
            ], 404);
        }

        return response()->json([
            "status" => true,
            "transaction" => $row
        ]);
    }

    public function getFailedPayments(Request $request)
    {
        $latestLogs = DB::table('pg_payment_logs')
            ->select(
                'school_id',
                'payment_ref_no',
                'payment_phase',
                DB::raw('MAX(updated_at) as latest_time')
            )
            ->groupBy('school_id', 'payment_ref_no', 'payment_phase');

        $query = DB::table('pg_payment_logs as t')
            ->joinSub($latestLogs, 'l', function ($join) {
                $join->on('t.school_id', '=', 'l.school_id')
                    ->on('t.payment_ref_no', '=', 'l.payment_ref_no')
                    ->on('t.payment_phase', '=', 'l.payment_phase')
                    ->on('t.updated_at', '=', 'l.latest_time');
            })
            ->leftJoin('school_information as s', 's.id', '=', 't.school_id')
            ->leftJoin('grant_pilotschedule_one as g', 'g.school_id', '=', 't.school_id')
            ->select(
                't.id',
                't.transaction_id',
                't.payment_ref_no',
                't.payment_phase',
                't.school_id',
                's.name as school_name',
                DB::raw("TRIM(SUBSTRING_INDEX(g.school_district, '-', -1)) as district_name"),
                'g.grant_amount',   // ← NEW FIELD
                't.http_status as result_code',
                't.status as pg_status',
                't.response_body',
                't.created_at'
            )
            ->where('t.status', 'failed');



        if ($request->payment_ref_no) {
            $query->where('t.payment_ref_no', $request->payment_ref_no);
        }

        if ($request->payment_phase) {
            $query->where('t.payment_phase', $request->payment_phase);
        }

        if ($request->school_id) {
            $query->where('t.school_id', $request->school_id);
        }

        if ($request->from_date) {
            $query->whereDate('t.created_at', '>=', $request->from_date);
        }

        if ($request->to_date) {
            $query->whereDate('t.created_at', '<=', $request->to_date);
        }

        if ($request->search) {
            $search = "%" . $request->search . "%";

            $query->where(function ($q) use ($search) {
                $q->where('t.transaction_id', 'like', $search)
                ->orWhere('t.payment_ref_no', 'like', $search)
                ->orWhere('s.name', 'like', $search);
            });
        }

        $query->orderBy('t.created_at', 'desc');


        $rows = $query->paginate(50);


        $rows->getCollection()->transform(function ($item) {

            $json = json_decode($item->response_body, true);

            $item->result_description = $json['ResultDescription'] ?? null;
            $item->result_details     = $json['ResultDetails'] ?? null;

            return $item;
        });

        return response()->json([
            "status" => true,
            "count"  => $rows->total(),
            "data"   => $rows
        ]);
    }

    // sa_app_submissions
    public function getSchoolTransactionSummaryV1()
    {
        $rows = DB::table('beneficiary_transaction_status as t1')
            ->join('sa_app_beneficiary_list_3 as t2', 't2.beneficiary_no', '=', 't1.beneficiary_no')
            ->where('t1.date_received', '>', '2025-12-01')
            ->select(
                't1.*',
                't2.beneficiary_id',
                't2.beneficiary_no',
                't2.first_name',
                't2.last_name',
                't2.grant_amount',
                't2.hhh_fname',
                't2.hhh_lname',
                't2.hhh_nrc_number',
                't2.school_id'
            )
            ->get();

        if ($rows->isEmpty()) {
            return response()->json([
                "status" => false,
                "message" => "No records found after filtering by date_received > 2025-12-01"
            ], 404);
        }

        $groupedBySchool = $rows->groupBy('school_id');

        $schoolsArray = [];

        foreach ($groupedBySchool as $schoolId => $beneficiaries) {

            // Fetch t3 school info
            $school = DB::table('school_information')->where('id', $schoolId)->first();
            if (!$school) continue;

            // Fetch t4 district info
            $district = DB::table('districts')->where('id', $school->district_id)->first();

            // Prepare beneficiaries
            $beneficiaryList = [];

            foreach ($beneficiaries as $b) {
                $beneficiaryList[] = [
                    "beneficiary_id"            => $b->beneficiary_id,
                    "beneficiary_no"            => $b->beneficiary_no,
                    "grant_amount"              => $b->grant_amount,
                    "first_name"                => $b->first_name,
                    "last_name"                 => $b->last_name,
                    "hhh_fname"                 => $b->hhh_fname,
                    "hhh_lname"                 => $b->hhh_lname,
                    "hhh_nrc_number"            => $b->hhh_nrc_number,
                    "payment_status"            => $b->payment_status,
                    "images"                    => $b->images,
                    "school_accountant_details" => $b->school_accountant_details,
                    "gps_latitude"              => $b->gps_latitude,
                    "gps_longitude"             => $b->gps_longitude,
                    "gps_altitude"              => $b->gps_altitude,
                    "date_received"             => $b->date_received
                ];
            }

            $schoolsArray[] = [
                "school_name"         => $school->name,
                "school_district"     => $district->name ?? '',
                "school_emis"         => $school->code,
                "total_beneficiaries" => count($beneficiaryList),
                "beneficiaries"       => $beneficiaryList
            ];
        }

        return response()->json([
            "status"        => true,
            "total_schools" => count($schoolsArray),
            "schools"       => $schoolsArray
        ]);
    }

    public function getSchoolTransactionSummary()
    {
        /**
         * STEP 1:
         * Fetch transaction records AFTER the given date.
         *
         * IMPORTANT (SAFE FIX):
         * ---------------------
         * Transactions (t1) may reference beneficiaries that exist in:
         *  - sa_app_beneficiary_list_3 (older snapshot)
         *  - sa_app_beneficiary_list_4 (newer snapshot)
         *
         * To avoid LOSING valid transactions, we:
         *  - LEFT JOIN both beneficiary tables
         *  - Use COALESCE to pick school_id from whichever table has the record
         *
         * This ensures:
         *  - No transaction is dropped
         *  - Versioned beneficiary tables do NOT leak into business logic
         */

        $rows = DB::table('beneficiary_transaction_status as t1')

            // Older beneficiary snapshot
            ->leftJoin(
                'sa_app_beneficiary_list_3 as b3',
                'b3.beneficiary_no',
                '=',
                't1.beneficiary_no'
            )

            // Newer beneficiary snapshot
            ->leftJoin(
                'sa_app_beneficiary_list_4 as b4',
                'b4.beneficiary_no',
                '=',
                't1.beneficiary_no'
            )

            ->where('t1.date_received', '>', '2025-12-01')

            ->select(
                't1.*',

                // Beneficiary identity (prefer b3, fallback to b4)
                DB::raw('COALESCE(b3.beneficiary_id, b4.beneficiary_id) AS beneficiary_id'),
                DB::raw('COALESCE(b3.beneficiary_no, b4.beneficiary_no) AS beneficiary_no'),
                DB::raw('COALESCE(b3.first_name, b4.first_name) AS first_name'),
                DB::raw('COALESCE(b3.last_name, b4.last_name) AS last_name'),
                DB::raw('COALESCE(b3.grant_amount, b4.grant_amount) AS grant_amount'),
                DB::raw('COALESCE(b3.hhh_fname, b4.hhh_fname) AS hhh_fname'),
                DB::raw('COALESCE(b3.hhh_lname, b4.hhh_lname) AS hhh_lname'),
                DB::raw('COALESCE(b3.hhh_nrc_number, b4.hhh_nrc_number) AS hhh_nrc_number'),

                // CRITICAL FIX: school_id resolved safely across versions
                DB::raw('COALESCE(b3.school_id, b4.school_id) AS school_id')
            )
            ->get();

        if ($rows->isEmpty()) {
            return response()->json([
                "status"  => false,
                "message" => "No records found after filtering by date_received > 2025-12-01"
            ], 404);
        }

        /**
         * STEP 2:
         * Group beneficiaries by resolved school_id
         */
        $groupedBySchool = $rows->groupBy('school_id');

        $schoolsArray = [];

        foreach ($groupedBySchool as $schoolId => $beneficiaries) {

            // Fetch school info
            $school = DB::table('school_information')->where('id', $schoolId)->first();
            if (!$school) continue;

            // Fetch district info
            $district = DB::table('districts')->where('id', $school->district_id)->first();

            $beneficiaryList = [];

            foreach ($beneficiaries as $b) {
                $beneficiaryList[] = [
                    "beneficiary_id"            => $b->beneficiary_id,
                    "beneficiary_no"            => $b->beneficiary_no,
                    "grant_amount"              => $b->grant_amount,
                    "first_name"                => $b->first_name,
                    "last_name"                 => $b->last_name,
                    "hhh_fname"                 => $b->hhh_fname,
                    "hhh_lname"                 => $b->hhh_lname,
                    "hhh_nrc_number"            => $b->hhh_nrc_number,
                    "payment_status"            => $b->payment_status,
                    "images"                    => $b->images,
                    "school_accountant_details" => $b->school_accountant_details,
                    "gps_latitude"              => $b->gps_latitude,
                    "gps_longitude"             => $b->gps_longitude,
                    "gps_altitude"              => $b->gps_altitude,
                    "date_received"             => $b->date_received
                ];
            }

            $schoolsArray[] = [
                "school_name"         => $school->name,
                "school_district"     => $district->name ?? '',
                "school_emis"         => $school->code,
                "total_beneficiaries" => count($beneficiaryList),
                "beneficiaries"       => $beneficiaryList
            ];
        }

        return response()->json([
            "status"        => true,
            "total_schools" => count($schoolsArray),
            "schools"       => $schoolsArray
        ]);
    }


    public function getImages(Request $request)
    {
        $uuidsRaw = $request->query('uuids');
        
        if (!$uuidsRaw) {
            return response()->json([
                'status' => false,
                'message' => 'No UUIDs provided.'
            ], 400);
        }

        // Convert to array
        $uuidList = array_filter(array_map('trim', explode(',', $uuidsRaw)));

        // Clean out invalid entries
        $invalidValues = ["", "n/a", "na", "none", "null", "undefined"];

        $uuidList = array_filter($uuidList, function ($val) use ($invalidValues) {
            return !in_array(strtolower($val), $invalidValues);
        });

        if (empty($uuidList)) {
            return response()->json([
                'status' => true,
                'images' => []
            ]);
        }

        // Query table
        $rows = DB::table('sa_app_beneficiary_images')
            ->whereIn('image_id', $uuidList)
            ->get();

        return response()->json([
            'status' => true,
            'count' => $rows->count(),
            'images' => $rows
        ]);
    }

    public function generateSchoolPaymentBatches()
    {
        try {
            set_time_limit(0);

            $year = date('Y');
            $month = date('m');

            $startTime = microtime(true);

            // get schools with null batch
            $schools = DB::table('sa_app_beneficiary_list_4')
                ->select('school_id', 'school_name', 'school_district')
                ->whereNull('sch_pay_bat_id')
                ->groupBy('school_id', 'school_name', 'school_district')
                ->get();

            if ($schools->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No schools found with null batch id'
                ]);
            }

            $updatedSchools = 0;

            foreach ($schools as $school) {

                // clean school name (remove "362 - ")
                $schoolName = trim(preg_replace('/^\d+\s*-\s*/', '', $school->school_name));

                // clean district name (remove "18 - ")
                $districtName = trim(preg_replace('/^\d+\s*-\s*/', '', $school->school_district));

                // normalize names → replace spaces with hyphens, uppercase
                $schoolName = strtoupper(str_replace(' ', '-', $schoolName));
                $districtName = strtoupper(str_replace(' ', '-', $districtName));

                // generate 12-char unique string
                $unique = strtoupper(substr(bin2hex(random_bytes(6)), 0, 12));

                // final batch id
                $batchId = $school->school_id
                    . '---' . $schoolName
                    . '---' . $districtName
                    . '-' . $year
                    . '-' . $month
                    . '-' . $unique;

                // update all rows for that school
                $affected = DB::table('sa_app_beneficiary_list_4')
                    ->where('school_id', $school->school_id)
                    ->whereNull('sch_pay_bat_id')
                    ->update([
                        'sch_pay_bat_id' => $batchId
                    ]);

                if ($affected > 0) {
                    $updatedSchools++;
                }
            }

            $endTime = microtime(true);

            return response()->json([
                'success' => true,
                'message' => 'Batch IDs generated successfully',
                'schools_updated' => $updatedSchools,
                'time_seconds' => round($endTime - $startTime, 2)
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error generating batch IDs',
                'error' => $e->getMessage()
            ]);
        }
    }

}
