<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\KeywordSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('1234'),
        ]);

        $this->call([
            KeywordSeeder::class,
        ]);
    }
}
