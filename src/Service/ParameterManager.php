<?php

namespace WelshDev\DoctrineBaseRepository\Service;

use Doctrine\ORM\QueryBuilder;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Uid\Uuid;

/**
 * Service responsible for parameter binding and value normalization
 * Isolated from repository to avoid mutable state issues
 */
class ParameterManager
{
    private int $parameterCounter = 0;

    /**
     * Create a named parameter for the query builder
     *
     * @param QueryBuilder $queryBuilder
     * @param mixed $value
     * @param int|null $type
     * @return string The parameter placeholder
     */
    public function createNamedParameter(QueryBuilder $queryBuilder, $value, ?int $type = null): string
    {
        $this->parameterCounter++;
        $placeholder = ':paramValue' . $this->parameterCounter;
        
        $preparedValue = $this->prepareValue($value);
        
        if ($type !== null) {
            $queryBuilder->setParameter(substr($placeholder, 1), $preparedValue, $type);
        } else {
            // Special handling for UUID binary
            if ($value instanceof Uuid) {
                $queryBuilder->setParameter(substr($placeholder, 1), $preparedValue, ParameterType::BINARY);
            } else {
                $queryBuilder->setParameter(substr($placeholder, 1), $preparedValue);
            }
        }
        
        return $placeholder;
    }

    /**
     * Prepare value for database storage
     *
     * @param mixed $value
     * @return mixed
     */
    public function prepareValue($value)
    {
        // DateTime
        if (is_object($value) && $value instanceof \DateTime) {
            return $value->format('Y-m-d H:i:s');
        }
        // UUID
        elseif ($value instanceof Uuid) {
            return $value->toBinary();
        }
        // Object (likely an association)
        elseif (is_object($value)) {
            return $value;
        }
        // Array
        elseif (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->prepareValue($v);
            }
            return $value;
        }
        // Anything else
        else {
            return $value;
        }
    }

    /**
     * Reset the parameter counter (useful for isolated operations)
     */
    public function resetCounter(): void
    {
        $this->parameterCounter = 0;
    }

    /**
     * Get current parameter counter value
     */
    public function getCounter(): int
    {
        return $this->parameterCounter;
    }
}