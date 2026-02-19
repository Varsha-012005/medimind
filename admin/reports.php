<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || !hasRole('admin')) {
    redirect(BASE_URL . '/index.php');
}

$userId = getCurrentUserId();
$user = getUserById($userId);

// Get report parameters
$reportType = isset($_GET['report']) ? sanitizeInput($_GET['report']) : 'appointments';
$startDate = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date']) : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? sanitizeInput($_GET['end_date']) : date('Y-m-t');
$doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : null;
$statusFilter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

// Get all doctors for filter dropdown
$doctors = [];
try {
    $stmt = $pdo->query("SELECT u.user_id, u.first_name, u.last_name 
                         FROM users u 
                         JOIN doctors d ON u.user_id = d.user_id 
                         WHERE u.user_type = 'doctor' 
                         ORDER BY u.first_name, u.last_name");
    $doctors = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Failed to fetch doctors: " . $e->getMessage());
}

// Generate reports based on type
$reportData = [];
$chartData = [];

try {
    switch ($reportType) {
        case 'appointments':
            $query = "SELECT a.*, 
                     p.first_name AS patient_first_name, p.last_name AS patient_last_name,
                     d.first_name AS doctor_first_name, d.last_name AS doctor_last_name
                     FROM appointments a
                     JOIN users p ON a.patient_id = p.user_id
                     JOIN users d ON a.doctor_id = d.user_id
                     WHERE a.appointment_date BETWEEN ? AND ?";
            
            $params = [$startDate, $endDate];
            
            if ($doctorId) {
                $query .= " AND a.doctor_id = ?";
                $params[] = $doctorId;
            }
            
            if ($statusFilter) {
                $query .= " AND a.status = ?";
                $params[] = $statusFilter;
            }
            
            $query .= " ORDER BY a.appointment_date, a.start_time";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll();
            
            // Prepare chart data for appointments by status
            $stmt = $pdo->prepare("SELECT status, COUNT(*) as count 
                                  FROM appointments 
                                  WHERE appointment_date BETWEEN ? AND ?
                                  GROUP BY status");
            $stmt->execute([$startDate, $endDate]);
            $statusCounts = $stmt->fetchAll();
            
            $chartData['labels'] = [];
            $chartData['data'] = [];
            foreach ($statusCounts as $row) {
                $chartData['labels'][] = ucfirst($row['status']);
                $chartData['data'][] = $row['count'];
            }
            break;
            
        case 'users':
            $query = "SELECT * FROM users WHERE created_at BETWEEN ? AND ?";
            $params = [$startDate, $endDate];
            
            if ($statusFilter === 'verified') {
                $query .= " AND is_verified = TRUE";
            } elseif ($statusFilter === 'unverified') {
                $query .= " AND is_verified = FALSE";
            }
            
            $query .= " ORDER BY created_at DESC";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll();
            
            // Prepare chart data for user growth
            $stmt = $pdo->prepare("SELECT DATE(created_at) as date, COUNT(*) as count 
                                  FROM users 
                                  WHERE created_at BETWEEN ? AND ?
                                  GROUP BY DATE(created_at) 
                                  ORDER BY date");
            $stmt->execute([$startDate, $endDate]);
            $userGrowth = $stmt->fetchAll();
            
            $chartData['labels'] = [];
            $chartData['data'] = [];
            foreach ($userGrowth as $row) {
                $chartData['labels'][] = formatDate($row['date'], 'M j');
                $chartData['data'][] = $row['count'];
            }
            break;
            
        case 'revenue':
            $query = "SELECT a.*, 
                     p.first_name AS patient_first_name, p.last_name AS patient_last_name,
                     d.first_name AS doctor_first_name, d.last_name AS doctor_last_name,
                     d.consultation_fee
                     FROM appointments a
                     JOIN users p ON a.patient_id = p.user_id
                     JOIN users d ON a.doctor_id = d.user_id
                     JOIN doctors doc ON d.user_id = doc.user_id
                     WHERE a.appointment_date BETWEEN ? AND ?
                     AND a.status = 'completed'";
            
            $params = [$startDate, $endDate];
            
            if ($doctorId) {
                $query .= " AND a.doctor_id = ?";
                $params[] = $doctorId;
            }
            
            $query .= " ORDER BY a.appointment_date";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll();
            
            // Prepare chart data for revenue by day
            $stmt = $pdo->prepare("SELECT a.appointment_date as date, SUM(d.consultation_fee) as revenue
                                   FROM appointments a
                                   JOIN users u ON a.doctor_id = u.user_id
                                   JOIN doctors d ON u.user_id = d.user_id
                                   WHERE a.appointment_date BETWEEN ? AND ?
                                   AND a.status = 'completed'
                                   GROUP BY a.appointment_date
                                   ORDER BY a.appointment_date");
            $stmt->execute([$startDate, $endDate]);
            $revenueData = $stmt->fetchAll();
            
            $chartData['labels'] = [];
            $chartData['data'] = [];
            foreach ($revenueData as $row) {
                $chartData['labels'][] = formatDate($row['date'], 'M j');
                $chartData['data'][] = $row['revenue'];
            }
            break;
            
        case 'doctor_performance':
            $query = "SELECT u.user_id, u.first_name, u.last_name, 
                     COUNT(a.appointment_id) as total_appointments,
                     SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
                     SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_appointments,
                     SUM(CASE WHEN a.status = 'no-show' THEN 1 ELSE 0 END) as no_show_appointments,
                     AVG(TIMESTAMPDIFF(MINUTE, a.start_time, a.end_time)) as avg_duration,
                     SUM(d.consultation_fee) as total_revenue
                     FROM users u
                     JOIN doctors d ON u.user_id = d.user_id
                     LEFT JOIN appointments a ON u.user_id = a.doctor_id AND a.appointment_date BETWEEN ? AND ?
                     WHERE u.user_type = 'doctor'
                     GROUP BY u.user_id, u.first_name, u.last_name
                     ORDER BY total_appointments DESC";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([$startDate, $endDate]);
            $reportData = $stmt->fetchAll();
            
            // Prepare chart data for top doctors by appointments
            $stmt = $pdo->prepare("SELECT CONCAT(u.first_name, ' ', u.last_name) as doctor_name, 
                                   COUNT(a.appointment_id) as appointments
                                   FROM users u
                                   JOIN appointments a ON u.user_id = a.doctor_id
                                   WHERE a.appointment_date BETWEEN ? AND ?
                                   AND u.user_type = 'doctor'
                                   GROUP BY u.user_id, u.first_name, u.last_name
                                   ORDER BY appointments DESC
                                   LIMIT 5");
            $stmt->execute([$startDate, $endDate]);
            $topDoctors = $stmt->fetchAll();
            
            $chartData['labels'] = [];
            $chartData['data'] = [];
            foreach ($topDoctors as $row) {
                $chartData['labels'][] = $row['doctor_name'];
                $chartData['data'][] = $row['appointments'];
            }
            break;
    }
} catch (PDOException $e) {
    error_log("Failed to generate report: " . $e->getMessage());
    $errors[] = "Failed to generate report. Please try again.";
}

// Get unread notifications
$unreadNotifications = getUnreadNotifications($userId);

// Handle CSV/JSON export
if (isset($_GET['export']) && in_array($_GET['export'], ['csv', 'json'])) {
    $exportType = $_GET['export'];
    
    if ($exportType === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="report_' . $reportType . '_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Write CSV headers based on report type
        if ($reportType === 'appointments') {
            fputcsv($output, ['Date & Time', 'Patient', 'Doctor', 'Status', 'Reason']);
            foreach ($reportData as $row) {
                fputcsv($output, [
                    formatDateTime($row['appointment_date'], $row['start_time']),
                    $row['patient_first_name'] . ' ' . $row['patient_last_name'],
                    'Dr. ' . $row['doctor_first_name'] . ' ' . $row['doctor_last_name'],
                    $row['status'],
                    $row['reason'] ?? 'N/A'
                ]);
            }
        } elseif ($reportType === 'users') {
            fputcsv($output, ['Name', 'Email', 'Type', 'Joined Date', 'Status']);
            foreach ($reportData as $row) {
                fputcsv($output, [
                    $row['first_name'] . ' ' . $row['last_name'],
                    $row['email'],
                    $row['user_type'],
                    formatDate($row['created_at']),
                    $row['is_verified'] ? 'Verified' : 'Unverified'
                ]);
            }
        } elseif ($reportType === 'revenue') {
            fputcsv($output, ['Date', 'Patient', 'Doctor', 'Fee', 'Status']);
            foreach ($reportData as $row) {
                fputcsv($output, [
                    formatDate($row['appointment_date']),
                    $row['patient_first_name'] . ' ' . $row['patient_last_name'],
                    'Dr. ' . $row['doctor_first_name'] . ' ' . $row['doctor_last_name'],
                    $row['consultation_fee'],
                    $row['status']
                ]);
            }
        } elseif ($reportType === 'doctor_performance') {
            fputcsv($output, ['Doctor', 'Total Appointments', 'Completed', 'Cancelled', 'No-Shows', 'Avg. Duration', 'Total Revenue']);
            foreach ($reportData as $row) {
                fputcsv($output, [
                    $row['first_name'] . ' ' . $row['last_name'],
                    $row['total_appointments'],
                    $row['completed_appointments'],
                    $row['cancelled_appointments'],
                    $row['no_show_appointments'],
                    round($row['avg_duration'] ?? 0) . ' min',
                    '$' . number_format($row['total_revenue'] ?? 0, 2)
                ]);
            }
        }
        
        fclose($output);
    } elseif ($exportType === 'json') {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="report_' . $reportType . '_' . date('Y-m-d') . '.json"');
        
        $jsonData = [];
        
        // Format data for JSON export based on report type
        if ($reportType === 'appointments') {
            foreach ($reportData as $row) {
                $jsonData[] = [
                    'date_time' => formatDateTime($row['appointment_date'], $row['start_time']),
                    'patient' => $row['patient_first_name'] . ' ' . $row['patient_last_name'],
                    'doctor' => 'Dr. ' . $row['doctor_first_name'] . ' ' . $row['doctor_last_name'],
                    'status' => $row['status'],
                    'reason' => $row['reason'] ?? 'N/A'
                ];
            }
        } elseif ($reportType === 'users') {
            foreach ($reportData as $row) {
                $jsonData[] = [
                    'name' => $row['first_name'] . ' ' . $row['last_name'],
                    'email' => $row['email'],
                    'type' => $row['user_type'],
                    'joined_date' => formatDate($row['created_at']),
                    'status' => $row['is_verified'] ? 'Verified' : 'Unverified'
                ];
            }
        } elseif ($reportType === 'revenue') {
            foreach ($reportData as $row) {
                $jsonData[] = [
                    'date' => formatDate($row['appointment_date']),
                    'patient' => $row['patient_first_name'] . ' ' . $row['patient_last_name'],
                    'doctor' => 'Dr. ' . $row['doctor_first_name'] . ' ' . $row['doctor_last_name'],
                    'fee' => $row['consultation_fee'],
                    'status' => $row['status']
                ];
            }
        } elseif ($reportType === 'doctor_performance') {
            foreach ($reportData as $row) {
                $jsonData[] = [
                    'doctor' => $row['first_name'] . ' ' . $row['last_name'],
                    'total_appointments' => $row['total_appointments'],
                    'completed' => $row['completed_appointments'],
                    'cancelled' => $row['cancelled_appointments'],
                    'no_shows' => $row['no_show_appointments'],
                    'avg_duration' => round($row['avg_duration'] ?? 0) . ' min',
                    'total_revenue' => '$' . number_format($row['total_revenue'] ?? 0, 2)
                ];
            }
        }
        
        echo json_encode($jsonData, JSON_PRETTY_PRINT);
    }
    
    exit();
}

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
    <title>MediMind - Reports</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/admin.css">
    <style>
        /* Reports Page Specific Styles */
        .report-filters {
            background-color: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid var(--gray-light);
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
        }
        
        .report-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .report-tab {
            padding: 12px 20px;
            cursor: pointer;
            font-weight: 500;
            color: var(--gray);
            position: relative;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
        }
        
        .report-tab:hover {
            color: var(--primary);
        }
        
        .report-tab.active {
            color: var(--primary);
            border-bottom: 3px solid var(--primary);
        }
        
        .report-content {
            display: none;
        }
        
        .report-content.active {
            display: block;
        }
        
        .chart-container {
            background-color: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            height: 300px;
        }
        
        .report-table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        }
        
        .report-table th {
            background-color: var(--primary);
            color: white;
            padding: 12px 15px;
            text-align: left;
        }
        
        .report-table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .report-table tr:last-child td {
            border-bottom: none;
        }
        
        .report-table tr:hover {
            background-color: var(--primary-light);
        }

        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .summary-card {
            background-color: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        }
        
        .summary-card h3 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 16px;
            color: var(--gray);
        }
        
        .summary-card .value {
            font-size: 24px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .summary-card .change {
            font-size: 14px;
            margin-top: 5px;
        }
        
        .summary-card .change.positive {
            color: var(--success);
        }
        
        .summary-card .change.negative {
            color: var(--danger);
        }
        
        .export-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .filter-row {
                flex-direction: column;
                gap: 10px;
            }
            
            .filter-group {
                min-width: 100%;
            }
            
            .report-tabs {
                flex-wrap: wrap;
            }
            
            .summary-cards {
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
                    <li><a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
                    <li class="active"><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="../includes/auth.php?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="main-header">
                <h1>Reports & Analytics</h1>
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

            <!-- Report Filters -->
            <div class="report-filters">
                <form method="GET">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="report">Report Type</label>
                            <select id="report" name="report" onchange="this.form.submit()">
                                <option value="appointments" <?php echo $reportType === 'appointments' ? 'selected' : ''; ?>>Appointments</option>
                                <option value="users" <?php echo $reportType === 'users' ? 'selected' : ''; ?>>User Activity</option>
                                <option value="revenue" <?php echo $reportType === 'revenue' ? 'selected' : ''; ?>>Revenue</option>
                                <option value="doctor_performance" <?php echo $reportType === 'doctor_performance' ? 'selected' : ''; ?>>Doctor Performance</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="start_date">Start Date</label>
                            <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
                        </div>
                        <div class="filter-group">
                            <label for="end_date">End Date</label>
                            <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
                        </div>
                        <?php if ($reportType === 'appointments' || $reportType === 'revenue'): ?>
                            <div class="filter-group">
                                <label for="doctor_id">Doctor</label>
                                <select id="doctor_id" name="doctor_id">
                                    <option value="">All Doctors</option>
                                    <?php foreach ($doctors as $doctor): ?>
                                        <option value="<?php echo $doctor['user_id']; ?>" <?php echo $doctorId === $doctor['user_id'] ? 'selected' : ''; ?>>
                                            Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <?php if ($reportType === 'appointments' || $reportType === 'users'): ?>
                            <div class="filter-group">
                                <label for="status">Status</label>
                                <select id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <?php if ($reportType === 'appointments'): ?>
                                        <option value="scheduled" <?php echo $statusFilter === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                        <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        <option value="no-show" <?php echo $statusFilter === 'no-show' ? 'selected' : ''; ?>>No-Show</option>
                                    <?php else: ?>
                                        <option value="verified" <?php echo $statusFilter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                                        <option value="unverified" <?php echo $statusFilter === 'unverified' ? 'selected' : ''; ?>>Unverified</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="filter-row">
                        <button type="submit" class="btn btn-primary">Generate Report</button>
                        <button type="button" id="export-csv" class="btn btn-outline">Export CSV</button>
                        <button type="button" id="export-json" class="btn btn-outline">Export JSON</button>
                    </div>
                </form>
            </div>

            <!-- Report Summary Cards -->
            <?php if ($reportType === 'appointments'): ?>
                <div class="summary-cards">
                    <div class="summary-card">
                        <h3>Total Appointments</h3>
                        <div class="value"><?php echo count($reportData); ?></div>
                        <div class="change positive">
                            <i class="fas fa-arrow-up"></i> 12% from last period
                        </div>
                    </div>
                    <div class="summary-card">
                        <h3>Completed</h3>
                        <div class="value">
                            <?php 
                            $completed = array_filter($reportData, function($a) { return $a['status'] === 'completed'; });
                            echo count($completed);
                            ?>
                        </div>
                        <div class="change positive">
                            <i class="fas fa-arrow-up"></i> 8% from last period
                        </div>
                    </div>
                    <div class="summary-card">
                        <h3>Cancelled</h3>
                        <div class="value">
                            <?php 
                            $cancelled = array_filter($reportData, function($a) { return $a['status'] === 'cancelled'; });
                            echo count($cancelled);
                            ?>
                        </div>
                        <div class="change negative">
                            <i class="fas fa-arrow-down"></i> 3% from last period
                        </div>
                    </div>
                </div>
            <?php elseif ($reportType === 'revenue'): ?>
                <div class="summary-cards">
                    <div class="summary-card">
                        <h3>Total Revenue</h3>
                        <div class="value">$
                            <?php 
                            $totalRevenue = array_reduce($reportData, function($carry, $item) {
                                return $carry + $item['consultation_fee'];
                            }, 0);
                            echo number_format($totalRevenue, 2);
                            ?>
                        </div>
                        <div class="change positive">
                            <i class="fas fa-arrow-up"></i> 15% from last period
                        </div>
                    </div>
                    <div class="summary-card">
                        <h3>Average Revenue per Appointment</h3>
                        <div class="value">$
                            <?php 
                            $avgRevenue = count($reportData) > 0 ? $totalRevenue / count($reportData) : 0;
                            echo number_format($avgRevenue, 2);
                            ?>
                        </div>
                        <div class="change positive">
                            <i class="fas fa-arrow-up"></i> 5% from last period
                        </div>
                    </div>
                    <div class="summary-card">
                        <h3>Busiest Day</h3>
                        <div class="value">
                            <?php 
                            $days = [];
                            foreach ($reportData as $appt) {
                                $day = date('l', strtotime($appt['appointment_date']));
                                $days[$day] = ($days[$day] ?? 0) + 1;
                            }
                            arsort($days);
                            echo count($days) > 0 ? key($days) : 'N/A';
                            ?>
                        </div>
                    </div>
                    <div class="summary-card">
                        <h3>Top Doctor</h3>
                        <div class="value">
                            <?php 
                            $doctors = [];
                            foreach ($reportData as $appt) {
                                $name = $appt['doctor_first_name'] . ' ' . $appt['doctor_last_name'];
                                $doctors[$name] = ($doctors[$name] ?? 0) + $appt['consultation_fee'];
                            }
                            arsort($doctors);
                            echo count($doctors) > 0 ? key($doctors) : 'N/A';
                            ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Chart Container -->
            <div class="chart-container">
                <canvas id="reportChart"></canvas>
            </div>

            <!-- Report Table -->
            <div class="card">
                <div class="card-body">
                    <?php if (!empty($reportData)): ?>
                        <div class="table-responsive">
                            <table class="report-table">
                                <thead>
                                    <?php if ($reportType === 'appointments'): ?>
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>Patient</th>
                                            <th>Doctor</th>
                                            <th>Status</th>
                                            <th>Reason</th>
                                        </tr>
                                    <?php elseif ($reportType === 'users'): ?>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Type</th>
                                            <th>Joined Date</th>
                                            <th>Status</th>
                                        </tr>
                                    <?php elseif ($reportType === 'revenue'): ?>
                                        <tr>
                                            <th>Date</th>
                                            <th>Patient</th>
                                            <th>Doctor</th>
                                            <th>Fee</th>
                                            <th>Status</th>
                                        </tr>
                                    <?php elseif ($reportType === 'doctor_performance'): ?>
                                        <tr>
                                            <th>Doctor</th>
                                            <th>Total Appointments</th>
                                            <th>Completed</th>
                                            <th>Cancelled</th>
                                            <th>No-Shows</th>
                                            <th>Avg. Duration</th>
                                            <th>Total Revenue</th>
                                        </tr>
                                    <?php endif; ?>
                                </thead>
                                <tbody>
                                    <?php if ($reportType === 'appointments'): ?>
                                        <?php foreach ($reportData as $appointment): ?>
                                            <tr>
                                                <td><?php echo formatDateTime($appointment['appointment_date'], $appointment['start_time']); ?></td>
                                                <td><?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?></td>
                                                <td>Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?></td>
                                                <td>
                                                    <?php echo ucfirst($appointment['status']); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($appointment['reason'] ?? 'N/A'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php elseif ($reportType === 'users'): ?>
                                        <?php foreach ($reportData as $userItem): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($userItem['first_name'] . ' ' . $userItem['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($userItem['email']); ?></td>
                                                <td><?php echo ucfirst($userItem['user_type']); ?></td>
                                                <td><?php echo formatDate($userItem['created_at']); ?></td>
                                                <td>
                                                    <?php if ($userItem['is_verified']): ?>
                                                        <span>Verified</span>
                                                    <?php else: ?>
                                                        <span>Unverified</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php elseif ($reportType === 'revenue'): ?>
                                        <?php foreach ($reportData as $appointment): ?>
                                            <tr>
                                                <td><?php echo formatDate($appointment['appointment_date']); ?></td>
                                                <td><?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?></td>
                                                <td>Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?></td>
                                                <td>$<?php echo number_format($appointment['consultation_fee'], 2); ?></td>
                                                <td>
                                                    <span>
                                                        <?php echo ucfirst($appointment['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php elseif ($reportType === 'doctor_performance'): ?>
                                        <?php foreach ($reportData as $doctor): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></td>
                                                <td><?php echo $doctor['total_appointments']; ?></td>
                                                <td><?php echo $doctor['completed_appointments']; ?></td>
                                                <td><?php echo $doctor['cancelled_appointments']; ?></td>
                                                <td><?php echo $doctor['no_show_appointments']; ?></td>
                                                <td><?php echo round($doctor['avg_duration'] ?? 0); ?> min</td>
                                                <td>$<?php echo number_format($doctor['total_revenue'] ?? 0, 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <div class="empty-state-title">No Data Available</div>
                            <div class="empty-state-text">
                                No records found for the selected criteria.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        // Initialize chart
        const ctx = document.getElementById('reportChart').getContext('2d');
        const chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chartData['labels'] ?? []); ?>,
                datasets: [{
                    label: '<?php echo ucfirst($reportType); ?>',
                    data: <?php echo json_encode($chartData['data'] ?? []); ?>,
                    backgroundColor: 'rgba(67, 97, 238, 0.7)',
                    borderColor: 'rgba(67, 97, 238, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: '<?php echo ucfirst($reportType); ?> Report',
                        font: {
                            size: 16
                        }
                    },
                    legend: {
                        display: false
                    }
                }
            }
        });

        document.getElementById('export-csv').addEventListener('click', function() {
            // Get current report parameters
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = window.location.pathname + '?' + params.toString();
        });

        document.getElementById('export-json').addEventListener('click', function() {
            // Get current report parameters
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'json');
            window.location.href = window.location.pathname + '?' + params.toString();
        });

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