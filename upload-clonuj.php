<?php
// Cesta ke zdroji (aktuální složka) a cíli (subdoména)
$source = __DIR__ . '/';
$dest = __DIR__ . '/subdom/test/';

// SEZNAM SLOŽEK, které v cíli vytvoříme, ale necháme PRÁZDNÉ
$createEmpty = [
    'agreements', 'bulletin', 'courses', 'doc', 'doc-poradce', 
    'documents', 'edo', 'img', 'img_bulletin', 'marketing', 
    'pdf', 'pdf-poradce', 'podcast', 'podcast-poradce', 
    'temp_uploads', 'uploads', 'videos', 'videos-poradce', 'seznam'
];

// CO ÚPLNĚ IGNOROVAT (nekopírovat ani nevytvářet)
$ignoreTotally = ['subdom', '.github', '.idea', '.vscode', 'clonuj.php', 'node_modules', '.git', 'snyk.exe', 'exclude.synk'];

function smartClone($src, $dst, $emptyDirs, $ignore) {
    if (!is_dir($dst)) {
        @mkdir($dst, 0777, true);
    }

    $dir = opendir($src);
    while (false !== ($file = readdir($dir))) {
        if ($file == '.' || $file == '..') continue;
        if (in_array($file, $ignore)) continue;

        $srcPath = $src . $file;
        $dstPath = $dst . $file;

        if (is_dir($srcPath)) {
            // Vytvoříme složku v cíli
            if (!is_dir($dstPath)) {
                @mkdir($dstPath, 0777, true);
                echo "Vytvořena složka: <b>$file</b><br>";
            }

            // Pokud má zůstat prázdná, nepokračujeme dovnitř
            if (in_array($file, $emptyDirs)) {
                echo "-- <i>Složka $file ponechána prázdná</i> --<br>";
                continue;
            }

            // Pokud je to složka s kódem (assets, css, js...), jdeme dovnitř
            smartClone($srcPath . '/', $dstPath . '/', $emptyDirs, $ignore);
        } else {
            // Je to soubor (PHP skripty, configy atd.) - zkopírujeme
            copy($srcPath, $dstPath);
        }
    }
    closedir($dir);
}

echo "<h2>Stavím testovací sandbox v /subdom/test/</h2>";
echo "<div style='font-family: monospace; background: #f4f4f4; padding: 10px; border: 1px solid #ccc;'>";
flush();

smartClone($source, $dest, $createEmpty, $ignoreTotally);

echo "</div>";
echo "<h3 style='color: green;'>Hotovo!</h3>";
echo "Teď už jen v <b>/subdom/test/config.php</b> (nebo db-config.php) nastav novou databázi.";
?>