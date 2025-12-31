MediMind - Telemedicine Platform
A comprehensive telemedicine platform built with PHP and MySQL that connects patients with doctors through secure real-time consultations.
Features
For Patients
    •	Book appointments with doctors
    •	Real-time chat messaging
    •	Video consultations
    •	Upload and manage medical records
    •	View digital prescriptions
    •	Maintain health profile
For Doctors
    •	Manage appointments
    •	Access patient medical records
    •	Real-time chat with patients
    •	Create digital prescriptions
    •	Update professional profile
    •	Set availability schedule
For Administrators
    •	Approve doctor registrations
    •	Manage users and appointments
    •	View system analytics
    •	Configure platform settings
    •	Monitor system activity
Tech Stack
    •	Backend: PHP 7.4+, MySQL 8.0+
    •	Frontend: HTML5, CSS3, JavaScript
    •	Real-Time: WebSockets (chat), WebRTC (video)

Installation
1.	Clone repository
    git clone https://github.com/yourusername/medimind.git
    cd medimind
2.	Create database
    mysql -u root -p
    CREATE DATABASE medimind;
    exit;
3.	Import schema
    mysql -u root -p medimind < docs/schema.sql
4.	Configure
    cp includes/config.example.php includes/config.php
    # Edit includes/config.php with your database credentials
5.	Set permissions
    chmod 755 uploads/
    chmod 644 includes/config.php
6.	Access
    http://localhost/medimind
Default Credentials
      Role	Email	Password
      Admin	admin@medimind.com
      admin123
      Doctor	doctor@medimind.com
      doctor123
      Patient	patient@medimind.com
      patient123
⚠️ Change these immediately after first login!
Configuration
    Edit includes/config.php:
    define('DB_HOST', 'localhost');
    define('DB_USER', 'your_username');
    define('DB_PASS', 'your_password');
    define('DB_NAME', 'medimind');
    define('BASE_URL', 'http://localhost/medimind');
Project Structure
	medimind/
├── admin/                      # Administrator portal
│   ├── dashboard.php          # Admin dashboard
│   ├── users.php              # User management
│   ├── doctors.php            # Doctor management and approval
│   ├── reports.php            # Analytics and reporting
│   └── settings.php           # System configuration
│
├── doctor/                     # Doctor portal
│   ├── dashboard.php          # Doctor dashboard
│   ├── appointments.php       # Appointment management
│   ├── patients.php           # Patient records
│   ├── chat.php               # Patient messaging
│   └── profile.php            # Professional profile
│
├── patient/                    # Patient portal
│   ├── dashboard.php          # Patient dashboard
│   ├── appointments.php       # Book and manage appointments
│   ├── chat.php               # Doctor messaging
│   ├── medical_records.php    # Health records
│   └── profile.php            # Personal profile
│
├── api/                        # REST API endpoints
│   ├── appointments.php       # Appointment CRUD operations
│   └── chat.php               # Chat messaging API
│
├── assets/                     # Static resources
│   ├── css/
│   │   ├── admin.css          # Admin styling
│   │   ├── doctor.css         # Doctor portal styling
│   │   └── patient.css        # Patient portal styling
│   ├── images/                # Images and graphics
│   └── js/
│       └── script.js          # JavaScript utilities
│
├── includes/                   # Core PHP includes
│   ├── auth.php               # Authentication logic
│   ├── config.php             # Database and app configuration
│   └── functions.php          # Helper functions
│
├── uploads/                    # User uploaded files
│   └── medical_records/       # Medical documents
│
├── docs/                       # Documentation
│   ├── schema.sql             # Database schema
│   ├── MEDIMIND.docx          # Project documentation
│   └── structure.txt          # Directory structure
│
└── index.php                   # Landing page
Medical Disclaimer
MediMind is for informational purposes only. Always seek advice from qualified healthcare providers. Never disregard professional medical advice. In emergencies, contact local emergency services immediately.

