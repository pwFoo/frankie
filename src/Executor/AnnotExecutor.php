<?php
namespace Corley\Middleware\Executor;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Corley\Middleware\Reader\HookReader;
use Corley\Middleware\Annotations\Before;
use Corley\Middleware\Annotations\After;
use Corley\Middleware\Exception\ShortcutException;
use Psr\Container\ContainerInterface;

class AnnotExecutor
{
    private $container;
    private $reader;
    private $request;
    private $response;
    private $limiter;

    public function __construct(ContainerInterface $container, HookReader $reader)
    {
        $this->container = $container;
        $this->reader = $reader;
        $this->limiter = [];
    }

    public function execute(Request $request, Response $response, array $matched)
    {
        $this->request = $request;
        $this->response = $response;

        $action     = $matched["action"];
        $controller = $matched["controller"];

        try {
            $this->executeActionsFor($controller, $action, Before::class, $matched, false);

            $controller = $this->getContainer()->get($controller);
            $data = array_diff_key($matched, array_flip(["annotation", "_route", "controller", "action"]));
            $actionReturn = $this->call([$controller, $action], array_merge([$request, $response], $data));

            $this->executeActionsFor($controller, $action, After::class, $actionReturn, true);
        } catch (ShortcutException $e) {
            $response = $e->getResponse();
        }

        return $response;
    }

    private function executeActionsFor($controller, $action, $filterClass, $data = null, $after = false)
    {
        $this->limiter = [];
        $methodAnnotations = $this->getReader()->getMethodAnnotationsFor($controller, $action, $filterClass);
        $this->executeSteps($methodAnnotations, [$this, __FUNCTION__], $filterClass, $data, $after);

        $classAnnotations = $this->getReader()->getClassAnnotationsFor($controller, $filterClass);
        $this->executeSteps($classAnnotations, [$this, __FUNCTION__], $filterClass, $data, $after);
    }

    private function executeSteps(array $annotations, callable $method, $filterClass, $data = null, $after = false)
    {
        foreach ($annotations as $annotation) {
            $limiterKey = $annotation->targetClass . "::" . $annotation->targetMethod;
            if (!array_key_exists($limiterKey, $this->limiter)) {
                if (!$after) {
                    $method($annotation->targetClass, $annotation->targetMethod, $filterClass, $data, $after);
                }

                $newController = $this->getContainer()->get($annotation->targetClass);
                $this->call([$newController, $annotation->targetMethod], [ $this->request, $this->response, $data ]);

                if ($after) {
                    $method($annotation->targetClass, $annotation->targetMethod, $filterClass, $data, $after);
                }
                $this->limiter[$limiterKey][] = true;
            }
        }
    }

    private function call(callable $method, array $params)
    {
        $response = call_user_func_array($method, $params);

        if ($response instanceOf Response) {
            throw new ShortcutException($response);
        }

        return $response;
    }

    public function getContainer()
    {
        return $this->container;
    }

    public function getReader()
    {
        return $this->reader;
    }
}
