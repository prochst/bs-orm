<?php

declare(strict_types=1);

namespace prochst\bsOrm;

use prochst\bsOrm\Types\LocaleManager;

/**
 * Helper třída pro práci s překlady entit
 * 
 * TranslationHelper zjednodušuje získávání metadat a překladů z entit.
 * Užitečná pro:
 * - Export překladů pro frontend (JSON, JavaScript)
 * - Generování formulářů
 * - Generování gridů/tabulek
 * - API endpoints s metadaty
 * - Dokumentaci
 * 
 * @package prochst\bsOrm
 * @author  Your Name
 * @version 1.0.0
 * 
 * @example
 * ```php
 * use prochst\bsOrm\TranslationHelper;
 * use App\Model\User;
 * 
 * // Získání kompletních metadat entity
 * $metadata = TranslationHelper::getEntityMetadata(User::class, 'cs_CZ');
 * 
 * // Export překladů pro více jazyků
 * $translations = TranslationHelper::exportTranslations(
 *     User::class,
 *     ['cs_CZ', 'en_US', 'de_DE']
 * );
 * 
 * // Uložení pro frontend
 * file_put_contents(
 *     'frontend/translations/user.json',
 *     json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
 * );
 * ```
 */
class TranslationHelper
{
    /**
     * Vrátí přeložené metadata entity (tabulka + sloupce)
     * 
     * Kompletní informace o entitě v jednom volání.
     * 
     * @param string $entityClass Plně kvalifikovaný název třídy entity
     * @param string|null $locale Locale pro překlady
     * 
     * @return array Asociativní pole s metadaty
     * 
     * @example
     * ```php
     * $metadata = TranslationHelper::getEntityMetadata(User::class, 'cs_CZ');
     * 
     * // Struktura výsledku:
     * [
     *     'table' => [
     *         'name' => 'users',
     *         'label' => 'Uživatelé',
     *         'description' => 'Tabulka uživatelů',
     *         'allLabels' => ['cs_CZ' => 'Uživatelé', 'en_US' => 'Users', ...]
     *     ],
     *     'columns' => [
     *         'email' => [
     *             'name' => 'email',
     *             'label' => 'E-mailová adresa',
     *             'type' => 'App\Core\Orm\Types\StringType',
     *             'nullable' => false,
     *             ...
     *         ],
     *         ...
     *     ]
     * ]
     * 
     * // Použití v API
     * $app->get('/api/entities/{entity}/metadata', function($entity, $locale) {
     *     $class = "App\\Model\\" . ucfirst($entity);
     *     return json_encode(
     *         TranslationHelper::getEntityMetadata($class, $locale)
     *     );
     * });
     * ```
     */
    public static function getEntityMetadata(string $entityClass, ?string $locale = null): array
    {
        $locale = LocaleManager::resolveLocale($locale);
        
        return [
            'table' => self::getTableMetadata($entityClass, $locale),
            'columns' => self::getColumnsMetadata($entityClass, $locale),
        ];
    }
    
    /**
     * Vrátí metadata tabulky
     * 
     * @param string $entityClass Název třídy entity
     * @param string|null $locale Locale
     * 
     * @return array Metadata tabulky
     * 
     * @example
     * ```php
     * $table = TranslationHelper::getTableMetadata(User::class, 'cs_CZ');
     * 
     * // Výsledek:
     * [
     *     'name' => 'users',
     *     'label' => 'Uživatelé',
     *     'description' => 'Tabulka uživatelů systému',
     *     'allLabels' => [
     *         'cs_CZ' => 'Uživatelé',
     *         'en_US' => 'Users',
     *         'de_DE' => 'Benutzer',
     *     ]
     * ]
     * 
     * // Pro breadcrumb navigaci
     * echo "<a href='/users'>{$table['label']}</a>";
     * ```
     */
    public static function getTableMetadata(string $entityClass, ?string $locale = null): array
    {
        $locale = LocaleManager::resolveLocale($locale);
        $table = $entityClass::getTableAttribute();
        
        if (!$table) {
            return [];
        }
        
        return [
            'name' => $table->getTableName($entityClass),
            'label' => $table->getLabel($entityClass, $locale),
            'description' => $table->getDescription($locale),
            'allLabels' => $table->getAllLabels(),
        ];
    }
    
    /**
     * Vrátí metadata všech sloupců
     * 
     * @param string $entityClass Název třídy entity
     * @param string|null $locale Locale
     * 
     * @return array Pole metadat sloupců
     * 
     * @example
     * ```php
     * $columns = TranslationHelper::getColumnsMetadata(User::class, 'cs_CZ');
     * 
     * // Výsledek:
     * [
     *     'email' => [
     *         'name' => 'email',
     *         'label' => 'E-mailová adresa',
     *         'description' => 'Unikátní e-mail uživatele',
     *         'placeholder' => 'Zadejte e-mail',
     *         'help' => 'Použijte platný e-mail',
     *         'type' => 'App\Core\Orm\Types\StringType',
     *         'nullable' => false,
     *         'unique' => true,
     *         'primaryKey' => false,
     *         'length' => 255,
     *         'allLabels' => ['cs_CZ' => 'E-mail', 'en_US' => 'Email', ...]
     *     ],
     *     'name' => [...],
     *     ...
     * ]
     * 
     * // Pro generování formuláře
     * foreach ($columns as $field => $meta) {
     *     if ($meta['primaryKey']) continue;
     *     
     *     echo "<label>{$meta['label']}</label>";
     *     echo "<input name='{$field}' placeholder='{$meta['placeholder']}'";
     *     if (!$meta['nullable']) echo " required";
     *     echo ">";
     *     
     *     if ($meta['help']) {
     *         echo "<small>{$meta['help']}</small>";
     *     }
     * }
     * ```
     */
    public static function getColumnsMetadata(string $entityClass, ?string $locale = null): array
    {
        $locale = LocaleManager::resolveLocale($locale);
        $columns = $entityClass::getColumns();
        $metadata = [];
        
        foreach ($columns as $propertyName => $column) {
            $metadata[$propertyName] = [
                'name' => $column->getColumnName($propertyName),
                'label' => $column->getLabel($propertyName, $locale),
                'description' => $column->getDescription($locale),
                'placeholder' => $column->getPlaceholder($locale),
                'help' => $column->getHelp($locale),
                'type' => get_class($column->type),
                'nullable' => $column->nullable,
                'unique' => $column->unique,
                'primaryKey' => $column->primaryKey,
                'autoIncrement' => $column->autoIncrement,
                'length' => $column->length,
                'allLabels' => $column->getAllLabels(),
            ];
        }
        
        return $metadata;
    }
    
    /**
     * Exportuje překlady do strukturovaného pole
     * 
     * Ideální pro export do JSON souboru pro frontend aplikace.
     * 
     * @param string $entityClass Název třídy entity
     * @param string[] $locales Pole locale pro které chceme překlady
     * 
     * @return array Strukturované překlady pro všechny locale
     * 
     * @example
     * ```php
     * // Export pro frontend
     * $translations = TranslationHelper::exportTranslations(
     *     User::class,
     *     ['cs_CZ', 'en_US', 'de_DE', 'fr_FR']
     * );
     * 
     * // Výsledek:
     * [
     *     'cs_CZ' => [
     *         'table' => [
     *             'label' => 'Uživatelé',
     *             'description' => 'Tabulka uživatelů',
     *         ],
     *         'columns' => [
     *             'email' => [
     *                 'label' => 'E-mailová adresa',
     *                 'description' => '...',
     *                 'placeholder' => 'Zadejte e-mail',
     *                 'help' => '...',
     *             ],
     *             ...
     *         ]
     *     ],
     *     'en_US' => [...],
     *     'de_DE' => [...],
     *     'fr_FR' => [...]
     * ]
     * 
     * // Uložení do souboru
     * file_put_contents(
     *     'frontend/translations/user.json',
     *     json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
     * );
     * 
     * // Použití ve Vue.js
     * import userTranslations from './translations/user.json';
     * 
     * export default {
     *     data() {
     *         return {
     *             labels: userTranslations[this.$i18n.locale].columns
     *         }
     *     }
     * }
     * ```
     */
    public static function exportTranslations(string $entityClass, array $locales): array
    {
        $translations = [];
        
        foreach ($locales as $locale) {
            $translations[$locale] = [
                'table' => [
                    'label' => $entityClass::getTableLabel($locale),
                    'description' => $entityClass::getTableDescription($locale),
                ],
                'columns' => [],
            ];
            
            $columns = $entityClass::getColumns();
            foreach ($columns as $propertyName => $column) {
                $translations[$locale]['columns'][$propertyName] = [
                    'label' => $column->getLabel($propertyName, $locale),
                    'description' => $column->getDescription($locale),
                    'placeholder' => $column->getPlaceholder($locale),
                    'help' => $column->getHelp($locale),
                ];
            }
        }
        
        return $translations;
    }
}