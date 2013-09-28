<?php

define('WEBROOT', realpath(__DIR__));
define('ROOT', dirname(WEBROOT));
require_once ROOT . '/vendor/autoload.php';

$app = require_once ROOT . '/app/bootstrap.php';

$app->run();
