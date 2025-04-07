<?php
/**
 * Index Page
 * This is the entry point of the application
 * Redirects to dashboard if logged in, otherwise to login page
 */

// Include initialization file
require_once __DIR__ . '/includes/init.php';

// Check if user is logged in
if (isLoggedIn()) {
    // Redirect to dashboard if logged in
    redirect('/dashboard.php');
} else {
    // Redirect to login page if not logged in
    redirect('/login.php');
}
?> 