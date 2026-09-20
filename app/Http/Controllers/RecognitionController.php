<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\RecognitionEvent;
use App\Models\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RecognitionController extends Controller
{
    public function index(Request $request): View
    {
        $enrollmentId = $request->string('enroll')->trim()->toString();
        $today = today();
        $sessions = Schedule::query()
            ->with('location')
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', $today))
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>=', $today))
            ->get()
            ->filter(fn (Schedule $schedule) => in_array($today->dayOfWeekIso, array_map('intval', $schedule->days_of_week), true))
            ->sortBy('starts_at')
            ->values();

        return view('recognition', [
            'enrollmentPerson' => $enrollmentId !== ''
                ? Person::query()->where('status', 'active')->where('institution_id', $enrollmentId)->first()
                : null,
            'events' => RecognitionEvent::query()->with(['person', 'terminal.location'])->latest('captured_at')->limit(15)->get(),
            'pendingExceptions' => RecognitionEvent::query()->with('terminal.location')
                ->where('review_status', 'pending')->latest('captured_at')->paginate(15),
            'sessions' => $sessions,
        ]);
    }

    public function activity(): JsonResponse
    {
        $events = RecognitionEvent::query()->with(['person', 'terminal'])->latest('captured_at')->limit(15)->get();

        return response()->json(['data' => $events->map(fn (RecognitionEvent $event) => [
            'uuid' => $event->uuid,
            'label' => $event->person?->full_name ?? ucfirst($event->result).' face',
            'terminal' => $event->terminal?->name ?? 'Laravel recognition',
            'result' => $event->result,
            'confidence' => $event->confidence !== null ? round($event->confidence * 100) : null,
            'captured_at' => $event->captured_at->toIso8601String(),
            'captured_human' => $event->captured_at->diffForHumans(),
        ])->values()]);
    }

    public function review(Request $request, RecognitionEvent $recognitionEvent): RedirectResponse
    {
        $validated = $request->validate([
            'review_status' => ['required', Rule::in(['reviewed', 'dismissed'])],
        ]);

        $recognitionEvent->update($validated + ['reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);

        return back()->with('success', 'Recognition exception reviewed.');
    }
}
