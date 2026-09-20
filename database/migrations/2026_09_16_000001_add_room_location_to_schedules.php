<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('schedules', 'location_type')) {
            Schema::table('schedules', fn (Blueprint $table) => $table->string('location_type', 30)->default('any')->after('location_id')->index());
        }

        if (! Schema::hasColumn('schedules', 'room_code')) {
            Schema::table('schedules', fn (Blueprint $table) => $table->string('room_code', 60)->nullable()->after('location_type')->index());
        }

        // Backfill existing schedules so legacy behavior is preserved:
        // - no location  -> "any" (unchanged loose matching)
        // - Main Entrance-like location -> "main_entrance"
        // - any other registered location -> "other" with the room code taken
        //   from that location's code (enforcement still uses location_id).
        DB::table('schedules')->whereNull('location_id')->update(['location_type' => 'any', 'room_code' => null]);

        $rows = DB::table('schedules as s')
            ->leftJoin('locations as l', 'l.id', '=', 's.location_id')
            ->whereNotNull('s.location_id')
            ->select('s.id', 'l.code as location_code', 'l.name as location_name')
            ->get();

        foreach ($rows as $row) {
            $haystack = strtolower(trim(($row->location_code ?? '').' '.($row->location_name ?? '')));
            $isMainEntrance = str_contains($haystack, 'main') || str_contains($haystack, 'entrance');
            $legacyRoom = substr(trim((string) ($row->location_code ?? '')), 0, 60);

            DB::table('schedules')->where('id', $row->id)->update([
                'location_type' => $isMainEntrance ? 'main_entrance' : 'other',
                'room_code' => $isMainEntrance || $legacyRoom === '' ? null : $legacyRoom,
            ]);
        }
    }

    public function down(): void
    {
        foreach (['room_code', 'location_type'] as $column) {
            if (Schema::hasColumn('schedules', $column)) {
                Schema::table('schedules', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
