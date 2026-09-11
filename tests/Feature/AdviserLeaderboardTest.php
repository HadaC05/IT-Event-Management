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

class AdviserLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    private User $adviser;

    private UserStatus $activeStatus;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['SBO Adviser', 'SBO', 'Faculty', 'Student'] as $role) {
            Role::create(['name' => $role]);
        }

        $this->activeStatus = UserStatus::create(['label' => 'active']);
        $this->adviser = $this->user('SBO Adviser');
    }

    public function test_leaderboard_is_available_only_to_advisers_and_is_linked_in_navigation(): void
    {
        $student = $this->user('Student');

        $this->actingAs($student)->get(route('adviser.leaderboard.index'))->assertForbidden();

        $this->actingAs($this->adviser)
            ->get(route('adviser.leaderboard.index'))
            ->assertOk()
            ->assertSee('Leaderboard')
            ->assertSee('Choose what to compare')
            ->assertSee(route('adviser.leaderboard.index'), false);
    }

    public function test_overall_leaderboard_uses_competition_ranks_and_keeps_unscored_tribes_unranked(): void
    {
        $year = SchoolYear::create(['label' => '2026–2027']);
        $event = $this->event('IT Games');
        $category = $this->category($event, 'Performance');
        $alpha = $this->team($year, 'Alpha');
        $beta = $this->team($year, 'Beta');
        $this->team($year, 'Gamma');

        $this->score($event, $category, $alpha, 100);
        $this->score($event, $category, $beta, 100);

        $response = $this->actingAs($this->adviser)
            ->get(route('adviser.leaderboard.index'))
            ->assertOk()
            ->assertSee('The lead is currently tied')
            ->assertSee('Not scored');

        $rankings = $response->viewData('rankings');

        $this->assertSame(['Alpha', 'Beta', 'Gamma'], $rankings->pluck('name')->all());
        $this->assertSame([1, 1, null], $rankings->pluck('rank')->all());
        $this->assertSame([100.0, 100.0, 0.0], $rankings->pluck('total_score')->all());
        $this->assertSame(2, $response->viewData('summary')['ranked_teams']);
        $this->assertSame(200.0, $response->viewData('summary')['points']);
        $this->assertSame(0.0, $response->viewData('summary')['lead']);

        $filtered = $this->get(route('adviser.leaderboard.index', ['search' => 'beta']))->assertOk();
        $this->assertSame(['Beta'], $filtered->viewData('rankings')->pluck('name')->all());
        $this->assertSame(1, $filtered->viewData('rankings')->first()->rank);
        $this->assertSame(2, $filtered->viewData('summary')['ranked_teams']);
    }

    public function test_event_category_and_school_year_filters_limit_the_standings(): void
    {
        $currentYear = SchoolYear::create(['label' => '2026–2027']);
        $previousYear = SchoolYear::create(['label' => '2025–2026']);
        $event = $this->event('Foundation Day', 'selected_tribes');
        $performance = $this->category($event, 'Performance', 100, 1);
        $sportsmanship = $this->category($event, 'Sportsmanship', 50, 2);
        $alpha = $this->team($currentYear, 'Alpha');
        $beta = $this->team($currentYear, 'Beta');
        $unrelated = $this->team($currentYear, 'Unrelated');
        $historic = $this->team($previousYear, 'Historic', false);
        $event->audienceTeams()->attach([$alpha->id, $beta->id]);

        $this->score($event, $performance, $alpha, 90);
        $this->score($event, $sportsmanship, $alpha, 10);
        $this->score($event, $performance, $beta, 80);
        $this->score($event, $performance, $historic, 70);

        $response = $this->actingAs($this->adviser)->get(route('adviser.leaderboard.index', [
            'event_id' => $event->id,
            'school_year_id' => $currentYear->id,
            'category_id' => $performance->id,
        ]))->assertOk()->assertSee('Category ranking');

        $rankings = $response->viewData('rankings');
        $this->assertSame(['Alpha', 'Beta'], $rankings->pluck('name')->all());
        $this->assertSame([90.0, 80.0], $rankings->pluck('total_score')->all());
        $this->assertFalse($rankings->contains('id', $unrelated->id));
        $this->assertFalse($rankings->contains('id', $historic->id));
        $this->assertSame(['Performance', 'Sportsmanship'], $response->viewData('categories')->pluck('name')->all());
    }

    public function test_category_must_belong_to_the_selected_event(): void
    {
        $event = $this->event('IT Games');
        $otherEvent = $this->event('Culture Fest');
        $otherCategory = $this->category($otherEvent, 'Creativity');

        $this->actingAs($this->adviser)
            ->from(route('adviser.leaderboard.index'))
            ->get(route('adviser.leaderboard.index', [
                'event_id' => $event->id,
                'category_id' => $otherCategory->id,
            ]))
            ->assertRedirect(route('adviser.leaderboard.index'))
            ->assertSessionHasErrors('category_id');

        $this->get(route('adviser.leaderboard.index', ['category_id' => $otherCategory->id]))
            ->assertSessionHasErrors('category_id');
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::where('name', $role)->value('id'),
            'status' => $this->activeStatus->id,
            'must_change_password' => false,
        ]);
    }

    private function event(string $title, string $audience = 'all_students'): Event
    {
        return Event::create([
            'title' => $title,
            'location' => 'Main Hall',
            'audience_type' => $audience,
            'start_at' => now()->startOfDay()->addHours(8),
            'end_at' => now()->startOfDay()->addHours(17),
            'created_by' => $this->adviser->id,
        ]);
    }

    private function category(Event $event, string $name, float $maximum = 100, int $order = 1): ScoreCategory
    {
        return ScoreCategory::create([
            'event_id' => $event->id,
            'name' => $name,
            'max_points' => $maximum,
            'sort_order' => $order,
        ]);
    }

    private function team(SchoolYear $year, string $name, bool $active = true): Team
    {
        return Team::create([
            'school_year_id' => $year->id,
            'name' => $name,
            'color' => '#397565',
            'is_active' => $active,
        ]);
    }

    private function score(Event $event, ScoreCategory $category, Team $team, float $points): Score
    {
        return Score::create([
            'event_id' => $event->id,
            'team_id' => $team->id,
            'score_category_id' => $category->id,
            'points' => $points,
            'recorded_by' => $this->adviser->id,
        ]);
    }
}
