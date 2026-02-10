<?php

declare(strict_types=1);

namespace prochst\bsOrm\Types;

use NumberFormatter;

/**
 * Typ pro měnové hodnoty
 * 
 * CurrencyType je specializovaný typ pro ceny a finanční částky.
 * Na rozdíl od DecimalType zahrnuje:
 * - Měnový symbol (Kč, €, $, £, ...)
 * - Formátování podle měnových konvencí
 * - Pozici symbolu (před/za částkou)
 * - Správné zaokrouhlování
 * 
 * Ukládá se jako DECIMAL pro zachování přesnosti.
 * 
 * Podporované měny:
 * - CZK (Koruna česká)
 * - EUR (Euro)
 * - USD (Americký dolar)
 * - GBP (Britská libra)
 * - PLN (Polský zlotý)
 * - ... a další dle ISO 4217
 * 
 * @package prochst\bsOrm\Types
 * @author  Your Name
 * @version 1.0.0
 * 
 * @example
 * ```php
 * // Cena produktu v CZK
 * #[Column(
 *     label: 'Cena',
 *     type: new CurrencyType('CZK'),
 *     nullable: false
 * )]
 * private float $price;
 * 
 * // Částka v EUR
 * #[Column(
 *     label: 'Částka',
 *     type: new CurrencyType('EUR', precision: 10, scale: 2)
 * )]
 * private float $amount;
 * 
 * // Zůstatek na účtu v USD
 * #[Column(
 *     type: new CurrencyType('USD'),
 *     nullable: false,
 *     default: 0.00
 * )]
 * private float $balance;
 * 
 * // Použití
 * $product->setPrice(1234.50);
 * echo $product->getFormattedPrice(); // "1 234,50 Kč"
 * ```
 */
class CurrencyType implements TypeInterface
{
    /**
     * Vytvoří nový CurrencyType
     * 
     * @param string $currency ISO 4217 kód měny (CZK, EUR, USD, ...)
     * @param int $precision Celkový počet číslic
     * @param int $scale Počet desetinných míst
     * 
     * @example
     * ```php
     * // Česká koruna
     * new CurrencyType('CZK')
     * 
     * // Euro s větší přesností
     * new CurrencyType('EUR', precision: 12, scale: 2)
     * 
     * // Bitcoin (8 desetinných míst)
     * new CurrencyType('BTC', precision: 16, scale: 8)
     * ```
     */
    public function __construct(
        private string $currency = 'CZK',
        private int $precision = 10,
        private int $scale = 2,
    ) {
    }
    
    /**
     * {@inheritdoc}
     * 
     * Ukládá jako string pro zachování přesnosti (stejně jako DecimalType).
     * 
     * @example
     * ```php
     * $type = new CurrencyType('CZK');
     * 
     * echo $type->toDatabase(1234.567);
     * // "1234.57" (string, zaokrouhleno na 2 des. místa)
     * 
     * echo $type->toDatabase(null);
     * // null
     * ```
     */
    public function toDatabase(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        
        return number_format((float) $value, $this->scale, '.', '');
    }
    
    /**
     * {@inheritdoc}
     * 
     * @example
     * ```php
     * $type = new CurrencyType('CZK');
     * 
     * $price = $type->fromDatabase('1234.50');
     * // 1234.5 (float)
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
     * Používá DECIMAL jako DecimalType.
     * 
     * @example
     * ```php
     * $type = new CurrencyType('CZK', precision: 10, scale: 2);
     * 
     * echo $type->getSqlType('mysql');
     * // "DECIMAL(10, 2)"
     * 
     * echo $type->getSqlType('pgsql');
     * // "NUMERIC(10, 2)"
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
     * Validuje že hodnota je číslo a není záporná (pro ceny).
     * 
     * @example
     * ```php
     * $type = new CurrencyType('CZK');
     * 
     * $errors = $type->validate(1234.50);
     * // [] - OK
     * 
     * $errors = $type->validate(-100);
     * // ["Částka nemůže být záporná"]
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
        
        if ((float) $value < 0) {
            return ['Částka nemůže být záporná'];
        }
        
        return [];
    }
    
    /**
     * Naformátuje částku s měnovým symbolem podle locale
     * 
     * Používá PHP Intl extension pro profesionální formátování.
     * Respektuje:
     * - Pozici symbolu (před/za částkou)
     * - Desetinný oddělovač
     * - Tisícové oddělovače
     * - Mezery kolem symbolu
     * 
     * @param mixed $value Částka k formátování
     * @param string|null $locale Locale
     * 
     * @return string Naformátovaná částka s měnou
     * 
     * @example
     * ```php
     * $czk = new CurrencyType('CZK');
     * $eur = new CurrencyType('EUR');
     * $usd = new CurrencyType('USD');
     * 
     * // České formátování
     * echo $czk->format(1234.50, 'cs_CZ');
     * // "1 234,50 Kč"
     * 
     * echo $eur->format(1234.50, 'cs_CZ');
     * // "1 234,50 €"
     * 
     * // Americké formátování
     * echo $usd->format(1234.50, 'en_US');
     * // "$1,234.50"
     * 
     * echo $czk->format(1234.50, 'en_US');
     * // "CZK 1,234.50" (s Intl) nebo "CZK1,234.50" (fallback)
     * 
     * // Německé formátování
     * echo $eur->format(1234.50, 'de_DE');
     * // "1.234,50 €"
     * 
     * // V šabloně
     * <span class="price"><?= $currencyType->format($product->getPrice()) ?></span>
     * 
     * // V gridu
     * <td class="amount"><?= $currencyType->format($order->getTotal()) ?></td>
     * ```
     */
    public function format(mixed $value, ?string $locale = null): string
    {
        if ($value === null) {
            return '';
        }
        
        $locale = LocaleManager::resolveLocale($locale);
        
        if (extension_loaded('intl')) {
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            return $formatter->formatCurrency((float) $value, $this->currency);
        }
        
        // Fallback bez Intl
        $decimalSeparator = $locale === 'en_US' ? '.' : ',';
        $thousandsSeparator = $locale === 'en_US' ? ',' : ' ';
        
        $formatted = number_format((float) $value, $this->scale, $decimalSeparator, $thousandsSeparator);
        
        $currencySymbol = match($this->currency) {
            'CZK' => 'Kč',
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            'PLN' => 'zł',
            default => $this->currency,
        };
        
        // Pro CZK dáváme měnu za částku, pro ostatní před
        return match($this->currency) {
            'CZK', 'PLN' => "$formatted $currencySymbol",
            default => "$currencySymbol$formatted",
        };
    }
    
    /**
     * Parsuje částku s měnou zpět na float
     * 
     * Umí odstranit měnové symboly a parsovat různé formáty.
     * 
     * @param string $value String k parsování
     * @param string|null $locale Locale
     * 
     * @return float|null Částka nebo null
     * 
     * @example
     * ```php
     * $type = new CurrencyType('CZK');
     * 
     * // České formáty
     * $amount = $type->parse('1 234,50 Kč', 'cs_CZ');
     * // 1234.5
     * 
     * $amount = $type->parse('1234,50', 'cs_CZ');
     * // 1234.5
     * 
     * // Americké formáty
     * $amount = $type->parse('$1,234.50', 'en_US');
     * // 1234.5
     * 
     * $amount = $type->parse('1,234.50', 'en_US');
     * // 1234.5
     * 
     * // Prázdný string
     * $amount = $type->parse('', 'cs_CZ');
     * // null
     * 
     * // Z formuláře
     * $price = $currencyType->parse($_POST['price']);
     * $product->setPrice($price);
     * ```
     */
    public function parse(string $value, ?string $locale = null): ?float
    {
        if ($value === '') {
            return null;
        }
        
        $locale = LocaleManager::resolveLocale($locale);
        
        if (extension_loaded('intl')) {
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            $result = $formatter->parseCurrency($value, $currency);
            return $result !== false ? (float) $result : null;
        }
        
        // Fallback - odstraníme symboly měn a parsujeme
        $cleaned = preg_replace('/[^\d\s,.-]/', '', $value);
        
        if ($locale === 'en_US') {
            $cleaned = str_replace(',', '', $cleaned);
        } else {
            $cleaned = str_replace([' ', ','], ['', '.'], $cleaned);
        }
        
        return is_numeric($cleaned) ? (float) $cleaned : null;
    }
    
    /**
     * Vrátí ISO 4217 kód měny
     * 
     * @return string Kód měny (CZK, EUR, USD, ...)
     * 
     * @example
     * ```php
     * $type = new CurrencyType('EUR');
     * echo $type->getCurrency();
     * // "EUR"
     * 
     * // Pro podmíněné formátování
     * if ($currencyType->getCurrency() === 'CZK') {
     *     echo "Česká měna";
     * }
     * ```
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }
}