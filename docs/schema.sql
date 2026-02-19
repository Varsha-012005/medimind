-- Database: medimind
CREATE DATABASE medimind;
USE medimind;
-- Users Table (for both patients and doctors)
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    date_of_birth DATE,
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(50),
    state VARCHAR(50),
    zip_code VARCHAR(20),
    country VARCHAR(50),
    profile_picture VARCHAR(255),
    user_type ENUM('patient', 'doctor', 'admin') NOT NULL,
    is_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Doctors Specific Information
CREATE TABLE doctors (
    doctor_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    license_number VARCHAR(50) NOT NULL UNIQUE,
    specialization VARCHAR(100) NOT NULL,
    qualifications TEXT,
    years_of_experience INT,
    hospital_affiliation VARCHAR(100),
    consultation_fee DECIMAL(10,2) NOT NULL,
    available_days VARCHAR(50),
    available_hours VARCHAR(100),
    is_approved BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Patient Health Profiles
CREATE TABLE patient_health_profiles (
    profile_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'),
    height DECIMAL(5,2),
    weight DECIMAL(5,2),
    allergies TEXT,
    current_medications TEXT,
    past_operations TEXT,
    chronic_conditions TEXT,
    family_medical_history TEXT,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Symptoms Library
CREATE TABLE symptoms (
    symptom_id INT AUTO_INCREMENT PRIMARY KEY,
    symptom_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    category VARCHAR(50)
);

-- Conditions/Diseases Library
CREATE TABLE conditions (
    condition_id INT AUTO_INCREMENT PRIMARY KEY,
    condition_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    category VARCHAR(50)
);

-- Symptom-Condition Mapping
CREATE TABLE symptom_condition_mapping (
    mapping_id INT AUTO_INCREMENT PRIMARY KEY,
    symptom_id INT NOT NULL,
    condition_id INT NOT NULL,
    probability_score DECIMAL(5,2),
    FOREIGN KEY (symptom_id) REFERENCES symptoms(symptom_id),
    FOREIGN KEY (condition_id) REFERENCES conditions(condition_id)
);

-- AI Diagnosis Sessions
CREATE TABLE diagnosis_sessions (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    symptoms_input TEXT NOT NULL,
    ai_response TEXT,
    possible_conditions TEXT,
    confidence_scores TEXT,
    recommended_actions TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Appointments
CREATE TABLE appointments (
    appointment_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status ENUM('scheduled', 'completed', 'cancelled', 'no-show') DEFAULT 'scheduled',
    reason TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Chat Conversations
CREATE TABLE conversations (
    conversation_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_message_at TIMESTAMP,
    status ENUM('active', 'closed') DEFAULT 'active',
    FOREIGN KEY (patient_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Chat Messages
CREATE TABLE messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    message_text TEXT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (conversation_id) REFERENCES conversations(conversation_id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Video Consultations
CREATE TABLE video_consultations (
    consultation_id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    room_id VARCHAR(100) NOT NULL UNIQUE,
    start_time TIMESTAMP,
    end_time TIMESTAMP,
    duration INT DEFAULT 0,
    notes TEXT,
    recording_url VARCHAR(255),
    FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE SET NULL,
    FOREIGN KEY (patient_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Medical Records
CREATE TABLE medical_records (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    record_type ENUM('prescription', 'lab_result', 'diagnosis', 'other') NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    file_path VARCHAR(255),
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Prescriptions
CREATE TABLE prescriptions (
    prescription_id INT AUTO_INCREMENT PRIMARY KEY,
    record_id INT NOT NULL,
    doctor_id INT NOT NULL,
    medication_name VARCHAR(100) NOT NULL,
    dosage VARCHAR(50) NOT NULL,
    frequency VARCHAR(50) NOT NULL,
    duration VARCHAR(50) NOT NULL,
    instructions TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (record_id) REFERENCES medical_records(record_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Notifications
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    link VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Audit Log
CREATE TABLE audit_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(50) NOT NULL,
    table_affected VARCHAR(50) NOT NULL,
    record_id INT,
    ip_address VARCHAR(50),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

CREATE TABLE system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    description TEXT
);

-- Modify the conversations table to support group chats
ALTER TABLE conversations
ADD COLUMN is_group BOOLEAN DEFAULT FALSE,
ADD COLUMN conversation_name VARCHAR(100),
ADD COLUMN created_by INT,
ADD FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL;

-- Create a table for conversation participants
CREATE TABLE conversation_participants (
    participant_id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_read_at TIMESTAMP,
    is_admin BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (conversation_id) REFERENCES conversations(conversation_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY (conversation_id, user_id)
);

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('system_name', 'MediMind', 'The name displayed throughout the system'),
('system_timezone', 'UTC', 'Default timezone for all date/time operations'),
('maintenance_mode', '0', 'When enabled, only administrators can access the system'),
('primary_color', '#4361ee', 'Main color used throughout the interface'),
('accent_color', '#7209b7', 'Secondary color used for highlights'),
('dark_mode', '0', 'Enable dark theme for all users'),
('default_appointment_duration', '30', 'Standard length for new appointments (minutes)'),
('booking_lead_time', '24', 'Minimum hours required before an appointment'),
('booking_window', '90', 'Maximum days in advance for booking'),
('allow_same_day_appointments', '1', 'Enable booking for the current day'),
('cancellation_notice', '24', 'Hours before appointment when cancellation is allowed'),
('late_cancellation_fee', '20', 'Percentage of fee charged for late cancellations'),
('no_show_fee', '50', 'Percentage of fee charged for no-shows'),
('smtp_host', '', 'Server for sending email notifications'),
('smtp_port', '587', 'Port for email server connection'),
('smtp_username', '', 'Email account username'),
('smtp_password', '', 'Email account password'),
('from_email', 'noreply@medimind.example.com', 'Sender address for system emails'),
('from_name', 'MediMind System', 'Sender name for system emails'),
('reminder_hours', '24', 'Hours before appointment to send reminder'),
('new_appointment_alert', '1', 'Notify doctor when appointment is booked'),
('cancellation_alert', '1', 'Notify doctor when appointment is cancelled'),
('password_complexity', 'medium', 'Minimum requirements for user passwords'),
('password_expiration', '90', 'Days before password must be changed (0 for never)'),
('failed_login_attempts', '5', 'Maximum attempts before account lockout'),
('lockout_duration', '15', 'Minutes to lock account after failed attempts'),
('require_2fa_admin', '0', 'Require 2FA for admin accounts'),
('session_timeout', '30', 'Minutes of inactivity before automatic logout'),
('single_session', '0', 'Allow only one active session per user');

-- Sample Data Insertion

-- Insert sample symptoms
INSERT INTO symptoms (symptom_name, description, category) VALUES
('Fever', 'Elevated body temperature above normal range', 'General'),
('Headache', 'Pain in any region of the head', 'Neurological'),
('Cough', 'Sudden expulsion of air from the lungs', 'Respiratory'),
('Fatigue', 'Feeling of tiredness or exhaustion', 'General'),
('Nausea', 'Feeling of sickness with an inclination to vomit', 'Digestive');

-- Insert sample conditions
INSERT INTO conditions (condition_name, description, category) VALUES
('Common Cold', 'Viral infection of the upper respiratory tract', 'Respiratory'),
('Influenza', 'Highly contagious viral infection of the respiratory passages', 'Respiratory'),
('Migraine', 'Recurrent headache disorder', 'Neurological'),
('Gastroenteritis', 'Inflammation of the stomach and intestines', 'Digestive'),
('Hypertension', 'Abnormally high blood pressure', 'Cardiovascular');

-- Insert symptom-condition mapping
INSERT INTO symptom_condition_mapping (symptom_id, condition_id, probability_score) VALUES
(1, 1, 0.75), (1, 2, 0.85), (2, 3, 0.90), (3, 1, 0.80), (3, 2, 0.70),
(4, 2, 0.65), (4, 4, 0.60), (5, 4, 0.85), (1, 5, 0.30), (2, 5, 0.25);