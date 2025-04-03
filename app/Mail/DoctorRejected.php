<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class Doctorrejected extends Mailable
{
    public $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function build()
    {
        return $this->subject('حالة طلب التسجيل كطبيب')
                    ->view('emails.doctor_rejected', [
                        'name' => $this->data['doctor_name'],
                        'reason' => $this->data['rejection_reason'],
                        'reapplyUrl' => $this->data['reapply_url']
                    ]);
    }
}