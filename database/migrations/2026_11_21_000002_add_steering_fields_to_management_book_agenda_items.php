<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lenkungsausschuss-Leitfaden je Agenda-Punkt (BROICH.MANAGEMENTMODELL, „Die vier Fragen“):
 * Ziel, Beobachtung, Hindernis, was der Bereich selbst lösen kann, wobei er das Gesamtsystem braucht,
 * einbringende Einheit (Knoten, lose) und die Handlungsfähigkeits-Kriterien nach dem LA (JSON-Liste der erfüllten Kriterien).
 * Reine Datenhaltung, keine Ablauflogik.
 */
return new class extends Migration
{
    private const TEXT_COLUMNS = ['goal', 'observation', 'obstacle', 'local_solution', 'system_support'];

    public function up(): void
    {
        if (! Schema::hasTable('management_book_agenda_items')) {
            return;
        }

        foreach (self::TEXT_COLUMNS as $col) {
            if (! Schema::hasColumn('management_book_agenda_items', $col)) {
                Schema::table('management_book_agenda_items', function (Blueprint $table) use ($col) {
                    $table->text($col)->nullable();
                });
            }
        }
        if (! Schema::hasColumn('management_book_agenda_items', 'organization_entity_id')) {
            Schema::table('management_book_agenda_items', function (Blueprint $table) {
                $table->unsignedBigInteger('organization_entity_id')->nullable();
            });
        }
        if (! Schema::hasColumn('management_book_agenda_items', 'outcome_checks')) {
            Schema::table('management_book_agenda_items', function (Blueprint $table) {
                $table->json('outcome_checks')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('management_book_agenda_items')) {
            return;
        }
        foreach ([...self::TEXT_COLUMNS, 'organization_entity_id', 'outcome_checks'] as $col) {
            if (Schema::hasColumn('management_book_agenda_items', $col)) {
                Schema::table('management_book_agenda_items', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }
};
