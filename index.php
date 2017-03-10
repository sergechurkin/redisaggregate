<?php

$loader = require( __DIR__ . '/vendor/autoload.php' );
//$loader->addPsr4( 'sergechurkin\cform\\', __DIR__ . '/vendor/sergechurkin/cform/' );
$loader->addPsr4( 'redisaggregate\\', __DIR__ . '/src/' );

use redisaggregate\ControllerRedis;

$params = require('./src/params.php');
(new ControllerRedis())->run($params);

