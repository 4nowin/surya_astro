<?php

declare(strict_types=1);

namespace App\Services\Astrology\Interpretation;

/**
 * Deterministic, rule-based bilingual interpretation service for Detailed Kundli Reports.
 *
 * Implements Work Package A5 of Astro Setu Milestone M9.2.
 *
 * Core Guarantees:
 *  - 100% deterministic rule-based output (zero stochastic LLM / AI API dependencies).
 *  - Full English ('en') and Hindi ('hi') parity.
 *  - Classical Purushartha ethical framing (Karma vs conscious action).
 *  - Explicit Non-medical Nadi disclaimer.
 *  - Explicit Physical Gemstone algorithmic prescription disclaimer.
 */
class DetailedInterpretationService
{
    public const RULE_VERSION = 'M9.2_A5_V1';

    /**
     * Ethical Purushartha Framing Disclaimers.
     */
    public const PURUSHARTHA_FRAMING = [
        'en' => 'Vedic astrology is an ancient symbolic science of self-understanding and timing, mapping the karmic tendencies (Prarabdha Karma) present at birth. It does NOT represent fatalistic or immutable destiny. The highest Vedic doctrine asserts that conscious free will, moral integrity, self-discipline, and proactive effort (Purushartha) have the power to shape character, transcend obstacles, and transform outcomes.',
        'hi' => 'वैदिक ज्योतिष आत्म-बोध और काल-ज्ञान का एक पारंपरिक प्रतीकात्मक विज्ञान है, जो जन्म के समय उपस्थित प्रारब्ध कर्मों और प्रवृत्तियों को दर्शाता है। यह किसी भी प्रकार का अटल या भाग्यवादी निर्णय नहीं है। वैदिक सिद्धांत के अनुसार सचेत पुरुषार्थ, नैतिक आचरण, संयम और जागरूक कर्म के द्वारा मनुष्य चुनौतियों का सामना करते हुए अपने जीवन को सकारात्मक दिशा देने में पूर्णतः समर्थ है।',
    ];

    /**
     * Non-Medical Nadi Disclaimer.
     */
    public const NADI_NON_MEDICAL_DISCLAIMER = [
        'en' => 'IMPORTANT HEALTH DISCLAIMER: In classical Vedic astrology, Nadi classifications (Adi, Madhya, Antya) represent traditional physiological balance categories and energetic temperament indicators within Avakahada and Ashta Kuta matching. They do NOT constitute medical, physiological, or genetic diagnoses. For all physical, mental, or medical health concerns, always consult a qualified licensed healthcare physician or medical specialist.',
        'hi' => 'महत्वपूर्ण स्वास्थ्य अस्वीकरण: वैदिक ज्योतिष में नाड़ी (आदि, मध्य, अंत्य) का वर्गीकरण पारंपरिक स्वभाव, वात-पित्त-कफ त्रिदोष संतुलन और प्रतीकात्मक ऊर्जा के अध्ययन के लिए किया जाता है। यह किसी भी प्रकार का चिकित्सीय, शारीरिक या आनुवंशिक निदान (Diagnosis) नहीं है। किसी भी स्वास्थ्य संबंधी समस्या अथवा उपचार हेतु सदैव प्रमाणित चिकित्सक (Doctor) से परामर्श लें।',
    ];

    /**
     * Physical Gemstone Algorithmic Prescription Disclaimer.
     */
    public const GEMSTONE_ALGORITHMIC_DISCLAIMER = [
        'en' => 'GEMSTONE ADVISORY NOTICE: Astro Setu strictly maintains Vedic ethical standards and does NOT algorithmically prescribe gemstones. Gemstone therapy involves precise mineral resonance, clarity, carat weight, metal setting, touch-to-skin contact, and in-depth structural chart examination. Inappropriate gemstone wearing can aggravate planetary disharmony. Gemstones must only be selected after personal consultation with an accredited, trustworthy gemstone specialist.',
        'hi' => 'रत्न परामर्श सूचना: एस्ट्रो सेतु वैदिक मर्यादा का पालन करते हुए किसी भी प्रकार के रत्न का कंप्यूटर-जनित या स्वचालित परामर्श प्रदान नहीं करता है। रत्न धारण केवल ग्रह स्थिति, शारीरिक प्रकृति और रत्न की गुणवत्ता (शुद्धता, वजन, धातु) की प्रत्यक्ष जांच के बाद ही किया जाना चाहिए। अनुचित रत्न धारण से प्रतिकूल प्रभाव हो सकता है। अतः रत्न केवल किसी अनुभवी ज्योतिषी व विशेषज्ञ से प्रत्यक्ष विचार-विमर्श के बाद ही धारण करें।',
    ];

    /**
     * General Report Disclaimer.
     */
    public const GENERAL_DISCLAIMER = [
        'en' => 'This comprehensive Vedic horoscope report is generated mathematically based on True Chitra Paksha Lahiri Ayanamsha and Parashari classical principles. The contents are intended exclusively for spiritual insight, philosophical guidance, and self-reflection. They should not be used as a substitute for professional legal, financial, or medical advice.',
        'hi' => 'यह विस्तृत वैदिक जन्म पत्रिका प्रतिवेदन सत्य चित्र पक्ष लाहिड़ी अयनांश और पराशरी सिद्धांतों के आधार पर गणितीय रूप से तैयार किया गया है। यह विश्लेषण केवल आत्म-चिंतन, मार्गदर्शन और आध्यात्मिक अध्ययन के उद्देश्य से है। इसे किसी भी विधिक, वित्तीय या चिकित्सीय निर्णय के विकल्प के रूप में प्रयोग न करें।',
    ];

    /**
     * Get planetary dignity description.
     */
    public function getDignityInterpretation(string $planet, string $dignity, string $locale = 'en'): string
    {
        $dignity = strtolower($dignity);
        $planet = strtolower($planet);

        $dignityTexts = [
            'exalted' => [
                'en' => 'Exalted (Uchcha) — Operating at peak natural vitality, delivering clear, noble, and constructively elevated expression in life.',
                'hi' => 'उच्च राशि स्थित — ग्रह अपनी सर्वोच्च सामर्थ्य में है, जो उत्कृष्ट, सकारात्मक और प्रभावी फल प्रदान करने की क्षमता रखता है।',
            ],
            'moolatrikona' => [
                'en' => 'Moolatrikona — In its core office of duty, highly purposeful, balanced, and structurally effective.',
                'hi' => 'मूलत्रिकोण स्थित — ग्रह अपने मूल कार्यक्षेत्र में है, जिससे इसके कार्यों में स्वाभाविकता, सामर्थ्य और संतुलन रहता है।',
            ],
            'own_sign' => [
                'en' => 'Own Sign (Swakshetra) — Comfortable, resourceful, and self-sufficient, fostering natural stability and confidence.',
                'hi' => 'स्वक्षेत्री (स्वराशि) — ग्रह अपने घर में स्थित है, जिससे स्थायित्व, आत्म-विश्वास और सुदृढ़ आधार प्राप्त होता है।',
            ],
            'friendly' => [
                'en' => 'Friendly Sign (Mitra Rashi) — Supported by welcoming environment, encouraging cooperative development and steady growth.',
                'hi' => 'मित्र राशि स्थित — अनुकूल परिवेश में स्थित होने से ग्रह के शुभ प्रभावों में वृद्धि और सुगम प्रगति होती है।',
            ],
            'neutral' => [
                'en' => 'Neutral Sign (Sama Rashi) — Impartial and standard functional manifestation according to conscious individual effort.',
                'hi' => 'सम राशि स्थित — सामान्य और संतुलित प्रभाव, जहां परिणामों का निर्धारण व्यक्ति के व्यक्तिगत प्रयासों पर निर्भर करता है।',
            ],
            'enemy' => [
                'en' => 'Enemy Sign (Shatru Rashi) — Facing friction or resistance, calling for patience, conscious adjustment, and maturity.',
                'hi' => 'शत्रु राशि स्थित — कुछ आंतरिक या बाहरी प्रतिरोध का सामना करना पड़ सकता है; धैर्य और सजगता से सामंजस्य आवश्यक है।',
            ],
            'debilitated' => [
                'en' => 'Debilitated (Neecha) — Experiencing functional restraint; challenges the individual to cultivate humility, resilience, and inner strength.',
                'hi' => 'नीच राशि स्थित — ग्रह की बाह्य ऊर्जा संकुचित है; यह स्थिति व्यक्ति को आत्म-मंथन, धैर्य और आंतरिक शक्ति विकसित करने की प्रेरणा देती है।',
            ],
        ];

        return $dignityTexts[$dignity][$locale] ?? ($locale === 'hi' ? 'सामान्य स्थिति' : 'Standard dignity');
    }

    /**
     * Get Janma Nakshatra psychological and spiritual deep dive.
     */
    public function getNakshatraDeepDive(string $nakshatraName, int $pada, string $locale = 'en'): array
    {
        $normalized = strtolower(str_replace([' ', '-', '_'], '', $nakshatraName));

        $data = [
            'ashwini' => [
                'deity' => ['en' => 'Ashvini Kumaras (Divine Healers)', 'hi' => 'अश्विनी कुमार (दिव्य चिकित्सक)'],
                'symbol' => ['en' => 'Horse\'s Head', 'hi' => 'अश्व (घोड़े) का मुख'],
                'nature' => ['en' => 'Swift, dynamic initiative, natural healing impulse, independence.', 'hi' => 'तीव्र गति, नव-पहल, आरोग्य कारक क्षमता, स्वतंत्रता प्रियता।'],
                'guidance' => ['en' => 'Harness enthusiasm into sustained long-term persistence rather than rushing into immediate closure.', 'hi' => 'अपनी ऊर्जा और उत्साह को केवल आरंभ तक सीमित न रखकर दीर्घकालिक निरंतरता में बदलें।'],
            ],
            'bharani' => [
                'deity' => ['en' => 'Yama (God of Dharma & Transformation)', 'hi' => 'यम (धर्म और न्याय के देवता)'],
                'symbol' => ['en' => 'Yoni (Vessel of Creation)', 'hi' => 'योनि (सृजन और पोषण का प्रतीक)'],
                'nature' => ['en' => 'Deep endurance, intense emotional commitment, capacity to carry heavy responsibilities.', 'hi' => 'गहन सहनशीलता, भावनात्मक निष्ठा, बड़े दायित्वों को वहन करने की क्षमता।'],
                'guidance' => ['en' => 'Channel emotional intensity through disciplined creativity and constructive boundaries.', 'hi' => 'अपनी भावनात्मक ऊर्जा को अनुशासित सृजन और व्यावहारिक सीमाओं में दिशा दें।'],
            ],
            'krittika' => [
                'deity' => ['en' => 'Agni (Sacred Fire)', 'hi' => 'अग्नि (पवित्र ज्योति)'],
                'symbol' => ['en' => 'Razor / Flame', 'hi' => 'अस्तरा / ज्वाला'],
                'nature' => ['en' => 'Sharp analytical intellect, purifying truth-seeking, protective courage.', 'hi' => 'तीक्ष्ण तार्किक बुद्धि, सत्य-निष्ठा, संरक्षणकारी साहस।'],
                'guidance' => ['en' => 'Temper fiery discernment with gentle speech and compassionate understanding.', 'hi' => 'अपनी स्पष्टवादिता में मधुर वाणी और करुणा का समावेश करें।'],
            ],
            'rohini' => [
                'deity' => ['en' => 'Brahma / Prajapati (The Creator)', 'hi' => 'प्रजापति ब्रह्मा (सृष्टिकर्ता)'],
                'symbol' => ['en' => 'Ox Cart / Chariot', 'hi' => 'रथ / बैलगाड़ी (उपजाऊ शक्ति)'],
                'nature' => ['en' => 'Aesthetic elegance, emotional charm, creative cultivation, appreciation of beauty.', 'hi' => 'सौंदर्य दृष्टि, भावनात्मक आकर्षण, कलात्मक क्षमता, जीवन का आनंद।'],
                'guidance' => ['en' => 'Guard against excessive attachment to material comforts; balance pleasure with inner purpose.', 'hi' => 'भौतिक सुखों में अति-आसक्ति से बचें; जीवन के उच्च लक्ष्यों के साथ सामंजस्य रखें।'],
            ],
            'mrigashira' => [
                'deity' => ['en' => 'Soma (The Moon God of Nectar)', 'hi' => 'सोम (अमृत और सौम्यता के देवता)'],
                'symbol' => ['en' => 'Deer\'s Head', 'hi' => 'मृग (हिरण) का शीश'],
                'nature' => ['en' => 'Inquisitive seeker, gentle restlessness, intellectual curiosity, perceptive mind.', 'hi' => 'जिज्ञासु स्वभाव, निरंतर खोज की प्रवृत्ति, बौद्धिक संवेदनशीलता।'],
                'guidance' => ['en' => 'Find grounding in focused studies instead of perpetual questing without pause.', 'hi' => 'अपनी जिज्ञासा को किसी एक दिशा में केंद्रित करके पूर्णता तक पहुंचाएं।'],
            ],
            'ardra' => [
                'deity' => ['en' => 'Rudra (The Storm Transformation God)', 'hi' => 'रुद्र (परिवर्तनकारी शिव स्वरूप)'],
                'symbol' => ['en' => 'Teardrop / Diamond', 'hi' => 'अश्रु बिंदु / हीरा'],
                'nature' => ['en' => 'Profound insight born through overcoming challenges, intellectual intensity, breakthrough power.', 'hi' => 'कठिनाइयों से तपकर निखरने वाली बुद्धि, गहन शोध क्षमता, परिवर्तनकारी शक्ति।'],
                'guidance' => ['en' => 'Embrace life storms as refining forces; maintain peace of mind through regular meditation.', 'hi' => 'जीवन के बदलावों को सकारात्मक रूप में लें और ध्यान द्वारा मानसिक शांति बनाए रखें।'],
            ],
            'punarvasu' => [
                'deity' => ['en' => 'Aditi (Cosmic Mother of Light)', 'hi' => 'अदिति (देवमाता, असीम विस्तार की देवी)'],
                'symbol' => ['en' => 'Bow and Quiver of Arrows', 'hi' => 'धनुष और तरकश'],
                'nature' => ['en' => 'Renewal, resilience, philosophical optimism, returning home to virtue.', 'hi' => 'पुनरुद्धार, धैर्य, आशावादी दृष्टिकोण, सद्गुणों की ओर सहज आकर्षण।'],
                'guidance' => ['en' => 'Trust in your natural rebound resilience; avoid over-idealizing practical situations.', 'hi' => 'अपनी स्वाभाविक धैर्यशीलता पर विश्वास रखें और व्यावहारिक यथार्थ को स्वीकारें।'],
            ],
            'pushya' => [
                'deity' => ['en' => 'Brihaspati (Divine Guru)', 'hi' => 'बृहस्पति (देवगुरु)'],
                'symbol' => ['en' => 'Cow\'s Udder / Lotus Flower', 'hi' => 'कमल पुष्प / पोषण का प्रतीक'],
                'nature' => ['en' => 'Nurturing wisdom, ethical dependability, spiritual inclination, generosity.', 'hi' => 'पोषणकारी विवेक, नैतिक निष्ठा, आध्यात्मिक समझ, उदारता।'],
                'guidance' => ['en' => 'Remember to care for yourself with the same devotion you extend to others.', 'hi' => 'दूसरों की सेवा और सहयोग के साथ-साथ अपने स्वयं के स्वास्थ्य का भी ध्यान रखें।'],
            ],
            'ashlesha' => [
                'deity' => ['en' => 'Sarpas (Nagas / Serpent Energies)', 'hi' => 'नाग (गूढ़ कुंडलिनी शक्ति)'],
                'symbol' => ['en' => 'Coiled Serpent', 'hi' => 'कुंडली मारे सर्प'],
                'nature' => ['en' => 'Deep psychological insight, strategic acumen, protective intuition.', 'hi' => 'मनोवैज्ञानिक गहराई, रणनीतिक सूझबूझ, सूक्ष्म अंतर्दृष्टि।'],
                'guidance' => ['en' => 'Direct strategic brilliance towards ethical service; practice unconditional forgiveness.', 'hi' => 'अपनी बौद्धिक चातुर्य का उपयोग सदाचार में करें और क्षमाशीलता का अभ्यास करें।'],
            ],
            'magha' => [
                'deity' => ['en' => 'Pitris (Ancestral Guardians)', 'hi' => 'पितृगण (पूर्वज संरक्षक)'],
                'symbol' => ['en' => 'Royal Palanquin / Throne', 'hi' => 'राज-सिंहासन / पालकी'],
                'nature' => ['en' => 'Noble dignity, deep respect for lineage, leadership poise, traditional values.', 'hi' => 'स्वाभिमान, कुल-परंपरा के प्रति आदर, स्वाभाविक नेतृत्व, उदार व्यक्तित्व।'],
                'guidance' => ['en' => 'Combine natural authority with humility; honor ancestors through charitable deeds.', 'hi' => 'अपने आत्म-सम्मान को विनम्रता से सुशोभित करें और पूर्वजों के प्रति कृतज्ञ रहें।'],
            ],
            'purvaphalguni' => [
                'deity' => ['en' => 'Bhaga (God of Prosperity & Fortune)', 'hi' => 'भग (सौभाग्य और समृद्धि के देवता)'],
                'symbol' => ['en' => 'Front Legs of Hammock / Bed', 'hi' => 'झूला / शय्या'],
                'nature' => ['en' => 'Social warmth, artistic appreciation, relaxed charm, affectionate loyalty.', 'hi' => 'सामाजिक सौहार्द, कला-प्रेम, मधुर व्यवहार, आत्मीय निष्ठा।'],
                'guidance' => ['en' => 'Balance leisure and celebrations with systematic dedication to professional duties.', 'hi' => 'आनंद और उत्सव के साथ-साथ अपने कर्तव्यों के प्रति सजग और अनुशासित रहें।'],
            ],
            'uttaraphalguni' => [
                'deity' => ['en' => 'Aryaman (God of Contracts & Friendship)', 'hi' => 'अर्यमा (सत्य, मित्रता और अनुबंध के देवता)'],
                'symbol' => ['en' => 'Back Legs of Bed', 'hi' => 'मंच / सुदृढ़ आसन'],
                'nature' => ['en' => 'Honorable reliability, enduring friendship, patronage, ethical consistency.', 'hi' => 'वचनबद्धता, सुदृढ़ मित्रता, संरक्षण देने की प्रवृत्ति, सत्यनिष्ठा।'],
                'guidance' => ['en' => 'Avoid taking on burdensome commitments out of reluctance to decline requests.', 'hi' => 'अपनी क्षमता से अधिक दायित्व लेने से बचें और संतुलन बनाए रखें।'],
            ],
            'hasta' => [
                'deity' => ['en' => 'Savitr (The Radiant Sun of Inspiration)', 'hi' => 'सविता (ज्ञान और प्रकाश के सूर्य)'],
                'symbol' => ['en' => 'Open Hand', 'hi' => 'खुली हथेली (शिल्प और आशीष)'],
                'nature' => ['en' => 'Craftsmanship, clever resourcefulness, detail orientation, healing hands.', 'hi' => 'हस्त-कौशल, सूझबूझ, सूक्ष्म विवरणों पर पकड़, सेवा भाव।'],
                'guidance' => ['en' => 'Prevent over-analyzing minor flaws; cultivate contentment with work accomplished.', 'hi' => 'छोटी-छोटी बातों में अत्यधिक उलझने से बचें और कार्य में संतोष का अनुभव करें।'],
            ],
            'chitra' => [
                'deity' => ['en' => 'Tvashtar / Vishwakarma (Cosmic Architect)', 'hi' => 'विश्वकर्मा (सृष्टि के दिव्य शिल्पी)'],
                'symbol' => ['en' => 'Bright Jewel / Pearl', 'hi' => 'चमकीला रत्न / मोती'],
                'nature' => ['en' => 'Architectural brilliance, aesthetic precision, dynamic creativity, eye for design.', 'hi' => 'रचनात्मक कौशल, सौन्दर्यबोध, निर्माण कला, विशिष्ट कार्यशैली।'],
                'guidance' => ['en' => 'Look beyond superficial external appearances into lasting inner character.', 'hi' => 'बाहरी चमक-दमक से परे व्यक्ति और वस्तु के आंतरिक गुणों को महत्व दें।'],
            ],
            'swati' => [
                'deity' => ['en' => 'Vayu (The Wind God)', 'hi' => 'वायु (स्वतंत्र गति के देवता)'],
                'symbol' => ['en' => 'Young Shoot Bending in the Wind', 'hi' => 'पवन में झुकती नन्हीं कोपल'],
                'nature' => ['en' => 'Adaptability, independence, commercial acumen, gentle flexibility, communicative grace.', 'hi' => 'लचीलापन, स्वतंत्र विचार, व्यापारिक सूझबूझ, मधुर संवाद।'],
                'guidance' => ['en' => 'Anchor restless ideas into stable soil through patient, consistent execution.', 'hi' => 'अपने विविध विचारों को एक निश्चित दिशा में धैर्यपूर्वक कार्यान्वित करें।'],
            ],
            'vishakha' => [
                'deity' => ['en' => 'Indra & Agni (Triumphant Light)', 'hi' => 'इन्द्राग्नि (सामर्थ्य और संकल्प की ऊर्जा)'],
                'symbol' => ['en' => 'Triumphal Arch / Potter\'s Wheel', 'hi' => 'विजय तोरण / कुम्हार का चक्र'],
                'nature' => ['en' => 'Single-minded goal focus, fierce determination, milestone achievement, ambition.', 'hi' => 'अडिग संकल्प, लक्ष्य के प्रति एकाग्रता, निरंतर प्रयास, विजय की अभिलाषा।'],
                'guidance' => ['en' => 'Celebrate milestones peacefully without immediately exhausting yourself on the next conquest.', 'hi' => 'सफलता प्राप्त होने पर संतोष का अनुभव करें और निरंतर दौड़ में शांति न खोएं।'],
            ],
            'anuradha' => [
                'deity' => ['en' => 'Mitra (God of Compassionate Friendship)', 'hi' => 'मित्र (सद्भाव और प्रेम के देवता)'],
                'symbol' => ['en' => 'Triumphal Lotus', 'hi' => 'कमल पुष्प'],
                'nature' => ['en' => 'Devotion, bridging divides, diplomatic grace, flourishing amidst hardships.', 'hi' => 'निस्वार्थ प्रेम, सामंजस्य, संगठन कौशल, विषम परिस्थितियों में खिलने की शक्ति।'],
                'guidance' => ['en' => 'Hold steadfast to high spiritual ideals when navigating complicated social dynamics.', 'hi' => 'सामाजिक जटिलताओं के बीच अपने आत्मिक मूल्यों और सिद्धांतों पर अडिग रहें।'],
            ],
            'jyeshtha' => [
                'deity' => ['en' => 'Indra (King of the Gods)', 'hi' => 'इन्द्र (देवराज, विजय और सामर्थ्य के प्रतीक)'],
                'symbol' => ['en' => 'Circular Amulet / Umbrella', 'hi' => 'कुंडल / छत्र (सुरक्षा का प्रतीक)'],
                'nature' => ['en' => 'Seniority, protective vigilance, courage under pressure, leadership dignity.', 'hi' => 'वरिष्ठता, संरक्षक दृष्टि, संकट में साहस, गरिमामय नेतृत्व।'],
                'guidance' => ['en' => 'Share burdens openly; avoid internalizing excessive solitary responsibility.', 'hi' => 'सभी उत्तरदायित्व अकेले उठाने के बजाय सहयोगियों पर भी विश्वास रखें।'],
            ],
            'mula' => [
                'deity' => ['en' => 'Nirriti (Goddess of Dissolution & Roots)', 'hi' => 'निरृति (मूल सत्य और जड़ की देवी)'],
                'symbol' => ['en' => 'Tied Bunch of Roots', 'hi' => 'जड़ों का गुच्छा'],
                'nature' => ['en' => 'Investigative depth, radical truth-seeking, uprooting illusions, philosophical courage.', 'hi' => 'जड़ तक पहुंचने की प्रवृत्ति, भ्रम को मिटाने का साहस, दार्शनिक गहराई।'],
                'guidance' => ['en' => 'Pair radical honesty with gentleness; rebuild constructively after clearing the old.', 'hi' => 'अपनी स्पष्टता में संवेदनशीलता रखें और पुराने के हटने पर नवनिर्माण पर ध्यान दें।'],
            ],
            'purvashadha' => [
                'deity' => ['en' => 'Apas (The Sacred Cosmic Waters)', 'hi' => 'आपः (पवित्र जल की देवी)'],
                'symbol' => ['en' => 'Winnowing Fan', 'hi' => 'सूप (अन्न छानने का पंखा)'],
                'nature' => ['en' => 'Invincible optimism, purifying speech, emotional eloquence, faith in victory.', 'hi' => 'अपराजेय उत्साह, ओजस्वी वाणी, प्रेरणादायक दृष्टिकोण, विजय में विश्वास।'],
                'guidance' => ['en' => 'Test optimistic assumptions against ground realities before committing large resources.', 'hi' => 'आशावादी योजना बनाने के साथ-साथ व्यावहारिक पहलुओं का भी परीक्षण करें।'],
            ],
            'uttarashadha' => [
                'deity' => ['en' => 'Vishvedevas (The Universal Cosmic Virtues)', 'hi' => 'विश्वेदेवाः (सार्वभौमिक धर्म और गुण)'],
                'symbol' => ['en' => 'Elephant\'s Tusk', 'hi' => 'गज-दंत (सुदृढ़ता और विजय)'],
                'nature' => ['en' => 'Unshakeable righteousness, steady dedication, collective leadership, permanent achievement.', 'hi' => 'अविचल धर्मपरायणता, शांत निष्ठा, दीर्घकालिक सफलता, आदरणीय व्यक्तित्व।'],
                'guidance' => ['en' => 'Balance duty to society with necessary time for personal family joy.', 'hi' => 'सामाजिक दायित्वों के साथ-साथ पारिवारिक और व्यक्तिगत सुख के लिए भी समय निकालें।'],
            ],
            'shravana' => [
                'deity' => ['en' => 'Vishnu (The Preserver)', 'hi' => 'भगवान विष्णु (जगत के पालनकर्ता)'],
                'symbol' => ['en' => 'Three Footprints / Ear', 'hi' => 'कर्ण (कान) / तीन पदचिह्न'],
                'nature' => ['en' => 'Attentive listening, scholastic absorption, wisdom transmission, lifelong learning.', 'hi' => 'श्रवण शक्ति, ज्ञानार्जन, बौद्धिक समझ, परंपरा का सम्मान।'],
                'guidance' => ['en' => 'Filter incoming information wisely; protect mental serenity from unnecessary gossip.', 'hi' => 'केवल सार्थक बातों को ही ग्रहण करें और व्यर्थ की चर्चाओं से मन को दूर रखें।'],
            ],
            'dhanishta' => [
                'deity' => ['en' => 'Ashta Vasus (The Eight Elemental Gods)', 'hi' => 'अष्ट वसु (प्रकृति की आठ दिव्य शक्तियां)'],
                'symbol' => ['en' => 'Musical Drum (Mridanga / Flute)', 'hi' => 'मृदंग / बाँसुरी'],
                'nature' => ['en' => 'Rhythmic timing, material prosperity, musical resonance, organizational leadership.', 'hi' => 'समयबद्धता, समृद्धि, संगीत व कला में रुचि, संगठन क्षमता।'],
                'guidance' => ['en' => 'Use affluence generously in service of dharma; cultivate inner silence.', 'hi' => 'अपनी समृद्धि का उपयोग सत्कार्यों में करें और आंतरिक शांति को प्राथमिकता दें।'],
            ],
            'shatabhisha' => [
                'deity' => ['en' => 'Varuna (Guardian of Cosmic Law & Oceans)', 'hi' => 'वरुण (ब्रह्मांडीय व्यवस्था और जल के देवता)'],
                'symbol' => ['en' => 'Empty Circle / Hundred Physicians', 'hi' => 'शून्य वृत्त / सौ चिकित्सक'],
                'nature' => ['en' => 'Solitary contemplation, esoteric healing insight, unveiling hidden patterns.', 'hi' => 'एकांतप्रियता, गूढ़ ज्ञान, प्राकृतिक उपचार समझ, रहस्यभेदी दृष्टि।'],
                'guidance' => ['en' => 'Open your heart to trusted confidants to balance solitary introversion.', 'hi' => 'अत्यधिक एकांत से बचें और विश्वासपात्र मित्रों से अपने विचार साझा करें।'],
            ],
            'purvabhadrapada' => [
                'deity' => ['en' => 'Aja Ekapada (The Cosmic Fire Serpent)', 'hi' => 'अज एकपाद (तप और साधना के रुद्र स्वरूप)'],
                'symbol' => ['en' => 'Front of Funeral Cot / Two-Faced Man', 'hi' => 'तपस्या का आसन / दो मुखी स्वरूप'],
                'nature' => ['en' => 'Transformative idealism, intense ascetic discipline, passion for philosophical reform.', 'hi' => 'तपस्वी स्वभाव, गहन वैचारिक निष्ठा, आदर्शवादी दृष्टि, एकाग्रता।'],
                'guidance' => ['en' => 'Practice moderation in speech and asceticism; maintain patience with human fallibility.', 'hi' => 'अतिवादी विचारों से बचें और व्यावहारिक जीवन में सहजता व संतुलन बनाए रखें।'],
            ],
            'uttarabhadrapada' => [
                'deity' => ['en' => 'Ahir Budhnya (Serpent of the Ocean Depths)', 'hi' => 'अहिर्बुध्न्य (गहरे सागर के ज्ञानी देवता)'],
                'symbol' => ['en' => 'Back of Funeral Cot / Snake in Water', 'hi' => 'गहरे सागर की लहरें'],
                'nature' => ['en' => 'Serene wisdom, deep meditation, emotional restraint, benevolence, spiritual shelter.', 'hi' => 'गंभीर विवेक, मानसिक स्थिरता, संयम, दयालुता, आध्यात्मिक आश्रय।'],
                'guidance' => ['en' => 'Keep active engagement with practical worldly duties while resting in inner tranquility.', 'hi' => 'आंतरिक शांति का आनंद लेते हुए सांसारिक कर्तव्यों का निष्ठापूर्वक पालन करें।'],
            ],
            'revati' => [
                'deity' => ['en' => 'Pushan (The Cosmic Guide & Nourisher)', 'hi' => 'पूषा (मार्गदर्शक और पोषणकारी देवता)'],
                'symbol' => ['en' => 'Fish Swimming in Waters / Drum', 'hi' => 'जल में तैरती मछली'],
                'nature' => ['en' => 'Compassionate care, journey guardianship, boundless empathy, artistic gentleness.', 'hi' => 'अपार करुणा, राह दिखाने की प्रवृत्ति, संवेदनशील मन, कलात्मक सौम्यता।'],
                'guidance' => ['en' => 'Establish firm emotional boundaries to avoid carrying other people\'s anxieties.', 'hi' => 'दूसरों की चिंताएं अपने ऊपर न ओढ़ें; स्पष्ट और स्वस्थ मानसिक सीमाएं बनाएं।'],
            ],
        ];

        $fallback = [
            'deity' => ['en' => 'Vedic Presiding Divinity', 'hi' => 'वैदिक अधिष्ठाता देव'],
            'symbol' => ['en' => 'Sacred Celestial Emblem', 'hi' => 'पवित्र खगोलीय प्रतीक'],
            'nature' => ['en' => 'Harmonious Vedic celestial influence supporting spiritual awareness.', 'hi' => 'वैदिक खगोलीय प्रभाव जो आत्म-चिंतन और सकारात्मक कर्म में सहायक है।'],
            'guidance' => ['en' => 'Cultivate steady mindfulness, moral integrity, and purposeful work.', 'hi' => 'सदाचार, धैर्य और विवेकपूर्ण कर्म के साथ जीवन में आगे बढ़ें।'],
        ];

        $item = $data[$normalized] ?? $fallback;

        return [
            'deity' => $item['deity'][$locale],
            'symbol' => $item['symbol'][$locale],
            'nature' => $item['nature'][$locale],
            'guidance' => $item['guidance'][$locale],
            'pada_note' => $locale === 'hi'
                ? "चरण {$pada}: यह चरण आपकी मूल मानसिक प्रकृति और जीवन लक्ष्यों को विशेष दिशा प्रदान करता है।"
                : "Pada {$pada}: Refines the foundational mental temperament and personal focus.",
        ];
    }

    /**
     * Interpret Parashari classical yogas with structural formation and expression notes.
     */
    public function getYogaInterpretation(string $yogaKey, array $details = [], string $locale = 'en'): array
    {
        $normalized = strtolower(str_replace([' ', '-', '_'], '', $yogaKey));

        $catalog = [
            'ruchaka' => [
                'name' => ['en' => 'Ruchaka Mahapurusha Yoga', 'hi' => 'रुचक महापुरुष योग'],
                'definition' => [
                    'en' => 'Mars situated in a Kendra house (1st, 4th, 7th, or 10th) in its own sign (Aries/Scorpio) or exaltation sign (Capricorn).',
                    'hi' => 'मंगल ग्रह केंद्र भाव (1, 4, 7 या 10) में अपनी स्वराशि (मेष/वृश्चिक) अथवा उच्च राशि (मकर) में स्थित हो।',
                ],
                'qualities' => [
                    'en' => 'Endows exceptional courage, administrative capability, physical vitality, technical acumen, and triumphant protective drive.',
                    'hi' => 'साहस, नेतृत्व क्षमता, शारीरिक ओज, तकनीकी कुशलता और चुनौतियों पर विजय पाने की अद्भुत शक्ति प्रदान करता है।',
                ],
                'ethical_advice' => [
                    'en' => 'Harness fiery martial drive strictly for righteous protection, discipline, and noble service rather than aggressive dominance.',
                    'hi' => 'अपने शौर्य और प्रभाव का उपयोग कमजोरों की रक्षा और अनुशासित निर्माण में करें, क्रोध व अहंकार से बचें।',
                ],
            ],
            'bhadra' => [
                'name' => ['en' => 'Bhadra Mahapurusha Yoga', 'hi' => 'भद्र महापुरुष योग'],
                'definition' => [
                    'en' => 'Mercury placed in a Kendra house in its own sign (Gemini) or exaltation sign (Virgo).',
                    'hi' => 'बुध ग्रह केंद्र भाव में अपनी स्वराशि (मिथुन) अथवा उच्च राशि (कन्या) में स्थित हो।',
                ],
                'qualities' => [
                    'en' => 'Confers scholarly brilliance, sharp analytical intellect, eloquent communication, commercial acumen, and diplomatic tact.',
                    'hi' => 'तीक्ष्ण बुद्धि, प्रभावी वाकपटुता, व्यापारिक समझ, वैज्ञानिक दृष्टिकोण और कूटनीतिक कुशलता देता है।',
                ],
                'ethical_advice' => [
                    'en' => 'Apply sharp intelligence with compassionate ethics; avoid intellectual cynicism or manipulative wordplay.',
                    'hi' => 'अपनी बौद्धिक योग्यता का उपयोग जनकल्याण में करें और वाणी में सत्य के साथ मधुरता बनाए रखें।',
                ],
            ],
            'hamsa' => [
                'name' => ['en' => 'Hamsa Mahapurusha Yoga', 'hi' => 'हंस महापुरुष योग'],
                'definition' => [
                    'en' => 'Jupiter positioned in a Kendra house in its own sign (Sagittarius/Pisces) or exaltation sign (Cancer).',
                    'hi' => 'बृहस्पति केंद्र भाव में अपनी स्वराशि (धनु/मीन) अथवा उच्च राशि (कर्क) में स्थित हो।',
                ],
                'qualities' => [
                    'en' => 'Blesses with deep spiritual wisdom, philosophical nobility, respected counsel, moral integrity, and benevolent guidance.',
                    'hi' => 'उच्च आध्यात्मिक ज्ञान, दार्शनिक गरिमा, समाज में प्रतिष्ठा, धर्मपरायणता और मार्गदर्शक क्षमता प्रदान करता है।',
                ],
                'ethical_advice' => [
                    'en' => 'Remain grounded in humble service; share knowledge freely without self-righteous dogmatism.',
                    'hi' => 'विद्या और ज्ञान से विनम्रता का विकास करें, दूसरों को सदा सकारात्मक व निष्पक्ष परामर्श दें।',
                ],
            ],
            'malavya' => [
                'name' => ['en' => 'Malavya Mahapurusha Yoga', 'hi' => 'मालव्य महापुरुष योग'],
                'definition' => [
                    'en' => 'Venus located in a Kendra house in its own sign (Taurus/Libra) or exaltation sign (Pisces).',
                    'hi' => 'शुक्र ग्रह केंद्र भाव में अपनी स्वराशि (वृषभ/तुला) अथवा उच्च राशि (मीन) में स्थित हो।',
                ],
                'qualities' => [
                    'en' => 'Confers refined aesthetic sensibilities, artistic genius, material grace, marital contentment, and magnetic charm.',
                    'hi' => 'सौंदर्य दृष्टि, कला-प्रेम, सुरुचिपूर्ण जीवन, सुख-सुविधाएं और सौम्य व आकर्षक व्यक्तित्व प्रदान करता है।',
                ],
                'ethical_advice' => [
                    'en' => 'Elevate sensual appreciation into refined artistic and charitable contributions rather than indulgent vanity.',
                    'hi' => 'सुख-साधनों के बीच सात्विकता बनाए रखें और कला व सौहार्द का विस्तार समाज में करें।',
                ],
            ],
            'sasa' => [
                'name' => ['en' => 'Sasa Mahapurusha Yoga', 'hi' => 'शश महापुरुष योग'],
                'definition' => [
                    'en' => 'Saturn positioned in a Kendra house in its own sign (Capricorn/Aquarius) or exaltation sign (Libra).',
                    'hi' => 'शनि ग्रह केंद्र भाव में अपनी स्वराशि (मकर/कुंभ) अथवा उच्च राशि (तुला) में स्थित हो।',
                ],
                'qualities' => [
                    'en' => 'Bestows profound endurance, organizational mastery, democratic authority, unwavering perseverance, and enduring legacy.',
                    'hi' => 'कठिन परिश्रम की सामर्थ्य, संगठनात्मक दक्षता, धैर्य, जन-समर्थन और स्थायी प्रतिष्ठा प्रदान करता है।',
                ],
                'ethical_advice' => [
                    'en' => 'Lead through selfless fairness and compassion for the working class; shun cold detachment.',
                    'hi' => 'न्यायप्रियता और सेवा भाव से नेतृत्व करें; जरूरतमंदों की सहायता को अपना कर्तव्य समझें।',
                ],
            ],
            'gajakesari' => [
                'name' => ['en' => 'Gaja Kesari Yoga', 'hi' => 'गजकेसरी योग'],
                'definition' => [
                    'en' => 'Jupiter located in a Kendra house (1st, 4th, 7th, or 10th) from the natal Moon.',
                    'hi' => 'बृहस्पति चंद्र से केंद्र भाव (1, 4, 7 या 10) में स्थित हो।',
                ],
                'qualities' => [
                    'en' => 'Inspires mental nobility, royal resilience like a lion (Kesari) atop wisdom (Gaja), enduring reputation, and moral strength.',
                    'hi' => 'मानसिक गरिमा, सिंह के समान अडिग धैर्य, समाज में सम्मान, दीर्घायु यश और विषम परिस्थितियों में भी विवेक बनाए रखने की शक्ति।',
                ],
                'ethical_advice' => [
                    'en' => 'Utilize prominent public standing for philanthropic upliftment and educational patronage.',
                    'hi' => 'अपने मान-सम्मान का उपयोग समाज की भलाई, शिक्षा के प्रसार और कमजोरों के उत्थान में करें।',
                ],
            ],
            'budhaditya' => [
                'name' => ['en' => 'Budhaditya Yoga', 'hi' => 'बुधादित्य योग'],
                'definition' => [
                    'en' => 'Sun and Mercury conjunct in the same zodiac sign without severe combustion impairment.',
                    'hi' => 'सूर्य और बुध की एक ही राशि में युति हो (जहाँ बुध अति दग्ध न हो)।',
                ],
                'qualities' => [
                    'en' => 'Fosters sharp mental clarity, administrative eloquence, quick mathematical learning, and articulate communication.',
                    'hi' => 'तेजस्वी बुद्धि, प्रशासनिक समझ, कुशल संवाद और तार्किक चिंतन का विकास होता है।',
                ],
                'ethical_advice' => [
                    'en' => 'Harness intellectual brilliance with patience; avoid speaking condescendingly to those of slower pace.',
                    'hi' => 'अपनी बौद्धिक गति के साथ दूसरों के प्रति धैर्य रखें और ज्ञान का प्रदर्शन अहंकार से मुक्त रखें।',
                ],
            ],
        ];

        $entry = $catalog[$normalized] ?? [
            'name' => ['en' => ucwords($yogaKey) . ' Yoga', 'hi' => $yogaKey . ' योग'],
            'definition' => ['en' => 'Classical planetary configuration.', 'hi' => 'शास्त्रीय ग्रहीय स्थिति।'],
            'qualities' => ['en' => 'Brings purposeful planetary harmony.', 'hi' => 'सकारात्मक फल प्रदायक स्थिति।'],
            'ethical_advice' => ['en' => 'Act with mindfulness and virtue.', 'hi' => 'सदाचार और विवेक से कार्य करें।'],
        ];

        return [
            'name' => $entry['name'][$locale],
            'definition' => $entry['definition'][$locale],
            'qualities' => $entry['qualities'][$locale],
            'ethical_advice' => $entry['ethical_advice'][$locale],
        ];
    }

    /**
     * Interpret Manglik Dosha, verified Bhangas, and behavioral mitigations.
     */
    public function getManglikInterpretation(array $manglikData, string $locale = 'en'): array
    {
        $hasDosha = !empty($manglikData['has_manglik_dosha']);
        $bhangas = $manglikData['bhangas'] ?? [];
        $mitigations = $manglikData['mitigations'] ?? [];
        $isCancelled = !empty($manglikData['is_cancelled']);

        if (!$hasDosha) {
            return [
                'headline' => $locale === 'hi'
                    ? 'मांगलिक दोष अनुपस्थित'
                    : 'Manglik Dosha Absent',
                'summary' => $locale === 'hi'
                    ? 'आपकी पत्रिका में लग्न एवं चंद्र दोनों से मंगल 1, 4, 7, 8, अथवा 12वें भाव में स्थित नहीं है। आपकी कुंडली मंगल दोष से सर्वथा मुक्त है।'
                    : 'Mars does not occupy any of the classical sensitive houses (1st, 4th, 7th, 8th, or 12th) from either the Ascendant or the natal Moon. Your chart is free from Kuja Dosha.',
                'cancellation_note' => null,
                'ethical_guidance' => $locale === 'hi'
                    ? 'पारस्परिक समझ, संवेदनशीलता और पारदर्शी संवाद वैवाहिक जीवन के मूल आधार हैं।'
                    : 'Mutual respect, patience, and transparent communication remain the true foundations of harmonious relationships.',
            ];
        }

        if ($isCancelled) {
            $bhangaNames = array_map(function ($b) use ($locale) {
                return $locale === 'hi' ? ($b['name_hi'] ?? $b['rule']) : ($b['rule'] ?? 'Classical Bhanga');
            }, $bhangas);

            return [
                'headline' => $locale === 'hi'
                    ? 'मांगलिक दोष भंग (शास्त्रीय अपवाद प्राप्त)'
                    : 'Manglik Dosha Mitigated / Cancelled by Classical Rule',
                'summary' => $locale === 'hi'
                    ? 'कुंडली में मंगल संवेदनशील भाव में स्थित होने के उपरांत भी प्रमाणिक शास्त्रीय भंग नियमों के अनुसार यह दोष निष्प्रभावी हो चुका है (' . implode(', ', $bhangaNames) . ')।'
                    : 'Although Mars occupies a classical sensitive house, verified Parashari exceptions cancel or nullify the detrimental impact (' . implode(', ', $bhangaNames) . ').',
                'cancellation_note' => $locale === 'hi'
                    ? 'नोट: शास्त्रीय सिद्धांतों के अनुसार कर्क राशि में मंगल की नीच स्थिति मांगलिक दोष का भंग नहीं मानी जाती है; केवल विशिष्ट शुभ दृष्टि, केंद्रस्थ बृहस्पति या स्व-उच्च भाव स्थिति ही वास्तविक भंग करती है।'
                    : 'Classical Clarification: Mars in Cancer (debilitation) is explicitly NOT a valid cancellation. Cancellation here is certified by verified functional benefic Jupiter lordship or specific classical positional strength.',
                'ethical_guidance' => $locale === 'hi'
                    ? 'शास्त्रीय भंग होने पर भी दांपत्य में धैर्य, सौम्यता और परस्पर विश्वास को सदैव प्राथमिकता दें।'
                    : 'Even with classical cancellations, nurturing emotional maturity and listening empathetically ensures marital bliss.',
            ];
        }

        // Active Manglik with or without behavioral mitigations
        $mitigationTexts = [];
        foreach ($mitigations as $m) {
            $mitigationTexts[] = $locale === 'hi' ? ($m['note_hi'] ?? $m['note']) : ($m['note'] ?? 'Mitigation');
        }

        return [
            'headline' => $locale === 'hi'
                ? 'मांगलिक प्रभाव (सचेत संतुलन आवश्यक)'
                : 'Manglik Influence Present (Mindful Harmony Advised)',
            'summary' => $locale === 'hi'
                ? 'मंगल ग्रह लग्न अथवा चंद्र से संवेदनशील भाव में स्थित है, जो स्वभाव में अत्यधिक ऊर्जा, स्पष्टवादिता और शीघ्र प्रतिक्रिया की संभावना दर्शाता है।'
                : 'Mars occupies a sensitive house from the Lagna or Moon, indicating intense drive, high expectations, candid speech, and high vitality in personal relationships.',
            'cancellation_note' => !empty($mitigationTexts)
                ? implode(' ', $mitigationTexts)
                : null,
            'ethical_guidance' => $locale === 'hi'
                ? 'मांगलिक दोष कोई अभिशाप नहीं बल्कि अतिरिक्त ऊर्जा का प्रतीक है। क्रोध पर नियंत्रण, वाणी में मधुरता और जीवनसाथी के दृष्टिकोण का सम्मान दांपत्य को अत्यंत सुखी बनाता है।'
                : 'Kuja Dosha is not a curse, but a reservoir of intense dynamic energy. When directed with self-awareness, active listening, and calm emotional restraint, it fosters a deeply devoted, vibrant partnership.',
        ];
    }

    /**
     * Interpret Saturn Sade Sati and Dhaiya transit phases.
     */
    public function getSaturnTransitInterpretation(string $phaseKey, bool $isRetrograde = false, string $locale = 'en'): array
    {
        $normalized = strtolower(str_replace([' ', '-', '_'], '', $phaseKey));

        $phases = [
            'rising' => [
                'title' => ['en' => 'Sade Sati — Rising Phase (12th from Moon)', 'hi' => 'साढ़ेसाती — प्रथम चरण (चंद्र से द्वादश भाव)'],
                'core' => [
                    'en' => 'Saturn transits the house immediately preceding the natal Moon. This period invites deep reflection, financial prudence, streamlining unnecessary expenses, and shedding outmoded patterns.',
                    'hi' => 'शनि जन्म चंद्र से 12वें भाव में गोचर कर रहे हैं। यह चरण व्यय में संयम, अनावश्यक चिंताओं से मुक्ति, और जीवन की प्राथमिकताओं को पुनः व्यवस्थित करने का काल है।',
                ],
                'advice' => [
                    'en' => 'Practice disciplined budgeting, maintain regular sleep habits, and seek quiet contemplative moments.',
                    'hi' => 'वित्तीय मामलों में सावधानी बरतें, नियमित दिनचर्या रखें और मन को शांत रखने हेतु ध्यान का अभ्यास करें।',
                ],
            ],
            'peak' => [
                'title' => ['en' => 'Sade Sati — Peak Phase (Transiting Over Natal Moon)', 'hi' => 'साढ़ेसाती — द्वितीय चरण (जन्म चंद्र पर गोचर - शिखर)'],
                'core' => [
                    'en' => 'Saturn transits directly over the natal Moon sign. This is the central crucible of emotional maturing, calling for inner strength, professional discipline, and dismantling of illusions.',
                    'hi' => 'शनि जन्म चंद्र राशि पर ही गोचर कर रहे हैं। यह मानसिक परिपक्वता, धैर्य और वास्तविक आत्म-चिंतन का महत्वपूर्ण काल है।',
                ],
                'advice' => [
                    'en' => 'Accept responsibilities with calm patience; avoid impulsive life upheavals; root your confidence in genuine work rather than praise.',
                    'hi' => 'दायित्वों को धैर्य से स्वीकार करें, भावुकता में आकर बड़े निर्णय न लें, और अपने कर्म को निष्ठापूर्वक करते रहें।',
                ],
            ],
            'setting' => [
                'title' => ['en' => 'Sade Sati — Setting Phase (2nd from Moon)', 'hi' => 'साढ़ेसाती — तृतीय चरण (चंद्र से द्वितीय भाव - उतार)'],
                'core' => [
                    'en' => 'Saturn transits the sign following the natal Moon. Focus shifts to financial consolidation, family harmony, careful speech, and rebuilding foundational stability.',
                    'hi' => 'शनि जन्म चंद्र से दूसरे भाव में गोचर कर रहे हैं। यह चरण संचित धन के संरक्षण, वाणी में संयम और पारिवारिक संतुलन स्थापित करने का अवसर है।',
                ],
                'advice' => [
                    'en' => 'Speak with measured kindness; make conservative financial investments; harvest the lessons learned during the peak phase.',
                    'hi' => 'वाणी में मधुरता रखें, सुरक्षित निवेश को प्राथमिकता दें और पूर्व के अनुभवों से सीखकर सुदृढ़ भविष्य बनाएं।',
                ],
            ],
            'kantaka' => [
                'title' => ['en' => 'Kantaka Shani / Small Dhaiya (4th from Moon)', 'hi' => 'कंटक शनि / लघु ढैय्या (चंद्र से चतुर्थ भाव)'],
                'core' => [
                    'en' => 'Saturn transits the 4th house from the natal Moon. This prompts careful attention to domestic affairs, maternal health, emotional tranquility, and work-life boundaries.',
                    'hi' => 'शनि चंद्र से चौथे भाव में गोचर कर रहे हैं। यह पारिवारिक शांति, माता के स्वास्थ्य और कार्य-जीवन में संतुलन बनाए रखने का समय है।',
                ],
                'advice' => [
                    'en' => 'Ensure a harmonious home environment; do not neglect domestic well-being in pursuit of career achievements.',
                    'hi' => 'घर में सौहार्दपूर्ण वातावरण रखें, भागदौड़ के बीच घरेलू सुख और मानसिक शांति को समय दें।',
                ],
            ],
            'ashtama' => [
                'title' => ['en' => 'Ashtama Shani / Major Dhaiya (8th from Moon)', 'hi' => 'अष्टम शनि / बड़ी ढैय्या (चंद्र से अष्टम भाव)'],
                'core' => [
                    'en' => 'Saturn transits the 8th house from the natal Moon. This is an intense period of psychological transformation, shedding vulnerabilities, managing unforeseen changes, and cultivating resilience.',
                    'hi' => 'शनि चंद्र से आठवें भाव में गोचर कर रहे हैं। यह आंतरिक परिवर्तन, छिपी चुनौतियों का सामना करने और जीवन में दृढ़ता विकसित करने का काल है।',
                ],
                'advice' => [
                    'en' => 'Prioritize physical safety and health checkups; practice ethical transparency; welcome personal evolution.',
                    'hi' => 'स्वास्थ्य और सुरक्षा का विशेष ध्यान रखें, नियमों का पालन करें और अप्रत्याशित परिवर्तनों को धैर्य से संभालें।',
                ],
            ],
            'none' => [
                'title' => ['en' => 'No Active Sade Sati or Dhaiya', 'hi' => 'वर्तमान में साढ़ेसाती या ढैय्या का प्रभाव नहीं'],
                'core' => [
                    'en' => 'Saturn is currently transiting favorable houses relative to your natal Moon. This provides a supportive window for constructive progress and stability.',
                    'hi' => 'शनि इस समय आपकी चंद्र राशि के सापेक्ष अनुकूल भावों में भ्रमण कर रहे हैं। यह समय प्रगति और स्थिरता के लिए शुभ है।',
                ],
                'advice' => [
                    'en' => 'Use this favorable transit to build long-term foundations, cultivate disciplined habits, and assist those in need.',
                    'hi' => 'इस अनुकूल समय का लाभ उठाकर अपने भविष्य की मजबूत नींव रखें और जनकल्याण में योगदान दें।',
                ],
            ],
        ];

        $entry = $phases[$normalized] ?? $phases['none'];

        $retroNote = '';
        if ($isRetrograde) {
            $retroNote = $locale === 'hi'
                ? ' [वक्री गति: शनि की वक्री चाल आंतरिक आत्म-निरीक्षण और रुके हुए कार्यों को पूरा करने की प्रेरणा देती है।]'
                : ' [Retrograde Motion: Saturn\'s retrograde phase invites deep inward evaluation and revisiting incomplete karmic responsibilities.]';
        }

        return [
            'title' => $entry['title'][$locale] . $retroNote,
            'core' => $entry['core'][$locale],
            'advice' => $entry['advice'][$locale],
        ];
    }

    /**
     * Interpret Vimshottari Mahadasha themes.
     */
    public function getMahadashaTheme(string $planet, string $locale = 'en'): array
    {
        $p = strtolower($planet);

        $themes = [
            'sun' => [
                'name' => ['en' => 'Sun Mahadasha (Surya)', 'hi' => 'सूर्य महादशा (6 वर्ष)'],
                'theme' => [
                    'en' => 'Theme of Vitality & Authority: A 6-year period emphasizing personal sovereignty, professional reputation, relationships with superiors, ethical integrity, and self-confidence.',
                    'hi' => 'आत्म-सम्मान और तेज का काल: 6 वर्ष की यह अवधि यश, आत्म-बल, प्रशासनिक कार्य, पिता से संबंध और नेतृत्व के विकास के लिए महत्वपूर्ण होती है।',
                ],
            ],
            'moon' => [
                'name' => ['en' => 'Moon Mahadasha (Chandra)', 'hi' => 'चन्द्र महादशा (10 वर्ष)'],
                'theme' => [
                    'en' => 'Theme of Emotional Mind & Nurturing: A 10-year cycle highlighting psychological equilibrium, domestic happiness, motherly bonding, creative intuition, and public resonance.',
                    'hi' => 'मानसिक शांति और सौम्यता का काल: 10 वर्ष की यह दशा मन की स्थिति, पारिवारिक सुख, माता के सहयोग, रचनात्मकता और सामाजिक संबंधों को प्रभावित करती है।',
                ],
            ],
            'mars' => [
                'name' => ['en' => 'Mars Mahadasha (Mangala)', 'hi' => 'मंगल महादशा (7 वर्ष)'],
                'theme' => [
                    'en' => 'Theme of Courage & Enterprise: A 7-year phase that activates ambition, competitive drive, physical stamina, technical prowess, property matters, and swift decisive action.',
                    'hi' => 'पराक्रम और ऊर्जा का काल: 7 वर्ष की यह अवधि साहस, महत्वाकांक्षा, भूमि-भवन संबंधी कार्य, तकनीकी क्षमता और त्वरित निर्णयों के लिए सक्रिय रहती है।',
                ],
            ],
            'rahu' => [
                'name' => ['en' => 'Rahu Mahadasha', 'hi' => 'राहु महादशा (18 वर्ष)'],
                'theme' => [
                    'en' => 'Theme of Unconventional Growth & Worldly Expansion: An 18-year period fostering breakthrough ambition, innovative technologies, foreign exposure, and intense worldly desires.',
                    'hi' => 'अभूतपूर्व विस्तार और नवीनता का काल: 18 वर्ष की यह दशा नई तकनीकों, विदेशी संपर्कों, सामाजिक उत्थान और महत्वाकांक्षी योजनाओं को गति देती है।',
                ],
            ],
            'jupiter' => [
                'name' => ['en' => 'Jupiter Mahadasha (Guru)', 'hi' => 'गुरु महादशा (16 वर्ष)'],
                'theme' => [
                    'en' => 'Theme of Wisdom, Dharma & Prosperity: A 16-year golden cycle promoting higher learning, spiritual maturity, children\'s welfare, financial expansion, and benevolent guidance.',
                    'hi' => 'ज्ञान, धर्म और समृद्धि का काल: 16 वर्ष की यह शुभ दशा उच्च शिक्षा, गुरु कृपा, संतान सुख, आर्थिक सुदृढ़ता और आध्यात्मिक उन्नति का मार्ग प्रशस्त करती है।',
                ],
            ],
            'saturn' => [
                'name' => ['en' => 'Saturn Mahadasha (Shani)', 'hi' => 'शनि महादशा (19 वर्ष)'],
                'theme' => [
                    'en' => 'Theme of Discipline, Karma & Enduring Foundation: A 19-year masterclass in patience, structural organization, tireless work ethic, detachment, and enduring achievements.',
                    'hi' => 'कर्म, अनुशासन और स्थायित्व का काल: 19 वर्ष की यह दशा व्यक्ति को धैर्य, कठिन परिश्रम, व्यावहारिक समझ और जीवन की स्थायी नींव स्थापित करना सिखाती है।',
                ],
            ],
            'mercury' => [
                'name' => ['en' => 'Mercury Mahadasha (Budha)', 'hi' => 'बुध महादशा (17 वर्ष)'],
                'theme' => [
                    'en' => 'Theme of Intellect, Commerce & Communication: A 17-year fertile period boosting business acumen, analytical skills, networking, literary talents, and adaptable versatility.',
                    'hi' => 'बुद्धि, व्यापार और संचार का काल: 17 वर्ष की यह अवधि व्यावसायिक उन्नति, तार्किक विश्लेषण, लेखन, मित्रों के सहयोग और बौद्धिक कौशल को बढ़ाती है।',
                ],
            ],
            'ketu' => [
                'name' => ['en' => 'Ketu Mahadasha', 'hi' => 'केतु महादशा (7 वर्ष)'],
                'theme' => [
                    'en' => 'Theme of Spiritual Awakening & Detachment: A 7-year introspective window encouraging spiritual contemplation, shedding superficial attachments, and unveiling esoteric insights.',
                    'hi' => 'अध्यात्म, वैराग्य और आत्म-साक्षात्कार का काल: 7 वर्ष की यह अवधि सांसारिक भ्रमों से मुक्ति, ध्यान, अनुसंधान और गहरे आत्मिक बोध की ओर प्रेरित करती है।',
                ],
            ],
            'venus' => [
                'name' => ['en' => 'Venus Mahadasha (Shukra)', 'hi' => 'शुक्र महादशा (20 वर्ष)'],
                'theme' => [
                    'en' => 'Theme of Grace, Harmony & Fulfillment: A 20-year expansive journey highlighting artistic appreciation, marital harmony, lifestyle comforts, affection, and creative prosperity.',
                    'hi' => 'सौंदर्य, सौहार्द और भौतिक सुख का काल: 20 वर्ष की यह दशा कलात्मक अभिरुचि, वैवाहिक आनंद, सामाजिक प्रतिष्ठा और सुख-समृद्धि में वृद्धि करती है।',
                ],
            ],
        ];

        $entry = $themes[$p] ?? [
            'name' => ['en' => ucfirst($planet) . ' Mahadasha', 'hi' => $planet . ' महादशा'],
            'theme' => ['en' => 'A significant planetary chapter in life.', 'hi' => 'जीवन का एक महत्वपूर्ण ग्रह काल।'],
        ];

        return [
            'name' => $entry['name'][$locale],
            'theme' => $entry['theme'][$locale],
        ];
    }

    /**
     * Get classical Vedic Upay (Remedies) for a planet: Mantras, Dana, Mindfulness.
     */
    public function getVedicRemedies(string $planet, string $locale = 'en'): array
    {
        $p = strtolower($planet);

        $remedies = [
            'sun' => [
                'mantra' => 'ॐ ह्रां ह्रीं ह्रौं सः सूर्याय नमः (Om Hraam Hreem Hroum Sah Suryaya Namah)',
                'dana' => ['en' => 'Donate wheat, jaggery (gur), copper, or red lentils to worthy persons on Sundays.', 'hi' => 'रविवार को गेहूं, गुड़, तांबा या लाल मसूर की दाल का दान करें।'],
                'lifestyle' => ['en' => 'Practice Surya Namaskar at dawn; honor father and father-figures; cultivate transparent truthfulness.', 'hi' => 'प्रातःकाल सूर्य नमस्कार करें; पिता व वरिष्ठजनों का आदर करें; आचरण में सत्यता बनाए रखें।'],
            ],
            'moon' => [
                'mantra' => 'ॐ श्रां श्रीं श्रौं सः चन्द्रमसे नमः (Om Shraam Shreem Shroum Sah Chandramase Namah)',
                'dana' => ['en' => 'Donate rice, milk, silver, white garments, or water to travelers on Mondays.', 'hi' => 'सोमवार को दूध, चावल, चांदी, श्वेत वस्त्र अथवा जल का दान करें।'],
                'lifestyle' => ['en' => 'Practice mindful breathing; honor mother; keep drinking water in pure silver or clean earthenware.', 'hi' => 'प्राणायाम का अभ्यास करें; माता का आशीर्वाद लें; मानसिक शांति हेतु व्यर्थ तनाव से बचें।'],
            ],
            'mars' => [
                'mantra' => 'ॐ क्रां क्रीं क्रौं सः भौमाय नमः (Om Kraam Kreem Kroum Sah Bhaumaya Namah)',
                'dana' => ['en' => 'Donate red lentils (masoor dal), pomegranate, or copper to laborers on Tuesdays.', 'hi' => 'मंगलवार को लाल मसूर दाल, गुड़, अथवा तांबे के बर्तन का दान करें।'],
                'lifestyle' => ['en' => 'Channel energy through regular vigorous exercise; avoid anger; practice chivalry and patience.', 'hi' => 'नियमित व्यायाम द्वारा ऊर्जा का सकारात्मक उपयोग करें; क्रोध पर संयम रखें और छोटे भाइयों का सहयोग करें।'],
            ],
            'mercury' => [
                'mantra' => 'ॐ ब्रां ब्रीं ब्रौं सः बुधाय नमः (Om Braam Breem Broum Sah Budhaya Namah)',
                'dana' => ['en' => 'Donate green moong dal, green vegetables, books, or stationary to students on Wednesdays.', 'hi' => 'बुधवार को साबुत मूंग, हरी सब्जियां, पुस्तकें या शिक्षण सामग्री विद्यार्थियों को भेंट करें।'],
                'lifestyle' => ['en' => 'Cultivate active listening; maintain clean speech; nurture green indoor plants or plant trees.', 'hi' => 'पेड़-पौधों की देखभाल करें; वाणी में सौम्यता रखें और अपनी अध्ययनशीलता को निरंतर बनाए रखें।'],
            ],
            'jupiter' => [
                'mantra' => 'ॐ ग्रां ग्रीं ग्रौं सः गुरवे नमः (Om Graam Greem Groum Sah Gurave Namah)',
                'dana' => ['en' => 'Donate yellow lentils (chana dal), turmeric, yellow fruits, or spiritual texts on Thursdays.', 'hi' => 'गुरुवार को चने की दाल, हल्दी, पीले फल अथवा धार्मिक पुस्तकों का दान करें।'],
                'lifestyle' => ['en' => 'Show deep respect to teachers and elders; practice meditation; read sacred philosophical treatises.', 'hi' => 'शिक्षकों और बड़ों का सम्मान करें; स्वाध्याय करें और जीवन में सात्विक विचारों को प्राथमिकता दें।'],
            ],
            'venus' => [
                'mantra' => 'ॐ द्रां द्रीं द्रौं सः शुक्राय नमः (Om Draam Dreem Droum Sah Shukraya Namah)',
                'dana' => ['en' => 'Donate white rice, sugar, ghee, fragrant white flowers, or milk sweets on Fridays.', 'hi' => 'शुक्रवार को मिश्री, खीर, घी, सफेद वस्त्र अथवा सुगंधित द्रव्यों का दान करें।'],
                'lifestyle' => ['en' => 'Maintain personal hygiene and tidy surroundings; respect women; patronize traditional fine arts.', 'hi' => 'स्वच्छता और सुरुचिपूर्ण वातावरण बनाए रखें; महिलाओं का सम्मान करें और कला-संगीत में मन लगाएं।'],
            ],
            'saturn' => [
                'mantra' => 'ॐ प्रां प्रीं प्रौं सः शनैश्चराय नमः (Om Praam Preem Proum Sah Shanaishcharaya Namah)',
                'dana' => ['en' => 'Donate black sesame seeds (til), mustard oil, iron utensils, or black blankets to the needy on Saturdays.', 'hi' => 'शनिवार को काले तिल, सरसों का तेल, लोहे के बर्तन या कंबल जरूरतमंदों को दान करें।'],
                'lifestyle' => ['en' => 'Treat sanitation workers and laborers with utmost dignity; keep disciplined routines; practice selfless service.', 'hi' => 'श्रमजीवियों और सहायकों का सम्मान करें; समयबद्धता बनाए रखें और निस्वार्थ सेवा को जीवन में स्थान दें।'],
            ],
            'rahu' => [
                'mantra' => 'ॐ भ्रां भ्रीं भ्रौं सः राहवे नमः (Om Bhraam Bhreem Bhroum Sah Rahave Namah)',
                'dana' => ['en' => 'Donate black sesame, coconut, or feed birds and stray animals with clean grains.', 'hi' => 'पक्षियों को दाना डालें, नारियल अथवा काले तिल का दान करें।'],
                'lifestyle' => ['en' => 'Avoid deception or shortcuts; maintain spotless personal living spaces; practice grounded meditation.', 'hi' => 'किसी भी प्रकार के भ्रम अथवा शॉर्टकट से बचें; घर को व्यवस्थित रखें और सात्विक दिनचर्या अपनाएं।'],
            ],
            'ketu' => [
                'mantra' => 'ॐ स्रां स्रीं स्रौं सः केतवे नमः (Om Sraam Sreem Sroum Sah Ketave Namah)',
                'dana' => ['en' => 'Donate brown or multi-colored blankets, sesame seeds, or feed stray dogs compassionately.', 'hi' => 'श्वानों (कुत्तों) को भोजन कराएं, भूरे अथवा दोरंगे कंबल जरूरतमंदों को दान करें।'],
                'lifestyle' => ['en' => 'Practice silent meditation; visit quiet sacred pilgrimages; cultivate inner detachment.', 'hi' => 'नियमित मौन और ध्यान का अभ्यास करें; आत्म-चिंतन करें और अहंकार से दूर रहें।'],
            ],
        ];

        $item = $remedies[$p] ?? $remedies['jupiter'];

        return [
            'mantra' => $item['mantra'],
            'dana' => $item['dana'][$locale],
            'lifestyle' => $item['lifestyle'][$locale],
        ];
    }
}
