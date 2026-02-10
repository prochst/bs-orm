# Labely a metadata

ORM umožňuje definovat uživatelsky přívětivé názvy (labely) a metadata pro tabulky a sloupce. Tato metadata se pak dají využít pro automatické generování formulářů, gridů a administračních rozhraní.

## Definice labelů v entitě

```php
<?php

namespace App\Model;

use App\Core\Orm\Entity;
use App\Core\Orm\Table;
use App\Core\Orm\Column;
use App\Core\Orm\Types\IntegerType;
use App\Core\Orm\Types\StringType;
use App\Core\Orm\Types\BooleanType;
use App\Core\Orm\Types\DateTimeType;

#[Table(
    name: 'users',
    label: 'Uživatelé',
    description: 'Tabulka uživatelů systému'
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
        label: 'E-mailová adresa',
        description: 'Unikátní e-mail uživatele pro přihlášení',
        placeholder: 'uzivatel@example.com',
        help: 'Zadejte platnou e-mailovou adresu'
    )]
    public private(set) string $email;
    
    #[Column(
        type: new StringType(255),
        label: 'Heslo',
        description: 'Hashované heslo',
        help: 'Heslo musí mít alespoň 8 znaků'
    )]
    public private(set) string $password;
    
    #[Column(
        type: new StringType(100),
        label: 'Celé jméno',
        placeholder: 'Jan Novák'
    )]
    public private(set) string $name;
    
    #[Column(
        type: new BooleanType(),
        label: 'Aktivní',
        description: 'Zda je účet aktivní',
        default: true
    )]
    public private(set) bool $active = true;
    
    #[Column(
        type: new DateTimeType(),
        label: 'Datum vytvoření'
    )]
    public private(set) \DateTimeImmutable $createdAt;
    
    // Pouze settery pro tracking změn
    public function setEmail(string $email): void {
        $this->email = $email;
        $this->markFieldAsModified('email');
    }
    
    public function setPassword(string $password): void {
        $this->password = $password;
        $this->markFieldAsModified('password');
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
```

## Získávání labelů

### Label tabulky

```php
use App\Model\User;

// Získání labelu tabulky
$tableLabel = User::getTableLabel();
echo $tableLabel; // "Uživatelé"

// Získání popisu tabulky
$tableDescription = User::getTableDescription();
echo $tableDescription; // "Tabulka uživatelů systému"
```

### Labely sloupců

```php
// Všechny labely najednou
$columnLabels = User::getColumnLabels();
print_r($columnLabels);
/*
Array
(
    [id] => ID
    [email] => E-mailová adresa
    [password] => Heslo
    [name] => Celé jméno
    [active] => Aktivní
    [created_at] => Datum vytvoření
)
*/

// Label konkrétního sloupce
$emailLabel = User::getColumnLabel('email');
echo $emailLabel; // "E-mailová adresa"

$nameLabel = User::getColumnLabel('name');
echo $nameLabel; // "Celé jméno"
```

## Generování formulářů

### Jednoduchý generátor

```php
function generateFormField(string $entityClass, string $propertyName, mixed $value = null): string
{
    $columns = $entityClass::getColumns();
    $column = $columns[$propertyName] ?? null;
    
    if (!$column) {
        return '';
    }
    
    $label = $column->getLabel($propertyName);
    $placeholder = $column->placeholder ?? '';
    $description = $column->description ?? '';
    $help = $column->help ?? '';
    $required = !$column->nullable ? 'required' : '';
    
    $html = '<div class="form-group">';
    $html .= sprintf('<label for="%s">%s%s</label>',
        $propertyName,
        htmlspecialchars($label),
        $required ? ' <span class="required">*</span>' : ''
    );
    
    if ($description) {
        $html .= sprintf('<div class="description">%s</div>', htmlspecialchars($description));
    }
    
    $html .= sprintf(
        '<input type="text" id="%s" name="%s" value="%s" placeholder="%s" %s title="%s">',
        $propertyName,
        $propertyName,
        htmlspecialchars($value ?? ''),
        htmlspecialchars($placeholder),
        $required,
        htmlspecialchars($description)
    );
    
    if ($help) {
        $html .= sprintf('<small class="help-text">%s</small>', htmlspecialchars($help));
    }
    
    $html .= '</div>';
    
    return $html;
}

// Použití
echo generateFormField(User::class, 'email', 'john@example.com');
echo generateFormField(User::class, 'name', 'John Doe');
echo generateFormField(User::class, 'active', true);
```

### Pokročilý generátor s různými typy polí

```php
function generateAdvancedFormField(string $entityClass, string $propertyName, mixed $value = null): string
{
    $columns = $entityClass::getColumns();
    $column = $columns[$propertyName] ?? null;
    
    if (!$column || $column->primaryKey) {
        return ''; // Přeskočíme primární klíče
    }
    
    $label = $column->getLabel($propertyName);
    $type = $column->type;
    $required = !$column->nullable ? 'required' : '';
    
    $html = '<div class="form-group">';
    $html .= sprintf('<label for="%s">%s%s</label>',
        $propertyName,
        htmlspecialchars($label),
        $required ? ' *' : ''
    );
    
    // Podle typu sloupce vygenerujeme různá pole
    if ($type instanceof \App\Core\Orm\Types\BooleanType) {
        // Checkbox
        $checked = $value ? 'checked' : '';
        $html .= sprintf(
            '<input type="checkbox" id="%s" name="%s" %s %s>',
            $propertyName, $propertyName, $checked, $required
        );
        
    } elseif ($type instanceof \App\Core\Orm\Types\TextType) {
        // Textarea
        $html .= sprintf(
            '<textarea id="%s" name="%s" %s>%s</textarea>',
            $propertyName, $propertyName, $required, htmlspecialchars($value ?? '')
        );
        
    } elseif ($type instanceof \App\Core\Orm\Types\DateTimeType) {
        // Datetime input
        $formatted = $value instanceof \DateTimeInterface 
            ? $value->format('Y-m-d\TH:i') 
            : '';
        $html .= sprintf(
            '<input type="datetime-local" id="%s" name="%s" value="%s" %s>',
            $propertyName, $propertyName, $formatted, $required
        );
        
    } elseif ($type instanceof \App\Core\Orm\Types\EnumType) {
        // Select
        $html .= sprintf('<select id="%s" name="%s" %s>',
            $propertyName, $propertyName, $required
        );
        foreach ($type->values as $enumValue) {
            $selected = $value === $enumValue ? 'selected' : '';
            $html .= sprintf(
                '<option value="%s" %s>%s</option>',
                $enumValue, $selected, $enumValue
            );
        }
        $html .= '</select>';
        
    } else {
        // Běžný textový input
        $maxLength = $column->length ? "maxlength=\"{$column->length}\"" : '';
        $placeholder = $column->placeholder ? "placeholder=\"{$column->placeholder}\"" : '';
        
        $html .= sprintf(
            '<input type="text" id="%s" name="%s" value="%s" %s %s %s>',
            $propertyName, $propertyName,
            htmlspecialchars($value ?? ''),
            $required, $maxLength, $placeholder
        );
    }
    
    if ($column->help) {
        $html .= sprintf('<small>%s</small>', htmlspecialchars($column->help));
    }
    
    $html .= '</div>';
    
    return $html;
}
```

### Celý formulář

```php
function generateEntityForm(string $entityClass, ?Entity $entity = null): string
{
    $columns = $entityClass::getColumns();
    $tableLabel = $entityClass::getTableLabel();
    
    $html = sprintf('<form class="entity-form"><h2>%s</h2>', htmlspecialchars($tableLabel));
    
    foreach ($columns as $propertyName => $column) {
        // Přeskočíme primární klíč a auto-increment pole
        if ($column->primaryKey || $column->autoIncrement) {
            continue;
        }
        
        // Přeskočíme hesla (zobrazíme pouze pro nové entity)
        if ($propertyName === 'password' && $entity !== null) {
            continue;
        }
        
        // Získáme hodnotu z entity
        $value = null;
        if ($entity !== null) {
            $reflection = new \ReflectionClass($entity);
            if ($reflection->hasProperty($propertyName)) {
                $prop = $reflection->getProperty($propertyName);
                if ($prop->isInitialized($entity)) {
                    $value = $prop->getValue($entity);
                }
            }
        }
        
        $html .= generateAdvancedFormField($entityClass, $propertyName, $value);
    }
    
    $html .= '<button type="submit">Uložit</button>';
    $html .= '</form>';
    
    return $html;
}

// Použití - nová entita
echo generateEntityForm(User::class);

// Použití - editace existující entity
$user = $userRepo->find(1);
echo generateEntityForm(User::class, $user);
```

## Generování gridů

### Hlavička gridu

```php
function generateGridHeader(string $entityClass): string
{
    $labels = $entityClass::getColumnLabels();
    $columns = $entityClass::getColumns();
    
    $headers = [];
    foreach ($labels as $propertyName => $label) {
        $column = $columns[$propertyName];
        
        // Skryjeme primární klíče, hesla a dlouhé texty
        if ($column->primaryKey || 
            $propertyName === 'password' || 
            $column->type instanceof \App\Core\Orm\Types\TextType) {
            continue;
        }
        
        $headers[] = sprintf(
            '<th data-column="%s" title="%s">%s</th>',
            $propertyName,
            htmlspecialchars($column->description ?? ''),
            htmlspecialchars($label)
        );
    }
    
    // Přidáme sloupec pro akce
    $headers[] = '<th>Akce</th>';
    
    return '<thead><tr>' . implode('', $headers) . '</tr></thead>';
}

// Použití
echo '<table class="data-grid">';
echo generateGridHeader(User::class);
echo '</table>';
```

### Řádky gridu

```php
function generateGridRow(Entity $entity): string
{
    $columns = $entity::getColumns();
    $cells = [];
    
    foreach ($columns as $propertyName => $column) {
        if ($column->primaryKey || 
            $propertyName === 'password' ||
            $column->type instanceof \App\Core\Orm\Types\TextType) {
            continue;
        }
        
        $reflection = new \ReflectionClass($entity);
        $prop = $reflection->getProperty($propertyName);
        
        if (!$prop->isInitialized($entity)) {
            $cells[] = '<td></td>';
            continue;
        }
        
        $value = $prop->getValue($entity);
        
        // Formátování pomocí TypeInterface
        $formatted = $column->type->format($value);
        
        $cells[] = sprintf('<td>%s</td>', htmlspecialchars($formatted));
    }
    
    // Akce
    $idProp = $reflection->getProperty('id');
    $id = $idProp->getValue($entity);
    $cells[] = sprintf(
        '<td><a href="edit.php?id=%s">Upravit</a> | <a href="delete.php?id=%s">Smazat</a></td>',
        $id, $id
    );
    
    return '<tr>' . implode('', $cells) . '</tr>';
}

// Celý grid
function generateGrid(string $entityClass, array $entities): string
{
    $tableLabel = $entityClass::getTableLabel();
    
    $html = sprintf('<h2>%s</h2>', htmlspecialchars($tableLabel));
    $html .= '<table class="data-grid">';
    $html .= generateGridHeader($entityClass);
    $html .= '<tbody>';
    
    foreach ($entities as $entity) {
        $html .= generateGridRow($entity);
    }
    
    $html .= '</tbody>';
    $html .= '</table>';
    
    return $html;
}

// Použití
$users = $userRepo->findAll();
echo generateGrid(User::class, $users);
```

## Integrace s Nette Forms

```php
use Nette\Application\UI\Form;
use App\Model\User;

function createUserForm(Form $form): void
{
    $columns = User::getColumns();
    
    foreach ($columns as $propertyName => $column) {
        if ($column->primaryKey || $column->autoIncrement) {
            continue;
        }
        
        $label = $column->getLabel($propertyName);
        $required = !$column->nullable;
        
        if ($propertyName === 'password') {
            $control = $form->addPassword($propertyName, $label);
        } elseif ($propertyName === 'email') {
            $control = $form->addEmail($propertyName, $label);
        } elseif ($column->type instanceof \App\Core\Orm\Types\BooleanType) {
            $control = $form->addCheckbox($propertyName, $label);
        } elseif ($column->type instanceof \App\Core\Orm\Types\TextType) {
            $control = $form->addTextArea($propertyName, $label);
        } else {
            $control = $form->addText($propertyName, $label);
        }
        
        if ($required) {
            $control->setRequired($column->help ?? 'Toto pole je povinné');
        }
        
        if ($column->length && method_exists($control, 'addRule')) {
            $control->addRule(Form::MaxLength, 
                "Maximální délka je {$column->length} znaků", 
                $column->length
            );
        }
        
        if ($column->placeholder) {
            $control->setHtmlAttribute('placeholder', $column->placeholder);
        }
        
        if ($column->description) {
            $control->setOption('description', $column->description);
        }
    }
    
    $form->addSubmit('submit', 'Uložit');
}

// Použití
$form = new Form();
createUserForm($form);
```

## Best Practices

### 1. Vždy definujte labely

```php
// ✓ Dobře
#[Column(
    type: new StringType(255),
    label: 'E-mailová adresa'
)]
public private(set) string $email;

// ✗ Špatně (použije se název property jako fallback)
#[Column(type: new StringType(255))]
public private(set) string $email;
```

### 2. Používejte description a help

```php
#[Column(
    type: new StringType(255),
    label: 'E-mail',
    description: 'Kontaktní e-mailová adresa uživatele',  // Pro tooltipy
    help: 'Zadejte platnou e-mailovou adresu',           // Pro nápovědu pod polem
    placeholder: 'uzivatel@example.com'                   // Pro placeholder
)]
public private(set) string $email;
```

### 3. Používejte placeholder pro příklady

```php
#[Column(
    type: new StringType(20),
    label: 'Telefon',
    placeholder: '+420 123 456 789'  // Ukázka formátu
)]
public private(set) ?string $phone = null;
```

## Další kroky

- [Zpět na základní použití](01-basic-usage.md)
- [Práce s relacemi](02-relations.md)
- [Formátování podle locale](04-locale-formatting.md) - Vícejazyčné labely
- [Vícejazyčnost](05-translations.md)
