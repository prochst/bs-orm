<?php

declare(strict_types=1);

namespace prochst\bsOrm\Types;

/**
 * Rozhraní pro všechny datové typy v ORM
 * 
 * TypeInterface definuje smlouvu pro konverzi a práci s daty mezi:
 * - PHP aplikací (objekty, pole, primitivní typy)
 * - Databází (SQL datové typy a formáty)
 * - Uživatelským rozhraním (lokalizované formátování)
 * 
 * Každý typ implementující toto rozhraní musí umět:
 * 1. Převést PHP hodnotu do SQL formátu (toDatabase)
 * 2. Převést SQL hodnotu do PHP formátu (fromDatabase)
 * 3. Vrátit SQL definici typu (getSqlType)
 * 4. Validovat hodnotu (validate)
 * 5. Naformátovat hodnotu pro zobrazení (format)
 * 6. Parsovat lokalizovaný string zpět na PHP hodnotu (parse)
 * 
 * @package prochst\bsOrm\Types
 * @author  Your Name
 * @version 1.0.0
 * 
 * @example
 * ```php
 * // Vlastní typ pro IP adresy
 * class IpAddressType implements TypeInterface
 * {
 *     public function toDatabase(mixed $value): ?string
 *     {
 *         if ($value === null) return null;
 *         // Převod na long integer pro úsporu místa
 *         return (string) ip2long($value);
 *     }
 *     
 *     public function fromDatabase(mixed $value): ?string
 *     {
 *         if ($value === null) return null;
 *         return long2ip((int) $value);
 *     }
 *     
 *     public function getSqlType(string $driver): string
 *     {
 *         return 'BIGINT'; // Uloženo jako číslo
 *     }
 *     
 *     public function validate(mixed $value): array
 *     {
 *         if ($value === null) return [];
 *         return filter_var($value, FILTER_VALIDATE_IP) 
 *             ? [] 
 *             : ['Neplatná IP adresa'];
 *     }
 *     
 *     public function format(mixed $value, ?string $locale = null): string
 *     {
 *         return $value ?? '';
 *     }
 *     
 *     public function parse(string $value, ?string $locale = null): ?string
 *     {
 *         return $value !== '' ? $value : null;
 *     }
 * }
 * ```
 */
interface TypeInterface
{
    /**
     * Převede hodnotu z PHP do databázového formátu
     * 
     * Tato metoda se volá před uložením do databáze (INSERT/UPDATE).
     * Měla by převést PHP objekty a typy na SQL kompatibilní formát.
     * 
     * @param mixed $value PHP hodnota
     * 
     * @return mixed SQL kompatibilní hodnota (string, int, float, null)
     * 
     * @example
     * ```php
     * // DateTimeType
     * $dateTime = new DateTimeImmutable('2025-02-05 14:30:00');
     * $sql = $type->toDatabase($dateTime);
     * // "2025-02-05 14:30:00" (string pro SQL)
     * 
     * // JsonType
     * $array = ['name' => 'John', 'age' => 30];
     * $sql = $type->toDatabase($array);
     * // '{"name":"John","age":30}' (JSON string)
     * 
     * // BooleanType
     * $bool = true;
     * $sql = $type->toDatabase($bool);
     * // 1 (integer pro SQL)
     * ```
     */
    public function toDatabase(mixed $value): mixed;
    
    /**
     * Převede hodnotu z databáze do PHP formátu
     * 
     * Tato metoda se volá při načítání z databáze (SELECT).
     * Měla by převést SQL hodnoty na PHP objekty a typy.
     * 
     * @param mixed $value SQL hodnota (obvykle string, int, float, null)
     * 
     * @return mixed PHP hodnota (object, array, bool, ...)
     * 
     * @example
     * ```php
     * // DateTimeType
     * $sql = '2025-02-05 14:30:00';
     * $dateTime = $type->fromDatabase($sql);
     * // DateTimeImmutable object
     * 
     * // JsonType
     * $sql = '{"name":"John","age":30}';
     * $array = $type->fromDatabase($sql);
     * // ['name' => 'John', 'age' => 30]
     * 
     * // BooleanType
     * $sql = 1;
     * $bool = $type->fromDatabase($sql);
     * // true
     * ```
     */
    public function fromDatabase(mixed $value): mixed;
    
    /**
     * Vrátí SQL definici typu pro CREATE TABLE
     * 
     * Generuje SQL specifické pro daný databázový systém.
     * Používá se při generování migračních skriptů nebo CREATE TABLE příkazů.
     * 
     * @param string $driver Typ databáze ('mysql', 'pgsql', 'sqlite')
     * 
     * @return string SQL typ (např. 'VARCHAR(255)', 'INTEGER', 'TIMESTAMP')
     * 
     * @example
     * ```php
     * $stringType = new StringType(maxLength: 255);
     * echo $stringType->getSqlType('mysql');   // "VARCHAR(255)"
     * echo $stringType->getSqlType('pgsql');   // "VARCHAR(255)"
     * 
     * $textType = new TextType();
     * echo $textType->getSqlType('mysql');     // "TEXT"
     * echo $textType->getSqlType('pgsql');     // "TEXT"
     * 
     * $boolType = new BooleanType();
     * echo $boolType->getSqlType('mysql');     // "TINYINT(1)"
     * echo $boolType->getSqlType('pgsql');     // "BOOLEAN"
     * 
     * // V CREATE TABLE:
     * CREATE TABLE users (
     *     id INT PRIMARY KEY,
     *     email VARCHAR(255) NOT NULL,
     *     bio TEXT,
     *     active TINYINT(1) DEFAULT 1
     * );
     * ```
     */
    public function getSqlType(string $driver): string;
    
    /**
     * Validuje hodnotu
     * 
     * Kontroluje, zda hodnota odpovídá požadavkům typu.
     * Vrací pole chybových zpráv (prázdné pole = validní).
     * 
     * @param mixed $value Hodnota k validaci
     * 
     * @return string[] Pole chybových zpráv
     * 
     * @example
     * ```php
     * $emailType = new StringType(maxLength: 255);
     * 
     * // Validní
     * $errors = $emailType->validate('john@example.com');
     * // []
     * 
     * // Příliš dlouhý string
     * $errors = $emailType->validate(str_repeat('a', 300));
     * // ["Hodnota překračuje maximální délku 255"]
     * 
     * $intType = new IntegerType(unsigned: true);
     * $errors = $intType->validate(-5);
     * // ["Hodnota musí být nezáporná"]
     * ```
     */
    public function validate(mixed $value): array;
    
    /**
     * Naformátuje hodnotu pro zobrazení podle locale
     * 
     * Převede PHP hodnotu na uživatelsky přívětivý string
     * podle národního prostředí (čísla, datumy, měny, ...).
     * 
     * @param mixed $value PHP hodnota
     * @param string|null $locale Locale (např. 'cs_CZ', 'en_US')
     * 
     * @return string Naformátovaný string
     * 
     * @example
     * ```php
     * $intType = new IntegerType();
     * echo $intType->format(1234567, 'cs_CZ');  // "1 234 567"
     * echo $intType->format(1234567, 'en_US');  // "1,234,567"
     * 
     * $dateType = new DateTimeType();
     * $date = new DateTimeImmutable('2025-02-05 14:30:00');
     * echo $dateType->format($date, 'cs_CZ');   // "5. 2. 2025 14:30"
     * echo $dateType->format($date, 'en_US');   // "2/5/2025 2:30 PM"
     * 
     * $priceType = new CurrencyType('CZK');
     * echo $priceType->format(1234.50, 'cs_CZ'); // "1 234,50 Kč"
     * ```
     */
    public function format(mixed $value, ?string $locale = null): string;
    
    /**
     * Parsuje lokalizovaný string zpět na PHP hodnotu
     * 
     * Inverzní operace k format(). Používá se pro parsing
     * uživatelského vstupu z formulářů.
     * 
     * @param string $value Lokalizovaný string
     * @param string|null $locale Locale
     * 
     * @return mixed PHP hodnota nebo null
     * 
     * @example
     * ```php
     * $intType = new IntegerType();
     * $value = $intType->parse('1 234 567', 'cs_CZ');
     * // 1234567 (int)
     * 
     * $value = $intType->parse('1,234,567', 'en_US');
     * // 1234567 (int)
     * 
     * $dateType = new DateTimeType();
     * $date = $dateType->parse('5.2.2025', 'cs_CZ');
     * // DateTimeImmutable object
     * 
     * $date = $dateType->parse('2/5/2025', 'en_US');
     * // DateTimeImmutable object
     * ```
     */
    public function parse(string $value, ?string $locale = null): mixed;
}