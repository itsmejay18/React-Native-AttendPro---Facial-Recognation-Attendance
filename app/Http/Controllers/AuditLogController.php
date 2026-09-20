<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'action' => ['nullable', 'string', 'max:50'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $logs = AuditLog::query()->with('user')
            ->when($validated['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where(fn ($inner) => $inner
                ->where('subject_type', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%"))))
            ->latest('created_at')->paginate(30)->withQueryString();

        return view('audit-logs.index', ['logs' => $logs]);
    }
}
