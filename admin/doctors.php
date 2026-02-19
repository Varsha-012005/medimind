<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || !hasRole('admin')) {
    redirect(BASE_URL . '/index.php');
}

$userId = getCurrentUserId();
$user = getUserById($userId);

// Get search parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$filter = isset($_GET['filter']) ? sanitizeInput($_GET['filter']) : 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Get total number of doctors based on filters
try {
    $query = "SELECT COUNT(*) as total 
              FROM doctors d
              JOIN users u ON d.user_id = u.user_id
              WHERE u.user_type = 'doctor'";
    
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR d.specialization LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if ($filter === 'unapproved') {
        $query .= " AND d.is_approved = FALSE";
    } elseif ($filter === 'approved') {
        $query .= " AND d.is_approved = TRUE";
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $totalDoctors = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Failed to count doctors: " . $e->getMessage());
    $totalDoctors = 0;
}

// Get doctors with pagination
$doctors = [];
try {
    $query = "SELECT u.*, d.*
              FROM doctors d
              JOIN users u ON d.user_id = u.user_id
              WHERE u.user_type = 'doctor'";
    
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR d.specialization LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if ($filter === 'unapproved') {
        $query .= " AND d.is_approved = FALSE";
    } elseif ($filter === 'approved') {
        $query .= " AND d.is_approved = TRUE";
    }
    
    $query .= " ORDER BY u.first_name, u.last_name LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $doctors = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Failed to fetch doctors: " . $e->getMessage());
    $errors[] = "Failed to load doctors. Please try again.";
}

// Handle doctor approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $doctorId = (int)$_POST['doctor_id'];
        $action = $_POST['action'];
        
        try {
            if ($action === 'approve') {
                $stmt = $pdo->prepare("UPDATE doctors SET is_approved = TRUE WHERE doctor_id = ?");
                $stmt->execute([$doctorId]);
                $_SESSION['success_message'] = "Doctor approved successfully!";
                logAction('doctor_approve', 'doctors', $doctorId);
            } elseif ($action === 'reject') {
                $stmt = $pdo->prepare("UPDATE doctors SET is_approved = FALSE WHERE doctor_id = ?");
                $stmt->execute([$doctorId]);
                $_SESSION['success_message'] = "Doctor rejected successfully!";
                logAction('doctor_reject', 'doctors', $doctorId);
            }
            
            redirect(BASE_URL . '/admin/doctors.php');
        } catch (PDOException $e) {
            error_log("Failed to update doctor status: " . $e->getMessage());
            $errors[] = "Failed to update doctor status. Please try again.";
        }
    }
    
    // Handle doctor details update
    if (isset($_POST['update_doctor'])) {
        $doctorId = (int)$_POST['doctor_id'];
        $userId = (int)$_POST['user_id'];
        
        $data = [
            'first_name' => sanitizeInput($_POST['first_name']),
            'last_name' => sanitizeInput($_POST['last_name']),
            'email' => sanitizeInput($_POST['email']),
            'phone' => sanitizeInput($_POST['phone']),
            'address' => sanitizeInput($_POST['address']),
            'city' => sanitizeInput($_POST['city']),
            'state' => sanitizeInput($_POST['state']),
            'zip_code' => sanitizeInput($_POST['zip_code']),
            'country' => sanitizeInput($_POST['country']),
            'license_number' => sanitizeInput($_POST['license_number']),
            'specialization' => sanitizeInput($_POST['specialization']),
            'qualifications' => sanitizeInput($_POST['qualifications']),
            'years_of_experience' => (int)$_POST['years_of_experience'],
            'hospital_affiliation' => sanitizeInput($_POST['hospital_affiliation']),
            'consultation_fee' => (float)$_POST['consultation_fee'],
            'available_days' => sanitizeInput($_POST['available_days']),
            'available_hours' => sanitizeInput($_POST['available_hours']),
            'doctor_id' => $doctorId,
            'user_id' => $userId
        ];
        
        try {
            // Update user table
            $stmt = $pdo->prepare("UPDATE users SET 
                first_name = :first_name,
                last_name = :last_name,
                email = :email,
                phone = :phone,
                address = :address,
                city = :city,
                state = :state,
                zip_code = :zip_code,
                country = :country,
                updated_at = NOW()
                WHERE user_id = :user_id");
            $stmt->execute([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'city' => $data['city'],
                'state' => $data['state'],
                'zip_code' => $data['zip_code'],
                'country' => $data['country'],
                'user_id' => $data['user_id']
            ]);
            
            // Update doctors table
            $stmt = $pdo->prepare("UPDATE doctors SET 
                license_number = :license_number,
                specialization = :specialization,
                qualifications = :qualifications,
                years_of_experience = :years_of_experience,
                hospital_affiliation = :hospital_affiliation,
                consultation_fee = :consultation_fee,
                available_days = :available_days,
                available_hours = :available_hours
                WHERE doctor_id = :doctor_id");
            $stmt->execute([
                'license_number' => $data['license_number'],
                'specialization' => $data['specialization'],
                'qualifications' => $data['qualifications'],
                'years_of_experience' => $data['years_of_experience'],
                'hospital_affiliation' => $data['hospital_affiliation'],
                'consultation_fee' => $data['consultation_fee'],
                'available_days' => $data['available_days'],
                'available_hours' => $data['available_hours'],
                'doctor_id' => $data['doctor_id']
            ]);
            
            $_SESSION['success_message'] = "Doctor details updated successfully!";
            logAction('doctor_update', 'doctors', $doctorId);
            redirect(BASE_URL . '/admin/doctors.php');
        } catch (PDOException $e) {
            error_log("Failed to update doctor details: " . $e->getMessage());
            $errors[] = "Failed to update doctor details. Please try again.";
        }
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
    <title>MediMind - Manage Doctors</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/admin.css">
    <style>
        /* Add these styles to your admin.css file */

/* Doctor List Styles */
.doctors-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.doctor-item {
    display: flex;
    align-items: center;
    padding: 15px;
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.doctor-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.doctor-avatar {
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

.doctor-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.doctor-info {
    flex: 1;
}

.doctor-name {
    font-weight: 600;
    margin-bottom: 5px;
    display: flex;
    flex-direction: column;
}

.doctor-email {
    font-size: 13px;
    color: var(--gray);
    font-weight: 400;
    margin-top: 3px;
}

.doctor-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-top: 10px;
}

.doctor-detail {
    display: flex;
    align-items: center;
    font-size: 13px;
    color: var(--dark-light);
}

.doctor-detail i {
    margin-right: 5px;
    color: var(--primary);
    font-size: 14px;
}

.doctor-actions {
    display: flex;
    gap: 5px;
}

/* Status Badges */
/* Add these to your existing badge styles */

.status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.status-badge.approved {
    background-color: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.status-badge.pending {
    background-color: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

/* Filter Controls */
.filter-controls {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}

.filter-tab {
    padding: 8px 16px;
    border-radius: 6px;
    background-color: white;
    color: var(--dark-light);
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    border: 1px solid var(--gray-light);
}

.filter-tab:hover {
    background-color: var(--primary-light);
    color: var(--primary);
    border-color: var(--primary);
}

.filter-tab.active {
    background-color: var(--primary);
    color: white;
    border-color: var(--primary);
}

/* Doctor Approval Buttons */
.approve-btn {
    background-color: rgba(16, 185, 129, 0.1);
    color: var(--success);
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.approve-btn:hover {
    background-color: var(--success);
    color: white;
}

.reject-btn {
    background-color: rgba(239, 68, 68, 0.1);
    color: var(--danger);
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.reject-btn:hover {
    background-color: var(--danger);
    color: white;
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

/* Doctor Details Styles */
.doctor-details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.doctor-details-section {
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

/* Form Styles for Doctor Management */
.doctor-form {
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
    width: 75%;
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

/* License Verification Section */
.license-verification {
    border-top: 1px solid var(--gray-light);
    padding-top: 20px;
    margin-top: 20px;
}

.verification-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 15px;
}

.license-document {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background-color: var(--primary-light);
    border-radius: 8px;
    margin-bottom: 15px;
}

.document-icon {
    font-size: 24px;
    color: var(--primary);
}

.document-info {
    flex: 1;
}

.document-name {
    font-weight: 500;
    margin-bottom: 5px;
}

.document-meta {
    font-size: 12px;
    color: var(--gray);
}

/* Action Buttons */
.action-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid var(--gray-light);
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .doctor-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .doctor-avatar {
        margin-right: 0;
        margin-bottom: 15px;
    }
    
    .doctor-meta {
        flex-direction: column;
        gap: 8px;
    }
    
    .doctor-actions {
        align-self: flex-end;
    }
    
    .filter-controls {
        flex-direction: column;
        gap: 8px;
    }
    
    .modal-content {
        margin: 10px auto;
        width: 95%;
    }
    
    .doctor-details-grid {
        grid-template-columns: 1fr;
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
                    <li class="active"><a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="../includes/auth.php?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="main-header">
                <h1>Manage Doctors</h1>
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

            <!-- Doctor Search and Filters -->
            <div class="card">
                <div class="card-header">
                    <h2>Search Doctors</h2>
                </div>
                <div class="card-body">
                    <form method="GET" class="form-row">
                        <div class="form-group">
                            <input type="text" name="search" placeholder="Search by name, email or specialization..." class="form-control" 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="form-group">
                            <select name="filter" class="form-control">
                                <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Doctors</option>
                                <option value="approved" <?php echo $filter === 'approved' ? 'selected' : ''; ?>>Approved Doctors</option>
                                <option value="unapproved" <?php echo $filter === 'unapproved' ? 'selected' : ''; ?>>Pending Approval</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Search</button>
                            <a href="doctors.php" class="btn btn-outline">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Doctors List -->
            <div class="card">
                <div class="card-header">
                    <h2>Doctor List</h2>
                    <div class="card-header-actions">
                        <span class="total-count"><?php echo $totalDoctors; ?> doctors found</span>
                        <a href="doctors.php?filter=unapproved" class="btn btn-sm btn-outline">View Pending</a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($doctors)): ?>
                        <div class="doctors-list">
                            <?php foreach ($doctors as $doctor): ?>
                                <div class="doctor-item">
                                    <div class="doctor-avatar">
                                        <?php if (!empty($doctor['profile_picture'])): ?>
                                            <img src="<?php echo BASE_URL . '/' . $doctor['profile_picture']; ?>" alt="Doctor Avatar">
                                        <?php else: ?>
                                            <i class="fas fa-user-md"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="doctor-info">
                                        <div class="doctor-name">
                                            Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                            <span class="doctor-email"><?php echo htmlspecialchars($doctor['email']); ?></span>
                                        </div>
                                        <div class="doctor-meta">
                                            <div class="doctor-detail">
                                                <i class="fas fa-certificate"></i>
                                                <span><?php echo htmlspecialchars($doctor['specialization']); ?></span>
                                            </div>
                                            <div class="doctor-detail">
                                                <i class="fas fa-id-card"></i>
                                                <span><?php echo htmlspecialchars($doctor['license_number']); ?></span>
                                            </div>
                                            <div class="doctor-detail">
                                                <i class="fas fa-hospital"></i>
                                                <span><?php echo htmlspecialchars($doctor['hospital_affiliation'] ?: 'N/A'); ?></span>
                                            </div>
                                            <div class="doctor-detail">
                                                <i class="fas fa-money-bill-wave"></i>
                                                <span>$<?php echo htmlspecialchars($doctor['consultation_fee']); ?> per consultation</span>
                                            </div>
                                            <div class="doctor-detail">
                                                <i class="fas fa-check-circle"></i>
                                                <span class="status-badge <?php echo $doctor['is_approved'] ? 'approved' : 'pending'; ?>">
                                                    <?php echo $doctor['is_approved'] ? 'Approved' : 'Pending Approval'; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="doctor-actions">
                                        <?php if (!$doctor['is_approved']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="doctor_id" value="<?php echo $doctor['doctor_id']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-icon" title="Approve Doctor">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="doctor_id" value="<?php echo $doctor['doctor_id']; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="btn btn-icon" title="Reject Doctor">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <button onclick="openDoctorModal(<?php echo htmlspecialchars(json_encode($doctor)); ?>, 'view')" class="btn btn-icon" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="openDoctorModal(<?php echo htmlspecialchars(json_encode($doctor)); ?>, 'edit')" class="btn btn-icon" title="Edit Profile">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalDoctors > $perPage): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>&page=<?php echo $page - 1; ?>" class="page-link">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </a>
                                <?php endif; ?>
                                
                                <div class="page-numbers">
                                    <?php 
                                    $totalPages = ceil($totalDoctors / $perPage);
                                    $maxVisiblePages = 5;
                                    $startPage = max(1, min($page - floor($maxVisiblePages / 2), $totalPages - $maxVisiblePages + 1));
                                    $endPage = min($startPage + $maxVisiblePages - 1, $totalPages);
                                    
                                    if ($startPage > 1) {
                                        echo '<a href="?search=' . urlencode($search) . '&filter=' . urlencode($filter) . '&page=1" class="page-link">1</a>';
                                        if ($startPage > 2) {
                                            echo '<span class="page-dots">...</span>';
                                        }
                                    }
                                    
                                    for ($i = $startPage; $i <= $endPage; $i++) {
                                        $active = $i == $page ? 'active' : '';
                                        echo '<a href="?search=' . urlencode($search) . '&filter=' . urlencode($filter) . '&page=' . $i . '" class="page-link ' . $active . '">' . $i . '</a>';
                                    }
                                    
                                    if ($endPage < $totalPages) {
                                        if ($endPage < $totalPages - 1) {
                                            echo '<span class="page-dots">...</span>';
                                        }
                                        echo '<a href="?search=' . urlencode($search) . '&filter=' . urlencode($filter) . '&page=' . $totalPages . '" class="page-link">' . $totalPages . '</a>';
                                    }
                                    ?>
                                </div>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="?search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>&page=<?php echo $page + 1; ?>" class="page-link">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-user-md"></i>
                            </div>
                            <div class="empty-state-title">No Doctors Found</div>
                            <div class="empty-state-text">
                                <?php echo empty($search) ? 'No doctors registered yet.' : 'No doctors match your search criteria.'; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- View Doctor Modal -->
    <div id="viewDoctorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Doctor Details</h3>
                <button class="modal-close" onclick="closeModal('viewDoctorModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="doctor-details-grid">
                    <div class="doctor-details-section">
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
                    
                    <div class="doctor-details-section">
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
                    
                    <div class="doctor-details-section">
                        <h4>Professional Information</h4>
                        <div class="detail-row">
                            <div class="detail-label">License Number</div>
                            <div class="detail-value" id="view-license"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Specialization</div>
                            <div class="detail-value" id="view-specialization"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Qualifications</div>
                            <div class="detail-value" id="view-qualifications"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Years of Experience</div>
                            <div class="detail-value" id="view-experience"></div>
                        </div>
                    </div>
                    
                    <div class="doctor-details-section">
                        <h4>Practice Details</h4>
                        <div class="detail-row">
                            <div class="detail-label">Hospital Affiliation</div>
                            <div class="detail-value" id="view-hospital"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Consultation Fee</div>
                            <div class="detail-value" id="view-fee"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Available Days</div>
                            <div class="detail-value" id="view-days"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Available Hours</div>
                            <div class="detail-value" id="view-hours"></div>
                        </div>
                    </div>
                </div>
                
                <div class="license-verification">
                    <h4 class="verification-title">License Verification</h4>
                    <div class="license-document">
                        <i class="fas fa-file-pdf document-icon"></i>
                        <div class="document-info">
                            <div class="document-name">Medical License Certificate</div>
                            <div class="document-meta">Uploaded on <span id="view-license-date"></span></div>
                        </div>
                        <a href="#" class="btn btn-sm btn-primary" id="view-license-link" target="_blank">View Document</a>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('viewDoctorModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Edit Doctor Modal -->
    <div id="editDoctorModal" class="modal">
        <div class="modal-content">
            <form method="POST" id="editDoctorForm">
                <div class="modal-header">
                    <h3 class="modal-title">Edit Doctor Details</h3>
                    <button type="button" class="modal-close" onclick="closeModal('editDoctorModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="update_doctor" value="1">
                    <input type="hidden" name="doctor_id" id="edit-doctor-id">
                    <input type="hidden" name="user_id" id="edit-user-id">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit-first-name" class="form-label">First Name</label>
                            <input type="text" id="edit-first-name" name="first_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-last-name" class="form-label">Last Name</label>
                            <input type="text" id="edit-last-name" name="last_name" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit-email" class="form-label">Email</label>
                            <input type="email" id="edit-email" name="email" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-phone" class="form-label">Phone</label>
                            <input type="text" id="edit-phone" name="phone" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit-address" class="form-label">Address</label>
                            <input type="text" id="edit-address" name="address" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit-city" class="form-label">City</label>
                            <input type="text" id="edit-city" name="city" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit-state" class="form-label">State</label>
                            <input type="text" id="edit-state" name="state" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit-zip-code" class="form-label">Zip Code</label>
                            <input type="text" id="edit-zip-code" name="zip_code" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit-country" class="form-label">Country</label>
                            <input type="text" id="edit-country" name="country" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit-license-number" class="form-label">License Number</label>
                            <input type="text" id="edit-license-number" name="license_number" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-specialization" class="form-label">Specialization</label>
                            <input type="text" id="edit-specialization" name="specialization" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit-qualifications" class="form-label">Qualifications</label>
                            <textarea id="edit-qualifications" name="qualifications" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit-years-of-experience" class="form-label">Years of Experience</label>
                            <input type="number" id="edit-years-of-experience" name="years_of_experience" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit-hospital-affiliation" class="form-label">Hospital Affiliation</label>
                            <input type="text" id="edit-hospital-affiliation" name="hospital_affiliation" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit-consultation-fee" class="form-label">Consultation Fee ($)</label>
                            <input type="number" step="0.01" id="edit-consultation-fee" name="consultation_fee" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-available-days" class="form-label">Available Days</label>
                            <input type="text" id="edit-available-days" name="available_days" class="form-control" placeholder="e.g. Mon-Fri">
                        </div>
                        <div class="form-group">
                            <label for="edit-available-hours" class="form-label">Available Hours</label>
                            <input type="text" id="edit-available-hours" name="available_hours" class="form-control" placeholder="e.g. 9am-5pm">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('editDoctorModal')">Cancel</button>
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
        function openDoctorModal(doctor, mode) {
            if (mode === 'view') {
                // Populate view modal
                document.getElementById('view-full-name').textContent = `Dr. ${doctor.first_name} ${doctor.last_name}`;
                document.getElementById('view-email').textContent = doctor.email || 'N/A';
                document.getElementById('view-phone').textContent = doctor.phone || 'N/A';
                document.getElementById('view-dob').textContent = doctor.date_of_birth ? new Date(doctor.date_of_birth).toLocaleDateString() : 'N/A';
                document.getElementById('view-address').textContent = doctor.address || 'N/A';
                document.getElementById('view-city').textContent = doctor.city || 'N/A';
                document.getElementById('view-state-zip').textContent = `${doctor.state || ''} ${doctor.zip_code || ''}`.trim() || 'N/A';
                document.getElementById('view-country').textContent = doctor.country || 'N/A';
                document.getElementById('view-license').textContent = doctor.license_number || 'N/A';
                document.getElementById('view-specialization').textContent = doctor.specialization || 'N/A';
                document.getElementById('view-qualifications').textContent = doctor.qualifications || 'N/A';
                document.getElementById('view-experience').textContent = doctor.years_of_experience ? `${doctor.years_of_experience} years` : 'N/A';
                document.getElementById('view-hospital').textContent = doctor.hospital_affiliation || 'N/A';
                document.getElementById('view-fee').textContent = doctor.consultation_fee ? `$${doctor.consultation_fee}` : 'N/A';
                document.getElementById('view-days').textContent = doctor.available_days || 'N/A';
                document.getElementById('view-hours').textContent = doctor.available_hours || 'N/A';
                document.getElementById('view-license-date').textContent = doctor.created_at ? new Date(doctor.created_at).toLocaleDateString() : 'N/A';
                
                // Show view modal
                document.getElementById('viewDoctorModal').style.display = 'block';
            } else if (mode === 'edit') {
                // Populate edit modal
                document.getElementById('edit-doctor-id').value = doctor.doctor_id;
                document.getElementById('edit-user-id').value = doctor.user_id;
                document.getElementById('edit-first-name').value = doctor.first_name;
                document.getElementById('edit-last-name').value = doctor.last_name;
                document.getElementById('edit-email').value = doctor.email;
                document.getElementById('edit-phone').value = doctor.phone || '';
                document.getElementById('edit-address').value = doctor.address || '';
                document.getElementById('edit-city').value = doctor.city || '';
                document.getElementById('edit-state').value = doctor.state || '';
                document.getElementById('edit-zip-code').value = doctor.zip_code || '';
                document.getElementById('edit-country').value = doctor.country || '';
                document.getElementById('edit-license-number').value = doctor.license_number;
                document.getElementById('edit-specialization').value = doctor.specialization;
                document.getElementById('edit-qualifications').value = doctor.qualifications || '';
                document.getElementById('edit-years-of-experience').value = doctor.years_of_experience || '';
                document.getElementById('edit-hospital-affiliation').value = doctor.hospital_affiliation || '';
                document.getElementById('edit-consultation-fee').value = doctor.consultation_fee;
                document.getElementById('edit-available-days').value = doctor.available_days || '';
                document.getElementById('edit-available-hours').value = doctor.available_hours || '';
                
                // Show edit modal
                document.getElementById('editDoctorModal').style.display = 'block';
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