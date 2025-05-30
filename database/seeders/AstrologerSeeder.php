<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AstrologerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $astrologers = [
            [
                'admin_id' => 2,
                'image' => '/seed/astro/ravinder.jpg',
                'name' => 'Ravinder Sharma',
                'language' => 'en',
                'astrologer_language' => 'Hindi, English',
                'expertise' => 'Vedic Astrology, Numerology',
                'experience' => '10 years',
                'excerpt' => 'Expert in Vedic astrology with over 10 years of experience.',
                'description' => 'Ravinder Sharma has helped thousands of clients find clarity in their personal and professional lives using time-tested Vedic astrology methods.',
                'chat_minutes' => 120,
                'call_minutes' => 95,
                'price' => 20,
                'original_price' => 30,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'admin_id' => 2,
                'image' => '/seed/astro/yashpal.jpg',
                'name' => 'Yashpal Sharma',
                'language' => 'en',
                'astrologer_language' => 'Gujarati, Hindi',
                'expertise' => 'Tarot Reading, Palmistry',
                'experience' => '7 years',
                'excerpt' => 'Specialist in Tarot reading with deep intuitive insights.',
                'description' => 'Yashpal Sharma is known for her compassionate readings and accurate predictions in the fields of love, relationships, and career guidance.',
                'chat_minutes' => 150,
                'call_minutes' => 110,
                'price' => 25,
                'original_price' => 35,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'admin_id' => 2,
                'image' => '/seed/astro/niti.jpg',
                'name' => 'Nitika Sharma',
                'language' => 'en',
                'astrologer_language' => 'English',
                'expertise' => 'Western Astrology, Numerology',
                'experience' => '5 years',
                'excerpt' => 'Combines Western astrology and numerology for modern guidance.',
                'description' => 'Nitika Sharma offers a unique blend of Western astrology techniques and numerological analysis to help people navigate complex life situations.',
                'chat_minutes' => 80,
                'call_minutes' => 60,
                'price' => 15,
                'original_price' => 25,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'admin_id' => 2,
                'image' => '/seed/astro/ravinder.jpg',
                'name' => 'रविंदर शर्मा',
                'language' => 'hi',
                'astrologer_language' => 'हिंदी, अंग्रेज़ी',
                'expertise' => 'वैदिक ज्योतिष, अंक ज्योतिष',
                'experience' => '10 वर्ष',
                'excerpt' => '10 वर्षों से अधिक अनुभव के साथ वैदिक ज्योतिष के विशेषज्ञ।',
                'description' => 'रविंदर शर्मा ने समय-परीक्षित वैदिक ज्योतिष विधियों का उपयोग करके हजारों लोगों को उनके व्यक्तिगत और व्यावसायिक जीवन में स्पष्टता पाने में मदद की है।',
                'chat_minutes' => 120,
                'call_minutes' => 95,
                'price' => 20,
                'original_price' => 30,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'admin_id' => 2,
                'image' => '/seed/astro/yashpal.jpg',
                'name' => 'यशपाल शर्मा',
                'language' => 'hi',
                'astrologer_language' => 'गुजराती, हिंदी',
                'expertise' => 'टैरो रीडिंग, हस्तरेखा शास्त्र',
                'experience' => '7 वर्ष',
                'excerpt' => 'गहरी अंतर्दृष्टि के साथ टैरो रीडिंग में विशेषज्ञ।',
                'description' => 'यशपाल शर्मा अपने सहानुभूतिपूर्ण रीडिंग्स और प्रेम, रिश्तों और करियर मार्गदर्शन में सटीक भविष्यवाणियों के लिए जानी जाती हैं।',
                'chat_minutes' => 150,
                'call_minutes' => 110,
                'price' => 25,
                'original_price' => 35,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'admin_id' => 2,
                'image' => '/seed/astro/niti.jpg',
                'name' => 'नितिका शर्मा',
                'language' => 'hi',
                'astrologer_language' => 'अंग्रेज़ी',
                'expertise' => 'पश्चिमी ज्योतिष, अंक ज्योतिष',
                'experience' => '5 वर्ष',
                'excerpt' => 'आधुनिक मार्गदर्शन के लिए पश्चिमी ज्योतिष और अंक ज्योतिष का समन्वय।',
                'description' => 'नितिका शर्मा पश्चिमी ज्योतिष की तकनीकों और अंक ज्योतिषीय विश्लेषण के अनूठे संयोजन के माध्यम से लोगों को जटिल जीवन स्थितियों में मार्गदर्शन प्रदान करते हैं।',
                'chat_minutes' => 80,
                'call_minutes' => 60,
                'price' => 15,
                'original_price' => 25,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('astrologers')->insert($astrologers);
    }
}
