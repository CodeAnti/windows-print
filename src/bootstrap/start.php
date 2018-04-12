<?php

use Ratchet\App;
use Lib\PrintServer;

/**
 * Created by PhpStorm.
 * User: CodeAnti
 * Date: 2018/4/11
 * Time: 15:44
 */
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/Lib/PrintServer.php';

$app = new App('127.0.0.1', 9527);
$app->route('/print', new PrintServer(), ['*']);
$app->run();