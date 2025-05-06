<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\Appointment;
use App\Models\HospitalDoctor;
use App\Models\Patient;
use Carbon\Carbon;

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

    public function countHospital()
{
    $count = Hospital::count();

    return response()->json([
        'total_hospital' => $count,
    ]);
}
public function allHospitalsStats()
{
    // جلب جميع المستشفيات
    $hospitals = Hospital::all();

    // دالة لإرجاع إحصائيات المستشفى
    $hospitalStats = $hospitals->map(function ($hospital) {
        return [
            'hospital_name' => $hospital->name,
            'hospital_id' => $hospital->id,
            'stats' => $this->getHospitalStats($hospital->id),
        ];
    });

    return response()->json($hospitalStats);
}

public function dashboardStats()
{
    // جلب جميع المستشفيات
    $hospitals = Hospital::all(); // تأكد من أن لديك موديل للمستشفيات

    // إحصائيات لكل مستشفى
    $hospitalStats = $hospitals->map(function ($hospital) {
        // عدد الحجوزات في هذا المستشفى
        $appointmentsCount = Appointment::where('hospital_id', $hospital->hospital_id)->count();

        // عدد الأطباء الذين يعملون في هذا المستشفى
        $doctorsCount = HospitalDoctor::where('hospital_id', $hospital->hospital_id)->count();

        // جلب جميع المرضى الذين لديهم حجوزات في هذا المستشفى
        $patientIds = Appointment::where('hospital_id', $hospital->hospital_id)->pluck('patient_id')->unique();
        $patients = Patient::whereIn('patient_id', $patientIds)->get();

        // توزيع الجنس
        $genderStats = $patients->groupBy('patient_gender')->map(function ($group) {
            return $group->count();
        });

        // إذا لم يكن هناك بيانات، يتم إضافة صفر للذكور والإناث
        $maleCount = $genderStats->get('male', 0);
        $femaleCount = $genderStats->get('female', 0);

        // توزيع العمر
        $ageStats = $patients->groupBy(function ($patient) {
            $age = Carbon::parse($patient->birth_date)->age;

            if ($age <= 18) return '0-18';
            elseif ($age <= 35) return '19-35';
            elseif ($age <= 50) return '36-50';
            else return '50+';
        })->map->count();

        return [
            'hospital_name' => $hospital->hospital_name,  // اسم المستشفى
            'hospital_id' => $hospital->hospital_id,      // معرف المستشفى
            'stats' => [
                'bookings_count' => $appointmentsCount,
                'doctors_count' => $doctorsCount,
                'gender_stats' => [
                    'male' => $maleCount,
                    'female' => $femaleCount,
                ],
                'age_stats' => $ageStats,
            ],
        ];
    });

    // إرجاع الإحصائيات الخاصة بجميع المستشفيات
    return response()->json($hospitalStats);
}


public function getAppointmentStats(Request $request)
{
    $type = $request->query('type'); // 'day' أو 'month'
    $dateInput = $request->query('date'); // مثلاً '2025-05-02' أو '2025-05'

    if (!$type || !$dateInput) {
        return response()->json(['error' => 'النوع أو التاريخ مفقود'], 400);
    }

    // تحويل التاريخ باستخدام Carbon
    $date = Carbon::parse($dateInput);

    // تحديد النطاقات
    $startOfDay = $date->copy()->startOfDay();
    $endOfDay = $date->copy()->endOfDay();

    $startOfWeek = $date->copy()->startOfWeek(Carbon::SATURDAY);
    $endOfWeek = $date->copy()->endOfWeek(Carbon::FRIDAY);

    $startOfMonth = $date->copy()->startOfMonth();
    $endOfMonth = $date->copy()->endOfMonth();

    // حساب الإحصائيات
    $todayBookings = Appointment::whereBetween('created_at', [$startOfDay, $endOfDay])->count();
    $weekBookings = Appointment::whereBetween('created_at', [$startOfWeek, $endOfWeek])->count();
    $monthBookings = Appointment::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();

    // بيانات الرسم البياني (لكل يوم في الشهر)
    $chartData = Appointment::selectRaw('DATE(created_at) as label, COUNT(*) as count')
        ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
        ->groupBy('label')
        ->orderBy('label')
        ->get();

    return response()->json([
        'todayBookings' => $todayBookings,
        'weekBookings' => $weekBookings,
        'monthBookings' => $monthBookings,
        'chartData' => $chartData,
    ]);
}
public function getMonthlyStats(Request $request)
{
    // الحصول على السنة من الـ query parameters، إذا لم تكن موجودة نأخذ السنة الحالية
    $year = $request->query('year', Carbon::now()->year);

    // استرجاع إحصائيات الحجوزات حسب الشهر
    $monthlyStats = Appointment::selectRaw('MONTH(created_at) as month, COUNT(*) as count')
        ->whereYear('created_at', $year) // تحديد السنة
        ->groupBy('month')
        ->orderBy('month')
        ->get();

    // تجهيز مصفوفة تحتوي على أسماء الشهور وعدد الحجوزات في كل شهر
    $months = [];
    for ($i = 1; $i <= 12; $i++) {
        // الحصول على اسم الشهر باستخدام Carbon وتحديد اللغة العربية
        $monthName = Carbon::createFromDate($year, $i, 1)->locale('ar')->translatedFormat('F');
        
        // جلب عدد الحجوزات للشهر الحالي، إذا لم توجد حجوزات للشهر نضع القيمة صفر
        $monthData = $monthlyStats->firstWhere('month', $i);
        $months[] = [
            'month' => $monthName,
            'count' => $monthData ? $monthData->count : 0 // إذا لم توجد بيانات للشهر نضع صفر
        ];
    }

    // إرجاع البيانات بتنسيق JSON
    return response()->json($months);
}
// في Controller الخاص بالحجوزات
public function getAvailableYears()
{
    // جلب السنوات المتاحة من البيانات (سنة الحجز من حقل تاريخ الحجز)
    $years = DB::table('appointments')
               ->selectRaw('YEAR(created_at) as year')
               ->distinct()
               ->orderByDesc('year') // ترتيب السنوات من الأحدث إلى الأقدم
               ->pluck('year');

    return response()->json($years);
}

}


