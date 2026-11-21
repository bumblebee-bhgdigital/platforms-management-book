<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #1054 / #1048: Risiken je Management Book (Risikomatrix-Vorlage) + Risikobereitschaft am Book.
 * Score = E × A wird gespeichert (sortier-/filterbar), die Ampel wird daraus abgeleitet.
 * organization_entity_id = Bereich/Abteilung als Knoten, lose gekoppelt (kein FK, wie linked_task_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('management_book_risks')) {
            Schema::create('management_book_risks', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->foreignId('management_book_id')->constrained('management_books')->cascadeOnDelete();

                $table->string('number', 50)->nullable();
                $table->string('title');
                $table->text('description')->nullable();
                $table->unsignedBigInteger('organization_entity_id')->nullable();
                $table->unsignedTinyInteger('probability')->nullable();
                $table->unsignedTinyInteger('impact')->nullable();
                $table->unsignedTinyInteger('score')->nullable();
                $table->string('category')->nullable();
                $table->text('measures_planned')->nullable();
                $table->text('measures_implemented')->nullable();
                $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('owner_agent_id')->nullable()->constrained('users')->nullOnDelete();
                $table->date('due_date')->nullable();
                $table->string('status', 20)->default('open');
                $table->date('review_date')->nullable();

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['management_book_id', 'score'], 'mb_risks_book_score_idx');
                $table->index(['management_book_id', 'status'], 'mb_risks_book_status_idx');
                $table->index('organization_entity_id', 'mb_risks_entity_idx');
            });
        }

        if (Schema::hasTable('management_books') && ! Schema::hasColumn('management_books', 'risk_appetite')) {
            Schema::table('management_books', function (Blueprint $table) {
                $table->string('risk_appetite', 20)->nullable()->after('next_review_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('management_books') && Schema::hasColumn('management_books', 'risk_appetite')) {
            Schema::table('management_books', function (Blueprint $table) {
                $table->dropColumn('risk_appetite');
            });
        }

        Schema::dropIfExists('management_book_risks');
    }
};
