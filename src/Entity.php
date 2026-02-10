<?php

declare(strict_types=1);

namespace prochst\bsOrm;

use ReflectionClass;
use ReflectionProperty;
use prochst\bsOrm\Types\LocaleManager;
use prochst\bsOrm\Relations\HasOne;
use prochst\bsOrm\Relations\HasMany;
use prochst\bsOrm\Relations\BelongsTo;
use prochst\bsOrm\Relations\BelongsToMany;

/**
 * Abstraktní základní třída pro všechny entity
 * 
 * Představuje řádek v databázové tabulce jako PHP objekt. Poskytuje:
 * - Automatické mapování sloupců na properties
 * - Sledování změn pro optimalizované UPDATE dotazy
 * - Type-safe konverze mezi PHP a SQL typy
 * - Přístup k metadatům (labely, typy, validace)
 * - Vícejazyčná podpora
 * 
 * Každá entita musí:
 * 1. Dědit z této třídy
 * 2. Mít #[Table] atribut s metadaty tabulky
 * 3. Mít #[Column] atributy pro každý mapovaný sloupec
 * 4. Implementovat gettery a settery
 * 
 * @package prochst\bsOrm
 * @author  Your Name
 * @version 1.0.0
 * 
 * @example
 * ```php
 * #[Table(name: 'users', label: 'Uživatelé')]
 * class User extends Entity
 * {
 *     #[Column(type: new IntegerType(), primaryKey: true)]
 *     private ?int $id = null;
 *     
 *     #[Column(type: new StringType(255))]
 *     private string $email;
 *     
 *     public function getId(): ?int { return $this->id; }
 *     public function getEmail(): string { return $this->email; }
 *     public function setEmail(string $email): void {
 *         $this->email = $email;
 *         $this->markFieldAsModified('email');
 *     }
 * }
 * ```
 */
abstract class Entity
{
    /**
     * Originální data načtená z databáze
     * 
     * Používá se pro porovnání a detekci změn
     * 
     * @var array<string, mixed>
     */
    private array $_originalData = [];
    
    /**
     * Seznam názvů polí, která byla změněna
     * 
     * Umožňuje generovat optimalizované UPDATE dotazy
     * které mění pouze změněná pole
     * 
     * @var string[]
     */
    private array $_modifiedFields = [];
    
    /**
     * Cache načtených relací (pro eager loading)
     * 
     * @var array<string, bool>
     */
    private array $_loadedRelations = [];
    
    /**
     * Konstruktor entity
     * 
     * Může být volán s prázdným polem (nová entita) nebo
     * s daty z databáze (existující entita)
     * 
     * @param array<string, mixed> $data Data pro naplnění entity
     * 
     * @example
     * ```php
     * // Nová prázdná entita
     * $user = new User();
     * 
     * // Entita naplněná z databáze
     * $user = new User([
     *     'id' => 1,
     *     'email' => 'john@example.com',
     *     'created_at' => '2025-02-05 10:00:00'
     * ]);
     * ```
     */
    public function __construct(array $data = [])
    {
        $this->hydrate($data);
        $this->_originalData = $this->toArray();
    }
    
    /**
     * Naplní entitu daty z databáze
     * 
     * Proces:
     * 1. Pro každý klíč v datech zkontroluje, zda existuje property
     * 2. Pokud property má Column atribut, převede hodnotu z DB formátu do PHP
     * 3. Nastaví hodnotu do property
     * 
     * Automaticky převádí:
     * - SQL stringy na DateTime objekty
     * - JSON stringy na PHP pole
     * - 0/1 na boolean
     * - atd. podle TypeInterface
     * 
     * @param array<string, mixed> $data Asociativní pole dat z databáze
     * 
     * @return void
     * 
     * @example
     * ```php
     * $user = new User();
     * $user->hydrate([
     *     'id' => 1,
     *     'email' => 'john@example.com',
     *     'created_at' => '2025-02-05 10:00:00' // Převede se na DateTimeImmutable
     * ]);
     * ```
     */
    public function hydrate(array $data): void
    {
        $reflection = new ReflectionClass($this);
        
        foreach ($data as $key => $value) {
          
            if ($reflection->hasProperty($key)) {
                $property = $reflection->getProperty($key);
                $column = $this->getColumnAttribute($property);
                
                // Přeskočíme properties bez #[Column] atributu
                // (relace jako posts, roles nemají být nastavovány z DB dat)
                if (!$column) {
                    continue;
                }
                
                $type = $column->type;
                // Konverze z SQL do PHP typu (např. '2025-02-05' -> DateTimeImmutable)
                $value = $type->fromDatabase($value);
                
                $property->setValue($this, $value);
            }
        }
    }
    
    /**
     * Převede entitu na asociativní pole
     * 
     * @param bool $forDatabase Pokud true, převede hodnoty do SQL formátu
     *                          Pokud false, vrátí PHP hodnoty
     * 
     * @return array<string, mixed> Asociativní pole [název_sloupce => hodnota]
     * 
     * @example
     * ```php
     * $user = new User();
     * $user->setEmail('john@example.com');
     * $user->setCreatedAt(new DateTimeImmutable());
     * 
     * // Pro práci v PHP
     * $data = $user->toArray(); // ['email' => 'john@...', 'created_at' => DateTimeImmutable]
     * 
     * // Pro uložení do DB
     * $data = $user->toArray(forDatabase: true); // ['email' => 'john@...', 'created_at' => '2025-02-05 10:00:00']
     * ```
     */
    public function toArray(bool $forDatabase = false): array
    {
        $data = [];
        $reflection = new ReflectionClass($this);
        
        foreach ($reflection->getProperties() as $property) {
            // Přeskočíme statické properties
            if ($property->isStatic()) {
                continue;
            }
            
            $column = $this->getColumnAttribute($property);
            if (!$column) {
                continue;
            }
            
            // Přeskoč neinicializované typed properties
            if (!$property->isInitialized($this)) {
                continue;
            }
            
            $value = $property->getValue($this);
            
            // Konverze z PHP do SQL formátu pokud je potřeba
            if ($forDatabase && $column->type) {
                $value = $column->type->toDatabase($value);
            }
            
            $columnName = $column->name ?? $property->getName();
            $data[$columnName] = $value;
        }
        
        return $data;
    }
    
    /**
     * Vrátí názvy polí (property names), která byla změněna od načtení entity
     * 
     * @return string[] Pole názvů změněných property
     * 
     * @example
     * ```php
     * $user = $userRepo->find(1);
     * $user->setEmail('new@example.com');
     * $user->setName('New Name');
     * 
     * $modified = $user->getModifiedFields();
     * // ['email', 'name']
     * ```
     */
    public function getModifiedFields(): array
    {
        return $this->_modifiedFields;
    }
    
    /**
     * Vrátí mapu property name → DB column name
     * 
     * @return array<string, string> Pole [propertyName => dbColumnName]
     */
    public static function getPropertyToColumnMap(): array
    {
        $map = [];
        foreach (static::getColumns() as $propertyName => $column) {
            $map[$propertyName] = $column->getColumnName($propertyName);
        }
        return $map;
    }
    
    /**
     * Vrátí pouze změněná data ve formátu pro databázi
     * 
     * Kombinuje getModifiedFields() s toArray(forDatabase: true)
     * pro generování optimalizovaných UPDATE dotazů.
     * 
     * @return array<string, mixed>|null Asociativní pole [db_column => hodnota]
     *                                   nebo null pokud nebyly žádné změny
     * 
     * @example
     * ```php
     * $user = $userRepo->find(1);
     * $user->setEmail('new@example.com');
     * 
     * $modified = $user->getModifiedData();
     * // ['email' => 'new@example.com']
     * 
     * // Repository vygeneruje:
     * // UPDATE users SET email = ? WHERE id = ?
     * ```
     */
    public function getModifiedData(): ?array
    {
        $modifiedFields = $this->getModifiedFields();
        if (empty($modifiedFields)) {
            return null;
        }
        
        $allData = $this->toArray(forDatabase: true);
        $propertyToColumn = static::getPropertyToColumnMap();
        $modifiedData = [];
        
        foreach ($modifiedFields as $propertyName) {
            $columnName = $propertyToColumn[$propertyName] ?? $propertyName;
            if (array_key_exists($columnName, $allData)) {
                $modifiedData[$columnName] = $allData[$columnName];
            }
        }
        
        return $modifiedData;
    }
    
    /**
     * Označí pole jako změněné
     * 
     * Tuto metodu by měly volat všechny settery v entitě
     * 
     * @param string $field Název pole (PHP property name, ne název sloupce!)
     * 
     * @return void
     * 
     * @example
     * ```php
     * public function setEmail(string $email): void 
     * {
     *     $this->email = $email;
     *     $this->markFieldAsModified('email'); // ← důležité!
     * }
     * ```
     */
    protected function markFieldAsModified(string $field): void
    {
        if (!in_array($field, $this->_modifiedFields, true)) {
            $this->_modifiedFields[] = $field;
        }
    }
    
    /**
     * Získá Table atribut pro entitu
     * 
     * Vrací metadata tabulky (název, labely, indexy, cizí klíče)
     * 
     * @return Table|null Table atribut nebo null pokud není definován
     * 
     * @example
     * ```php
     * $table = User::getTableAttribute();
     * echo $table->name;  // "users"
     * echo $table->label; // "Uživatelé"
     * ```
     */
    public static function getTableAttribute(): ?Table
    {
        $reflection = new ReflectionClass(static::class);
        $attributes = $reflection->getAttributes(Table::class);
        
        return !empty($attributes) ? $attributes[0]->newInstance() : null;
    }
    
    /**
     * Vrátí uživatelský název tabulky podle locale
     * 
     * Používá se v UI (gridy, formuláře, nadpisy stránek)
     * 
     * @param string|null $locale Locale (např. 'cs_CZ', 'en_US')
     *                            Pokud null, použije se výchozí
     * 
     * @return string Přeložený název tabulky
     * 
     * @example
     * ```php
     * echo User::getTableLabel('cs_CZ'); // "Uživatelé"
     * echo User::getTableLabel('en_US'); // "Users"
     * echo User::getTableLabel('de_DE'); // "Benutzer"
     * 
     * // V šabloně
     * <h1><?= User::getTableLabel() ?></h1>
     * ```
     */
    public static function getTableLabel(?string $locale = null): string
    {
        $table = static::getTableAttribute();
        return $table?->getLabel(static::class, $locale) ?? static::class;
    }
    
    /**
     * Vrátí popis tabulky podle locale
     * 
     * Používá se v dokumentaci, tooltipy, help textech
     * 
     * @param string|null $locale Locale
     * 
     * @return string|null Přeložený popis nebo null
     * 
     * @example
     * ```php
     * $description = User::getTableDescription('cs_CZ');
     * // "Tabulka uživatelů systému"
     * 
     * // V šabloně
     * <div title="<?= User::getTableDescription() ?>">...</div>
     * ```
     */
    public static function getTableDescription(?string $locale = null): ?string
    {
        $table = static::getTableAttribute();
        return $table?->getDescription($locale);
    }
    
    /**
     * Získá Column atribut pro danou property
     * 
     * @param ReflectionProperty $property Property pro kterou hledáme atribut
     * 
     * @return Column|null Column atribut nebo null
     * 
     * @internal Použití hlavně interně v Entity třídě
     */
    private function getColumnAttribute(ReflectionProperty $property): ?Column
    {
        $attributes = $property->getAttributes(Column::class);
        return !empty($attributes) ? $attributes[0]->newInstance() : null;
    }
    
    /**
     * Získá všechny sloupce entity jako asociativní pole
     * 
     * @return array<string, Column> Pole [property_name => Column]
     * 
     * @example
     * ```php
     * $columns = User::getColumns();
     * foreach ($columns as $propertyName => $column) {
     *     echo "$propertyName: " . $column->getLabel($propertyName) . "\n";
     * }
     * 
     * // id: ID
     * // email: E-mailová adresa
     * // name: Jméno
     * ```
     */
    public static function getColumns(): array
    {
        $reflection = new ReflectionClass(static::class);
        $columns = [];
        
        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }
            
            $attributes = $property->getAttributes(Column::class);
            if (!empty($attributes) && ($columnAttr = $attributes[0]->newInstance())) {
                $columns[$property->getName()] = $columnAttr;
            }
        }
        
        return $columns;
    }
    
    /**
     * Získá přeložené labely všech sloupců
     * 
     * Velmi užitečné pro generování formulářů a gridů
     * 
     * @param string|null $locale Locale
     * 
     * @return array<string, string> Pole [property_name => přeložený_label]
     * 
     * @example
     * ```php
     * $labels = User::getColumnLabels('cs_CZ');
     * // ['id' => 'ID', 'email' => 'E-mailová adresa', 'name' => 'Jméno']
     * 
     * // Generování formuláře
     * foreach ($labels as $field => $label) {
     *     echo "<label>$label</label>";
     *     echo "<input name=\"$field\">";
     * }
     * ```
     */
    public static function getColumnLabels(?string $locale = null): array
    {
        $reflection = new ReflectionClass(static::class);
        $labels = [];
        
        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }
            
            $attributes = $property->getAttributes(Column::class);
            if (!empty($attributes) && ($columnAttr = $attributes[0]->newInstance())) {
                $propertyName = $property->getName();
                $labels[$propertyName] = $columnAttr->getLabel($propertyName, $locale);
            }
        }
        
        return $labels;
    }
    
    /**
     * Získá přeložený label pro konkrétní sloupec
     * 
     * @param string      $propertyName Název PHP property
     * @param string|null $locale       Locale
     * 
     * @return string|null Přeložený label nebo null pokud property neexistuje
     * 
     * @example
     * ```php
     * echo User::getColumnLabel('email', 'cs_CZ'); // "E-mailová adresa"
     * echo User::getColumnLabel('email', 'en_US'); // "Email Address"
     * 
     * // V šabloně
     * <label><?= User::getColumnLabel('email') ?></label>
     * ```
     */
    public static function getColumnLabel(string $propertyName, ?string $locale = null): ?string
    {
        $reflection = new ReflectionClass(static::class);
        
        if (!$reflection->hasProperty($propertyName)) {
            return null;
        }
        
        $property = $reflection->getProperty($propertyName);
        $attributes = $property->getAttributes(Column::class);
        
        if (!empty($attributes) && ($columnAttr = $attributes[0]->newInstance())) {
            return $columnAttr->getLabel($propertyName, $locale);
        }
        
        return null;
    }
    
    /**
     * Získá všechny relace entity
     * 
     * Detekuje properties s atributy HasOne, HasMany, BelongsTo, BelongsToMany
     * 
     * @return array<string, array{type: string, relation: object}> Relace entity
     */
    public static function getRelations(): array
    {
        $reflection = new ReflectionClass(static::class);
        $relations = [];
        
        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }
            
            foreach ([HasOne::class, HasMany::class, BelongsTo::class, BelongsToMany::class] as $relationType) {
                $attributes = $property->getAttributes($relationType);
                if (!empty($attributes) && ($relation = $attributes[0]->newInstance())) {
                    // Nastavíme ownerClass pro HasOne/HasMany,
                    // aby guessedForeignKey() odvodil FK z vlastnické třídy
                    if ($relation instanceof HasOne || $relation instanceof HasMany) {
                        $relation->ownerClass = static::class;
                    }
                    
                    $relations[$property->getName()] = [
                        'type' => $relationType,
                        'relation' => $relation,
                    ];
                }
            }
        }
        
        return $relations;
    }
    
    /**
     * Načte relaci (eager loading)
     * 
     * @param string     $relationName Název property s relací
     * @param Repository $repository   Repository pro načtení souvisejících entit
     * 
     * @return void
     * 
     * @throws \RuntimeException Pokud relace neexistuje
     */
    public function loadRelation(string $relationName, Repository $repository): void
    {
        if (isset($this->_loadedRelations[$relationName])) {
            return; // Již načteno
        }
        
        $relations = static::getRelations();
        if (!isset($relations[$relationName])) {
            throw new \RuntimeException("Relace '$relationName' neexistuje");
        }
        
        $relationData = $relations[$relationName];
        $relation = $relationData['relation'];
        $type = $relationData['type'];
        
        $reflection = new ReflectionClass($this);
        $property = $reflection->getProperty($relationName);
        
        // Načtení podle typu relace
        $value = match($type) {
            HasOne::class => $this->loadHasOne($relation, $repository),
            HasMany::class => $this->loadHasMany($relation, $repository),
            BelongsTo::class => $this->loadBelongsTo($relation, $repository),
            BelongsToMany::class => $this->loadBelongsToMany($relation, $repository),
            default => null,
        };
        
        $property->setValue($this, $value);
        $this->_loadedRelations[$relationName] = true;
    }
    
    /**
     * Načte HasOne relaci
     */
    private function loadHasOne(HasOne $relation, Repository $repository): ?Entity
    {
        $relatedRepo = new Repository($repository->getDbal(), $relation->entityClass);
        $localKeyValue = $this->getPropertyValue($relation->localKey);
        
        $results = $relatedRepo->findBy([
            $relation->getForeignKey() => $localKeyValue
        ], limit: 1);
        
        return $results[0] ?? null;
    }
    
    /**
     * Načte HasMany relaci
     */
    private function loadHasMany(HasMany $relation, Repository $repository): array
    {
        $relatedRepo = new Repository($repository->getDbal(), $relation->entityClass);
        $localKeyValue = $this->getPropertyValue($relation->localKey);
        
        return $relatedRepo->findBy([
            $relation->getForeignKey() => $localKeyValue
        ]);
    }
    
    /**
     * Načte BelongsTo relaci
     */
    private function loadBelongsTo(BelongsTo $relation, Repository $repository): ?Entity
    {
        $relatedRepo = new Repository($repository->getDbal(), $relation->entityClass);
        $foreignKeyValue = $this->getPropertyValue($relation->getForeignKey());
        
        if ($foreignKeyValue === null) {
            return null;
        }
        
        return $relatedRepo->find($foreignKeyValue);
    }
    
    /**
     * Načte BelongsToMany relaci (M:N přes pivotní tabulku)
     */
    private function loadBelongsToMany(BelongsToMany $relation, Repository $repository): array
    {
        $dbal = $repository->getDbal();
        $localKeyValue = $this->getPropertyValue($relation->localKey);
        
        // Sestavení dotazu
        $pivotTable = $relation->pivotTable ?? $this->guessPivotTable($relation->entityClass);
        $foreignPivotKey = $relation->foreignPivotKey ?? $relation->getForeignKey();
        $relatedPivotKey = $relation->relatedPivotKey ?? $this->guessRelatedPivotKey($relation->entityClass);
        
        /** @var class-string<Entity> $entityClass */
        $entityClass = $relation->entityClass;
        $tableAttr = $entityClass::getTableAttribute();
        $relatedTable = $tableAttr?->getTableName($entityClass);
        
        $sql = "
            SELECT t.*
            FROM {$relatedTable} t
            INNER JOIN {$pivotTable} p ON t.{$relation->relatedKey} = p.{$relatedPivotKey}
            WHERE p.{$foreignPivotKey} = ?
        ";
        
        $rows = $dbal->fetchAll($sql, [$localKeyValue]);
        
        return array_map(fn($row) => new $entityClass($row), $rows);
    }
    
    /**
     * Získá hodnotu property
     */
    private function getPropertyValue(string $propertyName): mixed
    {
        $reflection = new ReflectionClass($this);
        $property = $reflection->getProperty($propertyName);
        return $property->getValue($this);
    }
    
    /**
     * Odhadne název pivotní tabulky pro M:N relaci
     */
    private function guessPivotTable(string $relatedClass): string
    {
        $tables = [
            $this->getTableName(),
            (new ReflectionClass($relatedClass))->getShortName()
        ];
        sort($tables);
        return strtolower(implode('_', $tables));
    }
    
    /**
     * Odhadne název klíče v pivotní tabulce
     */
    private function guessRelatedPivotKey(string $relatedClass): string
    {
        $shortName = (new ReflectionClass($relatedClass))->getShortName();
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName)) . '_id';
    }
    
    /**
     * Získá název tabulky entity
     */
    private function getTableName(): string
    {
        $tableAttr = static::getTableAttribute();
        return $tableAttr?->getTableName(static::class) ?? 'unknown';
    }
}
