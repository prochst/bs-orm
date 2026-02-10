<?php

declare(strict_types=1);

/**
 * Generátor SQL migrací z ORM modelů
 * 
 * Tento skript prochází všechny modely v app/Model a pro ty,
 * které obsahují atribut #[Table()], generuje SQL skripty
 * pro vytvoření tabulek.
 * 
 * Použití:
 *   php app/Core/Orm/Migration/generate-migrations.php [driver]
 * 
 * Parametry:
 *   driver - Typ databáze (mysql, pgsql, sqlite). Výchozí: mysql
 * 
 * Příklady:
 *   php app/Core/Orm/Migration/generate-migrations.php
 *   php app/Core/Orm/Migration/generate-migrations.php mysql
 *   php app/Core/Orm/Migration/generate-migrations.php pgsql
 */

// Načtení autoloaderu
require_once __DIR__ . '/../../../../vendor/autoload.php';

use prochst\bsOrm\Table;
use prochst\bsOrm\Column;
use prochst\bsOrm\Index;
use prochst\bsOrm\ForeignKey;

class MigrationGenerator
{
    private string $driver;
    private string $modelsDir;
    private string $migrationsDir;
    
    public function __construct(string $driver = 'mysql')
    {
        $this->driver = $driver;
        $this->modelsDir = __DIR__ . '/../../../Model';
        $this->migrationsDir = __DIR__;
    }
    
    /**
     * Spustí generování migrací
     */
    public function generate(): void
    {
        echo "=== Generátor SQL migrací ===\n";
        echo "Databázový driver: {$this->driver}\n";
        echo "Složka modelů: {$this->modelsDir}\n";
        echo "Složka migrací: {$this->migrationsDir}\n\n";
        
        $modelFiles = $this->findModelFiles();
        
        if (empty($modelFiles)) {
            echo "❌ Nebyly nalezeny žádné PHP soubory v {$this->modelsDir}\n";
            return;
        }
        
        echo "Nalezeno " . count($modelFiles) . " model(ů)\n\n";
        
        $generated = 0;
        foreach ($modelFiles as $file) {
            if ($this->generateMigration($file)) {
                $generated++;
            }
        }
        
        echo "\n=== Dokončeno ===\n";
        echo "Vygenerováno: {$generated} migračních skriptů\n";
    }
    
    /**
     * Najde všechny PHP soubory ve složce modelů
     * 
     * @return string[]
     */
    private function findModelFiles(): array
    {
        $files = glob($this->modelsDir . '/*.php');
        return $files ?: [];
    }
    
    /**
     * Vygeneruje migraci pro jeden model
     * 
     * @param string $filePath Cesta k souboru modelu
     * @return bool True pokud byla migrace vygenerována
     */
    private function generateMigration(string $filePath): bool
    {
        $className = $this->getClassNameFromFile($filePath);
        
        if (!$className) {
            echo "⚠️  {$filePath}: Nelze určit název třídy\n";
            return false;
        }
        
        if (!class_exists($className)) {
            echo "⚠️  {$className}: Třída neexistuje\n";
            return false;
        }
        
        $reflection = new ReflectionClass($className);
        $tableAttributes = $reflection->getAttributes(Table::class);
        
        if (empty($tableAttributes)) {
            echo "⏭️  {$className}: Nemá atribut #[Table()], přeskakuji\n";
            return false;
        }
        
        $tableAttr = $tableAttributes[0]->newInstance();
        $tableName = $tableAttr->getTableName($className);
        
        echo "✅ {$className} → {$tableName}\n";
        
        $sql = $this->generateCreateTableSql($reflection, $tableAttr, $tableName);
        
        $migrationFile = $this->migrationsDir . '/' . $tableName . '.sql';
        file_put_contents($migrationFile, $sql);
        
        echo "   📄 Uloženo: {$migrationFile}\n";
        
        return true;
    }
    
    /**
     * Získá plně kvalifikovaný název třídy ze souboru
     * 
     * @param string $filePath
     * @return string|null
     */
    private function getClassNameFromFile(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        
        // Najdi namespace
        $namespace = null;
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = $matches[1];
        }
        
        // Najdi název třídy
        $className = null;
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            $className = $matches[1];
        }
        
        if (!$className) {
            return null;
        }
        
        return $namespace ? $namespace . '\\' . $className : $className;
    }
    
    /**
     * Vygeneruje SQL CREATE TABLE příkaz
     * 
     * @param ReflectionClass $reflection
     * @param Table $tableAttr
     * @param string $tableName
     * @return string
     */
    private function generateCreateTableSql(
        ReflectionClass $reflection,
        Table $tableAttr,
        string $tableName
    ): string {
        $columns = [];
        $primaryKeys = [];
        
        // Projdi všechny properties s atributem Column
        foreach ($reflection->getProperties() as $property) {
            $columnAttributes = $property->getAttributes(Column::class);
            
            if (empty($columnAttributes)) {
                continue;
            }
            
            $columnAttr = $columnAttributes[0]->newInstance();
            $columnName = $columnAttr->getColumnName($property->getName());
            
            $columnDef = $this->generateColumnDefinition($columnName, $columnAttr);
            $columns[] = $columnDef;
            
            if ($columnAttr->primaryKey) {
                $primaryKeys[] = $columnName;
            }
        }
        
        // Začátek CREATE TABLE
        $sql = "-- Migrace pro tabulku: {$tableName}\n";
        $sql .= "-- Vygenerováno: " . date('Y-m-d H:i:s') . "\n";
        
        if ($tableAttr->description) {
            $sql .= "-- {$tableAttr->description}\n";
        }
        
        $sql .= "\n";
        
        if ($this->driver === 'mysql') {
            $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n\n";
            $sql .= "CREATE TABLE `{$tableName}` (\n";
        } else {
            $sql .= "DROP TABLE IF EXISTS {$tableName};\n\n";
            $sql .= "CREATE TABLE {$tableName} (\n";
        }
        
        // Přidej sloupce
        $sql .= "    " . implode(",\n    ", $columns);
        
        // Přidej primární klíč
        if (!empty($primaryKeys)) {
            $pkColumns = implode(', ', array_map(function($col) {
                return $this->driver === 'mysql' ? "`{$col}`" : $col;
            }, $primaryKeys));
            $sql .= ",\n    PRIMARY KEY ({$pkColumns})";
        }
        
        // Přidej indexy
        if (!empty($tableAttr->indexes)) {
            foreach ($tableAttr->indexes as $index) {
                $sql .= ",\n    " . $this->generateIndexDefinition($index);
            }
        }
        
        // Přidej cizí klíče
        if (!empty($tableAttr->foreignKeys)) {
            foreach ($tableAttr->foreignKeys as $fk) {
                $sql .= ",\n    " . $this->generateForeignKeyDefinition($fk);
            }
        }
        
        $sql .= "\n)";
        
        // Engine a charset pro MySQL
        if ($this->driver === 'mysql') {
            $sql .= " ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        }
        
        $sql .= ";\n";
        
        // Přidej samostatné CREATE INDEX příkazy pro PostgreSQL
        if ($this->driver === 'pgsql' && !empty($tableAttr->indexes)) {
            $sql .= "\n";
            foreach ($tableAttr->indexes as $index) {
                $sql .= $index->toSql($tableName, $this->driver) . ";\n";
            }
        }
        
        return $sql;
    }
    
    /**
     * Vygeneruje definici sloupce
     * 
     * @param string $columnName
     * @param Column $columnAttr
     * @return string
     */
    private function generateColumnDefinition(string $columnName, Column $columnAttr): string
    {
        $quoted = $this->driver === 'mysql' ? "`{$columnName}`" : $columnName;
        
        // Získej SQL typ z TypeInterface
        $sqlType = $columnAttr->type->getSqlType($this->driver);
        
        $def = "{$quoted} {$sqlType}";
        
        // NULL / NOT NULL
        if ($columnAttr->nullable) {
            $def .= " NULL";
        } else {
            $def .= " NOT NULL";
        }
        
        // AUTO_INCREMENT
        if ($columnAttr->autoIncrement) {
            if ($this->driver === 'mysql') {
                $def .= " AUTO_INCREMENT";
            } elseif ($this->driver === 'pgsql') {
                // PostgreSQL používá SERIAL nebo SEQUENCE
                // Typ by měl být SERIAL místo INTEGER AUTO_INCREMENT
                $def = str_replace('INTEGER NOT NULL', 'SERIAL', $def);
            } elseif ($this->driver === 'sqlite') {
                $def .= " AUTOINCREMENT";
            }
        }
        
        // DEFAULT
        if ($columnAttr->default !== null && !$columnAttr->autoIncrement) {
            $default = $this->formatDefaultValue($columnAttr->default);
            $def .= " DEFAULT {$default}";
        }
        
        // UNIQUE
        if ($columnAttr->unique && !$columnAttr->primaryKey) {
            $def .= " UNIQUE";
        }
        
        // Komentář (MySQL podporuje)
        if ($this->driver === 'mysql' && $columnAttr->description) {
            $comment = addslashes($columnAttr->description);
            $def .= " COMMENT '{$comment}'";
        }
        
        return $def;
    }
    
    /**
     * Naformátuje výchozí hodnotu pro SQL
     * 
     * @param mixed $value
     * @return string
     */
    private function formatDefaultValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        
        if (is_string($value)) {
            return "'" . addslashes($value) . "'";
        }
        
        return 'NULL';
    }
    
    /**
     * Vygeneruje definici indexu pro CREATE TABLE
     * 
     * @param Index $index
     * @return string
     */
    private function generateIndexDefinition(Index $index): string
    {
        $unique = $index->unique ? 'UNIQUE ' : '';
        
        $columns = implode(', ', array_map(function($col) {
            return $this->driver === 'mysql' ? "`{$col}`" : $col;
        }, $index->columns));
        
        $type = '';
        if ($index->type && $this->driver === 'mysql') {
            $type = " USING {$index->type}";
        }
        
        if ($this->driver === 'mysql') {
            return "{$unique}KEY `{$index->name}` ({$columns}){$type}";
        } else {
            // PostgreSQL indexy se dělají mimo CREATE TABLE
            return "-- Index {$index->name} bude vytvořen samostatným příkazem";
        }
    }
    
    /**
     * Vygeneruje definici cizího klíče
     * 
     * @param ForeignKey $fk
     * @return string
     */
    private function generateForeignKeyDefinition(ForeignKey $fk): string
    {
        if ($this->driver === 'mysql') {
            $columns = implode(', ', array_map(fn($col) => "`{$col}`", $fk->columns));
            $refColumns = implode(', ', array_map(fn($col) => "`{$col}`", $fk->referencedColumns));
            
            return "CONSTRAINT `{$fk->name}` FOREIGN KEY ({$columns}) " .
                   "REFERENCES `{$fk->referencedTable}` ({$refColumns}) " .
                   "ON DELETE {$fk->onDelete} ON UPDATE {$fk->onUpdate}";
        } else {
            $columns = implode(', ', $fk->columns);
            $refColumns = implode(', ', $fk->referencedColumns);
            
            return "CONSTRAINT {$fk->name} FOREIGN KEY ({$columns}) " .
                   "REFERENCES {$fk->referencedTable} ({$refColumns}) " .
                   "ON DELETE {$fk->onDelete} ON UPDATE {$fk->onUpdate}";
        }
    }
}

// === Hlavní část skriptu ===
// Spustí se pouze pokud je soubor spuštěn přímo z příkazové řádky

if (php_sapi_name() === 'cli' && (empty($_SERVER['SCRIPT_FILENAME']) || realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__)) {
    // Zjisti driver z parametrů příkazové řádky
    $driver = $argv[1] ?? 'mysql';
    
    if (!in_array($driver, ['mysql', 'pgsql', 'sqlite'])) {
        echo "❌ Neplatný driver: {$driver}\n";
        echo "Podporované drivery: mysql, pgsql, sqlite\n";
        exit(1);
    }
    
    // Vytvoř a spusť generátor
    $generator = new MigrationGenerator($driver);
    $generator->generate();
}
