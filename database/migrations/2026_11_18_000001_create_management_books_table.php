<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('management_books')) {
            return;
        }

        Schema::create('management_books', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            // draft | active | superseded | archived (Entwurf / aktiv / abgelöst / archiviert)
            $table->string('status', 20)->default('draft');
            $table->timestamp('activated_at')->nullable();

            // Zeitraum/Horizont: rein informativ, Überschneidungen sind erlaubt.
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->unsignedSmallInteger('horizon_year')->nullable();

            // Verantwortung: Owner (User) + verantwortlicher Agent (User vom Typ Agent)
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('responsible_agent_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Review
            $table->string('review_rhythm', 20)->nullable();
            $table->date('next_review_at')->nullable();

            // created_by_user_id ist die Ersteller-Spalte, die der Core-Authz-Resolver erkennt
            // (AuthzResolver::ownerColumn): Ersteller sieht sein Book, solange es nicht verortet ist.
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status']);
            $table->index('next_review_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('management_books');
    }
};
