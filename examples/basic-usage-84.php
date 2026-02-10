<?php

/**
 * Příklad použití BS ORM pro PHP 8.4+
 * 
 * Tento soubor ukazuje, jak používat BS ORM v běžném PHP projektu.
 * 
 * VYŽADUJE PHP 8.4+
 * Využívá property hooks a asymetric visibility (public private(set))
 */

require_once __DIR__ . '/../vendor/autoload.php';

use prochst\bsOrm\Dbal;
use prochst\bsOrm\Repository;
use prochst\bsOrm\Entity;
use prochst\bsOrm\Table;
use prochst\bsOrm\Column;
use prochst\bsOrm\Types\IntegerType;
use prochst\bsOrm\Types\StringType;
use prochst\bsOrm\Types\DateTimeType;
use prochst\bsOrm\Types\BooleanType;
use prochst\bsOrm\Relations\HasMany;

// ===========================
// 1. Definice entit
// ===========================

#[Table(
    name: 'users',
    label: 'Uživatelé',
    labels: [
        'cs_CZ' => 'Uživatelé',
        'en_US' => 'Users',
    ]
)]
class User extends Entity
{
    #[Column(
        type: new IntegerType(),
        primaryKey: true,
        autoIncrement: true
    )]
    public private(set) ?int $id = null;

    #[Column(
        name: 'email',
        type: new StringType(maxLength: 255),
        label: 'Email',
        labels: [
            'cs_CZ' => 'E-mailová adresa',
            'en_US' => 'Email Address',
        ],
        nullable: false,
        unique: true
    )]
    public private(set) string $email;

    #[Column(
        type: new StringType(maxLength: 100),
        label: 'Name',
        labels: [
            'cs_CZ' => 'Jméno',
            'en_US' => 'Name',
        ]
    )]
    public private(set) string $name;

    #[Column(
        type: new BooleanType(),
        label: 'Active'
    )]
    public private(set) bool $active = true;

    #[Column(
        name: 'created_at',
        type: new DateTimeType(),
        label: 'Created At'
    )]
    public private(set) ?\DateTimeImmutable $createdAt = null;

    #[HasMany(entityClass: Post::class, foreignKey: 'user_id')]
    public private(set) array $posts = [];

    // Setters
    public function setEmail(string $email): void {
        $this->email = $email;
        $this->markFieldAsModified('email');
    }

    public function setName(string $name): void {
        $this->name = $name;
        $this->markFieldAsModified('name');
    }

    public function setActive(bool $active): void {
        $this->active = $active;
        $this->markFieldAsModified('active');
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void {
        $this->createdAt = $createdAt;
        $this->markFieldAsModified('createdAt');
    }
}

#[Table(name: 'posts')]
class Post extends Entity
{
    #[Column(type: new IntegerType(), primaryKey: true, autoIncrement: true)]
    public private(set) ?int $id = null;

    #[Column(type: new IntegerType())]
    public private(set) int $user_id;

    #[Column(type: new StringType(maxLength: 255))]
    public private(set) string $title;

    #[Column(type: new DateTimeType())]
    public private(set) ?\DateTimeImmutable $created_at = null;

    public function setUserId(int $user_id): void {
        $this->user_id = $user_id;
        $this->markFieldAsModified('user_id');
    }

    public function setTitle(string $title): void {
        $this->title = $title;
        $this->markFieldAsModified('title');
    }

    public function setCreatedAt(\DateTimeImmutable $created_at): void {
        $this->created_at = $created_at;
        $this->markFieldAsModified('created_at');
    }
}

// ===========================
// 2. Připojení k databázi
// ===========================

// Vytvořte PDO připojení
$pdo = new PDO('mysql:host=localhost;dbname=test', 'root', 'password');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Vytvořte DBAL obal
$dbal = new Dbal($pdo);

// ===========================
// 3. Práce s Repository
// ===========================

// Vytvořte repository pro uživatele
$userRepo = new Repository($dbal, User::class);
$postRepo = new Repository($dbal, Post::class);

// ===========================
// PŘÍKLAD 1: Vytvoření nového uživatele
// ===========================
echo "=== Vytvoření nového uživatele ===\n";

$user = new User();
$user->setEmail('john.doe@example.com');
$user->setName('John Doe');
$user->setActive(true);
$user->setCreatedAt(new DateTimeImmutable());

if ($userRepo->save($user)) {
    echo "✓ Uživatel vytvořen s ID: " . $user->id . "\n\n";
}

// ===========================
// PŘÍKLAD 2: Načtení uživatele podle ID
// ===========================
echo "=== Načtení uživatele podle ID ===\n";

$user = $userRepo->find(1);
if ($user instanceof User) {
    echo "Uživatel: " . $user->name . "\n";
    echo "Email: " . $user->email . "\n";
    echo "Aktivní: " . ($user->active ? 'Ano' : 'Ne') . "\n\n";
}

// ===========================
// PŘÍKLAD 3: Aktualizace uživatele
// ===========================
echo "=== Aktualizace uživatele ===\n";

$user = $userRepo->find(1);
if ($user instanceof User) {
    $user->setName('John Updated Doe');
    $userRepo->save($user); // Aktualizuje pouze změněné pole 'name'
    echo "✓ Uživatel aktualizován\n\n";
}

// ===========================
// PŘÍKLAD 4: Vyhledávání podle kritérií
// ===========================
echo "=== Vyhledávání aktivních uživatelů ===\n";
/** @var User[] $activeUsers */
$activeUsers = $userRepo->findBy(
    ['active' => true],
    ['name' => 'ASC'],
    limit: 10
);

echo "Nalezeno " . count($activeUsers) . " aktivních uživatelů:\n";
foreach ($activeUsers as $user) {
    echo "- " . $user->name . " (" . $user->email . ")\n";
}
echo "\n";

// ===========================
// PŘÍKLAD 5: Počítání záznamů
// ===========================
echo "=== Počítání záznamů ===\n";

$totalUsers = $userRepo->count();
$activeCount = $userRepo->count(['active' => true]);

echo "Celkem uživatelů: $totalUsers\n";
echo "Aktivních: $activeCount\n\n";

// ===========================
// PŘÍKLAD 6: Eager loading (načtení relací)
// ===========================
echo "=== Eager loading uživatelů s posty ===\n";
/** @var User[] $users */
$users = $userRepo->findAllWithRelations(['posts']);
foreach ($users as $user) {
    echo $user->name . " má " . count($user->posts) . " příspěvků\n";
}
echo "\n";

// ===========================
// PŘÍKLAD 7: Transakce
// ===========================
echo "=== Použití transakcí ===\n";

try {
    $dbal->transaction(function($dbal) use ($userRepo, $postRepo) {
        // Vytvoř uživatele
        $user = new User();
        $user->setEmail('jane.doe@example.com');
        $user->setName('Jane Doe');
        $user->setCreatedAt(new DateTimeImmutable());
        $userRepo->save($user);
        
        // Vytvoř post pro uživatele
        $post = new Post();
        $post->setUserId($user->id);
        $post->setTitle('První příspěvek Jane');
        $post->setCreatedAt(new DateTimeImmutable());
        $postRepo->save($post);
        
        echo "✓ Transakce úspěšná - uživatel a post vytvořeny\n";
    });
} catch (Exception $e) {
    echo "✗ Transakce selhala: " . $e->getMessage() . "\n";
}
echo "\n";

// ===========================
// PŘÍKLAD 8: Smazání záznamu
// ===========================
echo "=== Smazání uživatele ===\n";

$user = $userRepo->find(1);
if ($user && $userRepo->delete($user)) {
    echo "✓ Uživatel smazán\n";
}

echo "\n=== Konec příkladů ===\n";
