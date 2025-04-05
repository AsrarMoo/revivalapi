<?php
namespace App\Http\Controllers;

use App\Models\Test;
use Illuminate\Http\Request;

class TestController extends Controller
{
    // جلب كل البيانات
    public function index()
    {
        $tests = Test::all();
        return response()->json($tests);
    }

    // إضافة فحص جديد
    public function store(Request $request)
    {
        $validated = $request->validate([
            'test_name' => 'required|string|max:255',
            'test_description' => 'nullable|string',
        ]);

        $test = Test::create($validated);
        return response()->json($test, 201);
    }

    // تعديل فحص
    public function update(Request $request, $id)
    {
        $test = Test::find($id);
        if (!$test) {
            return response()->json(['message' => 'Test not found'], 404);
        }

        $validated = $request->validate([
            'test_name' => 'required|string|max:255',
            'test_description' => 'nullable|string',
        ]);

        $test->update($validated);
        return response()->json($test);
    }

    // حذف فحص
    public function destroy($id)
    {
        $test = Test::find($id);
        if (!$test) {
            return response()->json(['message' => 'Test not found'], 404);
        }

        $test->delete();
        return response()->json(['message' => 'Test deleted successfully']);
    }

    // جلب اسم الفحص فقط
    public function getTestNames()
    {
        $tests = Test::select('test_name')->get();
        return response()->json($tests);
    }
}
