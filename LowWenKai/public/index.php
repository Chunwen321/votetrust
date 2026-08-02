<?php

declare(strict_types=1);

use VoteTrust\Election\Core\Application;
use VoteTrust\Election\Core\Config;
use VoteTrust\Election\Core\Request;
use VoteTrust\Election\Core\Router;
use VoteTrust\Election\Core\Session;

$root = dirname(__DIR__);
require $root . '/autoload.php';
Config::load($root);
date_default_timezone_set((string) Config::get('APP_TIMEZONE', 'Asia/Kuala_Lumpur'));
Session::start($root . '/storage/sessions');

$router = new Router();
(require $root . '/routes/web.php')($router);
(require $root . '/routes/api.php')($router);

(new Application($router))->run(Request::capture());

