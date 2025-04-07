<?php
/**
 * Logout Page
 * This file handles user logout by destroying the session
 */

// Include initialization file
require_once __DIR__ . '/includes/init.php';

// Log the logout activity
if (isLoggedIn()) {
    logActivity('logout', 'user', $_SESSION['user_id'], 'User logged out');
}

// Logout the user
$auth->logout();

// Redirect to login page with success message
setFlashMessage('success', 'You have been successfully logged out');
redirect('/login.php');
?> 