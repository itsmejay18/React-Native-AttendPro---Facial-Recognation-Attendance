<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Browser recognition no longer represents a physical terminal. Laravel
     * resolves the schedule and room itself after Python identifies a face.
     */
    public function up(): void
    {
        Schema::table('recognition_events', function (Blueprint $table): void {
            $table->dropForeign(['terminal_id']);
            $table->dropUnique('recognition_events_terminal_id_external_event_id_unique');
            $table->unsignedBigInteger('terminal_id')->nullable()->change();
            $table->foreign('terminal_id')->references('id')->on('terminals')->restrictOnDelete();
            $table->unique(['terminal_id', 'external_event_id']);
        });
    }

    public function down(): void
    {
        Schema::table('recognition_events', function (Blueprint $table): void {
            $table->dropForeign(['terminal_id']);
            $table->dropUnique('recognition_events_terminal_id_external_event_id_unique');
            $table->unsignedBigInteger('terminal_id')->nullable(false)->change();
            $table->foreign('terminal_id')->references('id')->on('terminals')->restrictOnDelete();
            $table->unique(['terminal_id', 'external_event_id']);
        });
    }
};
