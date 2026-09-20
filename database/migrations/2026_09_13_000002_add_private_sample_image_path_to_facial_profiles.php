<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facial_profiles', function (Blueprint $table): void {
            $table->string('sample_image_path')->nullable()->after('quality_metadata');
        });
    }

    public function down(): void
    {
        Schema::table('facial_profiles', function (Blueprint $table): void {
            $table->dropColumn('sample_image_path');
        });
    }
};
