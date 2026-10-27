<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ErrorExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $authenticateByDefault = false;

    public function test_every_web_response_includes_a_request_reference(): void
    {
        $this->get('/ingresar')
            ->assertOk()
            ->assertHeader('X-Request-ID');
    }

    public function test_production_not_found_page_is_clear_and_traceable(): void
    {
        $this->authenticate();
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn () => 'production');

        try {
            $this->get('/una-ruta-que-no-existe')
                ->assertNotFound()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Errors/HttpError')
                    ->where('status', 404)
                    ->where('homeUrl', route('dashboard'))
                    ->where('requestId', fn ($value) => is_string($value) && $value !== ''));
        } finally {
            app()->detectEnvironment(fn () => $originalEnvironment);
        }
    }

    public function test_production_json_errors_keep_a_safe_reference(): void
    {
        $this->authenticate();
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn () => 'production');

        try {
            $this->getJson('/una-ruta-api-que-no-existe')
                ->assertNotFound()
                ->assertJsonStructure(['message', 'error_id'])
                ->assertHeader('X-Request-ID');
        } finally {
            app()->detectEnvironment(fn () => $originalEnvironment);
        }
    }
}
