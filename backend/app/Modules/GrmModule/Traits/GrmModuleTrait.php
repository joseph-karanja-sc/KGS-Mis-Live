<?php
/**
 * Created by PhpStorm.
 * User: Kip
 * Date: 10/24/2019
 * Time: 1:02 PM
 */

namespace app\Modules\GrmModule\Traits;

use App\Jobs\ComplaintSubmissionEmailJob;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait GrmModuleTrait
{

    public function complaintSubmissionEmailNotification($record_details, $responsible_user_id, $prev_stage_id, $action_id, $curr_stage_id)
    {
        $programme_type_id = $record_details->programme_type_id;
        $submission_email = getSingleRecordColValue('grm_complaint_submission_emails', array('programme_type_id' => $programme_type_id, 'is_active' => 1), 'email_address');
        $program_name = getSingleRecordColValue('programme_types', array('id' => $programme_type_id), 'name');
        $responsible_user_email = aes_decrypt(getSingleRecordColValue('users', array('id' => $responsible_user_id), 'email'));

        $prev_stage = aes_decrypt(getSingleRecordColValue('wf_workflow_stages', array('id' => $prev_stage_id), 'name'));
        $action = aes_decrypt(getSingleRecordColValue('wf_workflow_actions', array('id' => $action_id), 'name'));
        $curr_stage = aes_decrypt(getSingleRecordColValue('wf_workflow_stages', array('id' => $curr_stage_id), 'name'));

        $vars = array(
            '{complaint_refno}' => $record_details->reference_no,
            '{prev_stage}' => $prev_stage,
            '{user_action}' => $action,
            '{next_stage}' => $curr_stage,
            '{submission_user}' => $record_details->reference_no,
            '{today_date}' => converter22(Carbon::now()),
            '{author}' => aes_decrypt(Auth::user()->first_name) . ' ' . aes_decrypt(Auth::user()->last_name)
        );
        //email
        if (is_connected()) {
            $emailTemplateInfo = getEmailTemplateInfo(1, $vars);
            $cc_array = array(
                $responsible_user_email
            );
            $emailJob = (new ComplaintSubmissionEmailJob($submission_email, $emailTemplateInfo->subject, $emailTemplateInfo->body, $program_name, $cc_array))->delay(Carbon::now()->addSeconds(10));
            dispatch($emailJob);
        }
    }

    public function grievanceEscalationEmailNotification($programme_type_id, $province_id, $complaintFormNo)
    {
        //HQ emails
        $priHQEmails = '';
        $ccHQEmails = '';
        $hqDetails = DB::table('grm_emailnotifications_setup')
            ->where('gewel_programme_id', $programme_type_id)
            ->where('level', 2)
            ->first();
        if ($hqDetails) {
            $priHQEmails = $hqDetails->primary_email;
            $ccHQEmails = $hqDetails->cc_email;
        }
        $priHQEmailsArr = explode(',', $priHQEmails);
        $ccHQEmailsArr = explode(',', $ccHQEmails);

        //Province emails
        $priProvEmails = '';
        $ccProvEmails = '';
        $provDetails = DB::table('grm_emailnotifications_setup')
            ->where('gewel_programme_id', $programme_type_id)
            ->where('level', 3)
            ->where('province_id', $province_id)
            ->first();
        if ($provDetails) {
            $priProvEmails = $provDetails->primary_email;
            $ccProvEmails = $provDetails->cc_email;
        }
        $priProvEmailsArr = explode(',', $priProvEmails);
        $ccProvEmailsArr = explode(',', $ccProvEmails);

        $toEmails = array_filter(array_merge($priHQEmailsArr, $priProvEmailsArr));
        $ccEmails = array_filter(array_merge($ccHQEmailsArr, $ccProvEmailsArr));

        //$submission_email = getSingleRecordColValue('grm_gewel_programmes', array('id' => $programme_type_id), 'notifications_email');
        $program_name = getSingleRecordColValue('grm_gewel_programmes', array('id' => $programme_type_id), 'name');

        $vars = array(
            '{complaintFormNo}' => $complaintFormNo
        );
        //email
        $emailTemplateInfo = getEmailTemplateInfo(3, $vars);
        if (is_connected()) {
            $emailJob = (new ComplaintSubmissionEmailJob($toEmails, $emailTemplateInfo->subject, $emailTemplateInfo->body, $program_name, $ccEmails))->delay(Carbon::now()->addSeconds(10));
            dispatch($emailJob);
        } else {
            $params = array(
                'email_to' => implode(',', $toEmails),
                'cc_to' => implode(',', $ccEmails),
                'subject' => $emailTemplateInfo->subject,
                'body' => $emailTemplateInfo->body,
                'exception' => 'No internet connection',
                'created_at' => Carbon::now()
            );
            DB::table('tra_failed_emails')
                ->insert($params);
        }
    }

    public function grievanceReferralEmailNotification($complaint_id, Request $request, $programme_type_id)
    {
        $submission_emails = getSingleRecordColValue('grm_nongewel_programmes', array('id' => $programme_type_id), 'notifications_email');
        $program_name = '';
        $toEmails = explode(',', $submission_emails);
        $provinceName = getSingleRecordColValue('provinces', array('id' => $request->input('province_id')), 'name');
        $districtName = getSingleRecordColValue('districts', array('id' => $request->input('district_id')), 'name');
        $cwacName = getSingleRecordColValue('cwac', array('id' => $request->input('cwac_id')), 'name');
        $complainantName = $request->input('complainant_first_name') . ' ' . $request->input('complainant_last_name');

        $vars = array(
            '{complaintFormNo}' => $request->input('complaint_form_no'),
            '{collectionDate}' => converter22($request->input('complaint_collection_date')),
            '{complainantName}' => $complainantName,
            '{complainantNRC}' => $request->input('complainant_nrc'),
            '{complainantMobile}' => $request->input('complainant_mobile'),
            '{provinceName}' => $provinceName,
            '{districtName}' => $districtName,
            '{cwacName}' => $cwacName,
            '{villageName}' => $request->input('complainant_village'),
            '{grievanceDetails}' => $request->input('complaint_details')
        );
        //get attachments
        $attachments = DB::table('grm_grievance_formsuploads')
            ->where('record_id', $complaint_id)
            ->select(DB::raw("CONCAT(server_filedirectory,saved_name) as file_path,initial_name as file_name,file_type"))
            ->get();
        $attachments = convertStdClassObjToArray($attachments);
        if (!is_array($attachments)) {
            $attachments = array();
        }
        //email
        $emailTemplateInfo = getEmailTemplateInfo(4, $vars);
        if (is_connected()) {
            $emailJob = (new ComplaintSubmissionEmailJob($toEmails, $emailTemplateInfo->subject, $emailTemplateInfo->body, $program_name, array(), $attachments))->delay(Carbon::now()->addSeconds(10));
            dispatch($emailJob);
        } else {
            $params = array(
                'email_to' => $submission_emails,
                'subject' => $emailTemplateInfo->subject,
                'body' => $emailTemplateInfo->body,
                'exception' => 'No internet connection',
                'created_at' => Carbon::now()
            );
            DB::table('tra_failed_emails')
                ->insert($params);
        }
    }

    public function grievanceLodgedNotificationToDistrict(Request $request)
    {//Notification sent if a grievance is lodged at HQ or Province...this will alert district focal point person(s)
        $accessPoint = Auth::user()->access_point_id;
        if ($accessPoint != 4) {//district...meaning complaint captured elsewhere
            $district_id = $request->input('district_id');
            $programme_type_id = $request->input('programme_type_id');
            $toEmails = DB::table('grm_focal_persons as t1')
                ->where('authority_level_id', 3)
                ->where('programme_type_id', $programme_type_id)
                ->where('district_id', $district_id)
                ->select('email')
                ->get();
            $toEmails = convertStdClassObjToArray($toEmails);
            $toEmails = convertAssArrayToSimpleArray($toEmails, 'email');
            if (count($toEmails) > 0) {
                $program_name = '';
                $provinceName = getSingleRecordColValue('provinces', array('id' => $request->input('province_id')), 'name');
                $districtName = getSingleRecordColValue('districts', array('id' => $district_id), 'name');
                $cwacName = getSingleRecordColValue('cwac', array('id' => $request->input('cwac_id')), 'name');
                $complainantName = $request->input('complainant_first_name') . ' ' . $request->input('complainant_last_name');

                $vars = array(
                    '{lodgedBy}' => aes_decrypt(Auth::user()->first_name) . ' ' . aes_decrypt(Auth::user()->last_name),
                    '{complaintFormNo}' => $request->input('complaint_form_no'),
                    '{collectionDate}' => converter22($request->input('complaint_collection_date')),
                    '{complainantName}' => $complainantName,
                    '{complainantNRC}' => $request->input('complainant_nrc'),
                    '{complainantMobile}' => $request->input('complainant_mobile'),
                    '{provinceName}' => $provinceName,
                    '{districtName}' => $districtName,
                    '{cwacName}' => $cwacName,
                    '{villageName}' => $request->input('complainant_village'),
                    '{grievanceDetails}' => $request->input('complaint_details')
                );
                //email
                $emailTemplateInfo = getEmailTemplateInfo(5, $vars);
                if (is_connected()) {
                    $emailJob = (new ComplaintSubmissionEmailJob($toEmails, $emailTemplateInfo->subject, $emailTemplateInfo->body, $program_name, array()))->delay(Carbon::now()->addSeconds(10));
                    dispatch($emailJob);
                } else {
                    $params = array(
                        'email_to' => implode(',', $toEmails),
                        'subject' => $emailTemplateInfo->subject,
                        'body' => $emailTemplateInfo->body,
                        'exception' => 'No internet connection',
                        'created_at' => Carbon::now()
                    );
                    DB::table('tra_failed_emails')
                        ->insert($params);
                }
            } else {
                //do nothing...no focal point persons set up
            }
        } else {
            //do nothing
        }
    }

}
