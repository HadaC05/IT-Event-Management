<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Role;
use App\Models\SchoolYear;
use App\Models\Score;
use App\Models\ScoreCategory;
use App\Models\Team;
use App\Models\User;
use App\Models\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoreManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $adviser;

    private SchoolYear $schoolYear;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['SBO Adviser', 'SBO', 'Faculty', 'Student'] as $role) {
            Role::create(['name' => $role]);
        }

        $active = UserStatus::create(['label' => 'active']);
        $this->adviser = User::factory()->create([
            'role_id' => Role::where('name', 'SBO Adviser')->value('id'),
            'status' => $active->id,
        ]);
        $this->schoolYear = SchoolYear::create(['label' => '2026–2027']);
    }

    public function test_only_adviser_can_access_score_management(): void
    {
        $event = $this->event();
        $student = User::factory()->create([
            'role_id' => Role::where('name', 'Student')->value('id'),
            'status' => UserStatus::where('label', 'active')->value('id'),
        ]);

        $this->actingAs($student)->get(route('adviser.scores.index'))->assertForbidden();
        $this->actingAs($student)->get(route('adviser.scores.show', $event))->assertForbidden();
        $this->actingAs($this->adviser)->get(route('adviser.scores.index'))
            ->assertOk()
            ->assertSee('Manage Scores')
            ->assertSee('Set Up Scoring');
    }

    public function test_adviser_can_create_update_and_remove_scoring_categories(): void
    {
        $event = $this->event();

        $this->actingAs($this->adviser)->post(route('adviser.scores.categories.store', $event), [
            'name' => 'Creativity',
            'max_points' => 50,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $category = ScoreCategory::firstOrFail();
        $this->assertDatabaseHas('score_categories', [
            'event_id' => $event->id,
            'name' => 'Creativity',
            'max_points' => 50,
        ]);

        $this->post(route('adviser.scores.categories.store', $event), [
            'name' => 'Creativity',
            'max_points' => 100,
        ])->assertSessionHasErrors('name');

        $this->put(route('adviser.scores.categories.update', [$event, $category]), [
            'name' => 'Performance',
            'max_points' => 75,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('score_categories', ['id' => $category->id, 'name' => 'Performance', 'max_points' => 75]);

        $otherEvent = $this->event('Other Event');
        $this->put(route('adviser.scores.categories.update', [$otherEvent, $category]), [
            'name' => 'Invalid move',
            'max_points' => 100,
        ])->assertNotFound();

        $this->delete(route('adviser.scores.categories.destroy', [$event, $category]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('score_categories', ['id' => $category->id]);
    }

    public function test_adviser_can_record_update_and_clear_scores_with_live_rank_data(): void
    {
        $event = $this->event();
        $performance = $this->category($event, 'Performance', 100, 1);
        $sportsmanship = $this->category($event, 'Sportsmanship', 50, 2);
        $alpha = $this->team('Alpha Tribe');
        $beta = $this->team('Beta Tribe');
        $gamma = $this->team('Gamma Tribe');

        $payload = ['scores' => [
            $performance->id => [$alpha->id => 80, $beta->id => 70, $gamma->id => ''],
            $sportsmanship->id => [$alpha->id => 20, $beta->id => 30, $gamma->id => 40],
        ]];

        $this->actingAs($this->adviser)->put(route('adviser.scores.update', $event), $payload)
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertDatabaseCount('scores', 5);
        $this->assertDatabaseHas('scores', [
            'event_id' => $event->id,
            'team_id' => $alpha->id,
            'score_category_id' => $performance->id,
            'points' => 80,
            'recorded_by' => $this->adviser->id,
        ]);
        $this->assertDatabaseHas('activity_logs', ['event_id' => $event->id, 'action' => 'scores_updated']);

        $response = $this->get(route('adviser.scores.show', $event))->assertOk()->assertSee('Tribe Score Matrix');
        $teams = $response->viewData('teams');
        $this->assertSame(['Alpha Tribe', 'Beta Tribe', 'Gamma Tribe'], $teams->pluck('name')->all());
        $this->assertSame([1, 1, 3], $teams->pluck('rank')->all());
        $this->assertSame(240.0, $response->viewData('summary')['total_points']);

        $payload['scores'][$performance->id][$alpha->id] = 90;
        $payload['scores'][$sportsmanship->id][$gamma->id] = '';
        $this->put(route('adviser.scores.update', $event), $payload)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('scores', ['team_id' => $alpha->id, 'score_category_id' => $performance->id, 'points' => 90]);
        $this->assertDatabaseMissing('scores', ['team_id' => $gamma->id, 'score_category_id' => $sportsmanship->id]);
    }

    public function test_scores_are_limited_to_event_categories_available_tribes_and_category_maximums(): void
    {
        $event = $this->event();
        $category = $this->category($event, 'Performance', 100);
        $team = $this->team('Alpha Tribe');

        $this->actingAs($this->adviser)->put(route('adviser.scores.update', $event), [
            'scores' => [$category->id => [$team->id => 101]],
        ])->assertSessionHasErrors("scores.{$category->id}.{$team->id}");

        $otherEvent = $this->event('Other Event');
        $otherCategory = $this->category($otherEvent, 'General', 100);
        $this->put(route('adviser.scores.update', $event), [
            'scores' => [$otherCategory->id => [$team->id => 50]],
        ])->assertSessionHasErrors('scores');

        $inactiveTeam = $this->team('Inactive Tribe', false);
        $this->put(route('adviser.scores.update', $event), [
            'scores' => [$category->id => [$inactiveTeam->id => 50]],
        ])->assertSessionHasErrors('scores');

        Score::create([
            'event_id' => $event->id,
            'team_id' => $team->id,
            'score_category_id' => $category->id,
            'points' => 90,
            'recorded_by' => $this->adviser->id,
        ]);

        $this->put(route('adviser.scores.categories.update', [$event, $category]), [
            'name' => 'Performance',
            'max_points' => 80,
        ])->assertSessionHasErrors('max_points');
    }

    public function test_removing_a_category_also_removes_its_score_entries(): void
    {
        $event = $this->event();
        $category = $this->category($event, 'Performance', 100);
        $team = $this->team('Alpha Tribe');
        Score::create([
            'event_id' => $event->id,
            'team_id' => $team->id,
            'score_category_id' => $category->id,
            'points' => 75,
        ]);

        $this->actingAs($this->adviser)->delete(route('adviser.scores.categories.destroy', [$event, $category]))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('score_categories', ['id' => $category->id]);
        $this->assertDatabaseMissing('scores', ['score_category_id' => $category->id]);
    }

    private function event(string $title = 'IT Days'): Event
    {
        return Event::create([
            'title' => $title,
            'location' => 'Main Hall',
            'start_at' => now()->startOfDay()->addHours(8),
            'end_at' => now()->startOfDay()->addHours(17),
            'created_by' => $this->adviser->id,
        ]);
    }

    private function category(Event $event, string $name, float $maximum, int $order = 1): ScoreCategory
    {
        return ScoreCategory::create([
            'event_id' => $event->id,
            'name' => $name,
            'max_points' => $maximum,
            'sort_order' => $order,
        ]);
    }

    private function team(string $name, bool $active = true): Team
    {
        return Team::create([
            'school_year_id' => $this->schoolYear->id,
            'name' => $name,
            'color' => '#41B06E',
            'is_active' => $active,
        ]);
    }
}
