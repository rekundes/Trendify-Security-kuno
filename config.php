<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Default XAMPP password is empty
define('DB_NAME', 'trendify_db');

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

// ========== SECURE SESSION CONFIGURATION ==========
// Set secure session cookie defaults
if (ini_get('session.status') === 'off') {
    // HttpOnly prevents JavaScript access
    ini_set('session.cookie_httponly', 1);
    
    // Secure flag (HTTPS only) - set to 0 for local development
    ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS
    
    // SameSite protection against CSRF
    ini_set('session.cookie_samesite', 'Strict');
    
    // Strict mode prevents session fixation
    ini_set('session.use_strict_mode', 1);
    
    // Increase session ID length and entropy
    ini_set('session.sid_length', 48);
    ini_set('session.sid_bits_per_character', 6);
    
    // Disable transparent sid (URL-based sessions)
    ini_set('session.use_trans_sid', 0);
    
    // Session timeout (30 minutes)
    ini_set('session.gc_maxlifetime', 1800);
}

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// Create logs directory if it doesn't exist
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
