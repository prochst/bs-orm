<?php

declare(strict_types=1);

namespace prochst\bsOrm;

/**
 * Definice cizího klíče (Foreign Key Constraint)
 * 
 * Cizí klíč zajišťuje referenční integritu na úrovni databáze:
 * - Nelze vložit hodnotu, která neexistuje v odkazované tabulce
 * - Lze definovat chování při UPDATE/DELETE rodičovského záznamu
 * - Databáze automaticky vytváří indexy na cizích klíčích
 * 
 * Akce ON DELETE a ON UPDATE:
 * - **RESTRICT** (výchozí) - zabrání smazání/změně pokud existují závislé záznamy
 * - **CASCADE** - automaticky smaže/změní závislé záznamy
 * - **SET NULL** - nastaví cizí klíč na NULL (sloupec musí být nullable)
 * - **SET DEFAULT** - nastaví výchozí hodnotu
 * - **NO ACTION** - podobné RESTRICT, ale kontrola až na konci transakce
 * 
 * @package prochst\bsOrm
 * @author  Your Name
 * @version 1.0.0
 * 
 * @example
 * ```php
 * #[Table(
 *     name: 'posts',
 *     foreignKeys: [
 *         // Základní cizí klíč
 *         new ForeignKey(
 *             name: 'fk_post_user',
 *             columns: ['user_id'],
 *             referencedTable: 'users',
 *             referencedColumns: ['id']
 *         ),
 *         
 *         // S CASCADE - smazání uživatele smaže jeho posty
 *         new ForeignKey(
 *             name: 'fk_post_user',
 *             columns: ['user_id'],
 *             referencedTable: 'users',
 *             referencedColumns: ['id'],
 *             onDelete: 'CASCADE'
 *         ),
 *         
 *         // S SET NULL - smazání kategorie nastaví NULL
 *         new ForeignKey(
 *             name: 'fk_post_category',
 *             columns: ['category_id'],
 *             referencedTable: 'categories',
 *             referencedColumns: ['id'],
 *             onDelete: 'SET NULL'
 *         ),
 *         
 *         // Složený cizí klíč (více sloupců)
 *         new ForeignKey(
 *             name: 'fk_order_item',
 *             columns: ['order_id', 'product_id'],
 *             referencedTable: 'order_items',
 *             referencedColumns: ['order_id', 'product_id'],
 *             onDelete: 'CASCADE'
 *         ),
 *     ]
 * )]
 * class Post extends Entity { ... }
 * ```
 */
class ForeignKey
{
    /**
     * Vytvoří definici cizího klíče
     * 
     * @param string $name Název constraintu (musí být unikátní v databázi)
     * @param string[] $columns Lokální sloupce (v této tabulce)
     * @param string $referencedTable Odkazovaná tabulka
     * @param string[] $referencedColumns Odkazované sloupce (obvykle primární klíč)
     * @param string $onDelete Akce při DELETE (CASCADE, SET NULL, RESTRICT, ...)
     * @param string $onUpdate Akce při UPDATE (CASCADE, SET NULL, RESTRICT, ...)
     * 
     * @example
     * ```php
     * // Příklad 1: Post patří uživateli
     * // Smazání uživatele smaže jeho posty
     * new ForeignKey(
     *     name: 'fk_post_user',
     *     columns: ['user_id'],
     *     referencedTable: 'users',
     *     referencedColumns: ['id'],
     *     onDelete: 'CASCADE',
     *     onUpdate: 'CASCADE'
     * )
     * 
     * // Příklad 2: Post má volitelnou kategorii
     * // Smazání kategorie nastaví category_id na NULL
     * new ForeignKey(
     *     name: 'fk_post_category',
     *     columns: ['category_id'],
     *     referencedTable: 'categories',
     *     referencedColumns: ['id'],
     *     onDelete: 'SET NULL',
     *     onUpdate: 'RESTRICT'
     * )
     * 
     * // Příklad 3: Ochrana dat
     * // Nelze smazat uživatele, pokud má objednávky
     * new ForeignKey(
     *     name: 'fk_order_user',
     *     columns: ['user_id'],
     *     referencedTable: 'users',
     *     referencedColumns: ['id'],
     *     onDelete: 'RESTRICT',
     *     onUpdate: 'RESTRICT'
     * )
     * ```
     */
    public function __construct(
        public string $name,
        public array $columns,
        public string $referencedTable,
        public array $referencedColumns,
        public string $onDelete = 'RESTRICT',
        public string $onUpdate = 'RESTRICT',
    ) {
    }
    
    /**
     * Vygeneruje SQL pro vytvoření cizího klíče
     * 
     * Používá se při generování CREATE TABLE nebo ALTER TABLE příkazů
     * 
     * @param string $driver Typ databáze (zatím nepoužito, pro budoucí rozšíření)
     * 
     * @return string SQL constraint definice
     * 
     * @example
     * ```php
     * $fk = new ForeignKey(
     *     name: 'fk_post_user',
     *     columns: ['user_id'],
     *     referencedTable: 'users',
     *     referencedColumns: ['id'],
     *     onDelete: 'CASCADE'
     * );
     * 
     * echo $fk->toSql('mysql');
     * // CONSTRAINT fk_post_user FOREIGN KEY (user_id) 
     * // REFERENCES users (id) ON DELETE CASCADE ON UPDATE RESTRICT
     * 
     * // V CREATE TABLE:
     * CREATE TABLE posts (
     *     id INT PRIMARY KEY,
     *     user_id INT NOT NULL,
     *     title VARCHAR(255),
     *     CONSTRAINT fk_post_user FOREIGN KEY (user_id) 
     *         REFERENCES users (id) ON DELETE CASCADE
     * );
     * 
     * // Nebo ALTER TABLE:
     * ALTER TABLE posts ADD CONSTRAINT fk_post_user 
     *     FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE;
     * ```
     */
    public function toSql(string $driver): string
    {
        $columns = implode(', ', $this->columns);
        $refColumns = implode(', ', $this->referencedColumns);
        
        return "CONSTRAINT {$this->name} FOREIGN KEY ({$columns}) " .
               "REFERENCES {$this->referencedTable} ({$refColumns}) " .
               "ON DELETE {$this->onDelete} ON UPDATE {$this->onUpdate}";
    }
}