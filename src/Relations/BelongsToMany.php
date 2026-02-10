<?php

declare(strict_types=1);

namespace prochst\bsOrm\Relations;

use Attribute;

/**
 * Relace BelongsToMany (M:N) - entita má více souvisejících entit přes pivotní tabulku
 * 
 * Reprezentuje vztah mnoho-k-mnoha, který vyžaduje prostřední (pivotní) tabulku
 * obsahující cizí klíče obou entit.
 * 
 * Příklady:
 * - User belongsToMany Roles (přes user_roles)
 * - Product belongsToMany Tags (přes product_tags)
 * - Student belongsToMany Courses (přes enrollments)
 * 
 * Struktura v databázi:
 * ```
 * users            user_roles         roles
 * -----            ----------         -----
 * id (PK)          user_id (FK) →    id (PK)
 * name             role_id (FK) →    name
 * email                              permissions
 * 
 * Jeden user může mít více rolí
 * Jedna role může patřit více uživatelům
 * ```
 * 
 * Pivotní tabulka:
 * - Obvykle pojmenovaná podle obou tabulek v abecedním pořadí: role_user, product_tag
 * - Obsahuje cizí klíče k oběma tabulkám
 * - Může obsahovat další sloupce (timestamp vytvoření, extra data)
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
 *     #[BelongsToMany(
 *         entityClass: Role::class,
 *         pivotTable: 'user_roles'
 *     )]
 *     private array $roles = [];
 *     
 *     public function getRoles(): array
 *     {
 *         return $this->roles;
 *     }
 * }
 * 
 * // Použití
 * $user = $userRepo->find(1);
 * $user->loadRelation('roles', $repo);
 * foreach ($user->getRoles() as $role) {
 *     echo $role->getName();
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class BelongsToMany implements RelationInterface
{
    /**
     * Konstruktor BelongsToMany relace
     * 
     * @param string      $entityClass       Plně kvalifikovaný název třídy související entity
     *                                        (např. App\Model\Role::class)
     * @param string|null $pivotTable        Název pivotní (spojovací) tabulky.
     *                                        Pokud null, odvozuje se z názvů obou tabulek
     *                                        v abecedním pořadí (např. role_user, product_tag)
     * @param string|null $foreignPivotKey   Název klíče v pivotní tabulce odkazující na TUTO entitu.
     *                                        Pokud null, odvozuje se z názvu této třídy
     *                                        (např. User → user_id)
     * @param string|null $relatedPivotKey   Název klíče v pivotní tabulce odkazující na SOUVISEJÍCÍ entitu.
     *                                        Pokud null, odvozuje se z entityClass
     *                                        (např. Role → role_id)
     * @param string      $localKey          Název klíče v této tabulce (obvykle primární klíč "id").
     *                                        Hodnota tohoto klíče se porovnává s foreignPivotKey
     *                                        v pivotní tabulce.
     * @param string      $relatedKey        Název klíče v související tabulce (obvykle primární klíč "id").
     *                                        Hodnota tohoto klíče se porovnává s relatedPivotKey
     *                                        v pivotní tabulce.
     * @param string|null $label             Uživatelsky přívětivý název relace pro UI
     *                                        (např. "Role uživatele")
     * @param string|null $description       Popis relace pro dokumentaci a nápovědu
     * 
     * @example
     * ```php
     * // Automatické odvození všech názvů
     * #[BelongsToMany(entityClass: Role::class)]
     * private array $roles = [];
     * // pivotTable: "role_user"
     * // foreignPivotKey: "user_id"
     * // relatedPivotKey: "role_id"
     * 
     * // Explicitní pivotní tabulka
     * #[BelongsToMany(
     *     entityClass: Role::class,
     *     pivotTable: 'user_roles'
     * )]
     * private array $roles = [];
     * 
     * // Vlastní názvy klíčů
     * #[BelongsToMany(
     *     entityClass: Tag::class,
     *     pivotTable: 'product_tags',
     *     foreignPivotKey: 'product_id',
     *     relatedPivotKey: 'tag_id',
     *     label: 'Štítky produktu'
     * )]
     * private array $tags = [];
     * ```
     */
    public function __construct(
        public string $entityClass,
        public ?string $pivotTable = null,
        public ?string $foreignPivotKey = null,
        public ?string $relatedPivotKey = null,
        public string $localKey = 'id',
        public string $relatedKey = 'id',
        public ?string $label = null,
        public ?string $description = null,
    ) {
    }
    
    /**
     * Načte všechny související entity z databáze přes pivotní tabulku
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
     * Vrátí název klíče v pivotní tabulce odkazující na související entitu
     * 
     * Pokud není explicitně nastaven, odvozuje se z názvu související třídy:
     * - Role → role_id
     * - Tag → tag_id
     * - Permission → permission_id
     * 
     * @return string Název klíče v pivotní tabulce (např. "role_id")
     * 
     * @example
     * ```php
     * $relation = new BelongsToMany(entityClass: Role::class);
     * echo $relation->getForeignKey(); // "role_id"
     * ```
     */
    public function getForeignKey(): string
    {
        return $this->foreignPivotKey ?? $this->guessedForeignKey();
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
     * Automaticky odvozuje název klíče v pivotní tabulce ze související třídy
     * 
     * Převádí název třídy na snake_case a přidává "_id":
     * - Role → role_id
     * - ProductTag → product_tag_id
     * - UserPermission → user_permission_id
     * 
     * @return string Odvozený název klíče v pivotní tabulce
     */
    private function guessedForeignKey(): string
    {
        $reflection = new \ReflectionClass($this->entityClass);
        $shortName = $reflection->getShortName();
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName)) . '_id';
    }
}