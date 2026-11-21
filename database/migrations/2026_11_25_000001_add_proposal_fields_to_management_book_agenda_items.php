<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regelkreis Einheit → LA: Agendapunkte, die ein Agent aus einem Signal vorschlägt, kommen als Entwurf
 * (proposal_status = draft) in die Sitzung; der Vorsitz übernimmt (accepted) oder nimmt sie heraus.
 * local_solution_confirmed: hat der Bereich 4a bestätigt? Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('management_book_agenda_items', 'proposal_status')) {
            Schema::table('management_book_agenda_items', fn (Blueprint $t) => $t->string('proposal_status', 20)->nullable()->index());
        }
        if (! Schema::hasColumn('management_book_agenda_items', 'local_solution_confirmed')) {
            Schema::table('management_book_agenda_items', fn (Blueprint $t) => $t->boolean('local_solution_confirmed')->default(false));
        }
    }

    public function down(): void
    {
        foreach (['proposal_status', 'local_solution_confirmed'] as $c) {
            if (Schema::hasColumn('management_book_agenda_items', $c)) {
                Schema::table('management_book_agenda_items', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
    }
};
