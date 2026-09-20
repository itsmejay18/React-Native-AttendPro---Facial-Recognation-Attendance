<?php

namespace App\Http\Controllers;

use App\Services\SystemApiConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SystemApiSettingsController extends Controller
{
    public function index(SystemApiConfiguration $configuration): View
    {
        return view('settings.api', ['settings' => $configuration->current()]);
    }

    public function update(Request $request, SystemApiConfiguration $configuration): RedirectResponse
    {
        $settings = $request->validate([
            'google_client_id' => ['nullable', 'string', 'max:255'],
            'google_client_secret' => ['nullable', 'string', 'max:500'],
            'google_redirect_uri' => ['nullable', 'url', 'max:255'],
            'python_url' => ['required', 'url', 'max:255'],
            'python_timeout' => ['required', 'integer', 'between:1,120'],
            'laravel_api_url' => ['required', 'url', 'max:255'],
            'cors_allowed_origins' => ['nullable', 'string', 'max:1000'],
            'public_attendance_enabled' => ['nullable', 'boolean'],
            'public_attendance_allowed_ips' => ['nullable', 'string', 'max:1000'],
        ]);

        $settings['public_attendance_enabled'] = $request->boolean('public_attendance_enabled');
        $configuration->save($settings);

        return back()->with('success', 'System API settings saved. Restart the local recognition server if its connection URL changed.');
    }
}
