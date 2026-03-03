<?php

namespace App\Jobs;

use App\Mail\ComplaintSubmissionNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class GenericSendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $tries = 5;
    protected $email,
        $subject,
        $cc_to,
        $body,
        $programme_name;

    /**
     * Create a new job instance.
     *
     * @param $email
     * @param $subject
     * @param $body
     * @param $programme_name
     * @param array $cc_to
     */
    public function __construct($email, $subject, $body, $cc_to = array(), $programme_name = '')
    {
        $this->email = $email;
        $this->subject = $subject;
        $this->body = $body;
        $this->cc_to = $cc_to;
        $this->programme_name = $programme_name;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            Mail::to($this->email)
                ->cc($this->cc_to)
                ->send(new ComplaintSubmissionNotification($this->subject, $this->body, $this->programme_name));
        } catch (\Exception $exception) {
            $this->failed($exception);
        }
    }

    public function failed(\Exception $exception)
    {
        // $params = array(
        //     'email_to' => $this->email,
        //     'subject' => $this->subject,
        //     'body' => $this->body,
        //     'exception' => $exception->getMessage(),
        //     'created_at' => Carbon::now()
        // );
        // $resp = DB::table('tra_failed_emails')
        //     ->insert($params);
        $log_array = array(
            "complaint_form_no" => $this->subject,
            "primary_email" => implode(",",$this->email),
            "cc_email" => implode(",",$this->cc_to),
            "programme_name" => $this->programme_name,
            "exception" => $exception->getMessage(),
            "is_sent" => 0
        );
        DB::table('batch_emailsent_log')->insert($log_array);
    }

}
