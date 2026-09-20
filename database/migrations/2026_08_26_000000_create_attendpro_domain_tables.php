<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('role', 30)->default('attendance_admin')->after('password')->index());
        }
        if (! Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', fn (Blueprint $table) => $table->boolean('is_active')->default(true)->after('role'));
        }
        if (! Schema::hasColumn('users', 'last_login_at')) {
            Schema::table('users', fn (Blueprint $table) => $table->timestamp('last_login_at')->nullable()->after('is_active'));
        }

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('category', 30)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('timezone', 60)->default('Asia/Manila');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('institution_id', 60)->unique();
            $table->string('type', 20)->index();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('suffix', 20)->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('phone', 40)->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('program')->nullable();
            $table->string('year_level', 30)->nullable();
            $table->string('position')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->date('joined_on')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'department_id', 'status']);
            $table->index(['last_name', 'first_name']);
        });

        Schema::create('terminals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->foreignId('location_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('status', 20)->default('offline')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->string('api_token_id', 36)->nullable()->unique();
            $table->string('api_token_hash', 64)->nullable();
            $table->ipAddress('last_ip')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->string('app_version', 60)->nullable();
            $table->json('capabilities')->nullable();
            $table->timestamps();
        });

        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('person_type', 20)->nullable()->index();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->json('days_of_week');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->time('check_in_opens_at')->nullable();
            $table->time('check_in_closes_at')->nullable();
            $table->unsignedSmallInteger('grace_minutes')->default(0);
            $table->boolean('checkout_required')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->timestamps();
            $table->unique(['schedule_id', 'person_id']);
        });

        Schema::create('facial_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->longText('embedding');
            $table->string('embedding_checksum', 64)->index();
            $table->string('model', 100);
            $table->unsignedInteger('dimensions');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('consented_at');
            $table->date('retention_until')->nullable();
            $table->foreignId('enrolled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['person_id', 'is_active']);
        });

        Schema::create('recognition_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('external_event_id', 100);
            $table->foreignId('terminal_id')->constrained()->restrictOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('result', 20)->index();
            $table->decimal('confidence', 6, 5)->nullable();
            $table->string('direction', 20)->default('auto');
            $table->timestamp('captured_at')->index();
            $table->string('review_status', 20)->default('pending')->index();
            $table->string('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['terminal_id', 'external_event_id']);
            $table->index(['result', 'captured_at']);
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('terminal_id')->nullable()->constrained()->nullOnDelete();
            $table->date('attendance_date')->index();
            $table->timestamp('time_in')->nullable();
            $table->timestamp('time_out')->nullable();
            $table->string('status', 20)->index();
            $table->string('source', 20)->default('recognition')->index();
            $table->decimal('match_confidence', 6, 5)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('corrected_at')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();

            $table->unique(['person_id', 'schedule_id', 'attendance_date'], 'attendance_person_schedule_date_unique');
            $table->index(['attendance_date', 'status', 'location_id']);
        });

        Schema::table('recognition_events', function (Blueprint $table) {
            $table->foreignId('attendance_record_id')->nullable()->after('person_id')->constrained()->nullOnDelete();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 50)->index();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::table('recognition_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attendance_record_id');
        });
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('recognition_events');
        Schema::dropIfExists('facial_profiles');
        Schema::dropIfExists('schedule_assignments');
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('terminals');
        Schema::dropIfExists('people');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('departments');

        foreach (['role', 'is_active', 'last_login_at'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                Schema::table('users', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
