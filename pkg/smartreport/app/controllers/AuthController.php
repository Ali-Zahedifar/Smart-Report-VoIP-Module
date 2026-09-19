<?php

namespace SmartReport\Controllers;

use SmartReport\Core\Auth;
use SmartReport\Core\Controller;
use SmartReport\Core\Csrf;
use SmartReport\Core\Response;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            redirect_to('/');
        }
        $this->view('auth/login', ['title' => t('login.title')], false);
    }

    public function login()
    {
        if (Auth::check()) {
            redirect_to('/');
        }
        if (!Csrf::validate()) {
            Response::forbidden(t('csrf.failed'));
        }
        $username = trim((string) $this->post('username', ''));
        $password = (string) $this->post('password', '');
        $locked = Auth::lockedSeconds();
        if ($locked > 0) {
            $this->redirect('/login', sprintf(t('login.locked'), $locked), 'error');
        }
        if ($username === '' || $password === '') {
            $this->redirect('/login', t('login.required'), 'error');
        }
        if (Auth::attempt($username, $password)) {
            $intended = isset($_SESSION['_intended']) && is_string($_SESSION['_intended']) ? $_SESSION['_intended'] : null;
            unset($_SESSION['_intended']);
            $this->redirect($intended !== null && starts($intended, '/') ? $intended : '/');
        }
        $locked = Auth::lockedSeconds();
        if ($locked > 0) {
            $this->redirect('/login', sprintf(t('login.locked'), $locked), 'error');
        }
        $this->redirect('/login', t('login.failed'), 'error');
    }

    public function logout()
    {
        Auth::logout();
        $this->redirect('/login', t('logout.success'));
    }
}