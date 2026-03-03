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

class ComplaintSubmissionEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $tries = 5,
        $attachmentsToPass;
    protected $email,
        $subject,
        $cc_to,
        $body,
        $programme_name;

    /**
     * Create a new job instance.
     *
     * @param $pri_emails
     * @param $subject
     * @param $body
     * @param $programme_name
     * @param array $cc_emails
     * @param array $attachmentsToPass
     */
    public function __construct($pri_emails, $subject, $body, $programme_name, $cc_emails = array(), $attachmentsToPass = array())
    {
        $this->email = array_map('trim', array_filter($pri_emails));
        $this->subject = $subject;
        $this->body = $body;
        $this->programme_name = $programme_name;
        $this->cc_to = array_map('trim', array_filter($cc_emails));
        $this->attachmentsToPass = $attachmentsToPass;
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
                ->send(new ComplaintSubmissionNotification($this->subject, $this->body, $this->programme_name, $this->attachmentsToPass));
        } catch (\Exception $exception) {
            $this->failed($exception);
        }
    }

    public function failed(\Exception $exception)
    {
        $params = array(
            'email_to' => implode(',', $this->email),
            'cc_to' => implode(',', $this->cc_to),
            'attachments' => json_encode($this->attachmentsToPass),
            'subject' => $this->subject,
            'body' => $this->body,
            'programme_name' => $this->programme_name,
            'exception' => $exception->getMessage(),
            'created_at' => Carbon::now()
        );
        DB::table('tra_failed_emails')
            ->insert($params);
    }

}
