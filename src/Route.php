<?php

namespace DanialRahimy\Router;

class Route
{
    protected static array $matchRoute = [];
    protected static array $routes = [];
    protected static array $_404 = [];
    protected static string $requestUri = '';
    protected static string $directionCache = '';
    protected static array $routeFiles = [];
    protected static bool $cacheIsOn = false;

    /**
     * @param string $direction
     */
    public static function setCacheDir(string $direction)
    {
        self::$directionCache = $direction;
    }

    /**
     * @param array $routeFiles
     */
    public static function setRouteFiles(array $routeFiles)
    {
        self::$routeFiles = $routeFiles;
    }

    /**
     * @param string $requestUri
     */
    public static function setTargetRoute(string $requestUri)
    {
        self::$requestUri = $requestUri;
    }

    /**
     * @param bool $cacheIsOn
     */
    public static function setCacheOn(bool $cacheIsOn = false)
    {
        self::$cacheIsOn = $cacheIsOn;
    }

    /**
     * @param array $params
     *      [
     *          controller => Controller Class, // REQUIRED
     *          action => String Name Of The Controller Method, // REQUIRED
     *      ]
     * @throws RouterException
     */
    public static function set404(array $params)
    {
        $params['path'] = '';
        self::checkNewRouteParam($params);

        self::$_404 = [
            'controller' => $params['controller'],
            'action' => $params['action'],
        ];
    }

    /**
     * @param array $params
     *      [
     *          path => String Of The Route Matched, // REQUIRED
     *          controller => Controller Class, // REQUIRED
     *          action => String Name Of The Controller Method, // REQUIRED
     *          name => Name Of The Route // OPTIONAL
     *      ]
     * @throws RouterException
     */
    public static function get(array $params)
    {

        self::checkNewRouteParam($params);

        $key = $params['name'] ?? count(self::$routes);

        self::$routes[$key] = [
            'method' => 'GET',
            'path' => $params['path'],
            'controller' => $params['controller'],
            'action' => $params['action'],
        ];
    }

    /**
     * @param array $params
     *      [
     *          path => String Of The Route Matched, // REQUIRED
     *          controller => Controller Class, // REQUIRED
     *          action => String Name Of The Controller Method, // REQUIRED
     *          name => Name Of The Route // OPTIONAL
     *      ]
     * @throws RouterException
     */
    public static function post(array $params)
    {
        self::checkNewRouteParam($params);

        $key = $params['name'] ?? count(self::$routes);

        self::$routes[$key] = [
            'method' => 'POST',
            'path' => $params['path'],
            'controller' => $params['controller'],
            'action' => $params['action'],
        ];
    }

    /**
     * @param array $params
     * @throws RouterException
     */
    protected static function checkNewRouteParam(array $params)
    {
        if (!isset($params['path']))
            throw new RouterException('Path is not set');

        if (!isset($params['controller']))
            throw new RouterException('Controller is not set');
        else
            if (!class_exists($params['controller']))
                throw new RouterException($params['controller'] . ' Is not a valid controller');

        if (!isset($params['action']))
            throw new RouterException('Action is not set');
        else
            if (!method_exists($params['controller'], $params['action']))
                throw new RouterException('Method ' . $params['action'] . ' In ' . $params['controller'] . ' not found');
    }

    /**
     * @return string
     */
    protected static function getCachePath(): string
    {
        return self::$directionCache . '/routes.php';
    }

    /**
     *
     */
    protected static function loadFromCache()
    {
        $cachePath = self::getCachePath();

        $cachedRoute = require_once $cachePath;
        self::$routes = $cachedRoute['routes'];
        self::$_404 = $cachedRoute['notFound'];
    }

    /**
     *
     */
    protected static function createCacheAndLoadRoutes()
    {
        $cachePath = self::getCachePath();

        foreach (self::$routeFiles as $routeFile)
            require_once $routeFile;

        $dir = dirname($cachePath);

        if (!is_dir($dir))
            mkdir($dir, 0777, true);

        $data = "<?php \nreturn [ \n 'routes' => [ \n";

        foreach (self::$routes as $name => $route) {
            $method = $route['method'];
            $path = $route['path'];
            $controller = $route['controller'];
            $action = $route['action'];
            $name = is_numeric($name) ? $name : "'$name'";
            $data .= "
    $name => [
        'method' => '$method',
        'path' => '$path',
        'controller' => $controller::class,
        'action' => '$action',
    ],";
        }
        $notFoundController = self::$_404['controller'];
        $notFoundAction = self::$_404['action'];
        $data .= "\n],\n";
        $data .= "
    'notFound' => [
        'controller' => $notFoundController::class,
        'action' => '$notFoundAction',
    ],\n";
        $data .= "];";

        file_put_contents($cachePath, $data);
    }

    /**
     * @return array
     */
    public static function getMatchedRoute(): array
    {
        $cachePath = self::getCachePath();

        if (self::$cacheIsOn){
            if (file_exists($cachePath))
                self::loadFromCache();
            else
                self::createCacheAndLoadRoutes();
        }else{
            foreach (self::$routeFiles as $routeFile)
                require_once $routeFile;
        }

        $parts = self::getParts(self::$requestUri);

        return self::findMatch(self::$routes, $parts, 0);
    }

    /**
     * @return array
     */
    public static function getAllRoutes(): array
    {
        return self::$routes;
    }

    /**
     * @param array $routes
     * @param array $parts
     * @param int $level
     * @return array
     */
    protected static function findMatch(array $routes, array $parts, int $level): array
    {
        $needToMatch = $routes;

        if (empty($parts) and self::isHome(self::$routes))
            $part = "";
        else
            $part = $parts[$level];

        foreach ($needToMatch as $key => $value) {

            $routesParts = explode("/", $value['path']);
            if (array_key_exists($level, $routesParts)){
                if ($routesParts[$level] != $part and !self::isParam($routesParts[$level]))
                    unset($needToMatch[$key]);
            }else{
                unset($needToMatch[$key]);
            }

        }

        $nextLevel = $level;
        $nextLevel = $nextLevel + 1;

        if (array_key_exists($nextLevel, $parts)) {
            return self::findMatch($needToMatch, $parts, $nextLevel);
        } else {

            if (count($needToMatch) > 0) {
                foreach ($needToMatch as $key => $value) {
                    if (self::countRoutePart($value['path']) !== count($parts)) {
                        unset($needToMatch[$key]);
                        continue;
                    } else {
                        $needToMatch = $value;
                        break;
                    }
                }

                if (
                    isset($needToMatch['path']) and
                    count($parts) === self::countRoutePart($needToMatch['path'])
                    and $_SERVER['REQUEST_METHOD'] === $needToMatch['method']
                ) {
                    self::$matchRoute = $needToMatch;

                    self::$matchRoute['targetRoute'] = self::$requestUri;

                    return self::$matchRoute;
                } else {
                    $_404 = self::$_404;
                    $_404['targetRoute'] = self::$requestUri;

                    return $_404;
                }

            } else {
                $_404 = self::$_404;
                $_404['targetRoute'] = self::$requestUri;
                $_404['path'] = self::$requestUri;

                return $_404;
            }
        }
    }

    /**
     * @param $value
     * @return array|false
     */
    protected static function isParam($value)
    {
        $posStart = strpos($value, "{");
        $posEnd = strpos($value, "}");

        if ($posEnd !== false and $posStart !== false)
            return array($posStart, $posEnd);

        return false;
    }

    /**
     * @param array $routes
     * @return bool
     */
    protected static function isHome(array $routes): bool
    {
        $valid = false;

        foreach ($routes as $value) {
            if ($value['path'] === "/") {
                self::$routes = $value;
                $valid = true;
                break;
            }
        }

        return $valid;
    }

    /**
     * @param string $value
     * @return int
     */
    protected static function countRoutePart(string $value): int
    {
        $parts = explode("/", $value);

        if (count($parts) > 0)
            foreach ($parts as $key => $value)
                if (empty($value))
                    unset($parts[$key]);

        return count($parts);
    }

    /**
     * @param string $uri
     * @return array
     */
    protected static function getParts(string $uri = ""): array
    {
        $parts = explode("/", $uri);
        $outPut = array();

        foreach ($parts as $part)
            if (!empty($part))
                $outPut[] = $part;

        return $outPut;
    }
}