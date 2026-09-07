<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('score_categories')) {
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
        }

        if (! Schema::hasColumn('scores', 'score_category_id')) {
            Schema::table('scores', function (Blueprint $table) {
                $table->foreignId('score_category_id')->nullable()->after('team_id')->constrained('score_categories')->cascadeOnDelete();
            });
        }

        if (Schema::hasColumn('scores', 'category')) {
            DB::table('scores')
                ->select(['event_id', 'category'])
                ->distinct()
                ->orderBy('event_id')
                ->each(function ($legacyCategory) {
                    $name = $legacyCategory->category ?: 'General';
                    $categoryId = DB::table('score_categories')
                        ->where('event_id', $legacyCategory->event_id)
                        ->where('name', $name)
                        ->value('id');

                    $categoryId ??= DB::table('score_categories')->insertGetId([
                        'event_id' => $legacyCategory->event_id,
                        'name' => $name,
                        'max_points' => 100,
                        'sort_order' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('scores')
                        ->where('event_id', $legacyCategory->event_id)
                        ->where('category', $legacyCategory->category)
                        ->whereNull('score_category_id')
                        ->update(['score_category_id' => $categoryId]);
                });
        }

        // MySQL may use the original composite unique index to support the event_id
        // foreign key. Give that foreign key its own index before removing the unique.
        if (! $this->indexExists('scores', 'scores_event_id_index')) {
            Schema::table('scores', function (Blueprint $table) {
                $table->index('event_id', 'scores_event_id_index');
            });
        }

        if ($this->indexExists('scores', 'scores_event_id_team_id_category_unique')) {
            Schema::table('scores', function (Blueprint $table) {
                $table->dropUnique('scores_event_id_team_id_category_unique');
            });
        }

        if (Schema::hasColumn('scores', 'category')) {
            Schema::table('scores', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }

        if (! $this->indexExists('scores', 'scores_event_team_category_unique')) {
            Schema::table('scores', function (Blueprint $table) {
                $table->unique(['event_id', 'team_id', 'score_category_id'], 'scores_event_team_category_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('scores', function (Blueprint $table) {
            $table->dropUnique('scores_event_team_category_unique');
            $table->string('category')->default('General')->after('team_id');
        });

        DB::table('scores')->orderBy('id')->each(function ($score) {
            $name = DB::table('score_categories')->where('id', $score->score_category_id)->value('name');
            DB::table('scores')->where('id', $score->id)->update(['category' => $name ?: 'General']);
        });

        Schema::table('scores', function (Blueprint $table) {
            $table->dropForeign(['score_category_id']);
            $table->dropColumn('score_category_id');
            $table->unique(['event_id', 'team_id', 'category']);
        });

        Schema::dropIfExists('score_categories');
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $definition) => $definition['name'] === $index);
    }
};
