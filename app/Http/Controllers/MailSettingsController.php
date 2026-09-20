<?php

namespace App\Http\Controllers;

use App\Services\MailConfiguration;
use App\Mail\EmailSettingsTest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class MailSettingsController extends Controller
{
    public function index(MailConfiguration $mail): View
    {
        return view('settings.email-notifications', ['settings' => $mail->current()]);
    }

    public function update(Request $request, MailConfiguration $mail): RedirectResponse
    {
        $settings = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'mailer' => ['required', Rule::in(['smtp', 'log'])],
            'host' => ['required_if:mailer,smtp', 'nullable', 'string', 'max:255'],
            'port' => ['required_if:mailer,smtp', 'nullable', 'integer', 'between:1,65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:500'],
            'scheme' => ['required', Rule::in(['smtp', 'smtps'])],
            'timeout' => ['required', 'integer', 'between:1,60'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
        ]);

        $settings['enabled'] = $request->boolean('enabled');
        $mail->save($settings);

        return back()->with('success', 'Email notification settings saved. The next recorded time-in or time-out will use this sender.');
    }

    public function sendTest(Request $request, MailConfiguration $mail): RedirectResponse
    {
        $validated = $request->validate([
            'recipient' => ['required', 'email', 'max:255'],
        ]);

        if ($missing = $mail->smtpMissing()) {
            return back()->withErrors(['recipient' => 'Gmail SMTP is not configured. Check '.implode(' and ', $missing).'.']);
        }

        try {
            Mail::to($validated['recipient'])->send(new EmailSettingsTest);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['recipient' => 'The test email could not be sent. Check the Gmail SMTP settings and app password.']);
        }

        return back()->with('success', 'Test email sent to '.$validated['recipient'].'. Check that inbox and its spam folder.');
    }
}
