<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in and is a patient
if (!isLoggedIn() || !hasRole('patient')) {
    redirect(BASE_URL . '/index.php');
}

$userId = getCurrentUserId();
$user = getUserById($userId);

// Get all conversations for the patient
$conversations = [];
try {
    $stmt = $pdo->prepare("SELECT c.*, 
                          d.first_name AS doctor_first_name, d.last_name AS doctor_last_name, 
                          d.profile_picture AS doctor_profile_picture,
                          doc.specialization
                          FROM conversations c
                          JOIN users d ON c.doctor_id = d.user_id
                          JOIN doctors doc ON d.user_id = doc.user_id
                          WHERE c.patient_id = ?
                          ORDER BY c.last_message_at DESC");
    $stmt->execute([$userId]);
    $conversations = $stmt->fetchAll();

    // Get unread counts for each conversation
    foreach ($conversations as &$conversation) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages 
                              WHERE conversation_id = ? AND sender_id != ? AND is_read = FALSE");
        $stmt->execute([$conversation['conversation_id'], $userId]);
        $conversation['unread_count'] = $stmt->fetchColumn();
    }
} catch (PDOException $e) {
    error_log("Failed to fetch conversations: " . $e->getMessage());
    $errors[] = "Failed to load conversations. Please try again.";
}

// Handle starting a new conversation
if (isset($_GET['doctor_id'])) {
    $doctorId = (int) $_GET['doctor_id'];

    // Check if conversation already exists
    try {
        $stmt = $pdo->prepare("SELECT * FROM conversations 
                              WHERE patient_id = ? AND doctor_id = ?");
        $stmt->execute([$userId, $doctorId]);
        $existingConversation = $stmt->fetch();

        if ($existingConversation) {
            // Redirect to existing conversation
            redirect(BASE_URL . '/patient/chat.php?conversation_id=' . $existingConversation['conversation_id']);
        }

        // Check if doctor exists and is approved
        $stmt = $pdo->prepare("SELECT user_id FROM doctors WHERE user_id = ? AND is_approved = TRUE");
        $stmt->execute([$doctorId]);
        $doctor = $stmt->fetch();

        if (!$doctor) {
            $errors[] = "Doctor not found or not approved";
        } else {
            // Create new conversation
            $stmt = $pdo->prepare("INSERT INTO conversations (patient_id, doctor_id) VALUES (?, ?)");
            $stmt->execute([$userId, $doctorId]);
            $conversationId = $pdo->lastInsertId();

            // Log the action
            logAction('create', 'conversations', $conversationId);

            // Send notification to doctor
            $doctorUser = getUserById($doctorId);
            $patientName = $user['first_name'] . ' ' . $user['last_name'];
            $chatLink = BASE_URL . '/doctor/chat.php?conversation_id=' . $conversationId;

            sendNotification(
                $doctorId,
                'New Conversation Started',
                "Patient $patientName has started a conversation with you",
                $chatLink
            );

            // Redirect to the new conversation
            redirect(BASE_URL . '/patient/chat.php?conversation_id=' . $conversationId);
        }
    } catch (PDOException $e) {
        error_log("Failed to create conversation: " . $e->getMessage());
        $errors[] = "Failed to start conversation. Please try again.";
    }
}

// Get messages for a specific conversation
$currentConversation = null;
$messages = [];
if (isset($_GET['conversation_id'])) {
    $conversationId = (int) $_GET['conversation_id'];

    try {
        // Verify user has access to this conversation
        $stmt = $pdo->prepare("SELECT c.*, 
                              d.first_name AS doctor_first_name, d.last_name AS doctor_last_name, 
                              d.profile_picture AS doctor_profile_picture,
                              doc.specialization
                              FROM conversations c
                              JOIN users d ON c.doctor_id = d.user_id
                              JOIN doctors doc ON d.user_id = doc.user_id
                              WHERE c.conversation_id = ? AND c.patient_id = ?");
        $stmt->execute([$conversationId, $userId]);
        $currentConversation = $stmt->fetch();

        if ($currentConversation) {
            // Get messages
            $stmt = $pdo->prepare("SELECT m.*, u.first_name, u.last_name, u.profile_picture
                                  FROM messages m
                                  JOIN users u ON m.sender_id = u.user_id
                                  WHERE m.conversation_id = ?
                                  ORDER BY m.sent_at ASC");
            $stmt->execute([$conversationId]);
            $messages = $stmt->fetchAll();

            // Mark messages as read if they're for the current user
            $stmt = $pdo->prepare("UPDATE messages SET is_read = TRUE 
                                  WHERE conversation_id = ? AND sender_id != ? AND is_read = FALSE");
            $stmt->execute([$conversationId, $userId]);
        }
    } catch (PDOException $e) {
        error_log("Failed to fetch messages: " . $e->getMessage());
        $errors[] = "Failed to load messages. Please try again.";
    }
}

// Handle sending a new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && $currentConversation) {
    $messageText = sanitizeInput($_POST['message']);

    if (empty($messageText)) {
        $errors[] = "Message cannot be empty";
    } else {
        try {
            // Insert the message
            $stmt = $pdo->prepare("INSERT INTO messages 
                                  (conversation_id, sender_id, message_text) 
                                  VALUES (?, ?, ?)");
            $stmt->execute([$currentConversation['conversation_id'], $userId, $messageText]);
            $messageId = $pdo->lastInsertId();

            // Update conversation last message time
            $stmt = $pdo->prepare("UPDATE conversations SET last_message_at = NOW() WHERE conversation_id = ?");
            $stmt->execute([$currentConversation['conversation_id']]);

            // Log the action
            logAction('send_message', 'messages', $messageId);

            // Send notification to the doctor
            $doctorId = $currentConversation['doctor_id'];
            $patientName = $user['first_name'] . ' ' . $user['last_name'];
            $chatLink = BASE_URL . '/doctor/chat.php?conversation_id=' . $currentConversation['conversation_id'];

            sendNotification(
                $doctorId,
                'New Message',
                "You have a new message from $patientName",
                $chatLink
            );

            // Redirect to prevent form resubmission
            redirect(BASE_URL . '/patient/chat.php?conversation_id=' . $currentConversation['conversation_id']);
        } catch (PDOException $e) {
            error_log("Failed to send message: " . $e->getMessage());
            $errors[] = "Failed to send message. Please try again.";
        }
    }
}

// Handle closing a conversation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_conversation']) && $currentConversation) {
    try {
        $stmt = $pdo->prepare("UPDATE conversations SET status = 'closed' WHERE conversation_id = ?");
        $stmt->execute([$currentConversation['conversation_id']]);

        // Log the action
        logAction('close', 'conversations', $currentConversation['conversation_id']);

        $_SESSION['success_message'] = "Conversation closed successfully";
        redirect(BASE_URL . '/patient/chat.php');
    } catch (PDOException $e) {
        error_log("Failed to close conversation: " . $e->getMessage());
        $errors[] = "Failed to close conversation. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediMind - Patient Chat</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/patient.css">
    <style>
        .chat-container {
            display: flex;
            height: calc(100vh - 120px);
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }

        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background-color: #fff;
        }

        .conversation-list {
            width: 300px;
            border-right: 1px solid #e0e0e0;
            overflow-y: auto;
            background-color: #f9f9f9;
            height: 100%;
        }

        .conversation-item {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            cursor: pointer;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
            text-decoration: none;
            color: inherit;
        }

        .conversation-item:hover {
            background-color: #f0f0f0;
        }

        .conversation-item.active {
            background-color: #e0e0e0;
        }

        .conversation-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            overflow: hidden;
            background-color: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            font-size: 18px;
        }

        .conversation-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .conversation-info {
            flex: 1;
            min-width: 0;
            /* Prevent text overflow */
        }

        .conversation-doctor {
            font-weight: 600;
            margin-bottom: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conversation-specialization {
            font-size: 12px;
            color: #666;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conversation-time {
            font-size: 12px;
            color: #999;
            margin-top: 3px;
        }

        .unread-count {
            background-color: #4361ee;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            margin-left: 10px;
            flex-shrink: 0;
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            text-align: center;
            padding: 20px;
            color: #666;
        }

        .empty-state-icon {
            font-size: 60px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #555;
        }

        .empty-state-text {
            color: #777;
            margin-bottom: 20px;
            max-width: 300px;
            line-height: 1.4;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .conversation-list {
                width: 100%;
                max-height: 200px;
                border-right: none;
                border-bottom: 1px solid #e0e0e0;
            }

            .conversation-item {
                padding: 10px;
            }

            .conversation-avatar {
                width: 35px;
                height: 35px;
                font-size: 16px;
            }

            .conversation-doctor {
                font-size: 14px;
            }

            .conversation-specialization,
            .conversation-time {
                font-size: 11px;
            }
        }

        .unread-count {
            background-color: #4361ee;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            margin-left: 10px;
        }

        .chat-header {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            background-color: #f9f9f9;
        }

        .chat-header-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            overflow: hidden;
            background-color: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .chat-header-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .chat-header-info {
            flex: 1;
        }

        .chat-header-doctor {
            font-weight: 600;
        }

        .chat-header-specialization {
            font-size: 13px;
            color: #666;
        }

        .chat-header-actions {
            display: flex;
        }

        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background-color: #f5f5f5;
        }

        .message {
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
        }

        .message-content {
            max-width: 70%;
            padding: 10px 15px;
            border-radius: 18px;
            position: relative;
            word-wrap: break-word;
        }

        .message.sent {
            align-items: flex-end;
        }

        .message.sent .message-content {
            background-color: #4361ee;
            color: white;
            border-bottom-right-radius: 0;
        }

        .message.received {
            align-items: flex-start;
        }

        .message.received .message-content {
            background-color: white;
            color: #333;
            border-bottom-left-radius: 0;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .message-info {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }

        .message-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            margin-right: 10px;
            overflow: hidden;
            background-color: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .message-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .message-sender {
            font-weight: 500;
            font-size: 14px;
        }

        .message-time {
            font-size: 12px;
            color: #999;
            margin-left: 10px;
        }

        .chat-input {
            padding: 15px;
            border-top: 1px solid #e0e0e0;
            background-color: #f9f9f9;
        }

        .chat-input-form {
            display: flex;
        }

        .chat-input-field {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 20px;
            outline: none;
            font-family: 'Poppins', sans-serif;
            resize: none;
            max-height: 100px;
        }

        .chat-input-field:focus {
            border-color: #4361ee;
        }

        .chat-send-btn {
            margin-left: 10px;
            background-color: #4361ee;
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .chat-send-btn:hover {
            background-color: #3a56d4;
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            text-align: center;
            padding: 20px;
        }

        .empty-state-icon {
            font-size: 60px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #555;
        }

        .empty-state-text {
            color: #777;
            margin-bottom: 20px;
            max-width: 300px;
        }

        @media (max-width: 768px) {
            .chat-container {
                flex-direction: column;
                height: auto;
            }

            .conversation-list {
                width: 100%;
                max-height: 200px;
                border-right: none;
                border-bottom: 1px solid #e0e0e0;
            }

            .chat-area {
                height: 400px;
            }
        }
    </style>
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
                    <div class="user-name">
                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                    <div class="user-role">Patient</div>
                </div>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
                    <li><a href="medical_records.php"><i class="fas fa-file-medical"></i> Medical Records</a></li>
                    <li class="active"><a href="chat.php"><i class="fas fa-comments"></i> Messages</a></li>
                    <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                    <li><a href="../includes/auth.php?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="main-header">
                <h1>Messages</h1>
                <div class="header-actions">
                    <a href="appointments.php" class="btn btn-outline">
                        <i class="fas fa-calendar-alt"></i> Book Appointment
                    </a>
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

            <div class="chat-container">
                <!-- Conversation List -->
                <div class="conversation-list">
                    <?php if (!empty($conversations)): ?>
                        <?php foreach ($conversations as $conversation): ?>
                            <a href="?conversation_id=<?php echo $conversation['conversation_id']; ?>"
                                class="conversation-item <?php echo isset($currentConversation) && $currentConversation['conversation_id'] == $conversation['conversation_id'] ? 'active' : ''; ?>">
                                <div class="conversation-avatar">
                                    <?php if (!empty($conversation['doctor_profile_picture'])): ?>
                                        <img src="<?php echo BASE_URL . '/' . $conversation['doctor_profile_picture']; ?>"
                                            alt="Doctor Profile">
                                    <?php else: ?>
                                        <i class="fas fa-user-md"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="conversation-info">
                                    <div class="conversation-doctor">Dr.
                                        <?php echo htmlspecialchars($conversation['doctor_first_name'] . ' ' . $conversation['doctor_last_name']); ?>
                                    </div>
                                    <div class="conversation-specialization">
                                        <?php echo htmlspecialchars($conversation['specialization']); ?></div>
                                    <div class="conversation-time">
                                        <?php echo formatDate($conversation['last_message_at'] ?: $conversation['started_at'], 'M j, g:i a'); ?>
                                    </div>
                                </div>
                                <?php if ($conversation['unread_count'] > 0): ?>
                                    <div class="unread-count"><?php echo $conversation['unread_count']; ?></div>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-comment-slash"></i>
                            </div>
                            <div class="empty-state-title">No Conversations</div>
                            <div class="empty-state-text">You don't have any active conversations yet. Book an appointment
                                to start chatting with a doctor.</div>
                            <a href="appointments.php" class="btn btn-primary">Book Appointment</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Chat Area -->
                <div class="chat-area">
                    <?php if (isset($currentConversation)): ?>
                        <!-- Chat Header -->
                        <div class="chat-header">
                            <div class="chat-header-avatar">
                                <?php if (!empty($currentConversation['doctor_profile_picture'])): ?>
                                    <img src="<?php echo BASE_URL . '/' . $currentConversation['doctor_profile_picture']; ?>"
                                        alt="Doctor Profile">
                                <?php else: ?>
                                    <i class="fas fa-user-md"></i>
                                <?php endif; ?>
                            </div>
                            <div class="chat-header-info">
                                <div class="chat-header-doctor">Dr.
                                    <?php echo htmlspecialchars($currentConversation['doctor_first_name'] . ' ' . $currentConversation['doctor_last_name']); ?>
                                </div>
                                <div class="chat-header-specialization">
                                    <?php echo htmlspecialchars($currentConversation['specialization']); ?></div>
                            </div>
                            <div class="chat-header-actions">
                                <form method="POST"
                                    onsubmit="return confirm('Are you sure you want to close this conversation?');">
                                    <button type="submit" name="close_conversation" class="btn btn-icon btn-danger"
                                        title="Close Conversation">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Messages -->
                        <div class="chat-messages" id="chat-messages">
                            <?php if (!empty($messages)): ?>
                                <?php foreach ($messages as $message): ?>
                                    <div class="message <?php echo $message['sender_id'] == $userId ? 'sent' : 'received'; ?>">
                                        <div class="message-info">
                                            <div class="message-avatar">
                                                <?php if (!empty($message['profile_picture'])): ?>
                                                    <img src="<?php echo BASE_URL . '/' . $message['profile_picture']; ?>"
                                                        alt="Profile Picture">
                                                <?php else: ?>
                                                    <i class="fas fa-user-circle"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="message-sender">
                                                <?php echo htmlspecialchars($message['first_name'] . ' ' . $message['last_name']); ?>
                                            </div>
                                            <div class="message-time"><?php echo formatDate($message['sent_at'], 'g:i a'); ?></div>
                                        </div>
                                        <div class="message-content">
                                            <?php echo nl2br(htmlspecialchars($message['message_text'])); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <div class="empty-state-icon">
                                        <i class="fas fa-comment-medical"></i>
                                    </div>
                                    <div class="empty-state-title">Start a Conversation</div>
                                    <div class="empty-state-text">Send your first message to Dr.
                                        <?php echo htmlspecialchars($currentConversation['doctor_first_name'] . ' ' . $currentConversation['doctor_last_name']); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Message Input -->
                        <div class="chat-input">
                            <form method="POST" class="chat-input-form">
                                <textarea name="message" class="chat-input-field" placeholder="Type your message here..."
                                    required></textarea>
                                <button type="submit" class="chat-send-btn">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-comments"></i>
                            </div>
                            <div class="empty-state-title">Select a Conversation</div>
                            <div class="empty-state-text">Choose a conversation from the list or start a new one by booking
                                an appointment with a doctor.</div>
                            <a href="appointments.php" class="btn btn-primary">Book Appointment</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Auto-scroll to bottom of messages
        const messagesContainer = document.getElementById('chat-messages');
        if (messagesContainer) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        // Auto-hide success message after 5 seconds
        const successMessage = document.querySelector('.alert-success');
        if (successMessage) {
            setTimeout(() => {
                successMessage.classList.add('fade-out');
                setTimeout(() => successMessage.remove(), 500);
            }, 5000);
        }

        // Real-time message updates with AJAX polling
        let lastMessageId = <?php echo !empty($messages) ? end($messages)['message_id'] : 0; ?>;
        let currentConversationId = <?php echo isset($currentConversation) ? $currentConversation['conversation_id'] : 0; ?>;

        function checkForNewMessages() {
            if (currentConversationId === 0) return;

            fetch(`<?php echo BASE_URL; ?>/api/chat.php?conversation_id=${currentConversationId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.messages && data.messages.length > 0) {
                        const latestMessage = data.messages[data.messages.length - 1];
                        if (latestMessage.message_id > lastMessageId) {
                            // New message received, reload the page to show it
                            window.location.reload();
                        }
                    }
                })
                .catch(error => console.error('Error checking for new messages:', error));
        }

        // Check for new messages every 5 seconds
        setInterval(checkForNewMessages, 5000);

        // Handle form submission with AJAX
        const messageForm = document.querySelector('.chat-input-form');
        if (messageForm) {
            messageForm.addEventListener('submit', function (e) {
                e.preventDefault();

                const formData = new FormData(this);
                const messageInput = this.querySelector('textarea');

                fetch(this.action, {
                    method: 'POST',
                    body: formData
                })
                    .then(response => {
                        if (response.ok) {
                            // Clear the input field
                            messageInput.value = '';
                            // Reload to show the new message
                            window.location.reload();
                        } else {
                            alert('Failed to send message. Please try again.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred. Please try again.');
                    });
            });
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