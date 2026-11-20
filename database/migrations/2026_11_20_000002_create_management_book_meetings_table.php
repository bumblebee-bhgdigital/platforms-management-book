<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('management_book_meetings')) {
            return;
        }

        Schema::create('management_book_meetings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('committee_id')->constrained('management_book_committees')->cascadeOnDelete();

            $table->string('title');
            $table->dateTime('scheduled_at');
            $table->unsignedInteger('duration_minutes')->nullable();
            // Ort oder Link, frei
            $table->string('location')->nullable();
            // Verweis auf einen bestehenden Kalendertermin (lose, keine Synchronisation)
            $table->string('external_calendar_ref')->nullable();

            // planned | prepared | held | cancelled
            $table->string('status', 20)->default('planned');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'committee_id']);
            $table->index(['committee_id', 'scheduled_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('management_book_meetings');
    }
};
