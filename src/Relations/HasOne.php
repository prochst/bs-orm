<?php

declare(strict_types=1);

namespace prochst\bsOrm\Relations;

use prochst\bsOrm\Entity;
use prochst\bsOrm\Repository;
use Attribute;

/**
 * Relace HasOne (1:1) - entita má jednu související entitu
 * 
 * Reprezentuje vztah, kdy jedna entita vlastní právě jednu související entitu.
 * Cizí klíč je uložen v související tabulce.
 * 
 * Příklady:
 * - User hasOne Profile (v tabulce profiles je sloupec user_id)
 * - Order hasOne Invoice (v tabulce invoices je sloupec order_id)
 * - Company hasOne Address (v tabulce addresses je sloupec company_id)
 * 
 * Struktura v databázi:
 * ```
 * users                profiles
 * -----                --------
 * id (PK)             id (PK)
 * name                user_id (FK) → users.id
 * email               bio
 *                     avatar
 * ```
 * 
 * @package prochst\bsOrm\Relations
 * @author  Your Name
 * @version 1.0.0
 * 
 * @example
 * ```php
 * #[Table(name: 'users')]
 * class User extends Entity
 * {
 *     #[Column(type: new IntegerType(), primaryKey: true)]
 *     private ?int $id = null;
 *     
 *     #[HasOne(entityClass: Profile::class)]
 *     private ?Profile $profile = null;
 *     
 *     public function getProfile(): ?Profile
 *     {
 *         return $this->profile;
 *     }
 * }
 * 
 * // Použití
 * $user = $userRepo->find(1);
 * $user->loadRelation('profile', $repo);
 * echo $user->getProfile()->getBio();
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class HasOne implements RelationInterface
{
    /**
     * Třída, která vlastní tuto relaci
     * 
     * Nastavuje se automaticky v Entity::getRelations() a používá se
     * pro odvození cizího klíče, pokud není explicitně zadán.
     * 
     * Např. User hasOne Profile → ownerClass = User::class → FK = user_id
     * 
     * @var string|null
     */
    public ?string $ownerClass = null;
    
    /**
     * Konstruktor HasOne relace
     * 
     * @param string      $entityClass   Plně kvalifikovaný název třídy související entity
     *                                    (např. App\Model\Profile::class)
     * @param string|null $foreignKey    Název cizího klíče v související tabulce.
     *                                    Pokud null, odvozuje se automaticky z ownerClass
     *                                    (např. User → user_id)
     * @param string      $localKey      Název klíče v této tabulce (obvykle primární klíč "id").
     *                                    Hodnota tohoto klíče se porovnává s cizím klíčem
     *                                    v související tabulce.
     * @param string|null $label         Uživatelsky přívětivý název relace pro UI
     *                                    (např. "Profil uživatele")
     * @param string|null $description   Popis relace pro dokumentaci a nápovědu
     * 
     * @example
     * ```php
     * // Automatické odvození FK (user_id)
     * #[HasOne(entityClass: Profile::class)]
     * private ?Profile $profile = null;
     * 
     * // Explicitní FK (vlastní název)
     * #[HasOne(
     *     entityClass: Profile::class,
     *     foreignKey: 'owner_id',
     *     label: 'Uživatelský profil'
     * )]
     * private ?Profile $profile = null;
     * ```
     */
    public function __construct(
        public string $entityClass,
        public ?string $foreignKey = null,
        public string $localKey = 'id',
        public ?string $label = null,
        public ?string $description = null,
    ) {
    }
    
    /**
     * Načte související entitu z databáze
     * 
     * Implementace je v Repository::loadRelation()
     * 
     * @return mixed Načtená entita nebo null
     */
    public function load(): mixed
    {
        // Implementace v Repository
        return null;
    }
    
    /**
     * Vrátí název cizího klíče v související tabulce
     * 
     * Pokud není explicitně nastaven, odvozuje se z názvu vlastnické třídy:
     * - User → user_id
     * - BlogPost → blog_post_id
     * - CompanyProfile → company_profile_id
     * 
     * @return string Název cizího klíče (např. "user_id")
     * 
     * @example
     * ```php
     * $relation = new HasOne(entityClass: Profile::class);
     * // S ownerClass = User::class
     * echo $relation->getForeignKey(); // "user_id"
     * ```
     */
    public function getForeignKey(): string
    {
        return $this->foreignKey ?? $this->guessedForeignKey();
    }
    
    /**
     * Vrátí název lokálního klíče v této tabulce
     * 
     * Obvykle je to primární klíč "id".
     * 
     * @return string Název lokálního klíče
     */
    public function getLocalKey(): string
    {
        return $this->localKey;
    }
    
    /**
     * Automaticky odvozuje název cizího klíče z vlastnické třídy
     * 
     * Převádí název třídy na snake_case a přidává "_id":
     * - User → user_id
     * - BlogPost → blog_post_id
     * - CompanyProfile → company_profile_id
     * 
     * Důležité: FK se odvozuje z VLASTNICKÉ třídy (ownerClass), nikoli ze související!
     * User hasOne Profile → FK v profiles je "user_id" (z User, ne Profile)
     * 
     * @return string Odvozený název cizího klíče
     */
    private function guessedForeignKey(): string
    {
        // FK se odvozuje z vlastnické třídy (User → user_id), ne ze související
        $sourceClass = $this->ownerClass ?? $this->entityClass;
        $reflection = new \ReflectionClass($sourceClass);
        $shortName = $reflection->getShortName();
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName)) . '_id';
    }
}