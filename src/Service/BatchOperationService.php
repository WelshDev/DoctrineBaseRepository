<?php

namespace WelshDev\DoctrineBaseRepository\Service;

use Doctrine\ORM\QueryBuilder;

/**
 * Service for batch operations (update/delete by criteria)
 */
class BatchOperationService
{
    private CriteriaBuilder $criteriaBuilder;

    public function __construct(CriteriaBuilder $criteriaBuilder)
    {
        $this->criteriaBuilder = $criteriaBuilder;
    }

    /**
     * Update entities by criteria
     *
     * @param QueryBuilder $queryBuilder Base update query builder
     * @param array $criteria
     * @param array $updateData Key-value pairs of fields to update
     * @param string $alias
     * @return int Number of affected rows
     */
    public function updateByCriteria(QueryBuilder $queryBuilder, array $criteria, array $updateData, string $alias): int
    {
        // Apply criteria to the update query
        if (count($criteria)) {
            $whereExpr = $this->criteriaBuilder->buildCriteria(
                $queryBuilder,
                $queryBuilder->expr()->andX(),
                $criteria,
                $alias
            );
            $queryBuilder->where($whereExpr);
        }

        // Apply updates
        foreach ($updateData as $field => $value) {
            if (stripos($field, ".") === false) {
                $field = $alias . "." . $field;
            }
            $queryBuilder->set($field, ':update_' . str_replace('.', '_', $field));
            $queryBuilder->setParameter('update_' . str_replace('.', '_', $field), $value);
        }

        return $queryBuilder->getQuery()->execute();
    }

    /**
     * Delete entities by criteria
     *
     * @param QueryBuilder $queryBuilder Base delete query builder
     * @param array $criteria
     * @param string $alias
     * @return int Number of affected rows
     */
    public function deleteByCriteria(QueryBuilder $queryBuilder, array $criteria, string $alias): int
    {
        // Apply criteria to the delete query
        if (count($criteria)) {
            $whereExpr = $this->criteriaBuilder->buildCriteria(
                $queryBuilder,
                $queryBuilder->expr()->andX(),
                $criteria,
                $alias
            );
            $queryBuilder->where($whereExpr);
        }

        return $queryBuilder->getQuery()->execute();
    }
}