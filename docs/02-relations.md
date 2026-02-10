# Práce s relacemi

ORM podporuje všechny běžné typy relací mezi entitami:

- **HasOne** (1:1) - entita má jednu související entitu
- **HasMany** (1:N) - entita má více souvisejících entit
- **BelongsTo** (N:1) - entita patří k jiné entitě
- **BelongsToMany** (M:N) - entita má více souvisejících entit přes pivotní tabulku

## Definice entit s relacemi

### User entita (HasMany a BelongsToMany)

```php
<?php

namespace App\Model;

use App\Core\Orm\Entity;
use App\Core\Orm\Table;
use App\Core\Orm\Column;
use App\Core\Orm\Relations\HasMany;
use App\Core\Orm\Relations\BelongsToMany;
use App\Core\Orm\Types\IntegerType;
use App\Core\Orm\Types\StringType;

#[Table(name: 'users', label: 'Uživatelé')]
class User extends Entity
{
    #[Column(type: new IntegerType(), primaryKey: true, autoIncrement: true)]
    public private(set) ?int $id = null;
    
    #[Column(type: new StringType(255))]
    public private(set) string $email;
    
    // Relace 1:N - uživatel má více příspěvků
    #[HasMany(entityClass: Post::class)]
    public private(set) array $posts = [];
    
    // Relace M:N - uživatel má více rolí (přes user_roles)
    #[BelongsToMany(
        entityClass: Role::class,
        pivotTable: 'user_roles'
    )]
    public private(set) array $roles = [];
    
    public function setEmail(string $email): void {
        $this->email = $email;
        $this->markFieldAsModified('email');
    }
}
```

### Post entita (BelongsTo)

```php
<?php

namespace App\Model;

use App\Core\Orm\Entity;
use App\Core\Orm\Table;
use App\Core\Orm\Column;
use App\Core\Orm\ForeignKey;
use App\Core\Orm\Relations\BelongsTo;
use App\Core\Orm\Types\IntegerType;
use App\Core\Orm\Types\StringType;

#[Table(
    name: 'posts',
    label: 'Příspěvky',
    foreignKeys: [
        new ForeignKey('fk_post_user', ['user_id'], 'users', ['id'], onDelete: 'CASCADE')
    ]
)]
class Post extends Entity
{
    #[Column(type: new IntegerType(), primaryKey: true, autoIncrement: true)]
    public private(set) ?int $id = null;
    
    #[Column(type: new IntegerType())]
    public private(set) int $userId;
    
    #[Column(type: new StringType(255))]
    public private(set) string $title;
    
    #[Column(type: new StringType(5000))]
    public private(set) string $content;
    
    // Relace N:1 - příspěvek patří jednomu uživateli
    #[BelongsTo(entityClass: User::class, foreignKey: 'userId')]
    public private(set) ?User $author = null;
    
    public function setUserId(int $id): void {
        $this->userId = $id;
        $this->markFieldAsModified('userId');
    }
    public function setTitle(string $title): void {
        $this->title = $title;
        $this->markFieldAsModified('title');
    }
    public function setContent(string $content): void {
        $this->content = $content;
        $this->markFieldAsModified('content');
    }
}
```

### Role entita (pro BelongsToMany)

```php
<?php

namespace App\Model;

use App\Core\Orm\Entity;
use App\Core\Orm\Table;
use App\Core\Orm\Column;
use App\Core\Orm\Types\IntegerType;
use App\Core\Orm\Types\StringType;

#[Table(name: 'roles', label: 'Role')]
class Role extends Entity
{
    #[Column(type: new IntegerType(), primaryKey: true, autoIncrement: true)]
    public private(set) ?int $id = null;
    
    #[Column(type: new StringType(50))]
    public private(set) string $name;
    
    public function setName(string $name): void {
        $this->name = $name;
        $this->markFieldAsModified('name');
    }
}
```

## Načítání relací

### Lazy Loading

Relace se načtou až když k nim přistoupíte:

```php
use App\Core\Orm\Dbal;
use App\Core\Orm\Repository;
use App\Model\User;

$dbal = new Dbal('mysql:host=localhost;dbname=test', 'root', 'password');
$userRepo = new Repository($dbal, User::class);

// Načte pouze uživatele (1 dotaz)
$user = $userRepo->find(1);

// Načte příspěvky když k nim přistoupíme (2. dotaz)
$user->loadRelation('posts', $userRepo);
$posts = $user->posts;  // Přímý přístup k property

foreach ($posts as $post) {
    echo $post->title . "\n";  // Přímý přístup
}

// Načte role když k nim přistoupíme (3. dotaz)
$user->loadRelation('roles', $userRepo);
$roles = $user->roles;

foreach ($roles as $role) {
    echo $role->name . "\n";
}
```

### Eager Loading (doporučeno)

Načte entity i s relacemi najednou, čímž se předejde N+1 problému:

```php
// Načte uživatele včetně příspěvků a rolí (3 dotazy celkem, ne N+1)
$user = $userRepo->findWithRelations(1, ['posts', 'roles']);

// Relace jsou již načteny, žádné další dotazy
$posts = $user->posts;  // Přímý přístup
$roles = $user->roles;

foreach ($posts as $post) {
    echo $post->title . "\n";
}

foreach ($roles as $role) {
    echo $role->name . "\n";
}
```

### Eager Loading více entit

```php
// Načte všechny uživatele včetně relací
$users = $userRepo->findAllWithRelations(['posts', 'roles']);

foreach ($users as $user) {
    echo "Uživatel: " . $user->email . "\n";
    
    echo "Příspěvky:\n";
    foreach ($user->posts as $post) {
        echo "  - " . $post->title . "\n";
    }
    
    echo "Role:\n";
    foreach ($user->roles as $role) {
        echo "  - " . $role->name . "\n";
    }
}
```

### Eager Loading s kritérii

```php
// Najde aktivní uživatele včetně relací
$activeUsers = $userRepo->findByWithRelations(
    criteria: ['active' => true],
    relations: ['posts', 'roles'],
    orderBy: ['created_at' => 'DESC'],
    limit: 10
);

foreach ($activeUsers as $user) {
    echo $user->email . " má " . count($user->posts) . " příspěvků\n";
}
```

## Práce s BelongsTo relací

```php
use App\Model\Post;

$postRepo = new Repository($dbal, Post::class);

// Načte příspěvek včetně autora
$post = $postRepo->findWithRelations(1, ['author']);

$author = $post->author;  // Přímý přístup
if ($author !== null) {
    echo "Autor: " . $author->email . "\n";
    echo "Příspěvek: " . $post->title . "\n";
}
```

## N+1 problém a jeho řešení

### Špatně - N+1 problém ❌

```php
// 1 dotaz pro uživatele
$users = $userRepo->findAll();

// Pro každého uživatele 1 dotaz na příspěvky = N dotazů
foreach ($users as $user) {
    $user->loadRelation('posts', $userRepo); // Dotaz v cyklu!
    echo $user->email . " má " . count($user->posts) . " příspěvků\n";
}

// Celkem: 1 + N dotazů (pro 100 uživatelů = 101 dotazů!)
```

### Správně - Eager Loading ✓

```php
// 2 dotazy celkem: 1 pro uživatele + 1 pro všechny příspěvky
$users = $userRepo->findAllWithRelations(['posts']);

// Žádné další dotazy, vše už je načteno
foreach ($users as $user) {
    echo $user->email . " má " . count($user->posts) . " příspěvků\n";
}

// Celkem: 2 dotazy (bez ohledu na počet uživatelů!)
```

## Automatické odvození cizích klíčů

ORM automaticky odvozuje názvy cizích klíčů z názvů tříd:

```php
// User -> user_id
#[HasMany(entityClass: Post::class)]
// Automaticky se použije foreignKey: 'user_id'

// BlogPost -> blog_post_id
#[HasMany(entityClass: Comment::class)]
// Automaticky se použije foreignKey: 'blog_post_id'

// UserProfile -> user_profile_id
#[BelongsTo(entityClass: UserProfile::class)]
// Automaticky se použije foreignKey: 'user_profile_id'
```

### Vlastní názvy cizích klíčů

Pokud chcete použít vlastní název:

```php
#[HasMany(
    entityClass: Post::class,
    foreignKey: 'author_id'  // Místo výchozího 'user_id'
)]
private array $posts = [];

#[BelongsTo(
    entityClass: User::class,
    foreignKey: 'created_by'  // Místo výchozího 'user_id'
)]
private ?User $creator = null;
```

## BelongsToMany - M:N relace

### Struktura databáze

Pro M:N relaci potřebujete pivotní tabulku:

```sql
CREATE TABLE user_roles (
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);
```

### Automatické odvození názvu pivotní tabulky

```php
// User + Role -> role_user (abecedně)
#[BelongsToMany(entityClass: Role::class)]
private array $roles = [];

// Product + Tag -> product_tag
#[BelongsToMany(entityClass: Tag::class)]
private array $tags = [];
```

### Vlastní název pivotní tabulky

```php
#[BelongsToMany(
    entityClass: Role::class,
    pivotTable: 'user_roles',  // Vlastní název
    foreignPivotKey: 'user_id',
    relatedPivotKey: 'role_id'
)]
private array $roles = [];
```

## Best Practices

### 1. Používejte Eager Loading pro zobrazení seznamů

```php
// ✓ Správně
$users = $userRepo->findAllWithRelations(['posts', 'roles']);

// ✗ Špatně
$users = $userRepo->findAll();
// ... následují dotazy v cyklu
```

### 2. Dokumentujte typy relací jako pole

```php
// Typ je již jasný z property deklarace a atributu
#[HasMany(entityClass: Post::class)]
public private(set) array $posts = [];
```

### 3. Inicializujte relace jako prázdná pole

```php
#[HasMany(entityClass: Post::class)]
private array $posts = [];  // ✓ Ne null

#[BelongsToMany(entityClass: Role::class)]
private array $roles = [];  // ✓ Ne null
```

### 4. Používejte nullable pro BelongsTo

```php
#[BelongsTo(entityClass: User::class)]
public private(set) ?User $author = null;  // ✓ Může být null

// Přístup přímo přes property
if ($post->author !== null) {
    echo $post->author->email;
}
```

## Další kroky

- [Zpět na základní použití](01-basic-usage.md)
- [Labely a metadata](03-labels-metadata.md)
- [Formátování podle locale](04-locale-formatting.md)
- [Vícejazyčnost](05-translations.md)
