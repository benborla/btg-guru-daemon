<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('nfl_games', function (Blueprint $table) {
            $table->id();

            // Basic identifiers
            $table->string('contest_id')->unique()->index(); // Primary game identifier
            $table->string('venue_id')->nullable();

            // Game timing
            $table->string('date')->nullable(); // Date string from API
            $table->string('datetime_utc')->nullable(); // UTC datetime string
            $table->string('time')->nullable(); // Game time string
            $table->string('timer')->nullable(); // Game timer/clock
            $table->string('timezone')->nullable(); // Timezone string

            // Game status and details
            $table->string('status')->nullable(); // Game status
            $table->string('formatted_date')->nullable(); // Formatted date string

            // Team and game stats (as strings from API)
            $table->text('attendance')->nullable(); // Can be empty string
            $table->text('awayteam')->nullable(); // JSON as string
            $table->text('hometeam')->nullable(); // JSON as string
            $table->text('defensive')->nullable(); // Defensive stats JSON
            $table->text('events')->nullable(); // Game events JSON
            $table->text('team_stats')->nullable(); // Team statistics JSON

            // Individual stat categories (as strings)
            $table->text('interceptions')->nullable();
            $table->text('kick_returns')->nullable();
            $table->text('kicking')->nullable();
            $table->text('passing')->nullable();
            $table->text('receiving')->nullable();
            $table->text('rushing')->nullable();

            $table->timestamps();

            // Indexes for common queries
            $table->index('date');
            $table->index('status');
            $table->index('datetime_utc');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfl_games');
    }
};
