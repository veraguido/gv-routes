<?php namespace Gvera\Helpers\routes;

use Exception;
use Gvera\Cache\Cache;
use Gvera\Exceptions\InvalidArgumentException;
use Gvera\Helpers\http\HttpRequest;
use Symfony\Component\Yaml\Yaml;

/**
 * Class RouteManager
 * @package Gvera\Helpers\routes
 * The routing is managed through convention over configuration by default, but a custom route could be added in the
 * routes.yml file, that rule will override and take precedence. This class has the algorithm that decides if the route
 * that is being input match any of the ones that are noted in routes.yml.
 */
class RouteManager
{
    private array $routes;
    const ROUTES_DEFAULT_FILE_PATH = CONFIG_ROOT . "routes.yml";
    const ROUTE_NEEDLE = ':';
    const ROUTE_CACHE_KEY = 'gv_routes';
    private HttpRequest $httpRequest;
    private array $excludeDirectories = [".", ".."];

    /**
     * RouteManager constructor.
     * @param HttpRequest $httpRequest
     * @param string $routesFilePath
     * @throws InvalidArgumentException
     */
    public function __construct(
        HttpRequest $httpRequest,
        string $routesFilePath = self::ROUTES_DEFAULT_FILE_PATH
    ) {
        $this->httpRequest = $httpRequest;
        $this->routes = $this->setRoutes($routesFilePath);
    }

    /**
     * @param string $filePath
     * @return array
     * @throws InvalidArgumentException
     * @throws Exception
     */
    private function setRoutes(string $filePath): array
    {
        if (Cache::getCache()->exists(RouteManager::ROUTE_CACHE_KEY)) {
            return Cache::getCache()->load(RouteManager::ROUTE_CACHE_KEY);
        }
        $routes = Yaml::parse(
            file_get_contents(self::ROUTES_DEFAULT_FILE_PATH)
        )['routes'];
        Cache::getCache()->save(RouteManager::ROUTE_CACHE_KEY, $routes);

        return $routes;
    }


    /**
     * @param $pathLike
     * @return mixed
     */
    public function getRoute($pathLike)
    {
        $pathLikeArray = explode("/", $pathLike);
        if (!$this->isPathLikeArrayValid($pathLikeArray)) {
            return false;
        }

        $filteredRoutes = $this->stripRoutesByHttpMethod($this->httpRequest->getRequestType());

        foreach ($filteredRoutes as $route) {
            if ($routeFound = $this->defineRoute($route, $pathLikeArray)) {
                return $routeFound;
            }
        }
        return false;
    }

    /**
     * @param $id
     * @param $method
     * @param $route
     * @param $action
     * @throws Exception
     */
    public function addRoute($id, $method, $route, $action)
    {
        if (isset($this->routes[$id])) {
            throw new Exception('route identifier already exists');
        }
        $this->routes[$id] = array('method' => $method, 'route' => $route, 'action' => $action);
    }

    public function getExcludeDirectories(): array
    {
        return $this->excludeDirectories;
    }

    /**
     * @return string|bool
     */
    private function defineRoute($route, $pathLikeArray)
    {
        if (!(strpos($route['uri'], $pathLikeArray[1]) !== false) ||
            !(strpos($route['uri'], $pathLikeArray[2]) !== false)) {
                return false;
        }
            $totalRoute = $route['uri'] ;
            $totalRouteArray = explode("/", $totalRoute);
            $routeController = $totalRouteArray[1];
            $routeMethod = $totalRouteArray[2];
            
            return $this->isUrlAndUriValid($pathLikeArray, $routeController, $routeMethod, $totalRoute, $route);
    }

    /**
     * @param array $pathLikeArray
     * @param string $routeController
     * @param string $routeMethod
     * @param string $totalRoute
     * @param array $route
     * @return false|string
     */
    private function isUrlAndUriValid(
        array $pathLikeArray,
        string $routeController,
        string $routeMethod,
        string $totalRoute,
        array $route
    ) {
        $urlCheck = ($pathLikeArray[1] == $routeController && $pathLikeArray[2] == $routeMethod);
        $checkUri = $this->convertUriParams($pathLikeArray, explode('/', $totalRoute));
        return $urlCheck && $checkUri ? $route['action'] : false;
    }

    /**
     * @param $pathLikeArray
     * @return bool
     */
    private function isPathLikeArrayValid($pathLikeArray): bool
    {
        return isset($pathLikeArray[2]) && !empty($pathLikeArray[2]);
    }

    /**
     * @param $totalRoute
     * @param $pathLikeArray
     * @return bool
     */
    private function convertUriParams($totalRoute, $pathLikeArray)
    {
        $count = count($pathLikeArray);
        for ($i = 0; $i < $count; $i++) {
            if (substr_count($pathLikeArray[$i], self::ROUTE_NEEDLE) == 2) {
                $value = $totalRoute[$i] ?? null;

                $this->httpRequest->setParameter(
                    str_replace(self::ROUTE_NEEDLE, '', $pathLikeArray[$i]),
                    $value
                );
            }
        }

        return true;
    }

    /**
     * @param $method
     * @return array
     */
    private function stripRoutesByHttpMethod($method): array
    {
        $filteredRoutes = array();
        foreach ($this->routes as $route) {
            var_dump($route);
            if ($route['method'] == $method) {
                $filteredRoutes[] = $route;
            }
        }

        return $filteredRoutes;
    }
}
