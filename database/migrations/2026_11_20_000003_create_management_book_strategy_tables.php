<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Strategie-Kern je Management Book (Umzug aus dem Organization-Strategie-Layer, #1053):
 * Mission/Vision (versioniert), Forecasts (+Versionen), Fokusräume, Zielbilder, Hindernisse, Meilensteine.
 * Felder 1:1 wie organization_* — nur management_book_id statt entity_id. Keine Datenmigration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('management_book_strategic_documents')) {
            Schema::create('management_book_strategic_documents', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->foreignId('management_book_id')->constrained('management_books')->cascadeOnDelete();

                // mission | vision; pro (Book, Typ) ist genau eine Version aktiv
                $table->string('type', 20);
                $table->string('title');
                $table->text('content')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->boolean('is_active')->default(false);
                $table->date('valid_from');
                $table->text('change_note')->nullable();

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['management_book_id', 'type', 'is_active'], 'mb_strategic_docs_book_type_idx');
            });
        }

        if (! Schema::hasTable('management_book_forecasts')) {
            Schema::create('management_book_forecasts', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->foreignId('management_book_id')->constrained('management_books')->cascadeOnDelete();

                $table->string('title');
                // Zieljahr/-datum der Regnose
                $table->date('target_date');
                $table->text('content')->nullable();
                // Zeigt auf die aktuell dargestellte Version (lose, ohne FK: Zirkelbezug zu forecast_versions)
                $table->unsignedBigInteger('current_version_id')->nullable();

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['management_book_id', 'target_date'], 'mb_forecasts_book_target_idx');
            });
        }

        if (! Schema::hasTable('management_book_forecast_versions')) {
            Schema::create('management_book_forecast_versions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('forecast_id')->constrained('management_book_forecasts')->cascadeOnDelete();
                $table->unsignedInteger('version')->default(1);
                $table->text('content');
                $table->text('change_note')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['forecast_id', 'version'], 'mb_forecast_versions_unique');
            });
        }

        if (! Schema::hasTable('management_book_focus_areas')) {
            Schema::create('management_book_focus_areas', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->foreignId('management_book_id')->constrained('management_books')->cascadeOnDelete();
                // Soft-Link wie in der Organization: Fokusräume hängen primär am Book, die Regnose ist optional
                $table->foreignId('forecast_id')->nullable()->constrained('management_book_forecasts')->nullOnDelete();

                $table->string('title');
                $table->text('description')->nullable();
                $table->text('content')->nullable();
                // Leitfragen der drei Spalten
                $table->text('central_question_vision_images')->nullable();
                $table->text('central_question_obstacles')->nullable();
                $table->text('central_question_milestones')->nullable();
                $table->integer('order')->default(0);

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['management_book_id', 'order'], 'mb_focus_areas_book_order_idx');
            });
        }

        if (! Schema::hasTable('management_book_vision_images')) {
            Schema::create('management_book_vision_images', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                // management_book_id wird beim Speichern aus dem Fokusraum abgeleitet
                $table->foreignId('management_book_id')->constrained('management_books')->cascadeOnDelete();
                $table->foreignId('focus_area_id')->constrained('management_book_focus_areas')->cascadeOnDelete();

                $table->string('title');
                $table->text('description')->nullable();
                $table->text('central_question')->nullable();
                $table->integer('order')->default(0);

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['focus_area_id', 'order'], 'mb_vision_images_fa_order_idx');
            });
        }

        if (! Schema::hasTable('management_book_obstacles')) {
            Schema::create('management_book_obstacles', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->foreignId('management_book_id')->constrained('management_books')->cascadeOnDelete();
                $table->foreignId('focus_area_id')->constrained('management_book_focus_areas')->cascadeOnDelete();

                $table->string('title');
                $table->text('description')->nullable();
                $table->text('central_question')->nullable();
                $table->integer('order')->default(0);

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['focus_area_id', 'order'], 'mb_obstacles_fa_order_idx');
            });
        }

        if (! Schema::hasTable('management_book_milestones')) {
            Schema::create('management_book_milestones', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->foreignId('management_book_id')->constrained('management_books')->cascadeOnDelete();
                $table->foreignId('focus_area_id')->constrained('management_book_focus_areas')->cascadeOnDelete();

                $table->string('title');
                $table->text('description')->nullable();
                $table->text('central_question')->nullable();
                // target_year + target_quarter sind die primären Achsen der Transformation Map, target_date optional
                $table->date('target_date')->nullable();
                $table->unsignedSmallInteger('target_year')->nullable();
                $table->unsignedTinyInteger('target_quarter')->nullable();
                $table->integer('order')->default(0);

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['management_book_id', 'target_year', 'target_quarter'], 'mb_milestones_book_time_idx');
                $table->index(['focus_area_id', 'order'], 'mb_milestones_fa_order_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('management_book_milestones');
        Schema::dropIfExists('management_book_obstacles');
        Schema::dropIfExists('management_book_vision_images');
        Schema::dropIfExists('management_book_focus_areas');
        Schema::dropIfExists('management_book_forecast_versions');
        Schema::dropIfExists('management_book_forecasts');
        Schema::dropIfExists('management_book_strategic_documents');
    }
};
