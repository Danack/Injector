<?php

namespace DI\Test\Benchmark;

use DI\Injector;

class SlightylyMoreComplicatedBench
{
    private $injector;

    public function __construct()
    {
        $this->injector = new Injector();

        $this->injector->delegate(
            DelegatedClass::class,
            [\DI\Test\Benchmark\DelegatedClass::class, 'create']
        );
        $this->injector->alias(
            AliasedInterface::class,
            AliasedImplementation::class
        );

        $this->injector->share(new SharedInstance('John'));
    }

    /**
     * @Revs(10000)
     */
    public function bench_make_non_trivial_object()
    {
        $this->injector->make(NonTrivial::class);
    }
}
