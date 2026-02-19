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

// Get search parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Get total number of patients
try {
    $query = "SELECT COUNT(DISTINCT a.patient_id) as total 
              FROM appointments a
              JOIN users u ON a.patient_id = u.user_id
              WHERE a.doctor_id = ?";
    
    $params = [$userId];
    
    if (!empty($search)) {
        $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $totalPatients = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Failed to count patients: " . $e->getMessage());
    $totalPatients = 0;
}

// Get patients with pagination
$patients = [];
try {
    $query = "SELECT DISTINCT u.*, php.blood_type, php.height, php.weight, php.allergies, php.current_medications, php.past_operations, php.chronic_conditions, php.family_medical_history
              FROM appointments a
              JOIN users u ON a.patient_id = u.user_id
              LEFT JOIN patient_health_profiles php ON u.user_id = php.user_id
              WHERE a.doctor_id = ?";
    
    $params = [$userId];
    
    if (!empty($search)) {
        $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $query .= " ORDER BY u.first_name, u.last_name LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $patients = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Failed to fetch patients: " . $e->getMessage());
    $errors[] = "Failed to load patients. Please try again.";
}

// Handle patient health profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_patient'])) {
    $patientId = (int)$_POST['patient_id'];
    
    $data = [
        'blood_type' => isset($_POST['blood_type']) ? sanitizeInput($_POST['blood_type']) : null,
        'height' => isset($_POST['height']) ? (float)$_POST['height'] : null,
        'weight' => isset($_POST['weight']) ? (float)$_POST['weight'] : null,
        'allergies' => isset($_POST['allergies']) ? sanitizeInput($_POST['allergies']) : null,
        'current_medications' => isset($_POST['current_medications']) ? sanitizeInput($_POST['current_medications']) : null,
        'past_operations' => isset($_POST['past_operations']) ? sanitizeInput($_POST['past_operations']) : null,
        'chronic_conditions' => isset($_POST['chronic_conditions']) ? sanitizeInput($_POST['chronic_conditions']) : null,
        'family_medical_history' => isset($_POST['family_medical_history']) ? sanitizeInput($_POST['family_medical_history']) : null,
        'user_id' => $patientId
    ];
    
    try {
        // Check if health profile exists
        $stmt = $pdo->prepare("SELECT profile_id FROM patient_health_profiles WHERE user_id = ?");
        $stmt->execute([$patientId]);
        $profileExists = $stmt->fetch();
        
        if ($profileExists) {
            // Update existing profile
            $stmt = $pdo->prepare("UPDATE patient_health_profiles SET 
                blood_type = :blood_type,
                height = :height,
                weight = :weight,
                allergies = :allergies,
                current_medications = :current_medications,
                past_operations = :past_operations,
                chronic_conditions = :chronic_conditions,
                family_medical_history = :family_medical_history
                WHERE user_id = :user_id");
        } else {
            // Insert new profile
            $stmt = $pdo->prepare("INSERT INTO patient_health_profiles (
                user_id, blood_type, height, weight, allergies, 
                current_medications, past_operations, chronic_conditions, family_medical_history
            ) VALUES (
                :user_id, :blood_type, :height, :weight, :allergies, 
                :current_medications, :past_operations, :chronic_conditions, :family_medical_history
            )");
        }
        
        $stmt->execute($data);
        
        $_SESSION['success_message'] = "Patient health profile updated successfully!";
        logAction('patient_profile_update', 'patient_health_profiles', $patientId);
        redirect(BASE_URL . '/doctor/patients.php');
    } catch (PDOException $e) {
        error_log("Failed to update patient health profile: " . $e->getMessage());
        $errors[] = "Failed to update patient health profile. Please try again.";
    }
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
    <title>MediMind - Doctor Patients</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/doctor.css">
    <style>
        /* Add these styles to your doctor.css file */

        /* Patient List Styles */
        .patients-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .patient-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .patient-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .patient-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin-right: 15px;
            background-color: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--primary);
            overflow: hidden;
        }

        .patient-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .patient-info {
            flex: 1;
        }

        .patient-name {
            font-weight: 600;
            margin-bottom: 5px;
            display: flex;
            flex-direction: column;
        }

        .patient-email {
            font-size: 13px;
            color: var(--gray);
            font-weight: 400;
            margin-top: 3px;
        }

        .patient-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 10px;
        }

        .patient-detail {
            display: flex;
            align-items: center;
            font-size: 13px;
            color: var(--dark-light);
        }

        .patient-detail i {
            margin-right: 5px;
            color: var(--primary);
            font-size: 14px;
        }

        .patient-actions {
            display: flex;
            gap: 5px;
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
            z-index: 1000;
            overflow-y: auto;
            padding: 20px;
            box-sizing: border-box;
        }

        .modal-content {
            background-color: white;
            border-radius: 12px;
            max-width: 800px;
            margin: 30px auto;
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.2);
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--gray-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--gray);
            transition: color 0.2s;
        }

        .modal-close:hover {
            color: var(--dark);
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid var(--gray-light);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* Patient Details Styles */
        .patient-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .patient-details-section {
            margin-bottom: 20px;
        }

        .detail-label {
            font-weight: 500;
            color: var(--dark-light);
            margin-bottom: 5px;
            font-size: 14px;
        }

        .detail-value {
            padding: 8px 12px;
            background-color: var(--bg-light);
            border-radius: 6px;
            font-size: 15px;
        }

        /* Form Styles for Patient Management */
        .patient-form {
            background-color: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        }

        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid var(--gray-light);
            border-radius: 6px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        select.form-control {
            height: 42px;
        }

        textarea.form-control {
            min-height: 100px;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .patient-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .patient-avatar {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .patient-meta {
                flex-direction: column;
                gap: 8px;
            }
            
            .patient-actions {
                align-self: flex-end;
            }
            
            .modal-content {
                margin: 10px auto;
                width: 95%;
            }
            
            .patient-details-grid {
                grid-template-columns: 1fr;
            }
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
                    <div class="user-name">Dr. <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($doctorInfo['specialization'] ?? 'Doctor'); ?></div>
                </div>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
                    <li class="active"><a href="patients.php"><i class="fas fa-users"></i> Patients</a></li>
                    <li><a href="chat.php"><i class="fas fa-comments"></i> Messages</a></li>
                    <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                    <li><a href="../includes/auth.php?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="main-header">
                <h1>Patients</h1>
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

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                    <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Patient Search and Filters -->
            <div class="card">
                <div class="card-header">
                    <h2>Search Patients</h2>
                </div>
                <div class="card-body">
                    <form method="GET" class="form-row">
                        <div class="form-group">
                            <input type="text" name="search" placeholder="Search by name or email..." class="form-control" 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Search</button>
                            <a href="patients.php" class="btn btn-outline">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Patients List -->
            <div class="card">
                <div class="card-header">
                    <h2>Patient List</h2>
                    <div class="card-header-actions">
                        <span class="total-count"><?php echo $totalPatients; ?> patients found</span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($patients)): ?>
                        <div class="patients-list">
                            <?php foreach ($patients as $patient): ?>
                                <div class="patient-item">
                                    <div class="patient-avatar">
                                        <?php if (!empty($patient['profile_picture'])): ?>
                                            <img src="<?php echo BASE_URL . '/' . $patient['profile_picture']; ?>" alt="Patient Avatar">
                                        <?php else: ?>
                                            <i class="fas fa-user-circle"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="patient-info">
                                        <div class="patient-name">
                                            <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                            <span class="patient-email"><?php echo htmlspecialchars($patient['email']); ?></span>
                                        </div>
                                        <div class="patient-meta">
                                            <div class="patient-detail">
                                                <i class="fas fa-phone"></i>
                                                <span><?php echo htmlspecialchars($patient['phone'] ?: 'Not provided'); ?></span>
                                            </div>
                                            <div class="patient-detail">
                                                <i class="fas fa-tint"></i>
                                                <span><?php echo htmlspecialchars($patient['blood_type'] ?: 'Unknown'); ?></span>
                                            </div>
                                            <div class="patient-detail">
                                                <i class="fas fa-ruler-combined"></i>
                                                <span><?php echo htmlspecialchars($patient['height'] ? $patient['height'] . ' cm' : 'N/A'); ?></span>
                                            </div>
                                            <div class="patient-detail">
                                                <i class="fas fa-weight"></i>
                                                <span><?php echo htmlspecialchars($patient['weight'] ? $patient['weight'] . ' kg' : 'N/A'); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="patient-actions">
                                        <a href="chat.php?patient_id=<?php echo $patient['user_id']; ?>" class="btn btn-icon" title="Message">
                                            <i class="fas fa-comment"></i>
                                        </a>
                                        <a href="appointments.php?patient_id=<?php echo $patient['user_id']; ?>" class="btn btn-icon" title="View Appointments">
                                            <i class="fas fa-calendar-alt"></i>
                                        </a>
                                        <button onclick="openPatientModal(<?php echo htmlspecialchars(json_encode($patient)); ?>, 'view')" class="btn btn-icon" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="openPatientModal(<?php echo htmlspecialchars(json_encode($patient)); ?>, 'edit')" class="btn btn-icon" title="Edit Health Profile">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPatients > $perPage): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?search=<?php echo urlencode($search); ?>&page=<?php echo $page - 1; ?>" class="page-link">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </a>
                                <?php endif; ?>
                                
                                <div class="page-numbers">
                                    <?php 
                                    $totalPages = ceil($totalPatients / $perPage);
                                    $maxVisiblePages = 5;
                                    $startPage = max(1, min($page - floor($maxVisiblePages / 2), $totalPages - $maxVisiblePages + 1));
                                    $endPage = min($startPage + $maxVisiblePages - 1, $totalPages);
                                    
                                    if ($startPage > 1) {
                                        echo '<a href="?search=' . urlencode($search) . '&page=1" class="page-link">1</a>';
                                        if ($startPage > 2) {
                                            echo '<span class="page-dots">...</span>';
                                        }
                                    }
                                    
                                    for ($i = $startPage; $i <= $endPage; $i++) {
                                        $active = $i == $page ? 'active' : '';
                                        echo '<a href="?search=' . urlencode($search) . '&page=' . $i . '" class="page-link ' . $active . '">' . $i . '</a>';
                                    }
                                    
                                    if ($endPage < $totalPages) {
                                        if ($endPage < $totalPages - 1) {
                                            echo '<span class="page-dots">...</span>';
                                        }
                                        echo '<a href="?search=' . urlencode($search) . '&page=' . $totalPages . '" class="page-link">' . $totalPages . '</a>';
                                    }
                                    ?>
                                </div>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="?search=<?php echo urlencode($search); ?>&page=<?php echo $page + 1; ?>" class="page-link">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-user-slash"></i>
                            </div>
                            <div class="empty-state-title">No Patients Found</div>
                            <div class="empty-state-text">
                                <?php echo empty($search) ? 'You have no patients yet.' : 'No patients match your search criteria.'; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- View Patient Modal -->
    <div id="viewPatientModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Patient Details</h3>
                <button class="modal-close" onclick="closeModal('viewPatientModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="patient-details-grid">
                    <div class="patient-details-section">
                        <h4>Personal Information</h4>
                        <div class="detail-row">
                            <div class="detail-label">Full Name</div>
                            <div class="detail-value" id="view-full-name"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Email</div>
                            <div class="detail-value" id="view-email"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Phone</div>
                            <div class="detail-value" id="view-phone"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Date of Birth</div>
                            <div class="detail-value" id="view-dob"></div>
                        </div>
                    </div>
                    
                    <div class="patient-details-section">
                        <h4>Address</h4>
                        <div class="detail-row">
                            <div class="detail-label">Street</div>
                            <div class="detail-value" id="view-address"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">City</div>
                            <div class="detail-value" id="view-city"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">State/Zip</div>
                            <div class="detail-value" id="view-state-zip"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Country</div>
                            <div class="detail-value" id="view-country"></div>
                        </div>
                    </div>
                    
                    <div class="patient-details-section">
                        <h4>Health Information</h4>
                        <div class="detail-row">
                            <div class="detail-label">Blood Type</div>
                            <div class="detail-value" id="view-blood-type"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Height</div>
                            <div class="detail-value" id="view-height"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Weight</div>
                            <div class="detail-value" id="view-weight"></div>
                        </div>
                    </div>
                    
                    <div class="patient-details-section">
                        <h4>Medical History</h4>
                        <div class="detail-row">
                            <div class="detail-label">Allergies</div>
                            <div class="detail-value" id="view-allergies"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Current Medications</div>
                            <div class="detail-value" id="view-medications"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Past Operations</div>
                            <div class="detail-value" id="view-operations"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Chronic Conditions</div>
                            <div class="detail-value" id="view-conditions"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Family Medical History</div>
                            <div class="detail-value" id="view-family-history"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('viewPatientModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Edit Patient Health Profile Modal -->
    <div id="editPatientModal" class="modal">
        <div class="modal-content">
            <form method="POST" id="editPatientForm">
                <div class="modal-header">
                    <h3 class="modal-title">Edit Patient Health Profile</h3>
                    <button type="button" class="modal-close" onclick="closeModal('editPatientModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="update_patient" value="1">
                    <input type="hidden" name="patient_id" id="edit-patient-id">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit-blood-type" class="form-label">Blood Type</label>
                            <select id="edit-blood-type" name="blood_type" class="form-control">
                                <option value="">Select Blood Type</option>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit-height" class="form-label">Height (cm)</label>
                            <input type="number" step="0.1" id="edit-height" name="height" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit-weight" class="form-label">Weight (kg)</label>
                            <input type="number" step="0.1" id="edit-weight" name="weight" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit-allergies" class="form-label">Allergies</label>
                            <textarea id="edit-allergies" name="allergies" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit-current-medications" class="form-label">Current Medications</label>
                            <textarea id="edit-current-medications" name="current_medications" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit-past-operations" class="form-label">Past Operations</label>
                            <textarea id="edit-past-operations" name="past_operations" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit-chronic-conditions" class="form-label">Chronic Conditions</label>
                            <textarea id="edit-chronic-conditions" name="chronic_conditions" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit-family-medical-history" class="form-label">Family Medical History</label>
                            <textarea id="edit-family-medical-history" name="family_medical_history" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('editPatientModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
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

        // Modal functions
        function openPatientModal(patient, mode) {
            if (mode === 'view') {
                // Populate view modal
                document.getElementById('view-full-name').textContent = `${patient.first_name} ${patient.last_name}`;
                document.getElementById('view-email').textContent = patient.email || 'N/A';
                document.getElementById('view-phone').textContent = patient.phone || 'N/A';
                document.getElementById('view-dob').textContent = patient.date_of_birth ? new Date(patient.date_of_birth).toLocaleDateString() : 'N/A';
                document.getElementById('view-address').textContent = patient.address || 'N/A';
                document.getElementById('view-city').textContent = patient.city || 'N/A';
                document.getElementById('view-state-zip').textContent = `${patient.state || ''} ${patient.zip_code || ''}`.trim() || 'N/A';
                document.getElementById('view-country').textContent = patient.country || 'N/A';
                document.getElementById('view-blood-type').textContent = patient.blood_type || 'Unknown';
                document.getElementById('view-height').textContent = patient.height ? `${patient.height} cm` : 'N/A';
                document.getElementById('view-weight').textContent = patient.weight ? `${patient.weight} kg` : 'N/A';
                document.getElementById('view-allergies').textContent = patient.allergies || 'None reported';
                document.getElementById('view-medications').textContent = patient.current_medications || 'None reported';
                document.getElementById('view-operations').textContent = patient.past_operations || 'None reported';
                document.getElementById('view-conditions').textContent = patient.chronic_conditions || 'None reported';
                document.getElementById('view-family-history').textContent = patient.family_medical_history || 'None reported';
                
                // Show view modal
                document.getElementById('viewPatientModal').style.display = 'block';
            } else if (mode === 'edit') {
                // Populate edit modal
                document.getElementById('edit-patient-id').value = patient.user_id;
                document.getElementById('edit-blood-type').value = patient.blood_type || '';
                document.getElementById('edit-height').value = patient.height || '';
                document.getElementById('edit-weight').value = patient.weight || '';
                document.getElementById('edit-allergies').value = patient.allergies || '';
                document.getElementById('edit-current-medications').value = patient.current_medications || '';
                document.getElementById('edit-past-operations').value = patient.past_operations || '';
                document.getElementById('edit-chronic-conditions').value = patient.chronic_conditions || '';
                document.getElementById('edit-family-medical-history').value = patient.family_medical_history || '';
                
                // Show edit modal
                document.getElementById('editPatientModal').style.display = 'block';
            }
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }

        // Initial check
        checkVisibility();

        // Check on scroll
        window.addEventListener('scroll', checkVisibility);
    </script>
</body>

</html>