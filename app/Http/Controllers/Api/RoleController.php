<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index()
    {
        return Role::with('business')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'business_id' => 'required|exists:businesses,id',
        ]);

        return Role::create($data);
    }

    public function show($id)
    {
        return Role::with('business')->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $data = $request->validate([
            'name' => 'string',
        ]);

        $role->update($data);

        return $role;
    }

    public function destroy($id)
    {
        Role::findOrFail($id)->delete();

        return response()->json(['message' => 'Role deleted']);
    }
}
