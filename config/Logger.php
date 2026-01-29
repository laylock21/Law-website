<?php
/**
 * Comprehensive Logging System
 * Provides structured logging for debugging, monitoring, and analytics
 */

class Logger {
    private static $log_dir = null;
    private static $log_levels = [
        'DEBUG' => 0,
        'INFO' => 1,
        'WARNING' => 2,
        'ERROR' => 3,
        'CRITICAL' => 4
    ];
    private static $current_level = 'INFO';
    private static $max_file_size = 10485760; // 10MB
    private static $max_files = 5;
    
    /**
     * Initialize logging system
     */
    public static function init($level = 'INFO') {
        self::$current_level = $level;
        
        // Set absolute path to logs directory (relative to document root)
        if (self::$log_dir === null) {
            // Find the project root by looking for config directory
            $current_dir = __DIR__;
            $project_root = dirname($current_dir); // Go up from config/ to root
            self::$log_dir = $project_root . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR;
        }
        
        // Create logs directory if it doesn't exist
        if (!is_dir(self::$log_dir)) {
            mkdir(self::$log_dir, 0755, true);
        }
        
        // Set up log rotation
        self::rotateLogs();
    }
    
    /**
     * Log debug message
     */
    public static function debug($message, $context = []) {
        self::log('DEBUG', $message, $context);
    }
    
    /**
     * Log info message
     */
    public static function info($message, $context = []) {
        self::log('INFO', $message, $context);
    }
    
    /**
     * Log warning message
     */
    public static function warning($message, $context = []) {
        self::log('WARNING', $message, $context);
    }
    
    /**
     * Log error message
     */
    public static function error($message, $context = []) {
        self::log('ERROR', $message, $context);
    }
    
    /**
     * Log critical message
     */
    public static function critical($message, $context = []) {
        self::log('CRITICAL', $message, $context);
    }
    
    /**
     * Log user action
     */
    public static function userAction($action, $user_id = null, $details = []) {
        $context = array_merge([
            'user_id' => $user_id ?: ($_SESSION['user_id'] ?? 'anonymous'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'action' => $action
        ], $details);
        
        self::log('INFO', "User action: {$action}", $context);
    }
    
    /**
     * Log API request
     */
    public static function apiRequest($endpoint, $method, $status_code, $response_time = null) {
        $context = [
            'endpoint' => $endpoint,
            'method' => $method,
            'status_code' => $status_code,
            'response_time' => $response_time,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        self::log('INFO', "API Request: {$method} {$endpoint} - {$status_code}", $context);
    }
    
    /**
     * Log database query
     */
    public static function databaseQuery($query, $execution_time = null, $affected_rows = null) {
        $context = [
            'query' => $query,
            'execution_time' => $execution_time,
            'affected_rows' => $affected_rows,
            'user_id' => $_SESSION['user_id'] ?? 'anonymous'
        ];
        
        self::log('DEBUG', 'Database Query', $context);
    }
    
    /**
     * Log security event
     */
    public static function security($event, $details = []) {
        $context = array_merge([
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'event' => $event
        ], $details);
        
        self::log('WARNING', "Security Event: {$event}", $context);
    }
    
    /**
     * Core logging method
     */
    private static function log($level, $message, $context = []) {
        // Check if we should log this level
        if (self::$log_levels[$level] < self::$log_levels[self::$current_level]) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
        
        // Write to file
        $log_file = self::$log_dir . 'application_' . date('Y-m-d') . '.log';
        $log_line = json_encode($log_entry) . "\n";
        
        file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
        
        // Also write to error log for critical errors
        if ($level === 'CRITICAL' || $level === 'ERROR') {
            error_log("[$level] $message - " . json_encode($context));
        }
    }
    
    /**
     * Rotate log files when they get too large
     */
    private static function rotateLogs() {
        $log_file = self::$log_dir . 'application_' . date('Y-m-d') . '.log';
        
        if (file_exists($log_file) && filesize($log_file) > self::$max_file_size) {
            // Rename current log file
            $rotated_file = $log_file . '.' . time();
            rename($log_file, $rotated_file);
            
            // Clean up old log files
            self::cleanupOldLogs();
        }
    }
    
    /**
     * Clean up old log files
     */
    private static function cleanupOldLogs() {
        $files = glob(self::$log_dir . 'application_*.log*');
        
        if (count($files) > self::$max_files) {
            // Sort by modification time (oldest first)
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Remove oldest files
            $files_to_remove = array_slice($files, 0, count($files) - self::$max_files);
            foreach ($files_to_remove as $file) {
                unlink($file);
            }
        }
    }
    
    /**
     * Get log statistics
     */
    public static function getStats($days = 7) {
        $stats = [
            'total_entries' => 0,
            'by_level' => [],
            'by_date' => [],
            'errors_today' => 0,
            'api_requests_today' => 0
        ];
        
        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $log_file = self::$log_dir . "application_{$date}.log";
            
            if (file_exists($log_file)) {
                $lines = file($log_file, FILE_IGNORE_NEW_LINES);
                $stats['total_entries'] += count($lines);
                
                foreach ($lines as $line) {
                    $entry = json_decode($line, true);
                    if ($entry) {
                        $level = $entry['level'];
                        $stats['by_level'][$level] = ($stats['by_level'][$level] ?? 0) + 1;
                        
                        if ($i === 0) { // Today
                            if ($level === 'ERROR' || $level === 'CRITICAL') {
                                $stats['errors_today']++;
                            }
                            if (isset($entry['context']['endpoint'])) {
                                $stats['api_requests_today']++;
                            }
                        }
                    }
                }
            }
        }
        
        return $stats;
    }
    
    /**
     * Export logs for analysis
     */
    public static function exportLogs($start_date, $end_date, $level = null) {
        $export_data = [];
        
        $current_date = $start_date;
        while ($current_date <= $end_date) {
            $log_file = self::$log_dir . "application_{$current_date}.log";
            
            if (file_exists($log_file)) {
                $lines = file($log_file, FILE_IGNORE_NEW_LINES);
                
                foreach ($lines as $line) {
                    $entry = json_decode($line, true);
                    if ($entry) {
                        if ($level === null || $entry['level'] === $level) {
                            $export_data[] = $entry;
                        }
                    }
                }
            }
            
            $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
        }
        
        return $export_data;
    }
}
?>
