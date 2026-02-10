# Vícejazyčnost - Překlady labelů a metadat

ORM poskytuje kompletní podporu vícejazyčnosti, umožňující překládat labely tabulek, sloupců, placeholdery, popisy a nápovědy do libovolného počtu jazyků.

## Nastavení výchozího jazyka

```php
use App\Core\Orm\Types\LocaleManager;

// Nastavení výchozího locale
LocaleManager::setDefaultLocale('cs_CZ');

// Získání aktuálního locale
$locale = LocaleManager::getDefaultLocale(); // 'cs_CZ'
```

## Definice vícejazyčné entity

### Překlad labelu tabulky

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

#[Table(
    name: 'products',
    label: [
        'cs_CZ' => 'Produkty',
        'en_US' => 'Products',
        'de_DE' => 'Produkte',
        'fr_FR' => 'Produits',
    ],
    description: [
        'cs_CZ' => 'Tabulka produktů v eshopu',
        'en_US' => 'Product table in e-shop',
        'de_DE' => 'Produkttabelle im E-Shop',
        'fr_FR' => 'Table des produits dans la boutique en ligne',
    ]
)]
class Product extends Entity
{
    // Sloupce...
}
```

### Překlad labelů sloupců

```php
#[Table(name: 'products')]
class Product extends Entity
{
    #[Column(
        type: new IntegerType(),
        primaryKey: true,
        label: [
            'cs_CZ' => 'ID',
            'en_US' => 'ID',
            'de_DE' => 'ID',
        ]
    )]
    public private(set) ?int $id = null;
    
    #[Column(
        type: new StringType(255),
        label: [
            'cs_CZ' => 'Název produktu',
            'en_US' => 'Product Name',
            'de_DE' => 'Produktname',
            'fr_FR' => 'Nom du produit',
        ],
        placeholder: [
            'cs_CZ' => 'Zadejte název produktu',
            'en_US' => 'Enter product name',
            'de_DE' => 'Produktname eingeben',
            'fr_FR' => 'Entrez le nom du produit',
        ],
        description: [
            'cs_CZ' => 'Obchodní název produktu',
            'en_US' => 'Commercial product name',
            'de_DE' => 'Handelsname des Produkts',
            'fr_FR' => 'Nom commercial du produit',
        ]
    )]
    public private(set) string $name;
    
    #[Column(
        type: new CurrencyType('CZK'),
        label: [
            'cs_CZ' => 'Cena',
            'en_US' => 'Price',
            'de_DE' => 'Preis',
            'fr_FR' => 'Prix',
        ],
        description: [
            'cs_CZ' => 'Prodejní cena včetně DPH',
            'en_US' => 'Sales price including VAT',
            'de_DE' => 'Verkaufspreis inkl. MwSt.',
            'fr_FR' => 'Prix de vente TTC',
        ],
        help: [
            'cs_CZ' => 'Zadejte cenu v korunách',
            'en_US' => 'Enter price in crowns',
            'de_DE' => 'Preis in Kronen eingeben',
            'fr_FR' => 'Entrez le prix en couronnes',
        ]
    )]
    public private(set) float $price;
    
    #[Column(
        type: new IntegerType(),
        label: [
            'cs_CZ' => 'Skladové množství',
            'en_US' => 'Stock Quantity',
            'de_DE' => 'Lagerbestand',
            'fr_FR' => 'Quantité en stock',
        ]
    )]
    public private(set) int $stock;
    
    #[Column(
        type: new DateTimeType(),
        label: [
            'cs_CZ' => 'Datum vytvoření',
            'en_US' => 'Creation Date',
            'de_DE' => 'Erstellungsdatum',
            'fr_FR' => 'Date de création',
        ]
    )]
    public private(set) \DateTimeImmutable $createdAt;
    
    // Settery pro tracking změn
    public function setName(string $name): void {
        $this->name = $name;
        $this->markFieldAsModified('name');
    }
    
    public function setPrice(float $price): void {
        $this->price = $price;
        $this->markFieldAsModified('price');
    }
    
    public function setStock(int $stock): void {
        $this->stock = $stock;
        $this->markFieldAsModified('stock');
    }
    
    public function setCreatedAt(\DateTimeImmutable $createdAt): void {
        $this->createdAt = $createdAt;
        $this->markFieldAsModified('createdAt');
    }
}
```

## Získávání přeložených labelů

### Label tabulky

```php
use App\Model\Product;

// Český label
echo Product::getTableLabel('cs_CZ');  // "Produkty"

// Anglický label
echo Product::getTableLabel('en_US');  // "Products"

// Německý label
echo Product::getTableLabel('de_DE');  // "Produkte"

// Francouzský label
echo Product::getTableLabel('fr_FR');  // "Produits"

// Použití výchozího locale
LocaleManager::setDefaultLocale('cs_CZ');
echo Product::getTableLabel();  // "Produkty"
```

### Popis tabulky

```php
echo Product::getTableDescription('cs_CZ');  
// "Tabulka produktů v eshopu"

echo Product::getTableDescription('en_US');  
// "Product table in e-shop"

echo Product::getTableDescription('de_DE');  
// "Produkttabelle im E-Shop"
```

### Labely sloupců

```php
// Všechny labely najednou v češtině
$labelsCs = Product::getColumnLabels('cs_CZ');
/*
Array (
    [id] => ID
    [name] => Název produktu
    [price] => Cena
    [stock] => Skladové množství
    [created_at] => Datum vytvoření
)
*/

// Všechny labely najednou v angličtině
$labelsEn = Product::getColumnLabels('en_US');
/*
Array (
    [id] => ID
    [name] => Product Name
    [price] => Price
    [stock] => Stock Quantity
    [created_at] => Creation Date
)
*/

// Konkrétní sloupec
echo Product::getColumnLabel('name', 'cs_CZ');  // "Název produktu"
echo Product::getColumnLabel('name', 'en_US');  // "Product Name"
echo Product::getColumnLabel('name', 'de_DE');  // "Produktname"
```

## Generování vícejazyčných formulářů

```php
function generateMultilingualForm(
    string $entityClass, 
    string $locale,
    ?Entity $entity = null
): string {
    $tableLabel = $entityClass::getTableLabel($locale);
    $columns = $entityClass::getColumns();
    
    $html = sprintf('<form><h2>%s</h2>', htmlspecialchars($tableLabel));
    
    foreach ($columns as $propertyName => $column) {
        if ($column->primaryKey || $column->autoIncrement) {
            continue;
        }
        
        $label = $column->getLabel($propertyName, $locale);
        $placeholder = $column->getPlaceholder($locale);
        $help = $column->getHelp($locale);
        $description = $column->getDescription($locale);
        $required = !$column->nullable ? 'required' : '';
        
        // Získání hodnoty z entity
        $value = '';
        if ($entity !== null) {
            $reflection = new \ReflectionClass($entity);
            if ($reflection->hasProperty($propertyName)) {
                $prop = $reflection->getProperty($propertyName);
                if ($prop->isInitialized($entity)) {
                    $val = $prop->getValue($entity);
                    $value = $val instanceof \DateTimeInterface 
                        ? $val->format('Y-m-d H:i:s') 
                        : (string)$val;
                }
            }
        }
        
        $html .= '<div class="form-group">';
        $html .= sprintf(
            '<label for="%s">%s%s</label>',
            $propertyName,
            htmlspecialchars($label),
            $required ? ' *' : ''
        );
        
        if ($description) {
            $html .= sprintf(
                '<div class="description">%s</div>',
                htmlspecialchars($description)
            );
        }
        
        $html .= sprintf(
            '<input type="text" id="%s" name="%s" value="%s" placeholder="%s" %s title="%s">',
            $propertyName,
            $propertyName,
            htmlspecialchars($value),
            htmlspecialchars($placeholder ?? ''),
            $required,
            htmlspecialchars($description ?? '')
        );
        
        if ($help) {
            $html .= sprintf(
                '<small class="help-text">%s</small>',
                htmlspecialchars($help)
            );
        }
        
        $html .= '</div>';
    }
    
    // Tlačítko submit také přeložené
    $submitLabels = [
        'cs_CZ' => 'Uložit',
        'en_US' => 'Save',
        'de_DE' => 'Speichern',
        'fr_FR' => 'Enregistrer',
    ];
    $submitLabel = $submitLabels[$locale] ?? 'Submit';
    
    $html .= sprintf('<button type="submit">%s</button>', htmlspecialchars($submitLabel));
    $html .= '</form>';
    
    return $html;
}

// Použití - český formulář
echo generateMultilingualForm(Product::class, 'cs_CZ');

// Anglický formulář
echo generateMultilingualForm(Product::class, 'en_US');

// Německý formulář
echo generateMultilingualForm(Product::class, 'de_DE');

// Editace existující entity v češtině
$product = $productRepo->find(1);
echo generateMultilingualForm(Product::class, 'cs_CZ', $product);
```

## Generování vícejazyčných gridů

```php
function generateMultilingualGrid(
    string $entityClass, 
    array $entities, 
    string $locale
): string {
    $tableLabel = $entityClass::getTableLabel($locale);
    $columnLabels = $entityClass::getColumnLabels($locale);
    $columns = $entityClass::getColumns();
    
    // Akční labely podle jazyka
    $actionLabels = [
        'cs_CZ' => ['edit' => 'Upravit', 'delete' => 'Smazat', 'actions' => 'Akce'],
        'en_US' => ['edit' => 'Edit', 'delete' => 'Delete', 'actions' => 'Actions'],
        'de_DE' => ['edit' => 'Bearbeiten', 'delete' => 'Löschen', 'actions' => 'Aktionen'],
        'fr_FR' => ['edit' => 'Modifier', 'delete' => 'Supprimer', 'actions' => 'Actions'],
    ];
    $actions = $actionLabels[$locale] ?? $actionLabels['en_US'];
    
    $html = sprintf('<h2>%s</h2>', htmlspecialchars($tableLabel));
    $html .= '<table class="data-grid">';
    
    // Hlavička
    $html .= '<thead><tr>';
    foreach ($columnLabels as $propertyName => $label) {
        $column = $columns[$propertyName];
        if ($column->primaryKey) {
            continue;
        }
        
        $description = $column->getDescription($locale);
        $html .= sprintf(
            '<th title="%s">%s</th>',
            htmlspecialchars($description ?? ''),
            htmlspecialchars($label)
        );
    }
    $html .= sprintf('<th>%s</th>', htmlspecialchars($actions['actions']));
    $html .= '</tr></thead>';
    
    // Data s formátováním podle locale
    $html .= '<tbody>';
    foreach ($entities as $entity) {
        $html .= '<tr>';
        
        foreach ($columns as $propertyName => $column) {
            if ($column->primaryKey) {
                continue;
            }
            
            $formatted = formatEntityField($entity, $propertyName, $locale);
            $html .= sprintf('<td>%s</td>', htmlspecialchars($formatted));
        }
        
        // Akce
        $id = $entity->id;  // Přímý přístup k property
        $html .= sprintf(
            '<td><a href="edit.php?id=%s">%s</a> | <a href="delete.php?id=%s">%s</a></td>',
            $id,
            htmlspecialchars($actions['edit']),
            $id,
            htmlspecialchars($actions['delete'])
        );
        
        $html .= '</tr>';
    }
    $html .= '</tbody>';
    
    $html .= '</table>';
    return $html;
}

// Použití
$products = $productRepo->findAll();

echo generateMultilingualGrid(Product::class, $products, 'cs_CZ');
echo generateMultilingualGrid(Product::class, $products, 'en_US');
echo generateMultilingualGrid(Product::class, $products, 'de_DE');
```

## Přepínání jazyků v aplikaci

```php
// V hlavním layoutu aplikace
$availableLocales = [
    'cs_CZ' => 'Čeština',
    'en_US' => 'English',
    'de_DE' => 'Deutsch',
    'fr_FR' => 'Français',
];

// Získání locale z URL/session/cookie
$currentLocale = $_GET['lang'] ?? $_SESSION['locale'] ?? 'cs_CZ';
LocaleManager::setDefaultLocale($currentLocale);

// Výběr jazyka
echo '<div class="language-switcher">';
foreach ($availableLocales as $locale => $name) {
    $active = $locale === $currentLocale ? 'active' : '';
    echo sprintf(
        '<a href="?lang=%s" class="%s">%s</a> ',
        $locale,
        $active,
        $name
    );
}
echo '</div>';

// Zobrazení produktů v aktuálním jazyce
$products = $productRepo->findAll();
echo generateMultilingualGrid(Product::class, $products, $currentLocale);
```

## Praktický příklad - Vícejazyčný e-shop

```php
<?php

use App\Core\Orm\Dbal;
use App\Core\Orm\Repository;
use App\Core\Orm\Types\LocaleManager;
use App\Model\Product;

// Detekce jazyka
$locale = $_GET['lang'] ?? $_COOKIE['locale'] ?? 'cs_CZ';
LocaleManager::setDefaultLocale($locale);

// Uložení do cookie
setcookie('locale', $locale, time() + 86400 * 365, '/');

$dbal = new Dbal('mysql:host=localhost;dbname=shop', 'root', 'password');
$productRepo = new Repository($dbal, Product::class);
$product = $productRepo->find(1);

// Texty podle jazyka
$texts = [
    'cs_CZ' => [
        'product' => 'Produkt',
        'price' => 'Cena',
        'stock' => 'Skladem',
        'add_to_cart' => 'Přidat do košíku',
    ],
    'en_US' => [
        'product' => 'Product',
        'price' => 'Price',
        'stock' => 'In Stock',
        'add_to_cart' => 'Add to Cart',
    ],
    'de_DE' => [
        'product' => 'Produkt',
        'price' => 'Preis',
        'stock' => 'Auf Lager',
        'add_to_cart' => 'In den Warenkorb',
    ],
];
$t = $texts[$locale] ?? $texts['en_US'];

?>
<!DOCTYPE html>
<html lang="<?= substr($locale, 0, 2) ?>">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($product->getName()) ?></title>
</head>
<body>
    <!-- Přepínač jazyků -->
    <nav>
        <a href="?lang=cs_CZ">🇨🇿 Čeština</a>
        <a href="?lang=en_US">🇬🇧 English</a>
        <a href="?lang=de_DE">🇩🇪 Deutsch</a>
    </nav>
    
    <!-- Detail produktu -->
    <div class="product-detail">
        <h1><?= htmlspecialchars($product->name) ?></h1>
        
        <?php $columns = Product::getColumns(); ?>
        
        <dl>
            <dt><?= Product::getColumnLabel('price', $locale) ?>:</dt>
            <dd><?= $columns['price']->type->format($product->price, $locale) ?></dd>
            
            <dt><?= Product::getColumnLabel('stock', $locale) ?>:</dt>
            <dd><?= $columns['stock']->type->format($product->stock, $locale) ?></dd>
            
            <dt><?= Product::getColumnLabel('createdAt', $locale) ?>:</dt>
            <dd><?= $columns['createdAt']->type->format($product->createdAt, $locale) ?></dd>
        </dl>
        
        <button><?= $t['add_to_cart'] ?></button>
    </div>
</body>
</html>
```

## Best Practices

### 1. Definujte překlady pro všechny podporované jazyky

```php
// ✓ Dobře - všechny jazyky
#[Column(
    label: [
        'cs_CZ' => 'Název',
        'en_US' => 'Name',
        'de_DE' => 'Name',
    ]
)]
public private(set) string $name;

// ✗ Špatně - chybí překlady
#[Column(label: ['cs_CZ' => 'Název'])]
public private(set) string $name;
```

### 2. Používejte fallback

Pokud překlad chybí, ORM použije výchozí hodnotu nebo první dostupný překlad:

```php
// Pokud 'fr_FR' není definováno, použije se 'cs_CZ' nebo první dostupný
$label = Product::getColumnLabel('name', 'fr_FR');
```

### 3. Centralizujte texty UI

```php
// Vytvořte třídu pro UI texty
class UiTexts
{
    public static function get(string $key, string $locale): string
    {
        $texts = [
            'save' => [
                'cs_CZ' => 'Uložit',
                'en_US' => 'Save',
                'de_DE' => 'Speichern',
            ],
            'cancel' => [
                'cs_CZ' => 'Zrušit',
                'en_US' => 'Cancel',
                'de_DE' => 'Abbrechen',
            ],
            // ...
        ];
        
        return $texts[$key][$locale] ?? $texts[$key]['en_US'] ?? $key;
    }
}
```

### 4. Cachujte překlady

Pro velké aplikace zvažte cachování přeložených textů:

```php
// V bootstrap.php
$cache = new Cache();
$translationKey = "entity_labels_{$locale}";

if (!$cache->has($translationKey)) {
    $labels = Product::getColumnLabels($locale);
    $cache->set($translationKey, $labels, 3600);
}
```

## Další kroky

- [Zpět na formátování podle locale](04-locale-formatting.md)
- [Labely a metadata](03-labels-metadata.md)
- [Práce s relacemi](02-relations.md)
- [Základní použití](01-basic-usage.md)
