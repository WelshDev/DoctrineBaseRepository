<?php

namespace WelshDev\DoctrineBaseRepository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr\Composite;

class BaseRepository extends EntityRepository
{
	protected $namedParamCounter = 0;
	protected $joins = array();
	protected $disableJoins = false;

	public function disableJoins(bool $disableJoins)
	{
		$this->disableJoins = $disableJoins;

		return $this;
	}

	public function countRows(string $column, array $filters = array())
	{
		// Get query builder
		$queryBuilder = $this->buildQuery($filters);

		// Select the count
		$queryBuilder->select('count(' . $column . ')');

		// Get the query
		$query = $queryBuilder->getQuery();

		return $query->getSingleScalarResult();

	}

	public function findFiltered(array $filters = array(), $order = array(), $limit = null, $offset = 0)
	{
		// Get query builder
		$queryBuilder = $this->buildQuery($filters, $order, $limit, $offset);

		// Get the query
		$query = $queryBuilder->getQuery();

		// Execute and return
		return $query->getResult();
	}

	public function findOneFiltered(array $filters = array(), $order = array(), $offset = 0)
	{
		// Get query builder
		$queryBuilder = $this->buildQuery($filters, $order, 1, $offset);

		// Get the query
		$query = $queryBuilder->getQuery();

		// Execute and return
		return $query->getOneOrNullResult();
	}

	public function buildQuery(array $filters = array(), $order = array(), $limit = null, $offset = 0, array $opt = [])
	{
		// Create the query builder
		$queryBuilder = $this->createQueryBuilder($this->alias)
			->select(array(
				$this->alias
			));

		// Defaults options
		$opt = array_merge(array(
			'disable_joins' => false
		), $opt);

		// Any joins?
		if(count($this->joins) && !$opt['disable_joins'] && !$this->disableJoins)
		{
			// Loop joins
			foreach($this->joins AS $someJoin)
			{
				list($joinType, $joinColumn, $joinTable) = $someJoin;

				// Not got a dot, prefix table alias
				if(stripos($joinColumn, ".") === false)
					$joinColumn = $this->alias . "." . $joinColumn;

				// Join
				$queryBuilder->{$joinType}($joinColumn, $joinTable);
			}
		}

		// Order
		if(count($order))
		{
			// Loop columns to order
			foreach($order AS $key => $val)
			{
				// Not got a dot, prefix table alias
				if(stripos($key, ".") === false)
					$key = $this->alias . "." . $key;

				$queryBuilder->addOrderBy($key, $val);
			}
		}

		// Limit
		if($limit)
			$queryBuilder->setMaxResults($limit);

		// Offset
		if($offset)
			$queryBuilder->setFirstResult($offset);

		// Got any filters?
		if(count($filters))
		{
			// Add the where
			$queryBuilder->where($this->addCriteria($queryBuilder, $queryBuilder->expr()->andX(), $filters));
		}

		return $queryBuilder;
	}

	public function addCriteria(QueryBuilder $queryBuilder, Composite $expr, array $criteria)
	{
		$em = $this->getEntityManager();

		if(count($criteria))
		{
			foreach($criteria AS $k => $v)
			{
				// Numeric (i.e. it's being passed in as an operator e.g. ["id", "eq", 999])
				if(is_numeric($k))
				{
					// Not an array
					if(!is_array($v))
						throw new \Exception("Non-indexed criteria must be in array form e.g. ['id', 'eq', 1234]");

					// Extract
					if(count($v) == 3)
						list($field, $operator, $value) = $v;
					else
					{
						list($field, $operator) = $v;

						// Default value of true
						$value = true;
					}

					// Is this a special case i.e. or/and
					if(in_array($field, array("or", "and")))
					{
						// Move things around
						$value = $operator;
						$operator = $field;

						// Field is no longer used
						$field = null;
					}
				}
				// Indexed (e.g. ["id" => 1234])
				else
				{
					// Is the value an array?
					if(is_array($v))
						throw new \Exception("Indexed criteria does not support array values");

					// Is the value null?
					if(is_null($v))
					{
						// Use "is_null" operator
						$field = $k;
						$operator = "is_null";
						$value = true;
					}
					else
					{
						// Default to "eq" operator
						$field = $k;
						$operator = "eq";
						$value = $v;
					}
				}

				// Not got a dot, prefix table alias
				if(stripos($field, ".") === false)
					$field = $this->alias . "." . $field;

				// Or
				if($operator === 'or')
					$expr->add($this->addCriteria($queryBuilder, $queryBuilder->expr()->orX(), $value));
				// And
				elseif($operator === 'and')
					$expr->add($this->addCriteria($queryBuilder, $queryBuilder->expr()->andX(), $value));
				// Basic operators
				elseif(in_array($operator, array("eq", "neq", "gt", "gte", "lt", "lte", "like")))
				{
					// Arrays not supported for this operator
					if(is_array($value))
						throw new \Exception("Array lookups are not supported for the '" . $operator . "' operator");

					// DateTime
					if(is_object($value) && $value instanceof \DateTime)
					{
						$expr->add($queryBuilder->expr()->{$operator}($field, $this->createNamedParameter($queryBuilder, $this->prepareValue($value))));
					}
					// Other object (likely an association)
					elseif(is_object($value))
					{
						$expr->add($queryBuilder->expr()->{$operator}($field, $this->createNamedParameter($queryBuilder, $this->prepareValue($value))));
					}
					// Is it null?
					elseif(is_null($value))
					{
						$expr->add($queryBuilder->expr()->isNull($field));
					}
					else
					{
						// Literal
						$expr->add($queryBuilder->expr()->{$operator}($field, $this->createNamedParameter($queryBuilder, $this->prepareValue($value))));
					}
				}
				// Null operator
				elseif(in_array($operator, array("is_null", "not_null")))
				{
					// Is null
					if($operator == "is_null")
					{
						// True or false value?
						if($value)
							$expr->add($queryBuilder->expr()->isNull($field));
						else
							$expr->add($queryBuilder->expr()->isNotNull($field));
					}
					// Not null
					elseif($operator == "not_null")
					{
						// True or false value?
						if($value)
							$expr->add($queryBuilder->expr()->isNotNull($field));
						else
							$expr->add($queryBuilder->expr()->isNull($field));
					}
				}
				// In/NotIn operators
				elseif(in_array($operator, array("in", "not_in")))
				{
					// Make sure it's an array
					if(!is_array($value))
						throw new \Exception("Invalid value for operator: " . $operator);

					// In
					if($operator == "in")
						$expr->add($queryBuilder->expr()->in($field, $this->createNamedParameter($queryBuilder, $this->prepareValue($value))));
					// Not in
					elseif($operator == "not_in")
					{
						// Need to use multiple != operations because "NOT IN" is not null-safe
						// We therefore loop the values and build the SQL string

						// Hold the array
						$builtArraySQL = array();

						// Loop the values
						foreach($this->prepareValue($value) AS $someValue)
						{
							// Is it null?
							if(is_null($someValue))
							{
								// Make sure we don't return if null
								$builtArraySQL[] = '(' . $field . ' IS NOT NULL)';
							}
							else
							{
								// Where (field = value OR field IS NULL)
								// This is done because != is not null safe and would therefore not return anything with null values
								$builtArraySQL[] = '(' . $field . ' != ' . $this->createNamedParameter($queryBuilder, $someValue) . ' OR ' . $field . ' IS NULL)';
							}
						}

						// Got anything?
						if(count($builtArraySQL))
						{
							// Implode into full array
							$fullSQL = "(" . implode($builtArraySQL, ' AND ') . ")";

							// Add it
							$expr->add($fullSQL);
						}
					}
				}
				// Unsupported operator
				else
					throw new \Exception("Unsupported operator: " . $operator);
			}
		}
		else
			throw new \Exception("Empty criteria");

		return $expr;
	}

	public function createNamedParameter(QueryBuilder $queryBuilder, $value)
	{
		// Increase count
		$this->namedParamCounter++;

		// Create the new placeholder
		$placeHolder = ':paramValue' . $this->namedParamCounter;

		// Set the parameter
		$queryBuilder->setParameter(substr($placeHolder, 1), $value);

		return $placeHolder;
	}

	public function prepareValue($value)
	{
		// DateTime
		if(is_object($value) && $value instanceof \DateTime)
		{
			return $value->format('Y-m-d H:i:s');
		}
		// Object
		elseif(is_object($value))
		{
			return $value;
		}
		// Array
		elseif(is_array($value))
		{
			// Loop
			foreach($value AS $k => $v)
			{
				// Prepare it
				$value[$k] = $this->prepareValue($v);
			}

			return $value;
		}
		// Anything else
		else
			return $value;
	}

	public function buildSearchCriteria(string $keywords, array $searchableColumns = array())
	{
		// Not no searchable columns
		if(!count($searchableColumns))
			throw new \Exception("No searchable columns specified");

		// Explode individual keywords
		$keywords = array_filter(explode(" ", trim($keywords)));

		// Hold the keyword criteria
		$keywordCriteria = array();

		// Loop keywords
		foreach($keywords AS $someKeyword)
		{
			// Grab this group
			$keywordGroup = array();

			// Loop search columns
			foreach($searchableColumns AS $searchColumn)
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
}
