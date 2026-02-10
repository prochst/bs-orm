# Příklady použití generátoru migrací

Tento dokument obsahuje praktické příklady, jak používat generátor SQL migrací.

## 1. Vygenerování migrací

### Pro MySQL (výchozí)
```bash
cd /var/www/html/vyuka
php app/Core/Orm/Migration/generate-migrations.php mysql
```

### Pro PostgreSQL
```bash
php app/Core/Orm/Migration/generate-migrations.php pgsql
```

### Pro SQLite
```bash
php app/Core/Orm/Migration/generate-migrations.php sqlite
```

## 2. Kontrola vygenerovaných souborů

```bash
# Seznam vygenerovaných souborů
ls -lh app/Core/Orm/Migration/*.sql

# Zobrazení obsahu konkrétní migrace
cat app/Core/Orm/Migration/users.sql
```

## 3. Spuštění migrací

### Ruční spuštění jednotlivých migrací

#### MySQL
```bash
mysql -u root -p mydatabase < app/Core/Orm/Migration/roles.sql
mysql -u root -p mydatabase < app/Core/Orm/Migration/users.sql
mysql -u root -p mydatabase < app/Core/Orm/Migration/user-roles.sql
mysql -u root -p mydatabase < app/Core/Orm/Migration/posts.sql
mysql -u root -p mydatabase < app/Core/Orm/Migration/products.sql
```

#### PostgreSQL
```bash
psql -U postgres -d mydatabase -f app/Core/Orm/Migration/roles.sql
psql -U postgres -d mydatabase -f app/Core/Orm/Migration/users.sql
psql -U postgres -d mydatabase -f app/Core/Orm/Migration/user-roles.sql
psql -U postgres -d mydatabase -f app/Core/Orm/Migration/posts.sql
psql -U postgres -d mydatabase -f app/Core/Orm/Migration/products.sql
```

#### SQLite
```bash
sqlite3 database.db < app/Core/Orm/Migration/roles.sql
sqlite3 database.db < app/Core/Orm/Migration/users.sql
sqlite3 database.db < app/Core/Orm/Migration/user-roles.sql
sqlite3 database.db < app/Core/Orm/Migration/posts.sql
sqlite3 database.db < app/Core/Orm/Migration/products.sql
```

### Automatické spuštění všech migrací

```bash
# MySQL
./app/Core/Orm/Migration/run-migrations.sh mysql mydatabase root

# PostgreSQL
./app/Core/Orm/Migration/run-migrations.sh pgsql mydatabase postgres

# SQLite
./app/Core/Orm/Migration/run-migrations.sh sqlite database.db
```

## 4. Kompletní workflow

### Nový projekt od začátku

```bash
# 1. Vygeneruj migrace
php app/Core/Orm/Migration/generate-migrations.php mysql

# 2. Zkontroluj vygenerované SQL
cat app/Core/Orm/Migration/users.sql

# 3. Spusť migrace
./app/Core/Orm/Migration/run-migrations.sh mysql mydatabase root
```

### Aktualizace existujícího projektu

```bash
# 1. Zálohuj stávající migrace (pokud mají změny)
cp app/Core/Orm/Migration/*.sql /backup/migrations/

# 2. Vygeneruj nové migrace
php app/Core/Orm/Migration/generate-migrations.php mysql

# 3. Porovnej rozdíly
diff /backup/migrations/users.sql app/Core/Orm/Migration/users.sql

# 4. Spusť migrace na testovací DB
mysql -u root -p test_database < app/Core/Orm/Migration/users.sql

# 5. Pokud je vše v pořádku, spusť na produkční DB
mysql -u root -p prod_database < app/Core/Orm/Migration/users.sql
```

## 5. Přidání nového modelu

### Příklad: Vytvoření modelu Category

```php
<?php
// app/Model/Category.php

namespace App\Model;

use App\Core\Orm\Entity;
use App\Core\Orm\Table;
use App\Core\Orm\Column;
use App\Core\Orm\Index;
use App\Core\Orm\Types\IntegerType;
use App\Core\Orm\Types\StringType;

#[Table(
    name: 'categories',
    label: 'Kategorie',
    description: 'Kategorie produktů',
    indexes: [
        new Index('idx_slug', ['slug'], unique: true),
    ]
)]
class Category extends Entity
{
    #[Column(
        name: 'id',
        type: new IntegerType(unsigned: true),
        primaryKey: true,
        autoIncrement: true
    )]
    private ?int $id = null;
    
    #[Column(
        name: 'name',
        type: new StringType(maxLength: 100),
        nullable: false
    )]
    private string $name;
    
    #[Column(
        name: 'slug',
        type: new StringType(maxLength: 100),
        nullable: false,
        unique: true
    )]
    private string $slug;
    
    // gettery a settery...
}
```

### Vygenerování migrace pro nový model

```bash
# Vygeneruj migrace (vytvoří se nový categories.sql)
php app/Core/Orm/Migration/generate-migrations.php mysql

# Zkontroluj vygenerovaný SQL
cat app/Core/Orm/Migration/categories.sql

# Spusť migraci
mysql -u root -p mydatabase < app/Core/Orm/Migration/categories.sql
```

## 6. Ladění a testování

### Kontrola syntaxe PHP

```bash
php -l app/Core/Orm/Migration/generate-migrations.php
```

### Test generátoru bez DB

```bash
# Pouze vygeneruj SQL soubory, nespouštěj je
php app/Core/Orm/Migration/generate-migrations.php mysql

# Zkontroluj obsah
for file in app/Core/Orm/Migration/*.sql; do
    echo "=== $file ==="
    cat "$file"
    echo ""
done
```

### Suchý běh SQL (kontrola syntaxe)

```bash
# MySQL - kontrola syntaxe bez provedení
mysql -u root -p mydatabase --verbose < app/Core/Orm/Migration/users.sql --dry-run

# Nebo ručně zkopíruj SQL do MySQL Workbench / phpMyAdmin
```

## 7. Běžné problémy a řešení

### Problem: "Class not found"

**Řešení:**
```bash
# Zkontroluj autoloader
composer dump-autoload

# Zkontroluj namespace v modelu
grep "namespace" app/Model/User.php
```

### Problem: "Foreign key constraint fails"

**Příčina:** Tabulky nejsou vytvořeny ve správném pořadí.

**Řešení:** Používej `run-migrations.sh` který respektuje pořadí:
```bash
./app/Core/Orm/Migration/run-migrations.sh mysql mydatabase root
```

### Problem: "Table already exists"

**Příčina:** Tabulka již existuje v databázi.

**Řešení:**
```bash
# Migrace obsahují DROP TABLE IF EXISTS, takže prostě znovu spusť:
mysql -u root -p mydatabase < app/Core/Orm/Migration/users.sql
```

## 8. Pokročilé použití

### Generování pro více databází najednou

```bash
#!/bin/bash
for driver in mysql pgsql sqlite; do
    echo "=== Generuji pro $driver ==="
    php app/Core/Orm/Migration/generate-migrations.php $driver
    
    # Přesuň do složky specifické pro driver
    mkdir -p "migrations/$driver"
    cp app/Core/Orm/Migration/*.sql "migrations/$driver/"
done
```

### Automatické verzování migrací

```bash
#!/bin/bash
VERSION=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="migrations/backups/$VERSION"

mkdir -p "$BACKUP_DIR"
cp app/Core/Orm/Migration/*.sql "$BACKUP_DIR/"

echo "Migrace zálohovány do: $BACKUP_DIR"
```

### CI/CD integrace

```yaml
# .gitlab-ci.yml nebo .github/workflows/migrations.yml
generate_migrations:
  script:
    - php app/Core/Orm/Migration/generate-migrations.php mysql
    - git diff app/Core/Orm/Migration/*.sql
```

## 9. Dokumentace změn

Po každé změně v modelech je dobré dokumentovat změny:

```bash
# Vytvoř changelog
echo "## $(date '+%Y-%m-%d %H:%M:%S')" >> MIGRATIONS_CHANGELOG.md
echo "" >> MIGRATIONS_CHANGELOG.md
echo "### Změny:" >> MIGRATIONS_CHANGELOG.md
git diff app/Core/Orm/Migration/*.sql >> MIGRATIONS_CHANGELOG.md
echo "" >> MIGRATIONS_CHANGELOG.md
```

## 10. Best practices

1. **Vždy testuj na vývojové DB před produkcí**
2. **Zálohuj databázi před spuštěním migrací**
3. **Verzuj vygenerované SQL soubory v Gitu**
4. **Dokumentuj významné změny v modelech**
5. **Používej transakce pro více migrací najednou**

```bash
# Příklad transakce (MySQL)
mysql -u root -p mydatabase << EOF
START TRANSACTION;
source app/Core/Orm/Migration/roles.sql;
source app/Core/Orm/Migration/users.sql;
source app/Core/Orm/Migration/user-roles.sql;
COMMIT;
EOF
```

## 11. Dodatečné nástroje

### Kontrola struktury databáze

```bash
# MySQL
mysql -u root -p -e "SHOW TABLES;" mydatabase
mysql -u root -p -e "DESCRIBE users;" mydatabase

# PostgreSQL
psql -U postgres -d mydatabase -c "\dt"
psql -U postgres -d mydatabase -c "\d users"

# SQLite
sqlite3 database.db ".tables"
sqlite3 database.db ".schema users"
```

### Porovnání schémat

```bash
# Exportuj aktuální schéma
mysqldump -u root -p --no-data mydatabase > current_schema.sql

# Porovnej s vygenerovanými migracemi
diff current_schema.sql <(cat app/Core/Orm/Migration/*.sql)
```
