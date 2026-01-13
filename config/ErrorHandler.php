<?php
/**
 * Centralized Error Handling System
 * Provides consistent error handling, logging, and user feedback
 */

class ErrorHandler {
    private static $log_file = 'logs/error.log';
    private static $debug_mode = true; // Set to false in production
    
    /**
     * Initialize error handling
     */
    public static function init() {
        // Set error reporting
        error_reporting(E_ALL);
        
        // Set custom error handler
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        
        // Create logs directory if it doesn't exist
        $log_dir = dirname(self::$log_file);
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
    }
    
    /**
     * Handle PHP errors
     */
    public static function handleError($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $error_type = self::getErrorType($severity);
        $error_data = [
            'type' => $error_type,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'timestamp' => date('Y-m-d H:i:s'),
            'severity' => $severity
        ];
        
        self::logError($error_data);
        
        if (self::$debug_mode) {
            self::displayError($error_data);
        }
        
        return true;
    }
    
    /**
     * Handle uncaught exceptions
     */
    public static function handleException($exception) {
        $error_data = [
            'type' => 'Exception',
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        self::logError($error_data);
        
        if (self::$debug_mode) {
            self::displayError($error_data);
        } else {
            self::displayUserFriendlyError();
        }
    }
    
    /**
     * Log error to file
     */
    private static function logError($error_data) {
        $log_entry = json_encode($error_data) . "\n";
        file_put_contents(self::$log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Display error for debugging
     */
    private static function displayError($error_data) {
        echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 10px; border-radius: 5px;'>";
        echo "<h3 style='color: #721c24; margin: 0 0 10px 0;'>Error: " . htmlspecialchars($error_data['type']) . "</h3>";
        echo "<p style='margin: 5px 0;'><strong>Message:</strong> " . htmlspecialchars($error_data['message']) . "</p>";
        echo "<p style='margin: 5px 0;'><strong>File:</strong> " . htmlspecialchars($error_data['file']) . "</p>";
        echo "<p style='margin: 5px 0;'><strong>Line:</strong> " . htmlspecialchars($error_data['line']) . "</p>";
        echo "<p style='margin: 5px 0;'><strong>Time:</strong> " . htmlspecialchars($error_data['timestamp']) . "</p>";
        if (isset($error_data['trace'])) {
            echo "<details><summary>Stack Trace</summary><pre>" . htmlspecialchars($error_data['trace']) . "</pre></details>";
        }
        echo "</div>";
    }
    
    /**
     * Display user-friendly error message
     */
    private static function displayUserFriendlyError() {
        http_response_code(500);
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Error - MD Law</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f8f9fa; }
                .error-container { max-width: 500px; margin: 0 auto; background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
                h1 { color: #dc3545; margin-bottom: 20px; }
                p { color: #6c757d; margin-bottom: 30px; }
                .btn { background: #0b1d3a; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; }
            </style>
        </head>
        <body>
            <div class='error-container'>
                <h1>Something went wrong</h1>
                <p>We're sorry, but something unexpected happened. Our team has been notified and is working to fix the issue.</p>
                <a href='index.html' class='btn'>Return to Homepage</a>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Get error type from severity
     */
    private static function getErrorType($severity) {
        switch ($severity) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                return 'Fatal Error';
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                return 'Warning';
            case E_NOTICE:
            case E_USER_NOTICE:
                return 'Notice';
            case E_STRICT:
                return 'Strict Standards';
            case E_RECOVERABLE_ERROR:
                return 'Recoverable Error';
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                return 'Deprecated';
            default:
                return 'Unknown Error';
        }
    }
    
    /**
     * Log custom application error
     */
    public static function logApplicationError($message, $context = []) {
        $error_data = [
            'type' => 'Application Error',
            'message' => $message,
            'context' => $context,
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $_SESSION['user_id'] ?? 'anonymous',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        self::logError($error_data);
    }
    
    /**
     * Return JSON error response for API
     */
    public static function returnJsonError($message, $code = 500, $details = null) {
        http_response_code($code);
        header('Content-Type: application/json');
        
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if ($details && self::$debug_mode) {
            $response['details'] = $details;
        }
        
        echo json_encode($response);
        exit;
    }
    
    /**
     * Validate and sanitize input
     */
    public static function validateInput($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? '';
            
            // Required validation
            if (isset($rule['required']) && $rule['required'] && empty($value)) {
                $errors[$field] = $rule['message'] ?? "Field '$field' is required";
                continue;
            }
            
            // Skip other validations if field is empty and not required
            if (empty($value) && !isset($rule['required'])) {
                continue;
            }
            
            // Type validation
            if (isset($rule['type'])) {
                switch ($rule['type']) {
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field] = $rule['message'] ?? "Invalid email format";
                        }
                        break;
                    case 'phone':
                        if (!preg_match('/^[0-9]{11}$/', $value)) {
                            $errors[$field] = $rule['message'] ?? "Phone must be 11 digits";
                        }
                        break;
                    case 'name':
                        if (!preg_match('/^[a-zA-Z\s\'-]{2,50}$/', $value)) {
                            $errors[$field] = $rule['message'] ?? "Name must be 2-50 characters, letters only";
                        }
                        break;
                }
            }
            
            // Length validation
            if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
                $errors[$field] = $rule['message'] ?? "Field must be at least {$rule['min_length']} characters";
            }
            
            if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
                $errors[$field] = $rule['message'] ?? "Field must be no more than {$rule['max_length']} characters";
            }
        }
        
        return $errors;
    }
}
?>
