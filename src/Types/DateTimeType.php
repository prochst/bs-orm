<?php

declare(strict_types=1);

namespace prochst\bsOrm\Types;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use IntlDateFormatter;

/**
 * Typ pro datum a čas
 * 
 * DateTimeType se používá pro:
 * - Časová razítka (created_at, updated_at)
 * - Datumy narození
 * - Termíny, deadliny
 * - Plánované události
 * - Historie změn
 * 
 * Podporuje:
 * - DateTime i DateTimeImmutable (immutable je doporučeno)
 * - Lokalizované formátování (český, americký, německý formát...)
 * - Parsing různých formátů data
 * - Samostatné formátování data a času
 * 
 * SQL mapování:
 * - MySQL/MariaDB: DATETIME
 * - PostgreSQL: TIMESTAMP
 * - SQLite: TEXT (ISO 8601 formát)
 * 
 * @package prochst\bsOrm\Types
 * @author  Your Name
 * @version 1.0.0
 * 
 * @example
 * ```php
 * // Datum vytvoření (immutable doporučeno)
 * #[Column(
 *     label: 'Vytvořeno',
 *     type: new DateTimeType(immutable: true),
 *     nullable: false
 * )]
 * private \DateTimeImmutable $createdAt;
 * 
 * // Datum aktualizace
 * #[Column(
 *     label: 'Aktualizováno',
 *     type: new DateTimeType(immutable: true),
 *     nullable: true
 * )]
 * private ?\DateTimeImmutable $updatedAt;
 * 
 * // Datum narození
 * #[Column(
 *     label: 'Datum narození',
 *     type: new DateTimeType(immutable: true),
 *     nullable: true
 * )]
 * private ?\DateTimeImmutable $birthDate;
 * 
 * // S vlastním formátem zobrazení
 * #[Column(
 *     type: new DateTimeType(
 *         immutable: true,
 *         displayFormat: 'd.m.Y'
 *     )
 * )]
 * private \DateTimeImmutable $eventDate;
 * ```
 */
class DateTimeType implements TypeInterface
{
    /**
     * Formát pro ukládání do databáze
     */
    private const FORMAT = 'Y-m-d H:i:s';
    
    /**
     * Vytvoří nový DateTimeType
     * 
     * @param bool $immutable Použít DateTimeImmutable místo DateTime?
     *                        Immutable je doporučeno pro thread-safety
     * @param string|null $displayFormat Vlastní formát pro zobrazení (PHP date format)
     *                                   Pokud null, použije se lokalizovaný formát
     * 
     * @example
     * ```php
     * // Immutable (doporučeno)
     * new DateTimeType(immutable: true)
     * 
     * // Mutable (starý způsob)
     * new DateTimeType()
     * 
     * // S vlastním formátem
     * new DateTimeType(immutable: true, displayFormat: 'd.m.Y')
     * new DateTimeType(immutable: true, displayFormat: 'j. n. Y H:i')
     * ```
     */
    public function __construct(
        private bool $immutable = false,
        private ?string $displayFormat = null,
    ) {
    }
    
    /**
     * {@inheritdoc}
     * 
     * Převádí DateTimeInterface na ISO 8601 string pro databázi.
     * 
     * @example
     * ```php
     * $type = new DateTimeType();
     * 
     * $date = new DateTimeImmutable('2025-02-05 14:30:00');
     * echo $type->toDatabase($date);
     * // "2025-02-05 14:30:00"
     * 
     * echo $type->toDatabase('2025-02-05');
     * // "2025-02-05" (string se zachová)
     * 
     * echo $type->toDatabase(1738765800);
     * // "2025-02-05 14:30:00" (timestamp)
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
        
        if ($value instanceof DateTimeInterface) {
            return $value->format(self::FORMAT);
        }
        
        if (is_string($value)) {
            return $value;
        }
        
        if (is_int($value)) {
            return date(self::FORMAT, $value);
        }
        
        return null;
    }
    
    /**
     * {@inheritdoc}
     * 
     * Převádí SQL string na DateTime nebo DateTimeImmutable.
     * 
     * @example
     * ```php
     * $type = new DateTimeType(immutable: true);
     * 
     * $date = $type->fromDatabase('2025-02-05 14:30:00');
     * // DateTimeImmutable object
     * 
     * echo $date->format('Y-m-d');
     * // "2025-02-05"
     * 
     * $type = new DateTimeType(immutable: false);
     * $date = $type->fromDatabase('2025-02-05 14:30:00');
     * // DateTime object
     * ```
     */
    public function fromDatabase(mixed $value): DateTimeInterface|null
    {
        if ($value === null) {
            return null;
        }
        
        if ($value instanceof DateTimeInterface) {
            return $value;
        }
        
        if (is_string($value)) {
            $class = $this->immutable ? DateTimeImmutable::class : DateTime::class;
            return new $class($value);
        }
        
        return null;
    }
    
    /**
     * {@inheritdoc}
     * 
     * @example
     * ```php
     * $type = new DateTimeType();
     * echo $type->getSqlType('mysql');   // "DATETIME"
     * echo $type->getSqlType('pgsql');   // "TIMESTAMP"
     * echo $type->getSqlType('sqlite');  // "TEXT"
     * ```
     */
    public function getSqlType(string $driver): string
    {
        return match($driver) {
            'pgsql' => 'TIMESTAMP',
            'mysql' => 'DATETIME',
            'sqlite' => 'TEXT',
            default => 'DATETIME',
        };
    }
    
    /**
     * {@inheritdoc}
     * 
     * Akceptuje DateTimeInterface nebo parsovatelný string.
     * 
     * @example
     * ```php
     * $type = new DateTimeType();
     * 
     * $errors = $type->validate(new DateTimeImmutable());
     * // [] - OK
     * 
     * $errors = $type->validate('2025-02-05');
     * // [] - OK (parsovatelný string)
     * 
     * $errors = $type->validate('invalid-date');
     * // ["Neplatný formát data/času"]
     * 
     * $errors = $type->validate(12345);
     * // ["Hodnota musí být DateTimeInterface nebo validní string"]
     * ```
     */
    public function validate(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        
        if ($value instanceof DateTimeInterface) {
            return [];
        }
        
        if (is_string($value)) {
            try {
                new DateTime($value);
                return [];
            } catch (\Exception $e) {
                return ['Neplatný formát data/času'];
            }
        }
        
        return ['Hodnota musí být DateTimeInterface nebo validní string'];
    }
    
    /**
     * Naformátuje datum/čas podle locale
     * 
     * Používá PHP Intl extension pro lokalizované formátování.
     * Fallback na tvrdě kódované formáty.
     * 
     * @param mixed $value Hodnota k formátování
     * @param string|null $locale Locale
     * 
     * @return string Naformátované datum/čas
     * 
     * @example
     * ```php
     * $type = new DateTimeType();
     * $date = new DateTimeImmutable('2025-02-05 14:30:00');
     * 
     * // České formátování
     * echo $type->format($date, 'cs_CZ');
     * // "5. 2. 2025 14:30"
     * 
     * // Americké formátování
     * echo $type->format($date, 'en_US');
     * // "2/5/2025 2:30 PM"
     * 
     * // Britské formátování
     * echo $type->format($date, 'en_GB');
     * // "05/02/2025 14:30"
     * 
     * // S vlastním formátem
     * $type = new DateTimeType(displayFormat: 'd.m.Y');
     * echo $type->format($date);
     * // "05.02.2025"
     * 
     * // V šabloně
     * <p>Vytvořeno: <?= $dateTimeType->format($entity->getCreatedAt()) ?></p>
     * ```
     */
    public function format(mixed $value, ?string $locale = null): string
    {
        if ($value === null) {
            return '';
        }
        
        if (!$value instanceof DateTimeInterface) {
            $value = $this->fromDatabase($value);
        }
        
        if (!$value instanceof DateTimeInterface) {
            return '';
        }
        
        $locale = LocaleManager::resolveLocale($locale);
        
        // Pokud je definován vlastní formát, použijeme ho
        if ($this->displayFormat !== null) {
            return $value->format($this->displayFormat);
        }
        
        // Formátování podle locale
        if (extension_loaded('intl')) {
            $formatter = new IntlDateFormatter(
                $locale,
                IntlDateFormatter::MEDIUM,
                IntlDateFormatter::SHORT
            );
            return $formatter->format($value);
        }
        
        // Fallback pro různé locale
        return match($locale) {
            'cs_CZ', 'sk_SK' => $value->format('d.m.Y H:i'),
            'en_US' => $value->format('m/d/Y g:i A'),
            'en_GB' => $value->format('d/m/Y H:i'),
            default => $value->format('Y-m-d H:i:s'),
        };
    }
    
    /**
     * Parsuje lokalizovaný datum/čas
     * 
     * Umí zpracovat různé formáty podle locale:
     * - "5.2.2025" (české)
     * - "2/5/2025" (americké)
     * - "2025-02-05" (ISO)
     * 
     * @param string $value String k parsování
     * @param string|null $locale Locale
     * 
     * @return DateTimeInterface|null Datum nebo null
     * 
     * @example
     * ```php
     * $type = new DateTimeType(immutable: true);
     * 
     * // České formáty
     * $date = $type->parse('5.2.2025', 'cs_CZ');
     * $date = $type->parse('5.2.2025 14:30', 'cs_CZ');
     * 
     * // Americké formáty
     * $date = $type->parse('2/5/2025', 'en_US');
     * $date = $type->parse('2/5/2025 2:30 PM', 'en_US');
     * 
     * // ISO formát (univerzální)
     * $date = $type->parse('2025-02-05');
     * $date = $type->parse('2025-02-05 14:30:00');
     * 
     * // Z formuláře
     * $birthDate = $type->parse($_POST['birth_date']);
     * ```
     */
    public function parse(string $value, ?string $locale = null): ?DateTimeInterface
    {
        if ($value === '') {
            return null;
        }
        
        $locale = LocaleManager::resolveLocale($locale);
        
        if (extension_loaded('intl')) {
            $formatter = new IntlDateFormatter(
                $locale,
                IntlDateFormatter::MEDIUM,
                IntlDateFormatter::SHORT
            );
            
            $timestamp = $formatter->parse($value);
            if ($timestamp !== false) {
                $class = $this->immutable ? DateTimeImmutable::class : DateTime::class;
                return (new $class())->setTimestamp($timestamp);
            }
        }
        
        // Fallback - zkusíme různé formáty
        $formats = [
            'd.m.Y H:i:s',
            'd.m.Y H:i',
            'd.m.Y',
            'm/d/Y g:i A',
            'm/d/Y',
            'Y-m-d H:i:s',
            'Y-m-d',
        ];
        
        $class = $this->immutable ? DateTimeImmutable::class : DateTime::class;
        
        foreach ($formats as $format) {
            $date = $class::createFromFormat($format, $value);
            if ($date !== false) {
                return $date;
            }
        }
        
        // Poslední pokus - necháme PHP rozpoznat
        try {
            return new $class($value);
        } catch (\Exception) {
            return null;
        }
    }
    
    /**
     * Formátuje pouze datum (bez času)
     * 
     * @param mixed $value Hodnota k formátování
     * @param string|null $locale Locale
     * 
     * @return string Naformátované datum
     * 
     * @example
     * ```php
     * $type = new DateTimeType();
     * $date = new DateTimeImmutable('2025-02-05 14:30:00');
     * 
     * echo $type->formatDate($date, 'cs_CZ');
     * // "5. 2. 2025"
     * 
     * echo $type->formatDate($date, 'en_US');
     * // "2/5/2025"
     * 
     * echo $type->formatDate($date, 'en_GB');
     * // "05/02/2025"
     * 
     * // V šabloně pro datum narození
     * <p>Narozen: <?= $dateTimeType->formatDate($user->getBirthDate()) ?></p>
     * ```
     */
    public function formatDate(mixed $value, ?string $locale = null): string
    {
        if ($value === null) {
            return '';
        }
        
        if (!$value instanceof DateTimeInterface) {
            $value = $this->fromDatabase($value);
        }
        
        if (!$value instanceof DateTimeInterface) {
            return '';
        }
        
        $locale = LocaleManager::resolveLocale($locale);
        
        if (extension_loaded('intl')) {
            $formatter = new IntlDateFormatter(
                $locale,
                IntlDateFormatter::MEDIUM,
                IntlDateFormatter::NONE
            );
            return $formatter->format($value);
        }
        
        return match($locale) {
            'cs_CZ', 'sk_SK' => $value->format('d.m.Y'),
            'en_US' => $value->format('m/d/Y'),
            'en_GB' => $value->format('d/m/Y'),
            default => $value->format('Y-m-d'),
        };
    }
    
    /**
     * Formátuje pouze čas (bez data)
     * 
     * @param mixed $value Hodnota k formátování
     * @param string|null $locale Locale
     * 
     * @return string Naformátovaný čas
     * 
     * @example
     * ```php
     * $type = new DateTimeType();
     * $date = new DateTimeImmutable('2025-02-05 14:30:00');
     * 
     * echo $type->formatTime($date, 'cs_CZ');
     * // "14:30"
     * 
     * echo $type->formatTime($date, 'en_US');
     * // "2:30 PM"
     * 
     * // V šabloně pro čas události
     * <p>Začátek: <?= $dateTimeType->formatTime($event->getStartTime()) ?></p>
     * ```
     */
    public function formatTime(mixed $value, ?string $locale = null): string
    {
        if ($value === null) {
            return '';
        }
        
        if (!$value instanceof DateTimeInterface) {
            $value = $this->fromDatabase($value);
        }
        
        if (!$value instanceof DateTimeInterface) {
            return '';
        }
        
        $locale = LocaleManager::resolveLocale($locale);
        
        if (extension_loaded('intl')) {
            $formatter = new IntlDateFormatter(
                $locale,
                IntlDateFormatter::NONE,
                IntlDateFormatter::SHORT
            );
            return $formatter->format($value);
        }
        
        return match($locale) {
            'en_US' => $value->format('g:i A'),
            default => $value->format('H:i'),
        };
    }
}