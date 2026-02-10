# Generátor SQL Migrací

Automatický generátor SQL skriptů pro vytvoření tabulek z ORM modelů.

## Popis

Tento nástroj prochází všechny PHP třídy ve složce `app/Model` a pro ty, které obsahují atribut `#[Table()]`, vygeneruje SQL skripty pro vytvoření databázových tabulek.

Generátor podporuje:
- ✅ Všechny datové typy (IntegerType, StringType, DateTimeType, BooleanType, TextType, DecimalType, ...)
- ✅ Constraints (PRIMARY KEY, UNIQUE, NOT NULL, DEFAULT, AUTO_INCREMENT)
- ✅ Indexy (normální i UNIQUE)
- ✅ Cizí klíče (Foreign Keys) s ON DELETE a ON UPDATE akcemi
- ✅ Komentáře (pro MySQL)
- ✅ Více databázových driverů (MySQL, PostgreSQL, SQLite)

## Použití

### Základní použití (MySQL)

```bash
php app/Core/Orm/Migration/generate-migrations.php
```

### S explicitním databázovým driverem

```bash
# MySQL (výchozí)
php app/Core/Orm/Migration/generate-migrations.php mysql

# PostgreSQL
php app/Core/Orm/Migration/generate-migrations.php pgsql

# SQLite
php app/Core/Orm/Migration/generate-migrations.php sqlite
```

### Spuštění z kořenové složky projektu

```bash
cd /var/www/html/vyuka
php app/Core/Orm/Migration/generate-migrations.php
```

### Alternativní způsob spuštění

```bash
# Přímé spuštění (pokud má skript správná práva)
./app/Core/Orm/Migration/generate-migrations.php

# Nebo z Migration složky
cd app/Core/Orm/Migration
./generate-migrations.php mysql
```

## Výstup

Pro každý model s atributem `#[Table()]` se vytvoří SQL soubor:

```
app/Core/Orm/Migration/
├── generate-migrations.php   # Generátor
├── README.md                 # Tato dokumentace
├── users.sql                 # Migrace pro tabulku users
├── roles.sql                 # Migrace pro tabulku roles
├── posts.sql                 # Migrace pro tabulku posts
├── user-roles.sql            # Migrace pro tabulku user-roles
└── products.sql              # Migrace pro tabulku products
```

## Příklad vygenerovaného SQL (MySQL)

```sql
-- Migrace pro tabulku: users
-- Vygenerováno: 2026-02-07 10:30:00
-- Tabulka uživatelů systému

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Primární klíč uživatele',
    `email` VARCHAR(255) NOT NULL UNIQUE COMMENT 'Unikátní e-mailová adresa uživatele',
    `password` VARCHAR(255) NOT NULL COMMENT 'Hash hesla uživatele',
    `name` VARCHAR(100) NULL COMMENT 'Celé jméno uživatele',
    `active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Určuje, zda je uživatelský účet aktivní',
    `created_at` DATETIME NOT NULL COMMENT 'Datum a čas vytvoření účtu',
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_email` (`email`),
    KEY `idx_name` (`name`),
    KEY `idx_active` (`active`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Příklad modelu

```php
<?php

namespace App\Model;

use App\Core\Orm\Entity;
use App\Core\Orm\Table;
use App\Core\Orm\Column;
use App\Core\Orm\Index;
use App\Core\Orm\ForeignKey;
use App\Core\Orm\Types\IntegerType;
use App\Core\Orm\Types\StringType;

#[Table(
    name: 'users',
    label: 'Uživatelé',
    description: 'Tabulka uživatelů systému',
    indexes: [
        new Index('idx_email', ['email'], unique: true),
        new Index('idx_name', ['name']),
    ]
)]
class User extends Entity
{
    #[Column(
        name: 'id',
        label: 'ID',
        type: new IntegerType(unsigned: true),
        primaryKey: true,
        autoIncrement: true,
        description: 'Primární klíč uživatele'
    )]
    private ?int $id = null;
    
    #[Column(
        name: 'email',
        label: 'E-mail',
        type: new StringType(maxLength: 255),
        nullable: false,
        unique: true,
        description: 'Unikátní e-mailová adresa'
    )]
    private string $email;
    
    // ... další sloupce
}
```

## Spuštění vygenerovaných migrací

### MySQL

```bash
mysql -u root -p database_name < app/Core/Orm/Migration/users.sql
mysql -u root -p database_name < app/Core/Orm/Migration/roles.sql
```

### PostgreSQL

```bash
psql -U postgres -d database_name -f app/Core/Orm/Migration/users.sql
```

### SQLite

```bash
sqlite3 database.db < app/Core/Orm/Migration/users.sql
```

### Spuštění všech migrací najednou

```bash
# MySQL
for file in app/Core/Orm/Migration/*.sql; do
    mysql -u root -p database_name < "$file"
done

# PostgreSQL
for file in app/Core/Orm/Migration/*.sql; do
    psql -U postgres -d database_name -f "$file"
done

# SQLite
for file in app/Core/Orm/Migration/*.sql; do
    sqlite3 database.db < "$file"
done
```

## Rozdíly mezi databázovými drivery

### MySQL
- Podporuje `AUTO_INCREMENT`
- Používá `` ` `` (backticks) pro identifikátory
- Podporuje `ENGINE` a `CHARSET`
- Podporuje `COMMENT` u sloupců
- Indexy jsou součástí CREATE TABLE

### PostgreSQL
- Používá `SERIAL` místo `AUTO_INCREMENT`
- Nepoužívá backticks
- Indexy se vytvářejí samostatnými `CREATE INDEX` příkazy
- Nepodporuje COMMENT v CREATE TABLE (použije se samostatný COMMENT ON příkaz)

### SQLite
- Podporuje `AUTOINCREMENT`
- Jednodušší syntaxe
- Omezená podpora pro ALTER TABLE

## Tipy a poznámky

### 1. Pořadí vytváření tabulek

Pokud máte cizí klíče, vytvářejte tabulky v tomto pořadí:
1. Nezávislé tabulky (např. `roles`)
2. Závislé tabulky (např. `users`)
3. Propojovací tabulky (např. `user_roles`)

### 2. Regenerace migrací

Skript přepíše existující SQL soubory. Pokud chcete zachovat změny, zálohujte si je.

### 3. Cizí klíče

Ujistěte se, že odkazované tabulky existují před vytvořením tabulky s cizím klíčem:

```php
#[Table(
    name: 'posts',
    foreignKeys: [
        new ForeignKey('fk_post_user', ['user_id'], 'users', ['id'], onDelete: 'CASCADE'),
    ]
)]
```

### 4. Modely bez #[Table()]

Pokud třída nemá atribut `#[Table()]`, generátor ji přeskočí:

```
⏭️  App\Model\SomeClass: Nemá atribut #[Table()], přeskakuji
```

### 5. Testování

Doporučujeme nejprve otestovat na vývojové databázi:

```bash
# Vygeneruj SQL
php app/Core/Orm/Migration/generate-migrations.php mysql

# Zkontroluj vygenerovaný SQL
cat app/Core/Orm/Migration/users.sql

# Spusť na testovací databázi
mysql -u root -p test_database < app/Core/Orm/Migration/users.sql
```

## Řešení problémů

### Chyba: "Třída neexistuje"

Ujistěte se, že:
- Máte správně nastavený autoloader
- Namespace v souboru odpovídá struktuře složek
- Soubor má správnou syntaxi PHP

### Chyba: "Nelze určit název třídy"

Zkontrolujte:
- Soubor obsahuje `namespace` deklaraci
- Soubor obsahuje `class` definici
- Syntaxe je správná

### Cizí klíče selhávají

- Ujistěte se, že odkazovaná tabulka existuje
- Ověřte, že typy sloupců odpovídají (např. INT UNSIGNED v obou tabulkách)
- V případě MySQL zkontrolujte, že používáte InnoDB engine

## Rozšíření

### Přidání vlastního typu

Pokud vytvoříte vlastní typ implementující `TypeInterface`:

```php
class CustomType implements TypeInterface
{
    public function getSqlType(string $driver): string
    {
        return 'VARCHAR(100)';
    }
    
    // ... další metody
}
```

Generátor ho automaticky rozpozná a použije.

### Přidání podpory pro další driver

Upravte metody `generateCreateTableSql()` a `generateColumnDefinition()` ve skriptu.

## Licence

Tento nástroj je součástí ORM systému projektu.
