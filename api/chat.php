<?php
/**
 * MediMind - Chat API Endpoint
 * 
 * This file handles all chat-related API requests for the MediMind platform.
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
            handleChatGetRequest();
            break;
        case 'POST':
            handleChatPostRequest();
            break;
        case 'PUT':
            handleChatPutRequest();
            break;
        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (PDOException $e) {
    error_log("Chat API Error: " . $e->getMessage());
    jsonResponse(['error' => 'Database error occurred'], 500);
} catch (Exception $e) {
    error_log("Chat API Error: " . $e->getMessage());
    jsonResponse(['error' => $e->getMessage()], 400);
}

/**
 * Handle GET requests for chat
 */
function handleChatGetRequest() {
    global $pdo;
    
    $userId = getCurrentUserId();
    $userType = $_SESSION['user_type'];
    
    // Check if we're getting conversations or messages
    if (isset($_GET['conversation_id'])) {
        // Get messages for a specific conversation
        $conversationId = (int)$_GET['conversation_id'];
        
        // Verify user has access to this conversation
        $stmt = $pdo->prepare("SELECT * FROM conversations 
                              WHERE conversation_id = ? AND 
                              (patient_id = ? OR doctor_id = ?)");
        $stmt->execute([$conversationId, $userId, $userId]);
        $conversation = $stmt->fetch();
        
        if (!$conversation) {
            jsonResponse(['error' => 'Conversation not found or access denied'], 404);
        }
        
        // Get messages
        $stmt = $pdo->prepare("SELECT m.*, u.first_name, u.last_name, u.profile_picture
                              FROM messages m
                              JOIN users u ON m.sender_id = u.user_id
                              WHERE m.conversation_id = ?
                              ORDER BY m.sent_at ASC");
        $stmt->execute([$conversationId]);
        $messages = $stmt->fetchAll();
        
        // Mark messages as read if they're for the current user
        if ($conversation['patient_id'] === $userId || $conversation['doctor_id'] === $userId) {
            $stmt = $pdo->prepare("UPDATE messages SET is_read = TRUE 
                                  WHERE conversation_id = ? AND sender_id != ? AND is_read = FALSE");
            $stmt->execute([$conversationId, $userId]);
        }
        
        jsonResponse([
            'conversation' => $conversation,
            'messages' => $messages
        ]);
    } else {
        // Get all conversations for the user
        $query = "SELECT c.*, 
                 p.first_name AS patient_first_name, p.last_name AS patient_last_name, p.profile_picture AS patient_profile_picture,
                 d.first_name AS doctor_first_name, d.last_name AS doctor_last_name, d.profile_picture AS doctor_profile_picture
                 FROM conversations c
                 JOIN users p ON c.patient_id = p.user_id
                 JOIN users d ON c.doctor_id = d.user_id
                 WHERE ";
        
        if ($userType === 'patient') {
            $query .= "c.patient_id = ?";
        } else {
            $query .= "c.doctor_id = ?";
        }
        
        $query .= " ORDER BY c.last_message_at DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId]);
        $conversations = $stmt->fetchAll();
        
        // Get unread counts for each conversation
        foreach ($conversations as &$conversation) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages 
                                  WHERE conversation_id = ? AND sender_id != ? AND is_read = FALSE");
            $stmt->execute([$conversation['conversation_id'], $userId]);
            $conversation['unread_count'] = $stmt->fetchColumn();
        }
        
        jsonResponse($conversations);
    }
}

/**
 * Handle POST requests for chat (create conversation or send message)
 */
function handleChatPostRequest() {
    global $pdo;
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        jsonResponse(['error' => 'Invalid CSRF token'], 403);
    }
    
    $userId = getCurrentUserId();
    $userType = $_SESSION['user_type'];
    
    if (isset($_POST['message'])) {
        // Send a message
        $requiredFields = ['conversation_id', 'message'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                jsonResponse(['error' => "Missing required field: $field"], 400);
            }
        }
        
        $conversationId = (int)$_POST['conversation_id'];
        $messageText = sanitizeInput($_POST['message']);
        
        // Verify user has access to this conversation
        $stmt = $pdo->prepare("SELECT * FROM conversations 
                              WHERE conversation_id = ? AND 
                              (patient_id = ? OR doctor_id = ?)");
        $stmt->execute([$conversationId, $userId, $userId]);
        $conversation = $stmt->fetch();
        
        if (!$conversation) {
            jsonResponse(['error' => 'Conversation not found or access denied'], 404);
        }
        
        // Insert the message
        $stmt = $pdo->prepare("INSERT INTO messages 
                              (conversation_id, sender_id, message_text) 
                              VALUES (?, ?, ?)");
        $stmt->execute([$conversationId, $userId, $messageText]);
        
        $messageId = $pdo->lastInsertId();
        
        // Update conversation last message time
        $stmt = $pdo->prepare("UPDATE conversations SET last_message_at = NOW() WHERE conversation_id = ?");
        $stmt->execute([$conversationId]);
        
        // Log the action
        logAction('send_message', 'messages', $messageId);
        
        // Get the created message with user info
        $stmt = $pdo->prepare("SELECT m.*, u.first_name, u.last_name, u.profile_picture
                              FROM messages m
                              JOIN users u ON m.sender_id = u.user_id
                              WHERE m.message_id = ?");
        $stmt->execute([$messageId]);
        $message = $stmt->fetch();
        
        // Send notification to the other user
        $otherUserId = $conversation['patient_id'] === $userId ? $conversation['doctor_id'] : $conversation['patient_id'];
        $user = getUserById($userId);
        $userName = $user['first_name'] . ' ' . $user['last_name'];
        $chatLink = BASE_URL . '/' . ($userType === 'doctor' ? 'doctor' : 'patient') . '/chat.php?conversation_id=' . $conversationId;
        
        sendNotification(
            $otherUserId,
            'New Message',
            "You have a new message from $userName",
            $chatLink
        );
        
        jsonResponse($message, 201);
    } else {
        // Create a new conversation
        if ($userType !== 'patient') {
            jsonResponse(['error' => 'Only patients can start conversations'], 403);
        }
        
        if (empty($_POST['doctor_id'])) {
            jsonResponse(['error' => 'Missing doctor ID'], 400);
        }
        
        $doctorId = (int)$_POST['doctor_id'];
        
        // Check if doctor exists and is approved
        $stmt = $pdo->prepare("SELECT user_id FROM doctors WHERE user_id = ? AND is_approved = TRUE");
        $stmt->execute([$doctorId]);
        if (!$stmt->fetch()) {
            jsonResponse(['error' => 'Doctor not found or not approved'], 404);
        }
        
        // Check if conversation already exists
        $stmt = $pdo->prepare("SELECT * FROM conversations 
                              WHERE patient_id = ? AND doctor_id = ?");
        $stmt->execute([$userId, $doctorId]);
        $existingConversation = $stmt->fetch();
        
        if ($existingConversation) {
            jsonResponse(['error' => 'Conversation already exists'], 409);
        }
        
        // Create new conversation
        $stmt = $pdo->prepare("INSERT INTO conversations 
                              (patient_id, doctor_id) 
                              VALUES (?, ?)");
        $stmt->execute([$userId, $doctorId]);
        
        $conversationId = $pdo->lastInsertId();
        
        // Log the action
        logAction('create', 'conversations', $conversationId);
        
        // Get the created conversation with user info
        $stmt = $pdo->prepare("SELECT c.*, 
                              p.first_name AS patient_first_name, p.last_name AS patient_last_name, p.profile_picture AS patient_profile_picture,
                              d.first_name AS doctor_first_name, d.last_name AS doctor_last_name, d.profile_picture AS doctor_profile_picture
                              FROM conversations c
                              JOIN users p ON c.patient_id = p.user_id
                              JOIN users d ON c.doctor_id = d.user_id
                              WHERE c.conversation_id = ?");
        $stmt->execute([$conversationId]);
        $conversation = $stmt->fetch();
        
        // Send notification to doctor
        $patient = getUserById($userId);
        $patientName = $patient['first_name'] . ' ' . $patient['last_name'];
        $chatLink = BASE_URL . '/doctor/chat.php?conversation_id=' . $conversationId;
        
        sendNotification(
            $doctorId,
            'New Conversation Started',
            "Patient $patientName has started a conversation with you",
            $chatLink
        );
        
        jsonResponse($conversation, 201);
    }
}

/**
 * Handle PUT requests for chat (mark messages as read, update conversation status)
 */
function handleChatPutRequest() {
    global $pdo;
    
    // Parse JSON input for PUT requests
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(['error' => 'Invalid JSON input'], 400);
    }
    
    $userId = getCurrentUserId();
    
    if (isset($input['conversation_id'])) {
        // Update conversation status
        $conversationId = (int)$input['conversation_id'];
        
        // Verify user has access to this conversation
        $stmt = $pdo->prepare("SELECT * FROM conversations 
                              WHERE conversation_id = ? AND 
                              (patient_id = ? OR doctor_id = ?)");
        $stmt->execute([$conversationId, $userId, $userId]);
        $conversation = $stmt->fetch();
        
        if (!$conversation) {
            jsonResponse(['error' => 'Conversation not found or access denied'], 404);
        }
        
        // Only allow status update to 'closed'
        if (isset($input['status']) && $input['status'] === 'closed') {
            $stmt = $pdo->prepare("UPDATE conversations SET status = 'closed' WHERE conversation_id = ?");
            $stmt->execute([$conversationId]);
            
            // Log the action
            logAction('close', 'conversations', $conversationId);
            
            jsonResponse(['message' => 'Conversation closed successfully']);
        } else {
            jsonResponse(['error' => 'Invalid status update'], 400);
        }
    } elseif (isset($input['message_ids'])) {
        // Mark messages as read
        if (!is_array($input['message_ids'])) {
            jsonResponse(['error' => 'message_ids must be an array'], 400);
        }
        
        $messageIds = array_map('intval', $input['message_ids']);
        $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
        
        // Verify user has access to these messages
        $stmt = $pdo->prepare("SELECT m.message_id FROM messages m
                              JOIN conversations c ON m.conversation_id = c.conversation_id
                              WHERE m.message_id IN ($placeholders) AND 
                              (c.patient_id = ? OR c.doctor_id = ?) AND
                              m.sender_id != ?");
        $params = array_merge($messageIds, [$userId, $userId, $userId]);
        $stmt->execute($params);
        
        $validMessageIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($validMessageIds) !== count($messageIds)) {
            jsonResponse(['error' => 'Some messages not found or access denied'], 403);
        }
        
        // Mark messages as read
        $stmt = $pdo->prepare("UPDATE messages SET is_read = TRUE 
                              WHERE message_id IN ($placeholders)");
        $stmt->execute($messageIds);
        
        jsonResponse(['message' => 'Messages marked as read']);
    } else {
        jsonResponse(['error' => 'Missing conversation_id or message_ids'], 400);
    }
}