<?php

declare(strict_types=1);

namespace prochst\bsOrm;

use prochst\bsOrm\Relations;

/**
 * Repository Pattern pro práci s kolekcemi entit
 * 
 * Poskytuje abstrakci nad CRUD operacemi a dotazy do databáze.
 * Odděluje datovou vrstvu od business logiky.
 * 
 * Implementuje RepositoryInterface pro snadné testování a výměnu implementace.
 * 
 * Výhody Repository Pattern:
 * - Centralizace datových operací
 * - Snadné testování (mock repository)
 * - Možnost vlastních metod (findByEmail, findActiveUsers, ...)
 * - Cachování na úrovni repository
 * - Konzistentní API napříč entitami
 * 
 * @package prochst\bsOrm
 * @author  Your Name
 * @version 1.0.0
 * 
 * @example
 * ```php
 * // Základní použití
 * $userRepo = new Repository($dbal, User::class);
 * $user = $userRepo->find(1);
 * 
 * // Vlastní repository s custom metodami
 * class UserRepository extends Repository
 * {
 *     public function __construct(Dbal $dbal)
 *     {
 *         parent::__construct($dbal, User::class);
 *     }
 *     
 *     public function findByEmail(string $email): ?User
 *     {
 *         return $this->findBy(['email' => $email], limit: 1)[0] ?? null;
 *     }
 * }
 * ```
 */
class Repository implements RepositoryInterface
{
    /**
     * Plně kvalifikovaný název třídy entity
     * 
     * @var class-string<Entity>
     */
    protected string $entityClass;
    
    /**
     * Název databázové tabulky
     * 
     * @var string
     */
    protected string $tableName;
    
    /**
     * Název primárního klíče
     * 
     * @var string
     */
    protected ?string $primaryKey = 'id';
    
    /**
     * Vytvoří novou instanci repository
     * 
     * @param Dbal                  $dbal        DBAL instance pro práci s DB
     * @param class-string<Entity>  $entityClass Název třídy entity
     * 
     * @example
     * ```php
     * $dbal = Dbal::fromConnection($connection);
     * $userRepo = new Repository($dbal, User::class);
     * ```
     */
    /**
     * Povolené směry řazení
     */
    private const ALLOWED_ORDER_DIRECTIONS = ['ASC', 'DESC'];
    
    /**
     * Cache platných názvů sloupců entity
     * 
     * @var array<string, true>
     */
    private array $validColumns = [];
    
    public function __construct(
        protected Dbal $dbal,
        string $entityClass,
    ) {
        $this->entityClass = $entityClass;
        
        /** @var class-string<Entity> $entityClass */
        $tableAttr = $entityClass::getTableAttribute();
        $this->tableName = $tableAttr?->getTableName($entityClass) ?? 'unknown';
        
        // Sestavíme mapu platných názvů sloupců (jak DB názvy, tak property názvy)
        foreach ($entityClass::getColumns() as $propertyName => $column) {
            $dbName = $column->getColumnName($propertyName);
            $this->validColumns[$dbName] = true;
            $this->validColumns[$propertyName] = true;
        }
    }
    
    /**
     * Ověří, že název sloupce existuje v entitě
     * 
     * Chrání proti SQL injection v dynamicky sestavovaných dotazech
     * 
     * @param string $column Název sloupce k ověření
     * 
     * @throws \InvalidArgumentException Pokud sloupec neexistuje
     */
    private function validateColumnName(string $column): void
    {
        if (!isset($this->validColumns[$column])) {
            throw new \InvalidArgumentException(
                "Neznámý sloupec '$column' v entitě {$this->entityClass}. "
                . "Povolené sloupce: " . implode(', ', array_keys($this->validColumns))
            );
        }
    }
    
    /**
     * Ověří a normalizuje směr řazení
     * 
     * @param string $direction Směr řazení
     * 
     * @return string Validovaný směr ('ASC' nebo 'DESC')
     * 
     * @throws \InvalidArgumentException Pokud směr není platný
     */
    private function validateOrderDirection(string $direction): string
    {
        $normalized = strtoupper(trim($direction));
        if (!in_array($normalized, self::ALLOWED_ORDER_DIRECTIONS, true)) {
            throw new \InvalidArgumentException(
                "Neplatný směr řazení '$direction'. Povolené: " . implode(', ', self::ALLOWED_ORDER_DIRECTIONS)
            );
        }
        return $normalized;
    }
    
    /**
     * Escapuje název tabulky
     */
    private function escTable(): string
    {
        return $this->dbal->escapeIdentifier($this->tableName);
    }
    
    /**
     * Escapuje název primárního klíče
     */
    private function escPk(): string
    {
        return $this->dbal->escapeIdentifier($this->primaryKey);
    }
    
    /**
     * Najde entitu podle primárního klíče (ID)
     * 
     * Nejčastěji používaná metoda pro načtení jednoho záznamu
     * 
     * @param mixed $id Hodnota primárního klíče
     * 
     * @return Entity|null Entity nebo null pokud neexistuje
     * 
     * @example
     * ```php
     * // Najít uživatele s ID 1
     * $user = $userRepo->find(1);
     * 
     * if ($user) {
     *     echo $user->getEmail();
     * } else {
     *     echo "Uživatel nenalezen";
     * }
     * 
     * // Najít produkt s UUID
     * $product = $productRepo->find('550e8400-e29b-41d4-a716-446655440000');
     * ```
     */
    public function find(mixed $id): ?Entity
    {
        $sql = "SELECT * FROM {$this->escTable()} WHERE {$this->escPk()} = ?";
        $row = $this->dbal->fetchOne($sql, [$id]);
        
        if (!$row) {
            return null;
        }
        
        return new $this->entityClass($row);
    }
    
    /**
     * Najde všechny entity v tabulce
     * 
     * ⚠️ POZOR: Může vrátit velké množství dat!
     * Pro velké tabulky raději použijte findBy() s limitem
     * 
     * @return Entity[] Pole entit
     * 
     * @example
     * ```php
     * // Načíst všechny uživatele
     * $users = $userRepo->findAll();
     * 
     * foreach ($users as $user) {
     *     echo $user->getEmail() . "\n";
     * }
     * 
     * // Lepší pro velké tabulky
     * $users = $userRepo->findBy([], orderBy: ['id' => 'DESC'], limit: 100);
     * ```
     */
    public function findAll(): array
    {
        $sql = "SELECT * FROM {$this->escTable()}";
        $rows = $this->dbal->fetchAll($sql);
        
        return array_map(fn($row) => new $this->entityClass($row), $rows);
    }
    
    /**
     * Najde entity podle kritérií s řazením, limitem a offsetem
     * 
     * Nejflexibilnější metoda pro dotazování. Podporuje:
     * - Filtrování podle více sloupců (AND logika)
     * - Řazení podle více sloupců
     * - Stránkování (limit + offset)
     * - NULL hodnoty
     * 
     * @param array<string, mixed> $criteria Kritéria pro WHERE (sloupec => hodnota)
     * @param array<string, string>|null $orderBy Řazení (sloupec => 'ASC'|'DESC')
     * @param int|null $limit Maximum záznamů
     * @param int|null $offset Offset pro stránkování
     * 
     * @return Entity[] Pole entit
     * 
     * @example
     * ```php
     * // Aktivní uživatelé seřazení podle jména
     * $users = $userRepo->findBy(
     *     ['active' => true],
     *     ['name' => 'ASC']
     * );
     * 
     * // Produkty v kategorii s cenou > 1000, stránka 2
     * $products = $productRepo->findBy(
     *     ['category_id' => 5],
     *     ['price' => 'DESC'],
     *     limit: 20,
     *     offset: 20
     * );
     * 
     * // Objednávky s NULL user_id (hosté)
     * $guestOrders = $orderRepo->findBy(['user_id' => null]);
     * 
     * // Kombinace více kritérií
     * $items = $repo->findBy(
     *     [
     *         'status' => 'active',
     *         'category' => 'electronics',
     *         'price' => 999.99
     *     ],
     *     ['created_at' => 'DESC'],
     *     10
     * );
     * ```
     */
    public function findBy(
        array $criteria, 
        ?array $orderBy = null, 
        ?int $limit = null, 
        ?int $offset = null
    ): array {
        $conditions = [];
        $params = [];
        
        foreach ($criteria as $column => $value) {
            $this->validateColumnName($column);
            $escapedColumn = $this->dbal->escapeIdentifier($column);
            if ($value === null) {
                $conditions[] = "$escapedColumn IS NULL";
            } else {
                $conditions[] = "$escapedColumn = ?";
                $params[] = $value;
            }
        }
        
        $sql = "SELECT * FROM {$this->escTable()}";
        if ($conditions) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        if ($orderBy) {
            $orderClauses = [];
            foreach ($orderBy as $column => $direction) {
                $this->validateColumnName($column);
                $escapedColumn = $this->dbal->escapeIdentifier($column);
                $validDirection = $this->validateOrderDirection($direction);
                $orderClauses[] = "$escapedColumn $validDirection";
            }
            $sql .= " ORDER BY " . implode(', ', $orderClauses);
        }
        
        if ($limit !== null) {
            $sql .= " LIMIT ?";
            $params[] = $limit;
        }
        
        if ($offset !== null) {
            $sql .= " OFFSET ?";
            $params[] = $offset;
        }
        
        $rows = $this->dbal->fetchAll($sql, $params);
        
        return array_map(fn($row) => new $this->entityClass($row), $rows);
    }
    
    /**
     * Uloží entitu do databáze (INSERT nebo UPDATE)
     * 
     * Automaticky detekuje zda se jedná o novou entitu (INSERT)
     * nebo existující (UPDATE) podle přítomnosti primárního klíče.
     * 
     * Pro UPDATE aktualizuje pouze změněná pole (optimalizace)
     * 
     * @param Entity $entity Entita k uložení
     * 
     * @return bool True pokud operace uspěla
     * 
     * @example
     * ```php
     * // INSERT - nová entita bez ID
     * $user = new User();
     * $user->setEmail('new@example.com');
     * $userRepo->save($user); // Provede INSERT
     * echo $user->getId(); // ID je nyní nastaveno
     * 
     * // UPDATE - existující entita s ID
     * $user = $userRepo->find(1);
     * $user->setEmail('updated@example.com');
     * $userRepo->save($user); // Provede UPDATE pouze pro email
     * 
     * // UPDATE s více změnami
     * $user->setName('New Name');
     * $user->setActive(false);
     * $userRepo->save($user); // UPDATE email, name, active
     * ```
     */
    public function save(Entity $entity): bool
    {
        $data = $entity->toArray(forDatabase: true);
        
        // Pokud má entita primární klíč, updatujeme
        if (isset($data[$this->primaryKey]) && $data[$this->primaryKey] !== null) {
            return $this->update($entity);
        }
        
        return $this->insert($entity);
    }
    
    /**
     * Vloží novou entitu do databáze
     * 
     * @param Entity $entity Entita k vložení
     * 
     * @return bool True pokud INSERT uspěl
     * 
     * @internal Většinou není potřeba volat přímo, použijte save()
     * 
     * @example
     * ```php
     * // Raději použijte save() které automaticky detekuje INSERT vs UPDATE
     * $userRepo->save($user);
     * ```
     */
    protected function insert(Entity $entity): bool
    {
        $data = $entity->toArray(forDatabase: true);
        
        // Odebereme auto-increment sloupce
        $entityColumns = $entity::getColumns();
        foreach ($entityColumns as $propertyName => $column) {
            if ($column->autoIncrement) {
                $columnName = $column->name ?? $propertyName;
                unset($data[$columnName]);
            }
        }
        
        $escapedColumns = array_map(
            fn(string $col) => $this->dbal->escapeIdentifier($col),
            array_keys($data)
        );
        $placeholders = array_fill(0, count($data), '?');
        
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->escTable(),
            implode(', ', $escapedColumns),
            implode(', ', $placeholders)
        );
        
        return $this->dbal->execute($sql, array_values($data));
    }
    
    /**
     * Aktualizuje existující entitu
     * 
     * Generuje optimalizovaný UPDATE dotaz který mění pouze
     * sloupce které byly skutečně změněny
     * 
     * @param Entity $entity Entita k aktualizaci
     * 
     * @return bool True pokud UPDATE uspěl
     * 
     * @internal Většinou není potřeba volat přímo, použijte save()
     * 
     * @example
     * ```php
     * // save() automaticky volá insert() nebo update()
     * $userRepo->save($user);
     * ```
     */
    protected function update(Entity $entity): bool
    {
        $allData = $entity->toArray(forDatabase: true);
        $id = $allData[$this->primaryKey] ?? null;
        
        if ($id === null) {
            return false;
        }
        
        // Pokud entita sleduje změny, aktualizujeme pouze změněná pole
        $modifiedData = $entity->getModifiedData();
        
        if ($modifiedData !== null) {
            // Partial update — pouze změněné sloupce
            if (empty($modifiedData)) {
                return true; // Žádné změny, nic k aktualizaci
            }
            $data = $modifiedData;
        } else {
            // Fallback — aktualizace všech sloupců (entita nepoužívá tracking)
            $data = $allData;
            unset($data[$this->primaryKey]);
        }
        
        $setClauses = [];
        foreach (array_keys($data) as $column) {
            $escapedColumn = $this->dbal->escapeIdentifier($column);
            $setClauses[] = "$escapedColumn = ?";
        }
        
        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s = ?",
            $this->escTable(),
            implode(', ', $setClauses),
            $this->escPk()
        );
        
        $params = array_values($data);
        $params[] = $id;
        
        return $this->dbal->execute($sql, $params);
    }
    
    /**
     * Smaže entitu z databáze
     * 
     * Provede DELETE podle primárního klíče entity
     * 
     * ⚠️ POZOR: Neřeší kaskádové mazání! Použijte cizí klíče nebo vlastní logiku
     * 
     * @param Entity $entity Entita ke smazání
     * 
     * @return bool True pokud DELETE uspěl (nebo pokud entita nemá ID)
     * 
     * @example
     * ```php
     * $user = $userRepo->find(1);
     * $userRepo->delete($user);
     * 
     * // Entita bez ID (nebyla uložena)
     * $newUser = new User();
     * $userRepo->delete($newUser); // Vrátí false
     * 
     * // S transakcí pro atomicitu
     * $dbal->transaction(function() use ($userRepo, $postRepo, $user) {
     *     // Smažeme posty uživatele
     *     $posts = $postRepo->findBy(['user_id' => $user->getId()]);
     *     foreach ($posts as $post) {
     *         $postRepo->delete($post);
     *     }
     *     // Smažeme uživatele
     *     $userRepo->delete($user);
     * });
     * ```
     */
    public function delete(Entity $entity): bool
    {
        $data = $entity->toArray();
        $id = $data[$this->primaryKey] ?? null;
        
        if ($id === null) {
            return false;
        }
        
        $sql = "DELETE FROM {$this->escTable()} WHERE {$this->escPk()} = ?";
        return $this->dbal->execute($sql, [$id]);
    }
    
    /**
     * Spočítá počet záznamů podle kritérií
     * 
     * Efektivnější než count(findBy()) protože neprovádí SELECT *
     * 
     * @param array<string, mixed> $criteria Kritéria pro WHERE
     * 
     * @return int Počet záznamů
     * 
     * @example
     * ```php
     * // Celkový počet uživatelů
     * $total = $userRepo->count();
     * 
     * // Počet aktivních uživatelů
     * $activeCount = $userRepo->count(['active' => true]);
     * 
     * // Počet produktů v kategorii
     * $productCount = $productRepo->count(['category_id' => 5]);
     * 
     * // Stránkování
     * $page = 1;
     * $perPage = 20;
     * $total = $userRepo->count();
     * $totalPages = ceil($total / $perPage);
     * $users = $userRepo->findBy(
     *     [],
     *     ['id' => 'DESC'],
     *     $perPage,
     *     ($page - 1) * $perPage
     * );
     * ```
     */
    public function count(array $criteria = []): int
    {
        $conditions = [];
        $params = [];
        
        foreach ($criteria as $column => $value) {
            $this->validateColumnName($column);
            $escapedColumn = $this->dbal->escapeIdentifier($column);
            if ($value === null) {
                $conditions[] = "$escapedColumn IS NULL";
            } else {
                $conditions[] = "$escapedColumn = ?";
                $params[] = $value;
            }
        }
        
        $sql = "SELECT COUNT(*) as cnt FROM {$this->escTable()}";
        if ($conditions) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $row = $this->dbal->fetchOne($sql, $params);
        return (int) ($row['cnt'] ?? 0);
    }
    
    /**
     * Načte entitu s načtenými relacemi (eager loading)
     * 
     * @param mixed    $id        Hodnota primárního klíče
     * @param string[] $relations Názvy relací k načtení
     * 
     * @return Entity|null Entity s načtenými relacemi nebo null
     * 
     * @example
     * ```php
     * // Načíst uživatele s posty
     * $user = $userRepo->findWithRelations(1, ['posts']);
     * 
     * // Načíst uživatele s posty a rolemi
     * $user = $userRepo->findWithRelations(1, ['posts', 'roles']);
     * ```
     */
    public function findWithRelations(mixed $id, array $relations = []): ?Entity
    {
        $entity = $this->find($id);
        
        if ($entity) {
            foreach ($relations as $relation) {
                $entity->loadRelation($relation, $this);
            }
        }
        
        return $entity;
    }
    
    /**
     * Načte všechny entity s relacemi (batch eager loading)
     * 
     * Místo N+1 dotazů provede 1 dotaz na entity + 1 dotaz per relace.
     * Pro 100 uživatelů se 2 relacemi = 3 dotazy místo 201.
     * 
     * @param string[] $relations Názvy relací k načtení
     * 
     * @return Entity[] Pole entit s načtenými relacemi
     * 
     * @example
     * ```php
     * // 3 SQL dotazy místo 201:
     * // 1. SELECT * FROM users
     * // 2. SELECT * FROM posts WHERE user_id IN (1,2,3,...)
     * // 3. SELECT r.* FROM roles r JOIN user_roles p ON ... WHERE p.user_id IN (1,2,3,...)
     * $users = $userRepo->findAllWithRelations(['posts', 'roles']);
     * ```
     */
    public function findAllWithRelations(array $relations = []): array
    {
        $entities = $this->findAll();
        
        if (!empty($relations) && !empty($entities)) {
            $this->batchLoadRelations($entities, $relations);
        }
        
        return $entities;
    }
    
    /**
     * Načte entity podle kritérií s relacemi (batch eager loading)
     * 
     * @param array<string, mixed>       $criteria  Kritéria pro WHERE
     * @param string[]                   $relations Názvy relací k načtení
     * @param array<string, string>|null $orderBy   Řazení
     * @param int|null                   $limit     Maximum záznamů
     * @param int|null                   $offset    Offset
     * 
     * @return Entity[] Pole entit s načtenými relacemi
     * 
     * @example
     * ```php
     * // Aktivní uživatelé s posty — 3 dotazy místo N+1
     * $users = $userRepo->findByWithRelations(
     *     ['active' => true],
     *     ['posts'],
     *     ['name' => 'ASC']
     * );
     * ```
     */
    public function findByWithRelations(
        array $criteria, 
        array $relations = [], 
        ?array $orderBy = null, 
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $entities = $this->findBy($criteria, $orderBy, $limit, $offset);
        
        if (!empty($relations) && !empty($entities)) {
            $this->batchLoadRelations($entities, $relations);
        }
        
        return $entities;
    }
    
    /**
     * Batch eager loading — načte relace pro pole entit najednou
     * 
     * Pro každou relaci provede jeden hromadný dotaz s WHERE ... IN (...)
     * místo jednoho dotazu per entita.
     * 
     * @param Entity[] $entities  Pole entit
     * @param string[] $relations Názvy relací k načtení
     * 
     * @return void
     */
    private function batchLoadRelations(array $entities, array $relations): void
    {
        $entityRelations = $this->entityClass::getRelations();
        
        foreach ($relations as $relationName) {
            if (!isset($entityRelations[$relationName])) {
                throw new \RuntimeException("Relace '$relationName' neexistuje v {$this->entityClass}");
            }
            
            $relationData = $entityRelations[$relationName];
            $relation = $relationData['relation'];
            $type = $relationData['type'];
            
            match($type) {
                Relations\HasOne::class   => $this->batchLoadHasOne($entities, $relationName, $relation),
                Relations\HasMany::class  => $this->batchLoadHasMany($entities, $relationName, $relation),
                Relations\BelongsTo::class => $this->batchLoadBelongsTo($entities, $relationName, $relation),
                Relations\BelongsToMany::class => $this->batchLoadBelongsToMany($entities, $relationName, $relation),
                default => null,
            };
        }
    }
    
    /**
     * Batch loading pro HasOne relaci
     * 1 dotaz: SELECT * FROM related WHERE foreign_key IN (...)
     * 
     * @param Entity[] $entities
     */
    private function batchLoadHasOne(array $entities, string $relationName, Relations\HasOne $relation): void
    {
        $localKeys = $this->collectKeys($entities, $relation->localKey);
        if (empty($localKeys)) return;
        
        $relatedRepo = new Repository($this->dbal, $relation->entityClass);
        $foreignKey = $relation->getForeignKey();
        $relatedEntities = $this->fetchWhereIn($relatedRepo, $foreignKey, $localKeys);
        
        // Indexujeme podle cizího klíče
        $indexed = [];
        foreach ($relatedEntities as $relatedEntity) {
            $fkValue = $this->getEntityPropertyValue($relatedEntity, $foreignKey);
            $indexed[$fkValue] = $relatedEntity; // HasOne → jen jedna
        }
        
        // Přiřadíme ke každé entitě
        $reflection = new \ReflectionClass($this->entityClass);
        $property = $reflection->getProperty($relationName);
        foreach ($entities as $entity) {
            $localValue = $this->getEntityPropertyValue($entity, $relation->localKey);
            $property->setValue($entity, $indexed[$localValue] ?? null);
        }
    }
    
    /**
     * Batch loading pro HasMany relaci
     * 1 dotaz: SELECT * FROM related WHERE foreign_key IN (...)
     * 
     * @param Entity[] $entities
     */
    private function batchLoadHasMany(array $entities, string $relationName, Relations\HasMany $relation): void
    {
        $localKeys = $this->collectKeys($entities, $relation->localKey);
        if (empty($localKeys)) return;
        
        $relatedRepo = new Repository($this->dbal, $relation->entityClass);
        $foreignKey = $relation->getForeignKey();
        $relatedEntities = $this->fetchWhereIn($relatedRepo, $foreignKey, $localKeys);
        
        // Seskupíme podle cizího klíče
        $grouped = [];
        foreach ($relatedEntities as $relatedEntity) {
            $fkValue = $this->getEntityPropertyValue($relatedEntity, $foreignKey);
            $grouped[$fkValue][] = $relatedEntity;
        }
        
        // Přiřadíme ke každé entitě
        $reflection = new \ReflectionClass($this->entityClass);
        $property = $reflection->getProperty($relationName);
        foreach ($entities as $entity) {
            $localValue = $this->getEntityPropertyValue($entity, $relation->localKey);
            $property->setValue($entity, $grouped[$localValue] ?? []);
        }
    }
    
    /**
     * Batch loading pro BelongsTo relaci
     * 1 dotaz: SELECT * FROM parent WHERE id IN (...)
     * 
     * @param Entity[] $entities
     */
    private function batchLoadBelongsTo(array $entities, string $relationName, Relations\BelongsTo $relation): void
    {
        $foreignKey = $relation->getForeignKey();
        $foreignKeys = $this->collectKeys($entities, $foreignKey);
        if (empty($foreignKeys)) return;
        
        $relatedRepo = new Repository($this->dbal, $relation->entityClass);
        $ownerKey = $relation->ownerKey;
        $relatedEntities = $this->fetchWhereIn($relatedRepo, $ownerKey, $foreignKeys);
        
        // Indexujeme podle owner key
        $indexed = [];
        foreach ($relatedEntities as $relatedEntity) {
            $keyValue = $this->getEntityPropertyValue($relatedEntity, $ownerKey);
            $indexed[$keyValue] = $relatedEntity;
        }
        
        // Přiřadíme ke každé entitě
        $reflection = new \ReflectionClass($this->entityClass);
        $property = $reflection->getProperty($relationName);
        foreach ($entities as $entity) {
            $fkValue = $this->getEntityPropertyValue($entity, $foreignKey);
            $property->setValue($entity, $fkValue !== null ? ($indexed[$fkValue] ?? null) : null);
        }
    }
    
    /**
     * Batch loading pro BelongsToMany relaci (M:N přes pivotní tabulku)
     * 1 dotaz: SELECT t.*, p.foreignPivotKey FROM related t JOIN pivot p ON ... WHERE p.foreignPivotKey IN (...)
     * 
     * @param Entity[] $entities
     */
    private function batchLoadBelongsToMany(array $entities, string $relationName, Relations\BelongsToMany $relation): void
    {
        $localKeys = $this->collectKeys($entities, $relation->localKey);
        if (empty($localKeys)) return;
        
        $pivotTable = $relation->pivotTable;
        $foreignPivotKey = $relation->foreignPivotKey ?? $relation->getForeignKey();
        $relatedPivotKey = $relation->relatedPivotKey;
        
        /** @var class-string<Entity> $entityClass */
        $entityClass = $relation->entityClass;
        $tableAttr = $entityClass::getTableAttribute();
        $relatedTable = $tableAttr?->getTableName($entityClass);
        
        // Escapujeme identifikátory
        $escRelatedTable = $this->dbal->escapeIdentifier($relatedTable);
        $escPivotTable = $this->dbal->escapeIdentifier($pivotTable);
        $escRelatedKey = $this->dbal->escapeIdentifier($relation->relatedKey);
        $escRelatedPivotKey = $this->dbal->escapeIdentifier($relatedPivotKey);
        $escForeignPivotKey = $this->dbal->escapeIdentifier($foreignPivotKey);
        
        $placeholders = implode(', ', array_fill(0, count($localKeys), '?'));
        
        $sql = "SELECT t.*, p.{$escForeignPivotKey} AS __pivot_key
                FROM {$escRelatedTable} t
                INNER JOIN {$escPivotTable} p ON t.{$escRelatedKey} = p.{$escRelatedPivotKey}
                WHERE p.{$escForeignPivotKey} IN ({$placeholders})";
        
        $rows = $this->dbal->fetchAll($sql, array_values($localKeys));
        
        // Seskupíme podle pivot key
        $grouped = [];
        foreach ($rows as $row) {
            $pivotKeyValue = $row['__pivot_key'];
            unset($row['__pivot_key']);
            $grouped[$pivotKeyValue][] = new $entityClass($row);
        }
        
        // Přiřadíme ke každé entitě
        $reflection = new \ReflectionClass($this->entityClass);
        $property = $reflection->getProperty($relationName);
        foreach ($entities as $entity) {
            $localValue = $this->getEntityPropertyValue($entity, $relation->localKey);
            $property->setValue($entity, $grouped[$localValue] ?? []);
        }
    }
    
    /**
     * Sbírá unikátní nenullové hodnoty klíče z pole entit
     * 
     * @param Entity[] $entities
     * @param string $keyName Název property s klíčem
     * 
     * @return array Unikátní hodnoty
     */
    private function collectKeys(array $entities, string $keyName): array
    {
        $keys = [];
        foreach ($entities as $entity) {
            $value = $this->getEntityPropertyValue($entity, $keyName);
            if ($value !== null) {
                $keys[$value] = $value;
            }
        }
        return array_values($keys);
    }
    
    /**
     * Načte entity pomocí WHERE column IN (...)
     * 
     * @param Repository $repo
     * @param string $column Název sloupce
     * @param array $values Pole hodnot
     * 
     * @return Entity[]
     */
    private function fetchWhereIn(Repository $repo, string $column, array $values): array
    {
        if (empty($values)) {
            return [];
        }
        
        $escapedColumn = $this->dbal->escapeIdentifier($column);
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $sql = "SELECT * FROM {$repo->escTable()} WHERE {$escapedColumn} IN ({$placeholders})";
        
        $rows = $this->dbal->fetchAll($sql, array_values($values));
        $entityClass = $repo->entityClass;
        
        return array_map(fn($row) => new $entityClass($row), $rows);
    }
    
    /**
     * Získá hodnotu property z entity přes reflexi
     * 
     * Hledá property přímo i jako camelCase variantu snake_case názvu
     * (např. 'user_id' → hledá 'user_id' i 'userId')
     * 
     * @param Entity $entity
     * @param string $propertyName
     * 
     * @return mixed
     */
    private function getEntityPropertyValue(Entity $entity, string $propertyName): mixed
    {
        $reflection = new \ReflectionClass($entity);
        
        // Přímý přístup
        if ($reflection->hasProperty($propertyName)) {
            $prop = $reflection->getProperty($propertyName);
            return $prop->isInitialized($entity) ? $prop->getValue($entity) : null;
        }
        
        // Zkusíme camelCase variantu (user_id → userId)
        $camelCase = lcfirst(str_replace('_', '', ucwords($propertyName, '_')));
        if ($reflection->hasProperty($camelCase)) {
            $prop = $reflection->getProperty($camelCase);
            return $prop->isInitialized($entity) ? $prop->getValue($entity) : null;
        }
        
        // Zkusíme najít přes Column atributy (DB name → property)
        foreach ($reflection->getProperties() as $prop) {
            $attrs = $prop->getAttributes(Column::class);
            if (!empty($attrs)) {
                $column = $attrs[0]->newInstance();
                if ($column->getColumnName($prop->getName()) === $propertyName) {
                    return $prop->isInitialized($entity) ? $prop->getValue($entity) : null;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Získá DBAL instanci
     * 
     * @return Dbal DBAL instance
     * 
     * @internal Použití hlavně interně pro relace
     */
    public function getDbal(): Dbal
    {
        return $this->dbal;
    }
}
