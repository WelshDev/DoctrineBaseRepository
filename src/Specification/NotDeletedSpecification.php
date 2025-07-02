<?php

namespace WelshDev\DoctrineBaseRepository\Specification;

use Doctrine\ORM\QueryBuilder;
use WelshDev\DoctrineBaseRepository\Contract\SpecificationInterface;

/**
 * Example specification for soft-delete functionality
 */
class NotDeletedSpecification implements SpecificationInterface
{
    private string $deletedAtColumn;

    public function __construct(string $deletedAtColumn = 'deletedAt')
    {
        $this->deletedAtColumn = $deletedAtColumn;
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        $aliases = $queryBuilder->getRootAliases();
        $rootAlias = $aliases[0];
        
        $queryBuilder->andWhere($queryBuilder->expr()->isNull($rootAlias . '.' . $this->deletedAtColumn));
    }
}