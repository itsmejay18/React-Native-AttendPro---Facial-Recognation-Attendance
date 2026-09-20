<?php

namespace Tests\Feature;

use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    public function test_google_login_reports_a_safe_error_when_it_is_not_configured(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
            'services.google.redirect' => null,
        ]);

        $this->get(route('auth.google.redirect'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }
}
