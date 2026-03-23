<?php
require_once __DIR__ . '/../../access.php';

// Define all upload folders to search (document storage directories)
$searchDirs = [
    'edo/physical'      => 'eDO - Fyzické běhy',
    'edo/online'        => 'eDO - Online',
    'test'              => 'Okruhy',
    'seznam'            => 'Odborná zkouška',
    'agreements'        => 'Smlouvy',
    'marketing'         => 'Marketing',
    'courses'           => 'Kurzy a školení',
    'links'             => 'Odkazy',
    'pdf'               => 'PDF Magazíny',
    'other'             => 'Ostatní',
    'contacts'          => 'Kontakty',
    'podcast'           => 'Podcasty',
    'videos'            => 'Videa',
    'documents'         => 'Knihovna'
];

// Get search query
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

// Gather all files
$results = [];
if ($query !== '') {
    foreach ($searchDirs as $dir => $label) {
        $fullDir = __DIR__ . '/../../' . $dir . '/';
        if (!is_dir($fullDir)) continue;
        
        try {
            // Search recursively for files
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($fullDir, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $file) {
                if ($file->isDir()) continue;
                
                $filename = $file->getFilename();
                $extension = strtolower($file->getExtension());
                
                // Skip system files and non-document files
                if (in_array($extension, ['php', 'js', 'css', 'html', 'htaccess', 'json'])) continue;
                if (strpos($filename, '.') === 0) continue; // Skip hidden files
                
                // Check if filename contains query (case-insensitive)
                if (stripos($filename, $query) !== false) {
                    $relPath = str_replace(__DIR__ . '/../../', '', $file->getPathname());
                    $relPath = str_replace('\\', '/', $relPath);
                    
                    $results[] = [
                        'category' => $label,
                        'filename' => $filename,
                        'filepath' => $relPath,
                        'url' => '/' . $relPath,
                        'size' => $file->getSize(),
                        'modified' => $file->getMTime(),
                        'extension' => $extension
                    ];
                }
            }
        } catch (Exception $e) {
            // Skip directories that can't be read
            continue;
        }
    }
    
    // Sort results by relevance (exact matches first, then by filename)
    usort($results, function($a, $b) use ($query) {
        $aExact = (stripos($a['filename'], $query) === 0) ? 0 : 1;
        $bExact = (stripos($b['filename'], $query) === 0) ? 0 : 1;
        
        if ($aExact !== $bExact) {
            return $aExact - $bExact;
        }
        
        return strcasecmp($a['filename'], $b['filename']);
    });
}

// Helper function to format file size
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    return round($bytes, 1) . ' ' . $units[$i];
}

// Helper function to get file type icon
function getFileIcon($extension) {
    $icons = [
        'pdf' => 'fas fa-file-pdf text-danger',
        'doc' => 'fas fa-file-word text-primary',
        'docx' => 'fas fa-file-word text-primary',
        'xls' => 'fas fa-file-excel text-success',
        'xlsx' => 'fas fa-file-excel text-success',
        'ppt' => 'fas fa-file-powerpoint text-warning',
        'pptx' => 'fas fa-file-powerpoint text-warning',
        'txt' => 'fas fa-file-alt text-secondary',
        'mp3' => 'fas fa-file-audio text-info',
        'wav' => 'fas fa-file-audio text-info',
        'mp4' => 'fas fa-file-video text-danger',
        'avi' => 'fas fa-file-video text-danger',
        'jpg' => 'fas fa-file-image text-success',
        'jpeg' => 'fas fa-file-image text-success',
        'png' => 'fas fa-file-image text-success',
        'gif' => 'fas fa-file-image text-success',
        'zip' => 'fas fa-file-archive text-warning',
        'rar' => 'fas fa-file-archive text-warning',
        'json' => 'fas fa-file-code text-info'
    ];
    
    return $icons[$extension] ?? 'fas fa-file text-muted';
}
?>

<div class="page-content" id="search">
    <div class="content-area">
        <div class="search-container">
            <div class="search-header">
                <h1><i class="fas fa-search"></i> Vyhledávání dokumentů</h1>
                <p>Prohledejte všechny nahrané dokumenty v systému</p>
            </div>
            
            <form method="get" class="search-form">
                <div class="search-input-group">
                    <input type="text" name="q" value="<?php echo htmlspecialchars($query); ?>" 
                           placeholder="Zadejte název souboru nebo část názvu..." 
                           class="search-input" autocomplete="off">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i> Hledat
                    </button>
                </div>
            </form>

            <?php if ($query !== ''): ?>
                <div class="search-results">
                    <div class="results-header">
                        <h3>Výsledky pro "<?php echo htmlspecialchars($query); ?>"</h3>
                        <span class="results-count"><?php echo count($results); ?> 
                            <?php echo count($results) == 1 ? 'nalezený soubor' : 'nalezených souborů'; ?>
                        </span>
                    </div>
                    
                    <?php if (count($results) > 0): ?>
                        <div class="results-list">
                            <?php foreach ($results as $doc): ?>
                                <div class="result-item">
                                    <div class="result-icon">
                                        <i class="<?php echo getFileIcon($doc['extension']); ?>"></i>
                                    </div>
                                    <div class="result-info">
                                        <div class="result-filename">
                                            <a href="<?php echo htmlspecialchars($doc['url']); ?>" target="_blank">
                                                <?php echo htmlspecialchars($doc['filename']); ?>
                                            </a>
                                        </div>
                                        <div class="result-meta">
                                            <span class="result-category">
                                                <i class="fas fa-folder"></i> <?php echo htmlspecialchars($doc['category']); ?>
                                            </span>
                                            <span class="result-size">
                                                <i class="fas fa-weight-hanging"></i> <?php echo formatFileSize($doc['size']); ?>
                                            </span>
                                            <span class="result-modified">
                                                <i class="fas fa-calendar"></i> <?php echo date('d.m.Y H:i', $doc['modified']); ?>
                                            </span>
                                        </div>
                                        <div class="result-path">
                                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($doc['filepath']); ?>
                                        </div>
                                    </div>
                                    <div class="result-actions">
                                        <a href="<?php echo htmlspecialchars($doc['url']); ?>" target="_blank" 
                                           class="action-btn view-btn" title="Otevřít">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                        <a href="<?php echo htmlspecialchars($doc['url']); ?>" download 
                                           class="action-btn download-btn" title="Stáhnout">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-results">
                            <i class="fas fa-search-minus"></i>
                            <h3>Žádné výsledky</h3>
                            <p>Pro hledaný výraz "<?php echo htmlspecialchars($query); ?>" nebyly nalezeny žádné soubory.</p>
                            <div class="search-tips">
                                <h4>Tipy pro vyhledávání:</h4>
                                <ul>
                                    <li>Zkontrolujte překlepy</li>
                                    <li>Zkuste kratší nebo obecnější výrazy</li>
                                    <li>Hledejte podle části názvu souboru</li>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="search-welcome">
                    <div class="welcome-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3>Vyhledávejte v dokumentech</h3>
                    <p>Zadejte název nebo část názvu souboru pro vyhledávání ve všech kategoriích dokumentů.</p>
                    
                    <div class="search-categories">
                        <h4>Prohledávané kategorie:</h4>
                        <div class="categories-grid">
                            <?php foreach ($searchDirs as $dir => $label): ?>
                                <div class="category-badge">
                                    <i class="fas fa-folder"></i> <?php echo htmlspecialchars($label); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    /* Search page styling - simplified to match global design */
    #search .content-area {
        display: flex;
        flex-direction: column;
        gap: 30px;
    }

    #search .search-container {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    #search .search-header {
        background: white;
        padding: 30px;
        text-align: center;
        border-bottom: 1px solid #e9ecef;
    }

    #search .search-header h1 {
        color: #2c3e50;
        margin: 0 0 10px 0;
        font-size: 28px;
        font-weight: 600;
    }

    #search .search-header p {
        color: #7f8c8d;
        margin: 0;
        font-size: 16px;
    }

    #search .search-form {
        padding: 30px;
        background: white;
        border-bottom: 1px solid #e9ecef;
    }

    #search .search-input-group {
        display: flex;
        gap: 10px;
        max-width: 600px;
        margin: 0 auto;
    }

    #search .search-input {
        flex: 1;
        padding: 12px 15px;
        font-size: 16px;
        border: 1px solid #ddd;
        border-radius: 5px;
        outline: none;
        transition: border-color 0.2s;
    }

    #search .search-input:focus {
        border-color: #3498db;
        box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
    }

    #search .search-btn {
        padding: 12px 20px;
        background: #3498db;
        color: white;
        border: none;
        border-radius: 5px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    #search .search-btn:hover {
        background: #2980b9;
    }

    #search .search-results {
        padding: 30px;
    }

    #search .results-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 1px solid #e9ecef;
    }

    #search .results-header h3 {
        margin: 0;
        color: #2c3e50;
        font-size: 20px;
    }

    #search .results-count {
        color: #7f8c8d;
        font-weight: 500;
        background: #ecf0f1;
        padding: 5px 12px;
        border-radius: 15px;
        font-size: 14px;
    }

    #search .results-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    #search .result-item {
        display: flex;
        align-items: center;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 8px;
        border: 1px solid #e9ecef;
        transition: all 0.2s;
    }

    #search .result-item:hover {
        background: white;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transform: translateY(-1px);
    }

    #search .result-icon {
        font-size: 20px;
        margin-right: 15px;
        flex-shrink: 0;
        color: #7f8c8d;
    }

    #search .result-info {
        flex: 1;
        min-width: 0;
    }

    #search .result-filename {
        margin-bottom: 8px;
    }

    #search .result-filename a {
        color: #2980b9;
        text-decoration: none;
        font-weight: 600;
        font-size: 16px;
    }

    #search .result-filename a:hover {
        text-decoration: underline;
    }

    #search .result-meta {
        display: flex;
        gap: 15px;
        margin-bottom: 5px;
        font-size: 13px;
        color: #7f8c8d;
        flex-wrap: wrap;
    }

    #search .result-meta span {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    #search .result-path {
        font-size: 12px;
        color: #95a5a6;
        font-family: inherit;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    #search .result-actions {
        display: flex;
        gap: 8px;
        flex-shrink: 0;
    }

    #search .action-btn {
        padding: 8px 12px;
        border-radius: 5px;
        text-decoration: none;
        font-size: 12px;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 500;
    }

    #search .view-btn {
        background: #3498db;
        color: white;
    }

    #search .view-btn:hover {
        background: #2980b9;
        color: white;
    }

    #search .download-btn {
        background: #2ecc71;
        color: white;
    }

    #search .download-btn:hover {
        background: #27ae60;
        color: white;
    }

    #search .no-results {
        text-align: center;
        padding: 60px 20px;
        color: #7f8c8d;
    }

    #search .no-results i {
        font-size: 48px;
        margin-bottom: 20px;
        opacity: 0.5;
    }

    #search .no-results h3 {
        margin: 0 0 15px 0;
        color: #2c3e50;
        font-size: 20px;
    }

    #search .search-tips {
        margin-top: 30px;
        text-align: left;
        max-width: 300px;
        margin-left: auto;
        margin-right: auto;
    }

    #search .search-tips h4 {
        color: #2c3e50;
        margin-bottom: 10px;
        font-size: 16px;
    }

    #search .search-tips ul {
        margin: 0;
        padding-left: 20px;
        color: #7f8c8d;
    }

    #search .search-welcome {
        padding: 60px 30px;
        text-align: center;
    }

    #search .welcome-icon {
        font-size: 64px;
        color: #3498db;
        margin-bottom: 20px;
        opacity: 0.6;
    }

    #search .search-welcome h3 {
        color: #2c3e50;
        margin: 0 0 15px 0;
        font-size: 24px;
    }

    #search .search-welcome p {
        color: #7f8c8d;
        margin: 0 0 40px 0;
        font-size: 16px;
        max-width: 500px;
        margin-left: auto;
        margin-right: auto;
        line-height: 1.5;
    }

    #search .search-categories h4 {
        color: #2c3e50;
        margin: 0 0 20px 0;
        font-size: 18px;
    }

    #search .categories-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 10px;
        max-width: 800px;
        margin: 0 auto;
    }

    #search .category-badge {
        background: #ecf0f1;
        padding: 10px 15px;
        border-radius: 5px;
        font-size: 14px;
        color: #2c3e50;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        border: 1px solid #e9ecef;
        transition: all 0.2s;
    }

    #search .category-badge:hover {
        background: #d5dbdb;
    }

    /* Responsive design - only for search page */
    @media (max-width: 768px) {
        #search .search-input_group {
            flex-direction: column;
            gap: 10px;
        }

        #search .search-input,
        #search .search-btn {
            border-radius: 5px;
        }

        #search .result-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }

        #search .result-actions {
            align-self: stretch;
            justify-content: center;
        }

        #search .result-meta {
            flex-direction: column;
            gap: 8px;
        }

        #search .categories-grid {
            grid-template-columns: 1fr;
        }

        #search .results-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }
    }
</style>
