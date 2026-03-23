<?php

// Načtení knihoven ze složky vendor
require __DIR__ . '/vendor/autoload.php';

use Kreait\Firebase\Factory;

// Cesta k tvému klíči v tajné složce
$serviceAccount = __DIR__ . '/secret/firebase-auth.json';

// Inicializace
$factory = (new Factory)
    ->withServiceAccount($serviceAccount)
    ->withDatabaseUri('https://edosystest-default-rtdb.europe-west1.firebasedatabase.app/'); // Nezapomeň ji tam dát!

$database = $factory->createDatabase();

// Zápis testovacích dat
$database->getReference('edosys/status')->set([
    'message' => 'Vibecoding v plném proudu!',
    'time' => date('H:i:s'),
    'platform' => 'PHPStorm + Wedos'
]);

echo "BOMBA! Data by měla být ve Firebase. Koukni se do konzole na kartu Data.";