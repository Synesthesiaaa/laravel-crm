<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_root_renders_the_crm_landing_page(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertViewIs('welcome')
            ->assertSee('Handle calls, customer details, and follow-ups in one place')
            ->assertSee('See how it works')
            ->assertSee('Sign in')
            ->assertSee('Call from your browser')
            ->assertSee('Customer details in one place')
            ->assertSee('Up-to-date call status')
            ->assertSee('Reports');
    }
}
