<?php

require_once 'vendor/autoload.php';
use App\Extractor\ImportEnergies;


ini_set('display_startup_errors', 1);
$extractor = new \App\Extractor\ImportEnergies();
$extractor->Import();
