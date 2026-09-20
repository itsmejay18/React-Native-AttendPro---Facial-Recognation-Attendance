<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReferenceDataController extends Controller
{
    public function department(Request $request): RedirectResponse
    {
        Department::query()->updateOrCreate(
            ['code' => $request->validate(['code' => ['required', 'string', 'max:30']])['code']],
            $request->validate([
                'name' => ['required', 'string', 'max:255'],
            ]) + ['is_active' => true],
        );

        return back()->with('success', 'Department saved.');
    }

    public function location(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'timezone' => ['required', 'timezone'],
        ]);

        Location::query()->updateOrCreate(['code' => $validated['code']], $validated + ['is_active' => true]);

        return back()->with('success', 'Location saved.');
    }
}
