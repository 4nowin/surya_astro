<?php

declare(strict_types=1);

namespace App\Services\Astrology\Interpretation;

use JayeshMepani\PanchangCore\Core\Enums\Nakshatra;
use JayeshMepani\PanchangCore\Core\Enums\Rasi;

/**
 * Service providing classical, rule-based interpretations for Janam Kundli charts.
 *
 * Implements M6 of the Astro Setu Product Roadmap.
 * Strictly educational and classical; no fatalistic or AI-hallucinated predictions.
 */
class KundliInterpretationService
{
    /**
     * Standard non-deterministic educational disclaimers.
     */
    public const DISCLAIMER_EN = 'This Vedic birth chart and its interpretations are calculated according to traditional Parashara astrological principles for personal self-reflection and educational purposes only. Astro Setu does not provide fatalistic or deterministic predictions. Decisions should always be guided by rational thinking and practical consideration.';
    public const DISCLAIMER_HI = 'यह वैदिक जन्म कुंडली और इसके विश्लेषण केवल व्यक्तिगत चिंतन और पारंपरिक पराशरी ज्योतिषीय अध्ययन के उद्देश्य से प्रस्तुत किए गए हैं। एस्ट्रो सेतु किसी भी प्रकार की अपरिवर्तनीय या भाग्यवादी भविष्यवाणी नहीं करता। सभी निर्णय अपने विवेक और व्यावहारिक समझ के आधार पर लें।';

    /**
     * Traditional Sanskrit & English significance for the 12 Bhavas (Houses).
     */
    public const HOUSE_SIGNIFICANCES = [
        1 => [
            'name_sa' => 'तनु भाव (Tanu Bhava)',
            'title_en' => 'House of Self & Vitality',
            'title_hi' => 'आत्म एवं व्यक्तित्व भाव',
            'karaka' => 'Sun',
            'significance_en' => 'Represents physical body, personality, vital energy, self-expression, natural constitution, and orientation towards life.',
            'significance_hi' => 'शारीरिक स्वास्थ्य, स्वभाव, जीवन शक्ति, आत्म-चेतना, रूप-रंग और जीवन के प्रति दृष्टिकोण का प्रतिनिधित्व करता है।',
        ],
        2 => [
            'name_sa' => 'धन भाव (Dhana Bhava)',
            'title_en' => 'House of Wealth & Speech',
            'title_hi' => 'धन, वाणी एवं कुटुंब भाव',
            'karaka' => 'Jupiter',
            'significance_en' => 'Represents accumulated assets, financial stability, family heritage, vocal quality, dietary habits, and early childhood values.',
            'significance_hi' => 'संचित धन, आर्थिक स्थिरता, कुटुंब, वाणी, भोजन की आदतें और प्रारंभिक पारिवारिक संस्कारों का कारक है।',
        ],
        3 => [
            'name_sa' => 'सहज / पराक्रम भाव (Sahaja Bhava)',
            'title_en' => 'House of Courage & Siblings',
            'title_hi' => 'पराक्रम एवं भ्राता भाव',
            'karaka' => 'Mars',
            'significance_en' => 'Signifies personal initiative, courage, short journeys, younger siblings, manual dexterity, communications, and creative hobbies.',
            'significance_hi' => 'साहस, उद्यम, छोटे भाई-बहन, छोटी यात्राएं, संवाद शैली और रचनात्मक कौशल का परिचायक है।',
        ],
        4 => [
            'name_sa' => 'सुख / मातृ भाव (Sukha Bhava)',
            'title_en' => 'House of Home, Happiness & Mother',
            'title_hi' => 'सुख, माता एवं गृह भाव',
            'karaka' => 'Moon',
            'significance_en' => 'Governs emotional peace, motherly nurturing, primary education, vehicles, immovable property, and foundational well-being.',
            'significance_hi' => 'मानसिक शांति, मातृ सुख, प्रारंभिक शिक्षा, वाहन, अचल संपत्ति और आंतरिक संतोष का प्रतीक है।',
        ],
        5 => [
            'name_sa' => 'पुत्र / बुद्धि भाव (Putra Bhava)',
            'title_en' => 'House of Intellect, Creativity & Progeny',
            'title_hi' => 'बुद्धि, ज्ञान एवं संतान भाव',
            'karaka' => 'Jupiter',
            'significance_en' => 'Signifies intellect, discernment (buddhi), creative expression, learning, progeny, purva-punya (past virtuous deeds), and contemplation.',
            'significance_hi' => 'बुद्धिमत्ता, विद्या, रचनात्मक प्रतिभा, संतान, पूर्व-पुण्य और आध्यात्मिक चिंतन का भाव है।',
        ],
        6 => [
            'name_sa' => 'रिपु / शत्रु भाव (Ripu Bhava)',
            'title_en' => 'House of Service, Health & Overcoming Obstacles',
            'title_hi' => 'रोग, ऋण, सेवा एवं शत्रु भाव',
            'karaka' => 'Mars, Saturn',
            'significance_en' => 'Associated with daily work discipline, health vigilance, competitive strength, problem-solving capacity, and overcoming hurdles.',
            'significance_hi' => 'दैनिक कार्य, सेवा, स्वास्थ्य सतर्कता, रोग-प्रतिरोधक क्षमता और चुनौतियों पर विजय पाने की सामर्थ्य।',
        ],
        7 => [
            'name_sa' => 'जाया / कलत्र भाव (Jaya Bhava)',
            'title_en' => 'House of Partnership & Relationships',
            'title_hi' => 'दाम्पत्य एवं साझेदारी भाव',
            'karaka' => 'Venus',
            'significance_en' => 'Reflects long-term partnerships, marital harmony, business collaborations, public interactions, and balanced reciprocity.',
            'significance_hi' => 'विवाह, जीवनसाथी, व्यावसायिक साझेदारियां, जनसंपर्क और सामाजिक संतुलन का मुख्य भाव है।',
        ],
        8 => [
            'name_sa' => 'आयु / रन्ध्र भाव (Ayu Bhava)',
            'title_en' => 'House of Longevity & Transformation',
            'title_hi' => 'आयु, गूढ़ विद्या एवं परिवर्तन भाव',
            'karaka' => 'Saturn',
            'significance_en' => 'Governs longevity, deep research, hidden truths, transformative life changes, psychological resilience, and unearned assets.',
            'significance_hi' => 'आयु, गहन शोध, गूढ़ ज्ञान, जीवन के गहरे रूपांतरण, मानसिक सहनशक्ति और पैतृक विरासत।',
        ],
        9 => [
            'name_sa' => 'भाग्य / धर्म भाव (Dharma Bhava)',
            'title_en' => 'House of Dharma, Fortune & Wisdom',
            'title_hi' => 'भाग्य, धर्म एवं उच्च शिक्षा भाव',
            'karaka' => 'Jupiter',
            'significance_en' => 'Signifies philosophical ethics, higher wisdom, spiritual guidance, father, pilgrimage, moral fortune, and alignment with Dharma.',
            'significance_hi' => 'धर्म, उच्च ज्ञान, दार्शनिक सोच, पिता, तीर्थाटन, नैतिक मूल्य और जीवन में सहज मार्गदर्शन।',
        ],
        10 => [
            'name_sa' => 'कर्म भाव (Karma Bhava)',
            'title_en' => 'House of Profession, Honor & Career',
            'title_hi' => 'कर्म, आजीविका एवं मान-सम्मान भाव',
            'karaka' => 'Sun, Mercury, Jupiter, Saturn',
            'significance_en' => 'Highlights profession, public reputation, leadership responsibilities, societal contributions, authority, and life purpose.',
            'significance_hi' => 'कार्यक्षेत्र, प्रतिष्ठा, नेतृत्व, सामाजिक उत्तरदायित्व, अधिकार और आजीविका की दिशा का प्रमुख भाव है।',
        ],
        11 => [
            'name_sa' => 'लाभ / आय भाव (Labha Bhava)',
            'title_en' => 'House of Gains, Aspirations & Social Networks',
            'title_hi' => 'लाभ, आकांक्षाएं एवं मित्र भाव',
            'karaka' => 'Jupiter',
            'significance_en' => 'Represents social networks, elder siblings, long-term aspirations, community collaborations, and tangible fulfillment of goals.',
            'significance_hi' => 'आर्थिक लाभ, दीर्घकालिक लक्ष्य, मित्र मंडली, बड़े भाई-बहन और सामूहिक प्रयासों से सफलता।',
        ],
        12 => [
            'name_sa' => 'व्यय / मोक्ष भाव (Vyaya Bhava)',
            'title_en' => 'House of Liberation, Reflection & Solitude',
            'title_hi' => 'व्यय, मोक्ष एवं एकांत भाव',
            'karaka' => 'Saturn, Ketu',
            'significance_en' => 'Signifies inner detachment, spiritual liberation (Moksha), restful retreat, charitable expenditure, foreign connections, and subconscious rest.',
            'significance_hi' => 'आध्यात्मिक मुक्ति (मोक्ष), आत्म-चिंतन, एकांत, परोपकारी व्यय, विदेश यात्रा और आंतरिक शांति।',
        ],
    ];

    /**
     * Nakshatra cultural and astronomical details (Deity, Symbol, Gana, Lord).
     */
    public const NAKSHATRA_METADATA = [
        0 => [
            'name' => 'Ashwini',
            'name_hi' => 'अश्विनी',
            'deity_en' => 'Ashwini Kumaras (Celestial Physicians)',
            'deity_hi' => 'अश्विनी कुमार (देवों के वैद्य)',
            'symbol_en' => "Horse's Head",
            'symbol_hi' => 'अश्व का सिर',
            'gana' => 'Deva',
            'significance_en' => 'Associated with swift healing, initiative, pioneering energy, vitality, and enthusiasm for beginning fresh ventures.',
            'significance_hi' => 'शीघ्र उपचारात्मक शक्ति, नई शुरुआत की तत्परता, ऊर्जावान स्वभाव और उत्साह से जुड़ा है।',
        ],
        1 => [
            'name' => 'Bharani',
            'name_hi' => 'भरणी',
            'deity_en' => 'Yama (God of Dharma & Cosmic Order)',
            'deity_hi' => 'यम (धर्म और संयम के देवता)',
            'symbol_en' => 'Yoni (Vessel of Creation)',
            'symbol_hi' => 'योनि (सृजन का पात्र)',
            'gana' => 'Manushya',
            'significance_en' => 'Governs transformative patience, moral discipline, creative gestation, emotional depth, and capacity to nurture ideas under pressure.',
            'significance_hi' => 'धैर्य, आत्म-संयम, रचनात्मक सहनशीलता, उत्तरदायित्व और कठिन परिस्थितियों में भी निर्माण की क्षमता।',
        ],
        2 => [
            'name' => 'Krittika',
            'name_hi' => 'कृत्तिका',
            'deity_en' => 'Agni (Sacred Fire)',
            'deity_hi' => 'अग्नि (पवित्र अग्नि)',
            'symbol_en' => 'Flame, Razor or Knife',
            'symbol_hi' => 'अग्नि शिखा या छुरिका',
            'gana' => 'Rakshasa',
            'significance_en' => 'Represents purifying intellect, penetrating determination, protective leadership, and sharp discernment between essence and illusion.',
            'significance_hi' => 'तीक्ष्ण बुद्धि, शुद्धि की शक्ति, नेतृत्व, सत्य की स्पष्ट पहचान और दृढ़ संकल्प का परिचायक है।',
        ],
        3 => [
            'name' => 'Rohini',
            'name_hi' => 'रोहिणी',
            'deity_en' => 'Brahma (Prajapati - The Creator)',
            'deity_hi' => 'ब्रह्मा (प्रजापति - सृष्टि के रचयिता)',
            'symbol_en' => 'Chariot, Temple or Cart',
            'symbol_hi' => 'रथ अथवा बैलगाड़ी',
            'gana' => 'Manushya',
            'significance_en' => 'Fosters aesthetic beauty, fertility, cultural appreciation, emotional warmth, material prosperity, and graceful growth.',
            'significance_hi' => 'सौंदर्य, कला, सृजनशीलता, भावनात्मक सहजता, भौतिक समृद्धि और मधुर स्वभाव की वृद्धि करता है।',
        ],
        4 => [
            'name' => 'Mrigashirsha',
            'name_hi' => 'मृगशिरा',
            'deity_en' => 'Soma (Chandra - Cosmic Nectar)',
            'deity_hi' => 'सोम (अमृत और चन्द्र देवता)',
            'symbol_en' => "Deer's Head",
            'symbol_hi' => 'हिरण का सिर',
            'gana' => 'Deva',
            'significance_en' => 'Embodies perpetual curiosity, gentle exploration, research oriented intellect, travel, and restless seeking of knowledge.',
            'significance_hi' => 'जिज्ञासु स्वभाव, ज्ञान की निरंतर खोज, सौम्यता, यात्राप्रियता और अध्ययनशील प्रवृत्ति।',
        ],
        5 => [
            'name' => 'Ardra',
            'name_hi' => 'आर्द्रा',
            'deity_en' => 'Rudra (Storm & Transformation Lord)',
            'deity_hi' => 'रुद्र (परिवर्तन एवं वायु के देवता)',
            'symbol_en' => 'Teardrop, Diamond',
            'symbol_hi' => 'अश्रु की बूंद अथवा हीरा',
            'gana' => 'Manushya',
            'significance_en' => 'Signifies clearing storms, emotional breakthroughs, deep analytical clarity following challenges, and renewal through truth.',
            'significance_hi' => 'चुनौतियों के बाद नई स्पष्टता, गहन विश्लेषणात्मक दृष्टि, आंतरिक दृढ़ता और सत्य के प्रति समर्पण।',
        ],
        6 => [
            'name' => 'Punarvasu',
            'name_hi' => 'पुनर्वसु',
            'deity_en' => 'Aditi (Cosmic Mother of Light)',
            'deity_hi' => 'अदिति (देवमाता)',
            'symbol_en' => 'Bow and Quiver of Arrows',
            'symbol_hi' => 'धनुष एवं तरकश',
            'gana' => 'Deva',
            'significance_en' => 'Inspires restoration, renewal, optimistic return to innocence, benevolent wisdom, and resilient recovery after setbacks.',
            'significance_hi' => 'पुनर्प्राप्ति, आशावादिता, नैतिक उदारता, विपरीत परिस्थितियों से उबरने की क्षमता और सद्भाव।',
        ],
        7 => [
            'name' => 'Pushya',
            'name_hi' => 'पुष्य',
            'deity_en' => 'Brihaspati (Teacher of Gods)',
            'deity_hi' => 'बृहस्पति (देवगुरु)',
            'symbol_en' => "Cow's Udder, Lotus or Circle",
            'symbol_hi' => 'कमल या गाय का थन',
            'gana' => 'Deva',
            'significance_en' => 'The most nourishing lunar mansion, symbolizing spiritual wisdom, steady protection, ethical guidance, and unconditional support.',
            'significance_hi' => 'परम पोषणकारी नक्षत्र; उच्च आध्यात्मिक ज्ञान, नीतिपरायणता, शांत स्वभाव और मार्गदर्शन का प्रतीक।',
        ],
        8 => [
            'name' => 'Ashlesha',
            'name_hi' => 'आश्लेषा',
            'deity_en' => 'Nagas (Serpents of Wisdom)',
            'deity_hi' => 'सर्प / नाग (गूढ़ ज्ञान के संरक्षक)',
            'symbol_en' => 'Coiled Serpent',
            'symbol_hi' => 'कुंडलित सर्प',
            'gana' => 'Rakshasa',
            'significance_en' => 'Endows keen psychological intuition, strategic vigilance, mystical insight, persuasive intellect, and self-preservation.',
            'significance_hi' => 'सूक्ष्म अंतर्दृष्टि, मनोवैज्ञानिक समझ, सतर्क बुद्धि, रणनीतिक कुशलता और गहन विचारशीलता।',
        ],
        9 => [
            'name' => 'Magha',
            'name_hi' => 'मघा',
            'deity_en' => 'Pitrus (Ancestors)',
            'deity_hi' => 'पितृगण (पूर्वज)',
            'symbol_en' => 'Royal Throne, Palanquin',
            'symbol_hi' => 'राजसिंहासन',
            'gana' => 'Rakshasa',
            'significance_en' => 'Represents ancestral legacy, self-respect, royal dignity, adherence to tradition, and noble ambition.',
            'significance_hi' => 'पितृ कृपा, आत्म-सम्मान, पारंपरिक मूल्य, नेतृत्व और गौरवपूर्ण व्यक्तित्व।',
        ],
        10 => [
            'name' => 'Purva Phalguni',
            'name_hi' => 'पूर्वा फाल्गुनी',
            'deity_en' => 'Bhaga (God of Fortune & Prosperity)',
            'deity_hi' => 'भग (सौभाग्य और समृद्धि के देवता)',
            'symbol_en' => 'Front Legs of a Bed or Hammock',
            'symbol_hi' => 'मंच अथवा शय्या',
            'gana' => 'Manushya',
            'significance_en' => 'Governs relaxation, joyful affections, romantic partnerships, artistic refinement, and sharing life delights with others.',
            'significance_hi' => 'आनंद, सौहार्द, कलात्मक अभिरुचि, दांपत्य सुख और सामाजिक सौम्यता।',
        ],
        11 => [
            'name' => 'Uttara Phalguni',
            'name_hi' => 'उत्तरा फाल्गुनी',
            'deity_en' => 'Aryaman (God of Friendship & Honor)',
            'deity_hi' => 'अर्यमा (मित्रता एवं परोपकार के देवता)',
            'symbol_en' => 'Back Legs of a Bed',
            'symbol_hi' => 'शय्या के पिछले पाये',
            'gana' => 'Manushya',
            'significance_en' => 'Highlights sustained commitment, generous hospitality, patronage, steady alliances, and moral responsibility.',
            'significance_hi' => 'स्थिर संबंध, उदारता, परोपकार, सामाजिक निष्ठा और उत्तरदायित्व का भाव।',
        ],
        12 => [
            'name' => 'Hasta',
            'name_hi' => 'हस्त',
            'deity_en' => 'Savitr (The Sun of Creative Light)',
            'deity_hi' => 'सवितृ (प्रातःकालीन सूर्य)',
            'symbol_en' => 'Open Hand, Clenched Fist',
            'symbol_hi' => 'खुली हथेली',
            'gana' => 'Deva',
            'significance_en' => 'Embodies skilled craftsmanship, dexterous hands, practical cleverness, healing touch, and humor.',
            'significance_hi' => 'हस्तशिल्प, व्यावहारिक दक्षता, कौशल, उपचार क्षमता और चतुर वाकपटुता।',
        ],
        13 => [
            'name' => 'Chitra',
            'name_hi' => 'चित्रा',
            'deity_en' => 'Twashtr / Vishwakarma (Celestial Architect)',
            'deity_hi' => 'त्वष्टा / विश्वकर्मा (सृष्टि के शिल्पी)',
            'symbol_en' => 'Bright Jewel, Pearl',
            'symbol_hi' => 'चमकता रत्न अथवा मोती',
            'gana' => 'Rakshasa',
            'significance_en' => 'Signifies visual architecture, artistic design, personal brilliance, precision, and passion for elegant aesthetics.',
            'significance_hi' => 'वास्तुकला, दृश्य सौंदर्य, रचनात्मक डिजाइन, चमक और उत्तम शिल्प कौशल।',
        ],
        14 => [
            'name' => 'Swati',
            'name_hi' => 'स्वाती',
            'deity_en' => 'Vayu (The Life Wind)',
            'deity_hi' => 'वायु (प्राणवायु देवता)',
            'symbol_en' => 'Shoot of Plant, Coral',
            'symbol_hi' => 'नया अंकुर अथवा मूंगा',
            'gana' => 'Deva',
            'significance_en' => 'Encourages independence, diplomatic flexibility, adaptable communications, trade acumen, and gentle movement.',
            'significance_hi' => 'स्वतंत्रता, कूटनीतिक लचीलापन, व्यापारिक कुशलता, संवाद और नम्र व्यवहार।',
        ],
        15 => [
            'name' => 'Vishakha',
            'name_hi' => 'विशाखा',
            'deity_en' => 'Indra & Agni (Triumph and Energy)',
            'deity_hi' => 'इन्द्राग्नि (इंद्र और अग्नि)',
            'symbol_en' => 'Triumphal Arch, Potter Wheel',
            'symbol_hi' => 'तोरण द्वार अथवा कुम्हार का चाक',
            'gana' => 'Rakshasa',
            'significance_en' => 'Bestows focused ambition, goal-oriented perseverance, competitive endurance, and capacity to reach heights.',
            'significance_hi' => 'लक्ष्य के प्रति एकाग्रता, दृढ़ संकल्प, विजय की चाह और अथक परिश्रम।',
        ],
        16 => [
            'name' => 'Anuradha',
            'name_hi' => 'अनुराधा',
            'deity_en' => 'Mitra (God of Compassion & Friendship)',
            'deity_hi' => 'मित्र (मैत्री और सद्भावना के देवता)',
            'symbol_en' => 'Lotus Flower, Row of Offerings',
            'symbol_hi' => 'कमल का पुष्प',
            'gana' => 'Deva',
            'significance_en' => 'Inspires deep devotional loyalty, collaborative harmony, resilience in adversity, and blossoming in difficult conditions.',
            'significance_hi' => 'गहरी मित्रता, निष्ठा, प्रतिकूलता में भी कमल की भांति खिलने की क्षमता और आध्यात्मिक झुकाव।',
        ],
        17 => [
            'name' => 'Jyeshtha',
            'name_hi' => 'ज्येष्ठा',
            'deity_en' => 'Indra (King of the Gods)',
            'deity_hi' => 'इंद्र (देवराज)',
            'symbol_en' => 'Circular Amulet, Umbrella',
            'symbol_hi' => 'सुरक्षा कवच अथवा छत्र',
            'gana' => 'Rakshasa',
            'significance_en' => 'Embodies protective responsibility, authoritative capability, seniority, courageous guardianship, and veteran leadership.',
            'significance_hi' => 'संरक्षण, अधिकार, वरिष्ठता, साहसी नेतृत्व और उत्तरदायित्व का निर्वहन।',
        ],
        18 => [
            'name' => 'Mula',
            'name_hi' => 'मूल',
            'deity_en' => 'Nirriti (Goddess of Dissolution & Foundations)',
            'deity_hi' => 'निरृति (विघटन एवं मूल की देवी)',
            'symbol_en' => 'Tied Bunch of Roots, Elephant Goad',
            'symbol_hi' => 'जड़ों का गुच्छा अथवा अंकुश',
            'gana' => 'Rakshasa',
            'significance_en' => 'Probes root causes, eliminates superflous attachments, and initiates deep philosophical investigations and renewal.',
            'significance_hi' => 'समस्या की जड़ तक पहुंचना, सत्य की खोज, गैर-जरूरी बंधनों से मुक्ति और दार्शनिक चिंतन।',
        ],
        19 => [
            'name' => 'Purva Ashadha',
            'name_hi' => 'पूर्वाषाढ़ा',
            'deity_en' => 'Apas (Cosmic Waters of Life)',
            'deity_hi' => 'आपः (जल देवता)',
            'symbol_en' => 'Winnowing Basket, Fan',
            'symbol_hi' => 'सूप (अनाज फटकने का पात्र)',
            'gana' => 'Manushya',
            'significance_en' => 'Brings emotional invincibility, purifying aspirations, inspiring eloquence, confidence, and ability to filter truth from untruth.',
            'significance_hi' => 'अजेय आत्मविश्वास, वाणी का प्रभाव, सत्य का चयन और प्रेरणादायक दृष्टिकोण।',
        ],
        20 => [
            'name' => 'Uttara Ashadha',
            'name_hi' => 'उत्तराषाढ़ा',
            'deity_en' => 'Vishwadevas (Universal Virtues)',
            'deity_hi' => 'विश्वेदेवा (सार्वभौमिक सत्य और धर्म)',
            'symbol_en' => "Elephant's Tusk, Small Cot",
            'symbol_hi' => 'हाथी का दांत',
            'gana' => 'Manushya',
            'significance_en' => 'Stands for permanent achievement, virtuous leadership, steadfast integrity, modesty in success, and universal harmony.',
            'significance_hi' => 'स्थायी सफलता, नैतिक सत्यनिष्ठा, विनम्रता, धैर्य और समग्र कल्याणकारी कार्य।',
        ],
        21 => [
            'name' => 'Shravana',
            'name_hi' => 'श्रवण',
            'deity_en' => 'Vishnu (The Preserver of the Cosmos)',
            'deity_hi' => 'भगवान विष्णु (जगतपालक)',
            'symbol_en' => 'Ear, Three Footprints',
            'symbol_hi' => 'कर्ण (कान) अथवा तीन पदचिह्न',
            'gana' => 'Deva',
            'significance_en' => 'Highlights attentive listening, traditional learning, oral wisdom preservation, scholarship, and service to society.',
            'significance_hi' => 'श्रवण शक्ति (ध्यानपूर्वक सुनना), ज्ञानार्जन, वैदिक ज्ञान, शांति और बौद्धिक परिपक्वता।',
        ],
        22 => [
            'name' => 'Dhanishta',
            'name_hi' => 'धनिष्ठा',
            'deity_en' => 'Ashta Vasus (The Eight Elemental Deities)',
            'deity_hi' => 'अष्ट वसु (प्रकृति के आठ तत्व)',
            'symbol_en' => 'Mridanga (Drum), Flute',
            'symbol_hi' => 'मृदंग अथवा बांसुरी',
            'gana' => 'Rakshasa',
            'significance_en' => 'Associated with musical rhythm, organizational wealth, timing, celebratory gatherings, and generous spirit.',
            'significance_hi' => 'संगीत, लयबद्धता, समन्वय, सामाजिक प्रतिष्ठा और उदार हृदय का संगम।',
        ],
        23 => [
            'name' => 'Shatabhisha',
            'name_hi' => 'शतभिषा',
            'deity_en' => 'Varuna (God of Cosmic Waters & Truth)',
            'deity_hi' => 'वरुण (जल एवं सत्य के देवता)',
            'symbol_en' => 'Empty Circle, Hundred Physicians',
            'symbol_hi' => 'खाली वृत्त अथवा सौ औषधियां',
            'gana' => 'Rakshasa',
            'significance_en' => 'Governs secretive healing, medicinal remedies, astronomical vision, contemplative solitude, and boundary discernment.',
            'significance_hi' => 'गूढ़ चिकित्सा, स्वास्थ्य सुधार, गहन अनुसंधान, एकांतप्रियता और सत्य का अन्वेषण।',
        ],
        24 => [
            'name' => 'Purva Bhadrapada',
            'name_hi' => 'पूर्वाभाद्रपद',
            'deity_en' => 'Aja Ekapada (The Cosmic Fire Dragon)',
            'deity_hi' => 'अज एकपाद (एक चरण वाले अग्नि देव)',
            'symbol_en' => 'Front of a Funeral Bed, Sword',
            'symbol_hi' => 'मंच के आगे के पाये अथवा खड्ग',
            'gana' => 'Manushya',
            'significance_en' => 'Signifies ascetic discipline, fiery determination, philosophical austerity, intense sincerity, and mystical transformation.',
            'significance_hi' => 'तपस्या, वैराग्य, गहन विचार, सत्यनिष्ठा और आंतरिक परिवर्तन का संकेत।',
        ],
        25 => [
            'name' => 'Uttara Bhadrapada',
            'name_hi' => 'उत्तराभाद्रपद',
            'deity_en' => 'Ahirbudhnya (Serpent of the Deep Waters)',
            'deity_hi' => 'अहिर्बुध्न्य (समुद्र के गहरे सर्प)',
            'symbol_en' => 'Back of a Funeral Bed, Snake in Water',
            'symbol_hi' => 'मंच के पीछे के पाये अथवा जल में सर्प',
            'gana' => 'Manushya',
            'significance_en' => 'Brings profound emotional stability, wise meditation, benevolence, deep equanimity, and benevolent protection.',
            'significance_hi' => 'मानसिक स्थिरता, ध्यान, परोपकार, सहनशीलता और गंभीर दृष्टिकोण।',
        ],
        26 => [
            'name' => 'Revati',
            'name_hi' => 'रेवती',
            'deity_en' => 'Pushan (The Nourisher & Safe Guide)',
            'deity_hi' => 'पूषा (मार्गदर्शक एवं पोषणकर्ता)',
            'symbol_en' => 'Fish, Pair of Fish Swimming',
            'symbol_hi' => 'मछली का जोड़ा अथवा ढोलक',
            'gana' => 'Deva',
            'significance_en' => 'The final lunar mansion; symbolizes safe passage, universal compassion, gentle guidance, wealth, and completion of the cycle.',
            'significance_hi' => 'पूर्णता, सुरक्षित यात्रा, दयालुता, करुणा, पशु-प्रेम और आध्यात्मिक संतोष।',
        ],
    ];

    /**
     * Synthesize complete rule-based interpretations for a generated Kundli chart.
     *
     * @param array<string, mixed> $kundliData
     * @param string $locale
     * @return array<string, mixed>
     */
    public function interpretChart(array $kundliData, string $locale = 'en'): array
    {
        $planets = $kundliData['planets'] ?? [];
        $houses = $kundliData['houses'] ?? null;
        $ascendant = $kundliData['ascendant'] ?? null;
        $chandraKundli = $kundliData['chandra_kundli'] ?? null;

        return [
            'disclaimer' => $locale === 'hi' ? self::DISCLAIMER_HI : self::DISCLAIMER_EN,
            'overview' => $this->interpretOverview($ascendant, $planets, $locale),
            'planets' => $this->interpretPlanets($planets, $houses, $locale),
            'houses' => $houses ? $this->interpretHouses($houses, $locale) : [],
            'nakshatra' => $this->interpretMoonNakshatra($planets['moon'] ?? null, $locale),
            'lagna_nakshatra' => isset($ascendant['nakshatra']) ? $this->interpretLagnaNakshatra($ascendant, $locale) : null,
        ];
    }

    /**
     * Interpret high-level chart overview (Lagna sign, Moon sign, and Sun sign).
     */
    protected function interpretOverview(?array $ascendant, array $planets, string $locale): array
    {
        $lagnaConfirmed = ($ascendant['status'] ?? null) === 'confirmed';
        $lagnaName = $ascendant['vedic_name'] ?? ($ascendant['name'] ?? 'Unknown');
        $moonRashi = $planets['moon']['rashi']['vedic_name'] ?? ($planets['moon']['rashi']['name'] ?? 'Chandra');
        $sunRashi = $planets['sun']['rashi']['vedic_name'] ?? ($planets['sun']['rashi']['name'] ?? 'Surya');

        if ($locale === 'hi') {
            $text = "आपकी चंद्र राशि {$moonRashi} है और सूर्य {$sunRashi} राशि में स्थित हैं। ";
            if ($lagnaConfirmed) {
                $text .= "लग्न भाव में {$lagnaName} राशि उदित होने से व्यक्तित्व में इस राशि के मूलभूत गुण परिलक्षित होते हैं। यह लग्न जीवन के प्रति आपके स्वाभाविक दृष्टिकोण और आंतरिक प्रेरणा को दिशा प्रदान करता है।";
            } else {
                $text .= "जन्म समय की अनिश्चितता के कारण लग्न की गणना सुरक्षित रूप से स्थगित रखी गई है, तथा चंद्र राशि को केंद्र मानकर विश्लेषण प्रस्तुत किया गया है।";
            }
        } else {
            $text = "Your Moon sign (Chandra Rashi) is {$moonRashi} and your Sun is placed in {$sunRashi}. ";
            if ($lagnaConfirmed) {
                $text .= "The rising sign (Lagna) is {$lagnaName}, defining the foundational energy through which your life experiences, vitality, and external persona express themselves.";
            } else {
                $text .= "Because the exact birth time is approximate, the Ascendant evaluation has been safely deferred, and the Moon sign acts as the primary reference point.";
            }
        }

        return [
            'lagna_confirmed' => $lagnaConfirmed,
            'lagna_sign' => $ascendant['name'] ?? null,
            'lagna_vedic_name' => $ascendant['vedic_name'] ?? null,
            'moon_sign' => $planets['moon']['rashi']['name'] ?? null,
            'moon_sign_vedic_name' => $planets['moon']['rashi']['vedic_name'] ?? null,
            'sun_sign' => $planets['sun']['rashi']['name'] ?? null,
            'sun_sign_vedic_name' => $planets['sun']['rashi']['vedic_name'] ?? null,
            'summary' => $text,
        ];
    }

    /**
     * Interpret all 9 planets with dignity, retrograde, combustion, and house placement notes.
     */
    protected function interpretPlanets(array $planets, ?array $houses, string $locale): array
    {
        $result = [];
        $planetHouseMap = [];

        // Build reverse lookup: planet -> house number
        if ($houses) {
            foreach ($houses as $h) {
                $hNum = (int) ($h['house_number'] ?? 1);
                $residents = $h['planets'] ?? ($h['resident_planets'] ?? []);
                foreach ($residents as $rp) {
                    $k = strtolower((string) ($rp['key'] ?? ''));
                    if ($k !== '') {
                        $planetHouseMap[$k] = $hNum;
                    }
                }
            }
        }

        foreach ($planets as $key => $p) {
            $pKey = strtolower($key);
            $hNum = $planetHouseMap[$pKey] ?? null;
            $dignity = $p['dignity'] ?? 'neutral';
            $isRetro = (bool) ($p['is_retrograde'] ?? false);
            $isCombust = (bool) ($p['is_combust'] ?? false);
            $rashiName = $locale === 'hi' ? ($p['rashi']['vedic_name'] ?? $p['rashi']['name']) : $p['rashi']['name'];
            $pName = $locale === 'hi' ? ($p['name_hi'] ?? $p['name']) : $p['name'];

            $explanation = $this->buildPlanetExplanation(
                planetKey: $pKey,
                planetName: $pName,
                rashiName: $rashiName,
                houseNumber: $hNum,
                dignity: $dignity,
                isRetro: $isRetro,
                isCombust: $isCombust,
                locale: $locale
            );

            $result[$pKey] = [
                'planet' => $p['name'],
                'planet_hi' => $p['name_hi'] ?? $p['name'],
                'rashi' => $p['rashi']['name'],
                'rashi_vedic' => $p['rashi']['vedic_name'],
                'house' => $hNum,
                'dignity' => $dignity,
                'is_retrograde' => $isRetro,
                'is_combust' => $isCombust,
                'nakshatra' => $p['nakshatra']['name'],
                'pada' => $p['nakshatra']['pada'],
                'explanation' => $explanation,
            ];
        }

        return $result;
    }

    /**
     * Build classical, constructive paragraph for a specific planet.
     */
    protected function buildPlanetExplanation(
        string $planetKey,
        string $planetName,
        string $rashiName,
        ?int $houseNumber,
        string $dignity,
        bool $isRetro,
        bool $isCombust,
        string $locale
    ): string {
        if ($locale === 'hi') {
            $text = "{$planetName} {$rashiName} राशि में स्थित हैं";
            if ($houseNumber !== null) {
                $text .= " (भाव संख्या {$houseNumber})";
            }
            $text .= "। ";

            if ($dignity === 'exalted') {
                $text .= "यह ग्रह की उच्च स्थिति है, जो इसके स्वाभाविक कारकतत्वों को उच्च नैतिक बल और स्पष्टता प्रदान करती है। ";
            } elseif ($dignity === 'own') {
                $text .= "यह ग्रह अपने ही स्वक्षेत्र में स्थित होकर स्थिरता और आत्मनिर्भरता दर्शाता है। ";
            } elseif ($dignity === 'debilitated') {
                $text .= "नीच राशि में होने के कारण इस ग्रह से जुड़े क्षेत्रों में अतिरिक्त सजगता, धैर्य और आत्ममंथन की आवश्यकता होती है। ";
            }

            if ($isRetro) {
                $text .= "वक्री अवस्था दर्शाती है कि इस ग्रह के विषयों में व्यक्ति की विचार प्रक्रिया पारंपरिक से हटकर अधिक चिंतनशील होती है। ";
            }

            if ($isCombust) {
                $text .= "सूर्य के अति निकट होने (अस्त) के कारण इसके बाह्य प्रभाव की अपेक्षा आंतरिक मानसिक जागरूकता अधिक प्रभावी रहती है। ";
            }

            return trim($text);
        }

        // English interpretation
        $text = "{$planetName} is placed in {$rashiName}";
        if ($houseNumber !== null) {
            $text .= " (House {$houseNumber})";
        }
        $text .= ". ";

        if ($dignity === 'exalted') {
            $text .= "This is an exalted placement, classical texts regard this as conferring high moral confidence and clear expression of its natural significations. ";
        } elseif ($dignity === 'own') {
            $text .= "Situated in its own domicile, reflecting strong natural stability and self-sufficiency. ";
        } elseif ($dignity === 'debilitated') {
            $text .= "In a debilitated position, indicating that matters governed by this planet benefit from deliberate patience, conscious grounding, and self-reflection. ";
        }

        if ($isRetro) {
            $text .= "Retrograde motion (Vakri) brings an introspective, non-linear approach to the areas ruled by this planet. ";
        }

        if ($isCombust) {
            $text .= "Combustion (Asta) due to proximity with the Sun directs its energy inwardly rather than in ostentatious outward display. ";
        }

        return trim($text);
    }

    /**
     * Interpret all 12 houses with house lords and resident planets.
     */
    protected function interpretHouses(array $houses, string $locale): array
    {
        $result = [];

        foreach ($houses as $h) {
            $hNum = (int) ($h['house_number'] ?? 1);
            $meta = self::HOUSE_SIGNIFICANCES[$hNum] ?? null;
            $signName = $locale === 'hi'
                ? ($h['sign_vedic_name'] ?? ($h['rashi']['vedic_name'] ?? ($h['sign_name'] ?? ($h['rashi']['name'] ?? 'Aries'))))
                : ($h['sign_name'] ?? ($h['rashi']['name'] ?? 'Aries'));

            $rawLord = $h['sign_lord'] ?? ($h['lord'] ?? 'Mars');
            if (is_array($rawLord)) {
                $lordName = $locale === 'hi' ? ($rawLord['name_hi'] ?? $rawLord['name']) : $rawLord['name'];
                $lordEn = $rawLord['name'];
                $lordHi = $rawLord['name_hi'] ?? $rawLord['name'];
            } else {
                $lordEn = (string) $rawLord;
                $lordHi = match ($lordEn) {
                    'Sun' => 'सूर्य',
                    'Moon' => 'चन्द्र',
                    'Mars' => 'मंगल',
                    'Mercury' => 'बुध',
                    'Jupiter' => 'बृहस्पति',
                    'Venus' => 'शुक्र',
                    'Saturn' => 'शनि',
                    'Rahu' => 'राहु',
                    'Ketu' => 'केतु',
                    default => $lordEn,
                };
                $lordName = $locale === 'hi' ? $lordHi : $lordEn;
            }

            $residents = $h['planets'] ?? ($h['resident_planets'] ?? []);
            $residentNames = [];
            foreach ($residents as $rp) {
                $residentNames[] = $locale === 'hi' ? ($rp['name_hi'] ?? $rp['name']) : $rp['name'];
            }

            if ($locale === 'hi') {
                $resText = count($residentNames) > 0
                    ? 'उपस्थित ग्रह: ' . implode(', ', $residentNames) . '।'
                    : 'इस भाव में कोई प्रत्यक्ष ग्रह उपस्थित नहीं है, अतः इसका फल मुख्य रूप से इसके स्वामी पर निर्भर करता है।';

                $summary = "भाव {$hNum} ({$meta['name_sa']}) में {$signName} राशि है, जिसके स्वामी {$lordName} हैं। {$resText}";
            } else {
                $resText = count($residentNames) > 0
                    ? 'Resident planets: ' . implode(', ', $residentNames) . '.'
                    : 'No direct resident planets; its core vitality is governed by its lord.';

                $summary = "House {$hNum} ({$meta['title_en']}) contains {$signName}, ruled by {$lordName}. {$resText}";
            }

            $result[$hNum] = [
                'house_number' => $hNum,
                'name_sa' => $meta['name_sa'] ?? "Bhava {$hNum}",
                'title' => $locale === 'hi' ? $meta['title_hi'] : $meta['title_en'],
                'karaka' => $meta['karaka'] ?? 'Unknown',
                'sign_name' => $h['sign_name'] ?? ($h['rashi']['name'] ?? 'Aries'),
                'sign_vedic_name' => $h['sign_vedic_name'] ?? ($h['rashi']['vedic_name'] ?? 'Mesha'),
                'lord' => $lordEn,
                'lord_hi' => $lordHi,
                'significance' => $locale === 'hi' ? $meta['significance_hi'] : $meta['significance_en'],
                'resident_planets' => $residents,
                'interpretation' => $summary,
            ];
        }

        return $result;
    }

    /**
     * Interpret the Janma Nakshatra (natal Moon constellation).
     */
    protected function interpretMoonNakshatra(?array $moonData, string $locale): ?array
    {
        if (!$moonData || !isset($moonData['nakshatra'])) {
            return null;
        }

        $nak = $moonData['nakshatra'];
        $idx = (int) ($nak['id'] ?? 0);
        $pada = (int) ($nak['pada'] ?? 1);
        $meta = self::NAKSHATRA_METADATA[$idx] ?? null;

        $name = $locale === 'hi' ? ($nak['name_hi'] ?? $nak['name']) : $nak['name'];
        $lordName = $locale === 'hi' ? ($nak['lord_hi'] ?? $nak['lord']) : $nak['lord'];

        if ($locale === 'hi') {
            $summary = "आपका जन्म नक्षत्र {$name} (पाद {$pada}) है। इसके स्वामी ग्रह {$lordName} हैं। " .
                ($meta ? "इस नक्षत्र के अधिष्ठाता देवता {$meta['deity_hi']} और प्रतीक '{$meta['symbol_hi']}' है। {$meta['significance_hi']}" : "");
        } else {
            $summary = "Your birth star (Janma Nakshatra) is {$name} (Pada {$pada}), ruled by {$lordName}. " .
                ($meta ? "Its presiding deity is {$meta['deity_en']} and symbol is {$meta['symbol_en']}. {$meta['significance_en']}" : "");
        }

        return [
            'id' => $idx,
            'name' => $nak['name'],
            'name_hi' => $nak['name_hi'] ?? $nak['name'],
            'pada' => $pada,
            'lord' => $nak['lord'],
            'lord_hi' => $nak['lord_hi'] ?? $nak['lord'],
            'deity' => $locale === 'hi' ? ($meta['deity_hi'] ?? null) : ($meta['deity_en'] ?? null),
            'symbol' => $locale === 'hi' ? ($meta['symbol_hi'] ?? null) : ($meta['symbol_en'] ?? null),
            'gana' => $meta['gana'] ?? 'Manushya',
            'significance' => $locale === 'hi' ? ($meta['significance_hi'] ?? null) : ($meta['significance_en'] ?? null),
            'summary' => trim($summary),
        ];
    }

    /**
     * Interpret Lagna Nakshatra when Ascendant is confirmed.
     */
    protected function interpretLagnaNakshatra(array $ascendant, string $locale): ?array
    {
        if (($ascendant['status'] ?? null) !== 'confirmed' || empty($ascendant['nakshatra'])) {
            return null;
        }

        $nak = $ascendant['nakshatra'];
        $idx = (int) ($nak['id'] ?? 0);
        $pada = (int) ($nak['pada'] ?? 1);
        $meta = self::NAKSHATRA_METADATA[$idx] ?? null;

        $name = $locale === 'hi' ? ($nak['name_hi'] ?? $nak['name']) : $nak['name'];
        $lordName = $locale === 'hi' ? ($nak['lord_hi'] ?? $nak['lord']) : $nak['lord'];

        if ($locale === 'hi') {
            $summary = "लग्न नक्षत्र {$name} (पाद {$pada}) है, जिसके अधिपति {$lordName} हैं। यह जीवन में आपकी प्राथमिक प्रेरणा को गति प्रदान करता है।";
        } else {
            $summary = "Ascendant constellation is {$name} (Pada {$pada}), governed by {$lordName}, shaping the initial life drive.";
        }

        return [
            'id' => $idx,
            'name' => $nak['name'],
            'pada' => $pada,
            'lord' => $nak['lord'],
            'summary' => $summary,
        ];
    }
}
