<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in and is a doctor
if (!isLoggedIn() || !hasRole('doctor')) {
    redirect(BASE_URL . '/index.php');
}

$userId = getCurrentUserId();
$user = getUserById($userId);
$doctorInfo = getDoctorByUserId($userId);

// Handle appointment status changes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $appointmentId = sanitizeInput($_POST['appointment_id'], 'int');
        $status = sanitizeInput($_POST['status'], 'string');

        // Validate status
        $allowedStatuses = ['scheduled', 'completed', 'cancelled'];
        if (!in_array($status, $allowedStatuses)) {
            $errors[] = "Invalid status selected.";
        } else {
            try {
                // Start transaction
                $pdo->beginTransaction();

                // Update appointment status
                $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ? AND doctor_id = ?");
                $stmt->execute([$status, $appointmentId, $userId]);

                if ($stmt->rowCount() === 0) {
                    throw new PDOException("No appointment found or you don't have permission to update it.");
                }

                // Get patient info for notification
                $stmt = $pdo->prepare("SELECT patient_id FROM appointments WHERE appointment_id = ?");
                $stmt->execute([$appointmentId]);
                $appointment = $stmt->fetch();

                if ($appointment) {
                    $statusText = ucfirst($status);
                    sendNotification(
                        $appointment['patient_id'],
                        "Appointment {$statusText}",
                        "Your appointment with Dr. {$user['first_name']} {$user['last_name']} has been {$status}",
                        "patient/appointments.php"
                    );
                }

                logAction('update_status', 'appointments', $appointmentId);
                $pdo->commit();

                $_SESSION['success_message'] = "Appointment status updated successfully";
                redirect(BASE_URL . '/doctor/appointments.php');
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Failed to update appointment status: " . $e->getMessage());
                $errors[] = "Failed to update appointment status. Please try again.";
            }
        }
    }
}

// Get appointments with filters
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'scheduled';
$dateFrom = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : null;
$dateTo = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : null;

$appointments = [];
try {
    $query = "SELECT a.*, 
          p.first_name AS patient_first_name, p.last_name AS patient_last_name,
          p.profile_picture AS patient_profile_picture
          FROM appointments a
          JOIN users p ON a.patient_id = p.user_id
          WHERE a.doctor_id = ?";

    $params = [$userId]; // Changed from $userId to $doctorInfo['doctor_id']

    if ($status && $status !== 'all') {
        $query .= " AND a.status = ?";
        $params[] = $status;
    }

    if ($dateFrom) {
        $query .= " AND a.appointment_date >= ?";
        $params[] = $dateFrom;
    }

    if ($dateTo) {
        $query .= " AND a.appointment_date <= ?";
        $params[] = $dateTo;
    }

    $query .= " ORDER BY a.appointment_date, a.start_time";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Failed to fetch appointments: " . $e->getMessage());
    $errors[] = "Failed to load appointments. Please try again.";
}

// Get unread notifications
$unreadNotifications = getUnreadNotifications($userId);

// Mark notifications as read if requested
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    markNotificationsAsRead($_GET['mark_read']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediMind - Doctor Appointments</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/doctor.css">
    <style>
        .status-select {
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #ddd;
            background-color: white;
            color: #333;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .status-select:focus {
            outline: none;
            border-color: #4361ee;
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.2);
        }

        .status-select option {
            padding: 8px;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            text-transform: capitalize;
        }

        .status-badge.scheduled {
            background-color: #f0ad4e;
            color: white;
        }

        .status-badge.completed {
            background-color: #5cb85c;
            color: white;
        }

        .status-badge.cancelled {
            background-color: #d9534f;
            color: white;
        }

        .appointment-item {
            transition: all 0.3s ease;
        }

        .appointment-item.scheduled {
            border-left: 4px solid #f0ad4e;
        }

        .appointment-item.completed {
            border-left: 4px solid #5cb85c;
        }

        .appointment-item.cancelled {
            border-left: 4px solid #d9534f;
        }
    </style>
</head>

<body>
    <div class="doctor-dashboard">
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
                    <div class="user-name">Dr.
                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                    </div>
                    <div class="user-role"><?php echo htmlspecialchars($doctorInfo['specialization'] ?? 'Doctor'); ?>
                    </div>
                </div>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li class="active"><a href="appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a>
                    </li>
                    <li><a href="patients.php"><i class="fas fa-users"></i> Patients</a></li>
                    <li><a href="chat.php"><i class="fas fa-comments"></i> Messages</a></li>
                    <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                    <li><a href="../includes/auth.php?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="main-header">
                <h1>Appointments</h1>
                <div class="header-actions">
                    <div class="notifications">
                        <i class="fas fa-bell"></i>
                        <?php if (!empty($unreadNotifications)): ?>
                            <span class="badge"><?php echo count($unreadNotifications); ?></span>
                        <?php endif; ?>
                        <div class="notifications-dropdown">
                            <?php if (!empty($unreadNotifications)): ?>
                                <?php foreach ($unreadNotifications as $notification): ?>
                                    <a href="<?php echo !empty($notification['link']) ? $notification['link'] : '#'; ?>"
                                        class="notification-item">
                                        <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?>
                                        </div>
                                        <div class="notification-message">
                                            <?php echo htmlspecialchars($notification['message']); ?>
                                        </div>
                                        <div class="notification-time">
                                            <?php echo formatDate($notification['created_at'], 'M j, g:i a'); ?>
                                        </div>
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

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                    <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Appointment Filters -->
            <div class="card">
                <div class="card-header">
                    <h2>Filters</h2>
                </div>
                <div class="card-body">
                    <form id="appointment-filters" method="GET" class="form-row">
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" class="form-control">
                                <option value="scheduled" <?php echo $status === 'scheduled' ? 'selected' : ''; ?>>
                                    Scheduled</option>
                                <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>
                                    Completed</option>
                                <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>
                                    Cancelled</option>
                                <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="date_from">From Date</label>
                            <input type="date" id="date_from" name="date_from" class="form-control"
                                value="<?php echo htmlspecialchars($dateFrom); ?>">
                        </div>
                        <div class="form-group">
                            <label for="date_to">To Date</label>
                            <input type="date" id="date_to" name="date_to" class="form-control"
                                value="<?php echo htmlspecialchars($dateTo); ?>">
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <a href="appointments.php" class="btn btn-outline">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Appointments List -->
            <div class="card">
                <div class="card-header">
                    <h2>Appointments</h2>
                </div>
                <div class="card-body">
                    <?php if (!empty($appointments)): ?>
                        <div class="appointment-list">
                            <?php foreach ($appointments as $appointment): ?>
                                <div class="appointment-item <?php echo $appointment['status']; ?>">
                                    <div class="appointment-info">
                                        <div class="appointment-date">
                                            <div class="date"><?php echo formatDate($appointment['appointment_date'], 'M j'); ?>
                                            </div>
                                            <div class="time"><?php echo formatTime($appointment['start_time']); ?></div>
                                        </div>
                                        <div class="patient-avatar">
                                            <?php if (!empty($appointment['patient_profile_picture'])): ?>
                                                <img src="<?php echo BASE_URL . '/' . $appointment['patient_profile_picture']; ?>"
                                                    alt="Patient Avatar">
                                            <?php else: ?>
                                                <i class="fas fa-user-circle"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="appointment-details">
                                            <div class="patient-name">
                                                <?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?>
                                            </div>
                                            <div class="appointment-reason">
                                                <?php echo htmlspecialchars($appointment['reason'] ?: 'No reason provided'); ?>
                                            </div>
                                            <div class="appointment-status">
                                                <span class="status-badge <?php echo $appointment['status']; ?>">
                                                    <?php echo ucfirst($appointment['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="appointment-actions">
                                        <form method="POST" class="status-form">
                                            <input type="hidden" name="appointment_id"
                                                value="<?php echo $appointment['appointment_id']; ?>">
                                            <select name="status" class="status-select" onchange="this.form.submit()">
                                                <option value="scheduled" <?php echo $appointment['status'] === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                                <option value="completed" <?php echo $appointment['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="cancelled" <?php echo $appointment['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                            <input type="hidden" name="update_status" value="1">
                                        </form>
                                        <a href="chat.php?patient_id=<?php echo $appointment['patient_id']; ?>"
                                            class="btn btn-icon" title="Message Patient">
                                            <i class="fas fa-comment"></i>
                                        </a>
                                        <?php if ($appointment['status'] === 'completed'): ?>
                                            <a href="patients.php?patient_id=<?php echo $appointment['patient_id']; ?>"
                                                class="btn btn-icon" title="View Patient Records">
                                                <i class="fas fa-file-medical"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>No appointments found matching your criteria.</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Toggle notifications dropdown
        document.querySelector('.notifications').addEventListener('click', function (e) {
            e.stopPropagation();
            this.querySelector('.notifications-dropdown').classList.toggle('show');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function () {
            document.querySelectorAll('.notifications-dropdown').forEach(dropdown => {
                dropdown.classList.remove('show');
            });
        });

        // Prevent dropdown from closing when clicking inside
        document.querySelectorAll('.notifications-dropdown').forEach(dropdown => {
            dropdown.addEventListener('click', function (e) {
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