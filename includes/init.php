<?php
/**
 * Initialization File
 * This file is included in every page and sets up the database connection,
 * loads configuration, and required classes and functions
 */

// Load configuration
require_once __DIR__ . '/../config/config.php';

// Load helper functions
require_once __DIR__ . '/functions.php';

// Connect to database
require_once __DIR__ . '/../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Load authentication system
require_once __DIR__ . '/auth.php';
$auth = new Auth($db);

// Check for "remember me" functionality
if (!isLoggedIn()) {
    $auth->checkRememberToken();
}

// Create directories if they don't exist
$directories = [
    ROOT_PATH . '/logs',
    ROOT_PATH . '/uploads',
    ROOT_PATH . '/uploads/reports',
    ROOT_PATH . '/uploads/profiles',
    ROOT_PATH . '/uploads/temp'
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}

// Function to check access rights and redirect if needed
function requireLogin() {
    if (!isLoggedIn()) {
        setFlashMessage('error', 'You must be logged in to access this page');
        redirect('/login.php');
    }
}

// Function to check admin access
function requireAdmin() {
    requireLogin();
    
    if (!hasRole('admin')) {
        setFlashMessage('error', 'You do not have permission to access this page');
        redirect('/dashboard.php');
    }
}

// Function to check manager access (managers and admins)
function requireManager() {
    requireLogin();
    
    if (!hasRole(['admin', 'manager'])) {
        setFlashMessage('error', 'You do not have permission to access this page');
        redirect('/dashboard.php');
    }
}

// Set active page in navigation
function setActivePage($page) {
    return $GLOBALS['active_page'] = $page;
}

function isActivePage($page) {
    return ($GLOBALS['active_page'] ?? '') === $page;
}

// Handler for uncaught exceptions
set_exception_handler(function($exception) {
    error_log($exception->getMessage() . "\n" . $exception->getTraceAsString());
    http_response_code(500);
    echo "An error occurred. Please try again later or contact support.";
    exit;
});

// Handler for fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING])) {
        error_log("Fatal error: {$error['message']} in {$error['file']} on line {$error['line']}");
        http_response_code(500);
        echo "A fatal error occurred. Please try again later or contact support.";
        exit;
    }
});

/**
 * Add this function at the end of init.php to ensure table consistency
 * This function will be executed via JavaScript to fix table structure issues
 */
function checkTableConsistency() {
    ob_start();
    ?>
    <script>
    // Function to fix DataTables column count mismatch
    document.addEventListener('DOMContentLoaded', function() {
        // Find all tables that will be converted to DataTables
        const tables = document.querySelectorAll('.data-table');
        
        tables.forEach(function(table) {
            // Get header columns count
            const headerCols = table.querySelectorAll('thead th').length;
            if (headerCols === 0) return; // Skip if no header
            
            // Check each row in tbody
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(function(row) {
                // Skip rows with colspan (typically "No data" rows)
                const hasColspan = Array.from(row.querySelectorAll('td')).some(td => td.hasAttribute('colspan'));
                if (hasColspan) return;
                
                // Count actual columns in this row
                const actualCols = row.querySelectorAll('td').length;
                
                // Fix if mismatch detected (and not a special row like "no data found")
                if (actualCols !== headerCols && !hasColspan) {
                    console.warn(`Fixed table row with ${actualCols} columns instead of ${headerCols}`);
                    
                    // If too few columns, add empty ones
                    if (actualCols < headerCols) {
                        for (let i = actualCols; i < headerCols; i++) {
                            const td = document.createElement('td');
                            td.innerHTML = '&mdash;';
                            row.appendChild(td);
                        }
                    }
                    
                    // If too many columns, remove excess
                    if (actualCols > headerCols) {
                        const cells = row.querySelectorAll('td');
                        for (let i = headerCols; i < actualCols; i++) {
                            if (cells[i]) cells[i].remove();
                        }
                    }
                }
            });
        });
    });
    </script>
    <?php
    $script = ob_get_clean();
    echo $script;
}

// Register the function to run on page footer
add_action('footer', 'checkTableConsistency');
?> 