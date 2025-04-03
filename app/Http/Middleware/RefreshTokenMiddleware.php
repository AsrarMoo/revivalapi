<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

class RefreshTokenMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            // محاولة التحقق من التوكن
            JWTAuth::parseToken()->authenticate();
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            try {
                // إذا كان التوكن منتهي الصلاحية، جلب التوكن الجديد
                $newToken = JWTAuth::refresh();
                // ضبط التوكن الجديد في الهيدر
                return response()->json([
                    'status' => 'success',
                    'new_access_token' => $newToken
                ], Response::HTTP_OK);
            } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid token, please log in again'
                ], Response::HTTP_UNAUTHORIZED);
            }
        }
        return $next($request);
    }
}
