<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formate je Gremientyp: Art eines Agenda-Punkts (z. B. regulation, direction, approval) und eines Beschlusses
 * (z. B. measure, direction, approval). Welche Arten gelten, legt das Format des Gremiums fest (src/Formats).
 * Beide Felder sind optional; null = Standard-Art des Formats.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('management_book_agenda_items') && ! Schema::hasColumn('management_book_agenda_items', 'kind')) {
            Schema::table('management_book_agenda_items', function (Blueprint $table) {
                $table->string('kind', 40)->nullable()->after('title');
            });
        }

        if (Schema::hasTable('management_book_decisions') && ! Schema::hasColumn('management_book_decisions', 'kind')) {
            Schema::table('management_book_decisions', function (Blueprint $table) {
                $table->string('kind', 40)->nullable()->after('text');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('management_book_agenda_items', 'kind')) {
            Schema::table('management_book_agenda_items', fn (Blueprint $table) => $table->dropColumn('kind'));
        }
        if (Schema::hasColumn('management_book_decisions', 'kind')) {
            Schema::table('management_book_decisions', fn (Blueprint $table) => $table->dropColumn('kind'));
        }
    }
};
