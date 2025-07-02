<?php

namespace WelshDev\DoctrineBaseRepository\Service;

use Doctrine\ORM\QueryBuilder;

/**
 * Service responsible for managing and applying filter functions
 * Extracted from BaseRepository for better separation of concerns
 */
class FilterManager
{
    /** @var callable[] */
    private array $globalFilters = [];
    
    /** @var array<string, callable[]> */
    private array $repositoryFilters = [];

    /**
     * Add a global filter that applies to all repositories
     *
     * @param callable $filter
     * @return self
     */
    public function addGlobalFilter(callable $filter): self
    {
        $this->globalFilters[] = $filter;
        return $this;
    }

    /**
     * Add a filter for a specific repository
     *
     * @param string $repositoryClass
     * @param callable $filter
     * @return self
     */
    public function addRepositoryFilter(string $repositoryClass, callable $filter): self
    {
        if (!isset($this->repositoryFilters[$repositoryClass])) {
            $this->repositoryFilters[$repositoryClass] = [];
        }
        
        $this->repositoryFilters[$repositoryClass][] = $filter;
        return $this;
    }

    /**
     * Apply all relevant filters to a query builder
     *
     * @param QueryBuilder $queryBuilder
     * @param string $repositoryClass
     * @param callable[] $instanceFilters Additional filters from repository instance
     * @return QueryBuilder
     */
    public function applyFilters(QueryBuilder $queryBuilder, string $repositoryClass, array $instanceFilters = []): QueryBuilder
    {
        // Apply global filters
        foreach ($this->globalFilters as $filter) {
            $queryBuilder = $filter($queryBuilder);
        }

        // Apply repository-specific filters
        if (isset($this->repositoryFilters[$repositoryClass])) {
            foreach ($this->repositoryFilters[$repositoryClass] as $filter) {
                $queryBuilder = $filter($queryBuilder);
            }
        }

        // Apply instance filters
        foreach ($instanceFilters as $filter) {
            $queryBuilder = $filter($queryBuilder);
        }

        return $queryBuilder;
    }

    /**
     * Get all global filters
     *
     * @return callable[]
     */
    public function getGlobalFilters(): array
    {
        return $this->globalFilters;
    }

    /**
     * Get filters for a specific repository
     *
     * @param string $repositoryClass
     * @return callable[]
     */
    public function getRepositoryFilters(string $repositoryClass): array
    {
        return $this->repositoryFilters[$repositoryClass] ?? [];
    }

    /**
     * Clear all global filters
     */
    public function clearGlobalFilters(): void
    {
        $this->globalFilters = [];
    }

    /**
     * Clear filters for a specific repository
     *
     * @param string $repositoryClass
     */
    public function clearRepositoryFilters(string $repositoryClass): void
    {
        unset($this->repositoryFilters[$repositoryClass]);
    }
}