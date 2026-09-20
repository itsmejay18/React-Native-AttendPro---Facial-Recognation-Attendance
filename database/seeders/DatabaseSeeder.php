<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Location;
use App\Models\Schedule;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(UserRoleSeeder::class);

        $academic = Department::query()->firstOrCreate(['code' => 'ACADEMIC'], [
            'name' => 'Academic Affairs', 'category' => 'academic', 'is_active' => true,
        ]);
        Department::query()->firstOrCreate(['code' => 'ADMIN'], [
            'name' => 'Administration', 'category' => 'administrative', 'is_active' => true,
        ]);
        $mainGate = Location::query()->firstOrCreate(['code' => 'MAIN-GATE'], [
            'name' => 'Main Entrance', 'timezone' => config('app.timezone'), 'is_active' => true,
        ]);

        Schedule::query()->firstOrCreate(['code' => 'WEEKDAY-DAY'], [
            'name' => 'Weekday Day Schedule',
            'department_id' => $academic->id,
            'location_id' => $mainGate->id,
            'location_type' => Schedule::LOCATION_TYPE_MAIN_ENTRANCE,
            'days_of_week' => [1, 2, 3, 4, 5],
            'starts_at' => '08:00',
            'ends_at' => '17:00',
            'check_in_opens_at' => '06:30',
            'check_in_closes_at' => '10:00',
            'grace_minutes' => 10,
            'checkout_required' => true,
            'is_active' => true,
        ]);

        // Portable demo dataset shipped with the repo (students, staff
        // accounts, schedules, terminal, enrollment images, history).
        $this->call(AttendproDatasetSeeder::class);
    }
}
