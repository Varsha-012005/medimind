<?php
/**
 * MediMind - Functions File
 * 
 * This file contains all the helper functions used throughout the MediMind platform.
 */

/**
 * Sanitize input data to prevent XSS and SQL injection
 * @param mixed $data The input data to sanitize
 * @param string $type The type of sanitization (string, int, float, email, etc.)
 * @return mixed The sanitized data
 */
function sanitizeInput($data, $type = 'string') {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }

    switch ($type) {
        case 'int':
            return filter_var($data, FILTER_SANITIZE_NUMBER_INT);
        case 'float':
            return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        case 'email':
            return filter_var($data, FILTER_SANITIZE_EMAIL);
        case 'url':
            return filter_var($data, FILTER_SANITIZE_URL);
        case 'string':
        default:
            return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Validate CSRF token
 * @param string $token The token to validate
 * @return bool True if valid, false otherwise
 */
function validateCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }
    
    if ($token !== $_SESSION['csrf_token']) {
        return false;
    }
    
    if (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_EXPIRE) {
        return false;
    }
    
    return true;
}

/**
 * Generate a secure password hash
 * @param string $password The password to hash
 * @return string The hashed password
 */
function generatePasswordHash($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => PASSWORD_HASH_COST]);
}

/**
 * Verify a password against a hash
 * @param string $password The password to verify
 * @param string $hash The hash to verify against
 * @return bool True if the password matches, false otherwise
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Redirect to a specified URL
 * @param string $url The URL to redirect to
 * @param int $statusCode The HTTP status code to use (default: 303)
 */
function redirect($url, $statusCode = 303) {
    header('Location: ' . $url, true, $statusCode);
    exit();
}

/**
 * Generate a random string
 * @param int $length The length of the string to generate
 * @return string The generated string
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Check if a user is logged in
 * @return bool True if logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if the current user has a specific role
 * @param string $role The role to check for (patient, doctor, admin)
 * @return bool True if the user has the role, false otherwise
 */
function hasRole($role) {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === $role;
}

/**
 * Require a specific role for access
 * @param string $role The required role
 */
function requireRole($role) {
    if (!isLoggedIn() || !hasRole($role)) {
        redirect(BASE_URL . '/index.php');
    }
}

/**
 * Get the current user's ID
 * @return int|null The user ID or null if not logged in
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Log an action to the audit log
 * @param string $action The action performed
 * @param string $table The table affected
 * @param int|null $recordId The ID of the affected record
 */
function logAction($action, $table, $recordId = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, table_affected, record_id, ip_address, user_agent) 
                               VALUES (:user_id, :action, :table_affected, :record_id, :ip_address, :user_agent)");
        
        $stmt->execute([
            ':user_id' => getCurrentUserId(),
            ':action' => $action,
            ':table_affected' => $table,
            ':record_id' => $recordId,
            ':ip_address' => $_SERVER['REMOTE_ADDR'],
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (PDOException $e) {
        error_log("Failed to log action: " . $e->getMessage());
    }
}

/**
 * Upload a file with validation
 * @param array $file The $_FILES array element
 * @param string $targetDir The directory to upload to
 * @param array $allowedTypes Allowed MIME types
 * @return array Result with 'success' and 'path' or 'error'
 */
function uploadFile($file, $targetDir, $allowedTypes = []) {
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    $fileName = sanitizeInput(basename($file['name']));
    $targetPath = $targetDir . '/' . uniqid() . '_' . $fileName;
    $fileType = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
    
    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'File is too large. Maximum size is ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB'];
    }
    
    // Check file type
    if (!empty($allowedTypes) && !in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'error' => 'File type not allowed'];
    }
    
    // Try to upload file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'path' => $targetPath];
    } else {
        return ['success' => false, 'error' => 'Failed to upload file'];
    }
}

/**
 * Get user information by ID
 * @param int $userId The user ID
 * @return array|null The user data or null if not found
 */
function getUserById($userId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Failed to get user by ID: " . $e->getMessage());
        return null;
    }
}

/**
 * Truncate a string to a specified length
 * 
 * @param string $string The string to truncate
 * @param int $length Maximum length of the string
 * @param string $suffix Suffix to append if string is truncated
 * @return string Truncated string
 */
function truncate($string, $length = 100, $suffix = '...') {
    if (mb_strlen($string) <= $length) {
        return $string;
    }
    return mb_substr($string, 0, $length) . $suffix;
}

function getUserName($userId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if ($user) {
            return $user['first_name'] . ' ' . $user['last_name'];
        }
        return 'Unknown User';
    } catch (PDOException $e) {
        error_log("Error getting user name: " . $e->getMessage());
        return 'Unknown User';
    }
}

/**
 * Get doctor information by user ID
 * @param int $userId The user ID
 * @return array|null The doctor data or null if not found
 */
function getDoctorByUserId($userId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM doctors WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Failed to get doctor by user ID: " . $e->getMessage());
        return null;
    }
}

/**
 * Get patient health profile by user ID
 * @param int $userId The user ID
 * @return array|null The patient health profile or null if not found
 */
function getPatientHealthProfile($userId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM patient_health_profiles WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Failed to get patient health profile: " . $e->getMessage());
        return null;
    }
}

/**
 * Get all appointments for a user
 * @param int $userId The user ID
 * @param string $userType The user type (patient or doctor)
 * @param string $status The appointment status to filter by (optional)
 * @return array Array of appointments
 */
function getUserAppointments($userId, $userType, $status = null) {
    global $pdo;
    
    try {
        $query = "SELECT a.*, 
                 p.first_name AS patient_first_name, p.last_name AS patient_last_name,
                 d.first_name AS doctor_first_name, d.last_name AS doctor_last_name
                 FROM appointments a
                 JOIN users p ON a.patient_id = p.user_id
                 JOIN users d ON a.doctor_id = d.user_id
                 WHERE a." . ($userType === 'patient' ? 'patient_id' : 'doctor_id') . " = ?";
        
        $params = [$userId];
        
        if ($status) {
            $query .= " AND a.status = ?";
            $params[] = $status;
        }
        
        $query .= " ORDER BY a.appointment_date, a.start_time";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to get user appointments: " . $e->getMessage());
        return [];
    }
}

/**
 * Send a notification to a user
 * @param int $userId The recipient user ID
 * @param string $title The notification title
 * @param string $message The notification message
 * @param string|null $link An optional link
 * @return bool True if successful, false otherwise
 */
function sendNotification($userId, $title, $message, $link = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, link) 
                              VALUES (?, ?, ?, ?)");
        return $stmt->execute([$userId, $title, $message, $link]);
    } catch (PDOException $e) {
        error_log("Failed to send notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get unread notifications for a user
 * @param int $userId The user ID
 * @param int $limit Maximum number of notifications to return
 * @return array Array of notifications
 */
function getUnreadNotifications($userId, $limit = 10) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM notifications 
                              WHERE user_id = ? AND is_read = FALSE 
                              ORDER BY created_at DESC 
                              LIMIT ?");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to get unread notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Mark notifications as read
 * @param array|int $notificationIds Single ID or array of IDs to mark as read
 * @return bool True if successful, false otherwise
 */
function markNotificationsAsRead($notificationIds) {
    global $pdo;
    
    if (!is_array($notificationIds)) {
        $notificationIds = [$notificationIds];
    }
    
    try {
        $placeholders = implode(',', array_fill(0, count($notificationIds), '?'));
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE 
                              WHERE notification_id IN ($placeholders)");
        return $stmt->execute($notificationIds);
    } catch (PDOException $e) {
        error_log("Failed to mark notifications as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Format a date for display
 * @param string $date The date string
 * @param string $format The format to use (default: 'F j, Y')
 * @return string The formatted date
 */
function formatDate($date, $format = 'F j, Y') {
    return date($format, strtotime($date));
}

/**
 * Format a time for display
 * @param string $time The time string
 * @param string $format The format to use (default: 'g:i A')
 * @return string The formatted time
 */
function formatTime($time, $format = 'g:i A') {
    return date($format, strtotime($time));
}

/**
 * Format a date and time for display
 * @param string $date The date string
 * @param string $time The time string
 * @return string The formatted date and time
 */
function formatDateTime($date, $time) {
    return formatDate($date) . ' at ' . formatTime($time);
}

/**
 * Check if a date is in the past
 * @param string $date The date to check
 * @return bool True if the date is in the past, false otherwise
 */
function isPastDate($date) {
    return strtotime($date) < time();
}

/**
 * Generate a unique room ID for video consultations
 * @return string The generated room ID
 */
function generateRoomId() {
    return uniqid('room_', true);
}

/**
 * Get the current URL
 * @return string The current URL
 */
function currentUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

/**
 * Get the base URL
 * @return string The base URL
 */
function baseUrl() {
    return BASE_URL;
}

/**
 * Get the assets URL
 * @param string $path Optional path to append
 * @return string The assets URL
 */
function assetsUrl($path = '') {
    return ASSETS_URL . ($path ? '/' . ltrim($path, '/') : '');
}

/**
 * Output JSON response
 * @param mixed $data The data to output
 * @param int $statusCode The HTTP status code
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/**
 * Check if the request is AJAX
 * @return bool True if AJAX, false otherwise
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}
?>