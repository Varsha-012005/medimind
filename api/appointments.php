<?php
/**
 * MediMind - Appointments API Endpoint
 * 
 * This file handles all appointment-related API requests for the MediMind platform.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if the request is valid
if (!isAjaxRequest()) {
    jsonResponse(['error' => 'Invalid request'], 400);
}

// Verify authentication
if (!isLoggedIn()) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

// Get the request method
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetRequest();
            break;
        case 'POST':
            handlePostRequest();
            break;
        case 'PUT':
            handlePutRequest();
            break;
        case 'DELETE':
            handleDeleteRequest();
            break;
        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (PDOException $e) {
    error_log("Appointments API Error: " . $e->getMessage());
    jsonResponse(['error' => 'Database error occurred'], 500);
} catch (Exception $e) {
    error_log("Appointments API Error: " . $e->getMessage());
    jsonResponse(['error' => $e->getMessage()], 400);
}

/**
 * Handle GET requests for appointments
 */
function handleGetRequest() {
    global $pdo;
    
    $userId = getCurrentUserId();
    $userType = $_SESSION['user_type'];
    
    // Get appointment ID if specified
    $appointmentId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    if ($appointmentId) {
        // Get single appointment
        $stmt = $pdo->prepare("SELECT a.*, 
                              p.first_name AS patient_first_name, p.last_name AS patient_last_name,
                              d.first_name AS doctor_first_name, d.last_name AS doctor_last_name
                              FROM appointments a
                              JOIN users p ON a.patient_id = p.user_id
                              JOIN users d ON a.doctor_id = d.user_id
                              WHERE a.appointment_id = ? AND 
                              (a.patient_id = ? OR a.doctor_id = ?)");
        $stmt->execute([$appointmentId, $userId, $userId]);
        $appointment = $stmt->fetch();
        
        if (!$appointment) {
            jsonResponse(['error' => 'Appointment not found or access denied'], 404);
        }
        
        // Get video consultation if exists
        $stmt = $pdo->prepare("SELECT * FROM video_consultations WHERE appointment_id = ?");
        $stmt->execute([$appointmentId]);
        $videoConsultation = $stmt->fetch();
        
        $appointment['video_consultation'] = $videoConsultation ?: null;
        
        jsonResponse($appointment);
    } else {
        // Get all appointments with filters
        $status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : null;
        $dateFrom = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : null;
        $dateTo = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : null;
        
        $query = "SELECT a.*, 
                 p.first_name AS patient_first_name, p.last_name AS patient_last_name,
                 d.first_name AS doctor_first_name, d.last_name AS doctor_last_name
                 FROM appointments a
                 JOIN users p ON a.patient_id = p.user_id
                 JOIN users d ON a.doctor_id = d.user_id
                 WHERE a." . ($userType === 'patient' ? 'patient_id' : 'doctor_id') . " = ?";
        
        $params = [$userId];
        
        if ($status) {
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
        
        jsonResponse($appointments);
    }
}

/**
 * Handle POST requests to create new appointments
 */
function handlePostRequest() {
    global $pdo;
    
    // Only patients can create appointments
    if (!hasRole('patient')) {
        jsonResponse(['error' => 'Only patients can create appointments'], 403);
    }
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        jsonResponse(['error' => 'Invalid CSRF token'], 403);
    }
    
    // Validate input
    $requiredFields = ['doctor_id', 'appointment_date', 'start_time', 'reason'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            jsonResponse(['error' => "Missing required field: $field"], 400);
        }
    }
    
    $doctorId = (int)$_POST['doctor_id'];
    $appointmentDate = sanitizeInput($_POST['appointment_date']);
    $startTime = sanitizeInput($_POST['start_time']);
    $reason = sanitizeInput($_POST['reason']);
    $notes = isset($_POST['notes']) ? sanitizeInput($_POST['notes']) : '';
    
    // Validate doctor exists and is approved
    $stmt = $pdo->prepare("SELECT user_id FROM doctors WHERE user_id = ? AND is_approved = TRUE");
    $stmt->execute([$doctorId]);
    if (!$stmt->fetch()) {
        jsonResponse(['error' => 'Doctor not found or not approved'], 404);
    }
    
    // Get default appointment duration from settings
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'default_appointment_duration'");
    $stmt->execute();
    $duration = (int)$stmt->fetchColumn();
    $endTime = date('H:i:s', strtotime($startTime) + ($duration * 60));
    
    // Check for conflicts
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments 
                          WHERE doctor_id = ? AND appointment_date = ? AND 
                          ((start_time <= ? AND end_time > ?) OR 
                          (start_time < ? AND end_time >= ?) OR 
                          (start_time >= ? AND end_time <= ?))");
    $stmt->execute([$doctorId, $appointmentDate, $startTime, $startTime, $endTime, $endTime, $startTime, $endTime]);
    if ($stmt->fetchColumn() > 0) {
        jsonResponse(['error' => 'The selected time slot is not available'], 409);
    }
    
    // Create appointment
    $stmt = $pdo->prepare("INSERT INTO appointments 
                          (patient_id, doctor_id, appointment_date, start_time, end_time, reason, notes) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        getCurrentUserId(),
        $doctorId,
        $appointmentDate,
        $startTime,
        $endTime,
        $reason,
        $notes
    ]);
    
    $appointmentId = $pdo->lastInsertId();
    
    // Log the action
    logAction('create', 'appointments', $appointmentId);
    
    // Send notification to doctor
    $patient = getUserById(getCurrentUserId());
    $patientName = $patient['first_name'] . ' ' . $patient['last_name'];
    $appointmentLink = BASE_URL . '/' . ($_SESSION['user_type'] === 'doctor' ? 'doctor' : 'patient') . '/appointments.php?id=' . $appointmentId;
    
    sendNotification(
        $doctorId,
        'New Appointment Scheduled',
        "Patient $patientName has scheduled an appointment for $appointmentDate at $startTime",
        $appointmentLink
    );
    
    // Return the created appointment
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE appointment_id = ?");
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch();
    
    jsonResponse($appointment, 201);
}

/**
 * Handle PUT requests to update appointments
 */
function handlePutRequest() {
    global $pdo;
    
    // Parse JSON input for PUT requests
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(['error' => 'Invalid JSON input'], 400);
    }
    
    // Validate required fields
    if (empty($input['appointment_id'])) {
        jsonResponse(['error' => 'Missing appointment ID'], 400);
    }
    
    $appointmentId = (int)$input['appointment_id'];
    $userId = getCurrentUserId();
    
    // Get the existing appointment
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE appointment_id = ?");
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch();
    
    if (!$appointment) {
        jsonResponse(['error' => 'Appointment not found'], 404);
    }
    
    // Check permissions (patient or doctor for their own appointments)
    if ($appointment['patient_id'] !== $userId && $appointment['doctor_id'] !== $userId) {
        jsonResponse(['error' => 'Unauthorized to modify this appointment'], 403);
    }
    
    // Determine what fields can be updated
    $updatableFields = [];
    $params = [];
    
    // Patients can only cancel appointments
    if (hasRole('patient')) {
        if (isset($input['status']) && $input['status'] === 'cancelled') {
            $updatableFields[] = 'status = ?';
            $params[] = 'cancelled';
        } else {
            jsonResponse(['error' => 'Patients can only cancel appointments'], 403);
        }
    }
    
    // Doctors can update status and notes
    if (hasRole('doctor')) {
        if (isset($input['status']) && in_array($input['status'], ['scheduled', 'completed', 'cancelled', 'no-show'])) {
            $updatableFields[] = 'status = ?';
            $params[] = $input['status'];
        }
        
        if (isset($input['notes'])) {
            $updatableFields[] = 'notes = ?';
            $params[] = sanitizeInput($input['notes']);
        }
    }
    
    if (empty($updatableFields)) {
        jsonResponse(['error' => 'No valid fields to update'], 400);
    }
    
    // Build and execute the update query
    $params[] = $appointmentId;
    $query = "UPDATE appointments SET " . implode(', ', $updatableFields) . ", updated_at = NOW() WHERE appointment_id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    // Log the action
    logAction('update', 'appointments', $appointmentId);
    
    // Send notification to the other party
    $otherUserId = $appointment['patient_id'] === $userId ? $appointment['doctor_id'] : $appointment['patient_id'];
    $user = getUserById($userId);
    $userName = $user['first_name'] . ' ' . $user['last_name'];
    $statusText = isset($input['status']) ? " to " . $input['status'] : "";
    $appointmentLink = BASE_URL . '/' . ($_SESSION['user_type'] === 'doctor' ? 'doctor' : 'patient') . '/appointments.php?id=' . $appointmentId;
    
    sendNotification(
        $otherUserId,
        'Appointment Updated',
        "Your appointment on {$appointment['appointment_date']} at {$appointment['start_time']} has been updated$statusText by $userName",
        $appointmentLink
    );
    
    // Return the updated appointment
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE appointment_id = ?");
    $stmt->execute([$appointmentId]);
    $updatedAppointment = $stmt->fetch();
    
    jsonResponse($updatedAppointment);
}

/**
 * Handle DELETE requests to cancel appointments
 */
function handleDeleteRequest() {
    global $pdo;
    
    // Parse JSON input for DELETE requests
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(['error' => 'Invalid JSON input'], 400);
    }
    
    // Validate required fields
    if (empty($input['appointment_id'])) {
        jsonResponse(['error' => 'Missing appointment ID'], 400);
    }
    
    $appointmentId = (int)$input['appointment_id'];
    $userId = getCurrentUserId();
    
    // Get the existing appointment
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE appointment_id = ?");
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch();
    
    if (!$appointment) {
        jsonResponse(['error' => 'Appointment not found'], 404);
    }
    
    // Check permissions (only patient or doctor can cancel)
    if ($appointment['patient_id'] !== $userId && $appointment['doctor_id'] !== $userId) {
        jsonResponse(['error' => 'Unauthorized to cancel this appointment'], 403);
    }
    
    // Check if appointment is already cancelled
    if ($appointment['status'] === 'cancelled') {
        jsonResponse(['error' => 'Appointment is already cancelled'], 400);
    }
    
    // Check cancellation notice period
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'cancellation_notice'");
    $stmt->execute();
    $cancellationNoticeHours = (int)$stmt->fetchColumn();
    
    $appointmentDateTime = strtotime($appointment['appointment_date'] . ' ' . $appointment['start_time']);
    $hoursUntilAppointment = ($appointmentDateTime - time()) / 3600;
    
    if ($hoursUntilAppointment < $cancellationNoticeHours) {
        jsonResponse(['error' => "Cancellation must be made at least $cancellationNoticeHours hours before the appointment"], 400);
    }
    
    // Update appointment status to cancelled
    $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled', updated_at = NOW() WHERE appointment_id = ?");
    $stmt->execute([$appointmentId]);
    
    // Log the action
    logAction('cancel', 'appointments', $appointmentId);
    
    // Send notification to the other party
    $otherUserId = $appointment['patient_id'] === $userId ? $appointment['doctor_id'] : $appointment['patient_id'];
    $user = getUserById($userId);
    $userName = $user['first_name'] . ' ' . $user['last_name'];
    $appointmentLink = BASE_URL . '/' . ($_SESSION['user_type'] === 'doctor' ? 'doctor' : 'patient') . '/appointments.php?id=' . $appointmentId;
    
    sendNotification(
        $otherUserId,
        'Appointment Cancelled',
        "Your appointment on {$appointment['appointment_date']} at {$appointment['start_time']} has been cancelled by $userName",
        $appointmentLink
    );
    
    jsonResponse(['message' => 'Appointment cancelled successfully']);
}