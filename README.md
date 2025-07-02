# DoctrineBaseRepository

A powerful and extensible base repository for Doctrine ORM providing simple array-based queries and advanced features for Symfony applications.

## Features

### Core Query Methods
- **Array-based filtering**: Simple and complex criteria using arrays
- **Specification pattern**: Reusable, testable query specifications
- **Join management**: Automatic join handling with alias generation  
- **Filter functions**: Chainable query modifications
- **Parameter binding**: Safe, isolated parameter management

### Advanced Features
- **JSON/JSONB support**: Query nested JSON data (PostgreSQL/MySQL 5.7+)
- **Full-text search**: PostgreSQL and MySQL full-text search integration
- **Batch operations**: Update and delete by criteria
- **Soft delete support**: Built-in soft delete filtering
- **Multi-tenant support**: Automatic tenant scoping

### Architecture
- **Separation of concerns**: Modular service-based architecture
- **Dependency injection**: ServiceEntityRepository support for DI
- **Backward compatibility**: Maintains full compatibility with v1 API
- **Strong typing**: PHP 7.4+ type hints and return types

## Installation

```bash
composer require welshdev/doctrine-base-repository
```

## Basic Usage

### Simple Filtering

```php
// Get all vehicles
$vehicles = $em->getRepository(Vehicle::class)->findByCriteria();

// Get all red vehicles  
$vehicles = $em->getRepository(Vehicle::class)->findByCriteria([
    "colour" => "red"
]);

// Get red vehicles older than 5 years
$vehicles = $em->getRepository(Vehicle::class)->findByCriteria([
    "colour" => "red",
    ["age", "gt", 5]
]);

// Get one vehicle by ID
$vehicle = $em->getRepository(Vehicle::class)->findOneByCriteria([
    "id" => 10
]);
```

### Repository Setup

#### Option 1: Extend BaseRepository (Legacy Compatible)
```php
<?php

namespace App\Repository;

use WelshDev\DoctrineBaseRepository\BaseRepository;
use App\Entity\Vehicle;

class VehicleRepository extends BaseRepository
{
    // Your custom methods here
}
```

#### Option 2: Extend EnhancedBaseRepository (with DI)
```php
<?php

namespace App\Repository;

use WelshDev\DoctrineBaseRepository\EnhancedBaseRepository;
use App\Entity\Vehicle;
use Doctrine\Persistence\ManagerRegistry;

class VehicleRepository extends EnhancedBaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vehicle::class);
    }
}
```

## Advanced Usage

### Specification Pattern

Create reusable query specifications:

```php
use WelshDev\DoctrineBaseRepository\Contract\SpecificationInterface;

class ActiveVehiclesSpecification implements SpecificationInterface  
{
    public function apply(QueryBuilder $queryBuilder): void
    {
        $queryBuilder
            ->andWhere('v.deletedAt IS NULL')
            ->andWhere('v.status = :status')
            ->setParameter('status', 'active');
    }
}

// Use the specification
$vehicles = $repository->findBySpecification(new ActiveVehiclesSpecification());
```

### JSON Field Queries

Query nested JSON data:

```php
// Query JSON field with -> notation
$users = $repository->findByCriteria([
    ['profile->preferences->theme', 'eq', 'dark']
]);

// Use JSON operators
$users = $repository->findByCriteria([
    ['metadata', 'json_contains', ['status' => 'active']]
]);
```

### Batch Operations

```php
// Update multiple records
$updatedCount = $repository->updateByCriteria(
    ['status' => 'pending'],  // criteria
    ['status' => 'processed', 'processedAt' => new DateTime()]  // updates
);

// Delete multiple records  
$deletedCount = $repository->deleteByCriteria([
    ['createdAt', 'lt', new DateTime('-1 year')]
]);
```

### Full-Text Search

```php
use WelshDev\DoctrineBaseRepository\Service\FullTextSearchService;

$searchService = new FullTextSearchService();

// PostgreSQL full-text search
$queryBuilder = $repository->createQueryBuilder('v');
$searchService->addPostgreSQLFullTextSearch(
    $queryBuilder,
    'search term',
    ['title', 'description'],
    'v'
);
$results = $queryBuilder->getQuery()->getResult();

// MySQL full-text search with relevance scoring
$queryBuilder = $repository->createQueryBuilder('v');
$searchService->addRelevanceScoring(
    $queryBuilder, 
    'search term',
    ['title', 'description'],
    'v'
);
$results = $queryBuilder->getQuery()->getResult();
```

### Filter Functions

Add reusable query modifications:

```php
// Add a filter function
$repository->addFilterFunction(function($queryBuilder) {
    return $queryBuilder->andWhere('v.deletedAt IS NULL');
});

// Results will automatically include the filter
$vehicles = $repository->findByCriteria(['colour' => 'red']);
```

### Query Setup

Use structured query setup instead of single callbacks:

```php
use WelshDev\DoctrineBaseRepository\Contract\QuerySetupInterface;

class SoftDeleteQuerySetup implements QuerySetupInterface
{
    public function setup(string $alias, QueryBuilder $queryBuilder): QueryBuilder  
    {
        return $queryBuilder->andWhere($alias . '.deletedAt IS NULL');
    }
}

$repository->addQuerySetup(new SoftDeleteQuerySetup());
```

## Available Operators

### Basic Operators
- `eq` - Equal
- `neq` - Not equal  
- `gt` - Greater than
- `gte` - Greater than or equal
- `lt` - Less than
- `lte` - Less than or equal
- `like` - SQL LIKE
- `in` - IN array
- `not_in` - NOT IN array (null-safe)
- `is_null` - IS NULL
- `not_null` - IS NOT NULL

### Advanced Operators
- `json_contains` - JSON contains (MySQL/PostgreSQL)
- `json_extract` - JSON path extraction
- `raw` - Raw SQL expression

### Logical Operators
- `and` - Logical AND grouping
- `or` - Logical OR grouping

## Examples

### Complex Criteria

```php
$vehicles = $repository->findByCriteria([
    'colour' => 'red',
    ['year', 'gte', 2020],
    ['or', [
        ['make', 'eq', 'Toyota'],
        ['make', 'eq', 'Honda']
    ]],
    ['features', 'json_contains', ['gps' => true]]
]);
```

### With Ordering and Pagination

```php
$vehicles = $repository->findByCriteria(
    ['status' => 'active'],           // criteria
    ['year' => 'DESC', 'make' => 'ASC'], // ordering  
    10,                               // limit
    20                                // offset
);
```

### Legacy API (Still Supported)

```php
// These methods still work for backward compatibility
$vehicles = $repository->findFiltered(['colour' => 'red']);
$vehicle = $repository->findOneFiltered(['id' => 1]);  
$count = $repository->countRows('id', ['status' => 'active']);
```

## Migration from v1

The library maintains full backward compatibility. All existing v1 methods continue to work unchanged. New features are available through:

- `findByCriteria()` instead of `findFiltered()`
- `findOneByCriteria()` instead of `findOneFiltered()`  
- `countByCriteria()` instead of `countRows()`

## Requirements

- PHP 7.4+
- Doctrine ORM 2.10+
- Doctrine DBAL 3.2+
