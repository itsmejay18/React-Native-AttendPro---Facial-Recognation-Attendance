<!doctype html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f4f7fb;color:#10233d;font-family:Arial,sans-serif;">
    <main style="max-width:620px;margin:0 auto;padding:28px;background:#ffffff;border:1px solid #d8e1ed;border-radius:18px;">
        <p style="margin:0 0 8px;color:#21196b;font-size:12px;font-weight:700;letter-spacing:1.5px;">ATTENDPRO</p>
        <h1 style="margin:0 0 14px;font-size:24px;">Attendance {{ $action === 'time_out' ? 'time-out' : 'time-in' }} recorded</h1>
        <p style="line-height:1.6;">Good day, this is an automated attendance notification from AttendPro. {{ $person->full_name }}&rsquo;s attendance has been recorded successfully through facial recognition.</p>
        <table style="width:100%;border-collapse:collapse;margin:20px 0;">
            <tr><td style="padding:9px 0;color:#5f6f85;">Student</td><td style="padding:9px 0;text-align:right;font-weight:700;">{{ $person->full_name }}</td></tr>
            <tr><td style="padding:9px 0;color:#5f6f85;">Student ID</td><td style="padding:9px 0;text-align:right;font-weight:700;">{{ $person->institution_id }}</td></tr>
            <tr><td style="padding:9px 0;color:#5f6f85;">Date</td><td style="padding:9px 0;text-align:right;font-weight:700;">{{ $record->attendance_date->format('F d, Y') }}</td></tr>
            <tr><td style="padding:9px 0;color:#5f6f85;">{{ $action === 'time_out' ? 'Time out' : 'Time in' }}</td><td style="padding:9px 0;text-align:right;font-weight:700;">{{ ($action === 'time_out' ? $record->time_out : $record->time_in)?->format('h:i A') }}</td></tr>
            <tr><td style="padding:9px 0;color:#5f6f85;">Status</td><td style="padding:9px 0;text-align:right;font-weight:700;">{{ ucfirst($record->status) }}</td></tr>
            @if ($record->schedule)<tr><td style="padding:9px 0;color:#5f6f85;">Class / subject</td><td style="padding:9px 0;text-align:right;font-weight:700;">{{ $record->schedule->name }}</td></tr>@endif
            <tr><td style="padding:9px 0;color:#5f6f85;">Location</td><td style="padding:9px 0;text-align:right;font-weight:700;">{{ $record->schedule?->room_display ?? $record->location?->name ?? 'Campus attendance station' }}</td></tr>
        </table>
        <p style="margin:22px 0 0;color:#5f6f85;font-size:13px;line-height:1.5;">This is an automated attendance confirmation from {{ config('app.name') }}.</p>
    </main>
</body>
</html>
