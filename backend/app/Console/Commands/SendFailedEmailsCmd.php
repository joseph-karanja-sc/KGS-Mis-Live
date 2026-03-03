<?php

namespace App\Console\Commands;

use App\Jobs\ComplaintSubmissionEmailJob;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendFailedEmailsCmd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:sendFailedEmails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends logged failed emails';

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
        $failedEmails = DB::table('tra_failed_emails')->get();
        foreach ($failedEmails as $failedEmail) {
            $id = $failedEmail->id;
            $emails_to = explode(',', $failedEmail->email_to);
            $cc_emails = explode(',', $failedEmail->cc_to);
            $attachments = json_decode($failedEmail->attachments);
            $attachments = convertStdClassObjToArray($attachments);
            if (!is_array($attachments)) {
                $attachments = array();
            }
            if (is_connected()) {
                $emailJob = (new ComplaintSubmissionEmailJob($emails_to, $failedEmail->subject, $failedEmail->body, $failedEmail->programme_name, $cc_emails, $attachments))
                    ->delay(Carbon::now()->addSeconds(10));
                dispatch($emailJob);

                /////
                /*$emails_to=array_map('trim', array_filter($emails_to));
                $cc_emails = array_map('trim', array_filter($cc_emails));
                try{
                    Mail::to($emails_to)
                        ->cc($cc_emails)
                        ->send(new ComplaintSubmissionNotification($failedEmail->subject, $failedEmail->body, $failedEmail->programme_name, $attachments));
                }catch (\Exception $exception) {
                    $params = array(
                        'email_to' => implode(',', $emails_to),
                        'cc_to' => implode(',', $cc_emails),
                        'attachments' => json_encode($attachments),
                        'subject' => $failedEmail->subject,
                        'body' => $failedEmail->body,
                        'programme_name' => $failedEmail->programme_name,
                        'exception' => $exception->getMessage(),
                        'created_at' => \Illuminate\Support\Carbon::now()
                    );
                    DB::table('tra_failed_emails')
                        ->insert($params);
                }*/
                ////

            } else {
                $params = array(
                    'email_to' => implode(',', $emails_to),
                    'cc_to' => implode(',', $cc_emails),
                    'subject' => $failedEmail->subject,
                    'body' => $failedEmail->body,
                    'attachments' => json_encode($failedEmail->attachments),
                    'exception' => 'No internet connection',
                    'created_at' => Carbon::now()
                );
                DB::table('tra_failed_emails')
                    ->insert($params);
            }
            DB::table('tra_failed_emails')->where('id', $id)->delete();
        }
        return;
    }
}
