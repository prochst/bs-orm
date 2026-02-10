<?php

declare(strict_types=1);

namespace prochst\bsOrm\Types;

/**
 * Typ pro textové řetězce s omezenou délkou
 * 
 * StringType je základní typ pro většinu textových dat v aplikaci:
 * - E-maily
 * - Jména, příjmení
 * - Titulky, nadpisy
 * - URL adresy
 * - Krátké popisy
 * 
 * Pro delší texty použijte TextType, který nemá omezení délky.
 * 
 * SQL mapování:
 * - VARCHAR(n) - pokud je zadána maxLength
 * - TEXT - pokud maxLength není zadána
 * 
 * @package prochst\bsOrm\Types
 * @author  Your Name
 * @version 1.0.0
 * 
 * @example
 * ```php
 * // E-mail s omezenou délkou
 * #[Column(type: new StringType(maxLength: 255))]
 * private string $email;
 * 
 * // Jméno
 * #[Column(type: new StringType(maxLength: 100))]
 * private string $name;
 * 
 * // Slug (URL-friendly string)
 * #[Column(
 *     type: new StringType(maxLength: 150),
 *     validators: [
 *         fn($v) => preg_match('/^[a-z0-9-]+$/', $v) ?: 'Neplatný slug'
 *     ]
 * )]
 * private string $slug;
 * 
 * // String bez omezení délky (použije TEXT)
 * #[Column(type: new StringType())]
 * private string $content;
 * ```
 */
class StringType implements TypeInterface
{
    /**
     * Vytvoří nový StringType
     * 
     * @param int|null $maxLength Maximální délka řetězce (null = neomezeno, použije TEXT)
     * 
     * @example
     * ```php
     * // E-mail - standardní limit 255
     * new StringType(maxLength: 255)
     * 
     * // Dlouhý text bez limitu
     * new StringType()
     * 
     * // Krátký kód
     * new StringType(maxLength: 50)
     * ```
     */
    public function __construct(
        private ?int $maxLength = null,
    ) {
    }
    
    /**
     * {@inheritdoc}
     * 
     * Převede hodnotu na string. Akceptuje i číselné hodnoty.
     * 
     * @example
     * ```php
     * $type = new StringType();
     * echo $type->toDatabase('text');      // "text"
     * echo $type->toDatabase(123);         // "123"
     * echo $type->toDatabase(null);        // null
     * ```
     */
    public function toDatabase(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        
        return (string) $value;
    }
    
    /**
     * {@inheritdoc}
     * 
     * Převede hodnotu z databáze na string.
     * 
     * @example
     * ```php
     * $type = new StringType();
     * echo $type->fromDatabase('text');    // "text"
     * echo $type->fromDatabase(null);      // null
     * ```
     */
    public function fromDatabase(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        
        return (string) $value;
    }
    
    /**
     * {@inheritdoc}
     * 
     * Vrací VARCHAR(n) pokud je maxLength zadána, jinak TEXT
     * 
     * @example
     * ```php
     * $type = new StringType(maxLength: 255);
     * echo $type->getSqlType('mysql');     // "VARCHAR(255)"
     * echo $type->getSqlType('pgsql');     // "VARCHAR(255)"
     * 
     * $type = new StringType();
     * echo $type->getSqlType('mysql');     // "TEXT"
     * ```
     */
    public function getSqlType(string $driver): string
    {
        if ($this->maxLength === null) {
            return match($driver) {
                'pgsql' => 'TEXT',
                'mysql' => 'TEXT',
                'sqlite' => 'TEXT',
                default => 'TEXT',
            };
        }
        
        return match($driver) {
            'pgsql' => "VARCHAR({$this->maxLength})",
            'mysql' => "VARCHAR({$this->maxLength})",
            'sqlite' => "VARCHAR({$this->maxLength})",
            default => "VARCHAR({$this->maxLength})",
        };
    }
    
    /**
     * {@inheritdoc}
     * 
     * Validuje:
     * - Typ hodnoty (musí být string nebo numeric)
     * - Délku řetězce (nesmí překročit maxLength)
     * 
     * @example
     * ```php
     * $type = new StringType(maxLength: 10);
     * 
     * $errors = $type->validate('short');
     * // [] - OK
     * 
     * $errors = $type->validate('very long string');
     * // ["Hodnota překračuje maximální délku 10"]
     * 
     * $errors = $type->validate([]);
     * // ["Hodnota musí být string"]
     * ```
     */
    public function validate(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        
        if (!is_string($value) && !is_numeric($value)) {
            return ['Hodnota musí být string'];
        }
        
        if ($this->maxLength !== null && mb_strlen((string) $value, 'UTF-8') > $this->maxLength) {
            return ["Hodnota překračuje maximální délku {$this->maxLength}"];
        }
        
        return [];
    }
    
    /**
     * {@inheritdoc}
     * 
     * Pro stringy jednoduše vrací hodnotu (není co formátovat).
     * 
     * @example
     * ```php
     * $type = new StringType();
     * echo $type->format('Hello World');   // "Hello World"
     * echo $type->format(null);            // ""
     * ```
     */
    public function format(mixed $value, ?string $locale = null): string
    {
        if ($value === null) {
            return '';
        }
        
        return (string) $value;
    }
    
    /**
     * {@inheritdoc}
     * 
     * Jednoduše vrací string (není co parsovat).
     * 
     * @example
     * ```php
     * $type = new StringType();
     * echo $type->parse('text');   // "text"
     * echo $type->parse('');       // "text"
     * ```
     */
    public function parse(string $value, ?string $locale = null): mixed
    {
        return $value;
    }
}