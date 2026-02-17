<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'db_config.php';

$userEmail = $_POST['user_email'] ?? $_GET['user_email'] ?? '';
$lastId = isset($_POST['last_id']) ? intval($_POST['last_id']) : (isset($_GET['last_id']) ? intval($_GET['last_id']) : 0);

if (empty($userEmail)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'user_email is required'
    ]);
    exit;
}

// Check if admin is sending a message (has 'message' parameter)
if (isset($_POST['message']) && !empty($_POST['message'])) {
    // Admin sending reply
    $message = $_POST['message'];
    $adminName = $_POST['user_name'] ?? 'Admin';
    
    try {
        // Insert admin reply (is_admin_reply = 1)
        $sql = "INSERT INTO live_chat (user_name, user_email, message, is_admin_reply, created_at, is_read) 
                VALUES (?, ?, ?, 1, NOW(), 0)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $adminName, $userEmail, $message);
        
        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Reply sent successfully',
                'message_id' => $conn->insert_id
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to send reply'
            ]);
        }
        
        $stmt->close();
        $conn->close();
        exit;
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
        $conn->close();
        exit;
    }
}

// Fetch messages for this user
try {
    // Mark user messages as read when fetched (for admin view)
    $updateSql = "UPDATE chat_messages SET is_read = 1 
                  WHERE user_email = ? AND is_admin_reply = 0 AND is_read = 0";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("s", $userEmail);
    $updateStmt->execute();
    $updateStmt->close();
    
    // Fetch messages newer than last_id
    $sql = "SELECT id, user_name, user_email, message, is_admin_reply, created_at 
            FROM chat_messages 
            WHERE user_email = ? AND id > ? 
            ORDER BY created_at ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $userEmail, $lastId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'id' => (int)$row['id'],
            'user_name' => $row['user_name'],
            'user_email' => $row['user_email'],
            'message' => $row['message'],
            'is_admin_reply' => (int)$row['is_admin_reply'],
            'sender_type' => $row['is_admin_reply'] == 1 ? 'admin' : 'user', // For Android compatibility
            'created_at' => $row['created_at']
        ];
    }
    
    echo json_encode([
        'status' => 'success',
        'messages' => $messages,
        'count' => count($messages)
    ]);
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>