<?php

namespace Database\Seeders;

use App\Services\Horoscope\DailyHoroscopeService;
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
            'Aries' => ['hi' => 'मेष', 'symbol' => 'Ram'],
            'Taurus' => ['hi' => 'वृषभ', 'symbol' => 'Bull'],
            'Gemini' => ['hi' => 'मिथुन', 'symbol' => 'Twins'],
            'Cancer' => ['hi' => 'कर्क', 'symbol' => 'Crab'],
            'Leo' => ['hi' => 'सिंह', 'symbol' => 'Lion'],
            'Virgo' => ['hi' => 'कन्या', 'symbol' => 'Maiden'],
            'Libra' => ['hi' => 'तुला', 'symbol' => 'Scales'],
            'Scorpio' => ['hi' => 'वृश्चिक', 'symbol' => 'Scorpion'],
            'Sagittarius' => ['hi' => 'धनु', 'symbol' => 'Archer'],
            'Capricorn' => ['hi' => 'मकर', 'symbol' => 'Goat'],
            'Aquarius' => ['hi' => 'कुम्भ', 'symbol' => 'Water Bearer'],
            'Pisces' => ['hi' => 'मीन', 'symbol' => 'Fish']
        ];

        $languages = ['en', 'hi'];
        $records = [];

        // 1. Seed rolling window for daily horoscopes (Past 3 days to Next 7 days = 11 days)
        $startDay = Carbon::today()->subDays(3);
        $endDay = Carbon::today()->addDays(7);

        // Delete existing daily records in this date window to ensure clean, idempotent re-seeding
        DB::table('horoscopes')
            ->where('horoscope_type', 'daily')
            ->whereBetween('start_date', [$startDay->toDateString() . ' 00:00:00', $endDay->toDateString() . ' 23:59:59'])
            ->delete();

        for ($day = $startDay->copy(); $day->lte($endDay); $day->addDay()) {
            foreach ($zodiacSigns as $sign => $info) {
                foreach ($languages as $lang) {
                    $records[] = $this->buildDailyHoroscope($sign, $info, $lang, $day);
                }
            }
        }

        // 2. Seed Weekly, Monthly, Yearly horoscopes
        $otherTypes = ['weekly', 'monthly', 'yearly'];
        $now = Carbon::now();

        foreach ($otherTypes as $type) {
            switch ($type) {
                case 'weekly':
                    $periodStart = $now->copy()->startOfWeek();
                    $periodEnd = $now->copy()->endOfWeek();
                    break;
                case 'monthly':
                    $periodStart = $now->copy()->startOfMonth();
                    $periodEnd = $now->copy()->endOfMonth();
                    break;
                case 'yearly':
                    $periodStart = $now->copy()->startOfYear();
                    $periodEnd = $now->copy()->endOfYear();
                    break;
            }

            foreach ($zodiacSigns as $sign => $info) {
                foreach ($languages as $lang) {
                    // Check if already exists for this period
                    $exists = DB::table('horoscopes')
                        ->where('horoscope_type', $type)
                        ->where('zodiac_sign', $sign)
                        ->where('language', $lang)
                        ->whereDate('start_date', $periodStart->toDateString())
                        ->exists();

                    if (!$exists) {
                        $records[] = $this->buildPeriodicHoroscope($sign, $info, $type, $lang, $periodStart, $periodEnd);
                    }
                }
            }
        }

        // Insert in chunks of 100 for optimal performance
        foreach (array_chunk($records, 100) as $chunk) {
            DB::table('horoscopes')->insert($chunk);
        }

        // Invalidate cached horoscope responses
        DailyHoroscopeService::invalidateCache();
    }

    private function buildDailyHoroscope(string $sign, array $info, string $lang, Carbon $date): array
    {
        $colors = ['Red', 'Blue', 'Green', 'Yellow', 'Golden', 'Silver', 'Purple', 'Orange', 'White', 'Maroon'];
        $colorsHi = ['लाल', 'नीला', 'हरा', 'पीला', 'सुनहरा', 'सफेद', 'बैंगनी', 'नारंगी', 'श्वेत', 'मैरून'];
        $moods = ['Confident', 'Optimistic', 'Focused', 'Calm', 'Energetic', 'Reflective', 'Peaceful', 'Harmonious'];
        $moodsHi = ['आत्मविश्वासी', 'सकारात्मक', 'एकाग्र', 'शांत', 'ऊर्जावान', 'विचारशील', 'प्रसन्न', 'संतुलित'];

        $signHi = $info['hi'];
        $colorIdx = (crc32($sign . $date->toDateString()) % count($colors));
        $moodIdx = (crc32($date->toDateString() . $sign) % count($moods));
        $luckyNum = ((crc32($sign . $date->day) % 99) + 1);

        $times = ['08:30 AM', '10:15 AM', '02:00 PM', '04:45 PM', '06:30 PM', '09:00 PM'];
        $luckyTime = $times[(crc32($sign . $date->toDateString() . 'time') % count($times))];

        if ($lang === 'en') {
            $context = "Today brings strong potential for {$sign}. Planetary alignments support clear decision-making and steady progress.";
            $love = "Harmonious communication strengthens bonds. Singles may find engaging conversations with like-minded individuals.";
            $career = "Professional focus yields productive results. Your initiative and leadership will be recognized by colleagues.";
            $money = "Financial stability prevails. It is a favorable day to review long-term investments and curb impulse expenses.";
            $health = "Vitality is elevated. Maintain balance with mindful eating, moderate exercise, and sufficient hydration.";
            $travel = "Short journeys for personal or professional growth will prove rewarding and refreshing.";
            $luckyColor = $colors[$colorIdx];
            $mood = $moods[$moodIdx];
            $tag = 'Daily Horoscope';
        } else {
            $context = "आज का दिन {$signHi} राशि के जातकों के लिए नई ऊर्जा और प्रगति लेकर आया है। ग्रहों की अनुकूल स्थिति आपके निर्णयों को संबल देगी।";
            $love = "पारिवारिक एवं प्रेम संबंधों में सौहार्द बढ़ेगा। अपनों के साथ समय बिताना मानसिक संतोष और प्रगाढ़ता प्रदान करेगा।";
            $career = "कार्यक्षेत्र में आपके प्रयास सफल होंगे। सहकर्मियों का सहयोग और वरिष्ठ अधिकारियों से प्रोत्साहन मिलने के योग हैं।";
            $money = "आर्थिक स्थिति सुदृढ़ रहेगी। योजनाबद्ध तरीके से किए गए निवेश लाभप्रद सिद्ध होंगे। अनावश्यक व्यय से बचें।";
            $health = "स्वास्थ्य उत्तम रहेगा। नियमित दिनचर्या, योग और संतुलित आहार से ताजगी और उत्साह बना रहेगा।";
            $travel = "छोटी यात्राओं की योजना बन सकती है जो लाभकारी और सुखद अनुभव प्रदान करेंगी।";
            $luckyColor = $colorsHi[$colorIdx];
            $mood = $moodsHi[$moodIdx];
            $tag = 'दैनिक राशिफल';
        }

        return [
            'image' => null,
            'zodiac_sign' => $sign,
            'horoscope_type' => 'daily',
            'language' => $lang,
            'zodiac' => strtolower($sign),
            'tag' => $tag,
            'context' => $context,
            'love' => $love,
            'career' => $career,
            'money' => $money,
            'health' => $health,
            'travel' => $travel,
            'lucky_number' => (string) $luckyNum,
            'lucky_color' => $luckyColor,
            'mood' => $mood,
            'lucky_time' => $luckyTime,
            'start_date' => $date->format('Y-m-d H:i:s'),
            'end_date' => $date->copy()->endOfDay()->format('Y-m-d H:i:s'),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function buildPeriodicHoroscope(string $sign, array $info, string $type, string $lang, Carbon $startDate, Carbon $endDate): array
    {
        $signHi = $info['hi'];
        $typeLabel = ucfirst($type);

        return [
            'image' => null,
            'zodiac_sign' => $sign,
            'horoscope_type' => $type,
            'language' => $lang,
            'zodiac' => strtolower($sign),
            'tag' => $lang === 'en' ? "{$typeLabel} Horoscope" : "{$typeLabel} राशिफल",
            'context' => $lang === 'en'
                ? "This {$type} period highlights strategic growth, career development, and meaningful relationships for {$sign}."
                : "यह {$type} अवधि {$signHi} राशि के लिए रणनीतिक विकास, करियर में उन्नति और सौहार्दपूर्ण संबंधों का संकेत देती है।",
            'love' => $lang === 'en'
                ? "Consistent understanding and empathy will nurture mutual trust."
                : "परस्पर समझ और सहानुभूति से आपसी विश्वास और प्रेम में वृद्धि होगी।",
            'career' => $lang === 'en'
                ? "Key projects reach milestones through diligent effort and team collaboration."
                : "परिश्रम और टीम सहयोग से महत्वपूर्ण परियोजनाएं सफलता के चरण तक पहुंचेंगी।",
            'money' => $lang === 'en'
                ? "Disciplined budgeting leads to increased savings and future wealth accumulation."
                : "अनुशासित वित्तीय योजना से बचत बढ़ेगी और भविष्य के लिए धन संचय सुगम होगा।",
            'health' => $lang === 'en'
                ? "Maintain regular physical routines and restorative sleep."
                : "नियमित व्यायाम और पर्याप्त विश्राम से स्वास्थ्य अनुकूल बना रहेगा।",
            'travel' => $lang === 'en'
                ? "Travel plans during this period are favorable and rejuvenating."
                : "इस अवधि में की गई यात्राएं मनोरंजक एवं लाभकारी साबित होंगी।",
            'lucky_number' => (string) rand(1, 99),
            'lucky_color' => 'Blue',
            'mood' => 'Optimistic',
            'lucky_time' => '10:00 AM',
            'start_date' => $startDate->format('Y-m-d H:i:s'),
            'end_date' => $endDate->format('Y-m-d H:i:s'),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
