<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
     // Create test users
        $user1 = User::create([
            'name' => 'Test User 1',
            'email' => 'user1@test.com',
            'password' => Hash::make('password'),
            'balance' => 100000,
        ]);

        $user2 = User::create([
            'name' => 'Test User 2',
            'email' => 'user2@test.com',
            'password' => Hash::make('password'),
            'balance' => 100000,
        ]);

        // Give user2 some BTC
        Asset::create([
            'user_id' => $user2->id,
            'symbol' => 'BTC',
            'amount' => 1,
            'locked_amount' => 0,
        ]);

        Asset::create([
            'user_id' => $user2->id,
            'symbol' => 'ETH',
            'amount' => 10,
            'locked_amount' => 0,
        ]);
    }
}
