<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class ComplaintResponseNotification extends Mailable
{
    use Queueable, SerializesModels;

    protected $emailSubject;
    protected $emailBody;
    protected $letterPath;
    protected $programName;

    /**
     * Create a new message instance.
     *
     * @param $emailSubject
     * @param $emailBody
     * @param $letterPath
     * @param $programName
     */
    public function __construct($emailSubject, $emailBody, $letterPath, $programName)
    {
        $this->emailSubject = $emailSubject;
        $this->emailBody = $emailBody;
        $this->letterPath = $letterPath;
        $this->programName = $programName;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $data['email_body'] = $this->emailBody;
        $data['program_name'] = $this->programName;
        return $this->view('mail.genericEmail')
            ->subject($this->emailSubject)
            ->with($data)
            ->attach($this->letterPath, [
                'as' => 'Complaint_Response_Letter.pdf',
                'mime' => 'application/pdf',
            ]);
    }
}