<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Astrologer;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class AstrologerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define seed astrologer data mapped to respective practitioner accounts
        $practitioners = [
            'ravinder@astrosetu.com' => [
                'name' => 'Ravinder Sharma',
                'en' => [
                    'name' => 'Ravinder Sharma',
                    'image' => '/seed/astro/ravinder.jpg',
                    'astrologer_language' => 'Hindi, English',
                    'expertise' => 'Vedic Astrology, Numerology',
                    'experience' => '10 years',
                    'excerpt' => 'Expert in Vedic astrology with over 10 years of experience.',
                    'description' => 'Ravinder Sharma has helped thousands of clients find clarity in their personal and professional lives using time-tested Vedic astrology methods.',
                    'chat_minutes' => 120,
                    'call_minutes' => 95,
                    'price' => 5,
                    'call_price' => 6,
                    'original_price' => 10,
                    'status' => 'online',
                    'active' => true,
                ],
                'hi' => [
                    'name' => 'रविंदर शर्मा',
                    'image' => '/seed/astro/ravinder.jpg',
                    'astrologer_language' => 'हिंदी, अंग्रेज़ी',
                    'expertise' => 'वैदिक ज्योतिष, अंक ज्योतिष',
                    'experience' => '10 वर्ष',
                    'excerpt' => '10 वर्षों से अधिक अनुभव के साथ वैदिक ज्योतिष के विशेषज्ञ।',
                    'description' => 'रविंदर शर्मा ने समय-परीक्षित वैदिक ज्योतिष विधियों का उपयोग करके हजारों लोगों को उनके व्यक्तिगत और व्यावसायिक जीवन में स्पष्टता पाने में मदद की है।',
                    'chat_minutes' => 120,
                    'call_minutes' => 95,
                    'price' => 5,
                    'call_price' => 6,
                    'original_price' => 10,
                    'status' => 'online',
                    'active' => true,
                ],
            ],
            'suman@astrosetu.com' => [
                'name' => 'Suman Sharma',
                'en' => [
                    'name' => 'Suman Sharma',
                    'image' => '/seed/astro/suman.jpg',
                    'astrologer_language' => 'Hindi, English',
                    'expertise' => 'Western Astrology, Numerology',
                    'experience' => '5 years',
                    'excerpt' => 'Combines Western astrology and numerology for modern guidance.',
                    'description' => 'Nitika Sharma offers a unique blend of Western astrology techniques and numerological analysis to help people navigate complex life situations.',
                    'chat_minutes' => 80,
                    'call_minutes' => 60,
                    'price' => 8,
                    'call_price' => 10,
                    'original_price' => 16,
                    'status' => 'online',
                    'active' => true,
                ],
                'hi' => [
                    'name' => 'सुमन शर्मा',
                    'image' => '/seed/astro/suman.jpg',
                    'astrologer_language' => 'हिंदी, अंग्रेज़ी',
                    'expertise' => 'पश्चिमी ज्योतिष, अंक ज्योतिष',
                    'experience' => '5 वर्ष',
                    'excerpt' => 'आधुनिक मार्गदर्शन के लिए पश्चिमी ज्योतिष और अंक ज्योतिष का समन्वय।',
                    'description' => 'नितिका शर्मा पश्चिमी ज्योतिष की तकनीकों और अंक ज्योतिषीय विश्लेषण के अनूठे संयोजन के माध्यम से लोगों को जटिल जीवन स्थितियों में मार्गदर्शन प्रदान करते हैं।',
                    'chat_minutes' => 80,
                    'call_minutes' => 60,
                    'price' => 8,
                    'call_price' => 10,
                    'original_price' => 16,
                    'status' => 'online',
                    'active' => true,
                ],
            ],
            'yashpal@astrosetu.com' => [
                'name' => 'Yashpal Sharma',
                'en' => [
                    'name' => 'Yashpal Sharma',
                    'image' => '/seed/astro/yashpal.jpg',
                    'astrologer_language' => 'Hindi, English',
                    'expertise' => 'Tarot Reading, Palmistry',
                    'experience' => '7 years',
                    'excerpt' => 'Specialist in Tarot reading with deep intuitive insights.',
                    'description' => 'Yashpal Sharma is known for her compassionate readings and accurate predictions in the fields of love, relationships, and career guidance.',
                    'chat_minutes' => 150,
                    'call_minutes' => 110,
                    'price' => 6,
                    'call_price' => 8,
                    'original_price' => 12,
                    'status' => 'online',
                    'active' => true,
                ],
                'hi' => [
                    'name' => 'यशपाल शर्मा',
                    'image' => '/seed/astro/yashpal.jpg',
                    'astrologer_language' => 'हिंदी, अंग्रेज़ी',
                    'expertise' => 'टैरो रीडिंग, हस्तरेखा शास्त्र',
                    'experience' => '7 वर्ष',
                    'excerpt' => 'गहरी अंतर्दृष्टि के साथ टैरो रीडिंग में विशेषज्ञ।',
                    'description' => 'यशपाल शर्मा अपने सहानुभूतिपूर्ण रीडिंग्स और प्रेम, रिश्तों और करियर मार्गदर्शन में सटीक भविष्यवाणियों के लिए जानी जाती हैं।',
                    'chat_minutes' => 150,
                    'call_minutes' => 110,
                    'price' => 6,
                    'call_price' => 8,
                    'original_price' => 12,
                    'status' => 'online',
                    'active' => true,
                ],
            ],
        ];

        $astrologerRole = Role::firstOrCreate(['name' => 'Astrologer', 'guard_name' => 'admin']);

        foreach ($practitioners as $email => $data) {
            $admin = Admin::where('email', $email)->first();
            if (!$admin) {
                $admin = Admin::create([
                    'name' => $data['name'],
                    'email' => $email,
                    'password' => bcrypt('12345678'),
                ]);
                $admin->assignRole($astrologerRole);
            }

            foreach (['en', 'hi'] as $lang) {
                $details = $data[$lang];
                Astrologer::updateOrCreate(
                    [
                        'admin_id' => $admin->id,
                        'language' => $lang,
                    ],
                    array_merge($details, [
                        'admin_id' => $admin->id,
                        'language' => $lang,
                    ])
                );
            }
        }
    }
}
