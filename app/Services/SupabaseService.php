<?php
namespace App\Services;

use Supabase\CreateClient;
use Illuminate\Support\Facades\Log;

class SupabaseService
{
    private $client;

    public function __construct()
    {
        // تحميل المفتاح والرابط من البيئة
        $apiKey = env('SUPABASE_API_KEY');  
        $url = env('SUPABASE_URL');         

        // إنشاء عميل Supabase
        $this->client = new CreateClient($url, $apiKey);
    }

    // لتسجيل مستخدم جديد
    public function signUp($email, $password, $phone = null)
    {
        try {
            // تحقق من صحة البيانات
            if (empty($email) || empty($password)) {
                throw new \Exception('البريد الإلكتروني أو كلمة المرور مفقود.');
            }
    
            Log::info('البريد الإلكتروني وكلمة المرور: ', ['email' => $email, 'password' => $password]);
    
            // التحقق من أن البريد الإلكتروني وكلمة المرور غير فارغين
            if (!$email || !$password) {
                throw new \Exception('البريد الإلكتروني أو كلمة المرور مفقود.');
            }
    
            // إعداد البيانات للإرسال
            $data = [
                'email' => $email,
                'password' => $password,
            ];
    
            // إضافة رقم الهاتف إذا تم تمريره
            if ($phone) {
                $data['phone'] = $phone;
            }
    
            // إرسال طلب التسجيل إلى Supabase
            $response = $this->client->auth->signUp($data);
    
            // التحقق إذا كان هناك خطأ
            if ($response instanceof \Supabase\Util\GoTrueError) {
                throw new \Exception($response->getMessage());
            }
    
            return $response;
        } catch (\Exception $e) {
            Log::error('خطأ في عملية التسجيل: ' . $e->getMessage());
            return ['error' => 'فشل في التسجيل. حاول مرة أخرى.'];
        }
    }
    


    // لتسجيل الدخول
    public function signIn($email, $password)
    {
        try {
            // إرسال طلب تسجيل الدخول
            $response = $this->client->auth->signInWithPassword($email, $password);

            // التحقق إذا كان هناك خطأ
            if ($response instanceof \Supabase\Util\GoTrueError) {
                throw new \Exception($response->getMessage());
            }

            return $response;
        } catch (\Exception $e) {
            Log::error('خطأ في عملية تسجيل الدخول: ' . $e->getMessage());
            return ['error' => 'فشل في تسجيل الدخول. حاول مرة أخرى.'];
        }
    }

    // لإعادة تعيين كلمة المرور
    public function resetPassword($email)
    {
        try {
            // إرسال طلب إعادة تعيين كلمة المرور
            $response = $this->client->auth->resetPassword($email);

            // التحقق إذا كان هناك خطأ
            if ($response instanceof \Supabase\Util\GoTrueError) {
                throw new \Exception($response->getMessage());
            }

            return $response;
        } catch (\Exception $e) {
            Log::error('خطأ في عملية إعادة تعيين كلمة المرور: ' . $e->getMessage());
            return ['error' => 'فشل في إعادة تعيين كلمة المرور. حاول مرة أخرى.'];
        }
    }
}
