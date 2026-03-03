<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class ComplaintsLagNotification extends Mailable
{
    use Queueable, SerializesModels;
    protected $complaints;
    protected $emailBody;
    protected $programName;
    protected $emailSubject;

    /**
     * Create a new message instance.
     *
     * @param $complaints
     * @param $emailBody
     * @param $programName
     * @param $emailSubject
     */
    public function __construct($complaints, $emailBody, $programName, $emailSubject)
    {
        $this->complaints = $complaints;
        $this->emailBody = $emailBody;
        $this->programName = $programName;
        $this->emailSubject = $emailSubject;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $data['complaints'] = $this->complaints;
        $data['email_body'] = $this->emailBody;
        $data['program_name'] = $this->programName;
        return $this->view('mail.complaintsLagEmail')
            ->subject($this->emailSubject)
            ->with($data);
    }
}
