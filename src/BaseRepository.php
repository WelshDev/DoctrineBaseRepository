<?php

namespace WelshDev\DoctrineBaseRepository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr\Composite;

class BaseRepository extends EntityRepository
{
    protected $namedParamCounter = 0;
    protected $joins = array();

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

    public function buildQuery(array $filters = array(), $order = array(), $limit = null, $offset = 0)
    {
		// Create the query builder
		$queryBuilder = $this->createQueryBuilder($this->alias)
			->select(array(
				$this->alias
            ));

        // Any joins?
        if(count($this->joins))
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
            // Is this a basic non-associative array (non-numeric keys and not special (or/and))
            if(count(array_filter(array_keys($filters), 'is_string')) && !in_array("or", array_keys($filters)) && !in_array("and", array_keys($filters)))
            {
                // Some array keys are not numeric which means this is a traditional (and simple) key => value lookup

                // Store the simple lookups in a structure compatible with the criteria builder
                $structuredFilterArray = array();

                // Loop each of the lookup elements
                foreach($filters AS $key => $val)
                {
                    // Build the array in the correct manner
                    $structuredFilterArray[] = array($key, "eq", $val);
                }

                // Add the where
                $queryBuilder->where($this->addCriteria($queryBuilder, $queryBuilder->expr()->andX(), $structuredFilterArray));
            }
            else
            {
                // All array keys are numeric which means this is a criteria driven lookup

                // Add the where
                $queryBuilder->where($this->addCriteria($queryBuilder, $queryBuilder->expr()->andX(), $filters));
            }
        }

        return $queryBuilder;
    }

    public function addCriteria(QueryBuilder $queryBuilder, Composite $expr, array $criteria)
    {
        $em = $this->getEntityManager();

        if(count($criteria))
        {
			foreach($criteria AS $expression => $comparison)
			{
                // Or
                if($expression === 'or')
                    $expr->add($this->addCriteria($queryBuilder, $queryBuilder->expr()->orX(), $comparison));
                // And
                elseif($expression === 'and')
                    $expr->add($this->addCriteria($queryBuilder, $queryBuilder->expr()->andX(), $comparison));
                // Something else
                else
                {
                    // Extract
                    if(count($comparison) == 3)
                        list($field, $operator, $value) = $comparison;
                    else
                    {
                        list($field, $operator) = $comparison;

                        // Default value of true
                        $value = true;
                    }

                    // Not got a dot, prefix table alias
                    if(stripos($field, ".") === false)
                        $field = $this->alias . "." . $field;

                    // Basic operators
                    if(in_array($operator, array("eq", "neq", "gt", "gte", "lt", "lte")))
                    {
                        // Array
                        if(is_array($value))
                        {
                            // Not supported
                            throw new \Exception("Array lookups are not supported!");
                        }
                        // DateTime
                        elseif(is_object($value) && $value instanceof \DateTime)
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
                    // Null operators
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
                            // We therefore loop the values and built the SQL string

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

                            // Implode into full array
                            $fullSQL = "(" . implode($builtArraySQL, ' AND ') . ")";

                            // Add it
                            $expr->add($fullSQL);
                        }
                    }
                    // Unsupported operator
                    else
                        throw new \Exception("Unsupported operator: " . $operator);
                }
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
        {
            return $value;
        }
    }
}
