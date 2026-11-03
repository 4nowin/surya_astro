<?php

namespace Database\Seeders;

use App\Services\Horoscope\DailyHoroscopeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class HoroscopeSeeder extends Seeder
{
    /**
     * Traditional Vedic Sign Astrological Profiles (English & Hindi)
     */
    protected const SIGN_PROFILES = [
        'Aries' => [
            'name_hi' => 'मेष',
            'lord_en' => 'Mars',
            'lord_hi' => 'मंगल',
            'element' => 'Fire',
            'colors_en' => ['Crimson Red', 'Coral', 'Scarlet', 'Deep Orange'],
            'colors_hi' => ['गहरा लाल', 'मूंगा', 'सिंदूरी', 'नारंगी'],
            'numbers' => ['9', '18', '27', '1'],
            'times' => ['07:15 AM', '11:30 AM', '04:45 PM', '08:00 PM'],
            'moods_en' => ['Dynamic', 'Energetic', 'Decisive', 'Bold'],
            'moods_hi' => ['ऊर्जावान', 'सक्रिय', 'दृढ़निश्चयी', 'साहसी'],
            'en' => [
                'base' => "Mars ignites high motivation and pioneering drive for Aries today. You are primed to take the initiative and overcome obstacles with bold resolve.",
                'career' => "Your proactive leadership shines at work. Spearhead pending tasks and make direct decisions, but avoid unnecessary clashes with senior colleagues.",
                'love' => "Passionate enthusiasm enlivens your personal connections. Singles attract admirers through authentic confidence; couples enjoy invigorating shared activities.",
                'money' => "Favorable conditions for swift financial negotiations. Focus on productive ventures while resisting impulsive or unvetted speculative expenses.",
                'health' => "Robust physical stamina is present. Channel high energy into outdoor sports or vigorous workouts; stay well hydrated to balance internal body heat.",
                'travel' => "Spontaneous short-distance business trips or adventurous travels yield invigorating experiences and fresh opportunities.",
            ],
            'hi' => [
                'base' => "राशि स्वामी मंगल का प्रभाव आज मेष राशि के जातकों में अदम्य साहस और नई ऊर्जा का संचार कर रहा है। अटके हुए कार्यों को गति देने का उत्तम समय है।",
                'career' => "कार्यक्षेत्र में आपका नेतृत्व और पराक्रम सराहा जाएगा। नए प्रोजेक्ट्स शुरू करने के लिए दिन अनुकूल है; वरिष्ठ अधिकारियों से संवाद में संयम रखें।",
                'love' => "दांपत्य व प्रेम संबंधों में नई उमंग और उत्साह का संचार होगा। साथी के साथ खुलकर बातचीत से आपसी समझ प्रगाढ़ होगी।",
                'money' => "आर्थिक लेन-देन में सक्रियता रहेगी और लाभ के अवसर बनेंगे। किसी भी जल्दबाजी वाले सट्टे या अनावश्यक व्यय से बचना श्रेयस्कर रहेगा।",
                'health' => "शारीरिक ऊर्जा का स्तर ऊंचा रहेगा। इस ऊर्जा को योग और व्यायाम में लगाएं। पित्त व गर्मी से बचने के लिए पर्याप्त जल ग्रहण करें।",
                'travel' => "कार्य संबंधी छोटी यात्राएं अत्यंत फलदायी सिद्ध होंगी। यात्रा के दौरान नई संभावनाओं और संपर्कों का मार्ग प्रशस्त होगा।",
            ],
        ],
        'Taurus' => [
            'name_hi' => 'वृषभ',
            'lord_en' => 'Venus',
            'lord_hi' => 'शुक्र',
            'element' => 'Earth',
            'colors_en' => ['Milky White', 'Emerald Green', 'Soft Pink', 'Ivory'],
            'colors_hi' => ['धवल श्वेत', 'पन्ना हरा', 'गुलाबी', 'हाथीदांत श्वेत'],
            'numbers' => ['6', '15', '24', '2'],
            'times' => ['08:30 AM', '12:45 PM', '05:15 PM', '09:30 PM'],
            'moods_en' => ['Calm', 'Grounded', 'Affectionate', 'Content'],
            'moods_hi' => ['शांत', 'स्थिर', 'स्नेही', 'संतुष्ट'],
            'en' => [
                'base' => "Venus brings grounding grace and aesthetic harmony to Taurus today. Patience and perseverance will yield gratifying domestic and material results.",
                'career' => "Methodical diligence yields steady progress. Colleagues rely on your pragmatic stability during complex deliberations.",
                'love' => "Warmhearted tenderness strengthens emotional bonds. Enjoying home-cooked meals or sharing art and music creates memorable moments.",
                'money' => "Financial security is reinforced through prudent management. Good day to evaluate real estate, household assets, or long-term fixed deposits.",
                'health' => "Physical endurance is solid. Practice gentle neck and throat exercises, and incorporate fresh seasonal fruits into your diet.",
                'travel' => "Leisurely, peaceful journeys to countryside spots or artistic venues provide deep relaxation and rejuvenation.",
            ],
            'hi' => [
                'base' => "शुक्र देव की कृपा से आज वृषभ राशि के जातकों के लिए सुख, सौम्यता और संतुलन बना रहेगा। आपका धैर्य और व्यावहारिक दृष्टिकोण सफलता दिलाएगा।",
                'career' => "कार्यस्थल पर आपकी योजनाबद्ध कार्यशैली और विश्वसनीयता की प्रशंसा होगी। महत्वपूर्ण व्यावसायिक अनुबंधों को अंतिम रूप देने के लिए दिन शुभ है।",
                'love' => "प्रेम व दांपत्य जीवन में मधुरता और परस्पर विश्वास बढ़ेगा। जीवनसाथी के साथ कला, संगीत या सुखद पारिवारिक पलों का आनंद लेंगे।",
                'money' => "धन संचय और स्थायी संपत्तियों के मूल्यांकन के लिए स्थिति अनुकूल है। पूर्व में किए गए निवेशों से संतोषजनक प्रतिफल प्राप्त होगा।",
                'health' => "स्वास्थ्य उत्तम और स्थिर रहेगा। गले और श्वसन तंत्र की देखभाल करें; सात्विक और पौष्टिक भोजन का सेवन लाभप्रद रहेगा।",
                'travel' => "प्राकृतिक सौंदर्य और शांतिपूर्ण परिवेश वाले स्थानों की यात्रा मन को ताजगी और आध्यात्मिक सुकून प्रदान करेगी।",
            ],
        ],
        'Gemini' => [
            'name_hi' => 'मिथुन',
            'lord_en' => 'Mercury',
            'lord_hi' => 'बुध',
            'element' => 'Air',
            'colors_en' => ['Bright Yellow', 'Mint Green', 'Light Cyan', 'Goldenrod'],
            'colors_hi' => ['चमकीला पीला', 'तोतिया हरा', 'आसमानी', 'पीताम्बर'],
            'numbers' => ['5', '14', '23', '3'],
            'times' => ['09:00 AM', '01:15 PM', '04:30 PM', '07:45 PM'],
            'moods_en' => ['Curious', 'Witty', 'Versatile', 'Sociable'],
            'moods_hi' => ['जिज्ञासु', 'वाक्पटु', 'कुशाग्र', 'मिलनसार'],
            'en' => [
                'base' => "Mercury stimulates sparkling mental agility and eloquence for Gemini today. Ideas flow freely, making it an exceptional day for brainstorming and networking.",
                'career' => "Communication projects, presentations, and trade negotiations progress smoothly. Your ability to juggle multifaceted responsibilities impresses teammates.",
                'love' => "Lighthearted banter and intellectual camaraderie define your romantic sphere. Stimulating dialogues keep mutual curiosity vibrant.",
                'money' => "Multiple commercial opportunities surface. Review agreements carefully before signing, and avoid dispersing funds across too many speculative schemes.",
                'health' => "Keep the nervous system calm through rhythmic breathing or short walking breaks from screens. Prioritize restorative evening rest.",
                'travel' => "Short urban excursions, meeting friends, or visiting informational seminars will prove stimulating and rewarding.",
            ],
            'hi' => [
                'base' => "बुध के प्रभाव से मिथुन राशि के जातकों की बुद्धि और वाक्चातुर्य आज शिखर पर रहेगा। नए विचारों, संचार और व्यापारिक संपर्कों के लिए अत्यंत शुभ दिन है।",
                'career' => "व्यापारिक वार्ताओं, लेखन, मीडिया और मार्केटिंग से जुड़े कार्यों में शानदार सफलता मिलेगी। कई कार्यों को एक साथ कुशलता से संभाल सकेंगे।",
                'love' => "रोचक संवाद और हास-परिहास से प्रेम जीवन में ताजगी आएगी। बौद्धिक रूप से समृद्ध चर्चाएं साथी के साथ निकटता बढ़ाएंगी।",
                'money' => "आर्थिक लेन-देन में तेजी आएगी और आय के नए स्रोत सामने आ सकते हैं। कागजातों की बारीकी से जांच करने के बाद ही कोई निर्णय लें।",
                'health' => "मानसिक सक्रियता अधिक रहेगी; प्राणायाम और ध्यान से मन को शांत रखें। अत्यधिक स्क्रीन समय से आंखों को विश्राम दें।",
                'travel' => "स्थानीय संपर्कों, बैठकों और अध्ययन संबंधी छोटी यात्राओं से ज्ञानार्जन और लाभ के सुअवसर प्राप्त होंगे।",
            ],
        ],
        'Cancer' => [
            'name_hi' => 'कर्क',
            'lord_en' => 'Moon',
            'lord_hi' => 'चंद्रमा',
            'element' => 'Water',
            'colors_en' => ['Pearl White', 'Silver', 'Ocean Cream', 'Moonlight Grey'],
            'colors_hi' => ['मोतिया श्वेत', 'रजत', 'क्रीम', 'चांदी जैसा'],
            'numbers' => ['2', '7', '11', '20'],
            'times' => ['06:45 AM', '10:30 AM', '03:15 PM', '08:30 PM'],
            'moods_en' => ['Intuitive', 'Caring', 'Empathetic', 'Peaceful'],
            'moods_hi' => ['संवेदनशील', 'स्नेही', 'अंतर्ज्ञानी', 'शांत'],
            'en' => [
                'base' => "The Moon heightens emotional depth and intuitive perception for Cancer today. Honoring your gut instincts protects you in personal and professional choices.",
                'career' => "Collaborative team harmony is fostered by your empathetic approach. You excel in resolving interpersonal tensions within your workplace.",
                'love' => "Deep emotional warmth envelops your home life. Expressing tender appreciation to your significant other deepens mutual devotion.",
                'money' => "Security-minded decisions prevail. It is a sensible time to organize domestic reserves, budget for home comforts, and manage family finances.",
                'health' => "Hydration and digestive balance are essential. Warm, nourishing soups and peaceful meditation near water soothe your spirit.",
                'travel' => "Visits to ancestral homes, sacred riverside shrines, or tranquil lakeshores provide deep mental tranquility.",
            ],
            'hi' => [
                'base' => "चंद्रमा का शुभ प्रभाव आज कर्क राशि के जातकों में अंतर्ज्ञान और पारिवारिक सौहार्द को बढ़ाएगा। अपनी सहज प्रेरणाओं पर विश्वास करें।",
                'career' => "सहकर्मियों के साथ आत्मीय संबंध कार्यक्षेत्र में सकारात्मक वातावरण बनाएंगे। मानव संसाधन और सेवा कार्यों से जुड़े लोगों को विशेष लाभ होगा।",
                'love' => "पारिवारिक रिश्तों और दांपत्य जीवन में गहरा भावनात्मक जुड़ाव रहेगा। प्रियजनों के प्रति आपका स्नेह संबंधों को नई मजबूती देगा।",
                'money' => "घरेलू आवश्यकताओं और सुख-सुविधाओं पर संतुलित व्यय होगा। पारिवारिक संचित निधि को सुरक्षित रखने के निर्णय सफल रहेंगे।",
                'health' => "पाचन तंत्र और मानसिक शांति पर ध्यान दें। पर्याप्त मात्रा में जल पिएं और रात्रि में आरामदायक नींद लें।",
                'travel' => "जलाशय, पवित्र नदियों या मातृ-पक्ष के तीर्थ स्थलों की यात्रा से आत्मिक संतोष और शांति की अनुभूति होगी।",
            ],
        ],
        'Leo' => [
            'name_hi' => 'सिंह',
            'lord_en' => 'Sun',
            'lord_hi' => 'सूर्य',
            'element' => 'Fire',
            'colors_en' => ['Sunlit Gold', 'Saffron', 'Ruby Red', 'Amber'],
            'colors_hi' => ['स्वर्णिम', 'केसरिया', 'माणिक्य लाल', 'सुनहरा पीला'],
            'numbers' => ['1', '10', '19', '28'],
            'times' => ['07:00 AM', '11:45 AM', '02:30 PM', '06:15 PM'],
            'moods_en' => ['Radiant', 'Confident', 'Generous', 'Regal'],
            'moods_hi' => ['प्रतापी', 'आत्मविश्वासी', 'उदार', 'तेजस्वी'],
            'en' => [
                'base' => "The Sun endows Leo with radiant vitality, authoritative presence, and clear conviction today. Take the center stage with gracious magnanimity.",
                'career' => "Your executive competence and strategic vision command respect from peers and superiors. An opportune time to pitch high-stakes proposals.",
                'love' => "Generous expressions of love and heartfelt gestures spark romantic joy. Mutual pride and authentic loyalty define relationship bonds.",
                'money' => "Earning potential rises through visionary enterprise. Keep luxury spending balanced against strategic wealth accumulation goals.",
                'health' => "Strong cardiovascular vitality and stamina. Basking in morning sunlight and maintaining upright posture support overall wellbeing.",
                'travel' => "Travel to prestigious conferences, cultural festivals, or grand scenic destinations brings recognition and delight.",
            ],
            'hi' => [
                'base' => "सूर्य देव का तेज सिंह राशि के जातकों के मान-सम्मान और आत्मविश्वास में अपार वृद्धि करेगा। आपकी नेतृत्व क्षमता और प्रभाव आज सर्वोपरि रहेगा।",
                'career' => "प्रशासनिक कार्यों और उच्च अधिकारियों के सहयोग से अटके कार्य पूर्ण होंगे। कार्यक्षेत्र में आपके निर्णय दूरगामी सफलता की नींव रखेंगे।",
                'love' => "प्रेम संबंधों में उदारता और निष्ठा की प्रधानता रहेगी। साथी के सम्मान और खुशियों का ध्यान रखने से संबंध अत्यंत मधुर रहेंगे।",
                'money' => "आर्थिक स्थिति सुदृढ़ होगी और प्रतिष्ठा में वृद्धि होगी। विलासिता की वस्तुओं पर व्यय करते समय बजट का ध्यान रखना आवश्यक है।",
                'health' => "आत्मबल और शारीरिक तेज बना रहेगा। प्रातःकालीन सूर्य नमस्कार और नियमित दिनचर्या से ऊर्जा का संचार निरंतर रहेगा।",
                'travel' => "प्रतिष्ठित सम्मेलनों, बैठकों या भव्य दर्शनीय स्थलों की यात्रा से सामाजिक दायरे में विस्तार होगा।",
            ],
        ],
        'Virgo' => [
            'name_hi' => 'कन्या',
            'lord_en' => 'Mercury',
            'lord_hi' => 'बुध',
            'element' => 'Earth',
            'colors_en' => ['Olive Green', 'Slate Grey', 'Forest Green', 'Earthy Beige'],
            'colors_hi' => ['धानी हरा', 'स्लेटी', 'गहरा हरा', 'हल्का बादामी'],
            'numbers' => ['5', '14', '32', '6'],
            'times' => ['08:15 AM', '12:00 PM', '03:45 PM', '07:30 PM'],
            'moods_en' => ['Analytical', 'Meticulous', 'Diligent', 'Systematic'],
            'moods_hi' => ['विचारशील', 'सूक्ष्मदर्शी', 'परिश्रमी', 'व्यवस्थित'],
            'en' => [
                'base' => "Mercury refines your analytical precision, practical discernment, and organizational genius today. You resolve complex tangles with orderly perfection.",
                'career' => "Attention to critical details ensures exceptional quality in documentation, data analysis, and professional project audits.",
                'love' => "Thoughtful acts of practical service communicate devotion more powerfully than flowery words. Honest discussions resolve subtle doubts.",
                'money' => "Superb day for budget recalibration, auditing accounts, and discovering avenues to eliminate unnecessary operational expenses.",
                'health' => "Support gut health through wholesome fiber, herbal infusions, and mindful eating habits. Avoid overanalyzing routine stressors.",
                'travel' => "Well-structured, purposeful journeys dedicated to research, health retreats, or professional certifications run without hitch.",
            ],
            'hi' => [
                'base' => "बुध ग्रह की शुभता से कन्या राशि के जातकों की तार्किक क्षमता और कार्यकुशलता आज अत्यंत प्रभावी रहेगी। योजनाबद्ध तरीके से किए गए कार्य सफल होंगे।",
                'career' => "दस्तावेजीकरण, लेखा-जोखा और शोध संबंधी कार्यों में त्रुटिरहित परिणाम देंगे। आपकी सूझबूझ से जटिल समस्याओं का सटीक समाधान निकलेगा।",
                'love' => "व्यावहारिक सहयोग और एक-दूसरे की जरूरतों को समझना संबंधों में विश्वास बढ़ाएगा। शांत चित्त से की गई बातचीत से गलतफहमियां दूर होंगी।",
                'money' => "वित्तीय प्रबंधन और खर्चों पर नियंत्रण के लिए समय उत्तम है। बचत योजनाओं और भविष्य के निवेशों पर गंभीरता से विचार करेंगे।",
                'health' => "पाचन और स्वास्थ्य के प्रति सचेत रहें। संतुलित सात्विक आहार और नियमित व्यायाम से मानसिक व शारीरिक स्फूर्ति बनी रहेगी।",
                'travel' => "अध्ययन, स्वास्थ्य लाभ या व्यावसायिक प्रशिक्षण से जुड़ी यात्राएं सुव्यवस्थित और उद्देश्यपूर्ण रहेंगी।",
            ],
        ],
        'Libra' => [
            'name_hi' => 'तुला',
            'lord_en' => 'Venus',
            'lord_hi' => 'शुक्र',
            'element' => 'Air',
            'colors_en' => ['Pastel Blue', 'Rose Pink', 'Turquoise', 'Silver-Grey'],
            'colors_hi' => ['हल्का नीला', 'गुलाबी', 'फिरोजी', 'रुपहला'],
            'numbers' => ['6', '15', '24', '7'],
            'times' => ['09:30 AM', '02:00 PM', '05:45 PM', '09:00 PM'],
            'moods_en' => ['Harmonious', 'Diplomatic', 'Charming', 'Balanced'],
            'moods_hi' => ['संतुलित', 'सौहार्दपूर्ण', 'आकर्षक', 'न्यायप्रिय'],
            'en' => [
                'base' => "Venus casts an aura of diplomatic grace, artistic balance, and ethical charm around Libra today. You naturally create bridge-building consensus.",
                'career' => "Joint partnerships, client negotiations, and creative collaborations prosper. Your refined mediation dissolves conflicting interests effortlessly.",
                'love' => "Romance flourishes through shared aesthetic experiences, romantic date nights, and mutual appreciation of each other's individuality.",
                'money' => "Equilibrium in financial transactions. Favorable planetary energy for entering equitable contracts or investing in artistic endeavors.",
                'health' => "Maintain kidney and lower-back health through regular hydration, gentle stretching, and avoiding prolonged sedentary posture.",
                'travel' => "Cultural trips to art exhibitions, scenic botanical gardens, or peaceful couples getaways are deeply fulfilling.",
            ],
            'hi' => [
                'base' => "शुक्र का प्रभाव तुला राशि के जातकों के जीवन में सामंजस्य, सौंदर्य और सौहार्द का विस्तार करेगा। आपकी कूटनीतिक कुशलता विवादों को सुलझाएगी।",
                'career' => "साझेदारी के व्यवसाय और संयुक्त परियोजनाओं में उल्लेखनीय प्रगति होगी। सहयोगियों के बीच संतुलन बनाए रखने में आपकी भूमिका सराहनीय रहेगी।",
                'love' => "दांपत्य व प्रेम जीवन में मधुरता और परस्पर आकर्षण बढ़ेगा। साथी के साथ कलात्मक या मनोरंजक गतिविधियों में समय बिताना सुखद रहेगा।",
                'money' => "आय और व्यय के मध्य अच्छा संतुलन बना रहेगा। न्यायसंगत समझौतों और साझेदारी के माध्यम से आर्थिक लाभ के योग हैं।",
                'health' => "जल का पर्याप्त सेवन करें और कमर तथा गुर्दे के स्वास्थ्य का ध्यान रखें। योग और हल्के व्यायाम से लचीलापन बनाए रखें।",
                'travel' => "सांस्कृतिक कार्यक्रमों, दर्शनीय पर्यटन स्थलों या कला प्रदर्शनियों की यात्रा मन को प्रफुल्लित करेगी।",
            ],
        ],
        'Scorpio' => [
            'name_hi' => 'वृश्चिक',
            'lord_en' => 'Mars',
            'lord_hi' => 'मंगल',
            'element' => 'Water',
            'colors_en' => ['Deep Maroon', 'Dark Crimson', 'Rust Copper', 'Obsidian'],
            'colors_hi' => ['गहरा मैरून', 'कत्थई', 'ताम्र', 'गहरा लाल'],
            'numbers' => ['9', '18', '27', '8'],
            'times' => ['06:30 AM', '11:00 AM', '04:00 PM', '08:45 PM'],
            'moods_en' => ['Intense', 'Focused', 'Strategic', 'Perceptive'],
            'moods_hi' => ['एकाग्र', 'रणनीतिक', 'गंभीर', 'अंतर्मुखी'],
            'en' => [
                'base' => "Mars empowers Scorpio with unshakeable willpower, laser focus, and deep intuitive insight today. You see beneath surfaces and make transformative progress.",
                'career' => "Mastery over complex investigations, strategic research, and confidential problem-solving. Your determination clears long-standing roadblocks.",
                'love' => "Profound emotional honesty fosters unbreakable intimacy. Superficiality drops away, revealing deep loyalty and mutual devotion.",
                'money' => "Fortunate trends in insurance matters, joint assets, tax planning, or recovering delayed dues. Rely on sharp due diligence.",
                'health' => "Support regenerative wellness through detoxifying hydration, deep breathing, and releasing lingering resentments through meditation.",
                'travel' => "Quiet journeys to spiritual sanctuaries, mountain hideaways, or private retreats facilitate emotional renewal.",
            ],
            'hi' => [
                'base' => "मंगल के प्रभाव से वृश्चिक राशि के जातकों में अदम्य इच्छाशक्ति और गहन एकाग्रता रहेगी। गूढ़ विषयों और जटिल परिस्थितियों को सुलझाने में सफलता मिलेगी।",
                'career' => "शोध, अन्वेषण और रणनीतिक योजनाओं में शानदार सफलता के योग हैं। अपनी योजनाओं को गुप्त रखते हुए आगे बढ़ना लाभप्रद सिद्ध होगा।",
                'love' => "संबंधों में सत्यनिष्ठा और प्रगाढ़ आत्मीयता का अनुभव होगा। साथी के प्रति आपकी वफादारी रिश्तों को अटूट विश्वास प्रदान करेगी।",
                'money' => "संयुक्त धन, पैतृक संपत्ति या पुरानी देनदारियों की वसूली में प्रगति होगी। वित्तीय मामलों में आपकी अंतर्दृष्टि लाभकारी रहेगी।",
                'health' => "मानसिक और शारीरिक ऊर्जा को संतुलित रखें। ध्यान और प्राणायाम से नकारात्मक विचारों का निष्कासन करें और पर्याप्त विश्राम लें।",
                'travel' => "एकांत, शांत प्राकृतिक स्थलों या आध्यात्मिक साधना केंद्रों की यात्रा से मानसिक शांति और नई ऊर्जा मिलेगी।",
            ],
        ],
        'Sagittarius' => [
            'name_hi' => 'धनु',
            'lord_en' => 'Jupiter',
            'lord_hi' => 'बृहस्पति',
            'element' => 'Fire',
            'colors_en' => ['Bright Yellow', 'Saffron', 'Royal Purple', 'Golden Amber'],
            'colors_hi' => ['पीला', 'केसरिया', 'बैंगनी', 'सुनहरा'],
            'numbers' => ['3', '12', '21', '30'],
            'times' => ['07:45 AM', '12:30 PM', '03:30 PM', '07:15 PM'],
            'moods_en' => ['Optimistic', 'Philosophical', 'Expansive', 'Inspiring'],
            'moods_hi' => ['आशावादी', 'दार्शनिक', 'उत्साही', 'प्रेरक'],
            'en' => [
                'base' => "Jupiter expands your horizon with noble wisdom, optimistic vision, and moral clarity today. Mentorship and educational endeavors flourish under your care.",
                'career' => "Strategic long-term planning, publishing, legal affairs, and cross-cultural initiatives find favorable momentum. Your visionary perspective guides peers.",
                'love' => "Joyful camaraderie and shared philosophical discussions enliven romance. Mutual respect for each other's independence strengthens bonds.",
                'money' => "Favorable indications in higher learning investments, advisory roles, and expanding wealth foundations. Maintain disciplined budget oversight.",
                'health' => "Outdoor hiking, brisk walks, and hip flexibility exercises support physical vitality. Avoid overeating sugary or heavy foods.",
                'travel' => "Long-distance journeys, temple pilgrimages, or university visits expand your understanding and bring joyous spiritual solace.",
            ],
            'hi' => [
                'base' => "देवगुरु बृहस्पति की कृपा से धनु राशि के जातकों के ज्ञान, धर्म और आशावाद में वृद्धि होगी। उच्च आदर्शों और सकारात्मक सोच से मार्ग प्रशस्त होगा।",
                'career' => "शिक्षा, परामर्श, विधि और अंतरराष्ट्रीय संपर्कों से जुड़े कार्यों में विशेष सफलता मिलेगी। आपका दूरदर्शी दृष्टिकोण सराहा जाएगा।",
                'love' => "प्रेम व दांपत्य जीवन में परस्पर सम्मान और स्वतंत्रता की भावना संबंधों को सुंदर बनाएगी। साथी के साथ दार्शनिक या प्रेरक विचार साझा करेंगे।",
                'money' => "ज्ञान और अनुभव के बल पर धनोपार्जन के अवसर बनेंगे। धार्मिक या परोपकारी कार्यों पर व्यय से आत्मिक संतोष मिलेगा।",
                'health' => "स्वास्थ्य उत्तम रहेगा। जांघों और कमर के लचीलेपन के लिए व्यायाम करें; अत्यधिक गरिष्ठ भोजन से परहेज रखना हितकर रहेगा।",
                'travel' => "तीर्थाटन, उच्च शिक्षा संस्थानों या दूरस्थ रमणीय स्थलों की यात्रा ज्ञानवर्धक और अत्यंत आनंददायक रहेगी।",
            ],
        ],
        'Capricorn' => [
            'name_hi' => 'मकर',
            'lord_en' => 'Saturn',
            'lord_hi' => 'शनि',
            'element' => 'Earth',
            'colors_en' => ['Charcoal Grey', 'Dark Navy', 'Indigo', 'Steel Blue'],
            'colors_hi' => ['गहरा नीला', 'जामुनी', 'श्याम', 'स्लेटी'],
            'numbers' => ['8', '17', '26', '4'],
            'times' => ['08:00 AM', '01:30 PM', '05:00 PM', '08:15 PM'],
            'moods_en' => ['Disciplined', 'Patient', 'Steadfast', 'Pragmatic'],
            'moods_hi' => ['अनुशासित', 'धैर्यवान', 'कर्मठ', 'दृढ़'],
            'en' => [
                'base' => "Saturn fortifies Capricorn with unflinching discipline, pragmatic endurance, and structural mastery today. Steady steps build your lasting reputation.",
                'career' => "Your executive competence and reliable work ethic gain recognition from superiors. An ideal time to organize long-term structural milestones.",
                'love' => "Quiet, rock-solid dependability reassures loved ones. Sharing practical responsibilities cements authentic emotional security.",
                'money' => "Conservative financial discipline proves rewarding. Focus on debt consolidation, capital asset preservation, and durable value acquisitions.",
                'health' => "Pay attention to bone, joint, and knee mobility. Gentle stretching, regular posture checks, and warming herbal teas maintain vigor.",
                'travel' => "Purpose-driven corporate travel, architectural heritage tours, or visits to quiet mountain elevations proceed productively.",
            ],
            'hi' => [
                'base' => "शनि देव की कृपा से मकर राशि के जातकों में कर्तव्यनिष्ठा, धैर्य और कर्मठता की प्रधानता रहेगी। आपकी निरंतर मेहनत ठोस परिणाम लाएगी।",
                'career' => "व्यावसायिक जीवन में आपकी विश्वसनीयता और प्रबंधकीय क्षमता का लोहा माना जाएगा। वरिष्ठों से महत्वपूर्ण जिम्मेदारियां प्राप्त हो सकती हैं।",
                'love' => "रिश्तों में स्थिरता और गंभीरता रहेगी। शब्दों से अधिक आपका सहयोग और समर्पण साथी के हृदय में आदर भाव बढ़ाएगा।",
                'money' => "दीर्घकालिक वित्तीय नियोजन और धन संचय में सफलता मिलेगी। अनावश्यक खर्चों पर कड़ा नियंत्रण रखने में सफल होंगे।",
                'health' => "हड्डियों और घुटनों के स्वास्थ्य पर ध्यान दें। नियमित रूप से स्ट्रेचिंग करें और वात कारक भोजन से परहेज करें।",
                'travel' => "व्यापारिक अनुबंधों, ऐतिहासिक स्मारकों या शांत पर्वतीय क्षेत्रों की यात्रा योजनाबद्ध रूप से सफल रहेगी।",
            ],
        ],
        'Aquarius' => [
            'name_hi' => 'कुम्भ',
            'lord_en' => 'Saturn',
            'lord_hi' => 'शनि',
            'element' => 'Air',
            'colors_en' => ['Electric Blue', 'Cyan', 'Violet', 'Steel Grey'],
            'colors_hi' => ['नीला', 'फिरोजी', 'बैंगनी', 'आसमानी'],
            'numbers' => ['4', '13', '22', '31'],
            'times' => ['09:15 AM', '02:15 PM', '06:00 PM', '09:45 PM'],
            'moods_en' => ['Innovative', 'Visionary', 'Progressive', 'Altruistic'],
            'moods_hi' => ['प्रगतिशील', 'दूरदर्शी', 'नवाचारी', 'परोपकारी'],
            'en' => [
                'base' => "Saturn and Rahu inspire unconventional thinking, progressive humanitarian ideals, and tech innovation for Aquarius today. Your original insights inspire peers.",
                'career' => "Collaborative team ventures and forward-thinking digital projects gain traction. Your knack for out-of-the-box solutions breaks current deadlocks.",
                'love' => "Friendship forms the solid cornerstone of romance. Engaging conversations on social causes and future aspirations unite hearts.",
                'money' => "Promising developments in community networks, modern tech ventures, or collective savings funds. Guard against speculative eccentricity.",
                'health' => "Support healthy blood circulation and ankle flexibility with light cardio. Keep nervous tension low through calming breathing rhythms.",
                'travel' => "Visits to innovation expos, social forums, vibrant scientific summits, or group retreats prove intellectually uplifting.",
            ],
            'hi' => [
                'base' => "शनि व राहु की ऊर्जा से आज कुम्भ राशि के जातक नए विचारों, वैज्ञानिक दृष्टिकोण और समाजोपयोगी योजनाओं में अग्रणी रहेंगे।",
                'career' => "सामूहिक परियोजनाओं, तकनीकी विकास और नवाचार में आपकी मौलिकता चमकेगी। सहकर्मियों को प्रेरित करने में सफल रहेंगे।",
                'love' => "प्रेम संबंधों में गहरी मित्रता और वैचारिक स्वतंत्रता का सामंजस्य रहेगा। भविष्य की योजनाओं पर साथ मिलकर विचार करेंगे।",
                'money' => "आधुनिक माध्यमों, सोशल नेटवर्क या तकनीकी उपक्रमों से आर्थिक लाभ के अवसर मिलेंगे। वित्तीय निर्णयों में व्यावहारिकता बनाए रखें।",
                'health' => "रक्त संचार और टखनों का ध्यान रखें। अत्यधिक मानसिक सोच से बचने के लिए खुली हवा में टहलें और प्राणायाम करें।",
                'travel' => "तकनीकी सम्मेलनों, सामाजिक सम्मेलनों या मित्रों के समूह के साथ की गई यात्राएं प्रेरणादायक सिद्ध होंगी।",
            ],
        ],
        'Pisces' => [
            'name_hi' => 'मीन',
            'lord_en' => 'Jupiter',
            'lord_hi' => 'बृहस्पति',
            'element' => 'Water',
            'colors_en' => ['Sea Green', 'Pale Yellow', 'Lavender', 'Aquamarine'],
            'colors_hi' => ['समुद्री हरा', 'हल्का पीला', 'लैवेंडर', 'धवल'],
            'numbers' => ['3', '7', '12', '21'],
            'times' => ['06:15 AM', '10:45 AM', '02:45 PM', '07:00 PM'],
            'moods_en' => ['Compassionate', 'Intuitive', 'Soulful', 'Artistic'],
            'moods_hi' => ['करुणामयी', 'अंतर्मुखी', 'भावुक', 'शांत'],
            'en' => [
                'base' => "Jupiter enriches Pisces with poetic sensitivity, empathetic intuition, and tranquil spiritual devotion today. Trust your artistic and moral inclinations.",
                'career' => "Creative artistry, counseling, healing, and philanthropic initiatives thrive. Your compassionate demeanor softens stressful environments.",
                'love' => "Soulful empathy and unconditional tenderness permeate romantic life. Poetic expressions of gratitude foster profound emotional resonance.",
                'money' => "Intuitive financial judgments are reliable, but double-check factual fine prints. Acts of charity and selfless service bring auspicious fortune.",
                'health' => "Pamper your feet and maintain warm hydration. Soothing baths, gentle yoga, and quiet prayer replenish spiritual vigor.",
                'travel' => "Pilgrimages to sacred water shrines, secluded coastal shores, or peaceful spiritual ashrams restore inner tranquility.",
            ],
            'hi' => [
                'base' => "देवगुरु बृहस्पति की कृपा से मीन राशि के जातकों में करुणा, कल्पनाशीलता और आध्यात्मिक शांति का वास रहेगा। आपकी संवेदनशीलता सकारात्मक परिणाम देगी।",
                'career' => "कला, संगीत, परामर्श, चिकित्सा और सेवाभावी क्षेत्रों से जुड़े लोगों को उत्तम सफलता प्राप्त होगी। आपकी सहानुभूति सबका मन मोह लेगी।",
                'love' => "प्रेम व दांपत्य जीवन में आत्मिक जुड़ाव और समर्पण का भाव रहेगा। साथी की भावनाओं को बिना कहे समझना संबंधों को दिव्य बनाएगा।",
                'money' => "वित्तीय मामलों में सहज ज्ञान सहायक रहेगा। परोपकार और दान-पुण्य से मानसिक संतोष के साथ-साथ ईश्वरीय कृपा प्राप्त होगी।",
                'health' => "पैरों की देखभाल करें और विश्राम पर ध्यान दें। ध्यान, मधुर संगीत और सकारात्मक चिंतन से मानसिक शांति बनी रहेगी।",
                'travel' => "तीर्थ स्थलों, पवित्र नदियों, समुद्री तटों या शांत साधना आश्रमों की यात्रा मन को असीम शांति और आत्मबल देगी।",
            ],
        ],
    ];

    /**
     * Daily Planetary Transit Influences (Vedic Vara)
     */
    protected const DAY_TRANSITS = [
        'Sunday' => [
            'lord_en' => 'Sun', 'lord_hi' => 'सूर्य',
            'en_tag' => "Surya's radiant solar vitality encourages executive clarity and honoring soul aspirations.",
            'hi_tag' => "सूर्य देव का प्रभाव आत्मबल, तेज और प्रशासनिक स्पष्टता को प्रोत्साहित करता है।",
        ],
        'Monday' => [
            'lord_en' => 'Moon', 'lord_hi' => 'चंद्रमा',
            'en_tag' => "Chandra's gentle lunar grace fosters emotional balance, mental receptivity, and domestic joy.",
            'hi_tag' => "चंद्र देव की सौम्य कृपा से मानसिक शांति, संवेदनशीलता और पारिवारिक सुख में वृद्धि होती है।",
        ],
        'Tuesday' => [
            'lord_en' => 'Mars', 'lord_hi' => 'मंगल',
            'en_tag' => "Mangala's dynamic martial impulse provides decisive courage, energetic drive, and physical vigor.",
            'hi_tag' => "मंगल देव का प्रभाव अदम्य साहस, पराक्रम और त्वरित निर्णय लेने की क्षमता प्रदान करता है।",
        ],
        'Wednesday' => [
            'lord_en' => 'Mercury', 'lord_hi' => 'बुध',
            'en_tag' => "Budha's swift intellectual currents facilitate commercial success, sharp negotiations, and witty communication.",
            'hi_tag' => "बुध देव की कृपा से व्यापारिक कुशलता, तीक्ष्ण बुद्धि और संवाद में सफलता प्राप्त होती है।",
        ],
        'Thursday' => [
            'lord_en' => 'Jupiter', 'lord_hi' => 'बृहस्पति',
            'en_tag' => "Guru's auspicious wisdom bestows expansive prosperity, ethical guidance, and spiritual merit.",
            'hi_tag' => "देवगुरु बृहस्पति का आशीर्वाद ज्ञान, समृद्धि, धर्म और शुभ संकल्पों को सिद्ध करता है।",
        ],
        'Friday' => [
            'lord_en' => 'Venus', 'lord_hi' => 'शुक्र',
            'en_tag' => "Shukra's refined artistic elegance enhances romantic harmony, material comforts, and social charm.",
            'hi_tag' => "शुक्र देव की कृपा से कला, सौंदर्य, दांपत्य सुख और भौतिक सुख-सुविधाओं में वृद्धि होती है।",
        ],
        'Saturday' => [
            'lord_en' => 'Saturn', 'lord_hi' => 'शनि',
            'en_tag' => "Shani's structured discipline rewards patient perseverance, duty, and long-term organizational foundations.",
            'hi_tag' => "शनि देव का प्रभाव कर्मनिष्ठा, अनुशासन, धैर्य और स्थायी नींव को सुदृढ़ करता है।",
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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
            foreach (self::SIGN_PROFILES as $sign => $profile) {
                foreach ($languages as $lang) {
                    $records[] = $this->buildDailyHoroscope($sign, $profile, $lang, $day);
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

            foreach (self::SIGN_PROFILES as $sign => $profile) {
                foreach ($languages as $lang) {
                    $exists = DB::table('horoscopes')
                        ->where('horoscope_type', $type)
                        ->where('zodiac_sign', $sign)
                        ->where('language', $lang)
                        ->whereDate('start_date', $periodStart->toDateString())
                        ->exists();

                    if (!$exists) {
                        $records[] = $this->buildPeriodicHoroscope($sign, $profile, $type, $lang, $periodStart, $periodEnd);
                    }
                }
            }
        }

        // Insert in chunks of 50 for optimal performance
        foreach (array_chunk($records, 50) as $chunk) {
            DB::table('horoscopes')->insert($chunk);
        }

        // Invalidate cached horoscope responses
        DailyHoroscopeService::invalidateCache();
    }

    /**
     * Build an authentic, sign-specific, date-specific Daily Horoscope record.
     */
    protected function buildDailyHoroscope(string $sign, array $profile, string $lang, Carbon $date): array
    {
        $dayName = $date->format('l'); // e.g. Sunday, Monday, Tuesday...
        $transit = self::DAY_TRANSITS[$dayName] ?? self::DAY_TRANSITS['Sunday'];

        // Pick distinct lucky factors per sign + date
        $colorCount = count($profile['colors_en']);
        $numCount = count($profile['numbers']);
        $timeCount = count($profile['times']);
        $moodCount = count($profile['moods_en']);

        $dayIndex = (int) $date->format('d');
        $colorIdx = ($dayIndex + ord($sign[0])) % $colorCount;
        $numIdx = ($dayIndex + strlen($sign)) % $numCount;
        $timeIdx = ($dayIndex + ord($sign[1] ?? 'A')) % $timeCount;
        $moodIdx = ($dayIndex + $date->dayOfWeek) % $moodCount;

        if ($lang === 'en') {
            $context = "{$profile['en']['base']} {$transit['en_tag']}";
            $career = "{$profile['en']['career']} Today's celestial alignment under {$transit['lord_en']} highlights disciplined execution and professional integrity.";
            $love = "{$profile['en']['love']} Open-hearted interactions strengthen mutual trust today.";
            $money = "{$profile['en']['money']} Auspicious planetary indicators encourage balanced resource allocation.";
            $health = "{$profile['en']['health']} Aligning with daily rhythms promotes holistic vitality.";
            $travel = "{$profile['en']['travel']} Journeys undertaken today foster productive outcomes.";
            $luckyColor = $profile['colors_en'][$colorIdx];
            $mood = $profile['moods_en'][$moodIdx];
            $tag = "Daily Horoscope - {$dayName}";
        } else {
            $context = "{$profile['hi']['base']} {$transit['hi_tag']}";
            $career = "{$profile['hi']['career']} आज {$transit['lord_hi']} देव के अनुकूल प्रभाव से कार्यक्षेत्र में कर्तव्यनिष्ठा और सफलता के योग बनेंगे।";
            $love = "{$profile['hi']['love']} सौहार्दपूर्ण संवाद से रिश्तों में नई प्रगाढ़ता आएगी।";
            $money = "{$profile['hi']['money']} ग्रहों की शुभ स्थिति आर्थिक संतुलन और विवेकपूर्ण निर्णयों का समर्थन करती है।";
            $health = "{$profile['hi']['health']} नियमित दिनचर्या और योग से शारीरिक व मानसिक ऊर्जा संतुलित रहेगी।";
            $travel = "{$profile['hi']['travel']} आज की गई यात्राएं मनोरंजक और उद्देश्यपूर्ण सिद्ध होंगी।";
            $luckyColor = $profile['colors_hi'][$colorIdx];
            $mood = $profile['moods_hi'][$moodIdx];
            $tag = "दैनिक राशिफल - {$dayName}";
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
            'lucky_number' => $profile['numbers'][$numIdx],
            'lucky_color' => $luckyColor,
            'mood' => $mood,
            'lucky_time' => $profile['times'][$timeIdx],
            'start_date' => $date->format('Y-m-d 00:00:00'),
            'end_date' => $date->format('Y-m-d 23:59:59'),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Build an authentic Periodic Horoscope record (weekly, monthly, yearly).
     */
    protected function buildPeriodicHoroscope(string $sign, array $profile, string $type, string $lang, Carbon $startDate, Carbon $endDate): array
    {
        $signHi = $profile['name_hi'];
        $typeCap = ucfirst($type);

        if ($lang === 'en') {
            $tag = "{$typeCap} Horoscope";
            $context = "This {$type} period brings key developmental phases for {$sign} under {$profile['lord_en']}'s guidance. Focus on steady progress, skill refinement, and emotional resilience.";
            $career = "{$profile['en']['career']} Long-term endeavors reach meaningful milestones through systematic effort.";
            $love = "{$profile['en']['love']} Patience and mutual understanding foster enduring harmony.";
            $money = "{$profile['en']['money']} Disciplined financial planning builds reliable reserves for the future.";
            $health = "{$profile['en']['health']} Consistent lifestyle habits and stress management sustain high wellbeing.";
            $travel = "{$profile['en']['travel']} Purposeful travel during this period offers enriching experiences.";
            $luckyColor = $profile['colors_en'][0];
            $mood = $profile['moods_en'][0];
        } else {
            $typeHi = match ($type) {
                'weekly' => 'साप्ताहिक',
                'monthly' => 'मासिक',
                'yearly' => 'वार्षिक',
                default => $typeCap,
            };
            $tag = "{$typeHi} राशिफल";
            $context = "यह {$typeHi} अवधि {$signHi} राशि के जातकों के लिए राशि स्वामी {$profile['lord_hi']} के प्रभाव से महत्वपूर्ण विकास और प्रगति का मार्ग प्रशस्त करेगी।";
            $career = "{$profile['hi']['career']} निरंतर परिश्रम और समर्पण से दीर्घकालिक परियोजनाओं में सफलता प्राप्त होगी।";
            $love = "{$profile['hi']['love']} परस्पर विश्वास और समझ से पारिवारिक और दांपत्य जीवन सुखमय रहेगा।";
            $money = "{$profile['hi']['money']} योजनाबद्ध वित्तीय प्रबंधन से भविष्य के लिए स्थायी संपत्ति का निर्माण होगा।";
            $health = "{$profile['hi']['health']} अनुशासित जीवनशैली और समय पर विश्राम से उत्तम स्वास्थ्य बना रहेगा।";
            $travel = "{$profile['hi']['travel']} इस समयावधि में की गई यात्राएं फलप्रद और सुखद सिद्ध होंगी।";
            $luckyColor = $profile['colors_hi'][0];
            $mood = $profile['moods_hi'][0];
        }

        return [
            'image' => null,
            'zodiac_sign' => $sign,
            'horoscope_type' => $type,
            'language' => $lang,
            'zodiac' => strtolower($sign),
            'tag' => $tag,
            'context' => $context,
            'love' => $love,
            'career' => $career,
            'money' => $money,
            'health' => $health,
            'travel' => $travel,
            'lucky_number' => $profile['numbers'][0],
            'lucky_color' => $luckyColor,
            'mood' => $mood,
            'lucky_time' => $profile['times'][0],
            'start_date' => $startDate->format('Y-m-d H:i:s'),
            'end_date' => $endDate->format('Y-m-d H:i:s'),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
