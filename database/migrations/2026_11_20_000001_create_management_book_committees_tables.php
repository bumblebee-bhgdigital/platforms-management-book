<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('management_book_committees')) {
            Schema::create('management_book_committees', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->foreignId('management_book_id')->constrained('management_books')->cascadeOnDelete();

                $table->string('name');
                // steering_committee | strategy_meeting | board | other (fest, damit der Regelkreis erkennbar bleibt)
                $table->string('type', 30)->default('other');
                $table->text('description')->nullable();
                // Freitext/Info, z. B. "monatlich" — keine Ablauflogik
                $table->string('cadence')->nullable();

                $table->foreignId('chair_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('responsible_agent_id')->nullable()->constrained('users')->nullOnDelete();
                // Verweis auf das Signals-Modul (lose, ohne FK: Modul ist optional)
                $table->unsignedBigInteger('signal_set_id')->nullable();

                // aktiv | ruhend | aufgeloest
                $table->string('status', 20)->default('active');

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['team_id', 'management_book_id']);
                $table->index('status');
            });
        }

        if (! Schema::hasTable('management_book_committee_members')) {
            Schema::create('management_book_committee_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('committee_id')->constrained('management_book_committees')->cascadeOnDelete();
                // Mitglied ist entweder ein User oder ein Agent (ebenfalls ein User-Datensatz, Typ ai_user)
                $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
                $table->foreignId('agent_id')->nullable()->constrained('users')->cascadeOnDelete();
                // chair | member | guest | secretary (Vorsitz/Mitglied/Gast/Protokoll)
                $table->string('role', 20)->default('member');
                $table->timestamps();

                $table->index('committee_id');
                $table->unique(['committee_id', 'user_id']);
                $table->unique(['committee_id', 'agent_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('management_book_committee_members');
        Schema::dropIfExists('management_book_committees');
    }
};
