<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gremium = Organisations-Knoten: Gremien bekommen genau einen Knoten (organization_entity_id, eindeutig je Team),
 * Sitzungs-Teilnehmer können Personen-Knoten ohne Plattform-Konto sein.
 * Vorhandene Verortungen per Dimension Bridge (Alias management_book_committee) werden in die Spalte übernommen.
 * Idempotent (hasColumn-/Index-Guards) – Forge-Deploys dürfen abbrechen und neu laufen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('management_book_committees') && ! Schema::hasColumn('management_book_committees', 'organization_entity_id')) {
            Schema::table('management_book_committees', function (Blueprint $table) {
                $table->unsignedBigInteger('organization_entity_id')->nullable()->after('management_book_id');
                $table->unique(['team_id', 'organization_entity_id'], 'mb_committees_team_entity_unique');
            });
        }

        if (Schema::hasTable('management_book_meeting_attendees') && ! Schema::hasColumn('management_book_meeting_attendees', 'organization_entity_id')) {
            Schema::table('management_book_meeting_attendees', function (Blueprint $table) {
                $table->unsignedBigInteger('organization_entity_id')->nullable()->after('agent_id');
                $table->index('organization_entity_id', 'mb_attendees_entity_index');
            });
        }

        $this->backfillFromBridge();
    }

    /** Übernimmt vorhandene Bridge-Verortungen (erste je Gremium) in die Spalte – nur wo leer und Knoten noch frei. */
    private function backfillFromBridge(): void
    {
        if (! Schema::hasColumn('management_book_committees', 'organization_entity_id')
            || ! class_exists(\Platform\Organization\Services\EntityDimensionBridge::class)) {
            return;
        }

        try {
            $committees = DB::table('management_book_committees')->whereNull('organization_entity_id')->whereNull('deleted_at')->get(['id', 'team_id']);
            if ($committees->isEmpty()) {
                return;
            }
            $links = \Platform\Organization\Services\EntityDimensionBridge::linksForLinkables(['management_book_committee'], $committees->pluck('id')->all(), false);
            foreach ($committees as $c) {
                $entityId = $links->firstWhere('linkable_id', $c->id)?->entity_id;
                if (! $entityId) {
                    continue;
                }
                $taken = DB::table('management_book_committees')->where('team_id', $c->team_id)->where('organization_entity_id', $entityId)->exists();
                if (! $taken) {
                    DB::table('management_book_committees')->where('id', $c->id)->update(['organization_entity_id' => (int) $entityId]);
                }
            }
        } catch (\Throwable $e) {
            // Organisations-Tabellen fehlen o. ä. – Spalte bleibt leer, Verortung kann nachgeholt werden
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('management_book_meeting_attendees', 'organization_entity_id')) {
            Schema::table('management_book_meeting_attendees', function (Blueprint $table) {
                $table->dropIndex('mb_attendees_entity_index');
                $table->dropColumn('organization_entity_id');
            });
        }
        if (Schema::hasColumn('management_book_committees', 'organization_entity_id')) {
            Schema::table('management_book_committees', function (Blueprint $table) {
                $table->dropUnique('mb_committees_team_entity_unique');
                $table->dropColumn('organization_entity_id');
            });
        }
    }
};
