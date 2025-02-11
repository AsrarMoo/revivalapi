<?php
namespace App\Http\Controllers;

use App\Models\Hospital;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
            'password' => 'required|string|min:6',
            'hospital_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        // سجل البيانات المدخلة لمراقبة المشاكل
        Log::debug('Received request data:', $request->all());

        // حفظ الصورة إذا وُجدت
        $imagePath = null;
        if ($request->hasFile('hospital_image')) {
            $imagePath = $request->file('hospital_image')->store('hospitals', 'public'); // حفظ الصورة في storage/app/public/hospitals
            $imageName = basename($imagePath); // استخراج اسم الصورة فقط
            Log::debug('Image stored at:', [$imagePath]);
        } else {
            Log::debug('No image uploaded');
            $imageName = null;
        }

        try {
            // إنشاء المستخدم المرتبط
            $user = User::create([
                'name' => $request->hospital_name,
                'password' => Hash::make($request->password),
                'user_type' => 'Hospital',
                'is_active' => true,
            ]);
            Log::debug('User created:', $user->toArray());

            // إنشاء المستشفى وربطه بالمستخدم
            $hospital = Hospital::create([
                'hospital_name' => $request->hospital_name,
                'hospital_email' => $request->hospital_email,
                'hospital_address' => $request->hospital_address,
                'hospital_phone' => $request->hospital_phone,
                'hospital_image' => $imageName, // تخزين فقط اسم الصورة
                'user_id' => $user->user_id,
            ]);
            Log::debug('Hospital created:', $hospital->toArray());

            // إرجاع البيانات بدون كلمة المرور
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
    
        $request->validate([
            'hospital_name' => 'sometimes|string|max:255',
            'hospital_email' => 'sometimes|email|unique:hospitals,hospital_email,' . $hospital->hospital_id, 
            'hospital_address' => 'sometimes|string|max:255',
            'hospital_phone' => 'sometimes|string|max:15',
            'hospital_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        // تحديث الصورة إذا تم رفع صورة جديدة
        if ($request->hasFile('hospital_image')) {
            // حذف الصورة القديمة إن وجدت
            if ($hospital->hospital_image) {
                Storage::disk('public')->delete('hospitals/' . $hospital->hospital_image);
            }

            // رفع الصورة الجديدة
            $imagePath = $request->file('hospital_image')->store('hospitals', 'public');
            $imageName = basename($imagePath);
        } else {
            $imageName = $hospital->hospital_image;
        }
    
        // تحديث بيانات المستشفى
        $hospital->update([
            'hospital_name' => $request->hospital_name ?? $hospital->hospital_name,
            'hospital_email' => $request->hospital_email ?? $hospital->hospital_email,
            'hospital_address' => $request->hospital_address ?? $hospital->hospital_address,
            'hospital_phone' => $request->hospital_phone ?? $hospital->hospital_phone,
            'hospital_image' => $imageName,
        ]);
    
        return response()->json(['hospital' => $hospital], 200);
    }

    // استرجاع مستشفى معين
    public function show($hospital_id)
    {
        $hospital = Hospital::with('user')->findOrFail($hospital_id);
        return response()->json(['hospital' => $hospital], 200);
    }

    // استرجاع جميع المستشفيات
    public function index()
    {
        $hospitals = Hospital::all();
        return response()->json(['hospitals' => $hospitals], 200);
    }

    // حذف المستشفى والمستخدم المرتبط
    public function destroy($hospital_id)
    {
        try {
            $hospital = Hospital::findOrFail($hospital_id);
            
            // حذف الصورة المرتبطة إذا كانت موجودة
            if ($hospital->hospital_image) {
                Storage::disk('public')->delete('hospitals/' . $hospital->hospital_image);
            }

            // حذف المستخدم المرتبط
            $user = User::where('user_id', $hospital->user_id)->first();
            if ($user) {
                $user->delete();
            }

            // حذف المستشفى
            $hospital->delete();
    
            return response()->json(['message' => 'تم حذف المستشفى بنجاح'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'حدث خطأ أثناء حذف المستشفى',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
