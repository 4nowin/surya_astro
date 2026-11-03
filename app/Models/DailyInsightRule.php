<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyInsightRule extends Model
{
    use HasFactory;

    protected $table = 'daily_insight_rules';

    public const CATEGORY_OVERALL = 'overall';
    public const CATEGORY_CAREER = 'career';
    public const CATEGORY_RELATIONSHIPS = 'relationships';
    public const CATEGORY_FINANCE = 'finance';
    public const CATEGORY_WELLBEING = 'wellbeing';
    public const CATEGORY_OPPORTUNITIES_CAUTIONS = 'opportunities_cautions';
    public const CATEGORY_GUIDANCE = 'guidance';

    public const CATEGORIES = [
        self::CATEGORY_OVERALL => [
            'name_en' => 'Overall Outlook',
            'name_hi' => 'समग्र दृष्टिकोण',
            'icon' => 'sparkles',
        ],
        self::CATEGORY_CAREER => [
            'name_en' => 'Career & Profession',
            'name_hi' => 'करियर और व्यवसाय',
            'icon' => 'briefcase',
        ],
        self::CATEGORY_RELATIONSHIPS => [
            'name_en' => 'Relationships & Personal Life',
            'name_hi' => 'संबंध और व्यक्तिगत जीवन',
            'icon' => 'heart',
        ],
        self::CATEGORY_FINANCE => [
            'name_en' => 'Finances & Wealth',
            'name_hi' => 'वित्त और धन लाभ',
            'icon' => 'banknotes',
        ],
        self::CATEGORY_WELLBEING => [
            'name_en' => 'Health & Wellbeing',
            'name_hi' => 'स्वास्थ्य और मानसिक शांति',
            'icon' => 'shield-check',
        ],
        self::CATEGORY_OPPORTUNITIES_CAUTIONS => [
            'name_en' => 'Opportunities & Cautions',
            'name_hi' => 'अवसर और सावधानियां',
            'icon' => 'exclamation-triangle',
        ],
        self::CATEGORY_GUIDANCE => [
            'name_en' => 'Practical Daily Guidance',
            'name_hi' => 'दैनिक व्यावहारिक मार्गदर्शन',
            'icon' => 'light-bulb',
        ],
    ];

    public const TONE_FAVOURABLE = 'favourable';
    public const TONE_MIXED = 'mixed';
    public const TONE_CAUTIOUS = 'cautious';

    public const TONES = [
        self::TONE_FAVOURABLE => ['label_en' => 'Favourable', 'label_hi' => 'अनुकूल', 'color' => '#10B981'],
        self::TONE_MIXED => ['label_en' => 'Mixed / Balanced', 'label_hi' => 'मिश्रित', 'color' => '#F59E0B'],
        self::TONE_CAUTIOUS => ['label_en' => 'Cautious', 'label_hi' => 'सतर्क रहें', 'color' => '#EF4444'],
    ];

    public const PLANETS = [
        'sun' => ['name_en' => 'Sun', 'name_hi' => 'सूर्य'],
        'moon' => ['name_en' => 'Moon', 'name_hi' => 'चन्द्र'],
        'mars' => ['name_en' => 'Mars', 'name_hi' => 'मंगल'],
        'mercury' => ['name_en' => 'Mercury', 'name_hi' => 'बुध'],
        'jupiter' => ['name_en' => 'Jupiter', 'name_hi' => 'बृहस्पति'],
        'venus' => ['name_en' => 'Venus', 'name_hi' => 'शुक्र'],
        'saturn' => ['name_en' => 'Saturn', 'name_hi' => 'शनि'],
        'rahu' => ['name_en' => 'Rahu', 'name_hi' => 'राहु'],
        'ketu' => ['name_en' => 'Ketu', 'name_hi' => 'केतु'],
    ];

    protected $fillable = [
        'rule_identifier',
        'category',
        'planet',
        'house',
        'is_retrograde',
        'tone',
        'headline_en',
        'headline_hi',
        'interpretation_en',
        'interpretation_hi',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'house' => 'integer',
        'is_retrograde' => 'boolean',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeForPlanetHouse(Builder $query, string $planet, int $house): Builder
    {
        return $query->where('planet', strtolower($planet))
            ->where('house', $house);
    }

    public function getHeadline(string $locale = 'en'): string
    {
        return $locale === 'hi' && !empty($this->headline_hi) ? $this->headline_hi : $this->headline_en;
    }

    public function getInterpretation(string $locale = 'en'): string
    {
        return $locale === 'hi' && !empty($this->interpretation_hi) ? $this->interpretation_hi : $this->interpretation_en;
    }
}
