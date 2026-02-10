<?php

declare(strict_types=1);

namespace prochst\bsOrm\Relations;

use Attribute;

/**
 * Relace HasMany (1:N) - entita má více souvisejících entit
 * 
 * Reprezentuje vztah, kdy jedna entita vlastní mnoho souvisejících entit.
 * Cizí klíč je uložen v související tabulce.
 * 
 * Příklady:
 * - User hasMany Posts (v tabulce posts je sloupec user_id)
 * - Category hasMany Products (v tabulce products je sloupec category_id)
 * - Author hasMany Books (v tabulce books je sloupec author_id)
 * 
 * Struktura v databázi:
 * ```
 * users                posts
 * -----                -----
 * id (PK)             id (PK)
 * name                user_id (FK) → users.id
 * email               title
 *                     content
 *                     
 * Jeden user může mít mnoho postů
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
 *     #[HasMany(entityClass: Post::class)]
 *     private array $posts = [];
 *     
 *     public function getPosts(): array
 *     {
 *         return $this->posts;
 *     }
 * }
 * 
 * // Použití
 * $user = $userRepo->find(1);
 * $user->loadRelation('posts', $repo);
 * foreach ($user->getPosts() as $post) {
 *     echo $post->getTitle();
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class HasMany implements RelationInterface
{
    /**
     * Třída, která vlastní tuto relaci
     * 
     * Nastavuje se automaticky v Entity::getRelations() a používá se
     * pro odvození cizího klíče, pokud není explicitně zadán.
     * 
     * Např. User hasMany Post → ownerClass = User::class → FK = user_id
     * 
     * @var string|null
     */
    public ?string $ownerClass = null;
    
    /**
     * Konstruktor HasMany relace
     * 
     * @param string      $entityClass   Plně kvalifikovaný název třídy související entity
     *                                    (např. App\Model\Post::class)
     * @param string|null $foreignKey    Název cizího klíče v související tabulce.
     *                                    Pokud null, odvozuje se automaticky z ownerClass
     *                                    (např. User → user_id)
     * @param string      $localKey      Název klíče v této tabulce (obvykle primární klíč "id").
     *                                    Hodnota tohoto klíče se porovnává s cizím klíčem
     *                                    v související tabulce.
     * @param string|null $label         Uživatelsky přívětivý název relace pro UI
     *                                    (např. "Příspěvky uživatele")
     * @param string|null $description   Popis relace pro dokumentaci a nápovědu
     * 
     * @example
     * ```php
     * // Automatické odvození FK (user_id)
     * #[HasMany(entityClass: Post::class)]
     * private array $posts = [];
     * 
     * // Explicitní FK a label
     * #[HasMany(
     *     entityClass: Post::class,
     *     foreignKey: 'author_id',
     *     label: 'Napsané příspěvky'
     * )]
     * private array $posts = [];
     * 
     * // S popisem
     * #[HasMany(
     *     entityClass: Comment::class,
     *     description: 'Komentáře napsané uživatelem'
     * )]
     * private array $comments = [];
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
     * Načte všechny související entity z databáze
     * 
     * Implementace je v Repository::loadRelation()
     * 
     * @return mixed Pole načtených entit
     */
    public function load(): mixed
    {
        return null;
    }
    
    /**
     * Vrátí název cizího klíče v související tabulce
     * 
     * Pokud není explicitně nastaven, odvozuje se z názvu vlastnické třídy:
     * - User → user_id
     * - BlogPost → blog_post_id
     * - ProductCategory → product_category_id
     * 
     * @return string Název cizího klíče (např. "user_id")
     * 
     * @example
     * ```php
     * $relation = new HasMany(entityClass: Post::class);
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
     * - ProductCategory → product_category_id
     * 
     * Důležité: FK se odvozuje z VLASTNICKÉ třídy (ownerClass), nikoli ze související!
     * User hasMany Post → FK v posts je "user_id" (z User, ne Post)
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