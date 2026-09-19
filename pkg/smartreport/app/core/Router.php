<?php

namespace SmartReport\Core;

use RuntimeException;

final class Router
{
    private static $instance = null;
    private $coreRoutes = [];

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function coreRoute($pattern, array $handler)
    {
        self::instance()->coreRoutes[$pattern] = $handler;
    }

    public function dispatch()
    {
        Lang::ensureInit();
        $path = current_path();
        unset($_GET['route']);
        $method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET');

        foreach ($this->coreRoutes() as $pattern => $handler) {
            $params = $this->match($pattern, $method, $path);
            if ($params !== null) {
                $this->run($handler, $params, $method, $path);
                return;
            }
        }

        if (!App::ready()) {
            View::render('setup', ['title' => Lang::t('not_installed.title')], false);
            return;
        }

        foreach (FeatureRegistry::routes() as $pattern => $handler) {
            $params = $this->match($pattern, $method, $path);
            if ($params !== null) {
                $this->run($handler, $params, $method, $path);
                return;
            }
        }

        Response::notFound();
    }

    private function coreRoutes()
    {
        if ($this->coreRoutes === []) {
            $file = SMR_APP . '/core/routes.php';
            if (is_file($file)) {
                $routes = require $file;
                if (is_array($routes)) {
                    $this->coreRoutes = $routes;
                }
            }
        }
        return $this->coreRoutes;
    }

    private function match($pattern, $method, $path)
    {
        $parts = explode(' ', $pattern, 2);
        $patternMethod = strtoupper($parts[0]);
        if (count($parts) !== 2 || $method !== $patternMethod) {
            return null;
        }
        $pathPattern = rtrim($parts[1], '/');
        if ($pathPattern === '') {
            $pathPattern = '/';
        }
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pathPattern);
        $regex = '#^' . $regex . '/?$#D';
        if (!preg_match($regex, $path, $matches)) {
            return null;
        }
        $params = [];
        foreach ($matches as $key => $value) {
            if (is_int($key)) {
                continue;
            }
            $params[$key] = $value;
        }
        return $params;
    }

    private function run(array $handler, array $params, $method, $path)
    {
        $roles = isset($handler[1]) ? $handler[1] : [];
        if (!empty($roles)) {
            if (!Auth::check()) {
                if (!SMR_CLI) {
                    $_SESSION['_intended'] = $path;
                }
                redirect_to('/login');
            }
            if (!Acl::userHasRole($roles)) {
                Response::forbidden();
            }
        }

        if ($method === 'POST' && !Csrf::validate()) {
            Response::forbidden(Lang::t('csrf.failed'));
        }

        $target = isset($handler[0]) ? $handler[0] : '';
        $parts = explode('@', $target, 2);
        if (count($parts) !== 2) {
            throw new RuntimeException('Invalid route handler: ' . $target);
        }
        list($class, $action) = $parts;
        if (!class_exists($class)) {
            throw new RuntimeException('Route controller not found: ' . $class);
        }
        $controller = new $class();
        if (!method_exists($controller, $action)) {
            throw new RuntimeException('Route action not found: ' . $target);
        }
        call_user_func_array([$controller, $action], array_values($params));
    }
}