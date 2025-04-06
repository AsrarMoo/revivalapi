<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Hospital;

class EmergencyController extends Controller
{
    public function handle(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $latitude = $request->latitude;
        $longitude = $request->longitude;

        $hospitals = Hospital::select('*')
            ->selectRaw('(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance', [$latitude, $longitude, $latitude])
            ->orderBy('distance')
            ->limit(3)
            ->get();

        return response()->json([
            'status' => 'success',
            'hospitals' => $hospitals,
        ]);
    }
}

