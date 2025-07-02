<?php

namespace WelshDev\DoctrineBaseRepository\Contract;

use Doctrine\ORM\QueryBuilder;

/**
 * Interface for specification pattern implementation
 * Allows reusable, testable filter specifications
 */
interface SpecificationInterface
{
    /**
     * Apply the specification to the query builder
     *
     * @param QueryBuilder $queryBuilder
     * @return void
     */
    public function apply(QueryBuilder $queryBuilder): void;
}