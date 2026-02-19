<?php
/**
 * MediMind - Configuration File
 * 
 * This file contains all the necessary configurations for the MediMind telemedicine platform.
 * It includes database connections, system settings, and environment configurations.
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Enable in production with HTTPS
session_start();

// Timezone configuration
date_default_timezone_set('UTC');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'medimind');
define('DB_CHARSET', 'utf8mb4');

// Application paths
define('BASE_URL', 'http://localhost/medimind');
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Security settings
define('PASSWORD_HASH_COST', 12);
define('CSRF_TOKEN_EXPIRE', 3600); // 1 hour
define('AUTH_TOKEN_EXPIRE', 86400 * 30); // 30 days

// SMTP configuration for emails
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'noreply@medimind.example.com');
define('SMTP_PASS', 'your_smtp_password');
define('SMTP_FROM', 'noreply@medimind.example.com');
define('SMTP_FROM_NAME', 'MediMind System');

// WebRTC configuration
define('WEBRTC_STUN_SERVER', 'stun:stun.l.google.com:19302');
define('WEBRTC_TURN_SERVER', '');
define('WEBRTC_TURN_USERNAME', '');
define('WEBRTC_TURN_CREDENTIAL', '');

// Try to establish database connection
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("We're experiencing technical difficulties. Please try again later.");
}

// Load system settings from database
$systemSettings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        $systemSettings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    error_log("Failed to load system settings: " . $e->getMessage());
}

// Apply system settings
if (isset($systemSettings['system_timezone'])) {
    date_default_timezone_set($systemSettings['system_timezone']);
}

// Check for maintenance mode
if (isset($systemSettings['maintenance_mode']) && $systemSettings['maintenance_mode'] == '1') {
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
        die("The system is currently undergoing maintenance. Please check back later.");
    }
}

// CSRF protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
}

// Include functions
require_once __DIR__ . '/functions.php';
?>