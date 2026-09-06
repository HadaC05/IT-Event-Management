<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\EventStatus;
use App\Models\Role;
use App\Models\User;
use App\Models\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdviserUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $adviser;

    private UserStatus $active;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['SBO Adviser', 'SBO', 'Faculty', 'Student'] as $role) {
            Role::create(['name' => $role]);
        }

        $this->active = UserStatus::create(['label' => 'active']);
        UserStatus::create(['label' => 'inactive']);
        $this->adviser = User::factory()->create([
            'role_id' => Role::where('name', 'SBO Adviser')->value('id'),
            'status' => $this->active->id,
        ]);
    }

    public function test_only_sbo_adviser_can_access_user_management(): void
    {
        $student = User::factory()->create([
            'role_id' => Role::where('name', 'Student')->value('id'),
            'status' => $this->active->id,
        ]);

        $this->actingAs($student)->get('/adviser/users')->assertForbidden();
        $this->actingAs($this->adviser)->get('/adviser/users')->assertOk();
    }

    public function test_adviser_can_create_a_manageable_user(): void
    {
        $this->actingAs($this->adviser)->post('/adviser/users', [
            'first_name' => 'John',
            'last_name' => 'Cruz',
            'username' => 'john.cruz',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_id' => Role::where('name', 'SBO')->value('id'),
        ])->assertRedirect('/adviser/users');

        $this->assertDatabaseHas('users', ['email' => 'john@example.com', 'status' => $this->active->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'user_created']);
    }

    public function test_adviser_cannot_create_another_adviser_from_user_management(): void
    {
        $this->actingAs($this->adviser)->post('/adviser/users', [
            'first_name' => 'Second',
            'last_name' => 'Adviser',
            'username' => 'second.adviser',
            'email' => 'second@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_id' => Role::where('name', 'SBO Adviser')->value('id'),
        ])->assertSessionHasErrors('role_id');

        $this->assertDatabaseMissing('users', ['email' => 'second@example.com']);
    }

    public function test_adviser_can_edit_filter_and_deactivate_a_user(): void
    {
        $faculty = User::factory()->create([
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'role_id' => Role::where('name', 'Faculty')->value('id'),
            'status' => $this->active->id,
        ]);

        $this->actingAs($this->adviser)->put("/adviser/users/{$faculty->id}", [
            'first_name' => 'Anna',
            'last_name' => 'Reyes',
            'username' => $faculty->username,
            'email' => $faculty->email,
            'role_id' => $faculty->role_id,
            'password' => '',
            'password_confirmation' => '',
        ])->assertSessionHasNoErrors();

        $this->patch("/adviser/users/{$faculty->id}/status")->assertSessionHasNoErrors();
        $this->get('/adviser/users?role=Faculty&search=Anna&status=inactive')
            ->assertOk()
            ->assertSee('Account Overview')
            ->assertSee('Anna')
            ->assertSee('Reyes');

        $this->assertDatabaseHas('users', [
            'id' => $faculty->id,
            'first_name' => 'Anna',
            'status' => UserStatus::where('label', 'inactive')->value('id'),
        ]);
        $this->assertDatabaseHas('activity_logs', ['subject_user_id' => $faculty->id, 'action' => 'user_status_changed']);
    }

    public function test_adviser_can_assign_and_unassign_an_sbo_user(): void
    {
        $sbo = User::factory()->create([
            'role_id' => Role::where('name', 'SBO')->value('id'),
            'status' => $this->active->id,
        ]);
        $eventStatus = EventStatus::create(['label' => 'active']);
        $event = Event::create([
            'title' => 'Foundation Day',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDays(2),
            'event_status_id' => $eventStatus->id,
        ]);

        $this->actingAs($this->adviser)
            ->post("/adviser/users/{$sbo->id}/events", ['event_id' => $event->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('event_user', ['user_id' => $sbo->id, 'event_id' => $event->id]);
        $this->get('/adviser/users')->assertOk()->assertSee('Foundation Day');

        $this->actingAs($this->adviser)
            ->delete("/adviser/users/{$sbo->id}/events/{$event->id}")
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('event_user', ['user_id' => $sbo->id, 'event_id' => $event->id]);
        $this->assertSame(2, ActivityLog::whereIn('action', ['event_assigned', 'event_unassigned'])->count());
        $this->assertDatabaseHas('users', ['id' => $sbo->id]);
        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }

    public function test_student_cannot_be_assigned_to_an_event(): void
    {
        $student = User::factory()->create([
            'role_id' => Role::where('name', 'Student')->value('id'),
            'status' => $this->active->id,
        ]);
        $event = Event::create([
            'title' => 'Sports Festival',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDays(2),
        ]);

        $this->actingAs($this->adviser)
            ->post("/adviser/users/{$student->id}/events", ['event_id' => $event->id])
            ->assertStatus(422);

        $this->assertDatabaseCount('event_user', 0);
    }

    public function test_an_unassigned_event_can_be_restored_through_undo_endpoint(): void
    {
        $faculty = User::factory()->create([
            'role_id' => Role::where('name', 'Faculty')->value('id'),
            'status' => $this->active->id,
        ]);
        $event = Event::create([
            'title' => 'IT Expo',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDays(2),
        ]);
        $faculty->assignedEvents()->attach($event);

        $response = $this->actingAs($this->adviser)
            ->deleteJson("/adviser/users/{$faculty->id}/events/{$event->id}")
            ->assertOk()
            ->assertJsonStructure(['message', 'undo_url']);

        $this->assertDatabaseMissing('event_user', ['user_id' => $faculty->id, 'event_id' => $event->id]);

        $this->postJson($response->json('undo_url'))
            ->assertOk()
            ->assertJsonFragment(['message' => "{$faculty->full_name} was reassigned to IT Expo."]);

        $this->assertDatabaseHas('event_user', ['user_id' => $faculty->id, 'event_id' => $event->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'event_assignment_restored']);
    }

    public function test_adviser_dashboard_shows_current_totals(): void
    {
        User::factory()->create([
            'role_id' => Role::where('name', 'Faculty')->value('id'),
            'status' => $this->active->id,
        ]);
        Event::create([
            'title' => 'IT Days',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDays(2),
        ]);

        $this->actingAs($this->adviser)->get('/dashboard')
            ->assertOk()
            ->assertSee('Total Users')
            ->assertSee('Upcoming Events')
            ->assertSee('IT Events');
    }

    public function test_deactivated_user_with_an_existing_session_loses_access(): void
    {
        $student = User::factory()->create([
            'role_id' => Role::where('name', 'Student')->value('id'),
            'status' => UserStatus::where('label', 'inactive')->value('id'),
        ]);

        $this->actingAs($student)->get('/dashboard')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }
}
