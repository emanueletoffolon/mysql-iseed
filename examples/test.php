<?php

use Emanueletoffolon\MysqlIseed\ISeed;

require __DIR__.'/../vendor/autoload.php';

$iseed = new ISeed(['host'=>'localhost','db'=>'gieffeco_db','username'=>'homestead','password'=>'secret','path_files'=>__DIR__.'/seeders/']);

$iseed->addTables(['linguaggio','linguaggio_valori_text_lang']);
$iseed->generate();

$iseed->load();

