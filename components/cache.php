<?php
/**
 * Simple File-based Caching System
 */

class SimpleCache {
    private $cacheDir;
    private $defaultTTL = 3600; // 1 hour
    
    public function __construct($cacheDir = 'cache/') {
        $this->cacheDir = __DIR__ . '/' . $cacheDir;
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    public function get($key) {
        $filename = $this->getFilename($key);
        
        if (!file_exists($filename)) {
            return null;
        }
        
        $data = file_get_contents($filename);
        $cache = unserialize($data);
        
        // Check if cache has expired
        if ($cache['expires'] < time()) {
            unlink($filename);
            return null;
        }
        
        return $cache['data'];
    }
    
    public function set($key, $data, $ttl = null) {
        $ttl = $ttl ?: $this->defaultTTL;
        $filename = $this->getFilename($key);
        
        $cache = [
            'data' => $data,
            'expires' => time() + $ttl
        ];
        
        return file_put_contents($filename, serialize($cache)) !== false;
    }
    
    public function delete($key) {
        $filename = $this->getFilename($key);
        if (file_exists($filename)) {
            return unlink($filename);
        }
        return true;
    }
    
    public function clear() {
        $files = glob($this->cacheDir . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
        return true;
    }
    
    private function getFilename($key) {
        return $this->cacheDir . md5($key) . '.cache';
    }
}

// Global cache instance
$cache = new SimpleCache();

/**
 * Helper function to cache database queries
 */
function cacheQuery($key, $callback, $ttl = 3600) {
    global $cache;
    
    $data = $cache->get($key);
    if ($data === null) {
        $data = $callback();
        $cache->set($key, $data, $ttl);
    }
    
    return $data;
}
?>
