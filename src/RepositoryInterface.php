<?php

declare(strict_types=1);

namespace prochst\bsOrm;

/**
 * Interface pro Repository Pattern
 * 
 * Definuje kontrakt pro datový přístup k entitám.
 * Umožňuje snadné testování (mock) a výměnu implementace
 * (např. InMemoryRepository pro testy, CachedRepository pro produkci).
 * 
 * @package prochst\bsOrm
 * @author  Your Name
 * @version 1.0.0
 * 
 * @example
 * ```php
 * // Produkční implementace
 * class UserRepository extends Repository implements RepositoryInterface
 * {
 *     public function __construct(Dbal $dbal)
 *     {
 *         parent::__construct($dbal, User::class);
 *     }
 * }
 * 
 * // Mock pro testy
 * class InMemoryUserRepository implements RepositoryInterface
 * {
 *     private array $users = [];
 *     
 *     public function find(mixed $id): ?Entity { ... }
 *     public function findAll(): array { ... }
 *     // ...
 * }
 * 
 * // DI Container
 * // services:
 * //   - App\Model\UserRepository
 * // nebo pro testy:
 * //   - App\Tests\InMemoryUserRepository
 * ```
 */
interface RepositoryInterface
{
    /**
     * Najde entitu podle primárního klíče
     * 
     * @param mixed $id Hodnota primárního klíče
     * 
     * @return Entity|null Entity nebo null pokud neexistuje
     */
    public function find(mixed $id): ?Entity;
    
    /**
     * Najde všechny entity v tabulce
     * 
     * @return Entity[] Pole entit
     */
    public function findAll(): array;
    
    /**
     * Najde entity podle kritérií
     * 
     * @param array<string, mixed>       $criteria Kritéria pro WHERE
     * @param array<string, string>|null $orderBy  Řazení
     * @param int|null                   $limit    Maximum záznamů
     * @param int|null                   $offset   Offset
     * 
     * @return Entity[] Pole entit
     */
    public function findBy(
        array $criteria, 
        ?array $orderBy = null, 
        ?int $limit = null, 
        ?int $offset = null
    ): array;
    
    /**
     * Uloží entitu (INSERT nebo UPDATE)
     * 
     * @param Entity $entity Entita k uložení
     * 
     * @return bool True pokud operace uspěla
     */
    public function save(Entity $entity): bool;
    
    /**
     * Smaže entitu
     * 
     * @param Entity $entity Entita ke smazání
     * 
     * @return bool True pokud DELETE uspěl
     */
    public function delete(Entity $entity): bool;
    
    /**
     * Spočítá počet záznamů
     * 
     * @param array<string, mixed> $criteria Kritéria pro WHERE
     * 
     * @return int Počet záznamů
     */
    public function count(array $criteria = []): int;
    
    /**
     * Najde entitu s načtenými relacemi
     * 
     * @param mixed    $id        Hodnota primárního klíče
     * @param string[] $relations Názvy relací k načtení
     * 
     * @return Entity|null Entity s relacemi nebo null
     */
    public function findWithRelations(mixed $id, array $relations = []): ?Entity;
    
    /**
     * Najde všechny entity s relacemi (batch eager loading)
     * 
     * @param string[] $relations Názvy relací k načtení
     * 
     * @return Entity[] Pole entit s relacemi
     */
    public function findAllWithRelations(array $relations = []): array;
    
    /**
     * Najde entity podle kritérií s relacemi (batch eager loading)
     * 
     * @param array<string, mixed>       $criteria  Kritéria pro WHERE
     * @param string[]                   $relations Názvy relací k načtení
     * @param array<string, string>|null $orderBy   Řazení
     * @param int|null                   $limit     Maximum záznamů
     * @param int|null                   $offset    Offset
     * 
     * @return Entity[] Pole entit s relacemi
     */
    public function findByWithRelations(
        array $criteria, 
        array $relations = [], 
        ?array $orderBy = null, 
        ?int $limit = null,
        ?int $offset = null
    ): array;
}
