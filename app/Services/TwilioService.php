<?php

namespace App\Services;

use Twilio\Rest\Client;

class TwilioService
{
    protected $twilio;

    public function __construct()
    {
        $this->twilio = new Client(env('TWILIO_SID'), env('TWILIO_AUTH_TOKEN'));
    }

    public function sendOTP($to, $code)
    {
        return $this->twilio->messages->create(
            "whatsapp:$to",
            [
                "from" => env('TWILIO_WHATSAPP_FROM'),
                "body" => "رمز التحقق الخاص بك هو: $code"
            ]
        );
    }
}
