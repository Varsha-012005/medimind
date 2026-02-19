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

// Handle form submissions
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle profile update
    if (isset($_POST['update_profile'])) {
        $firstName = sanitizeInput($_POST['first_name']);
        $lastName = sanitizeInput($_POST['last_name']);
        $phone = sanitizeInput($_POST['phone']);
        $address = sanitizeInput($_POST['address']);
        $city = sanitizeInput($_POST['city']);
        $state = sanitizeInput($_POST['state']);
        $zipCode = sanitizeInput($_POST['zip_code']);
        $country = sanitizeInput($_POST['country']);
        
        try {
            $stmt = $pdo->prepare("UPDATE users SET 
                                 first_name = ?, last_name = ?, phone = ?, 
                                 address = ?, city = ?, state = ?, 
                                 zip_code = ?, country = ?
                                 WHERE user_id = ?");
            $stmt->execute([$firstName, $lastName, $phone, $address, $city, 
                           $state, $zipCode, $country, $userId]);
            
            // Update session data
            $_SESSION['first_name'] = $firstName;
            $_SESSION['last_name'] = $lastName;
            
            $success = "Profile updated successfully!";
            $user = getUserById($userId); // Refresh user data
        } catch (PDOException $e) {
            error_log("Profile update failed: " . $e->getMessage());
            $errors[] = "Failed to update profile. Please try again.";
        }
    }
    
    // Handle health profile update
    if (isset($_POST['update_health_profile'])) {
        $bloodType = sanitizeInput($_POST['blood_type']);
        $height = sanitizeInput($_POST['height'], 'float');
        $weight = sanitizeInput($_POST['weight'], 'float');
        $allergies = sanitizeInput($_POST['allergies']);
        $medications = sanitizeInput($_POST['current_medications']);
        $conditions = sanitizeInput($_POST['chronic_conditions']);
        $history = sanitizeInput($_POST['family_medical_history']);
        
        try {
            $stmt = $pdo->prepare("UPDATE patient_health_profiles SET 
                                 blood_type = ?, height = ?, weight = ?, 
                                 allergies = ?, current_medications = ?, 
                                 chronic_conditions = ?, family_medical_history = ?
                                 WHERE user_id = ?");
            $stmt->execute([$bloodType, $height, $weight, $allergies, 
                           $medications, $conditions, $history, $userId]);
            
            $success = "Health profile updated successfully!";
            $healthProfile = getPatientHealthProfile($userId); // Refresh health profile
        } catch (PDOException $e) {
            error_log("Health profile update failed: " . $e->getMessage());
            $errors[] = "Failed to update health profile. Please try again.";
        }
    }
    
    // Handle profile picture upload
    if (isset($_POST['update_profile_picture']) && !empty($_FILES['profile_picture']['name'])) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $uploadResult = uploadFile($_FILES['profile_picture'], UPLOAD_DIR . '/profile_pictures', $allowedTypes);
        
        if ($uploadResult['success']) {
            try {
                // Delete old profile picture if exists
                if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])) {
                    unlink($user['profile_picture']);
                }
                
                // Update database with new path
                $relativePath = str_replace(__DIR__ . '/../', '', $uploadResult['path']);
                $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?");
                $stmt->execute([$relativePath, $userId]);
                
                // Update session
                $_SESSION['profile_picture'] = $relativePath;
                $user = getUserById($userId); // Refresh user data
                
                $success = "Profile picture updated successfully!";
            } catch (PDOException $e) {
                error_log("Profile picture update failed: " . $e->getMessage());
                $errors[] = "Failed to update profile picture. Please try again.";
            }
        } else {
            $errors[] = $uploadResult['error'] ?? "Invalid profile picture";
        }
    }
    
    // Handle password change
    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        if (!verifyPassword($currentPassword, $user['password_hash'])) {
            $errors[] = "Current password is incorrect";
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = "New passwords do not match";
        } elseif (strlen($newPassword) < 8) {
            $errors[] = "Password must be at least 8 characters";
        } else {
            try {
                $newHash = generatePasswordHash($newPassword);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                $stmt->execute([$newHash, $userId]);
                
                $success = "Password changed successfully!";
            } catch (PDOException $e) {
                error_log("Password change failed: " . $e->getMessage());
                $errors[] = "Failed to change password. Please try again.";
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
    <title>MediMind - Patient Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/patient.css">
    <style>
        /* Additional CSS for enhanced profile page */
        .profile-tabs {
            display: flex;
            margin-bottom: 25px;
            border-bottom: 1px solid var(--gray-light);
            position: relative;
        }
        
        .profile-tabs::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            height: 3px;
            background-color: var(--primary);
            transition: all 0.3s ease;
            border-radius: 3px 3px 0 0;
        }
        
        .profile-tab {
            padding: 12px 20px;
            cursor: pointer;
            font-weight: 500;
            color: var(--gray);
            position: relative;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
        }
        
        .profile-tab:hover {
            color: var(--primary);
        }
        
        .profile-tab.active {
            color: var(--primary);
            border-bottom: 3px solid var(--primary);
        }
        
        .tab-content {
            display: none;
            animation: fadeIn 0.5s ease;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Enhanced file upload button */
        .file-upload {
            position: relative;
            margin-bottom: 15px;
        }
        
        .file-upload-input {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-upload-label {
            display: block;
            padding: 12px 20px;
            background-color: var(--primary-light);
            color: var(--primary);
            border-radius: 8px;
            text-align: center;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 2px dashed var(--primary);
            cursor: pointer;
        }
        
        .file-upload-label:hover {
            background-color: var(--primary);
            color: white;
        }
        
        .file-upload-name {
            margin-top: 10px;
            font-size: 14px;
            color: var(--gray);
            text-align: center;
        }
        
        /* Enhanced buttons */
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        
        /* Profile picture section */
        .profile-picture-section {
            text-align: center;
            padding: 20px;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        /* Form enhancements */
        .form-section {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            padding: 25px;
            margin-bottom: 25px;
        }
        
        .form-section h3 {
            margin-bottom: 20px;
            color: var(--primary);
            font-size: 18px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--gray-light);
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .profile-tabs {
                flex-direction: column;
                border-bottom: none;
            }
            
            .profile-tab {
                border-bottom: 1px solid var(--gray-light);
                border-left: 3px solid transparent;
            }
            
            .profile-tab.active {
                border-bottom: 1px solid var(--gray-light);
                border-left: 3px solid var(--primary);
            }
        }
    </style>
</head>

<body>
    <div class="patient-dashboard">
        <!-- Sidebar Navigation (Same as dashboard) -->
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
                    <li><a href="medical_records.php"><i class="fas fa-file-medical"></i> Medical Records</a></li>
                    <li><a href="chat.php"><i class="fas fa-comments"></i> Messages</a></li>
                    <li class="active"><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                    <li><a href="../includes/auth.php?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="main-header">
                <h1>Profile Settings</h1>
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

            <div class="profile-tabs">
                <div class="profile-tab active" data-tab="personal">Personal Info</div>
                <div class="profile-tab" data-tab="health">Health Profile</div>
                <div class="profile-tab" data-tab="password">Password</div>
            </div>

            <div class="profile-container">
                <!-- Profile Picture Section (Always visible) -->
                <div class="profile-picture-section">
                    <div class="avatar-large">
                        <?php if (!empty($user['profile_picture'])): ?>
                            <img src="<?php echo BASE_URL . '/' . $user['profile_picture']; ?>" alt="Profile Picture">
                        <?php else: ?>
                            <i class="fas fa-user-circle"></i>
                        <?php endif; ?>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="file-upload">
                            <input type="file" id="profile_picture" name="profile_picture" accept="image/*" class="file-upload-input">
                            <label for="profile_picture" class="file-upload-label">
                                <i class="fas fa-camera"></i> Choose New Photo
                            </label>
                            <div class="file-upload-name" id="file-name">No file chosen</div>
                        </div>
                        <button type="submit" name="update_profile_picture" class="btn btn-primary">Update Picture</button>
                    </form>
                </div>

                <!-- Personal Information Tab -->
                <div class="tab-content active" id="personal-tab">
                    <div class="form-section">
                        <h3>Personal Information</h3>
                        <form method="POST">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="first_name">First Name</label>
                                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="last_name">Last Name</label>
                                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="address">Address</label>
                                <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="city">City</label>
                                    <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="state">State/Province</label>
                                    <input type="text" id="state" name="state" value="<?php echo htmlspecialchars($user['state'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="zip_code">ZIP/Postal Code</label>
                                    <input type="text" id="zip_code" name="zip_code" value="<?php echo htmlspecialchars($user['zip_code'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="country">Country</label>
                                    <input type="text" id="country" name="country" value="<?php echo htmlspecialchars($user['country'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                        </form>
                    </div>
                </div>

                <!-- Health Profile Tab -->
                <div class="tab-content" id="health-tab">
                    <div class="form-section">
                        <h3>Health Profile</h3>
                        <form method="POST">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="blood_type">Blood Type</label>
                                    <select id="blood_type" name="blood_type">
                                        <option value="">Select Blood Type</option>
                                        <option value="A+" <?php echo ($healthProfile['blood_type'] ?? '') === 'A+' ? 'selected' : ''; ?>>A+</option>
                                        <option value="A-" <?php echo ($healthProfile['blood_type'] ?? '') === 'A-' ? 'selected' : ''; ?>>A-</option>
                                        <option value="B+" <?php echo ($healthProfile['blood_type'] ?? '') === 'B+' ? 'selected' : ''; ?>>B+</option>
                                        <option value="B-" <?php echo ($healthProfile['blood_type'] ?? '') === 'B-' ? 'selected' : ''; ?>>B-</option>
                                        <option value="AB+" <?php echo ($healthProfile['blood_type'] ?? '') === 'AB+' ? 'selected' : ''; ?>>AB+</option>
                                        <option value="AB-" <?php echo ($healthProfile['blood_type'] ?? '') === 'AB-' ? 'selected' : ''; ?>>AB-</option>
                                        <option value="O+" <?php echo ($healthProfile['blood_type'] ?? '') === 'O+' ? 'selected' : ''; ?>>O+</option>
                                        <option value="O-" <?php echo ($healthProfile['blood_type'] ?? '') === 'O-' ? 'selected' : ''; ?>>O-</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="height">Height (cm)</label>
                                    <input type="number" id="height" name="height" step="0.1" value="<?php echo htmlspecialchars($healthProfile['height'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="weight">Weight (kg)</label>
                                    <input type="number" id="weight" name="weight" step="0.1" value="<?php echo htmlspecialchars($healthProfile['weight'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="allergies">Allergies</label>
                                <textarea id="allergies" name="allergies" rows="3"><?php echo htmlspecialchars($healthProfile['allergies'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="current_medications">Current Medications</label>
                                <textarea id="current_medications" name="current_medications" rows="3"><?php echo htmlspecialchars($healthProfile['current_medications'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="chronic_conditions">Chronic Conditions</label>
                                <textarea id="chronic_conditions" name="chronic_conditions" rows="3"><?php echo htmlspecialchars($healthProfile['chronic_conditions'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="family_medical_history">Family Medical History</label>
                                <textarea id="family_medical_history" name="family_medical_history" rows="3"><?php echo htmlspecialchars($healthProfile['family_medical_history'] ?? ''); ?></textarea>
                            </div>
                            
                            <button type="submit" name="update_health_profile" class="btn btn-primary">Save Health Profile</button>
                        </form>
                    </div>
                </div>

                <!-- Change Password Tab -->
                <div class="tab-content" id="password-tab">
                    <div class="form-section">
                        <h3>Change Password</h3>
                        <form method="POST">
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <div style="position: relative;">
                                    <input type="password" id="current_password" name="current_password" required>
                                    <button type="button" class="password-toggle" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer;">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <div style="position: relative;">
                                    <input type="password" id="new_password" name="new_password" required>
                                    <button type="button" class="password-toggle" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer;">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <div style="position: relative;">
                                    <input type="password" id="confirm_password" name="confirm_password" required>
                                    <button type="button" class="password-toggle" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer;">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Tab switching functionality
        document.querySelectorAll('.profile-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs and content
                document.querySelectorAll('.profile-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                // Add active class to clicked tab
                this.classList.add('active');
                
                // Show corresponding content
                const tabId = this.getAttribute('data-tab');
                document.getElementById(`${tabId}-tab`).classList.add('active');
            });
        });
        
        // Toggle password visibility
        document.querySelectorAll('.password-toggle').forEach(toggle => {
            toggle.addEventListener('click', function() {
                const input = this.parentElement.querySelector('input');
                const icon = this.querySelector('i');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });

        // Preview profile picture before upload
        const profilePictureInput = document.getElementById('profile_picture');
        if (profilePictureInput) {
            profilePictureInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                const fileNameDisplay = document.getElementById('file-name');
                
                if (file) {
                    fileNameDisplay.textContent = file.name;
                    
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        const avatar = document.querySelector('.avatar-large img') || 
                                      document.querySelector('.avatar-large i');
                        
                        if (avatar.tagName === 'IMG') {
                            avatar.src = event.target.result;
                        } else {
                            const img = document.createElement('img');
                            img.src = event.target.result;
                            img.alt = 'Profile Picture Preview';
                            avatar.replaceWith(img);
                        }
                    };
                    reader.readAsDataURL(file);
                } else {
                    fileNameDisplay.textContent = 'No file chosen';
                }
            });
        }

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