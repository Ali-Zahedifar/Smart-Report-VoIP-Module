<?php

namespace SmartReport\Controllers;

use SmartReport\Core\Controller;
use SmartReport\Core\Lang;

class LocaleController extends Controller
{
    public function switchLanguage($code)
    {
        Lang::set($code);
        redirect_to('/');
    }
}