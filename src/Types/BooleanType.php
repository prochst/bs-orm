<?php

declare(strict_types=1);

namespace prochst\bsOrm\Types;

/**
 * Typ pro logické hodnoty (true/false)
 * 
 * BooleanType se používá pro:
 * - Příznaky, flagy (active, deleted, published, ...)
 * - Souhlas/nesouhlas (terms_accepted, newsletter_subscribed, ...)
 * - Stavy (is_admin, has_access, can_edit, ...)
 * 
 * SQL mapování se liší podle databáze:
 * - MySQL: TINYINT(1) - 0 nebo 1
 * - PostgreSQL: BOOLEAN - true nebo false
 * - SQLite: INTEGER - 0 nebo 1
 * 
 * PHP boolean se automaticky převádí na správný SQL formát.
 * 
 * @package prochst\bsOrm\Types
 * @author  Your Name
 * @version 1.0.0
 * 
 * @example
 * ```php
 * // Aktivní uživatel
 * #[Column(
 *     label: 'Aktivní',
 *     type: new BooleanType(),
 *     default: true
 * )]
 * private bool $active;
 * 
 * // Smazaný záznam (soft delete)
 * #[Column(type: new BooleanType(), default: false)]
 * private bool $deleted;
 * 
 * // Souhlas s podmínkami
 * #[Column(
 *     label: 'Souhlas s podmínkami',
 *     type: new BooleanType(),
 *     nullable: false
 * )]
 * private bool $termsAccepted;
 * 
 * // Admin oprávnění
 * #[Column(type: new BooleanType(), default: false)]
 * private bool $isAdmin;
 * ```
 */
class BooleanType implements TypeInterface
{
    /**
     * {@inheritdoc}
     * 
     * Převádí PHP boolean na integer pro databázi:
     * - true → 1
     * - false → 0
     * - null → null
     * 
     * @example
     * ```php
     * $type = new BooleanType();
     * echo $type->toDatabase(true);    // 1
     * echo $type->toDatabase(false);   // 0
     * echo $type->toDatabase(null);    // null
     * ```
     */
    public function toDatabase(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        
        return $value ? 1 : 0;
    }
    
    /**
     * {@inheritdoc}
     * 
     * Převádí SQL hodnotu na PHP boolean:
     * - 1, '1', true → true
     * - 0, '0', false, '' → false
     * - null → null
     * 
     * @example
     * ```php
     * $type = new BooleanType();
     * var_dump($type->fromDatabase(1));      // true
     * var_dump($type->fromDatabase(0));      // false
     * var_dump($type->fromDatabase('1'));    // true
     * var_dump($type->fromDatabase(null));   // null
     * ```
     */
    public function fromDatabase(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }
        
        return (bool) $value;
    }
    
    /**
     * {@inheritdoc}
     * 
     * @example
     * ```php
     * $type = new BooleanType();
     * echo $type->getSqlType('mysql');   // "TINYINT(1)"
     * echo $type->getSqlType('pgsql');   // "BOOLEAN"
     * echo $type->getSqlType('sqlite');  // "INTEGER"
     * ```
     */
    public function getSqlType(string $driver): string
    {
        return match($driver) {
            'pgsql' => 'BOOLEAN',
            'mysql' => 'TINYINT(1)',
            'sqlite' => 'INTEGER',
            default => 'BOOLEAN',
        };
    }
    
    /**
     * {@inheritdoc}
     * 
     * Akceptuje boolean i integer (0/1)
     * 
     * @example
     * ```php
     * $type = new BooleanType();
     * 
     * $errors = $type->validate(true);
     * // [] - OK
     * 
     * $errors = $type->validate(1);
     * // [] - OK
     * 
     * $errors = $type->validate('yes');
     * // ["Hodnota musí být boolean"]
     * ```
     */
    public function validate(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        
        if (!is_bool($value) && $value !== 0 && $value !== 1) {
            return ['Hodnota musí být boolean'];
        }
        
        return [];
    }
    
    /**
     * Naformátuje boolean jako text
     * 
     * Pro checkbox/switch UI prvky raději použijte přímo hodnotu,
     * pro textový výstup vrací "Ano"/"Ne" podle locale.
     * 
     * @param mixed $value Hodnota k formátování
     * @param string|null $locale Locale (zatím nepoužito)
     * 
     * @return string "Ano" nebo "Ne" (nebo prázdný string pro null)
     * 
     * @example
     * ```php
     * $type = new BooleanType();
     * echo $type->format(true);    // "Ano"
     * echo $type->format(false);   // "Ne"
     * echo $type->format(null);    // ""
     * 
     * // V šabloně
     * <td><?= $type->format($user->isActive()) ?></td>
     * 
     * // Pro lokalizaci můžete rozšířit
     * public function format(mixed $value, ?string $locale = null): string
     * {
     *     if ($value === null) return '';
     *     $locale = LocaleManager::resolveLocale($locale);
     *     return match($locale) {
     *         'en_US' => $value ? 'Yes' : 'No',
     *         'de_DE' => $value ? 'Ja' : 'Nein',
     *         default => $value ? 'Ano' : 'Ne',
     *     };
     * }
     * ```
     */
    public function format(mixed $value, ?string $locale = null): string
    {
        if ($value === null) {
            return '';
        }
        
        return $value ? 'Ano' : 'Ne';
    }
    
    /**
     * Parsuje textovou reprezentaci na boolean
     * 
     * Rozpoznává běžné textové reprezentace:
     * - "1", "true", "yes", "ano" → true
     * - "0", "false", "no", "ne" → false
     * 
     * @param string $value String k parsování
     * @param string|null $locale Locale
     * 
     * @return bool|null Boolean nebo null
     * 
     * @example
     * ```php
     * $type = new BooleanType();
     * var_dump($type->parse('1'));      // true
     * var_dump($type->parse('yes'));    // true
     * var_dump($type->parse('ano'));    // true
     * var_dump($type->parse('0'));      // false
     * var_dump($type->parse(''));       // null
     * ```
     */
    public function parse(string $value, ?string $locale = null): ?bool
    {
        if ($value === '') {
            return null;
        }
        
        $value = strtolower(trim($value));
        
        if (in_array($value, ['1', 'true', 'yes', 'ano'], true)) {
            return true;
        }
        
        if (in_array($value, ['0', 'false', 'no', 'ne'], true)) {
            return false;
        }
        
        return null;
    }
}