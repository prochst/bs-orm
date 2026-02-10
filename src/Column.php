<?php

declare(strict_types=1);

namespace prochst\bsOrm;

use Attribute;
use prochst\bsOrm\Types\TypeInterface;
use prochst\bsOrm\Types\StringType;
use prochst\bsOrm\Types\LocaleManager;

/**
 * Atribut pro definici metadata sloupce
 * 
 * Používá se na properties v entitách pro specifikaci vlastností sloupce:
 * - Název sloupce v databázi
 * - Datový typ (StringType, IntegerType, ...)
 * - Constraints (nullable, unique, primary key, ...)
 * - Validace
 * - Vícejazyčné labely, placeholdery, nápovědy
 * 
 * Column atribut slouží jako single source of truth pro všechny informace
 * o sloupci - od databázové struktury přes validaci až po UI zobrazení.
 * 
 * @package prochst\bsOrm
 * @author  Your Name
 * @version 1.0.0
 * 
 * @example
 * ```php
 * class User extends Entity
 * {
 *     #[Column(
 *         name: 'email',
 *         label: 'E-mail',
 *         labels: [
 *             'cs_CZ' => 'E-mailová adresa',
 *             'en_US' => 'Email Address',
 *         ],
 *         type: new StringType(maxLength: 255),
 *         nullable: false,
 *         unique: true,
 *         description: 'Unikátní e-mailová adresa uživatele',
 *         placeholder: 'Zadejte e-mail',
 *         placeholders: [
 *             'cs_CZ' => 'Zadejte e-mailovou adresu',
 *             'en_US' => 'Enter email address',
 *         ],
 *         validators: [
 *             fn($v) => filter_var($v, FILTER_VALIDATE_EMAIL) ?: 'Neplatný e-mail'
 *         ]
 *     )]
 *     private string $email;
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Column
{
    /**
     * Vytvoří nový Column atribut
     * 
     * @param string|null $name Název sloupce v DB (pokud null, použije se název property)
     * @param string|null $label Výchozí uživatelský název (fallback)
     * @param array<string, string>|null $labels Překlady názvu sloupce
     * @param TypeInterface $type Datový typ sloupce (určuje SQL typ, validaci, formátování)
     * @param bool $nullable Může být sloupec NULL?
     * @param bool $primaryKey Je primární klíč?
     * @param bool $autoIncrement Auto-increment (pouze pro integer primary keys)?
     * @param mixed $default Výchozí hodnota
     * @param int|null $length Maximální délka (pro stringy)
     * @param string|null $description Výchozí popis sloupce
     * @param array<string, string>|null $descriptions Překlady popisu
     * @param callable[] $validators Pole vlastních validátorů
     * @param bool $unique Má být sloupec unikátní?
     * @param string|null $placeholder Výchozí placeholder pro formuláře
     * @param array<string, string>|null $placeholders Překlady placeholderu
     * @param string|null $help Výchozí nápověda pro formuláře
     * @param array<string, string>|null $helps Překlady nápovědy
     */
    public function __construct(
        public ?string $name = null,
        public ?string $label = null,
        public ?array $labels = null,
        public TypeInterface $type = new StringType(),
        public bool $nullable = false,
        public bool $primaryKey = false,
        public bool $autoIncrement = false,
        public mixed $default = null,
        public ?int $length = null,
        public ?string $description = null,
        public ?array $descriptions = null,
        public array $validators = [],
        public bool $unique = false,
        public ?string $placeholder = null,
        public ?array $placeholders = null,
        public ?string $help = null,
        public ?array $helps = null,
    ) {
    }
    
    /**
     * Vrátí uživatelský název sloupce podle locale
     * 
     * Používá se v UI pro labely formulářových polí, hlavičky gridů, atd.
     * 
     * Hierarchie fallbacků:
     * 1. Překlad pro dané locale ($labels)
     * 2. Výchozí label ($label)
     * 3. Název property s prvním velkým písmenem
     * 
     * @param string $propertyName Název PHP property
     * @param string|null $locale Locale (např. 'cs_CZ')
     * 
     * @return string Přeložený label
     * 
     * @example
     * ```php
     * $column = new Column(
     *     label: 'Email',
     *     labels: ['cs_CZ' => 'E-mail', 'en_US' => 'Email']
     * );
     * 
     * echo $column->getLabel('email', 'cs_CZ'); // "E-mail"
     * echo $column->getLabel('email', 'en_US'); // "Email"
     * echo $column->getLabel('email', 'xx_XX'); // "Email" (fallback)
     * 
     * // Bez definovaného labelu
     * $column = new Column();
     * echo $column->getLabel('firstName', 'cs_CZ'); // "FirstName"
     * ```
     */
    public function getLabel(string $propertyName, ?string $locale = null): string
    {
        $locale = LocaleManager::resolveLocale($locale);
        
        // 1. Priorita: Překlad pro dané locale
        if ($this->labels !== null && isset($this->labels[$locale])) {
            return $this->labels[$locale];
        }
        
        // 2. Fallback na výchozí label
        if ($this->label !== null) {
            return $this->label;
        }
        
        // 3. Fallback na název vlastnosti s prvním velkým písmenem
        return ucfirst($propertyName);
    }
    
    /**
     * Vrátí název sloupce v databázi
     * 
     * Pokud není explicitně definován, použije se název property
     * 
     * @param string $propertyName Název PHP property
     * 
     * @return string Název sloupce v DB
     * 
     * @example
     * ```php
     * // Explicitní název
     * #[Column(name: 'user_email')]
     * private string $email;
     * // getColumnName('email') → "user_email"
     * 
     * // Implicitní (stejný jako property)
     * #[Column]
     * private string $email;
     * // getColumnName('email') → "email"
     * ```
     */
    public function getColumnName(string $propertyName): string
    {
        return $this->name ?? $propertyName;
    }
    
    /**
     * Vrátí popis sloupce podle locale
     * 
     * Používá se pro tooltips, help texty, dokumentaci
     * 
     * @param string|null $locale Locale
     * 
     * @return string|null Přeložený popis nebo null
     * 
     * @example
     * ```php
     * $column = new Column(
     *     descriptions: [
     *         'cs_CZ' => 'Unikátní e-mailová adresa uživatele',
     *         'en_US' => 'Unique user email address',
     *     ]
     * );
     * 
     * echo $column->getDescription('cs_CZ');
     * // "Unikátní e-mailová adresa uživatele"
     * 
     * // V šabloně
     * <input title="<?= $column->getDescription() ?>">
     * ```
     */
    public function getDescription(?string $locale = null): ?string
    {
        $locale = LocaleManager::resolveLocale($locale);
        
        if ($this->descriptions !== null && isset($this->descriptions[$locale])) {
            return $this->descriptions[$locale];
        }
        
        return $this->description;
    }
    
    /**
     * Vrátí placeholder podle locale
     * 
     * Používá se pro placeholder atribut v HTML input elementech
     * 
     * @param string|null $locale Locale
     * 
     * @return string|null Přeložený placeholder nebo null
     * 
     * @example
     * ```php
     * $column = new Column(
     *     placeholders: [
     *         'cs_CZ' => 'Zadejte e-mail',
     *         'en_US' => 'Enter email',
     *     ]
     * );
     * 
     * // V šabloně
     * <input placeholder="<?= $column->getPlaceholder() ?>">
     * ```
     */
    public function getPlaceholder(?string $locale = null): ?string
    {
        $locale = LocaleManager::resolveLocale($locale);
        
        if ($this->placeholders !== null && isset($this->placeholders[$locale])) {
            return $this->placeholders[$locale];
        }
        
        return $this->placeholder;
    }
    
    /**
     * Vrátí nápovědu podle locale
     * 
     * Používá se pro help text pod formulářovým polem
     * 
     * @param string|null $locale Locale
     * 
     * @return string|null Přeložená nápověda nebo null
     * 
     * @example
     * ```php
     * $column = new Column(
     *     helps: [
     *         'cs_CZ' => 'Použijte platnou e-mailovou adresu',
     *         'en_US' => 'Use a valid email address',
     *     ]
     * );
     * 
     * // V šabloně
     * <input>
     * <small><?= $column->getHelp() ?></small>
     * ```
     */
    public function getHelp(?string $locale = null): ?string
    {
        $locale = LocaleManager::resolveLocale($locale);
        
        if ($this->helps !== null && isset($this->helps[$locale])) {
            return $this->helps[$locale];
        }
        
        return $this->help;
    }
    
    /**
     * Vrátí všechny dostupné překlady labelu
     * 
     * @return array<string, string> Pole překladů
     * 
     * @example
     * ```php
     * $labels = $column->getAllLabels();
     * // ['cs_CZ' => 'E-mail', 'en_US' => 'Email', 'de_DE' => 'E-Mail']
     * ```
     */
    public function getAllLabels(): array
    {
        return $this->labels ?? [];
    }
    
    /**
     * Validuje hodnotu podle definovaných pravidel
     * 
     * Provádí následující kontroly:
     * 1. NULL hodnoty (pokud není nullable)
     * 2. Délka stringu (pokud je definována length)
     * 3. Validace typem (TypeInterface::validate())
     * 4. Vlastní validátory (callables)
     * 
     * @param mixed $value Hodnota k validaci
     * @param string|null $locale Locale pro chybové zprávy
     * 
     * @return string[] Pole chybových zpráv (prázdné = validní)
     * 
     * @example
     * ```php
     * $column = new Column(
     *     label: 'E-mail',
     *     type: new StringType(maxLength: 255),
     *     nullable: false,
     *     validators: [
     *         fn($v) => filter_var($v, FILTER_VALIDATE_EMAIL) ?: 'Neplatný e-mail'
     *     ]
     * );
     * 
     * // Validní hodnota
     * $errors = $column->validate('john@example.com');
     * // []
     * 
     * // NULL v non-nullable sloupci
     * $errors = $column->validate(null);
     * // ["E-mail nemůže být prázdný"]
     * 
     * // Příliš dlouhý string
     * $errors = $column->validate(str_repeat('a', 300));
     * // ["E-mail překračuje maximální délku 255"]
     * 
     * // Neplatný formát
     * $errors = $column->validate('invalid-email');
     * // ["Neplatný e-mail"]
     * 
     * // Použití v entitě
     * class UserRepository extends Repository
     * {
     *     public function save(Entity $entity): bool
     *     {
     *         $columns = $entity::getColumns();
     *         foreach ($columns as $propertyName => $column) {
     *             $value = $this->getPropertyValue($entity, $propertyName);
     *             $errors = $column->validate($value);
     *             
     *             if (!empty($errors)) {
     *                 throw new ValidationException(implode(', ', $errors));
     *             }
     *         }
     *         return parent::save($entity);
     *     }
     * }
     * ```
     */
    public function validate(mixed $value, ?string $locale = null): array
    {
        $errors = [];
        $locale = LocaleManager::resolveLocale($locale);
        $label = $this->getLabel($this->name ?? 'field', $locale);
        
        // Kontrola nullable
        if (!$this->nullable && $value === null) {
            $errors[] = "{$label} nemůže být prázdný";
        }
        
        // Kontrola délky pro stringy
        if ($this->length !== null && is_string($value) && strlen($value) > $this->length) {
            $errors[] = "{$label} překračuje maximální délku {$this->length}";
        }
        
        // Validace typem
        if ($value !== null) {
            $typeErrors = $this->type->validate($value);
            $errors = array_merge($errors, $typeErrors);
        }
        
        // Vlastní validátory
        foreach ($this->validators as $validator) {
            if (is_callable($validator)) {
                $result = $validator($value);
                if ($result !== true) {
                    $errors[] = is_string($result) ? $result : "Validace selhala";
                }
            }
        }
        
        return $errors;
    }
}