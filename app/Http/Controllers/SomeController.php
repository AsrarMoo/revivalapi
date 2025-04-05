<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SomeController extends Controller
{
    //
}
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SomeController extends Controller
{
    // حمايّة طريقة باستخدام auth:api
    public function someProtectedMethod()
    {
        // إضافة middleware لحماية الدالة
        $this->middleware('auth:api');

        // الكود المحمي هنا
        return response()->json([
            'message' => 'تم الوصول إلى البيانات المحمية.',
        ]);
    }
}
