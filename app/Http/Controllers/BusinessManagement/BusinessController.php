<?php

namespace App\Http\Controllers\BusinessManagement;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BusinessController extends Controller
{
    public function index()
    {
        try {
            $user = auth('api')->user();
            $businesses = Business::where('id', $user->business_id)->get();
            return response()->json([
                'status' => 'success',
                'data' => $businesses
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get businesses',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(Request $request)
    {
        try {
            $user = auth('api')->user();
            $business = Business::findOrFail($user->business_id);

            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'industry' => ['required', 'string', 'max:255'],
                'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            ]);

            if ($request->hasFile('logo')) {
                $path = $request->file('logo')->store('logos', 'public');
                $validated['logo'] = $path;
            }

            $business->update($validated);

            return response()->json([
                'message' => 'Business updated successfully.',
                'business' => $business->fresh(),
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update business',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
