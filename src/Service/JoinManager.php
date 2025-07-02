<?php

namespace WelshDev\DoctrineBaseRepository\Service;

use Doctrine\ORM\QueryBuilder;

/**
 * Service responsible for managing joins and alias generation
 * Extracted from BaseRepository for better separation of concerns
 */
class JoinManager
{
    /** @var array */
    private array $joins = [];
    
    /** @var bool */
    private bool $joinsDisabled = false;
    
    /** @var int */
    private int $aliasCounter = 0;

    /**
     * Add a join definition
     *
     * @param string $joinType (e.g., 'leftJoin', 'innerJoin')
     * @param string $joinColumn
     * @param string $joinAlias
     * @return self
     */
    public function addJoin(string $joinType, string $joinColumn, string $joinAlias): self
    {
        $this->joins[] = [$joinType, $joinColumn, $joinAlias];
        return $this;
    }

    /**
     * Add a left join
     *
     * @param string $joinColumn
     * @param string|null $joinAlias Auto-generated if null
     * @return string The join alias used
     */
    public function leftJoin(string $joinColumn, ?string $joinAlias = null): string
    {
        if ($joinAlias === null) {
            $joinAlias = $this->generateAlias();
        }
        
        $this->addJoin('leftJoin', $joinColumn, $joinAlias);
        return $joinAlias;
    }

    /**
     * Add an inner join
     *
     * @param string $joinColumn
     * @param string|null $joinAlias Auto-generated if null
     * @return string The join alias used
     */
    public function innerJoin(string $joinColumn, ?string $joinAlias = null): string
    {
        if ($joinAlias === null) {
            $joinAlias = $this->generateAlias();
        }
        
        $this->addJoin('innerJoin', $joinColumn, $joinAlias);
        return $joinAlias;
    }

    /**
     * Disable joins for this instance
     *
     * @param bool $disabled
     * @return self
     */
    public function disableJoins(bool $disabled = true): self
    {
        $this->joinsDisabled = $disabled;
        return $this;
    }

    /**
     * Check if joins are disabled
     *
     * @return bool
     */
    public function areJoinsDisabled(): bool
    {
        return $this->joinsDisabled;
    }

    /**
     * Apply all joins to the query builder
     *
     * @param QueryBuilder $queryBuilder
     * @param string $entityAlias
     * @param array $options
     * @return QueryBuilder
     */
    public function applyJoins(QueryBuilder $queryBuilder, string $entityAlias, array $options = []): QueryBuilder
    {
        $disableJoins = $options['disable_joins'] ?? false;
        
        if (count($this->joins) && !$disableJoins && !$this->joinsDisabled) {
            foreach ($this->joins as $join) {
                list($joinType, $joinColumn, $joinAlias) = $join;
                
                // Add entity alias prefix if no dot present
                if (stripos($joinColumn, ".") === false) {
                    $joinColumn = $entityAlias . "." . $joinColumn;
                }
                
                $queryBuilder->{$joinType}($joinColumn, $joinAlias);
            }
        }
        
        return $queryBuilder;
    }

    /**
     * Get all join definitions
     *
     * @return array
     */
    public function getJoins(): array
    {
        return $this->joins;
    }

    /**
     * Clear all joins
     */
    public function clearJoins(): void
    {
        $this->joins = [];
    }

    /**
     * Generate a unique alias for joins
     *
     * @param string $prefix
     * @return string
     */
    public function generateAlias(string $prefix = 'j'): string
    {
        $this->aliasCounter++;
        return $prefix . $this->aliasCounter;
    }

    /**
     * Reset the alias counter
     */
    public function resetAliasCounter(): void
    {
        $this->aliasCounter = 0;
    }
}