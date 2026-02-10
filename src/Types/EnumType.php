<?php

declare(strict_types=1);

namespace prochst\bsOrm\Types;

/**
 * Typ pro výčtové hodnoty (enumerace)
 * 
 * EnumType se používá pro sloupce s omezenou sadou možných hodnot:
 * - Stavy (active, inactive, pending, ...)
 * - Priority (low, medium, high, critical)
 * - Role (admin, editor, viewer)
 * - Typy (article, page, post)
 * - Kategorie
 * 
 * Výhody oproti obyčejnému stringu:
 * - Validace na úrovni aplikace
 * - Překlady hodnot
 * - Type hints v editoru
 * - Snadno zjistitelné možné hodnoty
 * 
 * SQL mapování:
 * - MySQL: ENUM('value1', 'value2', ...)
 * - PostgreSQL: VARCHAR(255) (ENUM vyžaduje CREATE TYPE)
 * - SQLite: TEXT
 * 
 * 💡 TIP: Pro PHP 8.1+ můžete používat native PHP Enums
 * 
 * @package prochst\bsOrm\Types
 * @author  Your Name
 * @version 1.0.0
 * 
 * @example
 * ```php
 * // Stav uživatele
 * #[Column(
 *     label: 'Stav',
 *     type: new EnumType(
 *         values: ['active', 'inactive', 'banned'],
 *         translations: [
 *             'cs_CZ' => [
 *                 'active' => 'Aktivní',
 *                 'inactive' => 'Neaktivní',
 *                 'banned' => 'Zakázaný',
 *             ],
 *             'en_US' => [
 *                 'active' => 'Active',
 *                 'inactive' => 'Inactive',
 *                 'banned' => 'Banned',
 *             ],
 *         ]
 *     ),
 *     nullable: false,
 *     default: 'active'
 * )]
 * private string $status;
 * 
 * // Priorita
 * #[Column(
 *     type: new EnumType(
 *         values: ['low', 'medium', 'high', 'critical'],
 *         translations: [
 *             'cs_CZ' => [
 *                 'low' => 'Nízká',
 *                 'medium' => 'Střední',
 *                 'high' => 'Vysoká',
 *                 'critical' => 'Kritická',
 *             ],
 *         ]
 *     )
 * )]
 * private string $priority;
 * 
 * // Pro select v šabloně
 * $statusType = new EnumType(...);
 * $options = $statusType->getValuesWithLabels('cs_CZ');
 * // ['active' => 'Aktivní', 'inactive' => 'Neaktivní', ...]
 * ```
 */
class EnumType implements TypeInterface
{
    /**
     * Vytvoří nový EnumType
     * 
     * @param string[] $values Pole povolených hodnot (raw values)
     * @param array<string, array<string, string>>|null $translations Překlady hodnot
     *        Formát: ['locale' => ['value' => 'Překlad'], ...]
     * 
     * @example
     * ```php
     * // Jednoduchý enum bez překladů
     * new EnumType(values: ['draft', 'published', 'archived'])
     * 
     * // S překlady pro více jazyků
     * new EnumType(
     *     values: ['active', 'inactive'],
     *     translations: [
     *         'cs_CZ' => ['active' => 'Aktivní', 'inactive' => 'Neaktivní'],
     *         'en_US' => ['active' => 'Active', 'inactive' => 'Inactive'],
     *         'de_DE' => ['active' => 'Aktiv', 'inactive' => 'Inaktiv'],
     *     ]
     * )
     * ```
     */
    public function __construct(
        private array $values,
        private ?array $translations = null,
    ) {
    }
    
    /**
     * {@inheritdoc}
     * 
     * @example
     * ```php
     * $type = new EnumType(values: ['active', 'inactive']);
     * 
     * echo $type->toDatabase('active');
     * // "active"
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
     * $type = new EnumType(values: ['active', 'inactive']);
     * 
     * echo $type->fromDatabase('active');
     * // "active"
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
     * MySQL podporuje nativní ENUM typ, ostatní používají VARCHAR.
     * PostgreSQL ENUM vyžaduje CREATE TYPE, což je složitější.
     * 
     * @example
     * ```php
     * $type = new EnumType(values: ['active', 'inactive', 'banned']);
     * 
     * echo $type->getSqlType('mysql');
     * // "ENUM('active','inactive','banned')"
     * 
     * echo $type->getSqlType('pgsql');
     * // "VARCHAR(255)"
     * 
     * echo $type->getSqlType('sqlite');
     * // "TEXT"
     * ```
     */
    public function getSqlType(string $driver): string
    {
        $valuesList = implode("','", array_map(fn($v) => addslashes($v), $this->values));
        
        return match($driver) {
            'pgsql' => 'VARCHAR(255)', // PostgreSQL ENUM vyžaduje CREATE TYPE
            'mysql' => "ENUM('{$valuesList}')",
            'sqlite' => 'TEXT',
            default => 'VARCHAR(255)',
        };
    }
    
    /**
     * {@inheritdoc}
     * 
     * Validuje, že hodnota je jedna z povolených.
     * 
     * @example
     * ```php
     * $type = new EnumType(values: ['active', 'inactive', 'banned']);
     * 
     * $errors = $type->validate('active');
     * // [] - OK
     * 
     * $errors = $type->validate('unknown');
     * // ["Hodnota musí být jedna z: active, inactive, banned"]
     * 
     * $errors = $type->validate(null);
     * // [] - NULL je povolen (pokud je sloupec nullable)
     * ```
     */
    public function validate(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        
        if (!in_array($value, $this->values, true)) {
            $allowedValues = implode(', ', $this->values);
            return ["Hodnota musí být jedna z: {$allowedValues}"];
        }
        
        return [];
    }
    
    /**
     * Naformátuje enum hodnotu - vrátí překlad pokud existuje
     * 
     * Umožňuje zobrazit uživatelsky přívětivý text místo technické hodnoty.
     * 
     * @param mixed $value Raw enum hodnota
     * @param string|null $locale Locale
     * 
     * @return string Přeložená hodnota nebo originální
     * 
     * @example
     * ```php
     * $type = new EnumType(
     *     values: ['active', 'inactive'],
     *     translations: [
     *         'cs_CZ' => ['active' => 'Aktivní', 'inactive' => 'Neaktivní'],
     *         'en_US' => ['active' => 'Active', 'inactive' => 'Inactive'],
     *     ]
     * );
     * 
     * echo $type->format('active', 'cs_CZ');
     * // "Aktivní"
     * 
     * echo $type->format('active', 'en_US');
     * // "Active"
     * 
     * echo $type->format('active', 'de_DE');
     * // "active" (fallback - překlad neexistuje)
     * 
     * // V šabloně
     * <span class="status"><?= $enumType->format($user->getStatus()) ?></span>
     * 
     * // V gridu
     * foreach ($users as $user) {
     *     echo "<td>" . $statusType->format($user->getStatus()) . "</td>";
     * }
     * ```
     */
    public function format(mixed $value, ?string $locale = null): string
    {
        if ($value === null) {
            return '';
        }
        
        $locale = LocaleManager::resolveLocale($locale);
        
        // Pokud máme překlad pro danou hodnotu a locale
        if ($this->translations !== null 
            && isset($this->translations[$locale]) 
            && isset($this->translations[$locale][$value])) {
            return $this->translations[$locale][$value];
        }
        
        // Jinak vrátíme původní hodnotu
        return (string) $value;
    }
    
    /**
     * Parsuje hodnotu - pokud je to překlad, vrátí originální hodnotu
     * 
     * Umožňuje zpracovat formulářový input kde uživatel vidí překlad,
     * ale my potřebujeme raw hodnotu pro uložení.
     * 
     * @param string $value String k parsování (může být překlad)
     * @param string|null $locale Locale
     * 
     * @return string|null Raw enum hodnota nebo null
     * 
     * @example
     * ```php
     * $type = new EnumType(
     *     values: ['active', 'inactive'],
     *     translations: [
     *         'cs_CZ' => ['active' => 'Aktivní', 'inactive' => 'Neaktivní'],
     *     ]
     * );
     * 
     * // Parsování překladu
     * $value = $type->parse('Aktivní', 'cs_CZ');
     * // "active"
     * 
     * // Parsování raw hodnoty
     * $value = $type->parse('active', 'cs_CZ');
     * // "active"
     * 
     * // Prázdný string
     * $value = $type->parse('', 'cs_CZ');
     * // null
     * 
     * // Z formuláře
     * $status = $enumType->parse($_POST['status']);
     * $user->setStatus($status);
     * ```
     */
    public function parse(string $value, ?string $locale = null): ?string
    {
        if ($value === '') {
            return null;
        }
        
        $locale = LocaleManager::resolveLocale($locale);
        
        // Zkusíme najít originální hodnotu podle překladu
        if ($this->translations !== null && isset($this->translations[$locale])) {
            $originalValue = array_search($value, $this->translations[$locale], true);
            if ($originalValue !== false) {
                return $originalValue;
            }
        }
        
        // Pokud překlad neexistuje, vrátíme hodnotu jak je (může být raw value)
        return $value;
    }
    
    /**
     * Vrátí pole povolených hodnot
     * 
     * @return string[] Pole raw hodnot
     * 
     * @example
     * ```php
     * $type = new EnumType(values: ['active', 'inactive', 'banned']);
     * 
     * $values = $type->getValues();
     * // ['active', 'inactive', 'banned']
     * 
     * // Pro validaci v custom kódu
     * if (!in_array($inputValue, $enumType->getValues())) {
     *     throw new \InvalidArgumentException('Neplatná hodnota');
     * }
     * ```
     */
    public function getValues(): array
    {
        return $this->values;
    }
    
    /**
     * Vrátí všechny hodnoty s překlady pro dané locale
     * 
     * Velmi užitečné pro generování SELECT/RADIO prvků ve formulářích.
     * 
     * @param string|null $locale Locale
     * 
     * @return array<string, string> Pole [raw_value => překlad]
     * 
     * @example
     * ```php
     * $type = new EnumType(
     *     values: ['active', 'inactive', 'banned'],
     *     translations: [
     *         'cs_CZ' => [
     *             'active' => 'Aktivní',
     *             'inactive' => 'Neaktivní',
     *             'banned' => 'Zakázaný',
     *         ],
     *     ]
     * );
     * 
     * $options = $type->getValuesWithLabels('cs_CZ');
     * // [
     * //     'active' => 'Aktivní',
     * //     'inactive' => 'Neaktivní',
     * //     'banned' => 'Zakázaný',
     * // ]
     * 
     * // V Nette formuláři
     * $form->addSelect('status', 'Stav', $type->getValuesWithLabels());
     * 
     * // V čisté HTML šabloně
     * <select name="status">
     *     <?php foreach ($type->getValuesWithLabels() as $value => $label): ?>
     *         <option value="<?= $value ?>"><?= $label ?></option>
     *     <?php endforeach; ?>
     * </select>
     * 
     * // Pro radio buttons
     * <?php foreach ($type->getValuesWithLabels() as $value => $label): ?>
     *     <label>
     *         <input type="radio" name="priority" value="<?= $value ?>">
     *         <?= $label ?>
     *     </label>
     * <?php endforeach; ?>
     * ```
     */
    public function getValuesWithLabels(?string $locale = null): array
    {
        $locale = LocaleManager::resolveLocale($locale);
        $result = [];
        
        foreach ($this->values as $value) {
            $result[$value] = $this->format($value, $locale);
        }
        
        return $result;
    }
}