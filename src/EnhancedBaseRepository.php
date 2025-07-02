<?php

namespace WelshDev\DoctrineBaseRepository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use WelshDev\DoctrineBaseRepository\Contract\SpecificationInterface;
use WelshDev\DoctrineBaseRepository\Contract\QuerySetupInterface;
use WelshDev\DoctrineBaseRepository\Service\CriteriaBuilder;
use WelshDev\DoctrineBaseRepository\Service\FilterManager;
use WelshDev\DoctrineBaseRepository\Service\JoinManager;
use WelshDev\DoctrineBaseRepository\Service\ParameterManager;

/**
 * Enhanced base repository with improved architecture and separation of concerns
 * Now extends ServiceEntityRepository for better DI support
 */
class EnhancedBaseRepository extends ServiceEntityRepository
{
    protected CriteriaBuilder $criteriaBuilder;
    protected FilterManager $filterManager;
    protected JoinManager $joinManager;
    protected ParameterManager $parameterManager;
    
    /** @var QuerySetupInterface[] */
    protected array $querySetups = [];
    
    /** @var callable[] */
    protected array $instanceFilters = [];
    
    /** @var callable|null */
    protected $setupFunction = null;

    public function __construct(
        ManagerRegistry $registry,
        string $entityClass,
        ?CriteriaBuilder $criteriaBuilder = null,
        ?FilterManager $filterManager = null,
        ?JoinManager $joinManager = null,
        ?ParameterManager $parameterManager = null
    ) {
        parent::__construct($registry, $entityClass);
        
        // Initialize services with fallbacks for backward compatibility
        $this->parameterManager = $parameterManager ?? new ParameterManager();
        $this->criteriaBuilder = $criteriaBuilder ?? new CriteriaBuilder($this->parameterManager);
        $this->filterManager = $filterManager ?? new FilterManager();
        $this->joinManager = $joinManager ?? new JoinManager();
    }

    /**
     * Find entities by criteria array (new API method)
     *
     * @param array $criteria
     * @param array|null $orderBy
     * @param int|null $limit
     * @param int|null $offset
     * @return array
     */
    public function findByCriteria(array $criteria = [], ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        $queryBuilder = $this->buildQuery($criteria, $orderBy ?? [], $limit, $offset ?? 0);
        $query = $queryBuilder->getQuery();
        
        $this->clearInstanceFilters();
        
        return $query->getResult();
    }

    /**
     * Find one entity by criteria array (new API method)
     *
     * @param array $criteria
     * @param array|null $orderBy
     * @param int|null $offset
     * @return object|null
     */
    public function findOneByCriteria(array $criteria = [], ?array $orderBy = null, ?int $offset = null): ?object
    {
        $queryBuilder = $this->buildQuery($criteria, $orderBy ?? [], 1, $offset ?? 0);
        $query = $queryBuilder->getQuery();
        
        $this->clearInstanceFilters();
        
        return $query->getOneOrNullResult();
    }

    /**
     * Count entities by criteria array (new API method)
     *
     * @param array $criteria
     * @param string $column
     * @return int
     */
    public function countByCriteria(array $criteria = [], string $column = 'id'): int
    {
        $queryBuilder = $this->buildQuery($criteria);
        $queryBuilder->select('count(' . $this->getAlias() . '.' . $column . ')');
        
        $query = $queryBuilder->getQuery();
        
        return (int) $query->getSingleScalarResult();
    }

    /**
     * Find entities using a specification
     *
     * @param SpecificationInterface $specification
     * @param array|null $orderBy
     * @param int|null $limit
     * @param int|null $offset
     * @return array
     */
    public function findBySpecification(SpecificationInterface $specification, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        $queryBuilder = $this->createQueryBuilder($this->getAlias());
        $this->criteriaBuilder->applySpecification($queryBuilder, $specification);
        
        $this->applyOrderBy($queryBuilder, $orderBy ?? []);
        
        if ($limit) {
            $queryBuilder->setMaxResults($limit);
        }
        if ($offset) {
            $queryBuilder->setFirstResult($offset);
        }
        
        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Build a query from criteria (improved version)
     *
     * @param array $filters
     * @param array $order
     * @param int|null $limit
     * @param int $offset
     * @param array $options
     * @return QueryBuilder
     */
    public function buildQuery(array $filters = [], array $order = [], ?int $limit = null, int $offset = 0, array $options = []): QueryBuilder
    {
        $alias = $this->getAlias();
        $queryBuilder = $this->createQueryBuilder($alias)->select($alias);
        
        // Apply query setups
        foreach ($this->querySetups as $setup) {
            $queryBuilder = $setup->setup($alias, $queryBuilder);
        }
        
        // Legacy setup function support
        if (is_callable($this->setupFunction)) {
            $queryBuilder = call_user_func($this->setupFunction, $alias, $queryBuilder);
            $this->setupFunction = null;
        }
        
        // Apply joins
        $this->joinManager->applyJoins($queryBuilder, $alias, $options);
        
        // Apply ordering
        $this->applyOrderBy($queryBuilder, $order);
        
        // Apply limits
        if ($limit) {
            $queryBuilder->setMaxResults($limit);
        }
        if ($offset) {
            $queryBuilder->setFirstResult($offset);
        }
        
        // Apply filters
        $this->filterManager->applyFilters($queryBuilder, static::class, $this->instanceFilters);
        
        // Add default group by (legacy compatibility)
        $queryBuilder->addGroupBy($alias . '.id');
        
        // Apply criteria
        if (count($filters)) {
            $this->criteriaBuilder->applyCriteria($queryBuilder, $filters, $alias);
        }
        
        return $queryBuilder;
    }

    /**
     * Add a query setup handler
     *
     * @param QuerySetupInterface $setup
     * @return self
     */
    public function addQuerySetup(QuerySetupInterface $setup): self
    {
        $this->querySetups[] = $setup;
        return $this;
    }

    /**
     * Add an instance filter function
     *
     * @param callable $filter
     * @return self
     */
    public function addFilterFunction(callable $filter): self
    {
        $this->instanceFilters[] = $filter;
        return $this;
    }

    /**
     * Get all instance filter functions
     *
     * @return callable[]
     */
    public function getFilterFunctions(): array
    {
        return $this->instanceFilters;
    }

    /**
     * Clear instance filters
     */
    protected function clearInstanceFilters(): void
    {
        $this->instanceFilters = [];
    }

    /**
     * Legacy setup method for backward compatibility
     *
     * @param callable $callback
     * @return self
     */
    public function setup(callable $callback): self
    {
        $this->setupFunction = $callback;
        return $this;
    }

    /**
     * Disable joins
     *
     * @param bool $disabled
     * @return self
     */
    public function disableJoins(bool $disabled = true): self
    {
        $this->joinManager->disableJoins($disabled);
        return $this;
    }

    /**
     * Get the entity alias for queries
     *
     * @return string
     */
    protected function getAlias(): string
    {
        $className = $this->getClassName();
        $parts = explode('\\', $className);
        $shortName = end($parts);
        return strtolower(substr($shortName, 0, 1));
    }

    /**
     * Apply order by clauses to query builder
     *
     * @param QueryBuilder $queryBuilder
     * @param array $order
     */
    protected function applyOrderBy(QueryBuilder $queryBuilder, array $order): void
    {
        if (!count($order)) {
            return;
        }
        
        $alias = $this->getAlias();
        $metadata = $this->getClassMetadata();
        
        foreach ($order as $key => $direction) {
            // Add alias prefix if no dot present and it's a valid column
            if (is_string($key) && stripos($key, ".") === false && in_array($key, $metadata->getColumnNames())) {
                $key = $alias . "." . $key;
            }
            
            $queryBuilder->addOrderBy($key, $direction);
        }
    }

    /**
     * Build search criteria from keywords (legacy method)
     *
     * @param string $keywords
     * @param array $searchableColumns
     * @return array
     */
    public function buildSearchCriteria(string $keywords, array $searchableColumns = []): array
    {
        if (!count($searchableColumns)) {
            throw new \Exception("No searchable columns specified");
        }

        $keywords = array_filter(explode(" ", trim($keywords)));
        $keywordCriteria = [];

        foreach ($keywords as $keyword) {
            $keywordGroup = [];
            
            foreach ($searchableColumns as $searchColumn) {
                $keywordGroup[] = [$searchColumn, "like", "%" . $keyword . "%"];
            }
            
            $keywordCriteria[] = ["or", $keywordGroup];
        }

        return ["and", $keywordCriteria];
    }

    // Legacy methods for backward compatibility

    /**
     * @deprecated Use findByCriteria() instead
     */
    public function findFiltered(array $filters = [], array $order = [], ?int $limit = null, int $offset = 0): array
    {
        return $this->findByCriteria($filters, $order, $limit, $offset);
    }

    /**
     * @deprecated Use findOneByCriteria() instead
     */
    public function findOneFiltered(array $filters = [], array $order = [], int $offset = 0): ?object
    {
        return $this->findOneByCriteria($filters, $order, $offset);
    }

    /**
     * @deprecated Use countByCriteria() instead
     */
    public function countRows(string $column, array $filters = []): int
    {
        return $this->countByCriteria($filters, $column);
    }
}