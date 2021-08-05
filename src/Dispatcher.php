<?php

namespace DanialRahimy\Router;

class Dispatcher
{
    protected static array $request = [];
    protected static object $controller;
    protected static string $action = '';

    /**
     * @param array $request =>
     *      [
     *          controller => Controller Class, // REQUIRED
     *          action => String Name Of The Controller Method, // REQUIRED
     *          path => String Of The Route Matched, // REQUIRED
     *          targetRoute => Request Uri // REQUIRED
     *      ]
     * @return mixed
     * @throws DispatcherException
     */

    public static function handle(array $request)
    {
        self::$request = $request;
        self::setController();
        return self::setAction();
    }

    /**
     *
     * @throws DispatcherException
     */
    protected static function setController()
    {
        if (!class_exists(self::$request['controller']))
            throw new DispatcherException('Controller is not valid');

        $controller = self::$request['controller'];
        self::$controller = new $controller();
    }

    /**
     * @return array
     */
    protected static function setParams(): array
    {
        $params = explode("/", self::$request['path']);
        $uriPart = self::getParts(self::$request['targetRoute']);
        $outPut = array();

        foreach ($params as $key => $param) {
            if (!$pos = self::isParam($param))
                continue;

            $length = strlen($param);
            $indexStart = $pos[0] + 1;
            $indexEnd = $length - 2;
            $paramName = substr($param, $indexStart, $indexEnd);
            $outPut[$paramName] = $uriPart[$key];
        }

        return $outPut;
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

    /**
     * @throws DispatcherException
     */
    protected static function setAction()
    {
        self::$action = self::$request['action'];

        if (method_exists(self::$controller, self::$action)) {
            $params = self::setParams();
            $action = self::$action;

            return self::$controller->$action($params);
        }

        throw new DispatcherException('Action is not valid');
    }
}