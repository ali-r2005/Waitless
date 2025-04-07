<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index()
    {
        return Staff::with(['user', 'business', 'role'])->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'business_id' => 'required|exists:businesses,id',
            'role_id' => 'nullable|exists:roles,id',
            'position' => 'nullable|string',
        ]);

        return Staff::create($data);
    }

    public function show($id)
    {
        return Staff::with(['user', 'business', 'role'])->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $staff = Staff::findOrFail($id);

        $data = $request->validate([
            'role_id' => 'nullable|exists:roles,id',
            'position' => 'nullable|string',
        ]);

        $staff->update($data);

        return $staff;
    }

    public function destroy($id)
    {
        Staff::findOrFail($id)->delete();

        return response()->json(['message' => 'Staff deleted']);
    }
}