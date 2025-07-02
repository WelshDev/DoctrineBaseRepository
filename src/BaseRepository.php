<?php

namespace WelshDev\DoctrineBaseRepository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr\Composite;
use Symfony\Component\Uid\Uuid;
use Doctrine\DBAL\ParameterType;
use WelshDev\DoctrineBaseRepository\Contract\SpecificationInterface;
use WelshDev\DoctrineBaseRepository\Contract\QuerySetupInterface;
use WelshDev\DoctrineBaseRepository\Service\CriteriaBuilder;
use WelshDev\DoctrineBaseRepository\Service\FilterManager;
use WelshDev\DoctrineBaseRepository\Service\JoinManager;
use WelshDev\DoctrineBaseRepository\Service\ParameterManager;

class BaseRepository extends EntityRepository
{
	// Legacy properties for backward compatibility
	protected $namedParamCounter = 0;
	protected $joins = array();
	protected $disableJoins = false;
	protected $setupFunction = null;
	protected $filterFunctions = array();

	// New service properties
	protected ?CriteriaBuilder $criteriaBuilder = null;
	protected ?FilterManager $filterManager = null;
	protected ?JoinManager $joinManager = null;
	protected ?ParameterManager $parameterManager = null;
	
	/** @var QuerySetupInterface[] */
	protected array $querySetups = [];

	/**
	 * Get or create the CriteriaBuilder service
	 */
	protected function getCriteriaBuilder(): CriteriaBuilder
	{
		if ($this->criteriaBuilder === null) {
			$this->criteriaBuilder = RepositoryServiceFactory::createCriteriaBuilder($this->getParameterManager());
		}
		return $this->criteriaBuilder;
	}

	/**
	 * Get or create the FilterManager service
	 */
	protected function getFilterManager(): FilterManager
	{
		if ($this->filterManager === null) {
			$this->filterManager = RepositoryServiceFactory::createFilterManager();
		}
		return $this->filterManager;
	}

	/**
	 * Get or create the JoinManager service
	 */
	protected function getJoinManager(): JoinManager
	{
		if ($this->joinManager === null) {
			$this->joinManager = RepositoryServiceFactory::createJoinManager();
		}
		return $this->joinManager;
	}

	/**
	 * Get or create the ParameterManager service
	 */
	protected function getParameterManager(): ParameterManager
	{
		if ($this->parameterManager === null) {
			$this->parameterManager = RepositoryServiceFactory::createParameterManager();
		}
		return $this->parameterManager;
	}

	// New API methods with better naming

	/**
	 * Find entities by criteria array (new API method)
	 *
	 * @param array $criteria
	 * @param array $orderBy
	 * @param int|null $limit
	 * @param int $offset
	 * @return array
	 */
	public function findByCriteria(array $criteria = array(), array $orderBy = array(), ?int $limit = null, int $offset = 0): array
	{
		$queryBuilder = $this->buildQuery($criteria, $orderBy, $limit, $offset);
		$query = $queryBuilder->getQuery();
		
		$this->filterFunctions = [];
		
		return $query->getResult();
	}

	/**
	 * Find one entity by criteria array (new API method)
	 *
	 * @param array $criteria
	 * @param array $orderBy
	 * @param int $offset
	 * @return object|null
	 */
	public function findOneByCriteria(array $criteria = array(), array $orderBy = array(), int $offset = 0): ?object
	{
		$queryBuilder = $this->buildQuery($criteria, $orderBy, 1, $offset);
		$query = $queryBuilder->getQuery();
		
		$this->filterFunctions = [];
		
		return $query->getOneOrNullResult();
	}

	/**
	 * Count entities by criteria array (new API method)
	 *
	 * @param array $criteria
	 * @param string $column
	 * @return int
	 */
	public function countByCriteria(array $criteria = array(), string $column = 'id'): int
	{
		$queryBuilder = $this->buildQuery($criteria);
		$queryBuilder->select('count(' . $this->getEntityAlias() . '.' . $column . ')');
		
		$query = $queryBuilder->getQuery();
		
		return (int) $query->getSingleScalarResult();
	}

	/**
	 * Find entities using a specification
	 *
	 * @param SpecificationInterface $specification
	 * @param array $orderBy
	 * @param int|null $limit
	 * @param int $offset
	 * @return array
	 */
	public function findBySpecification(SpecificationInterface $specification, array $orderBy = array(), ?int $limit = null, int $offset = 0): array
	{
		$alias = $this->getEntityAlias();
		$queryBuilder = $this->createQueryBuilder($alias);
		
		$this->getCriteriaBuilder()->applySpecification($queryBuilder, $specification);
		
		$this->applyOrderBy($queryBuilder, $orderBy);
		
		if ($limit) {
			$queryBuilder->setMaxResults($limit);
		}
		if ($offset) {
			$queryBuilder->setFirstResult($offset);
		}
		
		return $queryBuilder->getQuery()->getResult();
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

	// Legacy methods (preserved for backward compatibility)

	public function addFilterFunction(callable $func)
	{
		$this->filterFunctions[] = $func;
		return $this;
	}

	public function getFilterFunctions()
	{
		return $this->filterFunctions;
	}

	public function disableJoins(bool $disableJoins)
	{
		$this->disableJoins = $disableJoins;
		$this->getJoinManager()->disableJoins($disableJoins);
		return $this;
	}

	public function countRows(string $column, array $filters = array())
	{
		return $this->countByCriteria($filters, $column);
	}

	public function setup(callable $callback)
	{
		$this->setupFunction = $callback;
		return $this;
	}

	public function findFiltered(array $filters = array(), $order = array(), $limit = null, $offset = 0)
	{
		return $this->findByCriteria($filters, $order, $limit, $offset);
	}

	public function findOneFiltered(array $filters = array(), $order = array(), $offset = 0)
	{
		return $this->findOneByCriteria($filters, $order, $offset);
	}

	public function buildQuery(array $filters = array(), $order = array(), $limit = null, $offset = 0, array $opt = [])
	{
		$alias = $this->getEntityAlias();
		
		// Create the query builder
		$queryBuilder = $this->createQueryBuilder($alias)
			->select(array($alias));

		// Apply query setups (new approach)
		foreach ($this->querySetups as $setup) {
			$queryBuilder = $setup->setup($alias, $queryBuilder);
		}

		// Got a setup function? (legacy support)
		if (is_callable($this->setupFunction)) {
			$queryBuilder = call_user_func($this->setupFunction, $alias, $queryBuilder);
			$this->setupFunction = null;
		}

		// Default options
		$opt = array_merge(array(
			'disable_joins' => false
		), $opt);

		// Apply joins using new JoinManager (but maintain legacy join array for compatibility)
		$this->syncLegacyJoins();
		$this->getJoinManager()->applyJoins($queryBuilder, $alias, $opt);

		// Order
		$this->applyOrderBy($queryBuilder, $order);

		// Limit
		if ($limit)
			$queryBuilder->setMaxResults($limit);

		// Offset
		if ($offset)
			$queryBuilder->setFirstResult($offset);

		// Apply filters using new FilterManager and legacy filter functions
		$this->getFilterManager()->applyFilters($queryBuilder, static::class, $this->filterFunctions);

		$queryBuilder->addGroupBy($alias . ".id");

		// Apply criteria using new CriteriaBuilder
		if (count($filters)) {
			$this->getCriteriaBuilder()->applyCriteria($queryBuilder, $filters, $alias);
		}

		return $queryBuilder;
	}

	// Keep the original addCriteria method for any direct usage (marked deprecated)
	
	/**
	 * @deprecated Use CriteriaBuilder service instead
	 */
	public function addCriteria(QueryBuilder $queryBuilder, Composite $expr, array $criteria)
	{
		return $this->getCriteriaBuilder()->buildCriteria($queryBuilder, $expr, $criteria, $this->getEntityAlias());
	}

	/**
	 * @deprecated Use ParameterManager service instead
	 */
	public function createNamedParameter(QueryBuilder $queryBuilder, $value)
	{
		// Use legacy counter for backward compatibility
		$this->namedParamCounter++;
		$placeHolder = ':paramValue' . $this->namedParamCounter;
		$queryBuilder->setParameter(substr($placeHolder, 1), $this->prepareValue($value));
		return $placeHolder;
	}

	public function prepareValue($value)
	{
		return $this->getParameterManager()->prepareValue($value);
	}

	public function buildSearchCriteria(string $keywords, array $searchableColumns = array())
	{
		// Got no searchable columns
		if (!count($searchableColumns))
			throw new \Exception("No searchable columns specified");

		// Explode individual keywords
		$keywords = array_filter(explode(" ", trim($keywords)));

		// Hold the keyword criteria
		$keywordCriteria = array();

		// Loop keywords
		foreach ($keywords as $someKeyword)
		{
			// Grab this group
			$keywordGroup = array();

			// Loop search columns
			foreach ($searchableColumns as $searchColumn)
			{
				// Grab it
				$keywordGroup[] = array($searchColumn, "like", "%" . $someKeyword . "%");
			}

			// Add this group the main array
			$keywordCriteria[] = array("or", $keywordGroup);
		}

		// Return the 'and' array
		return array("and", $keywordCriteria);
	}

	/**
	 * Get entity alias for queries
	 */
	protected function getEntityAlias(): string
	{
		if (property_exists($this, 'alias') && $this->alias) {
			return $this->alias;
		}
		
		$className = $this->getClassName();
		$parts = explode('\\', $className);
		$shortName = end($parts);
		return strtolower(substr($shortName, 0, 1));
	}

	/**
	 * Apply order by clauses to query builder
	 */
	protected function applyOrderBy(QueryBuilder $queryBuilder, array $order): void
	{
		if (!count($order)) {
			return;
		}
		
		$alias = $this->getEntityAlias();
		$metadata = $this->getClassMetadata();
		
		foreach ($order as $key => $val) {
			// Not got a dot, prefix table alias
			if (is_string($key) && stripos($key, ".") === false && in_array($key, $metadata->getColumnNames())) {
				$key = $alias . "." . $key;
			}
			
			$queryBuilder->addOrderBy($key, $val);
		}
	}

	/**
	 * Sync legacy joins array with new JoinManager
	 */
	protected function syncLegacyJoins(): void
	{
		$joinManager = $this->getJoinManager();
		
		// Clear existing joins in manager
		$joinManager->clearJoins();
		
		// Add legacy joins to manager
		foreach ($this->joins as $join) {
			if (count($join) >= 3) {
				list($joinType, $joinColumn, $joinTable) = $join;
				$joinManager->addJoin($joinType, $joinColumn, $joinTable);
			}
		}
	}
}
