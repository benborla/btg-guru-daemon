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
        Schema::create('afl_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('round');
            $table->string('match_id');
            $table->string('date');
            $table->string('time');
            $table->string('status');
            $table->string('venue');
            $table->string('local_team');
            $table->string('visitor_team');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('afl_schedules');
    }
};
