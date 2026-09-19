<?php

namespace SmartReport\Core;

abstract class Controller
{
    protected function view($template, array $data = [])
    {
        View::render($template, $data);
    }

    protected function partial($template, array $data = [])
    {
        return View::partial($template, $data);
    }

    protected function redirect($path, $flashMessage = null, $type = 'info')
    {
        if ($flashMessage !== null) {
            set_flash($type, $flashMessage);
        }
        redirect_to($path);
    }

    protected function requireRole(array $roles)
    {
        Acl::userHasRole($roles);
    }

    protected function request($key, $default = null)
    {
        return isset($_REQUEST[$key]) ? $_REQUEST[$key] : $default;
    }

    protected function post($key, $default = null)
    {
        return isset($_POST[$key]) ? $_POST[$key] : $default;
    }

    protected function query($key, $default = null)
    {
        return isset($_GET[$key]) ? $_GET[$key] : $default;
    }

    protected function json(array $data, $status = 200)
    {
        Response::json($data, $status);
    }
}