<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Terminal;
use App\Services\TerminalTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TerminalController extends Controller
{
    public function index(): View
    {
        $offlineBefore = now()->subMinutes(config('attendpro.terminal.offline_after_minutes'));
        Terminal::query()->where('status', 'online')->where('last_seen_at', '<', $offlineBefore)->update(['status' => 'offline']);

        return view('terminals.index', [
            'terminals' => Terminal::query()->with('location')->latest()->paginate(20),
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, TerminalTokenService $tokens): RedirectResponse
    {
        $validated = $this->validated($request);
        $terminal = Terminal::query()->create($validated + [
            'uuid' => (string) Str::uuid(), 'status' => 'offline', 'is_active' => true,
            'capabilities' => ['recognition' => true, 'face_sync' => true, 'enrollment' => $request->boolean('can_enroll')],
        ]);
        $plainTextToken = $tokens->issue($terminal);

        return back()->with('success', 'Terminal created. Copy its token now; it will not be shown again.')
            ->with('terminal_token', $plainTextToken)->with('terminal_name', $terminal->name);
    }

    public function update(Request $request, Terminal $terminal): RedirectResponse
    {
        $terminal->update($this->validated($request, $terminal) + [
            'is_active' => $request->boolean('is_active'),
            'capabilities' => ['recognition' => true, 'face_sync' => true, 'enrollment' => $request->boolean('can_enroll')],
        ]);

        return back()->with('success', 'Terminal updated.');
    }

    public function rotate(Terminal $terminal, TerminalTokenService $tokens): RedirectResponse
    {
        $plainTextToken = $tokens->issue($terminal);

        return back()->with('success', 'Terminal token rotated. The previous token no longer works.')
            ->with('terminal_token', $plainTextToken)->with('terminal_name', $terminal->name);
    }

    private function validated(Request $request, ?Terminal $terminal = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('terminals')->ignore($terminal)],
            'name' => ['required', 'string', 'max:255'],
            'location_id' => ['required', 'exists:locations,id'],
        ]);
    }
}
