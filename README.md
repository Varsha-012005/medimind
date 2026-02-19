 MediMind - Complete Telemedicine Platform
https://img.shields.io/badge/MediMind-v1.0-blue
https://img.shields.io/badge/PHP-8.0+-purple
https://img.shields.io/badge/MySQL-5.7+-orange
https://img.shields.io/badge/JavaScript-ES6-yellow
https://img.shields.io/badge/License-MIT-green
MediMind is a comprehensive telemedicine platform that connects patients with licensed doctors for remote consultations through secure chat and video calls. The platform enables seamless appointment scheduling, medical record management, and real-time communication between healthcare providers and patients.
 Core Features
For Patients
•	 Appointment Booking: Schedule consultations with available doctors
•	 Real-time Chat: Communicate with doctors via secure messaging
•	 Medical Records: Upload and manage health documents
•	 Health Profile: Track vitals and medical history
•	 Notifications: Receive appointment reminders and updates
For Doctors
•	 Appointment Management: View and manage patient appointments
•	 Patient List: Access patient history and records
•	 Secure Messaging: Chat with patients in real-time
•	 Dashboard: Track consultations and earnings
•	 Prescriptions: Create and manage digital prescriptions
For Admins
•	 User Management: Manage patients, doctors, and admins
•	 Doctor Verification: Approve/reject doctor registrations
•	 Reports & Analytics: Generate platform usage reports
•	 System Settings: Configure platform parameters
•	 Audit Logs: Track all platform activities

Technology Stack
Backend
•	PHP 8.0+ - Core application logic
•	MySQL 5.7+ - Database management
•	PDO - Secure database connections
•	Session Management - User authentication
Frontend
•	HTML5/CSS3 - Responsive interface
•	JavaScript (ES6) - Interactive features
•	Font Awesome 6 - Icons
•	AJAX - Asynchronous requests
Security
•	Password Hashing - Bcrypt with configurable cost
•	CSRF Protection - Token-based validation
•	Input Sanitization - XSS prevention
•	Prepared Statements - SQL injection protection
•	Session Security - HTTP-only cookies

 Database Schema (Core Tables)
Table	Purpose
users	User accounts (patients, doctors, admins)
doctors	Doctor-specific information
patient_health_profiles	Patient medical history
appointments	Appointment scheduling
conversations	Chat threads
messages	Chat messages
medical_records	Health documents
prescriptions	Digital prescriptions
notifications	System notifications
audit_log	Activity tracking
system_settings	Platform configuration
Key Relationships: All tables linked to users(user_id) with proper foreign key constraints.

 Installation in 5 Steps
Prerequisites
•	PHP 8.0 or higher
•	MySQL 5.7 or higher
•	Apache/Nginx web server
•	Composer (optional)
Step 1: Clone & Setup
git clone https://github.com/yourusername/medimind.git
cd medimind
Step 2: Create Database
# Create database and tables
mysql -u root -p < docs/schema.sql
Step 3: Configure Database
Edit includes/config.php:
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
define('DB_NAME', 'medimind');
define('BASE_URL', 'http://localhost/medimind');
Step 4: Set Permissions
# Create upload directories
mkdir -p uploads/medical_records uploads/profile_pictures
chmod -R 755 uploads/
Step 5: Configure Web Server
For Apache/XAMPP:
•	Place project in htdocs/ directory
•	Access via http://localhost/medimind

🔐 Security Features
Feature	Implementation
Password Security	Bcrypt hashing with configurable cost
CSRF Protection	Token validation on all forms
XSS Prevention	HTML special characters encoding
SQL Injection	PDO prepared statements
Session Security	HTTP-only cookies, regeneration
File Upload	Type validation, size limits
Access Control	Role-based authentication

👥 User Roles & Access
Patient
•	Register/login to platform
•	Book appointments with doctors
•	Upload medical records
•	Chat with assigned doctors
•	View health dashboard

Doctor
•	Manage appointment schedule
•	Access patient medical history
•	Respond to patient messages
•	Issue digital prescriptions
•	Update availability
Admin
•	Verify doctor registrations
•	Manage all users
•	Generate system reports
•	Configure platform settings
•	View audit logs

User Flow
Patient Journey
1.	Register → Choose 'Patient' account type
2.	Complete Profile → Add health information
3.	Find Doctor → Browse available specialists
4.	Book Appointment → Select date and time
5.	Consult → Chat or video call with doctor
6.	Receive Care → Get prescriptions, upload records
Doctor Journey
1.	Register → Choose 'Doctor' account type
2.	Get Verified → Admin approves profile
3.	Set Availability → Define working hours
4.	Manage Appointments → Accept/reschedule
5.	Consult Patients → Chat and prescribe
6.	Track Progress → Monitor patient history

 Contributing
1.	Fork the repository
2.	Create feature branch (git checkout -b feature/AmazingFeature)
3.	Commit changes (git commit -m 'Add AmazingFeature')
4.	Push to branch (git push origin feature/AmazingFeature)
5.	Open a Pull Request

