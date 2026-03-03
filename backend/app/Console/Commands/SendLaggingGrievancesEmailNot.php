<?php

namespace App\Console\Commands;

use App\Mail\ComplaintsLagNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendLaggingGrievancesEmailNot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:notifyOnLaggingGrievances';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send email notifications on grievances lagging behind';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $log_data = array(
            'process_type' => 'Lagging Grievances Email Notifications',
            'process_description' => $this->description,
            'created_at' => Carbon::now()
        );
        try {
            $emailTemplate = getEmailTemplateInfo(2, array());
            $this->sendDistrictEmailNotification($emailTemplate);
            $this->sendProvinceEmailNotification($emailTemplate);
        } catch (\Exception $exception) {
            $log_data['status'] = 'Failed';
            $log_data['failure_reason'] = $exception->getMessage();
            DB::table('auto_processes_logs')
                ->insert($log_data);
            $this->info($exception->getMessage());
        } catch (\Throwable $throwable) {
            $log_data['status'] = 'Failed';
            $log_data['failure_reason'] = $throwable->getMessage();
            DB::table('auto_processes_logs')
                ->insert($log_data);
            $this->info($throwable->getMessage());
        }
        return;
    }

    public function sendDistrictEmailNotification($emailTemplate)
    {
        $complaintsSimpleArray = array();
        //todo: 1: District->ongoing
        $mainSql1 = DB::table('grm_lagging_grievances as t1')
            ->join('grm_complaint_details as t2', 't1.complaint_id', '=', 't2.id')
            ->where('notification_level', 'district')
            ->where('email_sent', '<>', 1);

        $sql1 = clone $mainSql1;
        $sql1->join('users as t3', 't2.complaint_recorded_by', '=', 't3.id')
            ->select(DB::raw("decrypt(email) as email_address, t2.complaint_recorded_by"))
            ->groupBy('t2.complaint_recorded_by');
        $results1 = $sql1->get();
        foreach ($results1 as $result1) {
            $sql2 = clone $mainSql1;
            $sql2->select(DB::raw("t2.*,TOTAL_WEEKDAYS(t2.complaint_record_date,now()) as numOfDays"))
                ->where('t2.complaint_recorded_by', $result1->complaint_recorded_by);
            $results2 = $sql2->get();
            foreach ($results2 as $result2) {
                $complaintsSimpleArray[] = array(
                    'complaint_id' => $result2->id
                );
            }
            $complaintsSimpleArray = convertAssArrayToSimpleArray($complaintsSimpleArray, 'complaint_id');
            if ($results2->count() > 0) {//to avoid sending email with empty list of complaints
                Mail::to($result1->email_address)
                    //->cc($cc_array)
                    ->send(new ComplaintsLagNotification($results2, $emailTemplate->body, '', $emailTemplate->subject));

                if (count(Mail::failures()) > 0) {
                    //email failed
                } else {
                    //mark email lag flag
                    DB::table('grm_lagging_grievances as t1')
                        ->whereIn('t1.complaint_id', $complaintsSimpleArray)
                        ->update(array('email_sent' => 1));
                }
            }
        }
    }

    public function sendProvinceEmailNotification($emailTemplate)
    {
        $complaintsSimpleArray = array();
        //todo: 2: Province->ongoing
        $mainSql1 = DB::table('grm_lagging_grievances as t1')
            ->join('grm_complaint_details as t2', 't1.complaint_id', '=', 't2.id')
            ->where('notification_level', 'provhq')
            ->where('email_sent', '<>', 1)
            ->whereNotNull('t2.province_id');

        $sql1 = clone $mainSql1;
        $sql1->select(DB::raw("t2.*"))
            ->groupBy('t2.programme_type_id', 't2.province_id');
        $results1 = $sql1->get();

        foreach ($results1 as $result1) {
            //who receives email
            $priEmails = '';
            $ccEmails = '';
            $qryEmails = DB::table('grm_emailnotifications_setup')
                ->select('primary_email', 'cc_email')
                ->where('gewel_programme_id', $result1->programme_type_id)
                ->where('level', 3)
                ->where('province_id', $result1->province_id);
            $emailDetails = $qryEmails->first();

            if ($emailDetails) {
                $priEmails = $emailDetails->primary_email;
                $ccEmails = $emailDetails->cc_email;
            }
            $priEmailsArr = array_map('trim', array_filter(explode(',', $priEmails)));
            $ccEmailsArr = array_map('trim', array_filter(explode(',', $ccEmails)));

            $sql2 = clone $mainSql1;
            $sql2->select(DB::raw("t2.*,TOTAL_WEEKDAYS(t2.complaint_record_date,now()) as numOfDays"))
                ->where('t2.programme_type_id', $result1->programme_type_id)
                ->where('t2.province_id', $result1->province_id);
            $results2 = $sql2->get();

            foreach ($results2 as $result2) {
                $complaintsSimpleArray[] = array(
                    'complaint_id' => $result2->id
                );
            }
            $complaintsSimpleArray = convertAssArrayToSimpleArray($complaintsSimpleArray, 'complaint_id');
            if ($results2->count() > 0) {//to avoid sending email with empty list of complaints
                Mail::to($priEmailsArr)
                    ->cc($ccEmailsArr)
                    ->send(new ComplaintsLagNotification($results2, $emailTemplate->body, '', $emailTemplate->subject));

                if (count(Mail::failures()) > 0) {
                    //email failed
                } else {
                    //mark email lag flag
                    /* DB::table('grm_lagging_grievances as t1')
                         ->whereIn('t1.complaint_id', $complaintsSimpleArray)
                         ->update(array('email_sent' => 1));*/
                }
                $this->sendHQEmailNotification($complaintsSimpleArray, $result1->programme_type_id, $emailTemplate);
            }
        }
    }

    public function sendHQEmailNotification($complaintsSimpleArray, $programme_type_id, $emailTemplate)
    {
        //todo: 3: HQ->ongoing
        $sql1 = DB::table('grm_lagging_grievances as t1')
            ->join('grm_complaint_details as t2', 't1.complaint_id', '=', 't2.id')
            ->select(DB::raw("t2.*,TOTAL_WEEKDAYS(t2.complaint_record_date,now()) as numOfDays"))
            ->where('notification_level', 'provhq')
            ->where('email_sent', '<>', 1)
            ->whereNotNull('t2.province_id')
            ->whereIn('t1.complaint_id', $complaintsSimpleArray)
            ->where('t2.programme_type_id', $programme_type_id);
        $results1 = $sql1->get();

        //who receives email
        $priEmails = '';
        $ccEmails = '';
        $qryEmails = DB::table('grm_emailnotifications_setup')
            ->select('primary_email', 'cc_email')
            ->where('gewel_programme_id', $programme_type_id)
            ->where('level', 2);
        $emailDetails = $qryEmails->first();
        if ($emailDetails) {
            $priEmails = $emailDetails->primary_email;
            $ccEmails = $emailDetails->cc_email;
        }
        $priEmailsArr = array_map('trim',array_filter(explode(',', $priEmails)));
        $ccEmailsArr = array_map('trim',array_filter(explode(',', $ccEmails)));

        if ($results1->count() > 0) {//to avoid sending email with empty list of complaints
            Mail::to($priEmailsArr)
                ->cc($ccEmailsArr)
                ->send(new ComplaintsLagNotification($results1, $emailTemplate->body, '', $emailTemplate->subject));

            if (count(Mail::failures()) > 0) {
                //email failed
            } else {
                //mark email lag flag
                DB::table('grm_lagging_grievances as t1')
                    ->whereIn('t1.complaint_id', $complaintsSimpleArray)
                    ->update(array('email_sent' => 1));
            }
        }
    }

}
