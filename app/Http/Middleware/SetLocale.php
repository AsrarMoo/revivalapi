<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        // الحصول على اللغة من الجلسة أو تعيين اللغة الافتراضية كـ "ar"
        $locale = Session::get('locale', 'ar');
        // تعيين اللغة في التطبيق
        App::setLocale($locale);

        return $next($request);
    }
}
