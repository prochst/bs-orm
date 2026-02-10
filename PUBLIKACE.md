# Návod pro publikaci BS ORM balíčku

## 1. Příprava před publikací

### Aktualizujte composer.json
Upravte email a další informace v `/var/www/html/bs-orm/composer.json`:
```json
"authors": [
    {
        "name": "prochst",
        "email": "vase-skutecne-email@example.com"
    }
]
```

### Ověřte, že vše funguje
```bash
cd /var/www/html/bs-orm
composer validate
composer install
```

## 2. Vytvořte GitHub repositář

1. Přihlaste se na https://github.com
2. Klikněte na "New repository"
3. Název: `bs-orm`
4. Popis: "Simple, lightweight ORM for PHP with multi-language support"
5. **Nezaškrtávejte** "Initialize with README" (už máte)
6. Zvolte licenci: MIT
7. Klikněte "Create repository"

## 3. Nahrajte kód na GitHub

```bash
cd /var/www/html/bs-orm

# Nastavte remote repository (nahraďte USERNAME svým GitHub uživatelským jménem)
git remote add origin https://github.com/prochst/bs-orm.git

# Změňte branch na main (GitHub preferuje main místo master)
git branch -M main

# Nahrajte kód
git push -u origin main
```

## 4. Vytvořte release (tag)

```bash
cd /var/www/html/bs-orm

# Vytvořte tag pro verzi 1.0.0
git tag -a v1.0.0 -m "Initial release v1.0.0"

# Nahrajte tag
git push origin v1.0.0
```

## 5. Registrace na Packagist.org

1. Jděte na https://packagist.org
2. Přihlaste se pomocí GitHub účtu
3. Klikněte "Submit"
4. Vložte URL vašeho GitHub repositáře:
   `https://github.com/prochst/bs-orm`
5. Klikněte "Check"
6. Pokud je vše v pořádku, klikněte "Submit"

## 6. Automatická aktualizace (volitelné)

Nastavte GitHub webhook pro automatickou aktualizaci na Packagist:

1. Na Packagist najděte váš balíček
2. Klikněte na "Show API Token"
3. Zkopírujte Packagist URL a token
4. Jděte na GitHub → Settings → Webhooks → Add webhook
5. Vložte Packagist URL
6. Content type: `application/json`
7. Trigger: "Just the push event"
8. Klikněte "Add webhook"

## 7. Instalace vašeho balíčku

Po publikaci na Packagist může kdokoliv nainstalovat váš balíček:

```bash
composer require prochst/bs-orm
```

## 8. Použití v projektech

### V novém projektu
```bash
mkdir muj-projekt
cd muj-projekt
composer init
composer require prochst/bs-orm
```

### Ve vašem stávajícím Nette projektu

V `composer.json` přidejte:
```json
{
    "require": {
        "prochst/bs-orm": "^1.0"
    }
}
```

Poté:
```bash
composer update
```

Upravte kód v `app/Presentation/Home/HomePresenter.php`:
```php
<?php

namespace App\Presentation\Home;

use Nette;
use prochst\bsOrm\Dbal;           // ← ZMĚNA
use prochst\bsOrm\Repository;     // ← ZMĚNA
use prochst\bsOrm\Types\LocaleManager;  // ← ZMĚNA
use App\Model\User;
use App\Model\Product;

// ... zbytek zůstává stejný
```

A v modelech (`app/Model/User.php`):
```php
<?php

namespace App\Model;

use prochst\bsOrm\Entity;          // ← ZMĚNA
use prochst\bsOrm\Table;           // ← ZMĚNA
use prochst\bsOrm\Column;          // ← ZMĚNA
use prochst\bsOrm\Types\IntegerType;  // ← ZMĚNA
// atd.
```

## 9. Vydání nových verzí

Když uděláte změny:

```bash
cd /var/www/html/bs-orm

# Proveďte změny v kódu
git add .
git commit -m "Fix: Oprava bugu XYZ"
git push

# Vytvořte nový tag
git tag -a v1.0.1 -m "Release v1.0.1 - Bug fixes"
git push origin v1.0.1
```

Packagist automaticky detekuje nový tag (pokud máte webhook).

## 10. Sémantické verzování

Dodržujte [Semantic Versioning](https://semver.org/):

- **1.0.0** → Hlavní verze (breaking changes)
- **1.1.0** → Minor verze (nové funkce, zpětně kompatibilní)
- **1.0.1** → Patch verze (opravy bugů)

## Struktura vašeho balíčku

```
bs-orm/
├── .git/
├── .gitattributes
├── .gitignore
├── LICENSE
├── README.md
├── composer.json
└── src/
    ├── Column.php
    ├── Dbal.php
    ├── Entity.php
    ├── ForeignKey.php
    ├── Index.php
    ├── Repository.php
    ├── RepositoryInterface.php
    ├── Table.php
    ├── TranslationHelper.php
    ├── Relations/
    │   ├── BelongsTo.php
    │   ├── BelongsToMany.php
    │   ├── HasMany.php
    │   ├── HasOne.php
    │   └── RelationInterface.php
    ├── Types/
    │   ├── BlobType.php
    │   ├── BooleanType.php
    │   ├── CurrencyType.php
    │   ├── DateTimeType.php
    │   ├── DecimalType.php
    │   ├── EnumType.php
    │   ├── IntegerType.php
    │   ├── JsonType.php
    │   ├── LocaleManager.php
    │   ├── StringType.php
    │   ├── TextType.php
    │   └── TypeInterface.php
    └── Migration/
        └── generate-migrations.php
```

## Užitečné odkazy

- Váš GitHub: https://github.com/prochst/bs-orm
- Packagist: https://packagist.org/packages/prochst/bs-orm
- Composer dokumentace: https://getcomposer.org/doc/
- Semantic Versioning: https://semver.org/
