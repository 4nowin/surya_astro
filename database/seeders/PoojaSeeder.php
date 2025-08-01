<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PoojaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('poojas')->insert([
            [
                'title' => 'Shani Shanti Pooja',
                'tag' => 'Saturn Remedies',
                'language' => 'en',
                'excerpt' => 'Pacify the malefic effects of Saturn (Shani) in your birth chart.',
                'description' => 'This powerful pooja helps reduce the negative effects of Shani (Saturn), especially during Sade Sati or Dhaiya. It brings relief from obstacles, delays, and hardships caused by Saturn’s placement.',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/shani-shanti.jpg',
                'price' => 1100,
                'original_price' => 3100,
                'active' => true,
                'home_priority' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Rudra Abhishek Pooja',
                'tag' => 'Shiva Pooja',
                'language' => 'en',
                'excerpt' => 'A sacred ritual to please Lord Shiva and remove obstacles from life.',
                'description' => 'Rudra Abhishek is a powerful pooja performed to seek Lord Shiva’s divine grace, remove sins, and bring peace, success, and health into life.',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/rudra_abhishek_pooja.jpg',
                'price' => 1100,
                'original_price' => 3000,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Wealth & Prosperity Pooja',
                'tag' => 'Wealth & Prosperity',
                'language' => 'en',
                'excerpt' => 'Attract abundance and financial growth with this special pooja.',
                'description' => 'This pooja invokes divine blessings to enhance wealth, career success, and overall prosperity, helping individuals and businesses grow financially.',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/wealth_prosperity_pooja.jpg',
                'price' => 1111,
                'original_price' => 5151,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Kaal Sarp Dosh Nivaran Pooja',
                'tag' => 'Dosh Nivaran',
                'language' => 'en',
                'excerpt' => 'Eliminate the effects of Kaal Sarp Dosh and bring stability to life.',
                'description' => 'This pooja helps individuals suffering from Kaal Sarp Dosh, ensuring protection from obstacles, financial instability, and mental distress.',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/kaal_sarp.jpg',
                'price' => 1100,
                'original_price' => 2100,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Pitra Dosh Pooja',
                'tag' => 'Ancestral Peace',
                'language' => 'en',
                'excerpt' => 'Honor your ancestors and remove ancestral karmic blockages.',
                'description' => 'This pooja helps in offering peace to departed ancestors, resolving Pitra Dosh, and bringing prosperity and harmony to the family.',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/pitra_dosh.jpg',
                'price' => 2100,
                'original_price' => 4100,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Career & Job Success Pooja',
                'tag' => 'Career Growth',
                'language' => 'en',
                'excerpt' => 'Achieve job stability and career growth with divine blessings.',
                'description' => 'This pooja is performed to remove obstacles in job, career, and business, ensuring stability, promotions, and professional success.',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/career_job.jpg',
                'price' => 501,
                'original_price' => 2100,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Health & Wellness Pooja',
                'tag' => 'Health & Healing',
                'language' => 'en',
                'excerpt' => 'Promote physical and mental well-being with divine intervention.',
                'description' => 'This pooja is performed to invoke blessings for good health, recovery from illness, and protection from negative health energies.',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/health_wellness.jpg',
                'price' => 701,
                'original_price' => 2500,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Manglik Dosh Nivaran Pooja',
                'tag' => 'Marriage Remedies',
                'language' => 'en',
                'excerpt' => 'Neutralize Manglik Dosh and promote harmonious marital life.',
                'description' => 'This pooja reduces the negative effects of Manglik Dosh and enhances compatibility and success in marriage.',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/manglik_dosh.jpg',
                'price' => 1100,
                'original_price' => 3100,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Navgraha Shanti Pooja',
                'tag' => 'Planetary Peace',
                'language' => 'en',
                'excerpt' => 'Pacify malefic planets and strengthen beneficial planetary influences.',
                'description' => 'This powerful pooja is for those facing planetary issues in their horoscope and aims to balance cosmic influences for a peaceful life.',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/navgraha_shanti.jpg',
                'price' => 1500,
                'original_price' => 4500,
                'active' => true,
                'home_priority' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Marriage & Relationship Harmony Pooja',
                'tag' => 'Love & Relationship',
                'language' => 'en',
                'excerpt' => 'Restore love, trust, and harmony in relationships.',
                'description' => 'This pooja helps those facing challenges in marriage or relationships by invoking divine blessings for unity and affection.',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/love_relationship.jpg',
                'price' => 901,
                'original_price' => 2700,
                'active' => true,
                'home_priority' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Business Growth Pooja',
                'tag' => 'Business Success',
                'language' => 'en',
                'excerpt' => 'Boost your business success with divine blessings.',
                'description' => 'This pooja brings positive energy to your business ventures, helping with profits, stability, and expansion.',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/business_growth.jpg',
                'price' => 1300,
                'original_price' => 4100,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Black Magic Protection Pooja',
                'tag' => 'Spiritual Protection',
                'language' => 'en',
                'excerpt' => 'Safeguard yourself from black magic and evil eye effects.',
                'description' => 'This powerful pooja forms a divine shield to remove and protect from dark energies, black magic, and psychic attacks.',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/black_magic.jpg',
                'price' => 1600,
                'original_price' => 5100,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Peace & Harmony Pooja',
                'tag' => 'Calm & Clarity',
                'language' => 'en',
                'excerpt' => 'Invite peace, mental calmness, and family harmony into your life.',
                'description' => 'This pooja helps to eliminate stress, internal conflicts, and discord among family members by invoking tranquility.',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/peace_harmony.jpg',
                'price' => 701,
                'original_price' => 2100,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Nakshatra Shanti Pooja',
                'tag' => 'Birth Chart Remedies',
                'language' => 'en',
                'excerpt' => 'Correct harmful nakshatra influences from your birth chart.',
                'description' => 'This pooja is tailored to the native’s birth nakshatra and helps pacify ill effects related to birth stars and planetary alignment.',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/nakshatra_shanti.jpg',
                'price' => 1800,
                'original_price' => 3700,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Graha Dosh Nivaran Pooja',
                'tag' => 'Astrological Remedies',
                'language' => 'en',
                'excerpt' => 'Balance your planets and nullify doshas in your chart.',
                'description' => 'A highly effective pooja for nullifying Graha Doshas and enhancing the positive influence of planets in one’s life.',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/graha_dosh.jpg',
                'price' => 1200,
                'original_price' => 3300,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Child Blessing Pooja',
                'tag' => 'Fertility & Children',
                'language' => 'en',
                'excerpt' => 'Seek divine blessings for childbirth and healthy offspring.',
                'description' => 'This pooja is performed for couples seeking children or protection and good health for their existing children.',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/child_blessing.jpg',
                'price' => 901,
                'original_price' => 2600,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Santan Gopal Pooja',
                'tag' => 'Child Blessings',
                'language' => 'en',
                'excerpt' => 'A sacred ritual to bless childless couples with progeny.',
                'description' => 'Performed to please Lord Krishna in his infant form, this pooja aids in fertility and successful childbirth.',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/santan_gopal.jpg',
                'price' => 1500,
                'original_price' => 3500,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Mahamrityunjaya Jaap',
                'tag' => 'Health & Longevity',
                'language' => 'en',
                'excerpt' => 'Powerful mantra chanting for protection against untimely death.',
                'description' => 'This powerful ritual invokes Lord Shiva to overcome fear, diseases, and death. Ideal for healing, recovery, and longevity.',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/mahamrityunjaya_jaap.jpg',
                'price' => 2500,
                'original_price' => 5100,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Chandra Grahan Special Pooja',
                'tag' => 'Eclipse Pooja',
                'language' => 'en',
                'excerpt' => 'Shield yourself from the adverse effects of lunar eclipses.',
                'description' => 'This pooja helps minimize the negative spiritual and astrological effects caused by a lunar eclipse.',
                'start_date' => '2025-09-07',
                'end_date' => '2025-09-08',
                'image' => '/seed/pooja/chandra_grahan.jpg',
                'price' => 999,
                'original_price' => 2500,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Mahashivratri Blessings Pooja',
                'tag' => 'Shivratri Special',
                'language' => 'en',
                'excerpt' => 'Seek divine blessings from Lord Shiva on the sacred occasion of Mahashivratri.',
                'description' => 'This special pooja is performed on Mahashivratri to invoke Lord Shiva’s blessings for spiritual growth, prosperity, and protection from negative energies.',
                'start_date' => '2026-02-15',
                'end_date' => '2026-02-16',
                'image' => '/seed/pooja/mahashivratri_blessings.jpg',
                'price' => 2100,
                'original_price' => 4000,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Hanuman Jayanti Special Pooja',
                'tag' => 'Hanuman Jayanti',
                'language' => 'en',
                'excerpt' => 'Gain strength and protection through Lord Hanuman’s blessings.',
                'description' => 'On the occasion of Hanuman Jayanti, this pooja brings courage, protection, and victory over enemies and obstacles.',
                'start_date' => '2026-04-02',
                'end_date' => '2026-04-02',
                'image' => '/seed/pooja/hanuman_jayanti.jpg',
                'price' => 801,
                'original_price' => 2000,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'शनि शांति पूजा',
                'tag' => 'शनि दोष निवारण',
                'language' => 'hi',
                'excerpt' => 'जन्म कुंडली में शनि के अशुभ प्रभावों को शांत करें।',
                'description' => 'यह प्रभावशाली पूजा शनि के दोषों को कम करने में सहायक है, विशेष रूप से साढ़े साती या ढैय्या के समय। यह पूजा शनि के कारण उत्पन्न रुकावटों, देरी और कष्टों से राहत देती है।',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/shani-shanti.jpg',
                'price' => 1100,
                'original_price' => 3100,
                'active' => true,
                'home_priority' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'रुद्राभिषेक पूजा',
                'tag' => 'शिव पूजा',
                'language' => 'hi',
                'excerpt' => 'भगवान शिव को प्रसन्न करने और जीवन से बाधाएं दूर करने की एक पवित्र विधि।',
                'description' => 'रुद्राभिषेक एक शक्तिशाली पूजा है जो भगवान शिव की कृपा प्राप्त करने, पापों से मुक्ति और जीवन में शांति, सफलता और स्वास्थ्य के लिए की जाती है।',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/rudra_abhishek_pooja.jpg',
                'price' => 2500,
                'original_price' => 5100,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'धन एवं समृद्धि पूजा',
                'tag' => 'धन एवं समृद्धि',
                'language' => 'hi',
                'excerpt' => 'इस विशेष पूजा से आर्थिक वृद्धि और समृद्धि को आकर्षित करें।',
                'description' => 'यह पूजा धन, करियर में सफलता और समग्र समृद्धि को बढ़ाने के लिए दिव्य आशीर्वाद प्रदान करती है, जिससे व्यक्ति और व्यवसाय दोनों आर्थिक रूप से बढ़ते हैं।',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/wealth_prosperity_pooja.jpg',
                'price' => 2500,
                'original_price' => 5100,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'कालसर्प दोष निवारण पूजा',
                'tag' => 'दोष निवारण',
                'language' => 'hi',
                'excerpt' => 'कालसर्प दोष के प्रभावों को समाप्त कर जीवन में स्थिरता लाएं।',
                'description' => 'यह पूजा कालसर्प दोष से पीड़ित व्यक्तियों को जीवन की बाधाओं, आर्थिक अस्थिरता और मानसिक तनाव से सुरक्षा प्रदान करती है।',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/kaal_sarp.jpg',
                'price' => 2500,
                'original_price' => 5100,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'पितृ दोष पूजा',
                'tag' => 'पितृ शांति',
                'language' => 'hi',
                'excerpt' => 'अपने पितरों को सम्मान दें और पूर्वजों के कर्म बंधनों को दूर करें।',
                'description' => 'यह पूजा departed पितरों की शांति के लिए, पितृ दोष को समाप्त करने और परिवार में समृद्धि एवं सौहार्द लाने हेतु की जाती है।',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/pitra_dosh.jpg',
                'price' => 2500,
                'original_price' => 5100,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'कैरियर और नौकरी सफलता पूजा',
                'tag' => 'कैरियर विकास',
                'language' => 'hi',
                'excerpt' => 'ईश्वरीय आशीर्वाद से नौकरी की स्थिरता और कैरियर में उन्नति प्राप्त करें।',
                'description' => 'यह पूजा नौकरी, कैरियर और व्यवसाय में आने वाली बाधाओं को दूर करने के लिए की जाती है, जिससे स्थिरता, पदोन्नति और व्यावसायिक सफलता सुनिश्चित होती है।',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/career_job.jpg',
                'price' => 501,
                'original_price' => 2100,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'स्वास्थ्य और तंदुरुस्ती पूजा',
                'tag' => 'स्वास्थ्य और उपचार',
                'language' => 'hi',
                'excerpt' => 'ईश्वरीय कृपा से शारीरिक और मानसिक स्वास्थ्य को बढ़ावा दें।',
                'description' => 'यह पूजा अच्छे स्वास्थ्य, बीमारी से मुक्ति और नकारात्मक स्वास्थ्य ऊर्जा से सुरक्षा के लिए की जाती है।',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/health_wellness.jpg',
                'price' => 701,
                'original_price' => 2500,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'मांगलिक दोष निवारण पूजा',
                'tag' => 'विवाह उपाय',
                'language' => 'hi',
                'excerpt' => 'मांगलिक दोष को शांत कर सुखमय वैवाहिक जीवन पाएं।',
                'description' => 'यह पूजा मांगलिक दोष के नकारात्मक प्रभावों को कम करने और विवाह में सामंजस्य व सफलता को बढ़ाने के लिए की जाती है।',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/manglik_dosh.jpg',
                'price' => 1100,
                'original_price' => 3100,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'नवग्रह शांति पूजा',
                'tag' => 'ग्रहों की शांति',
                'language' => 'hi',
                'excerpt' => 'अशुभ ग्रहों को शांत कर शुभ प्रभावों को मजबूत करें।',
                'description' => 'यह शक्तिशाली पूजा उन लोगों के लिए है जो कुंडली में ग्रह बाधाओं का सामना कर रहे हैं, और यह जीवन में संतुलन और शांति लाने के लिए की जाती है।',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/navgraha_shanti.jpg',
                'price' => 1500,
                'original_price' => 4500,
                'active' => true,
                'home_priority' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'विवाह और संबंध सामंजस्य पूजा',
                'tag' => 'प्रेम और संबंध',
                'language' => 'hi',
                'excerpt' => 'रिश्तों में प्रेम, विश्वास और सामंजस्य को पुनः स्थापित करें।',
                'description' => 'यह पूजा विवाह या संबंधों में समस्याओं का सामना कर रहे लोगों के लिए है, जिसमें एकता और स्नेह के लिए ईश्वरीय आशीर्वाद मांगा जाता है।',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/love_relationship.jpg',
                'price' => 901,
                'original_price' => 2700,
                'active' => true,
                'home_priority' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'व्यवसाय वृद्धि पूजा',
                'tag' => 'व्यवसाय सफलता',
                'language' => 'hi',
                'excerpt' => 'ईश्वरीय आशीर्वाद से अपने व्यवसाय में सफलता प्राप्त करें।',
                'description' => 'यह पूजा आपके व्यवसायिक प्रयासों में सकारात्मक ऊर्जा लाती है, जिससे लाभ, स्थिरता और विस्तार में सहायता मिलती है।',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/business_growth.jpg',
                'price' => 1300,
                'original_price' => 4100,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'काला जादू सुरक्षा पूजा',
                'tag' => 'आध्यात्मिक सुरक्षा',
                'language' => 'hi',
                'excerpt' => 'काले जादू और बुरी नजर के प्रभाव से स्वयं की रक्षा करें।',
                'description' => 'यह शक्तिशाली पूजा बुरी शक्तियों, काले जादू और मानसिक हमलों से सुरक्षा के लिए एक दिव्य कवच प्रदान करती है।',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/black_magic.jpg',
                'price' => 1600,
                'original_price' => 5100,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'शांति और सौहार्द पूजा',
                'tag' => 'शांति और स्पष्टता',
                'language' => 'hi',
                'excerpt' => 'अपने जीवन में मानसिक शांति और पारिवारिक सौहार्द लाएं।',
                'description' => 'यह पूजा तनाव, आंतरिक संघर्ष और पारिवारिक कलह को दूर करने में सहायक होती है और शांति का आह्वान करती है।',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/peace_harmony.jpg',
                'price' => 701,
                'original_price' => 2100,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'नक्षत्र शांति पूजा',
                'tag' => 'कुंडली उपाय',
                'language' => 'hi',
                'excerpt' => 'जन्म नक्षत्र के हानिकारक प्रभावों को शांत करें।',
                'description' => 'यह पूजा जातक के जन्म नक्षत्र के अनुसार की जाती है और जन्म नक्षत्र तथा ग्रहों की स्थिति से उत्पन्न दोषों को शांत करती है।',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/nakshatra_shanti.jpg',
                'price' => 1800,
                'original_price' => 3700,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'ग्रह दोष निवारण पूजा',
                'tag' => 'ज्योतिषीय उपाय',
                'language' => 'hi',
                'excerpt' => 'ग्रहों का संतुलन करें और कुंडली के दोषों को दूर करें।',
                'description' => 'यह पूजा ग्रह दोषों को दूर करने और जीवन में ग्रहों के सकारात्मक प्रभाव को बढ़ाने में अत्यंत प्रभावी मानी जाती है।',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/graha_dosh.jpg',
                'price' => 1200,
                'original_price' => 3300,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'संतान प्राप्ति पूजा',
                'tag' => 'प्रजनन और संतान',
                'language' => 'hi',
                'excerpt' => 'संतान प्राप्ति और स्वस्थ संतान के लिए ईश्वरीय आशीर्वाद प्राप्त करें।',
                'description' => 'यह पूजा उन दंपतियों के लिए की जाती है जो संतान की प्राप्ति या पहले से मौजूद बच्चों की सुरक्षा और अच्छे स्वास्थ्य की कामना करते हैं।',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/child_blessing.jpg',
                'price' => 901,
                'original_price' => 2600,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'संतान गोपाल पूजा',
                'tag' => 'संतान आशीर्वाद',
                'language' => 'hi',
                'excerpt' => 'संतान सुख के लिए एक पवित्र अनुष्ठान।',
                'description' => 'भगवान श्रीकृष्ण के बाल रूप को प्रसन्न करने हेतु की जाने वाली यह पूजा प्रजनन और सफल संतान जन्म में सहायता करती है।',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/santan_gopal.jpg',
                'price' => 1500,
                'original_price' => 3500,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'महामृत्युंजय जाप',
                'tag' => 'स्वास्थ्य और दीर्घायु',
                'language' => 'hi',
                'excerpt' => 'अकाल मृत्यु से रक्षा के लिए शक्तिशाली मंत्र जाप।',
                'description' => 'यह शक्तिशाली अनुष्ठान भगवान शिव का आह्वान करता है, जो भय, रोग और मृत्यु पर विजय दिलाने में सहायक होता है। यह पूजा स्वास्थ्य लाभ, रोगमुक्ति और दीर्घायु के लिए उपयुक्त है।',
                'start_date' => null,
                'end_date' => null,
                'image' => '/seed/pooja/mahamrityunjaya_jaap.jpg',
                'price' => 2500,
                'original_price' => 5100,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'चंद्र ग्रहण विशेष पूजा',
                'tag' => 'ग्रहण पूजा',
                'language' => 'hi',
                'excerpt' => 'चंद्र ग्रहण के अशुभ प्रभावों से स्वयं को सुरक्षित रखें।',
                'description' => 'यह पूजा चंद्र ग्रहण से उत्पन्न आध्यात्मिक और ज्योतिषीय नकारात्मक प्रभावों को कम करने में सहायक होती है।',
                'start_date' => '2025-09-07',
                'end_date' => '2025-09-08',
                'image' => '/seed/pooja/chandra_grahan.jpg',
                'price' => 999,
                'original_price' => 2500,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'महाशिवरात्रि आशीर्वाद पूजा',
                'tag' => 'शिवरात्रि विशेष',
                'language' => 'hi',
                'excerpt' => 'महाशिवरात्रि के पवित्र अवसर पर भगवान शिव का दिव्य आशीर्वाद प्राप्त करें।',
                'description' => 'यह विशेष पूजा महाशिवरात्रि पर आध्यात्मिक विकास, समृद्धि और नकारात्मक ऊर्जा से सुरक्षा के लिए भगवान शिव का आशीर्वाद प्राप्त करने हेतु की जाती है।',
                'start_date' => '2026-05-15',
                'end_date' => '2026-05-16',
                'image' => '/seed/pooja/mahashivratri_blessings.jpg',
                'price' => 2500,
                'original_price' => 5100,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'हनुमान जयंती विशेष पूजा',
                'tag' => 'हनुमान जयंती',
                'language' => 'hi',
                'excerpt' => 'भगवान हनुमान की कृपा से शक्ति और सुरक्षा प्राप्त करें।',
                'description' => 'हनुमान जयंती के अवसर पर की जाने वाली यह पूजा साहस, सुरक्षा, और शत्रुओं एवं बाधाओं पर विजय प्रदान करती है।',
                'start_date' => '2026-04-02',
                'end_date' => '2026-04-02',
                'image' => '/seed/pooja/hanuman_jayanti.jpg',
                'price' => 801,
                'original_price' => 2000,
                'active' => true,
                'home_priority' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
