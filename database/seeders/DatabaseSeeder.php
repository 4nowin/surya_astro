<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            AdminSeeder::class,
            AstrologerSeeder::class,
            HoroscopeSeeder::class,
            PoojaSeeder::class,
            ReviewSeeder::class
        ]);
    }
}
