<?php
$dir = __DIR__;
// Hledáme běžné způsoby, jak se v PHP připojuje k databázi
$searchStrings = ['mysqli_connect', 'new PDO', 'new mysqli', 'mysql_connect', 'db_host', 'db_user', 'DB_PASSWORD'];

echo "<h2>Hledám zapomenutá připojení k DB v /subdom/test/:</h2>";
echo "<div style='font-family: monospace;'>";

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
foreach ($files as $file) {
    // Ignorujeme složky, ne-PHP soubory a tenhle skript samotný
    if ($file->isDir() || $file->getExtension() !== 'php' || $file->getFilename() === 'upload-najdi-db.php') continue;

    $content = file($file->getPathname());
    $foundInFile = false;

    foreach ($content as $lineNumber => $line) {
        foreach ($searchStrings as $search) {
            if (stripos($line, $search) !== false) {
                if (!$foundInFile) {
                    echo "<br><b>Soubor: " . $file->getPathname() . "</b><br>";
                    $foundInFile = true;
                }
                echo "Řádek " . ($lineNumber + 1) . ": <span style='color: red;'>" . htmlspecialchars(trim($line)) . "</span><br>";
            }
        }
    }
}
echo "</div><br><b>Hledání dokončeno.</b>";
?>