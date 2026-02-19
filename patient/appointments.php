<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in and is a patient
if (!isLoggedIn() || !hasRole('patient')) {
    redirect(BASE_URL . '/index.php');
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userId = getCurrentUserId();
$user = getUserById($userId);

// Handle appointment cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment'])) {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed");
    }

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

        // Set success message
        $_SESSION['success_message'] = "Appointment cancelled successfully";
        redirect(BASE_URL . '/patient/appointments.php');
    } catch (PDOException $e) {
        error_log("Failed to cancel appointment: " . $e->getMessage());
        $errors[] = "Failed to cancel appointment. Please try again.";
    }
}

// Handle new appointment creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['doctor_id'])) {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed");
    }

    $doctorId = sanitizeInput($_POST['doctor_id'], 'int');
    $appointmentDate = sanitizeInput($_POST['appointment_date']);
    $startTime = sanitizeInput($_POST['start_time']);
    $reason = sanitizeInput($_POST['reason']);
    $notes = sanitizeInput($_POST['notes'] ?? '');

    try {
        // Check if doctor is available at this time
        $stmt = $pdo->prepare("SELECT * FROM appointments 
                              WHERE doctor_id = ? 
                              AND appointment_date = ? 
                              AND start_time = ? 
                              AND status != 'cancelled'");
        $stmt->execute([$doctorId, $appointmentDate, $startTime]);

        if ($stmt->fetch()) {
            $errors[] = "Doctor is not available at this time. Please choose another time.";
        } else {
            $duration = 30; // Default 30 minutes, or fetch from system_settings
            $endTime = date('H:i:s', strtotime($startTime) + ($duration * 60));

            // Then modify the INSERT to include end_time:
            $stmt = $pdo->prepare("INSERT INTO appointments 
                      (patient_id, doctor_id, appointment_date, start_time, end_time, reason, notes, status, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', NOW())");
            $stmt->execute([$userId, $doctorId, $appointmentDate, $startTime, $endTime, $reason, $notes]);

            // Get the new appointment ID
            $appointmentId = $pdo->lastInsertId();

            // Notify the doctor
            sendNotification(
                $doctorId,
                'New Appointment',
                "You have a new appointment with {$user['first_name']} {$user['last_name']} on $appointmentDate at $startTime",
                "doctor/appointments.php"
            );

            // Log the action
            logAction('create', 'appointments', $appointmentId);

            $_SESSION['success_message'] = "Appointment scheduled successfully!";
            redirect(BASE_URL . '/patient/appointments.php');
        }
    } catch (PDOException $e) {
        error_log("Failed to schedule appointment: " . $e->getMessage());
        $errors[] = "Failed to schedule appointment. Please try again.";
    }
}

// Get appointments with filters
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'scheduled';
$dateFrom = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : null;
$dateTo = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : null;

$appointments = [];
try {
    $query = "SELECT a.*, 
              d.first_name AS doctor_first_name, d.last_name AS doctor_last_name,
              doc.specialization
              FROM appointments a
              JOIN users d ON a.doctor_id = d.user_id
              JOIN doctors doc ON d.user_id = doc.user_id
              WHERE a.patient_id = ?";

    $params = [$userId];

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

// Get available doctors for new appointment
$doctors = [];
try {
    $stmt = $pdo->prepare("SELECT u.user_id, u.first_name, u.last_name, d.specialization 
                          FROM users u
                          JOIN doctors d ON u.user_id = d.user_id
                          WHERE d.is_approved = TRUE
                          ORDER BY u.last_name, u.first_name");
    $stmt->execute();
    $doctors = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Failed to fetch doctors: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediMind - My Appointments</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/patient.css">
    <style>
        .fade-in {
            opacity: 0;
            animation: fadeIn 0.5s forwards;
        }

        @keyframes fadeIn {
            to {
                opacity: 1;
            }
        }

        .fade-out {
            animation: fadeOut 0.5s forwards;
        }

        @keyframes fadeOut {
            to {
                opacity: 0;
            }
        }

        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-badge.scheduled {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .status-badge.completed {
            background-color: #e8f5e9;
            color: #388e3c;
        }

        .status-badge.cancelled {
            background-color: #ffebee;
            color: #d32f2f;
        }
    </style>
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
                    <div class="user-name">
                        <?php echo htmlspecialchars($user['first_name'] . ' ' . htmlspecialchars($user['last_name'])); ?>
                    </div>
                    <div class="user-role">Patient</div>
                </div>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li class="active"><a href="appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a>
                    </li>
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
                <h1>My Appointments</h1>
                <div class="header-actions">
                    <button id="new-appointment-btn" class="btn btn-outline">
                        <i class="fas fa-plus"></i> New Appointment
                    </button>
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
            <div class="card fade-in">
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
            <div class="card fade-in">
                <div class="card-header">
                    <h2>Appointments</h2>
                </div>
                <div class="card-body">
                    <?php if (!empty($appointments)): ?>
                        <div class="appointment-list">
                            <?php foreach ($appointments as $appointment): ?>
                                <div class="appointment-item">
                                    <div class="appointment-info">
                                        <div class="appointment-date">
                                            <div class="date"><?php echo formatDate($appointment['appointment_date'], 'M j'); ?>
                                            </div>
                                            <div class="time"><?php echo formatTime($appointment['start_time']); ?></div>
                                        </div>
                                        <div class="appointment-details">
                                            <div class="doctor-name">
                                                Dr.
                                                <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?>
                                                <span
                                                    class="specialization">(<?php echo htmlspecialchars($appointment['specialization']); ?>)</span>
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
                                        <?php if ($appointment['status'] === 'scheduled'): ?>
                                            <a href="chat.php?doctor_id=<?php echo $appointment['doctor_id']; ?>"
                                                class="btn btn-icon" title="Message Doctor">
                                                <i class="fas fa-comment"></i>
                                            </a>
                                            <form method="POST"
                                                onsubmit="return confirm('Are you sure you want to cancel this appointment?');">
                                                <input type="hidden" name="appointment_id"
                                                    value="<?php echo $appointment['appointment_id']; ?>">
                                                <input type="hidden" name="csrf_token"
                                                    value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <button type="submit" name="cancel_appointment" class="btn btn-icon btn-danger"
                                                    title="Cancel Appointment">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($appointment['status'] === 'completed'): ?>
                                            <a href="medical_records.php?appointment_id=<?php echo $appointment['appointment_id']; ?>"
                                                class="btn btn-icon" title="View Records">
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

    <!-- New Appointment Modal -->
    <div id="new-appointment-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Schedule New Appointment</h2>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="new-appointment-form" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="form-group">
                        <label for="doctor_id">Doctor</label>
                        <select id="doctor_id" name="doctor_id" required class="form-control">
                            <option value="">Select a Doctor</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['user_id']; ?>">
                                    Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                    - <?php echo htmlspecialchars($doctor['specialization']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="appointment_date">Date</label>
                        <input type="date" id="appointment_date" name="appointment_date" required class="form-control"
                            min="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="start_time">Time</label>
                        <input type="time" id="start_time" name="start_time" required class="form-control" min="08:00"
                            max="17:00" step="1800">
                    </div>

                    <div class="form-group">
                        <label for="reason">Reason for Appointment</label>
                        <textarea id="reason" name="reason" required class="form-control" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="notes">Additional Notes (Optional)</label>
                        <textarea id="notes" name="notes" class="form-control" rows="2"></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Schedule Appointment</button>
                        <button type="button" class="btn btn-outline modal-close">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal functionality
        const modal = document.getElementById('new-appointment-modal');
        const openModalBtn = document.getElementById('new-appointment-btn');
        const closeModalBtns = document.querySelectorAll('.modal-close');

        openModalBtn.addEventListener('click', () => {
            modal.style.display = 'flex';
        });

        closeModalBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                modal.style.display = 'none';
            });
        });

        window.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });

        // New appointment form submission
        document.getElementById('new-appointment-form').addEventListener('submit', function (e) {
            e.preventDefault();

            // Client-side validation
            const doctorId = document.getElementById('doctor_id').value;
            const appointmentDate = document.getElementById('appointment_date').value;
            const startTime = document.getElementById('start_time').value;
            const reason = document.getElementById('reason').value;

            if (!doctorId || !appointmentDate || !startTime || !reason) {
                alert('Please fill all required fields');
                return;
            }

            // Submit the form
            this.submit();
        });

        // Enhanced New Appointment Form Validation
        document.getElementById('appointment_date').addEventListener('change', function () {
            const selectedDate = new Date(this.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            if (selectedDate < today) {
                alert('Please select a future date');
                this.value = '';
            }
        });

        document.getElementById('start_time').addEventListener('change', function () {
            const time = this.value;
            const [hours, minutes] = time.split(':').map(Number);

            if (hours < 8 || hours > 17 || (hours === 17 && minutes > 0)) {
                alert('Please select a time between 8:00 AM and 5:00 PM');
                this.value = '';
            }
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