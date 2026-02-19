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
    
    // Handle doctor profile update
    if (isset($_POST['update_doctor_profile'])) {
        $specialization = sanitizeInput($_POST['specialization']);
        $licenseNumber = sanitizeInput($_POST['license_number']);
        $qualifications = sanitizeInput($_POST['qualifications']);
        $yearsOfExperience = sanitizeInput($_POST['years_of_experience'], 'int');
        $hospitalAffiliation = sanitizeInput($_POST['hospital_affiliation']);
        $consultationFee = sanitizeInput($_POST['consultation_fee'], 'float');
        $availableDays = sanitizeInput($_POST['available_days']);
        $availableHours = sanitizeInput($_POST['available_hours']);
        
        try {
            // Check if doctor profile exists
            if ($doctorInfo) {
                $stmt = $pdo->prepare("UPDATE doctors SET 
                                     specialization = ?, license_number = ?, qualifications = ?, 
                                     years_of_experience = ?, hospital_affiliation = ?, 
                                     consultation_fee = ?, available_days = ?, available_hours = ?
                                     WHERE user_id = ?");
                $stmt->execute([$specialization, $licenseNumber, $qualifications, 
                               $yearsOfExperience, $hospitalAffiliation, $consultationFee, 
                               $availableDays, $availableHours, $userId]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO doctors 
                                     (user_id, specialization, license_number, qualifications, 
                                     years_of_experience, hospital_affiliation, consultation_fee, 
                                     available_days, available_hours)
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $specialization, $licenseNumber, $qualifications, 
                               $yearsOfExperience, $hospitalAffiliation, $consultationFee, 
                               $availableDays, $availableHours]);
            }
            
            $success = "Doctor profile updated successfully!";
            $doctorInfo = getDoctorByUserId($userId); // Refresh doctor info
        } catch (PDOException $e) {
            error_log("Doctor profile update failed: " . $e->getMessage());
            $errors[] = "Failed to update doctor profile. Please try again.";
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
    <title>MediMind - Doctor Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/doctor.css">
    <style>
        /* Profile Page Specific Styles */
        .profile-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .avatar-large {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background-color: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 60px;
            color: var(--primary);
            overflow: hidden;
        }

        .avatar-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-picture-form {
            text-align: center;
        }

        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--dark);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid var(--gray-light);
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 38px;
            cursor: pointer;
            color: var(--gray);
            background: none;
            border: none;
            outline: none;
            z-index: 2;
        }

        .password-toggle:hover {
            color: var(--primary);
        }

        /* Profile tabs */
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
        
        /* Profile picture section */
        .profile-picture-section {
            text-align: center;
            padding: 20px;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        /* Form sections */
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
        
        /* File upload styles */
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
            
            .profile-container {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                flex-direction: column;
                gap: 15px;
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
                    <li><a href="patients.php"><i class="fas fa-users"></i> Patients</a></li>
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
                <div class="profile-tab" data-tab="professional">Professional Info</div>
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

                <!-- Professional Information Tab -->
                <div class="tab-content" id="professional-tab">
                    <div class="form-section">
                        <h3>Professional Information</h3>
                        <form method="POST">
                            <div class="form-group">
                                <label for="specialization">Specialization</label>
                                <input type="text" id="specialization" name="specialization" 
                                       value="<?php echo htmlspecialchars($doctorInfo['specialization'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="license_number">License Number</label>
                                <input type="text" id="license_number" name="license_number" 
                                       value="<?php echo htmlspecialchars($doctorInfo['license_number'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="qualifications">Qualifications</label>
                                <textarea id="qualifications" name="qualifications" rows="3"><?php 
                                    echo htmlspecialchars($doctorInfo['qualifications'] ?? ''); 
                                ?></textarea>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="years_of_experience">Years of Experience</label>
                                    <input type="number" id="years_of_experience" name="years_of_experience" 
                                           value="<?php echo htmlspecialchars($doctorInfo['years_of_experience'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="consultation_fee">Consultation Fee ($)</label>
                                    <input type="number" id="consultation_fee" name="consultation_fee" step="0.01" 
                                           value="<?php echo htmlspecialchars($doctorInfo['consultation_fee'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="hospital_affiliation">Hospital Affiliation</label>
                                <input type="text" id="hospital_affiliation" name="hospital_affiliation" 
                                       value="<?php echo htmlspecialchars($doctorInfo['hospital_affiliation'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="available_days">Available Days</label>
                                <input type="text" id="available_days" name="available_days" 
                                       value="<?php echo htmlspecialchars($doctorInfo['available_days'] ?? ''); ?>" 
                                       placeholder="e.g., Monday, Wednesday, Friday" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="available_hours">Available Hours</label>
                                <input type="text" id="available_hours" name="available_hours" 
                                       value="<?php echo htmlspecialchars($doctorInfo['available_hours'] ?? ''); ?>" 
                                       placeholder="e.g., 9:00 AM - 5:00 PM" required>
                            </div>
                            
                            <button type="submit" name="update_doctor_profile" class="btn btn-primary">Save Professional Info</button>
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