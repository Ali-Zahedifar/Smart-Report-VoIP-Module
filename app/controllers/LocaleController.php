<?php

declare(strict_types=1);

namespace SmartReport\Controllers;

use SmartReport\Core\Controller;
use SmartReport\Core\Lang;

class LocaleController extends Controller
{
    public function switch(string $code): void
    {
        Lang::set($code);
        redirect_to('/');
    }
}