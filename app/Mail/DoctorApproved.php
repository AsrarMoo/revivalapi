<?php
namespace App\Mail;

use Illuminate\Mail\Mailable;

class Doctorapproved extends Mailable
{
    public $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function build()
    {
        return $this->subject('تمت الموافقة على تسجيلك كطبيب')
                    ->view('emails.doctor_approved', [
                        'name' => $this->data['doctor_name'],
                        'email' => $this->data['email'],
                        'password' => $this->data['password'],
                        'loginUrl' => $this->data['login_url']
                    ]);
    }
}