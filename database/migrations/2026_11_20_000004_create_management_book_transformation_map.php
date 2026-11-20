<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transformation Map + Milestone-Contributions je Management Book (#1053, Teil 2):
 * - Meilensteine werden zu Waypoints mit Status (planned/in_progress/reached/missed/cancelled) und Erreichungsdatum.
 *   Die Reihenfolge ist `order` (bestehend) innerhalb von target_year/target_quarter.
 * - management_book_milestone_contributions: Objectives/Key Results (OKR-Modul) zahlen auf Meilensteine des Books ein.
 *   Bewusst polymorph/lose wie organization_milestone_contributions (linkable_type = Morph-Alias, z. B. okr_objective,
 *   okr_key_result; kein FK auf das Fremdmodul) – die Brücke zum OKR-Modul bleibt damit unverändert. Org-Tabellen bleiben unangetastet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('management_book_milestones') && ! Schema::hasColumn('management_book_milestones', 'status')) {
            Schema::table('management_book_milestones', function (Blueprint $table) {
                // planned | in_progress | reached | missed | cancelled
                $table->string('status', 20)->default('planned')->after('target_quarter');
                $table->date('reached_at')->nullable()->after('status');
                $table->index(['management_book_id', 'status'], 'mb_milestones_book_status_idx');
            });
        }

        if (! Schema::hasTable('management_book_milestone_contributions')) {
            Schema::create('management_book_milestone_contributions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                // aus dem Meilenstein abgeleitet (für Abfragen je Book)
                $table->foreignId('management_book_id')->constrained('management_books')->cascadeOnDelete();
                $table->foreignId('milestone_id')->constrained('management_book_milestones')->cascadeOnDelete();

                // Polymorpher Beitragender, z. B. ('okr_objective', 42), ('okr_key_result', 88)
                $table->string('linkable_type');
                $table->unsignedBigInteger('linkable_id');

                // Optionales Gewicht 1-100 für spätere Fortschritts-Hochrechnung; null = 1
                $table->unsignedTinyInteger('weight')->nullable();

                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();

                $table->unique(['milestone_id', 'linkable_type', 'linkable_id'], 'mb_milestone_contrib_unique');
                $table->index(['linkable_type', 'linkable_id'], 'mb_milestone_contrib_linkable_idx');
                $table->index('management_book_id', 'mb_milestone_contrib_book_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('management_book_milestone_contributions');

        if (Schema::hasTable('management_book_milestones') && Schema::hasColumn('management_book_milestones', 'status')) {
            Schema::table('management_book_milestones', function (Blueprint $table) {
                $table->dropIndex('mb_milestones_book_status_idx');
                $table->dropColumn(['status', 'reached_at']);
            });
        }
    }
};
