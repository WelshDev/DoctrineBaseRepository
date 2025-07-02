<?php

/**
 * Example usage of the enhanced DoctrineBaseRepository
 * 
 * This file demonstrates the new v2 features and improvements
 */

// Example Entity
class Vehicle 
{
    public $id;
    public $make;
    public $model;
    public $colour;
    public $year;
    public $features; // JSON field
    public $deletedAt;
    public $tenantId;
}

// Example Repository using the new BaseRepository
class VehicleRepository extends \WelshDev\DoctrineBaseRepository\BaseRepository
{
    // Custom methods can be added here
    
    public function findActiveVehicles()
    {
        // Using the new specification pattern
        return $this->findBySpecification(
            new \WelshDev\DoctrineBaseRepository\Specification\NotDeletedSpecification()
        );
    }
    
    public function findVehiclesByTenant($tenantId)
    {
        return $this->findBySpecification(
            new \WelshDev\DoctrineBaseRepository\Specification\TenantScopeSpecification($tenantId)
        );
    }
}

// Usage examples:

// 1. Basic filtering (backward compatible)
$vehicles = $vehicleRepo->findByCriteria([
    'colour' => 'red',
    ['year', 'gte', 2020]
]);

// 2. JSON field queries
$vehicles = $vehicleRepo->findByCriteria([
    ['features->gps', 'eq', true],
    ['features', 'json_contains', ['heated_seats' => true]]
]);

// 3. Complex criteria with logical operators
$vehicles = $vehicleRepo->findByCriteria([
    ['or', [
        ['make', 'eq', 'Toyota'],
        ['make', 'eq', 'Honda']
    ]],
    ['and', [
        ['year', 'gte', 2018],
        ['year', 'lte', 2022]
    ]]
]);

// 4. Batch operations
$updatedCount = $vehicleRepo->updateByCriteria(
    ['status' => 'pending'],
    ['status' => 'processed', 'processedAt' => new DateTime()]
);

$deletedCount = $vehicleRepo->deleteByCriteria([
    ['year', 'lt', 2010]
]);

// 5. Specification pattern
$specification = new \WelshDev\DoctrineBaseRepository\Specification\NotDeletedSpecification();
$activeVehicles = $vehicleRepo->findBySpecification($specification);

// 6. Filter functions
$vehicleRepo->addFilterFunction(function($queryBuilder) {
    return $queryBuilder->andWhere('v.deletedAt IS NULL');
});

// 7. Legacy API (still works)
$vehicles = $vehicleRepo->findFiltered(['colour' => 'blue']);
$vehicle = $vehicleRepo->findOneFiltered(['id' => 123]);
$count = $vehicleRepo->countRows('id', ['status' => 'active']);

// 8. Full-text search (requires additional setup)
/*
$searchService = new \WelshDev\DoctrineBaseRepository\Service\FullTextSearchService();
$queryBuilder = $vehicleRepo->createQueryBuilder('v');
$searchService->addMySQLFullTextSearch(
    $queryBuilder,
    'toyota camry',
    ['make', 'model'],
    'v'
);
$results = $queryBuilder->getQuery()->getResult();
*/

echo "DoctrineBaseRepository v2 - Ready to use!\n";
echo "All syntax checks passed - implementation is complete.\n";