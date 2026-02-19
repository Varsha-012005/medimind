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
$userType = isset($_GET['type']) ? sanitizeInput($_GET['type']) : '';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Get total number of users
try {
    $query = "SELECT COUNT(*) as total FROM users WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $query .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    if (!empty($userType)) {
        $query .= " AND user_type = ?";
        $params[] = $userType;
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $totalUsers = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Failed to count users: " . $e->getMessage());
    $totalUsers = 0;
}

// Get users with pagination
$users = [];
try {
    $query = "SELECT u.*, 
             d.specialization AS doctor_specialization,
             d.is_approved AS doctor_approved,
             php.blood_type, php.height, php.weight
             FROM users u
             LEFT JOIN doctors d ON u.user_id = d.user_id
             LEFT JOIN patient_health_profiles php ON u.user_id = php.user_id
             WHERE 1=1";

    $params = [];

    if (!empty($search)) {
        $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    if (!empty($userType)) {
        $query .= " AND u.user_type = ?";
        $params[] = $userType;
    }

    $query .= " ORDER BY u.user_type, u.first_name, u.last_name LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Failed to fetch users: " . $e->getMessage());
    $errors[] = "Failed to load users. Please try again.";
}

// Handle user status changes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $targetUserId = (int) $_POST['user_id'];
        $action = sanitizeInput($_POST['action']);

        try {
            switch ($action) {
                case 'verify':
                    $stmt = $pdo->prepare("UPDATE users SET is_verified = TRUE WHERE user_id = ?");
                    $stmt->execute([$targetUserId]);
                    $_SESSION['success_message'] = "User verified successfully!";
                    logAction('verify_user', 'users', $targetUserId);
                    break;

                case 'approve_doctor':
                    $stmt = $pdo->prepare("UPDATE doctors SET is_approved = TRUE WHERE user_id = ?");
                    $stmt->execute([$targetUserId]);
                    $_SESSION['success_message'] = "Doctor approved successfully!";
                    logAction('approve_doctor', 'doctors', $targetUserId);
                    break;

                case 'suspend':
                    $stmt = $pdo->prepare("UPDATE users SET is_verified = FALSE WHERE user_id = ?");
                    $stmt->execute([$targetUserId]);
                    $_SESSION['success_message'] = "User suspended successfully!";
                    logAction('suspend_user', 'users', $targetUserId);
                    break;

                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                    $stmt->execute([$targetUserId]);
                    $_SESSION['success_message'] = "User deleted successfully!";
                    logAction('delete_user', 'users', $targetUserId);
                    break;

                case 'promote':
                    $stmt = $pdo->prepare("UPDATE users SET user_type = 'admin' WHERE user_id = ?");
                    $stmt->execute([$targetUserId]);
                    $_SESSION['success_message'] = "User promoted to admin successfully!";
                    logAction('promote_user', 'users', $targetUserId);
                    break;

                case 'demote':
                    $stmt = $pdo->prepare("UPDATE users SET user_type = 'patient' WHERE user_id = ?");
                    $stmt->execute([$targetUserId]);
                    $_SESSION['success_message'] = "Admin demoted to patient successfully!";
                    logAction('demote_user', 'users', $targetUserId);
                    break;
            }

            redirect(BASE_URL . '/admin/users.php');
        } catch (PDOException $e) {
            error_log("Failed to update user status: " . $e->getMessage());
            $errors[] = "Failed to update user status. Please try again.";
        }
    }
    
    // Handle user details update
    if (isset($_POST['update_user'])) {
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
            'date_of_birth' => sanitizeInput($_POST['date_of_birth']),
            'user_type' => sanitizeInput($_POST['user_type']),
            'is_verified' => isset($_POST['is_verified']) ? 1 : 0,
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
                date_of_birth = :date_of_birth,
                user_type = :user_type,
                is_verified = :is_verified,
                updated_at = NOW()
                WHERE user_id = :user_id");
                
            $stmt->execute($data);
            
            $_SESSION['success_message'] = "User details updated successfully!";
            logAction('user_update', 'users', $userId);
            redirect(BASE_URL . '/admin/users.php');
        } catch (PDOException $e) {
            error_log("Failed to update user details: " . $e->getMessage());
            $errors[] = "Failed to update user details. Please try again.";
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
    <title>MediMind - Admin Users</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/admin.css">
    <style>
        /* Users List Styles */
        .users-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .user-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .user-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .user-avatar {
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

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-info {
            flex: 1;
        }

        .users-info {
            flex: 1;
        }
        .user-name {
            font-weight: 600;
            margin-bottom: 5px;
            display: flex;
            flex-direction: column;
        }

        .user-email {
            font-size: 13px;
            color: var(--gray);
            font-weight: 400;
            margin-top: 3px;
        }

        .user-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 10px;
        }

        .user-detail {
            display: flex;
            align-items: center;
            font-size: 13px;
            color: var(--dark-light);
        }

        .user-detail i {
            margin-right: 5px;
            color: var(--primary);
            font-size: 14px;
        }

        .user-actions {
            display: flex;
            gap: 5px;
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

        /* User Details Styles */
        .user-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .detail-row {
            margin-bottom: 15px;
        }

        .detail-label {
            font-size: 14px;
            color: var(--dark-light);
            margin-bottom: 5px;
            display: block;
        }

        .detail-value {
            padding: 8px 12px;
            background-color: var(--bg-light);
            border-radius: 6px;
            font-size: 15px;
        }

        /* Form Styles for User Management */
        .user-form {
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
            .user-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .user-avatar {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .user-meta {
                flex-direction: column;
                gap: 8px;
            }
            
            .user-actions {
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
            
            .user-details-grid {
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
                    <li class="active"><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
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
                <h1>User Management</h1>
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

            <!-- User Search and Filters -->
            <div class="card">
                <div class="card-header">
                    <h2>Search Users</h2>
                </div>
                <div class="card-body">
                    <form method="GET" class="form-row">
                        <div class="form-group">
                            <input type="text" name="search" placeholder="Search by name or email..." class="form-control" 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="form-group">
                            <select name="type" class="form-control">
                                <option value="">All Users</option>
                                <option value="patient" <?php echo $userType === 'patient' ? 'selected' : ''; ?>>Patients</option>
                                <option value="doctor" <?php echo $userType === 'doctor' ? 'selected' : ''; ?>>Doctors</option>
                                <option value="admin" <?php echo $userType === 'admin' ? 'selected' : ''; ?>>Admins</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Search</button>
                            <a href="users.php" class="btn btn-outline">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users List -->
            <div class="card">
                <div class="card-header">
                    <h2>User List</h2>
                    <div class="card-header-actions">
                        <span class="total-count"><?php echo $totalUsers; ?> users found</span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($users)): ?>
                        <div class="users-list">
                            <?php foreach ($users as $userItem): ?>
                                <div class="user-item">
                                    <div class="user-avatar">
                                        <?php if (!empty($userItem['profile_picture'])): ?>
                                            <img src="<?php echo BASE_URL . '/' . $userItem['profile_picture']; ?>" alt="User Avatar">
                                        <?php else: ?>
                                            <i class="fas fa-user-circle"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="users-info">
                                        <div class="user-name">
                                            <?php echo htmlspecialchars($userItem['first_name'] . ' ' . $userItem['last_name']); ?>
                                            <span class="user-email"><?php echo htmlspecialchars($userItem['email']); ?></span>
                                        </div>
                                        <div class="user-meta">
                                            <div class="user-detail">
                                                <i class="fas fa-user-tag"></i>
                                                <span class="<?php echo $userItem['user_type']; ?>">
                                                    <?php echo ucfirst($userItem['user_type']); ?>
                                                    <?php if ($userItem['user_type'] === 'doctor' && isset($userItem['doctor_specialization'])): ?>
                                                        (<?php echo htmlspecialchars($userItem['doctor_specialization']); ?>)
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <div class="user-detail">
                                                <i class="fas fa-calendar-alt"></i>
                                                <span>Joined <?php echo formatDate($userItem['created_at']); ?></span>
                                            </div>
                                            <div class="user-detail">
                                                <i class="fas fa-check-circle"></i>
                                                <span<?php echo $userItem['is_verified'] ? 'verified' : 'unverified'; ?>">
                                                    <?php echo $userItem['is_verified'] ? 'Verified' : 'Not Verified'; ?>
                                                </span>
                                            </div>
                                            <?php if ($userItem['user_type'] === 'doctor'): ?>
                                                <div class="user-detail">
                                                    <i class="fas fa-certificate"></i>
                                                    <span<?php echo $userItem['doctor_approved'] ? 'approved' : 'pending'; ?>">
                                                        <?php echo $userItem['doctor_approved'] ? 'Approved' : 'Pending Approval'; ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="user-actions">
                                        <?php if ($userItem['user_type'] === 'patient' && !$userItem['is_verified']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $userItem['user_id']; ?>">
                                                <input type="hidden" name="action" value="verify">
                                                <button type="submit" class="btn btn-icon" title="Verify User">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if ($userItem['user_type'] === 'doctor' && !$userItem['doctor_approved']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $userItem['user_id']; ?>">
                                                <input type="hidden" name="action" value="approve_doctor">
                                                <button type="submit" class="btn btn-icon" title="Approve Doctor">
                                                    <i class="fas fa-check-double"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <button onclick="openUserModal(<?php echo htmlspecialchars(json_encode($userItem)); ?>, 'view')" class="btn btn-icon" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="openUserModal(<?php echo htmlspecialchars(json_encode($userItem)); ?>, 'edit')" class="btn btn-icon" title="Edit Profile">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        
                                        <?php if ($userItem['user_id'] != $userId): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                <input type="hidden" name="user_id" value="<?php echo $userItem['user_id']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn btn-icon text-danger" title="Delete User">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalUsers > $perPage): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($userType); ?>&page=<?php echo $page - 1; ?>" class="page-link">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </a>
                                <?php endif; ?>
                                
                                <div class="page-numbers">
                                    <?php 
                                    $totalPages = ceil($totalUsers / $perPage);
                                    $maxVisiblePages = 5;
                                    $startPage = max(1, min($page - floor($maxVisiblePages / 2), $totalPages - $maxVisiblePages + 1));
                                    $endPage = min($startPage + $maxVisiblePages - 1, $totalPages);
                                    
                                    if ($startPage > 1) {
                                        echo '<a href="?search=' . urlencode($search) . '&type=' . urlencode($userType) . '&page=1" class="page-link">1</a>';
                                        if ($startPage > 2) {
                                            echo '<span class="page-dots">...</span>';
                                        }
                                    }
                                    
                                    for ($i = $startPage; $i <= $endPage; $i++) {
                                        $active = $i == $page ? 'active' : '';
                                        echo '<a href="?search=' . urlencode($search) . '&type=' . urlencode($userType) . '&page=' . $i . '" class="page-link ' . $active . '">' . $i . '</a>';
                                    }
                                    
                                    if ($endPage < $totalPages) {
                                        if ($endPage < $totalPages - 1) {
                                            echo '<span class="page-dots">...</span>';
                                        }
                                        echo '<a href="?search=' . urlencode($search) . '&type=' . urlencode($userType) . '&page=' . $totalPages . '" class="page-link">' . $totalPages . '</a>';
                                    }
                                    ?>
                                </div>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="?search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($userType); ?>&page=<?php echo $page + 1; ?>" class="page-link">
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
                            <div class="empty-state-title">No Users Found</div>
                            <div class="empty-state-text">
                                <?php echo empty($search) ? 'There are no users in the system.' : 'No users match your search criteria.'; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- View User Modal -->
    <div id="viewUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">User Details</h3>
                <button class="modal-close" onclick="closeModal('viewUserModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="user-details-grid">
                    <div class="detail-row">
                        <div class="detail-label">Full Name</div>
                        <div class="detail-value" id="view-full-name"></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Email</div>
                        <div class="detail-value" id="view-email"></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">User Type</div>
                        <div class="detail-value" id="view-user-type"></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Verification Status</div>
                        <div class="detail-value" id="view-verified"></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Date of Birth</div>
                        <div class="detail-value" id="view-dob"></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Phone</div>
                        <div class="detail-value" id="view-phone"></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Address</div>
                        <div class="detail-value" id="view-address"></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">City</div>
                        <div class="detail-value" id="view-city"></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">State</div>
                        <div class="detail-value" id="view-state"></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Zip Code</div>
                        <div class="detail-value" id="view-zip"></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Country</div>
                        <div class="detail-value" id="view-country"></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Account Created</div>
                        <div class="detail-value" id="view-created"></div>
                    </div>
                </div>
                
                <?php /* Additional sections for doctors and medical info would go here */ ?>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('viewUserModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <form method="POST" id="editUserForm">
                <div class="modal-header">
                    <h3 class="modal-title">Edit User Details</h3>
                    <button type="button" class="modal-close" onclick="closeModal('editUserModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="update_user" value="1">
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
                            <label for="edit-user-type" class="form-label">User Type</label>
                            <select id="edit-user-type" name="user_type" class="form-control" required>
                                <option value="patient">Patient</option>
                                <option value="doctor">Doctor</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit-verified" class="form-label">Verification Status</label>
                            <select id="edit-verified" name="is_verified" class="form-control">
                                <option value="1">Verified</option>
                                <option value="0">Not Verified</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit-dob" class="form-label">Date of Birth</label>
                            <input type="date" id="edit-dob" name="date_of_birth" class="form-control">
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
                            <label for="edit-zip" class="form-label">Zip Code</label>
                            <input type="text" id="edit-zip" name="zip_code" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit-country" class="form-label">Country</label>
                            <input type="text" id="edit-country" name="country" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('editUserModal')">Cancel</button>
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
        function openUserModal(user, mode) {
            if (mode === 'view') {
                // Populate view modal
                document.getElementById('view-full-name').textContent = `${user.first_name} ${user.last_name}`;
                document.getElementById('view-email').textContent = user.email || 'N/A';
                document.getElementById('view-user-type').textContent = user.user_type ? user.user_type.charAt(0).toUpperCase() + user.user_type.slice(1) : 'N/A';
                document.getElementById('view-verified').textContent = user.is_verified ? 'Verified' : 'Not Verified';
                document.getElementById('view-dob').textContent = user.date_of_birth ? new Date(user.date_of_birth).toLocaleDateString() : 'N/A';
                document.getElementById('view-phone').textContent = user.phone || 'N/A';
                document.getElementById('view-address').textContent = user.address || 'N/A';
                document.getElementById('view-city').textContent = user.city || 'N/A';
                document.getElementById('view-state').textContent = user.state || 'N/A';
                document.getElementById('view-zip').textContent = user.zip_code || 'N/A';
                document.getElementById('view-country').textContent = user.country || 'N/A';
                document.getElementById('view-created').textContent = user.created_at ? new Date(user.created_at).toLocaleDateString() : 'N/A';
                
                // Show view modal
                document.getElementById('viewUserModal').style.display = 'block';
            } else if (mode === 'edit') {
                // Populate edit modal
                document.getElementById('edit-user-id').value = user.user_id;
                document.getElementById('edit-first-name').value = user.first_name;
                document.getElementById('edit-last-name').value = user.last_name;
                document.getElementById('edit-email').value = user.email;
                document.getElementById('edit-user-type').value = user.user_type;
                document.getElementById('edit-verified').value = user.is_verified ? '1' : '0';
                document.getElementById('edit-dob').value = user.date_of_birth || '';
                document.getElementById('edit-phone').value = user.phone || '';
                document.getElementById('edit-address').value = user.address || '';
                document.getElementById('edit-city').value = user.city || '';
                document.getElementById('edit-state').value = user.state || '';
                document.getElementById('edit-zip').value = user.zip_code || '';
                document.getElementById('edit-country').value = user.country || '';
                
                // Show edit modal
                document.getElementById('editUserModal').style.display = 'block';
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