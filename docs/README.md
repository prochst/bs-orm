# ORM Dokumentace

Kompletní dokumentace a příklady použití vlastního ORM systému.

## Přehled

Tento ORM (Object-Relational Mapping) systém poskytuje:

- 🗃️ **Active Record pattern** - entity reprezentují řádky v databázi
- 🔄 **Repository pattern** - oddělení datové vrstvy od business logiky
- 🔗 **Relace** - HasOne, HasMany, BelongsTo, BelongsToMany
- 🏷️ **Metadata** - labely, popisy, placeholdery pro automatické generování UI
- 🌍 **Vícejazyčnost** - překlady labelů a formátování podle locale
- 📊 **Type-safe** - silná typová kontrola PHP 8.4+
- 🔒 **Asymetrické properties** - intuitivní čtení hodnot (`$user->email`), změny pouze přes settery
- ⚡ **Eager loading** - předcházení N+1 problému
- 🔍 **Sledování změn** - optimalizované UPDATE dotazy

## Rychlý start

```php
use App\Core\Orm\Dbal;
use App\Core\Orm\Repository;
use App\Model\User;

// Připojení k databázi
$dbal = new Dbal('mysql:host=localhost;dbname=test', 'root', 'password');
$userRepo = new Repository($dbal, User::class);

// Vytvoření nového uživatele
$user = new User();
$user->setEmail('john@example.com');
$user->setName('John Doe');
$userRepo->save($user);

// Načtení uživatele
$user = $userRepo->find(1);
echo $user->email;  // Přímý přístup pro čtení

// Aktualizace
$user->setName('Jane Doe');
$userRepo->save($user);

// Smazání
$userRepo->delete($user);
```

## Dokumentace

### 📚 Průvodce krok za krokem

1. **[Základní použití](01-basic-usage.md)**
   - Inicializace databáze a repository
   - CRUD operace (Create, Read, Update, Delete)
   - Transakce
   - Sledování změn v entitách
   - Best practices

2. **[Práce s relacemi](02-relations.md)**
   - HasOne (1:1) - entita má jednu související entitu
   - HasMany (1:N) - entita má více souvisejících entit
   - BelongsTo (N:1) - entita patří k jiné entitě
   - BelongsToMany (M:N) - vazba přes pivotní tabulku
   - Lazy vs Eager loading
   - Řešení N+1 problému

3. **[Labely a metadata](03-labels-metadata.md)**
   - Definice uživatelsky přívětivých názvů
   - Popisy, placeholdery a nápovědy
   - Automatické generování formulářů
   - Automatické generování datových gridů
   - Integrace s Nette Forms

4. **[Formátování podle locale](04-locale-formatting.md)**
   - Formátování čísel podle jazyka
   - Formátování měn (CZK, EUR, USD, ...)
   - Formátování datumů a časů
   - EnumType s překlady
   - Generování vícejazyčných zobrazení

5. **[Vícejazyčnost](05-translations.md)**
   - Překlady labelů tabulek a sloupců
   - Vícejazyčné popisy a nápovědy
   - Přepínání jazyků v aplikaci
   - Vícejazyčné formuláře a gridy
   - Kompletní příklad e-shopu

## Struktura souborů

```
app/Core/Orm/
├── Column.php              # Atribut pro definici sloupce
├── Dbal.php                # Database Abstraction Layer
├── Entity.php              # Základní třída pro všechny entity
├── ForeignKey.php          # Atribut pro cizí klíče
├── Index.php               # Atribut pro indexy
├── Repository.php          # Repository pro práci s entitami
├── RepositoryInterface.php # Interface pro repository
├── Table.php               # Atribut pro definici tabulky
├── TranslationHelper.php   # Pomocník pro překlady
├── Relations/              # Atributy pro relace
│   ├── BelongsTo.php      # N:1 relace
│   ├── BelongsToMany.php  # M:N relace
│   ├── HasMany.php        # 1:N relace
│   ├── HasOne.php         # 1:1 relace
│   └── RelationInterface.php
├── Types/                  # Datové typy s formátováním
│   ├── BooleanType.php
│   ├── CurrencyType.php
│   ├── DateTimeType.php
│   ├── DecimalType.php
│   ├── EnumType.php
│   ├── IntegerType.php
│   ├── LocaleManager.php
│   ├── StringType.php
│   ├── TextType.php
│   └── TypeInterface.php
├── Migration/              # Nástroje pro migrace
│   └── ...
└── docs/                   # Dokumentace (tento adresář)
    ├── README.md          # Tento soubor
    ├── 01-basic-usage.md
    ├── 02-relations.md
    ├── 03-labels-metadata.md
    ├── 04-locale-formatting.md
    └── 05-translations.md
```

## Příklady entit

### Základní entita

```php
<?php

namespace App\Model;

use App\Core\Orm\Entity;
use App\Core\Orm\Table;
use App\Core\Orm\Column;
use App\Core\Orm\Types\IntegerType;
use App\Core\Orm\Types\StringType;
use App\Core\Orm\Types\DateTimeType;

#[Table(
    name: 'users',
    label: 'Uživatelé'
)]
class User extends Entity
{
    #[Column(
        type: new IntegerType(),
        primaryKey: true,
        autoIncrement: true,
        label: 'ID'
    )]
    public private(set) ?int $id = null;
    
    #[Column(
        type: new StringType(255),
        label: 'E-mail',
        nullable: false
    )]
    public private(set) string $email;
    
    #[Column(
        type: new StringType(100),
        label: 'Jméno'
    )]
    public private(set) string $name;
    
    #[Column(
        type: new DateTimeType(),
        label: 'Vytvořeno'
    )]
    public private(set) \DateTimeImmutable $createdAt;
    
    // Asymetrické properties (PHP 8.4+):
    // - public čtení: $user->email ✓
    // - private zápis: $user->email = 'x' ✗ (Error!)
    // - změny pouze přes settery, které volají markFieldAsModified()
    
    public function setEmail(string $email): void {
        $this->email = $email;
        $this->markFieldAsModified('email');
    }
    
    public function setName(string $name): void {
        $this->name = $name;
        $this->markFieldAsModified('name');
    }
    
    public function setCreatedAt(\DateTimeImmutable $createdAt): void {
        $this->createdAt = $createdAt;
        $this->markFieldAsModified('createdAt');
    }
}
```

### Entita s relacemi

```php
#[Table(name: 'posts')]
class Post extends Entity
{
    #[Column(type: new IntegerType(), primaryKey: true)]
    public private(set) ?int $id = null;
    
    #[Column(type: new IntegerType())]
    public private(set) int $userId;
    
    #[Column(type: new StringType(255))]
    public private(set) string $title;
    
    // Relace N:1 - příspěvek patří uživateli
    #[BelongsTo(entityClass: User::class)]
    public private(set) ?User $author = null;
    
    // Relace 1:N - příspěvek má mnoho komentářů
    #[HasMany(entityClass: Comment::class)]
    public private(set) array $comments = [];
    
    // Pouze settery pro tracking změn
    public function setUserId(int $userId): void {
        $this->userId = $userId;
        $this->markFieldAsModified('userId');
    }
    
    public function setTitle(string $title): void {
        $this->title = $title;
        $this->markFieldAsModified('title');
    }
}
```

### Vícejazyčná entita

```php
#[Table(
    name: 'products',
    label: [
        'cs_CZ' => 'Produkty',
        'en_US' => 'Products',
        'de_DE' => 'Produkte',
    ]
)]
class Product extends Entity
{
    #[Column(
        type: new StringType(255),
        label: [
            'cs_CZ' => 'Název produktu',
            'en_US' => 'Product Name',
            'de_DE' => 'Produktname',
        ],
        placeholder: [
            'cs_CZ' => 'Zadejte název',
            'en_US' => 'Enter name',
            'de_DE' => 'Namen eingeben',
        ]
    )]
    public private(set) string $name;
    
    #[Column(
        type: new CurrencyType('CZK'),
        label: [
            'cs_CZ' => 'Cena',
            'en_US' => 'Price',
            'de_DE' => 'Preis',
        ]
    )]
    public private(set) float $price;
    
    public function setName(string $name): void {
        $this->name = $name;
        $this->markFieldAsModified('name');
    }
    
    public function setPrice(float $price): void {
        $this->price = $price;
        $this->markFieldAsModified('price');
    }
}
```

## Klíčové vlastnosti

### Automatická detekce změn

ORM automaticky sleduje změny v entitách a generuje optimalizované UPDATE dotazy:

```php
$user = $userRepo->find(1);
$user->setName('New Name');
$user->setEmail('new@example.com');

// Vygeneruje: UPDATE users SET name = ?, email = ? WHERE id = ?
// (pouze změněná pole!)
$userRepo->save($user);
```

### Eager Loading

Předejděte N+1 problému pomocí eager loadingu:

```php
// ❌ Špatně - N+1 problém (1 + N dotazů)
$users = $userRepo->findAll();
foreach ($users as $user) {
    $user->loadRelation('posts', $userRepo); // Dotaz v cyklu!
    echo count($user->getPosts());
}

// ✅ Správně - Eager loading (2 dotazy celkem)
$users = $userRepo->findAllWithRelations(['posts']);
foreach ($users as $user) {
    echo count($user->getPosts()); // Již načteno
}
```

### Type Safety

Všechny operace jsou type-safe díky PHP 8.3+ features:

```php
// ✅ Správně
$user->setEmail('john@example.com');

// ❌ Chyba v compile time
$user->setEmail(123); // TypeError

// ✅ Nullable types
$user->getPhone(); // ?string

// ✅ Return types
public function getEmail(): string { ... }
```

## Požadavky

- **PHP 8.4 nebo vyšší** - využívá asymetrické properties (`public private(set)`)
- PDO extension
- MySQL 5.7+ / MariaDB 10.2+

### Proč PHP 8.4+?

ORM využívá **asymetrické properties** (RFC: Asymmetric Visibility), které umožňují:

```php
public private(set) string $email;

// ✓ Čtení hodnoty - intuitivní a přímý přístup
echo $user->email;

// ✗ Přímý zápis - ZAKÁZÁN (compile error)
$user->email = 'new@example.com'; // Error!

// ✓ Změna pouze přes setter - zajištění tracking změn
$user->setEmail('new@example.com'); // Volá markFieldAsModified()
```

**Výhody:**
- 🎯 **Intuitivní syntax** - čtení jako `$user->email` místo `$user->getEmail()`
- 🔒 **Bezpečnost** - nelze omylem změnit hodnotu bez tracking změn
- 🚀 **Výkon** - přímý přístup k property, bez overhead volání metody
- 📝 **Čistý kód** - méně boilerplate kódu, žádné gettery

## Licence

Proprietární - pouze pro interní použití

## Autor

Váš tým

## Podpora

Pro otázky a problémy kontaktujte vývojový tým nebo vytvořte issue v interním repozitáři.

---

**Začněte s [Základním použitím](01-basic-usage.md) →**
