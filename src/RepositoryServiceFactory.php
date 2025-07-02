<?php

namespace WelshDev\DoctrineBaseRepository;

use WelshDev\DoctrineBaseRepository\Service\CriteriaBuilder;
use WelshDev\DoctrineBaseRepository\Service\FilterManager;
use WelshDev\DoctrineBaseRepository\Service\JoinManager;
use WelshDev\DoctrineBaseRepository\Service\ParameterManager;

/**
 * Factory for creating repository services
 * Provides a convenient way to create configured instances
 */
class RepositoryServiceFactory
{
    private static ?ParameterManager $parameterManager = null;
    private static ?CriteriaBuilder $criteriaBuilder = null;
    private static ?FilterManager $filterManager = null;
    private static ?JoinManager $joinManager = null;

    /**
     * Get a shared ParameterManager instance
     *
     * @return ParameterManager
     */
    public static function getParameterManager(): ParameterManager
    {
        if (self::$parameterManager === null) {
            self::$parameterManager = new ParameterManager();
        }
        
        return self::$parameterManager;
    }

    /**
     * Get a shared CriteriaBuilder instance
     *
     * @return CriteriaBuilder
     */
    public static function getCriteriaBuilder(): CriteriaBuilder
    {
        if (self::$criteriaBuilder === null) {
            self::$criteriaBuilder = new CriteriaBuilder(self::getParameterManager());
        }
        
        return self::$criteriaBuilder;
    }

    /**
     * Get a shared FilterManager instance
     *
     * @return FilterManager
     */
    public static function getFilterManager(): FilterManager
    {
        if (self::$filterManager === null) {
            self::$filterManager = new FilterManager();
        }
        
        return self::$filterManager;
    }

    /**
     * Get a shared JoinManager instance
     *
     * @return JoinManager
     */
    public static function getJoinManager(): JoinManager
    {
        if (self::$joinManager === null) {
            self::$joinManager = new JoinManager();
        }
        
        return self::$joinManager;
    }

    /**
     * Create a new ParameterManager instance (not shared)
     *
     * @return ParameterManager
     */
    public static function createParameterManager(): ParameterManager
    {
        return new ParameterManager();
    }

    /**
     * Create a new CriteriaBuilder instance (not shared)
     *
     * @param ParameterManager|null $parameterManager
     * @return CriteriaBuilder
     */
    public static function createCriteriaBuilder(?ParameterManager $parameterManager = null): CriteriaBuilder
    {
        return new CriteriaBuilder($parameterManager ?? self::createParameterManager());
    }

    /**
     * Create a new FilterManager instance (not shared)
     *
     * @return FilterManager
     */
    public static function createFilterManager(): FilterManager
    {
        return new FilterManager();
    }

    /**
     * Create a new JoinManager instance (not shared)
     *
     * @return JoinManager
     */
    public static function createJoinManager(): JoinManager
    {
        return new JoinManager();
    }

    /**
     * Reset all shared instances (useful for testing)
     */
    public static function reset(): void
    {
        self::$parameterManager = null;
        self::$criteriaBuilder = null;
        self::$filterManager = null;
        self::$joinManager = null;
    }
}