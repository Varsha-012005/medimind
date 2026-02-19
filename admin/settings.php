<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || !hasRole('admin')) {
    redirect(BASE_URL . '/index.php');
}

$userId = getCurrentUserId();
$user = getUserById($userId);

// Handle form submission
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Update system settings
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'setting_') === 0) {
                $settingKey = substr($key, 8);
                // Validate and sanitize input based on setting type
                $sanitizedValue = sanitizeInput($value);
                
                // Special handling for checkbox values
                if (is_array($value)) {
                    $sanitizedValue = implode(',', array_map('sanitizeInput', $value));
                } elseif (in_array($settingKey, ['dark_mode', 'maintenance_mode', 'allow_same_day_appointments', 
                       'new_appointment_alert', 'cancellation_alert', 'single_session', 'require_2fa_admin'])) {
                    $sanitizedValue = $value === '1' ? '1' : '0';
                }
                
                $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->execute([$sanitizedValue, $settingKey]);
            }
        }
        
        // Handle SMTP test email
        if (isset($_POST['smtp_test_email'])) {
            $testEmail = sanitizeInput($_POST['smtp_test_email'], 'email');
            if ($testEmail) {
                $success = "SMTP settings saved successfully. Test email would be sent to $testEmail in a real implementation.";
                logAction('smtp_test', 'system_settings', null);
            } 
        } else {
            $success = "System settings updated successfully!";
        }
        
        // Log the settings update
        logAction('update_settings', 'system_settings', null);
        
        // Reload system settings to apply changes immediately
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
        $systemSettings = [];
        while ($row = $stmt->fetch()) {
            $systemSettings[$row['setting_key']] = ['value' => $row['setting_value'], 'description' => $row['description'] ?? ''];
        }
        
        // Apply timezone change immediately
        if (isset($systemSettings['system_timezone']['value'])) {
            date_default_timezone_set($systemSettings['system_timezone']['value']);
        }
        
    } catch (PDOException $e) {
        error_log("Settings update failed: " . $e->getMessage());
        $errors[] = "Failed to update settings. Please try again.";
    }
}

// Load current system settings
$systemSettings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value, description FROM system_settings");
    while ($row = $stmt->fetch()) {
        $systemSettings[$row['setting_key']] = ['value' => $row['setting_value'], 'description' => $row['description']];
    }
} catch (PDOException $e) {
    error_log("Failed to load system settings: " . $e->getMessage());
    $errors[] = "Failed to load system settings. Please try again.";
}

// Get unread notifications for the header
$unreadNotifications = getUnreadNotifications($userId);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediMind - System Settings</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/admin.css">
    <style>
        .settings-tabs {
            display: flex;
            margin-bottom: 25px;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .settings-tab {
            padding: 12px 20px;
            cursor: pointer;
            font-weight: 500;
            color: var(--gray);
            position: relative;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
        }
        
        .settings-tab:hover {
            color: var(--primary);
        }
        
        .settings-tab.active {
            color: var(--primary);
            border-bottom: 3px solid var(--primary);
        }
        
        .tab-content {
            display: none;
            animation: fadeIn 0.5s ease;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-section {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            padding: 25px;
            margin-bottom: 25px;
        }
        
        .form-section h3 {
            margin-bottom: 20px;
            color: var(--primary);
            font-size: 18px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 80%;
            padding: 10px 15px;
            border: 1px solid var(--gray-light);
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        
        .setting-description {
            font-size: 13px;
            color: var(--gray);
            margin-top: 5px;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: var(--primary);
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
        
        .toggle-label {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        @media (max-width: 768px) {
            .settings-tabs {
                flex-direction: column;
                border-bottom: none;
            }
            
            .settings-tab {
                border-bottom: 1px solid var(--gray-light);
                border-left: 3px solid transparent;
            }
            
            .settings-tab.active {
                border-bottom: 1px solid var(--gray-light);
                border-left: 3px solid var(--primary);
            }
            
            .form-row {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>

<body>
    <div class="admin-dashboard">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-heartbeat"></i>
                    <span>MediMind</span>
                </div>
                <div class="user-info">
                    <div class="avatar">
                        <?php if (!empty($user['profile_picture'])): ?>
                            <img src="<?php echo BASE_URL . '/' . $user['profile_picture']; ?>" alt="Profile Picture">
                        <?php else: ?>
                            <i class="fas fa-user-circle"></i>
                        <?php endif; ?>
                    </div>
                    <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                    <div class="user-role">Administrator</div>
                </div>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
                    <li><a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li class="active"><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="../includes/auth.php?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="main-header">
                <h1>System Settings</h1>
                <div class="header-actions">
                    <div class="notifications">
                        <i class="fas fa-bell"></i>
                        <?php if (!empty($unreadNotifications)): ?>
                            <span class="badge"><?php echo count($unreadNotifications); ?></span>
                        <?php endif; ?>
                        <div class="notifications-dropdown">
                            <?php if (!empty($unreadNotifications)): ?>
                                <?php foreach ($unreadNotifications as $notification): ?>
                                    <a href="<?php echo !empty($notification['link']) ? $notification['link'] : '#'; ?>" class="notification-item">
                                        <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                                        <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                                        <div class="notification-time"><?php echo formatDate($notification['created_at'], 'M j, g:i a'); ?></div>
                                    </a>
                                <?php endforeach; ?>
                                <a href="?mark_read=all" class="mark-all-read">Mark all as read</a>
                            <?php else: ?>
                                <div class="notification-empty">No new notifications</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </header>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                    <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                    <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endif; ?>

            <div class="settings-tabs">
                <div class="settings-tab active" data-tab="general">General</div>
                <div class="settings-tab" data-tab="appointments">Appointments</div>
                <div class="settings-tab" data-tab="email">Email</div>
                <div class="settings-tab" data-tab="security">Security</div>
            </div>

            <form method="POST">
                <!-- General Settings Tab -->
                <div class="tab-content active" id="general-tab">
                    <div class="form-section">
                        <h3>General Settings</h3>
                        
                        <div class="form-group">
                            <label for="setting_system_name">System Name</label>
                            <input type="text" id="setting_system_name" name="setting_system_name" 
                                   value="<?php echo htmlspecialchars($systemSettings['system_name']['value'] ?? 'MediMind'); ?>">
                            <div class="setting-description"><?php echo htmlspecialchars($systemSettings['system_name']['description'] ?? ''); ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="setting_system_timezone">System Timezone</label>
                            <select id="setting_system_timezone" name="setting_system_timezone">
                                <?php
                                $timezones = DateTimeZone::listIdentifiers();
                                foreach ($timezones as $tz) {
                                    $selected = ($systemSettings['system_timezone']['value'] ?? 'UTC') === $tz ? 'selected' : '';
                                    echo "<option value=\"$tz\" $selected>$tz</option>";
                                }
                                ?>
                            </select>
                            <div class="setting-description"><?php echo htmlspecialchars($systemSettings['system_timezone']['description'] ?? ''); ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="setting_primary_color">Primary Color</label>
                            <input type="color" id="setting_primary_color" name="setting_primary_color" 
                                   value="<?php echo htmlspecialchars($systemSettings['primary_color']['value'] ?? '#4361ee'); ?>">
                            <div class="setting-description"><?php echo htmlspecialchars($systemSettings['primary_color']['description'] ?? ''); ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="setting_accent_color">Accent Color</label>
                            <input type="color" id="setting_accent_color" name="setting_accent_color" 
                                   value="<?php echo htmlspecialchars($systemSettings['accent_color']['value'] ?? '#7209b7'); ?>">
                            <div class="setting-description"><?php echo htmlspecialchars($systemSettings['accent_color']['description'] ?? ''); ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label class="toggle-label">
                                <span>Dark Mode</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="setting_dark_mode" name="setting_dark_mode" 
                                           value="1" <?php echo ($systemSettings['dark_mode']['value'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </label>
                            <div class="setting-description"><?php echo htmlspecialchars($systemSettings['dark_mode']['description'] ?? ''); ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label class="toggle-label">
                                <span>Maintenance Mode</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="setting_maintenance_mode" name="setting_maintenance_mode" 
                                           value="1" <?php echo ($systemSettings['maintenance_mode']['value'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </label>
                            <div class="setting-description"><?php echo htmlspecialchars($systemSettings['maintenance_mode']['description'] ?? ''); ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Appointment Settings Tab -->
                <div class="tab-content" id="appointments-tab">
                    <div class="form-section">
                        <h3>Appointment Settings</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="setting_default_appointment_duration">Default Duration (minutes)</label>
                                <input type="number" id="setting_default_appointment_duration" name="setting_default_appointment_duration" 
                                       value="<?php echo htmlspecialchars($systemSettings['default_appointment_duration']['value'] ?? '30'); ?>">
                                <div class="setting-description"><?php echo htmlspecialchars($systemSettings['default_appointment_duration']['description'] ?? ''); ?></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="setting_booking_lead_time">Minimum Booking Lead Time (hours)</label>
                                <input type="number" id="setting_booking_lead_time" name="setting_booking_lead_time" 
                                       value="<?php echo htmlspecialchars($systemSettings['booking_lead_time']['value'] ?? '24'); ?>">
                                <div class="setting-description"><?php echo htmlspecialchars($systemSettings['booking_lead_time']['description'] ?? ''); ?></div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="setting_booking_window">Maximum Booking Window (days)</label>
                                <input type="number" id="setting_booking_window" name="setting_booking_window" 
                                       value="<?php echo htmlspecialchars($systemSettings['booking_window']['value'] ?? '90'); ?>">
                                <div class="setting-description"><?php echo htmlspecialchars($systemSettings['booking_window']['description'] ?? ''); ?></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="setting_reminder_hours">Reminder Before Appointment (hours)</label>
                                <input type="number" id="setting_reminder_hours" name="setting_reminder_hours" 
                                       value="<?php echo htmlspecialchars($systemSettings['reminder_hours']['value'] ?? '24'); ?>">
                                <div class="setting-description"><?php echo htmlspecialchars($systemSettings['reminder_hours']['description'] ?? ''); ?></div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="toggle-label">
                                <span>Allow Same-Day Appointments</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="setting_allow_same_day_appointments" name="setting_allow_same_day_appointments" 
                                           value="1" <?php echo ($systemSettings['allow_same_day_appointments']['value'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </label>
                            <div class="setting-description"><?php echo htmlspecialchars($systemSettings['allow_same_day_appointments']['description'] ?? ''); ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label class="toggle-label">
                                <span>Notify Doctor on New Appointment</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="setting_new_appointment_alert" name="setting_new_appointment_alert" 
                                           value="1" <?php echo ($systemSettings['new_appointment_alert']['value'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </label>
                            <div class="setting-description"><?php echo htmlspecialchars($systemSettings['new_appointment_alert']['description'] ?? ''); ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label class="toggle-label">
                                <span>Notify Doctor on Cancellation</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="setting_cancellation_alert" name="setting_cancellation_alert" 
                                           value="1" <?php echo ($systemSettings['cancellation_alert']['value'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </label>
                            <div class="setting-description"><?php echo htmlspecialchars($systemSettings['cancellation_alert']['description'] ?? ''); ?></div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3>Cancellation Policy</h3>
                        
                        <div class="form-group">
                            <label for="setting_cancellation_notice">Cancellation Notice (hours)</label>
                            <input type="number" id="setting_cancellation_notice" name="setting_cancellation_notice" 
                                   value="<?php echo htmlspecialchars($systemSettings['cancellation_notice']['value'] ?? '24'); ?>">
                            <div class="setting-description"><?php echo htmlspecialchars($systemSettings['cancellation_notice']['description'] ?? ''); ?></div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="setting_late_cancellation_fee">Late Cancellation Fee (%)</label>
                                <input type="number" id="setting_late_cancellation_fee" name="setting_late_cancellation_fee" min="0" max="100"
                                       value="<?php echo htmlspecialchars($systemSettings['late_cancellation_fee']['value'] ?? '20'); ?>">
                                <div class="setting-description"><?php echo htmlspecialchars($systemSettings['late_cancellation_fee']['description'] ?? ''); ?></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="setting_no_show_fee">No-Show Fee (%)</label>
                                <input type="number" id="setting_no_show_fee" name="setting_no_show_fee" min="0" max="100"
                                       value="<?php echo htmlspecialchars($systemSettings['no_show_fee']['value'] ?? '50'); ?>">
                                <div class="setting-description"><?php echo htmlspecialchars($systemSettings['no_show_fee']['description'] ?? ''); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Email Settings Tab -->
                <div class="tab-content" id="email-tab">
                    <div class="form-section">
                        <h3>SMTP Email Settings</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="setting_smtp_host">SMTP Host</label>
                                <input type="text" id="setting_smtp_host" name="setting_smtp_host" 
                                       value="<?php echo htmlspecialchars($systemSettings['smtp_host']['value'] ?? ''); ?>">
                                <div class="setting-description"><?php echo htmlspecialchars($systemSettings['smtp_host']['description'] ?? ''); ?></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="setting_smtp_port">SMTP Port</label>
                                <input type="number" id="setting_smtp_port" name="setting_smtp_port" 
                                       value="<?php echo htmlspecialchars($systemSettings['smtp_port']['value'] ?? '587'); ?>">
                                <div class="setting-description"><?php echo htmlspecialchars($systemSettings['smtp_port']['description'] ?? ''); ?></div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="setting_smtp_username">SMTP Username</label>
                                <input type="text" id="setting_smtp_username" name="setting_smtp_username" 
                                       value="<?php echo htmlspecialchars($systemSettings['smtp_username']['value'] ?? ''); ?>">
                                <div class="setting-description"><?php echo htmlspecialchars($systemSettings['smtp_username']['description'] ?? ''); ?></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="setting_smtp_password">SMTP Password</label>
                                <input type="password" id="setting_smtp_password" name="setting_smtp_password" 
                                       value="<?php echo htmlspecialchars($systemSettings['smtp_password']['value'] ?? ''); ?>">
                                <div class="setting-description"><?php echo htmlspecialchars($systemSettings['smtp_password']['description'] ?? ''); ?></div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="setting_from_email">From Email</label>
                                <input type="email" id="setting_from_email" name="setting_from_email" 
                                       value="<?php echo htmlspecialchars($systemSettings['from_email']['value'] ?? 'noreply@medimind.example.com'); ?>">
                                <div class="setting-description"><?php echo htmlspecialchars($systemSettings['from_email']['description'] ?? ''); ?></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="setting_from_name">From Name</label>
                                <input type="text" id="setting_from_name" name="setting_from_name" 
                                       value="<?php echo htmlspecialchars($systemSettings['from_name']['value'] ?? 'MediMind System'); ?>">
                                <div class="setting-description"><?php echo htmlspecialchars($systemSettings['from_name']['description'] ?? ''); ?></div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="smtp_test_email">Test Email Address</label>
                            <input type="email" id="smtp_test_email" name="smtp_test_email" placeholder="Enter email to test SMTP settings">
                            <button type="submit" class="btn btn-primary" style="margin-top: 10px;">Test SMTP Settings</button>
                        </div>
                    </div>
                </div>
                
                <!-- Security Settings Tab -->
                <div class="tab-content" id="security-tab">
                    <div class="form-section">
                        <h3>Password Policy</h3>
                        
                        <div class="form-group">
                            <label for="setting_password_complexity">Password Complexity</label>
                            <select id="setting_password_complexity" name="setting_password_complexity">
                                <option value="low" <?php echo ($systemSettings['password_complexity']['value'] ?? 'medium') === 'low' ? 'selected' : ''; ?>>Low (minimum 6 characters)</option>
                                <option value="medium" <?php echo ($systemSettings['password_complexity']['value'] ?? 'medium') === 'medium' ? 'selected' : ''; ?>>Medium (minimum 8 characters with mix of letters and numbers)</option>
                                <option value="high" <?php echo ($systemSettings['password_complexity']['value'] ?? 'medium') === 'high' ? 'selected' : ''; ?>>High (minimum 10 characters with uppercase, lowercase, numbers and special characters)</option>
                            </select>
                            <div class="setting-description"><?php echo htmlspecialchars($systemSettings['password_complexity']['description'] ?? ''); ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="setting_password_expiration">Password Expiration (days)</label>
                            <input type="number" id="setting_password_expiration" name="setting_password_expiration" 
                                   value="<?php echo htmlspecialchars($systemSettings['password_expiration']['value'] ?? '90'); ?>">
                            <div class="setting-description">Set to 0 to disable password expiration</div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3>Login Security</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="setting_failed_login_attempts">Max Failed Attempts</label>
                                <input type="number" id="setting_failed_login_attempts" name="setting_failed_login_attempts" 
                                       value="<?php echo htmlspecialchars($systemSettings['failed_login_attempts']['value'] ?? '5'); ?>">
                                <div class="setting-description"><?php echo htmlspecialchars($systemSettings['failed_login_attempts']['description'] ?? ''); ?></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="setting_lockout_duration">Lockout Duration (minutes)</label>
                                <input type="number" id="setting_lockout_duration" name="setting_lockout_duration" 
                                       value="<?php echo htmlspecialchars($systemSettings['lockout_duration']['value'] ?? '15'); ?>">
                                <div class="setting-description"><?php echo htmlspecialchars($systemSettings['lockout_duration']['description'] ?? ''); ?></div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="setting_session_timeout">Session Timeout (minutes)</label>
                            <input type="number" id="setting_session_timeout" name="setting_session_timeout" 
                                   value="<?php echo htmlspecialchars($systemSettings['session_timeout']['value'] ?? '30'); ?>">
                            <div class="setting-description"><?php echo htmlspecialchars($systemSettings['session_timeout']['description'] ?? ''); ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label class="toggle-label">
                                <span>Single Session Per User</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="setting_single_session" name="setting_single_session" 
                                           value="1" <?php echo ($systemSettings['single_session']['value'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </label>
                            <div class="setting-description"><?php echo htmlspecialchars($systemSettings['single_session']['description'] ?? ''); ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label class="toggle-label">
                                <span>Require 2FA for Admins</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="setting_require_2fa_admin" name="setting_require_2fa_admin" 
                                           value="1" <?php echo ($systemSettings['require_2fa_admin']['value'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </label>
                            <div class="setting-description"><?php echo htmlspecialchars($systemSettings['require_2fa_admin']['description'] ?? ''); ?></div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3>Backup & Recovery</h3>
                        
                        <div class="form-group">
                            <button type="button" id="backup-now" class="btn btn-primary">Create Database Backup Now</button>
                            <div class="setting-description">Manually create a backup of the database</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="backup-file">Restore from Backup</label>
                            <input type="file" id="backup-file" name="backup-file" accept=".sql,.gz,.zip">
                            <button type="button" id="restore-backup" class="btn btn-primary" style="margin-top: 10px;">Restore Backup</button>
                            <div class="setting-description">Warning: This will overwrite current data</div>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <button type="submit" class="btn btn-primary">Save All Settings</button>
                </div>
            </form>
        </main>
    </div>

    <script>
        // Tab switching functionality
        document.querySelectorAll('.settings-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs and content
                document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                // Add active class to clicked tab
                this.classList.add('active');
                
                // Show corresponding content
                const tabId = this.getAttribute('data-tab');
                document.getElementById(`${tabId}-tab`).classList.add('active');
            });
        });
        
        // Auto-hide success message after 5 seconds
        const successMessage = document.querySelector('.alert-success');
        if (successMessage) {
            setTimeout(() => {
                successMessage.classList.add('fade-out');
                setTimeout(() => successMessage.remove(), 500);
            }, 5000);
        }
        
        // Toggle switch functionality
        document.querySelectorAll('.toggle-switch input').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const slider = this.nextElementSibling;
                if (this.checked) {
                    slider.style.backgroundColor = '#4361ee';
                } else {
                    slider.style.backgroundColor = '#ccc';
                }
            });
        });
        
        // Backup button functionality
        document.getElementById('backup-now')?.addEventListener('click', function() {
            alert('In a real implementation, this would trigger a database backup.');
        });
        
        // Restore backup button functionality
        document.getElementById('restore-backup')?.addEventListener('click', function() {
            const fileInput = document.getElementById('backup-file');
            if (fileInput.files.length === 0) {
                alert('Please select a backup file first.');
                return;
            }
            
            if (confirm('WARNING: This will overwrite all current data. Are you sure you want to proceed?')) {
                alert('In a real implementation, this would upload and restore the backup file.');
            }
        });
    </script>
</body>

</html>