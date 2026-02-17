<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Include your database connection
require_once '../../includes/database.php';

// Check if admin is logged in
if (!isset($_SESSION["admin_logged_in"]) || $_SESSION["admin_logged_in"] !== true) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access'
    ]);
    exit;
}

$action = $_POST['action'] ?? '';
$userEmail = $_POST['user_email'] ?? '';

if (empty($action) || empty($userEmail)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Action and user email are required'
    ]);
    exit;
}

try {
    if ($action === 'archive') {
        // Begin transaction
        $conn->begin_transaction();
        
        // Get all messages for this user
        $selectSql = "SELECT id, user_name, user_email, message, is_admin_reply, timestamp as created_at 
                      FROM chat_messages 
                      WHERE user_email = ? 
                      ORDER BY timestamp ASC";
        $selectStmt = $conn->prepare($selectSql);
        $selectStmt->bind_param("s", $userEmail);
        $selectStmt->execute();
        $result = $selectStmt->get_result();
        
        $archivedCount = 0;
        
        // Archive each message
        while ($row = $result->fetch_assoc()) {
            $archiveSql = "INSERT INTO chat_messages_archive 
                          (original_id, user_name, user_email, message, is_admin_reply, created_at) 
                          VALUES (?, ?, ?, ?, ?, ?)";
            
            $archiveStmt = $conn->prepare($archiveSql);
            
            $archiveStmt->bind_param(
                "isssss",
                $row['id'],
                $row['user_name'],
                $row['user_email'],
                $row['message'],
                $row['is_admin_reply'],
                $row['created_at']
            );
            
            if ($archiveStmt->execute()) {
                $archivedCount++;
            }
            $archiveStmt->close();
        }
        
        $selectStmt->close();
        
      
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Chat archived successfully',
            'archived_count' => $archivedCount
        ]);
        
    } elseif ($action === 'get_archived') {
        // Get archived messages for a user
        $sql = "SELECT * FROM chat_messages_archive 
                WHERE user_email = ? 
                ORDER BY id ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $userEmail);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
        
        $stmt->close();
        
        echo json_encode([
            'status' => 'success',
            'messages' => $messages
        ]);
        
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid action'
        ]);
    }
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        $conn->rollback();
    }
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>