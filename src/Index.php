<?php

declare(strict_types=1);

namespace prochst\bsOrm;

/**
 * Definice databázového indexu
 * 
 * Index zrychluje vyhledávání v databázi, ale zpomaluje INSERT/UPDATE operace.
 * Používejte indexy pro:
 * - Sloupce ve WHERE klauzulích
 * - Sloupce v JOIN podmínkách
 * - Sloupce v ORDER BY
 * - Cizí klíče
 * 
 * Typy indexů:
 * - **Normální index** - zrychluje dotazy, může obsahovat duplicity
 * - **Unique index** - zajišťuje unikátnost hodnot
 * - **BTREE** (výchozí) - vhodný pro většinu případů, podporuje <, >, =, BETWEEN
 * - **HASH** - velmi rychlý pro = operace, ale nepodporuje rozsahy
 * - **FULLTEXT** - pro fulltextové vyhledávání (MySQL/MariaDB)
 * 
 * @package prochst\bsOrm
 * @author  Your Name
 * @version 1.0.0
 * 
 * @example
 * ```php
 * #[Table(
 *     name: 'users',
 *     indexes: [
 *         // Jednoduchý index na jednom sloupci
 *         new Index('idx_email', ['email']),
 *         
 *         // Unikátní index (alternativa k Column::$unique)
 *         new Index('idx_username', ['username'], unique: true),
 *         
 *         // Složený index (multi-column)
 *         new Index('idx_status_created', ['status', 'created_at']),
 *         
 *         // Index s explicitním typem
 *         new Index('idx_hash_email', ['email'], type: 'HASH'),
 *     ]
 * )]
 * class User extends Entity { ... }
 * ```
 */
class Index
{
    /**
     * Vytvoří definici indexu
     * 
     * @param string $name Název indexu (musí být unikátní v rámci tabulky)
     * @param string[] $columns Pole názvů sloupců (pro složený index více sloupců)
     * @param bool $unique Je index unikátní? (zajišťuje unikátnost hodnot)
     * @param string|null $type Typ indexu (BTREE, HASH, FULLTEXT, ...)
     *                          null = použije se výchozí typ databáze (obvykle BTREE)
     * 
     * @example
     * ```php
     * // Jednoduchý index
     * new Index('idx_email', ['email'])
     * 
     * // Unikátní index
     * new Index('idx_username', ['username'], unique: true)
     * 
     * // Složený index (pořadí sloupců záleží!)
     * // Vhodné pro WHERE status = ? AND created_at > ?
     * new Index('idx_status_created', ['status', 'created_at'])
     * 
     * // HASH index pro rychlé = operace
     * // Výhodné pro: WHERE token = ?
     * // Nevhodné pro: WHERE token LIKE '%abc%' nebo WHERE id > 100
     * new Index('idx_token', ['token'], type: 'HASH')
     * ```
     */
    public function __construct(
        public string $name,
        public array $columns,
        public bool $unique = false,
        public ?string $type = null,
    ) {
    }
    
    /**
     * Vygeneruje SQL příkaz pro vytvoření indexu
     * 
     * Generuje SQL specifické pro daný typ databáze
     * 
     * @param string $tableName Název tabulky
     * @param string $driver Typ databáze ('mysql', 'pgsql', 'sqlite')
     * 
     * @return string SQL příkaz CREATE INDEX
     * 
     * @example
     * ```php
     * $index = new Index('idx_email', ['email'], unique: true);
     * 
     * // MySQL
     * echo $index->toSql('users', 'mysql');
     * // CREATE UNIQUE INDEX idx_email ON `users` (`email`)
     * 
     * // PostgreSQL
     * echo $index->toSql('users', 'pgsql');
     * // CREATE UNIQUE INDEX idx_email ON users (email)
     * 
     * // S typem
     * $index = new Index('idx_email', ['email'], type: 'HASH');
     * echo $index->toSql('users', 'mysql');
     * // CREATE INDEX idx_email ON `users` (`email`) USING HASH
     * ```
     */
    public function toSql(string $tableName, string $driver): string
    {
        $unique = $this->unique ? 'UNIQUE ' : '';
        
        if ($driver === 'mysql') {
            $columns = implode(', ', array_map(fn($col) => "`$col`", $this->columns));
        } else {
            $columns = implode(', ', $this->columns);
        }
        
        $type = '';
        if ($this->type && $driver !== 'pgsql') {
            $type = " USING {$this->type}";
        }
        
        if ($driver === 'pgsql') {
            return "CREATE {$unique}INDEX {$this->name} ON {$tableName} ({$columns}){$type}";
        }
        
        return "CREATE {$unique}INDEX {$this->name} ON `{$tableName}` ({$columns}){$type}";
    }
}