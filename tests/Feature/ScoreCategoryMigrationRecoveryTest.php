<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ScoreCategoryMigrationRecoveryTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = DB::getDefaultConnection();
        config()->set('database.connections.migration_recovery', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('migration_recovery');
        DB::setDefaultConnection('migration_recovery');
    }

    protected function tearDown(): void
    {
        DB::setDefaultConnection($this->originalConnection);
        DB::purge('migration_recovery');

        parent::tearDown();
    }

    public function test_score_category_migration_recovers_after_a_partial_mysql_style_failure(): void
    {
        Schema::create('events', fn (Blueprint $table) => $table->id());
        Schema::create('teams', fn (Blueprint $table) => $table->id());
        Schema::create('users', fn (Blueprint $table) => $table->id());
        Schema::create('score_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->decimal('max_points', 10, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['event_id', 'name']);
            $table->index(['event_id', 'sort_order']);
        });
        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('score_category_id')->nullable()->constrained('score_categories')->cascadeOnDelete();
            $table->string('category')->default('General');
            $table->decimal('points', 10, 2);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'team_id', 'category']);
            $table->index(['team_id', 'points']);
        });

        DB::table('events')->insert(['id' => 1]);
        DB::table('teams')->insert(['id' => 1]);
        DB::table('scores')->insert([
            'event_id' => 1,
            'team_id' => 1,
            'category' => 'Creativity',
            'points' => 90,
        ]);

        $migration = require database_path('migrations/2026_09_07_000010_create_score_categories_table.php');
        $migration->up();
        $migration->up();

        $this->assertFalse(Schema::hasColumn('scores', 'category'));
        $this->assertTrue(Schema::hasColumn('scores', 'score_category_id'));
        $this->assertTrue(collect(Schema::getIndexes('scores'))->contains(
            fn (array $index) => $index['name'] === 'scores_event_id_index'
        ));
        $this->assertTrue(collect(Schema::getIndexes('scores'))->contains(
            fn (array $index) => $index['name'] === 'scores_event_team_category_unique'
        ));
        $this->assertSame('Creativity', DB::table('score_categories')->value('name'));
        $this->assertNotNull(DB::table('scores')->value('score_category_id'));
    }
}
