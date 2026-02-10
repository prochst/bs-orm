<?php

declare(strict_types=1);

namespace prochst\bsOrm\Relations;

/**
 * Interface pro ORM relace
 * 
 * Definuje kontrakt pro všechny typy relací mezi entitami (HasOne, HasMany, BelongsTo, BelongsToMany).
 * Každá relace musí implementovat metody pro načítání dat a získávání klíčů.
 * 
 * Relace umožňují:
 * - Lazy loading: načtení dat až při prvním přístupu
 * - Eager loading: předčasné načtení pro optimalizaci dotazů
 * - Automatické odvození cizích klíčů z názvů tříd
 * 
 * @package prochst\bsOrm\Relations
 * @author  Your Name
 * @version 1.0.0
 * 
 * @example
 * ```php
 * // HasOne relace
 * #[HasOne(entityClass: Profile::class)]
 * private ?Profile $profile = null;
 * 
 * // HasMany relace
 * #[HasMany(entityClass: Post::class)]
 * private array $posts = [];
 * 
 * // BelongsTo relace
 * #[BelongsTo(entityClass: User::class)]
 * private ?User $author = null;
 * 
 * // BelongsToMany relace
 * #[BelongsToMany(entityClass: Role::class, pivotTable: 'user_roles')]
 * private array $roles = [];
 * ```
 */
interface RelationInterface
{
    /**
     * Načte související data z databáze
     * 
     * Implementace závisí na typu relace:
     * - HasOne: vrací jednu entitu nebo null
     * - HasMany: vrací pole entit
     * - BelongsTo: vrací jednu entitu nebo null
     * - BelongsToMany: vrací pole entit
     * 
     * @return mixed Entity, pole entit nebo null
     * 
     * @example
     * ```php
     * // V Repository
     * $user = $userRepo->find(1);
     * $user->loadRelation('posts', $repository);
     * ```
     */
    public function load(): mixed;
    
    /**
     * Vrátí název cizího klíče pro tuto relaci
     * 
     * Pro HasOne/HasMany: klíč v související tabulce odkazující na tuto entitu
     * Pro BelongsTo: klíč v této tabulce odkazující na rodičovskou entitu
     * Pro BelongsToMany: klíč v pivotní tabulce odkazující na související entitu
     * 
     * Pokud není explicitně nastaven, automaticky se odvozuje z názvu třídy:
     * - User → user_id
     * - BlogPost → blog_post_id
     * 
     * @return string Název cizího klíče (např. "user_id", "post_id")
     * 
     * @example
     * ```php
     * $relation = new HasMany(entityClass: Post::class);
     * echo $relation->getForeignKey(); // "user_id" (odvozeno z User)
     * 
     * $relation = new HasMany(entityClass: Post::class, foreignKey: 'author_id');
     * echo $relation->getForeignKey(); // "author_id" (explicitně nastaveno)
     * ```
     */
    public function getForeignKey(): string;
    
    /**
     * Vrátí název lokálního klíče pro tuto relaci
     * 
     * Pro HasOne/HasMany: klíč v této tabulce (obvykle primární klíč)
     * Pro BelongsTo: klíč v rodičovské tabulce (obvykle primární klíč)
     * Pro BelongsToMany: klíč v této tabulce (obvykle primární klíč)
     * 
     * Výchozí hodnota je "id".
     * 
     * @return string Název lokálního klíče (např. "id", "user_id")
     * 
     * @example
     * ```php
     * $relation = new HasMany(entityClass: Post::class);
     * echo $relation->getLocalKey(); // "id" (výchozí)
     * 
     * $relation = new HasMany(entityClass: Post::class, localKey: 'user_uuid');
     * echo $relation->getLocalKey(); // "user_uuid" (vlastní klíč)
     * ```
     */
    public function getLocalKey(): string;
}