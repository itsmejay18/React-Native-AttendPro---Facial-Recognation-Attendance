<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        foreach (['super_admin', 'attendance_admin', 'reviewer'] as $role) {
            DB::table('roles')->insertOrIgnore([
                'name' => $role,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $roleIds = DB::table('roles')
            ->where('guard_name', 'web')
            ->pluck('id', 'name');

        DB::table('users')
            ->select(['id', 'role'])
            ->orderBy('id')
            ->each(function (object $user) use ($roleIds): void {
                $roleId = $roleIds[$user->role] ?? $roleIds['reviewer'];

                DB::table('model_has_roles')->insertOrIgnore([
                    'role_id' => $roleId,
                    'model_type' => User::class,
                    'model_id' => $user->id,
                ]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role', 30)->default('reviewer')->after('password')->index();
            });
        }

        DB::table('users')
            ->select('id')
            ->orderBy('id')
            ->each(function (object $user): void {
                $role = DB::table('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('model_has_roles.model_type', User::class)
                    ->where('model_has_roles.model_id', $user->id)
                    ->value('roles.name');

                DB::table('users')->where('id', $user->id)->update([
                    'role' => $role ?: 'reviewer',
                ]);
            });
    }
};
