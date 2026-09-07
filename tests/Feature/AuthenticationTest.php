<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_opens_the_homepage_modal(): void
    {
        $this->get('/login')->assertRedirect(route('home', ['login' => 1]));
        $this->get('/?login=1')->assertOk()->assertSee('Sign in to your account');
    }

    public function test_user_can_log_in_with_username(): void
    {
        $role = Role::create(['name' => 'SBO Adviser']);
        $status = UserStatus::create(['label' => 'active']);
        $user = User::factory()->create([
            'username' => 'sbo.adviser',
            'role_id' => $role->id,
            'status' => $status->id,
            'password' => 'SBOAdviser@2026',
        ]);

        $this->post('/login', [
            'login' => 'sbo.adviser',
            'password' => 'SBOAdviser@2026',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $status = UserStatus::create(['label' => 'inactive']);
        User::factory()->create([
            'status' => $status->id,
            'password' => 'password',
        ]);

        $this->post('/login', [
            'login' => User::first()->email,
            'password' => 'password',
        ])->assertSessionHasErrors('login');

        $this->assertGuest();
    }
}
