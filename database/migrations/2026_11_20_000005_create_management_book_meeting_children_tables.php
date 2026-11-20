<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kinder einer Sitzung (#1052): Agenda-Punkte, Unterlagen, Protokoll, Beschlüsse, Teilnehmer.
 * Reine Datenhaltung – keine Standard-Agenda, keine Ablaufregeln, keine Datenmigration.
 * Dateien liegen in den Core-ContextFiles (Verknüpfung über context_file_references, kein eigener File-Speicher).
 * Owner/Teilnehmer: entweder *_user_id oder *_agent_id (Agent = User vom Typ ai_user, wie bei Gremium-Mitgliedern).
 * linked_task_id verweist lose auf eine Planner-Aufgabe (keine FK, Planner ist optional).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('management_book_agenda_items')) {
            Schema::create('management_book_agenda_items', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->foreignId('meeting_id')->constrained('management_book_meetings')->cascadeOnDelete();

                $table->unsignedInteger('position')->default(0);
                $table->string('title');
                $table->text('description')->nullable();
                $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('owner_agent_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedInteger('duration_minutes')->nullable();
                // Polymorph (Morph-Alias, z. B. Meilenstein, Risiko, Signal) – lose, ohne FK
                $table->string('linked_object_type', 100)->nullable();
                $table->unsignedBigInteger('linked_object_id')->nullable();

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['meeting_id', 'position'], 'mb_agenda_meeting_pos_idx');
                $table->index('team_id');
                $table->index(['linked_object_type', 'linked_object_id'], 'mb_agenda_linked_idx');
            });
        }

        if (! Schema::hasTable('management_book_documents')) {
            Schema::create('management_book_documents', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->foreignId('meeting_id')->constrained('management_book_meetings')->cascadeOnDelete();
                $table->foreignId('agenda_item_id')->nullable()->constrained('management_book_agenda_items')->nullOnDelete();

                $table->string('title');
                // frei (z. B. Vorlage, Präsentation, Bericht)
                $table->string('type', 100)->nullable();

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index('meeting_id');
                $table->index('agenda_item_id');
                $table->index('team_id');
            });
        }

        if (! Schema::hasTable('management_book_minutes')) {
            Schema::create('management_book_minutes', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->foreignId('meeting_id')->constrained('management_book_meetings')->cascadeOnDelete();

                // Markdown
                $table->longText('content')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index('meeting_id');
                $table->index('team_id');
            });
        }

        if (! Schema::hasTable('management_book_decisions')) {
            Schema::create('management_book_decisions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->foreignId('meeting_id')->constrained('management_book_meetings')->cascadeOnDelete();
                $table->foreignId('agenda_item_id')->nullable()->constrained('management_book_agenda_items')->nullOnDelete();

                $table->text('text');
                // open | implemented | discarded
                $table->string('status', 20)->default('open');
                $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('owner_agent_id')->nullable()->constrained('users')->nullOnDelete();
                $table->date('due_date')->nullable();
                // lose Verknüpfung zu einer Planner-Aufgabe (keine FK)
                $table->unsignedBigInteger('linked_task_id')->nullable();

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index('meeting_id');
                $table->index('agenda_item_id');
                $table->index(['team_id', 'status']);
                $table->index('linked_task_id');
            });
        }

        if (! Schema::hasTable('management_book_meeting_attendees')) {
            Schema::create('management_book_meeting_attendees', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->foreignId('meeting_id')->constrained('management_book_meetings')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
                $table->foreignId('agent_id')->nullable()->constrained('users')->cascadeOnDelete();
                // frei (z. B. chair, member, guest, secretary)
                $table->string('role', 50)->nullable();
                $table->boolean('attended')->default(false);
                $table->timestamps();

                $table->unique(['meeting_id', 'user_id'], 'mb_attendee_meeting_user_uq');
                $table->unique(['meeting_id', 'agent_id'], 'mb_attendee_meeting_agent_uq');
                $table->index('team_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('management_book_meeting_attendees');
        Schema::dropIfExists('management_book_decisions');
        Schema::dropIfExists('management_book_minutes');
        Schema::dropIfExists('management_book_documents');
        Schema::dropIfExists('management_book_agenda_items');
    }
};
