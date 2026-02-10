<?php

declare(strict_types=1);

namespace prochst\bsOrm\Types;

/**
 * Typ pro binární data (Binary Large Object)
 * 
 * BlobType se používá pro:
 * - Obrázky, fotografie
 * - PDF soubory
 * - Binární dokumenty
 * - Serializovaná data
 * - Encrypted data
 * 
 * ⚠️ VAROVÁNÍ: Ukládání velkých souborů do databáze není doporučeno!
 * - Lepší je ukládat soubory na disk/S3 a v DB jen cestu
 * - BLOB zpomaluje zálohy
 * - Zvětšuje velikost databáze
 * - Horší výkon
 * 
 * Použijte pouze pro:
 * - Malá data (< 1 MB)
 * - Data která musí být v transakci s dalšími záznamy
 * - Avatary, ikony
 * 
 * SQL mapování:
 * - MySQL/MariaDB: BLOB
 * - PostgreSQL: BYTEA
 * - SQLite: BLOB
 * 
 * @package prochst\bsOrm\Types
 * @author  Your Name
 * @version 1.0.0
 * 
 * @example
 * ```php
 * // Avatar uživatele (malý obrázek)
 * #[Column(
 *     label: 'Avatar',
 *     type: new BlobType(),
 *     nullable: true
 * )]
 * private ?string $avatar;
 * 
 * // Serializovaná konfigurace
 * #[Column(type: new BlobType())]
 * private string $serializedConfig;
 * 
 * // ⚠️ NE-DOPORUČENO: velké soubory
 * // Raději uložte cestu k souboru jako String
 * #[Column(type: new StringType())]
 * private string $photoPath; // "/uploads/photos/123.jpg"
 * ```
 */
class BlobType implements TypeInterface
{
    /**
     * {@inheritdoc}
     * 
     * @example
     * ```php
     * $type = new BlobType();
     * 
     * $binary = file_get_contents('avatar.jpg');
     * $stored = $type->toDatabase($binary);
     * // Binární data
     * 
     * echo $type->toDatabase(null);
     * // null
     * ```
     */
    public function toDatabase(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        
        return $value;
    }
    
    /**
     * {@inheritdoc}
     * 
     * PostgreSQL vrací resource pro BYTEA, převedeme na string.
     * 
     * @example
     * ```php
     * $type = new BlobType();
     * 
     * // MySQL/SQLite
     * $binary = $type->fromDatabase($dbValue);
     * // Binární string
     * 
     * // PostgreSQL (vrací resource)
     * $binary = $type->fromDatabase($pgResource);
     * // Převedeno stream_get_contents()
     * 
     * // Uložení do souboru
     * file_put_contents('restored.jpg', $binary);
     * ```
     */
    public function fromDatabase(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        
        // PostgreSQL vrací resource pro bytea
        if (is_resource($value)) {
            return stream_get_contents($value);
        }
        
        return $value;
    }
    
    /**
     * {@inheritdoc}
     * 
     * @example
     * ```php
     * $type = new BlobType();
     * echo $type->getSqlType('mysql');   // "BLOB"
     * echo $type->getSqlType('pgsql');   // "BYTEA"
     * echo $type->getSqlType('sqlite');  // "BLOB"
     * ```
     */
    public function getSqlType(string $driver): string
    {
        return match($driver) {
            'pgsql' => 'BYTEA',
            'mysql' => 'BLOB',
            'sqlite' => 'BLOB',
            default => 'BLOB',
        };
    }
    
    /**
     * {@inheritdoc}
     * 
     * Kontroluje že hodnota je string nebo resource.
     * 
     * @example
     * ```php
     * $type = new BlobType();
     * 
     * $binary = file_get_contents('file.jpg');
     * $errors = $type->validate($binary);
     * // [] - OK
     * 
     * $resource = fopen('file.jpg', 'r');
     * $errors = $type->validate($resource);
     * // [] - OK
     * 
     * $errors = $type->validate(12345);
     * // ["Hodnota musí být string nebo resource"]
     * ```
     */
    public function validate(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        
        if (!is_string($value) && !is_resource($value)) {
            return ['Hodnota musí být string nebo resource'];
        }
        
        return [];
    }
    
    /**
     * Formátuje BLOB jako base64 nebo info
     * 
     * Binární data nelze zobrazit přímo, vrátíme info o velikosti.
     * 
     * @param mixed $value Hodnota k formátování
     * @param string|null $locale Locale (nepoužito)
     * 
     * @return string Informace o BLOB
     * 
     * @example
     * ```php
     * $type = new BlobType();
     * 
     * $binary = file_get_contents('avatar.jpg');
     * echo $type->format($binary);
     * // "[Binary data: 12.5 KB]"
     * 
     * // Pro zobrazení obrázku v HTML
     * $base64 = base64_encode($binary);
     * echo "<img src=\"data:image/jpeg;base64,{$base64}\">";
     * ```
     */
    public function format(mixed $value, ?string $locale = null): string
    {
        if ($value === null) {
            return '';
        }
        
        $size = strlen($value);
        $units = ['B', 'KB', 'MB', 'GB'];
        $unit = 0;
        
        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }
        
        return sprintf('[Binary data: %.1f %s]', $size, $units[$unit]);
    }
    
    /**
     * Parsuje base64 string na binární data
     * 
     * @param string $value Base64 encoded string
     * @param string|null $locale Locale (nepoužito)
     * 
     * @return string|null Binární data nebo null
     * 
     * @example
     * ```php
     * $type = new BlobType();
     * 
     * // Z formuláře s base64 input
     * $base64 = $_POST['avatar_base64'];
     * $binary = $type->parse($base64);
     * 
     * // Uložení
     * $user->setAvatar($binary);
     * $userRepo->save($user);
     * ```
     */
    public function parse(string $value, ?string $locale = null): ?string
    {
        if ($value === '') {
            return null;
        }
        
        // Pokusíme se dekódovat base64
        $decoded = base64_decode($value, true);
        if ($decoded !== false) {
            return $decoded;
        }
        
        return $value;
    }
}