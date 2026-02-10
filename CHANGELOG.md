# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-02-10

### Added
- Initial release of BS ORM
- Entity base class with attribute-based metadata
- Repository pattern implementation
- Database abstraction layer (DBAL) with PDO
- Support for MySQL, PostgreSQL, and SQLite
- Column attributes with multi-language support
- Table attributes with indexes and foreign keys
- Type system with 11 built-in types:
  - StringType, IntegerType, BooleanType
  - DateTimeType, DecimalType, CurrencyType
  - JsonType, EnumType, BlobType, TextType
  - LocaleManager for internationalization
- Relations support:
  - HasOne (1:1)
  - HasMany (1:N)
  - BelongsTo (N:1)
  - BelongsToMany (M:N)
- Eager loading to prevent N+1 queries
- Batch eager loading for collections
- Change tracking for optimized updates
- Transaction support with automatic rollback
- Built-in validation system
- Migration helper utilities
- Translation helper for exporting metadata
- Comprehensive documentation
- Usage examples

### Features
- **Type-safe**: Full PHP 8.1+ type support
- **Zero dependencies**: Only requires PDO
- **Database agnostic**: Works with MySQL, PostgreSQL, SQLite
- **Multi-language**: Built-in i18n for labels, placeholders, help texts
- **Performance**: Optimized queries, eager loading, change tracking
- **Flexible**: Repository pattern with custom repositories support
- **Developer-friendly**: Extensive PHPDoc, clear examples

## [Unreleased]

### Planned for v1.1.0
- Query builder for complex queries
- Soft deletes support
- Events and observers
- Cache layer integration
- More test coverage

---

[1.0.0]: https://github.com/prochst/bs-orm/releases/tag/v1.0.0
