<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class ActivateActivateAccount extends Mailable
{
    use Queueable, SerializesModels;


    public $username = " ";
    public $password = " ";
    public $link = " ";

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($email, $password, $link)
    {
        $this->username = $email;
        $this->password = $password;
        $this->link = $link;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
     {
        $data['username'] = $this->username;
        $data['password'] = $this->password;
        $data['link'] = $this->link;
        return $this->view('mail.accountActivation')
            ->subject('KGS MIS Account Activation')
            ->with($data);
    }
}
