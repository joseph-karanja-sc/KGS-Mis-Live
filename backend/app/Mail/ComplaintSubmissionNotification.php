<?php
/**
 * Created by PhpStorm.
 * User: Kip
 * Date: 10/22/2019
 * Time: 12:44 PM
 */

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class ComplaintSubmissionNotification extends Mailable
{
    use Queueable, SerializesModels;

    protected $emailSubject,
        $emailBody,
        $programName,
        $attachmentsPassed;

    /**
     * Create a new message instance.
     *
     * @param $emailSubject
     * @param $emailBody
     * @param $programName
     * @param array $attachmentsPassed
     */
    public function __construct($emailSubject, $emailBody, $programName, $attachmentsPassed = array())
    {
        $this->emailSubject = $emailSubject;
        $this->emailBody = $emailBody;
        $this->programName = $programName;
        $this->attachmentsPassed = $attachmentsPassed;
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
        /*  return $this->view('mail.genericEmail')
              ->subject($this->emailSubject)
              ->with($data);*/
        $return = $this->view('mail.genericEmail')
            ->subject($this->emailSubject)
            ->with($data);
        // if (count($this->attachmentsPassed) > 0) {
        //     foreach ($this->attachmentsPassed as $attachment) {
        //         $return->attach($attachment['file_path'], [
        //             'as' => $attachment['file_name'],
        //             'mime' => $attachment['file_type']
        //         ]);
        //     }
        // }
        return $return;
    }
}
