<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DoctorApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $doctorName;

    public function __construct($doctorName)
    {
        $this->doctorName = $doctorName;
    }

    public function build()
    {
        return $this->subject('تمت الموافقة على حسابك كطبيب')
                    ->view('emails.doctor_approved')
                    ->with([
                        'doctorName' => $this->doctorName,
                    ]);
    }
}
