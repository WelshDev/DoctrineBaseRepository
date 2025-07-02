<?php

namespace WelshDev\DoctrineBaseRepository\Contract;

use Doctrine\ORM\QueryBuilder;

/**
 * Interface for query setup implementations
 * Replaces single callable setupFunction with more structured approach
 */
interface QuerySetupInterface
{
    /**
     * Apply setup logic to the query builder
     *
     * @param string $alias The entity alias
     * @param QueryBuilder $queryBuilder
     * @return QueryBuilder
     */
    public function setup(string $alias, QueryBuilder $queryBuilder): QueryBuilder;
}