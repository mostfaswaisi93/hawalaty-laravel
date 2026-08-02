<?php

namespace App\Enums;

/**
 * The fourteen allergens that must be declared on food.
 *
 * Annex II of EU Regulation 1169/2011, which is the list the rest of the world's
 * food-labelling rules are either aligned with or a subset of. Fixed platform
 * data on purpose: a tenant inventing a fifteenth or renaming one would make its
 * declarations unreadable to anyone checking them, and the list is not the
 * business's to edit.
 *
 * Codex Alimentarius names a similar core set; where a jurisdiction adds to it
 * (sesame in the United States, for instance) the addition is already here.
 */
enum Allergen: string
{
    case Gluten = 'gluten';
    case Crustaceans = 'crustaceans';
    case Eggs = 'eggs';
    case Fish = 'fish';
    case Peanuts = 'peanuts';
    case Soybeans = 'soybeans';
    case Milk = 'milk';
    case Nuts = 'nuts';
    case Celery = 'celery';
    case Mustard = 'mustard';
    case Sesame = 'sesame';
    case Sulphites = 'sulphites';
    case Lupin = 'lupin';
    case Molluscs = 'molluscs';

    public function label(): string
    {
        return match ($this) {
            self::Gluten => __('الحبوب التي تحتوي غلوتين'),
            self::Crustaceans => __('القشريات'),
            self::Eggs => __('البيض'),
            self::Fish => __('الأسماك'),
            self::Peanuts => __('الفول السوداني'),
            self::Soybeans => __('الصويا'),
            self::Milk => __('الحليب'),
            self::Nuts => __('المكسرات'),
            self::Celery => __('الكرفس'),
            self::Mustard => __('الخردل'),
            self::Sesame => __('السمسم'),
            self::Sulphites => __('الكبريتيت'),
            self::Lupin => __('الترمس'),
            self::Molluscs => __('الرخويات'),
        };
    }

    /**
     * What the regulation actually covers, which is wider than the short name
     * suggests - "nuts" means a named list of tree nuts, not every nut.
     */
    public function note(): string
    {
        return match ($this) {
            self::Gluten => __('القمح والشعير والشوفان والجاودار والقمح المقشور ومنتجاتها.'),
            self::Crustaceans => __('الروبيان والسلطعون والكركند ومنتجاتها.'),
            self::Eggs => __('البيض ومنتجاته.'),
            self::Fish => __('الأسماك ومنتجاتها.'),
            self::Peanuts => __('الفول السوداني ومنتجاته.'),
            self::Soybeans => __('فول الصويا ومنتجاته.'),
            self::Milk => __('الحليب ومنتجاته بما فيها اللاكتوز.'),
            self::Nuts => __('اللوز والبندق والجوز والكاجو والفستق والمكاداميا والبقان والبرازيلي.'),
            self::Celery => __('الكرفس ومنتجاته.'),
            self::Mustard => __('الخردل ومنتجاته.'),
            self::Sesame => __('بذور السمسم ومنتجاتها.'),
            self::Sulphites => __('ثاني أكسيد الكبريت والكبريتيت بتركيز يفوق 10 ملغم/كغم.'),
            self::Lupin => __('الترمس ومنتجاته.'),
            self::Molluscs => __('الرخويات ومنتجاتها.'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Gluten => 'grain',
            self::Crustaceans => 'set_meal',
            self::Eggs => 'egg',
            self::Fish => 'phishing',
            self::Peanuts => 'nutrition',
            self::Soybeans => 'spa',
            self::Milk => 'water_full',
            self::Nuts => 'park',
            self::Celery => 'eco',
            self::Mustard => 'water_drop',
            self::Sesame => 'scatter_plot',
            self::Sulphites => 'science',
            self::Lupin => 'local_florist',
            self::Molluscs => 'waves',
        };
    }
}
