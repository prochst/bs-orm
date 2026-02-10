# BS ORM

Simple, lightweight ORM for PHP with multi-language support, relations, and migrations.

## Features

- ✅ **Active Record & Repository Pattern** - Choose the pattern that fits your needs
- ✅ **Type-Safe** - Full PHP 8.1+ type support with custom types
- ✅ **Relations** - HasOne, HasMany, BelongsTo, BelongsToMany
- ✅ **Multi-Language** - Built-in internationalization for labels, placeholders, help texts
- ✅ **Migrations** - Database schema versioning
- ✅ **Eager Loading** - Solve N+1 query problems
- ✅ **Change Tracking** - Only update modified fields
- ✅ **Validation** - Built-in and custom validators
- ✅ **Database Agnostic** - Works with MySQL, PostgreSQL, SQLite
- ✅ **PDO Based** - No external dependencies

## Installation

```bash
composer require prochst/bs-orm
```

## Quick Start

### 1. Define Your Entity

```php
<?php

use prochst\bsOrm\Entity;
use prochst\bsOrm\Table;
use prochst\bsOrm\Column;
use prochst\bsOrm\Types\StringType;
use prochst\bsOrm\Types\IntegerType;

#[Table(name: 'users', label: 'Users')]
class User extends Entity
{
    #[Column(
        type: new IntegerType(),
        primaryKey: true,
        autoIncrement: true
    )]
    private ?int $id = null;

    #[Column(
        name: 'email',
        type: new StringType(maxLength: 255),
        label: 'Email Address',
        nullable: false,
        unique: true
    )]
    private string $email;

    #[Column(
        type: new StringType(maxLength: 100),
        label: 'Name'
    )]
    private string $name;

    // Getters and setters
    public function getId(): ?int { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function getName(): string { return $this->name; }
    
    public function setEmail(string $email): void {
        $this->email = $email;
        $this->markFieldAsModified('email');
    }
    
    public function setName(string $name): void {
        $this->name = $name;
        $this->markFieldAsModified('name');
    }
}
```

### 2. Use the Repository

```php
<?php

use prochst\bsOrm\Dbal;
use prochst\bsOrm\Repository;

// Create PDO connection
$pdo = new PDO('mysql:host=localhost;dbname=mydb', 'user', 'password');
$dbal = new Dbal($pdo);

// Create repository
$userRepo = new Repository($dbal, User::class);

// Find user by ID
$user = $userRepo->find(1);

// Find all users
$users = $userRepo->findAll();

// Find with criteria
$activeUsers = $userRepo->findBy(
    ['active' => true],
    ['name' => 'ASC'],
    limit: 10
);

// Create new user
$user = new User();
$user->setEmail('john@example.com');
$user->setName('John Doe');
$userRepo->save($user);

// Update user
$user = $userRepo->find(1);
$user->setEmail('newemail@example.com');
$userRepo->save($user); // Only updates modified fields

// Delete user
$userRepo->delete($user);
```

### 3. Working with Relations

```php
<?php

use prochst\bsOrm\Relations\HasMany;
use prochst\bsOrm\Relations\BelongsTo;

#[Table(name: 'users')]
class User extends Entity
{
    #[Column(primaryKey: true)]
    private ?int $id = null;

    #[HasMany(entityClass: Post::class, foreignKey: 'user_id')]
    private array $posts = [];

    public function getPosts(): array { return $this->posts; }
}

#[Table(name: 'posts')]
class Post extends Entity
{
    #[Column(primaryKey: true)]
    private ?int $id = null;

    #[Column]
    private int $user_id;

    #[BelongsTo(entityClass: User::class, foreignKey: 'user_id')]
    private ?User $user = null;

    public function getUser(): ?User { return $this->user; }
}

// Eager loading to avoid N+1 queries
$users = $userRepo->findAllWithRelations(['posts']);
foreach ($users as $user) {
    echo $user->getName() . " has " . count($user->getPosts()) . " posts\n";
}
```

## Multi-Language Support

```php
<?php

use prochst\bsOrm\Types\LocaleManager;

#[Column(
    label: 'Email',
    labels: [
        'cs_CZ' => 'E-mailová adresa',
        'en_US' => 'Email Address',
        'de_DE' => 'E-Mail-Adresse',
    ],
    placeholder: 'Enter email',
    placeholders: [
        'cs_CZ' => 'Zadejte e-mail',
        'en_US' => 'Enter email',
        'de_DE' => 'E-Mail eingeben',
    ]
)]
private string $email;

// Set locale
LocaleManager::setDefaultLocale('cs_CZ');

// Get translated label
$label = User::getColumnLabel('email'); // "E-mailová adresa"
```

## Custom Types

BS ORM includes several built-in types:
- `StringType` - VARCHAR/TEXT with max length
- `IntegerType` - INT/BIGINT
- `DecimalType` - DECIMAL/NUMERIC with precision
- `BooleanType` - BOOLEAN/TINYINT(1)
- `DateTimeType` - DATETIME/TIMESTAMP
- `JsonType` - JSON data
- `EnumType` - ENUM values
- `BlobType` - BLOB/BYTEA
- `TextType` - TEXT/LONGTEXT
- `CurrencyType` - Money values with currency code

## Transactions

```php
<?php

$dbal->transaction(function($dbal) use ($userRepo, $postRepo) {
    $user = new User();
    $user->setEmail('john@example.com');
    $userRepo->save($user);
    
    $post = new Post();
    $post->setUserId($user->getId());
    $post->setTitle('My First Post');
    $postRepo->save($post);
});
```

## Requirements

- PHP 8.1 or higher
- PDO extension
- One of: MySQL, PostgreSQL, or SQLite

## License

MIT License - see LICENSE file for details

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Author

**prochst** - [GitHub Profile](https://github.com/prochst)
