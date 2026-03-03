<?php

namespace App\Http\Controllers\BusinessManagement;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Symfony\Component\HttpFoundation\Response;

class BusinessController extends Controller
{
    public function index()
    {
        try {
            $user = auth()->user();
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
}
