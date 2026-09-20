<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleAuthenticatedSessionController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return $this->configurationError();
        }

        $state = Str::random(48);
        $request->session()->put('google_oauth_state', $state);

        $query = http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]);

        return redirect('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return $this->configurationError();
        }

        $expectedState = (string) $request->session()->pull('google_oauth_state', '');
        if ($expectedState === '' || ! hash_equals($expectedState, (string) $request->string('state'))) {
            return $this->loginError('Google sign-in could not be verified. Please try again.');
        }

        if ($request->filled('error') || ! $request->filled('code')) {
            return $this->loginError('Google sign-in was cancelled or did not return an authorization code.');
        }

        try {
            $tokenResponse = Http::asForm()->acceptJson()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                'code' => $request->string('code')->toString(),
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => config('services.google.redirect'),
                'grant_type' => 'authorization_code',
            ]);

            $accessToken = (string) $tokenResponse->json('access_token', '');
            if (! $tokenResponse->successful() || $accessToken === '') {
                return $this->loginError('Google sign-in could not be completed. Please try again.');
            }

            $profileResponse = Http::withToken($accessToken)->acceptJson()->timeout(10)
                ->get('https://openidconnect.googleapis.com/v1/userinfo');
            $email = Str::lower((string) $profileResponse->json('email', ''));
            $emailVerified = filter_var($profileResponse->json('email_verified', false), FILTER_VALIDATE_BOOL);

            if (! $profileResponse->successful() || ! $emailVerified || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->loginError('Google did not provide a verified email address.');
            }
        } catch (\Throwable) {
            return $this->loginError('Google sign-in is temporarily unavailable. Please try again.');
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $user || ! $user->is_active) {
            return $this->loginError('This Google email is not authorized for AttendPro.');
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    private function configurationError(): RedirectResponse
    {
        return $this->loginError('Google OAuth is not configured. Check GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET.');
    }

    private function loginError(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
