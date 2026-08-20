<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index()
    {
        $employees = \App\Models\User::with(['branch', 'division'])->paginate(15);
        return view('admin.employees.index', compact('employees'));
    }

    public function create()
    {
        $branches = \App\Models\Branch::all();
        $divisions = \App\Models\Division::all();
        return view('admin.employees.create', compact('branches', 'divisions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'nik' => 'nullable|string|unique:users',
            'phone_number' => 'nullable|string',
            'branch_id' => 'nullable|exists:branches,id',
            'division_id' => 'nullable|exists:divisions,id',
            'position' => 'nullable|string|max:255',
            'system_role' => 'required|string|in:admin,head,director,user',
            'is_active' => 'boolean',
        ]);

        $validated['password'] = \Illuminate\Support\Facades\Hash::make($validated['password']);
        $validated['is_active'] = $request->has('is_active');

        \App\Models\User::create($validated);

        return redirect()->route('admin.employees.index')->with('success', 'Employee created successfully.');
    }

    public function show(string $id)
    {
        $employee = \App\Models\User::with(['branch', 'division'])->findOrFail($id);
        return view('admin.employees.show', compact('employee'));
    }

    public function edit(string $id)
    {
        $employee = \App\Models\User::findOrFail($id);
        $branches = \App\Models\Branch::all();
        $divisions = \App\Models\Division::all();
        return view('admin.employees.edit', compact('employee', 'branches', 'divisions'));
    }

    public function update(Request $request, string $id)
    {
        $employee = \App\Models\User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $employee->id,
            'password' => 'nullable|string|min:8',
            'nik' => 'nullable|string|unique:users,nik,' . $employee->id,
            'phone_number' => 'nullable|string',
            'branch_id' => 'nullable|exists:branches,id',
            'division_id' => 'nullable|exists:divisions,id',
            'position' => 'nullable|string|max:255',
            'system_role' => 'required|string|in:admin,head,director,user',
            'is_active' => 'boolean',
        ]);

        if ($request->filled('password')) {
            $validated['password'] = \Illuminate\Support\Facades\Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $validated['is_active'] = $request->has('is_active');

        $employee->update($validated);

        return redirect()->route('admin.employees.index')->with('success', 'Employee updated successfully.');
    }

    public function destroy(string $id)
    {
        $employee = \App\Models\User::findOrFail($id);
        $employee->delete();
        return redirect()->route('admin.employees.index')->with('success', 'Employee deleted successfully.');
    }
}
