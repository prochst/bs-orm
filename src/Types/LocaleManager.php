<?php

declare(strict_types=1);

namespace prochst\bsOrm\Types;

/**
 * Správce národních prostředí (locale)
 * 
 * LocaleManager slouží jako centrální bod pro správu aktuálního locale
 * v celé aplikaci. Všechny datové typy ho používají pro formátování a parsing.
 * 
 * Výhody centralizované správy:
 * - Jeden bod pro změnu locale pro celou aplikaci
 * - Konzistentní formátování napříč všemi typy
 * - Snadné testování s různými locale
 * - Možnost detekce locale z prostředí
 * 
 * @package prochst\bsOrm\Types
 * @author  Your Name
 * @version 1.0.0
 * 
 * @example
 * ```php
 * // Nastavení globálního locale při startu aplikace
 * LocaleManager::setDefaultLocale('cs_CZ');
 * 
 * // Všechny typy nyní používají cs_CZ
 * $intType = new IntegerType();
 * echo $intType->format(1234567); // "1 234 567" (české formátování)
 * 
 * // Lokální override pro konkrétní operaci
 * echo $intType->format(1234567, 'en_US'); // "1,234,567"
 * 
 * // V Nette aplikaci můžete nastavit podle uživatele
 * class BasePresenter extends Nette\Application\UI\Presenter
 * {
 *     protected function startup()
 *     {
 *         parent::startup();
 *         $locale = $this->user->isLoggedIn() 
 *             ? $this->user->getIdentity()->locale 
 *             : 'cs_CZ';
 *         LocaleManager::setDefaultLocale($locale);
 *     }
 * }
 * ```
 */
class LocaleManager
{
    /**
     * Výchozí locale pro celou aplikaci
     * 
     * @var string|null
     */
    private static ?string $defaultLocale = null;
    
    /**
     * Nastaví výchozí locale pro celou aplikaci
     * 
     * Toto locale bude použito pro všechny formátovací a parsing operace,
     * pokud není explicitně specifikováno jiné.
     * 
     * @param string $locale Locale ve formátu 'jazyk_ZEMĚ' (např. 'cs_CZ', 'en_US')
     * 
     * @return void
     * 
     * @example
     * ```php
     * // V bootstrap.php nebo config
     * LocaleManager::setDefaultLocale('cs_CZ');
     * 
     * // Podle nastavení uživatele
     * $userLocale = $user->getPreferredLocale();
     * LocaleManager::setDefaultLocale($userLocale);
     * 
     * // Z HTTP hlavičky Accept-Language
     * $locale = Locale::acceptFromHttp($_SERVER['HTTP_ACCEPT_LANGUAGE']);
     * LocaleManager::setDefaultLocale($locale);
     * ```
     */
    public static function setDefaultLocale(string $locale): void
    {
        self::$defaultLocale = $locale;
    }
    
    /**
     * Vrátí výchozí locale
     * 
     * Hierarchie zjišťování locale:
     * 1. Explicitně nastavené locale (setDefaultLocale)
     * 2. Systémové locale (Locale::getDefault() z Intl extension)
     * 3. Fallback na 'cs_CZ'
     * 
     * @return string Aktuální výchozí locale
     * 
     * @example
     * ```php
     * $currentLocale = LocaleManager::getDefaultLocale();
     * echo "Aplikace používá locale: $currentLocale";
     * 
     * // Podmíněné formátování
     * if (LocaleManager::getDefaultLocale() === 'cs_CZ') {
     *     echo "Česká verze aplikace";
     * }
     * ```
     */
    public static function getDefaultLocale(): string
    {
        if (self::$defaultLocale !== null) {
            return self::$defaultLocale;
        }
        
        // Pokusíme se zjistit z prostředí (Intl extension)
        if (class_exists(\Locale::class) && $locale = \Locale::getDefault()) {
            return $locale;
        }
        
        return 'cs_CZ'; // Fallback pro české prostředí
    }
    
    /**
     * Vrátí locale, preferuje předaný, jinak vrátí výchozí
     * 
     * Helper metoda pro pohodlné řešení "použij toto locale, nebo výchozí".
     * Používána interně ve všech datových typech.
     * 
     * @param string|null $locale Preferované locale nebo null
     * 
     * @return string Výsledné locale
     * 
     * @example
     * ```php
     * // Interní použití v typech
     * public function format(mixed $value, ?string $locale = null): string
     * {
     *     $locale = LocaleManager::resolveLocale($locale);
     *     // Nyní máme zaručeně platné locale
     *     // ...
     * }
     * 
     * // Manuální použití
     * $locale = LocaleManager::resolveLocale($_GET['locale'] ?? null);
     * // Pokud je $_GET['locale'] nastaveno, použije se, jinak výchozí
     * ```
     */
    public static function resolveLocale(?string $locale): string
    {
        return $locale ?? self::getDefaultLocale();
    }
}