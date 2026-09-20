<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalAttendanceController extends Controller
{
    public function __invoke(Request $request): View
    {
        $person = $request->user()->person()
            ->with(['department', 'activeFacialProfiles'])
            ->first();

        return view('portal.attendance', [
            'person' => $person,
            'records' => $person?->attendanceRecords()
                ->with(['schedule', 'location', 'terminal'])
                ->latest('attendance_date')
                ->latest('time_in')
                ->paginate(20),
        ]);
    }
}
