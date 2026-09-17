<?php

declare(strict_types=1);

namespace SmartReport\Core;

abstract class Controller
{
    protected function view(string $template, array $data = []): void
    {
        View::render($template, $data);
    }

    protected function partial(string $template, array $data = []): string
    {
        return View::partial($template, $data);
    }

    protected function redirect(string $path, ?string $flashMessage = null, string $type = 'info'): void
    {
        if ($flashMessage !== null) {
            set_flash($type, $flashMessage);
        }
        redirect_to($path);
    }

    protected function requireRole(array $roles): void
    {
        Acl::userHasRole($roles);
    }

    protected function request(string $key, $default = null)
    {
        return $_REQUEST[$key] ?? $default;
    }

    protected function post(string $key, $default = null)
    {
        return $_POST[$key] ?? $default;
    }

    protected function query(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    protected function json(array $data, int $status = 200): void
    {
        Response::json($data, $status);
    }
}