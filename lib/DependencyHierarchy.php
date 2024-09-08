<?php

namespace DI;

/**
 * An interface to allow factory objects/functions to retrieve the
 * current hierarchy of types of objects being created. This enables
 * them to create a specific type based on which type is
 * dependent on it.
 */
interface DependencyHierarchy
{
    /**
     * Get the hierarch of object types that the injector is currently in
     * progress of making. For reasons, the array returned will have the
     * type names as the key, and the 'level' as the value. e.g.
     *
     * array(3) {
     *   ["foo"] => int(0),
     *   ["bar"] => int(1),
     *   ["logger"] => int(2)
     * }
     *
     * @return mixed
     */
    public function getInProgressMakes();
}