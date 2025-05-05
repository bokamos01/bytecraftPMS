<?php

// Error reporting settings (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define base paths
define('BASE_PATH', dirname(__FILE__));
define('UPLOADS_PATH', BASE_PATH . '/uploads');
define('LOGS_PATH', BASE_PATH . '/logs');

// Application settings
define('APP_NAME', 'Carpark Management System');
define('APP_VERSION', '1.1.0');
define('DEFAULT_TIMEZONE', 'Etc/GMT-2');

// Set timezone
date_default_timezone_set(DEFAULT_TIMEZONE);

// Database credentials
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
// --- MODIFIED DATABASE NAME ---
define('DB_NAME', 'pms'); 
// --- END MODIFICATION ---
define('DB_USER', 'root');
define('DB_PASS', '');

// Session settings
define('SESSION_LIFETIME', 1800); // 30 minutes
define('SESSION_SECURE', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
define('SESSION_HTTP_ONLY', true);

// Create essential directories if they don't exist
function ensureDirectoriesExist() {
    $directories = [
        UPLOADS_PATH,
        UPLOADS_PATH . '/parking',
        LOGS_PATH,
    ];

    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            if (!mkdir($dir, 0755, true)) {
                error_log("Failed to create directory: {$dir}");
            }
        }
    }
}

// Initialize session with secure settings
function initSession() {
    // --- MODIFICATION: Set ini settings BEFORE session_start ---
    // Set secure session configuration only if session is not already active
    if (session_status() == PHP_SESSION_NONE) {
        // Attempt to set ini settings - use @ to suppress potential warnings
        // if settings are locked down by server config, but fixing the order is key.
        @ini_set('session.use_strict_mode', 1);
        @ini_set('session.use_only_cookies', 1);
        @ini_set('session.cookie_httponly', SESSION_HTTP_ONLY);
        @ini_set('session.cookie_secure', SESSION_SECURE);
        @ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    }
    // --- END MODIFICATION ---

    // Start the session if not already started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // Set session timeout check
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
        session_unset();
        session_destroy();
        session_start();
        // $_SESSION['login_message'] = "Your session has expired. Please log in again.";
    }
    // Update last activity time only if session is active
    if (session_status() == PHP_SESSION_ACTIVE) {
         $_SESSION['last_activity'] = time();
    }


    // Generate CSRF token if not exists and session is active
    if (session_status() == PHP_SESSION_ACTIVE && !isset($_SESSION['csrf_token'])) {
        try {
             $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
             error_log("Failed to generate CSRF token: " . $e->getMessage());
        }
    }
}

// Include utility functions
require_once __DIR__ . '/utility.php';

// Create Data Access object
require_once __DIR__ . '/PMSDataAccess.php';
$dataAccess = new DataAccess();

// Define authentication functions
function isLoggedIn() {
    return isset($_SESSION['customer_id']) || isset($_SESSION['staff_id']);
}

function isAdmin() {
    return isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1;
}

function requireLogin($redirect = 'login.php') {
    if (!isLoggedIn()) {
        $_SESSION['login_error'] = "Please log in to access that page.";
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header("Location: $redirect");
        exit;
    }
}

function requireAdmin($redirect = 'unauthorized.php') {
    if (!isLoggedIn() || !isAdmin()) {
        header("Location: $redirect");
        exit;
    }
}

// Initialize necessary components
ensureDirectoriesExist();
initSession();
