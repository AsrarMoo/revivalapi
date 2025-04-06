<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\Hospital;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Services\HereGeocodingService;
use Illuminate\Support\Facades\Http;

class HospitalController extends Controller
{
    protected $geoService;

    public function __construct(HereGeocodingService $geoService)
    {
        $this->middleware('auth:api', ['except' => ['register']]);
        $this->geoService = $geoService;
    }

    // ✅ تسجيل مستشفى جديد
    public function register(Request $request)
    {
        $validatedData = $request->validate([
            'hospital_name'  => 'required|string|max:255',
            'email'          => 'required|email|unique:users,email',
            'password'       => 'required|min:6',
            'hospital_phone' => 'required|string|max:15|unique:hospitals,hospital_phone',
            'hospital_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'hospital_address' => 'required|string|max:255',
        ]);

        return DB::transaction(function () use ($validatedData, $request) {
            try {
                // ✅ حفظ المستخدم في جدول users
                $user = User::create([
                  //  'name'      => $validatedData['hospital_name'],
                    'email'     => $validatedData['email'],
                    'password'  => Hash::make($validatedData['password']),
                    'user_type' => 'hospital',
                ]);

                // ✅ تحميل الصورة إن وجدت
                $imagePath = $request->hasFile('hospital_image')
                    ? $request->file('hospital_image')->store('hospital_images', 'public')
                    : null;

                // ✅ الحصول على الإحداثيات الجغرافية
                $latitude = null;
                $longitude = null;
                if (!empty($validatedData['hospital_address'])) {
                    $geoResponse = Http::get("https://geocode.search.hereapi.com/v1/geocode", [
                        'q' => $validatedData['hospital_address'],
                        'apiKey' => env('HERE_API_KEY'),
                    ]);
                    
                    if ($geoResponse->successful() && !empty($geoResponse['items'][0]['position'])) {
                        $latitude = $geoResponse['items'][0]['position']['lat'];
                        $longitude = $geoResponse['items'][0]['position']['lng'];
                    }
                }

                // ✅ حفظ المستشفى في جدول hospitals
                $hospital = Hospital::create([
                    'hospital_name'  => $validatedData['hospital_name'],
                    'hospital_phone' => $validatedData['hospital_phone'],
                    'hospital_image' => $imagePath,
                    'hospital_address' => $validatedData['hospital_address'],
                    'latitude'       => $latitude,
                    'longitude'      => $longitude,
                    'user_id'        => $user->user_id,
                ]);

                // ✅ تحديث hospital_id في users
                $user->hospital_id = $hospital->hospital_id;
                //$user->name = $hospital->hospital_name; // تأكيد ترحيل الاسم
                $user->save();

                // ✅ توليد JWT Token
                $token = JWTAuth::fromUser($user);

                return response()->json([
                    'message'  => 'تم تسجيل المستشفى بنجاح',
                    'hospital' => $hospital,
                    'user'     => $user,
                    'token'    => $token,
                ], 201);
            } catch (\Exception $e) {
                Log::error('❌ خطأ في تسجيل المستشفى:', ['error' => $e->getMessage()]);
                return response()->json(['error' => 'حدث خطأ أثناء التسجيل'], 500);
            }
        });
    }

    // ✅ استعلام عن جميع المستشفيات
    public function index()
    {
        $hospitals = Hospital::with('user')->get(); // لازم تجيب البيانات أول
    
        return response()->json([
            'hospitals' => $hospitals->map(function ($hospital) {
                return [
                    'hospital_id'      => $hospital->hospital_id,
                    'user_id'          => $hospital->user_id,
                    'hospital_name'    => $hospital->hospital_name,
                    'hospital_address' => $hospital->hospital_address,
                    'hospital_phone'   => $hospital->hospital_phone,
                    'hospital_image'   => $hospital->hospital_image,
                    'latitude'         => $hospital->latitude,
                    'longitude'        => $hospital->longitude,
                    'email'            => optional($hospital->user)->email, // الإيميل من جدول users
                ];
            }),
        ]);
    }
    

    // ✅ استعلام عن مستشفى معين
    public function show($id)
    {
        $hospital = Hospital::find($id);
        return $hospital ? response()->json(['hospital' => $hospital], 200)
        
                         : response()->json(['message' => 'المستشفى غير موجود'], 404);
    }

     // ✅ تحديث بيانات المستشفى
     public function update(Request $request, $id)
     {
         try {
             // البحث عن المستشفى والمستخدم المرتبط به
             $hospital = Hospital::findOrFail($id);
             $user = User::findOrFail($hospital->user_id);
 
             // تحديث صورة المستشفى إذا وُجدت
             if ($request->hasFile('hospital_image')) {
                 // حذف الصورة القديمة إذا كانت موجودة
                 if ($hospital->hospital_image) {
                     Storage::disk('public')->delete($hospital->hospital_image);
                 }
                 // حفظ الصورة الجديدة
                 $imagePath = $request->file('hospital_image')->store('hospital_images', 'public');
                 $hospital->hospital_image = $imagePath;
             }
 
             // تحديث بيانات المستشفى باستثناء الحقول غير المسموح بتحديثها مباشرة
             $hospital->fill($request->except(['hospital_image', 'email', 'password']))->save();
 
             // تحديث بيانات المستخدم المرتبطة بالمستشفى
             if ($request->has('email')) {
                 $user->email = $request->email;
             }
             if ($request->has('password')) {
                 $user->password = bcrypt($request->password);
             }
             if ($request->has('hospital_name')) {
                $hospital->hospital_name = $request->hospital_name;
            }
            
             $user->save();
             $hospital->save();
 
             return response()->json([
                 'message' => 'تم تحديث بيانات المستشفى بنجاح',
                 'updated_hospital' => $hospital,
                 'updated_user' => $user
             ], 200);
         } catch (\Exception $e) {
             return response()->json([
                 'error' => 'حدث خطأ أثناء تحديث بيانات المستشفى',
                 'details' => $e->getMessage()
             ], 500);
         }
     }
    // ✅ حذف مستشفى
    public function destroy($id)
    {
        $hospital = Hospital::find($id);
        if (!$hospital) {
            return response()->json(['message' => 'المستشفى غير موجود'], 404);
        }

        return DB::transaction(function () use ($hospital) {
            if ($hospital->hospital_image) {
                Storage::disk('public')->delete($hospital->hospital_image);
            }
            User::where('user_id', $hospital->user_id)->delete();
            $hospital->delete();

            return response()->json(['message' => 'تم حذف المستشفى بنجاح'], 200);
        });
    }
}
