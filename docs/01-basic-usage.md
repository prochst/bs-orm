# Základní použití ORM

Tento dokument ukazuje základní operace s ORM - vytváření, načítání, aktualizaci a mazání entit (CRUD operace).

## Inicializace

### Varianta 1: S PDO přímo

```php
<?php

declare(strict_types=1);

use App\Core\Orm\Dbal;
use App\Core\Orm\Repository;
use App\Model\User;

// Vytvoření PDO připojení
$pdo = new PDO(
    dsn: 'mysql:host=localhost;dbname=test;charset=utf8mb4',
    username: 'root',
    password: 'password',
    options: [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

// Vytvoření DBAL wrapperu
$dbal = new Dbal($pdo);

// Vytvoření repository pro entitu User
$userRepository = new Repository($dbal, User::class);
```

### Varianta 2: S Nette Framework a Dependency Injection

Pokud používáte Nette Framework, zaregistrujte DBAL jako službu v `services.neon`:

```neon
services:
    # Registrace DBAL jako služby
    - App\Core\Orm\Dbal::fromConnection
```

Pak použijte DI v presenterech nebo službách:

```php
<?php

declare(strict_types=1);

use App\Core\Orm\Dbal;
use App\Core\Orm\Repository;
use App\Model\User;
use Nette\Application\UI\Presenter;

class UserPresenter extends Presenter
{
    private Repository $userRepo;
    
    public function __construct(Dbal $dbal)
    {
        parent::__construct();
        $this->userRepo = new Repository($dbal, User::class);
    }
    
    public function actionDefault(): void
    {
        $users = $this->userRepo->findAll();
        $this->template->users = $users;
    }
    
    public function actionDetail(int $id): void
    {
        $user = $this->userRepo->find($id);
        if (!$user) {
            $this->error('Uživatel nenalezen');
        }
        $this->template->user = $user;
    }
}
```

Nebo pro opakované použití vytvořte service s repository:

```php
<?php

namespace App\Model;

use App\Core\Orm\Dbal;
use App\Core\Orm\Repository;

class UserFacade
{
    private Repository $userRepo;
    
    public function __construct(Dbal $dbal)
    {
        $this->userRepo = new Repository($dbal, User::class);
    }
    
    public function getUserById(int $id): ?User
    {
        return $this->userRepo->find($id);
    }
    
    public function getAllUsers(): array
    {
        return $this->userRepo->findAll();
    }
    
    public function createUser(string $email, string $name, string $password): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setName($name);
        $user->setPassword(password_hash($password, PASSWORD_DEFAULT));
        $user->setCreatedAt(new \DateTimeImmutable());
        
        $this->userRepo->save($user);
        
        return $user;
    }
}
```

A v presenteru:

```php
class UserPresenter extends Presenter
{
    public function __construct(
        private UserFacade $userFacade,
    ) {
        parent::__construct();
    }
    
    public function actionDefault(): void
    {
        $this->template->users = $this->userFacade->getAllUsers();
    }
}
```

## CREATE - Vytvoření nové entity

```php
// Vytvoření nového uživatele
$user = new User();
$user->setEmail('john@example.com');
$user->setPassword(password_hash('secret123', PASSWORD_DEFAULT));
$user->setName('John Doe');
$user->setCreatedAt(new DateTimeImmutable());

// Uložení do databáze (INSERT)
$userRepository->save($user);

// Po uložení má entita automaticky nastavené ID
echo "Nový uživatel má ID: " . $user->id;  // Přímý přístup pro čtení
```

## READ - Načítání entit

### Načtení podle ID

```php
// Najde uživatele s ID = 1
$user = $userRepository->find(1);

if ($user === null) {
    echo "Uživatel nenalezen";
} else {
    echo "Email: " . $user->email;  // Přímý přístup - intuitivní čtení
    echo "Jméno: " . $user->name;
}
```

### Načtení všech záznamů

```php
// Načte všechny uživatele
$allUsers = $userRepository->findAll();

foreach ($allUsers as $user) {
    echo $user->email . "\n";  // Přímý přístup pro čtení
}
```

### Načtení podle kritérií

```php
// Najde aktivní uživatele, seřadí podle data vytvoření, max 10 záznamů
$activeUsers = $userRepository->findBy(
    criteria: ['active' => true],
    orderBy: ['created_at' => 'DESC'],
    limit: 10
);

// Složitější kritéria
$users = $userRepository->findBy(
    criteria: [
        'active' => true,
        'role' => 'admin'
    ],
    orderBy: ['name' => 'ASC', 'created_at' => 'DESC'],
    limit: 20,
    offset: 0  // Pro stránkování
);
```

### Počítání záznamů

```php
// Spočítá všechny uživatele
$totalUsers = $userRepository->count();

// Spočítá aktivní uživatele
$activeCount = $userRepository->count(['active' => true]);

echo "Celkem uživatelů: {$totalUsers}, aktivních: {$activeCount}";
```

## UPDATE - Aktualizace entity

```php
// Načteme uživatele
$user = $userRepository->find(1);

// Změníme data
$user->setName('Jane Doe');
$user->setEmail('jane@example.com');

// Uložíme změny (UPDATE)
// ORM automaticky detekuje změněná pole a aktualizuje pouze ta
$userRepository->save($user);
```

### Sledování změn

ORM automaticky sleduje, která pole byla změněna:

```php
$user = $userRepository->find(1);

// Změníme několik polí
$user->setName('New Name');
$user->setEmail('new@example.com');

// Zjistíme, která pole byla změněna
$modifiedFields = $user->getModifiedFields();
print_r($modifiedFields);
// Výstup: ['name', 'email']

// Při save() se vygeneruje UPDATE pouze pro změněná pole:
// UPDATE users SET name = ?, email = ? WHERE id = ?
$userRepository->save($user);
```

## DELETE - Smazání entity

```php
// Načteme uživatele
$user = $userRepository->find(1);

// Smažeme ho z databáze
$userRepository->delete($user);
```

## Transakce

Pro zajištění konzistence dat při více operacích:

```php
// Všechny operace proběhnou atomicky - buď všechny uspějí, nebo se zruší všechny
$dbal->transaction(function($dbal) use ($userRepository) {
    // Vytvoření prvního uživatele
    $user1 = new User();
    $user1->setEmail('user1@example.com');
    $user1->setPassword(password_hash('pass', PASSWORD_DEFAULT));
    $user1->setCreatedAt(new DateTimeImmutable());
    $userRepository->save($user1);
    
    // Vytvoření druhého uživatele
    $user2 = new User();
    $user2->setEmail('user2@example.com');
    $user2->setPassword(password_hash('pass', PASSWORD_DEFAULT));
    $user2->setCreatedAt(new DateTimeImmutable());
    $userRepository->save($user2);
    
    // Pokud nastane chyba, obě operace se zruší (ROLLBACK)
    // Pokud vše proběhne OK, změny se potvrdí (COMMIT)
});
```

### Ruční řízení transakcí

```php
try {
    $dbal->beginTransaction();
    
    $user1 = new User();
    $user1->setEmail('user1@example.com');
    $user1->setCreatedAt(new DateTimeImmutable());
    $userRepository->save($user1);
    
    // Nějaká komplexní logika...
    
    $user2 = new User();
    $user2->setEmail('user2@example.com');
    $user2->setCreatedAt(new DateTimeImmutable());
    $userRepository->save($user2);
    
    $dbal->commit(); // Potvrdí změny
    
} catch (\Exception $e) {
    $dbal->rollback(); // Zruší změny
    throw $e;
}
```

## Best Practices

### 1. Vždy používejte type hints

```php
// Dobře ✓
public function getUser(int $id): ?User
{
    return $this->userRepository->find($id);
}

// Špatně ✗
public function getUser($id)
{
    return $this->userRepository->find($id);
}
```

### 2. Kontrolujte null hodnoty

```php
// Dobře ✓
$user = $userRepository->find($id);
if ($user === null) {
    throw new \RuntimeException('Uživatel nenalezen');
}
echo $user->name;  // Přímý přístup

// Špatně ✗ (může způsobit fatal error)
$user = $userRepository->find($id);
echo $user->name; // může být null!
```

### 3. Používejte transakce pro více operací

```php
// Dobře ✓
$dbal->transaction(function($dbal) use ($repo1, $repo2) {
    $repo1->save($entity1);
    $repo2->save($entity2);
});

// Špatně ✗ (nekonzistentní stav při chybě)
$repo1->save($entity1);
$repo2->save($entity2); // pokud selže, entity1 zůstane uložena
```

### 4. Volání markFieldAsModified() v setterech

```php
// Dobře ✓
public function setName(string $name): void
{
    $this->name = $name;
    $this->markFieldAsModified('name'); // ORM pozná změnu
}

// Špatně ✗ (ORM nepozná změnu, neaktualizuje)
public function setName(string $name): void
{
    $this->name = $name;
}
```

## Další kroky

- [Práce s relacemi](02-relations.md) - HasOne, HasMany, BelongsTo, BelongsToMany
- [Labely a metadata](03-labels-metadata.md) - Vícejazyčné labely, popisy, generování formulářů
- [Formátování podle locale](04-locale-formatting.md) - Formátování čísel, měn, datumů podle jazyka
- [Vícejazyčnost](05-translations.md) - Kompletní překlad entity do více jazyků
