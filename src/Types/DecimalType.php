<?php

declare(strict_types=1);

namespace prochst\bsOrm\Types;

use NumberFormatter;

/**
 * Typ pro desetinná čísla s pevnou přesností
 * 
 * DecimalType se používá pro přesná desetinná čísla kde záleží na každé číslici:
 * - Ceny (bez měnového formátování)
 * - Procenta
 * - Ratings, hodnocení
 * - GPS souřadnice
 * - Vědecká data
 * 
 * Pro měny použijte raději CurrencyType který zahrnuje formátování s měnovým symbolem.
 * 
 * SQL mapování:
 * - DECIMAL(p,s) nebo NUMERIC(p,s)
 * - p = precision (celkový počet číslic)
 * - s = scale (počet desetinných míst)
 * 
 * ⚠️ DŮLEŽITÉ: Ukládá se jako string pro zachování přesnosti!
 * 
 * @package prochst\bsOrm\Types
 * @author  Your Name
 * @version 1.0.0
 * 
 * @example
 * ```php
 * // Cena produktu (2 desetinná místa)
 * #[Column(type: new DecimalType(precision: 10, scale: 2))]
 * private float $price;
 * 
 * // Procenta (4 desetinná místa pro přesnost)
 * #[Column(type: new DecimalType(precision: 5, scale: 4))]
 * private float $percentage;
 * 
 * // GPS latitude (-90.0000 až +90.0000)
 * #[Column(type: new DecimalType(precision: 10, scale: 6))]
 * private float $latitude;
 * 
 * // Rating (0.0 až 5.0)
 * #[Column(type: new DecimalType(precision: 3, scale: 1))]
 * private float $rating;
 * ```
 */
class DecimalType implements TypeInterface
{
    /**
     * Vytvoří nový DecimalType
     * 
     * @param int $precision Celkový počet číslic (včetně desetinných)
     * @param int $scale Počet desetinných míst
     * 
     * @example
     * ```php
     * // Pro ceny: 99999999.99 (8 číslic před, 2 za desetinnou čárkou)
     * new DecimalType(precision: 10, scale: 2)
     * 
     * // Pro procenta: 100.0000
     * new DecimalType(precision: 7, scale: 4)
     * 
     * // Pro GPS: -180.000000 až +180.000000
     * new DecimalType(precision: 10, scale: 6)
     * ```
     */
    public function __construct(
        private int $precision = 10,
        private int $scale = 2,
    ) {
    }
    
    /**
     * {@inheritdoc}
     * 
     * ⚠️ Ukládá jako STRING pro zachování přesnosti!
     * Float má omezenou přesnost a může způsobit zaokrouhlovací chyby.
     * 
     * @example
     * ```php
     * $type = new DecimalType(precision: 10, scale: 2);
     * echo $type->toDatabase(1234.567);    // "1234.57" (string!)
     * echo $type->toDatabase(99.9);        // "99.90"
     * echo $type->toDatabase(null);        // null
     * ```
     */
    public function toDatabase(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        
        // Ukládáme jako string pro zachování přesnosti
        return number_format((float) $value, $this->scale, '.', '');
    }
    
    /**
     * {@inheritdoc}
     * 
     * @example
     * ```php
     * $type = new DecimalType(precision: 10, scale: 2);
     * echo $type->fromDatabase('1234.56');  // 1234.56 (float)
     * echo $type->fromDatabase(null);       // null
     * ```
     */
    public function fromDatabase(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }
        
        return (float) $value;
    }
    
    /**
     * {@inheritdoc}
     * 
     * @example
     * ```php
     * $type = new DecimalType(precision: 10, scale: 2);
     * echo $type->getSqlType('mysql');   // "DECIMAL(10, 2)"
     * echo $type->getSqlType('pgsql');   // "NUMERIC(10, 2)"
     * echo $type->getSqlType('sqlite');  // "REAL"
     * ```
     */
    public function getSqlType(string $driver): string
    {
        return match($driver) {
            'pgsql' => "NUMERIC({$this->precision}, {$this->scale})",
            'mysql' => "DECIMAL({$this->precision}, {$this->scale})",
            'sqlite' => 'REAL',
            default => "DECIMAL({$this->precision}, {$this->scale})",
        };
    }
    
    /**
     * {@inheritdoc}
     * 
     * @example
     * ```php
     * $type = new DecimalType(precision: 5, scale: 2);
     * 
     * $errors = $type->validate(123.45);
     * // [] - OK
     * 
     * $errors = $type->validate('abc');
     * // ["Hodnota musí být číslo"]
     * ```
     */
    public function validate(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        
        if (!is_numeric($value)) {
            return ['Hodnota musí být číslo'];
        }
        
        return [];
    }
    
    /**
     * Naformátuje desetinné číslo podle locale
     * 
     * Respektuje:
     * - Desetinný oddělovač (čárka vs tečka)
     * - Tisícové oddělovače (mezera, čárka, tečka)
     * - Počet desetinných míst (scale)
     * 
     * @param mixed $value Hodnota k formátování
     * @param string|null $locale Locale
     * 
     * @return string Naformátované číslo
     * 
     * @example
     * ```php
     * $type = new DecimalType(precision: 10, scale: 2);
     * 
     * // České formátování
     * echo $type->format(1234.567, 'cs_CZ');
     * // "1 234,57"
     * 
     * // Americké formátování
     * echo $type->format(1234.567, 'en_US');
     * // "1,234.57"
     * 
     * // Procenta
     * $percentType = new DecimalType(precision: 5, scale: 2);
     * echo $percentType->format(99.99, 'cs_CZ') . ' %';
     * // "99,99 %"
     * ```
     */
    public function format(mixed $value, ?string $locale = null): string
    {
        if ($value === null) {
            return '';
        }
        
        $locale = LocaleManager::resolveLocale($locale);
        
        if (extension_loaded('intl')) {
            $formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);
            $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $this->scale);
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $this->scale);
            return $formatter->format((float) $value);
        }
        
        // Fallback
        $decimalSeparator = $locale === 'en_US' ? '.' : ',';
        $thousandsSeparator = $locale === 'en_US' ? ',' : ' ';
        
        return number_format((float) $value, $this->scale, $decimalSeparator, $thousandsSeparator);
    }
    
    /**
     * Parsuje lokalizované desetinné číslo
     * 
     * Umí zpracovat:
     * - "1 234,56" (české)
     * - "1,234.56" (americké)
     * - "1.234,56" (německé)
     * 
     * @param string $value Lokalizovaný string
     * @param string|null $locale Locale
     * 
     * @return float|null Číslo nebo null
     * 
     * @example
     * ```php
     * $type = new DecimalType(precision: 10, scale: 2);
     * 
     * $num = $type->parse('1 234,56', 'cs_CZ');
     * // 1234.56
     * 
     * $num = $type->parse('1,234.56', 'en_US');
     * // 1234.56
     * 
     * // Z formuláře
     * $price = $type->parse($_POST['price']);
     * ```
     */
    public function parse(string $value, ?string $locale = null): ?float
    {
        if ($value === '') {
            return null;
        }
        
        $locale = LocaleManager::resolveLocale($locale);
        
        if (extension_loaded('intl')) {
            $formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);
            $result = $formatter->parse($value, NumberFormatter::TYPE_DOUBLE);
            return $result !== false ? (float) $result : null;
        }
        
        // Fallback - normalizujeme formát
        if ($locale === 'en_US') {
            $cleaned = str_replace(',', '', $value); // Odstraníme tisícové oddělovače
        } else {
            $cleaned = str_replace([' ', ','], ['', '.'], $value); // Mezery pryč, čárka na tečku
        }
        
        return is_numeric($cleaned) ? (float) $cleaned : null;
    }
}