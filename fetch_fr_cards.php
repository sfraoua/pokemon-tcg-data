<?php

use App\Extractor\TcgdexFr;

require_once 'vendor/autoload.php';

ini_set('display_startup_errors', 1);
//$extractor = new TcgdexFr();
//$extractor->Extract();
//$extractor->ExtractImages('fr');
$extractor = new \App\Extractor\UpdateNumbers();
$extractor->UpdateCards();
