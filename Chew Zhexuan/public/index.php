<?php

declare(strict_types=1);

use VoteTrust\UserAdmin\Core\Application;
use VoteTrust\UserAdmin\Core\Config;
use VoteTrust\UserAdmin\Core\Request;
use VoteTrust\UserAdmin\Core\Router;
use VoteTrust\UserAdmin\Core\Session;

$root = dirname(__DIR__);
require $root . '/autoload.php';

Config::load($root);
date_default_timezone_set((string) Config::get('APP_TIMEZONE', 'Asia/Kuala_Lumpur'));
Session::start($root, (string) Config::get('SESSION_STORAGE', 'storage/sessions'));

$router = new Router();
(require $root . '/routes/web.php')($router);
(require $root . '/routes/api.php')($router);

(new Application($router))->run(Request::capture());
