<?php

namespace Database\Seeders;

use App\Models\User;
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
        $this->call([
            CampaignSeeder::class,
            DispositionCodesSeeder::class,
            PauseCodesSeeder::class,
            FormFieldsSeeder::class,
        ]);

        if (User::where('username', 'admin')->doesntExist()) {
            User::factory()->create([
                'username' => 'admin',
                'name' => 'Admin',
                'full_name' => 'Administrator',
                'email' => 'admin@example.com',
                'role' => 'Super Admin',
            ]);
        }
    }
}
