<?php
namespace App\Http\Controllers;

use App\Models\Medication;
use Illuminate\Http\Request;

class MedicationController extends Controller
{
    // جلب كل البيانات
    public function index()
    {
        $medications = Medication::all();
        return response()->json($medications);
    }

    // إضافة دواء جديد
    public function store(Request $request)
    {
        $validated = $request->validate([
            'medication_name' => 'required|string|max:255',
            'medication_description' => 'nullable|string',
        ]);

        $medication = Medication::create($validated);
        return response()->json($medication, 201);
    }

    // تعديل دواء
    public function update(Request $request, $id)
    {
        $medication = Medication::find($id);
        if (!$medication) {
            return response()->json(['message' => 'Medication not found'], 404);
        }

        $validated = $request->validate([
            'medication_name' => 'required|string|max:255',
            'medication_description' => 'nullable|string',
        ]);

        $medication->update($validated);
        return response()->json($medication);
    }

    // حذف دواء
    public function destroy($id)
    {
        $medication = Medication::find($id);
        if (!$medication) {
            return response()->json(['message' => 'Medication not found'], 404);
        }

        $medication->delete();
        return response()->json(['message' => 'Medication deleted successfully']);
    }

    // جلب اسم الدواء فقط
    public function getMedicationNames()
    {
        $medications = Medication::select('medication_name')->get();
        return response()->json($medications);
    }
}
