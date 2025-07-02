<?php

namespace WelshDev\DoctrineBaseRepository\Specification;

use Doctrine\ORM\QueryBuilder;
use WelshDev\DoctrineBaseRepository\Contract\SpecificationInterface;

/**
 * Example specification for date range filtering
 */
class DateRangeSpecification implements SpecificationInterface
{
    private ?\DateTime $startDate;
    private ?\DateTime $endDate;
    private string $dateColumn;

    public function __construct(?\DateTime $startDate, ?\DateTime $endDate, string $dateColumn = 'createdAt')
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->dateColumn = $dateColumn;
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        $aliases = $queryBuilder->getRootAliases();
        $rootAlias = $aliases[0];
        
        if ($this->startDate) {
            $queryBuilder
                ->andWhere($queryBuilder->expr()->gte($rootAlias . '.' . $this->dateColumn, ':startDate'))
                ->setParameter('startDate', $this->startDate);
        }
        
        if ($this->endDate) {
            $queryBuilder
                ->andWhere($queryBuilder->expr()->lte($rootAlias . '.' . $this->dateColumn, ':endDate'))
                ->setParameter('endDate', $this->endDate);
        }
    }
}