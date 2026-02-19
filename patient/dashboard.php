<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in and is a patient
if (!isLoggedIn() || !hasRole('patient')) {
    redirect(BASE_URL . '/index.php');
}

$userId = getCurrentUserId();
$user = getUserById($userId);
$healthProfile = getPatientHealthProfile($userId);
$appointments = getUserAppointments($userId, 'patient', 'scheduled');
$unreadNotifications = getUnreadNotifications($userId);

// Mark notifications as read if requested
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    markNotificationsAsRead($_GET['mark_read']);
}

// Handle appointment cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment'])) {
    $appointmentId = sanitizeInput($_POST['appointment_id'], 'int');
    
    try {
        $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE appointment_id = ? AND patient_id = ?");
        $stmt->execute([$appointmentId, $userId]);
        
        // Notify the doctor
        $stmt = $pdo->prepare("SELECT doctor_id FROM appointments WHERE appointment_id = ?");
        $stmt->execute([$appointmentId]);
        $appointment = $stmt->fetch();
        
        if ($appointment) {
            sendNotification(
                $appointment['doctor_id'],
                'Appointment Cancelled',
                "Your appointment with {$user['first_name']} {$user['last_name']} has been cancelled",
                "doctor/appointments.php"
            );
        }
        
        // Log the action
        logAction('cancel', 'appointments', $appointmentId);
        
        // Refresh appointments
        $appointments = getUserAppointments($userId, 'patient', 'scheduled');
        
        // Set success message
        $_SESSION['success_message'] = "Appointment cancelled successfully";
        redirect(BASE_URL . '/patient/dashboard.php');
    } catch (PDOException $e) {
        error_log("Failed to cancel appointment: " . $e->getMessage());
        $errors[] = "Failed to cancel appointment. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediMind - Patient Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/patient.css">
</head>

<body>
    <div class="patient-dashboard">
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
                    <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . htmlspecialchars($user['last_name'])); ?></div>
                    <div class="user-role">Patient</div>
                </div>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li class="active"><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
                    <li><a href="medical_records.php"><i class="fas fa-file-medical"></i> Medical Records</a></li>
                    <li><a href="chat.php"><i class="fas fa-comments"></i> Messages</a></li>
                    <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                    <li><a href="../includes/auth.php?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="main-header">
                <h1>Dashboard</h1>
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
                <!-- Health Summary Card -->
                <div class="card health-summary">
                    <div class="card-header">
                        <h2>Health Summary</h2>
                        <a href="profile.php" class="btn btn-outline">View Full Profile</a>
                    </div>
                    <div class="card-body">
                        <?php if ($healthProfile): ?>
                            <div class="health-stats">
                                <div class="stat-item">
                                    <div class="stat-label">Blood Type</div>
                                    <div class="stat-value"><?php echo $healthProfile['blood_type'] ?? 'Not set'; ?></div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-label">Height</div>
                                    <div class="stat-value"><?php echo $healthProfile['height'] ? $healthProfile['height'] . ' cm' : 'Not set'; ?></div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-label">Weight</div>
                                    <div class="stat-value"><?php echo $healthProfile['weight'] ? $healthProfile['weight'] . ' kg' : 'Not set'; ?></div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-label">Allergies</div>
                                    <div class="stat-value"><?php echo $healthProfile['allergies'] ? substr($healthProfile['allergies'], 0, 20) . (strlen($healthProfile['allergies']) > 20 ? '...' : '') : 'None'; ?></div>
                                </div>
                            </div>
                        <?php else: ?>
                            <p>No health profile information available. <a href="profile.php">Complete your profile</a></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Upcoming Appointments -->
                <div class="card appointments">
                    <div class="card-header">
                        <h2>Upcoming Appointments</h2>
                        <a href="appointments.php" class="btn btn-outline">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($appointments)): ?>
                            <div class="appointment-list">
                                <?php foreach ($appointments as $appointment): ?>
                                    <div class="appointment-item">
                                        <div class="appointment-info">
                                            <div class="appointment-date">
                                                <div class="date"><?php echo formatDate($appointment['appointment_date'], 'M j'); ?></div>
                                                <div class="time"><?php echo formatTime($appointment['start_time']); ?></div>
                                            </div>
                                            <div class="appointment-details">
                                                <div class="doctor-name">Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?></div>
                                                <div class="appointment-reason"><?php echo htmlspecialchars($appointment['reason'] ? substr($appointment['reason'], 0, 30) . (strlen($appointment['reason']) > 30 ? '...' : '') : 'No reason provided'); ?></div>
                                            </div>
                                        </div>
                                        <div class="appointment-actions">
                                            <a href="chat.php?doctor_id=<?php echo $appointment['doctor_id']; ?>" class="btn btn-icon" title="Message Doctor">
                                                <i class="fas fa-comment"></i>
                                            </a>
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this appointment?');">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                                                <button type="submit" name="cancel_appointment" class="btn btn-icon btn-danger" title="Cancel Appointment">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p>No upcoming appointments. <a href="appointments.php">Schedule a consultation</a></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card quick-actions">
                    <div class="card-header">
                        <h2>Quick Actions</h2>
                    </div>
                    <div class="card-body">
                        <div class="action-grid">
                            <a href="appointments.php?new=1" class="action-item">
                                <div class="action-icon">
                                    <i class="fas fa-calendar-plus"></i>
                                </div>
                                <div class="action-text">New Appointment</div>
                            </a>
                            <a href="medical_records.php" class="action-item">
                                <div class="action-icon">
                                    <i class="fas fa-file-upload"></i>
                                </div>
                                <div class="action-text">Upload Records</div>
                            </a>
                            <a href="chat.php" class="action-item">
                                <div class="action-icon">
                                    <i class="fas fa-comment-medical"></i>
                                </div>
                                <div class="action-text">Message Doctor</div>
                            </a>
                            <a href="profile.php" class="action-item">
                                <div class="action-icon">
                                    <i class="fas fa-user-edit"></i>
                                </div>
                                <div class="action-text">Update Profile</div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Recent Medical Records -->
                <div class="card recent-records">
                    <div class="card-header">
                        <h2>Recent Medical Records</h2>
                        <a href="medical_records.php" class="btn btn-outline">View All</a>
                    </div>
                    <div class="card-body">
                        <?php
                        $recentRecords = [];
                        try {
                            $stmt = $pdo->prepare("SELECT mr.*, u.first_name, u.last_name 
                                                  FROM medical_records mr
                                                  JOIN users u ON mr.created_by = u.user_id
                                                  WHERE mr.patient_id = ?
                                                  ORDER BY mr.created_at DESC
                                                  LIMIT 3");
                            $stmt->execute([$userId]);
                            $recentRecords = $stmt->fetchAll();
                        } catch (PDOException $e) {
                            error_log("Failed to fetch recent records: " . $e->getMessage());
                        }
                        ?>
                        
                        <?php if (!empty($recentRecords)): ?>
                            <div class="records-list">
                                <?php foreach ($recentRecords as $record): ?>
                                    <div class="record-item">
                                        <div class="record-type">
                                            <i class="fas fa-<?php 
                                                switch($record['record_type']) {
                                                    case 'prescription': echo 'prescription-bottle-alt'; break;
                                                    case 'lab_result': echo 'flask'; break;
                                                    case 'diagnosis': echo 'diagnoses'; break;
                                                    default: echo 'file-medical';
                                                }
                                            ?>"></i>
                                        </div>
                                        <div class="record-details">
                                            <div class="record-title"><?php echo htmlspecialchars($record['title']); ?></div>
                                            <div class="record-meta">
                                                <span class="record-date"><?php echo formatDate($record['created_at']); ?></span>
                                                <span class="record-doctor">Dr. <?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></span>
                                            </div>
                                        </div>
                                        <div class="record-actions">
                                            <a href="<?php echo $record['file_path'] ? BASE_URL . '/' . $record['file_path'] : '#'; ?>" 
                                               class="btn btn-icon" 
                                               target="_blank"
                                               title="View Record">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p>No recent medical records found.</p>
                        <?php endif; ?>
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
    </script>
</body>

</html>