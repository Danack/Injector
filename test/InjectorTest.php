<?php

namespace DI\Test;

use DI\Injector;
use DI\InjectionException;

class InjectorTest extends BaseTest
{
    public function testArrayTypeDoesNotEvaluatesAsClass()
    {
        $injector = new Injector;
        $injector->defineParam('parameter', []);
        $injector->execute('DI\Test\hasArrayDependency');
    }

    public function testMakeInstanceInjectsSimpleConcreteDependency()
    {
        $injector = new Injector;
        $this->assertEquals(new TestNeedsDep(new TestDependency),
            $injector->make('DI\Test\TestNeedsDep')
        );
    }

    public function testMakeInstanceReturnsNewInstanceIfClassHasNoConstructor()
    {
        $injector = new Injector;
        $this->assertEquals(new TestNoConstructor, $injector->make('DI\Test\TestNoConstructor'));
    }

    public function testMakeInstanceReturnsAliasInstanceOnNonConcreteType()
    {
        $injector = new Injector;
        $injector->alias('DI\Test\DepInterface', 'DI\Test\DepImplementation');
        $this->assertEquals(new DepImplementation, $injector->make('DI\Test\DepInterface'));
    }

    public function testMakeInstanceThrowsExceptionOnInterfaceWithoutAlias()
    {
        $this->expectException(\DI\InjectionException::class);
        $this->expectExceptionMessage("Injection definition required for interface DI\Test\DepInterface");
        $this->expectExceptionCode(\DI\Injector::E_NEEDS_DEFINITION);
        $injector = new Injector;
        $injector->make('DI\Test\DepInterface');
    }

    public function testMakeInstanceThrowsExceptionOnNonConcreteCtorParamWithoutImplementation()
    {
        $this->expectException(\DI\InjectionException::class);
        $this->expectExceptionMessage("Injection definition required for interface DI\Test\DepInterface");
        $this->expectExceptionCode(\DI\Injector::E_NEEDS_DEFINITION);

        $injector = new Injector;
        $injector->make('DI\Test\RequiresInterface');
    }

    public function testMakeInstanceBuildsNonConcreteCtorParamWithAlias()
    {
        $injector = new Injector;
        $injector->alias('DI\Test\DepInterface', 'DI\Test\DepImplementation');
        $obj = $injector->make('DI\Test\RequiresInterface');
        $this->assertInstanceOf('DI\Test\RequiresInterface', $obj);
    }

    public function testMakeInstancePassesNullCtorParameterIfNoTypeOrDefaultCanBeDetermined()
    {
        $injector = new Injector;
        $nullCtorParamObj = $injector->make('DI\Test\ProvTestNoDefinitionNullDefaultClass');
        $this->assertEquals(new ProvTestNoDefinitionNullDefaultClass, $nullCtorParamObj);
        $this->assertNull($nullCtorParamObj->arg);
    }

    public function testMakeInstanceReturnsSharedInstanceIfAvailable()
    {
        $injector = new Injector;
        $injector->define('DI\Test\RequiresInterface', array('dep' => 'DI\Test\DepImplementation'));
        $injector->share('DI\Test\RequiresInterface');
        $injected = $injector->make('DI\Test\RequiresInterface');

        $this->assertEquals('something', $injected->testDep->testProp);
        $injected->testDep->testProp = 'something else';

        $injected2 = $injector->make('DI\Test\RequiresInterface');
        $this->assertEquals('something else', $injected2->testDep->testProp);
    }

    public function testMakeInstanceThrowsExceptionOnClassLoadFailure()
    {
        $classname = 'ClassThatDoesntExist';
        if (PHP_VERSION_ID >= 80000) {
            $classname = "\"" . $classname . "\"";
        }

        $this->expectException(\DI\InjectorException::class);
        $this->expectExceptionMessage("Could not make ClassThatDoesntExist: Class $classname does not exist");

        $injector = new Injector;
        $injector->make('ClassThatDoesntExist');
    }

    public function testMakeInstanceUsesCustomDefinitionIfSpecified()
    {
        $injector = new Injector;
        $injector->define('DI\Test\TestNeedsDep', array('testDep'=>'DI\Test\TestDependency'));
        $injected = $injector->make('DI\Test\TestNeedsDep', array('testDep'=>'DI\Test\TestDependency2'));
        $this->assertEquals('testVal2', $injected->testDep->testProp);
    }

    /**
     * @group deadish
     */
    public function testMakeInstanceCustomDefinitionOverridesExistingDefinitions()
    {
        $injector = new Injector;
        $injector->define('DI\Test\InjectorTestChildClass', array(':arg1'=>'First argument', ':arg2'=>'Second argument'));
        $injected = $injector->make('DI\Test\InjectorTestChildClass', array(':arg1'=>'Override'));
        $this->assertEquals('Override', $injected->arg1);
        $this->assertEquals('Second argument', $injected->arg2);
    }

    public function testMakeInstanceStoresShareIfMarkedWithNullInstance()
    {
        $injector = new Injector;
        $injector->share('DI\Test\TestDependency');
        $injector->make('DI\Test\TestDependency');
    }

    public function testMakeInstanceUsesReflectionForUnknownParamsInMultiBuildWithDeps()
    {
        $injector = new Injector;
        $obj = $injector->make('DI\Test\TestMultiDepsWithCtor', array('val1'=>'DI\Test\TestDependency'));
        $this->assertInstanceOf('DI\Test\TestMultiDepsWithCtor', $obj);

        $obj = $injector->make('DI\Test\NoTypeNoDefaultConstructorClass',
            array('val1'=>'DI\Test\TestDependency')
        );
        $this->assertInstanceOf('DI\Test\NoTypeNoDefaultConstructorClass', $obj);
        $this->assertNull($obj->testParam);
    }

    /**
     * @requires PHP 5.6
     */
    public function testMakeInstanceUsesReflectionForUnknownParamsInMultiBuildWithDepsAndVariadics()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped("HHVM doesn't support variadics with type declarations.");
        }

        require_once __DIR__ . "/fixtures_5_6.php";

        $injector = new Injector;
        $obj = $injector->make('DI\Test\NoTypeNoDefaultConstructorVariadicClass',
            array('val1'=>'DI\Test\TestDependency')
        );
        $this->assertInstanceOf('DI\Test\NoTypeNoDefaultConstructorVariadicClass', $obj);
        $this->assertEquals(array(), $obj->testParam);
    }

    /**
     * @requires PHP 5.6
     */
    public function testMakeInstanceUsesReflectionForUnknownParamsWithDepsAndVariadicsWithType()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped("HHVM doesn't support variadics with type declarations.");
        }

        require_once __DIR__ . "/fixtures_5_6.php";

        $injector = new Injector;
        $obj = $injector->make('DI\Test\TypeNoDefaultConstructorVariadicClass',
            array('arg'=>'DI\Test\TestDependency')
        );
        $this->assertInstanceOf('DI\Test\TypeNoDefaultConstructorVariadicClass', $obj);
        $this->assertIsArray($obj->testParam);
        $this->assertInstanceOf('DI\Test\TestDependency', $obj->testParam[0]);
    }

    public function testMakeInstanceThrowsExceptionOnUntypedParameterWithoutDefinitionOrDefault()
    {
        $this->expectException(\DI\InjectionException::class);
        // TODO - why does this message end with double-colon?
        $this->expectExceptionMessage('No definition available to provision typeless parameter $val at position 0 in DI\Test\InjectorTestCtorParamWithNoTypeOrDefault::__construct() declared in DI\Test\InjectorTestCtorParamWithNoTypeOrDefault::');
        $this->expectExceptionCode(\DI\Injector::E_UNDEFINED_PARAM);

        $injector = new Injector;
        $injector->make('DI\Test\InjectorTestCtorParamWithNoTypeOrDefault');
    }

    public function testbuildArgFromReflParamCoverageNonClassCase()
    {
        $injector= new Injector;

        $this->expectExceptionCode(\DI\Injector::E_UNDEFINED_PARAM);
        $injector->execute('DI\Test\aFunctionWithAParam');
    }

    public function testMakeInstanceThrowsExceptionOnUntypedParameterWithoutDefinitionOrDefaultThroughAliasedType()
    {
        $this->expectException(\DI\InjectionException::class);
        // TODO - why does this message end with double-colon?
        $this->expectExceptionMessage('No definition available to provision typeless parameter $val at position 0 in DI\Test\InjectorTestCtorParamWithNoTypeOrDefault::__construct() declared in DI\Test\InjectorTestCtorParamWithNoTypeOrDefault::');
        $this->expectExceptionCode(\DI\Injector::E_UNDEFINED_PARAM);

        $injector = new Injector;
        $injector->alias('DI\Test\TestNoExplicitDefine', 'DI\Test\InjectorTestCtorParamWithNoTypeOrDefault');
        $injector->make('DI\Test\InjectorTestCtorParamWithNoTypeOrDefaultDependent');
    }

    public function testMakeInstanceThrowsExceptionOnUninstantiableTypeWithoutDefinition()
    {
        $this->expectException(\DI\InjectorException::class);
        $this->expectExceptionMessage("Injection definition required for interface DI\Test\DepInterface");

        $injector = new Injector;
        $injector->make('DI\Test\RequiresInterface');
    }

    public function testTypelessDefineForDependency()
    {
        $thumbnailSize = 128;
        $injector = new Injector;
        $injector->defineParam('thumbnailSize', $thumbnailSize);
        $testClass = $injector->make('DI\Test\RequiresDependencyWithTypelessParameters');
        $this->assertEquals($thumbnailSize, $testClass->getThumbnailSize(), 'Typeless define was not injected correctly.');
    }

    public function testTypelessDefineForAliasedDependency()
    {
        $injector = new Injector;
        $injector->defineParam('val', 42);

        $injector->alias('DI\Test\TestNoExplicitDefine', 'DI\Test\ProviderTestCtorParamWithNoTypeOrDefault');
        $obj = $injector->make('DI\Test\ProviderTestCtorParamWithNoTypeOrDefaultDependent');
    }

    /**
     * @group deadish
     */
    public function testMakeInstanceInjectsRawParametersDirectly()
    {
        $injector = new Injector;
        $injector->define('DI\Test\InjectorTestRawCtorParams', array(
            ':string' => 'string',
            ':obj' => new \StdClass,
            ':int' => 42,
            ':array' => array(),
            ':float' => 9.3,
            ':bool' => true,
            ':null' => null,
        ));

        $obj = $injector->make('DI\Test\InjectorTestRawCtorParams');
        $this->assertIsString($obj->string);
        $this->assertInstanceOf('StdClass', $obj->obj);
        $this->assertIsInt($obj->int);
        $this->assertIsArray($obj->array);
        $this->assertIsFloat($obj->float);
        $this->assertIsBool($obj->bool);
        $this->assertNull($obj->null);
    }

    public function testMakeInstanceThrowsExceptionWhenDelegateDoes()
    {
        $injector= new Injector;

        $callable = $this->createPartialMock(
            'DI\Test\CallableMock',
            array('__invoke')
        );

        $injector->delegate('TestDependency', $callable);

        $message = "This is the expected exception.";
        $callable->expects($this->once())
            ->method('__invoke')
            ->will($this->throwException(new \Exception($message)));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($message);

        $injector->make('TestDependency');
    }

    public function testMakeInstanceHandlesNamespacedClasses()
    {
        $injector = new Injector;
        $injector->make('DI\Test\SomeClassName');
    }

    public function testMakeInstanceDelegate()
    {
        $injector= new Injector;

        $callable = $this->createPartialMock(
            'DI\Test\CallableMock',
            array('__invoke')
        );

        $callable->expects($this->once())
            ->method('__invoke')
            ->will($this->returnValue(new TestDependency()));

        $injector->delegate('DI\Test\TestDependency', $callable);

        $obj = $injector->make('DI\Test\TestDependency');

        $this->assertInstanceOf('DI\Test\TestDependency', $obj);
    }

    public function testMakeInstanceWithStringDelegate()
    {
        $injector= new Injector;
        $injector->delegate('StdClass', 'DI\Test\StringStdClassDelegateMock');
        $obj = $injector->make('StdClass');
        $this->assertEquals(42, $obj->test);
    }

    public function testMakeInstanceThrowsExceptionIfStringDelegateClassHasNoInvokeMethod()
    {
        $injector= new Injector;

        $this->expectException(\DI\ConfigException::class);
        $this->expectExceptionMessage("DI\\Injector::delegate expects a valid callable or executable class::method string at Argument 2 but received 'StringDelegateWithNoInvokeMethod'");

        $injector->delegate('StdClass', 'StringDelegateWithNoInvokeMethod');
    }

    public function testMakeInstanceThrowsExceptionIfStringDelegateClassInstantiationFails()
    {
        $this->expectException(\DI\ConfigException::class);
        $this->expectExceptionMessage("DI\\Injector::delegate expects a valid callable or executable class::method string at Argument 2 but received 'SomeClassThatDefinitelyDoesNotExistForReal'");

        $injector= new Injector;
        $injector->delegate('StdClass', 'SomeClassThatDefinitelyDoesNotExistForReal');
    }

    public function testMakeInstanceThrowsExceptionOnUntypedParameterWithNoDefinition()
    {
        $this->expectException(\DI\InjectionException::class);
        $this->expectExceptionMessage('Injection definition required for interface DI\Test\DepInterface');

        $injector = new Injector;
        $injector->make('DI\Test\RequiresInterface');
    }

    public function testDefineAssignsPassedDefinition()
    {
        $injector = new Injector;
        $definition = array('dep' => 'DI\Test\DepImplementation');
        $injector->define('DI\Test\RequiresInterface', $definition);
        $this->assertInstanceOf('DI\Test\RequiresInterface', $injector->make('DI\Test\RequiresInterface'));
    }

    public function testShareStoresSharedInstanceAndReturnsCurrentInstance()
    {
        $injector = new Injector;
        $testShare = new \StdClass;
        $testShare->test = 42;

        $this->assertInstanceOf('DI\Injector', $injector->share($testShare));
        $testShare->test = 'test';
        $this->assertEquals('test', $injector->make('stdclass')->test);
    }

    public function testShareMarksClassSharedOnNullObjectParameter()
    {
        $injector = new Injector;
        $this->assertInstanceOf('DI\Injector', $injector->share('SomeClass'));
    }

    public function testShareThrowsExceptionOnInvalidArgument()
    {
        $this->expectException(\DI\ConfigException::class);
        $this->expectExceptionMessage('DI\Injector::share() requires a string class name or object instance at Argument 1; integer specified');

        $injector = new Injector;
        $injector->share(42);
    }

    public function testAliasAssignsValueAndReturnsCurrentInstance()
    {
        $injector = new Injector;
        $this->assertInstanceOf('DI\Injector', $injector->alias('DepInterface', 'DI\Test\DepImplementation'));
    }

    public function provideInvalidDelegates()
    {
        return array(
            array(new \StdClass),
            array(42),
            array(true)
        );
    }

    /**
     * @dataProvider provideInvalidDelegates
     */
    public function testDelegateThrowsExceptionIfDelegateIsNotCallableOrString($badDelegate)
    {
        $this->expectException(\DI\ConfigException::class);
        $this->expectExceptionMessage('DI\Injector::delegate expects a valid callable or executable class::method string at Argument 2');

        $injector = new Injector;
        $injector->delegate('DI\Test\TestDependency', $badDelegate);
    }

    public function testDelegateInstantiatesCallableClassString()
    {
        $injector = new Injector;
        $injector->delegate('DI\Test\MadeByDelegate', 'DI\Test\CallableDelegateClassTest');
        $this->assertInstanceof('DI\Test\MadeByDelegate', $injector->make('DI\Test\MadeByDelegate'));
    }

    public function testDelegateInstantiatesCallableClassArray()
    {
        $injector = new Injector;
        $injector->delegate('DI\Test\MadeByDelegate', array('DI\Test\CallableDelegateClassTest', '__invoke'));
        $this->assertInstanceof('DI\Test\MadeByDelegate', $injector->make('DI\Test\MadeByDelegate'));
    }

    public function testUnknownDelegationFunction()
    {
        $injector = new Injector;
        try {
            $injector->delegate('DI\Test\DelegatableInterface', 'FunctionWhichDoesNotExist');
            $this->fail("Delegation was supposed to fail.");
        } catch (\DI\InjectorException $ie) {
            $this->assertStringContainsString(
                'FunctionWhichDoesNotExist',
                $ie->getMessage()
            );
            $this->assertEquals(\DI\Injector::E_DELEGATE_ARGUMENT, $ie->getCode());
        }
    }

    public function testUnknownDelegationMethod()
    {
        $injector = new Injector;
        try {
            $injector->delegate('DI\Test\DelegatableInterface', array('stdClass', 'methodWhichDoesNotExist'));
            $this->fail("Delegation was supposed to fail.");
        } catch (\DI\InjectorException $ie) {
            $this->assertStringContainsString(
                'stdClass',
                $ie->getMessage()
            );
            $this->assertStringContainsString(
                'methodWhichDoesNotExist',
                $ie->getMessage()
            );
            $this->assertEquals(\DI\Injector::E_DELEGATE_ARGUMENT, $ie->getCode());
        }
    }

    /**
     * @dataProvider provideExecutionExpectations
     * @group deadish
     */
    public function testProvisionedInvokables($toInvoke, $definition, $expectedResult)
    {
        $injector = new Injector;
        $this->assertEquals($expectedResult, $injector->execute($toInvoke, $definition));
    }

    public function provideExecutionExpectations()
    {
        $return = array();

        // 0 -------------------------------------------------------------------------------------->

        $toInvoke = array('DI\Test\ExecuteClassNoDeps', 'execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 1 -------------------------------------------------------------------------------------->

        $toInvoke = array(new ExecuteClassNoDeps, 'execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 2 -------------------------------------------------------------------------------------->

        $toInvoke = array('DI\Test\ExecuteClassDeps', 'execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 3 -------------------------------------------------------------------------------------->

        $toInvoke = array(new ExecuteClassDeps(new TestDependency), 'execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 4 -------------------------------------------------------------------------------------->

        $toInvoke = array('DI\Test\ExecuteClassDepsWithMethodDeps', 'execute');
        $args = array(':arg' => 9382);
        $expectedResult = 9382;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 5 -------------------------------------------------------------------------------------->

        $toInvoke = array('DI\Test\ExecuteClassStaticMethod', 'execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 6 -------------------------------------------------------------------------------------->

        $toInvoke = array(new ExecuteClassStaticMethod, 'execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 7 -------------------------------------------------------------------------------------->

        $toInvoke = 'DI\Test\ExecuteClassStaticMethod::execute';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 8 -------------------------------------------------------------------------------------->

        $toInvoke = array('DI\Test\ExecuteClassRelativeStaticMethod', 'parent::execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 9 -------------------------------------------------------------------------------------->

        $toInvoke = 'DI\Test\testExecuteFunction';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 10 ------------------------------------------------------------------------------------->

        $toInvoke = function () { return 42; };
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 11 ------------------------------------------------------------------------------------->

        $toInvoke = new ExecuteClassInvokable;
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 12 ------------------------------------------------------------------------------------->

        $toInvoke = 'DI\Test\ExecuteClassInvokable';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 13 ------------------------------------------------------------------------------------->

        $toInvoke = 'DI\Test\ExecuteClassNoDeps::execute';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 14 ------------------------------------------------------------------------------------->

        $toInvoke = 'DI\Test\ExecuteClassDeps::execute';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 15 ------------------------------------------------------------------------------------->

        $toInvoke = 'DI\Test\ExecuteClassStaticMethod::execute';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 16 ------------------------------------------------------------------------------------->

        $toInvoke = 'DI\Test\ExecuteClassRelativeStaticMethod::parent::execute';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 17 ------------------------------------------------------------------------------------->

        $toInvoke = 'DI\Test\testExecuteFunctionWithArg';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 18 ------------------------------------------------------------------------------------->

        $toInvoke = function () {
            return 42;
        };
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);


        if (PHP_VERSION_ID > 50400) {
            // 19 ------------------------------------------------------------------------------------->

            $object = new \DI\Test\ReturnsCallable('new value');
            $args = array();
            $toInvoke = $object->getCallable();
            $expectedResult = 'new value';
            $return[] = array($toInvoke, $args, $expectedResult);
        }
        // x -------------------------------------------------------------------------------------->

        return $return;
    }

    public function testStaticStringInvokableWithArgument()
    {
        $injector = new \DI\Injector;
        $invokable = $injector->buildExecutable('DI\Test\ClassWithStaticMethodThatTakesArg::doSomething');
        $this->assertEquals(42, $invokable(41));
    }

    public function testInterfaceFactoryDelegation()
    {
        $injector = new Injector;
        $injector->delegate('DI\Test\DelegatableInterface', 'DI\Test\ImplementsInterfaceFactory');
        $requiresDelegatedInterface = $injector->make('DI\Test\RequiresDelegatedInterface');
        $requiresDelegatedInterface->foo();
    }

    public function testMissingAlias()
    {
        $reportedClassname = 'TestMissingDependency';
        $classname = 'DI\Test\TypoInType';
        if (PHP_VERSION_ID >= 80000) {
            $classname = "\"" . $classname . "\"";
            $reportedClassname = 'TypoInType';
        }

        $this->expectException(\DI\InjectorException::class);
        $this->expectExceptionMessage(
            "Could not make DI\\Test\\$reportedClassname: Class $classname does not exist"
        );

        $injector = new Injector;
        $testClass = $injector->make('DI\Test\TestMissingDependency');
    }

    public function testAliasingConcreteClasses()
    {
        $injector = new Injector;
        $injector->alias('DI\Test\ConcreteClass1', 'DI\Test\ConcreteClass2');
        $obj = $injector->make('DI\Test\ConcreteClass1');
        $this->assertInstanceOf('DI\Test\ConcreteClass2', $obj);
    }

    public function testSharedByAliasedInterfaceName()
    {
        $injector = new Injector;
        $injector->alias('DI\Test\SharedAliasedInterface', 'DI\Test\SharedClass');
        $injector->share('DI\Test\SharedAliasedInterface');
        $class = $injector->make('DI\Test\SharedAliasedInterface');
        $class2 = $injector->make('DI\Test\SharedAliasedInterface');
        $this->assertSame($class, $class2);
    }

    public function testNotSharedByAliasedInterfaceName()
    {
        $injector = new Injector;
        $injector->alias('DI\Test\SharedAliasedInterface', 'DI\Test\SharedClass');
        $injector->alias('DI\Test\SharedAliasedInterface', 'DI\Test\NotSharedClass');
        $injector->share('DI\Test\SharedClass');
        $class = $injector->make('DI\Test\SharedAliasedInterface');
        $class2 = $injector->make('DI\Test\SharedAliasedInterface');

        $this->assertNotSame($class, $class2);
    }

    public function testSharedByAliasedInterfaceNameReversedOrder()
    {
        $injector = new Injector;
        $injector->share('DI\Test\SharedAliasedInterface');
        $injector->alias('DI\Test\SharedAliasedInterface', 'DI\Test\SharedClass');
        $class = $injector->make('DI\Test\SharedAliasedInterface');
        $class2 = $injector->make('DI\Test\SharedAliasedInterface');
        $this->assertSame($class, $class2);
    }

    public function testSharedByAliasedInterfaceNameWithParameter()
    {
        $injector = new Injector;
        $injector->alias('DI\Test\SharedAliasedInterface', 'DI\Test\SharedClass');
        $injector->share('DI\Test\SharedAliasedInterface');
        $sharedClass = $injector->make('DI\Test\SharedAliasedInterface');
        $childClass = $injector->make('DI\Test\ClassWithAliasAsParameter');
        $this->assertSame($sharedClass, $childClass->sharedClass);
    }

    public function testSharedByAliasedInstance()
    {
        $injector = new Injector;
        $injector->alias('DI\Test\SharedAliasedInterface', 'DI\Test\SharedClass');
        $sharedClass = $injector->make('DI\Test\SharedAliasedInterface');
        $injector->share($sharedClass);
        $childClass = $injector->make('DI\Test\ClassWithAliasAsParameter');
        $this->assertSame($sharedClass, $childClass->sharedClass);
    }

    public function testMultipleShareCallsDontOverrideTheOriginalSharedInstance()
    {
        $injector = new Injector;
        $injector->share('StdClass');
        $stdClass1 = $injector->make('StdClass');
        $injector->share('StdClass');
        $stdClass2 = $injector->make('StdClass');
        $this->assertSame($stdClass1, $stdClass2);
    }

    public function testDependencyWhereSharedWithProtectedConstructor()
    {
        $injector = new Injector;

        $inner = TestDependencyWithProtectedConstructor::create();
        $injector->share($inner);

        $outer = $injector->make('DI\Test\TestNeedsDepWithProtCons');

        $this->assertSame($inner, $outer->dep);
    }

    public function testDependencyWhereShared()
    {
        $injector = new Injector;
        $injector->share('DI\Test\ClassInnerB');
        $innerDep = $injector->make('DI\Test\ClassInnerB');
        $inner = $injector->make('DI\Test\ClassInnerA');
        $this->assertSame($innerDep, $inner->dep);
        $outer = $injector->make('DI\Test\ClassOuter');
        $this->assertSame($innerDep, $outer->dep->dep);
    }

    public function testBugWithReflectionPoolIncorrectlyReturningBadInfo()
    {
        $injector = new Injector;
        $obj = $injector->make('DI\Test\ClassOuter');
        $this->assertInstanceOf('DI\Test\ClassOuter', $obj);
        $this->assertInstanceOf('DI\Test\ClassInnerA', $obj->dep);
        $this->assertInstanceOf('DI\Test\ClassInnerB', $obj->dep->dep);
    }

    public function provideCyclicDependencies()
    {
        return array(
            'DI\Test\RecursiveClassA' => array('DI\Test\RecursiveClassA'),
            'DI\Test\RecursiveClassB' => array('DI\Test\RecursiveClassB'),
            'DI\Test\RecursiveClassC' => array('DI\Test\RecursiveClassC'),
            'DI\Test\RecursiveClass1' => array('DI\Test\RecursiveClass1'),
            'DI\Test\RecursiveClass2' => array('DI\Test\RecursiveClass2'),
            'DI\Test\DependsOnCyclic' => array('DI\Test\DependsOnCyclic'),
        );
    }

     /**
     * @dataProvider provideCyclicDependencies
     */
    public function testCyclicDependencies($class)
    {
        $this->expectException(\DI\InjectionException::class);
        $this->expectExceptionCode(\DI\Injector::E_CYCLIC_DEPENDENCY);

        $injector = new Injector;
        $injector->make($class);
    }

    public function testNonConcreteDependencyWithDefault()
    {
        $injector = new Injector;
        $class = $injector->make('DI\Test\NonConcreteDependencyWithDefaultValue');
        $this->assertInstanceOf('DI\Test\NonConcreteDependencyWithDefaultValue', $class);
        $this->assertNull($class->interface);
    }

    public function testNonConcreteDependencyWithDefaultValueThroughAlias()
    {
        $injector = new Injector;
        $injector->alias(
            'DI\Test\DelegatableInterface',
            'DI\Test\ImplementsInterface'
        );
        $class = $injector->make('DI\Test\NonConcreteDependencyWithDefaultValue');
        $this->assertInstanceOf('DI\Test\NonConcreteDependencyWithDefaultValue', $class);
        $this->assertInstanceOf('DI\Test\ImplementsInterface', $class->interface);
    }

    public function testNonConcreteDependencyWithDefaultValueThroughDelegation()
    {
        $injector = new Injector;
        $injector->delegate('DI\Test\DelegatableInterface', 'DI\Test\ImplementsInterfaceFactory');
        $class = $injector->make('DI\Test\NonConcreteDependencyWithDefaultValue');
        $this->assertInstanceOf('DI\Test\NonConcreteDependencyWithDefaultValue', $class);
        $this->assertInstanceOf('DI\Test\ImplementsInterface', $class->interface);
    }

    public function testDependencyWithDefaultValueThroughShare()
    {
        $injector = new Injector;
        //Instance is not shared, null default is used for dependency
        $instance = $injector->make('DI\Test\ConcreteDependencyWithDefaultValue');
        $this->assertNull($instance->dependency);

        //Instance is explicitly shared, $instance is used for dependency
        $instance = new \StdClass();
        $injector->share($instance);
        $instance = $injector->make('DI\Test\ConcreteDependencyWithDefaultValue');
        $this->assertInstanceOf('StdClass', $instance->dependency);
    }

    public function testShareAfterAliasException()
    {
        $injector = new Injector();
        $testClass = new \StdClass();
        $injector->alias('StdClass', 'DI\Test\SomeOtherClass');

        $this->expectException(\DI\ConfigException::class);
        $this->expectExceptionMessage('Cannot share class stdclass because it is currently aliased to DI\Test\SomeOtherClass');
        $this->expectExceptionCode(\DI\Injector::E_ALIASED_CANNOT_SHARE);

        $injector->share($testClass);
    }

    public function testShareAfterAliasAliasedClassAllowed()
    {
        $injector = new Injector();
        $testClass = new DepImplementation();
        $injector->alias('DI\Test\DepInterface', 'DI\Test\DepImplementation');
        $injector->share($testClass);
        $obj = $injector->make('DI\Test\DepInterface');
        $this->assertInstanceOf('DI\Test\DepImplementation', $obj);
    }

    public function testAliasAfterShareByStringAllowed()
    {
        $injector = new Injector();
        $injector->share('DI\Test\DepInterface');
        $injector->alias('DI\Test\DepInterface', 'DI\Test\DepImplementation');
        $obj = $injector->make('DI\Test\DepInterface');
        $obj2 = $injector->make('DI\Test\DepInterface');
        $this->assertInstanceOf('DI\Test\DepImplementation', $obj);
        $this->assertEquals($obj, $obj2);
    }

    public function testAliasAfterShareBySharingAliasAllowed()
    {
        $injector = new Injector();
        $injector->share('DI\Test\DepImplementation');
        $injector->alias('DI\Test\DepInterface', 'DI\Test\DepImplementation');
        $obj = $injector->make('DI\Test\DepInterface');
        $obj2 = $injector->make('DI\Test\DepInterface');
        $this->assertInstanceOf('DI\Test\DepImplementation', $obj);
        $this->assertEquals($obj, $obj2);
    }

    public function testAliasAfterShareException()
    {
        $injector = new Injector();
        $testClass = new \StdClass();
        $injector->share($testClass);

        $this->expectException(\DI\ConfigException::class);
        $this->expectExceptionMessage('Cannot alias class stdclass to DI\Test\SomeOtherClass because it is currently shared');
        $this->expectExceptionCode(\DI\Injector::E_SHARED_CANNOT_ALIAS);
        $injector->alias('StdClass', 'DI\Test\SomeOtherClass');
    }

    public function testAppropriateExceptionThrownOnNonPublicConstructor()
    {
        $this->expectException(\DI\InjectionException::class);
        $this->expectExceptionMessage('Cannot instantiate protected/private constructor in class DI\Test\HasNonPublicConstructor');
        $this->expectExceptionCode(\DI\Injector::E_NON_PUBLIC_CONSTRUCTOR);

        $injector = new Injector();
        $injector->make('DI\Test\HasNonPublicConstructor');
    }

    public function testAppropriateExceptionThrownOnNonPublicConstructorWithArgs()
    {
        $this->expectException(\DI\InjectionException::class);
        $this->expectExceptionMessage('Cannot instantiate protected/private constructor in class DI\Test\HasNonPublicConstructorWithArgs');
        $this->expectExceptionCode(\DI\Injector::E_NON_PUBLIC_CONSTRUCTOR);

        $injector = new Injector();
        $injector->make('DI\Test\HasNonPublicConstructorWithArgs');
    }

    public function testMakeExecutableFailsOnNonExistentFunction()
    {
        $injector = new Injector();
        $this->expectException(\DI\InjectionException::class);
        $this->expectExceptionMessage('nonExistentFunction');
        $this->expectExceptionCode(\DI\Injector::E_INVOKABLE);


        $injector->buildExecutable('nonExistentFunction');
    }

    public function testMakeExecutableFailsOnNonExistentInstanceMethod()
    {
        $injector = new Injector();
        $object = new \StdClass();
        $this->expectException(\DI\InjectionException::class);
        $this->expectExceptionMessage("[object(stdClass), 'nonExistentMethod']");
        $this->expectExceptionCode(\DI\Injector::E_INVOKABLE);
        $injector->buildExecutable(array($object, 'nonExistentMethod'));
    }

    public function testMakeExecutableFailsOnNonExistentStaticMethod()
    {
        $injector = new Injector();
        $this->expectException(\DI\InjectionException::class);
        $this->expectExceptionMessage("StdClass::nonExistentMethod");
        $this->expectExceptionCode(\DI\Injector::E_INVOKABLE);

        $injector->buildExecutable(array('StdClass', 'nonExistentMethod'));
    }

    public function testMakeExecutableFailsOnClassWithoutInvoke()
    {
        $this->expectException(\DI\InjectionException::class);
        $this->expectExceptionMessage('Invalid invokable: callable or provisional string required');
        $this->expectExceptionCode(\DI\Injector::E_INVOKABLE);

        $injector = new Injector();
        $object = new \StdClass();
        $injector->buildExecutable($object);
    }

    public function testBadAliasFirstArg()
    {
        $injector = new Injector;

        $this->expectException(\DI\ConfigException::class);
        $this->expectExceptionMessage(Injector::M_NON_EMPTY_STRING_ALIAS);
        $this->expectExceptionCode(\DI\Injector::E_NON_EMPTY_STRING_ALIAS);

        $injector->alias('', 'DI\Test\DepImplementation');
    }

    public function testBadAliasSecondArg()
    {
        $injector = new Injector();
        $injector->share('DI\Test\DepInterface');


        $this->expectException(\DI\ConfigException::class);
        $this->expectExceptionMessage('Invalid alias: non-empty string required at arguments 1 and 2');
        $this->expectExceptionCode(\DI\Injector::E_NON_EMPTY_STRING_ALIAS);

        $injector->alias('DI\Test\DepInterface', '');
    }

    public function testShareNewAlias()
    {
        $injector = new Injector();
        $injector->share('DI\Test\DepImplementation');
        $injector->alias('DI\Test\DepInterface', 'DI\Test\DepImplementation');
    }

    /**
     * @group deadish
     */
    public function testDefineWithBackslashAndMakeWithoutBackslash()
    {
        $injector = new Injector();
        $injector->define('DI\Test\SimpleNoTypeClass', array(':arg' => 'tested'));
        $testClass = $injector->make('DI\Test\SimpleNoTypeClass');
        $this->assertEquals('tested', $testClass->testParam);
    }

    public function testShareWithBackslashAndMakeWithoutBackslash()
    {
        $injector = new Injector();
        $injector->share('\StdClass');
        $classA = $injector->make('StdClass');
        $classA->tested = false;
        $classB = $injector->make('\StdClass');
        $classB->tested = true;

        $this->assertEquals($classA->tested, $classB->tested);
    }

    public function testInstanceMutate()
    {
        $injector = new Injector();
        $injector->prepare('\StdClass', function ($obj, $injector) {
            $obj->testval = 42;
        });
        $obj = $injector->make('StdClass');

        $this->assertSame(42, $obj->testval);
    }

    public function testInterfaceMutate()
    {
        $injector = new Injector();
        $injector->prepare('DI\Test\SomeInterface', function ($obj, $injector) {
            $obj->testProp = 42;
        });
        $obj = $injector->make('DI\Test\PreparesImplementationTest');

        $this->assertSame(42, $obj->testProp);
    }



    /**
     * Test that custom definitions are not passed through to dependencies.
     * Surprising things would happen if this did occur.
     */
    public function testCustomDefinitionNotPassedThrough()
    {
        $injector = new Injector();
        $injector->share('DI\Test\DependencyWithDefinedParam');

        $this->expectException(\DI\InjectionException::class);
        // TODO - why does this message end with double-colon?
        $this->expectExceptionMessage('No definition available to provision typeless parameter $foo at position 0 in DI\Test\DependencyWithDefinedParam::__construct() declared in DI\Test\DependencyWithDefinedParam::');
        $this->expectExceptionCode(\DI\Injector::E_UNDEFINED_PARAM);

        $injector->make('DI\Test\RequiresDependencyWithDefinedParam', array(':foo' => 5));
    }

    public function testDelegationFunction()
    {
        $injector = new Injector();
        $injector->delegate('DI\Test\TestDelegationSimple', 'DI\Test\createTestDelegationSimple');
        $obj = $injector->make('DI\Test\TestDelegationSimple');
        $this->assertInstanceOf('DI\Test\TestDelegationSimple', $obj);
        $this->assertTrue($obj->delegateCalled);
    }

    public function testDelegationDependency()
    {
        $injector = new Injector();
        $injector->delegate(
            'DI\Test\TestDelegationDependency',
            'DI\Test\createTestDelegationDependency'
        );
        $obj = $injector->make('DI\Test\TestDelegationDependency');
        $this->assertInstanceOf('DI\Test\TestDelegationDependency', $obj);
        $this->assertTrue($obj->delegateCalled);
    }

    public function testExecutableAliasing()
    {
        $injector = new Injector();
        $injector->alias('DI\Test\BaseExecutableClass', 'DI\Test\ExtendsExecutableClass');
        $result = $injector->execute(array('DI\Test\BaseExecutableClass', 'foo'));
        $this->assertEquals('This is the ExtendsExecutableClass', $result);
    }

    public function testExecutableAliasingStatic()
    {
        $injector = new Injector();
        $injector->alias('DI\Test\BaseExecutableClass', 'DI\Test\ExtendsExecutableClass');
        $result = $injector->execute(array('DI\Test\BaseExecutableClass', 'bar'));
        $this->assertEquals('This is the ExtendsExecutableClass', $result);
    }

    /**
     * Test coverage for delegate closures that are defined outside
     * of a class.ph
     * @throws \DI\ConfigException
     */
    public function testDelegateClosure()
    {
        $delegateClosure = \DI\Test\getDelegateClosureInGlobalScope();
        $injector = new Injector();
        $injector->delegate('DI\Test\DelegateClosureInGlobalScope', $delegateClosure);
        $injector->make('DI\Test\DelegateClosureInGlobalScope');
    }

    public function testCloningWithServiceLocator()
    {
        $injector = new Injector();
        $injector->share($injector);
        $instance = $injector->make('DI\Test\CloneTest');
        $newInjector = $instance->injector;
        $newInstance = $newInjector->make('DI\Test\CloneTest');
    }

    public function testAbstractExecute()
    {
        $injector = new Injector();

        $fn = function () {
            return new \DI\Test\ConcreteExexcuteTest();
        };

        $injector->delegate('DI\Test\AbstractExecuteTest', $fn);
        $result = $injector->execute(array('DI\Test\AbstractExecuteTest', 'process'));

        $this->assertEquals('Concrete', $result);
    }

    public function testDebugMake()
    {
        $injector = new Injector();
        try {
            $injector->make('DI\Test\DependencyChainTest');
        } catch (\DI\InjectionException $ie) {
            $chain = $ie->getDependencyChain();
            $this->assertCount(2, $chain);

            $this->assertEquals('di\test\dependencychaintest', $chain[0]);
            $this->assertEquals('di\test\depinterface', $chain[1]);
        }
    }

    public function testInspectShares()
    {
        $injector = new Injector();
        $injector->share('DI\Test\SomeClassName');

        $inspection = $injector->inspect('DI\Test\SomeClassName', Injector::I_SHARES);
        $this->assertArrayHasKey('di\test\someclassname', $inspection[Injector::I_SHARES]);
    }

    public function testInspectAll()
    {
        $injector = new Injector();

        // Injector::I_BINDINGS
        $injector->define('DI\Test\DependencyWithDefinedParam', array(':arg' => 42));

        // Injector::I_DELEGATES
        $injector->delegate('DI\Test\MadeByDelegate', 'DI\Test\CallableDelegateClassTest');

        // Injector::I_PREPARES
        $injector->prepare('DI\Test\MadeByDelegate', function ($c) {});

        // Injector::I_ALIASES
        $injector->alias('i', 'DI\Injector');

        // Injector::I_SHARES
        $injector->share('DI\Injector');

        $all = $injector->inspect();
        $some = $injector->inspect('DI\Test\MadeByDelegate');

        $this->assertCount(5, array_filter($all));
        $this->assertCount(2, array_filter($some));
    }

    public function testDelegationDoesntMakeObject()
    {
        $delegate = function () {
            return null;
        };
        $injector = new Injector();
        $injector->delegate('DI\Test\SomeClassName', $delegate);

        $this->expectException(\DI\InjectionException::class);
        $this->expectExceptionMessage('Making di\test\someclassname did not result in an object, instead result is of type \'NULL\'');
        $this->expectExceptionCode(\DI\Injector::E_MAKING_FAILED);

        $injector->make('DI\Test\SomeClassName');
    }

    public function testDelegationDoesntMakeObjectMakesString()
    {
        $delegate = function () {
            return 'ThisIsNotAClass';
        };
        $injector = new Injector();
        $injector->delegate('DI\Test\SomeClassName', $delegate);

        $this->expectException(\DI\InjectionException::class);
        $this->expectExceptionMessage('Making di\test\someclassname did not result in an object, instead result is of type \'string\'');
        $this->expectExceptionCode(\DI\Injector::E_MAKING_FAILED);

        $injector->make('DI\Test\SomeClassName');
    }

    public function testPrepareInvalidCallable()
    {
        $injector = new Injector;
        $invalidCallable = 'This_does_not_exist';
        $this->expectException(\DI\ConfigException::class);
        $this->expectExceptionMessage($invalidCallable);

        $injector->prepare("StdClass", $invalidCallable);
    }

    public function testPrepareCallableReplacesObjectWithReturnValueOfSameInterfaceType()
    {
        $injector = new Injector;
        $expected = new SomeImplementation; // <-- implements SomeInterface
        $injector->prepare("DI\Test\SomeInterface", function ($impl) use ($expected) {
            return $expected;
        });
        $actual = $injector->make("DI\Test\SomeImplementation");
        $this->assertSame($expected, $actual);
    }

    public function testPrepareCallableReplacesObjectWithReturnValueOfSameClassType()
    {
        $injector = new Injector;
        $expected = new SomeImplementation; // <-- implements SomeInterface
        $injector->prepare("DI\Test\SomeImplementation", function ($impl) use ($expected) {
            return $expected;
        });
        $actual = $injector->make("DI\Test\SomeImplementation");
        $this->assertSame($expected, $actual);
    }

    /**
     * @group deadish
     */
    public function testChildWithoutConstructorWorks() {

        $injector = new Injector;
        try {
            $injector->define('DI\Test\ParentWithConstructor', array(':foo' => 'parent'));
            $injector->define('DI\Test\ChildWithoutConstructor', array(':foo' => 'child'));

            $injector->share('DI\Test\ParentWithConstructor');
            $injector->share('DI\Test\ChildWithoutConstructor');

            $child = $injector->make('DI\Test\ChildWithoutConstructor');
            $this->assertEquals('child', $child->foo);

            $parent = $injector->make('DI\Test\ParentWithConstructor');
            $this->assertEquals('parent', $parent->foo);
        }
        catch (\DI\InjectionException $ie) {
            echo $ie->getMessage();
            $this->fail("Injector failed to locate the ");
        }
    }

//    public function testWhySeparationIsNeeded()
//    {
//        $injector = new Injector();
//        $message = "shared instance has one off message";
//
//        // Declare a class as shared
//        $injector->share(SharedClassInInjector::class);
//        // Create an instance with one-off variables. The object is created.
//        $obj1 = $injector->make(SharedClassInInjector::class, [':message' => $message]);
//
//        // Create another instance... but as it is shared, the previous
//        // 'one-off' message is used.
//        $obj2 = $injector->make(SharedClassInInjector::class, [':message' => "This doesn't get used"]);
//
//        $this->assertSame($message, $obj1->getMessage());
//        $this->assertSame($message, $obj2->getMessage());
//    }

    public function testSeparationWorks_with_shared_class()
    {
        $injector = new Injector();
        $message_1 = "shared instance has one off message";
        $message_2 = "This does get used";

        // We're sharing the class.
        $separated_injector_1 = $injector->separateContext();
        $separated_injector_2 = $injector->separateContext();
        $separated_injector_1->defineParam('message', $message_1);
        $obj1 = $separated_injector_1->make(SharedClassInInjector::class);

        $separated_injector_2->defineParam('message', $message_2);
        $obj2 = $separated_injector_2->make(SharedClassInInjector::class);

        $this->assertSame($message_1, $obj1->getMessage());
        $this->assertSame($message_2, $obj2->getMessage());
        $this->assertNotSame($obj1, $obj2);
    }

    public function testChildWithoutConstructorMissingParam()
    {
        $injector = new Injector;
        $injector->define('DI\Test\ParentWithConstructor', array(':foo' => 'parent'));

        $this->expectException(\DI\InjectionException::class);
        $this->markTestSkipped("With one of the optimisations, the error message has changed, and maybe isn't as good. Needs investigation.");
        $this->expectExceptionMessage('No definition available to provision typeless parameter $foo at position 0 in DI\Test\ChildWithoutConstructor::__construct() declared in DI\Test\ParentWithConstructor');

        $injector->make('DI\Test\ChildWithoutConstructor');
    }

    public function testInstanceClosureDelegates()
    {
        $injector = new Injector;
        $injector->delegate('DI\Test\DelegatingInstanceA', function (DelegateA $d) {
            return new \DI\Test\DelegatingInstanceA($d);
        });
        $injector->delegate('DI\Test\DelegatingInstanceB', function (DelegateB $d) {
            return new \DI\Test\DelegatingInstanceB($d);
        });

        $a = $injector->make('DI\Test\DelegatingInstanceA');
        $b = $injector->make('DI\Test\DelegatingInstanceB');

        $this->assertInstanceOf('DI\Test\DelegateA', $a->a);
        $this->assertInstanceOf('DI\Test\DelegateB', $b->b);
    }


    public function testThatExceptionInConstructorDoesntCauseCyclicDependencyException()
    {
        $injector = new Injector;

        try {
            $injector->make('DI\Test\ThrowsExceptionInConstructor');
        } catch (\Exception $e) {
        }

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Exception in constructor');

        $injector->make('DI\Test\ThrowsExceptionInConstructor');
    }

    public function testProvidesExtensionsOfArrayMap()
    {
        $injector = new Injector;
        $obj = $injector->make('\DI\Test\ExtendedExtendedArrayObject');

        $this->assertInstanceOf('\ArrayObject', $obj);
    }

    public function testDoubleShareClassThrows()
    {
        $injector = new Injector;
        $injector->share(new \StdClass);

        $this->expectExceptionCode(Injector::E_DOUBLE_SHARE);
        $this->expectExceptionMessageMatchesTemplateString(Injector::M_DOUBLE_SHARE);
        $this->expectExceptionMessageContains('stdclass');

        $injector->share(new \StdClass);
    }


    /**
     * This test is duplication of other tests. It is present to check
     * that the behaviour of three different ways of params being null-ish
     * are consistent.
     *
     * @requires PHP 8.0
     */
    public function testNullConsistency()
    {
        require_once __DIR__ . "/fixtures_8_0.php";

        $injector = new Injector;
        $obj = $injector->make(\NullableDependency::class);
        $this->assertInstanceOf(\NullableDependency::class, $obj);
        $this->assertNull($obj->instance);

        $obj = $injector->make(\UnionNullDependency::class);
        $this->assertInstanceOf(\UnionNullDependency::class, $obj);
        $this->assertNull($obj->instance);

        $obj = $injector->make(\DefaultNullDependency::class);
        $this->assertInstanceOf(\DefaultNullDependency::class, $obj);
        $this->assertNull($obj->instance);
    }

    /**
     * @requires PHP 8.1
     */
    public function testNewInIntializer()
    {
        require_once __DIR__ . "/fixtures_8_1.php";

        $injector = new Injector;
        $obj = $injector->make(\NewInInitializer::class);

        $this->assertInstanceOf(\NewInInitializer::class, $obj);
        $this->assertInstanceOf(\NewInInitializerDependency::class, $obj->instance);
    }
}
