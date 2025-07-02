<?php

namespace WelshDev\DoctrineBaseRepository\Specification;

use Doctrine\ORM\QueryBuilder;
use WelshDev\DoctrineBaseRepository\Contract\SpecificationInterface;

/**
 * Example specification for multi-tenant functionality
 */
class TenantScopeSpecification implements SpecificationInterface
{
    private $tenantId;
    private string $tenantColumn;

    public function __construct($tenantId, string $tenantColumn = 'tenantId')
    {
        $this->tenantId = $tenantId;
        $this->tenantColumn = $tenantColumn;
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        $aliases = $queryBuilder->getRootAliases();
        $rootAlias = $aliases[0];
        
        $queryBuilder
            ->andWhere($queryBuilder->expr()->eq($rootAlias . '.' . $this->tenantColumn, ':tenantId'))
            ->setParameter('tenantId', $this->tenantId);
    }
}