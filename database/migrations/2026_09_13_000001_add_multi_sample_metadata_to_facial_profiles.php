<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facial_profiles', function (Blueprint $table): void {
            $table->uuid('enrollment_session_uuid')->nullable()->after('version')->index();
            $table->json('quality_metadata')->nullable()->after('retention_until');
        });
    }

    public function down(): void
    {
        Schema::table('facial_profiles', function (Blueprint $table): void {
            $table->dropIndex(['enrollment_session_uuid']);
            $table->dropColumn(['enrollment_session_uuid', 'quality_metadata']);
        });
    }
};
