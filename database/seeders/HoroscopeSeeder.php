<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class HoroscopeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $zodiacSigns = [
            'Aries', 'Taurus', 'Gemini', 'Cancer', 'Leo', 'Virgo',
            'Libra', 'Scorpio', 'Sagittarius', 'Capricorn', 'Aquarius', 'Pisces'
        ];

        $types = ['daily', 'weekly', 'monthly', 'yearly'];

        $languages = ['en', 'hi'];

        $records = [];

        foreach ($zodiacSigns as $sign) {
            foreach ($types as $type) {
                foreach ($languages as $lang) {
                    $records[] = [
                        'image' => null,
                        'zodiac_sign' => $sign,
                        'horoscope_type' => $type,
                        'language' => $lang,
                        'zodiac' => strtolower($sign),
                        'tag' => $lang === 'en' ? ucfirst($type) . ' Insights' : ucfirst($type) . ' की जानकारी',
                        'context' => $lang === 'en'
                            ? "This is your {$type} horoscope for {$sign}. Expect change and growth."
                            : "यह {$sign} के लिए आपका {$type} राशिफल है। परिवर्तन और विकास की उम्मीद करें।",
                        'love' => $lang === 'en'
                            ? 'Opportunities for emotional bonding.'
                            : 'भावनात्मक संबंध मजबूत करने के अवसर मिलेंगे।',
                        'career' => $lang === 'en'
                            ? 'Workplace challenges may arise, stay focused.'
                            : 'कार्यक्षेत्र में चुनौतियाँ आ सकती हैं, ध्यान केंद्रित रखें।',
                        'money' => $lang === 'en'
                            ? 'Be mindful of unexpected expenses.'
                            : 'अप्रत्याशित खर्चों से सावधान रहें।',
                        'health' => $lang === 'en'
                            ? 'Health remains stable with minor ups and downs.'
                            : 'स्वास्थ्य सामान्य रहेगा, थोड़े उतार-चढ़ाव संभव हैं।',
                        'travel' => $lang === 'en'
                            ? 'A good time to plan short getaways.'
                            : 'छोटे यात्रा की योजना बनाने के लिए अच्छा समय है।',
                        'lucky_number' => rand(1, 99),
                        'lucky_color' => $lang === 'en' ? 'Blue' : 'नीला',
                        'lucky_time' => '08:00 AM',
                        'start_date' => Carbon::now()->toDateString(),
                        'end_date' => in_array($type, ['weekly', 'monthly', 'yearly']) ? Carbon::now()->addDays(7)->toDateString() : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        DB::table('horoscopes')->insert($records);
    }
}
