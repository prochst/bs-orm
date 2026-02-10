<?php

declare(strict_types=1);

namespace prochst\bsOrm\Relations;

use Attribute;

/**
 * Relace BelongsTo (N:1) - entita patří k jiné entitě
 * 
 * Reprezentuje inverzní stranu HasOne nebo HasMany relace.
 * Cizí klíč je uložen v TÉTO tabulce a odkazuje na rodičovskou entitu.
 * 
 * Příklady:
 * - Post belongsTo User (v tabulce posts je sloupec user_id)
 * - Comment belongsTo Post (v tabulce comments je sloupec post_id)
 * - Product belongsTo Category (v tabulce products je sloupec category_id)
 * 
 * Struktura v databázi:
 * ```
 * posts                users
 * -----                -----
 * id (PK)             id (PK)
 * user_id (FK) →      name
 * title               email
 * content
 * 
 * Každý post patří jednomu uživateli
 * ```
 * 
 * @package prochst\bsOrm\Relations
 * @author  Your Name
 * @version 1.0.0
 * 
 * @example
 * ```php
 * #[Table(name: 'posts')]
 * class Post extends Entity
 * {
 *     #[Column(type: new IntegerType(), primaryKey: true)]
 *     private ?int $id = null;
 *     
 *     #[Column(type: new IntegerType())]
 *     private ?int $user_id = null;
 *     
 *     #[BelongsTo(entityClass: User::class)]
 *     private ?User $author = null;
 *     
 *     public function getAuthor(): ?User
 *     {
 *         return $this->author;
 *     }
 * }
 * 
 * // Použití
 * $post = $postRepo->find(1);
 * $post->loadRelation('author', $repo);
 * echo $post->getAuthor()->getName();
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class BelongsTo implements RelationInterface
{
    /**
     * Konstruktor BelongsTo relace
     * 
     * @param string      $entityClass   Plně kvalifikovaný název třídy rodičovské entity
     *                                    (např. App\Model\User::class)
     * @param string|null $foreignKey    Název cizího klíče V TÉTO tabulce.
     *                                    Pokud null, odvozuje se automaticky z entityClass
     *                                    (např. User → user_id)
     * @param string      $ownerKey      Název klíče v rodičovské tabulce (obvykle primární klíč "id").
     *                                    Hodnota cizího klíče se porovnává s tímto klíčem.
     * @param string|null $label         Uživatelsky přívětivý název relace pro UI
     *                                    (např. "Autor příspěvku")
     * @param string|null $description   Popis relace pro dokumentaci a nápovědu
     * 
     * @example
     * ```php
     * // Automatické odvození FK (user_id)
     * #[BelongsTo(entityClass: User::class)]
     * private ?User $author = null;
     * 
     * // Explicitní FK (vlastní název)
     * #[BelongsTo(
     *     entityClass: User::class,
     *     foreignKey: 'created_by',
     *     label: 'Vytvořil'
     * )]
     * private ?User $creator = null;
     * 
     * // S neobvyklým ownerKey
     * #[BelongsTo(
     *     entityClass: User::class,
     *     foreignKey: 'user_uuid',
     *     ownerKey: 'uuid'
     * )]
     * private ?User $user = null;
     * ```
     */
    public function __construct(
        public string $entityClass,
        public ?string $foreignKey = null,
        public string $ownerKey = 'id',
        public ?string $label = null,
        public ?string $description = null,
    ) {
    }
    
    /**
     * Načte rodičovskou entitu z databáze
     * 
     * Implementace je v Repository::loadRelation()
     * 
     * @return mixed Načtená entita nebo null
     */
    public function load(): mixed
    {
        return null;
    }
    
    /**
     * Vrátí název cizího klíče v této tabulce
     * 
     * Pokud není explicitně nastaven, odvozuje se z názvu rodičovské třídy:
     * - User → user_id
     * - BlogPost → blog_post_id
     * - Category → category_id
     * 
     * @return string Název cizího klíče (např. "user_id")
     * 
     * @example
     * ```php
     * $relation = new BelongsTo(entityClass: User::class);
     * echo $relation->getForeignKey(); // "user_id"
     * ```
     */
    public function getForeignKey(): string
    {
        return $this->foreignKey ?? $this->guessedForeignKey();
    }
    
    /**
     * Vrátí název klíče v rodičovské tabulce
     * 
     * Obvykle je to primární klíč "id".
     * 
     * @return string Název klíče v rodičovské tabulce
     */
    public function getLocalKey(): string
    {
        return $this->ownerKey;
    }
    
    /**
     * Automaticky odvozuje název cizího klíče z rodičovské třídy
     * 
     * Převádí název třídy na snake_case a přidává "_id":
     * - User → user_id
     * - BlogPost → blog_post_id
     * - Category → category_id
     * 
     * @return string Odvozený název cizího klíče
     */
    private function guessedForeignKey(): string
    {
        $reflection = new \ReflectionClass($this->entityClass);
        $shortName = $reflection->getShortName();
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName)) . '_id';
    }
}