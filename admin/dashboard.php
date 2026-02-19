<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || !hasRole('admin')) {
    redirect(BASE_URL . '/index.php');
}

$userId = getCurrentUserId();
$user = getUserById($userId);
$unreadNotifications = getUnreadNotifications($userId);

// Mark notifications as read if requested
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    markNotificationsAsRead($_GET['mark_read']);
}

// Get statistics for dashboard
$stats = [];
try {
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users");
    $stats['total_users'] = $stmt->fetch()['total_users'];

    // Total patients
    $stmt = $pdo->query("SELECT COUNT(*) as total_patients FROM users WHERE user_type = 'patient'");
    $stats['total_patients'] = $stmt->fetch()['total_patients'];

    // Total doctors
    $stmt = $pdo->query("SELECT COUNT(*) as total_doctors FROM users WHERE user_type = 'doctor'");
    $stats['total_doctors'] = $stmt->fetch()['total_doctors'];

    // Total appointments
    $stmt = $pdo->query("SELECT COUNT(*) as total_appointments FROM appointments");
    $stats['total_appointments'] = $stmt->fetch()['total_appointments'];

    // Recent appointments
    $stmt = $pdo->prepare("
        SELECT a.*, 
               p.first_name AS patient_first_name, p.last_name AS patient_last_name,
               d.first_name AS doctor_first_name, d.last_name AS doctor_last_name
        FROM appointments a
        JOIN users p ON a.patient_id = p.user_id
        JOIN users d ON a.doctor_id = d.user_id
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recentAppointments = $stmt->fetchAll();

    // Recent users
    $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
    $recentUsers = $stmt->fetchAll();

    // System health
    $stmt = $pdo->query("SELECT COUNT(*) as unapproved_doctors FROM doctors WHERE is_approved = FALSE");
    $stats['unapproved_doctors'] = $stmt->fetch()['unapproved_doctors'];

} catch (PDOException $e) {
    error_log("Failed to fetch dashboard statistics: " . $e->getMessage());
    $errors[] = "Failed to load dashboard data. Please try again.";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediMind - Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/admin.css">
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
                    <li class="active"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
                    <li><a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="../includes/auth.php?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="main-header">
                <h1>Dashboard Overview</h1>
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

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                    <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <div class="dashboard-grid">
                <!-- Stats Cards -->
                <div class="card stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo $stats['total_users'] ?? 0; ?></div>
                        <div class="stat-label">Total Users</div>
                    </div>
                </div>

                <div class="card stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-procedures"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo $stats['total_patients'] ?? 0; ?></div>
                        <div class="stat-label">Patients</div>
                    </div>
                </div>

                <div class="card stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo $stats['total_doctors'] ?? 0; ?></div>
                        <div class="stat-label">Doctors</div>
                    </div>
                </div>

                <div class="card stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo $stats['total_appointments'] ?? 0; ?></div>
                        <div class="stat-label">Appointments</div>
                    </div>
                </div>

                <!-- Recent Appointments -->
                <div class="card recent-appointments">
                    <div class="card-header">
                        <h2>Recent Appointments</h2>
                        <a href="reports.php?tab=appointments" class="btn btn-outline">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recentAppointments)): ?>
                            <div class="appointment-list">
                                <?php foreach ($recentAppointments as $appointment): ?>
                                    <div class="appointment-item">
                                        <div class="appointment-info">
                                            <div class="appointment-date">
                                                <div class="date"><?php echo formatDate($appointment['appointment_date'], 'M j'); ?></div>
                                                <div class="time"><?php echo formatTime($appointment['start_time']); ?></div>
                                            </div>
                                            <div class="appointment-details">
                                                <div class="doctor-name">Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?></div>
                                                <div class="patient-name">Patient: <?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?></div>
                                                <div class="appointment-status">
                                                    <span class="status-badge <?php echo strtolower($appointment['status']); ?>"><?php echo $appointment['status']; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="appointment-actions">
                                            <a href="#" class="btn btn-icon" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p>No recent appointments found.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Users -->
                <div class="card recent-users">
                    <div class="card-header">
                        <h2>Recent Users</h2>
                        <a href="users.php" class="btn btn-outline">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recentUsers)): ?>
                            <div class="user-list">
                                <?php foreach ($recentUsers as $user): ?>
                                    <div class="user-item">
                                        <div class="user-avatar">
                                            <?php if (!empty($user['profile_picture'])): ?>
                                                <img src="<?php echo BASE_URL . '/' . $user['profile_picture']; ?>" alt="Profile Picture">
                                            <?php else: ?>
                                                <i class="fas fa-user-circle"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="user-details">
                                            <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                            <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                            <div class="user-type">
                                                <span class="type-badge <?php echo $user['user_type']; ?>"><?php echo ucfirst($user['user_type']); ?></span>
                                            </div>
                                        </div>
                                        <div class="user-actions">
                                            <a href="users.php?action=edit&id=<?php echo $user['user_id']; ?>" class="btn btn-icon" title="Edit User">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p>No recent users found.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- System Health -->
                <div class="card system-health">
                    <div class="card-header">
                        <h2>System Health</h2>
                    </div>
                    <div class="card-body">
                        <div class="health-item">
                            <div class="health-label">Unapproved Doctors</div>
                            <div class="health-value"><?php echo $stats['unapproved_doctors'] ?? 0; ?></div>
                            <a href="doctors.php?filter=unapproved" class="btn btn-sm">Review</a>
                        </div>
                        <div class="health-item">
                            <div class="health-label">Database Size</div>
                            <div class="health-value">
                                <?php
                                try {
                                    $stmt = $pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size FROM information_schema.TABLES WHERE table_schema = DATABASE()");
                                    $size = $stmt->fetch()['size'];
                                    echo $size . ' MB';
                                } catch (PDOException $e) {
                                    echo 'N/A';
                                }
                                ?>
                            </div>
                        </div>
                        <div class="health-item">
                            <div class="health-label">System Version</div>
                            <div class="health-value">1.0.0</div>
                        </div>
                        <div class="health-item">
                            <div class="health-label">Last Backup</div>
                            <div class="health-value">
                                <?php
                                try {
                                    $stmt = $pdo->query("SELECT MAX(created_at) as last_backup FROM audit_log WHERE action = 'backup'");
                                    $lastBackup = $stmt->fetch()['last_backup'];
                                    echo $lastBackup ? formatDate($lastBackup, 'M j, Y') : 'Never';
                                } catch (PDOException $e) {
                                    echo 'N/A';
                                }
                                ?>
                            </div>
                            <a href="settings.php?tab=backup" class="btn btn-sm">Backup Now</a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Toggle notifications dropdown
        document.querySelector('.notifications').addEventListener('click', function(e) {
            e.stopPropagation();
            this.querySelector('.notifications-dropdown').classList.toggle('show');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            document.querySelectorAll('.notifications-dropdown').forEach(dropdown => {
                dropdown.classList.remove('show');
            });
        });

        // Prevent dropdown from closing when clicking inside
        document.querySelectorAll('.notifications-dropdown').forEach(dropdown => {
            dropdown.addEventListener('click', function(e) {
                e.stopPropagation();
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

        // Animation for cards
        document.querySelectorAll('.card').forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
            card.classList.add('fade-in');
        });

        // Check visibility on scroll
        function checkVisibility() {
            const elements = document.querySelectorAll('.fade-in');
            elements.forEach(element => {
                const elementTop = element.getBoundingClientRect().top;
                const windowHeight = window.innerHeight;
                if (elementTop < windowHeight - 100) {
                    element.classList.add('visible');
                }
            });
        }

        // Initial check
        checkVisibility();

        // Check on scroll
        window.addEventListener('scroll', checkVisibility);

        // Load charts if needed
        document.addEventListener('DOMContentLoaded', function() {
            // You can initialize charts here if needed
            // Example: initializeUsageChart();
        });

        // Example chart function
        function initializeUsageChart() {
            // This would be replaced with actual chart initialization code
            console.log('Initializing usage chart...');
        }
    </script>
</body>

</html>