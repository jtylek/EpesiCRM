<?php

namespace App\Routing;

use Illuminate\Routing\Router as BaseRouter;

/**
 * Laravel's router, loading cached routes into App\Routing\CompiledRouteCollection
 * so `route:cache` doesn't break the home page of an installation in a
 * folder. Bound in bootstrap/app.php.
 */
class Router extends BaseRouter
{
    public function setCompiledRoutes(array $routes)
    {
        $this->routes = (new CompiledRouteCollection($routes['compiled'], $routes['attributes']))
            ->setRouter($this)
            ->setContainer($this->container);

        $this->container->instance('routes', $this->routes);
    }
}
