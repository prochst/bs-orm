<?php

declare(strict_types=1);

namespace prochst\bsOrm;

use Attribute;
use prochst\bsOrm\Types\LocaleManager;

/**
 * Atribut pro definici metadata tabulky
 * 
 * Používá se na třídách entit pro specifikaci vlastností tabulky:
 * - Název tabulky v databázi
 * - Uživatelské názvy (labely) v různých jazycích
 * - Popis tabulky
 * - Indexy
 * - Cizí klíče
 * 
 * @package prochst\bsOrm
 * @author  Your Name
 * @version 1.0.0
 * 
 * @example
 * ```php
 * #[Table(
 *     name: 'users',
 *     label: 'Uživatelé',
 *     labels: [
 *         'cs_CZ' => 'Uživatelé',
 *         'en_US' => 'Users',
 *         'de_DE' => 'Benutzer',
 *     ],
 *     description: 'Tabulka uživatelů systému',
 *     indexes: [
 *         new Index('idx_email', ['email'], unique: true),
 *         new Index('idx_created', ['created_at']),
 *     ],
 *     foreignKeys: [
 *         new ForeignKey('fk_user_role', ['role_id'], 'roles', ['id']),
 *     ]
 * )]
 * class User extends Entity { ... }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Table
{
    /**
     * Vytvoří nový Table atribut
     * 
     * @param string|null $name Název tabulky v DB (pokud null, odvodí se z názvu třídy)
     * @param string|null $label Výchozí uživatelský název (fallback)
     * @param array<string, string>|null $labels Překlady názvu ['cs_CZ' => 'Název', ...]
     * @param string|null $description Výchozí popis tabulky
     * @param array<string, string>|null $descriptions Překlady popisu
     * @param Index[] $indexes Pole definic indexů
     * @param ForeignKey[] $foreignKeys Pole definic cizích klíčů
     */
    public function __construct(
        public ?string $name = null,
        public ?string $label = null,
        public ?array $labels = null,
        public ?string $description = null,
        public ?array $descriptions = null,
        public array $indexes = [],
        public array $foreignKeys = [],
    ) {
    }
    
    /**
     * Vrátí název tabulky v databázi
     * 
     * Pokud není $name specifikován, automaticky převede název třídy
     * na snake_case (např. UserProfile -> user_profile)
     * 
     * @param string $className Plně kvalifikovaný název třídy
     * 
     * @return string Název tabulky
     * 
     * @example
     * ```php
     * $table = User::getTableAttribute();
     * echo $table->getTableName(User::class); // "users"
     * ```
     */
    public function getTableName(string $className): string
    {
        if ($this->name !== null) {
            return $this->name;
        }
        
        // Převod názvu třídy na snake_case
        $shortName = (new \ReflectionClass($className))->getShortName();
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName));
    }
    
    /**
     * Vrátí uživatelský název tabulky podle locale
     * 
     * Hierarchie fallbacků:
     * 1. Překlad pro dané locale ($labels)
     * 2. Výchozí label ($label)
     * 3. Název třídy
     * 
     * @param string $className Název třídy
     * @param string|null $locale Locale (např. 'cs_CZ')
     * 
     * @return string Přeložený název tabulky
     * 
     * @example
     * ```php
     * $table = User::getTableAttribute();
     * echo $table->getLabel(User::class, 'cs_CZ'); // "Uživatelé"
     * echo $table->getLabel(User::class, 'en_US'); // "Users"
     * echo $table->getLabel(User::class, 'xx_XX'); // "Uživatelé" (fallback na $label)
     * ```
     */
    public function getLabel(string $className, ?string $locale = null): string
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
        
        // 3. Fallback na název třídy
        return (new \ReflectionClass($className))->getShortName();
    }
    
    /**
     * Vrátí popis tabulky podle locale
     * 
     * @param string|null $locale Locale
     * 
     * @return string|null Přeložený popis nebo null
     * 
     * @example
     * ```php
     * $description = $table->getDescription('cs_CZ');
     * // "Tabulka obsahuje údaje o uživatelích systému"
     * ```
     */
    public function getDescription(?string $locale = null): ?string
    {
        $locale = LocaleManager::resolveLocale($locale);
        
        // 1. Priorita: Překlad popisu
        if ($this->descriptions !== null && isset($this->descriptions[$locale])) {
            return $this->descriptions[$locale];
        }
        
        // 2. Fallback na výchozí popis
        return $this->description;
    }
    
    /**
     * Vrátí všechny dostupné překlady labelu
     * 
     * @return array<string, string> Pole překladů
     * 
     * @example
     * ```php
     * $labels = $table->getAllLabels();
     * // ['cs_CZ' => 'Uživatelé', 'en_US' => 'Users', 'de_DE' => 'Benutzer']
     * ```
     */
    public function getAllLabels(): array
    {
        return $this->labels ?? [];
    }
}