<?php

use Emanueletoffolon\MysqlIseed\ISeed;

require __DIR__.'/../vendor/autoload.php';

$iseed = new ISeed(['host'=>'localhost','db'=>'database_name','username'=>'database_username','password'=>'database_password','path_files'=>__DIR__.'/seeders/']);

$iseed->addTables('*');
$iseed->generate();

//$iseed->load();

