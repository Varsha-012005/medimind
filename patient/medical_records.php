<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in and is a patient
if (!isLoggedIn() || !hasRole('patient')) {
    redirect(BASE_URL . '/index.php');
}

$userId = getCurrentUserId();
$user = getUserById($userId);

// Handle file upload
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_record'])) {
    $title = sanitizeInput($_POST['title']);
    $recordType = sanitizeInput($_POST['record_type']);
    $description = sanitizeInput($_POST['description']);
    
    if (empty($title)) {
        $errors[] = "Title is required";
    }
    
    if (empty($_FILES['record_file']['name'])) {
        $errors[] = "Please select a file to upload";
    } else {
        $allowedTypes = [
            'application/pdf', 
            'image/jpeg', 
            'image/png', 
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];
        
        // Ensure upload directory exists
        if (!file_exists(UPLOAD_DIR . '/medical_records')) {
            mkdir(UPLOAD_DIR . '/medical_records', 0755, true);
        }
        
        $uploadResult = uploadFile($_FILES['record_file'], UPLOAD_DIR . '/medical_records', $allowedTypes);
        
        if ($uploadResult['success']) {
            try {
                // Store only the relative path from the uploads directory
                $relativePath = 'medical_records/' . basename($uploadResult['path']);
                
                $stmt = $pdo->prepare("INSERT INTO medical_records 
                                      (patient_id, record_type, title, description, file_path, created_by) 
                                      VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $userId, 
                    $recordType, 
                    $title, 
                    $description, 
                    $relativePath, 
                    $userId
                ]);
                
                $success = "Medical record uploaded successfully!";
                
                // Log the action
                logAction('upload', 'medical_records', $pdo->lastInsertId());
                
                // Refresh the page to show the new record
                redirect(BASE_URL . '/patient/medical_records.php');
            } catch (PDOException $e) {
                error_log("Failed to save medical record: " . $e->getMessage());
                $errors[] = "Failed to save medical record. Please try again.";
                
                // Delete the uploaded file if database insertion failed
                if (file_exists($uploadResult['path'])) {
                    unlink($uploadResult['path']);
                }
            }
        } else {
            $errors[] = $uploadResult['error'] ?? "File upload failed";
        }
    }
}

// Handle record deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_record'])) {
    $recordId = sanitizeInput($_POST['record_id'], 'int');
    
    try {
        // Get the record to ensure it belongs to the current user
        $stmt = $pdo->prepare("SELECT * FROM medical_records WHERE record_id = ? AND patient_id = ?");
        $stmt->execute([$recordId, $userId]);
        $record = $stmt->fetch();
        
        if ($record) {
            // Delete the file if it exists
            if (!empty($record['file_path'])) {
                $filePath = UPLOAD_DIR . '/' . $record['file_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            
            // Delete the record from database
            $stmt = $pdo->prepare("DELETE FROM medical_records WHERE record_id = ?");
            $stmt->execute([$recordId]);
            
            // Log the action
            logAction('delete', 'medical_records', $recordId);
            
            $success = "Medical record deleted successfully!";
            
            // Refresh the page
            redirect(BASE_URL . '/patient/medical_records.php');
        } else {
            $errors[] = "Record not found or you don't have permission to delete it";
        }
    } catch (PDOException $e) {
        error_log("Failed to delete medical record: " . $e->getMessage());
        $errors[] = "Failed to delete medical record. Please try again.";
    }
}

// Get all medical records for the current user
$medicalRecords = [];
$searchQuery = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

try {
    $query = "SELECT mr.*, u.first_name, u.last_name 
              FROM medical_records mr
              JOIN users u ON mr.created_by = u.user_id
              WHERE mr.patient_id = ?";
    
    $params = [$userId];
    
    if (!empty($searchQuery)) {
        $query .= " AND (mr.title LIKE ? OR mr.description LIKE ?)";
        $searchParam = "%$searchQuery%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $query .= " ORDER BY mr.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $medicalRecords = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Failed to fetch medical records: " . $e->getMessage());
    $errors[] = "Failed to load medical records. Please try again.";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediMind - Medical Records</title>
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
                    <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
                    <li class="active"><a href="medical_records.php"><i class="fas fa-file-medical"></i> Medical Records</a></li>
                    <li><a href="chat.php"><i class="fas fa-comments"></i> Messages</a></li>
                    <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                    <li><a href="../includes/auth.php?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="main-header">
                <h1>Medical Records</h1>
                <div class="header-actions">
                    <form method="GET" class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search records..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                    </form>
                </div>
            </header>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                    <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                    <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endif; ?>

            <div class="medical-records-container">
                <!-- Upload Record Card -->
                <div class="card upload-record">
                    <div class="card-header">
                        <h2>Upload New Record</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="title">Title*</label>
                                    <input type="text" id="title" name="title" required>
                                </div>
                                <div class="form-group">
                                    <label for="record_type">Record Type*</label>
                                    <select id="record_type" name="record_type" required>
                                        <option value="prescription">Prescription</option>
                                        <option value="lab_result">Lab Result</option>
                                        <option value="diagnosis">Diagnosis</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" rows="3"></textarea>
                            </div>
                            
                            <div class="file-upload-wrapper">
                                <input type="file" id="record_file" name="record_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" required>
                                <label for="record_file" class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Choose a file or drag it here</span>
                                    <small>Accepted formats: PDF, JPG, PNG, DOC, XLS (Max 5MB)</small>
                                </label>
                                <div class="file-upload-name" id="file-name">No file chosen</div>
                            </div>
                            
                            <button type="submit" name="upload_record" class="btn btn-primary">Upload Record</button>
                        </form>
                    </div>
                </div>

                <!-- Records List -->
                <div class="card records-list">
                    <div class="card-header">
                        <h2>Your Medical Records</h2>
                        <div class="record-count"><?php echo count($medicalRecords); ?> records found</div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($medicalRecords)): ?>
                            <div class="records-grid">
                                <?php foreach ($medicalRecords as $record): ?>
                                    <div class="record-item">
                                        <div class="record-icon">
                                            <?php 
                                                switch($record['record_type']) {
                                                    case 'prescription': echo '<i class="fas fa-prescription-bottle-alt"></i>'; break;
                                                    case 'lab_result': echo '<i class="fas fa-flask"></i>'; break;
                                                    case 'diagnosis': echo '<i class="fas fa-diagnoses"></i>'; break;
                                                    default: echo '<i class="fas fa-file-medical"></i>';
                                                }
                                            ?>
                                        </div>
                                        <div class="record-details">
                                            <h3><?php echo htmlspecialchars($record['title']); ?></h3>
                                            <div class="record-meta">
                                                <span class="record-data"><?php echo ucfirst($record['record_type']); ?></span>
                                                <span class="record-date"><?php echo formatDate($record['created_at']); ?></span>
                                            </div>
                                            <?php if (!empty($record['description'])): ?>
                                                <div class="record-description">
                                                    <?php echo htmlspecialchars($record['description']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="record-uploader">
                                                Uploaded by <?php echo htmlspecialchars($record['first_name'] . ' ' . htmlspecialchars($record['last_name'])); ?>
                                            </div>
                                        </div>
                                        <div class="record-actions">
                                            <?php if (!empty($record['file_path'])): ?>
                                                <a href="<?php echo BASE_URL . '/uploads/' . $record['file_path']; ?>" 
                                                   class="btn btn-icon" 
                                                   download
                                                   title="Download">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            <?php endif; ?>
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this record?');">
                                                <input type="hidden" name="record_id" value="<?php echo $record['record_id']; ?>">
                                                <button type="submit" name="delete_record" class="btn btn-icon btn-danger" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-file-medical"></i>
                                <h3>No medical records found</h3>
                                <p>Upload your first medical record using the form above</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Show selected file name
        document.getElementById('record_file').addEventListener('change', function(e) {
            const fileName = e.target.files[0] ? e.target.files[0].name : 'No file chosen';
            document.getElementById('file-name').textContent = fileName;
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

        // Drag and drop file upload
        const fileUploadLabel = document.querySelector('.file-upload-label');
        const fileInput = document.getElementById('record_file');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            fileUploadLabel.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            fileUploadLabel.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            fileUploadLabel.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            fileUploadLabel.classList.add('highlight');
        }
        
        function unhighlight() {
            fileUploadLabel.classList.remove('highlight');
        }
        
        fileUploadLabel.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
            
            // Trigger change event
            const event = new Event('change');
            fileInput.dispatchEvent(event);
        }
    </script>
</body>

</html>