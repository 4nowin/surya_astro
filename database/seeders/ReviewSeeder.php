<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $reviews = [
            [
                'astrologer_id' => 1,
                'user_id' => 1,
                'rating' => 5,
                'comment' => 'Absolutely amazing reading. Very insightful!',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'astrologer_id' => 1,
                'user_id' => 1,
                'rating' => 4,
                'comment' => 'Great advice and accurate predictions.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'astrologer_id' => 2,
                'user_id' => 1,
                'rating' => 3,
                'comment' => 'Decent reading, but could be more detailed.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'astrologer_id' => 3,
                'user_id' => 1,
                'rating' => 5,
                'comment' => 'Loved the clarity and explanation.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'astrologer_id' => 2,
                'user_id' => 1,
                'rating' => 2,
                'comment' => 'Didn’t connect well, felt rushed.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('reviews')->insert($reviews);
    }
}
