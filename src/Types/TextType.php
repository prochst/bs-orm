<?php

declare(strict_types=1);

namespace prochst\bsOrm\Types;

/**
 * Typ pro dlouhé textové řetězce bez omezení délky
 * 
 * TextType se používá pro:
 * - Dlouhé popisy, články
 * - Komentáře
 * - HTML obsah
 * - Markdown text
 * - Log záznamy
 * 
 * Na rozdíl od StringType:
 * - Nemá omezení délky
 * - Vždy používá TEXT SQL typ
 * - Vhodný pro texty delší než 255 znaků
 * 
 * SQL mapování:
 * - Všechny databáze: TEXT
 * 
 * @package prochst\bsOrm\Types
 * @author  Your Name
 * @version 1.0.0
 * 
 * @example
 * ```php
 * // Popis produktu
 * #[Column(
 *     label: 'Popis',
 *     type: new TextType(),
 *     nullable: true
 * )]
 * private ?string $description;
 * 
 * // Obsah článku
 * #[Column(
 *     label: 'Obsah',
 *     type: new TextType(),
 *     nullable: false
 * )]
 * private string $content;
 * 
 * // Komentář
 * #[Column(type: new TextType())]
 * private string $comment;
 * 
 * // HTML obsah
 * #[Column(
 *     type: new TextType(),
 *     validators: [
 *         fn($v) => strlen($v) <= 65535 ?: 'Text je příliš dlouhý'
 *     ]
 * )]
 * private string $htmlContent;
 * ```
 */
class TextType implements TypeInterface
{
    /**
     * {@inheritdoc}
     * 
     * @example
     * ```php
     * $type = new TextType();
     * 
     * $longText = str_repeat('Lorem ipsum ', 1000);
     * echo $type->toDatabase($longText);
     * // Dlouhý text (bez omezení)
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
        
        return (string) $value;
    }
    
    /**
     * {@inheritdoc}
     * 
     * @example
     * ```php
     * $type = new TextType();
     * 
     * $text = $type->fromDatabase('Long text from database...');
     * // "Long text from database..."
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
     * Vždy vrací TEXT bez ohledu na databázi.
     * 
     * @example
     * ```php
     * $type = new TextType();
     * echo $type->getSqlType('mysql');   // "TEXT"
     * echo $type->getSqlType('pgsql');   // "TEXT"
     * echo $type->getSqlType('sqlite');  // "TEXT"
     * ```
     */
    public function getSqlType(string $driver): string
    {
        return match($driver) {
            'pgsql' => 'TEXT',
            'mysql' => 'TEXT',
            'sqlite' => 'TEXT',
            default => 'TEXT',
        };
    }
    
    /**
     * {@inheritdoc}
     * 
     * Kontroluje pouze že hodnota je string.
     * 
     * @example
     * ```php
     * $type = new TextType();
     * 
     * $errors = $type->validate('Any length text...');
     * // [] - OK
     * 
     * $errors = $type->validate(str_repeat('x', 100000));
     * // [] - OK (žádné omezení délky)
     * 
     * $errors = $type->validate(123);
     * // ["Hodnota musí být string"]
     * ```
     */
    public function validate(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        
        if (!is_string($value)) {
            return ['Hodnota musí být string'];
        }
        
        return [];
    }
    
    /**
     * {@inheritdoc}
     * 
     * Vrací string jak je. Pro dlouhé texty můžete zkrátit v šabloně.
     * 
     * @example
     * ```php
     * $type = new TextType();
     * echo $type->format('Long text...');
     * // "Long text..."
     * 
     * // V šabloně s omezením délky
     * <?= mb_substr($textType->format($text), 0, 200) . '...' ?>
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
     * @example
     * ```php
     * $type = new TextType();
     * $text = $type->parse($_POST['description']);
     * ```
     */
    public function parse(string $value, ?string $locale = null): mixed
    {
        return $value !== '' ? $value : null;
    }
}