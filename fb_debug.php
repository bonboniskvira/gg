<?php
// 1. Totální výpis chyb (uvidíš, co se děje)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "--- START TESTU ---<br>";

// 2. Načtení Firebase (cesta musí sedět k tvé složce vendor)
require __DIR__ . '/vendor/autoload.php';
use Kreait\Firebase\Factory;

try {
    // 3. Cesta k tvému JSON klíči v tajné složce
    $serviceAccount = __DIR__ . '/secret/firebase-auth.json';

    if (!file_exists($serviceAccount)) {
        throw new Exception("Chyba: Soubor s klíčem v 'secret/' neexistuje!");
    }

    // 4. Inicializace (používám URL z tvého screenshotu)
    $factory = (new Factory)
        ->withServiceAccount($serviceAccount)
        ->withDatabaseUri('https://edosystest-default-rtdb.europe-west1.firebasedatabase.app/');

    $database = $factory->createDatabase();

    // 5. Zápis - tohle musí rozsvítit Firebase!
    $database->getReference('test/martin-vibe')->set([
        'cas' => date('H:i:s'),
        'zprava' => 'Pokud tohle vidis, tak to funguje!',
        'server' => $_SERVER['SERVER_NAME']
    ]);

    echo "<b>BOMBA! Data byla odeslána. Koukni se do Firebase konzole!</b>";

} catch (Exception $e) {
    echo "<b>CHYBA:</b> " . $e->getMessage();
}

echo "<br>--- KONEC TESTU ---";