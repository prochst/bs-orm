<?php

declare(strict_types=1);

namespace prochst\bsOrm\Types;

use NumberFormatter;

/**
 * Typ pro celá čísla
 * 
 * IntegerType se používá pro:
 * - ID, primární klíče
 * - Počty, množství
 * - Věk, roky
 * - Skóre, body
 * - Indexy, pořadí
 * 
 * Podporuje:
 * - Signed/unsigned čísla
 * - Lokalizované formátování (tisícové oddělovače)
 * - Parsing lokalizovaných čísel
 * 
 * @package prochst\bsOrm\Types
 * @author  Your Name
 * @version 1.0.0
 * 
 * @example
 * ```php
 * // Primární klíč (unsigned)
 * #[Column(
 *     type: new IntegerType(unsigned: true),
 *     primaryKey: true,
 *     autoIncrement: true
 * )]
 * private ?int $id;
 * 
 * // Věk (musí být kladný)
 * #[Column(
 *     type: new IntegerType(unsigned: true),
 *     validators: [
 *         fn($v) => $v >= 0 && $v <= 150 ?: 'Neplatný věk'
 *     ]
 * )]
 * private int $age;
 * 
 * // Skóre (může být záporné)
 * #[Column(type: new IntegerType())]
 * private int $score;
 * 
 * // Počet kusů skladem
 * #[Column(type: new IntegerType(unsigned: true))]
 * private int $stockQuantity;
 * ```
 */
class IntegerType implements TypeInterface
{
    /**
     * Vytvoří nový IntegerType
     * 
     * @param bool $unsigned Je číslo bez znaménka (0 a kladná)?
     *                       MySQL podporuje UNSIGNED modifier přímo v SQL
     * 
     * @example
     * ```php
     * // Signed integer (může být i záporný)
     * new IntegerType()
     * 
     * // Unsigned integer (pouze 0 a kladná čísla)
     * new IntegerType(unsigned: true)
     * ```
     */
    public function __construct(
        private bool $unsigned = false,
    ) {
    }
    
    /**
     * {@inheritdoc}
     * 
     * @example
     * ```php
     * $type = new IntegerType();
     * echo $type->toDatabase(123);     // 123
     * echo $type->toDatabase('456');   // 456
     * echo $type->toDatabase(null);    // null
     * ```
     */
    public function toDatabase(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        
        return (int) $value;
    }
    
    /**
     * {@inheritdoc}
     * 
     * @example
     * ```php
     * $type = new IntegerType();
     * echo $type->fromDatabase(123);   // 123
     * echo $type->fromDatabase('456'); // 456
     * echo $type->fromDatabase(null);  // null
     * ```
     */
    public function fromDatabase(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        
        return (int) $value;
    }
    
    /**
     * {@inheritdoc}
     * 
     * MySQL podporuje UNSIGNED modifier, ostatní databáze použijí INTEGER
     * 
     * @example
     * ```php
     * $type = new IntegerType(unsigned: true);
     * echo $type->getSqlType('mysql');     // "INT UNSIGNED"
     * echo $type->getSqlType('pgsql');     // "INTEGER"
     * echo $type->getSqlType('sqlite');    // "INTEGER"
     * 
     * $type = new IntegerType();
     * echo $type->getSqlType('mysql');     // "INT"
     * ```
     */
    public function getSqlType(string $driver): string
    {
        $unsigned = $this->unsigned && $driver === 'mysql' ? ' UNSIGNED' : '';
        
        return match($driver) {
            'pgsql' => 'INTEGER',
            'mysql' => "INT{$unsigned}",
            'sqlite' => 'INTEGER',
            default => 'INTEGER',
        };
    }
    
    /**
     * {@inheritdoc}
     * 
     * Validuje:
     * - Typ hodnoty (musí být integer nebo numeric string)
     * - Unsigned constraint (pokud je unsigned, nesmí být záporné)
     * 
     * @example
     * ```php
     * $type = new IntegerType(unsigned: true);
     * 
     * $errors = $type->validate(123);
     * // [] - OK
     * 
     * $errors = $type->validate(-5);
     * // ["Hodnota musí být nezáporná"]
     * 
     * $errors = $type->validate('abc');
     * // ["Hodnota musí být integer"]
     * ```
     */
    public function validate(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        
        if (!is_int($value) && !is_numeric($value)) {
            return ['Hodnota musí být integer'];
        }
        
        $intValue = (int) $value;
        
        if ($this->unsigned && $intValue < 0) {
            return ['Hodnota musí být nezáporná'];
        }
        
        return [];
    }
    
    /**
     * Naformátuje číslo podle locale s tisícovými oddělovači
     * 
     * Používá PHP Intl extension pokud je dostupná, jinak fallback.
     * 
     * @param mixed $value Hodnota k formátování
     * @param string|null $locale Locale
     * 
     * @return string Naformátované číslo
     * 
     * @example
     * ```php
     * $type = new IntegerType();
     * 
     * // České formátování (mezery jako oddělovače)
     * echo $type->format(1234567, 'cs_CZ');
     * // "1 234 567"
     * 
     * // Americké formátování (čárky jako oddělovače)
     * echo $type->format(1234567, 'en_US');
     * // "1,234,567"
     * 
     * // Německé formátování (tečky jako oddělovače)
     * echo $type->format(1234567, 'de_DE');
     * // "1.234.567"
     * 
     * // V šabloně
     * <p>Počet zobrazení: <?= $type->format($views) ?></p>
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
            $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, 0);
            return $formatter->format((int) $value);
        }
        
        // Fallback bez Intl
        return number_format((int) $value, 0, ',', ' ');
    }
    
    /**
     * Parsuje lokalizované číslo zpět na integer
     * 
     * Umí zpracovat různé formáty tisícových oddělovačů:
     * - "1 234 567" (české mezery)
     * - "1,234,567" (americké čárky)
     * - "1.234.567" (německé tečky)
     * 
     * @param string $value Lokalizovaný string
     * @param string|null $locale Locale
     * 
     * @return int|null Číslo nebo null
     * 
     * @example
     * ```php
     * $type = new IntegerType();
     * 
     * // České formátování
     * $num = $type->parse('1 234 567', 'cs_CZ');
     * // 1234567
     * 
     * // Americké formátování
     * $num = $type->parse('1,234,567', 'en_US');
     * // 1234567
     * 
     * // Z formuláře
     * $quantity = $type->parse($_POST['quantity']);
     * ```
     */
    public function parse(string $value, ?string $locale = null): ?int
    {
        if ($value === '') {
            return null;
        }
        
        $locale = LocaleManager::resolveLocale($locale);
        
        if (extension_loaded('intl')) {
            $formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);
            $result = $formatter->parse($value, NumberFormatter::TYPE_INT64);
            return $result !== false ? (int) $result : null;
        }
        
        // Fallback - odstraníme běžné oddělovače
        $cleaned = str_replace([' ', ',', '.'], '', $value);
        return is_numeric($cleaned) ? (int) $cleaned : null;
    }
}