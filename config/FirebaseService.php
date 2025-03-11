<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FirebaseService
{
    protected $firebaseApiKey;

    public function __construct()
    {
        $this->firebaseApiKey = env('FIREBASE_API_KEY');
    }

    public function sendVerificationCode($phoneNumber)
    {
        $url = "https://identitytoolkit.googleapis.com/v1/accounts:sendVerificationCode?key=" . $this->firebaseApiKey;

        $response = Http::post($url, [
            'phoneNumber' => $phoneNumber,
            'recaptchaToken' => 'your-recaptcha-token' // تحتاج إلى توليد هذا التوكن من الواجهة الأمامية
        ]);

        if ($response->failed()) {
            throw new \Exception('فشل إرسال رمز التحقق: ' . $response->body());
        }

        return $response->json();
    }
}


