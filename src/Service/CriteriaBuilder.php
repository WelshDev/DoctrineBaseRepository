<?php

namespace WelshDev\DoctrineBaseRepository\Service;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr\Composite;
use WelshDev\DoctrineBaseRepository\Contract\SpecificationInterface;

/**
 * Service responsible for building queries from criteria arrays
 * Extracted from BaseRepository for better separation of concerns
 */
class CriteriaBuilder
{
    private ParameterManager $parameterManager;

    public function __construct(ParameterManager $parameterManager)
    {
        $this->parameterManager = $parameterManager;
    }

    /**
     * Apply criteria to a query builder
     *
     * @param QueryBuilder $queryBuilder
     * @param array $criteria
     * @param string $alias
     * @return QueryBuilder
     */
    public function applyCriteria(QueryBuilder $queryBuilder, array $criteria, string $alias): QueryBuilder
    {
        if (count($criteria)) {
            $queryBuilder->andWhere($this->buildCriteria($queryBuilder, $queryBuilder->expr()->andX(), $criteria, $alias));
        }

        return $queryBuilder;
    }

    /**
     * Apply a specification to a query builder
     *
     * @param QueryBuilder $queryBuilder
     * @param SpecificationInterface $specification
     * @return QueryBuilder
     */
    public function applySpecification(QueryBuilder $queryBuilder, SpecificationInterface $specification): QueryBuilder
    {
        $specification->apply($queryBuilder);
        return $queryBuilder;
    }

    /**
     * Build criteria expression from array
     *
     * @param QueryBuilder $queryBuilder
     * @param Composite $expr
     * @param array $criteria
     * @param string $alias
     * @return Composite
     */
    public function buildCriteria(QueryBuilder $queryBuilder, Composite $expr, array $criteria, string $alias): Composite
    {
        if (!count($criteria)) {
            throw new \Exception("Empty criteria");
        }

        foreach ($criteria as $k => $v) {
            // Numeric (i.e. it's being passed in as an operator e.g. ["id", "eq", 999])
            if (is_numeric($k)) {
                if (!is_array($v)) {
                    throw new \Exception("Non-indexed criteria must be in array form e.g. ['id', 'eq', 1234]");
                }

                // Extract
                if (count($v) == 3) {
                    list($field, $operator, $value) = $v;
                } else {
                    list($field, $operator) = $v;
                    $value = true; // Default value
                }

                // Special case for or/and
                if (in_array($field, array("or", "and"))) {
                    $value = $operator;
                    $operator = $field;
                    $field = null;
                }
            }
            // Indexed (e.g. ["id" => 1234])
            else {
                if (is_array($v)) {
                    throw new \Exception("Indexed criteria does not support array values");
                }

                if (is_null($v)) {
                    $field = $k;
                    $operator = "is_null";
                    $value = true;
                } else {
                    $field = $k;
                    $operator = "eq";
                    $value = $v;
                }
            }

            $this->applyCriterion($queryBuilder, $expr, $field, $operator, $value, $alias);
        }

        return $expr;
    }

    /**
     * Apply a single criterion to the expression
     *
     * @param QueryBuilder $queryBuilder
     * @param Composite $expr
     * @param string|null $field
     * @param string $operator
     * @param mixed $value
     * @param string $alias
     */
    private function applyCriterion(QueryBuilder $queryBuilder, Composite $expr, ?string $field, string $operator, $value, string $alias): void
    {
        // Add alias prefix if no dot present
        if ($field && stripos($field, ".") === false) {
            $field = $alias . "." . $field;
        }

        switch ($operator) {
            case 'raw':
                $expr->add($value);
                break;

            case 'or':
                $expr->add($this->buildCriteria($queryBuilder, $queryBuilder->expr()->orX(), $value, $alias));
                break;

            case 'and':
                $expr->add($this->buildCriteria($queryBuilder, $queryBuilder->expr()->andX(), $value, $alias));
                break;

            case 'eq':
            case 'neq':
            case 'gt':
            case 'gte':
            case 'lt':
            case 'lte':
            case 'like':
                $this->applyBasicOperator($queryBuilder, $expr, $field, $operator, $value);
                break;

            case 'is_null':
            case 'not_null':
                $this->applyNullOperator($expr, $field, $operator, $value);
                break;

            case 'in':
            case 'not_in':
                $this->applyInOperator($queryBuilder, $expr, $field, $operator, $value);
                break;

            default:
                throw new \Exception("Unsupported operator: " . $operator);
        }
    }

    /**
     * Apply basic operators (eq, neq, gt, etc.)
     */
    private function applyBasicOperator(QueryBuilder $queryBuilder, Composite $expr, string $field, string $operator, $value): void
    {
        if (is_array($value)) {
            throw new \Exception("Array lookups are not supported for the '" . $operator . "' operator");
        }

        if (is_null($value)) {
            $expr->add($queryBuilder->expr()->isNull($field));
        } else {
            $parameter = $this->parameterManager->createNamedParameter($queryBuilder, $value);
            $expr->add($queryBuilder->expr()->{$operator}($field, $parameter));
        }
    }

    /**
     * Apply null operators
     */
    private function applyNullOperator(Composite $expr, string $field, string $operator, $value): void
    {
        if ($operator === "is_null") {
            if ($value) {
                $expr->add($field . ' IS NULL');
            } else {
                $expr->add($field . ' IS NOT NULL');
            }
        } elseif ($operator === "not_null") {
            if ($value) {
                $expr->add($field . ' IS NOT NULL');
            } else {
                $expr->add($field . ' IS NULL');
            }
        }
    }

    /**
     * Apply in/not_in operators
     */
    private function applyInOperator(QueryBuilder $queryBuilder, Composite $expr, string $field, string $operator, $value): void
    {
        if (!is_array($value)) {
            throw new \Exception("Invalid value for operator: " . $operator);
        }

        if ($operator === "in") {
            $parameter = $this->parameterManager->createNamedParameter($queryBuilder, $value);
            $expr->add($queryBuilder->expr()->in($field, $parameter));
        } elseif ($operator === "not_in") {
            // Build null-safe NOT IN
            $builtArraySQL = array();
            foreach ($value as $someValue) {
                if (is_null($someValue)) {
                    $builtArraySQL[] = '(' . $field . ' IS NOT NULL)';
                } else {
                    $parameter = $this->parameterManager->createNamedParameter($queryBuilder, $someValue);
                    $builtArraySQL[] = '(' . $field . ' != ' . $parameter . ' OR ' . $field . ' IS NULL)';
                }
            }

            if (count($builtArraySQL)) {
                $fullSQL = "(" . implode(' AND ', $builtArraySQL) . ")";
                $expr->add($fullSQL);
            }
        }
    }
}