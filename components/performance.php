<?php
/**
 * Performance Monitoring and Optimization Tool
 * Use this to track page load times and identify bottlenecks
 */

class PerformanceMonitor {
    private $startTime;
    private $markers = [];
    private $queries = [];
    
    public function __construct() {
        $this->startTime = microtime(true);
        $this->markers['start'] = $this->startTime;
    }
    
    public function mark($name) {
        $this->markers[$name] = microtime(true);
    }
    
    public function trackQuery($sql, $executionTime) {
        $this->queries[] = [
            'sql' => $sql,
            'time' => $executionTime,
            'timestamp' => microtime(true)
        ];
    }
    
    public function getStats() {
        $endTime = microtime(true);
        $this->markers['end'] = $endTime;
        
        return [
            'total_time' => $endTime - $this->startTime,
            'memory_usage' => memory_get_peak_usage(true),
            'markers' => $this->markers,
            'query_count' => count($this->queries),
            'query_time' => array_sum(array_column($this->queries, 'time')),
            'queries' => $this->queries
        ];
    }
    
    public function output() {
        if (isset($_GET['debug']) && $_SESSION['role'] === 'admin') {
            $stats = $this->getStats();
            echo "<div style='position:fixed;bottom:0;right:0;background:#000;color:#fff;padding:10px;font-size:12px;z-index:9999;'>";
            echo "Time: " . round($stats['total_time'] * 1000, 2) . "ms | ";
            echo "Memory: " . round($stats['memory_usage'] / 1024 / 1024, 2) . "MB | ";
            echo "Queries: " . $stats['query_count'];
            echo "</div>";
        }
    }
}

// Global performance monitor
$perfMonitor = new PerformanceMonitor();

// Function to track database queries
function trackQuery($sql, $callback) {
    global $perfMonitor;
    
    $start = microtime(true);
    $result = $callback();
    $end = microtime(true);
    
    $perfMonitor->trackQuery($sql, $end - $start);
    return $result;
}
?>
