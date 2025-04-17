<?php

namespace App\Http\Controllers;


use App\Models\Specialty;
use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Log;


class SpecialtyController extends Controller
{
    // جلب جميع التخصصات
    public function index()
    {
        return response()->json(Specialty::all(), Response::HTTP_OK);
    }
   
     
     
     



    // إنشاء تخصص جديد
    public function store(Request $request)
    {
        $request->validate([
            'specialty_name' => 'required|string|max:255',
            'specialty_description' => 'nullable|string',
        ]);

        $specialty = Specialty::create($request->all());
        return response()->json($specialty, Response::HTTP_CREATED);
    }

    // جلب تخصص معين
    public function show($id)
    {
        $specialty = Specialty::find($id);
        if (!$specialty) {
            return response()->json(['message' => 'Specialty not found'], Response::HTTP_NOT_FOUND);
        }
        return response()->json($specialty, Response::HTTP_OK);
    }
      // ✅ جلب أسماء التخصصات فقط لإظهارها في القائمة المنسدلة
      public function getSpecialties()
      {
          $specialties = DB::table('specialties')->select('specialty_name')->get();
          return response()->json($specialties);
      }
      

    // تحديث تخصص
    public function update(Request $request, $id)
    {
        $specialty = Specialty::find($id);
        if (!$specialty) {
            return response()->json(['message' => 'Specialty not found'], Response::HTTP_NOT_FOUND);
        }

        $request->validate([
            'specialty_name' => 'required|string|max:255',
            'specialty_description' => 'nullable|string',
        ]);

        $specialty->update($request->all());
        return response()->json($specialty, Response::HTTP_OK);
    }

    // حذف تخصص
    public function destroy($id)
    {
        $specialty = Specialty::find($id);
        if (!$specialty) {
            return response()->json(['message' => 'Specialty not found'], Response::HTTP_NOT_FOUND);
        }
        $specialty->delete();
        return response()->json(['message' => 'Specialty deleted successfully'], Response::HTTP_OK);
    }



  // تأكد من إضافة هذا السطر في أعلى الملف

  public function getSpecialtiesWithDoctorCount()
  {
      // استرجاع التخصصات مع عدد الأطباء لكل تخصص
      $specialties = Specialty::select('specialty_id', 'specialty_name')
                              ->withCount('doctors')
                              ->get();
  
      // إرجاع البيانات المطلوبة
      return response()->json($specialties);
  }
  
  


}