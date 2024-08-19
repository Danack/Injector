<?php

namespace DI;

class ConfigException extends InjectorException
{
    /**
     * Add a human readable version of the invalid callable to the standard 'invalid invokable' message.
     */
    public static function fromInvalidCallable(
        $callableOrMethodStr,
        \Exception $previous = null
    ) {

        $message = InjectionException::getInvalidCallableMessage(
            $callableOrMethodStr
        );

        return new self($message, Injector::E_INVOKABLE, $previous);
    }

    public static function staticFactoryAlreadyRegistered($interfaceName)
    {
        $message = sprintf(
            Injector::M_STATIC_FACTORY_DUPLICATE,
            $interfaceName
        );
        return new self($message, Injector::E_STATIC_FACTORY_DUPLICATE);
    }

    public static function fromInvalidStaticFactory($interfaceName, $method, $specificProblem)
    {
        $message = sprintf(
            Injector::M_INVALID_STATIC_FACTORY,
            $interfaceName,
            $method,
            $specificProblem
        );
        return new self($message, Injector::E_INVALID_STATIC_FACTORY);
    }
}
