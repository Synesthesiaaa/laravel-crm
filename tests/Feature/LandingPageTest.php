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
            ->assertSee('Run campaign calls and CRM work from one connected workspace')
            ->assertSee('See how it works')
            ->assertSee('Sign in')
            ->assertSee('Browser calling')
            ->assertSee('Campaign-aware workflows')
            ->assertSee('Live call state')
            ->assertSee('Reporting');
    }
}
