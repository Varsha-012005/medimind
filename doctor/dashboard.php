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
$appointments = getUserAppointments($userId, 'doctor', 'scheduled');
$unreadNotifications = getUnreadNotifications($userId);

// Mark notifications as read if requested
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    markNotificationsAsRead($_GET['mark_read']);
}

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
                redirect(BASE_URL . '/doctor/dashboard.php');
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Failed to update appointment status: " . $e->getMessage());
                $errors[] = "Failed to update appointment status. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediMind - Doctor Dashboard</title>
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

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .modal.show {
            display: flex;
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background-color: white;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            transform: translateY(-20px);
            transition: transform 0.3s ease;
        }

        .modal.show .modal-content {
            transform: translateY(0);
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            color: #333;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #777;
            padding: 5px;
            line-height: 1;
        }

        .modal-close:hover {
            color: #d9534f;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #eee;
            text-align: right;
        }

        .prescription-details {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .detail-row {
            display: flex;
            align-items: flex-start;
        }

        .detail-label {
            font-weight: 600;
            color: #333;
            min-width: 120px;
        }

        .detail-value {
            flex: 1;
            color: #666;
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
                    <li class="active"><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
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

            <div class="dashboard-grid">
                <!-- Doctor Stats -->
                <div class="card doctor-stats">
                    <div class="card-header">
                        <h2>Your Statistics</h2>
                    </div>
                    <div class="card-body">
                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-icon">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div class="stat-info">
                                    <div class="stat-value">
                                        <?php
                                        try {
                                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND status = 'completed'");
                                            $stmt->execute([$userId]);
                                            echo $stmt->fetchColumn();
                                        } catch (PDOException $e) {
                                            echo "0";
                                        }
                                        ?>
                                    </div>
                                    <div class="stat-label">Completed Consultations</div>
                                </div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-icon">
                                    <i class="fas fa-user-clock"></i>
                                </div>
                                <div class="stat-info">
                                    <div class="stat-value"><?php echo count($appointments); ?></div>
                                    <div class="stat-label">Upcoming Appointments</div>
                                </div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="stat-info">
                                    <div class="stat-value">
                                        <?php
                                        try {
                                            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT patient_id) FROM appointments WHERE doctor_id = ?");
                                            $stmt->execute([$userId]);
                                            echo $stmt->fetchColumn();
                                        } catch (PDOException $e) {
                                            echo "0";
                                        }
                                        ?>
                                    </div>
                                    <div class="stat-label">Total Patients</div>
                                </div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-icon">
                                    <i class="fas fa-star"></i>
                                </div>
                                <div class="stat-info">
                                    <div class="stat-value">
                                        <?php echo $doctorInfo['years_of_experience'] ?? '0'; ?>+
                                    </div>
                                    <div class="stat-label">Years of Experience</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Appointments -->
                <div class="card appointments">
                    <div class="card-header">
                        <h2>Upcoming Appointments</h2>
                        <a href="appointments.php" class="btn btn-outline">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-error">
                                <?php foreach ($errors as $error): ?>
                                    <p><?php echo htmlspecialchars($error); ?></p>
                                <?php endforeach; ?>
                                <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($appointments)): ?>
                            <div class="appointment-list">
                                <?php foreach ($appointments as $appointment): ?>
                                    <div class="appointment-item <?php echo htmlspecialchars($appointment['status']); ?>">
                                        <div class="appointment-info">
                                            <div class="appointment-date">
                                                <div class="date">
                                                    <?php echo formatDate($appointment['appointment_date'], 'M j'); ?>
                                                </div>
                                                <div class="time"><?php echo formatTime($appointment['start_time']); ?></div>
                                            </div>
                                            <div class="appointment-details">
                                                <div class="patient-name">
                                                    <?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?>
                                                </div>
                                                <div class="appointment-reason">
                                                    <?php echo htmlspecialchars($appointment['reason'] ? substr($appointment['reason'], 0, 30) . (strlen($appointment['reason']) > 30 ? '...' : '') : 'No reason provided'); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="appointment-actions">
                                            <form method="POST" class="status-form">
                                                <input type="hidden" name="appointment_id"
                                                    value="<?php echo htmlspecialchars($appointment['appointment_id']); ?>">
                                                <select name="status" class="status-select"
                                                    data-appointment-id="<?php echo htmlspecialchars($appointment['appointment_id']); ?>">
                                                    <option value="scheduled" <?php echo $appointment['status'] === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                                    <option value="completed" <?php echo $appointment['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                    <option value="cancelled" <?php echo $appointment['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancel</option>
                                                </select>
                                                <input type="hidden" name="update_status" value="1">
                                            </form>
                                            <a href="chat.php?patient_id=<?php echo htmlspecialchars($appointment['patient_id']); ?>"
                                                class="btn btn-icon" title="Message Patient">
                                                <i class="fas fa-comment"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p>No upcoming appointments scheduled.</p>
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
                            <a href="appointments.php" class="action-item">
                                <div class="action-icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div class="action-text">Manage Appointments</div>
                            </a>
                            <a href="patients.php" class="action-item">
                                <div class="action-icon">
                                    <i class="fas fa-user-friends"></i>
                                </div>
                                <div class="action-text">View Patients</div>
                            </a>
                            <a href="chat.php" class="action-item">
                                <div class="action-icon">
                                    <i class="fas fa-comment-medical"></i>
                                </div>
                                <div class="action-text">Messages</div>
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

                <!-- Recent Prescriptions -->
                <div class="card recent-prescriptions">
                    <div class="card-header">
                        <h2>Recent Prescriptions</h2>
                        <a href="patients.php" class="btn btn-outline">View All</a>
                    </div>
                    <div class="card-body">
                        <?php
                        $recentPrescriptions = [];
                        try {
                            $stmt = $pdo->prepare("SELECT p.*, mr.title, mr.created_at, 
                                              u.first_name, u.last_name
                                              FROM prescriptions p
                                              JOIN medical_records mr ON p.record_id = mr.record_id
                                              JOIN users u ON mr.patient_id = u.user_id
                                              WHERE p.doctor_id = ?
                                              ORDER BY mr.created_at DESC
                                              LIMIT 3");
                            $stmt->execute([$userId]);
                            $recentPrescriptions = $stmt->fetchAll();
                        } catch (PDOException $e) {
                            error_log("Failed to fetch recent prescriptions: " . $e->getMessage());
                        }
                        ?>

                        <?php if (!empty($recentPrescriptions)): ?>
                            <div class="prescriptions-list">
                                <?php foreach ($recentPrescriptions as $prescription): ?>
                                    <div class="prescription-item">
                                        <div class="prescription-type">
                                            <i class="fas fa-prescription-bottle-alt"></i>
                                        </div>
                                        <div class="prescription-details">
                                            <div class="prescription-title">
                                                <?php echo htmlspecialchars($prescription['medication_name']); ?>
                                            </div>
                                            <div class="prescription-meta">
                                                <span
                                                    class="prescription-patient"><?php echo htmlspecialchars($prescription['first_name'] . ' ' . $prescription['last_name']); ?></span>
                                                <span
                                                    class="prescription-date"><?php echo formatDate($prescription['created_at']); ?></span>
                                            </div>
                                            <div class="prescription-dosage">
                                                <?php echo htmlspecialchars($prescription['dosage'] . ' - ' . $prescription['frequency'] . ' for ' . $prescription['duration']); ?>
                                            </div>
                                        </div>
                                        <a href="#" class="btn btn-icon" title="View Prescription"
                                            onclick="viewPrescription(<?php echo htmlspecialchars(json_encode($prescription), ENT_QUOTES, 'UTF-8'); ?>); return false;">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p>No recent prescriptions found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Prescription Modal -->
    <div id="prescriptionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Prescription Details</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="prescription-details">
                    <div class="detail-row">
                        <span class="detail-label">Patient:</span>
                        <span class="detail-value" id="modalPatientName"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Date:</span>
                        <span class="detail-value" id="modalDate"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Medication:</span>
                        <span class="detail-value" id="modalMedication"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Dosage:</span>
                        <span class="detail-value" id="modalDosage"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Frequency:</span>
                        <span class="detail-value" id="modalFrequency"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Duration:</span>
                        <span class="detail-value" id="modalDuration"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Instructions:</span>
                        <span class="detail-value" id="modalInstructions"></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary modal-close-btn">Close</button>
            </div>
        </div>
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

        // Style status select dropdowns
        document.addEventListener('DOMContentLoaded', function () {
            // Handle status changes with confirmation and AJAX
            document.querySelectorAll('.status-select').forEach(select => {
                select.addEventListener('change', function () {
                    const appointmentId = this.dataset.appointmentId;
                    const newStatus = this.value;
                    const appointmentItem = this.closest('.appointment-item');
                    const form = this.closest('form');

                    // Show confirmation for cancellations
                    if (newStatus === 'cancelled') {
                        if (!confirm('Are you sure you want to cancel this appointment? This will notify the patient.')) {
                            this.value = appointmentItem.classList.contains('completed') ? 'completed' : 'scheduled';
                            return false;
                        }
                    }

                    // Show confirmation for marking as completed
                    if (newStatus === 'completed') {
                        if (!confirm('Mark this appointment as completed? This will notify the patient.')) {
                            this.value = appointmentItem.classList.contains('cancelled') ? 'cancelled' : 'scheduled';
                            return false;
                        }
                    }

                    // Update UI immediately for better UX
                    appointmentItem.className = 'appointment-item ' + newStatus;

                    // Submit form via AJAX
                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams(new FormData(form))
                    })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.text();
                        })
                        .then(() => {
                            // Success - no need to do anything as we already updated UI
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            // Revert UI on error
                            this.value = appointmentItem.classList.contains('completed') ? 'completed' :
                                (appointmentItem.classList.contains('cancelled') ? 'cancelled' : 'scheduled');
                            appointmentItem.className = 'appointment-item ' + this.value;
                            alert('Failed to update appointment status. Please try again.');
                        });
                });
            });
        });

        // View Prescription Modal
        function viewPrescription(prescription) {
            const modal = document.getElementById('prescriptionModal');

            // Populate modal with prescription data
            document.getElementById('modalPatientName').textContent =
                prescription.first_name + ' ' + prescription.last_name;
            document.getElementById('modalDate').textContent =
                new Date(prescription.created_at).toLocaleDateString();
            document.getElementById('modalMedication').textContent =
                prescription.medication_name || 'Not specified';
            document.getElementById('modalDosage').textContent =
                prescription.dosage || 'Not specified';
            document.getElementById('modalFrequency').textContent =
                prescription.frequency || 'Not specified';
            document.getElementById('modalDuration').textContent =
                prescription.duration || 'Not specified';
            document.getElementById('modalInstructions').textContent =
                prescription.instructions || 'No special instructions';

            // Show modal
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        // Close modal
        function closeModal() {
            const modal = document.getElementById('prescriptionModal');
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }

        // Event listeners for closing modal
        document.querySelectorAll('.modal-close, .modal-close-btn').forEach(btn => {
            btn.addEventListener('click', closeModal);
        });

        // Close modal when clicking outside content
        document.getElementById('prescriptionModal').addEventListener('click', function (e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>

</html>