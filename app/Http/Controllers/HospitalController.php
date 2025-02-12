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

        Log::debug('Received request data:', $request->all());

        // حفظ الصورة إذا وُجدت
        $imageName = null;
        if ($request->hasFile('hospital_image')) {
            $imagePath = $request->file('hospital_image')->store('hospitals', 'public');
            $imageName = basename($imagePath);
            Log::debug('Image stored at:', [$imagePath]);
        }

        try {
            $user = User::create([
                'name' => $request->hospital_name,
                'password' => Hash::make($request->password),
                'user_type' => 'Hospital',
                'is_active' => true,
            ]);

            Log::debug('User created:', ['user' => $user->toArray()]);

            $hospital = Hospital::create([
                'hospital_name' => $request->hospital_name,
                'hospital_email' => $request->hospital_email,
                'hospital_address' => $request->hospital_address,
                'hospital_phone' => $request->hospital_phone,
                'hospital_image' => $imageName,
                'user_id' => $user->user_id,
            ]);

            Log::debug('Hospital created:', ['hospital' => $hospital->toArray()]);

            return response()->json(['hospital' => $hospital, 'user' => $user->makeHidden(['password'])], 201);
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
            if ($hospital->hospital_image && Storage::disk('public')->exists('hospitals/' . $hospital->hospital_image)) {
                Storage::disk('public')->delete('hospitals/' . $hospital->hospital_image);
            }

            // رفع الصورة الجديدة
            $imagePath = $request->file('hospital_image')->store('hospitals', 'public');
            $hospital->hospital_image = basename($imagePath);
        }

        $hospital->update($request->only(['hospital_name', 'hospital_email', 'hospital_address', 'hospital_phone', 'hospital_image']));

        return response()->json(['hospital' => $hospital], 200);
    }

    // استرجاع مستشفى معين
    public function show($hospital_id)
    {
        $hospital = Hospital::with('user')->findOrFail($hospital_id);
        
        if ($hospital->hospital_image) {
            $hospital->hospital_image = url('storage/hospitals/' . $hospital->hospital_image);
        }
    
        return response()->json(['hospital' => $hospital], 200);
    }
    
    public function index()
    {
        $hospitals = Hospital::all();
    
        foreach ($hospitals as $hospital) {
            if ($hospital->hospital_image) {
                $hospital->hospital_image = url('storage/hospitals/' . $hospital->hospital_image);
                

            }
        }
    
        return response()->json(['hospitals' => $hospitals], 200);
    }
    
    
    // حذف المستشفى والمستخدم المرتبط به
    public function destroy($hospital_id)
    {
        try {
            $hospital = Hospital::findOrFail($hospital_id);
            $user = User::find($hospital->user_id);

            // حذف الصورة إذا كانت موجودة
            if ($hospital->hospital_image && Storage::disk('public')->exists('hospitals/' . $hospital->hospital_image)) {
                Storage::disk('public')->delete('hospitals/' . $hospital->hospital_image);
            }

            // حذف المستشفى
            $hospital->delete();
            Log::debug('Hospital deleted:', ['hospital_id' => $hospital_id]);

            // حذف المستخدم المرتبط إذا وُجد
            if ($user) {
                $user->delete();
                Log::debug('User deleted:', ['user_id' => $hospital->user_id]);
            }

            return response()->json(['message' => 'Hospital and associated user deleted successfully'], 200);
        } catch (\Exception $e) {
            Log::error('Error occurred during hospital deletion: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to delete hospital', 'error' => $e->getMessage()], 500);
        }
    }
}
