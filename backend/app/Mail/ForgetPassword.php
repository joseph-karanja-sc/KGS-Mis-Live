<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class ForgetPassword extends Mailable
{
    use Queueable, SerializesModels;
    protected $username='';
    protected $link='';
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($name,$link)
    {
        $this->username=$name;
        $this->link=$link;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $data['username']=$this->username;
        $data['resetLink']=$this->link;
        return $this->view('mail.forgetPassword')
            ->subject('Password Reset Request')
            ->with($data);
    }
}
