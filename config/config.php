<?php
/**
 * Application Configuration File
 * This file contains global constants and settings for the Rice Stock System
 */

// Define application path constants
define('ROOT_PATH', dirname(dirname(__FILE__)));
define('URL_ROOT', '/RiceStochSystem');
define('SITE_NAME', 'Rice Stock System');

// System settings
define('RECORDS_PER_PAGE', 10);
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('CURRENCY_SYMBOL', '$');

// Stock level thresholds (in percentage of minimum stock level)
define('STOCK_LEVEL_LOW', 25);  // Below 25% of minimum level - Critical/Red
define('STOCK_LEVEL_MEDIUM', 50);  // Below 50% of minimum level - Warning/Yellow
define('STOCK_LEVEL_SAFE', 100);  // At or above minimum level - Good/Green

// File upload settings
define('MAX_FILE_SIZE', 5 * 1024 * 1024);  // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'xlsx', 'csv']);
define('UPLOAD_PATH', ROOT_PATH . '/uploads/');

// Email notification settings
define('ENABLE_EMAIL_NOTIFICATIONS', false);
define('EMAIL_FROM', 'system@ricestock.com');
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'username');
define('SMTP_PASSWORD', 'password');
define('SMTP_ENCRYPTION', 'tls');

// System version
define('SYSTEM_VERSION', '1.0.0');

// Error reporting settings
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', ROOT_PATH . '/logs/error.log');

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0);  // Set to 1 if using HTTPS
session_start();

// Time zone setting
date_default_timezone_set('UTC');
?> 