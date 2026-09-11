<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Where IT events');
        $response->assertSee('No featured events yet');
        $response->assertDontSee('IT Days 2026');
        $response->assertDontSee('IT Expo 2026');
    }
}
