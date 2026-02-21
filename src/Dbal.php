<?php

declare(strict_types=1);

namespace prochst\bsOrm;

use PDO;
use PDOException;
use PDOStatement;
use Nette\Database\Connection;

class Dbal
{
    private PDO $pdo;
    private string $driver;
    
    /**
     * Vytvoří DBAL obal nad PDO instancí
     * 
     * Přijímá přímo PDO objekt místo Nette\Database\Connection,
     * čímž snižuje závislost na Nette frameworku
     * a umožňuje použití s jakýmkoliv PDO kompatibilním driverem.
     * 
     * @param PDO $pdo PDO instance
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }
    
    /**
     * Tovární metoda pro vytvoření Dbal z Nette\Database\Connection
     * 
     * @param Connection $connection Nette Database Connection
     * 
     * @return self
     */
    public static function fromConnection(Connection $connection): self
    {
        return new self($connection->getPdo());
    }
    
    /**
     * Vrátí originální PDO instanci pro přímou práci s PDO API
     * 
     * @return PDO Přímý přístup k PDO objektu
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }
    
    /**
     * Vrátí název SQL driveru (mysql, pgsql, sqlite, atd.)
     * 
     * @return string Název PDO driveru (např. 'mysql', 'pgsql', 'sqlite')
     */
    public function getDriver(): string
    {
        return $this->driver;
    }
    
    /**
     * Provede SQL dotaz (INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, atd.)
     * 
     * @param string $sql SQL dotaz s případnými placeholdery (?)
     * @param array<int|string, mixed> $params Parametry pro prepared statement
     * 
     * @return bool True při úspěchu, false při selhání
     * 
     * @throws \RuntimeException Pokud příprava SQL statement selže
     */
    public function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->prepare($sql);
        $this->bindParams($stmt, $params);
        return $stmt->execute();
    }
    
    /**
     * Načte jeden řádek z výsledku dotazu jako asociativní pole
     * 
     * @param string $sql SELECT dotaz s případnými placeholdery (?)
     * @param array<int|string, mixed> $params Parametry pro prepared statement
     * 
     * @return array<string, mixed>|null Asociativní pole (sloupec => hodnota) nebo null pokud žádný řádek
     * 
     * @throws \RuntimeException Pokud příprava SQL statement selže
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }
    
    /**
     * Načte všechny řádky z výsledku dotazu jako pole asociativních polí
     * 
     * @param string $sql SELECT dotaz s případnými placeholdery (?)
     * @param array<int|string, mixed> $params Parametry pro prepared statement
     * 
     * @return array<int, array<string, mixed>> Pole řádků, kde každý řádek je asociativní pole (sloupec => hodnota)
     * 
     * @throws \RuntimeException Pokud příprava SQL statement selže
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Načte první sloupec prvního řádku z výsledku dotazu
     * 
     * Užitečné pro dotazy jako SELECT COUNT(*), SELECT MAX(id), atd.
     * 
     * @param string $sql SELECT dotaz s případnými placeholdery (?)
     * @param array<int|string, mixed> $params Parametry pro prepared statement
     * 
     * @return mixed Hodnota prvního sloupce prvního řádku, false pokud žádný řádek
     * 
     * @throws \RuntimeException Pokud příprava SQL statement selže
     */
    public function fetchSingle(string $sql, array $params = []): mixed
    {
        $stmt = $this->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();
        
        return $stmt->fetchColumn();
    }
    
    /**
     * Připraví SQL statement pro opakované použití
     * 
     * @param string $sql SQL dotaz s případnými placeholdery (?)
     * 
     * @return PDOStatement Připravený statement pro execute()
     * 
     * @throws \RuntimeException Pokud příprava SQL statement selže (chyba syntaxe, atd.)
     */
    /**
     * Naváže parametry na prepared statement se správným PDO typem
     *
     * Automaticky mapuje PHP typy na PDO konstanty:
     * - int  → PDO::PARAM_INT
     * - bool → PDO::PARAM_BOOL
     * - null → PDO::PARAM_NULL
     * - ostatní → PDO::PARAM_STR
     *
     * @param PDOStatement $stmt Připravený statement
     * @param array<int|string, mixed> $params Parametry k navázání
     */
    private function bindParams(PDOStatement $stmt, array $params): void
    {
        foreach (array_values($params) as $i => $value) {
            $type = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                is_null($value) => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };
            $stmt->bindValue($i + 1, $value, $type);
        }
    }

    public function prepare(string $sql): PDOStatement
    {
        try {
            return $this->pdo->prepare($sql);
        } catch (PDOException $e) {
            throw new \RuntimeException("Příprava SQL selhala: " . $e->getMessage() . "\nSQL: $sql", 0, $e);
        }
    }
    
    /**
     * Vrátí ID posledního vloženého záznamu (auto-increment)
     * 
     * Volat po INSERT dotazu pro získání vygenerovaného primárního klíče.
     * 
     * @param string|null $name Název sequence (pro PostgreSQL), null pro ostatní
     * 
     * @return string ID posledního vloženého záznamu jako řetězec
     */
    public function getLastInsertId(?string $name = null): string
    {
        return $this->pdo->lastInsertId($name);
    }
    
    /**
     * Zahájí databázovou transakci
     * 
     * Po zahájení transakce budou všechny změny dočasné dokud není volán commit().
     * Při rollback() se všechny změny zruší.
     * 
     * @return bool True při úspěchu
     * 
     * @throws PDOException Pokud je již aktivní transakce
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Potvrdí (commitne) aktivní transakci a uloží všechny změny do databáze
     * 
     * @return bool True při úspěchu
     * 
     * @throws PDOException Pokud není aktivní transakce
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }
    
    /**
     * Zruší (rollbackne) aktivní transakci a vrátí databázi do stavu před beginTransaction()
     * 
     * Všechny změny provedené v transakci budou zahozeny.
     * 
     * @return bool True při úspěchu
     * 
     * @throws PDOException Pokud není aktivní transakce
     */
    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }
    
    /**
     * Vrací, jestli je právě aktivní transakce
     * 
     * @return bool True pokud je aktivní transakce (mezi beginTransaction() a commit()/rollback())
     */
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }
    
    /**
     * Provede kód v transakci s automatickým commit/rollback
     * 
     * Zahájí transakci, provede callback, a pokud vše proběhne OK, provede commit.
     * Při výjimce provede rollback a výjimku znovu vyhodí.
     * 
     * @param callable(self): mixed $callback Funkce/closure která obdrží Dbal instanci jako parametr
     * 
     * @return mixed Návratová hodnota z $callback
     * 
     * @throws \Throwable Pokud $callback vyhodí výjimku (po rollbacku)
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * Escapuje identifikátor (název tabulky/sloupce) podle typu databáze
     * 
     * Automaticky detekuje typ databáze a použije správné uvozovky:
     * - MySQL: `identifier`
     * - PostgreSQL: "identifier"
     * - SQLite: "identifier"
     * 
     * @param string $identifier Název tabulky, sloupce nebo jiného DB objektu
     * 
     * @return string Escapovaný identifikátor s uvozovkami
     */
    public function escapeIdentifier(string $identifier): string
    {
        return match($this->driver) {
            'pgsql' => '"' . str_replace('"', '""', $identifier) . '"',
            'mysql' => '`' . str_replace('`', '``', $identifier) . '`',
            'sqlite' => '"' . str_replace('"', '""', $identifier) . '"',
            default => $identifier,
        };
    }
}
