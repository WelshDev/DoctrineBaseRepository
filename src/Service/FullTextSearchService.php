<?php

namespace WelshDev\DoctrineBaseRepository\Service;

use Doctrine\ORM\QueryBuilder;

/**
 * Service for full-text search capabilities
 * Supports PostgreSQL and MySQL full-text search
 */
class FullTextSearchService
{
    /**
     * Add PostgreSQL full-text search to query builder
     *
     * @param QueryBuilder $queryBuilder
     * @param string $searchTerm
     * @param array $searchColumns
     * @param string $alias
     * @param string $language Default language for search
     * @return QueryBuilder
     */
    public function addPostgreSQLFullTextSearch(
        QueryBuilder $queryBuilder,
        string $searchTerm,
        array $searchColumns,
        string $alias,
        string $language = 'english'
    ): QueryBuilder {
        if (empty($searchColumns)) {
            throw new \Exception("Search columns cannot be empty");
        }

        // Build the tsvector expression
        $tsvectorExpr = '';
        foreach ($searchColumns as $i => $column) {
            if ($i > 0) {
                $tsvectorExpr .= ' || ';
            }
            
            $fullColumn = stripos($column, '.') !== false ? $column : $alias . '.' . $column;
            $tsvectorExpr .= "to_tsvector(:language, COALESCE({$fullColumn}, ''))";
        }

        $queryBuilder
            ->andWhere($tsvectorExpr . ' @@ plainto_tsquery(:language, :searchTerm)')
            ->setParameter('language', $language)
            ->setParameter('searchTerm', $searchTerm);

        return $queryBuilder;
    }

    /**
     * Add MySQL full-text search to query builder
     *
     * @param QueryBuilder $queryBuilder
     * @param string $searchTerm
     * @param array $searchColumns
     * @param string $alias
     * @param string $mode Search mode: 'natural', 'boolean', 'expansion'
     * @return QueryBuilder
     */
    public function addMySQLFullTextSearch(
        QueryBuilder $queryBuilder,
        string $searchTerm,
        array $searchColumns,
        string $alias,
        string $mode = 'natural'
    ): QueryBuilder {
        if (empty($searchColumns)) {
            throw new \Exception("Search columns cannot be empty");
        }

        // Build column list
        $columnList = [];
        foreach ($searchColumns as $column) {
            $columnList[] = stripos($column, '.') !== false ? $column : $alias . '.' . $column;
        }

        $columnsExpr = implode(', ', $columnList);
        
        // Build MATCH AGAINST expression based on mode
        switch ($mode) {
            case 'boolean':
                $matchExpr = "MATCH({$columnsExpr}) AGAINST(:searchTerm IN BOOLEAN MODE)";
                break;
            case 'expansion':
                $matchExpr = "MATCH({$columnsExpr}) AGAINST(:searchTerm WITH QUERY EXPANSION)";
                break;
            case 'natural':
            default:
                $matchExpr = "MATCH({$columnsExpr}) AGAINST(:searchTerm IN NATURAL LANGUAGE MODE)";
                break;
        }

        $queryBuilder
            ->andWhere($matchExpr)
            ->setParameter('searchTerm', $searchTerm);

        return $queryBuilder;
    }

    /**
     * Add relevance scoring to query builder (MySQL)
     *
     * @param QueryBuilder $queryBuilder
     * @param string $searchTerm
     * @param array $searchColumns
     * @param string $alias
     * @return QueryBuilder
     */
    public function addRelevanceScoring(
        QueryBuilder $queryBuilder,
        string $searchTerm,
        array $searchColumns,
        string $alias
    ): QueryBuilder {
        if (empty($searchColumns)) {
            throw new \Exception("Search columns cannot be empty");
        }

        // Build column list
        $columnList = [];
        foreach ($searchColumns as $column) {
            $columnList[] = stripos($column, '.') !== false ? $column : $alias . '.' . $column;
        }

        $columnsExpr = implode(', ', $columnList);
        $relevanceExpr = "MATCH({$columnsExpr}) AGAINST(:searchTerm IN NATURAL LANGUAGE MODE)";

        $queryBuilder
            ->addSelect($relevanceExpr . ' AS relevance')
            ->andWhere($relevanceExpr . ' > 0')
            ->orderBy('relevance', 'DESC')
            ->setParameter('searchTerm', $searchTerm);

        return $queryBuilder;
    }
}