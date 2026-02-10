<?php

declare(strict_types=1);

namespace prochst\bsOrm\Types;

/**
 * Typ pro JSON data
 * 
 * JsonType se používá pro:
 * - Konfigurace, nastavení
 * - Metadata
 * - Flexibilní data (nested arrays)
 * - API responses
 * - Dynamické vlastnosti
 * 
 * Automaticky převádí mezi:
 * - PHP pole/objekty ↔ JSON string
 * 
 * SQL mapování:
 * - MySQL 5.7+: JSON (nativní typ s indexováním)
 * - PostgreSQL 9.4+: JSONB (binární, rychlejší)
 * - Starší databáze: TEXT
 * 
 * ⚠️ POZOR: JSON sloupce nelze efektivně indexovat (kromě PostgreSQL JSONB)
 * 
 * @package prochst\bsOrm\Types
 * @author  Your Name
 * @version 1.0.0
 * 
 * @example
 * ```php
 * // Nastavení uživatele
 * #[Column(
 *     label: 'Nastavení',
 *     type: new JsonType(),
 *     nullable: false,
 *     default: '{}'
 * )]
 * private array $settings;
 * 
 * // Metadata produktu
 * #[Column(type: new JsonType())]
 * private array $metadata;
 * 
 * // Použití
 * $user->setSettings([
 *     'theme' => 'dark',
 *     'language' => 'cs_CZ',
 *     'notifications' => [
 *         'email' => true,
 *         'sms' => false,
 *     ]
 * ]);
 * 
 * $settings = $user->getSettings();
 * echo $settings['theme']; // "dark"
 * ```
 */
class JsonType implements TypeInterface
{
    /**
     * {@inheritdoc}
     * 
     * Převádí PHP pole/objekt na JSON string.
     * Vyhodí výjimku pokud hodnota není JSON-serializable.
     * 
     * @throws \JsonException Pokud hodnota nelze převést na JSON
     * 
     * @example
     * ```php
     * $type = new JsonType();
     * 
     * $json = $type->toDatabase(['name' => 'John', 'age' => 30]);
     * // '{"name":"John","age":30}'
     * 
     * $json = $type->toDatabase(null);
     * // null
     * 
     * // Vnořené pole
     * $json = $type->toDatabase([
     *     'user' => [
     *         'name' => 'John',
     *         'roles' => ['admin', 'editor']
     *     ]
     * ]);
     * // '{"user":{"name":"John","roles":["admin","editor"]}}'
     * ```
     */
    public function toDatabase(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        
        return json_encode($value, JSON_THROW_ON_ERROR);
    }
    
    /**
     * {@inheritdoc}
     * 
     * Převádí JSON string na PHP pole.
     * Vyhodí výjimku pokud JSON string je neplatný.
     * 
     * @throws \JsonException Pokud JSON string je neplatný
     * 
     * @example
     * ```php
     * $type = new JsonType();
     * 
     * $data = $type->fromDatabase('{"name":"John","age":30}');
     * // ['name' => 'John', 'age' => 30]
     * 
     * $data = $type->fromDatabase(null);
     * // null
     * 
     * // Již dekódované (např. z PostgreSQL JSONB)
     * $data = $type->fromDatabase(['name' => 'John']);
     * // ['name' => 'John'] (zachová se)
     * ```
     */
    public function fromDatabase(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        
        if (is_string($value)) {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        }
        
        return $value;
    }
    
    /**
     * {@inheritdoc}
     * 
     * @example
     * ```php
     * $type = new JsonType();
     * echo $type->getSqlType('mysql');   // "JSON"
     * echo $type->getSqlType('pgsql');   // "JSONB"
     * echo $type->getSqlType('sqlite');  // "TEXT"
     * ```
     */
    public function getSqlType(string $driver): string
    {
        return match($driver) {
            'pgsql' => 'JSONB',
            'mysql' => 'JSON',
            'sqlite' => 'TEXT',
            default => 'TEXT',
        };
    }
    
    /**
     * {@inheritdoc}
     * 
     * Validuje že hodnotu lze převést na JSON.
     * 
     * @example
     * ```php
     * $type = new JsonType();
     * 
     * $errors = $type->validate(['name' => 'John']);
     * // [] - OK
     * 
     * $errors = $type->validate(null);
     * // [] - OK
     * 
     * // Resource nelze převést na JSON
     * $resource = fopen('file.txt', 'r');
     * $errors = $type->validate(['file' => $resource]);
     * // ["Hodnota není validní JSON: ..."]
     * ```
     */
    public function validate(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        
        try {
            json_encode($value, JSON_THROW_ON_ERROR);
            return [];
        } catch (\JsonException $e) {
            return ['Hodnota není validní JSON: ' . $e->getMessage()];
        }
    }
    
    /**
     * Formátuje JSON jako pretty-printed string
     * 
     * Užitečné pro zobrazení v admin panelu nebo debugování.
     * 
     * @param mixed $value Hodnota k formátování
     * @param string|null $locale Locale (nepoužito)
     * 
     * @return string Pretty-printed JSON
     * 
     * @example
     * ```php
     * $type = new JsonType();
     * $data = ['name' => 'John', 'age' => 30, 'roles' => ['admin', 'editor']];
     * 
     * echo $type->format($data);
     * // {
     * //     "name": "John",
     * //     "age": 30,
     * //     "roles": [
     * //         "admin",
     * //         "editor"
     * //     ]
     * // }
     * 
     * // V šabloně
     * <pre><?= $jsonType->format($entity->getMetadata()) ?></pre>
     * ```
     */
    public function format(mixed $value, ?string $locale = null): string
    {
        if ($value === null) {
            return '';
        }
        
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (\JsonException) {
            return '';
        }
    }
    
    /**
     * Parsuje JSON string na PHP pole
     * 
     * @param string $value JSON string
     * @param string|null $locale Locale (nepoužito)
     * 
     * @return mixed PHP hodnota nebo null
     * 
     * @example
     * ```php
     * $type = new JsonType();
     * 
     * $data = $type->parse('{"name":"John","age":30}');
     * // ['name' => 'John', 'age' => 30]
     * 
     * $data = $type->parse('');
     * // null
     * 
     * $data = $type->parse('invalid json');
     * // null (tichý fallback)
     * 
     * // Z formuláře (textarea s JSON)
     * $metadata = $type->parse($_POST['metadata_json']);
     * ```
     */
    public function parse(string $value, ?string $locale = null): mixed
    {
        if ($value === '') {
            return null;
        }
        
        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }
}