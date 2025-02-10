<?php
namespace App\Http\Controllers;

use App\Models\Hospital;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class HospitalController extends Controller
{
    // إضافة مستشفى جديد
    public function store(Request $request)
    {
        $request->validate([
            'hospital_name' => 'required|string|max:255',
            'hospital_email' => 'required|email|unique:hospitals,hospital_email',
            'hospital_address' => 'required|string|max:255',
            'hospital_phone' => 'required|string|max:15',
            'password' => 'required|string|min:6',  // كلمة المرور للمستخدم المرتبط
            'hospital_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // التحقق من نوع الصورة
        ]);

        // سجل البيانات المدخلة لمراقبة المشاكل
        Log::debug('Received request data:', $request->all());

        // تخزين الصورة إذا كانت موجودة
        $imagePath = null;
        if ($request->hasFile('hospital_image')) {
            $imagePath = $request->file('hospital_image')->store('hospitals', 'public');
            Log::debug('Image stored at:', [$imagePath]);
        } else {
            Log::debug('No image uploaded');
        }

        try {
            // إنشاء المستخدم (User) أولاً
            $user = User::create([
                'name' => $request->hospital_name,
                'password' => Hash::make($request->password),
                'user_type' => 'Hospital',
                'is_active' => true, // قيمة مبدئية
            ]);
            Log::debug('User created:', $user->toArray());

            // إنشاء المستشفى وربطه بالمستخدم
            $hospital = Hospital::create([
                'hospital_name' => $request->hospital_name,
                'hospital_email' => $request->hospital_email,
                'hospital_address' => $request->hospital_address,
                'hospital_phone' => $request->hospital_phone,
                'hospital_image' => $imagePath, // تخزين مسار الصورة
                'user_id' => $user->user_id,
            ]);
            Log::debug('Hospital created:', $hospital->toArray());

            // إرجاع المستشفى والمستخدم بدون كلمة السر
            $userWithoutPassword = $user->makeHidden(['password']);
            return response()->json(['hospital' => $hospital, 'user' => $userWithoutPassword], 201);
        } catch (\Exception $e) {
            Log::error('Error occurred during hospital creation: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to create hospital', 'error' => $e->getMessage()], 500);
        }
    }

    // تعديل بيانات مستشفى
    public function update(Request $request, $hospital_id)
    {
        $hospital = Hospital::findOrFail($hospital_id);
    
        // التحقق من الحقول التي يتم إرسالها فقط
        $request->validate([
            'hospital_name' => 'sometimes|string|max:255',
            'hospital_email' => 'sometimes|email|unique:hospitals,hospital_email,' . $hospital->hospital_id, 
            'hospital_address' => 'sometimes|string|max:255',
            'hospital_phone' => 'sometimes|string|max:15',
            'hospital_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);
    
        // تخزين الصورة إذا كانت موجودة
        $imagePath = $hospital->hospital_image;
        if ($request->hasFile('hospital_image')) {
            $imagePath = $request->file('hospital_image')->store('hospitals', 'public');
        }
    
        // إرجاع البيانات المعدلة (إذا كانت موجودة) باستخدام only
        $updateData = $request->only(['hospital_name', 'hospital_email', 'hospital_address', 'hospital_phone']);
    
        // إذا تم إرسال صورة جديدة، أضفها إلى البيانات المعدلة
        if ($imagePath) {
            $updateData['hospital_image'] = $imagePath;
        }
    
        // تحديث المستشفى
        $hospital->update($updateData);
    
        // إرجاع المستشفى المحدث
        return response()->json(['hospital' => $hospital], 200);
    }
    
    public function show($hospital_id)
    {
        // استرجاع المستشفى بجميع بياناته
        $hospital = Hospital::with('user')->findOrFail($hospital_id);
        return response()->json(['hospital' => $hospital], 200);
    }
    
    public function index()
    {
        // استرجاع جميع المستشفيات
        $hospitals = Hospital::all();
        return response()->json(['hospitals' => $hospitals], 200);
    }
}    