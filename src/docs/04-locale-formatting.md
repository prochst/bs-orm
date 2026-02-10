# TypeInterface - Formátování podle locale

Každý datový typ v ORM implementuje `TypeInterface`, které poskytuje dvě klíčové metody:
- `format(mixed $value, ?string $locale = null): string` - formátuje hodnotu pro zobrazení podle locale
- `parse(string $value, ?string $locale = null): mixed` - parsuje textovou hodnotu zpět na nativní typ

Díky tomu je formátování jednotné a automatické - nemusíte psát vlastní formátovací metody v entitách.

## Nastavení locale

```php
use App\Core\Orm\Types\LocaleManager;

// Nastavení výchozího locale pro celou aplikaci
LocaleManager::setDefaultLocale('cs_CZ');

// Získání aktuálního locale
$currentLocale = LocaleManager::getDefaultLocale(); // 'cs_CZ'
```

## Formátování čísel (IntegerType)

```php
use App\Core\Orm\Types\IntegerType;

$intType = new IntegerType();
$value = 1234567;

// České formátování (mezery jako oddělovač tisíců)
echo $intType->format($value, 'cs_CZ');  // "1 234 567"

// Americké formátování (čárky jako oddělovač tisíců)
echo $intType->format($value, 'en_US');  // "1,234,567"

// Německé formátování (tečky jako oddělovač tisíců)
echo $intType->format($value, 'de_DE');  // "1.234.567"

// Parsing zpět na číslo
$parsed = $intType->parse('1 234 567', 'cs_CZ');  // 1234567
$parsed = $intType->parse('1,234,567', 'en_US');  // 1234567
$parsed = $intType->parse('1.234.567', 'de_DE');  // 1234567
```

## Formátování desetinných čísel (DecimalType)

```php
use App\Core\Orm\Types\DecimalType;

$decimalType = new DecimalType(precision: 10, scale: 2);
$value = 1234.56;

// České formátování (čárka jako desetinný oddělovač)
echo $decimalType->format($value, 'cs_CZ');  // "1 234,56"

// Americké formátování (tečka jako desetinný oddělovač)
echo $decimalType->format($value, 'en_US');  // "1,234.56"

// Německé formátování
echo $decimalType->format($value, 'de_DE');  // "1.234,56"

// Parsing zpět
$parsed = $decimalType->parse('1 234,56', 'cs_CZ');  // 1234.56
$parsed = $decimalType->parse('1,234.56', 'en_US');  // 1234.56
$parsed = $decimalType->parse('1.234,56', 'de_DE');  // 1234.56
```

## Formátování měn (CurrencyType)

```php
use App\Core\Orm\Types\CurrencyType;

$price = 1234.50;

// České koruny
$czkType = new CurrencyType('CZK');
echo $czkType->format($price, 'cs_CZ');  // "1 234,50 Kč"
echo $czkType->format($price, 'en_US');  // "CZK 1,234.50"

// Eura
$eurType = new CurrencyType('EUR');
echo $eurType->format($price, 'cs_CZ');  // "1 234,50 €"
echo $eurType->format($price, 'en_US');  // "€1,234.50"
echo $eurType->format($price, 'de_DE');  // "1.234,50 €"

// Dolary
$usdType = new CurrencyType('USD');
echo $usdType->format($price, 'cs_CZ');  // "1 234,50 $"
echo $usdType->format($price, 'en_US');  // "$1,234.50"

// Parsing (vrací číslo bez měny)
$parsed = $czkType->parse('1 234,50 Kč', 'cs_CZ');  // 1234.50
$parsed = $eurType->parse('€1,234.50', 'en_US');    // 1234.50
```

## Formátování datumů (DateTimeType)

```php
use App\Core\Orm\Types\DateTimeType;

$dateTimeType = new DateTimeType();
$date = new DateTimeImmutable('2025-02-05 14:30:00');

// České formátování
echo $dateTimeType->format($date, 'cs_CZ');  
// "5. 2. 2025 14:30"

// Americké formátování
echo $dateTimeType->format($date, 'en_US');  
// "2/5/2025 2:30 PM"

// Britské formátování
echo $dateTimeType->format($date, 'en_GB');  
// "05/02/2025 14:30"

// Německé formátování
echo $dateTimeType->format($date, 'de_DE');  
// "05.02.2025 14:30"

// Pouze datum
echo $dateTimeType->formatDate($date, 'cs_CZ');  // "5. 2. 2025"
echo $dateTimeType->formatDate($date, 'en_US');  // "2/5/2025"
echo $dateTimeType->formatDate($date, 'de_DE');  // "05.02.2025"

// Pouze čas
echo $dateTimeType->formatTime($date, 'cs_CZ');  // "14:30"
echo $dateTimeType->formatTime($date, 'en_US');  // "2:30 PM"
echo $dateTimeType->formatTime($date, 'de_DE');  // "14:30"

// Parsing
$parsed = $dateTimeType->parse('5.2.2025 14:30', 'cs_CZ');
$parsed = $dateTimeType->parse('2/5/2025 2:30 PM', 'en_US');
```

## Formátování enum hodnot (EnumType)

```php
use App\Core\Orm\Types\EnumType;

$statusType = new EnumType(
    values: ['active', 'inactive', 'pending'],
    translations: [
        'cs_CZ' => [
            'active' => 'Aktivní',
            'inactive' => 'Neaktivní',
            'pending' => 'Čekající',
        ],
        'en_US' => [
            'active' => 'Active',
            'inactive' => 'Inactive',
            'pending' => 'Pending',
        ],
        'de_DE' => [
            'active' => 'Aktiv',
            'inactive' => 'Inaktiv',
            'pending' => 'Ausstehend',
        ],
    ]
);

$status = 'active';

echo $statusType->format($status, 'cs_CZ');  // "Aktivní"
echo $statusType->format($status, 'en_US');  // "Active"
echo $statusType->format($status, 'de_DE');  // "Aktiv"
```

## TypeInterface v entitách

Každý sloupec má definovaný `type`, který implementuje `TypeInterface`. To umožňuje automatické formátování hodnot:

```php
<?php

namespace App\Model;

use App\Core\Orm\Entity;
use App\Core\Orm\Table;
use App\Core\Orm\Column;
use App\Core\Orm\Types\IntegerType;
use App\Core\Orm\Types\StringType;
use App\Core\Orm\Types\CurrencyType;
use App\Core\Orm\Types\DateTimeType;
use App\Core\Orm\Types\EnumType;

#[Table(name: 'products')]
class Product extends Entity
{
    #[Column(
        type: new IntegerType(unsigned: true),
        primaryKey: true,
        autoIncrement: true
    )]
    public private(set) ?int $id = null;
    
    #[Column(type: new StringType(maxLength: 255))]
    public private(set) string $name;
    
    #[Column(type: new CurrencyType('CZK'))]
    public private(set) float $price;
    
    #[Column(type: new IntegerType(unsigned: true))]
    public private(set) int $stock;
    
    #[Column(
        type: new EnumType(
            values: ['available', 'out_of_stock', 'discontinued'],
            translations: [
                'cs_CZ' => [
                    'available' => 'Dostupné',
                    'out_of_stock' => 'Vyprodáno',
                    'discontinued' => 'Ukončeno',
                ],
                'en_US' => [
                    'available' => 'Available',
                    'out_of_stock' => 'Out of Stock',
                    'discontinued' => 'Discontinued',
                ]
            ]
        )
    )]
    public private(set) string $status;
    
    #[Column(type: new DateTimeType(immutable: true))]
    public private(set) ?\DateTimeImmutable $createdAt = null;
    
    // Settery...
}
```

## Formátování v šablonách

```php
<?php

use App\Core\Orm\Dbal;
use App\Core\Orm\Repository;
use App\Core\Orm\Types\LocaleManager;
use App\Model\Product;

// Nastavení locale podle jazyka uživatele
$userLanguage = $_GET['lang'] ?? 'cs_CZ';
LocaleManager::setDefaultLocale($userLanguage);

$dbal = new Dbal($pdo);
$productRepo = new Repository($dbal, Product::class);
$product = $productRepo->find(1);

// Získání sloupců pro formátování
$columns = Product::getColumns();

?>

<!-- České zobrazení -->
<div class="product" lang="cs">
    <h1><?= htmlspecialchars($product->name) ?></h1>
    <p><strong>Cena:</strong> <?= $columns['price']->type->format($product->price, 'cs_CZ') ?></p>
    <p><strong>Skladem:</strong> <?= $columns['stock']->type->format($product->stock, 'cs_CZ') ?> ks</p>
    <p><strong>Stav:</strong> <?= $columns['status']->type->format($product->status, 'cs_CZ') ?></p>
    <p><strong>Vytvořeno:</strong> <?= $columns['createdAt']->type->format($product->createdAt, 'cs_CZ') ?></p>
</div>

<!-- Anglické zobrazení -->
<div class="product" lang="en">
    <h1><?= htmlspecialchars($product->name) ?></h1>
    <p><strong>Price:</strong> <?= $columns['price']->type->format($product->price, 'en_US') ?></p>
    <p><strong>In stock:</strong> <?= $columns['stock']->type->format($product->stock, 'en_US') ?> pcs</p>
    <p><strong>Status:</strong> <?= $columns['status']->type->format($product->status, 'en_US') ?></p>
    <p><strong>Created:</strong> <?= $columns['createdAt']->type->format($product->createdAt, 'en_US') ?></p>
</div>

<!-- Německé zobrazení -->
<div class="product" lang="de">
    <h1><?= htmlspecialchars($product->name) ?></h1>
    <p><strong>Preis:</strong> <?= $columns['price']->type->format($product->price, 'de_DE') ?></p>
    <p><strong>Auf Lager:</strong> <?= $columns['stock']->type->format($product->stock, 'de_DE') ?> Stk</p>
    <p><strong>Status:</strong> <?= $columns['status']->type->format($product->status, 'de_DE') ?></p>
    <p><strong>Erstellt:</strong> <?= $columns['createdAt']->type->format($product->createdAt, 'de_DE') ?></p>
</div>
```

## Generická pomocná funkce

Pro univerzální formátování jakéhokoli pole entity:

```php
function formatEntityField(Entity $entity, string $fieldName, ?string $locale = null): string
{
    $columns = $entity::getColumns();
    
    if (!isset($columns[$fieldName])) {
        return '';
    }
    
    $column = $columns[$fieldName];
    $reflection = new \ReflectionClass($entity);
    $property = $reflection->getProperty($fieldName);
    
    if (!$property->isInitialized($entity)) {
        return '';
    }
    
    $value = $property->getValue($entity);
    
    return $column->type->format($value, $locale);
}

// Použití
$product = $productRepo->find(1);

echo formatEntityField($product, 'price', 'cs_CZ');      // "1 234,50 Kč"
echo formatEntityField($product, 'price', 'en_US');      // "CZK 1,234.50"
echo formatEntityField($product, 'stock', 'cs_CZ');      // "150"
echo formatEntityField($product, 'created_at', 'de_DE'); // "05.02.2025 14:30"
```

## Parsování hodnot z formulářů

TypeInterface poskytuje také metodu `parse()` pro zpětný převod textových hodnot:

```php
// Získání dat z formuláře
$priceInput = $_POST['price']; // např. "1 234,50 Kč"
$dateInput = $_POST['created_at']; // např. "5. 2. 2025 14:30"
$stockInput = $_POST['stock']; // např. "150"

$columns = Product::getColumns();

// Parsování podle locale
$price = $columns['price']->type->parse($priceInput, 'cs_CZ');  // 1234.50
$date = $columns['createdAt']->type->parse($dateInput, 'cs_CZ'); // DateTimeImmutable
$stock = $columns['stock']->type->parse($stockInput, 'cs_CZ');   // 150

// Nastavení do entity
$product = new Product();
$product->setPrice($price);
$product->setCreatedAt($date);
$product->setStock($stock);

$productRepo->save($product);
```

### Příklad zpracování formuláře

```php
function processProductForm(array $formData, string $locale): Product
{
    $columns = Product::getColumns();
    $product = new Product();
    
    // Automatické parsování podle typu sloupce
    foreach ($formData as $fieldName => $value) {
        if (!isset($columns[$fieldName])) {
            continue;
        }
        
        // Parse hodnoty podle TypeInterface
        $parsed = $columns[$fieldName]->type->parse($value, $locale);
        
        // Volání setteru
        $setter = 'set' . ucfirst($fieldName);
        if (method_exists($product, $setter)) {
            $product->$setter($parsed);
        }
    }
    
    return $product;
}

// Použití
$productData = [
    'name' => 'Nový produkt',
    'price' => '1 234,50 Kč',    // Bude parsováno na 1234.50
    'stock' => '150',             // Bude parsováno na 150
    'status' => 'available',
    'createdAt' => '5. 2. 2025 14:30'  // Bude parsováno na DateTimeImmutable
];

$product = processProductForm($productData, 'cs_CZ');
$productRepo->save($product);
```

## Best Practices

### 1. Definujte výchozí locale při startu aplikace

```php
// V bootstrap.php nebo index.php
use App\Core\Orm\Types\LocaleManager;

LocaleManager::setDefaultLocale('cs_CZ');
```

### 2. Používejte TypeInterface místo vlastních formátovacích metod

```php
// ✓ Dobře - použití TypeInterface
$columns = Product::getColumns();
echo $columns['price']->type->format($product->price, 'cs_CZ');

// ✓ Také dobře - univerzální helper
echo formatEntityField($product, 'price', 'cs_CZ');

// ✗ Špatně - zbytečné wrapper metody v entitě
class Product extends Entity {
    public function getFormattedPrice(): string {
        return $this->price . ' Kč';  // NEPOTŘEBNÉ!
    }
}
```

**Proč?** TypeInterface již poskytuje `format()` a `parse()` metody, které zvládají různé locale. Vlastní metody by byly duplicitní a méně flexibilní.

### 3. Optimalizujte getColumns() při hromadném zobrazování

```php
// ✗ Špatně - getColumns() se volá v každé iteraci
foreach ($products as $product) {
    $columns = Product::getColumns(); // Pomalé!
    echo $columns['price']->type->format($product->price, 'cs_CZ');
}

// ✓ Dobře - getColumns() se volá pouze jednou
$columns = Product::getColumns();
foreach ($products as $product) {
    echo $columns['price']->type->format($product->price, 'cs_CZ');
    echo $columns['stock']->type->format($product->stock, 'cs_CZ');
    echo $columns['status']->type->format($product->status, 'cs_CZ');
}
```

### 4. Používejte EnumType pro přeložitelné hodnoty

```php
// ✓ Dobře - hodnoty se přeloží podle locale
#[Column(type: new EnumType(
    values: ['active', 'inactive'],
    translations: [
        'cs_CZ' => ['active' => 'Aktivní', 'inactive' => 'Neaktivní'],
        'en_US' => ['active' => 'Active', 'inactive' => 'Inactive']
    ]
))]
public private(set) string $status;

// ✗ Špatně - pevně dané české texty v databázi
#[Column(type: new StringType(20))]
public private(set) string $status = 'Aktivní'; // Nepřeložitelné!
```

### 5. Cachujte TypeInterface instance v long-running aplikacích

Pro aplikace jako API servery nebo workery:

```php
class TypeCache
{
    private static array $cache = [];
    
    public static function getType(string $key, callable $factory): TypeInterface
    {
        if (!isset(self::$cache[$key])) {
            self::$cache[$key] = $factory();
        }
        return self::$cache[$key];
    }
}

// Použití
$currencyType = TypeCache::getType('currency_czk', fn() => new CurrencyType('CZK'));
```

### 6. Validujte vstupy před parsováním

```php
// ✓ Dobře - validace před parsováním
try {
    $price = $columns['price']->type->parse($input, 'cs_CZ');
    $product->setPrice($price);
} catch (\InvalidArgumentException $e) {
    // Zpracování chyby - neplatný formát
    echo "Neplatný formát ceny: " . $e->getMessage();
}

// ✗ Špatně - žádná validace
$price = $columns['price']->type->parse($input, 'cs_CZ');
$product->setPrice($price); // Může způsobit chybu
```

## Podporované locale

ORM používá standardní locale kódy:

- `cs_CZ` - Čeština (Česká republika)
- `sk_SK` - Slovenština (Slovensko)
- `en_US` - Angličtina (USA)
- `en_GB` - Angličtina (Velká Británie)
- `de_DE` - Němčina (Německo)
- `fr_FR` - Francouzština (Francie)
- `pl_PL` - Polština (Polsko)
- a další...

## Další kroky

- [Generování formulářů a gridů](03-labels-metadata.md) - UI generování s TypeInterface
- [Vícejazyčnost](05-translations.md) - Kompletní překlady labelů a metadat
- [Základní použití](01-basic-usage.md)
- [Práce s relacemi](02-relations.md)
