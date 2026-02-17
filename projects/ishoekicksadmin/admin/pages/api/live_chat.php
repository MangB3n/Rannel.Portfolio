<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

//live chat api admin

// Include your database connection
require_once '../../includes/database.php';

$userName = $_POST['user_name'] ?? '';
$userEmail = $_POST['user_email'] ?? '';
$userMessage = $_POST['user_message'] ?? '';

if (empty($userName) || empty($userEmail) || empty($userMessage)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'All fields are required'
    ]);
    exit;
}

try {
    // Insert user message (is_admin_reply = 0 means it's from user)
    $sql = "INSERT INTO chat_messages (user_name, user_email, message, is_admin_reply, created_at, is_read) 
            VALUES (?, ?, ?, 0, NOW(), 0)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $userName, $userEmail, $userMessage);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Message saved successfully',
            'message_id' => $conn->insert_id
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to save message'
        ]);
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>