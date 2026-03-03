<?php

namespace App\Console\Commands;

use App\Mail\ComplaintsLagNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ComplaintsLagCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:complaintsLag';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send email notifications of complaints lagging behind, based on the set time limits of the various complaint stages';

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
            'process_type' => 'Complaints Lag Email Notifications',
            'process_description' => $this->description,
            'created_at' => Carbon::now()
        );
        try {
            //those to receive emails
            //todo: 1
            $sql1 = DB::table('grm_complaint_submission_emails as t1')
                ->join('programme_types as t2', 't1.programme_type_id', '=', 't2.id')
                ->select('t1.*', 't2.name as programme_name')
                ->where('is_active', 1);
            $results1 = $sql1->get();
            //time limits
            //todo: 2
            $sql2 = DB::table('process_stage_max_days as t1');
            $results2 = $sql2->get();

            $complaintsArray = array();
            $complaintsSimpleArray = array();
            $emailTemplate = getEmailTemplateInfo(2, array());
            $email_body = 'The following complaint(s) have exceeded the allowed period in the respective stages:';

            foreach ($results1 as $result1) {
                foreach ($results2 as $result2) {
                    $sql = DB::table('grm_complaint_details as t1')
                        ->join('wf_workflow_stages as t2', 't1.workflow_stage_id', '=', 't2.id')
                        ->where('workflow_stage_id', $result2->stage_id)
                        ->where('programme_type_id', $result1->programme_type_id)
                        ->where('lag_email_sent', '<>', 1)
                        ->select(DB::raw("TOTAL_WEEKDAYS(t1.current_stage_entry_date,now()) as test,t1.reference_no,t2.name as stage,
                                            t1.current_stage_entry_date,t1.id as complaint_id"))
                        ->whereRaw("TOTAL_WEEKDAYS(t1.current_stage_entry_date,now())>$result2->max_days");
                    $data = $sql->get();
                    foreach ($data as $datum) {
                        $complaintsArray[] = array(
                            'refNo' => $datum->reference_no,
                            'stage' => $datum->stage,
                            'entry_date' => $datum->current_stage_entry_date,
                            'numOfDays' => $datum->test,
                            'allowedNumOfDays' => $result2->max_days
                        );
                        $complaintsSimpleArray[] = array(
                            'complaint_id' => $datum->complaint_id
                        );
                    }
                }
                $complaintsSimpleArray = convertAssArrayToSimpleArray($complaintsSimpleArray, 'complaint_id');
                if (count($complaintsArray) > 0) {//to avoid sending email with empty list of complaints
                    Mail::to($result1->email_address)
                        //->cc($cc_array)
                        ->send(new ComplaintsLagNotification($complaintsArray, $emailTemplate->body, $result1->programme_name, $emailTemplate->subject));

                    if (count(Mail::failures()) > 0) {
                        //email failed
                    } else {
                        //mark email lag flag
                        DB::table('grm_complaint_details as t1')
                            ->whereIn('t1.id', $complaintsSimpleArray)
                            ->update(array('lag_email_sent' => 1));
                    }
                }
            }
            $log_data['status'] = 'Successful';
            DB::table('auto_processes_logs')
                ->insert($log_data);
            $this->info('Email notification sent successfully');
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

}
