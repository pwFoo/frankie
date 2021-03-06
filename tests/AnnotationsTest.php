<?php
namespace Corley\Middleware;

use ReflectionClass;
use ReflectionMethod;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Corley\Middleware\Annotations as Corley;

/**
 * @Corley\Before(targetClass="AnotherClass", targetMethod="hello")
 */
class Sut
{
    /**
     * @Corley\Before(targetClass="SuperClass", targetMethod="aMethod")
     */
    public function method()
    {
    }

    /**
     * @Corley\Before(targetClass="SuperClass", targetMethod="firstMethod")
     * @Corley\Before(targetClass="SuperClass2", targetMethod="secondMethod")
     */
    public function multi()
    {
    }
}

class AnnotationsTest extends \PHPUnit_Framework_TestCase
{
    private $reader;

    public function setUp()
    {
        $loader = require __DIR__.'/../vendor/autoload.php';
        AnnotationRegistry::registerLoader(array($loader, 'loadClass'));

        $this->reader = new AnnotationReader();
    }

    public function testReadClassAnnotations()
    {
        $reflClass = new ReflectionClass('Corley\\Middleware\\Sut');
        $annotations = $this->reader->getClassAnnotations($reflClass);

        $this->assertCount(1, $annotations);
        foreach ($annotations as $annot) {
            $this->assertInstanceOf("Corley\\Middleware\\Annotations\\Before", $annot);

            $this->assertEquals("AnotherClass", $annot->targetClass);
            $this->assertEquals("hello", $annot->targetMethod);
        }
    }

    public function testReadMethodAnnotations()
    {
        $reflClass = new ReflectionMethod('Corley\\Middleware\\Sut', "method");
        $annotations = $this->reader->getMethodAnnotations($reflClass);

        $this->assertCount(1, $annotations);
        foreach ($annotations as $annot) {
            $this->assertInstanceOf("Corley\\Middleware\\Annotations\\Before", $annot);

            $this->assertEquals("SuperClass", $annot->targetClass);
            $this->assertEquals("aMethod", $annot->targetMethod);
        }
    }

    public function testReadMultipleMethodAnnotations()
    {
        $reflClass = new ReflectionMethod('Corley\\Middleware\\Sut', "multi");
        $annotations = $this->reader->getMethodAnnotations($reflClass);

        $this->assertCount(2, $annotations);
        $this->assertInstanceOf("Corley\\Middleware\\Annotations\\Before", $annotations[0]);

        $this->assertEquals("SuperClass", $annotations[0]->targetClass);
        $this->assertEquals("firstMethod", $annotations[0]->targetMethod);
        $this->assertEquals("SuperClass2", $annotations[1]->targetClass);
        $this->assertEquals("secondMethod", $annotations[1]->targetMethod);
    }
}
