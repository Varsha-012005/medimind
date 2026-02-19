<?php
/**
 * MediMind - Complete Authentication System
 * 
 * Handles user login, registration, and dashboard redirection for patients, doctors, and admins.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Initialize variables
$errors = [];
$success = [];
$activeTab = isset($_GET['register']) ? 'register' : 'login';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle logout if requested
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: auth.php");
    exit();
}

/**
 * Authenticates a user
 */
function authenticateUser($username, $password)
{
    global $pdo;

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if (!$user)
            return ['success' => false, 'message' => 'Invalid username or password'];
        if (!password_verify($password, $user['password_hash']))
            return ['success' => false, 'message' => 'Invalid username or password'];
        if (!$user['is_verified'])
            return ['success' => false, 'message' => 'Account not verified. Please check your email.'];

        // Additional check for doctors
        if ($user['user_type'] === 'doctor') {
            $stmt = $pdo->prepare("SELECT is_approved FROM doctors WHERE user_id = ?");
            $stmt->execute([$user['user_id']]);
            $doctor = $stmt->fetch();

            if (!$doctor || !$doctor['is_approved']) {
                return ['success' => false, 'message' => 'Your doctor account is pending approval.'];
            }
        }

        return ['success' => true, 'user' => $user];
    } catch (PDOException $e) {
        error_log("Authentication failed: " . $e->getMessage());
        return ['success' => false, 'message' => 'Authentication failed. Please try again.'];
    }
}

/**
 * Registers a new user
 */
function registerUser($userData)
{
    global $pdo;

    // Validate input
    if (
        empty($userData['username']) || empty($userData['email']) || empty($userData['password']) ||
        empty($userData['first_name']) || empty($userData['last_name'])
    ) {
        return ['success' => false, 'message' => 'All fields are required'];
    }

    if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email address'];
    }

    if (strlen($userData['password']) < 8) {
        return ['success' => false, 'message' => 'Password must be at least 8 characters'];
    }

    // Check if username or email exists
    try {
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$userData['username'], $userData['email']]);
        if ($stmt->fetch())
            return ['success' => false, 'message' => 'Username or email already exists'];
    } catch (PDOException $e) {
        error_log("Registration check failed: " . $e->getMessage());
        return ['success' => false, 'message' => 'Registration failed. Please try again.'];
    }

    // Hash password and generate token
    $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
    $verificationToken = bin2hex(random_bytes(32));

    try {
        $pdo->beginTransaction();

        // Insert user with is_verified = FALSE
        $stmt = $pdo->prepare("
            INSERT INTO users (
                username, email, password_hash, first_name, last_name, 
                date_of_birth, phone, user_type, is_verified, verification_token
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, FALSE, ?)
        ");
        $stmt->execute([
            $userData['username'],
            $userData['email'],
            $hashedPassword,
            $userData['first_name'],
            $userData['last_name'],
            $userData['date_of_birth'] ?? null,
            $userData['phone'] ?? null,
            $userData['user_type'],
            $verificationToken
        ]);

        $userId = $pdo->lastInsertId();

        // Insert role-specific data
        if ($userData['user_type'] === 'doctor') {
            $stmt = $pdo->prepare("
                INSERT INTO doctors (
                    user_id, license_number, specialization, 
                    qualifications, consultation_fee
                ) VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $userData['license_number'],
                $userData['specialization'],
                $userData['qualifications'],
                $userData['consultation_fee'] ?? 0
            ]);
        } elseif ($userData['user_type'] === 'patient') {
            $stmt = $pdo->prepare("INSERT INTO patient_health_profiles (user_id) VALUES (?)");
            $stmt->execute([$userId]);
        }
        // No additional tables needed for admin

        $pdo->commit();

        // Get the full user record
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        return ['success' => true, 'user' => $user];
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Registration failed: " . $e->getMessage());
        return ['success' => false, 'message' => 'Registration failed. Please try again.'];
    }
}

/**
 * Starts a user session
 */
function startUserSession($user)
{
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name'] = $user['last_name'];
    $_SESSION['user_type'] = $user['user_type'];
    $_SESSION['profile_picture'] = $user['profile_picture'] ?? null;
    $_SESSION['logged_in'] = true;

    session_regenerate_id(true);
}

/**
 * Redirects to appropriate dashboard
 */
function redirectToDashboard($userType)
{
    $dashboards = [
        'patient' => '../patient/dashboard.php',
        'doctor' => '../doctor/dashboard.php',
        'admin' => '../admin/dashboard.php'
    ];

    $dashboard = $dashboards[$userType] ?? $dashboards['patient'];
    header("Location: $dashboard");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login'])) {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        if (empty($username) || empty($password)) {
            $errors[] = "Please enter both username/email and password";
        } else {
            $authResult = authenticateUser($username, $password);
            if ($authResult['success']) {
                startUserSession($authResult['user']);
                redirectToDashboard($authResult['user']['user_type']);
            } else {
                $errors[] = $authResult['message'];
            }
        }
    } elseif (isset($_POST['register'])) {
        $userData = [
            'username' => trim($_POST['username']),
            'email' => trim($_POST['email']),
            'password' => $_POST['password'],
            'first_name' => trim($_POST['first_name']),
            'last_name' => trim($_POST['last_name']),
            'user_type' => $_POST['user_type'],
            'phone' => trim($_POST['phone'] ?? ''),
            'date_of_birth' => $_POST['date_of_birth'] ?? null
        ];

        if ($userData['user_type'] === 'doctor') {
            $userData['license_number'] = trim($_POST['license_number']);
            $userData['specialization'] = trim($_POST['specialization']);
            $userData['qualifications'] = trim($_POST['qualifications']);
            $userData['consultation_fee'] = trim($_POST['consultation_fee']);
        }

        $registerResult = registerUser($userData);
        if ($registerResult['success']) {
            startUserSession($registerResult['user']);
            redirectToDashboard($registerResult['user']['user_type']);
        } else {
            $errors[] = $registerResult['message'];
        }
    }
}

// Check for success message from session
if (isset($_SESSION['success_message'])) {
    $success[] = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediMind - Telemedicine Platform</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --accent: #7209b7;
            --accent-light: #b5179e;
            --dark: #14213d;
            --light: #f8fafc;
            --gray: #94a3b8;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light);
            color: var(--dark);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            background: linear-gradient(135deg, #f9f0ff 0%, #f0f4ff 50%, #f9f0ff 100%);
            overflow: hidden;
            position: relative;
        }

        .bg-circle {
            position: absolute;
            border-radius: 50%;
            filter: blur(60px);
            opacity: 0.15;
            z-index: -1;
        }

        .bg-circle-1 {
            width: 300px;
            height: 300px;
            background: var(--primary);
            top: -100px;
            left: -100px;
            animation: float 15s ease-in-out infinite;
        }

        .bg-circle-2 {
            width: 400px;
            height: 400px;
            background: var(--accent);
            bottom: -150px;
            right: -100px;
            animation: float 18s ease-in-out infinite reverse;
        }

        .bg-circle-3 {
            width: 200px;
            height: 200px;
            background: var(--warning);
            top: 50%;
            right: 20%;
            animation: float 12s ease-in-out infinite 2s;
        }

        @keyframes float {

            0%,
            100% {
                transform: translate(0, 0);
            }

            50% {
                transform: translate(20px, 30px);
            }
        }

        .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            max-width: 1200px;
            gap: 40px;
        }

        .welcome-section {
            flex: 1;
            max-width: 600px;
            padding: 40px;
            animation: slideInLeft 0.8s ease-out;
        }

        .welcome-logo {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            font-size: 26px;
            font-weight: 800;
            color: var(--dark);
            font-family: 'Montserrat', sans-serif;
            letter-spacing: -0.5px;
        }

        .welcome-logo i {
            margin-right: 10px;
            color: var(--accent);
            font-size: 28px;
            transition: transform 0.5s ease;
        }

        .welcome-logo:hover i {
            transform: rotate(360deg);
        }

        .welcome-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 20px;
            line-height: 1.2;
            color: var(--dark);
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            animation: gradient 8s ease infinite;
            background-size: 200% 200%;
        }

        @keyframes gradient {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        .welcome-description {
            font-size: 1.1rem;
            margin-bottom: 30px;
            color: var(--dark-light);
            line-height: 1.6;
        }

        .welcome-stats {
            display: flex;
            gap: 20px;
            margin-top: 30px;
        }

        .stat-item {
            width: calc(50% - 10px);
            background: white;
            border-radius: 15px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 5px;
            font-family: 'Montserrat', sans-serif;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--gray);
        }

        .auth-container {
            background-color: white;
            border-radius: 50px;
            box-shadow: 0 20px 150px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 500px;
            overflow: hidden;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideInRight 0.8s ease-out;
        }

        @keyframes slideInLeft {
            from {
                transform: translateX(-50px);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideInRight {
            from {
                transform: translateX(50px);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .auth-header {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .logo i {
            font-size: 2rem;
            margin-right: 10px;
            color: white;
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: 700;
        }

        .auth-header p {
            opacity: 0.9;
            font-weight: 300;
        }

        .tabs {
            display: flex;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .tab {
            flex: 1;
            text-align: center;
            padding: 1.2rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            position: relative;
            color: var(--gray);
        }

        .tab.active {
            color: var(--primary);
        }

        .tab.active:after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            border-radius: 3px 3px 0 0;
        }

        .tab-content {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 30px;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 48px;
            cursor: pointer;
            color: var(--gray);
            background: none;
            border: none;
            outline: none;
            z-index: 2;
        }

        .password-toggle:hover {
            color: var(--primary);
        }

        .btn {
            width: 100%;
            padding: 1rem;
            border-radius: 30px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
            cursor: pointer;
            border: none;
            text-align: center;
            background: var(--dark);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem;
            font-size: 0.9rem;
            border-left: 4px solid;
            position: relative;
            overflow: hidden;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            border-left-color: var(--success);
            color: var(--success);
        }

        .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            border-left-color: var(--danger);
            color: var(--danger);
        }

        .alert-close {
            position: absolute;
            right: 10px;
            top: 10px;
            cursor: pointer;
            background: none;
            border: none;
            color: inherit;
            font-size: 1rem;
        }

        .doctor-fields {
            display: none;
            margin-top: 1rem;
            padding: 1rem;
            background-color: rgba(227, 67, 238, 0.05);
            border-radius: 30px;
            border-left: 3px solid orchid;
            margin-bottom: 0.5rem;
        }

        .user-type-selector {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .user-type-option {
            flex: 1;
        }

        .user-type-option input {
            display: none;
        }

        .user-type-option label {
            display: block;
            padding: 0.8rem;
            text-align: center;
            border-radius: 8px;
            background-color: rgba(0, 0, 0, 0.05);
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .user-type-option input:checked+label {
            background: orchid;
            color: white;
        }

        .auth-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.9rem;
            color: var(--gray);
        }

        .auth-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 5px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: rgb(235, 199, 234);
            opacity: 0.8;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: orchid;
        }


        /* Success message animation */
        .alert-success.fade-out {
            animation: fadeOut 0.5s ease-out forwards;
        }

        @keyframes fadeOut {
            to {
                opacity: 0;
                height: 0;
                padding: 0;
                margin: 0;
                border: none;
            }
        }

        /* Responsive styles */
        @media (max-width: 992px) {
            .container {
                flex-direction: column;
                padding: 40px 20px;
            }

            .welcome-section {
                text-align: center;
                max-width: 100%;
                padding: 20px;
            }

            .welcome-stats {
                justify-content: center;
            }

            .auth-container {
                max-width: 100%;
            }
        }

        @media (max-width: 576px) {
            .welcome-title {
                font-size: 2rem;
            }

            .welcome-stats {
                flex-direction: column;
                gap: 15px;
            }

            .tab {
                padding: 1rem;
                font-size: 0.9rem;
            }

            .tab-content {
                padding: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <!-- Background elements -->
    <div class="bg-circle bg-circle-1"></div>
    <div class="bg-circle bg-circle-2"></div>
    <div class="bg-circle bg-circle-3"></div>

    <div class="container">
        <div class="welcome-section">
            <div class="welcome-logo">
                <i class="fas fa-heartbeat"></i>
                <span>MediMind</span>
            </div>
            <h1 class="welcome-title">Telemedicine Platform For Everyone</h1>
            <p class="welcome-description">MediMind connects patients with licensed doctors for remote consultations
                through secure chat and video calls.</p>

            <div class="welcome-stats">
                <div class="stat-item">
                    <div class="stat-value">24/7</div>
                    <div class="stat-label">Doctor Access</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">Secure</div>
                    <div class="stat-label">Video Calls</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">5min</div>
                    <div class="stat-label">Avg. Wait Time</div>
                </div>
            </div>
        </div>

        <div class="auth-container">
            <div class="auth-header">
                <div class="logo">
                    <i class="fas fa-heartbeat"></i>
                    <span class="logo-text">MediMind</span>
                </div>
                <p>Telemedicine Platform</p>
            </div>

            <div class="tabs">
                <div class="tab <?php echo $activeTab === 'login' ? 'active' : ''; ?>" onclick="switchTab('login')">
                    Login</div>
                <div class="tab <?php echo $activeTab === 'register' ? 'active' : ''; ?>"
                    onclick="switchTab('register')">Register</div>
            </div>

            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($error); ?>
                        <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <?php foreach ($success as $message): ?>
                    <div class="alert alert-success" id="success-message">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div id="login-tab" class="tab-content"
                style="<?php echo $activeTab !== 'login' ? 'display: none;' : ''; ?>">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="login-username">Username or Email</label>
                        <input type="text" id="login-username" name="username" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="login-password">Password</label>
                        <input type="password" id="login-password" name="password" class="form-control" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('login-password', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>

                    <button type="submit" name="login" class="btn">Sign In</button>

                    <div class="auth-footer">
                        <a href="forgot-password.php">Forgot password?</a> |
                        Don't have an account? <a href="?register=1">Sign up</a>
                    </div>
                </form>
            </div>

            <div id="register-tab" class="tab-content"
                style="<?php echo $activeTab !== 'register' ? 'display: none;' : ''; ?>">
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Account Type</label>
                        <div class="user-type-selector">
                            <div class="user-type-option">
                                <input type="radio" id="patient" name="user_type" value="patient" checked>
                                <label for="patient">Patient</label>
                            </div>
                            <div class="user-type-option">
                                <input type="radio" id="doctor" name="user_type" value="doctor">
                                <label for="doctor">Doctor</label>
                            </div>
                            <div class="user-type-option">
                                <input type="radio" id="admin" name="user_type" value="admin">
                                <label for="admin">Admin</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="register-username">Username</label>
                        <input type="text" id="register-username" name="username" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="register-email">Email</label>
                        <input type="email" id="register-email" name="email" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="register-first-name">First Name</label>
                        <input type="text" id="register-first-name" name="first_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="register-last-name">Last Name</label>
                        <input type="text" id="register-last-name" name="last_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="register-password">Password</label>
                        <input type="password" id="register-password" name="password" class="form-control" required>
                        <button type="button" class="password-toggle"
                            onclick="togglePassword('register-password', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>

                    <div class="form-group">
                        <label for="register-phone">Phone Number</label>
                        <input type="tel" id="register-phone" name="phone" class="form-control">
                    </div>

                    <div class="form-group">
                        <label for="register-dob">Date of Birth</label>
                        <input type="date" id="register-dob" name="date_of_birth" class="form-control">
                    </div>

                    <div id="doctor-fields" class="doctor-fields">
                        <div class="form-group">
                            <label for="license-number">Medical License Number</label>
                            <input type="text" id="license-number" name="license_number" class="form-control">
                        </div>

                        <div class="form-group">
                            <label for="specialization">Specialization</label>
                            <input type="text" id="specialization" name="specialization" class="form-control">
                        </div>

                        <div class="form-group">
                            <label for="qualifications">Qualifications</label>
                            <input type="text" id="qualifications" name="qualifications" class="form-control">
                        </div>

                        <div class="form-group">
                            <label for="consultation-fee">Consultation Fee ($)</label>
                            <input type="number" id="consultation-fee" name="consultation_fee" class="form-control"
                                min="0" step="0.01">
                        </div>
                    </div>

                    <button type="submit" name="register" class="btn">Create Account</button>

                    <div class="auth-footer">
                        Already have an account? <a href="?login=1">Sign in</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            document.getElementById('login-tab').style.display = tabName === 'login' ? 'block' : 'none';
            document.getElementById('register-tab').style.display = tabName === 'register' ? 'block' : 'none';

            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => {
                tab.classList.remove('active');
                if (tab.textContent.trim().toLowerCase() === tabName) {
                    tab.classList.add('active');
                }
            });

            // Update URL without reload
            const url = new URL(window.location);
            if (tabName === 'register') {
                url.searchParams.set('register', '1');
                url.searchParams.delete('login');
            } else {
                url.searchParams.set('login', '1');
                url.searchParams.delete('register');
            }
            window.history.pushState({}, '', url);
        }

        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Toggle doctor fields based on user type selection
        document.querySelectorAll('input[name="user_type"]').forEach(radio => {
            radio.addEventListener('change', function () {
                const doctorFields = document.getElementById('doctor-fields');
                if (this.value === 'doctor') {
                    doctorFields.style.display = 'block';
                    // Make doctor fields required
                    document.getElementById('license-number').required = true;
                    document.getElementById('specialization').required = true;
                    document.getElementById('qualifications').required = true;
                    document.getElementById('consultation-fee').required = true;
                } else {
                    doctorFields.style.display = 'none';
                    // Remove required from doctor fields
                    document.getElementById('license-number').required = false;
                    document.getElementById('specialization').required = false;
                    document.getElementById('qualifications').required = false;
                    document.getElementById('consultation-fee').required = false;
                }
            });
        });

        // Check URL for tab parameter on page load
        document.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('register')) {
                switchTab('register');
            } else {
                switchTab('login');
            }

            // Auto-hide success messages after 5 seconds
            const successMessage = document.getElementById('success-message');
            if (successMessage) {
                setTimeout(() => {
                    successMessage.classList.add('fade-out');
                    setTimeout(() => successMessage.remove(), 500);
                }, 5000);
            }
        });
    </script>
</body>

</html>