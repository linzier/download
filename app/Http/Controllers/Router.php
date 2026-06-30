<?php

namespace App\Http\Controllers;

use WecarSwoole\Http\Route;
use EasySwoole\Http\AbstractInterface\AbstractRouter;
use FastRoute\RouteCollector;
use EasySwoole\Http\Request;
use EasySwoole\Http\Response;
use WecarSwoole\Util\File;

/**
 * HTTP Router Entry
 * Do not add specific route rules here. Define routes by module in the Routes directory.
 * Class Router
 * @package App\Http\Controllers
 */
class Router extends AbstractRouter
{
    public function initialize(RouteCollector $routeCollector)
    {
        $this->setMethodNotAllowCallBack(function (Request $request, Response $response) {
            $response->withStatus(404);
            $response->withHeader('Content-type','text/html;charset=UTF-8');
            $response->write('Handler method not found');
            return false;
        });

        $this->setRouterNotFoundCallBack(function (Request $request, Response $response) {
            $response->withStatus(404);
            $response->withHeader('Content-type','text/html;charset=UTF-8');
            $response->write('Route not found');
            return false;
        });

        // Load specific routes
        $this->loadRoutes($routeCollector);
    }

    /**
     * Load specific routes
     * @param RouteCollector $route
     */
    protected function loadRoutes(RouteCollector $route)
    {
        $files = File::scanDirectory(File::join(EASYSWOOLE_ROOT, 'app/Http/Routes'));

        if (!$files || !($files = $files['files'])) {
            return;
        }

        foreach ($files as $routeFileName) {
            $this->loadRoutesFromFile($routeFileName, $route);
        }
    }

    protected function loadRoutesFromFile(string $fileName, RouteCollector $route)
    {
        $class = '\\App\\' . str_replace('/', '\\', str_replace('.php', '', explode('/app/', $fileName)[1]));
        if (!class_exists($class)) {
            return;
        }

        $instance = (new \ReflectionClass($class))->newInstance($route);
        if ($instance instanceof Route) {
            $instance->map();
        }
    }
}
