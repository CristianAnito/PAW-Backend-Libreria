<?php

namespace App\Core;

class Router
{
    protected array $routes = [];

    public function add(string $method, string $uri, array $action): void
    {
        $this->routes[strtoupper($method)][$uri] = $action;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $uri = $request->uri();

        // Direct match
        if (isset($this->routes[$method][$uri])) {
            return $this->callAction($this->routes[$method][$uri], []);
        }

        // Dynamic route matching (e.g. /catalogo/{slug} or /reservar/{slug})
        foreach ($this->routes[$method] ?? [] as $routeUri => $action) {
            $pattern = $this->convertToRegex($routeUri);
            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches); // Remove full match
                return $this->callAction($action, $matches);
            }
        }

        // 404 Not Found
        $controller = new \App\Controllers\ErrorController();
        return $controller->notFound();
    }

    protected function convertToRegex(string $routeUri): string
    {
        // Convert {param} to named/unnamed regex capture group
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([a-zA-Z0-9\-_]+)', $routeUri);
        return "#^" . $pattern . "$#";
    }

    protected function callAction(array $action, array $params): Response
    {
        [$controllerClass, $method] = $action;
        
        if (!class_exists($controllerClass)) {
            throw new \Exception("Controller not found: {$controllerClass}");
        }

        $controller = new $controllerClass();

        if (!method_exists($controller, $method)) {
            throw new \Exception("Method {$method} not found in controller {$controllerClass}");
        }

        return call_user_func_array([$controller, $method], $params);
    }
}
