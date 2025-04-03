<?php
// TranslationController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Post;
use Illuminate\Support\Facades\Http;

class TranslationController extends Controller
{
    public function translatePost($postId, $targetLanguage)
    {
        // جلب المحتوى الأصلي من قاعدة البيانات
        $post = Post::find($postId);
        $textToTranslate = $post->content;  // المحتوى الأصلي

        // إرسال النص إلى Google Translate API
        $response = Http::get('https://translation.googleapis.com/language/translate/v2', [
            'q' => $textToTranslate,
            'target' => $targetLanguage,
            'key' => 'YOUR_GOOGLE_API_KEY'
        ]);

        // إرجاع الترجمة
        $translatedText = $response->json()['data']['translations'][0]['translatedText'];
        return response()->json(['translatedText' => $translatedText]);
    }
}
