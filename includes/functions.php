<?php
/**
 * Utility Functions
 * Contains common helper functions used throughout the application
 */

/**
 * Sanitize user input to prevent XSS attacks
 * 
 * @param string $data The input data to sanitize
 * @return string Sanitized data
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Redirect to another page
 * 
 * @param string $location The URL to redirect to
 * @return void
 */
function redirect($location) {
    header("Location: " . URL_ROOT . $location);
    exit;
}

/**
 * Check if user is logged in
 * 
 * @return boolean True if user is logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user has the required role
 * 
 * @param string|array $roles Role or roles to check against
 * @return boolean True if user has the required role, false otherwise
 */
function hasRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    if (is_string($roles)) {
        $roles = [$roles];
    }
    
    return in_array($_SESSION['user_role'], $roles);
}

/**
 * Format a date according to the system's date format
 * 
 * @param string $date The date to format
 * @param string $format The format to use (defaults to DATE_FORMAT)
 * @return string Formatted date
 */
function formatDate($date, $format = DATE_FORMAT) {
    $timestamp = strtotime($date);
    return date($format, $timestamp);
}

/**
 * Format a number as currency
 * 
 * @param float $amount The amount to format
 * @param int $decimals Number of decimal places
 * @return string Formatted currency
 */
function formatCurrency($amount, $decimals = 2) {
    // Handle NULL or non-numeric values
    if ($amount === null || !is_numeric($amount)) {
        $amount = 0;
    }
    return CURRENCY_SYMBOL . number_format($amount, $decimals);
}

/**
 * Generate a unique ID/reference number
 * 
 * @param string $prefix The prefix to use for the ID
 * @return string A unique ID
 */
function generateUniqueId($prefix = '') {
    $timestamp = time();
    $random = mt_rand(1000, 9999);
    return strtoupper($prefix) . $timestamp . $random;
}

/**
 * Log system activity
 * 
 * @param string $action The action that occurred
 * @param string $entityType The type of entity (e.g., 'user', 'stock', etc.)
 * @param int $entityId The ID of the entity
 * @param string $details Additional details about the action
 * @return bool True on success, false on failure
 */
function logActivity($action, $entityType, $entityId = null, $details = '') {
    global $db;
    
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $query = "INSERT INTO system_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) 
              VALUES (:userId, :action, :entityType, :entityId, :details, :ipAddress, :userAgent)";
    
    try {
        $stmt = $db->prepare($query);
        $stmt->bindParam(':userId', $userId);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':entityType', $entityType);
        $stmt->bindParam(':entityId', $entityId);
        $stmt->bindParam(':details', $details);
        $stmt->bindParam(':ipAddress', $ipAddress);
        $stmt->bindParam(':userAgent', $userAgent);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error logging activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Create a notification
 * 
 * @param int $userId The user ID to notify (null for system-wide)
 * @param string $title The notification title
 * @param string $message The notification message
 * @param string $type The notification type (info, warning, alert, success)
 * @return bool True on success, false on failure
 */
function createNotification($userId, $title, $message, $type = 'info') {
    global $db;
    
    $query = "INSERT INTO notifications (user_id, title, message, type) 
              VALUES (:userId, :title, :message, :type)";
    
    try {
        $stmt = $db->prepare($query);
        $stmt->bindParam(':userId', $userId);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':type', $type);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get stock level status based on current and minimum quantity
 * 
 * @param float $currentQuantity Current stock quantity
 * @param float $minimumLevel Minimum stock level
 * @return string Status ('low', 'medium', 'safe')
 */
function getStockLevelStatus($currentQuantity, $minimumLevel) {
    $percentage = ($currentQuantity / $minimumLevel) * 100;
    
    if ($percentage <= STOCK_LEVEL_LOW) {
        return 'low';
    } elseif ($percentage <= STOCK_LEVEL_MEDIUM) {
        return 'medium';
    } else {
        return 'safe';
    }
}

/**
 * Upload a file to the server
 * 
 * @param array $file The $_FILES array element
 * @param string $destination The destination directory
 * @return array Response with status and message/filepath
 */
function uploadFile($file, $destination = UPLOAD_PATH) {
    // Check if file was uploaded without errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['status' => false, 'message' => 'Upload failed with error code: ' . $file['error']];
    }
    
    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['status' => false, 'message' => 'File too large. Maximum size is ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB'];
    }
    
    // Get file extension
    $fileInfo = pathinfo($file['name']);
    $extension = strtolower($fileInfo['extension']);
    
    // Check file extension
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        return ['status' => false, 'message' => 'Invalid file type. Allowed types: ' . implode(', ', ALLOWED_EXTENSIONS)];
    }
    
    // Create destination directory if it doesn't exist
    if (!file_exists($destination)) {
        mkdir($destination, 0777, true);
    }
    
    // Generate unique filename
    $newFilename = uniqid() . '.' . $extension;
    $targetPath = $destination . $newFilename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['status' => true, 'filepath' => $targetPath, 'filename' => $newFilename];
    } else {
        return ['status' => false, 'message' => 'Failed to move uploaded file'];
    }
}

/**
 * Format bytes to human-readable format
 * 
 * @param int $bytes Number of bytes
 * @param int $precision Decimal precision
 * @return string Formatted size
 */
function formatFileSize($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Set a flash message that will be displayed once
 * 
 * @param string $type Message type (success, error, warning, info)
 * @param string $message The message content
 * @return void
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Display and clear the flash message
 * 
 * @return string The HTML for the flash message, or empty string if none
 */
function displayFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        
        return '<div class="alert alert-' . $message['type'] . '">' . $message['message'] . '</div>';
    }
    return '';
}

/**
 * Global array to store action hooks
 */
$GLOBALS['_action_hooks'] = [];

/**
 * Add an action hook callback
 * 
 * @param string $hook Hook name
 * @param callable $callback Function to execute
 * @param int $priority Priority of the action (default: 10)
 * @return bool Success
 */
function add_action($hook, $callback, $priority = 10) {
    if (!isset($GLOBALS['_action_hooks'][$hook])) {
        $GLOBALS['_action_hooks'][$hook] = [];
    }
    
    if (!isset($GLOBALS['_action_hooks'][$hook][$priority])) {
        $GLOBALS['_action_hooks'][$hook][$priority] = [];
    }
    
    $GLOBALS['_action_hooks'][$hook][$priority][] = $callback;
    return true;
}

/**
 * Run all registered actions for a hook
 * 
 * @param string $hook Hook name
 * @param mixed $args Arguments to pass to callbacks
 * @return bool Whether any actions were executed
 */
function do_action($hook, $args = null) {
    if (!isset($GLOBALS['_action_hooks'][$hook])) {
        return false;
    }
    
    // Get priorities
    $priorities = array_keys($GLOBALS['_action_hooks'][$hook]);
    sort($priorities);
    
    foreach ($priorities as $priority) {
        foreach ($GLOBALS['_action_hooks'][$hook][$priority] as $callback) {
            if (is_callable($callback)) {
                call_user_func($callback, $args);
            }
        }
    }
    
    return true;
}

/**
 * Remove an action hook
 * 
 * @param string $hook Hook name
 * @param callable $callback Function to remove
 * @param int $priority Priority to remove from (default: 10)
 * @return bool Success
 */
function remove_action($hook, $callback, $priority = 10) {
    if (!isset($GLOBALS['_action_hooks'][$hook][$priority])) {
        return false;
    }
    
    // Find the callback in the array
    $key = array_search($callback, $GLOBALS['_action_hooks'][$hook][$priority]);
    
    if ($key !== false) {
        unset($GLOBALS['_action_hooks'][$hook][$priority][$key]);
        return true;
    }
    
    return false;
}
?> 