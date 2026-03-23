<?php

require __DIR__ . '/vendor/autoload.php';

use Kreait\Firebase\Factory;

// Cesta k tvojmu kľúču (používame __DIR__, aby to fungovalo aj na Wedose)
$serviceAccount = __DIR__ . '/secret/firebase-auth.json';

$factory = (new Factory)
    ->withServiceAccount($serviceAccount)
    ->withDatabaseUri('https://TVOJE-ID-PROJEKTU.europe-west1.firebasedatabase.app/');

$database = $factory->createDatabase();

// Skúsme malý test - zapíšeme čas prihlásenia
$database->getReference('logs/last_login')->set(date('Y-m-d H:i:s'));

echo "Firebase je pripravený!";